# HOD Advance Report API Documentation

## Overview
This API allows HODs (Heads of Department) to view advance requests submitted by their team members based on the reporting structure defined in `hrm_employee_reporting` table.

The API provides two main report types:
1. **Activity-wise Report** - Detailed view of all activities with month-wise breakdown
2. **Employee-wise Report** - Summary statistics per employee with month-wise breakdown

---

## Base URL
```
/api/advance/hod
```

---

## Endpoints

### 1. Consolidated Report (Single Table View)

Get a simplified single-table view showing one row per employee per month with aggregated paid advance details.

**Endpoint:** `GET /api/advance/hod/consolidated-report`

**Query Parameters:**

| Parameter | Type | Required | Description |
|-----------|------|----------|-------------|
| `hod_id` | integer | Yes | HOD's employee ID from hrm_employee_reporting.HodId |
| `year` | integer | No | Year (default: current year) |
| `month` | integer | No | Month (1-12) for specific month filter |
| `employee_id` | integer | No | Filter by specific employee |

**Example Request:**
```bash
GET /api/advance/hod/consolidated-report?hod_id=123&year=2024&month=2
```

**Response Structure:**
```json
{
  "success": true,
  "message": "Consolidated report retrieved successfully",
  "data": [
    {
      "employee_id": 456,
      "employee_name": "John Doe",
      "employee_code": "EMP001",
      "email": "john.doe@company.com",
      "department": "Sales",
      "designation": "Sales Manager",
      "payment_month": 2,
      "payment_month_name": "February",
      "payment_year": 2024,
      "first_payment_date": "2024-02-10",
      "last_payment_date": "2024-02-25",
      "total_requests": 3,
      "total_activities": 8,
      "total_requested_amount": 50000.00,
      "total_finance_approved_amount": 45000.00,
      "total_paid_amount": 45000.00,
      "request_ids": "25, 28, 31",
      "activity_types": "Travel, Meeting, Field Visit",
      "approval_variance": 5000.00,
      "payment_variance": 0.00,
      "approval_percentage": 90.00,
      "payment_percentage": 100.00
    }
  ]
}
```

**Response Fields:**

Employee Information:
- `employee_id` - Employee ID
- `employee_name` - Full name
- `employee_code` - Employee code
- `email` - Email address
- `department` - Department name
- `designation` - Designation

Payment Period:
- `payment_month` - Month number (1-12)
- `payment_month_name` - Month name
- `payment_year` - Year
- `first_payment_date` - First payment date in month
- `last_payment_date` - Last payment date in month

Summary Statistics:
- `total_requests` - Number of distinct requests
- `total_activities` - Total activities paid
- `total_requested_amount` - Total amount requested
- `total_finance_approved_amount` - Total finance approved
- `total_paid_amount` - Total amount paid

Additional Details:
- `request_ids` - Comma-separated request IDs
- `activity_types` - Comma-separated activity types

Variance Analysis:
- `approval_variance` - Difference between requested and approved
- `payment_variance` - Difference between approved and paid
- `approval_percentage` - Approval rate percentage
- `payment_percentage` - Payment completion percentage

**Key Features:**
- Shows only PAID advances
- One row per employee per month
- Aggregates all activities for the period
- Includes variance analysis
- Filterable by month and employee

---

### 2. Activity-wise Report

Get detailed activity-level advance request data for HOD's team members, organized by month.

**Endpoint:** `GET /api/advance/hod/activity-wise-report`

**Query Parameters:**

| Parameter | Type | Required | Description |
|-----------|------|----------|-------------|
| `hod_id` | integer | Yes | HOD's employee ID from hrm_employee_reporting.HodId |
| `year` | integer | No | Year (default: current year) |
| `month` | integer | No | Month (1-12) for specific month filter |
| `status` | string | No | Filter by request status (Draft, Submitted, In_Approval, etc.) |

**Example Request:**
```bash
GET /api/advance/hod/activity-wise-report?hod_id=123&year=2024&month=2
```

**Response Structure:**
```json
{
  "success": true,
  "message": "Activity-wise report retrieved successfully",
  "data": [
    {
      "month": 2,
      "month_name": "February",
      "year": 2024,
      "total_activities": 15,
      "total_amount": 125000.00,
      "total_approved_amount": 0,
      "total_finance_approved_amount": 100000.00,
      "employees": [
        {
          "employee_id": 456,
          "employee_name": "John Doe",
          "employee_code": "EMP001",
          "department": "Sales",
          "designation": "Sales Manager",
          "activities": [
            {
              "activity_id": 101,
              "request_id": 25,
              "request_status": "Finance_Approved",
              "activity_name": "Field Visit",
              "activity_type": "Travel",
              "crops": ["Cotton", "Maize"],
              "amount": 5000.00,
              "approval_status": "Approved",
              "finance_status": "Approved",
              "finance_approved_amount": 4500.00,
              "payment_status": "Pending",
              "payment_amount": 0,
              "purpose": "Customer meeting in Mumbai",
              "created_at": "2024-02-15T10:30:00.000000Z",
              "submission_no": 1
            }
          ],
          "summary": {
            "total_activities": 5,
            "total_amount": 25000.00,
            "approved_activities": 4,
            "pending_activities": 1,
            "rejected_activities": 0,
            "total_finance_approved": 22000.00
          }
        }
      ]
    }
  ]
}
```

**Response Fields:**

Month Level:
- `month` - Month number (1-12)
- `month_name` - Month name (January, February, etc.)
- `year` - Year
- `total_activities` - Total activities in this month
- `total_amount` - Total requested amount
- `total_finance_approved_amount` - Total finance approved amount

Employee Level:
- `employee_id` - Employee ID
- `employee_name` - Full name
- `employee_code` - Employee code
- `department` - Department name
- `designation` - Designation
- `activities` - Array of activity details
- `summary` - Employee statistics for the month

Activity Level:
- `activity_id` - Activity ID
- `request_id` - Parent request ID
- `request_status` - Overall request status
- `activity_name` - Activity name (from claimtype table)
- `activity_type` - Activity type (Travel, Meeting, etc.)
- `crops` - Array of crop names
- `amount` - Requested amount
- `approval_status` - Approval status (Pending/Approved/Rejected)
- `finance_status` - Finance status
- `finance_approved_amount` - Finance approved amount
- `payment_status` - Payment status
- `payment_amount` - Paid amount
- `purpose` - Activity purpose
- `created_at` - Request creation date
- `submission_no` - Submission number

---

### 3. Employee-wise Report

Get summarized statistics per employee, organized by month.

**Endpoint:** `GET /api/advance/hod/employee-wise-report`

**Query Parameters:**

| Parameter | Type | Required | Description |
|-----------|------|----------|-------------|
| `hod_id` | integer | Yes | HOD's employee ID |
| `year` | integer | No | Year (default: current year) |
| `month` | integer | No | Month (1-12) for specific month filter |

**Example Request:**
```bash
GET /api/advance/hod/employee-wise-report?hod_id=123&year=2024
```

**Response Structure:**
```json
{
  "success": true,
  "message": "Employee-wise report retrieved successfully",
  "data": [
    {
      "month": 2,
      "month_name": "February",
      "year": 2024,
      "employees": [
        {
          "employee_id": 456,
          "employee_name": "John Doe",
          "employee_code": "EMP001",
          "email": "john.doe@company.com",
          "department": "Sales",
          "designation": "Sales Manager",
          "statistics": {
            "total_activities": 5,
            "total_amount": 25000.00,
            "approved_amount": 25000.00,
            "finance_approved_amount": 22000.00,
            "paid_amount": 20000.00,
            "approved_count": 4,
            "pending_count": 1,
            "rejected_count": 0,
            "finance_approved_count": 4,
            "paid_count": 3,
            "approval_rate": 80.00
          }
        }
      ],
      "month_summary": {
        "total_employees": 8,
        "total_activities": 45,
        "total_amount": 180000.00,
        "total_approved_amount": 175000.00,
        "total_finance_approved_amount": 160000.00,
        "total_paid_amount": 140000.00
      }
    }
  ]
}
```

**Response Fields:**

Month Level:
- `month` - Month number
- `month_name` - Month name
- `year` - Year
- `employees` - Array of employee statistics
- `month_summary` - Aggregated statistics for the month

Employee Statistics:
- `total_activities` - Total activities submitted
- `total_amount` - Total requested amount
- `approved_amount` - Total approved amount
- `finance_approved_amount` - Total finance approved amount
- `paid_amount` - Total paid amount
- `approved_count` - Number of approved activities
- `pending_count` - Number of pending activities
- `rejected_count` - Number of rejected activities
- `finance_approved_count` - Number of finance approved activities
- `paid_count` - Number of paid activities
- `approval_rate` - Approval percentage

Month Summary:
- `total_employees` - Number of employees with activities
- `total_activities` - Total activities in month
- `total_amount` - Total requested amount
- `total_approved_amount` - Total approved amount
- `total_finance_approved_amount` - Total finance approved
- `total_paid_amount` - Total paid amount

---

### 4. Team Members List

Get list of all team members reporting to the HOD.

**Endpoint:** `GET /api/advance/hod/team-members`

**Query Parameters:**

| Parameter | Type | Required | Description |
|-----------|------|----------|-------------|
| `hod_id` | integer | Yes | HOD's employee ID |

**Example Request:**
```bash
GET /api/advance/hod/team-members?hod_id=123
```

**Response Structure:**
```json
{
  "success": true,
  "message": "Team members retrieved successfully",
  "data": [
    {
      "EmployeeID": 456,
      "EmployeeName": "John Doe",
      "EmployeeCode": "EMP001",
      "EmailId_Vnr": "john.doe@company.com",
      "DepartmentName": "Sales",
      "Designation": "Sales Manager"
    },
    {
      "EmployeeID": 457,
      "EmployeeName": "Jane Smith",
      "EmployeeCode": "EMP002",
      "EmailId_Vnr": "jane.smith@company.com",
      "DepartmentName": "Sales",
      "Designation": "Sales Executive"
    }
  ]
}
```

---

## Error Responses

### Missing HOD ID
```json
{
  "success": false,
  "message": "HOD ID is required"
}
```

### No Team Members Found
```json
{
  "success": true,
  "message": "No team members found for this HOD",
  "data": []
}
```

### Server Error
```json
{
  "success": false,
  "message": "Failed to retrieve activity-wise report: [error details]"
}
```

---

## Use Cases

### 1. Simple Monthly Payment Review (Consolidated Report)
HOD wants a quick overview of all paid advances for the month:
```bash
GET /api/advance/hod/consolidated-report?hod_id=123&month=2&year=2024
```
**Result:** One row per employee showing total paid amounts for February 2024

### 2. Specific Employee Payment History (Consolidated Report)
HOD wants to see payment history for a specific employee:
```bash
GET /api/advance/hod/consolidated-report?hod_id=123&employee_id=456&year=2024
```
**Result:** Month-by-month payment summary for employee 456

### 3. Monthly Team Performance Review
HOD wants to review all advance requests submitted by team in current month:
```bash
GET /api/advance/hod/employee-wise-report?hod_id=123&month=2&year=2024
```

### 2. Detailed Activity Audit
HOD wants to see all activity details for a specific employee:
```bash
GET /api/advance/hod/activity-wise-report?hod_id=123&employee_id=456&year=2024
```

### 3. Pending Approvals Overview
HOD wants to see all pending advance requests:
```bash
GET /api/advance/hod/activity-wise-report?hod_id=123&status=In_Approval
```

### 4. Year-end Summary
HOD wants annual summary for all team members:
```bash
GET /api/advance/hod/employee-wise-report?hod_id=123&year=2024
```

### 5. Team Structure View
HOD wants to see complete team list:
```bash
GET /api/advance/hod/team-members?hod_id=123
```

---

## Data Source

The API uses the following database structure:

**Reporting Structure:**
```sql
hrm_employee_reporting
- ReportingId
- EmployeeID (team member)
- AppraiserId
- ReviewerId
- HodId (HOD who can view reports)
```

**Advance Data:**
- `advance_requests` - Main request records
- `advance_request_employees` - Employee associations
- `advance_request_activities` - Activity details
- `hrm_employee_general` - Employee details
- `core_departments` - Department information
- `core_designations` - Designation information

---

## Notes

1. **Reporting Hierarchy**: Only employees where `hrm_employee_reporting.HodId` matches the provided `hod_id` will be included
2. **Month Sorting**: Results are sorted by month in descending order (latest first)
3. **Year Filter**: Defaults to current year if not specified
4. **Empty Results**: Returns empty array if no data found (not an error)
5. **Performance**: Large teams may have slower response times; consider using month filter
6. **Permissions**: Ensure HOD has proper authorization before calling these endpoints

---

## Status Values

### Request Status
- `Draft` - Initial draft
- `Submitted` - Submitted for approval
- `In_Approval` - Under approval process
- `Approved` - Approved by all approvers
- `Finance` - At finance level
- `Finance_Approved` - Approved by finance
- `Finance_Rejected` - Rejected by finance
- `Payment` - Payment processing
- `Closed` - Completed
- `Rejected` - Rejected
- `Reverted` - Sent back for changes
- `Finalized` - Final settlement done

### Activity Status Fields
- **approval_status**: `Pending`, `Approved`, `Rejected`, `Reverted`
- **finance_status**: `Pending`, `Approved`, `Rejected`, `Reverted`
- **payment_status**: `Pending`, `Ready for Payment`, `Released`, `Paid`
- **final_status**: `Pending`, `Exported to Focus`, `Imported from Focus`, `Finalized`
