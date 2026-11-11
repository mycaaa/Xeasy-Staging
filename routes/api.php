<?php

use App\Http\Controllers\admin\ReportController;
use App\Http\Controllers\admin\EmployeeController;
use App\Http\Controllers\Api\CentralNotificationController;
use App\Http\Controllers\Api\ExpenseTypeReportApiController;
use App\Http\Controllers\Api\RunningKmReportApiController;
use App\Http\Controllers\Api\OdometerBackdateController;
use App\Http\Controllers\Api\PaymentApiController;
use App\Http\Controllers\Api\LedgerSummaryApiController;
use App\Http\Controllers\Api\OpenMonthExpenseDetailsApiController;
use App\Http\Controllers\EmployeeLedgerController;
use Illuminate\Support\Facades\Route;

// ODO-APP-01: the app token must belong to the employee in the URL (the approver)
Route::middleware(\App\Http\Middleware\ActorMatchesRouteEmployee::class)->group(function () {
    Route::get('odo-backdate-submission/{yearId}/{employeeId}/', [OdometerBackdateController::class, 'odoBackdateSubmission']);
    Route::get('odo-backdate-submission-show/{yearId}/{employeeId}/', [OdometerBackdateController::class, 'odoBackdateSubmissionBtnShow']);
    Route::get('odo-backdate-approval/{yearId}/{employeeId}/{expenseId}/', [OdometerBackdateController::class, 'odoBackdateApprove']);
    Route::get('odo-backdate-rejection/{yearId}/{employeeId}/{expenseId}/', [OdometerBackdateController::class, 'odoBackdateReject']);
    Route::get('odo-backdate-bulk-approval/{yearId}/{employeeId}/{empiid}', [OdometerBackdateController::class, 'odoBackdateBulkApproval']);
    Route::get('odo-backdate-bulk-rejection/{yearId}/{employeeId}/{empiid}', [OdometerBackdateController::class, 'odoBackdateBulkRejection']);
});

// Payment API Routes
Route::prefix('payments')->group(function () {
    Route::post('import', [PaymentApiController::class, 'import']);
    Route::get('list', [PaymentApiController::class, 'list']);
    Route::get('details/{id}', [PaymentApiController::class, 'details']);
    Route::get('status/{batchId}', [PaymentApiController::class, 'importStatus']);
    Route::get('batches', [PaymentApiController::class, 'getBatches']);
    Route::delete('delete/{id}', [PaymentApiController::class, 'delete']);
    Route::get('export', [PaymentApiController::class, 'export']);
    Route::get('uploaded-files', [PaymentApiController::class, 'listUploadedFiles']);
}); 

// Employee Ledger API Routes
Route::prefix('employee-ledger')->group(function () {
    Route::get('data', [EmployeeLedgerController::class, 'getData']);
    Route::delete('{id}', [EmployeeLedgerController::class, 'destroy']);
});

// Ledger Summary API Routes
Route::prefix('ledger-summary')->group(function () {
    Route::get('/', [LedgerSummaryApiController::class, 'index']);
    Route::get('/employee/{employeeId}', [LedgerSummaryApiController::class, 'employeeDetails']);
});

// CLEAR-11: account clearance (full & final) for one employee
Route::get('account-clearance/{employeeId}', [\App\Http\Controllers\Api\AccountClearanceApiController::class, 'show'])->whereNumber('employeeId');

// Export Claim Stage Data API Route
Route::post('export-claim-stage-data', [ReportController::class, 'exportClaimStageData']);

// Expense Type Report – grouped export scoped to a manager's reporting employees
// GET /api/expense-type-report/export-grouped-by-manager
// Required : employee_id, year_id
// Optional : month, claim_type_id, department_id, vertical_id, function_id,
//            grade_id, zone_id, region_id, territory_id, emp_status,
//            claim_status, include_self, format (csv|json), company_id
Route::get('expense-type-report/export-grouped-by-manager', [ExpenseTypeReportApiController::class, 'exportGroupedByManager']);

// Running KM Report – export scoped to a manager's reporting employees (all companies)
// GET /api/running-km/export-by-manager
// Required : employee_id, year_id
// Optional : month (1-12 or apr/may/..), vehicle_type (2W|4W), department_id,
//            vertical_id, function_id, grade_id, emp_status,
//            include_self, format (csv|json)
Route::get('running-km/export-by-manager', [RunningKmReportApiController::class, 'exportByManager']);

// Get reporting manager for an employee
Route::get('employee/{employeeId}/reporting-manager', function ($employeeId) {
    $hrimsConn = \App\Helpers\DatabaseSwitcher::getHrimsConnection();
    $data = \Illuminate\Support\Facades\DB::connection($hrimsConn)
        ->table('hrm_employee_general as eg')
        ->join('hrm_employee as e', 'eg.RepEmployeeID', '=', 'e.EmployeeID')
        ->where('eg.EmployeeID', $employeeId)
        ->select([
            'e.EmployeeID as reporting_employee_id',
            'e.EmpCode as reporting_emp_code',
            \Illuminate\Support\Facades\DB::raw("CONCAT(e.Fname, ' ', COALESCE(e.Sname, ''), ' ', e.Lname) as reporting_name"),
        ])
        ->first();

    if ($data) {
        return response()->json(['success' => true, 'data' => $data]);
    }
    return response()->json(['success' => false, 'data' => null]);
});

// Get monthexpensefinal ID for an employee + month
Route::get('month-final-id/{employeeId}/{month}', function ($employeeId, $month) {
    $yearId = (int) (request()->query('year_id', session('year_id', 7)));
    $expenseConn = session('expense_connection', \App\Helpers\DatabaseSwitcher::getExpenseConnection());
    $table = 'y' . $yearId . '_monthexpensefinal';
    $record = \Illuminate\Support\Facades\DB::connection($expenseConn)
        ->table($table)
        ->where('EmployeeID', $employeeId)
        ->where('Month', $month)
        ->where('YearId', $yearId)
        ->select('id')
        ->first();

    if ($record) {
        return response()->json(['success' => true, 'id' => $record->id]);
    }
    return response()->json(['success' => false, 'id' => null]);
});

// Central Notification API Routes
Route::prefix('notifications')->group(function () {
    Route::get('pending/{employeeCode}', [CentralNotificationController::class, 'pending']);
    Route::get('counts/{employeeCode}', [CentralNotificationController::class, 'counts']);
    Route::get('history/{employeeCode}', [CentralNotificationController::class, 'history']);
    Route::post('raise', [CentralNotificationController::class, 'raise']);
    Route::post('approve', [CentralNotificationController::class, 'approve']);
    Route::post('reject', [CentralNotificationController::class, 'reject']);
    Route::post('cancel', [CentralNotificationController::class, 'cancel']);
    Route::post('forward', [CentralNotificationController::class, 'forward']);

    // Resigned Employees Final Expense Submission Reminder API
    Route::prefix('resigned-submission')->group(function () {
        Route::get('employees', [\App\Http\Controllers\admin\ResignedSubmissionEmailController::class, 'getEmployees']);
        Route::get('preview', [\App\Http\Controllers\admin\ResignedSubmissionEmailController::class, 'preview']);
        Route::post('send', [\App\Http\Controllers\admin\ResignedSubmissionEmailController::class, 'send']);
        Route::get('logs', [\App\Http\Controllers\admin\ResignedSubmissionEmailController::class, 'getLogs']);
        Route::get('logs/{id}', [\App\Http\Controllers\admin\ResignedSubmissionEmailController::class, 'getLogDetail']);
        Route::post('template', [\App\Http\Controllers\admin\ResignedSubmissionEmailController::class, 'updateTemplate']);
        Route::post('settings', [\App\Http\Controllers\admin\ResignedSubmissionEmailController::class, 'updateSettings']);
        Route::get('docs/download', [\App\Http\Controllers\admin\ResignedSubmissionEmailController::class, 'downloadDocs']);
    });
});

// Employee Mediator & Eligibility API Routes
Route::prefix('employee-mediator')->group(function () {
    Route::get('info/{employeeId}', [EmployeeController::class, 'getMediatorEligibility']);
    Route::post('assign/{employeeId}', [EmployeeController::class, 'assignMediator']);
});

// Open Month Expense Final, Employee Details & Claim Details API
Route::get('open-month-expense-details', [OpenMonthExpenseDetailsApiController::class, 'getOpenMonthDetails']);
Route::get('employee/{employeeId}/open-month-expenses', [OpenMonthExpenseDetailsApiController::class, 'getOpenMonthDetails']);

// Holded Employees Expense Details & DA Amounts API (/expense-payments?chkval=6&v=0)
Route::get('expense-payments/holded-details', [\App\Http\Controllers\Api\HoldedExpensePaymentApiController::class, 'getHoldedExpenseDetails']);
Route::get('employee/{employeeId}/holded-expense-details', [\App\Http\Controllers\Api\HoldedExpensePaymentApiController::class, 'getHoldedExpenseDetails']);
Route::get('employee/{employeeId}/holded-expenses', [\App\Http\Controllers\Api\HoldedExpensePaymentApiController::class, 'getHoldedExpenseDetails']);

