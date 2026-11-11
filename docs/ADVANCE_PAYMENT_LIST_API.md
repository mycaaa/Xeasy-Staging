# Advance Payment List APIs

## Overview
This document describes the APIs for listing released payments and payment batches. These endpoints allow users to view and track advance requests that have been released for payment processing.

## API Endpoints

### 1. List Released Payments
**Endpoint:** `GET /api/advance/finance/released-payments`

**Purpose:** Get a list of advance requests that have been released for payment

**Parameters:**
- `year_id` (optional): Financial year ID to filter results
- `batch_id` (optional): Specific payment batch ID to filter results
- `employee_id` (optional): Specific employee ID to filter results
- `status` (optional): Payment status (default: 'Ready for Payment')
  - Available values: 'Ready for Payment', 'Paid', 'Rejected', 'Reverted'
- `limit` (optional): Number of records per page (default: 50)
- `page` (optional): Page number for pagination (default: 1)

**Example Requests:**
```bash
# Get all released payments
curl -X GET "https://your-domain.com/api/advance/finance/released-payments"

# Get payments for specific batch
curl -X GET "https://your-domain.com/api/advance/finance/released-payments?batch_id=PAY_BATCH_20250131_001"

# Get payments for specific employee
curl -X GET "https://your-domain.com/api/advance/finance/released-payments?employee_id=28"

# Get paid requests only
curl -X GET "https://your-domain.com/api/advance/finance/released-payments?status=Paid"

# Pagination example
curl -X GET "https://your-domain.com/api/advance/finance/released-payments?page=2&limit=20"
```

**Response Structure:**
```json
{
  "success": true,
  "message": "Released payments retrieved successfully",
  "data": {
    "employees": [
      {
        "id": 5,
        "advance_request_id": 1,
        "employee_id": 28,
        "employee_name": "John Doe",
        "employee_code": "EMP001",
        "employee_email": "john.doe@company.com",
        "department": "Sales",
        "designation": "Sales Manager",
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
          "remark": "Advance Against Expenses Dec-25"
        },
        "req_data": {
          "id": 1,
          "advance_type_name": "Field Visit",
          "activity_name": "Marketing Activity",
          "current_status": "Payment",
          "submission_no": "ADV001",
          "last_submitted_at": "2026-01-30T10:00:00Z"
        },
        "activities": [
          {
            "id": 123,
            "activity_type_name": "Travel",
            "amount": 5000.00,
            "purpose": "Client meeting in Mumbai",
            "finance_approved_amount": 5000.00,
            "finance_status": "Approved",
            "payment_amount": 5000.00,
            "payment_status": "Ready for Payment",
            "payment_release_details": {
              "released_by_id": 11,
              "released_by_name": "Finance Manager",
              "released_date": "2026-01-31T10:30:00Z",
              "batch_id": "PAY_BATCH_20250131_001"
            }
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
      "year_id": null,
      "batch_id": null,
      "employee_id": null,
      "status": "Ready for Payment"
    },
    "batch_summary": {
      "batch_id": "PAY_BATCH_20250131_001",
      "status": "Pending",
      "created_by": 11,
      "created_by_name": "Finance Manager",
      "created_at": "2026-01-31T10:30:00Z",
      "total_requests": 5,
      "total_amount": 25000.00,
      "actual_requests": 5,
      "actual_amount": 25000.00
    }
  }
}
```

### 2. List Payment Batches
**Endpoint:** `GET /api/advance/finance/payment-batches`

**Purpose:** Get a list of payment batches with summary information

**Parameters:**
- `status` (optional): Batch status to filter results
  - Available values: 'Pending', 'Processing', 'Completed', 'Failed'
- `limit` (optional): Number of records per page (default: 20)
- `page` (optional): Page number for pagination (default: 1)

**Example Requests:**
```bash
# Get all payment batches
curl -X GET "https://your-domain.com/api/advance/finance/payment-batches"

# Get pending batches only
curl -X GET "https://your-domain.com/api/advance/finance/payment-batches?status=Pending"

# Pagination example
curl -X GET "https://your-domain.com/api/advance/finance/payment-batches?page=1&limit=10"
```

**Response Structure:**
```json
{
  "success": true,
  "message": "Payment batches retrieved successfully",
  "data": {
    "batches": [
      {
        "id": 1,
        "batch_id": "PAY_BATCH_20250131_001",
        "status": "Pending",
        "created_by": 11,
        "created_by_name": "Finance Manager",
        "created_at": "2026-01-31T10:30:00Z",
        "processed_by": null,
        "processed_by_name": null,
        "processed_at": null,
        "total_requests": 5,
        "total_amount": 25000.00,
        "actual_requests": 5,
        "actual_amount": 25000.00,
        "remarks": null,
        "updated_at": "2026-01-31T10:30:00Z"
      },
      {
        "id": 2,
        "batch_id": "PAY_BATCH_20250130_002",
        "status": "Completed",
        "created_by": 11,
        "created_by_name": "Finance Manager",
        "created_at": "2026-01-30T15:20:00Z",
        "processed_by": 12,
        "processed_by_name": "Payment Processor",
        "processed_at": "2026-01-30T16:45:00Z",
        "total_requests": 3,
        "total_amount": 15000.00,
        "actual_requests": 3,
        "actual_amount": 15000.00,
        "remarks": "Processed successfully via bank transfer",
        "updated_at": "2026-01-30T16:45:00Z"
      }
    ],
    "pagination": {
      "current_page": 1,
      "per_page": 20,
      "total": 10,
      "total_pages": 1
    },
    "filters": {
      "status": null
    }
  }
}
```

## Usage Examples

### 1. Frontend Integration - List Released Payments

```javascript
async function getReleasedPayments(filters = {}) {
  try {
    const params = new URLSearchParams();
    
    // Add filters if provided
    if (filters.batchId) params.append('batch_id', filters.batchId);
    if (filters.employeeId) params.append('employee_id', filters.employeeId);
    if (filters.status) params.append('status', filters.status);
    if (filters.yearId) params.append('year_id', filters.yearId);
    if (filters.page) params.append('page', filters.page);
    if (filters.limit) params.append('limit', filters.limit);
    
    const response = await fetch(`/api/advance/finance/released-payments?${params}`);
    const data = await response.json();
    
    if (data.success) {
      console.log(`Found ${data.data.employees.length} released payments`);
      
      // Display results
      data.data.employees.forEach(employee => {
        console.log(`Employee: ${employee.employee_name}`);
        console.log(`Activities: ${employee.activities.length}`);
        
        employee.activities.forEach(activity => {
          console.log(`  - ${activity.purpose}: ${activity.payment_amount}`);
          console.log(`  - Status: ${activity.payment_status}`);
          console.log(`  - Batch: ${activity.payment_release_details?.batch_id}`);
        });
      });
      
      return data.data;
    } else {
      console.error('Error:', data.message);
    }
  } catch (error) {
    console.error('API Error:', error);
  }
}

// Usage examples
getReleasedPayments(); // Get all released payments
getReleasedPayments({ status: 'Paid' }); // Get paid requests only
getReleasedPayments({ batchId: 'PAY_BATCH_20250131_001' }); // Get specific batch
getReleasedPayments({ employeeId: 28, page: 1, limit: 10 }); // Employee with pagination
```

### 2. Frontend Integration - List Payment Batches

```javascript
async function getPaymentBatches(filters = {}) {
  try {
    const params = new URLSearchParams();
    
    if (filters.status) params.append('status', filters.status);
    if (filters.page) params.append('page', filters.page);
    if (filters.limit) params.append('limit', filters.limit);
    
    const response = await fetch(`/api/advance/finance/payment-batches?${params}`);
    const data = await response.json();
    
    if (data.success) {
      console.log(`Found ${data.data.batches.length} payment batches`);
      
      // Display batch summary
      data.data.batches.forEach(batch => {
        console.log(`Batch: ${batch.batch_id}`);
        console.log(`Status: ${batch.status}`);
        console.log(`Requests: ${batch.actual_requests}/${batch.total_requests}`);
        console.log(`Amount: ${batch.actual_amount}/${batch.total_amount}`);
        console.log(`Created by: ${batch.created_by_name}`);
        console.log('---');
      });
      
      return data.data;
    } else {
      console.error('Error:', data.message);
    }
  } catch (error) {
    console.error('API Error:', error);
  }
}

// Usage examples
getPaymentBatches(); // Get all batches
getPaymentBatches({ status: 'Pending' }); // Get pending batches only
getPaymentBatches({ page: 1, limit: 5 }); // With pagination
```

### 3. Batch Details View

```javascript
async function getBatchDetails(batchId) {
  try {
    // Get batch payments
    const paymentsResponse = await fetch(`/api/advance/finance/released-payments?batch_id=${batchId}`);
    const paymentsData = await paymentsResponse.json();
    
    if (paymentsData.success) {
      const batchSummary = paymentsData.data.batch_summary;
      const employees = paymentsData.data.employees;
      
      console.log('Batch Summary:');
      console.log(`ID: ${batchSummary.batch_id}`);
      console.log(`Status: ${batchSummary.status}`);
      console.log(`Total Amount: ${batchSummary.total_amount}`);
      console.log(`Created by: ${batchSummary.created_by_name}`);
      console.log(`Created at: ${batchSummary.created_at}`);
      
      console.log('\nPayment Details:');
      employees.forEach(employee => {
        employee.activities.forEach(activity => {
          console.log(`${employee.employee_name}: ${activity.payment_amount} - ${activity.purpose}`);
        });
      });
      
      return { batchSummary, employees };
    }
  } catch (error) {
    console.error('Error getting batch details:', error);
  }
}

// Usage
getBatchDetails('PAY_BATCH_20250131_001');
```

## Filter Options

### Payment Status Values
- `Ready for Payment` - Released but not yet processed
- `Paid` - Successfully processed and paid
- `Rejected` - Payment rejected
- `Reverted` - Payment reverted back

### Batch Status Values
- `Pending` - Created but not yet processed
- `Processing` - Currently being processed
- `Completed` - Successfully processed
- `Failed` - Processing failed

## Pagination

Both endpoints support pagination:
- `page`: Page number (starts from 1)
- `limit`: Records per page
- Response includes pagination metadata

## Error Handling

### Common Error Responses

```json
{
  "success": false,
  "message": "Failed to retrieve released payments: Database connection error"
}
```

### Error Scenarios
1. Database connection issues
2. Invalid filter parameters
3. Batch not found (when filtering by batch_id)
4. Employee not found (when filtering by employee_id)

## Database Queries

### Check Released Payments
```sql
SELECT 
    ara.id,
    ara.payment_status,
    ara.payment_amount,
    ara.payment_batch_id,
    ara.payment_released_date,
    are.employee_id,
    ar.id as request_id
FROM advance_request_activities ara
JOIN advance_request_employees are ON ara.advance_request_employee_id = are.id
JOIN advance_requests ar ON are.advance_request_id = ar.id
WHERE ara.payment_status = 'Ready for Payment'
ORDER BY ara.payment_released_date DESC;
```

### Check Payment Batches
```sql
SELECT 
    pb.*,
    COUNT(ara.id) as actual_requests,
    SUM(ara.payment_amount) as actual_amount
FROM payment_batches pb
LEFT JOIN advance_request_activities ara ON pb.batch_id = ara.payment_batch_id
GROUP BY pb.id
ORDER BY pb.created_at DESC;
```

### Batch Summary with Employee Details
```sql
SELECT 
    pb.batch_id,
    pb.status,
    pb.total_amount,
    COUNT(DISTINCT are.employee_id) as unique_employees,
    COUNT(ara.id) as total_activities,
    SUM(ara.payment_amount) as actual_total
FROM payment_batches pb
LEFT JOIN advance_request_activities ara ON pb.batch_id = ara.payment_batch_id
LEFT JOIN advance_request_employees are ON ara.advance_request_employee_id = are.id
WHERE pb.batch_id = 'PAY_BATCH_20250131_001'
GROUP BY pb.id;
```

## Performance Considerations

### Optimization Tips
1. Use pagination for large datasets
2. Filter by specific batch_id when possible
3. Index on payment_status and payment_batch_id fields
4. Limit results with appropriate page sizes

### Recommended Limits
- Default limit: 50 for payments, 20 for batches
- Maximum recommended: 100 records per page
- Use filters to reduce dataset size

## Integration with Payment Systems

### Export for External Processing
```javascript
async function exportBatchForPayment(batchId) {
  try {
    const response = await fetch(`/api/advance/finance/released-payments?batch_id=${batchId}`);
    const data = await response.json();
    
    if (data.success) {
      // Transform for payment system
      const paymentData = data.data.employees.flatMap(employee => 
        employee.activities.map(activity => ({
          employee_id: employee.employee_id,
          employee_name: employee.employee_name,
          employee_code: employee.employee_code,
          amount: activity.payment_amount,
          purpose: activity.purpose,
          batch_id: activity.payment_release_details.batch_id,
          activity_id: activity.id
        }))
      );
      
      return paymentData;
    }
  } catch (error) {
    console.error('Export error:', error);
  }
}
```

## Security Considerations

### Access Control
- Ensure proper authentication for finance endpoints
- Validate user permissions before showing payment data
- Log all access to payment information

### Data Protection
- Sensitive payment information should be properly secured
- Implement rate limiting for API endpoints
- Audit trail for all payment-related queries

## Monitoring and Alerts

### Key Metrics to Monitor
1. Number of pending payment batches
2. Total amount in pending payments
3. Average processing time per batch
4. Failed payment rates

### Recommended Alerts
- Batches pending for more than 24 hours
- Large payment amounts requiring approval
- Failed batch processing
- Unusual payment patterns

## Conclusion

The payment list APIs provide comprehensive functionality for tracking and managing released advance payments. The endpoints support flexible filtering, pagination, and detailed information retrieval for both individual payments and batch summaries.

For additional support or questions, refer to the main advance request documentation or the payment release documentation.