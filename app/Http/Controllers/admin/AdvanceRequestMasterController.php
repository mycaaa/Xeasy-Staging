<?php
namespace App\Http\Controllers\admin;

use App\Http\Controllers\Controller;
use App\Models\AdvanceRequestLimit;
use App\Models\AdvanceRequestMaster;
use App\Models\AdvanceRequestMasterLog;
use App\Models\AdvanceRequestType;
use App\Models\CoreDepartments;
use App\Models\CoreDesignation;
use App\Models\CoreDesignationDepartmentMapping;
use App\Models\CoreGrade;
use App\Models\CoreVertical;
use App\Models\CoreVerticalDepartmentMapping;
use App\Models\CoreVerticalFunctionMapping;
use App\Models\HRMEmployees;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Exception;

class AdvanceRequestMasterController extends Controller
{
    public function index()
    {
        try {
            $advanceTypes = AdvanceRequestType::where('is_active', 1)->orderBy('id')->get();
            $departments = CoreDepartments::where('is_active', 1)->orderBy('department_name')->get();
            $allSettings = AdvanceRequestMaster::with(['advanceType', 'department'])->get();
            $allLimits = AdvanceRequestLimit::with(['advanceType', 'department'])->get();
            $settings = [];
            $limits = [];
            $activityLimits = [];
            foreach ($advanceTypes as $type) {
                $settings[$type->id] = collect();
                $limits[$type->id] = collect();
                $activityLimits[$type->id] = collect();
                if ($type->access_mode === 'DEPARTMENT') {
                    $typeSettings = $allSettings->where('advance_type_id', $type->id);
                    $typeLimits = $allLimits->where('advance_type_id', $type->id);
                    foreach ($typeSettings as $setting) {
                        $settings[$type->id][$setting->department_id] = $setting;
                    }
                    foreach ($typeLimits as $limit) {
                        if ($limit->activity_id) {
                            if (!isset($activityLimits[$type->id][$limit->department_id])) {
                                $activityLimits[$type->id][$limit->department_id] = collect();
                            }
                            $activityLimits[$type->id][$limit->department_id][$limit->activity_id] = $limit;
                        } else {
                            $limits[$type->id][$limit->department_id] = $limit;
                        }
                    }
                } else {
                    $settings[$type->id] = $allSettings->where('advance_type_id', $type->id);
                    $limits[$type->id] = $allLimits->where('advance_type_id', $type->id);
                }
            }
            $designationMappings = DB::connection('hrims')->table('core_designation_department_mapping as mapping')->join('core_designation as designation', 'mapping.designation_id', '=', 'designation.id')->join('core_departments as dept', 'mapping.department_id', '=', 'dept.id')->where('designation.is_active', 1)->where('dept.is_active', 1)->select('mapping.department_id', 'designation.id', 'designation.designation_name', 'designation.designation_code')->orderBy('designation.designation_name')->get();
            $mappedDesignationDetails = $designationMappings->groupBy('department_id');
            $employees = HRMEmployees::where('EmpStatus', 'A')->select('EmployeeID', 'Fname', 'Sname', 'Lname', 'EmpCode')->orderBy('Fname')->get();
            $activities = collect();
            $activities = DB::table('claimtype')->select('ClaimId as id', 'ClaimName as activity_name')->where('cgId', 7)->orderBy('ClaimName')->get();
            $grades = CoreGrade::where('is_active', 1)->orderBy('grade_name')->get();
            $deptVerticalMappings = CoreVerticalDepartmentMapping::whereIn('department_id', $departments->pluck('id'))->get();
            $funcVerticalMap = CoreVerticalFunctionMapping::all()->keyBy('id');
            $verticals = CoreVertical::all()->keyBy('id');
            $mappedVerticalDetails = $deptVerticalMappings
                ->groupBy('department_id')
                ->map(function ($deptMaps) use ($funcVerticalMap, $verticals) {
                    return $deptMaps->map(function ($deptMap) use ($funcVerticalMap, $verticals) {
                        $funcMapping = $funcVerticalMap[$deptMap->function_vertical_id] ?? null;
                        if (!$funcMapping) {
                            return null;
                        }
                        return $verticals[$funcMapping->vertical_id] ?? null;
                    })->filter()->unique()->values();
                });
            return view('admin.advance_request_master', compact('advanceTypes', 'departments', 'settings', 'limits', 'activityLimits', 'mappedDesignationDetails', 'employees', 'activities', 'grades', 'mappedVerticalDetails'));
        } catch (Exception $e) {
            return back()->with('error', 'Failed to load advance request master: ' . $e->getMessage());
        }
    }

    public function store(Request $request)
    {
        $cleanedData = [];
        foreach ($request->all() as $key => $value) {
            if ($value === '' || $value === 'null' || $value === 'undefined') {
                $cleanedData[$key] = null;
            } else {
                $cleanedData[$key] = $value;
            }
        }
        $request->merge($cleanedData);
        try {
            $advanceType = AdvanceRequestType::findOrFail($request->advance_type_id);
            $isActive = false;
            if ($request->has('is_active')) {
                $isActiveValue = $request->is_active;
                if (is_bool($isActiveValue)) {
                    $isActive = $isActiveValue;
                } else {
                    $isActive = in_array($isActiveValue, [1, '1', 'true', true], true);
                }
            }
            $rules = [
                'advance_type_id' => 'required|exists:advance_request_types,id',
                'is_active' => 'nullable',
            ];
            $rules['effective_from'] = $isActive ? 'required|date' : 'nullable|date';
            $rules['effective_to'] = $isActive ? 'nullable|date|after_or_equal:effective_from' : 'nullable|date';
            if ($advanceType->id == 4) {
                $rules['approver_level'] = 'nullable|in:1,2,3';
            } else {
                $rules['approver_level'] = $isActive ? 'required|in:1,2,3' : 'nullable|in:1,2,3';
            }
            if ($advanceType->access_mode === 'DEPARTMENT') {
                $rules['department_id'] = $isActive ? 'required|exists:hrims.core_departments,id' : 'nullable|exists:hrims.core_departments,id';
                if ($advanceType->type_code === 'Act') {
                    $rules['designation_ids'] = $isActive ? 'required|string' : 'nullable|string';
                    $rules['vertical_ids'] = 'nullable|array';
                    $rules['vertical_ids.*'] = 'exists:hrims.core_verticals,id';
                }
                if ($advanceType->type_code === 'Exp') {
                    $rules['grade_ids'] = 'nullable|array';
                    $rules['grade_ids.*'] = 'exists:hrims.core_grades,id';
                    $rules['vertical_ids'] = 'nullable|array';
                    $rules['vertical_ids.*'] = 'exists:hrims.core_verticals,id';
                    $rules['is_tour_travel'] = 'nullable|boolean';
                    $rules['allow_expense'] = 'nullable|boolean';
                }
            } else {
                $rules['employee_id'] = $isActive ? 'required|exists:hrims.hrm_employee,EmployeeID' : 'nullable|exists:hrims.hrm_employee,EmployeeID';
                $rules['request_for'] = $isActive ? 'required|array|min:1' : 'nullable|array';
                $rules['request_for.*'] = 'in:Act,Exp';
            }
            $validator = \Validator::make($request->all(), $rules);
            if ($validator->fails()) {
                throw new \Illuminate\Validation\ValidationException($validator);
            }
            $userId = auth()->user()->id;
            $data = ['advance_type_id' => $request->advance_type_id, 'is_active' => $isActive ? 1 : 0, 'updated_by' => $userId];
            if ($isActive) {
                $data['approver_level'] = $request->approver_level;
                $data['effective_from'] = $request->effective_from;
                $data['effective_to'] = $request->effective_to;
            }
            if ($advanceType->access_mode === 'DEPARTMENT') {
                if ($isActive) {
                    $data['department_id'] = $request->department_id;
                    if ($advanceType->type_code === 'Act') {
                        if ($request->designation_ids) {
                            $verticalDesignation = is_string($request->designation_ids) ? json_decode($request->designation_ids, true) : $request->designation_ids;
                            $data['vertical_designation'] = $verticalDesignation;
                            $data['vertical_ids'] = $verticalDesignation ? array_map('intval', array_keys($verticalDesignation)) : [];
                            $allDesignationIds = [];
                            if (is_array($verticalDesignation)) {
                                foreach ($verticalDesignation as $verticalId => $designations) {
                                    if (is_array($designations)) {
                                        foreach ($designations as $designation) {
                                            if (is_array($designation) && isset($designation['id'])) {
                                                $allDesignationIds[] = intval($designation['id']);
                                            } else {
                                                $allDesignationIds[] = intval($designation);
                                            }
                                        }
                                    }
                                }
                            }
                            $data['designation_ids'] = array_map('intval', array_unique($allDesignationIds));
                        } else {
                            $data['vertical_designation'] = [];
                            $data['vertical_ids'] = [];
                            $data['designation_ids'] = [];
                        }
                    }
                    if ($advanceType->type_code === 'Exp') {
                        $data['grade_ids'] = $request->grade_ids ? $request->grade_ids : [];
                        $data['vertical_ids'] = $request->vertical_ids ? $request->vertical_ids : [];
                        $data['is_tour_travel'] = $request->has('is_tour_travel') && in_array($request->is_tour_travel, [1, '1', 'true', true], true) ? 1 : 0;
                        $data['allow_expense'] = $request->has('allow_expense') && in_array($request->allow_expense, [1, '1', 'true', true], true) ? 1 : 0;
                    }
                    if ($advanceType->id == 4) {
                        $data['vertical_ids'] = $request->vertical_ids ? $request->vertical_ids : [];
                        $data['expense_percentage'] = $request->expense_percentage ? $request->expense_percentage : null;
                        $managementData = $request->management_data ? json_decode($request->management_data, true) : [];
                        $data['management_data'] = $managementData;
                        $managerIds = [];
                        if (is_array($managementData)) {
                            foreach ($managementData as $item) {
                                if (isset($item['manager_id'])) {
                                    $managerIds[] = $item['manager_id'];
                                }
                            }
                        }
                        $data['manager_ids'] = $managerIds;
                    }
                }
            } else {
                if ($isActive && $request->employee_id) {
                    $data['employee_ids'] = [$request->employee_id];
                    $data['request_for'] = $request->request_for;
                }
            }
            $whereConditions = ['advance_type_id' => $request->advance_type_id];
            if ($advanceType->access_mode === 'DEPARTMENT') {
                $whereConditions['department_id'] = $request->department_id ?? null;
            } else {
                if ($request->employee_id) {
                    $existingSetting = AdvanceRequestMaster::where('advance_type_id', $request->advance_type_id)->whereJsonContains('employee_ids', $request->employee_id)->first();
                    if ($existingSetting) {
                        $oldData = $existingSetting->toArray();
                        $existingSetting->update($data);
                        $newData = $existingSetting->fresh()->toArray();
                        $this->logChange($existingSetting->id, $isActive ? 'updated' : 'deactivated', $oldData, $newData, $request->advance_type_id, null, $request->employee_id);
                        $message = $isActive ? 'Employee advance request setting updated successfully' : 'Employee advance request setting deactivated successfully';
                    } else {
                        if ($isActive) {
                            $data['created_by'] = $userId;
                            $newSetting = AdvanceRequestMaster::create($data);
                            $this->logChange($newSetting->id, 'created', null, $newSetting->toArray(), $request->advance_type_id, null, $request->employee_id);
                            $message = 'Employee advance request setting created successfully';
                        } else {
                            $message = 'No changes made - setting was already inactive';
                        }
                    }
                    return response()->json(['success' => true, 'message' => $message]);
                }
            }
            $setting = AdvanceRequestMaster::where($whereConditions)->first();
            if ($setting) {
                $oldData = $setting->toArray();
                $setting->update($data);
                $newData = $setting->fresh()->toArray();
                $this->logChange($setting->id, $isActive ? 'updated' : 'deactivated', $oldData, $newData, $request->advance_type_id, $request->department_id ?? null, null);
                $message = $isActive ? 'Advance request master updated successfully' : 'Advance request master deactivated successfully';
            } else {
                if ($isActive) {
                    $data['created_by'] = $userId;
                    $newSetting = AdvanceRequestMaster::create($data);
                    $this->logChange($newSetting->id, 'created', null, $newSetting->toArray(), $request->advance_type_id, $request->department_id ?? null, null);
                    $message = 'Advance request master created successfully';
                } else {
                    $message = 'No changes made - setting was already inactive';
                }
            }
            return response()->json(['success' => true, 'message' => $message]);
        } catch (\Illuminate\Validation\ValidationException $e) {
            $errors = collect($e->errors())->flatten()->toArray();
            return response()->json(['success' => false, 'message' => 'Validation failed: ' . implode(' ', $errors), 'errors' => $e->errors()], 422);
        } catch (Exception $e) {
            return response()->json(['success' => false, 'message' => 'Failed to save advance request master: ' . $e->getMessage()], 500);
        }
    }

    public function getDesignationsByDepartment($departmentId)
    {
        try {
            $records = DB::connection('hrims')->table('core_designation_department_mapping as mapping')->join('core_designation as designation', 'mapping.designation_id', '=', 'designation.id')->join('hrm_employee_general as eg', function ($join) use ($departmentId) {
                $join->on('eg.DesigId', '=', 'designation.id')->where('eg.DepartmentId', $departmentId)->whereNotNull('eg.GradeId');
            })->join('hrm_employee as e', function ($join) {
                $join->on('e.EmployeeID', '=', 'eg.EmployeeID')->where('e.EmpStatus', 'A');
            })->leftJoin('core_grades as g', 'g.id', '=', 'eg.GradeId')->leftJoin('core_verticals as v', 'v.id', '=', 'eg.EmpVertical')->where('mapping.department_id', $departmentId)->where('designation.is_active', 1)->select('v.id as vertical_id', 'v.vertical_name', 'designation.id', 'designation.designation_name', 'designation.designation_code', DB::raw("GROUP_CONCAT(DISTINCT g.grade_name ORDER BY g.grade_name SEPARATOR ', ') as active_grades"))->groupBy('v.id', 'v.vertical_name', 'designation.id', 'designation.designation_name', 'designation.designation_code')->orderBy('v.vertical_name')->get();
            $grouped = $records->groupBy('vertical_id')->map(function ($items) {
                return ['vertical_id' => $items->first()->vertical_id, 'vertical_name' => $items->first()->vertical_name, 'designations' => $items->values()];
            })->values();
            return response()->json(['success' => true, 'verticals' => $grouped]);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => 'Failed: ' . $e->getMessage()], 500);
        }
    }

    public function getGrades(Request $request)
        {
            try {
                $departmentId = $request->get('department_id');

                if (!$departmentId) {
                    return response()->json(['success' => false, 'message' => 'Department ID is required'], 400);
                }

                // Get grades that are actively used by employees in this department
                // Only show grades where there are currently active employees
                $grades = DB::connection('hrims')
                    ->table('hrm_employee_general as eg')
                    ->join('hrm_employee as e', 'eg.EmployeeID', '=', 'e.EmployeeID')
                    ->join('core_grades as g', 'eg.GradeId', '=', 'g.id')
                    ->where('eg.DepartmentId', $departmentId)
                    ->where('e.EmpStatus', '=', 'A')
                    ->where(function($query) {
                        $query->where('g.is_active', '=', 1)
                              ->orWhere('g.is_active', '=', true);
                    })
                    ->whereNotNull('eg.GradeId')
                    ->where('eg.GradeId', '>', 0)
                    ->select('g.id', 'g.grade_name')
                    ->distinct()
                    ->orderBy('g.grade_name')
                    ->get();

                return response()->json(['success' => true, 'grades' => $grades]);
            } catch (Exception $e) {
                return response()->json(['success' => false, 'message' => 'Failed to fetch grades: ' . $e->getMessage()], 500);
            }
        }


    public function getEmployees()
    {
        try {
            $employees = HRMEmployees::where('EmpStatus', 'A')->select('EmployeeID', 'Fname', 'Sname', 'Lname', 'EmpCode')->orderBy('Fname')->get()->map(function ($emp) {
                return ['id' => $emp->EmployeeID, 'name' => trim($emp->Fname . ' ' . $emp->Sname . ' ' . $emp->Lname), 'emp_code' => $emp->EmpCode];
            });
            return response()->json(['success' => true, 'employees' => $employees]);
        } catch (Exception $e) {
            return response()->json(['success' => false, 'message' => 'Failed to fetch employees: ' . $e->getMessage()], 500);
        }
    }

    public function deleteEmployeeSetting(Request $request)
    {
        try {
            $request->validate(['advance_type_id' => 'required|exists:advance_request_types,id', 'employee_id' => 'required|exists:hrims.hrm_employee,EmployeeID']);
            $setting = AdvanceRequestMaster::where('advance_type_id', $request->advance_type_id)->whereJsonContains('employee_ids', $request->employee_id)->first();
            if ($setting) {
                $oldData = $setting->toArray();
                $settingId = $setting->id;
                $this->logChange($settingId, 'deleted', $oldData, null, $request->advance_type_id, null, $request->employee_id);
                $setting->delete();
                return response()->json(['success' => true, 'message' => 'Employee setting deleted successfully']);
            } else {
                return response()->json(['success' => false, 'message' => 'Employee setting not found'], 404);
            }
        } catch (Exception $e) {
            return response()->json(['success' => false, 'message' => 'Failed to delete employee setting: ' . $e->getMessage()], 500);
        }
    }

    public function bulkUpdate(Request $request)
    {
        try {
            $request->validate(['updates' => 'required|array', 'updates.*.advance_type_id' => 'required|exists:advance_request_types,id', 'updates.*.department_id' => 'nullable|exists:hrims.core_departments,id', 'updates.*.is_active' => 'boolean']);
            $userId = auth()->user()->id;
            $updatedCount = 0;
            foreach ($request->updates as $update) {
                $whereConditions = ['advance_type_id' => $update['advance_type_id']];
                if (isset($update['department_id'])) {
                    $whereConditions['department_id'] = $update['department_id'];
                }
                $setting = AdvanceRequestMaster::where($whereConditions)->first();
                if ($setting) {
                    $oldData = $setting->toArray();
                    $setting->update(['is_active' => $update['is_active'] ?? false, 'updated_by' => $userId]);
                    $newData = $setting->fresh()->toArray();
                    $this->logChange($setting->id, 'bulk_updated', $oldData, $newData, $update['advance_type_id'], $update['department_id'] ?? null, null);
                    $updatedCount++;
                }
            }
            return response()->json(['success' => true, 'message' => "Successfully updated {$updatedCount} settings"]);
        } catch (Exception $e) {
            return response()->json(['success' => false, 'message' => 'Failed to bulk update settings: ' . $e->getMessage()], 500);
        }
    }

    public function storeLimits(Request $request)
    {
        try {
            $advanceType = AdvanceRequestType::findOrFail($request->advance_type_id);
            $isActive = false;
            if ($request->has('is_active')) {
                $isActiveValue = $request->is_active;
                if (is_bool($isActiveValue)) {
                    $isActive = $isActiveValue;
                } else {
                    $isActive = in_array($isActiveValue, [1, '1', 'true', true], true);
                }
            }
            $rules = [
                'advance_type_id' => 'required|exists:advance_request_types,id',
                'department_id' => 'required|exists:hrims.core_departments,id',
                'is_active' => 'nullable',
                'min_amount' => 'nullable|numeric|min:0',
                'max_amount' => 'nullable|numeric|min:0|gte:min_amount',
                'activity_id' => 'nullable|integer',
            ];
            $validator = \Validator::make($request->all(), $rules);
            if ($validator->fails()) {
                throw new \Illuminate\Validation\ValidationException($validator);
            }
            $userId = auth()->user()->id;
            $data = ['advance_type_id' => $request->advance_type_id, 'department_id' => $request->department_id, 'activity_id' => $request->activity_id, 'min_amount' => $request->min_amount, 'max_amount' => $request->max_amount, 'is_active' => $isActive ? 1 : 0, 'updated_by' => $userId];
            $whereConditions = ['advance_type_id' => $request->advance_type_id, 'department_id' => $request->department_id, 'activity_id' => $request->activity_id];
            $limit = AdvanceRequestLimit::where($whereConditions)->first();
            if ($limit) {
                $limit->update($data);
                $message = 'Advance request limit updated successfully';
            } else {
                $data['created_by'] = $userId;
                AdvanceRequestLimit::create($data);
                $message = 'Advance request limit created successfully';
            }
            return response()->json(['success' => true, 'message' => $message]);
        } catch (\Illuminate\Validation\ValidationException $e) {
            $errors = collect($e->errors())->flatten()->toArray();
            return response()->json(['success' => false, 'message' => 'Validation failed: ' . implode(' ', $errors), 'errors' => $e->errors()], 422);
        } catch (Exception $e) {
            return response()->json(['success' => false, 'message' => 'Failed to save advance request limit: ' . $e->getMessage()], 500);
        }
    }

    public function getActivities(Request $request)
    {
        try {
            $departmentId = $request->get('department_id');
            if (!$departmentId) {
                return response()->json(['success' => false, 'message' => 'Department ID is required'], 400);
            }
            $activities = DB::table('adv_activity_names as aan')->join('claimtype as ct', 'aan.activity_name', '=', 'ct.ClaimId')->where('aan.status', 1)->where('ct.cgId', 7)->whereRaw('FIND_IN_SET(?, aan.dept_id)', [$departmentId])->select('ct.ClaimId as id', 'ct.ClaimName as activity_name', 'aan.id as activity_config_id', 'aan.approved_limit', 'aan.approved_amount')->orderBy('ct.ClaimName')->get();
            return response()->json(['success' => true, 'activities' => $activities]);
        } catch (\Throwable $e) {
            return response()->json(['success' => false, 'message' => 'Failed to fetch activities'], 500);
        }
    }

    public function getActivityLimits($advanceTypeId, $departmentId)
    {
        try {
            $limits = AdvanceRequestLimit::where('advance_type_id', $advanceTypeId)->where('department_id', $departmentId)->whereNotNull('activity_id')->where('is_active', 1)->get();
            return response()->json(['success' => true, 'limits' => $limits]);
        } catch (Exception $e) {
            return response()->json(['success' => false, 'message' => 'Failed to fetch activity limits: ' . $e->getMessage()], 500);
        }
    }

    public function getManagementTeam(Request $request)
    {
        try {
            $departmentId = $request->department_id;
            if (!$departmentId) {
                return response()->json(['success' => false, 'message' => 'Department ID is required'], 400);
            }
            $employees = DB::connection('hrims')->table('hrm_employee_general as emp')->leftJoin('hrm_employee as emp_detail', 'emp.EmployeeID', '=', 'emp_detail.EmployeeID')->where('emp.DepartmentId', 18)->where('emp_detail.EmpStatus', 'A')->select('emp.EmployeeID', DB::raw("CONCAT_WS(' ', emp_detail.Fname, emp_detail.Sname, emp_detail.Lname) as full_name, emp_detail.EmpCode"))->get();
            $managementList = [];
            foreach ($employees as $employee) {
                $verticals = $this->getManagerTeamVerticals($employee, $departmentId);
                $managementList[] = ['employee_id' => $employee->EmployeeID, 'full_name' => $employee->full_name, 'EmpCode' => $employee->EmpCode, 'verticals' => $verticals];
            }
            return response()->json(['success' => true, 'management' => $managementList]);
        } catch (Exception $e) {
            return response()->json(['success' => false, 'message' => 'Failed to fetch management team: ' . $e->getMessage()], 500);
        }
    }

    private function getManagerTeamVerticals($manager, $departmentId)
    {
        try {
            $teamMemberIds = $this->fetchEmployeesRecursive($manager->EmployeeID);
            
            if (empty($teamMemberIds)) {
                return [];
            }
            
            // Get unique verticals (EmpVertical) from team members
            $teamVerticals = DB::connection('hrims')
                ->table('hrm_employee_general as eg')
                ->join('hrm_employee as e', 'eg.EmployeeID', '=', 'e.EmployeeID')
                ->whereIn('eg.EmployeeID', $teamMemberIds)
                ->where('e.EmpStatus', 'A')
                ->where('eg.DepartmentId', $departmentId)
                ->whereNotNull('eg.EmpVertical')
                ->select('eg.EmpVertical')
                ->distinct()
                ->pluck('EmpVertical')
                ->toArray();
            
            if (empty($teamVerticals)) {
                return [];
            }
            
            // Get vertical details for the team's verticals
            $verticals = CoreVertical::whereIn('id', $teamVerticals)
                ->where('is_active', 1)
                ->select('id', 'vertical_name')
                ->orderBy('vertical_name')
                ->get()
                ->map(function ($vertical) {
                    return [
                        'id' => $vertical->id,
                        'name' => $vertical->vertical_name
                    ];
                })
                ->toArray();
            
            return $verticals;
        } catch (Exception $e) {
            \Log::error('Error getting manager team verticals: ' . $e->getMessage());
            return [];
        }
    }

    private function fetchEmployeesRecursive($managerId, $depth = 0, $maxDepth = 10, &$visited = [])
    {
        try {
            if ($depth >= $maxDepth || in_array($managerId, $visited)) {
                return [];
            }
            $visited[] = $managerId;
            $employees = collect();
            $directReports = DB::connection('hrims')->table('hrm_employee_general as eg')->join('hrm_employee as e', 'eg.EmployeeID', '=', 'e.EmployeeID')->where('eg.RepEmployeeID', $managerId)->where('e.EmpStatus', 'A')->select('eg.EmployeeID')->get();
            foreach ($directReports as $employee) {
                $employees->push($employee->EmployeeID);
                $subEmployees = $this->fetchEmployeesRecursive($employee->EmployeeID, $depth + 1, $maxDepth, $visited);
                $employees = $employees->merge($subEmployees);
            }
            return $employees->unique()->values()->toArray();
        } catch (Exception $e) {
            \Log::error('Error in fetchEmployeesRecursive: ' . $e->getMessage());
            return [];
        }
    }

    private function logChange($masterId, $action, $oldData, $newData, $advanceTypeId, $departmentId = null, $employeeId = null)
    {
        try {
            $userId = auth()->user()->id ?? null;
            $changesSummary = $this->generateChangesSummary($oldData, $newData, $action);
            AdvanceRequestMasterLog::create(['advance_request_master_id' => $masterId, 'advance_type_id' => $advanceTypeId, 'department_id' => $departmentId, 'employee_id' => $employeeId, 'action' => $action, 'old_data' => $oldData, 'new_data' => $newData, 'changes_summary' => $changesSummary, 'changed_by' => $userId]);
        } catch (Exception $e) {
            \Log::error('Failed to create log entry: ' . $e->getMessage());
        }
    }

    private function generateChangesSummary($oldData, $newData, $action)
    {
        if ($action === 'created') {
            return 'New advance request master setting created';
        }
        if ($action === 'deleted') {
            return 'Advance request master setting deleted';
        }
        $changes = [];
        $allKeys = array_unique(array_merge(array_keys($oldData ?? []), array_keys($newData ?? [])));
        foreach ($allKeys as $key) {
            $oldValue = $oldData[$key] ?? null;
            $newValue = $newData[$key] ?? null;
            if (in_array($key, ['created_at', 'updated_at', 'id'])) {
                continue;
            }
            if (is_array($oldValue)) {
                $oldValue = json_encode($oldValue);
            }
            if (is_array($newValue)) {
                $newValue = json_encode($newValue);
            }
            if ($oldValue != $newValue) {
                $changes[] = $this->formatFieldChange($key, $oldValue, $newValue);
            }
        }
        return implode('; ', $changes);
    }

    private function formatFieldChange($field, $oldValue, $newValue)
    {
        $fieldNames = ['is_active' => 'Status', 'approver_level' => 'Approver Level', 'effective_from' => 'Effective From', 'effective_to' => 'Effective To', 'designation_ids' => 'Designations', 'vertical_designation' => 'Vertical Designation Mapping', 'grade_ids' => 'Grades', 'vertical_ids' => 'Verticals', 'management_data' => 'Management Data', 'manager_ids' => 'Managers', 'expense_percentage' => 'Expense Percentage', 'employee_ids' => 'Employees', 'request_for' => 'Request For', 'is_tour_travel' => 'Tour Travel', 'allow_expense' => 'Allow Expense', 'department_id' => 'Department'];
        $fieldName = $fieldNames[$field] ?? ucfirst(str_replace('_', ' ', $field));
        if ($field === 'is_active') {
            $oldValue = $oldValue ? 'Active' : 'Inactive';
            $newValue = $newValue ? 'Active' : 'Inactive';
        }
        if (strlen($oldValue) > 100) {
            $oldValue = substr($oldValue, 0, 100) . '...';
        }
        if (strlen($newValue) > 100) {
            $newValue = substr($newValue, 0, 100) . '...';
        }
        return "{$fieldName} changed from '{$oldValue}' to '{$newValue}'";
    }

    public function getLogs(Request $request)
    {
        try {
            $query = AdvanceRequestMasterLog::with(['advanceType', 'department', 'changedBy']);
            if ($request->has('advance_request_master_id')) {
                $query->where('advance_request_master_id', $request->advance_request_master_id);
            }
            if ($request->has('advance_type_id')) {
                $query->where('advance_type_id', $request->advance_type_id);
            }
            if ($request->has('department_id')) {
                $query->where('department_id', $request->department_id);
            }
            if ($request->has('employee_id')) {
                $query->where('employee_id', $request->employee_id);
            }
            if ($request->has('action')) {
                $query->where('action', $request->action);
            }
            if ($request->has('from_date')) {
                $query->whereDate('created_at', '>=', $request->from_date);
            }
            if ($request->has('to_date')) {
                $query->whereDate('created_at', '<=', $request->to_date);
            }
            $logs = $query->orderBy('created_at', 'desc')->paginate($request->per_page ?? 50);
            return response()->json(['success' => true, 'logs' => $logs]);
        } catch (Exception $e) {
            return response()->json(['success' => false, 'message' => 'Failed to fetch logs: ' . $e->getMessage()], 500);
        }
    }
}
