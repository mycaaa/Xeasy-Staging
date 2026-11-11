<?php

namespace App\Http\Controllers\admin;

use App\Http\Controllers\Controller;
use App\Models\OtherParticipantCategory;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class OtherParticipantCategoryController extends Controller
{
    public function index()
    {
        OtherParticipantCategory::ensureTableExists();

        $status = request('status', 'active');
        $query = OtherParticipantCategory::query();

        if ($status === 'active') {
            $query->where('status', 1);
        } elseif ($status === 'inactive') {
            $query->where('status', 0);
        }

        try {
            $categories = $query->orderBy('sort_order', 'asc')->orderBy('id', 'asc')->get();
        } catch (\Exception $e) {
            $categories = collect([]);
        }

        return view('admin.other_participant_category', compact('categories', 'status'));
    }

    public function categoryList()
    {
        OtherParticipantCategory::ensureTableExists();

        try {
            $categories = OtherParticipantCategory::active()->get();
        } catch (\Exception $e) {
            $categories = collect([
                ['id' => 1, 'name' => 'Retailer', 'code' => 'RETAILER', 'sort_order' => 1, 'status' => 1],
                ['id' => 2, 'name' => 'Dealer', 'code' => 'DEALER', 'sort_order' => 2, 'status' => 1],
                ['id' => 3, 'name' => 'Distributor', 'code' => 'DISTRIBUTOR', 'sort_order' => 3, 'status' => 1],
                ['id' => 4, 'name' => 'Consultant', 'code' => 'CONSULTANT', 'sort_order' => 4, 'status' => 1],
                ['id' => 5, 'name' => 'Management', 'code' => 'MANAGEMENT', 'sort_order' => 5, 'status' => 1],
                ['id' => 6, 'name' => 'Guest', 'code' => 'GUEST', 'sort_order' => 6, 'status' => 1],
                ['id' => 7, 'name' => 'Other Employee not available in the list', 'code' => 'OTHER_EMPLOYEE', 'sort_order' => 7, 'status' => 1],
            ]);
        }

        return response()->json([
            'status' => 200,
            'success' => true,
            'data' => $categories,
            'message' => 'Other participant categories retrieved successfully.'
        ]);
    }

    public function store(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:255|unique:other_participant_categories,name',
            'code' => 'nullable|string|max:100|unique:other_participant_categories,code',
            'description' => 'nullable|string',
            'sort_order' => 'nullable|integer',
            'status' => 'boolean',
        ]);

        $code = $request->code ? Str::upper(Str::slug($request->code, '_')) : Str::upper(Str::slug($request->name, '_'));

        $category = OtherParticipantCategory::create([
            'name' => $request->name,
            'code' => $code,
            'description' => $request->description,
            'sort_order' => $request->sort_order ?? 0,
            'status' => $request->status ? 1 : 0,
        ]);

        if ($request->wantsJson() || $request->ajax()) {
            return response()->json([
                'status' => 200,
                'success' => true,
                'data' => $category,
                'message' => 'Other participant category created successfully.'
            ]);
        }

        return redirect()->back()->with('success', 'Participant category created successfully.');
    }

    public function edit(string $id)
    {
        $category = OtherParticipantCategory::findOrFail($id);
        return response()->json([
            'status' => 200,
            'success' => true,
            'data' => $category,
        ]);
    }

    public function update(Request $request, string $id)
    {
        $category = OtherParticipantCategory::findOrFail($id);

        $request->validate([
            'name' => 'required|string|max:255|unique:other_participant_categories,name,' . $id,
            'code' => 'nullable|string|max:100|unique:other_participant_categories,code,' . $id,
            'description' => 'nullable|string',
            'sort_order' => 'nullable|integer',
            'status' => 'boolean',
        ]);

        $code = $request->code ? Str::upper(Str::slug($request->code, '_')) : Str::upper(Str::slug($request->name, '_'));

        $category->update([
            'name' => $request->name,
            'code' => $code,
            'description' => $request->description,
            'sort_order' => $request->sort_order ?? 0,
            'status' => $request->has('status') ? ($request->status ? 1 : 0) : $category->status,
        ]);

        if ($request->wantsJson() || $request->ajax()) {
            return response()->json([
                'status' => 200,
                'success' => true,
                'data' => $category,
                'message' => 'Other participant category updated successfully.'
            ]);
        }

        return redirect()->back()->with('success', 'Participant category updated successfully.');
    }

    public function destroy(string $id)
    {
        $category = OtherParticipantCategory::findOrFail($id);
        $category->delete();

        if (request()->wantsJson() || request()->ajax()) {
            return response()->json([
                'status' => 200,
                'success' => true,
                'message' => 'Other participant category deleted successfully.'
            ]);
        }

        return redirect()->back()->with('success', 'Participant category deleted successfully.');
    }

    public function toggleStatus(string $id)
    {
        $category = OtherParticipantCategory::findOrFail($id);
        $category->status = $category->status == 1 ? 0 : 1;
        $category->save();

        return response()->json([
            'status' => 200,
            'success' => true,
            'new_status' => $category->status,
            'message' => 'Category status updated successfully.'
        ]);
    }
}
