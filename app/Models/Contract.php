<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Models\User;
use App\Models\Invite;

class Contract extends Model
{
    use HasFactory;

    protected $fillable = ['user_id', 'name', 'details'];

    // Users invited to this contract (registered users)
    public function members()
    {
        return $this->belongsToMany(User::class, 'contract_user');
    }

    // Creator of the contract
    public function creator()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    // Email invites (users may or may not exist yet)
    public function invites()
    {
        return $this->hasMany(Invite::class);
    }
}
