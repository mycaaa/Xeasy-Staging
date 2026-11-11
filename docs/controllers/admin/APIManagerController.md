# APIManagerController Documentation

## Overview

The `APIManagerController` manages API configurations and field mappings for external data integration in the Xeasy expense management system. It provides functionality to create, manage, and configure APIs for data synchronization, along with sophisticated field mapping capabilities between temporary and main database tables.

## Class Information

- **Namespace**: `App\Http\Controllers\admin`
- **Extends**: `Controller`
- **Location**: `app/Http/Controllers/admin/APIManagerController.php`

## Dependencies

- `App\Models\APIManager` - API configuration model
- `App\Models\ColumnFieldMapping` - Field mapping model
- `App\Http\Requests\StoreAPIManagerRequest` - API creation validation
- `App\Http\Requests\UpdateAPIManagerRequest` - API update validation
- `Illuminate\Support\Facades\DB` - Database operations
- `Illuminate\Support\Facades\Auth` - Authentication

## Methods

### index()

**Purpose**: Displays the API management interface with all configured APIs.

**Route**: `GET /admin/api-manager`

**Parameters**: None

**Returns**: View `admin.api_manager` with APIs data

**Business Logic**:
- Retrieves all API configurations from the database
- Provides interface for API CRUD operations
- Shows API status and configuration details

**Example Usage**:
```php
// Route definition
Route::get('/admin/api-manager', [APIManagerController::class, 'index'])
    ->name('admin.api.manager');
```

**Frontend Integration**:
```javascript
// Load API manager page
function loadAPIManager() {
    window.location.href = '/admin/api-manager';
}
```

---

### store(StoreAPIManagerRequest $request)

**Purpose**: Creates a new API configuration with validation and audit tracking.

**Route**: `POST /admin/api-manager`

**Parameters**:
- `claim_id` (required) - Associated claim type ID
- `api_name` (required) - API display name
- `endpoint` (required) - API endpoint URL
- `status` (required) - API status (active/inactive)

**Returns**: JSON response with created API data

**Business Logic**:
1. **Validation**: Uses StoreAPIManagerRequest for input validation
2. **Creation**: Creates new API record with user attribution
3. **Audit Trail**: Records creator and creation timestamp
4. **Response**: Returns success response with API data

**Request Validation**:
```php
// StoreAPIManagerRequest validation rules
public function rules()
{
    return [
        'claim_id' => 'required|integer|exists:claimtype,ClaimId',
        'api_name' => 'required|string|max:255',
        'endpoint' => 'required|url|max:500',
        'status' => 'required|boolean'
    ];
}
```

**Response Structure**:
```json
{
    "success": true,
    "message": "API created successfully.",
    "data": {
        "id": 1,
        "claim_id": 7,
        "name": "Field Activity API",
        "endpoint": "https://api.example.com/field-activities",
        "status": 1,
        "created_by": 123,
        "created_at": "2024-01-15T10:30:00.000000Z"
    }
}
```

**Frontend Integration**:
```javascript
// Create new API
async function createAPI(formData) {
    try {
        const response = await fetch('/admin/api-manager', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
            },
            body: JSON.stringify(formData)
        });
        
        const result = await response.json();
        
        if (result.success) {
            showSuccess(result.message);
            refreshAPIList();
            closeAPIModal();
        } else {
            showError(result.message);
        }
    } catch (error) {
        console.error('Error creating API:', error);
        showError('Failed to create API');
    }
}

// API creation form handler
function handleAPIForm() {
    const form = document.getElementById('api-form');
    form.addEventListener('submit', function(e) {
        e.preventDefault();
        
        const formData = {
            claim_id: document.getElementById('claim-id').value,
            api_name: document.getElementById('api-name').value,
            endpoint: document.getElementById('endpoint').value,
            status: document.getElementById('status').checked ? 1 : 0
        };
        
        createAPI(formData);
    });
}
```

---

### edit(string $id)

**Purpose**: Retrieves API configuration data for editing.

**Route**: `GET /admin/api-manager/{id}/edit`

**Parameters**:
- `id` (required) - API ID

**Returns**: JSON response with API data

**Business Logic**:
1. **Retrieval**: Finds API by ID or throws 404
2. **Data Return**: Returns complete API configuration
3. **Error Handling**: Handles non-existent API gracefully

**Response Structure**:
```json
{
    "success": true,
    "message": "API retrieved successfully.",
    "data": {
        "id": 1,
        "claim_id": 7,
        "name": "Field Activity API",
        "endpoint": "https://api.example.com/field-activities",
        "status": 1,
        "created_by": 123,
        "updated_by": 456,
        "created_at": "2024-01-15T10:30:00.000000Z",
        "updated_at": "2024-01-16T14:20:00.000000Z"
    }
}
```

---

### update(UpdateAPIManagerRequest $request, string $id)

**Purpose**: Updates existing API configuration with validation and audit tracking.

**Route**: `PUT /admin/api-manager/{id}`

**Parameters**:
- `id` (required) - API ID
- `claim_id` (required) - Associated claim type ID
- `api_name` (required) - API display name
- `endpoint` (required) - API endpoint URL
- `status` (required) - API status

**Returns**: JSON response with updated API data

**Business Logic**:
1. **Validation**: Uses UpdateAPIManagerRequest for input validation
2. **Update**: Updates existing API record with new data
3. **Audit Trail**: Records updater and update timestamp
4. **Response**: Returns success response with updated data

**Frontend Integration**:
```javascript
// Update existing API
async function updateAPI(apiId, formData) {
    try {
        const response = await fetch(`/admin/api-manager/${apiId}`, {
            method: 'PUT',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
            },
            body: JSON.stringify(formData)
        });
        
        const result = await response.json();
        
        if (result.success) {
            showSuccess(result.message);
            refreshAPIList();
            closeAPIModal();
        } else {
            showError(result.message);
        }
    } catch (error) {
        console.error('Error updating API:', error);
        showError('Failed to update API');
    }
}

// Load API data for editing
async function editAPI(apiId) {
    try {
        const response = await fetch(`/admin/api-manager/${apiId}/edit`);
        const result = await response.json();
        
        if (result.success) {
            populateAPIForm(result.data);
            showAPIModal('Edit API');
        } else {
            showError(result.message);
        }
    } catch (error) {
        console.error('Error loading API data:', error);
        showError('Failed to load API data');
    }
}

// Populate form with API data
function populateAPIForm(apiData) {
    document.getElementById('claim-id').value = apiData.claim_id;
    document.getElementById('api-name').value = apiData.name;
    document.getElementById('endpoint').value = apiData.endpoint;
    document.getElementById('status').checked = apiData.status === 1;
}
```

---

### destroy(string $id)

**Purpose**: Deletes an API configuration from the system.

**Route**: `DELETE /admin/api-manager/{id}`

**Parameters**:
- `id` (required) - API ID

**Returns**: JSON response with deletion confirmation

**Business Logic**:
1. **Retrieval**: Finds API by ID or throws 404
2. **Deletion**: Removes API record from database
3. **Response**: Returns success confirmation

**Frontend Integration**:
```javascript
// Delete API
async function deleteAPI(apiId) {
    const confirmDelete = confirm('Are you sure you want to delete this API? This action cannot be undone.');
    
    if (!confirmDelete) return;
    
    try {
        const response = await fetch(`/admin/api-manager/${apiId}`, {
            method: 'DELETE',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
            }
        });
        
        const result = await response.json();
        
        if (result.success) {
            showSuccess(result.message);
            refreshAPIList();
        } else {
            showError(result.message);
        }
    } catch (error) {
        console.error('Error deleting API:', error);
        showError('Failed to delete API');
    }
}
```

---

### getActiveAPIs()

**Purpose**: Retrieves all active API configurations for integration purposes.

**Route**: `GET /admin/api-manager/active`

**Parameters**: None

**Returns**: JSON response with active APIs

**Business Logic**:
- Filters APIs by active status (status = 1)
- Returns only APIs available for use
- Used by other systems for API discovery

**Response Structure**:
```json
{
    "success": true,
    "message": "Active APIs retrieved successfully.",
    "data": [
        {
            "id": 1,
            "claim_id": 7,
            "name": "Field Activity API",
            "endpoint": "https://api.example.com/field-activities",
            "status": 1
        },
        {
            "id": 2,
            "claim_id": 8,
            "name": "Research Data API",
            "endpoint": "https://api.example.com/research-data",
            "status": 1
        }
    ]
}
```

---

### showMappingPage($claim_id)

**Purpose**: Displays the field mapping interface for a specific claim type.

**Route**: `GET /admin/api-manager/mapping/{claim_id}`

**Parameters**:
- `claim_id` (required) - Claim type ID

**Returns**: View `admin.api_fields_mapping` with columns data

**Business Logic**:
1. **Table Discovery**: Identifies temporary table for claim type
2. **Column Analysis**: Retrieves table columns excluding system fields
3. **Interface Preparation**: Prepares mapping interface with available columns

**Database Operations**:
```php
// Get table columns dynamically
$tempTable = "temp_punch_{$claim_id}";
$excludeColumns = ['id', 'created_at', 'updated_at'];
$columns = DB::getSchemaBuilder()->getColumnListing($tempTable);
$columns = array_diff($columns, $excludeColumns);
```

**Frontend Integration**:
```javascript
// Load field mapping page
function loadFieldMapping(claimId) {
    window.location.href = `/admin/api-manager/mapping/${claimId}`;
}

// Initialize mapping interface
function initializeFieldMapping() {
    const mappingContainer = document.getElementById('field-mappings');
    const addMappingBtn = document.getElementById('add-mapping');
    
    addMappingBtn.addEventListener('click', function() {
        addMappingRow();
    });
    
    // Initialize existing mappings
    loadExistingMappings();
}

// Add new mapping row
function addMappingRow() {
    const row = document.createElement('div');
    row.className = 'mapping-row';
    row.innerHTML = `
        <div class="row mb-3">
            <div class="col-md-3">
                <select class="form-control temp-column" name="temp_column[]" required>
                    <option value="">Select Temp Column</option>
                    ${generateColumnOptions()}
                </select>
            </div>
            <div class="col-md-2">
                <select class="form-control input-type" name="input_type[]" required>
                    <option value="Input">Input</option>
                    <option value="Select">Select</option>
                </select>
            </div>
            <div class="col-md-2">
                <select class="form-control punch-table" name="punch_table[]">
                    <option value="">Select Table</option>
                    ${generateTableOptions()}
                </select>
            </div>
            <div class="col-md-2">
                <select class="form-control punch-column" name="punch_column[]">
                    <option value="">Select Column</option>
                </select>
            </div>
            <div class="col-md-2">
                <input type="text" class="form-control condition" name="condition[]" placeholder="Condition">
            </div>
            <div class="col-md-1">
                <button type="button" class="btn btn-danger remove-mapping">Remove</button>
            </div>
        </div>
    `;
    
    document.getElementById('field-mappings').appendChild(row);
    initializeMappingRowEvents(row);
}
```

---

### storeFieldMapping(Request $request, $claim_id)

**Purpose**: Stores comprehensive field mappings for API data integration.

**Route**: `POST /admin/api-manager/mapping/{claim_id}`

**Parameters**:
- `claim_id` (required) - Claim type ID
- `temp_column` (required|array) - Source columns from temporary table
- `input_type` (required|array) - Input type for each field (Input/Select)
- `select_table` (optional|array) - Reference tables for select inputs
- `search_column` (optional|array) - Search columns for lookups
- `return_column` (optional|array) - Return columns from lookups
- `punch_table` (optional|array) - Target tables for data insertion
- `punch_column` (optional|array) - Target columns for data insertion
- `condition` (optional|array) - Additional conditions for mappings

**Returns**: JSON response with mapping save status

**Business Logic**:
1. **Validation**: Comprehensive validation of mapping data
2. **Data Processing**: Processes arrays of mapping configurations
3. **Cleanup**: Removes existing mappings for claim type
4. **Storage**: Stores new mapping configurations
5. **Audit Trail**: Records creator and updater information

**Validation Rules**:
```php
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
```

**Frontend Integration**:
```javascript
// Save field mappings
async function saveFieldMappings(claimId) {
    const formData = new FormData(document.getElementById('mapping-form'));
    
    try {
        showLoading('Saving field mappings...');
        
        const response = await fetch(`/admin/api-manager/mapping/${claimId}`, {
            method: 'POST',
            headers: {
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
            },
            body: formData
        });
        
        const result = await response.json();
        
        hideLoading();
        
        if (result.success) {
            showSuccess(result.message);
        } else {
            showError(result.message);
        }
    } catch (error) {
        hideLoading();
        console.error('Error saving mappings:', error);
        showError('Failed to save field mappings');
    }
}

// Validate mapping form
function validateMappingForm() {
    const mappingRows = document.querySelectorAll('.mapping-row');
    let isValid = true;
    
    mappingRows.forEach(row => {
        const tempColumn = row.querySelector('.temp-column').value;
        const inputType = row.querySelector('.input-type').value;
        const punchTable = row.querySelector('.punch-table').value;
        const punchColumn = row.querySelector('.punch-column').value;
        
        if (!tempColumn || !inputType) {
            isValid = false;
            showError('Please fill all required fields');
            return false;
        }
        
        if (!punchTable || !punchColumn) {
            // Skip rows without target mapping
            row.style.display = 'none';
        }
    });
    
    return isValid;
}
```

---

### Utility Methods

#### getColumns(Request $request, $table)
**Purpose**: Retrieves column names for a specific database table.
**Returns**: JSON response with table columns

#### getTables()
**Purpose**: Retrieves all available database tables.
**Returns**: JSON response with table names

#### mapFields(Request $request)
**Purpose**: Executes field mapping from temporary to main tables.
**Returns**: JSON response with mapping execution status

## Security Considerations

### Access Control
- **Admin Authentication**: All methods require admin-level access
- **Permission Validation**: API management requires specific permissions
- **User Attribution**: All operations tracked with user IDs

### Data Validation
- **Input Sanitization**: All inputs validated and sanitized
- **URL Validation**: API endpoints validated as proper URLs
- **SQL Injection Prevention**: Parameterized queries for dynamic table operations

### API Security
- **Endpoint Validation**: API endpoints validated for security
- **Status Control**: APIs can be disabled for security purposes
- **Audit Logging**: Complete audit trail for API configurations

## Performance Optimizations

### Database Efficiency
```php
// Efficient column retrieval
$columns = DB::getSchemaBuilder()->getColumnListing($tempTable);
$columns = array_diff($columns, $excludeColumns);

// Batch mapping operations
ColumnFieldMapping::where('claim_id', $claim_id)->delete();
if (!empty($mappings)) {
    ColumnFieldMapping::insert($mappings);
}
```

### Caching Strategy
- **Schema Caching**: Cache table schemas for better performance
- **API Configuration Caching**: Cache active API configurations
- **Mapping Cache**: Cache field mappings for frequent operations

### Memory Management
- **Batch Processing**: Process large mapping operations in batches
- **Efficient Queries**: Use selective field retrieval
- **Resource Cleanup**: Proper cleanup of temporary resources

## Configuration

### Environment Variables
```env
# API Configuration
API_TIMEOUT=30
API_RETRY_ATTEMPTS=3
API_RATE_LIMIT=100

# Database Configuration
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=expense_db
```

### Database Schema Requirements
```sql
-- API Manager table
CREATE TABLE api_managers (
    id INT PRIMARY KEY AUTO_INCREMENT,
    claim_id INT NOT NULL,
    name VARCHAR(255) NOT NULL,
    endpoint VARCHAR(500) NOT NULL,
    status TINYINT(1) DEFAULT 1,
    created_by INT,
    updated_by INT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (claim_id) REFERENCES claimtype(ClaimId),
    FOREIGN KEY (created_by) REFERENCES users(id),
    FOREIGN KEY (updated_by) REFERENCES users(id)
);

-- Column Field Mapping table
CREATE TABLE column_field_mappings (
    id INT PRIMARY KEY AUTO_INCREMENT,
    claim_id INT NOT NULL,
    temp_column VARCHAR(255) NOT NULL,
    input_type ENUM('Input', 'Select') NOT NULL,
    select_table VARCHAR(255),
    search_column VARCHAR(255),
    return_column VARCHAR(255),
    punch_table VARCHAR(255),
    punch_column VARCHAR(255),
    condition TEXT,
    created_by INT,
    updated_by INT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);
```

## Integration Examples

### API Configuration Integration
```php
// Get active APIs for specific claim type
$apis = APIManager::where('claim_id', $claimId)
    ->where('status', 1)
    ->get();

// Execute API call with configuration
foreach ($apis as $api) {
    $response = Http::timeout(30)->get($api->endpoint);
    // Process response data
}
```

### Field Mapping Integration
```php
// Apply field mappings during data processing
$mappings = ColumnFieldMapping::where('claim_id', $claimId)->get();

foreach ($mappings as $mapping) {
    if ($mapping->input_type === 'Select') {
        // Handle select-based mapping with lookup
        $value = DB::table($mapping->select_table)
            ->where($mapping->search_column, $sourceValue)
            ->value($mapping->return_column);
    } else {
        // Handle direct input mapping
        $value = $sourceValue;
    }
    
    // Insert into target table
    DB::table($mapping->punch_table)
        ->updateOrInsert(
            ['ExpId' => $expId],
            [$mapping->punch_column => $value]
        );
}
```

## Error Handling and Logging

### Exception Management
```php
try {
    // API configuration operations
    $api = APIManager::create($data);
} catch (\Exception $e) {
    Log::error('API Manager Error', [
        'method' => 'store',
        'data' => $data,
        'error' => $e->getMessage(),
        'trace' => $e->getTraceAsString()
    ]);
    
    return $this->jsonError('Failed to create API: ' . $e->getMessage(), 500);
}
```

### Monitoring and Alerts
- **API Health Monitoring**: Monitor API endpoint availability
- **Mapping Validation**: Validate field mappings during execution
- **Performance Tracking**: Monitor mapping execution performance

This APIManagerController serves as a comprehensive system for managing external API integrations and data field mappings, enabling seamless data synchronization between external systems and the expense management platform.