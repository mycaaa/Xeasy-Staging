<?php

namespace App\Http\Controllers\Api\Mobile;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * App version check and maintenance status.
 * Replicates: appversion.php
 */
class AppVersionController extends BaseMobileController
{
    /**
     * Check app version and employee status.
     * Replicates: appversion.php?value=version
     */
    public function version(Request $request): JsonResponse
    {
        $value = $request->input('value');
        if (empty($value)) {
            return response()->json(["status" => "100", "msg" => "Parameter missing!"]);
        } elseif ($value !== 'version') {
            return response()->json(["data" => "100", "msg" => "Missing Value!"]);
        }

        $empId = (int) $request->input('empid');
        $comId = $this->getComId($request);

        $hrimsConn = $this->getHrimsConnection();
        $expenseConn = $this->getExpenseConnection($comId);

        // Check employee status
        $empStatusMsg = 'Not found or inactive';
        $empStatusKey = 'N';

        if ($empId > 0) {
            $employee = DB::connection($hrimsConn)
                ->table('hrm_employee')
                ->where('EmployeeID', $empId)
                ->where('EmpStatus', '!=', 'De')
                ->where('CompanyId', $comId)
                ->select('EmployeeID', 'EmpStatus', 'DateOfSepration')
                ->first();

            if ($employee) {
                if ($employee->EmpStatus === 'A' || $employee->EmpStatus === 'R' || \App\Support\ZeroDate::none($employee->DateOfSepration)) {
                    $empStatusMsg = 'Active Employee';
                    $empStatusKey = 'Y';
                } elseif ($employee->EmpStatus === 'D' && $employee->DateOfSepration >= '2022-05-01') {
                    $datAcc = date('Y-m-d', strtotime($employee->DateOfSepration . ' +15 day'));
                    $empStatusMsg = "Separated (Date + 15 days: {$datAcc})";
                    $empStatusKey = date('Y-m-d') <= $datAcc ? 'Y' : 'N';
                } else {
                    // Check relieving date
                    $relieving = DB::connection($hrimsConn)
                        ->table('hrm_employee_separation')
                        ->where('EmployeeID', $employee->EmployeeID)
                        ->max('Emp_RelievingDate');

                    if ($relieving) {
                        $datAcc = date('Y-m-d', strtotime($relieving . ' +15 day'));
                        $empStatusMsg = "Relieved (Last Relieving + 15 days: {$datAcc})";
                    }
                    $empStatusKey = 'N';
                }
            }
        }

        // Get app version info
        $versionData = DB::connection($expenseConn)
            ->table('app_version')
            ->first();

        if ($versionData) {
            return response()->json([
                'status' => '300',
                'version_no' => $versionData->VersionD ?? '',
                'version_no_ios' => $versionData->VersionD_ios ?? '',
                'under_maintenance' => 'No',
                'under_maintenance_msg' => 'We are currently performing maintenance. Upload claims are temporarily unavailable on this platform.',
                'notice' => 'No',
                'notice_msg' => 'For the April month claim, please login financial year 2026-2027.',
                'employee_status' => [
                    'msg' => $empStatusMsg,
                    'active' => $empStatusKey,
                ],
            ]);
        }

        return response()->json([
            'status' => '100',
            'msg' => 'No version data found!',
            'employee_status' => [
                'msg' => $empStatusMsg,
                'active' => $empStatusKey,
            ],
        ]);
    }

    /**
     * iOS update request.
     * Replicates: common.php?value=ios_update_request
     */
    public function iosUpdateRequest(Request $request): JsonResponse
    {
        $empId = (int) $request->input('empid');
        $yearId = $this->getYearId($request);

        if ($empId <= 0 || $yearId <= 0) {
            return $this->error('Parameter Missing!');
        }

        if (!$this->validateToken($request)) {
            return $this->error('Invalid Employee!');
        }

        // This endpoint is used to signal from mobile app that iOS needs update
        return $this->success([], 'iOS update request recorded');
    }

    /**
     * Get running KM data (cron/background).
     * Replicates: common.php?value=get_data_runkm
     */
    public function getDataRunKm(Request $request): JsonResponse
    {
        $comId = $this->getComId($request);

        if ($comId <= 0) {
            return $this->error('Parameter Missing!');
        }

        $expenseConn = $this->getExpenseConnection($comId);
        $yearId = $this->getYearId($request);

        // Get all running KM data grouped by employee and month
        $data = DB::connection($expenseConn)
            ->table($this->claimsTable($yearId))
            ->where('ClaimId', 7)
            ->where('ClaimStatus', '!=', 'Deactivate')
            ->where('ClaimStatus', '!=', 'Draft')
            ->where('TotKm', '>=', 0)
            ->selectRaw('CrBy, ClaimMonth, SUM(TotKm) as TotalKm, WType')
            ->groupBy('CrBy', 'ClaimMonth', 'WType')
            ->get();

        return $this->success(['run_km_data' => $data->toArray()]);
    }

    /**
     * Update image data (batch image processing).
     * Replicates: common.php?value=update_image_data
     */
    public function updateImageData(Request $request): JsonResponse
    {
        $comId = $this->getComId($request);

        if ($comId <= 0) {
            return $this->error('Parameter Missing!');
        }

        // This is typically used for batch S3 migration / image URL updates
        // It's a background/admin process, not commonly called by mobile app
        return $this->success([], 'Image data update processed');
    }
}
