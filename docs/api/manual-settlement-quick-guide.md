# Manual Settlement Quick Guide

## Quick API Reference

### Expense Payment Manual Settlement

**Endpoint:** `POST /api/advance/finance/manual-expense-settlement`

**Minimal Request:**
```json
{
  "year_id": 2024,
  "activity_id": 12345,
  "transaction_ref": "BT26013049926940"
}
```

**Full Request:**
```json
{
  "year_id": 2024,
  "activity_id": 12345,
  "transaction_ref": "BT26013049926940",
  "payment_amount": 5000.00,
  "payment_date": "2024-01-15",
  "payment_remark": "Custom remark"
}
```

---

### Advance Payment Manual Settlement

**Endpoint:** `POST /api/advance/finance/resolve-discrepancy`

**Request:**
```json
{
  "activity_id": 12345,
  "transaction_ref": "BT26013049926940",
  "payment_amount": 5000.00,
  "payment_date": "2024-01-15",
  "payment_remark": "Custom remark"
}
```

---

## Workflow

### Step 1: Get Unsettled Payments
```
GET /api/advance/finance/unsettled-payments?status=Unsettled
```

### Step 2: Identify Match
From response, find:
- `activity_id` (expense record ID)
- `matching_payment_records[].transaction_refno` (payment reference)

### Step 3: Manual Settlement
```json
POST /api/advance/finance/manual-expense-settlement
{
  "year_id": 2024,
  "activity_id": {from_step_2},
  "transaction_ref": {from_step_2}
}
```

---

## Common Scenarios

### Scenario 1: Account Mismatch
**Problem:** Payment file has different account number
**Solution:** Use basic request with correct transaction_ref

### Scenario 2: Amount Difference
**Problem:** Payment amount differs from requested
**Solution:** Add `payment_amount` parameter

### Scenario 3: Wrong Date
**Problem:** Payment date needs correction
**Solution:** Add `payment_date` parameter

---

## Response Codes

| Code | Meaning |
|------|---------|
| 200 | Success |
| 400 | Bad Request (missing params, invalid status) |
| 500 | Server Error |

---

## Key Differences

### Expense vs Advance Settlement

| Feature | Expense | Advance |
|---------|---------|---------|
| Endpoint | `/manual-expense-settlement` | `/resolve-discrepancy` |
| Required | `year_id`, `activity_id`, `transaction_ref` | `activity_id`, `transaction_ref` |
| Table | `y{year_id}_monthexpensefinal` | `advance_request_activities` |
| Status | `InstructedPayment` → `ConfirmedPayment` | `Ready` → `Paid` |

---

## Testing Commands

### cURL - Expense
```bash
curl -X POST http://localhost/api/advance/finance/manual-expense-settlement \
  -H "Content-Type: application/json" \
  -d '{"year_id":2024,"activity_id":12345,"transaction_ref":"BT26013049926940"}'
```

### cURL - Advance
```bash
curl -X POST http://localhost/api/advance/finance/resolve-discrepancy \
  -H "Content-Type: application/json" \
  -d '{"activity_id":12345,"transaction_ref":"BT26013049926940"}'
```

---

## Troubleshooting

### "Activity not found"
- Check `year_id` is correct
- Verify `activity_id` exists in `y{year_id}_monthexpensefinal`

### "Invalid status"
- Activity must be in `InstructedPayment` status
- Check current status in database

### "Payment record not found"
- Verify `transaction_ref` exists in `digi_next_payment_file`
- Check spelling and case sensitivity

---

## Best Practices

1. **Always check unsettled list first** - Don't guess activity IDs
2. **Use minimal request** - Let system use payment file values
3. **Override only when needed** - Use optional params sparingly
4. **Verify before settling** - Check amounts match expectations
5. **Document reason** - Use `payment_remark` for audit trail

---

## Support Queries

### Get all unsettled for specific employee
```
GET /api/advance/finance/unsettled-payments?status=Unsettled&employee_id=EMP001
```

### Check payment sync status
```
GET /api/advance/finance/payment-sync-status
```

### View confirmed payments
```
GET /api/advance/finance/confirmed-payments?year_id=2024
```
