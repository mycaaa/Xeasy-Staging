<?php

namespace App\Http\Controllers\admin;

use App\Http\Controllers\Controller;
use App\Models\ActivityCategory;
use App\Models\ActivityType;
use App\Models\CoreDepartments;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class ActivityTypeController extends Controller
{
    public function index()
    {
        $status = request('status', 'active');
        $activityTypeQuery = ActivityType::with('category');
        if ($status === 'active') {
            $activityTypeQuery->where('status', 1);
        } elseif ($status === 'inactive') {
            $activityTypeQuery->where('status', 0);
        } elseif ($status === 'all') {
            // No filter applied
        } else {
            $status = 'active';
            $activityTypeQuery->where('status', 1);
        }
        $activityTypes = $activityTypeQuery->get();
        return view('admin.activity_type', compact('activityTypes', 'status'));
    }

    public function activityTypeList()
    {
        $activityTypes = ActivityType::all();
        return $this->jsonSuccess($activityTypes, 'Activity types fetched successfully.');
    }

    public function store(Request $request)
    {
        $request->validate([
            'department_id' => 'nullable|array',
            'type_name' => 'required|string|max:255',
            'focus_code' => 'nullable|string|max:100',
            'description' => 'nullable|string',
            'status' => 'boolean',
        ]);

        $departmentId = $request->department_id && is_array($request->department_id) && !empty($request->department_id)
            ? implode(',', $request->department_id)
            : null;

        $activityType = ActivityType::create([
            'department_id' => $departmentId,
            'type_name' => $request->type_name,
            'focus_code' => $request->focus_code,
            'description' => $request->description,
            'status' => $request->status ?? 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $permissions = [
            "view activitytype {$activityType->id}",
            "update activitytype {$activityType->id}",
            "delete activitytype {$activityType->id}",
        ];
        foreach ($permissions as $perm) {
            Permission::firstOrCreate(['name' => $perm, 'guard_name' => 'web']);
        }
        $managerRole = Role::where('name', 'Super Admin')->first();
        if ($managerRole) {
            $managerRole->givePermissionTo("view activitytype {$activityType->id}");
        }

        return $this->jsonSuccess($activityType, 'Activity type created successfully.');
    }

    public function edit(string $id)
    {
        $activityType = ActivityType::findOrFail($id);
        return $this->jsonSuccess($activityType, 'Activity type retrieved successfully.');
    }

    public function update(Request $request, string $id)
    {
        // dd($request->all());

        $request->validate([
            'department_id' => 'nullable|array',
            'type_name' => 'required|string|max:255',
            'focus_code' => 'nullable|string|max:100',
            'description' => 'nullable|string',
            'status' => 'boolean',
        ]);

        $activityType = ActivityType::findOrFail($id);

        $departmentId = $request->department_id && is_array($request->department_id) && !empty($request->department_id)
            ? implode(',', $request->department_id)
            : null;

        $activityType->update([
            'department_id' => $departmentId,
            'type_name' => $request->type_name,
            'focus_code' => $request->focus_code,
            'description' => $request->description,
            'status' => $request->status ?? 1,
            'updated_at' => now(),
        ]);

        return $this->jsonSuccess($activityType, 'Activity type updated successfully.');
    }

    public function destroy(string $id)
    {
        $activityType = ActivityType::findOrFail($id);
        $activityType->delete();
        return $this->jsonSuccess(null, 'Activity type deleted successfully.');
    }

    public function getCategories()
    {
        $categories = ActivityCategory::all();
        return $this->jsonSuccess($categories, 'Activity categories retrieved successfully.');
    }

    public function getDepartments()
    {
        $departments = CoreDepartments::where('is_active', 1)->get(['id', 'department_name']);
        return $this->jsonSuccess($departments, 'Departments retrieved successfully.');
    }
}
