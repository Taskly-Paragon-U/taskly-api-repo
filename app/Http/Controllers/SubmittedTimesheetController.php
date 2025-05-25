<?php

namespace App\Http\Controllers;

use App\Models\SubmittedTimesheet;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class SubmittedTimesheetController extends Controller
{
    /**
     * GET /api/submissions?task_id=&contract_id=
     */
    public function index(Request $request)
    {
        $user = $request->user();
        $s = SubmittedTimesheet::where('task_id', $request->task_id)
            ->where('contract_id', $request->contract_id)
            ->where('user_id', $user->id)
            ->latest('submitted_at')
            ->first();

        return response()->json([
            'submission' => $s
                ? [
                    'id'           => $s->id,
                    'file_path'    => $s->file_path,
                    'file_name'    => $s->file_name,         // ← include
                    'file_url'     => Storage::url($s->file_path),
                    'submitted_at' => $s->submitted_at->toDateTimeString(),
                  ]
                : null,
        ]);
    }

    /**
     * DELETE /api/submissions/{id}
     */
    public function destroy(Request $request, $id)
    {
        $user = $request->user();
        $s = SubmittedTimesheet::findOrFail($id);

        if ($s->user_id !== $user->id) {
            return response()->json(['message' => 'Forbidden'], 403);
        }

        Storage::disk('public')->delete($s->file_path);
        $s->delete();

        return response()->json(null, 204);
    }
}
