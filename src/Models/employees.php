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


    public function loans()
    {
        return $this->hasMany(loans::class, 'userid', 'app_id');
    }

    public function occupation() {
        return $this->hasOne(occupation::class, 'userid'); // adjust FK if needed
    }

    public function nextOfKin() {
        return $this->hasOne(next_of_kin::class, 'userid'); // adjust FK if needed
    }


}
