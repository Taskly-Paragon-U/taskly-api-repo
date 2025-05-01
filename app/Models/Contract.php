<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use App\Models\User;
use App\Models\Invite;

class Contract extends Model
{
    use HasFactory;

    protected $fillable = ['user_id', 'name', 'details'];

    /**
     * All users on this contract, with their pivot role.
     */
    public function members(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'contract_user')
                    ->withPivot('role')
                    ->withTimestamps();
    }

    /**
     * Shortcut: just the owner(s).
     */
    public function owners(): BelongsToMany
    {
        return $this->members()->wherePivot('role', 'owner');
    }

    /**
     * Shortcut: only submitter submittors.
     */
    public function submitters(): BelongsToMany
    {
        return $this->members()->wherePivot('role', 'submitter');
    }

    /**
     * Shortcut: only supervisors.
     */
    public function supervisors(): BelongsToMany
    {
        return $this->members()->wherePivot('role', 'supervisor');
    }

    /**
     * Creator of the contract.
     */
    public function creator()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /**
     * Email invites
     */
    public function invites()
{
    return $this->hasMany(Invite::class);
}

    public function users()
    {
        return $this->belongsToMany(User::class)
                    ->withPivot('role')
                    ->withTimestamps();
    }

}
