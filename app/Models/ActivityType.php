<?php

namespace App\Models;

use App\Traits\CompanyDatabaseConnection;
use Illuminate\Database\Eloquent\Model;

class ActivityType extends Model
{
    use CompanyDatabaseConnection;

    protected $table = 'adv_activity_types';
    protected $primaryKey = 'id';
    protected $connection = null;
    protected $connectionType = 'expense';
    public $timestamps = true;
    protected $fillable = [
        'category_id',
        'department_id',
        'type_name',
        'focus_code',
        'description',
        'status',
    ];


    protected $casts = [
        'status' => 'boolean',
        'department_id' => 'string',
    ];


    public function category()
    {
        return $this->belongsTo(ActivityCategory::class, 'category_id', 'id');
    }


    public function getDepartmentIdsAttribute()
    {
        return $this->department_id ? explode(',', $this->department_id) : [];
    }


    public function setDepartmentIdsAttribute($value)
    {
        $this->attributes['department_id'] = is_array($value) ? implode(',', $value) : $value;
    }

    public static function getByDepartment($departmentId)
    {
        if (empty($departmentId)) {
            return collect();
        }
        return self::whereRaw("FIND_IN_SET(?, department_id)", [$departmentId])->where('status', 1)->orderBy('type_name')->get(['id', 'type_name', 'description']);
    }
}
