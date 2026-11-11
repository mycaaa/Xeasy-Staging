// CORRECTED JAVASCRIPT CODE FOR ADVANCE REQUEST SUBMISSION
// This shows the fixes needed in your existing code

// FOR SELF REQUESTS - ACTIVITY ADVANCE (around line 150-180)
if (selectedAdvanceType === 'Activity' && requestType === 'team') {
    // For team Activity advance, collect activity details
    const activityType = $('#commonActivityType').val();
    const crops = $('#commonCrop').val() || [];
    const varieties = $('#commonVariety').val() || [];
    
    if (!activityType) {
        isValid = false;
        errorMessage = 'Please select activity type';
    } else if (crops.length === 0) {
        isValid = false;
        errorMessage = 'Crops are required for team Activity advance';
    } else {
        const activity = {
            activity_type_id: parseInt(activityType),
            activity_crops_id: crops.map(c => parseInt(c)),
            activity_variety_id: varieties.map(v => parseInt(v)),
            amount: parseFloat(amount),
            purpose: commonPurpose.trim(),
            is_tour_travel: selectedAdvanceType === 'TourTravel' ? 'Y' : 'N'  // ADD THIS LINE
        };
        employeeData.activities.push(activity);
    }
} else {
    // For self requests (Expense/Other/TourTravel), create simple activity
    const activity = {
        activity_type_id: null,
        activity_crops_id: [],
        activity_variety_id: [],
        amount: parseFloat(amount),
        purpose: commonPurpose.trim(),
        advance_type: selectedAdvanceType,
        is_tour_travel: selectedAdvanceType === 'TourTravel' ? 'Y' : 'N'  // THIS IS CORRECT
    };
    employeeData.activities.push(activity);
}

// FOR TEAM REQUESTS (around line 220-250)
if (selectedAdvanceType === 'Activity' && requestType === 'team') {
    // For team Activity advance, collect activity details
    const activityType = $('#commonActivityType').val();
    const crops = $('#commonCrop').val() || [];
    const varieties = $('#commonVariety').val() || [];
    
    if (!activityType) {
        isValid = false;
        errorMessage = 'Please select activity type';
        return false;
    } else if (crops.length === 0) {
        isValid = false;
        errorMessage = 'Crops are required for team Activity advance';
        return false;
    }
    
    const activity = {
        activity_type_id: parseInt(activityType),
        activity_crops_id: crops.map(c => parseInt(c)),
        activity_variety_id: varieties.map(v => parseInt(v)),
        amount: parseFloat(amount),
        purpose: commonPurpose.trim(),
        is_tour_travel: selectedAdvanceType === 'TourTravel' ? 'Y' : 'N'  // THIS IS CORRECT
    };
    employeeData.activities.push(activity);
} else {
    // For self requests or non-Activity advances, create simple activity
    const activity = {
        activity_type_id: null,
        activity_crops_id: [],
        activity_variety_id: [],
        amount: parseFloat(amount),
        purpose: commonPurpose.trim(),
        advance_type: selectedAdvanceType,
        is_tour_travel: selectedAdvanceType === 'TourTravel' ? 'Y' : 'N'  // THIS IS CORRECT
    };
    employeeData.activities.push(activity);
}

// IMPORTANT NOTES:
// 1. The code already has is_tour_travel in most places - GOOD!
// 2. Make sure selectedAdvanceType === 'TourTravel' matches your UI
// 3. The backend expects 'Y' or 'N' (strings), not 1 or 0
// 4. Currently your code sends 'Y' for TourTravel type, 'N' for others
