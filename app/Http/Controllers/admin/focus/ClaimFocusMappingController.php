<?php

namespace App\Http\Controllers\admin\focus;

use App\Http\Controllers\Controller;
use App\Models\focus\ClaimFocusMapping;
use App\Models\ClaimType;
use App\Models\ExpenseHeadCategory;
use Illuminate\Http\Request;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class ClaimFocusMappingController extends Controller
{
    public function index()
    {
        $status = request('status', 'active');
        $query = ClaimFocusMapping::with(['claimType', 'expenseHeadCategory']);

        if ($status === 'active') {
            $query->where('is_active', 1);
        } elseif ($status === 'inactive') {
            $query->where('is_active', 0);
        }

        $claimFocusMappings = $query->orderBy('claim_id')->orderBy('expense_head_category_id')->get();
        
        // Get dropdown data for the form
        $claimTypes = ClaimType::orderBy('ClaimName')->get();
        $expenseHeadCategories = ExpenseHeadCategory::where('status', 1)->orderBy('expense_head_name')->get();
        
        return view('admin.focus.fc_claim_focus_mapping', compact('claimFocusMappings', 'status', 'claimTypes', 'expenseHeadCategories'));
    }

    public function store(Request $request)
    {
        $request->validate([
            'claim_id' => 'required|integer|exists:claimtype,ClaimId',
            'expense_head_category_id' => 'nullable|integer|exists:adv_expense_head_category,id',
            'wtype' => 'nullable|string|max:1|in:2,4',
            'focus_name_subl' => 'required|string|max:50',
            'focus_name' => 'required|string|max:100',
            'focus_ledger_code' => 'required|string|max:50',
            'is_active' => 'boolean',
        ]);

        // Check for duplicate mapping
        $existingMapping = ClaimFocusMapping::where('claim_id', $request->claim_id)
            ->where('expense_head_category_id', $request->expense_head_category_id)
            ->where('wtype', $request->wtype)
            ->first();

        if ($existingMapping) {
            return $this->jsonError('A mapping with the same claim, expense head category, and wtype already exists.');
        }

        $mapping = ClaimFocusMapping::create([
            'claim_id' => $request->claim_id,
            'expense_head_category_id' => $request->expense_head_category_id,
            'wtype' => $request->wtype,
            'focus_name_subl' => $request->focus_name_subl,
            'focus_name' => $request->focus_name,
            'focus_ledger_code' => $request->focus_ledger_code,
            'is_active' => $request->is_active ?? 1,
        ]);

        $permissions = [
            "view claimfocusmapping {$mapping->id}",
            "update claimfocusmapping {$mapping->id}",
            "delete claimfocusmapping {$mapping->id}",
        ];

        foreach ($permissions as $perm) {
            Permission::firstOrCreate(['name' => $perm, 'guard_name' => 'web']);
        }

        $managerRole = Role::where('name', 'Super Admin')->first();
        if ($managerRole) {
            $managerRole->givePermissionTo("view claimfocusmapping {$mapping->id}");
        }

        // Load relationships for response
        $mapping->load(['claimType', 'expenseHeadCategory']);

        return $this->jsonSuccess($mapping, 'Claim focus mapping created successfully.');
    }

    public function edit(string $id)
    {
        $mapping = ClaimFocusMapping::with(['claimType', 'expenseHeadCategory'])->findOrFail($id);
        return $this->jsonSuccess($mapping, 'Claim focus mapping retrieved successfully.');
    }

    public function update(Request $request, string $id)
    {
        $request->validate([
            'claim_id' => 'required|integer|exists:claimtype,ClaimId',
            'expense_head_category_id' => 'nullable|integer|exists:adv_expense_head_category,id',
            'wtype' => 'nullable|string|max:1|in:2,4',
            'focus_name_subl' => 'required|string|max:50',
            'focus_name' => 'required|string|max:100',
            'focus_ledger_code' => 'required|string|max:50',
            'is_active' => 'boolean',
        ]);

        $mapping = ClaimFocusMapping::findOrFail($id);

        // Check for duplicate mapping (excluding current record)
        $existingMapping = ClaimFocusMapping::where('claim_id', $request->claim_id)
            ->where('expense_head_category_id', $request->expense_head_category_id)
            ->where('wtype', $request->wtype)
            ->where('id', '!=', $id)
            ->first();

        if ($existingMapping) {
            return $this->jsonError('A mapping with the same claim, expense head category, and wtype already exists.');
        }

        $mapping->update([
            'claim_id' => $request->claim_id,
            'expense_head_category_id' => $request->expense_head_category_id,
            'wtype' => $request->wtype,
            'focus_name_subl' => $request->focus_name_subl,
            'focus_name' => $request->focus_name,
            'focus_ledger_code' => $request->focus_ledger_code,
            'is_active' => $request->is_active ?? 1,
        ]);

        // Load relationships for response
        $mapping->load(['claimType', 'expenseHeadCategory']);

        return $this->jsonSuccess($mapping, 'Claim focus mapping updated successfully.');
    }

    public function destroy(string $id)
    {
        $mapping = ClaimFocusMapping::findOrFail($id);
        $mapping->delete();
        return $this->jsonSuccess(null, 'Claim focus mapping deleted successfully.');
    }
}
