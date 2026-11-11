<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class EmployeeLedgerBalance extends Model
{
    use SoftDeletes;

    protected $table = 'employee_ledger_balances';

    protected $fillable = [
        'employee_id',
        'year_id',
        'company_id',
        'fixed_imprest_amt_1',
        'fixed_imprest_date_1',
        'fixed_imprest_amt_2',
        'fixed_imprest_date_2',
        'advance_amt_1',
        'advance_date_1',
        'advance_month_1',
        'advance_purpose_1',
        'advance_amt_2',
        'advance_date_2',
        'advance_month_2',
        'advance_purpose_2',
        'advance_amt_3',
        'advance_date_3',
        'advance_month_3',
        'advance_purpose_3',
        'advance_amt_4',
        'advance_date_4',
        'advance_month_4',
        'advance_purpose_4',
        'dr',
        'cr',
        'balance',
        'remark'
    ];

    protected $casts = [
        'dr' => 'decimal:2',
        'cr' => 'decimal:2',
        'balance' => 'decimal:2',
        'fixed_imprest_amt_1' => 'decimal:2',
        'fixed_imprest_date_1' => 'date',
        'fixed_imprest_amt_2' => 'decimal:2',
        'fixed_imprest_date_2' => 'date',
        'advance_amt_1' => 'decimal:2',
        'advance_date_1' => 'date',
        'advance_amt_2' => 'decimal:2',
        'advance_date_2' => 'date',
        'advance_amt_3' => 'decimal:2',
        'advance_date_3' => 'date',
        'advance_amt_4' => 'decimal:2',
        'advance_date_4' => 'date',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'deleted_at' => 'datetime'
    ];

    /**
     * Get the employee that owns the ledger balance
     */
    public function employee()
    {
        return $this->belongsTo(HRMEmployees::class, 'employee_id');
    }

    /**
     * Get the financial year
     */
    public function financialYear()
    {
        return $this->belongsTo(FinancialYear::class, 'year_id');
    }

    /**
     * Get the company
     */
    public function company()
    {
        return $this->belongsTo(CoreCompany::class, 'company_id');
    }

    /**
     * Scope to filter by employee
     */
    public function scopeForEmployee($query, $employeeId)
    {
        return $query->where('employee_id', $employeeId);
    }

    /**
     * Scope to filter by financial year
     */
    public function scopeForYear($query, $yearId)
    {
        return $query->where('year_id', $yearId);
    }

    /**
     * Scope to filter by company
     */
    public function scopeForCompany($query, $companyId)
    {
        return $query->where('company_id', $companyId);
    }

    /**
     * Get balance summary for an employee in a specific year
     */
    public static function getEmployeeBalance($employeeId, $yearId)
    {
        return self::where('employee_id', $employeeId)
            ->where('year_id', $yearId)
            ->selectRaw('
                SUM(dr) as total_debit,
                SUM(cr) as total_credit,
                SUM(balance) as current_balance,
                MAX(fixed_imprest_amt_1) as fixed_imprest_amount_1,
                MAX(fixed_imprest_date_1) as fixed_imprest_date_1,
                MAX(fixed_imprest_amt_2) as fixed_imprest_amount_2,
                MAX(fixed_imprest_date_2) as fixed_imprest_date_2
            ')
            ->first();
    }

    /**
     * Get ledger entries for a specific period
     */
    public static function getEntriesForPeriod($employeeId, $yearId, $month = null)
    {
        $query = self::where('employee_id', $employeeId)
            ->where('year_id', $yearId);

        if ($month) {
            $query->where(function($q) use ($month) {
                $q->where('advance_month_1', $month)
                  ->orWhere('advance_month_2', $month)
                  ->orWhere('advance_month_3', $month)
                  ->orWhere('advance_month_4', $month);
            });
        }

        return $query->orderBy('created_at', 'desc')->get();
    }
}