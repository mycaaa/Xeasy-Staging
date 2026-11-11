<?php

namespace App\Http\Controllers\admin;

use App\Helpers\DatabaseSwitcher;
use App\Helpers\OrganogramFilterHelper;
use App\Http\Controllers\Controller;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class NotShowingReportController extends Controller
{
    /**
     * Display the Not Showing Report page
     */
    public function index()
    {
        $companyId = session('company_id', 11);
        $yearId = session('year_id');

        $organogramOptions = OrganogramFilterHelper::getOrganogramFilterOptions($companyId);
        $hrimsDb = DatabaseSwitcher::getHrimsDatabaseName();

        $employees = DB::connection('hrims')
            ->table('hrm_employee as e')
            ->join('hrm_employee_general as g', 'e.EmployeeID', '=', 'g.EmployeeID')
            ->where('e.CompanyId', $companyId) // CompanyId is on hrm_employee, not hrm_employee_general
            ->where('e.EmpStatus', 'A')
            ->select('e.EmployeeID', 'e.EmpCode', 'e.EmpCode_New', 'e.Fname', 'e.Sname', 'e.Lname')
            ->orderBy('e.Fname')
            ->get();

        // VVNR-YEAR-01: the company's own year list, defaulting to the year that contains today
        $expenseConn = session('expense_connection') ?: DatabaseSwitcher::getExpenseConnection($companyId);
        $financialYears = DB::connection($expenseConn)->table('financialyear')->orderBy('YearId', 'desc')->get();
        $today = now('Asia/Kolkata')->toDateString();
        $yearId = $financialYears->first(fn ($y) => $y->FromDate <= $today && $y->Todate >= $today)->YearId ?? $yearId;

        $monthNames = [
            4 => 'April', 5 => 'May', 6 => 'June', 7 => 'July',
            8 => 'August', 9 => 'September', 10 => 'October', 11 => 'November',
            12 => 'December', 1 => 'January', 2 => 'February', 3 => 'March'
        ];

        return view('reports.not-showing-report', array_merge($organogramOptions, compact(
            'employees', 'financialYears', 'monthNames', 'yearId'
        )));
    }

    /**
     * Fetch filtered report data via AJAX
     */
    public function getData(Request $request)
    {
        try {
            $yearId = (int) ($request->input('year_id', session('year_id')));
            $expenseConn = session('expense_connection') ?: DatabaseSwitcher::getExpenseConnection();
            $hrimsDb = DatabaseSwitcher::getHrimsDatabaseName();
            $tableName = "y{$yearId}_monthexpensefinal";

            // VVNR-COURIER-01: the column comes from migration 2026_11_05_000011 (no ALTER TABLE from a page request)
            if (!\Illuminate\Support\Facades\Schema::connection($expenseConn)->hasColumn($tableName, 'courier_date')) {
                return response()->json(['success' => false, 'data' => [], 'message' => 'Courier Date is not set up on this database yet (run migration 2026_11_05_000011).']);
            }

            $query = DB::connection($expenseConn)
                ->table("{$tableName} as mef")
                ->leftJoin("{$hrimsDb}.hrm_employee as e", 'mef.EmployeeID', '=', 'e.EmployeeID')
                ->leftJoin("{$hrimsDb}.hrm_employee_general as g", 'e.EmployeeID', '=', 'g.EmployeeID')
                ->leftJoin("{$hrimsDb}.core_departments as dep", 'g.DepartmentId', '=', 'dep.id')
                ->where('mef.YearId', $yearId)
                ->whereNotNull('mef.courier_date')
                ->where('mef.courier_date', '!=', '')
                ->where('mef.courier_date', '!=', '0000-00-00')
                ->where(function ($q) {
                    $q->whereNull('mef.RecevingDate')
                      ->orWhere('mef.RecevingDate', '=', '')
                      ->orWhere('mef.RecevingDate', '=', '0000-00-00');
                })
                ->select([
                    'mef.id',
                    'mef.EmployeeID',
                    'mef.Month',
                    'mef.YearId',
                    'mef.Claim_Amount',
                    'mef.Total_Claim',
                    'mef.courier_date',
                    'mef.RecevingDate',
                    'mef.DocateNo',
                    'mef.Agency',
                    'mef.DocRmk',
                    'e.EmpCode',
                    'e.EmpCode_New',
                    DB::raw("TRIM(CONCAT(e.Fname, ' ', COALESCE(e.Sname, ''), ' ', e.Lname)) as EmpName"),
                    'dep.department_name',
                ]);

            if ($request->filled('month')) {
                $query->where('mef.Month', $request->month);
            }

            if ($request->filled('employee_id')) {
                $query->where('mef.EmployeeID', $request->employee_id);
            }

            if ($request->filled('department_id')) {
                $query->where('g.DepartmentId', $request->department_id);
            }

            $records = $query->orderBy('mef.Month', 'asc')->orderBy('e.Fname', 'asc')->get();

            $monthNames = [
                1 => 'January', 2 => 'February', 3 => 'March', 4 => 'April',
                5 => 'May', 6 => 'June', 7 => 'July', 8 => 'August',
                9 => 'September', 10 => 'October', 11 => 'November', 12 => 'December'
            ];

            // Batch fetch previous month payment dates
            $data = [];
            foreach ($records as $row) {
                $prevMonth = ($row->Month > 1) ? $row->Month - 1 : 12;
                $prevYearId = ($row->Month > 1) ? $yearId : ($yearId - 1);
                $prevTable = "y{$prevYearId}_monthexpensefinal";

                $prevPayDate = '—';
                try {
                    $prevRow = DB::connection($expenseConn)
                        ->table($prevTable)
                        ->where('EmployeeID', $row->EmployeeID)
                        ->where('Month', $prevMonth)
                        ->where('YearId', $prevYearId)
                        ->select('Fin_PayDate', 'payment_date')
                        ->first();

                    if ($prevRow) {
                        $pDate = (!empty($prevRow->Fin_PayDate) && \App\Support\ZeroDate::has($prevRow->Fin_PayDate))
                            ? $prevRow->Fin_PayDate
                            : ((!empty($prevRow->payment_date) && \App\Support\ZeroDate::has($prevRow->payment_date)) ? $prevRow->payment_date : null);

                        if ($pDate) {
                            $prevPayDate = Carbon::parse($pDate)->format('d-m-Y');
                        }
                    }
                } catch (\Exception $e) {
                    // Ignore previous year table missing
                }

                $courierDateFormatted = (!empty($row->courier_date) && \App\Support\ZeroDate::has($row->courier_date))
                    ? Carbon::parse($row->courier_date)->format('d-m-Y')
                    : '—';

                $receiveDateFormatted = (!empty($row->RecevingDate) && \App\Support\ZeroDate::has($row->RecevingDate))
                    ? Carbon::parse($row->RecevingDate)->format('d-m-Y')
                    : '—';

                $claimDetails = ($row->Total_Claim ?? 0) . ' Claims (₹' . number_format((float) ($row->Claim_Amount ?? 0), 2) . ')';

                $data[] = [
                    'id' => $row->id,
                    'employee_id' => $row->EmployeeID,
                    'month_num' => $row->Month,
                    'month' => $monthNames[$row->Month] ?? "Month {$row->Month}",
                    'emp_code' => $row->EmpCode_New ?: $row->EmpCode,
                    'emp_name' => $row->EmpName,
                    'department' => $row->department_name ?? '—',
                    'claim_details' => $claimDetails,
                    'total_claim' => (int) ($row->Total_Claim ?? 0),
                    'claim_amount' => (float) ($row->Claim_Amount ?? 0),
                    'courier_date' => $courierDateFormatted,
                    'courier_date_raw' => $row->courier_date,
                    'receive_date' => $receiveDateFormatted,
                    'prev_payment_date' => $prevPayDate,
                    'docket_no' => $row->DocateNo ?? '',
                    'agency' => $row->Agency ?? '',
                    'remark' => $row->DocRmk ?? '',
                ];
            }

            return response()->json([
                'success' => true,
                'data' => $data,
                'total' => count($data),
            ]);

        } catch (\Exception $e) {
            Log::error('NotShowingReport getData error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Error retrieving report data: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Update Receive Date for Verification directly from the report
     */
    public function updateReceiveDate(Request $request)
    {
        try {
            $request->validate([
                'employee_id' => 'required|integer',
                'month' => 'required|integer',
                'receiving_date' => 'required|date',
                'doc_rmk' => 'nullable|string'
            ]);

            $yearId = session('year_id');
            $expenseConn = session('expense_connection') ?: DatabaseSwitcher::getExpenseConnection();
            $tableName = "y{$yearId}_monthexpensefinal";

            $updated = DB::connection($expenseConn)
                ->table($tableName)
                ->where('EmployeeID', $request->employee_id)
                ->where('Month', $request->month)
                ->where('YearId', $yearId)
                ->update([
                    'RecevingDate' => $request->receiving_date,
                    'DocRmk' => $request->doc_rmk
                ]);

            if ($updated !== false) {
                return response()->json([
                    'success' => true,
                    'message' => 'Receive Date for Verification updated successfully'
                ]);
            }

            return response()->json([
                'success' => false,
                'message' => 'Failed to update record'
            ], 400);

        } catch (\Exception $e) {
            Log::error('NotShowingReport updateReceiveDate error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Export Not Showing Report to CSV
     */
    public function export(Request $request)
    {
        $yearId = (int) ($request->input('year_id', session('year_id')));
        $dataResponse = $this->getData($request);
        $result = json_decode($dataResponse->getContent(), true);

        $headers = [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => 'attachment; filename="not_showing_report_' . date('Ymd_His') . '.csv"',
            'Pragma' => 'no-cache',
            'Cache-Control' => 'must-revalidate, post-check=0, pre-check=0',
            'Expires' => '0',
        ];

        $callback = function () use ($result) {
            $file = fopen('php://output', 'w');
            fputcsv($file, [
                '#',
                'Month',
                'Employee Code',
                'Employee Name',
                'Department',
                'Claim Details',
                'Courier Date (Received by DMS)',
                'Receive Date for Verification',
                'Payment Date of Previous Month',
                'Docket No',
                'Agency'
            ]);

            if (!empty($result['data'])) {
                foreach ($result['data'] as $idx => $r) {
                    fputcsv($file, [
                        $idx + 1,
                        $r['month'],
                        $r['emp_code'],
                        $r['emp_name'],
                        $r['department'],
                        $r['claim_details'],
                        $r['courier_date'],
                        $r['receive_date'],
                        $r['prev_payment_date'],
                        $r['docket_no'],
                        $r['agency'],
                    ]);
                }
            }

            fputcsv($file, []); fputcsv($file, \App\Support\ReportStamp::csvRow()); // STAMP-01
            fclose($file);
        };

        return response()->stream($callback, 200, $headers);
    }
}
