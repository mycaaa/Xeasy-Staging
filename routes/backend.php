<?php

use App\Http\Controllers\AccountSummaryController;
use App\Http\Controllers\admin\ActivityCategoryController;
use App\Http\Controllers\admin\ActivityController;
use App\Http\Controllers\admin\ActivityLogController;
use App\Http\Controllers\admin\ActivityNameController;
use App\Http\Controllers\admin\ActivityTypeController;
use App\Http\Controllers\admin\AdvanceFinanceController;
use App\Http\Controllers\admin\AdvanceReportController;
use App\Http\Controllers\admin\AdvanceRequestAdminController;
use App\Http\Controllers\admin\AdvanceRequestMasterController;
use App\Http\Controllers\admin\AirportController;
use App\Http\Controllers\admin\APIManagerController;
use App\Http\Controllers\admin\CityCategoryController;
use App\Http\Controllers\admin\ClaimantController;
use App\Http\Controllers\admin\ClaimTypeController;
use App\Http\Controllers\admin\ClaimViewController;
use App\Http\Controllers\admin\EmailTemplateController;
use App\Http\Controllers\admin\EmployeeController;
use App\Http\Controllers\admin\EmployeeVehicleController;
use App\Http\Controllers\admin\ExpenseDetailsController;
use App\Http\Controllers\admin\ExpenseHeadCategoryController;
use App\Http\Controllers\admin\ExpenseHeadMappingController;
use App\Http\Controllers\admin\FilterController;
use App\Http\Controllers\admin\FinancialYearController;
use App\Http\Controllers\admin\focus\ClaimFocusMappingController;
use App\Http\Controllers\admin\focus\DepartmentCodeMappingController;
use App\Http\Controllers\admin\focus\DepartmentVerticalMappingController;
use App\Http\Controllers\admin\focus\StateCodeMappingController;
use App\Http\Controllers\admin\focus\StateFieldPromotionMappingController;
use App\Http\Controllers\admin\focus\SystemConfigurationController;
use App\Http\Controllers\admin\git\GitDiagnosticsController;
use App\Http\Controllers\admin\git\GitVersionControlController;
use App\Http\Controllers\admin\MediatorController;
use App\Http\Controllers\admin\MenuController;
use App\Http\Controllers\admin\NotificationController;
use App\Http\Controllers\admin\NotShowingReportController;
use App\Http\Controllers\admin\NurseryApproverController;
use App\Http\Controllers\admin\OdometerAnalysisReportController;
use App\Http\Controllers\admin\OdometerContinuityReportController;
use App\Http\Controllers\admin\OdometerTrackingReportController;
use App\Http\Controllers\admin\OpenMonthController;
use App\Http\Controllers\admin\OtherParticipantCategoryController;
use App\Http\Controllers\admin\PermissionController;
use App\Http\Controllers\admin\PermissionGroupController;
use App\Http\Controllers\admin\RateController;
use App\Http\Controllers\admin\ReportController;
use App\Http\Controllers\admin\reports\ExtractController;
use App\Http\Controllers\admin\RolesController;
use App\Http\Controllers\admin\S3TransferController;
use App\Http\Controllers\admin\SettingController;
use App\Http\Controllers\admin\SystemNotificationController;
use App\Http\Controllers\admin\UsersController;
use App\Http\Controllers\admin\UtilityController;
use App\Http\Controllers\admin\VendorListController;
use App\Http\Controllers\admin\VendorTypeController;
use App\Http\Controllers\admin\VerifierController;
use App\Http\Controllers\admin\VNRApproverController;
use App\Http\Controllers\admin\VVNRApproverController;
use App\Http\Controllers\AdvanceRequestViewController;
use App\Http\Controllers\ClaimController;
use App\Http\Controllers\ClaimReportController;
use App\Http\Controllers\Controller;
use App\Http\Controllers\DatabaseSwitchController;
use App\Http\Controllers\ExpensePaymentController;
use App\Http\Controllers\Finance\AdvanceApprovalsController;
use App\Http\Controllers\HomeController;
use App\Http\Controllers\MatrixPaymentController;
use App\Http\Controllers\PrintDetailsController;
use App\Http\Controllers\VerifierClaimantController;
use Illuminate\Support\Facades\Route;

// Protected Routes (Requires Authentication)
Route::middleware('auth')->group(function () {
    // FILES-01 (UAT-03): authorised downloads for exports and Pax documents that used to be public.
    // Permission for exports: config/route_access.php (exports/download, storage/temp).
    Route::get('exports/download/{file}', [\App\Http\Controllers\PrivateFileController::class, 'export'])
        ->where('file', \App\Support\PrivateExport::NAME)->name('exports.download');
    Route::get('storage/temp/{file}', [\App\Http\Controllers\PrivateFileController::class, 'export'])
        ->where('file', \App\Support\PrivateExport::NAME)->name('exports.download.legacy');
    Route::get('uploads/pax_documents/{year}/{claim}/{file}', [\App\Http\Controllers\PrivateFileController::class, 'pax'])
        ->whereNumber('year')->whereNumber('claim')->where('file', '[A-Za-z0-9_.\-]+')->name('pax.document');

    // System Utilities
    // Routes for system-wide utilities like cache clearing and company listing
    Route::get('companies-list', [DatabaseSwitchController::class, 'getCompanies']);
    Route::get('clear-caches', [Controller::class, 'clearAllCaches']);
    // System Requirements / Feature Tracker
    Route::get('system-requirements', fn () => view('admin.requirements'))->name('system.requirements');

    // System Alerts & Notifications Details Page
    Route::get('system-notifications', [SystemNotificationController::class, 'index'])->name('admin.system-notifications.index');
    Route::get('api/system-notifications/summary', [SystemNotificationController::class, 'getSummary'])->name('admin.system-notifications.summary');

    // S3 Storage Transfer Utility & File Manager
    Route::get('admin/s3-transfer', [S3TransferController::class, 'index'])->name('admin.s3-transfer.index');
    Route::post('admin/s3-transfer/process', [S3TransferController::class, 'process'])->name('admin.s3-transfer.process');
    Route::post('admin/s3-transfer/explore', [S3TransferController::class, 'explore'])->name('admin.s3-transfer.explore');
    Route::post('admin/s3-transfer/delete', [S3TransferController::class, 'delete'])->name('admin.s3-transfer.delete');
    Route::post('admin/s3-transfer/rename', [S3TransferController::class, 'rename'])->name('admin.s3-transfer.rename');
    Route::post('admin/s3-transfer/create-folder', [S3TransferController::class, 'createFolder'])->name('admin.s3-transfer.create-folder');
    Route::post('admin/s3-transfer/upload', [S3TransferController::class, 'upload'])->name('admin.s3-transfer.upload');
    Route::post('admin/s3-transfer/bulk-delete', [S3TransferController::class, 'bulkDelete'])->name('admin.s3-transfer.bulk-delete');

    // Developer Documentation (auth-protected)
    // Route::get('developer-guide', fn() => response()->file(public_path('docs/developer-guide.html')))->name('developer.guide');

    // REQ-001: Imprest Automation
    Route::prefix('imprest-automation')->name('imprest.')->group(function () {
        Route::get('/', [\App\Http\Controllers\admin\ImprestAutomationController::class, 'index'])->name('index');
        Route::get('/missing-imprest', [\App\Http\Controllers\admin\ImprestAutomationController::class, 'missingImprest'])->name('missing');
        // Tracker #36: approve / deny / release limited to Finance (permission imprest.approve)
        Route::middleware('permission:imprest.approve')->group(function () {
            Route::post('{id}/approve', [\App\Http\Controllers\admin\ImprestAutomationController::class, 'approve'])->name('approve');
            Route::post('{id}/reject', [\App\Http\Controllers\admin\ImprestAutomationController::class, 'reject'])->name('reject');
            Route::post('missing-imprest/{employeeId}/release', [\App\Http\Controllers\admin\ImprestAutomationController::class, 'releaseMissing'])->name('release-missing');
            Route::post('missing-imprest/{employeeId}/reject', [\App\Http\Controllers\admin\ImprestAutomationController::class, 'rejectMissing'])->name('reject-missing');
        });
        Route::post('run-manual', [\App\Http\Controllers\admin\ImprestAutomationController::class, 'runManual'])->name('run-manual');
        Route::get('clean-raised-by', [\App\Http\Controllers\admin\ImprestAutomationController::class, 'cleanRaisedBy'])->name('clean-raised-by');
    });

    // Employee Master Report
    Route::get('employee-report', [\App\Http\Controllers\admin\EmployeeReportController::class, 'index'])->name('employee.report');
    Route::get('employee-report/export', [\App\Http\Controllers\admin\EmployeeReportController::class, 'export'])->name('employee.report.export');
    // Company/Year Switching API
    Route::post('api/switch-company-year', [DatabaseSwitchController::class, 'switchCompanyYear']);
    Route::get('api/financial-years/{company}', [DatabaseSwitchController::class, 'getFinancialYears']);
    // Financial Year Management
    // Routes for managing financial years
    Route::resource('financial', FinancialYearController::class);
    // Dashboard Routes
    // Routes for dashboard data and employee trends
    Route::middleware('permission:section_dashboard')->group(function () {
        Route::get('home', [HomeController::class, 'index']);
        Route::get('home-data', [HomeController::class, 'getDashboardData']);
        Route::post('get-employee-trend', [HomeController::class, 'getEmployeeTrend']);
        Route::post('export/expense-month-wise', [HomeController::class, 'exportExpenseMonthWise']);
        Route::post('export/expense-department-wise', [HomeController::class, 'exportExpenseDepartmentWise']);
        Route::post('export/expense-claim-type-wise', [HomeController::class, 'exportExpenseClaimTypeWise']);
        Route::get('analytics/{page}', [HomeController::class, 'analytics']);
        Route::get('analytics-dashboard-data', [HomeController::class, 'analyticsDashboardData']);
        Route::post('get-sub-departments', [HomeController::class, 'getSubDepartments']);
        Route::post('export-reports', [HomeController::class, 'exportReports']);
        Route::get('export/employee-running-report', [HomeController::class, 'exportEmployeeRunningKmReport']);
        Route::get('export/claim-exception-report', [HomeController::class, 'exportClaimExceptionReport']);
        Route::get('claim-exception-report', [HomeController::class, 'claimExceptionReport']);
        Route::post('update-final-amount', [HomeController::class, 'updateFinalAmount']);
        Route::get('claim-exception-detail', [HomeController::class, 'getClaimExceptionDetail']);
    });
    // Permission Management
    // Routes for managing permission groups, permissions, and role assignments
    Route::middleware('permission:permission_management')->group(function () {
        Route::resource('permission-groups', PermissionGroupController::class);
        Route::resource('permissions', PermissionController::class);
        Route::get('permissions-old', [PermissionController::class, 'permissionOld']);
        Route::post('permissions/assign', [PermissionController::class, 'assignPermissions']);
        Route::get('permissions-list', [PermissionController::class, 'getAllPermissions']);
    });
    // Role Management
    // Routes for managing roles
    Route::middleware('permission:master_roles')->group(function () {
        Route::resource('roles', RolesController::class);
        Route::get('get-roles-list', [RolesController::class, 'getRoles']);
    });
    // Claim Types
    // Routes for claim types
    Route::middleware('permission:settings_claim_type')->group(function () {
        Route::resource('claim-types', ClaimTypeController::class)->except(['create', 'show']); // UAT-03: these actions do not exist
        Route::get('claim-type/groups', [ClaimTypeController::class, 'getGroups']);
        Route::post('claim-type-list', [ClaimTypeController::class, 'claimTypeList']);
    });
    Route::middleware('permission:settings_activity')->group(function () {
        Route::get('activity', [ActivityController::class, 'index'])->name('activity.index');
    });
    Route::middleware('permission:expense_head_management')->group(function () {
        Route::resource('expense-heads', ExpenseHeadCategoryController::class)->except(['create', 'show']); // UAT-03: these actions do not exist
    });
    Route::middleware('permission:activity_category_management')->group(function () {
        Route::resource('activity-categories', ActivityCategoryController::class)->except(['create', 'show']); // UAT-03: these actions do not exist
    });
    Route::resource('other-participant-categories', OtherParticipantCategoryController::class)->except(['create', 'show']); // UAT-03: these actions do not exist
    Route::post('other-participant-categories/toggle-status/{id}', [OtherParticipantCategoryController::class, 'toggleStatus']);
    Route::get('other-participant-categories-list', [OtherParticipantCategoryController::class, 'categoryList']);

    // City Category Master Management
    Route::get('city-categories/export', [CityCategoryController::class, 'export'])->name('city-categories.export');
    Route::get('city-categories/logs', [CityCategoryController::class, 'logs'])->name('city-categories.logs');
    Route::get('city-categories/logs/export', [CityCategoryController::class, 'exportLogs'])->name('city-categories.logs.export');
    Route::get('city-categories/data', [CityCategoryController::class, 'getData'])->name('city-categories.data');
    Route::resource('city-categories', CityCategoryController::class)->except(['create']); // UAT-03: these actions do not exist

    // City Category Master Management
    Route::get('city-categories/export', [CityCategoryController::class, 'export'])->name('city-categories.export');
    Route::get('city-categories/logs', [CityCategoryController::class, 'logs'])->name('city-categories.logs');
    Route::get('city-categories/logs/export', [CityCategoryController::class, 'exportLogs'])->name('city-categories.logs.export');
    Route::get('city-categories/data', [CityCategoryController::class, 'getData'])->name('city-categories.data');
    Route::resource('city-categories', CityCategoryController::class)->except(['create']); // UAT-03: these actions do not exist
    Route::middleware('permission:activity_type_management')->group(function () {
        Route::resource('activity-types', ActivityTypeController::class)->except(['create', 'show']); // UAT-03: these actions do not exist
        Route::get('activity-type/categories', [ActivityTypeController::class, 'getCategories'])->name('activity.types.categories');
        Route::get('activity-type/departments', [ActivityTypeController::class, 'getDepartments'])->name('activity.types.departments');
    });
    Route::middleware('permission:activity_name_management')->group(function () {
        Route::resource('activity-names', ActivityNameController::class)->except(['create', 'show']); // UAT-03: these actions do not exist
        Route::get('activity-name/categories', [ActivityNameController::class, 'getCategories'])->name('activity.names.categories');
        Route::get('activity-name/departments', [ActivityNameController::class, 'getDepartments'])->name('activity.names.departments');
        Route::get('activity-name/verticals', [ActivityNameController::class, 'getVerticals'])->name('activity.names.verticals');
        Route::get('activity-name/claim-types', [ActivityNameController::class, 'getClaimTypes'])->name('activity.names.claim-types');
    });
    Route::middleware('permission:expense_head_mapping_management')->group(function () {
        Route::resource('expense-head-mappings', ExpenseHeadMappingController::class)->only(['index']); // UAT-03: only index exists; show shadowed /activities and /expense-heads
        Route::post('expense-head-mappings/toggle', [ExpenseHeadMappingController::class, 'toggle'])->name('expense-head-mappings.toggle');
        Route::get('expense-head-mappings/activities', [ExpenseHeadMappingController::class, 'getActivities'])->name('expense-head-mappings.activities');
        Route::get('expense-head-mappings/expense-heads', [ExpenseHeadMappingController::class, 'getExpenseHeads'])->name('expense-head-mappings.expense-heads');
    });
    Route::middleware('permission:vendor_management')->group(function () {
        Route::get('vendors/data', [VendorListController::class, 'data'])->name('vendors.data'); // PERF-07
        Route::resource('vendors', VendorListController::class)->except(['create', 'show']); // UAT-03: these actions do not exist
        Route::get('vendor-types', [VendorTypeController::class, 'getVendorTypes']);
    });
    Route::get('vendor-list/{type?}', [VendorListController::class, 'vendorList']);
    // User Management
    // Routes for user management, including permissions and profiles
    Route::middleware('permission:user_management')->group(function () {
        Route::resource('users', UsersController::class);
        Route::post('users/data', [UsersController::class, 'datatable'])->name('users.datatable');
        Route::get('user/{id}/permission', [UsersController::class, 'getPermissionView']);
        Route::get('users/{id}/permissions', [PermissionController::class, 'getPermissions']);
        Route::post('users/{id}/permissions/assign', [PermissionController::class, 'assignPermission']);
        Route::post('users/{id}/permissions/revoke', [PermissionController::class, 'revokePermission']);
        Route::post('users/{id}/claim-types/assign', [PermissionController::class, 'assignClaimType']);
        Route::post('users/{id}/claim-types/revoke', [PermissionController::class, 'revokeClaimType']);
        Route::post('users/import', [UsersController::class, 'importEmployees'])->name('users.import');
        Route::get('profile', [UsersController::class, 'profile']);
        Route::get('user-activity', [UsersController::class, 'userActivity']);
        Route::get('user-activity/data', [UsersController::class, 'userActivityData']);
    });
    // Menu Management
    // Routes for managing menus and their logs
    Route::middleware('permission:master_menus')->group(function () {
        Route::resource('menu', MenuController::class);
        Route::get('menu-list', [MenuController::class, 'menuList']);
        Route::get('menu/log/{id}', [MenuController::class, 'getLogs']);
    });
    Route::middleware('permission:menu_globle_search')->group(function () {
        Route::get('search-menu-list', [MenuController::class, 'menuList'])->name('admin.menu.list');
    });
    // Settings Management
    // Routes for general, company, and theme settings
    Route::middleware('permission:general_settings')->group(function () {
        Route::get('settings', [SettingController::class, 'index']);
        Route::get('company', [SettingController::class, 'company']);
        Route::get('company-config/{id}', [SettingController::class, 'getCompanyConfig']);
        Route::post('save-config', [SettingController::class, 'saveCompanyConfig']);
        Route::post('settings/theme', [SettingController::class, 'saveThemeSettings']);
        Route::get('general', [SettingController::class, 'getGeneralSettings']);
        Route::post('general', [SettingController::class, 'saveGeneralSettings']);
        Route::get('odo-backdate-approval', [SettingController::class, 'odoBackdateApproval']);
        Route::post('odo-backdate-setting/{department_id}', [SettingController::class, 'updateOdoBackdateSetting'])->name('odo-backdate-setting.update');
        Route::get('access-running-km-setting', [SettingController::class, 'accessRunningKmSetting']);
        Route::post('access-running-km-setting/{department_id}', [SettingController::class, 'updateAccessRunningKmSetting'])->name('access-running-km-setting.update');
        Route::get('advance-request-master', [AdvanceRequestMasterController::class, 'index'])->name('admin.advance-request-master');
        Route::post('advance-request-master', [AdvanceRequestMasterController::class, 'store'])->name('admin.advance-request-master.store');
        Route::post('advance-request-master/bulk-update', [AdvanceRequestMasterController::class, 'bulkUpdate'])->name('admin.advance-request-master.bulk-update');
        Route::delete('advance-request-master/employee', [AdvanceRequestMasterController::class, 'deleteEmployeeSetting'])->name('admin.advance-request-master.delete-employee');
        Route::get('advance-request-master/designations/{departmentId}', [AdvanceRequestMasterController::class, 'getDesignationsByDepartment'])->name('admin.advance-request-master.designations');
        Route::get('advance-request-master/employees', [AdvanceRequestMasterController::class, 'getEmployees'])->name('admin.advance-request-master.employees');
        Route::get('advance-request-master/grades', [AdvanceRequestMasterController::class, 'getGrades'])->name('admin.advance-request-master.grades');
        Route::post('advance-request-master/management-team', [AdvanceRequestMasterController::class, 'getManagementTeam'])->name('admin.advance-request-master.management-team');
        Route::post('advance-request-master/limits', [AdvanceRequestMasterController::class, 'storeLimits'])->name('admin.advance-request-master.limits.store');
        Route::get('advance-request-master/activities', [AdvanceRequestMasterController::class, 'getActivities'])->name('admin.advance-request-master.activities');
        Route::get('advance-request-master/activity-limits/{advanceTypeId}/{departmentId}', [AdvanceRequestMasterController::class, 'getActivityLimits'])->name('admin.advance-request-master.activity-limits');
        Route::get('settings/auto-verify', [\App\Http\Controllers\admin\AutoVerifySettingController::class, 'index'])->name('admin.settings.auto-verify');
        Route::post('settings/auto-verify', [\App\Http\Controllers\admin\AutoVerifySettingController::class, 'update'])->name('admin.settings.auto-verify.update');
        Route::post('settings/auto-verify/simulate', [\App\Http\Controllers\admin\AutoVerifySettingController::class, 'simulate'])->name('admin.settings.auto-verify.simulate');
        Route::get('settings/auto-verify/claim-details', [\App\Http\Controllers\admin\AutoVerifySettingController::class, 'getClaimDetails'])->name('admin.settings.auto-verify.claim-details');
    });
    // Report Management
    // Routes for generating and exporting reports, including claims and daily activities
    Route::middleware('permission:section_reports')->group(function () {

        // Hotel Price Variation Report Routes
        Route::get('/admin/hotel-price-variation-report', [ReportController::class, 'hotelPriceVariationReport'])->name('admin.hotel.price.variation.report');
        Route::get('/admin/get-hotel-price-variation-data', [ReportController::class, 'getHotelPriceVariationData'])->name('admin.get.hotel.price.variation.data');
        Route::get('/admin/get-hotel-price-variation-summary', [ReportController::class, 'getHotelPriceVariationSummary'])->name('admin.get.hotel.price.variation.summary');
        Route::get('/admin/export-hotel-price-variation', [ReportController::class, 'exportHotelPriceVariation'])->name('admin.export.hotel.price.variation');
        Route::get('/admin/get-hotel-details/{expId}', [ReportController::class, 'getHotelDetailsByExpId'])->name('admin.get.hotel.details');

        // Payment Stages Summary Routes
        Route::get('/admin/reports/payment-stages', [App\Http\Controllers\admin\ReportController::class, 'paymentStagesSummary'])->name('admin.reports.payment.stages');
        Route::post('/admin/reports/payment-stages/data', [App\Http\Controllers\admin\ReportController::class, 'getPaymentStagesData'])->name('admin.reports.payment.stages.data');
        Route::get('/admin/reports/payment-stages/export', [App\Http\Controllers\admin\ReportController::class, 'exportPaymentStages'])->name('admin.reports.payment.stages.export');

        Route::get('/high-amount-claim-report', [ReportController::class, 'highAmountClaimReport'])
            ->name('high.amount.claim.report');
        Route::get('claim-report', [ReportController::class, 'claimReport']);
        Route::get('expense-type-report', [\App\Http\Controllers\admin\ExpenseTypeReportController::class, 'index'])->name('expense-type-report');
        Route::get('expense_type_report', [\App\Http\Controllers\admin\ExpenseTypeReportController::class, 'index']);
        Route::post('expense-type-report/data', [\App\Http\Controllers\admin\ExpenseTypeReportController::class, 'getData'])->name('expense-type-report.data');
        Route::get('expense-type-report/contribution', [\App\Http\Controllers\admin\ExpenseTypeReportController::class, 'getContributionReport'])->name('expense-type-report.contribution');
        Route::get('expense-type-report/functions', [\App\Http\Controllers\admin\ExpenseTypeReportController::class, 'getFunctions'])->name('expense-type-report.functions');
        Route::get('expense-type-report/departments', [\App\Http\Controllers\admin\ExpenseTypeReportController::class, 'getDepartments'])->name('expense-type-report.departments');
        Route::get('expense-type-report/employees', [\App\Http\Controllers\admin\ExpenseTypeReportController::class, 'getEmployees'])->name('expense-type-report.employees');
        Route::get('expense-type-report/export', [\App\Http\Controllers\admin\ExpenseTypeReportController::class, 'export'])->name('expense-type-report.export');
        Route::get('expense-type-report/export-detailed', [\App\Http\Controllers\admin\ExpenseTypeReportController::class, 'exportDetailedRecords'])->name('expense-type-report.export-detailed');
        Route::get('expense-type-report/export-grouped', [\App\Http\Controllers\admin\ExpenseTypeReportController::class, 'exportGrouped'])->name('expense-type-report.export-grouped');
        Route::get('expense-type-report/export-contribution', [\App\Http\Controllers\admin\ExpenseTypeReportController::class, 'exportContribution'])->name('expense-type-report.export-contribution');
        Route::get('expense-type-report/manager-grouped', [\App\Http\Controllers\admin\ExpenseTypeReportController::class, 'managerGroupedIndex'])->name('expense-type-report.manager-grouped');
        Route::get('expense-type-report/search-managers', [\App\Http\Controllers\admin\ExpenseTypeReportController::class, 'searchManagers'])->name('expense-type-report.search-managers');

        // Expense Tree Report Routes (Hierarchical Drilldown)
        Route::get('expense-tree-report', [\App\Http\Controllers\admin\ExpenseTreeReportController::class, 'index'])->name('expense-tree-report');
        Route::get('admin/reports/expense-tree-report', [\App\Http\Controllers\admin\ExpenseTreeReportController::class, 'index']);
        Route::match(['get', 'post'], 'expense-tree-report/tree-data', [\App\Http\Controllers\admin\ExpenseTreeReportController::class, 'getTreeData'])->name('expense-tree-report.tree-data');
        Route::get('expense-tree-report/export', [\App\Http\Controllers\admin\ExpenseTreeReportController::class, 'export'])->name('expense-tree-report.export');

        // Company Multi-Year Expense Report Routes
        Route::get('company-multi-year-expense-report', [\App\Http\Controllers\admin\CompanyMultiYearExpenseReportController::class, 'index'])->name('company-multi-year-expense-report');
        Route::get('admin/reports/company-multi-year-expense-report', [\App\Http\Controllers\admin\CompanyMultiYearExpenseReportController::class, 'index']);
        Route::get('company-multi-year-expense-report/data', [\App\Http\Controllers\admin\CompanyMultiYearExpenseReportController::class, 'getData'])->name('company-multi-year-expense-report.data');
        Route::get('company-multi-year-expense-report/export', [\App\Http\Controllers\admin\CompanyMultiYearExpenseReportController::class, 'export'])->name('company-multi-year-expense-report.export');

        // Activity Report Routes
        Route::get('activity-report', [\App\Http\Controllers\admin\ActivityReportController::class, 'index'])->name('activity-report');
        Route::get('activity-report/activity-wise', [\App\Http\Controllers\admin\ActivityReportController::class, 'getActivityWise'])->name('activity-report.activity-wise');
        Route::get('activity-report/daily-summary', [\App\Http\Controllers\admin\ActivityReportController::class, 'getDailySummary'])->name('activity-report.daily-summary');
        Route::get('activity-report/employee-tracking', [\App\Http\Controllers\admin\ActivityReportController::class, 'getEmployeeTracking'])->name('activity-report.employee-tracking');
        Route::get('activity-report/pipeline', [\App\Http\Controllers\admin\ActivityReportController::class, 'getPipeline'])->name('activity-report.pipeline');
        Route::get('activity-report/performance', [\App\Http\Controllers\admin\ActivityReportController::class, 'getPerformance'])->name('activity-report.performance');
        Route::get('activity-report/export-month-wise', [\App\Http\Controllers\admin\ActivityReportController::class, 'exportMonthWise'])->name('activity-report.export-month-wise');

        Route::get('claim-stage', [ReportController::class, 'claimStage']);
        Route::get('claim-stage-fy', [ReportController::class, 'claimStageFy']);
        Route::post('export-claim-stage', [ReportController::class, 'exportClaimStage']);
        Route::post('export-claim-stage-data', [ReportController::class, 'exportClaimStageData']);
        Route::post('preview-excel-file', [ReportController::class, 'previewExcelFile']);

        // Not Showing Report (VVNR)
        Route::get('not-showing-report', [NotShowingReportController::class, 'index'])->name('reports.not.showing');
        Route::get('not-showing-report/data', [NotShowingReportController::class, 'getData'])->name('reports.not.showing.data');
        Route::post('not-showing-report/update-receive-date', [NotShowingReportController::class, 'updateReceiveDate'])->name('reports.not.showing.update-receive-date');
        Route::get('not-showing-report/export', [NotShowingReportController::class, 'export'])->name('reports.not.showing.export');

        // REP-32: VVNR stepwise pending payment report
        Route::get('vvnr-pending-payment', [\App\Http\Controllers\admin\VvnrPendingPaymentReportController::class, 'index'])->name('reports.vvnr.pending.payment');
        Route::get('vvnr-pending-payment/data', [\App\Http\Controllers\admin\VvnrPendingPaymentReportController::class, 'data'])->name('reports.vvnr.pending.payment.data');
        Route::get('vvnr-pending-payment/export', [\App\Http\Controllers\admin\VvnrPendingPaymentReportController::class, 'export'])->name('reports.vvnr.pending.payment.export');

        // Claim Stage Dashboard Reports
        Route::prefix('reports/claim-dashboard')->name('reports.claim.dashboard.')->group(function () {
            Route::get('/', [\App\Http\Controllers\admin\ClaimStageReportController::class, 'index'])->name('index');
            Route::get('/stage-summary', [\App\Http\Controllers\admin\ClaimStageReportController::class, 'getStageSummary'])->name('stage.summary');
            Route::get('/stage-pending-detail', [\App\Http\Controllers\admin\ClaimStageReportController::class, 'getStagePendingDetail'])->name('stage.pending.detail');
            Route::get('/department-stage-summary', [\App\Http\Controllers\admin\ClaimStageReportController::class, 'getDepartmentStageSummary'])->name('department.stage.summary');
            Route::get('/tat-analysis', [\App\Http\Controllers\admin\ClaimStageReportController::class, 'getTATAnalysis'])->name('tat.analysis');
            Route::get('/employee-wise-tat', [\App\Http\Controllers\admin\ClaimStageReportController::class, 'getEmployeeWiseTATAnalysis'])->name('employee.wise.tat');
            Route::get('/claim-wise-tat', [\App\Http\Controllers\admin\ClaimStageReportController::class, 'getClaimWiseTATAnalysis'])->name('claim.wise.tat');
            Route::get('/submission-return', [\App\Http\Controllers\admin\ClaimStageReportController::class, 'getSubmissionReturnReport'])->name('submission.return');
            Route::get('/submission-timeline', [\App\Http\Controllers\admin\ClaimStageReportController::class, 'getSubmissionTimeline'])->name('submission.timeline');
            Route::get('/fnf-status', [\App\Http\Controllers\admin\ClaimStageReportController::class, 'getFnFStatus'])->name('fnf.status');
            Route::get('/courier-posting-status', [\App\Http\Controllers\admin\ClaimStageReportController::class, 'getCourierPostingStatus'])->name('courier.posting.status');
            Route::get('/management-claim-status', [\App\Http\Controllers\admin\ClaimStageReportController::class, 'getManagementClaimStatus'])->name('management.claim.status');
            Route::get('/imprest-promotions', [\App\Http\Controllers\admin\ClaimStageReportController::class, 'getImprestPromotions'])->name('imprest.promotions');
            // Exports
            Route::get('/export/stage-summary', [\App\Http\Controllers\admin\ClaimStageReportController::class, 'exportStageSummary'])->name('export.stage.summary');
            Route::get('/export/pending-detail', [\App\Http\Controllers\admin\ClaimStageReportController::class, 'exportPendingDetail'])->name('export.pending.detail');
            Route::get('/export/tat-analysis', [\App\Http\Controllers\admin\ClaimStageReportController::class, 'exportTATAnalysis'])->name('export.tat.analysis');
            Route::get('/export/employee-wise-tat', [\App\Http\Controllers\admin\ClaimStageReportController::class, 'exportEmployeeWiseTAT'])->name('export.employee.wise.tat');
            Route::get('/export/claim-wise-tat', [\App\Http\Controllers\admin\ClaimStageReportController::class, 'exportClaimWiseTAT'])->name('export.claim.wise.tat');
            Route::get('/export/submission-return', [\App\Http\Controllers\admin\ClaimStageReportController::class, 'exportSubmissionReturn'])->name('export.submission.return');
            Route::get('/export/fnf-status', [\App\Http\Controllers\admin\ClaimStageReportController::class, 'exportFnFStatus'])->name('export.fnf.status');
            Route::get('/export/full-report', [\App\Http\Controllers\admin\ClaimStageReportController::class, 'exportFullReport'])->name('export.full');
            // PDF Exports
            Route::get('/pdf/stage-summary', [\App\Http\Controllers\admin\ClaimStageReportController::class, 'pdfStageSummary'])->name('pdf.stage.summary');
            Route::get('/pdf/pending-detail', [\App\Http\Controllers\admin\ClaimStageReportController::class, 'pdfPendingDetail'])->name('pdf.pending.detail');
            // Print View (PPT-ready)
            Route::get('/print', [\App\Http\Controllers\admin\ClaimStageReportController::class, 'printView'])->name('print');
            // Cache Management
            Route::post('/clear-cache', [\App\Http\Controllers\admin\ClaimStageReportController::class, 'clearCache'])->name('clear.cache');

            // Full Year Report (no stage separation)
            Route::get('/fy-report', [\App\Http\Controllers\admin\ClaimStageReportController::class, 'fullYearReport'])->name('fy.report');
            Route::get('/fy-report/data', [\App\Http\Controllers\admin\ClaimStageReportController::class, 'getFullYearData'])->name('fy.report.data');
            Route::post('/fy-report/export', [\App\Http\Controllers\admin\ClaimStageReportController::class, 'exportFullYearData'])->name('fy.report.export');
        });

        // Expense Mismatch Report (G7 vs Claim Details)
        Route::get('admin/expense-mismatch-report', [\App\Http\Controllers\admin\ExpenseMismatchReportController::class, 'index'])->name('admin.expense-mismatch.index');
        Route::get('admin/expense-mismatch-report/data', [\App\Http\Controllers\admin\ExpenseMismatchReportController::class, 'getData'])->name('admin.expense-mismatch.data');
        Route::get('admin/expense-mismatch-report/detail', [\App\Http\Controllers\admin\ExpenseMismatchReportController::class, 'getDetail'])->name('admin.expense-mismatch.detail');
        Route::post('admin/expense-mismatch-report/fix', [\App\Http\Controllers\admin\ExpenseMismatchReportController::class, 'fixMismatch'])->name('admin.expense-mismatch.fix');

        // Claim Action Log (Return & Submit Audit)
        Route::prefix('reports/claim-action-log')->name('reports.claim-action-log.')->group(function () {
            Route::get('/', [\App\Http\Controllers\admin\ClaimActionLogController::class, 'index'])->name('index');
            Route::get('/data', [\App\Http\Controllers\admin\ClaimActionLogController::class, 'getData'])->name('data');
            Route::get('/summary', [\App\Http\Controllers\admin\ClaimActionLogController::class, 'summary'])->name('summary');
            Route::get('/export', [\App\Http\Controllers\admin\ClaimActionLogController::class, 'export'])->name('export');
        });

        // Claim Action Tracking & Audit (Who Claimed, Filled, Verified, Approved, Financed)
        Route::prefix('reports/claim-action-tracking')->name('reports.claim-action-tracking.')->group(function () {
            Route::get('/', [\App\Http\Controllers\admin\ClaimActionTrackingController::class, 'index'])->name('index');
            Route::post('/data', [\App\Http\Controllers\admin\ClaimActionTrackingController::class, 'getData'])->name('data');
            Route::get('/employee-summary', [\App\Http\Controllers\admin\ClaimActionTrackingController::class, 'getEmployeeSummary'])->name('employee-summary');
            Route::get('/summary', [\App\Http\Controllers\admin\ClaimActionTrackingController::class, 'getSummary'])->name('summary');
            Route::get('/export', [\App\Http\Controllers\admin\ClaimActionTrackingController::class, 'export'])->name('export');
        });

        // Claim Rating Rules Management
        Route::prefix('admin/claim-rating')->name('admin.claim.rating.')->group(function () {
            Route::get('/', [\App\Http\Controllers\admin\ClaimRatingController::class, 'index'])->name('index');
            Route::get('/dashboard', [\App\Http\Controllers\admin\ClaimRatingController::class, 'ratingsView'])->name('dashboard');
            Route::post('/store', [\App\Http\Controllers\admin\ClaimRatingController::class, 'store'])->name('store');
            Route::get('/edit/{id}', [\App\Http\Controllers\admin\ClaimRatingController::class, 'edit'])->name('edit');
            Route::delete('/{id}', [\App\Http\Controllers\admin\ClaimRatingController::class, 'destroy'])->name('destroy');
            Route::post('/toggle/{id}', [\App\Http\Controllers\admin\ClaimRatingController::class, 'toggle'])->name('toggle');
            Route::post('/settings', [\App\Http\Controllers\admin\ClaimRatingController::class, 'saveSettings'])->name('settings');
            Route::post('/calculate', [\App\Http\Controllers\admin\ClaimRatingController::class, 'calculateAndStore'])->name('calculate');
            Route::get('/ratings', [\App\Http\Controllers\admin\ClaimRatingController::class, 'getRatings'])->name('ratings');
            Route::get('/employee-ratings', [\App\Http\Controllers\admin\ClaimRatingController::class, 'getEmployeeRatings'])->name('employee.ratings');
        });

        // Mail Logs
        Route::prefix('admin/mail-logs')->name('admin.mail.logs.')->group(function () {
            Route::get('/', [\App\Http\Controllers\admin\MailLogController::class, 'index'])->name('index');
            Route::get('/data', [\App\Http\Controllers\admin\MailLogController::class, 'getData'])->name('data');
            Route::get('/subjects-by-category', [\App\Http\Controllers\admin\MailLogController::class, 'getSubjectsByCategory'])->name('subjects-by-category');
            Route::get('/body/{id}', [\App\Http\Controllers\admin\MailLogController::class, 'getBody'])->name('body');
        });

        // Cron / Scheduled Tasks Management
        Route::prefix('admin/cron')->name('admin.cron.')->group(function () {
            Route::get('/', function () {
                return view('admin.cron-manager');
            })->name('index');
            Route::post('/run', function (\Illuminate\Http\Request $request) {
                $command = $request->input('command');
                $allowed = [
                    'claims:close-expired-months',
                    'claims:close-expired-months --dry-run',
                    'claims:close-expired-months --months=2',
                    'claims:close-expired-months --company=1',
                    'claims:close-expired-months --company=11',
                    'claims:close-expired-months --company=3',
                ];
                if (! in_array($command, $allowed)) {
                    return response()->json(['success' => false, 'message' => 'Command not allowed']);
                }
                try {
                    $parts = explode(' ', $command);
                    $cmd = array_shift($parts);
                    $params = [];
                    foreach ($parts as $part) {
                        if (str_starts_with($part, '--')) {
                            $kv = explode('=', ltrim($part, '-'), 2);
                            $params['--'.$kv[0]] = $kv[1] ?? true;
                        }
                    }
                    \Illuminate\Support\Facades\Artisan::call($cmd, $params);
                    $output = \Illuminate\Support\Facades\Artisan::output();

                    return response()->json(['success' => true, 'output' => $output]);
                } catch (\Exception $e) {
                    return response()->json(['success' => false, 'message' => $e->getMessage()]);
                }
            })->name('run');
        });

        // Advance Report
        Route::get('advance-report', [AdvanceReportController::class, 'index']);
        Route::post('advance-report/export', [AdvanceReportController::class, 'export']);
        Route::get('daily-activity', [ReportController::class, 'dailyActivity']);
        Route::post('daily-activity/data', [ReportController::class, 'getDailyActivityData']);
        Route::post('daily-activity/export', [ReportController::class, 'exportDailyActivity']);
        // Total Running KM – All Years – Employee-Wise
        Route::get('total-running-km', [ReportController::class, 'totalRunningKmReport'])->name('reports.total-running-km');
        Route::post('total-running-km/data', [ReportController::class, 'totalRunningKmData'])->name('reports.total-running-km.data');
        Route::get('total-running-km/export', [ReportController::class, 'totalRunningKmExport'])->name('reports.total-running-km.export');
        Route::get('employee-running-km-detail/{employeeId}', [ReportController::class, 'employeeRunningKmDetail'])->name('reports.employee-running-km-detail');
        // Odometer Analysis Report
        Route::get('odometer-analysis', [OdometerAnalysisReportController::class, 'index'])->name('reports.odometer-analysis');
        Route::get('odometer-analysis/summary', [OdometerAnalysisReportController::class, 'getSummary'])->name('odometer.summary');
        Route::get('odometer-analysis/two-wheeler', [OdometerAnalysisReportController::class, 'getTwoWheelerWise'])->name('odometer.two-wheeler');
        Route::get('odometer-analysis/four-wheeler', [OdometerAnalysisReportController::class, 'getFourWheelerWise'])->name('odometer.four-wheeler');
        Route::get('odometer-analysis/employee-wise', [OdometerAnalysisReportController::class, 'getEmployeeWise'])->name('odometer.employee-wise');
        Route::get('odometer-analysis/department-wise', [OdometerAnalysisReportController::class, 'getDepartmentWise'])->name('odometer.department-wise');
        Route::get('odometer-analysis/vertical-wise', [OdometerAnalysisReportController::class, 'getVerticalWise'])->name('odometer.vertical-wise');
        Route::get('odometer-analysis/export', [OdometerAnalysisReportController::class, 'export'])->name('odometer.export');
        Route::get('odometer-analysis/export-month-wise', [OdometerAnalysisReportController::class, 'exportMonthWise'])->name('odometer.export-month-wise');
        // Odometer vs Tracking KM Comparison Report (Claim ID 7)
        Route::get('odometer-tracking-report', [OdometerTrackingReportController::class, 'index'])->name('reports.odometer-tracking');
        Route::post('odometer-tracking-report/data', [OdometerTrackingReportController::class, 'getData'])->name('reports.odometer-tracking.data');
        Route::get('odometer-tracking-report/export', [OdometerTrackingReportController::class, 'export'])->name('reports.odometer-tracking.export');
        Route::get('odometer-tracking-report/session-details/{employeeId}/{date}', [OdometerTrackingReportController::class, 'getSessionDetails'])->name('reports.odometer-tracking.session-details');

        // Odometer Continuity Report (Claim ID 7)
        Route::get('odometer-continuity-report', [OdometerContinuityReportController::class, 'index'])->name('reports.odometer-continuity');
        Route::get('reports/odometer-continuity', [OdometerContinuityReportController::class, 'index']);
        Route::post('odometer-continuity-report/data', [OdometerContinuityReportController::class, 'getData'])->name('reports.odometer-continuity.data');
        Route::get('odometer-continuity-report/export', [OdometerContinuityReportController::class, 'export'])->name('reports.odometer-continuity.export');
        Route::post('filter-claims', [ReportController::class, 'filterClaims']);
        Route::post('expense-claims/export', [ReportController::class, 'export']);
        Route::get('top-rating-employee', [ReportController::class, 'topRatingEmployee']);
        Route::post('claims/return', [ReportController::class, 'returnClaim']);
        Route::get('same_date', [ReportController::class, 'sameDayClaimUpload']);
        Route::post('execute-refactor-migration', [ReportController::class, 'executeRefactorMigration']);
        Route::post('switch-to-new-focus-mapping', [ReportController::class, 'switchToNewFocusMapping']);
    });
    // Employee Running KM view — accessible to all authenticated users (used in iframe modal across roles)
    Route::get('employee-running-km-view/{employeeId}', [ReportController::class, 'employeeRunningKmView'])->name('reports.employee-running-km-view');
    // Email Template Management
    // Routes for managing email templates and their logs
    // Route::middleware('permission:email_template_management')->group(function () {
    Route::get('email-templates', [EmailTemplateController::class, 'index'])->name('email_templates.index');
    Route::post('email-template', [EmailTemplateController::class, 'store'])->name('email_template.store');
    Route::get('email-template/{id}/edit', [EmailTemplateController::class, 'edit'])->name('email_template.edit');
    Route::get('email-templates/{id}/edit', [EmailTemplateController::class, 'edit']);
    Route::put('email-template/{template}', [EmailTemplateController::class, 'update'])->name('email_template.update');
    Route::delete('email-template/{id}', [EmailTemplateController::class, 'destroy'])->name('email_template.destroy');
    Route::get('email-template/log/{template}', [EmailTemplateController::class, 'getLogs'])->name('email_template.logs');
    Route::get('email-template-list', [EmailTemplateController::class, 'templateList'])->name('email_template.list');
    Route::get('email-template-variables', [EmailTemplateController::class, 'getVariables']);

    // Email Send Log
    Route::get('email-send-log', [\App\Http\Controllers\admin\EmailSendLogController::class, 'index'])->name('email.send.log');
    Route::get('email-send-log/data', [\App\Http\Controllers\admin\EmailSendLogController::class, 'getData'])->name('email.send.log.data');
    Route::get('email-send-log/stats', [\App\Http\Controllers\admin\EmailSendLogController::class, 'getStats'])->name('email.send.log.stats');
    Route::get('email-send-log/detail', [\App\Http\Controllers\admin\EmailSendLogController::class, 'getDetail'])->name('email.send.log.detail');
    Route::get('email-send-log/filter-options', [\App\Http\Controllers\admin\EmailSendLogController::class, 'getFilterOptions'])->name('email.send.log.filter.options');

    // Email Notification Schedule
    Route::get('email-notification-schedule', function () {
        $data = \Illuminate\Support\Facades\DB::table('eml_notification as n')
            ->leftJoin('eml_email_templates as t', 'n.email_template_id', '=', 't.id')
            ->select('n.*', 't.name as template_name')
            ->orderBy('n.notification_type')
            ->orderBy('n.stage')
            ->get();

        return response()->json(['success' => true, 'data' => $data]);
    });
    Route::put('email-notification-schedule/{id}', function (\Illuminate\Http\Request $request, $id) {
        \Illuminate\Support\Facades\DB::table('eml_notification')->where('id', $id)->update([
            'stage' => $request->input('stage'),
            'email_template_id' => $request->input('email_template_id'),
            'send_day' => $request->input('send_day'),
            'is_active' => $request->input('is_active'),
            'updated_at' => now(),
        ]);

        return response()->json(['success' => true, 'message' => 'Notification schedule updated']);
    });
    // });
    // Activity Log Report
    Route::get('activity-logs', [ActivityLogController::class, 'index'])->name('activity_logs.index');
    Route::get('activity-log-data', [ActivityLogController::class, 'getData'])->name('activity_logs.data');
    Route::get('activity-log-module-summary', [ActivityLogController::class, 'moduleSummary'])->name('activity_logs.module_summary');
    Route::get('activity-log-user-summary', [ActivityLogController::class, 'userSummary'])->name('activity_logs.user_summary');
    Route::get('activity-log-export', [ActivityLogController::class, 'export'])->name('activity_logs.export');

    // Email Control — manage who receives emails
    Route::get('email-control', [\App\Http\Controllers\admin\EmailControlController::class, 'index'])->name('email_control.index');
    Route::get('email-control/data', [\App\Http\Controllers\admin\EmailControlController::class, 'getData'])->name('email_control.data');
    Route::post('email-control/update', [\App\Http\Controllers\admin\EmailControlController::class, 'update'])->name('email_control.update');
    Route::post('email-control/bulk-update', [\App\Http\Controllers\admin\EmailControlController::class, 'bulkUpdate'])->name('email_control.bulk_update');

    // VVNR Email Management & Automation (Restricted to VVNR active session only)
    Route::prefix('vvnr/email-management')->middleware(\App\Http\Middleware\EnsureVvnrSession::class)->name('vvnr.email.')->group(function () {
        Route::get('/', [\App\Http\Controllers\admin\VvnrEmailNotificationController::class, 'index'])->name('index');

        // Rules (Trigger Schedules)
        Route::get('rules', [\App\Http\Controllers\admin\VvnrEmailNotificationController::class, 'getRules'])->name('rules');
        Route::post('rules', [\App\Http\Controllers\admin\VvnrEmailNotificationController::class, 'saveRule'])->name('rules.save');
        Route::post('rules/{id}/toggle', [\App\Http\Controllers\admin\VvnrEmailNotificationController::class, 'toggleRule'])->name('rules.toggle');
        Route::delete('rules/{id}', [\App\Http\Controllers\admin\VvnrEmailNotificationController::class, 'deleteRule'])->name('rules.delete');

        // Templates
        Route::get('templates', [\App\Http\Controllers\admin\VvnrEmailNotificationController::class, 'getTemplates'])->name('templates');
        Route::post('templates', [\App\Http\Controllers\admin\VvnrEmailNotificationController::class, 'saveTemplate'])->name('templates.save');
        Route::get('templates/{id}', [\App\Http\Controllers\admin\VvnrEmailNotificationController::class, 'getTemplate'])->name('templates.detail');
        Route::get('templates/{id}/edit', [\App\Http\Controllers\admin\VvnrEmailNotificationController::class, 'getTemplate'])->name('templates.edit');
        Route::get('templates/{id}/preview', [\App\Http\Controllers\admin\VvnrEmailNotificationController::class, 'previewTemplate'])->name('templates.preview');
        Route::delete('templates/{id}', [\App\Http\Controllers\admin\VvnrEmailNotificationController::class, 'deleteTemplate'])->name('templates.delete');

        // Recipient Control
        Route::get('recipients', [\App\Http\Controllers\admin\VvnrEmailNotificationController::class, 'getRecipients'])->name('recipients');
        Route::get('recipients/department-summary', [\App\Http\Controllers\admin\VvnrEmailNotificationController::class, 'getDepartmentRecipientsSummary'])->name('recipients.dept_summary');
        Route::get('recipients/grade-summary', [\App\Http\Controllers\admin\VvnrEmailNotificationController::class, 'getGradeRecipientsSummary'])->name('recipients.grade_summary');
        Route::post('recipients/update', [\App\Http\Controllers\admin\VvnrEmailNotificationController::class, 'updateRecipient'])->name('recipients.update');
        Route::post('recipients/bulk-update', [\App\Http\Controllers\admin\VvnrEmailNotificationController::class, 'bulkUpdateRecipients'])->name('recipients.bulk');

        // Trigger Execution / Preview / Test
        Route::get('preview-trigger/{type}', [\App\Http\Controllers\admin\VvnrEmailNotificationController::class, 'previewTrigger'])->name('trigger.preview');
        Route::post('dispatch-trigger/{type}', [\App\Http\Controllers\admin\VvnrEmailNotificationController::class, 'dispatchTrigger'])->name('trigger.dispatch');
        Route::post('send-test', [\App\Http\Controllers\admin\VvnrEmailNotificationController::class, 'sendTestEmail'])->name('test');

        // Monthly Hierarchy-wise Summary (Reporting Manager's Manager)
        Route::get('hierarchy-summary', [\App\Http\Controllers\admin\VvnrEmailNotificationController::class, 'getHierarchyMonthlySummary'])->name('hierarchy.summary');
        Route::post('hierarchy-summary/dispatch', [\App\Http\Controllers\admin\VvnrEmailNotificationController::class, 'dispatchHierarchyMonthlySummary'])->name('hierarchy.dispatch');

        // Logs
        Route::get('logs', [\App\Http\Controllers\admin\VvnrEmailNotificationController::class, 'getLogs'])->name('logs');
        Route::get('logs/{id}', [\App\Http\Controllers\admin\VvnrEmailNotificationController::class, 'getLogDetail'])->name('logs.detail');
    });

    // Claim Type Managment
    Route::middleware('permission:claim_type_management')->group(function () {
        Route::get('claim-type', [ClaimTypeController::class, 'index'])->name('claim_type.index');
    });

    //   // Add these routes
    Route::get('/claimant-embed', [App\Http\Controllers\admin\ClaimantController::class, 'claimantEmbed'])->name('claimant.embed');

    // Verifier routes
    Route::prefix('verifier')->group(function () {
        Route::get('/claimant', [VerifierClaimantController::class, 'index'])->name('verifier.claimant');

        // API endpoints
        Route::get('/draft-list', [VerifierClaimantController::class, 'draftList'])->name('verifier.draft.list');
        Route::get('/open-list', [VerifierClaimantController::class, 'openList'])->name('verifier.open.list');
        Route::get('/close-list', [VerifierClaimantController::class, 'closeList'])->name('verifier.close.list');
        Route::get('/open-day', [VerifierClaimantController::class, 'openDayDetails'])->name('verifier.open.day');
        Route::get('/close-day', [VerifierClaimantController::class, 'closeDayDetails'])->name('verifier.close.day');
        Route::get('/running-km', [VerifierClaimantController::class, 'runningKmDetails'])->name('verifier.running.km');
        Route::get('/courier-details', [VerifierClaimantController::class, 'getCourierDetails'])->name('verifier.courier.details');
        Route::get('/month-report', [VerifierClaimantController::class, 'monthReport'])->name('verifier.month.report');

        // POST endpoints
        Route::post('/add-remark', [VerifierClaimantController::class, 'addRemark'])->name('verifier.add.remark');
        Route::post('/delete-draft', [VerifierClaimantController::class, 'deleteDraft'])->name('verifier.draft.delete');
        Route::post('/verify-claim', [VerifierClaimantController::class, 'verifyClaim'])->name('verifier.verify.claim');
        Route::post('/deny-claim', [VerifierClaimantController::class, 'denyClaim'])->name('verifier.deny.claim');
        Route::post('/submit-month', [VerifierClaimantController::class, 'submitMonth'])->name('verifier.submit.month');
        Route::post('/update-courier', [VerifierClaimantController::class, 'updateCourier'])->name('verifier.update.courier');
        Route::post('/courier-detail', [VerifierClaimantController::class, 'getCourierDetail'])->name('verifier.courier.detail.post');
    });
    // Claim Details
    // Routes for viewing claim details and types
    Route::get('claim-detail', [ClaimViewController::class, 'getClaimDetailView']);
    Route::get('claims/detail-view', [ClaimViewController::class, 'getClaimDetailView']);
    Route::get('get-claim-types', [ClaimViewController::class, 'getActiveClaimTypes']);
    Route::get('get-uploaded-files/{expid}/{claimid}', [ClaimViewController::class, 'viewUploadedFiles']);
    Route::post('get-varieties', [ClaimViewController::class, 'getVarieties']);
    Route::post('upload-claim-files', [ClaimViewController::class, 'uploadClaimFiles']);
    Route::post('claim-upload-image', [ClaimViewController::class, 'uploadClaimImage']);
    Route::post('claim-change-head', [ClaimViewController::class, 'changeClaimHead']);
    Route::get('get-activity-expense-heads/{claimid}', [ClaimViewController::class, 'getActivityExpenseHeads']);
    Route::get('get-bill-upload-categories', [ClaimViewController::class, 'getBillUploadCategories']);
    Route::post('hold-claim', [ClaimViewController::class, 'actionHold'])->name('hold-claim');
    Route::post('draft-claim', [ClaimViewController::class, 'actionDraft'])->name('draft-claim');
    Route::post('filled-claim', [ClaimViewController::class, 'actionFilled'])->name('filled-claim');
    Route::post('deny-claim', [ClaimViewController::class, 'actionDeny'])->name('deny-claim');
    Route::post('revert-uploaded-claim', [ClaimViewController::class, 'actionRevertUploaded'])->name('revert-uploaded-claim');
    Route::get('employee-all-claims', [ClaimViewController::class, 'getEmployeeAllClaims']);
    Route::get('expense/ehome-dashboard', [EmployeeController::class, 'eHomeDashboard']);
    Route::get('expense/ehome-month-details', [EmployeeController::class, 'eHomeMonthDetails']);
    Route::get('city-list/{category?}', [CityCategoryController::class, 'cityList']);
    Route::get('airport-list', [AirportController::class, 'airportList']);
    Route::post('calculate-rate', [RateController::class, 'calculateRateAjax']);
    Route::post('send-message', [ClaimViewController::class, 'sendMessage']);
    Route::get('claimant', [ClaimantController::class, 'claimant'])->name('claimant');
    Route::post('/get-cities', [ClaimViewController::class, 'getCities']);
    Route::post('/get-hotels', [ClaimViewController::class, 'getHotels']);
    Route::post('/get-hotels-by-city', [ClaimViewController::class, 'getHotelsByCity']);
    Route::post('/get-hotel-details', [ClaimViewController::class, 'getHotelDetails']);
    Route::post('/get-employees', [ClaimViewController::class, 'getEmployees']);
    Route::post('/get-employees-by-ids', [ClaimViewController::class, 'getEmployeesByIds']);
    Route::post('/get-city-category', [ClaimViewController::class, 'getCityCategory']);
    Route::get('/get-courier-providers', [ClaimViewController::class, 'getCourierProviders']);
    Route::get('new-claim', [ClaimantController::class, 'claims'])->name('claims.new');
    Route::get('claims', [ClaimantController::class, 'claims'])->name('claims');
    Route::get('claim-list/data', [ClaimantController::class, 'claimList'])->name('claim.list.data');
    Route::get('odometer/data', [ClaimantController::class, 'getOdometerData'])->name('odometer.data');
    Route::post('odometer/upload', [ClaimantController::class, 'uploadAndExtract'])->name('odometer.upload');
    Route::get('claimant/draft/list', [ClaimantController::class, 'getDraftList'])->name('claimant.draft.list');
    Route::post('claimant/draft/delete', [ClaimantController::class, 'deleteDraft'])->name('claimant.draft.delete');
    Route::post('claimant/add/remark', [ClaimantController::class, 'addRemark'])->name('claimant.add.remark');
    Route::get('claimant/open/list', [ClaimantController::class, 'getOpenList'])->name('claimant.open.list');
    Route::get('claimant/open/day', [ClaimantController::class, 'getOpenDayDetails'])->name('claimant.open.day');
    Route::get('claimant/close/list', [ClaimantController::class, 'getCloseList'])->name('claimant.close.list');
    Route::get('claimant/close/day', [ClaimantController::class, 'getCloseDayDetails'])->name('claimant.close.day');
    Route::post('claimant/claim/verify', [ClaimantController::class, 'verifyClaim'])->name('claimant.claim.verify');
    Route::post('claimant/claim/deny', [ClaimantController::class, 'denyClaim'])->name('claimant.claim.deny');
    Route::post('claimant/month/submit', [ClaimantController::class, 'submitMonth'])->name('claimant.month.submit');
    Route::get('claimant/running-km', [ClaimantController::class, 'getRunningKm'])->name('claimant.running.km');
    Route::get('claimant/expense/detail', [ClaimantController::class, 'getExpenseDetail'])->name('claimant.expense.detail');
    Route::post('claimant/courier/submit', [ClaimantController::class, 'submitCourier'])->name('claimant.courier.submit');
    Route::post('claimant/courier-detail', [ClaimantController::class, 'getCourierDetail'])->name('claimant.pending.courier.detail');
    Route::post('claimant/courier-update', [ClaimantController::class, 'updateCourierDetail'])->name('claimant.pending.courier.update');
    Route::post('/da-claim/check-holiday', [ClaimantController::class, 'checkHoliday'])->name('da.check.holiday');

    // Reporting Manager Dashboard Routes
    Route::prefix('reporting-manager')->name('reporting-manager.')->middleware('permission:reporting_manager_dashboard')->group(function () {
        Route::get('dashboard', [\App\Http\Controllers\ReportingManagerController::class, 'dashboard'])->name('dashboard');
        Route::get('approvals', [\App\Http\Controllers\ReportingManagerController::class, 'approvals'])->name('approvals');
        Route::get('team', [\App\Http\Controllers\ReportingManagerController::class, 'team'])->name('team');
        Route::get('api/approval-inbox', [\App\Http\Controllers\ReportingManagerController::class, 'getApprovalInbox'])->name('api.approval.inbox');
        Route::get('api/team-claims', [\App\Http\Controllers\ReportingManagerController::class, 'getTeamClaimsSummary'])->name('api.team.claims');
    });
    Route::post('/da-claim/submit', [ClaimantController::class, 'submitDaClaim'])->name('da.submit');
    Route::get('/da-claim/data', [ClaimantController::class, 'getDaData'])->name('da.data');
    Route::get('/claim/upload-data', [ClaimantController::class, 'getUploadClaimData'])->name('claim.upload.data');
    Route::post('/claim/extract-data', [ClaimantController::class, 'extractClaimData'])->name('claim.extract.data');
    Route::post('/claim/upload-submit', [ClaimantController::class, 'submitUploadClaim'])->name('claim.upload.submit');
    Route::post('/activity-claim/submit', [ClaimantController::class, 'submitActivityClaim'])->name('activity.claim.submit');
    Route::prefix('vnr')->name('vnr.')->group(function () {
        Route::get('approver', [ClaimantController::class, 'approver'])->name('approver');
        Route::get('approver/pending/list', [VNRApproverController::class, 'getApproverPendingList'])->name('approver.pending.list');
        Route::get('approver/pending/day', [VNRApproverController::class, 'getApproverPendingDay'])->name('approver.pending.day');
        Route::get('approver/approved/list', [VNRApproverController::class, 'getApproverApprovedList'])->name('approver.approved.list');
        Route::get('approver/approved/day', [VNRApproverController::class, 'getApproverApprovedDay'])->name('approver.approved.day');
        Route::post('approver/claim/approve', [VNRApproverController::class, 'approveClaimByApprover'])->name('approver.claim.approve');
        Route::post('approver/claim/approve-headwise', [VNRApproverController::class, 'approveClaimHeadWise'])->name('approver.claim.approve.headwise');
        Route::post('approver/claim/submit', [VNRApproverController::class, 'submitClaimByApprover'])->name('approver.claim.submit');
        Route::get('approver/expense/detail', [VNRApproverController::class, 'getExpenseDetail'])->name('approver.expense.detail');
        Route::get('team/members/list', [VNRApproverController::class, 'getTeamMembersList'])->name('team.members.list');
        Route::get('team/all/expenses', [VNRApproverController::class, 'getAllTeamExpenses'])->name('team.all.expenses');
        Route::get('team/member/expenses', [VNRApproverController::class, 'getMemberExpenses'])->name('team.member.expenses');
        Route::get('team/member/expenses/by-claim', [VNRApproverController::class, 'getMemberExpensesByClaim'])->name('team.member.expenses.by.claim');
        Route::get('team/member/all-expenses-by-claim', [VNRApproverController::class, 'getAllTeamMemberExpensesByClaimType'])->name('team.member.all-expenses.by-claim');
    });
    Route::prefix('vvnr')->name('vvnr.')->group(function () {
        Route::get('approver', [ClaimantController::class, 'approver'])->name('approver');
        Route::get('approver/pending/list', [VVNRApproverController::class, 'getApproverPendingList'])->name('approver.pending.list');
        Route::get('approver/pending/day', [VVNRApproverController::class, 'getApproverPendingDay'])->name('approver.pending.day');
        Route::get('approver/approved/list', [VVNRApproverController::class, 'getApproverApprovedList'])->name('approver.approved.list');
        Route::get('approver/approved/day', [VVNRApproverController::class, 'getApproverApprovedDay'])->name('approver.approved.day');
        Route::post('approver/claim/approve', [VVNRApproverController::class, 'approveClaimByApprover'])->name('approver.claim.approve');
        Route::post('approver/claim/submit', [VVNRApproverController::class, 'submitClaimByApprover'])->name('approver.claim.submit');
        Route::get('approver/expense/detail', [VVNRApproverController::class, 'getExpenseDetail'])->name('approver.expense.detail');
        Route::get('team/members/list', [VVNRApproverController::class, 'getTeamMembersList'])->name('team.members.list');
        Route::get('team/all/expenses', [VVNRApproverController::class, 'getAllTeamExpenses'])->name('team.all.expenses');
        Route::get('team/member/expenses', [VVNRApproverController::class, 'getMemberExpenses'])->name('team.member.expenses');
        Route::get('team/member/expenses/by-claim', [VVNRApproverController::class, 'getMemberExpensesByClaim'])->name('team.member.expenses.by.claim');
        Route::get('team/member/all-expenses-by-claim', [VVNRApproverController::class, 'getAllTeamMemberExpensesByClaimType'])->name('team.member.all-expenses.by-claim');
    });
    Route::prefix('nursery')->name('nursery.')->group(function () {
        Route::get('approver', [ClaimantController::class, 'approver'])->name('approver');
        Route::get('approver/pending/list', [NurseryApproverController::class, 'getApproverPendingList'])->name('approver.pending.list');
        Route::get('approver/pending/day', [NurseryApproverController::class, 'getApproverPendingDay'])->name('approver.pending.day');
        Route::get('approver/approved/list', [NurseryApproverController::class, 'getApproverApprovedList'])->name('approver.approved.list');
        Route::get('approver/approved/day', [NurseryApproverController::class, 'getApproverApprovedDay'])->name('approver.approved.day');
        Route::post('approver/claim/approve', [NurseryApproverController::class, 'approveClaimByApprover'])->name('approver.claim.approve');
        Route::post('approver/claim/approve-headwise', [NurseryApproverController::class, 'approveClaimHeadWise'])->name('approver.claim.approve.headwise');
        Route::post('approver/claim/submit', [NurseryApproverController::class, 'submitClaimByApprover'])->name('approver.claim.submit');
        Route::get('approver/expense/detail', [NurseryApproverController::class, 'getExpenseDetail'])->name('approver.expense.detail');
        Route::get('team/members/list', [NurseryApproverController::class, 'getTeamMembersList'])->name('team.members.list');
        Route::get('team/all/expenses', [NurseryApproverController::class, 'getAllTeamExpenses'])->name('team.all.expenses');
        Route::get('team/member/expenses', [NurseryApproverController::class, 'getMemberExpenses'])->name('team.member.expenses');
        Route::get('team/member/expenses/by-claim', [NurseryApproverController::class, 'getMemberExpensesByClaim'])->name('team.member.expenses.by.claim');
        Route::get('team/member/all-expenses-by-claim', [NurseryApproverController::class, 'getAllTeamMemberExpensesByClaimType'])->name('team.member.all-expenses.by-claim');
    });
    // Employee Management
    // Routes for employee-related operations, including search and filtering
    Route::match(['get', 'post'], 'employees/search', [EmployeeController::class, 'search']);
    Route::match(['get', 'post'], 'admin/employees/search', [EmployeeController::class, 'search']);
    Route::post('employees/search-all', [EmployeeController::class, 'searchAll']); // Unrestricted search for Group Meal / Lodging participants
    Route::get('employee/{id}', [EmployeeController::class, 'employee']);
    Route::post('employee/toggle-allow-submit', [EmployeeController::class, 'toggleAllowSubmit']);
    Route::post('employee/return-claim', [EmployeeController::class, 'returnClaim']);
    Route::post('employee/backend-submit-claim', [EmployeeController::class, 'backendSubmitClaim']);
    Route::post('employees/by-department', [FilterController::class, 'getEmployeesByDepartment']);
    Route::post('verticals/by-function', [FilterController::class, 'getVerticalsByFunction']);
    Route::post('departments/by-vertical', [FilterController::class, 'getDepartmentsByVertical']);
    Route::post('sub-departments/by-department', [FilterController::class, 'getSubDepartmentsByDepartment']);
    // API Management
    // Routes for managing API field mappings and table/column data
    Route::resource('api-manager', APIManagerController::class);
    Route::get('api/fields-mapping/{claim_id}', [APIManagerController::class, 'showMappingPage'])->name('fields.mapping.page'); // UAT-03: the API manager page links to this name
    Route::post('api/fields-mapping/{claim_id}', [APIManagerController::class, 'storeFieldMapping']);
    Route::get('api/fields/mapping/{claim_id}', [APIManagerController::class, 'getFieldMappings']);
    Route::get('api/tables', [APIManagerController::class, 'getTables']);
    Route::get('api/columns/{table}', [APIManagerController::class, 'getColumns']);
    Route::post('api/map-fields', [APIManagerController::class, 'mapFields']);
    // Expense Management
    // Route for generating expense PDFs
    Route::get('generate-expense-pdf', [ExpenseDetailsController::class, 'generatePdf']);
    // Data Punch
    // Routes for data punch operations
    Route::middleware('permission:section_data_punching')->group(function () {
        Route::get('data-punch/{status}', [MediatorController::class, 'dataPunch'])->name('data.punch');
        Route::post('data-punch/{status}/data', [MediatorController::class, 'dataPunchData'])->name('data.punch.data');
        Route::post('data-punch/{status}/filter-options', [MediatorController::class, 'getFilteredOptions'])->name('data.punch.filter.options');
        Route::post('data-punch/deactivate-claim', [MediatorController::class, 'deactivateClaim'])->name('data.punch.deactivate');
        Route::post('data-punch/revert-deactivated-claim', [MediatorController::class, 'revertDeactivatedClaim'])->name('data.punch.revert.deactivated');
        // Tracker #28: bulk claim head change (Expense team)
        Route::post('data-punch/bulk-change-head', [ClaimViewController::class, 'bulkChangeClaimHead'])
            ->middleware('permission:claim.bulk_change_head')->name('data.punch.bulk.change.head');
    });

    // Section Verifier
    // Routes for section verifier operations
    Route::middleware('permission:section_verifier')->group(function () {
        Route::get('verify-running-km', [VerifierController::class, 'verifyRunningKm'])->name('verify.running.km');
        Route::post('verify-running-km/data', [VerifierController::class, 'verifyRunningKmData'])->name('verify.running.km.data');
        Route::match(['get', 'post'], 'verify-running-km/export', [VerifierController::class, 'exportRunningKm'])->name('verify.running.km.export');
        Route::post('verify-running-km/update', [VerifierController::class, 'updateRunningKm'])->name('verify.running.km.update');
        Route::post('verify-running-km/verify', [VerifierController::class, 'verifyRunningKmRecord'])->name('verify.running.km.verify');
        Route::post('verify-running-km/deactivate', [VerifierController::class, 'deactivateRunningKmRecord'])->name('verify.running.km.deactivate');
        Route::post('verify-running-km/bulk-deactivate', [VerifierController::class, 'bulkDeactivateRunningKmRecords'])->name('verify.running.km.bulk.deactivate');
        Route::post('verify-running-km/revert-deactivated', [VerifierController::class, 'revertDeactivatedRunningKmRecord'])->name('verify.running.km.revert.deactivated');
        Route::post('verify-running-km/calculate-rate', [VerifierController::class, 'getRunningKmRate'])->name('verify.running.km.calculate.rate');
        Route::post('verify-running-km/duplicate-odometer', [VerifierController::class, 'duplicateOdometerData'])->name('verify.running.km.duplicate.odometer');
        Route::post('verify-running-km/duplicate-odometer/not-duplicate', [VerifierController::class, 'markOdometerNotDuplicate'])->name('verify.running.km.duplicate.odometer.review'); // ODO-DUP-02
        Route::match(['get', 'post'], 'verify-running-km/duplicate-odometer/export', [VerifierController::class, 'exportDuplicateOdometer'])->name('verify.running.km.duplicate.odometer.export');

        Route::get('employee-vehicles', [EmployeeVehicleController::class, 'employeeVehicles'])->name('employee.vehicles');
        Route::post('employee-vehicles/data', [EmployeeVehicleController::class, 'getEmployeeVehiclesData'])->name('employee.vehicles.data');
        Route::post('employee-vehicles/policy-details', [EmployeeVehicleController::class, 'getPolicyDetails'])->name('employee.vehicles.policy.details');
        Route::post('employee-vehicles/running-km', [EmployeeVehicleController::class, 'getRunningKmDetails'])->name('employee.vehicles.running.km');
        Route::post('employee-vehicles/documents', [EmployeeVehicleController::class, 'getVehicleDocuments'])->name('employee.vehicles.documents');
        Route::post('employee/vehicles/export', [EmployeeVehicleController::class, 'exportVehicles'])->name('employee.vehicles.export');
        Route::get('verify-pending', [VerifierController::class, 'verifyPendingClaims'])->name('verify-pending');
        Route::post('verify-pending-claims/data', [VerifierController::class, 'verifyPendingClaimsData'])->name('verify.pending.claims.data');
        Route::post('verify-approved-claims/data', [VerifierController::class, 'verifyApprovedClaimsData'])->name('verify.approved.claims.data');
        Route::post('verify-approved/claim-revert', [VerifierController::class, 'revertClaim'])->name('verify.approved.claim.revert');
        Route::post('verify-pending/claim-return', [VerifierController::class, 'returnClaim'])->name('verify.pending.claim.return');
        Route::post('verify-pending/claim-submit', [VerifierController::class, 'submitToApprover'])->name('verify.pending.claim.submit');
        Route::get('verify-approved', [VerifierController::class, 'verifyApprovedClaims'])->name('verify-approved');
        Route::post('verify-pending/get-claims', [VerifierController::class, 'getEmployeeClaimsAjax'])->name('verify.pending.get.claims');
        Route::post('verify-pending/get-single-claim', [VerifierController::class, 'getSingleClaim'])->name('verify.pending.get.single.claim');
        Route::post('verify-pending/verify-single-claim', [VerifierController::class, 'verifySingleClaim'])->name('verify.pending.verify.single');
        Route::post('verify/pending/verify-all', [VerifierController::class, 'verifyAllClaims'])->name('verify.pending.verify.all');
        Route::get('verify-pending/auto-verify-preview', [VerifierController::class, 'autoVerifyPreview'])->name('verify.pending.auto-verify.preview');
        Route::post('verify-pending/auto-verify-batch', [VerifierController::class, 'autoVerifyBatch'])->name('verify.pending.auto-verify.batch');

        // Verified By Report Routes
        Route::get('/reports/claims-verification', [ClaimReportController::class, 'claimsVerificationReport'])->name('reports.claims.verification');
        Route::get('/reports/get-employee-claims/{empid}/{month}', [ClaimReportController::class, 'getEmployeeClaimsAjaxByVerifier']);
        Route::get('/reports/claims-verification/details', [ClaimReportController::class, 'getClaimDetails'])->name('reports.claims.verification.details');
        // Add these routes for the new endpoints
        Route::get('/reports/get-claim-types', [ClaimReportController::class, 'getClaimTypes']);
        Route::get('/reports/get-verifiers', [ClaimReportController::class, 'getVerifiers']);
        // Route::get('/reports/get-employee-claims/{empid}/{month?}', [ClaimReportController::class, 'getEmployeeClaimsAjaxByVerifier']);
        // Route::get('/reports/get-claim-details', [ClaimReportController::class, 'getClaimDetails']);

        Route::middleware(['permission:pending_claims_courier_detail'])->group(function () {
            Route::post('verify-pending/courier-detail', [VerifierController::class, 'getCourierDetail'])->name('verify.pending.courier.detail');
            Route::post('verify-pending/courier-update', [VerifierController::class, 'updateCourierDetail'])->name('verify.pending.courier.update');
        });
    });
    // Utility
    Route::get('temp_files', [UtilityController::class, 'temp_files'])->name('temp_files');
    Route::delete('/temp-files/delete', [UtilityController::class, 'delete'])->name('temp-files.delete');
    Route::post('/temp-files/bulk-delete', [UtilityController::class, 'bulkDelete'])->name('temp-files.bulk-delete');
    Route::post('/temp-files/delete-all', [UtilityController::class, 'deleteAll'])->name('temp-files.delete-all');
    Route::get('/temp-files/download/{filename}', [UtilityController::class, 'download'])->name('temp-files.download');
    Route::post('/temp-files/bulk-download', [UtilityController::class, 'bulkDownload'])->name('temp-files.bulk-download');
    // Version Control
    // Route for version control
    Route::get('version-control', [GitVersionControlController::class, 'index'])->name('version-control.index');
    Route::get('version-control/diagnostics', [GitDiagnosticsController::class, 'diagnose'])->name('version-control.diagnostics');
    Route::get('version-control/status', [GitVersionControlController::class, 'gitStatus'])->name('version-control.status');
    Route::post('version-control/add', [GitVersionControlController::class, 'gitAdd'])->name('version-control.add');
    Route::post('version-control/commit', [GitVersionControlController::class, 'gitCommit'])->name('version-control.commit');
    Route::post('version-control/push', [GitVersionControlController::class, 'gitPush'])->name('version-control.push');
    Route::post('version-control/pull', [GitVersionControlController::class, 'gitPull'])->name('version-control.pull');
    Route::post('version-control/fetch', [GitVersionControlController::class, 'gitFetch'])->name('version-control.fetch');
    Route::post('version-control/checkout', [GitVersionControlController::class, 'gitCheckout'])->name('version-control.checkout');
    Route::get('version-control/log', [GitVersionControlController::class, 'gitLog'])->name('version-control.log');
    Route::get('version-control/diff', [GitVersionControlController::class, 'gitDiff'])->name('version-control.diff');
    Route::post('version-control/stash', [GitVersionControlController::class, 'gitStash'])->name('version-control.stash');
    Route::post('version-control/create-branch', [GitVersionControlController::class, 'gitCreateBranch'])->name('version-control.create-branch');
    Route::post('version-control/delete-branch', [GitVersionControlController::class, 'gitDeleteBranch'])->name('version-control.delete-branch');
    Route::post('version-control/merge', [GitVersionControlController::class, 'gitMerge'])->name('version-control.merge');
    Route::post('version-control/tag', [GitVersionControlController::class, 'gitTag'])->name('version-control.tag');
    Route::post('version-control/reset', [GitVersionControlController::class, 'gitReset'])->name('version-control.reset');
    Route::post('version-control/revert', [GitVersionControlController::class, 'gitRevert'])->name('version-control.revert');
    Route::post('version-control/init', [GitVersionControlController::class, 'gitInit'])->name('version-control.init');
    Route::post('version-control/clone', [GitVersionControlController::class, 'gitClone'])->name('version-control.clone');
    Route::get('version-control/show', [GitVersionControlController::class, 'gitShow'])->name('version-control.show');
    Route::post('version-control/discard', [GitVersionControlController::class, 'gitDiscard'])->name('version-control.discard');
    Route::get('version-control/conflicts', [GitVersionControlController::class, 'gitConflicts'])->name('version-control.conflicts');
    Route::post('version-control/remote', [GitVersionControlController::class, 'gitRemote'])->name('version-control.remote');
    Route::prefix('reports')->name('reports.')->group(function () {
        Route::get('/extract', [ExtractController::class, 'extract'])->name('extract');
        Route::post('/extract/data', [ExtractController::class, 'getData'])->name('extract.data');
        Route::get('/extract/details/{id}', [ExtractController::class, 'getDetails'])->name('extract.details');
        Route::get('/extract/export', [ExtractController::class, 'export'])->name('extract.export');
    });
    // Focus Export
    // Route for focus export
    Route::resource('state-code-mappings', StateCodeMappingController::class)->except(['show', 'create']);
    Route::resource('department-code-mappings', DepartmentCodeMappingController::class)->except(['show', 'create']);
    Route::resource('claim-focus-mappings', ClaimFocusMappingController::class)->except(['show', 'create']);
    Route::resource('state-field-promotion-mappings', StateFieldPromotionMappingController::class)->except(['show', 'create']);
    Route::resource('department-vertical-mappings', DepartmentVerticalMappingController::class)->except(['show', 'create']);
    Route::resource('system-configurations', SystemConfigurationController::class)->except(['show', 'create']);
    // Advance Finance Management
    // Routes for finance level advance request approvals
    // ADV-WEB-01 (13 Nov 2026): the permission advance_finance_management does not exist, so only Super Admin could open
    // these screens; the menu ("Advance Approvals") gives them to Payment Section, which is what the route now checks.
    Route::middleware('permission:payment_section')->group(function () {
        Route::get('advance/finance', [AdvanceFinanceController::class, 'index'])->name('advance.finance.index');
        Route::get('advance/finance/pending', [AdvanceFinanceController::class, 'pending'])->name('advance.finance.pending');
        Route::get('advance/finance/rejected', [AdvanceFinanceController::class, 'rejected'])->name('advance.finance.rejected');
        Route::get('advance/finance/approved', [AdvanceFinanceController::class, 'approved'])->name('advance.finance.approved');
        Route::post('advance/finance/approve', [AdvanceFinanceController::class, 'approve'])->name('advance.finance.approve');
        Route::post('advance/finance/reject', [AdvanceFinanceController::class, 'reject'])->name('advance.finance.reject');
        Route::post('advance/finance/revert', [AdvanceFinanceController::class, 'revert'])->name('advance.finance.revert');
    });
    // Admin Advance Request Submission
    // Routes for admin to submit advance requests on behalf of employees
    // TODO: Add permission middleware after testing - Route::middleware('permission:advance_admin_submit')->group(function () {
    Route::prefix('advance/admin')->name('advance.admin.')->group(function () {
        Route::get('submit', [AdvanceRequestAdminController::class, 'index'])->name('submit');
        Route::get('employees', [AdvanceRequestAdminController::class, 'getEmployees'])->name('employees');
        Route::get('departments', [AdvanceRequestAdminController::class, 'getDepartments'])->name('departments');
        Route::get('advance-types', [AdvanceRequestAdminController::class, 'getAdvanceTypes'])->name('advance-types');
        Route::get('activity-names', [AdvanceRequestAdminController::class, 'getActivityNames'])->name('activity-names');
        Route::get('activity-types', [AdvanceRequestAdminController::class, 'getActivityTypes'])->name('activity-types');
        Route::get('crops', [AdvanceRequestAdminController::class, 'getCrops'])->name('crops');
        Route::get('varieties', [AdvanceRequestAdminController::class, 'getVarieties'])->name('varieties');
        Route::post('submit-request', [AdvanceRequestAdminController::class, 'submitRequest'])->name('submit-request');
        Route::post('submit-manual-advance', [AdvanceRequestAdminController::class, 'submitManualAdvance'])->name('submit-manual-advance');
    });
    // Payment Management
    // Routes for payment import and listing
    Route::prefix('payments')->name('payments.')->group(function () {
        Route::get('/', [\App\Http\Controllers\admin\PaymentController::class, 'index'])->name('index');
        Route::get('/data', [\App\Http\Controllers\admin\PaymentController::class, 'getData'])->name('data');
        Route::get('/details', [\App\Http\Controllers\admin\PaymentController::class, 'getDetails'])->name('details');
        Route::get('/import', [\App\Http\Controllers\admin\PaymentController::class, 'import'])->name('import');
        Route::post('/import', [\App\Http\Controllers\admin\PaymentController::class, 'processImport'])->name('import.process');
    });
    Route::prefix('advance-requests')->group(function () {
        Route::get('/', [AdvanceRequestViewController::class, 'index'])->name('advance.requests.index');
        Route::post('/update-status', [AdvanceRequestViewController::class, 'updateStatus'])->name('advance.requests.update-status');
        Route::get('/{id}/edit', [AdvanceRequestViewController::class, 'edit'])->name('advance.requests.edit');
        Route::get('/create', [AdvanceRequestViewController::class, 'create'])->name('advance.requests.create');
        Route::post('/', [AdvanceRequestViewController::class, 'store'])->name('advance.requests.store');
        Route::post('/advance-create', [AdvanceRequestViewController::class, 'store'])->name('advance_approvals.advance_create');
        Route::put('/{id}', [AdvanceRequestViewController::class, 'update'])->name('advance.requests.update');
        Route::delete('/{id}', [AdvanceRequestViewController::class, 'destroy'])->name('advance.requests.destroy');
        Route::get('/advanceApprovals', [AdvanceRequestViewController::class, 'advanceapproval'])->name('advanceApprovals.requests.destroy');
        Route::get('/report', [AdvanceRequestViewController::class, 'report'])->name('advance.requests.report');
        Route::get('/hod-report', [AdvanceRequestViewController::class, 'report'])->name('advance.requests.hod-report');
        Route::get('/advanceReport', [AdvanceRequestViewController::class, 'report'])->name('advance.requests.advance-report');
    });

    Route::post('/expense-payments/mark-exported', [ExpensePaymentController::class, 'markAsExported'])->name('expense-payments.mark-exported');
    Route::get('/treasury/instructed', [ExpensePaymentController::class, 'instructedtreasury'])->name('treasury.instructed');
    Route::post('/expense-payments/export-focus', [ExpensePaymentController::class, 'generateFocusExport'])->name('expense-payments.export-focus');
    Route::get('treasury/advance-approvals', [AdvanceApprovalsController::class, 'treasury'])->name('treasury.advance-approvals');
    Route::post('/expense-payments/export-only', [ExpensePaymentController::class, 'exportOnly'])
        ->name('expense-payments.export-only');
    Route::post('/expense-payments/release-selected', [ExpensePaymentController::class, 'releaseSelected'])->name('expense-payments.release-selected');
    Route::post('/update-hold-remark', [ExpensePaymentController::class, 'updateHoldRemark'])->name('update.hold.remark');

    Route::post('/expense-payments/export-combined', [ExpensePaymentController::class, 'exportCombined'])
        ->name('expense-payments.export-combined');

    // Matrix Payment Integration
    Route::post('/matrix/push-reimbursements', [MatrixPaymentController::class, 'pushReimbursements'])->name('matrix.push-reimbursements');
    Route::post('/matrix/push-advances', [MatrixPaymentController::class, 'pushAdvances'])->name('matrix.push-advances');
    Route::post('/matrix/sync-updates', [MatrixPaymentController::class, 'syncUpdates'])->name('matrix.sync-updates');
    Route::post('/matrix/preview-payload', [MatrixPaymentController::class, 'previewPayload'])->name('matrix.preview-payload');
    Route::get('/matrix/instruction-statuses', [MatrixPaymentController::class, 'getInstructionStatuses'])->name('matrix.instruction-statuses');
    // UTR-02 / FF-03 (Finance 7 Nov 2026): no-UTR reasons and F&F links only by the person processing
    // payments (payment_section), Admin or Super Admin
    Route::middleware('role_or_permission:Super Admin|Admin|payment_section')->group(function () {
        Route::post('/expense-payments/utr-exemptions/reason', [\App\Http\Controllers\UtrExemptionController::class, 'setReason'])->name('expense-payments.utr-exemptions.reason');
        Route::post('/expense-payments/utr-exemptions/ff', [\App\Http\Controllers\UtrExemptionController::class, 'linkFf'])->name('expense-payments.utr-exemptions.ff');
    });
    // Finance Advance Approvals
    Route::middleware('permission:payment_section')->group(function () {
        Route::get('/expense-payments', [ExpensePaymentController::class, 'displayRecords'])->name('expense-payments.index');
        // UTR-02: reasons for months marked paid with no UTR / ₹0, and F&F settlement links
        Route::get('/expense-payments/utr-exemptions', [\App\Http\Controllers\UtrExemptionController::class, 'index'])->name('expense-payments.utr-exemptions');
        // FOCUS-LOG-01: read-only Focus export log
        Route::get('/focus-export-log', [\App\Http\Controllers\FocusExportLogController::class, 'index'])->name('focus-export-log');
        Route::get('/expense-payments/pending', [ExpensePaymentController::class, 'displayRecords'])->defaults('chkval', 1)->name('expense-payments.pending');
        Route::get('/expense-payments/approved', [ExpensePaymentController::class, 'displayRecords'])->defaults('chkval', 2)->name('expense-payments.approved');
        Route::get('/expense-payments/instructed', [ExpensePaymentController::class, 'displayRecords'])->defaults('chkval', 3)->name('expense-payments.instructed');
        Route::get('/expense-payments/confirmed', [ExpensePaymentController::class, 'displayRecords'])->defaults('chkval', 4)->name('expense-payments.confirmed');
        Route::get('/expense-payments/unsettled', [ExpensePaymentController::class, 'displayRecords'])->defaults('chkval', 5)->name('expense-payments.unsettled');
        // AJAX routes
        Route::post('/expense-payments/approve', [ExpensePaymentController::class, 'approveClaimMonth'])->name('expense-payments.approve');
        Route::post('/expense-payments/release', [ExpensePaymentController::class, 'releasePayments'])->name('expense-payments.release');
        Route::post('/expense-payments/revert', [ExpensePaymentController::class, 'revertPaymentStage'])->name('expense-payments.revert');
        Route::get('/expense-payments/revert-logs', [ExpensePaymentController::class, 'getRevertLogs'])->name('expense-payments.revert-logs');
        Route::post('/expense-payments/get-unsettled-details', [ExpensePaymentController::class, 'getUnsettledPaymentDetails'])->name('expense-payments.unsettled-details');
        Route::post('/expense-payments/get-available-records', [ExpensePaymentController::class, 'getAvailablePaymentRecords'])->name('expense-payments.available-records');
        Route::post('/expense-payments/get-financial-amounts', [ExpensePaymentController::class, 'getFinancialAmounts'])->name('expense-payments.financial-amounts');
        Route::post('/update-remark', [ExpensePaymentController::class, 'updateRemark'])->name('update-remark');

        Route::post('/expense-payments/update-remark', [ExpensePaymentController::class, 'updateRemark'])->name('expense-payments.update-remark');
        Route::post('/expense-payments/update-other-adjustment', [ExpensePaymentController::class, 'updateOtherAdjustment'])->name('expense-payments.update-other-adjustment');
        Route::post('/expense-payments/get-available-records', [ExpensePaymentController::class, 'getAvailablePaymentRecords'])->name('expense-payments.get-available-records');
        Route::post('/expense-payments/get-unsettled-details', [ExpensePaymentController::class, 'getUnsettledPaymentDetails'])->name('expense-payments.get-unsettled-details');
        Route::post('/expense-payments/resolve-unsettled', [ExpensePaymentController::class, 'resolveUnsettledPayment'])->name('expense-payments.resolve-unsettled');
        Route::post('/expense-payments/mark-as-hold', [ExpensePaymentController::class, 'markAsHold'])->name('mark.as.hold');
        Route::get('/expense-payments/export-excel', [ExpensePaymentController::class, 'exportExcel'])->name('expense-payments.export-excel');
        // Ledger Summary Routes
        Route::get('/expense-payments/ledger-summary', [ExpensePaymentController::class, 'ledgerSummary'])
            ->name('expense-payments.ledger-summary');

        Route::get('/get-employee-details/{employeeId}', [ExpensePaymentController::class, 'getEmployeeLedgerDetails'])
            ->name('get.employee-details');

        Route::get('/expense-payments/export-ledger-summary', [ExpensePaymentController::class, 'exportLedgerSummary'])
            ->name('expense-payments.export-ledger-summary');

        // As-of-date snapshot routes
        Route::get('/expense-payments/ledger-summary-as-of-date', [ExpensePaymentController::class, 'ledgerSummaryAsOfDate'])
            ->name('expense-payments.ledger-summary-as-of-date');

        Route::get('/expense-payments/export-ledger-summary-as-of-date', [ExpensePaymentController::class, 'exportLedgerSummaryAsOfDate'])
            ->name('expense-payments.export-ledger-summary-as-of-date');

        Route::post('/update-ledger-balance', [ExpensePaymentController::class, 'updateLedgerBalance'])
            ->name('update.ledger-balance');

        // Legacy routes for backward compatibility
        Route::get('/ledger-details/{employeeId}', [ExpensePaymentController::class, 'getEmployeeLedgerDetails'])
            ->name('ledger.details');

        Route::get('/print-ledger/{employeeId}', [ExpensePaymentController::class, 'printLedger'])
            ->name('print.ledger');

        // Route::get('/printdetails', [PrintDetailsController::class, 'printDetails'])->name('print.details');
        // Route::get('/account-summary', [AccountSummaryController::class, 'index']);
        // // Print Details & Account Summary
        // Route::get('/printdetails', [PrintDetailsController::class, 'printDetails'])->name('print.details');
        // Route::get('/account-summary', [AccountSummaryController::class, 'index']);

        Route::prefix('finance')->group(function () {
            // Main page
            Route::get('/advance-approvals', [AdvanceApprovalsController::class, 'index'])->name('advance-approvals');
            // API routes for data fetching
            Route::prefix('api')->group(function () {
                Route::get('/pending', [AdvanceApprovalsController::class, 'getPending'])->name('api.pending');
                Route::get('/approved', [AdvanceApprovalsController::class, 'getApproved'])->name('api.approved');
                Route::get('/rejected', [AdvanceApprovalsController::class, 'getRejected'])->name('api.rejected');
                Route::get('/released-payments', [AdvanceApprovalsController::class, 'getReleasedPayments'])->name('api.released-payments');
                Route::get('/confirmed-payments', [AdvanceApprovalsController::class, 'getConfirmedPayments'])->name('api.confirmed-payments');
                Route::get('/unsettled-payments', [AdvanceApprovalsController::class, 'getUnsettledPayments'])->name('api.unsettled-payments');
                // Action routes
                Route::post('/approve', [AdvanceApprovalsController::class, 'approve'])->name('api.approve');
                Route::post('/reject', [AdvanceApprovalsController::class, 'reject'])->name('api.reject');
                Route::post('/release-payment', [AdvanceApprovalsController::class, 'releasePayment'])->name('api.release-payment');
                Route::post('/resolve-discrepancy', [AdvanceApprovalsController::class, 'resolveDiscrepancy'])->name('api.resolve-discrepancy');
                // Export/Import routes
                Route::get('/export-payments', [AdvanceApprovalsController::class, 'exportPayments'])->name('api.export-payments');
                Route::post('/import-payment', [AdvanceApprovalsController::class, 'importPayment'])->name('api.import-payment');
            });
        });
    });
});

// CLAIM MANAGEMENT ROUTES
Route::get('/claims', [ClaimController::class, 'index'])->name('claims.index');
Route::get('/claims/display', [ClaimController::class, 'displayRecords'])->name('claims.display');
Route::post('/claims/month-details', [ClaimController::class, 'getMonthDetails'])->name('claims.month.details');
Route::post('/claims/close-month', [ClaimController::class, 'closeClaimMonth'])->name('claims.close.month');
Route::post('/claims/reject-finance', [ClaimController::class, 'rejectClaimFinance'])->name('claims.reject-finance');

// Open Month Management
Route::get('/open-month', [OpenMonthController::class, 'index'])->name('open-month.index');
Route::post('/open-month', [OpenMonthController::class, 'openMonth'])->name('open-month.store');

// Claim details view in iframe
Route::get('/showclaim', [ClaimController::class, 'showClaim'])->name('showclaim');
Route::post('/claims/get-value', [ClaimController::class, 'getFormValue'])->name('claims.get-value');
Route::post('/claims/update', [ClaimController::class, 'updateClaim'])->name('claims.update');
// Claim details modal routes
Route::prefix('claims')->group(function () {
    Route::get('/show-details/{expid}', [ClaimController::class, 'showExpenseDetails'])->name('claims.show-details');
    Route::post('/show-form', [ClaimController::class, 'showClaimForm'])->name('claims.show-form');
    Route::post('/get-limit', [ClaimController::class, 'getLimit'])->name('claims.get-limit');
    Route::post('/verify', [ClaimController::class, 'verifyClaim'])->name('claims.verify');
    Route::post('/approve', [ClaimController::class, 'approveClaim'])->name('claims.approve');
    Route::post('/finance', [ClaimController::class, 'financeClaim'])->name('claims.finance');

    // FIXED: Use consistent naming with dash
    Route::post('/approve-finance', [ClaimController::class, 'approveClaimFinance'])->name('claims.approve-finance');
    Route::post('/reject-finance', [ClaimController::class, 'rejectClaimFinance'])->name('claims.reject-finance');
});

// Image preview
Route::get('/img-preview', [ClaimController::class, 'imagePreview'])->name('img-preview');

// Additional AJAX endpoints
Route::post('/claimlistajax', [\App\Http\Controllers\ClaimAjaxController::class, 'handleAjax'])->name('claimlistajax');
Route::post('/claim-pending', [ClaimController::class, 'getEmployeeClaimsAjax'])->name('claims.pending.get.claims');

Route::get('/employees-multiple-vehicles', [ReportController::class, 'getEmployeesWithMultipleVehiclesView'])->name('employees.multiple.vehicles');
Route::get('/api/employees-multiple-vehicles', [ReportController::class, 'getEmployeesWithMultipleVehicles'])->name('api.employees.multiple.vehicles');

// Routes for submission reminders and email notifications
// UAT E-14: admin screen and manual sends need a login and the permission of the
// "Submission Reminder" menu (seection_settings). Scheduled sends use the cron/* URLs in
// routes/web.php or the artisan commands, not these routes.
Route::middleware(['auth', 'permission:seection_settings'])->group(function () {
    Route::get('submission-reminder', [NotificationController::class, 'submissionReminder'])->name('submission-reminder');
    Route::get('submission-reminder/emails', [NotificationController::class, 'sendDynamicNonSubmittedEmails'])->name('submission-reminder.emails');
    Route::post('submission-reminder/send', [NotificationController::class, 'sendDynamicNonSubmittedEmails'])->name('submission-reminder.send');
    Route::get('receiving-pending/emails', [NotificationController::class, 'sendReceivingPendingEmails'])->name('receiving-pending.emails');
    Route::post('receiving-pending/send', [NotificationController::class, 'sendReceivingPendingEmails'])->name('receiving-pending.send');
    Route::post('hardcopy-escalation/send', [NotificationController::class, 'sendHardCopyEscalationEmails'])->name('hardcopy-escalation.send');
    Route::get('hardcopy-escalation/emails', [NotificationController::class, 'sendHardCopyEscalationEmails'])->name('hardcopy-escalation.emails');
});

// Resigned Employees Final Expense Submission Reminder & Testing Page
Route::prefix('resigned-submission-reminder')->name('admin.resigned-submission.')->group(function () {
    Route::get('/', [\App\Http\Controllers\admin\ResignedSubmissionEmailController::class, 'testPage'])->name('test');
    Route::get('/preview', [\App\Http\Controllers\admin\ResignedSubmissionEmailController::class, 'preview'])->name('preview');
    Route::post('/send', [\App\Http\Controllers\admin\ResignedSubmissionEmailController::class, 'send'])->name('send');
    Route::get('/employees', [\App\Http\Controllers\admin\ResignedSubmissionEmailController::class, 'getEmployees'])->name('employees');
    Route::get('/logs', [\App\Http\Controllers\admin\ResignedSubmissionEmailController::class, 'getLogs'])->name('logs');
    Route::get('/logs/{id}', [\App\Http\Controllers\admin\ResignedSubmissionEmailController::class, 'getLogDetail'])->name('logs.detail');
    Route::post('/template', [\App\Http\Controllers\admin\ResignedSubmissionEmailController::class, 'updateTemplate'])->name('template.update');
    Route::post('/settings', [\App\Http\Controllers\admin\ResignedSubmissionEmailController::class, 'updateSettings'])->name('settings.update');
    Route::get('/docs/download', [\App\Http\Controllers\admin\ResignedSubmissionEmailController::class, 'downloadDocs'])->name('docs.download');
});
// Print Details & Account Summary
Route::get('/printdetails', [PrintDetailsController::class, 'printDetails'])->name('print.details');
Route::get('/account-summary', [AccountSummaryController::class, 'index']);
// KM-CAP-01: HOD approval of running km above the monthly limit
Route::middleware(['auth'])->group(function () {
    Route::get('/km-cap-approvals', [\App\Http\Controllers\admin\KmCapApprovalController::class, 'index'])->name('km.cap.approvals');
    Route::post('/km-cap-approvals/{id}', [\App\Http\Controllers\admin\KmCapApprovalController::class, 'decide'])->whereNumber('id')->name('km.cap.approvals.decide');
});
// FOCUS-RECON-01: Focus ledger vs Xeasy
Route::middleware(['auth', 'role_or_permission:Super Admin|Admin|Ledger Summary|Finance'])->group(function () {
    Route::get('/focus-ledger-recon', [\App\Http\Controllers\FocusLedgerReconController::class, 'index'])->name('focus.ledger.recon');
    Route::post('/focus-ledger-recon', [\App\Http\Controllers\FocusLedgerReconController::class, 'upload'])->name('focus.ledger.recon.upload');
    Route::get('/focus-ledger-recon/export', [\App\Http\Controllers\FocusLedgerReconController::class, 'export'])->name('focus.ledger.recon.export');
});
// F-02 / F-06 / F-08: Finance review flags (peer outliers, bulk back-fill, round numbers)
Route::middleware(['auth', 'role_or_permission:Super Admin|Admin|Ledger Summary|Finance'])->group(function () {
    Route::get('/finance-review-flags', [\App\Http\Controllers\FinanceReviewFlagsController::class, 'index'])->name('finance.review.flags');
    Route::get('/finance-review-flags/export', [\App\Http\Controllers\FinanceReviewFlagsController::class, 'export'])->name('finance.review.flags.export');
});

// Advance Request & Adjustment Report
Route::get('/advance-adjustment-report', [\App\Http\Controllers\AdvanceAdjustmentReportController::class, 'index'])->name('reports.advance-adjustment');
Route::get('/advance-adjustment-report/export', [\App\Http\Controllers\AdvanceAdjustmentReportController::class, 'export'])->name('reports.advance-adjustment.export');

// ── BTC-01: Bill To Company process ─────────────────────────────────────────
Route::prefix('btc')->name('btc.')->group(function () {
    Route::get('/', [\App\Http\Controllers\admin\BtcController::class, 'index'])->name('index');
    Route::get('/data', [\App\Http\Controllers\admin\BtcController::class, 'data'])->name('data');
    Route::post('/', [\App\Http\Controllers\admin\BtcController::class, 'store'])->name('store');
    Route::get('/case/{id}', [\App\Http\Controllers\admin\BtcController::class, 'show'])->whereNumber('id')->name('show');
    Route::post('/case/{id}/act', [\App\Http\Controllers\admin\BtcController::class, 'act'])->whereNumber('id')->name('act');
    Route::get('/file/{fileId}', [\App\Http\Controllers\admin\BtcController::class, 'file'])->whereNumber('fileId')->name('file');
    Route::get('/export/focus', [\App\Http\Controllers\admin\BtcController::class, 'focusExport'])->name('export.focus');
    Route::get('/export/boarding-pass', [\App\Http\Controllers\admin\BtcController::class, 'boardingPassExport'])->name('export.boarding');
    Route::get('/approvers', [\App\Http\Controllers\admin\BtcController::class, 'approverSearch'])->name('approvers');
    // BOARDING-01: vendor boarding-pass portal links (Finance)
    Route::get('/vendor-links', [\App\Http\Controllers\VendorBoardingPassController::class, 'links'])->name('vendor.links');
    Route::post('/vendor-links', [\App\Http\Controllers\VendorBoardingPassController::class, 'create'])->name('vendor.create');
    Route::post('/vendor-links/{id}/revoke', [\App\Http\Controllers\VendorBoardingPassController::class, 'revoke'])->whereNumber('id')->name('vendor.revoke');
});

Route::middleware('auth')->group(function () {
    // HARDCOPY-01: Submit Hardcopy Bills (employee)
    Route::get('/hardcopy-bills', [\App\Http\Controllers\HardcopySubmissionController::class, 'index'])->name('hardcopy.index');
    Route::post('/hardcopy-bills', [\App\Http\Controllers\HardcopySubmissionController::class, 'store'])->name('hardcopy.store');
    Route::get('/hardcopy-bills/file/{id}', [\App\Http\Controllers\HardcopySubmissionController::class, 'file'])->whereNumber('id')->name('hardcopy.file');
    Route::get('/hardcopy-bills/summary-sheet', [\App\Http\Controllers\HardcopySubmissionController::class, 'summary'])->name('hardcopy.summary');
    // UPI-PROOF-01: UPI payment proof on a claim (access checked in the controller)
    Route::get('/claims/{expId}/upi-proof', [\App\Http\Controllers\UpiProofController::class, 'panel'])->whereNumber('expId')->name('upi-proof.panel');
    Route::post('/claims/{expId}/upi-proof', [\App\Http\Controllers\UpiProofController::class, 'store'])->whereNumber('expId')->middleware('throttle:30,1')->name('upi-proof.store');
    Route::post('/upi-proof/{id}/remove', [\App\Http\Controllers\UpiProofController::class, 'remove'])->whereNumber('id')->name('upi-proof.remove');
    Route::get('/upi-proof/file/{id}', [\App\Http\Controllers\UpiProofController::class, 'file'])->whereNumber('id')->name('upi-proof.file');

    // ELIG-REPORT-01 / PUNCH-TRACK-01: reports
    Route::get('/eligibility-report', [\App\Http\Controllers\admin\EligibilityReportController::class, 'index'])->name('eligibility.report');
    Route::get('/eligibility-report/export', [\App\Http\Controllers\admin\EligibilityReportController::class, 'export'])->name('eligibility.report.export');
    // PKG-01: download the staging code package (Super Admin only; file kept outside public/)
    Route::get('/admin/staging-package', function () {
        $f = storage_path('app/staging_package/expense_staging_code_07nov2026.tgz');
        abort_unless(is_file($f), 404);
        \Illuminate\Support\Facades\Log::info('Staging package downloaded', ['user' => auth()->id(), 'ip' => request()->ip()]);

        return response()->download($f);
    })->middleware('role:Super Admin')->name('staging.package');
    // REM-SET-01: reminder mail settings
    Route::get('/reminder-settings', [\App\Http\Controllers\admin\ReminderSettingsController::class, 'index'])->middleware('role_or_permission:Super Admin|Admin')->name('reminder.settings');
    Route::post('/reminder-settings', [\App\Http\Controllers\admin\ReminderSettingsController::class, 'update'])->middleware('role_or_permission:Super Admin|Admin')->name('reminder.settings.update');
    // RET-01
    Route::get('/records-retention', [\App\Http\Controllers\admin\RecordsRetentionController::class, 'index'])->middleware('role_or_permission:Super Admin|Admin|payment_section')->name('records.retention');
    // ADV-POL-01
    Route::get('/advance-policy-check', [\App\Http\Controllers\admin\AdvancePolicyCheckController::class, 'index'])->name('advance.policy.check');
    // VEH-RATE-REPORT-01 / RATE-EFF-01
    Route::get('/vehicle-rate-gaps', [\App\Http\Controllers\admin\VehicleRateGapsController::class, 'index'])->name('vehicle.rate.gaps');
    Route::get('/vehicle-rate-gaps/export', [\App\Http\Controllers\admin\VehicleRateGapsController::class, 'export'])->name('vehicle.rate.gaps.export');
    Route::post('/vehicle-rate-gaps/effective', [\App\Http\Controllers\admin\VehicleRateGapsController::class, 'setEffective'])->middleware('role_or_permission:Super Admin|Admin')->name('vehicle.rate.effective');
    Route::get('/punching-change-report', [\App\Http\Controllers\admin\PunchChangeReportController::class, 'index'])->name('punch.change.report');
    Route::get('/punching-change-report/export', [\App\Http\Controllers\admin\PunchChangeReportController::class, 'export'])->name('punch.change.report.export');
});

// ── Bill to Company ──────────────────────────────────────────────────────────
Route::prefix('bill-to-company')->name('bill-to-company.')->group(function () {
    Route::get('/', [\App\Http\Controllers\admin\BillToCompanyController::class, 'index'])->name('index');
    Route::get('/data', [\App\Http\Controllers\admin\BillToCompanyController::class, 'getData'])->name('data');
    Route::post('/', [\App\Http\Controllers\admin\BillToCompanyController::class, 'store'])->name('store');
    Route::get('/employees/search', [\App\Http\Controllers\admin\BillToCompanyController::class, 'searchEmployees'])->name('employees.search');

    // ── Categories ───────────────────────────────────────────────────────────
    Route::get('/categories/list', [\App\Http\Controllers\admin\BillToCompanyCategoryController::class, 'list'])->name('categories.list');
    Route::prefix('categories')->name('categories.')->group(function () {
        Route::get('/', [\App\Http\Controllers\admin\BillToCompanyCategoryController::class, 'index'])->name('index');
        Route::get('/data', [\App\Http\Controllers\admin\BillToCompanyCategoryController::class, 'getData'])->name('data');
        Route::post('/', [\App\Http\Controllers\admin\BillToCompanyCategoryController::class, 'store'])->name('store');
        Route::put('/{id}', [\App\Http\Controllers\admin\BillToCompanyCategoryController::class, 'update'])->name('update');
        Route::post('/toggle/{id}', [\App\Http\Controllers\admin\BillToCompanyCategoryController::class, 'toggle'])->name('toggle');
        Route::delete('/{id}', [\App\Http\Controllers\admin\BillToCompanyCategoryController::class, 'destroy'])->name('destroy');
    });

    Route::get('/{id}', [\App\Http\Controllers\admin\BillToCompanyController::class, 'show'])->name('show');
    Route::post('/{id}/remarks', [\App\Http\Controllers\admin\BillToCompanyController::class, 'updateRemarks'])->name('update-remarks');
    Route::delete('/{id}', [\App\Http\Controllers\admin\BillToCompanyController::class, 'destroy'])->name('destroy');
});

// PAX Details API Routes for Claims (Lodging, Meals, Rail Fare, etc.)
// These endpoints retrieve and manage PAX details, employee eligibility, and quotas
Route::prefix('api/pax-details')->name('api.pax.')->group(function () {
    // Get PAX details for a specific claim
    Route::get('/get-claim-pax', [\App\Http\Controllers\PaxDetailsController::class, 'getPaxDetails'])
        ->name('get');

    // Update quota for rail fare claims (Data Punch)
    Route::post('/update-quota', [\App\Http\Controllers\PaxDetailsController::class, 'updateQuota'])
        ->name('update-quota');

    // Update PAX participants for group claims
    Route::post('/update-participants', [\App\Http\Controllers\PaxDetailsController::class, 'updatePaxDetails'])
        ->name('update-participants');

    // Upload supporting document for PAX details
    Route::post('/upload-document', [\App\Http\Controllers\PaxDetailsController::class, 'uploadDocument'])
        ->name('upload-document');
});
