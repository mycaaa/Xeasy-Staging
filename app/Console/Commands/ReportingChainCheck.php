<?php

namespace App\Console\Commands;

use App\Support\ReportingTree;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * HIER-LOOP-01 (16 Nov 2026): read-only check of the HRIMS reporting chain - loops (A reports to B, B to A),
 * people who report to themselves, active people whose manager is missing or inactive, and the longest chain.
 * Xeasy now walks the chain safely whatever the data, but a loop still means wrong approvers, so HR should fix it.
 */
class ReportingChainCheck extends Command
{
    protected $signature = 'hr:reporting-check';

    protected $description = 'List loops and gaps in the HRIMS reporting chain (read-only)';

    public function handle(): int
    {
        $rows = DB::connection('hrims')->table('hrm_employee_general as g')
            ->leftJoin('hrm_employee as e', 'e.EmployeeID', '=', 'g.EmployeeID')
            ->get(['g.EmployeeID', 'g.RepEmployeeID', 'e.EmpCode', 'e.EmpStatus'])->keyBy('EmployeeID');

        $self = $rows->filter(fn ($r) => (int) $r->RepEmployeeID === (int) $r->EmployeeID);
        $this->line('Report to themselves (top of the tree): '.$self->map(fn ($r) => $r->EmployeeID.' ('.$r->EmpCode.', '.$r->EmpStatus.')')->implode(', '));

        $loops = ReportingTree::loops();
        $this->line('Loops: '.count($loops));
        foreach ($loops as $l) {
            $this->warn('  '.implode(' -> ', array_map(fn ($id) => $id.' ('.($rows[$id]->EmpCode ?? '?').')', $l)));
        }

        $gap = $rows->filter(function ($r) use ($rows) {
            $m = (int) $r->RepEmployeeID;

            return $r->EmpStatus === 'A' && $m > 0 && $m !== (int) $r->EmployeeID && (($rows[$m]->EmpStatus ?? null) !== 'A');
        });
        $this->line('Active people whose manager is missing or not active: '.$gap->count()
            .($gap->isNotEmpty() ? ' e.g. '.$gap->take(10)->map(fn ($r) => $r->EmployeeID.'->'.$r->RepEmployeeID)->implode(', ') : ''));

        $max = 0;
        foreach ($rows->keys() as $id) {
            $max = max($max, count(ReportingTree::ancestors((int) $id)));
        }
        $this->line("Longest chain: {$max} levels");

        return $loops ? self::FAILURE : self::SUCCESS;
    }
}
