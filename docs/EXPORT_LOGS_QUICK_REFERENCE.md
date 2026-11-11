# Export Logs - Quick Reference

## What Is It?
Each export request now creates a detailed log entry with precise date & time, instead of just incrementing a counter.

## New Table
`advance_export_logs` - Stores each export as a separate record

## Key Fields
- `exported_at` - Precise timestamp (YYYY-MM-DD HH:MM:SS)
- `export_batch_id` - Unique batch ID (e.g., EXP_20260207_143025_65c3f1a2b4d8e)
- `file_name` - Generated file name
- `exported_by` - User who exported
- `export_amount` - Amount exported
- `export_status` - success/failed

## API Endpoints

### Get Activity Export Logs
```
GET /api/advance/finance/activity-export-logs/{activityId}
```
Returns all export logs for a specific activity.

### Get Export History
```
GET /api/advance/finance/export-history
```
**Filters:**
- `activity_id` - Specific activity
- `batch_id` - Specific batch
- `from_date` - Date range start
- `to_date` - Date range end
- `year_id` - Financial year

### Export Payments
```
GET /api/advance/finance/export-payments
```
Now creates log entry for each activity exported.

## Quick Examples

### View All Exports for Activity
```bash
GET /api/advance/finance/activity-export-logs/123
```

### Find Exports in a Batch
```bash
GET /api/advance/finance/export-history?batch_id=EXP_20260207_143025_65c3f1a2b4d8e
```

### Get Today's Exports
```bash
GET /api/advance/finance/export-history?from_date=2026-02-07&to_date=2026-02-07
```

## Model Usage

```php
// Get all export logs for an activity
$activity->exportLogs

// Get latest export log
$activity->latestExportLog

// Query logs
AdvanceExportLog::forActivity($activityId)->get()
AdvanceExportLog::forBatch($batchId)->get()
AdvanceExportLog::dateRange($from, $to)->get()
AdvanceExportLog::successful()->get()
```

## Batch ID Format
```
EXP_YYYYMMDD_HHMMSS_UNIQUEID
```
Example: `EXP_20260207_143025_65c3f1a2b4d8e`

## Statistics Available
- Total export requests
- Total exported activities
- Total not exported
- Total export amount
- Unique batches
- Today's exports
- This month's exports

## Benefits
✅ Complete audit trail with timestamps
✅ Track each export request separately
✅ Batch tracking and verification
✅ Historical analysis
✅ Compliance reporting

## Related Docs
- Full Documentation: `docs/EXPORT_LOGS_WITH_DATETIME.md`
- Implementation: `docs/EXPORT_LOGS_IMPLEMENTATION_SUMMARY.md`
- Export Tracking: `docs/ADVANCE_PAYMENT_EXPORT_TRACKING.md`
