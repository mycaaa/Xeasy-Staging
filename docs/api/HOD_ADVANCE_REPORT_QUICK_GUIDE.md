# HOD Advance Report API - Quick Reference

## Quick Start

HOD can view advance requests of team members based on `hrm_employee_reporting` table where `HodId` = HOD's employee ID.

---

## Endpoints Summary

| Endpoint | Description | Key Parameters |
|----------|-------------|----------------|
| `GET /api/advance/hod/consolidated-report` | **Single-table view** - One row per employee per month (paid only) | `hod_id`, `year`, `month`, `employee_id` |
| `GET /api/advance/hod/activity-wise-report` | Detailed activities with month breakdown | `hod_id`, `year`, `month`, `status` |
| `GET /api/advance/hod/employee-wise-report` | Employee summary with statistics | `hod_id`, `year`, `month` |
| `GET /api/advance/hod/team-members` | List of team members | `hod_id` |

---

## Quick Examples

### 1. Get Consolidated Report (Recommended for Simple View)
```bash
curl -X GET "/api/advance/hod/consolidated-report?hod_id=123&month=2&year=2024"
```

**Returns:** One row per employee per month showing all paid advance totals

### 2. Get Current Month Activity Report
```bash
curl -X GET "/api/advance/hod/activity-wise-report?hod_id=123&month=2&year=2024"
```

**Returns:** Month-wise breakdown with all activities per employee

### 2. Get Employee Summary for Year
```bash
curl -X GET "/api/advance/hod/employee-wise-report?hod_id=123&year=2024"
```

**Returns:** Statistics per employee grouped by month

### 3. Get Team Members List
```bash
curl -X GET "/api/advance/hod/team-members?hod_id=123"
```

**Returns:** All employees reporting to this HOD

### 4. Filter by Status
```bash
curl -X GET "/api/advance/hod/activity-wise-report?hod_id=123&status=In_Approval"
```

**Returns:** Only activities with "In_Approval" status

---

## Response Structure

### Activity-wise Report
```
Month
├── Employee 1
│   ├── Activity 1 (detailed info)
│   ├── Activity 2
│   └── Summary (counts & totals)
├── Employee 2
│   └── ...
└── Month Totals
```

### Employee-wise Report
```
Month
├── Employee 1 (statistics only)
├── Employee 2 (statistics only)
└── Month Summary (aggregated)
```

---

## Common Filters

| Filter | Parameter | Example | Description |
|--------|-----------|---------|-------------|
| Specific Month | `month=2` | February | Month 1-12 |
| Year | `year=2024` | 2024 | Defaults to current year |
| Status | `status=Finance` | Finance level | Filter by request status |
| All Months | No month param | Full year | All months in year |

---

## Key Metrics Available

### Activity-wise Report Provides:
- Individual activity details
- Crop information
- Amount breakdowns (requested, approved, finance approved, paid)
- Status at each stage
- Purpose and dates
- Employee summary per month

### Employee-wise Report Provides:
- Total activities count
- Amount statistics (requested, approved, finance approved, paid)
- Activity counts by status (approved, pending, rejected)
- Approval rate percentage
- Month-level aggregations

---

## Status Flow Reference

```
Draft → Submitted → In_Approval → Approved → Finance → Finance_Approved → Payment → Closed → Finalized
  ↓         ↓            ↓            ↓          ↓            ↓
Rejected  Rejected    Rejected    Reverted  Finance_Rejected  Reverted
```

---

## Use Case Scenarios

### Scenario 1: Monthly Team Review
**Need:** Review all team activities for current month  
**Endpoint:** `GET /hod/activity-wise-report?hod_id=123&month=2&year=2024`  
**Result:** Detailed view of all activities with employee breakdown

### Scenario 2: Employee Performance
**Need:** Check specific employee's advance request pattern  
**Endpoint:** `GET /hod/employee-wise-report?hod_id=123&year=2024`  
**Result:** Month-by-month statistics per employee

### Scenario 3: Pending Items
**Need:** See what's waiting for approval  
**Endpoint:** `GET /hod/activity-wise-report?hod_id=123&status=In_Approval`  
**Result:** All activities currently in approval process

### Scenario 4: Year-end Report
**Need:** Annual summary for budget planning  
**Endpoint:** `GET /hod/employee-wise-report?hod_id=123&year=2024`  
**Result:** Complete year statistics by employee and month

### Scenario 5: Team Structure
**Need:** Know who reports to me  
**Endpoint:** `GET /hod/team-members?hod_id=123`  
**Result:** List of all team members with details

---

## Response Format

### Success Response
```json
{
  "success": true,
  "message": "Report retrieved successfully",
  "data": [ /* report data */ ]
}
```

### Error Response
```json
{
  "success": false,
  "message": "Error description"
}
```

---

## Important Notes

1. **HOD ID Required**: All endpoints require `hod_id` parameter
2. **Team Based**: Only shows data for employees where `hrm_employee_reporting.HodId` matches
3. **Month Format**: Use 1-12 for months (1=January, 12=December)
4. **Year Default**: Current year used if not specified
5. **Empty Results**: Returns empty array if no data (not an error)
6. **Sorting**: Results sorted by month descending (latest first)

---

## Data Breakdown

### Activity-wise Report Shows:
- Request ID and status
- Activity name and type
- Crops involved
- Amounts at each stage
- Approval/Finance/Payment status
- Purpose and dates
- Employee details

### Employee-wise Report Shows:
- Employee information
- Activity counts (total, approved, pending, rejected)
- Amount totals (requested, approved, finance approved, paid)
- Approval rate percentage
- Month-wise breakdown
- Aggregated month summary

---

## Quick Tips

1. **For detailed audit**: Use activity-wise report
2. **For quick overview**: Use employee-wise report
3. **For team structure**: Use team-members endpoint
4. **Filter by month**: Add `month` parameter for specific month
5. **Filter by status**: Add `status` parameter to see specific stage
6. **Year comparison**: Call with different `year` values

---

## Common Status Values

| Status | Meaning |
|--------|---------|
| `Draft` | Not yet submitted |
| `In_Approval` | Under approval process |
| `Finance` | At finance level |
| `Finance_Approved` | Finance approved |
| `Payment` | Payment processing |
| `Closed` | Payment completed |
| `Rejected` | Rejected at any stage |
| `Reverted` | Sent back for changes |

---

## Integration Example

```javascript
// Get current month activity report
async function getTeamActivities(hodId) {
  const currentDate = new Date();
  const month = currentDate.getMonth() + 1;
  const year = currentDate.getFullYear();
  
  const response = await fetch(
    `/api/advance/hod/activity-wise-report?hod_id=${hodId}&month=${month}&year=${year}`
  );
  
  const data = await response.json();
  
  if (data.success) {
    console.log('Team activities:', data.data);
    return data.data;
  } else {
    console.error('Error:', data.message);
    return null;
  }
}

// Get employee summary
async function getEmployeeSummary(hodId, year = new Date().getFullYear()) {
  const response = await fetch(
    `/api/advance/hod/employee-wise-report?hod_id=${hodId}&year=${year}`
  );
  
  const data = await response.json();
  return data.success ? data.data : null;
}

// Get team members
async function getTeamMembers(hodId) {
  const response = await fetch(
    `/api/advance/hod/team-members?hod_id=${hodId}`
  );
  
  const data = await response.json();
  return data.success ? data.data : [];
}
```

---

## Troubleshooting

| Issue | Solution |
|-------|----------|
| Empty results | Check if HOD ID is correct and has team members in `hrm_employee_reporting` |
| No data for month | Verify team members submitted requests in that month |
| Wrong team members | Verify `HodId` in `hrm_employee_reporting` table |
| Slow response | Use month filter to reduce data volume |
| Missing employee details | Check HRIMS database connection |
