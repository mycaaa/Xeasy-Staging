<?php

namespace App\Http\Controllers\admin;

use App\Http\Controllers\Controller;
use App\Models\CityCategory;
use App\Models\CityCategoryLog;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

class CityCategoryController extends Controller
{
    /**
     * Display the City Category master page
     */
    public function index(Request $request)
    {
        // Ensure log table exists
        CityCategoryLog::ensureTableExists();

        $selectedCategory = $request->get('category', '');
        $search = $request->get('search', '');

        // Fetch distinct categories for filtering
        $distinctCategories = CityCategory::select('city_category')
            ->whereNotNull('city_category')
            ->where('city_category', '!=', '')
            ->distinct()
            ->orderBy('city_category')
            ->pluck('city_category')
            ->toArray();

        // Compute summary metrics
        $totalCities = CityCategory::count();
        $totalLogs = CityCategoryLog::count();
        $categoryBreakdown = CityCategory::select('city_category', DB::raw('count(*) as total'))
            ->whereNotNull('city_category')
            ->where('city_category', '!=', '')
            ->groupBy('city_category')
            ->orderByDesc('total')
            ->get();

        // Build query for list
        $query = CityCategory::query();

        if (!empty($selectedCategory) && $selectedCategory !== 'all') {
            $query->where('city_category', $selectedCategory);
        }

        if (!empty($search)) {
            $query->where(function ($q) use ($search) {
                $q->where('city_name', 'like', "%{$search}%")
                  ->orWhere('city_category', 'like', "%{$search}%")
                  ->orWhere('ccid', $search);
            });
        }

        $cities = $query->orderBy('city_name', 'asc')->paginate(50)->withQueryString();

        // Fetch creator employee names mapping if available
        $creatorIds = $cities->pluck('crby')->filter()->unique()->toArray();
        $creatorsMap = [];
        if (!empty($creatorIds)) {
            try {
                $creators = DB::connection('hrims')->table('hrm_employee')
                    ->whereIn('EmployeeID', $creatorIds)
                    ->select('EmployeeID', 'EmpCode_New', 'Fname', 'Sname', 'Lname')
                    ->get();
                foreach ($creators as $c) {
                    $fullName = trim("{$c->Fname} {$c->Sname} {$c->Lname}");
                    $creatorsMap[$c->EmployeeID] = "{$fullName} (" . ($c->EmpCode_New ?? $c->EmployeeID) . ")";
                }
            } catch (\Exception $e) {
                Log::debug("CityCategory: Could not load employee names: " . $e->getMessage());
            }
        }

        return view('admin.city_category.index', compact(
            'cities',
            'distinctCategories',
            'selectedCategory',
            'search',
            'totalCities',
            'totalLogs',
            'categoryBreakdown',
            'creatorsMap'
        ));
    }

    /**
     * JSON data for AJAX / DataTables
     */
    public function getData(Request $request)
    {
        $query = CityCategory::query();

        if ($request->filled('category') && $request->category !== 'all') {
            $query->where('city_category', $request->category);
        }

        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('city_name', 'like', "%{$search}%")
                  ->orWhere('city_category', 'like', "%{$search}%")
                  ->orWhere('ccid', $search);
            });
        }

        $cities = $query->orderBy('city_name', 'asc')->get();

        return response()->json([
            'success' => true,
            'data'    => $cities
        ]);
    }

    /**
     * Store a newly created City Category
     */
    public function store(Request $request)
    {
        $request->validate([
            'city_name'     => 'required|string|max:150|unique:city_category,city_name',
            'city_category' => 'required|string|max:50',
        ]);

        $cityName = trim($request->city_name);
        $cityCategory = trim($request->city_category);

        $crby = session('emp_id', session('employee_id', auth()->id() ?? 0));
        $userName = session('emp_name', auth()->user()?->name ?? 'Admin User');
        $ip = $request->ip();

        $city = CityCategory::create([
            'city_name'     => $cityName,
            'city_category' => $cityCategory,
            'crby'          => $crby,
            'crdate'        => now()->toDateString(),
        ]);

        // Maintain audit log
        CityCategoryLog::ensureTableExists();
        CityCategoryLog::create([
            'ccid'            => $city->ccid,
            'action'          => 'Created',
            'city_name'       => $city->city_name,
            'city_category'   => $city->city_category,
            'new_data'        => [
                'ccid'          => $city->ccid,
                'city_name'     => $city->city_name,
                'city_category' => $city->city_category,
                'crby'          => $city->crby,
                'crdate'        => $city->crdate,
            ],
            'changes_summary' => "Added new city '{$city->city_name}' with category '{$city->city_category}'.",
            'action_by'       => $crby,
            'action_by_name'  => $userName,
            'ip_address'      => $ip,
        ]);

        return response()->json([
            'success' => true,
            'message' => "City '{$city->city_name}' added successfully.",
            'data'    => $city
        ]);
    }

    /**
     * Display / Edit data of a specific City Category
     */
    public function show($id)
    {
        $city = CityCategory::findOrFail($id);
        return response()->json([
            'success' => true,
            'data'    => $city
        ]);
    }

    public function edit($id)
    {
        return $this->show($id);
    }

    /**
     * Update an existing City Category
     */
    public function update(Request $request, $id)
    {
        $city = CityCategory::findOrFail($id);

        $request->validate([
            'city_name'     => 'required|string|max:150|unique:city_category,city_name,' . $id . ',ccid',
            'city_category' => 'required|string|max:50',
        ]);

        $oldData = [
            'ccid'          => $city->ccid,
            'city_name'     => $city->city_name,
            'city_category' => $city->city_category,
            'crby'          => $city->crby,
            'crdate'        => $city->crdate,
        ];

        $newCityName = trim($request->city_name);
        $newCategory = trim($request->city_category);

        // CITY-03: "Same city as" - group a misspelling under the correct city (takes its category)
        $newAlias = $city->alias_of ?? null;
        if (CityCategory::hasAliases() && $request->has('alias_of')) {
            $target = (int) $request->input('alias_of');
            if ($target > 0) {
                $t = CityCategory::find($target);
                if (! $t || $t->ccid === $city->ccid) {
                    return response()->json(['success' => false, 'message' => 'Choose a different, existing city.'], 422);
                }
                if ($t->alias_of) {
                    $t = CityCategory::find($t->alias_of) ?: $t;   // always point at the correct city itself
                }
                if (CityCategory::where('alias_of', $city->ccid)->exists()) {
                    return response()->json(['success' => false, 'message' => "Other names are grouped under '{$city->city_name}'; it cannot itself be grouped."], 422);
                }
                $newAlias = $t->ccid;
                $newCategory = $t->city_category;
            } else {
                $newAlias = null;
            }
        }

        $changes = [];
        if ((int) ($city->alias_of ?? 0) !== (int) ($newAlias ?? 0)) {
            $changes[] = $newAlias ? "Grouped under '" . CityCategory::find($newAlias)->city_name . "'" : 'No longer grouped';
        }
        if ($city->city_name !== $newCityName) {
            $changes[] = "City Name: '{$city->city_name}' &rarr; '{$newCityName}'";
        }
        if ($city->city_category !== $newCategory) {
            $changes[] = "Category: '{$city->city_category}' &rarr; '{$newCategory}'";
        }

        if (empty($changes)) {
            return response()->json([
                'success' => true,
                'message' => 'No changes were made.',
                'data'    => $city
            ]);
        }

        $city->city_name     = $newCityName;
        $city->city_category = $newCategory;
        if (CityCategory::hasAliases()) {
            $city->alias_of = $newAlias;
        }
        $city->save();

        $crby = session('emp_id', session('employee_id', auth()->id() ?? 0));
        $userName = session('emp_name', auth()->user()?->name ?? 'Admin User');
        $ip = $request->ip();

        // Maintain audit log
        CityCategoryLog::ensureTableExists();
        CityCategoryLog::create([
            'ccid'            => $city->ccid,
            'action'          => 'Updated',
            'city_name'       => $city->city_name,
            'city_category'   => $city->city_category,
            'old_data'        => $oldData,
            'new_data'        => [
                'ccid'          => $city->ccid,
                'city_name'     => $city->city_name,
                'city_category' => $city->city_category,
                'crby'          => $city->crby,
                'crdate'        => $city->crdate,
            ],
            'changes_summary' => implode(' | ', $changes),
            'action_by'       => $crby,
            'action_by_name'  => $userName,
            'ip_address'      => $ip,
        ]);

        return response()->json([
            'success' => true,
            'message' => "City '{$city->city_name}' updated successfully.",
            'data'    => $city
        ]);
    }

    /**
     * Delete a City Category record
     */
    public function destroy($id, Request $request)
    {
        $city = CityCategory::findOrFail($id);

        $oldData = [
            'ccid'          => $city->ccid,
            'city_name'     => $city->city_name,
            'city_category' => $city->city_category,
            'crby'          => $city->crby,
            'crdate'        => $city->crdate,
        ];

        $cityName = $city->city_name;
        $cityCat  = $city->city_category;

        $crby = session('emp_id', session('employee_id', auth()->id() ?? 0));
        $userName = session('emp_name', auth()->user()?->name ?? 'Admin User');
        $ip = $request->ip();

        $city->delete();

        // Maintain audit log
        CityCategoryLog::ensureTableExists();
        CityCategoryLog::create([
            'ccid'            => $id,
            'action'          => 'Deleted',
            'city_name'       => $cityName,
            'city_category'   => $cityCat,
            'old_data'        => $oldData,
            'new_data'        => null,
            'changes_summary' => "Deleted city '{$cityName}' (Category: '{$cityCat}').",
            'action_by'       => $crby,
            'action_by_name'  => $userName,
            'ip_address'      => $ip,
        ]);

        return response()->json([
            'success' => true,
            'message' => "City '{$cityName}' deleted successfully."
        ]);
    }

    /**
     * Export City Categories list to CSV/Excel
     */
    public function export(Request $request)
    {
        $query = CityCategory::query();

        if ($request->filled('category') && $request->category !== 'all') {
            $query->where('city_category', $request->category);
        }

        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('city_name', 'like', "%{$search}%")
                  ->orWhere('city_category', 'like', "%{$search}%")
                  ->orWhere('ccid', $search);
            });
        }

        $cities = $query->orderBy('city_name', 'asc')->get();

        // Load creator details
        $creatorIds = $cities->pluck('crby')->filter()->unique()->toArray();
        $creatorsMap = [];
        if (!empty($creatorIds)) {
            try {
                $creators = DB::connection('hrims')->table('hrm_employee')
                    ->whereIn('EmployeeID', $creatorIds)
                    ->select('EmployeeID', 'EmpCode_New', 'Fname', 'Sname', 'Lname')
                    ->get();
                foreach ($creators as $c) {
                    $fullName = trim("{$c->Fname} {$c->Sname} {$c->Lname}");
                    $creatorsMap[$c->EmployeeID] = "{$fullName} ({$c->EmpCode_New})";
                }
            } catch (\Exception $e) {
                // Ignore cross-db error
            }
        }

        $filename = 'city_categories_' . now()->format('Ymd_His') . '.csv';

        return response()->streamDownload(function () use ($cities, $creatorsMap) {
            $out = fopen('php://output', 'w');

            // UTF-8 BOM for proper Excel encoding
            fwrite($out, "\xEF\xBB\xBF");

            fputcsv($out, [
                'S.No',
                'CCID',
                'City Name',
                'City Category',
                'Created By',
                'Created By (ID)',
                'Created Date'
            ]);

            $i = 1;
            foreach ($cities as $city) {
                fputcsv($out, [
                    $i++,
                    $city->ccid,
                    $city->city_name,
                    $city->city_category,
                    $creatorsMap[$city->crby] ?? ($city->crby ? "ID: {$city->crby}" : 'System'),
                    $city->crby ?? '',
                    $city->crdate ? Carbon::parse($city->crdate)->format('d-m-Y') : '-'
                ]);
            }

            fputcsv($out, []); fputcsv($out, \App\Support\ReportStamp::csvRow()); // STAMP-01 (UAT-03: was missing here)
            fclose($out);
        }, $filename, [
            'Content-Type'        => 'text/csv; charset=UTF-8',
            'Content-Disposition' => "attachment; filename=\"{$filename}\"",
        ]);
    }

    /**
     * Retrieve audit logs (JSON)
     */
    public function logs(Request $request)
    {
        CityCategoryLog::ensureTableExists();

        $query = CityCategoryLog::query();

        if ($request->filled('ccid')) {
            $query->where('ccid', $request->ccid);
        }

        if ($request->filled('action') && $request->action !== 'all') {
            $query->where('action', $request->action);
        }

        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('city_name', 'like', "%{$search}%")
                  ->orWhere('city_category', 'like', "%{$search}%")
                  ->orWhere('changes_summary', 'like', "%{$search}%")
                  ->orWhere('action_by_name', 'like', "%{$search}%");
            });
        }

        $logs = $query->orderByDesc('created_at')->limit(150)->get()->map(function ($log) {
            return [
                'id'              => $log->id,
                'ccid'            => $log->ccid,
                'action'          => $log->action,
                'city_name'       => $log->city_name,
                'city_category'   => $log->city_category,
                'changes_summary' => $log->changes_summary,
                'action_by'       => $log->action_by,
                'action_by_name'  => $log->action_by_name ?? ($log->action_by ? "ID: {$log->action_by}" : 'System'),
                'ip_address'      => $log->ip_address,
                'created_at'      => $log->created_at ? $log->created_at->format('d M Y, h:i A') : '-',
            ];
        });

        return response()->json([
            'success' => true,
            'data'    => $logs
        ]);
    }

    /**
     * Export audit logs to CSV
     */
    public function exportLogs(Request $request)
    {
        CityCategoryLog::ensureTableExists();

        $query = CityCategoryLog::query();

        if ($request->filled('ccid')) {
            $query->where('ccid', $request->ccid);
        }

        if ($request->filled('action') && $request->action !== 'all') {
            $query->where('action', $request->action);
        }

        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('city_name', 'like', "%{$search}%")
                  ->orWhere('city_category', 'like', "%{$search}%")
                  ->orWhere('changes_summary', 'like', "%{$search}%")
                  ->orWhere('action_by_name', 'like', "%{$search}%");
            });
        }

        $logs = $query->orderByDesc('created_at')->get();

        $filename = 'city_category_logs_' . now()->format('Ymd_His') . '.csv';

        return response()->streamDownload(function () use ($logs) {
            $out = fopen('php://output', 'w');

            // UTF-8 BOM
            fwrite($out, "\xEF\xBB\xBF");

            fputcsv($out, [
                '#',
                'Log ID',
                'CCID',
                'Action',
                'City Name',
                'City Category',
                'Changes Summary',
                'Action By',
                'Action By (ID)',
                'IP Address',
                'Date & Time'
            ]);

            $i = 1;
            foreach ($logs as $log) {
                fputcsv($out, [
                    $i++,
                    $log->id,
                    $log->ccid ?? '-',
                    $log->action,
                    $log->city_name ?? '-',
                    $log->city_category ?? '-',
                    $log->changes_summary ?? '-',
                    $log->action_by_name ?? ($log->action_by ? "ID: {$log->action_by}" : 'System'),
                    $log->action_by ?? '',
                    $log->ip_address ?? '-',
                    $log->created_at ? $log->created_at->format('d-m-Y H:i:s') : '-'
                ]);
            }

            fputcsv($out, []); fputcsv($out, \App\Support\ReportStamp::csvRow()); // STAMP-01
            fclose($out);
        }, $filename, [
            'Content-Type'        => 'text/csv; charset=UTF-8',
            'Content-Disposition' => "attachment; filename=\"{$filename}\"",
        ]);
    }

    /**
     * Backward-compatible API method
     */
    public function cityList($category = null)
    {
        // CITY-03: misspelled names are grouped under the correct city and not listed separately
        $cities = CityCategory::listWithAliases($category ?: null);
        return $this->jsonSuccess($cities, 'Cities fetched successfully.');
    }
}
