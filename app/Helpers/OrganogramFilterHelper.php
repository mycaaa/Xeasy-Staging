<?php

namespace App\Helpers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class OrganogramFilterHelper
{
    /**
     * Retrieve all 9 organogram levels + eligibility policy options from HRIMS.
     */
    public static function getOrganogramFilterOptions($companyId = null): array
    {
        $companyId = $companyId ?: session('company_id', 1);
        $cacheKey = "organogram_filter_options_{$companyId}";

        return \Illuminate\Support\Facades\Cache::remember($cacheKey, 3600, function () use ($companyId) {
            $hrimsConn = session('hrims_connection') ?: DatabaseSwitcher::getHrimsConnection($companyId);

            try {
                $functions = DB::connection($hrimsConn)->table('core_functions')
                    ->where('is_active', 1)
                    ->orderBy('function_name')
                    ->get(['id', 'function_name']);
            } catch (\Exception $e) {
                Log::warning('OrganogramFilterHelper: core_functions error: ' . $e->getMessage());
                $functions = collect([]);
            }

            try {
                $verticals = DB::connection($hrimsConn)->table('core_verticals')
                    ->where('is_active', 1)
                    ->orderBy('vertical_name')
                    ->get(['id', 'vertical_name']);
            } catch (\Exception $e) {
                Log::warning('OrganogramFilterHelper: core_verticals error: ' . $e->getMessage());
                $verticals = collect([]);
            }

            try {
                $departments = DB::connection($hrimsConn)->table('core_departments')
                    ->where('is_active', 1)
                    ->orderBy('department_name')
                    ->get(['id', 'department_name']);
            } catch (\Exception $e) {
                Log::warning('OrganogramFilterHelper: core_departments error: ' . $e->getMessage());
                $departments = collect([]);
            }

            try {
                $sub_departments = DB::connection($hrimsConn)->table('core_sub_department_master')
                    ->where('is_active', 1)
                    ->orderBy('sub_department_name')
                    ->get(['id', 'sub_department_name']);
            } catch (\Exception $e) {
                Log::warning('OrganogramFilterHelper: core_sub_department_master error: ' . $e->getMessage());
                $sub_departments = collect([]);
            }

            try {
                $business_units = DB::connection($hrimsConn)->table('core_business_unit')
                    ->where('is_active', 1)
                    ->orderBy('business_unit_name')
                    ->get(['id', 'business_unit_name']);
            } catch (\Exception $e) {
                Log::warning('OrganogramFilterHelper: core_business_unit error: ' . $e->getMessage());
                $business_units = collect([]);
            }

            try {
                $zones = DB::connection($hrimsConn)->table('core_zones')
                    ->where('is_active', 1)
                    ->orderBy('zone_name')
                    ->get(['id', 'zone_name']);
            } catch (\Exception $e) {
                Log::warning('OrganogramFilterHelper: core_zones error: ' . $e->getMessage());
                $zones = collect([]);
            }

            try {
                $regions = DB::connection($hrimsConn)->table('core_regions')
                    ->where('is_active', 1)
                    ->orderBy('region_name')
                    ->get(['id', 'region_name']);
            } catch (\Exception $e) {
                Log::warning('OrganogramFilterHelper: core_regions error: ' . $e->getMessage());
                $regions = collect([]);
            }

            try {
                $territories = DB::connection($hrimsConn)->table('core_territory')
                    ->whereIn('id', function($sub) use ($hrimsConn, $companyId) {
                        $sub->select('TerrId')->from('hrm_employee_general as eg')
                            ->join('hrm_employee as e', 'eg.EmployeeID', '=', 'e.EmployeeID')
                            ->where('e.CompanyId', $companyId)
                            ->where('eg.TerrId', '>', 0)
                            ->distinct();
                    })
                    ->orderBy('territory_name')
                    ->get(['id', 'territory_name']);
                if ($territories->isEmpty()) {
                    $territories = DB::connection($hrimsConn)->table('core_territory')
                        ->orderBy('territory_name')
                        ->get(['id', 'territory_name']);
                }
            } catch (\Exception $e) {
                Log::warning('OrganogramFilterHelper: core_territory error: ' . $e->getMessage());
                $territories = collect([]);
            }

            try {
                $hqs = DB::connection($hrimsConn)->table('core_city_village_by_state')
                    ->whereIn('id', function($sub) use ($hrimsConn, $companyId) {
                        $sub->select('HqId')->from('hrm_employee_general as eg')
                            ->join('hrm_employee as e', 'eg.EmployeeID', '=', 'e.EmployeeID')
                            ->where('e.CompanyId', $companyId)
                            ->where('eg.HqId', '>', 0)
                            ->distinct();
                    })
                    ->orderBy('city_village_name')
                    ->get(['id', 'city_village_name']);
                if ($hqs->isEmpty()) {
                    $hqs = DB::connection($hrimsConn)->table('core_city_village_by_state')
                        ->where('is_active', 1)
                        ->orderBy('city_village_name')
                        ->limit(500)
                        ->get(['id', 'city_village_name']);
                }
            } catch (\Exception $e) {
                try {
                    $hqs = DB::connection($hrimsConn)->table('core_city_village_by_state')
                        ->orderBy('city_village_name')
                        ->limit(500)
                        ->get(['id', 'city_village_name']);
                } catch (\Exception $e2) {
                    Log::warning('OrganogramFilterHelper: core_city_village_by_state error: ' . $e2->getMessage());
                    $hqs = collect([]);
                }
            }

            try {
                $eligibility_policy = DB::connection($hrimsConn)->table('hrm_master_eligibility_policy')
                    ->where('CompanyId', $companyId)
                    ->orderBy('PolicyName')
                    ->get(['PolicyId', 'PolicyName']);
                if ($eligibility_policy->isEmpty()) {
                    $eligibility_policy = DB::connection($hrimsConn)->table('hrm_master_eligibility_policy')
                        ->orderBy('PolicyName')
                        ->get(['PolicyId', 'PolicyName']);
                }
            } catch (\Exception $e) {
                Log::warning('OrganogramFilterHelper: hrm_master_eligibility_policy error: ' . $e->getMessage());
                $eligibility_policy = collect([]);
            }

            return [
                'functions'          => $functions,
                'verticals'          => $verticals,
                'departments'        => $departments,
                'sub_departments'    => $sub_departments,
                'business_units'     => $business_units,
                'zones'              => $zones,
                'regions'            => $regions,
                'territories'        => $territories,
                'hqs'                => $hqs,
                'eligibility_policy' => $eligibility_policy,
            ];
        });
    }

    /**
     * Render the organogram filter offcanvas drawer server-side.
     *
     * @param array $extraData Additional view parameters (e.g., drawerId, drawerTitle, extraFieldsView, extraFieldsData, buttonLayout, etc.)
     * @param int|null $companyId
     * @return string
     */
    public static function renderDrawer(array $extraData = [], $companyId = null): string
    {
        $options = self::getOrganogramFilterOptions($companyId);
        return view('partials.organogram-filters-drawer', array_merge($options, $extraData))->render();
    }

    /**
     * Parse raw input into a sanitized array of integer IDs.
     */
    public static function parseFilterIds($val): array
    {
        if (empty($val)) {
            return [];
        }
        if (is_string($val)) {
            $val = explode(',', $val);
        }
        return array_values(array_filter(array_map('intval', (array) $val), fn($id) => $id > 0));
    }

    /**
     * Extract organogram filters from request (supporting multiple aliases).
     */
    public static function extractFilters(Request $request): array
    {
        $fnIds = self::parseFilterIds($request->input('function_ids') ?: $request->input('functionSelect'));
        if ($request->filled('function_id')) {
            $topFn = self::parseFilterIds($request->input('function_id'));
            $fnIds = array_values(array_unique(array_merge($fnIds, $topFn)));
        }

        $deptIds = self::parseFilterIds($request->input('department_ids') ?: $request->input('departmentSelect'));
        if ($request->filled('department_id')) {
            $topDept = self::parseFilterIds($request->input('department_id'));
            $deptIds = array_values(array_unique(array_merge($deptIds, $topDept)));
        }

        return [
            'function_ids'       => $fnIds,
            'vertical_ids'       => self::parseFilterIds($request->input('vertical_ids') ?: $request->input('verticalSelect')),
            'department_ids'     => $deptIds,
            'sub_department_ids' => self::parseFilterIds($request->input('sub_department_ids') ?: $request->input('subDepartmentSelect')),
            'bu_ids'             => self::parseFilterIds($request->input('bu_ids') ?: $request->input('buSelect') ?: $request->input('bu_id')),
            'zone_ids'           => self::parseFilterIds($request->input('zone_ids') ?: $request->input('zoneSelect')),
            'region_ids'         => self::parseFilterIds($request->input('region_ids') ?: $request->input('regionSelect')),
            'territory_ids'      => self::parseFilterIds($request->input('territory_ids') ?: $request->input('territorySelect')),
            'hq_ids'             => self::parseFilterIds($request->input('hq_ids') ?: $request->input('hqSelect')),
            'policy_ids'         => self::parseFilterIds($request->input('policy_ids') ?: $request->input('policySelect')),
        ];
    }

    /**
     * Apply organogram filters to a QueryBuilder / Eloquent query instance.
     * Supports both ($query, $filters, $egAlias, $empIdCol, $hrimsDb)
     * and ($query, $filters, $egAlias, $hrimsDb, $empIdCol).
     */
    public static function applyToQuery($query, array $filters, string $egAlias = 'eg', $arg4 = null, $arg5 = null)
    {
        $empIdCol = 'e.EmployeeID';
        $hrimsDb = null;

        if (is_string($arg4)) {
            if (str_contains($arg4, '.') || stripos($arg4, 'EmployeeID') !== false || stripos($arg4, 'id') !== false) {
                $empIdCol = $arg4;
                $hrimsDb = is_string($arg5) ? $arg5 : null;
            } else {
                $hrimsDb = $arg4;
                $empIdCol = is_string($arg5) ? $arg5 : 'e.EmployeeID';
            }
        } elseif (is_string($arg5)) {
            if (str_contains($arg5, '.') || stripos($arg5, 'EmployeeID') !== false) {
                $empIdCol = $arg5;
            } else {
                $hrimsDb = $arg5;
            }
        }

        $hrimsDb = $hrimsDb ?: DatabaseSwitcher::getHrimsDatabaseName();

        $functionIds = !empty($filters['function_ids']) ? $filters['function_ids'] : (!empty($filters['function_id']) ? self::parseFilterIds($filters['function_id']) : []);
        if (!empty($functionIds)) {
            $query->whereIn("{$egAlias}.EmpFunction", $functionIds);
        }
        if (!empty($filters['vertical_ids'])) {
            $query->whereIn("{$egAlias}.EmpVertical", $filters['vertical_ids']);
        }
        $deptIds = !empty($filters['department_ids']) ? $filters['department_ids'] : (!empty($filters['department_id']) ? self::parseFilterIds($filters['department_id']) : []);
        if (!empty($deptIds)) {
            $query->whereIn("{$egAlias}.DepartmentId", $deptIds);
        }
        if (!empty($filters['sub_department_ids'])) {
            $query->whereIn("{$egAlias}.SubDepartmentId", $filters['sub_department_ids']);
        }
        if (!empty($filters['bu_ids'])) {
            $query->whereIn("{$egAlias}.BUId", $filters['bu_ids']);
        }
        if (!empty($filters['zone_ids'])) {
            $query->whereIn("{$egAlias}.ZoneId", $filters['zone_ids']);
        }
        if (!empty($filters['region_ids'])) {
            $query->whereIn("{$egAlias}.RegionId", $filters['region_ids']);
        }
        if (!empty($filters['territory_ids'])) {
            $query->whereIn("{$egAlias}.TerrId", $filters['territory_ids']);
        }
        if (!empty($filters['hq_ids'])) {
            $query->whereIn("{$egAlias}.HqId", $filters['hq_ids']);
        }
        if (!empty($filters['policy_ids'])) {
            $query->whereIn($empIdCol, function ($sub) use ($hrimsDb, $filters) {
                $sub->select('EmployeeID')
                    ->from("{$hrimsDb}.hrm_employee_eligibility")
                    ->where('Status', 'A')
                    ->whereIn('VehiclePolicy', $filters['policy_ids']);
            });
        }

        return $query;
    }

    /**
     * Append organogram filters to raw SQL WHERE clauses and bindings.
     */
    public static function appendRawSqlFilters(
        array &$whereClauses,
        array &$bindings,
        array $filters,
        string $gAlias = 'g',
        string $vfmAlias = 'vfm',
        ?string $hrimsDb = null,
        string $empIdExpr = 'COALESCE(NULLIF(c.CrBy, 0), c.FilledBy)'
    ): void {
        $hrimsDb = $hrimsDb ?: DatabaseSwitcher::getHrimsDatabaseName();

        $functionIds = !empty($filters['function_ids']) ? $filters['function_ids'] : (!empty($filters['function_id']) ? self::parseFilterIds($filters['function_id']) : []);
        if (!empty($functionIds)) {
            $placeholders = implode(',', array_fill(0, count($functionIds), '?'));
            $whereClauses[] = "COALESCE(NULLIF({$gAlias}.EmpFunction, 0), {$vfmAlias}.org_function_id) IN ({$placeholders})";
            foreach ($functionIds as $fid) {
                $bindings[] = $fid;
            }
        }
        if (!empty($filters['vertical_ids'])) {
            $placeholders = implode(',', array_fill(0, count($filters['vertical_ids']), '?'));
            $whereClauses[] = "{$gAlias}.EmpVertical IN ({$placeholders})";
            foreach ($filters['vertical_ids'] as $vid) {
                $bindings[] = $vid;
            }
        }
        $deptIds = !empty($filters['department_ids']) ? $filters['department_ids'] : (!empty($filters['department_id']) ? self::parseFilterIds($filters['department_id']) : []);
        if (!empty($deptIds)) {
            $placeholders = implode(',', array_fill(0, count($deptIds), '?'));
            $whereClauses[] = "{$gAlias}.DepartmentId IN ({$placeholders})";
            foreach ($deptIds as $did) {
                $bindings[] = $did;
            }
        }
        if (!empty($filters['sub_department_ids'])) {
            $placeholders = implode(',', array_fill(0, count($filters['sub_department_ids']), '?'));
            $whereClauses[] = "{$gAlias}.SubDepartmentId IN ({$placeholders})";
            foreach ($filters['sub_department_ids'] as $sdid) {
                $bindings[] = $sdid;
            }
        }
        if (!empty($filters['bu_ids'])) {
            $placeholders = implode(',', array_fill(0, count($filters['bu_ids']), '?'));
            $whereClauses[] = "{$gAlias}.BUId IN ({$placeholders})";
            foreach ($filters['bu_ids'] as $buid) {
                $bindings[] = $buid;
            }
        }
        if (!empty($filters['zone_ids'])) {
            $placeholders = implode(',', array_fill(0, count($filters['zone_ids']), '?'));
            $whereClauses[] = "{$gAlias}.ZoneId IN ({$placeholders})";
            foreach ($filters['zone_ids'] as $zid) {
                $bindings[] = $zid;
            }
        }
        if (!empty($filters['region_ids'])) {
            $placeholders = implode(',', array_fill(0, count($filters['region_ids']), '?'));
            $whereClauses[] = "{$gAlias}.RegionId IN ({$placeholders})";
            foreach ($filters['region_ids'] as $rid) {
                $bindings[] = $rid;
            }
        }
        if (!empty($filters['territory_ids'])) {
            $placeholders = implode(',', array_fill(0, count($filters['territory_ids']), '?'));
            $whereClauses[] = "{$gAlias}.TerrId IN ({$placeholders})";
            foreach ($filters['territory_ids'] as $tid) {
                $bindings[] = $tid;
            }
        }
        if (!empty($filters['hq_ids'])) {
            $placeholders = implode(',', array_fill(0, count($filters['hq_ids']), '?'));
            $whereClauses[] = "{$gAlias}.HqId IN ({$placeholders})";
            foreach ($filters['hq_ids'] as $hqid) {
                $bindings[] = $hqid;
            }
        }
        if (!empty($filters['policy_ids'])) {
            $placeholders = implode(',', array_fill(0, count($filters['policy_ids']), '?'));
            $whereClauses[] = "{$empIdExpr} IN (SELECT EmployeeID FROM {$hrimsDb}.hrm_employee_eligibility WHERE Status = 'A' AND VehiclePolicy IN ({$placeholders}))";
            foreach ($filters['policy_ids'] as $pid) {
                $bindings[] = $pid;
            }
        }
    }
}
