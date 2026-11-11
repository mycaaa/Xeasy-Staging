# Employee Ledger Balance - Complete Usage Guide

## Overview
This comprehensive guide covers all aspects of the Employee Ledger Balance system, including the new Fixed Imprest functionality. The system tracks employee advance payments, adjustments, and fixed imprest allocations through a dedicated ledger balance table.

## Table of Contents
1. [Database Setup](#database-setup)
2. [API Endpoints](#api-endpoints)
3. [Usage Examples](#usage-examples)
4. [Fixed Imprest Management](#fixed-imprest-management)
5. [Integration with Finance Approval](#integration-with-finance-approval)
6. [Testing Guide](#testing-guide)
7. [Troubleshooting](#troubleshooting)

## Database Setup

### Required Migration
First, ensure the `fixed_imprest` column is added to the `employee_ledger_balances` table:

```sql
-- Migration: Add fixed_imprest column to employee_ledger_balances table
ALTER TABLE `employee_ledger_balances` 
ADD COLUMN `fixed_imprest` decimal(12,2) NOT NULL DEFAULT 0.00 
AFTER `balance`;
```

### Complete Table Structure
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

## API Endpoints

### 1. Get Employee Balance
**Endpoint:** `GET /api/advance/finance/employee-balance`

**Parameters:**
- `employee_id` (required): Employee ID
- `year_id` (optional): Financial year ID
- `company_id` (optional): Company ID (default: 1)
- `include_history` (optional): Include balance history (true/false)

**Example Request:**
```bash
curl -X GET "https://your-domain.com/api/advance/finance/employee-balance?employee_id=28&year_id=7&include_history=true"
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

### 2. Update Fixed Imprest
**Endpoint:** `POST /api/advance/finance/update-fixed-imprest`

**Parameters:**
- `employee_id` (required): Employee ID
- `fixed_imprest` (required): New fixed imprest amount
- `year_id` (optional): Financial year ID
- `company_id` (optional): Company ID (default: 1)
- `remark` (optional): Reason for imprest update

**Example Request:**
```bash
curl -X POST "https://your-domain.com/api/advance/finance/update-fixed-imprest" \
  -H "Content-Type: application/json" \
  -d '{
    "employee_id": 28,
    "fixed_imprest": 75000.00,
    "year_id": 7,
    "remark": "Imprest increased due to promotion"
  }'
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

### 3. Finance Pending Requests (Enhanced with Balance Data)
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
        "remark": "Advance Against Expenses Dec-25"
      },
      "req_data": { /* ... request data ... */ },
      "activities": [ /* ... activities data ... */ ]
    }
  ]
}
```

### 4. Finance Approved Requests (Enhanced)
**Endpoint:** `GET /api/advance/finance/approved?year_id={year_id}`

Same structure as pending, but for approved requests.

### 5. Finance Rejected Requests (Enhanced)
**Endpoint:** `GET /api/advance/finance/rejected?year_id={year_id}`

Same structure as pending, but for rejected requests.

## Usage Examples

### 1. Check Employee Balance Before Approval

```javascript
// Frontend JavaScript example
async function checkEmployeeBalance(employeeId, yearId = 7) {
  try {
    const response = await fetch(`/api/advance/finance/employee-balance?employee_id=${employeeId}&year_id=${yearId}`);
    const data = await response.json();
    
    if (data.success) {
      const balance = data.data.current_balance;
      console.log(`Employee ${employeeId} Balance:`, balance.balance);
      console.log(`Fixed Imprest:`, balance.fixed_imprest);
      
      // Check if employee has high outstanding balance
      if (balance.balance > balance.fixed_imprest) {
        alert('Warning: Employee has outstanding balance exceeding fixed imprest!');
      }
      
      return balance;
    } else {
      console.error('Error:', data.message);
    }
  } catch (error) {
    console.error('API Error:', error);
  }
}

// Usage
checkEmployeeBalance(28, 7);
```

### 2. Update Employee Fixed Imprest

```javascript
// Frontend JavaScript example
async function updateFixedImprest(employeeId, newImprest, remark, yearId = 7) {
  try {
    const response = await fetch('/api/advance/finance/update-fixed-imprest', {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
      },
      body: JSON.stringify({
        employee_id: employeeId,
        fixed_imprest: newImprest,
        year_id: yearId,
        remark: remark
      })
    });
    
    const data = await response.json();
    
    if (data.success) {
      console.log('Fixed imprest updated successfully');
      console.log('New balance:', data.data.updated_balance);
      return data.data.updated_balance;
    } else {
      console.error('Error:', data.message);
    }
  } catch (error) {
    console.error('API Error:', error);
  }
}

// Usage
updateFixedImprest(28, 75000, 'Imprest increased due to promotion', 7);
```

### 3. Get Pending Finance Requests with Balance Information

```javascript
// Frontend JavaScript example
async function getPendingFinanceRequests(yearId = 7) {
  try {
    const response = await fetch(`/api/advance/finance/pending?year_id=${yearId}`);
    const data = await response.json();
    
    if (data.success) {
      data.data.forEach(employee => {
        console.log(`Employee: ${employee.employee_name}`);
        console.log(`Current Balance: ${employee.ledger_balance.balance}`);
        console.log(`Fixed Imprest: ${employee.ledger_balance.fixed_imprest}`);
        console.log(`Available Imprest: ${employee.ledger_balance.fixed_imprest - employee.ledger_balance.balance}`);
        console.log('---');
      });
      
      return data.data;
    } else {
      console.error('Error:', data.message);
    }
  } catch (error) {
    console.error('API Error:', error);
  }
}

// Usage
getPendingFinanceRequests(7);
```

## Fixed Imprest Management

### Understanding Fixed Imprest
- **Fixed Imprest**: A predetermined amount allocated to an employee for regular business expenses
- **Available Imprest**: Fixed Imprest - Current Balance (amount employee can still request)
- **Outstanding Balance**: Current balance that employee owes to the company

### Common Fixed Imprest Operations

#### 1. Set Initial Fixed Imprest for New Employee
```bash
curl -X POST "/api/advance/finance/update-fixed-imprest" \
  -H "Content-Type: application/json" \
  -d '{
    "employee_id": 123,
    "fixed_imprest": 50000.00,
    "year_id": 7,
    "remark": "Initial fixed imprest allocation for new employee"
  }'
```

#### 2. Increase Fixed Imprest (Promotion/Role Change)
```bash
curl -X POST "/api/advance/finance/update-fixed-imprest" \
  -H "Content-Type: application/json" \
  -d '{
    "employee_id": 28,
    "fixed_imprest": 100000.00,
    "year_id": 7,
    "remark": "Imprest increased due to promotion to Senior Manager"
  }'
```

#### 3. Decrease Fixed Imprest (Role Change/Policy Update)
```bash
curl -X POST "/api/advance/finance/update-fixed-imprest" \
  -H "Content-Type: application/json" \
  -d '{
    "employee_id": 28,
    "fixed_imprest": 25000.00,
    "year_id": 7,
    "remark": "Imprest reduced due to role change"
  }'
```

#### 4. Reset Fixed Imprest to Zero (Employee Leaving)
```bash
curl -X POST "/api/advance/finance/update-fixed-imprest" \
  -H "Content-Type: application/json" \
  -d '{
    "employee_id": 28,
    "fixed_imprest": 0.00,
    "year_id": 7,
    "remark": "Employee leaving - imprest reset to zero"
  }'
```

### Fixed Imprest Business Rules

1. **Approval Limits**: Employees should not receive advances exceeding their available imprest
2. **Balance Monitoring**: Regular monitoring of employees with high outstanding balances
3. **Annual Reset**: Consider resetting imprest amounts at the start of each financial year
4. **Role-Based Imprest**: Different roles should have different imprest amounts

## Integration with Finance Approval

### Automatic Balance Updates
When finance approves an advance request, the system automatically:

1. **Updates Employee Balance**: Adds the approved amount to employee's outstanding balance
2. **Creates Ledger Entry**: Records the transaction with details
3. **Maintains Fixed Imprest**: Preserves the existing fixed imprest amount
4. **Logs Transaction**: Creates audit trail with remarks

### Example Finance Approval Flow
```php
// When finance approves an advance (automatic in controller)
$this->updateEmployeeLedgerBalance(
    $employeeId,           // 28
    $approvedAmount,       // 15000.00
    $yearId,              // 7
    $companyId,           // 1
    $remark               // "Advance approved for Request #123"
);
```

This creates a new ledger entry:
```json
{
  "employee_id": 28,
  "year_id": 7,
  "company_id": 1,
  "dr": 15000.00,
  "cr": 0.00,
  "balance": 48000.00,  // Previous balance (33000) + new advance (15000)
  "fixed_imprest": 50000.00,  // Unchanged
  "entry_date": "2026-01-31",
  "remark": "Advance approved for Request #123"
}
```

## Testing Guide

### 1. Test Employee Balance API

#### Basic Balance Check
```bash
# Test getting employee balance
curl -X GET "http://localhost:8000/api/advance/finance/employee-balance?employee_id=28&year_id=7"
```

#### Balance with History
```bash
# Test getting employee balance with history
curl -X GET "http://localhost:8000/api/advance/finance/employee-balance?employee_id=28&year_id=7&include_history=true"
```

### 2. Test Fixed Imprest Updates

#### Valid Update
```bash
curl -X POST "http://localhost:8000/api/advance/finance/update-fixed-imprest" \
  -H "Content-Type: application/json" \
  -d '{
    "employee_id": 28,
    "fixed_imprest": 75000.00,
    "year_id": 7,
    "remark": "Testing imprest update"
  }'
```

#### Error Cases
```bash
# Missing employee_id
curl -X POST "http://localhost:8000/api/advance/finance/update-fixed-imprest" \
  -H "Content-Type: application/json" \
  -d '{
    "fixed_imprest": 75000.00
  }'

# Negative imprest
curl -X POST "http://localhost:8000/api/advance/finance/update-fixed-imprest" \
  -H "Content-Type: application/json" \
  -d '{
    "employee_id": 28,
    "fixed_imprest": -1000.00
  }'
```

### 3. Test Finance Requests with Balance Data

```bash
# Test pending requests
curl -X GET "http://localhost:8000/api/advance/finance/pending?year_id=7"

# Test approved requests
curl -X GET "http://localhost:8000/api/advance/finance/approved?year_id=7"

# Test rejected requests
curl -X GET "http://localhost:8000/api/advance/finance/rejected?year_id=7"
```

### 4. Database Verification

#### Check Balance After Update
```sql
SELECT * FROM employee_ledger_balances 
WHERE employee_id = 28 AND year_id = 7 
ORDER BY created_at DESC LIMIT 5;
```

#### Verify Fixed Imprest Changes
```sql
SELECT employee_id, fixed_imprest, entry_date, remark, created_at 
FROM employee_ledger_balances 
WHERE employee_id = 28 AND year_id = 7 
ORDER BY created_at DESC;
```

## Troubleshooting

### Common Issues and Solutions

#### 1. "employee_id is required" Error
**Problem**: Missing employee_id parameter in API request
**Solution**: Ensure employee_id is included in all requests
```bash
# Wrong
curl -X GET "/api/advance/finance/employee-balance?year_id=7"

# Correct
curl -X GET "/api/advance/finance/employee-balance?employee_id=28&year_id=7"
```

#### 2. "fixed_imprest cannot be negative" Error
**Problem**: Trying to set negative fixed imprest
**Solution**: Use 0 or positive values only
```json
// Wrong
{"fixed_imprest": -1000.00}

// Correct
{"fixed_imprest": 0.00}
```

#### 3. No Balance Record Found
**Problem**: Employee has no balance history
**Solution**: System returns default structure with zero balance
```json
{
  "id": null,
  "employee_id": 28,
  "balance": 0.00,
  "fixed_imprest": 0.00,
  "remark": "No balance record found"
}
```

#### 4. Database Connection Issues
**Problem**: Cannot connect to database
**Solution**: Check database configuration and connection
- Verify database credentials in `.env`
- Ensure database server is running
- Check network connectivity

#### 5. Year ID Confusion
**Problem**: Wrong year_id causing no data
**Solution**: Use correct year_id calculation
```
Current Year - 2019 = year_id
2026 - 2019 = 7
2025 - 2019 = 6
```

### Debugging Tips

#### 1. Enable Detailed Logging
Add logging to track API calls:
```php
\Log::info('Employee balance request', [
    'employee_id' => $employeeId,
    'year_id' => $yearId,
    'company_id' => $companyId
]);
```

#### 2. Check Database Directly
```sql
-- Check if employee exists in balance table
SELECT COUNT(*) FROM employee_ledger_balances WHERE employee_id = 28;

-- Check latest balance entry
SELECT * FROM employee_ledger_balances 
WHERE employee_id = 28 AND year_id = 7 
ORDER BY created_at DESC LIMIT 1;
```

#### 3. Verify API Response Format
Use browser developer tools or Postman to inspect full API responses and identify issues.

#### 4. Test with Known Good Data
Start with employees that definitely have balance records to verify API functionality.

## Best Practices

### 1. Regular Balance Monitoring
- Monitor employees with high outstanding balances
- Set up alerts for balances exceeding fixed imprest
- Regular reconciliation of balance records

### 2. Fixed Imprest Management
- Review and update imprest amounts annually
- Align imprest amounts with employee roles and responsibilities
- Document all imprest changes with proper remarks

### 3. API Usage
- Always include proper error handling in frontend code
- Cache balance data appropriately to reduce API calls
- Use pagination for large balance history requests

### 4. Security
- Implement proper authentication for balance APIs
- Log all balance-related operations for audit
- Restrict access to balance information based on user roles

### 5. Performance
- Use appropriate indexes on database tables
- Limit balance history requests to reasonable amounts
- Consider caching frequently accessed balance data

## Conclusion

The Employee Ledger Balance system with Fixed Imprest functionality provides comprehensive tracking of employee advance payments and imprest allocations. This guide covers all aspects of implementation, usage, and maintenance to ensure successful deployment and operation of the system.

For additional support or questions, refer to the main documentation files:
- `EMPLOYEE_LEDGER_BALANCE.md` - Detailed technical documentation
- `EMPLOYEE_LEDGER_BALANCE_QUICK_REFERENCE.md` - Quick reference guide