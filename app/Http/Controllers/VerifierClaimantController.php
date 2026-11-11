<?php

namespace App\Http\Controllers;

use App\Services\ClaimRules\ClaimSubmissionValidator;
use App\Support\LegacyApi;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Session;

class VerifierClaimantController extends Controller
{
    // Main View for Verifier
    public function index(Request $request)
    {
        $status = $request->get('status', 'drafts');
       $employeeId = $request->get('ei') ?? session('employee_id');
        $yearId = (int) ($request->get('YearId') ?? session('FYearId', 1));
        $companyId = $request->get('CompanyId') ?? session('CompanyId', 1);
        $tokenId = \App\Support\LegacyApi::webToken();
        $fYear = session('FYear', date('Y') . '-' . (date('Y') + 1));

        // Get counts from API
        $draftCount = $this->getCount('Draftlist', $employeeId, $yearId, $companyId, $tokenId, 'Draft_list');
        $openedCount = $this->getCount('Openlist', $employeeId, $yearId, $companyId, $tokenId, 'Open_list');
        $closedCount = $this->getCount('Closelist_All_Month', $employeeId, $yearId, $companyId, $tokenId, 'Close_list');

        return view('employee.claimant', [
            'status' => $status,
            'employeeId' => $employeeId,
            'yearId' => $yearId,
            'companyId' => $companyId,
            'fYear' => $fYear,
            'tokenId' => $tokenId,
            'draftCount' => $draftCount,
            'openedCount' => $openedCount,
            'closedCount' => $closedCount,
            'isVerifierView' => false,  // false = render as full standalone page with layout
        ]);
    }

    // Draft List API
    public function draftList(Request $request)
    {
        $employeeId = $request->get('ei') ?? session('employee_id');
        $yearId = (int) ($request->get('YearId') ?? session('FYearId', 1));
        $companyId = $request->get('CompanyId') ?? session('CompanyId', 1);
        $tokenId = \App\Support\LegacyApi::webToken();

        $url = \App\Support\LegacyApi::url('common.php') . "?value=Draftlist&empid={$employeeId}&YearId={$yearId}&ComId={$companyId}&tokenid={$tokenId}";

        try {
            $response = \App\Support\LegacyApi::http(30)->get($url);
            return response()->json($response->json());
        } catch (\Exception $e) {
            return response()->json(['Code' => '500', 'msg' => 'API Error: ' . $e->getMessage(), 'Draft_list' => []]);
        }
    }

    // Open List API
    public function openList(Request $request)
    {
        $employeeId = $request->get('ei') ?? session('employee_id');
        $yearId = (int) ($request->get('YearId') ?? session('FYearId', 1));
        $companyId = $request->get('CompanyId') ?? session('CompanyId', 1);
        $tokenId = \App\Support\LegacyApi::webToken();

        $url = \App\Support\LegacyApi::url('common.php') . "?value=Openlist&empid={$employeeId}&YearId={$yearId}&ComId={$companyId}&tokenid={$tokenId}";

        try {
            $response = \App\Support\LegacyApi::http(30)->get($url);
            return response()->json($response->json());
        } catch (\Exception $e) {
            return response()->json(['Code' => '500', 'msg' => 'API Error: ' . $e->getMessage(), 'Open_list' => []]);
        }
    }

    // Close List API
    public function closeList(Request $request)
    {
        $employeeId = $request->get('ei') ?? session('employee_id');
        $yearId = (int) ($request->get('YearId') ?? session('FYearId', 1));
        $companyId = $request->get('CompanyId') ?? session('CompanyId', 1);
        $tokenId = \App\Support\LegacyApi::webToken();

        $url = \App\Support\LegacyApi::url('common.php') . "?value=Closelist_All_Month&empid={$employeeId}&YearId={$yearId}&ComId={$companyId}&tokenid={$tokenId}";

        try {
            $response = \App\Support\LegacyApi::http(30)->get($url);
            return response()->json($response->json());
        } catch (\Exception $e) {
            return response()->json(['Code' => '500', 'msg' => 'API Error: ' . $e->getMessage(), 'Close_list' => []]);
        }
    }

    // Open Day Details
    public function openDayDetails(Request $request)
    {
        $employeeId = $request->get('ei') ?? session('employee_id');
        $yearId = (int) ($request->get('YearId') ?? session('FYearId', 1));
        $companyId = $request->get('CompanyId') ?? session('CompanyId', 1);
        $tokenId = session('token_id');
        $month = $request->month;

        $url = \App\Support\LegacyApi::url('common.php') . "?value=Openlist_Day&empid={$employeeId}&YearId={$yearId}&ComId={$companyId}&tokenid={$tokenId}&Month={$month}";

        try {
            $response = \App\Support\LegacyApi::http(30)->get($url);
            return response()->json($response->json());
        } catch (\Exception $e) {
            return response()->json(['Code' => '500', 'msg' => 'API Error', 'DayWiseDetails' => []]);
        }
    }

    // Close Day Details
    public function closeDayDetails(Request $request)
    {
        $employeeId = $request->get('ei') ?? session('employee_id');
        $yearId = (int) ($request->get('YearId') ?? session('FYearId', 1));
        $companyId = $request->get('CompanyId') ?? session('CompanyId', 1);
        $tokenId = session('token_id');
        $month = $request->month;

        $url = \App\Support\LegacyApi::url('common.php') . "?value=Closelist_Day&empid={$employeeId}&Month={$month}&YearId={$yearId}&ComId={$companyId}&tokenid={$tokenId}";

        try {
            $response = \App\Support\LegacyApi::http(30)->get($url);
            return response()->json($response->json());
        } catch (\Exception $e) {
            return response()->json(['Code' => '500', 'msg' => 'API Error', 'DayWiseDetails' => []]);
        }
    }

    // Add Remark (for verifier)
    public function addRemark(Request $request)
    {
        $employeeId = $request->get('ei') ?? session('employee_id');
        $yearId = (int) ($request->get('YearId') ?? session('FYearId', 1));
        $companyId = $request->get('CompanyId') ?? session('CompanyId', 1);
        $tokenId = session('token_id');
        $expId = $request->exp_id;
        $remark = $request->remark;
        $empRole = 'V';

        $url = \App\Support\LegacyApi::url('common.php') . "?value=AddRemark&empid={$employeeId}&YearId={$yearId}&tokenid={$tokenId}&remark=" . urlencode($remark) . "&emprole={$empRole}&expid={$expId}&ComId={$companyId}";

        try {
            $response = \App\Support\LegacyApi::http(30)->get($url);
            return response()->json($response->json());
        } catch (\Exception $e) {
            return response()->json(['Code' => '500', 'msg' => 'Failed to add remark']);
        }
    }

    // Delete Draft
    public function deleteDraft(Request $request)
    {
        $employeeId = $request->get('ei') ?? session('employee_id');
        $yearId = (int) ($request->get('YearId') ?? session('FYearId', 1));
        $companyId = $request->get('CompanyId') ?? session('CompanyId', 1);
        $tokenId = session('token_id');
        $expId = $request->exp_id;

        $url = \App\Support\LegacyApi::url('common.php') . "?value=DeleteDraft&empid={$employeeId}&YearId={$yearId}&ComId={$companyId}&tokenid={$tokenId}&expid={$expId}";

        try {
            $response = \App\Support\LegacyApi::http(30)->get($url);
            return response()->json($response->json());
        } catch (\Exception $e) {
            return response()->json(['Code' => '500', 'msg' => 'Failed to delete draft']);
        }
    }

    // Verify Claim
    public function verifyClaim(Request $request)
    {
        $employeeId = $request->get('ei') ?? session('employee_id');
        $yearId = (int) ($request->get('YearId') ?? session('FYearId', 1));
        $companyId = $request->get('CompanyId') ?? session('CompanyId', 1);
        $tokenId = session('token_id');
        $expId = $request->exp_id;

        $url = \App\Support\LegacyApi::url('claim.php') . "?value=Claim_Okay&empid={$employeeId}&YearId={$yearId}&ComId={$companyId}&ExpId={$expId}&tokenid={$tokenId}";

        try {
            $response = \App\Support\LegacyApi::http(30)->get($url);
            return response()->json($response->json());
        } catch (\Exception $e) {
            return response()->json(['status' => 'error', 'message' => 'Verification failed']);
        }
    }

    // Deny Claim
    public function denyClaim(Request $request)
    {
        $employeeId = $request->get('ei') ?? session('employee_id');
        $yearId = (int) ($request->get('YearId') ?? session('FYearId', 1));
        $companyId = $request->get('CompanyId') ?? session('CompanyId', 1);
        $tokenId = session('token_id');
        $expId = $request->exp_id;
        $remark = $request->remark;

        $url = \App\Support\LegacyApi::url('claim.php') . "?value=Claim_Deny&empid={$employeeId}&YearId={$yearId}&ComId={$companyId}&ExpId={$expId}&remark=" . urlencode($remark) . "&tokenid={$tokenId}";

        try {
            $response = \App\Support\LegacyApi::http(30)->get($url);
            return response()->json($response->json());
        } catch (\Exception $e) {
            return response()->json(['status' => 'error', 'message' => 'Failed to deny claim']);
        }
    }

    // Submit Month
    public function submitMonth(Request $request)
    {
        $employeeId = $request->get('ei') ?? session('employee_id');
        $yearId = (int) ($request->get('YearId') ?? session('FYearId', 1));
        $companyId = $request->get('CompanyId') ?? session('CompanyId', 1);
        $tokenId = session('token_id');
        $month = $request->month;

        $url = \App\Support\LegacyApi::url('common.php') . "?value=SubmitMonth&empid={$employeeId}&YearId={$yearId}&ComId={$companyId}&tokenid={$tokenId}&Month={$month}";

        try {
            $response = \App\Support\LegacyApi::http(30)->get($url);
            return response()->json($response->json());
        } catch (\Exception $e) {
            return response()->json(['Code' => '500', 'msg' => 'Failed to submit month']);
        }
    }

    // Update Courier Details
    public function updateCourier(Request $request)
    {
        $employeeId = $request->get('ei') ?? session('employee_id');
        $yearId = (int) ($request->get('YearId') ?? session('FYearId', 1));
        $companyId = $request->get('CompanyId') ?? session('CompanyId', 1);
        $tokenId = session('token_id');
        $month = $request->month;
        $postDate = $request->post_date;
        $docketNo = $request->docket_no;
        $agency = $request->agency;

        $dateError = ClaimSubmissionValidator::first(app(ClaimSubmissionValidator::class)->courierDateForMonth($postDate, (int) $employeeId, (int) $month, (int) $yearId));
        if ($dateError !== null) {
            return response()->json(['Code' => '100', 'success' => false, 'msg' => $dateError, 'message' => $dateError], 422);
        }
        $query = ['value' => 'UpdateCourierDetails', 'empid' => $employeeId, 'YearId' => $yearId, 'ComId' => $companyId, 'tokenid' => $tokenId, 'Month' => $month, 'PostDate' => $postDate, 'DocateNo' => $docketNo, 'Agency' => $agency];

        try {
            $response = LegacyApi::http(30)->get(LegacyApi::url('common.php'), $query);
            return response()->json($response->json());
        } catch (\Exception $e) {
            return response()->json(['Code' => '500', 'msg' => 'Failed to update courier']);
        }
    }

    // Get Courier Details
    public function getCourierDetails(Request $request)
    {
        $employeeId = $request->get('ei') ?? session('employee_id');
        $yearId = (int) ($request->get('YearId') ?? session('FYearId', 1));
        $companyId = $request->get('CompanyId') ?? session('CompanyId', 1);
        $tokenId = session('token_id');
        $month = $request->month;

        $url = \App\Support\LegacyApi::url('common.php') . "?value=CourierDetails&empid={$employeeId}&YearId={$yearId}&ComId={$companyId}&tokenid={$tokenId}&Month={$month}";

        try {
            $response = \App\Support\LegacyApi::http(30)->get($url);
            return response()->json($response->json());
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => 'Failed to load courier details']);
        }
    }

    // Running KM Details
    public function runningKmDetails(Request $request)
    {
        $employeeId = $request->get('ei') ?? session('employee_id');
        $yearId = (int) ($request->get('YearId') ?? session('FYearId', 1));
        $companyId = $request->get('CompanyId') ?? session('CompanyId', 1);
        $month = $request->month;
        $token = \App\Support\LegacyApi::webToken(); // LV-S11: no hard-coded device token

        $url = \App\Support\LegacyApi::url('common.php') . "?value=VehicleRunning&empid={$employeeId}&YearId={$yearId}&ComId={$companyId}&month={$month}&tokenid={$token}";

        try {
            $response = \App\Support\LegacyApi::http(30)->get($url);
            return response()->json($response->json());
        } catch (\Exception $e) {
            return response()->json(['status' => 'error', 'message' => 'Failed to load running KM data']);
        }
    }

    // Show Claim Details (Modal iframe)
    public function showClaim(Request $request)
    {
        $expId = $request->expid;
        $employeeId = $request->get('ei') ?? session('employee_id');
        $yearId = (int) ($request->get('YearId') ?? session('FYearId', 1));
        $companyId = $request->get('CompanyId') ?? session('CompanyId', 1);
        $tokenId = session('token_id');

        return view('verifier.showclaim', [
            'expId' => $expId,
            'employeeId' => $employeeId,
            'yearId' => $yearId,
            'companyId' => $companyId,
            'tokenId' => $tokenId,
        ]);
    }

    // Month Report (Print View)
    public function monthReport(Request $request)
    {
        $employeeId = $request->e;
        $month = $request->m;
        $yearId = $request->y;
        $type = $request->n;
        $status = $request->nn;
        $tokenId = session('token_id');
        $companyId = session('CompanyId', 1);

        return view('verifier.monthreport', [
            'employeeId' => $employeeId,
            'month' => $month,
            'yearId' => $yearId,
            'type' => $type,
            'status' => $status,
            'tokenId' => $tokenId,
            'companyId' => $companyId,
        ]);
    }

    // Get Courier Details (Claimant's own closed month)
    public function getCourierDetail(Request $request)
    {
        $employeeId = $request->get('empid') ?? $request->get('ei') ?? session('employee_id');
        $yearId     = (int) ($request->get('YearId') ?? session('FYearId', 1));
        $companyId  = $request->get('CompanyId') ?? session('CompanyId', 1);
        $tokenId    = session('token_id');
        $month      = $request->month;

        $url = \App\Support\LegacyApi::url('common.php') . "?value=CourierDetails&empid={$employeeId}&YearId={$yearId}&ComId={$companyId}&tokenid={$tokenId}&Month={$month}";

        try {
            $response = \App\Support\LegacyApi::http(30)->get($url);
            $data = $response->json();

            // Normalise response to a consistent shape for the claimant view
            if (isset($data['CourierDetails']) && count($data['CourierDetails']) > 0) {
                return response()->json([
                    'success' => true,
                    'data'    => $data['CourierDetails'][0],
                ]);
            }

            // Return blank structure so form can still be filled
            return response()->json([
                'success' => true,
                'data'    => [
                    'EmployeeID'   => $employeeId,
                    'Month'        => $month,
                    'PostDate'     => null,
                    'DocateNo'     => null,
                    'Agency'       => null,
                    'RecevingDate' => null,
                ],
            ]);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => 'Failed to load courier details: ' . $e->getMessage()]);
        }
    }

    // Update / Save Courier Details (Claimant posting their physical hardcopy)
    public function updateCourierDetail(Request $request)
    {
        $employeeId = $request->get('empid') ?? $request->get('ei') ?? session('employee_id');
        $yearId     = (int) ($request->get('YearId') ?? session('FYearId', 1));
        $companyId  = $request->get('CompanyId') ?? session('CompanyId', 1);
        $tokenId    = session('token_id');
        $month      = $request->month;
        $postDate   = $request->post_date;
        $docketNo   = $request->docket_no;
        $agency     = $request->agency;

        $dateError = ClaimSubmissionValidator::first(app(ClaimSubmissionValidator::class)->courierDateForMonth($postDate, (int) $employeeId, (int) $month, (int) $yearId));
        if ($dateError !== null) {
            return response()->json(['Code' => '100', 'success' => false, 'msg' => $dateError, 'message' => $dateError], 422);
        }
        $query = ['value' => 'UpdateCourierDetails', 'empid' => $employeeId, 'YearId' => $yearId, 'ComId' => $companyId, 'tokenid' => $tokenId, 'Month' => $month, 'PostDate' => $postDate, 'DocateNo' => $docketNo, 'Agency' => $agency];

        try {
            $response = LegacyApi::http(30)->get(LegacyApi::url('common.php'), $query);
            $data = $response->json();

            if (isset($data['Code']) && in_array((string) $data['Code'], ['200', '300'], true)) {
                return response()->json(['success' => true, 'message' => 'Courier details saved successfully.']);
            }

            return response()->json(['success' => false, 'message' => $data['msg'] ?? 'Failed to save courier details.']);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
        }
    }

    private function getCount($type, $employeeId, $yearId, $companyId, $tokenId, $dataKey)
    {
        $url = \App\Support\LegacyApi::url('common.php') . "?value={$type}&empid={$employeeId}&YearId={$yearId}&ComId={$companyId}&tokenid={$tokenId}";

        try {
            $response = \App\Support\LegacyApi::http(30)->get($url);
            $data = $response->json();
            return isset($data[$dataKey]) ? count($data[$dataKey]) : 0;
        } catch (\Exception $e) {
            return 0;
        }
    }
}
