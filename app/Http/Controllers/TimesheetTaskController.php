<?php
namespace App\Http\Controllers;

use App\Models\TimesheetTask;
use Illuminate\Http\Request;

class TimesheetTaskController extends Controller
{
    public function index(Request $request)
    {
        $q = TimesheetTask::query();
        if ($request->filled('contract_id')) {
            $q->where('contract_id', $request->contract_id);
        }
        return response()->json($q->get());
    }

    public function create(Request $request)
    {
        $data = $request->validate([
            'title'       => 'required|string',
            'details'     => 'nullable|string',
            'start_date'  => 'required|date',
            'due_date'    => 'required|date',
            'role'        => 'required|in:submitter,supervisor',
            'contract_id' => 'required|exists:contracts,id',
        ]);

        $task = TimesheetTask::create($data);

        return response()->json([
            'message' => 'Timesheet Task Created Successfully!',
            'task'    => $task,
        ], 201);
    }

    // ✏️ Update an existing task
    public function update(Request $request, TimesheetTask $task)
    {
        $data = $request->validate([
            'title'      => 'required|string',
            'details'    => 'nullable|string',
            'start_date' => 'required|date',
            'due_date'   => 'required|date',
            // you can validate role/contract_id here if you want
        ]);

        $task->update($data);

        return response()->json([
            'message' => 'Timesheet Task Updated!',
            'task'    => $task,
        ]);
    }

    // 🗑️ Delete a task
    public function destroy(TimesheetTask $task)
    {
        $task->delete();
        return response()->json(['message' => 'Timesheet Task Deleted']);
    }
}
