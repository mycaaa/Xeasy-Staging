<?php

namespace App\Http\Controllers\Api\Mobile;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use App\Helpers\CommonHelper;
use App\Services\ClaimRules\ClaimSubmissionValidator;
use App\Services\ClaimFiles\ClaimFileBatch;
use App\Services\ClaimFiles\ClaimFileException;

class ClaimActionsController extends BaseMobileController
{
    /**
     * Update DA (Daily Allowance) claim.
     * Replicates: claim.php?value=DA_Update
     */
    public function handleDAUpdate(Request $request): JsonResponse
    {
        $empId = (int) $request->input('empid');
        $yearId = $this->getYearId($request);
        $comId = $this->getComId($request);
        $billDate = $request->input('Billdate', '');
        $daType = $request->input('DAtype', '');
        $daAmt = $request->input('DAamt', '');
        $daRemark = $request->input('DArmk', '');
        $img1Offline = trim($request->input('Img1_offline', ''));
        $offlineExpId = trim($request->input('offline_exp_id', ''));

        if ($empId <= 0 || $yearId <= 0 || empty($billDate) || empty($daType) || empty($daAmt) || $comId <= 0) {
            return $this->error('Parameter Missing!');
        }

        if (!$this->validateToken($request)) {
            return $this->error('Invalid Employee!');
        }

        // Tracker #17: DOS needs a proof attachment (same rule as the web DA form).
        $ruleError = ClaimSubmissionValidator::first(app(ClaimSubmissionValidator::class)->validateDaRequest($request));
        if ($ruleError !== null) {
            return $this->error($ruleError);
        }

        $conn = $this->getExpenseConnection($comId);
        $hrimsConn = $this->getHrimsConnection();
        $claimsTable = $this->claimsTable($yearId);

        try {
            // Get eligibility for DA amount if BillDate < 2025-08-01
            if (strtotime($billDate) < strtotime('2025-08-01')) {
                $eligRows = DB::connection($hrimsConn)->select(
                    "SELECT EligibilityId, DA_Outside_Hq, DA_Inside_Hq FROM hrm_employee_eligibility WHERE EmployeeID = ? AND EligCreatedDate < '2025-08-01' ORDER BY EligibilityId DESC LIMIT 1",
                    [$empId]
                );

                if (!empty($eligRows)) {
                    $eligRow = $eligRows[0];
                    $daString = ($daType == 20) ? $eligRow->DA_Outside_Hq : $eligRow->DA_Inside_Hq;
                    if (preg_match('/Rs\.?\s*([\d.]+)/i', $daString, $matches)) {
                        $daAmt = (float) $matches[1];
                    } else {
                        $daAmt = 0;
                    }
                } else {
                    $daAmt = 0;
                }
            }

            // Check if claim already exists for this date (before the proof is stored, so a
            // rejected duplicate leaves no orphan file).
            $formattedDate = (\App\Services\ClaimRules\ClaimSubmissionValidator::parseDate($billDate) ?? date('Y-m-d', strtotime($billDate))); // BILLDATE-03 day-first
            $existing = DB::connection($conn)->select(
                "SELECT * FROM {$claimsTable} WHERE ClaimId IN(19, 20) AND BillDate=? AND ClaimStatus!='Deactivate' AND FilledBy=?",
                [$formattedDate, $empId]
            );

            if (!empty($existing)) {
                return $this->error('Already claim in the same date!');
            }

            // Handle file upload for DA type 19 or 20 (UAT R-01: stored first; the claim is
            // written only if the store succeeded, and the file is deleted if the write fails).
            $fileName = '';
            $files = new ClaimFileBatch();
            if ($daType == 19 || $daType == 20) {
                if ($request->hasFile('upload_img')) {
                    $file = \Illuminate\Support\Arr::first(array_filter(\Illuminate\Support\Arr::wrap($request->file('upload_img'))));
                    $mili = sprintf('%03d', (microtime(true) - floor(microtime(true))) * 1000);
                    $generatedName = sprintf('DA_%s_%s%s.jpg', $empId, date('dmyHis'), $mili);
                    try {
                        $fileName = $files->putMobile($file, $yearId, $empId, $generatedName, 'upload_img');
                    } catch (ClaimFileException $e) {
                        return $this->error($e->getMessage());
                    }
                }
            }
            // #17 DOS proof again, now counting only the proof actually stored.
            $storedRuleError = ClaimSubmissionValidator::first(app(ClaimSubmissionValidator::class)->validateDaRequest($request, $files->count()));
            if ($storedRuleError !== null) {
                $files->discard();
                return $this->error($storedRuleError);
            }

            return $files->commitWith($conn, function () use ($conn, $claimsTable, $daType, $formattedDate, $daRemark, $empId, $daAmt, $yearId, $fileName, $img1Offline, $offlineExpId, $billDate) {

                // Insert claim
                $billMonth = date('m', strtotime($billDate));
                $today = date('Y-m-d');

                DB::connection($conn)->statement(
                    "INSERT INTO {$claimsTable} (`ClaimId`, `BillDate`, `Remark`, `DateEntryRemark`, `ClaimStatus`, `ClaimAtStep`, `ClaimMonth`, `FilledBy`, `FilledOkay`, `FilledTAmt`, `FilledDate`, `CrBy`, `CrDate`, `ClaimYearId`, `Img_1`, `Img1_offline`, `offline_exp_id`) VALUES (?, ?, ?, ?, 'Filled', 2, ?, ?, 1, ?, ?, ?, ?, ?, ?, ?, ?)",
                    [$daType, $formattedDate, $daRemark, $daRemark, $billMonth, $empId, $daAmt, $today, $empId, $today, $yearId, $fileName, $img1Offline, $offlineExpId]
                );

                // Get the inserted ID
                $lastIdResult = DB::connection($conn)->select("SELECT LAST_INSERT_ID() as id");
                $expId = $lastIdResult[0]->id;

                // Insert fill data
                DB::connection($conn)->statement(
                    "INSERT INTO y{$yearId}_g1_expensefilldata (`ExpId`, `BillDate`) VALUES (?, ?)",
                    [$expId, $formattedDate]
                );

                // Insert claim details
                DB::connection($conn)->statement(
                    "INSERT INTO y{$yearId}_expenseclaimsdetails (`ExpId`, `Title`, `Amount`, `Remark`) VALUES (?, 'Manual amount', ?, ?)",
                    [$expId, $daAmt, $daRemark]
                );

                // Insert into claimuploads if file was uploaded
                if (($daType == 19 || $daType == 20) && $fileName) {
                    DB::connection($conn)->statement(
                        "INSERT INTO y{$yearId}_claimuploads (`ExpId`, `FileName`, `UploadSequence`) VALUES (?, ?, '1')",
                        [$expId, $fileName]
                    );
                }

                // Check/create monthexpensefinal record
                $monthCheck = DB::connection($conn)->select(
                    "SELECT * FROM y{$yearId}_monthexpensefinal WHERE EmployeeID=? AND Month=? AND YearId=? AND Status='Open'",
                    [$empId, $billMonth, $yearId]
                );

                if (empty($monthCheck)) {
                    DB::connection($conn)->statement(
                        "INSERT IGNORE INTO y{$yearId}_monthexpensefinal (`EmployeeID`, `Month`, `YearId`, `Status`, `Crdate`) VALUES (?, ?, ?, 'Open', ?)",
                        [$empId, $billMonth, $yearId, $today]
                    );
                }

                return response()->json(['Code' => '300', 'msg' => 'Data Found', 'DAClaim_type' => (string) $daType]);
            });
        } catch (\Throwable $e) {
            Log::error('ClaimActionsController@handleDAUpdate error: ' . $e->getMessage());
            return $this->error('Error: ' . $e->getMessage());
        }
    }

    /**
     * Mark claim as Okay (employee confirms filled amount).
     * Replicates: claim.php?value=Claim_Okay
     */
    public function handleClaimOkay(Request $request): JsonResponse
    {
        $empId = (int) $request->input('empid');
        $yearId = $this->getYearId($request);
        $comId = $this->getComId($request);
        $expId = (int) $request->input('ExpId');
        $remark = $request->input('remark', '');

        if ($empId <= 0 || $yearId <= 0 || $comId <= 0 || $expId <= 0) {
            return $this->error('Parameter Missing!');
        }

        if (!$this->validateToken($request)) {
            return $this->error('Invalid Employee!');
        }

        $conn = $this->getExpenseConnection($comId);
        $claimsTable = $this->claimsTable($yearId);

        try {
            // Check expense exists
            $expCheck = DB::connection($conn)->select(
                "SELECT * FROM {$claimsTable} WHERE ExpId=?",
                [$expId]
            );

            if (empty($expCheck)) {
                return $this->error('Invalid Expense Id!');
            }

            // Update FilledOkay
            $updated = DB::connection($conn)->statement(
                "UPDATE {$claimsTable} SET FilledOkay=1, FilOkDenyRemark=? WHERE ExpId=?",
                [$remark, $expId]
            );

            if (!$updated) {
                return $this->error('Error!');
            }

            // Get ClaimMonth for TotVerifyAmt calculation
            $monthResult = DB::connection($conn)->select(
                "SELECT ClaimMonth FROM {$claimsTable} WHERE ExpId=?",
                [$expId]
            );
            $claimMonth = $monthResult[0]->ClaimMonth;

            // Get TotVerifyAmt
            $totResult = DB::connection($conn)->select(
                "SELECT COALESCE(SUM(CASE WHEN FilledTAmt != '' THEN FilledTAmt END), 0) as TotVerifyAmt FROM {$claimsTable} WHERE ClaimYearId=? AND CrBy=? AND ClaimMonth=? AND FilledOkay=1 AND ClaimStatus!='Deactivate' AND ClaimStatus!='Draft'",
                [$yearId, $empId, $claimMonth]
            );
            $totVerifyAmt = $totResult[0]->TotVerifyAmt;

            return response()->json([
                'Code' => '300',
                'msg' => 'Data updated successfully',
                'FilledOkay' => '1',
                'TotVerifyAmt' => (string) $totVerifyAmt,
            ]);
        } catch (\Exception $e) {
            Log::error('ClaimActionsController@handleClaimOkay error: ' . $e->getMessage());
            return $this->error('Error: ' . $e->getMessage());
        }
    }

    /**
     * Deny a claim (employee denies filled amount).
     * Replicates: claim.php?value=Claim_Deny
     */
    public function handleClaimDeny(Request $request): JsonResponse
    {
        $empId = (int) $request->input('empid');
        $yearId = $this->getYearId($request);
        $comId = $this->getComId($request);
        $expId = (int) $request->input('ExpId');
        $remark = $request->input('remark', '');

        if ($empId <= 0 || $yearId <= 0 || $expId <= 0 || $comId <= 0) {
            return $this->error('Parameter Missing!');
        }

        if (!$this->validateToken($request)) {
            return $this->error('Invalid Employee!');
        }

        $conn = $this->getExpenseConnection($comId);
        $claimsTable = $this->claimsTable($yearId);

        try {
            // Check expense exists and get ClaimMonth, CrBy
            $expCheck = DB::connection($conn)->select(
                "SELECT * FROM {$claimsTable} WHERE ExpId=?",
                [$expId]
            );

            if (empty($expCheck)) {
                return $this->error('Invalid Expense Id!');
            }

            $claimMonth = $expCheck[0]->ClaimMonth;
            $crBy = $expCheck[0]->CrBy;

            // Update FilledOkay to 2 (denied)
            $updated = DB::connection($conn)->statement(
                "UPDATE {$claimsTable} SET FilledOkay = 2, FilOkDenyRemark = ? WHERE ExpId = ?",
                [$remark, $expId]
            );

            if (!$updated) {
                return $this->error('Error!');
            }

            // Count total denied
            $countResult = DB::connection($conn)->select(
                "SELECT COUNT(*) as count FROM {$claimsTable} WHERE ClaimYearId=? AND ClaimMonth=? AND FilledOkay='2' AND CrBy=?",
                [$yearId, $claimMonth, $crBy]
            );
            $totalDeny = $countResult[0]->count;

            return response()->json([
                'Code' => '300',
                'msg' => 'Data Updated successfully',
                'TotalDeny' => (string) $totalDeny,
            ]);
        } catch (\Exception $e) {
            Log::error('ClaimActionsController@handleClaimDeny error: ' . $e->getMessage());
            return $this->error('Error: ' . $e->getMessage());
        }
    }

    /**
     * Delete (deactivate) a claim.
     * Replicates: claim.php?value=Claim_Deleted
     */
    public function handleClaimDeleted(Request $request): JsonResponse
    {
        $empId = (int) $request->input('empid');
        $yearId = $this->getYearId($request);
        $comId = $this->getComId($request);
        $expId = (int) $request->input('ExpId');

        if ($empId <= 0 || $yearId <= 0 || $expId <= 0 || $comId <= 0) {
            return $this->error('Parameter Missing!');
        }

        if (!$this->validateToken($request)) {
            return $this->error('Invalid Employee!');
        }

        $conn = $this->getExpenseConnection($comId);
        $claimsTable = $this->claimsTable($yearId);

        try {
            // Check expense exists
            $expCheck = DB::connection($conn)->select(
                "SELECT * FROM {$claimsTable} WHERE ExpId=?",
                [$expId]
            );

            if (empty($expCheck)) {
                return $this->error('Invalid Expense Id!');
            }

            \App\Services\Claims\ClaimTransition::guard($conn, (int) $yearId, (int) $expId, 'Deactivate', 'claimant', 'app_claim_deleted', null, 'app', (int) $empId, (int) $comId);
            // Deactivate claim
            $updated = DB::connection($conn)->statement(
                "UPDATE {$claimsTable} SET ClaimStatus='Deactivate' WHERE ExpId=?",
                [$expId]
            );

            if (!$updated) {
                return $this->error('Error!');
            }

            return response()->json(['Code' => '300', 'code' => '300', 'msg' => 'Data Updated successfully']);
        } catch (\Exception $e) {
            Log::error('ClaimActionsController@handleClaimDeleted error: ' . $e->getMessage());
            return $this->error('Error: ' . $e->getMessage());
        }
    }

    /**
     * Approve a claim (by approver).
     * Replicates: claim.php?value=Claim_approved
     */
    public function handleClaimApproved(Request $request): JsonResponse
    {
        $empId = (int) $request->input('empid');
        $yearId = $this->getYearId($request);
        $comId = $this->getComId($request);
        $expId = (int) $request->input('ExpId');
        $month = (int) $request->input('Month');
        $employeeId = (int) $request->input('EmployeeId');
        $amt = $request->input('Amt', '');
        $apprRemark = $request->input('ApprRemark', '');

        if ($empId <= 0 || $yearId <= 0 || $comId <= 0 || $expId <= 0 || $month <= 0 || $employeeId <= 0 || $amt === '') {
            return $this->error('Parameter Missing!');
        }

        if (!$this->validateToken($request)) {
            return $this->error('Invalid Employee!');
        }

        $conn = $this->getExpenseConnection($comId);
        $claimsTable = $this->claimsTable($yearId);

        try {
            // Check expense exists
            $expCheck = DB::connection($conn)->select(
                "SELECT * FROM {$claimsTable} WHERE ExpId=?",
                [$expId]
            );

            if (empty($expCheck)) {
                return $this->error('Invalid Expense Id!');
            }

            // APPR-LOCK-01: the approver may change their own amount until the month's final submit, not after
            if ($lockMsg = $this->approverLocked($conn, (int) $yearId, $expCheck[0])) {
                return $this->error($lockMsg);
            }
            // Q-02: approved above verified (or claimed) needs a reason in the remark
            if ($capMsg = \App\Services\Claims\StageCap::check($conn, (int) $yearId, $expCheck[0], $amt, 'approve', (string) $apprRemark, 'app_claim_approved')) {
                return $this->error($capMsg);
            }
            // PAY-REMARK-01: a cut needs the reason too (VNR)
            if ($cutMsg = \App\Services\Claims\StageCap::checkCut($conn, (int) $yearId, $expCheck[0], $amt, (string) $apprRemark, 'app_claim_approved', (int) $comId)) {
                return $this->error($cutMsg);
            }
            // B-01: the caller must be the claimant's approver
            \App\Services\Claims\ClaimTransition::guard($conn, (int) $yearId, (int) $expId, 'Approved', 'approver', 'app_claim_approved', $apprRemark ?: null, 'app', (int) $empId, (int) $comId);
            // Update claim with approved amount
            $updated = DB::connection($conn)->statement(
                "UPDATE {$claimsTable} SET ClaimStatus='Approved', ClaimAtStep=4, ApprBy=?, ApprTAmt=?, ApprTRemark=?, ApprDate=NOW(), FinancedTAmt=? WHERE ExpId=?",
                [$empId, $amt, $apprRemark, $amt, $expId]
            );

            if (!$updated) {
                return $this->error('Error!');
            }

            // Get total approved amount
            $totalResult = DB::connection($conn)->select(
                "SELECT SUM(CASE WHEN ApprBy > 0 AND ApprBy IS NOT NULL AND ApprDate IS NOT NULL AND ApprDate != '' THEN ApprTAmt ELSE 0 END) AS TotalApprovedAmt FROM {$claimsTable} WHERE ClaimMonth = ? AND ClaimYearId = ? AND CrBy = ? AND FilledOkay = 1 AND ClaimStatus != 'Deactivate' AND ClaimStatus != 'Draft'",
                [$month, $yearId, $employeeId]
            );
            $totalApprovedAmt = $totalResult[0]->TotalApprovedAmt ?? 0;

            return response()->json([
                'Code' => '300',
                'msg' => 'Approved Successfully',
                'Amount' => (string) $amt,
                'Remark' => (string) ($apprRemark ?? ''),
                'AppDate' => date('Y-m-d'),
                'Claim_Status' => 'Approved',
                'TotalApprovedAmt' => (string) $totalApprovedAmt,
            ]);
        } catch (\Exception $e) {
            Log::error('ClaimActionsController@handleClaimApproved error: ' . $e->getMessage());
            return $this->error('Error: ' . $e->getMessage());
        }
    }

    /**
     * ACT-01: head-wise approval of an activity claim (claim.php?value=Act_Claim_approved).
     * Body: JSON list of {label_value, app_amount, app_remark}. Each head's approved amount
     * goes to app_<head> / app_remark_<head> in y*_g7_expensefilldata, and the claim's
     * ApprTAmt is the sum. This value was routed to handleClaimApproved(), which expects a
     * single Amt and so could not approve activity claims head-wise.
     */
    public function handleActClaimApproved(Request $request): JsonResponse
    {
        $empId = (int) $request->input('empid');
        $yearId = $this->getYearId($request);
        $comId = $this->getComId($request);
        $expId = (int) $request->input('ExpId');
        $apprRemark = (string) $request->input('ApprRemark', '');
        $heads = $request->json()->all();
        if (! is_array($heads) || $heads === [] || ! array_is_list($heads)) {
            $heads = (array) $request->input('head_amounts', []);
        }
        if ($empId <= 0 || $yearId <= 0 || $expId <= 0 || $heads === []) {
            return $this->error('Parameter Missing!');
        }
        if (! $this->validateToken($request)) {
            return $this->error('Invalid Employee!');
        }

        $conn = $this->getExpenseConnection($comId);
        $claimsTable = $this->claimsTable($yearId);
        $g7Table = "y{$yearId}_g7_expensefilldata";
        try {
            $claim = DB::connection($conn)->table($claimsTable)->where('ExpId', $expId)->first(['ExpId', 'CrBy', 'ClaimMonth', 'VerifyTAmt', 'FilledTAmt', 'ClaimAtStep']);
            if (! $claim) {
                return $this->error('Invalid Expense Id!');
            }
            // APPR-LOCK-01: the approver may change their own amount until the month's final submit, not after
            if ($lockMsg = $this->approverLocked($conn, (int) $yearId, $claim)) {
                return $this->error($lockMsg);
            }
            $columns = array_flip(\Illuminate\Support\Facades\Schema::connection($conn)->getColumnListing($g7Table));
            $g7Update = [];
            $total = 0.0;
            foreach ($heads as $h) {
                $label = preg_replace('/[^a-z0-9_]/', '', strtolower((string) ($h['label_value'] ?? '')));
                $amount = round((float) ($h['app_amount'] ?? 0), 2);
                if ($label === '' || $amount < 0) {
                    continue;
                }
                $total += $amount;
                if (isset($columns["app_{$label}"])) {
                    $g7Update["app_{$label}"] = $amount;
                }
                if (isset($columns["app_remark_{$label}"])) {
                    $g7Update["app_remark_{$label}"] = mb_substr((string) ($h['app_remark'] ?? ''), 0, 250);
                }
            }
            $total = (int) round($total);

            // Q-02: approved above verified (or claimed) needs a reason in the remark
            if ($capMsg = \App\Services\Claims\StageCap::check($conn, (int) $yearId, $claim, $total, 'approve', $apprRemark, 'app_activity_approved')) {
                return $this->error($capMsg);
            }
            if ($cutMsg = \App\Services\Claims\StageCap::checkCut($conn, (int) $yearId, $claim, $total, (string) $apprRemark, 'app_activity_approved', (int) $comId)) {
                return $this->error($cutMsg);
            }
            \App\Services\Claims\ClaimTransition::guard($conn, (int) $yearId, (int) $expId, 'Approved', 'approver', 'app_activity_approved', $apprRemark ?: null, 'app', (int) $empId, (int) $comId);
            DB::connection($conn)->transaction(function () use ($conn, $claimsTable, $g7Table, $expId, $empId, $total, $apprRemark, $g7Update) {
                if ($g7Update) {
                    DB::connection($conn)->table($g7Table)->where('ExpId', $expId)->update($g7Update);
                }
                DB::connection($conn)->update(
                    "UPDATE {$claimsTable} SET ClaimStatus='Approved', ClaimAtStep=4, ApprBy=?, ApprTAmt=?, ApprTRemark=?, ApprDate=NOW(), FinancedTAmt=? WHERE ExpId=?",
                    [$empId, $total, $apprRemark, $total, $expId]
                );
            });

            return response()->json([
                'Code' => '300',
                'msg' => 'Approved Successfully',
                'Amount' => (string) $total,
                'Remark' => $apprRemark,
                'AppDate' => date('Y-m-d'),
                'Claim_Status' => 'Approved',
            ]);
        } catch (\Exception $e) {
            Log::error('ClaimActionsController@handleActClaimApproved error: ' . $e->getMessage());
            return $this->error('Error: ' . $e->getMessage());
        }
    }

    /**
     * Submit claims for reporting (approver final submit).
     * Replicates: claim.php?value=Reporting_Claim_Submit
     */
    public function handleReportingClaimSubmit(Request $request): JsonResponse
    {
        $empId = (int) $request->input('empid');
        $yearId = $this->getYearId($request);
        $comId = $this->getComId($request);
        $crBy = (int) $request->input('CrBy');
        $month = (int) $request->input('Month');

        if ($empId <= 0 || $yearId <= 0 || $comId <= 0 || $crBy <= 0 || $month <= 0) {
            return $this->error('Parameter Missing!');
        }

        if (!$this->validateToken($request)) {
            return $this->error('Invalid Employee!');
        }

        $conn = $this->getExpenseConnection($comId);
        $claimsTable = $this->claimsTable($yearId);
        $monthFinalTable = $this->monthFinalTable($yearId);

        try {
            \App\Services\Claims\ClaimTransition::guard($conn, (int) $yearId, fn ($q) => $q->where('CrBy', $crBy)->where('ClaimMonth', $month)->where('ClaimYearId', $yearId)->whereIn('ClaimStatus', ['Verified', 'Approved']), 'Approved', 'approver', 'app_reporting_submit', null, 'app', (int) $empId, (int) $comId);
            // Update all verified/approved claims to Approved with ClaimAtStep=5
            DB::connection($conn)->statement(
                // audit B-02 (10 Nov 2026): month approval carries the verifier's amount into ApprTAmt (submit had pre-filled it with the claimed amount, so cuts were lost); set before ClaimStatus in the same UPDATE
                "UPDATE {$claimsTable} SET ApprTAmt=IF(ClaimStatus='Verified' AND VerifyTAmt>0 AND (ApprTAmt IS NULL OR ApprTAmt=0 OR ApprTAmt=FilledTAmt), VerifyTAmt, ApprTAmt), ClaimAtStep=5, ClaimStatus='Approved', ApprBy=?, ApprDate=NOW() WHERE CrBy=? AND ClaimMonth=? AND ClaimYearId=? AND (ClaimStatus='Verified' OR ClaimStatus='Approved')",
                [$empId, $crBy, $month, $yearId]
            );

            // Get sum of approved amounts
            $apprResult = DB::connection($conn)->select(
                "SELECT SUM(ApprTAmt) as ApprAmt FROM {$claimsTable} WHERE ClaimYearId=? AND CrBy=? AND ClaimMonth=? AND FilledOkay=1 AND ClaimStatus!='Deactivate' AND ClaimStatus!='Draft' AND ApprBy>0",
                [$yearId, $crBy, $month]
            );
            $approvedAmount = $apprResult[0]->ApprAmt ?? 0;

            // Update monthexpensefinal
            DB::connection($conn)->statement(
                "UPDATE {$monthFinalTable} SET Approved_Amount=?, Approved_Date=NOW() WHERE EmployeeID=? AND Month=? AND YearId=?",
                [$approvedAmount, $crBy, $month, $yearId]
            );

            return response()->json(['Code' => '300', 'code' => '300', 'msg' => 'Data submitted successfully']);
        } catch (\Exception $e) {
            Log::error('ClaimActionsController@handleReportingClaimSubmit error: ' . $e->getMessage());
            return $this->error('Error: ' . $e->getMessage());
        }
    }

    /**
     * Upload bill - creates expense claim with all form fields and file uploads.
     * Replicates: claim.php?value=upload_bill (the FULL logic)
     */
    public function handleUploadBill(Request $request): JsonResponse
    {
        $empId = (int) $request->input('empid');
        $yearId = $this->getYearId($request);
        $comId = $this->getComId($request);
        $expId = (int) $request->input('expid', 0);
        $billDate = $request->input('Billdate', '');
        $amount = (float) ($request->input('amount') ?: 0); // OCR-OPTIONAL-01: no empty strings into number columns
        $remark = $request->input('remark') ?? '';
        $claimId = (int) $request->input('claim_id', 0);
        $tempPunchId = (int) ($request->input('temp_punch_id') ?: 0); // empty when the bill was not read automatically

        if ($empId <= 0 || $yearId <= 0 || $comId <= 0) {
            return $this->error('Parameter Missing!');
        }

        if (!$this->validateToken($request)) {
            return $this->error('Invalid Employee!');
        }

        // Tracker rules (receipt, ticket category, participants): reject before anything is written.
        $ruleError = ClaimSubmissionValidator::first(app(ClaimSubmissionValidator::class)->validateBillRequest($request));
        if ($ruleError !== null) {
            return $this->error($ruleError);
        }

        $conn = $this->getExpenseConnection($comId);
        $claimsTable = $this->claimsTable($yearId);

        // Date validation
        $formattedDate = (\App\Services\ClaimRules\ClaimSubmissionValidator::parseDate($billDate) ?? date('Y-m-d', strtotime($billDate))); // BILLDATE-03 day-first
        $billMonth = date('m', strtotime($billDate));
        $today = date('Y-m-d');

        // Get offline fields
        $img1Offline = trim($request->input('Img1_offline', ''));
        $img2Offline = trim($request->input('Img2_offline', ''));
        $img3Offline = trim($request->input('Img3_offline', ''));
        $pdf1Offline = trim($request->input('Pdf1_offline', ''));
        $pdf2Offline = trim($request->input('Pdf2_offline', ''));
        $offlineExpId = trim($request->input('offline_exp_id', ''));

        // Check for duplicate offline claims (read-only; before any file or row is written).
        if (!empty($offlineExpId)) {
            $checkDup = DB::connection($conn)->select(
                "SELECT ExpId FROM {$claimsTable} WHERE offline_exp_id = ? AND CrBy = ? AND ClaimId = ? AND BillDate = ? LIMIT 1",
                [$offlineExpId, $empId, $claimId, $formattedDate]
            );
            if (!empty($checkDup)) {
                return response()->json(['Code' => '200', 'msg' => 'Offline claim already exists', 'ExpId' => $checkDup[0]->ExpId]);
            }
        }

        // UAT R-01: store every file first. If one cannot be stored, delete the ones
        // already stored and reject the claim; nothing is written to the DB.
        $files = new ClaimFileBatch();
        $uploadRows = []; // [FileName, UploadSequence, Img_n column slot or null]
        // Unique per request: names used to be {prefix}_{emp}_{dmyHis}.ext, so two head-wise files of
        // one field, or two saves in the same second, got the same name and overwrote each other.
        $stamp = date('dmyHis') . ClaimFileBatch::uniqueSuffix();
        $headCount = [];
        try {
            $imageCount = 0;
            if ($request->hasFile('upload_img')) {
                foreach (array_filter(\Illuminate\Support\Arr::wrap($request->file('upload_img'))) as $image) {
                    $imageCount++;
                    $ext = ($image instanceof \Illuminate\Http\UploadedFile ? $image->getClientOriginalExtension() : '') ?: 'jpg';
                    $fileName = sprintf('Img_%s_%s_%d.%s', $empId, $stamp, $imageCount, $ext);
                    $files->putMobile($image, $yearId, $empId, $fileName, 'upload_img');
                    $uploadRows[] = [$fileName, $imageCount, $imageCount <= 3 ? $imageCount : null];
                }
            }

            $pdfCount = 3;
            if ($request->hasFile('upload_pdf')) {
                foreach (array_filter(\Illuminate\Support\Arr::wrap($request->file('upload_pdf'))) as $pdf) {
                    $pdfCount++;
                    $fileName = sprintf('Pdf_%s_%s_%d.pdf', $empId, $stamp, $pdfCount);
                    $files->putMobile($pdf, $yearId, $empId, $fileName, 'upload_pdf');
                    $uploadRows[] = [$fileName, $pdfCount, $pdfCount <= 5 ? $pdfCount : null];
                }
            }

            // Head-wise file uploads (Meeting_Hall, Lodging, etc.)
            $headWiseFiles = ['File_Meeting_Hall', 'File_Lodging', 'File_Meals', 'File_Business_Entertainment', 'File_Others', 'File_Toll_Pass', 'File_Toll_Tax', 'File_International_Recharge', 'File_Printing_Stationery', ClaimSubmissionValidator::RECEIPT_FILE_FIELD];
            $uploadSequenceMap = ['File_Meeting_Hall' => 101, 'File_Lodging' => 102, 'File_Meals' => 103, 'File_Business_Entertainment' => 104, 'File_Others' => 105, 'File_Toll_Pass' => 106, 'File_Toll_Tax' => 107, 'File_International_Recharge' => 108, 'File_Printing_Stationery' => 109, ClaimSubmissionValidator::RECEIPT_FILE_FIELD => ClaimSubmissionValidator::RECEIPT_UPLOAD_SEQUENCE];
            foreach ($headWiseFiles as $fileKey) {
                if ($request->hasFile($fileKey)) {
                    foreach (array_filter(\Illuminate\Support\Arr::wrap($request->file($fileKey))) as $f) {
                        $ext = ($f instanceof \Illuminate\Http\UploadedFile ? $f->getClientOriginalExtension() : '') ?: 'jpg';
                        $headCount[$fileKey] = ($headCount[$fileKey] ?? 0) + 1;
                        $fileName = sprintf('%s_%s_%s_%d.%s', $fileKey, $empId, $stamp, $headCount[$fileKey], $ext);
                        $files->putMobile($f, $yearId, $empId, $fileName, $fileKey);
                        $uploadRows[] = [$fileName, $uploadSequenceMap[$fileKey] ?? 999, null];
                    }
                }
            }

            // #16 receipt rule again, now against the files actually stored.
            $storedRuleError = ClaimSubmissionValidator::first(app(ClaimSubmissionValidator::class)->validateBillRequest($request, $files->countsByField()));
            if ($storedRuleError !== null) {
                throw new ClaimFileException($storedRuleError);
            }
        } catch (ClaimFileException $e) {
            $files->discard();
            return $this->error($e->getMessage());
        }

        // Every DB write of this claim runs in one transaction; on failure the stored files are deleted.
        $db = DB::connection($conn);
        $db->beginTransaction();
        try {
            // If editing (expid > 0), deactivate old record
            if ($expId > 0) {
                \App\Services\Claims\ClaimTransition::guard($conn, (int) $yearId, fn ($q) => $q->where('ExpId', (int) $expId)->where('ClaimStatus', '!=', 'Deactivate'), 'Deactivate', 'claimant', 'app_bill_reupload', null, 'app', (int) $empId, (int) $comId);
                DB::connection($conn)->statement(
                    "UPDATE {$claimsTable} SET ClaimStatus = 'Deactivate' WHERE ExpId = ?",
                    [$expId]
                );
            }

            // Determine ClaimStatus and ClaimAtStep based on claim_id
            $claimStatus = 'Submitted';
            $claimAtStep = 0;
            $filledBy = '';
            $filledDate = '';

            if (in_array($claimId, [16, 42, 18, 6])) {
                $claimStatus = 'Filled';
                $claimAtStep = 3;
                $filledBy = $empId;
                $filledDate = $today;
            }

            // Insert new expense claim
            if (!empty($filledBy)) {
                DB::connection($conn)->statement(
                    "INSERT INTO {$claimsTable} (CrBy, CrDate, Img_1, DateEntryRemark, ClaimStatus, ClaimAtStep, BillDate, ClaimMonth, Img1_offline, Img2_offline, Img3_offline, Pdf1_offline, Pdf2_offline, offline_exp_id, FilledTAmt, ClaimYearId, ClaimId, temp_punch_id, FilledBy, FilledDate) VALUES (?, ?, '', ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)",
                    [$empId, $today, $remark, $claimStatus, $claimAtStep, $formattedDate, $billMonth, $img1Offline, $img2Offline, $img3Offline, $pdf1Offline, $pdf2Offline, $offlineExpId, $amount, $yearId, $claimId, $tempPunchId, $filledBy, $filledDate]
                );
            } else {
                DB::connection($conn)->statement(
                    "INSERT INTO {$claimsTable} (CrBy, CrDate, Img_1, DateEntryRemark, ClaimStatus, BillDate, ClaimMonth, Img1_offline, Img2_offline, Img3_offline, Pdf1_offline, Pdf2_offline, offline_exp_id, FilledTAmt, ClaimYearId, ClaimId, temp_punch_id) VALUES (?, ?, '', ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)",
                    [$empId, $today, $remark, $claimStatus, $formattedDate, $billMonth, $img1Offline, $img2Offline, $img3Offline, $pdf1Offline, $pdf2Offline, $offlineExpId, $amount, $yearId, $claimId, $tempPunchId]
                );
            }

            // Get inserted ID
            $lastIdResult = DB::connection($conn)->select("SELECT LAST_INSERT_ID() as id");
            $id = $lastIdResult[0]->id ?? 0;

            if (!$id) {
                throw new ClaimFileException('Error inserting claim!');
            }

            // Upload rows for the files stored above (UAT R-01: only stored files get a row).
            foreach ($uploadRows as [$fileName, $sequence, $slot]) {
                DB::connection($conn)->statement(
                    "INSERT INTO y{$yearId}_claimuploads (ExpId, FileName, UploadSequence) VALUES (?, ?, ?)",
                    [$id, $fileName, $sequence]
                );
                if ($slot !== null) {
                    DB::connection($conn)->statement(
                        "UPDATE {$claimsTable} SET Img_{$slot} = ? WHERE ExpId = ?",
                        [$fileName, $id]
                    );
                }
            }

            // Handle claim_id 42 (Business Entertainment) - insert into expensefilldata and details
            if ($claimId == 42) {
                $paxDetails = $request->input('PaxDetails', '');
                DB::connection($conn)->statement(
                    "INSERT INTO y{$yearId}_g1_expensefilldata (ExpId, PaxDetails, BillDate) VALUES (?, ?, ?)",
                    [$id, $paxDetails, $formattedDate]
                );

                $expenseData = [
                    'Meeting Hall' => ['amount' => $request->input('Meeting_Hall', '0'), 'remark' => $request->input('Remark_Meeting_Hall', '')],
                    'Lodging' => ['amount' => $request->input('Lodging', '0'), 'remark' => $request->input('Remark_Lodging', '')],
                    'Meals' => ['amount' => $request->input('Meals', '0'), 'remark' => $request->input('Remark_Meals', '')],
                    'Business Entertainment' => ['amount' => $request->input('Business_Entertainment', '0'), 'remark' => $request->input('Remark_Business_Entertainment', '')],
                    'Others' => ['amount' => $request->input('Others', '0'), 'remark' => $request->input('Remark_Others', '')],
                ];

                foreach ($expenseData as $title => $data) {
                    if ((float) $data['amount'] > 0) {
                        DB::connection($conn)->statement(
                            "INSERT INTO y{$yearId}_expenseclaimsdetails (ExpId, Title, Amount, Remark) VALUES (?, ?, ?, ?)",
                            [$id, $title, $data['amount'], $data['remark']]
                        );
                    }
                }
            }

            // Handle claim_id 16 (Miscellaneous) - insert details
            if ($claimId == 16) {
                $expenseData = [
                    'Toll Pass' => ['amount' => $request->input('Toll_Pass', '0'), 'remark' => $request->input('Remark_Toll_Pass', '')],
                    'Toll Tax' => ['amount' => $request->input('Toll_Tax', '0'), 'remark' => $request->input('Remark_Toll_Tax', '')],
                    'International Recharge' => ['amount' => $request->input('International_Recharge', '0'), 'remark' => $request->input('Remark_International_Recharge', '')],
                    'Printing Stationery' => ['amount' => $request->input('Printing_Stationery', '0'), 'remark' => $request->input('Remark_Printing_Stationery', '')],
                    'Others' => ['amount' => $request->input('Others', '0'), 'remark' => $request->input('Remark_Others', '')],
                ];

                foreach ($expenseData as $title => $data) {
                    if ((float) $data['amount'] > 0) {
                        DB::connection($conn)->statement(
                            "INSERT INTO y{$yearId}_expenseclaimsdetails (ExpId, Title, Amount, Remark) VALUES (?, ?, ?, ?)",
                            [$id, $title, $data['amount'], $data['remark']]
                        );
                    }
                }
            }

            // Handle claim_id 17 (Group Meal) - insert PaxDetails and participant info
            if ($claimId == 17) {
                $paxDetails = $request->input('PaxDetails', '');
                $groupMeal = $request->input('group_meal', '');
                $mealParticipants = $request->input('meal_participants', '');
                $mealOtherParticipants = $request->input('meal_other_participants', '');
                $normalizedOther = CommonHelper::normalizeOtherParticipantsJson($mealOtherParticipants);

                $participantCodes = '';
                if (!empty($mealParticipants)) {
                    if (is_array($mealParticipants)) {
                        $participantCodes = implode(',', array_filter($mealParticipants));
                    } elseif (is_string($mealParticipants)) {
                        $decoded = json_decode($mealParticipants, true);
                        if (is_array($decoded)) {
                            $participantCodes = implode(',', array_filter($decoded));
                        } else {
                            $participantCodes = trim($mealParticipants);
                        }
                    }
                }

                DB::connection($conn)->statement(
                    "INSERT INTO y{$yearId}_g1_expensefilldata (ExpId, NoOfPAX, PaxDetails, PaxDetails_Other, BillDate) VALUES (?, ?, ?, ?, ?)",
                    [$id, $paxDetails, $participantCodes, $normalizedOther, $formattedDate]
                );
            }

            // Handle claim_id 1 (Lodging) - insert PaxDetails and participant info
            if ($claimId == 1) {
                $paxDetails = $request->input('PaxDetails', '');
                $lodgingGroup = $request->input('lodging_group', '');
                $lodgingParticipants = $request->input('lodging_participants', '');
                $lodgingOtherParticipants = $request->input('lodging_other_participants', '');
                $normalizedOther = CommonHelper::normalizeOtherParticipantsJson($lodgingOtherParticipants);

                $participantCodes = '';
                if (!empty($lodgingParticipants)) {
                    if (is_array($lodgingParticipants)) {
                        $participantCodes = implode(',', array_filter($lodgingParticipants));
                    } elseif (is_string($lodgingParticipants)) {
                        $decoded = json_decode($lodgingParticipants, true);
                        if (is_array($decoded)) {
                            $participantCodes = implode(',', array_filter($decoded));
                        } else {
                            $participantCodes = trim($lodgingParticipants);
                        }
                    }
                }

                // Check if g1_expensefilldata record already exists for this ExpId
                $existingFillData = DB::connection($conn)->select(
                    "SELECT ExpId FROM y{$yearId}_g1_expensefilldata WHERE ExpId = ?",
                    [$id]
                );

                if (empty($existingFillData)) {
                    DB::connection($conn)->statement(
                        "INSERT INTO y{$yearId}_g1_expensefilldata (ExpId, NoOfPAX, PaxDetails, PaxDetails_Other, BillDate) VALUES (?, ?, ?, ?, ?)",
                        [$id, $paxDetails, $participantCodes, $normalizedOther, $formattedDate]
                    );
                } else {
                    DB::connection($conn)->statement(
                        "UPDATE y{$yearId}_g1_expensefilldata SET NoOfPAX = ?, PaxDetails = ?, PaxDetails_Other = ? WHERE ExpId = ?",
                        [$paxDetails, $participantCodes, $normalizedOther, $id]
                    );
                }
            }

            // Handle claim_id 14 (Rail Fare) - store ticket category
            if ($claimId == 14) {
                // Validated as mandatory above; store the canonical spelling.
                // APP-RAIL-01: the app sends the rail category as rail_quota (and ticket_category on older builds)
                $rawCategory = $request->input('rail_quota', $request->input('ticket_category', ''));
                $ticketCategory = app(ClaimSubmissionValidator::class)->normalizeTicketCategory($rawCategory)
                    ?? $rawCategory;

                if (!empty($ticketCategory)) {
                    // Check if g1_expensefilldata record already exists for this ExpId
                    $existingFillData = DB::connection($conn)->select(
                        "SELECT ExpId FROM y{$yearId}_g1_expensefilldata WHERE ExpId = ?",
                        [$id]
                    );

                    if (empty($existingFillData)) {
                        DB::connection($conn)->statement(
                            "INSERT INTO y{$yearId}_g1_expensefilldata (ExpId, Quota, BillDate) VALUES (?, ?, ?)",
                            [$id, $ticketCategory, $formattedDate]
                        );
                    } else {
                        DB::connection($conn)->statement(
                            "UPDATE y{$yearId}_g1_expensefilldata SET Quota = ? WHERE ExpId = ?",
                            [$ticketCategory, $id]
                        );
                    }
                }
            }

            // Handle extraction queue (temp_punch_id processing)
            if ($tempPunchId > 0 && $claimId > 0 && $id > 0) {
                $createdAt = date('Y-m-d H:i:s');
                DB::connection($conn)->statement(
                    "DELETE FROM tbl_extraction_queue WHERE expid = ? AND temp_punch_id = ?",
                    [$id, $tempPunchId]
                );
                DB::connection($conn)->statement(
                    "INSERT INTO tbl_extraction_queue (expid, temp_punch_id, claim_id, status, created_at, updated_at, created_by) VALUES (?, ?, ?, 'PENDING', ?, ?, ?)",
                    [$id, $tempPunchId, $claimId, $createdAt, $createdAt, $empId]
                );

                // Update temp_punch table
                try {
                    DB::connection($conn)->statement(
                        "UPDATE y{$yearId}_temp_punch_{$claimId} SET ExpId = ? WHERE id = ?",
                        [$id, $tempPunchId]
                    );
                } catch (\Exception $e) {
                    // Table might not exist for all claim types
                }

                // Trigger autopunch in background (non-blocking)
                try {
                    $lastQueueResult = DB::connection($conn)->select("SELECT LAST_INSERT_ID() as id");
                    $lastQueueId = $lastQueueResult[0]->id ?? 0;
                    // Fire and forget - the autopunch will process asynchronously
                } catch (\Exception $e) {
                    // Non-critical
                }
            }

            // Ensure monthexpensefinal exists
            $monthCheck = DB::connection($conn)->select(
                "SELECT * FROM y{$yearId}_monthexpensefinal WHERE EmployeeID=? AND Month=? AND YearId=?",
                [$empId, $billMonth, $yearId]
            );
            if (empty($monthCheck)) {
                DB::connection($conn)->statement(
                    "INSERT IGNORE INTO y{$yearId}_monthexpensefinal (EmployeeID, Month, YearId, Status, Crdate) VALUES (?, ?, ?, 'Open', ?)",
                    [$empId, $billMonth, $yearId, $today]
                );
            }

            $db->commit();
            $files->keep();

            return response()->json([
                'Code' => '300',
                'msg' => 'Data updated successfully',
                'ExpId' => (string) $id,
                'temp_punch_id' => (string) $tempPunchId,
                'claim_id' => (string) $claimId,
            ]);
        } catch (\Throwable $e) {
            $db->rollBack();
            $files->discard();
            \Illuminate\Support\Facades\Log::error('ClaimActionsController@handleUploadBill error: ' . $e->getMessage());
            return $this->error($e instanceof ClaimFileException ? $e->getMessage() : 'Error: ' . $e->getMessage());
        }
    }

    /**
     * Upload odometer opening image.
     * Replicates: claim.php?value=uploadimg_odo_opening
     */
    public function handleUploadOdoOpening(Request $request): JsonResponse
    {
        $empId = (int) $request->input('empid');
        $yearId = $this->getYearId($request);
        $comId = $this->getComId($request);
        $dateFor = $request->input('datefor', '');
        $vehicleType = $request->input('vehicleType', '');
        $remark = $request->input('remark', '');
        $odomtrOpening = $request->input('odomtr_opening', '');
        $tempPunchId = (int) ($request->input('temp_punch_id') ?: 0); // OCR-OPTIONAL-01
        $offlineOpeningFilepath = $request->input('offline_opening_filepath', '');
        $offlineExpId = $request->input('offline_exp_id', '');

        if ($empId <= 0 || $yearId <= 0 || $comId <= 0 || empty($dateFor)) {
            return $this->error('Parameter Missing!');
        }

        if (!$this->validateToken($request)) {
            return $this->error('Invalid Employee!');
        }

        if (!$request->hasFile('opening_img')) {
            return $this->error('Opening Image Missing!');
        }

        // ODO-FY-01 (13 Nov 2026): a back-dated odometer is accepted for any date of the claim's financial year up to
        // today (late entries still go through the back-date approval); another year or a future date is refused.
        if ($odoDateErr = \App\Services\ClaimRules\ClaimSubmissionValidator::first(app(\App\Services\ClaimRules\ClaimSubmissionValidator::class)->billDate($dateFor, (int) $yearId, $this->getExpenseConnection($comId)))) {
            return $this->error(str_replace('Bill date', 'Odometer date', $odoDateErr));
        }


        $conn = $this->getExpenseConnection($comId);
        $claimsTable = $this->claimsTable($yearId);
        $uploadsTable = $this->uploadsTable($yearId);

        try {
            $file = $request->file('opening_img');
            // DATE-KEEP-01: day first (15-05-2026 / 15/05/2026 / 2026-05-15); strtotime read 05/08 as 8 May
            $billDate = \App\Services\ClaimRules\ClaimSubmissionValidator::parseDate($dateFor) ?? date('Y-m-d', strtotime($dateFor));
            $billMonth = date('m', strtotime($billDate));

            // Upload image to S3 (UAT R-01: through a batch, so a failed DB write deletes it again)
            $mili = sprintf('%03d', (microtime(true) - floor(microtime(true))) * 1000);
            $img = sprintf('OpnImg_%s_%s%s.jpg', $empId, date('dmyHis'), $mili);

            $files = new ClaimFileBatch();
            try {
                $files->putMobile($file, $yearId, $empId, $img, 'opening_img');
            } catch (ClaimFileException $e) {
                return $this->error($e->getMessage());
            }

            // ODO-DUP-01: one upload per employee + date at a time, so a double tap / retry updates instead of inserting twice
            return \App\Services\Claims\DuplicateOdometer::withLock($conn, (int) $yearId, $empId, $billDate, fn () => $files->commitWith($conn, function () use ($conn, $claimsTable, $uploadsTable, $billDate, $billMonth, $empId, $yearId, $img, $odomtrOpening, $tempPunchId, $offlineOpeningFilepath, $offlineExpId, $vehicleType, $remark, $request) {

                // Check if claim exists for this date
                $existing = DB::connection($conn)->select(
                    "SELECT * FROM {$claimsTable} WHERE ClaimId=7 AND BillDate=? AND CrBy=? AND ClaimStatus != 'Deactivate'",
                    [$billDate, $empId]
                );

                if (!empty($existing)) {
                    // B-01: an odometer re-upload may not reopen a claim already verified / approved / paid
                    \App\Services\Claims\ClaimTransition::guard($conn, (int) $yearId, fn ($q) => $q->where('ClaimId', 7)->where('BillDate', $billDate)->where('CrBy', $empId)->where('ClaimStatus', '!=', 'Deactivate'), 'Submitted', 'claimant', 'app_odo_opening', null, 'app', (int) $empId, (int) $comId);
                    // UPDATE existing record
                    DB::connection($conn)->statement(
                        "UPDATE {$claimsTable} SET opening_filepath=?, odomtr_opening=?, ClaimStatus='Submitted', temp_punch_id=?, offline_opening_filepath=?, offline_exp_id=?, VType=?, WType=?, DateEntryRemark=? WHERE ClaimId=7 AND BillDate=? AND CrBy=? AND ClaimStatus != 'Deactivate'",
                        [$img, $odomtrOpening, $tempPunchId, $offlineOpeningFilepath, $offlineExpId, $vehicleType, $vehicleType, $remark, $billDate, $empId]
                    );
                } else {
                    // INSERT new record
                    DB::connection($conn)->statement(
                        "INSERT INTO {$claimsTable} (ClaimId, CrBy, CrDate, BillDate, temp_punch_id, opening_filepath, odomtr_opening, VType, WType, DateEntryRemark, ClaimMonth, ClaimYearId, FilledBy, FilledDate, offline_opening_filepath, offline_exp_id, ClaimStatus) VALUES (7, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'Submitted')",
                        [$empId, date('Y-m-d'), $billDate, $tempPunchId, $img, $odomtrOpening, $vehicleType, $vehicleType, $remark, $billMonth, $yearId, $empId, $billDate, $offlineOpeningFilepath, $offlineExpId]
                    );
                }

                // Get the ExpId
                $expIdResult = DB::connection($conn)->select(
                    "SELECT ExpId FROM {$claimsTable} WHERE ClaimId=7 AND BillDate=? AND CrBy=? AND ClaimStatus != 'Deactivate'",
                    [$billDate, $empId]
                );
                $id = !empty($expIdResult) ? $expIdResult[0]->ExpId : 0;
                \App\Services\Claims\OdometerGps::save($conn, $claimsTable, (int) $id, $request, ['opening']); // GPS-ODO-01

                // Insert into claimuploads
                if ($id > 0) {
                    DB::connection($conn)->statement(
                        "INSERT INTO {$uploadsTable} (ExpId, FileName, UploadSequence) VALUES (?, ?, 1)",
                        [$id, $img]
                    );
                }

                // Check/create monthexpensefinal
                $monthCheck = DB::connection($conn)->select(
                    "SELECT * FROM y{$yearId}_monthexpensefinal WHERE EmployeeID=? AND Month=? AND YearId=? AND Status='Open'",
                    [$empId, $billMonth, $yearId]
                );

                if (empty($monthCheck)) {
                    DB::connection($conn)->statement(
                        "INSERT IGNORE INTO y{$yearId}_monthexpensefinal (`EmployeeID`, `Month`, `YearId`, `Status`, `Crdate`) VALUES (?, ?, ?, 'Open', ?)",
                        [$empId, $billMonth, $yearId, date('Y-m-d')]
                    );
                }

                $odoWarn = \App\Services\Claims\OdometerContinuity::flag($conn, (int) $yearId, (int) $id); // M-02
                return response()->json(['Code' => '300', 'msg' => 'Data updated successfully'] + ($odoWarn ? ['odo_continuity_warning' => $odoWarn] : []));
            }));
        } catch (\Throwable $e) {
            Log::error('ClaimActionsController@handleUploadOdoOpening error: ' . $e->getMessage());
            return $this->error('Error: ' . $e->getMessage());
        }
    }

    /**
     * Upload odometer closing image.
     * Replicates: claim.php?value=uploadimg_odo_closing
     */
    public function handleUploadOdoClosing(Request $request): JsonResponse
    {
        $empId = (int) $request->input('empid');
        $yearId = $this->getYearId($request);
        $comId = $this->getComId($request);
        $dateFor = $request->input('datefor', '');
        $vehicleType = $request->input('vehicleType', '');
        $odomtrOpening = $request->input('odomtr_opening', '');
        $odomtrClosing = $request->input('odomtr_closing', '');
        $totKm = $request->input('TotKm', '');
        $offlineClosingFilepath = $request->input('offline_clossing_filepath', '');
        $offlineOpeningFilepath = $request->input('offline_opening_filepath', '');
        $offlineExpId = $request->input('offline_exp_id', '');
        $tempPunchId = (int) ($request->input('temp_punch_id') ?: 0); // OCR-OPTIONAL-01
        $remark = $request->input('remark', '');

        if ($empId <= 0 || $yearId <= 0 || $comId <= 0 || empty($dateFor)) {
            return $this->error('Parameter Missing!');
        }

        if (!$this->validateToken($request)) {
            return $this->error('Invalid Employee!');
        }

        if (!$request->hasFile('closing_img')) {
            return $this->error('Closing Image Missing!');
        }

        // ODO-FY-01: same date rule as the opening reading
        if ($odoDateErr = \App\Services\ClaimRules\ClaimSubmissionValidator::first(app(\App\Services\ClaimRules\ClaimSubmissionValidator::class)->billDate($dateFor, (int) $yearId, $this->getExpenseConnection($comId)))) {
            return $this->error(str_replace('Bill date', 'Odometer date', $odoDateErr));
        }

        $conn = $this->getExpenseConnection($comId);
        $claimsTable = $this->claimsTable($yearId);
        $uploadsTable = $this->uploadsTable($yearId);

        try {
            // DATE-KEEP-01: day first (15-05-2026 / 15/05/2026 / 2026-05-15); strtotime read 05/08 as 8 May
            $billDate = \App\Services\ClaimRules\ClaimSubmissionValidator::parseDate($dateFor) ?? date('Y-m-d', strtotime($dateFor));
            $billMonth = date('m', strtotime($billDate));

            // UAT R-01: opening (optional) and closing images are stored all-or-nothing; a
            // failed opening image is no longer skipped silently.
            $files = new ClaimFileBatch();
            $openingFilepath = '';
            try {
                if ($request->hasFile('opening_img')) {
                    $mili = sprintf('%03d', (microtime(true) - floor(microtime(true))) * 1000);
                    $openingFileName = sprintf('OpnImg_%s_%s%s.jpg', $empId, date('dmyHis'), $mili);
                    $openingFilepath = $files->putMobile($request->file('opening_img'), $yearId, $empId, $openingFileName, 'opening_img');
                }

                // Upload closing image
                $mili = sprintf('%03d', (microtime(true) - floor(microtime(true))) * 1000);
                $closingFileName = sprintf('ClsImg_%s_%s%s.jpg', $empId, date('dmyHis'), $mili);
                $files->putMobile($request->file('closing_img'), $yearId, $empId, $closingFileName, 'closing_img');
            } catch (ClaimFileException $e) {
                $files->discard();
                return $this->error($e->getMessage());
            }

            // ODO-DUP-01: see opening upload
            return \App\Services\Claims\DuplicateOdometer::withLock($conn, (int) $yearId, $empId, $billDate, fn () => $files->commitWith($conn, function () use ($conn, $claimsTable, $uploadsTable, $billDate, $billMonth, $empId, $yearId, $openingFilepath, $closingFileName, $tempPunchId, $odomtrOpening, $odomtrClosing, $totKm, $vehicleType, $offlineClosingFilepath, $offlineOpeningFilepath, $offlineExpId, $remark, $comId, $request) {

                // Find existing claim
                $existing = DB::connection($conn)->select(
                    "SELECT ExpId FROM {$claimsTable} WHERE ClaimId=7 AND BillDate=? AND CrBy=? AND ClaimStatus != 'Deactivate'",
                    [$billDate, $empId]
                );

                $lastInsertId = 0;

                if (!empty($existing)) {
                    $lastInsertId = $existing[0]->ExpId;
                    \App\Services\Claims\ClaimTransition::guard($conn, (int) $yearId, fn ($q) => $q->where('ClaimId', 7)->where('BillDate', $billDate)->where('CrBy', $empId)->where('ClaimStatus', '!=', 'Deactivate'), 'Submitted', 'claimant', 'app_odo_closing', null, 'app', (int) $empId, (int) $comId);

                    // UPDATE with closing data
                    $updateSql = "UPDATE {$claimsTable} SET closing_filepath=?, CrDate=?, temp_punch_id=?, odomtr_opening=?, odomtr_closing=?, TotKm=?, VType=?, WType=?, offline_clossing_filepath=?, offline_exp_id=?, ClaimStatus='Submitted', DateEntryRemark=?";
                    $updateParams = [$closingFileName, date('Y-m-d'), $tempPunchId, $odomtrOpening, $odomtrClosing, $totKm, $vehicleType, $vehicleType, $offlineClosingFilepath, $offlineExpId, $remark];

                    if ($offlineOpeningFilepath != '') {
                        $updateSql .= ", offline_opening_filepath=?";
                        $updateParams[] = $offlineOpeningFilepath;
                    }
                    if ($openingFilepath != '') {
                        $updateSql .= ", opening_filepath=?";
                        $updateParams[] = $openingFilepath;
                    }

                    $updateSql .= " WHERE ClaimId=7 AND BillDate=? AND CrBy=? AND ClaimStatus != 'Deactivate'";
                    $updateParams[] = $billDate;
                    $updateParams[] = $empId;

                    DB::connection($conn)->statement($updateSql, $updateParams);
                } else {
                    // INSERT new record
                    $insertSql = "INSERT INTO {$claimsTable} (ClaimId, CrBy, CrDate, BillDate, ClaimMonth, ClaimYearId, opening_filepath, closing_filepath, odomtr_opening, odomtr_closing, VType, WType, DateEntryRemark, FilledBy, FilledDate, offline_clossing_filepath, offline_exp_id, TotKm, temp_punch_id, ClaimStatus";
                    $insertValues = "VALUES (7, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'Submitted'";
                    $insertParams = [$empId, date('Y-m-d'), $billDate, $billMonth, $yearId, $openingFilepath, $closingFileName, $odomtrOpening, $odomtrClosing, $vehicleType, $vehicleType, $remark, $empId, $billDate, $offlineClosingFilepath, $offlineExpId, $totKm, $tempPunchId];

                    if ($offlineOpeningFilepath != '') {
                        $insertSql .= ", offline_opening_filepath";
                        $insertValues .= ", ?";
                        $insertParams[] = $offlineOpeningFilepath;
                    }

                    $insertSql .= ") " . $insertValues . ")";
                    DB::connection($conn)->statement($insertSql, $insertParams);

                    $lastIdResult = DB::connection($conn)->select("SELECT LAST_INSERT_ID() as id");
                    $lastInsertId = $lastIdResult[0]->id;
                }

                // GPS-ODO-01: closing point, and the opening point when both photos come in one call
                \App\Services\Claims\OdometerGps::save($conn, $claimsTable, (int) $lastInsertId, $request, $openingFilepath != '' ? ['closing', 'opening'] : ['closing']);

                // Insert into claimuploads with sequence 2 for closing
                if ($lastInsertId > 0) {
                    if ($openingFilepath != '') {
                        DB::connection($conn)->statement(
                            "INSERT INTO {$uploadsTable} (ExpId, FileName, UploadSequence) VALUES (?, ?, 1)",
                            [$lastInsertId, $openingFilepath]
                        );
                    }
                    DB::connection($conn)->statement(
                        "INSERT INTO {$uploadsTable} (ExpId, FileName, UploadSequence) VALUES (?, ?, 2)",
                        [$lastInsertId, $closingFileName]
                    );
                }

                // Check/create monthexpensefinal
                $monthCheck = DB::connection($conn)->select(
                    "SELECT * FROM y{$yearId}_monthexpensefinal WHERE EmployeeID=? AND Month=? AND YearId=? AND (Status='Open' OR Status='Closed')",
                    [$empId, $billMonth, $yearId]
                );

                if (empty($monthCheck)) {
                    DB::connection($conn)->statement(
                        "INSERT IGNORE INTO y{$yearId}_monthexpensefinal (`EmployeeID`, `Month`, `YearId`, `Status`, `Crdate`) VALUES (?, ?, ?, 'Open', ?)",
                        [$empId, $billMonth, $yearId, date('Y-m-d')]
                    );
                }

                $odoWarn = \App\Services\Claims\OdometerContinuity::flag($conn, (int) $yearId, (int) $lastInsertId); // M-02
                return response()->json(['Code' => '300', 'msg' => 'Data updated successfully'] + ($odoWarn ? ['odo_continuity_warning' => $odoWarn] : []));
            }));
        } catch (\Throwable $e) {
            Log::error('ClaimActionsController@handleUploadOdoClosing error: ' . $e->getMessage());
            return $this->error('Error: ' . $e->getMessage());
        }
    }

    /**
     * APPR-LOCK-01 (13 Nov 2026): once the approver has done the final submit of the month
     * (monthexpensefinal.Approved_Date set, claims at step 5 or later), the approved amounts are fixed.
     */
    private function approverLocked($conn, int $yearId, object $claim): ?string
    {
        if ((int) ($claim->ClaimAtStep ?? 0) >= 5) {
            return 'The month is already submitted by the approver; approved amounts cannot be changed now.';
        }
        $submitted = DB::connection($conn)->table("y{$yearId}_monthexpensefinal")
            ->where('EmployeeID', (int) $claim->CrBy)->where('Month', (int) $claim->ClaimMonth)->where('YearId', $yearId)
            ->whereNotNull('Approved_Date')->where('Approved_Date', '>', '1970-01-01')
            ->exists();

        return $submitted ? 'The month is already submitted by the approver; approved amounts cannot be changed now.' : null;
    }
}
