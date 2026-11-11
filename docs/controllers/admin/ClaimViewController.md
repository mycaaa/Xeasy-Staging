# ClaimViewController Documentation

## Overview

The `ClaimViewController` is a comprehensive controller that manages the viewing, processing, and manipulation of expense claims in the Xeasy expense management system. It handles complex claim data retrieval, file uploads, rate calculations, and provides various utility functions for claim management across different claim types and groups.

## Class Information

- **Namespace**: `App\Http\Controllers\admin`
- **Extends**: `Controller`
- **Location**: `app/Http/Controllers/admin/ClaimViewController.php`

## Constants

```php
const VEHICLE_TYPE_TWO_WHEELER = 2;
const VEHICLE_TYPE_FOUR_WHEELER = 4;
```

## Dependencies

- `App\Helpers\CommonHelper` - Common utility functions
- `App\Http\Controllers\admin\RateController` - Rate calculation logic
- Multiple Models: `ExpenseClaim`, `ClaimType`, `EmployeeVehicle`, etc.
- `Illuminate\Support\Facades\DB` - Database operations
- `Illuminate\Support\Facades\Storage` - File storage operations

## Methods

### getClaimDetailView(Request $request)

**Purpose**: Retrieves and renders detailed view of expense claims with context-aware formatting based on claim type and group.

**Route**: `POST /admin/claim-detail-view`

**Parameters**:
- `claim_id` (required) - The claim type ID
- `expid` (required) - The expense ID

**Returns**: JSON response with rendered HTML content

**Business Logic**:
1. **Context Detection**: Determines view context (view/verify/approve/finance) from referer URL
2. **Claim Retrieval**: Fetches expense claim details from year-specific tables
3. **Remark Processing**: Aggregates remarks and comments with user information
4. **Type-Specific Processing**: Handles different claim types (1-42) with specialized data retrieval
5. **Component Rendering**: Renders appropriate Blade components for each claim type

**Claim Type Processing**:

**Group 1 Claims (Travel & Expenses)**:
- **Claim ID 1**: Lodging claims with city category and hotel information
- **Claim ID 2**: Air travel with flight details, PNR, and insurance
- **Claim ID 3**: Local conveyance with journey details
- **Claim ID 4**: Hired vehicle with agency and billing information
- **Claim ID 7**: Vehicle claims with rate calculation and odometer entries
- **Claim ID 8-9**: Meal and entertainment expenses
- **Claim ID 14-18**: Various travel modes and accommodations
- **Claim ID 42**: Miscellaneous expenses with particulars

**Group 7 Claims (Activity-Based)**:
- Dynamic expense head mapping based on activity types
- Crop and variety selection for agricultural activities
- File upload handling for multiple expense categories

**Response Structure**:
```json
{
    "html": "<div class='claim-detail-view'>...</div>"
}
```

**Error Handling**:
```json
{
    "error": "No expense claim found for ExpId: 12345"
}
```

**Frontend Integration**:
```javascript
// Load claim detail view
async function loadClaimDetail(claimId, expId) {
    try {
        const response = await fetch('/admin/claim-detail-view', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
            },
            body: JSON.stringify({
                claim_id: claimId,
                expid: expId
            })
        });
        
        const result = await response.json();
        
        if (result.html) {
            document.getElementById('claim-detail-container').innerHTML = result.html;
            initializeClaimDetailEvents();
        } else if (result.error) {
            showError(result.error);
        }
    } catch (error) {
        console.error('Error loading claim detail:', error);
        showError('Failed to load claim details');
    }
}

// Initialize events for claim detail view
function initializeClaimDetailEvents() {
    // Rate calculation for vehicle claims
    document.querySelectorAll('.vehicle-type-selector').forEach(selector => {
        selector.addEventListener('change', function() {
            calculateVehicleRate(this.value);
        });
    });
    
    // File preview functionality
    document.querySelectorAll('.file-preview-btn').forEach(btn => {
        btn.addEventListener('click', function() {
            previewFile(this.dataset.fileUrl);
        });
    });
}
```

---

### viewUploadedFiles(Request $request, $expid, $claimid)

**Purpose**: Retrieves and organizes uploaded files for expense claims with proper URL generation for different claim groups.

**Route**: `GET /admin/view-uploaded-files/{expid}/{claimid}`

**Parameters**:
- `expid` (required) - Expense ID
- `claimid` (required) - Claim type ID

**Returns**: JSON response with organized file data

**Business Logic**:
1. **Group Detection**: Determines claim group (1 or 7) for different file handling
2. **File Mapping**: Maps upload sequences to meaningful file categories
3. **URL Generation**: Creates S3 URLs for file access
4. **Category Organization**: Groups files by expense head categories for Group 7

**File Organization**:

**Group 1 Files**:
```php
$uploadSequenceMap = [
    'File_Meeting_Hall' => 101,
    'File_Lodging' => 102,
    'File_Meals' => 103,
    'File_Business_Entertainment' => 104,
    'File_Others' => 105,
    'File_Toll_Pass' => 106,
    'File_Toll_Tax' => 107,
    'File_International_Recharge' => 108,
    'File_Printing_Stationery' => 109,
];
```

**Response Structure**:
```json
{
    "success": true,
    "data": {
        "expid": "12345",
        "cgid": 1,
        "uploaded_files": [
            {
                "sequence": 102,
                "cuId": "456",
                "file_column": "File_Lodging",
                "file_path": "receipt_hotel_123.pdf",
                "file_url": "https://s3.amazonaws.com/bucket/Expense/2024/1729/receipt_hotel_123.pdf",
                "created_at": "2024-01-15 10:30:00"
            }
        ]
    }
}
```

**Frontend Integration**:
```javascript
// View uploaded files
async function viewUploadedFiles(expId, claimId) {
    try {
        const response = await fetch(`/admin/view-uploaded-files/${expId}/${claimId}`);
        const result = await response.json();
        
        if (result.success) {
            displayUploadedFiles(result.data.uploaded_files);
        }
    } catch (error) {
        console.error('Error viewing files:', error);
    }
}

// Display files in modal or container
function displayUploadedFiles(files) {
    const container = document.getElementById('uploaded-files-container');
    container.innerHTML = '';
    
    files.forEach(file => {
        const fileElement = `
            <div class="file-item">
                <div class="file-info">
                    <strong>${file.file_column.replace('File_', '').replace('_', ' ')}</strong>
                    <small class="text-muted d-block">${file.created_at}</small>
                </div>
                <div class="file-actions">
                    <a href="${file.file_url}" target="_blank" class="btn btn-sm btn-primary">
                        <i class="fas fa-eye"></i> View
                    </a>
                    <a href="${file.file_url}" download class="btn btn-sm btn-secondary">
                        <i class="fas fa-download"></i> Download
                    </a>
                </div>
            </div>
        `;
        container.innerHTML += fileElement;
    });
}
```

---

### calculateRateAjax(Request $request)

**Purpose**: Calculates per-kilometer rates for vehicle claims based on employee, date, and vehicle type.

**Route**: `POST /admin/calculate-rate`

**Parameters**:
- `employee_id` (required|integer) - Employee ID
- `bill_date` (required|date) - Bill date for rate calculation
- `vehicle_type` (required|in:2,4) - Vehicle type (2W or 4W)

**Returns**: JSON response with calculated rate information

**Business Logic**:
1. **Rate Retrieval**: Uses RateController to get current rates
2. **Date-Based Calculation**: Considers rate changes over time
3. **Vehicle Type Handling**: Different rates for 2-wheeler vs 4-wheeler
4. **Formatting**: Provides both raw and formatted rate values

**Response Structure**:
```json
{
    "success": true,
    "data": {
        "rate": 12.50,
        "formatted_rate": "12.50",
        "vehicle_type": 4,
        "vehicle_type_label": "4W"
    },
    "message": "Rate calculated successfully"
}
```

**Frontend Integration**:
```javascript
// Calculate vehicle rate
async function calculateVehicleRate(employeeId, billDate, vehicleType) {
    try {
        const response = await fetch('/admin/calculate-rate', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
            },
            body: JSON.stringify({
                employee_id: employeeId,
                bill_date: billDate,
                vehicle_type: vehicleType
            })
        });
        
        const result = await response.json();
        
        if (result.success) {
            updateRateDisplay(result.data);
            calculateTotalAmount();
        }
    } catch (error) {
        console.error('Error calculating rate:', error);
    }
}

// Update rate display in form
function updateRateDisplay(rateData) {
    document.getElementById('rate-per-km').textContent = rateData.formatted_rate;
    document.getElementById('vehicle-type-label').textContent = rateData.vehicle_type_label;
    document.getElementById('rate-input').value = rateData.rate;
}
```

---

### getActiveClaimTypes()

**Purpose**: Retrieves claim types accessible to the current user based on role and permissions.

**Route**: `GET /admin/active-claim-types`

**Parameters**: None

**Returns**: JSON response with grouped claim types

**Business Logic**:
1. **Role Check**: Super Admin gets all claim types, others get filtered list
2. **Permission Filtering**: Checks user-specific claim type assignments
3. **Group Organization**: Groups claim types by claim group for better UI organization
4. **Status Filtering**: Only includes active claim types (status A or B)

**Response Structure**:
```json
{
    "success": true,
    "data": [
        {
            "text": "Travel Claims",
            "children": [
                {"id": 1, "text": "Lodging"},
                {"id": 2, "text": "Air Travel"},
                {"id": 3, "text": "Local Conveyance"}
            ]
        },
        {
            "text": "Activity Claims", 
            "children": [
                {"id": 101, "text": "Field Activity"},
                {"id": 102, "text": "Research Activity"}
            ]
        }
    ],
    "message": "Claim type fetched successfully."
}
```

---

### uploadClaimFiles(Request $request)

**Purpose**: Processes and uploads claim files with comprehensive data validation and storage management.

**Route**: `POST /admin/upload-claim-files`

**Parameters**:
- `claimid` (required) - Claim type ID
- `expid` (required) - Expense ID
- `cgid` (required) - Claim group ID
- `empid` (required) - Employee ID
- `totalAmount` (required) - Total claim amount
- `formData` (required) - Form data as JSON
- `expensesData` (optional) - Expense data for Group 7 claims

**Returns**: JSON response with upload status

**Business Logic**:
1. **Transaction Management**: Uses database transactions for data integrity
2. **Group-Specific Processing**: Different logic for Group 1 vs Group 7 claims
3. **File Upload Handling**: Manages multiple file uploads with proper naming
4. **Data Validation**: Validates form data and file formats
5. **Amount Tracking**: Updates various amount fields (filled, verified, approved, finance)

**Group 7 Processing**:
- Dynamic expense head processing
- Crop and variety list management
- Activity type assignment
- Multi-file upload with categorization

**Group 1 Processing**:
- Travel data processing by claim type
- Journey information handling
- Hotel and vendor data management
- Insurance and booking details

**Frontend Integration**:
```javascript
// Upload claim files
async function uploadClaimFiles(formData, files) {
    const uploadData = new FormData();
    
    // Add form data
    Object.keys(formData).forEach(key => {
        uploadData.append(key, formData[key]);
    });
    
    // Add files
    files.forEach((file, index) => {
        uploadData.append(`files[${index}]`, file);
    });
    
    try {
        showLoading('Uploading claim files...');
        
        const response = await fetch('/admin/upload-claim-files', {
            method: 'POST',
            headers: {
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
            },
            body: uploadData
        });
        
        const result = await response.json();
        
        hideLoading();
        
        if (result.success) {
            showSuccess('Claim files uploaded successfully!');
            refreshClaimList();
        } else {
            showError(result.message);
        }
    } catch (error) {
        hideLoading();
        console.error('Error uploading files:', error);
        showError('Failed to upload claim files');
    }
}
```

---

### sendMessage(Request $request)

**Purpose**: Adds remarks/messages to expense claims for communication between stakeholders.

**Route**: `POST /admin/send-message`

**Parameters**:
- `expid` (required) - Expense ID
- `message` (required|string) - Message content

**Returns**: JSON response with rendered message HTML

**Business Logic**:
1. **Message Storage**: Stores message in year-specific remark table
2. **Role Assignment**: Assigns appropriate role based on current user
3. **HTML Generation**: Creates formatted HTML for immediate display
4. **Timestamp Tracking**: Records creation time for message ordering

**Response Structure**:
```json
{
    "html": "<div class='d-flex mb-2 justify-content-end'>...</div>"
}
```

---

### actionHold(Request $request) / actionDraft(Request $request)

**Purpose**: Changes claim status to Hold or Draft with counter updates.

**Route**: `POST /admin/action-hold` / `POST /admin/action-draft`

**Parameters**:
- `expid` (required|integer) - Expense ID
- `claimid` (required|integer) - Claim ID

**Returns**: JSON response with updated counts

**Business Logic**:
1. **Status Update**: Changes claim status in database
2. **Counter Calculation**: Updates hold and draft counts for UI
3. **Validation**: Ensures claim exists and is in valid state for status change
4. **Audit Trail**: Maintains status change history

---

### Utility Methods

#### getCities(Request $request)
**Purpose**: Retrieves cities with search functionality for travel claims.

#### getHotels(Request $request)
**Purpose**: Fetches hotel list with city-based filtering.

#### getEmployees(Request $request)
**Purpose**: Searches employees for claim assignment and processing.

#### getVarieties(Request $request)
**Purpose**: Gets crop varieties based on selected crops for agricultural activities.

## Security Considerations

### Access Control
- **Admin Authentication**: All methods require admin-level access
- **Role-Based Filtering**: Claim types filtered by user permissions
- **Data Isolation**: Year-based table access ensures data segregation

### Data Validation
- **Input Sanitization**: All inputs validated and sanitized
- **File Upload Security**: File type and size validation
- **SQL Injection Prevention**: Parameterized queries throughout

### File Security
- **S3 Integration**: Secure file storage with proper access controls
- **Path Validation**: Prevents directory traversal attacks
- **File Type Restrictions**: Only allowed file types accepted

## Performance Optimizations

### Database Efficiency
```php
// Optimized claim detail query with proper joins
$expense_detail = DB::connection(session('expense_connection'))
    ->table(ExpenseClaim::tableName($yearId))
    ->where('ExpId', $expid)
    ->first();

// Efficient file retrieval with joins
$files = DB::connection(session('expense_connection'))
    ->table($uploadsTable . ' as u')
    ->join($claimsTable . ' as c', 'u.ExpId', '=', 'c.ExpId')
    ->where('u.ExpId', $expid)
    ->orderBy('u.UploadSequence')
    ->select('u.cuId', 'u.ExpId', 'u.FileName', 'u.UploadSequence', 'u.created_at', 'c.CrBy', 'c.ClaimYearId')
    ->get();
```

### Memory Management
- **Lazy Loading**: Claims loaded on-demand
- **Efficient Collections**: Laravel collections for data manipulation
- **Transaction Batching**: Grouped database operations

### Caching Strategy
- **Rate Caching**: Cache frequently accessed rates
- **Claim Type Caching**: Cache claim type configurations
- **File URL Caching**: Cache S3 URLs for better performance

## Configuration

### Environment Variables
```env
# File storage configuration
AWS_ACCESS_KEY_ID=your_access_key
AWS_SECRET_ACCESS_KEY=your_secret_key
AWS_DEFAULT_REGION=us-east-1
AWS_BUCKET=your-expense-bucket

# Database connections
DB_CONNECTION_EXPENSE=expense_db
DB_CONNECTION_HRIMS=hrims_db
```

### Database Schema Requirements
```sql
-- Year-specific expense claims table
CREATE TABLE y2024_expenseclaims (
    ExpId INT PRIMARY KEY AUTO_INCREMENT,
    ClaimId INT,
    CrBy VARCHAR(20),
    BillDate DATE,
    FilledTAmt DECIMAL(10,2),
    VerifyTAmt DECIMAL(10,2),
    ApprTAmt DECIMAL(10,2),
    FinancedTAmt DECIMAL(10,2),
    ClaimStatus VARCHAR(20),
    created_at TIMESTAMP,
    updated_at TIMESTAMP
);

-- File uploads table
CREATE TABLE y2024_claimuploads (
    cuId INT PRIMARY KEY AUTO_INCREMENT,
    ExpId INT,
    FileName VARCHAR(255),
    UploadSequence INT,
    created_at TIMESTAMP
);
```

## Integration Examples

### Rate Calculation Integration
```php
// Custom rate calculation with policy consideration
$rate = $this->getRatePerKM($employeeId, $billDate, $vehicleType, $vehiclePolicy);

// Rate comparison for different vehicle types
$rate_2w = $this->getRatePerKM($employeeId, $billDate, self::VEHICLE_TYPE_TWO_WHEELER);
$rate_4w = $this->getRatePerKM($employeeId, $billDate, self::VEHICLE_TYPE_FOUR_WHEELER);
```

### File Upload Integration
```php
// S3 file upload with proper path structure
$filePath = "Expense/{$yearId}/{$employeeId}/{$fileName}";
Storage::disk('s3')->put($filePath, $fileContent);

// Generate secure URL for file access
$fileUrl = Storage::disk('s3')->url($filePath);
```

## Error Handling and Logging

### Exception Management
```php
try {
    DB::connection(session('expense_connection'))->transaction(function () use ($data) {
        // Complex claim processing logic
    });
} catch (\Exception $e) {
    Log::error('Claim processing error', [
        'expid' => $expId,
        'error' => $e->getMessage(),
        'trace' => $e->getTraceAsString()
    ]);
    
    return $this->jsonError('Failed to process claim: ' . $e->getMessage(), 500);
}
```

### Monitoring and Alerts
- **File Upload Monitoring**: Track upload success/failure rates
- **Performance Metrics**: Monitor query execution times
- **Error Alerting**: Notify administrators of critical failures

This ClaimViewController serves as the central hub for expense claim viewing and processing, providing comprehensive functionality for managing complex expense workflows across different claim types and organizational requirements.