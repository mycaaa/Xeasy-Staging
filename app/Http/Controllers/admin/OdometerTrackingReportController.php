<?php

namespace App\Http\Controllers\admin;

use App\Exports\OdometerTrackingExport;
use App\Helpers\OrganogramFilterHelper;
use App\Http\Controllers\Controller;
use App\Models\CoreDepartments;
use App\Models\CoreFunctions;
use App\Models\CoreGrade;
use App\Models\CoreVertical;
use App\Services\S3Service;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Maatwebsite\Excel\Facades\Excel;

class OdometerTrackingReportController extends Controller
{
    /**
     * Display the Odometer vs Tracking KM comparison report page.
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

        // Fetch Employees for filter
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

        return view('reports.odometer_tracking_report', array_merge($organogramOptions, compact(
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
     * AJAX endpoint to fetch report comparison data with filters.
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
            Log::error('OdometerTrackingReport getData error: ' . $e->getMessage() . ' | Trace: ' . $e->getTraceAsString());
            return response()->json([
                'success' => false,
                'message' => 'Failed to load report data: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Export comparison report to Excel.
     */
    public function export(Request $request)
    {
        ini_set('max_execution_time', 0);
        ini_set('memory_limit', '1024M');

        try {
            $result = $this->buildReportData($request, true);
            $fileName = 'Odometer_vs_Tracking_Report_' . now()->format('Ymd_His') . '.xlsx';

            return Excel::download(new OdometerTrackingExport($result['rows']), $fileName);
        } catch (\Throwable $e) {
            Log::error('OdometerTrackingReport export error: ' . $e->getMessage());
            return back()->with('error', 'Export failed: ' . $e->getMessage());
        }
    }

    /**
     * Get detailed tracking sessions for an employee on a specific date.
     */
    public function getSessionDetails($employeeId, $date)
    {
        try {
            $sessions = DB::connection('tracking')->table('tracking_sessions')
                ->where('employee_id', (int) $employeeId)
                ->where('tracking_date', $date)
                ->orderBy('started_at')
                ->get();

            $stops = DB::connection('tracking')->table('tracking_stops')
                ->where('employee_id', (int) $employeeId)
                ->whereDate('arrival_at', $date)
                ->orderBy('arrival_at')
                ->get();

            return response()->json([
                'success' => true,
                'sessions' => $sessions,
                'stops' => $stops,
                'employee_id' => $employeeId,
                'date' => $date,
            ]);
        } catch (\Throwable $e) {
            Log::error('getSessionDetails error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Unable to fetch tracking session details: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Core business logic to query claims, fetch tracking sessions, compute variance and apply filters.
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

        // Filters
        $fromDate = $request->input('from_date');
        $toDate = $request->input('to_date');
        $month = $request->input('month');
        $departmentIds = array_filter((array) $request->input('department_ids', []));
        $designationIds = array_filter((array) $request->input('designation_ids', []));
        $verticalIds = array_filter((array) $request->input('vertical_ids', []));
        $functionIds = array_filter((array) $request->input('function_ids', []));
        $gradeIds = array_filter((array) $request->input('grade_ids', []));
        $hqIds = array_filter((array) $request->input('hq_ids', []));
        $employeeIds = array_filter((array) $request->input('employee_ids', []));
        $vehicleType = $request->input('vehicle_type'); // '2W', '4W', or empty
        $claimStatus = $request->input('claim_status');
        $diffFilter = $request->input('difference_filter', 'all');
        $threshold = (float) $request->input('diff_threshold', 15.0);
        $search = trim($request->input('search', ''));

        // Build main query on expenseclaims table (ClaimId = 7)
        $query = DB::connection($expConn)->table("{$claimsTable} as ec")
            ->where('ec.ClaimId', 7)
            ->whereNotIn('ec.ClaimStatus', ['Deactivate', 'Draft']);

        // Check if wheeler entry table exists for join fallback
        $hasWheelerTable = false;
        try {
            $hasWheelerTable = DB::connection($expConn)->getSchemaBuilder()->hasTable($wheelerTable);
        } catch (\Throwable $e) {
            $hasWheelerTable = false;
        }

        // UAT-03: the 2/4-wheeler table has no ExpId index (none at all on VVNR y6); the join made the
        // export time out. Its columns are read in batches after the main query instead.

        // Join HRIMS tables
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
            // Default to current month or last 30 days
            $query->where('ec.BillDate', '>=', now()->startOfMonth()->toDateString());
        }

        // Apply Employee IDs
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

        // Search Keyword
        if (!empty($search)) {
            $query->where(function ($q) use ($search) {
                $q->where('emp.EmpCode', 'LIKE', "%{$search}%")
                  ->orWhere(DB::raw("CONCAT(emp.Fname, ' ', COALESCE(emp.Sname, ''), ' ', emp.Lname)"), 'LIKE', "%{$search}%")
                  ->orWhere('ec.ExpId', 'LIKE', "%{$search}%")
                  ->orWhere('dep.department_name', 'LIKE', "%{$search}%");
            });
        }

        // Columns to select
        $selects = [
            'ec.ExpId',
            'ec.ClaimId',
            'ec.CrBy',
            'ec.BillDate',
            'ec.CrDate',
            'ec.WType',
            'ec.RatePerKM',
            'ec.FilledTAmt',
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



        $records = $query->select($selects)
            ->orderByDesc('ec.BillDate')
            ->orderByDesc('ec.ExpId')
            ->get();

        if ($hasWheelerTable && $records->isNotEmpty()) {
            $wheel = [];
            foreach ($records->pluck('ExpId')->unique()->chunk(5000) as $ids) {
                DB::connection($expConn)->table($wheelerTable)
                    ->whereIn('ExpId', $ids->values()->all())
                    ->select('ExpId', 'DistTraOpen', 'DistTraClose', 'Totalkm', 'VehicleReg')
                    ->get()
                    ->each(function ($w) use (&$wheel) {
                        $wheel[$w->ExpId] ??= $w;
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
                    'total_claimed_km' => 0,
                    'total_tracked_km' => 0,
                    'total_difference_km' => 0,
                    'high_discrepancy_count' => 0,
                    'moderate_count' => 0,
                    'matched_count' => 0,
                    'no_tracking_count' => 0,
                ],
            ];
        }

        // Extract employee IDs and date range for tracking data query
        $empIds = $records->pluck('CrBy')->unique()->values()->all();
        $dates = $records->pluck('BillDate')->filter()->unique()->values()->all();
        $minDate = !empty($dates) ? min($dates) : now()->toDateString();
        $maxDate = !empty($dates) ? max($dates) : now()->toDateString();

        // Query Tracking Sessions
        $trackingMap = [];
        try {
            $trackingSessions = DB::connection('tracking')->table('tracking_sessions')
                ->whereIn('employee_id', $empIds)
                ->whereBetween('tracking_date', [$minDate, $maxDate])
                ->groupBy('employee_id', 'tracking_date')
                ->select([
                    'employee_id',
                    'tracking_date',
                    DB::raw('COALESCE(SUM(total_distance_km), 0) as total_distance_km'),
                    DB::raw('COUNT(id) as sessions_count'),
                    DB::raw('COALESCE(SUM(moving_seconds), 0) as total_moving_seconds'),
                    DB::raw('COALESCE(SUM(stopped_seconds), 0) as total_stopped_seconds'),
                    DB::raw('MIN(started_at) as first_start'),
                    DB::raw('MAX(ended_at) as last_end'),
                ])
                ->get();

            foreach ($trackingSessions as $ts) {
                $key = $ts->employee_id . '_' . $ts->tracking_date;
                $trackingMap[$key] = $ts;
            }
        } catch (\Throwable $e) {
            Log::warning('Tracking DB connection/query error in report: ' . $e->getMessage());
        }

        // Prepare rows and compute variance
        $rows = [];
        $totalClaimedKm = 0;
        $totalTrackedKm = 0;
        $totalDifferenceKm = 0;
        $highDiscrepancyCount = 0;
        $moderateCount = 0;
        $matchedCount = 0;
        $noTrackingCount = 0;

        foreach ($records as $item) {
            $empId = (int) $item->CrBy;
            $billDate = !empty($item->BillDate) ? date('Y-m-d', strtotime($item->BillDate)) : null;

            // Opening and Closing KM
            $openKm = (float) ($item->odomtr_opening ?? 0);
            $closeKm = (float) ($item->odomtr_closing ?? 0);

            if ($openKm == 0 && isset($item->wheeler_open) && $item->wheeler_open > 0) {
                $openKm = (float) $item->wheeler_open;
            }
            if ($closeKm == 0 && isset($item->wheeler_close) && $item->wheeler_close > 0) {
                $closeKm = (float) $item->wheeler_close;
            }

            // Total Claimed KM
            $claimedKm = (float) ($item->TotKm ?? 0);
            if ($claimedKm <= 0 && isset($item->wheeler_totalkm) && $item->wheeler_totalkm > 0) {
                $claimedKm = (float) $item->wheeler_totalkm;
            }
            if ($claimedKm <= 0 && $closeKm > $openKm) {
                $claimedKm = round($closeKm - $openKm, 2);
            }

            // Tracking KM
            $trackingKey = $empId . '_' . $billDate;
            $trackingSession = $trackingMap[$trackingKey] ?? null;

            $trackedKm = null;
            $sessionsCount = 0;
            $movingSeconds = 0;
            $stoppedSeconds = 0;

            if ($trackingSession) {
                $trackedKm = round((float) $trackingSession->total_distance_km, 2);
                $sessionsCount = (int) $trackingSession->sessions_count;
                $movingSeconds = (int) $trackingSession->total_moving_seconds;
                $stoppedSeconds = (int) $trackingSession->total_stopped_seconds;
            }

            // Variance & Discrepancy Classification
            $differenceKm = null;
            $diffPercentage = null;
            $status = 'no_tracking'; // 'match', 'moderate', 'high', 'no_tracking', 'zero_km'

            $hasValidTracking = ($trackedKm !== null && $trackedKm > 0);
            $hasValidClaim = ($claimedKm > 0);

            if ($hasValidTracking && $hasValidClaim) {
                // Both Claimed KM and Tracked KM are positive
                $differenceKm = round($claimedKm - $trackedKm, 2);
                $absDiff = abs($differenceKm);
                $diffPercentage = round(($absDiff / $claimedKm) * 100, 1);

                if ($absDiff > $threshold) {
                    $status = 'high';
                    $highDiscrepancyCount++;
                } elseif ($absDiff > 5.0) {
                    $status = 'moderate';
                    $moderateCount++;
                } else {
                    $status = 'match';
                    $matchedCount++;
                }

                $totalTrackedKm += $trackedKm;
                $totalDifferenceKm += $differenceKm;
            } elseif (!$hasValidClaim && !$hasValidTracking) {
                // Both Claimed KM and Tracked KM are 0 (or no tracking movement)
                $status = 'zero_km';
                $differenceKm = 0.0;
                $diffPercentage = 0.0;
                // Do NOT count in matchedCount
            } elseif ($hasValidClaim && !$hasValidTracking) {
                // Claimed > 0, but no GPS tracking was recorded (or tracked = 0)
                $status = 'no_tracking';
                $noTrackingCount++;
                $differenceKm = $claimedKm;
                $diffPercentage = 100.0;
                $totalDifferenceKm += $differenceKm;
            } else {
                // Claimed == 0, but Tracked > 0 (unclaimed movement)
                $differenceKm = round(0 - $trackedKm, 2);
                $absDiff = abs($differenceKm);
                if ($absDiff > $threshold) {
                    $status = 'high';
                    $highDiscrepancyCount++;
                } else {
                    $status = 'moderate';
                    $moderateCount++;
                }
                $totalTrackedKm += $trackedKm;
                $totalDifferenceKm += $differenceKm;
            }

            $totalClaimedKm += $claimedKm;

            // Vehicle Type Label
            $vTypeLabel = '2W';
            if (in_array($item->WType, [3, 4])) {
                $vTypeLabel = '4W';
            }

            // Image URLs
            $openingUrl = $this->resolveImageUrl($item->opening_filepath, $yearId, $empId);
            $closingUrl = $this->resolveImageUrl($item->closing_filepath, $yearId, $empId);

            $empName = trim("{$item->Fname} " . ($item->Sname ? "{$item->Sname} " : '') . "{$item->Lname}");

            $row = [
                'exp_id' => $item->ExpId,
                'claim_id' => $item->ClaimId,
                'emp_id' => $empId,
                'emp_code' => $item->EmpCode ?? 'N/A',
                'emp_name' => $empName,
                'department' => $item->department_name ?? 'N/A',
                'designation' => $item->DesigName ?? 'N/A',
                'headquarter' => $item->HqName ?? 'N/A',
                'vertical' => $item->vertical_name ?? '',
                'function' => $item->function_name ?? '',
                'bill_date' => $billDate,
                'bill_date_formatted' => $billDate ? date('d M Y', strtotime($billDate)) : 'N/A',
                'vehicle_type' => $vTypeLabel,
                'claim_status' => $item->ClaimStatus,
                'opening_km' => $openKm,
                'closing_km' => $closeKm,
                'claimed_km' => $claimedKm,
                'tracking_km' => $trackedKm,
                'difference_km' => $differenceKm,
                'diff_percentage' => $diffPercentage,
                'status' => $status,
                'sessions_count' => $sessionsCount,
                'moving_minutes' => round($movingSeconds / 60),
                'stopped_minutes' => round($stoppedSeconds / 60),
                'opening_image' => $item->opening_filepath,
                'opening_image_url' => $openingUrl,
                'closing_image' => $item->closing_filepath,
                'closing_image_url' => $closingUrl,
                'amount' => (float) ($item->FilledTAmt ?? 0),
                'rate_per_km' => (float) ($item->RatePerKM ?? 0),
                'remark' => $item->DateEntryRemark ?: $item->Remark ?: '',
            ];

            // Apply Difference Filter
            if ($diffFilter === 'high' && $status !== 'high') {
                continue;
            }
            if ($diffFilter === 'moderate' && $status !== 'moderate') {
                continue;
            }
            if ($diffFilter === 'match' && $status !== 'match') {
                continue;
            }
            if ($diffFilter === 'no_tracking' && $status !== 'no_tracking') {
                continue;
            }
            if ($diffFilter === 'zero_km' && $status !== 'zero_km') {
                continue;
            }
            if ($diffFilter === 'claimed_higher' && ($differenceKm === null || $differenceKm <= 1)) {
                continue;
            }
            if ($diffFilter === 'tracking_higher' && ($differenceKm === null || $differenceKm >= -1)) {
                continue;
            }

            $row['year_id'] = $yearId;
            $rows[] = $row;
        }

        return [
            'rows' => $rows,
            'summary' => [
                'total_claims' => count($records),
                'total_claimed_km' => round($totalClaimedKm, 2),
                'total_tracked_km' => round($totalTrackedKm, 2),
                'total_difference_km' => round($totalDifferenceKm, 2),
                'high_discrepancy_count' => $highDiscrepancyCount,
                'moderate_count' => $moderateCount,
                'matched_count' => $matchedCount,
                'no_tracking_count' => $noTrackingCount,
            ],
        ];
    }

    /**
     * Resolve public URL for uploaded odometer images without blocking S3 network calls.
     */
    private function resolveImageUrl(?string $fileName, int $yearId, int $empId): ?string
    {
        if (empty($fileName)) {
            return null;
        }

        if (filter_var($fileName, FILTER_VALIDATE_URL)) {
            return $fileName;
        }

        try {
            return app(\App\Services\S3Service::class)->getFileViewUrl("Expense/{$yearId}/{$empId}/{$fileName}");
        } catch (\Throwable $e) {
            $sanitized = preg_replace('/[^A-Za-z0-9\-\_\.]/', '_', basename($fileName));
            return "https://vnragro.com/File_Bkp/xeasyfile/{$yearId}/{$empId}/{$sanitized}";
        }
    }
}
