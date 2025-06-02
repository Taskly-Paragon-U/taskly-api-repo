<?php

namespace App\Http\Controllers;

use App\Models\SubmittedTimesheet;
use App\Models\Contract;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class SubmittedTimesheetController extends Controller
{
    /**
     * GET /api/submissions?task_id=&contract_id=
     *
     * Returns:
     * {
     *   submission: { … } | null,       // for submitter only
     *   submissions: [ … ]              // for owner & supervisor
     * }
     */
    public function index(Request $request)
    {
        $user       = $request->user();
        $taskId     = $request->query('task_id');
        $contractId = $request->query('contract_id');

        // 1) Find contract and current user's role within it
        $contract = Contract::findOrFail($contractId);

        $role = $contract->members()
            ->where('users.id', $user->id)
            ->first()
            ?->pivot->role;

        // 2) Base query for fetching submissions
        $baseQuery = SubmittedTimesheet::with('submitter')
            ->where('task_id', $taskId)
            ->where('contract_id', $contractId);

        $submissions = [];

        // === A) SUPERVISOR: see only their submitters
        if ($role === 'supervisor') {
            $supervisedIds = $contract->members()
                ->wherePivot('role', 'submitter')
                ->wherePivot('supervisor_id', $user->id)
                ->pluck('users.id');

            $subs = $baseQuery
                ->whereIn('user_id', $supervisedIds)
                ->latest('submitted_at')
                ->get();

            foreach ($subs as $s) {
                $submissions[] = [
                    'id'              => $s->id,
                    'submitter'       => [
                        'id'   => $s->submitter->id,
                        'name' => $s->submitter->name,
                    ],
                    'submittedAt'     => $s->submitted_at->toDateTimeString(),
                    'file_path'       => $s->file_path,
                    'fileUrl'         => Storage::url($s->file_path),
                    'status'          => $s->status,
                    'rejectionReason' => $s->rejection_reason,
                ];
            }
        }

        // === B) OWNER: see all submitters
        elseif ($role === 'owner') {
            $subs = $baseQuery
                ->latest('submitted_at')
                ->get();

            foreach ($subs as $s) {
                $submissions[] = [
                    'id'              => $s->id,
                    'submitter'       => [
                        'id'   => $s->submitter->id,
                        'name' => $s->submitter->name,
                    ],
                    'submittedAt'     => $s->submitted_at->toDateTimeString(),
                    'file_path'       => $s->file_path,
                    'fileUrl'         => Storage::url($s->file_path),
                    'status'          => $s->status,
                    'rejectionReason' => $s->rejection_reason,
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
                $submission = [
                    'id'         => $latest->id,
                    'file_path'  => $latest->file_path,
                    'file_name'  => $latest->file_name,
                    'fileUrl'    => Storage::url($latest->file_path),
                    'status'     => $latest->status,
                    'rejectionReason' => $latest->rejection_reason,
                ];
            }
        }

        return response()->json([
            'submission'  => $submission,
            'submissions' => $submissions,
        ]);
    }

    /**
     * DELETE /api/submissions/{id}
     */
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

    /**
     * PATCH /api/contracts/{contract}/timesheet-tasks/{task}/submissions/{submission}
     *
     * Body:
     * {
     *   status: 'pending' | 'approved' | 'rejected',
     *   rejection_reason?: string
     * }
     */
    public function updateStatus(
        Request $request,
        int $contract,
        int $task,
        int $submission
    ) {
        // 1) Validate input
        $data = $request->validate([
            'status'           => 'required|string|in:pending,approved,rejected,unsubmitted',
            'rejection_reason' => 'nullable|string|max:255',
        ]);

        // 2) Find submission
        $timesheet = SubmittedTimesheet::where('id', $submission)
            ->where('contract_id', $contract)
            ->where('task_id', $task)
            ->firstOrFail();

        // 3) Update status + reason
        $timesheet->status = $data['status'];

        if ($data['status'] === 'rejected') {
            $timesheet->rejection_reason = $data['rejection_reason'] ?? null;
        } else {
            $timesheet->rejection_reason = null;
        }

        // 4) Metadata
        $timesheet->supervisor_id = $request->user()->id;
        $timesheet->reviewed_at   = now();

        $timesheet->save();

        // 5) Return updated
        return response()->json([
            'submission' => $timesheet,
        ]);
    }
}
