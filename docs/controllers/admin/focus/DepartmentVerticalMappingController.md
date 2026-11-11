# DepartmentVerticalMappingController

## Overview
The DepartmentVerticalMappingController manages the mapping between base departments and their vertical-specific department names for Focus ERP integration. This controller handles the complex department structure where departments can have different names based on their business vertical or division.

## Namespace
`App\Http\Controllers\admin\focus`

## Dependencies
- `App\Models\focus\DepartmentVerticalMapping`
- `Spatie\Permission\Models\Permission`
- `Spatie\Permission\Models\Role`

## Routes

### Resource Routes
- **GET** `/department-vertical-mappings` - Display listing (index)
- **POST** `/department-vertical-mappings` - Store new mapping (store)
- **GET** `/department-vertical-mappings/{id}/edit` - Show edit form (edit)
- **PUT/PATCH** `/department-vertical-mappings/{id}` - Update mapping (update)
- **DELETE** `/department-vertical-mappings/{id}` - Delete mapping (destroy)

**Note**: `show` and `create` routes are excluded from the resource.

## Methods

### index()
**Purpose**: Displays the department vertical mapping listing page with filtering options.

**Parameters**: 
- `status` (query parameter): Filter by status ('active', 'inactive', or 'all')

**Returns**: View with department vertical mappings list

**Features**:
- Status-based filtering (active/inactive)
- Ordered by base department, then by vertical name
- Passes status and mappings to view

**View**: `admin.focus.fc_department_vertical_mapping`

### store(Request $request)
**Purpose**: Creates a new department vertical mapping entry.

**Parameters**:
- `base_department` (required, string, max:50): Base department name
- `vertical_name` (required, string, max:100): Business vertical/division name
- `mapped_department_name` (required, string, max:100): Final mapped department name
- `is_active` (boolean): Active status

**Returns**: JSON success response with created mapping

**Features**:
- Automatic permission creation for the new mapping
- Assigns view permission to Super Admin role
- Supports complex department-vertical combinations

**Validation Rules**:
- `base_department`: Required, string, maximum 50 characters
- `vertical_name`: Required, string, maximum 100 characters
- `mapped_department_name`: Required, string, maximum 100 characters
- `is_active`: Boolean (defaults to 1 if not provided)

### edit(string $id)
**Purpose**: Retrieves a specific department vertical mapping for editing.

**Parameters**:
- `$id`: Mapping ID

**Returns**: JSON success response with mapping data

**Error Handling**: Returns 404 if mapping not found

### update(Request $request, string $id)
**Purpose**: Updates an existing department vertical mapping.

**Parameters**:
- `$id`: Mapping ID
- Same validation rules as store method

**Returns**: JSON success response with updated mapping

**Validation Rules**: Same as store method

### destroy(string $id)
**Purpose**: Deletes a department vertical mapping.

**Parameters**:
- `$id`: Mapping ID

**Returns**: JSON success response

**Error Handling**: Returns 404 if mapping not found

## Model Relationships
- Uses `DepartmentVerticalMapping` model from `App\Models\focus` namespace
- Integrates with Spatie Permission system for access control

## Permission System
- Automatically creates permissions for each mapping:
  - `view departmentverticalmapping {id}`
  - `update departmentverticalmapping {id}`
  - `delete departmentverticalmapping {id}`
- Assigns view permission to Super Admin role by default

## Focus Integration
This controller manages complex department-vertical mapping for Focus ERP:
- **Base Department**: Core department classification
- **Vertical Name**: Business vertical, division, or specialized unit
- **Mapped Department Name**: Final department name used in Focus ERP

## Use Cases
This mapping system supports scenarios like:
- Sales department in different regions (Sales + North Region = Sales North)
- Technical departments in different product lines (Engineering + Product A = Engineering Product A)
- Support departments serving different verticals (HR + Manufacturing = HR Manufacturing)

## Sorting and Organization
- Primary sort by base department for logical grouping
- Secondary sort by vertical name for easy navigation
- Maintains hierarchical view of department-vertical relationships

## Status Management
- Supports active/inactive status filtering
- Default status is active (1)
- Inactive mappings are preserved but not used in processing

## Error Handling
- Uses Laravel's built-in validation
- Returns appropriate HTTP status codes
- Provides descriptive error messages
- Uses `findOrFail` for automatic 404 responses

## Response Format
All methods return JSON responses using the `jsonSuccess` helper method with:
- Data payload
- Success message
- Appropriate HTTP status codes