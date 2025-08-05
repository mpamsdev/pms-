<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class companies extends Model{
    protected $table = 'companies';

    protected $primaryKey = 'id';
    public $timestamps = false;
    
    protected $fillable = [
        'company_number', 'username', 'phone', 'company_name',
        'operation_area', 'years_of_operation', 'password', 'country','city','address',
        'tax_clearance_cert', 'company_reg_cert', 'form_three_cert'
    ];
}
