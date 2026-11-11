# Employee Ledger Balance Integration

## Overview
This document describes the employee ledger balance functionality integrated into the Advance Finance Controller. The system tracks employee advance payments and adjustments through a dedicated ledger balance table.

## Database Structure

### Table: `employee_ledger_balances`

```sql
CREATE TABLE `employee_ledger_balances` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `employee_id` int(11) NOT NULL,
  `year_id` int(11) NOT NULL,
  `company_id` int(11) NOT NULL,
  `dr` decimal(12,2) NOT NULL DEFAULT 0.00,
  `cr` decimal(12,2) NOT NULL DEFAULT 0.00,
  `balance` decimal(12,2) NOT NULL DEFAULT 0.00,
  `fixed_imprest` decimal(12,2) NOT NULL DEFAULT 0.00,
  `entry_date` date NOT NULL,
  `remark` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `deleted_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_emp_year_company` (`employee_id`,`year_id`,`company_id`),
  KEY `idx_entry_date` (`entry_date`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci;
```

### Field Descriptions:
- **id**: Primary key, auto-increment
- **employee_id**: Reference to employee ID from HRIMS
- **year_id**: Financial year ID (e.g., 7 for 2026, 6 for 2025)
- **company_id**: Company identifier (default: 1)
- **dr**: Debit amount (advances given to employee)
- **cr**: Credit amount (adjustments/recoveries from employee)
- **balance**: Running balance (dr - cr)
- **fixed_imprest**: Fixed imprest amount allocated to employee
- **entry_date**: Date of the transaction
- **remark**: Description of the transaction
- **created_at**: Record creation timestamp
- **updated_at**: Record last update timestamp
- **deleted_at**: Soft delete timestamp

## API Endpoints

### 1. Finance Pending Requests (Enhanced)
**Endpoint:** `GET /api/advance/finance/pending?year_id={year_id}`

**Enhanced Response Structure:**
```json
{
  "success": true,
  "message": "Pending finance approvals retrieved successfully",
  "data": [
    {
      "id": 5,
      "employee_id": 28,
      "employee_name": "John Doe",
      "employee_code": "85",
      "ledger_balance": {
        "id": 2,
        "employee_id": 28,
        "year_id": 7,
        "company_id": 1,
        "dr": 33000.00,
        "cr": 0.00,
        "balance": 33000.00,
        "fixed_imprest": 50000.00,
        "entry_date": "2026-01-27",
        "remark": "Advance Against Expenses Dec-25",
        "created_at": "2026-01-31T10:23:58.000000Z",
        "updated_at": "2026-01-31T10:23:58.000000Z"
      },
      "ledger_balance_history": [
        {
          "id": 2,
          "employee_id": 28,
          "year_id": 7,
          "company_id": 1,
          "dr": 33000.00,
          "cr": 0.00,
          "balance": 33000.00,
          "entry_date": "2026-01-27",
          "remark": "Advance Against Expenses Dec-25",
          "created_at": "2026-01-31T10:23:58.000000Z",
          "updated_at": "2026-01-31T10:23:58.000000Z"
        }
      ],
      "req_data": { /* ... request data ... */ },
      "activities": [ /* ... activities data ... */ ]
    }
  ]
}
```

### 2. Finance Approved Requests (Enhanced)
**Endpoint:** `GET /api/advance/finance/approved?year_id={year_id}`

**Response:** Same structure as pending, but for approved requests.

### 3. Finance Rejected Requests (Enhanced)
**Endpoint:** `GET /api/advance/finance/rejected?year_id={year_id}`

**Response:** Same structure as pending, but for rejected requests.

### 4. Employee Balance API (New)
**Endpoint:** `GET /api/advance/finance/employee-balance`

**Parameters:**
- `employee_id` (required): Employee ID
- `year_id` (optional): Financial year ID (defaults to current year)
- `company_id` (optional): Company ID (defaults to 1)
- `include_history` (optional): Include balance history (true/false)

**Request Example:**
```
GET /api/advance/finance/employee-balance?employee_id=28&year_id=7&include_history=true
```

**Response:**
```json
{
  "success": true,
  "message": "Employee ledger balance retrieved successfully",
  "data": {
    "employee_id": 28,
    "current_balance": {
      "id": 2,
      "employee_id": 28,
      "year_id": 7,
      "company_id": 1,
      "dr": 33000.00,
      "cr": 0.00,
      "balance": 33000.00,
      "fixed_imprest": 50000.00,
      "entry_date": "2026-01-27",
      "remark": "Advance Against Expenses Dec-25",
      "created_at": "2026-01-31T10:23:58.000000Z",
      "updated_at": "2026-01-31T10:23:58.000000Z"
    }
  }
}
```

### 5. Update Fixed Imprest API (New)
**Endpoint:** `POST /api/advance/finance/update-fixed-imprest`

**Parameters:**
- `employee_id` (required): Employee ID
- `fixed_imprest` (required): New fixed imprest amount
- `year_id` (optional): Financial year ID (defaults to current year)
- `company_id` (optional): Company ID (defaults to 1)
- `remark` (optional): Reason for imprest update

**Request Example:**
```
POST /api/advance/finance/update-fixed-imprest
{
  "employee_id": 28,
  "fixed_imprest": 75000.00,
  "year_id": 7,
  "remark": "Imprest increased due to promotion"
}
```

**Response:**
```json
{
  "success": true,
  "message": "Fixed imprest updated successfully",
  "data": {
    "employee_id": 28,
    "updated_balance": {
      "id": 3,
      "employee_id": 28,
      "year_id": 7,
      "company_id": 1,
      "dr": 0.00,
      "cr": 0.00,
      "balance": 33000.00,
      "fixed_imprest": 75000.00,
      "entry_date": "2026-01-31",
      "remark": "Imprest increased due to promotion",
      "created_at": "2026-01-31T14:30:00.000000Z",
      "updated_at": "2026-01-31T14:30:00.000000Z"
    },
    "message": "Fixed imprest updated successfully"
  }
}
```

## Business Logic

### Year ID Calculation
The system uses a year_id calculation where:
- 2020 = 1
- 2021 = 2
- 2022 = 3
- ...
- 2026 = 7

Formula: `year_id = current_year - 2019`

### Balance Calculation
- **Debit (DR)**: Advances given to employee (increases balance)
- **Credit (CR)**: Adjustments/recoveries from employee (decreases balance)
- **Balance**: Running total (DR - CR)

### Automatic Balance Updates
When finance approves an advance request:
1. System calculates the total approved amount
2. Creates a new ledger entry with:
   - DR = approved amount
   - Balance = previous balance + approved amount
   - Remark = descriptive text with request details

## Controller Methods

### Core Methods Added:

#### 1. `getEmployeeLedgerBalance($employeeId, $yearId, $companyId)`
Retrieves the current ledger balance for an employee.

**Parameters:**
- `$employeeId`: Employee ID
- `$yearId`: Financial year ID (optional)
- `$companyId`: Company ID (default: 1)

**Returns:** Array with balance information or default structure if no record found.

#### 2. `getEmployeeLedgerBalanceHistory($employeeId, $yearId, $companyId, $limit)`
Retrieves balance history for an employee.

**Parameters:**
- `$employeeId`: Employee ID
- `$yearId`: Financial year ID (optional)
- `$companyId`: Company ID (default: 1)
- `$limit`: Number of records to return (default: 10)

**Returns:** Array of balance history records.

#### 3. `updateEmployeeLedgerBalance($employeeId, $amount, $yearId, $companyId, $remark)`
Updates employee ledger balance after finance approval.

**Parameters:**
- `$employeeId`: Employee ID
- `$amount`: Amount to add/subtract
- `$yearId`: Financial year ID (optional)
- `$companyId`: Company ID (default: 1)
- `$remark`: Transaction description

**Returns:** Boolean success indicator.

#### 4. `getEmployeeBalance(Request $request)`
Standalone API endpoint for getting employee balance.

## Integration Points

### Finance Approval Process
When an advance is approved through finance:
1. Activities are updated with finance approval details
2. Employee ledger balance is automatically updated
3. New ledger entry is created with transaction details
4. Balance history is maintained for audit trail

### Response Enhancement
All finance controller methods (pending, approved, rejected) now include:
- `ledger_balance`: Current balance information
- `ledger_balance_history`: Recent balance transactions (last 10)

## Error Handling

### Common Scenarios:
1. **No Balance Record Found**: Returns default structure with zero balance
2. **Database Connection Issues**: Logs error and returns default structure
3. **Invalid Year ID**: Uses current year as fallback
4. **Missing Employee**: Returns error response

### Logging:
- All balance operations are logged for audit purposes
- Errors are logged with detailed context
- Balance updates include transaction details in logs

## Usage Examples

### 1. Check Employee Balance Before Approval
```php
$balance = $this->getEmployeeLedgerBalance(28, 7, 1);
if ($balance['balance'] > 50000) {
    // Employee has high outstanding balance
    // Consider additional approval or limit
}
```

### 2. Update Balance After Approval
```php
$this->updateEmployeeLedgerBalance(
    28, 
    15000, 
    7, 
    1, 
    "Advance approved for Request #123 - Marketing Activity"
);
```

### 3. Get Balance History for Review
```php
$history = $this->getEmployeeLedgerBalanceHistory(28, 7, 1, 20);
// Review last 20 transactions for employee
```

## Security Considerations

1. **Access Control**: Only finance users should access balance information
2. **Data Integrity**: Balance calculations are transaction-safe
3. **Audit Trail**: All balance changes are logged and timestamped
4. **Soft Deletes**: Records are soft-deleted to maintain audit trail

## Performance Considerations

1. **Indexing**: Proper indexes on employee_id, year_id, company_id
2. **Limiting**: Balance history is limited to prevent large responses
3. **Caching**: Consider caching current balances for frequently accessed employees
4. **Pagination**: Large balance histories should be paginated

## Maintenance

### Regular Tasks:
1. **Archive Old Records**: Move old year data to archive tables
2. **Balance Reconciliation**: Periodic balance verification
3. **Index Maintenance**: Monitor and optimize database indexes
4. **Log Cleanup**: Regular cleanup of old log entries

### Monitoring:
1. **Balance Anomalies**: Monitor for unusual balance patterns
2. **Performance Metrics**: Track API response times
3. **Error Rates**: Monitor error rates and patterns
4. **Data Growth**: Monitor table size and growth patterns