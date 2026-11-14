<?php

use App\Http\Controllers\Api\Advance\AdvanceApprovalController;
use App\Http\Controllers\Api\Advance\AdvanceFinanceController;
use App\Http\Controllers\Api\Advance\AdvanceRequestController;
use App\Http\Controllers\Api\Advance\HodAdvanceReportController;
use App\Http\Controllers\Api\Advance\BuAdvanceReportController;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;

Route::prefix('advance')->group(function () {
    Route::options('/draft', function () {
        return response('', 200)
            ->header('Access-Control-Allow-Origin', 'https://expense.vnrseeds.co.in')
            ->header('Access-Control-Allow-Methods', 'GET, POST, PUT, DELETE, OPTIONS')
            ->header('Access-Control-Allow-Headers', 'Content-Type, Authorization, X-Requested-With')
            ->header('Access-Control-Allow-Credentials', 'true')
            ->header('Access-Control-Max-Age', '86400');
    });

    Route::post('/draft', [AdvanceRequestController::class, 'storeDraft']);
    Route::put('/draft/{requestId}', [AdvanceRequestController::class, 'updateDraft']);
    Route::post('/submit/{requestId}', [AdvanceRequestController::class, 'submit']);
    Route::post('/resubmit/{requestId}', [AdvanceRequestController::class, 'resubmit']);

    // 14 Nov 2026: ADV-CONTEXT-01 ledger / last advance, ADV-FX-ELIG-01 foreign-currency eligibility, ADV-WITHDRAW-01
    Route::get('/employee-context', [\App\Http\Controllers\Api\Advance\AdvanceExtrasController::class, 'context']);
    Route::get('/fx-eligibility', [\App\Http\Controllers\Api\Advance\AdvanceExtrasController::class, 'fxEligibility']);
    Route::post('/withdraw/{lineId}', [\App\Http\Controllers\Api\Advance\AdvanceExtrasController::class, 'withdraw'])->whereNumber('lineId');

    // Approval (must come before generic routes)
    Route::get('/approvals/pending', [AdvanceApprovalController::class, 'pending']);
    Route::get('/approvals/approved', [AdvanceApprovalController::class, 'approved']);
    Route::get('/approvals/rejected', [AdvanceApprovalController::class, 'rejected']);
    Route::get('/submitted-for-me', [AdvanceApprovalController::class, 'submittedForMe']);
    Route::get('/rejected-for-me', [AdvanceApprovalController::class, 'rejectedForMe']);
    Route::get('/employee-approval-summary/{requestId}', [AdvanceApprovalController::class, 'employeeApprovalSummary'])->where('requestId', '[0-9]+');
    Route::post('/approve', [AdvanceApprovalController::class, 'approve']);
    Route::post('/reject', [AdvanceApprovalController::class, 'reject']);
    Route::post('/revert', [AdvanceApprovalController::class, 'revert']);

    // Finance (must come before generic routes)
    // FX-53: exchange rates for foreign-currency advances
    Route::get('/fx/rates', [\App\Http\Controllers\Api\Advance\AdvanceFxRateController::class, 'index']);
    Route::get('/fx/quote', [\App\Http\Controllers\Api\Advance\AdvanceFxRateController::class, 'quote']);
    Route::post('/fx/rates', [\App\Http\Controllers\Api\Advance\AdvanceFxRateController::class, 'store']);

    Route::get('/finance/pending', [AdvanceFinanceController::class, 'pending']);
    Route::get('/finance/rejected', [AdvanceFinanceController::class, 'rejected']);
    Route::get('/finance/approved', [AdvanceFinanceController::class, 'approved']);
    Route::get('/finance/released-payments', [AdvanceFinanceController::class, 'releasedPayments']);
    Route::get('/finance/payment-batches', [AdvanceFinanceController::class, 'paymentBatches']);
    Route::get('/finance/employee-balance', [AdvanceFinanceController::class, 'getEmployeeBalance']);
    Route::get('/finance/export-payments', [AdvanceFinanceController::class, 'exportPayments']);

    Route::post('/finance/release-payment', [AdvanceFinanceController::class, 'releasePayment']);
    Route::post('/finance/approve', [AdvanceFinanceController::class, 'approve']);
    Route::post('/finance/reject', [AdvanceFinanceController::class, 'reject']);
    Route::post('/finance/revert', [AdvanceFinanceController::class, 'revert']);

    // Focus Integration & Finalization
    Route::get('/finance/focus-export-ready', [AdvanceFinanceController::class, 'getActivitiesForFocusExport']);
    Route::get('/finance/finalization-ready', [AdvanceFinanceController::class, 'getFinalizationReady']);
    Route::get('/finance/finalized', [AdvanceFinanceController::class, 'getFinalized']);
    Route::post('/finance/mark-exported', [AdvanceFinanceController::class, 'markAsExported']);
    Route::post('/finance/mark-imported', [AdvanceFinanceController::class, 'markAsImported']);
    Route::post('/finance/confirm-payments', [AdvanceFinanceController::class, 'confirmAdvancePayments']);
    Route::post('/finance/confirm-expense-payments', [AdvanceFinanceController::class, 'confirmExpensePayments']);
    Route::post('/finance/force-resync-payments', [AdvanceFinanceController::class, 'forceResyncPayments']);
    Route::get('/finance/confirmed-payments', [AdvanceFinanceController::class, 'getConfirmedPayments']);
    Route::get('/finance/employee-confirmed-payments', [AdvanceFinanceController::class, 'getEmployeeConfirmedPayments']);
    Route::get('/finance/unsettled-payments', [AdvanceFinanceController::class, 'getUnsettledPayments']);
    Route::post('/finance/cleanup-unsettled-duplicates', [AdvanceFinanceController::class, 'cleanupUnsettledDuplicates']);
    Route::post('/finance/resolve-discrepancy', [AdvanceFinanceController::class, 'resolveDiscrepancy']);
    Route::post('/finance/manual-expense-settlement', [AdvanceFinanceController::class, 'manualExpenseSettlement']);
    Route::post('/finance/settle-unsettled-payment', [AdvanceFinanceController::class, 'settleUnsettledPayment']);

    // Export tracking endpoints
    Route::get('/finance/export-history', [AdvanceFinanceController::class, 'getExportHistory']);
    Route::post('/finance/reset-export-status', [AdvanceFinanceController::class, 'resetExportStatus']);
    Route::get('/finance/activity-export-logs/{activityId}', [AdvanceFinanceController::class, 'getActivityExportLogs']);

    // HOD Reports - Team advance request reports
    Route::get('/hod/consolidated-report', [HodAdvanceReportController::class, 'consolidatedReport']);
    Route::get('/hod/team', [HodAdvanceReportController::class, 'getTeamMembersList']);
    Route::get('/hod/employee-level', [HodAdvanceReportController::class, 'getEmployeeLevel']);

    // HOD Hierarchy - Get hierarchy data for filters
    Route::get('/hod/business-units', [HodAdvanceReportController::class, 'getBusinessUnits']);
    Route::get('/hod/zones', [HodAdvanceReportController::class, 'getZones']);
    Route::get('/hod/regions', [HodAdvanceReportController::class, 'getRegions']);
    Route::get('/hod/territories', [HodAdvanceReportController::class, 'getTerritories']);
    Route::get('/hod/hierarchy-mapping', [HodAdvanceReportController::class, 'getHierarchyMapping']);

    // View & delete (must come after specific routes)
    Route::get('/', [AdvanceRequestController::class, 'index']);
    Route::get('/expense', [AdvanceRequestController::class, 'indexExpense']);
    Route::get('/other', [AdvanceRequestController::class, 'indexOther']);
    Route::get('/table-view', [AdvanceRequestController::class, 'tableView']);
    Route::get('/employee-summary', [AdvanceRequestController::class, 'employeeSummary']);

    Route::get('/{requestId}', [AdvanceRequestController::class, 'show'])->where('requestId', '[0-9]+');
    Route::delete('/{requestId}', [AdvanceRequestController::class, 'delete'])->where('requestId', '[0-9]+');
});
