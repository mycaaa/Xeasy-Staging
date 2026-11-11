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
        $vendorListQuery = VendorList::query();

        // Filter by status
        if ($status === 'active') {
            $vendorListQuery->where('status', '1');
        } elseif ($status === 'inactive') {
            $vendorListQuery->where('status', '0');
        }

        // Filter by request status
        if ($requestStatus !== 'all') {
            $vendorListQuery->where('request_status', $requestStatus);
        }

        // Filter by vendor type
        if ($vendorTypeId !== 'all') {
            $vendorListQuery->where('vendor_type_id', $vendorTypeId);
        }

        // Fetch vendors with their vendor type
        $vendors = $vendorListQuery->with('vendorType')->get();

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

        return view('admin.vendor_list', compact('vendors', 'status', 'requestStatus', 'vendorTypeId', 'requestCounts', 'vendorTypes'));
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
