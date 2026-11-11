<?php

namespace Database\Seeders;

use App\Models\Permission;
use App\Models\PermissionGroup;
use Illuminate\Database\Seeder;

class VerifierRevertPermissionSeeder extends Seeder
{
    public function run()
    {
        $group = PermissionGroup::firstOrCreate(
            ['name' => 'Verifier'],
            ['status' => 1]
        );

        Permission::firstOrCreate(
            ['name' => 'verifier_revert'],
            [
                'permission_key' => 'Verifier Revert',
                'permission_group_id' => $group->id,
                'status' => 1,
                'guard_name' => 'web',
                'created_by' => 1,
                'updated_by' => 1,
            ]
        );
    }
}
