<?php

namespace  App\Models;

use Illuminate\Database\Eloquent\Model;

class admins extends Model{
    protected $table = 'admins';

    protected $primaryKey = 'id';

    protected $fillable = [
        'employee_number','username','department','password','name','phone', 'role', 'status'
    ];
}