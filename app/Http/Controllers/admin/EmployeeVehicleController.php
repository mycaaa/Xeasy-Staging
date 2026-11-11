<?php

namespace App\Http\Controllers\admin;

use App\Exports\EmployeeVehicleExport;
use App\Http\Controllers\Controller;
use App\Models\CoreDepartments;
use App\Models\EligibilityPolicy;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Maatwebsite\Excel\Facades\Excel;
use Yajra\DataTables\Facades\DataTables;

class EmployeeVehicleController extends Controller
{
    public static function getColumnsConfig()
    {
        return [
            // Basic Employee Info
            'sr_no' => ['label' => 'Sr. No', 'group' => 'Basic Info', 'default' => true, 'order' => 1],
            'emp_code' => ['label' => 'Employee Code', 'group' => 'Basic Info', 'default' => true, 'order' => 2],
            'emp_name' => ['label' => 'Employee Name', 'group' => 'Basic Info', 'default' => true, 'order' => 3],
            'department' => ['label' => 'Department', 'group' => 'Basic Info', 'default' => false, 'order' => 4],
            'grade' => ['label' => 'Grade', 'group' => 'Basic Info', 'default' => true, 'order' => 5],
            'emp_status' => ['label' => 'Status', 'group' => 'Basic Info', 'default' => true, 'order' => 6],

            // Vehicle Info
            'purchase_date' => ['label' => 'Purchase Date', 'group' => 'Vehicle Info', 'default' => false, 'order' => 7],
            'oldnew' => ['label' => 'Old / New', 'group' => 'Vehicle Info', 'default' => true, 'order' => 8],
            'policyentrydate' => ['label' => 'Policy Entry Date', 'group' => 'Vehicle Info', 'default' => true, 'order' => 9],
            'registration_date' => ['label' => 'Reg Date', 'group' => 'Vehicle Info', 'default' => true, 'order' => 10],
            'vehicle_age' => ['label' => 'Vehicle Age', 'group' => 'Vehicle Info', 'default' => true, 'order' => 11],
            'vehicle_type' => ['label' => 'Vehicle Type', 'group' => 'Vehicle Info', 'default' => true, 'order' => 12],
            'reg_no' => ['label' => 'Registration No', 'group' => 'Vehicle Info', 'default' => true, 'order' => 13],
            'brand_model' => ['label' => 'Brand & Model', 'group' => 'Vehicle Info', 'default' => false, 'order' => 14],
            'fuel_type' => ['label' => 'Fuel Type', 'group' => 'Vehicle Info', 'default' => false, 'order' => 15],
            'vehicle_price' => ['label' => 'Current Vehicle Value', 'group' => 'Vehicle Info', 'default' => true, 'order' => 16],

            // Policy Details & Limits
            'policy_name' => ['label' => 'Policy Name', 'group' => 'Policy Details', 'default' => true, 'order' => 17],
            'plan' => ['label' => 'Plan', 'group' => 'Policy Details', 'default' => false, 'order' => 18],
            'flat_2w' => ['label' => 'Flat 2W Rate', 'group' => 'Policy Details', 'default' => true, 'order' => 19],
            'flat_4w' => ['label' => 'Flat 4W Rate', 'group' => 'Policy Details', 'default' => true, 'order' => 20],
            'limit_2w_km' => ['label' => 'Monthly Limit 2W KM', 'group' => 'Policy Details', 'default' => false, 'order' => 21],
            'limit_4w_km' => ['label' => 'Monthly Limit 4W KM', 'group' => 'Policy Details', 'default' => false, 'order' => 22],
            'policy_vehicle_value' => ['label' => 'Policy Vehicle Value', 'group' => 'Policy Details', 'default' => false, 'order' => 23],
            'policy_tenure' => ['label' => 'Policy Tenure', 'group' => 'Policy Details', 'default' => false, 'order' => 24],
            'max_km_month' => ['label' => 'Max KM / Month', 'group' => 'Policy Details', 'default' => false, 'order' => 25],
            'reimbursement_rate' => ['label' => 'Current Rate', 'group' => 'Policy Details', 'default' => true, 'order' => 26],
            'rate_reason' => ['label' => 'Rate Reason / Rule', 'group' => 'Policy Details', 'default' => true, 'order' => 27],
            'with_driver' => ['label' => 'With Driver', 'group' => 'Policy Details', 'default' => false, 'order' => 28],
            'advance_com' => ['label' => 'Advance Company', 'group' => 'Policy Details', 'default' => false, 'order' => 29],

            // Document Check
            'doc_status' => ['label' => 'Doc Summary', 'group' => 'Document Check', 'default' => true, 'order' => 30],
            'rc_check' => ['label' => 'RC File Check', 'group' => 'Document Check', 'default' => false, 'order' => 31],
            'ins_check' => ['label' => 'Insurance Check', 'group' => 'Document Check', 'default' => false, 'order' => 32],
            'dl_check' => ['label' => 'DL File Check', 'group' => 'Document Check', 'default' => false, 'order' => 33],
            'img_check' => ['label' => 'Vehicle Photo Check', 'group' => 'Document Check', 'default' => false, 'order' => 34],
            'odo_check' => ['label' => 'Odometer Photo Check', 'group' => 'Document Check', 'default' => false, 'order' => 35],
            'bill_check' => ['label' => 'Bill File Check', 'group' => 'Document Check', 'default' => false, 'order' => 36],
        ];
    }

    public function employeeVehicles()
    {
        $departments = CoreDepartments::where('is_active', 1)->orderBy('department_name')->get(['id', 'department_name']);
        $eligibility_policy = EligibilityPolicy::where('CompanyId', session('company_id'))->get(['PolicyId', 'PolicyName']);
        $columnsConfig = self::getColumnsConfig();
        return view('verifier.employee_vehicles', compact('departments', 'eligibility_policy', 'columnsConfig'));
    }

    private function employeeVehicleQuery($department = null, $policy = null, $status = null)
    {
        $query = DB::connection('hrims')->table('hrm_employee as e')
            ->leftJoin('hrm_employee_eligibility as el', 'e.EmployeeID', '=', 'el.EmployeeID')
            ->leftJoin('hrm_employee_general as g', 'e.EmployeeID', '=', 'g.EmployeeID')
            ->leftJoin('hrm_master_eligibility_policy as elp', 'el.VehiclePolicy', '=', 'elp.PolicyId')
            ->leftJoin('core_grades as gr', 'g.GradeId', '=', 'gr.id')
            ->leftJoin('core_departments as dep', 'g.DepartmentId', '=', 'dep.id')
            ->leftJoin('hrm_employee_vehicle as ev', 'e.EmployeeID', '=', 'ev.EmployeeID')
            ->where('el.Status', 'A')
            ->where('e.CompanyId', session('company_id'))
            ->select(
                'e.EmployeeID',
                'e.EmpCode',
                DB::raw("CONCAT(e.Fname, ' ', COALESCE(e.Sname, ''), ' ', COALESCE(e.Lname, '')) AS Name"),
                'e.EmpStatus',
                'dep.department_name',
                'gr.id as grade_id',
                'gr.grade_name',
                'el.Travel_TwoWeeKM as flat_2w',
                'el.Travel_FourWeeKM as flat_4w',
                'el.Travel_TwoWeeLimitPerMonth as limit_2w_km',
                'el.Travel_FourWeeLimitPerMonth as limit_4w_km',
                'el.VehiclePolicy',
                'elp.PolicyName',
                'el.Plan',
                'el.WithDriver',
                'el.AdvanceCom',
                'el.CostOfVehicle as elig_vehicle_cost',
                'el.DateOfEntryPolicy as elig_entry_date',
                'ev.id as vehicle_id',
                'ev.vehicle_type as ev_vehicle_type',
                'ev.registration_no',
                'ev.brand',
                'ev.model_name',
                'ev.model_no',
                'ev.fuel_type',
                'ev.price as vehicle_price',
                'ev.vehiclevalue',
                'ev.current_odo_meter',
                'ev.purchase_date',
                'ev.policyentrydate',
                'ev.oldnew',
                'ev.registration_date',
                'ev.rc_file',
                'ev.insurance',
                'ev.dl_file',
                'ev.vehicle_image',
                'ev.odo_meter',
                'ev.bill_file'
            );

        if ($department) {
            $query->where('g.DepartmentId', $department);
        }
        if ($policy) {
            $query->where('el.VehiclePolicy', $policy);
        }
        if ($status) {
            $query->where('e.EmpStatus', $status);
        }
        return $query;
    }

    private static $policyTableCache = [];

    private function getPolicyTableData($policyId)
    {
        if (!$policyId) return collect();
        if (!isset(self::$policyTableCache[$policyId])) {
            $policyTable = "hrm_master_eligibility_policy_tbl{$policyId}";
            $tableExists = DB::connection('hrims')->select("SHOW TABLES LIKE '{$policyTable}'");
            if (!empty($tableExists)) {
                self::$policyTableCache[$policyId] = DB::connection('hrims')->table($policyTable)->get()->keyBy('GradeId');
            } else {
                self::$policyTableCache[$policyId] = collect();
            }
        }
        return self::$policyTableCache[$policyId];
    }

    /**
     * Calculate comprehensive vehicle age, rate, and detailed explanation
     */
    public static function calculateRateAndExplanation($row, $policyData = null)
    {
        $oldNew = strtolower(trim($row->oldnew ?? 'new'));
        if (!in_array($oldNew, ['new', 'old'])) {
            $oldNew = 'new';
        }

        // 1. Vehicle Age Calculation
        $regDate = $row->registration_date;
        if (empty($regDate) || in_array($regDate, ['0000-00-00', '1970-01-01'])) {
            $regDate = $row->purchase_date;
        }
        if (empty($regDate) || in_array($regDate, ['0000-00-00', '1970-01-01'])) {
            $regDate = $row->policyentrydate;
        }

        $vehicleAgeYears = 0.0;
        $vehicleAgeText = 'N/A';
        $years = 0;
        $months = 0;
        $hasValidDate = false;

        if (!empty($regDate) && !in_array($regDate, ['0000-00-00', '1970-01-01'])) {
            try {
                $parsedDate = \Carbon\Carbon::parse($regDate);
                $diff = $parsedDate->diff(\Carbon\Carbon::now());
                $years = $diff->y;
                $months = $diff->m;
                $vehicleAgeYears = round($years + ($months / 12), 1);
                $vehicleAgeText = "{$years}y {$months}m";
                $hasValidDate = true;
            } catch (\Exception $e) {
                // Keep defaults
            }
        }

        // 2. Vehicle Value: prioritize vehiclevalue (actual valuation/invoice value) over price (policy cap/budget)
        $vehicleValue = (float) (($row->vehiclevalue ?? 0) > 0 ? $row->vehiclevalue : ($row->vehicle_price ?? ($row->price ?? 0)));
        $vehicleValueFormatted = $vehicleValue > 0 ? '₹' . number_format($vehicleValue) : 'N/A';

        // 3. Determine Wheel Type
        $policyId = (int) ($row->VehiclePolicy ?? 0);
        $gradeId = (int) ($row->grade_id ?? 0);

        $isFourWheeler = in_array($policyId, [1, 2, 4, 5, 6, 7, 11, 12, 13])
            || (isset($row->ev_vehicle_type) && $row->ev_vehicle_type == 4)
            || (stripos($row->PolicyName ?? '', 'car') !== false || stripos($row->PolicyName ?? '', 'four') !== false || stripos($row->PolicyName ?? '', '4w') !== false);

        // 4. Policy rule for employee's assigned grade
        $assignedPolicyRule = null;
        if ($policyData && $gradeId && isset($policyData[$gradeId])) {
            $assignedPolicyRule = $policyData[$gradeId];
        }

        $policyVehicleValue = null;
        if ($assignedPolicyRule) {
            $rawElig = $assignedPolicyRule->Fn2 ?? ($assignedPolicyRule->Fn14 ?? null);
            if ($rawElig && is_numeric($rawElig)) {
                $policyVehicleValue = (float) $rawElig;
            }
        }
        // Fallback to vehicle_price / price if it represents the policy cap (greater than actual vehicle value)
        if (!$policyVehicleValue) {
            $capCandidate = (float) ($row->vehicle_price ?? ($row->price ?? 0));
            if ($capCandidate > $vehicleValue) {
                $policyVehicleValue = $capCandidate;
            }
        }

        // Assigned base rate for employee's actual grade (e.g. M4 -> 15.5)
        $assignedRate = 0.0;
        if ($assignedPolicyRule && !empty($assignedPolicyRule->Fn6) && is_numeric($assignedPolicyRule->Fn6)) {
            $assignedRate = (float) $assignedPolicyRule->Fn6;
        } elseif ($assignedPolicyRule && !empty($assignedPolicyRule->Fn15) && is_numeric($assignedPolicyRule->Fn15)) {
            $assignedRate = (float) $assignedPolicyRule->Fn15;
        } elseif (!empty($row->flat_4w) && is_numeric($row->flat_4w)) {
            $assignedRate = (float) $row->flat_4w;
        }

        // Grade downgrade check if vehicle value does not meet eligibility threshold
        $effectivePolicyRule = $assignedPolicyRule;
        $isGradeDowngraded = false;
        $downgradeDetails = '';

        if ($isFourWheeler && in_array($policyId, [1, 2, 4, 5, 6, 7, 11, 12, 13]) && $vehicleValue > 0) {
            // Find lower grade rule if available in $policyData
            $lowerRule = null;
            if ($policyData && $policyData->isNotEmpty()) {
                if (isset($policyData[$gradeId - 1])) {
                    $lowerRule = $policyData[$gradeId - 1];
                } else {
                    foreach ($policyData as $pRule) {
                        $pRate = (float) ($pRule->Fn6 ?? 0);
                        if ($pRate > 0 && $assignedRate > 0 && $pRate < $assignedRate) {
                            if (!$lowerRule || $pRate > (float)($lowerRule->Fn6 ?? 0)) {
                                $lowerRule = $pRule;
                            }
                        }
                    }
                }
            }

            // Check if vehicle value is below eligibility threshold
            // In Policy 12 / Own Your Car: Grade M4 assigned rate is 15.5.
            // When vehicle value <= 11 Lakhs (or below policy cap), employee receives 14.0 rate instead of 15.5.
            $isBelowValueThreshold = false;
            // RATE-03: policy allows a car up to Rs 50,000 below the grade's eligible value
            $allowance = (float) config('claim_rules.vehicle_value_allowance', 50000);
            if ($policyVehicleValue && $vehicleValue > 0 && ($vehicleValue + $allowance) < $policyVehicleValue) {
                $isBelowValueThreshold = true;
                $downgradeDetails = "vehicle value (₹" . number_format($vehicleValue) . ") is more than ₹" . number_format($allowance) . " below the " . ($row->grade_name ?? 'assigned grade') . " eligible value of ₹" . number_format($policyVehicleValue);
            } elseif (!$policyVehicleValue && $assignedRate >= 15.5 && $vehicleValue > 0 && ($vehicleValue + $allowance) < 1100000) {
                $isBelowValueThreshold = true;
                $downgradeDetails = "vehicle value (₹" . number_format($vehicleValue) . ") is more than ₹" . number_format($allowance) . " below the eligible value of ₹11,00,000 for ₹" . number_format($assignedRate, 2) . "/KM rate";
            }

            if ($isBelowValueThreshold) {
                $isGradeDowngraded = true;
                if ($lowerRule) {
                    $effectivePolicyRule = $lowerRule;
                }
            }
        }

        // Age limits
        $maxTenureLimit = ($oldNew === 'old') ? 5 : 7;
        $tier1AgeLimit = ($oldNew === 'old') ? 2 : 3;
        $isOverMaxAge = $hasValidDate && ($vehicleAgeYears > $maxTenureLimit);

        // 5. Rate and Reason Determination
        $rate = 0.0;
        $rateType = 'Standard';
        $reason = '';
        $shortReason = '';
        $badgeClass = 'bg-secondary';

        if ($isFourWheeler) {
            $isLessKm = (stripos($row->PolicyName ?? '', 'less') !== false);

            if ($isLessKm) {
                $rate = (float) ($effectivePolicyRule->Fn6 ?? ($row->flat_4w ?? 10.0));
                $rateType = 'Less KM Run';
                $shortReason = 'Less KM Run Flat Rate';
                $reason = "Less KM Run Policy: Flat rate of ₹{$rate}/KM for 4-Wheeler.";
                $badgeClass = 'bg-info-subtle text-info border border-info-subtle';
            } elseif (in_array($policyId, [1, 2, 5, 7])) {
                // Own Your Car / Age-based 4W Policies
                if ($oldNew === 'old') {
                    if ($isOverMaxAge) {
                        // > 5 Years
                        $rate = (float) ($effectivePolicyRule->Fn28 ?? ($effectivePolicyRule->Fn20 ?? ($effectivePolicyRule->Fn6 ?? 10.0)));
                        $rateType = 'Over 5 Yrs (Reduced)';
                        $shortReason = "Age {$vehicleAgeText} > 5 yrs limit (Reduced Rate)";
                        $reason = "Pre-owned vehicle age ({$vehicleAgeText}) exceeds the 5-year policy limit. Reduced maintenance rate of ₹{$rate}/KM applies.";
                        $badgeClass = 'bg-danger-subtle text-danger border border-danger-subtle';
                    } elseif ($vehicleAgeYears > $tier1AgeLimit) {
                        // 2 to 5 Years
                        $rate = (float) ($effectivePolicyRule->Fn20 ?? ($effectivePolicyRule->Fn6 ?? 12.0));
                        $rateType = 'Tier-2 (2-5 Yrs)';
                        $shortReason = "Age {$vehicleAgeText} > 2 yrs (Tier-2 Rate)";
                        $reason = "Pre-owned vehicle age is {$vehicleAgeText} (> 2 yrs cutoff, ≤ 5 yrs limit). Tier-2 rate of ₹{$rate}/KM applies.";
                        $badgeClass = 'bg-warning-subtle text-warning-emphasis border border-warning-subtle';
                    } else {
                        // <= 2 Years
                        $rate = (float) ($effectivePolicyRule->Fn19 ?? ($effectivePolicyRule->Fn6 ?? 14.0));
                        $rateType = 'Tier-1 (≤2 Yrs)';
                        $shortReason = "Age {$vehicleAgeText} ≤ 2 yrs (Fresh Rate)";
                        $reason = "Pre-owned vehicle age is {$vehicleAgeText} (within 2-year threshold). Standard Tier-1 rate of ₹{$rate}/KM applies.";
                        $badgeClass = 'bg-success-subtle text-success border border-success-subtle';
                    }
                } else {
                    // New vehicle rules
                    if ($isOverMaxAge) {
                        // > 7 Years
                        $rate = (float) ($effectivePolicyRule->Fn24 ?? ($effectivePolicyRule->Fn16 ?? ($effectivePolicyRule->Fn6 ?? 10.0)));
                        $rateType = 'Over 7 Yrs (Reduced)';
                        $shortReason = "Age {$vehicleAgeText} > 7 yrs limit (Reduced Rate)";
                        $reason = "Vehicle age ({$vehicleAgeText}) exceeds the 7-year policy limit for new vehicles. Reduced maintenance rate of ₹{$rate}/KM applies.";
                        $badgeClass = 'bg-danger-subtle text-danger border border-danger-subtle';
                    } elseif ($vehicleAgeYears > $tier1AgeLimit) {
                        // 3 to 7 Years
                        $rate = (float) ($effectivePolicyRule->Fn16 ?? ($effectivePolicyRule->Fn6 ?? 14.0));
                        $rateType = 'Tier-2 (3-7 Yrs)';
                        $shortReason = "Age {$vehicleAgeText} > 3 yrs (Tier-2 Rate)";
                        $reason = "New vehicle age is {$vehicleAgeText} (> 3 yrs cutoff, ≤ 7 yrs policy tenure limit). Tier-2 rate of ₹{$rate}/KM applies.";
                        $badgeClass = 'bg-primary-subtle text-primary border border-primary-subtle';
                    } else {
                        // <= 3 Years
                        $rate = (float) ($effectivePolicyRule->Fn15 ?? ($effectivePolicyRule->Fn6 ?? 15.5));
                        $rateType = 'Tier-1 (≤3 Yrs)';
                        $shortReason = "Age {$vehicleAgeText} ≤ 3 yrs (Tier-1 Fresh Rate)";
                        $reason = "New vehicle age is {$vehicleAgeText} (within 3-year threshold). Standard fresh vehicle rate of ₹{$rate}/KM applies.";
                        $badgeClass = 'bg-success-subtle text-success border border-success-subtle';
                    }
                }
            } elseif ($policyId == 12) {
                $baseRate = (float) ($effectivePolicyRule->Fn6 ?? 0);
                $reducedRate = (float) ($effectivePolicyRule->Fn37 ?? 0);
                if ($isOverMaxAge && $reducedRate > 0) {
                    $rate = $reducedRate;
                    $rateType = ($oldNew === 'old' ? 'Over 5 Yrs' : 'Over 7 Yrs') . ' (Reduced)';
                    $shortReason = "Age {$vehicleAgeText} > {$maxTenureLimit} yrs limit (Reduced Rate)";
                    $reason = "Vehicle age ({$vehicleAgeText}) exceeds {$maxTenureLimit}-year policy tenure limit. Reduced rate of ₹{$rate}/KM applies.";
                    $badgeClass = 'bg-danger-subtle text-danger border border-danger-subtle';
                } elseif ($isGradeDowngraded) {
                    $rate = ($baseRate > 0 && $baseRate < $assignedRate) ? $baseRate : 14.0;
                    $rateType = 'Value Downgraded';
                    $shortReason = "Downgraded to ₹" . number_format($rate, 0) . " (Assigned ₹" . number_format($assignedRate, 1) . ") • Veh Value: ₹" . number_format($vehicleValue);
                    $reason = "Vehicle Current Value: ₹" . number_format($vehicleValue) . ". Assigned rate is ₹" . number_format($assignedRate, 2) . "/KM (" . ($row->grade_name ?? 'Grade M4') . "), but due to vehicle value, employee receives ₹" . number_format($rate, 2) . "/KM. " . ucfirst($downgradeDetails) . ".";
                    $badgeClass = 'bg-warning-subtle text-warning-emphasis border border-warning-subtle';
                } else {
                    $rate = $baseRate > 0 ? $baseRate : (float)($row->flat_4w ?? 0);
                    $rateType = 'Policy 12 Rate';
                    $shortReason = 'Policy 12 Rate';
                    $reason = "Policy 12 rate applied: ₹{$rate}/KM.";
                    $badgeClass = 'bg-info-subtle text-info border border-info-subtle';
                }
            } elseif ($effectivePolicyRule && !empty($effectivePolicyRule->Fn6)) {
                $rate = (float) $effectivePolicyRule->Fn6;
                $rateType = 'Policy Rate';
                $shortReason = "Policy Rate (" . ($row->PolicyName ?? 'Standard') . ")";
                $reason = "Standard rate configured in " . ($row->PolicyName ?? 'Policy') . ": ₹{$rate}/KM.";
                $badgeClass = 'bg-info-subtle text-info border border-info-subtle';
            } elseif (!empty($row->flat_4w)) {
                if (strtolower($row->flat_4w) === 'actual') {
                    $rate = 'Actual';
                    $rateType = 'Actual';
                    $shortReason = 'Actual Fuel Reimbursement';
                    $reason = 'Reimbursed on actual fuel receipts as per employee grade eligibility.';
                    $badgeClass = 'bg-secondary-subtle text-secondary border border-secondary-subtle';
                } else {
                    $rate = (float) $row->flat_4w;
                    $rateType = 'Flat 4W';
                    $shortReason = 'Flat 4W Allowance';
                    $reason = "Flat 4W travel allowance: ₹{$rate}/KM.";
                    $badgeClass = 'bg-light text-dark border';
                }
            }
        } else {
            // Two Wheeler
            if ($effectivePolicyRule && !empty($effectivePolicyRule->Fn6)) {
                $rate = (float) $effectivePolicyRule->Fn6;
                $rateType = '2W Policy Rate';
                $shortReason = '2W Policy Rate';
                $reason = "Two-wheeler policy rate (" . ($row->PolicyName ?? 'Standard') . "): ₹{$rate}/KM.";
                $badgeClass = 'bg-info-subtle text-info border border-info-subtle';
            } elseif (!empty($row->flat_2w)) {
                if (strtolower($row->flat_2w) === 'actual') {
                    $rate = 'Actual';
                    $rateType = 'Actual';
                    $shortReason = 'Actual Fuel Reimbursement';
                    $reason = 'Reimbursed on actual fuel receipts.';
                    $badgeClass = 'bg-secondary-subtle text-secondary border border-secondary-subtle';
                } else {
                    $rate = (float) $row->flat_2w;
                    $rateType = 'Flat 2W';
                    $shortReason = 'Flat 2W Allowance';
                    $reason = "Flat 2W travel allowance: ₹{$rate}/KM.";
                    $badgeClass = 'bg-light text-dark border';
                }
            }
        }

        // If grade was downgraded due to vehicle value in other policies
        if ($isGradeDowngraded && $assignedRate > $rate && $rateType !== 'Value Downgraded') {
            $shortReason = "Downgraded to ₹" . number_format($rate, 0) . " (Assigned ₹" . number_format($assignedRate, 1) . ") • Veh Value: ₹" . number_format($vehicleValue);
            $reason = "Vehicle Current Value: ₹" . number_format($vehicleValue) . ". Assigned rate is ₹" . number_format($assignedRate, 2) . "/KM (" . ($row->grade_name ?? 'Grade') . "), but due to vehicle value, eligible rate is adjusted to ₹" . number_format($rate, 2) . "/KM. " . ucfirst($downgradeDetails) . ". " . $reason;
            $badgeClass = 'bg-warning-subtle text-warning-emphasis border border-warning-subtle';
        }

        // Prepend vehicle current value to reason if not already present
        if ($vehicleValue > 0 && strpos($reason, 'Vehicle Current Value:') === false) {
            $reason = "Vehicle Current Value: ₹" . number_format($vehicleValue) . ($policyVehicleValue ? " (Policy Cap: ₹" . number_format($policyVehicleValue) . ")" : "") . ". " . $reason;
        }

        // Vehicle age badge
        $ageBadgeClass = 'bg-secondary-subtle text-secondary';
        $ageBadgeIcon = 'ri-time-line';
        if ($hasValidDate) {
            if ($isOverMaxAge) {
                $ageBadgeClass = 'bg-danger-subtle text-danger border border-danger-subtle';
                $ageBadgeIcon = 'ri-alert-line';
            } elseif (($oldNew === 'old' && $vehicleAgeYears > 2) || ($oldNew === 'new' && $vehicleAgeYears > 3)) {
                $ageBadgeClass = 'bg-warning-subtle text-warning-emphasis border border-warning-subtle';
                $ageBadgeIcon = 'ri-time-line';
            } else {
                $ageBadgeClass = 'bg-success-subtle text-success border border-success-subtle';
                $ageBadgeIcon = 'ri-checkbox-circle-line';
            }
        }

        $isValueDowngraded = $isGradeDowngraded && ($assignedRate > $rate);

        return [
            'vehicle_age_years' => $vehicleAgeYears,
            'vehicle_age_text' => $vehicleAgeText,
            'vehicle_age_badge' => $hasValidDate
                ? "<span class=\"badge {$ageBadgeClass} fs-11\" title=\"Reg Date: " . ($row->registration_date ? date('d-m-Y', strtotime($row->registration_date)) : 'N/A') . "\"><i class=\"{$ageBadgeIcon} me-1\"></i>{$vehicleAgeText}</span>"
                : "<span class=\"text-muted fs-11\">-</span>",
            'is_over_max_age' => $isOverMaxAge,
            'max_age_limit' => $maxTenureLimit,
            'oldnew' => $oldNew,
            'vehicle_value' => $vehicleValue,
            'vehicle_value_formatted' => $vehicleValueFormatted,
            'policy_vehicle_value' => $policyVehicleValue,
            'policy_vehicle_value_formatted' => $policyVehicleValue ? '₹' . number_format($policyVehicleValue) : 'N/A',
            'assigned_rate' => $assignedRate,
            'assigned_rate_formatted' => $assignedRate > 0 ? '₹' . number_format($assignedRate, 2) . ' / KM' : 'N/A',
            'is_value_downgraded' => $isValueDowngraded,
            'current_rate' => is_numeric($rate) ? (float) $rate : $rate,
            'current_rate_formatted' => is_numeric($rate) ? '₹' . number_format((float)$rate, 2) . ' / KM' : (string) $rate,
            'rate_type' => $rateType,
            'rate_reason' => $reason,
            'rate_reason_short' => $shortReason,
            'rate_reason_badge' => "<span class=\"badge {$badgeClass} fs-11 text-wrap\" style=\"max-width: 270px; cursor: pointer;\" data-bs-toggle=\"tooltip\" data-bs-placement=\"top\" title=\"" . htmlspecialchars($reason, ENT_QUOTES) . "\">" . htmlspecialchars($shortReason) . "</span>",
        ];
    }

    public function getEmployeeVehiclesData(Request $request)
    {
        try {
            $query = $this->employeeVehicleQuery($request->department, $request->policy, $request->status);

            return DataTables::of($query)
                ->addIndexColumn()
                ->addColumn('vehicle_type', function ($row) {
                    if (in_array($row->VehiclePolicy, [1, 2, 4, 5, 6, 7, 11, 12, 13]) || (isset($row->ev_vehicle_type) && $row->ev_vehicle_type == 4) || stripos($row->PolicyName ?? '', 'car') !== false) {
                        return '4 Wheeler';
                    } elseif ($row->VehiclePolicy > 0 || (isset($row->ev_vehicle_type) && $row->ev_vehicle_type == 2)) {
                        return '2 Wheeler';
                    } else {
                        return '';
                    }
                })
                ->addColumn('brand_model', function ($row) {
                    $parts = array_filter([$row->brand, $row->model_name]);
                    return !empty($parts) ? implode(' - ', $parts) : '';
                })
                ->addColumn('vehicle_age', function ($row) {
                    $policyData = $this->getPolicyTableData($row->VehiclePolicy);
                    $calc = self::calculateRateAndExplanation($row, $policyData);
                    return $calc['vehicle_age_badge'];
                })
                ->addColumn('vehicle_price_formatted', function ($row) {
                    $policyData = $this->getPolicyTableData($row->VehiclePolicy);
                    $calc = self::calculateRateAndExplanation($row, $policyData);
                    $html = '<div class="text-end">';
                    if ($calc['vehicle_value'] > 0) {
                        $html .= '<div class="fw-bold text-dark fs-12">' . $calc['vehicle_value_formatted'] . '</div>';
                        $html .= '<div class="fs-10 text-muted">Current Value</div>';
                    } else {
                        $html .= '<span class="text-muted fs-11">-</span>';
                    }
                    if ($calc['policy_vehicle_value']) {
                        $capLabel = !empty($calc['is_value_downgraded']) ? 'Eligible Cap' : 'Policy Cap';
                        $html .= '<div class="fs-10 text-primary mt-1" title="Policy Value Cap">' . $capLabel . ': ' . $calc['policy_vehicle_value_formatted'] . '</div>';
                    }
                    $html .= '</div>';
                    return $html;
                })
                ->addColumn('policy_vehicle_value', function ($row) {
                    if (!$row->VehiclePolicy || !$row->grade_id) return '';
                    $policyData = $this->getPolicyTableData($row->VehiclePolicy);
                    $rule = $policyData[$row->grade_id] ?? null;
                    if (!$rule) return '';
                    $val = $rule->Fn2 ?? $rule->Fn14 ?? null;
                    return ($val && is_numeric($val)) ? '₹' . number_format((float)$val) : ($val ?? '');
                })
                ->addColumn('policy_tenure', function ($row) {
                    if (!$row->VehiclePolicy || !$row->grade_id) return '';
                    $policyData = $this->getPolicyTableData($row->VehiclePolicy);
                    $rule = $policyData[$row->grade_id] ?? null;
                    return $rule->Fn3 ?? '';
                })
                ->addColumn('max_km_month', function ($row) {
                    if (!$row->VehiclePolicy || !$row->grade_id) return '';
                    $policyData = $this->getPolicyTableData($row->VehiclePolicy);
                    $rule = $policyData[$row->grade_id] ?? null;
                    return $rule->Fn8 ?? $rule->Fn9 ?? $rule->Fn32 ?? '';
                })
                ->addColumn('reimbursement_rate', function ($row) {
                    $policyData = $this->getPolicyTableData($row->VehiclePolicy);
                    $calc = self::calculateRateAndExplanation($row, $policyData);
                    if (is_numeric($calc['current_rate']) && $calc['current_rate'] > 0) {
                        if (!empty($calc['is_value_downgraded']) && $calc['assigned_rate'] > $calc['current_rate']) {
                            return '<div class="d-flex flex-column align-items-center">'
                                . '<span class="fw-bold text-dark fs-12">₹' . number_format($calc['current_rate'], 2) . '</span>'
                                . '<span class="badge bg-warning-subtle text-warning-emphasis fs-10 border border-warning-subtle mt-1" data-bs-toggle="tooltip" data-bs-placement="top" title="Assigned Rate: ₹' . number_format($calc['assigned_rate'], 2) . ' / KM for ' . htmlspecialchars($row->grade_name ?? 'Grade') . ' | Current Value: ' . $calc['vehicle_value_formatted'] . '">'
                                . 'Assigned: <span class="text-decoration-line-through text-muted">₹' . number_format($calc['assigned_rate'], 1) . '</span>'
                                . '</span>'
                                . '</div>';
                        }
                        return '<span class="fw-bold text-dark fs-12">₹' . number_format($calc['current_rate'], 2) . '</span>';
                    }
                    if (!empty($calc['current_rate'])) {
                        return '<span class="badge bg-secondary-subtle text-secondary fs-11">' . htmlspecialchars($calc['current_rate']) . '</span>';
                    }
                    return '<span class="text-muted fs-11">-</span>';
                })
                ->addColumn('rate_reason', function ($row) {
                    $policyData = $this->getPolicyTableData($row->VehiclePolicy);
                    $calc = self::calculateRateAndExplanation($row, $policyData);
                    return $calc['rate_reason_badge'];
                })
                ->addColumn('doc_status', function ($row) {
                    $docs = [
                        'RC' => !empty($row->rc_file),
                        'Insurance' => !empty($row->insurance),
                        'DL' => !empty($row->dl_file),
                        'Photo' => !empty($row->vehicle_image),
                        'Odo' => !empty($row->odo_meter),
                        'Bill' => !empty($row->bill_file),
                    ];
                    $uploadedCount = count(array_filter($docs));
                    $total = count($docs);

                    $badgeClass = $uploadedCount === $total ? 'bg-success' : ($uploadedCount > 0 ? 'bg-warning' : 'bg-danger');
                    $html = '<div class="d-flex align-items-center justify-content-center gap-1">';
                    $html .= "<span class=\"badge {$badgeClass}\">{$uploadedCount}/{$total}</span>";

                    // Mini badges for each document
                    $html .= '<div class="btn-group btn-group-sm ms-1" role="group">';
                    foreach ($docs as $name => $uploaded) {
                        $cls = $uploaded ? 'btn-outline-success text-success' : 'btn-outline-secondary text-muted opacity-50';
                        $html .= "<span class=\"btn p-0 px-1 fs-11 {$cls}\" title=\"{$name}: " . ($uploaded ? 'Uploaded' : 'Missing') . "\">{$name}</span>";
                    }
                    $html .= '</div></div>';
                    return $html;
                })
                ->addColumn('rc_check', function ($row) {
                    return !empty($row->rc_file)
                        ? '<span class="badge bg-success-subtle text-success"><i class="ri-checkbox-circle-line me-1"></i>Yes</span>'
                        : '<span class="badge bg-danger-subtle text-danger"><i class="ri-close-circle-line me-1"></i>No</span>';
                })
                ->addColumn('ins_check', function ($row) {
                    return !empty($row->insurance)
                        ? '<span class="badge bg-success-subtle text-success"><i class="ri-checkbox-circle-line me-1"></i>Yes</span>'
                        : '<span class="badge bg-danger-subtle text-danger"><i class="ri-close-circle-line me-1"></i>No</span>';
                })
                ->addColumn('dl_check', function ($row) {
                    return !empty($row->dl_file)
                        ? '<span class="badge bg-success-subtle text-success"><i class="ri-checkbox-circle-line me-1"></i>Yes</span>'
                        : '<span class="badge bg-danger-subtle text-danger"><i class="ri-close-circle-line me-1"></i>No</span>';
                })
                ->addColumn('img_check', function ($row) {
                    return !empty($row->vehicle_image)
                        ? '<span class="badge bg-success-subtle text-success"><i class="ri-checkbox-circle-line me-1"></i>Yes</span>'
                        : '<span class="badge bg-danger-subtle text-danger"><i class="ri-close-circle-line me-1"></i>No</span>';
                })
                ->addColumn('odo_check', function ($row) {
                    return !empty($row->odo_meter)
                        ? '<span class="badge bg-success-subtle text-success"><i class="ri-checkbox-circle-line me-1"></i>Yes</span>'
                        : '<span class="badge bg-danger-subtle text-danger"><i class="ri-close-circle-line me-1"></i>No</span>';
                })
                ->addColumn('bill_check', function ($row) {
                    return !empty($row->bill_file)
                        ? '<span class="badge bg-success-subtle text-success"><i class="ri-checkbox-circle-line me-1"></i>Yes</span>'
                        : '<span class="badge bg-danger-subtle text-danger"><i class="ri-close-circle-line me-1"></i>No</span>';
                })
                ->addColumn('view', function ($row) {
                    $policyItem = '';
                    $vehicleIdAttr = !empty($row->vehicle_id) ? ' data-vehicleid="' . $row->vehicle_id . '"' : '';
                    if ($row->VehiclePolicy > 0 || !empty($row->vehicle_id)) {
                        $policyItem = '<li><a class="dropdown-item view-policy" href="javascript:void(0)" data-empid="' . $row->EmployeeID . '"' . $vehicleIdAttr . '><i class="ri-file-list-line me-2 text-primary"></i> Policy Details</a></li>';
                    }
                    return '<div class="dropdown text-center"><a href="javascript:void(0)" role="button" data-bs-toggle="dropdown"><i class="ri-more-2-fill fs-5"></i></a><ul class="dropdown-menu">' . $policyItem . '<li><a class="dropdown-item view-docs" href="javascript:void(0)" data-empid="' . $row->EmployeeID . '"' . $vehicleIdAttr . '><i class="ri-file-text-line me-2 text-info"></i> Vehicle Documents</a></li></ul></div>';
                })
                ->filter(function ($query) use ($request) {
                    if ($request->has('search') && !empty($request->search['value'])) {
                        $searchValue = $request->search['value'];
                        $query->where(function ($q) use ($searchValue) {
                            $q->where('e.EmpCode', 'like', "%{$searchValue}%")
                                ->orWhere(DB::raw("CONCAT(e.Fname, ' ', COALESCE(e.Sname, ''), ' ', COALESCE(e.Lname, ''))"), 'like', "%{$searchValue}%")
                                ->orWhere('dep.department_name', 'like', "%{$searchValue}%")
                                ->orWhere('gr.grade_name', 'like', "%{$searchValue}%")
                                ->orWhere('elp.PolicyName', 'like', "%{$searchValue}%")
                                ->orWhere('ev.registration_no', 'like', "%{$searchValue}%")
                                ->orWhere('ev.brand', 'like', "%{$searchValue}%")
                                ->orWhere('ev.model_name', 'like', "%{$searchValue}%");
                        });
                    }
                })
                ->order(function ($query) use ($request) {
                    if ($request->has('order')) {
                        $orderColumn = $request->order[0]['column'];
                        $orderDir = $request->order[0]['dir'];
                        $columns = [
                            0 => 'e.EmployeeID',
                            1 => 'e.EmpCode',
                            2 => 'Name',
                            3 => 'dep.department_name',
                            4 => 'gr.grade_name',
                            5 => 'e.EmpStatus',
                            6 => 'ev.purchase_date',
                            7 => 'ev.oldnew',
                            8 => 'ev.policyentrydate',
                            9 => 'ev.registration_date',
                            10 => 'elp.PolicyName',
                            11 => 'ev.registration_no',
                            12 => 'ev.brand',
                            13 => 'elp.PolicyName',
                            14 => 'el.Plan',
                            15 => 'el.Travel_TwoWeeKM',
                            16 => 'el.Travel_FourWeeKM',
                        ];
                        if (isset($columns[$orderColumn])) {
                            $query->orderBy($columns[$orderColumn], $orderDir);
                        }
                    } else {
                        $query->orderBy('e.EmployeeID', 'asc');
                    }
                })
                ->rawColumns(['view', 'doc_status', 'rc_check', 'ins_check', 'dl_check', 'img_check', 'odo_check', 'bill_check', 'vehicle_age', 'vehicle_price_formatted', 'reimbursement_rate', 'rate_reason'])
                ->make(true);
        } catch (\Exception $e) {
            return response()->json(['error' => true, 'message' => $e->getMessage()], 500);
        }
    }

    public function exportVehicles(Request $request)
    {
        $selectedColumns = $request->input('columns', []);
        if (is_string($selectedColumns)) {
            $selectedColumns = json_decode($selectedColumns, true) ?? [];
        }
        if (empty($selectedColumns)) {
            $allConfig = self::getColumnsConfig();
            $selectedColumns = array_keys(array_filter($allConfig, function ($col) {
                return $col['default'] ?? false;
            }));
        }

        $query = $this->employeeVehicleQuery($request->department, $request->policy, $request->status);
        return Excel::download(
            new EmployeeVehicleExport($query, $selectedColumns),
            'employee_vehicles_' . date('Y-m-d_His') . '.xlsx'
        );
    }

    public function getPolicyDetails(Request $request)
    {
        try {
            $employeeId = $request->empid;
            $vehicleId = $request->vehicleid ?? $request->vehicle_id;

            if (empty($employeeId) && !empty($vehicleId)) {
                $vRec = DB::connection('hrims')->table('hrm_employee_vehicle')->where('id', $vehicleId)->first();
                if ($vRec) {
                    $employeeId = $vRec->EmployeeID;
                }
            }

            $query = DB::connection('hrims')->table('hrm_employee as e')
                ->leftJoin('hrm_employee_eligibility as el', 'e.EmployeeID', '=', 'el.EmployeeID')
                ->leftJoin('hrm_employee_general as g', 'e.EmployeeID', '=', 'g.EmployeeID')
                ->leftJoin('hrm_master_eligibility_policy as elp', 'el.VehiclePolicy', '=', 'elp.PolicyId')
                ->leftJoin('core_grades as gr', 'g.GradeId', '=', 'gr.id')
                ->leftJoin('core_departments as dep', 'g.DepartmentId', '=', 'dep.id')
                ->where('e.EmployeeID', $employeeId)
                ->where('el.Status', 'A');

            if (!empty($vehicleId)) {
                $query->leftJoin('hrm_employee_vehicle as ev', function ($join) use ($vehicleId) {
                    $join->on('e.EmployeeID', '=', 'ev.EmployeeID')
                        ->where('ev.id', '=', $vehicleId);
                })->where('ev.id', $vehicleId);
            } else {
                $query->leftJoin('hrm_employee_vehicle as ev', 'e.EmployeeID', '=', 'ev.EmployeeID');
            }

            $employee = $query->select(
                    'e.EmployeeID',
                    'e.EmpCode',
                    DB::raw("CONCAT(e.Fname, ' ', COALESCE(e.Sname, ''), ' ', COALESCE(e.Lname, '')) AS Name"),
                    'dep.department_name',
                    'el.VehiclePolicy as PolicyId',
                    'elp.PolicyName',
                    'gr.id as GradeId',
                    'gr.grade_name as GradeName',
                    'ev.id as vehicle_id',
                    'ev.vehicle_type as ev_vehicle_type',
                    'ev.oldnew',
                    'ev.oldnew as VehicleType',
                    'el.Plan',
                    'el.Travel_TwoWeeKM as flat_2w',
                    'el.Travel_FourWeeKM as flat_4w',
                    'el.Travel_TwoWeeLimitPerMonth as limit_2w_km',
                    'el.Travel_FourWeeLimitPerMonth as limit_4w_km',
                    'el.WithDriver',
                    'el.AdvanceCom',
                    'el.CostOfVehicle',
                    'el.DateOfEntryPolicy',
                    'ev.registration_no',
                    'ev.brand',
                    'ev.model_name',
                    'ev.model_no',
                    'ev.fuel_type',
                    'ev.vehiclevalue',
                    'ev.price',
                    'ev.bill_no',
                    'ev.purchase_date',
                    'ev.registration_date',
                    'ev.policyentrydate',
                    'ev.rc_file',
                    'ev.insurance',
                    'ev.dl_file',
                    'ev.vehicle_image',
                    'ev.odo_meter',
                    'ev.bill_file'
                )
                ->first();

            if (!$employee) {
                return response()->json(['success' => false, 'message' => 'No policy details found for this employee']);
            }

            $policyId = $employee->PolicyId;
            $gradeId = $employee->GradeId;

            // Retrieve ALL mapped fields for this policy
            $fields = DB::connection('hrims')->table('hrm_master_eligibility_mapping_tblfield as m')
                ->join('hrm_master_eligibility_field as f', 'm.FieldId', '=', 'f.FieldId')
                ->where('m.PolicyId', $policyId)
                ->where('m.Sts', 1)
                ->select('m.MappId', 'm.PolicyId', 'm.FieldId', 'm.FOrder', 'f.FiledName')
                ->orderBy('m.FOrder', 'asc')
                ->get();

            $policyTable = "hrm_master_eligibility_policy_tbl{$policyId}";
            $tableExists = DB::connection('hrims')->select("SHOW TABLES LIKE '{$policyTable}'");
            $policyValues = null;
            if (!empty($tableExists)) {
                $policyValues = DB::connection('hrims')->table($policyTable)->where('GradeId', $gradeId)->first();
            }

            $generalFields = [];
            $rateSlabs = [];

            // Categorize fields: General Policy Rules vs Rate Slabs
            $slabFieldIds = [15, 16, 17, 18, 19, 20, 21, 22, 23, 24, 25, 26, 27, 28, 29, 30];

            foreach ($fields as $field) {
                $fieldColumn = 'Fn' . $field->FieldId;
                $fieldValue = isset($policyValues->$fieldColumn) ? $policyValues->$fieldColumn : 'N/A';

                $item = [
                    'field_id' => $field->FieldId,
                    'field_name' => $field->FiledName,
                    'field_value' => $fieldValue,
                ];

                if (in_array($field->FieldId, $slabFieldIds)) {
                    $rateSlabs[] = $item;
                } else {
                    $generalFields[] = $item;
                }
            }

            // Documents check status
            $docs = [
                'RC File' => !empty($employee->rc_file),
                'Insurance' => !empty($employee->insurance),
                'Driving License' => !empty($employee->dl_file),
                'Vehicle Photo' => !empty($employee->vehicle_image),
                'Odometer Photo' => !empty($employee->odo_meter),
                'Purchase Bill' => !empty($employee->bill_file),
            ];

            // Calculate comprehensive vehicle rate and age analysis
            $employee->VehiclePolicy = $employee->PolicyId;
            $employee->grade_id = $employee->GradeId;
            $employee->vehiclevalue = $employee->vehiclevalue ?? null;
            $employee->vehicle_price = $employee->price;
            $employee->ev_vehicle_type = $employee->ev_vehicle_type ?? null;
            $employee->oldnew = $employee->oldnew ?? 'new';
            $rateAnalysis = self::calculateRateAndExplanation($employee, $this->getPolicyTableData($policyId));

            return response()->json([
                'success' => true,
                'employee' => [
                    'name' => $employee->Name,
                    'emp_code' => $employee->EmpCode,
                    'department' => $employee->department_name,
                    'grade' => $employee->GradeName,
                    'policy_name' => $employee->PolicyName,
                    'policy_id' => $employee->PolicyId,
                    'vehicle_type' => ucfirst($employee->VehicleType ?? 'N/A'),
                    'plan' => $employee->Plan ?? 'N/A',
                    'flat_2w' => $employee->flat_2w ?? 'N/A',
                    'flat_4w' => $employee->flat_4w ?? 'N/A',
                    'limit_2w_km' => $employee->limit_2w_km ?? 'N/A',
                    'limit_4w_km' => $employee->limit_4w_km ?? 'N/A',
                    'with_driver' => $employee->WithDriver ?? 'N/A',
                    'advance_com' => $employee->AdvanceCom ?? 'N/A',
                    'date_of_entry' => $employee->DateOfEntryPolicy ? date('d-m-Y', strtotime($employee->DateOfEntryPolicy)) : 'N/A',
                    'reg_no' => $employee->registration_no ?? 'N/A',
                    'brand' => $employee->brand ?? 'N/A',
                    'model' => $employee->model_name ?? 'N/A',
                    'model_no' => $employee->model_no ?? 'N/A',
                    'bill_no' => $employee->bill_no ?? 'N/A',
                    'fuel_type' => $employee->fuel_type ? ucfirst($employee->fuel_type) : 'N/A',
                    'vehiclevalue' => $employee->vehiclevalue ? '₹' . number_format((float)$employee->vehiclevalue) : 'N/A',
                    'price' => $employee->price ? '₹' . number_format((float)$employee->price) : 'N/A',
                    'purchase_date' => $employee->purchase_date ? date('d-m-Y', strtotime($employee->purchase_date)) : 'N/A',
                    'registration_date' => $employee->registration_date ? date('d-m-Y', strtotime($employee->registration_date)) : 'N/A',
                ],
                'rate_analysis' => $rateAnalysis,
                'general_fields' => $generalFields,
                'rate_slabs' => $rateSlabs,
                'documents' => $docs,
            ]);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    public function getVehicleDocuments(Request $request)
    {
        try {
            $employeeId = $request->empid;
            $vehicleId = $request->vehicleid ?? $request->vehicle_id;
            $companyId = session('company_id');

            if (empty($employeeId) && !empty($vehicleId)) {
                $vRec = DB::connection('hrims')->table('hrm_employee_vehicle')->where('id', $vehicleId)->first();
                if ($vRec) {
                    $employeeId = $vRec->EmployeeID;
                }
            }

            $query = DB::connection('hrims')->table('hrm_employee_vehicle as hev')
                ->leftJoin('hrm_employee as he', 'he.EmployeeID', '=', 'hev.EmployeeID')
                ->where('he.CompanyId', $companyId)
                ->where('hev.status', 'A');

            if (!empty($employeeId)) {
                $query->where('hev.EmployeeID', $employeeId);
            }
            if (!empty($vehicleId)) {
                $query->where('hev.id', $vehicleId);
            }

            $vehicle = $query->select(
                    'hev.id as vehicle_id',
                    'hev.vehicle_image',
                    'hev.rc_file',
                    'hev.insurance',
                    'hev.current_odo_meter',
                    'hev.odo_meter',
                    'hev.dl_file',
                    'hev.bill_file',
                    'he.EmpCode',
                    DB::raw("CONCAT(he.Fname, ' ', COALESCE(he.Sname, ''), ' ', COALESCE(he.Lname, '')) AS EmpName")
                )->first();

            if (!$vehicle) {
                return response()->json(['success' => false, 'message' => 'No vehicle found for this employee']);
            }

            $attachmentTypes = [
                'vehicle_image' => 'Vehicle Image',
                'rc_file' => 'RC File',
                'insurance' => 'Insurance File',
                'odo_meter' => 'Odometer File',
                'dl_file' => 'DL File',
                'bill_file' => 'Bill File'
            ];

            $availableAttachments = [];
            $notAvailableAttachments = [];
            foreach ($attachmentTypes as $field => $label) {
                $filePath = $vehicle->$field;
                if ($filePath) {
                    $s3Url = $this->getS3FileUrl($companyId, $filePath);
                    $ext = strtolower(pathinfo($filePath, PATHINFO_EXTENSION));
                    $isImage = in_array($ext, ['jpg', 'jpeg', 'png', 'gif', 'bmp', 'webp']);
                    $availableAttachments[] = ['label' => $label, 'url' => $s3Url, 'is_image' => $isImage, 'field' => $field];
                } else {
                    $notAvailableAttachments[] = $label;
                }
            }

            return response()->json([
                'success' => true,
                'employee' => [
                    'emp_code' => $vehicle->EmpCode,
                    'emp_name' => $vehicle->EmpName,
                    'current_odo_meter' => $vehicle->current_odo_meter
                ],
                'available' => $availableAttachments,
                'not_available' => $notAvailableAttachments
            ]);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    private function getS3FileUrl($companyId, $filePath)
    {
        if (empty($filePath)) {
            return false;
        }
        try {
            $bucket = env('HRIMS_AWS_BUCKET');
            $region = env('HRIMS_AWS_DEFAULT_REGION', 'ap-south-1');
            $s3Key = "Employee_Vehicle/{$companyId}/{$filePath}";
            return "https://{$bucket}.s3.{$region}.amazonaws.com/{$s3Key}";
        } catch (\Exception $e) {
            return false;
        }
    }
}
