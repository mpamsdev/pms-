<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class employee extends Model{
    protected $table = 'employee';
    protected $primaryKey = 'id';
    public $timestamps = false;

    protected $fillable = [
        'employee_number','email','password','firstname',
        'lastname','phone', 'national_id','department','dob','gender','address','country','city','country','is_verified',
        'role','status', 'profile_picture'
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
