<?php

// app/Models/LoanProduct.php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class loan_products extends Model
{
    protected $table = 'loan_products';

    protected $primaryKey = 'id';

    protected $fillable = [
        'product_name',
        'rate',
        'processing_fee',
        'min_amount',
        'max_amount',
        'min_period',
        'max_period',
        // Add other fields as needed
    ];

    public $timestamps = true; // Or false, depending on your table
}
