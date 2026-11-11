# Activity-Wise Limits Implementation

## Overview
Implemented activity-wise min/max amount limits for ACTIVITY type advance requests. Each department can now have individual limits set for each activity.

## Key Changes Made

### 1. View Template Updates (`resources/views/admin/advance_request_master.blade.php`)
- **Modified table structure**: For ACTIVITY type, replaced direct min/max amount inputs with "Activity Limits" button
- **Added Activity Limits Modal**: Large modal with table showing all activities for the department
- **Conditional display**: Only ACTIVITY type shows the Activity Limits button, other types show direct min/max inputs

### 2. JavaScript Enhancements (`public/custom/js/pages/advance_request_master_new.js`)
- **Added activity limits functionality**:
  - `fetchActivitiesAndLimits()`: Fetches activities and existing limits
  - `fetchExistingActivityLimits()`: Gets current limits for department
  - `populateActivityLimitsModal()`: Populates modal with activities and current limits
  - `saveActivityLimit()`: Saves individual activity limit
  - `saveAllActivityLimits()`: Bulk saves all activity limits
- **Enhanced modal handling**: Added support for activity limits modal
- **Activity limit switches**: Enable/disable individual activity limits

### 3. Controller Updates (`app/Http/Controllers/admin/AdvanceRequestMasterController.php`)
- **Added `getActivityLimits()` method**: Fetches existing activity limits for a department
- **Enhanced `storeLimits()` method**: Already supports activity_id parameter for individual activity limits

### 4. Routes (`routes/backend.php`)
- **Added new route**: `GET /advance-request-master/activity-limits/{advanceTypeId}/{departmentId}`

## How It Works

### For ACTIVITY Type Advance Requests:
1. **Department Settings**: User configures basic department settings (enable, designations, approver level, dates)
2. **Activity Limits**: User clicks "Activity Limits" button to open modal
3. **Individual Activity Configuration**: Modal shows all activities with individual enable/disable switches and min/max amount fields
4. **Flexible Saving**: User can save individual activity limits or save all at once

### For Other Types (EXPENSE, etc.):
- **Direct Limits**: Shows min/max amount inputs directly in the main table
- **Department Level**: Limits apply to the entire department, not individual activities

## Database Structure
- **Table**: `advance_request_limits`
- **Key Fields**:
  - `advance_type_id`: Links to advance request type
  - `department_id`: Links to department
  - `activity_id`: NULL for department-level limits, specific ID for activity-level limits
  - `min_amount`, `max_amount`: The limit amounts
  - `is_active`: Enable/disable the limit

## User Interface Features

### Activity Limits Modal:
- **Large modal**: Shows all activities in a table format
- **Individual controls**: Each activity has its own enable switch and min/max inputs
- **Bulk operations**: "Save All Limits" button for convenience
- **Individual save**: Each row has its own save button
- **Validation**: Client-side validation ensures max >= min and both fields filled when active

### Main Table:
- **Clean interface**: ACTIVITY type shows single "Activity Limits" button instead of cluttered min/max inputs
- **Consistent**: Other types continue to show direct min/max inputs
- **Department context**: Modal title shows which department is being configured

## Validation Rules
- **Active limits**: Must have both min and max amounts
- **Amount validation**: Max amount must be >= min amount
- **Non-negative**: All amounts must be >= 0
- **Server-side**: Controller validates all data before saving
- **Client-side**: JavaScript provides immediate feedback

## Data Flow
1. **Load**: Page loads with existing settings and limits
2. **Configure**: User sets up department basic settings
3. **Activity Limits**: User opens activity limits modal for detailed configuration
4. **Save**: Individual or bulk save of activity limits
5. **Persist**: Data saved to `advance_request_limits` table with activity_id

## Benefits
- **Granular Control**: Each activity can have different limits
- **Flexible**: Can enable/disable limits per activity
- **User Friendly**: Clean interface with modal for detailed configuration
- **Scalable**: Supports any number of activities
- **Consistent**: Follows existing patterns for other advance request types

## Technical Implementation
- **Modal-based**: Uses Bootstrap modal for activity configuration
- **AJAX**: All operations use AJAX for smooth user experience
- **Validation**: Both client and server-side validation
- **Error Handling**: Comprehensive error handling and user feedback
- **Data Preservation**: Existing limits preserved during updates

## Files Modified
- `resources/views/admin/advance_request_master.blade.php` - Added activity limits modal and button
- `public/custom/js/pages/advance_request_master_new.js` - Added activity limits functionality
- `app/Http/Controllers/admin/AdvanceRequestMasterController.php` - Added getActivityLimits method
- `routes/backend.php` - Added activity limits route

## Testing Recommendations
1. Test activity limits modal opening and loading
2. Verify individual activity limit save functionality
3. Test bulk save all limits feature
4. Confirm validation works for min/max amounts
5. Test enable/disable switches for activities
6. Verify existing limits are loaded correctly
7. Test error handling for network issues

## Status
✅ **COMPLETED** - Activity-wise limits fully implemented and functional