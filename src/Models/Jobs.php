<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Jobs extends Model
{
    protected $table = 'jobs';

    protected $fillable = [
        'company_name',
        'company_logo',
        'email',
        'position',
        'salary_range',
        'description',
        'slug',
        'job_type',
        'location',
        'website',
        'deadline',
    ];

    protected $casts = [
        'deadline' => 'date',
    ];

    public $timestamps = true; // Uses created_at and updated_at
}
