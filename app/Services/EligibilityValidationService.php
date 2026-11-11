<?php

namespace App\Services;

use App\Http\Controllers\admin\RateController;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class EligibilityValidationService
{
    /**
     * Get eligibility validation data for a given expense claim.
     *
     * @param object $expenseDetail The expense claim record
     * @param int $yearId Current year ID
     * @return array Eligibility comparison data
     */
    public function getEligibilityData($expenseDetail, $yearId): array
    {
        $employeeId = $expenseDetail->CrBy;
        $claimId = (int) $expenseDetail->ClaimId;
        $claimedAmount = (float) ($expenseDetail->FilledTAmt ?? 0);
        $billDate = $expenseDetail->BillDate ?? date('Y-m-d');

        // Get employee eligibility record
        $eligibility = $this->getEmployeeEligibility($employeeId);
        if (!$eligibility) {
            return $this->buildResponse($claimId, $claimedAmount, null, 'Eligibility record not found');
        }

        // Get employee grade
        $grade = $this->getEmployeeGrade($employeeId);

        // Get policy name
        $policyName = $this->getPolicyName((int) ($eligibility->VehiclePolicy ?? 0));

        // Build base info
        $baseInfo = [
            'grade' => $grade,
            'policy_id' => (int) ($eligibility->VehiclePolicy ?? 0),
            'policy_name' => $policyName,
        ];

        // Route to claim-type-specific validation
        switch ($claimId) {
            case 1: // Lodging / Hotel
                return $this->validateLodging($expenseDetail, $eligibility, $claimedAmount, $baseInfo, $yearId);

            case 7: // Travel / KM
                return $this->validateTravel($expenseDetail, $eligibility, $claimedAmount, $baseInfo, $billDate, $yearId);

            case 17: // Group Meal
                return $this->validateGroupMeal($eligibility, $claimedAmount, $baseInfo);

            case 19: // DA Inside HQ
                return $this->validateDA($eligibility, $claimedAmount, $baseInfo, 'inside');

            case 20: // DA Outside HQ
                return $this->validateDA($eligibility, $claimedAmount, $baseInfo, 'outside');

            default:
                // Check if there's a generic limit in claimlimits table
                return $this->validateGenericLimit($employeeId, $claimId, $claimedAmount, $baseInfo);
        }
    }

    /**
     * Validate Lodging (ClaimId 1) against city category limits × duration
     */
    private function validateLodging($expenseDetail, $eligibility, float $claimedAmount, array $baseInfo, int $yearId): array
    {
        $expId = $expenseDetail->ExpId;

        // Get city category and duration from fill data
        $fillData = DB::connection(session('expense_connection'))
            ->table("y{$yearId}_g1_expensefilldata")
            ->where('ExpId', $expId)
            ->first(['CityCategory', 'StayDuration', 'Arrival', 'Departure']);

        $cityCategory = $fillData->CityCategory ?? ($expenseDetail->CityCategory ?? '');

        // Calculate duration (days)
        $duration = 1;
        if (!empty($fillData->StayDuration) && is_numeric($fillData->StayDuration) && $fillData->StayDuration > 0) {
            $duration = (int) ceil((float) $fillData->StayDuration);
        } elseif (!empty($fillData->Arrival) && !empty($fillData->Departure)) {
            // Calculate from arrival/departure dates
            try {
                $arrival = \Carbon\Carbon::parse($fillData->Arrival);
                $departure = \Carbon\Carbon::parse($fillData->Departure);
                $diffDays = $arrival->diffInDays($departure);
                $duration = max(1, (int) $diffDays);
            } catch (\Exception $e) {
                $duration = 1;
            }
        }

        $limitA = $this->extractNumeric($eligibility->Lodging_CategoryA ?? 0);
        $limitB = $this->extractNumeric($eligibility->Lodging_CategoryB ?? 0);
        $limitC = $this->extractNumeric($eligibility->Lodging_CategoryC ?? 0);

        $perDayLimit = 0;
        $categoryLabel = 'Unknown';

        switch (strtoupper($cityCategory)) {
            case 'A':
                $perDayLimit = $limitA;
                $categoryLabel = 'Category A (Metro)';
                break;
            case 'B':
                $perDayLimit = $limitB;
                $categoryLabel = 'Category B (Tier 2)';
                break;
            case 'C':
                $perDayLimit = $limitC;
                $categoryLabel = 'Category C (Other)';
                break;
        }

        // Total eligible = per day limit × duration
        $totalEligible = $perDayLimit * $duration;
        $exceeds = $totalEligible > 0 && $claimedAmount > ($totalEligible + 1); // ₹1 tolerance

        return [
            'claim_type' => 'Lodging',
            'claim_id' => 1,
            'claimed_amount' => $claimedAmount,
            'eligible_limit' => $totalEligible,
            'exceeds_limit' => $exceeds,
            'excess_amount' => $exceeds ? ($claimedAmount - $totalEligible) : 0,
            'validation_basis' => "Lodging {$categoryLabel}",
            'details' => [
                'city_category' => $cityCategory,
                'category_label' => $categoryLabel,
                'duration_days' => $duration,
                'per_day_limit' => $perDayLimit,
                'all_limits' => [
                    'Category A' => $limitA,
                    'Category B' => $limitB,
                    'Category C' => $limitC,
                ],
            ],
            'base_info' => $baseInfo,
            'status' => $exceeds ? 'exceeded' : 'within_limit',
        ];
    }

    /**
     * Validate Travel/KM (ClaimId 7) against rate per km and km limits
     */
    private function validateTravel($expenseDetail, $eligibility, float $claimedAmount, array $baseInfo, string $billDate, int $yearId): array
    {
        $employeeId = $expenseDetail->CrBy;
        $wheelType = $expenseDetail->WType ?? 4;
        $totalKm = (float) ($expenseDetail->TotKm ?? 0);

        // Calculate rate using RateController
        try {
            $rateController = new RateController();
            $calculatedRate = $rateController->getRatePerKM($employeeId, $billDate, $wheelType, 0);
        } catch (\Exception $e) {
            Log::warning('Rate calculation failed: ' . $e->getMessage());
            $calculatedRate = 0;
        }

        // Get base rates from eligibility
        $twoWheelerRate = $this->extractNumeric($eligibility->Travel_TwoWeeKM ?? 0);
        $fourWheelerRate = $this->extractNumeric($eligibility->Travel_FourWeeKM ?? 0);
        $baseRate = $wheelType == 2 ? $twoWheelerRate : $fourWheelerRate;

        // Expected amount based on calculated rate
        $expectedAmount = $totalKm > 0 ? round($totalKm * $calculatedRate, 2) : 0;

        // Check if claimed exceeds expected (allow ₹1 tolerance for rounding)
        $exceeds = $expectedAmount > 0 && $claimedAmount > ($expectedAmount + 1);

        // Get monthly running km for context
        $monthRunKm = $this->getMonthlyRunningKm($employeeId, $expenseDetail->ClaimMonth ?? date('n'), $yearId, $wheelType);

        return [
            'claim_type' => 'Travel / KM',
            'claim_id' => 7,
            'claimed_amount' => $claimedAmount,
            'eligible_limit' => $expectedAmount,
            'exceeds_limit' => $exceeds,
            'excess_amount' => $exceeds ? ($claimedAmount - $expectedAmount) : 0,
            'validation_basis' => 'Rate × KM calculation',
            'details' => [
                'total_km' => $totalKm,
                'wheel_type' => $wheelType == 2 ? '2-Wheeler' : '4-Wheeler',
                'calculated_rate' => $calculatedRate,
                'base_rate_2w' => $twoWheelerRate,
                'base_rate_4w' => $fourWheelerRate,
                'expected_amount' => $expectedAmount,
                'month_running_km' => $monthRunKm,
                'policy_id' => $baseInfo['policy_id'],
            ],
            'base_info' => $baseInfo,
            'status' => $exceeds ? 'exceeded' : 'within_limit',
        ];
    }

    /**
     * Validate DA (ClaimId 19 or 20) against HQ limits
     */
    private function validateDA($eligibility, float $claimedAmount, array $baseInfo, string $type): array
    {
        if ($type === 'inside') {
            $rawLimit = $eligibility->DA_Inside_Hq ?? '0';
            $claimLabel = 'DA - Inside HQ';
            $claimId = 19;
        } else {
            $rawLimit = $eligibility->DA_Outside_Hq ?? '0';
            $claimLabel = 'DA - Outside HQ';
            $claimId = 20;
        }

        $limit = $this->extractNumeric($rawLimit);
        $exceeds = $limit > 0 && $claimedAmount > $limit;

        return [
            'claim_type' => $claimLabel,
            'claim_id' => $claimId,
            'claimed_amount' => $claimedAmount,
            'eligible_limit' => $limit,
            'exceeds_limit' => $exceeds,
            'excess_amount' => $exceeds ? ($claimedAmount - $limit) : 0,
            'validation_basis' => "{$claimLabel} per day limit",
            'details' => [
                'raw_limit_value' => $rawLimit,
                'parsed_limit' => $limit,
            ],
            'base_info' => $baseInfo,
            'status' => $exceeds ? 'exceeded' : 'within_limit',
        ];
    }

    /**
     * Validate Group Meal (ClaimId 17) against DA Inside HQ and DA Outside HQ limits
     */
    private function validateGroupMeal($eligibility, float $claimedAmount, array $baseInfo): array
    {
        $daInside = $this->extractNumeric($eligibility->DA_Inside_Hq ?? 0);
        $daOutside = $this->extractNumeric($eligibility->DA_Outside_Hq ?? 0);
        $maxLimit = max($daInside, $daOutside);
        $exceeds = $maxLimit > 0 && $claimedAmount > $maxLimit;

        return [
            'claim_type' => 'Group Meal',
            'claim_id' => 17,
            'claimed_amount' => $claimedAmount,
            'eligible_limit' => $maxLimit,
            'exceeds_limit' => $exceeds,
            'excess_amount' => $exceeds ? ($claimedAmount - $maxLimit) : 0,
            'validation_basis' => 'Group Meal DA Limits (Inside & Outside HQ)',
            'details' => [
                'da_inside_hq' => $daInside,
                'da_outside_hq' => $daOutside,
            ],
            'base_info' => $baseInfo,
            'status' => $exceeds ? 'exceeded' : 'within_limit',
        ];
    }

    /**
     * Validate against generic claim limits table
     */
    private function validateGenericLimit($employeeId, $claimId, float $claimedAmount, array $baseInfo): array
    {
        $claimName = DB::connection(session('expense_connection'))
            ->table('claimtype')
            ->where('ClaimId', $claimId)
            ->value('ClaimName') ?? "Claim #{$claimId}";

        // E-03 (10 Nov 2026): the local claimlimits table is stale (7 rows for one employee, 2019); limits come from
        // the HRIMS eligibility only, unless CLAIM_RULES_LOCAL_LIMITS=true brings the old table back
        $limit = 0;
        if (config('claim_rules.local_limits', false)) try {
            $limitRecord = DB::connection(session('expense_connection'))
                ->selectOne("SELECT verifiedLimit FROM claimlimits WHERE EmployeeID = ? AND ClaimId = ?", [$employeeId, $claimId]);
            $limit = $limitRecord ? (float) $limitRecord->verifiedLimit : 0;
        } catch (\Exception $e) {
            // claimlimits table may not exist for all companies
            $limit = 0;
        }

        $exceeds = $limit > 0 && $claimedAmount > $limit;

        return [
            'claim_type' => $claimName,
            'claim_id' => $claimId,
            'claimed_amount' => $claimedAmount,
            'eligible_limit' => $limit,
            'exceeds_limit' => $exceeds,
            'excess_amount' => $exceeds ? ($claimedAmount - $limit) : 0,
            'validation_basis' => $limit > 0 ? 'Per-employee claim limit' : 'No specific limit defined',
            'details' => [
                'has_limit' => $limit > 0,
            ],
            'base_info' => $baseInfo,
            'status' => $limit > 0 ? ($exceeds ? 'exceeded' : 'within_limit') : 'no_limit',
        ];
    }

    /**
     * Build default response when eligibility not found
     */
    private function buildResponse($claimId, float $claimedAmount, $limit, string $message): array
    {
        return [
            'claim_type' => "Claim #{$claimId}",
            'claim_id' => $claimId,
            'claimed_amount' => $claimedAmount,
            'eligible_limit' => $limit,
            'exceeds_limit' => false,
            'excess_amount' => 0,
            'validation_basis' => $message,
            'details' => [],
            'base_info' => [],
            'status' => 'unavailable',
        ];
    }

    /**
     * Get employee eligibility record from HRIMS
     */
    private function getEmployeeEligibility($employeeId)
    {
        try {
            return DB::connection('hrims')->selectOne(
                "SELECT * FROM hrm_employee_eligibility WHERE EmployeeID = ? AND Status = 'A'",
                [$employeeId]
            );
        } catch (\Exception $e) {
            Log::warning("Eligibility fetch failed for employee {$employeeId}: " . $e->getMessage());
            return null;
        }
    }

    /**
     * Get employee grade name
     */
    private function getEmployeeGrade($employeeId): string
    {
        try {
            $result = DB::connection('hrims')->selectOne("
                SELECT gr.GradeValue
                FROM hrm_employee_general g
                INNER JOIN hrm_grade gr ON g.GradeId = gr.GradeId
                WHERE g.EmployeeID = ?
            ", [$employeeId]);
            return $result ? $result->GradeValue : 'N/A';
        } catch (\Exception $e) {
            return 'N/A';
        }
    }

    /**
     * Get policy name from policy ID
     */
    private function getPolicyName($policyId): string
    {
        $policyId = (int) $policyId;
        if ($policyId <= 0) return 'No Policy';

        try {
            $result = DB::connection('hrims')->selectOne(
                "SELECT PolicyName FROM hrm_master_eligibility_policy WHERE PolicyId = ?",
                [$policyId]
            );
            return $result ? $result->PolicyName : "Policy #{$policyId}";
        } catch (\Exception $e) {
            return "Policy #{$policyId}";
        }
    }

    /**
     * Get monthly running KM for context
     */
    private function getMonthlyRunningKm($employeeId, $month, $yearId, $wheelType): float
    {
        try {
            $result = DB::connection(session('expense_connection'))->selectOne("
                SELECT SUM(TotKm) as total_km
                FROM y{$yearId}_expenseclaims
                WHERE ClaimId = 7
                AND CrBy = ?
                AND ClaimMonth = ?
                AND ClaimStatus NOT IN ('Deactivate', 'Draft')
                AND TotKm >= 0
                AND WType = ?
            ", [$employeeId, $month, $wheelType]);

            return $result ? (float) $result->total_km : 0;
        } catch (\Exception $e) {
            return 0;
        }
    }

    /**
     * Extract numeric value from eligibility string fields
     * Handles formats like: "Upto Rs.175/day", "Rs. 2500", "2,500", "175", "Rs.3.50"
     */
    private function extractNumeric($value): float
    {
        if ($value === null || $value === '') {
            return 0;
        }

        if (is_numeric($value)) {
            return (float) $value;
        }

        $value = (string) $value;

        // Try to find pattern like Rs.XXX or Rs. XXX or Rs XXX
        if (preg_match('/Rs\.?\s*([\d,]+\.?\d*)/i', $value, $matches)) {
            $cleaned = str_replace(',', '', $matches[1]);
            return (float) $cleaned;
        }

        // Fallback: extract all digits and last decimal point
        $cleaned = preg_replace('/[^0-9.]/', '', $value);
        // Handle multiple dots (e.g., "Rs.175" → ".175") - take the number after last dot if starts with dot
        if (str_starts_with($cleaned, '.')) {
            $cleaned = ltrim($cleaned, '.');
        }
        // Handle cases like "2.500.00" - keep only last decimal
        $parts = explode('.', $cleaned);
        if (count($parts) > 2) {
            $decimal = array_pop($parts);
            $cleaned = implode('', $parts) . '.' . $decimal;
        }

        return (float) $cleaned;
    }
}
