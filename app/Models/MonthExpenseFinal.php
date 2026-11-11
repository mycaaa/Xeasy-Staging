<?php

namespace App\Models;

use App\Traits\CompanyDatabaseConnection;
use Illuminate\Database\Eloquent\Model;
use Spatie\Activitylog\Traits\LogsActivity;
use Spatie\Activitylog\LogOptions;

class MonthExpenseFinal extends Model
{
    use LogsActivity, CompanyDatabaseConnection;


    protected $primaryKey = 'id';
    public $timestamps = false;

    protected $fillable = [
        'DocId',
        'DocNo',
        'EmployeeID',
        'Month',
        'YearId',
        'AllowSubmit',
        'Status',
        'SubPermission',
        'Odo_Approve_Final_Submit',
        'Km_Confirmation',
        'Crdate',
        'DateOfSubmit',
        'Verified_Date',
        'Approved_Date',
        'Finance_Date',
        'Fin_AppDate',
        'Fin_PayDate',
        'PostDate',
        'RecevingDate',
        'courier_date',
        'VerifDate',
        'CNIssueDate',
        'Total_Claim',
        'Claim_Amount',
        'Verified_Amount',
        'Approved_Amount',
        'Finance_Amount',
        'Move_FocuseSheet',
        'Fin_AppBy',
        'Fin_AppTime',
        'Fin_AdvancePay',
        'Fin_PayOption',
        'Fin_PayBy',
        'Fin_PayAmt',
        'advance_payment_amount',
        'advance_payment_date',
        'advance_payment_for_month',
        'payment_status',
        'payment_amount',
        'ledger_balance_at_approval',
        'adjust_activity_amount',
        'adjust_expense_amount',
        'payment_approved_date',
        'payment_instructed_date',
        'payment_confirmed_date',
        'payment_unsettled_date',
        'Fin_PayRemark',
        'Fin_PayRemark_1',
        'Fin_PayRemark_2',
        'DocateNo',
        'Agency',
        'CuImag',
        'DocRmk',
    ];

    // default values for missing fields
    protected $attributes = [
        'Status' => 'Open',
    ];

    protected $casts = [
        'Crdate' => 'datetime:Y-m-d H:i:s',
        'DateOfSubmit' => 'date:Y-m-d',
        'Verified_Date' => 'date:Y-m-d',
        'Approved_Date' => 'date:Y-m-d',
        'Finance_Date' => 'date:Y-m-d',
        'Fin_AppDate' => 'date:Y-m-d',
        'Fin_PayDate' => 'date:Y-m-d',
        'PostDate' => 'date:Y-m-d',
        'RecevingDate' => 'date:Y-m-d',
        'VerifDate' => 'date:Y-m-d',
        'CNIssueDate' => 'date:Y-m-d',
    ];

    /**
     * ──────────────────────────────────────────────────────────────
     * AUTO CONVERT NULL/EMPTY DATE FIELDS → "0000-00-00"
     * ──────────────────────────────────────────────────────────────
     */
    private function maskDate($value)
    {
        if (empty($value) || $value === null || $value === 'null') {
            return '0000-00-00';
        }
        return $value;
    }

    public function setDateOfSubmitAttribute($value)
    {
        $this->attributes['DateOfSubmit'] = $this->maskDate($value);
    }

    public function setVerifiedDateAttribute($value)
    {
        $this->attributes['Verified_Date'] = $this->maskDate($value);
    }

    public function setApprovedDateAttribute($value)
    {
        $this->attributes['Approved_Date'] = $this->maskDate($value);
    }

    public function setFinanceDateAttribute($value)
    {
        $this->attributes['Finance_Date'] = $this->maskDate($value);
    }

    public function setFinAppDateAttribute($value)
    {
        $this->attributes['Fin_AppDate'] = $this->maskDate($value);
    }

    public function setFinPayDateAttribute($value)
    {
        $this->attributes['Fin_PayDate'] = $this->maskDate($value);
    }

    public function setPostDateAttribute($value)
    {
        $this->attributes['PostDate'] = $this->maskDate($value);
    }

    public function setRecevingDateAttribute($value)
    {
        $this->attributes['RecevingDate'] = $this->maskDate($value);
    }

    public function setVerifDateAttribute($value)
    {
        $this->attributes['VerifDate'] = $this->maskDate($value);
    }

    public function setCNIssueDateAttribute($value)
    {
        $this->attributes['CNIssueDate'] = $this->maskDate($value);
    }

    public static function tableName(int $yearId): string
    {
        return 'y' . $yearId . '_monthexpensefinal';
    }

    

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logAll()
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs();
    }
}
