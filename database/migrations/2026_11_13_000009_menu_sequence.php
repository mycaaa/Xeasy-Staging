<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * MENU-SEQ-01 (13 Nov 2026): side menu in the order of the claim's journey, then money, then reports and
 * administration; sub-menus in the order people use them. Only "order" (and a few titles' capitals) change;
 * items are matched by title, so menus added by hand are left where they are. Safe to run again.
 */
return new class extends Migration
{
    /** top-level title => [order, [child title => order]] */
    private function plan(): array
    {
        return [
            'Dashboard' => [1, []],
            'Employee' => [2, ['Claimant' => 1, 'Approver' => 2, 'Late Bill Approval' => 3, 'Submit Hardcopy Bills' => 4]],
            'Claims' => [3, []],
            'Bill To Company' => [4, ['BTC Cases' => 1, 'Boarding Pass (Company-Booked Tickets)' => 2, 'Boarding Pass' => 2, 'Vendor Boarding-Pass Links' => 3]],
            'Data Punching' => [5, []],
            'Verifier' => [6, ['Verifier Screen' => 1, 'Verify (Running Km)' => 2, 'Employee Vehicles' => 3]],
            'Km Limit Approvals' => [7, []],
            'Finance Claim' => [8, []],
            'Payment' => [9, ['Expense Payments' => 1, 'Advance Approvals' => 2, 'No-UTR Reasons' => 3, 'Focus Export Log' => 4]],
            'Treasury' => [10, ['Expense Instructed' => 1, 'Advance Instructed' => 2]],
            'Reports' => [11, [
                'Claim Reports' => 1, 'Expense Reports' => 2, 'Employee Reports' => 3, 'Vehicle Reports' => 4,
                'Company Multiyear Exp Report' => 5, 'Employee Status Report' => 6, 'Daily Activity' => 7, 'Exception' => 8,
            ]],
            'Notification' => [12, ['Submission Reminder' => 1, 'Email Logs' => 2, 'New Email Logs' => 3]],
            'Master' => [13, ['Users' => 1, 'Roles' => 2, 'Permission' => 3, 'Menu' => 4, 'Core API' => 5]],
            'Settings' => [14, [
                'Claim Type' => 1, 'Activity' => 2, 'City Categories' => 3, 'Other Participant Categories' => 4,
                'Claim Rating Rules' => 5, 'Backdated Approval' => 6, 'Open Month' => 7, 'Advance Request' => 8,
                'New Advance Request' => 9, 'Focus Export' => 10, 'Duplicate Entries' => 11, 'Email Format' => 12,
                'Email Control' => 13, 'Email Setup VVNR' => 14, 'Reminder Settings' => 15, 'Resigned Submission Reminder' => 16,
            ]],
            'Utilities' => [15, ['Vendors' => 1, 'Temp Files' => 2, 'Version Control' => 3]],
        ];
    }

    /** report group => [child title => order] */
    private function reportGroups(): array
    {
        return [
            'Claim Reports' => ['Claim Report' => 1, 'Claim Stage' => 2, 'Claim Stage FY' => 3, 'Claim Amount' => 4,
                'Claim Return And Action' => 5, 'Submission Issues' => 6, 'Punching Change Track' => 7,
                'Stepwise Pending Payment' => 8, 'Not Showing Report' => 9],
            'Expense Reports' => ['MRM Report' => 1, 'Expense Type Report' => 2, 'Expense Tree Report' => 3,
                'Manager Grouped Claims' => 4, 'Activity Report Analysis' => 5, 'High Amount Claims' => 6,
                'Ledger Summary' => 7, 'Employee Ledger (Imprest)' => 8, 'Advance Report' => 9, 'Missing Imprest' => 10,
                'Focus Ledger vs Xeasy' => 11, 'Finance Review Flags' => 12],
            'Employee Reports' => ['Employee Eligibility Report' => 1, 'Top Rating Employee' => 2, 'Employee Status' => 3],
            'Vehicle Reports' => ['Total RunKM' => 1, 'ODO Tracking' => 2, 'Odometer Continuity Report' => 3,
                'Odo Analysis' => 4, 'Employees Multiple Vehicles' => 5],
        ];
    }

    public function up(): void
    {
        if (! Schema::hasTable('menus')) {
            return;
        }
        // capitals only
        foreach (['claim stage FY' => 'Claim Stage FY', 'High amount claim' => 'High Amount Claims', 'Email logs' => 'Email Logs'] as $old => $new) {
            DB::table('menus')->where('title', $old)->update(['title' => $new]);
        }
        $set = fn ($id, int $order) => DB::table('menus')->where('id', $id)->update(['order' => $order]);
        $child = fn ($parentId, string $title) => DB::table('menus')->where('parent_id', $parentId)->where('title', $title)->value('id');

        $groups = $this->reportGroups();
        foreach ($this->plan() as $title => [$order, $children]) {
            $id = DB::table('menus')->whereNull('parent_id')->where('title', $title)->orderBy('id')->value('id');
            if (! $id) {
                continue;
            }
            $set($id, $order);
            foreach ($children as $ct => $co) {
                if ($cid = $child($id, $ct)) {
                    $set($cid, $co);
                    foreach ($groups[$ct] ?? [] as $gt => $go) {
                        if ($gid = $child($cid, $gt)) {
                            $set($gid, $go);
                        }
                    }
                }
            }
        }
    }

    public function down(): void
    {
        // order only; nothing to undo
    }
};
