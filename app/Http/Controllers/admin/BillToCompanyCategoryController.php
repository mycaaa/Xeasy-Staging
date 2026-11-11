<?php

namespace App\Http\Controllers\admin;

use App\Http\Controllers\Controller;
use App\Models\BillToCompanyCategory;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class BillToCompanyCategoryController extends Controller
{
    // ── Index view ───────────────────────────────────────────────────────────

    public function index()
    {
        $categories = BillToCompanyCategory::orderBy('name')->get();

        return view('admin.bill-to-company.categories', compact('categories'));
    }

    // ── DataTable data (JSON) ────────────────────────────────────────────────

    public function getData()
    {
        $categories = BillToCompanyCategory::orderBy('name')
            ->withCount(['bills as bills_count' => function ($q) {
                // Only count active bills
                $q->where('status', 1);
            }])
            ->get()
            ->map(function ($cat) {
                return [
                    'id'          => $cat->id,
                    'name'        => $cat->name,
                    'description' => $cat->description,
                    'is_active'   => $cat->is_active,
                    'bills_count' => $cat->bills_count ?? 0,
                    'created_at'  => $cat->created_at?->format('d M Y'),
                ];
            });

        return response()->json(['success' => true, 'data' => $categories]);
    }

    // ── Active list for dropdowns ────────────────────────────────────────────

    public function list()
    {
        $categories = BillToCompanyCategory::active()
            ->orderBy('name')
            ->get(['id', 'name']);

        return response()->json($categories);
    }

    // ── Store ────────────────────────────────────────────────────────────────

    public function store(Request $request)
    {
        $request->validate([
            'name'        => 'required|string|max:100|unique:bill_to_company_categories,name',
            'description' => 'nullable|string|max:255',
            'is_active'   => 'nullable|boolean',
        ]);

        BillToCompanyCategory::create([
            'name'        => trim($request->name),
            'description' => $request->description,
            'is_active'   => $request->boolean('is_active', true),
            'created_by'  => auth()->id(),
            'updated_by'  => auth()->id(),
        ]);

        return response()->json(['success' => true, 'message' => 'Category created successfully.']);
    }

    // ── Update ───────────────────────────────────────────────────────────────

    public function update(Request $request, int $id)
    {
        $category = BillToCompanyCategory::findOrFail($id);

        $request->validate([
            'name'        => ['required', 'string', 'max:100', Rule::unique('bill_to_company_categories', 'name')->ignore($id)],
            'description' => 'nullable|string|max:255',
            'is_active'   => 'nullable|boolean',
        ]);

        $category->update([
            'name'        => trim($request->name),
            'description' => $request->description,
            'is_active'   => $request->boolean('is_active', $category->is_active),
            'updated_by'  => auth()->id(),
        ]);

        return response()->json(['success' => true, 'message' => 'Category updated successfully.']);
    }

    // ── Toggle active status ─────────────────────────────────────────────────

    public function toggle(int $id)
    {
        $category = BillToCompanyCategory::findOrFail($id);
        $category->update([
            'is_active'  => !$category->is_active,
            'updated_by' => auth()->id(),
        ]);

        $label = $category->is_active ? 'activated' : 'deactivated';

        return response()->json(['success' => true, 'message' => "Category {$label}.", 'is_active' => $category->is_active]);
    }

    // ── Destroy ──────────────────────────────────────────────────────────────

    public function destroy(int $id)
    {
        $category = BillToCompanyCategory::findOrFail($id);

        // Check if any bills are attached to this category (by name)
        $billsCount = DB::table('bill_to_company_bills')
            ->where('bill_category', $category->name)
            ->where('status', 1)
            ->count();

        if ($billsCount > 0) {
            return response()->json([
                'success' => false,
                'message' => "Cannot delete: {$billsCount} active bill(s) are using this category. Deactivate it instead.",
            ], 422);
        }

        $category->delete();

        return response()->json(['success' => true, 'message' => 'Category deleted successfully.']);
    }
}
