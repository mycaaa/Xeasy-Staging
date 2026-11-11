# Payment Export API - Quick Reference

## Endpoint
```
GET /api/advance/finance/export-payments
```

## Quick Examples

### Basic Export (All Payments)
```bash
curl -X GET "https://your-domain.com/api/advance/finance/export-payments"
```

### Filter by Employee
```bash
curl -X GET "https://your-domain.com/api/advance/finance/export-payments?employee_id=12345"
```

### Filter by Date Range
```bash
curl -X GET "https://your-domain.com/api/advance/finance/export-payments?from_date=2026-01-01&to_date=2026-01-31"
```

## Response
- **Success**: Excel file download (.xlsx)
- **Error**: JSON with error message

## Excel Format
| DocNo | Date | Time | CashBankAC | TDSJVNo | sNarration | ... | Amount | Reference | Finance Advance Amount |
|-------|------|------|------------|---------|------------|-----|--------|-----------|------------------------|
| ADVPAY/26/0001 | 01/02/2026 | 14:30:00 | CASH001 | | Advance payment for Travel | ... | 5000.00 | ADV-456 | 5000.00 |

## Key Features
- ✅ Clean, simplified export structure
- ✅ Removed unused code for better performance
- ✅ Follows payment data format reference
- ✅ Exports activities with "Ready for Payment" status
- ✅ Auto-generated document numbers
- ✅ Formatted employee codes (E0123)
- ✅ Multiple filter options
- ✅ Professional Excel styling

## Files Created
1. `app/Exports/PaymentExport.php` - Main export class
2. `app/Exports/PaymentExportSheet.php` - Sheet implementation
3. `app/Http/Controllers/Api/Advance/AdvanceFinanceController.php` - Added exportPayments method
4. `routes/advance.php` - Added export route
5. `docs/PAYMENT_EXPORT_API.md` - Full documentation

## Status: ✅ COMPLETED & FIXED

### Recent Fix (February 2026)
- **Issue Fixed**: "Call to undefined relationship [request] on model [App\\Models\\AdvanceRequestEmployee]"
- **Solution**: Refactored exportPayments method to use Eloquent ORM with proper relationships
- **Changes Made**:
  - Replaced complex DB query with `AdvanceRequestActivity::with(['employee.advanceRequest', 'activityType'])`
  - Fixed relationship access from `employee.request` to `employee.advanceRequest`
  - Added proper error handling and data validation
  - Fixed missing 32nd column "Finance Advance Amount"
- **Status**: ✅ Export functionality now working correctly
- **Verified**: Generates Excel files successfully with all 32 columns and proper data