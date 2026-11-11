<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\admin\DashboardController;
use App\Http\Controllers\admin\NotificationController;
use App\Http\Controllers\PaymentVerificationController;
use App\Http\Controllers\ExpensePdfController;
use App\Http\Controllers\admin\PrintDetailsEmpController;
use App\Http\Controllers\ApproverController;
use App\Http\Controllers\DuplicateExpenseController;
use Illuminate\Support\Facades\Route;
use Illuminate\Http\Request;

Route::match(['get', 'post'], '/', [AuthController::class, 'login'])->middleware('throttle:20,1')->name('login'); // LV-S02: rate limit
Route::get('login', [AuthController::class, 'showLoginForm'])->name('login.form');
Route::get('xeasy_login', [AuthController::class, 'login'])->middleware('throttle:20,1');
Route::post('logout', [AuthController::class, 'logout'])->name('logout');

// PRIV-01: employee privacy notice (public, linked from the login page and the app)
Route::view('privacy', 'legal.privacy-notice')->name('privacy.notice');

// BOARDING-01: vendor boarding-pass portal (no Xeasy login; the random link is the credential)
Route::middleware('throttle:30,1')->group(function () {
    Route::get('vendor/boarding-pass/{token}', [\App\Http\Controllers\VendorBoardingPassController::class, 'show'])->name('vendor.boarding.show');
    Route::post('vendor/boarding-pass/{token}/{caseId}', [\App\Http\Controllers\VendorBoardingPassController::class, 'upload'])->whereNumber('caseId')->name('vendor.boarding.upload');
});


// Test route to check financial years
Route::get('test/financial-years', function () {
    try {
        $years = \App\Models\FinancialYear::orderBy('YearId', 'desc')->get(['YearId', 'Year']);
        $output = '<h2>Financial Years in Database:</h2><ul>';
        foreach($years as $year) {
            $output .= '<li>ID: ' . $year->YearId . ' - Year: ' . $year->Year . '</li>';
        }
        $output .= '</ul>';
        return $output;
    } catch (\Exception $e) {
        return 'Error: ' . $e->getMessage();
    }
});

Route::prefix('payment-verification')->name('payment-verification.')->group(function () {

    /**
     * Bulk payment verification
     * POST /api/payment-verification/verify-bulk
     */
    Route::post('/verify-bulk', [PaymentVerificationController::class, 'verifyBulk'])
        ->name('verify-bulk');


Route::post('/verify-unsettled', [PaymentVerificationController::class, 'verifyUnsettled']);

    // UAT-03: route to getVerificationStatus removed - the method never existed (500)

    // UAT-03: route to retryFailed removed - the method never existed (500)

    // UAT-03: route to export removed - the method never existed (500)

    // UAT-03: route to getSummary removed - the method never existed (500)
    /**
     * Optional: Get unsettled payments list
     * GET /api/payment-verification/unsettled
     */
    Route::get('/unsettled', [PaymentVerificationController::class, 'getUnsettled'] /* UAT-03: method is getUnsettled */)
        ->name('unsettled');

    /**
     * Optional: Resolve unsettled payment
     * POST /api/payment-verification/unsettled/{id}/resolve
     */
    Route::post('/unsettled/{id}/resolve', [PaymentVerificationController::class, 'resolveUnsettled'])
        ->name('unsettled.resolve');


});

// routes/web.php
Route::get('/approver/team-members', [ApproverController::class, 'getTeamMembersList'])->name('approver.team.members');
Route::get('/approver/expense-types', [ApproverController::class, 'getClaimTypesList'])->name('approver.expense.types');
// Team Expense Routes
Route::get('approver/team-expense', [ApproverController::class, 'teamExpense'])->name('approver.team.expense');
Route::get('approver/team-runningkm', [ApproverController::class, 'teamRunningKm'])->name('approver.team.runningkm');
// Approver Routes
Route::prefix('approver')->group(function () {
    // Main view route — original claim management page
    Route::get('/', [ApproverController::class, 'index'])->name('approver.index');

    // Analytics dashboard route
    Route::get('dashboard', [ApproverController::class, 'dashboard'])->name('approver.dashboard');

    // API/Data routes
    Route::get('pending-list', [ApproverController::class, 'pendingList'])->name('approver.pending.list');
    Route::get('verified-list', [ApproverController::class, 'verifiedList'])->name('approver.verified.list');
    Route::get('approved-list', [ApproverController::class, 'approvedList'])->name('approver.approved.list');
    Route::get('denied-list', [ApproverController::class, 'deniedList'])->name('approver.denied.list');

    // Claim details routes
    Route::get('expense-details', [ApproverController::class, 'expenseDetails'])->name('approver.expense.details');
    Route::get('claim-details', [ApproverController::class, 'claimDetails'])->name('approver.claim.details');
    Route::get('month-details', [ApproverController::class, 'monthDetails'])->name('approver.month.details');

    // Action routes
    Route::post('approve', [ApproverController::class, 'approve'])->name('approver.approve');
    Route::post('deny', [ApproverController::class, 'deny'])->name('approver.deny');
    Route::post('submit', [ApproverController::class, 'submit'])->name('approver.submit');

    // Report routes
    Route::get('running-km', [ApproverController::class, 'runningKm'])->name('approver.running.km');
    Route::get('expense-report', [ApproverController::class, 'expenseReport'])->name('approver.expense.report');
    Route::get('month-summary-print', [ApproverController::class, 'monthSummaryPrint'])->name('approver.month.summary.print');

    // Courier routes
    Route::post('save-courier', [ApproverController::class, 'saveCourier'])->name('approver.save.courier');
    Route::get('courier-detail', [ApproverController::class, 'courierDetail'])->name('approver.courier.detail');
});
 // LV-S01: GET check/{empCode} removed - it returned any employee's HRMS password in clear text.
 Route::get('/expense-pdf', [ExpensePdfController::class, 'generate']);
 Route::get('/expense/print-details', [PrintDetailsEmpController::class, 'generate'])->name('expense.print.details');


Route::middleware('auth')->group(function () {
    Route::get('dashboard', [DashboardController::class, 'index'])->name('dashboard');
    Route::get('dashboard/my-claims', [DashboardController::class, 'getMyClaimsMonthWise'])->name('dashboard.my-claims');
    Route::get('dashboard/odometer-trend', [DashboardController::class, 'getOdometerTrendByYear'])->name('dashboard.odometer-trend');
    // LV-S11 / LV-U04: the git command panel route was removed (no controller; never on a live server)
    // Submission Deadline Settings
    Route::get('admin/submission-settings', [\App\Http\Controllers\admin\SubmissionSettingController::class, 'index'])->name('admin.submission-settings.index');
    Route::put('admin/submission-settings', [\App\Http\Controllers\admin\SubmissionSettingController::class, 'update'])->name('admin.submission-settings.update');

    // LV-U04: auto-verify settings are registered once, in routes/backend.php (admin group)

    // Activity Log Viewer
    Route::get('admin/activity-log', [\App\Http\Controllers\admin\ActivityLogController::class, 'index'])->name('admin.activity-log.index');
    Route::get('admin/activity-log/data', [\App\Http\Controllers\admin\ActivityLogController::class, 'getData'])->name('admin.activity-log.data');
    Route::get('admin/activity-log/filters', [\App\Http\Controllers\admin\ActivityLogController::class, 'getFilters'])->name('admin.activity-log.filters');

    // Employee Ledger Import pages
    Route::get('employee-ledger', function () {
        return view('admin.employee-ledger.import');
    })->name('employee-ledger.index');

    Route::get('admin/employee-ledger/import', function () {
        return view('admin.employee-ledger.import');
    })->name('admin.employee-ledger.import');

    // Duplicate Expense Management Routes
    Route::prefix('admin/duplicate-expense')->name('duplicate-expense.')->group(function () {
        Route::get('/', [DuplicateExpenseController::class, 'index'])->name('index');
        Route::delete('/destroy', [DuplicateExpenseController::class, 'destroy'])->name('destroy');
        Route::delete('/bulk-destroy', [DuplicateExpenseController::class, 'bulkDestroy'])->name('bulk-destroy');
        Route::get('/{expId}', [DuplicateExpenseController::class, 'getExpenseDuplicates'])->name('get-duplicates');

        // Debug route
        Route::get('/debug/info', function () {
            return response()->json([
                'session' => [
                    'year_id' => session('year_id'),
                    'expense_connection' => session('expense_connection'),
                    'role_id' => session('role_id'),
                    'emp_role' => session('emp_role'),
                    'user' => \Illuminate\Support\Facades\Auth::user() ? \Illuminate\Support\Facades\Auth::user()->name : 'Not logged in',
                ],
                'roles' => \Illuminate\Support\Facades\Auth::user() ? \Illuminate\Support\Facades\Auth::user()->getRoleNames() : [],
            ]);
        });
    });

    // Expense Tree Report Routes
    Route::get('expense-tree-report', [\App\Http\Controllers\admin\ExpenseTreeReportController::class, 'index'])->name('expense-tree-report');
    Route::get('admin/reports/expense-tree-report', [\App\Http\Controllers\admin\ExpenseTreeReportController::class, 'index']);
    Route::match(['get', 'post'], 'expense-tree-report/tree-data', [\App\Http\Controllers\admin\ExpenseTreeReportController::class, 'getTreeData'])->name('expense-tree-report.tree-data');
    Route::get('expense-tree-report/export', [\App\Http\Controllers\admin\ExpenseTreeReportController::class, 'export'])->name('expense-tree-report.export');

    // Company Multi-Year Expense Report Routes
    Route::get('company-multi-year-expense-report', [\App\Http\Controllers\admin\CompanyMultiYearExpenseReportController::class, 'index'])->name('company-multi-year-expense-report');
    Route::get('admin/reports/company-multi-year-expense-report', [\App\Http\Controllers\admin\CompanyMultiYearExpenseReportController::class, 'index']);
    Route::get('company-multi-year-expense-report/data', [\App\Http\Controllers\admin\CompanyMultiYearExpenseReportController::class, 'getData'])->name('company-multi-year-expense-report.data');
    Route::get('company-multi-year-expense-report/export', [\App\Http\Controllers\admin\CompanyMultiYearExpenseReportController::class, 'export'])->name('company-multi-year-expense-report.export');
});




// Cron Job Routes (public, year_id required)
Route::get('cron/send-submission-reminder', [NotificationController::class, 'sendDynamicNonSubmittedEmails'])
    ->name('cron.submission-reminder');
Route::get('cron/send-courier-pending', [NotificationController::class, 'sendReceivingPendingEmails'])
    ->name('cron.courier-pending');
Route::get('cron/send-hardcopy-escalation', [NotificationController::class, 'sendHardCopyEscalationEmails'])
    ->name('cron.hardcopy-escalation');

// VVNR Cron Job Routes (Company 11)
Route::prefix('cron/vvnr')->name('cron.vvnr.')->group(function () {
    Route::get('send-submission-reminder', [\App\Http\Controllers\admin\VvnrEmailNotificationController::class, 'cronSubmissionReminder'])
        ->name('submission-reminder');
    Route::get('send-courier-pending', [\App\Http\Controllers\admin\VvnrEmailNotificationController::class, 'cronCourierPending'])
        ->name('courier-pending');
    Route::get('send-hardcopy-escalation', [\App\Http\Controllers\admin\VvnrEmailNotificationController::class, 'cronHardcopyEscalation'])
        ->name('hardcopy-escalation');
    Route::get('send-approval-reminder', [\App\Http\Controllers\admin\VvnrEmailNotificationController::class, 'cronApprovalReminder'])
        ->name('approval-reminder');
    Route::get('send-hierarchy-summary', [\App\Http\Controllers\admin\VvnrEmailNotificationController::class, 'cronHierarchyMonthlySummary'])
        ->name('hierarchy-summary');
    Route::get('daily-automation', [\App\Http\Controllers\admin\VvnrEmailNotificationController::class, 'cronDailyAutomation'])
        ->name('daily-automation');
});



// Include employee ledger routes
require __DIR__.'/employee-ledger.php';

// TEST-S3-01: claim files kept locally on a test server (CLAIM_FILES_LOCAL=true); login required. Not registered on live.
if (config('filesystems.disks.s3.driver') === 'local') {
    Route::middleware('auth')->get('/s3-local/{path}', function (string $path) {
        $disk = \Illuminate\Support\Facades\Storage::disk('s3');
        abort_if(str_contains($path, '..') || ! $disk->exists($path), 404);

        return $disk->response($path);
    })->where('path', '.*')->name('s3local.file');
}
