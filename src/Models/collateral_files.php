<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class collateral_files extends Model{
    protected $table = 'collateral_files';
    protected $primaryKey = 'application_id';

    public $timestamps = false;

    protected $fillable = [
        'application_id', 'payslip', 'collateral1', 'collateral2', 'collateral3',
        'accountStament', 'nationalid_front', 'nationalid_back'
    ];
}
