# Advance Request Limits Feature

## Overview
This feature allows setting minimum and maximum amount limits for advance requests on a department-wise basis, with optional activity-specific limits for Activity-type advances.

## Database Structure

### Table: `advance_request_limits`
- **id**: Primary key
- **advance_type_id**: Foreign key to advance_request_types
- **department_id**: Department ID (from hrims.core_departments)
- **activity_id**: Optional activity ID (from hrims.claimtype where cgId = 7)
- **min_amount**: Minimum allowed amount (decimal 15,2)
- **max_amount**: Maximum allowed amount (decimal 15,2)
- **is_active**: Boolean flag to enable/disable limit
- **effective_from**: Start date for the limit
- **effective_to**: End date for the limit (nullable)
- **created_by**: User who created the record
- **updated_by**: User who last updated the record
- **timestamps**: created_at, updated_at

### Indexes
- `idx_adv_type_dept`: (advance_type_id, department_id)
- `idx_adv_type_dept_activity`: (advance_type_id, department_id, activity_id)
- `idx_is_active`: (is_active)
- `idx_effective_from`: (effective_from)

## Features

### 1. **Department-wise Limits**
- Set different min/max amounts for each department
- Separate limits for different advance types (Activity, Expense, etc.)

### 2. **Activity-specific Limits** (for Activity type)
- Optional activity selection from claimtype table (cgId = 7)
- Different limits can be set for different activities within the same department

### 3. **Date-based Effectiveness**
- Limits can have effective from/to dates
- Supports overlapping periods for different limit configurations

### 4. **Enable/Disable Functionality**
- Individual limits can be activated or deactivated
- Preserves configuration when disabled (similar to master settings)

## API Endpoints

### Store Limits
- **URL**: `POST /advance-request-master/limits`
- **Purpose**: Create or update advance request limits
- **Parameters**:
  - `advance_type_id` (required)
  - `department_id` (required)
  - `activity_id` (optional, for Activity type)
  - `min_amount` (required when active)
  - `max_amount` (required when active, must be >= min_amount)
  - `is_active` (boolean)
  - `effective_from` (required when active)
  - `effective_to` (optional)

### Get Activities
- **URL**: `GET /advance-request-master/activities`
- **Purpose**: Fetch activities from claimtype table for Activity-type advances
- **Returns**: List of activities with id and activity_name

## Model Methods

### AdvanceRequestLimit Model
- `scopeActive()`: Filter only active limits
- `scopeEffective($date)`: Filter limits effective on given date
- `getLimitForDepartment($advanceTypeId, $departmentId, $activityId)`: Get applicable limit

## UI Components

### 1. **Limits Table**
- Displayed below the main settings table for each advance type
- Shows department-wise limit configuration
- Includes activity dropdown for Activity-type advances

### 2. **Form Fields**
- Enable/Disable switch
- Activity dropdown (Activity type only)
- Min/Max amount inputs (number with 2 decimal places)
- Effective from/to date pickers
- Save button with confirmation modal

### 3. **Validation**
- Min amount must be >= 0
- Max amount must be >= min amount
- Effective from date required when active
- Activity selection available for Activity type only

## JavaScript Functionality

### File: `advance_request_limits.js`
- Handles limit form interactions
- Validates min/max amount relationships
- Manages enable/disable state
- AJAX submission with confirmation modal
- Preserves data when toggling active state

## Usage Examples

### Setting Department Limits
1. Navigate to Advance Request Master page
2. Select the appropriate advance type tab
3. Scroll to the "Amount Limits" section
4. Enable the limit for desired department
5. Set min/max amounts
6. Select effective dates
7. For Activity type, optionally select specific activity
8. Save the configuration

### Querying Limits in Code
```php
// Get limit for specific department and advance type
$limit = AdvanceRequestLimit::getLimitForDepartment(
    $advanceTypeId, 
    $departmentId, 
    $activityId // optional
);

if ($limit) {
    $minAmount = $limit->min_amount;
    $maxAmount = $limit->max_amount;
}
```

## Integration Points

### With Advance Request Processing
- Limits can be checked during advance request creation
- Validation against min/max amounts before approval
- Activity-specific limits for detailed control

### With Existing Master Settings
- Works alongside existing approval workflows
- Complements designation and employee-based settings
- Same department structure and UI patterns

## Benefits

1. **Financial Control**: Prevent excessive advance requests
2. **Department-specific Rules**: Different limits for different departments
3. **Activity-based Granularity**: Fine-grained control for Activity advances
4. **Temporal Flexibility**: Date-based effectiveness for changing policies
5. **Easy Management**: Integrated UI with existing master settings
6. **Data Preservation**: Settings preserved when temporarily disabled

## Future Enhancements

1. **Employee-specific Limits**: Individual limits overriding department limits
2. **Approval Level Integration**: Different limits for different approval levels
3. **Currency Support**: Multi-currency limit management
4. **Audit Trail**: Track limit changes and usage
5. **Reporting**: Limit utilization and compliance reports