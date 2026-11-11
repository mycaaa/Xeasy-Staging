<?php

namespace App\Models\focus;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class DepartmentCodeMapping extends Model
{
    use HasFactory;

    protected $table = 'fc_department_code_mapping';

    protected $fillable = [
        'original_department_name',
        'mapped_department_code',
        'mapped_department_name',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'created_at' => 'datetime',
    ];

    public $timestamps = false;

    public function scopeActive($query)
    {
        return $query->where('is_active', 1);
    }
}
