<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * D-03: the claim, upload, detail and payment tables have no foreign keys, so child rows can lose
 * their parent. Adding foreign keys to the year tables needs the orphans cleaned first and can lock
 * big tables, so this nightly check counts them instead and keeps the result in data_orphan_checks
 * (one row per check per day, with up to 20 sample ids). Nothing is changed or deleted.
 */
class OrphanCheck extends Command
{
    protected $signature = 'data:orphan-check {--company=1} {--year= : YearId (default: current and previous)}';

    protected $description = 'Count child rows whose parent claim / month / advance is missing (report only)';

    public function handle(): int
    {
        $co = (int) $this->option('company');
        $conn = \App\Helpers\DatabaseSwitcher::getExpenseConnection($co);
        $db = DB::connection($conn);
        $schema = Schema::connection($conn);
        $cur = (int) ($this->option('year') ?: \App\Support\CurrentYear::id($conn));
        $years = $this->option('year') ? [$cur] : [$cur, $cur - 1];

        $checks = [];
        foreach ($years as $y) {
            $c = "y{$y}_expenseclaims";
            if (! $schema->hasTable($c)) {
                continue;
            }
            foreach (["y{$y}_claimuploads" => 'ExpId', "y{$y}_g1_expensefilldata" => 'ExpId', "y{$y}_g7_expensefilldata" => 'ExpId', "y{$y}_expenseremark" => 'ExpId'] as $child => $col) {
                if ($schema->hasTable($child) && $schema->hasColumn($child, $col)) {
                    $checks["{$child}.{$col} -> {$c}"] = "SELECT ch.{$col} id FROM {$child} ch LEFT JOIN {$c} p ON p.ExpId = ch.{$col} WHERE p.ExpId IS NULL AND ch.{$col} > 0";
                }
            }
            $m = "y{$y}_monthexpensefinal";
            if ($schema->hasTable($m)) {
                $checks["{$c} claims without a {$m} month"] = "SELECT DISTINCT CONCAT(p.CrBy,'/',p.ClaimMonth) id FROM {$c} p LEFT JOIN {$m} m ON m.EmployeeID = p.CrBy AND m.Month = p.ClaimMonth WHERE m.id IS NULL AND p.ClaimStatus NOT IN ('Deactivate','Draft') AND p.ClaimMonth BETWEEN 1 AND 12";
            }
        }
        if ($schema->hasTable('advance_request_activities') && $schema->hasTable('advance_request_employees')) {
            $checks['advance_request_activities -> advance_request_employees'] = 'SELECT a.id FROM advance_request_activities a LEFT JOIN advance_request_employees e ON e.id = a.advance_request_employee_id WHERE e.id IS NULL';
        }
        if ($schema->hasTable('advance_request_employees') && $schema->hasTable('advance_requests')) {
            $checks['advance_request_employees -> advance_requests'] = 'SELECT e.id FROM advance_request_employees e LEFT JOIN advance_requests r ON r.id = e.advance_request_id WHERE r.id IS NULL';
        }

        $this->ensureTable($db->getName());
        $today = now()->toDateString();
        $bad = 0;
        foreach ($checks as $name => $sql) {
            try {
                $n = (int) ($db->selectOne("SELECT COUNT(*) n FROM ({$sql}) x")->n ?? 0);
                $sample = $n ? implode(',', array_map(fn ($r) => $r->id, $db->select("{$sql} LIMIT 20"))) : '';
            } catch (\Throwable $e) {
                $this->warn("{$name}: ".$e->getMessage());

                continue;
            }
            $db->table('data_orphan_checks')->updateOrInsert(['check_date' => $today, 'company_id' => $co, 'check_name' => mb_substr($name, 0, 150)], ['orphans' => $n, 'sample_ids' => mb_substr($sample, 0, 500)]);
            $this->line(sprintf('%-75s %d', $name, $n));
            $bad += $n > 0 ? 1 : 0;
        }
        $this->info("{$bad} of ".count($checks).' checks found orphan rows (details in data_orphan_checks).');

        return self::SUCCESS;
    }

    private function ensureTable(string $conn): void
    {
        if (Schema::connection($conn)->hasTable('data_orphan_checks')) {
            return;
        }
        Schema::connection($conn)->create('data_orphan_checks', function ($t) {
            $t->id();
            $t->date('check_date');
            $t->unsignedSmallInteger('company_id');
            $t->string('check_name', 150);
            $t->unsignedInteger('orphans')->default(0);
            $t->string('sample_ids', 500)->nullable();
            $t->unique(['check_date', 'company_id', 'check_name']);
        });
    }
}
