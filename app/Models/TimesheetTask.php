<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class TimesheetTask extends Model
{
    use HasFactory;

    protected $fillable = [
        'title',
        'details',
        'start_date',
        'due_date',
        'role',
        'contract_id',
        'template_link',
        'template_file',
        'template_file_name',
    ];

    /**
     * The owning contract.
     */
    public function contract()
    {
        return $this->belongsTo(Contract::class, 'contract_id');
    }

    /**
     * All submitted timesheets for this task.
     */
    public function submissions()
    {
        return $this->hasMany(SubmittedTimesheet::class, 'task_id');
    }

    // app/Http/Controllers/TimesheetTaskController.php

    public function submit(Request $request)
    {
        // … auth & validate …

        // 5) store the uploaded file
        $file = $request->file('timesheet');
        $path = $file->store('submitted_timesheets', 'public');

        // 6) record the submission
        $submission = SubmittedTimesheet::create([
            'task_id'     => $task->id,
            'contract_id' => $task->contract_id,                 // ← now included
            'user_id'     => $user->id,
            'file_path'   => $path,
            'file_name'   => $file->getClientOriginalName(),     // ← store original name
        ]);
        // … return JSON …
    }

}
