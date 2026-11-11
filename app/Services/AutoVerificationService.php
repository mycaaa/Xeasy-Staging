<?php

namespace App\Services;

use App\Helpers\CommonHelper;
use App\Helpers\DatabaseSwitcher;
use App\Http\Controllers\admin\RateController;
use App\Models\AutoVerifySetting;
use App\Models\ExpenseClaim;
use App\Models\MonthExpenseFinal;
use App\Models\TrackingSession;
use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class AutoVerificationService
{
    protected EligibilityValidationService $eligibilityService;
    protected RateController $rateController;

    protected array $rateCache = [];
    protected array $tableExistsCache = [];

    public function __construct(
        ?EligibilityValidationService $eligibilityService = null,
        ?RateController $rateController = null
    ) {
        $this->eligibilityService = $eligibilityService ?? new EligibilityValidationService();
        $this->rateController = $rateController ?? new RateController();
    }

    /**
     * Evaluate and optionally verify a single expense claim.
     *
     * @param object $claim Raw or Eloquent claim record
     * @param int $yearId Financial Year ID
     * @param int $companyId Company ID
     * @param bool $dryRun If true, calculate outcome without mutating DB
     * @return array Verification decision and audit payload
     */
    public function verifyClaim(object $claim, int $yearId, int $companyId = 1, bool $dryRun = false): array
    {
        $expId = (int) $claim->ExpId;
        $claimId = (int) $claim->ClaimId;
        $empId = (int) $claim->CrBy;
        $billDate = $claim->BillDate ?? date('Y-m-d');
        $claimedAmount = (float) ($claim->FilledTAmt ?? $claim->Amount ?? 0);
        $expenseConnection = DatabaseSwitcher::getExpenseConnection($companyId);
        $claimTable = ExpenseClaim::tableName($yearId);

        // 1. Strictly exclude Deactivate and Draft claims from auto-verification
        if (in_array($claim->ClaimStatus, ['Deactivate', 'Draft'])) {
            return $this->result('skipped', 0, "Claim is in {$claim->ClaimStatus} status (excluded from auto-verification)", 'none', $expId);
        }

        if ($claim->ClaimStatus === 'Verified' || ($claim->v_verify ?? 'N') === 'Y') {
            return $this->result('skipped', (float) ($claim->VerifyTAmt ?? $claimedAmount), 'Already verified', 'none', $expId);
        }

        // Must be in a submitted/filled state and at step >= 3
        if (!in_array($claim->ClaimStatus, ['Submitted', 'Filled']) || ($claim->ClaimAtStep ?? 0) < 3) {
            return $this->result('flagged', $claimedAmount, 'Claim not yet submitted by claimant', 'red', $expId);
        }

        // 2. Fast Indexed Duplicate Detection
        $duplicate = DB::connection($expenseConnection)->table($claimTable)
            ->where('ExpId', '!=', $expId)
            ->where('CrBy', $empId)
            ->where('ClaimId', $claimId)
            ->where('BillDate', $billDate)
            ->where('AttachTo', 0)
            ->whereNotIn('ClaimStatus', ['Deactivate', 'Draft'])
            ->whereBetween('FilledTAmt', [$claimedAmount - 0.01, $claimedAmount + 0.01])
            ->first(['ExpId']);

        if ($duplicate) {
            return $this->result('flagged', $claimedAmount, "Duplicate punch: Matches ExpId #{$duplicate->ExpId} on {$billDate}", 'red', $expId);
        }

        // 3. Dynamic Auto-Verification Settings & Policy Rules Check
        $settings = AutoVerifySetting::getSettingsForCompany($companyId);
        if (!$settings->is_enabled) {
            return $this->result('flagged', $claimedAmount, 'Auto-verification is currently paused in Admin Settings', 'yellow', $expId);
        }

        $rule = $settings->getRuleForClaimType($claimId);
        if (empty($rule['enabled'])) {
            return $this->result('flagged', $claimedAmount, 'Auto-verification disabled for this claim type in Admin Settings', 'yellow', $expId);
        }

        // Mandatory bill / receipt attachment check
        if (!empty($rule['require_attachment']) && !$this->hasAttachment($claim, $yearId, $companyId)) {
            return $this->result('flagged', $claimedAmount, 'Missing mandatory invoice/bill attachment required by policy', 'red', $expId);
        }

        // Max single claim amount ceiling check
        $maxAllowed = (float) ($rule['max_amount'] ?? $settings->max_auto_verify_amount);
        if ($maxAllowed > 0 && $claimedAmount > $maxAllowed) {
            return $this->result('flagged', $claimedAmount, sprintf("Claim ₹%.2f exceeds auto-verify limit ceiling ₹%.2f (manual review required)", $claimedAmount, $maxAllowed), 'yellow', $expId);
        }

        // 4. Claim Type Specific Business Rules
        switch ($claimId) {
            case 7: // Travel / Vehicle Running KM
                $decision = $this->evaluateTravelClaim($claim, $yearId, $companyId);
                break;

            case 19: // DA Inside HQ
            case 20: // DA Outside HQ
                $decision = $this->evaluateDAClaim($claim, $yearId, $companyId);
                break;

            case 1: // Lodging / Hotel
                $decision = $this->evaluateLodgingClaim($claim, $yearId, $companyId);
                break;

            case 17: // Group Meal
                $decision = $this->evaluateGroupMealClaim($claim, $yearId, $companyId);
                break;

            case 2: // Travel - Train
            case 14: // Travel - Air
            case 15: // Travel - Bus
            case 3: // Local Conveyance (Train/Bus/Auto)
            case 4: // Travel - Taxi / Hired Vehicle
            case 18: // Auto/Taxi/Rikshaw
            case 43: // Other Conveyance
                $decision = $this->evaluateTransitClaim($claim, $yearId, $companyId);
                break;

            default: // Other general claims (stationary, courier, printing, client meeting, etc.)
                $decision = $this->evaluateGeneralClaim($claim, $yearId, $companyId);
                break;
        }

        // If decision is not flagged/rejected, apply changes if not a dry-run
        if (in_array($decision['status'], ['approved', 'adjusted']) && !$dryRun) {
            $this->applyClaimVerification($claim, $decision, $yearId, $companyId);
        }

        return $decision;
    }

    /**
     * Travel / KM (ClaimId 7) validation against Odometer continuity, GPS distance, and Rate/KM.
     */
    protected function evaluateTravelClaim(object $claim, int $yearId, int $companyId): array
    {
        $expId = (int) $claim->ExpId;
        $empId = (int) $claim->CrBy;
        $billDate = $claim->BillDate ?? date('Y-m-d');
        $wheelType = (int) ($claim->WType ?? 4);
        $opening = (float) ($claim->odomtr_opening ?? 0);
        $closing = (float) ($claim->odomtr_closing ?? 0);
        $claimedKm = (float) ($claim->TotKm ?? ($closing - $opening));
        $claimedAmount = (float) ($claim->FilledTAmt ?? 0);
        $expenseConnection = DatabaseSwitcher::getExpenseConnection($companyId);
        $claimTable = ExpenseClaim::tableName($yearId);

        if ($claimedKm <= 0 && ($closing > $opening)) {
            $claimedKm = $closing - $opening;
        }

        if ($claimedKm <= 0) {
            return $this->result('flagged', $claimedAmount, 'Total KM is zero or closing is less than opening reading', 'red', $expId);
        }

        $settings = AutoVerifySetting::getSettingsForCompany($companyId);
        $rule = $settings->getOdometerSettings();

        // 1. Max Daily KM Limit check
        $maxDailyKm = (float) ($rule['max_daily_km'] ?? 400);
        if ($maxDailyKm > 0 && $claimedKm > $maxDailyKm) {
            return $this->result('flagged', $claimedAmount, sprintf("Single-day travel of %.1f KM exceeds max threshold of %.0f KM", $claimedKm, $maxDailyKm), 'yellow', $expId);
        }

        // 2. Odometer Photos Requirements (Opening & Closing)
        if (!empty($rule['require_opening_photo']) && empty($claim->opening_filepath)) {
            return $this->result('flagged', $claimedAmount, 'Opening odometer photo is mandatory per policy', 'red', $expId);
        }
        if (!empty($rule['require_closing_photo']) && empty($claim->closing_filepath)) {
            return $this->result('flagged', $claimedAmount, 'Closing odometer photo is mandatory per policy', 'red', $expId);
        }

        // 3. Monthly 1,200 KM threshold check
        if (!empty($rule['monthly_km_limit_check'])) {
            $claimMonth = (int) ($claim->ClaimMonth ?? date('n', strtotime($billDate)));
            $cumulativeKm = DB::connection($expenseConnection)->table($claimTable)
                ->where('CrBy', $empId)
                ->where('ClaimMonth', $claimMonth)
                ->where('ClaimId', 7)
                ->whereNotIn('ClaimStatus', ['Deactivate', 'Draft'])
                ->where('ExpId', '<=', $expId)
                ->sum('TotKm');

            if ($cumulativeKm > 1200 && in_array($claim->App_Odometer ?? '', ['N', 'R'])) {
                return $this->result('flagged', $claimedAmount, sprintf("Monthly travel of %.0f KM crosses 1,200 KM policy limit and requires special approval", $cumulativeKm), 'red', $expId);
            }
        }

        // Rate per KM calculation with memory caching
        $rateKey = "{$empId}_{$billDate}_{$wheelType}_" . ($claim->With_Driver ?? '');
        if (!isset($this->rateCache[$rateKey])) {
            try {
                $this->rateCache[$rateKey] = (float) $this->rateController->getRatePerKM($empId, $billDate, $wheelType, 0, $claim->With_Driver ?? null);
            } catch (\Exception $e) {
                $this->rateCache[$rateKey] = (float) ($claim->RatePerKM ?? 0);
            }
        }
        $ratePerKm = $this->rateCache[$rateKey];

        if ($ratePerKm <= 0) {
            return $this->result('flagged', $claimedAmount, 'Vehicle rate per KM not defined for employee grade/vehicle', 'red', $expId);
        }

        $expectedAmount = round($claimedKm * $ratePerKm, 2);
        $gpsVariance = null;

        // 4. GPS Telemetry Cross-Check (Only if check_with_gps toggle is ON)
        if (!empty($rule['check_with_gps'])) {
            $trackingSession = null;
            try {
                $trackingSession = TrackingSession::where('employee_id', $empId)
                    ->whereDate('tracking_date', $billDate)
                    ->where('status', '!=', 'cancelled')
                    ->first();
            } catch (\Throwable $e) {
                Log::debug("Tracking check query skipped for ExpId {$expId}: " . $e->getMessage());
                $trackingSession = null;
            }

            if ($trackingSession && $trackingSession->total_distance_km > 0) {
                $gpsDist = (float) $trackingSession->total_distance_km;
                $gpsVariance = abs($claimedKm - $gpsDist) / max($gpsDist, 1) * 100;
                $maxGpsTol = (float) ($rule['gps_tolerance_pct'] ?? $settings->gps_tolerance_pct ?? 15.0);

                if ($gpsVariance > $maxGpsTol && $claimedKm > $gpsDist) {
                    $excessGpsAction = $rule['excess_gps_action'] ?? 'flag';

                    if ($excessGpsAction === 'cap') {
                        $allowedKm = round($gpsDist * (1 + ($maxGpsTol / 100)), 1);
                        $cappedAmount = round($allowedKm * $ratePerKm, 2);
                        return $this->result(
                            'adjusted',
                            $cappedAmount,
                            sprintf("Distance auto-capped to GPS distance + %.0f%% tolerance (Claimed %.1f KM, Capped to %.1f KM = ₹%.2f)", $maxGpsTol, $claimedKm, $allowedKm, $cappedAmount),
                            'yellow',
                            $expId,
                            ['rate_per_km' => $ratePerKm, 'total_km' => $allowedKm, 'gps_variance_pct' => $gpsVariance]
                        );
                    } elseif ($excessGpsAction === 'reject') {
                        return $this->result('flagged', $claimedAmount, sprintf("GPS variance %.1f%% exceeds limit (Claimed %.1f KM vs GPS %.1f KM - Rejected)", $gpsVariance, $claimedKm, $gpsDist), 'red', $expId);
                    }

                    return $this->result(
                        'flagged',
                        $claimedAmount,
                        sprintf("GPS variance %.1f%% exceeds limit: Claimed %.1f KM vs GPS %.1f KM", $gpsVariance, $claimedKm, $gpsDist),
                        'red',
                        $expId
                    );
                }
            } else {
                // Tracking session is missing / unavailable
                $missingAction = $rule['missing_gps_action'] ?? 'pass';
                if ($missingAction === 'flag') {
                    return $this->result('flagged', $claimedAmount, 'GPS tracking session unavailable for this travel date', 'yellow', $expId);
                }
            }
        }

        // 5. Amount Check against Rate Calculation
        $diff = $claimedAmount - $expectedAmount;
        if ($diff > 1.0) { // Claimed more than rate × km
            $action = $rule['action_over_limit'] ?? 'cap';
            if ($action === 'cap') {
                return $this->result(
                    'adjusted',
                    $expectedAmount,
                    sprintf("Auto-capped to Rate (₹%.2f/KM × %.1f KM = ₹%.2f)", $ratePerKm, $claimedKm, $expectedAmount),
                    'yellow',
                    $expId,
                    [
                        'rate_per_km' => $ratePerKm,
                        'total_km' => $claimedKm,
                        'gps_variance_pct' => $gpsVariance,
                    ]
                );
            } elseif ($action === 'reject') {
                return $this->result('flagged', $claimedAmount, sprintf("Amount ₹%.2f exceeds rate calculation ₹%.2f (Policy: Auto-reject)", $claimedAmount, $expectedAmount), 'red', $expId);
            }
            return $this->result('flagged', $claimedAmount, sprintf("Amount ₹%.2f exceeds rate calculation ₹%.2f (Policy: Route to verifier)", $claimedAmount, $expectedAmount), 'red', $expId);
        }

        $verifiedAmount = ($claimedAmount > 0) ? min($claimedAmount, $expectedAmount) : $expectedAmount;

        return $this->result(
            'approved',
            $verifiedAmount,
            sprintf("Odometer & rate verified (₹%.2f/KM × %.1f KM)", $ratePerKm, $claimedKm),
            'green',
            $expId,
            [
                'rate_per_km' => $ratePerKm,
                'total_km' => $claimedKm,
                'gps_variance_pct' => $gpsVariance,
            ]
        );
    }

    /**
     * Daily Allowance (DA Inside/Outside HQ) validation against Grade Limits.
     */
    protected function evaluateDAClaim(object $claim, int $yearId, int $companyId): array
    {
        $expId = (int) $claim->ExpId;
        $claimedAmount = (float) ($claim->FilledTAmt ?? 0);

        $settings = AutoVerifySetting::getSettingsForCompany($companyId);
        $daSettings = $settings->getDaSettings();

        if (empty($daSettings['enabled'])) {
            return $this->result('flagged', $claimedAmount, 'DA auto-verification disabled in policy', 'red', $expId);
        }

        // Attendance cross-check if enabled
        if (!empty($daSettings['check_attendance']) && !empty($claim->ExpDate) && !empty($claim->EmployeeID)) {
            try {
                $attRecord = DB::connection('hrims')->table('hrm_employee_attendance')
                    ->where('EmployeeID', $claim->EmployeeID)
                    ->where('AttDate', $claim->ExpDate)
                    ->first();
                if ($attRecord) {
                    $attVal = strtoupper(trim($attRecord->AttValue ?? ''));
                    $leaveCodes = ['A', 'AB', 'L', 'CL', 'SL', 'PL', 'EL', 'ML', 'CH'];
                    if (in_array($attVal, $leaveCodes)) {
                        return $this->result('flagged', $claimedAmount, "Employee marked on leave/absent ({$attVal}) on claim date {$claim->ExpDate}", 'red', $expId);
                    }
                }
            } catch (\Exception $e) {
                // Skip if attendance table not reachable
            }
        }

        if (!empty($daSettings['check_grade_limit'])) {
            $eligibilityData = $this->eligibilityService->getEligibilityData($claim, $yearId);
            $eligibleLimit = (float) ($eligibilityData['eligible_limit'] ?? 0);
            $exceeds = (bool) ($eligibilityData['exceeds_limit'] ?? false);

            if ($eligibleLimit <= 0) {
                return $this->result('flagged', $claimedAmount, 'DA limit not configured for employee grade', 'red', $expId);
            }

            $action = $daSettings['action_over_limit'] ?? 'cap';

            if ($exceeds) {
                if ($action === 'cap') {
                    return $this->result(
                        'adjusted',
                        $eligibleLimit,
                        sprintf("Auto-capped to DA grade limit (Claimed ₹%.2f, Max ₹%.2f)", $claimedAmount, $eligibleLimit),
                        'yellow',
                        $expId,
                        ['eligible_limit' => $eligibleLimit]
                    );
                } elseif ($action === 'reject') {
                    return $this->result('flagged', $claimedAmount, sprintf("DA claim ₹%.2f exceeds limit ₹%.2f (Policy: Auto-reject)", $claimedAmount, $eligibleLimit), 'red', $expId);
                }
                return $this->result('flagged', $claimedAmount, sprintf("DA claim ₹%.2f exceeds limit ₹%.2f (Policy: Route to verifier)", $claimedAmount, $eligibleLimit), 'red', $expId);
            }

            return $this->result(
                'approved',
                $claimedAmount,
                sprintf("DA is within eligible limit (₹%.2f <= ₹%.2f)", $claimedAmount, $eligibleLimit),
                'green',
                $expId,
                ['eligible_limit' => $eligibleLimit]
            );
        }

        return $this->result('approved', $claimedAmount, 'DA claim approved (Grade limit check bypassed)', 'green', $expId);
    }

    /**
     * Lodging validation against City Category and Duration limits.
     */
    protected function evaluateLodgingClaim(object $claim, int $yearId, int $companyId): array
    {
        $expId = (int) $claim->ExpId;
        $claimedAmount = (float) ($claim->FilledTAmt ?? 0);

        $settings = AutoVerifySetting::getSettingsForCompany($companyId);
        $lodgingSettings = $settings->getLodgingSettings();

        if (empty($lodgingSettings['enabled'])) {
            return $this->result('flagged', $claimedAmount, 'Lodging auto-verification disabled in policy', 'red', $expId);
        }

        // Ensure bill attachment exists if required
        if (!empty($lodgingSettings['require_attachment']) && !$this->hasAttachment($claim, $yearId, $companyId)) {
            return $this->result('flagged', $claimedAmount, 'Lodging claim missing invoice/bill attachment', 'red', $expId);
        }

        if (!empty($lodgingSettings['check_city_category'])) {
            $eligibilityData = $this->eligibilityService->getEligibilityData($claim, $yearId);
            $eligibleLimit = (float) ($eligibilityData['eligible_limit'] ?? 0);
            $exceeds = (bool) ($eligibilityData['exceeds_limit'] ?? false);

            if ($eligibleLimit <= 0) {
                return $this->result('flagged', $claimedAmount, 'Lodging limit not found for city category/grade', 'red', $expId);
            }

            $action = $lodgingSettings['action_over_limit'] ?? 'flag';

            if ($exceeds) {
                if ($action === 'cap') {
                    return $this->result(
                        'adjusted',
                        $eligibleLimit,
                        sprintf("Auto-capped to lodging limit ₹%.2f", $eligibleLimit),
                        'yellow',
                        $expId
                    );
                } elseif ($action === 'reject') {
                    return $this->result('flagged', $claimedAmount, sprintf("Hotel bill ₹%.2f exceeds city category limit ₹%.2f (Policy: Auto-reject)", $claimedAmount, $eligibleLimit), 'red', $expId);
                }
                return $this->result(
                    'flagged',
                    $claimedAmount,
                    sprintf("Hotel bill ₹%.2f exceeds city category limit ₹%.2f (Policy: Route to verifier)", $claimedAmount, $eligibleLimit),
                    'red',
                    $expId
                );
            }

            return $this->result(
                'approved',
                $claimedAmount,
                sprintf("Lodging complies with city category tariff limit ₹%.2f", $eligibleLimit),
                'green',
                $expId
            );
        }

        return $this->result('approved', $claimedAmount, 'Lodging claim approved', 'green', $expId);
    }

    /**
     * Group Meal validation against Headcount and Quota.
     */
    protected function evaluateGroupMealClaim(object $claim, int $yearId, int $companyId): array
    {
        $expId = (int) $claim->ExpId;
        $claimedAmount = (float) ($claim->FilledTAmt ?? 0);

        $settings = AutoVerifySetting::getSettingsForCompany($companyId);
        $mealSettings = $settings->getMealSettings();

        if (empty($mealSettings['enabled'])) {
            return $this->result('flagged', $claimedAmount, 'Group meal auto-verification disabled in policy', 'red', $expId);
        }

        if (!empty($mealSettings['require_attachment']) && !$this->hasAttachment($claim, $yearId, $companyId)) {
            return $this->result('flagged', $claimedAmount, 'Group meal claim missing bill/receipt attachment', 'red', $expId);
        }

        if (!empty($mealSettings['check_headcount_limit'])) {
            $eligibilityData = $this->eligibilityService->getEligibilityData($claim, $yearId);
            $eligibleLimit = (float) ($eligibilityData['eligible_limit'] ?? 0);
            $exceeds = (bool) ($eligibilityData['exceeds_limit'] ?? false);

            $action = $mealSettings['action_over_limit'] ?? 'cap';

            if ($exceeds && $eligibleLimit > 0) {
                if ($action === 'cap') {
                    return $this->result(
                        'adjusted',
                        $eligibleLimit,
                        sprintf("Auto-capped to group meal allowance ₹%.2f", $eligibleLimit),
                        'yellow',
                        $expId
                    );
                } elseif ($action === 'reject') {
                    return $this->result('flagged', $claimedAmount, sprintf("Meal amount ₹%.2f exceeds allowance ₹%.2f (Policy: Auto-reject)", $claimedAmount, $eligibleLimit), 'red', $expId);
                }
                return $this->result('flagged', $claimedAmount, sprintf("Meal amount ₹%.2f exceeds allowance ₹%.2f (Policy: Route to verifier)", $claimedAmount, $eligibleLimit), 'red', $expId);
            }
        }

        return $this->result('approved', $claimedAmount, 'Group meal is within authorized limits', 'green', $expId);
    }

    /**
     * Transit & Conveyance (Air, Rail, Bus, Fares) validation against policy & entitlements.
     */
    protected function evaluateTransitClaim(object $claim, int $yearId, int $companyId): array
    {
        $expId = (int) $claim->ExpId;
        $claimedAmount = (float) ($claim->FilledTAmt ?? $claim->Amount ?? 0);

        $settings = AutoVerifySetting::getSettingsForCompany($companyId);
        $transitSettings = $settings->getTransitSettings();

        if (empty($transitSettings['enabled'])) {
            return $this->result('flagged', $claimedAmount, 'Transit auto-verification disabled in policy', 'red', $expId);
        }

        // Require ticket / boarding pass attachment check
        if (!empty($transitSettings['require_attachment']) && !$this->hasAttachment($claim, $yearId, $companyId)) {
            return $this->result('flagged', $claimedAmount, 'Transit claim missing mandatory ticket/boarding pass attachment', 'red', $expId);
        }

        // Check grade entitlement if enabled
        if (!empty($transitSettings['check_grade_entitlement'])) {
            $eligibilityData = $this->eligibilityService->getEligibilityData($claim, $yearId);
            $eligibleLimit = (float) ($eligibilityData['eligible_limit'] ?? 0);
            $exceeds = (bool) ($eligibilityData['exceeds_limit'] ?? false);

            if ($exceeds && $eligibleLimit > 0) {
                $action = $transitSettings['action_over_limit'] ?? 'flag';
                if ($action === 'cap') {
                    return $this->result(
                        'adjusted',
                        $eligibleLimit,
                        sprintf("Auto-capped to transit entitlement limit ₹%.2f", $eligibleLimit),
                        'yellow',
                        $expId
                    );
                } elseif ($action === 'reject') {
                    return $this->result('flagged', $claimedAmount, sprintf("Transit fare ₹%.2f exceeds entitlement limit ₹%.2f (Policy: Auto-reject)", $claimedAmount, $eligibleLimit), 'red', $expId);
                }
                return $this->result('flagged', $claimedAmount, sprintf("Transit fare ₹%.2f exceeds entitlement limit ₹%.2f (Policy: Route to verifier)", $claimedAmount, $eligibleLimit), 'red', $expId);
            }
        }

        // Max amount check
        $maxAmt = (float) ($transitSettings['max_amount'] ?? 0);
        if ($maxAmt > 0 && $claimedAmount > $maxAmt) {
            $action = $transitSettings['action_over_limit'] ?? 'flag';
            if ($action === 'cap') {
                return $this->result('adjusted', $maxAmt, sprintf("Auto-capped to transit max ceiling ₹%.2f", $maxAmt), 'yellow', $expId);
            } elseif ($action === 'reject') {
                return $this->result('flagged', $claimedAmount, sprintf("Claimed ₹%.2f exceeds transit policy ceiling ₹%.2f (Auto-reject)", $claimedAmount, $maxAmt), 'red', $expId);
            }
            return $this->result('flagged', $claimedAmount, sprintf("Claimed ₹%.2f exceeds transit policy ceiling ₹%.2f (Route to verifier)", $claimedAmount, $maxAmt), 'red', $expId);
        }

        if ($claimedAmount <= 0) {
            return $this->result('flagged', 0, 'Transit claimed amount must be greater than zero', 'red', $expId);
        }

        return $this->result('approved', $claimedAmount, 'Transit claim complies with travel policy', 'green', $expId);
    }

    /**
     * General bill-based claims (Travel tickets, stationery, courier, etc.)
     */
    protected function evaluateGeneralClaim(object $claim, int $yearId, int $companyId): array
    {
        $expId = (int) $claim->ExpId;
        $claimedAmount = (float) ($claim->FilledTAmt ?? $claim->Amount ?? 0);

        $settings = AutoVerifySetting::getSettingsForCompany($companyId);
        $billSettings = $settings->getGeneralBillSettings();

        if (empty($billSettings['enabled'])) {
            return $this->result('flagged', $claimedAmount, 'General bill auto-verification disabled in policy', 'red', $expId);
        }

        // Require invoice attachment check
        if (!empty($billSettings['require_attachment']) && !$this->hasAttachment($claim, $yearId, $companyId)) {
            return $this->result('flagged', $claimedAmount, 'Claim missing mandatory invoice/bill attachment', 'red', $expId);
        }

        // Policy limit check
        $maxAmt = (float) ($billSettings['max_amount'] ?? 0);
        if ($maxAmt > 0 && $claimedAmount > $maxAmt) {
            $action = $billSettings['action_over_limit'] ?? 'flag';
            if ($action === 'cap') {
                return $this->result('adjusted', $maxAmt, sprintf("Auto-capped to general bill limit ₹%.2f", $maxAmt), 'yellow', $expId);
            } elseif ($action === 'reject') {
                return $this->result('flagged', $claimedAmount, sprintf("Claimed ₹%.2f exceeds bill policy limit ₹%.2f (Auto-reject)", $claimedAmount, $maxAmt), 'red', $expId);
            }
            return $this->result('flagged', $claimedAmount, sprintf("Claimed ₹%.2f exceeds bill policy limit ₹%.2f (Route to verifier)", $claimedAmount, $maxAmt), 'red', $expId);
        }

        // OCR Comparison if OCR option enabled AND OCR data exists
        if (!empty($billSettings['check_with_ocr'])) {
            $ocrAmount = isset($claim->ocr_amount) && is_numeric($claim->ocr_amount) ? (float) $claim->ocr_amount : null;
            if ($ocrAmount !== null && $ocrAmount > 0) {
                $ocrTolerancePct = (float) ($billSettings['ocr_tolerance_pct'] ?? $settings->ocr_tolerance_pct ?? 5.0);
                $ocrToleranceFlat = (float) ($billSettings['ocr_tolerance_flat'] ?? $settings->ocr_tolerance_flat ?? 5.0);

                $diff = abs($claimedAmount - $ocrAmount);
                $maxAllowedDiff = max($ocrToleranceFlat, $claimedAmount * ($ocrTolerancePct / 100));

                if ($diff > $maxAllowedDiff) {
                    $actionOcr = $billSettings['action_ocr_mismatch'] ?? 'flag';
                    if ($actionOcr === 'cap') {
                        return $this->result(
                            'adjusted',
                            $ocrAmount,
                            sprintf("Auto-adjusted to OCR scanned bill amount ₹%.2f (Claimed ₹%.2f)", $ocrAmount, $claimedAmount),
                            'yellow',
                            $expId,
                            ['ocr_amount' => $ocrAmount]
                        );
                    } elseif ($actionOcr === 'reject') {
                        return $this->result(
                            'flagged',
                            $claimedAmount,
                            sprintf("OCR bill amount (₹%.2f) differs from claimed (₹%.2f) - Policy: Auto-reject", $ocrAmount, $claimedAmount),
                            'red',
                            $expId
                        );
                    }
                    return $this->result(
                        'flagged',
                        $claimedAmount,
                        sprintf("OCR bill amount (₹%.2f) differs from claimed (₹%.2f) - Exceeds tolerance", $ocrAmount, $claimedAmount),
                        'red',
                        $expId
                    );
                }
            }
        }

        if ($claimedAmount <= 0) {
            return $this->result('flagged', 0, 'Claimed amount must be greater than zero', 'red', $expId);
        }

        return $this->result('approved', $claimedAmount, 'Standard compliant expense item', 'green', $expId);
    }

    /**
     * Check if claim has uploaded attachment files.
     */
    protected function hasAttachment(object $claim, int $yearId, int $companyId): bool
    {
        // 1. Check direct file path columns on expenseclaim table
        foreach (['opening_filepath', 'closing_filepath', 'Img_1', 'Img_2', 'Img1_offline'] as $col) {
            if (!empty($claim->{$col})) return true;
        }

        // 2. Check claimuploads table
        $conn = DatabaseSwitcher::getExpenseConnection($companyId);
        $uploadTable = "y{$yearId}_claimuploads";
        $cacheKey = "{$conn}_{$uploadTable}";
        if (!isset($this->tableExistsCache[$cacheKey])) {
            $this->tableExistsCache[$cacheKey] = DB::connection($conn)->getSchemaBuilder()->hasTable($uploadTable);
        }
        if ($this->tableExistsCache[$cacheKey]) {
            $hasUpload = DB::connection($conn)->table($uploadTable)
                ->where('ExpId', $claim->ExpId)
                ->whereNotNull('FileName')
                ->where('FileName', '!=', '')
                ->exists();
            if ($hasUpload) return true;
        }

        return false;
    }

    /**
     * Persist auto-verification to database.
     */
    protected function applyClaimVerification(object $claim, array $decision, int $yearId, int $companyId): void
    {
        $conn = DatabaseSwitcher::getExpenseConnection($companyId);
        $claimTable = ExpenseClaim::tableName($yearId);
        $expId = (int) $claim->ExpId;
        $verifiedAmount = $decision['verified_amount'];
        $verifierId = Auth::id() ?? 1;
        $verifyDate = now()->format('Y-m-d');
        $source = ($decision['channel'] === 'yellow') ? 'auto_adjusted' : 'auto';

        // Q-02: the system never verifies above the claimed amount
        $verifiedAmount = \App\Services\Claims\StageCap::clamp($conn, (int) $yearId, $claim, $verifiedAmount, 'verify', 'auto_verify_'.$source);

        DB::connection($conn)->transaction(function () use ($conn, $claimTable, $expId, $verifiedAmount, $verifierId, $verifyDate, $source, $decision, $claim, $yearId, $companyId) {
            // B-01: auto-verification is a system transition (checked against the table, logged)
            \App\Services\Claims\ClaimTransition::guard($conn, (int) $yearId, $expId, 'Verified', 'system', 'auto_verify_'.$source, $decision['reason'] ?? null, 'auto', null, $companyId);
            // Update main claim record
            DB::connection($conn)->table($claimTable)->where('ExpId', $expId)->update([
                'ClaimStatus' => 'Verified',
                'ClaimAtStep' => 4,
                'VerifyBy' => $verifierId,
                'VerifyTAmt' => $verifiedAmount,
                'VerifyDate' => $verifyDate,
                'VerifyTRemark' => \App\Support\SystemRemark::mark($decision['reason'] ?? null, 500), // REMARK-AUTO-01
                'v_verify' => 'Y',
                'verification_source' => $source,
                'verification_reason' => $decision['reason'],
                'is_manually_edited' => 0,
            ]);

            // If Travel (ClaimId 7), update 24_wheeler_entry if present
            if ((int) $claim->ClaimId === 7) {
                $wheelerTable = "y{$yearId}_24_wheeler_entry";
                $cacheKey = "{$conn}_{$wheelerTable}";
                if (!isset($this->tableExistsCache[$cacheKey])) {
                    $this->tableExistsCache[$cacheKey] = DB::connection($conn)->getSchemaBuilder()->hasTable($wheelerTable);
                }
                if ($this->tableExistsCache[$cacheKey]) {
                    DB::connection($conn)->table($wheelerTable)->where('ExpId', $expId)->update([
                        'Totalkm' => $decision['metadata']['total_km'] ?? $claim->TotKm,
                    ]);
                }
            }

            // Populate y{yearId}_expenseclaimsdetails to guarantee consistency with manual verifier flow
            $detailsTable = "y{$yearId}_expenseclaimsdetails";
            $cacheKey = "{$conn}_{$detailsTable}";
            if (!isset($this->tableExistsCache[$cacheKey])) {
                $this->tableExistsCache[$cacheKey] = DB::connection($conn)->getSchemaBuilder()->hasTable($detailsTable);
            }
            if ($this->tableExistsCache[$cacheKey]) {
                DB::connection($conn)->table($detailsTable)->where('ExpId', $expId)->delete();
                DB::connection($conn)->table($detailsTable)->insert([
                    'ExpId' => $expId,
                    'Title' => 'Verified Expense',
                    'Amount' => (float) ($claim->FilledTAmt ?? 0),
                    'VerifierEditAmount' => $verifiedAmount,
                    'VerifiedDate' => $verifyDate,
                    'VerifierRemark' => \App\Support\SystemRemark::mark($decision['reason'] ?? null, 500),
                ]);
            }
        });
    }

    /**
     * Evaluate and auto-submit a monthly voucher if all constituent claims are verified.
     */
    public function verifyMonthVoucher(int $employeeId, int $month, int $yearId, int $companyId = 1, bool $dryRun = false): array
    {
        $conn = DatabaseSwitcher::getExpenseConnection($companyId);
        $claimTable = ExpenseClaim::tableName($yearId);
        $monthTable = MonthExpenseFinal::tableName($yearId);

        if (!DB::connection($conn)->getSchemaBuilder()->hasTable($claimTable) ||
            !DB::connection($conn)->getSchemaBuilder()->hasTable($monthTable)) {
            return ['status' => 'skipped', 'message' => 'Table not found'];
        }

        // Get month voucher
        $monthRecord = DB::connection($conn)->table($monthTable)
            ->where('EmployeeID', $employeeId)
            ->where('Month', $month)
            ->where('YearId', $yearId)
            ->first();

        if (!$monthRecord) {
            return ['status' => 'skipped', 'message' => 'Month voucher not found'];
        }

        $settings = AutoVerifySetting::getSettingsForCompany($companyId);
        if (!$settings->auto_submit_month) {
            return ['status' => 'skipped', 'message' => 'Month auto-submission disabled in Admin Settings'];
        }

        // Check courier requirement
        if ($settings->courier_check_required) {
            $courierReceived = !empty($monthRecord->RecevingDate) && \App\Support\ZeroDate::has($monthRecord->RecevingDate);
            // If company is VVNR (11), verify courier receipt
            if ($companyId === 11 && !$courierReceived) {
                return ['status' => 'held_courier', 'message' => 'Courier document pending for VVNR settlement'];
            }
        }

        // Count total active submitted claims for this month (excluding Draft & Deactivate)
        $totalClaims = DB::connection($conn)->table($claimTable)
            ->where('CrBy', $employeeId)
            ->where('ClaimMonth', $month)
            ->whereNotIn('ClaimStatus', ['Deactivate', 'Draft'])
            ->count();

        if ($totalClaims === 0) {
            return ['status' => 'skipped', 'message' => 'No active claims for this month'];
        }

        // Count verified claims
        $verifiedClaims = DB::connection($conn)->table($claimTable)
            ->where('CrBy', $employeeId)
            ->where('ClaimMonth', $month)
            ->where('ClaimStatus', 'Verified')
            ->whereNotIn('ClaimStatus', ['Deactivate', 'Draft'])
            ->count();

        if ($verifiedClaims < $totalClaims) {
            return [
                'status' => 'partial',
                'message' => "Partially verified ({$verifiedClaims}/{$totalClaims} claims verified)",
                'verified_count' => $verifiedClaims,
                'total_count' => $totalClaims,
            ];
        }

        // ODO-DUP-01: a duplicate odometer claim is never counted in the verified total
        if (!$dryRun && config('odo_duplicates.on_submit', true)) {
            app(\App\Services\Claims\DuplicateOdometer::class)->resolve($conn, (int) $yearId, (int) $employeeId, (int) $month, true, 'auto_verify');
        }

        // 100% of claims are verified -> auto-submit month
        $totalVerifiedAmount = (float) DB::connection($conn)->table($claimTable)
            ->where('CrBy', $employeeId)
            ->where('ClaimMonth', $month)
            ->where('ClaimStatus', 'Verified')
            ->whereNotIn('ClaimStatus', ['Deactivate', 'Draft'])
            ->sum('VerifyTAmt');

        if (!$dryRun) {
            $nowDate = now()->format('Y-m-d');
            DB::connection($conn)->table($monthTable)
                ->where('EmployeeID', $employeeId)
                ->where('Month', $month)
                ->where('YearId', $yearId)
                ->update([
                    'Status' => 'Closed',
                    'Verified_Date' => $nowDate,
                    'Verified_Amount' => $totalVerifiedAmount,
                    'Approved_Amount' => $totalVerifiedAmount, // Prepared for approver review
                ]);

            // Audit in claim action logs
            try {
                ClaimActionLogService::logSubmit($employeeId, $month, 'Verified', 'Approved', 'Auto-Verifier');
            } catch (\Exception $e) {
                Log::warning("ClaimActionLog submit failed: " . $e->getMessage());
            }
        }

        return [
            'status' => 'submitted',
            'message' => 'Month fully verified and submitted to Approver',
            'verified_amount' => $totalVerifiedAmount,
            'total_claims' => $totalClaims,
        ];
    }

    /**
     * Batch verify all pending claims for a company and financial year.
     */
    public function batchVerifyPending(
        int $companyId = 1,
        ?int $yearId = null,
        array $filters = [],
        bool $dryRun = false
    ): array {
        $yearId = $yearId ?? \App\Support\CurrentYear::id();
        $conn = DatabaseSwitcher::getExpenseConnection($companyId);
        $claimTable = ExpenseClaim::tableName($yearId);

        $cacheKey = "{$conn}_{$claimTable}";
        if (!isset($this->tableExistsCache[$cacheKey])) {
            $this->tableExistsCache[$cacheKey] = DB::connection($conn)->getSchemaBuilder()->hasTable($claimTable);
        }
        if (!$this->tableExistsCache[$cacheKey]) {
            return ['success' => false, 'message' => "Table {$claimTable} does not exist on {$conn}"];
        }

        $query = DB::connection($conn)->table($claimTable)
            ->whereIn('ClaimStatus', ['Submitted', 'Filled'])
            ->whereNotIn('ClaimStatus', ['Deactivate', 'Draft'])
            ->where(function ($q) {
                $q->whereNull('v_verify')->orWhere('v_verify', '!=', 'Y');
            })
            ->where('AttachTo', 0);

        if (!empty($filters['month'])) {
            $query->where('ClaimMonth', (int) $filters['month']);
        }

        if (!empty($filters['employee_id'])) {
            $query->where('CrBy', (int) $filters['employee_id']);
        }

        if (!empty($filters['claim_id'])) {
            $query->where('ClaimId', (int) $filters['claim_id']);
        }

        $settings = AutoVerifySetting::getSettingsForCompany($companyId);
        $batchSize = (int) ($settings->batch_size ?? 250);
        $summary = [
            'total_evaluated' => 0,
            'approved' => 0,
            'adjusted' => 0,
            'flagged' => 0,
            'skipped' => 0,
            'approved_amount' => 0.0,
            'adjusted_amount' => 0.0,
            'claims' => [],
        ];

        $maxLimit = !empty($filters['limit']) ? (int) $filters['limit'] : null;
        if ($maxLimit && $batchSize > $maxLimit) {
            $batchSize = $maxLimit;
        }

        $query->orderBy('ExpId')->chunk($batchSize, function ($claims) use (&$summary, $yearId, $companyId, $dryRun, $maxLimit) {
            foreach ($claims as $claim) {
                if ($maxLimit && $summary['total_evaluated'] >= $maxLimit) {
                    return false;
                }
                $result = $this->verifyClaim($claim, $yearId, $companyId, $dryRun);
                $summary['total_evaluated']++;
                $status = $result['status'];

                if (isset($summary[$status])) {
                    $summary[$status]++;
                }

                if ($status === 'approved') {
                    $summary['approved_amount'] += $result['verified_amount'];
                } elseif ($status === 'adjusted') {
                    $summary['adjusted_amount'] += $result['verified_amount'];
                }

                // Keep detail for small sets or samples
                if (count($summary['claims']) < 200) {
                    $summary['claims'][] = [
                        'exp_id' => $claim->ExpId,
                        'claim_id' => $claim->ClaimId,
                        'employee_id' => $claim->CrBy,
                        'bill_date' => $claim->BillDate,
                        'claimed_amount' => (float) ($claim->FilledTAmt ?? $claim->Amount ?? 0),
                        'verified_amount' => $result['verified_amount'],
                        'channel' => $result['channel'],
                        'status' => $result['status'],
                        'reason' => $result['reason'],
                    ];
                }
            }
        });

        return array_merge(['success' => true], $summary);
    }

    protected function result(
        string $status,
        float $verifiedAmount,
        string $reason,
        string $channel = 'green',
        ?int $expId = null,
        array $metadata = []
    ): array {
        return [
            'exp_id' => $expId,
            'status' => $status,
            'channel' => $channel,
            'verified_amount' => $verifiedAmount,
            'reason' => $reason,
            'metadata' => $metadata,
        ];
    }
}
