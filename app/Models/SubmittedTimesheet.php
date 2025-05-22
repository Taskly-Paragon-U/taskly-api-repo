<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SubmittedTimesheet extends Model
{
    protected $table = 'submitted_timesheets';

    // Let Eloquent know we only have submitted_at
    public $timestamps = true;
    const CREATED_AT = 'submitted_at';
    const UPDATED_AT = null;

    protected $fillable = [
        'task_id',
        'user_id',
        'file_path',
        // submitted_at is auto‐set by the DB
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
