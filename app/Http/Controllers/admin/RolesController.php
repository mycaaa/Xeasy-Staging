<?php

namespace App\Http\Controllers\admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreRoleRequest;
use App\Http\Requests\UpdateRoleRequest;
use App\Models\Roles;
use Illuminate\Support\Facades\Auth;

class RolesController extends Controller
{
    public function index()
    {

        $status = request('status', 'active');
        $rolesQuery = Roles::query();
        if ($status === 'active') {
            $rolesQuery->where('status', 1);
        } elseif ($status === 'inactive') {
            $rolesQuery->where('status', 0);
        }
        $roles = $rolesQuery->where('id', '!=', 1)->get();

        return view('admin.roles', compact('roles', 'status'));
    }

    public function create() {}

    public function store(StoreRoleRequest $request)
    {
        $roles = Roles::create([
            'name' => $request->role_name,
            'guard_name' => 'web',
            'status' => $request->is_active,
            'created_by' => Auth::id(),
            'created_at' => now(),
        ]);
        if (! empty($request->permissions)) {
            $roles->permissions()->sync($request->permissions);
        }

        return $this->jsonSuccess($roles, 'Role created successfully.');
    }

    public function show(string $id) {}

    public function edit(string $id)
    {
        $role = Roles::with('permissions')->findOrFail($id);

        return $this->jsonSuccess([
            'id' => $role->id,
            'name' => $role->name,
            'status' => $role->status,
            'permissions' => $role->permissions->pluck('id')->toArray(),
        ], 'Role retrieved successfully.');
    }

    public function update(UpdateRoleRequest $request, string $id)
    {
        $role = Roles::findOrFail($id);
        $role->update([
            'name' => $request->role_name,
            'guard_name' => 'web',
            'status' => $request->is_active,
            'updated_by' => Auth::id(),
            'updated_at' => now(),
        ]);

        // Sync permissions even if the array is empty
        $role->permissions()->sync($request->permissions ?? []);

        return $this->jsonSuccess($role, 'Role updated successfully.');
    }

    public function destroy(string $id)
    {
        $role = Roles::findOrFail($id);
        $role->delete();

        return $this->jsonSuccess($role, 'Role deleted successfully.');
    }

    public function getRoles()
    {
        // $roles = Roles::where('status', 1)->where('id', '!=', 1)->get();
        $roles = Roles::where('status', 1)->get();
        return $this->jsonSuccess($roles, 'Roles retrieved successfully.');
    }
}
