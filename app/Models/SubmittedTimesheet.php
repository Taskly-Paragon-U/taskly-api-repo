<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SubmittedTimesheet extends Model
{
    protected $table = 'submitted_timesheets';

    // Use submitted_at as the created timestamp, no updated timestamp
    public $timestamps = true;
    const CREATED_AT = 'submitted_at';
    const UPDATED_AT = null;

    protected $fillable = [
        'task_id',
        'contract_id',
        'user_id',
        'file_path',
        'file_name',
        'status',
        'supervisor_id',
        'reviewed_at',
    ];

    protected $casts = [
        'submitted_at' => 'datetime',
        'reviewed_at'  => 'datetime',
    ];

    public function task(): BelongsTo
    {
        return $this->belongsTo(TimesheetTask::class, 'task_id');
    }

    public function submitter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function supervisor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'supervisor_id');
    }
}
