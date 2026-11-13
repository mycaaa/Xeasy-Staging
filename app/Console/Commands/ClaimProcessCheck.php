<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * PROCESS-01 (13 Nov 2026): where claims stand in the process (config/claim_process.php) and which records broke
 * the order - for example a month approved before it was verified, or a bill left in a submitted month without
 * being punched. Read only.
 *
 *   php artisan claims:process-check --company=1            (VNR; 3 = Nursery, 11 = VVNR)
 *   php artisan claims:process-check --company=1 --year=8 --list
 */
class ClaimProcessCheck extends Command
{
    protected $signature = 'claims:process-check {--company=1} {--year=} {--list : list the Exp Ids / employees of each problem}';

    protected $description = 'Count claims at each process stage and list records that skipped a stage or are in the wrong order';

    private function set(string $col): string
    {
        return "({$col} IS NOT NULL AND {$col} > '1970-01-01')";
    }

    public function handle(): int
    {
        $company = (int) $this->option('company');
        $process = config("claim_transitions.process.{$company}", 'vnr');
        $conn = \App\Helpers\DatabaseSwitcher::getExpenseConnection($company);
        $year = (int) ($this->option('year') ?: \App\Support\CurrentYear::id($conn));
        $c = "y{$year}_expenseclaims";
        $m = "y{$year}_monthexpensefinal";
        $db = DB::connection($conn);
        $this->info("Company {$company} ({$process}), year {$year}, database connection {$conn}");

        $live = "e.ClaimStatus NOT IN ('Deactivate','Deactivat','Draft') AND e.ClaimYearId = {$year}";
        $join = "JOIN {$m} f ON f.EmployeeID = e.CrBy AND f.Month = e.ClaimMonth AND f.YearId = {$year}";
        $count = fn (string $sql) => (int) ($db->selectOne($sql)->n ?? 0);

        // ── where claims / months stand ──
        $stages = [
            ['Bill uploaded, not punched yet', "SELECT COUNT(*) n FROM {$c} e WHERE {$live} AND e.ClaimStatus IN ('Submitted','Open','')"],
            ['Punched, waiting for the employee to confirm', "SELECT COUNT(*) n FROM {$c} e WHERE {$live} AND e.ClaimStatus = 'Filled' AND COALESCE(e.FilledOkay,0) = 0"],
            ['Confirmed, month not submitted', "SELECT COUNT(*) n FROM {$c} e {$join} WHERE {$live} AND e.ClaimStatus = 'Filled' AND e.FilledOkay = 1 AND f.Status <> 'Closed'"],
            ['Months submitted, waiting for verification', "SELECT COUNT(*) n FROM {$m} f WHERE f.YearId = {$year} AND f.Status = 'Closed' AND f.Total_Claim > 0 AND NOT ".$this->set('f.Verified_Date').($process === 'vnr' ? '' : ' AND NOT '.$this->set('f.Approved_Date'))],
        ];
        if ($process === 'vnr') {
            $stages[] = ['Months verified, waiting for manager approval', "SELECT COUNT(*) n FROM {$m} f WHERE f.YearId = {$year} AND ".$this->set('f.Verified_Date').' AND NOT '.$this->set('f.Approved_Date')];
        }
        $stages[] = ['Months approved, waiting for Finance', "SELECT COUNT(*) n FROM {$m} f WHERE f.YearId = {$year} AND ".$this->set('f.Approved_Date').' AND NOT '.$this->set('f.Fin_AppDate')];
        $stages[] = ['Finance approved, waiting for payment team', "SELECT COUNT(*) n FROM {$m} f WHERE f.YearId = {$year} AND ".$this->set('f.Fin_AppDate')." AND COALESCE(f.payment_status,'Pending') = 'Pending'"];
        $stages[] = ['Payment approved, waiting for treasury', "SELECT COUNT(*) n FROM {$m} f WHERE f.YearId = {$year} AND f.payment_status = 'Approved'"];
        $stages[] = ['Payment instructed, not confirmed', "SELECT COUNT(*) n FROM {$m} f WHERE f.YearId = {$year} AND f.payment_status = 'InstructedPayment'"];
        $stages[] = ['Paid', "SELECT COUNT(*) n FROM {$m} f WHERE f.YearId = {$year} AND f.payment_status = 'ConfirmedPayment'"];
        if ($process === 'vvnr') {
            $stages[] = ['Paid, waiting for verification', "SELECT COUNT(*) n FROM {$m} f WHERE f.YearId = {$year} AND f.payment_status = 'ConfirmedPayment' AND NOT ".$this->set('f.Verified_Date')];
        }
        $rows = [];
        foreach ($stages as [$label, $sql]) {
            try {
                $rows[] = [$label, number_format($count($sql))];
            } catch (\Throwable $e) {
                $rows[] = [$label, 'n/a ('.mb_substr($e->getMessage(), 0, 60).')'];
            }
        }
        $this->table(['Stage', 'Count'], $rows);

        // ── records out of order ──
        $checks = [
            ['Bills left unpunched in a submitted month (never reach verification)', "SELECT e.ExpId id FROM {$c} e {$join} WHERE {$live} AND e.ClaimStatus IN ('Submitted','Open','') AND f.Status = 'Closed' AND COALESCE(e.FilledOkay,0) <> 2"],
            ['Claims verified / approved / financed while their month is not submitted', "SELECT e.ExpId id FROM {$c} e {$join} WHERE {$live} AND e.ClaimStatus IN ('Verified','Approved','Financed') AND f.Status <> 'Closed'"],
            ['Months approved by Finance before the manager', "SELECT f.id id FROM {$m} f WHERE f.YearId = {$year} AND ".$this->set('f.Fin_AppDate').' AND NOT '.$this->set('f.Approved_Date')],
            ['Months in payment / paid without Finance approval', "SELECT f.id id FROM {$m} f WHERE f.YearId = {$year} AND f.payment_status IN ('Approved','InstructedPayment','ConfirmedPayment') AND NOT ".$this->set('f.Fin_AppDate')],
            ['Months paid without a payment instruction / approval date', "SELECT f.id id FROM {$m} f WHERE f.YearId = {$year} AND f.payment_status = 'ConfirmedPayment' AND NOT ".$this->set('f.payment_approved_date')],
        ];
        if ($process === 'vnr') {
            array_splice($checks, 2, 0, [
                ['Months approved by the manager before verification', "SELECT f.id id FROM {$m} f WHERE f.YearId = {$year} AND ".$this->set('f.Approved_Date').' AND NOT '.$this->set('f.Verified_Date')],
                ['Claims approved while their month is not verified', "SELECT e.ExpId id FROM {$c} e {$join} WHERE {$live} AND e.ClaimStatus = 'Approved' AND NOT ".$this->set('f.Verified_Date')],
            ]);
        }
        $out = [];
        $bad = 0;
        foreach ($checks as [$label, $sql]) {
            try {
                $ids = array_map(fn ($r) => $r->id, $db->select($sql.' LIMIT 2000'));
                $n = count($ids);
                $bad += $n;
                $out[] = [$n ? '!!' : 'ok', $label, $n, $this->option('list') && $n ? implode(', ', array_slice($ids, 0, 30)).($n > 30 ? ' ...' : '') : ''];
            } catch (\Throwable $e) {
                $out[] = ['?', $label, 'n/a', mb_substr($e->getMessage(), 0, 80)];
            }
        }
        $this->table(['', 'Order check', 'Records', $this->option('list') ? 'Ids (claims: Exp Id, months: row id)' : ''], $out);
        $bad ? $this->warn("{$bad} record(s) out of order - see above (add --list for the ids).") : $this->info('Every record follows the process order.');

        return self::SUCCESS;
    }
}
