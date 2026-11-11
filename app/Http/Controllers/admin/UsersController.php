<?php

namespace App\Http\Controllers\admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreUserRequest;
use App\Http\Requests\UpdateUserRequest;
use App\Models\HRMEmployees;
use App\Models\Permission;
use App\Models\Roles;
use App\Models\User;
use App\Models\UserLog;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Auth;
use Laravel\Reverb\Loggers\Log;

class UsersController extends Controller
{
    public function index()
    {
        $status = request('status', 'active');
        $roles = Roles::where('status', 1)->get();
        return view('admin.users', compact('roles', 'status'));
    }
    public function datatable(Request $request)
    {
        $draw = $request->input('draw', 1);
        $start = $request->input('start', 0);
        $length = $request->input('length', 10);
        $searchValue = $request->input('search.value', '');
        $status = $request->input('status', 'active');
        $query = User::with(['roles', 'employee']);
        if ($status === 'active') {
            $query->where('status', 1);
        } elseif ($status === 'inactive') {
            $query->where('status', 0);
        }
        $totalRecords = $query->count();
        if (!empty($searchValue)) {
            $query->where(function ($q) use ($searchValue) {
                $q->where('employee_id', 'LIKE', "%{$searchValue}%")
                  ->orWhere('name', 'LIKE', "%{$searchValue}%")
                  ->orWhere('email', 'LIKE', "%{$searchValue}%");
                $matchingEmployeeIds = \App\Models\HRMEmployees::where(function ($eq) use ($searchValue) {
                    $eq->where('EmpCode', 'LIKE', "%{$searchValue}%")
                      ->orWhere('Fname', 'LIKE', "%{$searchValue}%")
                      ->orWhere('Sname', 'LIKE', "%{$searchValue}%")
                      ->orWhere('Lname', 'LIKE', "%{$searchValue}%");
                })->pluck('EmployeeID')->toArray();
                if (!empty($matchingEmployeeIds)) {
                    $q->orWhereIn('employee_id', $matchingEmployeeIds);
                }
            });
        }
        $filteredRecords = $query->count();
        $users = $query->orderBy('id', 'desc')->skip($start)->take($length)->get();
        $canModify = auth()->user()->can('modify_user');
        $canRemove = auth()->user()->can('remove_user');
        $canManagePermissions = auth()->user()->can('manage_permissions');

        $data = $users->map(function ($user) use ($canModify, $canRemove, $canManagePermissions) {
            $companies = [
                1 => 'VNR Seeds Pvt Ltd',
                3 => 'VNR Nursery Pvt Ltd',
                11 => 'VVNR',
            ];

            $companyIds = explode(',', (string) $user->company_access_id);
            $companyNames = array_map(function ($id) use ($companies) {
                return $companies[$id] ?? '';
            }, $companyIds);

            $isGuest = (bool) ($user->is_guest ?? false);
            if ($isGuest) {
                $empCodeHtml = '<span class="badge bg-warning-subtle text-warning border border-warning-subtle"><i class="ri-user-shared-line me-1"></i>GUEST</span>';
                $empNameHtml = htmlspecialchars($user->name ?: '-');
                if (!empty($user->email)) {
                    $empNameHtml .= '<br><small class="text-muted"><i class="ri-mail-line me-1"></i>' . htmlspecialchars($user->email) . '</small>';
                }
                $empStatus = 'Guest';
            } else {
                $empCodeHtml = $user->employee ? ($user->employee->EmpCode ?: '-') : '-';
                $empNameHtml = $user->employee ? htmlspecialchars($user->employee->full_name) : htmlspecialchars($user->name ?: '-');
                $empStatus = $user->employee ? ($user->employee->EmpStatus ?: '-') : '-';
            }

            return [
                'id' => $user->id,
                'is_guest' => $isGuest,
                'employee_id' => $user->employee_id,
                'emp_code' => $empCodeHtml,
                'employee_name' => $empNameHtml,
                'company_name' => $user->company_access_id ? implode(', ', array_filter($companyNames)) : '-',
                'emp_status' => $empStatus,
                'roles' => $user->roles->map(function ($role) {
                    return ['name' => $role->name, 'id' => $role->id];
                }),
                'status' => $user->status,
                'can_modify' => $canModify,
                'can_remove' => $canRemove,
                'can_manage_permissions' => $canManagePermissions,
                'actions' => ''
            ];
        });
        return response()->json(['draw' => intval($draw), 'recordsTotal' => $totalRecords, 'recordsFiltered' => $filteredRecords, 'data' => $data]);
    }
    public function userActivity(Request $request)
    {
        return view('reports.user_activity');
    }
    public function userActivityData(Request $request)
    {
        $baseQuery = UserLog::query();
        if ($request->filled('date_from') && $request->filled('date_to')) {
            $dateFrom = $request->date_from . ' 00:00:00';
            $dateTo = $request->date_to . ' 23:59:59';
            $baseQuery->whereBetween('user_logs.timestamp', [$dateFrom, $dateTo]);
        }
        if ($request->filled('user_ids')) {
            $userIds = is_array($request->user_ids) ? $request->user_ids : explode(',', $request->user_ids);
            $baseQuery->whereIn('user_logs.user_id', $userIds);
        }
        if ($request->filled('login_method') && $request->login_method !== 'all') {
            $baseQuery->where('user_logs.login_method', $request->login_method);
        }
        if ($request->filled('filter_type') && $request->filter_type === 'multiple_ip') {
            $multipleIPUsers = UserLog::select('user_id')->groupBy('user_id')->havingRaw('COUNT(DISTINCT ip_address) > 1')->pluck('user_id')->toArray();
            $baseQuery->whereIn('user_logs.user_id', $multipleIPUsers);
        }
        $activities = $baseQuery->leftJoin('users', 'user_logs.user_id', '=', 'users.employee_id')->orderBy('user_logs.timestamp', 'desc')->get(['user_logs.id', 'user_logs.user_id', 'user_logs.ip_address', 'user_logs.is_success', 'user_logs.user_agent', 'user_logs.timestamp', 'user_logs.status', 'user_logs.login_method', 'user_logs.created_at', 'user_logs.updated_at', 'users.name as user_name', 'users.email as user_email', 'users.employee_id as employee_id',]);
        $totalLogins = (clone $baseQuery)->count();
        $uniqueUsers = (clone $baseQuery)->distinct('user_logs.user_id')->count('user_logs.user_id');
        $tokenLogins = (clone $baseQuery)->where('user_logs.login_method', 'token')->count();
        $uniqueIPs = (clone $baseQuery)->distinct('user_logs.ip_address')->count('user_logs.ip_address');
        $peakHourQuery = (clone $baseQuery)->selectRaw('HOUR(user_logs.timestamp) as hour, COUNT(*) as login_count')->groupBy('hour')->orderByDesc('login_count')->first();
        $peakHour = $peakHourQuery ? $peakHourQuery->hour : 0;
        $multipleIPUsersCount = (clone $baseQuery)->select('user_logs.user_id')->groupBy('user_logs.user_id')->havingRaw('COUNT(DISTINCT user_logs.ip_address) > 1')->count();
        $stats = ['total_logins' => $totalLogins, 'unique_users' => $uniqueUsers, 'token_logins' => $tokenLogins, 'unique_ips' => $uniqueIPs, 'peak_hour' => $peakHour, 'multiple_ip_users' => $multipleIPUsersCount,];
        return $this->jsonSuccess(['activities' => $activities, 'stats' => $stats,], 'User activity data and statistics fetched successfully.');
    }
    public function create() {}
    public function store(StoreUserRequest $request)
    {
        User::ensureGuestColumnExists();

        if ($request->boolean('is_guest')) {
            $username = trim($request->username ?? $request->employee_id);
            $existing = User::where('employee_id', $username)->first();
            if ($existing) {
                return response()->json(['success' => false, 'message' => 'Username "' . $username . '" is already taken by another account.'], 422);
            }

            if ($request->filled('email')) {
                $emailCheck = User::where('email', trim($request->email))->first();
                if ($emailCheck) {
                    return response()->json(['success' => false, 'message' => 'Email address is already in use by another account.'], 422);
                }
            }

            $user = User::create([
                'name' => trim($request->name),
                'email' => trim($request->email ?? ''),
                'password' => Hash::make($request->password),
                'employee_id' => $username,
                'company_access_id' => $request->company_id,
                'status' => $request->is_active ? 1 : 0,
                'is_guest' => 1,
                'created_by' => auth()->id(),
                'updated_by' => auth()->id(),
            ]);

            if ($request->has('role_id') && !empty($request->role_id)) {
                $roleIds = is_array($request->role_id) ? $request->role_id : [$request->role_id];
                $roleIds = array_map('intval', array_filter($roleIds));
                $user->assignRole($roleIds);
                if (!empty($roleIds)) {
                    $user->update(['role_id' => $roleIds[0]]);
                }
            } else {
                $guestRole = Roles::where('name', 'Guest')->first();
                if ($guestRole) {
                    $user->assignRole($guestRole->id);
                    $user->update(['role_id' => $guestRole->id]);
                }
            }

            return $this->jsonSuccess($user, 'Guest user created successfully! Username: ' . $username);
        }

        $inputEmpId = trim($request->employeeid ?? $request->employee_id);
        $employee = HRMEmployees::where('EmployeeID', $inputEmpId)
            ->orWhere('EmpCode', $inputEmpId)
            ->orWhere('EmpCode_New', $inputEmpId)
            ->orWhere('EmpCode', ltrim($inputEmpId, '0'))
            ->orWhere('EmpCode_New', ltrim($inputEmpId, '0'))
            ->first();

        if (!$employee) {
            return response()->json(['success' => false, 'message' => 'Employee ID or Code not found in HRIMS database.'], 404);
        }

        $existing = User::where('employee_id', (string) $employee->EmployeeID)->first();
        if ($existing) {
            return response()->json(['success' => false, 'message' => 'This employee (' . $employee->full_name . ') already has a user account.'], 422);
        }

        $user = User::create([
            'employee_id' => (string) $employee->EmployeeID,
            'name' => $employee->full_name ?: trim(($employee->Fname ?? '') . ' ' . ($employee->Lname ?? '')),
            'email' => '',
            'password' => Hash::make('password123'),
            'company_access_id' => $request->company_id,
            'status' => $request->is_active ? 1 : 0,
            'is_guest' => 0,
            'created_by' => auth()->id(),
            'updated_by' => auth()->id(),
        ]);

        if ($request->has('role_id') && !empty($request->role_id)) {
            $roleIds = is_array($request->role_id) ? $request->role_id : [$request->role_id];
            $roleIds = array_map('intval', array_filter($roleIds));
            $user->assignRole($roleIds);
        }
        return $this->jsonSuccess($user, 'User created successfully with default password: password123');
    }
    public function importEmployees(Request $request)
    {
        try {
            @set_time_limit(300);
            @ini_set('memory_limit', '512M');

            $authUserId = auth()->id() ?? 1;
            $defaultRoleId = 7;
            $defaultPasswordHash = Hash::make('password123');
            $now = now();

            // 1. Fetch all employees from HRIMS in one query
            $employees = DB::connection('hrims')
                ->table('hrm_employee as e')
                ->leftJoin('hrm_employee_general as g', 'e.EmployeeID', '=', 'g.EmployeeID')
                ->select([
                    'e.EmployeeID',
                    'e.EmpCode',
                    'e.Fname',
                    'e.Sname',
                    'e.Lname',
                    'e.EmpStatus',
                    'e.CompanyId',
                    'g.EmailId_Vnr as email',
                ])
                ->get();

            if ($employees->isEmpty()) {
                return $this->jsonSuccess(['imported_count' => 0, 'updated_count' => 0], 'No employees found in HRIMS.');
            }

            // 2. Fetch existing users mapped by employee_id
            $existingUsers = DB::table('users')
                ->select('id', 'employee_id', 'name', 'email', 'status', 'company_access_id', 'role_id')
                ->get()
                ->keyBy('employee_id');

            // Find all role IDs that correspond to the "Employee" role
            $allRoles = DB::table('roles')->select('id', 'name')->get();
            $employeeRoleIds = $allRoles->filter(function ($r) {
                return strtolower(trim($r->name)) === 'employee';
            })->pluck('id')->map('intval')->toArray();

            if (empty($employeeRoleIds)) {
                $employeeRoleIds = [(int) $defaultRoleId];
            }

            // Fetch all assigned roles for existing users to check if they have non-employee or additional roles
            $existingUserIds = $existingUsers->pluck('id')->toArray();
            $userRoleMap = DB::table('model_has_roles')
                ->where('model_type', 'App\Models\User')
                ->whereIn('model_id', $existingUserIds)
                ->select('model_id as user_id', 'role_id')
                ->get()
                ->groupBy('user_id');

            $newUsers = [];
            $updatedCount = 0;

            foreach ($employees as $emp) {
                $empId = (string) $emp->EmployeeID;
                $name = trim(($emp->Fname ?? '') . ' ' . ($emp->Sname ? $emp->Sname . ' ' : '') . ($emp->Lname ?? ''));
                if (empty($name)) {
                    $name = 'Emp ' . ($emp->EmpCode ?: $empId);
                }
                $status = in_array($emp->EmpStatus, ['A', 'R']) ? 1 : 0;
                $companyId = (string) ($emp->CompanyId ?: 1);

                $email = trim($emp->email ?? '');
                if (empty($email)) {
                    $email = !empty($emp->EmpCode) ? ($emp->EmpCode . '@vnrseeds.com') : ('emp' . $empId . '@vnrseeds.com');
                }

                if (isset($existingUsers[$empId])) {
                    $u = $existingUsers[$empId];

                    // Determine if the employee has other roles too, or a role other than "Employee"
                    $assignedRoleIds = $userRoleMap->has($u->id)
                        ? $userRoleMap->get($u->id)->pluck('role_id')->map('intval')->toArray()
                        : [];

                    if (!empty($u->role_id) && !in_array((int) $u->role_id, $assignedRoleIds, true)) {
                        $assignedRoleIds[] = (int) $u->role_id;
                    }

                    $hasOtherRole = false;
                    foreach ($assignedRoleIds as $rid) {
                        if (!in_array($rid, $employeeRoleIds, true)) {
                            $hasOtherRole = true;
                            break;
                        }
                    }

                    $updateData = [];

                    if ($u->name !== $name) {
                        $updateData['name'] = $name;
                    }

                    if ((int) $u->status !== $status) {
                        $updateData['status'] = $status;
                    }

                    // Only change assigned company if employee has ONLY the Employee role (no other/additional roles)
                    if (!$hasOtherRole && (string) $u->company_access_id !== $companyId) {
                        $updateData['company_access_id'] = $companyId;
                    }

                    if (!empty($updateData)) {
                        $updateData['updated_by'] = $authUserId;
                        $updateData['updated_at'] = $now;

                        DB::table('users')->where('id', $u->id)->update($updateData);
                        $updatedCount++;
                    }
                } else {
                    $newUsers[] = [
                        'name' => $name,
                        'email' => $email,
                        'password' => $defaultPasswordHash,
                        'employee_id' => $empId,
                        'role_id' => $defaultRoleId,
                        'status' => $status,
                        'company_access_id' => $companyId,
                        'created_by' => $authUserId,
                        'updated_by' => $authUserId,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ];
                }
            }

            // 3. Batch insert new users in chunks
            $importedCount = count($newUsers);
            if (!empty($newUsers)) {
                foreach (array_chunk($newUsers, 500) as $chunk) {
                    DB::table('users')->insert($chunk);
                }
            }

            // 4. Batch assign default role to users who don't have any roles yet (e.g. newly imported users)
            $allUsers = DB::table('users')->whereNotNull('employee_id')->pluck('id')->toArray();
            $usersWithAnyRole = DB::table('model_has_roles')
                ->where('model_type', 'App\Models\User')
                ->pluck('model_id')
                ->unique()
                ->toArray();
            $missingRoleUserIds = array_diff($allUsers, $usersWithAnyRole);

            if (!empty($missingRoleUserIds)) {
                $roleInserts = [];
                foreach ($missingRoleUserIds as $uid) {
                    $roleInserts[] = [
                        'role_id' => $defaultRoleId,
                        'model_type' => 'App\Models\User',
                        'model_id' => $uid,
                    ];
                }
                foreach (array_chunk($roleInserts, 500) as $chunk) {
                    DB::table('model_has_roles')->insert($chunk);
                }
            }

            // 5. Clear Spatie permission cache once
            app(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();

            $msg = "Import completed successfully! $importedCount new employees imported, $updatedCount updated.";
            return $this->jsonSuccess([
                'imported_count' => $importedCount,
                'updated_count' => $updatedCount,
                'total_synced' => $employees->count()
            ], $msg);
        } catch (\Exception $e) {
            return $this->jsonError('Import failed: ' . $e->getMessage(), 500);
        }
    }
    public function show(string $id) {}
    public function edit(string $id)
    {
        $user = User::findOrFail($id);
        $hasRoles = $user->roles()->pluck('id');
        $userData = $user->toArray();
        $userData['role_ids'] = $hasRoles;
        $userData['is_guest'] = (bool) ($user->is_guest ?? false);
        return $this->jsonSuccess($userData, 'User fetched successfully.');
    }
    public function update(UpdateUserRequest $request, string $id)
    {
        $user = User::findOrFail($id);
        $updateData = [
            'status' => $request->is_active ? 1 : 0,
            'updated_by' => auth()->id(),
            'updated_at' => now(),
        ];

        if ($request->has('company_id')) {
            $updateData['company_access_id'] = $request->company_id;
        }

        if ($user->is_guest) {
            if ($request->filled('name')) {
                $updateData['name'] = trim($request->name);
            }
            if ($request->has('email')) {
                $email = trim($request->email ?? '');
                $currentEmail = trim((string)($user->email ?? ''));
                if (!empty($email) && strcasecmp($email, $currentEmail) !== 0) {
                    $existingEmail = User::where('email', $email)->where('id', '!=', $user->id)->first();
                    if ($existingEmail) {
                        return response()->json(['success' => false, 'message' => 'Email address is already in use by another account.'], 422);
                    }
                }
                $updateData['email'] = $email;
            }
            if ($request->filled('username')) {
                $username = trim($request->username);
                $currentUsername = trim((string)($user->employee_id ?? ''));
                if (!empty($username) && strcasecmp($username, $currentUsername) !== 0) {
                    $existingUsername = User::where('employee_id', $username)->where('id', '!=', $user->id)->first();
                    if ($existingUsername) {
                        return response()->json(['success' => false, 'message' => 'Username "' . $username . '" is already taken.'], 422);
                    }
                    $updateData['employee_id'] = $username;
                }
            }
            if ($request->filled('password')) {
                $updateData['password'] = Hash::make($request->password);
            }
        }

        $user->update($updateData);

        if ($request->has('role_id')) {
            $roleIds = is_array($request->role_id) ? $request->role_id : [$request->role_id];
            $roleIds = array_map('intval', array_filter($roleIds));
            $user->syncRoles($roleIds);
            $user->update(['role_id' => $roleIds[0] ?? null]);
        }

        return $this->jsonSuccess($user, 'User updated successfully.');
    }
    public function destroy(string $id)
    {
        $user = User::findOrFail($id);
        $user->delete();
        return $this->jsonSuccess(null, 'User deleted successfully.');
    }
    public function profile()
    {
        $id = Auth::id();
        $user_detail = User::find($id);
        return view('admin.profile', compact('user_detail'));
    }
    public function getPermissionView($id)
    {
        $roles = Roles::where('status', 1)->get();
        $user = User::findOrFail($id);
        $userPermissions = $user->getDirectPermissions()->load('group')->map(function ($permission) {
            return ['id' => $permission->id, 'name' => $permission->name, 'group' => $permission->group ? $permission->group->name : 'Other',];
        })->groupBy('group')->toArray();
        $allPermissions = Permission::with('group')->get()->map(function ($permission) {
            return ['id' => $permission->id, 'name' => $permission->name, 'group' => $permission->group ? $permission->group->name : 'Other',];
        })->groupBy('group')->toArray();
        $hasRoles = $user->roles()->pluck('id')->toArray();
        return view('admin.set_permission', compact('roles', 'user', 'userPermissions', 'allPermissions', 'hasRoles'));
    }
}
