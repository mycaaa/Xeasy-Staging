<?php

namespace App\Services\Claims;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * MONTH-TOTAL-01 (tracker, Mail 23 Oct; Finance "ok" 5 Nov 2026): month totals in
 * y{year}_monthexpensefinal that no longer equal their claims.
 *
 *   Claim_Amount    = SUM(FilledTAmt)     Verified_Amount = SUM(VerifyTAmt)
 *   Approved_Amount = SUM(ApprTAmt)       Finance_Amount  = SUM(FinancedTAmt)
 * over the employee-month's live claims (not Deactivate / Draft).
 *
 * Rules
 *  - only closed (submitted) months; a field is recomputed only if it is already set (> 0),
 *    i.e. the month has reached that stage;
 *  - by default only months not yet paid; paid months are reported, changed only with includePaid;
 *  - Fin_PayAmt / payment fields are never touched;
 *  - VVNR (11) pays after approval and verifies afterwards; a verified-vs-paid difference is carried
 *    to the next month, so on a paid VVNR month Verified_Amount is never rewritten, even with includePaid.
 *  - apply: backup of the affected rows (monthexpensefinal_recalc_bak_<ts>) and one log row per change
 *    (month_total_recalc_logs), in one transaction.
 */
class MonthTotalRecalculator
{
    public const FIELDS = [
        'Claim_Amount' => 'FilledTAmt',
        'Verified_Amount' => 'VerifyTAmt',
        'Approved_Amount' => 'ApprTAmt',
        'Finance_Amount' => 'FinancedTAmt',
    ];

    /** @return array{mismatches: array, months: int, rows_changed: int, fields_changed: int, backup: ?string} */
    public function run(int $companyId, int $yearId, bool $apply = false, bool $includePaid = false): array
    {
        $conn = \App\Helpers\DatabaseSwitcher::getExpenseConnection($companyId);
        $m = "y{$yearId}_monthexpensefinal";
        $c = "y{$yearId}_expenseclaims";
        $hr = \App\Helpers\DatabaseSwitcher::getHrimsDatabaseName($companyId);
        $db = DB::connection($conn);

        $rows = $db->select("SELECT m.id, m.EmployeeID, m.Month, m.Status, m.DateOfSubmit, m.Fin_PayDate, m.Fin_PayAmt,
                m.Claim_Amount, m.Verified_Amount, m.Approved_Amount, m.Finance_Amount, h.EmpCode,
                COALESCE(s.f,0) f, COALESCE(s.v,0) v, COALESCE(s.a,0) a, COALESCE(s.fi,0) fi, COALESCE(s.n,0) n,
                COALESCE(s.added_late,0) added_late, COALESCE(d.n,0) deactivated
            FROM {$m} m
            LEFT JOIN {$hr}.hrm_employee h ON h.EmployeeID = m.EmployeeID
            LEFT JOIN (SELECT CrBy, ClaimMonth, SUM(FilledTAmt) f, SUM(VerifyTAmt) v, SUM(ApprTAmt) a, SUM(FinancedTAmt) fi, COUNT(*) n,
                              SUM(CrDate > (SELECT DateOfSubmit FROM {$m} mm WHERE mm.EmployeeID = x.CrBy AND mm.Month = x.ClaimMonth AND mm.YearId = ? LIMIT 1)) added_late
                         FROM {$c} x WHERE ClaimStatus NOT IN ('Deactivate','Deactivat','Draft') GROUP BY CrBy, ClaimMonth) s
                   ON s.CrBy = m.EmployeeID AND s.ClaimMonth = m.Month
            LEFT JOIN (SELECT CrBy, ClaimMonth, COUNT(*) n FROM {$c} WHERE ClaimStatus IN ('Deactivate','Deactivat') GROUP BY CrBy, ClaimMonth) d
                   ON d.CrBy = m.EmployeeID AND d.ClaimMonth = m.Month
            WHERE m.YearId = ? AND m.Status <> 'Open'", [$yearId, $yearId]);

        $headMoves = [];
        if (Schema::connection($conn)->hasTable('claim_head_change_logs')) {
            foreach ($db->select("SELECT l.employee_id, x.ClaimMonth, COUNT(*) n FROM claim_head_change_logs l JOIN {$c} x ON x.ExpId = l.exp_id WHERE l.year_id = ? GROUP BY l.employee_id, x.ClaimMonth", [$yearId]) as $h) {
                $headMoves[$h->employee_id.'-'.$h->ClaimMonth] = (int) $h->n;
            }
        }

        $sumKey = ['Claim_Amount' => 'f', 'Verified_Amount' => 'v', 'Approved_Amount' => 'a', 'Finance_Amount' => 'fi'];
        $mismatches = [];
        $changes = [];
        foreach ($rows as $r) {
            $paid = ! empty($r->Fin_PayDate) && \App\Support\ZeroDate::has($r->Fin_PayDate);
            foreach ($sumKey as $field => $k) {
                $monthVal = (float) $r->$field;
                $sum = round((float) $r->$k, 2);
                if ($monthVal <= 0 || abs($monthVal - $sum) < 0.5) {
                    continue;
                }
                $cause = $this->cause($companyId, $field, $r, $paid, $headMoves[$r->EmployeeID.'-'.$r->Month] ?? 0);
                // a month whose claims were all deactivated is left for a person to look at
                $willFix = (! $paid || $includePaid) && (int) $r->n > 0 && ! ($companyId == 11 && $paid && $field === 'Verified_Amount');
                $mismatches[] = [
                    'emp_code' => $r->EmpCode, 'employee_id' => (int) $r->EmployeeID, 'month' => (int) $r->Month, 'field' => $field,
                    'month_value' => $monthVal, 'claims_sum' => $sum, 'difference' => round($monthVal - $sum, 2),
                    'paid' => $paid ? 'Yes' : 'No', 'paid_on' => $paid ? $r->Fin_PayDate : '', 'paid_amount' => $paid ? (float) $r->Fin_PayAmt : null,
                    'cause' => $cause, 'action' => $willFix ? ($apply ? 'corrected' : 'would be corrected') : 'report only',
                ];
                if ($willFix) {
                    $changes[(int) $r->id][$field] = ['old' => $monthVal, 'new' => $sum];
                }
            }
        }

        $backup = null;
        $fieldsChanged = 0;
        if ($apply && $changes) {
            $backup = 'monthexpensefinal_recalc_bak_'.now()->format('Ymd_His');
            $ids = array_keys($changes);
            $db->statement("CREATE TABLE `{$backup}` AS SELECT * FROM `{$m}` WHERE id IN (".implode(',', array_map('intval', $ids)).')');
            if (! Schema::connection($conn)->hasTable('month_total_recalc_logs')) {
                Schema::connection($conn)->create('month_total_recalc_logs', function ($t) {
                    $t->id();
                    $t->unsignedSmallInteger('year_id');
                    $t->unsignedBigInteger('month_row_id');
                    $t->unsignedBigInteger('employee_id');
                    $t->unsignedTinyInteger('month');
                    $t->string('field', 30);
                    $t->decimal('old_value', 12, 2);
                    $t->decimal('new_value', 12, 2);
                    $t->string('backup_table', 64)->nullable();
                    $t->unsignedBigInteger('user_id')->nullable();
                    $t->timestamp('created_at')->useCurrent();
                });
            }
            $byId = collect($rows)->keyBy('id');
            $db->transaction(function () use ($db, $m, $changes, $byId, $yearId, $backup, &$fieldsChanged) {
                foreach ($changes as $id => $fields) {
                    $db->table($m)->where('id', $id)->update(array_map(fn ($x) => $x['new'], $fields));
                    foreach ($fields as $f => $x) {
                        $db->table('month_total_recalc_logs')->insert([
                            'year_id' => $yearId, 'month_row_id' => $id, 'employee_id' => $byId[$id]->EmployeeID, 'month' => $byId[$id]->Month,
                            'field' => $f, 'old_value' => $x['old'], 'new_value' => $x['new'], 'backup_table' => $backup, 'user_id' => auth()->id(),
                        ]);
                        $fieldsChanged++;
                    }
                }
            });
        }

        return ['mismatches' => $mismatches, 'months' => count($rows), 'rows_changed' => $apply ? count($changes) : 0,
            'rows_to_change' => count($changes), 'fields_changed' => $fieldsChanged, 'backup' => $backup];
    }

    private function cause(int $companyId, string $field, object $r, bool $paid, int $headMoves): string
    {
        $c = [];
        if ($companyId == 11 && $paid && in_array($field, ['Verified_Amount', 'Approved_Amount'], true)) {
            $c[] = 'VVNR: verified after payment; difference goes to the next month';
        }
        if ((int) $r->added_late > 0) {
            $c[] = $r->added_late.' claim(s) dated after submission';
        }
        $diff = (float) $r->$field - (float) $r->{['Claim_Amount' => 'f', 'Verified_Amount' => 'v', 'Approved_Amount' => 'a', 'Finance_Amount' => 'fi'][$field]};
        if ((int) $r->deactivated > 0 && $diff > 0) {
            $c[] = 'month total still includes '.$r->deactivated.' deactivated claim(s)';
        } elseif ($diff < 0) {
            $c[] = 'claims added or raised after the month total was saved';
        }
        if ($headMoves > 0) {
            $c[] = $headMoves.' head change(s)';
        }
        if ((int) $r->n === 0) {
            $c[] = 'no live claims left (all deactivated) - check by hand';
        }
        if (! $c) {
            $c[] = 'claim amounts edited after the month total was saved';
        }

        return implode('; ', $c) ?: 'claim amounts changed after the month total was saved';
    }
}
