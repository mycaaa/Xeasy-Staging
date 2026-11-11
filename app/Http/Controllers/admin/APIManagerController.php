<?php

namespace App\Http\Controllers\admin;

use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Auth;
use App\Models\APIManager;
use App\Models\ColumnFieldMapping;
use App\Http\Requests\StoreAPIManagerRequest;
use App\Http\Requests\UpdateAPIManagerRequest;
use Illuminate\Support\Facades\DB;
use Illuminate\Http\Request;

/**
 * This controller manages API Manager records in the admin area.
 */
class APIManagerController extends Controller
{
    /**
     * Show the list of all APIs.
     */
    public function index()
    {
        $apis = APIManager::all();
        return view('admin.api_manager', compact('apis'));
    }

    /**
     * Show the form for creating a new API.
     * Not in use currently.
     */
    public function create()
    {
        //
    }

    /**
     * Store a newly created API in the database.
     */
    public function store(StoreAPIManagerRequest $request)
    {
        $api = APIManager::create([
            'claim_id' => $request->claim_id,
            'name' => $request->api_name,
            'endpoint' => $request->endpoint,
            'status' => $request->status,
            'created_by' => Auth::id(),
            'created_at' => now(),
        ]);

        return $this->jsonSuccess($api, 'API created successfully.');
    }

    /**
     * Display the specified API details.
     * Not in use currently.
     */
    public function show(string $id)
    {
        //
    }

    /**
     * Show the form for editing the specified API.
     */
    public function edit(string $id)
    {
        $api = APIManager::findOrFail($id);
        return $this->jsonSuccess($api, 'API retrieved successfully.');
    }

    /**
     * Update the specified API in the database.
     */
    public function update(UpdateAPIManagerRequest $request, string $id)
    {
        $api = APIManager::findOrFail($id);
        $api->update([
            'claim_id' => $request->claim_id,
            'name' => $request->api_name,
            'endpoint' => $request->endpoint,
            'status' => $request->status,
            'updated_by' => Auth::id(),
            'updated_at' => now(),
        ]);

        return $this->jsonSuccess($api, 'API updated successfully.');
    }

    /**
     * Remove the specified API from the database.
     */
    public function destroy(string $id)
    {
        $api = APIManager::findOrFail($id);
        $api->delete();

        return $this->jsonSuccess($api, 'API deleted successfully.');
    }

    /**
     * Get all active APIs.
     */
    public function getActiveAPIs()
    {
        $apis = APIManager::where('status', 1)->get();
        return $this->jsonSuccess($apis, 'Active APIs retrieved successfully.');
    }
    /**
     * Show the mapping page for a specific claim.
     *
     * @param int $claim_id
     * @return \Illuminate\View\View
     */

    public function showMappingPage($claim_id)
    {

        $tempTable = "temp_punch_{$claim_id}";
        $excludeColumns = ['id', 'created_at', 'updated_at'];
        $columns = DB::getSchemaBuilder()->getColumnListing($tempTable);
        $columns = array_diff($columns, $excludeColumns);

        return view('admin.api_fields_mapping', [
            'claim_id' => $claim_id,
            'columns' => $columns
        ]);
    }
    /**
     * Get columns of a specific table.
     *
     * @param Request $request
     * @param string $table
     * @return \Illuminate\Http\JsonResponse
     */

    public function getColumns(Request $request, $table)
    {
        $excludeColumns = ['id', 'created_at', 'updated_at'];
        $columns = DB::getSchemaBuilder()->getColumnListing($table);
        $columns = array_diff($columns, $excludeColumns);
        return response()->json(['columns' => array_values($columns)]);
    }

    public function getTables()
    {
        $tables = DB::select('SHOW TABLES');
        $tableNames = array_map(function ($table) {
            return array_values((array) $table)[0];
        }, $tables);
        return response()->json(['tables' => $tableNames]);
    }
    /**
     * Map fields from the temporary table to the main table.
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */

    public function mapFields(Request $request)
    {
        $claimId = $request->input('claim_id');
        $fyearId = session('FYearId', 0);

        $cgId = DB::table('claimtype')->where('ClaimId', $claimId)->value('cgId');
        if (!$cgId) {
            return response()->json(['error' => 'cgId not found'], 404);
        }

        $tempTable = "temp_punch_{$claimId}";
        $mainTable = "y{$fyearId}_g{$cgId}_expensefilldata";

        $mappings = $request->input('mappings');
        foreach ($mappings as $mapping) {
            $tempColumn = $mapping['temp_column'];
            $mainColumn = $mapping['main_column'];
            DB::table($mainTable)
                ->insertUsing(
                    [$mainColumn],
                    DB::table($tempTable)->select($tempColumn)
                );
        }

        return response()->json(['success' => 'Fields mapped and data moved successfully']);
    }
    /**
     * Store field mappings for a specific claim.
     *
     * @param Request $request
     * @param int $claim_id
     * @return \Illuminate\Http\JsonResponse
     */
    public function storeFieldMapping(Request $request, $claim_id)
    {
        $request->validate([
            'temp_column' => 'required|array',
            'temp_column.*' => 'required|string',
            'input_type' => 'required|array',
            'input_type.*' => 'required|string|in:Input,Select',
            'select_table' => 'array',
            'select_table.*' => 'nullable|string',
            'search_column' => 'array',
            'search_column.*' => 'nullable|string',
            'return_column' => 'array',
            'return_column.*' => 'nullable|string',
            'punch_table' => 'array',
            'punch_table.*' => 'nullable|string',
            'punch_column' => 'array',
            'punch_column.*' => 'nullable|string',
            'condition' => 'array',
            'condition.*' => 'nullable|string',
        ]);
        $mappings = [];
        $temp_columns = $request->input('temp_column', []);
        $input_types = $request->input('input_type', []);
        $select_tables = $request->input('select_table', []);
        $search_columns = $request->input('search_column', []);
        $return_columns = $request->input('return_column', []);
        $punch_tables = $request->input('punch_table', []);
        $punch_columns = $request->input('punch_column', []);
        $conditions = $request->input('condition', []);
        foreach ($temp_columns as $index => $temp_column) {
            $mapping = [
                'claim_id' => $claim_id,
                'temp_column' => $temp_column,
                'input_type' => $input_types[$index] ?? 'Input',
                'select_table' => $select_tables[$index] ?? null,
                'search_column' => $search_columns[$index] ?? null,
                'return_column' => $return_columns[$index] ?? null,
                'punch_table' => $punch_tables[$index] ?? null,
                'punch_column' => $punch_columns[$index] ?? null,
                'condition' => $conditions[$index] ?? null,
                'created_by' => Auth::id(),
                'updated_by' => Auth::id(),
            ];
            if (is_null($mapping['punch_table']) && is_null($mapping['punch_column'])) {
                continue;
            }
            $mappings[] = $mapping;
        }
        ColumnFieldMapping::where('claim_id', $claim_id)->delete();
        if (!empty($mappings)) {
            ColumnFieldMapping::insert($mappings);
        }
        return $this->jsonSuccess(null, 'Field mappings saved successfully.');
    }
}
