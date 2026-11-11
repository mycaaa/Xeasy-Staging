# 🔐 Authentication System

Xeasy Laravel implements a comprehensive authentication system supporting both traditional session-based authentication and modern JWT token authentication for API access.

## 🎯 Authentication Overview

The authentication system provides:
- **Dual Authentication Methods**: Session-based and JWT token
- **Multi-Company Support**: Company-specific user authentication
- **Role-Based Access Control**: Granular permission management
- **Activity Logging**: Comprehensive audit trails
- **Security Features**: Password encryption, session management, and CSRF protection

## 🔑 Authentication Methods

### 1. Session-Based Authentication (Web)

**Primary Method**: Used for web interface access

**Flow**:
1. User submits credentials (Employee ID + Password)
2. System validates against HRIMS database
3. Password decryption using custom algorithm
4. Session creation with user context
5. Redirect to dashboard

**Login Endpoint**: `POST /` or `POST /login`

### 2. JWT Token Authentication (API)

**Secondary Method**: Used for API access and external integrations

**Flow**:
1. Client submits JWT token
2. Token validation using secret key
3. User extraction from token payload
4. Automatic session creation
5. API access granted

**Token Configuration**:
- **Secret Key**: `v7n90l9uvy`
- **Algorithm**: HS256
- **Library**: Firebase JWT

## 🏗️ Authentication Architecture

### AuthController Structure

**Location**: `app/Http/Controllers/AuthController.php`

**Key Methods**:
- `showLoginForm()` - Display login page
- `login()` - Handle authentication
- `logout()` - Terminate session

### Authentication Flow Diagram

```
┌─────────────────┐
│ Login Request   │
└─────────────────┘
         ↓
┌─────────────────┐
│ Input Validation│
│ • Employee ID   │
│ • Password      │
│ • Company       │
│ • Financial Yr  │
└─────────────────┘
         ↓
┌─────────────────┐
│ Authentication  │
│ Method Check    │
└─────────────────┘
    ↓         ↓
┌─────────┐ ┌─────────┐
│ Normal  │ │ Token   │
│ Auth    │ │ Auth    │
└─────────┘ └─────────┘
    ↓         ↓
┌─────────────────┐
│ User Validation │
│ & Session Setup │
└─────────────────┘
         ↓
┌─────────────────┐
│ Dashboard       │
│ Redirect        │
└─────────────────┘
```

## 🔐 Password Security

### Custom Encryption System

Xeasy Laravel uses a custom encryption system for legacy password compatibility:

**Encryption Array**:
```php
protected $strcode = [
    '', '0', '1', '2', '3', '4', '5', '6', '7', '8', '9',
    'a', 'A', 'b', 'B', 'c', 'C', 'd', 'D', 'e', 'E',
    'f', 'F', 'g', 'G', 'h', 'H', 'i', 'I', 'j', 'J',
    'k', 'K', 'l', 'L', 'm', 'M', 'n', 'N', 'o', 'O',
    'p', 'P', 'q', 'Q', 'r', 'R', 's', 'S', 't', 'T',
    'u', 'U', 'v', 'V', 'w', 'W', 'x', 'X', 'y', 'Y',
    'z', 'Z', '#', '@', '$', '%', '^', '&', '*', '_',
    '!', '?', ' '
];
```

**Password Decryption Process**:
1. Convert encrypted string to character array
2. Map characters using strcode array
3. Reverse the decryption algorithm
4. Compare with user input

### Modern Password Hashing

For new users, the system uses Laravel's built-in password hashing:
- **Algorithm**: bcrypt
- **Rounds**: 12 (configurable)
- **Salt**: Automatically generated

## 🏢 Multi-Company Authentication

### Company Selection

**Required Fields**:
- Employee ID
- Password
- Company (1-4)
- Financial Year

**Company Configuration**:
```php
// config/company_databases.php
'companies' => [
    1 => ['name' => 'VNR Seeds Pvt Ltd'],
    2 => ['name' => 'VNR Group Concern'],
    3 => ['name' => 'Nursery Pvt Ltd'],
    4 => ['name' => 'VVNR'],
]
```

### Database Context Switching

After successful authentication:
1. Company ID stored in session
2. Database connections switched dynamically
3. User context established for selected company
4. Financial year context set

## 📝 Session Management

### Session Data Structure

**Session Variables**:
```php
session([
    'employee_id' => $user->EmployeeID,
    'role_id' => $user->role_id,
    'company_id' => $request->company,
    'year_id' => $request->financial_year,
    'emp_code' => $employee->EmpCode,
    'authenticated' => true
]);
```

### Session Configuration

**Driver**: Database (recommended for production)
**Lifetime**: 120 minutes
**Table**: `sessions`

**Configuration** (`config/session.php`):
```php
'driver' => env('SESSION_DRIVER', 'database'),
'lifetime' => env('SESSION_LIFETIME', 120),
'expire_on_close' => false,
'encrypt' => false,
'files' => storage_path('framework/sessions'),
'connection' => env('SESSION_CONNECTION'),
'table' => env('SESSION_TABLE', 'sessions'),
```

## 🛡️ Authorization System

### Role-Based Access Control (RBAC)

**Package**: Spatie Laravel Permission

**Core Components**:
- **Roles**: User role definitions
- **Permissions**: Granular access controls
- **Permission Groups**: Organized permission sets

### Permission Structure

**Permission Naming Convention**:
```
feature.action
├── users.view
├── users.create
├── users.edit
├── users.delete
├── claims.view
├── claims.approve
└── reports.export
```

### Middleware Protection

**Route Protection**:
```php
Route::middleware(['auth', 'permission:users.view'])
    ->get('/users', [UsersController::class, 'index']);
```

**Controller Protection**:
```php
public function __construct()
{
    $this->middleware('auth');
    $this->middleware('permission:users.view')->only(['index', 'show']);
    $this->middleware('permission:users.create')->only(['create', 'store']);
}
```

## 📊 Activity Logging

### Authentication Logging

**UserLog Model**: Tracks all authentication attempts

**Logged Data**:
```php
[
    'user_id' => $user->id ?? 0,
    'ip_address' => $request->ip(),
    'is_success' => 1, // 1 for success, 0 for failure
    'user_agent' => $request->userAgent(),
    'timestamp' => now()->toDateTimeString(),
    'status' => json_encode($status_messages),
    'login_method' => 'normal' // or 'token'
]
```

### Activity Tracking

**Spatie Activity Log**: Tracks user actions

**Configuration**:
```php
// In models
use LogsActivity;

protected static $logAttributes = ['*'];
protected static $logName = 'user_activity';
protected static $logOnlyDirty = true;
```

## 🔧 JWT Token Authentication

### Token Structure

**JWT Payload**:
```json
{
    "employee_id": "12345",
    "company_id": 1,
    "year_id": 7,
    "iat": 1640995200,
    "exp": 1640998800
}
```

### Token Validation Process

1. **Extract Token**: From request parameter
2. **Decode JWT**: Using secret key and HS256 algorithm
3. **Validate Payload**: Check required fields
4. **User Lookup**: Find user by employee_id
5. **Session Creation**: Automatic session establishment
6. **Context Setting**: Company and year context

### API Authentication Example

**Request**:
```http
GET /api/endpoint?token=eyJ0eXAiOiJKV1QiLCJhbGciOiJIUzI1NiJ9...
```

**Token Generation** (External System):
```php
use Firebase\JWT\JWT;

$payload = [
    'employee_id' => '12345',
    'company_id' => 1,
    'year_id' => 7,
    'iat' => time(),
    'exp' => time() + 3600 // 1 hour
];

$token = JWT::encode($payload, 'v7n90l9uvy', 'HS256');
```

## 🚫 Error Handling

### Authentication Errors

**Common Error Scenarios**:
1. **Invalid Credentials**: Wrong employee ID or password
2. **Inactive User**: User account disabled
3. **Invalid Company**: Company not found or inactive
4. **Invalid Financial Year**: Year not found or inactive
5. **Token Expired**: JWT token past expiration
6. **Invalid Token**: Malformed or invalid JWT

### Error Response Format

**Web Authentication**:
```php
return back()
    ->withErrors(['error' => 'Invalid credentials'])
    ->withInput($request->except('password'));
```

**API Authentication**:
```json
{
    "error": "Invalid token",
    "message": "The provided token is invalid or expired",
    "code": 401
}
```

## 🔒 Security Best Practices

### Implemented Security Measures

1. **Password Protection**:
   - Custom encryption for legacy compatibility
   - bcrypt hashing for new passwords
   - Password confirmation for sensitive operations

2. **Session Security**:
   - Database-stored sessions
   - Session regeneration on login
   - Automatic session cleanup

3. **CSRF Protection**:
   - Laravel's built-in CSRF middleware
   - Token validation on all forms
   - API exemption for stateless operations

4. **Activity Monitoring**:
   - All authentication attempts logged
   - IP address and user agent tracking
   - Failed login attempt monitoring

5. **Token Security**:
   - JWT token expiration
   - Secret key protection
   - Token validation on each request

### Security Configuration

**Authentication Guards** (`config/auth.php`):
```php
'guards' => [
    'web' => [
        'driver' => 'session',
        'provider' => 'users',
    ],
],

'providers' => [
    'users' => [
        'driver' => 'eloquent',
        'model' => App\Models\User::class,
    ],
],
```

## 🔄 Logout Process

### Session Termination

**Logout Flow**:
1. Session invalidation
2. CSRF token regeneration
3. Activity logging
4. Redirect to login page

**Logout Implementation**:
```php
public function logout(Request $request)
{
    // Log the logout activity
    activity()->log('User logged out');
    
    // Invalidate session
    Auth::logout();
    $request->session()->invalidate();
    $request->session()->regenerateToken();
    
    return redirect()->route('login');
}
```

## 📱 API Authentication Examples

### Using JWT Token

**cURL Example**:
```bash
curl -X GET "http://localhost:8000/api/odo-backdate-submission/7/12345/?token=eyJ0eXAiOiJKV1QiLCJhbGciOiJIUzI1NiJ9..."
```

**JavaScript Example**:
```javascript
const token = 'eyJ0eXAiOiJKV1QiLCJhbGciOiJIUzI1NiJ9...';
const response = await fetch(`/api/endpoint?token=${token}`);
const data = await response.json();
```

### Using Sanctum (Alternative)

**Token Generation**:
```php
$token = $user->createToken('api-token')->plainTextToken;
```

**API Request**:
```http
GET /api/endpoint
Authorization: Bearer {token}
```

---

The authentication system provides robust security while maintaining flexibility for both web and API access, ensuring secure multi-company operations with comprehensive audit trails.