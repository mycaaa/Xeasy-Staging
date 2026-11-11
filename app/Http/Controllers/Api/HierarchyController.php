<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Exception;

class HierarchyController extends Controller
{
    /**
     * Get list of Business Units
     */
    public function getBusinessUnits(Request $request)
    {
        try {
            $query = DB::connection('hrims')
                ->table('core_business_unit')
                ->where('is_active', '1')
                ->select([
                    'id',
                    'business_unit_name',
                    'business_unit_code',
                    'numeric_code',
                    'vertical_id'
                ])
                ->orderBy('business_unit_name');

            $businessUnits = $query->get();

            return $this->jsonSuccess($businessUnits, 'Business units retrieved successfully');
        } catch (Exception $e) {
            \Log::error('Error fetching business units: ' . $e->getMessage());
            return $this->jsonError('Failed to retrieve business units: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Get list of Zones (optionally filtered by Business Unit)
     */
    public function getZones(Request $request)
    {
        try {
            $buId = $request->get('bu_id');

            $query = DB::connection('hrims')
                ->table('core_zones as z')
                ->where('z.is_active', '1')
                ->select([
                    'z.id',
                    'z.zone_name',
                    'z.zone_code',
                    'z.numeric_code',
                    'z.vertical_id'
                ]);

            // Filter by Business Unit if provided
            if ($buId) {
                $query->join('core_bu_zone_mapping as bzm', 'z.id', '=', 'bzm.zone_id')
                    ->where('bzm.business_unit_id', $buId)
                    ->addSelect('bzm.business_unit_id');
            }

            $zones = $query->orderBy('z.zone_name')->get();

            return $this->jsonSuccess($zones, 'Zones retrieved successfully');
        } catch (Exception $e) {
            \Log::error('Error fetching zones: ' . $e->getMessage());
            return $this->jsonError('Failed to retrieve zones: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Get list of Regions (optionally filtered by Zone)
     */
    public function getRegions(Request $request)
    {
        try {
            $zoneId = $request->get('zone_id');

            $query = DB::connection('hrims')
                ->table('core_regions as r')
                ->where('r.is_active', '1')
                ->select([
                    'r.id',
                    'r.region_name',
                    'r.region_code',
                    'r.numeric_code',
                    'r.vertical_id'
                ]);

            // Filter by Zone if provided
            if ($zoneId) {
                $query->join('core_zone_region_mapping as zrm', 'r.id', '=', 'zrm.region_id')
                    ->where('zrm.zone_id', $zoneId)
                    ->addSelect('zrm.zone_id');
            }

            $regions = $query->orderBy('r.region_name')->get();

            return $this->jsonSuccess($regions, 'Regions retrieved successfully');
        } catch (Exception $e) {
            \Log::error('Error fetching regions: ' . $e->getMessage());
            return $this->jsonError('Failed to retrieve regions: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Get list of Territories (optionally filtered by Region)
     */
    public function getTerritories(Request $request)
    {
        try {
            $regionId = $request->get('region_id');

            $query = DB::connection('hrims')
                ->table('core_city_village_by_state as t')
                ->where('t.is_active', '1')
                ->select([
                    't.id',
                    't.city_village_name',
                    't.city_village_code',
                    't.state_id',
                    't.district_id',
                    't.division_name'
                ]);

            // Filter by Region if provided (using core_terr_mapping)
            if ($regionId) {
                $query->join('core_terr_mapping as tm', function($join) use ($regionId) {
                    $join->on('t.id', '=', DB::raw('CAST(tm.HqFC AS CHAR)'))
                        ->orOn('t.id', '=', DB::raw('CAST(tm.HqVC AS CHAR)'));
                })
                ->where(function($q) use ($regionId) {
                    $q->where('tm.FC_RegionId', $regionId)
                      ->orWhere('tm.VC_RegionId', $regionId);
                })
                ->distinct();
            }

            $territories = $query->orderBy('t.city_village_name')->get();

            return $this->jsonSuccess($territories, 'Territories retrieved successfully');
        } catch (Exception $e) {
            \Log::error('Error fetching territories: ' . $e->getMessage());
            return $this->jsonError('Failed to retrieve territories: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Get BU-Zone mappings
     */
    public function getBuZoneMappings(Request $request)
    {
        try {
            $buId = $request->get('bu_id');

            $query = DB::connection('hrims')
                ->table('core_bu_zone_mapping as bzm')
                ->join('core_business_unit as bu', 'bzm.business_unit_id', '=', 'bu.id')
                ->join('core_zones as z', 'bzm.zone_id', '=', 'z.id')
                ->select([
                    'bzm.id',
                    'bzm.business_unit_id',
                    'bu.business_unit_name',
                    'bu.business_unit_code',
                    'bzm.zone_id',
                    'z.zone_name',
                    'z.zone_code',
                    'bzm.name as mapping_name',
                    'bzm.effective_from',
                    'bzm.effective_to'
                ]);

            if ($buId) {
                $query->where('bzm.business_unit_id', $buId);
            }

            $mappings = $query->orderBy('bu.business_unit_name')->orderBy('z.zone_name')->get();

            return $this->jsonSuccess($mappings, 'BU-Zone mappings retrieved successfully');
        } catch (Exception $e) {
            \Log::error('Error fetching BU-Zone mappings: ' . $e->getMessage());
            return $this->jsonError('Failed to retrieve BU-Zone mappings: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Get Zone-Region mappings
     */
    public function getZoneRegionMappings(Request $request)
    {
        try {
            $zoneId = $request->get('zone_id');

            $query = DB::connection('hrims')
                ->table('core_zone_region_mapping as zrm')
                ->join('core_zones as z', 'zrm.zone_id', '=', 'z.id')
                ->join('core_regions as r', 'zrm.region_id', '=', 'r.id')
                ->select([
                    'zrm.id',
                    'zrm.zone_id',
                    'z.zone_name',
                    'z.zone_code',
                    'zrm.region_id',
                    'r.region_name',
                    'r.region_code',
                    'zrm.name as mapping_name',
                    'zrm.effective_from',
                    'zrm.effective_to'
                ]);

            if ($zoneId) {
                $query->where('zrm.zone_id', $zoneId);
            }

            $mappings = $query->orderBy('z.zone_name')->orderBy('r.region_name')->get();

            return $this->jsonSuccess($mappings, 'Zone-Region mappings retrieved successfully');
        } catch (Exception $e) {
            \Log::error('Error fetching Zone-Region mappings: ' . $e->getMessage());
            return $this->jsonError('Failed to retrieve Zone-Region mappings: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Get Territory mappings
     */
    public function getTerritoryMappings(Request $request)
    {
        try {
            $regionId = $request->get('region_id');

            $query = DB::connection('hrims')
                ->table('core_terr_mapping as tm')
                ->leftJoin('core_city_village_by_state as fc', 'tm.HqFC', '=', 'fc.id')
                ->leftJoin('core_city_village_by_state as vc', 'tm.HqVC', '=', 'vc.id')
                ->select([
                    'tm.id',
                    'tm.HqFC',
                    'fc.city_village_name as fc_territory_name',
                    'fc.city_village_code as fc_territory_code',
                    'tm.FC_BUId',
                    'tm.FC_ZoneId',
                    'tm.FC_RegionId',
                    'tm.HqVC',
                    'vc.city_village_name as vc_territory_name',
                    'vc.city_village_code as vc_territory_code',
                    'tm.VC_BUId',
                    'tm.VC_ZoneId',
                    'tm.VC_RegionId'
                ]);

            if ($regionId) {
                $query->where(function($q) use ($regionId) {
                    $q->where('tm.FC_RegionId', $regionId)
                      ->orWhere('tm.VC_RegionId', $regionId);
                });
            }

            $mappings = $query->get();

            return $this->jsonSuccess($mappings, 'Territory mappings retrieved successfully');
        } catch (Exception $e) {
            \Log::error('Error fetching territory mappings: ' . $e->getMessage());
            return $this->jsonError('Failed to retrieve territory mappings: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Get complete hierarchy tree
     */
    public function getHierarchyTree(Request $request)
    {
        try {
            $buId = $request->get('bu_id');
            $zoneId = $request->get('zone_id');
            $regionId = $request->get('region_id');

            // Get Business Units
            $buQuery = DB::connection('hrims')
                ->table('core_business_unit')
                ->where('is_active', '1')
                ->select(['id', 'business_unit_name', 'business_unit_code']);

            if ($buId) {
                $buQuery->where('id', $buId);
            }

            $businessUnits = $buQuery->get();

            $hierarchyTree = [];

            foreach ($businessUnits as $bu) {
                $buData = [
                    'id' => $bu->id,
                    'name' => $bu->business_unit_name,
                    'code' => $bu->business_unit_code,
                    'zones' => []
                ];

                // Get Zones for this BU
                $zonesQuery = DB::connection('hrims')
                    ->table('core_zones as z')
                    ->join('core_bu_zone_mapping as bzm', 'z.id', '=', 'bzm.zone_id')
                    ->where('bzm.business_unit_id', $bu->id)
                    ->where('z.is_active', '1')
                    ->select(['z.id', 'z.zone_name', 'z.zone_code']);

                if ($zoneId) {
                    $zonesQuery->where('z.id', $zoneId);
                }

                $zones = $zonesQuery->get();

                foreach ($zones as $zone) {
                    $zoneData = [
                        'id' => $zone->id,
                        'name' => $zone->zone_name,
                        'code' => $zone->zone_code,
                        'regions' => []
                    ];

                    // Get Regions for this Zone
                    $regionsQuery = DB::connection('hrims')
                        ->table('core_regions as r')
                        ->join('core_zone_region_mapping as zrm', 'r.id', '=', 'zrm.region_id')
                        ->where('zrm.zone_id', $zone->id)
                        ->where('r.is_active', '1')
                        ->select(['r.id', 'r.region_name', 'r.region_code']);

                    if ($regionId) {
                        $regionsQuery->where('r.id', $regionId);
                    }

                    $regions = $regionsQuery->get();

                    foreach ($regions as $region) {
                        $zoneData['regions'][] = [
                            'id' => $region->id,
                            'name' => $region->region_name,
                            'code' => $region->region_code
                        ];
                    }

                    $buData['zones'][] = $zoneData;
                }

                $hierarchyTree[] = $buData;
            }

            return $this->jsonSuccess($hierarchyTree, 'Hierarchy tree retrieved successfully');
        } catch (Exception $e) {
            \Log::error('Error fetching hierarchy tree: ' . $e->getMessage());
            return $this->jsonError('Failed to retrieve hierarchy tree: ' . $e->getMessage(), 500);
        }
    }
}
