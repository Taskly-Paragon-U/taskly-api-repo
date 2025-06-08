<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SubmitterLabel extends Model
{
    use HasFactory;

    protected $fillable = [
        'contract_id',
        'submitter_id',
        'label',
    ];

    /**
     * The contract this label belongs to
     */
    public function contract(): BelongsTo
    {
        return $this->belongsTo(Contract::class);
    }

    /**
     * The submitter (user) this label is assigned to
     */
    public function submitter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'submitter_id');
    }
}