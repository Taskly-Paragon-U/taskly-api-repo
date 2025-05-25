<?php

namespace App\Http\Controllers;

use App\Models\TimesheetTask;
use App\Models\SubmittedTimesheet;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class TimesheetTaskController extends Controller
{
    /**
     * GET  /timesheet-tasks
     * Optional query param: ?contract_id=123
     */
    public function index(Request $request)
    {
        $q = TimesheetTask::query();

        if ($request->filled('contract_id')) {
            $q->where('contract_id', $request->contract_id);
        }

        $tasks = $q->get()->map(fn($task) => $task->toArray() + [
            'template_file_url'  => $task->template_file
                                      ? Storage::url($task->template_file)
                                      : null,
            'template_file_name' => $task->template_file_name,
        ]);

        return response()->json($tasks);
    }

    /**
     * GET  /timesheet-tasks/{task}
     */
    public function show(TimesheetTask $task)
    {
        return response()->json(
            $task->toArray() + [
                'template_file_url'  => $task->template_file
                                          ? Storage::url($task->template_file)
                                          : null,
                'template_file_name' => $task->template_file_name,
            ]
        );
    }

    /**
     * POST /timesheet-tasks
     */
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
            $data['template_file']      = $file->store('timesheet_templates', 'public');
            $data['template_file_name'] = $file->getClientOriginalName();
        }

        $task = TimesheetTask::create($data);

        return response()->json([
            'message' => 'Timesheet Task Created Successfully!',
            'task'    => $task->toArray() + [
                'template_file_url'  => $task->template_file
                                          ? Storage::url($task->template_file)
                                          : null,
                'template_file_name' => $task->template_file_name,
            ],
        ], 201);
    }

    /**
     * PATCH /timesheet-tasks/{task}
     */
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
            $data['template_file']      = $file->store('timesheet_templates', 'public');
            $data['template_file_name'] = $file->getClientOriginalName();
        }

        $task->update($data);

        return response()->json([
            'message' => 'Timesheet Task Updated!',
            'task'    => $task->toArray() + [
                'template_file_url'  => $task->template_file
                                          ? Storage::url($task->template_file)
                                          : null,
                'template_file_name' => $task->template_file_name,
            ],
        ]);
    }

    /**
     * DELETE /timesheet-tasks/{task}
     */
    public function destroy(TimesheetTask $task)
    {
        $task->delete();
        return response()->json(['message' => 'Timesheet Task Deleted']);
    }
    
    /**
     * POST /submit-timesheet
     * form-data: task_id, timesheet (file)
     */
    public function submit(Request $request)
    {
        // 1) auth
        $user = $request->user();
        if (! $user) {
            return response()->json(['message' => 'Unauthenticated'], 401);
        }

        // 2) validate
        $data = $request->validate([
            'task_id'   => 'required|exists:timesheet_tasks,id',
            'timesheet' => 'required|file|max:10240',
        ]);

        // 3) load task + contract.members
        $task = TimesheetTask::with('contract.members')
                  ->findOrFail($data['task_id']);

        // 4) ensure this user is a member of that contract
        $allowed = $task->contract
                        ->members
                        ->pluck('id')
                        ->contains($user->id);

        if (! $allowed) {
            return response()->json(['message' => 'Forbidden'], 403);
        }

        // 5) store the uploaded file
        $path = $request->file('timesheet')
                        ->store('submitted_timesheets', 'public');

        // 6) record the submission
        $submission = SubmittedTimesheet::create([
            'task_id'     => $task->id,
            'contract_id' => $task->contract_id,                   // ← include contract
            'user_id'     => $user->id,
            'file_path'   => $path,
            'file_name'   => $request->file('timesheet')
                                ->getClientOriginalName(),     // ← store original name
        ]);

        // 7) public URL
        $url = Storage::url($path);

        return response()->json([
            'message'    => 'Submitted successfully',
            'submission' => [
                'id'           => $submission->id,
                'task_id'      => $submission->task_id,
                'user_id'      => $submission->user_id,
                'file_path'    => $submission->file_path,
                'file_url'     => $url,
                'file_name'    => $submission->file_name,           // ← added
                'submitted_at' => $submission->submitted_at->toDateTimeString(),
            ],
        ], 201);
    }
}
