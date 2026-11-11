# StateFieldPromotionMappingController

## Overview
The StateFieldPromotionMappingController manages the mapping between states and their corresponding field promotion configurations in Focus ERP. This controller handles state-specific promotional activities and their integration with Focus ledger codes for accounting purposes.

## Namespace
`App\Http\Controllers\admin\focus`

## Dependencies
- `App\Models\focus\StateFieldPromotionMapping`
- `Spatie\Permission\Models\Permission`
- `Spatie\Permission\Models\Role`

## Routes

### Resource Routes
- **GET** `/state-field-promotion-mappings` - Display listing (index)
- **POST** `/state-field-promotion-mappings` - Store new mapping (store)
- **GET** `/state-field-promotion-mappings/{id}/edit` - Show edit form (edit)
- **PUT/PATCH** `/state-field-promotion-mappings/{id}` - Update mapping (update)
- **DELETE** `/state-field-promotion-mappings/{id}` - Delete mapping (destroy)

**Note**: `show` and `create` routes are excluded from the resource.

## Methods

### index()
**Purpose**: Displays the state field promotion mapping listing page with filtering options.

**Parameters**: 
- `status` (query parameter): Filter by status ('active', 'inactive', or 'all')

**Returns**: View with state field promotion mappings list

**Features**:
- Status-based filtering (active/inactive)
- Ordered by state name alphabetically
- Passes status and mappings to view

**View**: `admin.focus.fc_state_field_promotion_mapping`

### store(Request $request)
**Purpose**: Creates a new state field promotion mapping entry.

**Parameters**:
- `state_name` (required, string, max:100, unique): State name
- `focus_name` (required, string, max:100): Focus ledger name for promotions
- `focus_ledger_code` (required, string, max:50): Focus ledger code
- `is_active` (boolean): Active status

**Returns**: JSON success response with created mapping

**Features**:
- Automatic permission creation for the new mapping
- Assigns view permission to Super Admin role
- Converts state name to lowercase for consistency
- Ensures uniqueness of state names

**Validation Rules**:
- `state_name`: Required, string, max 100 chars, unique in table
- `focus_name`: Required, string, max 100 characters
- `focus_ledger_code`: Required, string, max 50 characters
- `is_active`: Boolean (defaults to 1 if not provided)

### edit(string $id)
**Purpose**: Retrieves a specific state field promotion mapping for editing.

**Parameters**:
- `$id`: Mapping ID

**Returns**: JSON success response with mapping data

**Error Handling**: Returns 404 if mapping not found

### update(Request $request, string $id)
**Purpose**: Updates an existing state field promotion mapping.

**Parameters**:
- `$id`: Mapping ID
- Same validation rules as store method (with unique rule exception for current record)

**Returns**: JSON success response with updated mapping

**Features**:
- Converts state name to lowercase for consistency
- Excludes current record from uniqueness validation

**Validation Rules**: Same as store method, but excludes current record from uniqueness check

### destroy(string $id)
**Purpose**: Deletes a state field promotion mapping.

**Parameters**:
- `$id`: Mapping ID

**Returns**: JSON success response

**Error Handling**: Returns 404 if mapping not found

## Model Relationships
- Uses `StateFieldPromotionMapping` model from `App\Models\focus` namespace
- Integrates with Spatie Permission system for access control

## Permission System
- Automatically creates permissions for each mapping:
  - `view statefieldpromotionmapping {id}`
  - `update statefieldpromotionmapping {id}`
  - `delete statefieldpromotionmapping {id}`
- Assigns view permission to Super Admin role by default

## Focus Integration
This controller manages state-specific field promotion accounting for Focus ERP:
- **State Name**: Geographic state for promotional activities (stored in lowercase)
- **Focus Name**: Descriptive name for the promotion ledger in Focus
- **Focus Ledger Code**: Unique accounting code for promotional expenses

## Data Standardization
- State names are automatically converted to lowercase for consistency
- Ensures uniform data storage and retrieval
- Prevents case-sensitive duplicates

## Use Cases
This mapping system supports:
- State-specific promotional campaigns
- Regional marketing expense tracking
- Field promotion budget allocation
- State-wise promotional expense reporting

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