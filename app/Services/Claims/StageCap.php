<?php

namespace App\Services\Claims;

use Illuminate\Database\ConnectionInterface;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

/**
 * Q-02 (audit, stage caps): a verifier may not verify more than was claimed, and an approver may
 * not approve more than was verified (or claimed, when the claim is not verified yet), unless a
 * real reason is written in the remark. Every raise that is let through is logged in
 * claim_amount_change_logs (field "stage_cap_override"), so Finance can review it.
 *
 * Config: claim_rules.stage_caps (enabled, mode enforce|log, tolerance in rupees).
 */
class StageCap
{
    /** Remarks that are not a reason. */
    private const EMPTY_REMARKS = ['', 'ok', 'okay', 'approved', 'verified', 'done', 'na', 'n/a', '-', '.', 'yes'];

    /**
     * The amount the new figure may not exceed: the claimed amount for "verify", the verified
     * amount (or claimed, if not verified) for "approve". Null when there is nothing to compare.
     */
    public static function basis(object $claim, string $stage): ?float
    {
        $claimed = (float) ($claim->FilledTAmt ?? 0);
        $verified = (float) ($claim->VerifyTAmt ?? 0);
        if ($stage === 'approve') {
            // APPR-CAP-02 (14 Nov 2026): the claimed amount is the ceiling for the approver. The approver may raise a
            // reduced verified amount back up to the claimed amount, never above it, even when the verifier's figure is
            // higher (a verifier typo of 15,000 on a 1,500 bill let 15,000 through). Verified only when nothing was claimed.
            $b = $claimed > 0 ? $claimed : $verified;
        } else {
            $b = $claimed;
        }

        return $b > 0 ? $b : null;
    }

    public static function hasReason(?string $remark): bool
    {
        $r = strtolower(trim((string) $remark));

        return mb_strlen($r) >= 3 && ! in_array($r, self::EMPTY_REMARKS, true);
    }

    /**
     * Null when the amount is allowed; otherwise the message to show. An allowed raise (with a
     * reason, or in log mode) is logged.
     *
     * @param  string  $stage  "verify" | "approve"
     */
    public static function check($conn, int $yearId, object $claim, $newAmount, string $stage, ?string $remark, string $source): ?string
    {
        if (! config('claim_rules.stage_caps.enabled', true)) {
            return null;
        }
        $basis = self::basis($claim, $stage);
        $new = (float) $newAmount;
        $tol = (float) config('claim_rules.stage_caps.tolerance', 1);
        // APPR-CAP-01 (13 Nov 2026): the approver can keep or lower the amount, never raise it, reason or not:
        // the ceiling is the verified amount (the verifier may correct a claim up to the policy rate), or the
        // claimed amount when the claim is not verified
        // APPR-CAP-03 (14 Nov 2026): no rounding allowance on the approver's ceiling: 1,001 against a 1,000 claim is refused
        if ($stage === 'approve' && $basis !== null && $new > $basis + 0.005
            && config('claim_rules.stage_caps.approve_never_above_claimed', true)) {
            self::log($conn, $yearId, $claim, $basis, $new, $stage, $remark, $source, 'refused_above_basis');
            $what = ((float) ($claim->FilledTAmt ?? 0)) > 0 ? 'claimed' : 'verified';

            return 'The approved amount ('.number_format($new, 2).') cannot be more than the '.$what.' amount ('
                .number_format($basis, 2).'). The approver can raise the verified amount up to the claimed amount, not beyond.';
        }
        // APPR-CAP-02: the verifier cannot go above the claimed amount either; a remark does not unlock it any more
        // (a remark such as "approve" counted as a reason). Policy corrections above the bill go through a new claim line.
        if ($stage === 'verify' && $basis !== null && $new > $basis + $tol
            && config('claim_rules.stage_caps.verify_never_above_claimed', true)) {
            self::log($conn, $yearId, $claim, $basis, $new, $stage, $remark, $source, 'refused_above_basis');

            return 'The verified amount ('.number_format($new, 2).') cannot be more than the claimed amount ('
                .number_format($basis, 2).').';
        }
        // APPR-RAISE-01: a raise above the verified amount (within the claimed amount) is allowed and logged for Finance
        if ($stage === 'approve' && $basis !== null && (float) ($claim->VerifyTAmt ?? 0) > 0 && $new > (float) $claim->VerifyTAmt + $tol) {
            self::log($conn, $yearId, $claim, (float) $claim->VerifyTAmt, $new, $stage, $remark, $source, 'approver_raised_within_claimed');

            return null;
        }
        if ($basis === null || $new <= $basis + $tol) {
            return null;
        }
        $what = $stage === 'approve'
            ? (((float) ($claim->VerifyTAmt ?? 0)) > 0 ? 'verified' : 'claimed')
            : 'claimed';
        $label = $stage === 'approve' ? 'approved' : 'verified';

        if (self::hasReason($remark)) {
            self::log($conn, $yearId, $claim, $basis, $new, $stage, $remark, $source, 'allowed_with_reason');

            return null;
        }
        if (config('claim_rules.stage_caps.mode', 'enforce') === 'log') {
            self::log($conn, $yearId, $claim, $basis, $new, $stage, $remark, $source, 'log_only');

            return null;
        }

        return 'The '.$label.' amount ('.number_format($new, 2).') is more than the '.$what.' amount ('
            .number_format($basis, 2).'). Please enter the reason in the remark, or reduce the amount.';
    }

    /**
     * PAY-REMARK-01 (Finance decision 7 Nov 2026): a VNR approver who approves LESS than the verified
     * amount (or claimed, when not verified) must give the reason in the remark. VVNR is not covered.
     * Null when allowed; otherwise the message to show.
     */
    public static function checkCut($conn, int $yearId, object $claim, $newAmount, ?string $remark, string $source, ?int $companyId = null): ?string
    {
        if (! config('claim_rules.stage_caps.cut_needs_remark', true)) {
            return null;
        }
        $connName = $conn instanceof ConnectionInterface ? $conn->getName() : (string) $conn;
        if ((int) $companyId === 11 || str_contains(strtolower($connName), 'vvnr')) {
            return null;
        }
        // a cut is measured against what the verifier passed (else the claimed amount), capped at the claimed amount
        $claimed = (float) ($claim->FilledTAmt ?? 0);
        $verified = (float) ($claim->VerifyTAmt ?? 0);
        $basis = $verified > 0 ? ($claimed > 0 ? min($verified, $claimed) : $verified) : ($claimed > 0 ? $claimed : null);
        $new = (float) $newAmount;
        $tol = (float) config('claim_rules.stage_caps.tolerance', 1);
        if ($basis === null || $new >= $basis - $tol || self::hasReason($remark)) {
            return null;
        }
        if (config('claim_rules.stage_caps.mode', 'enforce') === 'log') {
            self::log($conn, $yearId, $claim, $basis, $new, 'approve', $remark, $source, 'cut_no_reason_log_only');

            return null;
        }
        $what = ((float) ($claim->VerifyTAmt ?? 0)) > 0 ? 'verified' : 'claimed';

        return 'The approved amount ('.number_format($new, 2).') is less than the '.$what.' amount ('
            .number_format($basis, 2).'). Please enter the reason for the cut in the remark.';
    }

    /**
     * APPR-CAP-02: the web approver screens forward the approval to the claim API, which may be another server whose
     * rules are older. Check the cap here first, on this app's own copy of the claim. Null when allowed.
     */
    public static function webApproveGuard(?string $conn, int $yearId, int $expId, $amount, ?string $remark, string $source): ?string
    {
        if ($expId <= 0 || ! is_numeric($amount)) {
            return null;
        }
        try {
            $conn = $conn ?: (string) session('expense_connection', 'expense');
            $claim = DB::connection($conn)->table("y{$yearId}_expenseclaims")->where('ExpId', $expId)
                ->first(['ExpId', 'CrBy', 'FilledTAmt', 'VerifyTAmt']);
        } catch (\Throwable $e) {
            return null;
        }

        return $claim ? self::check($conn, $yearId, $claim, $amount, 'approve', $remark, $source) : null;
    }

    /**
     * APPR-CAP-02: claims of an employee's month whose approved (or, not yet approved, verified) amount is above the
     * claimed amount. The month submit refuses while any is left. Returns [ExpId => message].
     */
    public static function monthOverClaimed($conn, int $yearId, int $empId, int $month): array
    {
        if (! config('claim_rules.stage_caps.enabled', true)) {
            return [];
        }
        $tol = (float) config('claim_rules.stage_caps.tolerance', 1);
        $db = $conn instanceof ConnectionInterface ? $conn : DB::connection($conn);
        $rows = $db->table("y{$yearId}_expenseclaims")->where('CrBy', $empId)->where('ClaimMonth', $month)->where('ClaimYearId', $yearId)
            ->whereNotIn('ClaimStatus', ['Deactivate', 'Deactivat', 'Draft'])->where('FilledTAmt', '>', 0)
            ->where(fn ($q) => $q->whereRaw('ApprTAmt > FilledTAmt + ?', [$tol])
                ->orWhereRaw('(ApprTAmt IS NULL OR ApprTAmt = 0) AND VerifyTAmt > FilledTAmt + ?', [$tol]))
            ->get(['ExpId', 'FilledTAmt', 'VerifyTAmt', 'ApprTAmt']);
        $out = [];
        foreach ($rows as $r) {
            $a = (float) $r->ApprTAmt > 0 ? (float) $r->ApprTAmt : (float) $r->VerifyTAmt;
            $out[(int) $r->ExpId] = '#'.$r->ExpId.': '.number_format($a, 2).' is more than the claimed '.number_format((float) $r->FilledTAmt, 2);
        }

        return $out;
    }

    /** For a system step (auto-verify): never above the basis. Returns the amount to use. */
    public static function clamp($conn, int $yearId, object $claim, $newAmount, string $stage, string $source): float
    {
        $new = (float) $newAmount;
        if (! config('claim_rules.stage_caps.enabled', true)) {
            return $new;
        }
        $basis = self::basis($claim, $stage);
        if ($basis !== null && $new > $basis + (float) config('claim_rules.stage_caps.tolerance', 1)) {
            self::log($conn, $yearId, $claim, $basis, $new, $stage, 'System amount above the '.($stage === 'approve' ? 'verified' : 'claimed').' amount; capped', $source, 'capped');

            return $basis;
        }

        return $new;
    }

    private static function log($conn, int $yearId, object $claim, float $basis, float $new, string $stage, ?string $remark, string $source, string $mode): void
    {
        try {
            $db = $conn instanceof ConnectionInterface ? $conn : DB::connection($conn);
            if (! Schema::connection($db->getName())->hasTable('claim_amount_change_logs')) {
                $db = DB::connection();   // VVNR until its migration runs: log on the main database
            }
            $cols = array_flip(Schema::connection($db->getName())->getColumnListing('claim_amount_change_logs'));
            $row = [
                'year_id' => $yearId,
                'exp_id' => (int) ($claim->ExpId ?? 0),
                'employee_id' => isset($claim->CrBy) ? (int) $claim->CrBy : null,
                'source' => mb_substr($source, 0, 40),
                'field' => 'stage_cap_override',
                'old_value' => (string) $basis,
                'new_value' => (string) $new,
                'remark' => mb_substr(trim($stage.' '.$mode.': '.(string) $remark), 0, 500),
                'changed_by' => Auth::id(),
                'created_at' => now(),
                'company_id' => session('company_id'),
                'mode' => $mode,
                'actor_role' => $stage === 'approve' ? 'approver' : ($mode === 'capped' ? 'system' : 'verifier'),
            ];
            $db->table('claim_amount_change_logs')->insert(array_intersect_key($row, $cols));
        } catch (\Throwable $e) {
            Log::warning('StageCap log failed: '.$e->getMessage(), ['exp' => $claim->ExpId ?? null]);
        }
    }
}
