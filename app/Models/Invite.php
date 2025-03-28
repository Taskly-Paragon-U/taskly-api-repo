<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Invite extends Model
{
    use HasFactory;

    protected $fillable = ['contract_id', 'email', 'invited_by'];

    public function contract()
    {
        return $this->belongsTo(Contract::class);
    }

    public function inviter()
    {
        return $this->belongsTo(User::class, 'invited_by');
    }
}
