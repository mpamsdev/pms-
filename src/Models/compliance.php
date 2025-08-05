<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class compliance extends Model{
    protected $table = 'compliance';
    public $timestamps = false;
    protected $primaryKey = 'compliance_id';
    protected $fillable = [
        'certificate_name', 'renewal_date'
    ];
}
