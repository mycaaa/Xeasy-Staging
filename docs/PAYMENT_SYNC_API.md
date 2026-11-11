# Payment Sync API Documentation

## Overview
The Payment Sync API allows you to synchronize existing payment records from the `digi_next_payment_file` table with advance requests that are "Ready for Payment". This eliminates the need to upload Excel files repeatedly and provides better tracking of payment processing.

## Key Features
- **No File Upload Required**: Uses existing data in `digi_next_payment_file` table
- **Smart Matching**: Matches payments with advance requests based on name similarity and exact amount matching
- **Flag Management**: Tracks processing status with proper flags in the database
- **Comprehensive Reporting**: Provides detailed statistics and sync status

## API Endpoints

### 1. Sync Payment Confirmation
**Endpoint:** `POST /api/advance/finance/confirm-payments`

**Description:** Synchronizes existing payment records with ready advance requests.

**Parameters:**
- `user_id` (required): ID of the user performing the sync
- `batch_id` (optional): Process only specific payment batch
- `force_resync` (optional, boolean): Re-process already processed records

**Request Example:**
```bash
curl -X POST "http://your-domain.com/api/advance/finance/confirm-payments" \
  -H "Content-Type: application/json" \
  -d '{
    "user_id": 123,
    "batch_id": "PAY_BATCH_20260202_001",
    "force_resync": false
  }'
```

**Response Example:**
```json
{
  "success": true,
  "message": "Payment confirmation sync processed successfully",
  "data": {
    "total_payment_records": 50,
    "processed_payment_records": 45,
    "total_advance_requests": 30,
    "matched_payments": 25,
    "unsettled_requests": 5,
    "success_rate": 83.33,
    "payment_match_rate": 55.56,
    "errors": []
  }
}
```

## Database Schema Changes

### New Columns in `digi_next_payment_file` Table
- `is_processed` (boolean): Whether the record has been processed
- `matched_activity_id` (bigint, nullable): ID of matched advance request activity
- `processed_at` (timestamp, nullable): When the record was processed
- `processed_by` (bigint, nullable): ID of user who processed the record
- `match_status` (enum): 'MATCHED', 'UNMATCHED', or 'PENDING'
- `match_remarks` (text, nullable): Additional remarks about the matching process

## Matching Logic

### Criteria for Payment Matching
1. **Transaction Status**: Only 'SUCCESS' status payments are considered
2. **Amount Match**: Exact amount match (within 0.01 tolerance)
3. **Name Similarity**: Employee name similarity > 80% using Levenshtein distance
4. **Unique Matching**: Each payment record can only match one advance request

### Processing Flow
1. **Get Ready Activities**: Fetch all advance requests with "Ready for Payment" status
2. **Get Unprocessed Payments**: Fetch payment records with 'SUCCESS' status that haven't been processed
3. **Match Process**: For each payment record, find the best matching advance request
4. **Update Records**: Mark matched activities as "Paid" and update payment record flags
5. **Handle Unmatched**: Create unsettled records for unmatched advance requests

## Status Management

### Payment Record Status (`match_status`)
- **PENDING**: Not yet processed
- **MATCHED**: Successfully matched with an advance request
- **UNMATCHED**: Processed but no matching advance request found

### Advance Request Status (`payment_status`)
- **Ready for Payment**: Ready to be matched with payment records
- **Paid**: Successfully matched and confirmed
- **Unsettled**: No matching payment record found

## Error Handling
- Invalid payment records are logged but don't stop the process
- Detailed error messages are returned in the response
- Database transactions ensure data consistency
- Rollback occurs if critical errors are encountered

## Best Practices
1. **Regular Sync**: Run payment sync regularly to keep data up-to-date
2. **Monitor Status**: Use the sync status API to monitor processing rates
3. **Handle Unsettled**: Review and resolve unsettled payments promptly
4. **Batch Processing**: Use batch_id parameter for targeted processing
5. **Force Resync**: Use force_resync sparingly, only when necessary

## Integration Examples

### Daily Sync Process
```bash
# 1. Run sync process (main endpoint)
curl -X POST "http://your-domain.com/api/advance/finance/confirm-payments" \
  -H "Content-Type: application/json" \
  -d '{"user_id": 123}'

# 2. Check confirmed payments
curl -X GET "http://your-domain.com/api/advance/finance/confirmed-payments"

# 3. Check unsettled payments
curl -X GET "http://your-domain.com/api/advance/finance/unsettled-payments"
```

### Batch-Specific Processing
```bash
# Process specific batch
curl -X POST "http://your-domain.com/api/advance/finance/confirm-payments" \
  -H "Content-Type: application/json" \
  -d '{
    "user_id": 123,
    "batch_id": "PAY_BATCH_20260202_001"
  }'
```

## Migration
To add the new columns to your existing `digi_next_payment_file` table, run:
```bash
php artisan migrate
```

This will execute the migration: `2026_02_02_140000_add_processing_flags_to_digi_next_payment_file_table.php`