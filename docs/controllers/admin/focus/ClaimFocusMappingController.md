# ClaimFocusMappingController

## Overview
The ClaimFocusMappingController manages the mapping between expense claims and Focus ERP system entries. It handles CRUD operations for claim-to-focus mappings, including ledger codes and focus names for integration with the Focus accounting system.

## Namespace
`App\Http\Controllers\admin\focus`

## Dependencies
- `App\Models\focus\ClaimFocusMapping`
- `Spatie\Permission\Models\Permission`
- `Spatie\Permission\Models\Role`

## Routes

### Resource Routes (Defined in `routes/backend.php`)
- **GET** `/claim-focus-mappings` - Display listing (index)
- **POST** `/claim-focus-mappings` - Store new mapping (store)
- **GET** `/claim-focus-mappings/{id}/edit` - Show edit form (edit)
- **PUT/PATCH** `/claim-focus-mappings/{id}` - Update mapping (update)
- **DELETE** `/claim-focus-mappings/{id}` - Delete mapping (destroy)

**Note**: `show` and `create` routes are excluded from the resource.
**Middleware**: Requires authentication (`auth` middleware)

## Methods

### index()
**Purpose**: Displays the claim focus mapping listing page with filtering options.

**Parameters**: 
- `status` (query parameter): Filter by status ('active', 'inactive', or 'all')

**Returns**: View with claim focus mappings list

**Features**:
- Status-based filtering (active/inactive)
- Ordered by claim name
- Passes status and mappings to view

**View**: `admin.focus.fc_claim_focus_mapping`

### store(Request $request)
**Purpose**: Creates a new claim focus mapping entry.

**Parameters**:
- `claim_name` (required, string, max:100): Name of the claim type
- `wtype` (nullable, string, max:1, in:2,4): Work type identifier
- `focus_name_subl` (required, string, max:50): Focus sub-ledger name
- `focus_name` (required, string, max:100): Focus ledger name
- `focus_ledger_code` (required, string, max:50): Focus ledger code
- `is_active` (boolean): Active status

**Returns**: JSON success response with created mapping

**Features**:
- Automatic permission creation for the new mapping
- Assigns view permission to Super Admin role
- Creates view, update, and delete permissions

**Validation Rules**:
- `claim_name`: Required, string, maximum 100 characters
- `wtype`: Optional, single character, must be '2' or '4'
- `focus_name_subl`: Required, string, maximum 50 characters
- `focus_name`: Required, string, maximum 100 characters
- `focus_ledger_code`: Required, string, maximum 50 characters
- `is_active`: Boolean (defaults to 1 if not provided)

### edit(string $id)
**Purpose**: Retrieves a specific claim focus mapping for editing.

**Parameters**:
- `$id`: Mapping ID

**Returns**: JSON success response with mapping data

**Error Handling**: Returns 404 if mapping not found

### update(Request $request, string $id)
**Purpose**: Updates an existing claim focus mapping.

**Parameters**:
- `$id`: Mapping ID
- Same validation rules as store method

**Returns**: JSON success response with updated mapping

**Validation Rules**: Same as store method

### destroy(string $id)
**Purpose**: Deletes a claim focus mapping.

**Parameters**:
- `$id`: Mapping ID

**Returns**: JSON success response

**Error Handling**: Returns 404 if mapping not found

## Model Relationships
- Uses `ClaimFocusMapping` model from `App\Models\focus` namespace
- Integrates with Spatie Permission system for access control

## Permission System
- Automatically creates permissions for each mapping:
  - `view claimfocusmapping {id}`
  - `update claimfocusmapping {id}`
  - `delete claimfocusmapping {id}`
- Assigns view permission to Super Admin role by default

## Focus Integration
This controller manages the integration between the expense system and Focus ERP:
- **Claim Name**: Maps to expense claim types
- **WType**: Work type classification (2 or 4)
- **Focus Name Subl**: Sub-ledger classification in Focus
- **Focus Name**: Main ledger name in Focus
- **Focus Ledger Code**: Unique identifier for Focus ledger entries

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