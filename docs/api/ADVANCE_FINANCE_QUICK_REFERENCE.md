# Advance Finance API - Quick Reference

## Important Note
**The `/api/advance/finance/pending` endpoint returns the EXACT same JSON structure as `/api/advance` endpoint, but filtered for finance-ready requests.**

## Endpoints Summary

| Method | Endpoint | Description | Key Parameters |
|--------|----------|-------------|----------------|
| GET | `/api/advance/finance/pending` | Get pending finance approvals | `user_id` |
| POST | `/api/advance/finance/approve` | Approve employee activities | `user_id`, `employee_id`, `request_id` |
| POST | `/api/advance/finance/reject` | Reject employee activities | `user_id`, `employee_id`, `request_id`, `remarks` |
| POST | `/api/advance/finance/revert` | Revert employee activities | `user_id`, `employee_id`, `request_id`, `remarks` |

## Quick Examples

### 1. Get Pending Approvals
```bash
curl -X GET "/api/advance/finance/pending?user_id=456"
```

### 2. Approve All Activities for Employee
```bash
curl -X POST "/api/advance/finance/approve" \
  -H "Content-Type: application/json" \
  -d '{
    "user_id": 456,
    "employee_id": "EMP001", 
    "request_id": 5,
    "finance_approved_amount": 9500.00,
    "finance_remark": "Approved with adjustment"
  }'
```

### 3. Approve Specific Activity
```bash
curl -X POST "/api/advance/finance/approve" \
  -H "Content-Type: application/json" \
  -d '{
    "user_id": 456,
    "employee_id": "EMP001",
    "request_id": 5, 
    "activity_id": 10,
    "finance_approved_amount": 5000.00
  }'
```

### 4. Reject Activities
```bash
curl -X POST "/api/advance/finance/reject" \
  -H "Content-Type: application/json" \
  -d '{
    "user_id": 456,
    "employee_id": "EMP001",
    "request_id": 5,
    "remarks": "Exceeds policy limits"
  }'
```

### 5. Revert Activities
```bash
curl -X POST "/api/advance/finance/revert" \
  -H "Content-Type: application/json" \
  -d '{
    "user_id": 456,
    "employee_id": "EMP001", 
    "request_id": 5,
    "remarks": "Need additional documentation"
  }'
```

## Response Structure

### Success Response
```json
{
  "success": true,
  "message": "Operation completed successfully",
  "data": { /* response data */ }
}
```

### Error Response  
```json
{
  "success": false,
  "message": "Error description"
}
```

## Key Business Rules

1. **Employee-Level Processing**: All operations work at employee level within a request
2. **Activity Status**: Only `'Approved'` activities with `finance_status = null` can be processed
3. **Amount Distribution**: If multiple activities exist, approved amount is distributed proportionally
4. **Status Updates**: Request status automatically updates based on activity processing completion
5. **Audit Trail**: All actions are logged with timestamps and user information
6. **Email Notifications**: Automatic emails sent to requester on finance actions

## Common Use Cases

### Scenario 1: Approve All Activities for Employee
- Use: When finance approves all activities for an employee
- Endpoint: `POST /finance/approve`
- Required: `employee_id`, `request_id`
- Optional: `finance_approved_amount` (defaults to full amount)

### Scenario 2: Partial Approval
- Use: When finance approves with reduced amount
- Endpoint: `POST /finance/approve` 
- Include: `finance_approved_amount` with reduced value
- Result: Amount distributed proportionally across activities

### Scenario 3: Selective Activity Processing
- Use: When finance wants to process only specific activity
- Endpoint: `POST /finance/approve|reject|revert`
- Include: `activity_id` parameter
- Result: Only specified activity is processed

### Scenario 4: Bulk Rejection
- Use: When finance rejects all activities for policy reasons
- Endpoint: `POST /finance/reject`
- Required: `remarks` explaining rejection reason
- Result: All approved activities for employee marked as rejected

## Status Flow Diagram

```
Request Status Flow:
In_Approval → Finance → Closed/Rejected/Reverted
     ↓           ↓
   (some      (all approved
 activities   activities
 approved)    processed)

Activity Status Flow:
Approved + finance_status=null → Approved + finance_status='Approved'/'Rejected'
Approved → Reverted (with updated approval_level)
```