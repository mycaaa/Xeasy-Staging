# 🔄 DatabaseSwitchController Documentation

The DatabaseSwitchController manages dynamic database connections and context switching for multi-company and multi-year operations in the Xeasy Laravel expense management system.

## 📋 Overview

**Location**: `app/Http/Controllers/DatabaseSwitchController.php`

**Purpose**: Handles dynamic database connection management, company switching, and financial year context management for multi-tenant operations.

**Key Features**:
- Multi-company database connection management
- Dynamic financial year switching
- Session-based context management
- Safe database connection handling
- Configuration-driven company setup

## 📊 Public Methods

### 1. Company List Retrieval

#### `getCompanies()`

**Route**: `GET /api/companies`
**Middleware**: `auth`

**Purpose**: Retrieves list of available companies from configuration.

**Parameters**: None

**Response**:
```json
[
    {
        "id": 1,
        "name": "Company A"
    },
    {
        "id": 2,
        "name": "Company B"
    }
]
```

**Business Logic**:
1. Reads company configuration from `config/company_databases.php`
2. Maps company IDs to names
3. Returns formatted array for frontend consumption

**Example Usage**:
```javascript
fetch('/api/companies')
    .then(response => response.json())
    .then(companies => {
        // Populate company dropdown
        companies.forEach(company => {
            console.log(`${company.id}: ${company.name}`);
        });
    });
```

---

### 2. Financial Years Retrieval

#### `getFinancialYears(Request $request)`

**Route**: `GET /api/financial-years`
**Middleware**: `auth`

**Purpose**: Retrieves available financial years for a specific company by dynamically connecting to the company's database.

**Request Parameters**:
| Parameter | Type | Required | Description |
|-----------|------|----------|-------------|
| company | integer | Yes | Company ID (query parameter) |

**Response**:
```json
[
    {
        "id": 7,
        "name": "2024-25"
    },
    {
        "id": 6,
        "name": "2023-24"
    }
]
```

**Error Responses**:
```json
// Missing company ID
{
    "message": "Missing company ID"
}

// Invalid company
{
    "message": "Invalid company ID or missing hrims configuration"
}

// No financial years found
{
    "message": "No financial years found for company ID 1"
}

// Database error
{
    "message": "Error fetching financial years: Connection failed"
}
```

**Business Logic**:
1. **Validation**: Checks for required company parameter
2. **Configuration Lookup**: Retrieves company database configuration
3. **Dynamic Connection**: Creates temporary database connection
4. **Data Retrieval**: Queries FinancialYear model from company database
5. **Connection Cleanup**: Purges temporary connection and restores default
6. **Error Handling**: Comprehensive error handling with connection cleanup

**Database Connection Flow**:
```php
// 1. Create dynamic connection
$connectionName = 'company_' . $company;
config([
    "database.connections.$connectionName" => [
        'driver' => 'mysql',
        'host' => $expense['host'],
        'port' => $expense['port'],
        'database' => $expense['database'],
        'username' => $expense['username'],
        'password' => $expense['password'],
        // ... other config
    ],
]);

// 2. Switch to new connection
DB::purge($connectionName);
DB::setDefaultConnection($connectionName);

// 3. Query data
$years = FinancialYear::select('YearId as id', 'Year as name')->get();

// 4. Cleanup and restore
DB::purge($connectionName);
DB::setDefaultConnection('mysql');
```

---

### 3. Database Context Switching

#### `switchDatabase(Request $request)`

**Route**: `POST /api/switch-database`
**Middleware**: `auth`

**Purpose**: Switches the user's session context to a specific company and financial year.

**Request Parameters**:
| Parameter | Type | Required | Description |
|-----------|------|----------|-------------|
| company_id | integer | Yes | Company ID to switch to |
| year_id | integer | Yes | Financial year ID to switch to |

**Request Example**:
```json
{
    "company_id": 1,
    "year_id": 7
}
```

**Response**:
```json
{
    "success": true,
    "message": "Database switched successfully"
}
```

**Error Response**:
```json
{
    "success": false,
    "message": "Error message details"
}
```

**Business Logic**:
1. **Input Validation**: Validates required company_id and year_id
2. **Company Name Lookup**: Retrieves company name from configuration
3. **Year Value Lookup**: Gets year display value using FinancialYear model
4. **Session Update**: Updates session with new context
5. **Response**: Returns success/failure status

**Session Variables Set**:
```php
Session::put('company_id', $companyId);
Session::put('company_name', $company_name);
Session::put('year_id', $yearId);
Session::put('year_value', $year_value);
```

---

## 🔧 Configuration Management

### Company Database Configuration

The controller relies on `config/company_databases.php` configuration:

```php
// config/company_databases.php
return [
    'companies' => [
        1 => [
            'name' => 'Company A',
            'hrims' => [
                'host' => 'localhost',
                'port' => '3306',
                'database' => 'company_a_hrims',
                'username' => 'username',
                'password' => 'password',
            ],
            'expense' => [
                'host' => 'localhost',
                'port' => '3306',
                'database' => 'company_a_expense',
                'username' => 'username',
                'password' => 'password',
            ],
        ],
        2 => [
            'name' => 'Company B',
            // ... similar structure
        ],
    ],
];
```

### Dynamic Connection Configuration

**Connection Parameters**:
- **Driver**: MySQL
- **Charset**: utf8mb4
- **Collation**: utf8mb4_unicode_ci
- **Strict Mode**: Enabled
- **Connection Pooling**: Managed by Laravel

---

## 🔒 Security Features

### Input Validation

```php
$request->validate([
    'company_id' => 'required|integer',
    'year_id' => 'required|integer'
]);
```

**Validation Rules**:
- Company ID must be integer and required
- Year ID must be integer and required
- Additional business logic validation in methods

### Configuration Security

**Security Measures**:
- Database credentials stored in secure configuration
- No direct SQL injection possibilities
- Connection validation before use
- Proper connection cleanup

### Session Security

**Session Management**:
- Secure session variable updates
- Context isolation per user
- No cross-user context bleeding

---

## 🚨 Error Handling

### Connection Error Handling

```php
try {
    // Database operations
} catch (\Exception $e) {
    // Cleanup connections
    DB::purge($connectionName);
    DB::setDefaultConnection('mysql');
    
    return response()->json([
        'message' => 'Error fetching financial years: ' . $e->getMessage()
    ], 500);
}
```

**Error Scenarios**:
- Invalid company configuration
- Database connection failures
- Missing financial year data
- Configuration parsing errors

### Graceful Degradation

**Cleanup Strategy**:
- Always purge temporary connections
- Restore default connection on errors
- Provide meaningful error messages
- Log errors for debugging

---

## 🎯 Business Logic

### Multi-Tenant Architecture

**Company Isolation**:
- Each company has separate databases
- Dynamic connection switching
- Session-based context management
- Configuration-driven setup

### Financial Year Management

**Year Context**:
- Company-specific financial years
- Dynamic year retrieval
- Session-based year tracking
- Year display value management

### Connection Management

**Connection Lifecycle**:
1. **Create**: Dynamic connection configuration
2. **Switch**: Change default connection
3. **Use**: Execute database operations
4. **Cleanup**: Purge and restore connections

---

## 📈 Performance Considerations

### Connection Pooling

**Optimization Strategies**:
- Temporary connection creation
- Immediate connection cleanup
- Minimal connection lifetime
- Efficient connection reuse

### Memory Management

**Resource Management**:
- Connection purging after use
- Minimal session data storage
- Efficient configuration caching

### Query Optimization

**Database Efficiency**:
- Selective column retrieval
- Minimal query execution
- Indexed lookups where possible

---

## 🔗 Integration Points

### Session Management

**Session Integration**:
- User context management
- Company/year state tracking
- Authentication integration

### Configuration System

**Config Integration**:
- Dynamic configuration loading
- Multi-environment support
- Secure credential management

### Model Integration

**Model Usage**:
- FinancialYear model integration
- Dynamic model connection switching
- Cross-database model operations

---

## 📊 Usage Examples

### Frontend Company Selection

```javascript
// Load companies on page load
async function loadCompanies() {
    try {
        const response = await fetch('/api/companies');
        const companies = await response.json();
        
        const companySelect = document.getElementById('company-select');
        companies.forEach(company => {
            const option = document.createElement('option');
            option.value = company.id;
            option.textContent = company.name;
            companySelect.appendChild(option);
        });
    } catch (error) {
        console.error('Failed to load companies:', error);
    }
}

// Load financial years when company changes
async function loadFinancialYears(companyId) {
    try {
        const response = await fetch(`/api/financial-years?company=${companyId}`);
        const years = await response.json();
        
        const yearSelect = document.getElementById('year-select');
        yearSelect.innerHTML = '<option value="">Select Year</option>';
        
        years.forEach(year => {
            const option = document.createElement('option');
            option.value = year.id;
            option.textContent = year.name;
            yearSelect.appendChild(option);
        });
    } catch (error) {
        console.error('Failed to load financial years:', error);
    }
}

// Switch database context
async function switchDatabase(companyId, yearId) {
    try {
        const response = await fetch('/api/switch-database', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
            },
            body: JSON.stringify({
                company_id: companyId,
                year_id: yearId
            })
        });
        
        const result = await response.json();
        
        if (result.success) {
            // Reload page or update UI
            window.location.reload();
        } else {
            alert('Failed to switch database: ' + result.message);
        }
    } catch (error) {
        console.error('Database switch failed:', error);
    }
}
```

### Backend Usage

```php
// Check current context in other controllers
class SomeController extends Controller
{
    public function index()
    {
        $companyId = session('company_id');
        $yearId = session('year_id');
        $companyName = session('company_name');
        $yearValue = session('year_value');
        
        // Use context for business logic
        if (!$companyId || !$yearId) {
            return redirect()->route('database-switch')
                ->with('error', 'Please select company and financial year');
        }
        
        // Continue with business logic
    }
}
```

---

## 🔧 Configuration Examples

### Company Database Configuration

```php
// config/company_databases.php
return [
    'companies' => [
        1 => [
            'name' => 'Acme Corporation',
            'hrims' => [
                'host' => env('COMPANY_1_HRIMS_HOST', 'localhost'),
                'port' => env('COMPANY_1_HRIMS_PORT', '3306'),
                'database' => env('COMPANY_1_HRIMS_DB', 'acme_hrims'),
                'username' => env('COMPANY_1_HRIMS_USER', 'root'),
                'password' => env('COMPANY_1_HRIMS_PASS', ''),
            ],
            'expense' => [
                'host' => env('COMPANY_1_EXPENSE_HOST', 'localhost'),
                'port' => env('COMPANY_1_EXPENSE_PORT', '3306'),
                'database' => env('COMPANY_1_EXPENSE_DB', 'acme_expense'),
                'username' => env('COMPANY_1_EXPENSE_USER', 'root'),
                'password' => env('COMPANY_1_EXPENSE_PASS', ''),
            ],
        ],
        // Additional companies...
    ],
];
```

### Environment Variables

```env
# Company 1 - Acme Corporation
COMPANY_1_HRIMS_HOST=localhost
COMPANY_1_HRIMS_PORT=3306
COMPANY_1_HRIMS_DB=acme_hrims
COMPANY_1_HRIMS_USER=acme_user
COMPANY_1_HRIMS_PASS=secure_password

COMPANY_1_EXPENSE_HOST=localhost
COMPANY_1_EXPENSE_PORT=3306
COMPANY_1_EXPENSE_DB=acme_expense
COMPANY_1_EXPENSE_USER=acme_user
COMPANY_1_EXPENSE_PASS=secure_password
```

---

The DatabaseSwitchController provides essential multi-tenant functionality, enabling seamless switching between different company databases and financial year contexts while maintaining security and performance standards.