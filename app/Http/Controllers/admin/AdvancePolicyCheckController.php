<?php

namespace App\Http\Controllers\admin;

use App\Http\Controllers\Controller;
use App\Services\Advance\AdvancePolicy;

/** ADV-POL-01: Advance policy check - set-up and this year's advances against the written policy. */
class AdvancePolicyCheckController extends Controller
{
    public function index()
    {
        $conn = (string) (session('expense_connection') ?: 'expense');
        $sections = AdvancePolicy::report($conn, (int) session('year_id'));
        $names = \App\Services\Claims\BillDuplicate::names(collect($sections['auto']['rows'])->pluck('Employee')->map(fn ($x) => (int) $x)->all());
        foreach ($sections['auto']['rows'] as &$r) {
            $r['Employee'] = $names[(int) $r['Employee']] ?? $r['Employee'];
        }
        unset($r);

        return view('reports.advance-policy-check', ['sections' => $sections, 'policy' => config('advance_policy')]);
    }
}
