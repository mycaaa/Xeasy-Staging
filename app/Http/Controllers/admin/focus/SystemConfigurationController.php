<?php

namespace App\Http\Controllers\admin\focus;

use App\Http\Controllers\Controller;
use App\Models\focus\SystemConfiguration;
use Illuminate\Http\Request;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class SystemConfigurationController extends Controller
{
    public function index()
    {
        $status = request('status', 'active');
        $query = SystemConfiguration::query();

        if ($status === 'active') {
            $query->where('is_active', 1);
        } elseif ($status === 'inactive') {
            $query->where('is_active', 0);
        }

        $configurations = $query->orderBy('config_key')->get();
        return view('admin.focus.system_configuration', compact('configurations', 'status'));
    }

    public function store(Request $request)
    {
        $request->validate([
            'config_key' => 'required|string|max:100|unique:system_configuration,config_key',
            'config_value' => 'required|string|max:255',
            'config_type' => 'required|string|in:string,integer,boolean,url',
            'description' => 'nullable|string',
            'is_active' => 'boolean',
        ]);

        $configuration = SystemConfiguration::create([
            'config_key' => $request->config_key,
            'config_value' => $request->config_value,
            'config_type' => $request->config_type,
            'description' => $request->description,
            'is_active' => $request->is_active ?? 1,
        ]);

        $permissions = [
            "view systemconfiguration {$configuration->id}",
            "update systemconfiguration {$configuration->id}",
            "delete systemconfiguration {$configuration->id}",
        ];

        foreach ($permissions as $perm) {
            Permission::firstOrCreate(['name' => $perm, 'guard_name' => 'web']);
        }

        $managerRole = Role::where('name', 'Super Admin')->first();
        if ($managerRole) {
            $managerRole->givePermissionTo("view systemconfiguration {$configuration->id}");
        }

        return $this->jsonSuccess($configuration, 'System configuration created successfully.');
    }

    public function edit(string $id)
    {
        $configuration = SystemConfiguration::findOrFail($id);
        return $this->jsonSuccess($configuration, 'System configuration retrieved successfully.');
    }

    public function update(Request $request, string $id)
    {
        $request->validate([
            'config_key' => 'required|string|max:100|unique:system_configuration,config_key,' . $id,
            'config_value' => 'required|string|max:255',
            'config_type' => 'required|string|in:string,integer,boolean,url',
            'description' => 'nullable|string',
            'is_active' => 'boolean',
        ]);

        $configuration = SystemConfiguration::findOrFail($id);
        $configuration->update([
            'config_key' => $request->config_key,
            'config_value' => $request->config_value,
            'config_type' => $request->config_type,
            'description' => $request->description,
            'is_active' => $request->is_active ?? 1,
        ]);

        return $this->jsonSuccess($configuration, 'System configuration updated successfully.');
    }

    public function destroy(string $id)
    {
        $configuration = SystemConfiguration::findOrFail($id);
        $configuration->delete();
        return $this->jsonSuccess(null, 'System configuration deleted successfully.');
    }

    // Helper method to get configuration value by key
    public static function getValue(string $key, $default = null)
    {
        $config = SystemConfiguration::where('config_key', $key)
            ->where('is_active', 1)
            ->first();

        if (!$config) {
            return $default;
        }

        switch ($config->config_type) {
            case 'integer':
                return (int) $config->config_value;
            case 'boolean':
                return filter_var($config->config_value, FILTER_VALIDATE_BOOLEAN);
            default:
                return $config->config_value;
        }
    }
}
