<?php

namespace App\Http\Controllers\admin;

use App\Http\Controllers\Controller;
use App\Models\ActivityCategory;
use App\Models\ActivityName;
use App\Models\ClaimType;
use App\Models\CoreDepartments;
use App\Models\CoreVertical;
use Illuminate\Http\Request;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class ActivityNameController extends Controller
{
    public function index()
    {
        $status = request('status', 'active');
        $activityNameQuery = ActivityName::with(['category', 'claimType']);
        if ($status === 'active') {
            $activityNameQuery->where('status', 1);
        } elseif ($status === 'inactive') {
            $activityNameQuery->where('status', 0);
        } elseif ($status === 'all') {
        } else {
            $status = 'active';
            $activityNameQuery->where('status', 1);
        }
        $activityNames = $activityNameQuery->get();
        return view('admin.activity_name', compact('activityNames', 'status'));
    }

    public function activityNameList()
    {
        $activityNames = ActivityName::all();
        return $this->jsonSuccess($activityNames, 'Activity names fetched successfully.');
    }

    public function store(Request $request)
    {
        $connection = session('expense_connection', 'expense');
        $request->validate([
            'category_id' => "nullable|integer|exists:{$connection}.adv_activity_categories,id",
            'activity_name' => "required|integer|exists:{$connection}.claimtype,ClaimId,cgId,7",
            'dept_id' => 'nullable|array',
            'description' => 'nullable|string',
            'status' => 'boolean',
        ]);

        $deptIds = $request->dept_id
            ? array_map('intval', array_filter($request->dept_id, 'is_numeric'))
            : null;
        $verticals = $request->vertical
            ? array_map('trim', array_filter($request->vertical))
            : null;

        $activityName = ActivityName::create([
            'category_id' => $request->category_id,
            'activity_name' => $request->activity_name,
            'description' => $request->description,
            'dept_id' => $deptIds ? implode(',', $deptIds) : null,
            'vertical' => $verticals ? implode(',', $verticals) : null,
            'from_month' => $request->from_month,
            'to_month' => $request->to_month,
            'from_year' => $request->from_year,
            'to_year' => $request->to_year,
            'approved_limit' => $request->approved_limit,
            'approved_amount' => $request->approved_amount,
            'status' => $request->status ?? 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        return $this->jsonSuccess($activityName, 'Activity name created successfully.');
    }

    public function edit(string $id)
    {
        $activityName = ActivityName::findOrFail($id);
        return $this->jsonSuccess($activityName, 'Activity name retrieved successfully.');
    }

    public function update(Request $request, string $id)
    {
        $connection = session('expense_connection', 'expense');
        $request->validate([
            'category_id' => "nullable|integer|exists:{$connection}.adv_activity_categories,id",
            'activity_name' => "required|integer|exists:{$connection}.claimtype,ClaimId,cgId,7",
            'dept_id' => 'nullable|array',
            'description' => 'nullable|string',
            'status' => 'boolean',
        ]);

        $deptIds = $request->dept_id
            ? array_map('intval', array_filter($request->dept_id, 'is_numeric'))
            : null;
        $verticals = $request->vertical
            ? array_map('trim', array_filter($request->vertical))
            : null;

        $activityName = ActivityName::findOrFail($id);
        $activityName->update([
            'category_id' => $request->category_id,
            'activity_name' => $request->activity_name,
            'description' => $request->description,
            'dept_id' => $deptIds ? implode(',', $deptIds) : null,
            'vertical' => $verticals ? implode(',', $verticals) : null,
            'from_month' => $request->from_month,
            'to_month' => $request->to_month,
            'from_year' => $request->from_year,
            'to_year' => $request->to_year,
            'approved_limit' => $request->approved_limit,
            'approved_amount' => $request->approved_amount,
            'status' => $request->status ?? 1,
            'updated_at' => now(),
        ]);

        return $this->jsonSuccess($activityName, 'Activity name updated successfully.');
    }

    public function destroy(string $id)
    {
        $activityName = ActivityName::findOrFail($id);
        $activityName->delete();
        return $this->jsonSuccess(null, 'Activity name deleted successfully.');
    }

    public function getCategories()
    {
        $categories = ActivityCategory::all();
        return $this->jsonSuccess($categories, 'Activity categories retrieved successfully.');
    }

    public function getDepartments()
    {
        $departments = CoreDepartments::active();
        return $this->jsonSuccess($departments, 'Departments retrieved successfully.');
    }

    public function getClaimTypes()
    {
        $claimTypes = ClaimType::where('cgId', 7)->get(['ClaimId', 'ClaimName']);
        return $this->jsonSuccess($claimTypes, 'Claim types retrieved successfully.');
    }

    public function getVerticals()
    {
        $verticals = CoreVertical::active();
        return $this->jsonSuccess($verticals, 'Verticals retrieved successfully.');
    }
}
