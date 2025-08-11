<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class employees extends Model{
    protected $table = 'employees';
    protected $primaryKey = 'id';
    public $timestamps = false;

    protected $fillable = [
        'name','username','password','phone','department','job_title', 'contract_type',
        'national_id','tax_id', 'ssn','nhima_number','contract_start_date','contract_end_date','gender',
        'status'
    ];


    public function leaveRequests()
    {
        return $this->hasMany(leaveRequest::class, 'employee_id');
    }

    public function salaries()
    {
        return $this->hasMany(salary::class);
    }

    public  function allowances()
    {
        return $this->hasMany(allowance::class);
    }

}
