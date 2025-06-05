<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SubmitterSupervisor extends Model
{
    use HasFactory;
    
    protected $table = 'submitter_supervisor';
    
    protected $fillable = [
        'contract_id',
        'submitter_id',
        'supervisor_id',
    ];
    
    public function contract()
    {
        return $this->belongsTo(Contract::class);
    }
    
    public function submitter()
    {
        return $this->belongsTo(User::class, 'submitter_id');
    }
    
    public function supervisor()
    {
        return $this->belongsTo(User::class, 'supervisor_id');
    }
}