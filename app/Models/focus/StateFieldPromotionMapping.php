<?php

namespace App\Models\focus;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class StateFieldPromotionMapping extends Model
{
    use HasFactory;

    protected $table = 'fc_state_field_promotion_mapping';

    protected $fillable = [
        'state_name',
        'focus_name',
        'focus_ledger_code',
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

    public function getStateNameDisplayAttribute()
    {
        return ucwords($this->state_name);
    }
}
