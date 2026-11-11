<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class EmployeeVehicle extends Model
{
    use HasFactory;
    protected $connection = 'hrims';
    protected $table = 'hrm_employee_vehicle';
    protected $primaryKey = 'id';
    public $incrementing = true;
    protected $keyType = 'int';
    public $timestamps = true;
    protected $fillable = ['EmployeeID', 'EmpCode', 'brand', 'vehicle_type', 'vehiclevalue', 'oldnew', 'model_name', 'model_no', 'dealer_name', 'dealer_contact', 'purchase_date', 'price', 'registration_no', 'registration_date', 'policyentrydate', 'promotion_elig_date', 'lastslab_change_grade', 'bill_no', 'invoice', 'fuel_type', 'ownership', 'vehicle_image', 'rc_file', 'insurance', 'current_odo_meter', 'odo_meter', 'remark', 'CreatedBy', 'CreatedDate', 'YearId', 'departmentid', 'status', 'dl_file', 'bill_file', 'hr_approval', 'hr_approved_by', 'hr_approval_at', 'hr_remarks',];
    protected $dates = ['purchase_date', 'registration_date', 'policyentrydate', 'promotion_elig_date', 'lastslab_change_grade', 'CreatedDate', 'hr_approval_at', 'created_at', 'updated_at'];
    protected $casts = ['vehiclevalue' => 'float', 'hr_approval' => 'integer',];
    public function employee()
    {
        return $this->belongsTo(HRMEmployees::class, 'EmployeeID', 'EmployeeID');
    }
}
