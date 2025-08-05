<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class interest extends Model{

    protected $table = 'interests';

    protected $primaryKey = 'id';
    public $timestamps = false;
    protected $fillable = ['app_id','applicationNumber','pop','amount', 'comment'];
}
