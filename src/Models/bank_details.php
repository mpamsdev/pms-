<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class bank_details extends Model{
    protected $table = 'bank_details';

    protected $primaryKey = 'id';

    public $timestamps = false;

    protected $fillable = [
        'userid', 'account_number', 'bank_name','branch_name', 'mobile_account', 'provider'
    ];
}