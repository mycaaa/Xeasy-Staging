# DepartmentCodeMappingController

## Overview
The DepartmentCodeMappingController manages the mapping between original department names and their corresponding Focus ERP department codes and names. This controller handles the translation layer between the expense system's department structure and Focus ERP's department classification.

## Namespace
`App\Http\Controllers\admin\focus`

## Dependencies
- `App\Models\focus\DepartmentCodeMapping`
- `Spatie\Permission\Models\Permission`
- `Spatie\Permission\Models\Role`

## Routes

### Resource Routes
- **GET** `/department-code-mappings` - Display listing (index)
- **POST** `/department-code-mappings` - Store new mapping (store)
- **GET** `/department-code-mappings/{id}/edit` - Show edit form (edit)
- **PUT/PATCH** `/department-code-mappings/{id}` - Update mapping (update)
- **DELETE** `/department-code-mappings/{id}` - Delete mapping (destroy)

**Note**: `show` and `create` routes are excluded from the resource.

## Methods

### index()
**Purpose**: Displays the department code mapping listing page with filtering options.

**Parameters**: 
- `status` (query parameter): Filter by status ('active', 'inactive', or 'all')

**Returns**: View with department code mappings list

**Features**:
- Status-based filtering (active/inactive)
- Ordered by original department name
- Passes status and mappings to view

**View**: `admin.focus.fc_department_code_mapping`

### store(Request $request)
**Purpose**: Creates a new department code mapping entry.

**Parameters**:
- `original_department_name` (required, string, max:100, unique): Original department name
- `mapped_department_code` (required, string, max:50): Focus department code
- `mapped_department_name` (required, string, max:100): Focus department name
- `is_active` (boolean): Active status

**Returns**: JSON success response with created mapping

**Features**:
- Automatic permission creation for the new mapping
- Assigns view permission to Super Admin role
- Ensures uniqueness of original department names

**Validation Rules**:
- `original_department_name`: Required, string, max 100 chars, unique in table
- `mapped_department_code`: Required, string, max 50 characters
- `mapped_department_name`: Required, string, max 100 characters
- `is_active`: Boolean (defaults to 1 if not provided)

### edit(string $id)
**Purpose**: Retrieves a specific department code mapping for editing.

**Parameters**:
- `$id`: Mapping ID

**Returns**: JSON success response with mapping data

**Error Handling**: Returns 404 if mapping not found

### update(Request $request, string $id)
**Purpose**: Updates an existing department code mapping.

**Parameters**:
- `$id`: Mapping ID
- Same validation rules as store method (with unique rule exception for current record)

**Returns**: JSON success response with updated mapping

**Validation Rules**: Same as store method, but excludes current record from uniqueness check

### destroy(string $id)
**Purpose**: Deletes a department code mapping.

**Parameters**:
- `$id`: Mapping ID

**Returns**: JSON success response

**Error Handling**: Returns 404 if mapping not found

## Model Relationships
- Uses `DepartmentCodeMapping` model from `App\Models\focus` namespace
- Integrates with Spatie Permission system for access control

## Permission System
- Automatically creates permissions for each mapping:
  - `view departmentcodemapping {id}`
  - `update departmentcodemapping {id}`
  - `delete departmentcodemapping {id}`
- Assigns view permission to Super Admin role by default

## Focus Integration
This controller manages department mapping for Focus ERP integration:
- **Original Department Name**: Department name as it appears in the expense system
- **Mapped Department Code**: Corresponding department code in Focus ERP
- **Mapped Department Name**: Corresponding department name in Focus ERP

## Data Integrity
- Enforces uniqueness on original department names
- Prevents duplicate mappings for the same department
- Maintains referential integrity through validation

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