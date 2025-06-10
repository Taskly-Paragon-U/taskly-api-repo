<?php

namespace App\Http\Controllers;

use App\Models\SubmittedTimesheet;
use App\Models\Contract;
use App\Models\SubmitterSupervisor;
use App\Models\SupervisorApproval; // New model we'll need
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class SubmittedTimesheetController extends Controller
{
    /**
     * GET /api/submissions?task_id=&contract_id=
     */
    public function index(Request $request)
    {
        $user       = $request->user();
        $taskId     = $request->query('task_id');
        $contractId = $request->query('contract_id');

        $contract = Contract::findOrFail($contractId);
        $role     = $contract->members()
                       ->where('users.id', $user->id)
                       ->first()?->pivot->role;

        $baseQuery = SubmittedTimesheet::with(['submitter', 'supervisorApprovals.supervisor'])
                       ->where('task_id', $taskId)
                       ->where('contract_id', $contractId);

        $submissions = [];

        // === A) SUPERVISOR: see only their submitters with approval status
        if ($role === 'supervisor') {
            $supervisedIds = SubmitterSupervisor::where('contract_id', $contractId)
                                ->where('supervisor_id', $user->id)
                                ->pluck('submitter_id');

            $subs = $baseQuery
                    ->whereIn('user_id', $supervisedIds)
                    ->latest('submitted_at')
                    ->get();

            foreach ($subs as $s) {
                // Check if this supervisor has already approved/rejected
                $myApproval = $s->supervisorApprovals->where('supervisor_id', $user->id)->first();
                $myStatus = $myApproval ? $myApproval->status : 'pending';
                
                // Get assigned supervisors count for this submitter
                $assignedSupervisors = SubmitterSupervisor::where('contract_id', $contractId)
                    ->where('submitter_id', $s->user_id)
                    ->count();

                $submissions[] = [
                    'id'              => $s->id,
                    'submitter'       => [
                        'id'   => $s->submitter->id,
                        'name' => $s->submitter->name,
                    ],
                    'submittedAt'     => $s->submitted_at->toDateTimeString(),
                    'fileUrl'         => Storage::url($s->file_path),
                    'status'          => $myStatus,
                    'rejectionReason' => $myApproval?->rejection_reason,
                    'requiresMultipleApprovals' => $assignedSupervisors > 1,
                ];
            }
        }

        // === B) OWNER: see all submitters with detailed supervisor status
        elseif ($role === 'owner') {
            $subs = $baseQuery->latest('submitted_at')->get();

            foreach ($subs as $s) {
                // Get assigned supervisors for this submitter
                $assignments = SubmitterSupervisor::where('contract_id', $contractId)
                                 ->where('submitter_id', $s->user_id)
                                 ->with('supervisor')
                                 ->get();

                $supervisorStatuses = [];
                $approvedCount = 0;
                
                foreach ($assignments as $assignment) {
                    $approval = $s->supervisorApprovals->where('supervisor_id', $assignment->supervisor_id)->first();
                    $status = $approval ? $approval->status : 'pending';
                    
                    if ($status === 'approved') {
                        $approvedCount++;
                    }
                    
                    $supervisorStatuses[] = [
                        'id' => $assignment->supervisor_id,
                        'name' => $assignment->supervisor->name,
                        'status' => $status,
                        'rejectionReason' => $approval?->rejection_reason,
                    ];
                }

                // Overall status: approved only if ALL supervisors approved
                $overallStatus = 'pending';
                if ($approvedCount === count($supervisorStatuses)) {
                    $overallStatus = 'approved';
                } elseif (collect($supervisorStatuses)->contains('status', 'rejected')) {
                    $overallStatus = 'rejected';
                }

                $submissions[] = [
                    'id'                  => $s->id,
                    'submitter'           => [
                        'id'   => $s->submitter->id,
                        'name' => $s->submitter->name,
                    ],
                    'submittedAt'         => $s->submitted_at->toDateTimeString(),
                    'fileUrl'             => Storage::url($s->file_path),
                    'supervisorStatuses'  => $supervisorStatuses,
                    'approvedCount'       => $approvedCount,
                    'totalSupervisors'    => count($supervisorStatuses),
                    'overallStatus'       => $overallStatus,
                ];
            }
        }

        // === C) SUBMITTER: get their own latest submission
        $submission = null;
        if ($role === 'submitter') {
            $latest = $baseQuery
                      ->where('user_id', $user->id)
                      ->latest('submitted_at')
                      ->first();

            if ($latest) {
                // Calculate overall status based on supervisor approvals
                $assignments = SubmitterSupervisor::where('contract_id', $contractId)
                                 ->where('submitter_id', $user->id)
                                 ->count();
                
                $approvals = $latest->supervisorApprovals;
                $approvedCount = $approvals->where('status', 'approved')->count();
                $rejectedCount = $approvals->where('status', 'rejected')->count();
                
                $overallStatus = 'pending';
                if ($rejectedCount > 0) {
                    $overallStatus = 'rejected';
                } elseif ($approvedCount === $assignments) {
                    $overallStatus = 'approved';
                }

                $submission = [
                    'id'              => $latest->id,
                    'file_path'       => $latest->file_path,
                    'file_name'       => $latest->file_name,
                    'fileUrl'         => Storage::url($latest->file_path),
                    'status'          => $overallStatus,
                    'rejectionReason' => $approvals->where('status', 'rejected')->first()?->rejection_reason,
                ];
            }
        }

        return response()->json([
            'submission'  => $submission,
            'submissions' => $submissions,
        ]);
    }

    /**
     * PATCH /api/contracts/{contract}/timesheet-tasks/{task}/submissions/{submission}
     */
    public function updateStatus(
        Request $request,
        int $contract,
        int $task,
        int $submission
    ) {
        $data = $request->validate([
            'status'           => 'required|string|in:pending,approved,rejected',
            'rejection_reason' => 'nullable|string|max:255',
        ]);

        $timesheet = SubmittedTimesheet::findOrFail($submission);
        $supervisorId = $request->user()->id;

        // Create or update supervisor approval
        $approval = SupervisorApproval::updateOrCreate(
            [
                'submitted_timesheet_id' => $timesheet->id,
                'supervisor_id' => $supervisorId,
            ],
            [
                'status' => $data['status'],
                'rejection_reason' => $data['status'] === 'rejected' ? ($data['rejection_reason'] ?? null) : null,
                'reviewed_at' => now(),
            ]
        );

        // Update the main timesheet status based on all supervisor approvals
        $this->updateTimesheetOverallStatus($timesheet);

        return response()->json(['submission' => $timesheet->fresh(['supervisorApprovals'])]);
    }

    private function updateTimesheetOverallStatus(SubmittedTimesheet $timesheet)
    {
        // Get total assigned supervisors
        $totalSupervisors = SubmitterSupervisor::where('contract_id', $timesheet->contract_id)
            ->where('submitter_id', $timesheet->user_id)
            ->count();

        // Get all approvals for this timesheet
        $approvals = $timesheet->supervisorApprovals;
        $approvedCount = $approvals->where('status', 'approved')->count();
        $rejectedCount = $approvals->where('status', 'rejected')->count();

        // Determine overall status
        if ($rejectedCount > 0) {
            $timesheet->status = 'rejected';
        } elseif ($approvedCount === $totalSupervisors) {
            $timesheet->status = 'approved';
        } else {
            $timesheet->status = 'pending';
        }

        $timesheet->save();
    }

    public function destroy(Request $request, $id)
    {
        $user = $request->user();
        $s    = SubmittedTimesheet::findOrFail($id);

        if ($s->user_id !== $user->id) {
            return response()->json(['message' => 'Forbidden'], 403);
        }

        Storage::disk('public')->delete($s->file_path);
        $s->delete();

        return response()->json(null, 204);
    }
}