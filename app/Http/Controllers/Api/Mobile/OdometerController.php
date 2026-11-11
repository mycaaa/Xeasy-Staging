<?php

namespace App\Http\Controllers\Api\Mobile;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class OdometerController extends BaseMobileController
{
    /**
     * Get current date odometer details.
     * Replicates: common.php?value=Current_date_odo_details
     */
    public function currentDateOdoDetails(Request $request): JsonResponse
    {
        $empId = (int) $request->input('empid');
        $date = $request->input('date', '');
        $yearId = $this->getYearId($request);
        $comId = $this->getComId($request);

        if ($empId <= 0 || empty($date) || $yearId <= 0 || $comId <= 0) {
            return $this->error('Parameter Missing!');
        }

        if (!$this->validateToken($request)) {
            return $this->error('Invalid Employee!');
        }

        $hrimsConn = $this->getHrimsConnection();
        $expenseConn = $this->getExpenseConnection($comId);

        // Check driving license expiry
        $dlCheck = DB::connection($hrimsConn)
            ->table('hrm_employee_personal')
            ->where('EmployeeID', $empId)
            ->select('Driv_ExpiryDateTo', 'DrivingLicNo_YN')
            ->first();

        $dlExpired = 'N';
        $dlMessage = 'Driving license is valid.';

        if ($dlCheck && $dlCheck->DrivingLicNo_YN === 'Y' && date('Y-m-d') > date('Y-m-d', strtotime($dlCheck->Driv_ExpiryDateTo))) {
            $dlExpired = 'Y';
            $dlMessage = 'Your driving license has expired. Please renew it and share the updated copy with HR to upload your odometer reading.';
        }

        // Get today's odometer claims
        $odoClaims = DB::connection($expenseConn)
            ->table($this->claimsTable($yearId))
            ->where('CrBy', $empId)
            ->where('BillDate', $date)
            ->where('ClaimId', 7) // Odometer/Travel claim type
            ->where('ClaimStatus', '!=', 'Deactivate')
            ->where('ClaimStatus', '!=', 'Draft')
            ->orderBy('ExpId')
            ->get();

        // Get last odometer reading (previous day or last entry)
        $lastOdo = DB::connection($expenseConn)
            ->table($this->claimsTable($yearId))
            ->where('CrBy', $empId)
            ->where('BillDate', '<', $date)
            ->where('ClaimId', 7)
            ->where('ClaimStatus', '!=', 'Deactivate')
            ->where('ClaimStatus', '!=', 'Draft')
            ->where('odomtr_closing', '>', 0)
            ->orderByDesc('BillDate')
            ->orderByDesc('ExpId')
            ->select('odomtr_closing', 'BillDate')
            ->first();

        return $this->success([
            'DLExpired' => $dlExpired,
            'DLMessage' => $dlMessage,
            'odo_claims' => $odoClaims->toArray(),
            'last_odo' => $lastOdo ? [
                'closing_reading' => $lastOdo->odomtr_closing,
                'date' => $lastOdo->BillDate,
            ] : null,
        ]);
    }

    /**
     * Upload odometer opening image.
     * Replicates: claim.php?value=uploadimg_odo_opening
     */
    public function uploadOdoOpening(Request $request): JsonResponse
    {
        return $this->handleOdometerUpload($request, 'opening');
    }

    /**
     * Upload odometer closing image.
     * Replicates: claim.php?value=uploadimg_odo_closing
     */
    public function uploadOdoClosing(Request $request): JsonResponse
    {
        return $this->handleOdometerUpload($request, 'closing');
    }

    /**
     * Get pending odometer readings for approval.
     * Replicates: common.php?value=pending_odo_approve
     */
    public function pendingOdoApprove(Request $request): JsonResponse
    {
        $empId = (int) $request->input('empid');
        $yearId = $this->getYearId($request);
        $comId = $this->getComId($request);

        if ($empId <= 0 || $yearId <= 0 || $comId <= 0) {
            return $this->error('Parameter Missing!');
        }

        if (!$this->validateToken($request)) {
            return $this->error('Invalid Employee!');
        }

        $hrimsConn = $this->getHrimsConnection();
        $expenseConn = $this->getExpenseConnection($comId);

        // Get team members under this approver
        $teamMembers = DB::connection($hrimsConn)
            ->table('hrm_employee_general as eg')
            ->join('hrm_employee as e', 'eg.EmployeeID', '=', 'e.EmployeeID')
            ->where('eg.RepEmployeeID', $empId)
            ->where('e.EmpStatus', 'A')
            ->pluck('eg.EmployeeID')
            ->toArray();

        if (empty($teamMembers)) {
            return response()->json(['Code' => '200', 'code' => '200', 'data' => [], 'pending_odo' => [], 'msg' => 'No Data Available!']);
        }

        // Get pending odometer claims from team grouped for mobile app and web
        $pendingOdo = DB::connection($expenseConn)
            ->table($this->claimsTable($yearId) . ' as ec')
            ->join(DB::connection($hrimsConn)->getDatabaseName() . '.hrm_employee as e', 'ec.CrBy', '=', 'e.EmployeeID')
            ->leftJoin(DB::connection($hrimsConn)->getDatabaseName() . '.hrm_employee_general as eg', 'e.EmployeeID', '=', 'eg.EmployeeID')
            ->whereIn('ec.CrBy', $teamMembers)
            ->where('ec.ClaimId', 7)
            ->where('ec.ClaimStatus', '!=', 'Deactivate')
            ->where('ec.ClaimStatus', '!=', 'Draft')
            ->where('ec.odomtr_status', 'Pending')
            ->where('ec.ClaimYearId', $yearId)
            ->orderBy('ec.CrBy')
            ->orderBy('ec.ClaimMonth')
            ->orderBy('ec.BillDate')
            ->select([
                'ec.ExpId', 'ec.BillDate', 'ec.CrDate', 'ec.odomtr_opening', 'ec.odomtr_closing',
                'ec.TotKm', 'ec.CrBy', 'ec.ClaimMonth', 'ec.opening_filepath', 'ec.closing_filepath',
                'ec.odomtr_status', 'ec.ClaimStatus',
                DB::raw("CONCAT(e.Fname, ' ', COALESCE(e.Sname, ''), ' ', e.Lname) as FullName"),
                'e.EmpCode',
                DB::raw("CASE WHEN eg.TwoWheeler='Y' THEN '2 Wheeler' WHEN eg.FourWheeler='Y' THEN '4 Wheeler' ELSE '2 Wheeler' END as wheeler_type")
            ])
            ->get();

        $grouped = [];
        $runningTotals = [];

        foreach ($pendingOdo as $row) {
            $key = $row->CrBy . '_' . $row->ClaimMonth;
            if (!isset($grouped[$key])) {
                $grouped[$key] = [
                    'EmpId' => (string) $row->CrBy,
                    'FullName' => (string) $row->FullName,
                    'EmpCode' => (string) $row->EmpCode,
                    'ClaimMonth' => (string) $row->ClaimMonth,
                    'wheeler_type' => (string) $row->wheeler_type,
                    'total_run_km' => 0,
                    'items' => [],
                ];
                $runningTotals[$key] = 0;
            }

            $km = (int) ($row->TotKm ?? 0);
            $runningTotals[$key] += $km;

            $openingUrl = $this->resolveFileUrl($row->opening_filepath ?? '', $yearId, (int) $row->CrBy);
            $closingUrl = $this->resolveFileUrl($row->closing_filepath ?? '', $yearId, (int) $row->CrBy);

            $grouped[$key]['items'][] = [
                'ExpId' => (int) $row->ExpId,
                'BillDate' => (string) ($row->BillDate ?? ''),
                'CrDate' => (string) ($row->CrDate ?? ''),
                'TotKm' => $km,
                'CumulativeKm' => $runningTotals[$key],
                'odomtr_opening' => (int) ($row->odomtr_opening ?? 0),
                'odomtr_closing' => (int) ($row->odomtr_closing ?? 0),
                'opening_filepath' => $openingUrl,
                'closing_filepath' => $closingUrl,
                'Status' => (string) ($row->odomtr_status ?? 'Pending'),
            ];
            $grouped[$key]['total_run_km'] = $runningTotals[$key];
        }

        $dataList = array_values($grouped);

        return response()->json([
            'Code' => '200',
            'code' => '200',
            'data' => $dataList,
            'pending_odo' => $pendingOdo->toArray(),
            'msg' => 'Data Available!',
        ]);
    }

    /**
     * Approve odometer reading.
     * Replicates: common.php?value=approve_odo_reading
     */
    public function approveOdoReading(Request $request): JsonResponse
    {
        $empId = (int) $request->input('empid');
        $yearId = $this->getYearId($request);
        $comId = $this->getComId($request);
        $expId = (int) $request->input('ExpId');
        $split = $request->input('split', '');

        if ($empId <= 0 || $yearId <= 0 || $comId <= 0 || $expId <= 0 || empty($split)) {
            return $this->error('Parameter Missing!');
        }

        if (!$this->validateToken($request)) {
            return $this->error('Invalid Employee!');
        }

        $expenseConn = $this->getExpenseConnection($comId);

        DB::connection($expenseConn)
            ->table($this->claimsTable($yearId))
            ->where('ExpId', $expId)
            ->update([
                'odomtr_status' => 'Approved',
                'odomtr_approved_by' => $empId,
                'odomtr_approved_date' => now()->format('Y-m-d H:i:s'),
                'SplitJourney' => $split,
            ]);

        return response()->json(['Code' => '200', 'code' => '200', 'msg' => 'Odometer reading approved']);
    }

    /**
     * Reject odometer reading.
     * Replicates: common.php?value=reject_odo_reading
     */
    public function rejectOdoReading(Request $request): JsonResponse
    {
        $empId = (int) $request->input('empid');
        $yearId = $this->getYearId($request);
        $comId = $this->getComId($request);
        $expId = (int) $request->input('ExpId');

        if ($empId <= 0 || $yearId <= 0 || $comId <= 0 || $expId <= 0) {
            return $this->error('Parameter Missing!');
        }

        if (!$this->validateToken($request)) {
            return $this->error('Invalid Employee!');
        }

        $expenseConn = $this->getExpenseConnection($comId);

        DB::connection($expenseConn)
            ->table($this->claimsTable($yearId))
            ->where('ExpId', $expId)
            ->update([
                'odomtr_status' => 'Rejected',
                'odomtr_approved_by' => $empId,
                'odomtr_approved_date' => now()->format('Y-m-d H:i:s'),
            ]);

        return response()->json(['Code' => '200', 'code' => '200', 'msg' => 'Odometer reading rejected']);
    }

    /**
     * Approve all odometer readings for a team member in a month.
     * Replicates: common.php?value=approve_all_odo_reading
     */
    public function approveAllOdoReading(Request $request): JsonResponse
    {
        $empId = (int) $request->input('empid');
        $yearId = $this->getYearId($request);
        $comId = $this->getComId($request);
        $tmEmpId = (int) $request->input('tmempid');
        $month = (int) $request->input('month');

        if ($empId <= 0 || $yearId <= 0 || $comId <= 0 || $tmEmpId <= 0 || $month <= 0) {
            return $this->error('Parameter Missing!');
        }

        if (!$this->validateToken($request)) {
            return $this->error('Invalid Employee!');
        }

        $expenseConn = $this->getExpenseConnection($comId);

        $updated = DB::connection($expenseConn)
            ->table($this->claimsTable($yearId))
            ->where('CrBy', $tmEmpId)
            ->where('ClaimMonth', $month)
            ->where('ClaimId', 7)
            ->where('odomtr_status', 'Pending')
            ->where('ClaimStatus', '!=', 'Deactivate')
            ->where('ClaimYearId', $yearId)
            ->update([
                'odomtr_status' => 'Approved',
                'odomtr_approved_by' => $empId,
                'odomtr_approved_date' => now()->format('Y-m-d H:i:s'),
            ]);

        return response()->json(['Code' => '200', 'code' => '200', 'updated' => $updated, 'msg' => 'All odometer readings approved']);
    }

    /**
     * Reject all odometer readings for a team member in a month.
     * Replicates: common.php?value=reject_all_odo_reading
     */
    public function rejectAllOdoReading(Request $request): JsonResponse
    {
        $empId = (int) $request->input('empid');
        $yearId = $this->getYearId($request);
        $comId = $this->getComId($request);
        $tmEmpId = (int) $request->input('tmempid');
        $month = (int) $request->input('month');

        if ($empId <= 0 || $yearId <= 0 || $comId <= 0 || $tmEmpId <= 0 || $month <= 0) {
            return $this->error('Parameter Missing!');
        }

        if (!$this->validateToken($request)) {
            return $this->error('Invalid Employee!');
        }

        $expenseConn = $this->getExpenseConnection($comId);

        $updated = DB::connection($expenseConn)
            ->table($this->claimsTable($yearId))
            ->where('CrBy', $tmEmpId)
            ->where('ClaimMonth', $month)
            ->where('ClaimId', 7)
            ->where('odomtr_status', 'Pending')
            ->where('ClaimStatus', '!=', 'Deactivate')
            ->where('ClaimYearId', $yearId)
            ->update([
                'odomtr_status' => 'Rejected',
                'odomtr_approved_by' => $empId,
                'odomtr_approved_date' => now()->format('Y-m-d H:i:s'),
            ]);

        return response()->json(['Code' => '200', 'code' => '200', 'updated' => $updated, 'msg' => 'All odometer readings rejected']);
    }

    /**
     * Final submit of odometer (approver confirms month submission).
     * Replicates: common.php?value=approve_odo_final_submit
     */
    public function approveFinalSubmit(Request $request): JsonResponse
    {
        $empId = (int) $request->input('empid');
        $yearId = $this->getYearId($request);
        $comId = $this->getComId($request);
        $tmEmpId = (int) $request->input('tmempid');
        $month = (int) $request->input('month');

        if ($empId <= 0 || $yearId <= 0 || $comId <= 0 || $tmEmpId <= 0 || $month <= 0) {
            return $this->error('Parameter Missing!');
        }

        if (!$this->validateToken($request)) {
            return $this->error('Invalid Employee!');
        }

        $expenseConn = $this->getExpenseConnection($comId);

        // Update monthexpensefinal with odometer approval
        DB::connection($expenseConn)
            ->table($this->monthFinalTable($yearId))
            ->where('EmployeeID', $tmEmpId)
            ->where('Month', $month)
            ->where('YearId', $yearId)
            ->update([
                'OdoApprovedBy' => $empId,
                'OdoApprovedDate' => now()->format('Y-m-d H:i:s'),
                'OdoStatus' => 'Approved',
            ]);

        return response()->json(['Code' => '200', 'code' => '200', 'msg' => 'Submitted successfully']);
    }

    /**
     * Show/hide pending odometer status.
     * Replicates: common.php?value=show_hide_pending
     */
    public function showHidePending(Request $request): JsonResponse
    {
        $empId = (int) $request->input('empid');
        $yearId = $this->getYearId($request);
        $comId = $this->getComId($request);

        if ($empId <= 0 || $yearId <= 0 || $comId <= 0) {
            return $this->error('Parameter Missing!');
        }

        if (!$this->validateToken($request)) {
            return $this->error('Invalid Employee!');
        }

        $hrimsConn = $this->getHrimsConnection();
        $expenseConn = $this->getExpenseConnection($comId);

        // Get team members
        $teamMembers = DB::connection($hrimsConn)
            ->table('hrm_employee_general as eg')
            ->join('hrm_employee as e', 'eg.EmployeeID', '=', 'e.EmployeeID')
            ->where('eg.RepEmployeeID', $empId)
            ->where('e.EmpStatus', 'A')
            ->pluck('eg.EmployeeID')
            ->toArray();

        if (empty($teamMembers)) {
            return $this->success(['show_pending' => 'N', 'pending_count' => 0]);
        }

        $pendingCount = DB::connection($expenseConn)
            ->table($this->claimsTable($yearId))
            ->whereIn('CrBy', $teamMembers)
            ->where('ClaimId', 7)
            ->where('odomtr_status', 'Pending')
            ->where('ClaimStatus', '!=', 'Deactivate')
            ->where('ClaimYearId', $yearId)
            ->count();

        return $this->success([
            'show_pending' => $pendingCount > 0 ? 'Y' : 'N',
            'pending_count' => $pendingCount,
        ]);
    }

    /**
     * Handle odometer image upload (opening or closing).
     */
    private function handleOdometerUpload(Request $request, string $type): JsonResponse
    {
        $empId = (int) $request->input('empid');
        $yearId = $this->getYearId($request);
        $comId = $this->getComId($request);
        $dateFor = $request->input('datefor', '');

        if ($empId <= 0 || $yearId <= 0 || $comId <= 0 || empty($dateFor)) {
            return $this->error('Parameter Missing!');
        }

        if (!$this->validateToken($request)) {
            return $this->error('Invalid Employee!');
        }

        $fileKey = $type === 'opening' ? 'odo_opening_img' : 'odo_closing_img';
        if (!$request->hasFile($fileKey)) {
            return $this->error('No file uploaded.');
        }

        $file = $request->file($fileKey);
        $allowedMimes = ['image/jpeg', 'image/png'];

        if (!in_array($file->getMimeType(), $allowedMimes)) {
            return $this->error('Invalid file type. Allowed: image/jpeg, image/png');
        }

        if ($file->getSize() > 12 * 1024 * 1024) {
            return $this->error('File size exceeds limit (12MB)');
        }

        $expenseConn = $this->getExpenseConnection($comId);

        try {
            $prefix = $type === 'opening' ? 'OpnImg' : 'ClsImg';
            $mili = sprintf('%03d', (microtime(true) - floor(microtime(true))) * 1000);
            $fileName = sprintf('%s_%s_%s%s.jpg', $prefix, $empId, date('dmyHis'), $mili);

            // UAT R-01: stored through a batch, so a failed DB write deletes the file again.
            $files = new \App\Services\ClaimFiles\ClaimFileBatch();
            try {
                $files->putMobile($file, $yearId, $empId, $fileName, $fileKey);
            } catch (\App\Services\ClaimFiles\ClaimFileException $e) {
                return $this->error($e->getMessage());
            }

            $files->commitWith($expenseConn, function () use ($expenseConn, $yearId, $empId, $dateFor, $type, $fileName, $request) {
                // Find the expense claim for this date and update
                $claim = DB::connection($expenseConn)
                    ->table($this->claimsTable($yearId))
                    ->where('CrBy', $empId)
                    ->where('BillDate', $dateFor)
                    ->where('ClaimId', 7)
                    ->where('ClaimStatus', '!=', 'Deactivate')
                    ->orderByDesc('ExpId')
                    ->first();

                if ($claim) {
                    $updateField = $type === 'opening' ? 'opening_filepath' : 'closing_filepath';
                    DB::connection($expenseConn)
                        ->table($this->claimsTable($yearId))
                        ->where('ExpId', $claim->ExpId)
                        ->update([$updateField => $fileName]);
                    \App\Services\Claims\OdometerGps::save($expenseConn, $this->claimsTable($yearId), (int) $claim->ExpId, $request, [$type]); // GPS-ODO-01

                    // Insert into uploads table
                    $sequence = $type === 'opening' ? 1 : 2;
                    DB::connection($expenseConn)->table($this->uploadsTable($yearId))->insert([
                        'ExpId' => $claim->ExpId,
                        'FileName' => $fileName,
                        'UploadSequence' => $sequence,
                    ]);
                }
            });

            return $this->success(['fileName' => $fileName], 'File uploaded successfully');
        } catch (\Throwable $e) {
            Log::error("Mobile API odometer upload error: " . $e->getMessage());
            return $this->error('Upload failed: ' . $e->getMessage(), '500');
        }
    }
}
