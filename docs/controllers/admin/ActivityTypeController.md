# ActivityTypeController Documentation

## Overview

The `ActivityTypeController` manages activity types for expense claims in the Xeasy expense management system. It provides functionality for creating, editing, and managing different types of activities that can be associated with expense claims, particularly for field activities and research-based expenses. The controller integrates with department management and permission systems.

## Class Information

- **Namespace**: `App\Http\Controllers\admin`
- **Extends**: `Controller`
- **Location**: `app/Http/Controllers/admin/ActivityTypeController.php`

## Dependencies

- `App\Models\ActivityCategory` - Activity category management
- `App\Models\ActivityType` - Activity type model
- `App\Models\CoreDepartments` - Department management
- `Illuminate\Support\Facades\Auth` - Authentication
- `Spatie\Permission\Models\Permission` - Permission management
- `Spatie\Permission\Models\Role` - Role management

## Methods

### index()

**Purpose**: Displays the activity type management interface with status filtering.

**Route**: `GET /admin/activity-types`

**Parameters** (Query Parameters):
- `status` (optional) - Filter by status (active/inactive/all)

**Returns**: View `admin.activity_type` with activity types and status

**Business Logic**:
1. **Status Filtering**: Filters activity types by active/inactive status
2. **Relationship Loading**: Eager loads category relationships
3. **Default Handling**: Defaults to active status if invalid status provided

**Filtering Logic**:
```php
$activityTypeQuery = ActivityType::with('category');
if ($status === 'active') {
    $activityTypeQuery->where('status', 1);
} elseif ($status === 'inactive') {
    $activityTypeQuery->where('status', 0);
} elseif ($status === 'all') {
    // No filter applied
} else {
    $status = 'active';
    $activityTypeQuery->where('status', 1);
}
```
**Frontend Integration**:
```javascript
// Initialize activity type management
function initializeActivityTypeManagement() {
    initializeStatusFilter();
    initializeActivityTypeActions();
    loadDepartments();
    loadCategories();
}

// Status filter functionality
function initializeStatusFilter() {
    const statusFilter = document.getElementById('status-filter');
    statusFilter.addEventListener('change', function() {
        const status = this.value;
        const url = `/admin/activity-types?status=${status}`;
        window.location.href = url;
    });
}

// Load departments for form
async function loadDepartments() {
    try {
        const response = await fetch('/admin/activity-types/departments');
        const result = await response.json();
        
        if (result.success) {
            populateDepartmentCheckboxes(result.data);
        }
    } catch (error) {
        console.error('Error loading departments:', error);
    }
}

// Populate department checkboxes
function populateDepartmentCheckboxes(departments) {
    const container = document.getElementById('department-checkboxes');
    container.innerHTML = '';
    
    departments.forEach(dept => {
        const checkboxHtml = `
            <div class="form-check">
                <input class="form-check-input" type="checkbox" 
                       name="department_id[]" value="${dept.id}" 
                       id="dept_${dept.id}">
                <label class="form-check-label" for="dept_${dept.id}">
                    ${dept.department_name}
                </label>
            </div>
        `;
        container.innerHTML += checkboxHtml;
    });
}
```

---

### activityTypeList()

**Purpose**: Retrieves all activity types for API consumption and dropdown population.

**Route**: `GET /admin/activity-types/list`

**Parameters**: None

**Returns**: JSON response with all activity types

**Business Logic**:
- Returns all activity types regardless of status
- Used for form dropdowns and API integration

**Response Structure**:
```json
{
    "success": true,
    "message": "Activity types fetched successfully.",
    "data": [
        {
            "id": 1,
            "department_id": "1,2,3",
            "type_name": "Field Research",
            "description": "Field research activities and data collection",
            "status": 1,
            "created_at": "2024-01-15T10:30:00.000000Z",
            "updated_at": "2024-01-15T10:30:00.000000Z"
        }
    ]
}
```

**Frontend Integration**:
```javascript
// Load activity types for dropdown
async function loadActivityTypeDropdown() {
    try {
        const response = await fetch('/admin/activity-types/list');
        const result = await response.json();
        
        if (result.success) {
            populateActivityTypeDropdown(result.data);
        }
    } catch (error) {
        console.error('Error loading activity types:', error);
    }
}

// Populate activity type dropdown
function populateActivityTypeDropdown(activityTypes) {
    const dropdown = document.getElementById('activity-type-dropdown');
    dropdown.innerHTML = '<option value="">Select Activity Type</option>';
    
    activityTypes.forEach(type => {
        if (type.status === 1) { // Only show active types
            const option = document.createElement('option');
            option.value = type.id;
            option.textContent = type.type_name;
            option.dataset.description = type.description;
            dropdown.appendChild(option);
        }
    });
}
```

---

### store(Request $request)

**Purpose**: Creates a new activity type with department associations and automatic permission setup.

**Route**: `POST /admin/activity-types`

**Parameters**:
- `department_id` (nullable|array) - Associated department IDs
- `type_name` (required|string|max:255) - Activity type name
- `description` (nullable|string) - Activity type description
- `status` (boolean) - Activity type status

**Returns**: JSON response with created activity type data

**Business Logic**:
1. **Validation**: Validates input parameters with specific rules
2. **Department Processing**: Converts department array to comma-separated string
3. **Activity Type Creation**: Creates new activity type record
4. **Permission Generation**: Automatically creates activity type-specific permissions
5. **Role Assignment**: Assigns permissions to Super Admin role

**Department Processing Logic**:
```php
$departmentId = $request->department_id && is_array($request->department_id) && !empty($request->department_id)
    ? implode(',', $request->department_id)
    : null;
```

**Permission System Integration**:
```php
$permissions = [
    "view activitytype {$activityType->id}",
    "update activitytype {$activityType->id}",
    "delete activitytype {$activityType->id}",
];

foreach ($permissions as $perm) {
    Permission::firstOrCreate(['name' => $perm, 'guard_name' => 'web']);
}

$managerRole = Role::where('name', 'Super Admin')->first();
if ($managerRole) {
    $managerRole->givePermissionTo("view activitytype {$activityType->id}");
}
```
**Response Structure**:
```json
{
    "success": true,
    "message": "Activity type created successfully.",
    "data": {
        "id": 1,
        "department_id": "1,2,3",
        "type_name": "Field Research",
        "description": "Field research activities and data collection",
        "status": 1,
        "created_at": "2024-01-15T10:30:00.000000Z",
        "updated_at": "2024-01-15T10:30:00.000000Z"
    }
}
```

**Frontend Integration**:
```javascript
// Create new activity type
async function createActivityType(formData) {
    try {
        showLoading('Creating activity type...');
        
        const response = await fetch('/admin/activity-types', {
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
            refreshActivityTypeList();
            closeActivityTypeModal();
        } else {
            showError(result.message);
        }
    } catch (error) {
        hideLoading();
        console.error('Error creating activity type:', error);
        showError('Failed to create activity type');
    }
}

// Activity type form handler
function handleActivityTypeForm() {
    const form = document.getElementById('activity-type-form');
    form.addEventListener('submit', function(e) {
        e.preventDefault();
        
        // Get selected departments
        const selectedDepartments = [];
        document.querySelectorAll('input[name="department_id[]"]:checked').forEach(checkbox => {
            selectedDepartments.push(parseInt(checkbox.value));
        });
        
        const formData = {
            department_id: selectedDepartments,
            type_name: document.getElementById('type-name').value,
            description: document.getElementById('description').value,
            status: document.getElementById('status').checked
        };
        
        if (validateActivityTypeForm(formData)) {
            createActivityType(formData);
        }
    });
}

// Form validation
function validateActivityTypeForm(formData) {
    const errors = [];
    
    if (!formData.type_name || formData.type_name.length > 255) {
        errors.push('Activity type name is required and must be less than 255 characters');
    }
    
    if (errors.length > 0) {
        showError(errors.join('<br>'));
        return false;
    }
    
    return true;
}
```

---

### edit(string $id)

**Purpose**: Retrieves activity type data for editing purposes.

**Route**: `GET /admin/activity-types/{id}/edit`

**Parameters**:
- `id` (required) - Activity type ID

**Returns**: JSON response with activity type data

**Business Logic**:
1. **Activity Type Retrieval**: Finds activity type by ID or throws 404
2. **Data Return**: Returns complete activity type information
3. **Error Handling**: Handles non-existent activity types gracefully

**Response Structure**:
```json
{
    "success": true,
    "message": "Activity type retrieved successfully.",
    "data": {
        "id": 1,
        "department_id": "1,2,3",
        "type_name": "Field Research",
        "description": "Field research activities and data collection",
        "status": 1,
        "created_at": "2024-01-15T10:30:00.000000Z",
        "updated_at": "2024-01-15T10:30:00.000000Z"
    }
}
```

**Frontend Integration**:
```javascript
// Load activity type data for editing
async function editActivityType(activityTypeId) {
    try {
        showLoading('Loading activity type data...');
        
        const response = await fetch(`/admin/activity-types/${activityTypeId}/edit`);
        const result = await response.json();
        
        hideLoading();
        
        if (result.success) {
            populateActivityTypeForm(result.data);
            showActivityTypeModal('Edit Activity Type');
        } else {
            showError(result.message);
        }
    } catch (error) {
        hideLoading();
        console.error('Error loading activity type data:', error);
        showError('Failed to load activity type data');
    }
}

// Populate form with activity type data
function populateActivityTypeForm(activityTypeData) {
    document.getElementById('type-name').value = activityTypeData.type_name;
    document.getElementById('description').value = activityTypeData.description || '';
    document.getElementById('status').checked = activityTypeData.status === 1;
    
    // Clear all department checkboxes first
    document.querySelectorAll('input[name="department_id[]"]').forEach(checkbox => {
        checkbox.checked = false;
    });
    
    // Check selected departments
    if (activityTypeData.department_id) {
        const selectedDepartments = activityTypeData.department_id.split(',');
        selectedDepartments.forEach(deptId => {
            const checkbox = document.getElementById(`dept_${deptId}`);
            if (checkbox) {
                checkbox.checked = true;
            }
        });
    }
}
```

---

### update(Request $request, string $id)

**Purpose**: Updates existing activity type information with validation and audit tracking.

**Route**: `PUT /admin/activity-types/{id}`

**Parameters**:
- `id` (required) - Activity type ID
- Same validation rules as store method

**Returns**: JSON response with updated activity type data

**Business Logic**:
1. **Validation**: Same comprehensive validation as creation
2. **Department Processing**: Converts department array to comma-separated string
3. **Activity Type Update**: Updates existing activity type with new data
4. **Audit Trail**: Records update timestamp

**Frontend Integration**:
```javascript
// Update existing activity type
async function updateActivityType(activityTypeId, formData) {
    try {
        showLoading('Updating activity type...');
        
        const response = await fetch(`/admin/activity-types/${activityTypeId}`, {
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
            refreshActivityTypeList();
            closeActivityTypeModal();
        } else {
            showError(result.message);
        }
    } catch (error) {
        hideLoading();
        console.error('Error updating activity type:', error);
        showError('Failed to update activity type');
    }
}
```
---

### destroy(string $id)

**Purpose**: Deletes an activity type from the system.

**Route**: `DELETE /admin/activity-types/{id}`

**Parameters**:
- `id` (required) - Activity type ID

**Returns**: JSON response with deletion confirmation

**Business Logic**:
1. **Activity Type Retrieval**: Finds activity type by ID or throws 404
2. **Deletion**: Removes activity type record from database
3. **Response**: Returns success confirmation

**Frontend Integration**:
```javascript
// Delete activity type
async function deleteActivityType(activityTypeId) {
    const confirmDelete = confirm('Are you sure you want to delete this activity type? This action cannot be undone.');
    
    if (!confirmDelete) return;
    
    try {
        showLoading('Deleting activity type...');
        
        const response = await fetch(`/admin/activity-types/${activityTypeId}`, {
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
            refreshActivityTypeList();
        } else {
            showError(result.message);
        }
    } catch (error) {
        hideLoading();
        console.error('Error deleting activity type:', error);
        showError('Failed to delete activity type');
    }
}
```

---

### getCategories()

**Purpose**: Retrieves all activity categories for form population.

**Route**: `GET /admin/activity-types/categories`

**Parameters**: None

**Returns**: JSON response with activity categories

**Business Logic**:
- Returns all available activity categories
- Used for form dropdowns and category selection

**Response Structure**:
```json
{
    "success": true,
    "message": "Activity categories retrieved successfully.",
    "data": [
        {
            "id": 1,
            "category_name": "Research",
            "description": "Research-related activities",
            "status": 1
        },
        {
            "id": 2,
            "category_name": "Field Work",
            "description": "Field-based activities",
            "status": 1
        }
    ]
}
```

---

### getDepartments()

**Purpose**: Retrieves active departments for activity type association.

**Route**: `GET /admin/activity-types/departments`

**Parameters**: None

**Returns**: JSON response with active departments

**Business Logic**:
- Filters departments by active status (is_active = 1)
- Returns essential department information for form population

**Response Structure**:
```json
{
    "success": true,
    "message": "Departments retrieved successfully.",
    "data": [
        {
            "id": 1,
            "department_name": "Research & Development"
        },
        {
            "id": 2,
            "department_name": "Field Operations"
        },
        {
            "id": 3,
            "department_name": "Quality Assurance"
        }
    ]
}
```

## Security Considerations

### Access Control
- **Admin Authentication**: All methods require admin-level access
- **Permission System**: Automatic activity type-specific permission creation
- **Role Integration**: Seamless integration with Spatie permission system

### Data Validation
- **Input Sanitization**: All inputs validated and sanitized
- **Length Restrictions**: Appropriate field length limitations
- **Array Validation**: Proper validation of department ID arrays

### Audit Trail
- **Timestamp Recording**: Creation and update timestamps maintained
- **Status Tracking**: Activity type status changes tracked

## Performance Optimizations

### Database Efficiency
```php
// Efficient activity type retrieval with relationships
$activityTypes = ActivityType::with('category')->get();

// Optimized department retrieval
$departments = CoreDepartments::where('is_active', 1)
    ->get(['id', 'department_name']);
```

### Caching Strategy
- **Department Caching**: Cache active departments for form population
- **Category Caching**: Cache activity categories
- **Activity Type Caching**: Cache frequently accessed activity types

### Memory Management
- **Selective Loading**: Load only required fields for large datasets
- **Efficient Queries**: Use proper indexing and selective joins
- **Resource Cleanup**: Proper cleanup of temporary resources

## Configuration

### Database Schema Requirements
```sql
-- Activity types table
CREATE TABLE activity_types (
    id INT PRIMARY KEY AUTO_INCREMENT,
    department_id TEXT,
    type_name VARCHAR(255) NOT NULL,
    description TEXT,
    status TINYINT(1) DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_status (status),
    INDEX idx_type_name (type_name)
);

-- Activity categories table
CREATE TABLE activity_categories (
    id INT PRIMARY KEY AUTO_INCREMENT,
    category_name VARCHAR(255) NOT NULL,
    description TEXT,
    status TINYINT(1) DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);
```

## Integration Examples

### Expense Claim Integration
```php
// Get activity types for specific department
class ExpenseClaimController
{
    public function getActivityTypesForDepartment($departmentId)
    {
        $activityTypes = ActivityType::where('status', 1)
            ->where(function($query) use ($departmentId) {
                $query->whereRaw("FIND_IN_SET(?, department_id)", [$departmentId])
                      ->orWhereNull('department_id');
            })
            ->get(['id', 'type_name', 'description']);
            
        return $activityTypes;
    }
}
```

### Department-Based Filtering
```php
// Filter activity types by user's department
class ActivityTypeService
{
    public function getActivityTypesForUser($userId)
    {
        $user = User::with('employee.department')->find($userId);
        $userDepartmentId = $user->employee->department->id;
        
        return ActivityType::where('status', 1)
            ->where(function($query) use ($userDepartmentId) {
                $query->whereRaw("FIND_IN_SET(?, department_id)", [$userDepartmentId])
                      ->orWhereNull('department_id');
            })
            ->get();
    }
}
```

## Error Handling and Logging

### Exception Management
```php
try {
    // Activity type operations
    $activityType = ActivityType::create($data);
} catch (\Exception $e) {
    Log::error('Activity Type Controller Error', [
        'method' => 'store',
        'data' => $data,
        'error' => $e->getMessage(),
        'trace' => $e->getTraceAsString()
    ]);
    
    return $this->jsonError('Failed to create activity type: ' . $e->getMessage(), 500);
}
```

### Monitoring and Alerts
- **Activity Type Usage**: Monitor activity type usage in expense claims
- **Department Association**: Track department-activity type relationships
- **Performance Monitoring**: Monitor query performance and response times

This ActivityTypeController provides comprehensive activity type management functionality, enabling efficient categorization and organization of expense claim activities while maintaining security, performance, and integration capabilities throughout the expense management system.