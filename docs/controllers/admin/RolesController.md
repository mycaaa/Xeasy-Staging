# 🛡️ Admin RolesController Documentation

The Admin RolesController manages role-based access control (RBAC) in the Xeasy Laravel expense management system, providing comprehensive role management with permission assignments and status control.

## 📋 Overview

**Location**: `app/Http/Controllers/admin/RolesController.php`

**Purpose**: Handles complete role lifecycle management including creation, modification, permission assignment, and status management for the application's role-based access control system.

**Middleware**: Inherits from base Controller (typically includes auth and admin middleware)

**Key Features**:
- Complete CRUD operations for roles
- Permission assignment and synchronization
- Status-based filtering (Active/Inactive)
- Super Admin role protection
- Integration with Spatie Permission package
- JSON API responses for frontend integration

## 📊 Public Methods

### 1. Role Management Interface

#### `index(): View`

**Route**: `GET /admin/roles`
**Permission**: Admin access required

**Purpose**: Displays the role management interface with filtering capabilities.

**Query Parameters**:
| Parameter | Type | Required | Description |
|-----------|------|----------|-------------|
| status | string | No | Filter by status: 'active', 'inactive', 'all' (default: 'active') |

**Returns**: Blade view (`admin.roles`) with roles and status data

**Data Provided to View**:
- **roles**: Collection of roles (excluding Super Admin role ID 1)
- **status**: Current filter status

**Status Filtering Logic**:
- **Active**: `status = 1`
- **Inactive**: `status = 0`
- **All**: No status filtering

**Business Logic**:
1. **Query Parameter Processing**: Handles status filter with default fallback
2. **Super Admin Exclusion**: Excludes role ID 1 from management interface
3. **Status-Based Filtering**: Applies appropriate status filters
4. **Data Preparation**: Prepares data for view consumption

**Example Usage**:
```php
GET /admin/roles                    // Active roles
GET /admin/roles?status=inactive    // Inactive roles
GET /admin/roles?status=all         // All roles (except Super Admin)
```

---

### 2. Create Role

#### `store(StoreRoleRequest $request): JsonResponse`

**Route**: `POST /admin/roles`
**Permission**: Admin access required

**Purpose**: Creates a new role with permission assignments.

**Request Parameters** (via StoreRoleRequest):
| Parameter | Type | Required | Description |
|-----------|------|----------|-------------|
| role_name | string | Yes | Role name (unique, max: 255 chars) |
| is_active | boolean | No | Active status (default: false) |
| permissions | array | No | Array of permission IDs to assign |

**Request Example**:
```json
{
    "role_name": "Department Manager",
    "is_active": true,
    "permissions": [1, 2, 3, 5, 8, 12]
}
```

**Response Structure**:
```json
{
    "success": true,
    "message": "Role created successfully.",
    "data": {
        "id": 15,
        "name": "Department Manager",
        "guard_name": "web",
        "status": 1,
        "created_by": 1,
        "created_at": "2024-01-15T10:30:00.000000Z",
        "updated_at": "2024-01-15T10:30:00.000000Z"
    }
}
```

**Business Logic**:
1. **Input Validation**: Uses StoreRoleRequest for comprehensive validation
2. **Guard Assignment**: Automatically sets guard_name to 'web'
3. **User Attribution**: Sets created_by to current authenticated user
4. **Permission Sync**: Synchronizes assigned permissions
5. **Status Mapping**: Maps boolean is_active to integer status

**Permission Assignment**:
```php
// Sync permissions if provided
if (!empty($request->permissions)) {
    $roles->permissions()->sync($request->permissions);
}
```

---

### 3. Retrieve Role for Editing

#### `edit(string $id): JsonResponse`

**Route**: `GET /admin/roles/{id}/edit`
**Permission**: Admin access required

**Purpose**: Retrieves role data with assigned permissions for editing.

**Parameters**:
| Parameter | Type | Required | Description |
|-----------|------|----------|-------------|
| id | string | Yes | Role ID (route parameter) |

**Response Structure**:
```json
{
    "success": true,
    "message": "Role retrieved successfully.",
    "data": {
        "id": 15,
        "name": "Department Manager",
        "status": 1,
        "permissions": [1, 2, 3, 5, 8, 12]
    }
}
```

**Features**:
- **Permission Loading**: Eager loads assigned permissions
- **Simplified Response**: Returns only essential data for editing
- **Permission IDs**: Returns array of permission IDs for form population

**Error Response**:
```json
{
    "message": "No query results for model [App\\Models\\Roles] 999"
}
```

---

### 4. Update Role

#### `update(UpdateRoleRequest $request, string $id): JsonResponse`

**Route**: `PUT /admin/roles/{id}`
**Permission**: Admin access required

**Purpose**: Updates role information and permission assignments.

**Parameters**:
| Parameter | Type | Required | Description |
|-----------|------|----------|-------------|
| id | string | Yes | Role ID (route parameter) |
| role_name | string | Yes | Updated role name |
| is_active | boolean | No | Updated active status |
| permissions | array | No | Updated permission assignments |

**Request Example**:
```json
{
    "role_name": "Senior Department Manager",
    "is_active": false,
    "permissions": [1, 2, 3, 5, 8, 12, 15, 18]
}
```

**Response Structure**:
```json
{
    "success": true,
    "message": "Role updated successfully.",
    "data": {
        "id": 15,
        "name": "Senior Department Manager",
        "guard_name": "web",
        "status": 0,
        "updated_by": 1,
        "updated_at": "2024-01-15T15:45:00.000000Z"
    }
}
```

**Business Logic**:
1. **Role Validation**: Ensures role exists
2. **Input Validation**: Uses UpdateRoleRequest for validation
3. **Selective Update**: Updates only provided fields
4. **Permission Synchronization**: Syncs permissions (handles empty arrays)
5. **User Attribution**: Updates updated_by field
6. **Timestamp Management**: Automatically updates timestamp

**Permission Synchronization**:
```php
// Sync permissions even if array is empty
$role->permissions()->sync($request->permissions ?? []);
```

---

### 5. Delete Role

#### `destroy(string $id): JsonResponse`

**Route**: `DELETE /admin/roles/{id}`
**Permission**: Admin access required

**Purpose**: Deletes a role and its permission assignments.

**Parameters**:
| Parameter | Type | Required | Description |
|-----------|------|----------|-------------|
| id | string | Yes | Role ID (route parameter) |

**Response Structure**:
```json
{
    "success": true,
    "message": "Role deleted successfully.",
    "data": {
        "id": 15,
        "name": "Department Manager",
        "guard_name": "web",
        "status": 1
    }
}
```

**Business Logic**:
1. **Role Validation**: Ensures role exists
2. **Dependency Check**: Should check for users with this role (not implemented)
3. **Permission Cleanup**: Automatically removes permission assignments
4. **Soft/Hard Delete**: Performs deletion based on model configuration

**Considerations**:
- **User Impact**: Consider checking for users assigned to this role
- **Audit Trail**: Deletion should be logged for compliance
- **Soft Delete**: Consider implementing soft delete for data integrity

---

### 6. Active Roles API

#### `getRoles(): JsonResponse`

**Route**: `GET /api/admin/roles`
**Permission**: Admin access required

**Purpose**: Retrieves all active roles for dropdown population and API integration.

**Parameters**: None

**Response Structure**:
```json
{
    "success": true,
    "message": "Roles retrieved successfully.",
    "data": [
        {
            "id": 2,
            "name": "Manager",
            "guard_name": "web",
            "status": 1,
            "created_by": 1,
            "created_at": "2024-01-01T00:00:00.000000Z",
            "updated_at": "2024-01-01T00:00:00.000000Z"
        },
        {
            "id": 3,
            "name": "Employee",
            "guard_name": "web",
            "status": 1,
            "created_by": 1,
            "created_at": "2024-01-01T00:00:00.000000Z",
            "updated_at": "2024-01-01T00:00:00.000000Z"
        }
    ]
}
```

**Features**:
- **Active Only**: Returns only active roles (status = 1)
- **Super Admin Exclusion**: Excludes Super Admin role (ID 1)
- **Complete Data**: Returns full role information
- **API Integration**: Designed for frontend and external API consumption

**Use Cases**:
- User assignment dropdowns
- Role selection interfaces
- API integrations
- Mobile app data synchronization

---

## 🔧 Implementation Details

### Request Validation Classes

#### StoreRoleRequest
```php
// Role creation validation
$rules = [
    'role_name' => 'required|string|max:255|unique:roles,name',
    'is_active' => 'boolean',
    'permissions' => 'array',
    'permissions.*' => 'integer|exists:permissions,id'
];
```

#### UpdateRoleRequest
```php
// Role update validation
$rules = [
    'role_name' => 'required|string|max:255|unique:roles,name,' . $this->route('role'),
    'is_active' => 'boolean',
    'permissions' => 'array',
    'permissions.*' => 'integer|exists:permissions,id'
];
```

### Model Relationships

#### Role Model Integration
```php
// In Roles model
public function permissions()
{
    return $this->belongsToMany(Permission::class, 'role_has_permissions');
}

public function users()
{
    return $this->belongsToMany(User::class, 'model_has_roles', 'role_id', 'model_id');
}
```

### Spatie Permission Integration

#### Permission Synchronization
```php
// Sync permissions with role
$role->permissions()->sync($permissionIds);

// This handles:
// - Adding new permissions
// - Removing unassigned permissions
// - Maintaining existing assignments
// - Handling empty arrays (removes all)
```

---

## 🎯 Business Logic Patterns

### Super Admin Protection

#### System Role Protection
```php
// Exclude Super Admin role from management
$roles = $rolesQuery->where('id', '!=', 1)->get();
```

**Protection Features**:
- **Interface Exclusion**: Super Admin role not shown in management interface
- **API Exclusion**: Super Admin role not returned in API calls
- **Modification Prevention**: Prevents accidental modification of system role

### Status Management

#### Active/Inactive Logic
```php
// Status filtering
if ($status === 'active') {
    $rolesQuery->where('status', 1);
} elseif ($status === 'inactive') {
    $rolesQuery->where('status', 0);
}
```

**Status Features**:
- **Binary Status**: 1 for active, 0 for inactive
- **Filter Support**: Interface supports status-based filtering
- **API Integration**: Active roles API for dropdown population

### Permission Management

#### Comprehensive Permission Sync
```php
// Always sync permissions, even empty arrays
$role->permissions()->sync($request->permissions ?? []);
```

**Sync Features**:
- **Add New**: Assigns new permissions
- **Remove Old**: Removes unassigned permissions
- **Handle Empty**: Removes all permissions if empty array
- **Maintain Existing**: Keeps unchanged assignments

---

## 🚀 Performance Considerations

### Query Optimization

#### Efficient Role Loading
```php
// Eager load permissions for editing
$role = Roles::with('permissions')->findOrFail($id);
```

#### Selective Queries
```php
// Only load active roles for API
$roles = Roles::where('status', 1)->where('id', '!=', 1)->get();
```

### Permission Synchronization

#### Bulk Operations
- **Sync Method**: Uses Laravel's efficient sync method
- **Batch Processing**: Handles multiple permission assignments efficiently
- **Minimal Queries**: Optimizes database operations

### Caching Opportunities

Consider caching for:
- **Active Roles**: Cache for 1 hour (rarely changes)
- **Role Permissions**: Cache for 30 minutes (frequently accessed)
- **Role Hierarchy**: Cache for 2 hours (very stable)

---

## 🔐 Security Considerations

### Input Validation

#### Comprehensive Validation
```php
$rules = [
    'role_name' => 'required|string|max:255|unique:roles,name',
    'permissions' => 'array',
    'permissions.*' => 'integer|exists:permissions,id'
];
```

**Security Features**:
- **Unique Names**: Prevents duplicate role names
- **Permission Validation**: Ensures valid permission IDs
- **Type Validation**: Ensures correct data types
- **Length Limits**: Prevents buffer overflow attacks

### Access Control

#### Admin-Only Operations
- **Authentication Required**: All operations require authentication
- **Admin Permissions**: Requires admin-level permissions
- **Super Admin Protection**: Protects system-critical roles

### Data Integrity

#### Referential Integrity
- **Permission Validation**: Ensures permissions exist before assignment
- **User Impact**: Should validate user assignments before deletion
- **Audit Trail**: Tracks role modifications

---

## 📈 Usage Examples

### Frontend Role Management

```javascript
// Role Management Interface
class RoleManager {
    constructor() {
        this.roles = [];
        this.permissions = [];
        this.currentStatus = 'active';
        this.loadRoles();
        this.loadPermissions();
    }

    async loadRoles(status = 'active') {
        try {
            const response = await fetch(`/admin/roles?status=${status}`);
            const html = await response.text();
            
            // Parse HTML or use separate API endpoint
            this.currentStatus = status;
            this.renderRoles();
        } catch (error) {
            console.error('Failed to load roles:', error);
        }
    }

    async loadPermissions() {
        try {
            const response = await fetch('/api/admin/permissions');
            const result = await response.json();
            
            if (result.success) {
                this.permissions = result.data;
                this.renderPermissionTree();
            }
        } catch (error) {
            console.error('Failed to load permissions:', error);
        }
    }

    async createRole(formData) {
        try {
            const response = await fetch('/admin/roles', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
                },
                body: JSON.stringify(formData)
            });

            const result = await response.json();

            if (result.success) {
                this.showSuccess('Role created successfully');
                this.loadRoles(this.currentStatus);
                this.resetForm();
            } else {
                this.showErrors(result.errors || result.message);
            }
        } catch (error) {
            console.error('Failed to create role:', error);
            this.showError('Failed to create role');
        }
    }

    async updateRole(id, formData) {
        try {
            const response = await fetch(`/admin/roles/${id}`, {
                method: 'PUT',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
                },
                body: JSON.stringify(formData)
            });

            const result = await response.json();

            if (result.success) {
                this.showSuccess('Role updated successfully');
                this.loadRoles(this.currentStatus);
                this.closeEditModal();
            } else {
                this.showErrors(result.errors || result.message);
            }
        } catch (error) {
            console.error('Failed to update role:', error);
            this.showError('Failed to update role');
        }
    }

    async deleteRole(id) {
        if (!confirm('Are you sure you want to delete this role? This action cannot be undone.')) {
            return;
        }

        try {
            const response = await fetch(`/admin/roles/${id}`, {
                method: 'DELETE',
                headers: {
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
                }
            });

            const result = await response.json();

            if (result.success) {
                this.showSuccess('Role deleted successfully');
                this.loadRoles(this.currentStatus);
            } else {
                this.showError(result.message);
            }
        } catch (error) {
            console.error('Failed to delete role:', error);
            this.showError('Failed to delete role');
        }
    }

    async editRole(id) {
        try {
            const response = await fetch(`/admin/roles/${id}/edit`);
            const result = await response.json();

            if (result.success) {
                this.populateEditForm(result.data);
                this.showEditModal();
            } else {
                this.showError('Failed to load role data');
            }
        } catch (error) {
            console.error('Failed to load role:', error);
            this.showError('Failed to load role');
        }
    }

    populateEditForm(role) {
        document.getElementById('edit-role-name').value = role.name;
        document.getElementById('edit-is-active').checked = role.status === 1;
        
        // Clear all permission checkboxes
        document.querySelectorAll('input[name="edit_permissions[]"]').forEach(cb => {
            cb.checked = false;
        });
        
        // Check assigned permissions
        role.permissions.forEach(permissionId => {
            const checkbox = document.querySelector(`input[name="edit_permissions[]"][value="${permissionId}"]`);
            if (checkbox) {
                checkbox.checked = true;
            }
        });
        
        document.getElementById('edit-form').dataset.roleId = role.id;
    }

    renderPermissionTree() {
        const container = document.getElementById('permissions-tree');
        container.innerHTML = '';

        // Group permissions by category
        const groupedPermissions = this.permissions.reduce((acc, permission) => {
            const group = permission.group || 'Other';
            if (!acc[group]) {
                acc[group] = [];
            }
            acc[group].push(permission);
            return acc;
        }, {});

        // Render permission groups
        Object.keys(groupedPermissions).forEach(group => {
            const groupDiv = document.createElement('div');
            groupDiv.className = 'permission-group';
            
            const groupHeader = document.createElement('h4');
            groupHeader.textContent = group;
            groupDiv.appendChild(groupHeader);

            const permissionList = document.createElement('div');
            permissionList.className = 'permission-list';

            groupedPermissions[group].forEach(permission => {
                const permissionDiv = document.createElement('div');
                permissionDiv.className = 'permission-item';
                
                permissionDiv.innerHTML = `
                    <label>
                        <input type="checkbox" name="permissions[]" value="${permission.id}">
                        ${permission.name}
                    </label>
                `;
                
                permissionList.appendChild(permissionDiv);
            });

            groupDiv.appendChild(permissionList);
            container.appendChild(groupDiv);
        });
    }

    changeStatus(status) {
        this.currentStatus = status;
        this.loadRoles(status);
        
        // Update active button
        document.querySelectorAll('.status-filter').forEach(btn => btn.classList.remove('active'));
        document.querySelector(`[data-status="${status}"]`).classList.add('active');
    }
}

// Initialize role manager
const roleManager = new RoleManager();

// Form submission handlers
document.getElementById('create-role-form').addEventListener('submit', (e) => {
    e.preventDefault();
    
    const formData = {
        role_name: document.getElementById('role-name').value,
        is_active: document.getElementById('is-active').checked,
        permissions: Array.from(document.querySelectorAll('input[name="permissions[]"]:checked'))
            .map(cb => parseInt(cb.value))
    };
    
    roleManager.createRole(formData);
});

document.getElementById('edit-role-form').addEventListener('submit', (e) => {
    e.preventDefault();
    
    const roleId = e.target.dataset.roleId;
    const formData = {
        role_name: document.getElementById('edit-role-name').value,
        is_active: document.getElementById('edit-is-active').checked,
        permissions: Array.from(document.querySelectorAll('input[name="edit_permissions[]"]:checked'))
            .map(cb => parseInt(cb.value))
    };
    
    roleManager.updateRole(roleId, formData);
});

// Status filter handlers
document.querySelectorAll('.status-filter').forEach(button => {
    button.addEventListener('click', (e) => {
        const status = e.target.dataset.status;
        roleManager.changeStatus(status);
    });
});

// Permission group toggle handlers
document.addEventListener('click', (e) => {
    if (e.target.classList.contains('group-toggle')) {
        const group = e.target.dataset.group;
        const checkboxes = document.querySelectorAll(`input[data-group="${group}"]`);
        const allChecked = Array.from(checkboxes).every(cb => cb.checked);
        
        checkboxes.forEach(cb => {
            cb.checked = !allChecked;
        });
    }
});
```

### Role-Based Access Control Integration

```php
// Middleware for role-based access
class RoleMiddleware
{
    public function handle($request, Closure $next, ...$roles)
    {
        if (!auth()->check()) {
            return redirect()->route('login');
        }

        $user = auth()->user();
        
        // Check if user has any of the required roles
        if (!$user->hasAnyRole($roles)) {
            abort(403, 'Insufficient permissions');
        }

        return $next($request);
    }
}

// Usage in routes
Route::middleware(['auth', 'role:Manager,Admin'])->group(function () {
    Route::get('/admin/reports', [ReportController::class, 'index']);
});

// Usage in controllers
class SomeController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth');
        $this->middleware('role:Admin')->only(['create', 'store', 'destroy']);
        $this->middleware('role:Manager,Admin')->only(['edit', 'update']);
    }
}

// Usage in Blade templates
@role('Admin')
    <a href="/admin/users" class="btn btn-primary">Manage Users</a>
@endrole

@hasanyrole('Manager|Admin')
    <div class="admin-panel">
        <!-- Admin content -->
    </div>
@endhasanyrole
```

### API Integration Example

```php
// External API integration
class RoleApiClient
{
    private $baseUrl;
    private $token;

    public function __construct($baseUrl, $token)
    {
        $this->baseUrl = $baseUrl;
        $this->token = $token;
    }

    public function getAllRoles()
    {
        $response = Http::withToken($this->token)
            ->get("{$this->baseUrl}/api/admin/roles");

        return $response->json();
    }

    public function createRole($data)
    {
        $response = Http::withToken($this->token)
            ->post("{$this->baseUrl}/admin/roles", $data);

        return $response->json();
    }

    public function updateRole($id, $data)
    {
        $response = Http::withToken($this->token)
            ->put("{$this->baseUrl}/admin/roles/{$id}", $data);

        return $response->json();
    }

    public function deleteRole($id)
    {
        $response = Http::withToken($this->token)
            ->delete("{$this->baseUrl}/admin/roles/{$id}");

        return $response->json();
    }

    public function getRolePermissions($id)
    {
        $response = Http::withToken($this->token)
            ->get("{$this->baseUrl}/admin/roles/{$id}/edit");

        return $response->json();
    }
}

// Usage
$client = new RoleApiClient('https://your-domain.com', $apiToken);
$roles = $client->getAllRoles();
```

---

## 🔗 Integration Points

### Spatie Permission Package Integration

#### Role Management
- **Role Creation**: Automatic guard assignment
- **Permission Sync**: Efficient permission synchronization
- **User Assignment**: Role assignment to users
- **Permission Checking**: Runtime permission validation

### User Management Integration

#### User-Role Relationships
- **Role Assignment**: Users can be assigned multiple roles
- **Permission Inheritance**: Users inherit permissions from roles
- **Dynamic Checking**: Real-time permission validation
- **Role Hierarchy**: Support for role-based hierarchies

### Frontend Integration

#### Admin Interface
- **Role Management**: Complete CRUD interface
- **Permission Trees**: Hierarchical permission display
- **Status Filtering**: Active/inactive role filtering
- **Real-time Updates**: Dynamic content updates

---

The Admin RolesController provides comprehensive role management functionality, serving as the foundation for the application's role-based access control system while maintaining security, performance, and usability standards essential for enterprise applications.