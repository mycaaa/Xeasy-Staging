<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class BillToCompanyCategory extends Model
{
    protected $connection = 'mysql'; // always use the main app DB, never the expense connection
    protected $table = 'bill_to_company_categories';

    protected $fillable = [
        'name',
        'description',
        'is_active',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'is_active'  => 'boolean',
        'created_by' => 'integer',
        'updated_by' => 'integer',
    ];

    // ── Scopes ──────────────────────────────────────────────────────────────

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    // ── Relationships ────────────────────────────────────────────────────────

    /**
     * Bills that use this category (matched by name, since bills store the category name string).
     */
    public function bills()
    {
        return $this->hasMany(\App\Models\BillToCompany::class, 'bill_category', 'name');
    }
}
