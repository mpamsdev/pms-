<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class system_logs extends Model{

    protected $table = 'system_logs';

    protected $primaryKey = 'id';
    public $timestamps = false;

    protected $fillable = ['userid', 'username', 'action'];
}
