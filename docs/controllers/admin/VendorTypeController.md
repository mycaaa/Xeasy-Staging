# VendorTypeController Documentation

## Overview

The `VendorTypeController` is a simple controller that manages vendor type information in the Xeasy expense management system. It provides functionality to retrieve active vendor types for dropdown population and form integration throughout the application.

## Class Information

- **Namespace**: `App\Http\Controllers\admin`
- **Extends**: `Controller`
- **Location**: `app/Http/Controllers/admin/VendorTypeController.php`

## Dependencies

- `App\Models\VendorType` - Vendor type model

## Methods

### getVendorTypes()

**Purpose**: Retrieves all active vendor types for API consumption and dropdown population.

**Route**: `GET /admin/vendor-types`

**Parameters**: None

**Returns**: JSON response with active vendor types

**Business Logic**:
- Filters vendor types by active status (status = 1)
- Returns essential vendor type information (id and vendor_type)
- Used throughout the application for form dropdowns and vendor categorization

**Response Structure**:
```json
{
    "success": true,
    "message": "Vendor types loaded successfully.",
    "data": [
        {
            "id": 1,
            "vendor_type": "hotel"
        },
        {
            "id": 2,
            "vendor_type": "agency"
        },
        {
            "id": 3,
            "vendor_type": "transport"
        }
    ]
}
```

**Frontend Integration**:
```javascript
// Load vendor types for dropdown
async function loadVendorTypes() {
    try {
        const response = await fetch('/admin/vendor-types');
        const result = await response.json();
        
        if (result.success) {
            populateVendorTypeDropdown(result.data);
        }
    } catch (error) {
        console.error('Error loading vendor types:', error);
    }
}

// Populate vendor type dropdown
function populateVendorTypeDropdown(vendorTypes) {
    const dropdown = document.getElementById('vendor-type-dropdown');
    dropdown.innerHTML = '<option value="">Select Vendor Type</option>';
    
    vendorTypes.forEach(type => {
        const option = document.createElement('option');
        option.value = type.id;
        option.textContent = type.vendor_type.charAt(0).toUpperCase() + type.vendor_type.slice(1);
        dropdown.appendChild(option);
    });
}
```

## Integration Examples

### Vendor Management Integration
```php
// Used in VendorListController for type selection
class VendorListController extends Controller
{
    public function index()
    {
        $vendorTypeController = new VendorTypeController();
        $vendorTypesResponse = $vendorTypeController->getVendorTypes();
        $vendorTypes = $vendorTypesResponse->getData()->data;
        
        return view('admin.vendor_list', compact('vendorTypes'));
    }
}
```

### Form Validation Integration
```php
// Dynamic validation based on available vendor types
class VendorValidationService
{
    public function getValidVendorTypes()
    {
        $vendorTypeController = new VendorTypeController();
        $response = $vendorTypeController->getVendorTypes();
        $vendorTypes = $response->getData()->data;
        
        return array_column($vendorTypes, 'vendor_type');
    }
    
    public function validateVendorType($vendorType)
    {
        $validTypes = $this->getValidVendorTypes();
        return in_array($vendorType, $validTypes);
    }
}
```

This VendorTypeController provides essential vendor type management functionality, serving as a simple but crucial component for vendor categorization throughout the expense management system.