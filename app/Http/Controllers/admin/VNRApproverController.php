<?php

namespace App\Http\Controllers\admin;

use App\Http\Controllers\Controller;
use App\Services\CentralNotificationService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;

class VNRApproverController extends Controller
{
    private CentralNotificationService $centralNotification;

    public function __construct()
    {
        $this->centralNotification = new CentralNotificationService();
    }

    private function baseUrl(): string
    {
        $base      = \App\Support\LegacyApi::baseUrl();
        $companyId = session('company_id', 1);
        return "{$base}/{$companyId}/common.php";
    }

    private function claimUrl(): string
    {
        $base      = \App\Support\LegacyApi::baseUrl();
        $companyId = session('company_id', 1);
        return "{$base}/{$companyId}/claim.php";
    }

    // ── Shared session helpers ──────────────────────────────────────────────

    private function sessionData(): array
    {
        return [
            'employeeId' => session('employee_id'),
            'yearId' => session('year_id') ?: 12,
            'companyId' => session('company_id') ?: 1,
            'tokenId' => session('token_id') ?: config('app.api_token_id'),
        ];
    }

    private function baseParams(): array
    {
        $s = $this->sessionData();

        return [
            'empid' => $s['employeeId'],
            'YearId' => $s['yearId'],
            'ComId' => $s['companyId'],
            'tokenid' => $s['tokenId'],
        ];
    }

    private function get(string $url, array $params = [], int $timeout = 30): array
    {
        // APPR-LOAD-01: answered in this process when the API is this app (no HTTP call to itself)
        if (($own = \App\Support\LegacyApi::inProcess($url, $params, 'GET')) !== null) {
            return $own;
        }
        try {
            $response = \App\Support\LegacyApi::http($timeout)
                ->get($url, $params);

            return $response->json() ?? [];
        } catch (\Exception $e) {
            return ['Code' => '500', 'msg' => 'API Error: '.$e->getMessage()];
        }
    }

    private function postForm(string $url, array $params = [], int $timeout = 30): array
    {
        if (($own = \App\Support\LegacyApi::inProcess($url, $params, 'POST')) !== null) {
            return $own;
        }
        try {
            $response = \App\Support\LegacyApi::http($timeout)
                ->asForm()
                ->post($url, $params);

            return $response->json() ?? [];
        } catch (\Exception $e) {
            return ['Code' => '500', 'msg' => 'API Error: '.$e->getMessage()];
        }
    }

    // ── Pending ─────────────────────────────────────────────────────────────

    public function getApproverPendingList()
    {
        $params = array_merge($this->baseParams(), [
            'value' => 'Team_Pending_Claimlist',
        ]);

        // Try GET first (standard API query for common.php)
        $data = $this->get($this->baseUrl(), $params);

        // Fallback to POST if GET returned empty/failed
        if (empty($data['Pending_list']) || !is_array($data['Pending_list'])) {
            $postData = $this->postForm($this->baseUrl(), $params);
            if (!empty($postData['Pending_list']) && is_array($postData['Pending_list'])) {
                $data = $postData;
            }
        }

        // Database fallback if external API returned no pending claims
        if (empty($data['Pending_list']) || !is_array($data['Pending_list'])) {
            try {
                $employeeId = session('employee_id');
                $yearId = session('year_id') ?: 12;
                $mefTable = "y{$yearId}_monthexpensefinal";
                $conn = session('expense_connection') ?: \App\Helpers\DatabaseSwitcher::getExpenseConnection();
                $hrimsDb = \App\Helpers\DatabaseSwitcher::getHrimsDatabaseName();

                $teamMembers = DB::table($hrimsDb . '.hrm_employee_general as eg')
                    ->join($hrimsDb . '.hrm_employee as e', 'eg.EmployeeID', '=', 'e.EmployeeID')
                    ->where('eg.RepEmployeeID', $employeeId)
                    ->where('e.EmpStatus', 'A')
                    ->select('e.EmployeeID', 'e.EmpCode', DB::raw("CONCAT(e.Fname, ' ', COALESCE(e.Lname, '')) as EmpName"))
                    ->get();

                if ($teamMembers->isNotEmpty()) {
                    $teamMemberIds = $teamMembers->pluck('EmployeeID')->toArray();
                    $empMap = $teamMembers->keyBy('EmployeeID');

                    $localPending = DB::connection($conn)->table($mefTable)
                        ->whereIn('EmployeeID', $teamMemberIds)
                        ->where(function ($q) {
                            $q->where('Status', 'Closed')
                                ->orWhere(function ($q2) {
                                    $q2->whereNotNull('DateOfSubmit')->where('DateOfSubmit', '!=', '0000-00-00');
                                });
                        })
                        ->where(function ($q) {
                            $q->whereNull('Approved_Date')->orWhere('Approved_Date', '0000-00-00');
                        })
                        ->when(\App\Support\ApproverAfterVerifier::applies((int) (session('company_id') ?: 1)), fn ($q) => $q->whereRaw(\App\Support\ApproverAfterVerifier::verifiedSql())) // APPR-VER-01
                        ->orderBy('Month', 'desc')
                        ->get();

                    if ($localPending->isNotEmpty()) {
                        $list = [];
                        foreach ($localPending as $row) {
                            $emp = $empMap->get($row->EmployeeID);
                            $list[] = [
                                'EmployeeID'          => $row->EmployeeID,
                                'EmpCode'             => $emp ? $emp->EmpCode : '',
                                'EmpName'             => $emp ? $emp->EmpName : 'N/A',
                                'Month'               => (string)$row->Month,
                                'YearId'              => (string)($row->YearId ?? $yearId),
                                'Total_Claim'         => (string)($row->Total_Claim ?? '0'),
                                'Claim_Amount'        => (string)($row->Claim_Amount ?? '0'),
                                'Verified_Amount'     => (string)($row->Verified_Amount ?? '0'),
                                'Approved_Amount'     => (string)($row->Approved_Amount ?? '0'),
                                'RecevingDate'        => $row->RecevingDate ?? '',
                                'Verified_Date'       => $row->Verified_Date ?? '',
                                'DateOfSubmit'        => $row->DateOfSubmit ?? '',
                                'verification_status' => ((float)($row->Verified_Amount ?? 0) > 0 || (!empty($row->Verified_Date) && \App\Support\ZeroDate::has($row->Verified_Date))) ? 'Verified' : 'Pending',
                            ];
                        }
                        $data['Pending_list'] = $list;
                        $data['Code'] = '300';
                    }
                }
            } catch (\Throwable $e) {
                // Ignore fallback error
            }
        }

        return response()->json($data);
    }

    public function getApproverPendingDay(Request $request)
    {
        ini_set('max_execution_time', 300);
        // APPR-VER-01: the reporting manager opens a month after the verifier has verified it
        if ($block = $this->blockedBeforeVerification((int) $request->input('empid'), (int) $request->input('month'))) {
            return $block;
        }
        $data = $this->get($this->baseUrl(), array_merge($this->baseParams(), [
            'value' => 'Team_Pending_Claimlist_Day',
            'empid' => $request->input('empid'),
            'Month' => $request->input('month'),
        ]), 180);

        return response()->json($data);
    }

    // ── Approved ────────────────────────────────────────────────────────────

    public function getApproverApprovedList(Request $request)
    {
        $member = (string) $request->input('member', $request->input('memid', ''));
        $month = (string) $request->input('month', '');

        // Mirrors Flutter VnrApprovedClaimsApiService::fetchFilterApprovedList.
        if ($month !== '' && $member === '') {
            $apiValue = 'ApprovedClaimsByMonth';
        } elseif ($month === '' && $member !== '') {
            $apiValue = 'ApprovedClaimsByMem';
        } elseif ($month !== '' && $member !== '') {
            $apiValue = 'ApprovedClaimsByMonthMem';
        } else {
            $apiValue = 'Team_Approved_Claimlist';
        }

        $params = array_merge($this->baseParams(), ['value' => $apiValue]);

        if ($member !== '') {
            $params['memid'] = $member;
        }
        if ($month !== '') {
            $params['month'] = $month;
        }

        $data = $this->postForm($this->baseUrl(), $params);

        return response()->json($data);
    }

    public function getApproverApprovedDay(Request $request)
    {
        $data = $this->get($this->baseUrl(), array_merge($this->baseParams(), [
            'value' => 'Team_Approved_Claimlist_Day',
            'empid' => $request->input('empid'),
            'YearId' => $request->input('yearid'),
            'ComId' => $request->input('comid'),
            'Month' => $request->input('month'),
        ]));

        return response()->json($data);
    }

    // ── Approve / Submit ────────────────────────────────────────────────────

    public function approveClaimByApprover(Request $request)
    {
        try {
            $request->validate([
                'exp_id' => 'required',
                'employee_id' => 'required',
                'amount' => 'required|numeric|min:0',
                'month' => 'required|integer',
            ]);

            $s = $this->sessionData();
            // APPR-CAP-02: never above the claimed amount, checked here before the claim API is called
            if ($capMsg = \App\Services\Claims\StageCap::webApproveGuard(session('expense_connection'), (int) $request->input('year_id', $request->input('yearid', $s['yearId'])), (int) $request->input('exp_id'), $request->input('amount'), (string) $request->input('remark', ''), 'web_approve')) {
                return response()->json(['Code' => '100', 'msg' => $capMsg, 'message' => $capMsg], 422);
            }
            $data = $this->postForm($this->claimUrl(), [
                'value' => 'Claim_approved',
                'empid' => $s['employeeId'],
                'YearId' => $request->input('year_id', $request->input('yearid', $s['yearId'])),
                'ComId' => $s['companyId'],
                'tokenid' => $s['tokenId'],
                'ExpId' => $request->input('exp_id'),
                'EmployeeId' => $request->input('employee_id'),
                'Amt' => $request->input('amount'),
                'ApprRemark' => $request->input('remark', ''),
                'Month' => $request->input('month'),
            ]);

            // Notify Central Notification Service
            try {
                $yearId = (int) ($request->input('year_id', $request->input('yearid', $s['yearId'])));
                $empId = $request->input('employee_id');
                $month = $request->input('month');
                $mfTable = 'y' . $yearId . '_monthexpensefinal';
                $mfRecord = \Illuminate\Support\Facades\DB::connection(session('expense_connection'))->table($mfTable)
                    ->where('EmployeeID', $empId)->where('Month', $month)->where('YearId', $yearId)->first(['id']);
                $refId = $mfRecord ? (string) $mfRecord->id : "{$empId}{$month}";
                $this->centralNotification->approveAction(
                    'expense_claim',
                    $refId,
                    $request->input('remark', 'Approved'),
                    ['approved_by' => $s['employeeId'], 'exp_id' => $request->input('exp_id'), 'amount' => $request->input('amount')]
                );
            } catch (\Throwable $e) {
                \Log::warning('Central Notification (approve claim) failed: ' . $e->getMessage());
            }

            return response()->json($data);

        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'Code' => '400',
                'msg' => 'Validation Error: '.implode(', ', collect($e->errors())->flatten()->toArray()),
            ], 400);
        } catch (\Exception $e) {
            return response()->json(['Code' => '500', 'msg' => $e->getMessage()], 500);
        }
    }

    public function submitClaimByApprover(Request $request)
    {
        try {
            $request->validate([
                'emp_id' => 'required',
                'month' => 'required|integer|min:1|max:12',
            ]);

            $s = $this->sessionData();
            $yearId = (int) $request->input('year_id', $request->input('yearid', $s['yearId']));
            $empId = (int) $request->input('emp_id');
            $month = (int) $request->input('month');

            // Server-side check: month must be verified by verifier before approver can submit
            $mfTable = 'y' . $yearId . '_monthexpensefinal';
            $mfRecord = \Illuminate\Support\Facades\DB::connection(session('expense_connection', 'expense'))->table($mfTable)
                ->where('EmployeeID', $empId)->where('Month', $month)->where('YearId', $yearId)->first();

            if ($mfRecord) {
                $isVerified = ((float)($mfRecord->Verified_Amount ?? 0) > 0) ||
                              (!empty($mfRecord->Verified_Date) && \App\Support\ZeroDate::has($mfRecord->Verified_Date) && $mfRecord->Verified_Date !== '1970-01-01');
                if (!$isVerified) {
                    return response()->json([
                        'Code' => '100',
                        'msg' => 'Cannot submit: Claims for this month are not yet verified by the verifier.',
                        'message' => 'Cannot submit: Claims for this month are not yet verified by the verifier.'
                    ]);
                }
            }

            // APPR-CAP-02: no final submit while an approved (or verified) amount is above the claimed amount
            try {
                $over = \App\Services\Claims\StageCap::monthOverClaimed(session('expense_connection', 'expense'), $yearId, $empId, $month);
            } catch (\Throwable $e) {
                $over = [];
            }
            if ($over) {
                $m = 'Cannot submit: the approved amount is more than the claimed amount for '.implode('; ', $over).'. Correct the approved amount (at most the claimed amount) and approve again.';

                return response()->json(['Code' => '100', 'msg' => $m, 'message' => $m]);
            }

            $data = $this->postForm($this->claimUrl(), [
                'value' => 'Reporting_Claim_Submit',
                'empid' => $s['employeeId'],
                'CrBy' => $empId,
                'YearId' => $yearId,
                'ComId' => $s['companyId'],
                'tokenid' => $s['tokenId'],
                'Month' => $month,
            ]);

            if (isset($data['Code']) && $data['Code'] === '300') {
                // Notify Central Notification Service - Raise action for claim submission
                try {
                    $yearId = (int) ($request->input('year_id', $request->input('yearid', $s['yearId'])));
                    $empId = $request->input('emp_id');
                    $month = $request->input('month');
                    $mfTable = 'y' . $yearId . '_monthexpensefinal';
                    $mfRecord = \Illuminate\Support\Facades\DB::connection(session('expense_connection'))->table($mfTable)
                        ->where('EmployeeID', $empId)->where('Month', $month)->where('YearId', $yearId)->first(['id']);
                    $refId = $mfRecord ? (string) $mfRecord->id : "{$empId}{$month}";
                    $this->centralNotification->raiseExpenseClaim(
                        $refId,
                        $empId,
                        $empId, // applicant name (employee code as fallback)
                        $s['employeeId'],
                        $s['employeeId'], // approver name (employee code as fallback)
                        1,
                        "Expense Claim Submitted - Month {$month}",
                        "Expense claim submitted for approval for month {$month}",
                        ['month' => $month, 'year_id' => $yearId]
                    );
                } catch (\Throwable $e) {
                    \Log::warning('Central Notification (raise claim) failed: ' . $e->getMessage());
                }

                return response()->json($data);
            }

            return response()->json(['Code' => '400', 'msg' => $data['msg'] ?? 'Failed to submit claim'], 400);

        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'Code' => '400',
                'msg' => 'Validation Error: '.implode(', ', collect($e->errors())->flatten()->toArray()),
            ], 400);
        } catch (\Exception $e) {
            return response()->json(['Code' => '500', 'msg' => $e->getMessage()], 500);
        }
    }

    // ── Expense Detail (Flutter: fetchExpenseDetails → ExDetail) ────────────

    public function getExpenseDetail(Request $request)
    {
        // APPR-VER-01: a team member's claim opens for the reporting manager after verification
        $expId = (int) $request->input('exp_id');
        $yr = (int) session('year_id');
        $conn = session('expense_connection') ?: 'expense';
        $row = $expId > 0 && $yr > 0 ? \Illuminate\Support\Facades\DB::connection($conn)->table("y{$yr}_expenseclaims")->where('ExpId', $expId)->first(['CrBy', 'ClaimMonth']) : null;
        if ($row && ($block = $this->blockedBeforeVerification((int) $row->CrBy, (int) $row->ClaimMonth))) {
            return $block;
        }
        $s = $this->sessionData();
        // Flutter passes the claimant's empId, not the approver's session empId
        $empId = $request->input('emp_id', $s['employeeId']);
        $data = $this->get($this->baseUrl(), [
            'value'   => 'ExDetail',
            'empid'   => $empId,
            'YearId'  => $s['yearId'],
            'ComId'   => $s['companyId'],
            'tokenid' => $s['tokenId'],
            'expid'   => $request->input('exp_id'),
        ]);
        return response()->json($data);
    }

    // ── Head-wise Approve (Flutter: Act_Claim_approved for cgId=7) ───────────

    public function approveClaimHeadWise(Request $request)
    {
        try {
            $request->validate([
                'exp_id'       => 'required',
                'month'        => 'required|integer',
                'head_amounts' => 'required|array',
            ]);

            $s = $this->sessionData();
            // APPR-CAP-02: the heads together never above the claimed amount
            $headTotal = array_sum(array_map(fn ($v) => is_array($v) ? (float) ($v['app_amount'] ?? $v['amount'] ?? 0) : (float) $v, (array) $request->input('head_amounts', [])));
            if ($capMsg = \App\Services\Claims\StageCap::webApproveGuard(session('expense_connection'), (int) $s['yearId'], (int) $request->input('exp_id'), $headTotal, (string) $request->input('remark', ''), 'web_approve_heads')) {
                return response()->json(['Code' => '100', 'msg' => $capMsg, 'message' => $capMsg], 422);
            }

            $response = \App\Support\LegacyApi::http()
                ->withHeaders(['Content-Type' => 'application/json'])
                ->post(
                    $this->claimUrl() . '?' . http_build_query([
                        'value'      => 'Act_Claim_approved',
                        'ComId'      => $s['companyId'],
                        'tokenid'    => $s['tokenId'],
                        'YearId'     => $s['yearId'],
                        'empid'      => $s['employeeId'],
                        'ExpId'      => $request->input('exp_id'),
                        'ApprRemark' => $request->input('remark', ''),
                        'Month'      => $request->input('month'),
                    ]),
                    $request->input('head_amounts')
                );

            return response()->json($response->json() ?? []);

        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'Code' => '400',
                'msg'  => 'Validation Error: ' . implode(', ', collect($e->errors())->flatten()->toArray()),
            ], 400);
        } catch (\Exception $e) {
            return response()->json(['Code' => '500', 'msg' => $e->getMessage()], 500);
        }
    }

    // ── Team Members / Expenses ─────────────────────────────────────────────

    public function getTeamMembersList()
    {
        $data = $this->get($this->baseUrl(), array_merge($this->baseParams(), [
            'value' => 'TeamMemberAppraiser',
        ]));

        return response()->json($data);
    }

    public function getAllTeamExpenses(Request $request)
    {
        $s = $this->sessionData();

        $params = array_merge($this->baseParams(), [
            'value' => 'AllTeamExp',
            'empid' => $s['employeeId'],
        ]);

        // Support filtering by member or claim type (same as Flutter)
        $memId   = $request->input('member');
        $claimId = $request->input('claim_id');

        if ($claimId && !$memId) {
            $params['value']  = 'TeamMemberExpensesById';
            $params['expcid'] = $claimId;
        } elseif (!$claimId && $memId) {
            $params['value']    = 'TeamMemberExpenses';
            $params['memempid'] = $memId;
        } elseif ($claimId && $memId) {
            $params['value']    = 'TeamExpensesByIdAndMember';
            $params['expcid']   = $claimId;
            $params['memempid'] = $memId;
        }

        $data = $this->postForm($this->baseUrl(), $params);

        // Return the raw API response — frontend handles rendering
        return response()->json($data);
    }

    public function getMemberExpenses(Request $request)
    {
        $data = $this->get($this->baseUrl(), array_merge($this->baseParams(), [
            'value' => 'TeamMemberExpensesById',
            'expcid' => $request->input('claim_id'),
        ]));

        return response()->json($data);
    }

    public function getMemberExpensesByClaim(Request $request)
    {
        $data = $this->get($this->baseUrl(), array_merge($this->baseParams(), [
            'value' => 'TeamExpensesByIdAndMember',
            'expcid' => $request->input('claim_id'),
            'memempid' => $request->input('member_id'),
        ]));

        return response()->json($data);
    }

    public function getAllTeamMemberExpensesByClaimType(Request $request)
    {
        $claimId = $request->input('claim_id');
        $month   = $request->input('month');   // optional month filter (1-12)

        if (!$claimId) {
            return response()->json(['error' => 'claim_id is required'], 400);
        }

        $s = $this->sessionData();

        // Build params — always pass the logged-in empid so the API scopes to the approver's team
        $params = array_merge($this->baseParams(), [
            'value'  => 'TeamMemberExpensesById',
            'expcid' => $claimId,
            'empid'  => $s['employeeId'],   // logged-in approver's employee ID
        ]);

        // Pass month filter to the API if provided
        if ($month) {
            $params['Month'] = $month;
        }

        $data = $this->get($this->baseUrl(), $params);

        // Group by employee — try multiple possible response keys
        $employees = [];
        $records = $data['Team_list']
            ?? $data['Expense_list']
            ?? $data['TeamExpList']
            ?? $data['data']
            ?? $data['expenses']
            ?? $data['records']
            ?? [];

        if (!is_array($records)) {
            $records = [];
        }

        // PHP-side month filter fallback (if API doesn't support Month param)
        if ($month && is_array($records)) {
            $records = array_filter($records, function ($r) use ($month) {
                $recMonth = $r['ClaimMonth'] ?? $r['Month'] ?? $r['month'] ?? $r['ExpMonth'] ?? null;
                return $recMonth !== null && (int)$recMonth === (int)$month;
            });
            $records = array_values($records);
        }

        foreach ($records as $record) {
            $empId   = $record['CrBy']        ?? $record['EmployeeID'] ?? $record['EmpId'] ?? 'Unknown';
            $empName = $record['EmpName']      ?? $record['Ename']      ?? $record['FullName'] ?? $record['Name'] ?? 'Unknown';
            $empCode = $record['EmpCode']      ?? $record['empcode']    ?? '';

            if (!isset($employees[$empId])) {
                $employees[$empId] = [
                    'EmployeeID'   => $empId,
                    'EmpName'      => $empName,
                    'EmpCode'      => $empCode,
                    'records'      => [],
                    'total_amount' => 0,
                ];
            }

            $amount = (float) ($record['TotAmt'] ?? $record['FilledTAmt'] ?? $record['Amount'] ?? $record['Claim_Amount'] ?? 0);
            $employees[$empId]['records'][]    = $record;
            $employees[$empId]['total_amount'] += $amount;
        }

        return response()->json([
            'success'       => true,
            'employees'     => array_values($employees),
            'total_records' => count($records),
            'raw_keys'      => array_keys($data),
            'filtered_month'=> $month,
        ]);
    }

    /** APPR-VER-01: the response refusing an unverified month to the reporting manager, or null. */
    private function blockedBeforeVerification(int $memberId, int $month)
    {
        $companyId = (int) (session('company_id') ?: 1);
        $viewer = (int) session('employee_id');
        if ($memberId <= 0 || $month <= 0 || \App\Support\ApproverAfterVerifier::mayOpen($companyId, session('expense_connection') ?: 'expense', (int) session('year_id'), $viewer, $memberId, $month, auth()->user())) {
            return null;
        }
        $msg = \App\Support\ApproverAfterVerifier::MESSAGE;
        return response()->json(['Code' => '100', 'msg' => $msg, 'message' => $msg]);
    }
}
