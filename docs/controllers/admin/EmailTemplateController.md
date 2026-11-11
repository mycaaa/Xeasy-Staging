# EmailTemplateController Documentation

## Overview

The `EmailTemplateController` manages email templates for the Xeasy expense management system. It provides comprehensive functionality for creating, editing, and managing email templates used throughout the application for notifications, reminders, and communication with users. The controller supports template categorization, variable substitution, and activity logging.

## Class Information

- **Namespace**: `App\Http\Controllers\admin`
- **Extends**: `Controller`
- **Location**: `app/Http/Controllers/admin/EmailTemplateController.php`

## Dependencies

- `App\Models\EmailTemplate` - Email template model
- `App\Http\Requests\StoreEmailTemplateRequest` - Template creation validation
- `App\Http\Requests\UpdateEmailTemplateRequest` - Template update validation
- `Illuminate\Support\Facades\DB` - Database operations

## Methods

### index()

**Purpose**: Displays the email template management interface with filtering capabilities.

**Route**: `GET /admin/email-templates`

**Parameters**:
- `status` (optional) - Filter by template status (active/inactive/all)

**Returns**: View `admin.email_templates` with templates and status data

**Business Logic**:
1. **Status Filtering**: Filters templates based on active/inactive status
2. **Template Retrieval**: Fetches templates according to filter criteria
3. **Interface Preparation**: Prepares data for template management interface

**Query Logic**:
```php
$templatesQuery = EmailTemplate::query();
if ($status === 'active') {
    $templatesQuery->where('is_active', 1);
} elseif ($status === 'inactive') {
    $templatesQuery->where('is_active', 0);
}
$templates = $templatesQuery->get();
```

**Frontend Integration**:
```javascript
// Load email templates with status filter
function loadEmailTemplates(status = 'active') {
    const url = `/admin/email-templates?status=${status}`;
    window.location.href = url;
}

// Status filter handler
function initializeStatusFilter() {
    const statusFilter = document.getElementById('status-filter');
    statusFilter.addEventListener('change', function() {
        loadEmailTemplates(this.value);
    });
}

// Initialize template management interface
function initializeTemplateInterface() {
    initializeStatusFilter();
    initializeTemplateActions();
    loadTemplateVariables();
}
```

---

### store(StoreEmailTemplateRequest $request)

**Purpose**: Creates a new email template with comprehensive validation and audit tracking.

**Route**: `POST /admin/email-templates`

**Parameters**:
- `name` (required) - Template name/identifier
- `subject` (required) - Email subject line
- `body_html` (required) - HTML email body content
- `is_active` (required) - Template active status
- `category` (optional) - Template category for organization

**Returns**: JSON response with created template data

**Business Logic**:
1. **Validation**: Uses StoreEmailTemplateRequest for comprehensive validation
2. **Creation**: Creates new template with user attribution
3. **Audit Trail**: Records creator and creation timestamp
4. **Response**: Returns success response with template data

**Request Validation**:
```php
// StoreEmailTemplateRequest validation rules
public function rules()
{
    return [
        'name' => 'required|string|max:255|unique:email_templates,name',
        'subject' => 'required|string|max:500',
        'body_html' => 'required|string',
        'is_active' => 'required|boolean',
        'category' => 'nullable|string|max:100'
    ];
}
```

**Response Structure**:
```json
{
    "success": true,
    "message": "Email template created successfully.",
    "data": {
        "id": 1,
        "name": "expense_submission_reminder",
        "subject": "Monthly Expense Submission Reminder",
        "body_html": "<html><body><h1>Dear {{employee_name}},</h1><p>Please submit your expenses for {{month_name}}.</p></body></html>",
        "is_active": 1,
        "category": "reminders",
        "created_by": 123,
        "updated_by": 123,
        "created_at": "2024-01-15T10:30:00.000000Z",
        "updated_at": "2024-01-15T10:30:00.000000Z"
    }
}
```

**Frontend Integration**:
```javascript
// Create new email template
async function createEmailTemplate(formData) {
    try {
        showLoading('Creating email template...');
        
        const response = await fetch('/admin/email-templates', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
            },
            body: JSON.stringify(formData)
        });
        
        const result = await response.json();
        
        hideLoading();
        
        if (result.success) {
            showSuccess(result.message);
            refreshTemplateList();
            closeTemplateModal();
        } else {
            showError(result.message);
        }
    } catch (error) {
        hideLoading();
        console.error('Error creating template:', error);
        showError('Failed to create email template');
    }
}

// Template creation form handler
function handleTemplateForm() {
    const form = document.getElementById('template-form');
    form.addEventListener('submit', function(e) {
        e.preventDefault();
        
        const formData = {
            name: document.getElementById('template-name').value,
            subject: document.getElementById('template-subject').value,
            body_html: getEditorContent(), // From rich text editor
            is_active: document.getElementById('is-active').checked ? 1 : 0,
            category: document.getElementById('category').value
        };
        
        if (validateTemplateForm(formData)) {
            createEmailTemplate(formData);
        }
    });
}

// Rich text editor integration
function initializeRichTextEditor() {
    const editor = new Quill('#template-editor', {
        theme: 'snow',
        modules: {
            toolbar: [
                ['bold', 'italic', 'underline', 'strike'],
                ['blockquote', 'code-block'],
                [{ 'header': 1 }, { 'header': 2 }],
                [{ 'list': 'ordered'}, { 'list': 'bullet' }],
                [{ 'script': 'sub'}, { 'script': 'super' }],
                [{ 'indent': '-1'}, { 'indent': '+1' }],
                [{ 'direction': 'rtl' }],
                [{ 'size': ['small', false, 'large', 'huge'] }],
                [{ 'header': [1, 2, 3, 4, 5, 6, false] }],
                [{ 'color': [] }, { 'background': [] }],
                [{ 'font': [] }],
                [{ 'align': [] }],
                ['clean'],
                ['link', 'image', 'video']
            ]
        }
    });
    
    return editor;
}

// Get editor content
function getEditorContent() {
    return window.templateEditor.root.innerHTML;
}
```

---

### edit(string $id)

**Purpose**: Retrieves email template data for editing purposes.

**Route**: `GET /admin/email-templates/{id}/edit`

**Parameters**:
- `id` (required) - Template ID

**Returns**: JSON response with template data

**Business Logic**:
1. **Retrieval**: Finds template by ID or throws 404
2. **Data Return**: Returns complete template configuration
3. **Error Handling**: Handles non-existent templates gracefully

**Response Structure**:
```json
{
    "success": true,
    "message": "Email template fetched successfully.",
    "data": {
        "id": 1,
        "name": "expense_submission_reminder",
        "subject": "Monthly Expense Submission Reminder",
        "body_html": "<html><body>...</body></html>",
        "is_active": 1,
        "category": "reminders",
        "created_by": 123,
        "updated_by": 456,
        "created_at": "2024-01-15T10:30:00.000000Z",
        "updated_at": "2024-01-16T14:20:00.000000Z"
    }
}
```

**Frontend Integration**:
```javascript
// Load template data for editing
async function editEmailTemplate(templateId) {
    try {
        showLoading('Loading template data...');
        
        const response = await fetch(`/admin/email-templates/${templateId}/edit`);
        const result = await response.json();
        
        hideLoading();
        
        if (result.success) {
            populateTemplateForm(result.data);
            showTemplateModal('Edit Email Template');
        } else {
            showError(result.message);
        }
    } catch (error) {
        hideLoading();
        console.error('Error loading template data:', error);
        showError('Failed to load template data');
    }
}

// Populate form with template data
function populateTemplateForm(templateData) {
    document.getElementById('template-name').value = templateData.name;
    document.getElementById('template-subject').value = templateData.subject;
    document.getElementById('category').value = templateData.category || '';
    document.getElementById('is-active').checked = templateData.is_active === 1;
    
    // Set editor content
    window.templateEditor.root.innerHTML = templateData.body_html;
}
```

---

### update(UpdateEmailTemplateRequest $request, EmailTemplate $template)

**Purpose**: Updates existing email template with validation and audit tracking.

**Route**: `PUT /admin/email-templates/{template}`

**Parameters**:
- `template` (required) - Template model instance
- `name` (required) - Template name/identifier
- `subject` (required) - Email subject line
- `body_html` (required) - HTML email body content
- `is_active` (required) - Template active status
- `category` (optional) - Template category

**Returns**: JSON response with updated template data

**Business Logic**:
1. **Validation**: Uses UpdateEmailTemplateRequest for input validation
2. **Update**: Updates existing template with new data
3. **Audit Trail**: Records updater and update timestamp
4. **Response**: Returns success response with updated data

**Frontend Integration**:
```javascript
// Update existing email template
async function updateEmailTemplate(templateId, formData) {
    try {
        showLoading('Updating email template...');
        
        const response = await fetch(`/admin/email-templates/${templateId}`, {
            method: 'PUT',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
            },
            body: JSON.stringify(formData)
        });
        
        const result = await response.json();
        
        hideLoading();
        
        if (result.success) {
            showSuccess(result.message);
            refreshTemplateList();
            closeTemplateModal();
        } else {
            showError(result.message);
        }
    } catch (error) {
        hideLoading();
        console.error('Error updating template:', error);
        showError('Failed to update email template');
    }
}
```

---

### destroy(string $id)

**Purpose**: Deletes an email template from the system.

**Route**: `DELETE /admin/email-templates/{id}`

**Parameters**:
- `id` (required) - Template ID

**Returns**: JSON response with deletion confirmation

**Business Logic**:
1. **Retrieval**: Finds template by ID or throws 404
2. **Deletion**: Removes template record from database
3. **Response**: Returns success confirmation

**Frontend Integration**:
```javascript
// Delete email template
async function deleteEmailTemplate(templateId) {
    const confirmDelete = confirm('Are you sure you want to delete this email template? This action cannot be undone.');
    
    if (!confirmDelete) return;
    
    try {
        showLoading('Deleting email template...');
        
        const response = await fetch(`/admin/email-templates/${templateId}`, {
            method: 'DELETE',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
            }
        });
        
        const result = await response.json();
        
        hideLoading();
        
        if (result.success) {
            showSuccess(result.message);
            refreshTemplateList();
        } else {
            showError(result.message);
        }
    } catch (error) {
        hideLoading();
        console.error('Error deleting template:', error);
        showError('Failed to delete email template');
    }
}
```

---

### templateList()

**Purpose**: Retrieves a list of all active email templates for API consumption.

**Route**: `GET /admin/email-templates/list`

**Parameters**: None

**Returns**: JSON response with active templates

**Business Logic**:
- Filters templates by active status (is_active = 1)
- Returns essential template information
- Used by other systems for template selection

**Response Structure**:
```json
[
    {
        "id": 1,
        "name": "expense_submission_reminder",
        "subject": "Monthly Expense Submission Reminder",
        "category": "reminders",
        "is_active": 1,
        "created_at": "2024-01-15T10:30:00.000000Z",
        "updated_at": "2024-01-15T10:30:00.000000Z"
    },
    {
        "id": 2,
        "name": "claim_approval_notification",
        "subject": "Expense Claim Approved",
        "category": "notifications",
        "is_active": 1,
        "created_at": "2024-01-16T09:15:00.000000Z",
        "updated_at": "2024-01-16T09:15:00.000000Z"
    }
]
```

---

### getLogs(EmailTemplate $template)

**Purpose**: Retrieves activity logs for a specific email template.

**Route**: `GET /admin/email-templates/{template}/logs`

**Parameters**:
- `template` (required) - Template model instance

**Returns**: JSON response with activity logs

**Business Logic**:
1. **Activity Retrieval**: Fetches template activity logs with user information
2. **Data Formatting**: Formats log data for display
3. **Chronological Order**: Orders logs by most recent first

**Response Structure**:
```json
{
    "data": [
        {
            "event": "updated",
            "description": "Email template updated",
            "properties": {
                "attributes": {
                    "subject": "Updated Monthly Expense Submission Reminder",
                    "updated_by": 456
                },
                "old": {
                    "subject": "Monthly Expense Submission Reminder",
                    "updated_by": 123
                }
            },
            "causer": {
                "name": "John Admin"
            },
            "created_at": "2024-01-16T14:20:00.000000Z"
        },
        {
            "event": "created",
            "description": "Email template created",
            "properties": {
                "attributes": {
                    "name": "expense_submission_reminder",
                    "subject": "Monthly Expense Submission Reminder"
                }
            },
            "causer": {
                "name": "Jane Admin"
            },
            "created_at": "2024-01-15T10:30:00.000000Z"
        }
    ],
    "title": "expense_submission_reminder"
}
```

**Frontend Integration**:
```javascript
// View template activity logs
async function viewTemplateLogs(templateId) {
    try {
        showLoading('Loading activity logs...');
        
        const response = await fetch(`/admin/email-templates/${templateId}/logs`);
        const result = await response.json();
        
        hideLoading();
        
        displayActivityLogs(result.data, result.title);
    } catch (error) {
        hideLoading();
        console.error('Error loading logs:', error);
        showError('Failed to load activity logs');
    }
}

// Display activity logs in modal
function displayActivityLogs(logs, templateTitle) {
    const modal = document.getElementById('logs-modal');
    const title = modal.querySelector('.modal-title');
    const body = modal.querySelector('.modal-body');
    
    title.textContent = `Activity Logs - ${templateTitle}`;
    
    let logsHtml = '<div class="timeline">';
    logs.forEach(log => {
        logsHtml += `
            <div class="timeline-item">
                <div class="timeline-marker"></div>
                <div class="timeline-content">
                    <h6 class="timeline-title">${log.event.toUpperCase()}</h6>
                    <p class="timeline-description">${log.description}</p>
                    <small class="text-muted">
                        by ${log.causer ? log.causer.name : 'System'} 
                        on ${new Date(log.created_at).toLocaleString()}
                    </small>
                    ${formatLogProperties(log.properties)}
                </div>
            </div>
        `;
    });
    logsHtml += '</div>';
    
    body.innerHTML = logsHtml;
    
    // Show modal
    const bsModal = new bootstrap.Modal(modal);
    bsModal.show();
}

// Format log properties for display
function formatLogProperties(properties) {
    if (!properties || Object.keys(properties).length === 0) {
        return '';
    }
    
    let html = '<div class="mt-2"><small class="text-muted">Changes:</small><ul class="list-unstyled ms-3">';
    
    if (properties.attributes) {
        Object.keys(properties.attributes).forEach(key => {
            const newValue = properties.attributes[key];
            const oldValue = properties.old ? properties.old[key] : null;
            
            if (oldValue !== null && oldValue !== newValue) {
                html += `<li><strong>${key}:</strong> ${oldValue} → ${newValue}</li>`;
            } else if (oldValue === null) {
                html += `<li><strong>${key}:</strong> ${newValue}</li>`;
            }
        });
    }
    
    html += '</ul></div>';
    return html;
}
```

---

### getVariables()

**Purpose**: Retrieves available template variables for dynamic content substitution.

**Route**: `GET /admin/email-templates/variables`

**Parameters**: None

**Returns**: JSON response with available variables

**Business Logic**:
- Fetches predefined template variables from database
- Returns variable names for template editor integration
- Used for variable suggestion and validation

**Response Structure**:
```json
[
    {"variable_name": "{{employee_name}}"},
    {"variable_name": "{{month_name}}"},
    {"variable_name": "{{company_name}}"},
    {"variable_name": "{{submission_deadline}}"},
    {"variable_name": "{{claim_amount}}"},
    {"variable_name": "{{approval_date}}"},
    {"variable_name": "{{verifier_name}}"},
    {"variable_name": "{{approver_name}}"}
]
```

**Frontend Integration**:
```javascript
// Load template variables for editor
async function loadTemplateVariables() {
    try {
        const response = await fetch('/admin/email-templates/variables');
        const variables = await response.json();
        
        initializeVariableSelector(variables);
        setupVariableInsertion(variables);
    } catch (error) {
        console.error('Error loading variables:', error);
    }
}

// Initialize variable selector
function initializeVariableSelector(variables) {
    const selector = document.getElementById('variable-selector');
    selector.innerHTML = '<option value="">Select a variable to insert</option>';
    
    variables.forEach(variable => {
        const option = document.createElement('option');
        option.value = variable.variable_name;
        option.textContent = variable.variable_name;
        selector.appendChild(option);
    });
}

// Setup variable insertion into editor
function setupVariableInsertion(variables) {
    const selector = document.getElementById('variable-selector');
    const insertBtn = document.getElementById('insert-variable-btn');
    
    insertBtn.addEventListener('click', function() {
        const selectedVariable = selector.value;
        if (selectedVariable && window.templateEditor) {
            const range = window.templateEditor.getSelection();
            window.templateEditor.insertText(range.index, selectedVariable);
            selector.value = '';
        }
    });
    
    // Auto-complete functionality
    setupVariableAutoComplete(variables);
}

// Setup auto-complete for variables
function setupVariableAutoComplete(variables) {
    const variableNames = variables.map(v => v.variable_name);
    
    // Add auto-complete to editor
    window.templateEditor.on('text-change', function(delta, oldDelta, source) {
        if (source === 'user') {
            const text = window.templateEditor.getText();
            const cursorPosition = window.templateEditor.getSelection().index;
            
            // Check for {{ pattern
            const beforeCursor = text.substring(0, cursorPosition);
            const match = beforeCursor.match(/\{\{[^}]*$/);
            
            if (match) {
                showVariableSuggestions(match[0], variableNames, cursorPosition - match[0].length);
            } else {
                hideVariableSuggestions();
            }
        }
    });
}
```

## Security Considerations

### Access Control
- **Admin Authentication**: All methods require admin-level access
- **Permission Validation**: Template management requires specific permissions
- **User Attribution**: All operations tracked with user IDs

### Data Validation
- **Input Sanitization**: All inputs validated and sanitized
- **HTML Validation**: Email HTML content validated for security
- **Variable Validation**: Template variables validated against allowed list

### Template Security
- **XSS Prevention**: HTML content sanitized to prevent XSS attacks
- **Variable Escaping**: Template variables properly escaped
- **Content Filtering**: Malicious content filtered from templates

## Performance Optimizations

### Database Efficiency
```php
// Efficient template retrieval with selective fields
$query = EmailTemplate::select('id', 'name', 'subject', 'category', 'is_active', 'created_at', 'updated_at')
    ->where('is_active', 1);

// Optimized activity log retrieval
$logs = $template->activities()
    ->with('causer')
    ->latest()
    ->get();
```

### Caching Strategy
- **Template Caching**: Cache frequently used templates
- **Variable Caching**: Cache template variables list
- **Rendered Content Caching**: Cache rendered email content

### Memory Management
- **Selective Loading**: Load only required template fields
- **Efficient Queries**: Use proper indexing for template queries
- **Resource Cleanup**: Proper cleanup of template resources

## Configuration

### Environment Variables
```env
# Email Configuration
MAIL_MAILER=smtp
MAIL_HOST=smtp.company.com
MAIL_PORT=587
MAIL_USERNAME=templates@company.com
MAIL_PASSWORD=your_password
MAIL_ENCRYPTION=tls

# Template Configuration
TEMPLATE_CACHE_TTL=3600
TEMPLATE_MAX_SIZE=1048576
```

### Database Schema Requirements
```sql
-- Email templates table
CREATE TABLE email_templates (
    id INT PRIMARY KEY AUTO_INCREMENT,
    name VARCHAR(255) NOT NULL UNIQUE,
    subject VARCHAR(500) NOT NULL,
    body_html LONGTEXT NOT NULL,
    is_active TINYINT(1) DEFAULT 1,
    category VARCHAR(100),
    created_by INT,
    updated_by INT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (created_by) REFERENCES users(id),
    FOREIGN KEY (updated_by) REFERENCES users(id),
    INDEX idx_active (is_active),
    INDEX idx_category (category)
);

-- Template variables table
CREATE TABLE eml_template_variables (
    id INT PRIMARY KEY AUTO_INCREMENT,
    variable_name VARCHAR(255) NOT NULL UNIQUE,
    description TEXT,
    category VARCHAR(100),
    is_active TINYINT(1) DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);
```

## Integration Examples

### Email Sending Integration
```php
// Use template for email sending
$template = EmailTemplate::where('name', 'expense_submission_reminder')
    ->where('is_active', 1)
    ->first();

if ($template) {
    $subject = str_replace('{{month_name}}', $monthName, $template->subject);
    $body = str_replace([
        '{{employee_name}}',
        '{{month_name}}',
        '{{company_name}}'
    ], [
        $employee->name,
        $monthName,
        config('app.company_name')
    ], $template->body_html);
    
    Mail::send([], [], function ($message) use ($employee, $subject, $body) {
        $message->to($employee->email)
                ->subject($subject)
                ->setBody($body, 'text/html');
    });
}
```

### Notification System Integration
```php
// Template-based notification
class ExpenseReminderNotification extends Notification
{
    public function toMail($notifiable)
    {
        $template = EmailTemplate::where('name', 'expense_submission_reminder')
            ->where('is_active', 1)
            ->first();
            
        if (!$template) {
            throw new Exception('Email template not found');
        }
        
        return (new MailMessage)
            ->subject($this->processTemplate($template->subject, $notifiable))
            ->view('emails.template', [
                'content' => $this->processTemplate($template->body_html, $notifiable)
            ]);
    }
    
    private function processTemplate($content, $notifiable)
    {
        return str_replace([
            '{{employee_name}}',
            '{{month_name}}',
            '{{submission_deadline}}'
        ], [
            $notifiable->name,
            $this->monthName,
            $this->deadline
        ], $content);
    }
}
```

## Error Handling and Logging

### Exception Management
```php
try {
    // Template operations
    $template = EmailTemplate::create($data);
} catch (\Exception $e) {
    Log::error('Email Template Error', [
        'method' => 'store',
        'data' => $data,
        'error' => $e->getMessage(),
        'trace' => $e->getTraceAsString()
    ]);
    
    return $this->jsonError('Failed to create template: ' . $e->getMessage(), 500);
}
```

### Monitoring and Alerts
- **Template Usage Tracking**: Monitor template usage frequency
- **Error Rate Monitoring**: Track template processing errors
- **Performance Monitoring**: Monitor template rendering performance

This EmailTemplateController provides a comprehensive system for managing email templates, enabling consistent and professional communication throughout the expense management system while maintaining flexibility and ease of use.