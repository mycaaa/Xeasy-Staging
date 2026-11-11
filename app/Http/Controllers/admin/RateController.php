<?php

namespace App\Http\Controllers\admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;


class RateController extends Controller
{
    // Constants for vehicle types
    const VEHICLE_TYPE_TWO_WHEELER = 2;
    const VEHICLE_TYPE_FOUR_WHEELER = 4;
    // Constants for policy calculation periods
    const POLICY_CUTOFF_DATE = '2025-08-01';
    const OLD_POLICY_CUTOFF_DATE = '2025-03-31';
    // Constants for vehicle age thresholds
    const NEW_VEHICLE_AGE_THRESHOLD = 3;
    const OLD_VEHICLE_AGE_THRESHOLD = 2;
    const DRIVER_NEW_VEHICLE_MAX_AGE = 7;
    const DRIVER_OLD_VEHICLE_MAX_AGE = 5;
    /**
     * Main method to get rate per kilometer
     *
     * @param int $employeeId - Employee ID
     * @param string $billDate - Bill date for rate calculation
     * @param int $wheelType - Vehicle wheel type (2 or 4)
     * @param int $vehiclePolicy - Optional vehicle policy override
     * @return float - Calculated rate per kilometer
     */
    /** RATE-04: the month's self-declaration "with extensive running assistance" (driver), 'Y'/'N' or null = not declared */
    private $withDriver = null;
    /** RATE-04: last computed details for the screen (lifetime km under the policy, over the annual limit) */
    public $lastRateInfo = [];

    public function getRatePerKM($employeeId, $billDate, $wheelType, $vehiclePolicy = 0, $withDriver = null)
    {
        $this->withDriver = $withDriver;
        $this->lastRateInfo = [];
        // Step 1: Fetch employee policy data
        $policyData = $this->fetchEmployeePolicy($employeeId, $billDate);
        if (!$policyData) {
            return 0;
        }
        // Step 2: Fetch employee vehicle data (latest from hrims.hrm_employee_vehicle in DESC order)
        $vehicleData = $this->fetchEmployeeVehicle($policyData->EmpCode, $employeeId);
        // Step 3: Calculate vehicle age
        $vehicleAge = $this->calculateVehicleAge($vehicleData);
        // Step 4: Determine which policy to use
        $activePolicy = $this->determineActivePolicy($policyData, $vehiclePolicy, $billDate);
        // OWN-CAR-VALUE-01 (HR 11 Nov 2026): Own Your Car four-wheeler km need the vehicle value in HRIMS; without it
        // the rate is 0 (not allowed), except for the employees HR exempted for now
        if ((int) $activePolicy === 12 && (int) $wheelType === self::VEHICLE_TYPE_FOUR_WHEELER && (float) ($vehicleData['VehicleValue'] ?? 0) <= 0
            && config('claim_rules.own_car_value.required', true)
            && ! in_array((int) $employeeId, (array) config('claim_rules.own_car_value.exempt', []), true)) {
            $this->lastRateInfo['blocked'] = 'Own Your Car: vehicle value missing in HRIMS';

            return 0;
        }
        // Step 5: Get rate configuration from policy
        $rateConfig = $this->fetchPolicyRateConfiguration($activePolicy, $policyData->GradeId, $vehicleData, $vehicleAge, $wheelType);
        // Step 6: Calculate yearly and monthly kilometers
        $kilometerStats = $this->calculateKilometerStatistics($employeeId, $billDate, $vehicleData['PolicyEntryDate'], session('year_id'));
        // RATE-04: km claimed under the Own Your Car policy since the policy entry date, all years
        $kilometerStats['LifetimeRunKm'] = ((int) $activePolicy === 12 && (int) $wheelType === self::VEHICLE_TYPE_FOUR_WHEELER)
            ? $this->lifetimePolicyKm($employeeId, $billDate, $vehicleData['PolicyEntryDate'])
            : 0;
        // Step 7: Calculate final rate based on all parameters
        $finalRate = $this->calculateFinalRate($activePolicy, $rateConfig, $kilometerStats, $wheelType, $vehicleAge, $vehicleData, $policyData, $billDate);
        return $finalRate;
    }
    /**
     * Fetch employee policy and eligibility data
     *
     * @param int $employeeId
     * @param string $billDate
     * @return object|null
     */
    private function fetchEmployeePolicy($employeeId, $billDate)
    {
        $query = DB::connection('hrims')->table('hrm_employee_eligibility as el')->leftJoin('hrm_employee_general as g', 'el.EmployeeID', '=', 'g.EmployeeID')->leftJoin('hrm_employee as e', 'el.EmployeeID', '=', 'e.EmployeeID')->select('el.Travel_TwoWeeKM', 'el.Travel_FourWeeKM', 'el.old_Travel_FourWeeKM', 'el.VehiclePolicy', 'el.old_VehiclePolicy', 'e.EmployeeID', 'e.EmpCode', 'g.GradeId')->where('el.EmployeeID', $employeeId);
        // Determine which policy to fetch based on bill date
        if (strtotime($billDate) < strtotime(self::POLICY_CUTOFF_DATE)) {
            $query->where('el.EligCreatedDate', '<', self::POLICY_CUTOFF_DATE)->orderBy('el.EligibilityId', 'DESC')->limit(1);
        } else {
            // RATE-EFF-01: the eligibility row in force on the bill date (a mid-month change applies
            // from its effective date); the active row when none is in force
            $inForce = config('claim_rules.rate_by_effective_date', true)
                ? \App\Services\Rates\EligibilityEffectiveDate::rowFor('hrims', (string) (session('expense_connection') ?: 'expense'), (int) $employeeId, (string) $billDate)
                : null;
            if ($inForce) {
                $row = (clone $query)->where('el.EligibilityId', $inForce)->first();
                if ($row) {
                    $this->lastRateInfo['eligibility_id'] = $inForce;

                    return $row;
                }
            }
            $query->where('el.Status', 'A');
        }
        return $query->first();
    }
    /**
     * Fetch employee vehicle information
     *
     * @param string $empCode
     * @param int|null $employeeId
     * @return array
     */
    private function fetchEmployeeVehicle($empCode, $employeeId = null)
    {
        $vehicle = null;
        try {
            $query = DB::connection('hrims')->table('hrm_employee_vehicle')
                ->where('status', 'A');

            if ($employeeId && $empCode) {
                $query->where(function ($q) use ($employeeId, $empCode) {
                    $q->where('EmployeeID', $employeeId)->orWhere('EmpCode', $empCode);
                });
            } elseif ($employeeId) {
                $query->where('EmployeeID', $employeeId);
            } elseif ($empCode) {
                $query->where('EmpCode', $empCode);
            }

            $vehicle = $query->orderBy('id', 'DESC')->first();
        } catch (\Exception $e) {
            // In case table or connection error occurs, fallback
        }

        if ($vehicle) {
            $regDate = $vehicle->registration_date ?? '';
            $purchaseDate = $vehicle->purchase_date ?? '';
            $vehicleValue = (float) (($vehicle->vehiclevalue ?? 0) > 0 ? $vehicle->vehiclevalue : ($vehicle->price ?? 0));
            $policyEntryDate = $vehicle->policyentrydate ?? '';
            $oldNew = strtolower($vehicle->oldnew ?? '');

            return [
                'OldNew' => $oldNew,
                'RegDate' => $regDate,
                'PurchaseDate' => $purchaseDate,
                'VehicleValue' => $vehicleValue,
                'PolicyEntryDate' => $policyEntryDate,
                'exists' => true,
            ];
        }

        // Fallback to legacy expense.employee_vehicle table in descending order
        $legacyVehicle = DB::connection(session('expense_connection'))->table('employee_vehicle')
            ->where('EmpCode', $empCode)
            ->where('Status', 'A')
            ->orderBy('id', 'DESC')
            ->first();

        if (!$legacyVehicle) {
            return ['OldNew' => '', 'RegDate' => '', 'PurchaseDate' => '', 'VehicleValue' => 0, 'PolicyEntryDate' => '', 'exists' => false];
        }

        return [
            'OldNew' => strtolower($legacyVehicle->OldNew ?? ''),
            'RegDate' => $legacyVehicle->RegDate ?? '',
            'PurchaseDate' => $legacyVehicle->PurchaseDate ?? '',
            'VehicleValue' => (float) ($legacyVehicle->VehicleValue ?? 0),
            'PolicyEntryDate' => $legacyVehicle->PolicyEntryDate ?? '',
            'exists' => true,
        ];
    }
    /**
     * Calculate vehicle age based on registration or purchase date
     *
     * @param array $vehicleData
     * @return float
     */
    private function calculateVehicleAge($vehicleData)
    {
        $currentDate = Carbon::now()->format('Y.m');
        // Check registration date first
        if ($this->isValidDate($vehicleData['RegDate'])) {
            $vehicleDate = Carbon::parse($vehicleData['RegDate'])->format('Y.m');
            return $currentDate - $vehicleDate;
        }
        // Fallback to purchase date
        if ($this->isValidDate($vehicleData['PurchaseDate'])) {
            $vehicleDate = Carbon::parse($vehicleData['PurchaseDate'])->format('Y.m');
            return $currentDate - $vehicleDate;
        }
        return 0;
    }
    /**
     * Check if date is valid (not null, not default values)
     *
     * @param string|null $date
     * @return bool
     */
    private function isValidDate($date)
    {
        if (!$date) return false;
        $invalidDates = ['0000-00-00', '1970-01-01'];
        return !in_array($date, $invalidDates);
    }
    /**
     * Determine which vehicle policy should be active
     *
     * @param object $policyData
     * @param int $vehiclePolicy
     * @param string $billDate
     * @return int
     */
    private function determineActivePolicy($policyData, $vehiclePolicy, $billDate)
    {
        // If policy is explicitly provided and valid, use it
        if ($vehiclePolicy > 0) {
            return $vehiclePolicy;
        }
        // Use old policy for dates before cutoff
        if ($billDate <= self::OLD_POLICY_CUTOFF_DATE) {
            $val = $policyData->old_VehiclePolicy ?? 0;
            return is_numeric($val) ? (int)$val : 0;
        }
        // Use current policy
        $val = $policyData->VehiclePolicy ?? 0;
        return is_numeric($val) ? (int)$val : 0;
    }
    /**
     * Fetch rate configuration from policy table
     *
     * @param int $policyId
     * @param int $gradeId
     * @param array $vehicleData
     * @param float $vehicleAge
     * @param int $wheelType
     * @return array
     */
    private function fetchPolicyRateConfiguration($policyId, $gradeId, $vehicleData, $vehicleAge, $wheelType)
    {
        // Initialize default rate configuration
        $rateConfig = ['Wise' => '', 'MinKm' => 0, 'MaxKm' => 0, 'Rate_AfterMinKm' => 0, 'Rate_AfterMaxKm' => 0, 'Rate' => 0, 'Rate_VDriv' => 0, 'ReduceRate' => 0, 'ReduceRate_VDriv' => 0, 'tpkm' => 0, 'fpkm' => 0,];
        // If no policy or non-numeric/invalid value, return defaults
        if (!is_numeric($policyId) || (int)$policyId <= 0) {
            return $rateConfig;
        }
        $policyId = (int)$policyId;
        // Fetch policy rate data
        $policyRate = DB::connection('hrims')->table("hrm_master_eligibility_policy_tbl{$policyId}")->where('GradeId', $gradeId)->first();
        if (!$policyRate) {
            return $rateConfig;
        }
        // Check if grade needs to be downgraded based on vehicle value
        $policyRate = $this->adjustGradeBasedOnVehicleValue($policyId, $gradeId, $vehicleData['VehicleValue'], $policyRate);
        // Configure rates based on policy type
        $this->configureRatesByPolicyType($policyId, $policyRate, $vehicleData['OldNew'], $vehicleAge, $wheelType, $rateConfig);
        return $rateConfig;
    }
    /**
     * Adjust grade if vehicle value is below eligibility threshold
     *
     * @param int $policyId
     * @param int $gradeId
     * @param float $vehicleValue
     * @param object $policyRate
     * @return object
     */
    private function adjustGradeBasedOnVehicleValue($policyId, $gradeId, $vehicleValue, $policyRate)
    {
        // Only certain policies check vehicle value
        $policiesWithValueCheck = [1, 2, 4, 5, 6, 7, 11, 12, 13];
        if (!in_array($policyId, $policiesWithValueCheck)) {
            return $policyRate;
        }
        // Extract eligible vehicle value
        $eligibleValue = $this->extractNumericValue($policyRate->Fn2 ?? 0);
        // If vehicle value is below threshold, use previous grade.
        // RATE-03: the Vehicle Policy (Own Your Car) allows a difference of up to Rs 50,000 below the
        // grade's eligible car value; only a car below (limit - allowance) drops to the lower grade.
        $allowance = (float) config('claim_rules.vehicle_value_allowance', 50000);
        if ($eligibleValue > 0 && $vehicleValue > 0 && ($vehicleValue + $allowance) < $eligibleValue) {
            $lowerGrade = DB::connection('hrims')->table("hrm_master_eligibility_policy_tbl{$policyId}")->where('GradeId', $gradeId - 1)->first();
            return $lowerGrade ?? $policyRate;
        }
        return $policyRate;
    }
    /**
     * Configure rates based on policy type
     *
     * @param int $policyId
     * @param object $policyRate
     * @param string $oldNew
     * @param float $vehicleAge
     * @param int $wheelType
     * @param array &$rateConfig (passed by reference)
     */
    private function configureRatesByPolicyType($policyId, $policyRate, $oldNew, $vehicleAge, $wheelType, &$rateConfig)
    {
        // Monthly calculation policies
        $monthlyPolicies = [3, 4, 6, 8, 9, 10, 11, 13];
        // Yearly calculation policies
        $yearlyPolicies = [1, 2, 5, 7, 12];
        if (in_array($policyId, $monthlyPolicies)) {
            $this->configureMonthlyPolicyRates($policyId, $policyRate, $wheelType, $rateConfig);
        } elseif (in_array($policyId, $yearlyPolicies)) {
            $this->configureYearlyPolicyRates($policyId, $policyRate, $oldNew, $vehicleAge, $rateConfig);
        }
        // Set vehicle type specific rates
        $this->setVehicleTypeRates($wheelType, $rateConfig);
    }
    /**
     * Configure rates for monthly calculation policies
     *
     * @param int $policyId
     * @param object $policyRate
     * @param int $wheelType
     * @param array &$rateConfig
     */
    private function configureMonthlyPolicyRates($policyId, $policyRate, $wheelType, &$rateConfig)
    {
        $rateConfig['Wise'] = 'M'; // Monthly calculation
        // Base rate
        $rateConfig['Rate'] = $this->extractNumericValue($policyRate->Fn6 ?? 0);
        $rateConfig['Rate_AfterMaxKm'] = $this->extractNumericValue($policyRate->Fn12 ?? 0);
        // Policies 4 and 11 have different kilometer limit fields
        if (in_array($policyId, [4, 11])) {
            $rateConfig['MaxKm'] = $this->extractNumericValue($policyRate->Fn8 ?? ($policyRate->Fn32 ?? 0));
        } else {
            $rateConfig['MinKm'] = $this->extractNumericValue($policyRate->Fn7 ?? 0);
            $rateConfig['MaxKm'] = $this->extractNumericValue($policyRate->Fn9 ?? 0);
            $rateConfig['Rate_AfterMinKm'] = $this->extractNumericValue($policyRate->Fn11 ?? 0);
        }
    }
    /**
     * Configure rates for yearly calculation policies
     *
     * @param int $policyId
     * @param object $policyRate
     * @param string $oldNew
     * @param float $vehicleAge
     * @param array &$rateConfig
     */
    private function configureYearlyPolicyRates($policyId, $policyRate, $oldNew, $vehicleAge, &$rateConfig)
    {
        $rateConfig['Wise'] = 'Y'; // Yearly calculation
        // Policy 12 has different field structure
        if ($policyId == 12) {
            $rateConfig['Rate'] = $this->extractNumericValue($policyRate->Fn6 ?? 0);
            $rateConfig['ReduceRate'] = $this->extractNumericValue($policyRate->Fn37 ?? 0);
            $rateConfig['Rate_VDriv'] = $this->extractNumericValue($policyRate->Fn34 ?? 0);
            $rateConfig['ReduceRate_VDriv'] = $this->extractNumericValue($policyRate->Fn38 ?? 0);
            $rateConfig['MaxKm'] = $this->extractNumericValue($policyRate->Fn32 ?? 0);
            $rateConfig['Rate_AfterMaxKm'] = $this->extractNumericValue($policyRate->Fn37 ?? 0);
        } else {
            // Policies 1, 2, 5, 7 have vehicle age-based rates
            $this->setAgeBasedRates($oldNew, $vehicleAge, $policyRate, $rateConfig);
            // Kilometer limits
            $rateConfig['MaxKm'] = $this->extractNumericValue($policyRate->Fn8 ?? ($policyRate->Fn9 ?? 0));
            $rateConfig['Rate_AfterMinKm'] = $this->extractNumericValue($policyRate->Fn11 ?? 0);
            $rateConfig['Rate_AfterMaxKm'] = $this->extractNumericValue($policyRate->Fn12 ?? 0);
        }
    }
    /**
     * Set rates based on vehicle age (new/old)
     *
     * @param string $oldNew
     * @param float $vehicleAge
     * @param object $policyRate
     * @param array &$rateConfig
     */
    private function setAgeBasedRates($oldNew, $vehicleAge, $policyRate, &$rateConfig)
    {
        if ($oldNew == 'old') {
            // Old vehicle rates
            if ($vehicleAge <= self::OLD_VEHICLE_AGE_THRESHOLD) {
                $rateConfig['Rate'] = $this->extractNumericValue($policyRate->Fn19 ?? 0);
                $rateConfig['ReduceRate'] = $this->extractNumericValue($policyRate->Fn27 ?? 0);
                $rateConfig['Rate_VDriv'] = $this->extractNumericValue($policyRate->Fn21 ?? 0);
                $rateConfig['ReduceRate_VDriv'] = $this->extractNumericValue($policyRate->Fn29 ?? 0);
            } else {
                $rateConfig['Rate'] = $this->extractNumericValue($policyRate->Fn20 ?? 0);
                $rateConfig['ReduceRate'] = $this->extractNumericValue($policyRate->Fn28 ?? 0);
                $rateConfig['Rate_VDriv'] = $this->extractNumericValue($policyRate->Fn22 ?? 0);
                $rateConfig['ReduceRate_VDriv'] = $this->extractNumericValue($policyRate->Fn30 ?? 0);
            }
        } elseif ($oldNew == 'new') {
            // New vehicle rates
            if ($vehicleAge <= self::NEW_VEHICLE_AGE_THRESHOLD) {
                $rateConfig['Rate'] = $this->extractNumericValue($policyRate->Fn15 ?? 0);
                $rateConfig['ReduceRate'] = $this->extractNumericValue($policyRate->Fn23 ?? 0);
                $rateConfig['Rate_VDriv'] = $this->extractNumericValue($policyRate->Fn17 ?? 0);
                $rateConfig['ReduceRate_VDriv'] = $this->extractNumericValue($policyRate->Fn25 ?? 0);
            } else {
                $rateConfig['Rate'] = $this->extractNumericValue($policyRate->Fn16 ?? 0);
                $rateConfig['ReduceRate'] = $this->extractNumericValue($policyRate->Fn24 ?? 0);
                $rateConfig['Rate_VDriv'] = $this->extractNumericValue($policyRate->Fn18 ?? 0);
                $rateConfig['ReduceRate_VDriv'] = $this->extractNumericValue($policyRate->Fn26 ?? 0);
            }
        }
    }
    /**
     * Set vehicle type specific rates (2-wheeler vs 4-wheeler)
     *
     * @param int $wheelType
     * @param array &$rateConfig
     */
    private function setVehicleTypeRates($wheelType, &$rateConfig)
    {
        if ($wheelType == self::VEHICLE_TYPE_TWO_WHEELER) {
            $rateConfig['tpkm'] = $rateConfig['Rate'];
        } elseif ($wheelType == self::VEHICLE_TYPE_FOUR_WHEELER) {
            $rateConfig['fpkm'] = $rateConfig['Rate'];
        }
    }
    /**
     * Calculate yearly and monthly kilometer statistics
     *
     * @param int $employeeId
     * @param string $billDate
     * @param string $policyEntryDate
     * @param int $yearId
     * @return array
     */
    private function calculateKilometerStatistics($employeeId, $billDate, $policyEntryDate, $yearId)
    {
        $stats = ['Year2RunKm' => 0, 'Year4RunKm' => 0, 'monthly' => $this->initializeMonthlyKilometers()];
        // Fetch all claims for the year
        $claims = DB::connection(session('expense_connection'))->table("y{$yearId}_expenseclaims")->selectRaw('SUM(TotKm) as TotalKm, ClaimMonth, WType as VehicleType')->where('ClaimId', 7)->where('BillDate', '!=', $billDate)->whereNotIn('ClaimStatus', ['Deactivate', 'Draft'])->where('CrBy', $employeeId)->where('TotKm', '>=', 0)->groupBy('WType', 'ClaimMonth')->get();
        // Process each claim
        foreach ($claims as $claim) {
            $this->processClaimKilometers($claim, $policyEntryDate, $stats);
        }
        return $stats;
    }
    /**
     * Initialize monthly kilometer tracking array
     *
     * @return array
     */
    private function initializeMonthlyKilometers()
    {
        return ['apr2' => 0, 'may2' => 0, 'jun2' => 0, 'jul2' => 0, 'aug2' => 0, 'sep2' => 0, 'oct2' => 0, 'nov2' => 0, 'dec2' => 0, 'jan2' => 0, 'feb2' => 0, 'mar2' => 0, 'apr4' => 0, 'may4' => 0, 'jun4' => 0, 'jul4' => 0, 'aug4' => 0, 'sep4' => 0, 'oct4' => 0, 'nov4' => 0, 'dec4' => 0, 'jan4' => 0, 'feb4' => 0, 'mar4' => 0,];
    }
    /**
     * Process individual claim kilometers
     *
     * @param object $claim
     * @param string $policyEntryDate
     * @param array &$stats
     */
    private function processClaimKilometers($claim, $policyEntryDate, &$stats)
    {
        $monthMap = [1 => 'jan', 2 => 'feb', 3 => 'mar', 4 => 'apr', 5 => 'may', 6 => 'jun', 7 => 'jul', 8 => 'aug', 9 => 'sep', 10 => 'oct', 11 => 'nov', 12 => 'dec'];
        $monthKey = $monthMap[$claim->ClaimMonth] ?? '';
        if ($claim->VehicleType == self::VEHICLE_TYPE_TWO_WHEELER) {
            $stats['Year2RunKm'] += $claim->TotalKm;
            $stats['monthly'][$monthKey . '2'] = $claim->TotalKm;
        } elseif ($claim->VehicleType == self::VEHICLE_TYPE_FOUR_WHEELER) {
            // Check if policy entry date allows this month's kilometers
            if ($this->isMonthEligibleForFourWheeler($claim->ClaimMonth, $policyEntryDate)) {
                $stats['monthly'][$monthKey . '4'] = $claim->TotalKm;
                $stats['Year4RunKm'] += $claim->TotalKm;
            }
        }
    }
    /**
     * Check if month is eligible for 4-wheeler calculation based on policy entry date
     *
     * @param int $claimMonth
     * @param string $policyEntryDate
     * @return bool
     */
    private function isMonthEligibleForFourWheeler($claimMonth, $policyEntryDate)
    {
        if (!$policyEntryDate) {
            return true;
        }
        $yearStart = Carbon::parse(date('Y') . '-04-01');
        // If policy started before financial year, all months are eligible
        if (Carbon::parse($policyEntryDate)->lte($yearStart)) {
            return true;
        }
        // Check if policy entry date is before month end
        $monthEndDates = [4 => '04-30', 5 => '05-31', 6 => '06-30', 7 => '07-31', 8 => '08-31', 9 => '09-30', 10 => '10-31', 11 => '11-30', 12 => '12-31', 1 => '01-01', 2 => '02-29', 3 => '03-31'];
        $monthEndDate = Carbon::parse(date('Y') . '-' . $monthEndDates[$claimMonth]);
        return Carbon::parse($policyEntryDate)->lte($monthEndDate);
    }
    /**
     * Calculate final rate based on all parameters
     *
     * @param int $policyId
     * @param array $rateConfig
     * @param array $kilometerStats
     * @param int $wheelType
     * @param float $vehicleAge
     * @param array $vehicleData
     * @param object $policyData
     * @return float
     */
    private function calculateFinalRate($policyId, $rateConfig, $kilometerStats, $wheelType, $vehicleAge, $vehicleData, $policyData, $billDate = null)
    {
        $this->lastRateInfo['lifetime_km'] = (float) ($kilometerStats['LifetimeRunKm'] ?? 0);
        // If no policy or invalid value, return base rate
        if (!is_numeric($policyId) || (int)$policyId <= 0) {
            return $this->getBaseRate($policyData, $wheelType);
        }
        $policyId = (int)$policyId;
        // Get current month's kilometer breakdown based on bill date
        $monthlyKms = $this->getCurrentMonthKilometers($kilometerStats, $billDate);
        // Calculate rate based on vehicle type
        $rate = 0.0;
        // RND-2W-01 (HR decision 7 Nov 2026): R&D two-wheeler policy (J3-M3, was Rs 6.75/km up to 400 km
        // then Rs 1.95/km) is a flat Rs 3.50/km for every km, for bills from the effective date
        $flat = (array) config('claim_rules.rnd_2w_flat', []);
        if ($wheelType == self::VEHICLE_TYPE_TWO_WHEELER && in_array($policyId, (array) ($flat['policies'] ?? []), true)
            && $billDate && date('Y-m-d', strtotime($billDate)) >= (string) ($flat['from'] ?? '9999-12-31')) {
            $this->lastRateInfo['rule'] = 'rnd_2w_flat';

            return (float) ($flat['rate'] ?? 3.5);
        }
        if ($wheelType == self::VEHICLE_TYPE_TWO_WHEELER) {
            $rate = $this->calculateTwoWheelerRate($policyId, $rateConfig, $monthlyKms);
        } elseif ($wheelType == self::VEHICLE_TYPE_FOUR_WHEELER) {
            $rate = $this->calculateFourWheelerRate($policyId, $rateConfig, $kilometerStats, $monthlyKms, $vehicleAge, $vehicleData['OldNew']);
        } else {
            $rate = (float) ($rateConfig['Rate'] ?? 0);
        }

        // If policy calculation returned 0 or no rate was found, fallback to employee's flat base rate from eligibility (Travel_TwoWeeKM / Travel_FourWeeKM)
        if ($rate <= 0) {
            $baseRate = $this->getBaseRate($policyData, $wheelType);
            if ($baseRate > 0) {
                return $baseRate;
            }
        }

        return $rate;
    }
    /**
     * Get base rate when no policy is defined
     *
     * @param object $policyData
     * @param int $wheelType
     * @return float
     */
    private function getBaseRate($policyData, $wheelType)
    {
        if ($wheelType == self::VEHICLE_TYPE_TWO_WHEELER) {
            return $this->extractNumericValue($policyData->Travel_TwoWeeKM ?? 0);
        } elseif ($wheelType == self::VEHICLE_TYPE_FOUR_WHEELER) {
            return $this->extractNumericValue($policyData->Travel_FourWeeKM ?? 0);
        }
        return 0;
    }
    /**
     * Get current month and previous 3 months kilometer data
     *
     * @param array $kilometerStats
     * @param string|null $billDate
     * @return array
     */
    private function getCurrentMonthKilometers($kilometerStats, $billDate = null)
    {
        $currentMonth = $billDate ? (int)date('m', strtotime($billDate)) : (int)date('m');
        if ($currentMonth < 1 || $currentMonth > 12) {
            $currentMonth = (int)date('m');
        }
        // Map to get previous 4 months (including current)
        $monthSequence = [4 => ['apr', 'apr', 0, 0, 0], 5 => ['may', 'apr', 0, 0], 6 => ['jun', 'may', 'apr', 0], 7 => ['jul', 'jun', 'may', 'apr'], 8 => ['aug', 'jul', 'jun', 'may'], 9 => ['sep', 'aug', 'jul', 'jun'], 10 => ['oct', 'sep', 'aug', 'jul'], 11 => ['nov', 'oct', 'sep', 'aug'], 12 => ['dec', 'nov', 'oct', 'sep'], 1 => ['jan', 'dec', 'nov', 'oct'], 2 => ['feb', 'jan', 'dec', 'nov'], 3 => ['mar', 'feb', 'jan', 'dec'],];
        $months = $monthSequence[$currentMonth] ?? ['', '', '', ''];
        return ['n2_1' => $kilometerStats['monthly'][($months[0] ?? '') . '2'] ?? 0, 'n2_2' => $kilometerStats['monthly'][($months[1] ?? '') . '2'] ?? 0, 'n2_3' => $kilometerStats['monthly'][($months[2] ?? '') . '2'] ?? 0, 'n2_4' => $kilometerStats['monthly'][($months[3] ?? '') . '2'] ?? 0, 'n4_1' => $kilometerStats['monthly'][($months[0] ?? '') . '4'] ?? 0, 'n4_2' => $kilometerStats['monthly'][($months[1] ?? '') . '4'] ?? 0, 'n4_3' => $kilometerStats['monthly'][($months[2] ?? '') . '4'] ?? 0, 'n4_4' => $kilometerStats['monthly'][($months[3] ?? '') . '4'] ?? 0,];
    }
    /**
     * Calculate rate for 2-wheeler based on policy rules
     *
     * @param int $policyId
     * @param array $rateConfig
     * @param array $monthlyKms
     * @return float
     */
    private function calculateTwoWheelerRate($policyId, $rateConfig, $monthlyKms)
    {
        $currentMonthKm = $monthlyKms['n2_1'];
        // Policy 10: Fixed rate
        if ($policyId == 10) {
            return $rateConfig['tpkm'];
        }
        // Policies 8, 9: Check max km
        if (in_array($policyId, [8, 9]) && $currentMonthKm <= $rateConfig['MaxKm']) {
            return $rateConfig['tpkm'];
        }
        // Policy 3: Has min and max km limits
        if ($policyId == 3) {
            if ($currentMonthKm <= $rateConfig['MinKm']) {
                return $rateConfig['tpkm'];
            }
            if ($currentMonthKm > $rateConfig['MinKm'] && $currentMonthKm <= $rateConfig['MaxKm']) {
                return $rateConfig['Rate_AfterMinKm'];
            }
        }
        // Check rolling 4-month average for policies 3, 8, 9
        if (in_array($policyId, [3, 8, 9]) && $currentMonthKm > $rateConfig['MaxKm']) {
            $rolling2Month = $monthlyKms['n2_2'] + $monthlyKms['n2_1'];
            $rolling3Month = $monthlyKms['n2_3'] + $monthlyKms['n2_2'] + $monthlyKms['n2_1'];
            $rolling4Month = $monthlyKms['n2_4'] + $monthlyKms['n2_3'] + $monthlyKms['n2_2'] + $monthlyKms['n2_1'];
            // If any rolling average is below max, use base rate
            if ($rolling2Month < $rateConfig['MaxKm'] * 2 || $rolling3Month < $rateConfig['MaxKm'] * 3 || $rolling4Month < $rateConfig['MaxKm'] * 4) {
                return $rateConfig['tpkm'];
            }
            // Otherwise use reduced rate (policies 8, 9 keep base rate)
            $afterMaxRate = in_array($policyId, [8, 9]) ? $rateConfig['tpkm'] : $rateConfig['Rate_AfterMaxKm'];
            return $afterMaxRate > 0 ? $afterMaxRate : $rateConfig['tpkm'];
        }
        return $rateConfig['tpkm'];
    }
    /**
     * Calculate rate for 4-wheeler based on policy rules
     *
     * @param int $policyId
     * @param array $rateConfig
     * @param array $kilometerStats
     * @param array $monthlyKms
     * @param float $vehicleAge
     * @param string $oldNew
     * @return float
     */
    private function calculateFourWheelerRate($policyId, $rateConfig, $kilometerStats, $monthlyKms, $vehicleAge, $oldNew)
    {
        $yearlyMaxKm = in_array($policyId, [4, 12]) ? $rateConfig['MaxKm'] : $rateConfig['MaxKm'] * 12;
        $yearlyRunKm = $kilometerStats['Year4RunKm'];
        $currentMonthKm = $monthlyKms['n4_1'];
        // Policy 4: Yearly limit based
        if ($policyId == 4) {
            if ($yearlyRunKm <= $yearlyMaxKm) {
                return $rateConfig['fpkm'];
            }
            return $rateConfig['Rate_AfterMaxKm'];
        }
        // Policy 11: Monthly limit with rolling 4-month check
        if ($policyId == 11) {
            if ($currentMonthKm <= $rateConfig['MaxKm']) {
                return $rateConfig['fpkm'];
            }
            // Check rolling 4-month average
            $rolling2Month = $monthlyKms['n4_2'] + $monthlyKms['n4_1'];
            $rolling3Month = $monthlyKms['n4_3'] + $monthlyKms['n4_2'] + $monthlyKms['n4_1'];
            $rolling4Month = $monthlyKms['n4_4'] + $monthlyKms['n4_3'] + $monthlyKms['n4_2'] + $monthlyKms['n4_1'];
            if ($rolling2Month < $rateConfig['MaxKm'] * 2 || $rolling3Month < $rateConfig['MaxKm'] * 3 || $rolling4Month < $rateConfig['MaxKm'] * 4) {
                return $rateConfig['fpkm'];
            }
            return $rateConfig['Rate_AfterMaxKm'];
        }
        // Policy 6: Min and Max km limits with rolling check
        if ($policyId == 6) {
            if ($currentMonthKm <= $rateConfig['MinKm']) {
                return $rateConfig['fpkm'];
            }
            if ($currentMonthKm > $rateConfig['MinKm'] && $currentMonthKm <= $rateConfig['MaxKm']) {
                return $rateConfig['Rate_AfterMinKm'];
            }
            if ($currentMonthKm > $rateConfig['MaxKm']) {
                $rolling2Month = $monthlyKms['n4_2'] + $monthlyKms['n4_1'];
                $rolling3Month = $monthlyKms['n4_3'] + $monthlyKms['n4_2'] + $monthlyKms['n4_1'];
                $rolling4Month = $monthlyKms['n4_4'] + $monthlyKms['n4_3'] + $monthlyKms['n4_2'] + $monthlyKms['n4_1'];
                if ($rolling2Month < $rateConfig['MaxKm'] * 2 || $rolling3Month < $rateConfig['MaxKm'] * 3 || $rolling4Month < $rateConfig['MaxKm'] * 4) {
                    return $rateConfig['fpkm'];
                }
                return $rateConfig['Rate_AfterMaxKm'];
            }
        }
        // Policies 1, 2, 5, 7, 12: Age and driver-based rates
        if (in_array($policyId, [1, 2, 5, 7, 12])) {
            return $this->calculateAgeBasedFourWheelerRate($policyId, $rateConfig, $yearlyRunKm, $yearlyMaxKm, $vehicleAge, $oldNew);
        }
        return $rateConfig['fpkm'];
    }
    /**
     * Calculate age-based rate for 4-wheeler
     *
     * @param int $policyId
     * @param array $rateConfig
     * @param float $yearlyRunKm
     * @param float $yearlyMaxKm
     * @param float $vehicleAge
     * @param string $oldNew
     * @return float
     */
    private function calculateAgeBasedFourWheelerRate($policyId, $rateConfig, $yearlyRunKm, $yearlyMaxKm, $vehicleAge, $oldNew)
    {
        // RATE-04: the monthly self-declaration (With_Driver = 'Y') decides the assistance rate
        $hasDriver = strtoupper((string) $this->withDriver) === 'Y' ? 1 : 0;
        // Policies 1, 2, 5, 7 have complex age-based logic
        if (in_array($policyId, [1, 2, 5, 7])) {
            if ($hasDriver == 1) {
                // With driver rates
                return $this->getDriverBasedRate($oldNew, $vehicleAge, $yearlyRunKm, $yearlyMaxKm, $rateConfig['Rate_VDriv'], $rateConfig['ReduceRate_VDriv']);
            } else {
                // Without driver rates
                return $this->getDriverBasedRate($oldNew, $vehicleAge, $yearlyRunKm, $yearlyMaxKm, $rateConfig['Rate'], $rateConfig['ReduceRate']);
            }
        }
        // Policy 12 (Own Your Car), RATE-04, per the HR manual and the 5 Nov rate walkthrough:
        //  A. grade rate (car value checked earlier, lower grade if the car is below the limit by more than Rs 50,000)
        //  B. with extensive running assistance (driver, monthly self-declaration): the "with assistance" rate
        //     (grade rate + Rs 4 for M4-M5); where the grade has no such rate the plain rate applies
        //  C. once 2,52,000 km have been claimed under the policy: the reduced rate (Rs 11 M4-M5, Rs 10 M1-M3)
        //  Running beyond 36,000 km in a year needs approval; it does not change the rate.
        if ($policyId == 12) {
            $lifetimeKm = (float) ($this->lastRateInfo['lifetime_km'] ?? 0);
            $lifetimeMax = (float) config('claim_rules.oyc_lifetime_km', 252000);
            $this->lastRateInfo['over_annual_limit'] = $yearlyMaxKm > 0 && $yearlyRunKm > $yearlyMaxKm;
            if ($lifetimeMax > 0 && $lifetimeKm >= $lifetimeMax && $rateConfig['ReduceRate'] > 0) {
                $this->lastRateInfo['basis'] = 'reduced_after_lifetime_km';
                return $rateConfig['ReduceRate'];
            }
            if ($hasDriver == 1 && $rateConfig['Rate_VDriv'] > $rateConfig['Rate']) {
                $this->lastRateInfo['basis'] = 'with_assistance';
                return $rateConfig['Rate_VDriv'];
            }
            $this->lastRateInfo['basis'] = 'grade_rate';
            return $rateConfig['Rate'];
        }
        return $rateConfig['fpkm'];
    }
    /**
     * Get rate based on driver availability and vehicle age
     *
     * @param string $oldNew
     * @param float $vehicleAge
     * @param float $yearlyRunKm
     * @param float $yearlyMaxKm
     * @param float $baseRate
     * @param float $reducedRate
     * @return float
     */
    private function getDriverBasedRate($oldNew, $vehicleAge, $yearlyRunKm, $yearlyMaxKm, $baseRate, $reducedRate)
    {
        // Old vehicle logic
        if ($oldNew == 'old') {
            if ($vehicleAge <= self::DRIVER_OLD_VEHICLE_MAX_AGE) {
                return $yearlyRunKm <= $yearlyMaxKm ? $baseRate : $reducedRate;
            }
            return $reducedRate;
        }
        // New vehicle logic
        if ($oldNew == 'new') {
            if ($vehicleAge <= self::DRIVER_NEW_VEHICLE_MAX_AGE) {
                return $yearlyRunKm <= $yearlyMaxKm ? $baseRate : $reducedRate;
            }
            return $reducedRate;
        }
        return $baseRate;
    }
    /**
     * RATE-04: 4-wheeler km claimed (verified claims, not deactivated/draft) from the policy entry date
     * up to, but not including, this bill date, across every financial-year table. The policy caps the
     * car at 2,52,000 claimed km; previous running km of a used car or km before a promotion are not
     * held in Xeasy and must be entered in HRIMS to count.
     */
    private function lifetimePolicyKm($employeeId, $billDate, $policyEntryDate): float
    {
        $from = ($policyEntryDate && ! str_starts_with((string) $policyEntryDate, '0000') && $policyEntryDate !== '1970-01-01') ? $policyEntryDate : '2025-04-01';
        $conn = session('expense_connection') ?: \App\Helpers\DatabaseSwitcher::getExpenseConnection();
        static $cache = [];
        $key = "{$conn}|{$employeeId}|{$from}|{$billDate}";
        if (isset($cache[$key])) {
            return $cache[$key];
        }
        $total = 0.0;
        try {
            $years = DB::connection($conn)->table('financialyear')->where('Todate', '>=', $from)->where('FromDate', '<=', $billDate)->pluck('YearId');
            foreach ($years as $y) {
                $t = "y{$y}_expenseclaims";
                if (! \Illuminate\Support\Facades\Schema::connection($conn)->hasTable($t)) {
                    continue;
                }
                try {
                    $q = DB::connection($conn)->table($t)->where('CrBy', $employeeId)->where('ClaimId', 7)
                        ->whereNotIn('ClaimStatus', ['Deactivate', 'Draft'])->where('BillDate', '>=', $from)->where('BillDate', '<', $billDate);
                    // older year tables have no WType column: count their running-km claims as 4-wheeler only when the
                    // employee's vehicle under the policy is a car (policy 12 is a car policy)
                    if (\Illuminate\Support\Facades\Schema::connection($conn)->hasColumn($t, 'WType')) {
                        $q->where('WType', 4);
                    }
                    $total += (float) $q->sum('TotKm');
                } catch (\Throwable $e) {
                    \Illuminate\Support\Facades\Log::warning("RateController lifetime km {$t}: ".$e->getMessage());
                }
            }
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::warning('RateController lifetime km: '.$e->getMessage());
        }

        return $cache[$key] = $total;
    }

    /**
     * Extract numeric value from string (removes non-numeric characters)
     *
     * @param mixed $value
     * @return float
     */
    private function extractNumericValue($value)
    {
        if (is_numeric($value)) {
            return (float)$value;
        }
        return (float)preg_replace('/[^0-9.]/', '', $value);
    }

    public function calculateRateAjax(Request $request)
    {
        $request->validate([
            'employee_id' => 'required|integer',
            'bill_date' => 'required|date',
            'vehicle_type' => 'required|in:2,4',
        ]);
        $employeeId = $request->input('employee_id');
        $billDate = $request->input('bill_date');
        $vehicleType = (int) $request->input('vehicle_type');
        $rate = $this->getRatePerKM($employeeId, $billDate, $vehicleType);
        return response()->json([
            'success' => true,
            'rate' => $rate,
            'formatted_rate' => number_format($rate, 2),
            'vehicle_type' => $vehicleType,
            'vehicle_type_label' => $vehicleType == self::VEHICLE_TYPE_TWO_WHEELER ? '2W' : '4W',
        ]);
    }
}

