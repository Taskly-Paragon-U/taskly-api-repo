<?php

namespace App\Models;

use Carbon\Traits\Timestamp;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use App\Models\Contract;
use App\Models\Invite;

class User extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable;

    protected $fillable = [
        'name',
        'email',
        'password',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected $casts = [
        'email_verified_at' => 'datetime',
        'password' => 'hashed',
    ];

    // Contracts created by this user
    public function contracts()
    {
        return $this->belongsToMany(Contract::class)
                    ->withPivot(['role', 'start_date', 'due_date', 'supervisor_id', 'label'])
                    ->withTimestamps();
    }

    // Contracts this user was invited to (via pivot)
    public function sharedContracts()
    {
        return $this->belongsToMany(Contract::class, 'contract_user')
            ->withPivot(['role', 'start_date', 'due_date', 'supervisor_id', 'label'])
            ->withTimestamps();
    }

    // Invites sent by this user
    public function sentInvites()
    {
        return $this->hasMany(Invite::class, 'invited_by');
    }
    
    /**
     * Get all submitters supervised by this user
     */
    public function supervisedSubmitters()
    {
        return $this->belongsToMany(User::class, 'submitter_supervisor', 'supervisor_id', 'submitter_id')
                    ->withPivot('contract_id')
                    ->withTimestamps();
    }

    /**
     * Get all supervisors for this submitter
     */
    public function supervisors()
    {
        return $this->belongsToMany(User::class, 'submitter_supervisor', 'submitter_id', 'supervisor_id')
                    ->withPivot('contract_id')
                    ->withTimestamps();
    }
}
