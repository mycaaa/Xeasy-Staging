# Department-Specific Activities Update

## Overview
Updated the `getActivities()` function to fetch activities based on department configuration from the `adv_activity_names` table, which contains department-specific activity mappings.

## Database Structure Analysis

### Tables Involved:
1. **`adv_activity_names`**: Contains activity configurations per department
   - `activity_name`: References `claimtype.ClaimId`
   - `dept_id`: Comma-separated department IDs (e.g., "15", "11,13")
   - `status`: Active/inact