# 👤 Admin UsersController Documentation

The Admin UsersController manages user accounts, authentication, role assignments, and user activity monitoring in the Xeasy Laravel expense management system.

## 📋 Overview

**Location**: `app/Http/Controllers/admin/UsersController.php`

**Purpose**: Handles comprehensive user management including CRUD operations, role assignments, permission management, employee imports, and user activity tracking.

**Middleware**: Inherits from base Controller (typically includes auth and admin middleware)

**Key Features**:
- Complete user lifecycle management
- Role-based access control integration
- Employee data import from HRIMS
- User activity monitoring and analytics
- Permission management interface
- DataTables integration for large datasets
- User profile management

## 📊 Public Methods

### 1. User Management Interface

#### `index(): View`

**Route**: `GET /admin/users`
**Permission**: Admin access required

**Purpose**: Displays the main user management interface with filtering capabilities.

**Query Parameters**:
| Parameter | Type | Required | Description |
|-----------|------|----------|-------------|
| status | string | No | Filter by status: 'active', 'inactive', 'all' (default: 'active') |

**Returns**: Blade view (`admin.users`) with roles and status data

**Data Provided to View**:
- **roles**: Active roles for assignment dropdown
- **status**: Current filter status

**Business Logic**:
1. **Role Loading**: Retrieves active roles for user assignment
2. **Status Handling**: Processes status filter parameter
3. **Interface Preparation**: Prepares data for DataTables integration

---

### 2. DataTables API

#### `datatable(Request $request): JsonResponse`

**Route**: `POST /admin/users/datatable`
**Permission**: Admin access required

**Purpose**: Provides server-side DataTables integration for user listing with search, pagination, and filtering.

**Request Parameters**:
| Parameter | Type | Required | Description |
|-----------|------|----------|-------------|
| draw | integer | Yes | DataTables draw counter |
| start | integer | Yes | Starting record number |
| length | integer | Yes | Number of records to return |
| search.value | string | No | Search term |
| status | string | No | Status filter |

**Response Structure**:
```json
{
    "draw": 1,
    "recordsTotal": 1000,
    "recordsFiltered": 250,
    "data": [
        {
            "id": 1,
            "name": "John Doe",
            "email": "john.doe@company.com",
            "roles": [
                {
                    "name": "Admin",
                    "id": 1
                }
            ],
            "status": 1,
            "can_modify": true,
            "can_remove": true,
            "can_manage_permissions": true,
            "actions": ""
        }
    ]
}
```

**Advanced Features**:
- **Permission-Based Actions**: Includes user-specific action permissions
- **Role Information**: Includes assigned roles for each user
- **Search Functionality**: Searches across name and email fields
- **Status Filtering**: Filters by active/inactive status
- **Pagination**: Efficient handling of large user datasets

**Business Logic**:
1. **Query Building**: Constructs query with relationships and filters
2. **Search Implementation**: Applies search across multiple fields
3. **Permission Checking**: Determines user-specific action permissions
4. **Data Transformation**: Formats data for frontend consumption

---

### 3. User Activity Monitoring

#### `userActivity(Request $request): View`

**Route**: `GET /admin/users/activity`
**Permission**: Admin access required

**Purpose**: Displays user activity monitoring interface.

**Returns**: Blade view (`reports.user_activity`)

**Features**:
- Login activity tracking
- IP address monitoring
- Authentication method analysis
- User behavior analytics

---

#### `userActivityData(Request $request): JsonResponse`

**Route**: `POST /admin/users/activity-data`
**Permission**: Admin access required

**Purpose**: Provides comprehensive user activity data with analytics and filtering.

**Request Parameters**:
| Parameter | Type | Required | Description |
|-----------|------|----------|-------------|
| date_from | date | No | Start date for activity filter |
| date_to | date | No | End date for activity filter |
| user_ids | array/string | No | Specific user IDs to filter |
| login_method | string | No | Login method filter ('token', 'normal', 'all') |
| filter_type | string | No | Special filter type ('multiple_ip') |

**Response Structure**:
```json
{
    "success": true,
    "message": "User activity data and statistics fetched successfully.",
    "data": {
        "activities": [
            {
                "id": 1,
                "user_id": 12345,
                "ip_address": "192.168.1.100",
                "is_success": 1,
                "user_agent": "Mozilla/5.0...",
                "timestamp": "2024-01-15 10:30:00",
                "status": "{\"login_status\":\"success\"}",
                "login_method": "normal",
                "user_name": "John Doe",
                "user_email": "john.doe@company.com",
                "employee_id": 12345
            }
        ],
        "stats": {
            "total_logins": 1500,
            "unique_users": 250,
            "token_logins": 800,
            "unique_ips": 180,
            "peak_hour": 9,
            "multiple_ip_users": 15
        }
    }
}
```

**Analytics Features**:
- **Total Logins**: Count of all login attempts
- **Unique Users**: Number of distinct users
- **Token Logins**: JWT-based authentication count
- **Unique IPs**: Number of distinct IP addresses
- **Peak Hour**: Hour with most login activity
- **Multiple IP Users**: Users logging in from multiple IPs

**Advanced Filtering**:
- **Date Range**: Custom date range filtering
- **User-Specific**: Filter by specific user IDs
- **Login Method**: Filter by authentication method
- **Security Analysis**: Multiple IP address detection

---

### 4. User Creation

#### `store(StoreUserRequest $request): JsonResponse`

**Route**: `POST /admin/users`
**Permission**: Admin access required

**Purpose**: Creates a new user account with role assignment.

**Request Parameters** (via StoreUserRequest):
| Parameter | Type | Required | Description |
|-----------|------|----------|-------------|
| name | string | Yes | User's full name |
| email | string | Yes | Unique email address |
| password | string | Yes | User password |
| is_active | boolean | No | Active status (default: false) |
| role_id | array/integer | No | Role ID(s) to assign |

**Request Example**:
```json
{
    "name": "Jane Smith",
    "email": "jane.smith@company.com",
    "password": "SecurePassword123",
    "is_active": true,
    "role_id": [2, 3]
}
```

**Response Structure**:
```json
{
    "success": true,
    "message": "User created successfully.",
    "data": {
        "id": 15,
        "name": "Jane Smith",
        "email": "jane.smith@company.com",
        "status": 1,
        "created_by": 1,
        "updated_by": 1,
        "created_at": "2024-01-15T10:30:00.000000Z",
        "updated_at": "2024-01-15T10:30:00.000000Z"
    }
}
```

**Business Logic**:
1. **Validation**: Uses StoreUserRequest for comprehensive validation
2. **Password Hashing**: Automatically hashes password using Laravel Hash
3. **User Attribution**: Sets created_by and updated_by to current user
4. **Role Assignment**: Assigns specified roles using Spatie Permission
5. **Status Mapping**: Maps boolean is_active to integer status

---

### 5. Employee Import from HRIMS

#### `importEmployees(Request $request): JsonResponse`

**Route**: `POST /admin/users/import-employees`
**Permission**: Admin access required

**Purpose**: Imports employee data from HRIMS system to create user accounts.

**Parameters**: None (processes all eligible employees)

**Response Structure**:
```json
{
    "success": true,
    "message": "150 employees imported successfully.",
    "data": {
        "imported_count": 150
    }
}
```

**Business Logic**:
1. **HRIMS Connection**: Connects to HRIMS database
2. **Employee Query**: Retrieves employees with email addresses
3. **Chunked Processing**: Processes employees in batches of 100
4. **Duplicate Handling**: Updates existing users, creates new ones
5. **Default Role Assignment**: Assigns default role ID 7
6. **Status Mapping**: Maps HRIMS EmpStatus to user status
7. **Password Preservation**: Uses existing HRIMS password

**Import Process**:
```php
// Employee data mapping
$name = trim($employee->Fname . ' ' . ($employee->Sname ? $employee->Sname . ' ' : '') . $employee->Lname);
$status = $employee->EmpStatus === 'A' ? 1 : 0;

// Duplicate check and handling
if ($existingUser) {
    // Update existing user
    $existingUser->update([...]);
} else {
    // Create new user
    $user = User::create([...]);
    $user->assignRole($defaultRoleId);
}
```

**Performance Features**:
- **Chunked Processing**: Handles large datasets efficiently
- **Memory Management**: Processes in 100-record batches
- **Duplicate Prevention**: Checks existing users before creation
- **Bulk Operations**: Optimized for large-scale imports

---

### 6. User Retrieval and Editing

#### `edit(string $id): JsonResponse`

**Route**: `GET /admin/users/{id}/edit`
**Permission**: Admin access required

**Purpose**: Retrieves user data for editing, including assigned roles.

**Parameters**:
| Parameter | Type | Required | Description |
|-----------|------|----------|-------------|
| id | string | Yes | User ID (route parameter) |

**Response Structure**:
```json
{
    "success": true,
    "message": "User fetched successfully.",
    "data": {
        "id": 15,
        "name": "Jane Smith",
        "email": "jane.smith@company.com",
        "status": 1,
        "employee_id": 12345,
        "role_id": 7,
        "role_ids": [2, 3],
        "created_at": "2024-01-15T10:30:00.000000Z",
        "updated_at": "2024-01-15T10:30:00.000000Z"
    }
}
```

**Features**:
- **Role Information**: Includes assigned role IDs
- **Complete User Data**: Returns all user attributes
- **Edit Form Population**: Formatted for form population

---

### 7. User Update

#### `update(UpdateUserRequest $request, string $id): JsonResponse`

**Route**: `PUT /admin/users/{id}`
**Permission**: Admin access required

**Purpose**: Updates user information and role assignments.

**Parameters**:
| Parameter | Type | Required | Description |
|-----------|------|----------|-------------|
| id | string | Yes | User ID (route parameter) |
| name | string | Yes | Updated user name |
| is_active | boolean | No | Updated active status |
| role_id | array/integer | No | Updated role assignments |

**Request Example**:
```json
{
    "name": "Jane Smith Updated",
    "is_active": false,
    "role_id": [2, 4, 5]
}
```

**Response Structure**:
```json
{
    "success": true,
    "message": "User updated successfully.",
    "data": {
        "id": 15,
        "name": "Jane Smith Updated",
        "email": "jane.smith@company.com",
        "status": 0,
        "updated_by": 1,
        "updated_at": "2024-01-15T15:45:00.000000Z"
    }
}
```

**Business Logic**:
1. **User Validation**: Ensures user exists
2. **Selective Update**: Updates only provided fields
3. **Role Synchronization**: Uses syncRoles for role management
4. **Attribution**: Updates updated_by field
5. **Timestamp Management**: Automatically updates timestamp

---

### 8. User Deletion

#### `destroy(string $id): JsonResponse`

**Route**: `DELETE /admin/users/{id}`
**Permission**: Admin access required

**Purpose**: Deletes a user account.

**Parameters**:
| Parameter | Type | Required | Description |
|-----------|------|----------|-------------|
| id | string | Yes | User ID (route parameter) |

**Response Structure**:
```json
{
    "success": true,
    "message": "User deleted successfully.",
    "data": null
}
```

**Considerations**:
- **Soft Delete**: Consider implementing soft delete for audit purposes
- **Dependency Check**: Should verify no active sessions or claims
- **Role Cleanup**: Automatically removes role assignments

---

### 9. User Profile Management

#### `profile(): View`

**Route**: `GET /admin/profile`
**Permission**: Authenticated user

**Purpose**: Displays current user's profile page.

**Returns**: Blade view (`admin.profile`) with user details

**Features**:
- **Personal Information**: User's profile data
- **Account Settings**: Account configuration options
- **Activity History**: User's recent activity

---

### 10. Permission Management Interface

#### `getPermissionView($id): View`

**Route**: `GET /admin/users/{id}/permissions`
**Permission**: Admin access with manage_permissions

**Purpose**: Displays permission management interface for a specific user.

**Parameters**:
| Parameter | Type | Required | Description |
|-----------|------|----------|-------------|
| id | integer | Yes | User ID (route parameter) |

**Returns**: Blade view (`admin.set_permission`) with permission data

**Data Provided to View**:
- **roles**: Available roles for assignment
- **user**: User object
- **userPermissions**: User's direct permissions grouped by category
- **allPermissions**: All available permissions grouped by category
- **hasRoles**: User's currently assigned role IDs

**Permission Structure**:
```php
$userPermissions = [
    'User Management' => [
        ['id' => 1, 'name' => 'view_users', 'group' => 'User Management'],
        ['id' => 2, 'name' => 'create_users', 'group' => 'User Management']
    ],
    'Reports' => [
        ['id' => 10, 'name' => 'view_reports', 'group' => 'Reports']
    ]
];
```

---

## 🔧 Implementation Details

### Request Validation

#### StoreUserRequest
```php
// Validation rules for user creation
$rules = [
    'name' => 'required|string|max:255',
    'email' => 'required|email|unique:users,email',
    'password' => 'required|string|min:8',
    'is_active' => 'boolean',
    'role_id' => 'array|exists:roles,id'
];
```

#### UpdateUserRequest
```php
// Validation rules for user updates
$rules = [
    'name' => 'required|string|max:255',
    'is_active' => 'boolean',
    'role_id' => 'array|exists:roles,id'
];
```

### Role Management Integration

#### Spatie Permission Package
```php
// Role assignment
$user->assignRole($roleIds);

// Role synchronization
$user->syncRoles($roleIds);

// Permission checking
$canModify = auth()->user()->can('modify_user');
```

### Database Relationships

#### User Model Relationships
```php
// User → Roles (Many-to-Many)
public function roles()
{
    return $this->belongsToMany(Role::class);
}

// User → Permissions (Many-to-Many)
public function permissions()
{
    return $this->belongsToMany(Permission::class);
}

// User → UserLogs (One-to-Many)
public function logs()
{
    return $this->hasMany(UserLog::class, 'user_id', 'employee_id');
}
```

---

## 🎯 Advanced Features

### User Activity Analytics

#### Comprehensive Tracking
- **Login Attempts**: Success and failure tracking
- **IP Address Monitoring**: Security analysis
- **Authentication Methods**: Token vs normal login tracking
- **Peak Usage Analysis**: Time-based usage patterns
- **Security Alerts**: Multiple IP address detection

#### Statistical Analysis
```php
$stats = [
    'total_logins' => $totalLogins,
    'unique_users' => $uniqueUsers,
    'token_logins' => $tokenLogins,
    'unique_ips' => $uniqueIPs,
    'peak_hour' => $peakHour,
    'multiple_ip_users' => $multipleIPUsersCount,
];
```

### HRIMS Integration

#### Employee Data Synchronization
- **Automatic Import**: Bulk employee import from HRIMS
- **Data Mapping**: HRIMS fields to user attributes
- **Status Synchronization**: Employee status to user status
- **Password Migration**: Preserves existing passwords
- **Duplicate Handling**: Updates existing, creates new

#### Performance Optimization
```php
// Chunked processing for large datasets
$query->chunk(100, function ($employees) use (&$importedCount) {
    foreach ($employees as $employee) {
        // Process each employee
    }
});
```

### Permission Management

#### Granular Permission Control
- **Direct Permissions**: User-specific permissions
- **Role-Based Permissions**: Inherited from roles
- **Permission Groups**: Organized by functional areas
- **Dynamic Permission Checking**: Runtime permission validation

---

## 🚀 Performance Considerations

### DataTables Optimization

#### Server-Side Processing
- **Pagination**: Efficient large dataset handling
- **Search Optimization**: Database-level search
- **Relationship Loading**: Eager loading for roles
- **Permission Caching**: Cached permission checks

### Import Performance

#### Bulk Operations
- **Chunked Processing**: 100-record batches
- **Memory Management**: Prevents memory overflow
- **Database Optimization**: Bulk inserts where possible
- **Progress Tracking**: Import progress monitoring

### Query Optimization

#### Efficient Queries
```php
// Optimized user query with relationships
$query = User::with('roles')
    ->select(['id', 'name', 'email', 'status'])
    ->where('status', 1);
```

---

## 🔐 Security Considerations

### Input Validation

#### Comprehensive Validation
- **Email Uniqueness**: Prevents duplicate accounts
- **Password Strength**: Minimum 8 characters
- **Role Validation**: Ensures valid role assignments
- **Status Validation**: Boolean to integer mapping

### Access Control

#### Permission-Based Actions
```php
$canModify = auth()->user()->can('modify_user');
$canRemove = auth()->user()->can('remove_user');
$canManagePermissions = auth()->user()->can('manage_permissions');
```

### Activity Monitoring

#### Security Analytics
- **Login Tracking**: All authentication attempts
- **IP Monitoring**: Suspicious activity detection
- **Multiple IP Detection**: Security risk identification
- **Audit Trail**: Comprehensive activity logging

---

## 📈 Usage Examples

### Frontend User Management

```javascript
// User Management Interface
class UserManager {
    constructor() {
        this.dataTable = null;
        this.currentStatus = 'active';
        this.initializeDataTable();
    }

    initializeDataTable() {
        this.dataTable = $('#users-table').DataTable({
            processing: true,
            serverSide: true,
            ajax: {
                url: '/admin/users/datatable',
                type: 'POST',
                headers: {
                    'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
                },
                data: (d) => {
                    d.status = this.currentStatus;
                    return d;
                }
            },
            columns: [
                { data: 'id', name: 'id' },
                { data: 'name', name: 'name' },
                { data: 'email', name: 'email' },
                { 
                    data: 'roles', 
                    name: 'roles',
                    render: (data) => {
                        return data.map(role => `<span class="badge badge-primary">${role.name}</span>`).join(' ');
                    }
                },
                { 
                    data: 'status', 
                    name: 'status',
                    render: (data) => {
                        return data === 1 
                            ? '<span class="badge badge-success">Active</span>'
                            : '<span class="badge badge-danger">Inactive</span>';
                    }
                },
                {
                    data: 'actions',
                    name: 'actions',
                    orderable: false,
                    searchable: false,
                    render: (data, type, row) => {
                        let actions = '';
                        
                        if (row.can_modify) {
                            actions += `<button class="btn btn-sm btn-primary edit-user" data-id="${row.id}">Edit</button> `;
                        }
                        
                        if (row.can_manage_permissions) {
                            actions += `<a href="/admin/users/${row.id}/permissions" class="btn btn-sm btn-info">Permissions</a> `;
                        }
                        
                        if (row.can_remove) {
                            actions += `<button class="btn btn-sm btn-danger delete-user" data-id="${row.id}">Delete</button>`;
                        }
                        
                        return actions;
                    }
                }
            ],
            order: [[0, 'desc']],
            pageLength: 25
        });
    }

    async createUser(formData) {
        try {
            const response = await fetch('/admin/users', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
                },
                body: JSON.stringify(formData)
            });

            const result = await response.json();

            if (result.success) {
                this.showSuccess('User created successfully');
                this.dataTable.ajax.reload();
                this.resetForm();
            } else {
                this.showErrors(result.errors || result.message);
            }
        } catch (error) {
            console.error('Failed to create user:', error);
            this.showError('Failed to create user');
        }
    }

    async updateUser(id, formData) {
        try {
            const response = await fetch(`/admin/users/${id}`, {
                method: 'PUT',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
                },
                body: JSON.stringify(formData)
            });

            const result = await response.json();

            if (result.success) {
                this.showSuccess('User updated successfully');
                this.dataTable.ajax.reload();
                this.closeEditModal();
            } else {
                this.showErrors(result.errors || result.message);
            }
        } catch (error) {
            console.error('Failed to update user:', error);
            this.showError('Failed to update user');
        }
    }

    async deleteUser(id) {
        if (!confirm('Are you sure you want to delete this user?')) {
            return;
        }

        try {
            const response = await fetch(`/admin/users/${id}`, {
                method: 'DELETE',
                headers: {
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
                }
            });

            const result = await response.json();

            if (result.success) {
                this.showSuccess('User deleted successfully');
                this.dataTable.ajax.reload();
            } else {
                this.showError(result.message);
            }
        } catch (error) {
            console.error('Failed to delete user:', error);
            this.showError('Failed to delete user');
        }
    }

    async importEmployees() {
        if (!confirm('This will import all employees from HRIMS. Continue?')) {
            return;
        }

        try {
            this.showLoading('Importing employees...');

            const response = await fetch('/admin/users/import-employees', {
                method: 'POST',
                headers: {
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
                }
            });

            const result = await response.json();

            if (result.success) {
                this.showSuccess(`${result.data.imported_count} employees imported successfully`);
                this.dataTable.ajax.reload();
            } else {
                this.showError(result.message);
            }
        } catch (error) {
            console.error('Failed to import employees:', error);
            this.showError('Failed to import employees');
        } finally {
            this.hideLoading();
        }
    }

    changeStatus(status) {
        this.currentStatus = status;
        this.dataTable.ajax.reload();
        
        // Update active button
        document.querySelectorAll('.status-filter').forEach(btn => btn.classList.remove('active'));
        document.querySelector(`[data-status="${status}"]`).classList.add('active');
    }
}

// Initialize user manager
const userManager = new UserManager();

// Event handlers
document.getElementById('create-user-form').addEventListener('submit', (e) => {
    e.preventDefault();
    
    const formData = {
        name: document.getElementById('name').value,
        email: document.getElementById('email').value,
        password: document.getElementById('password').value,
        is_active: document.getElementById('is_active').checked,
        role_id: Array.from(document.querySelectorAll('input[name="role_id"]:checked')).map(cb => parseInt(cb.value))
    };
    
    userManager.createUser(formData);
});

// Status filter handlers
document.querySelectorAll('.status-filter').forEach(button => {
    button.addEventListener('click', (e) => {
        const status = e.target.dataset.status;
        userManager.changeStatus(status);
    });
});

// Import employees handler
document.getElementById('import-employees').addEventListener('click', () => {
    userManager.importEmployees();
});
```

### User Activity Analytics

```javascript
// User Activity Analytics
class UserActivityAnalytics {
    constructor() {
        this.chart = null;
        this.loadActivityData();
    }

    async loadActivityData(filters = {}) {
        try {
            const response = await fetch('/admin/users/activity-data', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
                },
                body: JSON.stringify(filters)
            });

            const result = await response.json();

            if (result.success) {
                this.renderStatistics(result.data.stats);
                this.renderActivityChart(result.data.activities);
                this.renderActivityTable(result.data.activities);
            }
        } catch (error) {
            console.error('Failed to load activity data:', error);
        }
    }

    renderStatistics(stats) {
        document.getElementById('total-logins').textContent = stats.total_logins;
        document.getElementById('unique-users').textContent = stats.unique_users;
        document.getElementById('token-logins').textContent = stats.token_logins;
        document.getElementById('unique-ips').textContent = stats.unique_ips;
        document.getElementById('peak-hour').textContent = `${stats.peak_hour}:00`;
        document.getElementById('multiple-ip-users').textContent = stats.multiple_ip_users;
    }

    renderActivityChart(activities) {
        // Group activities by hour
        const hourlyData = activities.reduce((acc, activity) => {
            const hour = new Date(activity.timestamp).getHours();
            acc[hour] = (acc[hour] || 0) + 1;
            return acc;
        }, {});

        const chartData = Array.from({length: 24}, (_, i) => ({
            hour: i,
            count: hourlyData[i] || 0
        }));

        // Render chart using Chart.js or similar
        this.renderChart(chartData);
    }

    renderActivityTable(activities) {
        const tbody = document.querySelector('#activity-table tbody');
        tbody.innerHTML = '';

        activities.forEach(activity => {
            const row = document.createElement('tr');
            row.innerHTML = `
                <td>${activity.user_name || 'Unknown'}</td>
                <td>${activity.user_email || 'N/A'}</td>
                <td>${activity.ip_address}</td>
                <td>${activity.login_method}</td>
                <td>${new Date(activity.timestamp).toLocaleString()}</td>
                <td>
                    <span class="badge badge-${activity.is_success ? 'success' : 'danger'}">
                        ${activity.is_success ? 'Success' : 'Failed'}
                    </span>
                </td>
            `;
            tbody.appendChild(row);
        });
    }
}

// Initialize analytics
const activityAnalytics = new UserActivityAnalytics();

// Filter handlers
document.getElementById('activity-filters').addEventListener('submit', (e) => {
    e.preventDefault();
    
    const filters = {
        date_from: document.getElementById('date_from').value,
        date_to: document.getElementById('date_to').value,
        login_method: document.getElementById('login_method').value,
        filter_type: document.getElementById('filter_type').value
    };
    
    activityAnalytics.loadActivityData(filters);
});
```

---

## 🔗 Integration Points

### HRIMS System Integration

#### Employee Data Synchronization
- **Database Connection**: Uses 'hrims' connection
- **Employee Import**: Bulk import functionality
- **Data Mapping**: HRIMS to user attribute mapping
- **Status Synchronization**: Employee status to user status

### Permission System Integration

#### Spatie Permission Package
- **Role Management**: Complete role assignment system
- **Permission Checking**: Runtime permission validation
- **Direct Permissions**: User-specific permissions
- **Permission Groups**: Organized permission structure

### Activity Monitoring Integration

#### UserLog Model
- **Login Tracking**: All authentication attempts
- **Activity Analytics**: Comprehensive usage statistics
- **Security Monitoring**: Suspicious activity detection
- **Audit Trail**: Complete user activity history

---

The Admin UsersController provides comprehensive user management functionality, serving as the central hub for user administration, security monitoring, and access control in the expense management system while maintaining high security standards and performance optimization.