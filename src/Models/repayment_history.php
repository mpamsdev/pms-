<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class repayment_history extends Model{

    protected $table = 'repayment_history';
    public $timestamps = false;
    protected $fillable = ['app_id', 'schedule_id', 'amount_paid', 'date_received', 'comment'];
}