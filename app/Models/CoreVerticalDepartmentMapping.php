<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CoreVerticalDepartmentMapping extends Model
{
    protected $connection = 'hrims';
    protected $table = 'core_vertical_department_mapping';
    protected $primaryKey = 'id';
    public $incrementing = false;
    protected $keyType = 'string';
    protected $fillable = ['id', 'function_vertical_id', 'department_id', 'name', 'effective_from', 'effective_to'];
}
