# Advance Finance API Documentation

## Overview
The Advance Finance API handles finance-level processing of advance requests that have been approved by the approval hierarchy. Finance users can approve, reject, or revert advance requests at the employee level.

**IMPORTANT: The `/api/advance/finance/pending` endpoint returns the EXACT same JSON response structure as `/api/advance` endpoint, but filtered for requests ready for finance processing.**

## Base URL
```
/api/advance/finance
```

## Authentication
All endpoints require user authentication. Pass `user_id` as a parameter in requests.

---

## Endpoints

### 1. Finance Test Endpoint

**Endpoint:** `GET /api/advance/finance/test`

**Description:** Test database connectivity and get finance-related statistics.

**Request Example:**
```http
GET /api/advance/finance/test
```

**Response:**
```json
{
  "success": true,
  "message": "Finance controller test successful",
  "data": {
    "finance_ready_activities": 25,
    "finance_processed_activities": 150,
    "advance_types": [
      {
        "id": 1,
        "type_name": "Activity",
        "type_code": "ACTIVITY"
      },
      {
        "id": 2,
        "type_name": "Expense", 
        "type_code": "EXPENSE"
      },
      {
        "id": 3,
        "type_name": "Other",
        "type_code": "OTHER"
      }
    ],
    "controller": "AdvanceFinanceController",
    "timestamp": "2024-01-27T10:30:00Z"
  }
}
```

### 2. Get Pending Finance Approvals

**Endpoint:** `GET /api/advance/finance/pending`

**Description:** Retrieves all advance requests that have approved activities ready for finance processing.

**Parameters:**
- `user_id` (required): ID of the finance user

**Request Example:**
```http
GET /api/advance/finance/pending?user_id=123
```

### 3. Get Finance Rejected Requests

**Endpoint:** `GET /api/advance/finance/rejected`

**Description:** Retrieves all advance requests that have activities rejected by finance.

**Parameters:**
- `user_id` (optional): ID of the finance user

**Request Example:**
```http
GET /api/advance/finance/rejected?user_id=123
```

### 4. Get Finance Approved Requests

**Endpoint:** `GET /api/advance/finance/approved`

**Description:** Retrieves all advance requests that have activities approved by finance.

**Parameters:**
- `user_id` (optional): ID of the finance user

**Request Example:**
```http
GET /api/advance/finance/approved?user_id=123
```

**Response Format:**
```json
{
  "success": true,
  "message": "Pending finance approvals retrieved successfully",
  "data": [
    {
      "id": 1,
      "advance_request_id": 5,
      "employee_id": "EMP001",
      "employee_name": "John Doe",
      "employee_code": "EMP001",
      "employee_email": "john.doe@company.com",
      "department": "Sales",
      "designation": "Sales Manager",
      "total_finance_pending_amount": 15000.00,
      "finance_pending_activities_count": 2,
      "finance_level_info": "Ready for Finance Processing",
      "created_at": "2024-01-15T10:30:00Z",
      "updated_at": "2024-01-15T10:30:00Z",
      "deleted_at": null,
      "req_data": {
        "id": 5,
        "activity_name_id": 10,
        "activity_name": "Travel Advance",
        "month": 1,
        "req_app_submit_id": "EMP001",
        "submission_no": 1,
        "last_submitted_at": "2024-01-15T09:00:00Z",
        "advance_fill_date": "2024-01-15",
        "current_status": "Finance",
        "reverted_from_status": null,
        "revert_reason": null,
        "reverted_by": null,
        "created_by": "EMP001",
        "updated_by": "EMP001",
        "reverted_at": null,
        "created_at": "2024-01-15T08:30:00Z",
        "updated_at": "2024-01-15T10:30:00Z",
        "deleted_at": null
      },
      "activities": [
        {
          "id": 10,
          "advance_request_employee_id": 1,
          "activity_type_id": 2,
          "activity_type_name": "Travel",
          "activity_crops_id": "[1,2,3]",
          "crop_names": ["Wheat", "Rice", "Corn"],
          "activity_variety_id": "[5,6]",
          "variety_names": ["Variety A", "Variety B"],
          "amount": 10000.00,
          "purpose": "Field visit for crop inspection",
          "current_approval_level": 2,
          "approval_status": "Approved",
          "finance_id": null,
          "finance_approved_amount": null,
          "finance_status": null,
          "finance_remark": null,
          "finance_date": null,
          "created_at": "2024-01-15T08:30:00Z",
          "updated_at": "2024-01-15T10:30:00Z",
          "deleted_at": null
        }
      ]
    }
  ]
}
```

**Response Fields:**
- `employee_*`: Employee information from HRIMS database
- `total_finance_pending_amount`: Sum of all approved activities ready for finance
- `finance_pending_activities_count`: Number of activities ready for finance processing
- `req_data`: Complete advance request information
- `activities`: Array of approved activities ready for finance processing
- `crop_names`/`variety_names`: Resolved names from HRIMS core tables

---

### 3. Finance Approve Activities

**Endpoint:** `POST /api/advance/finance/approve`

**Description:** Approve all or specific activities for an employee at finance level.

**Request Body:**
```json
{
  "user_id": 456,
  "employee_id": "EMP001",
  "request_id": 5,
  "activity_id": 10,
  "finance_approved_amount": 9500.00,
  "finance_remark": "Approved with reduced amount due to policy limits"
}
```

**Required Parameters:**
- `user_id` (integer): Finance user ID
- `employee_id` (integer): Employee ID to approve
- `request_id` (integer): Advance request ID

**Optional Parameters:**
- `activity_id` (integer): Specific activity ID (if not provided, all approved activities for the employee will be processed)
- `finance_approved_amount` (decimal): Approved amount (if not provided, full requested amount is approved)
- `finance_remark` (string): Finance remarks

**Request Example:**
```http
POST /api/advance/finance/approve
Content-Type: application/json

{
  "user_id": 456,
  "employee_id": "EMP001",
  "request_id": 5,
  "finance_approved_amount": 9500.00,
  "finance_remark": "Approved with policy adjustment"
}
```

**Response:**
```json
{
  "success": true,
  "message": "Employee activities approved by finance successfully",
  "data": {
    // Complete advance request object with updated activities
  }
}
```

**Business Logic:**
- Only processes activities with `approval_status = 'Approved'` and `finance_status = null`
- If `finance_approved_amount` is provided and multiple activities exist, amount is distributed proportionally
- Updates activity fields: `finance_id`, `finance_approved_amount`, `finance_status`, `finance_remark`, `finance_date`
- Checks if all approved activities are finance-processed to update request status
- Sends email notifications to requester

---

### 4. Finance Reject Activities

**Endpoint:** `POST /api/advance/finance/reject`

**Description:** Reject all or specific activities for an employee at finance level.

**Request Body:**
```json
{
  "user_id": 456,
  "employee_id": "EMP001",
  "request_id": 5,
  "activity_id": 10,
  "remarks": "Exceeds policy limits for this activity type"
}
```

**Required Parameters:**
- `user_id` (integer): Finance user ID
- `employee_id` (integer): Employee ID to reject
- `request_id` (integer): Advance request ID
- `remarks` (string): Rejection reason

**Optional Parameters:**
- `activity_id` (integer): Specific activity ID (if not provided, all approved activities for the employee will be rejected)

**Response:**
```json
{
  "success": true,
  "message": "Employee activities rejected by finance successfully",
  "data": {
    // Complete advance request object with updated activities
  }
}
```

**Business Logic:**
- Sets `finance_status = 'Rejected'` for targeted activities
- Updates request status based on remaining activities
- Logs rejection action in audit trail
- Sends rejection notification emails

---

### 5. Finance Revert Activities

**Endpoint:** `POST /api/advance/finance/revert`

**Description:** Revert all or specific activities for an employee back to the last approval level.

**Request Body:**
```json
{
  "user_id": 456,
  "employee_id": "EMP001",
  "request_id": 5,
  "activity_id": 10,
  "remarks": "Need additional documentation before finance approval"
}
```

**Required Parameters:**
- `user_id` (integer): Finance user ID
- `employee_id` (integer): Employee ID to revert
- `request_id` (integer): Advance request ID
- `remarks` (string): Revert reason

**Optional Parameters:**
- `activity_id` (integer): Specific activity ID (if not provided, all approved activities for the employee will be reverted)

**Response:**
```json
{
  "success": true,
  "message": "Employee activities reverted by finance successfully",
  "data": {
    // Complete advance request object with updated activities
  }
}
```

**Business Logic:**
- Finds the last approved level from `advance_activity_approvals` table
- Sets activity `current_approval_level` back to last approved level
- Sets `approval_status = 'Reverted'`
- Updates request status to `'Reverted'` with revert metadata
- Sends revert notification emails

---

## Status Flow

### Request Status Transitions
1. **Finance** → **Closed** (when all approved activities are finance-processed)
2. **Finance** → **Rejected** (when activities are rejected)
3. **Finance** → **Reverted** (when activities are reverted)
4. **In_Approval** → **Finance** (activities can be processed even in approval status if some are approved)

### Activity Status Transitions
1. **Approved** + `finance_status = null` → **Approved** + `finance_status = 'Approved'`
2. **Approved** + `finance_status = null` → **Approved** + `finance_status = 'Rejected'`
3. **Approved** → **Reverted** (with updated approval level)

---

## Error Responses

### Common Error Formats
```json
{
  "success": false,
  "message": "Error description"
}
```

### Possible Error Messages
- `"employee_id and request_id are required"`
- `"Employee ID {id} not found in request ID {id}"`
- `"No approved activities ready for finance found for employee ID {id}"`
- `"Request is in '{status}' status, cannot process by finance. Expected 'Finance' or 'In Approval'"`
- `"Finance approved amount cannot exceed total activities amount"`
- `"Activity has already been processed by finance. Status: {status}"`

---

## Data Models

### Activity Finance Fields
```json
{
  "finance_id": "integer|null - Finance user who processed",
  "finance_approved_amount": "decimal|null - Amount approved by finance",
  "finance_status": "enum|null - 'Approved'|'Rejected'",
  "finance_remark": "string|null - Finance comments",
  "finance_date": "date|null - Date of finance processing"
}
```

### Request Status Values
- `Draft` - Initial state
- `Submitted` - Submitted for approval
- `In_Approval` - In approval hierarchy
- `Approved` - All activities approved
- `Finance` - At finance level
- `Rejected` - Rejected by finance
- `Reverted` - Reverted by finance
- `Closed` - Finance processing complete

---

## Integration Notes

### Email Notifications
- Controlled by `MAIL_ACTIVE` environment variable
- Sent to requester's email from HRIMS database
- Includes activity details table in email body
- Templates: `finance_approval`, `finance_rejection`, `finance_revert`

### Database Connections
- Main database: Advance request tables
- HRIMS database: Employee details, crops, varieties
- Audit trail: All actions logged in `advance_request_actions`

### Employee-Level Processing
All finance operations work at the employee level, meaning:
- One request can have multiple employees
- Each employee can have multiple activities
- Finance can process employees independently
- Proportional amount distribution for multiple activities per employee

### Validation Rules
- Activities must be in `'Approved'` status with `finance_status = null`
- Request must be in `'Finance'` or `'In_Approval'` status
- Finance approved amount cannot exceed activity amount
- Employee must exist in the specific request