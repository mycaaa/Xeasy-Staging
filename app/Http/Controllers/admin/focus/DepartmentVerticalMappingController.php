<?php

namespace App\Http\Controllers\admin\focus;

use App\Http\Controllers\Controller;
use App\Models\focus\DepartmentVerticalMapping;
use Illuminate\Http\Request;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class DepartmentVerticalMappingController extends Controller
{
    public function index()
    {
        $status = request('status', 'active');
        $query = DepartmentVerticalMapping::query();
        
        if ($status === 'active') {
            $query->where('is_active', 1);
        } elseif ($status === 'inactive') {
            $query->where('is_active', 0);
        }
        
        $mappings = $query->orderBy('base_department')->orderBy('vertical_name')->get();
        return view('admin.focus.fc_department_vertical_mapping', compact('mappings', 'status'));
    }

    public function store(Request $request)
    {
        $request->validate([
            'base_department' => 'required|string|max:50',
            'vertical_name' => 'required|string|max:100',
            'mapped_department_name' => 'required|string|max:100',
            'is_active' => 'boolean',
        ]);

        $mapping = DepartmentVerticalMapping::create([
            'base_department' => $request->base_department,
            'vertical_name' => $request->vertical_name,
            'mapped_department_name' => $request->mapped_department_name,
            'is_active' => $request->is_active ?? 1,
        ]);

        $permissions = [
            "view departmentverticalmapping {$mapping->id}",
            "update departmentverticalmapping {$mapping->id}",
            "delete departmentverticalmapping {$mapping->id}",
        ];
        
        foreach ($permissions as $perm) {
            Permission::firstOrCreate(['name' => $perm, 'guard_name' => 'web']);
        }
        
        $managerRole = Role::where('name', 'Super Admin')->first();
        if ($managerRole) {
            $managerRole->givePermissionTo("view departmentverticalmapping {$mapping->id}");
        }

        return $this->jsonSuccess($mapping, 'Department vertical mapping created successfully.');
    }

    public function edit(string $id)
    {
        $mapping = DepartmentVerticalMapping::findOrFail($id);
        return $this->jsonSuccess($mapping, 'Department vertical mapping retrieved successfully.');
    }

    public function update(Request $request, string $id)
    {
        $request->validate([
            'base_department' => 'required|string|max:50',
            'vertical_name' => 'required|string|max:100',
            'mapped_department_name' => 'required|string|max:100',
            'is_active' => 'boolean',
        ]);

        $mapping = DepartmentVerticalMapping::findOrFail($id);
        $mapping->update([
            'base_department' => $request->base_department,
            'vertical_name' => $request->vertical_name,
            'mapped_department_name' => $request->mapped_department_name,
            'is_active' => $request->is_active ?? 1,
        ]);

        return $this->jsonSuccess($mapping, 'Department vertical mapping updated successfully.');
    }

    public function destroy(string $id)
    {
        $mapping = DepartmentVerticalMapping::findOrFail($id);
        $mapping->delete();
        return $this->jsonSuccess(null, 'Department vertical mapping deleted successfully.');
    }
}
