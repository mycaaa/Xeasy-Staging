<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ClaimRatingResult extends Model
{
    protected $table = 'claim_rating_results';

    protected $fillable = [
        'company_id', 'year_id', 'month', 'employee_id', 'emp_code',
        'employee_name', 'department_name', 'stage_key', 'stage_label',
        'rated_role', 'rated_person_id', 'rated_person_name',
        'days_taken', 'stars', 'from_date', 'to_date',
        'rule_id', 'calculated_date', 'calculated_by',
    ];

    protected $casts = [
        'stars' => 'integer',
        'days_taken' => 'integer',
        'from_date' => 'date',
        'to_date' => 'date',
        'calculated_date' => 'date',
    ];
}
