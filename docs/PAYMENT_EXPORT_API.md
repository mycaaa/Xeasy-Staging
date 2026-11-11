# Payment Export API

## Overview
The Payment Export API allows users to export payment data to Excel format. This API provides a clean, simplified export functionality for advance payment records.

## Endpoint
```
GET /api/advance/finance/export-payments
```

## Parameters
All parameters are optional and can be used to filter the export data:

| Parameter | Type | Description | Example |
|-----------|------|-------------|---------|
| `employee_id` | string | Filter by specific employee ID | `12345` |
| `request_id` | integer | Filter by specific advance request ID | `123` |
| `batch_id` | string | Filter by payment batch ID | `BATCH001` |
| `from_date` | date | Filter payments from this date (YYYY-MM-DD) | `2026-01-01` |
| `to_date` | date | Filter payments to this date (YYYY-MM-DD) | `2026-01-31` |
| `status` | string | Payment status (default: 'Ready for Payment') | `Ready for Payment` |

## Example Requests

### Export All Payments
```bash
GET /api/advance/finance/export-payments
```

### Export Payments for Specific Employee
```bash
GET /api/advance/finance/export-payments?employee_id=12345
```

### Export Payments for Date Range
```bash
GET /api/advance/finance/export-payments?from_date=2026-01-01&to_date=2026-01-31
```

### Export Payments for Specific Batch
```bash
GET /api/advance/finance/export-payments?batch_id=BATCH001
```

## Response
The API returns an Excel file (.xlsx) with the following columns:

| Column | Description |
|--------|-------------|
| DocNo | Document number (auto-generated: ADVPAY/YY/0001) |
| Date | Finance approved date |
| Time | Finance approved time |
| CashBankAC | Cash/Bank account code |
| TDSJVNo | TDS Journal Voucher Number (empty) |
| sNarration | Payment description/narration |
| sChequeNo | Cheque number (empty) |
| TransactiontypeCode | Transaction type (ADV for Advance) |
| Activity | Activity name |
| Category | Payment category |
| Region | Employee region |
| Crop | Crop details |
| Farm | Farm details (empty) |
| Business Entity | Business entity code (120) |
| Cost Center | Cost center/state |
| Department | Employee department |
| PMT Category | Payment category (ADVANCE) |
| Business Unit | Business unit |
| Location | Employee location |
| State | State name |
| Function | Employee function |
| FC-Vertical | Employee vertical |
| Sub Department | Sub department |
| Zone | Zone (empty) |
| Account | Employee account code (E0123) |
| Amount | Payment amount |
| Reference | Payment reference |
| sRemarks | Payment remarks |
| TDSBillAmount | TDS bill amount (0) |
| TDS | TDS amount (0) |
| sBRSUser | BRS user (empty) |
| Finance Advance Amount | Final advance amount |

## Export Format
- **File Type**: Excel (.xlsx)
- **File Name**: `payment_export_YYYY_MM_DD_HH_MM_SS.xlsx`
- **Sheet Name**: "Payment Export"
- **Headers**: Styled with blue background and white text
- **Data**: Alternating row colors for better readability

## Error Responses

### No Data Found
```json
{
    "success": false,
    "message": "No payment data found for export with Ready for Payment status"
}
```

### General Error
```json
{
    "success": false,
    "message": "Failed to export payment data: [error details]"
}
```

## Data Source
The export includes:
- Advance request activities with `finance_status = 'approved'`
- Activities with `payment_status = 'Ready for Payment'`
- Employee details and activity information
- Payment amounts and references

## Usage Notes
1. Only activities with "Ready for Payment" status are included in the export
2. Employee codes are formatted as 'E' + padded 4-digit code (e.g., E0123)
3. Document numbers are auto-generated in format: PAY/YY/0001
4. All filters can be combined for precise data selection
5. Large exports may take some time to process

## Implementation Details
- Uses Laravel Excel package for export functionality
- Implements clean, simplified export structure
- Removes unused code for better performance
- Follows the provided payment data format reference

## Recent Updates (February 2026)

### Fixed Relationship Error
- **Issue**: "Call to undefined relationship [request] on model [App\\Models\\AdvanceRequestEmployee]"
- **Solution**: Refactored exportPayments method to use Eloquent ORM with proper relationships instead of complex DB query
- **Changes**: 
  - Uses `AdvanceRequestActivity::with(['employee.advanceRequest', 'activityType'])` for proper relationship loading
  - Replaced complex joins with Eloquent relationships to avoid relationship errors
  - Added proper error handling for missing relationships

### Enhanced Data Retrieval
- **Improvement**: Better employee data retrieval using existing `getEmployeeDetails()` helper method
- **Benefits**: More comprehensive employee information including organizational hierarchy
- **Data Sources**: Properly handles HRIMS database connections for employee details

### Fixed Column Structure
- **Issue**: Missing 32nd column in export
- **Solution**: Added "Finance Advance Amount" as the 32nd column in both headers and data arrays
- **Verification**: Export now correctly includes all 32 required columns as specified

### Testing Verified
- Export functionality tested and confirmed working
- Generates Excel files successfully with proper data structure
- All relationships load correctly without errors
- File naming follows pattern: `payment_export_YYYY_MM_DD_HH_MM_SS.xlsx`