<?php

namespace App\Http\Controllers\admin;

use App\Helpers\CommonHelper;
use App\Http\Controllers\Controller;
use App\Models\ActivityName;
use App\Models\ExpenseClaim;
use App\Models\HRMEmployeeGeneral;
use App\Services\ClaimRules\ClaimSubmissionValidator;
use App\Support\LegacyApi;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class ClaimantController extends Controller
{
    public function claims()
    {
        $employeeId = session('employee_id');
        $depId = HRMEmployeeGeneral::where('EmployeeID', $employeeId)->value('DepartmentId');
        $hasActivity = ActivityName::whereRaw('FIND_IN_SET(?, dept_id)', [$depId])->exists();
        $participantActivityClaimIds = app(ClaimSubmissionValidator::class)->participantActivityClaimIds();

        $participantOptionalClaimIds = array_values(array_diff((array) config('claim_rules.participants.activity_optional_claim_ids', []), $participantActivityClaimIds));

        return view('employee.claims', compact('hasActivity', 'depId', 'participantActivityClaimIds', 'participantOptionalClaimIds'));
    }

    public function claimant(Request $request)
    {
        $status = $request->get('status', 'open');
        $employeeId = session('employee_id');
        $yearId = $this->resolveYearId($request);
        $companyId = $this->resolveCompanyId($request, $employeeId);
        $tokenId = LegacyApi::webToken();
        $fYear = session('FYear', date('Y').'-'.(date('Y') + 1));

        $draftCount = $this->getDraftCount($employeeId, $yearId, $companyId, $tokenId);
        $openedCount = $this->getOpenedCount($employeeId, $yearId, $companyId, $tokenId);
        $closedCount = $this->getClosedCount($employeeId, $yearId, $companyId, $tokenId);

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
            'isVerifierView' => false,
        ]);
    }

    public function approver(Request $request)
    {
        $status = $request->get('status', 'pending');
        $employeeId = session('employee_id');
        $yearId = session('year_id');
        $companyId = session('company_id');
        $tokenId = LegacyApi::webToken();
        $prefix = ltrim($request->route()->getPrefix(), '/');
        if ($prefix === 'vnr') {
            $controller = new VNRApproverController;
            $resolvedCompanyId = $companyId;
        } elseif ($prefix === 'vvnr') {
            $controller = new VVNRApproverController;
            $resolvedCompanyId = $companyId;
        } else {
            // nursery — always company ID 3
            $controller = new NurseryApproverController;
            $resolvedCompanyId = 3;
        }
        $pendingCount = 0;
        $approvedCount = 0;

        return view('employee.'.$prefix.'.approver', compact('status', 'pendingCount', 'approvedCount', 'prefix') + ['companyId' => $resolvedCompanyId]);
    }

    public function claimList(Request $request)
    {
        $year_id = session('year_id');
        $table = ExpenseClaim::tableName($year_id);
        $month = $request->month;
        $userId = session('employee_id');

        $query = DB::table($table.' as ec')
            ->leftJoin('claimtype as ct', 'ct.ClaimId', '=', 'ec.ClaimId')
            ->select(
                'ec.ExpId',
                'ct.ClaimName',
                'ec.BillDate',
                'ec.CrDate',
                'ec.ClaimMonth',
                'ec.FilledTAmt',
                'ec.DateEntryRemark',
                'ec.ClaimStatus',
                'ec.Img_1',
                'ec.Img_2',
                'ec.Img_3',
                DB::raw('ec.Img_4 as pdf_1'),
                DB::raw('ec.Img_5 as pdf_2')
            )
            ->where('ec.CrBy', $userId)
            ->where('ec.ClaimStatus', '!=', 'Deactivate');

        // UPLOAD-01: show the whole month's claims (was the last 5 of any month). With no month
        // chosen, the month of the employee's latest claim is used and returned to the screen.
        if (empty($month)) {
            $month = optional((clone $query)->orderBy('ec.ExpId', 'desc')->first())->ClaimMonth;
        }
        if (! empty($month) && $month !== 'all') {
            $query->where('ec.ClaimMonth', (int) $month);
        }

        $rows = $query->orderBy('ec.ExpId', 'desc')->limit(1000)->get();

        $formatted = $rows->map(function ($row) {
            $monthNames = ['', 'Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];

            // Images
            $images = '';
            foreach (['Img_1', 'Img_2', 'Img_3'] as $f) {
                if (! empty($row->$f)) {
                    $images .= '<a href="'.e($row->$f).'" target="_blank">'
                             .'<img src="'.e($row->$f).'" style="height:32px;width:32px;object-fit:cover;border-radius:4px;border:1px solid #dee2e6;margin:1px;" '
                             .'onerror="this.style.display=\'none\'">'
                             .'</a>';
                }
            }
            foreach (['pdf_1', 'pdf_2'] as $f) {
                if (! empty($row->$f)) {
                    $images .= '<a href="'.e($row->$f).'" target="_blank" class="badge bg-danger-subtle text-danger" style="font-size:10px;padding:4px 6px;margin:1px;">'
                             .'<i class="ri-file-pdf-line"></i> PDF</a>';
                }
            }
            if (empty($images)) {
                $images = '<span class="text-muted" style="font-size:11px;">—</span>';
            }
            $images = '<div class="d-flex flex-wrap gap-1 justify-content-center">'.$images.'</div>';

            // Status badge
            $map = [
                'draft' => 'bg-secondary-subtle text-secondary',
                'submitted' => 'bg-warning-subtle text-warning',
                'filled' => 'bg-info-subtle text-info',
                'verified' => 'bg-primary-subtle text-primary',
                'approved' => 'bg-success-subtle text-success',
                'financed' => 'bg-teal-subtle text-teal',
            ];
            $cls = $map[strtolower($row->ClaimStatus)] ?? 'bg-secondary-subtle text-secondary';
            $statusBadge = '<span class="badge '.$cls.'">'.ucfirst($row->ClaimStatus).'</span>';

            return [
                'ExpId' => $row->ExpId,
                'ClaimName' => $row->ClaimName ?? '—',
                'ClaimMonth' => $monthNames[(int) $row->ClaimMonth] ?? '—',
                'BillDate' => $row->BillDate && \App\Support\ZeroDate::has($row->BillDate) ? date('d M Y', strtotime($row->BillDate)) : '—',
                'CrDate' => $row->CrDate && \App\Support\ZeroDate::has($row->CrDate) ? date('d M Y', strtotime($row->CrDate)) : '—',
                'FilledTAmt' => $row->FilledTAmt > 0 ? '₹ '.number_format($row->FilledTAmt, 0) : '—',
                'Images' => $images,
                'DateEntryRemark' => $row->DateEntryRemark ?? '—',
                'ClaimStatus' => $statusBadge,
            ];
        });

        return response()->json([
            'month' => $month ? (string) $month : '',
            'draw' => (int) $request->input('draw', 1),
            'recordsTotal' => $formatted->count(),
            'recordsFiltered' => $formatted->count(),
            'data' => $formatted->values(),
        ]);
    }

    public function getOdometerData(Request $request)
    {
        // DATE-KEEP-01: the form sends d-m-Y; the claim table holds Y-m-d (the lookup never found a back-dated reading)
        $billDate = ClaimSubmissionValidator::parseDate((string) ($request->date ?? '')) ?? date('Y-m-d');
        $employeeId = session('employee_id');
        $yearId = session('year_id');
        $companyId = session('company_id');
        $table = ExpenseClaim::tableName($yearId);
        $existingData = DB::connection(session('expense_connection') ?: config('database.default'))->table($table)->where('BillDate', $billDate)->where('ClaimId', 7)->where('CrBy', $employeeId)->where('ClaimStatus', '!=', 'Deactivate')->first();
        $apiUrl = LegacyApi::baseUrl()."/{$companyId}/common.php?value=Current_date_odo_details&empid={$employeeId}&YearId={$yearId}&ComId={$companyId}&tokenid=".LegacyApi::webToken()."&date=".urlencode($billDate);
        try {
            $response = LegacyApi::http()->get($apiUrl);
            $apiData = $response->json();
        } catch (\Exception $e) {
            $apiData = [];
        }

        return response()->json(['success' => true, 'existingData' => $existingData, 'apiData' => $apiData]);
    }

    public function uploadAndExtract(Request $request)
    {
        try {
            if ($request->hasFile('photo') && $request->has('type')) {
                $type = $request->type;
                $empId = $request->emp_id;
                $tempPunchId = (int) ($request->temp_punch_id ?: 0);
                $yearId = session('year_id');
                $companyId = session('company_id');
                $url = LegacyApi::baseUrl().'/extract-data.php?value=extract_odo_data&YearId='.urlencode($yearId).'&tokenid='.LegacyApi::extractToken().'&ComId='.urlencode($companyId).'&empid='.urlencode($empId);
                // OCR-OPTIONAL-01: the meter reader is optional; do not hold the screen for a minute when it is down
                $response = LegacyApi::http(20)->attach($type, file_get_contents($request->file('photo')->path()), $request->file('photo')->getClientOriginalName())->post($url, ['temp_punch_id' => $tempPunchId]);
                $decodedResponse = $response->json();
                if (is_array($decodedResponse)) {
                    return response()->json(['success' => true, 'httpCode' => $response->status(), 'response' => $decodedResponse]);
                } else {
                    return response()->json(['success' => false, 'message' => 'Invalid API response.', 'raw' => $response->body()]);
                }
            }
            if ($request->has('vehicleType')) {
                $imageType = $request->imageType;
                $empId = session('employee_id');
                $yearId = session('year_id');
                $companyId = session('company_id');
                $billDate = $request->billDate;
                $vehicleType = $request->vehicleType;
                $tempPunchId = (int) ($request->temp_punch_id ?: 0);
                $remark = $request->remark;
                $openingReading = $request->openingReading;
                $closingReading = $request->closingReading;
                $totalKM = $request->totalKM;
                $url = LegacyApi::baseUrl()."/{$companyId}/claim.php";
                $postData = ['empid' => $empId, 'YearId' => $yearId, 'ComId' => $companyId, 'tokenid' => LegacyApi::webToken(), 'datefor' => $billDate, 'vehicleType' => $vehicleType, 'temp_punch_id' => $tempPunchId, 'remark' => $remark];
                $http = LegacyApi::http();
                if ($imageType == 'opening' && $request->hasFile('startPhoto')) {
                    $postData['value'] = 'uploadimg_odo_opening';
                    $postData['odomtr_opening'] = $openingReading;
                    $http = $http->attach('opening_img', file_get_contents($request->file('startPhoto')->path()), $request->file('startPhoto')->getClientOriginalName());
                } elseif ($imageType == 'closing' && $request->hasFile('endPhoto')) {
                    $postData['value'] = 'uploadimg_odo_closing';
                    $postData['odomtr_opening'] = $openingReading;
                    $postData['odomtr_closing'] = $closingReading;
                    $postData['TotKm'] = $totalKM;
                    $http = $http->attach('closing_img', file_get_contents($request->file('endPhoto')->path()), $request->file('endPhoto')->getClientOriginalName());
                } elseif ($imageType == 'opening_closing' && $request->hasFile('startPhoto') && $request->hasFile('endPhoto')) {
                    $postData['value'] = 'uploadimg_odo_closing';
                    $postData['odomtr_opening'] = $openingReading;
                    $postData['odomtr_closing'] = $closingReading;
                    $postData['TotKm'] = $totalKM;
                    $http = $http->attach('opening_img', file_get_contents($request->file('startPhoto')->path()), $request->file('startPhoto')->getClientOriginalName())->attach('closing_img', file_get_contents($request->file('endPhoto')->path()), $request->file('endPhoto')->getClientOriginalName());
                } else {
                    return response()->json(['success' => false, 'message' => 'Invalid image type or missing files']);
                }
                $response = $http->post($url, $postData);
                $decodedResponse = $response->json();
                if (is_array($decodedResponse) && isset($decodedResponse['msg']) && $decodedResponse['msg'] === 'Data updated successfully') {
                    return response()->json(['success' => true, 'httpCode' => $response->status(), 'response' => $decodedResponse]);
                } else {
                    // the save's own message (date rule, duplicate, login ...) instead of a generic one
                    $msg = is_array($decodedResponse) ? ($decodedResponse['msg'] ?? $decodedResponse['message'] ?? null) : null;

                    return response()->json(['success' => false, 'message' => $msg ?: 'The odometer reading could not be saved (server answered '.$response->status().').']);
                }
            }

            return response()->json(['success' => false, 'message' => 'Invalid request']);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()]);
        }
    }

    public function getDraftList(Request $request)
    {
        $employeeId = $request->get('ei') ?? session('employee_id');
        $yearId = $this->resolveYearId($request);
        $companyId = $this->resolveCompanyId($request, $employeeId);
        $tokenId = $this->tokenId();
        $url = LegacyApi::baseUrl()."/{$companyId}/common.php?value=Draftlist&empid={$employeeId}&YearId={$yearId}&ComId={$companyId}&tokenid={$tokenId}";
        try {
            $response = LegacyApi::http()->get($url);
            $data = $response->json();

            return response()->json($data);
        } catch (\Exception $e) {
            return response()->json(['Code' => '500', 'msg' => 'API Error', 'Draft_list' => []]);
        }
    }

    public function deleteDraft(Request $request)
    {
        $employeeId = $request->get('ei') ?? session('employee_id');
        $yearId = $this->resolveYearId($request);
        $companyId = $this->resolveCompanyId($request, $employeeId);
        $tokenId = $this->tokenId();
        $expId = $request->exp_id;
        $url = LegacyApi::baseUrl()."/{$companyId}/claim.php?value=Claim_Deleted&empid={$employeeId}&YearId={$yearId}&ComId={$companyId}&tokenid={$tokenId}&ExpId={$expId}";
        try {
            $response = LegacyApi::http()->get($url);
            $data = $response->json();

            return response()->json($data);
        } catch (\Exception $e) {
            return response()->json(['Code' => '500', 'msg' => 'Delete Failed']);
        }
    }

    public function getOpenList(Request $request)
    {
        $employeeId = $request->get('ei') ?? session('employee_id');
        $yearId = $this->resolveYearId($request);
        $companyId = $this->resolveCompanyId($request, $employeeId);
        $tokenId = $this->tokenId();
        $url = LegacyApi::baseUrl()."/{$companyId}/common.php?value=Openlist&empid={$employeeId}&YearId={$yearId}&ComId={$companyId}&tokenid={$tokenId}";
        try {
            $response = LegacyApi::http()->get($url);
            $data = $response->json();

            return response()->json($data);
        } catch (\Exception $e) {
            return response()->json(['Code' => '500', 'msg' => 'API Error', 'Open_list' => []]);
        }
    }

    public function getOpenDayDetails(Request $request)
    {
        $employeeId = $request->get('ei') ?? session('employee_id');
        $yearId = $this->resolveYearId($request);
        $companyId = $this->resolveCompanyId($request, $employeeId);
        $tokenId = $this->tokenId();
        $month = $request->month;
        $url = LegacyApi::baseUrl()."/{$companyId}/common.php?value=Openlist_Day&empid={$employeeId}&YearId={$yearId}&ComId={$companyId}&tokenid={$tokenId}&Month={$month}";
        try {
            $response = LegacyApi::http()->get($url);
            $data = $response->json();

            return response()->json($data);
        } catch (\Exception $e) {
            return response()->json(['Code' => '500', 'msg' => 'API Error', 'DayWiseDetails' => []]);
        }
    }

    public function getCloseList(Request $request)
    {
        $employeeId = $request->get('ei') ?? session('employee_id');
        $yearId = $this->resolveYearId($request);
        $companyId = $this->resolveCompanyId($request, $employeeId);
        $tokenId = $this->tokenId();
        // Month filter mirrors the app: blank/All => every closed month, a month number => that month only
        $month = $request->month;
        $value = (! empty($month) && $month !== 'All') ? 'Closelist_Month' : 'Closelist_All_Month';
        $url = LegacyApi::baseUrl()."/{$companyId}/common.php?value={$value}&empid={$employeeId}&YearId={$yearId}&ComId={$companyId}&tokenid={$tokenId}";
        if ($value === 'Closelist_Month') {
            $url .= '&Month='.urlencode($month);
        }
        try {
            $response = LegacyApi::http()->get($url);
            $data = $response->json();

            if (!empty($data['Close_list']) && is_array($data['Close_list'])) {
                $expenseConn = match ((int) $companyId) {
                    11 => 'expense_vvnr',
                    3 => 'expense_nursery',
                    default => 'expense',
                };
                foreach ($data['Close_list'] as &$item) {
                    $m = (int) ($item['Month'] ?? 0);
                    $rem = trim($item['Fin_PayRemark'] ?? '');
                    $cPaid = (float) ($item['Fin_PayAmt'] ?? 0);
                    $payDate = trim($item['Fin_PayDate'] ?? '');
                    $payStatus = trim($item['payment_status'] ?? '');

                    $isPaid = ($cPaid > 0)
                        || (!empty($payDate) && \App\Support\ZeroDate::has($payDate))
                        || in_array(strtolower($payStatus), ['confirmedpayment', 'paid', 'confirmed']);

                    // If not paid yet, it must not display or store any "Paid Rs." remark
                    if (!$isPaid) {
                        if (stripos($rem, 'Paid Rs.') !== false) {
                            $item['Fin_PayRemark'] = '';
                            try {
                                $updateMef = DB::connection($expenseConn)->table("y{$yearId}_monthexpensefinal");
                                if (!empty($item['id'])) {
                                    $updateMef->where('id', $item['id']);
                                } else {
                                    $updateMef->where('EmployeeID', $employeeId)->where('Month', $m)->where('YearId', $yearId);
                                }
                                $updateMef->update(['Fin_PayRemark' => '']);
                            } catch (\Exception $e) {}
                        }
                        continue;
                    }

                    try {
                        $daAmt = 0;
                        try {
                            $claimsTable = "y{$yearId}_expenseclaims";
                            $daRow = DB::connection($expenseConn)->selectOne("
                                SELECT 
                                    COALESCE(SUM(CASE WHEN FinancedTAmt > 0 THEN FinancedTAmt ELSE 0 END), 0) as da_fin,
                                    COALESCE(SUM(CASE WHEN ApprTAmt > 0 THEN ApprTAmt ELSE 0 END), 0) as da_appr,
                                    COALESCE(SUM(CASE WHEN VerifyTAmt > 0 THEN VerifyTAmt ELSE 0 END), 0) as da_ver,
                                    COALESCE(SUM(CASE WHEN FilledTAmt > 0 THEN FilledTAmt ELSE 0 END), 0) as da_fill
                                FROM {$claimsTable}
                                WHERE CrBy = ? AND ClaimMonth = ? AND ClaimStatus != 'Deactivate' AND FilledOkay = 1 AND (ClaimId = 19 OR ClaimId = 20)
                            ", [$employeeId, $m]);
                            if ($daRow) {
                                $daAmt = (float) ($daRow->da_fin > 0 ? $daRow->da_fin : ($daRow->da_appr > 0 ? $daRow->da_appr : ($daRow->da_ver > 0 ? $daRow->da_ver : ($daRow->da_fill ?? 0))));
                            }
                        } catch (\Exception $e) {}

                        // Fallback: If claims query yielded 0, infer from (Approved - Adjustments - Paid)
                        if ($daAmt <= 0) {
                            $itAppr = (float) ($item['Approved_Amount'] ?? ($item['Verified_Amount'] ?? 0));
                            $itPaid = (float) ($item['Fin_PayAmt'] ?? 0);
                            $itAdj = (float) ($item['total_adjustment'] ?? ((float) ($item['total_activity_adjustment'] ?? 0) + (float) ($item['total_expense_adjustment'] ?? 0)));
                            if ($itAppr > 0 && $itPaid > 0 && ($itAppr - $itAdj - $itPaid) > 0) {
                                $daAmt = round($itAppr - $itAdj - $itPaid, 2);
                            }
                        }

                        if ($daAmt > 0) {
                            $daStr = (floor($daAmt) == $daAmt) ? number_format($daAmt, 0, '', '') : number_format($daAmt, 2);
                            $daTag = "DA Rs. {$daStr}";

                            if (preg_match('/DA\s+Rs\.?\s*([0-9.]+)/i', $rem, $matches)) {
                                if ((float) $matches[1] != $daAmt) {
                                    $newRem = preg_replace('/DA\s+Rs\.?\s*[0-9.]+/i', $daTag, $rem);
                                } else {
                                    $newRem = $rem;
                                }
                            } else {
                                if (empty($rem) || $rem === '—' || $rem === '-') {
                                    $paidFormatted = (floor((float) ($item['Fin_PayAmt'] ?? 0)) == (float) ($item['Fin_PayAmt'] ?? 0)) ? number_format((float) ($item['Fin_PayAmt'] ?? 0), 0, '', '') : number_format((float) ($item['Fin_PayAmt'] ?? 0), 2);
                                    $newRem = "Paid Rs. {$paidFormatted}, No adjustments, {$daTag}";
                                } else {
                                    $newRem = "{$rem}, {$daTag}";
                                }
                            }
                        } else {
                            if (preg_match('/DA\s+Rs\.?\s*[0-9.]+/i', $rem)) {
                                $newRem = preg_replace('/,?\s*DA\s+Rs\.?\s*[0-9.]+/i', '', $rem);
                                $newRem = trim($newRem, " ,");
                            } else {
                                $newRem = $rem;
                            }
                        }

                        if ($newRem !== $rem) {
                            $item['Fin_PayRemark'] = $newRem;
                            try {
                                $updateMef = DB::connection($expenseConn)->table("y{$yearId}_monthexpensefinal");
                                if (!empty($item['id'])) {
                                    $updateMef->where('id', $item['id']);
                                } else {
                                    $updateMef->where('EmployeeID', $employeeId)->where('Month', $m)->where('YearId', $yearId);
                                }
                                $updateMef->update(['Fin_PayRemark' => $newRem]);
                            } catch (\Exception $e) {}
                        }
                    } catch (\Exception $e) {}
                }
                unset($item);
            }

            return response()->json($data);
        } catch (\Exception $e) {
            return response()->json(['Code' => '500', 'msg' => 'API Error', 'Close_list' => [], 'Closed_months' => []]);
        }
    }

    public function getCloseDayDetails(Request $request)
    {
        $employeeId = $request->get('ei') ?? session('employee_id');
        $yearId = $this->resolveYearId($request);
        $companyId = $this->resolveCompanyId($request, $employeeId);
        $tokenId = $this->tokenId();
        $month = $request->month;
        $url = LegacyApi::baseUrl()."/{$companyId}/common.php?value=Closelist_Day&empid={$employeeId}&Month={$month}&YearId={$yearId}&ComId={$companyId}&tokenid={$tokenId}";
        try {
            $response = LegacyApi::http()->get($url);
            $data = $response->json();

            return response()->json($data);
        } catch (\Exception $e) {
            return response()->json(['Code' => '500', 'msg' => 'API Error', 'DayWiseDetails' => []]);
        }
    }

    /**
     * Verify a single filled claim (mirrors the "Verify" action on the app's open-month card).
     */
    public function verifyClaim(Request $request)
    {
        $employeeId = session('employee_id');
        $yearId = session('year_id');
        $companyId = session('company_id');
        $tokenId = $this->tokenId();
        $expId = $request->exp_id;
        $url = LegacyApi::baseUrl()."/{$companyId}/claim.php?value=Claim_Okay&empid={$employeeId}&YearId={$yearId}&ComId={$companyId}&ExpId={$expId}&tokenid={$tokenId}";
        try {
            $response = LegacyApi::http()->get($url);

            return response()->json($response->json());
        } catch (\Exception $e) {
            return response()->json(['Code' => '500', 'msg' => 'Verification failed']);
        }
    }

    /**
     * Deny a single filled claim with a mandatory remark.
     */
    public function denyClaim(Request $request)
    {
        $request->validate(['exp_id' => 'required', 'remark' => 'required|string|max:50']);
        $employeeId = session('employee_id');
        $yearId = session('year_id');
        $companyId = session('company_id');
        $tokenId = $this->tokenId();
        $expId = $request->exp_id;
        $url = LegacyApi::baseUrl()."/{$companyId}/claim.php?value=Claim_Deny&empid={$employeeId}&YearId={$yearId}&ComId={$companyId}&ExpId={$expId}&remark=".urlencode($request->remark)."&tokenid={$tokenId}";
        try {
            $response = LegacyApi::http()->get($url);

            return response()->json($response->json());
        } catch (\Exception $e) {
            return response()->json(['Code' => '500', 'msg' => 'Failed to deny claim']);
        }
    }

    /**
     * ODO-AGREE-01 (13 Nov 2026): the employee agrees (optional remark) or does not agree (remark needed) with the
     * odometer readings the running-km verifier checked for a month. Own claims only. Not agreeing sends the readings
     * back to the verifier (Denied list on Verify Running KM).
     */
    public function odometerAgree(Request $request)
    {
        return $this->odometerDecision($request, true);
    }

    public function odometerNotAgree(Request $request)
    {
        return $this->odometerDecision($request, false);
    }

    private function odometerDecision(Request $request, bool $agree)
    {
        $request->validate(['month' => 'required|integer|min:1|max:12', 'remark' => ($agree ? 'nullable' : 'required').'|string|max:250']);
        $employeeId = $this->ownEmployeeId($request, ['ei', 'empid']);
        if ($employeeId === null) {
            return response()->json(['status' => 'error', 'message' => 'You can confirm only your own odometer readings.'], 403);
        }
        $yearId = (int) $this->resolveYearId($request);
        $companyId = (int) $this->resolveCompanyId($request, $employeeId);
        $conn = \App\Helpers\DatabaseSwitcher::getExpenseConnection($companyId);
        $month = (int) $request->month;
        $closed = DB::connection($conn)->table("y{$yearId}_monthexpensefinal")->where('EmployeeID', $employeeId)
            ->where('Month', $month)->where('YearId', $yearId)->value('Status');
        if ($closed === 'Closed') {
            return response()->json(['status' => 'error', 'message' => 'This month is already submitted.'], 422);
        }
        $pending = DB::connection($conn)->table("y{$yearId}_expenseclaims")->where('ClaimId', 7)->where('CrBy', $employeeId)
            ->where('ClaimMonth', $month)->where('v_verify', 'Y')->whereRaw("COALESCE(c_verify,'N') <> 'Y'")
            ->whereNotIn('ClaimStatus', ['Deactivate', 'Deactivat', 'Draft'])->count();
        if (! $pending) {
            return response()->json(['status' => 'error', 'message' => 'No checked odometer readings are waiting for you this month.'], 422);
        }
        $params = ['empid' => $employeeId, 'empId' => $employeeId, 'month' => $month, 'YearId' => $yearId, 'year' => $yearId,
            'ComId' => $companyId, 'remark' => trim((string) $request->input('remark', ''))];
        $mobile = app(\App\Http\Controllers\Api\Mobile\ExpenseDetailController::class);
        $sub = Request::create('/', 'POST', $params);
        try {
            $res = $agree ? $mobile->handleAgree($sub) : $mobile->handleNotAgree($sub);
            $data = $res->getData(true);
        } catch (\Throwable $e) {
            $ref = substr(md5(uniqid('', true)), 0, 8);
            Log::error("Odometer agree/not agree failed [{$ref}]: ".$e->getMessage());

            return response()->json(['status' => 'error', 'message' => "Could not save (ref {$ref})."], 500);
        }
        if (($data['status'] ?? '') !== 'success') {
            return response()->json(['status' => 'error', 'message' => $data['message'] ?? $data['msg'] ?? 'Could not save.'], 422);
        }

        return response()->json(['status' => 'success', 'message' => $agree
            ? "You agreed with {$pending} odometer reading(s)."
            : 'Sent back to the verification team with your remark.']);
    }

    /**
     * Final submit of an open month to the approval workflow.
     */
    public function submitMonth(Request $request)
    {
        $request->validate(['month' => 'required']);
        $employeeId = $request->get('ei') ?? $request->get('empid') ?? session('employee_id');
        $yearId = $this->resolveYearId($request);
        $companyId = $this->resolveCompanyId($request, $employeeId);
        $tokenId = $this->tokenId();
        $month = $request->month;
        $ack = $request->input('ack_unpunched') == '1' ? '&ack_unpunched=1' : '';   // SUBMIT-PENDING-01
        $url = LegacyApi::baseUrl()."/{$companyId}/claim.php?value=Claim_Submit&empid={$employeeId}&YearId={$yearId}&ComId={$companyId}&month={$month}&tokenid={$tokenId}".$ack;
        try {
            // this app's own rules first (SUBMIT-PENDING-01 needs the warning step), then the configured claim API
            $own = LegacyApi::inProcess($url, [], 'GET');
            if (is_array($own)) {
                return response()->json($own);
            }
            $response = LegacyApi::http()->get($url);

            return response()->json($response->json());
        } catch (\Exception $e) {
            return response()->json(['Code' => '500', 'msg' => 'Failed to submit month']);
        }
    }

    /**
     * Vehicle running KM for an open month (proxied so the token stays server side).
     */
    public function getRunningKm(Request $request)
    {
        $employeeId = $request->get('ei') ?? session('employee_id');
        $yearId = $this->resolveYearId($request);
        $companyId = $this->resolveCompanyId($request, $employeeId);
        $tokenId = $this->tokenId();
        $month = $request->month;
        $url = LegacyApi::baseUrl()."/{$companyId}/common.php?value=VehicleRunning&empid={$employeeId}&YearId={$yearId}&ComId={$companyId}&month={$month}&tokenid={$tokenId}";
        try {
            $response = LegacyApi::http()->get($url);

            return response()->json($response->json());
        } catch (\Exception $e) {
            return response()->json(['status' => 'error', 'message' => 'Failed to load running KM data']);
        }
    }

    /**
     * Get single claim detailed expense particulars & remarks history (mirrors Flutter ExDetail).
     */
    public function getExpenseDetail(Request $request)
    {
        $employeeId = $request->get('ei') ?? session('employee_id');
        $expId = $request->get('expid') ?? $request->get('exp_id');
        $yearId = $this->resolveYearId($request);
        $companyId = $this->resolveCompanyId($request, $employeeId);
        $tokenId = $this->tokenId();

        $url = LegacyApi::baseUrl()."/{$companyId}/common.php?value=ExDetail&empid={$employeeId}&expid={$expId}&YearId={$yearId}&ComId={$companyId}&tokenid={$tokenId}";
        try {
            $response = LegacyApi::http()->get($url);

            return response()->json($response->json());
        } catch (\Exception $e) {
            return response()->json(['Code' => '500', 'msg' => 'Failed to load expense details']);
        }
    }

    /**
     * Submit Courier / Dispatch details for a closed month (mirrors Flutter Courier_Submit).
     */
    public function submitCourier(Request $request)
    {
        // UAT E-07: own courier details only; ei / empid from the request must match the session.
        $employeeId = $this->ownEmployeeId($request, ['ei', 'empid']);
        if ($employeeId === null) {
            return response()->json(['Code' => '100', 'msg' => 'You can submit courier details only for your own claims.'], 403);
        }
        $yearId = $this->resolveYearId($request);
        $companyId = $this->resolveCompanyId($request, $employeeId);
        $tokenId = $this->tokenId();

        $postDate = $request->input('PostDate');
        if (!empty($postDate) && strpos($postDate, '-') !== false) {
            $parts = explode('-', $postDate);
            if (count($parts) === 3 && strlen($parts[0]) === 4) {
                // Convert yyyy-mm-dd to dd-mm-yyyy if needed
                $postDate = $parts[2] . '-' . $parts[1] . '-' . $parts[0];
            }
        }

        $docateNo = $request->input('DocateNo');
        $agency = $request->input('Agency');
        $month = $request->input('month');

        // Tracker #20: actual dispatch date only (checked here as well as at the relay target).
        $dateError = ClaimSubmissionValidator::first(app(ClaimSubmissionValidator::class)->courierDateForMonth($postDate, (int) $employeeId, (int) $month, (int) $yearId));
        if ($dateError !== null) {
            return response()->json(['Code' => '100', 'msg' => $dateError]);
        }

        $url = LegacyApi::baseUrl()."/{$companyId}/common.php";

        $postData = [
            'value' => 'Courier_Submit',
            'empid' => (string) $employeeId,
            'YearId' => (string) $yearId,
            'ComId' => (string) $companyId,
            'tokenid' => (string) $tokenId,
            'PostDate' => (string) $postDate,
            'DocateNo' => (string) $docateNo,
            'Agency' => (string) $agency,
            'month' => (string) $month,
        ];

        try {
            if ($request->hasFile('file') || $request->hasFile('Courier_Img')) {
                $file = $request->file('file') ?? $request->file('Courier_Img');
                $response = LegacyApi::http()
                    ->attach('file', file_get_contents($file->getRealPath()), $file->getClientOriginalName())
                    ->post($url, $postData);
            } else {
                $response = LegacyApi::http()->asForm()->post($url, $postData);
            }

            $res = $response->json();
            return response()->json($res ?? ['Code' => '200', 'msg' => 'Data Updated successfully']);
        } catch (\Exception $e) {
            return response()->json(['Code' => '500', 'msg' => $e->getMessage()], 500);
        }
    }

    private function resolveYearId(Request $request = null)
    {
        if ($request && ($request->filled('YearId') || $request->filled('yearId') || $request->filled('year_id'))) {
            return (int) ($request->YearId ?? $request->yearId ?? $request->year_id);
        }
        if (session('year_id')) {
            return (int) session('year_id');
        }
        if (session('FYearId')) {
            return (int) session('FYearId');
        }
        try {
            $today = date('Y-m-d');
            $activeYear = DB::table('financialyear')
                ->where('FromDate', '<=', $today)
                ->where('Todate', '>=', $today)
                ->value('YearId');
            if ($activeYear) {
                return (int) $activeYear;
            }
            $activeYearByStatus = DB::table('financialyear')->where('Status', 'Active')->value('YearId');
            if ($activeYearByStatus) {
                return (int) $activeYearByStatus;
            }
        } catch (\Exception $e) {}

        return 7;
    }

    private function resolveCompanyId(Request $request = null, $employeeId = null)
    {
        if ($request && ($request->filled('CompanyId') || $request->filled('companyId') || $request->filled('company_id'))) {
            return (int) ($request->CompanyId ?? $request->companyId ?? $request->company_id);
        }
        if (session('company_id')) {
            return (int) session('company_id');
        }
        if (session('CompanyId')) {
            return (int) session('CompanyId');
        }
        $empId = $employeeId ?? session('employee_id');
        if ($empId) {
            try {
                $comp = DB::connection('hrims')->table('hrm_employee')->where('EmployeeID', $empId)->value('CompanyId');
                if ($comp) {
                    return (int) $comp;
                }
            } catch (\Exception $e) {}
        }

        return 1;
    }

    /**
     * UAT E-07: the logged-in employee's id. A different id in the request (empid / ei) is
     * refused (null) instead of being used, so one employee cannot act on another's rows.
     */
    private function ownEmployeeId(Request $request, array $keys = ['empid']): ?int
    {
        $own = (int) session('employee_id');
        if ($own <= 0) {
            return null;
        }
        foreach ($keys as $key) {
            $given = $request->input($key);
            if ($given !== null && $given !== '' && (int) $given !== $own) {
                Log::warning('Claimant courier request for another employee refused.', ['key' => $key, 'user_id' => auth()->id()]);

                return null;
            }
        }

        return $own;
    }

    private function tokenId()
    {
        $token = session('token_id');
        if (!empty($token) && is_string($token) && strlen(trim($token)) > 5) {
            return trim($token);
        }

        return LegacyApi::webToken();
    }

    // Add this method to your existing ClaimantController.php
    public function claimantEmbed(Request $request)
    {
        $status = $request->get('status', 'drafts');
        $employeeId = $request->get('ei') ?? session('employee_id');
        $yearId = $this->resolveYearId($request);
        $companyId = $this->resolveCompanyId($request, $employeeId);
        $tokenId = $this->tokenId();

        $draftCount = $this->getDraftCount($employeeId, $yearId, $companyId, $tokenId);
        $openedCount = $this->getOpenedCount($employeeId, $yearId, $companyId, $tokenId);
        $closedCount = $this->getClosedCount($employeeId, $yearId, $companyId, $tokenId);

        // Pass isAdminView flag as true
        return view('employee.claimant', [
            'status' => $status,
            'isAdminView' => true,  // ← IMPORTANT: set to true for embed view
            'draftCount' => $draftCount,
            'openedCount' => $openedCount,
            'closedCount' => $closedCount,
        ]);
    }

    private function getDraftCount($employeeId, $yearId, $companyId, $tokenId)
    {
        try {
            $url = LegacyApi::baseUrl()."/{$companyId}/common.php?value=Draftlist&empid={$employeeId}&YearId={$yearId}&ComId={$companyId}&tokenid={$tokenId}";
            $response = LegacyApi::http()->get($url);
            $data = $response->json();

            return isset($data['Draft_list']) ? count($data['Draft_list']) : 0;
        } catch (\Exception $e) {
            return 0;
        }
    }

    private function getOpenedCount($employeeId, $yearId, $companyId, $tokenId)
    {
        try {
            $url = LegacyApi::baseUrl()."/{$companyId}/common.php?value=Openlist&empid={$employeeId}&YearId={$yearId}&ComId={$companyId}&tokenid={$tokenId}";
            $response = LegacyApi::http()->get($url);
            $data = $response->json();

            return isset($data['Open_list']) ? count($data['Open_list']) : 0;
        } catch (\Exception $e) {
            return 0;
        }
    }

    private function getClosedCount($employeeId, $yearId, $companyId, $tokenId)
    {
        try {
            $url = LegacyApi::baseUrl()."/{$companyId}/common.php?value=Closelist_All_Month&empid={$employeeId}&YearId={$yearId}&ComId={$companyId}&tokenid={$tokenId}";
            $response = LegacyApi::http()->get($url);
            $data = $response->json();

            return isset($data['Close_list']) ? count($data['Close_list']) : 0;
        } catch (\Exception $e) {
            return 0;
        }
    }

    public function addRemark(Request $request)
    {
        $employeeId = session('employee_id');
        $yearId = session('year_id');
        $companyId = session('company_id');
        $tokenId = LegacyApi::webToken();
        $expId = $request->exp_id;
        $remark = $request->remark;
        $empRole = 'E';
        $url = LegacyApi::baseUrl()."/{$companyId}/common.php?value=AddRemark&empid={$employeeId}&YearId={$yearId}&tokenid={$tokenId}&remark=".urlencode($remark)."&emprole={$empRole}&expid={$expId}&ComId={$companyId}";
        try {
            $response = LegacyApi::http()->get($url);
            $data = $response->json();

            return response()->json($data);
        } catch (\Exception $e) {
            return response()->json(['Code' => '500', 'msg' => 'Failed to add remark']);
        }
    }

    public function getCourierDetail(Request $request)
    {
        try {
            $employeeId = $this->ownEmployeeId($request);
            if ($employeeId === null) {
                return response()->json(['success' => false, 'message' => 'You can view courier details only for your own claims.'], 403);
            }
            $month = $request->month;
            $yearId = session('year_id');
            $table = "y{$yearId}_monthexpensefinal";
            $courierData = DB::table($table)->where('EmployeeID', $employeeId)->where('Month', $month)->where('YearId', $yearId)->select('PostDate', 'DocateNo', 'Agency', 'RecevingDate', 'DocRmk')->first();
            if (! $courierData) {
                return response()->json(['success' => false, 'message' => 'No courier data found']);
            }
            $isEditable = (empty($courierData->PostDate) && \App\Support\ZeroDate::none($courierData->PostDate));

            return response()->json(['success' => true, 'data' => $courierData, 'isEditable' => $isEditable]);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    public function updateCourierDetail(Request $request)
    {
        try {
            $request->validate(['empid' => 'nullable|integer', 'month' => 'required|integer', 'post_date' => 'required|date', 'docket_no' => 'required|string', 'agency' => 'required|string']);
            // UAT E-07: the claimant updates only their own courier row (employee id from the session).
            $employeeId = $this->ownEmployeeId($request);
            if ($employeeId === null) {
                return response()->json(['success' => false, 'message' => 'You can update courier details only for your own claims.'], 403);
            }
            $yearId = session('year_id');
            $dateError = ClaimSubmissionValidator::first(app(ClaimSubmissionValidator::class)->courierDateForMonth($request->post_date, $employeeId, (int) $request->month, (int) $yearId));
            if ($dateError !== null) {
                return response()->json(['success' => false, 'message' => $dateError], 422);
            }
            $table = "y{$yearId}_monthexpensefinal";
            $record = DB::table($table)->where('EmployeeID', $employeeId)->where('Month', $request->month)->where('YearId', $yearId)->first();
            if (! $record) {
                return response()->json(['success' => false, 'message' => 'Record not found']);
            }
            $updated = DB::table($table)->where('EmployeeID', $employeeId)->where('Month', $request->month)->where('YearId', $yearId)->update(['PostDate' => (ClaimSubmissionValidator::parseDate($request->post_date) ?? $request->post_date), 'DocateNo' => $request->docket_no, 'Agency' => $request->agency]);
            if ($updated) {
                return response()->json(['success' => true, 'message' => 'Courier details updated successfully']);
            }

            return response()->json(['success' => false, 'message' => 'No changes saved']);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    public function getDaData(Request $request)
    {
        $employeeId = session('employee_id');
        $eligibility = DB::connection('hrims')->table('hrm_employee_eligibility')->where('Status', 'A')->where('EmployeeID', $employeeId)->select('DA_Inside_Hq', 'DA_Outside_Hq')->first();
        $DAInside = $eligibility ? preg_replace('/[^0-9]/', '', $eligibility->DA_Inside_Hq) : '';
        $DAOutside = $eligibility ? preg_replace('/[^0-9]/', '', $eligibility->DA_Outside_Hq) : '';
        $claimTypes = DB::table('claimtype as ct')->join('claimgroup as cg', 'ct.cgId', '=', 'cg.cgId')->where('ct.ClaimStatus', 'B')->select('ct.ClaimId', 'ct.ClaimName', 'cg.cgCode', 'cg.cgName')->get()->filter(function ($claim) use ($eligibility) {
            return ($claim->ClaimId == 19 && ! empty($eligibility->DA_Inside_Hq)) || ($claim->ClaimId == 20 && ! empty($eligibility->DA_Outside_Hq));
        });

        return response()->json(['success' => true, 'DAInside' => $DAInside, 'DAOutside' => $DAOutside, 'claimTypes' => $claimTypes->values()->toArray(), 'fyearId' => session('year_id')]);
    }

    public function checkHoliday(Request $request)
    {
        $billDate = ClaimSubmissionValidator::parseDate($request->billDate) ?? date('Y-m-d', strtotime(str_replace('/', '-', (string) $request->billDate)));
        $employeeId = $request->employeeID;
        $year = date('Y');
        if (date('w', strtotime($billDate)) == 0) {
            return response()->json(['status' => 'sunday']);
        }
        $holidayTypes = ['HO', 'SL', 'PL', 'EL', 'FL', 'CL', 'ML'];
        $holidays = DB::connection('hrims')->table('hrm_employee_attendance')->where('EmployeeID', $employeeId)->whereIn('AttValue', $holidayTypes)->where('Year', $year)->pluck('AttDate')->toArray();
        if (empty($holidays)) {
            $archiveTable = 'hrm_employee_attendance_'.$year;
            if (DB::connection('hrims')->getSchemaBuilder()->hasTable($archiveTable)) {
                $holidays = DB::connection('hrims')->table($archiveTable)->where('EmployeeID', $employeeId)->whereIn('AttValue', $holidayTypes)->where('Year', $year)->pluck('AttDate')->toArray();
            }
        }
        if (in_array($billDate, $holidays)) {
            return response()->json(['status' => 'holiday']);
        }

        return response()->json(['status' => 'not_holiday']);
    }

    public function submitDaClaim(Request $request)
    {
        try {
            // UAT E-12: the file is optional here; for DOS (20) the shared #17 rule below decides,
            // so the user sees the night-halt proof message instead of "The uploaded file field is required.".
            $request->validate(['mclaim_date' => 'required|date_format:d-m-Y', 'mclaimtype' => 'required|in:19,20', 'mamount' => 'required|numeric|min:0', 'mremarks' => 'required|string|max:500', 'uploadedFile' => 'nullable|file|mimes:jpg,jpeg,png,pdf|max:5120']);
            // Tracker #17: same DOS proof rule as the mobile paths (shared validator). No offline
            // reference on the web (see ClaimSubmissionValidator::validateDaRequest).
            $ruleError = ClaimSubmissionValidator::first(app(ClaimSubmissionValidator::class)->validateDaRequest($request, null, false));
            if ($ruleError !== null) {
                return response()->json(['status' => 'error', 'msg' => $ruleError]);
            }
            $billDate = (\App\Services\ClaimRules\ClaimSubmissionValidator::parseDate($request->mclaim_date) ?? date('Y-m-d', strtotime($request->mclaim_date))); // BILLDATE-03 day-first
            $yearId = session('year_id');
            $employeeId = session('employee_id');
            $companyId = session('company_id');
            $mremarks = urlencode($request->mremarks);
            $url = LegacyApi::baseUrl()."/{$companyId}/claim.php?value=DA_Update&empid={$employeeId}&YearId={$yearId}&Billdate={$billDate}&DAtype={$request->mclaimtype}&DAamt={$request->mamount}&DArmk={$mremarks}&ComId={$companyId}&tokenid=".LegacyApi::webToken()."";
            $http = LegacyApi::http();
            if ($request->hasFile('uploadedFile')) {
                $file = $request->file('uploadedFile');
                $http = $http->attach('upload_img', file_get_contents($file->path()), $file->getClientOriginalName());
            }
            $response = $http->post($url);
            $result = $response->json();
            if ($result['Code'] == 300 && $result['msg'] == 'Data Found') {
                return response()->json(['status' => 'success', 'msg' => 'DA Claim Added Successfully.']);
            } else {
                return response()->json(['status' => 'dateissue', 'msg' => $result['msg']]);
            }
        } catch (\Illuminate\Validation\ValidationException $e) {
            // UAT E-12: show the rule message (e.g. the #17 DOS night-halt proof) instead of the generic error.
            return response()->json(['status' => 'error', 'msg' => collect($e->errors())->flatten()->first() ?: $e->getMessage()]);
        } catch (\Exception $e) {
            Log::error('DA Claim Error: '.$e->getMessage());

            return response()->json(['status' => 'error', 'msg' => 'There is some problem in your request.']);
        }
    }

    public function getUploadClaimData(Request $request)
    {
        $employeeId = session('employee_id');
        $yearId = session('year_id');
        $claimTypes = DB::table('claimtype')->where('ClaimStatus', 'A')->where('cgId', '!=', 7)->where('cgId', 1)->whereNotIn('ClaimId', [7, 19, 20])->whereRaw("NOT (ClaimName = 'Phone Fax' AND DATE(NOW()) >= '2025-04-01')")->orderBy('cgId')->orderBy('ClaimId')->select('ClaimId', 'ClaimName')->get();
        $financialYears = [6 => ['from' => '2024-04-01', 'to' => '2025-03-31'], 7 => ['from' => '2025-04-01', 'to' => '2026-03-31']];

        return response()->json(['success' => true, 'claimTypes' => $claimTypes, 'fyearId' => $yearId, 'financialYear' => $financialYears[$yearId] ?? null]);
    }

    public function extractClaimData(Request $request)
    {
        try {
            $request->validate(['file' => 'required|file|mimes:jpg,jpeg,png,pdf|max:5120', 'claim_id' => 'required']);
            $file = $request->file('file');
            $claimId = $request->claim_id;
            $employeeId = session('employee_id');
            $yearId = session('year_id');
            $companyId = session('company_id');
            $url = LegacyApi::baseUrl().'/extract-data.php';
            $response = LegacyApi::http()->attach('file', file_get_contents($file->path()), $file->getClientOriginalName())->post($url, ['value' => 'extract_data', 'YearId' => $yearId, 'tokenid' => LegacyApi::extractToken(), 'ComId' => $companyId, 'empid' => $employeeId, 'claim_id' => $claimId, 'form_type' => 'web']);

            return response()->json($response->json());
        } catch (\Exception $e) {
            Log::error('Extract Data Error: '.$e->getMessage());

            return response()->json(['success' => 'false', 'message' => 'Failed to extract data from file.']);
        }
    }

    public function submitUploadClaim(Request $request)
    {
        try {
            $employeeId = session('employee_id');
            $yearId = session('year_id');
            $companyId = session('company_id');
            // BILLDATE-02: dd/mm/yyyy read day-first (strtotime read 05/08 as 8 May)
            $billDate = ClaimSubmissionValidator::parseDate($request->uploadBillDate) ?? date('Y-m-d', strtotime(str_replace('/', '-', (string) $request->uploadBillDate)));
            $tempPunchId = (int) ($request->temp_punch_id ?: 0); // OCR-OPTIONAL-01: empty when the bill was not read automatically
            $remark = $request->Rmk ?? '';
            $claimType = $request->claimtype;
            $amount = $request->amount;
            // Tracker rules run here too, so a configured external relay target cannot bypass them.
            // UPLOAD-SIZE-01: a file above the server's per-file limit arrives as an upload error, not as a file
            foreach (Arr::flatten($request->allFiles()) as $f) {
                if ($f instanceof \Illuminate\Http\UploadedFile && ! $f->isValid()) {
                    return response()->json(['status' => 'error', 'msg' => "File \"{$f->getClientOriginalName()}\" was not received: ".$f->getErrorMessage().' Upload a smaller file.']);
                }
            }
            $ruleError = ClaimSubmissionValidator::first(app(ClaimSubmissionValidator::class)->validateBillRequest($request));
            if ($ruleError !== null) {
                return response()->json(['status' => 'error', 'msg' => $ruleError]);
            }
            $formData = ['value' => 'upload_bill', 'empid' => $employeeId, 'YearId' => $yearId, 'ComId' => $companyId, 'tokenid' => LegacyApi::webToken(), 'Billdate' => $billDate, 'temp_punch_id' => $tempPunchId, 'remark' => $remark, 'amount' => $amount, 'claim_id' => $claimType, 'expid' => 0];
            $url = LegacyApi::baseUrl().'/'.$companyId.'/claim.php';
            $http = LegacyApi::http();
            if ($claimType == '16') {
                $formData['Toll_Pass'] = $request->Toll_Pass;
                $formData['Toll_Tax'] = $request->Toll_Tax;
                $formData['International_Recharge'] = $request->International_Recharge;
                $formData['Printing_Stationery'] = $request->Printing_Stationery;
                $formData['Others'] = $request->Others_t1;
                $formData['Remark_Toll_Pass'] = $request->Remark_Toll_Pass;
                $formData['Remark_Toll_Tax'] = $request->Remark_Toll_Tax;
                $formData['Remark_International_Recharge'] = $request->Remark_International_Recharge;
                $formData['Remark_Printing_Stationery'] = $request->Remark_Printing_Stationery;
                $formData['Remark_Others'] = $request->Remark_Others_t1;
                $fileFields = ['File_Toll_Pass', 'File_Toll_Tax', 'File_International_Recharge', 'File_Printing_Stationery', 'File_Others_t1'];
            }
            if ($claimType == '42') {
                $formData['PaxDetails'] = $request->PaxDetails;
                $formData['Meeting_Hall'] = $request->Meeting_Hall;
                $formData['Lodging'] = $request->Lodging;
                $formData['Meals'] = $request->Meals;
                $formData['Business_Entertainment'] = $request->Business_Entertainment;
                $formData['Others'] = $request->Others_t2;
                $formData['Remark_Meeting_Hall'] = $request->Remark_Meeting_Hall;
                $formData['Remark_Lodging'] = $request->Remark_Lodging;
                $formData['Remark_Meals'] = $request->Remark_Meals;
                $formData['Remark_Business_Entertainment'] = $request->Remark_Business_Entertainment;
                $formData['Remark_Others'] = $request->Remark_Others_t2;
                $fileFields = ['File_Meeting_Hall', 'File_Lodging', 'File_Meals', 'File_Business_Entertainment', 'File_Others_t2'];
            }
            // Handle claim_id 17 (Group Meal)
            if ($claimType == '17') {
                $formData['PaxDetails'] = $request->PaxDetails ?? '';
                $formData['group_meal'] = $request->group_meal ?? '';
                $formData['meal_participants'] = $request->meal_participants ?? '';
                $formData['meal_other_participants'] = CommonHelper::normalizeOtherParticipantsJson($request->meal_other_participants ?? '');
                $formData['meal_bill_type'] = $request->meal_bill_type ?? 'image';
            }
            // Handle claim_id 1 (Lodging)
            if ($claimType == '1') {
                $formData['receipt_upload_supported'] = '1';
                $formData['PaxDetails'] = $request->PaxDetails ?? '';
                $formData['lodging_group'] = $request->lodging_group ?? '';
                $formData['lodging_participants'] = $request->lodging_participants ?? '';
                $formData['lodging_other_participants'] = CommonHelper::normalizeOtherParticipantsJson($request->lodging_other_participants ?? '');
            }
            // Handle claim_id 14 (Rail Fare)
            if ($claimType == '14') {
                $formData['ticket_category'] = app(ClaimSubmissionValidator::class)->normalizeTicketCategory($request->ticket_category) ?? ($request->ticket_category ?? '');
            }
            if ($claimType == '16' || $claimType == '42') {
                foreach ($fileFields as $fieldName) {
                    if ($request->hasFile($fieldName)) {
                        $file = $request->file($fieldName);
                        $apiFieldName = match ($fieldName) {
                            'File_Others_t1', 'File_Others_t2' => 'File_Others[]',
                            default => $fieldName.'[]',
                        };
                        $http = $http->attach($apiFieldName, file_get_contents($file->getRealPath()), $file->getClientOriginalName());
                    }
                }
            } else {
                if ($request->hasFile('NewFile')) {
                    foreach ($request->file('NewFile') as $file) {
                        $ext = strtolower($file->getClientOriginalExtension());
                        if (in_array($ext, ['jpg', 'jpeg', 'png'])) {
                            $http = $http->attach('upload_img[]', file_get_contents($file->getRealPath()), $file->getClientOriginalName());
                        } elseif ($ext === 'pdf') {
                            $http = $http->attach('upload_pdf[]', file_get_contents($file->getRealPath()), $file->getClientOriginalName());
                        }
                    }
                }
                $receiptField = ClaimSubmissionValidator::RECEIPT_FILE_FIELD;
                if ($request->hasFile($receiptField)) {
                    foreach (Arr::wrap($request->file($receiptField)) as $file) {
                        $http = $http->attach($receiptField.'[]', file_get_contents($file->getRealPath()), $file->getClientOriginalName());
                    }
                }
            }
            $response = $http->post($url, $formData);
            $result = $response->json();
            if (($result['Code'] ?? null) == 300 || ($result['status'] ?? null) == 'success') {
                return response()->json(['status' => 'success', 'msg' => $result['msg'] ?? 'Claim submitted successfully.']);
            }

            return response()->json(['status' => 'error', 'msg' => $result['msg'] ?? 'Failed to submit claim.']);
        } catch (\Throwable $e) {
            // UPLOAD-SIZE-01: any failure is logged with a reference the user can quote
            $ref = strtoupper(substr(sha1(uniqid('', true)), 0, 8));
            \Illuminate\Support\Facades\Log::error('submitUploadClaim failed', ['ref' => $ref, 'emp' => session('employee_id'), 'error' => $e->getMessage(), 'at' => $e->getFile().':'.$e->getLine()]);

            return response()->json(['status' => 'error', 'msg' => "The claim could not be saved (ref {$ref}). Please try again or send the ref to the Xeasy team."]);
        }
    }

    public function submitActivityClaim(Request $request)
    {
        try {
            $employeeId = session('employee_id');
            $yearId = session('year_id');
            $companyId = session('company_id');
            $billDate = $request->activity_bill_date;
            $activityClaim = $request->activity_claim;
            $activityType = $request->activity_type;
            $activityRemark = $request->activity_remark ?? '';
            $trialNo = $request->trial_no ?? '';
            $totalAmount = $request->total_amount ?? 0;
            // Tracker #21: participant list (checked here as well as at the relay target).
            $ruleError = ClaimSubmissionValidator::first(app(ClaimSubmissionValidator::class)->validateActivityRequest($request));
            if ($ruleError !== null) {
                return response()->json(['status' => 'error', 'msg' => $ruleError]);
            }
            $formData = ['empid' => $employeeId, 'YearId' => $yearId, 'ComId' => $companyId, 'tokenid' => LegacyApi::extractToken(), 'claim_id' => $activityClaim, 'activity_type' => $activityType, 'bill_date' => $billDate, 'amount' => $totalAmount];
            if (! empty($activityRemark)) {
                $formData['activity_remark'] = $activityRemark;
            }
            if (! empty($trialNo)) {
                $formData['traill_no'] = $trialNo;
            }
            if ($request->has('crop_list') && is_array($request->crop_list)) {
                $formData['crop_list'] = implode(',', $request->crop_list);
            }
            if ($request->has('variety_list') && is_array($request->variety_list)) {
                $formData['variety_list'] = implode(',', $request->variety_list);
            }
            $excludedFields = ['_token', 'activity_claim', 'activity_type', 'activity_bill_date', 'trial_no', 'activity_remark', 'crop_list', 'variety_list', 'total_amount'];
            foreach ($request->all() as $key => $value) {
                if (! in_array($key, $excludedFields) && ! $request->hasFile($key) && ! is_array($value)) {
                    if ($value !== null && $value !== '') {
                        $formData[$key] = $value;
                    }
                }
            }
            $url = LegacyApi::baseUrl().'/extract-data.php?value=upload_activity_bill';
            $http = LegacyApi::http();
            foreach ($request->allFiles() as $fieldName => $files) {
                $flatFiles = is_array($files) ? Arr::flatten($files) : [$files];
                foreach ($flatFiles as $file) {
                    if ($file instanceof \Illuminate\Http\UploadedFile) {
                        $http = $http->attach($fieldName.'[]', file_get_contents($file->getRealPath()), $file->getClientOriginalName());
                    }
                }
            }
            $response = $http->post($url, $formData);
            $result = $response->json();
            if (isset($result['Code']) && $result['Code'] == 200) {
                return response()->json(['status' => 'success', 'msg' => $result['msg'] ?? 'Activity claim submitted successfully.']);
            }

            return response()->json(['status' => 'error', 'msg' => $result['msg'] ?? 'Failed to submit activity claim.']);
        } catch (\Exception $e) {
            return response()->json(['status' => 'error', 'msg' => 'There is some problem in your request: '.$e->getMessage()]);
        }
    }
}
