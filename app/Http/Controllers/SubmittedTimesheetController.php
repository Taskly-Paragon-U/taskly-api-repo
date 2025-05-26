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
     * Always returns:
     * {
     *   submission: { … } | null,
     *   submissions: [ … ]  // empty if not supervisor
     * }
     */
    public function index(Request $request)
    {
        $user       = $request->user();
        $taskId     = $request->query('task_id');
        $contractId = $request->query('contract_id');

        // 1) Fetch contract so we can see if current user is a supervisor
        $contract = Contract::findOrFail($contractId);
        $isSupervisor = $contract->members()
                                 ->wherePivot('role', 'supervisor')
                                 ->where('users.id', $user->id)
                                 ->exists();

        // 2) Build base query
        $baseQuery = SubmittedTimesheet::with('submitter')
            ->where('task_id',     $taskId)
            ->where('contract_id', $contractId);

        // 3a) If supervisor, grab _all_ their assigned submitters’ submissions
        $submissions = [];
        if ($isSupervisor) {
            // find the submitter IDs this supervisor is responsible for
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
                    'id'          => $s->id,
                    'submitter'   => [
                        'id'   => $s->submitter->id,
                        'name' => $s->submitter->name,
                    ],
                    'submittedAt' => $s->submitted_at->toDateTimeString(),
                    'fileUrl'     => Storage::url($s->file_path),
                    'status'      => $s->status,
                ];
            }
        }

        // 3b) If _not_ a supervisor (i.e. a submitter), grab _their_ latest submission
        $latest = $baseQuery
            ->where('user_id', $user->id)
            ->latest('submitted_at')
            ->first();

        $submission = $latest
            ? [
                'id'        => $latest->id,
                'file_path' => $latest->file_path,
                'file_name' => $latest->file_name,
              ]
            : null;

        // 4) Return both keys
        return response()->json([
            'submission'  => $submission,
            'submissions' => $submissions,
        ], 200);
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
}
