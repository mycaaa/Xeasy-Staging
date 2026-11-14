<?php

namespace App\Services\ClaimRules;

use App\Services\Odometer\BackdateApproval as BA;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

/**
 * LATE-PUNCH-01 (13 Nov 2026): back-dated bills.
 *
 * A bill may always be uploaded (any day of the current financial year up to today). A bill uploaded after its
 * month's due date (the submission cut-off: last_submission_day of the following month, Settings > Submission
 * settings) is not data-punched until it is approved:
 *   - VNR / Nursery: by the GM (BU head of the claimant's BU, or the BU head / HOD set for the department in the
 *     back-date settings) or by an admin;
 *   - VVNR: by an admin only.
 * The admin's existing "Allow submit" for the employee's month (SubPermission = 'Y') also counts as approval.
 * Approval is stored on the claim (Backdate_Odometer_Status 'A' / 'R', By, Date, Backdate_Final_Remark), the same
 * columns the odometer back-date approval uses. Odometer claims keep their own back-date rule.
 */
class LateClaim
{
    /** End of the due date of a claim month, or null when no cut-off is set. */
    public static function dueDate(int $companyId, int $yearId, int $month, ?string $conn = null): ?Carbon
    {
        if (! config('claim_rules.late_punch.enabled', true) || $month < 1 || $month > 12) {
            return null;
        }
        $day = (int) config('claim_rules.cutoff.day', 0);
        if ($day <= 0) {
            try {
                $day = (int) (\App\Models\SubmissionSetting::where('company_id', $companyId)->where('is_active', true)->value('last_submission_day') ?? 0);
            } catch (\Throwable $e) {
                $day = 0;
            }
        }
        if ($day < 1 || $day > 28) {
            return null;
        }
        $start = app(ClaimSubmissionValidator::class)->fyStartYear($yearId, $conn);
        if ($start <= 0) {
            return null;
        }
        $calYear = $month >= 4 ? $start : $start + 1;

        return Carbon::create($calYear, $month, 1, 0, 0, 0, 'Asia/Kolkata')->addMonthNoOverflow()->setDay($day)->endOfDay();
    }

    /** Uploaded after the due date of its month (odometer claims excluded). */
    public static function isLate(object $claim, int $companyId, int $yearId, ?string $conn = null): bool
    {
        // LATE-PUNCH-02 (14 Nov 2026, management): GM approval is for back-dated odometer punching only (Sales), never for
        // other bills. Hard rule in code, so a cached configuration or an old .env value cannot bring the block back.
        if (! config('claim_rules.late_punch.force_enable', false)) {
            return false;
        }
        if ((int) ($claim->ClaimId ?? 0) === 7 || empty($claim->CrDate) || (int) ($claim->ClaimMonth ?? 0) <= 0) {
            return false;
        }
        $due = self::dueDate($companyId, $yearId, (int) $claim->ClaimMonth, $conn);

        return $due !== null && Carbon::parse(substr((string) $claim->CrDate, 0, 10), 'Asia/Kolkata')->endOfDay()->greaterThan($due);
    }

    /** null when the claim may be punched, otherwise the reason it may not. */
    public static function punchBlock(string $conn, int $yearId, int $expId, int $companyId): ?string
    {
        if ($expId <= 0) {
            return null;
        }
        $c = DB::connection($conn)->table("y{$yearId}_expenseclaims")->where('ExpId', $expId)
            ->first(['ExpId', 'CrBy', 'ClaimId', 'ClaimMonth', 'CrDate', 'Backdate_Odometer_Status']);
        if (! $c || ! self::isLate($c, $companyId, $yearId, $conn)) {
            return null;
        }
        if (($c->Backdate_Odometer_Status ?? null) === 'A' || self::monthAllowed($conn, $yearId, (int) $c->CrBy, (int) $c->ClaimMonth)) {
            return null;
        }
        $due = self::dueDate($companyId, $yearId, (int) $c->ClaimMonth, $conn);
        $who = $companyId === 11 ? 'an admin' : 'the GM or an admin';
        if (($c->Backdate_Odometer_Status ?? null) === 'R') {
            return "This back-dated bill was rejected (uploaded after the due date {$due->format('d/m/Y')}). It cannot be punched.";
        }

        return "Back-dated bill: uploaded on ".date('d/m/Y', strtotime($c->CrDate))." after the due date {$due->format('d/m/Y')} of its month. "
            ."It can be punched after approval by {$who} (Late Bill Approval).";
    }

    /** The admin allowed a late submission for the employee's month. */
    public static function monthAllowed(string $conn, int $yearId, int $empId, int $month): bool
    {
        try {
            return DB::connection($conn)->table("y{$yearId}_monthexpensefinal")->where('EmployeeID', $empId)->where('Month', $month)
                ->where('YearId', $yearId)->where('SubPermission', 'Y')->exists();
        } catch (\Throwable $e) {
            return false;
        }
    }

    public static function isAdmin($user): bool
    {
        return $user && method_exists($user, 'hasAnyRole') && $user->hasAnyRole(['Super Admin', 'Admin']);
    }

    /** May $approverEmp (with web $user) approve late bills of $claimantId in $companyId? */
    public static function canApprove($user, int $approverEmp, int $claimantId, int $companyId, string $conn): bool
    {
        if (self::isAdmin($user)) {
            return true;
        }
        if ($companyId === 11 || $approverEmp <= 0 || $approverEmp === $claimantId) {
            return false; // VVNR: admin only; nobody approves their own bill
        }
        $g = BA::general($claimantId);

        return ($g && BA::isBuGeneralManager($approverEmp, (int) $g->BUId)) || BA::isFinalApproverFor($conn, $approverEmp, $claimantId);
    }

    /** Record the decision on the claim. */
    public static function decide(string $conn, int $yearId, int $expId, string $decision, int $by, string $remark): void
    {
        $table = "y{$yearId}_expenseclaims";
        $upd = ['Backdate_Odometer_Status' => $decision, 'Backdate_Odometer_By' => $by, 'Backdate_Odometer_Date' => date('Y-m-d')];
        if (Schema::connection($conn)->hasColumn($table, 'Backdate_Final_Remark')) {
            $upd['Backdate_Final_Remark'] = mb_substr($remark, 0, 500);
        }
        DB::connection($conn)->table($table)->where('ExpId', $expId)->update($upd);
        Log::info('LATE-PUNCH-01 '.($decision === 'A' ? 'approved' : 'rejected'), ['exp' => $expId, 'by' => $by, 'conn' => $conn, 'remark' => mb_substr($remark, 0, 200)]);
    }
}
