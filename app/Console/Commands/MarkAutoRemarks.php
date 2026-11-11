<?php

namespace App\Console\Commands;

use App\Support\SystemRemark;
use App\Support\CurrentYear;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/** REMARK-AUTO-01: mark the remarks the system wrote before the fix with "(auto generated)". Dry run by default. */
class MarkAutoRemarks extends Command
{
    protected $signature = 'remarks:mark-auto {--company=1} {--year= : YearId (default: current year)} {--apply}';

    protected $description = 'Add "(auto generated)" to remarks written by the system (dry run by default)';

    public function handle(): int
    {
        $co = (int) $this->option('company');
        $conn = \App\Helpers\DatabaseSwitcher::getExpenseConnection($co);
        $y = (int) ($this->option('year') ?: CurrentYear::id($conn));
        $db = DB::connection($conn);
        $s = Schema::connection($conn);
        $c = "y{$y}_expenseclaims";
        $m = "y{$y}_monthexpensefinal";
        $suffix = trim(SystemRemark::SUFFIX);
        $jobs = [];
        if ($s->hasColumn($c, 'verification_source')) {
            $jobs['auto-verified claims (VerifyTRemark)'] = [$c, 'VerifyTRemark', fn ($q) => $q->where('verification_source', 'like', 'auto%')];
        }
        $jobs['duplicate odometer claims (Remark)'] = [$c, 'Remark', fn ($q) => $q->where('Remark', 'like', '[Duplicate of ExpId % - not counted]%')->where('Remark', 'not like', '%(auto generated)]%')];
        $jobs['bank-file payment remarks (Fin_PayRemark)'] = [$m, 'Fin_PayRemark', fn ($q) => $q->where(fn ($w) => $w->where('Fin_PayRemark', 'Zero payment amount - auto confirmed locally')->orWhere('Fin_PayRemark', 'like', 'Matrix %'))];
        foreach ($jobs as $label => [$table, $col, $where]) {
            if (! $s->hasTable($table)) {
                continue;
            }
            $q = $db->table($table)->where($col, 'not like', '%'.$suffix);
            $where($q);
            $n = (clone $q)->count();
            $this->line(sprintf('%-45s %d %s', $label, $n, $this->option('apply') ? 'marked' : 'to mark'));
            if ($n && $this->option('apply')) {
                if ($col === 'Remark') {
                    (clone $q)->update(['Remark' => DB::raw("LEFT(REPLACE(Remark, ' - not counted]', ' - not counted (auto generated)]'), 200)")]);
                } else {
                    (clone $q)->update([$col => DB::raw("CONCAT(TRIM({$col}), ' {$suffix}')")]);
                }
            }
        }

        return self::SUCCESS;
    }
}
