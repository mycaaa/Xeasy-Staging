<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CoreGrade extends Model
{
    protected $connection = 'hrims';
    protected $table = 'core_grades';
    public $timestamps = false;

    protected $fillable = [
        'grade_name',
        'level_id',
        'effective_date',
        'is_active',
        'company_id'
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'effective_date' => 'date'
    ];
}
