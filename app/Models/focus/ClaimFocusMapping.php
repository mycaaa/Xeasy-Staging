<?php

namespace App\Models\focus;

use App\Models\ClaimType;
use App\Models\ExpenseHeadCategory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ClaimFocusMapping extends Model
{
    use HasFactory;

    protected $table = 'fc_claim_focus_mapping';

    /**
     * Dynamically set DB connection from session
     */
    public function getConnectionName()
    {
        return session('expense_connection') ?? $this->connection;
    }

    protected $fillable = [
        'claim_id',
        'expense_head_category_id',
        'wtype',
        'focus_name_subl',
        'focus_name',
        'focus_ledger_code',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'created_at' => 'datetime',
    ];

    public $timestamps = false;

    /**
     * Scope for active records
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', 1);
    }

    /**
     * Accessor for wtype display
     */
    public function getWtypeDisplayAttribute()
    {
        if ($this->wtype === '2') return 'Two Wheeler';
        if ($this->wtype === '4') return 'Four Wheeler';
        return 'N/A';
    }

    /**
     * Relationships
     */
    public function claimType()
    {
        return $this->belongsTo(ClaimType::class, 'claim_id', 'ClaimId');
    }

    public function expenseHeadCategory()
    {
        return $this->belongsTo(ExpenseHeadCategory::class, 'expense_head_category_id', 'id');
    }

    /**
     * Accessors for backward compatibility
     */
    public function getClaimNameAttribute()
    {
        return $this->claimType?->ClaimName ?? 'N/A';
    }

    public function getExpenseHeadNameAttribute()
    {
        return $this->expenseHeadCategory?->expense_head_name ?? 'All';
    }
}
