# 🏠 HomeController Documentation

The HomeController is the central controller for dashboard functionality, analytics, and reporting in the Xeasy Laravel expense management system.

## 📋 Overview

**Location**: `app/Http/Controllers/HomeController.php`

**Purpose**: Handles dashboard data retrieval, analytics processing, report generation, and data export functionality.

**Middleware**: `auth` - All methods require authentication

**Key Features**:
- Dashboard statistics and visualizations
- Multi-year expense analytics
- Department-wise comparisons
- Employee trend analysis
- Report generation and export
- Real-time data processing

## 🔧 Constructor & Middleware

```php
public function __construct()
{
    $this->middleware('auth');
}
```

**Middleware Applied**:
- `auth` - Ensures all methods require user authentication

## 📊 Public Methods

### 1. Dashboard Home Page

#### `index(Request $request)`

**Route**: `GET /home`
**Permission**: `section_dashboard`

**Purpose**: Renders the main dashboard view.

**Parameters**: None

**Returns**: Blade view (`home`)

**Example Usage**:
```php
// Accessed via route
GET /home
```

**Response**: Returns the dashboard HTML view with embedded JavaScript for data loading.

---

### 2. Dashboard Data API

#### `getDashboardData(Request $request)`

**Route**: `GET /home-data`
**Permission**: `section_dashboard`

**Purpose**: Provides comprehensive dashboard statistics and analytics data in JSON format.

**Request Parameters**:
| Parameter | Type | Required | Description |
|-----------|------|----------|-------------|
| bill_date_from | date (Y-m-d) | No | Start date for filtering |
| bill_date_to | date (Y-m-d) | No | End date for filtering |

**Response Structure**:
```json
{
    "cardData": {
        "Draft": 15,
        "Submitted": 25,
        "Filled": 120,
        "Verified": 95,
        "Approved": 80,
        "Financed": 75,
        "Total Expense": 410
    },
    "claimTypeTotals": [
        {
            "ClaimName": "Daily Allowance",
            "ClaimCode": "DA",
            "FilledTotal": 50000,
            "VerifiedTotal": 45000,
            "ApprovedTotal": 40000,
            "FinancedTotal": 38000
        }
    ],
    "monthlyTotals": [
        {
            "month": "January",
            "FilledTotal": 100000,
            "VerifiedTotal": 95000,
            "ApprovedTotal": 90000,
            "FinancedTotal": 85000
        }
    ],
    "departmentTotals": [
        {
            "department_name": "Sales",
            "TotalFinancedTAmt_Y2024": 500000,
            "TotalFinancedTAmt_Y2023": 450000,
            "VariationPercentage": 11.11
        }
    ],
    "top10TravelersSplitByWType": [
        {
            "employee_name": "John Doe",
            "employee_code": "EMP001",
            "total_amount": 25000,
            "with_driver": 15000,
            "without_driver": 10000
        }
    ],
    "yearlyComparison": {
        "current_year": 2024,
        "previous_year": 2023,
        "current_total": 1000000,
        "previous_total": 900000,
        "variation_percentage": 11.11
    },
    "topEmployees": [
        {
            "employee_name": "Jane Smith",
            "employee_code": "EMP002",
            "total_financed": 30000,
            "claim_count": 15
        }
    ],
    "yearId": 2024
}
```

**Error Response**:
```json
{
    "error": "Server error occurred"
}
```

**Business Logic**:
1. Validates date parameters and session data
2. Prepares database table names based on financial year
3. Fetches multiple analytics datasets in parallel
4. Formats data for frontend consumption
5. Handles empty datasets with default values
6. Comprehensive error logging

---

### 3. Analytics Pages

#### `analytics(Request $request)`

**Route**: `GET /analytics/{page}`
**Permission**: `section_dashboard`

**Purpose**: Renders specific analytics pages with department data.

**Parameters**:
| Parameter | Type | Required | Description |
|-----------|------|----------|-------------|
| page | string | Yes | Analytics page name (route parameter) |

**Returns**: Blade view (`reports.{page}`) with departments data

**Example Usage**:
```php
GET /analytics/department-comparison
GET /analytics/employee-trends
```

**Business Logic**:
1. Extracts page name from route parameter
2. Loads active departments for filtering
3. Validates view existence
4. Returns 404 if view doesn't exist

---

### 4. Sub-Department Analytics

#### `getSubDepartments(Request $request)`

**Route**: `POST /get-sub-departments`
**Permission**: `section_dashboard`

**Purpose**: Retrieves sub-department expense analytics for a specific department.

**Request Parameters**:
| Parameter | Type | Required | Description |
|-----------|------|----------|-------------|
| bill_date_from | date (Y-m-d) | Yes | Start date |
| bill_date_to | date (Y-m-d) | Yes | End date (must be >= start date) |
| department_id | integer | No | Department ID for filtering |

**Response**:
```json
{
    "success": true,
    "message": "Sub Department expense analytics loaded successfully.",
    "data": [
        {
            "sub_department_name": "Sales North",
            "TotalFinancedTAmt_Y2024": 250000,
            "TotalFinancedTAmt_Y2023": 200000,
            "VariationPercentage": 25.0
        }
    ]
}
```

---

### 5. Advanced Analytics Dashboard

#### `analyticsDashboardData(Request $request)`

**Route**: `GET /analytics-dashboard-data`
**Permission**: `section_dashboard`

**Purpose**: Provides comprehensive analytics data for advanced dashboard views.

**Request Parameters**:
| Parameter | Type | Required | Description |
|-----------|------|----------|-------------|
| bill_date_from | date (Y-m-d) | Yes | Start date |
| bill_date_to | date (Y-m-d) | Yes | End date |
| filter_type | string | No | Filter type: all, increased, decreased, critical |
| sort_by | string | No | Sort by: variation, current, previous |
| department | integer | No | Department ID for filtering |

**Response Structure**:
```json
{
    "success": true,
    "message": "Department expense analytics loaded successfully.",
    "data": {
        "departments": [
            {
                "department": "Sales",
                "department_id": 1,
                "code": "SAL",
                "previousYear": 450000,
                "currentYear": 500000,
                "variation": 11.11
            }
        ],
        "topEmployees": [...],
        "topEmployeesSameDay": [...],
        "topEmployeesRevert": [...],
        "departmentMonthlyTotals": [...],
        "departmentTotalsClaimTypeWise": {
            "departments": [...],
            "claimTypeTotals": [...],
            "grandTotals": [...]
        }
    }
}
```

---

### 6. Multi-Report Export

#### `exportReports(Request $request)`

**Route**: `POST /export-reports`
**Permission**: `section_dashboard`

**Purpose**: Generates and downloads multiple reports in a ZIP file.

**Request Parameters**:
| Parameter | Type | Required | Description |
|-----------|------|----------|-------------|
| bill_date_from | date (Y-m-d) | Yes | Start date |
| bill_date_to | date (Y-m-d) | Yes | End date |
| reports | array | Yes | Array of report types to export |
| reports.* | string | Yes | Report type: department_expense_comparison, expense_month_wise, expense_department_wise, expense_claim_type_wise |
| department | integer | No | Department ID for filtering |

**Valid Report Types**:
- `department_expense_comparison` - Department comparison report
- `expense_month_wise` - Monthly expense breakdown
- `expense_department_wise` - Department-wise expenses
- `expense_claim_type_wise` - Claim type analysis

**Response**: ZIP file download containing requested Excel reports

**Example Request**:
```json
{
    "bill_date_from": "2024-01-01",
    "bill_date_to": "2024-12-31",
    "reports": [
        "expense_month_wise",
        "expense_department_wise"
    ],
    "department": 1
}
```

**Business Logic**:
1. Validates request parameters
2. Creates temporary directory for file generation
3. Generates each requested report as Excel file
4. Packages all files into ZIP archive
5. Returns ZIP file for download
6. Cleans up temporary files
7. Comprehensive error handling and logging

---

### 7. Individual Report Exports

#### `exportExpenseMonthWise(Request $request)`

**Route**: `POST /export/expense-month-wise`
**Permission**: `section_dashboard`

**Purpose**: Exports monthly expense report as Excel file.

**Parameters**:
| Parameter | Type | Required | Description |
|-----------|------|----------|-------------|
| bill_date_from | date (Y-m-d) | Yes | Start date |
| bill_date_to | date (Y-m-d) | Yes | End date |

**Response**: Excel file download (`Expense_Month_Wise_{start_date}_to_{end_date}.xlsx`)

---

#### `exportExpenseDepartmentWise(Request $request)`

**Route**: `POST /export/expense-department-wise`
**Permission**: `section_dashboard`

**Purpose**: Exports department-wise expense report with year-over-year comparison.

**Response**: Excel file download (`Expense_Department_Wise_{start_date}_to_{end_date}.xlsx`)

---

#### `exportExpenseClaimTypeWise(Request $request)`

**Route**: `POST /export/expense-claim-type-wise`
**Permission**: `section_dashboard`

**Purpose**: Exports claim type analysis report.

**Response**: Excel file download (`Claim_Type_Wise_Report_{start_date}_to_{end_date}.xlsx`)

---

### 8. Employee Trend Analysis

#### `getEmployeeTrend(Request $request)`

**Route**: `POST /get-employee-trend`
**Permission**: `section_dashboard`

**Purpose**: Retrieves monthly expense trends for a specific employee.

**Request Parameters**:
| Parameter | Type | Required | Description |
|-----------|------|----------|-------------|
| bill_date_from | date (Y-m-d) | Yes | Start date |
| bill_date_to | date (Y-m-d) | Yes | End date |
| employee_id | integer | Yes | Employee ID |

**Response**:
```json
{
    "success": true,
    "data": [
        {
            "ClaimName": "Daily Allowance",
            "Jan": 5000,
            "Feb": 4500,
            "Mar": 6000,
            "total_year": 55000
        },
        {
            "ClaimName": "Vehicle Expense",
            "Apr": 3000,
            "May": 3500,
            "Jun": 4000,
            "total_year": 35000
        }
    ]
}
```

**Business Logic**:
1. Validates employee ID and date range
2. Queries expense data grouped by claim type and month
3. Filters out months with zero expenses
4. Orders results by total yearly amount
5. Returns only claim types with positive totals

---

## 🔧 Private Helper Methods

### Data Preparation

#### `prepareDateAndTableParams(Request $request): array`

**Purpose**: Prepares standardized parameters for database queries.

**Returns**:
```php
[
    'startDate' => '2024-01-01',
    'endDate' => '2024-12-31',
    'yearId' => 2024,
    'previousYearId' => 2023,
    'table' => 'y2024_expenseclaims',
    'previousYearTable' => 'y2023_expenseclaims',
    'previousYearStartDate' => '2023-01-01',
    'previousYearEndDate' => '2023-12-31'
]
```

**Business Logic**:
1. Parses and validates date inputs
2. Determines financial year from session or date
3. Generates dynamic table names based on year
4. Checks table existence for previous year
5. Calculates corresponding previous year dates

---

### Data Formatting

#### `formatCardData(Collection $claimStatusCounts): array`

**Purpose**: Formats claim status counts for dashboard cards.

**Input**: Collection of claim status counts from database
**Output**: Associative array with human-readable status names

---

#### `formatDepartmentClaimTypeTotals(Collection $data, int $yearId, int $previousYearId): array`

**Purpose**: Formats complex department and claim type analytics data.

**Returns**: Structured array with departments, claim totals, and grand totals

---

### Default Data Handling

#### `defaultDashboardData(int $yearId): array`

**Purpose**: Provides default dashboard data structure when no data is available.

**Returns**: Array with empty/default values for all dashboard components

---

## 📊 Database Queries

The HomeController uses several complex database queries for analytics:

### Multi-Year Comparisons
- Joins current and previous year expense tables
- Calculates percentage variations
- Handles missing previous year data gracefully

### Aggregation Queries
- Monthly totals by claim status
- Department-wise expense summaries
- Employee ranking and trends
- Claim type analysis

### Dynamic Table Names
- Uses `ExpenseClaim::tableName($yearId)` for year-based tables
- Validates table existence before querying
- Falls back to current year table if previous year unavailable

## 🚨 Error Handling

### Comprehensive Logging
```php
Log::error('Error in getDashboardData', [
    'bill_date_from' => $request->input('bill_date_from') ?? '-',
    'bill_date_to' => $request->input('bill_date_to') ?? '-',
    'year_id' => session('year_id') ?? '-',
    'error' => $e->getMessage(),
    'trace' => $e->getTraceAsString(),
]);
```

### Graceful Degradation
- Returns default data when queries fail
- Handles empty result sets appropriately
- Provides meaningful error messages to frontend

### Validation
- Strict date format validation
- Parameter type checking
- Business rule validation (end date >= start date)

## 🔐 Security Considerations

### Authentication
- All methods require authentication via middleware
- Session-based user context validation

### Permission Checks
- Routes protected by `permission:section_dashboard`
- User access validated before data retrieval

### Input Sanitization
- Laravel validation rules applied
- SQL injection prevention through Eloquent ORM
- XSS protection through proper output encoding

## 📈 Performance Optimizations

### Query Optimization
- Efficient database queries with proper indexing
- Selective column retrieval
- Aggregation at database level

### Caching Considerations
- Results suitable for caching (implement as needed)
- Session-based data context
- Temporary file cleanup for exports

### Memory Management
- Streaming for large exports
- Temporary file cleanup
- Efficient collection processing

## 🔗 Related Components

### Models Used
- `ExpenseClaim` - Main expense data
- `CoreDepartments` - Department information

### Export Classes
- `DepartmentExpenseComparisonExport`
- `ExpenseMonthWiseReportExport`
- `ExpenseDepartmentWiseReportExport`
- `ExpenseClaimTypeWiseReportExport`
- `EmployeeRunningKmExport`
- `ClaimExceptionExport`

### Frontend Integration
- AJAX endpoints for real-time data loading
- JSON responses for chart libraries (ApexCharts)
- File download handling for reports

---

### 9. Employee Running KM Report

#### `getEmployeeRunningKmAsPerPolacyData()`

**Route**: Not directly accessible (internal method)
**Permission**: `section_dashboard`

**Purpose**: Retrieves employee running kilometer data based on policy settings.

**Returns**: Collection of employee data with running KM calculations

**Business Logic**:
1. Fetches employee data from HRIMS
2. Calculates running KM based on policy
3. Integrates with external API for rate information
4. Formats data for export

---

#### `exportEmployeeRunningKmReport()`

**Route**: `GET /export/employee-running-km`
**Permission**: `section_dashboard`

**Purpose**: Exports employee running KM report as Excel file.

**Response**: Excel file download (`Employee_Running_KM_Report.xlsx`)

---

### 10. Claim Exception Reports

#### `claimExceptionReport(Request $request)`

**Route**: `GET /reports/claim-exception`
**Permission**: `section_dashboard`

**Purpose**: Displays claim exception report for a specific month.

**Request Parameters**:
| Parameter | Type | Required | Description |
|-----------|------|----------|-------------|
| month | integer | Yes | Month number (1-12) |

**Returns**: Blade view (`reports.claim_exception_report`) with exception data

---

#### `exportClaimExceptionReport(Request $request)`

**Route**: `POST /export/claim-exception`
**Permission**: `section_dashboard`

**Purpose**: Exports claim exception report as Excel file.

**Request Parameters**:
| Parameter | Type | Required | Description |
|-----------|------|----------|-------------|
| month | integer | Yes | Month number (1-12) |

**Response**: Excel file download (`Claim_Exception_Report_Month_{month}_{timestamp}.xlsx`)

---

#### `getClaimExceptionDetail(Request $request)`

**Route**: `POST /get-claim-exception-detail`
**Permission**: `section_dashboard`

**Purpose**: Retrieves detailed claim exception data for a specific employee and month.

**Request Parameters**:
| Parameter | Type | Required | Description |
|-----------|------|----------|-------------|
| employee_id | integer | Yes | Employee ID |
| month | integer | Yes | Month number (1-12) |

**Response**:
```json
{
    "data": [
        {
            "ExpId": 12345,
            "ClaimId": 7,
            "ClaimMonth": 3,
            "ClaimYearId": 2024,
            "FilledTAmt": 5000.00,
            "FilledOkay": 1,
            "ClaimStatus": "Financed",
            "App_Odometer": "Y",
            "Backdate_Odometer_Status": "A",
            "employee_name": "John Doe Smith",
            "department_name": "Sales"
        }
    ]
}
```

---

## 🔧 Private Data Retrieval Methods

### Core Analytics Methods

#### `getClaimStatusCounts($tableName, $startDate, $endDate)`

**Purpose**: Retrieves count of claims grouped by status.

**Parameters**:
- `$tableName`: Expense claims table name
- `$startDate`: Start date for filtering
- `$endDate`: End date for filtering

**Returns**: Collection with claim status counts

**Query Logic**:
```sql
SELECT ClaimStatus, COUNT(*) as TotalCount
FROM {tableName}
WHERE BillDate BETWEEN ? AND ?
  AND BillDate IS NOT NULL
  AND BillDate != '0000-00-00'
GROUP BY ClaimStatus
```

---

#### `getClaimTypeTotals($tableName, $startDate, $endDate)`

**Purpose**: Retrieves financial totals grouped by claim type.

**Returns**: Collection with claim type financial summaries

**Query Features**:
- Joins with `claimtype` table for claim names
- Aggregates amounts by claim status (Filled, Verified, Approved, Financed)
- Excludes Draft, Submitted, and Deactivated claims

---

#### `getMonthlyStatusTotals($tableName, $startDate, $endDate)`

**Purpose**: Retrieves monthly totals grouped by claim status.

**Returns**: Collection with monthly financial data by status

**Query Logic**:
- Groups by claim month and status
- Sums financial amounts for each status
- Formats month names for display

---

#### `getRawMonthlyTotals($tableName, $startDate, $endDate)`

**Purpose**: Retrieves raw monthly totals across all claim statuses.

**Returns**: Collection with monthly aggregated data

**Data Structure**:
```php
[
    'month' => 'January',
    'FilledTotal' => 100000,
    'VerifiedTotal' => 95000,
    'ApprovedTotal' => 90000,
    'FinancedTotal' => 85000
]
```

---

#### `getDepartmentTotals($tableName, $previousYearTable, $yearId, $previousYearId, $startDate, $endDate, $previousYearStartDate, $previousYearEndDate, $departmentId = null)`

**Purpose**: Retrieves department-wise expense totals with year-over-year comparison.

**Parameters**:
- `$departmentId`: Optional department filter

**Returns**: Collection with department comparison data

**Business Logic**:
1. Joins current and previous year tables
2. Calculates percentage variation
3. Handles missing previous year data
4. Filters by department if specified

---

#### `getSubDepartmentTotals(...)`

**Purpose**: Similar to `getDepartmentTotals` but for sub-departments.

**Additional Features**:
- Filters by parent department
- Groups by sub-department
- Maintains same comparison logic

---

#### `getYearlyComparison($tableName, $previousYearTable, $yearId, $previousYearId, $startDate, $endDate, $previousYearStartDate, $previousYearEndDate)`

**Purpose**: Calculates overall yearly comparison metrics.

**Returns**: Object with yearly comparison data

**Data Structure**:
```php
{
    'current_year': 2024,
    'previous_year': 2023,
    'current_total': 1000000,
    'previous_total': 900000,
    'variation_percentage': 11.11
}
```

---

#### `getTopEmployees($tableName, $startDate, $endDate, $limit = 10, $sameDay = null, $departmentId = null)`

**Purpose**: Retrieves top employees by expense amount.

**Parameters**:
- `$limit`: Number of employees to return (default: 10)
- `$sameDay`: Filter for same-day claims (true/false/null)
- `$departmentId`: Optional department filter

**Returns**: Collection of top employees with expense totals

**Query Features**:
- Joins with HRIMS employee tables
- Filters by same-day claims if specified
- Orders by total financed amount
- Includes employee details and claim counts

---

#### `getDepartmentMonthlyTotals($tableName, $startDate, $endDate, $departmentId = null)`

**Purpose**: Retrieves monthly totals grouped by department.

**Returns**: Collection with department monthly data

**Query Logic**:
- Groups by department and month
- Sums financed amounts
- Joins with department information
- Optional department filtering

---

#### `getDepartmentTotalsClaimTypeWise(...)`

**Purpose**: Retrieves department totals broken down by claim type.

**Returns**: Collection with detailed department and claim type analysis

**Complex Query Features**:
- Multi-table joins (expense claims, claim types, departments)
- Year-over-year comparison by claim type
- Department-wise grouping
- Variation percentage calculations

---

### Specialized Report Methods

#### `getClaimExceptionReport($month)`

**Purpose**: Identifies discrepancies between final and detail claim data.

**Parameters**:
- `$month`: Month number for analysis

**Returns**: Collection of exception records

**Business Logic**:
1. Compares final expense table with detail claims
2. Identifies amount and count discrepancies
3. Filters by department-specific rules
4. Handles odometer approval status
5. Returns only records with differences

**Exception Detection**:
- Amount mismatches between final and detail tables
- Count discrepancies in claim numbers
- Status inconsistencies

---

## 🎯 Advanced Query Patterns

### Multi-Year Comparisons

The controller implements sophisticated multi-year comparison logic:

```php
// Dynamic table name generation
$currentYearTable = "y{$yearId}_expenseclaims";
$previousYearTable = "y{$previousYearId}_expenseclaims";

// Fallback to current year if previous year table doesn't exist
if (!Schema::hasTable($previousYearTable)) {
    $previousYearTable = $currentYearTable;
}
```

### Complex Joins

Many methods use complex multi-table joins:

```sql
-- Example from getDepartmentTotals
SELECT 
    d.department_name,
    COALESCE(cy.TotalFinancedTAmt, 0) as TotalFinancedTAmt_Y2024,
    COALESCE(py.TotalFinancedTAmt, 0) as TotalFinancedTAmt_Y2023,
    CASE 
        WHEN py.TotalFinancedTAmt > 0 
        THEN ROUND(((cy.TotalFinancedTAmt - py.TotalFinancedTAmt) / py.TotalFinancedTAmt) * 100, 2)
        ELSE CASE WHEN cy.TotalFinancedTAmt > 0 THEN 100 ELSE NULL END
    END as VariationPercentage
FROM core_departments d
LEFT JOIN (current_year_subquery) cy ON d.id = cy.department_id
LEFT JOIN (previous_year_subquery) py ON d.id = py.department_id
```

### Conditional Filtering

Advanced conditional filtering for business rules:

```php
// Example from claim exception report
->where(function ($q) {
    $q->where(function ($sub) {
        $sub->whereIn('g.DepartmentId', [2, 3])
            ->where(function ($x) {
                $x->where(function ($c) {
                    $c->where('e.ClaimId', 7)
                      ->where('e.App_Odometer', 'Y');
                })->orWhere('e.ClaimId', '!=', 7);
            });
    })->orWhereNotIn('g.DepartmentId', [2, 3]);
})
```

## 📊 Data Transformation Patterns

### Collection Processing

The controller uses Laravel Collections extensively for data transformation:

```php
// Example from formatDepartmentClaimTypeTotals
$formatted = $departmentTotalsClaimTypeWise->groupBy('department_name')
    ->map(function ($items, $deptName) use ($yearId, $previousYearId) {
        return [
            'department_name' => $deptName,
            'claims' => $items->map(function ($item) {
                return [
                    'ClaimName' => $item->ClaimName,
                    'ClaimCode' => $item->ClaimCode,
                    'current_year_total' => $item->{"TotalFinancedTAmt_Y{$yearId}"},
                    'previous_year_total' => $item->{"TotalFinancedTAmt_Y{$previousYearId}"},
                    'variation' => $item->VariationPercentage
                ];
            }),
            'totals' => $this->calculateDepartmentTotals($items)
        ];
    });
```

### Null Handling

Comprehensive null and empty data handling:

```php
// Default data when no results found
$data['claimTypeTotals'] = $data['claimTypeTotals']->isEmpty()
    ? collect([['ClaimName' => 'No Data', 'ClaimCode' => '', 'FilledTotal' => 0]])
    : $data['claimTypeTotals'];
```

## 🚀 Performance Considerations

### Query Optimization

1. **Selective Column Retrieval**: Only fetches required columns
2. **Proper Indexing**: Queries designed for optimal index usage
3. **Aggregation at Database Level**: Reduces data transfer
4. **Conditional Joins**: Uses LEFT JOINs appropriately

### Memory Management

1. **Streaming for Large Exports**: Uses Laravel Excel streaming
2. **Collection Processing**: Efficient use of Laravel Collections
3. **Temporary File Cleanup**: Proper cleanup of generated files

### Caching Opportunities

Methods suitable for caching:
- `getDepartmentTotals()` - Cache for 1 hour
- `getClaimTypeTotals()` - Cache for 30 minutes
- `getTopEmployees()` - Cache for 15 minutes

## 🔐 Security Implementation

### Input Validation

```php
$request->validate([
    'bill_date_from' => 'required|date_format:Y-m-d',
    'bill_date_to' => 'required|date_format:Y-m-d|after_or_equal:bill_date_from',
    'filter_type' => 'nullable|in:all,increased,decreased,critical',
    'sort_by' => 'nullable|in:variation,current,previous',
]);
```

### SQL Injection Prevention

- Uses Eloquent ORM and Query Builder
- Parameterized queries for all user inputs
- No raw SQL with user data

### Access Control

- Authentication middleware on all methods
- Permission-based route protection
- Session-based context validation

## 📈 Monitoring and Logging

### Error Logging

```php
Log::error('Error in getDashboardData', [
    'bill_date_from' => $request->input('bill_date_from') ?? '-',
    'bill_date_to' => $request->input('bill_date_to') ?? '-',
    'year_id' => session('year_id') ?? '-',
    'error' => $e->getMessage(),
    'trace' => $e->getTraceAsString(),
]);
```

### Performance Monitoring

Track these metrics:
- Query execution times
- Memory usage for large exports
- File generation times
- Cache hit rates

## 🔗 Integration Points

### Frontend Integration

- **AJAX Endpoints**: Real-time data loading
- **Chart Libraries**: ApexCharts integration
- **File Downloads**: Seamless export handling
- **Progress Indicators**: For long-running operations

### External Systems

- **HRIMS Integration**: Employee and department data
- **S3 Storage**: File storage for exports
- **External APIs**: Rate information for KM calculations

### Database Connections

- **Primary Connection**: Main application database
- **HRIMS Connection**: Employee management system
- **Expense Connection**: Expense-specific database

---

The HomeController serves as the central hub for dashboard functionality, providing comprehensive analytics and reporting capabilities while maintaining high performance and security standards. Its sophisticated query patterns and data transformation logic make it the backbone of the expense management system's reporting infrastructure.