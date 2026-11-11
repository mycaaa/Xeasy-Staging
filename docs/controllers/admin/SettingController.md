# ⚙️ Admin SettingController Documentation

The Admin SettingController manages comprehensive system configuration within the Xeasy Laravel expense management system, providing centralized settings management for companies, themes, general settings, and specialized approval rules.

## 📋 Overview

**Location**: `app/Http/Controllers/admin/SettingController.php`

**Purpose**: Provides comprehensive system configuration management including company database configurations, theme customization, general application settings, and specialized approval workflow configurations.

**Models Used**:
- `CompanyDbConfig` - Company database configurations
- `CoreCompany` - Company information
- `GeneralSettings` - Application-wide settings
- `ThemeCustomizer` - User interface customization
- `OdoBackdateSetting` - Odometer backdate approval settings
- `OdoApprovalRule` - Running kilometer approval rules

**Form Requests**: `SaveConfigRequest`

**Key Features**:
- Multi-company database configuration
- Theme and UI customization
- General application settings management
- File upload handling for logos
- Complex approval rule management
- Department and vertical mapping integration

## 📊 Public Methods

### 1. Settings Index

#### `index(): View`

**Route**: `GET /admin/settings`
**Permission**: Admin access required

**Purpose**: Displays the main settings management interface.

**Response**: Returns Blade view for settings management

---

### 2. Company Management

#### `company(): JsonResponse`

**Route**: `GET /admin/settings/companies`
**Permission**: Admin access required

**Purpose**: Returns all companies for configuration management.

**Response Structure**:
```json
{
    "success": true,
    "data": [
        {
            "id": 1,
            "company_name": "Acme Corporation",
            "company_code": "ACME",
            "status": 1,
            "created_at": "2025-01-03T10:00:00.000000Z"
        }
    ],
    "message": "Company fetched successfully."
}
```

---

### 3. Company Database Configuration

#### `saveCompanyConfig(SaveConfigRequest $request): JsonResponse`

**Route**: `POST /admin/settings/company-config`
**Permission**: Admin access required

**Purpose**: Creates or updates company database configuration settings.

**Request Validation** (via SaveConfigRequest):
```php
[
    'company_id' => 'required|integer|exists:core_companies,id',
    'db_name' => 'required|in:hrims,expense',
    'db_connection' => 'required|string',
    'db_host' => 'required|string',
    'db_port' => 'required|integer',
    'db_database' => 'required|string',
    'db_username' => 'required|string',
    'db_password' => 'required|string',
    'is_active' => 'boolean'
]
```

**Request Body Example**:
```json
{
    "company_id": 1,
    "db_name": "hrims",
    "db_connection": "mysql",
    "db_host": "localhost",
    "db_port": 3306,
    "db_database": "company_hrims",
    "db_username": "db_user",
    "db_password": "secure_password",
    "is_active": true
}
```

**Response Structure**:
```json
{
    "success": true,
    "data": {
        "id": 5,
        "company_id": 1,
        "db_name": "hrims",
        "db_connection": "mysql",
        "db_host": "localhost",
        "db_port": 3306,
        "db_database": "company_hrims",
        "db_username": "db_user",
        "status": 1,
        "created_by": 1,
        "updated_by": 1,
        "created_at": "2025-01-03T10:30:00.000000Z",
        "updated_at": "2025-01-03T10:30:00.000000Z"
    },
    "message": "Configuration created successfully."
}
```

**Business Logic**:
1. **Validation**: Uses SaveConfigRequest for comprehensive validation
2. **Status Processing**: Converts boolean `is_active` to integer status
3. **User Tracking**: Records creating and updating user IDs
4. **Upsert Logic**: Updates existing configuration or creates new one
5. **Response**: Returns appropriate success message based on operation

---

### 4. Get Company Configuration

#### `getCompanyConfig(Request $request, string $id): JsonResponse`

**Route**: `GET /admin/settings/company-config/{id}`
**Permission**: Admin access required

**Purpose**: Retrieves database configurations for a specific company.

**Parameters**:
- `id` (required): Company ID

**Response Structure**:
```json
{
    "success": true,
    "data": {
        "hrims": {
            "id": 1,
            "company_id": 1,
            "db_name": "hrims",
            "db_connection": "mysql",
            "db_host": "localhost",
            "db_port": 3306,
            "db_database": "company_hrims",
            "db_username": "hrims_user",
            "status": 1
        },
        "expense": {
            "id": 2,
            "company_id": 1,
            "db_name": "expense",
            "db_connection": "mysql",
            "db_host": "localhost",
            "db_port": 3306,
            "db_database": "company_expense",
            "db_username": "expense_user",
            "status": 1
        }
    },
    "message": "Company details fetched successfully."
}
```

**Business Logic**:
1. **Configuration Retrieval**: Fetches all configurations for company
2. **Data Organization**: Separates HRIMS and expense configurations
3. **Structured Response**: Returns organized configuration data

---

### 5. Theme Settings Management

#### `saveThemeSettings(Request $request): JsonResponse`

**Route**: `POST /admin/settings/theme`
**Permission**: Admin access required

**Purpose**: Saves user-specific theme and UI customization settings.

**Request Validation**:
```php
[
    'layout' => 'in:vertical,horizontal,twocolumn,semibox',
    'sidebar_user_profile' => 'boolean',
    'theme' => 'in:default,saas,corporate,galaxy,material,creative,minimal,modern,interactive,classic,vintage',
    'color_scheme' => 'in:light,dark',
    'sidebar_visibility' => 'in:show,hidden',
    'layout_width' => 'in:fluid,boxed',
    'layout_position' => 'in:fixed,scrollable',
    'topbar_color' => 'in:light,dark',
    'sidebar_size' => 'in:lg,md,sm,sm-hover',
    'sidebar_view' => 'in:default,detached',
    'sidebar_color' => 'in:light,dark,gradient,gradient-2,gradient-3,gradient-4',
    'sidebar_image' => 'in:none,img-1,img-2,img-3,img-4',
    'primary_color' => 'in:default,green,purple,blue',
    'preloader' => 'in:enable,disable',
    'body_image' => 'in:none,img-1,img-2,img-3'
]
```

**Request Body Example**:
```json
{
    "layout": "vertical",
    "theme": "corporate",
    "color_scheme": "light",
    "sidebar_visibility": "show",
    "layout_width": "fluid",
    "topbar_color": "light",
    "sidebar_size": "lg",
    "primary_color": "blue",
    "preloader": "disable"
}
```

**Business Logic**:
1. **User Association**: Links theme settings to authenticated user
2. **Upsert Operation**: Updates existing settings or creates new ones
3. **Comprehensive Validation**: Validates all theme configuration options

---

### 6. General Settings Management

#### `saveGeneralSettings(Request $request): JsonResponse`

**Route**: `POST /admin/settings/general`
**Permission**: Admin access required

**Purpose**: Manages application-wide general settings including logo upload.

**Request Validation**:
```php
[
    'projectName' => 'required|string|max:255',
    'timeZone' => 'required|string|max:255',
    'language' => 'required|string|max:255',
    'siteUrl' => 'nullable|url|max:255',
    'contactInfo' => 'nullable|string|max:500',
    'siteDescription' => 'nullable|string|max:1000',
    'logo' => 'nullable|image|max:2048',
    'maintenanceMode' => 'nullable|boolean'
]
```

**Request Body Example**:
```json
{
    "projectName": "Xeasy Expense Management",
    "timeZone": "Asia/Kolkata",
    "language": "en",
    "siteUrl": "https://expense.company.com",
    "contactInfo": "support@company.com",
    "siteDescription": "Comprehensive expense management system",
    "maintenanceMode": false
}
```

**File Upload Handling**:
```php
if ($request->hasFile('logo')) {
    $timestamp = now()->format('YmdHis');
    $folder = "uploads/logos/{$timestamp}";
    $fileName = Str::random(10) . '.' . $request->file('logo')->getClientOriginalExtension();
    $path = $request->file('logo')->storeAs($folder, $fileName, 'public');
    $data['logo_path'] = $path;
}
```

**Business Logic**:
1. **File Upload**: Handles logo upload with timestamp-based organization
2. **Random Naming**: Uses random filename for security
3. **Upsert Logic**: Updates existing settings or creates new ones
4. **User Tracking**: Records updating user information

#### `getGeneralSettings(): JsonResponse`

**Route**: `GET /admin/settings/general`
**Permission**: Admin access required

**Purpose**: Retrieves current general application settings.

**Response Structure**:
```json
{
    "success": true,
    "data": {
        "id": 1,
        "project_name": "Xeasy Expense Management",
        "time_zone": "Asia/Kolkata",
        "default_language": "en",
        "site_url": "https://expense.company.com",
        "contact_info": "support@company.com",
        "site_description": "Comprehensive expense management system",
        "logo_path": "uploads/logos/20250103101530/abc123def4.png",
        "maintenance_mode": 0,
        "created_by": 1,
        "updated_by": 1
    },
    "message": "Settings fetched successfully."
}
```

---

### 7. ODO Backdate Approval Settings

#### `odoBackdateApproval(): View`

**Route**: `GET /admin/settings/odo-backdate-approval`
**Permission**: Admin access required

**Purpose**: Displays odometer backdate approval configuration interface.

**View Data**:
```php
[
    'departments' => Collection,           // All departments
    'settings' => Collection,             // Existing backdate settings keyed by department_id
    'mappedVerticalDetails' => Collection // Department-vertical mappings
]
```

**Complex Data Processing**:
```php
$mappedVerticalDetails = $deptVerticalMappings->groupBy('department_id')
    ->map(function ($deptMaps) use ($funcVerticalMap, $verticals) {
        return $deptMaps->map(function ($deptMap) use ($funcVerticalMap, $verticals) {
            $funcMapping = $funcVerticalMap[$deptMap->function_vertical_id] ?? null;
            if (!$funcMapping) return null;
            return $verticals[$funcMapping->vertical_id] ?? null;
        })->filter();
    });
```

#### `updateOdoBackdateSetting(Request $request, $department_id): JsonResponse`

**Route**: `PUT /admin/settings/odo-backdate-approval/{department_id}`
**Permission**: Admin access required

**Purpose**: Updates odometer backdate approval settings for a specific department.

**Request Parameters**:
```php
[
    'is_active' => 'boolean',
    'approval_type' => 'string',
    'effective_date' => 'date',
    'delayed_day' => 'integer',
    'verticals' => 'array'
]
```

**Business Logic**:
1. **Data Processing**: Converts verticals array to comma-separated string
2. **Upsert Operation**: Updates existing setting or creates new one
3. **Department Association**: Links setting to specific department

---

### 8. Access Running KM Settings

#### `accessRunningKmSetting(): View`

**Route**: `GET /admin/settings/access-running-km`
**Permission**: Admin access required

**Purpose**: Displays running kilometer access approval configuration interface.

**View Data**: Similar structure to ODO backdate approval with departments, rules, and vertical mappings.

#### `updateAccessRunningKmSetting(Request $request, $department_id): JsonResponse`

**Route**: `PUT /admin/settings/access-running-km/{department_id}`
**Permission**: Admin access required

**Purpose**: Updates access running kilometer settings with comprehensive validation and business logic.

**Dynamic Validation**:
```php
$rules = [
    'is_active' => 'required|boolean',
    'auto_approve_if_below_limit' => 'required|in:Y,N',
    'dept_active' => 'required|in:Y,N',
    'effective_to' => 'nullable|date|after:effective_from',
];

// If department is active, make fields required
if ($isActive) {
    $rules['monthly_km_limit'] = 'required|integer|min:1|max:999999';
    $rules['effective_from'] = 'required|date|before_or_equal:today';
    $rules['verticals'] = 'required|array|min:1';
    $rules['verticals.*'] = 'integer';
}
```

**Complex Business Logic**:
1. **Dynamic Validation**: Rules change based on active status
2. **Date Range Validation**: Prevents overlapping active rules
3. **Department Existence Check**: Validates department exists
4. **Conflict Detection**: Checks for conflicting date ranges
5. **Data Integrity**: Ensures logical consistency

**Conflict Detection Logic**:
```php
$conflictingRules = OdoApprovalRule::where('department_id', $department_id)
    ->where('id', '!=', $existingRule->id)
    ->where('is_active', true)
    ->where(function ($query) use ($effectiveFrom, $effectiveTo) {
        // Complex date range overlap detection
    })
    ->exists();
```

---

## 🔧 Implementation Details

### File Upload Management

**Logo Upload Process**:
1. **Timestamp Organization**: Creates folders with timestamp for organization
2. **Random Naming**: Uses `Str::random(10)` for secure filenames
3. **Extension Preservation**: Maintains original file extension
4. **Public Storage**: Stores in public disk for web access

### Database Configuration Security

**Security Considerations**:
- Database passwords stored in plain text (consider encryption)
- User tracking for audit purposes
- Status-based activation/deactivation

### Theme Customization

**Comprehensive Options**:
- Layout configurations (vertical, horizontal, etc.)
- Color schemes and themes
- Sidebar customization
- UI element positioning
- Image and gradient options

### Complex Approval Rules

**ODO Backdate Settings**:
- Department-specific configurations
- Vertical-based restrictions
- Effective date management
- Approval type specifications

**Running KM Access Rules**:
- Monthly kilometer limits
- Auto-approval thresholds
- Date range validations
- Conflict prevention
- Department and vertical mappings

## 🚨 Business Rules

### Company Configuration

1. **Database Separation**: HRIMS and expense databases configured separately
2. **Multi-Company Support**: Each company can have different database configurations
3. **Status Management**: Configurations can be activated/deactivated
4. **User Tracking**: All changes tracked with user information

### Theme Management

1. **User-Specific**: Theme settings are per-user
2. **Comprehensive Options**: Wide range of customization options
3. **Validation**: All options validated against predefined values
4. **Persistence**: Settings persist across sessions

### General Settings

1. **Application-Wide**: Settings affect entire application
2. **File Management**: Logo uploads with organized storage
3. **Maintenance Mode**: System-wide maintenance control
4. **Localization**: Timezone and language configuration

### Approval Rules

1. **Department-Specific**: Rules configured per department
2. **Date Range Management**: Prevents overlapping active rules
3. **Vertical Integration**: Links with organizational verticals
4. **Conflict Prevention**: Validates against existing rules

## 🔐 Security Considerations

### Data Protection

**Sensitive Information**:
- Database credentials stored (consider encryption)
- File upload validation and security
- User authentication for all operations

**Recommendations**:
```php
// Encrypt database passwords
protected $casts = [
    'db_password' => 'encrypted'
];

// Add file upload security
public function saveGeneralSettings(Request $request)
{
    $request->validate([
        'logo' => 'nullable|image|mimes:jpeg,png,jpg,gif|max:2048'
    ]);
    
    // Additional security checks
    if ($request->hasFile('logo')) {
        $file = $request->file('logo');
        
        // Validate file content
        if (!$this->isValidImage($file)) {
            return response()->json(['error' => 'Invalid image file'], 400);
        }
    }
}
```

### Access Control

**Current Implementation**:
- Inherits base controller security
- Admin-level access required for all operations

**Enhanced Security**:
```php
public function __construct()
{
    $this->middleware(['auth', 'permission:manage_system_settings']);
}
```

## 🚀 Performance Considerations

### Database Optimization

**Current Queries**:
- Multiple model queries for complex data relationships
- Nested mapping operations for vertical-department relationships

**Optimization Recommendations**:
```php
// Cache department-vertical mappings
$mappedVerticalDetails = Cache::remember('dept_vertical_mappings', 3600, function() {
    return $this->buildVerticalMappings();
});

// Optimize complex queries
$departments = CoreDepartments::with([
    'verticalMappings.functionalMapping.vertical'
])->get();
```

### File Storage Optimization

**Current Implementation**:
- Timestamp-based folder organization
- Random filename generation

**Improvements**:
```php
// Add image optimization
public function optimizeAndStoreImage($file)
{
    $image = Image::make($file);
    $image->resize(300, 300, function ($constraint) {
        $constraint->aspectRatio();
        $constraint->upsize();
    });
    
    return $image->save($path, 80); // 80% quality
}
```

## 📈 Monitoring and Analytics

### Key Metrics to Track

1. **Configuration Changes**:
   - Database configuration updates
   - Theme customization frequency
   - Settings modification patterns

2. **File Upload Statistics**:
   - Logo upload frequency
   - File sizes and types
   - Storage usage patterns

3. **Approval Rule Usage**:
   - Rule activation/deactivation frequency
   - Department-wise rule distribution
   - Conflict occurrence rates

### Enhanced Logging

```php
public function saveCompanyConfig(SaveConfigRequest $request)
{
    Log::info('Company configuration update started', [
        'company_id' => $request->company_id,
        'db_name' => $request->db_name,
        'user_id' => Auth::id(),
        'timestamp' => now()
    ]);
    
    try {
        // Existing logic
        
        Log::info('Company configuration updated successfully', [
            'config_id' => $config->id,
            'company_id' => $config->company_id,
            'operation' => $config->wasRecentlyCreated ? 'created' : 'updated',
            'timestamp' => now()
        ]);
        
    } catch (\Exception $e) {
        Log::error('Company configuration update failed', [
            'error' => $e->getMessage(),
            'company_id' => $request->company_id,
            'timestamp' => now()
        ]);
    }
}
```

## 🔄 Future Enhancements

### Suggested Improvements

1. **Enhanced Security**:
   ```php
   // Encrypt sensitive configuration data
   protected $casts = [
       'db_password' => 'encrypted',
       'api_keys' => 'encrypted'
   ];
   
   // Add configuration backup
   public function backupConfiguration($companyId)
   {
       $configs = CompanyDbConfig::where('company_id', $companyId)->get();
       
       Storage::put(
           "backups/company_{$companyId}_" . now()->format('Y-m-d_H-i-s') . ".json",
           $configs->toJson()
       );
   }
   ```

2. **Configuration Validation**:
   ```php
   public function testDatabaseConnection(Request $request)
   {
       $config = $request->only(['db_host', 'db_port', 'db_database', 'db_username', 'db_password']);
       
       try {
           $pdo = new PDO(
               "mysql:host={$config['db_host']};port={$config['db_port']};dbname={$config['db_database']}",
               $config['db_username'],
               $config['db_password']
           );
           
           return response()->json(['success' => true, 'message' => 'Connection successful']);
       } catch (\Exception $e) {
           return response()->json(['success' => false, 'message' => 'Connection failed: ' . $e->getMessage()]);
       }
   }
   ```

3. **Advanced Theme Management**:
   ```php
   public function exportTheme($userId)
   {
       $theme = ThemeCustomizer::where('user_id', $userId)->first();
       
       return response()->json([
           'theme_export' => $theme,
           'export_date' => now(),
           'version' => '1.0'
       ]);
   }
   
   public function importTheme(Request $request)
   {
       $request->validate([
           'theme_data' => 'required|json',
           'apply_to_user' => 'required|integer|exists:users,id'
       ]);
       
       $themeData = json_decode($request->theme_data, true);
       
       ThemeCustomizer::updateOrCreate(
           ['user_id' => $request->apply_to_user],
           $themeData
       );
   }
   ```

4. **Settings History Tracking**:
   ```php
   public function getSettingsHistory($type, $limit = 50)
   {
       return DB::table('settings_history')
           ->where('setting_type', $type)
           ->orderBy('created_at', 'desc')
           ->limit($limit)
           ->get();
   }
   
   private function logSettingChange($type, $oldValue, $newValue)
   {
       DB::table('settings_history')->insert([
           'setting_type' => $type,
           'old_value' => json_encode($oldValue),
           'new_value' => json_encode($newValue),
           'changed_by' => Auth::id(),
           'created_at' => now()
       ]);
   }
   ```

### Feature Additions

1. **Configuration Templates**:
   - Predefined configuration templates
   - Quick setup wizards
   - Best practice recommendations

2. **Advanced Validation**:
   - Real-time configuration testing
   - Dependency validation
   - Impact analysis

3. **Bulk Operations**:
   - Bulk theme application
   - Mass configuration updates
   - Template-based setup

4. **Integration Features**:
   - External configuration sync
   - API-based configuration management
   - Automated backup and restore

## 🔗 Related Components

### Database Tables
- `company_db_configs` - Company database configurations
- `core_companies` - Company information
- `general_settings` - Application settings
- `theme_customizers` - User theme preferences
- `odo_backdate_settings` - Backdate approval settings
- `odo_approval_rules` - Running KM approval rules

### Related Controllers
- `CompanyController` - Company management
- `UserController` - User management
- `DashboardController` - Dashboard configuration

### Frontend Integration
- Settings management interfaces
- Theme customization panels
- Configuration wizards
- File upload components

---

The Admin SettingController provides comprehensive system configuration management with advanced features for multi-company setups, theme customization, and complex approval rule management, serving as the central hub for all system-wide configuration needs.