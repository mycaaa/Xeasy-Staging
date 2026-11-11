# 🔧 Backend Routes Documentation

## 📋 Overview

This document provides comprehensive documentation for all backend administrative routes defined in `routes/backend.php`. These routes handle the complete admin panel functionality and are separate from the basic authentication routes in `web.php`.

**File**: `routes/backend.php`  
**Total Routes**: 200+ routes  
**Base Middleware**: `auth` (all routes require authentication)  
**Additional Middleware**: Permission-based middleware for specific sections  

## 🏗️ Code Structure Analysis

### File Organization
```php
<?php
// Import statements (50+ controller imports)
use App\Http\Controllers\admin\{ControllerName};
use Illuminate\Support\Facades\Route;

// Main route group with auth middleware
Route::middleware('auth')->group(function () {
    // All backend routes defined here
});
```

### Middleware Stack
```php
Route::middleware('auth')->group(function () {
    Route::middleware('permission:specific_permission')->group(function () {
        // Permission-protected routes
    });
});
```

## 🗂️ Route Categories & Structure

### 1. System Utilities
**Purpose**: Basic system operations and company management  
**Middleware**: `auth` only  
**Type**: System-level operations  

| Method | URI | Controller | Action | Return Type | Description |
|--------|-----|------------|--------|-------------|-------------|
| GET | `/companies-list` | DatabaseSwitchController | getCompanies | JsonResponse | Get available companies |
| GET | `/clear-caches` | Controller | clearAllCaches | JsonResponse | Clear application caches |

**Financial Year Management**:
```php
Route::resource('financial', FinancialYearController::class);
```
- **Type**: RESTful Resource
- **Methods**: index, create, store, show, edit, update, destroy
- **Return Types**: View (index, create, edit), JsonResponse (store, update, destroy)

---

### 2. Dashboard Routes
**Middleware**: `permission:section_dashboard`  
**Purpose**: Dashboard data and analytics  
**Type**: Data visualization and reporting  

| Method | URI | Controller | Action | Return Type | Parameters | Description |
|--------|-----|------------|--------|-------------|------------|-------------|
| GET | `/home` | HomeController | index | View | - | Main dashboard page |
| GET | `/home-data` | HomeController | getDashboardData | JsonResponse | - | Dashboard statistics |
| POST | `/get-employee-trend` | HomeController | getEmployeeTrend | JsonResponse | Request | Employee expense trends |
| POST | `/export/expense-month-wise` | HomeController | exportExpenseMonthWise | BinaryFileResponse | Request | Monthly expense export |
| POST | `/export/expense-department-wise` | HomeController | exportExpenseDepartmentWise | BinaryFileResponse | Request | Department expense export |
| POST | `/export/expense-claim-type-wise` | HomeController | exportExpenseClaimTypeWise | BinaryFileResponse | Request | Claim type export |
| GET | `/analytics/{page}` | HomeController | analytics | View | string $page | Analytics pages |
| GET | `/analytics-dashboard-data` | HomeController | analyticsDashboardData | JsonResponse | - | Analytics data |
| POST | `/get-sub-departments` | HomeController | getSubDepartments | JsonResponse | Request | Sub-department list |
| POST | `/export-reports` | HomeController | exportReports | BinaryFileResponse | Request | Multi-report export |
| GET | `/export/employee-running-report` | HomeController | exportEmployeeRunningKmReport | BinaryFileResponse | - | Employee KM report |
| GET | `/export/claim-exception-report` | HomeController | exportClaimExceptionReport | BinaryFileResponse | - | Exception report |
| GET | `/claim-exception-report` | HomeController | claimExceptionReport | View | - | Exception report view |
| GET | `/claim-exception-detail` | HomeController | getClaimExceptionDetail | JsonResponse | - | Exception details |

**Code Structure**:
```php
Route::middleware('permission:section_dashboard')->group(function () {
    Route::get('home', [HomeController::class, 'index']);
    Route::get('home-data', [HomeController::class, 'getDashboardData']);
    // ... other dashboard routes
});
```

---

### 3. Permission Management System
**Middleware**: `permission:permission_management`  
**Purpose**: Permission groups and individual permissions  
**Type**: RBAC (Role-Based Access Control)  

#### Permission Groups
```php
Route::resource('permission-groups', PermissionGroupController::class);
```
**Resource Type**: Full CRUD Resource  
**Controller**: `PermissionGroupController`  
**Model**: `PermissionGroup`  
**Methods & Return Types**:
- `index()`: JsonResponse - List all permission groups
- `create()`: void - Not implemented
- `store(PermissionGroupRequest $request)`: JsonResponse - Create new group
- `show(string $id)`: void - Not implemented
- `edit(string $id)`: void - Not implemented
- `update(Request $request, string $id)`: void - Not implemented
- `destroy(string $id)`: void - Not implemented

#### Individual Permissions
```php
Route::resource('permissions', PermissionController::class);
```
**Additional Permission Routes**:
| Method | URI | Controller | Action | Return Type | Parameters | Description |
|--------|-----|------------|--------|-------------|------------|-------------|
| GET | `/permissions-old` | PermissionController | permissionOld | View | - | Legacy permissions |
| POST | `/permissions/assign` | PermissionController | assignPermissions | JsonResponse | Request | Assign permissions |
| GET | `/permissions-list` | PermissionController | getAllPermissions | JsonResponse | - | All permissions list |

---

### 4. Role Management
**Middleware**: `permission:master_roles`  
**Purpose**: Role creation and management  
**Type**: RBAC System  

```php
Route::resource('roles', RolesController::class);
Route::get('get-roles-list', [RolesController::class, 'getRoles']);
```

**Resource Details**:
- **Controller**: `RolesController`
- **Model**: `Role`
- **Additional Route**: `getRoles()` returns JsonResponse for dropdown lists

---

### 5. User Management System
**Middleware**: `permission:user_management`  
**Purpose**: Complete user CRUD with permissions  
**Type**: User administration  

| Method | URI | Controller | Action | Return Type | Parameters | Description |
|--------|-----|------------|--------|-------------|------------|-------------|
| GET | `/users` | UsersController | index | View | - | List users |
| POST | `/users` | UsersController | store | JsonResponse | Request | Create user |
| GET | `/users/{id}` | UsersController | show | JsonResponse | int $id | Show user |
| PUT | `/users/{id}` | UsersController | update | JsonResponse | Request, int $id | Update user |
| DELETE | `/users/{id}` | UsersController | destroy | JsonResponse | int $id | Delete user |
| POST | `/users/data` | UsersController | datatable | JsonResponse | Request | Users datatable |
| GET | `/user/{id}/permission` | UsersController | getPermissionView | View | int $id | Permission view |
| GET | `/users/{id}/permissions` | PermissionController | getPermissions | JsonResponse | int $id | User permissions |
| POST | `/users/{id}/permissions/assign` | PermissionController | assignPermission | JsonResponse | Request, int $id | Assign permission |
| POST | `/users/{id}/permissions/revoke` | PermissionController | revokePermission | JsonResponse | Request, int $id | Revoke permission |
| POST | `/users/{id}/claim-types/assign` | PermissionController | assignClaimType | JsonResponse | Request, int $id | Assign claim type |
| POST | `/users/{id}/claim-types/revoke` | PermissionController | revokeClaimType | JsonResponse | Request, int $id | Revoke claim type |
| POST | `/users/import` | UsersController | importEmployees | JsonResponse | Request | Import employees |
| GET | `/profile` | UsersController | profile | View | - | User profile |
| GET | `/user-activity` | UsersController | userActivity | View | - | User activity |
| GET | `/user-activity/data` | UsersController | userActivityData | JsonResponse | - | Activity data |

---

### 6. Settings Management
**Middleware**: `permission:general_settings`  
**Purpose**: System configuration and company settings  
**Type**: Configuration management  

| Method | URI | Controller | Action | Return Type | Parameters | Description |
|--------|-----|------------|--------|-------------|------------|-------------|
| GET | `/settings` | SettingController | index | View | - | Settings page |
| GET | `/company` | SettingController | company | JsonResponse | - | Company settings |
| GET | `/company-config/{id}` | SettingController | getCompanyConfig | JsonResponse | int $id | Company config |
| POST | `/save-config` | SettingController | saveCompanyConfig | JsonResponse | SaveConfigRequest | Save company config |
| POST | `/settings/theme` | SettingController | saveThemeSettings | JsonResponse | Request | Save theme settings |
| GET | `/general` | SettingController | getGeneralSettings | JsonResponse | - | General settings |
| POST | `/general` | SettingController | saveGeneralSettings | JsonResponse | Request | Save general settings |
| GET | `/odo-backdate-approval` | SettingController | odoBackdateApproval | View | - | Odometer backdate approval |
| POST | `/odo-backdate-setting/{department_id}` | SettingController | updateOdoBackdateSetting | JsonResponse | Request, int $department_id | Update odometer setting |
| GET | `/access-running-km-setting` | SettingController | accessRunningKmSetting | View | - | Running KM access setting |
| POST | `/access-running-km-setting/{department_id}` | SettingController | updateAccessRunningKmSetting | JsonResponse | Request, int $department_id | Update KM access setting |

**Form Requests Used**:
- `SaveConfigRequest` - Company configuration validation

---

## 🔒 Permission System

### Available Permissions
| Permission | Purpose | Routes Protected |
|------------|---------|------------------|
| `section_dashboard` | Dashboard access | Dashboard, analytics, exports |
| `permission_management` | Permission system | Permission groups, permissions |
| `master_roles` | Role management | Role CRUD operations |
| `user_management` | User operations | User CRUD, permissions, imports |
| `general_settings` | System settings | Company, theme, general settings |
| `settings_claim_type` | Claim type management | Claim type CRUD |
| `settings_activity` | Activity management | Activity overview |
| `expense_head_management` | Expense categories | Expense head CRUD |
| `activity_category_management` | Activity categories | Activity category CRUD |
| `activity_type_management` | Activity types | Activity type CRUD |
| `activity_name_management` | Activity names | Activity name CRUD |
| `expense_head_mapping_management` | Expense mappings | Expense head mapping CRUD |
| `vendor_management` | Vendor operations | Vendor CRUD |
| `master_menus` | Menu management | Menu CRUD, logs |
| `email_template_management` | Email templates | Template CRUD, variables |
| `claim_type_management` | Claim types | Claim type management |
| `section_reports` | Report generation | All report operations |
| `section_data_punching` | Data entry | Data punch operations |
| `section_verifier` | Verification | Claim verification, vehicle management |
| `pending_claims_courier_detail` | Courier access | Courier detail operations |

### Middleware Structure
```php
// Base authentication for all routes
Route::middleware('auth')->group(function () {
    
    // Permission-based protection
    Route::middleware('permission:specific_permission')->group(function () {
        // Protected routes
    });
    
    // Nested permission protection
    Route::middleware(['permission:section_verifier'])->group(function () {
        Route::middleware(['permission:pending_claims_courier_detail'])->group(function () {
            // Double-protected routes
        });
    });
});
```

## 📊 Return Type Analysis

### Response Types Used
| Return Type | Usage | Purpose | Examples |
|-------------|-------|---------|----------|
| `View` | 25% | Page rendering | Dashboard, forms, lists |
| `JsonResponse` | 65% | AJAX responses | API endpoints, data tables |
| `BinaryFileResponse` | 10% | File downloads | Exports, document generation |
| `void` | <1% | Not implemented | Placeholder methods |

### JSON Response Patterns
```php
// Success response
return response()->json([
    'success' => true,
    'data' => $data,
    'message' => 'Operation completed successfully.'
]);

// Error response
return response()->json([
    'success' => false,
    'error' => 'Error message',
    'code' => 400
], 400);
```

### File Response Patterns
```php
// Excel export
return Excel::download(new ExportClass($data), 'filename.xlsx');

// PDF generation
return response()->file($pdfPath);

// Direct download
return response()->download($filePath, $filename);
```

## 🏗️ Route Naming Conventions

### Resource Routes
- **Pattern**: `{resource}.{action}`
- **Examples**: `users.index`, `roles.store`, `permissions.update`

### Custom Named Routes
- **Pattern**: `{module}.{action}` or `{module}.{sub-module}.{action}`
- **Examples**: `version-control.status`, `vnr.approver.pending.list`

### Prefix-Based Routes
- **VNR Routes**: `vnr.{action}`
- **VVNR Routes**: `vvnr.{action}`
- **Reports**: `reports.{action}`
- **Version Control**: `version-control.{action}`

## 🔄 Integration Points

### Database Connections
- **Default**: Main application database
- **HRIMS**: HR information system
- **Expense**: Expense management database

### External Systems
- **Focus ERP**: Integration through mapping controllers
- **Git**: Version control through web interface
- **Email System**: Template-based communication

### File Systems
- **Local Storage**: Temporary files, uploads
- **Cloud Storage**: Document storage (if configured)
- **Export System**: Excel, PDF generation

## 📈 Performance Considerations

### Route Optimization
- **Route Caching**: Enabled in production
- **Middleware Caching**: Permission checks cached
- **Database Optimization**: Indexed queries for permissions

### Response Optimization
- **JSON Responses**: Minimal data transfer
- **File Streaming**: Large file downloads
- **Pagination**: Large dataset handling

## 🔗 Related Documentation

- [Web Routes](web-routes.md) - Basic authentication routes
- [API Routes](api-routes.md) - External API endpoints
- [Controller Documentation](../controllers/) - Individual controller details
- [Permission System](../authentication.md) - RBAC implementation
- [Database Schema](../database/) - Database structure

---

This comprehensive documentation provides a complete reference for the backend routing system with proper type information, parameter details, and code structure analysis that matches the main codebase implementation.

### 7. Activity & Claim Management
**Purpose**: Expense claim types and activity management  
**Type**: Business logic configuration  

#### Claim Types
**Middleware**: `permission:settings_claim_type`
```php
Route::resource('claim-types', ClaimTypeController::class);
Route::get('claim-type/groups', [ClaimTypeController::class, 'getGroups']);
Route::post('claim-type-list', [ClaimTypeController::class, 'claimTypeList']);
```

#### Activity Management
**Middleware**: `permission:settings_activity`
```php
Route::get('activity', [ActivityController::class, 'index'])->name('activity.index');
Route::get('activity/tab-content', [ActivityController::class, 'getTabContent'])->name('activity.tab-content');
```

#### Expense Head Management
**Middleware**: `permission:expense_head_management`
```php
Route::resource('expense-heads', ExpenseHeadCategoryController::class);
```

#### Activity Categories
**Middleware**: `permission:activity_category_management`
```php
Route::resource('activity-categories', ActivityCategoryController::class);
```

#### Activity Types
**Middleware**: `permission:activity_type_management`
```php
Route::resource('activity-types', ActivityTypeController::class);
Route::get('activity-type/categories', [ActivityTypeController::class, 'getCategories'])->name('activity.types.categories');
Route::get('activity-type/departments', [ActivityTypeController::class, 'getDepartments'])->name('activity.types.departments');
```

#### Activity Names
**Middleware**: `permission:activity_name_management`
```php
Route::resource('activity-names', ActivityNameController::class);
Route::get('activity-name/categories', [ActivityNameController::class, 'getCategories'])->name('activity.names.categories');
Route::get('activity-name/departments', [ActivityNameController::class, 'getDepartments'])->name('activity.names.departments');
Route::get('activity-name/verticals', [ActivityNameController::class, 'getVerticals'])->name('activity.names.verticals');
Route::get('activity-name/claim-types', [ActivityNameController::class, 'getClaimTypes'])->name('activity.names.claim-types');
```

#### Expense Head Mapping
**Middleware**: `permission:expense_head_mapping_management`
```php
Route::resource('expense-head-mappings', ExpenseHeadMappingController::class);
Route::post('expense-head-mappings/toggle', [ExpenseHeadMappingController::class, 'toggle'])->name('expense-head-mappings.toggle');
Route::get('expense-head-mappings/activities', [ExpenseHeadMappingController::class, 'getActivities'])->name('expense-head-mappings.activities');
Route::get('expense-head-mappings/expense-heads', [ExpenseHeadMappingController::class, 'getExpenseHeads'])->name('expense-head-mappings.expense-heads');
```

#### Vendor Management
**Middleware**: `permission:vendor_management`
```php
Route::resource('vendors', VendorListController::class);
Route::get('vendor-list/{type?}', [VendorListController::class, 'vendorList']);
Route::get('vendor-types', [VendorTypeController::class, 'getVendorTypes']);
```

---

### 8. Menu Management
**Middleware**: `permission:master_menus`  
**Purpose**: Dynamic menu system  
**Type**: CMS functionality  

| Method | URI | Controller | Action | Return Type | Parameters | Description |
|--------|-----|------------|--------|-------------|------------|-------------|
| GET | `/menu` | MenuController | index | View | - | List menus |
| POST | `/menu` | MenuController | store | JsonResponse | Request | Create menu |
| GET | `/menu/{id}` | MenuController | show | JsonResponse | int $id | Show menu |
| PUT | `/menu/{id}` | MenuController | update | JsonResponse | Request, int $id | Update menu |
| DELETE | `/menu/{id}` | MenuController | destroy | JsonResponse | int $id | Delete menu |
| GET | `/menu-list` | MenuController | menuList | JsonResponse | - | Menu list |
| GET | `/menu/log/{id}` | MenuController | getLogs | JsonResponse | int $id | Menu logs |
| GET | `/search-menu-list` | MenuController | menuList | JsonResponse | - | Search menu list |

---

### 9. Report Management
**Middleware**: `permission:section_reports`  
**Purpose**: Report generation and export  
**Type**: Business intelligence  

| Method | URI | Controller | Action | Return Type | Parameters | Description |
|--------|-----|------------|--------|-------------|------------|-------------|
| GET | `/claim-report` | ReportController | claimReport | View | - | Claim reports |
| GET | `/claim-stage` | ReportController | claimStage | View | - | Claim stage report |
| POST | `/export-claim-stage` | ReportController | exportClaimStage | BinaryFileResponse | Request | Export claim stage |
| GET | `/daily-activity` | ReportController | dailyActivity | View | - | Daily activity report |
| POST | `/daily-activity/data` | ReportController | getDailyActivityData | JsonResponse | Request | Daily activity data |
| POST | `/daily-activity/export` | ReportController | exportDailyActivity | BinaryFileResponse | Request | Export daily activity |
| POST | `/filter-claims` | ReportController | filterClaims | JsonResponse | Request | Filter claims |
| POST | `/expense-claims/export` | ReportController | export | BinaryFileResponse | Request | Export expense claims |
| GET | `/top-rating-employee` | ReportController | topRatingEmployee | View | - | Top rating employees |
| POST | `/claims/return` | ReportController | returnClaim | JsonResponse | Request | Return claim |
| GET | `/same_date` | ReportController | sameDayClaimUpload | View | - | Same day claims |

**Additional Report Routes**:
```php
Route::get('employee_running_km', [ReportController::class, 'employeeRunningKm']);
Route::get('getEmployeeRunningKm', [HomeController::class, 'getEmployeefningKm']);
```

---

### 10. Email Template Management
**Middleware**: `permission:email_template_management`  
**Purpose**: Dynamic email template system  
**Type**: Communication management  

| Method | URI | Controller | Action | Return Type | Parameters | Description |
|--------|-----|------------|--------|-------------|------------|-------------|
| GET | `/email-templates` | EmailTemplateController | index | View | - | List email templates |
| POST | `/email-template` | EmailTemplateController | store | JsonResponse | Request | Create email template |
| GET | `/email-template/{id}/edit` | EmailTemplateController | edit | View | int $id | Edit email template |
| PUT | `/email-template/{template}` | EmailTemplateController | update | JsonResponse | Request, EmailTemplate $template | Update email template |
| DELETE | `/email-template/{id}` | EmailTemplateController | destroy | JsonResponse | int $id | Delete email template |
| GET | `/email-template/log/{template}` | EmailTemplateController | getLogs | JsonResponse | EmailTemplate $template | Template logs |
| GET | `/email-template-list` | EmailTemplateController | templateList | JsonResponse | - | Template list |
| GET | `/email-template-variables` | EmailTemplateController | getVariables | JsonResponse | - | Template variables |

**Route Names**: All routes use `email_template.{action}` naming convention

---

### 11. VNR & VVNR Approver Systems
**Purpose**: Specialized approval workflows  
**Type**: Business process management  

#### VNR Approver Routes
**Prefix**: `vnr/`  
**Route Names**: `vnr.{action}`  

| Method | URI | Controller | Action | Return Type | Description |
|--------|-----|------------|--------|-------------|-------------|
| GET | `/vnr/approver` | ClaimantController | approver | View | VNR approver page |
| GET | `/vnr/approver/pending/list` | VNRApproverController | getApproverPendingList | JsonResponse | Pending list |
| GET | `/vnr/approver/pending/day` | VNRApproverController | getApproverPendingDay | JsonResponse | Pending day |
| GET | `/vnr/approver/approved/list` | VNRApproverController | getApproverApprovedList | JsonResponse | Approved list |
| GET | `/vnr/approver/approved/day` | VNRApproverController | getApproverApprovedDay | JsonResponse | Approved day |
| POST | `/vnr/approver/claim/approve` | VNRApproverController | approveClaimByApprover | JsonResponse | Approve claim |
| POST | `/vnr/approver/claim/submit` | VNRApproverController | submitClaimByApprover | JsonResponse | Submit claim |
| GET | `/vnr/team/members/list` | VNRApproverController | getTeamMembersList | JsonResponse | Team members |
| GET | `/vnr/team/all/expenses` | VNRApproverController | getAllTeamExpenses | JsonResponse | All team expenses |
| GET | `/vnr/team/member/expenses` | VNRApproverController | getMemberExpenses | JsonResponse | Member expenses |
| GET | `/vnr/team/member/expenses/by-claim` | VNRApproverController | getMemberExpensesByClaim | JsonResponse | Expenses by claim |

#### VVNR Approver Routes
**Prefix**: `vvnr/`  
**Route Names**: `vvnr.{action}`  

| Method | URI | Controller | Action | Return Type | Description |
|--------|-----|------------|--------|-------------|-------------|
| GET | `/vvnr/approver` | ClaimantController | approver | View | VVNR approver page |
| GET | `/vvnr/approver/pending/list` | VVNRApproverController | getApproverPendingList | JsonResponse | Pending list |
| GET | `/vvnr/approver/pending/day` | VVNRApproverController | getApproverPendingDay | JsonResponse | Pending day |
| GET | `/vvnr/approver/approved/list` | VVNRApproverController | getApproverApprovedList | JsonResponse | Approved list |
| GET | `/vvnr/approver/approved/day` | VVNRApproverController | getApproverApprovedDay | JsonResponse | Approved day |
| POST | `/vvnr/approver/claim/approve` | VVNRApproverController | approveClaimByApprover | JsonResponse | Approve claim |
| POST | `/vvnr/approver/claim/submit` | VVNRApproverController | submitClaimByApprover | JsonResponse | Submit claim |
| GET | `/vvnr/team/members/list` | VVNRApproverController | getTeamMembersList | JsonResponse | Team members |
| GET | `/vvnr/team/all/expenses` | VVNRApproverController | getAllTeamExpenses | JsonResponse | All team expenses |
| GET | `/vvnr/team/member/expenses` | VVNRApproverController | getMemberExpenses | JsonResponse | Member expenses |
| GET | `/vvnr/team/member/expenses/by-claim` | VVNRApproverController | getMemberExpensesByClaim | JsonResponse | Expenses by claim |

**Code Structure**:
```php
Route::prefix('vnr')->name('vnr.')->group(function () {
    Route::get('approver', [ClaimantController::class, 'approver'])->name('approver');
    // ... other VNR routes
});

Route::prefix('vvnr')->name('vvnr.')->group(function () {
    Route::get('approver', [ClaimantController::class, 'approver'])->name('approver');
    // ... other VVNR routes
});
```

---

### 12. Version Control System
**Purpose**: Git operations through web interface  
**Type**: Development tools  

| Method | URI | Controller | Action | Return Type | Parameters | Description |
|--------|-----|------------|--------|-------------|------------|-------------|
| GET | `/version-control` | GitVersionControlController | index | View | - | Version control |
| GET | `/version-control/diagnostics` | GitDiagnosticsController | diagnose | JsonResponse | - | Git diagnostics |
| GET | `/version-control/status` | GitVersionControlController | gitStatus | JsonResponse | - | Git status |
| POST | `/version-control/add` | GitVersionControlController | gitAdd | JsonResponse | Request | Git add |
| POST | `/version-control/commit` | GitVersionControlController | gitCommit | JsonResponse | Request | Git commit |
| POST | `/version-control/push` | GitVersionControlController | gitPush | JsonResponse | Request | Git push |
| POST | `/version-control/pull` | GitVersionControlController | gitPull | JsonResponse | Request | Git pull |
| POST | `/version-control/fetch` | GitVersionControlController | gitFetch | JsonResponse | Request | Git fetch |
| POST | `/version-control/checkout` | GitVersionControlController | gitCheckout | JsonResponse | Request | Git checkout |
| GET | `/version-control/log` | GitVersionControlController | gitLog | JsonResponse | - | Git log |
| GET | `/version-control/diff` | GitVersionControlController | gitDiff | JsonResponse | - | Git diff |
| POST | `/version-control/stash` | GitVersionControlController | gitStash | JsonResponse | Request | Git stash |
| POST | `/version-control/create-branch` | GitVersionControlController | gitCreateBranch | JsonResponse | Request | Create branch |
| POST | `/version-control/delete-branch` | GitVersionControlController | gitDeleteBranch | JsonResponse | Request | Delete branch |
| POST | `/version-control/merge` | GitVersionControlController | gitMerge | JsonResponse | Request | Git merge |
| POST | `/version-control/tag` | GitVersionControlController | gitTag | JsonResponse | Request | Git tag |
| POST | `/version-control/reset` | GitVersionControlController | gitReset | JsonResponse | Request | Git reset |
| POST | `/version-control/revert` | GitVersionControlController | gitRevert | JsonResponse | Request | Git revert |
| POST | `/version-control/init` | GitVersionControlController | gitInit | JsonResponse | Request | Git init |
| POST | `/version-control/clone` | GitVersionControlController | gitClone | JsonResponse | Request | Git clone |
| GET | `/version-control/conflicts` | GitVersionControlController | gitConflicts | JsonResponse | - | Git conflicts |
| POST | `/version-control/remote` | GitVersionControlController | gitRemote | JsonResponse | Request | Git remote |

**Route Names**: All routes use `version-control.{action}` naming convention

---

### 13. Focus Integration System
**Purpose**: ERP system integration and mapping  
**Type**: External system integration  

#### State Code Mappings
```php
Route::resource('state-code-mappings', StateCodeMappingController::class)->except(['show', 'create']);
```
**Resource Type**: Partial CRUD (excludes show, create)  
**Available Methods**: index, store, edit, update, destroy  

#### Department Code Mappings
```php
Route::resource('department-code-mappings', DepartmentCodeMappingController::class)->except(['show', 'create']);
```

#### Claim Focus Mappings
```php
Route::resource('claim-focus-mappings', ClaimFocusMappingController::class)->except(['show', 'create']);
```

#### State Field Promotion Mappings
```php
Route::resource('state-field-promotion-mappings', StateFieldPromotionMappingController::class)->except(['show', 'create']);
```

#### Department Vertical Mappings
```php
Route::resource('department-vertical-mappings', DepartmentVerticalMappingController::class)->except(['show', 'create']);
```

#### System Configurations
```php
Route::resource('system-configurations', SystemConfigurationController::class)->except(['show', 'create']);
```

**Common Pattern**: All Focus routes exclude `show` and `create` methods from the resource routes, providing only:
- `index()`: View - List items
- `store()`: JsonResponse - Create new item
- `edit()`: View - Edit form
- `update()`: JsonResponse - Update item
- `destroy()`: JsonResponse - Delete item

---

This completes the comprehensive backend routes documentation with proper type information and code structure analysis.