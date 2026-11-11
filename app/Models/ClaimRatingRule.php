<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ClaimRatingRule extends Model
{
    protected $table = 'claim_rating_rules';

    protected $fillable = [
        'company_id',
        'stage_key',
        'stage_label',
        'responsible_role',
        'star_5_max_days',
        'star_4_max_days',
        'star_3_max_days',
        'star_2_max_days',
        'star_1_min_days',
        'min_claims',
        'sort_order',
        'effective_date',
        'is_active',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'star_5_max_days' => 'integer',
        'star_4_max_days' => 'integer',
        'star_3_max_days' => 'integer',
        'star_2_max_days' => 'integer',
        'star_1_min_days' => 'integer',
    ];

    // ── VNR Stages (Full lifecycle) ──
    // Bill → Upload → Punch → Submit → Courier Post → Courier Receive → Verify → Approve → Finance → Payment
    public const VNR_STAGES = [
        'bill_to_upload' => ['label' => 'Bill Date to Claim Upload', 'role' => 'employee', 'sort' => 1, 'type' => 'days'],
        'upload_to_punch' => ['label' => 'Claim Upload to Data Punch', 'role' => 'mediator', 'sort' => 2, 'type' => 'days'],
        'punch_to_submit' => ['label' => 'Final Submission (by date of next month)', 'role' => 'employee', 'sort' => 3, 'type' => 'date_range'],
        'submit_to_courier_post' => ['label' => 'Final Submission to Courier Posting', 'role' => 'employee', 'sort' => 4, 'type' => 'days'],
        'courier_post_to_receive' => ['label' => 'Courier Posting to Courier Receive', 'role' => 'courier', 'sort' => 5, 'type' => 'days'],
        'receive_to_verify' => ['label' => 'Courier Receive to Verification', 'role' => 'verifier', 'sort' => 6, 'type' => 'days'],
        'verify_to_approve' => ['label' => 'Verification to Approval', 'role' => 'approver', 'sort' => 7, 'type' => 'days'],
        'approve_to_finance' => ['label' => 'Approval to Finance', 'role' => 'finance', 'sort' => 8, 'type' => 'days'],
        'finance_to_payment' => ['label' => 'Finance to Payment', 'role' => 'finance', 'sort' => 9, 'type' => 'days'],
    ];

    // ── VVNR Stages (Full lifecycle — different order after submission) ──
    // Bill → Upload → Punch → Submit → Approve → Finance → Payment → Courier Post → Courier Receive → Verify
    public const VVNR_STAGES = [
        'bill_to_upload' => ['label' => 'Bill Date to Claim Upload', 'role' => 'employee', 'sort' => 1, 'type' => 'days'],
        'upload_to_punch' => ['label' => 'Claim Upload to Data Punch', 'role' => 'mediator', 'sort' => 2, 'type' => 'days'],
        'punch_to_submit' => ['label' => 'Final Submission (by date of next month)', 'role' => 'employee', 'sort' => 3, 'type' => 'date_range'],
        'submit_to_approve' => ['label' => 'Final Submission to Approval', 'role' => 'approver', 'sort' => 4, 'type' => 'days'],
        'approve_to_finance' => ['label' => 'Approval to Finance', 'role' => 'finance', 'sort' => 5, 'type' => 'days'],
        'finance_to_payment' => ['label' => 'Finance to Payment', 'role' => 'finance', 'sort' => 6, 'type' => 'days'],
        'payment_to_courier_post' => ['label' => 'Payment to Courier Posting', 'role' => 'employee', 'sort' => 7, 'type' => 'days'],
        'courier_post_to_receive' => ['label' => 'Courier Posting to Courier Receive', 'role' => 'courier', 'sort' => 8, 'type' => 'days'],
        'receive_to_verify' => ['label' => 'Courier Receive to Verification', 'role' => 'verifier', 'sort' => 9, 'type' => 'days'],
    ];

    /**
     * Get stages for the current company
     */
    public static function getStagesForCompany(?int $companyId = null): array
    {
        $companyId = $companyId ?? (int) session('company_id', 1);
        return $companyId == 11 ? self::VVNR_STAGES : self::VNR_STAGES;
    }

    /**
     * Get active rules for a company
     */
    public static function getRulesForCompany(?int $companyId = null)
    {
        $companyId = $companyId ?? (int) session('company_id', 1);
        return self::where('company_id', $companyId)
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->get();
    }

    /**
     * Calculate star rating based on days taken (for 'days' type)
     * OR based on day-of-month submitted (for 'date_range' type)
     */
    public function calculateRating(int $value): int
    {
        if ($value <= $this->star_5_max_days) return 5;
        if ($value <= $this->star_4_max_days) return 4;
        if ($value <= $this->star_3_max_days) return 3;
        if ($value <= $this->star_2_max_days) return 2;
        return 1;
    }

    /**
     * Check if this stage uses date_range logic
     */
    public function isDateRangeType(): bool
    {
        $stages = self::getStagesForCompany($this->company_id);
        return ($stages[$this->stage_key]['type'] ?? 'days') === 'date_range';
    }

    /**
     * Get star color for display
     */
    public static function getStarColor(int $stars): string
    {
        return match ($stars) {
            5 => '#4CAF50',  // Green
            4 => '#8BC34A',  // Light Green
            3 => '#FFC107',  // Yellow
            2 => '#FF9800',  // Orange
            1 => '#F44336',  // Red
            default => '#9E9E9E',
        };
    }
}
