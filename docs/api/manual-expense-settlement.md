# Manual Expense Settlement API

## Overview
This API allows manual matching of unsettled expense payment records with payment file records when automatic matching fails.

---

## Endpoint

```
POST /api/advance/finance/manual-expense-settlement
```

---

## Authentication
Requires valid user authentication token.

---

## Request Headers

```
Content-Type: application/json
Authorization: Bearer {token}
```

---

## Request Parameters

### Required Parameters

| Parameter | Type | Description |
|-----------|------|-------------|
| `year_id` | integer | Financial year ID (e.g., 2024) |
| `activity_id` | integer | MonthExpenseFinal record ID from `y{year_id}_monthexpensefinal` table |
| `transaction_ref` | string | Transaction reference number from payment file (e.g., "BT26013049926940") |

### Optional Parameters

| Parameter | Type | Description |
|-----------|------|-------------|
| `payment_amount` | decimal | Override payment amount (if different from payment file) |
| `payment_date` | date | Override payment date (format: YYYY-MM-DD) |
| `payment_remark` | string | Custom payment remark |

---

## Request Example

```json
{
  "year_id": 2024,
  "activity_id": 12345,
  "transaction_ref": "BT26013049926940",
  "payment_amount": 5000.00,
  "payment_date": "2024-01-15",
  "payment_remark": "Manual settlement for account mismatch"
}
```

### Minimal Request (Using Payment File Values)

```json
{
  "year_id": 2024,
  "activity_id": 12345,
  "transaction_ref": "BT26013049926940"
}
```

---

## Response

### Success Response (200 OK)

```json
{
  "success": true,
  "message": "Expense payment manually settled successfully",
  "data": {
    "activity_id": 12345,
    "transaction_ref": "BT26013049926940",
    "payment_amount": 5000.00,
    "payment_date": "2024-01-15",
    "employee_id": "EMP001",
    "year_id": 2024
  }
}
```

### Error Responses

#### Missing Required Parameters (400 Bad Request)
```json
{
  "success": false,
  "message": "year_id, activity_id, and transaction_ref are required"
}
```

#### Activity Not Found (400 Bad Request)
```json
{
  "success": false,
  "message": "Expense activity not found"
}
```

#### Invalid Status (400 Bad Request)
```json
{
  "success": false,
  "message": "Activity is not in InstructedPayment status. Current status: ConfirmedPayment"
}
```

#### Payment Record Not Found (400 Bad Request)
```json
{
  "success": false,
  "message": "Payment record not found with transaction reference: BT26013049926940"
}
```

---

## What This API Does

1. **Validates Input**: Checks that all required parameters are provided
2. **Fetches Records**: Gets the expense activity and payment record
3. **Status Check**: Ensures activity is in "InstructedPayment" status
4. **Updates Expense Record**: Marks as "ConfirmedPayment" with payment details
5. **Updates Ledger**: Updates employee ledger balance
6. **Marks Payment**: Updates payment file record as processed and matched
7. **Resolves Unsettled**: Removes from unsettled payments table

---

## Use Cases

### 1. Account Number Mismatch
When payment file has different account number than HRIMS:
```json
{
  "year_id": 2024,
  "activity_id": 12345,
  "transaction_ref": "BT26013049926940"
}
```

### 2. Amount Discrepancy
When payment amount differs slightly from requested amount:
```json
{
  "year_id": 2024,
  "activity_id": 12345,
  "transaction_ref": "BT26013049926940",
  "payment_amount": 4999.50,
  "payment_remark": "Adjusted for bank charges"
}
```

### 3. Date Override
When payment date needs to be corrected:
```json
{
  "year_id": 2024,
  "activity_id": 12345,
  "transaction_ref": "BT26013049926940",
  "payment_date": "2024-01-20"
}
```

---

## Finding Unsettled Records

To get unsettled expense payments, use:

```
GET /api/advance/finance/unsettled-payments?status=Unsettled
```

Response will include:
- `activity_id`: Use this for manual settlement
- `matching_payment_records`: List of potential payment records with `transaction_refno`

---

## Testing the API

### Using cURL

```bash
curl -X POST http://your-domain/api/advance/finance/manual-expense-settlement \
  -H "Content-Type: application/json" \
  -H "Authorization: Bearer YOUR_TOKEN" \
  -d '{
    "year_id": 2024,
    "activity_id": 12345,
    "transaction_ref": "BT26013049926940"
  }'
```

### Using Postman

1. Method: `POST`
2. URL: `http://your-domain/api/advance/finance/manual-expense-settlement`
3. Headers:
   - `Content-Type`: `application/json`
   - `Authorization`: `Bearer YOUR_TOKEN`
4. Body (raw JSON):
```json
{
  "year_id": 2024,
  "activity_id": 12345,
  "transaction_ref": "BT26013049926940"
}
```

---

## Notes

- This API is for **expense payments** only (not advance payments)
- Activity must be in `InstructedPayment` status
- Payment record must exist in `digi_next_payment_file` table
- All updates are wrapped in a database transaction
- Automatically updates employee ledger balance
- Marks unsettled record as resolved
- Adds "(Manual Settlement)" tag to payment remarks

---

## Related APIs

- `GET /api/advance/finance/unsettled-payments` - Get list of unsettled payments
- `POST /api/advance/finance/confirm-expense-payments` - Automatic expense payment confirmation
- `POST /api/advance/finance/resolve-discrepancy` - Manual settlement for advance payments

---

## Error Handling

All errors return JSON with:
```json
{
  "success": false,
  "message": "Error description"
}
```

Common errors:
- Missing parameters
- Activity not found
- Invalid status
- Payment record not found
- Database transaction failure

---

## Support

For issues or questions, contact the development team.
