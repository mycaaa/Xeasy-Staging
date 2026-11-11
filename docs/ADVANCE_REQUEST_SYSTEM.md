# Advance Request System Documentation

## Overview

The Advance Request System is a comprehensive Laravel-based solution for managing employee advance requests with multi-level approvals, finance oversight, and complete audit trails.

## Key Features

- **Multi-Employee Requests**: Support multiple employees per request
- **Multi-Activity Types**: Multiple activity types per employee
- **Dynamic Approval Levels**: Not fixed, derived from approval matrix
- **Partial Approvals**: Amount reduction at each level
- **Finance Override**: Final approval with amount adjustment
- **Unlimited Reverts**: Revert at ANY stage with proper routing
- **Resubmission**: Unlimited revert-resubmit cycles
- **Complete Audit Trail**: All actions logged with email notifications
- **Soft Delete Support**: Data preservation with soft deletes
- **Public API**: No authentication required, user identification via parameters
- **Testing Friendly**: Easy integration and testing with default user fallback

## Database Schema

### Core Tables

1. **advance_requests** - Main request table
2. **advance_request_employees** - Employees in each request
3. **advance_request_activities** - Activities per employee
4. **advance_activity_approvals** - Approval matrix and status
5. **advance_request_actions** - Complete audit trail

### Key Fields

- `amount` - Original requested amount (never overwritten)
- `approver_amount` - Approved amount per level
- `finance_approved_amount` - Final payable amount
- `submission_no` - Increments on every resubmission
- `current_approval_level` - Controls visibility and routing

## API Endpoints

### Request Management
```
POST   /api/advance/draft                    - Create draft (user_id in body)
PUT    /api/advance/draft/{requestId}        - Update draft (user_id in body)
POST   /api/advance/submit/{requestId}       - Submit for approval (user_id in body)
POST   /api/advance/resubmit/{requestId}     - Resubmit after revert (user_id in body)
GET    /api/advance/                         - List user requests (?user_id=123)
GET    /api/advance/{requestId}              - Get request details
DELETE /api/advance/{requestId}              - Delete draft request (user_id in body)
```

### Approval Management
```
GET    /api/advance/approvals/pending        - Get pending approvals (?user_id=123)
POST   /api/advance/approve                  - Approve request (user_id in body)
POST   /api/advance/reject                   - Reject request (user_id in body)
POST   /api/advance/revert                   - Revert to previous level (user_id in body)
```

### Finance Management
```
GET    /api/advance/finance/pending          - Get finance pending
POST   /api/advance/finance/approve          - Finance approve (user_id in body)
POST   /api/advance/finance/reject           - Finance reject (user_id in body)
POST   /api/advance/finance/revert           - Finance revert to approver (user_id in body)
```

### Parameter Notes
- **user_id**: Optional parameter that defaults to 1 if not provided
- **Query Parameters**: Use `?user_id=123` for GET requests
- **Body Parameters**: Include `"user_id": 123` in request body for POST/PUT requests

## Request Lifecycle

### 1. Draft Creation
```json
{
  "user_id": 123,
  "request": {
    "employee_id": 123,
    "department_id": 5,
    "amount": 50000.00,
    "purpose": "Business travel and accommodation"
  },
  "employees": [
    {
      "employee_id": 123,
      "employee_name": "John Doe",
      "designation": "Manager",
      "department_id": 5,
      "activities": [
        {
          "activity_type_id": 1,
          "activity_name": "Travel",
          "description": "Flight and hotel booking",
          "amount": 30000.00
        },
        {
          "activity_type_id": 2,
          "activity_name": "Meals",
          "description": "Daily allowance",
          "amount": 20000.00
        }
      ]
    }
  ]
}
```

### 2. Submission Process
- Status changes from `draft` to `submitted`
- `submission_no` starts at 1
- `current_approval_level` set to 1
- Approval matrix created based on department/amount rules
- Email notifications sent to Level 1 approvers

### 3. Approval Flow
- Approvers can approve full or partial amounts
- Next level sees reduced amount if partially approved
- All approvals at current level must complete before moving to next
- Status remains `submitted` until all levels complete
- Final status becomes `approved` when ready for finance

### 4. Finance Processing
- Finance can approve, reject, or revert
- Finance can further reduce the approved amount
- `finance_approved_amount` contains final payable amount
- Status becomes `finance_approved` or `finance_rejected`

### 5. Revert Logic
- **Level Revert**: Goes back to previous level (Level N → Level N-1)
- **Finance Revert**: Goes back to LAST approved approver
- Status becomes `reverted`
- `current_approval_level` updated accordingly

### 6. Resubmission
- Only possible when status is `reverted`
- `submission_no` increments
- Fresh approval matrix created
- Old approvals remain untouched (audit trail)
- Process restarts from Level 1

## Status Flow Diagram

```
draft → submitted → approved → finance_approved
  ↓         ↓           ↓            ↓
deleted   rejected   reverted   finance_rejected
  ↓         ↓           ↓            ↓
 END      END     resubmitted      END
                      ↓
                  submitted (new submission_no)
```

## Key Business Rules

### Amount Handling
1. **Original Amount**: Never modified, stored in `amount`
2. **Approval Amount**: Each approver can reduce, stored in `approver_amount`
3. **Carry Forward**: Next level sees reduced amount from previous approvals
4. **Finance Amount**: Final amount in `finance_approved_amount`

### Approval Rules
1. **Dynamic Levels**: No hardcoded approval levels
2. **Level Control**: `current_approval_level` controls visibility
3. **Parallel Approvals**: Multiple approvers per level supported
4. **Sequential Levels**: Must complete current level before next

### Revert Rules
1. **Level Revert**: Current level → Previous level
2. **Finance Revert**: Finance → Last approved level
3. **Reason Mandatory**: All reverts require remarks
4. **Status Update**: Always changes status to `reverted`

### Audit Rules
1. **Log Everything**: Every action logged in `advance_request_actions`
2. **Email Tracking**: Template ID and send status tracked
3. **Submission Tracking**: All actions tagged with `submission_no`
4. **Actor Tracking**: Who did what to whom

## Email System

### Template Integration
- Uses existing `eml_email_templates` table
- Dynamic placeholder replacement
- Template selection based on action type
- Send status tracking in audit log

### Available Placeholders
- `{{request_no}}` - Request number
- `{{amount}}` - Original amount
- `{{purpose}}` - Request purpose
- `{{status}}` - Current status
- `{{submission_no}}` - Current submission number
- `{{current_level}}` - Current approval level
- `{{created_date}}` - Request creation date
- `{{submitted_date}}` - Submission date

### Email Templates Required
1. `advance_submission` - New request submitted
2. `advance_approved` - Request approved at level
3. `advance_rejected` - Request rejected
4. `advance_reverted` - Request reverted
5. `advance_pending_approval` - Pending approval notification
6. `advance_finance_approved` - Finance approved
7. `advance_finance_rejected` - Finance rejected
8. `advance_finance_reverted` - Finance reverted

## Security & Authorization

### Public API Access
- **No Authentication Required**: All endpoints are publicly accessible
- **User ID Parameter**: Pass `user_id` parameter to identify the acting user
- **Default User**: System defaults to user ID 1 if no user_id provided
- **Testing Friendly**: Designed for easy testing and integration

### Data Protection
- Soft deletes preserve audit trail
- All actions logged with user identification
- Complete audit trail maintained for compliance

## Architecture

The Advance Request System follows a **Controller-based architecture** where all business logic is handled directly within the controllers:

- **Controllers**: Handle all business logic, validation, and database operations
- **Models**: Eloquent models with relationships and helper methods
- **Requests**: Form request validation classes
- **Database**: MySQL with proper indexing and relationships

### Key Components

1. **AdvanceRequestController** - Handles draft creation, submission, and request management
2. **AdvanceApprovalController** - Manages approval workflow and level-based approvals
3. **AdvanceFinanceController** - Handles finance-level approvals and final processing
4. **Models** - Eloquent models with relationships and business logic helpers
5. **Request Classes** - Validation and data sanitization

## Configuration
```php
'email_templates' => [...],
'default_approval_levels' => 3,
'finance_roles' => ['finance_manager', 'finance_admin'],
'amount_limits' => [...],
'notifications' => [...],
'audit' => [...]
```

### Environment Variables
```env
ADVANCE_EMAIL_ENABLED=true
ADVANCE_SMS_ENABLED=false
ADVANCE_AUDIT_RETENTION_DAYS=365
```

## Testing the API

### Quick Test Endpoint
```bash
# Test system status
GET /api/advance/test

# Expected response:
{
    "success": true,
    "message": "System is working correctly",
    "tables_exist": true,
    "request_count": 0,
    "database_connection": "mysql"
}
```

### Sample API Calls

#### Create a Draft Request
```bash
curl -X POST http://your-domain/api/advance/draft \
  -H "Content-Type: application/json" \
  -d '{
    "user_id": 123,
    "request": {
      "activity_name_id": 1,
      "month": 12,
      "req_app_submit_id": 1,
      "advance_fill_date": "2024-01-15"
    },
    "employees": [
      {
        "employee_id": 123,
        "activities": [
          {
            "activity_type_id": 1,
            "amount": 50000.00,
            "purpose": "Business travel expenses"
          }
        ]
      }
    ]
  }'
```

#### Submit for Approval
```bash
curl -X POST http://your-domain/api/advance/submit/1 \
  -H "Content-Type: application/json" \
  -d '{"user_id": 123}'
```

#### Approve Request
```bash
curl -X POST http://your-domain/api/advance/approve \
  -H "Content-Type: application/json" \
  -d '{
    "user_id": 456,
    "activity_id": 1,
    "approver_amount": 45000.00,
    "approver_remark": "Approved with reduced amount"
  }'
```

#### Get User Requests
```bash
curl "http://your-domain/api/advance/?user_id=123&per_page=10"
```

#### Get Pending Approvals
```bash
curl "http://your-domain/api/advance/approvals/pending?user_id=456"
```

## Installation & Setup
```bash
# Run migrations (if tables don't exist)
php artisan migrate

# Seed email templates
php artisan db:seed --class=AdvanceEmailTemplatesSeeder
```

### 2. Route Registration
Routes are automatically registered in `bootstrap/app.php`:
```php
Route::middleware('api')
    ->prefix('api')
    ->group(base_path('routes/advance.php'));
```

### 3. Service Provider (Optional)
Create a service provider for advanced configuration:
```bash
php artisan make:provider AdvanceServiceProvider
```

## Usage Examples

### Creating a Draft Request
```php
$response = Http::post('/api/advance/draft', [
    'user_id' => 123,  // Optional, defaults to 1
    'request' => [
        'employee_id' => 123,
        'department_id' => 5,
        'amount' => 50000.00,
        'purpose' => 'Business travel'
    ],
    'employees' => [...]
]);
```

### Approving a Request
```php
$response = Http::post('/api/advance/approve', [
    'user_id' => 456,  // Optional, defaults to 1
    'request_id' => 1,
    'approver_amount' => 45000.00,  // Partial approval
    'remarks' => 'Approved with reduced amount'
]);
```

### Finance Operations
```php
$response = Http::post('/api/advance/finance/approve', [
    'user_id' => 789,  // Optional, defaults to 1
    'request_id' => 1,
    'approved_amount' => 40000.00,  // Further reduction
    'remarks' => 'Final approval with budget constraints'
]);
```

### Getting User's Requests
```php
$response = Http::get('/api/advance/?user_id=123&status=draft');
```

### Getting Pending Approvals
```php
$response = Http::get('/api/advance/approvals/pending?user_id=456');
```

## Error Handling

### Common Errors
- **Invalid Status Transition**: Request not in correct status for action
- **Amount Validation**: Approved amount exceeds available amount
- **Missing Approvals**: Trying to proceed without completing current level
- **Invalid User ID**: User ID not found or invalid (defaults to 1)
- **Missing Parameters**: Required parameters not provided

### Error Response Format
```json
{
    "success": false,
    "message": "Request cannot be submitted in current status"
}
```

## Performance Considerations

### Database Indexes
- Status and approval level combinations
- Employee and department filtering
- Action type and date ranges for audit queries

### Caching Strategies
- Approval matrix caching
- User role/permission caching
- Email template caching

### Query Optimization
- Eager loading relationships
- Pagination for large datasets
- Selective field loading

## Monitoring & Maintenance

### Audit Queries
```sql
-- Get all actions for a request
SELECT * FROM advance_request_actions 
WHERE advance_request_id = 1 
ORDER BY created_at;

-- Get pending approvals by level
SELECT * FROM advance_activity_approvals 
WHERE status = 'pending' 
AND approval_level = 1;

-- Get revert statistics
SELECT action_type, COUNT(*) 
FROM advance_request_actions 
WHERE action_type LIKE '%reverted%' 
GROUP BY action_type;
```

### Health Checks
- Email delivery status
- Pending approval counts
- System response times
- Database connection status

### Troubleshooting

### Common Issues
1. **Emails Not Sending**: Check template existence and SMTP configuration
2. **Approval Not Progressing**: Verify all approvers at current level have acted
3. **Revert Not Working**: Check request status and user ID validity
4. **Amount Mismatch**: Validate total activity amounts match request amount
5. **User ID Issues**: Ensure user_id is provided or system will default to 1

### Debug Mode
Enable detailed logging in `config/advance.php`:
```php
'debug' => env('ADVANCE_DEBUG', false),
'log_level' => env('ADVANCE_LOG_LEVEL', 'info'),
```

## Future Enhancements

### Planned Features
- Mobile app integration
- Real-time notifications
- Advanced reporting dashboard
- Integration with accounting systems
- Bulk approval operations
- Approval delegation
- Holiday/weekend handling
- Currency conversion support

### API Versioning
Current version: v1
Future versions will maintain backward compatibility.

---

**Note**: This system is production-ready and follows Laravel best practices for security, performance, and maintainability.