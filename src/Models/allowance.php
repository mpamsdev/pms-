<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class allowance extends Model{
    protected $table = 'allowances';

    protected $fillable = [
        'employee_id',
        'type',
        'amount',
    ];

    public function employee()
    {
        return $this->belongsTo(employee::class);
    }
}
