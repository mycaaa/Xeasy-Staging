<?php

namespace App\Http\Controllers\Api\Mobile;

use App\Services\ClaimRules\ClaimSubmissionValidator;
use App\Services\ClaimFiles\ClaimFileBatch;
use App\Services\ClaimFiles\ClaimFileException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class ClaimController extends BaseMobileController
{
    /*
     * UAT-03 (mobile lists): these lists filtered on ClaimStatus 'Draft', 'Open' and 'Close', which the
     * database never uses (claims are Submitted, Filled, Verified, Approved, Financed, Payment, Return,
     * Deactivate; months in y*_monthexpensefinal are Open or Closed). They now mirror legacy
     * api/common.php: Draftlist = uploaded-but-not-filled claims (Submitted/Draft, AttachTo = 0);
     * Openlist = Open months with filled claims; Closelist = Closed months. The legacy response keys
     * (Draft_list, Open_list, DayWiseDetails, Close_list, Closed_months) are returned, plus the
     * lower-case keys the Laravel endpoints already returned.
     */

    private const MONTH_NAMES = [1 => 'January', 2 => 'February', 3 => 'March', 4 => 'April', 5 => 'May', 6 => 'June',
        7 => 'July', 8 => 'August', 9 => 'September', 10 => 'October', 11 => 'November', 12 => 'December'];

    /** Shared guard: returns [empId, yearId, comId, conn] or a JsonResponse error. */
    private function listContext(Request $request, bool $needMonth = false)
    {
        $empId = (int) $request->input('empid');
        $yearId = $this->getYearId($request);
        $comId = $this->getComId($request);
        $month = (int) $request->input('Month', $request->input('month'));

        if ($empId <= 0 || $yearId <= 0 || $comId <= 0 || ($needMonth && $month <= 0)) {
            return $this->error('Parameter Missing!');
        }
        if (!$this->validateToken($request)) {
            return $this->error('Invalid Employee!');
        }

        return [$empId, $yearId, $comId, $this->getExpenseConnection($comId), $month];
    }

    /** Claim columns the legacy day-wise lists return. */
    private function dayWiseColumns(): array
    {
        return [
            'e.ExpId', 'e.ClaimYearId', 'e.CrBy', 'e.ClaimId', 'e.BillDate', 'e.FilledTAmt', 'e.TotKm',
            'e.VerifyTAmt', 'e.VerifyBy', 'e.VerifyDate', 'e.VerifyTRemark', 'e.ApprTAmt', 'e.ApprBy', 'e.ApprDate',
            'e.ApprTRemark', 'e.FinancedTAmt', 'e.FinancedBy', 'e.FinancedDate', 'e.FinancedTRemark', 'e.ClaimStatus',
            'e.Remark', 'e.DateEntryRemark', 'e.FilledOkay', 'e.ClaimAtStep', 'ct.ClaimName', 'e.ClaimMonth',
            'e.opening_filepath', 'e.closing_filepath', 'e.Img_1', 'e.Img_2', 'e.Img_3', 'e.Img_4 as pdf_1', 'e.Img_5 as pdf_2',
            'e.v_verify',
        ];
    }

    /** Claims of one month (legacy: not Deactivate/Draft, FilledBy > 0), optionally excluding statuses. */
    private function monthClaims(string $conn, int $yearId, int $empId, int $month, array $excludeStatuses, bool $withMedia)
    {
        $rows = DB::connection($conn)->table("y{$yearId}_expenseclaims as e")
            ->leftJoin('claimtype as ct', 'e.ClaimId', '=', 'ct.ClaimId')
            ->where('e.ClaimMonth', $month)
            ->where('e.ClaimYearId', $yearId)
            ->where('e.CrBy', $empId)
            ->whereNotIn('e.ClaimStatus', $excludeStatuses)
            ->where('e.FilledBy', '>', 0)
            ->orderBy('e.BillDate')
            ->select($this->dayWiseColumns())
            ->get();

        return $rows->map(function ($r) use ($withMedia, $yearId, $conn) {
            $a = (array) $r;
            return $withMedia ? array_merge($a, $this->processMediaFiles($r, $yearId, $conn)) : $a;
        })->all();
    }

    /** Closed months with claims (legacy Closed_months). */
    private function closedMonths(string $conn, int $yearId, int $empId): array
    {
        return DB::connection($conn)->table("y{$yearId}_monthexpensefinal")
            ->where('EmployeeID', $empId)->where('YearId', $yearId)
            ->where('Status', 'Closed')->where('Total_Claim', '>', 0)
            ->groupBy('Month')->orderByDesc('Month')->pluck('Month')
            ->map(fn ($m) => ['Month_Name' => self::MONTH_NAMES[(int) $m] ?? (string) $m, 'Month_Number' => (int) $m])
            ->all();
    }

    /**
     * Draft list: claims uploaded but not yet filled.
     * Replicates: common.php?value=Draftlist
     */
    public function draftList(Request $request): JsonResponse
    {
        $ctx = $this->listContext($request);
        if ($ctx instanceof JsonResponse) {
            return $ctx;
        }
        [$empId, $yearId, , $conn] = $ctx;

        $rows = DB::connection($conn)->table("y{$yearId}_expenseclaims as ec")
            ->leftJoin("y{$yearId}_expenseremark as er", 'ec.ExpId', '=', 'er.ExpId')
            ->leftJoin('claimtype as ct', 'ec.ClaimId', '=', 'ct.ClaimId')
            ->where('ec.CrBy', $empId)
            ->whereIn('ec.ClaimStatus', ['Submitted', 'Draft'])
            ->where('ec.AttachTo', 0)
            ->orderByDesc('ec.ExpId')
            ->select([
                'ec.ExpId', 'ec.ClaimId', 'ct.ClaimName', 'ec.CrDate', 'ec.BillDate', 'ec.ClaimYearId', 'ec.CrBy',
                'ec.Remark', 'ec.DateEntryRemark', 'ec.odomtr_opening', 'ec.opening_filepath', 'ec.odomtr_closing',
                'ec.closing_filepath', 'ec.offline_opening_filepath', 'ec.offline_clossing_filepath',
                'ec.Img_1', 'ec.Img_2', 'ec.Img_3', 'ec.Img_4 as pdf_1', 'ec.Img_5 as pdf_2',
                'ec.Img1_offline', 'ec.Img2_offline', 'ec.Img3_offline', 'ec.Pdf1_offline', 'ec.Pdf2_offline',
                'ec.offline_exp_id', 'ec.ClaimStatus', 'ec.FilledTAmt as TotAmt', 'ec.ClaimMonth',
                'er.Remark as remark', 'er.by as sent_by', 'er.CrDateTime as remark_date',
            ])
            ->get();

        if ($rows->isEmpty()) {
            return $this->error('Data Not Available!');
        }

        $list = [];
        foreach ($rows as $r) {
            if (!isset($list[$r->ExpId])) {
                $item = (array) $r;
                unset($item['remark'], $item['sent_by'], $item['remark_date']);
                $item['remarks'] = [];
                $list[$r->ExpId] = array_merge($item, $this->processMediaFiles($r, $yearId, $conn));
            }
            if (!empty($r->remark)) {
                $list[$r->ExpId]['remarks'][] = ['remark' => $r->remark, 'sent_by' => $r->sent_by, 'remark_date' => $r->remark_date];
            }
        }
        $list = array_values($list);

        return $this->success(['Draft_list' => $list, 'draft_list' => $list], 'Data Available!');
    }

    /**
     * Open list: months still Open in y*_monthexpensefinal that have filled claims, with totals.
     * Replicates: common.php?value=Openlist
     */
    public function openList(Request $request): JsonResponse
    {
        $ctx = $this->listContext($request);
        if ($ctx instanceof JsonResponse) {
            return $ctx;
        }
        [$empId, $yearId, , $conn] = $ctx;
        $db = DB::connection($conn);

        $months = $db->table("y{$yearId}_monthexpensefinal")
            ->where('EmployeeID', $empId)->where('YearId', $yearId)->where('Status', 'Open')
            ->groupBy('Month')->orderBy('Month')
            ->select('Month', DB::raw('MAX(SubPermission) as SubPermission'))
            ->get();

        if ($months->isEmpty()) {
            return $this->error('Data Not Available!');
        }

        // Claim counts the way legacy counts them: running-km claims (ClaimId 7) count only once
        // the vehicle is verified and the claim is Filled.
        $counted = "(ClaimId != 7 OR (ClaimId = 7 AND v_verify = 'Y' AND ClaimStatus = 'Filled'))";
        $list = [];
        foreach ($months as $m) {
            $t = $db->table("y{$yearId}_expenseclaims")
                ->where('ClaimYearId', $yearId)->where('CrBy', $empId)->where('ClaimMonth', $m->Month)
                ->whereNotIn('ClaimStatus', ['Deactivate', 'Draft'])
                ->selectRaw("COUNT(CASE WHEN {$counted} THEN 1 END) AS TotClaim,
                    COALESCE(SUM(CASE WHEN FilledTAmt != '' AND FilledTAmt > 0 AND {$counted} THEN FilledTAmt END), 0) AS TotFilledAmt,
                    COUNT(CASE WHEN FilledOkay = 1 THEN 1 END) AS TotVerifiedClaims,
                    COUNT(CASE WHEN FilledOkay = 2 THEN 1 END) AS TotDeniedClaims,
                    COUNT(CASE WHEN FilledOkay = 0 THEN 1 END) AS TotUnactionedClaims,
                    COALESCE(SUM(CASE WHEN FilledOkay = 1 AND FilledTAmt != '' AND FilledTAmt > 0 THEN FilledTAmt END), 0) AS TotVerifyAmt")
                ->first();

            if ((int) $t->TotClaim <= 0 || (float) $t->TotFilledAmt <= 0) {
                continue;
            }
            $verify = ['TotVerifyAmt' => $t->TotVerifyAmt];
            unset($t->TotVerifyAmt);

            // Legacy rule: submit allowed for last month or earlier until the 15th, or with SubPermission.
            $submit = ((int) $m->Month <= (int) date('m', strtotime('-1 month')) && date('Y-m-d') <= date('Y-m-15'))
                || $m->SubPermission === 'Y';

            $list[] = [
                'Month' => $m->Month,
                'SubPermission' => $m->SubPermission,
                'TotalFilled_Claim_with_Amount' => (array) $t,
                'TotalVerify_Claim_with_Amount' => $verify,
                'submit_btn' => $submit ? 'Y' : 'N',
            ];
        }

        if (empty($list)) {
            return $this->error('No Claims with Filled Amount Found!', '404');
        }

        return $this->success(['Open_list' => $list, 'open_list' => $list], 'Data Available!');
    }

    /**
     * Claims of one open month.
     * Replicates: common.php?value=Openlist_Day
     */
    public function openListDay(Request $request): JsonResponse
    {
        $ctx = $this->listContext($request, true);
        if ($ctx instanceof JsonResponse) {
            return $ctx;
        }
        [$empId, $yearId, , $conn, $month] = $ctx;

        $rows = $this->monthClaims($conn, $yearId, $empId, $month, ['Deactivate', 'Draft', 'Submitted'], true);
        if (empty($rows)) {
            return $this->error('No Claims with Filled Amount Found!', '404');
        }

        return $this->success(['DayWiseDetails' => $rows, 'open_list_day' => $rows], 'Data Available!');
    }

    /**
     * Close list: Closed months with claims that are not yet paid.
     * Replicates: common.php?value=Closelist
     */
    public function closeList(Request $request): JsonResponse
    {
        $ctx = $this->listContext($request);
        if ($ctx instanceof JsonResponse) {
            return $ctx;
        }
        [$empId, $yearId, , $conn] = $ctx;

        $rows = DB::connection($conn)->table("y{$yearId}_monthexpensefinal")
            ->where('EmployeeID', $empId)->where('YearId', $yearId)
            ->where('Status', 'Closed')->where('Total_Claim', '>', 0)
            ->where('Fin_PayBy', 0)
            ->where(fn ($q) => $q->where('Fin_PayDate', '0000-00-00')->orWhereNull('Fin_PayDate'))
            ->orderBy('Month')
            ->get()->unique('Month')->values()->map(fn ($r) => (array) $r)->all();

        if (empty($rows)) {
            return $this->error('Data Not Available!');
        }
        $closed = $this->closedMonths($conn, $yearId, $empId);

        return $this->success(['Close_list' => $rows, 'Closed_months' => $closed, 'close_list' => $rows], 'Data Available!');
    }

    /**
     * Claims of one closed month.
     * Replicates: common.php?value=Closelist_Day
     */
    public function closeListDay(Request $request): JsonResponse
    {
        $ctx = $this->listContext($request, true);
        if ($ctx instanceof JsonResponse) {
            return $ctx;
        }
        [$empId, $yearId, , $conn, $month] = $ctx;

        $rows = $this->monthClaims($conn, $yearId, $empId, $month, ['Deactivate', 'Draft'], true);
        if (empty($rows)) {
            return $this->error('No Claims with Filled Amount Found!', '404');
        }

        return $this->success(['DayWiseDetails' => $rows, 'close_list_day' => $rows], 'Data Available!');
    }

    /**
     * One closed month with its claims.
     * Replicates: common.php?value=Closelist_Month
     */
    public function closeListMonth(Request $request): JsonResponse
    {
        return $this->closedMonthRows($request, true);
    }

    /**
     * All closed months (legacy returns the month rows without claim details).
     * Replicates: common.php?value=Closelist_All_Month
     */
    public function closeListAllMonths(Request $request): JsonResponse
    {
        return $this->closedMonthRows($request, false);
    }

    private function closedMonthRows(Request $request, bool $oneMonth): JsonResponse
    {
        $ctx = $this->listContext($request, $oneMonth);
        if ($ctx instanceof JsonResponse) {
            return $ctx;
        }
        [$empId, $yearId, , $conn, $month] = $ctx;

        $q = DB::connection($conn)->table("y{$yearId}_monthexpensefinal")
            ->where('EmployeeID', $empId)->where('YearId', $yearId)
            ->where('Status', 'Closed')->where('Total_Claim', '>', 0)
            ->orderBy('Month');
        if ($oneMonth) {
            $q->where('Month', $month);
        }
        $rows = $q->get()->unique('Month')->values();
        if ($rows->isEmpty()) {
            return $this->error('Data Not Available!');
        }

        $list = $rows->map(function ($r) use ($oneMonth, $conn, $yearId, $empId) {
            $a = (array) $r;
            if ($oneMonth) {
                $a['DayWiseDetails'] = $this->monthClaims($conn, $yearId, $empId, (int) $r->Month, ['Deactivate', 'Draft'], false);
            }
            return $a;
        })->all();

        return $this->success([
            'Close_list' => $list,
            'Closed_months' => $this->closedMonths($conn, $yearId, $empId),
            'close_list' => $list,
        ], 'Data Available!');
    }

    /**
     * Get expense detail by ID.
     * Replicates: common.php?value=ExDetail
     */
    public function expenseDetail(Request $request): JsonResponse
    {
        $empId = (int) $request->input('empid');
        $expId = (int) $request->input('expid');
        $yearId = $this->getYearId($request);
        $comId = $this->getComId($request);

        if ($empId <= 0 || $expId <= 0 || $yearId <= 0 || $comId <= 0) {
            return $this->error('Parameter Missing!');
        }

        if (!$this->validateToken($request)) {
            return $this->error('Invalid Employee!');
        }

        $expenseConn = $this->getExpenseConnection($comId);

        $claim = DB::connection($expenseConn)
            ->table($this->claimsTable($yearId) . ' as ec')
            ->leftJoin('claimtype as ct', 'ec.ClaimId', '=', 'ct.ClaimId')
            ->where('ec.ExpId', $expId)
            ->select([
                'ec.*', 'ct.ClaimName',
            ])
            ->first();

        if (!$claim) {
            return $this->error('Expense detail not found.');
        }

        // Get remarks
        $remarks = DB::connection($expenseConn)
            ->table($this->remarkTable($yearId))
            ->where('ExpId', $expId)
            ->orderByDesc('CrDateTime')
            ->get();

        // Get uploaded files
        $uploads = DB::connection($expenseConn)
            ->table($this->uploadsTable($yearId))
            ->where('ExpId', $expId)
            ->orderBy('UploadSequence')
            ->get();

        // Process media file URLs
        $mediaFiles = $this->processMediaFiles($claim, $yearId, $expenseConn);

        return $this->success([
            'expense_detail' => $claim,
            'remarks' => $remarks->toArray(),
            'uploads' => $uploads->toArray(),
            'media' => $mediaFiles,
        ]);
    }

    /**
     * Get vehicle running KM for a month.
     * Replicates: common.php?value=VehicleRunning
     */
    public function vehicleRunning(Request $request): JsonResponse
    {
        $empId = (int) $request->input('empid');
        $month = (int) $request->input('month');
        $yearId = $this->getYearId($request);
        $comId = $this->getComId($request);

        if ($empId <= 0 || $month <= 0 || $yearId <= 0 || $comId <= 0) {
            return $this->error('Parameter Missing!');
        }

        if (!$this->validateToken($request)) {
            return $this->error('Invalid Employee!');
        }

        $expenseConn = $this->getExpenseConnection($comId);

        $runningKm = DB::connection($expenseConn)
            ->table($this->claimsTable($yearId))
            ->where('CrBy', $empId)
            ->where('ClaimId', 7)
            ->where('ClaimMonth', $month)
            ->where('ClaimStatus', '!=', 'Deactivate')
            ->where('ClaimStatus', '!=', 'Draft')
            ->where('TotKm', '>=', 0)
            ->selectRaw('SUM(TotKm) as TotalKm, WType as VehicleType')
            ->groupBy('WType')
            ->get();

        return $this->success(['vehicle_running' => $runningKm->toArray()]);
    }

    /**
     * Check rate per KM for employee.
     * Replicates: claim.php?value=check_rate
     */
    public function checkRate(Request $request): JsonResponse
    {
        $empId = (int) $request->input('empid');
        $yearId = $this->getYearId($request);
        $comId = $this->getComId($request);
        $policyId = (int) $request->input('Pid', 0);

        if ($empId <= 0 || $yearId <= 0 || $comId <= 0) {
            return $this->error('Parameter Missing!');
        }

        $hrimsConn = $this->getHrimsConnection();
        $expenseConn = $this->getExpenseConnection($comId);

        // Get employee eligibility
        $eligibility = DB::connection($hrimsConn)
            ->table('hrm_employee_eligibility as el')
            ->leftJoin('hrm_employee_general as g', 'el.EmployeeID', '=', 'g.EmployeeID')
            ->leftJoin('hrm_employee as e', 'el.EmployeeID', '=', 'e.EmployeeID')
            ->where('el.EmployeeID', $empId)
            ->where('el.Status', 'A')
            ->select([
                'el.Travel_TwoWeeKM', 'el.Travel_FourWeeKM', 'el.old_Travel_FourWeeKM',
                'el.FourWElig', 'el.CostOfVehicle', 'el.WithDriver',
                'el.VehiclePolicy', 'el.old_VehiclePolicy', 'e.EmpCode', 'g.GradeId',
            ])
            ->first();

        if (!$eligibility) {
            return $this->error('Eligibility not found for employee.');
        }

        // Get vehicle details from hrims.hrm_employee_vehicle in descending order (latest data)
        $vehicle = null;
        try {
            $vehicle = DB::connection('hrims')
                ->table('hrm_employee_vehicle')
                ->where(function ($q) use ($empId, $eligibility) {
                    $q->where('EmployeeID', $empId);
                    if (!empty($eligibility->EmpCode)) {
                        $q->orWhere('EmpCode', $eligibility->EmpCode);
                    }
                })
                ->where('status', 'A')
                ->orderBy('id', 'DESC')
                ->first();
        } catch (\Exception $e) {
        }

        if (!$vehicle) {
            $vehicle = DB::connection($expenseConn)
                ->table('employee_vehicle')
                ->where('EmpCode', $eligibility->EmpCode)
                ->where('Status', 'A')
                ->orderBy('id', 'DESC')
                ->first();
        }

        $vehiclePolicy = $policyId > 0 ? $policyId : ($eligibility->VehiclePolicy ?? 0);
        $twoWheelerRate = preg_replace('/[^0-9.]/', '', $eligibility->Travel_TwoWeeKM ?? '0');
        $fourWheelerRate = preg_replace('/[^0-9.]/', '', $eligibility->Travel_FourWeeKM ?? '0');

        $vehValue = $vehicle ? (float) (($vehicle->vehiclevalue ?? 0) > 0 ? $vehicle->vehiclevalue : ($vehicle->price ?? ($vehicle->VehicleValue ?? 0))) : 0;
        $vehOldNew = $vehicle ? ($vehicle->oldnew ?? ($vehicle->OldNew ?? '')) : '';
        $vehRegDate = $vehicle ? ($vehicle->registration_date ?? ($vehicle->RegDate ?? '')) : '';

        return $this->success([
            'two_wheeler_rate' => $twoWheelerRate,
            'four_wheeler_rate' => $fourWheelerRate,
            'vehicle_policy' => $vehiclePolicy,
            'vehicle' => $vehicle ? [
                'OldNew' => $vehOldNew,
                'RegDate' => $vehRegDate,
                'VehicleValue' => $vehValue,
            ] : null,
        ]);
    }

    /**
     * Update DA (Daily Allowance) claim.
     * Replicates: claim.php?value=DA_Update
     */
    public function daUpdate(Request $request): JsonResponse
    {
        $empId = (int) $request->input('empid');
        $yearId = $this->getYearId($request);
        $comId = $this->getComId($request);
        $billDate = $request->input('Billdate', '');
        $daType = $request->input('DAtype', '');
        $daAmt = $request->input('DAamt', '');
        $daRemark = $request->input('DArmk', '');

        if ($empId <= 0 || $yearId <= 0 || empty($billDate) || empty($daType) || empty($daAmt) || $comId <= 0) {
            return $this->error('Parameter Missing!');
        }

        if (!$this->validateToken($request)) {
            return $this->error('Invalid Employee!');
        }

        // Tracker #17: DOS needs a proof attachment. This REST endpoint does not store
        // attachments (the app's DA_Update via claim.php -> ClaimActionsController does), so
        // UAT R-01: an attached file is not counted here, as it would never be stored.
        $ruleError = ClaimSubmissionValidator::first(app(ClaimSubmissionValidator::class)->validateDaRequest($request, 0));
        if ($ruleError !== null) {
            if ($request->hasFile('upload_img')) {
                $ruleError .= ' This endpoint does not store attachments: submit the DOS claim with its proof through DA_Update.';
            }
            return $this->error($ruleError);
        }

        $expenseConn = $this->getExpenseConnection($comId);
        $claimMonth = (int) date('n', strtotime($billDate));

        // Check if DA already exists for this date
        $existing = DB::connection($expenseConn)
            ->table($this->claimsTable($yearId))
            ->where('CrBy', $empId)
            ->where('BillDate', $billDate)
            ->whereIn('ClaimId', [1, 2, 3]) // DA claim types
            ->where('ClaimStatus', '!=', 'Deactivate')
            ->first();

        if ($existing) {
            // Update existing
            DB::connection($expenseConn)
                ->table($this->claimsTable($yearId))
                ->where('ExpId', $existing->ExpId)
                ->update([
                    'TotAmt' => $daAmt,
                    'Remark' => $daRemark,
                    'DAType' => $daType,
                    'ModDate' => now()->format('Y-m-d H:i:s'),
                ]);

            return $this->success(['ExpId' => $existing->ExpId], 'DA Updated successfully');
        }

        // Insert new DA
        $expId = DB::connection($expenseConn)
            ->table($this->claimsTable($yearId))
            ->insertGetId([
                'ClaimId' => $daType,
                'BillDate' => $billDate,
                'TotAmt' => $daAmt,
                'Remark' => $daRemark,
                'CrBy' => $empId,
                'CrDate' => now()->format('Y-m-d H:i:s'),
                'ClaimMonth' => $claimMonth,
                'ClaimYearId' => $yearId,
                'ClaimStatus' => 'Open',
                'DAType' => $daType,
            ]);

        return $this->success(['ExpId' => $expId], 'DA Added successfully');
    }

    /**
     * Upload image for a claim.
     * Replicates: claim.php?value=uploadimg
     */
    public function uploadImage(Request $request): JsonResponse
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

        if (!$request->hasFile('upload_img') && !$request->hasFile('upload_pdf')) {
            return $this->error('No file uploaded.');
        }

        $expenseConn = $this->getExpenseConnection($comId);
        $expId = (int) $request->input('expid', 0);

        // UAT R-01: store all files first; if one fails, delete the stored ones and report the
        // error (previously the failure was skipped and the response still said "uploaded").
        $files = new ClaimFileBatch();
        $rows = [];
        $stamp = date('dmyHis') . ClaimFileBatch::uniqueSuffix(); // no same-second name collisions
        try {
            $imageCount = 0;
            foreach (array_filter(\Illuminate\Support\Arr::wrap($request->file('upload_img'))) as $image) {
                $imageCount++;
                $extension = $image->getClientOriginalExtension() ?: 'jpg';
                $fileName = sprintf('Img_%s_%s_%d.%s', $empId, $stamp, $imageCount, $extension);
                $files->putMobile($image, $yearId, $empId, $fileName, 'upload_img');
                $rows[] = ['ExpId' => $expId, 'FileName' => $fileName, 'UploadSequence' => $imageCount];
            }
            $pdfCount = 3;
            foreach (array_filter(\Illuminate\Support\Arr::wrap($request->file('upload_pdf'))) as $pdf) {
                $pdfCount++;
                $fileName = sprintf('Pdf_%s_%s_%d.pdf', $empId, $stamp, $pdfCount);
                $files->putMobile($pdf, $yearId, $empId, $fileName, 'upload_pdf');
                $rows[] = ['ExpId' => $expId, 'FileName' => $fileName, 'UploadSequence' => $pdfCount];
            }
        } catch (ClaimFileException $e) {
            $files->discard();
            return $this->error($e->getMessage());
        }

        try {
            $uploadedFiles = $files->names();
            $files->commitWith($expenseConn, function () use ($expenseConn, $yearId, $expId, $rows) {
                if ($expId > 0 && ! empty($rows)) {
                    DB::connection($expenseConn)->table($this->uploadsTable($yearId))->insert($rows);
                }
            });

            return $this->success(['files' => $uploadedFiles], 'Files uploaded successfully');
        } catch (\Throwable $e) {
            Log::error('Mobile API uploadImage error: ' . $e->getMessage());
            return $this->error('File upload failed: ' . $e->getMessage(), '500');
        }
    }

    /**
     * Upload bill for a claim.
     * Replicates: claim.php?value=upload_bill
     */
    public function uploadBill(Request $request): JsonResponse
    {
        // Same as uploadImage but for bill-specific uploads
        return $this->uploadImage($request);
    }

    /**
     * Mark claim as Okay (employee confirms).
     * Replicates: claim.php?value=Claim_Okay
     */
    public function claimOkay(Request $request): JsonResponse
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

        \App\Services\Claims\ClaimTransition::guard($expenseConn, (int) $yearId, fn ($q) => $q->where('ExpId', $expId)->where('CrBy', $empId), 'Open', 'claimant', 'app_claim_okay', null, 'app', (int) $empId, (int) $comId);
        DB::connection($expenseConn)
            ->table($this->claimsTable($yearId))
            ->where('ExpId', $expId)
            ->where('CrBy', $empId)
            ->update([
                'ClaimStatus' => 'Open',
                'Okay' => 'Y',
                'ModDate' => now()->format('Y-m-d H:i:s'),
            ]);

        return $this->success([], 'Claim marked as Okay');
    }

    /**
     * Mark claim as Deny (employee denies verified amount).
     * Replicates: claim.php?value=Claim_Deny
     */
    public function claimDeny(Request $request): JsonResponse
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
            ->where('CrBy', $empId)
            ->update([
                'Okay' => 'N',
                'ModDate' => now()->format('Y-m-d H:i:s'),
            ]);

        return $this->success([], 'Claim denied');
    }

    /**
     * Delete (deactivate) a claim.
     * Replicates: claim.php?value=Claim_Deleted
     */
    public function claimDeleted(Request $request): JsonResponse
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

        // Only allow deletion of Draft/Open claims by the creator
        $claim = DB::connection($expenseConn)
            ->table($this->claimsTable($yearId))
            ->where('ExpId', $expId)
            ->where('CrBy', $empId)
            ->whereIn('ClaimStatus', ['Draft', 'Open'])
            ->first();

        if (!$claim) {
            return $this->error('Claim not found or cannot be deleted.');
        }

        \App\Services\Claims\ClaimTransition::guard($expenseConn, (int) $yearId, (int) $expId, 'Deactivate', 'claimant', 'app_claim_delete', null, 'app', (int) $empId, (int) $comId);
        DB::connection($expenseConn)
            ->table($this->claimsTable($yearId))
            ->where('ExpId', $expId)
            ->update([
                'ClaimStatus' => 'Deactivate',
                'ModDate' => now()->format('Y-m-d H:i:s'),
            ]);

        return $this->success([], 'Claim deleted successfully');
    }

    /**
     * Mark all claims for a month as Okay.
     * Replicates: claim.php?value=Claim_OkeyAll
     */
    public function claimOkayAll(Request $request): JsonResponse
    {
        $empId = (int) $request->input('empid');
        $yearId = $this->getYearId($request);
        $comId = $this->getComId($request);
        $month = (int) $request->input('month');

        if ($empId <= 0 || $yearId <= 0 || $comId <= 0 || $month <= 0) {
            return $this->error('Parameter Missing!');
        }

        if (!$this->validateToken($request)) {
            return $this->error('Invalid Employee!');
        }

        $expenseConn = $this->getExpenseConnection($comId);

        $updated = DB::connection($expenseConn)
            ->table($this->claimsTable($yearId))
            ->where('CrBy', $empId)
            ->where('ClaimMonth', $month)
            ->where('ClaimYearId', $yearId)
            ->where('ClaimStatus', '!=', 'Deactivate')
            ->where('ClaimStatus', '!=', 'Draft')
            ->update([
                'Okay' => 'Y',
                'ModDate' => now()->format('Y-m-d H:i:s'),
            ]);

        return $this->success(['updated' => $updated], 'All claims marked as Okay');
    }

    /**
     * Submit claims for a month.
     * Replicates: claim.php?value=Claim_Submit
     */
    public function claimSubmit(Request $request): JsonResponse
    {
        $empId = (int) $request->input('empid');
        $yearId = $this->getYearId($request);
        $comId = $this->getComId($request);
        $month = (int) $request->input('month');

        if ($empId <= 0 || $yearId <= 0 || $comId <= 0 || $month <= 0) {
            return $this->error('Parameter Missing!');
        }

        if (!$this->validateToken($request)) {
            return $this->error('Invalid Employee!');
        }

        $expenseConn = $this->getExpenseConnection($comId);

        // Update all open claims for this month to Submitted
        \App\Services\Claims\ClaimTransition::guard($expenseConn, (int) $yearId, fn ($q) => $q->where('CrBy', $empId)->where('ClaimMonth', $month)->where('ClaimYearId', $yearId)->where('ClaimStatus', 'Open'), 'Submitted', 'claimant', 'app_claim_submit', null, 'app', (int) $empId, (int) $comId);
        $updated = DB::connection($expenseConn)
            ->table($this->claimsTable($yearId))
            ->where('CrBy', $empId)
            ->where('ClaimMonth', $month)
            ->where('ClaimYearId', $yearId)
            ->where('ClaimStatus', 'Open')
            ->update([
                'ClaimStatus' => 'Submitted',
                'SubmitDate' => now()->format('Y-m-d H:i:s'),
            ]);

        // Update monthexpensefinal
        DB::connection($expenseConn)
            ->table($this->monthFinalTable($yearId))
            ->updateOrInsert(
                ['EmployeeID' => $empId, 'Month' => $month, 'YearId' => $yearId],
                ['SubmitDate' => now()->format('Y-m-d H:i:s'), 'Status' => 'Submitted']
            );

        return $this->success(['updated' => $updated], 'Claims submitted successfully');
    }

    /**
     * Submit courier details.
     * Replicates: claim.php?value=Courier_Submit
     */
    public function courierSubmit(Request $request): JsonResponse
    {
        $empId = (int) $request->input('empid');
        $yearId = $this->getYearId($request);
        $comId = $this->getComId($request);
        $month = (int) $request->input('month');
        $postDate = $request->input('PostDate', '');
        $docNo = $request->input('DocateNo', '');
        $agency = $request->input('Agency', '');

        if ($empId <= 0 || $yearId <= 0 || $comId <= 0 || $month <= 0 || empty($postDate) || empty($docNo) || empty($agency)) {
            return $this->error('Parameter Missing!');
        }

        if (!$this->validateToken($request)) {
            return $this->error('Invalid Employee!');
        }

        $expenseConn = $this->getExpenseConnection($comId);

        // Tracker #20: actual dispatch date only (valid, not future, not before the claim month).
        $dateError = ClaimSubmissionValidator::first(app(ClaimSubmissionValidator::class)->courierDateForMonth($postDate, $empId, $month, $yearId, $expenseConn));
        if ($dateError !== null) {
            return $this->error($dateError);
        }
        $postDate = ClaimSubmissionValidator::parseDate($postDate) ?? $postDate;

        // Update monthexpensefinal with courier details
        DB::connection($expenseConn)
            ->table($this->monthFinalTable($yearId))
            ->where('EmployeeID', $empId)
            ->where('Month', $month)
            ->where('YearId', $yearId)
            ->update([
                'PostDate' => $postDate,
                'DocateNo' => $docNo,
                'Agency' => $agency,
            ]);

        return $this->success([], 'Courier details submitted successfully');
    }

    /**
     * Approve a claim (by approver).
     * Replicates: claim.php?value=Claim_approved
     */
    public function claimApproved(Request $request): JsonResponse
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

        $expenseConn = $this->getExpenseConnection($comId);

        // PAY-REMARK-01: a VNR cut below the verified amount needs the reason
        $cutClaim = DB::connection($expenseConn)->table($this->claimsTable($yearId))->where('ExpId', $expId)->first(['ExpId', 'CrBy', 'FilledTAmt', 'VerifyTAmt']);
        if ($cutClaim && ($cutMsg = \App\Services\Claims\StageCap::checkCut($expenseConn, (int) $yearId, $cutClaim, $amt, (string) $apprRemark, 'app_claim_approved', (int) $comId))) {
            return $this->error($cutMsg);
        }
        // B-01: the caller must be the claimant's approver (reporting chain / approval history)
        \App\Services\Claims\ClaimTransition::guard($expenseConn, (int) $yearId, (int) $expId, 'Approved', 'approver', 'app_claim_approved', $apprRemark ?: null, 'app', (int) $empId, (int) $comId);
        DB::connection($expenseConn)
            ->table($this->claimsTable($yearId))
            ->where('ExpId', $expId)
            ->update([
                'ApprovedAmt' => $amt,
                'ApprovedBy' => $empId,
                'ApprovedDate' => now()->format('Y-m-d H:i:s'),
                'ApprRemark' => $apprRemark,
                'ClaimStatus' => 'Approved',
            ]);

        return $this->success([], 'Claim approved successfully');
    }

    /**
     * Submit claims for reporting (by approver to next level).
     * Replicates: claim.php?value=Reporting_Claim_Submit
     */
    public function reportingClaimSubmit(Request $request): JsonResponse
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

        $expenseConn = $this->getExpenseConnection($comId);

        // Update all approved claims for this employee/month
        \App\Services\Claims\ClaimTransition::guard($expenseConn, (int) $yearId, fn ($q) => $q->where('CrBy', $crBy)->where('ClaimMonth', $month)->where('ClaimYearId', $yearId)->where('ClaimStatus', 'Approved'), 'Close', 'approver', 'app_reporting_submit', null, 'app', (int) $empId, (int) $comId);
        DB::connection($expenseConn)
            ->table($this->claimsTable($yearId))
            ->where('CrBy', $crBy)
            ->where('ClaimMonth', $month)
            ->where('ClaimYearId', $yearId)
            ->where('ClaimStatus', 'Approved')
            ->update([
                'ClaimStatus' => 'Close',
                'CloseDate' => now()->format('Y-m-d H:i:s'),
            ]);

        // Update monthexpensefinal
        DB::connection($expenseConn)
            ->table($this->monthFinalTable($yearId))
            ->where('EmployeeID', $crBy)
            ->where('Month', $month)
            ->where('YearId', $yearId)
            ->update([
                'ApprovedBy' => $empId,
                'ApprovedDate' => now()->format('Y-m-d H:i:s'),
                'Status' => 'Approved',
            ]);

        return $this->success([], 'Claims submitted to reporting successfully');
    }

    /**
     * Process media files for an expense record (S3 + fallback URLs).
     */
    private function processMediaFiles(object $record, int $yearId, string $connection): array
    {
        $userId = $record->CrBy;
        $images = [];
        $pdfs = [];

        $fileColumns = ['Img_1', 'Img_2', 'Img_3', 'pdf_1', 'pdf_2', 'opening_filepath', 'closing_filepath'];
        foreach ($fileColumns as $col) {
            if (!empty($record->$col)) {
                $ext = strtolower(pathinfo($record->$col, PATHINFO_EXTENSION));
                if ($ext === 'pdf') {
                    $pdfs[] = $record->$col;
                } else {
                    $images[] = $record->$col;
                }
            }
        }

        // Fallback to uploads table
        if (count($images) < 3 || count($pdfs) < 2) {
            $uploadFiles = DB::connection($connection)
                ->table("y{$yearId}_claimuploads")
                ->where('ExpId', $record->ExpId)
                ->orderBy('UploadSequence')
                ->limit(5)
                ->pluck('FileName')
                ->toArray();

            foreach ($uploadFiles as $file) {
                $ext = strtolower(pathinfo($file, PATHINFO_EXTENSION));
                if ($ext === 'pdf' && count($pdfs) < 2) {
                    $pdfs[] = $file;
                } elseif ($ext !== 'pdf' && count($images) < 3) {
                    $images[] = $file;
                }
            }
        }

        $images = array_slice(array_unique($images), 0, 3);
        $pdfs = array_slice(array_unique($pdfs), 0, 2);

        $resolveUrl = function ($fileName) use ($yearId, $userId) {
            if (empty($fileName)) return '';
            try {
                $s3Service = app(\App\Services\S3Service::class);
                $s3Path = "Expense/{$yearId}/{$userId}/{$fileName}";
                if ($s3Service->fileExists($s3Path)) {
                    return $s3Service->getFileViewUrl($s3Path);
                }
            } catch (\Exception $e) {
                // Fallback
            }
            $sanitized = preg_replace('/[^A-Za-z0-9\-\_\.]/', '_', basename($fileName));
            return "https://vnragro.com/File_Bkp/xeasyfile/{$yearId}/{$userId}/{$sanitized}";
        };

        return [
            'Img_1' => $resolveUrl($images[0] ?? ''),
            'Img_2' => $resolveUrl($images[1] ?? ''),
            'Img_3' => $resolveUrl($images[2] ?? ''),
            'pdf_1' => $resolveUrl($pdfs[0] ?? ''),
            'pdf_2' => $resolveUrl($pdfs[1] ?? ''),
            'opening_filepath' => !empty($record->opening_filepath) ? $resolveUrl($record->opening_filepath) : '',
            'closing_filepath' => !empty($record->closing_filepath) ? $resolveUrl($record->closing_filepath) : '',
        ];
    }
}
