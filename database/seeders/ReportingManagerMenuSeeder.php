<?php

namespace Database\Seeders;

use App\Models\Menu;
use Illuminate\Database\Seeder;

class ReportingManagerMenuSeeder extends Seeder
{
    public function run()
    {
        // Check if the Reporting Manager Dashboard menu item already exists
        $exists = Menu::where('url', 'reporting-manager/dashboard')->exists();
        
        if (!$exists) {
            // Create the main Reporting Manager menu item
            $menu = Menu::create([
                'title' => 'Reporting Manager Dashboard',
                'url' => 'reporting-manager/dashboard',
                'icon' => 'ri-user-follow-line',
                'parent_id' => null,
                'permission_name' => 'reporting_manager_dashboard',
                'order' => 15,
                'data_key' => 't-reporting-manager-dashboard',
                'status' => 1,
                'created_by' => 1,
                'updated_by' => 1,
            ]);

            // Create sub-menu items for Reporting Manager Dashboard
            Menu::create([
                'title' => 'Approvals',
                'url' => 'reporting-manager/approvals',
                'icon' => 'ri-check-double-line',
                'parent_id' => $menu->id,
                'permission_name' => 'reporting_manager_dashboard',
                'order' => 1,
                'data_key' => 't-reporting-manager-approvals',
                'status' => 1,
                'created_by' => 1,
                'updated_by' => 1,
            ]);

            Menu::create([
                'title' => 'Team',
                'url' => 'reporting-manager/team',
                'icon' => 'ri-team-line',
                'parent_id' => $menu->id,
                'permission_name' => 'reporting_manager_dashboard',
                'order' => 2,
                'data_key' => 't-reporting-manager-team',
                'status' => 1,
                'created_by' => 1,
                'updated_by' => 1,
            ]);
        }
    }
}
