<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class leaveRequest extends Model
{
    use HasFactory;

    protected $table = 'leave_requests';

    protected $fillable = [
        'employee_id',
        'leave_type_id',
        'start_date',
        'end_date',
        'reason',
        'status',
        'approver_id',
    ];

    public function leaveType()
    {
        return $this->belongsTo(leaveType::class, 'leave_type_id');
    }

    public function employee()
    {
        return $this->belongsTo(employee::class, 'employee_id');
    }

    public function approver()
    {
        return $this->belongsTo(admins::class, 'approver_id');
    }
}
