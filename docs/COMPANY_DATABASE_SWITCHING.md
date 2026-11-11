# Company-Based Database Switching Implementation

## Overview
This implementation allows the application to dynamically switch between different database connections based on the selected company during login.

## Companies and Database Mapping

### Company 1: VNR Seeds Pvt Ltd
- **Expense Database**: `expense` connection (uses `DB_DATABASE` from .env)
- **HRIMS Database**: `hrims` connection (uses `SECOND_DB_DATABASE` from .env)

### Company 2: VVNR
- **Expense Database**: `expense_vvnr` connection (uses `DB_VVNR_DATABASE` from .env)
- **HRIMS Database**: `hrims_vvnr` connection (uses `SECOND_DB_VVNR_DATABASE` from .env)

## Implementation Components

### 1. Database Configuration (`config/database.php`)
Added new database connections:
- `expense_vvnr`: For VVNR expense data
- `hrims_vvnr`: For VVNR HRIMS data

### 2. DatabaseSwitcher Helper (`app/Helpers/DatabaseSwitcher.php`)
Core helper class that manages database connections:
- `setCompanyConnections($companyId)`: Sets appropriate connections based on company
- `getExpenseConnection($companyId)`: Returns expense connection name
- `getHrimsConnection($companyId)`: Returns HRIMS connection name
- `getCompanyName($companyId)`: Returns company display name
- `initializeSessionConnections()`: Initializes connections from session

### 3. CompanyDatabaseConnection Trait (`app/Traits/CompanyDatabaseConnection.php`)
Trait for models to automatically use correct database connections:
- Dynamically sets connection based on session company
- Overrides `newQuery()` to ensure proper connection
- Provides `setDynamicConnection()` method

### 4. SetDatabaseConnection Middleware (`app/Http/Middleware/SetDatabaseConnection.php`)
Middleware that ensures correct database connections are set for each request:
- Runs on every web request
- Initializes connections based on session company

### 5. Updated Models
The following models have been updated to use the CompanyDatabaseConnection trait:
- `User`: Uses expense connection
- `FinancialYear`: Uses expense connection
- `ExpenseClaim`: Uses expense connection
- `HRMEmployees`: Uses HRIMS connection

### 6. Updated AuthController (`app/Http/Controllers/AuthController.php`)
- Added DatabaseSwitcher import
- Updated validation to allow company values 1 and 2
- Sets database connections during login process
- Uses appropriate HRIMS connection for employee authentication

### 7. Updated Login Form (`resources/views/login.blade.php`)
- Fixed company dropdown to properly handle both VNR and VVNR options
- Corrected `old('company')` validation for option 2

## Environment Variables Required

Ensure your `.env` file contains all necessary database configuration:

```env
# VNR Seeds Pvt Ltd
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=2025
DB_DATABASE=expense
DB_USERNAME=root
DB_PASSWORD=root

SECOND_DB_CONNECTION=mysql
SECOND_DB_HOST=127.0.0.1
SECOND_DB_PORT=2025
SECOND_DB_DATABASE=hrims
SECOND_DB_USERNAME=root
SECOND_DB_PASSWORD=root

# VVNR
DB_VVNR_CONNECTION=mysql
DB_VVNR_HOST=127.0.0.1
DB_VVNR_PORT=2025
DB_VVNR_DATABASE=vnressus_expense
DB_VVNR_USERNAME=root
DB_VVNR_PASSWORD=root

SECOND_DB_VVNR_CONNECTION=mysql
SECOND_DB_VVNR_HOST=127.0.0.1
SECOND_DB_VVNR_PORT=2025
SECOND_DB_VVNR_DATABASE=vnressus_hrims
SECOND_DB_VVNR_USERNAME=root
SECOND_DB_VVNR_PASSWORD=root
```

## Testing

Test routes have been added for development:
- `/test/database-connections`: Tests raw database connections for both companies
- `/test/model-connections`: Tests model connections with current session

## Usage

### For New Models
To make a new model company-aware:

1. Add the trait:
```php
use App\Traits\CompanyDatabaseConnection;

class YourModel extends Model
{
    use CompanyDatabaseConnection;
    
     // or 'hrims'
    protected $connectionType = 'expense'; // or 'hrims'
}
```

### For Controllers
Use the DatabaseSwitcher helper:
```php
use App\Helpers\DatabaseSwitcher;

// Get current connections
$expenseConn = DatabaseSwitcher::getExpenseConnection();
$hrimsConn = DatabaseSwitcher::getHrimsConnection();

// Use specific connection
$data = DB::connection($expenseConn)->table('some_table')->get();
```

### For Raw Queries
```php
use App\Helpers\CommonHelper;

// Use helper methods
$expenseConn = CommonHelper::getExpenseConnection();
$hrimsConn = CommonHelper::getHrimsConnection();
```

## Session Data
The following session variables are set during login:
- `company_id`: 1 for VNR, 2 for VVNR
- `authenticated`: true when logged in
- Other existing session variables remain unchanged

## Middleware Registration
The `SetDatabaseConnection` middleware is automatically applied to all web routes via `bootstrap/app.php`.

## Important Notes
1. Database connections are purged and recreated when switching companies to ensure clean connections
2. All models using the trait will automatically use the correct database based on session
3. The middleware ensures connections are properly set on every request
4. Token-based authentication defaults to company 1 (VNR Seeds Pvt Ltd)