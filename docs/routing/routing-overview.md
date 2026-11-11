# Routing Overview

## Route File Structure

The application uses multiple route files to organize different types of routes:

### 1. web.php - Basic Web Routes
Contains basic authentication and public routes:

```php
// Authentication Routes
Route::match(['get', 'post'], '/', [AuthController::class, 'login'])->name('login');
Route::get('login', [AuthController::class, 'showLoginForm'])->name('login.form');
Route::get('xeasy_login', [AuthController::class, 'login']);
Route::post('logout', [AuthController::class, 'logout'])->name('logout');

// Protected Routes
Route::middleware('auth')->group(function () {
    Route::get('dashboard', [DashboardController::class, 'index'])->name('dashboard');
    Route::post('run-git-command', [GitController::class, 'runCommand']);
});
```

**Purpose**: Handles basic authentication flow and simple dashboard access.

### 2. backend.php - Admin Panel Routes
Contains all administrative functionality routes:

- **System Utilities**: Cache clearing, company management
- **Dashboard & Analytics**: Comprehensive dashboard with reports
- **Permission Management**: Roles, permissions, permission groups
- **User Management**: User CRUD, permissions assignment
- **Settings**: General settings, company configuration
- **Claims Management**: Claim types, claim processing
- **Reports**: Various report generation and exports
- **Focus Integration**: ERP mapping controllers
- **Version Control**: Git management interface
- **Data Operations**: Verification, mediation, extraction

**Middleware**: All routes are protected by `auth` middleware with additional permission-based middleware for specific sections.

### 3. api.php - API Routes
Contains API endpoints for external integrations:

```php
// Odometer Backdate API Routes
Route::get('odo-backdate-submission/{yearId}/{employeeId}/', [OdometerBackdateController::class, 'odoBackdateSubmission']);
Route::get('odo-backdate-submission-show/{yearId}/{employeeId}/', [OdometerBackdateController::class, 'odoBackdateSubmissionBtnShow']);
Route::get('odo-backdate-approval/{yearId}/{employeeId}/{expenseId}/', [OdometerBackdateController::class, 'odoBackdateApprove']);
Route::get('odo-backdate-rejection/{yearId}/{employeeId}/{expenseId}/', [OdometerBackdateController::class, 'odoBackdateReject']);
Route::get('odo-backdate-bulk-approval/{yearId}/{employeeId}/{empiid}', [OdometerBackdateController::class, 'odoBackdateBulkApproval']);
Route::get('odo-backdate-bulk-rejection/{yearId}/{employeeId}/{empiid}', [OdometerBackdateController::class, 'odoBackdateBulkRejection']);
```

**Purpose**: Provides API endpoints for mobile apps and external system integrations.

### 4. channels.php - Broadcast Channels
Defines broadcasting channels for real-time features:

```php
Broadcast::channel('App.Models.User.{id}', function ($user, $id) {
    return (int) $user->id === (int) $id;
});
```

**Purpose**: Handles WebSocket/broadcasting authorization.

### 5. console.php - Artisan Commands
Defines custom Artisan console commands:

```php
Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');
```

**Purpose**: Custom CLI commands for the application.

## Route Organization Strategy

### URL Structure
- **Public Routes**: `/` (login), `/login`, `/logout`
- **Admin Routes**: All admin functionality under authenticated middleware
- **API Routes**: `/api/*` for external integrations
- **Dashboard**: `/dashboard` for basic authenticated users

### Permission-Based Access
The backend routes use Laravel's permission middleware to control access:

```php
Route::middleware('permission:section_dashboard')->group(function () {
    // Dashboard routes
});

Route::middleware('permission:permission_management')->group(function () {
    // Permission management routes
});

Route::middleware('permission:user_management')->group(function () {
    // User management routes
});
```

### Route Prefixes and Naming
- **VNR Routes**: Prefix `vnr.` for VNR Seeds functionality
- **VVNR Routes**: Prefix `vvnr.` for VNR ESS functionality
- **Reports Routes**: Prefix `reports.` for report generation
- **Version Control**: Prefix `version-control.` for Git operations

## Key Differences Between web.php and backend.php

| Aspect | web.php | backend.php |
|--------|---------|-------------|
| **Purpose** | Basic authentication & simple dashboard | Complete admin panel functionality |
| **Routes Count** | ~6 routes | 200+ routes |
| **Middleware** | Basic `auth` | `auth` + permission-based |
| **Functionality** | Login/logout, basic dashboard | Full CRUD operations, reports, settings |
| **User Type** | All authenticated users | Admin users with specific permissions |
| **Complexity** | Simple | Complex with nested route groups |

## Route Loading Order
1. `web.php` - Basic web routes
2. `api.php` - API routes (with `/api` prefix)
3. `backend.php` - Admin routes (loaded within web middleware group)
4. `channels.php` - Broadcast channels
5. `console.php` - Console commands

This separation allows for:
- **Clean organization** of different route types
- **Easier maintenance** of specific functionality areas
- **Better security** through targeted middleware application
- **Scalability** for adding new route groups