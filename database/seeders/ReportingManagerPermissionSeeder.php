<?php

namespace Database\Seeders;

use App\Models\Permission;
use App\Models\PermissionGroup;
use Illuminate\Database\Seeder;

class ReportingManagerPermissionSeeder extends Seeder
{
    public function run()
    {
        // Find or create the permission group for Reporting Manager
        $group = PermissionGroup::firstOrCreate(
            ['name' => 'Reporting Manager'],
            ['status' => 1]
        );

        // Create the permission if it doesn't exist
        $permission = Permission::firstOrCreate(
            ['permission_key' => 'reporting_manager_dashboard'],
            [
                'name' => 'Reporting Manager Dashboard',
                'permission_group_id' => $group->id,
                'status' => 1,
                'guard_name' => 'web',
                'created_by' => 1,
                'updated_by' => 1,
            ]
        );
    }
}
