# SystemConfigurationController

## Overview
The SystemConfigurationController manages system-wide configuration settings for the Focus ERP integration. This controller provides a centralized way to store, retrieve, and manage application configuration parameters with type safety and validation.

## Namespace
`App\Http\Controllers\admin\focus`

## Dependencies
- `App\Models\focus\SystemConfiguration`
- `Spatie\Permission\Models\Permission`
- `Spatie\Permission\Models\Role`

## Routes

### Resource Routes
- **GET** `/system-configurations` - Display listing (index)
- **POST** `/system-configurations` - Store new configuration (store)
- **GET** `/system-configurations/{id}/edit` - Show edit form (edit)
- **PUT/PATCH** `/system-configurations/{id}` - Update configuration (update)
- **DELETE** `/system-configurations/{id}` - Delete configuration (destroy)

**Note**: `show` and `create` routes are excluded from the resource.

## Methods

### index()
**Purpose**: Displays the system configuration listing page with filtering options.

**Parameters**: 
- `status` (query parameter): Filter by status ('active', 'inactive', or 'all')

**Returns**: View with system configurations list

**Features**:
- Status-based filtering (active/inactive)
- Ordered by configuration key alphabetically
- Passes status and configurations to view

**View**: `admin.focus.system_configuration`

### store(Request $request)
**Purpose**: Creates a new system configuration entry.

**Parameters**:
- `config_key` (required, string, max:100, unique): Configuration key identifier
- `config_value` (required, string, max:255): Configuration value
- `config_type` (required, string, in:string,integer,boolean,url): Data type
- `description` (nullable, string): Configuration description
- `is_active` (boolean): Active status

**Returns**: JSON success response with created configuration

**Features**:
- Automatic permission creation for the new configuration
- Assigns view permission to Super Admin role
- Ensures uniqueness of configuration keys
- Supports multiple data types

**Validation Rules**:
- `config_key`: Required, string, max 100 chars, unique in table
- `config_value`: Required, string, max 255 characters
- `config_type`: Required, must be one of: string, integer, boolean, url
- `description`: Optional, string
- `is_active`: Boolean (defaults to 1 if not provided)

### edit(string $id)
**Purpose**: Retrieves a specific system configuration for editing.

**Parameters**:
- `$id`: Configuration ID

**Returns**: JSON success response with configuration data

**Error Handling**: Returns 404 if configuration not found

### update(Request $request, string $id)
**Purpose**: Updates an existing system configuration.

**Parameters**:
- `$id`: Configuration ID
- Same validation rules as store method (with unique rule exception for current record)

**Returns**: JSON success response with updated configuration

**Validation Rules**: Same as store method, but excludes current record from uniqueness check

### destroy(string $id)
**Purpose**: Deletes a system configuration.

**Parameters**:
- `$id`: Configuration ID

**Returns**: JSON success response

**Error Handling**: Returns 404 if configuration not found

## Static Helper Methods

### getValue(string $key, $default = null)
**Purpose**: Retrieves a configuration value by key with type casting.

**Parameters**:
- `$key`: Configuration key to retrieve
- `$default`: Default value if configuration not found

**Returns**: Typed configuration value or default

**Features**:
- Automatic type casting based on config_type
- Only returns active configurations
- Supports integer, boolean, and string types
- Returns default value if configuration not found or inactive

**Type Casting**:
- `integer`: Casts to int
- `boolean`: Uses filter_var with FILTER_VALIDATE_BOOLEAN
- `string` (default): Returns as string
- `url`: Returns as string (validation handled at input level)

## Model Relationships
- Uses `SystemConfiguration` model from `App\Models\focus` namespace
- Integrates with Spatie Permission system for access control

## Permission System
- Automatically creates permissions for each configuration:
  - `view systemconfiguration {id}`
  - `update systemconfiguration {id}`
  - `delete systemconfiguration {id}`
- Assigns view permission to Super Admin role by default

## Configuration Types
Supports four data types:
1. **string**: Text values, URLs, file paths
2. **integer**: Numeric values, counts, limits
3. **boolean**: True/false flags, feature toggles
4. **url**: Web addresses, API endpoints

## Use Cases
This system supports various configuration needs:
- API endpoints and URLs
- Feature flags and toggles
- Numeric limits and thresholds
- System-wide settings
- Integration parameters
- Environment-specific values

## Type Safety
- Input validation ensures correct data types
- Automatic type casting on retrieval
- Prevents type-related runtime errors
- Consistent data handling across the application

## Status Management
- Supports active/inactive status filtering
- Default status is active (1)
- Inactive configurations are ignored by getValue method
- Allows temporary disabling without deletion

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

## Best Practices
- Use descriptive configuration keys
- Include meaningful descriptions
- Choose appropriate data types
- Test configuration changes in development
- Document configuration dependencies