# 🔐 AuthController Documentation

The AuthController handles all authentication-related functionality in the Xeasy Laravel expense management system, including both traditional login/logout and JWT token-based authentication.

## 📋 Overview

**Location**: `app/Http/Controllers/AuthController.php`

**Purpose**: Manages user authentication, session management, and security operations including custom encryption/decryption for legacy password systems.

**Key Features**:
- Dual authentication methods (traditional + JWT token)
- Custom encryption/decryption for legacy passwords
- Comprehensive audit logging
- Session management and security
- Financial year validation
- Multi-company support

## 🔧 Constructor & Properties

```php
protected $strcode;

public function __construct()
{
    $this->strcode = ['', '0', '1', '2', '3', '4', '5', '6', '7', '8', '9', 
                      'a', 'A', 'b', 'B', 'c', 'C', 'd', 'D', 'e', 'E', 
                      'f', 'F', 'g', 'G', 'h', 'H', 'i', 'I', 'j', 'J', 
                      'k', 'K', 'l', 'L', 'm', 'M', 'n', 'N', 'o', 'O', 
                      'p', 'P', 'q', 'Q', 'r', 'R', 's', 'S', 't', 'T', 
                      'u', 'U', 'v', 'V', 'w', 'W', 'x', 'X', 'y', 'Y', 
                      'z', 'Z', '#', '@', '$', '%', '^', '&', '*', '_', 
                      '!', '?', ' '];
}
```

**Properties**:
- `$strcode` - Character array used for custom encryption/decryption algorithm

## 📊 Public Methods

### 1. Login Form Display

#### `showLoginForm()`

**Route**: `GET /login`
**Middleware**: None

**Purpose**: Displays the login form or redirects authenticated users to dashboard.

**Returns**: 
- Redirect to dashboard if already authenticated
- Login view for unauthenticated users

**Example Usage**:
```php
GET /login
```

**Business Logic**:
1. Checks if user is already authenticated
2. Redirects to dashboard if authenticated
3. Returns login view if not authenticated

---

### 2. User Authentication

#### `login(Request $request)`

**Route**: `POST /login`
**Middleware**: None

**Purpose**: Handles both traditional and JWT token-based authentication with comprehensive logging.

**Request Parameters**:

**Traditional Login**:
| Parameter | Type | Required | Description |
|-----------|------|----------|-------------|
| employeeid | string | Yes | Employee ID/Code |
| password | string | Yes | Employee password |
| company | integer | Yes | Company ID (must be 1) |
| financial_year | integer | Yes | Financial year ID |
| remember | boolean | No | Remember me checkbox |

**Token-Based Login**:
| Parameter | Type | Required | Description |
|-----------|------|----------|-------------|
| token | string | Yes | JWT authentication token |
| employeeid | string | No | Employee ID (optional, validated against token) |
| password | string | No | Optional password for additional verification |

**Response**:
- **Success**: Redirect to dashboard with success message
- **Failure**: Redirect back with error messages and input (except password)

**Authentication Flow**:

#### Traditional Authentication
1. **Input Validation**: Validates required fields
2. **Financial Year Check**: Ensures valid financial year exists
3. **Employee Lookup**: Finds employee in HRIMS system
4. **Password Verification**: Decrypts stored password and compares
5. **User Account Check**: Verifies active user account exists
6. **Session Setup**: Sets session variables and logs in user

#### JWT Token Authentication
1. **Token Decoding**: Decodes JWT using secret key `v7n90l9uvy`
2. **Token Validation**: Validates token structure and claims
3. **User Lookup**: Finds user by employee ID from token
4. **Session Conflict Check**: Handles existing sessions for different users
5. **Session Setup**: Uses static values (company=1, financial_year=7)

**Session Variables Set**:
```php
Session::put('back_office', false);
Session::put('back_office_active', false);
Session::put('role_id', $user->role_id ?? 7);
Session::put('employee_id', $user->employee_id);
Session::put('company_id', $token_valid ? 1 : $request->company);
Session::put('year_id', $token_valid ? 7 : $request->financial_year);
Session::put('emp_code', $request->employeeid ?: ($decoded ? $decoded->sub : ''));
Session::put('authenticated', true);
```

**Audit Logging**:
Every login attempt is logged with:
- User ID
- IP address
- Success/failure status
- User agent
- Timestamp
- Detailed status messages
- Login method (normal/token)

**Error Scenarios**:
- Invalid input validation
- Invalid financial year
- Invalid JWT token
- Employee not found
- Incorrect password
- Inactive user account
- Session conflicts

---

### 3. User Logout

#### `logout(Request $request)`

**Route**: `POST /logout`
**Middleware**: `auth`

**Purpose**: Logs out the user and clears all session data.

**Parameters**: None

**Returns**: Redirect to login page with success message

**Business Logic**:
1. Logs out user from Laravel Auth
2. Invalidates current session
3. Regenerates CSRF token
4. Redirects to login page

**Example Usage**:
```php
POST /logout
```

---

## 🔐 Custom Encryption Methods

The AuthController includes a sophisticated custom encryption system for handling legacy passwords:

### 4. Password Decryption

#### `decrypt($encryptedText)`

**Purpose**: Decrypts passwords using custom algorithm.

**Parameters**:
- `$encryptedText`: Encrypted password string

**Returns**: Decrypted plain text password

**Algorithm**:
1. Splits encrypted text into 3-character chunks
2. Processes each chunk through derandomization
3. Concatenates results to form original password

---

#### `derandomized($chunk, $strcode)`

**Purpose**: Core decryption logic for individual chunks.

**Parameters**:
- `$chunk`: 3-character encrypted chunk
- `$strcode`: Character mapping array

**Returns**: Decrypted character(s)

**Algorithm**:
1. Splits chunk into components
2. Locates character positions in strcode array
3. Performs reverse mathematical operation
4. Handles wraparound for negative values

---

### 5. Password Encryption (Legacy Support)

#### `encrypt($thetext)`

**Purpose**: Encrypts plain text using custom algorithm.

**Parameters**:
- `$thetext`: Plain text to encrypt

**Returns**: Encrypted string

**Algorithm**:
1. Splits text into 2-character segments
2. Applies random key transformation
3. Converts to encrypted format

---

#### `convert_keyto_value($thetext)`

**Purpose**: Converts text to encrypted values using random key.

**Algorithm**:
1. Generates random key
2. Applies key transformation to each character
3. Appends random key to result
4. Ensures no invalid combinations

---

#### `add_random_key($thetext)`

**Purpose**: Adds random key transformation to text.

**Returns**: Array of transformed character positions

---

### 6. Utility Methods

#### `strsplt($text, $size = 1)`

**Purpose**: Custom string splitting function.

**Parameters**:
- `$text`: String to split
- `$size`: Chunk size (default: 1)

**Returns**: Array of string chunks

---

#### `key_locator($code, $strcode)`

**Purpose**: Finds position of character in strcode array.

**Parameters**:
- `$code`: Character to find
- `$strcode`: Character mapping array

**Returns**: Position index (0 if not found)

---

## 🔒 Security Features

### JWT Token Security

**Configuration**:
- **Secret Key**: `v7n90l9uvy`
- **Algorithm**: HS256
- **Library**: Firebase JWT

**Token Validation**:
- Signature verification
- Expiration checking
- Subject (employee ID) validation
- Optional employee ID cross-verification

### Session Security

**Session Management**:
- Session invalidation on logout
- CSRF token regeneration
- Session conflict detection
- Secure session variable handling

**Session Variables**:
- User identification data
- Role and permission context
- Company and financial year context
- Authentication status flags

### Password Security

**Legacy Password Handling**:
- Custom encryption/decryption algorithm
- Character-based transformation
- Random key generation
- Secure comparison operations

### Audit Trail

**Comprehensive Logging**:
```php
UserLog::create([
    'user_id' => $user_id,
    'ip_address' => $request->ip(),
    'is_success' => $success_flag,
    'user_agent' => $request->userAgent(),
    'timestamp' => now()->toDateTimeString(),
    'status' => json_encode($status_messages),
    'login_method' => $login_method,
]);
```

**Logged Information**:
- All login attempts (successful and failed)
- Detailed status progression
- IP addresses and user agents
- Authentication method used
- Error reasons and validation failures

## 🎯 Business Logic

### Financial Year Validation

```php
$fy_check = FinancialYear::orderBy('YearId', 'desc')->first();
if (!$fy_check) {
    return back()->with('error', 'Invalid or inactive financial year.');
}
```

**Purpose**: Ensures system operates within valid financial year context.

### Multi-Authentication Support

The controller supports two distinct authentication methods:

1. **Traditional Authentication**:
   - Employee ID + Password
   - Company selection
   - Financial year selection
   - Full validation chain

2. **JWT Token Authentication**:
   - Token-based verification
   - Optional additional password check
   - Static company/year assignment
   - Simplified validation

### Session Conflict Resolution

```php
if (Session::has('authenticated') && Session::get('employee_id') != $user->employee_id) {
    Session::flush();
    return back()->with('error', 'Previous user session terminated. Logging in as new user.');
}
```

**Purpose**: Prevents session hijacking and ensures clean user transitions.

## 🚨 Error Handling

### Validation Errors

**Input Validation**:
- Required field checking
- Format validation
- Business rule validation
- Custom error messages

**Error Response Format**:
```php
return back()->withErrors($e->errors())->withInput($request->except('password'));
```

### Authentication Errors

**Common Error Scenarios**:
- Invalid credentials
- Expired tokens
- Inactive user accounts
- Financial year issues
- Session conflicts

**Error Logging**:
All errors are logged with context for debugging and security monitoring.

## 📈 Performance Considerations

### Database Queries

**Optimized Lookups**:
- Single query for employee verification
- Efficient user account lookup
- Financial year caching opportunity

### Session Management

**Efficient Session Handling**:
- Minimal session data storage
- Strategic session variable placement
- Clean session invalidation

### Encryption Performance

**Custom Algorithm Efficiency**:
- Character-based operations
- Minimal memory usage
- Fast encryption/decryption cycles

## 🔗 Integration Points

### HRIMS Integration

**Employee Data Source**:
- Employee lookup by code
- Password verification
- Department and role context

### User Management System

**User Account Validation**:
- Active status checking
- Role assignment
- Permission context setup

### Audit System

**Comprehensive Logging**:
- UserLog model integration
- Detailed status tracking
- Security event recording

## 📊 Usage Examples

### Traditional Login

```javascript
// Frontend login form submission
const loginData = {
    employeeid: 'EMP001',
    password: 'userpassword',
    company: 1,
    financial_year: 7,
    remember: true
};

fetch('/login', {
    method: 'POST',
    headers: {
        'Content-Type': 'application/json',
        'X-CSRF-TOKEN': csrfToken
    },
    body: JSON.stringify(loginData)
});
```

### JWT Token Login

```javascript
// Token-based authentication
const tokenLogin = {
    token: 'eyJ0eXAiOiJKV1QiLCJhbGciOiJIUzI1NiJ9...',
    employeeid: 'EMP001' // Optional verification
};

fetch('/login', {
    method: 'POST',
    headers: {
        'Content-Type': 'application/json',
        'X-CSRF-TOKEN': csrfToken
    },
    body: JSON.stringify(tokenLogin)
});
```

### Logout

```javascript
// User logout
fetch('/logout', {
    method: 'POST',
    headers: {
        'X-CSRF-TOKEN': csrfToken
    }
});
```

## 🔧 Configuration

### JWT Configuration

```php
// JWT settings (in controller)
$secretKey = '';
$algorithm = '';

// Token decoding
$decoded = JWT::decode($token, new Key($secretKey, 'HS256'));
```

### Session Configuration

```php
// Default session values for token authentication
$defaultCompany = 1;
$defaultFinancialYear = 7;
$defaultRole = 7;
```

---

The AuthController provides a robust, secure authentication system that supports both traditional and modern authentication methods while maintaining comprehensive audit trails and security features essential for enterprise expense management systems.