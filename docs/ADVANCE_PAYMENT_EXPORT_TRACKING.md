# Advance Payment Export Tracking Feature

## Overview
This feature adds export tracking to advance payment activities to prevent duplicate exports and track export history.

## Database Changes

### New Columns in `advance_request_activities` Table

| Column | Type | Description |
|--------|------|-------------|
| `is_exported` | boolean | Flag indicating if the activity has been exported (default: false) |
| `first_export_date` | date | Date when the activity was first exported |
| `last_export_date` | date | Date when the activity was last exported |
| `export_count` | integer | Number of times the activity has been exported (default: 0) |
| `exported_by` | bigint | User ID who performed the export |

### New Table: `advance_export_logs`

Stores detailed log of each export request with date & time.

| Column | Type | Description |
|--------|------|-------------|
| `id` | bigint | Primary key |
| `activity_id` | bigint | Activity that was exported |
| `exported_by` | bigint | User who performed export |
| `export_batch_id` | varchar(100) | Unique batch identifier (e.g., EXP_20260207_143025_65c3f1a2b4d8e) |
| `export_amount` | decimal(15,2) | Amount exported |
| `export_type` | varchar(50) | Type: payment, focus, etc. |
| `export_status` | varchar(50) | Status: success, failed |
| `export_remarks` | text | Additional remarks |
| `file_name` | varchar(255) | Generated file name |
| `total_activities_in_export` | integer | Total activities in this export |
| `exported_at` | timestamp | When exported (with precise time) |

**See:** [Export Logs with Date & Time](EXPORT_LOGS_WITH_DATETIME.md) for complete documentation.

### Migration File
- `database/migrations/2026_02_07_120000_add_export_tracking_to_advance_request_activities.php` - Adds tracking columns
- `database/migrations/2026_02_06_232028_create_advance_export_logs_table.php` - Creates export logs table

## Model Updates

### AdvanceRequestActivity Model

#### New Methods

1. **`isExported()`**
   - Returns: boolean
   - Checks if the activity has been exported

2. **`markAsExported($userId = null)`**
   - Marks the activity as exported
   - Updates export count and dates
   - Parameters:
     - `$userId` (optional): ID of the user performing the export

3. **`resetExportStatus()`**
   - Resets all export tracking fields to default values
   - Used for re-export scenarios

## API Endpoints

### 1. Export Payments (Updated)
**Endpoint:** `GET /api/advance/finance/export-payments`

**New Parameters:**
- `include_exported` (boolean, optional): Include already exported records (default: false)

**Behavior:**
- By default, excludes already exported activities
- Automatically marks activities as exported after successful export
- Tracks export count and dates
- Logs export activity

**Response:** Excel file download with payment data

**Error Response (No Activities Available):**
```json
{
  "success": false,
  "message": "No activities found with \"Ready for Payment\" status for export",
  "data": {
    "excluded_activities": [
      {
        "activity_id": 123,
        "employee_id": 456,
        "employee_name": "John Doe",
        "employee_code": "E001",
        "amount": 50000.00,
        "export_count": 2,
        "first_export_date": "2026-02-01",
        "last_export_date": "2026-02-07",
        "exported_by": 789,
        "exporter_name": "Finance User",
        "payment_batch_id": "PAY_BATCH_20260207_001",
        "reason": "Already exported"
      }
    ],
    "excluded_count": 5,
    "total_excluded_amount": 250000.00,
    "suggestion": "All activities matching your criteria have already been exported. Use include_exported=true parameter to re-export them."
  }
}
```

**Note:** When no activities are available for export (because they've all been exported already), the API returns a list of excluded activities showing which activities were not included and why.

---

### 2. Get Export History
**Endpoint:** `GET /api/advance/finance/export-history`

**Parameters:**
- `year_id` (integer, optional): Filter by financial year
- `from_date` (date, optional): Filter from date
- `to_date` (date, optional): Filter to date
- `limit` (integer, optional): Records per page (default: 50)
- `page` (integer, optional): Page number (default: 1)

**Response:**
```json
{
  "success": true,
  "message": "Export history retrieved successfully",
  "data": {
    "export_history": [
      {
        "activity_id": 123,
        "employee_id": 456,
        "employee_name": "John Doe",
        "employee_code": "E001",
        "amount": 50000.00,
        "export_count": 2,
        "first_export_date": "2026-02-01",
        "last_export_date": "2026-02-07",
        "exported_by": 789,
        "exporter_name": "Finance User",
        "payment_status": "Ready for Payment",
        "payment_batch_id": "PAY_BATCH_20260207_001"
      }
    ],
    "statistics": {
      "total_exported": 150,
      "total_not_exported": 25,
      "total_export_amount": 7500000.00,
      "multiple_exports": 10,
      "today_exports": 5
    },
    "pagination": {
      "current_page": 1,
      "per_page": 50,
      "total": 150,
      "total_pages": 3
    },
    "filters": {
      "year_id": 7,
      "from_date": "2026-02-01",
      "to_date": "2026-02-07"
    }
  }
}
```

---

### 3. Reset Export Status
**Endpoint:** `POST /api/advance/finance/reset-export-status`

**Parameters:**
- `activity_ids` (array, required): Array of activity IDs to reset

**Request Body:**
```json
{
  "activity_ids": [123, 456, 789]
}
```

**Response:**
```json
{
  "success": true,
  "message": "Export status reset successfully for 3 activities",
  "data": {
    "reset_count": 3,
    "activity_ids": [123, 456, 789]
  }
}
```

**Use Case:** Admin function to allow re-export of activities if needed

---

### 4. Get Activity Export Logs (NEW)
**Endpoint:** `GET /api/advance/finance/activity-export-logs/{activityId}`

**Description:** Get detailed export logs for a specific activity showing each export request with date & time

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
      }
    ],
    "total_exports": 3
  }
}
```

**Use Case:** View complete export history for a specific activity with precise timestamps

**See:** [Export Logs with Date & Time](EXPORT_LOGS_WITH_DATETIME.md) for complete documentation.

---

## Usage Examples

### 1. Export Payments (Excluding Already Exported)
```bash
GET /api/advance/finance/export-payments?year_id=7
```
- Exports only activities that haven't been exported yet
- Automatically marks them as exported

### 2. Export Payments (Including Already Exported)
```bash
GET /api/advance/finance/export-payments?year_id=7&include_exported=true
```
- Exports all activities including previously exported ones
- Increments export count for already exported activities

### 3. View Export History
```bash
GET /api/advance/finance/export-history?year_id=7&from_date=2026-02-01&to_date=2026-02-07
```
- Shows all exported activities with export details
- Includes statistics and pagination

### 4. Reset Export Status (Admin)
```bash
POST /api/advance/finance/reset-export-status
Content-Type: application/json

{
  "activity_ids": [123, 456]
}
```
- Resets export status for specific activities
- Allows them to be exported again

### 5. Handle Excluded Activities
```bash
GET /api/advance/finance/export-payments?year_id=7
```
**Response when all activities already exported:**
```json
{
  "success": false,
  "message": "No activities found with \"Ready for Payment\" status for export",
  "data": {
    "excluded_activities": [...],
    "excluded_count": 5,
    "total_excluded_amount": 250000.00,
    "suggestion": "All activities matching your criteria have already been exported. Use include_exported=true parameter to re-export them."
  }
}
```
- Shows which activities were excluded and why
- Provides export history for each excluded activity
- Suggests using `include_exported=true` if re-export is needed

---

## Export Tracking Flow

```
1. Finance approves advance request
   ↓
2. Activity status: "Ready for Payment"
   is_exported: false
   ↓
3. User exports payments
   ↓
4. System marks activities as exported:
   - is_exported: true
   - first_export_date: current date
   - last_export_date: current date
   - export_count: 1
   - exported_by: user_id
   - Creates export log with success status
   ↓
5. Next export excludes these activities
   (unless include_exported=true)
   ↓
6. If user tries to export again:
   - No activities available
   - Returns excluded activities list
   - Creates export log with "excluded" status
   - Logs all excluded activity details
   ↓
7. If re-exported:
   - last_export_date: updated
   - export_count: incremented
   - Creates new export log
```

---

## Benefits

1. **Prevents Duplicate Exports**
   - Activities are automatically excluded from subsequent exports
   - Reduces risk of duplicate payment processing

2. **Audit Trail**
   - Track when activities were exported
   - Track who performed the export
   - Track how many times exported

3. **Flexible Re-export**
   - Option to include already exported records if needed
   - Admin can reset export status for corrections

4. **Export Statistics**
   - Monitor export activity
   - Identify activities exported multiple times
   - Track daily export volumes

5. **Excluded Activities List**
   - When no activities are available for export, see which activities were excluded
   - Shows export history for excluded activities (dates, count, exporter)
   - Helps understand why no activities are available
   - Provides suggestion to use `include_exported=true` if re-export is needed

6. **Excluded Export Logging**
   - All excluded export attempts are logged in `advance_export_logs` table
   - Tracks who attempted the export and when
   - Stores complete details of excluded activities
   - Includes filters used and reasons for exclusion
   - Helps audit export attempts and identify patterns

---

## Migration Instructions

1. **Run Migration:**
   ```bash
   php artisan migrate
   ```

2. **Verify Columns:**
   ```sql
   DESCRIBE advance_request_activities;
   ```

3. **Check Indexes:**
   ```sql
   SHOW INDEXES FROM advance_request_activities;
   ```

---

## Notes

- Export tracking is automatic - no manual intervention needed
- Default behavior excludes already exported records
- Use `include_exported=true` parameter for re-export scenarios
- Export count helps identify activities that may need review
- Reset function is admin-only for data correction purposes

---

## Related Files

- Migration: `database/migrations/2026_02_07_120000_add_export_tracking_to_advance_request_activities.php`
- Model: `app/Models/AdvanceRequestActivity.php`
- Controller: `app/Http/Controllers/Api/Advance/AdvanceFinanceController.php`
- Routes: `routes/advance.php`
