# Employee Ledger Balance - Quick Reference

## API Endpoints Summary

| Method | Endpoint | Description |
|--------|----------|-------------|
| GET | `/api/advance/finance/pending?year_id=7` | Get pending finance requests with balance data |
| GET | `/api/advance/finance/approved?year_id=7` | Get approved finance requests with balance data |
| GET | `/api/advance/finance/rejected?year_id=7` | Get rejected finance requests with balance data |
| GET | `/api/advance/finance/employee-balance` | Get employee balance information |
| POST | `/api/advance/finance/update-fixed-imprest` | Update employee fixed imprest amount |

## Quick API Calls

### 1. Get Employee Balance
```bash
curl -X GET "https://your-domain.com/api/advance/finance/employee-balance?employee_id=28&year_id=7&include_history=true"
```

### 2. Get Pending Finance Requests (with balance)
```bash
curl -X GET "https://your-domain.com/api/advance/finance/pending?year_id=7"
```

### 3. Update Fixed Imprest
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

## Response Structure

### Balance Object
```json
{
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
  }
}
```

## Key Methods

### Get Current Balance
```php
$balance = $this->getEmployeeLedgerBalance($employeeId, $yearId, $companyId);
```

### Get Balance History
```php
$history = $this->getEmployeeLedgerBalanceHistory($employeeId, $yearId, $companyId, $limit);
```

### Update Balance (Auto-called on finance approval)
```php
$this->updateEmployeeLedgerBalance($employeeId, $amount, $yearId, $companyId, $remark);
```

### Update Fixed Imprest
```php
$this->updateEmployeeFixedImprest($employeeId, $fixedImprest, $yearId, $companyId, $remark);
```

## Year ID Reference
- 2020 = 1
- 2021 = 2
- 2022 = 3
- 2023 = 4
- 2024 = 5
- 2025 = 6
- 2026 = 7

## Balance Logic
- **DR (Debit)**: Money given to employee (advance payments)
- **CR (Credit)**: Money recovered from employee (adjustments)
- **Balance**: DR - CR (positive = employee owes company)
- **Fixed Imprest**: Fixed amount allocated to employee for regular expenses

## Common Use Cases

### 1. Check if employee has outstanding balance
```php
$balance = $this->getEmployeeLedgerBalance(28, 7);
if ($balance['balance'] > 0) {
    echo "Employee has outstanding balance: " . $balance['balance'];
}
```

### 2. Review recent transactions
```php
$history = $this->getEmployeeLedgerBalanceHistory(28, 7, 1, 5);
foreach ($history as $transaction) {
    echo $transaction['entry_date'] . ": " . $transaction['remark'] . " - " . $transaction['balance'];
}
```

### 3. Update employee fixed imprest
```php
$success = $this->updateEmployeeFixedImprest(28, 75000, 7, 1, "Imprest increased due to promotion");
if ($success) {
    echo "Fixed imprest updated successfully";
}
```

## Error Handling

### No Balance Found
```json
{
  "id": null,
  "employee_id": 28,
  "balance": 0.00,
  "remark": "No balance record found"
}
```

### API Error Response
```json
{
  "success": false,
  "message": "employee_id is required"
}
```

## Integration Notes

1. **Automatic Updates**: Balance is automatically updated when finance approves advances
2. **Transaction Safety**: All balance updates are wrapped in database transactions
3. **Audit Trail**: Every balance change is logged with timestamp and remarks
4. **Soft Deletes**: Records are never hard-deleted to maintain audit trail

## Testing

### Test Employee Balance API
```bash
# Get balance without history
curl "https://your-domain.com/api/advance/finance/employee-balance?employee_id=28&year_id=7"

# Get balance with history
curl "https://your-domain.com/api/advance/finance/employee-balance?employee_id=28&year_id=7&include_history=true"
```

### Test Finance Pending with Balance
```bash
curl "https://your-domain.com/api/advance/finance/pending?year_id=7"
```

### Test Fixed Imprest Update
```bash
curl -X POST "https://your-domain.com/api/advance/finance/update-fixed-imprest" \
  -H "Content-Type: application/json" \
  -d '{
    "employee_id": 28,
    "fixed_imprest": 75000.00,
    "year_id": 7,
    "remark": "Testing imprest update"
  }'
```

## Database Queries

### Check Employee Balance Directly
```sql
SELECT * FROM employee_ledger_balances 
WHERE employee_id = 28 AND year_id = 7 AND company_id = 1 
ORDER BY entry_date DESC LIMIT 1;
```

### Get Balance History
```sql
SELECT * FROM employee_ledger_balances 
WHERE employee_id = 28 AND year_id = 7 AND company_id = 1 
ORDER BY entry_date DESC LIMIT 10;
```

### Check All Employees with Outstanding Balances
```sql
SELECT employee_id, balance, fixed_imprest, entry_date, remark 
FROM employee_ledger_balances 
WHERE year_id = 7 AND balance > 0 
ORDER BY balance DESC;
```

### Check Employee Balance with Fixed Imprest
```sql
SELECT employee_id, balance, fixed_imprest, entry_date, remark 
FROM employee_ledger_balances 
WHERE employee_id = 28 AND year_id = 7 AND company_id = 1 
ORDER BY entry_date DESC LIMIT 1;
```