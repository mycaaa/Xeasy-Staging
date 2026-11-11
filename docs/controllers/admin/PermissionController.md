# 🔐 Admin PermissionController Documentation

The Admin PermissionController manages the permission system in the Xeasy Laravel expense management system, providing comprehensive permission management, role assignments, user-specific permissions, and claim type access control.

## 📋 Overview

**Location**: `app/Http/Controllers/admin/PermissionController.php`

**Purpose**: Handles complete permission lifecycle management including CRUD operations, role-permission assignments, user-specific permissions, claim type assignments, and permission group organization.

**Middleware**: Inherits from base Controller (typically includes auth and admin middleware)

**Key Features**:
- Complete CRUD operations for permissions
- Role-permission assignment and management
- User-specific permission assignments
- Claim type access control
- Permission group organization
- Cache management for performance
- Integration with Spatie Permission package
- Menu system integration

## 📊 Public Methods

### 1. Permission Management Interface

#### `index(Request $request): View`

**Route**: `GET /admin/permissions`
**Permission**: Admin access required

**Purpose**: Displays the main permission management interface with filtering capabilities.

**Query Parameters**:
| Parameter | Type | Required | Description |
|-----------|------|----------|-------------|
| group_id | integer | No | Filter permissions by group ID |

**Returns**: Blade view (`admin.permissions`) with comprehensive permission data

**Data Provided to View**:
- **permissions**: Permissions with group information
- **roles**: Active roles for assignment
- **group**: All permission groups

**Business Logic**:
1. **Group Filtering**: Filters permissions by group if specified
2. **Join Operations**: Joins permissions with permission groups
3. **Role Loading**: Loads active roles for assignment interface
4. **Ordering**: Orders permissions by ID descending

**Example Usage**:
```php
GET /admin/permissions                    // All permissions
GET /admin/permissions?group_id=5         // Permissions in specific group
```

---

#### `permissionOld(Request $request): View`

**Route**: `GET /admin/permissions-old`
**Permission**: Admin access required

**Purpose**: Displays legacy permission management interface.

**Returns**: Blade view (`admin.permissions_old`) with permission data

**Features**:
- **Legacy Interface**: Maintains backward compatibility
- **Role Filtering**: Excludes Super Admin role (ID 1)
- **Group Support**: Includes permission group filtering

---

### 2. Create Permission

#### `store(StorePermissionRequest $request): JsonResponse`

**Route**: `POST /admin/permissions`
**Permission**: Admin access required

**Purpose**: Creates a new permission with automatic key generation and cache invalidation.

**Request Parameters** (via StorePermissionRequest):
| Parameter | Type | Required | Description |
|-----------|------|----------|-------------|
| permission_name | string | Yes | Human-readable permission name |
| group_id | integer | Yes | Permission group ID |
| is_active | boolean | No | Active status (default: false) |

**Request Example**:
```json
{
    "permission_name": "Manage Employee Reports",
    "group_id": 3,
    "is_active": true
}
```

**Response Structure**:
```json
{
    "success": true,
    "message": "Permission created successfully.",
    "data": {
        "id": 25,
        "name": "manage_employee_reports",
        "permission_key": "Manage Employee Reports",
        "permission_group_id": 3,
        "status": 1,
        "guard_name": "web",
        "created_by": 1,
        "created_at": "2024-01-15T10:30:00.000000Z",
        "updated_at": "2024-01-15T10:30:00.000000Z"
    }
}
```

**Business Logic**:
1. **Key Generation**: Converts permission name to slug format
2. **Guard Assignment**: Automatically sets guard_name to 'web'
3. **User Attribution**: Sets created_by to current user
4. **Cache Invalidation**: Clears Spatie permission cache
5. **Status Mapping**: Maps boolean to integer status

**Key Generation Logic**:
```php
$permissionKey = Str::slug($request->permission_name, '_');
// "Manage Employee Reports" → "manage_employee_reports"
```

---

### 3. Retrieve Permission for Editing

#### `edit(string $id): JsonResponse`

**Route**: `GET /admin/permissions/{id}/edit`
**Permission**: Admin access required

**Purpose**: Retrieves permission data for editing.

**Parameters**:
| Parameter | Type | Required | Description |
|-----------|------|----------|-------------|
| id | string | Yes | Permission ID (route parameter) |

**Response Structure**:
```json
{
    "success": true,
    "message": "Permission fetched successfully.",
    "data": {
        "id": 25,
        "name": "manage_employee_reports",
        "permission_key": "Manage Employee Reports",
        "permission_group_id": 3,
        "status": 1,
        "guard_name": "web",
        "created_by": 1,
        "updated_by": null,
        "created_at": "2024-01-15T10:30:00.000000Z",
        "updated_at": "2024-01-15T10:30:00.000000Z"
    }
}
```

---

### 4. Update Permission

#### `update(UpdatePermissionRequest $request, string $id): JsonResponse`

**Route**: `PUT /admin/permissions/{id}`
**Permission**: Admin access required

**Purpose**: Updates permission information with menu system integration and cache invalidation.

**Parameters**:
| Parameter | Type | Required | Description |
|-----------|------|----------|-------------|
| id | string | Yes | Permission ID (route parameter) |
| permission_name | string | Yes | Updated permission name |
| group_id | integer | Yes | Updated group ID |
| is_active | boolean | No | Updated active status |

**Request Example**:
```json
{
    "permission_name": "Advanced Employee Reports",
    "group_id": 3,
    "is_active": false
}
```

**Response Structure**:
```json
{
    "success": true,
    "message": "Permission updated successfully.",
    "data": {
        "id": 25,
        "name": "advanced_employee_reports",
        "permission_key": "Advanced Employee Reports",
        "permission_group_id": 3,
        "status": 0,
        "updated_by": 1,
        "updated_at": "2024-01-15T15:45:00.000000Z"
    }
}
```

**Business Logic**:
1. **Key Regeneration**: Updates permission key based on new name
2. **Menu Integration**: Updates related menu items
3. **User Attribution**: Sets updated_by field
4. **Cache Invalidation**: Clears Spatie permission cache
5. **Timestamp Management**: Updates timestamp

**Menu System Integration**:
```php
// Update related menu items
Menu::where('permission_id', $id)->update([
    'permission_name' => $permissionKey,
    'updated_by' => Auth::id(),
    'updated_at' => now(),
]);
```

---

### 5. Delete Permission

#### `destroy(string $id): JsonResponse`

**Route**: `DELETE /admin/permissions/{id}`
**Permission**: Admin access required

**Purpose**: Deletes a permission and invalidates cache.

**Parameters**:
| Parameter | Type | Required | Description |
|-----------|------|----------|-------------|
| id | string | Yes | Permission ID (route parameter) |

**Response Structure**:
```json
{
    "success": true,
    "message": "Permission deleted successfully.",
    "data": null
}
```

**Business Logic**:
1. **Permission Validation**: Ensures permission exists
2. **Dependency Check**: Should check for role/user assignments
3. **Cache Invalidation**: Clears Spatie permission cache
4. **Cleanup**: Removes permission from system

---

### 6. Role-Permission Assignment

#### `assignPermissions(Request $request): JsonResponse`

**Route**: `POST /admin/permissions/assign`
**Permission**: Admin access required

**Purpose**: Assigns or revokes permissions for roles with guard validation.

**Request Parameters**:
| Parameter | Type | Required | Description |
|-----------|------|----------|-------------|
| role_id | integer | Yes | Role ID (must exist) |
| permission_id | integer | Yes | Permission ID (must exist) |
| isChecked | boolean | Yes | Assign (true) or revoke (false) |

**Request Example**:
```json
{
    "role_id": 3,
    "permission_id": 25,
    "isChecked": true
}
```

**Response Structure**:
```json
{
    "success": true,
    "message": "Permission updated successfully.",
    "data": []
}
```

**Business Logic**:
1. **Validation**: Validates role and permission existence
2. **Guard Matching**: Ensures role and permission have same guard
3. **Assignment Logic**: Assigns or revokes based on isChecked
4. **Cache Invalidation**: Clears Spatie permission cache

**Guard Validation**:
```php
if ($role->guard_name !== $permission->guard_name) {
    return response()->json([
        'success' => false,
        'message' => 'Guard mismatch between role and permission.',
    ], 400);
}
```

**Assignment/Revocation Logic**:
```php
if ($request->isChecked) {
    $role->givePermissionTo($permission->name);
} else {
    $role->revokePermissionTo($permission->name);
}
```

---

### 7. User Permission Management

#### `getPermissions(string $id): JsonResponse`

**Route**: `GET /admin/users/{id}/permissions`
**Permission**: Admin access required

**Purpose**: Retrieves user's direct permissions and assigned claim types.

**Parameters**:
| Parameter | Type | Required | Description |
|-----------|------|----------|-------------|
| id | string | Yes | User ID (route parameter) |

**Response Structure**:
```json
{
    "success": true,
    "message": "Permissions fetched successfully.",
    "data": {
        "permissions": {
            "User Management": [
                {
                    "id": 1,
                    "name": "view_users",
                    "group": "User Management"
                },
                {
                    "id": 2,
                    "name": "create_users",
                    "group": "User Management"
                }
            ],
            "Reports": [
                {
                    "id": 10,
                    "name": "view_reports",
                    "group": "Reports"
                }
            ]
        },
        "user": {
            "id": 15,
            "name": "John Doe",
            "email": "john.doe@company.com"
        },
        "assigned_claim_types": [1, 3, 7]
    }
}
```

**Features**:
- **Direct Permissions**: Only user's direct permissions (not inherited from roles)
- **Group Organization**: Permissions grouped by permission group
- **User Information**: Basic user details
- **Claim Type Access**: Assigned claim type IDs

---

#### `assignPermission(Request $request, string $id): JsonResponse`

**Route**: `POST /admin/users/{id}/permissions/assign`
**Permission**: Admin access required

**Purpose**: Assigns a direct permission to a user.

**Parameters**:
| Parameter | Type | Required | Description |
|-----------|------|----------|-------------|
| id | string | Yes | User ID (route parameter) |
| permission_id | integer | Yes | Permission ID to assign |

**Request Example**:
```json
{
    "permission_id": 25
}
```

**Response Structure**:
```json
{
    "success": true,
    "message": "Permission assigned successfully.",
    "data": null
}
```

---

#### `revokePermission(Request $request, string $id): JsonResponse`

**Route**: `POST /admin/users/{id}/permissions/revoke`
**Permission**: Admin access required

**Purpose**: Revokes a direct permission from a user.

**Parameters**:
| Parameter | Type | Required | Description |
|-----------|------|----------|-------------|
| id | string | Yes | User ID (route parameter) |
| permission_id | integer | Yes | Permission ID to revoke |

**Request Example**:
```json
{
    "permission_id": 25
}
```

**Response Structure**:
```json
{
    "success": true,
    "message": "Permission revoked successfully.",
    "data": null
}
```

---

### 8. Claim Type Access Control

#### `assignClaimType(Request $request, string $id): JsonResponse`

**Route**: `POST /admin/users/{id}/claim-types/assign`
**Permission**: Admin access required

**Purpose**: Assigns claim type access to a user.

**Parameters**:
| Parameter | Type | Required | Description |
|-----------|------|----------|-------------|
| id | string | Yes | User ID (route parameter) |
| claim_type_id | integer | Yes | Claim type ID to assign |

**Request Example**:
```json
{
    "claim_type_id": 7
}
```

**Response Structure**:
```json
{
    "success": true,
    "message": "Claim type assigned successfully.",
    "data": null
}
```

**Business Logic**:
1. **Duplicate Prevention**: Uses firstOrCreate to prevent duplicates
2. **Access Control**: Controls which claim types user can access
3. **Granular Control**: Per-user claim type access management

---

#### `revokeClaimType(Request $request, string $id): JsonResponse`

**Route**: `POST /admin/users/{id}/claim-types/revoke`
**Permission**: Admin access required

**Purpose**: Revokes claim type access from a user.

**Parameters**:
| Parameter | Type | Required | Description |
|-----------|------|----------|-------------|
| id | string | Yes | User ID (route parameter) |
| claim_type_id | integer | Yes | Claim type ID to revoke |

**Request Example**:
```json
{
    "claim_type_id": 7
}
```

**Response Structure**:
```json
{
    "success": true,
    "message": "Claim type revoked successfully.",
    "data": null
}
```

---

### 9. All Permissions API

#### `getAllPermissions(): JsonResponse`

**Route**: `GET /api/admin/permissions/all`
**Permission**: Admin access required

**Purpose**: Retrieves all permissions organized by category and group for comprehensive permission management interfaces.

**Parameters**: None

**Response Structure**:
```json
{
    "success": true,
    "message": "All permissions fetched successfully.",
    "data": {
        "System Administration": {
            "User Management": [
                {
                    "id": 1,
                    "name": "view_users",
                    "permission_key": "View Users"
                },
                {
                    "id": 2,
                    "name": "create_users",
                    "permission_key": "Create Users"
                }
            ],
            "Role Management": [
                {
                    "id": 10,
                    "name": "manage_roles",
                    "permission_key": "Manage Roles"
                }
            ]
        },
        "Expense Management": {
            "Claims": [
                {
                    "id": 20,
                    "name": "view_claims",
                    "permission_key": "View Claims"
                }
            ],
            "Reports": [
                {
                    "id": 30,
                    "name": "generate_reports",
                    "permission_key": "Generate Reports"
                }
            ]
        }
    }
}
```

**Complex Data Structure**:
```php
// Three-level hierarchy: Category → Group → Permissions
$permissions = Permission::with('group')
    ->get()
    ->groupBy('category')           // Level 1: Category
    ->map(function ($categoryPermissions) {
        return $categoryPermissions
            ->groupBy('group')      // Level 2: Group
            ->map(function ($groupPermissions) {
                return $groupPermissions
                    ->map(function ($permission) {  // Level 3: Permissions
                        return [
                            'id' => $permission['id'],
                            'name' => $permission['name'],
                            'permission_key' => $permission['permission_key'],
                        ];
                    });
            });
    });
```

**Use Cases**:
- **Permission Trees**: Hierarchical permission display
- **Role Assignment**: Complete permission selection
- **User Management**: Direct permission assignment
- **API Integration**: External system integration

---

## 🔧 Implementation Details

### Request Validation Classes

#### StorePermissionRequest
```php
// Permission creation validation
$rules = [
    'permission_name' => 'required|string|max:255|unique:permissions,permission_key',
    'group_id' => 'required|integer|exists:permission_groups,id',
    'is_active' => 'boolean'
];
```

#### UpdatePermissionRequest
```php
// Permission update validation
$rules = [
    'permission_name' => 'required|string|max:255|unique:permissions,permission_key,' . $this->route('permission'),
    'group_id' => 'required|integer|exists:permission_groups,id',
    'is_active' => 'boolean'
];
```

### Cache Management

#### Spatie Permission Cache
```php
// Clear cache after permission changes
Cache::forget('spatie.permission.cache');
```

**Cache Invalidation Points**:
- Permission creation
- Permission updates
- Permission deletion
- Role-permission assignments
- User-permission assignments

### Model Relationships

#### Permission Model Integration
```php
// In Permission model
public function group()
{
    return $this->belongsTo(PermissionGroup::class, 'permission_group_id');
}

public function roles()
{
    return $this->belongsToMany(Role::class, 'role_has_permissions');
}

public function users()
{
    return $this->belongsToMany(User::class, 'model_has_permissions', 'permission_id', 'model_id');
}
```

---

## 🎯 Advanced Features

### Permission Key Generation

#### Automatic Slug Generation
```php
// Convert human-readable name to system key
$permissionKey = Str::slug($request->permission_name, '_');

// Examples:
// "Manage Employee Reports" → "manage_employee_reports"
// "View Financial Data" → "view_financial_data"
// "Create New Claims" → "create_new_claims"
```

### Menu System Integration

#### Automatic Menu Updates
```php
// Update menu items when permission changes
Menu::where('permission_id', $id)->update([
    'permission_name' => $permissionKey,
    'updated_by' => Auth::id(),
    'updated_at' => now(),
]);
```

**Integration Features**:
- **Menu Synchronization**: Keeps menu permissions in sync
- **Navigation Control**: Controls menu item visibility
- **User Experience**: Seamless permission-based navigation

### Hierarchical Permission Organization

#### Three-Level Structure
1. **Category**: High-level grouping (e.g., "System Administration")
2. **Group**: Functional grouping (e.g., "User Management")
3. **Permission**: Specific permission (e.g., "view_users")

### Claim Type Access Control

#### Granular Access Management
```php
// User-specific claim type access
UserClaimType::firstOrCreate([
    'user_id' => $userId,
    'claim_type_id' => $claimTypeId,
]);
```

**Features**:
- **Per-User Control**: Individual claim type access
- **Flexible Assignment**: Dynamic claim type permissions
- **Business Logic**: Controls expense claim creation access

---

## 🚀 Performance Considerations

### Cache Management

#### Efficient Cache Strategy
- **Spatie Cache**: Leverages Spatie Permission package caching
- **Selective Invalidation**: Only clears cache when necessary
- **Performance Impact**: Minimizes database queries for permission checks

### Query Optimization

#### Efficient Permission Loading
```php
// Eager load relationships
$permissions = Permission::with('group')->get();

// Join for filtered queries
$query = Permission::join('permission_groups', 'permissions.permission_group_id', '=', 'permission_groups.id');
```

### Data Structure Optimization

#### Hierarchical Data Processing
```php
// Efficient grouping and mapping
$permissions->groupBy('category')
    ->map(function ($categoryPermissions) {
        return $categoryPermissions->groupBy('group');
    });
```

---

## 🔐 Security Considerations

### Input Validation

#### Comprehensive Validation
- **Unique Names**: Prevents duplicate permission keys
- **Group Validation**: Ensures valid permission groups
- **Type Validation**: Ensures correct data types
- **Existence Validation**: Validates role and user existence

### Guard Validation

#### Security Enforcement
```php
// Ensure role and permission have matching guards
if ($role->guard_name !== $permission->guard_name) {
    return response()->json([
        'success' => false,
        'message' => 'Guard mismatch between role and permission.',
    ], 400);
}
```

### Access Control

#### Admin-Only Operations
- **Authentication Required**: All operations require authentication
- **Admin Permissions**: Requires admin-level permissions
- **Audit Trail**: Tracks permission modifications

---

## 📈 Usage Examples

### Frontend Permission Management

```javascript
// Permission Management Interface
class PermissionManager {
    constructor() {
        this.permissions = [];
        this.groups = [];
        this.roles = [];
        this.loadInitialData();
    }

    async loadInitialData() {
        await Promise.all([
            this.loadPermissions(),
            this.loadGroups(),
            this.loadRoles()
        ]);
        this.renderInterface();
    }

    async loadPermissions(groupId = null) {
        try {
            const url = groupId ? `/admin/permissions?group_id=${groupId}` : '/admin/permissions';
            const response = await fetch(url);
            const html = await response.text();
            
            // Parse HTML or use API endpoint
            this.renderPermissions();
        } catch (error) {
            console.error('Failed to load permissions:', error);
        }
    }

    async createPermission(formData) {
        try {
            const response = await fetch('/admin/permissions', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
                },
                body: JSON.stringify(formData)
            });

            const result = await response.json();

            if (result.success) {
                this.showSuccess('Permission created successfully');
                this.loadPermissions();
                this.resetForm();
            } else {
                this.showErrors(result.errors || result.message);
            }
        } catch (error) {
            console.error('Failed to create permission:', error);
            this.showError('Failed to create permission');
        }
    }

    async updatePermission(id, formData) {
        try {
            const response = await fetch(`/admin/permissions/${id}`, {
                method: 'PUT',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
                },
                body: JSON.stringify(formData)
            });

            const result = await response.json();

            if (result.success) {
                this.showSuccess('Permission updated successfully');
                this.loadPermissions();
                this.closeEditModal();
            } else {
                this.showErrors(result.errors || result.message);
            }
        } catch (error) {
            console.error('Failed to update permission:', error);
            this.showError('Failed to update permission');
        }
    }

    async assignPermissionToRole(roleId, permissionId, isChecked) {
        try {
            const response = await fetch('/admin/permissions/assign', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
                },
                body: JSON.stringify({
                    role_id: roleId,
                    permission_id: permissionId,
                    isChecked: isChecked
                })
            });

            const result = await response.json();

            if (result.success) {
                this.showSuccess('Permission assignment updated');
            } else {
                this.showError(result.message);
                // Revert checkbox state
                this.revertCheckbox(roleId, permissionId);
            }
        } catch (error) {
            console.error('Failed to assign permission:', error);
            this.showError('Failed to update permission assignment');
            this.revertCheckbox(roleId, permissionId);
        }
    }

    async loadUserPermissions(userId) {
        try {
            const response = await fetch(`/admin/users/${userId}/permissions`);
            const result = await response.json();

            if (result.success) {
                this.renderUserPermissions(result.data);
            }
        } catch (error) {
            console.error('Failed to load user permissions:', error);
        }
    }

    async assignUserPermission(userId, permissionId) {
        try {
            const response = await fetch(`/admin/users/${userId}/permissions/assign`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
                },
                body: JSON.stringify({
                    permission_id: permissionId
                })
            });

            const result = await response.json();

            if (result.success) {
                this.showSuccess('Permission assigned to user');
                this.loadUserPermissions(userId);
            } else {
                this.showError(result.message);
            }
        } catch (error) {
            console.error('Failed to assign user permission:', error);
            this.showError('Failed to assign permission');
        }
    }

    async assignClaimType(userId, claimTypeId) {
        try {
            const response = await fetch(`/admin/users/${userId}/claim-types/assign`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
                },
                body: JSON.stringify({
                    claim_type_id: claimTypeId
                })
            });

            const result = await response.json();

            if (result.success) {
                this.showSuccess('Claim type assigned successfully');
                this.loadUserPermissions(userId);
            } else {
                this.showError(result.message);
            }
        } catch (error) {
            console.error('Failed to assign claim type:', error);
            this.showError('Failed to assign claim type');
        }
    }

    renderInterface() {
        this.renderPermissionMatrix();
        this.renderGroupFilter();
        this.renderRoleHeaders();
    }

    renderPermissionMatrix() {
        const container = document.getElementById('permission-matrix');
        container.innerHTML = '';

        this.permissions.forEach(permission => {
            const row = document.createElement('tr');
            row.innerHTML = `
                <td>
                    <strong>${permission.permission_key}</strong>
                    <br><small class="text-muted">${permission.group_name}</small>
                </td>
                ${this.roles.map(role => `
                    <td class="text-center">
                        <input type="checkbox" 
                               class="permission-checkbox"
                               data-role-id="${role.id}"
                               data-permission-id="${permission.id}"
                               ${this.hasPermission(role.id, permission.id) ? 'checked' : ''}>
                    </td>
                `).join('')}
                <td>
                    <button class="btn btn-sm btn-primary edit-permission" data-id="${permission.id}">
                        Edit
                    </button>
                    <button class="btn btn-sm btn-danger delete-permission" data-id="${permission.id}">
                        Delete
                    </button>
                </td>
            `;
            container.appendChild(row);
        });
    }

    renderUserPermissions(data) {
        const container = document.getElementById('user-permissions');
        container.innerHTML = '';

        // Render user info
        const userInfo = document.createElement('div');
        userInfo.innerHTML = `
            <h4>${data.user.name}</h4>
            <p class="text-muted">${data.user.email}</p>
        `;
        container.appendChild(userInfo);

        // Render permissions by group
        Object.keys(data.permissions).forEach(groupName => {
            const groupDiv = document.createElement('div');
            groupDiv.className = 'permission-group mb-3';
            
            groupDiv.innerHTML = `
                <h5>${groupName}</h5>
                <div class="permission-list">
                    ${data.permissions[groupName].map(permission => `
                        <div class="permission-item">
                            <span>${permission.name}</span>
                            <button class="btn btn-sm btn-danger revoke-permission" 
                                    data-user-id="${data.user.id}" 
                                    data-permission-id="${permission.id}">
                                Revoke
                            </button>
                        </div>
                    `).join('')}
                </div>
            `;
            
            container.appendChild(groupDiv);
        });

        // Render assigned claim types
        if (data.assigned_claim_types.length > 0) {
            const claimTypesDiv = document.createElement('div');
            claimTypesDiv.innerHTML = `
                <h5>Assigned Claim Types</h5>
                <div class="claim-types-list">
                    ${data.assigned_claim_types.map(claimTypeId => `
                        <span class="badge badge-info">
                            Claim Type ${claimTypeId}
                            <button class="btn btn-sm btn-link revoke-claim-type" 
                                    data-user-id="${data.user.id}" 
                                    data-claim-type-id="${claimTypeId}">
                                ×
                            </button>
                        </span>
                    `).join('')}
                </div>
            `;
            container.appendChild(claimTypesDiv);
        }
    }
}

// Initialize permission manager
const permissionManager = new PermissionManager();

// Event handlers
document.addEventListener('change', (e) => {
    if (e.target.classList.contains('permission-checkbox')) {
        const roleId = parseInt(e.target.dataset.roleId);
        const permissionId = parseInt(e.target.dataset.permissionId);
        const isChecked = e.target.checked;
        
        permissionManager.assignPermissionToRole(roleId, permissionId, isChecked);
    }
});

document.addEventListener('click', (e) => {
    if (e.target.classList.contains('revoke-permission')) {
        const userId = e.target.dataset.userId;
        const permissionId = e.target.dataset.permissionId;
        
        permissionManager.revokeUserPermission(userId, permissionId);
    }
    
    if (e.target.classList.contains('revoke-claim-type')) {
        const userId = e.target.dataset.userId;
        const claimTypeId = e.target.dataset.claimTypeId;
        
        permissionManager.revokeClaimType(userId, claimTypeId);
    }
});

// Form submission handlers
document.getElementById('create-permission-form').addEventListener('submit', (e) => {
    e.preventDefault();
    
    const formData = {
        permission_name: document.getElementById('permission-name').value,
        group_id: parseInt(document.getElementById('group-id').value),
        is_active: document.getElementById('is-active').checked
    };
    
    permissionManager.createPermission(formData);
});
```

### Permission Checking in Application

```php
// Middleware for permission checking
class PermissionMiddleware
{
    public function handle($request, Closure $next, $permission)
    {
        if (!auth()->check()) {
            return redirect()->route('login');
        }

        if (!auth()->user()->can($permission)) {
            abort(403, 'Insufficient permissions');
        }

        return $next($request);
    }
}

// Usage in routes
Route::middleware(['auth', 'permission:manage_employee_reports'])->group(function () {
    Route::get('/admin/employee-reports', [ReportController::class, 'employeeReports']);
});

// Usage in controllers
class ReportController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth');
        $this->middleware('permission:view_reports')->only(['index', 'show']);
        $this->middleware('permission:create_reports')->only(['create', 'store']);
        $this->middleware('permission:manage_employee_reports')->only(['employeeReports']);
    }
}

// Usage in Blade templates
@can('manage_employee_reports')
    <a href="/admin/employee-reports" class="btn btn-primary">Employee Reports</a>
@endcan

@cannot('create_reports')
    <p class="text-muted">You don't have permission to create reports.</p>
@endcannot

// Check multiple permissions
@canany(['view_reports', 'create_reports'])
    <div class="reports-section">
        <!-- Reports content -->
    </div>
@endcanany
```

---

## 🔗 Integration Points

### Spatie Permission Package Integration

#### Core Functionality
- **Permission Storage**: Database-backed permission storage
- **Role Assignment**: Role-permission relationships
- **User Permissions**: Direct user permissions
- **Cache Management**: Performance optimization through caching

### Menu System Integration

#### Navigation Control
- **Menu Visibility**: Permission-based menu item display
- **Dynamic Navigation**: Real-time navigation updates
- **User Experience**: Seamless permission-based interface

### Claim Type Access Control

#### Business Logic Integration
- **Expense Claims**: Controls claim type access
- **User Restrictions**: Per-user claim type limitations
- **Workflow Control**: Permission-based workflow access

---

The Admin PermissionController provides comprehensive permission management functionality, serving as the foundation for the application's security and access control system while maintaining performance, flexibility, and ease of use essential for enterprise applications.