<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CoreSubDepartment extends Model
{
    protected $connection = 'hrims';
    protected $table = 'core_sub_department_master';
    protected $primaryKey = 'id';
    public $timestamps = false;
    protected $fillable = ['id', 'sub_department_name', 'sub_department_code', 'numeric_code', 'effective_date', 'is_active'];
}
