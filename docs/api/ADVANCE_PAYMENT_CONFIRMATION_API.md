# Simple Payment Confirmation API

This API provides a simple way to confirm advance payments by uploading an Excel file that matches payment records with "Ready for Payment" advance requests, and retrieve confirmed payments.

## Overview

The payment confirmation process:
1. Upload Excel file with payment confirmation data
2. System finds advance activities with "Ready for Payment" status
3. Matches employee names and amounts using similarity algorithm
4. Marks matching advance requests as paid
5. Updates request status to closed when all activities are paid

## API Endpoints

### 1. Confirm Payments

**Endpoint:** `POST /api/advance/finance/confirm-payments`

**Description:** Upload Excel file to confirm payments and mark advance requests as paid

**Request:**
```
Content-Type: multipart/form-data

payment_file: Excel file (.xlsx, .xls)
```

**Excel File Format:**
The Excel file should contain 8 columns in this exact order:
1. **Value Date** (DD/MM/YYYY format)
2. **Beneficiary Name** (used for employee matching)
3. **Payment Type** (e.g., NEFT, RTGS)
4. **Amount** (used for exact amount matching)
5. **RBI/UTR Reference Number**
6. **Transaction Refno** (used in payment remarks)
7. **Beneficiary Account Number**
8. **IFSC Code**

**Response:**
```json
{
    "success": true,
    "message": "Payment confirmation processed successfully",
    "data": {
        "total_rows": 100,
        "processed_rows": 95,
        "matched_payments": 80,
        "unsettled_payments": 5,
        "success_rate": 84.21,
        "errors": [
            "Row 5: Missing beneficiary name or invalid amount"
        ]
    }
}
```

### 2. Get Confirmed Payments

**Endpoint:** `GET /api/advance/finance/confirmed-payments`

**Description:** Get list of payments that have been confirmed (marked as "Paid")

**Parameters:**
- `year_id` (optional): Filter by year

**Response:**
```json
{
    "success": true,
    "message": "Confirmed payments retrieved successfully",
    "data": {
        "confirmed_payments": [
            {
                "activity_id": 456,
                "advance_request_id": 123,
                "employee_id": 12345,
                "employee_name": "John Doe",
                "employee_code": "EMP001",
                "department": "Sales",
                "designation": "Manager",
                "advance_type_name": "Travel Advance",
                "activity_type_name": "Field Visit",
                "requested_amount": 5000.00,
                "finance_approved_amount": 4500.00,
                "payment_amount": 4500.00,
                "payment_date": "2026-02-01",
                "payment_remark": "Payment confirmed via Excel upload - Ref: TXN123456",
                "payment_status": "Paid",
                "request_status": "Closed",
                "submission_no": 1,
                "advance_fill_date": "2026-01-15",
                "created_at": "2026-01-15T10:30:00Z",
                "updated_at": "2026-02-01T14:20:00Z"
            }
        ],
        "total_count": 80,
        "total_payment_amount": 360000.00
    }
}
```

### 3. Get Unsettled Payments

**Endpoint:** `GET /api/advance/finance/unsettled-payments`

**Description:** Get list of payments where Transaction Refno matched in `digi_next_payment_file` but amounts didn't match

**Response:**
```json
{
    "success": true,
    "message": "Unsettled payments retrieved successfully",
    "data": {
        "unsettled_payments": [
            {
                "id": 1,
                "transaction_refno": "TXN123456",
                "beneficiary_name": "John Doe",
                "excel_amount": 5000.00,
                "db_amount": 4800.00,
                "amount_difference": 200.00,
                "reason": "Amount mismatch",
                "status": "Unsettled",
                "created_at": "2026-02-01T10:30:00Z",
                "updated_at": "2026-02-01T10:30:00Z"
            }
        ],
        "total_count": 5,
        "total_excel_amount": 25000.00,
        "total_db_amount": 24000.00,
        "total_difference": 1000.00
    }
}
```

## Matching Logic

The system matches payment records with advance requests using:

1. **Transaction Reference Check:** 
   - First checks if Transaction Refno (Column 6) exists in `digi_next_payment_file` table
   - If found, compares amounts between Excel and database
   - If amounts don't match, creates "Unsettled" record

2. **Status Filter:** Only matches activities with `payment_status = 'Ready for Payment'`

3. **Amount Matching:** Exact match between Excel Amount (Column 4) and `finance_approved_amount`

4. **Employee Name Matching:** 
   - Compares Excel Beneficiary Name (Column 2) with employee names from HRIMS
   - Case-insensitive partial matching
   - Levenshtein distance algorithm for similarity (>80% match)
   - Handles name variations and formatting differences

## Unsettled Payments Logic

When Transaction Refno is found in `digi_next_payment_file` but amounts don't match:
- Creates record in `unsettled_payments` table
- Stores both Excel amount and database amount
- Calculates difference for reconciliation
- Marks status as "Unsettled"
- Can be retrieved via separate API for review

## Process Flow

1. **File Upload & Validation**
   - Validate Excel file format and size (max 10MB)
   - Parse Excel data and validate required fields

2. **Payment Matching**
   - Find activities with "Ready for Payment" status
   - Match exact amounts with finance-approved amounts
   - Filter by employee name similarity (>80%)

3. **Mark as Paid**
   - Update matched activities with `payment_status = 'Paid'`
   - Set `payment_amount`, `payment_date`, and `payment_remark`
   - Check if all activities in request are paid

4. **Request Status Update**
   - Update request status to `'Closed'` if all activities are paid

## Usage Examples

### Upload Payment Confirmation File

```bash
curl -X POST "http://your-domain/api/advance/finance/confirm-payments" \
  -H "Content-Type: multipart/form-data" \
  -F "payment_file=@Payment_confirm.xlsx"
```

### Get Confirmed Payments

```bash
curl -X GET "http://your-domain/api/advance/finance/confirmed-payments"
```

### Get Confirmed Payments for Specific Year

```bash
curl -X GET "http://your-domain/api/advance/finance/confirmed-payments?year_id=2026"
```

### Get Unsettled Payments

```bash
curl -X GET "http://your-domain/api/advance/finance/unsettled-payments"
```

## Error Handling

- **Invalid file format:** `422 Unprocessable Entity`
- **Missing required fields:** Row-level error in response
- **No matching advance requests:** Not treated as error, just reported in summary
- **Database transaction failure:** `500 Internal Server Error` with rollback