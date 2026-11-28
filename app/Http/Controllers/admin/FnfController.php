<?php

namespace App\Http\Controllers\admin;

use App\Helpers\DatabaseSwitcher;
use App\Http\Controllers\Controller;
use App\Services\Fnf\FnfCalculator;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * FNF-01 (20 Nov 2026, tracker tab "FNF_Xeasy 20 Nov", section 7): FNF - employee-wise Finance clearance.
 * Employees whose HR clearance is done in ESS (HRIMS hrm_employee_separation.HR_NOC = 'Y') and whose Finance
 * clearance is pending are listed with: Focus / Xeasy ledger balance, payment hold, eligible expenses (rules of
 * section 8), HR recovery / payment and the final balance. Finance enters HR's recovery / payment figure, checks the
 * working and marks the Finance clearance done (the working is kept as it was at that moment).
 */
class FnfController extends Controller
{
    private function hrims(): string
    {
        return DatabaseSwitcher::getHrimsConnection((int) session('company_id', 1)) ?: 'hrims';
    }

    private function hasSeparationTable(): bool
    {
        try {
            return Schema::connection($this->hrims())->hasTable('hrm_employee_separation');
        } catch (\Throwable $e) {
            return false;
        }
    }

    /** Employees for the screen: HR clearance done (or, where the ESS separation table is not on this server, separated employees). */
    private function employees(?int $only = null)
    {
        $companyId = (int) session('company_id', 1);
        $h = DB::connection($this->hrims());
        $q = $h->table('hrm_employee as e')
            ->leftJoin('hrm_employee_general as g', 'g.EmployeeID', '=', 'e.EmployeeID')
            ->leftJoin('core_departments as d', 'd.id', '=', 'g.DepartmentId')
            ->where('e.CompanyId', $companyId);
        $cols = ['e.EmployeeID', 'e.EmpCode', 'e.CompanyId', 'e.EmpStatus', 'e.DateOfResignation', 'e.DateOfSepration', 'd.department_name',
            DB::raw("TRIM(CONCAT(e.Fname, ' ', COALESCE(e.Sname, ''), ' ', COALESCE(e.Lname, ''))) as name")];
        if ($this->hasSeparationTable()) {
            $sepCols = array_flip(Schema::connection($this->hrims())->getColumnListing('hrm_employee_separation'));
            $latest = $h->table('hrm_employee_separation')->selectRaw('EmployeeID, MAX(EmpSepId) as sid')->groupBy('EmployeeID');
            $q->joinSub($latest, 'ls', 'ls.EmployeeID', '=', 'e.EmployeeID')
                ->join('hrm_employee_separation as s', 's.EmpSepId', '=', 'ls.sid')
                ->where('s.HR_NOC', 'Y');
            $cols[] = 's.HR_NOC';
            if (isset($sepCols['Acc_NOC'])) {
                $cols[] = 's.Acc_NOC';
            }
        } else {
            $q->where('e.DateOfSepration', '>=', now()->subDays(180)->toDateString())->where('e.DateOfSepration', '<=', now()->addDays(30)->toDateString());
            $cols[] = DB::raw("'' as HR_NOC");
        }
        if ($only) {
            $q->where('e.EmployeeID', $only);
        }

        return $q->orderByDesc('e.DateOfSepration')->get($cols);
    }

    private function saved(array $ids)
    {
        return Schema::hasTable('fnf_clearances')
            ? DB::table('fnf_clearances')->where('company_id', (int) session('company_id', 1))->whereIn('employee_id', $ids ?: [0])->get()->keyBy('employee_id')
            : collect();
    }

    public function index(Request $request)
    {
        abort_unless(Schema::hasTable('fnf_clearances'), 503, 'Run the database update (Admin > DB setup) first.');
        $tab = in_array($request->input('tab'), ['pending', 'cleared'], true) ? $request->input('tab') : 'pending';
        $emps = $this->employees();
        $saved = $this->saved($emps->pluck('EmployeeID')->map(fn ($x) => (int) $x)->all());
        $conn = DatabaseSwitcher::getExpenseConnection((int) session('company_id', 1)) ?: 'expense';
        $prev = session('expense_connection');
        session(['expense_connection' => $conn]);
        $ledger = $emps->isEmpty() ? [] : app(\App\Http\Controllers\ExpensePaymentController::class)->computeBatchEmployeeLedgerBalances($emps->pluck('EmployeeID')->map(fn ($x) => (int) $x)->all());
        session(['expense_connection' => $prev]);

        $rows = [];
        foreach ($emps as $e) {
            $s = $saved->get((int) $e->EmployeeID);
            $cleared = ($s && $s->status === 'cleared') || (($e->Acc_NOC ?? '') === 'Y');
            if (($tab === 'cleared') !== (bool) $cleared) {
                continue;
            }
            if ($s && $s->status === 'cleared' && $s->working_json) {   // frozen at clearance
                $w = json_decode($s->working_json, true);
            } else {
                try {
                    $w = FnfCalculator::forEmployee($e, (float) ($ledger[(int) $e->EmployeeID]['total_balance'] ?? 0));
                } catch (\Throwable $ex) {   // one employee's data must not stop the list
                    report($ex);
                    $w = ['ledger_balance' => 0, 'hold_amount' => 0, 'hold' => [], 'expense_current' => 0, 'expense_previous' => 0, 'expense_other' => 0,
                        'expense_total' => 0, 'months' => [], 'waiting' => 0, 'excluded' => 0, 'lwd' => null, 'hard_copy_until' => null, 'error' => true];
                }
            }
            $hr = (float) ($s->hr_amount ?? 0);
            $rows[] = ['e' => $e, 's' => $s, 'w' => $w, 'hr' => $hr, 'final' => FnfCalculator::finalBalance((float) $w['ledger_balance'], (float) $w['expense_total'], $hr)];
        }
        if ($request->input('export') === 'csv') {
            return $this->csv($rows);
        }

        return view('fnf.index', ['rows' => $rows, 'tab' => $tab, 'hrTable' => $this->hasSeparationTable()]);
    }

    public function show(int $id)
    {
        $e = $this->employees($id)->first();
        abort_unless($e, 404, 'Employee not in the FNF list.');
        $s = $this->saved([$id])->get($id);
        $w = $s && $s->status === 'cleared' && $s->working_json ? json_decode($s->working_json, true) : FnfCalculator::forEmployee($e);
        $hr = (float) ($s->hr_amount ?? 0);

        // FNF-LIVE-01: claims' status today, month by month, beside the working; links to the month summary sheet
        try {
            $live = FnfCalculator::liveStatus($e, $w['months'] ?? []);
        } catch (\Throwable $ex) {
            report($ex);
            $live = [];
        }

        return response()->json(['e' => $e, 's' => $s, 'w' => $w, 'hr' => $hr, 'live' => $live, 'summary_url' => route('expense.print.details'), 'company' => (int) session('company_id', 1),   // FNF-SUM-01 (28 Nov 2026, testing point 45): the E-Home summary sheet
           
            'final' => FnfCalculator::finalBalance((float) $w['ledger_balance'], (float) $w['expense_total'], $hr)]);
    }

    /** Save HR's recovery / payment figure (+ payable to the employee, - recoverable) or mark the Finance clearance. */
    public function save(Request $request, int $id)
    {
        $v = $request->validate([
            'hr_amount' => 'nullable|numeric|between:-99999999,99999999',
            'hr_remark' => 'nullable|string|max:500',
            'action' => 'required|in:save,clear,reopen',
            'remark' => 'nullable|string|max:500',
        ]);
        $e = $this->employees($id)->first();
        abort_unless($e, 404, 'Employee not in the FNF list.');
        $companyId = (int) session('company_id', 1);
        $s = DB::table('fnf_clearances')->where('company_id', $companyId)->where('employee_id', $id)->first();
        if ($s && $s->status === 'cleared' && $v['action'] !== 'reopen') {
            return response()->json(['success' => false, 'message' => 'Finance clearance is already done; reopen it first.'], 422);
        }
        $user = auth()->user();
        $row = ['company_id' => $companyId, 'employee_id' => $id, 'emp_code' => $e->EmpCode, 'emp_name' => $e->name,
            // FNF-FIX-01 (21 Nov 2026, testing point 2): a blank separation date in HRIMS is 0000-00-00, which the table refuses
            'separation_date' => ((string) $e->DateOfSepration > '2000-01-01') ? substr((string) $e->DateOfSepration, 0, 10) : null,
            'updated_by' => $user->id, 'updated_at' => now()];
        if ($v['action'] !== 'reopen') {
            $row['hr_amount'] = round((float) ($v['hr_amount'] ?? ($s->hr_amount ?? 0)), 2);
            $row['hr_remark'] = $v['hr_remark'] ?? ($s->hr_remark ?? null);
        }
        if ($v['action'] === 'clear') {
            try {
                $w = FnfCalculator::forEmployee($e);
            } catch (\Throwable $ex) {
                report($ex);

                return response()->json(['success' => false, 'message' => 'The F&F working could not be made - '.mb_substr($ex->getMessage(), 0, 300)], 500);
            }
            if ($w['waiting'] > 0) {
                return response()->json(['success' => false, 'message' => 'Hard copies are still awaited (until '.date('d-m-Y', strtotime($w['hard_copy_until'])).'); clear after that date.'], 422);
            }
            $row += ['status' => 'cleared', 'cleared_by' => $user->id, 'cleared_at' => now(), 'clear_remark' => $v['remark'] ?? null,
                'ledger_balance' => $w['ledger_balance'], 'hold_amount' => $w['hold_amount'], 'expense_total' => $w['expense_total'],
                'final_balance' => FnfCalculator::finalBalance($w['ledger_balance'], $w['expense_total'], $row['hr_amount']),
                'working_json' => json_encode($w)];
        } elseif ($v['action'] === 'reopen') {
            abort_unless($user->hasRole('Super Admin') || $user->can('payment_section'), 403, 'Only Finance can reopen a clearance.');
            $row += ['status' => 'pending', 'cleared_by' => null, 'cleared_at' => null, 'working_json' => null,
                'clear_remark' => trim('Reopened by '.$user->name.'. '.($v['remark'] ?? ''))];
        } else {
            $row['status'] = $s->status ?? 'pending';
        }
        try {
            $s ? DB::table('fnf_clearances')->where('id', $s->id)->update($row) : DB::table('fnf_clearances')->insert($row + ['created_at' => now(), 'status' => $row['status'] ?? 'pending']);
        } catch (\Throwable $ex) {
            report($ex);
            \Illuminate\Support\Facades\Log::error('FNF save failed', ['employee' => $id, 'action' => $v['action'], 'error' => $ex->getMessage()]);

            return response()->json(['success' => false, 'message' => 'Not saved - '.mb_substr(preg_replace('/\(Connection:.*$/s', '', $ex->getMessage()), 0, 300)], 500);
        }

        return response()->json(['success' => true, 'message' => ['save' => 'Saved.', 'clear' => 'Finance clearance done.', 'reopen' => 'Reopened.'][$v['action']]]);
    }

    private function csv(array $rows)
    {
        $name = 'fnf_finance_clearance_'.now()->format('Ymd_His').'.csv';

        return response()->streamDownload(function () use ($rows) {
            $o = fopen('php://output', 'w');
            fputcsv($o, ['Emp code', 'Employee', 'Department', 'Resignation date', 'Last working day', 'Ledger balance (held)', 'Payment hold', 'Current month eligible', 'Previous month eligible', 'Other eligible', 'Total expense / payment', 'HR recovery (-) / payment (+)', 'Final balance (+ pay / - recover)', 'Status']);
            foreach ($rows as $r) {
                $w = $r['w'];
                fputcsv($o, [$r['e']->EmpCode, $r['e']->name, $r['e']->department_name, substr((string) $r['e']->DateOfResignation, 0, 10), substr((string) $r['e']->DateOfSepration, 0, 10),
                    $w['ledger_balance'], $w['hold_amount'], $w['expense_current'], $w['expense_previous'], $w['expense_other'], $w['expense_total'], $r['hr'], $r['final'],
                    ($r['s']->status ?? 'pending') === 'cleared' ? 'Finance clearance done' : 'Pending']);
            }
            fclose($o);
        }, $name, ['Content-Type' => 'text/csv']);
    }
}
