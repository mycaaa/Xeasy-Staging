<?php

namespace App\Http\Controllers\admin\reports;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Exports\ExtractionReportExport;
use Maatwebsite\Excel\Facades\Excel;

class ExtractController extends Controller
{
    private function getHrimsEmployeeTable(): string
    {
        return 'hrims.hrm_employee';
    }
    private function getExpenseTable(): string
    {
        $year_id = session('year_id');
        return "y{$year_id}_expenseclaims";
    }
    private function getTempPunchTable(): string
    {
        $year_id = session('year_id');
        return "y{$year_id}_temp_punch_1";
    }
    public function extract()
    {
        $companyId = session('company_id', 1);
        $organogramOptions = \App\Helpers\OrganogramFilterHelper::getOrganogramFilterOptions($companyId);
        $claimTypes = DB::table('claimtype')->where('cgId', 1)->where('ClaimStatus', 'A')->whereNotIn('ClaimId', [5, 19, 20])->select('ClaimId as id', 'ClaimName as name', 'ClaimCode as code')->orderBy('ClaimName')->get();
        return view('admin.reports.extract', array_merge($organogramOptions, compact('claimTypes', 'organogramOptions')));
    }
    public function getData(Request $request)
    {
        try {
            $baseQuery = $this->buildQuery($request);
            $totalRecords = (clone $baseQuery)->count();
            $start = $request->input('start', 0);
            $length = $request->input('length', 25);
            $records = $baseQuery->offset($start)->limit($length)->get();
            $data = $records->map(function ($item) {
                return ['id' => $item->queue_id, 'extract_id' => $item->ExpId, 'employee_name' => $item->employee_name ?? 'N/A', 'claim_type' => $item->ClaimName ?? 'N/A', 'date' => $item->BillDate ? date('d-M-Y', strtotime($item->BillDate)) : 'N/A', 'amount' => number_format($item->FilledTAmt ?? 0, 2), 'status' => strtolower($item->queue_status ?? 'pending'),];
            });
            $summary = $this->getSummaryStats($request);
            return response()->json(['draw' => (int)$request->input('draw'), 'recordsTotal' => $totalRecords, 'recordsFiltered' => $totalRecords, 'data' => $data, 'summary' => $summary,]);
        } catch (\Throwable $e) {
            \Log::error('ExtractController@getData error: ' . $e->getMessage(), ['trace' => $e->getTraceAsString()]);
            return response()->json(['draw' => (int)$request->input('draw'), 'recordsTotal' => 0, 'recordsFiltered' => 0, 'data' => [], 'error' => 'Failed to load data.',], 500);
        }
    }
    private function buildQuery(Request $request)
    {
        $expTable = $this->getExpenseTable();
        $punchTable = $this->getTempPunchTable();
        $empTable = $this->getHrimsEmployeeTable();
        $hrimsDb = \App\Helpers\DatabaseSwitcher::getHrimsDatabaseName();
        $query = DB::table('tbl_extraction_queue as eq')
            ->join("{$expTable} as ec", 'ec.ExpId', '=', 'eq.expid')
            ->join("{$punchTable} as tp", 'tp.id', '=', 'eq.temp_punch_id')
            ->leftJoin('claimtype as ct', 'ct.ClaimId', '=', 'ec.ClaimId')
            ->leftJoin("{$empTable} as emp", 'emp.EmployeeID', '=', 'ec.FilledBy')
            ->leftJoin("{$hrimsDb}.hrm_employee_general as eg", 'eg.EmployeeID', '=', 'emp.EmployeeID')
            ->select('eq.id               AS queue_id', 'eq.expid            AS expid', 'eq.status           AS queue_status', 'eq.created_at       AS queue_created', 'ec.ExpId', 'ec.ClaimId', 'ec.FilledTAmt', 'ec.BillDate', 'ec.ClaimStatus', 'ec.ClaimMonth', 'ec.FilledDate', 'ec.temp_punch_id', 'ec.Remark', 'ct.ClaimName', 'ct.ClaimCode', 'emp.EmpCode', DB::raw("CONCAT(TRIM(emp.Fname), ' ', COALESCE(TRIM(emp.Sname), ''), ' ', COALESCE(TRIM(emp.Lname), '')) AS employee_name"));

        $organogramFilters = \App\Helpers\OrganogramFilterHelper::extractFilters($request);
        \App\Helpers\OrganogramFilterHelper::applyToQuery($query, $organogramFilters, 'eg', 'emp.EmployeeID', $hrimsDb);

        if ($request->filled('date_range')) {
            $dates = array_map('trim', explode(' to ', $request->date_range));
            if (count($dates) === 2 && $dates[0] && $dates[1]) {
                $query->whereBetween('ec.BillDate', [$dates[0], $dates[1]]);
            } elseif (count($dates) === 1 && $dates[0]) {
                $query->whereDate('ec.BillDate', $dates[0]);
            }
        }
        if ($request->filled('claim_type') && $request->claim_type !== '') {
            $query->where('ec.ClaimId', $request->claim_type);
        }
        if ($request->filled('status') && $request->status !== '') {
            $query->where('eq.status', strtoupper($request->status));
        }
        if ($request->filled('employee') && $request->employee !== '') {
            $search = $request->employee;
            $query->where(function ($q) use ($search) {
                $q->where('emp.Fname', 'like', "%{$search}%")->orWhere('emp.Sname', 'like', "%{$search}%")->orWhere('emp.Lname', 'like', "%{$search}%")->orWhere('emp.EmpCode', 'like', "%{$search}%");
            });
        }
        $orderColumnIndex = $request->input('order.0.column', 0);
        $orderDir = $request->input('order.0.dir', 'desc') === 'asc' ? 'asc' : 'desc';
        $columns = [0 => 'eq.id', 1 => 'ec.ExpId', 2 => 'employee_name', 3 => 'ct.ClaimName', 4 => 'ec.BillDate', 5 => 'ec.FilledTAmt', 6 => 'eq.status',];
        $orderColumn = $columns[$orderColumnIndex] ?? 'eq.id';
        $query->orderBy($orderColumn, $orderDir);
        return $query;
    }
    private function getSummaryStats(Request $request)
    {
        try {
            $expTable = $this->getExpenseTable();
            $punchTable = $this->getTempPunchTable();
            $empTable = $this->getHrimsEmployeeTable();
            $hrimsDb = \App\Helpers\DatabaseSwitcher::getHrimsDatabaseName();
            $query = DB::table('tbl_extraction_queue as eq')
                ->join("{$expTable} as ec", 'ec.ExpId', '=', 'eq.expid')
                ->join("{$punchTable} as tp", 'tp.id', '=', 'eq.temp_punch_id')
                ->leftJoin('claimtype as ct', 'ct.ClaimId', '=', 'ec.ClaimId')
                ->leftJoin("{$empTable} as emp", 'emp.EmployeeID', '=', 'ec.FilledBy')
                ->leftJoin("{$hrimsDb}.hrm_employee_general as eg", 'eg.EmployeeID', '=', 'emp.EmployeeID')
                ->whereIn('eq.status', ['PENDING', 'PROCESSING']);

            $organogramFilters = \App\Helpers\OrganogramFilterHelper::extractFilters($request);
            \App\Helpers\OrganogramFilterHelper::applyToQuery($query, $organogramFilters, 'eg', 'emp.EmployeeID', $hrimsDb);
            if ($request->filled('date_range')) {
                $dates = array_map('trim', explode(' to ', $request->date_range));
                if (count($dates) === 2 && $dates[0] && $dates[1]) {
                    $query->whereBetween('ec.BillDate', [$dates[0], $dates[1]]);
                } elseif (count($dates) === 1 && $dates[0]) {
                    $query->whereDate('ec.BillDate', $dates[0]);
                }
            }
            if ($request->filled('claim_type') && $request->claim_type !== '') {
                $query->where('ec.ClaimId', $request->claim_type);
            }
            if ($request->filled('status') && $request->status !== '') {
                $query->where('eq.status', strtoupper($request->status));
            }
            $stats = $query->selectRaw('
                COUNT(eq.id)                         AS total_records,
                COALESCE(SUM(ec.FilledTAmt),0)        AS total_amount,
                SUM(CASE WHEN eq.status="PENDING"   THEN 1 ELSE 0 END) AS pending_count,
                SUM(CASE WHEN eq.status="PROCESSING" THEN 1 ELSE 0 END) AS processing_count
            ')->first();
            return ['total_records' => $stats->total_records ?? 0, 'total_amount' => number_format($stats->total_amount ?? 0, 2), 'pending_count' => $stats->pending_count ?? 0, 'processing_count' => $stats->processing_count ?? 0,];
        } catch (\Throwable $e) {
            \Log::error('ExtractController@summary error: ' . $e->getMessage());
            return ['total_records' => 0, 'total_amount' => '0.00', 'pending_count' => 0, 'processing_count' => 0,];
        }
    }
    public function getDetails($id)
    {
        try {
            $expTable = $this->getExpenseTable();
            $punchTable = $this->getTempPunchTable();
            $empTable = $this->getHrimsEmployeeTable();
            $extraction = DB::table('tbl_extraction_queue as eq')->join("{$expTable} as ec", 'ec.ExpId', '=', 'eq.expid')->join("{$punchTable} as tp", 'tp.id', '=', 'eq.temp_punch_id')->leftJoin('claimtype as ct', 'ct.ClaimId', '=', 'ec.ClaimId')->leftJoin("{$empTable} as emp", 'emp.EmployeeID', '=', 'ec.FilledBy')->select('eq.*', 'ec.*', 'ct.ClaimName', 'ct.ClaimCode', 'emp.EmpCode', DB::raw("CONCAT(TRIM(emp.Fname), ' ', COALESCE(TRIM(emp.Sname), ''), ' ', COALESCE(TRIM(emp.Lname), ''), ' (', emp.EmpCode, ')') AS employee_name"))->where('eq.id', $id)->first();
            if (!$extraction) {
                return response()->json(['error' => 'Record not found'], 404);
            }
            $details = DB::table("{$expTable}details")->where('ExpId', $extraction->ExpId)->orderBy('ExpId')->get();
            return view('admin.reports.extract_details', compact('extraction', 'details'));
        } catch (\Throwable $e) {
            \Log::error('ExtractController@details error: ' . $e->getMessage());
            return response()->json(['error' => 'Failed to load details'], 500);
        }
    }
    public function export(Request $request)
    {
        try {
            $format = $request->input('format', 'excel');
            $data = $this->buildQuery($request)->get();
            if ($data->isEmpty()) {
                return redirect()->back()->with('error', 'No data available for export with current filters.');
            }
            $fileName = 'extraction_report_' . now()->format('Y-m-d_His');
            if ($format === 'csv') {
                return Excel::download(new ExtractionReportExport($data), $fileName . '.csv', \Maatwebsite\Excel\Excel::CSV, ['Content-Type' => 'text/csv']);
            }
            return Excel::download(new ExtractionReportExport($data), $fileName . '.xlsx', \Maatwebsite\Excel\Excel::XLSX);
        } catch (\Throwable $e) {
            \Log::error('ExtractController@export error: ' . $e->getMessage());
            return redirect()->back()->with('error', 'Export failed.');
        }
    }
}
