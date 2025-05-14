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
        // 1) validate incoming fields
        $data = $request->validate([
            'title'          => 'required|string',
            'details'        => 'nullable|string',
            'start_date'     => 'required|date',
            'due_date'       => 'nullable|date',
            'role'           => 'required|in:submitter,supervisor',
            'contract_id'    => 'required|exists:contracts,id',
            'template_link'  => 'nullable|url',
            'template_file'  => 'nullable|file|mimes:pdf,xlsx,xls|max:10240', // 10MB max
        ]);

        // 2) if they uploaded a file, store it and override just that field
        if ($request->hasFile('template_file')) {
            $data['template_file'] = $request
                ->file('template_file')
                ->store('timesheet_templates', 'public');
        }

        // 3) create the record
        $task = TimesheetTask::create($data);

        return response()->json([
            'message' => 'Timesheet Task Created Successfully!',
            'task'    => $task,
        ], 201);
    }

    public function update(Request $request, TimesheetTask $task)
    {
        // You can allow changing link or file on edit too
        $data = $request->validate([
            'title'          => 'required|string',
            'details'        => 'nullable|string',
            'start_date'     => 'required|date',
            'due_date'       => 'nullable|date',
            'template_link'  => 'nullable|url',
            'template_file'  => 'nullable|file|mimes:pdf,xlsx,xls|max:10240',
        ]);

        if ($request->hasFile('template_file')) {
            $data['template_file'] = $request
                ->file('template_file')
                ->store('timesheet_templates', 'public');
        }

        $task->update($data);

        return response()->json([
            'message' => 'Timesheet Task Updated!',
            'task'    => $task,
        ]);
    }

    public function destroy(TimesheetTask $task)
    {
        $task->delete();
        return response()->json(['message' => 'Timesheet Task Deleted']);
    }
}
