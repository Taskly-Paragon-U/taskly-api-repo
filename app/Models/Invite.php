<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Invite extends Model
{
    use HasFactory;

    protected $fillable = [
        'token',
        'contract_id',
        'email',
        'role',
        'invited_by',
        'consumed',
    ];

    /**
     * The contract this invite belongs to.
     */
    public function contract()
    {
        return $this->belongsTo(Contract::class);
    }

    /**
     * The user who sent the invite.
     */
    public function inviter()
    {
        return $this->belongsTo(User::class, 'invited_by');
    }
}
