<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class leaveType extends Model
{
    use HasFactory;

    protected $table = 'leave_types';

    protected $fillable = [
        'name',
        'description',
        'max_days',
        'is_paid',
        'requires_document',
    ];

    public function leaveRequests()
    {
        return $this->hasMany(leaveRequest::class, 'leave_type_id');
    }
}


