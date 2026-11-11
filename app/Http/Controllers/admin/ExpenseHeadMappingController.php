<?php
namespace App\Http\Controllers\admin;
use App\Http\Controllers\Controller;
use App\Models\ActivityName;
use App\Models\ExpenseHeadCategory;
use App\Models\ExpenseHeadMapping;
use Illuminate\Http\Request;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
class ExpenseHeadMappingController extends Controller {
    public function index() {
        
        $expenseHeads = ExpenseHeadCategory::where('status', 1)->get();
        
        $activities = \DB::connection(session('expense_connection'))->table('adv_activity_names as an')->leftJoin('adv_activity_categories as ac', 'ac.id', '=', 'an.category_id')->leftJoin('claimtype as ct', 'ct.ClaimId', '=', 'an.activity_name')->select('an.id as activity_id', 'ac.category_name', 'ct.ClaimName as activity_name')->where('an.status', 1)->get();
        
        $mappings = ExpenseHeadMapping::pluck('checked', \DB::raw("CONCAT(activity_id,'-',expense_head_id)"))->toArray();
        return view('admin.expense_head_mapping', compact('expenseHeads', 'activities', 'mappings'));
    }
    public function toggle(Request $request) {
        $connection = session('expense_connection', 'expense');
        $request->validate(['activity_id' => "required|integer|exists:{$connection}.adv_activity_names,id", 'expense_head_id' => "required|integer|exists:{$connection}.adv_expense_head_category,id", 'checked' => 'required|boolean', ]);
        $activityId = $request->activity_id;
        $expenseHeadId = $request->expense_head_id;
        $checked = $request->checked;
        $mapping = ExpenseHeadMapping::where(['activity_id' => $activityId, 'expense_head_id' => $expenseHeadId, ])->first();
        if ($mapping) {
            $mapping->update(['checked' => $checked, 'updated_at' => now(), ]);
        } else {
            $mapping = ExpenseHeadMapping::create(['activity_id' => $activityId, 'expense_head_id' => $expenseHeadId, 'checked' => $checked, 'created_at' => now(), 'updated_at' => now(), ]);
            $managerRole = Role::where('name', 'Super Admin')->first();
            if ($managerRole) {
                $managerRole->givePermissionTo("view expenseheadmapping {$mapping->id}");
            }
        }
        return response()->json(['success' => true, 'message' => 'Mapping updated successfully.', 'data' => $mapping, ]);
    }
    public function getActivities() {
        $activities = ActivityName::with('claimType')->get(['id', 'activity_name'])->map(function ($activity) {
            return ['id' => $activity->id, 'name' => $activity->claim_name, ];
        });
        return response()->json(['success' => true, 'data' => $activities, 'message' => 'Activities retrieved successfully.']);
    }
    public function getExpenseHeads() {
        $expenseHeads = ExpenseHeadCategory::all(['id', 'expense_head_name']);
        return response()->json(['success' => true, 'data' => $expenseHeads, 'message' => 'Expense heads retrieved successfully.']);
    }
}
