# StateCodeMappingController

## Overview
The StateCodeMappingController manages the mapping between state names and their corresponding state codes for Focus ERP integration. This controller handles the standardization of state information across different systems and ensures consistent state code usage.

## Namespace
`App\Http\Controllers\admin\focus`

## Dependencies
- `App\Models\focus\StateCodeMapping`
- `App\Models\Permission` (custom Permission model)
- `App\Models\Roles` (custom Roles model)

## Routes

### Resource Routes
- **GET** `/state-code-mappings` - Display listing (index)
- **POST** `/state-code-mappings` - Store new mapping (store)
- **GET** `/state-code-mappings/{id}/edit` - Show edit form (edit)
- **PUT/PATCH** `/state-code-mappings/{id}` - Update mapping (update)
- **DELETE** `/state-code-mappings/{id}` - Delete mapping (destroy)

**Note**: `show` and `create` routes are excluded from the resource.

## Methods

### index()
**Purpose**: Displays the state code mapping listing page with filtering options.

**Parameters**: 
- `status` (query parameter): Filter by status ('active', 'inactive', or 'all')

**Returns**: View with state code mappings list

**Features**:
- Status-based filtering (active/inactive)
- Ordered by state name alphabetically
- Passes status and mappings to view

**View**: `admin.focus.fc_state_code_mapping`

### store(Request $request)
**Purpose**: Creates a new state code mapping entry.

**Parameters**:
- `state_name` (required, string, max:100, unique): Full state name
- `state_code` (required, string, max:2): Two-character state code
- `is_active` (boolean): Active status

**Returns**: JSON success response with created mapping

**Features**:
- Automatic permission creation for the new mapping
- Assigns view permission to Super Admin role
- Ensures uniqueness of state names
- Automatically sets created_at timestamp

**Validation Rules**:
- `state_name`: Required, string, max 100 chars, unique in table
- `state_code`: Required, string, max 2 characters (typically state abbreviation)
- `is_active`: Boolean (defaults to 1 if not provided)

### edit(string $id)
**Purpose**: Retrieves a specific state code mapping for editing.

**Parameters**:
- `$id`: Mapping ID

**Returns**: JSON success response with mapping data

**Error Handling**: Returns 404 if mapping not found

### update(Request $request, string $id)
**Purpose**: Updates an existing state code mapping.

**Parameters**:
- `$id`: Mapping ID
- Same validation rules as store method (with unique rule exception for current record)

**Returns**: JSON success response with updated mapping

**Validation Rules**: Same as store method, but excludes current record from uniqueness check

### destroy(string $id)
**Purpose**: Deletes a state code mapping.

**Parameters**:
- `$id`: Mapping ID

**Returns**: JSON success response

**Error Handling**: Returns 404 if mapping not found

## Model Relationships
- Uses `StateCodeMapping` model from `App\Models\focus` namespace
- Uses custom Permission and Roles models (not Spatie)
- Integrates with custom permission system for access control

## Permission System
- Automatically creates permissions for each mapping:
  - `view statecodemapping {id}`
  - `update statecodemapping {id}`
  - `delete statecodemapping {id}`
- Assigns view permission to Super Admin role by default

## Focus Integration
This controller manages state standardization for Focus ERP:
- **State Name**: Full state name (e.g., "California", "Texas")
- **State Code**: Standardized 2-character code (e.g., "CA", "TX")

## Data Standards
- State codes are typically 2-character abbreviations
- State names must be unique to prevent conflicts
- Supports both active and inactive states for historical data

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

## Note on Dependencies
This controller uses custom Permission and Roles models instead of Spatie Permission package, indicating a custom authorization system implementation.