# Export Logs with Date & Time Tracking

## Overview
Enhanced export tracking system that stores each export request as a separate log entry with complete date & time information, instead of just incrementing a counter.

## What Changed

### Before
- Only stored counter (`export_count`) and dates
- No detailed history of each export
- Couldn't see who exported when with what batch

### After
- Each export request creates a new log entry
- Full details: date, time, user, batch ID, file name
- Complete audit trail of all export activities
- Can query export history by activity, batch, date range

## Database Schema

### New Table: `advance_export_logs`

| Column | Type | Description |
|--------|------|-------------|
| `id` | bigint | Primary key |
| `activity_id` | bigint | Activity that was exported |
| `exported_by` | bigint | User who performed export |
| `export_batch_id` | varchar(100) | Unique batch identifier |
| `export_amount` | decimal(15,2) | Amount exported |
| `export_type` | varchar(50) | Type: payment, focus, etc. |
| `export_status` | varchar(50) | Status: success, failed |
| `export_remarks` | text | Additional remarks |
| `file_name` | varchar(255) | Generated file name |
| `total_activities_in_export` | integer | Total activities in this export |
| `exported_at` | timestamp | When exported (with time) |
| `created_at` | timestamp | Record creation time |
| `updated_at` | timestamp | Record update time |

### Indexes
- `activity_id` - Fast lookup by activity
- `export_batch_id` - Fast lookup by batch

## API Endpoints

### 1. Export Payments (Enhanced)
**Endpoint:** `GET /api/advance/finance/export-payments`

**What It Does:**
- Exports activities as before
- Now creates a log entry for each activity
- Generates unique batch ID for the export
- Stores complete export details with timestamp

**Example Log Entry Created:**
```json
{
  "activity_id": 123,
  "exported_by": 789,
  "export_batch_id": "EXP_20260207_143025_65c3f1a2b4d8e",
  "export_amount": 50000.00,
  "export_type": "payment",
  "export_status": "success",
  "export_remarks": "Payment export - Batch: EXP_20260207_143025_65c3f1a2b4d8e",
  "file_name": "adv_payment_export_year_7_2026_02_07_14_30_25.xlsx",
  "total_activities_in_export": 15,
  "exported_at": "2026-02-07 14:30:25"
}
```

---

### 2. Get Export History (Enhanced)
**Endpoint:** `GET /api/advance/finance/export-history`

**Parameters:**
- `year_id` (optional): Filter by financial year
- `from_date` (optional): Filter from date
- `to_date` (optional): Filter to date
- `activity_id` (optional): Filter by specific activity
- `batch_id` (optional): Filter by export batch
- `limit` (optional): Records per page (default: 50)
- `page` (optional): Page number (default: 1)

**Response:**
```json
{
  "success": true,
  "message": "Export history retrieved successfully",
  "data": {
    "export_history": [
      {
        "log_id": 456,
        "activity_id": 123,
        "employee_id": 789,
        "employee_name": "John Doe",
        "employee_code": "E001",
        "export_amount": 50000.00,
        "export_batch_id": "EXP_20260207_143025_65c3f1a2b4d8e",
        "export_type": "payment",
        "export_status": "success",
        "export_remarks": "Payment export - Batch: EXP_20260207_143025_65c3f1a2b4d8e",
        "file_name": "adv_payment_export_year_7_2026_02_07_14_30_25.xlsx",
        "total_activities_in_export": 15,
        "exported_at": "2026-02-07 14:30:25",
        "exported_by": 456,
        "exporter_name": "Finance User",
        "payment_status": "Ready for Payment",
        "payment_batch_id": null
      }
    ],
    "statistics": {
      "total_export_requests": 250,
      "total_exported_activities": 150,
      "total_not_exported": 25,
      "total_export_amount": 7500000.00,
      "unique_batches": 45,
      "today_exports": 5,
      "this_month_exports": 78
    },
    "pagination": {
      "current_page": 1,
      "per_page": 50,
      "total": 250,
      "total_pages": 5
    },
    "filters": {
      "year_id": 7,
      "from_date": "2026-02-01",
      "to_date": "2026-02-07",
      "activity_id": null,
      "batch_id": null
    }
  }
}
```

---

### 3. Get Activity Export Logs (NEW)
**Endpoint:** `GET /api/advance/finance/activity-export-logs/{activityId}`

**Description:** Get all export logs for a specific activity

**Response:**
```json
{
  "success": true,
  "message": "Activity export logs retrieved successfully",
  "data": {
    "activity_id": 123,
    "employee_id": 789,
    "employee_name": "John Doe",
    "employee_code": "E001",
    "current_status": {
      "is_exported": true,
      "export_count": 3,
      "first_export_date": "2026-02-01",
      "last_export_date": "2026-02-07",
      "payment_status": "Ready for Payment"
    },
    "export_logs": [
      {
        "log_id": 789,
        "export_batch_id": "EXP_20260207_143025_65c3f1a2b4d8e",
        "export_amount": 50000.00,
        "export_type": "payment",
        "export_status": "success",
        "export_remarks": "Payment export - Batch: EXP_20260207_143025_65c3f1a2b4d8e",
        "file_name": "adv_payment_export_year_7_2026_02_07_14_30_25.xlsx",
        "total_activities_in_export": 15,
        "exported_at": "2026-02-07 14:30:25",
        "exported_by": 456,
        "exporter_name": "Finance User",
        "exporter_code": "F001"
      },
      {
        "log_id": 654,
        "export_batch_id": "EXP_20260205_101520_65c2a1b3c5d9f",
        "export_amount": 50000.00,
        "export_type": "payment",
        "export_status": "success",
        "export_remarks": "Payment export - Batch: EXP_20260205_101520_65c2a1b3c5d9f",
        "file_name": "adv_payment_export_year_7_2026_02_05_10_15_20.xlsx",
        "total_activities_in_export": 20,
        "exported_at": "2026-02-05 10:15:20",
        "exported_by": 456,
        "exporter_name": "Finance User",
        "exporter_code": "F001"
      }
    ],
    "total_exports": 3
  }
}
```

## Export Batch ID Format

Each export generates a unique batch ID:
```
EXP_YYYYMMDD_HHMMSS_UNIQUEID
```

Example: `EXP_20260207_143025_65c3f1a2b4d8e`
- `EXP` - Prefix
- `20260207` - Date (Feb 7, 2026)
- `143025` - Time (14:30:25)
- `65c3f1a2b4d8e` - Unique identifier

## Use Cases

### 1. Track All Exports for an Activity
**Scenario:** Finance wants to see how many times an activity was exported

**Action:**
```bash
GET /api/advance/finance/activity-export-logs/123
```

**Result:** See complete history with dates, times, and exporters

### 2. Find All Exports in a Batch
**Scenario:** Need to verify all activities in a specific export file

**Action:**
```bash
GET /api/advance/finance/export-history?batch_id=EXP_20260207_143025_65c3f1a2b4d8e
```

**Result:** See all activities exported in that batch

### 3. Audit Export Activity
**Scenario:** Check who exported what and when

**Action:**
```bash
GET /api/advance/finance/export-history?from_date=2026-02-01&to_date=2026-02-07
```

**Result:** Complete audit trail with timestamps

### 4. Identify Duplicate Exports
**Scenario:** Find activities exported multiple times

**Action:**
```bash
GET /api/advance/finance/activity-export-logs/123
```

**Result:** See if activity was exported multiple times with details

## Benefits

### 1. Complete Audit Trail
- Every export is logged with full details
- Know exactly when, who, and what was exported
- Trace back to specific export files

### 2. Better Troubleshooting
- Identify duplicate exports easily
- See export patterns and issues
- Track export failures (if any)

### 3. Compliance & Reporting
- Generate export reports by date range
- Track user export activity
- Verify export completeness

### 4. Batch Tracking
- Group exports by batch ID
- Verify all activities in a batch
- Re-export specific batches if needed

### 5. Historical Analysis
- Analyze export patterns over time
- Identify peak export times
- Track export volumes

## Statistics Available

The export history endpoint provides:

| Statistic | Description |
|-----------|-------------|
| `total_export_requests` | Total number of export log entries |
| `total_exported_activities` | Unique activities exported |
| `total_not_exported` | Activities not yet exported |
| `total_export_amount` | Sum of all exported amounts |
| `unique_batches` | Number of unique export batches |
| `today_exports` | Exports performed today |
| `this_month_exports` | Exports this month |

## Model Relationships

### AdvanceExportLog Model
```php
// Get the activity
$log->activity

// Get the exporter
$log->exporter

// Scopes
AdvanceExportLog::forActivity($activityId)->get()
AdvanceExportLog::forBatch($batchId)->get()
AdvanceExportLog::dateRange($from, $to)->get()
AdvanceExportLog::successful()->get()
```

### AdvanceRequestActivity Model
```php
// Get all export logs
$activity->exportLogs

// Get latest export log
$activity->latestExportLog
```

## Frontend Integration

### Display Export History Table
```javascript
// Fetch export history
const response = await api.get('/api/advance/finance/export-history', {
  params: {
    from_date: '2026-02-01',
    to_date: '2026-02-07',
    limit: 50,
    page: 1
  }
});

// Display in table
const logs = response.data.data.export_history;
logs.forEach(log => {
  console.log(`${log.employee_name} - ${log.export_amount} - ${log.exported_at}`);
});
```

### Show Activity Export Timeline
```javascript
// Get all exports for an activity
const response = await api.get(`/api/advance/finance/activity-export-logs/${activityId}`);

const logs = response.data.data.export_logs;
// Display timeline of exports
logs.forEach(log => {
  console.log(`Exported on ${log.exported_at} by ${log.exporter_name}`);
});
```

## Migration Notes

### Existing Data
- Existing activities with `export_count > 0` will not have log entries
- Only new exports will create log entries
- Old export counts are preserved in `advance_request_activities` table

### Backward Compatibility
- All existing functionality continues to work
- `export_count`, `first_export_date`, `last_export_date` still updated
- New log table provides additional detail

## Related Files

- Migration: `database/migrations/2026_02_06_232028_create_advance_export_logs_table.php`
- Model: `app/Models/AdvanceExportLog.php`
- Activity Model: `app/Models/AdvanceRequestActivity.php`
- Controller: `app/Http/Controllers/Api/Advance/AdvanceFinanceController.php`
- Routes: `routes/advance.php`

## Testing

### Test Export Logging
1. Export some activities
2. Check `advance_export_logs` table
3. Verify log entries created with correct timestamps
4. Verify batch ID is unique

### Test Export History
1. Call export history endpoint
2. Verify logs are returned with correct details
3. Test filtering by date, activity, batch

### Test Activity Logs
1. Export same activity multiple times
2. Call activity export logs endpoint
3. Verify all exports are listed with timestamps

## Conclusion

The enhanced export logging system provides complete visibility into export activities with precise date & time tracking. Each export request is now a permanent record that can be queried, analyzed, and audited.
