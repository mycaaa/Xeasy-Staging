<?php

namespace App\Http\Controllers\admin\focus;

use App\Http\Controllers\Controller;
use App\Models\focus\StateCodeMapping;
use App\Models\Permission;
use App\Models\Roles;
use Illuminate\Http\Request;
use Spatie\Permission\Contracts\Role;

class StateCodeMappingController extends Controller
{
    public function index()
    {
        $status = request('status', 'active');
        $stateCodeMappingQuery = StateCodeMapping::query();

        if ($status === 'active') {
            $stateCodeMappingQuery->where('is_active', 1);
        } elseif ($status === 'inactive') {
            $stateCodeMappingQuery->where('is_active', 0);
        }

        $stateCodeMappings = $stateCodeMappingQuery->orderBy('state_name')->get();
        return view('admin.focus.fc_state_code_mapping', compact('stateCodeMappings', 'status'));
    }

    public function store(Request $request)
    {
        $request->validate([
            'state_name' => 'required|string|max:100|unique:fc_state_code_mapping,state_name',
            'state_code' => 'required|string|max:2',
            'is_active' => 'boolean',
        ]);

        $stateCodeMapping = StateCodeMapping::create([
            'state_name' => $request->state_name,
            'state_code' => $request->state_code,
            'is_active' => $request->is_active ?? 1,
            'created_at' => now(),
        ]);

        $permissions = [
            "view statecodemapping {$stateCodeMapping->id}",
            "update statecodemapping {$stateCodeMapping->id}",
            "delete statecodemapping {$stateCodeMapping->id}",
        ];

        foreach ($permissions as $perm) {
            Permission::firstOrCreate(['name' => $perm, 'guard_name' => 'web']);
        }

        $managerRole = Roles::where('name', 'Super Admin')->first();
        if ($managerRole) {
            $managerRole->givePermissionTo("view statecodemapping {$stateCodeMapping->id}");
        }

        return $this->jsonSuccess($stateCodeMapping, 'State code mapping created successfully.');
    }

    public function edit(string $id)
    {
        $stateCodeMapping = StateCodeMapping::findOrFail($id);
        return $this->jsonSuccess($stateCodeMapping, 'State code mapping retrieved successfully.');
    }

    public function update(Request $request, string $id)
    {
        $request->validate([
            'state_name' => 'required|string|max:100|unique:fc_state_code_mapping,state_name,' . $id,
            'state_code' => 'required|string|max:2',
            'is_active' => 'boolean',
        ]);

        $stateCodeMapping = StateCodeMapping::findOrFail($id);
        $stateCodeMapping->update([
            'state_name' => $request->state_name,
            'state_code' => $request->state_code,
            'is_active' => $request->is_active ?? 1,
        ]);

        return $this->jsonSuccess($stateCodeMapping, 'State code mapping updated successfully.');
    }

    public function destroy(string $id)
    {
        $stateCodeMapping = StateCodeMapping::findOrFail($id);
        $stateCodeMapping->delete();
        return $this->jsonSuccess(null, 'State code mapping deleted successfully.');
    }
}
