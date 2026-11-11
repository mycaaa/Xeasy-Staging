# ExtractController

## Overview
The ExtractController manages extraction reports for expense claims data. It provides functionality to view, filter, search, and export extraction queue data with detailed information about expense claims, employee details, and processing status.

## Namespace
`App\Http\Controllers\admin\reports`

## Routes

### Reports Routes (Protected by `permission:section_reports`)
- **GET** `/reports/extract` - Display extraction report page (extract)
- **POST** `/reports/extract/data` - Get paginated extraction data (getData)
- **GET** `/reports/extract/details/{id}` - Get extraction details (getDetails)
- **GET** `/reports/extract/export` - Export extraction data (export)

**Route Names**: All routes are prefixed with `reports.`

## Dependencies
- `Illuminate\Support\Facades\DB`
- `App\Exports\ExtractionReportExport`
- `Maatwebsite\Excel\Facades\Excel`

## Methods

### extract()
**Purpose**: Displays the main extraction report page with claim type filters.

**Parameters**: None

**Returns**: View with available claim types for filtering

**Features**:
- Loads active claim types (excluding specific IDs: 5, 19, 20)
- Filters by company group ID (cgId = 1)
- Orders claim types alphabetically

**View**: `admin.reports.extract`

### getData(Request $request)
**Purpose**: Provides paginated data for DataTables with filtering and search capabilities.

**Parameters**:
- `start` (integer): Pagination offset
- `length` (integer): Number of records per page (default: 25)
- `draw` (integer): DataTables draw counter
- `date_range` (string): Date range filter ("YYYY-MM-DD to YYYY-MM-DD")
- `claim_type` (integer): Claim type ID filter
- `status` (string): Queue status filter
- `employee` (string): Employee search term
- `order` (array): Sorting configuration

**Returns**: JSON response with DataTables format

**Response Structure**:
```json
{
  "draw": 1,
  "recordsTotal": 100,
  "recordsFiltered": 100,
  "data": [
    {
      "id": "queue_id",
      "extract_id": "ExpId",
      "employee_name": "Full Name",
      "claim_type": "Claim Type Name",
      "date": "dd-MMM-yyyy",
      "amount": "formatted_amount",
      "status": "pending|processing|completed"
    }
  ],
  "summary": {
    "total_records": 100,
    "total_amount": "formatted_total",
    "pending_count": 50,
    "processing_count": 30
  }
}
```

### getDetails($id)
**Purpose**: Retrieves detailed information for a specific extraction record.

**Parameters**:
- `$id`: Extraction queue ID

**Returns**: 
- View with extraction and expense details
- JSON error response if record not found

**Features**:
- Joins multiple tables for comprehensive data
- Includes expense claim details
- Shows employee information
- Displays claim type information

**View**: `admin.reports.extract_details`

### export(Request $request)
**Purpose**: Exports extraction data to Excel or CSV format.

**Parameters**:
- `format` (string): Export format ('excel' or 'csv', default: 'excel')
- All filtering parameters from getData method

**Returns**: 
- File download (Excel/CSV)
- Redirect with error message if no data or export fails

**Features**:
- Supports Excel (.xlsx) and CSV formats
- Applies same filters as getData method
- Generates timestamped filenames
- Handles empty data gracefully

## Private Helper Methods

### getHrimsEmployeeTable()
**Purpose**: Returns the HRIMS employee table name.

**Returns**: String `'hrims.hrm_employee'`

### getExpenseTable()
**Purpose**: Returns the year-specific expense claims table name.

**Returns**: String `"y{year_id}_expenseclaims"`

**Uses**: Session variable `year_id`

### getTempPunchTable()
**Purpose**: Returns the year-specific temporary punch table name.

**Returns**: String `"y{year_id}_temp_punch_1"`

**Uses**: Session variable `year_id`

### buildQuery(Request $request)
**Purpose**: Constructs the main database query with joins and filters.

**Features**:
- Joins extraction queue, expense claims, temp punch, claim types, and employee tables
- Applies date range filtering
- Supports claim type filtering
- Implements status filtering
- Provides employee search functionality
- Handles sorting and ordering

**Joins**:
- `tbl_extraction_queue` (main table)
- Year-specific expense claims table
- Year-specific temp punch table
- `claimtype` table (left join)
- HRIMS employee table (left join)

**Filtering Options**:
- Date range (single date or range)
- Claim type ID
- Queue status
- Employee name/code search

### getSummaryStats(Request $request)
**Purpose**: Calculates summary statistics for the filtered data.

**Returns**: Array with summary information

**Statistics Provided**:
- Total records count
- Total amount (formatted)
- Pending records count
- Processing records count

**Filters**: Only includes PENDING and PROCESSING status records

## Database Schema Dependencies

### Tables Used
1. **tbl_extraction_queue**: Main extraction queue table
2. **y{year}_expenseclaims**: Year-specific expense claims
3. **y{year}_temp_punch_1**: Year-specific temporary punch data
4. **claimtype**: Claim type definitions
5. **hrims.hrm_employee**: Employee information from HRIMS

### Key Relationships
- Extraction queue → Expense claims (via ExpId)
- Extraction queue → Temp punch (via temp_punch_id)
- Expense claims → Claim types (via ClaimId)
- Expense claims → Employees (via FilledBy)

## Filtering and Search Features

### Date Filtering
- Single date: `YYYY-MM-DD`
- Date range: `YYYY-MM-DD to YYYY-MM-DD`
- Applied to `BillDate` field

### Employee Search
Searches across multiple employee fields:
- First name (`Fname`)
- Second name (`Sname`)
- Last name (`Lname`)
- Employee code (`EmpCode`)

### Status Filtering
- Filters by extraction queue status
- Case-insensitive matching
- Converts to uppercase for database query

### Claim Type Filtering
- Filters by specific claim type ID
- Excludes certain claim types (5, 19, 20) from selection

## Export Functionality
- Uses Laravel Excel package
- Supports multiple formats (XLSX, CSV)
- Applies all active filters to export data
- Generates timestamped filenames
- Handles large datasets efficiently

## Error Handling
- Comprehensive try-catch blocks for all methods
- Detailed error logging with stack traces
- Graceful error responses for API calls
- User-friendly error messages for exports
- Fallback values for missing data

## Performance Considerations
- Pagination support for large datasets
- Efficient database queries with proper joins
- Indexed filtering on common fields
- Summary statistics calculated separately
- Lazy loading for export operations

## Security Features
- Input validation and sanitization
- SQL injection prevention through query builder
- Session-based year filtering
- Proper error message handling without data exposure