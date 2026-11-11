# EmployeeVehicleController Documentation

## Overview

The `EmployeeVehicleController` manages employee vehicle information and related policies in the Xeasy expense management system. It provides comprehensive functionality for viewing employee vehicle details, policy information, document management, and data export capabilities. The controller integrates with HR systems to provide complete vehicle eligibility and policy management.

## Class Information

- **Namespace**: `App\Http\Controllers\admin`
- **Extends**: `Controller`
- **Location**: `app/Http/Controllers/admin/EmployeeVehicleController.php`

## Dependencies

- `App\Exports\EmployeeVehicleExport` - Excel export functionality
- `App\Models\CoreDepartments` - Department management
- `App\Models\EligibilityPolicy` - Policy management
- `Illuminate\Support\Facades\DB` - Database operations
- `Maatwebsite\Excel\Facades\Excel` - Excel export
- `Yajra\DataTables\Facades\DataTables` - DataTables integration

## Methods

### employeeVehicles()

**Purpose**: Displays the employee vehicle management interface with department and policy filters.

**Route**: `GET /admin/employee-vehicles`

**Parameters**: None

**Returns**: View `verifier.employee_vehicles` with departments and eligibility policies

**Business Logic**:
1. **Department Retrieval**: Fetches active departments for filtering
2. **Policy Retrieval**: Gets eligibility policies for current company
3. **Interface Preparation**: Prepares filter options for vehicle management

**Data Preparation**:
```php
$departments = CoreDepartments::where('is_active', 1)
    ->orderBy('department_name')
    ->get(['id', 'department_name']);

$eligibility_policy = EligibilityPolicy::where('CompanyId', session('company_id'))
    ->get(['PolicyId', 'PolicyName']);
```

**Frontend Integration**:
```javascript
// Initialize employee vehicles interface
function initializeEmployeeVehicles() {
    initializeDataTable();
    initializeFilters();
    initializeExportFunctionality();
}

// Initialize DataTable
function initializeDataTable() {
    const table = $('#employee-vehicles-table').DataTable({
        processing: true,
        serverSide: true,
        ajax: {
            url: '/admin/employee-vehicles/data',
            type: 'POST',
            data: function(d) {
                d.department = $('#department-filter').val();
                d.policy = $('#policy-filter').val();
                d.status = $('#status-filter').val();
            }
        },
        columns: [
            { data: 'DT_RowIndex', name: 'DT_RowIndex', orderable: false },
            { data: 'EmpCode', name: 'EmpCode' },
            { data: 'Name', name: 'Name' },
            { data: 'EmpStatus', name: 'EmpStatus' },
            { data: 'department_name', name: 'department_name' },
            { data: 'grade_name', name: 'grade_name' },
            { data: 'PolicyName', name: 'PolicyName' },
            { data: 'vehicle_type', name: 'vehicle_type', orderable: false },
            { data: 'flat_2w', name: 'flat_2w' },
            { data: 'flat_4w', name: 'flat_4w' },
            { data: 'purchase_date', name: 'purchase_date' },
            { data: 'view', name: 'view', orderable: false, searchable: false }
        ],
        order: [[1, 'asc']],
        drawCallback: function(settings) {
            initializeActionButtons();
        }
    });
    
    return table;
}

// Initialize filter functionality
function initializeFilters() {
    $('#department-filter, #policy-filter, #status-filter').on('change', function() {
        $('#employee-vehicles-table').DataTable().ajax.reload();
    });
    
    // Reset filters
    $('#reset-filters').on('click', function() {
        $('#department-filter, #policy-filter, #status-filter').val('');
        $('#employee-vehicles-table').DataTable().ajax.reload();
    });
}
```

---

### getEmployeeVehiclesData(Request $request)

**Purpose**: Provides DataTables data for employee vehicle information with comprehensive filtering and search capabilities.

**Route**: `POST /admin/employee-vehicles/data`

**Parameters**:
- `department` (optional) - Department filter
- `policy` (optional) - Policy filter
- `status` (optional) - Employee status filter

**Returns**: DataTables JSON response with employee vehicle data

**Business Logic**:
1. **Query Building**: Constructs complex query with multiple joins
2. **Filtering**: Applies department, policy, and status filters
3. **Vehicle Type Detection**: Determines vehicle type based on policy
4. **Action Generation**: Creates action buttons based on employee data
5. **Search Functionality**: Implements comprehensive search across multiple fields

**Database Query Structure**:
```php
$query = DB::connection('hrims')
    ->table('hrm_employee as e')
    ->leftJoin('hrm_employee_eligibility as el', 'e.EmployeeID', '=', 'el.EmployeeID')
    ->leftJoin('hrm_employee_general as g', 'e.EmployeeID', '=', 'g.EmployeeID')
    ->leftJoin('hrm_master_eligibility_policy as elp', 'el.VehiclePolicy', '=', 'elp.PolicyId')
    ->leftJoin('core_grades as gr', 'g.GradeId', '=', 'gr.id')
    ->leftJoin('core_departments as dep', 'g.DepartmentId', '=', 'dep.id')
    ->leftJoin('hrm_employee_vehicle as ev', 'e.EmployeeID', '=', 'ev.EmployeeID')
    ->where('el.Status', 'A')
    ->where('e.CompanyId', session('company_id'));
```

**Vehicle Type Logic**:
```php
// Vehicle type determination
if (in_array($row->VehiclePolicy, [12, 13])) {
    return '4 Wheeler';
} elseif ($row->VehiclePolicy > 0) {
    return '2 Wheeler';
} else {
    return '';
}
```

**Response Structure**:
```json
{
    "draw": 1,
    "recordsTotal": 500,
    "recordsFiltered": 125,
    "data": [
        {
            "DT_RowIndex": 1,
            "EmployeeID": "1729",
            "EmpCode": "EMP001",
            "Name": "John Doe",
            "EmpStatus": "A",
            "department_name": "Sales",
            "grade_name": "Manager",
            "PolicyName": "Two Wheeler Policy",
            "vehicle_type": "2 Wheeler",
            "flat_2w": "1500",
            "flat_4w": "0",
            "purchase_date": "2023-06-15",
            "view": "<div class='dropdown text-center'>...</div>"
        }
    ]
}
```

**Frontend Integration**:
```javascript
// Initialize action buttons
function initializeActionButtons() {
    // Policy details button
    $('.view-policy').on('click', function(e) {
        e.preventDefault();
        const empId = $(this).data('empid');
        viewPolicyDetails(empId);
    });
    
    // Vehicle documents button
    $('.view-docs').on('click', function(e) {
        e.preventDefault();
        const empId = $(this).data('empid');
        viewVehicleDocuments(empId);
    });
}

// View policy details
async function viewPolicyDetails(empId) {
    try {
        showLoading('Loading policy details...');
        
        const response = await fetch('/admin/employee-vehicles/policy-details', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
            },
            body: JSON.stringify({ empid: empId })
        });
        
        const result = await response.json();
        
        hideLoading();
        
        if (result.success) {
            displayPolicyDetails(result.employee, result.fields);
        } else {
            showError(result.message);
        }
    } catch (error) {
        hideLoading();
        console.error('Error loading policy details:', error);
        showError('Failed to load policy details');
    }
}
```

---

### exportVehicles(Request $request)

**Purpose**: Exports employee vehicle data to Excel format with applied filters.

**Route**: `GET /admin/employee-vehicles/export`

**Parameters**:
- `department` (optional) - Department filter
- `policy` (optional) - Policy filter
- `status` (optional) - Employee status filter

**Returns**: Excel file download

**Business Logic**:
1. **Query Reuse**: Uses same query logic as DataTables for consistency
2. **Filter Application**: Applies same filters as used in data view
3. **Export Generation**: Creates Excel file with timestamp
4. **File Naming**: Uses descriptive filename with date/time

**Frontend Integration**:
```javascript
// Export employee vehicles data
function exportEmployeeVehicles() {
    const department = $('#department-filter').val();
    const policy = $('#policy-filter').val();
    const status = $('#status-filter').val();
    
    const params = new URLSearchParams();
    if (department) params.append('department', department);
    if (policy) params.append('policy', policy);
    if (status) params.append('status', status);
    
    const url = `/admin/employee-vehicles/export?${params.toString()}`;
    
    // Create temporary link for download
    const link = document.createElement('a');
    link.href = url;
    link.download = `employee_vehicles_${new Date().toISOString().slice(0, 10)}.xlsx`;
    document.body.appendChild(link);
    link.click();
    document.body.removeChild(link);
    
    showSuccess('Export started. File will download shortly.');
}

// Export button handler
$('#export-btn').on('click', function() {
    exportEmployeeVehicles();
});
```

---

### getPolicyDetails(Request $request)

**Purpose**: Retrieves detailed policy information for a specific employee including eligibility fields and values.

**Route**: `POST /admin/employee-vehicles/policy-details`

**Parameters**:
- `empid` (required) - Employee ID
- `vehicleid` (optional) - Specific Employee Vehicle ID (required when employee has multiple vehicles)

**Returns**: JSON response with policy details and field values

**Business Logic**:
1. **Employee Retrieval**: Fetches employee with policy information
2. **Field Mapping**: Determines relevant fields based on policy and vehicle type
3. **Policy Table Query**: Queries dynamic policy table for grade-specific values
4. **Data Formatting**: Formats field data for display

**Policy Field Logic**:
```php
private function getFieldIds($policyId, $vehicleType)
{
    $checkPolicyIds = [1, 2, 5, 7];
    if (in_array($policyId, $checkPolicyIds)) {
        if ($vehicleType === 'new') {
            return [15, 23, 16, 24, 17, 25, 18, 26];
        } elseif ($vehicleType === 'old') {
            return [19, 27, 20, 28, 21, 29, 22, 30];
        }
    }
    return [];
}
```

**Response Structure**:
```json
{
    "success": true,
    "employee": {
        "name": "John Doe",
        "emp_code": "EMP001",
        "grade": "Manager",
        "policy_name": "Two Wheeler Policy",
        "vehicle_type": "New",
        "plan": "Standard"
    },
    "fields": [
        {
            "field_name": "Purchase Limit",
            "field_value": "75000",
            "field_id": 15
        },
        {
            "field_name": "Monthly Allowance",
            "field_value": "1500",
            "field_id": 23
        }
    ]
}
```

**Frontend Integration**:
```javascript
// Display policy details in modal
function displayPolicyDetails(employee, fields) {
    const modal = document.getElementById('policy-details-modal');
    const title = modal.querySelector('.modal-title');
    const body = modal.querySelector('.modal-body');
    
    title.textContent = `Policy Details - ${employee.name}`;
    
    let html = `
        <div class="row mb-3">
            <div class="col-md-6">
                <strong>Employee Code:</strong> ${employee.emp_code}
            </div>
            <div class="col-md-6">
                <strong>Grade:</strong> ${employee.grade}
            </div>
        </div>
        <div class="row mb-3">
            <div class="col-md-6">
                <strong>Policy:</strong> ${employee.policy_name}
            </div>
            <div class="col-md-6">
                <strong>Vehicle Type:</strong> ${employee.vehicle_type}
            </div>
        </div>
        <div class="row mb-3">
            <div class="col-md-12">
                <strong>Plan:</strong> ${employee.plan}
            </div>
        </div>
        <hr>
        <h6>Policy Fields:</h6>
        <div class="table-responsive">
            <table class="table table-sm">
                <thead>
                    <tr>
                        <th>Field Name</th>
                        <th>Value</th>
                    </tr>
                </thead>
                <tbody>
    `;
    
    fields.forEach(field => {
        html += `
            <tr>
                <td>${field.field_name}</td>
                <td>${field.field_value}</td>
            </tr>
        `;
    });
    
    html += `
                </tbody>
            </table>
        </div>
    `;
    
    body.innerHTML = html;
    
    // Show modal
    const bsModal = new bootstrap.Modal(modal);
    bsModal.show();
}
```

---

### getVehicleDocuments(Request $request)

**Purpose**: Retrieves vehicle document information and generates S3 URLs for file access.

**Route**: `POST /admin/employee-vehicles/vehicle-documents`

**Parameters**:
- `empid` (required) - Employee ID
- `vehicleid` (optional) - Specific Employee Vehicle ID (required when employee has multiple vehicles)

**Returns**: JSON response with available and missing documents

**Business Logic**:
1. **Vehicle Retrieval**: Fetches vehicle information for employee
2. **Document Processing**: Checks availability of various document types
3. **S3 URL Generation**: Creates secure URLs for document access
4. **File Type Detection**: Determines if files are images for preview
5. **Categorization**: Separates available and missing documents

**Document Types**:
```php
$attachmentTypes = [
    'vehicle_image' => 'Vehicle Image',
    'rc_file' => 'RC File',
    'insurance' => 'Insurance File',
    'odo_meter' => 'Odometer File',
    'dl_file' => 'DL File',
    'bill_file' => 'Bill File'
];
```

**S3 URL Generation**:
```php
private function getS3FileUrl($companyId, $filePath)
{
    if (empty($filePath)) {
        return false;
    }
    
    try {
        $bucket = env('HRIMS_AWS_BUCKET');
        $region = env('HRIMS_AWS_DEFAULT_REGION', 'ap-south-1');
        $s3Key = "Employee_Vehicle/{$companyId}/{$filePath}";
        return "https://{$bucket}.s3.{$region}.amazonaws.com/{$s3Key}";
    } catch (\Exception $e) {
        return false;
    }
}
```

**Response Structure**:
```json
{
    "success": true,
    "employee": {
        "emp_code": "EMP001",
        "emp_name": "John Doe",
        "current_odo_meter": "15000"
    },
    "available": [
        {
            "label": "Vehicle Image",
            "url": "https://bucket.s3.region.amazonaws.com/Employee_Vehicle/1/vehicle_123.jpg",
            "is_image": true,
            "field": "vehicle_image"
        },
        {
            "label": "RC File",
            "url": "https://bucket.s3.region.amazonaws.com/Employee_Vehicle/1/rc_123.pdf",
            "is_image": false,
            "field": "rc_file"
        }
    ],
    "not_available": [
        "Insurance File",
        "DL File"
    ]
}
```

**Frontend Integration**:
```javascript
// View vehicle documents
async function viewVehicleDocuments(empId) {
    try {
        showLoading('Loading vehicle documents...');
        
        const response = await fetch('/admin/employee-vehicles/vehicle-documents', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
            },
            body: JSON.stringify({ empid: empId })
        });
        
        const result = await response.json();
        
        hideLoading();
        
        if (result.success) {
            displayVehicleDocuments(result.employee, result.available, result.not_available);
        } else {
            showError(result.message);
        }
    } catch (error) {
        hideLoading();
        console.error('Error loading vehicle documents:', error);
        showError('Failed to load vehicle documents');
    }
}

// Display vehicle documents in modal
function displayVehicleDocuments(employee, available, notAvailable) {
    const modal = document.getElementById('vehicle-docs-modal');
    const title = modal.querySelector('.modal-title');
    const body = modal.querySelector('.modal-body');
    
    title.textContent = `Vehicle Documents - ${employee.emp_name}`;
    
    let html = `
        <div class="row mb-3">
            <div class="col-md-6">
                <strong>Employee Code:</strong> ${employee.emp_code}
            </div>
            <div class="col-md-6">
                <strong>Current Odometer:</strong> ${employee.current_odo_meter || 'N/A'}
            </div>
        </div>
        <hr>
    `;
    
    if (available.length > 0) {
        html += '<h6>Available Documents:</h6><div class="row">';
        
        available.forEach(doc => {
            html += `
                <div class="col-md-4 mb-3">
                    <div class="card">
                        <div class="card-body text-center">
                            <h6 class="card-title">${doc.label}</h6>
                            ${doc.is_image ? 
                                `<img src="${doc.url}" class="img-thumbnail mb-2" style="max-height: 100px;" onclick="viewFullImage('${doc.url}')">` :
                                `<i class="ri-file-line fs-1 text-primary"></i>`
                            }
                            <br>
                            <a href="${doc.url}" target="_blank" class="btn btn-sm btn-primary">
                                <i class="ri-eye-line"></i> View
                            </a>
                            <a href="${doc.url}" download class="btn btn-sm btn-secondary ms-1">
                                <i class="ri-download-line"></i> Download
                            </a>
                        </div>
                    </div>
                </div>
            `;
        });
        
        html += '</div>';
    }
    
    if (notAvailable.length > 0) {
        html += '<h6 class="mt-3">Missing Documents:</h6><ul class="list-group">';
        
        notAvailable.forEach(doc => {
            html += `<li class="list-group-item d-flex justify-content-between align-items-center">
                ${doc}
                <span class="badge bg-danger">Not Available</span>
            </li>`;
        });
        
        html += '</ul>';
    }
    
    body.innerHTML = html;
    
    // Show modal
    const bsModal = new bootstrap.Modal(modal);
    bsModal.show();
}

// View full image in modal
function viewFullImage(imageUrl) {
    const imageModal = document.getElementById('image-preview-modal');
    const imageElement = imageModal.querySelector('#preview-image');
    
    imageElement.src = imageUrl;
    
    const bsModal = new bootstrap.Modal(imageModal);
    bsModal.show();
}
```

## Security Considerations

### Access Control
- **Admin Authentication**: All methods require admin-level access
- **Company Isolation**: Data filtered by company ID for multi-tenant security
- **Permission Validation**: Vehicle management requires specific permissions

### Data Protection
- **Sensitive Information**: Employee and vehicle data handled securely
- **File Access**: S3 URLs generated with proper security considerations
- **Input Validation**: All inputs validated and sanitized

### File Security
- **S3 Integration**: Secure file storage with proper access controls
- **URL Generation**: Temporary URLs for secure file access
- **File Type Validation**: File types validated for security

## Performance Optimizations

### Database Efficiency
```php
// Optimized query with proper joins and indexing
$query = DB::connection('hrims')
    ->table('hrm_employee as e')
    ->leftJoin('hrm_employee_eligibility as el', 'e.EmployeeID', '=', 'el.EmployeeID')
    ->leftJoin('hrm_employee_general as g', 'e.EmployeeID', '=', 'g.EmployeeID')
    // ... additional joins
    ->where('el.Status', 'A')
    ->where('e.CompanyId', session('company_id'));
```

### Caching Strategy
- **Department Caching**: Cache department list for better performance
- **Policy Caching**: Cache eligibility policies
- **S3 URL Caching**: Cache generated S3 URLs

### Memory Management
- **Selective Loading**: Load only required fields for large datasets
- **Efficient Queries**: Use proper indexing and selective joins
- **Resource Cleanup**: Proper cleanup of temporary resources

## Configuration

### Environment Variables
```env
# HRIMS AWS Configuration
HRIMS_AWS_BUCKET=your-hrims-bucket
HRIMS_AWS_DEFAULT_REGION=ap-south-1
HRIMS_AWS_ACCESS_KEY_ID=your_access_key
HRIMS_AWS_SECRET_ACCESS_KEY=your_secret_key

# Database Configuration
DB_CONNECTION_HRIMS=hrims_db
DB_HOST_HRIMS=127.0.0.1
DB_PORT_HRIMS=3306
DB_DATABASE_HRIMS=hrims_database
```

### Database Schema Requirements
```sql
-- Employee vehicle table
CREATE TABLE hrm_employee_vehicle (
    id INT PRIMARY KEY AUTO_INCREMENT,
    EmployeeID VARCHAR(20) NOT NULL,
    vehicle_image VARCHAR(255),
    rc_file VARCHAR(255),
    insurance VARCHAR(255),
    current_odo_meter INT,
    odo_meter VARCHAR(255),
    dl_file VARCHAR(255),
    bill_file VARCHAR(255),
    purchase_date DATE,
    policyentrydate DATE,
    oldnew VARCHAR(10),
    registration_date DATE,
    status CHAR(1) DEFAULT 'A',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- Employee eligibility table
CREATE TABLE hrm_employee_eligibility (
    EmployeeID VARCHAR(20) NOT NULL,
    VehiclePolicy INT,
    Travel_TwoWeeKM DECIMAL(10,2),
    Travel_FourWeeKM DECIMAL(10,2),
    Plan VARCHAR(50),
    Status CHAR(1) DEFAULT 'A',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);
```

## Integration Examples

### Excel Export Integration
```php
// Custom export class
class EmployeeVehicleExport implements FromQuery, WithHeadings, WithMapping
{
    protected $query;
    
    public function __construct($query)
    {
        $this->query = $query;
    }
    
    public function query()
    {
        return $this->query;
    }
    
    public function headings(): array
    {
        return [
            'Employee Code',
            'Employee Name',
            'Status',
            'Department',
            'Grade',
            'Policy Name',
            'Vehicle Type',
            '2W Allowance',
            '4W Allowance',
            'Purchase Date'
        ];
    }
    
    public function map($row): array
    {
        return [
            $row->EmpCode,
            $row->Name,
            $row->EmpStatus,
            $row->department_name,
            $row->grade_name,
            $row->PolicyName,
            $this->getVehicleType($row->VehiclePolicy),
            $row->flat_2w,
            $row->flat_4w,
            $row->purchase_date
        ];
    }
}
```

### S3 File Integration
```php
// Secure file URL generation
private function generateSecureS3Url($companyId, $filePath, $expiration = 3600)
{
    $s3Client = new S3Client([
        'version' => 'latest',
        'region' => env('HRIMS_AWS_DEFAULT_REGION'),
        'credentials' => [
            'key' => env('HRIMS_AWS_ACCESS_KEY_ID'),
            'secret' => env('HRIMS_AWS_SECRET_ACCESS_KEY'),
        ],
    ]);
    
    $bucket = env('HRIMS_AWS_BUCKET');
    $key = "Employee_Vehicle/{$companyId}/{$filePath}";
    
    $command = $s3Client->getCommand('GetObject', [
        'Bucket' => $bucket,
        'Key' => $key
    ]);
    
    return $s3Client->createPresignedRequest($command, "+{$expiration} seconds")->getUri();
}
```

## Error Handling and Logging

### Exception Management
```php
try {
    // Vehicle data operations
    $vehicles = $this->employeeVehicleQuery($department, $policy, $status);
} catch (\Exception $e) {
    Log::error('Employee Vehicle Controller Error', [
        'method' => 'getEmployeeVehiclesData',
        'filters' => compact('department', 'policy', 'status'),
        'error' => $e->getMessage(),
        'trace' => $e->getTraceAsString()
    ]);
    
    return response()->json([
        'error' => true, 
        'message' => 'Failed to load vehicle data: ' . $e->getMessage()
    ], 500);
}
```

### Monitoring and Alerts
- **Query Performance**: Monitor complex join query performance
- **File Access**: Track S3 file access patterns and errors
- **Export Usage**: Monitor export functionality usage and performance

This EmployeeVehicleController provides comprehensive vehicle management functionality, enabling administrators to efficiently manage employee vehicle information, policies, and related documentation while maintaining security and performance standards.