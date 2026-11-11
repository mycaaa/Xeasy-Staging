<?php

namespace App\Http\Controllers\admin;

use App\Http\Controllers\Controller;
use App\Models\ClaimGroup;
use App\Models\ClaimType;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class ClaimTypeController extends Controller
{
    public function index()
    {
        $status = request('status', 'active');
        $claimTypeQuery = ClaimType::with('group');
        if ($status === 'active') {
            $claimTypeQuery->whereIn('ClaimStatus', ['A', 'B']);
        } elseif ($status === 'inactive') {
            $claimTypeQuery->where('ClaimStatus', 'D');
        } elseif ($status === 'all') {
        } else {
            $status = 'active';
            $claimTypeQuery->whereIn('ClaimStatus', ['A', 'B']);
        }
        $claimTypes = $claimTypeQuery->get();
        return view('admin.claim_type', compact('claimTypes', 'status'));
    }

    public function claimTypeList()
    {
        $claimType = ClaimType::all();
        return $this->jsonSuccess($claimType, 'Claim type fetched successfully.');
    }

    public function store(Request $request)
    {
        $request->validate([
            'claim_name' => 'required|string|max:255',
            'claim_code' => 'required|string|max:255',
            'focus_code' => 'nullable|string|max:100',
            'color_code' => 'nullable|string|max:50',
            'cg_id' => 'required|integer|exists:claimgroup,cgId',
            'is_active' => 'boolean',
        ]);
        $claimType = ClaimType::create([
            'cgId' => $request->cg_id,
            'ClaimName' => $request->claim_name,
            'ClaimCode' => $request->claim_code,
            'focus_code' => $request->focus_code,
            'color_code' => $request->color_code,
            'ClaimStatus' => $request->is_active ? 'A' : 'D',
            'ClaimCrBy' => Auth::id(),
        ]);
        $permissions = [
            "view claimtype {$claimType->ClaimId}",
            "update claimtype {$claimType->ClaimId}",
            "delete claimtype {$claimType->ClaimId}",
        ];
        foreach ($permissions as $perm) {
            Permission::firstOrCreate(['name' => $perm, 'guard_name' => 'web']);
        }
        $managerRole = Role::where('name', 'Super Admin')->first();
        if ($managerRole) {
            $managerRole->givePermissionTo("view claimtype {$claimType->ClaimId}");
        }
        return $this->jsonSuccess($claimType, 'Claim type created successfully.');
    }

    public function edit(string $id)
    {
        $claimType = ClaimType::findOrFail($id);
        return $this->jsonSuccess($claimType, 'Claim type retrieved successfully.');
    }

    public function update(Request $request, string $id)
    {
        $request->validate([
            'claim_name' => 'required|string|max:255',
            'claim_code' => 'required|string|max:255',
            'focus_code' => 'nullable|string|max:100',
            'color_code' => 'nullable|string|max:50',
            'cg_id' => 'required|integer|exists:claimgroup,cgId',
            'is_active' => 'boolean',
        ]);

        $claimType = ClaimType::findOrFail($id);
        $claimType->update([
            'cgId' => $request->cg_id,
            'ClaimName' => $request->claim_name,
            'ClaimCode' => $request->claim_code,
            'focus_code' => $request->focus_code,
            'color_code' => $request->color_code,
            'ClaimStatus' => $request->is_active ? 'A' : 'D',
        ]);
        return $this->jsonSuccess($claimType, 'Claim type updated successfully.');
    }

    public function destroy(string $id)
    {
        $claimType = ClaimType::findOrFail($id);
        $claimType->delete();
        return $this->jsonSuccess(null, 'Claim type deleted successfully.');
    }

    public function getGroups()
    {
        $groups = ClaimGroup::all();
        return $this->jsonSuccess($groups, 'Claim groups retrieved successfully.');
    }
}
