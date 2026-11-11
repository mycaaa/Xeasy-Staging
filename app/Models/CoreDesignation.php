<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CoreDesignation extends Model
{
    protected $connection = 'hrims';
    protected $table = 'core_designation';
    protected $primaryKey = 'id';
    public $timestamps = false;
    protected $fillable = [
        'id', 
        'designation_name', 
        'designation_code', 
        'effective_date', 
        'is_active', 
        'prefix', 
        'suffix'
    ];

    public function departments()
    {
        return $this->belongsToMany(
            CoreDepartments::class, 
            'core_designation_department_mapping', 
            'designation_id', 
            'department_id'
        );
    }

    public static function active()
    {
        return self::where('is_active', 1)->orderBy('designation_name')->get(['id', 'designation_name as name']);
    }
}