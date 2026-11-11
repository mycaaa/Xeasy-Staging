<?php

namespace App\Http\Controllers\admin;

use App\Exports\OdometerContinuityExport;
use App\Helpers\OrganogramFilterHelper;
use App\Http\Controllers\Controller;
use App\Models\CoreDepartments;
use App\Models\CoreFunctions;
use App\Models\CoreGrade;
use App\Models\CoreVertical;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Maatwebsite\Excel\Facades\Excel;

class OdometerContinuityReportController extends Controller
{
    /**
     * Display the Odometer Continuity Report page with filters.
     */
    public function index(Request $request)
    {
        $companyId = session('company_id', 1);
        $yearId = session('year_id', 8);

        $expConn = session('expense_connection', 'expense');
        $hrimsConn = session('hrims_connection', 'hrims');

        $organogramOptions = OrganogramFilterHelper::getOrganogramFilterOptions($companyId);

        $functions = $organogramOptions['functions'] ?? CoreFunctions::where('is_active', 1)->orderBy('function_name')->get(['id', 'function_name']);
        $verticals = $organogramOptions['verticals'] ?? CoreVertical::where('is_active', 1)->orderBy('vertical_name')->get(['id', 'vertical_name']);
        $departments = $organogramOptions['departments'] ?? CoreDepartments::where('is_active', 1)->orderBy('department_name')->get(['id', 'department_name']);
        $grades = CoreGrade::where('is_active', 1)->orderBy('grade_name')->get(['id', 'grade_name']);

        // Fetch Designations from HRIMS
        $designations = collect();
        try {
            $designations = DB::connection($hrimsConn)->table('core_designation')
                ->where('is_active', 1)
                ->orderBy('designation_name')
                ->get(['id as DesigId', 'designation_name as DesigName']);
        } catch (\Throwable $e) {
            Log::warning('Could not load designations: ' . $e->getMessage());
        }

        // Fetch Headquarters from HRIMS (only distinct headquarters for active employees in this company)
        $headquarters = collect();
        try {
            $headquarters = DB::connection($hrimsConn)->table('hrm_employee_general as eg')
                ->join('hrm_employee as e', 'e.EmployeeID', '=', 'eg.EmployeeID')
                ->leftJoin('core_city_village_by_state as hq', 'eg.HqId', '=', 'hq.id')
                ->leftJoin('core_territory as tr', 'eg.TerrId', '=', 'tr.id')
                ->where('e.CompanyId', $companyId)
                ->where('e.EmpStatus', 'A')
                ->where(function($q) {
                    $q->where('eg.HqId', '>', 0)
                      ->orWhere('eg.TerrId', '>', 0);
                })
                ->select([
                    'eg.HqId as HqId',
                    DB::raw('CASE WHEN eg.TerrId > 0 AND tr.territory_name IS NOT NULL THEN tr.territory_name ELSE hq.city_village_name END as HqName')
                ])
                ->whereNotNull(DB::raw('CASE WHEN eg.TerrId > 0 AND tr.territory_name IS NOT NULL THEN tr.territory_name ELSE hq.city_village_name END'))
                ->distinct()
                ->orderBy('HqName')
                ->get();
        } catch (\Throwable $e) {
            Log::warning('Could not load headquarters: ' . $e->getMessage());
        }

        // Fetch Active Employees for filter
        $employees = collect();
        try {
            $employees = DB::connection($hrimsConn)->table('hrm_employee as e')
                ->where('e.CompanyId', $companyId)
                ->where('e.EmpStatus', 'A')
                ->orderBy('e.EmpCode')
                ->get(['e.EmployeeID', 'e.EmpCode', 'e.Fname', 'e.Sname', 'e.Lname']);
        } catch (\Throwable $e) {
            Log::warning('Could not load employees: ' . $e->getMessage());
        }

        // Fetch Financial Years
        $financialYears = collect();
        try {
            $financialYears = DB::connection($expConn)->table('financialyear')
                ->orderByDesc('YearId')
                ->get(['YearId', 'Year']);
        } catch (\Throwable $e) {
            Log::warning('Could not load financial years: ' . $e->getMessage());
        }

        return view('reports.odometer_continuity_report', array_merge($organogramOptions, compact(
            'functions',
            'verticals',
            'departments',
            'grades',
            'designations',
            'headquarters',
            'employees',
            'financialYears',
            'yearId',
            'companyId'
        )));
    }

    /**
     * AJAX endpoint to fetch odometer continuity records with computed gaps.
     */
    public function getData(Request $request)
    {
        try {
            $result = $this->buildReportData($request);

            return response()->json([
                'success' => true,
                'summary' => $result['summary'],
                'data' => $result['rows'],
                'total_count' => count($result['rows']),
            ]);
        } catch (\Throwable $e) {
            Log::error('OdometerContinuityReport getData error: ' . $e->getMessage() . ' | Trace: ' . $e->getTraceAsString());
            return response()->json([
                'success' => false,
                'message' => 'Error fetching odometer continuity report: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Export Odometer Continuity report to Excel.
     */
    public function export(Request $request)
    {
        ini_set('max_execution_time', 0);
        ini_set('memory_limit', '1024M');

        try {
            $result = $this->buildReportData($request, true);
            $fileName = 'Odometer_Continuity_Report_' . now()->format('Ymd_His') . '.xlsx';

            return Excel::download(new OdometerContinuityExport($result['rows']), $fileName);
        } catch (\Throwable $e) {
            Log::error('OdometerContinuityReport export error: ' . $e->getMessage());
            return back()->with('error', 'Export failed: ' . $e->getMessage());
        }
    }

    /**
     * Core business logic to query claims, order chronologically by employee,
     * compute continuity between previous closing and current opening, and apply filters.
     */
    private function buildReportData(Request $request, bool $forExport = false): array
    {
        $yearId = (int) $request->input('year_id', session('year_id', 8));
        $companyId = (int) $request->input('company_id', session('company_id', 1));

        $expConn = session('expense_connection', 'expense');
        $hrimsConn = session('hrims_connection', 'hrims');
        $hrimsDb = config("database.connections.{$hrimsConn}.database", 'hrims');

        $claimsTable = "y{$yearId}_expenseclaims";
        $wheelerTable = "y{$yearId}_24_wheeler_entry";

        // Filter Inputs
        $fromDate = $request->input('from_date');
        $toDate = $request->input('to_date');
        $month = $request->input('month');
        $departmentIds = array_filter((array) $request->input('department_ids', []));
        $designationIds = array_filter((array) $request->input('designation_ids', []));
        $verticalIds = array_filter((array) $request->input('vertical_ids', []));
        $functionIds = array_filter((array) $request->input('function_ids', []));
        $gradeIds = array_filter((array) $request->input('grade_ids', []));
        $hqIds = array_filter((array) ($request->input('headquarter_ids') ?: $request->input('hq_ids', [])));
        $employeeIds = array_filter((array) $request->input('employee_ids', []));
        $vehicleType = $request->input('vehicle_type'); // '2W', '4W', or empty
        $claimStatus = $request->input('claim_status');
        $continuityStatus = $request->input('continuity_status', 'all'); // 'all', 'only_gaps', 'continuous', 'positive_gap', 'overlap'
        $minGap = $request->filled('min_gap') ? (float) $request->input('min_gap') : null;
        $maxGap = $request->filled('max_gap') ? (float) $request->input('max_gap') : null;
        $minKm = $request->filled('min_km') ? (float) $request->input('min_km') : null;
        $maxKm = $request->filled('max_km') ? (float) $request->input('max_km') : null;
        $search = trim($request->input('search', ''));

        // Base Query on expenseclaims for ClaimId = 7 (Running KM / Odometer)
        $query = DB::connection($expConn)->table("{$claimsTable} as ec")
            ->where('ec.ClaimId', 7)
            ->whereNotIn('ec.ClaimStatus', ['Deactivate', 'Draft']);

        // Check if 2W/4W wheeler entry table exists
        $hasWheelerTable = false;
        try {
            $hasWheelerTable = DB::connection($expConn)->getSchemaBuilder()->hasTable($wheelerTable);
        } catch (\Throwable $e) {
            $hasWheelerTable = false;
        }

        // UAT-03: the 2/4-wheeler table is not indexed on ExpId (VVNR y6 has no index at all), so a
        // join made the export run past 2 minutes. Its four columns are now read in batches after
        // the main query and merged in PHP.

        // Join HRIMS Tables
        $query->join("{$hrimsDb}.hrm_employee as emp", 'ec.CrBy', '=', 'emp.EmployeeID')
            ->leftJoin("{$hrimsDb}.hrm_employee_general as eg", 'emp.EmployeeID', '=', 'eg.EmployeeID')
            ->leftJoin("{$hrimsDb}.core_departments as dep", 'eg.DepartmentId', '=', 'dep.id')
            ->leftJoin("{$hrimsDb}.core_designation as desig", 'eg.DesigId', '=', 'desig.id')
            ->leftJoin("{$hrimsDb}.core_territory as tr", 'eg.TerrId', '=', 'tr.id')
            ->leftJoin("{$hrimsDb}.core_city_village_by_state as hq", 'eg.HqId', '=', 'hq.id')
            ->leftJoin("{$hrimsDb}.core_verticals as vt", 'eg.EmpVertical', '=', 'vt.id')
            ->leftJoin("{$hrimsDb}.core_functions as fn", 'eg.EmpFunction', '=', 'fn.id')
            ->leftJoin("{$hrimsDb}.core_grades as gr", 'eg.GradeId', '=', 'gr.id');

        // Apply Company filter
        if ($companyId > 0) {
            $query->where('emp.CompanyId', $companyId);
        }

        // Date Range Filters
        if (!empty($fromDate) && !empty($toDate)) {
            $query->whereBetween('ec.BillDate', [$fromDate, $toDate]);
        } elseif (!empty($fromDate)) {
            $query->where('ec.BillDate', '>=', $fromDate);
        } elseif (!empty($toDate)) {
            $query->where('ec.BillDate', '<=', $toDate);
        } elseif (!empty($month)) {
            $query->where('ec.ClaimMonth', (int) $month);
        } else {
            // Default to start of current financial year or last 90 days if nothing passed
            $query->where('ec.BillDate', '>=', now()->subMonths(3)->startOfMonth()->toDateString());
        }

        // Employee filter
        if (!empty($employeeIds)) {
            $query->whereIn('ec.CrBy', $employeeIds);
        }

        // Apply All Organogram & Policy Filters (Function, Vertical, Department, Sub-Dept, BU, Zone, Region, Territory, HQ, Policy)
        $organogramFilters = OrganogramFilterHelper::extractFilters($request);
        OrganogramFilterHelper::applyToQuery($query, $organogramFilters, 'eg', 'emp.EmployeeID', $hrimsDb);

        // Additional Specific Filters
        if (!empty($designationIds)) {
            $query->whereIn('eg.DesigId', $designationIds);
        }
        if (!empty($gradeIds)) {
            $query->whereIn('eg.GradeId', $gradeIds);
        }

        // Vehicle Type Filter
        if ($vehicleType === '2W') {
            $query->whereIn('ec.WType', [1, 2]);
        } elseif ($vehicleType === '4W') {
            $query->whereIn('ec.WType', [3, 4]);
        }

        // Claim Status Filter
        if (!empty($claimStatus) && $claimStatus !== 'all') {
            $query->where('ec.ClaimStatus', $claimStatus);
        }

        // Search Filter (EmpCode, Employee Name, ExpId)
        if (!empty($search)) {
            $query->where(function ($q) use ($search) {
                $q->where('emp.EmpCode', 'LIKE', "%{$search}%")
                  ->orWhere(DB::raw("CONCAT(emp.Fname, ' ', COALESCE(emp.Sname, ''), ' ', emp.Lname)"), 'LIKE', "%{$search}%")
                  ->orWhere('ec.ExpId', 'LIKE', "%{$search}%")
                  ->orWhere('dep.department_name', 'LIKE', "%{$search}%");
            });
        }

        // Select Columns
        $selects = [
            'ec.ExpId',
            'ec.ClaimId',
            'ec.CrBy',
            'ec.BillDate',
            'ec.CrDate',
            'ec.WType',
            'ec.RatePerKM',
            'ec.FilledTAmt',
            'ec.ClaimMonth',
            'ec.ClaimYearId',
            'ec.odomtr_opening',
            'ec.odomtr_closing',
            'ec.opening_filepath',
            'ec.closing_filepath',
            'ec.TotKm',
            'ec.ClaimStatus',
            'ec.DateEntryRemark',
            'ec.Remark',
            'emp.EmployeeID',
            'emp.EmpCode',
            'emp.Fname',
            'emp.Sname',
            'emp.Lname',
            'dep.department_name',
            'desig.designation_name as DesigName',
            DB::raw('CASE WHEN eg.TerrId > 0 THEN tr.territory_name ELSE hq.city_village_name END as HqName'),
            'vt.vertical_name',
            'fn.function_name',
        ];


        // Fetch records ordered by employee and date ASCENDING so continuity is strictly chronological
        $records = $query->select($selects)
            ->orderBy('emp.EmpCode', 'ASC')
            ->orderBy('ec.CrBy', 'ASC')
            ->orderBy('ec.BillDate', 'ASC')
            ->orderBy('ec.ExpId', 'ASC')
            ->get();

        if ($hasWheelerTable && $records->isNotEmpty()) {
            $wheel = [];
            foreach ($records->pluck('ExpId')->unique()->chunk(5000) as $ids) {
                DB::connection($expConn)->table($wheelerTable)
                    ->whereIn('ExpId', $ids->values()->all())
                    ->select('ExpId', 'DistTraOpen', 'DistTraClose', 'Totalkm', 'VehicleReg')
                    ->get()
                    ->each(function ($w) use (&$wheel) {
                        $wheel[$w->ExpId] ??= $w; // first row per claim, as the left join's first match
                    });
            }
            foreach ($records as $r) {
                $w = $wheel[$r->ExpId] ?? null;
                $r->wheeler_open = $w->DistTraOpen ?? null;
                $r->wheeler_close = $w->DistTraClose ?? null;
                $r->wheeler_totalkm = $w->Totalkm ?? null;
                $r->VehicleReg = $w->VehicleReg ?? null;
            }
        }

        if ($records->isEmpty()) {
            return [
                'rows' => [],
                'summary' => [
                    'total_claims' => 0,
                    'total_employees' => 0,
                    'continuous_count' => 0,
                    'gap_count' => 0,
                    'overlap_count' => 0,
                    'initial_count' => 0,
                    'total_claimed_km' => 0,
                    'total_gap_km' => 0,
                ],
            ];
        }

        // Color palettes for employees (8 distinct pleasant pastel colors)
        $palette = [
            'emp-color-0', // soft blue
            'emp-color-1', // soft emerald
            'emp-color-2', // soft purple
            'emp-color-3', // soft amber
            'emp-color-4', // soft teal
            'emp-color-5', // soft rose
            'emp-color-6', // soft indigo
            'emp-color-7', // soft cyan
        ];

        $empColorMap = [];
        $empIndex = 0;

        // Group records by employee (CrBy)
        $grouped = $records->groupBy('CrBy');

        $allRows = [];
        $totalClaimedKm = 0;
        $totalGapKm = 0;
        $continuousCount = 0;
        $gapCount = 0;
        $overlapCount = 0;
        $initialCount = 0;
        $uniqueEmployees = $grouped->count();

        foreach ($grouped as $empId => $empRecords) {
            if (!isset($empColorMap[$empId])) {
                $empColorMap[$empId] = $palette[$empIndex % count($palette)];
                $empIndex++;
            }
            $empClass = $empColorMap[$empId];

            $lastClosing = null;
            $lastBillDate = null;
            $lastBillDateFormatted = null;
            $lastExpId = null;
            $entryIndex = 0;

            foreach ($empRecords as $item) {
                $entryIndex++;

                // Resolve Opening and Closing KM
                $openKm = (float) ($item->odomtr_opening ?? 0);
                $closeKm = (float) ($item->odomtr_closing ?? 0);

                if ($openKm == 0 && isset($item->wheeler_open) && $item->wheeler_open > 0) {
                    $openKm = (float) $item->wheeler_open;
                }
                if ($closeKm == 0 && isset($item->wheeler_close) && $item->wheeler_close > 0) {
                    $closeKm = (float) $item->wheeler_close;
                }

                $claimedKm = (float) ($item->TotKm ?? 0);
                if ($claimedKm == 0 && $closeKm > $openKm) {
                    $claimedKm = round($closeKm - $openKm, 2);
                }

                // Month formatting for grouping
                $billDateObj = !empty($item->BillDate) ? Carbon::parse($item->BillDate) : null;
                $monthName = $billDateObj ? $billDateObj->format('F Y') : 'Unknown';
                $formattedDate = $billDateObj ? $billDateObj->format('d-m-Y') : '-';
                $dayOfWeek = $billDateObj ? $billDateObj->format('D') : '';

                // Calculate Continuity Gap:
                // Today's Opening - Yesterday's Closing
                $prevClosing = null;
                $prevExpId = null;
                $prevDate = null;
                $prevDateFormatted = null;
                $comparisonDetail = 'First entry in sequence (No prior closing reading)';
                $gap = 0.0;
                $status = 'initial';
                $statusBadge = 'badge-initial';
                $statusText = 'First Entry';

                if ($lastClosing !== null) {
                    $prevClosing = $lastClosing;
                    $prevExpId = $lastExpId;
                    $prevDate = $lastBillDate;
                    $prevDateFormatted = $lastBillDateFormatted;
                    $gap = round($openKm - $prevClosing, 2);
                    $comparisonDetail = "Today Open: " . number_format($openKm, 1) . " KM ({$formattedDate}) vs Prev Close: " . number_format($prevClosing, 1) . " KM ({$prevDateFormatted}, Exp #{$prevExpId})";

                    if ($gap == 0.0) {
                        $status = 'continuous';
                        $statusBadge = 'badge-continuous';
                        $statusText = 'Continuous (0 km)';
                        $continuousCount++;
                    } elseif ($gap > 0) {
                        $status = 'gap';
                        $statusBadge = ($gap > 50) ? 'badge-danger-gap' : 'badge-warning-gap';
                        $statusText = '+' . number_format($gap, 1) . ' km Gap';
                        $gapCount++;
                        $totalGapKm += $gap;
                    } else {
                        $status = 'overlap';
                        $statusBadge = 'badge-overlap';
                        $statusText = number_format($gap, 1) . ' km Overlap';
                        $overlapCount++;
                    }
                } else {
                    $initialCount++;
                }

                // Update last state for the next row of this employee
                $lastClosing = $closeKm;
                $lastBillDate = $item->BillDate;
                $lastBillDateFormatted = $formattedDate;
                $lastExpId = $item->ExpId;

                // Format Employee Name
                $empName = trim("{$item->Fname} " . ($item->Sname ? "{$item->Sname} " : '') . "{$item->Lname}");

                // Format Vehicle Type string: 2W / 4W only
                $wTypeStr = in_array($item->WType, [3, 4]) ? '4W' : '2W';

                $empId = (int) $item->CrBy;
                $rowYearId = (int) ($item->ClaimYearId ?: $yearId);

                // Image URLs with fallback
                $openImgUrl = $this->resolveImageUrl($item->opening_filepath, $rowYearId, $empId);
                $closeImgUrl = $this->resolveImageUrl($item->closing_filepath, $rowYearId, $empId);
                $backupOpenUrl = $this->getBackupImageUrl($item->opening_filepath, $rowYearId, $empId);
                $backupCloseUrl = $this->getBackupImageUrl($item->closing_filepath, $rowYearId, $empId);

                $row = [
                    'exp_id' => $item->ExpId,
                    'emp_id' => $empId,
                    'emp_code' => $item->EmpCode ?? '-',
                    'emp_name' => $empName,
                    'emp_color_class' => $empClass,
                    'department' => $item->department_name ?? '-',
                    'designation' => $item->DesigName ?? '-',
                    'headquarter' => $item->HqName ?? '-',
                    'vertical' => $item->vertical_name ?? '-',
                    'function' => $item->function_name ?? '-',
                    'bill_date' => $formattedDate,
                    'bill_date_raw' => $item->BillDate,
                    'day_of_week' => $dayOfWeek,
                    'month_name' => $monthName,
                    'entry_index' => $entryIndex,
                    'vehicle_type' => $wTypeStr,
                    'vehicle_reg' => $item->VehicleReg ?? '',
                    'rate_per_km' => (float) ($item->RatePerKM ?? 0),
                    'claimed_amount' => (float) ($item->FilledTAmt ?? 0),
                    'open_km' => $openKm,
                    'close_km' => $closeKm,
                    'claimed_km' => $claimedKm,
                    'prev_closing_km' => $prevClosing,
                    'prev_exp_id' => $prevExpId,
                    'prev_date' => $prevDateFormatted,
                    'prev_date_raw' => $prevDate,
                    'comparison_detail' => $comparisonDetail,
                    'gap_km' => $gap,
                    'abs_gap_km' => abs($gap),
                    'status' => $status,
                    'status_badge' => $statusBadge,
                    'status_text' => $statusText,
                    'claim_status' => $item->ClaimStatus ?? '-',
                    'year_id' => $rowYearId,
                    'has_open_img' => !empty($item->opening_filepath),
                    'has_close_img' => !empty($item->closing_filepath),
                    'open_img' => $openImgUrl ?: '',
                    'close_img' => $closeImgUrl ?: '',
                    'backup_open_img' => $backupOpenUrl,
                    'backup_close_img' => $backupCloseUrl,
                    'remarks' => $item->DateEntryRemark ?: ($item->Remark ?: '-'),
                ];

                // Post-Continuity Filtering
                $keepRow = true;

                // 1. Continuity Status filter
                if ($continuityStatus === 'only_gaps') {
                    // Only rows with gap != 0 (excluding initial)
                    if ($status === 'continuous' || $status === 'initial') {
                        $keepRow = false;
                    }
                } elseif ($continuityStatus === 'continuous') {
                    if ($status !== 'continuous') {
                        $keepRow = false;
                    }
                } elseif ($continuityStatus === 'positive_gap') {
                    if ($status !== 'gap') {
                        $keepRow = false;
                    }
                } elseif ($continuityStatus === 'overlap') {
                    if ($status !== 'overlap') {
                        $keepRow = false;
                    }
                }

                // 2. Gap KM Range Filter
                if ($minGap !== null && $prevClosing !== null) {
                    if (abs($gap) < $minGap) {
                        $keepRow = false;
                    }
                }
                if ($maxGap !== null && $prevClosing !== null) {
                    if (abs($gap) > $maxGap) {
                        $keepRow = false;
                    }
                }

                // 3. Claimed KM Range Filter
                if ($minKm !== null && $claimedKm < $minKm) {
                    $keepRow = false;
                }
                if ($maxKm !== null && $claimedKm > $maxKm) {
                    $keepRow = false;
                }

                if ($keepRow) {
                    $allRows[] = $row;
                    $totalClaimedKm += $claimedKm;
                }
            }
        }

        return [
            'rows' => $allRows,
            'summary' => [
                'total_claims' => count($allRows),
                'total_employees' => $uniqueEmployees,
                'continuous_count' => $continuousCount,
                'gap_count' => $gapCount,
                'overlap_count' => $overlapCount,
                'initial_count' => $initialCount,
                'total_claimed_km' => round($totalClaimedKm, 2),
                'total_gap_km' => round($totalGapKm, 2),
            ],
        ];
    }

    /**
     * Resolve public URL for uploaded odometer images.
     */
    private function resolveImageUrl(?string $fileName, int $yearId, int $empId): ?string
    {
        if (empty($fileName)) {
            return null;
        }

        if (filter_var($fileName, FILTER_VALIDATE_URL)) {
            return $fileName;
        }

        $fileName = ltrim($fileName, '/');
        $companyId = session('company_id', 1);
        $basePath = ($companyId == 11) ? 'VVNR_Expense' : 'Expense';

        try {
            return Storage::disk('s3')->url("{$basePath}/{$yearId}/{$empId}/{$fileName}");
        } catch (\Throwable $e) {
            return $this->getBackupImageUrl($fileName, $yearId, $empId);
        }
    }

    /**
     * Resolve fallback backup URL from vnragro.com.
     */
    private function getBackupImageUrl(?string $fileName, int $yearId, int $empId): string
    {
        if (empty($fileName)) {
            return '';
        }

        if (filter_var($fileName, FILTER_VALIDATE_URL)) {
            return $fileName;
        }

        $companyId = session('company_id', 1);
        $backupFolder = ($companyId == 11) ? 'vxeasyfile' : 'xeasyfile';
        $sanitized = preg_replace('/[^A-Za-z0-9\-\_\.]/', '_', basename($fileName));

        return "https://vnragro.com/File_Bkp/{$backupFolder}/{$yearId}/{$empId}/{$sanitized}";
    }
}
