# Focus Integration and Advance Amount Finalization System

## Overview
This system handles the complete workflow for exporting advance payment data to Focus software, importing it back, and finalizing the advance amounts. It provides a structured approach to manage the final stage of advance payment processing.

## Workflow Stages

### Stage 1: Payment Completion
- **Status:** `payment_status = 'Paid'`
- **Description:** Activities that have been paid and are ready for Focus export
- **API:** `GET /api/advance/finance/focus-export-ready`

### Stage 2: Focus Export
- **Status:** `final_status = 'Exported to Focus'`
- **Description:** Activities exported to Focus software for processing
- **API:** `POST /api/advance/finance/mark-exported`

### Stage 3: Focus Import
- **Status:** `final_status = 'Imported from Focus'`
- **Description:** Activities imported back from Focus with processing results
- **API:** `POST /api/advance/finance/mark-imported`

### Stage 4: Amount Finalization
- **Status:** `final_status = 'Finalized'`
- **Description:** Final amounts confirmed and locked
- **API:** `POST /api/advance/finance/finalize-amounts`

## Database Schema

### New Fields in `advance_request_activities` Table

```sql
-- Final amount after Focus processing
final_amount DECIMAL(10,2) NULL

-- Current status in finalization workflow
final_status VARCHAR(255) DEFAULT 'Pending'

-- Remarks for finalization
final_remarks TEXT NULL

-- User who finalized the amount
finalized_by BIGINT UNSIGNED NULL

-- When the amount was finalized
finalized_at TIMESTAMP NULL

-- Focus batch identifier for tracking
focus_batch_id VARCHAR(255) NULL

-- When exported to Focus
focus_exported_at TIMESTAMP NULL

-- When imported from Focus
focus_imported_at TIMESTAMP NULL
```

### Final Status Values

```php
const FINAL_STATUS_PENDING = 'Pending';
const FINAL_STATUS_EXPORTED = 'Exported to Focus';
const FINAL_STATUS_IMPORTED = 'Imported from Focus';
const FINAL_STATUS_FINALIZED = 'Finalized';
const FINAL_STATUS_REJECTED = 'Rejected';
```

### New Action Types

```php
const ACTION_FOCUS_EXPORT = 'Focus_Export';
const ACTION_FOCUS_IMPORT = 'Focus_Import';
const ACTION_FINALIZE_AMOUNT = 'Finalize_Amount';
```

## API Endpoints

### 1. Get Activities Ready for Focus Export

**Endpoint:** `GET /api/advance/finance/focus-export-ready`

**Description:** Retrieves activities that are paid but not yet exported to Focus.

**Parameters:**
- `year_id` (optional): Filter by financial year

**Response:**
```json
{
  "success": true,
  "message": "Activities ready for Focus export retrieved successfully",
  "data": {
    "activities": [
      {
        "id": 123,
        "advance_request_id": 45,
        "employee_id": 28,
        "employee_name": "John Doe",
        "employee_code": "E001",
        "department": "Sales",
        "designation": "Manager",
        "amount": 5000.00,
        "finance_approved_amount": 4500.00,
        "payment_amount": 4500.00,
        "payment_date": "2026-01-30",
        "payment_batch_id": "PAY_BATCH_20260130_123",
        "final_status": "Pending",
        "submission_no": 1,
        "advance_fill_date": "2026-01-25"
      }
    ],
    "total_count": 15,
    "total_amount": 67500.00
  }
}
```

### 2. Mark Activities as Exported to Focus

**Endpoint:** `POST /api/advance/finance/mark-exported`

**Description:** Marks activities as exported to Focus software.

**Request Body:**
```json
{
  "user_id": "11",
  "activity_ids": [123, 124, 125],
  "focus_batch_id": "FOCUS_BATCH_20260131_001",
  "remarks": "Exported for January 2026 processing"
}
```

**Response:**
```json
{
  "success": true,
  "message": "3 activities marked as exported to Focus successfully",
  "data": {
    "exported_count": 3,
    "focus_batch_id": "FOCUS_BATCH_20260131_001",
    "exported_at": "2026-01-31T10:30:00Z",
    "activities": [
      {
        "id": 123,
        "employee_id": 28,
        "amount": 4500.00,
        "final_status": "Exported to Focus"
      }
    ]
  }
}
```

### 3. Mark Activities as Imported from Focus

**Endpoint:** `POST /api/advance/finance/mark-imported`

**Description:** Marks activities as imported back from Focus software.

**Request Body:**
```json
{
  "user_id": "11",
  "focus_batch_id": "FOCUS_BATCH_20260131_001",
  "remarks": "Imported after Focus processing"
}
```

**Response:**
```json
{
  "success": true,
  "message": "3 activities marked as imported from Focus successfully",
  "data": {
    "imported_count": 3,
    "focus_batch_id": "FOCUS_BATCH_20260131_001",
    "imported_at": "2026-01-31T14:30:00Z",
    "activities": [
      {
        "id": 123,
        "employee_id": 28,
        "amount": 4500.00,
        "final_status": "Imported from Focus"
      }
    ]
  }
}
```

### 4. Get Activities Ready for Finalization

**Endpoint:** `GET /api/advance/finance/finalization-ready`

**Description:** Retrieves activities that are imported from Focus and ready for finalization.

**Parameters:**
- `year_id` (optional): Filter by financial year
- `focus_batch_id` (optional): Filter by specific Focus batch

**Response:**
```json
{
  "success": true,
  "message": "Activities ready for finalization retrieved successfully",
  "data": {
    "activities": [
      {
        "id": 123,
        "advance_request_id": 45,
        "employee_id": 28,
        "employee_name": "John Doe",
        "employee_code": "E001",
        "department": "Sales",
        "designation": "Manager",
        "amount": 5000.00,
        "finance_approved_amount": 4500.00,
        "payment_amount": 4500.00,
        "final_amount": null,
        "final_status": "Imported from Focus",
        "focus_batch_id": "FOCUS_BATCH_20260131_001",
        "focus_exported_at": "2026-01-31T10:30:00Z",
        "focus_imported_at": "2026-01-31T14:30:00Z"
      }
    ],
    "total_count": 3,
    "total_payment_amount": 13500.00,
    "focus_batches": ["FOCUS_BATCH_20260131_001"]
  }
}
```

### 5. Finalize Advance Amounts

**Endpoint:** `POST /api/advance/finance/finalize-amounts`

**Description:** Finalizes the advance amounts after Focus processing.  

**Request Body:**
```json
{
  "user_id": "11",
  "selected_activities": [
    {
      "id": 123,
      "final_amount": 4200.00
    },
    {
      "id": 124,
      "final_amount": 3800.00
    }
  ],
  "remarks": "Amounts finalized after Focus verification"
}
```

**Response:**
```json
{
  "success": true,
  "message": "2 activities finalized successfully with total amount: 8000.00",
  "data": {
    "finalized_count": 2,
    "total_final_amount": 8000.00,
    "finalized_at": "2026-01-31T16:30:00Z",
    "activities": [
      {
        "id": 123,
        "employee_id": 28,
        "payment_amount": 4500.00,
        "final_amount": 4200.00,
        "final_status": "Finalized",
        "focus_batch_id": "FOCUS_BATCH_20260131_001"
      }
    ]
  }
}
```

### 6. Get Finalized Activities

**Endpoint:** `GET /api/advance/finance/finalized`

**Description:** Retrieves activities that have been finalized.

**Parameters:**
- `year_id` (optional): Filter by financial year
- `focus_batch_id` (optional): Filter by specific Focus batch

**Response:**
```json
{
  "success": true,
  "message": "Finalized activities retrieved successfully",
  "data": {
    "activities": [
      {
        "id": 123,
        "advance_request_id": 45,
        "employee_id": 28,
        "employee_name": "John Doe",
        "employee_code": "E001",
        "department": "Sales",
        "designation": "Manager",
        "amount": 5000.00,
        "finance_approved_amount": 4500.00,
        "payment_amount": 4500.00,
        "final_amount": 4200.00,
        "final_status": "Finalized",
        "final_remarks": "Amounts finalized after Focus verification",
        "finalized_by": 11,
        "finalizer_name": "Finance Manager",
        "finalized_at": "2026-01-31T16:30:00Z",
        "focus_batch_id": "FOCUS_BATCH_20260131_001",
        "focus_exported_at": "2026-01-31T10:30:00Z",
        "focus_imported_at": "2026-01-31T14:30:00Z"
      }
    ],
    "total_count": 2,
    "total_payment_amount": 9000.00,
    "total_final_amount": 8000.00,
    "focus_batches": ["FOCUS_BATCH_20260131_001"]
  }
}
```

## Usage Workflow

### Step 1: Export to Focus
```bash
# 1. Get activities ready for Focus export
curl -X GET "https://your-domain.com/api/advance/finance/focus-export-ready?year_id=7"

# 2. Mark activities as exported
curl -X POST "https://your-domain.com/api/advance/finance/mark-exported" \
  -H "Content-Type: application/json" \
  -d '{
    "user_id": "11",
    "activity_ids": [123, 124, 125],
    "focus_batch_id": "FOCUS_BATCH_20260131_001",
    "remarks": "Exported for January 2026 processing"
  }'
```

### Step 2: Import from Focus
```bash
# Mark activities as imported after Focus processing
curl -X POST "https://your-domain.com/api/advance/finance/mark-imported" \
  -H "Content-Type: application/json" \
  -d '{
    "user_id": "11",
    "focus_batch_id": "FOCUS_BATCH_20260131_001",
    "remarks": "Imported after Focus processing"
  }'
```

### Step 3: Finalize Amounts
```bash
# 1. Get activities ready for finalization
curl -X GET "https://your-domain.com/api/advance/finance/finalization-ready?focus_batch_id=FOCUS_BATCH_20260131_001"

# 2. Finalize the amounts
curl -X POST "https://your-domain.com/api/advance/finance/finalize-amounts" \
  -H "Content-Type: application/json" \
  -d '{
    "user_id": "11",
    "selected_activities": [
      {"id": 123, "final_amount": 4200.00},
      {"id": 124, "final_amount": 3800.00}
    ],
    "remarks": "Amounts finalized after Focus verification"
  }'
```

### Step 4: View Finalized Activities
```bash
# Get finalized activities summary
curl -X GET "https://your-domain.com/api/advance/finance/finalized?year_id=7"
```

## Business Rules

### Export Rules
1. Only activities with `payment_status = 'Paid'` can be exported
2. Activities must have `final_status = 'Pending'` to be eligible for export
3. Each export must have a unique `focus_batch_id`
4. Export action is logged in the audit trail

### Import Rules
1. Only activities with `final_status = 'Exported to Focus'` can be imported
2. Import is done by `focus_batch_id` (all activities in a batch)
3. Import action is logged in the audit trail

### Finalization Rules
1. Only activities with `final_status = 'Imported from Focus'` can be finalized
2. `final_amount` must be greater than 0
3. `final_amount` cannot exceed `payment_amount`
4. Finalization is irreversible
5. Finalization action is logged in the audit trail

## Error Handling

### Common Errors

#### Export Errors
```json
{
  "success": false,
  "message": "No eligible activities found for Focus export. Activities must be paid and not already exported."
}
```

#### Import Errors
```json
{
  "success": false,
  "message": "No exported activities found for Focus batch ID: FOCUS_BATCH_20260131_001"
}
```

#### Finalization Errors
```json
{
  "success": false,
  "message": "Final amount cannot exceed payment amount for activity ID 123"
}
```

## Database Queries

### Check Activities by Status

#### Ready for Export
```sql
SELECT ara.id, ara.payment_amount, ara.final_status, ara.payment_date
FROM advance_request_activities ara
WHERE ara.payment_status = 'Paid'
AND ara.final_status = 'Pending';
```

#### Exported to Focus
```sql
SELECT ara.id, ara.focus_batch_id, ara.focus_exported_at, ara.final_status
FROM advance_request_activities ara
WHERE ara.final_status = 'Exported to Focus';
```

#### Ready for Finalization
```sql
SELECT ara.id, ara.payment_amount, ara.focus_batch_id, ara.focus_imported_at
FROM advance_request_activities ara
WHERE ara.final_status = 'Imported from Focus';
```

#### Finalized Activities
```sql
SELECT ara.id, ara.payment_amount, ara.final_amount, ara.finalized_at, ara.finalized_by
FROM advance_request_activities ara
WHERE ara.final_status = 'Finalized';
```

## Audit Trail

All Focus-related actions are logged in the `advance_request_actions` table:

- **Focus_Export**: When activities are exported to Focus
- **Focus_Import**: When activities are imported from Focus
- **Finalize_Amount**: When final amounts are confirmed

## Integration with Existing System

### Payment Release Integration
- Activities must complete the payment release process before Focus export
- Focus export is the next step after payment completion
- The system maintains the complete audit trail from approval to finalization

### Employee Ledger Integration
- Final amounts can be used to adjust employee ledger balances if needed
- Difference between payment amount and final amount can be tracked
- Integration with existing ledger balance system

## Frontend Implementation

### Recommended UI Flow

1. **Focus Export Tab**
   - Show activities ready for export
   - Bulk export functionality
   - Generate Focus batch ID

2. **Focus Import Tab**
   - Show exported batches
   - Mark batches as imported
   - Track import status

3. **Finalization Tab**
   - Show activities ready for finalization
   - Allow editing of final amounts
   - Bulk finalization functionality

4. **Finalized Tab**
   - Show completed finalizations
   - Summary reports
   - Export capabilities

### Sample JavaScript Integration

```javascript
// Export to Focus
async function exportToFocus(activityIds, batchId, remarks) {
  const response = await fetch('/api/advance/finance/mark-exported', {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify({
      user_id: getCurrentUserId(),
      activity_ids: activityIds,
      focus_batch_id: batchId,
      remarks: remarks
    })
  });
  return response.json();
}

// Finalize amounts
async function finalizeAmounts(activities, remarks) {
  const response = await fetch('/api/advance/finance/finalize-amounts', {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify({
      user_id: getCurrentUserId(),
      selected_activities: activities,
      remarks: remarks
    })
  });
  return response.json();
}
```

## Security Considerations

1. **User Authentication**: All endpoints require valid user authentication
2. **Authorization**: Only finance users should access these endpoints
3. **Audit Logging**: All actions are logged with user ID and timestamp
4. **Data Validation**: Strict validation on amounts and status transitions
5. **Transaction Safety**: All operations use database transactions

## Performance Considerations

1. **Indexing**: Added indexes on `final_status`, `finalized_at`, and `focus_batch_id`
2. **Batch Processing**: Operations support bulk processing for efficiency
3. **Pagination**: Large result sets should implement pagination
4. **Caching**: Consider caching for frequently accessed data

## Monitoring and Reporting

### Key Metrics to Track

1. **Export Volume**: Number of activities exported per batch
2. **Processing Time**: Time between export and import
3. **Amount Differences**: Variance between payment and final amounts
4. **Error Rates**: Failed exports, imports, or finalizations
5. **User Activity**: Who is performing these operations

### Sample Reports

1. **Focus Processing Summary**: Activities by status and batch
2. **Amount Variance Report**: Differences between payment and final amounts
3. **Processing Timeline**: Time taken for each stage
4. **User Activity Report**: Actions performed by each user

## Conclusion

This Focus integration and finalization system provides a complete solution for managing the final stage of advance payment processing. It ensures proper audit trails, maintains data integrity, and provides the flexibility needed for integration with external Focus software while maintaining the existing advance payment workflow.

The system is designed to be scalable, secure, and user-friendly, with comprehensive error handling and detailed logging for compliance and troubleshooting purposes.