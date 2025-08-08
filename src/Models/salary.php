<?php


namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class salary extends Model{
    protected $table = 'salaries';

    protected $fillable = [
        'employee_id',
        'basic_salary',
        'pay_frequency',
    ];


    public function employee()
    {
        return $this->belongsTo(Employee::class);
    }
}
