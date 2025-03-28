<?php

namespace App\Models;

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
        return $this->hasMany(Contract::class);
    }

    // Contracts this user was invited to (via pivot)
    public function sharedContracts()
    {
        return $this->belongsToMany(Contract::class, 'contract_user');
    }

    // Invites sent by this user (optional)
    public function sentInvites()
    {
        return $this->hasMany(Invite::class, 'invited_by');
    }
}
