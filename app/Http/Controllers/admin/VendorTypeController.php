<?php

namespace App\Http\Controllers\admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\VendorType;

class VendorTypeController extends Controller
{
    public function getVendorTypes()
    {
        $vendorTypes = VendorType::where('status', 1)->get(['id', 'vendor_type']);
        return $this->jsonSuccess($vendorTypes, 'Vendor types loaded successfully.');
    }
}
