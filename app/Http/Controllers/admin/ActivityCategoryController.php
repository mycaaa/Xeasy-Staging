<?php

namespace App\Http\Controllers\admin;

use App\Http\Controllers\Controller;
use App\Models\ActivityCategory;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class ActivityCategoryController extends Controller
{
    public function index()
    {
        $status = request('status', 'active');
        $activityCategoryQuery = ActivityCategory::query();
        if ($status === 'active') {
            $activityCategoryQuery->where('status', 1);
        } elseif ($status === 'inactive') {
            $activityCategoryQuery->where('status', 0);
        } elseif ($status === 'all') {
            // No filter applied
        } else {
            $status = 'active';
            $activityCategoryQuery->where('status', 1);
        }
        $activityCategories = $activityCategoryQuery->get();
        return view('admin.activity_category', compact('activityCategories', 'status'));
    }

    public function activityCategoryList()
    {
        $activityCategories = ActivityCategory::all();
        return $this->jsonSuccess($activityCategories, 'Activity categories fetched successfully.');
    }

    public function store(Request $request)
    {
        $request->validate([
            'category_name' => 'required|string|max:255',
            'mapped_activity' => 'required|in:Y,N',
            'description' => 'nullable|string',
            'status' => 'boolean',
        ]);

        $activityCategory = ActivityCategory::create([
            'category_name' => $request->category_name,
            'mapped_activity' => $request->mapped_activity,
            'description' => $request->description,
            'status' => $request->status ?? 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $permissions = [
            "view activitycategory {$activityCategory->id}",
            "update activitycategory {$activityCategory->id}",
            "delete activitycategory {$activityCategory->id}",
        ];
        foreach ($permissions as $perm) {
            Permission::firstOrCreate(['name' => $perm, 'guard_name' => 'web']);
        }
        $managerRole = Role::where('name', 'Super Admin')->first();
        if ($managerRole) {
            $managerRole->givePermissionTo("view activitycategory {$activityCategory->id}");
        }

        return $this->jsonSuccess($activityCategory, 'Activity category created successfully.');
    }

    public function edit(string $id)
    {
        $activityCategory = ActivityCategory::findOrFail($id);
        return $this->jsonSuccess($activityCategory, 'Activity category retrieved successfully.');
    }

    public function update(Request $request, string $id)
    {
        $request->validate([
            'category_name' => 'required|string|max:255',
            'mapped_activity' => 'required|in:Y,N',
            'description' => 'nullable|string',
            'status' => 'boolean',
        ]);

        $activityCategory = ActivityCategory::findOrFail($id);
        $activityCategory->update([
            'category_name' => $request->category_name,
            'mapped_activity' => $request->mapped_activity,
            'description' => $request->description,
            'status' => $request->status ?? 1,
            'updated_at' => now(),
        ]);

        return $this->jsonSuccess($activityCategory, 'Activity category updated successfully.');
    }

    public function destroy(string $id)
    {
        $activityCategory = ActivityCategory::findOrFail($id);
        $activityCategory->delete();
        return $this->jsonSuccess(null, 'Activity category deleted successfully.');
    }
}