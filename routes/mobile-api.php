<?php

/**
 * Mobile API Routes
 * 
 * These routes replicate the legacy PHP API (api_expense) endpoints
 * for the mobile application. Kept isolated from the main web routes.
 * 
 * Base prefix: /mobile-api
 */

use App\Http\Controllers\Api\Mobile\LoginController;
use App\Http\Controllers\Api\Mobile\CommonController;
use App\Http\Controllers\Api\Mobile\ClaimController;
use App\Http\Controllers\Api\Mobile\OdometerController;
use App\Http\Controllers\Api\Mobile\TeamController;
use App\Http\Controllers\Api\Mobile\AdvanceController;
use App\Http\Controllers\Api\Mobile\ExtractionController;
use App\Http\Controllers\Api\Mobile\AppVersionController;
use App\Http\Controllers\Api\OpenMonthExpenseDetailsApiController;
use Illuminate\Support\Facades\Route;

// ─── Authentication ───────────────────────────────────────────────────────────
Route::post('login', [LoginController::class, 'login'])->middleware('throttle:20,1'); // LV-S02

// ─── App Version / Status ─────────────────────────────────────────────────────
Route::get('version', [AppVersionController::class, 'version']);
Route::get('ios-update-request', [AppVersionController::class, 'iosUpdateRequest']);
Route::get('get-data-runkm', [AppVersionController::class, 'getDataRunKm']);
Route::post('update-image-data', [AppVersionController::class, 'updateImageData']);

// ─── Common / Lookup ──────────────────────────────────────────────────────────
Route::prefix('common')->group(function () {
    Route::get('current-status', [CommonController::class, 'currentStatus']);
    Route::get('company-list', [CommonController::class, 'companyList']);
    Route::get('year-list', [CommonController::class, 'yearList']);
    Route::get('help-manual', [CommonController::class, 'helpManual']);
    Route::get('claim-types', [CommonController::class, 'daClaimTypes']);
    Route::get('2-4-wheeler-checkbox', [CommonController::class, 'wheelCheckbox']);
    Route::post('add-remark', [CommonController::class, 'addRemark']);
});

// ─── Claims (Employee) ────────────────────────────────────────────────────────
Route::prefix('claims')->group(function () {
    Route::get('draft-list', [ClaimController::class, 'draftList']);
    Route::get('open-list', [ClaimController::class, 'openList']);
    Route::get('open-list-day', [ClaimController::class, 'openListDay']);
    Route::get('open-month-details', [OpenMonthExpenseDetailsApiController::class, 'getOpenMonthDetails']);
    Route::get('close-list', [ClaimController::class, 'closeList']);
    Route::get('close-list-day', [ClaimController::class, 'closeListDay']);
    Route::get('close-list-month', [ClaimController::class, 'closeListMonth']);
    Route::get('close-list-all-months', [ClaimController::class, 'closeListAllMonths']);
    Route::get('detail', [ClaimController::class, 'expenseDetail']);
    Route::get('vehicle-running', [ClaimController::class, 'vehicleRunning']);
    Route::get('check-rate', [ClaimController::class, 'checkRate']);

    Route::post('da-update', [ClaimController::class, 'daUpdate']);
    Route::post('upload-image', [ClaimController::class, 'uploadImage']);
    Route::post('upload-bill', [ClaimController::class, 'uploadBill']);
    Route::post('okay', [ClaimController::class, 'claimOkay']);
    Route::post('deny', [ClaimController::class, 'claimDeny']);
    Route::post('delete', [ClaimController::class, 'claimDeleted']);
    Route::post('okay-all', [ClaimController::class, 'claimOkayAll']);
    Route::post('submit', [ClaimController::class, 'claimSubmit']);
    Route::post('courier-submit', [ClaimController::class, 'courierSubmit']);
    Route::post('approved', [ClaimController::class, 'claimApproved']);
    Route::post('reporting-submit', [ClaimController::class, 'reportingClaimSubmit']);
});

// ─── Odometer ─────────────────────────────────────────────────────────────────
Route::prefix('odometer')->group(function () {
    Route::get('current-date-details', [OdometerController::class, 'currentDateOdoDetails']);
    Route::post('upload-opening', [OdometerController::class, 'uploadOdoOpening']);
    Route::post('upload-closing', [OdometerController::class, 'uploadOdoClosing']);

    // Approver odometer operations
    Route::get('pending-approve', [OdometerController::class, 'pendingOdoApprove']);
    Route::post('approve-reading', [OdometerController::class, 'approveOdoReading']);
    Route::post('reject-reading', [OdometerController::class, 'rejectOdoReading']);
    Route::post('approve-all', [OdometerController::class, 'approveAllOdoReading']);
    Route::post('reject-all', [OdometerController::class, 'rejectAllOdoReading']);
    Route::post('final-submit', [OdometerController::class, 'approveFinalSubmit']);
    Route::get('show-hide-pending', [OdometerController::class, 'showHidePending']);
});

// ─── Team / Approver ──────────────────────────────────────────────────────────
Route::prefix('team')->group(function () {
    Route::get('members', [TeamController::class, 'teamMembers']);
    Route::get('members-appraiser', [TeamController::class, 'teamMembersAppraiser']);
    Route::get('members-appraiser-advance', [TeamController::class, 'teamMembersAppraiser']);
    Route::get('all-expenses', [TeamController::class, 'allTeamExpenses']);
    Route::get('running-km', [TeamController::class, 'teamRunningKm']);
    Route::get('member-expenses', [TeamController::class, 'teamMemberExpenses']);
    Route::get('member-expenses-by-id', [TeamController::class, 'teamMemberExpensesById']);
    Route::get('expenses-by-id-member', [TeamController::class, 'teamExpensesByIdAndMember']);
    Route::get('last-odometer', [TeamController::class, 'teamLastOdo']);

    Route::get('pending-claims', [TeamController::class, 'teamPendingClaimList']);
    Route::get('pending-claims-day', [TeamController::class, 'teamPendingClaimListDay']);
    Route::get('approved-claims', [TeamController::class, 'teamApprovedClaimList']);
    Route::get('approved-claims-day', [TeamController::class, 'teamApprovedClaimListDay']);
    Route::get('approved-by-month', [TeamController::class, 'approvedClaimsByMonth']);
    Route::get('approved-by-member', [TeamController::class, 'approvedClaimsByMember']);
    Route::get('approved-by-month-member', [TeamController::class, 'approvedClaimsByMonthMember']);

    Route::post('update-expense-okay-all', [TeamController::class, 'updateExpenseClaimsOkayAll']);
    Route::post('agree', [TeamController::class, 'agree']);
    Route::post('not-agree', [TeamController::class, 'notAgree']);
});

// ─── Extraction / OCR / Activity ──────────────────────────────────────────────
Route::prefix('extraction')->group(function () {
    Route::post('extract-data', [ExtractionController::class, 'extractData']);
    Route::post('extract-odo-data', [ExtractionController::class, 'extractOdoData']);
    Route::get('show-activity-tab', [ExtractionController::class, 'showActivityTab']);
    Route::get('claim-list', [ExtractionController::class, 'claimList']);
    Route::get('activity-claim-list', [ExtractionController::class, 'activityClaimList']);
    Route::get('adv-activity-claim-list', [ExtractionController::class, 'advActivityClaimList']);
    Route::get('activity-type', [ExtractionController::class, 'activityType']);
    Route::get('additional-fields', [ExtractionController::class, 'getAdditionalFields']);
    Route::get('varieties', [ExtractionController::class, 'getVarieties']);
    Route::post('upload-activity-bill', [ExtractionController::class, 'uploadActivityBill']);
    Route::get('remove-activity-bill-file', [ExtractionController::class, 'removeActivityBillFile']);
    Route::post('cron-move-data', [ExtractionController::class, 'cronMoveData']);
    Route::post('autopunch-data', [ExtractionController::class, 'autopunchData']);
});

// ─── Advance Request (Legacy) ─────────────────────────────────────────────────
Route::prefix('advance-legacy')->group(function () {
    Route::get('list', [AdvanceController::class, 'list']);
    Route::get('approver-list', [AdvanceController::class, 'approverList']);
    Route::get('approver2-list', [AdvanceController::class, 'approver2List']);
    Route::get('check-roles', [AdvanceController::class, 'checkRoles']);
    Route::post('add', [AdvanceController::class, 'addAdvance']);
    Route::post('action', [AdvanceController::class, 'action']);
});


// ═══════════════════════════════════════════════════════════════════════════════
// LEGACY-COMPATIBLE ROUTES
// These match the EXACT URL pattern the mobile app currently uses.
// Mobile app just needs to change base URL from:
//   https://expense.vnrseeds.co.in/api_expense/
// to:
//   https://your-domain.com/mobile-api/
//
// Then calls like:
//   /mobile-api/common.php?value=Draftlist&empid=123&YearId=7&ComId=1&tokenid=xxx
//   /mobile-api/login.php?value=login&empcode=123&emppass=xxx&...
//   /mobile-api/claim.php?value=DA_Update&...
// will work identically.
// ═══════════════════════════════════════════════════════════════════════════════

use App\Http\Controllers\Api\Mobile\LegacyProxyController;

// Match the exact legacy file names so mobile app URL change is minimal
Route::match(['get', 'post'], 'common.php', [LegacyProxyController::class, 'common']);
Route::match(['get', 'post'], 'login.php', [LegacyProxyController::class, 'login'])->middleware('throttle:20,1');
Route::match(['get', 'post'], 'login_new.php', [LegacyProxyController::class, 'login'])->middleware('throttle:20,1');
Route::match(['get', 'post'], 'claim.php', [LegacyProxyController::class, 'claim']);
Route::match(['get', 'post'], 'extract-data.php', [ExtractionController::class, 'handleLegacy']);
Route::match(['get', 'post'], 'appversion.php', [AppVersionController::class, 'version']);
Route::match(['get', 'post'], 'advance_request.php', [App\Http\Controllers\Api\Mobile\AdvanceRequestLegacyController::class, 'handleLegacy']);

// Flutter app also uses /{comId}/ in URL path (e.g., api/1/common.php, api/3/common.php)
Route::match(['get', 'post'], '{comId}/common.php', [LegacyProxyController::class, 'common'])->where('comId', '[0-9]+');
Route::match(['get', 'post'], '{comId}/claim.php', [LegacyProxyController::class, 'claim'])->where('comId', '[0-9]+');
Route::match(['get', 'post'], '{comId}/extract-data.php', [ExtractionController::class, 'handleLegacy'])->where('comId', '[0-9]+');
Route::match(['get', 'post'], '{comId}/advance_request.php', [App\Http\Controllers\Api\Mobile\AdvanceRequestLegacyController::class, 'handleLegacy'])->where('comId', '[0-9]+');
Route::match(['get', 'post'], '{comId}/login_new.php', [LegacyProxyController::class, 'login'])->where('comId', '[0-9]+');
Route::match(['get', 'post'], '{comId}/appversion.php', [AppVersionController::class, 'version'])->where('comId', '[0-9]+');
// APP-VVNR-01 (12 Nov 2026): the app's VVNR approver screens call {base}/api/11/api/common_rep.php and claim_rep.php
// (the old VVNR API path). Same handlers; the company comes from ComId in the request.
Route::match(['get', 'post'], '{comId}/api/common_rep.php', [LegacyProxyController::class, 'common'])->where('comId', '[0-9]+');
Route::match(['get', 'post'], '{comId}/api/claim_rep.php', [LegacyProxyController::class, 'claim'])->where('comId', '[0-9]+');

// PDF generation for mobile app print feature
// Maps legacy pdfexpense.php & printdetailsemp.php parameters to PrintDetailsEmpController format
// PRINT-01 (7 Nov 2026): the statement of employee e may be printed by e, by someone above e in the
// reporting chain or who approved e's claims, or by staff. Before, any login could print anyone's.
$printAccessDenied = function (\Illuminate\Http\Request $request) {
    $target = (int) $request->input('e', $request->input('empid', 0));
    if ($target <= 0) {
        return null;
    }
    $via = $request->attributes->get('api_auth_via');
    if ($via === 'mobile_token') {
        $self = (int) $request->attributes->get('api_auth_empid');
        $ok = $self === $target || in_array($self, \App\Support\RecordAccess::managersOf($target), true)
            || \App\Support\RecordAccess::approvedFor($self, $target);
    } elseif ($via === 'internal_key') {
        $ok = true;
    } else {
        $ok = auth()->check() && \App\Support\RecordAccess::canViewEmployee(auth()->user(), $target);
    }
    if ($ok) {
        return null;
    }
    $enforce = (bool) config('mobile_api_auth.enforce', true);
    \Illuminate\Support\Facades\Log::warning('Print of another employee refused', ['path' => $request->path(), 'target' => $target, 'via' => $via, 'enforced' => $enforce]);

    return $enforce ? response()->json(['success' => false, 'Code' => '403', 'msg' => 'Not allowed for this employee'], 403) : null;
};

$handlePrintDetails = function (\Illuminate\Http\Request $request, $comId = null) use ($printAccessDenied) {
    $request->merge([
        'e' => $request->input('empid', $request->input('e')),
        'y' => $request->input('YearId', $request->input('y')),
        'm' => $request->input('month', $request->input('m')),
        'nn' => $request->input('nn', 2),
        'c' => $comId ?? $request->input('ComId', $request->input('c', 1)),
    ]);
    if ($denied = $printAccessDenied($request)) {
        return $denied;
    }
    return app(\App\Http\Controllers\admin\PrintDetailsEmpController::class)->generate($request);
};

// APP-LEDGER-01 (13 Nov 2026): the app's Ledger screen opened the old PHP page accsummary.php, which the new
// server does not have ("Not Found" in the app). It is served here from the web account summary. Unlike the other
// legacy pages it always needs the app login (X-Xeasy-Empid / X-Xeasy-Token headers sent by the app 1.9.0+) and the
// viewer must be the employee, someone above the employee in the reporting chain, or their approver.
$handleAccSummary = function (\Illuminate\Http\Request $request, $comId = null) {
    $target = (int) $request->input('e', 0);
    $via = $request->attributes->get('api_auth_via');
    $self = (int) $request->attributes->get('api_auth_empid');
    $ok = $target > 0 && (
        ($via === 'mobile_token' && ($self === $target || in_array($self, \App\Support\RecordAccess::managersOf($target), true) || \App\Support\RecordAccess::approvedFor($self, $target)))
        || ($via === 'session' && auth()->check() && \App\Support\RecordAccess::canViewEmployee(auth()->user(), $target))
    );
    if (! $ok) {
        $msg = $via === null ? 'Please update the Xeasy app and log in again to see the ledger.' : 'You cannot see the ledger of this employee.';

        return response('<!doctype html><meta name="viewport" content="width=device-width,initial-scale=1"><body style="font-family:sans-serif;padding:24px;color:#374151"><h3>Ledger</h3><p>'.e($msg).'</p></body>', $via === null ? 401 : 403);
    }
    $company = (int) ($comId ?? $request->input('CompanyId', $request->input('c', 1))) ?: 1;
    session(['expense_connection' => \App\Helpers\DatabaseSwitcher::getExpenseConnection($company), 'company_id' => $company]);
    if ($request->filled('y')) {
        session(['year_id' => (int) $request->input('y')]);
    }

    return app(\App\Http\Controllers\AccountSummaryController::class)->index($request);
};
Route::get('accsummary.php', $handleAccSummary);
Route::get('{comId}/accsummary.php', $handleAccSummary)->where('comId', '[0-9]+');
// APP-PDF-01: the app's VVNR expense PDF is requested under api/11/api/
Route::match(['get', 'post'], '{comId}/api/pdfexpense.php', $handlePrintDetails)->where('comId', '[0-9]+');

Route::match(['get', 'post'], 'pdfexpense.php', $handlePrintDetails);
Route::match(['get', 'post'], 'printdetailsemp.php', $handlePrintDetails);
Route::match(['get', 'post'], '{comId}/pdfexpense.php', $handlePrintDetails)->where('comId', '[0-9]+');
Route::match(['get', 'post'], '{comId}/printdetailsemp.php', $handlePrintDetails)->where('comId', '[0-9]+');

// Odometer OCR/Background Update script: OdoMUpdate.php
$handleOdoMUpdate = function (\Illuminate\Http\Request $request, $comId = null) {
    $ci = (int) ($comId ?? $request->input('ci', 1));
    $act = $request->input('act');
    $expid = (int) $request->input('expid');
    $yi = (int) $request->input('yi');

    $conn = ($ci == 3) ? 'expense_nr' : (($ci == 11) ? 'vnressus_expense' : 'expense');

    if ($act === 'UpdateOdoReading' && $expid > 0 && $yi > 0) {
        $intReading = preg_replace('/[^0-9]/', '', (string) $request->input('OReading'));
        \Illuminate\Support\Facades\DB::connection($conn)->table("y{$yi}_expenseclaims")
            ->where('ExpId', $expid)
            ->update(['odomtr_opening' => $intReading]);
        return response('Success');
    }

    if ($act === 'UpdateOdoReading_Cls' && $expid > 0 && $yi > 0) {
        $intReading = preg_replace('/[^0-9]/', '', (string) $request->input('CReading'));
        $opeRd = (int) $request->input('OpeRd', 0);
        $totKm = 0;
        if ($opeRd > 0 && (int) $intReading > 0) {
            $totKm = (int) $intReading - $opeRd;
            if ($totKm > 600) { $totKm = 0; }
        }
        \Illuminate\Support\Facades\DB::connection($conn)->table("y{$yi}_expenseclaims")
            ->where('ExpId', $expid)
            ->update([
                'odomtr_closing' => $intReading,
                'TotKm' => $totKm,
                'FilledDate' => date('Y-m-d'),
            ]);

        $exists = \Illuminate\Support\Facades\DB::connection($conn)->table("y{$yi}_24_wheeler_entry")
            ->where('ExpId', $expid)
            ->exists();

        if ($exists) {
            \Illuminate\Support\Facades\DB::connection($conn)->table("y{$yi}_24_wheeler_entry")
                ->where('ExpId', $expid)
                ->update([
                    'DistTraOpen' => $opeRd,
                    'DistTraClose' => $intReading,
                    'Totalkm' => $totKm,
                ]);
        } else {
            \Illuminate\Support\Facades\DB::connection($conn)->table("y{$yi}_24_wheeler_entry")
                ->insert([
                    'ExpId' => $expid,
                    'DistTraOpen' => $opeRd,
                    'DistTraClose' => $intReading,
                    'Totalkm' => $totKm,
                ]);
        }
        return response('Success');
    }

    return response('Failed', 400);
};

Route::match(['get', 'post'], 'OdoMUpdate.php', $handleOdoMUpdate);
Route::match(['get', 'post'], '{comId}/OdoMUpdate.php', $handleOdoMUpdate)->where('comId', '[0-9]+');

// Print-details for the mobile app (login checked by MobileApiAuthenticate; employee checked by PRINT-01)
Route::get('print-details', function (\Illuminate\Http\Request $request) use ($printAccessDenied) {
    if ($denied = $printAccessDenied($request)) {
        return $denied;
    }
    return app(\App\Http\Controllers\admin\PrintDetailsEmpController::class)->generate($request);
});
