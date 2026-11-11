# 🧭 Admin MenuController Documentation

The Admin MenuController manages the navigation menu system in the Xeasy Laravel expense management system, providing comprehensive menu management with hierarchical structure, permission integration, and dynamic ordering.

## 📋 Overview

**Location**: `app/Http/Controllers/admin/MenuController.php`

**Purpose**: Handles complete menu lifecycle management including creation, modification, hierarchical organization, permission-based access control, and dynamic ordering for the application's navigation system.

**Middleware**: Inherits from base Controller (typically includes auth and admin middleware)

**Key Features**:
- Hierarchical menu structure with parent-child relationships
- Dynamic menu ordering and reordering
- Permission-based menu access control
- Status-based filtering (Active/Inactive)
- Automatic data key generation
- Activity logging integration
- JSON API responses for frontend integration
- Search functionality with level calculation

## 📊 Public Methods

### 1. Menu Management Interface

#### `index(): View`

**Route**: `GET /admin/menus`
**Permission**: Admin access required

**Purpose**: Displays the main menu management interface with filtering capabilities.

**Query Parameters**:
| Parameter | Type | Required | Description |
|-----------|------|----------|-------------|
| status | string | No | Filter by status: 'active', 'inactive', 'all' (default: 'active') |

**Returns**: Blade view (`admin.menu`) with menus and status data

**Data Provided to View**:
- **menus**: Collection of menus based on status filter
- **status**: Current filter status

**Status Filtering Logic**:
- **Active**: `status = 1`
- **Inactive**: `status = 0`
- **All**: No status filtering

**Business Logic**:
1. **Query Parameter Processing**: Handles status filter with default fallback
2. **Status-Based Filtering**: Applies appropriate status filters
3. **Data Preparation**: Prepares menu data for view consumption

**Example Usage**:
```php
GET /admin/menus                    // Active menus
GET /admin/menus?status=inactive    // Inactive menus
GET /admin/menus?status=all         // All menus
```

---

### 2. Create Menu

#### `store(StoreMenuRequest $request): JsonResponse`

**Route**: `POST /admin/menus`
**Permission**: Admin access required

**Purpose**: Creates a new menu item with automatic ordering, permission integration, and data key generation.

**Request Parameters** (via StoreMenuRequest):
| Parameter | Type | Required | Description |
|-----------|------|----------|-------------|
| menu_name | string | Yes | Menu display name |
| parent_id | integer | No | Parent menu ID for hierarchical structure |
| icon | string | No | Menu icon class/name |
| url | string | No | Menu URL/route |
| permission | string | No | Permission name for access control |
| order | integer | No | Menu order (auto-calculated if not provided) |
| is_active | boolean | No | Active status (default: false) |

**Request Example**:
```json
{
    "menu_name": "Employee Reports",
    "parent_id": 5,
    "icon": "fas fa-chart-bar",
    "url": "/admin/employee-reports",
    "permission": "view_employee_reports",
    "order": 3,
    "is_active": true
}
```

**Response Structure**:
```json
{
    "success": true,
    "message": "Menu created successfully.",
    "data": {
        "id": 25,
        "title": "Employee Reports",
        "data_key": "employee-reports",
        "parent_id": 5,
        "icon": "fas fa-chart-bar",
        "order": 3,
        "url": "/admin/employee-reports",
        "permission_name": "view_employee_reports",
        "permission_id": 15,
        "status": 1,
        "created_by": 1,
        "updated_by": 1,
        "created_at": "2024-01-15T10:30:00.000000Z",
        "updated_at": "2024-01-15T10:30:00.000000Z"
    }
}
```

**Complex Business Logic**:

#### Automatic Order Calculation
```php
// Calculate next order if not provided
$newOrder = isset($validated['order']) 
    ? (int) $validated['order'] 
    : (Menu::where('parent_id', $validated['parent_id'] ?? null)->max('order') ?? 0) + 1;
```

#### Order Adjustment
```php
// Increment order of existing items to make space
Menu::where('parent_id', $parentId)
    ->where('order', '>=', $newOrder)
    ->increment('order');
```

#### Permission Integration
```php
// Find and link permission
$permission = Permission::where('name', $validated['permission'] ?? null)->first();
$menu->permission_id = $permission->id ?? null;
```

#### Data Key Generation
```php
// Generate kebab-case data key
$dataKey = Str::kebab($validated['menu_name']);
// "Employee Reports" → "employee-reports"
```

---

### 3. Retrieve Menu for Editing

#### `edit(string $id): JsonResponse`

**Route**: `GET /admin/menus/{id}/edit`
**Permission**: Admin access required

**Purpose**: Retrieves menu data for editing.

**Parameters**:
| Parameter | Type | Required | Description |
|-----------|------|----------|-------------|
| id | string | Yes | Menu ID (route parameter) |

**Response Structure**:
```json
{
    "success": true,
    "message": "Menu fetched successfully.",
    "data": {
        "id": 25,
        "title": "Employee Reports",
        "data_key": "employee-reports",
        "parent_id": 5,
        "icon": "fas fa-chart-bar",
        "order": 3,
        "url": "/admin/employee-reports",
        "permission_name": "view_employee_reports",
        "permission_id": 15,
        "status": 1,
        "created_by": 1,
        "updated_by": 1,
        "created_at": "2024-01-15T10:30:00.000000Z",
        "updated_at": "2024-01-15T10:30:00.000000Z"
    }
}
```

---

### 4. Update Menu

#### `update(UpdateMenuRequest $request, Menu $menu): JsonResponse`

**Route**: `PUT /admin/menus/{menu}`
**Permission**: Admin access required

**Purpose**: Updates menu information with intelligent reordering and permission synchronization.

**Parameters**:
| Parameter | Type | Required | Description |
|-----------|------|----------|-------------|
| menu | Menu | Yes | Menu model (route model binding) |
| menu_name | string | Yes | Updated menu name |
| parent_id | integer | No | Updated parent menu ID |
| icon | string | No | Updated menu icon |
| url | string | No | Updated menu URL |
| permission | string | No | Updated permission name |
| order | integer | Yes | Updated menu order |
| is_active | boolean | No | Updated active status |

**Request Example**:
```json
{
    "menu_name": "Advanced Employee Reports",
    "parent_id": 5,
    "icon": "fas fa-chart-line",
    "url": "/admin/advanced-employee-reports",
    "permission": "view_advanced_employee_reports",
    "order": 2,
    "is_active": true
}
```

**Response Structure**:
```json
{
    "success": true,
    "message": "Menu updated successfully.",
    "data": {
        "id": 25,
        "title": "Advanced Employee Reports",
        "parent_id": 5,
        "icon": "fas fa-chart-line",
        "order": 2,
        "url": "/admin/advanced-employee-reports",
        "permission_name": "view_advanced_employee_reports",
        "permission_id": 18,
        "status": 1,
        "updated_by": 1,
        "updated_at": "2024-01-15T15:45:00.000000Z"
    }
}
```

**Complex Reordering Logic**:
```php
// Check if reordering is needed
if ($newOrder !== $oldOrder || $parentId !== $menu->parent_id) {
    $this->reorderMenuItems($menu, $newOrder, $oldOrder, $parentId);
}
```

**Business Logic**:
1. **Order Change Detection**: Detects if order or parent changed
2. **Intelligent Reordering**: Calls reordering method if needed
3. **Permission Synchronization**: Updates permission references
4. **User Attribution**: Updates updated_by field

---

### 5. Delete Menu

#### `destroy(string $id): JsonResponse`

**Route**: `DELETE /admin/menus/{id}`
**Permission**: Admin access required

**Purpose**: Deletes a menu item.

**Parameters**:
| Parameter | Type | Required | Description |
|-----------|------|----------|-------------|
| id | string | Yes | Menu ID (route parameter) |

**Response Structure**:
```json
{
    "success": true,
    "message": "Menu deleted successfully.",
    "data": {
        "id": 25,
        "title": "Employee Reports",
        "status": 1
    }
}
```

**Considerations**:
- **Child Menus**: Should handle child menu items
- **Order Adjustment**: Should adjust order of remaining items
- **Permission Cleanup**: Consider permission reference cleanup

---

### 6. Active Menu List API

#### `menuList(): JsonResponse`

**Route**: `GET /api/admin/menus`
**Permission**: Admin access required

**Purpose**: Retrieves all active menus for navigation rendering and API integration.

**Parameters**: None

**Response Structure**:
```json
[
    {
        "id": 1,
        "title": "Dashboard",
        "url": "/admin/dashboard",
        "icon": "fas fa-tachometer-alt",
        "parent_id": null,
        "order": 1,
        "data_key": "dashboard",
        "status": 1,
        "created_at": "2024-01-01T00:00:00.000000Z",
        "updated_at": "2024-01-01T00:00:00.000000Z"
    },
    {
        "id": 5,
        "title": "Reports",
        "url": "/admin/reports",
        "icon": "fas fa-chart-bar",
        "parent_id": null,
        "order": 2,
        "data_key": "reports",
        "status": 1,
        "created_at": "2024-01-01T00:00:00.000000Z",
        "updated_at": "2024-01-01T00:00:00.000000Z"
    },
    {
        "id": 25,
        "title": "Employee Reports",
        "url": "/admin/employee-reports",
        "icon": "fas fa-users",
        "parent_id": 5,
        "order": 1,
        "data_key": "employee-reports",
        "status": 1,
        "created_at": "2024-01-15T10:30:00.000000Z",
        "updated_at": "2024-01-15T10:30:00.000000Z"
    }
]
```

**Features**:
- **Active Only**: Returns only active menus (status = 1)
- **Complete Data**: Returns all necessary fields for navigation
- **Hierarchical Data**: Includes parent_id for tree structure
- **Ordered Results**: Maintains menu order

---

### 7. Search Menu List with Levels

#### `searchMenuList(): JsonResponse`

**Route**: `GET /api/admin/menus/search`
**Permission**: Admin access required

**Purpose**: Retrieves active menus with calculated hierarchy levels for advanced navigation interfaces.

**Parameters**: None

**Response Structure**:
```json
[
    {
        "id": 1,
        "title": "Dashboard",
        "url": "/admin/dashboard",
        "icon": "fas fa-tachometer-alt",
        "parent_id": null,
        "order": 1,
        "level": 0
    },
    {
        "id": 5,
        "title": "Reports",
        "url": "/admin/reports",
        "icon": "fas fa-chart-bar",
        "parent_id": null,
        "order": 2,
        "level": 0
    },
    {
        "id": 25,
        "title": "Employee Reports",
        "url": "/admin/employee-reports",
        "icon": "fas fa-users",
        "parent_id": 5,
        "order": 1,
        "level": 1
    },
    {
        "id": 30,
        "title": "Detailed Reports",
        "url": "/admin/detailed-reports",
        "icon": "fas fa-list",
        "parent_id": 25,
        "order": 1,
        "level": 2
    }
]
```

**Level Calculation Logic**:
```php
$menus = Menu::select('id', 'title', 'url', 'icon', 'parent_id', 'order')
    ->where('status', 1)
    ->get()
    ->map(function ($menu) {
        $level = 0;
        $parent = $menu->parent;
        
        // Traverse up the hierarchy to calculate level
        while ($parent && $level < 10) { // Max 10 levels to prevent infinite loops
            $level++;
            $parent = $parent->parent;
        }
        
        $menu->level = $level;
        return $menu;
    })
    ->sortBy('order');
```

**Features**:
- **Level Calculation**: Automatically calculates hierarchy depth
- **Infinite Loop Protection**: Limits traversal to 10 levels
- **Sorted Results**: Sorted by menu order
- **Search Optimized**: Optimized for search interfaces

---

### 8. Activity Logs

#### `getLogs(Menu $menu): JsonResponse`

**Route**: `GET /admin/menus/{menu}/logs`
**Permission**: Admin access required

**Purpose**: Retrieves activity logs for a specific menu item.

**Parameters**:
| Parameter | Type | Required | Description |
|-----------|------|----------|-------------|
| menu | Menu | Yes | Menu model (route model binding) |

**Response Structure**:
```json
{
    "data": [
        {
            "event": "updated",
            "description": "Menu updated",
            "properties": {
                "attributes": {
                    "title": "Advanced Employee Reports",
                    "order": 2
                },
                "old": {
                    "title": "Employee Reports",
                    "order": 3
                }
            },
            "causer": {
                "name": "John Doe"
            },
            "created_at": "2024-01-15T15:45:00+00:00"
        },
        {
            "event": "created",
            "description": "Menu created",
            "properties": {
                "attributes": {
                    "title": "Employee Reports",
                    "parent_id": 5,
                    "order": 3
                }
            },
            "causer": {
                "name": "John Doe"
            },
            "created_at": "2024-01-15T10:30:00+00:00"
        }
    ],
    "title": "Advanced Employee Reports"
}
```

**Features**:
- **Activity Tracking**: Complete audit trail of menu changes
- **User Attribution**: Shows who made changes
- **Property Changes**: Shows what changed (old vs new values)
- **Chronological Order**: Latest activities first

---

## 🔧 Implementation Details

### Request Validation Classes

#### StoreMenuRequest
```php
// Menu creation validation
$rules = [
    'menu_name' => 'required|string|max:255',
    'parent_id' => 'nullable|integer|exists:menus,id',
    'icon' => 'nullable|string|max:255',
    'url' => 'nullable|string|max:255',
    'permission' => 'nullable|string|exists:permissions,name',
    'order' => 'nullable|integer|min:1',
    'is_active' => 'boolean'
];
```

#### UpdateMenuRequest
```php
// Menu update validation
$rules = [
    'menu_name' => 'required|string|max:255',
    'parent_id' => 'nullable|integer|exists:menus,id',
    'icon' => 'nullable|string|max:255',
    'url' => 'nullable|string|max:255',
    'permission' => 'nullable|string|exists:permissions,name',
    'order' => 'required|integer|min:1',
    'is_active' => 'boolean'
];
```

### Complex Reordering Algorithm

#### `reorderMenuItems(Menu $currentMenu, int $newOrder, int $oldOrder, ?int $parentId): void`

**Purpose**: Intelligently reorders menu items when order or parent changes.

**Scenarios Handled**:

1. **Parent Change**: Moving item to different parent
```php
if ($currentMenu->parent_id !== $parentId) {
    // Decrement order in old parent
    Menu::where('parent_id', $currentMenu->parent_id)
        ->where('order', '>', $oldOrder)
        ->decrement('order');
    
    // Increment order in new parent
    Menu::where('parent_id', $parentId)
        ->where('order', '>=', $newOrder)
        ->increment('order');
}
```

2. **Order Decrease**: Moving item up in same parent
```php
if ($newOrder < $oldOrder) {
    Menu::where('parent_id', $parentId)
        ->where('order', '>=', $newOrder)
        ->where('order', '<', $oldOrder)
        ->increment('order');
}
```

3. **Order Increase**: Moving item down in same parent
```php
if ($newOrder > $oldOrder) {
    Menu::where('parent_id', $parentId)
        ->where('order', '>', $oldOrder)
        ->where('order', '<=', $newOrder)
        ->decrement('order');
}
```

### Model Relationships

#### Menu Model Integration
```php
// In Menu model
public function parent()
{
    return $this->belongsTo(Menu::class, 'parent_id');
}

public function children()
{
    return $this->hasMany(Menu::class, 'parent_id')->orderBy('order');
}

public function permission()
{
    return $this->belongsTo(Permission::class, 'permission_id');
}

public function activities()
{
    return $this->morphMany(Activity::class, 'subject');
}
```

---

## 🎯 Advanced Features

### Hierarchical Menu Structure

#### Multi-Level Navigation
- **Unlimited Depth**: Supports unlimited menu hierarchy levels
- **Parent-Child Relationships**: Proper foreign key relationships
- **Level Calculation**: Automatic hierarchy depth calculation
- **Circular Reference Protection**: Prevents infinite loops

### Dynamic Ordering System

#### Intelligent Order Management
- **Automatic Ordering**: Calculates next order if not provided
- **Gap Filling**: Automatically adjusts orders when items are moved
- **Parent-Specific Ordering**: Orders are maintained per parent
- **Conflict Resolution**: Handles order conflicts intelligently

### Permission Integration

#### Access Control
- **Permission-Based Visibility**: Menus shown based on user permissions
- **Dynamic Permission Lookup**: Automatic permission ID resolution
- **Menu Security**: Secure navigation based on user roles
- **Permission Synchronization**: Keeps menu permissions in sync

### Activity Logging

#### Comprehensive Audit Trail
- **Change Tracking**: Tracks all menu modifications
- **User Attribution**: Records who made changes
- **Property Comparison**: Shows old vs new values
- **Timestamp Tracking**: Complete chronological history

---

## 🚀 Performance Considerations

### Query Optimization

#### Efficient Menu Loading
```php
// Selective column loading for API
Menu::select('id', 'title', 'url', 'icon', 'parent_id', 'order', 'data_key', 'status')
    ->where('status', 1)
    ->get();
```

#### Relationship Optimization
```php
// Eager load relationships when needed
Menu::with(['parent', 'children', 'permission'])->get();
```

### Ordering Performance

#### Batch Updates
- **Bulk Operations**: Uses increment/decrement for batch updates
- **Targeted Updates**: Only updates affected menu items
- **Minimal Queries**: Optimizes database operations

### Caching Opportunities

Consider caching for:
- **Active Menu List**: Cache for 1 hour (rarely changes)
- **Menu Hierarchy**: Cache for 30 minutes (frequently accessed)
- **Permission-Based Menus**: Cache per user for 15 minutes

---

## 🔐 Security Considerations

### Input Validation

#### Comprehensive Validation
- **Parent Validation**: Ensures parent menu exists
- **Permission Validation**: Validates permission names exist
- **URL Validation**: Validates URL format and security
- **Circular Reference Prevention**: Prevents self-referencing parents

### Access Control

#### Permission-Based Security
- **Admin-Only Operations**: All operations require admin permissions
- **Permission Integration**: Menu visibility based on user permissions
- **Secure Navigation**: Prevents unauthorized access through menu manipulation

### Data Integrity

#### Referential Integrity
- **Foreign Key Constraints**: Proper parent-child relationships
- **Permission References**: Valid permission associations
- **Order Consistency**: Maintains proper ordering

---

## 📈 Usage Examples

### Frontend Menu Management

```javascript
// Menu Management Interface
class MenuManager {
    constructor() {
        this.menus = [];
        this.permissions = [];
        this.currentStatus = 'active';
        this.loadInitialData();
    }

    async loadInitialData() {
        await Promise.all([
            this.loadMenus(),
            this.loadPermissions()
        ]);
        this.renderInterface();
    }

    async loadMenus(status = 'active') {
        try {
            const response = await fetch(`/admin/menus?status=${status}`);
            const html = await response.text();
            
            // Parse HTML or use API endpoint
            this.currentStatus = status;
            this.renderMenuTree();
        } catch (error) {
            console.error('Failed to load menus:', error);
        }
    }

    async loadMenuList() {
        try {
            const response = await fetch('/api/admin/menus');
            const menus = await response.json();
            
            this.menus = menus;
            this.buildMenuTree();
        } catch (error) {
            console.error('Failed to load menu list:', error);
        }
    }

    async createMenu(formData) {
        try {
            const response = await fetch('/admin/menus', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
                },
                body: JSON.stringify(formData)
            });

            const result = await response.json();

            if (result.success) {
                this.showSuccess('Menu created successfully');
                this.loadMenus(this.currentStatus);
                this.resetForm();
            } else {
                this.showErrors(result.errors || result.message);
            }
        } catch (error) {
            console.error('Failed to create menu:', error);
            this.showError('Failed to create menu');
        }
    }

    async updateMenu(id, formData) {
        try {
            const response = await fetch(`/admin/menus/${id}`, {
                method: 'PUT',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
                },
                body: JSON.stringify(formData)
            });

            const result = await response.json();

            if (result.success) {
                this.showSuccess('Menu updated successfully');
                this.loadMenus(this.currentStatus);
                this.closeEditModal();
            } else {
                this.showErrors(result.errors || result.message);
            }
        } catch (error) {
            console.error('Failed to update menu:', error);
            this.showError('Failed to update menu');
        }
    }

    async deleteMenu(id) {
        if (!confirm('Are you sure you want to delete this menu? This action cannot be undone.')) {
            return;
        }

        try {
            const response = await fetch(`/admin/menus/${id}`, {
                method: 'DELETE',
                headers: {
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
                }
            });

            const result = await response.json();

            if (result.success) {
                this.showSuccess('Menu deleted successfully');
                this.loadMenus(this.currentStatus);
            } else {
                this.showError(result.message);
            }
        } catch (error) {
            console.error('Failed to delete menu:', error);
            this.showError('Failed to delete menu');
        }
    }

    async loadMenuLogs(menuId) {
        try {
            const response = await fetch(`/admin/menus/${menuId}/logs`);
            const result = await response.json();
            
            this.renderMenuLogs(result.data, result.title);
        } catch (error) {
            console.error('Failed to load menu logs:', error);
        }
    }

    buildMenuTree() {
        const menuMap = {};
        const rootMenus = [];

        // Create menu map
        this.menus.forEach(menu => {
            menuMap[menu.id] = { ...menu, children: [] };
        });

        // Build tree structure
        this.menus.forEach(menu => {
            if (menu.parent_id) {
                if (menuMap[menu.parent_id]) {
                    menuMap[menu.parent_id].children.push(menuMap[menu.id]);
                }
            } else {
                rootMenus.push(menuMap[menu.id]);
            }
        });

        // Sort by order
        const sortByOrder = (menus) => {
            menus.sort((a, b) => a.order - b.order);
            menus.forEach(menu => {
                if (menu.children.length > 0) {
                    sortByOrder(menu.children);
                }
            });
        };

        sortByOrder(rootMenus);
        this.menuTree = rootMenus;
    }

    renderMenuTree() {
        const container = document.getElementById('menu-tree');
        container.innerHTML = '';

        const renderMenuItem = (menu, level = 0) => {
            const menuDiv = document.createElement('div');
            menuDiv.className = `menu-item level-${level}`;
            menuDiv.style.marginLeft = `${level * 20}px`;
            
            menuDiv.innerHTML = `
                <div class="menu-content">
                    <div class="menu-info">
                        <i class="${menu.icon || 'fas fa-circle'}"></i>
                        <span class="menu-title">${menu.title}</span>
                        <span class="menu-url text-muted">${menu.url || ''}</span>
                        <span class="menu-order badge badge-secondary">${menu.order}</span>
                    </div>
                    <div class="menu-actions">
                        <button class="btn btn-sm btn-primary edit-menu" data-id="${menu.id}">
                            Edit
                        </button>
                        <button class="btn btn-sm btn-info view-logs" data-id="${menu.id}">
                            Logs
                        </button>
                        <button class="btn btn-sm btn-danger delete-menu" data-id="${menu.id}">
                            Delete
                        </button>
                    </div>
                </div>
            `;

            container.appendChild(menuDiv);

            // Render children
            if (menu.children && menu.children.length > 0) {
                menu.children.forEach(child => {
                    renderMenuItem(child, level + 1);
                });
            }
        };

        this.menuTree.forEach(menu => {
            renderMenuItem(menu);
        });
    }

    renderMenuLogs(logs, menuTitle) {
        const modal = document.getElementById('logs-modal');
        const title = modal.querySelector('.modal-title');
        const body = modal.querySelector('.modal-body');

        title.textContent = `Activity Logs - ${menuTitle}`;
        body.innerHTML = '';

        if (logs.length === 0) {
            body.innerHTML = '<p class="text-muted">No activity logs found.</p>';
            return;
        }

        logs.forEach(log => {
            const logDiv = document.createElement('div');
            logDiv.className = 'log-entry mb-3 p-3 border rounded';
            
            const eventBadge = log.event === 'created' ? 'success' : 
                              log.event === 'updated' ? 'info' : 
                              log.event === 'deleted' ? 'danger' : 'secondary';

            logDiv.innerHTML = `
                <div class="d-flex justify-content-between align-items-start mb-2">
                    <span class="badge badge-${eventBadge}">${log.event.toUpperCase()}</span>
                    <small class="text-muted">${new Date(log.created_at).toLocaleString()}</small>
                </div>
                <p class="mb-2">${log.description}</p>
                ${log.causer ? `<small class="text-muted">By: ${log.causer.name}</small>` : ''}
                ${log.properties && Object.keys(log.properties).length > 0 ? `
                    <details class="mt-2">
                        <summary>View Changes</summary>
                        <pre class="mt-2 p-2 bg-light rounded"><code>${JSON.stringify(log.properties, null, 2)}</code></pre>
                    </details>
                ` : ''}
            `;
            
            body.appendChild(logDiv);
        });

        // Show modal (assuming Bootstrap modal)
        $(modal).modal('show');
    }

    populateParentDropdown() {
        const select = document.getElementById('parent-id');
        select.innerHTML = '<option value="">No Parent (Root Level)</option>';

        const addOptions = (menus, level = 0) => {
            menus.forEach(menu => {
                const option = document.createElement('option');
                option.value = menu.id;
                option.textContent = '  '.repeat(level) + menu.title;
                select.appendChild(option);

                if (menu.children && menu.children.length > 0) {
                    addOptions(menu.children, level + 1);
                }
            });
        };

        addOptions(this.menuTree);
    }
}

// Initialize menu manager
const menuManager = new MenuManager();

// Event handlers
document.addEventListener('click', (e) => {
    if (e.target.classList.contains('edit-menu')) {
        const menuId = e.target.dataset.id;
        menuManager.editMenu(menuId);
    }
    
    if (e.target.classList.contains('delete-menu')) {
        const menuId = e.target.dataset.id;
        menuManager.deleteMenu(menuId);
    }
    
    if (e.target.classList.contains('view-logs')) {
        const menuId = e.target.dataset.id;
        menuManager.loadMenuLogs(menuId);
    }
});

// Form submission handlers
document.getElementById('create-menu-form').addEventListener('submit', (e) => {
    e.preventDefault();
    
    const formData = {
        menu_name: document.getElementById('menu-name').value,
        parent_id: document.getElementById('parent-id').value || null,
        icon: document.getElementById('icon').value,
        url: document.getElementById('url').value,
        permission: document.getElementById('permission').value,
        order: parseInt(document.getElementById('order').value) || null,
        is_active: document.getElementById('is-active').checked
    };
    
    menuManager.createMenu(formData);
});

// Status filter handlers
document.querySelectorAll('.status-filter').forEach(button => {
    button.addEventListener('click', (e) => {
        const status = e.target.dataset.status;
        menuManager.loadMenus(status);
        
        // Update active button
        document.querySelectorAll('.status-filter').forEach(btn => btn.classList.remove('active'));
        e.target.classList.add('active');
    });
});
```

### Navigation Rendering

```php
// Helper class for rendering navigation
class NavigationRenderer
{
    public static function renderNavigation($userId = null)
    {
        $user = $userId ? User::find($userId) : auth()->user();
        
        if (!$user) {
            return '';
        }

        $menus = Menu::where('status', 1)
            ->orderBy('order')
            ->get();

        $menuTree = self::buildMenuTree($menus);
        
        return self::renderMenuTree($menuTree, $user);
    }

    private static function buildMenuTree($menus)
    {
        $menuMap = [];
        $rootMenus = [];

        foreach ($menus as $menu) {
            $menuMap[$menu->id] = $menu;
            $menuMap[$menu->id]->children = [];
        }

        foreach ($menus as $menu) {
            if ($menu->parent_id && isset($menuMap[$menu->parent_id])) {
                $menuMap[$menu->parent_id]->children[] = $menu;
            } else {
                $rootMenus[] = $menu;
            }
        }

        return $rootMenus;
    }

    private static function renderMenuTree($menus, $user, $level = 0)
    {
        $html = '';

        foreach ($menus as $menu) {
            // Check permission
            if ($menu->permission_name && !$user->can($menu->permission_name)) {
                continue;
            }

            $hasChildren = count($menu->children) > 0;
            $activeClass = request()->is(trim($menu->url, '/')) ? 'active' : '';

            $html .= '<li class="nav-item ' . ($hasChildren ? 'dropdown' : '') . ' ' . $activeClass . '">';
            
            if ($hasChildren) {
                $html .= '<a class="nav-link dropdown-toggle" href="#" data-toggle="dropdown">';
                $html .= '<i class="' . $menu->icon . '"></i> ' . $menu->title;
                $html .= '</a>';
                $html .= '<ul class="dropdown-menu">';
                $html .= self::renderMenuTree($menu->children, $user, $level + 1);
                $html .= '</ul>';
            } else {
                $html .= '<a class="nav-link" href="' . $menu->url . '">';
                $html .= '<i class="' . $menu->icon . '"></i> ' . $menu->title;
                $html .= '</a>';
            }
            
            $html .= '</li>';
        }

        return $html;
    }
}

// Usage in Blade template
{!! NavigationRenderer::renderNavigation() !!}

// Or in controller
public function dashboard()
{
    $navigation = NavigationRenderer::renderNavigation(auth()->id());
    return view('dashboard', compact('navigation'));
}
```

---

## 🔗 Integration Points

### Permission System Integration

#### Access Control
- **Permission-Based Visibility**: Menus shown based on user permissions
- **Dynamic Permission Resolution**: Automatic permission ID lookup
- **Security Integration**: Secure navigation through permission checking

### Activity Logging Integration

#### Audit Trail
- **Change Tracking**: Complete audit trail of menu modifications
- **User Attribution**: Tracks who made changes
- **Property Tracking**: Shows what changed in detail

### Frontend Navigation Integration

#### Dynamic Navigation
- **Real-time Updates**: Navigation updates based on menu changes
- **Permission-Based Rendering**: Shows only accessible menu items
- **Hierarchical Display**: Proper nested menu rendering

---

The Admin MenuController provides comprehensive menu management functionality, serving as the foundation for the application's navigation system while maintaining hierarchical organization, permission-based security, and dynamic ordering capabilities essential for enterprise applications.