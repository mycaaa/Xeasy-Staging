# 🔍 Admin FilterController Documentation

The Admin FilterController provides dynamic filtering capabilities for organizational hierarchy data in the Xeasy Laravel expense management system, enabling cascading dropdown functionality and hierarchical data relationships.

## 📋 Overview

**Location**: `app/Http/Controllers/admin/FilterController.php`

**Purpose**: Handles dynamic filtering and cascading dropdown functionality for organizational hierarchy including functions, verticals, departments, sub-departments, and employees with complex relationship mappings.

**Middleware**: Inherits from base Controller (typically includes auth and admin middleware)

**Key Features**:
- Cascading dropdown functionality
- Multi-level organizational hierarchy filtering
- Dynamic employee filtering by organizational units
- Complex relationship mapping between organizational entities
- Session-based company context filtering
- Error handling with graceful degradation

## 📊 Public Methods

### 1. Employee Filtering by Department

#### `getEmployeesByDepartment(Request $request): JsonResponse`

**Route**: `POST /admin/filters/employees-by-department`
**Permission**: Admin access required

**Purpose**: Retrieves employees filtered by department and sub-department selections with company context.

**Request Parameters**:
| Parameter | Type | Required | Description |
|-----------|------|----------|-------------|
| department_ids | array | No | Array of department IDs to filter by |
| sub_department_ids | array | No | Array of sub-department IDs to filter by |

**Request Example**:
```json
{
    "department_ids": [1, 2, 3],
    "sub_department_ids": [5, 8, 12]
}
```

**Response Structure**:
```json
{
    "success": true,
    "message": "Employees loaded successfully.",
    "data": [
        {
            "EmployeeID": 12345,
            "EmpCode": "EMP001",
            "Fname": "John",
            "Sname": "M",
            "Lname": "Doe",
            "EmpStatus": "A"
        },
        {
            "EmployeeID": 12346,
            "EmpCode": "EMP002",
            "Fname": "Jane",
            "Sname": null,
            "Lname": "Smith",
            "EmpStatus": "A"
        }
    ]
}
```

**Business Logic**:
1. **Company Context**: Automatically filters by session company_id
2. **Multi-Level Filtering**: Supports both department and sub-department filtering
3. **Optional Filters**: Both filter parameters are optional
4. **HRIMS Integration**: Queries HRIMS database for employee data
5. **Status Inclusion**: Returns all employee statuses for comprehensive filtering

**Query Logic**:
```sql
SELECT 
    hrm_employee.EmployeeID,
    hrm_employee.EmpCode,
    hrm_employee.Fname,
    hrm_employee.Sname,
    hrm_employee.Lname,
    hrm_employee.EmpStatus
FROM hrm_employee
JOIN hrm_employee_general ON hrm_employee.EmployeeID = hrm_employee_general.EmployeeID
WHERE hrm_employee.CompanyId = ?
  AND (hrm_employee_general.DepartmentId IN (?) OR ? IS EMPTY)
  AND (hrm_employee_general.SubDepartmentId IN (?) OR ? IS EMPTY)
```

**Error Response**:
```json
{
    "success": false,
    "message": "Failed to load employees."
}
```

---

### 2. Vertical Filtering by Function

#### `getVerticalsByFunction(Request $request): JsonResponse`

**Route**: `POST /admin/filters/verticals-by-function`
**Permission**: Admin access required

**Purpose**: Retrieves verticals filtered by selected organizational functions through mapping relationships.

**Request Parameters**:
| Parameter | Type | Required | Description |
|-----------|------|----------|-------------|
| function_ids | array | No | Array of function IDs to filter by |

**Request Example**:
```json
{
    "function_ids": [1, 3, 5]
}
```

**Response Structure**:
```json
{
    "success": true,
    "message": "Verticals loaded successfully.",
    "data": [
        {
            "id": 1,
            "vertical_name": "Sales & Marketing"
        },
        {
            "id": 2,
            "vertical_name": "Operations"
        },
        {
            "id": 3,
            "vertical_name": "Finance & Accounts"
        }
    ]
}
```

**Business Logic**:
1. **Relationship Mapping**: Uses core_vertical_function_mapping for relationships
2. **Optional Filtering**: Returns all verticals if no function IDs provided
3. **Deduplication**: Groups by vertical ID to prevent duplicates
4. **HRIMS Integration**: Queries HRIMS organizational structure

**Complex Relationship Query**:
```sql
SELECT DISTINCT v.id, v.vertical_name
FROM core_verticals AS v
JOIN core_vertical_function_mapping AS vfm ON v.id = vfm.vertical_id
WHERE vfm.org_function_id IN (?)
GROUP BY v.id, v.vertical_name
```

---

### 3. Department Filtering by Vertical

#### `getDepartmentsByVertical(Request $request): JsonResponse`

**Route**: `POST /admin/filters/departments-by-vertical`
**Permission**: Admin access required

**Purpose**: Retrieves departments filtered by selected verticals through complex multi-level mapping relationships.

**Request Parameters**:
| Parameter | Type | Required | Description |
|-----------|------|----------|-------------|
| vertical_ids | array | No | Array of vertical IDs to filter by |

**Request Example**:
```json
{
    "vertical_ids": [1, 2, 4]
}
```

**Response Structure**:
```json
{
    "success": true,
    "message": "Departments loaded successfully.",
    "data": [
        {
            "id": 1,
            "department_name": "Sales Department"
        },
        {
            "id": 2,
            "department_name": "Marketing Department"
        },
        {
            "id": 5,
            "department_name": "Operations Department"
        }
    ]
}
```

**Business Logic**:
1. **Multi-Level Mapping**: Uses complex relationship chain through multiple mapping tables
2. **Subquery Integration**: Uses subquery to resolve vertical-to-department relationships
3. **Deduplication**: Groups results to prevent duplicate departments
4. **Optional Filtering**: Returns all departments if no vertical IDs provided

**Complex Multi-Level Query**:
```sql
SELECT DISTINCT d.id, d.department_name
FROM core_departments AS d
JOIN core_fun_vertical_dept_mapping AS vdm ON d.id = vdm.department_id
WHERE vdm.function_vertical_id IN (
    SELECT vfm.id
    FROM core_function_vertical_mapping AS vfm
    WHERE vfm.vertical_id IN (?)
)
GROUP BY d.id, d.department_name
```

**Relationship Chain**:
1. **Verticals** → **VerticalFunctionMapping** → **FunctionVerticalDeptMapping** → **Departments**

---

### 4. Sub-Department Filtering by Department

#### `getSubDepartmentsByDepartment(Request $request): JsonResponse`

**Route**: `POST /admin/filters/sub-departments-by-department`
**Permission**: Admin access required

**Purpose**: Retrieves sub-departments filtered by selected departments through mapping relationships.

**Request Parameters**:
| Parameter | Type | Required | Description |
|-----------|------|----------|-------------|
| department_ids | array | No | Array of department IDs to filter by |

**Request Example**:
```json
{
    "department_ids": [1, 3, 7]
}
```

**Response Structure**:
```json
{
    "success": true,
    "message": "Sub-Departments loaded successfully.",
    "data": [
        {
            "id": 1,
            "sub_department_name": "Sales North"
        },
        {
            "id": 2,
            "sub_department_name": "Sales South"
        },
        {
            "id": 5,
            "sub_department_name": "Marketing Digital"
        }
    ]
}
```

**Business Logic**:
1. **Mapping Relationships**: Uses sub-department mapping tables for relationships
2. **Subquery Resolution**: Resolves department-to-sub-department relationships
3. **Deduplication**: Groups results to prevent duplicates
4. **Optional Filtering**: Returns all sub-departments if no department IDs provided

**Relationship Query**:
```sql
SELECT DISTINCT sd.id, sd.sub_department_name
FROM core_sub_department_master AS sd
JOIN core_sub_department_mapping AS sdm ON sd.id = sdm.sub_department_id
WHERE sdm.fun_vertical_dept_id IN (
    SELECT vdm.id
    FROM core_vertical_department_mapping AS vdm
    WHERE vdm.department_id IN (?)
)
GROUP BY sd.id, sd.sub_department_name
```

**Relationship Chain**:
1. **Departments** → **VerticalDepartmentMapping** → **SubDepartmentMapping** → **SubDepartments**

---

## 🔧 Implementation Details

### Database Connection Management

#### HRIMS Integration
```php
// All queries use HRIMS connection
$query = DB::connection('hrims')->table('table_name');
```

**Connection Features**:
- **Dedicated Connection**: Uses separate HRIMS database connection
- **Organizational Data**: Accesses master organizational data
- **Company Context**: Filters by session company_id where applicable

### Complex Relationship Mapping

#### Multi-Level Organizational Hierarchy
```
Functions → Verticals → Departments → Sub-Departments → Employees
```

**Mapping Tables**:
- `core_vertical_function_mapping` - Functions to Verticals
- `core_function_vertical_mapping` - Alternative function-vertical mapping
- `core_fun_vertical_dept_mapping` - Verticals to Departments
- `core_vertical_department_mapping` - Department relationships
- `core_sub_department_mapping` - Department to Sub-Department mapping

### Error Handling Pattern

#### Consistent Error Response
```php
try {
    // Query logic
    return $this->jsonSuccess($data, 'Success message');
} catch (\Exception $e) {
    return $this->jsonError('Failed to load data.');
}
```

**Error Handling Features**:
- **Exception Catching**: Catches all database and processing exceptions
- **Graceful Degradation**: Returns consistent error format
- **User-Friendly Messages**: Provides meaningful error messages
- **Logging**: Should log detailed errors for debugging (not implemented)

---

## 🎯 Business Logic Patterns

### Cascading Dropdown Implementation

#### Hierarchical Data Flow
1. **Function Selection** → Filters available Verticals
2. **Vertical Selection** → Filters available Departments  
3. **Department Selection** → Filters available Sub-Departments
4. **Department/Sub-Department Selection** → Filters available Employees

### Optional Filtering Logic

#### Flexible Filter Application
```php
// Optional department filtering
if (!empty($departmentIds)) {
    $query->whereIn('hrm_employee_general.DepartmentId', $departmentIds);
}

// Optional sub-department filtering
if (!empty($subDepartmentIds)) {
    $query->whereIn('hrm_employee_general.SubDepartmentId', $subDepartmentIds);
}
```

**Features**:
- **Conditional Filtering**: Applies filters only when provided
- **Multiple Selections**: Supports multiple ID selections
- **Cumulative Filtering**: Combines multiple filter criteria

### Company Context Integration

#### Session-Based Company Filtering
```php
// Automatic company context filtering
->where('hrm_employee.CompanyId', session('company_id'))
```

**Benefits**:
- **Multi-Tenant Support**: Isolates data by company
- **Automatic Context**: Uses session company context
- **Security**: Prevents cross-company data access

---

## 🚀 Performance Considerations

### Query Optimization

#### Efficient Relationship Queries
```php
// Optimized joins and subqueries
$query->join('core_vertical_function_mapping as vfm', 'v.id', '=', 'vfm.vertical_id')
    ->whereIn('vfm.org_function_id', $functionIds)
    ->groupBy('v.id', 'v.vertical_name');
```

**Optimization Features**:
- **Selective Joins**: Only joins necessary tables
- **Indexed Lookups**: Uses primary and foreign key indexes
- **Grouped Results**: Prevents duplicate data transfer
- **Subquery Optimization**: Efficient subquery usage

### Caching Opportunities

Consider caching for:
- **Organizational Structure**: Cache for 2 hours (very stable)
- **Employee Lists**: Cache for 30 minutes (moderately stable)
- **Mapping Relationships**: Cache for 4 hours (rarely changes)

### Memory Management

#### Efficient Data Transfer
- **Selective Columns**: Only selects required columns
- **Grouped Results**: Prevents duplicate data
- **Minimal Payload**: Returns only essential data

---

## 🔐 Security Considerations

### Input Validation

#### Array Parameter Validation
```php
$departmentIds = $request->input('department_ids', []);
$subDepartmentIds = $request->input('sub_department_ids', []);
```

**Security Features**:
- **Default Values**: Provides safe default empty arrays
- **Type Safety**: Ensures array type for parameters
- **SQL Injection Prevention**: Uses parameterized queries

### Access Control

#### Company Data Isolation
- **Session Context**: Uses session company_id for filtering
- **Data Isolation**: Prevents cross-company data access
- **Authentication Required**: All methods require authentication

### Data Privacy

#### Minimal Data Exposure
- **Essential Fields Only**: Returns only necessary employee data
- **Status Inclusion**: Includes status for proper filtering
- **No Sensitive Data**: Avoids exposing sensitive employee information

---

## 📈 Usage Examples

### Frontend Cascading Dropdowns

```javascript
// Cascading Dropdown Manager
class CascadingDropdowns {
    constructor() {
        this.functions = [];
        this.verticals = [];
        this.departments = [];
        this.subDepartments = [];
        this.employees = [];
        
        this.initializeEventHandlers();
    }

    initializeEventHandlers() {
        // Function change handler
        document.getElementById('function-select').addEventListener('change', (e) => {
            const selectedFunctions = Array.from(e.target.selectedOptions).map(option => option.value);
            this.loadVerticalsByFunction(selectedFunctions);
        });

        // Vertical change handler
        document.getElementById('vertical-select').addEventListener('change', (e) => {
            const selectedVerticals = Array.from(e.target.selectedOptions).map(option => option.value);
            this.loadDepartmentsByVertical(selectedVerticals);
        });

        // Department change handler
        document.getElementById('department-select').addEventListener('change', (e) => {
            const selectedDepartments = Array.from(e.target.selectedOptions).map(option => option.value);
            this.loadSubDepartmentsByDepartment(selectedDepartments);
            this.loadEmployeesByDepartment(selectedDepartments, []);
        });

        // Sub-department change handler
        document.getElementById('sub-department-select').addEventListener('change', (e) => {
            const selectedSubDepartments = Array.from(e.target.selectedOptions).map(option => option.value);
            const selectedDepartments = Array.from(document.getElementById('department-select').selectedOptions)
                .map(option => option.value);
            this.loadEmployeesByDepartment(selectedDepartments, selectedSubDepartments);
        });
    }

    async loadVerticalsByFunction(functionIds) {
        try {
            const response = await fetch('/admin/filters/verticals-by-function', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
                },
                body: JSON.stringify({ function_ids: functionIds })
            });

            const result = await response.json();

            if (result.success) {
                this.verticals = result.data;
                this.populateVerticalDropdown();
                this.clearDependentDropdowns(['department', 'sub-department', 'employee']);
            } else {
                console.error('Failed to load verticals:', result.message);
            }
        } catch (error) {
            console.error('Error loading verticals:', error);
        }
    }

    async loadDepartmentsByVertical(verticalIds) {
        try {
            const response = await fetch('/admin/filters/departments-by-vertical', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
                },
                body: JSON.stringify({ vertical_ids: verticalIds })
            });

            const result = await response.json();

            if (result.success) {
                this.departments = result.data;
                this.populateDepartmentDropdown();
                this.clearDependentDropdowns(['sub-department', 'employee']);
            } else {
                console.error('Failed to load departments:', result.message);
            }
        } catch (error) {
            console.error('Error loading departments:', error);
        }
    }

    async loadSubDepartmentsByDepartment(departmentIds) {
        try {
            const response = await fetch('/admin/filters/sub-departments-by-department', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
                },
                body: JSON.stringify({ department_ids: departmentIds })
            });

            const result = await response.json();

            if (result.success) {
                this.subDepartments = result.data;
                this.populateSubDepartmentDropdown();
            } else {
                console.error('Failed to load sub-departments:', result.message);
            }
        } catch (error) {
            console.error('Error loading sub-departments:', error);
        }
    }

    async loadEmployeesByDepartment(departmentIds, subDepartmentIds = []) {
        try {
            const response = await fetch('/admin/filters/employees-by-department', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
                },
                body: JSON.stringify({ 
                    department_ids: departmentIds,
                    sub_department_ids: subDepartmentIds
                })
            });

            const result = await response.json();

            if (result.success) {
                this.employees = result.data;
                this.populateEmployeeDropdown();
            } else {
                console.error('Failed to load employees:', result.message);
            }
        } catch (error) {
            console.error('Error loading employees:', error);
        }
    }

    populateVerticalDropdown() {
        const select = document.getElementById('vertical-select');
        select.innerHTML = '<option value="">Select Vertical</option>';
        
        this.verticals.forEach(vertical => {
            const option = document.createElement('option');
            option.value = vertical.id;
            option.textContent = vertical.vertical_name;
            select.appendChild(option);
        });
    }

    populateDepartmentDropdown() {
        const select = document.getElementById('department-select');
        select.innerHTML = '<option value="">Select Department</option>';
        
        this.departments.forEach(department => {
            const option = document.createElement('option');
            option.value = department.id;
            option.textContent = department.department_name;
            select.appendChild(option);
        });
    }

    populateSubDepartmentDropdown() {
        const select = document.getElementById('sub-department-select');
        select.innerHTML = '<option value="">Select Sub-Department</option>';
        
        this.subDepartments.forEach(subDepartment => {
            const option = document.createElement('option');
            option.value = subDepartment.id;
            option.textContent = subDepartment.sub_department_name;
            select.appendChild(option);
        });
    }

    populateEmployeeDropdown() {
        const select = document.getElementById('employee-select');
        select.innerHTML = '<option value="">Select Employee</option>';
        
        this.employees.forEach(employee => {
            const option = document.createElement('option');
            option.value = employee.EmployeeID;
            option.textContent = `${employee.EmpCode} - ${employee.Fname} ${employee.Sname || ''} ${employee.Lname}`.trim();
            select.appendChild(option);
        });
    }

    clearDependentDropdowns(dropdownTypes) {
        dropdownTypes.forEach(type => {
            const select = document.getElementById(`${type}-select`);
            if (select) {
                select.innerHTML = `<option value="">Select ${type.charAt(0).toUpperCase() + type.slice(1)}</option>`;
            }
        });

        // Clear data arrays
        if (dropdownTypes.includes('department')) {
            this.departments = [];
        }
        if (dropdownTypes.includes('sub-department')) {
            this.subDepartments = [];
        }
        if (dropdownTypes.includes('employee')) {
            this.employees = [];
        }
    }

    // Utility method to get selected values
    getSelectedValues() {
        return {
            functions: Array.from(document.getElementById('function-select').selectedOptions)
                .map(option => option.value),
            verticals: Array.from(document.getElementById('vertical-select').selectedOptions)
                .map(option => option.value),
            departments: Array.from(document.getElementById('department-select').selectedOptions)
                .map(option => option.value),
            subDepartments: Array.from(document.getElementById('sub-department-select').selectedOptions)
                .map(option => option.value),
            employees: Array.from(document.getElementById('employee-select').selectedOptions)
                .map(option => option.value)
        };
    }

    // Reset all dropdowns
    resetAll() {
        document.getElementById('function-select').selectedIndex = 0;
        this.clearDependentDropdowns(['vertical', 'department', 'sub-department', 'employee']);
        this.verticals = [];
        this.departments = [];
        this.subDepartments = [];
        this.employees = [];
    }
}

// Initialize cascading dropdowns
const cascadingDropdowns = new CascadingDropdowns();

// Example usage in forms
document.getElementById('filter-form').addEventListener('submit', (e) => {
    e.preventDefault();
    
    const selectedValues = cascadingDropdowns.getSelectedValues();
    console.log('Selected values:', selectedValues);
    
    // Use selected values for filtering or processing
    processFilteredData(selectedValues);
});

function processFilteredData(filters) {
    // Process the filtered data based on selections
    console.log('Processing data with filters:', filters);
    
    // Example: Load reports based on selected filters
    loadReportsWithFilters(filters);
}
```

### Advanced Filter Integration

```javascript
// Advanced Filter Manager with Multiple Filter Types
class AdvancedFilterManager {
    constructor() {
        this.filterChains = {
            organizational: new CascadingDropdowns(),
            temporal: new TemporalFilters(),
            status: new StatusFilters()
        };
        
        this.activeFilters = {};
        this.initializeAdvancedFilters();
    }

    initializeAdvancedFilters() {
        // Combine multiple filter types
        this.setupFilterCombinations();
        this.setupFilterPresets();
        this.setupFilterSaving();
    }

    async applyMultipleFilters(filterCriteria) {
        try {
            // Apply organizational filters
            if (filterCriteria.organizational) {
                await this.applyOrganizationalFilters(filterCriteria.organizational);
            }

            // Apply temporal filters
            if (filterCriteria.temporal) {
                this.applyTemporalFilters(filterCriteria.temporal);
            }

            // Apply status filters
            if (filterCriteria.status) {
                this.applyStatusFilters(filterCriteria.status);
            }

            // Combine and execute final filter
            const combinedFilters = this.combineAllFilters();
            return await this.executeFilterQuery(combinedFilters);

        } catch (error) {
            console.error('Error applying multiple filters:', error);
            throw error;
        }
    }

    async applyOrganizationalFilters(orgFilters) {
        // Use the cascading dropdown functionality
        if (orgFilters.functions && orgFilters.functions.length > 0) {
            await this.filterChains.organizational.loadVerticalsByFunction(orgFilters.functions);
        }

        if (orgFilters.verticals && orgFilters.verticals.length > 0) {
            await this.filterChains.organizational.loadDepartmentsByVertical(orgFilters.verticals);
        }

        if (orgFilters.departments && orgFilters.departments.length > 0) {
            await this.filterChains.organizational.loadSubDepartmentsByDepartment(orgFilters.departments);
            await this.filterChains.organizational.loadEmployeesByDepartment(
                orgFilters.departments, 
                orgFilters.subDepartments || []
            );
        }
    }

    combineAllFilters() {
        return {
            organizational: this.filterChains.organizational.getSelectedValues(),
            temporal: this.filterChains.temporal.getSelectedValues(),
            status: this.filterChains.status.getSelectedValues()
        };
    }

    async executeFilterQuery(combinedFilters) {
        // Execute the final filtered query
        const response = await fetch('/admin/reports/filtered-data', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
            },
            body: JSON.stringify(combinedFilters)
        });

        return await response.json();
    }
}
```

---

## 🔗 Integration Points

### HRIMS System Integration

#### Organizational Data Source
- **Master Data**: Accesses organizational master data
- **Employee Information**: Retrieves employee details
- **Relationship Mappings**: Uses complex mapping tables
- **Company Context**: Filters by company boundaries

### Frontend Integration

#### Cascading Dropdown Support
- **Dynamic Loading**: Real-time dropdown population
- **Dependency Management**: Handles dropdown dependencies
- **State Management**: Maintains selection state
- **Error Handling**: Graceful error handling

### Reporting System Integration

#### Filter Application
- **Report Filtering**: Provides filters for reporting system
- **Data Segmentation**: Enables data segmentation by organizational units
- **Performance Optimization**: Optimizes report queries through filtering

---

The Admin FilterController provides essential filtering capabilities that enable dynamic, hierarchical data relationships throughout the expense management system, serving as the foundation for cascading dropdowns and advanced filtering functionality while maintaining performance and data integrity.