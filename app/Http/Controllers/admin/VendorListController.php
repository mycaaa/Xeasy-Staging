<?php

namespace App\Http\Controllers\admin;

use App\Http\Controllers\Controller;
use App\Models\VendorList;
use App\Models\VendorType;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class VendorListController extends Controller
{
    public function index()
    {
        $status = request('status', 'active');
        $requestStatus = request('request_status', 'all');
        $vendorTypeId = request('vendor_type_id', 'all');

        // PERF-07: the rows now come from vendors/data a page at a time (the page used to carry
        // every vendor, about 4.8 MB of HTML, with a permission check per row)
        $filteredCount = $this->filtered($status, $requestStatus, $vendorTypeId)->count();

        // Fetch request status counts
        $requestCounts = VendorList::selectRaw("
            SUM(CASE WHEN request_status = 'P' THEN 1 ELSE 0 END) AS pending_count,
            SUM(CASE WHEN request_status = 'A' THEN 1 ELSE 0 END) AS approved_count,
            SUM(CASE WHEN request_status = 'R' THEN 1 ELSE 0 END) AS rejected_count,
            SUM(CASE WHEN request_status = 'H' THEN 1 ELSE 0 END) AS hold_count,
            COUNT(*) AS total_count
        ")->first();

        // Fetch active vendor types with count
        $vendorTypes = VendorType::where('status', 1)
            ->withCount(['vendors as vendor_count' => function ($query) use ($status, $requestStatus) {
                if ($status === 'active') {
                    $query->where('status', 1);
                } elseif ($status === 'inactive') {
                    $query->where('status', 0);
                }
                if ($requestStatus !== 'all') {
                    $query->where('request_status', $requestStatus);
                }
            }])
            ->get(['id', 'vendor_type']);

        return view('admin.vendor_list', compact('filteredCount', 'status', 'requestStatus', 'vendorTypeId', 'requestCounts', 'vendorTypes'));
    }

    private function filtered(string $status, string $requestStatus, string $vendorTypeId)
    {
        $q = VendorList::query();
        if ($status === 'active') {
            $q->where('status', '1');
        } elseif ($status === 'inactive') {
            $q->where('status', '0');
        }
        if ($requestStatus !== 'all') {
            $q->where('request_status', $requestStatus);
        }
        if ($vendorTypeId !== 'all') {
            $q->where('vendor_type_id', $vendorTypeId);
        }
        return $q;
    }

    /** Vendor ids named in the user's "<action> vendor <id>" permissions; null = every vendor (Super Admin). */
    private function vendorIdsFor($user, string $action): ?array
    {
        if ($user->hasRole('Super Admin')) {
            return null;
        }
        $ids = [];
        foreach ($user->getAllPermissions() as $perm) {
            if (preg_match('/^' . $action . ' vendor (\d+)$/', $perm->name, $m)) {
                $ids[] = (int) $m[1];
            }
        }
        return $ids;
    }

    /** PERF-07: one page of the vendor table (DataTables server-side), newest first. */
    public function data(Request $request)
    {
        $user = Auth::user();
        $draw = (int) $request->input('draw', 1);
        if (! $user || ! $user->can('vendor_list')) {
            return response()->json(['draw' => $draw, 'recordsTotal' => 0, 'recordsFiltered' => 0, 'data' => [], 'error' => 'You do not have permission to view the Vendor List.']);
        }

        $q = $this->filtered((string) $request->input('status', 'active'), (string) $request->input('request_status', 'all'), (string) $request->input('vendor_type_id', 'all'));
        $viewIds = $this->vendorIdsFor($user, 'view');
        if ($viewIds !== null) {
            $q->whereIn('id', $viewIds ?: [0]);
        }
        $total = (clone $q)->count();

        $search = trim((string) data_get($request->input('search'), 'value', ''));
        if ($search !== '') {
            $like = '%' . str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $search) . '%';
            $q->where(function ($w) use ($like) {
                foreach (['vendor_name', 'vendor_type', 'contact_person', 'contact_number', 'email', 'gst_no'] as $col) {
                    $w->orWhere($col, 'like', $like);
                }
            });
        }
        $filtered = $search === '' ? $total : (clone $q)->count();

        $start = max(0, (int) $request->input('start', 0));
        $length = (int) $request->input('length', 10);
        $length = $length < 1 ? 10 : min($length, 100);

        $rows = $q->orderByDesc('id')->skip($start)->take($length)
            ->get(['id', 'vendor_name', 'vendor_type', 'contact_person', 'contact_number', 'email', 'gst_no', 'status', 'request_status']);

        $updateIds = $this->vendorIdsFor($user, 'update');
        $data = [];
        foreach ($rows as $i => $v) {
            $data[] = [
                'sno' => $start + $i + 1,
                'id' => $v->id,
                'vendor_name' => $v->vendor_name,
                'vendor_type' => ucfirst((string) $v->vendor_type),
                'contact_person' => $v->contact_person ?: '-',
                'contact_number' => $v->contact_number ?: '-',
                'email' => $v->email ?: '-',
                'gst_no' => $v->gst_no ?: '-',
                'status' => (string) $v->status,
                'request_status' => (string) $v->request_status,
                'can_edit' => $updateIds === null || in_array((int) $v->id, $updateIds, true),
            ];
        }

        return response()->json(['draw' => $draw, 'recordsTotal' => $total, 'recordsFiltered' => $filtered, 'data' => $data]);
    }

    public function vendorList($type = null)
    {
        $vendors = VendorList::getActiveVendorsByType($type, false);
        return $this->jsonSuccess($vendors, 'Vendors fetched successfully.');
    }
    public function store(Request $request)
    {
        $request->validate(['vendor_type' => 'required|in:hotel,agency,agent,travel,transport,others', 'vendor_name' => 'required|string|max:100', 'vendor_address' => 'nullable|string|max:255', 'contact_person' => 'nullable|string|max:100', 'contact_number' => 'nullable|string|max:30', 'email' => 'nullable|email|max:100', 'gst_no' => 'nullable|string|max:30', 'status' => 'nullable|in:1,0', 'request_status' => 'nullable|in:P,A,R,H',]);
        $vendor = VendorList::create([
            'vendor_type' => $request->vendor_type,
            'vendor_name' => $request->vendor_name,
            'vendor_address' => $request->vendor_address,
            'contact_person' => $request->contact_person,
            'contact_number' => $request->contact_number,
            'email' => $request->email,
            'gst_no' => $request->gst_no,
            'company_id' => $request->company_id ?? 0,
            'created_by' => Auth::id() ?? 0,
            'created_date' => now(),
            'updated_by' => Auth::id() ?? 0,
            'updated_date' => now(),
            'status' => $request->status ?? '1',
            'request_status' => $request->request_status ?? 'P',

        ]);
        $permissions = ["view vendor {$vendor->id}", "update vendor {$vendor->id}", "delete vendor {$vendor->id}",];
        foreach ($permissions as $perm) {
            Permission::firstOrCreate(['name' => $perm, 'guard_name' => 'web']);
        }
        $managerRole = Role::where('name', 'Super Admin')->first();
        if ($managerRole) {
            $managerRole->givePermissionTo("view vendor {$vendor->id}");
        }
        return $this->jsonSuccess($vendor, 'Vendor created successfully.');
    }
    public function edit(string $id)
    {
        $vendor = VendorList::findOrFail($id);
        return $this->jsonSuccess($vendor, 'Vendor retrieved successfully.');
    }
    public function update(Request $request, string $id)
    {
        $request->validate(['vendor_type' => 'required|in:hotel,agency,agent,travel,transport,others', 'vendor_name' => 'required|string|max:100', 'vendor_address' => 'nullable|string|max:255', 'contact_person' => 'nullable|string|max:100', 'contact_number' => 'nullable|string|max:30', 'email' => 'nullable|email|max:100', 'gst_no' => 'nullable|string|max:30', 'status' => 'nullable|in:1,0', 'request_status' => 'nullable|in:P,A,R,H',]);
        $vendor = VendorList::findOrFail($id);
        $vendor->update(['vendor_type' => $request->vendor_type, 'vendor_name' => $request->vendor_name, 'vendor_address' => $request->vendor_address, 'contact_person' => $request->contact_person, 'contact_number' => $request->contact_number, 'email' => $request->email, 'gst_no' => $request->gst_no, 'company_id' => $request->company_id ?? 0, 'updated_by' => Auth::id() ?? 0, 'updated_at' => now(), 'status' => $request->status ?? '1', 'request_status' => $request->request_status ?? $vendor->request_status,]);
        return $this->jsonSuccess($vendor, 'Vendor updated successfully.');
    }
    public function destroy(string $id)
    {
        $vendor = VendorList::findOrFail($id);
        $vendor->delete();
        return $this->jsonSuccess(null, 'Vendor deleted successfully.');
    }
}
