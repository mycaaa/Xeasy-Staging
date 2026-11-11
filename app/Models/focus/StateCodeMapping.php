<?php

namespace App\Models\focus;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class StateCodeMapping extends Model
{
    use HasFactory;

    protected $table = 'fc_state_code_mapping';

    protected $fillable = [
        'state_name',
        'state_code',
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
