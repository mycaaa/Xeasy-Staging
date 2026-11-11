# Payment Status Enum Fix

## Issue Description
The release payment API was failing with the following error:
```
SQLSTATE[01000]: Warning: 1265 Data truncated for column 'payment_status' at row 1
```

This occurred when trying to update the `payment_status` field to 'Ready for Payment'.

## Root Cause
The issue was caused by two factors:
1. **String Literals vs Constants**: The controller was using string literals instead of model constants
2. **Enum Definition**: The database enum might not have included all required values

## Fix Applied

### 1. Updated Controller to Use Constants
**File:** `app/Http/Controllers/Api/Advance/AdvanceFinanceController.php`

**Before:**
```php
'payment_status' => 'Ready for Payment'
```

**After:**
```php
'payment_status' => AdvanceRequestActivity::PAYMENT_STATUS_READY
```

### 2. Fixed All Payment Status References
Updated all instances in the controller:
- ✅ `releasePayment()` method - Activity update
- ✅ `releasePayment()` method - Validation check
- ✅ `releasePayment()` method - Response data
- ✅ `releasedPayments()` method - Default status parameter
- ✅ `checkAndUpdateRequestPaymentStatus()` method - Query condition

### 3. Database Enum Fix
**Migration:** `2026_01_31_152000_fix_payment_status_enum_in_advance_request_activities_table.php`

**SQL Applied:**
```sql
ALTER TABLE `advance_request_activities` 
MODIFY `payment_status` ENUM('Pending', 'Ready for Payment', 'Paid', 'Rejected', 'Reverted') 
DEFAULT 'Pending'
```

## Verification

### Database Structure
```json
{
    "Field": "payment_status",
    "Type": "enum('Pending','Ready for Payment','Paid','Rejected','Reverted')",
    "Null": "YES",
    "Key": "MUL",
    "Default": "Pending",
    "Extra": ""
}
```

### Model Constants
```php
// In AdvanceRequestActivity model
const PAYMENT_STATUS_PENDING = 'Pending';
const PAYMENT_STATUS_READY = 'Ready for Payment';
const PAYMENT_STATUS_PAID = 'Paid';
const PAYMENT_STATUS_REJECTED = 'Rejected';
const PAYMENT_STATUS_REVERTED = 'Reverted';
```

## Testing the Fix

### 1. Test Release Payment API
```bash
curl -X POST "https://expensenew.vnrseeds.co.in/api/advance/finance/release-payment" \
  -H "Content-Type: application/json" \
  -d '{
    "user_id": "11",
    "selected_requests": [
      {
        "id": 5,
        "employee_id": 28,
        "advance_request_id": 1,
        "finance_approved_amount": 1000.00
      }
    ]
  }'
```

### 2. Expected Success Response
```json
{
  "success": true,
  "message": "1 request(s) released for payment successfully",
  "data": {
    "released_count": 1,
    "total_amount": 1000.00,
    "payment_batch_id": "PAY_BATCH_20260131_XXX",
    "released_requests": [
      {
        "id": 5,
        "employee_name": "Employee Name",
        "employee_code": "EMP001",
        "activity_name": "Activity Name",
        "amount": 1000.00,
        "payment_status": "Ready for Payment",
        "payment_released_date": "2026-01-31T08:59:19Z"
      }
    ]
  }
}
```

### 3. Database Verification
```sql
-- Check the updated record
SELECT id, payment_status, payment_amount, payment_batch_id, payment_released_date 
FROM advance_request_activities 
WHERE id = 5;

-- Should show:
-- payment_status: 'Ready for Payment'
-- payment_amount: 1000.00
-- payment_batch_id: 'PAY_BATCH_20260131_XXX'
-- payment_released_date: '2026-01-31 08:59:19'
```

## Benefits of Using Constants

### 1. Type Safety
- Prevents typos in status values
- IDE autocompletion support
- Compile-time error detection

### 2. Maintainability
- Single source of truth for status values
- Easy to update across the entire codebase
- Clea