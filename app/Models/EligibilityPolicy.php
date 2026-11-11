<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class EligibilityPolicy extends Model
{
    protected $connection = 'hrims';
    protected $table = 'hrm_master_eligibility_policy';
    protected $primaryKey = 'PolicyId';
    public $timestamps = false;
    protected $fillable = ['PolicyId', 'PolicyName', 'CompanyId'];
}
