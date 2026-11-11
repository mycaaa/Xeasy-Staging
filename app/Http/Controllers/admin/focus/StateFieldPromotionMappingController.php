<?php

namespace App\Http\Controllers\admin\focus;

use App\Http\Controllers\Controller;
use App\Models\focus\StateFieldPromotionMapping;
use Illuminate\Http\Request;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class StateFieldPromotionMappingController extends Controller
{
    public function index()
    {
        $status = request('status', 'active');
        $query = StateFieldPromotionMapping::query();
        
        if ($status === 'active') {
            $query->where('is_active', 1);
        } elseif ($status === 'inactive') {
            $query->where('is_active', 0);
        }
        
        $mappings = $query->orderBy('state_name')->get();
        return view('admin.focus.fc_state_field_promotion_mapping', compact('mappings', 'status'));
    }

    public function store(Request $request)
    {
        $request->validate([
            'state_name' => 'required|string|max:100|unique:fc_state_field_promotion_mapping,state_name',
            'focus_name' => 'required|string|max:100',
            'focus_ledger_code' => 'required|string|max:50',
            'is_active' => 'boolean',
        ]);

        $mapping = StateFieldPromotionMapping::create([
            'state_name' => strtolower($request->state_name),
            'focus_name' => $request->focus_name,
            'focus_ledger_code' => $request->focus_ledger_code,
            'is_active' => $request->is_active ?? 1,
        ]);

        $permissions = [
            "view statefieldpromotionmapping {$mapping->id}",
            "update statefieldpromotionmapping {$mapping->id}",
            "delete statefieldpromotionmapping {$mapping->id}",
        ];
        
        foreach ($permissions as $perm) {
            Permission::firstOrCreate(['name' => $perm, 'guard_name' => 'web']);
        }
        
        $managerRole = Role::where('name', 'Super Admin')->first();
        if ($managerRole) {
            $managerRole->givePermissionTo("view statefieldpromotionmapping {$mapping->id}");
        }

        return $this->jsonSuccess($mapping, 'State field promotion mapping created successfully.');
    }

    public function edit(string $id)
    {
        $mapping = StateFieldPromotionMapping::findOrFail($id);
        return $this->jsonSuccess($mapping, 'State field promotion mapping retrieved successfully.');
    }

    public function update(Request $request, string $id)
    {
        $request->validate([
            'state_name' => 'required|string|max:100|unique:fc_state_field_promotion_mapping,state_name,' . $id,
            'focus_name' => 'required|string|max:100',
            'focus_ledger_code' => 'required|string|max:50',
            'is_active' => 'boolean',
        ]);

        $mapping = StateFieldPromotionMapping::findOrFail($id);
        $mapping->update([
            'state_name' => strtolower($request->state_name),
            'focus_name' => $request->focus_name,
            'focus_ledger_code' => $request->focus_ledger_code,
            'is_active' => $request->is_active ?? 1,
        ]);

        return $this->jsonSuccess($mapping, 'State field promotion mapping updated successfully.');
    }

    public function destroy(string $id)
    {
        $mapping = StateFieldPromotionMapping::findOrFail($id);
        $mapping->delete();
        return $this->jsonSuccess(null, 'State field promotion mapping deleted successfully.');
    }
}
