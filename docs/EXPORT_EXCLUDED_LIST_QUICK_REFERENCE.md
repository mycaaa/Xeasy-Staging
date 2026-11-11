# Export Excluded List - Quick Reference

## What Is It?
When attempting to export advance payments, if all activities have already been exported, the API returns a detailed list of excluded activities instead of just an error.

## API Endpoint
```
GET /api/advance/finance/export-payments
```

## Response When No Activities Available

```json
{
  "success": false,
  "message": "No activities found with \"Ready for Payment\" status for export",
  "data": {
    "excluded_activities": [...],
    "excluded_count": 5,
    "total_excluded_amount": 250000.00,
    "suggestion": "Use include_exported=true to re-export"
  }
}
```

## Excluded Activity Fields

| Field | Description |
|-------|-------------|
| `activity_id` | Activity ID |
| `employee_name` | Employee name |
| `amount` | Activity amount |
| `export_count` | Times exported |
| `first_export_date` | First export date |
| `last_export_date` | Last export date |
| `exporter_name` | Who exported |
| `reason` | "Already exported" |

## Common Scenarios

### Scenario 1: All Activities Exported
**Action:** Try to export
**Result:** Get excluded list showing all previously exported activities

### Scenario 2: Need to Re-export
**Action:** Add `include_exported=true` parameter
**Result:** Activities are re-exported, export count increments

### Scenario 3: Check Export Status
**Action:** Try to export with specific filters
**Result:** See which activities were already exported and when

## Frontend Handling

```javascript
try {
  const response = await exportPayments(filters);
  // Success - file downloaded
} catch (error) {
  if (error.response?.data?.data?.excluded_activities) {
    // Show excluded list to user
    showExcludedActivitiesModal(error.response.data.data);
  }
}
```

## Quick Actions

| Action | How To |
|--------|--------|
| View excluded list | Try to export, check response data |
| Re-export all | Add `include_exported=true` parameter |
| Reset export status | POST to `/api/advance/finance/reset-export-status` |
| View full history | GET `/api/advance/finance/export-history` |

## Benefits
✅ Know exactly why export failed
✅ See export history for each activity
✅ Make informed re-export decisions
✅ Reduce support tickets

## Related Docs
- Full Feature: `docs/EXPORT_EXCLUDED_LIST_FEATURE.md`
- Implementation: `docs/EXPORT_EXCLUDED_LIST_IMPLEMENTATION.md`
- Export Tracking: `docs/ADVANCE_PAYMENT_EXPORT_TRACKING.md`
