<?php

namespace App\Http\Controllers\admin;

use App\Helpers\DatabaseSwitcher;
use App\Http\Controllers\Controller;
use App\Services\Reports\VvnrPendingPaymentSteps;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

/** REP-32: VVNR stepwise pending payment report (screen + Excel). */
class VvnrPendingPaymentReportController extends Controller
{
    private function service(): VvnrPendingPaymentSteps
    {
        return new VvnrPendingPaymentSteps(DatabaseSwitcher::getExpenseConnection(11));
    }

    public function index()
    {
        $svc = $this->service();
        $conn = $svc->connection();
        $hrims = DatabaseSwitcher::getHrimsDatabaseName();
        $financialYears = DB::connection($conn)->table('financialyear')->orderByDesc('YearId')->get();
        $yearId = $svc->currentYearId();

        $employees = DB::connection('hrims')->table('hrm_employee as e')
            ->join('hrm_employee_general as g', 'g.EmployeeID', '=', 'e.EmployeeID')
            ->where('e.CompanyId', 11)
            ->select('e.EmployeeID', 'e.EmpCode', 'e.Fname', 'e.Sname', 'e.Lname', 'e.EmpStatus', 'g.RepEmployeeID', 'g.DepartmentId')
            ->orderBy('e.Fname')->get();
        $approvers = DB::connection('hrims')->table('hrm_employee')
            ->whereIn('EmployeeID', $employees->pluck('RepEmployeeID')->filter()->unique()->all())
            ->select('EmployeeID', 'EmpCode', 'Fname', 'Sname', 'Lname')->orderBy('Fname')->get();
        $departments = DB::connection('hrims')->table('core_departments')
            ->whereIn('id', $employees->pluck('DepartmentId')->filter()->unique()->all())
            ->orderBy('department_name')->get(['id', 'department_name']);
        $monthNames = [4 => 'April', 5 => 'May', 6 => 'June', 7 => 'July', 8 => 'August', 9 => 'September', 10 => 'October', 11 => 'November', 12 => 'December', 1 => 'January', 2 => 'February', 3 => 'March'];
        $steps = VvnrPendingPaymentSteps::STEPS;

        return view('reports.vvnr-pending-payment', compact('financialYears', 'yearId', 'employees', 'approvers', 'departments', 'monthNames', 'steps'));
    }

    public function data(Request $request)
    {
        try {
            $svc = $this->service();
            $yearId = (int) ($request->input('year_id') ?: $svc->currentYearId());

            return response()->json(['success' => true, 'year_id' => $yearId] + $svc->build($yearId, $this->filters($request)));
        } catch (\Throwable $e) {
            Log::error('VVNR pending payment report: '.$e->getMessage());

            return response()->json(['success' => false, 'message' => 'Could not build the report: '.$e->getMessage()], 500);
        }
    }

    public function export(Request $request)
    {
        $svc = $this->service();
        $yearId = (int) ($request->input('year_id') ?: $svc->currentYearId());
        $res = $svc->build($yearId, $this->filters($request));
        $yearName = DB::connection($svc->connection())->table('financialyear')->where('YearId', $yearId)->value('Year');

        $book = new Spreadsheet();
        $sum = $book->getActiveSheet()->setTitle('Summary');
        $sum->fromArray([['VVNR stepwise pending payment report - FY '.$yearName], [], ['Step', 'Months', 'Amount (₹)']], null, 'A1');
        $r = 4;
        foreach ($res['summary']['steps'] as $s) {
            $sum->fromArray([[$s['label'].(isset($s['excess']) ? ' (excess paid '.number_format($s['excess'], 0).', short paid '.number_format($s['short'], 0).')' : ''), $s['count'], round($s['amount'], 2)]], null, "A{$r}");
            $r++;
        }
        $r++;
        $sum->fromArray([['Ageing of pending months (days at current step)', 'Months', 'Amount (₹)']], null, "A{$r}");
        $hdr2 = $r;
        foreach ($res['summary']['ageing'] as $b => $s) {
            $r++;
            $sum->fromArray([[$b.' days', $s['count'], round($s['amount'], 2)]], null, "A{$r}");
        }
        $r += 2;
        $sum->setCellValue("A{$r}", 'Step 4 difference = verified amount - approved amount (payment was made on the approved amount). Negative = paid in excess, to recover. Months whose difference was carried into a later payment are counted as closed.');
        foreach (['A1', 'A3:C3', "A{$hdr2}:C{$hdr2}"] as $c) {
            $sum->getStyle($c)->getFont()->setBold(true);
        }
        $sum->getColumnDimension('A')->setWidth(52);
        $sum->getColumnDimension('B')->setWidth(10);
        $sum->getColumnDimension('C')->setWidth(16);

        $det = $book->createSheet()->setTitle('Months');
        $head = ['Month', 'Emp code', 'Employee', 'Status', 'Department', 'Reporting manager', 'Step', 'Days at step', 'Ageing', 'Step amount (₹)',
            'Claims', 'Claimed', 'Approved', 'Paid', 'Verified', 'Difference (verified - approved)', 'Submitted', 'Approved on', 'Paid on', 'Verified on', 'Payment status', 'UTR', 'Note'];
        $det->fromArray([$head], null, 'A1');
        $det->getStyle('A1:W1')->getFont()->setBold(true);
        $i = 2;
        foreach ($res['rows'] as $x) {
            $det->fromArray([[$x['month_name'], $x['emp_code'], $x['emp_name'], $x['emp_status'], $x['department'], $x['rm'], $x['step_label'], $x['age_days'], $x['age_bucket'], $x['step_amount'],
                $x['claims'], $x['claimed'], $x['approved'], $x['paid'], $x['verified'], $x['difference'], $x['submitted_on'], $x['approved_on'], $x['paid_on'], $x['verified_on'], $x['payment_status'], $x['utr'], $x['note']]], null, "A{$i}");
            $det->setCellValueExplicit("B{$i}", $x['emp_code'], \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
            $det->setCellValueExplicit("V{$i}", $x['utr'], \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
            $i++;
        }
        $det->setAutoFilter('A1:W'.max(1, $i - 1));
        $det->freezePane('A2');
        foreach (range('A', 'W') as $c) {
            $det->getColumnDimension($c)->setAutoSize(true);
        }

        \App\Support\ReportStamp::workbook($book); // STAMP-01
        $file = 'VVNR_pending_payment_steps_FY'.str_replace('-', '_', (string) $yearName).'_'.date('Ymd_His').'.xlsx';

        return response()->streamDownload(function () use ($book) {
            (new Xlsx($book))->save('php://output');
        }, $file, ['Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet']);
    }

    private function filters(Request $request): array
    {
        return [
            'month' => $request->input('month'),
            'department_id' => $request->input('department_id'),
            'employee_id' => $request->input('employee_id'),
            'approver_id' => $request->input('approver_id'),
            'step' => $request->input('step'),
            'include_closed' => $request->boolean('include_closed'),
        ];
    }
}
