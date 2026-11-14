<?php

namespace App\Http\Controllers\Api\Advance;

use App\Http\Controllers\Controller;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class HodAdvanceReportController extends Controller
{
    /** ACCESS-HOD-01: HODs only; the report runs for the logged-in employee (staff may pick one). */
    public function __construct()
    {
        $this->middleware(function ($request, $next) {
            // TEAM-ADV-01: HODs, and reporting managers for their own team (the report runs on the caller's tree)
            if (! \App\Support\HodAccess::allowedTeamReport()) {
                return response()->json(['success' => false, 'message' => 'The team advance report is for managers with a team.'], 403);
            }
            if ($request->has('employee_id')) {
                $request->merge(['employee_id' => \App\Support\HodAccess::subject($request->input('employee_id'))]);
            }

            return $next($request);
        });
    }

    /**
     * Get employee's access level based on hierarchy
     */
    private function getEmployeeAccessLevel($employeeId)
    {
        try {
            // Static list of employee IDs with "all" level access
            $allLevelEmployeeIds = \App\Support\HodAccess::ALL_LEVEL;

            if (in_array($employeeId, $allLevelEmployeeIds)) {
                return 'all';
            }

            $employee = DB::connection('hrims')
                ->table('hrm_employee_general')
                ->where('EmployeeID', $employeeId)
                ->first(['BUId', 'ZoneId', 'RegionId', 'TerrId']);

            if (! $employee) {
                return 'none';
            }

            if ($employee->TerrId > 0) {
                return 'territory';
            } elseif ($employee->RegionId > 0 && $employee->TerrId == 0) {
                return 'region';
            } elseif ($employee->ZoneId > 0 && $employee->RegionId == 0 && $employee->TerrId == 0) {
                return 'zone';
            } elseif ($employee->BUId > 0 && $employee->ZoneId == 0 && $employee->RegionId == 0 && $employee->TerrId == 0) {
                return 'bu';
            } else {
                return 'none';
            }
        } catch (Exception $e) {
            \Log::error('Error getting employee access level: '.$e->getMessage());

            return 'none';
        }
    }

    /**
     * Recursively fetch all employees reporting to a manager
     */
    private function fetchEmployeesRecursive($managerId)
    {
        try {
            $employees = collect();

            // Get direct reports from hrm_employee_general table using RepEmployeeID
            $directReports = DB::connection('hrims')
                ->table('hrm_employee_general as eg')
                ->join('hrm_employee as e', 'eg.EmployeeID', '=', 'e.EmployeeID')
                ->where('eg.RepEmployeeID', $managerId)
                ->where('e.EmpStatus', 'A')
                ->select('eg.EmployeeID')
                ->get();

            foreach ($directReports as $employee) {
                // Add current employee
                $employees->push($employee->EmployeeID);

                // Recursively get their reports
                $subEmployees = $this->fetchEmployeesRecursive($employee->EmployeeID);
                $employees = $employees->merge($subEmployees);
            }

            return $employees->unique()->values()->toArray();
        } catch (Exception $e) {
            \Log::error('Error in fetchEmployeesRecursive: '.$e->getMessage());

            return [];
        }
    }

    /**
     * Get team members based on employee hierarchy
     */
    private function getTeamMembers(Request $request)
    {
        try {
            $employeeId = $request->get('employee_id');

            if (! $employeeId) {
                return [];
            }

            // Get all employees recursively under this employee
            $teamMembers = $this->fetchEmployeesRecursive($employeeId);

            // Add the employee themselves
            array_unshift($teamMembers, $employeeId);

            return array_unique($teamMembers);
        } catch (Exception $e) {
            \Log::error('Error fetching team members: '.$e->getMessage());

            return [];
        }
    }

    private function getEmployeeDetails($employeeId)
    {
        try {
            $employee = DB::connection('hrims')->table('hrm_employee as e')->leftJoin('hrm_employee_general as eg', 'e.EmployeeID', '=', 'eg.EmployeeID')->leftJoin('core_departments as d', 'eg.DepartmentId', '=', 'd.id')->leftJoin('core_designation as des', 'eg.DesigId', '=', 'des.id')->leftJoin('core_business_unit as bu', 'eg.BUId', '=', 'bu.id')->leftJoin('core_zones as z', 'eg.ZoneId', '=', 'z.id')->leftJoin('core_regions as r', 'eg.RegionId', '=', 'r.id')->leftJoin('core_region_territory as rt', 'eg.TerrId', '=', 'rt.territory_id')->where('e.EmployeeID', $employeeId)->select(['e.EmployeeID', DB::raw("CONCAT(COALESCE(e.Fname, ''), ' ', COALESCE(e.Sname, ''), ' ', COALESCE(e.Lname, '')) as EmployeeName"), 'eg.EC as EmployeeCode', 'eg.EmailId_Vnr', 'd.department_name as DepartmentName', 'des.designation_name as Designation', 'eg.BUId', 'bu.business_unit_name as BUName', 'bu.business_unit_code as BUCode', 'eg.ZoneId', 'z.zone_name as ZoneName', 'z.zone_code as ZoneCode', 'eg.RegionId', 'r.region_name as RegionName', 'r.region_code as RegionCode', 'eg.TerrId', 'rt.name as TerritoryName', 'rt.territory_id as TerritoryCode'])->first();
            if (! $employee) {
                return ['EmployeeID' => $employeeId, 'EmployeeName' => 'N/A', 'EmployeeCode' => $employeeId, 'EmailId_Vnr' => 'N/A', 'DepartmentName' => 'N/A', 'Designation' => 'N/A', 'BUId' => null, 'BUName' => null, 'BUCode' => null, 'ZoneId' => null, 'ZoneName' => null, 'ZoneCode' => null, 'RegionId' => null, 'RegionName' => null, 'RegionCode' => null, 'TerrId' => null, 'TerritoryName' => null, 'TerritoryCode' => null];
            }

            return ['EmployeeID' => $employee->EmployeeID, 'EmployeeName' => trim($employee->EmployeeName), 'EmployeeCode' => $employee->EmployeeCode ?? $employeeId, 'EmailId_Vnr' => $employee->EmailId_Vnr ?? 'N/A', 'DepartmentName' => $employee->DepartmentName ?? 'N/A', 'Designation' => $employee->Designation ?? 'N/A', 'BUId' => $employee->BUId, 'BUName' => $employee->BUName, 'BUCode' => $employee->BUCode, 'ZoneId' => $employee->ZoneId, 'ZoneName' => $employee->ZoneName, 'ZoneCode' => $employee->ZoneCode, 'RegionId' => $employee->RegionId, 'RegionName' => $employee->RegionName, 'RegionCode' => $employee->RegionCode, 'TerrId' => $employee->TerrId, 'TerritoryName' => $employee->TerritoryName, 'TerritoryCode' => $employee->TerritoryCode];
        } catch (Exception $e) {
            \Log::error('Error fetching employee details: '.$e->getMessage());

            return ['EmployeeID' => $employeeId, 'EmployeeName' => 'N/A', 'EmployeeCode' => $employeeId, 'EmailId_Vnr' => 'N/A', 'DepartmentName' => 'N/A', 'Designation' => 'N/A', 'BUId' => null, 'BUName' => null, 'BUCode' => null, 'ZoneId' => null, 'ZoneName' => null, 'ZoneCode' => null, 'RegionId' => null, 'RegionName' => null, 'RegionCode' => null, 'TerrId' => null, 'TerritoryName' => null, 'TerritoryCode' => null];
        }
    }

    public function getTeamMembersList(Request $request)
    {
        try {
            $employeeId = $request->get('employee_id');

            if (! $employeeId) {
                return $this->jsonError('Employee ID is required');
            }

            // Get access level
            $accessLevel = $this->getEmployeeAccessLevel($employeeId);

            $teamMembers = $this->getTeamMembers($request);

            if (empty($teamMembers)) {
                return $this->jsonSuccess([
                    'access_level' => $accessLevel,
                    'team_members' => [],
                ], 'No team members found');
            }

            // TEAM-ADV-01 (14 Nov 2026): list the whole team (the list showed only members who already had an advance,
            // so a new team showed just "All Team Members"); those with advances come first, flagged has_advance
            $withAdvances = array_flip(array_map('intval', DB::table('advance_request_employees')
                ->whereIn('employee_id', $teamMembers)->distinct()->pluck('employee_id')->toArray()));
            $teamMembersData = [];
            foreach (array_chunk(array_values($teamMembers), 500) as $chunk) {
                $rows = DB::connection('hrims')->table('hrm_employee as e')->leftJoin('hrm_employee_general as eg', 'e.EmployeeID', '=', 'eg.EmployeeID')->leftJoin('core_departments as d', 'eg.DepartmentId', '=', 'd.id')->leftJoin('core_designation as des', 'eg.DesigId', '=', 'des.id')->leftJoin('core_business_unit as bu', 'eg.BUId', '=', 'bu.id')->leftJoin('core_zones as z', 'eg.ZoneId', '=', 'z.id')->leftJoin('core_regions as r', 'eg.RegionId', '=', 'r.id')->leftJoin('core_region_territory as rt', 'eg.TerrId', '=', 'rt.territory_id')
                    ->whereIn('e.EmployeeID', $chunk)
                    ->select(['e.EmployeeID', DB::raw("TRIM(CONCAT(COALESCE(e.Fname, ''), ' ', COALESCE(e.Sname, ''), ' ', COALESCE(e.Lname, ''))) as EmployeeName"), 'eg.EC as EmployeeCode', 'eg.EmailId_Vnr', 'd.department_name as DepartmentName', 'des.designation_name as Designation', 'eg.BUId', 'bu.business_unit_name as BUName', 'bu.business_unit_code as BUCode', 'eg.ZoneId', 'z.zone_name as ZoneName', 'z.zone_code as ZoneCode', 'eg.RegionId', 'r.region_name as RegionName', 'r.region_code as RegionCode', 'eg.TerrId', 'rt.name as TerritoryName', 'rt.territory_id as TerritoryCode'])
                    ->get();
                foreach ($rows as $r) {
                    $row = (array) $r;
                    $row['EmployeeCode'] = $row['EmployeeCode'] ?? $row['EmployeeID'];
                    $row['EmployeeName'] = preg_replace('/\s+/', ' ', (string) $row['EmployeeName']);
                    $row['has_advance'] = isset($withAdvances[(int) $r->EmployeeID]);
                    $teamMembersData[] = $row;
                }
            }
            usort($teamMembersData, fn ($a, $b) => [! $a['has_advance'], $a['EmployeeName']] <=> [! $b['has_advance'], $b['EmployeeName']]);

            return $this->jsonSuccess([
                'access_level' => $accessLevel,
                'team_members' => $teamMembersData,
            ], 'Team members with advances retrieved successfully');
        } catch (Exception $e) {
            \Log::error('Error in getTeamMembersList: '.$e->getMessage());

            return $this->jsonError('Failed to retrieve team members: '.$e->getMessage(), 500);
        }
    }

    public function consolidatedReport(Request $request)
    {
        try {
            $employeeId = $request->get('employee_id');
            $month = $request->get('month');
            $yearId = ($request->filled('year_id') ? (int) $request->get('year_id') : null);
            $specificEmployeeId = $request->get('specific_employee_id');

            // Hierarchy filters
            $buId = $request->get('bu_id');
            $zoneId = $request->get('zone_id');
            $regionId = $request->get('region_id');
            $terrId = $request->get('terr_id');

            if (! $employeeId) {
                return $this->jsonError('Employee ID is required');
            }

            // Get access level
            $accessLevel = $this->getEmployeeAccessLevel($employeeId);

            $teamMembers = $this->getTeamMembers($request);

            if (empty($teamMembers)) {
                return $this->jsonSuccess([
                    'access_level' => $accessLevel,
                    'grand_total' => 0,
                    'data' => [],
                ], 'No team members found');
            }

            // Apply hierarchy filters to team members
            if ($buId || $zoneId || $regionId || $terrId) {
                $hierarchyQuery = DB::connection('hrims')
                    ->table('hrm_employee_general')
                    ->whereIn('EmployeeID', $teamMembers);

                if ($buId) {
                    $hierarchyQuery->where('BUId', $buId);
                }
                if ($zoneId) {
                    $hierarchyQuery->where('ZoneId', $zoneId);
                }
                if ($regionId) {
                    $hierarchyQuery->where('RegionId', $regionId);
                }
                if ($terrId) {
                    $hierarchyQuery->where('TerrId', $terrId);
                }

                $filteredTeamMembers = $hierarchyQuery->pluck('EmployeeID')->toArray();

                if (empty($filteredTeamMembers)) {
                    return $this->jsonSuccess([
                        'access_level' => $accessLevel,
                        'grand_total' => 0,
                        'data' => [],
                    ], 'No team members found matching hierarchy filters');
                }

                $teamMembers = $filteredTeamMembers;
            }

            // Build query for paid activities
            $query = DB::table('advance_requests as ar')
                ->join('advance_request_employees as are', 'ar.id', '=', 'are.advance_request_id')
                ->join('advance_request_activities as ara', 'are.id', '=', 'ara.advance_request_employee_id')
                ->whereIn('are.employee_id', $teamMembers)
                ->where('ara.payment_status', 'Paid')
                ->whereNotNull('ara.payment_date');

            if ($yearId) {
                $query->where('ar.year_id', $yearId);
            }

            if ($month) {
                $query->where('ar.month', $month);
            }

            if ($specificEmployeeId) {
                $query->where('are.employee_id', $specificEmployeeId);
            }

            $paidActivities = $query->select([
                'ar.id as request_id',
                'ar.activity_name_id',
                'ar.month',
                'ar.current_status',
                'ar.last_submitted_at',
                'ar.created_by',
                'ar.submission_no',
                'are.employee_id',
                'ara.id as activity_id',
                'ara.activity_type_id',
                'ara.activity_crops_id',
                'ara.amount as requested_amount',
                'ara.finance_approved_amount',
                'ara.finance_status',
                'ara.finance_date',
                'ara.finance_remark',
                'ara.payment_amount',
                'ara.payment_status',
                'ara.payment_date',
                'ara.payment_remark',
            ])->get();

            if ($paidActivities->isEmpty()) {
                return $this->jsonSuccess([
                    'access_level' => $accessLevel,
                    'grand_total' => 0,
                    'data' => [],
                ], 'No paid advances found');
            }

            // Hierarchical structure: activity_name -> month -> submitted_by -> employee -> requests
            $reportData = [];
            $grandTotal = 0;

            foreach ($paidActivities as $activity) {
                $activityNameId = $activity->activity_name_id;

                // Get activity name
                $activityName = 'Expense';
                if ($activityNameId) {
                    $activityNameRecord = DB::table('claimtype')
                        ->where('ClaimId', $activityNameId)
                        ->where('ClaimStatus', 'A')
                        ->first();
                    $activityName = $activityNameRecord->ClaimName ?? 'Expense';
                }

                // Get month name
                $monthNames = ['', 'Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];
                $monthName = $monthNames[$activity->month] ?? 'Unknown';

                // Get activity type name
                $activityTypeName = 'N/A';
                if ($activity->activity_type_id) {
                    $activityType = DB::table('adv_activity_types')
                        ->where('id', $activity->activity_type_id)
                        ->first();
                    $activityTypeName = $activityType->type_name ?? 'N/A';
                }

                // Get crop names
                $cropNames = [];
                if ($activity->activity_crops_id) {
                    $cropIds = is_string($activity->activity_crops_id)
                        ? json_decode($activity->activity_crops_id, true)
                        : $activity->activity_crops_id;

                    if (is_array($cropIds) && ! empty($cropIds)) {
                        $crops = DB::connection('hrims')
                            ->table('core_crops')
                            ->whereIn('id', $cropIds)
                            ->pluck('crop_name');
                        $cropNames = $crops->toArray();
                    }
                }

                // Get approval details for this activity
                $approvalDetails = [];
                try {
                    $approvals = DB::table('advance_activity_approvals')
                        ->where('advance_request_activity_id', $activity->activity_id)
                        ->where('submission_no', $activity->submission_no)
                        ->orderBy('approval_level')
                        ->get();

                    foreach ($approvals as $approval) {
                        $approverDetails = $this->getEmployeeDetails($approval->approver_id);
                        $approvalDetails[] = [
                            'approval_level' => $approval->approval_level,
                            'approver_id' => $approval->approver_id,
                            'approver_name' => $approverDetails['EmployeeName'] ?? 'N/A',
                            'approver_status' => $approval->approver_status,
                            'approver_amount' => $approval->approver_amount ? (float) $approval->approver_amount : null,
                            'approver_date' => $approval->approver_date,
                            'approver_remark' => $approval->approver_remark,
                        ];
                    }
                } catch (\Exception $e) {
                    \Log::warning('Error fetching approval details: '.$e->getMessage());
                }

                // Get employee details
                $employeeDetails = $this->getEmployeeDetails($activity->employee_id);

                // Get submitted by details
                $submittedByDetails = $this->getEmployeeDetails($activity->created_by);
                $submittedById = $activity->created_by;
                $submittedByName = $submittedByDetails['EmployeeName'];

                $paymentAmount = (float) $activity->payment_amount;
                $grandTotal += $paymentAmount;

                // Initialize structure
                if (! isset($reportData[$activityNameId])) {
                    $reportData[$activityNameId] = [
                        'activity_name' => $activityName,
                        'grand_total' => 0,
                        'months' => [],
                    ];
                }

                if (! isset($reportData[$activityNameId]['months'][$monthName])) {
                    $reportData[$activityNameId]['months'][$monthName] = [
                        'month_total' => 0,
                        'submitted_by' => [],
                    ];
                }

                if (! isset($reportData[$activityNameId]['months'][$monthName]['submitted_by'][$submittedById])) {
                    $submittedByName = ($submittedByName == 'N/A' || empty($submittedByName)) ? 'By System' : $submittedByName;
                    $reportData[$activityNameId]['months'][$monthName]['submitted_by'][$submittedById] = [
                        'submitted_by_id' => $submittedById,
                        'submitted_by_name' => $submittedByName,
                        'submitted_by_code' => $submittedByDetails['EmployeeCode'] ?? 0,
                        'submitted_by_total' => 0,
                        'employees' => [],
                    ];
                }

                $empId = $activity->employee_id;
                if (! isset($reportData[$activityNameId]['months'][$monthName]['submitted_by'][$submittedById]['employees'][$empId])) {
                    $reportData[$activityNameId]['months'][$monthName]['submitted_by'][$submittedById]['employees'][$empId] = [
                        'employee_id' => $empId,
                        'employee_name' => $employeeDetails['EmployeeName'],
                        'employee_code' => $employeeDetails['EmployeeCode'],
                        'department' => $employeeDetails['DepartmentName'],
                        'designation' => $employeeDetails['Designation'],
                        'hierarchy' => [
                            'business_unit' => [
                                'id' => $employeeDetails['BUId'],
                                'name' => $employeeDetails['BUName'],
                                'code' => $employeeDetails['BUCode'],
                            ],
                            'zone' => [
                                'id' => $employeeDetails['ZoneId'],
                                'name' => $employeeDetails['ZoneName'],
                                'code' => $employeeDetails['ZoneCode'],
                            ],
                            'region' => [
                                'id' => $employeeDetails['RegionId'],
                                'name' => $employeeDetails['RegionName'],
                                'code' => $employeeDetails['RegionCode'],
                            ],
                            'territory' => [
                                'id' => $employeeDetails['TerrId'],
                                'name' => $employeeDetails['TerritoryName'],
                                'code' => $employeeDetails['TerritoryCode'],
                            ],
                        ],
                        'advance_requests' => [],
                        'employee_total' => 0,
                    ];
                }

                $reqId = $activity->request_id;
                if (! isset($reportData[$activityNameId]['months'][$monthName]['submitted_by'][$submittedById]['employees'][$empId]['advance_requests'][$reqId])) {
                    $reportData[$activityNameId]['months'][$monthName]['submitted_by'][$submittedById]['employees'][$empId]['advance_requests'][$reqId] = [
                        'request_id' => $reqId,
                        'status' => $activity->current_status,
                        'submitted_at' => $activity->last_submitted_at,
                        'total_amount' => 0,
                        'activities' => [],
                    ];
                }

                // Add activity
                $reportData[$activityNameId]['months'][$monthName]['submitted_by'][$submittedById]['employees'][$empId]['advance_requests'][$reqId]['activities'][] = [
                    'activity_type' => $activityTypeName,
                    'crops' => $cropNames,
                    'amount' => $paymentAmount,
                    'payment_status' => $activity->payment_status,
                    'approvals' => $approvalDetails,
                    'finance' => [
                        'approved_amount' => $activity->finance_approved_amount ? (float) $activity->finance_approved_amount : null,
                        'status' => $activity->finance_status,
                        'date' => $activity->finance_date,
                        'remark' => $activity->finance_remark,
                    ],
                    'payment' => [
                        'amount' => $paymentAmount,
                        'status' => $activity->payment_status,
                        'date' => $activity->payment_date,
                        'remark' => $activity->payment_remark,
                    ],
                ];

                // Update totals
                $reportData[$activityNameId]['months'][$monthName]['submitted_by'][$submittedById]['employees'][$empId]['advance_requests'][$reqId]['total_amount'] += $paymentAmount;
                $reportData[$activityNameId]['months'][$monthName]['submitted_by'][$submittedById]['employees'][$empId]['employee_total'] += $paymentAmount;
                $reportData[$activityNameId]['months'][$monthName]['submitted_by'][$submittedById]['submitted_by_total'] += $paymentAmount;
                $reportData[$activityNameId]['months'][$monthName]['month_total'] += $paymentAmount;
                $reportData[$activityNameId]['grand_total'] += $paymentAmount;
            }

            // Convert nested arrays to indexed arrays
            foreach ($reportData as &$activityData) {
                foreach ($activityData['months'] as &$monthData) {
                    $monthData['submitted_by'] = array_values($monthData['submitted_by']);
                    foreach ($monthData['submitted_by'] as &$submittedByData) {
                        $submittedByData['employees'] = array_values($submittedByData['employees']);
                        foreach ($submittedByData['employees'] as &$employee) {
                            $employee['advance_requests'] = array_values($employee['advance_requests']);
                        }
                    }
                }
            }

            return response()->json([
                'success' => true,
                'access_level' => $accessLevel,
                'grand_total' => $grandTotal,
                'data' => $reportData,
            ]);
        } catch (Exception $e) {
            \Log::error('Error in consolidatedReport: '.$e->getMessage(), ['trace' => $e->getTraceAsString()]);

            return $this->jsonError('Failed to retrieve consolidated report: '.$e->getMessage(), 500);
        }
    }

    public function getBusinessUnits(Request $request)
    {
        try {
            $employeeId = $request->get('employee_id');
            $search = $request->get('search');


            $query = DB::connection('hrims')->table('core_business_unit')->where('is_active', '1');

            // If employee_id provided, filter by their assigned BU
            if ($employeeId) {
                $empGeneral = DB::connection('hrims')
                    ->table('hrm_employee_general')
                    ->where('EmployeeID', $employeeId)
                    ->first(['BUId']);

                if ($empGeneral && $empGeneral->BUId > 0) {
                    $query->where('id', $empGeneral->BUId);
                }
            }

            if ($search) {
                $query->where(function ($q) use ($search) {
                    $q
                        ->where('business_unit_name', 'LIKE', "%{$search}%")
                        ->orWhere('business_unit_code', 'LIKE', "%{$search}%")
                        ->orWhere('numeric_code', 'LIKE', "%{$search}%");
                });
            }

            $businessUnits = $query
                ->select(['id', 'business_unit_name as name', 'business_unit_code as code', 'numeric_code'])
                ->orderBy('business_unit_name')

                ->get();

            return $this->jsonSuccess($businessUnits, 'Business units retrieved successfully');
        } catch (Exception $e) {
            \Log::error('Error fetching business units: '.$e->getMessage());

            return $this->jsonError('Failed to retrieve business units: '.$e->getMessage(), 500);
        }
    }

    public function getZones(Request $request)
    {
        try {
            $employeeId = $request->get('employee_id');
            $search = $request->get('search');

            $businessUnitId = $request->get('business_unit_id');

            $query = DB::connection('hrims')->table('core_zones as z')->where('z.is_active', '1');

            // If employee_id provided, filter by their assigned Zone
            if ($employeeId) {
                $empGeneral = DB::connection('hrims')
                    ->table('hrm_employee_general')
                    ->where('EmployeeID', $employeeId)
                    ->first(['BUId', 'ZoneId']);

                if ($empGeneral) {
                    // If employee has a zone assigned, filter by it
                    if ($empGeneral->ZoneId > 0) {
                        $query->where('z.id', $empGeneral->ZoneId);
                    }
                    // Otherwise filter by BU if provided
                    elseif ($businessUnitId) {
                        $query
                            ->join('core_bu_zone_mapping as bzm', 'z.id', '=', 'bzm.zone_id')
                            ->where('bzm.business_unit_id', $businessUnitId);
                    }
                    // Or use employee's BU
                    elseif ($empGeneral->BUId > 0) {
                        $query
                            ->join('core_bu_zone_mapping as bzm', 'z.id', '=', 'bzm.zone_id')
                            ->where('bzm.business_unit_id', $empGeneral->BUId);
                    }
                }
            }
            // No employee_id, use business_unit_id if provided
            elseif ($businessUnitId) {
                $query
                    ->join('core_bu_zone_mapping as bzm', 'z.id', '=', 'bzm.zone_id')
                    ->where('bzm.business_unit_id', $businessUnitId);
            }

            if ($search) {
                $query->where(function ($q) use ($search) {
                    $q
                        ->where('z.zone_name', 'LIKE', "%{$search}%")
                        ->orWhere('z.zone_code', 'LIKE', "%{$search}%")
                        ->orWhere('z.numeric_code', 'LIKE', "%{$search}%");
                });
            }

            $zones = $query
                ->select(['z.id', 'z.zone_name as name', 'z.zone_code as code', 'z.numeric_code', 'z.vertical_id'])
                ->distinct()
                ->orderBy('z.zone_name')

                ->get();

            return $this->jsonSuccess($zones, 'Zones retrieved successfully');
        } catch (Exception $e) {
            \Log::error('Error fetching zones: '.$e->getMessage());

            return $this->jsonError('Failed to retrieve zones: '.$e->getMessage(), 500);
        }
    }

    public function getRegions(Request $request)
    {
        try {
            $employeeId = $request->get('employee_id');
            $search = $request->get('search');

            $businessUnitId = $request->get('business_unit_id');
            $zoneId = $request->get('zone_id');

            $query = DB::connection('hrims')->table('core_regions as r')->where('r.is_active', '1');

            // If employee_id provided, filter by their assigned Region
            if ($employeeId) {
                $empGeneral = DB::connection('hrims')
                    ->table('hrm_employee_general')
                    ->where('EmployeeID', $employeeId)
                    ->first(['BUId', 'ZoneId', 'RegionId']);

                if ($empGeneral) {
                    // If employee has a region assigned, filter by it
                    if ($empGeneral->RegionId > 0) {
                        $query->where('r.id', $empGeneral->RegionId);
                    }
                    // Otherwise try zone mapping
                    elseif ($zoneId || $empGeneral->ZoneId > 0) {
                        $useZoneId = $zoneId ?? $empGeneral->ZoneId;
                        try {
                            $regionIds = DB::connection('hrims')
                                ->table('core_zone_region_mapping')
                                ->where('zone_id', $useZoneId)
                                ->pluck('region_id')
                                ->toArray();
                            if (! empty($regionIds)) {
                                $query->whereIn('r.id', $regionIds);
                            }
                        } catch (\Exception $e) {
                            \Log::warning('Zone-Region mapping not found: '.$e->getMessage());
                        }
                    }
                    // Or try BU mapping
                    elseif ($businessUnitId || $empGeneral->BUId > 0) {
                        $useBuId = $businessUnitId ?? $empGeneral->BUId;
                        try {
                            $zoneIds = DB::connection('hrims')
                                ->table('core_bu_zone_mapping')
                                ->where('business_unit_id', $useBuId)
                                ->pluck('zone_id')
                                ->toArray();
                            if (! empty($zoneIds)) {
                                $regionIds = DB::connection('hrims')
                                    ->table('core_zone_region_mapping')
                                    ->where('effective_to', '')
                                    ->whereIn('zone_id', $zoneIds)
                                    ->pluck('region_id')
                                    ->toArray();
                                if (! empty($regionIds)) {
                                    $query->whereIn('r.id', $regionIds);
                                }
                            }
                        } catch (\Exception $e) {
                            \Log::warning('BU-Zone-Region mapping not found: '.$e->getMessage());
                        }
                    }
                }
            }
            // No employee_id, use provided filters
            elseif ($businessUnitId || $zoneId) {
                try {
                    if ($businessUnitId) {
                        $zoneIds = DB::connection('hrims')
                            ->table('core_bu_zone_mapping')
                            ->where('business_unit_id', $businessUnitId)
                            ->pluck('zone_id')
                            ->toArray();
                        if (! empty($zoneIds)) {
                            $regionIds = DB::connection('hrims')
                                ->table('core_zone_region_mapping')
                                ->where('effective_to', '')
                                ->whereIn('zone_id', $zoneIds)
                                ->pluck('region_id')
                                ->toArray();
                            if (! empty($regionIds)) {
                                $query->whereIn('r.id', $regionIds);
                            }
                        }
                    } elseif ($zoneId) {
                        $regionIds = DB::connection('hrims')
                            ->table('core_zone_region_mapping')
                            ->where('effective_to', '')
                            ->where('zone_id', $zoneId)
                            ->pluck('region_id')
                            ->toArray();
                        if (! empty($regionIds)) {
                            $query->whereIn('r.id', $regionIds);
                        }
                    }
                } catch (\Exception $e) {
                    \Log::warning('Zone-Region mapping table not found: '.$e->getMessage());
                }
            }

            if ($search) {
                $query->where(function ($q) use ($search) {
                    $q
                        ->where('r.region_name', 'LIKE', "%{$search}%")
                        ->orWhere('r.region_code', 'LIKE', "%{$search}%")
                        ->orWhere('r.numeric_code', 'LIKE', "%{$search}%");
                });
            }

            $regions = $query
                ->select(['r.id', 'r.region_name as name', 'r.region_code as code', 'r.numeric_code', 'r.vertical_id'])
                ->distinct()
                ->orderBy('r.region_name')

                ->get();

            return $this->jsonSuccess($regions, 'Regions retrieved successfully');
        } catch (Exception $e) {
            \Log::error('Error fetching regions: '.$e->getMessage());

            return $this->jsonError('Failed to retrieve regions: '.$e->getMessage(), 500);
        }
    }

    public function getTerritories(Request $request)
    {
        try {
            $employeeId = $request->get('employee_id');
            $search = $request->get('search');
            $regionId = $request->get('region_id');
            $request->validate([
                'region_id' => 'required|integer|min:1',
            ]);

            $regionId = (int) $request->get('region_id');
            $query = DB::connection('hrims')
                ->table('core_region_territory as rt')
                ->leftJoin('core_territory as t', 't.id', '=', 'rt.territory_id')
                ->where('t.is_active', '1');
            // If employee_id provided, filter by their assigned Territory
            if ($employeeId) {
                $empGeneral = DB::connection('hrims')
                    ->table('hrm_employee_general')
                    ->where('EmployeeID', $employeeId)
                    ->first(['RegionId', 'TerrId']);

                if ($empGeneral) {
                    // If employee has a territory assigned, filter by it
                    if ($empGeneral->TerrId > 0) {
                        $query->where('rt.territory_id', $empGeneral->TerrId);
                    }
                    // Otherwise filter by region if provided
                    elseif ($regionId) {
                        $query->where('rt.region_id', $regionId);
                    }
                    // Or use employee's region
                    elseif ($empGeneral->RegionId > 0) {
                        $query->where('rt.region_id', $empGeneral->RegionId);
                    }
                }
            }
            // No employee_id, use region_id if provided
            elseif ($regionId) {
                $query->where('rt.region_id', $regionId);
            }
            if ($search) {
                $query->where(function ($q) use ($search) {
                    $q
                        ->where('t.territory_name', 'LIKE', "%{$search}%")
                        ->orWhere('rt.territory_id', 'LIKE', "%{$search}%")
                        ->orWhere('rt.name', 'LIKE', "%{$search}%");
                });
            }
            $territories = $query
                ->select([
                    'rt.id',
                    'rt.territory_id',
                    'rt.region_id',
                    't.territory_name as name',
                    'rt.name as mapping_name',
                    'rt.effective_from',
                    'rt.effective_to',
                ])
                ->distinct()
                ->orderBy('t.territory_name')
                ->get();
            return $this->jsonSuccess($territories, 'Territories retrieved successfully');
        } catch (Exception $e) {
            \Log::error('Error fetching territories: '.$e->getMessage());

            return $this->jsonError('Failed to retrieve territories: '.$e->getMessage(), 500);
        }
    }

    /**
     * Get employee's hierarchy level
     * GET /api/advance/hod/employee-level?employee_id=1225
     */
    public function getEmployeeLevel(Request $request)
    {
        try {
            $employeeId = $request->get('employee_id');

            if (! $employeeId) {
                return $this->jsonError('Employee ID is required');
            }

            // Static list of employee IDs with "all" level access
            $allLevelEmployeeIds = \App\Support\HodAccess::ALL_LEVEL;

            if (in_array($employeeId, $allLevelEmployeeIds)) {
                // Get basic employee info
                $employee = DB::connection('hrims')
                    ->table('hrm_employee_general as eg')
                    ->leftJoin('hrm_employee as e', 'eg.EmployeeID', '=', 'e.EmployeeID')
                    ->where('eg.EmployeeID', $employeeId)
                    ->select([
                        'eg.EmployeeID',
                        DB::raw("CONCAT(COALESCE(e.Fname, ''), ' ', COALESCE(e.Sname, ''), ' ', COALESCE(e.Lname, '')) as EmployeeName"),
                        'eg.EC as EmployeeCode',
                    ])
                    ->first();

                if (! $employee) {
                    return $this->jsonError('Employee not found', 404);
                }

                $response = [
                    'employee_id' => $employee->EmployeeID,
                    'employee_name' => trim($employee->EmployeeName),
                    'employee_code' => $employee->EmployeeCode,
                    'access_level' => 'all',
                    'level_description' => 'All Levels Access (Admin)',
                ];

                return $this->jsonSuccess($response, 'Employee level retrieved successfully');
            }

            // Get employee hierarchy details
            $employee = DB::connection('hrims')
                ->table('hrm_employee_general as eg')
                ->leftJoin('hrm_employee as e', 'eg.EmployeeID', '=', 'e.EmployeeID')
                ->leftJoin('core_business_unit as bu', 'eg.BUId', '=', 'bu.id')
                ->leftJoin('core_zones as z', 'eg.ZoneId', '=', 'z.id')
                ->leftJoin('core_regions as r', 'eg.RegionId', '=', 'r.id')
                ->leftJoin('core_region_territory as rt', 'eg.TerrId', '=', 'rt.territory_id')
                ->where('eg.EmployeeID', $employeeId)
                ->select([
                    'eg.EmployeeID',
                    DB::raw("CONCAT(COALESCE(e.Fname, ''), ' ', COALESCE(e.Sname, ''), ' ', COALESCE(e.Lname, '')) as EmployeeName"),
                    'eg.EC as EmployeeCode',
                    'eg.BUId',
                    'bu.business_unit_name as BUName',
                    'bu.business_unit_code as BUCode',
                    'eg.ZoneId',
                    'z.zone_name as ZoneName',
                    'z.zone_code as ZoneCode',
                    'eg.RegionId',
                    'r.region_name as RegionName',
                    'r.region_code as RegionCode',
                    'eg.TerrId',
                    'rt.name as TerritoryName',
                    'rt.territory_id as TerritoryCode',
                ])
                ->first();

            if (! $employee) {
                return $this->jsonError('Employee not found', 404);
            }

            // Determine access level
            $accessLevel = 'none';
            $levelDescription = 'No hierarchy assigned';

            if ($employee->TerrId > 0) {
                $accessLevel = 'territory';
                $levelDescription = 'Territory Level';
            } elseif ($employee->RegionId > 0 && $employee->TerrId == 0) {
                $accessLevel = 'region';
                $levelDescription = 'Region Level';
            } elseif ($employee->ZoneId > 0 && $employee->RegionId == 0 && $employee->TerrId == 0) {
                $accessLevel = 'zone';
                $levelDescription = 'Zone Level';
            } elseif ($employee->BUId > 0 && $employee->ZoneId == 0 && $employee->RegionId == 0 && $employee->TerrId == 0) {
                $accessLevel = 'bu';
                $levelDescription = 'Business Unit Level';
            } elseif ($employee->BUId > 0) {
                $accessLevel = 'all';
                $levelDescription = 'All Levels Access';
            }

            $response = [
                'employee_id' => $employee->EmployeeID,
                'employee_name' => trim($employee->EmployeeName),
                'employee_code' => $employee->EmployeeCode,
                'access_level' => $accessLevel,
                'level_description' => $levelDescription,
            ];

            return $this->jsonSuccess($response, 'Employee level retrieved successfully');
        } catch (Exception $e) {
            \Log::error('Error fetching employee level: '.$e->getMessage());

            return $this->jsonError('Failed to retrieve employee level: '.$e->getMessage(), 500);
        }
    }
}
