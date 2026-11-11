# OdometerBackdateController

## Overview
The OdometerBackdateController manages the approval workflow for backdated odometer readings in expense claims. It provides functionality for department heads and business unit managers to review, approve, or reject odometer readings that were submitted after the actual travel date.

## Namespace
`App\Http\Controllers\Api`

## Routes

### API Routes (Defined in `routes/api.php`)
- **GET** `/api/odo-backdate-submission/{yearId}/{employeeId}/` - Get backdate submissions (odoBackdateSubmission)
- **GET** `/api/odo-backdate-submission-show/{yearId}/{employeeId}/` - Check button visibility (odoBackdateSubmissionBtnShow)
- **GET** `/api/odo-backdate-approval/{yearId}/{employeeId}/{expenseId}/` - Approve backdate (odoBackdateApprove)
- **GET** `/api/odo-backdate-rejection/{yearId}/{employeeId}/{expenseId}/` - Reject backdate (odoBackdateReject)
- **GET** `/api/odo-backdate-bulk-approval/{yearId}/{employeeId}/{empiid}` - Bulk approve (odoBackdateBulkApproval)
- **GET** `/api/odo-backdate-bulk-rejection/{yearId}/{employeeId}/{empiid}` - Bulk reject (odoBackdateBulkRejection)

**Note**: All routes use GET method for simplicity in API consumption.
**Middleware**: API routes typically don't require web authentication but may have API-specific middleware.

## Dependencies
- `App\Models\ExpenseClaim`
- `Illuminate\Support\Facades\DB`
- `Storage`

## Methods

### odoBackdateSubmission($yearId, $employeeId)
**Purpose**: Retrieves backdated odometer submissions for approval by authorized personnel.

**Parameters**:
- `$yearId`: Financial year ID
- `$employeeId`: ID of the approving employee (HOD or BU Manager)

**Returns**: JSON response with grouped odometer submissions

**Authorization Levels**:
- **BU Level**: Business Unit General Manager can approve for entire BU
- **HOD Level**: Head of Department can approve for department employees

**Validation Checks**:
1. Employee has assigned department and business unit
2. Backdate settings exist and are active for department
3. Employee has appropriate authorization level
4. Effective date configuration exists

**Data Filtering**:
- Only claim type 7 (odometer claims)
- Bill date differs from creation date
- Creation date >= effective date
- Employees within authorized scope
- Excludes deactivated claims
- Filters by configured verticals
- Applies delayed day threshold if configured

**Response Structure**:
```json
{
  "employee_id": {
    "approval_type": "bu_level|hod_level",
    "month": "month_number",
    "employee_name": "Full Name",
    "EmpCode": "employee_code",
    "total_records": 10,
    "status_count": {
      "Pending": 5,
      "Approved": 3,
      "Rejected": 2
    },
    "items": [
      {
        "ExpId": "expense_id",
        "BillDate": "YYYY-MM-DD",
        "CrDate": "YYYY-MM-DD",
        "WType": "work_type",
        "odomtr_opening": "opening_reading",
        "odomtr_closing": "closing_reading",
        "opening_filepath": "s3_url",
        "closing_filepath": "s3_url",
        "TotKm": "total_kilometers",
        "RatePerKM": "rate_per_km",
        "ClaimStatus": "status",
        "days_difference": "days_between_dates",
        "Backdate_Odometer_Status": "Pending|Approved|Rejected"
      }
    ]
  }
}
```

### odoBackdateSubmissionBtnShow($yearId, $employeeId)
**Purpose**: Determines if the backdate approval button should be displayed.

**Parameters**:
- `$yearId`: Financial year ID
- `$employeeId`: ID of the potential approver

**Returns**: JSON response with button visibility status

**Logic**:
- Performs same authorization checks as main submission method
- Counts only pending backdate submissions
- Returns button visibility flag based on pending count

**Response Structure**:
```json
{
  "pending_count": 5,
  "show_button": true
}
```

### odoBackdateApprove($yearId, $employeeId, $expenseId)
**Purpose**: Approves a specific backdated odometer submission.

**Parameters**:
- `$yearId`: Financial year ID
- `$employeeId`: ID of the approving employee
- `$expenseId`: Expense claim ID to approve

**Returns**: JSON response with approval status

**Approval Process**:
1. Validates expense claim exists
2. Checks current status (prevents duplicate approvals)
3. Updates status to 'A' (Approved)
4. Sets approver and approval date
5. Updates claim status to 'Filled'
6. Sets claim step to '1'

**Status Validations**:
- Cannot approve already approved claims
- Cannot approve already rejected claims

### odoBackdateReject($yearId, $employeeId, $expenseId)
**Purpose**: Rejects a specific backdated odometer submission.

**Parameters**:
- `$yearId`: Financial year ID
- `$employeeId`: ID of the rejecting employee
- `$expenseId`: Expense claim ID to reject

**Returns**: JSON response with rejection status

**Rejection Process**:
1. Validates expense claim exists
2. Checks current status (prevents duplicate rejections)
3. Updates status to 'R' (Rejected)
4. Sets rejector and rejection date

**Status Validations**:
- Cannot reject already rejected claims
- Cannot reject already approved claims

### odoBackdateBulkApproval($yearId, $employeeId, $empid)
**Purpose**: Bulk approves all pending backdated odometer submissions for an employee.

**Parameters**:
- `$yearId`: Financial year ID
- `$employeeId`: ID of the approving employee
- `$empid`: Employee ID whose submissions to approve

**Returns**: JSON response with count of approved records

**Bulk Approval Criteria**:
- Claim type 7 (odometer)
- Status 'P' (Pending)
- Not deactivated claims
- Claim step < 3

**Updates Applied**:
- Status to 'A' (Approved)
- Claim status to 'Filled'
- Claim step to 3
- Approver and approval date

### odoBackdateBulkRejection($yearId, $employeeId, $empid)
**Purpose**: Bulk rejects all pending backdated odometer submissions for an employee.

**Parameters**:
- `$yearId`: Financial year ID
- `$employeeId`: ID of the rejecting employee
- `$empid`: Employee ID whose submissions to reject

**Returns**: JSON response with count of rejected records

**Bulk Rejection Criteria**: Same as bulk approval

**Updates Applied**:
- Status to 'R' (Rejected)
- Rejector and rejection date

## Private Helper Methods

### isBuGeneralManager($employeeId, $buId)
**Purpose**: Validates if employee is a Business Unit General Manager.

**Validation Criteria**:
- TerrId = 0 (no territory assignment)
- ZoneId = 0 (no zone assignment)
- RegionId = 0 (no region assignment)
- BUId > 0 and matches specified BU
- Employee exists in specified BU

**Returns**: Boolean indicating BU GM status

### isHod($employeeId, $departmentId)
**Purpose**: Validates if employee is Head of Department.

**Logic**:
1. Gets all employees in department
2. Checks if employee is at top of hierarchy
3. Validates through recursive hierarchy check

**Returns**: Boolean indicating HOD status

### isTopInHierarchy($employeeId, $employeeMap, $validEmployeeIds)
**Purpose**: Recursively determines if employee is at top of department hierarchy.

**Logic**:
- If no manager or manager outside department: top level
- If has subordinates: continue hierarchy check
- If no subordinates: not top level

**Returns**: Boolean indicating top hierarchy status

## Database Schema Dependencies

### Tables Used
1. **hrims.hrm_employee_general**: Employee department/BU assignments
2. **expense.odo_backdate_settings**: Department backdate configuration
3. **y{year}_expenseclaims**: Year-specific expense claims
4. **y{year}_monthexpensefinal**: Monthly expense status
5. **hrims.hrm_employee**: Employee basic information
6. **hrims.core_departments**: Department information

### Key Configuration Fields
- `effective_date`: When backdate rules became active
- `approval_type`: 'bu_level' or 'hod_level'
- `verticals`: Comma-separated list of allowed verticals
- `delayed_day`: Minimum days difference to require approval
- `is_active`: Whether settings are currently active

## Business Rules

### Approval Authority
- **BU General Manager**: Can approve for entire business unit
- **Head of Department**: Can approve for department employees

### Backdate Criteria
- Only odometer claims (ClaimId = 7)
- Bill date must differ from creation date
- Creation date must be >= effective date
- Employee must be in configured verticals
- Days difference must exceed delayed day threshold

### Status Workflow
- **P (Pending)**: Awaiting approval
- **A (Approved)**: Approved and claim activated
- **R (Rejected)**: Rejected and blocked

## File Storage Integration
- Uses S3 storage for odometer images
- Generates signed URLs for opening/closing odometer photos
- File path format: `Expense/{yearId}/{employeeId}/{filename}`

## Error Handling
- Validates employee authorization at multiple levels
- Checks configuration existence and active status
- Prevents duplicate status changes
- Provides descriptive error messages for each failure scenario

## Security Features
- Multi-level authorization validation
- Department and BU scope restrictions
- Vertical-based filtering
- Status change prevention logic
- Audit trail with approver and date tracking