<?php

namespace App\Models;

use App\Traits\CompanyDatabaseConnection;
use Illuminate\Database\Eloquent\Model;
use Spatie\Activitylog\Traits\LogsActivity;
use Spatie\Activitylog\LogOptions;

class ExpenseClaim extends Model
{
    use LogsActivity, CompanyDatabaseConnection;


    protected $primaryKey = 'ExpId';

    public $timestamps = false;

    protected $table;

    protected $fillable = [
        'ExpId',
        'ClaimId',
        'activity_type',
        'odomtr_opening',
        'opening_filepath',
        'odomtr_closing',
        'closing_filepath',
        'offline_opening_filepath',
        'offline_clossing_filepath',
        'TotKm',
        'WType',
        'v_verify',
        'c_verify',
        'RatePerKM',
        'With_Driver',
        'VType',
        'Img_1',
        'Img_2',
        'Img_3',
        'Img_4',
        'Img_5',
        'Amount',
        'Remark',
        'DateEntryRemark',
        'ClaimStatus',
        'ClaimAtStep',
        'Rmk',
        'CrBy',
        'CrDate',
        'BillDate',
        'ClaimMonth',
        'ClaimYearId',
        'FilledBy',
        'FilledTAmt',
        'FilledDate',
        'FilledOkay',
        'FilOkDenyRemark',
        'Opening_Remark',
        'Closing_Remark',
        'Other_Remark',
        'VerifyBy',
        'VerifyTAmt',
        'VerifyTRemark',
        'VerifyDate',
        'ApprBy',
        'ApprTAmt',
        'ApprTRemark',
        'ApprDate',
        'FinancedBy',
        'FinancedTAmt',
        'FinancedTRemark',
        'FinancedDate',
        'AttachTo',
        'RtnBy',
        'offline_exp_id',
        'Img1_offline',
        'Img2_offline',
        'Img3_offline',
        'Pdf1_offline',
        'Pdf2_offline',
        'App_Odometer',
        'Backdate_Odometer_Status',
        'Backdate_Odometer_By',
        'Backdate_Odometer_Date',
        'temp_punch_id',
    ];


    // Spatie activity log config
    protected static $logAttributes = ['*']; // Log all attributes
    protected static $logName = 'expense_claim';
    protected static $logOnlyDirty = true; // Only log changed fields
    protected static $submitEmptyLogs = false;

    
    public static function tableName(int $yearId): string
    {
        return 'y' . $yearId . '_expenseclaims';
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logAll()
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs();
    }
}
