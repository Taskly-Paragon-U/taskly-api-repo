<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SubmittedTimesheet extends Model
{
    protected $table = 'submitted_timesheets';

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
        'supervisor_id', // Keep for backward compatibility
        'rejection_reason',
        'reviewed_at',
    ];

    protected $casts = [
        'status' => 'string',
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

    public function supervisorApprovals(): HasMany
    {
        return $this->hasMany(SupervisorApproval::class);
    }

    public function getStatusLabelAttribute(): string
    {
        return match ($this->status) {
            'pending'  => 'Pending',
            'approved' => 'Approved',
            'rejected' => 'Rejected',
            default    => ucfirst($this->status),
        };
    }

    public function supervisorAssignments()
    {
        return $this->hasMany(SubmitterSupervisor::class, 'submitter_id', 'user_id')
                    ->where('contract_id', $this->contract_id);
    }
}