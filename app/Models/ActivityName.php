<?php
namespace App\Models;
use App\Traits\CompanyDatabaseConnection;
use Illuminate\Database\Eloquent\Model;
use App\Models\CoreDepartments;
use App\Models\CoreVertical;
class ActivityName extends Model {
    use CompanyDatabaseConnection;

    protected $table = 'adv_activity_names';
    protected $primaryKey = 'id';
    protected $connection = null;
    protected $connectionType = 'expense';
    public $timestamps = true;
    protected $fillable = ['category_id', 'activity_name', 'description', 'dept_id', 'vertical', 'from_month', 'to_month', 'from_year', 'to_year', 'approved_limit', 'approved_amount', 'status', ];
    protected $casts = ['status' => 'boolean', 'dept_id' => 'string', 'vertical' => 'string', 'from_month' => 'date', 'to_month' => 'date', 'approved_limit' => 'decimal:0', 'approved_amount' => 'integer', ];
    public function category() {
        return $this->belongsTo(ActivityCategory::class, 'category_id', 'id');
    }
    public function claimType() {
        return $this->belongsTo(ClaimType::class, 'activity_name', 'ClaimId');
    }
    public function getDepartmentIdsAttribute() {
        return $this->dept_id ? explode(',', $this->dept_id) : [];
    }
    public function setDepartmentIdsAttribute($value) {
        $this->attributes['dept_id'] = is_array($value) ? implode(',', $value) : $value;
    }
    public function getVerticalsAttribute() {
        return $this->vertical ? explode(',', $this->vertical) : [];
    }
    public function setVerticalsAttribute($value) {
        $this->attributes['vertical'] = is_array($value) ? implode(',', $value) : $value;
    }
    public function getClaimNameAttribute() {
        return $this->claimType ? $this->claimType->ClaimName : '-';
    }
    public function getDepartmentsAttribute() {
        $deptIds = $this->getDepartmentIdsAttribute();
        if (empty($deptIds)) {
            return '-';
        }
        return CoreDepartments::whereIn('id', $deptIds)->where('is_active', 1)->pluck('department_name')->implode(', ') ? : '-';
    }
    public function getVerticalNamesAttribute() {
        $verticalIds = $this->getVerticalsAttribute();
        if (empty($verticalIds)) {
            return '-';
        }
        return CoreVertical::whereIn('id', $verticalIds)->where('is_active', 1)->pluck('vertical_name')->implode(', ') ? : '-';
    }
}
