<?php

namespace App\Models\focus;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SystemConfiguration extends Model
{
    use HasFactory;

    protected $table = 'system_configuration';

    protected $fillable = [
        'config_key',
        'config_value',
        'config_type',
        'description',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    public function scopeActive($query)
    {
        return $query->where('is_active', 1);
    }

    public function getTypedValueAttribute()
    {
        switch ($this->config_type) {
            case 'integer':
                return (int) $this->config_value;
            case 'boolean':
                return filter_var($this->config_value, FILTER_VALIDATE_BOOLEAN);
            default:
                return $this->config_value;
        }
    }
}
