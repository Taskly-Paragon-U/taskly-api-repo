<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Contract extends Model
{
    use HasFactory;

    protected $fillable = ['user_id', 'name', 'details'];

    /**
     * All members of this contract with pivot metadata.
     */
    public function members(): BelongsToMany
    {
        return $this
            ->belongsToMany(User::class, 'contract_user')
            ->withPivot([
                'role',
                'start_date',
                'due_date',
                'supervisor_id',
                'label',     
            ])
            ->withTimestamps();
    }



    /**
     * Only owners of the contract.
     */
    public function owners(): BelongsToMany
    {
        return $this->members()->wherePivot('role', 'owner');
    }

    /**
     * Only submitters of the contract.
     */
    public function submitters(): BelongsToMany
    {
        return $this->members()->wherePivot('role', 'submitter');
    }

    /**
     * Only supervisors of the contract.
     */
    public function supervisors(): BelongsToMany
    {
        return $this->members()->wherePivot('role', 'supervisor');
    }

    /**
     * Creator (owner) of the contract record.
     */
    public function creator()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /**
     * Pending invites for this contract.
     */
    public function invites()
    {
        return $this->hasMany(Invite::class);
    }
    
}
