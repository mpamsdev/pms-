<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class products extends Model{
    protected $table = 'products';
    protected $primaryKey = 'id';

    protected $fillable = ['product_name', 'rate','processing_fee', 'min_amount', 'max_amount','min_period', 'max_period'];
}