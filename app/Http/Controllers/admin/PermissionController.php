<?php

namespace App\Http\Controllers\admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\StorePermissionRequest;
use App\Http\Requests\UpdatePermissionRequest;
use App\Models\Menu;
use App\Models\Permission;
use App\Models\PermissionGroup;
use App\Models\Roles;
use App\Models\User;
use App\Models\UserClaimType;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

class PermissionController extends Controller
{
    public function index(Request $request)
    {
        $query = Permission::join('permission_groups', 'permissions.permission_group_id', '=', 'permission_groups.id')->select('permissions.*', 'permission_groups.name as group_name');
        if ($request->filled('group_id')) {
            $query->where('permissions.permission_group_id', $request->group_id);
        }
        $permissions = $query->orderBy('permissions.id', 'desc')->get();
        $roles = Roles::where('status', 1)->get();
        $group = PermissionGroup::all();

        return view('admin.permissions', compact('permissions', 'roles', 'group'));
    }

    public function permissionOld(Request $request)
    {
        $query = Permission::join('permission_groups', 'permissions.permission_group_id', '=', 'permission_groups.id')->select('permissions.*', 'permission_groups.name as group_name');

        if ($request->filled('group_id')) {
            $query->where('permissions.permission_group_id', $request->group_id);
        }

        $permissions = $query->get();
        $roles = Roles::where('status', 1)
            ->where('id', '!=', 1)
            ->get();
        $group = PermissionGroup::all();

        return view('admin.permissions_old', compact('permissions', 'roles', 'group'));
    }

    public function create() {}

    public function store(StorePermissionRequest $request)
    {
        $permissionKey = Str::slug($request->permission_name, '_');
        $permission = Permission::create([
            'name' => $permissionKey,
            'permission_key' => $request->permission_name,
            'permission_group_id' => $request->group_id,
            'status' => $request->is_active,
            'guard_name' => 'web',
            'created_by' => Auth::id(),
        ]);

        Cache::forget('spatie.permission.cache');
        return $this->jsonSuccess($permission, 'Permission created successfully.');
    }

    public function show(string $id) {}

    public function edit(string $id)
    {
        $permission = Permission::findOrFail($id);

        return $this->jsonSuccess($permission, 'Permission fetched successfully.');
    }

    public function update(UpdatePermissionRequest $request, string $id)
    {
        $permissionKey = Str::slug($request->permission_name, '_');
        $permission = Permission::findOrFail($id);
        $permission->update([
            'name' => $permissionKey,
            'permission_group_id' => $request->group_id,
            'permission_key' => $request->permission_name,
            'status' => $request->is_active,
            'updated_by' => Auth::id(),
            'updated_at' => now(),
        ]);
        Menu::where('permission_id', $id)->update([
            'permission_name' => $permissionKey,
            'updated_by' => Auth::id(),
            'updated_at' => now(),
        ]);
        Cache::forget('spatie.permission.cache');
        return $this->jsonSuccess($permission, 'Permission updated successfully.');
    }

    public function destroy(string $id)
    {
        $permission = Permission::findOrFail($id);
        $permission->delete();

        Cache::forget('spatie.permission.cache');

        return $this->jsonSuccess(null, 'Permission deleted successfully.');
    }

    public function assignPermissions(Request $request)
    {
        $request->validate([
            'role_id' => 'required|exists:roles,id',
            'permission_id' => 'required|exists:permissions,id',
            'isChecked' => 'required|boolean',
        ]);
        $role = Roles::findOrFail($request->role_id);
        $permission = Permission::findOrFail($request->permission_id);

        if ($role->guard_name !== $permission->guard_name) {
            return response()->json(
                [
                    'success' => false,
                    'message' => 'Guard mismatch between role and permission.',
                ],
                400
            );
        }

        if ($request->isChecked) {
            $role->givePermissionTo($permission->name);
        } else {
            $role->revokePermissionTo($permission->name);
        }

        Cache::forget('spatie.permission.cache');

        return $this->jsonSuccess([], 'Permission updated successfully.');
    }

    public function getPermissions(string $id)
    {
        $user = User::findOrFail($id);

        $permissions = $user
            ->getDirectPermissions()
            ->load('group')
            ->map(function ($permission) {
                return [
                    'id' => $permission->id,
                    'name' => $permission->name,
                    'group' => $permission->group ? $permission->group->name : 'Other',
                ];
            })
            ->groupBy('group')
            ->toArray();

        $assignedClaimTypes = UserClaimType::where('user_id', $user->id)
            ->pluck('claim_type_id')
            ->toArray();

        $data = [
            'permissions' => $permissions,
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
            ],
            'assigned_claim_types' => $assignedClaimTypes,
        ];

        return $this->jsonSuccess($data, 'Permissions fetched successfully.');
    }

    public function assignClaimType(Request $request, string $id)
    {
        $request->validate([
            'claim_type_id' => 'required|integer',
        ]);

        UserClaimType::firstOrCreate([
            'user_id' => $id,
            'claim_type_id' => $request->claim_type_id,
        ]);

        return $this->jsonSuccess(null, 'Claim type assigned successfully.');
    }

    public function revokeClaimType(Request $request, string $id)
    {
        $request->validate([
            'claim_type_id' => 'required|integer',
        ]);

        UserClaimType::where('user_id', $id)
            ->where('claim_type_id', $request->claim_type_id)
            ->delete();

        return $this->jsonSuccess(null, 'Claim type revoked successfully.');
    }

    public function getAllPermissions()
    {
        $permissions = Permission::with('group')
            ->get()
            ->map(function ($permission) {
                return [
                    'id' => $permission->id,
                    'name' => $permission->name,
                    'permission_key' => $permission->permission_key,
                    'group' => $permission->group ? $permission->group->name : 'Other',
                    'category' => $permission->group ? $permission->group->category : 'Uncategorized',
                ];
            })
            ->groupBy('category')
            ->map(function ($categoryPermissions) {
                return $categoryPermissions
                    ->groupBy('group')
                    ->map(function ($groupPermissions) {
                        return $groupPermissions
                            ->map(function ($permission) {
                                return [
                                    'id' => $permission['id'],
                                    'name' => $permission['name'],
                                    'permission_key' => $permission['permission_key'],
                                ];
                            })
                            ->values()
                            ->toArray();
                    })
                    ->toArray();
            })
            ->toArray();

        return $this->jsonSuccess($permissions, 'All permissions fetched successfully.');
    }

    public function assignPermission(Request $request, string $id)
    {
        $request->validate([
            'permission_id' => 'required|exists:permissions,id',
        ]);
        $user = User::findOrFail($id);
        $permission = Permission::findOrFail($request->permission_id);
        $user->givePermissionTo($permission->name);
        Cache::forget('spatie.permission.cache');

        return $this->jsonSuccess(null, 'Permission assigned successfully.');
    }

    public function revokePermission(Request $request, string $id)
    {
        $request->validate([
            'permission_id' => 'required|exists:permissions,id',
        ]);
        $user = User::findOrFail($id);
        $permission = Permission::findOrFail($request->permission_id);
        $user->revokePermissionTo($permission->name);
        Cache::forget('spatie.permission.cache');

        return $this->jsonSuccess(null, 'Permission revoked successfully.');
    }
}
