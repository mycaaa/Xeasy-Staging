<?php

namespace App\Http\Controllers\admin;

use App\Services\ClaimRules\ClaimSubmissionValidator;
use App\Http\Controllers\Controller;
use App\Models\CoreDepartments;
use App\Models\ExpenseClaim;
use App\Models\MonthExpenseFinal;
use App\Services\ClaimActionLogService;
use Carbon\Carbon;
use Illuminate\Filesystem\FilesystemAdapter;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Yajra\DataTables\Facades\DataTables;
use Maatwebsite\Excel\Facades\Excel;
use App\Exports\VerifyRunningKmExport;
use App\Exports\VerifyDuplicateOdometerExport;

class VerifierController extends Controller
{
    const VEHICLE_TYPE_TWO_WHEELER = 2;

    const VEHICLE_TYPE_FOUR_WHEELER = 4;

    private function getRateController()
    {
        return new RateController;
    }

    private function getRatePerKM($employeeId, $billDate, $wheelType, $vehiclePolicy = 0, $withDriver = null)
    {
        $rateController = $this->getRateController();

        return $rateController->getRatePerKM($employeeId, $billDate, $wheelType, $vehiclePolicy, $withDriver);
    }

    /**
     * Check if the authenticated user is a Super Admin.
     */
    protected function isSuperAdmin(): bool
    {
        if (function_exists('isSuperAdmin')) {
            return isSuperAdmin();
        }
        $user = auth()->user();
        if (!$user) {
            return false;
        }
        if ((int)$user->id === 1) {
            return true;
        }
        if (session('user_type') === 'Super Admin' || session('user_type') === 'super_admin') {
            return true;
        }
        if (method_exists($user, 'hasAnyRole') && $user->hasAnyRole(['Super Admin', 'super_admin'])) {
            return true;
        }
        if (method_exists($user, 'hasRole') && ($user->hasRole('Super Admin') || $user->hasRole('super_admin'))) {
            return true;
        }
        if (method_exists($user, 'can') && $user->can('super_admin')) {
            return true;
        }
        return false;
    }

    public function verifyRunningKm()
    {
        return view('verifier.verify_running_km');
    }

    public function verifyPendingClaims()
    {
        return view('verifier.verify_pending_claims');
    }

    public function verifyApprovedClaims()
    {
        return view('verifier.verify_approved_claims');
    }

    protected function getVerifyRunningKmBaseQuery(Request $request)
    {
        $yearId = session('year_id');
        $companyId = session('company_id');
        $table = "y{$yearId}_expenseclaims";
        $hrimsDb = \App\Helpers\DatabaseSwitcher::getHrimsDatabaseName();
        $query = DB::connection(session('expense_connection'))->table($table . ' as e')
            ->join($hrimsDb . '.hrm_employee as emp', 'e.CrBy', '=', 'emp.EmployeeID')
            ->leftJoin($hrimsDb . '.hrm_employee_general as empg', 'e.CrBy', '=', 'empg.EmployeeID')
            ->leftJoin($hrimsDb . '.core_departments as dept', 'empg.DepartmentId', '=', 'dept.id')
            ->leftJoin("y{$yearId}_monthexpensefinal as mf", function ($join) use ($yearId) {
                $join
                    ->on('mf.EmployeeID', '=', 'e.CrBy')
                    ->on(DB::raw('MONTH(e.BillDate)'), '=', 'mf.Month')
                    ->where('mf.YearId', '=', $yearId);
            })
            ->select([
                'e.ExpId',
                'e.CrBy',
                'emp.EmpCode',
                DB::raw("CONCAT(emp.Fname, ' ', COALESCE(emp.Sname, ''), ' ', emp.Lname) AS employee_name"),
                'dept.department_name',
                'e.ClaimId',
                'e.CrDate',
                'e.BillDate',
                'e.opening_filepath',
                'e.closing_filepath',
                'e.odomtr_opening',
                'e.odomtr_closing',
                'e.TotKm',
                'e.RatePerKM',
                'e.FilledTAmt',
                'e.WType',
                'e.FilledOkay',
                'e.v_verify',
                'e.VerifyTRemark as verifyremark',
                'e.Backdate_Odometer_Status',
                'e.FilOkDenyRemark',
                'e.ClaimStatus',
                'mf.Status as final_status',
                'mf.SubPermission as final_subpermission',
            ])->where('e.ClaimId', 7);

        // ODO-45: the reading the photo OCR returned for this upload (temp_odometer_data via
        // temp_punch_id, same employee, created around the bill date - ids are reused).
        // ODO-45b: VVNR has no temp_odometer_data table, so the join is added only where it exists
        // (without this guard the VVNR Verify Running KM screen and its export failed).
        $schema = \Illuminate\Support\Facades\Schema::connection(session('expense_connection'));
        if ($schema->hasTable('temp_odometer_data') && $schema->hasColumn($table, 'temp_punch_id')) {
            $query->leftJoin('temp_odometer_data as ocr', function ($join) {
                $join->on('ocr.id', '=', 'e.temp_punch_id')
                    ->on('ocr.emp_id', '=', 'e.CrBy')
                    ->whereRaw('DATE(ocr.created_at) BETWEEN DATE_SUB(e.BillDate, INTERVAL 1 DAY) AND DATE_ADD(DATE(e.CrDate), INTERVAL 1 DAY)');
            })->addSelect(['ocr.opening as ocr_opening', 'ocr.closeing as ocr_closing', DB::raw('ocr.id IS NOT NULL as has_ocr')]);
        } else {
            $query->addSelect([DB::raw('NULL as ocr_opening'), DB::raw('NULL as ocr_closing'), DB::raw('0 as has_ocr')]);
        }

        $isDeactivatedFilter = $this->isSuperAdmin() && $request->boolean('deactivated');

        if ($isDeactivatedFilter) {
            $query->whereIn('e.ClaimStatus', ['Deactivate', 'Deactivated']);
        } else {
            $query->where(function ($q) {
                $q->whereNull('e.ClaimStatus')
                  ->orWhereNotIn('e.ClaimStatus', ['Deactivate', 'Deactivated', 'Draft']);
            });
        }

        if ($request->boolean('only_employee')) {
            if ($request->filled('employee')) {
                $query->where('e.CrBy', $request->employee);
            } else {
                $query->whereRaw('1=0');
            }
        } else {
            if (!$isDeactivatedFilter) {
                if (!$request->boolean('denied')) {
                    // Filter out already verified records for all companies
                    $query->where('e.v_verify', 'N');
                }
            }
            if ($request->filled('employee')) {
                $query->where('e.CrBy', $request->employee);
            }
        }
        if ($request->filled('month')) {
            $query->whereMonth('e.BillDate', (int) $request->month);
        }
        if (!$isDeactivatedFilter) {
            if ($request->boolean('denied')) {
                $query->where('e.FilledOkay', 2)->where('e.v_verify', 'Y');
            } else {
                $query->where(function ($q) {
                    $q->whereNull('e.FilledOkay')->orWhere('e.FilledOkay', '!=', 2);
                });
            }
        }
        if ($request->filled('bill_date_range')) {
            $dates = explode(' to ', $request->bill_date_range);
            if (count($dates) == 2) {
                $startDate = Carbon::createFromFormat('Y-m-d', trim($dates[0]))->startOfDay();
                $endDate = Carbon::createFromFormat('Y-m-d', trim($dates[1]))->endOfDay();
                $query->whereBetween('e.BillDate', [$startDate, $endDate]);
            }
        }
        if ($request->boolean('missing_images')) {
            $query->where(function ($q) {
                $q->where(function ($subQ) {
                    $subQ->whereNull('e.opening_filepath')->orWhere('e.opening_filepath', '');
                })->orWhere(function ($subQ) {
                    $subQ->whereNull('e.closing_filepath')->orWhere('e.closing_filepath', '');
                });
            });
        }
        if ($request->boolean('both_images')) {
            $query->where(function ($q) {
                $q
                    ->whereNotNull('e.opening_filepath')
                    ->where('e.opening_filepath', '!=', '')
                    ->whereNotNull('e.closing_filepath')
                    ->where('e.closing_filepath', '!=', '');
            });
        }
        if ($request->filled('bill_date')) {
            $query->whereDate('e.BillDate', $request->bill_date);
        }
        $query->orderBy('e.BillDate', 'ASC');

        return $query;
    }

    public function verifyRunningKmData(Request $request)
    {
        $yearId = session('year_id');
        $companyId = session('company_id');

        // RKM-UI-01: the filter lists are worked out when the table is first drawn (a new filter re-creates the table)
        // and for the explicit list request - not again on every page, sort or reload of the same table
        $withLists = $request->boolean('only_dropdowns') || (int) $request->input('draw', 1) <= 1;
        $response = $withLists ? [
            'months' => $this->getAvailableMonths($request),
            'bill_dates' => $this->getAvailableBillDates($request),
            'employees' => $request->boolean('only_employee') ? $this->getAllEmployees() : $this->getAvailableEmployees($request),
        ] : [];

        if ($request->boolean('only_dropdowns')) {
            return response()->json($response);
        }

        $query = $this->getVerifyRunningKmBaseQuery($request);

        $cumulativeKmMap = [];
        if ($request->boolean('only_employee')) {
            $allRecords = (clone $query)->get();
            $cumulative = 0;
            foreach ($allRecords as $r) {
                $cumulative += (int) $r->TotKm;
                $cumulativeKmMap[$r->ExpId] = $cumulative;
            }
        }

        $rateCache = [];
        $resolveRowRate = function ($row) use (&$rateCache) {
            $rate = (float) ($row->RatePerKM ?? 0);
            if ($rate > 0) {
                return $rate;
            }
            $wheelType = (int) ($row->WType ?: 2);
            $billDate = $row->BillDate ? Carbon::parse($row->BillDate)->format('Y-m-d') : date('Y-m-d');
            $cacheKey = "{$row->CrBy}_{$billDate}_{$wheelType}_" . ($row->With_Driver ?? '');
            if (!isset($rateCache[$cacheKey])) {
                $rateCache[$cacheKey] = (float) $this->getRatePerKM($row->CrBy, $billDate, $wheelType, 0, $row->With_Driver ?? null);
            }
            return $rateCache[$cacheKey];
        };

        $dataTable = DataTables::of($query)->addIndexColumn()->filter(function ($query) {
            if (request()->has('search') && request('search')['value'] !== '') {
                $keyword = request('search')['value'];
                $query->where(function ($q) use ($keyword) {
                    $q->where('emp.EmpCode', 'like', "%{$keyword}%")->orWhere(DB::raw("CONCAT(emp.Fname, ' ', COALESCE(emp.Sname, ''), ' ', emp.Lname)"), 'like', "%{$keyword}%")->orWhere('e.ExpId', 'like', "%{$keyword}%")->orWhere('e.CrDate', 'like', "%{$keyword}%")->orWhere('e.BillDate', 'like', "%{$keyword}%")->orWhere('e.odomtr_opening', 'like', "%{$keyword}%")->orWhere('e.odomtr_closing', 'like', "%{$keyword}%")->orWhere('e.TotKm', 'like', "%{$keyword}%")->orWhere('dept.department_name', 'like', "%{$keyword}%");
                });
            }
        })->editColumn('employee_name', function ($row) {
            $dept = $row->department_name ?? '';
            return '<div><span class="fw-semibold">' . e($row->employee_name) . '</span>' .
                   ($dept ? '<br><small class="text-muted" style="font-size:10px;">' . e($dept) . '</small>' : '') .
                   '</div>';
        })->editColumn('CrDate', fn($row) => $row->CrDate ? Carbon::parse($row->CrDate)->format('d-m-Y') : '')->editColumn('BillDate', fn($row) => $row->BillDate ? Carbon::parse($row->BillDate)->format('d-m-Y') : '')->addColumn('opening_image', function ($row) use ($yearId) {
            if (empty($row->opening_filepath)) {
                return '<span class="text-danger text-sm">Upload Pending</span>';
            }
            $basePath = $this->getExpenseBasePath();
            $path = "{$basePath}/{$yearId}/{$row->CrBy}/{$row->opening_filepath}";
            if ($url = $this->getS3Url($path)) {
                return '<img class="rounded-sm zoom-verify-img" src="' . $url . '" width="100" height="60" />';
            }

            return '<span class="text-danger text-sm">Upload Pending</span>';
        })->addColumn('closing_image', function ($row) use ($yearId) {
            if (empty($row->closing_filepath)) {
                return '<span class="text-danger text-sm">Upload Pending</span>';
            }
            $basePath = $this->getExpenseBasePath();
            $path = "{$basePath}/{$yearId}/{$row->CrBy}/{$row->closing_filepath}";
            if ($url = $this->getS3Url($path)) {
                return '<img class="rounded-sm zoom-verify-img" src="' . $url . '" width="100" height="60" />';
            }

            return '<span class="text-danger text-sm">Upload Pending</span>';
        })->editColumn('odomtr_opening', function ($row) {
            return '<input type="number"
            class="form-control form-control-sm odometer-input opening-odo text-center"
            data-expid="' . $row->ExpId . '"
            data-type="opening"
            value="' . $row->odomtr_opening . '" />' . self::ocrFlag($row, 'opening');
        })->editColumn('odomtr_closing', function ($row) {
            return '<input type="number"
            class="form-control form-control-sm odometer-input closing-odo text-center"
            data-expid="' . $row->ExpId . '"
            data-type="closing"
            value="' . $row->odomtr_closing . '" />' . self::ocrFlag($row, 'closing');
        })->editColumn('verifyremark', function ($row) {
            return '<input type="text"
        class="form-control form-control-sm verifyremark-input text-center"
        data-expid="' . $row->ExpId . '"
        data-type="verifyremark"
        value="' . ($row->VerifyTRemark ?? '') . '" />';
        })->editColumn('TotKm', function ($row) {
            return '<span class="fw-bold">' . number_format($row->TotKm, 0) . '</span>';
        })->addColumn('cumulative_km', function ($row) use ($request, $cumulativeKmMap) {
            if ($request->boolean('only_employee') && isset($cumulativeKmMap[$row->ExpId])) {
                return '<span class="fw-bold text-primary">' . $cumulativeKmMap[$row->ExpId] . '</span>';
            }

            return '-';
        })->addColumn('rate_column', function ($row) use ($resolveRowRate) {
            $rate = $resolveRowRate($row);
            return '<input type="number" step="0.01"
            class="form-control form-control-sm rate-input text-center"
            data-expid="' . $row->ExpId . '"
            value="' . number_format($rate, 2, '.', '') . '" style="width:80px;"'
            // RKM-RATE-01: the rate is fetched from the eligibility, not typed
            . (config('claim_rules.running_km.manual_rate', false) ? '' : ' readonly title="Rate from the employee\'s eligibility (auto)" ') . ' />';
        })->addColumn('amount_column', function ($row) use ($resolveRowRate) {
            $amount = (float) ($row->FilledTAmt ?? 0);
            if ($amount <= 0 && (float) $row->TotKm > 0) {
                $rate = $resolveRowRate($row);
                if ($rate > 0) {
                    $amount = round((float) $row->TotKm * $rate, 2);
                }
            }
            return '<span class="fw-bold row-amount-' . $row->ExpId . '">' . number_format($amount, 2) . '</span>';
        })->addColumn('vehicle_type', function ($row) {
            $checked2W = $row->WType == '2' ? 'checked' : '';
            $checked4W = $row->WType == '4' ? 'checked' : '';

            return '
                            <div class="d-flex flex-column align-items-center">
                                <div class="form-check mb-1">
                                    <input class="form-check-input vehicle-type-radio" type="radio"
                                    name="vehicle_type_' . $row->ExpId . '" value="2" ' . $checked2W . '
                                    data-expid="' . $row->ExpId . '">
                                    <label class="form-check-label">2W</label>
                                </div>
                                <div class="form-check">
                                    <input class="form-check-input vehicle-type-radio" type="radio"
                                    name="vehicle_type_' . $row->ExpId . '" value="4" ' . $checked4W . '
                                    data-expid="' . $row->ExpId . '">
                                    <label class="form-check-label">4W</label>
                                </div>
                            </div>
                        ';
        })->addColumn('update_action', function ($row) {
            $allow = ($row->final_status === 'Open' || $row->final_subpermission === 'Y' || session('company_id') == 11);
            $backdateAllowed = ($row->Backdate_Odometer_Status === 'A' || $row->Backdate_Odometer_Status === null || $row->Backdate_Odometer_Status === '');
            if ($allow && $backdateAllowed) {
                return '<a href="javascript:void(0);" class="badge bg-info update-running-km"
                                    data-expid="' . $row->ExpId . '" data-emp="' . $row->CrBy . '"><i class="ri-edit-line me-1"></i> Update</a>';
            }

            return '<span class="badge bg-danger-subtle text-warning">Blocked</span>';
        })->addColumn('verify_action', function ($row) {
            if ((int) $row->FilledOkay === 2) {
                return '<span class="text-warning text-wrap">' . ($row->FilOkDenyRemark ?? 'Denied') . '</span>';
            }
            // RKM-VERIFY-02: an already verified reading shows Verified (the employee view lists them too)
            if (($row->v_verify ?? '') === 'Y') {
                return '<span class="badge bg-success"><i class="ri-check-double-line me-1"></i>Verified</span>';
            }
            $allow = ($row->final_status === 'Open' || $row->final_subpermission === 'Y' || session('company_id') == 11);
            $backdateAllowed = ($row->Backdate_Odometer_Status === 'A' || $row->Backdate_Odometer_Status === null || $row->Backdate_Odometer_Status === '');
            if ($allow && $backdateAllowed) {
                return '<a href="javascript:void(0);" class="badge bg-success mark-verified"
                                    data-expid="' . $row->ExpId . '" data-emp="' . $row->CrBy . '"><i class="ri-check-double-line me-1"></i> Verify</a>';
            }

            return '<span class="badge bg-danger-subtle text-warning">Blocked</span>';
        })->addColumn('details_action', function ($row) {
            return '<a href="javascript:void(0);" class="badge bg-primary view-claim-detail"
                        data-expid="' . $row->ExpId . '" data-claim-id="7" data-emp="' . $row->CrBy . '"
                        style="cursor:pointer;"><i class="ri-eye-line me-1"></i>View</a>';
        });

        $rawColumns = ['employee_name', 'opening_image', 'closing_image', 'update_action', 'verify_action', 'details_action', 'vehicle_type', 'odomtr_opening', 'odomtr_closing', 'verifyremark', 'TotKm', 'cumulative_km', 'rate_column', 'amount_column'];

        if ($this->isSuperAdmin()) {
            $rawColumns[] = 'deactivate_action';
            $dataTable->addColumn('deactivate_action', function ($row) {
                if ($row->ClaimStatus === 'Deactivate' || $row->ClaimStatus === 'Deactivated') {
                    $reason = !empty($row->FilOkDenyRemark) ? e($row->FilOkDenyRemark) : 'Deactivated';
                    return '<div class="d-flex flex-column align-items-center gap-1">
                                <span class="badge bg-danger-subtle text-danger" title="' . $reason . '"><i class="ri-forbid-line me-1"></i>Deactivated</span>
                                <a href="javascript:void(0);" class="badge bg-success revert-running-km-btn"
                                    data-expid="' . $row->ExpId . '"
                                    data-empcode="' . e($row->EmpCode) . '"
                                    data-empname="' . e($row->employee_name) . '"
                                    title="Revert claim to active status">
                                    <i class="ri-arrow-go-back-line me-1"></i>Revert
                                </a>
                            </div>';
                }

                return '<div class="d-flex align-items-center justify-content-center gap-2">
                            <input type="checkbox" class="form-check-input chk-deactivate-row m-0"
                                value="' . $row->ExpId . '"
                                data-expid="' . $row->ExpId . '"
                                data-empcode="' . e($row->EmpCode) . '"
                                data-empname="' . e($row->employee_name) . '"
                                data-totkm="' . (int)$row->TotKm . '"
                                data-billdate="' . ($row->BillDate ? Carbon::parse($row->BillDate)->format('d-m-Y') : '') . '"
                                title="Select claim #' . $row->ExpId . ' for bulk deactivate">
                            <a href="javascript:void(0);" class="badge bg-danger deactivate-running-km-btn"
                                data-expid="' . $row->ExpId . '"
                                data-claim-id="' . $row->ClaimId . '"
                                data-empcode="' . e($row->EmpCode) . '"
                                data-empname="' . e($row->employee_name) . '"
                                data-billdate="' . ($row->BillDate ? Carbon::parse($row->BillDate)->format('d-m-Y') : '') . '"
                                data-totkm="' . (int)$row->TotKm . '"
                                style="cursor:pointer;"
                                title="Deactivate Claim">
                                <i class="ri-forbid-line me-1"></i>Deactivate
                            </a>
                        </div>';
            });
        }

        return $dataTable->rawColumns($rawColumns)->with($response)->make(true);
    }

    public function exportRunningKm(Request $request)
    {
        $yearId = session('year_id');
        $basePath = $this->getExpenseBasePath();
        $disk = Storage::disk('s3');

        $query = $this->getVerifyRunningKmBaseQuery($request);

        $keyword = $request->input('search');
        if (is_array($keyword)) {
            $keyword = $keyword['value'] ?? '';
        }
        if (!empty($keyword)) {
            $hrimsDb = \App\Helpers\DatabaseSwitcher::getHrimsDatabaseName();
            $query->where(function ($q) use ($keyword) {
                $q->where('emp.EmpCode', 'like', "%{$keyword}%")
                  ->orWhere(DB::raw("CONCAT(emp.Fname, ' ', COALESCE(emp.Sname, ''), ' ', emp.Lname)"), 'like', "%{$keyword}%")
                  ->orWhere('e.ExpId', 'like', "%{$keyword}%")
                  ->orWhere('e.CrDate', 'like', "%{$keyword}%")
                  ->orWhere('e.BillDate', 'like', "%{$keyword}%")
                  ->orWhere('e.odomtr_opening', 'like', "%{$keyword}%")
                  ->orWhere('e.odomtr_closing', 'like', "%{$keyword}%")
                  ->orWhere('e.TotKm', 'like', "%{$keyword}%")
                  ->orWhere('dept.department_name', 'like', "%{$keyword}%");
            });
        }

        $records = $query->get();

        $fileName = 'Verify_Running_KM_Report_' . date('Ymd_His') . '.xlsx';

        return Excel::download(
            new VerifyRunningKmExport($records, $yearId, $basePath, $disk, $request->boolean('only_employee')),
            $fileName
        );
    }

    /**
     * Helper to get annotated duplicate odometer rows.
     */
    protected function getDuplicateOdometerRows()
    {
        $yearId    = session('year_id');
        $expConn   = session('expense_connection');
        $hrimsDb   = \App\Helpers\DatabaseSwitcher::getHrimsDatabaseName();
        $table     = "y{$yearId}_expenseclaims";

        // Step 1: Find (opening, closing) pairs that appear more than once
        $dupPairs = DB::connection($expConn)
            ->table("{$table} as e")
            ->select([
                'e.odomtr_opening',
                'e.odomtr_closing',
                DB::raw('COUNT(*) as pair_count'),
                DB::raw('COUNT(DISTINCT e.CrBy) as employee_count'),
            ])
            ->where('e.ClaimId', 7)
            ->whereNotIn('e.ClaimStatus', ['Deactivate'])
            ->where('e.odomtr_opening', '>', 0)
            ->where('e.odomtr_closing', '>', 0)
            ->groupBy('e.odomtr_opening', 'e.odomtr_closing')
            ->having('pair_count', '>', 1)
            ->get();

        if ($dupPairs->isEmpty()) {
            $held = $this->heldDuplicateOdometerRows($expConn, (int) $yearId, $hrimsDb);

            return ['rows' => $held, 'groups_count' => 0, 'held_count' => count($held)];
        }

        // Build pair keys for WHERE IN equivalent
        $openings = $dupPairs->pluck('odomtr_opening')->unique()->values()->toArray();
        $closings  = $dupPairs->pluck('odomtr_closing')->unique()->values()->toArray();

        // Step 2: Fetch all individual records matching those pairs
        $records = DB::connection($expConn)
            ->table("{$table} as e")
            ->join("{$hrimsDb}.hrm_employee as emp", 'e.CrBy', '=', 'emp.EmployeeID')
            ->leftJoin("{$hrimsDb}.hrm_employee_general as gen", 'e.CrBy', '=', 'gen.EmployeeID')
            ->leftJoin("{$hrimsDb}.core_departments as dept", 'gen.DepartmentId', '=', 'dept.id')
            ->select([
                'e.ExpId',
                'e.CrBy',
                'emp.EmpCode_New as EmpCode',
                DB::raw("CONCAT(TRIM(CONCAT(emp.Fname, ' ', COALESCE(emp.Sname,''), ' ', emp.Lname))) as employee_name"),
                'dept.department_name',
                'e.BillDate',
                'e.CrDate',
                'e.odomtr_opening',
                'e.odomtr_closing',
                'e.TotKm',
                'e.v_verify',
                'e.ClaimStatus',
                'e.WType',
            ])
            ->where('e.ClaimId', 7)
            ->whereNotIn('e.ClaimStatus', ['Deactivate'])
            ->whereIn('e.odomtr_opening', $openings)
            ->whereIn('e.odomtr_closing', $closings)
            ->where('e.odomtr_opening', '>', 0)
            ->where('e.odomtr_closing', '>', 0)
            ->get();

        // Build a lookup from the pair counts
        $pairMeta = [];
        foreach ($dupPairs as $p) {
            $key = $p->odomtr_opening . '_' . $p->odomtr_closing;
            $pairMeta[$key] = [
                'pair_count'     => $p->pair_count,
                'employee_count' => $p->employee_count,
            ];
        }

        // Annotate each record with type and group key, then filter to confirmed pairs
        $annotated = [];
        foreach ($records as $r) {
            $key  = $r->odomtr_opening . '_' . $r->odomtr_closing;
            $meta = $pairMeta[$key] ?? null;
            if (!$meta) continue;

            $dupType = $meta['employee_count'] > 1 ? 'Cross Employee' : 'Same Employee';

            $annotated[] = [
                'ExpId'           => $r->ExpId,
                'CrBy'            => $r->CrBy,
                'EmpCode'         => $r->EmpCode,
                'employee_name'   => $r->employee_name,
                'department_name' => $r->department_name ?? 'N/A',
                'BillDate'        => $r->BillDate ? Carbon::parse($r->BillDate)->format('d-m-Y') : '',
                'CrDate'          => $r->CrDate  ? Carbon::parse($r->CrDate)->format('d-m-Y')  : '',
                'odomtr_opening'  => (int) $r->odomtr_opening,
                'odomtr_closing'  => (int) $r->odomtr_closing,
                'TotKm'           => (int) $r->TotKm,
                'v_verify'        => $r->v_verify,
                'ClaimStatus'     => $r->ClaimStatus,
                'duplicate_type'  => $dupType,
                'group_key'       => $key,
                'pair_count'      => $meta['pair_count'],
            ];
        }

        // Sort: group same pairs together, then by opening reading
        usort($annotated, fn($a, $b) => strcmp($a['group_key'], $b['group_key']));

        // ODO-DUP-02: which ones are already reviewed, and which wait (they cannot be approved)
        $reviewed = \Illuminate\Support\Facades\Schema::connection($expConn)->hasTable(\App\Services\Claims\DuplicateOdometer::REVIEW_TABLE)
            ? DB::connection($expConn)->table(\App\Services\Claims\DuplicateOdometer::REVIEW_TABLE)->where('year_id', $yearId)->pluck('remark', 'exp_id')->all() : [];
        foreach ($annotated as &$row) {
            $row['reviewed'] = isset($reviewed[$row['ExpId']]);
            $row['review_remark'] = $reviewed[$row['ExpId']] ?? '';
        }
        unset($row);
        $held = $this->heldDuplicateOdometerRows($expConn, (int) $yearId, $hrimsDb);
        $open = collect($annotated)->where('duplicate_type', 'Same Employee')->where('reviewed', false)->pluck('group_key')->unique()->count();

        return ['rows' => array_values(array_merge($annotated, $held)), 'groups_count' => count($dupPairs), 'held_count' => count($held), 'open_groups' => $open];
    }

    /** ODO-DUP-02: duplicates the system took out (same day, same readings), newest first. */
    protected function heldDuplicateOdometerRows(string $expConn, int $yearId, string $hrimsDb): array
    {
        $logs = \App\Services\Claims\DuplicateOdometer::heldBySystem($expConn, $yearId);
        if ($logs->isEmpty()) {
            return [];
        }
        $emps = DB::connection($expConn)->table("{$hrimsDb}.hrm_employee as emp")
            ->leftJoin("{$hrimsDb}.hrm_employee_general as gen", 'emp.EmployeeID', '=', 'gen.EmployeeID')
            ->leftJoin("{$hrimsDb}.core_departments as dept", 'gen.DepartmentId', '=', 'dept.id')
            ->whereIn('emp.EmployeeID', $logs->pluck('employee_id')->unique()->all())
            ->get(['emp.EmployeeID', 'emp.EmpCode_New as EmpCode', DB::raw("TRIM(CONCAT(emp.Fname, ' ', COALESCE(emp.Sname,''), ' ', emp.Lname)) as employee_name"), 'dept.department_name'])
            ->keyBy('EmployeeID');
        $claims = DB::connection($expConn)->table("y{$yearId}_expenseclaims")->whereIn('ExpId', $logs->pluck('exp_id')->all())
            ->get(['ExpId', 'CrDate', 'odomtr_opening', 'odomtr_closing', 'TotKm', 'v_verify', 'ClaimStatus'])->keyBy('ExpId');
        $out = [];
        foreach ($logs as $l) {
            $e = $emps[$l->employee_id] ?? null;
            $c = $claims[$l->exp_id] ?? null;
            $out[] = [
                'ExpId' => (int) $l->exp_id, 'CrBy' => (int) $l->employee_id, 'EmpCode' => $e->EmpCode ?? '',
                'employee_name' => $e->employee_name ?? '', 'department_name' => $e->department_name ?? 'N/A',
                'BillDate' => Carbon::parse($l->bill_date)->format('d-m-Y'), 'CrDate' => $c && $c->CrDate ? Carbon::parse($c->CrDate)->format('d-m-Y') : '',
                'odomtr_opening' => (int) ($c->odomtr_opening ?? 0), 'odomtr_closing' => (int) ($c->odomtr_closing ?? 0), 'TotKm' => (int) ($c->TotKm ?? 0),
                'v_verify' => $c->v_verify ?? '', 'ClaimStatus' => $c->ClaimStatus ?? 'Deactivate',
                'duplicate_type' => 'Held by system', 'group_key' => 'held_'.$l->kept_exp_id, 'pair_count' => 2,
                'kept_exp_id' => (int) $l->kept_exp_id, 'held_on' => Carbon::parse($l->created_at)->format('d-m-Y H:i'),
                'reviewed' => true, 'review_remark' => 'Taken out by the system on '.Carbon::parse($l->created_at)->format('d-m-Y').' - duplicate of ExpId '.$l->kept_exp_id,
            ];
        }

        return $out;
    }

    /** ODO-DUP-02: verifier confirms the same readings on two claims are genuine. */
    public function markOdometerNotDuplicate(Request $request)
    {
        $request->validate(['expid' => 'required|integer', 'remark' => 'required|string|min:5|max:300']);
        $yearId = (int) session('year_id');
        $conn = (string) session('expense_connection');
        $claim = DB::connection($conn)->table("y{$yearId}_expenseclaims")->where('ExpId', (int) $request->expid)->first(['ExpId', 'CrBy', 'ClaimId']);
        if (! $claim || (int) $claim->ClaimId !== 7) {
            return response()->json(['success' => false, 'message' => 'Running-km claim not found.'], 404);
        }
        if (! \App\Support\RecordAccess::canViewEmployee(auth()->user(), (int) $claim->CrBy)) {
            return response()->json(['success' => false, 'message' => 'Not allowed.'], 403);
        }
        \App\Services\Claims\DuplicateOdometer::markReviewed($conn, $yearId, (int) $claim->ExpId, (int) (session('employee_id') ?? 0) ?: null, trim((string) $request->remark));
        Log::info('Odometer marked not duplicate', ['exp' => $claim->ExpId, 'by' => auth()->id(), 'remark' => $request->remark]);

        return response()->json(['success' => true, 'message' => 'Marked as not a duplicate. The claim can now go to approval.']);
    }

    /**
     * Detect duplicate odometer readings:
     * - Same employee, same (opening, closing) pair used more than once
     * - Different employees sharing the exact same (opening, closing) pair
     */
    public function duplicateOdometerData(Request $request)
    {
        try {
            $res = $this->getDuplicateOdometerRows();
            $annotated = $res['rows'];
            $groupsCount = $res['groups_count'];
            $total = count($annotated);

            return response()->json([
                'draw'            => (int) $request->input('draw', 1),
                'recordsTotal'    => $total,
                'recordsFiltered' => $total,
                'data'            => $annotated,
                'groups_count'    => $groupsCount,
                'held_count'      => $res['held_count'] ?? 0,
                'open_groups'     => $res['open_groups'] ?? 0,
            ]);
        } catch (\Exception $e) {
            Log::error('Duplicate Odometer Data Error: ' . $e->getMessage());
            return response()->json([
                'draw'            => 1,
                'recordsTotal'    => 0,
                'recordsFiltered' => 0,
                'data'            => [],
                'error'           => $e->getMessage(),
            ], 500);
        }
    }

    public function exportDuplicateOdometer(Request $request)
    {
        $res = $this->getDuplicateOdometerRows();
        $fileName = 'Duplicate_Odometer_Readings_' . date('Ymd_His') . '.xlsx';

        return Excel::download(
            new VerifyDuplicateOdometerExport($res['rows']),
            $fileName
        );
    }

    protected function getAvailableBillDates(Request $request)
    {
        $yearId = session('year_id');
        $table = "y{$yearId}_expenseclaims";
        $isDeactivated = $this->isSuperAdmin() && $request->boolean('deactivated');

        $query = DB::connection(session('expense_connection'))
            ->table($table . ' as e')
            ->where('e.ClaimId', 7)
            ->whereNotNull('e.BillDate');

        if ($isDeactivated) {
            $query->whereIn('e.ClaimStatus', ['Deactivate', 'Deactivated']);
        } else {
            $query->where(function ($q) {
                $q->whereNull('e.ClaimStatus')->orWhereNotIn('e.ClaimStatus', ['Deactivate', 'Deactivated', 'Draft']);
            });

            if (!$request->boolean('only_employee') && !$request->boolean('denied')) {
                if (session('company_id') != 3) {
                    $query->where('e.v_verify', 'N');
                }
            }

            if ($request->boolean('denied')) {
                $query->where('e.FilledOkay', 2)->where('e.v_verify', 'Y');
            } else {
                $query->where(function ($q) {
                    $q->whereNull('e.FilledOkay')->orWhere('e.FilledOkay', '!=', 2);
                });
            }
        }

        if ($request->filled('employee')) {
            $query->where('e.CrBy', $request->employee);
        }

        if ($request->filled('month')) {
            $query->whereMonth('e.BillDate', (int) $request->month);
        }

        if ($request->boolean('missing_images')) {
            $query->where(function ($q) {
                $q->where(function ($s) {
                    $s->whereNull('e.opening_filepath')->orWhere('e.opening_filepath', '');
                })->orWhere(function ($s) {
                    $s->whereNull('e.closing_filepath')->orWhere('e.closing_filepath', '');
                });
            });
        }

        if ($request->boolean('both_images')) {
            $query->where(function ($q) {
                $q
                    ->whereNotNull('e.opening_filepath')
                    ->where('e.opening_filepath', '!=', '')
                    ->whereNotNull('e.closing_filepath')
                    ->where('e.closing_filepath', '!=', '');
            });
        }

        return $query
            ->select(
                DB::raw('DATE(e.BillDate) as bill_date'),
                DB::raw('COUNT(*) as record_count')
            )
            ->groupBy(DB::raw('DATE(e.BillDate)'))
            ->orderBy(DB::raw('DATE(e.BillDate)'))
            ->get()
            ->map(fn($item) => [
                'value' => $item->bill_date,
                'text' => Carbon::parse($item->bill_date)->format('d-M-Y'),
                'count' => $item->record_count,
            ]);
    }

    protected function getAvailableMonths(Request $request)
    {
        $yearId = session('year_id');
        $table = "y{$yearId}_expenseclaims";
        $isDeactivated = $this->isSuperAdmin() && $request->boolean('deactivated');

        $query = DB::connection(session('expense_connection'))->table($table . ' as e')->where('e.ClaimId', 7);

        if ($isDeactivated) {
            $query->whereIn('e.ClaimStatus', ['Deactivate', 'Deactivated']);
        } else {
            $query->where(function ($q) {
                $q->whereNull('e.ClaimStatus')->orWhereNotIn('e.ClaimStatus', ['Deactivate', 'Deactivated', 'Draft']);
            });

            if (!$request->boolean('only_employee') && !$request->boolean('denied')) {
                if (session('company_id') != 3) {
                    $query->where('e.v_verify', 'N');
                }
            }

            if ($request->boolean('denied')) {
                $query->where('e.FilledOkay', 2)->where('e.v_verify', 'Y');
            } else {
                $query->where(function ($q) {
                    $q->whereNull('e.FilledOkay')->orWhere('e.FilledOkay', '!=', 2);
                });
            }
        }
        if ($request->filled('employee')) {
            $query->where('e.CrBy', $request->employee);
        }
        if ($request->boolean('denied')) {
            $query->where('e.FilledOkay', 2)->where('e.v_verify', 'Y');
        } else {
            $query->where(function ($q) {
                $q->whereNull('e.FilledOkay')->orWhere('e.FilledOkay', '!=', 2);
            });
        }
        if ($request->boolean('missing_images')) {
            $query->where(function ($q) {
                $q->where(function ($subQ) {
                    $subQ->whereNull('e.opening_filepath')->orWhere('e.opening_filepath', '');
                })->orWhere(function ($subQ) {
                    $subQ->whereNull('e.closing_filepath')->orWhere('e.closing_filepath', '');
                });
            });
        }
        if ($request->boolean('both_images')) {
            $query->where(function ($q) {
                $q
                    ->whereNotNull('e.opening_filepath')
                    ->where('e.opening_filepath', '!=', '')
                    ->whereNotNull('e.closing_filepath')
                    ->where('e.closing_filepath', '!=', '');
            });
        }

        return $query->select(DB::raw('MONTH(e.BillDate) as month_value'))->whereNotNull('e.BillDate')->groupBy(DB::raw('MONTH(e.BillDate)'))->orderBy(DB::raw('MONTH(e.BillDate)'))->get()->map(function ($item) {
            return [
                'value' => (int) $item->month_value,
                'text' => Carbon::createFromDate(null, (int) $item->month_value, 1)->format('F'),
            ];
        });
    }

    protected function getAvailableEmployees(Request $request)
    {
        $yearId = session('year_id');
        $table = "y{$yearId}_expenseclaims";
        $isDeactivated = $this->isSuperAdmin() && $request->boolean('deactivated');

        $query = DB::connection(session('expense_connection'))->table($table . ' as e')->where('e.ClaimId', 7);

        if ($isDeactivated) {
            $query->whereIn('e.ClaimStatus', ['Deactivate', 'Deactivated']);
        } else {
            $query->where(function ($q) {
                $q->whereNull('e.ClaimStatus')->orWhereNotIn('e.ClaimStatus', ['Deactivate', 'Deactivated', 'Draft']);
            });

            if (!$request->boolean('denied')) {
                $query->where('e.v_verify', 'N');
            }

            if ($request->boolean('denied')) {
                $query->where('e.FilledOkay', 2)->where('e.v_verify', 'Y');
            } else {
                $query->where(function ($q) {
                    $q->whereNull('e.FilledOkay')->orWhere('e.FilledOkay', '!=', 2);
                });
            }
        }

        if ($request->filled('month')) {
            $query->whereMonth('e.BillDate', (int) $request->month);
        }
        if ($request->boolean('missing_images')) {
            $query->where(function ($q) {
                $q->where(function ($subQ) {
                    $subQ->whereNull('e.opening_filepath')->orWhere('e.opening_filepath', '');
                })->orWhere(function ($subQ) {
                    $subQ->whereNull('e.closing_filepath')->orWhere('e.closing_filepath', '');
                });
            });
        }
        if ($request->boolean('both_images')) {
            $query->where(function ($q) {
                $q
                    ->whereNotNull('e.opening_filepath')
                    ->where('e.opening_filepath', '!=', '')
                    ->whereNotNull('e.closing_filepath')
                    ->where('e.closing_filepath', '!=', '');
            });
        }
        $employeeIds = $query->select('e.CrBy')->groupBy('e.CrBy')->pluck('e.CrBy')->toArray();
        if (empty($employeeIds)) {
            return collect();
        }

        return DB::connection(session('hrims_connection'))->table('hrm_employee')->whereIn('EmployeeID', $employeeIds)->select(['EmployeeID', 'EmpCode', DB::raw("CONCAT(EmpCode, ' - ', Fname, ' ', COALESCE(Sname, ''), ' ', Lname) as EmpName")])->orderBy('EmpCode')->get()->map(function ($employee) {
            return [
                'value' => $employee->EmployeeID,
                'text' => $employee->EmpName,
            ];
        });
    }

    protected function getAllEmployees()
    {
        return DB::connection(session('hrims_connection'))
            ->table('hrm_employee')
            ->where('EmpStatus', 'A')
            ->where('CompanyId', session('company_id'))
            ->select(['EmployeeID', 'EmpCode', DB::raw("CONCAT(EmpCode, ' - ', Fname, ' ', COALESCE(Sname, ''), ' ', Lname) as EmpName")])
            ->orderBy('EmpCode')
            ->get()
            ->map(function ($employee) {
                return ['value' => $employee->EmployeeID, 'text' => $employee->EmpName];
            });
    }

    public function updateRunningKm(Request $request)
    {
        try {
            $request->validate([
                'expId' => 'required|integer',
                'opening' => 'required|numeric|min:0',
                'closing' => 'required|numeric|min:0',
                'verifyremark' => 'nullable|string',
                'vehicleType' => 'required|in:2,4',
                'rate' => 'nullable|numeric|min:0',
            ]);
            $yearId = session('year_id');
            $expTable = "y{$yearId}_expenseclaims";
            $wheelerTable = "y{$yearId}_24_wheeler_entry";
            $fillDataTable = "y{$yearId}_g1_expensefilldata";
            if ($request->closing <= $request->opening) {
                return response()->json(['success' => false, 'message' => 'Closing odometer must be greater than opening odometer.'], 422);
            }
            $runningKm = $request->closing - $request->opening;
            $VerifyTRemark = $request->verifyremark;
            $expense = DB::connection(session('expense_connection'))->table($expTable)->where('ExpId', $request->expId)
                ->first(array_merge(['CrBy', 'BillDate'], \App\Services\Claims\ClaimChangeTracker::trackedColumns()));
            if (!$expense) {
                return response()->json(['success' => false, 'message' => 'Expense record not found.'], 404);
            }
            $billDate = $expense->BillDate ?? date('Y-m-d');

            // Use rate from request if provided and > 0, otherwise auto-calculate
            // RKM-RATE-01 (10 Nov 2026): the verifier corrects readings; the rate is always fetched
            if (config('claim_rules.running_km.manual_rate', false) && $request->filled('rate') && (float) $request->rate > 0) {
                $ratePerKm = (float) $request->rate;
            } else {
                $ratePerKm = (float) $this->getRatePerKM($expense->CrBy, $billDate, $request->vehicleType, 0, $request->input('with_driver', $expense->With_Driver ?? null));
            }

            $totalAmount = round($runningKm * $ratePerKm, 2);
            // FREEZE-01 (decided 5 Nov, Finance mail 26 Oct): the claimed amount stays as the
            // employee claimed it; the verifier's corrected readings give the verified amount only.
            // Every change is still logged in claim_amount_change_logs (ClaimChangeTracker below).
            $filledAmount = (float) ($expense->FilledTAmt ?? 0) > 0 ? $expense->FilledTAmt : $totalAmount;
            // Q-02: verified above claimed needs a reason
            $expense->ExpId = (int) $request->expId;
            if ($capMsg = \App\Services\Claims\StageCap::check(session('expense_connection'), (int) $yearId, $expense, $totalAmount, 'verify', $VerifyTRemark, 'running_km_update')) {
                return response()->json(['success' => false, 'message' => $capMsg, 'remark_required' => true], 422);
            }
            $expUpdateData = [
                'odomtr_opening' => $request->opening,
                'odomtr_closing' => $request->closing,
                'TotKm' => $runningKm,
                'WType' => $request->vehicleType,
                'BillDate' => $billDate,
                'RatePerKM' => $ratePerKm,
                'VerifyTAmt' => $totalAmount,
                'VerifyTRemark' => $VerifyTRemark,
            ];

            $expenseDb = DB::connection(session('expense_connection'));
            $expenseDb->beginTransaction();
            try {
                (new \App\Services\Claims\ClaimChangeTracker($expenseDb, (int) $yearId))->record(
                    (int) $request->expId, $expense, $expUpdateData, 'running_km_update', $VerifyTRemark,
                    (int) $expense->CrBy, (int) (session('employee_id') ?? session('EmployeeID') ?? 0) ?: null
                );
                $expenseDb->table($expTable)->where('ExpId', $request->expId)->update($expUpdateData);
                $expenseDb->commit();
                // ODO-DUP-02: a same-day exact copy is taken out at punching, before it reaches approval
                try {
                    app(\App\Services\Claims\DuplicateOdometer::class)->resolve(session('expense_connection'), (int) $yearId, (int) $expense->CrBy, (int) date('n', strtotime($billDate)), true, 'punching');
                } catch (\Throwable $e) {
                    Log::warning('Duplicate odometer check at punching failed: '.$e->getMessage());
                }
            } catch (\Throwable $e) {
                $expenseDb->rollBack();
                throw $e;
            }

            $wheelerExists = DB::connection(session('expense_connection'))->table($wheelerTable)->where('ExpId', $request->expId)->exists();
            if ($wheelerExists) {
                DB::connection(session('expense_connection'))->table($wheelerTable)->where('ExpId', $request->expId)->update([
                    'DistTraOpen' => $request->opening,
                    'DistTraClose' => $request->closing,
                    'Totalkm' => $runningKm,
                    'Rate_PerKm' => $ratePerKm,
                    'FilledTAmt' => $filledAmount,
                    'JourneyStartDt' => $billDate,
                    'JourneyEndDt' => $billDate,
                ]);
            } else {
                DB::connection(session('expense_connection'))->table($wheelerTable)->insert([
                    'ExpId' => $request->expId,
                    'DistTraOpen' => $request->opening,
                    'DistTraClose' => $request->closing,
                    'Totalkm' => $runningKm,
                    'Rate_PerKm' => $ratePerKm,
                    'FilledTAmt' => $filledAmount,
                    'JourneyStartDt' => $billDate,
                    'JourneyEndDt' => $billDate,
                ]);
            }

            $fillDataExists = DB::connection(session('expense_connection'))->table($fillDataTable)->where('ExpId', $request->expId)->exists();
            if ($fillDataExists) {
                DB::connection(session('expense_connection'))->table($fillDataTable)->where('ExpId', $request->expId)->update(['VehicleType' => $request->vehicleType]);
            } else {
                DB::connection(session('expense_connection'))->table($fillDataTable)->insert(['ExpId' => $request->expId, 'BillDate' => $billDate, 'JourneyStartDt' => $billDate, 'JourneyEndDt' => $billDate, 'VehicleType' => $request->vehicleType]);
            }

            return response()->json([
                'success' => true,
                'message' => 'Running KM updated successfully.',
                'data' => [
                    'runningKm' => $runningKm,
                    'rate' => $ratePerKm,
                    'amount' => $totalAmount,
                ],
            ]);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => 'Error updating running KM: ' . $e->getMessage()], 500);
        }
    }

    /**
     * ODO-45 (tracker #45): flag a reading the employee typed when the photo could not be read
     * or the photo reading differs (shown under the reading on the Verify Running KM screen).
     */
    private static function ocrFlag($row, string $which): string
    {
        if (empty($row->has_ocr)) {
            return '';
        }
        $ocr = $which === 'opening' ? ($row->ocr_opening ?? null) : ($row->ocr_closing ?? null);
        $typed = $which === 'opening' ? ($row->odomtr_opening ?? null) : ($row->odomtr_closing ?? null);
        if ($typed === null || (float) $typed <= 0) {
            return '';
        }
        if ($ocr === null || (float) $ocr <= 0) {
            return '<div><span class="badge bg-warning-subtle text-warning" title="The photo could not be read; the employee typed this reading">Photo unreadable - typed</span></div>';
        }
        if (abs((float) $ocr - (float) $typed) > (float) config('claim_rules.odometer.ocr_tolerance_km', 1)) {
            return '<div><span class="badge bg-danger-subtle text-danger" title="Reading read from the photo">Photo shows ' . e((string) (int) $ocr) . '</span></div>';
        }

        return '';
    }

    public function verifyRunningKmRecord(Request $request)
    {
        try {
            $request->validate([
                'expId' => 'required|integer',
                'rate' => 'nullable|numeric|min:0',
                'vehicleType' => 'nullable|in:2,4',
            ]);
            $yearId = session('year_id');
            $expTable = "y{$yearId}_expenseclaims";
            $wheelerTable = "y{$yearId}_24_wheeler_entry";
            $fillDataTable = "y{$yearId}_g1_expensefilldata";
            $expense = DB::connection(session('expense_connection'))->table($expTable)->where('ExpId', $request->expId)->first(array_merge(['opening_filepath', 'closing_filepath', 'WType', 'BillDate', 'CrBy'], \App\Services\Claims\ClaimChangeTracker::trackedColumns()));
            if (!$expense) {
                return response()->json(['success' => false, 'message' => 'Expense record not found.'], 404);
            }
            if (empty($expense->opening_filepath) || empty($expense->closing_filepath)) {
                return response()->json(['success' => false, 'message' => 'Cannot verify: Opening or closing image is missing.'], 422);
            }
            // Allow opening = 0 as long as opening image is present, reading is not negative/null, and closing > 0
            if (is_null($expense->odomtr_opening) || (float)$expense->odomtr_opening < 0 || is_null($expense->odomtr_closing) || (float)$expense->odomtr_closing <= 0) {
                return response()->json(['success' => false, 'message' => 'Cannot verify: Odometer readings are missing.'], 422);
            }
            if ((float)$expense->odomtr_closing <= (float)$expense->odomtr_opening) {
                return response()->json(['success' => false, 'message' => 'Cannot verify: Closing odometer must be greater than opening.'], 422);
            }
            $totKm = (float) $expense->TotKm;
            if ($totKm <= 0 && (float)$expense->odomtr_closing > (float)$expense->odomtr_opening) {
                $totKm = (float) ($expense->odomtr_closing - $expense->odomtr_opening);
            }
            $wType = $request->input('vehicleType', $expense->WType);
            $billDate = $expense->BillDate ?? date('Y-m-d');
            $crBy = $expense->CrBy;
            $systemRate = (float) $this->getRatePerKM($crBy, $billDate, $wType, 0, $request->input('with_driver', $expense->With_Driver ?? null));

            // Rate precedence:
            // 1. Explicit rate sent in verify request (> 0)
            // 2. Already updated RatePerKM on the expense claim record (> 0)
            // 3. System calculated rate
            // RKM-RATE-01 (10 Nov 2026): the rate is always the employee's eligible rate on the trip date
            $manualRate = false;
            if (config('claim_rules.running_km.manual_rate', false) && $request->filled('rate') && (float) $request->rate > 0) {
                $rate = (float) $request->rate;
                $manualRate = true;
            } else {
                $rate = $systemRate > 0 ? $systemRate : (float) ($expense->RatePerKM ?? 0);
            }

            $totAmt = round($totKm * $rate, 2);
            $status = ($totKm > 0) ? 'Y' : 'N';
            // FREEZE-01: the claimed amount stays; only the verified amount follows the verified km
            // (changes still logged by ClaimChangeTracker below). A claim with no amount yet gets it.
            $filledAmt = (float) ($expense->FilledTAmt ?? 0) > 0 ? $expense->FilledTAmt : $totAmt;
            // Q-02: verified above claimed needs a reason
            $expense->ExpId = (int) $request->expId;
            // RKM-VERIFY-01 (13 Nov 2026): the amount here is km x the employee's eligible rate (worked out by the system,
            // RKM-RATE-01), so it may be above what the app put as claimed; that needs no remark - this screen has none,
            // and such claims could not be verified at all. A rate typed by hand still needs the reason.
            if ($manualRate && $capMsg = \App\Services\Claims\StageCap::check(session('expense_connection'), (int) $yearId, $expense, $totAmt, 'verify', (string) $request->input('verifyremark', $request->input('remark', '')), 'running_km_verify')) {
                return response()->json(['success' => false, 'message' => $capMsg, 'remark_required' => true], 422);
            }
            $verifyUpdate = [
                'v_verify' => $status,
                'TotKm' => $totKm,
                'RatePerKM' => $rate,
                'FilledTAmt' => $filledAmt,
                'VerifyTAmt' => $totAmt,
                'ClaimStatus' => 'Filled',
                'WType' => $wType,
                'ClaimAtStep' => 3,
                'FilledBy' => DB::raw('CrBy')
            ];
            $expenseDb = DB::connection(session('expense_connection'));
            $expenseDb->beginTransaction();
            try {
                \App\Services\Claims\ClaimTransition::guard($expenseDb, (int) $yearId, (int) $request->expId, 'Filled', 'verifier', 'running_km_verify');
                (new \App\Services\Claims\ClaimChangeTracker($expenseDb, (int) $yearId))->record(
                    (int) $request->expId, $expense, $verifyUpdate, 'running_km_verify', null,
                    (int) $crBy, (int) (session('employee_id') ?? session('EmployeeID') ?? 0) ?: null
                );
                $expenseDb->table($expTable)->where('ExpId', $request->expId)->update($verifyUpdate);
                $expenseDb->commit();
            } catch (\Throwable $e) {
                $expenseDb->rollBack();
                throw $e;
            }
            $wheelerExists = DB::connection(session('expense_connection'))->table($wheelerTable)->where('ExpId', $request->expId)->exists();
            if ($wheelerExists) {
                DB::connection(session('expense_connection'))->table($wheelerTable)->where('ExpId', $request->expId)->update([
                    'DistTraOpen' => $expense->odomtr_opening,
                    'DistTraClose' => $expense->odomtr_closing,
                    'Totalkm' => $totKm,
                    'Rate_PerKm' => $rate,
                    'FilledTAmt' => $filledAmt,
                    'JourneyStartDt' => $billDate,
                    'JourneyEndDt' => $billDate
                ]);
            } else {
                DB::connection(session('expense_connection'))->table($wheelerTable)->insert([
                    'ExpId' => $request->expId,
                    'DistTraOpen' => $expense->odomtr_opening,
                    'DistTraClose' => $expense->odomtr_closing,
                    'Totalkm' => $totKm,
                    'Rate_PerKm' => $rate,
                    'FilledTAmt' => $filledAmt,
                    'JourneyStartDt' => $billDate,
                    'JourneyEndDt' => $billDate
                ]);
            }
            try {
                DB::connection(session('expense_connection'))->table($fillDataTable)->insertOrIgnore(['ExpId' => $request->expId, 'VehicleType' => $wType, 'BillDate' => $billDate]);
            } catch (\Exception $e) {
            }

            return response()->json(['success' => true, 'message' => 'Record verified successfully.', 'data' => ['rate' => $rate, 'amount' => $totAmt]]);
        } catch (\Illuminate\Validation\ValidationException $e) {
            throw $e;
        } catch (\Throwable $e) {
            // RKM-VERIFY-03: every failure (also PHP errors) answers with its reason and a reference in the log
            $ref = strtoupper(substr(sha1(uniqid('', true)), 0, 8));
            \Illuminate\Support\Facades\Log::error('verifyRunningKmRecord failed', ['ref' => $ref, 'exp' => $request->expId, 'error' => $e->getMessage(), 'at' => $e->getFile().':'.$e->getLine()]);
            $msg = $e instanceof \App\Services\Claims\ClaimTransitionDenied ? $e->getMessage() : 'Error verifying record: '.mb_substr($e->getMessage(), 0, 300);

            return response()->json(['success' => false, 'message' => $msg.' (ref '.$ref.')'], $e instanceof \App\Services\Claims\ClaimTransitionDenied ? 403 : 500);
        }
    }

    /**
     * Deactivate a running KM claim record.
     * Accessible ONLY by Super Admin.
     * Writes comprehensive audit trail to activity_log and expenseremark table.
     */
    public function deactivateRunningKmRecord(Request $request)
    {
        if (!$this->isSuperAdmin()) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized. Only Super Admin can deactivate running KM claims.'
            ], 403);
        }

        $request->validate([
            'expId'  => 'required|integer',
            'reason' => 'required|string|min:3|max:500',
        ]);

        $expId       = (int) $request->input('expId');
        $reason      = trim($request->input('reason'));
        $yearId      = session('year_id');
        $companyId   = session('company_id', 1);
        $expenseConn = session('expense_connection') ?: \App\Helpers\DatabaseSwitcher::getExpenseConnection($companyId);
        $table       = "y{$yearId}_expenseclaims";

        try {
            DB::connection($expenseConn)->beginTransaction();

            $currentClaim = DB::connection($expenseConn)->table($table)
                ->where('ExpId', $expId)
                ->where(function ($q) {
                    $q->whereNull('ClaimStatus')
                      ->orWhereNotIn('ClaimStatus', ['Deactivate', 'Deactivated']);
                })
                ->first();

            if (!$currentClaim) {
                DB::connection($expenseConn)->rollBack();
                return response()->json([
                    'success' => false,
                    'message' => 'Claim not found or already deactivated.'
                ], 404);
            }

            $previousStatus = $currentClaim->ClaimStatus;
            $claimId = $currentClaim->ClaimId ?? 7;

            // Update claim status to Deactivate (B-01: transition + actor check, logged)
            \App\Services\Claims\ClaimTransition::guardTable($expenseConn, $table, $expId, 'Deactivate', 'verifier', 'running_km_deactivate', $reason);
            DB::connection($expenseConn)->table($table)
                ->where('ExpId', $expId)
                ->update([
                    'ClaimStatus'     => 'Deactivate',
                    'FilOkDenyRemark' => 'Deactivated: ' . $reason,
                ]);

            // Log to activity_log (Audit trail)
            try {
                DB::table('activity_log')->insert([
                    'log_name'     => 'running_km_claim',
                    'description'  => "Running KM Claim #{$expId} was deactivated by " . (auth()->user()->name ?? 'Super Admin') . ". Reason: " . $reason,
                    'subject_type' => 'App\\Models\\ExpenseClaim',
                    'subject_id'   => $expId,
                    'event'        => 'deactivated',
                    'causer_type'  => 'App\\Models\\User',
                    'causer_id'    => auth()->id(),
                    'properties'   => json_encode([
                        'exp_id'              => $expId,
                        'claim_id'            => $claimId,
                        'reason'              => $reason,
                        'action'              => 'deactivate_running_km',
                        'previous_status'     => $previousStatus,
                        'odomtr_opening'      => $currentClaim->odomtr_opening ?? null,
                        'odomtr_closing'      => $currentClaim->odomtr_closing ?? null,
                        'tot_km'              => $currentClaim->TotKm ?? null,
                        'rate'                => $currentClaim->RatePerKM ?? null,
                        'amount'              => $currentClaim->FilledTAmt ?? null,
                        'employee_id'         => $currentClaim->CrBy ?? null,
                        'bill_date'           => $currentClaim->BillDate ?? null,
                        'deactivated_by_id'   => auth()->id(),
                        'deactivated_by_name' => auth()->user()->name ?? 'Super Admin',
                        'deactivated_at'      => now()->toDateTimeString(),
                        'company_id'          => $companyId,
                        'year_id'             => $yearId,
                        'ip_address'          => $request->ip(),
                        'user_agent'          => $request->userAgent(),
                    ]),
                    'batch_uuid'   => null,
                    'created_at'   => now(),
                    'updated_at'   => now(),
                ]);
            } catch (\Exception $logEx) {
                Log::error('deactivateRunningKmRecord: activity_log insert failed: ' . $logEx->getMessage());
            }

            // Log to expenseremark
            try {
                $remarkTable = \App\Models\ExpenseRemark::tableName($yearId);
                if (!\Illuminate\Support\Facades\Schema::connection($expenseConn)->hasTable($remarkTable)) {
                    $remarkTable = 'expenseremark';
                }
                if (\Illuminate\Support\Facades\Schema::connection($expenseConn)->hasTable($remarkTable)) {
                    DB::connection($expenseConn)->table($remarkTable)->insert([
                        'ExpId'      => $expId,
                        'Remark'     => 'Running KM Claim Deactivated: ' . $reason,
                        'By'         => auth()->id(),
                        'ByRole'     => 'A',
                        'CrDateTime' => now(),
                    ]);
                }
            } catch (\Exception $remarkEx) {
                Log::error('deactivateRunningKmRecord: expenseremark insert failed: ' . $remarkEx->getMessage());
            }

            DB::connection($expenseConn)->commit();

            Log::info("Running KM Claim #{$expId} deactivated by Super Admin user #" . auth()->id() . " (" . (auth()->user()->name ?? '') . "). Reason: {$reason}");

            return response()->json([
                'success' => true,
                'message' => 'Running KM claim #' . $expId . ' has been deactivated successfully.',
                'expId'   => $expId
            ]);
        } catch (\Exception $e) {
            DB::connection($expenseConn)->rollBack();
            Log::error('deactivateRunningKmRecord error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to deactivate claim. ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Bulk deactivate multiple running KM claims.
     * Accessible ONLY by Super Admin.
     * Writes comprehensive audit trail to activity_log and expenseremark table for each claim.
     */
    public function bulkDeactivateRunningKmRecords(Request $request)
    {
        if (!$this->isSuperAdmin()) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized. Only Super Admin can deactivate running KM claims.'
            ], 403);
        }

        $request->validate([
            'expIds'   => 'required|array|min:1',
            'expIds.*' => 'numeric',
            'reason'   => 'required|string|min:3|max:500',
        ]);

        $expIds      = array_map('intval', $request->input('expIds'));
        $reason      = trim($request->input('reason'));
        $yearId      = session('year_id');
        $companyId   = session('company_id', 1);
        $expenseConn = session('expense_connection') ?: \App\Helpers\DatabaseSwitcher::getExpenseConnection($companyId);
        $table       = "y{$yearId}_expenseclaims";

        try {
            DB::connection($expenseConn)->beginTransaction();

            $claims = DB::connection($expenseConn)->table($table)
                ->whereIn('ExpId', $expIds)
                ->where(function ($q) {
                    $q->whereNull('ClaimStatus')
                      ->orWhereNotIn('ClaimStatus', ['Deactivate', 'Deactivated']);
                })
                ->get();

            if ($claims->isEmpty()) {
                DB::connection($expenseConn)->rollBack();
                return response()->json([
                    'success' => false,
                    'message' => 'No active claims found matching the selected IDs, or they are already deactivated.'
                ], 404);
            }

            $userId   = auth()->id();
            $userName = auth()->user()->name ?? 'Super Admin';
            $now      = now();
            $ip       = $request->ip();
            $ua       = $request->userAgent();

            $deactivatedCount = 0;
            $deactivatedExpIds = [];

            // Identify remark table
            $remarkTable = \App\Models\ExpenseRemark::tableName($yearId);
            if (!\Illuminate\Support\Facades\Schema::connection($expenseConn)->hasTable($remarkTable)) {
                $remarkTable = 'expenseremark';
            }
            $hasRemarkTable = \Illuminate\Support\Facades\Schema::connection($expenseConn)->hasTable($remarkTable);

            foreach ($claims as $claim) {
                $expId = $claim->ExpId;
                $previousStatus = $claim->ClaimStatus;
                $claimId = $claim->ClaimId ?? 7;

                // Update claim status to Deactivate (B-01: transition + actor check, logged)
                \App\Services\Claims\ClaimTransition::guardTable($expenseConn, $table, $expId, 'Deactivate', 'verifier', 'running_km_bulk_deactivate', $reason, 'bulk');
                DB::connection($expenseConn)->table($table)
                    ->where('ExpId', $expId)
                    ->update([
                        'ClaimStatus'     => 'Deactivate',
                        'FilOkDenyRemark' => 'Deactivated (Bulk): ' . $reason,
                    ]);

                // Audit trail in activity_log
                try {
                    DB::table('activity_log')->insert([
                        'log_name'     => 'running_km_claim',
                        'description'  => "Running KM Claim #{$expId} was bulk deactivated by {$userName}. Reason: " . $reason,
                        'subject_type' => 'App\\Models\\ExpenseClaim',
                        'subject_id'   => $expId,
                        'event'        => 'bulk_deactivated',
                        'causer_type'  => 'App\\Models\\User',
                        'causer_id'    => $userId,
                        'properties'   => json_encode([
                            'exp_id'              => $expId,
                            'claim_id'            => $claimId,
                            'reason'              => $reason,
                            'action'              => 'bulk_deactivate_running_km',
                            'previous_status'     => $previousStatus,
                            'odomtr_opening'      => $claim->odomtr_opening ?? null,
                            'odomtr_closing'      => $claim->odomtr_closing ?? null,
                            'tot_km'              => $claim->TotKm ?? null,
                            'rate'                => $claim->RatePerKM ?? null,
                            'amount'              => $claim->FilledTAmt ?? null,
                            'employee_id'         => $claim->CrBy ?? null,
                            'bill_date'           => $claim->BillDate ?? null,
                            'batch_total'         => count($claims),
                            'deactivated_by_id'   => $userId,
                            'deactivated_by_name' => $userName,
                            'deactivated_at'      => $now->toDateTimeString(),
                            'company_id'          => $companyId,
                            'year_id'             => $yearId,
                            'ip_address'          => $ip,
                            'user_agent'          => $ua,
                        ]),
                        'batch_uuid'   => null,
                        'created_at'   => $now,
                        'updated_at'   => $now,
                    ]);
                } catch (\Exception $logEx) {
                    Log::error("bulkDeactivateRunningKmRecords: activity_log insert failed for ExpId #{$expId}: " . $logEx->getMessage());
                }

                // Log to expenseremark
                if ($hasRemarkTable) {
                    try {
                        DB::connection($expenseConn)->table($remarkTable)->insert([
                            'ExpId'      => $expId,
                            'Remark'     => 'Running KM Claim Bulk Deactivated: ' . $reason,
                            'By'         => $userId,
                            'ByRole'     => 'A',
                            'CrDateTime' => $now,
                        ]);
                    } catch (\Exception $remarkEx) {
                        Log::error("bulkDeactivateRunningKmRecords: expenseremark insert failed for ExpId #{$expId}: " . $remarkEx->getMessage());
                    }
                }

                $deactivatedCount++;
                $deactivatedExpIds[] = $expId;
            }

            DB::connection($expenseConn)->commit();

            Log::info("Bulk deactivated {$deactivatedCount} Running KM Claims by Super Admin user #{$userId} ({$userName}). ExpIds: " . implode(',', $deactivatedExpIds) . ". Reason: {$reason}");

            return response()->json([
                'success' => true,
                'message' => "Successfully deactivated {$deactivatedCount} running KM claims.",
                'count'   => $deactivatedCount,
                'expIds'  => $deactivatedExpIds
            ]);
        } catch (\Exception $e) {
            DB::connection($expenseConn)->rollBack();
            Log::error('bulkDeactivateRunningKmRecords error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to bulk deactivate claims. ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Revert a deactivated running KM claim back to active (Submitted) status.
     * Accessible ONLY by Super Admin.
     */
    public function revertDeactivatedRunningKmRecord(Request $request)
    {
        if (!$this->isSuperAdmin()) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized. Only Super Admin can revert deactivated running KM claims.'
            ], 403);
        }

        $request->validate([
            'expId' => 'required|integer',
        ]);

        $expId       = (int) $request->input('expId');
        $yearId      = session('year_id');
        $companyId   = session('company_id', 1);
        $expenseConn = session('expense_connection') ?: \App\Helpers\DatabaseSwitcher::getExpenseConnection($companyId);
        $table       = "y{$yearId}_expenseclaims";

        try {
            DB::connection($expenseConn)->beginTransaction();

            $claim = DB::connection($expenseConn)->table($table)
                ->where('ExpId', $expId)
                ->whereIn('ClaimStatus', ['Deactivate', 'Deactivated'])
                ->first();

            if (!$claim) {
                DB::connection($expenseConn)->rollBack();
                return response()->json([
                    'success' => false,
                    'message' => 'Claim not found or not in deactivated state.'
                ], 404);
            }

            $claimId = $claim->ClaimId ?? 7;

            // Revert status to Submitted (B-01: transition + actor check, logged)
            \App\Services\Claims\ClaimTransition::guardTable($expenseConn, $table, $expId, 'Submitted', 'verifier', 'running_km_revert_deactivate');
            DB::connection($expenseConn)->table($table)
                ->where('ExpId', $expId)
                ->update([
                    'ClaimStatus'     => 'Submitted',
                    'FilOkDenyRemark' => null,
                ]);

            // Log to activity_log
            try {
                DB::table('activity_log')->insert([
                    'log_name'     => 'running_km_claim',
                    'description'  => "Deactivated Running KM claim #{$expId} was reverted to active status by " . (auth()->user()->name ?? 'Super Admin'),
                    'subject_type' => 'App\\Models\\ExpenseClaim',
                    'subject_id'   => $expId,
                    'event'        => 'reactivated',
                    'causer_type'  => 'App\\Models\\User',
                    'causer_id'    => auth()->id(),
                    'properties'   => json_encode([
                        'exp_id'            => $expId,
                        'claim_id'          => $claimId,
                        'action'            => 'revert_deactivated_running_km',
                        'reverted_by_id'    => auth()->id(),
                        'reverted_by_name'  => auth()->user()->name ?? 'Super Admin',
                        'reverted_at'       => now()->toDateTimeString(),
                        'company_id'        => $companyId,
                        'year_id'           => $yearId,
                        'ip_address'        => $request->ip(),
                        'user_agent'        => $request->userAgent(),
                    ]),
                    'batch_uuid'   => null,
                    'created_at'   => now(),
                    'updated_at'   => now(),
                ]);
            } catch (\Exception $logEx) {
                Log::error('revertDeactivatedRunningKmRecord: activity_log insert failed: ' . $logEx->getMessage());
            }

            // Log to expenseremark
            try {
                $remarkTable = \App\Models\ExpenseRemark::tableName($yearId);
                if (!\Illuminate\Support\Facades\Schema::connection($expenseConn)->hasTable($remarkTable)) {
                    $remarkTable = 'expenseremark';
                }
                if (\Illuminate\Support\Facades\Schema::connection($expenseConn)->hasTable($remarkTable)) {
                    DB::connection($expenseConn)->table($remarkTable)->insert([
                        'ExpId'      => $expId,
                        'Remark'     => 'Deactivated Running KM claim reverted by Super Admin.',
                        'By'         => auth()->id(),
                        'ByRole'     => 'A',
                        'CrDateTime' => now(),
                    ]);
                }
            } catch (\Exception $remarkEx) {
                Log::error('revertDeactivatedRunningKmRecord: expenseremark insert failed: ' . $remarkEx->getMessage());
            }

            DB::connection($expenseConn)->commit();

            Log::info("Running KM Claim #{$expId} reverted from Deactivate by Super Admin user #" . auth()->id() . " (" . (auth()->user()->name ?? '') . ")");

            return response()->json([
                'success' => true,
                'message' => 'Running KM claim #' . $expId . ' has been reverted successfully.',
                'expId'   => $expId
            ]);
        } catch (\Exception $e) {
            DB::connection($expenseConn)->rollBack();
            Log::error('revertDeactivatedRunningKmRecord error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to revert claim. ' . $e->getMessage()
            ], 500);
        }
    }

    public function getRunningKmRate(Request $request)
    {
        try {
            $request->validate([
                'expId' => 'required|integer',
                'vehicleType' => 'required|in:2,4',
            ]);
            $yearId = session('year_id');
            $expTable = "y{$yearId}_expenseclaims";
            $expense = DB::connection(session('expense_connection'))->table($expTable)
                ->where('ExpId', $request->expId)
                ->first(['CrBy', 'BillDate']);
            if (!$expense) {
                return response()->json(['success' => false, 'message' => 'Expense not found'], 404);
            }
            $billDate = $expense->BillDate ?? date('Y-m-d');
            $rate = (float) $this->getRatePerKM($expense->CrBy, $billDate, (int) $request->vehicleType, 0, $request->input('with_driver', $expense->With_Driver ?? null));
            return response()->json([
                'success' => true,
                'rate' => $rate,
                'formatted_rate' => number_format($rate, 2),
            ]);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    protected function getExpenseBasePath(): string
    {
        $companyId = session('company_id');

        return ($companyId == 11) ? 'VVNR_Expense' : 'Expense';
    }

    protected function getBackupFolder(): string
    {
        $companyId = session('company_id');

        return ($companyId == 11) ? 'vxeasyfile' : 'xeasyfile';
    }

    protected function getS3Url(string $path): ?string
    {
        if ($path === '') {
            return null;
        }
        // VERIFY-S3-01: a storage error (S3 not reachable or not configured) must not stop the
        // verifier screen from loading; the file just shows as missing.
        try {
            $disk = Storage::disk('s3');
            if (!$disk->exists($path)) {
                return null;
            }
            if ($disk instanceof FilesystemAdapter && method_exists($disk, 'url')) {
                return $disk->url($path);
            }
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::warning('Verifier file check failed: '.$e->getMessage());
        }

        return null;
    }

    public function employeeVehicles()
    {
        $departments = CoreDepartments::where('is_active', 1)->get(['id', 'department_name']);

        return view('verifier.employee_vehicles', compact('departments'));
    }

    // public function verifyPendingClaimsData(Request $request)
    // {
    //     $yearId = session('year_id');
    //     $table = "y{$yearId}_monthexpensefinal";
    //     $hrimsDb = \App\Helpers\DatabaseSwitcher::getHrimsDatabaseName();
    //     $query = DB::connection(session('expense_connection'))->table($table . ' as m')->leftJoin($hrimsDb . '.hrm_employee as e', 'm.EmployeeID', '=', 'e.EmployeeID')->leftJoin($hrimsDb . '.hrm_employee_general as g', 'e.EmployeeID', '=', 'g.EmployeeID')->leftJoin($hrimsDb . '.core_grades as gr', 'g.GradeId', '=', 'gr.id')->leftJoin($hrimsDb . '.core_departments as dep', 'g.DepartmentId', '=', 'dep.id')->select(['e.EmployeeID', 'e.EmpCode', DB::raw("CONCAT(e.Fname, ' ', COALESCE(e.Sname, ''), ' ', e.Lname) AS Name"), 'g.MobileNo_Vnr', 'e.EmpStatus', 'dep.department_name', 'gr.grade_name', 'm.DateOfSubmit', 'm.Total_Claim', 'm.Claim_Amount', 'm.RecevingDate', 'm.Month', DB::raw("(SELECT lm.Month FROM `{$table}` AS lm WHERE lm.EmployeeID = m.EmployeeID AND lm.YearId = m.YearId AND lm.Total_Claim > 0 AND lm.Month < m.Month ORDER BY lm.Month DESC LIMIT 1) AS LastClaimMonth"), DB::raw("(SELECT lm.Total_Claim FROM `{$table}` AS lm WHERE lm.EmployeeID = m.EmployeeID AND lm.YearId = m.YearId AND lm.Total_Claim > 0 AND lm.Month < m.Month ORDER BY lm.Month DESC LIMIT 1) AS LastMonthClaimCount"), DB::raw("(SELECT lm.Claim_Amount FROM `{$table}` AS lm WHERE lm.EmployeeID = m.EmployeeID AND lm.YearId = m.YearId AND lm.Total_Claim > 0 AND lm.Month < m.Month ORDER BY lm.Month DESC LIMIT 1) AS LastMonthClaimAmount")])->where('m.YearId', $yearId)->where('m.Status', 'Closed')->where('m.Total_Claim', '>', 0)->where(function ($q) {
    //         $q->where('m.Verified_Amount', 0)->orWhere('m.Verified_Date', '0000-00-00');
    //     })->where('m.Verified_Date', '!=', '1970-01-01');
    //     $courierStatus = $request->input('courier_status', 'all');
    //     if ($courierStatus === 'received') {
    //         $query->where('m.RecevingDate', '!=', '0000-00-00')->whereNotNull('m.RecevingDate');
    //     } elseif ($courierStatus === 'not_received') {
    //         $query->where(function ($q) {
    //             $q->where('m.RecevingDate', '0000-00-00')->orWhereNull('m.RecevingDate');
    //         });
    //     }
    //     if (session('company_id') == 11) {
    //         $query->where('m.Fin_PayDate', '!=', '0000-00-00')->whereNotNull('m.Fin_PayDate');
    //     }
    //     if ($request->filled('month')) {
    //         $query->where('m.Month', operator: (int) $request->month);
    //     }
    //     if ($request->filled('department')) {
    //         $query->where('dep.id', $request->department);
    //     }
    //     if ($request->filled('employee')) {
    //         $query->where('m.EmployeeID', $request->employee);
    //     }
    //     $records = $query->orderBy('m.Month', 'DESC')->orderBy('e.EmpCode', 'ASC')->get();
    //     $months = $this->getPendingClaimsAvailableMonths($request);
    //     $employees = $this->getPendingClaimsAvailableEmployees($request);
    //     $departments = $this->getPendingClaimsAvailableDepartments($request);
    //     $canSubmit = auth()->user()->can('pending_claims_submit_to_app');
    //     $canReturn = auth()->user()->can('pending_claims_return');
    //     $canEHome = auth()->user()->can('pending_claims_ehome');
    //     $canViewAttendance = auth()->user()->can('pending_claims_view_attendance');
    //     $canViewEligibility = auth()->user()->can('pending_claims_view_eligibility');
    //     $canViewCourier = auth()->user()->can('pending_claims_courier_detail');
    //     $canViewClaims = auth()->user()->can('view_claim_detail');
    //     return DataTables::of($records)->addIndexColumn()->editColumn('Name', function ($row) use ($canViewClaims) {
    //         if (!$canViewClaims) {
    //             return $row->Name;
    //         }
    //         return '<a href="javascript:void(0);" onclick="viewClaim(' . $row->EmployeeID . ', ' . $row->Month . ', ' . "'" . ($row->DateOfSubmit ? Carbon::parse($row->DateOfSubmit)->format('d-m-Y') : '') . "', " . "'" . ($row->LastClaimMonth ? Carbon::createFromDate(null, (int) $row->LastClaimMonth, 1)->format('M') : '') . "', " . "'" . ($row->LastMonthClaimAmount ?? '') . "', " . "'" . ($row->RecevingDate ? Carbon::parse($row->RecevingDate)->format('d-m-Y') : '') . "'" . ')" class="text-primary text-decoration-underline" title="View Claims">' . $row->Name . '</a>';
    //     })->editColumn('DateOfSubmit', fn($row) => $row->DateOfSubmit ? Carbon::parse($row->DateOfSubmit)->format('d-m-Y') : '')->editColumn('Month', function ($row) use ($canViewClaims) {
    //         $monthName = Carbon::createFromDate(null, (int) $row->Month, 1)->format('F');
    //         if (!$canViewClaims) {
    //             return $monthName;
    //         }
    //         return '<a href="javascript:void(0);" onclick="viewClaim(' . $row->EmployeeID . ', ' . $row->Month . ', ' . "'" . ($row->DateOfSubmit ? Carbon::parse($row->DateOfSubmit)->format('d-m-Y') : '') . "', " . "'" . ($row->LastClaimMonth ? Carbon::createFromDate(null, (int) $row->LastClaimMonth, 1)->format('M') : '') . "', " . "'" . ($row->LastMonthClaimAmount ?? '') . "', " . "'" . ($row->RecevingDate ? Carbon::parse($row->RecevingDate)->format('d-m-Y') : '') . "'" . ')" class="text-primary text-decoration-underline" title="View Claims">' . $monthName . '</a>';
    //     })->editColumn('Total_Claim', fn($row) => '<span class="fw-bold">' . number_format($row->Total_Claim, 0) . '</span>')->editColumn('Claim_Amount', fn($row) => '<span class="fw-bold">' . number_format($row->Claim_Amount, 0) . '</span>')->addColumn('Actions', function ($row) use ($canSubmit, $canReturn, $canEHome) {
    //         $actions = '<div class="d-flex justify-content-center gap-2">';
    //         if ($canReturn) {
    //             $actions .= '<a href="javascript:void(0);" onclick="returnClaim(' . $row->EmployeeID . ', ' . $row->Month . ', ' . session('year_id') . ', ' . session('company_id') . ')" class="badge bg-danger-subtle text-danger text-decoration-none" title="Return"><i class="ri-arrow-go-back-line"></i> Return</a>';
    //         }
    //         if ($canEHome) {
    //             $actions .= '<a href="javascript:void(0);" onclick="eHome(' . $row->EmployeeID . ', ' . $row->Month . ', ' . session('year_id') . ', ' . session('company_id') . ')" class="badge bg-primary-subtle text-primary text-decoration-none" title="E-Home"><i class="ri-home-3-line"></i> E-Home</a>';
    //         }
    //         $actions .= '</div>';
    //         return $actions;
    //     })->addColumn('Check', function ($row) use ($canViewAttendance, $canViewEligibility) {
    //         $tableName = ExpenseClaim::tableName(session('year_id'));
    //         $getYear = ExpenseClaim::from($tableName)
    //             ->where('CrBy', $row->EmployeeID)
    //             ->whereMonth('BillDate', $row->Month)
    //             ->whereIn('FilledOkay', ['1'])
    //             ->whereNotNull('BillDate')
    //             ->whereNotIn('ClaimStatus', ['Deactivate'])
    //             ->whereNotIn('BillDate', ['0000-00-00', '1970-01-01'])
    //             ->first(['BillDate']);
    //         $attendanceUrl = "https://ess.vnrseeds.co.in/Employee/EmpEligElig.php?id={$row->EmployeeID}"
    //             . '&typeT=Attd'
    //             . "&m={$row->Month}"
    //             . '&y=' . ($getYear ? \Carbon\Carbon::parse($getYear->BillDate)->year : date('Y'))
    //             . '&act=cal';
    //         $eligibilityUrl = "https://ess.vnrseeds.co.in/Employee/EmpEligElig.php?id={$row->EmployeeID}";
    //         $html = '<div class="d-flex justify-content-center gap-2">';
    //         if ($canViewAttendance) {
    //             $html .= '<a href="javascript:void(0);" onclick="openPopUpWindow(\'' . $attendanceUrl . '\')" class="text-primary" title="Attendance Calendar"><i class="ri-eye-line" style="font-size:18px;"></i></a>';
    //         }
    //         if ($canViewEligibility) {
    //             $html .= '<a href="javascript:void(0);" onclick="openPopUpWindow(\'' . $eligibilityUrl . '\')" class="text-info" title="Open Eligibility"><i class="ri-file-list-3-line" style="font-size:18px;"></i></a>';
    //         }
    //         $html .= '</div>';
    //         return $html;
    //     })->addColumn('Courier_Detail', function ($row) use ($canViewCourier) {
    //         if (!$canViewCourier) {
    //             return '<span class="text-muted">-</span>';
    //         }
    //         $empId = $row->EmployeeID;
    //         $month = $row->Month;
    //         if (empty($row->RecevingDate) || \App\Support\ZeroDate::none($row->RecevingDate)) {
    //             return '<a href="javascript:void(0);"   data-bs-target="#courierModal" class="courier-detail text-danger text-decoration-underline" onclick="openCourierDetail(' . $empId . ', ' . $month . ', \'not_received\', this)" title="Courier Not Received">Not Received</a>';
    //         }
    //         return '<a href="javascript:void(0);"   data-bs-target="#courierModal" class="courier-detail text-success text-decoration-underline" onclick="openCourierDetail(' . $empId . ', ' . $month . ', \'received\', this)" title="Courier Received">Received</a>';
    //     })->addColumn('Last_Claim_Info', function ($row) {
    //         if ($row->LastClaimMonth && $row->LastMonthClaimAmount) {
    //             $monthName = Carbon::createFromDate(null, (int) $row->LastClaimMonth, 1)->format('M');
    //             return $monthName . ' - ' . number_format($row->LastMonthClaimAmount, 0);
    //         }
    //         return '<span class="text-muted">N/A</span>';
    //     })->rawColumns(['Name', 'Month', 'Total_Claim', 'Claim_Amount', 'Actions', 'Check', 'Courier_Detail', 'Last_Claim_Info'])->with(['months' => $months, 'employees' => $employees, 'departments' => $departments])->make(true);
    // }

    public function verifyPendingClaimsData(Request $request)
    {
        $yearId = session('year_id');
        $table = "y{$yearId}_monthexpensefinal";
        $hrimsDb = \App\Helpers\DatabaseSwitcher::getHrimsDatabaseName();
        $conn = session('expense_connection') ?: \App\Helpers\DatabaseSwitcher::getExpenseConnection();
        $courierDateCol = $this->hasCourierDateColumn($table, $conn) ? 'm.courier_date' : DB::raw('NULL as courier_date');

        // 1. Use JOIN instead of correlated subqueries
        $lastClaimsSub = DB::connection(session('expense_connection'))
            ->table($table . ' as lm')
            ->select([
                'lm.EmployeeID',
                'lm.YearId',
                DB::raw('MAX(lm.Month) as LastClaimMonth'),
                DB::raw('MAX(CASE WHEN lm.Month = sub.max_month THEN lm.Total_Claim END) as LastMonthClaimCount'),
                DB::raw('MAX(CASE WHEN lm.Month = sub.max_month THEN lm.Claim_Amount END) as LastMonthClaimAmount'),
            ])
            ->join(
                DB::raw("(SELECT EmployeeID, YearId, MAX(Month) as max_month
                     FROM {$table}
                     WHERE Total_Claim > 0
                     GROUP BY EmployeeID, YearId) as sub"),
                function ($join) {
                    $join
                        ->on('lm.EmployeeID', '=', 'sub.EmployeeID')
                        ->on('lm.YearId', '=', 'sub.YearId')
                        ->on('lm.Month', '=', 'sub.max_month');
                }
            )
            ->where('lm.Total_Claim', '>', 0)
            ->groupBy('lm.EmployeeID', 'lm.YearId');

        // 2. Main query with JOIN instead of subqueries
        $query = DB::connection(session('expense_connection'))
            ->table($table . ' as m')
            ->leftJoin($hrimsDb . '.hrm_employee as e', 'm.EmployeeID', '=', 'e.EmployeeID')
            ->leftJoin($hrimsDb . '.hrm_employee_general as g', 'e.EmployeeID', '=', 'g.EmployeeID')
            ->leftJoin($hrimsDb . '.core_grades as gr', 'g.GradeId', '=', 'gr.id')
            ->leftJoin($hrimsDb . '.core_departments as dep', 'g.DepartmentId', '=', 'dep.id')
            ->leftJoinSub($lastClaimsSub, 'lc', function ($join) {
                $join
                    ->on('m.EmployeeID', '=', 'lc.EmployeeID')
                    ->on('m.YearId', '=', 'lc.YearId');
            })
            ->select([
                'e.EmployeeID',
                'e.EmpCode',
                DB::raw("CONCAT(e.Fname, ' ', COALESCE(e.Sname, ''), ' ', e.Lname) AS Name"),
                'g.MobileNo_Vnr',
                'e.EmpStatus',
                'e.DateOfResignation',
                'e.DateOfSepration',
                'dep.department_name',
                'gr.grade_name',
                'm.DateOfSubmit',
                'm.Total_Claim',
                'm.Claim_Amount',
                'm.RecevingDate',
                $courierDateCol,
                'm.Month',
                'lc.LastClaimMonth',
                'lc.LastMonthClaimCount',
                'lc.LastMonthClaimAmount',
            ])
            ->where('m.YearId', $yearId)
            ->where('m.Status', 'Closed')
            ->where('m.Total_Claim', '>', 0)
            ->where(function ($q) {
                $q
                    ->where('m.Verified_Amount', 0)
                     ->orWhereNull('m.Verified_Date')
                    ->orWhere('m.Verified_Date', '0000-00-00');
                   
            });
            // ->where(function ($q) {
            //     $q->where('m.Verified_Date', '!=', '1970-01-01')
            //       ->orWhereNull('m.Verified_Date');
            // });

        // Apply filters
        $this->applyCourierFilter($query, $request);
        $this->applyFilters($query, $request);

        // Get filter data for dropdowns
        $months = $this->getPendingClaimsAvailableMonths($request);
        $employees = $this->getPendingClaimsAvailableEmployees($request);
        $departments = $this->getPendingClaimsAvailableDepartments($request);

        // Permission checks
        $canSubmit = auth()->user()->can('pending_claims_submit_to_app');
        $canReturn = auth()->user()->can('pending_claims_return');
        $canEHome = auth()->user()->can('pending_claims_ehome');
        $canViewAttendance = auth()->user()->can('pending_claims_view_attendance');
        $canViewEligibility = auth()->user()->can('pending_claims_view_eligibility');
        $canViewCourier = auth()->user()->can('pending_claims_courier_detail');
        $canViewClaims = auth()->user()->can('view_claim_detail');

        // 5. Return DataTables with query builder (not collection)
        return DataTables::of($query)
            ->addIndexColumn()
            ->filterColumn('EmpCode', function ($query, $keyword) {
                $query->where('e.EmpCode', 'like', "%{$keyword}%");
            })
            ->filterColumn('Name', function ($query, $keyword) {
                $query->whereRaw("CONCAT(e.Fname, ' ', COALESCE(e.Sname, ''), ' ', e.Lname) LIKE ?", ["%{$keyword}%"]);
            })
            ->editColumn('Name', function ($row) use ($canViewClaims) {
                if (!$canViewClaims) {
                    return $row->Name;
                }

                return $this->generateViewClaimLink($row);
            })
            ->editColumn('DateOfSubmit', fn($row) => $row->DateOfSubmit ? Carbon::parse($row->DateOfSubmit)->format('d-m-Y') : '')
            ->editColumn('Month', function ($row) use ($canViewClaims) {
                $monthName = Carbon::createFromDate(null, (int) $row->Month, 1)->format('F');
                if (!$canViewClaims) {
                    return $monthName;
                }

                return '<a href="javascript:void(0);" onclick="' . $this->getViewClaimJS($row) . '" class="text-primary text-decoration-underline">' . $monthName . '</a>';
            })
            ->editColumn('Total_Claim', fn($row) => '<span class="fw-bold">' . number_format($row->Total_Claim, 0) . '</span>')
            ->editColumn('Claim_Amount', fn($row) => '<span class="fw-bold">' . number_format($row->Claim_Amount, 0) . '</span>')
            ->addColumn('Actions', function ($row) use ($canReturn, $canEHome) {
                return $this->generateActionButtons($row, $canReturn, $canEHome);
            })
            ->addColumn('Check', function ($row) use ($canViewAttendance, $canViewEligibility) {
                return $this->generateCheckButtons($row, $canViewAttendance, $canViewEligibility);
            })
            ->addColumn('Courier_Detail', function ($row) use ($canViewCourier) {
                return $this->generateCourierDetail($row, $canViewCourier);
            })
            ->addColumn('Last_Claim_Info', function ($row) {
                return $this->generateLastClaimInfo($row);
            })
            ->rawColumns(['Name', 'Month', 'Total_Claim', 'Claim_Amount', 'Actions', 'Check', 'Courier_Detail', 'Last_Claim_Info'])
            ->with([
                'months' => $months,
                'employees' => $employees,
                'departments' => $departments,
            ])
            ->make(true);
    }

    // Helper methods for employee status resolution & badges
    private function resolveEmployeeStatus($row): string
    {
        $blankDates = ['', '0000-00-00', '1970-01-01', '1971-01-01'];
        $hasResignation = !empty($row->DateOfResignation) && !in_array($row->DateOfResignation, $blankDates) && strtotime($row->DateOfResignation) > strtotime('2000-01-01');
        $hasSeparation = !empty($row->DateOfSepration) && !in_array($row->DateOfSepration, $blankDates) && strtotime($row->DateOfSepration) > strtotime('2000-01-01');

        if ($hasSeparation) {
            $actualStatus = (strtotime($row->DateOfSepration) < strtotime(date('Y-m-d'))) ? 'D' : 'R';
        } elseif ($hasResignation) {
            $actualStatus = (in_array($row->EmpStatus ?? '', ['D', 'De'])) ? 'D' : 'R';
        } elseif (in_array($row->EmpStatus ?? '', ['D', 'De'])) {
            $actualStatus = 'D';
        } else {
            $actualStatus = function_exists('getStsUser') ? (getStsUser($row->EmployeeID, $row) ?: ($row->EmpStatus ?? 'A')) : ($row->EmpStatus ?? 'A');
        }

        return strtoupper(trim((string)($actualStatus ?: 'A')));
    }

    private function getEmployeeStatusBadge(string $status): string
    {
        if ($status === 'R') {
            return '<span class="badge bg-warning-subtle text-warning border border-warning ms-1" style="font-size:10px; font-weight:600;">Resigned</span>';
        }
        if ($status === 'D') {
            return '<span class="badge bg-danger-subtle text-danger border border-danger ms-1" style="font-size:10px; font-weight:600;">Deactive</span>';
        }

        return '<span class="badge bg-success-subtle text-success border border-success ms-1" style="font-size:10px; font-weight:600;">Active</span>';
    }

    private function getEmployeeDetailsHtml($row, string $empStatus): string
    {
        $blankDates = ['', '0000-00-00', '1970-01-01', '1971-01-01'];
        $details = [];
        if (!empty($row->department_name)) {
            $details[] = 'Dept: ' . e($row->department_name);
        }
        if (!empty($row->grade_name)) {
            $details[] = 'Grade: ' . e($row->grade_name);
        }
        if ($empStatus === 'D' || $empStatus === 'R') {
            if (!empty($row->DateOfResignation) && !in_array($row->DateOfResignation, $blankDates) && strtotime($row->DateOfResignation) > strtotime('2000-01-01')) {
                $details[] = '<span class="text-danger fw-semibold">Resigned: ' . Carbon::parse($row->DateOfResignation)->format('d-m-Y') . '</span>';
            }
            if (!empty($row->DateOfSepration) && !in_array($row->DateOfSepration, $blankDates) && strtotime($row->DateOfSepration) > strtotime('2000-01-01')) {
                if (empty($row->DateOfResignation) || $row->DateOfResignation !== $row->DateOfSepration) {
                    $details[] = '<span class="text-danger fw-semibold">Separation: ' . Carbon::parse($row->DateOfSepration)->format('d-m-Y') . '</span>';
                }
            }
        }

        return !empty($details) ? '<div class="text-muted small" style="font-size:11px; font-weight:normal;">' . implode(' &bull; ', $details) . '</div>' : '';
    }

    // Helper method to generate view claim link
    private function generateViewClaimLink($row)
    {
        $dateOfSubmit = $row->DateOfSubmit ? Carbon::parse($row->DateOfSubmit)->format('d-m-Y') : '';
        $lastClaimMonth = $row->LastClaimMonth ? Carbon::createFromDate(null, (int) $row->LastClaimMonth, 1)->format('M') : '';
        $lastMonthClaimAmount = $row->LastMonthClaimAmount ?? '';
        $recevingDate = $row->RecevingDate ? Carbon::parse($row->RecevingDate)->format('d-m-Y') : '';

        $empStatus = $this->resolveEmployeeStatus($row);
        $statusBadge = $this->getEmployeeStatusBadge($empStatus);
        $detailsStr = $this->getEmployeeDetailsHtml($row, $empStatus);

        return '<a href="javascript:void(0);" onclick="viewClaim('
            . $row->EmployeeID . ', '
            . $row->Month . ', '
            . "'" . $dateOfSubmit . "', "
            . "'" . $lastClaimMonth . "', "
            . "'" . $lastMonthClaimAmount . "', "
            . "'" . $recevingDate . "'"
            . ')" class="text-primary fw-bold text-decoration-underline" title="View Claims">'
            . e($row->Name)
            . '</a> '
            . $statusBadge
            . $detailsStr;
    }

    // Helper method to get view claim JS
    private function getViewClaimJS($row)
    {
        $dateOfSubmit = $row->DateOfSubmit ? Carbon::parse($row->DateOfSubmit)->format('d-m-Y') : '';
        $lastClaimMonth = $row->LastClaimMonth ? Carbon::createFromDate(null, (int) $row->LastClaimMonth, 1)->format('M') : '';
        $lastMonthClaimAmount = $row->LastMonthClaimAmount ?? '';
        $recevingDate = $row->RecevingDate ? Carbon::parse($row->RecevingDate)->format('d-m-Y') : '';

        return "viewClaim({$row->EmployeeID}, {$row->Month}, '{$dateOfSubmit}', '{$lastClaimMonth}', '{$lastMonthClaimAmount}', '{$recevingDate}')";
    }

    // Helper method to generate action buttons
    private function generateActionButtons($row, $canReturn, $canEHome)
    {
        $actions = '<div class="d-flex justify-content-center gap-2">';

        if ($canReturn) {
            $actions .= '<a href="javascript:void(0);" onclick="returnClaim('
                . $row->EmployeeID . ', '
                . $row->Month . ', '
                . session('year_id') . ', '
                . session('company_id')
                . ')" class="badge bg-danger-subtle text-danger text-decoration-none" title="Return">'
                . '<i class="ri-arrow-go-back-line"></i> Return</a>';
        }

        if ($canEHome) {
            $actions .= '<a href="javascript:void(0);"'
                . ' class="badge bg-primary-subtle text-primary text-decoration-none ehome-btn"'
                . ' data-employee-id="' . $row->EmployeeID . '"'
                . ' data-year-id="' . session('year_id') . '"'
                . ' data-company-id="' . session('company_id') . '"'
                . ' title="E-Home">'
                . '<i class="ri-home-3-line"></i> E-Home</a>';
        }

        $actions .= '</div>';

        return $actions;
    }

    // Helper method to generate check buttons
    private function generateCheckButtons($row, $canViewAttendance, $canViewEligibility)
    {
        static $fyYearCache = [];
        $yearId = session('year_id');
        if (!isset($fyYearCache[$yearId])) {
            try {
                $fy = DB::connection(session('expense_connection'))->table('financialyear')->where('YearId', $yearId)->first(['Year', 'FromDate', 'ToDate']);
                if ($fy && !empty($fy->FromDate) && !empty($fy->ToDate)) {
                    $fyYearCache[$yearId] = [
                        'start' => Carbon::parse($fy->FromDate)->year,
                        'end'   => Carbon::parse($fy->ToDate)->year,
                    ];
                } elseif ($fy && !empty($fy->Year) && str_contains($fy->Year, '-')) {
                    $parts = explode('-', $fy->Year);
                    $fyYearCache[$yearId] = [
                        'start' => (int) trim($parts[0]),
                        'end'   => (int) trim($parts[1]),
                    ];
                } else {
                    $fyYearCache[$yearId] = [
                        'start' => (int) date('Y'),
                        'end'   => (int) date('Y'),
                    ];
                }
            } catch (\Exception $e) {
                $fyYearCache[$yearId] = [
                    'start' => (int) date('Y'),
                    'end'   => (int) date('Y'),
                ];
            }
        }
        $calYear = ((int) $row->Month >= 4) ? $fyYearCache[$yearId]['start'] : $fyYearCache[$yearId]['end'];

        $attendanceUrl = "https://vnrseeds.co.in/public/api/Att_records?empid={$row->EmployeeID}"
            . "&Month={$row->Month}"
            . '&Year=' . $calYear;

        $eligibilityUrl = "https://vnrseeds.co.in/public/api/Eligibility_records?empid={$row->EmployeeID}";

        $html = '<div class="d-flex justify-content-center gap-2">';

        if ($canViewAttendance) {
            $html .= '<a href="javascript:void(0);" onclick="openPopUpWindow(\'' . $attendanceUrl . '\')" class="text-primary" title="Attendance Calendar">'
                . '<i class="ri-eye-line" style="font-size:18px;"></i></a>';
        }

        if ($canViewEligibility) {
            $html .= '<a href="javascript:void(0);" onclick="openPopUpWindow(\'' . $eligibilityUrl . '\')" class="text-info" title="Open Eligibility">'
                . '<i class="ri-file-list-3-line" style="font-size:18px;"></i></a>';
        }

        // Running KM detail button — uses the reusable modal component
        $html .= '<a href="javascript:void(0);" '
            . 'onclick="openEmpKmModal(' . $row->EmployeeID . ", '" . e(addslashes($row->Name ?? '')) /* UAT-03: escaped for the HTML attribute */ . '\')" '
            . 'class="text-success" title="View Running KM">'
            . '<i class="ri-roadster-line" style="font-size:18px;"></i></a>';

        $html .= '</div>';

        return $html;
    }

    // Check and ensure courier_date column existence
    private function hasCourierDateColumn($table, $conn): bool
    {
        static $hasCol = [];
        $key = "{$conn}_{$table}";
        if (isset($hasCol[$key])) {
            return $hasCol[$key];
        }

        try {
            // VVNR-COURIER-01: column added by migration 2026_11_05_000011, not by ALTER TABLE at run time
            if (!\Illuminate\Support\Facades\Schema::connection($conn)->hasColumn($table, 'courier_date')) {
                $hasCol[$key] = false;
                return false;
            }
            $hasCol[$key] = true;
            return true;
        } catch (\Throwable $e) {
            $hasCol[$key] = false;
            return false;
        }
    }

    // Helper method to generate courier detail
    private function generateCourierDetail($row, $canViewCourier)
    {
        if (!$canViewCourier) {
            return '<span class="text-muted">-</span>';
        }

        $empId = $row->EmployeeID;
        $month = $row->Month;

        if (empty($row->RecevingDate) || \App\Support\ZeroDate::none($row->RecevingDate)) {
            $html = '<a href="javascript:void(0);" data-bs-target="#courierModal" class="courier-detail text-danger text-decoration-underline" onclick="openCourierDetail('
                . $empId . ', ' . $month . ', \'not_received\', this)" title="Courier Not Received">Not Received</a>';
        } else {
            $html = '<a href="javascript:void(0);" data-bs-target="#courierModal" class="courier-detail text-success text-decoration-underline" onclick="openCourierDetail('
                . $empId . ', ' . $month . ', \'received\', this)" title="Courier Received">Received</a>';
        }

        // In VVNR, show "DMS Recvd" below the link when DMS receiving is completed
        $isVvnr = in_array((int) session('company_id'), [11, 2], true);
        if ($isVvnr && !empty($row->courier_date) && \App\Support\ZeroDate::has($row->courier_date) && $row->courier_date !== '1970-01-01') {
            $dmsDate = Carbon::parse($row->courier_date)->format('d-m-Y');
            $html .= '<div class="mt-1"><span class="badge bg-info-subtle text-info fs-11" title="DMS Received: ' . $dmsDate . '"><i class="ri-checkbox-circle-line me-1"></i>DMS Recvd</span></div>';
        }

        return $html;
    }

    // Helper method to generate last claim info
    private function generateLastClaimInfo($row)
    {
        if ($row->LastClaimMonth && $row->LastMonthClaimAmount) {
            $monthName = Carbon::createFromDate(null, (int) $row->LastClaimMonth, 1)->format('M');

            return $monthName . ' - ' . number_format($row->LastMonthClaimAmount, 0);
        }

        return '<span class="text-muted">N/A</span>';
    }

    // Helper methods for optimization
    private function applyCourierFilter($query, Request $request)
    {
        $courierStatus = $request->input('courier_status', 'all');
        if ($courierStatus === 'received') {
            $query
                ->where('m.RecevingDate', '!=', '0000-00-00')
                ->whereNotNull('m.RecevingDate');
        } elseif ($courierStatus === 'not_received') {
            $query->where(function ($q) {
                $q
                    ->where('m.RecevingDate', '0000-00-00')
                    ->orWhereNull('m.RecevingDate');
            });
        } elseif ($courierStatus === 'dms_received') {
            $query
                ->whereNotNull('m.courier_date')
                ->where('m.courier_date', '!=', '0000-00-00')
                ->where('m.courier_date', '!=', '1970-01-01');
        }

        if (session('company_id') == 11) {
            $query
                ->where('m.Fin_PayDate', '!=', '0000-00-00')
                ->whereNotNull('m.Fin_PayDate');
        }
    }

    // Helper methods for optimization

    private function applyFilters($query, Request $request)
    {
        if ($request->filled('month')) {
            $query->where('m.Month', (int) $request->month);
        }
        if ($request->filled('department')) {
            $query->where('dep.id', $request->department);
        }
        if ($request->filled('employee')) {
            $query->where('m.EmployeeID', $request->employee);
        }

        $query
            ->orderBy('m.Month', 'DESC')
            ->orderBy('e.EmpCode', 'ASC');
    }

    // Optimized filter methods with indexes
    protected function getPendingClaimsAvailableMonthsOptimized(Request $request, $yearId)
    {
        $cacheKey = "pending_months_{$yearId}_" . md5(serialize($request->all()));

        return cache()->remember($cacheKey, 600, function () use ($request, $yearId) {
            $query = DB::connection(session('expense_connection'))
                ->table("y{$yearId}_monthexpensefinal as m")
                ->where('m.YearId', $yearId)
                ->where('m.Status', 'Closed')
                ->where('m.Total_Claim', '>', 0)
                ->where(function ($q) {
                    $q
                        ->where('m.Verified_Amount', 0)
                        ->orWhere('m.Verified_Date', '0000-00-00')
                        ->orWhereNull('m.Verified_Date');
                });

            // Apply same filters as main query
            $this->applyCourierFilter($query, $request);

            return $query
                ->select(DB::raw('DISTINCT m.Month as month_value'))
                ->whereNotNull('m.Month')
                ->orderBy('m.Month')
                ->get()
                ->map(fn($item) => [
                    'value' => (int) $item->month_value,
                    'text' => Carbon::createFromDate(null, (int) $item->month_value, 1)->format('F'),
                ]);
        });
    }

    public function verifyApprovedClaimsData(Request $request)
    {
        $yearId = session('year_id');
        $companyId = session('company_id');  // Get company ID from session
        $table = "y{$yearId}_monthexpensefinal";
        $fromDate = $request->input('from_date');
        $toDate = $request->input('to_date');
        $hrimsDb = \App\Helpers\DatabaseSwitcher::getHrimsDatabaseName();
        $conn = session('expense_connection') ?: \App\Helpers\DatabaseSwitcher::getExpenseConnection();
        $courierDateCol = $this->hasCourierDateColumn($table, $conn) ? 'm.courier_date' : DB::raw('NULL as courier_date');

        $query = DB::connection($conn)
            ->table($table . ' as m')
            ->leftJoin($hrimsDb . '.hrm_employee as e', 'm.EmployeeID', '=', 'e.EmployeeID')
            ->leftJoin($hrimsDb . '.hrm_employee_general as g', 'e.EmployeeID', '=', 'g.EmployeeID')
            ->leftJoin($hrimsDb . '.core_grades as gr', 'g.GradeId', '=', 'gr.id')
            ->leftJoin($hrimsDb . '.core_departments as dep', 'g.DepartmentId', '=', 'dep.id')
            ->select([
                'e.EmployeeID',
                'e.EmpCode',
                DB::raw("CONCAT(e.Fname, ' ', COALESCE(e.Sname, ''), ' ', e.Lname) AS Name"),
                'g.MobileNo_Vnr',
                'e.EmpStatus',
                'e.DateOfResignation',
                'e.DateOfSepration',
                'dep.department_name',
                'gr.grade_name',
                'm.DateOfSubmit',
                'm.Total_Claim',
                'm.Claim_Amount',
                'm.RecevingDate',
                $courierDateCol,
                'm.Month',
                'm.Verified_Amount',
                'm.Verified_Date',
                'm.Approved_Amount',
                'm.Approved_Date',
                'm.id as claim_id',  // Add claim ID
            ])
            ->where('m.YearId', $yearId)
            ->where('m.Status', 'Closed')
            ->where('m.Total_Claim', '>', 0)
            ->where('m.Verified_Date', '!=', '0000-00-00')
            ->whereNotNull('m.Verified_Date')
            ->where('m.Verified_Date', '!=', '1970-01-01');

        if (!empty($fromDate) && !empty($toDate)) {
            try {
                $fromDateObj = Carbon::parse($fromDate)->startOfDay();
                $toDateObj = Carbon::parse($toDate)->endOfDay();
                $query->whereBetween('m.Verified_Date', [$fromDateObj, $toDateObj]);
            } catch (\Exception $e) {
                // Invalid date format
            }
        } elseif (!empty($fromDate)) {
            // EXPORT-29SEP: one date picked means that day only (it meant "from that day on",
            // so a 29 Sep Focus export took claims verified on 30 Sep too - Finance mail 3 Oct)
            $query->whereBetween('m.Verified_Date', [Carbon::parse($fromDate)->startOfDay(), Carbon::parse($fromDate)->endOfDay()]);
        } elseif (!empty($toDate)) {
            $query->whereBetween('m.Verified_Date', [Carbon::parse($toDate)->startOfDay(), Carbon::parse($toDate)->endOfDay()]);
        }

        $courierStatus = $request->input('courier_status', 'all');
        if ($courierStatus === 'received') {
            $query->where('m.RecevingDate', '!=', '0000-00-00')->whereNotNull('m.RecevingDate');
        } elseif ($courierStatus === 'not_received') {
            $query->where(function ($q) {
                $q->where('m.RecevingDate', '0000-00-00')->orWhereNull('m.RecevingDate');
            });
        } elseif ($courierStatus === 'dms_received') {
            $query
                ->whereNotNull('m.courier_date')
                ->where('m.courier_date', '!=', '0000-00-00')
                ->where('m.courier_date', '!=', '1970-01-01');
        }

        if ($request->filled('month')) {
            $query->where('m.Month', (int) $request->month);
        }
        if ($request->filled('department')) {
            $query->where('dep.id', $request->department);
        }
        if ($request->filled('employee')) {
            $query->where('m.EmployeeID', $request->employee);
        }

        $query->orderBy('m.Month', 'DESC')->orderBy('e.EmpCode', 'ASC');

        $isInitialOrFilterChange = ((int) $request->input('start', 0) === 0);
        $months = null;
        $employees = null;
        $departments = null;

        if ($isInitialOrFilterChange) {
            $months = $this->getApprovedClaimsAvailableMonths($request);
            $employees = $this->getApprovedClaimsAvailableEmployees($request);
            $departments = $this->getApprovedClaimsAvailableDepartments($request);
        }

        $canEHome = auth()->user()->can('pending_claims_ehome');
        $canViewCourier = auth()->user()->can('pending_claims_courier_detail');
        $canViewAttendance = auth()->user()->can('pending_claims_view_attendance');
        $canViewEligibility = auth()->user()->can('pending_claims_view_eligibility');
        $canRevert = auth()->user()->can('verifier_revert');

        $dataTable = DataTables::of($query)
            ->addIndexColumn()
            ->filterColumn('EmpCode', function ($query, $keyword) {
                $query->where('e.EmpCode', 'like', "%{$keyword}%");
            })
            ->filterColumn('Name', function ($query, $keyword) {
                $query->whereRaw("CONCAT(e.Fname, ' ', COALESCE(e.Sname, ''), ' ', e.Lname) LIKE ?", ["%{$keyword}%"]);
            })
            ->orderColumn('EmpCode', 'e.EmpCode $1')
            ->orderColumn('Name', 'e.Fname $1, e.Lname $1')
            ->orderColumn('Month', 'm.Month $1')
            ->orderColumn('DateOfSubmit', 'm.DateOfSubmit $1')
            ->orderColumn('Total_Claim', 'm.Total_Claim $1')
            ->orderColumn('Claim_Amount', 'm.Claim_Amount $1')
            ->orderColumn('Verified_Amount', 'm.Verified_Amount $1')
            ->orderColumn('Verified_Date', 'm.Verified_Date $1')
            ->addColumn('checkbox', function ($row) use ($companyId) {
                if ($companyId == 11) {
                    $paymentAmount = $row->Verified_Amount ?? 0;
                    $employeeId = $row->EmployeeID;
                    $month = $row->Month;
                    $claimId = $row->claim_id ?? $row->id;

                    // Return just the input, not wrapped in td tags
                    return '<input type="checkbox" class="row-check"
                    value="' . $claimId . '"
                    data-emp="' . $employeeId . '"
                    data-amount="' . $paymentAmount . '"
                    data-month="' . $month . '">';
                }

                return '';
            })
            ->editColumn('Name', function ($row) {
                $dateOfSubmit = $row->DateOfSubmit ? Carbon::parse($row->DateOfSubmit)->format('d-m-Y') : '';
                $recevingDate = $row->RecevingDate ? Carbon::parse($row->RecevingDate)->format('d-m-Y') : '';

                $empStatus = $this->resolveEmployeeStatus($row);
                $statusBadge = $this->getEmployeeStatusBadge($empStatus);
                $detailsStr = $this->getEmployeeDetailsHtml($row, $empStatus);

                return '<a href="javascript:void(0);" onclick="viewClaim(' . $row->EmployeeID . ', ' . $row->Month . ', ' . "'" . $dateOfSubmit . "', " . "'' , " . "'' , " . "'" . $recevingDate . "'" . ')" class="text-primary fw-bold text-decoration-underline" title="View Claims">' . e($row->Name) . '</a> ' . $statusBadge . $detailsStr;
            })
            ->editColumn('DateOfSubmit', fn($row) => $row->DateOfSubmit ? Carbon::parse($row->DateOfSubmit)->format('d-m-Y') : '')
            ->editColumn('Month', function ($row) {
                $monthName = Carbon::createFromDate(null, (int) $row->Month, 1)->format('F');

                return '<a href="javascript:void(0);" onclick="viewClaim(' . $row->EmployeeID . ', ' . $row->Month . ', ' . "'" . ($row->DateOfSubmit ? Carbon::parse($row->DateOfSubmit)->format('d-m-Y') : '') . "', " . "'' , " . "'' , " . "'" . ($row->RecevingDate ? Carbon::parse($row->RecevingDate)->format('d-m-Y') : '') . "'" . ')" class="text-primary text-decoration-underline" title="View Claims">' . $monthName . '</a>';
            })
            ->editColumn('Total_Claim', fn($row) => '<span class="fw-bold">' . number_format($row->Total_Claim, 0) . '</span>')
            ->editColumn('Claim_Amount', fn($row) => '<span class="fw-bold">' . number_format($row->Claim_Amount, 0) . '</span>')
            ->editColumn('Verified_Amount', fn($row) => '<span class="fw-bold">' . number_format($row->Verified_Amount, 0) . '</span>')
            ->editColumn('Verified_Date', fn($row) => $row->Verified_Date && \App\Support\ZeroDate::has($row->Verified_Date) ? Carbon::parse($row->Verified_Date)->format('d-m-Y') : '')
            ->addColumn('Actions', function ($row) use ($canEHome, $canRevert, $companyId) {
                $actions = '<div class="d-flex justify-content-center gap-2">';
                if ($canRevert) {
                    $currentCompanyId = (int) ($companyId ?? session('company_id', 1));
                    $isCompany11 = ($currentCompanyId === 11);

                    if ($isCompany11) {
                        // Company 11 flow: claim -> approve -> finance -> verify
                        // Since verify is the last step (after approve and finance),
                        // Approver & Finance already acted before verifier.
                        // Therefore, Approved_Date / Approved_Amount should not lock revert.
                        $actions .= '<a href="javascript:void(0);" onclick="revertClaim('
                            . $row->EmployeeID . ', '
                            . $row->Month . ', '
                            . session('year_id') . ', '
                            . $currentCompanyId
                            . ')" class="badge bg-warning-subtle text-warning text-decoration-none" title="Revert to Pending">'
                            . '<i class="ri-history-line"></i> Revert</a>';
                    } else {
                        // Company 1 flow: claim -> verify -> approve -> finance
                        // Verify is step 2. If Approver has already approved (step 3), revert is locked.
                        $hasValidApprDate = !empty($row->Approved_Date)
                            && \App\Support\ZeroDate::has($row->Approved_Date)
                            && $row->Approved_Date !== '1970-01-01';
                        $hasApprAmt = floatval($row->Approved_Amount ?? 0) > 0;

                        if ($hasValidApprDate && $hasApprAmt) {
                            $actions .= '<span class="badge bg-secondary-subtle text-muted border border-secondary-subtle text-decoration-none" title="Revert Locked: Claim has already been approved by Approver" style="cursor: not-allowed; opacity: 0.7;">'
                                . '<i class="ri-lock-line"></i> Revert</span>';
                        } else {
                            $actions .= '<a href="javascript:void(0);" onclick="revertClaim('
                                . $row->EmployeeID . ', '
                                . $row->Month . ', '
                                . session('year_id') . ', '
                                . $currentCompanyId
                                . ')" class="badge bg-warning-subtle text-warning text-decoration-none" title="Revert to Pending">'
                                . '<i class="ri-history-line"></i> Revert</a>';
                        }
                    }
                }
                if ($canEHome) {
                    $actions .= '<a href="javascript:void(0);"'
                        . ' class="badge bg-primary-subtle text-primary text-decoration-none ehome-btn"'
                        . ' data-employee-id="' . $row->EmployeeID . '"'
                        . ' data-year-id="' . session('year_id') . '"'
                        . ' data-company-id="' . ($currentCompanyId ?? session('company_id')) . '"'
                        . ' title="E-Home"><i class="ri-home-3-line"></i> E-Home</a>';
                }
                $actions .= '</div>';

                return $actions;
            })
            ->addColumn('Check', function ($row) use ($canViewAttendance, $canViewEligibility) {
                return $this->generateCheckButtons($row, $canViewAttendance, $canViewEligibility);
            })
            ->addColumn('Courier_Detail', function ($row) use ($canViewCourier) {
                return $this->generateCourierDetail($row, $canViewCourier);
            })
            ->rawColumns(['checkbox', 'Name', 'Check', 'Month', 'Total_Claim', 'Claim_Amount', 'Verified_Amount', 'Actions', 'Courier_Detail']);

        $withData = [];
        if ($months !== null) {
            $withData['months'] = $months;
        }
        if ($employees !== null) {
            $withData['employees'] = $employees;
        }
        if ($departments !== null) {
            $withData['departments'] = $departments;
        }

        return $dataTable->with($withData)->make(true);
    }

    // public function verifyApprovedClaimsData(Request $request)
    // {
    //     $yearId = session('year_id');
    //     $table = "y{$yearId}_monthexpensefinal";
    //     $hrimsDb = \App\Helpers\DatabaseSwitcher::getHrimsDatabaseName();
    //     $query = DB::connection(session('expense_connection'))->table($table . ' as m')->leftJoin($hrimsDb . '.hrm_employee as e', 'm.EmployeeID', '=', 'e.EmployeeID')->leftJoin($hrimsDb . '.hrm_employee_general as g', 'e.EmployeeID', '=', 'g.EmployeeID')->leftJoin($hrimsDb . '.core_grades as gr', 'g.GradeId', '=', 'gr.id')->leftJoin($hrimsDb . '.core_departments as dep', 'g.DepartmentId', '=', 'dep.id')->select(['e.EmployeeID', 'e.EmpCode', DB::raw("CONCAT(e.Fname, ' ', COALESCE(e.Sname, ''), ' ', e.Lname) AS Name"), 'g.MobileNo_Vnr', 'e.EmpStatus', 'dep.department_name', 'gr.grade_name', 'm.DateOfSubmit', 'm.Total_Claim', 'm.Claim_Amount', 'm.RecevingDate', 'm.Month', 'm.Verified_Amount', 'm.Verified_Date'])->where('m.YearId', $yearId)->where('m.Status', 'Closed')->where('m.Total_Claim', '>', 0)->where('m.Verified_Date', '!=', '0000-00-00')->where('m.Verified_Date', '!=', '1970-01-01');
    //     $courierStatus = $request->input('courier_status', 'all');
    //     if ($courierStatus === 'received') {
    //         $query->where('m.RecevingDate', '!=', '0000-00-00')->whereNotNull('m.RecevingDate');
    //     } elseif ($courierStatus === 'not_received') {
    //         $query->where(function ($q) {
    //             $q->where('m.RecevingDate', '0000-00-00')->orWhereNull('m.RecevingDate');
    //         });
    //     }
    //     if ($request->filled('month')) {
    //         $query->where('m.Month', (int) $request->month);
    //     }
    //     if ($request->filled('department')) {
    //         $query->where('dep.id', $request->department);
    //     }
    //     if ($request->filled('employee')) {
    //         $query->where('m.EmployeeID', $request->employee);
    //     }
    //     $records = $query->orderBy('m.Month', 'DESC')->orderBy('e.EmpCode', 'ASC')->get();
    //     $months = $this->getApprovedClaimsAvailableMonths($request);
    //     $employees = $this->getApprovedClaimsAvailableEmployees($request);
    //     $departments = $this->getApprovedClaimsAvailableDepartments($request);
    //     $canEHome = auth()->user()->can('pending_claims_ehome');
    //     $canViewCourier = auth()->user()->can('pending_claims_courier_detail');
    //     return DataTables::of($records)->addIndexColumn()->editColumn('Name', function ($row) {
    //         return '<a href="javascript:void(0);" onclick="viewClaim(' . $row->EmployeeID . ', ' . $row->Month . ', ' . "'" . ($row->DateOfSubmit ? Carbon::parse($row->DateOfSubmit)->format('d-m-Y') : '') . "', " . "'' , " . "'' , " . "'" . ($row->RecevingDate ? Carbon::parse($row->RecevingDate)->format('d-m-Y') : '') . "'" . ')" class="text-primary text-decoration-underline" title="View Claims">' . $row->Name . '</a>';
    //     })->editColumn('DateOfSubmit', fn($row) => $row->DateOfSubmit ? Carbon::parse($row->DateOfSubmit)->format('d-m-Y') : '')->editColumn('Month', function ($row) {
    //         $monthName = Carbon::createFromDate(null, (int) $row->Month, 1)->format('F');
    //         return '<a href="javascript:void(0);" onclick="viewClaim(' . $row->EmployeeID . ', ' . $row->Month . ', ' . "'" . ($row->DateOfSubmit ? Carbon::parse($row->DateOfSubmit)->format('d-m-Y') : '') . "', " . "'' , " . "'' , " . "'" . ($row->RecevingDate ? Carbon::parse($row->RecevingDate)->format('d-m-Y') : '') . "'" . ')" class="text-primary text-decoration-underline" title="View Claims">' . $monthName . '</a>';
    //     })->editColumn('Total_Claim', fn($row) => '<span class="fw-bold">' . number_format($row->Total_Claim, 0) . '</span>')->editColumn('Claim_Amount', fn($row) => '<span class="fw-bold">' . number_format($row->Claim_Amount, 0) . '</span>')->editColumn('Verified_Amount', fn($row) => '<span class="fw-bold">' . number_format($row->Verified_Amount, 0) . '</span>')->editColumn('Verified_Date', fn($row) => $row->Verified_Date && \App\Support\ZeroDate::has($row->Verified_Date) ? Carbon::parse($row->Verified_Date)->format('d-m-Y') : '')->addColumn('Actions', function ($row) use ($canEHome) {
    //         $actions = '<div class="d-flex justify-content-center gap-2">';
    //         if ($canEHome) {
    //             $actions .= '<a href="javascript:void(0);" class="badge bg-primary-subtle text-primary text-decoration-none ehome-btn" data-employee-id="' . $row->EmployeeID . '" title="E-Home"><i class="ri-home-3-line"></i> E-Home</a>';
    //         }
    //         $actions .= '</div>';
    //         return $actions;
    //     })->addColumn('Courier_Detail', function ($row) use ($canViewCourier) {
    //         if (!$canViewCourier) {
    //             return '<span class="text-muted">-</span>';
    //         }
    //         $empId = $row->EmployeeID;
    //         $month = $row->Month;
    //         if (empty($row->RecevingDate) || \App\Support\ZeroDate::none($row->RecevingDate)) {
    //             return '
    //                     <a href="javascript:void(0);"
    //                        class="courier-detail text-danger text-decoration-underline"
    //                        data-bs-target="#courierModal"
    //                        onclick="openCourierDetail(' . $empId . ', ' . $month . ', \'not_received\', this)"
    //                        title="Courier Not Received">
    //                         Not Received
    //                     </a>
    //                 ';
    //         }
    //         return '
    //                 <a href="javascript:void(0);"
    //                    class="courier-detail text-success text-decoration-underline"
    //                    data-bs-target="#courierModal"
    //                    onclick="openCourierDetail(' . $empId . ', ' . $month . ', \'received\', this)"
    //                    title="Courier Received">
    //                     Received
    //                 </a>
    //             ';
    //     })->rawColumns(['Name', 'Month', 'Total_Claim', 'Claim_Amount', 'Verified_Amount', 'Actions', 'Courier_Detail'])->with(['months' => $months, 'employees' => $employees, 'departments' => $departments])->make(true);
    // }

    protected function getPendingClaimsAvailableMonths(Request $request)
    {
        $yearId = session('year_id');
        $table = "y{$yearId}_monthexpensefinal";
        $hrimsDb = \App\Helpers\DatabaseSwitcher::getHrimsDatabaseName();
        $query = DB::connection(session('expense_connection'))->table($table . ' as m')->leftJoin($hrimsDb . '.hrm_employee_general as g', 'm.EmployeeID', '=', 'g.EmployeeID')->leftJoin($hrimsDb . '.core_departments as dep', 'g.DepartmentId', '=', 'dep.id')->where('m.YearId', $yearId)->where('m.Status', 'Closed')->where('m.Total_Claim', '>', 0)->where(function ($q) {
            $q->where('m.Verified_Amount', 0)->orWhere('m.Verified_Date', '0000-00-00')->orWhereNull('m.Verified_Date');
        })->where('m.Verified_Date', '!=', '1970-01-01');
        $courierStatus = $request->input('courier_status', 'all');
        if ($courierStatus === 'received') {
            $query->where('m.RecevingDate', '!=', '0000-00-00')->whereNotNull('m.RecevingDate');
        } elseif ($courierStatus === 'not_received') {
            $query->where(function ($q) {
                $q->where('m.RecevingDate', '0000-00-00')->orWhereNull('m.RecevingDate');
            });
        } elseif ($courierStatus === 'dms_received') {
            $query
                ->whereNotNull('m.courier_date')
                ->where('m.courier_date', '!=', '0000-00-00')
                ->where('m.courier_date', '!=', '1970-01-01');
        }
        if (session('company_id') == 11) {
            $query->where('m.Fin_PayDate', '!=', '0000-00-00')->whereNotNull('m.Fin_PayDate');
        }
        if ($request->filled('department')) {
            $query->where('dep.id', $request->department);
        }
        if ($request->filled('employee')) {
            $query->where('m.EmployeeID', $request->employee);
        }

        return $query->select(DB::raw('DISTINCT m.Month as month_value'))->whereNotNull('m.Month')->orderBy('m.Month')->get()->map(function ($item) {
            return ['value' => (int) $item->month_value, 'text' => Carbon::createFromDate(null, (int) $item->month_value, 1)->format('F')];
        });
    }

    protected function getPendingClaimsAvailableEmployees(Request $request)
    {
        $yearId = session('year_id');
        $table = "y{$yearId}_monthexpensefinal";
        $hrimsDb = \App\Helpers\DatabaseSwitcher::getHrimsDatabaseName();
        $query = DB::connection(session('expense_connection'))->table($table . ' as m')->leftJoin($hrimsDb . '.hrm_employee_general as g', 'm.EmployeeID', '=', 'g.EmployeeID')->leftJoin($hrimsDb . '.core_departments as dep', 'g.DepartmentId', '=', 'dep.id')->where('m.YearId', $yearId)->where('m.Status', 'Closed')->where('m.Total_Claim', '>', 0)->where(function ($q) {
            $q->where('m.Verified_Amount', 0)->orWhere('m.Verified_Date', '0000-00-00')->orWhereNull('m.Verified_Date');
        });
        $courierStatus = $request->input('courier_status', 'all');
        if ($courierStatus === 'received') {
            $query->where('m.RecevingDate', '!=', '0000-00-00')->whereNotNull('m.RecevingDate');
        } elseif ($courierStatus === 'not_received') {
            $query->where(function ($q) {
                $q->where('m.RecevingDate', '0000-00-00')->orWhereNull('m.RecevingDate');
            });
        } elseif ($courierStatus === 'dms_received') {
            $query
                ->whereNotNull('m.courier_date')
                ->where('m.courier_date', '!=', '0000-00-00')
                ->where('m.courier_date', '!=', '1970-01-01');
        }
        if (session('company_id') == 11) {
            $query->where('m.Fin_PayDate', '!=', '0000-00-00')->whereNotNull('m.Fin_PayDate');
        }
        if ($request->filled('month')) {
            $query->where('m.Month', (int) $request->month);
        }
        if ($request->filled('department')) {
            $query->where('dep.id', $request->department);
        }
        $employeeIds = $query->select('m.EmployeeID')->distinct()->pluck('m.EmployeeID')->toArray();
        if (empty($employeeIds)) {
            return collect();
        }

        return DB::connection(session('hrims_connection'))->table('hrm_employee')->whereIn('EmployeeID', $employeeIds)->select(['EmployeeID', 'EmpCode', DB::raw("CONCAT(EmpCode, ' - ', Fname, ' ', COALESCE(Sname, ''), ' ', Lname) as EmpName")])->orderBy('EmpCode')->get()->map(function ($employee) {
            return ['value' => $employee->EmployeeID, 'text' => $employee->EmpName];
        });
    }

    protected function getPendingClaimsAvailableDepartments(Request $request)
    {
        $yearId = session('year_id');
        $table = "y{$yearId}_monthexpensefinal";
        $hrimsDb = \App\Helpers\DatabaseSwitcher::getHrimsDatabaseName();
        $query = DB::connection(session('expense_connection'))->table($table . ' as m')->leftJoin($hrimsDb . '.hrm_employee_general as g', 'm.EmployeeID', '=', 'g.EmployeeID')->leftJoin($hrimsDb . '.core_departments as dep', 'g.DepartmentId', '=', 'dep.id')->where('m.YearId', $yearId)->where('m.Status', 'Closed')->where('m.Total_Claim', '>', 0)->where(function ($q) {
            $q->where('m.Verified_Amount', 0)->orWhere('m.Verified_Date', '0000-00-00')->orWhereNull('m.Verified_Date');
        })->where('m.Verified_Date', '!=', '1970-01-01');
        $courierStatus = $request->input('courier_status', 'all');
        if ($courierStatus === 'received') {
            $query->where('m.RecevingDate', '!=', '0000-00-00')->whereNotNull('m.RecevingDate');
        } elseif ($courierStatus === 'not_received') {
            $query->where(function ($q) {
                $q->where('m.RecevingDate', '0000-00-00')->orWhereNull('m.RecevingDate');
            });
        } elseif ($courierStatus === 'dms_received') {
            $query
                ->whereNotNull('m.courier_date')
                ->where('m.courier_date', '!=', '0000-00-00')
                ->where('m.courier_date', '!=', '1970-01-01');
        }
        if (session('company_id') == 11) {
            $query->where('m.Fin_PayDate', '!=', '0000-00-00')->whereNotNull('m.Fin_PayDate');
        }
        if ($request->filled('month')) {
            $query->where('m.Month', (int) $request->month);
        }
        if ($request->filled('employee')) {
            $query->where('m.EmployeeID', $request->employee);
        }

        return $query->select('dep.id', 'dep.department_name')->whereNotNull('dep.id')->whereNotNull('dep.department_name')->distinct()->orderBy('dep.department_name')->get()->map(function ($department) {
            return ['value' => $department->id, 'text' => $department->department_name];
        });
    }

    protected function getApprovedClaimsAvailableMonths(Request $request)
    {
        $yearId = session('year_id');
        $companyId = session('company_id', 1);
        $cacheKey = "approved_months_{$companyId}_{$yearId}_" . md5(serialize($request->only(['courier_status', 'department', 'employee'])));

        return cache()->remember($cacheKey, 120, function () use ($request, $yearId) {
            $table = "y{$yearId}_monthexpensefinal";
            $query = DB::connection(session('expense_connection'))
                ->table($table . ' as m')
                ->where('m.YearId', $yearId)
                ->where('m.Status', 'Closed')
                ->where('m.Total_Claim', '>', 0)
                ->where('m.Verified_Date', '!=', '0000-00-00')
                ->whereNotNull('m.Verified_Date')
                ->where('m.Verified_Date', '!=', '1970-01-01');

            $courierStatus = $request->input('courier_status', 'all');
            if ($courierStatus === 'received') {
                $query->where('m.RecevingDate', '!=', '0000-00-00')->whereNotNull('m.RecevingDate');
            } elseif ($courierStatus === 'not_received') {
                $query->where(function ($q) {
                    $q->where('m.RecevingDate', '0000-00-00')->orWhereNull('m.RecevingDate');
                });
            } elseif ($courierStatus === 'dms_received') {
                $query
                    ->whereNotNull('m.courier_date')
                    ->where('m.courier_date', '!=', '0000-00-00')
                    ->where('m.courier_date', '!=', '1970-01-01');
            }
            if ($request->filled('department')) {
                $hrimsDb = \App\Helpers\DatabaseSwitcher::getHrimsDatabaseName();
                $query->leftJoin($hrimsDb . '.hrm_employee_general as g', 'm.EmployeeID', '=', 'g.EmployeeID')
                      ->where('g.DepartmentId', $request->department);
            }
            if ($request->filled('employee')) {
                $query->where('m.EmployeeID', $request->employee);
            }

            return $query->select(DB::raw('DISTINCT m.Month as month_value'))
                ->whereNotNull('m.Month')
                ->orderBy('m.Month')
                ->get()
                ->map(function ($item) {
                    return [
                        'value' => (int) $item->month_value,
                        'text'  => Carbon::createFromDate(null, (int) $item->month_value, 1)->format('F'),
                    ];
                });
        });
    }

    protected function getApprovedClaimsAvailableEmployees(Request $request)
    {
        $yearId = session('year_id');
        $companyId = session('company_id', 1);
        $cacheKey = "approved_emps_{$companyId}_{$yearId}_" . md5(serialize($request->only(['courier_status', 'month', 'department'])));

        return cache()->remember($cacheKey, 120, function () use ($request, $yearId) {
            $table = "y{$yearId}_monthexpensefinal";
            $query = DB::connection(session('expense_connection'))
                ->table($table . ' as m')
                ->where('m.YearId', $yearId)
                ->where('m.Status', 'Closed')
                ->where('m.Total_Claim', '>', 0)
                ->where('m.Verified_Date', '!=', '0000-00-00')
                ->whereNotNull('m.Verified_Date')
                ->where('m.Verified_Date', '!=', '1970-01-01');

            $courierStatus = $request->input('courier_status', 'all');
            if ($courierStatus === 'received') {
                $query->where('m.RecevingDate', '!=', '0000-00-00')->whereNotNull('m.RecevingDate');
            } elseif ($courierStatus === 'not_received') {
                $query->where(function ($q) {
                    $q->where('m.RecevingDate', '0000-00-00')->orWhereNull('m.RecevingDate');
                });
            } elseif ($courierStatus === 'dms_received') {
                $query
                    ->whereNotNull('m.courier_date')
                    ->where('m.courier_date', '!=', '0000-00-00')
                    ->where('m.courier_date', '!=', '1970-01-01');
            }
            if ($request->filled('month')) {
                $query->where('m.Month', (int) $request->month);
            }
            if (session('company_id') == 11) {
                $query->where('m.Fin_PayDate', '!=', '0000-00-00')->whereNotNull('m.Fin_PayDate');
            }
            if ($request->filled('department')) {
                $hrimsDb = \App\Helpers\DatabaseSwitcher::getHrimsDatabaseName();
                $query->leftJoin($hrimsDb . '.hrm_employee_general as g', 'm.EmployeeID', '=', 'g.EmployeeID')
                      ->where('g.DepartmentId', $request->department);
            }

            $employeeIds = $query->select('m.EmployeeID')->distinct()->pluck('m.EmployeeID')->toArray();
            if (empty($employeeIds)) {
                return collect();
            }

            return DB::connection(session('hrims_connection'))
                ->table('hrm_employee')
                ->whereIn('EmployeeID', $employeeIds)
                ->select(['EmployeeID', 'EmpCode', DB::raw("CONCAT(EmpCode, ' - ', Fname, ' ', COALESCE(Sname, ''), ' ', Lname) as EmpName")])
                ->orderBy('EmpCode')
                ->get()
                ->map(function ($employee) {
                    return ['value' => $employee->EmployeeID, 'text' => $employee->EmpName];
                });
        });
    }

    protected function getApprovedClaimsAvailableDepartments(Request $request)
    {
        $yearId = session('year_id');
        $companyId = session('company_id', 1);
        $cacheKey = "approved_depts_{$companyId}_{$yearId}_" . md5(serialize($request->only(['courier_status', 'month', 'employee'])));

        return cache()->remember($cacheKey, 120, function () use ($request, $yearId) {
            $table = "y{$yearId}_monthexpensefinal";
            $hrimsDb = \App\Helpers\DatabaseSwitcher::getHrimsDatabaseName();
            $query = DB::connection(session('expense_connection'))
                ->table($table . ' as m')
                ->leftJoin($hrimsDb . '.hrm_employee_general as g', 'm.EmployeeID', '=', 'g.EmployeeID')
                ->leftJoin($hrimsDb . '.core_departments as dep', 'g.DepartmentId', '=', 'dep.id')
                ->where('m.YearId', $yearId)
                ->where('m.Status', 'Closed')
                ->where('m.Total_Claim', '>', 0)
                ->where('m.Verified_Date', '!=', '0000-00-00')
                ->whereNotNull('m.Verified_Date')
                ->where('m.Verified_Date', '!=', '1970-01-01');

            $courierStatus = $request->input('courier_status', 'all');
            if ($courierStatus === 'received') {
                $query->where('m.RecevingDate', '!=', '0000-00-00')->whereNotNull('m.RecevingDate');
            } elseif ($courierStatus === 'not_received') {
                $query->where(function ($q) {
                    $q->where('m.RecevingDate', '0000-00-00')->orWhereNull('m.RecevingDate');
                });
            } elseif ($courierStatus === 'dms_received') {
                $query
                    ->whereNotNull('m.courier_date')
                    ->where('m.courier_date', '!=', '0000-00-00')
                    ->where('m.courier_date', '!=', '1970-01-01');
            }
            if ($request->filled('month')) {
                $query->where('m.Month', (int) $request->month);
            }
            if ($request->filled('employee')) {
                $query->where('m.EmployeeID', $request->employee);
            }
            if (session('company_id') == 11) {
                $query->where('m.Fin_PayDate', '!=', '0000-00-00')->whereNotNull('m.Fin_PayDate');
            }

            return $query->select('dep.id', 'dep.department_name')
                ->whereNotNull('dep.id')
                ->whereNotNull('dep.department_name')
                ->distinct()
                ->orderBy('dep.department_name')
                ->get()
                ->map(function ($department) {
                    return ['value' => $department->id, 'text' => $department->department_name];
                });
        });
    }

    public function getCourierDetail(Request $request)
    {
        try {
            $employeeId = $request->empid;
            $month = $request->month;
            $yearId = session('year_id');
            $table = "y{$yearId}_monthexpensefinal";
            $conn = session('expense_connection') ?: \App\Helpers\DatabaseSwitcher::getExpenseConnection();
            $isVvnr = ((int) session('company_id') === 11);

            $selectFields = ['PostDate', 'DocateNo', 'Agency', 'RecevingDate', 'DocRmk'];

            if ($isVvnr) {
                if (!\Illuminate\Support\Facades\Schema::connection($conn)->hasColumn($table, 'courier_date')) {
                    try {
                        DB::connection($conn)->statement("SET SESSION sql_mode = ''");
                        DB::connection($conn)->statement("ALTER TABLE `{$table}` ADD `courier_date` DATE NULL DEFAULT NULL AFTER `RecevingDate`");
                    } catch (\Exception $ex) {
                        \Illuminate\Support\Facades\Log::warning("VerifierController auto-add courier_date notice: " . $ex->getMessage());
                    }
                }
                if (\Illuminate\Support\Facades\Schema::connection($conn)->hasColumn($table, 'courier_date')) {
                    $selectFields[] = 'courier_date';
                }
            }

            $courierData = DB::connection($conn)->table($table)
                ->where('EmployeeID', $employeeId)
                ->where('Month', $month)
                ->where('YearId', $yearId)
                ->select($selectFields)
                ->first();

            if (!$courierData) {
                return response()->json(['success' => false, 'message' => 'No courier data found']);
            }
            $isEditable = (empty($courierData->RecevingDate) || \App\Support\ZeroDate::none($courierData->RecevingDate));

            return response()->json(['success' => true, 'data' => $courierData, 'isEditable' => $isEditable]);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    public function updateCourierDetail(Request $request)
    {
        try {
            $isVvnr = ((int) session('company_id') === 11);
            $yearId = session('year_id');
            $table = "y{$yearId}_monthexpensefinal";

            // Tracker #20: courier date = actual dispatch date; receiving date not before it, not in the future.
            $rules = app(ClaimSubmissionValidator::class);
            $monthRow = DB::connection(session('expense_connection'))->table($table)
                ->where('EmployeeID', (int) $request->empid)->where('Month', (int) $request->month)->where('YearId', $yearId)
                ->first(); // select * : courier_date exists only where the 2026_10_30 migration ran
            $dateErrors = [];
            if ($isVvnr && $request->filled('courier_date')) {
                $dateErrors = $rules->courierDateForMonth($request->courier_date, (int) $request->empid, (int) $request->month, (int) $yearId, session('expense_connection'));
            }
            if (! $dateErrors && $request->filled('receiving_date')) {
                $sentOn = $request->filled('courier_date') ? $request->courier_date : ($monthRow->courier_date ?? $monthRow->PostDate ?? null);
                $dateErrors = $rules->receivingDate($request->receiving_date, $sentOn);
            }
            if ($dateErrors) {
                return response()->json(['success' => false, 'message' => $dateErrors[0]], 422);
            }

            if ($isVvnr) {
                // VVNR: supports Courier Date (DMS) and Receive Date for Verification (Finance)
                $request->validate([
                    'empid' => 'required|integer',
                    'month' => 'required|integer',
                    'receiving_date' => 'nullable|date',
                    'courier_date' => 'nullable|date',
                    'doc_rmk' => 'nullable|string'
                ]);

                $updateFields = [
                    'DocRmk' => $request->doc_rmk
                ];
                if ($request->has('receiving_date') && !empty($request->receiving_date)) {
                    $updateFields['RecevingDate'] = (ClaimSubmissionValidator::parseDate($request->receiving_date) ?? $request->receiving_date);
                }
                if ($request->has('courier_date') && !$this->hasCourierDateColumn($table, session('expense_connection'))) {
                    return response()->json(['success' => false, 'message' => 'Courier Date is not set up on this database yet (run migration 2026_11_05_000011).'], 422);
                }
                if ($request->has('courier_date')) {
                    $updateFields['courier_date'] = $request->courier_date ? (ClaimSubmissionValidator::parseDate($request->courier_date) ?? $request->courier_date) : null;
                }
                $updated = DB::connection(session('expense_connection'))->table($table)
                    ->where('EmployeeID', $request->empid)
                    ->where('Month', $request->month)
                    ->where('YearId', $yearId)
                    ->update($updateFields);
            } else {
                // VNR: exactly as previous (Receiving Date is required)
                $request->validate([
                    'empid' => 'required|integer',
                    'month' => 'required|integer',
                    'receiving_date' => 'required|date',
                    'doc_rmk' => 'nullable|string'
                ]);

                $updated = DB::connection(session('expense_connection'))->table($table)
                    ->where('EmployeeID', $request->empid)
                    ->where('Month', $request->month)
                    ->where('YearId', $yearId)
                    ->update([
                        'RecevingDate' => (ClaimSubmissionValidator::parseDate($request->receiving_date) ?? $request->receiving_date),
                        'DocRmk' => $request->doc_rmk
                    ]);
            }

            if ($updated !== false) {
                return response()->json(['success' => true, 'message' => 'Courier details updated successfully']);
            }

            return response()->json(['success' => false, 'message' => 'Failed to update courier details']);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    public function returnClaim(Request $request)
    {
        try {
            $request->validate(['empid' => 'required|integer', 'month' => 'required|integer']);
            $yearId = session('year_id');
            $reason = $request->input('reason', '');
            $tableName = "y{$yearId}_monthexpensefinal";

            // Capture current status before update
            $current = DB::connection(session('expense_connection'))
                ->table($tableName)
                ->where('EmployeeID', $request->empid)
                ->where('Month', $request->month)
                ->where('YearId', $yearId)
                ->value('Status');

            $updated = DB::connection(session('expense_connection'))
                ->table($tableName)
                ->where('EmployeeID', $request->empid)
                ->where('Month', $request->month)
                ->where('YearId', $yearId)
                ->update(['Status' => 'Open', 'Verified_Amount' => 0, 'Verified_Date' => null]);

            if ($updated) {
                \App\Services\Advance\AutoAdvanceOnReopen::handle((int) $request->empid, (int) $request->month, (int) $yearId, (string) $reason); // ADV-11
                ClaimActionLogService::logMonthReturn(
                    (int) $request->empid,
                    (int) $request->month,
                    $current ?? 'Submitted',
                    'Open',
                    $reason,
                    \App\Models\ClaimActionLog::ROLE_VERIFIER
                );
                return response()->json(['success' => true, 'message' => 'Claim returned successfully']);
            }

            return response()->json(['success' => false, 'message' => 'Failed to return claim']);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    public function revertClaim(Request $request)
    {
        try {
            if (!auth()->user()->can('verifier_revert')) {
                return response()->json(['success' => false, 'message' => 'You do not have permission to revert claims.'], 403);
            }

            $request->validate([
                'empid' => 'required|integer',
                'month' => 'required|integer',
                'reason' => 'required|string',
            ]);

            $yearId = session('year_id');
            $empId = $request->empid;
            $month = $request->month;
            $reason = trim($request->input('reason', ''));
            $tableName = "y{$yearId}_monthexpensefinal";

            $currentRow = DB::connection(session('expense_connection'))
                ->table($tableName)
                ->where('EmployeeID', $empId)
                ->where('Month', $month)
                ->where('YearId', $yearId)
                ->first();

            if (!$currentRow) {
                return response()->json(['success' => false, 'message' => 'Monthly expense record not found']);
            }

            $companyId = (int) ($request->input('company_id') ?? session('company_id', 1));
            $isCompany11 = ($companyId === 11);

            // In company 1 (flow: claim -> verify -> approve -> finance),
            // prevent reverting if Approver has already approved
            if (!$isCompany11) {
                $hasValidApprDate = !empty($currentRow->Approved_Date)
                    && \App\Support\ZeroDate::has($currentRow->Approved_Date)
                    && $currentRow->Approved_Date !== '1970-01-01';
                $hasApprAmt = floatval($currentRow->Approved_Amount ?? 0) > 0;

                if ($hasValidApprDate && $hasApprAmt) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Cannot revert claim because it has already been approved by the Approver.'
                    ], 422);
                }
            }

            $updateData = [
                'Status' => 'Closed',
                'Verified_Amount' => 0,
                'Verified_Date' => '0000-00-00',
            ];

            if ($isCompany11) {
                $updateData['DocId'] = null;
                $updateData['DocNo'] = null;
            }

            $updated = DB::connection(session('expense_connection'))
                ->table($tableName)
                ->where('EmployeeID', $empId)
                ->where('Month', $month)
                ->where('YearId', $yearId)
                ->update($updateData);

            if ($updated) {
                $expTable = "y{$yearId}_expenseclaims";
                DB::connection(session('expense_connection'))
                    ->table($expTable)
                    ->where('CrBy', $empId)
                    ->where('ClaimMonth', $month)
                    ->where('ClaimYearId', $yearId)
                    ->whereNotIn('ClaimStatus', ['Deactivate', 'Draft'])
                    ->update([
                        'ClaimAtStep' => 3,
                    ]);

                ClaimActionLogService::logMonthRevert(
                    (int) $empId,
                    (int) $month,
                    'Approved',
                    'Pending',
                    $reason,
                    \App\Models\ClaimActionLog::ROLE_VERIFIER
                );

                return response()->json(['success' => true, 'message' => 'Claim reverted to pending successfully']);
            }

            return response()->json(['success' => false, 'message' => 'Failed to revert claim']);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    public function submitToApprover(Request $request)
    {
        try {
            $request->validate(['empid' => 'required|integer', 'month' => 'required|integer']);
            $empId = $request->empid;
            $month = $request->month;
            $yearId = (int) ($request->input('year_id') ?? $request->input('yearid') ?? \App\Support\CurrentYear::id());
            $sessionEmpId = session('employee_id');
            $verifierId = (is_numeric($sessionEmpId) && (int)$sessionEmpId > 0)
                ? (int)$sessionEmpId
                : (int)(auth()->id() ?? 0);
            $hasPendingClaims = $this->checkPendingClaims($empId, $month, $yearId);
            if ($hasPendingClaims) {
                return response()->json(['success' => false, 'message' => 'Employee has pending claims that need to be verified first']);
            }
            DB::connection(session('expense_connection'))->transaction(function () use ($empId, $month, $yearId, $verifierId) {
                $this->updateClaimsToSubmitted($empId, $month, $yearId, $verifierId);
                $this->updateMonthExpenseFinal($empId, $month, $yearId);
                if (session('company_id') != 11) {
                    $this->updateClaimDetails($empId, $month, $yearId);
                }
            });

            if (session('company_id') != 11) {
                $this->sendApproverNotification($empId, $month);
            }

            ClaimActionLogService::logSubmit(
                (int) $empId,
                (int) $month,
                'Verified',
                'Approved',
                \App\Models\ClaimActionLog::ROLE_VERIFIER
            );

            return response()->json(['success' => true, 'message' => 'Claim submitted to approver successfully']);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    private function checkPendingClaims($empId, $month, $yearId)
    {
        $expTable = "y{$yearId}_expenseclaims";
        $employee = DB::connection(session('hrims_connection'))->table('hrm_employee_general')->where('EmployeeID', $empId)->select('DepartmentId', 'EmpVertical')->first();
        $deptId = $employee->DepartmentId ?? 0;
        $empVertical = $employee->EmpVertical ?? 0;
        $odometerCondition = '';
        if (in_array($deptId, [2, 3]) && in_array($empVertical, [1, 2])) {
            $expIds = $this->getExcludedExpIds($empId, $month, $yearId, $expTable);
            if (!empty($expIds)) {
                $odometerCondition = ' AND ExpId NOT IN (' . implode(',', $expIds) . ')';
            }
        }
        $pendingCount = DB::connection(session('expense_connection'))->table($expTable)->where('ClaimYearId', $yearId)->where('CrBy', $empId)->where('ClaimMonth', $month)->where('FilledOkay', 1)->where('ClaimStatus', 'Filled')->whereRaw("1=1 {$odometerCondition}")->count();

        return $pendingCount > 0;
    }

    private function getExcludedExpIds($empId, $month, $yearId, $expTable)
    {
        $claims = DB::connection(session('expense_connection'))->table($expTable)->where('CrBy', $empId)->where('ClaimMonth', $month)->where('ClaimId', 7)->whereIn('WType', [2, 4])->whereNotIn('ClaimStatus', ['Draft', 'Deactivate'])->select('ExpId', 'TotKm', 'App_Odometer')->orderBy('ExpId', 'ASC')->get();
        $cumulativeKm = 0;
        $thresholdCrossed = false;
        $excludedIds = [];
        foreach ($claims as $claim) {
            $cumulativeKm += (int) $claim->TotKm;
            if ($cumulativeKm > 1200) {
                $thresholdCrossed = true;
            }
            if ($thresholdCrossed && in_array($claim->App_Odometer, ['N', 'R'])) {
                $excludedIds[] = $claim->ExpId;
            }
        }

        return $excludedIds;
    }

    private function updateClaimsToSubmitted($empId, $month, $yearId, $verifierId)
    {
        $expTable = "y{$yearId}_expenseclaims";
        DB::connection(session('expense_connection'))->table($expTable)->where('CrBy', $empId)->where('ClaimMonth', $month)->where('ClaimYearId', $yearId)->where('ClaimStatus', 'Verified')->update(['ClaimAtStep' => 4, 'VerifyBy' => $verifierId]);
    }

    private function updateMonthExpenseFinal($empId, $month, $yearId)
    {
        $expTable = "y{$yearId}_expenseclaims";

        $verifiedData = DB::connection(session('expense_connection'))
            ->table($expTable)
            ->where('ClaimYearId', $yearId)
            ->where('CrBy', $empId)
            ->where('ClaimMonth', $month)
            ->where('FilledOkay', 1)
            ->whereNotIn('ClaimStatus', ['Deactivate', 'Draft'])
            ->where('VerifyBy', '>', 0)
            ->selectRaw('SUM(VerifyTAmt) as VerifyAmt, MAX(VerifyDate) as VerifyD')
            ->first();

        $verifiedAmount = $verifiedData->VerifyAmt ?? 0;


        $monthExpense = new MonthExpenseFinal;
        $monthExpense->setConnection(session('expense_connection'));
        $monthExpense->setTable(MonthExpenseFinal::tableName($yearId));

        // Update Verified Amount
        $updateVerified = $monthExpense
            ->newQuery()
            ->where('EmployeeID', $empId)
            ->where('Month', $month)
            ->where('YearId', $yearId)
            ->update([
                'Verified_Amount' => $verifiedAmount,
                'Verified_Date' => now()->format('Y-m-d'),
            ]);

     

        // Update DocId & DocNo
        if (session('company_id') == 11) {
            $currentRow = $monthExpense
                ->newQuery()
                ->where('EmployeeID', $empId)
                ->where('Month', $month)
                ->where('YearId', $yearId)
                ->first();

            if (!empty($currentRow)) {
                $docId = $currentRow->DocId;
                $docNo = $currentRow->DocNo;

                if (empty($docId) || $docId == 0) {
                    $maxDocId = (int) $monthExpense->newQuery()->max('DocId');
                    $docId = $maxDocId + 1;
                    $docNo = str_pad($docId, 4, '0', STR_PAD_LEFT);
                } elseif (empty($docNo) || $docNo == '') {
                    $docNo = str_pad($docId, 4, '0', STR_PAD_LEFT);
                }

                if ($docId != $currentRow->DocId || $docNo != $currentRow->DocNo) {
                    $monthExpense
                        ->newQuery()
                        ->where('id', $currentRow->id)
                        ->update([
                            'DocId' => $docId,
                            'DocNo' => $docNo,
                        ]);
                }
            }
        }
    }

    private function updateClaimDetails($empId, $month, $yearId)
    {
        $expTable = "y{$yearId}_expenseclaims";
        $detailsTable = "y{$yearId}_expenseclaimsdetails";
        $expIds = DB::connection(session('expense_connection'))->table($expTable)->where('CrBy', $empId)->where('ClaimMonth', $month)->where('ClaimYearId', $yearId)->where('ClaimStatus', 'Verified')->pluck('ExpId');
        foreach ($expIds as $expId) {
            DB::connection(session('expense_connection'))->table($detailsTable)->where('ExpId', $expId)->update(['ApproverEditAmount' => DB::raw('VerifierEditAmount')]);
        }
    }

    private function sendApproverNotification($empId, $month)
    {
        try {
            $employee = DB::connection(session('hrims_connection'))->table('hrm_employee')->where('EmployeeID', $empId)->select('Fname', 'Sname', 'Lname')->first();
            $employeeName = trim(($employee->Fname ?? '') . ' ' . ($employee->Sname ?? '') . ' ' . ($employee->Lname ?? ''));
            $reporting = DB::connection(session('hrims_connection'))->table('hrm_employee_general')->where('EmployeeID', $empId)->first();
            if ($reporting && $reporting->RepEmployeeID > 0) {
                $approver = DB::connection(session('hrims_connection'))->table('hrm_employee_general')->where('EmployeeID', $reporting->RepEmployeeID)->first();
                if ($approver && $approver->EmailId_Vnr) {
                    $monthName = \Carbon\Carbon::createFromDate(null, $month, 1)->format('F');
                    $subject   = "Xeasy: Pending for approval (Month - {$monthName}, Emp - {$employeeName})";
                    $isSent    = false;

                    try {
                        \Mail::to($approver->EmailId_Vnr)->send(new \App\Mail\ClaimApprovalNotification($employeeName, $monthName));
                        $isSent = true;
                    } catch (\Exception $mailEx) {
                        app(\App\Services\EmailLogService::class)->logFailed([
                            'notification_type'     => 'claim_approval_notification',
                            'mail_category'         => 'expense',
                            'subject'               => $subject,
                            'recipient_employee_id' => $reporting->RepEmployeeID,
                            'recipient_email'       => $approver->EmailId_Vnr,
                            'ref_type'              => 'expense_claim',
                            'ref_id'                => $empId,
                            'sent_for_month'        => now()->year . '-' . str_pad($month, 2, '0', STR_PAD_LEFT),
                        ], $mailEx->getMessage());
                        return;
                    }

                    app(\App\Services\EmailLogService::class)->logSent([
                        'notification_type'     => 'claim_approval_notification',
                        'mail_category'         => 'expense',
                        'subject'               => $subject,
                        'recipient_employee_id' => $reporting->RepEmployeeID,
                        'recipient_email'       => $approver->EmailId_Vnr,
                        'ref_type'              => 'expense_claim',
                        'ref_id'                => $empId,
                        'sent_for_month'        => now()->year . '-' . str_pad($month, 2, '0', STR_PAD_LEFT),
                    ]);
                }
            }
        } catch (\Exception $e) {
        }
    }

    public function getEmployeeClaimsAjax(Request $request)
    {
        try {
            $empId = $request->empid;
            $month = $request->month;
            $yearId = session('year_id');
            $companyId = session('company_id');
            $expTable = "y{$yearId}_expenseclaims";
            $expDetailsTable = "y{$yearId}_g1_expensefilldata";
            $hrimsDb = \App\Helpers\DatabaseSwitcher::getHrimsDatabaseName();
            $employee = DB::table($hrimsDb . '.hrm_employee as e')
                ->join($hrimsDb . '.hrm_employee_general as g', 'e.EmployeeID', '=', 'g.EmployeeID')
                ->leftJoin($hrimsDb . '.core_departments as d', 'g.DepartmentId', '=', 'd.id')
                ->leftJoin($hrimsDb . '.core_designation as des', 'g.DesigId', '=', 'des.id')
                ->leftJoin($hrimsDb . '.core_grades as gr', 'g.GradeId', '=', 'gr.id')
                ->where('e.EmployeeID', $empId)
                ->first([
                    'e.EmployeeID',
                    'e.EmpCode',
                    DB::raw("CONCAT(e.Fname, ' ', COALESCE(e.Sname, ''), ' ', e.Lname) AS Name"),
                    'e.EmpStatus',
                    'e.DateOfResignation',
                    'e.DateOfSepration',
                    'd.department_name',
                    'des.designation_name',
                    'gr.grade_name'
                ]);
            if (!$employee) {
                return response()->json(['success' => false, 'message' => 'Employee not found']);
            }
            $blankDates = ['', '0000-00-00', '1970-01-01', '1971-01-01'];
            $actualStatus = $this->resolveEmployeeStatus($employee);
            $employee->EmpStatus = $actualStatus;
            $employee->status_label = ($actualStatus === 'R') ? 'Resigned' : (($actualStatus === 'D') ? 'Deactive' : 'Active');

            $employee->formatted_resignation_date = (!empty($employee->DateOfResignation) && !in_array($employee->DateOfResignation, $blankDates) && strtotime($employee->DateOfResignation) > strtotime('2000-01-01'))
                ? Carbon::parse($employee->DateOfResignation)->format('d-m-Y')
                : '';
            $employee->formatted_separation_date = (!empty($employee->DateOfSepration) && !in_array($employee->DateOfSepration, $blankDates) && strtotime($employee->DateOfSepration) > strtotime('2000-01-01'))
                ? Carbon::parse($employee->DateOfSepration)->format('d-m-Y')
                : '';

            if (empty($employee->formatted_resignation_date) && empty($employee->formatted_separation_date)) {
                try {
                    $separation = DB::connection('hrims')->selectOne("
                        SELECT Emp_RelievingDate, NoticeDate, ResignationDate
                        FROM hrm_employee_separation
                        WHERE EmployeeID = ?
                          AND Rep_Approved != 'C'
                          AND Hod_Approved != 'C'
                          AND HR_Approved != 'C'
                        LIMIT 1
                    ", [$empId]);
                    if ($separation) {
                        if (!empty($separation->ResignationDate) && !in_array($separation->ResignationDate, $blankDates) && strtotime($separation->ResignationDate) > strtotime('2000-01-01')) {
                            $employee->formatted_resignation_date = Carbon::parse($separation->ResignationDate)->format('d-m-Y');
                        }
                        if (!empty($separation->Emp_RelievingDate) && !in_array($separation->Emp_RelievingDate, $blankDates) && strtotime($separation->Emp_RelievingDate) > strtotime('2000-01-01')) {
                            $employee->formatted_separation_date = Carbon::parse($separation->Emp_RelievingDate)->format('d-m-Y');
                        }
                    }
                } catch (\Throwable $e) {
                }
            }
            $deptData = DB::table($hrimsDb . '.hrm_employee_general')->where('EmployeeID', $empId)->first(['DepartmentId', 'EmpVertical']);
            $deptId = $deptData->DepartmentId ?? 0;
            $empVertical = $deptData->EmpVertical ?? 0;
            $appOdometerCond = '';
            if (in_array($deptId, [2, 3])) {
                $appOdometerCond = " AND ((e.ClaimId = 7 AND e.App_Odometer NOT IN ('N','R')) OR (e.ClaimId != 7))";
            }
            // GST-01: GSTIN columns exist once migration 000009 has run
            $gstCols = \Illuminate\Support\Facades\Schema::connection(session('expense_connection'))->hasColumn($expDetailsTable, 'GSTIN')
                ? ', ed.GSTIN, ed.GSTIN_Status' : ", NULL AS GSTIN, NULL AS GSTIN_Status";
            // TATKAL-02: rail booking/journey dates and category for the Tatkal check
            $gstCols .= ', ed.Quota AS RailQuota, ed.BookingDate AS RailBookingDate, ed.JourneyStartDt AS RailJourneyDate';
            $claims = DB::connection(session('expense_connection'))->select("SELECT e.*, c.ClaimName, c.cgid,ed.CityCategory,ed.StayDuration,ed.Arrival,ed.Departure{$gstCols}
                      FROM `{$expTable}` e
                      INNER JOIN claimtype c ON c.ClaimId = e.ClaimId
                      LEFT JOIN `{$expDetailsTable}` ed  ON ed.ExpId = e.ExpId
                      WHERE e.ClaimMonth = ?
                      AND e.ClaimYearId = ?
                      AND e.CrBy = ?
                      AND e.FilledOkay = 1
                      AND e.ClaimStatus NOT IN ('Deactivate', 'Draft')
                      AND e.ClaimAtStep >= 3
                      {$appOdometerCond}
                  
                      ORDER BY e.BillDate ASC", [$month, $yearId, $empId]);

            // Deduplicate by ExpId — the LEFT JOIN on expensefilldata can produce
            // multiple rows for the same claim when there are multiple detail records.
            $seenExpIds = [];
            $uniqueClaims = [];
            foreach ($claims as $claim) {
                if (in_array($claim->ExpId, $seenExpIds)) {
                    continue;
                }
                $seenExpIds[] = $claim->ExpId;
                $uniqueClaims[] = $claim;
            }
            $claims = $uniqueClaims;

            $processedClaims = [];
            foreach ($claims as $claim) {
                $claim->file_preview = $this->getFilePreview($claim->ExpId, $yearId, $empId);
                $claim->with_driver = $this->getWithDriverBadge($claim);
                $claim->km_details = $this->getKmDetails($claim, $yearId);
                $claim->emp_att_status = getUserAttStatus($empId, $claim->BillDate);
                $claim->emp_elig = getUserExpEligibility($empId);
                $claim->stay_details = ($empId);
                $processedClaims[] = $claim;
            }
            $totals = $this->calculateClaimTotals($empId, $month, $yearId);
            $isVVNR = ((int) session('company_id') === 11);

            return response()->json([
                'success' => true,
                'data' => $processedClaims,
                'employee' => $employee,
                'totals' => $totals,
                'monthName' => \Carbon\Carbon::createFromDate(null, $month, 1)->format('F Y'),
                'is_vvnr' => $isVVNR,
            ]);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    public function getSingleClaim(Request $request)
    {
        try {
            $request->validate(['empid' => 'required|integer', 'month' => 'required|integer', 'expid' => 'required|integer']);
            $empId = $request->empid;
            $month = $request->month;
            $expId = $request->expid;
            $yearId = session('year_id');
            $expTable = "y{$yearId}_expenseclaims";
            $claim = DB::connection(session('expense_connection'))->table("{$expTable} as e")->select('e.*', 'c.ClaimName', 'c.cgid')->join('claimtype as c', 'c.ClaimId', '=', 'e.ClaimId')->where('e.ExpId', $expId)->where('e.ClaimMonth', $month)->where('e.ClaimYearId', $yearId)->where('e.CrBy', $empId)->where('e.FilledOkay', 1)->whereNotIn('e.ClaimStatus', ['Deactivate', 'Draft'])->where('e.ClaimAtStep', '>=', 3)->first();
            if (!$claim) {
                return response()->json(['success' => false, 'message' => 'Claim not found']);
            }
            $claim->file_preview = $this->getFilePreview($claim->ExpId, $yearId, $empId);
            $claim->with_driver = $this->getWithDriverBadge($claim);
            $claim->km_details = $this->getKmDetails($claim, $yearId);

            return response()->json(['success' => true, 'claim' => $claim]);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    private function getFilePreview($expId, $yearId, $empId)
    {
        $upload = DB::connection(session('expense_connection'))->table("y{$yearId}_claimuploads")->where('ExpId', $expId)->first(['FileName']);
        if (!$upload || empty($upload->FileName)) {
            return '<span class="text-muted"></span>';
        }
        $fileName = $upload->FileName;
        $basePath = $this->getExpenseBasePath();
        $backupFolder = $this->getBackupFolder();
        $path = "{$basePath}/{$yearId}/{$empId}/{$fileName}";
        $url = $this->getS3Url($path);
        if (!$url) {
            $url = "https://vnragro.com/File_Bkp/{$backupFolder}/{$yearId}/{$empId}/" . rawurlencode($fileName);
        }
        $ext = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));
        if (in_array($ext, ['jpg', 'jpeg', 'png', 'gif', 'bmp', 'webp'])) {
            return '<img style="width:150px;height:80px;" src="' . htmlspecialchars($url) . '" class="rounded-sm zoom-verify-img" onclick="viewFullImage(\'' . htmlspecialchars($url) . '\')">';
        } elseif (in_array($ext, ['pdf', 'doc', 'docx'])) {
            return '<a href="' . htmlspecialchars($url) . '" target="_blank" class="btn btn-sm btn-primary"><i class="ri-file-line"></i></a>';
        }

        return '<a href="' . htmlspecialchars($url) . '" target="_blank">File</a>';
    }

    private function getWithDriverBadge($claim)
    {
        if ($claim->ClaimId != 7) {
            return '';
        }
        if ($claim->With_Driver === 'Y') {
            return '<span class="badge bg-success">Yes</span>';
        } elseif ($claim->With_Driver === 'N') {
            return '<span class="badge bg-danger">No</span>';
        }

        return '';
    }

    private function getKmDetails($claim, $yearId)
    {
        if ($claim->ClaimId != 7) {
            return '';
        }
        $wheeler = DB::connection(session('expense_connection'))->table("y{$yearId}_24_wheeler_entry")->where('ExpId', $claim->ExpId)->first(['DistTraOpen', 'DistTraClose', 'Totalkm']);
        if ($wheeler) {
            return "<small><b>Op:</b> {$wheeler->DistTraOpen}<br><b>Cl:</b> {$wheeler->DistTraClose}<br><b>Total Km:</b> {$wheeler->Totalkm}</small>";
        }

        return '';
    }

    public function verifySingleClaim(Request $request)
    {
        try {
            $request->validate(['expId' => 'required|integer', 'verifyAmt' => 'nullable|numeric|min:0', 'verifyRemark' => 'nullable|string']);
            $yearId = session('year_id');
            $sessionEmpId = session('employee_id');
            $verifierId = (is_numeric($sessionEmpId) && (int)$sessionEmpId > 0)
                ? (int)$sessionEmpId
                : (int)(auth()->id() ?? 0);
            $expId = $request->expId;
            $verifyAmt = $request->verifyAmt ?? 0;
            $verifyRemark = $request->verifyRemark ?? '';
            $verifyDate = now()->format('Y-m-d');
            $expTable = "y{$yearId}_expenseclaims";
            $fillDataTable = "y{$yearId}_g7_expensefilldata";
            $detailsTable = "y{$yearId}_expenseclaimsdetails";
            // B-01: verifier may verify this claim from its current status (checked and logged)
            \App\Services\Claims\ClaimTransition::guard(session('expense_connection'), (int) $yearId, (int) $expId, 'Verified', 'verifier', 'verify_single');
            $claimInfo = DB::connection(session('expense_connection'))->table($expTable)->leftJoin('claimtype', 'claimtype.ClaimId', '=', "{$expTable}.ClaimId")->where("{$expTable}.ExpId", $expId)->select("{$expTable}.ExpId", "{$expTable}.CrBy", "{$expTable}.ClaimId", "{$expTable}.FilledTAmt", 'claimtype.ClaimName', 'claimtype.cgId')->first();
            // Q-02: verified above claimed needs a reason
            if ($claimInfo && ($capMsg = \App\Services\Claims\StageCap::check(session('expense_connection'), (int) $yearId, $claimInfo, $verifyAmt, 'verify', $verifyRemark, 'verify_single'))) {
                return response()->json(['success' => false, 'message' => $capMsg, 'remark_required' => true], 422);
            }
            // LODGING-VERIFY-01: lodging limit by city tier is checked at verification
            if ($claimInfo && ($eligMsg = app(\App\Services\ClaimRules\EligibilityCaps::class)->lodgingAtVerify(session('expense_connection'), (int) $yearId, (int) $expId, (int) $claimInfo->CrBy, (int) $claimInfo->ClaimId, (float) $verifyAmt, $verifyRemark))) {
                return response()->json(['success' => false, 'message' => $eligMsg, 'remark_required' => true], 422);
            }
            if (!$claimInfo) {
                $this->handleSimpleVerification($expId, $verifyAmt, $verifyRemark, $verifyDate, $detailsTable, $expTable, $verifierId, null, 0);

                return response()->json(['success' => true, 'message' => 'Claim verified successfully']);
            }
            $claimId = $claimInfo->ClaimId;
            $claimName = $claimInfo->ClaimName ?? 'General Expense';
            $filledAmount = $claimInfo->FilledTAmt ?? 0;
            $cgId = $claimInfo->cgId;
            $isCgId7 = ($cgId == 7);
            if (!$isCgId7 || !$claimId) {
                $this->handleSimpleVerification($expId, $verifyAmt, $verifyRemark, $verifyDate, $detailsTable, $expTable, $verifierId, $claimName, $filledAmount);

                return response()->json(['success' => true, 'message' => 'Claim verified successfully']);
            }
            // Get activity fields locally instead of external API (handles deactivated claim types too)
            $apiResponse = null;
            try {
                $activityRow = DB::connection(session('expense_connection'))->selectOne(
                    "SELECT id as activity_name_id FROM adv_activity_names WHERE activity_name = ?", [$claimId]
                );
                $activityId = $activityRow ? $activityRow->activity_name_id : 0;
                if ($activityId > 0) {
                    $fields = DB::connection(session('expense_connection'))->select(
                        "SELECT adv_expense_head_category.expense_head_name, adv_expense_head_category.short_code
                         FROM adv_expense_head_name_mapping
                         LEFT JOIN adv_expense_head_category ON adv_expense_head_name_mapping.expense_head_id = adv_expense_head_category.id
                         WHERE activity_id = ? AND adv_expense_head_name_mapping.checked = 1
                         ORDER BY adv_expense_head_category.expense_head_name ASC", [$activityId]
                    );
                    $expenses = [];
                    foreach ($fields as $row) {
                        $label = $row->expense_head_name;
                        $value = preg_replace("/[^A-Za-z0-9 ]/", "", $label);
                        $value = preg_replace("/\s+/", "_", trim($value));
                        $value = preg_replace("/__+/", "_", $value);
                        $value = strtolower($value);
                        $expenses[] = ['label' => $label, 'value' => $value];
                    }
                    if (!empty($expenses)) {
                        $apiResponse = ['data' => ['expenses' => $expenses]];
                    }
                }
            } catch (\Exception $e) {
                \Log::warning('Local getAdditionalFields failed: ' . $e->getMessage());
                $apiResponse = null;
            }
            if (!$apiResponse) {
                $this->handleSimpleVerification($expId, $verifyAmt, $verifyRemark, $verifyDate, $detailsTable, $expTable, $verifierId, $claimName, $filledAmount);

                return response()->json(['success' => true, 'message' => 'Claim verified successfully']);
            }
            $valueLabelMap = [];
            $values = [];
            foreach ($apiResponse['data']['expenses'] as $item) {
                if (isset($item['value']) && isset($item['label'])) {
                    $valueLabelMap[$item['value']] = $item['label'];
                    $values[] = $item['value'];
                }
            }
            if (empty($values)) {
                $this->handleSimpleVerification($expId, $verifyAmt, $verifyRemark, $verifyDate, $detailsTable, $expTable, $verifierId, $claimName, $filledAmount);

                return response()->json(['success' => true, 'message' => 'Claim verified successfully']);
            }
            $normalFields = implode(',', $values);
            $verifierFields = implode(',', array_map(fn($v) => "ver_{$v}", $values));
            $allFields = $normalFields . ',' . $verifierFields;
            $fillData = DB::connection(session('expense_connection'))->table($fillDataTable)->where('ExpId', $expId)->selectRaw($allFields)->first();
            $isCompany11 = ((int) session('company_id') === 11);
            if ($fillData) {
                $existingApproverAmounts = [];
                if ($isCompany11) {
                    $existingApproverAmounts = DB::connection(session('expense_connection'))
                        ->table($detailsTable)
                        ->where('ExpId', $expId)
                        ->pluck('ApproverEditAmount', 'Title')
                        ->toArray();
                }

                DB::connection(session('expense_connection'))->table($detailsTable)->where('ExpId', $expId)->delete();
                $insertedRows = 0;
                foreach ($values as $fieldName) {
                    $amount = $fillData->{$fieldName} ?? 0;
                    $verifierAmount = $fillData->{"ver_{$fieldName}"} ?? 0;
                    if ($amount > 0 || $verifierAmount > 0) {
                        $originalLabel = $valueLabelMap[$fieldName] ?? $fieldName;
                        $insertData = [
                            'ExpId' => $expId,
                            'Title' => $originalLabel,
                            'Amount' => $amount,
                            'VerifierEditAmount' => $verifierAmount,
                            'VerifiedDate' => $verifyDate,
                            'VerifierRemark' => $verifyRemark
                        ];
                        if ($isCompany11 && isset($existingApproverAmounts[$originalLabel])) {
                            $insertData['ApproverEditAmount'] = $existingApproverAmounts[$originalLabel];
                        }
                        DB::connection(session('expense_connection'))->table($detailsTable)->insert($insertData);
                        $insertedRows++;
                    }
                }
                // When verifier zeroes an activity or all values are 0, still insert a detail record so the claim NEVER disappears (Point B.6)
                if ($insertedRows === 0 && !empty($values)) {
                    $firstField = $values[0];
                    $originalLabel = $valueLabelMap[$firstField] ?? $claimName ?? 'Expense';
                    $fallbackInsert = [
                        'ExpId' => $expId,
                        'Title' => $originalLabel,
                        'Amount' => (float)($fillData->{$firstField} ?? $filledAmount ?? 0),
                        'VerifierEditAmount' => (float)$verifyAmt,
                        'VerifiedDate' => $verifyDate,
                        'VerifierRemark' => $verifyRemark,
                    ];
                    if ($isCompany11 && isset($existingApproverAmounts[$originalLabel])) {
                        $fallbackInsert['ApproverEditAmount'] = $existingApproverAmounts[$originalLabel];
                    }
                    DB::connection(session('expense_connection'))->table($detailsTable)->insert($fallbackInsert);
                }
            } else {
                $this->handleSimpleVerification($expId, $verifyAmt, $verifyRemark, $verifyDate, $detailsTable, $expTable, $verifierId, $claimName, $filledAmount);

                return response()->json(['success' => true, 'message' => 'Claim verified successfully']);
            }

            $updateData = [
                'ClaimStatus' => 'Verified',
                'ClaimAtStep' => 4,
                'VerifyBy' => $verifierId,
                'VerifyTAmt' => $verifyAmt,
                'VerifyTRemark' => $verifyRemark,
                'VerifyDate' => $verifyDate,
            ];
            if (!$isCompany11) {
                $updateData['ApprTAmt'] = $verifyAmt;
                $updateData['FinancedTAmt'] = $verifyAmt;
            }
            DB::connection(session('expense_connection'))->table($expTable)->where('ExpId', $expId)->update($updateData);

            return response()->json(['success' => true, 'message' => 'Claim verified successfully']);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => 'Failed to verify claim: ' . $e->getMessage()], 500);
        }
    }

    public function verifyAllClaims(Request $request)
    {
        try {
            $request->validate([
                'empid' => 'required|integer',
                'month' => 'required|string',
                'claims' => 'nullable|array',  // Optional: pass specific claims to verify
            ]);

            $yearId = session('year_id');
            $sessionEmpId = session('employee_id');
            $verifierId = (is_numeric($sessionEmpId) && (int)$sessionEmpId > 0)
                ? (int)$sessionEmpId
                : (int)(auth()->id() ?? 0);
            $empid = $request->empid;
            $month = $request->month;

            // Get the expense table name
            $expTable = "y{$yearId}_expenseclaims";

            // Build query to get all pending claims for this employee and month
            $query = DB::connection(session('expense_connection'))
                ->table($expTable)
                ->where('CrBy', $empid)
                ->where('ClaimMonth', $month)
                ->where('ClaimStatus', '!=', 'Verified');

            // If specific claims are provided, filter by them
            if ($request->has('claims') && is_array($request->claims) && count($request->claims) > 0) {
                $query->whereIn('ExpId', $request->claims);
            }

            $pendingClaims = $query->get();

            if ($pendingClaims->isEmpty()) {
                return response()->json([
                    'success' => false,
                    'message' => 'No pending claims found to verify',
                ]);
            }

            $verifiedCount = 0;
            $failedCount = 0;
            $companyId = session('company_id');
            $isCompany11 = ((int) $companyId === 11);

            foreach ($pendingClaims as $claim) {
                try {
                    $verifyBaseAmt = ($isCompany11 && $claim->ApprTAmt !== null && $claim->ApprTAmt !== '')
                        ? (float) $claim->ApprTAmt
                        : (float) ($claim->FilledTAmt ?? 0);

                    $result = $this->processSingleClaimVerification(
                        $claim->ExpId,
                        $verifyBaseAmt,
                        '',  // Empty remark for auto-verify
                        $yearId,
                        $companyId,
                        $verifierId
                    );

                    if ($result) {
                        $verifiedCount++;
                    } else {
                        $failedCount++;
                        $failedClaims[] = $claim->ExpId;
                    }
                } catch (\Exception $e) {
                    $failedCount++;
                    $failedClaims[] = $claim->ExpId;
                    \Log::error("Failed to verify claim {$claim->ExpId}: " . $e->getMessage());
                }
            }

            $message = "Verified {$verifiedCount} out of {$pendingClaims->count()} claims";
            if ($failedCount > 0) {
                $message .= '. Failed claims: ' . implode(', ', $failedClaims);
            }

            return response()->json([
                'success' => $verifiedCount > 0,
                'message' => $message,
                'data' => [
                    'total' => $pendingClaims->count(),
                    'verified' => $verifiedCount,
                    'failed' => $failedCount,
                    'failed_claims' => $failedClaims,
                ],
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to verify claims: ' . $e->getMessage(),
            ], 500);
        }
    }

    protected function isAutoVerifyAuthorized(): bool
    {
        $empCode = (string) (
            session('emp_code') 
            ?? session('employee_details.EmpCode') 
            ?? auth()->user()?->employee?->EmpCode 
            ?? auth()->user()?->employee?->EmpCode_New 
            ?? auth()->user()?->employee_id 
            ?? ''
        );
        return $empCode === '1740' || auth()->user()?->employee_id == 2081 || auth()->user()?->employee_id == 1740;
    }

    /**
     * Preview automated verification results without modifying records.
     */
    public function autoVerifyPreview(Request $request, \App\Services\AutoVerificationService $autoVerifyService)
    {
        if (!$this->isAutoVerifyAuthorized()) {
            return response()->json(['success' => false, 'message' => 'Unauthorized. Feature restricted to employee code 1740.'], 403);
        }

        $yearId = (int) session('year_id', 8);
        $companyId = (int) session('company_id', 1);

        $filters = array_filter([
            'month' => $request->input('month'),
            'employee_id' => $request->input('employee_id'),
            'claim_id' => $request->input('claim_id'),
        ]);

        $summary = $autoVerifyService->batchVerifyPending($companyId, $yearId, $filters, true);

        return response()->json([
            'success' => true,
            'summary' => [
                'total_evaluated' => $summary['total_evaluated'] ?? 0,
                'approved' => $summary['approved'] ?? 0,
                'adjusted' => $summary['adjusted'] ?? 0,
                'flagged' => $summary['flagged'] ?? 0,
                'approved_amount' => $summary['approved_amount'] ?? 0,
                'adjusted_amount' => $summary['adjusted_amount'] ?? 0,
                'claims' => array_slice($summary['claims'] ?? [], 0, 50),
            ],
        ]);
    }

    /**
     * Execute automated verification on compliant pending claims in batch.
     */
    public function autoVerifyBatch(Request $request, \App\Services\AutoVerificationService $autoVerifyService)
    {
        if (!$this->isAutoVerifyAuthorized()) {
            return response()->json(['success' => false, 'message' => 'Unauthorized. Feature restricted to employee code 1740.'], 403);
        }
        $yearId = (int) session('year_id', 8);
        $companyId = (int) session('company_id', 1);

        $filters = array_filter([
            'month' => $request->input('month'),
            'employee_id' => $request->input('employee_id'),
            'claim_id' => $request->input('claim_id'),
        ]);

        $result = $autoVerifyService->batchVerifyPending($companyId, $yearId, $filters, false);

        // If specific employee and month passed, also attempt month voucher auto-submission
        $monthSubmitted = false;
        if ($request->filled('employee_id') && $request->filled('month')) {
            $monthRes = $autoVerifyService->verifyMonthVoucher(
                (int) $request->input('employee_id'),
                (int) $request->input('month'),
                $yearId,
                $companyId,
                false
            );
            $monthSubmitted = ($monthRes['status'] === 'submitted');
        }

        return response()->json([
            'success' => true,
            'message' => sprintf(
                "Auto-verified %d claims (₹%s approved, ₹%s policy-adjusted). %d claims flagged for review.",
                $result['approved'] + $result['adjusted'],
                number_format($result['approved_amount'], 2),
                number_format($result['adjusted_amount'], 2),
                $result['flagged']
            ),
            'data' => $result,
            'month_submitted' => $monthSubmitted,
        ]);
    }

    /**
     * Process a single claim verification (reusable method)
     */
    private function processSingleClaimVerification($expId, $verifyAmt, $verifyRemark, $yearId, $companyId, $verifierId)
    {
        $verifyDate = now()->format('Y-m-d');
        $expTable = "y{$yearId}_expenseclaims";
        \App\Services\Claims\ClaimTransition::guard(session('expense_connection'), (int) $yearId, (int) $expId, 'Verified', 'verifier', 'verify_bulk', null, 'bulk');
        $fillDataTable = "y{$yearId}_g7_expensefilldata";
        $detailsTable = "y{$yearId}_expenseclaimsdetails";

        $claimInfo = DB::connection(session('expense_connection'))
            ->table($expTable)
            ->leftJoin('claimtype', 'claimtype.ClaimId', '=', "{$expTable}.ClaimId")
            ->where("{$expTable}.ExpId", $expId)
            ->select("{$expTable}.ExpId", "{$expTable}.CrBy", "{$expTable}.ClaimId", "{$expTable}.FilledTAmt", 'claimtype.ClaimName', 'claimtype.cgId')
            ->first();

        // Q-02: bulk verify carries no remark, so it may not verify above the claimed amount
        if ($claimInfo && ($capMsg = \App\Services\Claims\StageCap::check(session('expense_connection'), (int) $yearId, $claimInfo, $verifyAmt, 'verify', $verifyRemark, 'verify_bulk'))) {
            throw new \RuntimeException($capMsg);
        }
        // LODGING-VERIFY-01: bulk verify has no remark, so lodging above the city-tier limit is left for single verification
        if ($claimInfo && ($eligMsg = app(\App\Services\ClaimRules\EligibilityCaps::class)->lodgingAtVerify(session('expense_connection'), (int) $yearId, (int) $expId, (int) $claimInfo->CrBy, (int) $claimInfo->ClaimId, (float) $verifyAmt, $verifyRemark))) {
            throw new \RuntimeException('Claim #'.$expId.': '.$eligMsg);
        }

        if (!$claimInfo) {
            $this->handleSimpleVerification($expId, $verifyAmt, $verifyRemark, $verifyDate, $detailsTable, $expTable, $verifierId, null, 0);

            return true;
        }

        $claimId = $claimInfo->ClaimId;
        $claimName = $claimInfo->ClaimName ?? 'General Expense';
        $filledAmount = $claimInfo->FilledTAmt ?? 0;
        $cgId = $claimInfo->cgId;
        $isCgId7 = ($cgId == 7);

        if (!$isCgId7 || !$claimId) {
            $this->handleSimpleVerification($expId, $verifyAmt, $verifyRemark, $verifyDate, $detailsTable, $expTable, $verifierId, $claimName, $filledAmount);

            return true;
        }

        $tokenId = config('app.api_token_id'); // hard-coded device token removed (13 Nov 2026); not used below
        // Get activity fields locally (handles deactivated claim types too)
        $apiResponse = null;
        try {
            $activityRow = DB::connection(session('expense_connection'))->selectOne(
                "SELECT id as activity_name_id FROM adv_activity_names WHERE activity_name = ?", [$claimId]
            );
            $activityId = $activityRow ? $activityRow->activity_name_id : 0;
            if ($activityId > 0) {
                $fields = DB::connection(session('expense_connection'))->select(
                    "SELECT adv_expense_head_category.expense_head_name
                     FROM adv_expense_head_name_mapping
                     LEFT JOIN adv_expense_head_category ON adv_expense_head_name_mapping.expense_head_id = adv_expense_head_category.id
                     WHERE activity_id = ? AND adv_expense_head_name_mapping.checked = 1
                     ORDER BY adv_expense_head_category.expense_head_name ASC", [$activityId]
                );
                $expenses = [];
                foreach ($fields as $row) {
                    $label = $row->expense_head_name;
                    $value = preg_replace("/[^A-Za-z0-9 ]/", "", $label);
                    $value = preg_replace("/\s+/", "_", trim($value));
                    $value = preg_replace("/__+/", "_", $value);
                    $value = strtolower($value);
                    $expenses[] = ['label' => $label, 'value' => $value];
                }
                if (!empty($expenses)) {
                    $apiResponse = ['data' => ['expenses' => $expenses]];
                }
            }
        } catch (\Exception $e) {
            \Log::warning('Local getAdditionalFields failed in processSingleClaimVerification: ' . $e->getMessage());
            $apiResponse = null;
        }

        if (!$apiResponse) {
            $this->handleSimpleVerification($expId, $verifyAmt, $verifyRemark, $verifyDate, $detailsTable, $expTable, $verifierId, $claimName, $filledAmount);

            return true;
        }

        $valueLabelMap = [];
        $values = [];
        foreach ($apiResponse['data']['expenses'] as $item) {
            if (isset($item['value']) && isset($item['label'])) {
                $valueLabelMap[$item['value']] = $item['label'];
                $values[] = $item['value'];
            }
        }

        if (empty($values)) {
            $this->handleSimpleVerification($expId, $verifyAmt, $verifyRemark, $verifyDate, $detailsTable, $expTable, $verifierId, $claimName, $filledAmount);

            return true;
        }

        $normalFields = implode(',', $values);
        $verifierFields = implode(',', array_map(fn($v) => "ver_{$v}", $values));
        $allFields = $normalFields . ',' . $verifierFields;

        $fillData = DB::connection(session('expense_connection'))
            ->table($fillDataTable)
            ->where('ExpId', $expId)
            ->selectRaw($allFields)
            ->first();

        $isCompany11 = ((int) session('company_id') === 11);
        if ($fillData) {
            $existingApproverAmounts = [];
            if ($isCompany11) {
                $existingApproverAmounts = DB::connection(session('expense_connection'))
                    ->table($detailsTable)
                    ->where('ExpId', $expId)
                    ->pluck('ApproverEditAmount', 'Title')
                    ->toArray();
            }

            DB::connection(session('expense_connection'))->table($detailsTable)->where('ExpId', $expId)->delete();

            foreach ($values as $fieldName) {
                $amount = $fillData->{$fieldName} ?? 0;
                $verifierAmount = $fillData->{"ver_{$fieldName}"} ?? 0;

                if ($amount > 0 || $verifierAmount > 0) {
                    $originalLabel = $valueLabelMap[$fieldName] ?? $fieldName;
                    $insertData = [
                        'ExpId' => $expId,
                        'Title' => $originalLabel,
                        'Amount' => $amount,
                        'VerifierEditAmount' => $verifierAmount,
                        'VerifiedDate' => $verifyDate,
                        'VerifierRemark' => $verifyRemark,
                    ];
                    if ($isCompany11 && isset($existingApproverAmounts[$originalLabel])) {
                        $insertData['ApproverEditAmount'] = $existingApproverAmounts[$originalLabel];
                    }
                    DB::connection(session('expense_connection'))->table($detailsTable)->insert($insertData);
                }
            }
        } else {
            $this->handleSimpleVerification($expId, $verifyAmt, $verifyRemark, $verifyDate, $detailsTable, $expTable, $verifierId, $claimName, $filledAmount);

            return true;
        }

        $updateData = [
            'ClaimStatus' => 'Verified',
            'ClaimAtStep' => 4,
            'VerifyBy' => $verifierId,
            'VerifyTAmt' => $verifyAmt,
            'VerifyTRemark' => $verifyRemark,
            'VerifyDate' => $verifyDate,
        ];
        if (!$isCompany11) {
            $updateData['ApprTAmt'] = $verifyAmt;
            $updateData['FinancedTAmt'] = $verifyAmt;
        }

        DB::connection(session('expense_connection'))
            ->table($expTable)
            ->where('ExpId', $expId)
            ->update($updateData);

        return true;
    }

    private function fetchAPIData($apiUrl)
    {
        try {
            $response = Http::timeout(10)->withOptions(['verify' => \App\Support\TlsVerify::option()])->get($apiUrl);
            if (!$response->successful()) {
                return null;
            }
            $apiResponse = $response->json();
            if (!isset($apiResponse['data']['expenses']) || !is_array($apiResponse['data']['expenses']) || empty($apiResponse['data']['expenses'])) {
                return null;
            }

            return $apiResponse;
        } catch (\Exception $e) {
            return null;
        }
    }

    private function handleSimpleVerification($expId, $verifyAmt, $verifyRemark, $verifyDate, $detailsTable, $expTable, $verifierId, $claimName = null, $filledAmount = 0)
    {
        $isCompany11 = ((int) session('company_id') === 11);
        $title = $claimName ?? 'General Expense';
        $existingDetail = DB::connection(session('expense_connection'))->table($detailsTable)->where('ExpId', $expId)->exists();
        if ($existingDetail) {
            DB::connection(session('expense_connection'))->table($detailsTable)->where('ExpId', $expId)->update(['VerifierEditAmount' => $verifyAmt, 'VerifiedDate' => $verifyDate, 'VerifierRemark' => $verifyRemark]);
        } else {
            DB::connection(session('expense_connection'))->table($detailsTable)->insert(['ExpId' => $expId, 'Title' => $title, 'Amount' => $filledAmount, 'VerifierEditAmount' => $verifyAmt, 'VerifiedDate' => $verifyDate, 'VerifierRemark' => $verifyRemark]);
        }

        $updateData = [
            'ClaimStatus' => 'Verified',
            'ClaimAtStep' => 4,
            'VerifyBy' => $verifierId,
            'VerifyTAmt' => $verifyAmt,
            'VerifyTRemark' => $verifyRemark,
            'VerifyDate' => $verifyDate,
        ];
        if (!$isCompany11) {
            $updateData['ApprTAmt'] = $verifyAmt;
            $updateData['FinancedTAmt'] = $verifyAmt;
        }
        DB::connection(session('expense_connection'))->table($expTable)->where('ExpId', $expId)->update($updateData);
    }

    private function calculateClaimTotals($empId, $month, $yearId)
    {
        $expTable = "y{$yearId}_expenseclaims";
        $deptData = DB::connection(session('hrims_connection'))->table('hrm_employee_general')->where('EmployeeID', $empId)->first(['DepartmentId', 'EmpVertical']);
        $deptId = $deptData->DepartmentId ?? 0;
        $empVertical = $deptData->EmpVertical ?? 0;
        $expIds = [];
        $odoCondition = '';
        if (in_array($deptId, [2, 3]) && in_array($empVertical, [1, 2])) {
            $kmRecords = DB::connection(session('expense_connection'))->table($expTable)->where('CrBy', $empId)->where('ClaimMonth', $month)->where('ClaimId', 7)->whereIn('WType', [2, 4])->whereNotIn('ClaimStatus', ['Draft', 'Deactivate'])->select('ExpId', 'TotKm', 'App_Odometer')->orderBy('ExpId', 'ASC')->get();
            $cumulativeKm = 0;
            $thresholdCrossed = false;
            foreach ($kmRecords as $record) {
                $cumulativeKm += (int) $record->TotKm;
                if ($cumulativeKm > 1200) {
                    $thresholdCrossed = true;
                }
                if ($thresholdCrossed && in_array($record->App_Odometer, ['N', 'R'])) {
                    $expIds[] = $record->ExpId;
                }
            }
            if (!empty($expIds)) {
                $odoCondition = ' AND ExpId NOT IN (' . implode(',', $expIds) . ')';
            }
        }
        $filledTotal = DB::connection(session('expense_connection'))->select("SELECT SUM(FilledTAmt) as total FROM `{$expTable}`
                  WHERE ClaimYearId = ? AND CrBy = ? AND ClaimMonth = ?
                  AND FilledOkay = 1 AND ClaimStatus NOT IN ('Deactivate', 'Draft')
                  AND FilledBy > 0 {$odoCondition}", [$yearId, $empId, $month]);
        $verifiedTotal = DB::connection(session('expense_connection'))->select("SELECT SUM(VerifyTAmt) as total FROM `{$expTable}`
                  WHERE ClaimYearId = ? AND CrBy = ? AND ClaimMonth = ?
                  AND FilledOkay = 1 AND ClaimStatus NOT IN ('Deactivate', 'Draft')
                  AND VerifyBy > 0", [$yearId, $empId, $month]);
        $approvedTotal = DB::connection(session('expense_connection'))->select("SELECT SUM(ApprTAmt) as total FROM `{$expTable}`
                  WHERE ClaimYearId = ? AND CrBy = ? AND ClaimMonth = ?
                  AND FilledOkay = 1 AND ClaimStatus NOT IN ('Deactivate', 'Draft')
                  AND ApprBy > 0", [$yearId, $empId, $month]);

        return [
            'filled' => $filledTotal[0]->total ?? 0,
            'verified' => $verifiedTotal[0]->total ?? 0,
            'approved' => $approvedTotal[0]->total ?? 0,
        ];
    }
}
