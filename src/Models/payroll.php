<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class payroll extends Model{

    protected $table = 'payrolls';
    protected $primaryKey = 'id';
    public $timestamps = true;
    protected $fillable = [
        'employee_id','account_number', 'bank_name', 'branch_name','net_pay'
    ];

    // Relationship: one payroll belongs to one employee
    public function employee()
    {
        return $this->belongsTo(employee::class, 'employee_id');
    }
}
