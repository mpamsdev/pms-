<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class deductions extends Model{

    protected $table = 'deductions';
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
