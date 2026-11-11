<?php

namespace App\Http\Controllers;

use App\Exports\AdvanceAdjustmentReportExport;
use App\Helpers\DatabaseSwitcher;
use App\Helpers\OrganogramFilterHelper;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Maatwebsite\Excel\Facades\Excel;

class AdvanceAdjustmentReportController extends Controller
{
    /**
     * Display the Advance Request and Adjustment Report page.
     */
    public function index(Request $request)
    {
        $conn = session('expense_connection') ?: 'expense';
        $companyId = session('company_id', 1);

        // 1. Load Filter Dropdown Options
        $financialYears = DB::connection($conn)
            ->table('financialyear')
            ->orderBy('YearId', 'desc')
            ->get();

        $latestYear = $financialYears->first();
        $defaultYearId = $latestYear ? $latestYear->YearId : 8;

        $activityTypes = DB::connection($conn)
            ->table('adv_activity_types')
            ->where('status', 1)
            ->orderBy('type_name')
            ->get(['id', 'type_name']);

        $organogramOptions = OrganogramFilterHelper::getOrganogramFilterOptions($companyId);

        // 2. Extract Request Filters
        $filters = $this->extractFilters($request, $defaultYearId);

        // 3. Build and execute query
        $rawRecords = $this->fetchData($filters, $conn);

        // 4. Compute KPIs across the entire filtered dataset
        $kpis = $this->calculateKpis($rawRecords);

        // 5. Apply Adjustment Status filter in memory (since status depends on dynamic balance & age)
        $filteredRecords = $this->applyAdjustmentStatusFilter($rawRecords, $filters['adjustment_status']);

        // 6. Pagination
        $perPage = $request->input('per_page', 50);
        $currentPage = LengthAwarePaginator::resolveCurrentPage();

        if ($perPage === 'all' || (int)$perPage <= 0) {
            $paginatedRecords = new LengthAwarePaginator(
                $filteredRecords,
                $filteredRecords->count(),
                max(1, $filteredRecords->count()),
                1,
                ['path' => LengthAwarePaginator::resolveCurrentPath(), 'query' => $request->query()]
            );
        } else {
            $perPage = (int) $perPage;
            $currentItems = $filteredRecords->slice(($currentPage - 1) * $perPage, $perPage)->values();
            $paginatedRecords = new LengthAwarePaginator(
                $currentItems,
                $filteredRecords->count(),
                $perPage,
                $currentPage,
                ['path' => LengthAwarePaginator::resolveCurrentPath(), 'query' => $request->query()]
            );
        }

        // 7. If an employee is searched, fetch their authoritative Ledger Summary to display a cross-check banner
        $employeeLedgerSummary = null;
        if (!empty($filters['search'])) {
            $s = trim($filters['search']);
            // Match on employee code only, within the current company (was: any company, and
            // also matched the internal EmployeeID, so a numeric search picked the wrong person).
            $emp = DB::table('hrims.hrm_employee')
                ->where('CompanyId', $companyId)
                ->where(function ($q) use ($s) {
                    $q->where('EmpCode', '=', $s)->orWhere('EmpCode_New', '=', $s);
                })
                ->first();

            if (!$emp && $filteredRecords->isNotEmpty() && $filteredRecords->pluck('employee_id')->unique()->count() === 1) {
                $firstEmpId = $filteredRecords->first()->employee_id;
                $emp = DB::table('hrims.hrm_employee')->where('EmployeeID', $firstEmpId)->first();
            }

            if ($emp) {
                $ledgerBal = DB::connection($conn)->table('employee_ledger_balances')
                    ->where('employee_id', $emp->EmployeeID)
                    ->orderByDesc('id')
                    ->first();

                // Same figures as the Employee Ledger Summary (the imported opening `balance` column is
                // a one-time import figure and was shown here as if it were the current balance).
                $computed = app(\App\Http\Controllers\ExpensePaymentController::class)
                    ->computeEmployeeLedgerBalance($emp->EmployeeID);
                $totalImprest = (float) $computed['total_imprest'];
                $netBal = (float) $computed['total_balance'];

                $employeeLedgerSummary = [
                    'employee_id'   => $emp->EmployeeID,
                    'emp_code'      => $emp->EmpCode ?: $emp->EmpCode_New,
                    'employee_name' => trim(trim($emp->Fname . ' ' . ($emp->Sname ?? '')) . ' ' . $emp->Lname),
                    'imprest'       => $totalImprest,
                    'imprest_date'  => $ledgerBal->fixed_imprest_date_1 ?? null,
                    'net_balance'   => $netBal,
                    'adv_balance'   => (float) $computed['advance_balance'],
                ];
            }
        }

        return view('reports.advance-adjustment-report', array_merge($organogramOptions, [
            'records'               => $paginatedRecords,
            'kpis'                  => $kpis,
            'filters'               => $filters,
            'financialYears'        => $financialYears,
            'activityTypes'         => $activityTypes,
            'defaultYearId'         => $defaultYearId,
            'totalCount'            => $filteredRecords->count(),
            'employeeLedgerSummary' => $employeeLedgerSummary,
        ]));
    }

    /**
     * Export the filtered Advance Request and Adjustment Report to Excel.
     */
    public function export(Request $request)
    {
        ini_set('max_execution_time', 600);
        ini_set('memory_limit', '2048M');

        $conn = session('expense_connection') ?: 'expense';
        $financialYears = DB::connection($conn)->table('financialyear')->orderBy('YearId', 'desc')->get();
        $defaultYearId = $financialYears->first() ? $financialYears->first()->YearId : 8;

        $filters = $this->extractFilters($request, $defaultYearId);
        $rawRecords = $this->fetchData($filters, $conn);
        $kpis = $this->calculateKpis($rawRecords);
        $filteredRecords = $this->applyAdjustmentStatusFilter($rawRecords, $filters['adjustment_status']);

        $fileName = 'Advance_Adjustment_Report_' . date('Ymd_His') . '.xlsx';
        return Excel::download(new AdvanceAdjustmentReportExport($filteredRecords, $kpis, $filters), $fileName);
    }

    /**
     * Extract and normalize filters from the request.
     */
    private function extractFilters(Request $request, $defaultYearId): array
    {
        $yearId = $request->input('year_id');  // 'all' or a number; cast below (LV-S08)
        if ($yearId === 'all') {
            $yearId = null;
        } elseif ($yearId !== null && is_numeric($yearId)) {
            $yearId = (int) $yearId;
        } else {
            $yearId = $defaultYearId;
        }

        $months = (array) $request->input('months', []);
        $months = array_values(array_filter(array_map('intval', $months), fn($m) => $m >= 1 && $m <= 12));

        $organogramFilters = OrganogramFilterHelper::extractFilters($request);

        return array_merge($organogramFilters, [
            'year_id'            => $yearId,
            'months'             => $months,
            'adjustment_status'  => $request->input('adjustment_status', 'all'),
            'payment_status'     => $request->input('payment_status', 'all'),
            'activity_type_ids'  => OrganogramFilterHelper::parseFilterIds($request->input('activity_type_ids') ?: $request->input('activity_type_id')),
            'is_imprest'         => $request->input('is_imprest', 'regular'),
            'search'             => trim($request->input('search', '')),
            'pay_date_from'      => $request->input('pay_date_from'),
            'pay_date_to'        => $request->input('pay_date_to'),
            'req_date_from'      => $request->input('req_date_from'),
            'req_date_to'        => $request->input('req_date_to'),
            'per_page'           => $request->input('per_page', 50),
        ]);
    }

    /**
     * Fetch raw advance activities joined with employee and organogram details.
     */
    private function fetchData(array $filters, string $conn): \Illuminate\Support\Collection
    {
        $query = DB::connection($conn)->table('advance_requests as ar')
            ->join('advance_request_employees as are', 'are.advance_request_id', '=', 'ar.id')
            ->join('advance_request_activities as ara', 'ara.advance_request_employee_id', '=', 'are.id')
            ->leftJoin('financialyear as fy', 'fy.YearId', '=', 'ar.year_id')
            ->leftJoin('adv_activity_types as aat', 'ara.activity_type_id', '=', 'aat.id')
            ->leftJoin('advance_request_types as art', 'ar.advance_type_id', '=', 'art.id')
            ->leftJoin('adv_activity_names as an', 'an.id', '=', 'ar.activity_name_id')
            ->leftJoin('claimtype as ct', 'ct.ClaimId', '=', 'an.activity_name')
            ->join('hrims.hrm_employee as emp', 'emp.EmployeeID', '=', 'are.employee_id')
            ->join('hrims.hrm_employee_general as eg', 'eg.EmployeeID', '=', 'emp.EmployeeID')
            ->leftJoin('hrims.core_departments as dept', 'dept.id', '=', 'eg.DepartmentId')
            ->leftJoin('hrims.core_sub_department_master as sub_dept', 'sub_dept.id', '=', 'eg.SubDepartmentId')
            ->leftJoin('hrims.core_verticals as vert', 'vert.id', '=', 'eg.EmpVertical')
            ->leftJoin('hrims.core_functions as func', 'func.id', '=', 'eg.EmpFunction')
            ->leftJoin('hrims.core_city_village_by_state as hq', 'hq.id', '=', 'eg.HqId') // UAT-03: HRIMS has no hrm_headquater; HQ master is core_city_village_by_state
            ->whereNull('ar.deleted_at')
            ->whereNull('are.deleted_at')
            ->whereNull('ara.deleted_at');

        // Financial Year filter
        if (!empty($filters['year_id'])) {
            $query->where('ar.year_id', $filters['year_id']);
        }

        // Months filter
        if (!empty($filters['months'])) {
            $query->whereIn('ar.month', $filters['months']);
        }

        // Payment status filter
        if (!empty($filters['payment_status']) && $filters['payment_status'] !== 'all') {
            if ($filters['payment_status'] === 'Pending') {
                $query->where(function($q) {
                    $q->whereNotIn('ara.payment_status', ['Paid', 'Unsettled'])
                      ->orWhereNull('ara.payment_status');
                });
            } else {
                $query->where('ara.payment_status', $filters['payment_status']);
            }
        }

        // Activity type filter
        if (!empty($filters['activity_type_ids'])) {
            $query->whereIn('ara.activity_type_id', $filters['activity_type_ids']);
        }

        // Imprest filter
        if ($filters['is_imprest'] === 'regular') {
            $query->where(function($q) {
                $q->where('ara.is_imprest', 0)->orWhereNull('ara.is_imprest');
            })->where(function($q) {
                $q->where('ar.is_imprest', 0)->orWhereNull('ar.is_imprest');
            })->where(function($q) {
                $q->where('ara.purpose', 'NOT LIKE', '%imprest%')->orWhereNull('ara.purpose');
            });
        } elseif ($filters['is_imprest'] === 'imprest') {
            $query->where(function($q) {
                $q->where('ara.is_imprest', 1)
                  ->orWhere('ar.is_imprest', 1)
                  ->orWhere('ara.purpose', 'LIKE', '%imprest%');
            });
        }

        // Date Range: Payment Date
        if (!empty($filters['pay_date_from'])) {
            $query->whereDate('ara.payment_date', '>=', $filters['pay_date_from']);
        }
        if (!empty($filters['pay_date_to'])) {
            $query->whereDate('ara.payment_date', '<=', $filters['pay_date_to']);
        }

        // Date Range: Request Date
        if (!empty($filters['req_date_from'])) {
            $query->whereDate('ara.created_at', '>=', $filters['req_date_from']);
        }
        if (!empty($filters['req_date_to'])) {
            $query->whereDate('ara.created_at', '<=', $filters['req_date_to']);
        }

        // General Search Filter
        if (!empty($filters['search'])) {
            $s = trim($filters['search']);
            $query->where(function ($q) use ($s) {
                $q->where('emp.EmpCode', '=', $s)
                  ->orWhere('emp.EmpCode', 'LIKE', '%' . $s . '%')
                  ->orWhere(DB::raw("TRIM(CONCAT(COALESCE(emp.Fname,''), ' ', COALESCE(emp.Sname,''), ' ', COALESCE(emp.Lname,'')))"), 'LIKE', '%' . $s . '%')
                  ->orWhere('ara.purpose', 'LIKE', '%' . $s . '%')
                  ->orWhere('ar.id', '=', $s)
                  ->orWhere('ara.id', '=', $s);
                // Prevent numeric employee codes (e.g. 1482) from matching substrings inside bank UTRs of other employees
                if (strlen($s) >= 8 && !is_numeric($s)) {
                    $q->orWhere('ara.utr_number', 'LIKE', '%' . $s . '%');
                }
            });
        }

        // Organogram filters
        OrganogramFilterHelper::applyToQuery($query, $filters, 'eg', 'hrims', 'emp.EmployeeID');

        $query->select([
            'ar.id as request_id',
            'ar.year_id',
            'fy.Year as financial_year',
            'ar.month as request_month',
            'ar.last_submitted_at as request_submitted_at',
            'ar.is_imprest as is_req_imprest',
            'art.type_name as advance_type',
            'ct.ClaimName as activity_name',
            'ara.id as activity_id',
            'ara.activity_type_id',
            'aat.type_name as activity_type',
            'ara.purpose',
            'ara.amount as requested_amount',
            'ara.approval_status',
            'ara.finance_approved_amount',
            'ara.finance_status',
            'ara.payment_amount',
            'ara.payment_status',
            'ara.payment_date',
            'ara.adjustment_amount',
            'ara.remaining_balance',
            'ara.adjusted_against_month',
            'ara.adjustment_date',
            'ara.adjustment_remarks',
            'ara.utr_number',
            'ara.is_imprest',
            'ara.created_at as activity_created_at',
            'emp.EmployeeID as employee_id',
            'emp.EmpCode as emp_code',
            DB::raw("TRIM(CONCAT(COALESCE(emp.Fname,''), ' ', COALESCE(emp.Sname,''), ' ', COALESCE(emp.Lname,''))) as emp_name"),
            'dept.department_name',
            'sub_dept.sub_department_name',
            'func.function_name',
            'vert.vertical_name',
            'hq.city_village_name as hq_name',
        ]);

        $rows = $query->orderBy(DB::raw('COALESCE(ara.payment_date, ara.created_at)'), 'desc')
            ->orderBy('ara.id', 'desc')
            ->get();

        // ADV-HL-01 (Finance 10 Nov 2026): keep the stored adjustment date to spot advances that the claims used up
        // fully but that have no adjustment recorded; they are highlighted for the payment section to act on.
        foreach ($rows as $r) {
            $r->stored_adjustment_date = $r->adjustment_date ?? null;
            $r->stored_adjustment_amount = $r->adjustment_amount ?? null;
        }

        // Reconcile with claim deductions recorded in y{year}_monthexpensefinal
        $rows = $this->reconcileWithClaims($rows, $conn);

        // Compute dynamic fields (paid amount, remaining, adjustment status, age)
        return $rows->map(function ($row) {
            $isImprest = (!empty($row->is_imprest) && $row->is_imprest == 1)
                || (!empty($row->is_req_imprest) && $row->is_req_imprest == 1)
                || (stripos($row->purpose ?? '', 'imprest') !== false)
                || (stripos($row->advance_type ?? '', 'imprest') !== false);

            $paymentStatus = trim($row->payment_status ?? 'Pending');
            $isPaid = (strtolower($paymentStatus) === 'paid');

            if ($isImprest) {
                // IMPREST IS FIXED AND NEVER ADJUSTED AGAINST MONTHLY CLAIMS
                $paidAmt = $isPaid ? (float) ($row->payment_amount ?: $row->requested_amount) : 0.0;
                $adjAmt  = 0.0;
                $remBal  = $paidAmt;

                if (!$isPaid) {
                    $statusKey = 'pending_payment';
                    $statusLabel = 'Pending Payment';
                    $statusBadge = 'bg-secondary';
                } else {
                    $statusKey = 'unadjusted';
                    $statusLabel = 'Fixed Imprest (Active)';
                    $statusBadge = 'bg-info text-white';
                }
            } else {
                if (!$isPaid) {
                    // Unpaid / Pending requests have NOT disbursed funds to the employee yet,
                    // so there is no outstanding unadjusted advance balance with the employee.
                    $paidAmt = 0.0;
                    $adjAmt  = 0.0;
                    $remBal  = 0.0;
                    $statusKey = 'pending_payment';
                    $statusLabel = 'Pending Payment';
                    $statusBadge = 'bg-secondary';
                } else {
                    $paidAmt = (float) ($row->reconciled_paid ?? ($row->payment_amount ?: $row->requested_amount));
                    $adjAmt  = (float) ($row->reconciled_adjusted ?? ($row->adjustment_amount ?? 0));
                    $remBal  = (float) ($row->reconciled_remaining ?? (($row->remaining_balance !== null && $row->remaining_balance !== '')
                        ? (float) $row->remaining_balance
                        : max(0.0, $paidAmt - $adjAmt)));

                    if ($remBal <= 0 || ($adjAmt >= $paidAmt && $paidAmt > 0)) {
                        $statusKey = 'fully_adjusted';
                        $statusLabel = 'Fully Adjusted';
                        $statusBadge = 'bg-success';
                    } elseif ($adjAmt > 0 && $remBal > 0) {
                        $statusKey = 'partially_adjusted';
                        $statusLabel = 'Partially Adjusted';
                        $statusBadge = 'bg-warning text-dark';
                    } else {
                        $statusKey = 'unadjusted';
                        $statusLabel = 'Completely Unadjusted';
                        $statusBadge = 'bg-danger';
                    }
                }
            }

            $payDate = $row->payment_date ?: ($row->activity_created_at ? substr($row->activity_created_at, 0, 10) : null);

            $ageDays = 0;
            if ($payDate) {
                try {
                    $ageDays = max(0, (int) floor(Carbon::parse($payDate)->diffInDays(Carbon::now())));
                } catch (\Exception $e) {
                    $ageDays = 0;
                }
            }
            $isOver2Months = ($ageDays > 60 && $remBal > 0 && $isPaid && !$isImprest);

            $row->is_imprest_flag            = $isImprest;
            $row->computed_paid_amount       = $paidAmt;
            $row->computed_paid_amt          = $paidAmt;
            $row->computed_adjusted_amount   = $adjAmt;
            $row->computed_adjusted_amt      = $adjAmt;
            $row->computed_remaining_balance = $remBal;
            $row->adjustment_status_key      = $statusKey;
            $row->adjustment_status_label    = $statusLabel;
            $row->status_label               = $statusLabel;
            $row->adjustment_status_badge    = $statusBadge;
            $row->status_badge_class         = $statusBadge;
            $row->age_days                   = $ageDays;
            $row->age_in_days                = $ageDays;
            $row->is_over_2_months           = $isOver2Months;

            return $row;
        });
    }

    /**
     * Cross-reference claim deductions from y{year}_monthexpensefinal and carried_balance_json.
     * This mirrors the exact logic used by Employee Ledger Summary so that all numbers match 100%.
     */
    /**
     * Load every regular (non-imprest) paid/unsettled advance of the given employees, across all
     * financial years, plus the opening advances imported into employee_ledger_balances
     * (advance_amt_1..4). This is the full set a claim deduction can be applied to, so FIFO
     * allocation no longer depends on which subset (year / month / date filter) the caller passed in.
     *
     * Opening advances get activity_id "L{ledger_id}_{slot}", is_ledger_opening = 1 and are dated at
     * the ledger import date (created_at): the imported figure is the outstanding position at import,
     * so only claims settled from that month onward can adjust it.
     */
    public function loadAdvanceUniverse(array $empIds, $conn, ?string $asOfDate = null): \Illuminate\Support\Collection
    {
        if (empty($empIds)) {
            return collect();
        }

        $query = DB::connection($conn)
            ->table('advance_requests as ar')
            ->join('advance_request_employees as are', 'are.advance_request_id', '=', 'ar.id')
            ->join('advance_request_activities as ara', 'ara.advance_request_employee_id', '=', 'are.id')
            ->leftJoin('adv_activity_types as aat', 'aat.id', '=', 'ara.activity_type_id')
            ->whereIn('are.employee_id', $empIds)
            ->whereIn('ara.payment_status', ['Paid', 'Unsettled'])
            ->where(function ($q) {
                $q->where('ar.is_imprest', 0)->orWhereNull('ar.is_imprest');
            })
            ->where(function ($q) {
                $q->where('ara.is_imprest', 0)->orWhereNull('ara.is_imprest');
            })
            ->where(function ($q) {
                $q->where('ara.purpose', 'NOT LIKE', '%imprest%')->orWhereNull('ara.purpose');
            })
            ->whereNull('ar.deleted_at')
            ->whereNull('are.deleted_at')
            ->whereNull('ara.deleted_at');

        if ($asOfDate) {
            $query->where(function ($q) use ($asOfDate) {
                $q->whereNull('ara.payment_date')
                  ->orWhere('ara.payment_date', '0000-00-00')
                  ->orWhere('ara.payment_date', '<=', $asOfDate);
            });
        }

        $advances = $query->select([
                'ara.id as activity_id',
                'are.advance_request_id as request_id',
                'are.employee_id',
                'ara.amount',
                'ara.amount as requested_amount',
                'ara.payment_amount',
                'ara.payment_date',
                'ara.payment_status',
                'ara.adjustment_amount',
                'ara.adjustment_date',
                'ara.remaining_balance',
                'ara.adjusted_against_month',
                'ara.purpose',
                'ara.utr_number',
                'ara.adjustment_remarks',
                'ara.is_imprest',
                'ar.is_imprest as is_req_imprest',
                'aat.type_name as activity_type',
                'ar.month as request_month',
                'ar.year_id',
                'ara.created_at',
                'ara.created_at as activity_created_at',
            ])
            ->orderBy(DB::raw('COALESCE(ara.payment_date, ara.created_at)'))
            ->orderBy('ara.id')
            ->get();

        // Opening advances carried in the imported ledger row (latest row per employee).
        $ledgerRows = DB::connection($conn)
            ->table('employee_ledger_balances')
            ->whereIn('employee_id', $empIds)
            ->whereNull('deleted_at')
            ->orderByDesc('id')
            ->get()
            ->unique('employee_id');

        foreach ($ledgerRows as $lb) {
            $importDate = $lb->created_at ? substr((string) $lb->created_at, 0, 10) : null;
            if ($asOfDate && $importDate && $importDate > $asOfDate) {
                continue;
            }
            for ($slot = 1; $slot <= 4; $slot++) {
                $amt = (float) ($lb->{"advance_amt_$slot"} ?? 0);
                if ($amt <= 0) {
                    continue;
                }
                $slotDate = $lb->{"advance_date_$slot"} ?? null;
                $advances->push((object) [
                    'activity_id'            => 'L' . $lb->id . '_' . $slot,
                    'request_id'             => null,
                    'employee_id'            => (int) $lb->employee_id,
                    'amount'                 => $amt,
                    'requested_amount'       => $amt,
                    'payment_amount'         => $amt,
                    'payment_date'           => $importDate,
                    'opening_advance_date'   => ($slotDate && \App\Support\ZeroDate::has($slotDate)) ? $slotDate : null,
                    'payment_status'         => 'Paid',
                    'adjustment_amount'      => 0,
                    'adjustment_date'        => null,
                    'remaining_balance'      => null,
                    'adjusted_against_month' => null,
                    'purpose'                => trim('Opening advance ' . ($lb->{"advance_purpose_$slot"} ?? '')),
                    'utr_number'             => null,
                    'adjustment_remarks'     => null,
                    'is_imprest'             => 0,
                    'is_req_imprest'         => 0,
                    'activity_type'          => 'Opening Advance (Ledger)',
                    'request_month'          => $lb->{"advance_month_$slot"} ?? null,
                    'year_id'                => (int) $lb->year_id,
                    'created_at'             => $lb->created_at,
                    'activity_created_at'    => $lb->created_at,
                    'is_ledger_opening'      => 1,
                    'ledger_slot'            => $slot,
                ]);
            }
        }

        // Infer financial year_id from the payment date (same rule as the callers used).
        foreach ($advances as $adv) {
            $d = $adv->payment_date ?: ($adv->created_at ? substr($adv->created_at, 0, 10) : null);
            if ($d && \App\Support\ZeroDate::has($d)) {
                $cYear = (int) date('Y', strtotime($d));
                $cMonth = (int) date('n', strtotime($d));
                $fyStart = ($cMonth >= 4) ? $cYear : ($cYear - 1);
                $adv->year_id = max(1, $fyStart - 2018);
            } elseif (empty($adv->year_id)) {
                $adv->year_id = session('year_id') ?: 8;
            }
        }

        return $advances;
    }

    /**
     * Reconcile advances against claim deductions.
     *
     * The FIFO allocation is always run on the employee's FULL advance universe (all years + opening
     * ledger advances, see loadAdvanceUniverse) and the result copied back to the rows passed in, so a
     * year / month / date filter on the report no longer changes how deductions are allocated.
     *
     * Options: include_opening (bool) – append the opening-ledger pseudo advances to the result;
     *          as_of (Y-m-d)          – ignore claims settled after this date (as-of-date snapshot).
     */
    public function reconcileWithClaims($rows, $conn, array $options = [])
    {
        $includeOpening = ! empty($options['include_opening']);
        $asOfDate = $options['as_of'] ?? null;

        if ($rows->isEmpty() && empty($options['employee_ids'])) {
            return $rows;
        }

        $empIds = $rows->pluck('employee_id')->filter()->unique()->values()->all();
        if (! empty($options['employee_ids'])) {
            $empIds = array_values(array_unique(array_merge($empIds, $options['employee_ids'])));
        }
        if (empty($empIds)) {
            return $rows;
        }

        $universe = $this->loadAdvanceUniverse($empIds, $conn, $asOfDate);
        $reconciled = $this->reconcileUniverse($universe, $conn, $asOfDate)->keyBy(fn ($a) => (string) $a->activity_id);

        foreach ($rows as $row) {
            $paid = (float) ($row->payment_amount ?: ($row->requested_amount ?? ($row->amount ?? 0)));
            $row->reconciled_paid = $paid;
            $row->reconciled_adjusted = 0.0;
            $row->reconciled_remaining = $paid;
            $row->claim_settlements = [];
            $row->adjusted_against_month = null;
            $row->adjustment_date = null;

            $key = (string) ($row->activity_id ?? '');
            if ($key !== '' && isset($reconciled[$key])) {
                $m = $reconciled[$key];
                $row->reconciled_paid = $m->reconciled_paid;
                $row->reconciled_adjusted = $m->reconciled_adjusted;
                $row->reconciled_remaining = $m->reconciled_remaining;
                $row->adjusted_against_month = $m->adjusted_against_month;
                $row->adjustment_date = $m->adjustment_date;
                $row->claim_settlements = $m->claim_settlements;
            }
        }

        if ($includeOpening) {
            $present = $rows->pluck('activity_id')->map(fn ($v) => (string) $v)->flip();
            foreach ($reconciled as $key => $adv) {
                if (! empty($adv->is_ledger_opening) && ! isset($present[$key])) {
                    $rows->push($adv);
                }
            }
        }

        return $rows;
    }

    /**
     * Full reconciled advance set (regular advances + opening ledger advances) for employees.
     * Used by the Employee Ledger Summary, its API/export and the employee ledger detail.
     */
    public function reconcileEmployees(array $empIds, $conn, ?string $asOfDate = null): \Illuminate\Support\Collection
    {
        return $this->reconcileUniverse($this->loadAdvanceUniverse($empIds, $conn, $asOfDate), $conn, $asOfDate);
    }

    /**
     * FIFO allocation of claim deductions (y{year}_monthexpensefinal) to a complete advance set.
     */
    private function reconcileUniverse($rows, $conn, ?string $asOfDate = null)
    {
        if ($rows->isEmpty()) {
            return $rows;
        }

        $empIds = $rows->pluck('employee_id')->filter()->unique()->values()->all();
        if (empty($empIds)) {
            return $rows;
        }

        $yearIds = $rows->pluck('year_id')->filter()->unique()->values()->all();
        if (empty($yearIds)) {
            $yearIds = [session('year_id') ?: 8];
        } else {
            $minYear = min($yearIds);
            $maxYear = max(array_merge([session('year_id') ?: 8], $yearIds));
            $yearIds = range($minYear, $maxYear);
        }

        $allClaims = [];
        foreach ($yearIds as $y) {
            $tbl = "y{$y}_monthexpensefinal";
            try {
                $columns = [
                    'id', 'EmployeeID', 'Month', 'YearId', 'payment_status',
                    'total_adjustment', 'total_activity_adjustment', 'adjust_activity_amount',
                    'carried_balance_json', 'Fin_PayDate'
                ];
                if (\Illuminate\Support\Facades\Schema::connection($conn)->hasColumn($tbl, 'Fin_AppDate')) {
                    $columns[] = 'Fin_AppDate';
                }
                if (\Illuminate\Support\Facades\Schema::connection($conn)->hasColumn($tbl, 'activity_adjustments_json')) {
                    $columns[] = 'activity_adjustments_json';
                }
                if (\Illuminate\Support\Facades\Schema::connection($conn)->hasColumn($tbl, 'expense_adjustments_json')) {
                    $columns[] = 'expense_adjustments_json';
                }
                if (\Illuminate\Support\Facades\Schema::connection($conn)->hasColumn($tbl, 'Finance_Amount')) {
                    $columns[] = 'Finance_Amount';
                }
                if (\Illuminate\Support\Facades\Schema::connection($conn)->hasColumn($tbl, 'Finance_Date')) {
                    $columns[] = 'Finance_Date';
                }
                if (\Illuminate\Support\Facades\Schema::connection($conn)->hasColumn($tbl, 'Fin_PayRemark')) {
                    $columns[] = 'Fin_PayRemark';
                }

                $claims = DB::connection($conn)
                    ->table($tbl)
                    ->whereIn('EmployeeID', $empIds)
                    ->where(function ($q) {
                        $q->whereIn('payment_status', ['ConfirmedPayment', 'InstructedPayment', 'Unsettled'])
                          ->orWhere('Status', 'Closed')
                          ->orWhere(function ($q2) {
                              $q2->whereNotNull('Fin_PayDate')
                                 ->where('Fin_PayDate', '!=', '0000-00-00')
                                 ->where('total_adjustment', '>', 0);
                          });
                    })
                    ->select($columns)
                    ->orderBy('Month', 'asc')
                    ->orderBy('id', 'asc')
                    ->get();

                foreach ($claims as $c) {
                    if ($asOfDate) {
                        // As-of snapshot: only deductions settled (paid, else finance-approved) on/before the date.
                        $settled = (! empty($c->Fin_PayDate) && \App\Support\ZeroDate::has($c->Fin_PayDate))
                            ? $c->Fin_PayDate
                            : ((! empty($c->Fin_AppDate) && \App\Support\ZeroDate::has($c->Fin_AppDate)) ? $c->Fin_AppDate : null);
                        if ($settled === null || $settled > $asOfDate) {
                            continue;
                        }
                    }
                    $allClaims[$c->EmployeeID][] = $c;
                }
            } catch (\Exception $e) {
                // Table might not exist for older years
            }
        }

        // Helper to determine chronological rank of an advance (Year * 100 + Month)
        $getAdvRank = function ($a) {
            $aDate = $a->payment_date ?: ($a->activity_created_at ? substr($a->activity_created_at, 0, 10) : null);
            $aMonth = $aDate ? (int) date('n', strtotime($aDate)) : (int) ($a->request_month ?? 4);
            $aYear = (int) ($a->year_id ?? 0);
            if ($aDate && \App\Support\ZeroDate::has($aDate)) {
                $cYear = (int) date('Y', strtotime($aDate));
                $cMonth = (int) date('n', strtotime($aDate));
                $fyStart = ($cMonth >= 4) ? $cYear : ($cYear - 1);
                $aYear = max(1, $fyStart - 2018);
                $aMonth = $cMonth;
            } elseif (empty($aYear)) {
                $aYear = 8;
            }
            return ($aYear * 100) + (($aMonth >= 4) ? $aMonth : $aMonth + 12);
        };

        // Initialize default reconciliation values for all advances
        foreach ($rows as $adv) {
            $paid = (float) ($adv->payment_amount ?: ($adv->requested_amount ?? ($adv->amount ?? 0)));
            $adv->reconciled_paid = $paid;
            $adv->reconciled_adjusted = 0.0;
            $adv->reconciled_remaining = $paid;
            $adv->claim_settlements = [];
            $adv->adjusted_against_month = null;
            $adv->adjustment_date = null;
        }

        $rowsByEmp = $rows->groupBy('employee_id');

        foreach ($rowsByEmp as $empId => $empRows) {
            $claims = $allClaims[$empId] ?? [];
            if (empty($claims)) {
                continue;
            }

            // Map advances by activity_id (sorted chronologically ASC for correct FIFO allocation)
            $sortedEmpRows = $empRows->sortBy(function ($adv) use ($getAdvRank) {
                // Opening (imported) advances are older than any advance paid in the same month.
                return $getAdvRank($adv) . '_' . (empty($adv->is_ledger_opening) ? '1' : '0') . '_' . str_pad((string) ($adv->activity_id ?? ''), 10, '0', STR_PAD_LEFT);
            });

            $advMap = [];
            foreach ($sortedEmpRows as $adv) {
                $isAdvImprest = (!empty($adv->is_imprest) && $adv->is_imprest == 1)
                    || (!empty($adv->is_req_imprest) && $adv->is_req_imprest == 1)
                    || (stripos($adv->purpose ?? '', 'imprest') !== false)
                    || (stripos($adv->advance_type ?? '', 'imprest') !== false);

                $paid = (float) ($adv->payment_amount ?: $adv->requested_amount);

                if ($isAdvImprest) {
                    // IMPREST IS FIXED AND NEVER ADJUSTED AGAINST MONTHLY CLAIMS
                    $adv->reconciled_paid = $paid;
                    $adv->reconciled_adjusted = 0.0;
                    $adv->reconciled_remaining = $paid;
                    $adv->claim_settlements = [];
                    $adv->adjusted_against_month = null;
                    $adv->adjustment_date = null;
                    continue; // Exclude from $advMap so claim deductions never touch imprest!
                }

                $adv->reconciled_paid = $paid;
                $adv->reconciled_adjusted = 0.0;
                $adv->reconciled_remaining = $paid;
                $adv->claim_settlements = [];
                $adv->adjusted_against_month = null;
                $adv->adjustment_date = null;
                $advMap[(string) $adv->activity_id] = $adv;
            }

            // Deduction handler matching AccountSummaryController
            $applyDeduction = function ($targetActId, $amt, $mMonth, $mYear, $payDate = null) use (&$advMap, $getAdvRank) {
                if ($amt <= 0) {
                    return 0.0;
                }
                $rem = $amt;
                $claimRank = ($mYear * 100) + (($mMonth >= 4) ? $mMonth : $mMonth + 12);

                // Determine effective chronological rank of this claim.
                // A claim for expense month M is typically finalized and settled in month M+1 (or later).
                // Thus, any advance disbursed on or before the claim settlement date is eligible.
                $claimPayYear = $mYear;
                $claimPayMonth = $mMonth;
                if ($payDate && \App\Support\ZeroDate::has($payDate)) {
                    $cYear = (int) date('Y', strtotime($payDate));
                    $cMonth = (int) date('n', strtotime($payDate));
                    $fyStart = ($cMonth >= 4) ? $cYear : ($cYear - 1);
                    $claimPayYear = max(1, $fyStart - 2018);
                    $claimPayMonth = $cMonth;
                }
                $claimPayRank = ($claimPayYear * 100) + (($claimPayMonth >= 4) ? $claimPayMonth : $claimPayMonth + 12);
                $effectiveClaimRank = max($claimRank, $claimPayRank);

                // 1. Direct match by activity ID (explicitly recorded by finance in JSON - always respect factual record)
                if ($targetActId && isset($advMap[$targetActId]) && $advMap[$targetActId]->reconciled_remaining > 0) {
                    $can = min($advMap[$targetActId]->reconciled_remaining, $rem);
                    $advMap[$targetActId]->reconciled_adjusted += $can;
                    $advMap[$targetActId]->reconciled_remaining = max(0.0, $advMap[$targetActId]->reconciled_remaining - $can);
                    $advMap[$targetActId]->adjusted_against_month = $mMonth;
                    if ($payDate && \App\Support\ZeroDate::has($payDate)) {
                        $advMap[$targetActId]->adjustment_date = $payDate;
                    }
                    $advMap[$targetActId]->claim_settlements[] = [
                        'year' => $mYear,
                        'month' => $mMonth,
                        'adjusted' => $can,
                        'remaining' => $advMap[$targetActId]->reconciled_remaining,
                        'date' => $payDate,
                    ];
                    $rem -= $can;
                }

                // 2. Exact amount match in same month & year (advances disbursed on or before this claim settlement)
                if ($rem > 0 && $mMonth) {
                    foreach ($advMap as $id => $a) {
                        $aRank = $getAdvRank($a);
                        if ($aRank > $effectiveClaimRank) {
                            continue;
                        }

                        $aDate = $a->payment_date ?: ($a->activity_created_at ? substr($a->activity_created_at, 0, 10) : null);
                        $aMonth = $aDate ? (int) date('n', strtotime($aDate)) : (int) $a->request_month;
                        $aYear = (int) ($a->year_id ?? 0);
                        if ($a->reconciled_remaining > 0 && ($aMonth == $mMonth || $aMonth == $claimPayMonth) && (! $mYear || $aYear == $mYear || $aYear == $claimPayYear)) {
                            if (abs($a->reconciled_remaining - $rem) < 0.01) {
                                $can = min($a->reconciled_remaining, $rem);
                                $a->reconciled_adjusted += $can;
                                $a->reconciled_remaining = max(0.0, $a->reconciled_remaining - $can);
                                $a->adjusted_against_month = $mMonth;
                                if ($payDate && \App\Support\ZeroDate::has($payDate)) {
                                    $a->adjustment_date = $payDate;
                                }
                                $a->claim_settlements[] = [
                                    'year' => $mYear,
                                    'month' => $mMonth,
                                    'adjusted' => $can,
                                    'remaining' => $a->reconciled_remaining,
                                    'date' => $payDate,
                                ];
                                $rem -= $can;
                                break;
                            }
                        }
                    }
                }

                // 3. Fallback FIFO within the same financial year (advances disbursed on or before this claim settlement)
                if ($rem > 0 && $mYear) {
                    foreach ($advMap as $id => $a) {
                        $aRank = $getAdvRank($a);
                        if ($aRank > $effectiveClaimRank) {
                            continue;
                        }

                        if ($a->reconciled_remaining > 0 && ((int) $a->year_id == $mYear || (int) $a->year_id == $claimPayYear)) {
                            $can = min($a->reconciled_remaining, $rem);
                            $a->reconciled_adjusted += $can;
                            $a->reconciled_remaining = max(0.0, $a->reconciled_remaining - $can);
                            $a->adjusted_against_month = $mMonth;
                            if ($payDate && \App\Support\ZeroDate::has($payDate)) {
                                $a->adjustment_date = $payDate;
                            }
                            $a->claim_settlements[] = [
                                'year' => $mYear,
                                'month' => $mMonth,
                                'adjusted' => $can,
                                'remaining' => $a->reconciled_remaining,
                                'date' => $payDate,
                            ];
                            $rem -= $can;
                            if ($rem <= 0) {
                                break;
                            }
                        }
                    }
                }

                // 4. Global FIFO fallback across any prior advances (disbursed on or before this claim settlement)
                if ($rem > 0) {
                    foreach ($advMap as $id => $a) {
                        $aRank = $getAdvRank($a);
                        if ($aRank > $effectiveClaimRank) {
                            continue;
                        }

                        if ($a->reconciled_remaining > 0) {
                            $can = min($a->reconciled_remaining, $rem);
                            $a->reconciled_adjusted += $can;
                            $a->reconciled_remaining = max(0.0, $a->reconciled_remaining - $can);
                            $a->adjusted_against_month = $mMonth;
                            if ($payDate && \App\Support\ZeroDate::has($payDate)) {
                                $a->adjustment_date = $payDate;
                            }
                            $a->claim_settlements[] = [
                                'year' => $mYear,
                                'month' => $mMonth,
                                'adjusted' => $can,
                                'remaining' => $a->reconciled_remaining,
                                'date' => $payDate,
                            ];
                            $rem -= $can;
                            if ($rem <= 0) {
                                break;
                            }
                        }
                    }
                }

                return $amt - $rem;
            };

            $prevCarriedList = [];
            foreach ($claims as $c) {
                if (strtolower(trim($c->payment_status ?? '')) === 'open') {
                    continue;
                }

                $mMonth = (int) $c->Month;
                $mYear = (int) ($c->YearId ?? 8);
                $payDate = (!empty($c->Fin_PayDate) && \App\Support\ZeroDate::has($c->Fin_PayDate)) ? $c->Fin_PayDate : null;
                $recordedSum = 0.0;

                // 1. Process explicit activity_adjustments_json
                $monthAdjs = json_decode($c->activity_adjustments_json ?? '{}', true) ?: [];
                if (is_array($monthAdjs)) {
                    foreach ($monthAdjs as $k => $adj) {
                        $amt = (float) ($adj['amount'] ?? 0);
                        if ($amt <= 0) {
                            continue;
                        }
                        $recordedSum += $amt;

                        $targetId = (string) ($adj['advance_activity_id'] ?? '');
                        $targetActId = null;

                        if ($targetId !== '' && isset($advMap[$targetId])) {
                            $targetActId = $targetId;
                        } elseif (is_numeric($targetId) && (int) $targetId > 0 && isset($advMap[(int) $targetId])) {
                            $targetActId = (string) (int) $targetId;
                        } elseif (strpos($targetId, 'carried_') === 0 || strpos((string) $k, 'carried_') === 0) {
                            $cleanKey = strpos($targetId, 'carried_') === 0 ? $targetId : (string) $k;
                            $idx = (int) str_replace(['carried_', 'carried_activity_'], '', $cleanKey);
                            $targetActId = isset($prevCarriedList[$idx]) ? (string) $prevCarriedList[$idx] : null;
                        }

                        $applyDeduction($targetActId, $amt, $mMonth, $mYear, $payDate);
                    }
                }

                // 2. Process explicit expense_adjustments_json
                $expAdjs = json_decode($c->expense_adjustments_json ?? '{}', true) ?: [];
                if (is_array($expAdjs)) {
                    foreach ($expAdjs as $k => $adj) {
                        $amt = (float) ($adj['amount'] ?? 0);
                        if ($amt <= 0) {
                            continue;
                        }
                        $recordedSum += $amt;

                        $targetId = (string) ($adj['advance_activity_id'] ?? '');
                        $targetActId = null;

                        if ($targetId !== '' && isset($advMap[$targetId])) {
                            $targetActId = $targetId;
                        } elseif (is_numeric($targetId) && (int) $targetId > 0 && isset($advMap[(int) $targetId])) {
                            $targetActId = (string) (int) $targetId;
                        } elseif (strpos($targetId, 'carried_') === 0 || strpos((string) $k, 'carried_') === 0) {
                            $cleanKey = strpos($targetId, 'carried_') === 0 ? $targetId : (string) $k;
                            $idx = (int) str_replace(['carried_', 'carried_expense_'], '', $cleanKey);
                            $targetActId = isset($prevCarriedList[$idx]) ? (string) $prevCarriedList[$idx] : null;
                        }

                        $applyDeduction($targetActId, $amt, $mMonth, $mYear, $payDate);
                    }
                }

                // 3. Fallback for unaccounted monthly total adjustments
                $monthTotalAdj = max(
                    (float) ($c->total_activity_adjustment ?? 0),
                    (float) ($c->total_adjustment ?? 0),
                    (float) ($c->adjust_activity_amount ?? 0)
                );

                $unaccounted = max(0.0, $monthTotalAdj - $recordedSum);
                if ($unaccounted > 0) {
                    $applyDeduction(null, $unaccounted, $mMonth, $mYear, $payDate);
                }

                // 4. Update prevCarriedList from this month's carried_balance_json for subsequent months
                $cb = json_decode($c->carried_balance_json ?? '{}', true) ?: [];
                $prevCarriedList = [];
                foreach (['paid', 'carried', 'ledger', 'expense'] as $cat) {
                    if (!empty($cb[$cat]) && is_array($cb[$cat])) {
                        foreach ($cb[$cat] as $item) {
                            if ($cat === 'ledger' && isset($item['advance_id']) && $item['advance_id'] !== '' && (float) ($item['remaining_amount'] ?? 0) > 0) {
                                // Ledger items carry the 0-based opening slot index, not an activity id.
                                $slot = (int) $item['advance_id'] + 1;
                                $openingId = null;
                                foreach ($advMap as $oid => $oa) {
                                    if (! empty($oa->is_ledger_opening) && (int) ($oa->ledger_slot ?? 0) === $slot) {
                                        $openingId = $oid;
                                        break;
                                    }
                                }
                                $prevCarriedList[] = $openingId;
                                continue;
                            }
                            if (!empty($item['advance_id']) && (float) ($item['remaining_amount'] ?? 0) > 0) {
                                $prevCarriedList[] = $item['advance_id'];
                            }
                        }
                    }
                }
            }

            // Sync reconciled values back to original objects
            foreach ($empRows as $adv) {
                if (isset($advMap[(string) $adv->activity_id])) {
                    $m = $advMap[(string) $adv->activity_id];
                    $adv->reconciled_paid = $m->reconciled_paid;
                    $adv->reconciled_adjusted = $m->reconciled_adjusted;
                    $adv->reconciled_remaining = $m->reconciled_remaining;
                    $adv->adjusted_against_month = $m->adjusted_against_month ?: $adv->adjusted_against_month;
                    $adv->adjustment_date = $m->adjustment_date ?: $adv->adjustment_date;
                    $adv->claim_settlements = $m->claim_settlements;
                }
            }
        }

        return $rows;
    }

    /**
     * Compute KPIs across the full dataset.
     */
    private function calculateKpis(\Illuminate\Support\Collection $records): array
    {
        $totalPaid = 0.0;
        $totalAdjusted = 0.0;
        $totalUnadjusted = 0.0;
        $paidCount = 0;
        $fullyCount = 0;
        $partiallyCount = 0;
        $unadjustedCount = 0;
        $over2MonthsCount = 0;
        $pendingPaymentCount = 0;

        foreach ($records as $r) {
            $isPaid = (strtolower(trim($r->payment_status ?? '')) === 'paid');
            if ($isPaid) {
                $totalPaid += $r->computed_paid_amount;
                $totalAdjusted += $r->computed_adjusted_amount;
                $totalUnadjusted += $r->computed_remaining_balance;
                $paidCount++;

                if ($r->adjustment_status_key === 'fully_adjusted') {
                    $fullyCount++;
                } elseif ($r->adjustment_status_key === 'partially_adjusted') {
                    $partiallyCount++;
                } elseif ($r->adjustment_status_key === 'unadjusted') {
                    $unadjustedCount++;
                }

                if ($r->is_over_2_months) {
                    $over2MonthsCount++;
                }
            } else {
                $pendingPaymentCount++;
            }
        }

        $adjPercentage = $totalPaid > 0 ? round(($totalAdjusted / $totalPaid) * 100, 1) : 0;
        $unadjPercentage = $totalPaid > 0 ? round(($totalUnadjusted / $totalPaid) * 100, 1) : 0;

        return [
            'total_disbursed_amt'    => $totalPaid,
            'total_disbursed_count'  => $paidCount,
            'total_adjusted_amt'     => $totalAdjusted,
            'adjusted_percentage'    => $adjPercentage,
            'total_unadjusted_amt'   => $totalUnadjusted,
            'unadjusted_percentage'  => $unadjPercentage,
            'fully_adjusted_count'   => $fullyCount,
            'partially_adjusted_count' => $partiallyCount,
            'unadjusted_count'       => $unadjustedCount,
            'over_2_months_count'    => $over2MonthsCount,
            'pending_payment_count'  => $pendingPaymentCount,
            'grand_total_records'    => $records->count(),
        ];
    }

    /**
     * Filter records based on selected adjustment status.
     */
    private function applyAdjustmentStatusFilter(\Illuminate\Support\Collection $records, string $statusFilter): \Illuminate\Support\Collection
    {
        if ($statusFilter === 'all' || empty($statusFilter)) {
            return $records;
        }

        return $records->filter(function ($row) use ($statusFilter) {
            if ($statusFilter === 'over_2_months') {
                return $row->is_over_2_months;
            }
            return $row->adjustment_status_key === $statusFilter;
        })->values();
    }
}
