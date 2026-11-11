<?php

namespace App\Http\Controllers;

use App\Services\Reports\FinanceReviewFlags;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

/** F-02 / F-06 / F-08: Finance review flags screen and CSV export. */
class FinanceReviewFlagsController extends Controller
{
    private function data(Request $request): array
    {
        $conn = session('expense_connection', \App\Helpers\DatabaseSwitcher::getExpenseConnection());
        $year = (int) ($request->integer('year') ?: \App\Support\CurrentYear::id());
        $key = "finance-review-flags:{$conn}:{$year}";
        if ($request->boolean('refresh')) {
            Cache::forget($key);
        }

        return Cache::remember($key, 3600, function () use ($conn, $year) {
            $f = new FinanceReviewFlags($conn, $year, session('hrims_connection', 'hrims'));

            return ['year' => $year, 'peer' => $f->peerOutliers(), 'backfill' => $f->bulkBackfill(), 'round' => $f->roundNumbers(), 'at' => now()->format('d-m-Y H:i')];
        });
    }

    public function index(Request $request)
    {
        $d = $this->data($request);
        $tab = in_array($request->query('tab'), ['peer', 'backfill', 'round'], true) ? $request->query('tab') : 'peer';

        return view('reports.finance-review-flags', $d + ['tab' => $tab]);
    }

    public function export(Request $request)
    {
        $d = $this->data($request);
        $tab = (string) $request->query('tab', 'peer');
        [$head, $rows] = match ($tab) {
            'backfill' => [['Hour', 'Emp code', 'Employee', 'Department', 'Claims', 'Old bills', 'Amount', 'Night'],
                array_map(fn ($r) => [$r['hour'], $r['code'], $r['name'], $r['department'], $r['claims'], $r['old'], $r['amount'], $r['night'] ? 'yes' : ''], $d['backfill']['sessions'])],
            'keyed' => [['Emp code', 'Keyed by', 'Roles', 'Claims for others', 'Claimants', 'Amount', 'Last entry'],
                array_map(fn ($r) => [$r['code'], $r['name'], $r['roles'], $r['claims'], $r['claimants'], $r['amount'], $r['last']], $d['backfill']['keyed'])],
            'round' => [['Emp code', 'Employee', 'Department', 'Lines', 'Round lines', 'Expected', 'z', 'Round amount'],
                array_map(fn ($r) => [$r['code'], $r['name'], $r['department'], $r['lines'], $r['round'], $r['expected'], $r['z'], $r['amount']], $d['round']['rows'])],
            default => [['Emp code', 'Employee', 'Department', 'Grade', 'Outlier months', 'Excess above fence', 'Not mapped'],
                array_map(fn ($r) => [$r['code'], $r['name'], $r['department'], $r['grade'], $r['outlier_months'], $r['excess'], $r['unmapped'] ? 'yes' : ''], $d['peer']['rows'])],
        };
        $name = "finance_review_{$tab}_y{$d['year']}.csv";

        return response()->streamDownload(function () use ($head, $rows) {
            $out = fopen('php://output', 'w');
            fputcsv($out, $head);
            foreach ($rows as $r) {
                // a cell starting with = + - @ is written as text so Excel does not run it
                fputcsv($out, array_map(fn ($v) => is_string($v) && preg_match('/^[=+\-@]/', $v) ? "'".$v : $v, $r));
            }
            fclose($out);
        }, $name, ['Content-Type' => 'text/csv']);
    }
}
