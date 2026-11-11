<?php

namespace App\Http\Controllers\admin\focus;

use App\Http\Controllers\Controller;
use App\Models\focus\DepartmentCodeMapping;
use Illuminate\Http\Request;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class DepartmentCodeMappingController extends Controller
{
    public function index()
    {
        $status = request('status', 'active');
        $query = DepartmentCodeMapping::query();

        if ($status === 'active') {
            $query->where('is_active', 1);
        } elseif ($status === 'inactive') {
            $query->where('is_active', 0);
        }

        $departmentCodeMappings = $query->orderBy('original_department_name')->get();
        return view('admin.focus.fc_department_code_mapping', compact('departmentCodeMappings', 'status'));
    }

    public function store(Request $request)
    {
        $request->validate([
            'original_department_name' => 'required|string|max:100|unique:fc_department_code_mapping,original_department_name',
            'mapped_department_code' => 'required|string|max:50',
            'mapped_department_name' => 'required|string|max:100',
            'is_active' => 'boolean',
        ]);

        $mapping = DepartmentCodeMapping::create([
            'original_department_name' => $request->original_department_name,
            'mapped_department_code' => $request->mapped_department_code,
            'mapped_department_name' => $request->mapped_department_name,
            'is_active' => $request->is_active ?? 1,
        ]);

        $permissions = [
            "view departmentcodemapping {$mapping->id}",
            "update departmentcodemapping {$mapping->id}",
            "delete departmentcodemapping {$mapping->id}",
        ];

        foreach ($permissions as $perm) {
            Permission::firstOrCreate(['name' => $perm, 'guard_name' => 'web']);
        }

        $managerRole = Role::where('name', 'Super Admin')->first();
        if ($managerRole) {
            $managerRole->givePermissionTo("view departmentcodemapping {$mapping->id}");
        }

        return $this->jsonSuccess($mapping, 'Department code mapping created successfully.');
    }

    public function edit(string $id)
    {
        $mapping = DepartmentCodeMapping::findOrFail($id);
        return $this->jsonSuccess($mapping, 'Department code mapping retrieved successfully.');
    }

    public function update(Request $request, string $id)
    {
        $request->validate([
            'original_department_name' => 'required|string|max:100|unique:fc_department_code_mapping,original_department_name,' . $id,
            'mapped_department_code' => 'required|string|max:50',
            'mapped_department_name' => 'required|string|max:100',
            'is_active' => 'boolean',
        ]);

        $mapping = DepartmentCodeMapping::findOrFail($id);
        $mapping->update([
            'original_department_name' => $request->original_department_name,
            'mapped_department_code' => $request->mapped_department_code,
            'mapped_department_name' => $request->mapped_department_name,
            'is_active' => $request->is_active ?? 1,
        ]);

        return $this->jsonSuccess($mapping, 'Department code mapping updated successfully.');
    }

    public function destroy(string $id)
    {
        $mapping = DepartmentCodeMapping::findOrFail($id);
        $mapping->delete();
        return $this->jsonSuccess(null, 'Department code mapping deleted successfully.');
    }
}
