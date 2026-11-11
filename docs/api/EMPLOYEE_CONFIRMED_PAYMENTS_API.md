# Employee Confirmed Payments API

## Overview
This API endpoint allows retrieving confirmed payments for a specific employee with optional month filtering. It returns the same response structure as the existing `/finance/confirmed-payments` endpoint but filtered for a single employee.

## Endpoint
```
GET /api/advance/finance/employee-confirmed-payments
```

## Parameters

### Required Parameters
- `employee_id` (string): The ID of the employee to retrieve confirmed payments for

### Optional Parameters
- `month` (string): Filter payments by month (format: YYYY-MM, e.g., "2024-01")
- `year_id` (integer): Filter by financial year ID
- `batch_id` (integer): Filter by payment batch ID
- `limit` (integer): Number of records per page (default: 50)
- `page` (integer): Page number for pagination (default: 1)

## Response Structure

### Success Response (200)
```json
{
    "success": true,
    "message": "Employee confirmed payments retrieved successfully",
    "data": {
        "employees": [
            {
                "id": 123,
                "advance_request_id": 456,
                "employee_id": "12345",
                "employee_name": "John Doe",
                "employee_code": "EMP001",
                "employee_email": "john.doe@company.com",
                "department": "Sales",
                "designation": "Sales Manager",
                "ledger_balance": {
                    "current_balance": 5000.00,
                    "total_advances": 15000.00,
                    "total_expenses": 10000.00
                },
                "req_data": {
                    "id": 456,
                    "advance_type_name": "Travel Advance",
                    "activity_name": "Business Trip",
                    "month": "2024-01",
                    "current_status": "Paid",
                    "approval_summary": [...],
                    "advance_history": [...],
                    "expense_submissions": [...]
                },
                "activities": [
                    {
                        "id": 789,
                        "activity_type_name": "Travel",
                        "amount": 5000.00,
                        "payment_status": "Paid",
                        "payment_amount": 5000.00,
                        "payment_date": "2024-01-15",
                        "payment_confirmation_details": {
                            "confirmed_date": "2024-01-15",
                            "confirmed_amount": 5000.00,
                            "confirmation_remark": "Payment confirmed via Excel upload - Ref: BT26013049926940",
                            "batch_id": 101
                        },
                        "digi_next_payment_details": {
                            "transaction_refno": "BT26013049926940",
                            "beneficiary_name": "John Doe",
                            "amount": 5000.00,
                            "bank_name": "State Bank of India",
                            "transaction_status": "SUCCESS"
                        },
                        "approvals": [...],
                        "crop_names": ["Wheat", "Rice"],
                        "variety_names": ["Variety A", "Variety B"]
                    }
                ]
            }
        ],
        "pagination": {
            "current_page": 1,
            "per_page": 50,
            "total": 25,
            "total_pages": 1
        },
        "filters": {
            "employee_id": "12345",
            "month": "2024-01",
            "year_id": null,
            "batch_id": null,
            "status": "Paid"
        },
        "batch_summary": {
            "batch_id": 101,
            "total_amount": 50000.00,
            "total_records": 10,
            "created_at": "2024-01-15T10:00:00Z"
        }
    }
}
```

### Error Response (400)
```json
{
    "success": false,
    "message": "employee_id is required"
}
```

## Usage Examples

### Basic Usage
```bash
GET /api/advance/finance/employee-confirmed-payments?employee_id=12345
```

### With Month Filter
```bash
GET /api/advance/finance/employee-confirmed-payments?employee_id=12345&month=2024-01
```

### With Pagination
```bash
GET /api/advance/finance/employee-confirmed-payments?employee_id=12345&limit=10&page=2
```

### With Multiple Filters
```bash
GET /api/advance/finance/employee-confirmed-payments?employee_id=12345&month=2024-01&batch_id=101&limit=25
```

## Key Features

1. **Employee-Specific Filtering**: Returns payments only for the specified employee
2. **Month Filtering**: Optional month parameter to filter payments by specific month
3. **Same Response Structure**: Maintains compatibility with existing confirmed payments API
4. **Pagination Support**: Built-in pagination with configurable limits
5. **Batch Information**: Includes payment batch details when batch_id is specified
6. **Employee Ledger Balance**: Includes current ledger balance for the employee
7. **Payment Confirmation Details**: Includes payment confirmation and DigiNext payment file details
8. **Comprehensive Activity Data**: Includes approval history, crop/variety information, and payment status

## Notes

- Only returns activities with `payment_status = "Paid"` and non-null `payment_date`
- Includes employee ledger balance information
- Supports filtering by payment batch ID
- Returns empty array if no confirmed payments found for the employee
- Maintains the same data structure as the main confirmed payments API for consistency