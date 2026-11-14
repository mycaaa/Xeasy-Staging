<?php

use App\Models\Menu as MasterMenu;
use App\Models\User;
use App\Models\Permission;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;

if (!function_exists('getMenuTree')) {
    function getMenuTree($user)
    {
        $menuModel = new MasterMenu();
        $menuModel->setConnection('expense');
        $query = $menuModel->where('status', 1);

        // Version Control menu is strictly restricted to Employee IDs 2081 & 1729 only
        $empId = (string) ($user->employee_id ?? session('employee_id'));
        if (!in_array($empId, ['2081', '1729'], true)) {
            $query->where(function ($q) {
                $q->where('url', 'not like', '%version-control%')
                  ->where('title', 'not like', '%Version Control%');
            });
        }

        // ACCESS-01 (decided 10 Nov 2026): the Approver menus only for people who approve (direct
        // reports or approvals this year) and staff; the HOD advance report only for HODs
        if ($user && ! \App\Support\RecordAccess::isStaff($user)) {
            if (! \App\Support\RecordAccess::isApprover($user)) {
                $query->where(function ($q) {
                    $q->whereNull('url')->orWhere(function ($w) {
                        $w->where('url', 'not regexp', '(^|/)(approver|vnr/approver|vvnr/approver|nursery/approver|approver-dashboard|approver-module)(/|$)');
                    });
                })->where('title', 'not like', 'Approver%');
            }
            if (! \App\Support\HodAccess::allowed($user)) {
                $query->where(function ($q) {
                    $q->whereNull('url')->orWhere('url', 'not like', '%advanceReport%');
                })->where('title', 'not like', '%HOD%');
            }
            // KM-CAP-01: the km limit approvals menu only for HODs with something to decide
            $me = (int) \App\Support\RecordAccess::selfId($user);
            $kmConn = (string) (session('expense_connection') ?: 'expense');
            $hasKm = false;
            try {
                $hasKm = \Illuminate\Support\Facades\Schema::connection($kmConn)->hasTable('km_cap_approvals')
                    && \Illuminate\Support\Facades\DB::connection($kmConn)->table('km_cap_approvals')->where('hod_id', $me)->exists();
            } catch (\Throwable $e) {
            }
            if (! $hasKm) {
                $query->where(function ($q) {
                    $q->whereNull('url')->orWhere('url', '!=', 'km-cap-approvals');
                });
            }
        }

        // MENU-LATE-01 (13 Nov 2026): Late Bill Approval only for those who can approve late bills: admins, and in
        // VNR / Nursery the BU General Managers and approvers (final approvers have a team or approvals); VVNR admin only
        // LATE-PUNCH-02: no Late Bill Approval menu while late bills need no approval
        if (! config('claim_rules.late_punch.force_enable', false)) {
            $query->where(function ($q) {
                $q->whereNull('url')->orWhere('url', '!=', 'late-bills');
            });
        } elseif ($user && ! \App\Services\ClaimRules\LateClaim::isAdmin($user)) {
            $mayApproveLate = false;
            if ((int) session('company_id', 1) !== 11) {
                $me = (int) \App\Support\RecordAccess::selfId($user);
                try {
                    $g = $me > 0 ? \App\Services\Odometer\BackdateApproval::general($me) : null;
                    $mayApproveLate = ($g && (int) ($g->BUId ?? 0) > 0 && \App\Services\Odometer\BackdateApproval::isBuGeneralManager($me, (int) $g->BUId))
                        || \App\Support\RecordAccess::isApprover($user);
                } catch (\Throwable $e) {
                    $mayApproveLate = false;
                }
            }
            if (! $mayApproveLate) {
                $query->where(function ($q) {
                    $q->whereNull('url')->orWhere('url', '!=', 'late-bills');
                });
            }
        }

        return $query->orderBy('order')->get();
    }
    function buildMenuTree($menuItems, $parentId = null)
    {
        $branch = [];
        foreach ($menuItems as $menu) {
            if ($menu->parent_id == $parentId) {
                $menu->children = buildMenuTree($menuItems, $menu->id);
                $branch[] = $menu;
            }
        }
        return $branch;
    }
    function hasChildren($menuItems, $parentId)
    {
        return $menuItems->contains('parent_id', $parentId);
    }
    function renderMenuTree($menuTree, $menuItems, $level = 1)
    {
        if (empty($menuTree)) {
            return;
        }
        static $permissionCache = [];
        $currentCompany = (string)session('company_id');
        $ulClass = ($level === 1) ? 'nav flex-column' : 'nav nav-sm flex-column';
        echo '<ul class="' . $ulClass . '">';
        foreach ($menuTree as $menu) {
            // Version Control is strictly restricted to Employee IDs 2081 & 1729 only
            if ($menu->url === 'version-control' || str_contains($menu->url, 'version-control') || $menu->title === 'Version Control') {
                $empId = (string) (Auth::user()?->employee_id ?? session('employee_id'));
                if (!in_array($empId, ['2081', '1729'], true)) {
                    continue;
                }
            }

            if (empty($menu->company_access_id)) {
                continue;
            }
            $allowedCompanies = array_filter(array_map('trim', explode(',', $menu->company_access_id)));
            if (empty($allowedCompanies)) {
                continue;
            }
            if (!in_array($currentCompany, $allowedCompanies, strict: true)) {
                continue;
            }
            if (!empty($menu->permission_name)) {
                if ($permissionCache === []) {
                    // PERF-01: one query (cached 10 min) for the whole menu instead of one per menu item
                    $permissionCache = \Illuminate\Support\Facades\Cache::remember('menu_permission_map', 600, function () {
                        $map = [];
                        $rows = DB::connection('expense')->table('permissions')->orderBy('id')->get(['name', 'permission_key']);
                        foreach ($rows as $p) {
                            if (!empty($p->permission_key) && !isset($map[$p->permission_key])) {
                                $map[$p->permission_key] = $p->name;
                            }
                        }
                        foreach ($rows as $p) {
                            $map[$p->name] = $map[$p->name] ?? $p->name;
                        }
                        return $map ?: ['__none__' => '__none__'];
                    });
                }
                if (!isset($permissionCache[$menu->permission_name])) {
                    $permissionCache[$menu->permission_name] = $menu->permission_name;
                }
                if (!Auth::user()->can($permissionCache[$menu->permission_name])) {
                    continue;
                }
            }
            $hasChildren = hasChildren($menuItems, $menu->id);
            $collapseId = 'sidebar' . $menu->id;
            $iconClass = $menu->icon ? htmlspecialchars($menu->icon) : '';
            echo '<li class="nav-item">';
            if ($hasChildren) {
                echo '<a class="nav-link menu-link collapsed"
                    href="#' . $collapseId . '"
                    data-bs-toggle="collapse"
                    role="button"
                    aria-expanded="false"
                    aria-controls="' . $collapseId . '">';
                if ($iconClass) echo '<i class="' . $iconClass . '"></i> ';
                echo '<span data-key="' . htmlspecialchars($menu->data_key) . '">' . htmlspecialchars($menu->title) . '</span>';
                echo '</a>';
                echo '<div class="collapse menu-dropdown" id="' . $collapseId . '">';
                renderMenuTree($menu->children, $menuItems, $level + 1);
                echo '</div>';
            } else {
                $childClass = ($level === 1) ? 'nav-link menu-link' : 'nav-link';
                echo '<a href="' . url($menu->url) . '" class="' . $childClass . '">';
                if ($iconClass) echo '<i class="' . $iconClass . '"></i> ';
                echo '<span data-key="' . htmlspecialchars($menu->data_key) . '">' . htmlspecialchars($menu->title) . '</span>';
                echo '</a>';
            }
            echo '</li>';
        }
        echo '</ul>';
    }
}
