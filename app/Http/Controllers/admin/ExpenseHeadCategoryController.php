<?php

namespace App\Http\Controllers\admin;

use App\Http\Controllers\Controller;
use App\Models\ExpenseHeadCategory;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class ExpenseHeadCategoryController extends Controller
{
    public function index()
    {
        $status = request('status', 'active');
        $expenseHeadQuery = ExpenseHeadCategory::query();
        if ($status === 'active') {
            $expenseHeadQuery->where('status', 1);
        } elseif ($status === 'inactive') {
            $expenseHeadQuery->where('status', 0);
        } elseif ($status === 'all') {
            // No filter applied
        } else {
            $status = 'active';
            $expenseHeadQuery->where('status', 1);
        }
        $expenseHeads = $expenseHeadQuery->get();
        return view('admin.expense_head_category', compact('expenseHeads', 'status'));
    }

    public function expenseHeadList()
    {
        $expenseHeads = ExpenseHeadCategory::all();
        return $this->jsonSuccess($expenseHeads, 'Expense head categories fetched successfully.');
    }

    public function store(Request $request)
    {
        $request->validate([
            'expense_head_name' => 'required|string|max:255',
            'short_code' => 'nullable|string|max:100',
            'field_type' => 'required|string|in:number,text,textarea',
            'has_file' => 'boolean',
            'file_required' => 'boolean',
            'status' => 'boolean',
        ]);

        $expenseHead = ExpenseHeadCategory::create([
            'expense_head_name' => $request->expense_head_name,
            'short_code' => $request->short_code,
            'field_type' => $request->field_type,
            'has_file' => $request->has_file ?? 0,
            'file_required' => $request->file_required ?? 0,
            'status' => $request->status ?? 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $permissions = [
            "view expensehead {$expenseHead->id}",
            "update expensehead {$expenseHead->id}",
            "delete expensehead {$expenseHead->id}",
        ];
        foreach ($permissions as $perm) {
            Permission::firstOrCreate(['name' => $perm, 'guard_name' => 'web']);
        }
        $managerRole = Role::where('name', 'Super Admin')->first();
        if ($managerRole) {
            $managerRole->givePermissionTo("view expensehead {$expenseHead->id}");
        }

        return $this->jsonSuccess($expenseHead, 'Expense head category created successfully.');
    }

    public function edit(string $id)
    {
        $expenseHead = ExpenseHeadCategory::findOrFail($id);
        return $this->jsonSuccess($expenseHead, 'Expense head category retrieved successfully.');
    }

    public function update(Request $request, string $id)
    {
        $request->validate([
            'expense_head_name' => 'required|string|max:255',
            'short_code' => 'nullable|string|max:100',
            'field_type' => 'required|string|in:number,text,textarea',
            'has_file' => 'boolean',
            'file_required' => 'boolean',
            'status' => 'boolean',
        ]);

        $expenseHead = ExpenseHeadCategory::findOrFail($id);
        $expenseHead->update([
            'expense_head_name' => $request->expense_head_name,
            'short_code' => $request->short_code,
            'field_type' => $request->field_type,
            'has_file' => $request->has_file ?? 0,
            'file_required' => $request->file_required ?? 0,
            'status' => $request->status ?? 1,
            'updated_at' => now(),
        ]);

        return $this->jsonSuccess($expenseHead, 'Expense head category updated successfully.');
    }

    public function destroy(string $id)
    {
        $expenseHead = ExpenseHeadCategory::findOrFail($id);
        $expenseHead->delete();
        return $this->jsonSuccess(null, 'Expense head category deleted successfully.');
    }
}