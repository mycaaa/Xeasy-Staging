<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

class AutoVerifySetting extends Model
{
    protected $table = 'auto_verify_settings';

    protected $fillable = [
        'company_id',
        'is_enabled',
        'auto_submit_month',
        'courier_check_required',
        'batch_size',
        'gps_tolerance_pct',
        'ocr_tolerance_pct',
        'ocr_tolerance_flat',
        'max_auto_verify_amount',
        'rules',
        'updated_by',
    ];

    protected $casts = [
        'company_id' => 'integer',
        'is_enabled' => 'boolean',
        'auto_submit_month' => 'boolean',
        'courier_check_required' => 'boolean',
        'batch_size' => 'integer',
        'gps_tolerance_pct' => 'float',
        'ocr_tolerance_pct' => 'float',
        'ocr_tolerance_flat' => 'float',
        'max_auto_verify_amount' => 'float',
        'rules' => 'array',
    ];

    /**
     * Get settings for a specific company, falling back to global default (company_id = 0).
     */
    public static function getSettingsForCompany(?int $companyId = null): self
    {
        $companyId = (int) ($companyId ?? 0);
        $cacheKey = "auto_verify_settings_company_{$companyId}";

        return Cache::remember($cacheKey, 300, function () use ($companyId) {
            // First look for company-specific row
            $setting = self::where('company_id', $companyId)->first();

            // If not found and not company 0, fall back to global
            if (!$setting && $companyId !== 0) {
                $setting = self::where('company_id', 0)->first();
            }

            // If still not found, return new instance with defaults
            if (!$setting) {
                $setting = new self([
                    'company_id' => $companyId,
                    'is_enabled' => (bool) config('autoverify.enabled', true),
                    'auto_submit_month' => (bool) config('autoverify.auto_submit_month_if_all_verified', true),
                    'courier_check_required' => (bool) config('autoverify.courier_required_check', true),
                    'batch_size' => (int) config('autoverify.batch_size', 250),
                    'gps_tolerance_pct' => (float) config('autoverify.gps_tolerance_percent', 15.0),
                    'ocr_tolerance_pct' => (float) config('autoverify.ocr_tolerance_percent', 5.0),
                    'ocr_tolerance_flat' => (float) config('autoverify.ocr_tolerance_flat', 5.0),
                    'max_auto_verify_amount' => 10000.0,
                    'rules' => [],
                ]);
            }

            return $setting;
        });
    }

    /**
     * Clear cached settings.
     */
    public static function clearCache(?int $companyId = null): void
    {
        if ($companyId !== null) {
            Cache::forget("auto_verify_settings_company_{$companyId}");
        } else {
            Cache::forget('auto_verify_settings_company_0');
            Cache::forget('auto_verify_settings_company_1');
            Cache::forget('auto_verify_settings_company_3');
            Cache::forget('auto_verify_settings_company_11');
        }
    }

    /**
     * Get rule dictionary for a specific claim type id.
     */
    public function getRuleForClaimType(int $claimId): array
    {
        $rules = $this->rules ?? [];
        $key = (string) $claimId;

        // Base defaults from category
        $base = match ($claimId) {
            7 => $this->getOdometerSettings(),
            19, 20 => $this->getDaSettings(),
            1 => $this->getLodgingSettings(),
            17 => $this->getMealSettings(),
            2, 14, 15, 3, 4, 18, 43 => $this->getTransitSettings(),
            10, 11, 12, 13, 22, 23, 24, 25, 26, 27, 28, 29, 30, 31, 32, 33, 34, 35, 36, 37, 38, 39, 40, 41, 42 => $this->getCampaignSettings(),
            default => $this->getGeneralBillSettings(),
        };

        if (isset($rules[$key]) && is_array($rules[$key])) {
            return array_merge($base, $rules[$key]);
        }

        return $base;
    }

    /**
     * Odometer & Vehicle Running (Claim 7) settings.
     */
    public function getOdometerSettings(): array
    {
        $defaults = [
            'enabled' => true,
            'check_with_gps' => true, // ON / OFF for GPS comparison
            'gps_tolerance_pct' => $this->gps_tolerance_pct ?? 15.0,
            'missing_gps_action' => 'pass', // pass (allow if odo valid) or flag
            'excess_gps_action' => 'flag', // flag, cap, reject
            'require_opening_photo' => false,
            'require_closing_photo' => false,
            'require_attachment' => false,
            'continuity_check' => false,
            'monthly_km_limit_check' => true, // 1200 KM threshold check
            'max_daily_km' => 400.0,
            'action_over_limit' => 'cap', // when claimed > rate * km: cap, flag, reject
            'max_amount' => 15000.0,
        ];

        $stored = $this->rules['odometer'] ?? ($this->rules['7'] ?? []);
        return array_merge($defaults, is_array($stored) ? $stored : []);
    }

    /**
     * Daily Allowance (Claims 19, 20) settings.
     */
    public function getDaSettings(): array
    {
        $defaults = [
            'enabled' => true,
            'check_grade_limit' => true,
            'action_over_limit' => 'cap', // cap, flag, reject
            'check_attendance' => false, // cross-check ESS attendance status
            'require_attachment' => false,
            'max_amount' => 3000.0,
        ];

        $stored = $this->rules['da'] ?? [];
        return array_merge($defaults, is_array($stored) ? $stored : []);
    }

    /**
     * Lodging & Hotel (Claim 1) settings.
     */
    public function getLodgingSettings(): array
    {
        $defaults = [
            'enabled' => true,
            'check_city_category' => true,
            'action_over_limit' => 'flag', // flag, cap, reject
            'require_attachment' => true,
            'check_stay_dates' => true,
            'max_amount' => 10000.0,
        ];

        $stored = $this->rules['lodging'] ?? ($this->rules['1'] ?? []);
        return array_merge($defaults, is_array($stored) ? $stored : []);
    }

    /**
     * Meals & Group Meals (Claim 17) settings.
     */
    public function getMealSettings(): array
    {
        $defaults = [
            'enabled' => true,
            'check_headcount_limit' => true,
            'action_over_limit' => 'cap', // cap, flag, reject
            'require_attachment' => false,
            'max_amount' => 5000.0,
        ];

        $stored = $this->rules['meals'] ?? ($this->rules['17'] ?? []);
        return array_merge($defaults, is_array($stored) ? $stored : []);
    }

    /**
     * Transit & Fares (Air, Rail, Bus, Conveyance) settings.
     */
    public function getTransitSettings(): array
    {
        $defaults = [
            'enabled' => true,
            'check_grade_entitlement' => true,
            'action_unentitled' => 'flag', // flag, reject
            'action_over_limit' => 'flag',
            'require_attachment' => true,
            'max_amount' => 15000.0,
        ];

        $stored = $this->rules['transit'] ?? [];
        return array_merge($defaults, is_array($stored) ? $stored : []);
    }

    /**
     * General Bills & Invoices settings.
     */
    public function getGeneralBillSettings(): array
    {
        $defaults = [
            'enabled' => true,
            'check_with_ocr' => true, // ON / OFF for OCR scanned bill comparison
            'ocr_tolerance_pct' => $this->ocr_tolerance_pct ?? 5.0,
            'ocr_tolerance_flat' => $this->ocr_tolerance_flat ?? 5.0,
            'action_ocr_mismatch' => 'flag', // flag, cap, reject
            'action_over_limit' => 'flag',
            'require_attachment' => true,
            'max_amount' => 5000.0,
        ];

        $stored = $this->rules['general_bills'] ?? ($this->rules['default'] ?? []);
        return array_merge($defaults, is_array($stored) ? $stored : []);
    }

    /**
     * Campaigns, Field Days & Meetings settings.
     */
    public function getCampaignSettings(): array
    {
        $defaults = [
            'enabled' => true,
            'require_attachment' => true,
            'action_over_limit' => 'flag',
            'max_amount' => 25000.0,
        ];

        $stored = $this->rules['campaigns'] ?? [];
        return array_merge($defaults, is_array($stored) ? $stored : []);
    }

    /**
     * Base default rule structure.
     */
    public function getDefaultRuleStructure(): array
    {
        return [
            'enabled' => true,
            'action_over_limit' => 'flag', // cap, flag, reject
            'require_attachment' => false,
            'max_amount' => $this->max_auto_verify_amount ?? 10000.0,
        ];
    }

    /**
     * Friendly company name attribute.
     */
    public function getCompanyNameAttribute(): string
    {
        return match ($this->company_id) {
            0 => 'Global Defaults (All Companies)',
            1 => 'VNR Seeds',
            3 => 'VNR Nursery',
            11 => 'VVNR Seeds',
            default => "Company {$this->company_id}",
        };
    }
}
