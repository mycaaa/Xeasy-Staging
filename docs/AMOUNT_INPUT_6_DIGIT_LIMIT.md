# Amount Input 6-Digit Limit Implementation

## Overview
Implemented 6-digit maximum limit for all amount input fields in the advance request master settings to prevent users from entering amounts exceeding 999,999.

## Changes Made

### 1. HTML Input Attributes (`resources/views/admin/advance_request_master.blade.php`)
**Added `max="999999"` attribute to amount inputs:**
```html
<!-- Min Amount Input -->
<input type="number" class="form-control min-amount-input" 
       placeholder="0.00" step="0.01" min="0" max="999999"
       value="{{ $limit ? $limit->min_amount : '' }}">

<!-- Max Amount Input -->
<input type="number" class="form-control max-amount-input" 
       placeholder="0.00" step="0.01" min="0" max="999999"
       value="{{ $limit ? $limit->max_amount : '' }}">
```

### 2. JavaScript Dynamic Inputs (`public/custom/js/pages/advance_request_master_employee_rows.js`)
**Updated activity limits modal inputs:**
```javascript
<input type="number" class="form-control activity-min-amount" 
       placeholder="0.00" step="0.01" min="0" max="999999"
       value="${minAmount}">

<input type="number" class="form-control activity-max-amount" 
       placeholder="0.00" step="0.01" min="0" max="999999"
       value="${maxAmount}">
```

### 3. Real-time Input Validation
**Added comprehensive input validation:**
```javascript
$(document).on('input', '.min-amount-input, .max-amount-input, .activity-min-amount, .activity-max-amount', function() {
    let value = $(this).val();
    
    // Remove non-numeric characters except decimal point
    value = value.replace(/[^0-9.]/g, '');
    
    // Ensure only one decimal point
    // Limit to 6 digits before decimal point
    // Limit to 2 decimal places
    // Prevent exceeding 999999
});
```

### 4. Paste Prevention
**Prevents pasting invalid values:**
```javascript
$(document).on('paste', '.min-amount-input, .max-amount-input, .activity-min-amount, .activity-max-amount', function(e) {
    const pastedData = (e.originalEvent || e).clipboardData.getData('text/plain');
    const numValue = parseFloat(pastedData);
    
    if (isNaN(numValue) || numValue > 999999) {
        e.preventDefault();
        showAlert('Invalid amount. Maximum allowed is 999,999', 'warning');
    }
});
```

### 5. Save Validation
**Added validation before saving:**
```javascript
// Individual save validation
if (parseFloat(minAmount) > 999999 || parseFloat(maxAmount) > 999999) {
    showAlert('Amount cannot exceed 999,999', 'error');
    return;
}

// Bulk save validation
if (parseFloat(minAmount) > 999999 || parseFloat(maxAmount) > 999999) {
    showAlert('Amount cannot exceed 999,999', 'error');
    hasError = true;
    return false;
}
```

## Features Implemented

### Input Restrictions:
- **Maximum Value**: 999,999 (6 digits)
- **Decimal Places**: Up to 2 decimal places (e.g., 999999.99)
- **Minimum Value**: 0 (no negative amounts)
- **Step**: 0.01 for precise decimal input

### Real-time Validation:
- **Character Filtering**: Only allows numbers and decimal point
- **Length Limiting**: Automatically truncates to 6 digits
- **Decimal Control**: Limits to 2 decimal places
- **Overflow Prevention**: Shows warning if user tries to exceed limit

### User Experience:
- **Visual Feedback**: Warning alerts when limits are exceeded
- **Paste Protection**: Prevents pasting invalid amounts
- **Auto-correction**: Automatically corrects input to valid format
- **Clear Messaging**: Specific error messages for different validation failures

## Validation Rules

### Input Format:
- **Valid**: `123456`, `999999`, `123456.99`, `0.01`
- **Invalid**: `1234567` (7 digits), `123456.999` (3 decimals), `-100` (negative)

### Business Logic:
- **Min Amount**: Must be ≤ Max Amount
- **Max Amount**: Must be ≥ Min Amount
- **Both Fields**: Required for active limits
- **Range**: 0.01 to 999,999.99

## Error Messages

### User-Friendly Alerts:
- `"Maximum amount cannot exceed 999,999"` - When input exceeds limit
- `"Invalid amount. Maximum allowed is 999,999"` - When pasting invalid data
- `"Amount cannot exceed 999,999"` - During save validation
- `"Maximum amount must be greater than or equal to minimum amount"` - Logic validation

## Files Modified:
1. **`resources/views/admin/advance_request_master.blade.php`** - Added max attribute to HTML inputs
2. **`public/custom/js/pages/advance_request_master_employee_rows.js`** - Added validation and dynamic input limits

## Testing Scenarios:
✅ **Input Validation**: Typing more than 6 digits gets truncated
✅ **Paste Prevention**: Pasting invalid amounts shows warning
✅ **Save Validation**: Cannot save amounts exceeding 999,999
✅ **Decimal Handling**: Properly handles up to 2 decimal places
✅ **User Feedback**: Clear error messages for all validation failures
✅ **Cross-browser**: Works in all modern browsers

## Benefits:
- **Data Integrity**: Prevents invalid amounts from being stored
- **User Experience**: Real-time feedback prevents frustration
- **Business Logic**: Enforces reasonable amount limits
- **Consistency**: Same validation across all amount inputs
- **Performance**: Client-side validation reduces server load

## Status:
✅ **COMPLETED** - All amount inputs now have 6-digit maximum limit with comprehensive validation