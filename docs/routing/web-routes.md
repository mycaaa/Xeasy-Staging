# 🌐 Web Routes Documentation

This document covers the basic web routes defined in `routes/web.php`. These routes handle authentication and basic dashboard access.

## 📍 Route File Location
**File**: `routes/web.php`
**Purpose**: Basic authentication flow and simple dashboard access

## 🔓 Public Routes

These routes are accessible without authentication and handle basic application access.

### Authentication Routes

| Method | URI | Controller | Action | Description |
|--------|-----|------------|--------|-------------|
| GET/POST | `/` | AuthController | login | Main login endpoint |
| GET | `/login` | AuthController | showLoginForm | Display login form |
| GET | `/xeasy_login` | AuthController | login | Alternative login endpoint |
| POST | `/logout` | AuthController | logout | User logout |

## 🔐 Protected Routes

These routes require authentication (`auth` middleware).

### Dashboard Routes

| Method | URI | Controller | Action | Middleware | Description |
|--------|-----|------------|--------|------------|-------------|
| GET | `/dashboard` | DashboardController | index | auth | Main dashboard |
| POST | `/run-git-command` | GitController | runCommand | auth | Git command execution |

## 🎯 Route Details

### Authentication Flow

The web.php file handles the basic authentication flow:

```php
// Main login route (handles both GET and POST)
Route::match(['get', 'post'], '/', [AuthController::class, 'login'])->name('login');

// Alternative login form display
Route::get('login', [AuthController::class, 'showLoginForm'])->name('login.form');

// Alternative login endpoint
Route::get('xeasy_login', [AuthController::class, 'login']);

// Logout functionality
Route::post('logout', [AuthController::class, 'logout'])->name('logout');
```

### Protected Routes

```php
Route::middleware('auth')->group(function () {
    Route::get('dashboard', [DashboardController::class, 'index'])->name('dashboard');
    Route::post('run-git-command', [GitController::class, 'runCommand']);
});
```

## 🔄 Route Behavior

### Login Route (`/`)
- **GET Request**: Displays the login form
- **POST Request**: Processes login credentials
- **Redirect**: Successful login redirects to `/dashboard`

### Dashboard Route (`/dashboard`)
- **Purpose**: Basic dashboard for authenticated users
- **Access**: All authenticated users (no specific permissions required)
- **Content**: Basic dashboard information and navigation

### Git Command Route (`/run-git-command`)
- **Purpose**: Execute Git commands through web interface
- **Method**: POST only
- **Access**: Authenticated users only

## 🚦 Middleware

### Authentication Middleware
All protected routes use Laravel's built-in `auth` middleware:
- Redirects unauthenticated users to login page
- Maintains user session state
- Provides access to authenticated user data

## 🔗 Integration with Backend Routes

The web.php routes work in conjunction with backend.php:

| File | Purpose | User Type | Functionality |
|------|---------|-----------|---------------|
| **web.php** | Basic authentication & dashboard | All users | Login, logout, basic dashboard |
| **backend.php** | Admin functionality | Admin users | Full CRUD, reports, settings |

### Navigation Flow
1. User accesses `/` (login page)
2. After authentication, redirected to `/dashboard`
3. From dashboard, users access admin features (handled by backend.php routes)

## 🔒 Security Features

### CSRF Protection
All POST routes are protected by Laravel's CSRF middleware:
- Login form includes CSRF token
- Git command execution requires valid CSRF token

### Session Management
- User sessions are maintained across requests
- Automatic session cleanup on logout
- Session timeout handling

## 📊 Route Statistics

| Metric | Count |
|--------|-------|
| **Total Routes** | 6 |
| **Public Routes** | 4 |
| **Protected Routes** | 2 |
| **GET Routes** | 4 |
| **POST Routes** | 2 |

## 🎨 URL Structure

### Public URLs
- `/` - Main login page
- `/login` - Alternative login form
- `/xeasy_login` - Alternative login endpoint
- `/logout` - Logout (POST only)

### Protected URLs
- `/dashboard` - Main dashboard
- `/run-git-command` - Git operations (POST only)

## 🔍 Route Testing

### Authentication Testing
```php
// Test login page access
$this->get('/')->assertStatus(200);

// Test protected route without auth
$this->get('/dashboard')->assertRedirect('/login');

// Test protected route with auth
$this->actingAs($user)->get('/dashboard')->assertStatus(200);
```

### Logout Testing
```php
// Test logout functionality
$this->actingAs($user)
     ->post('/logout')
     ->assertRedirect('/');
```

## 📈 Performance Considerations

### Route Optimization
- Minimal route count for fast loading
- Simple middleware stack
- Efficient authentication checks

### Caching
- Routes are cached in production
- Session data cached for performance
- Static assets served efficiently

## 🔧 Configuration

### Route Configuration
Routes are loaded automatically by Laravel's RouteServiceProvider:

```php
// In RouteServiceProvider
Route::middleware('web')
     ->group(base_path('routes/web.php'));
```

### Middleware Configuration
```php
// Web middleware group includes:
'web' => [
    \App\Http\Middleware\EncryptCookies::class,
    \Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse::class,
    \Illuminate\Session\Middleware\StartSession::class,
    \Illuminate\View\Middleware\ShareErrorsFromSession::class,
    \App\Http\Middleware\VerifyCsrfToken::class,
    \Illuminate\Routing\Middleware\SubstituteBindings::class,
],
```

## 🔗 Related Documentation

- [Backend Routes](backend-routes.md) - Complete admin functionality routes
- [API Routes](api-routes.md) - External API endpoints  
- [Routing Overview](routing-overview.md) - Complete routing structure
- [Authentication System](../authentication.md) - User authentication details

---

The web.php routes provide a clean, simple foundation for user authentication and basic dashboard access, working seamlessly with the more complex backend.php administrative routes.

### System Utilities

| Method | URI | Controller | Action | Permission | Description |
|--------|-----|------------|--------|------------|-------------|
| GET | `/companies-list` | DatabaseSwitchController | getCompanies | - | Get available companies |
| GET | `/clear-caches` | Controller | clearAllCaches | - | Clear application caches |

### Financial Year Management

| Method | URI | Controller | Action | Permission | Description |
|--------|-----|------------|--------|------------|-------------|
| GET | `/financial` | FinancialYearController | index | - | List financial years |
| POST | `/financial` | FinancialYearController | store | - | Create financial year |
| GET | `/financial/{id}` | FinancialYearController | show | - | Show financial year |
| PUT | `/financial/{id}` | FinancialYearController | update | - | Update financial year |
| DELETE | `/financial/{id}` | FinancialYearController | destroy | - | Delete financial year |

### Dashboard & Analytics

All dashboard routes require `permission:section_dashboard`.

| Method | URI | Controller | Action | Description |
|--------|-----|------------|--------|-------------|
| GET | `/home` | HomeController | index | Dashboard home page |
| GET | `/home-data` | HomeController | getDashboardData | Dashboard statistics |
| POST | `/get-employee-trend` | HomeController | getEmployeeTrend | Employee expense trends |
| POST | `/export/expense-month-wise` | HomeController | exportExpenseMonthWise | Monthly expense export |
| POST | `/export/expense-department-wise` | HomeController | exportExpenseDepartmentWise | Department expense export |
| POST | `/export/expense-claim-type-wise` | HomeController | exportExpenseClaimTypeWise | Claim type export |
| GET | `/analytics/{page}` | HomeController | analytics | Analytics pages |
| GET | `/analytics-dashboard-data` | HomeController | analyticsDashboardData | Analytics data |
| POST | `/get-sub-departments` | HomeController | getSubDepartments | Sub-department list |
| POST | `/export-reports` | HomeController | exportReports | Multi-report export |
| GET | `/export/employee-running-report` | HomeController | exportEmployeeRunningKmReport | Employee KM report |
| GET | `/export/claim-exception-report` | HomeController | exportClaimExceptionReport | Exception report |
| GET | `/claim-exception-report` | HomeController | claimExceptionReport | Exception report view |
| GET | `/claim-exception-detail` | HomeController | getClaimExceptionDetail | Exception details |

### Permission Management

All permission routes require `permission:permission_management`.

| Method | URI | Controller | Action | Description |
|--------|-----|------------|--------|-------------|
| GET | `/permission-groups` | PermissionGroupController | index | List permission groups |
| POST | `/permission-groups` | PermissionGroupController | store | Create permission group |
| GET | `/permission-groups/{id}` | PermissionGroupController | show | Show permission group |
| PUT | `/permission-groups/{id}` | PermissionGroupController | update | Update permission group |
| DELETE | `/permission-groups/{id}` | PermissionGroupController | destroy | Delete permission group |
| GET | `/permissions` | PermissionController | index | List permissions |
| POST | `/permissions` | PermissionController | store | Create permission |
| GET | `/permissions/{id}` | PermissionController | show | Show permission |
| PUT | `/permissions/{id}` | PermissionController | update | Update permission |
| DELETE | `/permissions/{id}` | PermissionController | destroy | Delete permission |
| GET | `/permissions-old` | PermissionController | permissionOld | Legacy permissions |
| POST | `/permissions/assign` | PermissionController | assignPermissions | Assign permissions |
| GET | `/permissions-list` | PermissionController | getAllPermissions | All permissions list |

### Role Management

All role routes require `permission:master_roles`.

| Method | URI | Controller | Action | Description |
|--------|-----|------------|--------|-------------|
| GET | `/roles` | RolesController | index | List roles |
| POST | `/roles` | RolesController | store | Create role |
| GET | `/roles/{id}` | RolesController | show | Show role |
| PUT | `/roles/{id}` | RolesController | update | Update role |
| DELETE | `/roles/{id}` | RolesController | destroy | Delete role |
| GET | `/get-roles-list` | RolesController | getRoles | Roles dropdown |

### Claim Type Management

Routes require `permission:settings_claim_type`.

| Method | URI | Controller | Action | Description |
|--------|-----|------------|--------|-------------|
| GET | `/claim-types` | ClaimTypeController | index | List claim types |
| POST | `/claim-types` | ClaimTypeController | store | Create claim type |
| GET | `/claim-types/{id}` | ClaimTypeController | show | Show claim type |
| PUT | `/claim-types/{id}` | ClaimTypeController | update | Update claim type |
| DELETE | `/claim-types/{id}` | ClaimTypeController | destroy | Delete claim type |
| GET | `/claim-type/groups` | ClaimTypeController | getGroups | Claim type groups |
| POST | `/claim-type-list` | ClaimTypeController | claimTypeList | Claim types list |

### Activity Management

#### Activity Overview
Routes require `permission:settings_activity`.

| Method | URI | Controller | Action | Description |
|--------|-----|------------|--------|-------------|
| GET | `/activity` | ActivityController | index | Activity management |
| GET | `/activity/tab-content` | ActivityController | getTabContent | Tab content |

#### Expense Head Management
Routes require `permission:expense_head_management`.

| Method | URI | Controller | Action | Description |
|--------|-----|------------|--------|-------------|
| GET | `/expense-heads` | ExpenseHeadCategoryController | index | List expense heads |
| POST | `/expense-heads` | ExpenseHeadCategoryController | store | Create expense head |
| GET | `/expense-heads/{id}` | ExpenseHeadCategoryController | show | Show expense head |
| PUT | `/expense-heads/{id}` | ExpenseHeadCategoryController | update | Update expense head |
| DELETE | `/expense-heads/{id}` | ExpenseHeadCategoryController | destroy | Delete expense head |

#### Activity Category Management
Routes require `permission:activity_category_management`.

| Method | URI | Controller | Action | Description |
|--------|-----|------------|--------|-------------|
| GET | `/activity-categories` | ActivityCategoryController | index | List activity categories |
| POST | `/activity-categories` | ActivityCategoryController | store | Create activity category |
| GET | `/activity-categories/{id}` | ActivityCategoryController | show | Show activity category |
| PUT | `/activity-categories/{id}` | ActivityCategoryController | update | Update activity category |
| DELETE | `/activity-categories/{id}` | ActivityCategoryController | destroy | Delete activity category |

#### Activity Type Management
Routes require `permission:activity_type_management`.

| Method | URI | Controller | Action | Description |
|--------|-----|------------|--------|-------------|
| GET | `/activity-types` | ActivityTypeController | index | List activity types |
| POST | `/activity-types` | ActivityTypeController | store | Create activity type |
| GET | `/activity-types/{id}` | ActivityTypeController | show | Show activity type |
| PUT | `/activity-types/{id}` | ActivityTypeController | update | Update activity type |
| DELETE | `/activity-types/{id}` | ActivityTypeController | destroy | Delete activity type |
| GET | `/activity-type/categories` | ActivityTypeController | getCategories | Activity categories |
| GET | `/activity-type/departments` | ActivityTypeController | getDepartments | Departments list |

#### Activity Name Management
Routes require `permission:activity_name_management`.

| Method | URI | Controller | Action | Description |
|--------|-----|------------|--------|-------------|
| GET | `/activity-names` | ActivityNameController | index | List activity names |
| POST | `/activity-names` | ActivityNameController | store | Create activity name |
| GET | `/activity-names/{id}` | ActivityNameController | show | Show activity name |
| PUT | `/activity-names/{id}` | ActivityNameController | update | Update activity name |
| DELETE | `/activity-names/{id}` | ActivityNameController | destroy | Delete activity name |
| GET | `/activity-name/categories` | ActivityNameController | getCategories | Categories list |
| GET | `/activity-name/departments` | ActivityNameController | getDepartments | Departments list |
| GET | `/activity-name/verticals` | ActivityNameController | getVerticals | Verticals list |
| GET | `/activity-name/claim-types` | ActivityNameController | getClaimTypes | Claim types list |

### Expense Head Mapping Management

Routes require `permission:expense_head_mapping_management`.

| Method | URI | Controller | Action | Description |
|--------|-----|------------|--------|-------------|
| GET | `/expense-head-mappings` | ExpenseHeadMappingController | index | List mappings |
| POST | `/expense-head-mappings` | ExpenseHeadMappingController | store | Create mapping |
| GET | `/expense-head-mappings/{id}` | ExpenseHeadMappingController | show | Show mapping |
| PUT | `/expense-head-mappings/{id}` | ExpenseHeadMappingController | update | Update mapping |
| DELETE | `/expense-head-mappings/{id}` | ExpenseHeadMappingController | destroy | Delete mapping |
| POST | `/expense-head-mappings/toggle` | ExpenseHeadMappingController | toggle | Toggle mapping |
| GET | `/expense-head-mappings/activities` | ExpenseHeadMappingController | getActivities | Activities list |
| GET | `/expense-head-mappings/expense-heads` | ExpenseHeadMappingController | getExpenseHeads | Expense heads list |

### Vendor Management

Routes require `permission:vendor_management`.

| Method | URI | Controller | Action | Description |
|--------|-----|------------|--------|-------------|
| GET | `/vendors` | VendorListController | index | List vendors |
| POST | `/vendors` | VendorListController | store | Create vendor |
| GET | `/vendors/{id}` | VendorListController | show | Show vendor |
| PUT | `/vendors/{id}` | VendorListController | update | Update vendor |
| DELETE | `/vendors/{id}` | VendorListController | destroy | Delete vendor |
| GET | `/vendor-list/{type?}` | VendorListController | vendorList | Vendor list by type |
| GET | `/vendor-types` | VendorTypeController | getVendorTypes | Vendor types |

### User Management

Routes require `permission:user_management`.

| Method | URI | Controller | Action | Description |
|--------|-----|------------|--------|-------------|
| GET | `/users` | UsersController | index | List users |
| POST | `/users` | UsersController | store | Create user |
| GET | `/users/{id}` | UsersController | show | Show user |
| PUT | `/users/{id}` | UsersController | update | Update user |
| DELETE | `/users/{id}` | UsersController | destroy | Delete user |
| POST | `/users/data` | UsersController | datatable | Users datatable |
| GET | `/user/{id}/permission` | UsersController | getPermissionView | Permission view |
| GET | `/users/{id}/permissions` | PermissionController | getPermissions | User permissions |
| POST | `/users/{id}/permissions/assign` | PermissionController | assignPermission | Assign permission |
| POST | `/users/{id}/permissions/revoke` | PermissionController | revokePermission | Revoke permission |
| POST | `/users/{id}/claim-types/assign` | PermissionController | assignClaimType | Assign claim type |
| POST | `/users/{id}/claim-types/revoke` | PermissionController | revokeClaimType | Revoke claim type |
| POST | `/users/import` | UsersController | importEmployees | Import employees |
| GET | `/profile` | UsersController | profile | User profile |
| GET | `/user-activity` | UsersController | userActivity | User activity |
| GET | `/user-activity/data` | UsersController | userActivityData | Activity data |

### Menu Management

Routes require `permission:master_menus`.

| Method | URI | Controller | Action | Description |
|--------|-----|------------|--------|-------------|
| GET | `/menu` | MenuController | index | List menus |
| POST | `/menu` | MenuController | store | Create menu |
| GET | `/menu/{id}` | MenuController | show | Show menu |
| PUT | `/menu/{id}` | MenuController | update | Update menu |
| DELETE | `/menu/{id}` | MenuController | destroy | Delete menu |
| GET | `/menu-list` | MenuController | menuList | Menu list |
| GET | `/menu/log/{id}` | MenuController | getLogs | Menu logs |
| GET | `/search-menu-list` | MenuController | menuList | Search menu list |

### Settings Management

Routes require `permission:general_settings`.

| Method | URI | Controller | Action | Description |
|--------|-----|------------|--------|-------------|
| GET | `/settings` | SettingController | index | Settings page |
| GET | `/company` | SettingController | company | Company settings |
| GET | `/company-config/{id}` | SettingController | getCompanyConfig | Company config |
| POST | `/save-config` | SettingController | saveCompanyConfig | Save company config |
| POST | `/settings/theme` | SettingController | saveThemeSettings | Save theme settings |
| GET | `/general` | SettingController | getGeneralSettings | General settings |
| POST | `/general` | SettingController | saveGeneralSettings | Save general settings |
| GET | `/odo-backdate-approval` | SettingController | odoBackdateApproval | Odometer backdate approval |
| POST | `/odo-backdate-setting/{department_id}` | SettingController | updateOdoBackdateSetting | Update odometer setting |
| GET | `/access-running-km-setting` | SettingController | accessRunningKmSetting | Running KM access setting |
| POST | `/access-running-km-setting/{department_id}` | SettingController | updateAccessRunningKmSetting | Update KM access setting |

### Report Management

Routes require `permission:section_reports`.

| Method | URI | Controller | Action | Description |
|--------|-----|------------|--------|-------------|
| GET | `/claim-report` | ReportController | claimReport | Claim reports |
| GET | `/claim-stage` | ReportController | claimStage | Claim stage report |
| POST | `/export-claim-stage` | ReportController | exportClaimStage | Export claim stage |
| GET | `/daily-activity` | ReportController | dailyActivity | Daily activity report |
| POST | `/daily-activity/data` | ReportController | getDailyActivityData | Daily activity data |
| POST | `/daily-activity/export` | ReportController | exportDailyActivity | Export daily activity |
| POST | `/filter-claims` | ReportController | filterClaims | Filter claims |
| POST | `/expense-claims/export` | ReportController | export | Export expense claims |
| GET | `/top-rating-employee` | ReportController | topRatingEmployee | Top rating employees |
| POST | `/claims/return` | ReportController | returnClaim | Return claim |
| GET | `/same_date` | ReportController | sameDayClaimUpload | Same day claims |

### Additional Report Routes

| Method | URI | Controller | Action | Description |
|--------|-----|------------|--------|-------------|
| GET | `/employee_running_km` | ReportController | employeeRunningKm | Employee running KM |
| GET | `/getEmployeeRunningKm` | HomeController | getEmployeefningKm | Get employee running KM |

### Email Template Management

Routes require `permission:email_template_management`.

| Method | URI | Controller | Action | Description |
|--------|-----|------------|--------|-------------|
| GET | `/email-templates` | EmailTemplateController | index | List email templates |
| POST | `/email-template` | EmailTemplateController | store | Create email template |
| GET | `/email-template/{id}/edit` | EmailTemplateController | edit | Edit email template |
| PUT | `/email-template/{template}` | EmailTemplateController | update | Update email template |
| DELETE | `/email-template/{id}` | EmailTemplateController | destroy | Delete email template |
| GET | `/email-template/log/{template}` | EmailTemplateController | getLogs | Template logs |
| GET | `/email-template-list` | EmailTemplateController | templateList | Template list |
| GET | `/email-template-variables` | EmailTemplateController | getVariables | Template variables |

### Claim Management

#### Claim Type Management
Routes require `permission:claim_type_management`.

| Method | URI | Controller | Action | Description |
|--------|-----|------------|--------|-------------|
| GET | `/claim-type` | ClaimTypeController | index | Claim type management |

#### Claim Details & Operations

| Method | URI | Controller | Action | Description |
|--------|-----|------------|--------|-------------|
| GET | `/claim-detail` | ClaimViewController | getClaimDetailView | Claim detail view |
| GET | `/claims/detail-view` | ClaimViewController | getClaimDetailView | Claims detail view |
| GET | `/get-claim-types` | ClaimViewController | getActiveClaimTypes | Active claim types |
| GET | `/get-uploaded-files/{expid}/{claimid}` | ClaimViewController | viewUploadedFiles | View uploaded files |
| POST | `/get-varieties` | ClaimViewController | getVarieties | Get varieties |
| POST | `/upload-claim-files` | ClaimViewController | uploadClaimFiles | Upload claim files |
| POST | `/hold-claim` | ClaimViewController | actionHold | Hold claim |
| POST | `/draft-claim` | ClaimViewController | actionDraft | Draft claim |
| GET | `/employee-all-claims` | ClaimViewController | getEmployeeAllClaims | Employee all claims |
| GET | `/city-list/{category?}` | CityCategoryController | cityList | City list |
| GET | `/airport-list` | AirportController | airportList | Airport list |
| POST | `/calculate-rate` | RateController | calculateRateAjax | Calculate rate |
| POST | `/send-message` | ClaimViewController | sendMessage | Send message |

#### Claimant Operations

| Method | URI | Controller | Action | Description |
|--------|-----|------------|--------|-------------|
| GET | `/claimant` | ClaimantController | claimant | Claimant page |
| GET | `/claims` | ClaimantController | claims | Claims page |
| GET | `/claim-list/data` | ClaimantController | claimList | Claim list data |
| GET | `/odometer/data` | ClaimantController | getOdometerData | Odometer data |
| POST | `/odometer/upload` | ClaimantController | uploadAndExtract | Upload odometer |
| GET | `/claimant/draft/list` | ClaimantController | getDraftList | Draft list |
| POST | `/claimant/draft/delete` | ClaimantController | deleteDraft | Delete draft |
| POST | `/claimant/add/remark` | ClaimantController | addRemark | Add remark |
| GET | `/claimant/open/list` | ClaimantController | getOpenList | Open list |
| GET | `/claimant/open/day` | ClaimantController | getOpenDayDetails | Open day details |
| GET | `/claimant/close/list` | ClaimantController | getCloseList | Close list |
| GET | `/claimant/close/day` | ClaimantController | getCloseDayDetails | Close day details |
| POST | `/claimant/courier-detail` | ClaimantController | getCourierDetail | Courier detail |
| POST | `/claimant/courier-update` | ClaimantController | updateCourierDetail | Update courier |

#### Claim Submission Routes

| Method | URI | Controller | Action | Description |
|--------|-----|------------|--------|-------------|
| POST | `/da-claim/check-holiday` | ClaimantController | checkHoliday | Check holiday |
| POST | `/da-claim/submit` | ClaimantController | submitDaClaim | Submit DA claim |
| GET | `/da-claim/data` | ClaimantController | getDaData | DA claim data |
| GET | `/claim/upload-data` | ClaimantController | getUploadClaimData | Upload claim data |
| POST | `/claim/extract-data` | ClaimantController | extractClaimData | Extract claim data |
| POST | `/claim/upload-submit` | ClaimantController | submitUploadClaim | Submit upload claim |
| POST | `/activity-claim/submit` | ClaimantController | submitActivityClaim | Submit activity claim |

#### Helper Routes

| Method | URI | Controller | Action | Description |
|--------|-----|------------|--------|-------------|
| POST | `/get-cities` | ClaimViewController | getCities | Get cities |
| POST | `/get-hotels` | ClaimViewController | getHotels | Get hotels |
| POST | `/get-hotels-by-city` | ClaimViewController | getHotelsByCity | Hotels by city |
| POST | `/get-hotel-details` | ClaimViewController | getHotelDetails | Hotel details |
| POST | `/get-employees` | ClaimViewController | getEmployees | Get employees |
| POST | `/get-employees-by-ids` | ClaimViewController | getEmployeesByIds | Employees by IDs |
| POST | `/get-city-category` | ClaimViewController | getCityCategory | City category |

### VNR Approver Routes

All VNR approver routes are prefixed with `/vnr/` and named with `vnr.` prefix.

| Method | URI | Controller | Action | Description |
|--------|-----|------------|--------|-------------|
| GET | `/vnr/approver` | ClaimantController | approver | VNR approver page |
| GET | `/vnr/approver/pending/list` | VNRApproverController | getApproverPendingList | Pending list |
| GET | `/vnr/approver/pending/day` | VNRApproverController | getApproverPendingDay | Pending day |
| GET | `/vnr/approver/approved/list` | VNRApproverController | getApproverApprovedList | Approved list |
| GET | `/vnr/approver/approved/day` | VNRApproverController | getApproverApprovedDay | Approved day |
| POST | `/vnr/approver/claim/approve` | VNRApproverController | approveClaimByApprover | Approve claim |
| POST | `/vnr/approver/claim/submit` | VNRApproverController | submitClaimByApprover | Submit claim |
| GET | `/vnr/team/members/list` | VNRApproverController | getTeamMembersList | Team members |
| GET | `/vnr/team/all/expenses` | VNRApproverController | getAllTeamExpenses | All team expenses |
| GET | `/vnr/team/member/expenses` | VNRApproverController | getMemberExpenses | Member expenses |
| GET | `/vnr/team/member/expenses/by-claim` | VNRApproverController | getMemberExpensesByClaim | Expenses by claim |

### VVNR Approver Routes

All VVNR approver routes are prefixed with `/vvnr/` and named with `vvnr.` prefix.

| Method | URI | Controller | Action | Description |
|--------|-----|------------|--------|-------------|
| GET | `/vvnr/approver` | ClaimantController | approver | VVNR approver page |
| GET | `/vvnr/approver/pending/list` | VVNRApproverController | getApproverPendingList | Pending list |
| GET | `/vvnr/approver/pending/day` | VVNRApproverController | getApproverPendingDay | Pending day |
| GET | `/vvnr/approver/approved/list` | VVNRApproverController | getApproverApprovedList | Approved list |
| GET | `/vvnr/approver/approved/day` | VVNRApproverController | getApproverApprovedDay | Approved day |
| POST | `/vvnr/approver/claim/approve` | VVNRApproverController | approveClaimByApprover | Approve claim |
| POST | `/vvnr/approver/claim/submit` | VVNRApproverController | submitClaimByApprover | Submit claim |
| GET | `/vvnr/team/members/list` | VVNRApproverController | getTeamMembersList | Team members |
| GET | `/vvnr/team/all/expenses` | VVNRApproverController | getAllTeamExpenses | All team expenses |
| GET | `/vvnr/team/member/expenses` | VVNRApproverController | getMemberExpenses | Member expenses |
| GET | `/vvnr/team/member/expenses/by-claim` | VVNRApproverController | getMemberExpensesByClaim | Expenses by claim |

### Employee Management

| Method | URI | Controller | Action | Description |
|--------|-----|------------|--------|-------------|
| POST | `/employees/search` | EmployeeController | search | Search employees |
| GET | `/employee/{id}` | EmployeeController | employee | Employee details |
| POST | `/employees/by-department` | FilterController | getEmployeesByDepartment | Employees by department |
| POST | `/verticals/by-function` | FilterController | getVerticalsByFunction | Verticals by function |
| POST | `/departments/by-vertical` | FilterController | getDepartmentsByVertical | Departments by vertical |
| POST | `/sub-departments/by-department` | FilterController | getSubDepartmentsByDepartment | Sub-departments |

### API Management

| Method | URI | Controller | Action | Description |
|--------|-----|------------|--------|-------------|
| GET | `/api-manager` | APIManagerController | index | API manager |
| POST | `/api-manager` | APIManagerController | store | Create API config |
| GET | `/api-manager/{id}` | APIManagerController | show | Show API config |
| PUT | `/api-manager/{id}` | APIManagerController | update | Update API config |
| DELETE | `/api-manager/{id}` | APIManagerController | destroy | Delete API config |
| GET | `/api/fields-mapping/{claim_id}` | APIManagerController | showMappingPage | Field mapping page |
| POST | `/api/fields-mapping/{claim_id}` | APIManagerController | storeFieldMapping | Store field mapping |
| GET | `/api/fields/mapping/{claim_id}` | APIManagerController | getFieldMappings | Get field mappings |
| GET | `/api/tables` | APIManagerController | getTables | Get tables |
| GET | `/api/columns/{table}` | APIManagerController | getColumns | Get columns |
| POST | `/api/map-fields` | APIManagerController | mapFields | Map fields |

### Expense Management

| Method | URI | Controller | Action | Description |
|--------|-----|------------|--------|-------------|
| GET | `/generate-expense-pdf` | ExpenseDetailsController | generatePdf | Generate expense PDF |

### Data Punch Operations

Routes require `permission:section_data_punching`.

| Method | URI | Controller | Action | Description |
|--------|-----|------------|--------|-------------|
| GET | `/data-punch/{status}` | MediatorController | dataPunch | Data punch page |
| POST | `/data-punch/{status}/data` | MediatorController | dataPunchData | Data punch data |
| POST | `/data-punch/{status}/filter-options` | MediatorController | getFilteredOptions | Filter options |

### Section Verifier Operations

Routes require `permission:section_verifier`.

#### Running KM Verification

| Method | URI | Controller | Action | Description |
|--------|-----|------------|--------|-------------|
| GET | `/verify-running-km` | VerifierController | verifyRunningKm | Verify running KM |
| POST | `/verify-running-km/data` | VerifierController | verifyRunningKmData | Running KM data |
| POST | `/verify-running-km/update` | VerifierController | updateRunningKm | Update running KM |
| POST | `/verify-running-km/verify` | VerifierController | verifyRunningKmRecord | Verify KM record |

#### Employee Vehicle Management

| Method | URI | Controller | Action | Description |
|--------|-----|------------|--------|-------------|
| GET | `/employee-vehicles` | EmployeeVehicleController | employeeVehicles | Employee vehicles |
| POST | `/employee-vehicles/data` | EmployeeVehicleController | getEmployeeVehiclesData | Vehicle data |
| POST | `/employee-vehicles/policy-details` | EmployeeVehicleController | getPolicyDetails | Policy details |
| POST | `/employee-vehicles/running-km` | EmployeeVehicleController | getRunningKmDetails | Running KM details |
| POST | `/employee-vehicles/documents` | EmployeeVehicleController | getVehicleDocuments | Vehicle documents |
| POST | `/employee/vehicles/export` | EmployeeVehicleController | exportVehicles | Export vehicles |

#### Claim Verification

| Method | URI | Controller | Action | Description |
|--------|-----|------------|--------|-------------|
| GET | `/verify-pending` | VerifierController | verifyPendingClaims | Verify pending claims |
| POST | `/verify-pending-claims/data` | VerifierController | verifyPendingClaimsData | Pending claims data |
| POST | `/verify-approved-claims/data` | VerifierController | verifyApprovedClaimsData | Approved claims data |
| POST | `/verify-pending/claim-return` | VerifierController | returnClaim | Return claim |
| POST | `/verify-pending/claim-submit` | VerifierController | submitToApprover | Submit to approver |
| GET | `/verify-approved` | VerifierController | verifyApprovedClaims | Verify approved claims |
| POST | `/verify-pending/get-claims` | VerifierController | getEmployeeClaimsAjax | Get employee claims |
| POST | `/verify-pending/verify-single-claim` | VerifierController | verifySingleClaim | Verify single claim |

#### Courier Detail Management
Routes require `permission:pending_claims_courier_detail`.

| Method | URI | Controller | Action | Description |
|--------|-----|------------|--------|-------------|
| POST | `/verify-pending/courier-detail` | VerifierController | getCourierDetail | Get courier detail |
| POST | `/verify-pending/courier-update` | VerifierController | updateCourierDetail | Update courier detail |

### Utility Routes

| Method | URI | Controller | Action | Description |
|--------|-----|------------|--------|-------------|
| GET | `/temp_files` | UtilityController | temp_files | Temporary files |
| DELETE | `/temp-files/delete` | UtilityController | delete | Delete temp file |
| POST | `/temp-files/bulk-delete` | UtilityController | bulkDelete | Bulk delete |
| POST | `/temp-files/delete-all` | UtilityController | deleteAll | Delete all |
| GET | `/temp-files/download/{filename}` | UtilityController | download | Download file |
| POST | `/temp-files/bulk-download` | UtilityController | bulkDownload | Bulk download |

### Version Control Routes

| Method | URI | Controller | Action | Description |
|--------|-----|------------|--------|-------------|
| GET | `/version-control` | GitVersionControlController | index | Version control |
| GET | `/version-control/diagnostics` | GitDiagnosticsController | diagnose | Git diagnostics |
| GET | `/version-control/status` | GitVersionControlController | gitStatus | Git status |
| POST | `/version-control/add` | GitVersionControlController | gitAdd | Git add |
| POST | `/version-control/commit` | GitVersionControlController | gitCommit | Git commit |
| POST | `/version-control/push` | GitVersionControlController | gitPush | Git push |
| POST | `/version-control/pull` | GitVersionControlController | gitPull | Git pull |
| POST | `/version-control/fetch` | GitVersionControlController | gitFetch | Git fetch |
| POST | `/version-control/checkout` | GitVersionControlController | gitCheckout | Git checkout |
| GET | `/version-control/log` | GitVersionControlController | gitLog | Git log |
| GET | `/version-control/diff` | GitVersionControlController | gitDiff | Git diff |
| POST | `/version-control/stash` | GitVersionControlController | gitStash | Git stash |
| POST | `/version-control/create-branch` | GitVersionControlController | gitCreateBranch | Create branch |
| POST | `/version-control/delete-branch` | GitVersionControlController | gitDeleteBranch | Delete branch |
| POST | `/version-control/merge` | GitVersionControlController | gitMerge | Git merge |
| POST | `/version-control/tag` | GitVersionControlController | gitTag | Git tag |
| POST | `/version-control/reset` | GitVersionControlController | gitReset | Git reset |
| POST | `/version-control/revert` | GitVersionControlController | gitRevert | Git revert |
| POST | `/version-control/init` | GitVersionControlController | gitInit | Git init |
| POST | `/version-control/clone` | GitVersionControlController | gitClone | Git clone |
| GET | `/version-control/conflicts` | GitVersionControlController | gitConflicts | Git conflicts |
| POST | `/version-control/remote` | GitVersionControlController | gitRemote | Git remote |

### Extract Reports

Routes are prefixed with `/reports/` and named with `reports.` prefix.

| Method | URI | Controller | Action | Description |
|--------|-----|------------|--------|-------------|
| GET | `/reports/extract` | ExtractController | extract | Extract reports |
| POST | `/reports/extract/data` | ExtractController | getData | Extract data |
| GET | `/reports/extract/details/{id}` | ExtractController | getDetails | Extract details |
| GET | `/reports/extract/export` | ExtractController | export | Export extract |

### Focus Export Routes

| Method | URI | Controller | Action | Description |
|--------|-----|------------|--------|-------------|
| GET | `/state-code-mappings` | StateCodeMappingController | index | State code mappings |
| POST | `/state-code-mappings` | StateCodeMappingController | store | Create state mapping |
| GET | `/state-code-mappings/{id}/edit` | StateCodeMappingController | edit | Edit state mapping |
| PUT | `/state-code-mappings/{id}` | StateCodeMappingController | update | Update state mapping |
| DELETE | `/state-code-mappings/{id}` | StateCodeMappingController | destroy | Delete state mapping |
| GET | `/department-code-mappings` | DepartmentCodeMappingController | index | Department mappings |
| POST | `/department-code-mappings` | DepartmentCodeMappingController | store | Create dept mapping |
| GET | `/department-code-mappings/{id}/edit` | DepartmentCodeMappingController | edit | Edit dept mapping |
| PUT | `/department-code-mappings/{id}` | DepartmentCodeMappingController | update | Update dept mapping |
| DELETE | `/department-code-mappings/{id}` | DepartmentCodeMappingController | destroy | Delete dept mapping |
| GET | `/claim-focus-mappings` | ClaimFocusMappingController | index | Claim focus mappings |
| POST | `/claim-focus-mappings` | ClaimFocusMappingController | store | Create claim mapping |
| GET | `/claim-focus-mappings/{id}/edit` | ClaimFocusMappingController | edit | Edit claim mapping |
| PUT | `/claim-focus-mappings/{id}` | ClaimFocusMappingController | update | Update claim mapping |
| DELETE | `/claim-focus-mappings/{id}` | ClaimFocusMappingController | destroy | Delete claim mapping |
| GET | `/state-field-promotion-mappings` | StateFieldPromotionMappingController | index | State field mappings |
| POST | `/state-field-promotion-mappings` | StateFieldPromotionMappingController | store | Create field mapping |
| GET | `/state-field-promotion-mappings/{id}/edit` | StateFieldPromotionMappingController | edit | Edit field mapping |
| PUT | `/state-field-promotion-mappings/{id}` | StateFieldPromotionMappingController | update | Update field mapping |
| DELETE | `/state-field-promotion-mappings/{id}` | StateFieldPromotionMappingController | destroy | Delete field mapping |
| GET | `/department-vertical-mappings` | DepartmentVerticalMappingController | index | Department vertical mappings |
| POST | `/department-vertical-mappings` | DepartmentVerticalMappingController | store | Create vertical mapping |
| GET | `/department-vertical-mappings/{id}/edit` | DepartmentVerticalMappingController | edit | Edit vertical mapping |
| PUT | `/department-vertical-mappings/{id}` | DepartmentVerticalMappingController | update | Update vertical mapping |
| DELETE | `/department-vertical-mappings/{id}` | DepartmentVerticalMappingController | destroy | Delete vertical mapping |
| GET | `/department-vertical-mappings` | DepartmentVerticalMappingController | index | Department vertical mappings |
| POST | `/department-vertical-mappings` | DepartmentVerticalMappingController | store | Create vertical mapping |
| GET | `/department-vertical-mappings/{id}/edit` | DepartmentVerticalMappingController | edit | Edit vertical mapping |
| PUT | `/department-vertical-mappings/{id}` | DepartmentVerticalMappingController | update | Update vertical mapping |
| DELETE | `/department-vertical-mappings/{id}` | DepartmentVerticalMappingController | destroy | Delete vertical mapping |
| GET | `/system-configurations` | SystemConfigurationController | index | System configurations |
| POST | `/system-configurations` | SystemConfigurationController | store | Create system config |
| GET | `/system-configurations/{id}/edit` | SystemConfigurationController | edit | Edit system config |
| PUT | `/system-configurations/{id}` | SystemConfigurationController | update | Update system config |
| DELETE | `/system-configurations/{id}` | SystemConfigurationController | destroy | Delete system config |

**Note**: All Focus routes exclude `show` and `create` methods from the resource routes.

## 🔍 Route Patterns & Conventions

### Permission-Based Access Control

Routes are organized by permission groups:
- `section_dashboard` - Dashboard and analytics
- `permission_management` - Permission system
- `master_roles` - Role management
- `settings_claim_type` - Claim type settings
- `user_management` - User operations
- `section_reports` - Reporting features
- `section_verifier` - Verification workflows
- `section_data_punching` - Data entry operations

### RESTful Resource Routes

Many controllers use Laravel's resource routing:
```php
Route::resource('users', UsersController::class);
// Generates: index, create, store, show, edit, update, destroy
```

### Nested Route Groups

Routes are logically grouped by feature:
- VNR/VVNR approver workflows
- Version control operations
- API management
- Employee and vehicle management

### AJAX Endpoints

Many routes return JSON for AJAX requests:
- Data tables (`/data` suffix)
- Export operations (`/export` prefix)
- Filter operations (`/filter-` prefix)
- Get operations (`/get-` prefix)

---

This comprehensive routing structure provides a well-organized, secure, and scalable foundation for the Xeasy Laravel expense management system.