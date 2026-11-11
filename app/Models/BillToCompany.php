<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;

class BillToCompany extends Model
{
    protected $connection = 'mysql'; // always use the main app DB, never the expense connection
    protected $table = 'bill_to_company_bills';

    protected $fillable = [
        'company_id',
        'employee_id',
        'emp_code',
        'emp_name',
        'bill_title',
        'bill_category',
        'bill_amount',
        'bill_date',
        'file_path',
        'file_name',
        'file_type',
        'remarks',
        'status',
        'uploaded_by',
    ];

    protected $casts = [
        'bill_amount'  => 'decimal:2',
        'bill_date'    => 'date',
        'company_id'   => 'integer',
        'employee_id'  => 'integer',
        'uploaded_by'  => 'integer',
        'status'       => 'integer',
    ];

    // ── Scopes ──────────────────────────────────────────────────────────────

    public function scopeActive($query)
    {
        return $query->where('status', 1);
    }

    public function scopeForCompany($query, int $companyId)
    {
        return $query->where('company_id', $companyId);
    }

    public function scopeForEmployee($query, int $employeeId)
    {
        return $query->where('employee_id', $employeeId);
    }

    // ── Helpers ──────────────────────────────────────────────────────────────

    /**
     * Return a public URL for the uploaded file.
     */
    public function getFileUrlAttribute(): string
    {
        return Storage::disk('public')->url($this->file_path);
    }

    /**
     * Check whether the file is an image (for inline preview).
     */
    public function getIsImageAttribute(): bool
    {
        return in_array(strtolower($this->file_type), ['jpg', 'jpeg', 'png', 'gif', 'webp']);
    }

    /**
     * Check whether the file is a PDF.
     */
    public function getIsPdfAttribute(): bool
    {
        return strtolower($this->file_type) === 'pdf';
    }
}
