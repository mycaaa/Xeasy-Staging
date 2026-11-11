<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CoreDesignationDepartmentMapping extends Model
{
    protected $connection = 'hrims';
    protected $table = 'core_designation_department_mapping';
    protected $primaryKey = 'id';
    public $timestamps = false;
    protected $fillable = ['id', 'department_id', 'designation_id'];

    public function department()
    {
        return $this->belongsTo(CoreDepartments::class, 'department_id', 'id');
    }

    public function designation()
    {
        return $this->belongsTo(CoreDesignation::class, 'designation_id', 'id');
    }
}