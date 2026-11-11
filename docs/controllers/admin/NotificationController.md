# NotificationController Documentation

## Overview

The `NotificationController` manages automated email notifications for expense submission reminders in the Xeasy expense management system. It handles the complex logic of determining when to send reminders to employees who haven't submitted their monthly expenses, tracking email delivery, and preventing duplicate notifications.

## Class Information

- **Namespace**: `App\Http\Controllers\Admin`
- **Extends**: `Controller`
- **Location**: `app/Http/Controllers/admin/NotificationController.php`

## Dependencies

- `Carbon\Carbon` - Date/time manipulation
- `Dcblogdev\LaravelSentEmails\Models\Sentemail` - Email tracking
- `Illuminate\Support\Facades\DB` - Database operations
- `Illuminate\Support\Facades\Mail` - Email sending
- `Symfony\Component\Mime\Part\TextPart` - Email content formatting

## Methods

### submissionReminder()

**Purpose**: Displays the submission reminder management interface.

**Route**: `GET /admin/submission-reminder`

**Parameters**: None

**Returns**: View `admin.submission_reminder`

**Business Logic**:
- Provides interface for administrators to manage expense submission reminders
- Allows viewing of pending notifications and email status

**Example Usage**:
```php
// Route definition
Route::get('/admin/submission-reminder', [NotificationController::class, 'submissionReminder'])
    ->name('admin.submission.reminder');
```

**Frontend Integration**:
```javascript
// Load submission reminder page
function loadSubmissionReminder() {
    window.location.href = '/admin/submission-reminder';
}
```

---

### getSubmissionReminderEmails()

**Purpose**: Retrieves and analyzes employees eligible for expense submission reminder emails with detailed condition checking.

**Route**: `GET /admin/submission-reminder/emails`

**Parameters**: None

**Returns**: JSON response with employee data and conditions

**Business Logic**:
1. **Notification Retrieval**: Fetches active expense submission notifications
2. **Date Validation**: Checks if current date matches configured send day
3. **Employee Analysis**: Identifies employees with unsubmitted expenses from previous month
4. **Duplicate Prevention**: Checks if reminder already sent for current month
5. **Condition Tracking**: Provides detailed debugging information for each employee

**Database Tables Used**:
- `eml_notification` - Notification configurations
- `eml_email_templates` - Email templates
- `y7_monthexpensefinal` - Monthly expense records
- `users` - Employee information
- `eml_stage_email_logs` - Email delivery tracking

**Response Structure**:
```json
{
    "success": true,
    "message": "Employees with conditions fetched successfully.",
    "data": [
        {
            "employee_id": "1729",
            "employee_name": "John Doe",
            "email": "john.doe@company.com",
            "month": "December",
            "status": "Open",
            "stage": "first_reminder",
            "email_template_subject": "Monthly Expense Submission Reminder",
            "conditions": {
                "today_date": 15,
                "send_day": 15,
                "mail_active": "true",
                "date_condition": "true",
                "previous_month_not_submitted": "true",
                "already_sent": "<span class=\"badge bg-dark-subtle text-dark badge-border\">No</span>"
            },
            "debugger": {
                "today_date": 15,
                "send_day": 15,
                "employee_id": "1729",
                "month_in_record": 12,
                "previous_month": 12,
                "date_condition": true,
                "previous_month_record_found": true
            }
        }
    ]
}
```

**Error Handling**:
```json
{
    "success": false,
    "message": "Error fetching employees: Database connection failed",
    "status": 500
}
```

**Frontend Integration**:
```javascript
// Fetch submission reminder emails
async function fetchSubmissionReminderEmails() {
    try {
        const response = await fetch('/admin/submission-reminder/emails', {
            method: 'GET',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
            }
        });
        
        const result = await response.json();
        
        if (result.success) {
            displayEmployeeReminders(result.data);
        } else {
            showError(result.message);
        }
    } catch (error) {
        console.error('Error fetching reminder emails:', error);
        showError('Failed to fetch reminder emails');
    }
}

// Display employee reminders in table
function displayEmployeeReminders(employees) {
    const tableBody = document.getElementById('reminder-table-body');
    tableBody.innerHTML = '';
    
    employees.forEach(employee => {
        const row = `
            <tr>
                <td>${employee.employee_id}</td>
                <td>${employee.employee_name}</td>
                <td>${employee.email}</td>
                <td>${employee.month}</td>
                <td>${employee.stage}</td>
                <td>${employee.conditions.already_sent}</td>
                <td>
                    <button class="btn btn-sm btn-primary" 
                            onclick="viewEmployeeDetails('${employee.employee_id}')">
                        View Details
                    </button>
                </td>
            </tr>
        `;
        tableBody.innerHTML += row;
    });
}
```

---

### sendDynamicNonSubmittedEmails()

**Purpose**: Processes and sends automated expense submission reminder emails to eligible employees.

**Route**: `POST /admin/submission-reminder/send`

**Parameters**: None

**Returns**: JSON response with processing status

**Business Logic**:
1. **Notification Processing**: Iterates through active notification configurations
2. **Date Matching**: Only processes notifications scheduled for current date
3. **Template Retrieval**: Fetches corresponding email templates
4. **Employee Filtering**: Identifies employees with unsubmitted expenses
5. **Duplicate Prevention**: Skips employees who already received reminders
6. **Email Composition**: Replaces template variables with dynamic content
7. **Email Delivery**: Sends emails if mail system is active
8. **Logging**: Records email delivery in tracking tables

**Email Template Variables**:
- `{{month_name}}` - Name of the month for which expenses are pending

**Database Operations**:
```sql
-- Insert email log
INSERT INTO eml_stage_email_logs (
    employee_id, 
    notification_type, 
    stage, 
    sent_for_month, 
    created_at, 
    updated_at
) VALUES (?, 'expense_submission', ?, ?, NOW(), NOW());

-- Record sent email
INSERT INTO sent_emails (to, subject, body) VALUES (?, ?, ?);
```

**Response Examples**:

**Success Response**:
```json
{
    "success": true,
    "message": "Emails processed successfully.",
    "data": []
}
```

**Error Response**:
```json
{
    "success": false,
    "message": "Error sending emails: Template not found",
    "status": 500
}
```

**Frontend Integration**:
```javascript
// Send submission reminder emails
async function sendSubmissionReminders() {
    const confirmSend = confirm('Are you sure you want to send reminder emails to all eligible employees?');
    
    if (!confirmSend) return;
    
    try {
        showLoading('Sending reminder emails...');
        
        const response = await fetch('/admin/submission-reminder/send', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
            }
        });
        
        const result = await response.json();
        
        hideLoading();
        
        if (result.success) {
            showSuccess('Reminder emails sent successfully!');
            // Refresh the employee list
            fetchSubmissionReminderEmails();
        } else {
            showError(result.message);
        }
    } catch (error) {
        hideLoading();
        console.error('Error sending reminder emails:', error);
        showError('Failed to send reminder emails');
    }
}

// Utility functions for UI feedback
function showLoading(message) {
    const loadingDiv = document.createElement('div');
    loadingDiv.id = 'loading-overlay';
    loadingDiv.innerHTML = `
        <div class="loading-content">
            <div class="spinner-border text-primary" role="status"></div>
            <p class="mt-2">${message}</p>
        </div>
    `;
    document.body.appendChild(loadingDiv);
}

function hideLoading() {
    const loadingDiv = document.getElementById('loading-overlay');
    if (loadingDiv) {
        loadingDiv.remove();
    }
}
```

## Security Considerations

### Access Control
- **Admin Only**: All methods require administrator privileges
- **Route Protection**: Implement middleware for admin authentication
- **CSRF Protection**: All POST requests must include CSRF tokens

### Data Validation
- **Email Validation**: Verify email addresses before sending
- **Template Security**: Sanitize email template content
- **SQL Injection Prevention**: Use parameterized queries

### Privacy Protection
- **Email Logging**: Sensitive email content is logged securely
- **Employee Data**: Personal information is handled according to privacy policies

## Performance Optimizations

### Database Efficiency
```php
// Optimized query with proper indexing
$employees = DB::table('y7_monthexpensefinal as m')
    ->join('users as u', 'u.employee_id', '=', 'm.EmployeeId')
    ->select('u.id', 'u.name', 'u.email', 'm.EmployeeId', 'm.Month', 'm.Status')
    ->where('m.Status', 'Open')
    ->where('m.Month', $previousMonth)
    ->where(function ($q) {
        $q->whereNull('m.DateOfSubmit')->orWhere('m.DateOfSubmit', '0000-00-00');
    })
    ->distinct()
    ->get();
```

### Memory Management
- **Batch Processing**: Process employees in chunks for large datasets
- **Collection Usage**: Efficient data handling with Laravel collections
- **Query Optimization**: Minimize database calls with joins

### Email Delivery
- **Queue Integration**: Consider using Laravel queues for email sending
- **Rate Limiting**: Implement email rate limiting to prevent spam
- **Retry Logic**: Add retry mechanism for failed email deliveries

## Configuration

### Environment Variables
```env
# Email system activation
MAIL_ACTIVE=true

# Timezone for date calculations
APP_TIMEZONE=Asia/Kolkata

# Mail configuration
MAIL_MAILER=smtp
MAIL_HOST=smtp.company.com
MAIL_PORT=587
MAIL_USERNAME=notifications@company.com
MAIL_PASSWORD=your_password
MAIL_ENCRYPTION=tls
```

### Database Schema Requirements
```sql
-- Notification configuration table
CREATE TABLE eml_notification (
    id INT PRIMARY KEY AUTO_INCREMENT,
    notification_type VARCHAR(50),
    stage VARCHAR(50),
    send_day INT,
    email_template_id INT,
    is_active BOOLEAN DEFAULT 1,
    created_at TIMESTAMP,
    updated_at TIMESTAMP
);

-- Email delivery tracking
CREATE TABLE eml_stage_email_logs (
    id INT PRIMARY KEY AUTO_INCREMENT,
    employee_id VARCHAR(20),
    notification_type VARCHAR(50),
    stage VARCHAR(50),
    sent_for_month VARCHAR(7),
    created_at TIMESTAMP,
    updated_at TIMESTAMP
);
```

## Integration Examples

### Scheduled Task Integration
```php
// In app/Console/Kernel.php
protected function schedule(Schedule $schedule)
{
    // Send daily reminder emails at 9 AM
    $schedule->call(function () {
        app(NotificationController::class)->sendDynamicNonSubmittedEmails();
    })->dailyAt('09:00');
}
```

### Event-Driven Notifications
```php
// Custom event listener for expense submission deadlines
class ExpenseDeadlineListener
{
    public function handle(ExpenseDeadlineEvent $event)
    {
        $controller = app(NotificationController::class);
        $controller->sendDynamicNonSubmittedEmails();
    }
}
```

## Error Handling and Logging

### Exception Management
```php
try {
    // Email processing logic
} catch (\Exception $e) {
    Log::error('Notification Controller Error', [
        'method' => 'sendDynamicNonSubmittedEmails',
        'error' => $e->getMessage(),
        'trace' => $e->getTraceAsString()
    ]);
    
    return $this->jsonError('Error sending emails: ' . $e->getMessage(), 500);
}
```

### Monitoring and Alerts
- **Email Delivery Tracking**: Monitor successful/failed email deliveries
- **Performance Metrics**: Track processing time and memory usage
- **Alert System**: Notify administrators of system failures

## Testing Considerations

### Unit Tests
```php
class NotificationControllerTest extends TestCase
{
    public function test_submission_reminder_view()
    {
        $response = $this->actingAs($this->adminUser)
            ->get('/admin/submission-reminder');
            
        $response->assertStatus(200)
            ->assertViewIs('admin.submission_reminder');
    }
    
    public function test_get_submission_reminder_emails()
    {
        $response = $this->actingAs($this->adminUser)
            ->get('/admin/submission-reminder/emails');
            
        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'message',
                'data' => [
                    '*' => [
                        'employee_id',
                        'employee_name',
                        'email',
                        'conditions'
                    ]
                ]
            ]);
    }
}
```

This NotificationController serves as a critical component for maintaining expense submission compliance through automated reminder systems, ensuring employees stay on track with their monthly expense reporting requirements.