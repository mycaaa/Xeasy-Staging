<?php

namespace App\Http\Controllers;

use App\Helpers\DatabaseSwitcher;
use App\Services\CentralNotificationService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;

class ApproverController extends Controller
{
    private $date;
    private CentralNotificationService $centralNotification;

    public function __construct()
    {
        $this->date = now();
        $this->centralNotification = new CentralNotificationService();
    }

    // ── URL helpers (built lazily so session is available) ──────────────────

    private function baseUrl(): string
    {
        $base      = \App\Support\LegacyApi::baseUrl();
        $companyId = session('company_id', 1);
        return "{$base}/{$companyId}/common.php";
    }

    private function claimUrl(): string
    {
        $base      = \App\Support\LegacyApi::baseUrl();
        $companyId = session('company_id', 1);
        return "{$base}/{$companyId}/claim.php";
    }

    // ── Session helpers ──────────────────────────────────────────────────────

    private function baseParams(): array
    {
        return [
            'empid'   => session('employee_id'),
            'YearId'  => session('year_id'),
            'ComId'   => session('company_id'),
            'tokenid' => session('token_id', env('API_TOKEN_ID', '')),
        ];
    }

    private function expenseDb(): \Illuminate\Database\Connection
    {
        return DB::connection(session('expense_connection', DatabaseSwitcher::getExpenseConnection()));
    }

    private function hrimsDb(): \Illuminate\Database\Connection
    {
        return DB::connection(session('hrims_connection', DatabaseSwitcher::getHrimsConnection()));
    }


    /**
     * Display the approver claim management page (original page, restored)
     */
    public function index(Request $request)
    {
        $status = $request->get('status', 'pending');
        return view('employee.approver', compact('status'));
    }

    /**
     * Display the approver analytics dashboard
     */
    public function dashboard(Request $request)
    {
        $selectedMonth = $request->get('month');
        $selectedMonth = ($selectedMonth && is_numeric($selectedMonth)) ? (int) $selectedMonth : null;
        $dashboardService = new \App\Services\ApproverDashboardService();
        $dashboardData = $dashboardService->getDashboardData($selectedMonth);
        return view('employee.approver-dashboard', compact('dashboardData', 'selectedMonth'));
    }

    /**
     * Get list of pending claims for approval
     */
    public function pendingList(Request $request)
    {
        try {
            $response = \App\Support\LegacyApi::http()
                ->get($this->baseUrl(), array_merge($this->baseParams(), [
                    'value' => 'Team_Pending_Claimlist',
                ]));

            if ($response->successful()) {
                $data = $response->json();
                return response()->json($data);
            }

            return response()->json(['Code' => '500', 'msg' => 'Failed to fetch pending claims'], 500);
        } catch (\Exception $e) {
            Log::error('Error fetching pending claims: ' . $e->getMessage());
            return response()->json(['Code' => '500', 'msg' => 'Server error'], 500);
        }
    }




    /**
     * Get team expense data from API
     */
    public function teamExpense(Request $request)
    {
        try {
            $claimType = $request->get('claim_type', 'ALL');

            $response = \App\Support\LegacyApi::http()
                ->get($this->baseUrl(), array_merge($this->baseParams(), [
                    'value' => 'AllTeamExp',
                ]));

            if ($response->successful()) {
                $data = $response->json();

                if ($data['success'] && isset($data['data']['ExpenseType']['ClaimTypes'])) {
                    $claimTypes = $data['data']['ExpenseType']['ClaimTypes'];

                    // Filter by claim type if selected
                    if ($claimType != 'ALL') {
                        $claimTypes = array_filter($claimTypes, function ($ct) use ($claimType) {
                            return $ct['ClaimId'] == $claimType;
                        });
                        $claimTypes = array_values($claimTypes);
                    }

                    // Format the data for the frontend
                    $formattedData = [];
                    foreach ($claimTypes as $ct) {
                        $monthlyData = [];
                        $totalAmount = floatval($ct['MonthTotal']);

                        // Convert monthly totals to the format expected by frontend
                        if (isset($ct['MonthlyTotals'])) {
                            foreach ($ct['MonthlyTotals'] as $month => $amount) {
                                $monthlyData[intval($month)] = floatval($amount);
                            }
                        }

                        // Only include if total amount > 0 (optional)
                        if ($totalAmount > 0) {
                            $formattedData[] = [
                                'claim_name' => $ct['ClaimName'],
                                'total_amount' => $totalAmount,
                                'months' => $monthlyData
                            ];
                        }
                    }

                    // Get monthly grand totals
                    $monthlyGrandTotals = [];
                    if (isset($data['data']['ExpenseType']['MonthlyGrandTotals'])) {
                        foreach ($data['data']['ExpenseType']['MonthlyGrandTotals'] as $month => $total) {
                            $monthlyGrandTotals[intval($month)] = floatval($total);
                        }
                    }

                    // Get overall grand total
                    $overallGrandTotal = isset($data['data']['ExpenseType']['OverallGrandTotal']) ?
                        floatval($data['data']['ExpenseType']['OverallGrandTotal']) : 0;

                    // Add grand total row to formatted data
                    $formattedData[] = [
                        'claim_name' => 'GRAND TOTAL',
                        'total_amount' => $overallGrandTotal,
                        'months' => $monthlyGrandTotals,
                        'is_grand_total' => true
                    ];

                    return response()->json(['success' => true, 'data' => $formattedData]);
                }
            }

            return response()->json(['success' => false, 'message' => 'Failed to fetch team expense data']);
        } catch (\Exception $e) {
            \Log::error('Team Expense API Error: ' . $e->getMessage());
            return response()->json(['success' => false, 'message' => 'Error loading team expense data: ' . $e->getMessage()], 500);
        }
    }

    /**
     * Get team running KM data
     */
    public function teamRunningKm(Request $request)
    {
        try {
            $userId = $request->get('user_id', 'ALL');
            $yearId = (int) ($request->get('year_id', session('year_id')));

            $teamMembers = $this->getTeamMembers($userId);

            $employees = $this->hrimsDb()
                ->table('hrm_employee')
                ->whereIn('EmployeeID', $teamMembers)
                ->where('EmpStatus', 'A')
                ->select('EmployeeID', DB::raw("CONCAT(EmpCode, ' - ', Fname, ' ', Sname, ' ', Lname) as FullName"))
                ->get();

            $result = [];

            foreach ($employees as $emp) {
                $runningKm = $this->expenseDb()
                    ->table('runningkm')
                    ->where('empid', $emp->EmployeeID)
                    ->where('yearid', $yearId)
                    ->first();

                if ($runningKm) {
                    $monthlyData = [];
                    $totalKm = 0;

                    $months = ['apr', 'may', 'jun', 'jul', 'aug', 'sep', 'oct', 'nov', 'dec', 'jan', 'feb', 'mar'];
                    $monthNumbers = [4, 5, 6, 7, 8, 9, 10, 11, 12, 1, 2, 3];

                    foreach ($months as $index => $month) {
                        $km = ($runningKm->{$month . '_2w_km'} ?? 0) + ($runningKm->{$month . '_4w_km'} ?? 0);
                        $monthlyData[$monthNumbers[$index]] = $km;
                        $totalKm += $km;
                    }

                    if ($totalKm > 0) {
                        $result[] = [
                            'employee_name' => $emp->FullName,
                            'total_km' => $totalKm,
                            'months' => $monthlyData
                        ];
                    }
                }
            }

            return response()->json(['success' => true, 'data' => $result]);
        } catch (\Exception $e) {
            \Log::error('Team Running KM Error: ' . $e->getMessage());
            return response()->json(['success' => false, 'message' => 'Error loading running KM data'], 500);
        }
    }

    /**
     * Get team members for the current approver
     */
    private function getTeamMembers($userId = 'ALL')
    {
        if ($userId != 'ALL') {
            return [$userId];
        }

        try {
            $reporting = $this->hrimsDb()
                ->table('hrm_employee_general')
                ->where('RepEmployeeID', session('employee_id'))
                ->pluck('EmployeeID')
                ->toArray();

            $teamMembers = array_unique(array_merge($reporting, [session('employee_id')]));

            return $teamMembers;
        } catch (\Exception $e) {
            \Log::error('Error getting team members: ' . $e->getMessage());
            // Fallback: return just the current user
            return [session('employee_id')];
        }
    }




    /**
     * Get list of approved claims
     */
    public function approvedList(Request $request)
    {
        try {
            $response = \App\Support\LegacyApi::http()
                ->get($this->baseUrl(), array_merge($this->baseParams(), [
                    'value' => 'Team_Approved_Claimlist',
                ]));

            if ($response->successful()) {
                $data = $response->json();
                return response()->json($data);
            }

            return response()->json(['Code' => '500', 'msg' => 'Failed to fetch approved claims'], 500);
        } catch (\Exception $e) {
            Log::error('Error fetching approved claims: ' . $e->getMessage());
            return response()->json(['Code' => '500', 'msg' => 'Server error'], 500);
        }
    }

    /**
     * Get list of denied claims
     * Note: You may need to create this endpoint or filter from another source
     */
    public function deniedList(Request $request)
    {
        try {
            $response = \App\Support\LegacyApi::http()
                ->get($this->baseUrl(), array_merge($this->baseParams(), [
                    'value' => 'Team_Denied_Claimlist',
                ]));

            if ($response->successful()) {
                $data = $response->json();
                return response()->json($data);
            }

            // Fallback: Return empty list if endpoint doesn't exist
            return response()->json(['Code' => '300', 'msg' => 'No denied claims', 'Denied_list' => []]);
        } catch (\Exception $e) {
            Log::error('Error fetching denied claims: ' . $e->getMessage());
            return response()->json(['Code' => '500', 'msg' => 'Server error'], 500);
        }
    }

    /**
     * Get day-wise details for a specific employee's claim
     */
    public function claimDetails(Request $request)
    {
        try {
            $empid  = $request->get('empid');
            $month  = $request->get('month');
               $comId  = $request->get('comid', session('company_id'));
            $yearId = (int) ($request->get('yearid', session('year_id')));
          $yearId =  $yearId - 2;

            if (!$empid || !$month) {
                return response()->json(['Code' => '400', 'msg' => 'Missing required parameters'], 400);
            }

            $response = \App\Support\LegacyApi::http()
                ->get($this->claimUrl(), array_merge($this->baseParams(), [
                    'value'  => 'Get_DayWise_Details',
                    'empid'  => $empid,
                    'Month'  => $month,
                    'YearId' => $yearId,
                    'ComId'  => $comId,
                ]));

            if ($response->successful()) {
                $data = $response->json();
                return response()->json($data);
            }

            return response()->json(['Code' => '500', 'msg' => 'Failed to fetch claim details'], 500);
        } catch (\Exception $e) {
            Log::error('Error fetching claim details: ' . $e->getMessage());
            return response()->json(['Code' => '500', 'msg' => 'Server error'], 500);
        }
    }

    /**
     * Approve claims for a specific month
     */
    public function approve(Request $request)
    {
        try {
            $empid           = $request->get('empid');
            $month           = $request->get('month');
            $yearId          = (int) ($request->get('yearid', session('year_id')));
            $comId           = $request->get('comid', session('company_id'));
            $approvedAmounts = $request->get('approved_amounts', []);

            if (!$empid || !$month || empty($approvedAmounts)) {
                return response()->json(['Code' => '400', 'msg' => 'Missing required parameters'], 400);
            }

            $results = [];
            foreach ($approvedAmounts as $expId => $amount) {
                $response = \App\Support\LegacyApi::http()
                    ->get($this->claimUrl(), array_merge($this->baseParams(), [
                        'value'      => 'Claim_Approve',
                        'empid'      => $empid,
                        'YearId'     => $yearId,
                        'ComId'      => $comId,
                        'ExpId'      => $expId,
                        'ApproveAmt' => $amount,
                    ]));

                if ($response->successful()) {
                    $results[] = $response->json();
                } else {
                    Log::error("Failed to approve claim: $expId");
                }
            }

            \App\Support\LegacyApi::http()
                ->get($this->claimUrl(), array_merge($this->baseParams(), [
                    'value'  => 'Month_Approve',
                    'empid'  => $empid,
                    'Month'  => $month,
                    'YearId' => $yearId,
                    'ComId'  => $comId,
                ]));

            // Notify Central Notification Service
            try {
                $mfTable = 'y' . $yearId . '_monthexpensefinal';
                $mfRecord = DB::connection(session('expense_connection'))->table($mfTable)
                    ->where('EmployeeID', $empid)->where('Month', $month)->where('YearId', $yearId)->first(['id']);
                $refId = $mfRecord ? (string) $mfRecord->id : "{$empid}{$month}";
                $this->centralNotification->approveAction(
                    'expense_claim',
                    $refId,
                    'Claims approved',
                    ['approved_by' => session('employee_id'), 'month' => $month, 'year_id' => $yearId]
                );
            } catch (\Throwable $e) {
                Log::warning('Central Notification (approve) failed: ' . $e->getMessage());
            }

            return response()->json([
                'Code' => '200',
                'status' => 'success',
                'msg' => 'Claims approved successfully',
                'data' => $results
            ]);
        } catch (\Exception $e) {
            Log::error('Error approving claims: ' . $e->getMessage());
            return response()->json(['Code' => '500', 'msg' => 'Server error'], 500);
        }
    }

    /**
     * Deny a claim
     */
    public function deny(Request $request)
    {
        try {
            $empid  = $request->get('empid');
            $month  = $request->get('month');
            $yearId = (int) ($request->get('yearid', session('year_id')));
            $comId  = $request->get('comid', session('company_id'));
            $remark = $request->get('remark');

            if (!$empid || !$month || !$remark) {
                return response()->json(['Code' => '400', 'msg' => 'Missing required parameters'], 400);
            }

            $response = \App\Support\LegacyApi::http()
                ->get($this->claimUrl(), array_merge($this->baseParams(), [
                    'value'  => 'Claim_Deny',
                    'empid'  => $empid,
                    'Month'  => $month,
                    'YearId' => $yearId,
                    'ComId'  => $comId,
                    'remark' => $remark,
                ]));

            if ($response->successful()) {
                $data = $response->json();

                // Notify Central Notification Service
                try {
                    $mfTable = 'y' . $yearId . '_monthexpensefinal';
                    $mfRecord = DB::connection(session('expense_connection'))->table($mfTable)
                        ->where('EmployeeID', $empid)->where('Month', $month)->where('YearId', $yearId)->first(['id']);
                    $refId = $mfRecord ? (string) $mfRecord->id : "{$empid}{$month}";
                    $this->centralNotification->rejectAction(
                        'expense_claim',
                        $refId,
                        $remark,
                        ['rejected_by' => session('employee_id'), 'month' => $month, 'year_id' => $yearId]
                    );
                } catch (\Throwable $e) {
                    Log::warning('Central Notification (reject) failed: ' . $e->getMessage());
                }

                return response()->json($data);
            }

            return response()->json(['Code' => '500', 'msg' => 'Failed to deny claim'], 500);
        } catch (\Exception $e) {
            Log::error('Error denying claim: ' . $e->getMessage());
            return response()->json(['Code' => '500', 'msg' => 'Server error'], 500);
        }
    }

    /**
     * Get count of pending claims for badge
     */
    private function getPendingCount()
    {
        try {
            $response = \App\Support\LegacyApi::http()
                ->get($this->baseUrl(), array_merge($this->baseParams(), [
                    'value' => 'Team_Pending_Claimlist',
                ]));

            if ($response->successful()) {
                $data = $response->json();
                return isset($data['Pending_list']) ? count($data['Pending_list']) : 0;
            }

            return 0;
        } catch (\Exception $e) {
            Log::error('Error getting pending count: ' . $e->getMessage());
            return 0;
        }
    }

    private function getApprovedCount()
    {
        try {
            $response = \App\Support\LegacyApi::http()
                ->get($this->baseUrl(), array_merge($this->baseParams(), [
                    'value' => 'Team_Approved_Claimlist',
                ]));

            if ($response->successful()) {
                $data = $response->json();
                return isset($data['Approved_list']) ? count($data['Approved_list']) : 0;
            }

            return 0;
        } catch (\Exception $e) {
            Log::error('Error getting approved count: ' . $e->getMessage());
            return 0;
        }
    }

    private function getDeniedCount()
    {
        try {
            $response = \App\Support\LegacyApi::http()
                ->get($this->baseUrl(), array_merge($this->baseParams(), [
                    'value' => 'Team_Denied_Claimlist',
                ]));

            if ($response->successful()) {
                $data = $response->json();
                return isset($data['Denied_list']) ? count($data['Denied_list']) : 0;
            }

            return 0;
        } catch (\Exception $e) {
            Log::error('Error getting denied count: ' . $e->getMessage());
            return 0;
        }
    }



// App\Http\Controllers\ApproverController.php

    /**
     * Show expense details in modal (PDF format)
     */
    public function expenseDetails(Request $request)
    {
        $employeeId = $request->get('empid');
        $month      = $request->get('month');
        $yearId     = (int) ($request->get('yearid', session('year_id')));
        $comId      = $request->get('comid', session('company_id'));
        $nn         = $request->get('nn', 2);
        $tab        = $request->get('tab', 'vnr'); // which company tab triggered the modal

        // Resolve the correct DB connections based on the active tab,
        // not the session (which reflects the user's primary company).
        $expenseConn = match ($tab) {
            'vvnr'    => 'expense_vvnr',
            'nursery' => 'expense_nursery',
            default   => session('expense_connection', DatabaseSwitcher::getExpenseConnection()),
        };
        $hrimsConn = session('hrims_connection', DatabaseSwitcher::getHrimsConnection());

        $expDb   = DB::connection($expenseConn);
        $hrimsDb = DB::connection($hrimsConn);

        // Get month expense details
        $monthExpense = $expDb
            ->table("y{$yearId}_monthexpensefinal")
            ->where('YearId', $yearId)
            ->where('Month', $month)
            ->where('EmployeeID', $employeeId)
            ->first();
// dd( $monthExpense);
        if (!$monthExpense) {
            return view('employee.partials.expense-details', [
                'error' => 'No expense data found'
            ]);
        }

        // Get employee details
        $employee = $hrimsDb
            ->table('hrm_employee_general as g')
            ->join('hrm_employee as e', 'g.EmployeeID', '=', 'e.EmployeeID')
            ->leftJoin('hrm_employee_personal as p', 'g.EmployeeID', '=', 'p.EmployeeID')
            ->leftJoin('core_functions as fun', 'g.EmpFunction', '=', 'fun.id')
            ->leftJoin('core_verticals as ver', 'g.EmpVertical', '=', 'ver.id')
            ->leftJoin('core_departments as dept', 'g.DepartmentId', '=', 'dept.id')
            ->leftJoin('core_sub_department_master as subdept', 'g.SubDepartmentId', '=', 'subdept.id')
            ->leftJoin('core_section as sec', 'g.EmpSection', '=', 'sec.id')
            ->leftJoin('core_designation as desig', 'g.DesigId', '=', 'desig.id')
            ->leftJoin('core_grades as gr', 'g.GradeId', '=', 'gr.id')
            ->leftJoin('core_states as st', 'g.CostCenter', '=', 'st.id')
            ->leftJoin('core_city_village_by_state as vlg', 'g.HqId', '=', 'vlg.id')
            ->leftJoin('core_business_unit as Bu', 'g.BUId', '=', 'Bu.id')
            ->leftJoin('core_zones as Zn', 'g.ZoneId', '=', 'Zn.id')
            ->leftJoin('core_regions as Rg', 'g.RegionId', '=', 'Rg.id')
            ->leftJoin('core_territory as Tr', 'g.TerrId', '=', 'Tr.id')
            ->where('e.EmployeeID', $employeeId)
            ->select(
                'e.EmployeeID',
                'e.EmpCode',
                DB::raw("CONCAT_WS(' ', e.Fname, e.Sname, e.Lname) as Name"),
                'g.HqId',
                'g.EmpVertical',
                'g.DepartmentId',
                'g.TerrId',
                'g.GradeId',
                'g.DesigId',
                'dept.department_name',
                'ver.vertical_name',
                'desig.designation_name',
                'gr.grade_name',
                'st.state_name',
                'vlg.city_village_name',
                'Zn.zone_name',
                'Rg.region_name',
                'Tr.territory_name'
            )
            ->first();

        // Get all claim types for TRAVEL group (cgId=1)
        $travelClaimTypes = $expDb
            ->table('claimtype')
            ->where('cgId', 1)
            ->whereIn('ClaimStatus', ['A', 'B'])
            ->orderBy('ClaimCode', 'asc')
            ->get();

        $sqry = ($nn == 2) ? 'FilledOkay = 1' : '1=1';

        // Filter claim types to only show those that have data for this employee/month
        $activeClaimTypes = [];
        $claimTypeIds = [];

        foreach ($travelClaimTypes as $ct) {
            if ($ct->ClaimId == 7) {
                // Check if there's any vehicle data (2W or 4W)
                $vehicleDataExists = $expDb
                    ->table("y{$yearId}_expenseclaims as clm")
                    ->join("y{$yearId}_g1_expensefilldata as gd", 'clm.ExpId', '=', 'gd.ExpId')
                    ->where('clm.ClaimId', 7)
                    ->whereRaw($sqry)
                    ->where('clm.ClaimStatus', '!=', 'Deactivate')
                    ->where('clm.ClaimStatus', '!=', 'Draft')
                    ->where('clm.FilledBy', '>', 0)
                    ->where('clm.CrBy', $employeeId)
                    ->where('clm.ClaimYearId', $yearId)
                    ->where('clm.ClaimMonth', $month)
                    ->exists();

                if ($vehicleDataExists) {
                    // Add both 2W and 4W if vehicle data exists
                    $activeClaimTypes[] = ['id' => 7, 'code' => '2W', 'type' => 'vehicle_2w'];
                    $activeClaimTypes[] = ['id' => 7, 'code' => '4W', 'type' => 'vehicle_4w'];
                    $claimTypeIds[] = 7;
                }
            } else {
                // Check if this claim type has any data
                $hasData = $expDb
                    ->table("y{$yearId}_expenseclaims")
                    ->where('ClaimId', $ct->ClaimId)
                    ->whereRaw($sqry)
                    ->where('ClaimStatus', '!=', 'Deactivate')
                    ->where('ClaimStatus', '!=', 'Draft')
                    ->where('FilledBy', '>', 0)
                    ->where('CrBy', $employeeId)
                    ->where('ClaimYearId', $yearId)
                    ->where('ClaimMonth', $month)
                    ->exists();

                if ($hasData) {
                    $activeClaimTypes[] = [
                        'id' => $ct->ClaimId,
                        'code' => $ct->ClaimCode,
                        'type' => 'normal'
                    ];
                    $claimTypeIds[] = $ct->ClaimId;
                }
            }
        }

        // If no active claim types found, show all (or handle gracefully)
        if (empty($activeClaimTypes)) {
            $activeClaimTypes = [];
            foreach ($travelClaimTypes as $ct) {
                if ($ct->ClaimId == 7) {
                    $activeClaimTypes[] = ['id' => 7, 'code' => '2W', 'type' => 'vehicle_2w'];
                    $activeClaimTypes[] = ['id' => 7, 'code' => '4W', 'type' => 'vehicle_4w'];
                    $claimTypeIds[] = 7;
                } else {
                    $activeClaimTypes[] = [
                        'id' => $ct->ClaimId,
                        'code' => $ct->ClaimCode,
                        'type' => 'normal'
                    ];
                    $claimTypeIds[] = $ct->ClaimId;
                }
            }
        }

        // Get day-wise details grouped by date
        $details = $expDb
            ->table("y{$yearId}_expenseclaims as clm")
            ->leftJoin("y{$yearId}_24_wheeler_entry as wh", 'clm.ExpId', '=', 'wh.ExpId')
            ->whereIn('clm.ClaimId', $claimTypeIds)
            ->whereRaw($sqry)
            ->where('clm.ClaimStatus', '!=', 'Deactivate')
            ->where('clm.ClaimStatus', '!=', 'Draft')
            ->where('clm.FilledBy', '>', 0)
            ->where('clm.CrBy', $employeeId)
            ->where('clm.ClaimYearId', $yearId)
            ->where('clm.ClaimMonth', $month)
            ->select(
                DB::raw('MAX(odomtr_closing) as odomtr_closing'),
                DB::raw('MAX(odomtr_opening) as odomtr_opening'),
                'clm.BillDate',
                'clm.Remark',
                'wh.DistTraOpen',
                'wh.DistTraClose',
                'wh.Totalkm',
                DB::raw('SUM(clm.FilledTAmt) as Tot2Fill')
            )
            ->groupBy('clm.BillDate', 'clm.Remark', 'wh.DistTraOpen', 'wh.DistTraClose', 'wh.Totalkm')
            ->orderBy('clm.BillDate', 'asc')
            ->get();

        // Calculate amounts for each claim type per date and totals
        $amountsByDate = [];
        $typeTotals = [];

        // Initialize totals array
        foreach ($activeClaimTypes as $header) {
            if ($header['type'] == 'vehicle_2w') {
                $typeTotals['2W'] = 0;
            } elseif ($header['type'] == 'vehicle_4w') {
                $typeTotals['4W'] = 0;
            } else {
                $typeTotals[$header['code']] = 0;
            }
        }

        // For each date, calculate amounts for each claim type
        foreach ($details as $detail) {
            $billDate = $detail->BillDate;
            $amountsByDate[$billDate] = [];

            foreach ($activeClaimTypes as $header) {
                $amount = 0;

                if ($header['type'] == 'vehicle_2w') {
                    $amount = $expDb
                        ->table("y{$yearId}_expenseclaims as clm")
                        ->join("y{$yearId}_g1_expensefilldata as gd", 'clm.ExpId', '=', 'gd.ExpId')
                        ->where('clm.ClaimId', 7)
                        ->where('clm.BillDate', $billDate)
                        ->where('clm.CrBy', $employeeId)
                        ->where('clm.ClaimYearId', $yearId)
                        ->where('clm.ClaimMonth', $month)
                        ->where('gd.VehicleType', 2)
                        ->whereRaw($sqry)
                        ->sum('clm.FilledTAmt');
                    $typeTotals['2W'] += $amount;
                } elseif ($header['type'] == 'vehicle_4w') {
                    $amount = $expDb
                        ->table("y{$yearId}_expenseclaims as clm")
                        ->join("y{$yearId}_g1_expensefilldata as gd", 'clm.ExpId', '=', 'gd.ExpId')
                        ->where('clm.ClaimId', 7)
                        ->where('clm.BillDate', $billDate)
                        ->where('clm.CrBy', $employeeId)
                        ->where('clm.ClaimYearId', $yearId)
                        ->where('clm.ClaimMonth', $month)
                        ->where('gd.VehicleType', 4)
                        ->whereRaw($sqry)
                        ->sum('clm.FilledTAmt');
                    $typeTotals['4W'] += $amount;
                } else {
                    $amount = $expDb
                        ->table("y{$yearId}_expenseclaims")
                        ->where('ClaimId', $header['id'])
                        ->where('BillDate', $billDate)
                        ->where('CrBy', $employeeId)
                        ->where('ClaimYearId', $yearId)
                        ->where('ClaimMonth', $month)
                        ->whereRaw($sqry)
                        ->sum('FilledTAmt');
                    $typeTotals[$header['code']] += $amount;
                }

                $amountsByDate[$billDate][$header['code']] = $amount;
            }
        }

        // Calculate total amount
        $totalAmount = $expDb
            ->table("y{$yearId}_expenseclaims")
            ->whereIn('ClaimId', $claimTypeIds)
            ->whereRaw($sqry)
            ->where('ClaimStatus', '!=', 'Deactivate')
            ->where('ClaimStatus', '!=', 'Draft')
            ->where('FilledBy', '>', 0)
            ->where('CrBy', $employeeId)
            ->where('ClaimYearId', $yearId)
            ->where('ClaimMonth', $month)
            ->sum('FilledTAmt');

        // Calculate total KM
        $totKmAmt = $expDb
            ->table("y{$yearId}_expenseclaims as clm")
            ->join("y{$yearId}_24_wheeler_entry as gd", 'clm.ExpId', '=', 'gd.ExpId')
            ->where('clm.ClaimId', 7)
            ->whereRaw($sqry)
            ->where('clm.ClaimStatus', '!=', 'Deactivate')
            ->where('clm.ClaimStatus', '!=', 'Draft')
            ->where('clm.FilledBy', '>', 0)
            ->where('clm.CrBy', $employeeId)
            ->where('clm.ClaimYearId', $yearId)
            ->where('clm.ClaimMonth', $month)
            ->sum('gd.Totalkm');

        // Get financial year
        $financialYear = $expDb
            ->table('financialyear')
            ->where('YearId', $yearId)
            ->first();

        $group = (object) [
            'cgName' => 'Travel',
            'cgCode' => 'TRV'
        ];

        return view('employee.partials.expense-details', compact(
            'monthExpense',
            'employee',
            'details',
            'activeClaimTypes',
            'amountsByDate',
            'typeTotals',
            'totalAmount',
            'totKmAmt',
            'group',
            'financialYear',
            'employeeId',
            'month',
            'yearId',
            'comId'
        ));
    }






    /**
     * Update the token (if needed for different users)
     */
    public function updateToken(Request $request)
    {
        try {
            $newToken = $request->get('token');
            if ($newToken) {
                // Store in session or database based on your requirements
                session(['approver_token' => $newToken]);
                return response()->json(['status' => 'success', 'msg' => 'Token updated successfully']);
            }

            return response()->json(['status' => 'error', 'msg' => 'No token provided'], 400);
        } catch (\Exception $e) {
            Log::error('Error updating token: ' . $e->getMessage());
            return response()->json(['status' => 'error', 'msg' => 'Server error'], 500);
        }
    }
}
