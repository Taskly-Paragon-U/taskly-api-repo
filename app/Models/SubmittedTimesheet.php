<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SubmittedTimesheet extends Model
{
    protected $table = 'submitted_timesheets';
    public    $timestamps = false; // we use our own submitted_at

    protected $fillable = [
        'task_id',
        'user_id',
        'file_path',
        'submitted_at',
    ];

    public function task()
    {
        return $this->belongsTo(TimesheetTask::class, 'task_id');
    }

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
