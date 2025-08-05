<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class occupation extends Model{
    protected $table = 'occupation';
    public $timestamps = false;

    protected $fillable = [
        'userid', 'workStatus', 'position', 'department', 'company','address'
    ];
}