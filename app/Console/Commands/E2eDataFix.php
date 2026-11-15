<?php

namespace App\Console\Commands;

use App\Support\CurrentYear;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * E2E-DATA-01 (16 Nov 2026, "E2E findings 15 Nov" tab #1, #2, #8): corrects the old data the E2E run found, by the
 * rules the code now enforces. Dry run unless --apply; --apply first copies every row it changes into a backup
 * table and writes one log line per changed field (e2e_data_fix_logs), so each change can be undone.
 *
 *  dates   (#2) a bill whose date has the right month but a wrong year (2006, 2019, 2024 ...) gets the year of
 *               this financial year for that month. Only when the new date is not after the day the bill was
 *               entered and not in the future; the claim month does not change, so no total changes. Empty dates
 *               (1970) and bills of the previous year filed into Jan-Mar of this year are listed, not changed.
 *  confirm (#1) a Verified bill the employee never confirmed, in a month that is submitted but not yet approved,
 *               is confirmed as month submit now does (E2E-AV-01), and the month's bill count and claimed total are
 *               worked out again the way submit does. Months already approved or paid are listed, not changed:
 *               those bills were never paid and a change there is a payment decision.
 *  cap     (#8) a bill verified or approved above the claimed amount, in a month not yet approved, is capped at
 *               the claimed amount (the rule since 17 Oct). Approved or paid months are listed, not changed.
 *
 * After it: php artisan claims:recalc-month-totals --apply (month totals) and claims:ensure-month-rows (#3).
 */
class E2eDataFix extends Command
{
    protected $signature = 'claims:e2e-data-fix {--company=1 : 1 = VNR, 11 = VVNR, 3 = Nursery} {--year= : YearId (default: the current year)}
        {--only= : dates, confirm or cap (default all)} {--apply : write the corrections (backup + log)} {--report= : write every row to this CSV path}';

    protected $description = 'Correct the E2E data findings (bill dates, unconfirmed verified bills, verified above claimed); dry run by default';

    private const ODO_OK = "((c.ClaimId = 7 AND IFNULL(c.App_Odometer,'') NOT IN ('N','R')) OR c.ClaimId <> 7)";

    private array $rows = [];

    public function handle(): int
    {
        $co = (int) $this->option('company');
        $conn = \App\Helpers\DatabaseSwitcher::getExpenseConnection($co);
        $year = (int) ($this->option('year') ?: CurrentYear::id($conn));
        $db = DB::connection($conn);
        $c = "y{$year}_expenseclaims";
        $m = "y{$year}_monthexpensefinal";
        if (! Schema::connection($conn)->hasTable($c) || ! Schema::connection($conn)->hasTable($m)) {
            $this->error("company {$co} year {$year}: claim tables not found");

            return self::FAILURE;
        }
        $fy = $db->table('financialyear')->where('YearId', $year)->first();
        if (! $fy) {
            $this->error("year {$year} not in financialyear");

            return self::FAILURE;
        }
        $from = substr((string) $fy->FromDate, 0, 10);
        $to = substr((string) ($fy->Todate ?? $fy->ToDate), 0, 10);
        $y1 = (int) substr($from, 0, 4);
        $apply = (bool) $this->option('apply');
        $only = $this->option('only');
        $stage = "CASE WHEN m.id IS NULL THEN 'no month record' WHEN m.Fin_PayDate > '2000-01-01' THEN 'paid' WHEN m.Approved_Date > '2000-01-01' THEN 'approved'
                  WHEN m.Status <> 'Closed' THEN 'open' WHEN m.Verified_Date > '2000-01-01' THEN 'verified' ELSE 'submitted' END";
        $join = "LEFT JOIN {$m} m ON m.EmployeeID = c.CrBy AND m.Month = c.ClaimMonth AND m.YearId = {$year}";
        $changes = [];   // ExpId => [field => [old, new]]
        $months = [];    // "emp-month" => [emp, month] whose totals the confirm step works out again

        // #2 bill dates
        if (! $only || $only === 'dates') {
            $today = now()->toDateString();
            foreach ($db->select("SELECT c.ExpId, c.CrBy, c.ClaimMonth, c.BillDate, DATE(c.CrDate) cr, c.ClaimStatus, {$stage} st FROM {$c} c {$join}
                    WHERE c.ClaimYearId = ? AND c.ClaimStatus NOT IN ('Deactivate','Draft') AND (c.BillDate < ? OR c.BillDate > ?)", [$year, $from, $to]) as $r) {
                $bill = substr((string) $r->BillDate, 0, 10);
                $mon = (int) substr($bill, 5, 2);
                $new = null;
                $why = '';
                if ($bill < '1971-01-01') {
                    $why = 'empty date (1970): the real date is not known';
                } elseif ($mon !== (int) $r->ClaimMonth) {
                    $why = 'month of the date differs from the claim month';
                } else {
                    $cand = sprintf('%04d-%s', $mon >= 4 ? $y1 : $y1 + 1, substr($bill, 5));
                    if (! checkdate($mon, (int) substr($cand, 8, 2), (int) substr($cand, 0, 4))) {
                        $why = 'no such day in this year';
                    } elseif ($cand > $today || ($r->cr && $cand > $r->cr)) {
                        $why = $mon <= 3 ? 'bill of an earlier year filed into Jan-Mar of this year (month not started)' : 'the corrected date would be after the day the bill was entered';
                    } else {
                        $new = $cand;
                    }
                }
                $this->add('dates', $r, 'BillDate', $bill, $new, $new ? 'year corrected' : 'not changed: '.$why);
                if ($new) {
                    $changes[(int) $r->ExpId]['BillDate'] = [$bill, $new];
                }
            }
        }

        // #1 Verified bills the employee never confirmed, in a closed month
        if (! $only || $only === 'confirm') {
            foreach ($db->select("SELECT c.ExpId, c.CrBy, c.ClaimMonth, c.ClaimStatus, c.VerifyTAmt, c.FilledTAmt, {$stage} st FROM {$c} c {$join}
                    WHERE c.ClaimYearId = ? AND c.ClaimStatus = 'Verified' AND IFNULL(c.FilledOkay,0) = 0 AND m.Status = 'Closed'
                    AND ".self::ODO_OK." AND (c.Backdate_Odometer_Status IS NULL OR c.Backdate_Odometer_Status = 'A')", [$year]) as $r) {
                $fix = in_array($r->st, ['submitted', 'verified'], true);
                $this->add('confirm', $r, 'FilledOkay', 0, $fix ? 1 : null, $fix ? 'confirmed (month not yet approved)'
                    : 'not changed: month already '.$r->st.' and this bill was not paid (verified '.$r->VerifyTAmt.')');
                if ($fix) {
                    $changes[(int) $r->ExpId]['FilledOkay'] = [0, 1];
                    $changes[(int) $r->ExpId]['c_verify'] = [null, 'Y'];
                    $months[$r->CrBy.'-'.$r->ClaimMonth] = [(int) $r->CrBy, (int) $r->ClaimMonth];
                }
            }
        }

        // #8 verified / approved above claimed
        if (! $only || $only === 'cap') {
            foreach ($db->select("SELECT c.ExpId, c.CrBy, c.ClaimMonth, c.ClaimStatus, c.FilledTAmt, c.VerifyTAmt, c.ApprTAmt, {$stage} st FROM {$c} c {$join}
                    WHERE c.ClaimYearId = ? AND c.ClaimStatus NOT IN ('Deactivate','Draft') AND c.FilledTAmt > 0
                    AND (c.VerifyTAmt > c.FilledTAmt + 0.5 OR c.ApprTAmt > c.FilledTAmt + 0.5)", [$year]) as $r) {
                $fix = in_array($r->st, ['open', 'submitted', 'verified'], true);
                foreach (['VerifyTAmt', 'ApprTAmt'] as $f) {
                    if ((float) $r->$f > (float) $r->FilledTAmt + 0.5) {
                        $this->add('cap', $r, $f, $r->$f, $fix ? $r->FilledTAmt : null, $fix ? 'capped at claimed '.$r->FilledTAmt
                            : 'not changed: month already '.$r->st.' (claimed '.$r->FilledTAmt.')');
                        if ($fix) {
                            $changes[(int) $r->ExpId][$f] = [$r->$f, $r->FilledTAmt];
                        }
                    }
                }
            }
        }

        foreach (collect($this->rows)->groupBy('step') as $step => $g) {
            $this->line(sprintf('  %-8s %4d rows: %4d %s, %4d listed only', $step, $g->count(), $g->whereNotNull('new')->count(), $apply ? 'changed' : 'to change', $g->whereNull('new')->count()));
        }

        $backup = null;
        if ($apply && $changes) {
            $backup = $this->save($conn, $year, $c, $m, $changes, $months);
            $this->info('  applied; backup '.$backup.', '.count($months).' month totals worked out again');
        }

        if ($path = $this->option('report')) {
            $f = fopen($path, 'w');
            fputcsv($f, ['Step', 'Exp Id', 'Employee', 'Month', 'Month stage', 'Field', 'Old', 'New', 'Action']);
            foreach ($this->rows as $x) {
                fputcsv($f, [$x['step'], $x['exp'], $x['emp'], $x['month'], $x['stage'], $x['field'], $x['old'], $x['new'], $x['action']]);
            }
            fclose($f);
            $this->line("  report: {$path}");
        }

        return self::SUCCESS;
    }

    private function add(string $step, object $r, string $field, $old, $new, string $action): void
    {
        $this->rows[] = ['step' => $step, 'exp' => (int) $r->ExpId, 'emp' => (int) $r->CrBy, 'month' => (int) $r->ClaimMonth,
            'stage' => $r->st, 'field' => $field, 'old' => $old, 'new' => $new, 'action' => $action];
    }

    private function save(string $conn, int $year, string $c, string $m, array $changes, array $months): string
    {
        $db = DB::connection($conn);
        $schema = Schema::connection($conn);
        $stamp = now()->format('Ymd_His');
        $backup = "expenseclaims_e2efix_bak_{$stamp}";
        $db->statement("CREATE TABLE `{$backup}` AS SELECT * FROM `{$c}` WHERE ExpId IN (".implode(',', array_map('intval', array_keys($changes))).')');
        if ($months) {
            $where = implode(' OR ', array_map(fn ($x) => '(EmployeeID = '.$x[0].' AND Month = '.$x[1].')', $months));
            $db->statement("CREATE TABLE `monthexpensefinal_e2efix_bak_{$stamp}` AS SELECT * FROM `{$m}` WHERE YearId = {$year} AND ({$where})");
        }
        if (! $schema->hasTable('e2e_data_fix_logs')) {
            $schema->create('e2e_data_fix_logs', function ($t) {
                $t->id();
                $t->unsignedSmallInteger('year_id');
                $t->string('step', 20);
                $t->string('table_name', 40);
                $t->unsignedBigInteger('row_id');
                $t->string('field', 30);
                $t->string('old_value', 60)->nullable();
                $t->string('new_value', 60)->nullable();
                $t->string('backup_table', 64)->nullable();
                $t->timestamp('created_at')->useCurrent();
            });
        }
        $stepOf = fn ($f) => match ($f) { 'BillDate' => 'dates', 'FilledOkay', 'c_verify' => 'confirm', default => 'cap' };
        $db->transaction(function () use ($db, $c, $m, $year, $changes, $months, $backup, $stepOf) {
            foreach ($changes as $id => $fields) {
                $db->table($c)->where('ExpId', $id)->update(array_map(fn ($x) => $x[1], $fields));
                foreach ($fields as $f => $x) {
                    $db->table('e2e_data_fix_logs')->insert(['year_id' => $year, 'step' => $stepOf($f), 'table_name' => $c, 'row_id' => $id,
                        'field' => $f, 'old_value' => $x[0], 'new_value' => $x[1], 'backup_table' => $backup]);
                }
            }
            // month bill count and claimed total, the way month submit works them out
            foreach ($months as [$emp, $mon]) {
                $t = $db->selectOne("SELECT COUNT(*) n, COALESCE(SUM(FilledTAmt),0) f FROM {$c} c WHERE c.ClaimYearId = ? AND c.CrBy = ? AND c.ClaimMonth = ?
                    AND c.FilledOkay = 1 AND c.ClaimStatus NOT IN ('Deactivate','Draft') AND c.FilledBy > 0 AND ".self::ODO_OK."
                    AND (c.Backdate_Odometer_Status IS NULL OR c.Backdate_Odometer_Status = 'A')", [$year, $emp, $mon]);
                $row = $db->table($m)->where('YearId', $year)->where('EmployeeID', $emp)->where('Month', $mon)->first();
                if ($row) {
                    $db->table($m)->where('id', $row->id)->update(['Total_Claim' => $t->n, 'Claim_Amount' => $t->f]);
                    foreach (['Total_Claim' => $t->n, 'Claim_Amount' => $t->f] as $f => $v) {
                        $db->table('e2e_data_fix_logs')->insert(['year_id' => $year, 'step' => 'confirm', 'table_name' => $m, 'row_id' => $row->id,
                            'field' => $f, 'old_value' => $row->$f, 'new_value' => $v, 'backup_table' => $backup]);
                    }
                }
            }
        });

        return $backup;
    }
}
