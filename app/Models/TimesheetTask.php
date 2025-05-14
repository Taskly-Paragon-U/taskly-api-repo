<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class TimesheetTask extends Model
{
    use HasFactory;

    protected $fillable = [
        'title',
        'details',
        'start_date',
        'due_date',
        'role',
        'contract_id',
        'template_link',    // ← new
    ];
}
