<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class changed_tenure extends Model{

    protected $table = 'changed_tenure';

    protected $primaryKey = 'id';

    protected $fillable = [
        'app_id', 'applicationNumber', 'amount', 'oldTotalPayable',
        'newTotalPayable', 'oldPeriod','newPeriod', 'changeReason'
    ];
}
