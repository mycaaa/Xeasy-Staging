# Employee Ledger Balance - Usage Examples

## Scenario 1: Finance Team Reviewing Pending Advances

### Situation
Finance team wants to review all pending advance requests and see each employee's current outstanding balance before making approval decisions.

### API Call
```bash
GET /api/advance/finance/pending?year_id=7
```

### Response Analysis
```json
{
  "success": true,
  "data": [
    {
      "employee_id": 28,
      "employee_name": "Nandkishore Sharma",
      "ledger_balance": {
        "balance": 33000.00,
        "entry_date": "2026-01-27",
        "remark": "Advance Against Expenses Dec-25"
      },
      "activities": [
        {
          "amount": "1000.00",
          "purpose": "Group Retailer Meeting"
        }
      ]
    }
  ]
}
```

### Decision Making
- Employee has ₹33,000 outstanding balance
- Requesting additional ₹1,000 for meeting
- Total would be ₹34,000 if approved
- Finance can decide based on company policy limits

## Scenario 2: Checking Individual Employee Balance

### Situation
HR wants to check a specific employee's advance balance and transaction history before processing a new request.

### API Call
```bash
GET /api/advance/finance/employee-balance?employee_id=28&year_id=7&include_history=true
```

### Response
```json
{
  "success": true,
  "data": {
    "employee_id": 28,
    "current_balance": {
      "balance": 33000.00,
      "entry_date": "2026-01-27",
      "remark": "Advance Against Expenses Dec-25"
    },
    "balance_history": [
      {
        "dr": 33000.00,
        "cr": 0.00,
        "balance": 33000.00,
        "entry_date": "2026-01-27",
        "remark": "Advance Against Expenses Dec-25"
      },
      {
        "dr": 25000.00,
        "cr": 0.00,
        "balance": 25000.00,
        "entry_date": "2026-01-15",
        "remark": "Initial advance for January activities"
      }
    ]
  }
}
```

### Analysis
- Employee received ₹25,000 on Jan 15
- Additional ₹8,000 on Jan 27 (33,000 - 25,000)
- No recoveries/adjustments yet (CR = 0)
- Pattern shows regular advance requests

## Scenario 3: Finance Approval Process

### Situation
Finance approves an advance request and the system automatically updates the ledger balance.

### Before Approval
```json
{
  "employee_id": 28,
  "current_balance": 33000.00
}
```

### Finance Approval API Call
```bash
POST /api/advance/finance/approve
{
  "employee_id": 28,
  "request_id": 4,
  "finance_approved_amount": 1000.00,
  "finance_remark": "Approved for retailer meeting"
}
```

### After Approval (Automatic Balance Update)
```json
{
  "employee_id": 28,
  "current_balance": 34000.00,
  "latest_entry": {
    "dr": 1000.00,
    "cr": 0.00,
    "balance": 34000.00,
    "entry_date": "2026-01-31",
    "remark": "Advance approved for Nandkishore Sharma - Request #4 - Amount: 1000"
  }
}
```

## Scenario 4: Monthly Reconciliation

### Situation
Finance team wants to see all employees with outstanding balances for monthly reconciliation.

### Database Query
```sql
SELECT 
    elb.employee_id,
    elb.balance,
    elb.entry_date,
    elb.remark,
    heg.EmployeeName,
    heg.EC as EmployeeCode
FROM employee_ledger_balances elb
LEFT JOIN hrims.hrm_employee_general heg ON elb.employee_id = heg.EmployeeID
WHERE elb.year_id = 7 
AND elb.balance > 0 
AND elb.deleted_at IS NULL
ORDER BY elb.balance DESC;
```

### Expected Results
```
| employee_id | balance  | employee_name      | employee_code |
|-------------|----------|-------------------|---------------|
| 46          | 90000.00 | John Smith        | 123           |
| 28          | 34000.00 | Nandkishore Sharma| 85            |
| 52          | 15000.00 | Jane Doe          | 456           |
```

## Scenario 5: Expense Adjustment

### Situation
Employee submits expense claims that need to be adjusted against their advance balance.

### Current Balance
```json
{
  "employee_id": 28,
  "balance": 34000.00
}
```

### Expense Claim Processing
When expenses are processed, a credit entry should be made:

```php
// This would be called from expense processing system
$this->updateEmployeeLedgerBalance(
    28,           // employee_id
    -25000,       // negative amount for credit
    7,            // year_id
    1,            // company_id
    "Expense adjustment for December 2025 claims - Amount: 25000"
);
```

### After Adjustment
```json
{
  "employee_id": 28,
  "balance": 9000.00,
  "latest_entries": [
    {
      "dr": 0.00,
      "cr": 25000.00,
      "balance": 9000.00,
      "entry_date": "2026-02-01",
      "remark": "Expense adjustment for December 2025 claims - Amount: 25000"
    },
    {
      "dr": 1000.00,
      "cr": 0.00,
      "balance": 34000.00,
      "entry_date": "2026-01-31",
      "remark": "Advance approved for Request #4"
    }
  ]
}
```

## Scenario 6: Error Handling

### Invalid Employee ID
```bash
GET /api/advance/finance/employee-balance?employee_id=999999&year_id=7
```

### Response
```json
{
  "success": true,
  "data": {
    "employee_id": 999999,
    "current_balance": {
      "id": null,
      "employee_id": 999999,
      "balance": 0.00,
      "remark": "No balance record found"
    }
  }
}
```

### Missing Required Parameter
```bash
GET /api/advance/finance/employee-balance?year_id=7
```

### Response
```json
{
  "success": false,
  "message": "employee_id is required"
}
```

## Scenario 7: Year-End Processing

### Situation
At year-end, finance needs to carry forward balances to the new year.

### Get All Outstanding Balances for Current Year
```sql
SELECT employee_id, balance, remark 
FROM employee_ledger_balances 
WHERE year_id = 7 AND balance > 0 AND deleted_at IS NULL
ORDER BY employee_id;
```

### Create Carry Forward Entries for New Year
```php
foreach ($outstandingBalances as $balance) {
    // Create opening balance for new year (year_id = 8)
    DB::table('employee_ledger_balances')->insert([
        'employee_id' => $balance->employee_id,
        'year_id' => 8, // New year
        'company_id' => 1,
        'dr' => $balance->balance,
        'cr' => 0,
        'balance' => $balance->balance,
        'entry_date' => '2027-04-01', // New financial year start
        'remark' => 'Opening balance carried forward from FY 2026-27',
        'created_at' => now(),
        'updated_at' => now()
    ]);
}
```

## Scenario 8: Audit Trail Review

### Situation
Auditors want to review all transactions for a specific employee.

### API Call for Complete History
```bash
GET /api/advance/finance/employee-balance?employee_id=28&year_id=7&include_history=true
```

### Database Query for Detailed Audit
```sql
SELECT 
    elb.*,
    CASE 
        WHEN elb.dr > 0 THEN 'Advance Payment'
        WHEN elb.cr > 0 THEN 'Expense Adjustment'
        ELSE 'Other'
    END as transaction_type
FROM employee_ledger_balances elb
WHERE elb.employee_id = 28 
AND elb.year_id = 7 
AND elb.deleted_at IS NULL
ORDER BY elb.entry_date ASC, elb.created_at ASC;
```

### Audit Report Format
```
Employee: Nandkishore Sharma (ID: 28)
Year: 2026-27 (Year ID: 7)

Date       | Type              | DR      | CR      | Balance | Remark
-----------|-------------------|---------|---------|---------|------------------
2026-01-15 | Advance Payment   | 25000   | 0       | 25000   | Initial advance
2026-01-27 | Advance Payment   | 8000    | 0       | 33000   | Additional advance
2026-01-31 | Advance Payment   | 1000    | 0       | 34000   | Meeting expenses
2026-02-01 | Expense Adjustment| 0       | 25000   | 9000    | December claims
-----------|-------------------|---------|---------|---------|------------------
Total      |                   | 34000   | 25000   | 9000    | Final Balance
```

## Best Practices

### 1. Regular Balance Monitoring
- Check employee balances before approving new advances
- Set up alerts for high outstanding balances
- Review balance trends monthly

### 2. Proper Documentation
- Always include descriptive remarks for each transaction
- Reference original request/document numbers
- Include employee names in remarks for clarity

### 3. Error Prevention
- Validate employee IDs before processing
- Check for negative balances (shouldn't happen normally)
- Implement approval limits based on current balance

### 4. Performance Optimization
- Use indexed queries for balance lookups
- Limit history results to prevent large responses
- Cache frequently accessed balances

### 5. Security Measures
- Restrict balance access to authorized personnel only
- Log all balance queries and modifications
- Implement proper authentication and authorization