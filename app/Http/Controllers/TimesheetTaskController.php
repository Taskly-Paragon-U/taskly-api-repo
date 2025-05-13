<?php

namespace App\Http\Controllers;

use App\Models\TimesheetTask;
use Illuminate\Http\Request;

class TimesheetTaskController extends Controller
{
    public function create(Request $request)
    {
        // validate only the fields the client should supply
        $request->validate([
            'title'       => 'required|string',
            'details'     => 'nullable|string',
            'start_date'  => 'required|date',
            'due_date'    => 'required|date',
            'contract_id' => 'required|exists:contracts,id',
        ]);

        $task = TimesheetTask::create([
            'title'       => $request->title,
            'details'     => $request->details,
            'start_date'  => $request->start_date,
            'due_date'    => $request->due_date,
            // hard-code the role to submitter
            'role'        => 'submitter',
            'contract_id' => $request->contract_id,
        ]);

        return response()->json([
            'message' => 'Timesheet Task Created Successfully!',
            'task'    => $task,
        ], 201);
    }

    public function index()
    {
        // If you want participants only to see submitter-tasks:
        // $tasks = TimesheetTask::where('role','submitter')->get();

        // Or if everyone sees all tasks:
        $tasks = TimesheetTask::all();

        return response()->json($tasks);
    }
}
