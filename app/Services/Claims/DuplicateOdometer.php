<?php

namespace App\Services\Claims;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * ODO-DUP-01 (Finance, 6 Nov 2026 - V1259, Sept 2026): the same odometer trip is saved twice.
 *
 * Cause: the app's opening/closing upload does "is there a claim for this date? no -> INSERT".
 * Two requests for the same trip (double tap, or the app retrying on a slow network) both see
 * "no claim" and both insert, so two running-km claims exist with the same temp_punch_id and the
 * same readings (ExpIds next to each other). Month totals then count both.
 *
 * What this class does
 *  - lock():   a named MySQL lock per employee + bill date, held across the check and the insert,
 *              so a second request waits and then finds the first claim (it updates instead).
 *  - groups(): exact duplicates - same employee, bill date, opening and closing reading, running-km
 *              claim (ClaimId 7), both live. Two real trips on one day never have the same readings.
 *  - resolve(): keeps one claim per group and sets the others to Deactivate, so no total, approval,
 *              finance or payment step counts them. The one kept is the one Finance / the verifier
 *              actually processed (highest FinancedTAmt, then VerifyTAmt, then furthest step), or the
 *              first upload if none was processed. Every change is logged (odo_duplicate_logs) with the
 *              old status and amounts so it can be reversed.
 *  - A paid month is reported only, never changed here (payment already left; Finance decides recovery).
 */
class DuplicateOdometer
{
    public const LOG_TABLE = 'odo_duplicate_logs';

    /** Run $fn while holding the per employee + date lock on $conn. */
    public static function withLock(string $conn, int $yearId, int $empId, string $billDate, callable $fn)
    {
        $db = DB::connection($conn);
        $name = 'xeasy_odo_'.$db->getDatabaseName().'_'.$yearId.'_'.$empId.'_'.$billDate;
        $name = substr($name, 0, 64);
        $got = (int) ($db->selectOne('SELECT GET_LOCK(?, 15) AS l', [$name])->l ?? 0);
        if ($got !== 1) {
            throw new \RuntimeException('Another upload for this date is still being saved. Please try again in a moment.');
        }
        try {
            return $fn();
        } finally {
            $db->select('SELECT RELEASE_LOCK(?)', [$name]);
        }
    }

    /**
     * @return array<int, array{emp: int, bill_date: string, month: int, keep: object, extras: array<object>}>
     */
    public function groups(string $conn, int $yearId, ?int $empId = null, ?int $month = null): array
    {
        $c = "y{$yearId}_expenseclaims";
        $where = "ClaimId = 7 AND ClaimStatus NOT IN ('Deactivate','Deactivat','Draft') AND odomtr_closing > 0";
        $bind = [];
        if ($empId) {
            $where .= ' AND CrBy = ?';
            $bind[] = $empId;
        }
        if ($month) {
            $where .= ' AND ClaimMonth = ?';
            $bind[] = $month;
        }
        $rows = DB::connection($conn)->select(
            "SELECT x.ExpId, x.CrBy, x.BillDate, x.ClaimMonth, x.odomtr_opening, x.odomtr_closing, x.TotKm, x.temp_punch_id,
                    x.ClaimStatus, x.ClaimAtStep, x.FilledOkay, x.FilledTAmt, x.VerifyTAmt, x.ApprTAmt, x.FinancedTAmt
               FROM {$c} x
               JOIN (SELECT CrBy, BillDate, odomtr_opening, odomtr_closing FROM {$c} WHERE {$where}
                      GROUP BY CrBy, BillDate, odomtr_opening, odomtr_closing HAVING COUNT(*) > 1) d
                 ON d.CrBy = x.CrBy AND d.BillDate = x.BillDate AND d.odomtr_opening = x.odomtr_opening AND d.odomtr_closing = x.odomtr_closing
              WHERE x.ClaimId = 7 AND x.ClaimStatus NOT IN ('Deactivate','Deactivat','Draft')
              ORDER BY x.CrBy, x.BillDate, x.ExpId",
            $bind
        );

        $out = [];
        foreach (collect($rows)->groupBy(fn ($r) => $r->CrBy.'|'.$r->BillDate.'|'.$r->odomtr_opening.'|'.$r->odomtr_closing) as $g) {
            $rank = fn ($r) => [(float) $r->FinancedTAmt > 0 ? 1 : 0, (float) $r->VerifyTAmt, (int) $r->ClaimAtStep, -(int) $r->ExpId];
            $sorted = $g->sort(fn ($a, $b) => $rank($b) <=> $rank($a))->values();
            $keep = $sorted->first();
            $out[] = [
                'emp' => (int) $keep->CrBy, 'bill_date' => $keep->BillDate, 'month' => (int) $keep->ClaimMonth,
                'keep' => $keep, 'extras' => $sorted->slice(1)->values()->all(),
            ];
        }

        return $out;
    }

    /**
     * Deactivate the extra claims of each group (unpaid months only).
     *
     * @return array{groups: int, deactivated: int, report: array<int, array>}
     */
    public function resolve(string $conn, int $yearId, ?int $empId = null, ?int $month = null, bool $apply = true, string $by = 'system'): array
    {
        $db = DB::connection($conn);
        $groups = $this->groups($conn, $yearId, $empId, $month);
        $paid = [];
        foreach ($db->select("SELECT EmployeeID, Month, Fin_PayDate, Fin_PayAmt FROM y{$yearId}_monthexpensefinal WHERE YearId = ?", [$yearId]) as $m) {
            $paid[$m->EmployeeID.'-'.$m->Month] = ! empty($m->Fin_PayDate) && \App\Support\ZeroDate::has($m->Fin_PayDate);
        }

        $report = [];
        $count = 0;
        if ($apply && $groups) {
            $this->ensureLogTable($conn);
        }
        foreach ($groups as $g) {
            $isPaid = $paid[$g['emp'].'-'.$g['month']] ?? false;
            foreach ($g['extras'] as $x) {
                $action = $isPaid ? 'report only (month paid)' : ($apply ? 'deactivated' : 'would be deactivated');
                $report[] = [
                    'employee_id' => $g['emp'], 'bill_date' => $g['bill_date'], 'month' => $g['month'],
                    'kept_exp_id' => (int) $g['keep']->ExpId, 'duplicate_exp_id' => (int) $x->ExpId,
                    'km' => (float) $x->TotKm, 'claimed' => (float) $x->FilledTAmt, 'verified' => (float) $x->VerifyTAmt,
                    'approved' => (float) $x->ApprTAmt, 'financed' => (float) $x->FinancedTAmt,
                    'month_paid' => $isPaid ? 'Yes' : 'No', 'action' => $action,
                ];
                if (! $apply || $isPaid) {
                    continue;
                }
                $db->transaction(function () use ($db, $yearId, $g, $x, $by) {
                    $db->table(self::LOG_TABLE)->insert([
                        'year_id' => $yearId, 'exp_id' => $x->ExpId, 'kept_exp_id' => $g['keep']->ExpId, 'employee_id' => $g['emp'],
                        'bill_date' => $g['bill_date'], 'old_status' => $x->ClaimStatus, 'old_claim_at_step' => $x->ClaimAtStep,
                        'old_filled' => $x->FilledTAmt, 'old_verified' => $x->VerifyTAmt, 'old_approved' => $x->ApprTAmt, 'old_financed' => $x->FinancedTAmt,
                        'done_by' => $by, 'created_at' => now(),
                    ]);
                    $db->table("y{$yearId}_expenseclaims")->where('ExpId', $x->ExpId)->update([
                        'ClaimStatus' => 'Deactivate',
                        'Remark' => DB::raw("LEFT(TRIM(CONCAT('[Duplicate of ExpId ".(int) $g['keep']->ExpId." - not counted (auto generated)] ', COALESCE(Remark,''))), 200)"),
                    ]);
                });
                $count++;
            }
        }

        return ['groups' => count($groups), 'deactivated' => $count, 'report' => $report];
    }

    public const REVIEW_TABLE = 'odo_duplicate_reviews';

    /**
     * ODO-DUP-02 (Finance decision 7 Nov 2026): a running-km claim whose readings match another live
     * claim of the same employee (any date) does not go to approval until the verifier has looked at it:
     * deactivated one of them, or marked it "not a duplicate". Same-date exact copies are removed by
     * resolve() already; this catches the same readings used again on another day.
     *
     * @param  iterable<object>  $rows  ExpId, CrBy, ClaimId, odomtr_opening, odomtr_closing
     * @return array<int, int[]>  ExpId => the other ExpIds with the same readings
     */
    public static function conflicts($conn, int $yearId, iterable $rows): array
    {
        $db = $conn instanceof \Illuminate\Database\ConnectionInterface ? $conn : DB::connection($conn);
        $out = [];
        $reviewed = null;
        foreach ($rows as $r) {
            if ((int) ($r->ClaimId ?? 0) !== 7 || (float) ($r->odomtr_closing ?? 0) <= 0 || (float) ($r->odomtr_opening ?? 0) <= 0) {
                continue;
            }
            $others = $db->table("y{$yearId}_expenseclaims")->where('ClaimId', 7)->where('CrBy', $r->CrBy)
                ->where('odomtr_opening', $r->odomtr_opening)->where('odomtr_closing', $r->odomtr_closing)
                ->where('ExpId', '!=', $r->ExpId)->whereNotIn('ClaimStatus', ['Deactivate', 'Deactivat', 'Draft'])
                ->pluck('ExpId')->map(fn ($x) => (int) $x)->all();
            if (! $others) {
                continue;
            }
            if ($reviewed === null) {
                $reviewed = Schema::connection($db->getName())->hasTable(self::REVIEW_TABLE)
                    ? $db->table(self::REVIEW_TABLE)->where('year_id', $yearId)->pluck('exp_id')->map(fn ($x) => (int) $x)->flip()->all()
                    : [];
            }
            if (isset($reviewed[(int) $r->ExpId])) {
                continue;
            }
            $others = array_values(array_filter($others, fn ($o) => ! isset($reviewed[$o])));
            if ($others) {
                $out[(int) $r->ExpId] = $others;
            }
        }

        return $out;
    }

    /** The verifier confirms the readings are genuine (e.g. vehicle not used between two days). */
    public static function markReviewed(string $conn, int $yearId, int $expId, ?int $by, string $remark): void
    {
        if (! Schema::connection($conn)->hasTable(self::REVIEW_TABLE)) {
            Schema::connection($conn)->create(self::REVIEW_TABLE, function ($t) {
                $t->id();
                $t->unsignedSmallInteger('year_id');
                $t->unsignedBigInteger('exp_id');
                $t->unsignedBigInteger('reviewed_by')->nullable();
                $t->string('remark', 300);
                $t->timestamp('created_at')->useCurrent();
                $t->unique(['year_id', 'exp_id']);
            });
        }
        DB::connection($conn)->table(self::REVIEW_TABLE)->updateOrInsert(
            ['year_id' => $yearId, 'exp_id' => $expId],
            ['reviewed_by' => $by, 'remark' => mb_substr($remark, 0, 300), 'created_at' => now()]
        );
    }

    /** Claims removed by the system (resolve), newest first - shown to the verifier. */
    public static function heldBySystem(string $conn, int $yearId, int $limit = 500)
    {
        if (! Schema::connection($conn)->hasTable(self::LOG_TABLE)) {
            return collect();
        }

        return DB::connection($conn)->table(self::LOG_TABLE)->where('year_id', $yearId)->orderByDesc('id')->limit($limit)->get();
    }

    public function ensureLogTable(string $conn): void
    {
        if (Schema::connection($conn)->hasTable(self::LOG_TABLE)) {
            return;
        }
        Schema::connection($conn)->create(self::LOG_TABLE, function ($t) {
            $t->id();
            $t->unsignedSmallInteger('year_id');
            $t->unsignedBigInteger('exp_id');
            $t->unsignedBigInteger('kept_exp_id');
            $t->unsignedBigInteger('employee_id');
            $t->date('bill_date');
            $t->string('old_status', 20);
            $t->string('old_claim_at_step', 5)->nullable();
            $t->decimal('old_filled', 12, 2)->default(0);
            $t->decimal('old_verified', 12, 2)->default(0);
            $t->decimal('old_approved', 12, 2)->default(0);
            $t->decimal('old_financed', 12, 2)->default(0);
            $t->string('done_by', 40)->nullable();
            $t->timestamp('created_at')->useCurrent();
            $t->index(['year_id', 'employee_id']);
        });
    }
}
