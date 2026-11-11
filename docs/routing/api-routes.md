# 🔌 API Routes Documentation

This document covers all API endpoints in the Xeasy Laravel application designed for external integrations and mobile applications.

## 📍 API Base Configuration

- **Base URL**: `/api/`
- **Authentication**: JWT Token (via query parameter)
- **Response Format**: JSON
- **Error Handling**: HTTP status codes with JSON error messages

## 🔐 Authentication

All API routes use JWT token authentication passed as a query parameter:

```
GET /api/endpoint?token=eyJ0eXAiOiJKV1QiLCJhbGciOiJIUzI1NiJ9...
```

**Token Configuration**:
- **Secret Key**: `v7n90l9uvy`
- **Algorithm**: HS256
- **Library**: Firebase JWT

## 📋 Available API Endpoints

### Odometer Backdate Management

The API currently focuses on odometer backdate operations for vehicle expense management.

#### 1. Check Backdate Submission Eligibility

**Endpoint**: `GET /api/odo-backdate-submission/{yearId}/{employeeId}/`

**Description**: Checks if an employee is eligible to submit odometer backdate requests for a specific financial year.

**Parameters**:
| Parameter | Type | Required | Description |
|-----------|------|----------|-------------|
| yearId | integer | Yes | Financial year ID |
| employeeId | integer | Yes | Employee ID |
| token | string | Yes | JWT authentication token |

**Request Example**:
```http
GET /api/odo-backdate-submission/7/12345/?token=eyJ0eXAiOiJKV1QiLCJhbGciOiJIUzI1NiJ9...
```

**Response Example**:
```json
{
    "success": true,
    "eligible": true,
    "message": "Employee is eligible for backdate submission",
    "data": {
        "employee_id": 12345,
        "year_id": 7,
        "pending_requests": 2,
        "max_allowed": 5
    }
}
```

**Error Response**:
```json
{
    "success": false,
    "error": "Employee not found or not eligible",
    "code": 404
}
```

#### 2. Show Backdate Submission Button

**Endpoint**: `GET /api/odo-backdate-submission-show/{yearId}/{employeeId}/`

**Description**: Determines whether to show the backdate submission button in the UI based on employee eligibility and current status.

**Parameters**:
| Parameter | Type | Required | Description |
|-----------|------|----------|-------------|
| yearId | integer | Yes | Financial year ID |
| employeeId | integer | Yes | Employee ID |
| token | string | Yes | JWT authentication token |

**Request Example**:
```http
GET /api/odo-backdate-submission-show/7/12345/?token=eyJ0eXAiOiJKV1QiLCJhbGciOiJIUzI1NiJ9...
```

**Response Example**:
```json
{
    "success": true,
    "show_button": true,
    "button_text": "Submit Backdate Request",
    "button_enabled": true,
    "reason": "Employee has pending odometer readings"
}
```

#### 3. Approve Backdate Request

**Endpoint**: `GET /api/odo-backdate-approval/{yearId}/{employeeId}/{expenseId}/`

**Description**: Approves a specific odometer backdate request.

**Parameters**:
| Parameter | Type | Required | Description |
|-----------|------|----------|-------------|
| yearId | integer | Yes | Financial year ID |
| employeeId | integer | Yes | Employee ID |
| expenseId | integer | Yes | Expense claim ID |
| token | string | Yes | JWT authentication token |

**Request Example**:
```http
GET /api/odo-backdate-approval/7/12345/67890/?token=eyJ0eXAiOiJKV1QiLCJhbGciOiJIUzI1NiJ9...
```

**Response Example**:
```json
{
    "success": true,
    "message": "Backdate request approved successfully",
    "data": {
        "expense_id": 67890,
        "employee_id": 12345,
        "approved_by": 98765,
        "approved_at": "2024-01-15T10:30:00Z",
        "status": "approved"
    }
}
```

#### 4. Reject Backdate Request

**Endpoint**: `GET /api/odo-backdate-rejection/{yearId}/{employeeId}/{expenseId}/`

**Description**: Rejects a specific odometer backdate request.

**Parameters**:
| Parameter | Type | Required | Description |
|-----------|------|----------|-------------|
| yearId | integer | Yes | Financial year ID |
| employeeId | integer | Yes | Employee ID |
| expenseId | integer | Yes | Expense claim ID |
| token | string | Yes | JWT authentication token |

**Request Example**:
```http
GET /api/odo-backdate-rejection/7/12345/67890/?token=eyJ0eXAiOiJKV1QiLCJhbGciOiJIUzI1NiJ9...
```

**Response Example**:
```json
{
    "success": true,
    "message": "Backdate request rejected successfully",
    "data": {
        "expense_id": 67890,
        "employee_id": 12345,
        "rejected_by": 98765,
        "rejected_at": "2024-01-15T10:30:00Z",
        "status": "rejected",
        "reason": "Invalid odometer reading"
    }
}
```

#### 5. Bulk Approve Backdate Requests

**Endpoint**: `GET /api/odo-backdate-bulk-approval/{yearId}/{employeeId}/{empid}`

**Description**: Approves multiple odometer backdate requests for an employee in bulk.

**Parameters**:
| Parameter | Type | Required | Description |
|-----------|------|----------|-------------|
| yearId | integer | Yes | Financial year ID |
| employeeId | integer | Yes | Approver employee ID |
| empid | integer | Yes | Target employee ID whose requests to approve |
| token | string | Yes | JWT authentication token |

**Request Example**:
```http
GET /api/odo-backdate-bulk-approval/7/12345/54321?token=eyJ0eXAiOiJKV1QiLCJhbGciOiJIUzI1NiJ9...
```

**Response Example**:
```json
{
    "success": true,
    "message": "5 backdated odometer record(s) have been approved successfully.",
    "data": {
        "updated_count": 5
    }
}
```

#### 6. Bulk Reject Backdate Requests

**Endpoint**: `GET /api/odo-backdate-bulk-rejection/{yearId}/{employeeId}/{empid}`

**Description**: Rejects multiple odometer backdate requests for an employee in bulk.

**Parameters**:
| Parameter | Type | Required | Description |
|-----------|------|----------|-------------|
| yearId | integer | Yes | Financial year ID |
| employeeId | integer | Yes | Approver employee ID |
| empid | integer | Yes | Target employee ID whose requests to reject |
| token | string | Yes | JWT authentication token |

**Request Example**:
```http
GET /api/odo-backdate-bulk-rejection/7/12345/54321?token=eyJ0eXAiOiJKV1QiLCJhbGciOiJIUzI1NiJ9...
```

**Response Example**:
```json
{
    "success": true,
    "message": "3 backdated odometer record(s) have been rejected successfully.",
    "data": {
        "updated_count": 3
    }
}
```

## 🔧 API Integration Examples

### JavaScript/Fetch API

```javascript
const token = 'eyJ0eXAiOiJKV1QiLCJhbGciOiJIUzI1NiJ9...';
const yearId = 7;
const employeeId = 12345;

// Check backdate submission eligibility
async function checkBackdateEligibility() {
    try {
        const response = await fetch(
            `/api/odo-backdate-submission/${yearId}/${employeeId}/?token=${token}`
        );
        const data = await response.json();
        
        if (data.success) {
            console.log('Eligibility:', data.eligible);
            return data;
        } else {
            console.error('Error:', data.error);
        }
    } catch (error) {
        console.error('Network error:', error);
    }
}

// Approve backdate request
async function approveBackdate(expenseId) {
    try {
        const response = await fetch(
            `/api/odo-backdate-approval/${yearId}/${employeeId}/${expenseId}/?token=${token}`
        );
        const data = await response.json();
        
        if (data.success) {
            console.log('Approved:', data.message);
            return data;
        } else {
            console.error('Approval failed:', data.error);
        }
    } catch (error) {
        console.error('Network error:', error);
    }
}
```

### cURL Examples

```bash
# Check backdate submission eligibility
curl -X GET "https://your-domain.com/api/odo-backdate-submission/7/12345/?token=eyJ0eXAiOiJKV1QiLCJhbGciOiJIUzI1NiJ9..."

# Approve backdate request
curl -X GET "https://your-domain.com/api/odo-backdate-approval/7/12345/67890/?token=eyJ0eXAiOiJKV1QiLCJhbGciOiJIUzI1NiJ9..."

# Bulk approve requests
curl -X GET "https://your-domain.com/api/odo-backdate-bulk-approval/7/12345/54321?token=eyJ0eXAiOiJKV1QiLCJhbGciOiJIUzI1NiJ9..."
```

### PHP/Laravel HTTP Client

```php
use Illuminate\Support\Facades\Http;

class OdometerApiClient
{
    private $baseUrl;
    private $token;

    public function __construct($baseUrl, $token)
    {
        $this->baseUrl = $baseUrl;
        $this->token = $token;
    }

    public function checkBackdateEligibility($yearId, $employeeId)
    {
        $response = Http::get("{$this->baseUrl}/api/odo-backdate-submission/{$yearId}/{$employeeId}/", [
            'token' => $this->token
        ]);

        return $response->json();
    }

    public function approveBackdate($yearId, $employeeId, $expenseId)
    {
        $response = Http::get("{$this->baseUrl}/api/odo-backdate-approval/{$yearId}/{$employeeId}/{$expenseId}/", [
            'token' => $this->token
        ]);

        return $response->json();
    }

    public function bulkApprove($yearId, $employeeId, $empiid)
    {
        $response = Http::get("{$this->baseUrl}/api/odo-backdate-bulk-approval/{$yearId}/{$employeeId}/{$empiid}", [
            'token' => $this->token
        ]);

        return $response->json();
    }
}

// Usage
$client = new OdometerApiClient('https://your-domain.com', $jwtToken);
$result = $client->checkBackdateEligibility(7, 12345);
```

## 🚨 Error Handling

### HTTP Status Codes

| Status Code | Description |
|-------------|-------------|
| 200 | Success |
| 400 | Bad Request - Invalid parameters |
| 401 | Unauthorized - Invalid or missing token |
| 403 | Forbidden - Insufficient permissions |
| 404 | Not Found - Resource not found |
| 422 | Unprocessable Entity - Validation errors |
| 500 | Internal Server Error |

### Error Response Format

```json
{
    "success": false,
    "error": "Error message description",
    "code": 404,
    "details": {
        "parameter": "Additional error details",
        "suggestion": "Possible solution"
    }
}
```

### Common Error Scenarios

#### Invalid Token
```json
{
    "success": false,
    "error": "Invalid or expired token",
    "code": 401
}
```

#### Employee Not Found
```json
{
    "success": false,
    "error": "Employee not found",
    "code": 404,
    "details": {
        "employee_id": 12345,
        "suggestion": "Verify employee ID and company context"
    }
}
```

#### Invalid Financial Year
```json
{
    "success": false,
    "error": "Invalid financial year",
    "code": 400,
    "details": {
        "year_id": 7,
        "suggestion": "Use active financial year ID"
    }
}
```

## 🔒 Security Considerations

### Token Security
- Tokens should be kept secure and not exposed in client-side code
- Implement token refresh mechanism for long-running applications
- Use HTTPS for all API communications

### Rate Limiting
- Consider implementing rate limiting for production use
- Monitor API usage patterns
- Implement proper logging for security auditing

### Input Validation
- All parameters are validated server-side
- SQL injection protection through Eloquent ORM
- XSS protection through proper output encoding

## 📈 API Expansion

The current API focuses on odometer backdate management, but the architecture supports expansion for:

### Potential Future Endpoints

#### Expense Claims API
```
GET /api/claims/{employeeId}           # List employee claims
POST /api/claims                       # Create new claim
GET /api/claims/{claimId}             # Get claim details
PUT /api/claims/{claimId}             # Update claim
DELETE /api/claims/{claimId}          # Delete claim
```

#### Employee Management API
```
GET /api/employees                     # List employees
GET /api/employees/{employeeId}        # Employee details
GET /api/employees/{employeeId}/vehicles # Employee vehicles
```

#### Reporting API
```
GET /api/reports/dashboard             # Dashboard data
GET /api/reports/claims               # Claims report
GET /api/reports/expenses             # Expense report
```

### API Versioning Strategy

Future API versions can be implemented using URL versioning:
```
/api/v1/odo-backdate-submission/...   # Current version
/api/v2/odo-backdate-submission/...   # Future version
```

## 📚 Related Documentation

- [Authentication System](../authentication.md) - JWT token generation and validation
- [HomeController](../controllers/HomeController.md) - Dashboard data endpoints
- [Database Schema](../database/schema.md) - Database structure for API data

## 🔍 Advanced Usage Patterns

### Workflow Implementation

#### Complete Backdate Approval Workflow

```javascript
class BackdateWorkflow {
    constructor(baseUrl, token) {
        this.baseUrl = baseUrl;
        this.token = token;
    }

    async processBackdateRequests(yearId, employeeId) {
        try {
            // Step 1: Check if button should be shown
            const buttonStatus = await this.checkButtonVisibility(yearId, employeeId);
            if (!buttonStatus.show_button) {
                return { message: 'No pending requests to process' };
            }

            // Step 2: Get all pending requests
            const submissions = await this.getBackdateSubmissions(yearId, employeeId);
            
            // Step 3: Process each employee's requests
            const results = [];
            for (const [empId, empData] of Object.entries(submissions.data)) {
                const result = await this.processEmployeeRequests(
                    yearId, employeeId, empId, empData
                );
                results.push(result);
            }

            return { success: true, results };
        } catch (error) {
            console.error('Workflow error:', error);
            return { success: false, error: error.message };
        }
    }

    async checkButtonVisibility(yearId, employeeId) {
        const response = await fetch(
            `${this.baseUrl}/api/odo-backdate-submission-show/${yearId}/${employeeId}/?token=${this.token}`
        );
        return await response.json();
    }

    async getBackdateSubmissions(yearId, employeeId) {
        const response = await fetch(
            `${this.baseUrl}/api/odo-backdate-submission/${yearId}/${employeeId}/?token=${this.token}`
        );
        return await response.json();
    }

    async processEmployeeRequests(yearId, approverId, employeeId, empData) {
        // Decide whether to bulk approve or process individually
        if (empData.status_count.Pending > 5) {
            return await this.bulkApprove(yearId, approverId, employeeId);
        } else {
            return await this.processIndividually(yearId, approverId, empData.items);
        }
    }

    async bulkApprove(yearId, approverId, employeeId) {
        const response = await fetch(
            `${this.baseUrl}/api/odo-backdate-bulk-approval/${yearId}/${approverId}/${employeeId}?token=${this.token}`
        );
        return await response.json();
    }

    async processIndividually(yearId, approverId, items) {
        const results = [];
        for (const item of items) {
            if (item.Backdate_Odometer_Status === 'Pending') {
                // Apply business logic for approval/rejection
                const shouldApprove = this.evaluateRequest(item);
                const result = shouldApprove 
                    ? await this.approveRequest(yearId, approverId, item.ExpId)
                    : await this.rejectRequest(yearId, approverId, item.ExpId);
                results.push(result);
            }
        }
        return results;
    }

    evaluateRequest(item) {
        // Business logic for automatic approval/rejection
        const daysDifference = parseInt(item.days_difference);
        const totalKm = parseFloat(item.TotKm);
        
        // Auto-approve if within reasonable limits
        if (daysDifference <= 7 && totalKm <= 100) {
            return true;
        }
        
        // Auto-reject if excessive
        if (daysDifference > 30 || totalKm > 500) {
            return false;
        }
        
        // Manual review needed for edge cases
        return null; // Requires manual intervention
    }

    async approveRequest(yearId, approverId, expenseId) {
        const response = await fetch(
            `${this.baseUrl}/api/odo-backdate-approval/${yearId}/${approverId}/${expenseId}/?token=${this.token}`
        );
        return await response.json();
    }

    async rejectRequest(yearId, approverId, expenseId) {
        const response = await fetch(
            `${this.baseUrl}/api/odo-backdate-rejection/${yearId}/${approverId}/${expenseId}/?token=${this.token}`
        );
        return await response.json();
    }
}

// Usage
const workflow = new BackdateWorkflow('https://your-domain.com', jwtToken);
const result = await workflow.processBackdateRequests(7, 12345);
```

### Batch Processing with Error Handling

```javascript
class BatchProcessor {
    constructor(baseUrl, token, maxRetries = 3) {
        this.baseUrl = baseUrl;
        this.token = token;
        this.maxRetries = maxRetries;
    }

    async processBatch(requests, batchSize = 10) {
        const results = [];
        const errors = [];

        for (let i = 0; i < requests.length; i += batchSize) {
            const batch = requests.slice(i, i + batchSize);
            const batchResults = await Promise.allSettled(
                batch.map(request => this.processWithRetry(request))
            );

            batchResults.forEach((result, index) => {
                if (result.status === 'fulfilled') {
                    results.push(result.value);
                } else {
                    errors.push({
                        request: batch[index],
                        error: result.reason
                    });
                }
            });

            // Rate limiting - wait between batches
            if (i + batchSize < requests.length) {
                await this.delay(1000);
            }
        }

        return { results, errors };
    }

    async processWithRetry(request, attempt = 1) {
        try {
            return await this.makeApiCall(request);
        } catch (error) {
            if (attempt < this.maxRetries && this.isRetryableError(error)) {
                await this.delay(attempt * 1000); // Exponential backoff
                return this.processWithRetry(request, attempt + 1);
            }
            throw error;
        }
    }

    isRetryableError(error) {
        // Retry on network errors or 5xx status codes
        return error.code >= 500 || error.message.includes('network');
    }

    delay(ms) {
        return new Promise(resolve => setTimeout(resolve, ms));
    }

    async makeApiCall(request) {
        const { endpoint, method = 'GET' } = request;
        const response = await fetch(`${this.baseUrl}${endpoint}?token=${this.token}`, {
            method
        });
        
        if (!response.ok) {
            throw new Error(`HTTP ${response.status}: ${response.statusText}`);
        }
        
        return await response.json();
    }
}
```

## 📊 Response Data Structures

### Detailed Response Schemas

#### Backdate Submission Response Schema

```json
{
    "success": true,
    "message": "Odometer backdate submission retrieved successfully.",
    "data": {
        "12345": {
            "approval_type": "hod_level",
            "month": "2024-01",
            "employee_name": "John Doe Smith",
            "EmpCode": "EMP001",
            "total_records": 3,
            "status_count": {
                "Pending": 2,
                "Approved": 1,
                "Rejected": 0
            },
            "items": [
                {
                    "ExpId": 67890,
                    "BillDate": "2024-01-10",
                    "CrDate": "2024-01-15",
                    "WType": "Field Work",
                    "odomtr_opening": "12500",
                    "odomtr_closing": "12650",
                    "opening_filepath": "https://s3.amazonaws.com/bucket/Expense/7/12345/opening_12345.jpg",
                    "closing_filepath": "https://s3.amazonaws.com/bucket/Expense/7/12345/closing_12345.jpg",
                    "TotKm": "150.00",
                    "RatePerKM": "8.50",
                    "ClaimStatus": "Pending",
                    "days_difference": 5,
                    "Backdate_Odometer_Status": "Pending"
                }
            ]
        }
    }
}
```

#### Button Visibility Response Schema

```json
{
    "success": true,
    "message": "Odometer backdate pending count retrieved successfully.",
    "data": {
        "pending_count": 15,
        "show_button": true
    }
}
```

#### Approval/Rejection Response Schema

```json
{
    "success": true,
    "message": "The backdated odometer has been approved successfully.",
    "data": {
        "ExpId": 67890,
        "CrBy": 12345,
        "ClaimYearId": 7,
        "BillDate": "2024-01-10",
        "CrDate": "2024-01-15",
        "ClaimStatus": "Filled",
        "ClaimAtStep": "1",
        "Backdate_Odometer_Status": "A",
        "Backdate_Odometer_By": 98765,
        "Backdate_Odometer_Date": "2024-01-20",
        "updated_at": "2024-01-20T14:30:00.000000Z"
    }
}
```

#### Bulk Operation Response Schema

```json
{
    "success": true,
    "message": "5 backdated odometer record(s) have been approved successfully.",
    "data": {
        "updated_count": 5
    }
}
```

## 🎯 Business Logic Implementation

### Approval Hierarchy

The API implements a sophisticated approval hierarchy system:

#### BU Level Approval (`bu_level`)
- **Approver**: Business Unit General Manager
- **Scope**: All employees within the same Business Unit
- **Validation**: Employee must have `TerrId=0`, `ZoneId=0`, `RegionId=0`, and valid `BUId`

#### HOD Level Approval (`hod_level`)
- **Approver**: Head of Department
- **Scope**: All employees within the same Department
- **Validation**: Employee must be at the top of the department hierarchy

### Backdate Settings Configuration

The system uses `odo_backdate_settings` table with the following key fields:

| Field | Description | Impact |
|-------|-------------|---------|
| `department_id` | Target department | Determines which department's employees are affected |
| `effective_date` | Start date for backdate rules | Only claims created after this date are considered |
| `is_active` | Enable/disable setting | Must be `1` for the API to process requests |
| `approval_type` | `bu_level` or `hod_level` | Determines approval hierarchy |
| `verticals` | Comma-separated vertical IDs | Filters employees by their vertical assignment |
| `delayed_day` | Minimum delay threshold | Only claims with delay > this value are included |

### Status Flow

```
Pending (P) → Approved (A) → Filled
            ↘ Rejected (R)
```

**Status Codes**:
- `P`: Pending approval
- `A`: Approved by authorized personnel
- `R`: Rejected by authorized personnel

**Claim Status Updates**:
- Approval: `ClaimStatus` → `'Filled'`, `ClaimAtStep` → `'1'`
- Rejection: No status change, only backdate status updated

## 🔧 Database Schema Context

### Key Tables and Relationships

#### Primary Tables
- `y{yearId}_expenseclaim` - Main expense claims table (dynamic table name)
- `y{yearId}_monthexpensefinal` - Monthly expense finalization status
- `odo_backdate_settings` - Backdate configuration per department

#### HRIMS Integration
- `hrims.hrm_employee` - Employee basic information
- `hrims.hrm_employee_general` - Employee organizational details
- `hrims.core_departments` - Department information

#### Key Relationships
```sql
-- Employee to Claims
hrm_employee.EmployeeID = expenseclaim.CrBy

-- Employee to Department
hrm_employee_general.EmployeeID = hrm_employee.EmployeeID
hrm_employee_general.DepartmentId = core_departments.id

-- Claims to Monthly Status
expenseclaim.CrBy = monthexpensefinal.EmployeeID
expenseclaim.ClaimMonth = monthexpensefinal.Month

-- Department to Settings
core_departments.id = odo_backdate_settings.department_id
```

## 🚀 Performance Optimization

### Query Optimization Tips

#### 1. Index Recommendations
```sql
-- For expense claims table
CREATE INDEX idx_expense_backdate ON y{year}_expenseclaim 
(CrBy, ClaimId, Backdate_Odometer_Status, ClaimStatus, BillDate, CrDate);

-- For employee general table
CREATE INDEX idx_employee_dept_bu ON hrm_employee_general 
(DepartmentId, BUId, EmpVertical, EmployeeID);

-- For monthly expense final
CREATE INDEX idx_monthexp_status ON y{year}_monthexpensefinal 
(EmployeeID, Month, Status);
```

#### 2. Caching Strategy
```php
// Cache department settings
$backdateSetting = Cache::remember(
    "backdate_settings_{$departmentId}", 
    3600, // 1 hour
    fn() => DB::connection(session('expense_connection'))
        ->table('odo_backdate_settings')
        ->where('department_id', $departmentId)
        ->first()
);

// Cache employee hierarchy
$isHod = Cache::remember(
    "is_hod_{$employeeId}_{$departmentId}",
    1800, // 30 minutes
    fn() => $this->isHod($employeeId, $departmentId)
);
```

#### 3. Pagination for Large Datasets
```javascript
async function getPaginatedSubmissions(yearId, employeeId, page = 1, limit = 50) {
    const response = await fetch(
        `/api/odo-backdate-submission/${yearId}/${employeeId}/?token=${token}&page=${page}&limit=${limit}`
    );
    return await response.json();
}
```

## 🔐 Advanced Security Features

### Token Validation Process

The API uses Firebase JWT with the following validation steps:

1. **Token Extraction**: Retrieved from query parameter `token`
2. **Signature Verification**: Using secret key `v7n90l9uvy`
3. **Expiration Check**: Validates token hasn't expired
4. **Payload Validation**: Ensures required claims are present

### Security Headers

Recommended security headers for API responses:

```php
// In middleware or controller
return response()->json($data)->withHeaders([
    'X-Content-Type-Options' => 'nosniff',
    'X-Frame-Options' => 'DENY',
    'X-XSS-Protection' => '1; mode=block',
    'Strict-Transport-Security' => 'max-age=31536000; includeSubDomains',
    'Content-Security-Policy' => "default-src 'self'",
]);
```

### Input Sanitization

```php
// Parameter validation example
$yearId = filter_var($yearId, FILTER_VALIDATE_INT);
$employeeId = filter_var($employeeId, FILTER_VALIDATE_INT);
$expenseId = filter_var($expenseId, FILTER_VALIDATE_INT);

if (!$yearId || !$employeeId || ($expenseId !== null && !$expenseId)) {
    return $this->jsonError('Invalid parameters provided.');
}
```

## 📈 Monitoring and Analytics

### API Usage Tracking

```php
// Log API calls for monitoring
Log::info('API Call', [
    'endpoint' => request()->path(),
    'method' => request()->method(),
    'user_id' => $employeeId,
    'ip' => request()->ip(),
    'user_agent' => request()->userAgent(),
    'response_time' => microtime(true) - LARAVEL_START,
]);
```

### Performance Metrics

Track these key metrics:
- **Response Time**: Average API response time
- **Success Rate**: Percentage of successful API calls
- **Error Rate**: Frequency of different error types
- **Usage Patterns**: Most frequently used endpoints
- **Peak Hours**: High-traffic time periods

### Health Check Endpoint

Consider adding a health check endpoint:

```php
Route::get('health', function () {
    return response()->json([
        'status' => 'healthy',
        'timestamp' => now()->toISOString(),
        'version' => config('app.version'),
        'database' => DB::connection()->getPdo() ? 'connected' : 'disconnected',
    ]);
});
```

---

This comprehensive API documentation provides a solid foundation for external integrations while maintaining security and scalability for the Xeasy Laravel expense management system.