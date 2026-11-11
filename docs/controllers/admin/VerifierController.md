# VerifierController Documentation

## Overview

The `VerifierController` is a comprehensive controller that manages the verification process for expense claims in the Xeasy expense management system. It handles vehicle odometer verification, claim verification workflows, courier tracking, and provides detailed interfaces for verifiers to review and approve employee expense submissions.

## Class Information

- **Namespace**: `App\Http\Controllers\admin`
- **Extends**: `Controller`
- **Location**: `app/Http/Controllers/admin/VerifierController.php`

## Constants

```php
const VEHICLE_TYPE_TWO_WHEELER = 2;
const VEHICLE_TYPE_FOUR_WHEELER = 4;
```

## Dependencies

- `App\Models\CoreDepartments` - Department management
- `App\Models\HRMEmployees` - Employee information
- `App\Models\MonthExpenseFinal` - Monthly expense summaries
- `Carbon\Carbon` - Date/time manipulation
- `Illuminate\Support\Facades\Storage` - File storage operations
- `Yajra\DataTables\Facades\DataTables` - DataTables integration

## Methods

### View Methods

#### verifyRunningKm()
**Purpose**: Displays the running kilometer verification interface.
**Route**: `GET /admin/verify-running-km`
**Returns**: View `verifier.verify_running_km`

#### verifyPendingClaims()
**Purpose**: Displays the pending claims verification interface.
**Route**: `GET /admin/verify-pending-claims`
**Returns**: View `verifier.verify_pending_claims`

#### verifyApprovedClaims()
**Purpose**: Displays the approved claims verification interface.
**Route**: `GET /admin/verify-approved-claims`
**Returns**: View `verifier.verify_approved_claims`

#### employeeVehicles()
**Purpose**: Displays employee vehicle management interface.
**Route**: `GET /admin/employee-vehicles`
**Returns**: View `verifier.employee_vehicles` with departments data

---

### verifyRunningKmData(Request $request)

**Purpose**: Provides DataTables data for vehicle odometer verification with comprehensive filtering and validation capabilities.

**Route**: `POST /admin/verify-running-km/data`

**Parameters**:
- `only_employee` (boolean) - Filter for specific employee view
- `employee` (optional) - Employee ID filter
- `month` (optional) - Month filter
- `denied` (boolean) - Show denied claims
- `missing_images` (boolean) - Show claims with missing odometer images

**Returns**: DataTables JSON response with vehicle claim data

**Business Logic**:
1. **Data Retrieval**: Fetches vehicle claims (ClaimId = 7) with employee and final status information
2. **Filtering Logic**: Applies multiple filters for employee, month, denial status, and missing images
3. **Cumulative KM Calculation**: Calculates running totals for employee-specific views
4. **Image Processing**: Generates S3 URLs for odometer images with fallback handling
5. **Permission Checks**: Validates final status and backdate permissions for actions

**Key Features**:
- **Odometer Validation**: Ensures opening < closing readings
- **Image Verification**: Displays opening and closing odometer images
- **Rate Calculation**: Integrates with RateController for per-km rates
- **Status Tracking**: Monitors verification and approval status
- **Cumulative Tracking**: Shows running KM totals for employee analysis

**Response Structure**:
```json
{
    "draw": 1,
    "recordsTotal": 150,
    "recordsFiltered": 25,
    "data": [
        {
            "DT_RowIndex": 1,
            "ExpId": "12345",
            "employee_name": "John Doe",
            "BillDate": "15-01-2024",
            "opening_image": "<img class='rounded-sm zoom-verify-img' src='https://s3.../opening.jpg' width='100' height='60' />",
            "closing_image": "<img class='rounded-sm zoom-verify-img' src='https://s3.../closing.jpg' width='100' height='60' />",
            "odomtr_opening": "<input type='number' class='form-control odometer-input' data-expid='12345' value='15000' />",
            "odomtr_closing": "<input type='number' class='form-control odometer-input' data-expid='12345' value='15150' />",
            "TotKm": "<span class='fw-bold'>150</span>",
            "cumulative_km": "<span class='fw-bold text-primary'>1350</span>",
            "vehicle_type": "<div class='d-flex flex-column'>...</div>",
            "update_action": "<a href='javascript:void(0);' class='badge bg-info update-running-km' data-expid='12345'>Update</a>",
            "verify_action": "<a href='javascript:void(0);' class='badge bg-success mark-verified' data-expid='12345'>Verify</a>"
        }
    ],
    "months": [
        {"value": 1, "text": "January"},
        {"value": 2, "text": "February"}
    ],
    "employees": [
        {"value": "1729", "text": "EMP001 - John Doe"}
    ]
}
```

**Frontend Integration**:
```javascript
// Initialize running KM verification DataTable
function initializeRunningKmTable() {
    const table = $('#running-km-table').DataTable({
        processing: true,
        serverSide: true,
        ajax: {
            url: '/admin/verify-running-km/data',
            type: 'POST',
            data: function(d) {
                d.only_employee = $('#only-employee-filter').is(':checked');
                d.employee = $('#employee-filter').val();
                d.month = $('#month-filter').val();
                d.denied = $('#denied-filter').is(':checked');
                d.missing_images = $('#missing-images-filter').is(':checked');
            }
        },
        columns: [
            { data: 'DT_RowIndex', name: 'DT_RowIndex', orderable: false },
            { data: 'employee_name', name: 'employee_name' },
            { data: 'BillDate', name: 'BillDate' },
            { data: 'opening_image', name: 'opening_image', orderable: false },
            { data: 'closing_image', name: 'closing_image', orderable: false },
            { data: 'odomtr_opening', name: 'odomtr_opening', orderable: false },
            { data: 'odomtr_closing', name: 'odomtr_closing', orderable: false },
            { data: 'TotKm', name: 'TotKm' },
            { data: 'cumulative_km', name: 'cumulative_km', orderable: false },
            { data: 'vehicle_type', name: 'vehicle_type', orderable: false },
            { data: 'update_action', name: 'update_action', orderable: false },
            { data: 'verify_action', name: 'verify_action', orderable: false }
        ],
        drawCallback: function(settings) {
            initializeOdometerInputs();
            initializeVehicleTypeRadios();
            initializeActionButtons();
        }
    });
    
    return table;
}

// Handle odometer input changes
function initializeOdometerInputs() {
    $('.odometer-input').on('blur', function() {
        const expId = $(this).data('expid');
        const type = $(this).data('type');
        const value = $(this).val();
        
        validateOdometerReading(expId, type, value);
    });
}

// Handle vehicle type selection
function initializeVehicleTypeRadios() {
    $('.vehicle-type-radio').on('change', function() {
        const expId = $(this).data('expid');
        const vehicleType = $(this).val();
        
        updateVehicleType(expId, vehicleType);
    });
}
```

---

### updateRunningKm(Request $request)

**Purpose**: Updates odometer readings and calculates running kilometers with rate-based amount calculation.

**Route**: `POST /admin/update-running-km`

**Parameters**:
- `expId` (required|integer) - Expense ID
- `opening` (required|numeric|min:0) - Opening odometer reading
- `closing` (required|numeric|min:0) - Closing odometer reading
- `vehicleType` (required|in:2,4) - Vehicle type (2W or 4W)

**Returns**: JSON response with calculation results

**Business Logic**:
1. **Validation**: Ensures closing > opening odometer readings
2. **Rate Calculation**: Gets current per-km rate based on employee, date, and vehicle type
3. **Amount Calculation**: Computes total amount (running KM × rate)
4. **Multi-Table Updates**: Updates expense claims, wheeler entry, and fill data tables
5. **Data Consistency**: Maintains consistency across related tables

**Database Operations**:
```sql
-- Update main expense claims table
UPDATE y2024_expenseclaims SET 
    odomtr_opening = ?, 
    odomtr_closing = ?, 
    TotKm = ?, 
    WType = ?, 
    RatePerKM = ?, 
    FilledTAmt = ? 
WHERE ExpId = ?;

-- Update wheeler entry table
UPDATE y2024_24_wheeler_entry SET 
    DistTraOpen = ?, 
    DistTraClose = ?, 
    Totalkm = ? 
WHERE ExpId = ?;

-- Update or insert fill data
INSERT INTO y2024_g1_expensefilldata (ExpId, VehicleType, BillDate) 
VALUES (?, ?, ?) 
ON DUPLICATE KEY UPDATE VehicleType = ?;
```

**Response Structure**:
```json
{
    "success": true,
    "message": "Running KM updated successfully.",
    "data": {
        "runningKm": 150,
        "rate": 12.50,
        "amount": 1875.00
    }
}
```

**Frontend Integration**:
```javascript
// Update running KM
async function updateRunningKm(expId) {
    const openingInput = $(`.opening-odo[data-expid="${expId}"]`);
    const closingInput = $(`.closing-odo[data-expid="${expId}"]`);
    const vehicleTypeRadio = $(`input[name="vehicle_type_${expId}"]:checked`);
    
    const opening = parseFloat(openingInput.val());
    const closing = parseFloat(closingInput.val());
    const vehicleType = vehicleTypeRadio.val();
    
    // Validation
    if (!opening || !closing || !vehicleType) {
        showError('Please fill all required fields');
        return;
    }
    
    if (closing <= opening) {
        showError('Closing odometer must be greater than opening odometer');
        return;
    }
    
    try {
        const response = await fetch('/admin/update-running-km', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
            },
            body: JSON.stringify({
                expId: expId,
                opening: opening,
                closing: closing,
                vehicleType: vehicleType
            })
        });
        
        const result = await response.json();
        
        if (result.success) {
            showSuccess(result.message);
            // Update UI with calculated values
            updateKmDisplay(expId, result.data);
            // Refresh table
            $('#running-km-table').DataTable().ajax.reload(null, false);
        } else {
            showError(result.message);
        }
    } catch (error) {
        console.error('Error updating running KM:', error);
        showError('Failed to update running KM');
    }
}

// Update KM display in UI
function updateKmDisplay(expId, data) {
    $(`.total-km[data-expid="${expId}"]`).text(data.runningKm);
    $(`.rate-display[data-expid="${expId}"]`).text(data.rate.toFixed(2));
    $(`.amount-display[data-expid="${expId}"]`).text(data.amount.toFixed(2));
}
```

---

### verifyRunningKmRecord(Request $request)

**Purpose**: Verifies and finalizes odometer-based vehicle claims with comprehensive validation.

**Route**: `POST /admin/verify-running-km-record`

**Parameters**:
- `expId` (required|integer) - Expense ID

**Returns**: JSON response with verification status

**Business Logic**:
1. **Pre-Verification Checks**: Validates presence of odometer images and readings
2. **Data Integrity**: Ensures closing > opening odometer readings
3. **Rate Calculation**: Computes final rate and amount
4. **Status Updates**: Changes claim status to 'Filled' and sets verification flags
5. **Multi-Table Synchronization**: Updates all related tables consistently

**Validation Rules**:
- Opening and closing images must be present
- Odometer readings must be valid numbers
- Closing reading must be greater than opening reading
- Total KM and rate must be positive values

**Response Examples**:

**Success Response**:
```json
{
    "success": true,
    "message": "Record verified successfully.",
    "data": {
        "rate": 12.50,
        "amount": 1875.00
    }
}
```

**Error Responses**:
```json
{
    "success": false,
    "message": "Cannot verify: Opening or closing image is missing."
}

{
    "success": false,
    "message": "Cannot verify: Closing odometer must be greater than opening."
}
```

---

### verifyPendingClaimsData(Request $request)

**Purpose**: Provides DataTables data for pending claims verification with comprehensive employee and claim information.

**Route**: `POST /admin/verify-pending-claims/data`

**Parameters**:
- `courier_status` (optional) - Filter by courier status (received/not_received/all)
- `month` (optional) - Month filter
- `department` (optional) - Department filter
- `employee` (optional) - Employee filter

**Returns**: DataTables JSON response with pending claims data

**Business Logic**:
1. **Claim Identification**: Finds submitted claims awaiting verification
2. **Employee Information**: Includes comprehensive employee details and department info
3. **Historical Data**: Shows last month's claim information for comparison
4. **Courier Tracking**: Integrates courier receipt status
5. **Permission-Based Actions**: Shows actions based on user permissions

**Key Features**:
- **Multi-Level Filtering**: Department, employee, month, and courier status filters
- **Historical Comparison**: Shows previous month's claim amounts
- **Action Permissions**: Role-based action buttons (return, submit, e-home)
- **External Integration**: Links to attendance and eligibility systems
- **Courier Management**: Tracks document receipt status

**Response Structure**:
```json
{
    "draw": 1,
    "recordsTotal": 200,
    "recordsFiltered": 45,
    "data": [
        {
            "DT_RowIndex": 1,
            "EmployeeID": "1729",
            "EmpCode": "EMP001",
            "Name": "<a href='javascript:void(0);' onclick='viewClaim(...)' class='text-primary'>John Doe</a>",
            "department_name": "Sales",
            "grade_name": "Manager",
            "DateOfSubmit": "15-01-2024",
            "Month": "<a href='javascript:void(0);' onclick='viewClaim(...)' class='text-primary'>January</a>",
            "Total_Claim": "<span class='fw-bold'>15</span>",
            "Claim_Amount": "<span class='fw-bold'>25,000</span>",
            "Actions": "<div class='d-flex justify-content-center gap-2'>...</div>",
            "Check": "<div class='d-flex justify-content-center gap-2'>...</div>",
            "Courier_Detail": "<a href='javascript:void(0);' class='courier-detail text-success'>Received</a>",
            "Last_Claim_Info": "Dec - 22,000"
        }
    ],
    "months": [...],
    "employees": [...],
    "departments": [...]
}
```

---

### submitToApprover(Request $request)

**Purpose**: Submits verified claims to the approval workflow with comprehensive validation and notification.

**Route**: `POST /admin/submit-to-approver`

**Parameters**:
- `empid` (required|integer) - Employee ID
- `month` (required|integer) - Month

**Returns**: JSON response with submission status

**Business Logic**:
1. **Pending Claims Check**: Validates all claims are verified before submission
2. **Odometer Validation**: Special handling for vehicle claims with KM thresholds
3. **Status Updates**: Changes claim status to submitted and updates step tracking
4. **Amount Synchronization**: Updates verified amounts in monthly summary
5. **Notification System**: Sends email notifications to approvers

**Validation Process**:
```php
// Check for pending claims
$pendingCount = DB::connection(session('expense_connection'))
    ->table($expTable)
    ->where('ClaimYearId', $yearId)
    ->where('CrBy', $empId)
    ->where('ClaimMonth', $month)
    ->where('FilledOkay', 1)
    ->where('ClaimStatus', 'Filled')
    ->count();

// Special odometer validation for departments 2,3 and verticals 1,2
if (in_array($deptId, [2, 3]) && in_array($empVertical, [1, 2])) {
    // Exclude claims exceeding 1200 KM threshold with pending odometer approval
    $excludedIds = $this->getExcludedExpIds($empId, $month, $yearId, $expTable);
}
```

**Frontend Integration**:
```javascript
// Submit claims to approver
async function submitToApprover(empId, month) {
    const confirmSubmit = confirm('Are you sure you want to submit this employee\'s claims to the approver?');
    
    if (!confirmSubmit) return;
    
    try {
        showLoading('Submitting claims to approver...');
        
        const response = await fetch('/admin/submit-to-approver', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
            },
            body: JSON.stringify({
                empid: empId,
                month: month
            })
        });
        
        const result = await response.json();
        
        hideLoading();
        
        if (result.success) {
            showSuccess(result.message);
            // Refresh the pending claims table
            $('#pending-claims-table').DataTable().ajax.reload();
        } else {
            showError(result.message);
        }
    } catch (error) {
        hideLoading();
        console.error('Error submitting to approver:', error);
        showError('Failed to submit claims to approver');
    }
}
```

---

### getEmployeeClaimsAjax(Request $request)

**Purpose**: Retrieves detailed claim information for a specific employee and month with file previews and totals.

**Route**: `POST /admin/get-employee-claims`

**Parameters**:
- `empid` (required) - Employee ID
- `month` (required) - Month

**Returns**: JSON response with detailed claim data

**Business Logic**:
1. **Claim Retrieval**: Fetches all verified claims for employee/month
2. **File Processing**: Generates file previews with S3 URL handling
3. **Special Handling**: Processes vehicle claims with KM details
4. **Total Calculation**: Computes filled and verified amounts
5. **Department Logic**: Applies special rules for specific departments

**Response Structure**:
```json
{
    "success": true,
    "data": [
        {
            "ExpId": "12345",
            "ClaimName": "Local Conveyance",
            "BillDate": "2024-01-15",
            "FilledTAmt": "1875.00",
            "VerifyTAmt": "1875.00",
            "file_preview": "<img style='width:150px;height:80px;' src='...' class='zoom-verify-img'>",
            "with_driver": "<span class='badge bg-success'>Yes</span>",
            "km_details": "<small><b>Op:</b> 15000<br><b>Cl:</b> 15150<br><b>Total Km:</b> 150</small>"
        }
    ],
    "employee": {
        "EmpCode": "EMP001",
        "Name": "John Doe"
    },
    "totals": {
        "filled": 25000.00,
        "verified": 24500.00
    },
    "monthName": "January 2024"
}
```

---

### Utility Methods

#### getCourierDetail(Request $request)
**Purpose**: Retrieves courier tracking information for specific employee/month.

#### updateCourierDetail(Request $request)
**Purpose**: Updates courier receipt information and remarks.

#### returnClaim(Request $request)
**Purpose**: Returns claims to employee for corrections.

#### verifySingleClaim(Request $request)
**Purpose**: Verifies individual claims with amount and remark updates.

## Security Considerations

### Access Control
- **Verifier Authentication**: All methods require verifier-level access
- **Permission Checks**: Role-based action availability
- **Data Isolation**: Year and company-based data segregation

### Data Validation
- **Input Sanitization**: All inputs validated and sanitized
- **Business Rule Validation**: Odometer readings, amount limits, status transitions
- **File Security**: S3 URL validation and secure file access

### Audit Trail
- **Status Tracking**: Complete verification workflow tracking
- **User Attribution**: All actions attributed to specific users
- **Timestamp Recording**: Detailed timestamp tracking for all operations

## Performance Optimizations

### Database Efficiency
```php
// Optimized query with proper joins and indexing
$query = DB::table($table . ' as e')
    ->join('hrims.hrm_employee as emp', 'e.CrBy', '=', 'emp.EmployeeID')
    ->leftJoin("y{$yearId}_monthexpensefinal as mf", function ($join) {
        $join->on('mf.EmployeeID', '=', 'e.CrBy')
             ->on(DB::raw('MONTH(e.BillDate)'), '=', 'mf.Month');
    })
    ->select([...])
    ->where('e.ClaimId', 7)
    ->whereNotIn('e.ClaimStatus', ['Deactivate', 'Draft']);
```

### Caching Strategy
- **Rate Caching**: Cache frequently accessed per-km rates
- **Employee Data Caching**: Cache employee information for session
- **File URL Caching**: Cache S3 URLs for better performance

### Memory Management
- **Batch Processing**: Process large datasets in chunks
- **Lazy Loading**: Load data on-demand for DataTables
- **Query Optimization**: Efficient joins and selective field retrieval

## Configuration

### Environment Variables
```env
# S3 Configuration for file storage
AWS_ACCESS_KEY_ID=your_access_key
AWS_SECRET_ACCESS_KEY=your_secret_key
AWS_DEFAULT_REGION=us-east-1
AWS_BUCKET=your-expense-bucket

# External API endpoints
ESS_BASE_URL=https://ess.vnrseeds.co.in
API_TIMEOUT=10

# Email configuration for notifications
MAIL_MAILER=smtp
MAIL_HOST=smtp.company.com
```

### Database Schema Requirements
```sql
-- Year-specific expense claims table
CREATE TABLE y2024_expenseclaims (
    ExpId INT PRIMARY KEY AUTO_INCREMENT,
    ClaimId INT,
    CrBy VARCHAR(20),
    BillDate DATE,
    odomtr_opening INT,
    odomtr_closing INT,
    TotKm INT,
    WType TINYINT,
    opening_filepath VARCHAR(255),
    closing_filepath VARCHAR(255),
    v_verify CHAR(1) DEFAULT 'N',
    FilledOkay TINYINT DEFAULT 0,
    ClaimStatus VARCHAR(20),
    ClaimAtStep TINYINT DEFAULT 1,
    VerifyBy INT,
    VerifyTAmt DECIMAL(10,2),
    VerifyTRemark TEXT,
    VerifyDate DATE,
    created_at TIMESTAMP,
    updated_at TIMESTAMP
);

-- Monthly expense final summary
CREATE TABLE y2024_monthexpensefinal (
    EmployeeID VARCHAR(20),
    Month TINYINT,
    YearId INT,
    Status VARCHAR(20),
    Total_Claim INT,
    Claim_Amount DECIMAL(10,2),
    Verified_Amount DECIMAL(10,2),
    Verified_Date DATE,
    DateOfSubmit DATE,
    RecevingDate DATE,
    PostDate DATE,
    DocateNo VARCHAR(50),
    Agency VARCHAR(100),
    DocRmk TEXT,
    SubPermission CHAR(1) DEFAULT 'N'
);
```

## Integration Examples

### Rate Controller Integration
```php
// Get current per-km rate
private function getRatePerKM($employeeId, $billDate, $wheelType, $vehiclePolicy = 0)
{
    $rateController = $this->getRateController();
    return $rateController->getRatePerKM($employeeId, $billDate, $wheelType, $vehiclePolicy);
}
```

### S3 File Storage Integration
```php
// Generate secure S3 URLs
protected function getS3Url(string $path): ?string
{
    if ($path === '') return null;
    
    $disk = Storage::disk('s3');
    if (!$disk->exists($path)) return null;
    
    return $disk->url($path);
}
```

### Email Notification Integration
```php
// Send approver notification
private function sendApproverNotification($empId, $month)
{
    $employee = DB::connection('hrims')
        ->table('hrm_employee')
        ->where('EmployeeID', $empId)
        ->first();
        
    $reporting = DB::connection('hrims')
        ->table('hrm_employee_reporting')
        ->where('EmployeeID', $empId)
        ->first();
        
    if ($reporting && $reporting->AppraiserId > 0) {
        Mail::to($approver->EmailId_Vnr)
            ->send(new ClaimApprovalNotification($employeeName, $monthName));
    }
}
```

## Error Handling and Logging

### Exception Management
```php
try {
    // Complex verification logic
    DB::connection(session('expense_connection'))->transaction(function () use ($data) {
        // Multi-table updates
    });
} catch (\Exception $e) {
    Log::error('Verification Controller Error', [
        'method' => 'verifyRunningKmRecord',
        'expId' => $expId,
        'error' => $e->getMessage(),
        'trace' => $e->getTraceAsString()
    ]);
    
    return response()->json([
        'success' => false, 
        'message' => 'Error verifying record: ' . $e->getMessage()
    ], 500);
}
```

### Monitoring and Alerts
- **Verification Metrics**: Track verification completion rates
- **Performance Monitoring**: Monitor query execution times
- **Error Alerting**: Notify administrators of verification failures

This VerifierController serves as the central hub for expense claim verification, providing comprehensive tools for verifiers to review, validate, and approve employee expense submissions while maintaining data integrity and audit trails throughout the verification process.