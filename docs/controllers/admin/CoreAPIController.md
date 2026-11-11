# 🔗 Admin CoreAPIController Documentation

The Admin CoreAPIController manages external API synchronization and data import functionality within the Xeasy Laravel expense management system, providing centralized API management and automated data synchronization capabilities.

## 📋 Overview

**Location**: `app/Http/Controllers/admin/CoreAPIController.php`

**Purpose**: Provides comprehensive API management functionality including external API synchronization, data import operations, and dynamic table creation for synchronized data storage.

**Database Tables**:
- `core_apis` - API configuration and metadata storage
- `core_api_setup` - API connection settings and credentials
- `core_*` - Dynamically created tables for synchronized data

**Middleware**: Inherits from base Controller (typically includes auth middleware)

**Key Features**:
- External API synchronization
- Dynamic table creation and management
- Bulk data import with upsert operations
- API configuration management
- Error handling and logging
- Parameterized API calls

## 📊 Public Methods

### 1. API Management Index

#### `index(): View`

**Route**: `GET /admin/core-apis`
**Permission**: Admin access required

**Purpose**: Displays the core API management interface with list of available APIs.

**Parameters**: None

**Response**: Returns Blade view with API list data

**View Data**:
```php
[
    'api_list' => Collection // All APIs from core_apis table
]
```

**Business Logic**:
1. **Data Retrieval**: Fetches all API records from `core_apis` table
2. **View Rendering**: Returns admin.core view with API list
3. **Interface Display**: Shows API management dashboard

**Database Query**:
```sql
SELECT * FROM core_apis;
```

---

### 2. API Synchronization

#### `sync(): JsonResponse`

**Route**: `POST /admin/core-apis/sync`
**Permission**: Admin access required

**Purpose**: Synchronizes API configurations from external source and updates local API registry.

**Parameters**: None

**Response Structure**:

**Success Response**:
```json
{
    "status": 200,
    "msg": "API synchronized successfully."
}
```

**Error Responses**:

*API Request Failed*:
```json
{
    "status": 400,
    "msg": "Failed to synchronize APIs."
}
```

*Invalid Response Format*:
```json
{
    "status": 400,
    "msg": "Unexpected API response format."
}
```

*Database Error*:
```json
{
    "status": 500,
    "msg": "Database error occurred."
}
```

**Business Logic**:
1. **Configuration Retrieval**: Gets API setup from `core_api_setup` table
2. **External API Call**: Makes authenticated request to external API registry
3. **Response Validation**: Validates response structure and data format
4. **Data Processing**: Maps external API data to local format
5. **Database Update**: Truncates and repopulates `core_apis` table
6. **Error Handling**: Comprehensive error logging and response handling

**API Request Details**:
```php
$response = Http::withHeaders([
    'api-key' => $apiKey,
    'Accept' => 'application/json'
])->get("$baseUrl/api/project/apis");
```

**Data Mapping**:
```php
$apiRecords = array_map(function ($value) {
    return [
        'api_id' => $value['id'] ?? null,
        'api_name' => $value['api_name'] ?? '',
        'api_end_point' => $value['api_end_point'] ?? '',
        'description' => $value['description'] ?? '',
        'parameters' => $value['parameters'],
        'table_name' => $value['table_name'] ?? ''
    ];
}, $data['api_list']);
```

---

### 3. API Data Import

#### `importAPISData(Request $request): JsonResponse`

**Route**: `POST /admin/core-apis/import`
**Permission**: Admin access required

**Purpose**: Imports data from multiple external APIs into dynamically created local tables.

**Request Parameters**:
```php
[
    'api_end_points' => ['endpoint1', 'endpoint2', 'endpoint3'] // Array of API endpoints
]
```

**Request Body Example**:
```json
{
    "api_end_points": [
        "employees",
        "departments", 
        "projects"
    ]
}
```

**Response Structure**:

**Success Response**:
```json
{
    "status": 200,
    "msg": "APIs synchronized successfully"
}
```

**Error Responses**:

*No Records Found*:
```json
{
    "status": 400,
    "msg": "No records found in table core_employees"
}
```

*API Request Failed*:
```json
{
    "status": 400,
    "msg": "Failed to synchronize APIs."
}
```

*Invalid Response Format*:
```json
{
    "status": 400,
    "msg": "Unexpected API response format."
}
```

**Business Logic**:
1. **Endpoint Processing**: Iterates through provided API endpoints
2. **Configuration Lookup**: Retrieves API metadata from `core_apis` table
3. **Parameter Handling**: Determines if API requires parameters
4. **Conditional Requests**: Makes parameterized or simple API calls
5. **Data Processing**: Processes each API response individually
6. **Table Management**: Creates/updates tables dynamically

**Parameter Handling Logic**:
```php
if ($parameter) {
    // Parameterized API calls
    $ids = DB::table($tableName)->pluck('id');
    foreach ($ids as $id) {
        $response = Http::withHeaders([
            'api-key' => $apiKey,
            'Accept' => 'application/json'
        ])->get("$baseUrl/api/$api", [$parameter => $id]);
        
        $this->processApiResponse($tableName, $response, $api, $parameter, $id);
    }
} else {
    // Simple API calls
    $response = Http::withHeaders([
        'api-key' => $apiKey,
        'Accept' => 'application/json'
    ])->get("$baseUrl/api/$api");
    
    $this->processApiResponse($tableName, $response, $api);
}
```

---

## 🔧 Implementation Details

### Private Methods

#### API Response Processing

##### `processApiResponse($tableName, $response, $api, $parameter = null, $id = null): JsonResponse`

**Purpose**: Processes individual API responses and manages data synchronization.

**Parameters**:
- `$tableName`: Target table name for data storage
- `$response`: HTTP response object from API call
- `$api`: API endpoint identifier
- `$parameter`: Optional parameter name for parameterized calls
- `$id`: Optional parameter value for parameterized calls

**Business Logic**:
1. **Execution Time Extension**: Sets time limit to 300 seconds for large operations
2. **Response Validation**: Checks HTTP status and response structure
3. **Data Extraction**: Extracts data list from API response
4. **Table Creation**: Dynamically creates table if not exists
5. **Data Upsert**: Updates or inserts records using `updateOrInsert()`

**Error Handling**:
```php
if ($response->failed()) {
    $errorMsg = $parameter && $id ? "$parameter=$id" : "No parameter";
    Log::error("API sync failed for $api with $errorMsg: HTTP Status {$response->status()}");
    return response()->json(['status' => 400, 'msg' => 'Failed to synchronize APIs.']);
}
```

**Data Processing**:
```php
$data = $response->json();
if (!isset($data['list']) || !is_array($data['list'])) {
    Log::error("Invalid API response structure for $api");
    return response()->json(['status' => 400, 'msg' => 'Unexpected API response format.']);
}

$columns = array_keys($data['list'][0]);
DB::statement($this->generateCreateTableQuery($tableName, $columns));

foreach ($data['list'] as $item) {
    DB::table($tableName)->updateOrInsert(['id' => $item['id']], $item);
}
```

#### Dynamic Table Creation

##### `generateCreateTableQuery($tableName, $columns): string`

**Purpose**: Generates SQL CREATE TABLE statement for dynamic table creation.

**Parameters**:
- `$tableName`: Name of table to create
- `$columns`: Array of column names from API response

**Generated SQL Structure**:
```sql
CREATE TABLE IF NOT EXISTS `core_employees` (
    `id` VARCHAR(255),
    `name` VARCHAR(255),
    `email` VARCHAR(255),
    `department_id` VARCHAR(255),
    PRIMARY KEY (`id`)
);
```

**Implementation**:
```php
private function generateCreateTableQuery($tableName, $columns)
{
    $createTableQuery = "CREATE TABLE IF NOT EXISTS `$tableName` (";
    
    foreach ($columns as $column) {
        $createTableQuery .= "`$column` VARCHAR(255),";
    }
    
    $createTableQuery .= "PRIMARY KEY (`id`));";
    return $createTableQuery;
}
```

### Database Structure

#### Core API Setup Table
```sql
CREATE TABLE core_api_setup (
    id INT AUTO_INCREMENT PRIMARY KEY,
    api_key VARCHAR(255) NOT NULL,
    base_url VARCHAR(255) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);
```

#### Core APIs Table
```sql
CREATE TABLE core_apis (
    id INT AUTO_INCREMENT PRIMARY KEY,
    api_id INT,
    api_name VARCHAR(255),
    api_end_point VARCHAR(255),
    description TEXT,
    parameters VARCHAR(255),
    table_name VARCHAR(255),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);
```

#### Dynamic Tables (Example)
```sql
-- Dynamically created based on API response
CREATE TABLE core_employees (
    id VARCHAR(255) PRIMARY KEY,
    name VARCHAR(255),
    email VARCHAR(255),
    department_id VARCHAR(255),
    status VARCHAR(255)
);
```

## 🚨 Business Rules

### API Synchronization

1. **Configuration Dependency**: Requires valid API setup in `core_api_setup` table
2. **Authentication**: Uses API key authentication for external requests
3. **Data Replacement**: Truncates and replaces all API configurations during sync
4. **Error Tolerance**: Continues processing other APIs if one fails

### Data Import

1. **Table Naming**: Prefixes all synchronized tables with `core_`
2. **Dynamic Schema**: Creates tables based on API response structure
3. **Upsert Operations**: Updates existing records or inserts new ones
4. **Parameter Handling**: Supports both parameterized and simple API calls

### Data Integrity

1. **Primary Key**: Uses `id` field as primary key for all tables
2. **Data Types**: All columns created as VARCHAR(255) for flexibility
3. **Null Handling**: Handles missing fields gracefully with null coalescing
4. **Transaction Safety**: Each API processed independently

## 🔐 Security Considerations

### API Security

**Authentication**:
- API key-based authentication
- Secure header transmission
- Credential storage in database

**Recommendations**:
```php
// Encrypt API keys in database
protected $casts = [
    'api_key' => 'encrypted'
];

// Add request validation
public function importAPISData(Request $request)
{
    $request->validate([
        'api_end_points' => 'required|array|min:1',
        'api_end_points.*' => 'required|string|max:255'
    ]);
    
    // Continue with existing logic
}
```

### Data Security

**Current Implementation**:
- No input sanitization for dynamic table creation
- Direct SQL statement execution
- No access control on imported data

**Security Improvements**:
```php
// Sanitize table and column names
private function sanitizeIdentifier($identifier)
{
    return preg_replace('/[^a-zA-Z0-9_]/', '', $identifier);
}

// Add column type validation
private function getColumnType($value)
{
    if (is_numeric($value)) {
        return is_int($value) ? 'INT' : 'DECIMAL(10,2)';
    }
    return 'VARCHAR(255)';
}
```

## 🚀 Performance Considerations

### Current Performance Characteristics

**Bottlenecks**:
- Sequential API processing
- No connection pooling
- Large dataset memory usage
- No batch processing

**Time Limits**:
```php
set_time_limit(300); // 5 minutes per API response
```

### Optimization Recommendations

1. **Parallel Processing**:
   ```php
   use Illuminate\Support\Facades\Queue;
   
   public function importAPISData(Request $request)
   {
       $apiEndPoints = $request->input('api_end_points');
       
       foreach ($apiEndPoints as $api) {
           Queue::push(new ImportApiDataJob($api));
       }
       
       return response()->json(['status' => 200, 'msg' => 'Import jobs queued successfully.']);
   }
   ```

2. **Batch Processing**:
   ```php
   // Process data in chunks
   $chunks = array_chunk($data['list'], 1000);
   foreach ($chunks as $chunk) {
       DB::table($tableName)->upsert($chunk, ['id']);
   }
   ```

3. **Connection Optimization**:
   ```php
   // Use HTTP client with connection pooling
   $client = Http::pool(fn (Pool $pool) => [
       $pool->withHeaders(['api-key' => $apiKey])->get("$baseUrl/api/$api1"),
       $pool->withHeaders(['api-key' => $apiKey])->get("$baseUrl/api/$api2"),
   ]);
   ```

4. **Caching Strategy**:
   ```php
   // Cache API configurations
   $apiList = Cache::remember('core_apis', 3600, function () {
       return DB::table('core_apis')->get();
   });
   ```

## 📈 Monitoring and Analytics

### Key Metrics to Track

1. **Synchronization Statistics**:
   - Sync frequency and success rates
   - Data volume per API endpoint
   - Processing time per endpoint

2. **Performance Metrics**:
   - API response times
   - Database operation performance
   - Memory usage during imports

3. **Error Analytics**:
   - Failed API calls by endpoint
   - Data validation errors
   - Network timeout occurrences

### Enhanced Logging

```php
public function sync()
{
    $startTime = microtime(true);
    
    Log::info('API sync started', [
        'user_id' => Auth::id(),
        'timestamp' => now()
    ]);
    
    try {
        // Existing sync logic
        
        Log::info('API sync completed', [
            'duration' => microtime(true) - $startTime,
            'apis_synced' => count($apiRecords),
            'timestamp' => now()
        ]);
        
        return response()->json(['status' => 200, 'msg' => 'API synchronized successfully.']);
        
    } catch (\Exception $e) {
        Log::error('API sync failed', [
            'error' => $e->getMessage(),
            'duration' => microtime(true) - $startTime,
            'timestamp' => now()
        ]);
        
        return response()->json(['status' => 500, 'msg' => 'Synchronization failed.']);
    }
}
```

## 🔄 Future Enhancements

### Suggested Improvements

1. **Enhanced Error Handling**:
   ```php
   public function sync()
   {
       try {
           DB::beginTransaction();
           
           // Existing sync logic
           
           DB::commit();
           return response()->json(['status' => 200, 'msg' => 'API synchronized successfully.']);
           
       } catch (\Exception $e) {
           DB::rollBack();
           Log::error('API sync failed', ['error' => $e->getMessage()]);
           return response()->json(['status' => 500, 'msg' => 'Synchronization failed.']);
       }
   }
   ```

2. **Configuration Management**:
   ```php
   public function updateApiSetup(Request $request)
   {
       $request->validate([
           'api_key' => 'required|string',
           'base_url' => 'required|url'
       ]);
       
       DB::table('core_api_setup')->updateOrInsert(
           ['id' => 1],
           [
               'api_key' => encrypt($request->api_key),
               'base_url' => $request->base_url,
               'updated_at' => now()
           ]
       );
       
       return response()->json(['status' => 200, 'msg' => 'API setup updated successfully.']);
   }
   ```

3. **Selective Synchronization**:
   ```php
   public function syncSpecificApis(Request $request)
   {
       $request->validate([
           'api_ids' => 'required|array',
           'api_ids.*' => 'integer|exists:core_apis,api_id'
       ]);
       
       $apiIds = $request->input('api_ids');
       
       // Sync only specified APIs
       foreach ($apiIds as $apiId) {
           $this->syncSingleApi($apiId);
       }
       
       return response()->json(['status' => 200, 'msg' => 'Selected APIs synchronized successfully.']);
   }
   ```

4. **Data Validation**:
   ```php
   private function validateApiData($data, $apiEndpoint)
   {
       $rules = $this->getValidationRules($apiEndpoint);
       
       foreach ($data['list'] as $item) {
           $validator = Validator::make($item, $rules);
           
           if ($validator->fails()) {
               Log::warning('Data validation failed', [
                   'api' => $apiEndpoint,
                   'item' => $item,
                   'errors' => $validator->errors()
               ]);
           }
       }
   }
   ```

### Feature Additions

1. **API Health Monitoring**:
   - Endpoint availability checking
   - Response time monitoring
   - Automated failure notifications

2. **Data Transformation**:
   - Custom data mapping rules
   - Field transformation pipelines
   - Data enrichment capabilities

3. **Scheduling and Automation**:
   - Automated sync scheduling
   - Incremental data updates
   - Conflict resolution strategies

4. **Management Interface**:
   - API configuration dashboard
   - Sync history and logs
   - Performance analytics
   - Manual trigger controls

## 🔗 Related Components

### Database Tables
- `core_api_setup` - API connection configuration
- `core_apis` - API registry and metadata
- `core_*` - Dynamically created synchronized data tables

### Related Controllers
- `SettingController` - System configuration management
- `UtilityController` - System utility functions
- `ReportController` - Data reporting and analytics

### Integration Points
- External API systems
- Data synchronization services
- Reporting and analytics systems
- Configuration management tools

### Queue Jobs (Recommended)
```php
// Create job for async processing
class ImportApiDataJob implements ShouldQueue
{
    public function handle()
    {
        // Process API import asynchronously
    }
}
```

---

The Admin CoreAPIController provides critical API synchronization and data import functionality, serving as a bridge between external systems and the local expense management database. It enables automated data synchronization, dynamic table management, and centralized API configuration, making it essential for maintaining data consistency across integrated systems.