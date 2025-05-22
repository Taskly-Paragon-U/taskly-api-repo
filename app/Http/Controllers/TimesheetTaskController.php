<?php

namespace App\Http\Controllers;

use App\Models\TimesheetTask;
use App\Models\SubmittedTimesheet;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class TimesheetTaskController extends Controller
{
    public function index(Request $request)
    {
        $q = TimesheetTask::query();

        if ($request->filled('contract_id')) {
            $q->where('contract_id', $request->contract_id);
        }

        $tasks = $q->get()->map(function ($task) {
            return $task->toArray() + [
                'template_file_url'  => $task->template_file ? Storage::url($task->template_file) : null,
                'template_file_name' => $task->template_file_name,
            ];
        });

        return response()->json($tasks);
    }

    public function show(TimesheetTask $task)
    {
        return response()->json(
            $task->toArray() + [
                'template_file_url'  => $task->template_file ? Storage::url($task->template_file) : null,
                'template_file_name' => $task->template_file_name,
            ]
        );
    }

    public function create(Request $request)
    {
        $data = $request->validate([
            'title'         => 'required|string',
            'details'       => 'nullable|string',
            'start_date'    => 'required|date',
            'due_date'      => 'nullable|date',
            'role'          => 'required|in:submitter,supervisor',
            'contract_id'   => 'required|exists:contracts,id',
            'template_link' => 'nullable|url',
            'template_file' => 'nullable|file|mimes:pdf,xlsx,xls|max:10240',
        ]);

        if ($request->hasFile('template_file')) {
            $file = $request->file('template_file');
            $data['template_file'] = $file->store('timesheet_templates', 'public');
            $data['template_file_name'] = $file->getClientOriginalName();
        }

        $task = TimesheetTask::create($data);

        return response()->json([
            'message' => 'Timesheet Task Created Successfully!',
            'task'    => $task->toArray() + [
                'template_file_url'  => $task->template_file ? Storage::url($task->template_file) : null,
                'template_file_name' => $task->template_file_name,
            ],
        ], 201);
    }

    public function update(Request $request, TimesheetTask $task)
    {
        $data = $request->validate([
            'title'         => 'required|string',
            'details'       => 'nullable|string',
            'start_date'    => 'required|date',
            'due_date'      => 'nullable|date',
            'template_link' => 'nullable|url',
            'template_file' => 'nullable|file|mimes:pdf,xlsx,xls|max:10240',
        ]);

        if ($request->hasFile('template_file')) {
            $file = $request->file('template_file');
            $data['template_file'] = $file->store('timesheet_templates', 'public');
            $data['template_file_name'] = $file->getClientOriginalName();
        }

        $task->update($data);

        return response()->json([
            'message' => 'Timesheet Task Updated!',
            'task'    => $task->toArray() + [
                'template_file_url'  => $task->template_file ? Storage::url($task->template_file) : null,
                'template_file_name' => $task->template_file_name,
            ],
        ]);
    }

    public function destroy(TimesheetTask $task)
    {
        $task->delete();
        return response()->json(['message' => 'Timesheet Task Deleted']);
    }

    public function submit(Request $request)
    {
        $user = $request->user();

        $data = $request->validate([
            'task_id'     => 'required|exists:timesheet_tasks,id',
            'contract_id' => 'required|exists:contracts,id',
            'timesheet'   => 'required|file|max:10240',
        ]);

        $path = $request->file('timesheet')->store('submitted_timesheets', 'public');

        $submission = SubmittedTimesheet::create([
            'task_id'      => $data['task_id'],
            'user_id'      => $user->id,
            'file_path'    => $path,
            'submitted_at' => now(),
        ]);

        return response()->json([
            'message'    => 'Submitted successfully',
            'submission'=> $submission,
        ]);
    }
}
