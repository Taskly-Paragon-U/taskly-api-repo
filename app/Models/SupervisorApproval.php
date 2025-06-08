<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SupervisorApproval extends Model
{
    protected $fillable = [
        'submitted_timesheet_id',
        'supervisor_id',
        'status',
        'rejection_reason',
        'reviewed_at',
    ];

    protected $casts = [
        'reviewed_at' => 'datetime',
    ];

    public function submittedTimesheet(): BelongsTo
    {
        return $this->belongsTo(SubmittedTimesheet::class);
    }

    public function supervisor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'supervisor_id');
    }
}