# Payment API Documentation

## Base URL
```
{your-domain}/api/payments
```

## Authentication
Currently, these APIs don't require authentication. Add authentication middleware as needed.

## API Endpoints

### 1. Import Payment File
**POST** `/api/payments/import`

Import payment data from Excel/CSV file.

#### Request
- **Content-Type**: `multipart/form-data`
- **Parameters**:
  - `payment_file` (required): Excel (.xlsx, .xls) or CSV file
  - `source_app` (optional): String to identify source application

#### Response
```json
{
    "success": true,
    "message": "Import completed successfully",
    "data": {
        "total_rows": 100,
        "inserted_rows": 95,
        "skipped_rows": 3,
        "failed_rows": [
            {
                "row": 15,
                "reason": "Field 'amount' is required"
            }
        ],
        "import_batch_id": "550e8400-e29b-41d4-a716-446655440000",
        "source_app": "External App"
    }
}
```

#### Error Response
```json
{
    "success": false,
    "message": "Validation failed",
    "errors": {
        "payment_file": ["The payment file field is required."]
    }
}
```

### 2. Get Payment List
**GET** `/api/payments/list`

Get paginated list of payments with comprehensive filtering options.

#### Query Parameters
- `per_page` (optional): Number of records per page (default: 25)
- `page` (optional): Page number (default: 1)
- `search` (optional): Search term for transaction ref, beneficiary name, account number, bank name, or RBI/UTR reference
- `sort_by` (optional): Field to sort by (default: created_at)
- `sort_order` (optional): Sort order - asc/desc (default: desc)

#### Date Filters
- `date_from` (optional): Filter by value date from (YYYY-MM-DD)
- `date_to` (optional): Filter by value date to (YYYY-MM-DD)
- `import_date_from` (optional): Filter by import date from (YYYY-MM-DD)
- `import_date_to` (optional): Filter by import date to (YYYY-MM-DD)

#### Other Filters
- `batch_id` (optional): Filter by specific import batch ID
- `status` (optional): Filter by transaction status (SUCCESS, FAILED, PENDING)
- `payment_type` (optional): Filter by payment type (NEFT, RTGS, etc.)
- `bank_name` (optional): Filter by bank name (partial match)
- `min_amount` (optional): Filter by minimum amount
- `max_amount` (optional): Filter by maximum amount

#### Response
```json
{
    "success": true,
    "data": {
        "payments": [
            {
                "id": 1,
                "record_no": 1,
                "beneficiary_account_number": "1234567890",
                "transaction_refno": "TXN123456",
                "bank_name": "HDFC Bank",
                "ifsc_code": "HDFC0001234",
                "value_date": "2026-01-28",
                "amount": "10000.00",
                "beneficiary_name": "John Doe",
                "payment_type": "NEFT",
                "transaction_status": "SUCCESS",
                "import_date": "2026-02-01",
                "import_batch_id": "550e8400-e29b-41d4-a716-446655440000",
                "created_at": "2026-02-01T10:30:00.000000Z"
            }
        ],
        "pagination": {
            "current_page": 1,
            "last_page": 4,
            "per_page": 25,
            "total": 100,
            "from": 1,
            "to": 25
        },
        "summary": {
            "total_amount": "1,500,000.00",
            "status_counts": {
                "SUCCESS": 95,
                "FAILED": 3,
                "PENDING": 2
            },
            "filters_applied": {
                "search": null,
                "date_from": "2026-01-01",
                "date_to": "2026-01-31",
                "batch_id": null,
                "status": "SUCCESS"
            }
        }
    }
}
```

### 3. Get Payment Details
**GET** `/api/payments/details/{id}`

Get detailed information for a specific payment.

#### Response
```json
{
    "success": true,
    "data": {
        "id": 1,
        "record_no": 1,
        "beneficiary_account_number": "1234567890",
        "transaction_refno": "TXN123456",
        "bank_name": "HDFC Bank",
        "ifsc_code": "HDFC0001234",
        "debit_account_number": "9876543210",
        "value_date": "2026-01-28",
        "amount": "10000.00",
        "beneficiary_name": "John Doe",
        "payment_type": "NEFT",
        "rbi_utr_reference_number": "UTR123456789",
        "currency": "INR",
        "error_description": null,
        "transaction_status": "SUCCESS",
        "beneficiary_aadhaar_number": "123456789012",
        "destination_bank": "State Bank of India",
        "credit_reference": "REF123",
        "import_date": "2026-02-01",
        "import_batch_id": "550e8400-e29b-41d4-a716-446655440000",
        "created_at": "2026-02-01T10:30:00.000000Z"
    }
}
```

### 4. Get Import Batches
**GET** `/api/payments/batches`

Get paginated list of import batches with statistics.

#### Query Parameters
- `per_page` (optional): Number of records per page (default: 25)
- `page` (optional): Page number (default: 1)
- `sort_by` (optional): Field to sort by (default: created_at)
- `sort_order` (optional): Sort order - asc/desc (default: desc)

#### Response
```json
{
    "success": true,
    "data": {
        "batches": [
            {
                "batch_id": "550e8400-e29b-41d4-a716-446655440000",
                "import_date": "2026-02-01",
                "created_at": "2026-02-01T10:30:00.000000Z",
                "total_records": 100,
                "total_amount": "1,500,000.00",
                "status_breakdown": {
                    "SUCCESS": 95,
                    "FAILED": 3,
                    "PENDING": 2
                },
                "success_rate": 95.00
            }
        ],
        "pagination": {
            "current_page": 1,
            "last_page": 3,
            "per_page": 25,
            "total": 75,
            "from": 1,
            "to": 25
        }
    }
}
```

### 5. Get Import Status
**GET** `/api/payments/status/{batchId}`

Get statistics for a specific import batch.

#### Response
```json
{
    "success": true,
    "data": {
        "batch_id": "550e8400-e29b-41d4-a716-446655440000",
        "total_records": 100,
        "import_date": "2026-02-01",
        "created_at": "2026-02-01T10:30:00.000000Z",
        "status_breakdown": {
            "SUCCESS": 95,
            "FAILED": 3,
            "PENDING": 2
        },
        "total_amount": 1500000.00
    }
}
```

### 6. Delete Payment
**DELETE** `/api/payments/delete/{id}`

Delete a specific payment record.

#### Response
```json
{
    "success": true,
    "message": "Payment deleted successfully"
}
```

### 7. Export Payments
**GET** `/api/payments/export`

Export payments to CSV file with optional filtering.

#### Query Parameters
- `batch_id` (optional): Filter by import batch ID
- `date_from` (optional): Filter by import date from (YYYY-MM-DD)
- `date_to` (optional): Filter by import date to (YYYY-MM-DD)

#### Response
Returns a CSV file download with all payment data.

## Error Codes

| Code | Description |
|------|-------------|
| `IMPORT_ERROR` | Error during file import process |
| `FETCH_ERROR` | Error fetching data from database |
| `NOT_FOUND` | Requested resource not found |
| `BATCH_NOT_FOUND` | Import batch not found |
| `DELETE_ERROR` | Error deleting record |
| `EXPORT_ERROR` | Error during export process |
| `NO_DATA` | No data available for export |

## File Format Requirements

### Excel/CSV Structure (17 columns):
1. Record No
2. Beneficiary Account Number
3. Transaction Refno
4. Bank Name
5. IFSC Code
6. Account Number (Debit)
7. Value Date (DD/MM/YYYY)
8. Amount
9. Beneficiary Name
10. Payment Type
11. RBI/UTR Reference Number
12. Currency
13. Error Description
14. Status of Cheque and Direct Debit Transaction
15. Beneficiary Aadhaar number
16. Destination Bank
17. Credit Reference

### Data Rules
- Date format: DD/MM/YYYY
- Amount: Numeric values
- Empty cells or '- -' treated as NULL
- Duplicate transaction reference numbers are skipped
- Required fields: Record No, Beneficiary Account Number, Transaction Refno, Value Date, Amount, Beneficiary Name

## Example Usage

### cURL Examples

#### Import File
```bash
curl -X POST \
  http://your-domain/api/payments/import \
  -H 'Content-Type: multipart/form-data' \
  -F 'payment_file=@/path/to/payment_file.xlsx' \
  -F 'source_app=External System'
```

#### Get Payment List with Filters
```bash
curl -X GET \
  'http://your-domain/api/payments/list?per_page=10&search=HDFC&sort_by=amount&sort_order=desc&date_from=2026-01-01&date_to=2026-01-31&status=SUCCESS&min_amount=1000&max_amount=50000'
```

#### Get Import Batches
```bash
curl -X GET \
  'http://your-domain/api/payments/batches?per_page=10&sort_by=created_at&sort_order=desc'
```

#### Get Payment Details
```bash
curl -X GET \
  http://your-domain/api/payments/details/123
```

#### Export Payments
```bash
curl -X GET \
  'http://your-domain/api/payments/export?date_from=2026-01-01&date_to=2026-01-31' \
  --output payments_export.csv
```

## Integration Notes

1. **File Size Limit**: Maximum file size is 10MB
2. **Supported Formats**: Excel (.xlsx, .xls) and CSV (.csv)
3. **Duplicate Handling**: Duplicate transaction reference numbers are automatically skipped
4. **Batch Tracking**: Each import gets a unique batch ID for tracking
5. **Error Handling**: Detailed error information is provided for failed imports
6. **Data Validation**: All required fields are validated before import

## Response Format

All API responses follow this standard format:
```json
{
    "success": boolean,
    "message": "string",
    "data": object|array,
    "error_code": "string" // Only present in error responses
}
```