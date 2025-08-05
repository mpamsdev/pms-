<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class next_of_kin extends Model{
    protected $table = 'next_of_kin';
    public $timestamps = false;

    protected $fillable = [
        'userid', 'firstname', 'lastname', 'next_phone', 'next_national_id',
        'next_relationship', 'next_address', 'next_work_status', 'next_company',
        'next_company_address'
    ];
}
