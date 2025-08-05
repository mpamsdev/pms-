<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Ramsey\Uuid\Uuid;

class  loans extends Model {

    use SoftDeletes; // 👈 Add this line

    protected $table = 'applications';

    protected $primaryKey = 'app_id';
    public $timestamps = true; // <-- Add this line
    protected $dates = ['deleted_at'];

    protected $fillable = [
        'uuid','userid', 'applicationNumber', 'amount', 'totalPayable', 'balance',
        'paid', 'paidInterest', 'period', 'interestRate', 'loanType', 'description',
        'tenure_status', 'status', 'comment'
    ];


    public function user()
    {
        return $this->belongsTo(employees::class, 'userid', 'userid');
    }

    public function repayments()
    {
        return $this->hasMany(repayment_history::class, 'app_id', 'id');
    }

    protected static function boot()
    {
        parent::boot();

        static::creating(function ($loan) {
            $loan->uuid = Uuid::uuid4()->toString();
        });
    }


}

