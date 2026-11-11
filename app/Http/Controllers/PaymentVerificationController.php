<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use App\Models\DigiNextPaymentFile;
use App\Models\AdvanceRequest;
use App\Models\AdvanceRequestActivity;

class PaymentVerificationController extends Controller
{

    const API_TIMEOUT = 60;
    const CHUNK_SIZE = 1000;


    const PAYMENT_STATUS_INSTRUCTED = 'InstructedPayment';
    const PAYMENT_STATUS_CONFIRMED = 'ConfirmedPayment';
    const PAYMENT_STATUS_UNSETTLED = 'Unsettled';
    const PAYMENT_STATUS_READY = 'ready';
    const PAYMENT_STATUS_READY_FOR_PYMNT = 'Ready for Payment';
    const PAYMENT_STATUS_PAID = 'paid';

    public function verifyBulk(Request $request)
    {
        try {
            $yearId = session('year_id');
            $confirmedBy = session('employee_id');

            $results = [];


            $expenseResult = $this->processExpensePayments($yearId, $confirmedBy);
            $results['expense'] = $expenseResult;

            $advanceResult = $this->processAdvancePayments($yearId, $confirmedBy);
            $results['advance'] = $advanceResult;

            return response()->json([
                'success' => true,
                'data' => $results
            ]);
        } catch (\Exception $e) {
            Log::error('Bulk payment verification failed', ['error' => $e->getMessage()]);
            return response()->json([
                'success' => false,
                'message' => 'Failed: ' . $e->getMessage()
            ], 500);
        }
    }


    /**
 * Verify only unsettled payments
 */
public function verifyUnsettled(Request $request)
{
    try {
        $yearId = session('year_id');
        $confirmedBy = session('employee_id');

        $results = [];

        // Process unsettled expense payments
        $expenseResult = $this->processUnsettledExpensePayments($yearId, $confirmedBy);
        $results['expense'] = $expenseResult;

        // Process unsettled advance payments
        $advanceResult = $this->processUnsettledAdvancePayments($yearId, $confirmedBy);
        $results['advance'] = $advanceResult;

        return response()->json([
            'success' => true,
            'data' => $results,
            'message' => 'Unsettled payments verification completed'
        ]);
    } catch (\Exception $e) {
        Log::error('Unsettled payment verification failed', ['error' => $e->getMessage()]);
        return response()->json([
            'success' => false,
            'message' => 'Failed: ' . $e->getMessage()
        ], 500);
    }
}



/**
 * Process unsettled expense payments only
 */
private function processUnsettledExpensePayments($yearId, $confirmedBy)
{
    $table = "y{$yearId}_monthexpensefinal";

    // Get only unsettled payments
    $activities = DB::connection(session('expense_connection'))
        ->table($table)
        ->where('payment_status', self::PAYMENT_STATUS_UNSETTLED)
        ->whereNotNull('EmployeeID')
        ->where('payment_amount', '>', 0)
        ->get();

    if ($activities->isEmpty()) {
        return [
            'total' => 0,
            'matched' => 0,
            'unsettled' => 0,
            'message' => 'No unsettled expense payments to verify'
        ];
    }

    // Reuse your existing processing logic
    return $this->processExpensePaymentsList($activities, $yearId, $confirmedBy);
}

/**
 * Process unsettled advance payments only
 */
private function processUnsettledAdvancePayments($yearId, $confirmedBy)
{
    // Get only unsettled advance payments
    $activities = AdvanceRequestActivity::with('employee')
        ->where('payment_status', self::PAYMENT_STATUS_UNSETTLED)
        ->get();

    if ($activities->isEmpty()) {
        return [
            'total' => 0,
            'matched' => 0,
            'unsettled' => 0,
            'message' => 'No unsettled advance payments to verify'
        ];
    }

    // Reuse your existing processing logic
    return $this->processAdvancePaymentsList($activities, $yearId, $confirmedBy);
}


/**
 * Process a list of expense payments (extracted from processExpensePayments)
 */
private function processExpensePaymentsList($activities, $yearId, $confirmedBy)
{
    $employeeIds = $activities->pluck('EmployeeID')->unique()->toArray();
    $accounts = $this->getEmployeeAccounts($employeeIds);

    $payments = [];
    $activityMap = [];

    foreach ($activities as $index => $activity) {
        $accountNo = $accounts[$activity->EmployeeID] ?? null;

        if (!$accountNo) {
            // Keep as unsettled with updated reason
            $this->updateUnsettledReason('expense', $activity, 'Still no account number', $yearId);
            continue;
        }

        $payments[] = [
            'beneficiary_account_number' => $accountNo,
            'amount' => (float) $activity->payment_amount,
            'date' => $activity->payment_instructed_date
                ? \Carbon\Carbon::parse($activity->payment_instructed_date)->format('Y-m-d')
                : now()->subDay()->format('Y-m-d'),
            'source' => 'Xeasy_Expense',
            'source_reference' => "EXP-{$activity->id}"
        ];

        $activityMap[count($payments) - 1] = [
            'activity' => $activity,
            'employee_id' => $activity->EmployeeID,
            'account_no' => $accountNo
        ];
    }

    if (empty($payments)) {
        return [
            'total' => 0,
            'matched' => 0,
            'unsettled' => count($activities),
            'message' => 'No valid payments to verify'
        ];
    }

    $apiResponse = $this->callBulkVerificationAPI($payments);

    if (!$apiResponse['success']) {
        throw new \Exception('API call failed: ' . ($apiResponse['message'] ?? 'Unknown error'));
    }

    $matched = 0;
    $unsettled = 0;

    foreach ($apiResponse['results'] as $result) {
        $originalIndex = $result['index'];

        if (!isset($activityMap[$originalIndex])) {
            Log::warning('No activity mapping found for index: ' . $originalIndex);
            continue;
        }

        $mappedData = $activityMap[$originalIndex];

        if ($result['match_status'] === 'matched') {
                        if (! $this->markAsMatched(
                'expense',
                $mappedData['activity'],
                $result['transaction'],
                $confirmedBy,
                $yearId
            )) { $unsettled++; } else { $matched++; }
        } else {
            $unsettled++;
            $reason = 'Payment still not found in bank';

            if (!empty($result['mismatch_reasons'])) {
                $reasonParts = array_map(function($r) {
                    return is_array($r) ? ($r['reason'] ?? json_encode($r)) : (string)$r;
                }, $result['mismatch_reasons']);
                $reason .= ' | Reason: ' . implode(', ', $reasonParts);
            }

            if (!empty($result['nearest_match'])) {
                $nearest = $result['nearest_match'];
                $reason .= " | Nearest Match - UTR: {$nearest['utr_number']}, Amount: {$nearest['amount']}, Date: {$nearest['value_date']}";
            }

            $this->updateUnsettledReason(
                'expense',
                $mappedData['activity'],
                $reason,
                $yearId
            );
        }
    }

    return [
        'total' => count($payments),
        'matched' => $matched,
        'unsettled' => $unsettled,
        'api_summary' => $apiResponse['summary'] ?? null
    ];
}

/**
 * Process a list of advance payments (extracted from processAdvancePayments)
 */
private function processAdvancePaymentsList($activities, $yearId, $confirmedBy)
{
    $employeeIds = $activities->pluck('employee.employee_id')->filter()->unique()->toArray();
    $accounts = $this->getEmployeeAccounts($employeeIds);

    $payments = [];
    $activityMap = [];

    foreach ($activities as $index => $activity) {
        $employeeId = $activity->employee->employee_id ?? null;
        $accountNo = $employeeId ? ($accounts[$employeeId] ?? null) : null;

        if (!$accountNo) {
            $this->updateUnsettledReason('advance', $activity, 'Still no account number', $yearId);
            continue;
        }

        $payments[] = [
            'beneficiary_account_number' => $accountNo,
            'amount' => (float) $activity->finance_approved_amount,
            'date' => $activity->payment_released_date
                ? \Carbon\Carbon::parse($activity->payment_released_date)->format('Y-m-d')
                : now()->subDay()->format('Y-m-d'),
            'source' => 'Xeasy_Advance',
            'source_reference' => "ADV-{$activity->id}"
        ];

        $activityMap[count($payments) - 1] = [
            'activity' => $activity,
            'employee_id' => $employeeId,
            'account_no' => $accountNo
        ];
    }

    if (empty($payments)) {
        return [
            'total' => 0,
            'matched' => 0,
            'unsettled' => count($activities),
            'message' => 'No valid payments to verify'
        ];
    }

    $apiResponse = $this->callBulkVerificationAPI($payments);

    if (!$apiResponse['success']) {
        throw new \Exception('API call failed: ' . ($apiResponse['message'] ?? 'Unknown error'));
    }

    $matched = 0;
    $unsettled = 0;

    foreach ($apiResponse['results'] as $result) {
        $originalIndex = $result['index'];

        if (!isset($activityMap[$originalIndex])) {
            Log::warning('No activity mapping found for index: ' . $originalIndex);
            continue;
        }

        $mappedData = $activityMap[$originalIndex];

        if ($result['match_status'] === 'matched') {
                        if (! $this->markAsMatched(
                'advance',
                $mappedData['activity'],
                $result['transaction'],
                $confirmedBy,
                $yearId
            )) { $unsettled++; } else { $matched++; }
        } else {
            $unsettled++;
            $reason = 'Payment still not found in bank';

            if (!empty($result['mismatch_reasons'])) {
                $reasonParts = array_map(function($r) {
                    return is_array($r) ? ($r['reason'] ?? json_encode($r)) : (string)$r;
                }, $result['mismatch_reasons']);
                $reason .= ' | Reason: ' . implode(', ', $reasonParts);
            }

            if (!empty($result['nearest_match'])) {
                $nearest = $result['nearest_match'];
                $reason .= " | Nearest Match - UTR: {$nearest['utr_number']}, Amount: {$nearest['amount']}, Date: {$nearest['value_date']}";
            }

            $this->updateUnsettledReason(
                'advance',
                $mappedData['activity'],
                $reason,
                $yearId
            );
        }
    }

    return [
        'total' => count($payments),
        'matched' => $matched,
        'unsettled' => $unsettled,
        'api_summary' => $apiResponse['summary'] ?? null
    ];
}

/**
 * Update unsettled reason without changing status
 */
private function updateUnsettledReason($type, $activity, $reason, $yearId)
{
    if ($type === 'expense') {
        DB::connection(session('expense_connection'))
            ->table("y{$yearId}_monthexpensefinal")
            ->where('id', $activity->id)
            ->update([
                'payment_unsettled_reason' => $reason,
                'payment_unsettled_date' => now()->toDateString()
            ]);
    } else {
        $activity->update([
            'payment_unsettled_reason' => $reason,
            'unsettled_at' => now()
        ]);
    }
}


    // public function resolveUnsettled(Request $request, $id)
    // {
    //     try {
    //         $yearId = session('year_id');

    //         DB::connection(session('expense_connection'))
    //             ->table("y{$yearId}_monthexpensefinal")
    //             ->where('id', $id)
    //             ->update([
    //                 'payment_status' => self::PAYMENT_STATUS_CONFIRMED,
    //                 'payment_confirmed_date' => now(),
    //                 'Fin_PayRemark' => 'Manual Resolution | ' . ($request->notes ?? '')
    //             ]);

    //         return response()->json([
    //             'success' => true
    //         ]);
    //     } catch (\Exception $e) {
    //         return response()->json([
    //             'success' => false,
    //             'message' => $e->getMessage()
    //         ]);
    //     }
    // }


public function resolveUnsettled(Request $request, $id)
{
    try {
        $yearId = session('year_id');
        $confirmedBy = session('employee_id');

        $adjustmentAmount = $request->adjustment_amount ?? 0;
        $utrreference = \App\Support\PaymentUtr::assert($request->utr_reference ?? null, 'Payment'); // UTR-01
        $paymentdate = $request->payment_date;
        $adjustmentType = $request->adjustment_type ?? '';
        $resolutionNotes = $request->resolution_notes ?? '';

        // Get payment record
        $payment = DB::connection(session('expense_connection'))
            ->table("y{$yearId}_monthexpensefinal")
            ->where('id', $id)
            ->first();

        if (!$payment) {
            return response()->json([
                'success' => false,
                'message' => 'Payment record not found'
            ]);
        }

        $paymentAmount = floatval($request->payment_amount ?? 0);
        $total_adjustment = floatval($payment->tatal_adjustment ?? 0);

        // Prepare remark
        $companyId = session('company_id');
        if ($companyId == 11 && function_exists('formatVvnrPaymentRemark')) {
            $remark = formatVvnrPaymentRemark($payment->EmployeeID, $payment->Month, $yearId, $paymentAmount, '', $payment);
        } else {
            $remark = 'Paid Rs. ' . number_format($paymentAmount, 2);

            if ($adjustmentAmount > 0) {
                if ($payment->total_activity_adjustment > 0 && $payment->total_expense_adjustment > 0) {
                    $currentExpenseAdjustment = floatval($payment->total_expense_adjustment);
                    $currentActivityAdjustment = floatval($payment->total_activity_adjustment);

                    $remark .= " | Adjusted with Activity Adv. Rs. "
                        . number_format($currentActivityAdjustment, 2)
                        . " and Expense Adj. Rs. "
                        . number_format($currentExpenseAdjustment, 2);

                } elseif ($payment->total_expense_adjustment > 0) {
                    $currentExpenseAdjustment = floatval($payment->total_expense_adjustment);
                    $remark .= " | Adjusted with Expense Adj. Rs. "
                        . number_format($currentExpenseAdjustment, 2);

                } elseif ($payment->total_activity_adjustment > 0) {
                    $currentActivityAdjustment = floatval($payment->total_activity_adjustment);
                    $remark .= " | Adjusted with Activity Adv. Rs. "
                        . number_format($currentActivityAdjustment, 2);
                }
            } else {
                $remark .= " | No adjustments";
            }

            // Check DA if exists
            $daAmount = 0;
            try {
                $claimsTable = "y{$yearId}_expenseclaims";
                $daRow = DB::connection(session('expense_connection'))->selectOne("
                    SELECT 
                        COALESCE(SUM(CASE WHEN FinancedTAmt > 0 THEN FinancedTAmt ELSE 0 END), 0) as da_fin,
                        COALESCE(SUM(CASE WHEN ApprTAmt > 0 THEN ApprTAmt ELSE 0 END), 0) as da_appr,
                        COALESCE(SUM(CASE WHEN VerifyTAmt > 0 THEN VerifyTAmt ELSE 0 END), 0) as da_ver,
                        COALESCE(SUM(CASE WHEN FilledTAmt > 0 THEN FilledTAmt ELSE 0 END), 0) as da_fill
                    FROM {$claimsTable}
                    WHERE CrBy = ? AND ClaimMonth = ? AND ClaimStatus != 'Deactivate' AND FilledOkay = 1 AND (ClaimId = 19 OR ClaimId = 20)
                ", [$payment->EmployeeID, $payment->Month]);
                if ($daRow) {
                    $daAmount = floatval($daRow->da_fin > 0 ? $daRow->da_fin : ($daRow->da_appr > 0 ? $daRow->da_appr : ($daRow->da_ver > 0 ? $daRow->da_ver : ($daRow->da_fill ?? 0))));
                }
            } catch (\Exception $e) {}

            if ($daAmount == 0) {
                $payAppr = floatval($payment->Approved_Amount ?? ($payment->Verified_Amount ?? 0));
                $payAdj = floatval($payment->total_adjustment ?? (floatval($payment->total_expense_adjustment ?? 0) + floatval($payment->total_activity_adjustment ?? 0)));
                if ($payAppr > 0 && $paymentAmount > 0 && ($payAppr - $payAdj - $paymentAmount) > 0) {
                    $daAmount = round($payAppr - $payAdj - $paymentAmount, 2);
                }
            }

            if ($daAmount > 0) {
                $daStr = (floor($daAmount) == $daAmount) ? number_format($daAmount, 0, '', '') : number_format($daAmount, 2);
                $remark .= ", DA Rs. {$daStr}";
            }
        }

        if (!empty($resolutionNotes)) {
            $remark .= " | " . $resolutionNotes;
        }

        // Update data (FIXED SYNTAX HERE)
        $updateData = [
            'Fin_PayAmt' => $paymentAmount,
            'Fin_PayBy' => $confirmedBy,
            'Fin_PayDate' => $paymentdate,
            'payment_date' => $paymentdate,
            'payment_amount' => $paymentAmount,
            'utr_number' => $utrreference,
            'payment_status' => self::PAYMENT_STATUS_CONFIRMED,
            'payment_confirmed_date' => $paymentdate,
            'payment_unsettled_date' => null,
            'payment_unsettled_reason' => null,
            'Fin_PayOption' =>'Bank',
            'Fin_PayRemark' => $remark
        ];

        DB::connection(session('expense_connection'))
            ->table("y{$yearId}_monthexpensefinal")
            ->where('id', $id)
            ->update($updateData);

        return response()->json([
            'success' => true,
            'message' => $remark
        ]);

    } catch (\Exception $e) {
        return response()->json([
            'success' => false,
            'message' => $e->getMessage()
        ]);
    }
}





    private function processExpensePayments($yearId, $confirmedBy)
    {
        $table = "y{$yearId}_monthexpensefinal";


        $activities = DB::connection(session('expense_connection'))
            ->table($table)
            ->whereIn('payment_status', [self::PAYMENT_STATUS_INSTRUCTED])
            ->whereNotNull('EmployeeID')
            ->where('payment_amount', '>', 0)
            ->where('is_exported', '=', 1)
            ->get();

        if ($activities->isEmpty()) {
            return [
                'total' => 0,
                'matched' => 0,
                'unsettled' => 0,
                'message' => 'No expense payments to verify'
            ];
        }


        $employeeIds = $activities->pluck('EmployeeID')->unique()->toArray();
        $accounts = $this->getEmployeeAccounts($employeeIds);

        $payments = [];
        $activityMap = [];

        foreach ($activities as $index => $activity) {
            $accountNo = $accounts[$activity->EmployeeID] ?? null;

            if (!$accountNo) {

                $this->markAsUnsettled('expense', $activity, $activity->EmployeeID, 'No account number', $yearId);
                continue;
            }

            $payments[] = [
                'beneficiary_account_number' => $accountNo,
                'amount' => (float) $activity->payment_amount,
                'date' => $activity->payment_instructed_date
                    ? \Carbon\Carbon::parse($activity->payment_instructed_date)->format('Y-m-d')
                    : now()->subDay()->format('Y-m-d'),
                'source' => 'Xeasy_Expense',
                'source_reference' => "EXP-{$activity->id}"
            ];

            $activityMap[count($payments) - 1] = [
                'activity' => $activity,
                'employee_id' => $activity->EmployeeID,
                'account_no' => $accountNo
            ];
        }

        if (empty($payments)) {
            return [
                'total' => 0,
                'matched' => 0,
                'unsettled' => count($activities),
                'message' => 'No valid payments to verify'
            ];
        }


        $apiResponse = $this->callBulkVerificationAPI($payments);

        if (!$apiResponse['success']) {
            throw new \Exception('API call failed: ' . ($apiResponse['message'] ?? 'Unknown error'));
        }


        $matched = 0;
        $unsettled = 0;

        foreach ($apiResponse['results'] as $result) {
            $originalIndex = $result['index'];

            if (!isset($activityMap[$originalIndex])) {
                Log::warning('No activity mapping found for index: ' . $originalIndex);
                continue;
            }

            $mappedData = $activityMap[$originalIndex];

            if ($result['match_status'] === 'matched') {
                                if (! $this->markAsMatched(
                    'expense',
                    $mappedData['activity'],
                    $result['transaction'],
                    $confirmedBy,
                    $yearId
                )) { $unsettled++; } else { $matched++; }
            } else {
                $unsettled++;
                $reason = 'Payment not found in bank';

                if (!empty($result['mismatch_reasons'])) {
                    $reasonParts = array_map(function($r) {
                        return is_array($r) ? ($r['reason'] ?? json_encode($r)) : (string)$r;
                    }, $result['mismatch_reasons']);
                    $reason .= ' | Reason: ' . implode(', ', $reasonParts);
                }

                if (!empty($result['nearest_match'])) {
                    $nearest = $result['nearest_match'];

                    $reason .= " | Nearest Match - UTR: {$nearest['utr_number']}, Amount: {$nearest['amount']}, Date: {$nearest['value_date']}";
                }

                $this->markAsUnsettled(
                    'expense',
                    $mappedData['activity'],
                    $mappedData['employee_id'],
                    $reason,
                    $yearId
                );
            }
        }

        return [
            'total' => count($payments),
            'matched' => $matched,
            'unsettled' => $unsettled,
            'api_summary' => $apiResponse['summary'] ?? null
        ];
    }
    private function processAdvancePayments($yearId, $confirmedBy)
    {

        $activities = AdvanceRequestActivity::with('employee')
            ->whereIn('payment_status', [self::PAYMENT_STATUS_READY_FOR_PYMNT])
            ->where('is_exported', '=', 1)
            ->get();

        if ($activities->isEmpty()) {
            return [
                'total' => 0,
                'matched' => 0,
                'unsettled' => 0,
                'message' => 'No advance payments to verify'
            ];
        }


        $employeeIds = $activities->pluck('employee.employee_id')->filter()->unique()->toArray();
        $accounts = $this->getEmployeeAccounts($employeeIds);


        $payments = [];
        $activityMap = [];

        foreach ($activities as $index => $activity) {
            $employeeId = $activity->employee->employee_id ?? null;
            $accountNo = $employeeId ? ($accounts[$employeeId] ?? null) : null;

            if (!$accountNo) {

                $this->markAsUnsettled('advance', $activity, $employeeId, 'No account number', $yearId);
                continue;
            }


            $payments[] = [
                'beneficiary_account_number' => $accountNo,
                'amount' => (float) $activity->finance_approved_amount,
                'date' => $activity->payment_released_date
                    ? \Carbon\Carbon::parse($activity->payment_released_date)->format('Y-m-d')
                    : now()->subDay()->format('Y-m-d'),
                'source' => 'Xeasy_Advance',
                'source_reference' => "ADV-{$activity->id}"
            ];

            // Store mapping
            $activityMap[count($payments) - 1] = [
                'activity' => $activity,
                'employee_id' => $employeeId,
                'account_no' => $accountNo
            ];
        }

        if (empty($payments)) {
            return [
                'total' => 0,
                'matched' => 0,
                'unsettled' => count($activities),
                'message' => 'No valid payments to verify'
            ];
        }

        $apiResponse = $this->callBulkVerificationAPI($payments);

        if (!$apiResponse['success']) {
            throw new \Exception('API call failed: ' . ($apiResponse['message'] ?? 'Unknown error'));
        }

        // Process results
        $matched = 0;
        $unsettled = 0;

        foreach ($apiResponse['results'] as $result) {
            $originalIndex = $result['index'];

            if (!isset($activityMap[$originalIndex])) {
                Log::warning('No activity mapping found for index: ' . $originalIndex);
                continue;
            }

            $mappedData = $activityMap[$originalIndex];

            if ($result['match_status'] === 'matched') {

                                if (! $this->markAsMatched(
                    'advance',
                    $mappedData['activity'],
                    $result['transaction'],
                    $confirmedBy,
                    $yearId
                )) { $unsettled++; } else { $matched++; }
            } else {

                $unsettled++;

                // ✅ BUILD PROPER REASON
                $reason = 'Payment not found in bank';

                if (!empty($result['mismatch_reasons'])) {
                    $reasonParts = array_map(function($r) {
                        return is_array($r) ? ($r['reason'] ?? json_encode($r)) : (string)$r;
                    }, $result['mismatch_reasons']);
                    $reason .= ' | Reason: ' . implode(', ', $reasonParts);
                }

                if (!empty($result['nearest_match'])) {
                    $nearest = $result['nearest_match'];

                    $reason .= " | Nearest Match - UTR: {$nearest['utr_number']}, Amount: {$nearest['amount']}, Date: {$nearest['value_date']}";
                }

                $this->markAsUnsettled(
                    'advance',
                    $mappedData['activity'],
                    $mappedData['employee_id'],
                    $reason,
                    $yearId
                );
            }
        }
        return [
            'total' => count($payments),
            'matched' => $matched,
            'unsettled' => $unsettled,
            'api_summary' => $apiResponse['summary'] ?? null
        ];
    }


    private function callBulkVerificationAPI(array $payments): array
    {
        try {
            $apiUrl = env('PAYMENT_VERIFY_API_URL');
            $apiKey = env('PAYMENT_VERIFICATION_API_KEY');

            Log::info('Calling bulk payment verification API', [
                'url' => $apiUrl,
                'payment_count' => count($payments),
                'has_api_key' => !empty($apiKey)
            ]);


            Log::info('Bulk verification payload', ['payments' => $payments]);


            $http = Http::timeout(self::API_TIMEOUT)
                ->withHeaders([
                    'Content-Type' => 'application/json',
                    'Accept' => 'application/json'
                ]);


            if (!empty($apiKey)) {
                $http->withHeaders([
                    'X-API-Key' => $apiKey
                ]);
                Log::info('Added X-API-Key header to request');
            } else {
                Log::warning('X-API-Key not configured');
            }


            $response = $http->post($apiUrl, ['payments' => $payments]);


            Log::info('Bulk verification response', [
                'status' => $response->status(),
                'headers' => $response->headers(),
                'body' => $response->body()
            ]);

            if ($response->successful()) {
                $data = $response->json();


                if (isset($data['success']) && isset($data['results'])) {
                    return $data;
                }

                return [
                    'success' => false,
                    'message' => 'Invalid API response format',
                    'results' => [],
                    'summary' => ['total' => 0, 'matched' => 0, 'not_found' => 0]
                ];
            }


            if ($response->status() === 422) {
                $errorData = $response->json();
                Log::error('API validation failed', ['errors' => $errorData['errors'] ?? []]);
                return [
                    'success' => false,
                    'message' => $errorData['message'] ?? 'Validation failed',
                    'errors' => $errorData['errors'] ?? [],
                    'results' => [],
                    'summary' => ['total' => 0, 'matched' => 0, 'not_found' => 0]
                ];
            }


            if ($response->status() === 401 || $response->status() === 403) {
                Log::error('API authentication failed', ['status' => $response->status()]);
                return [
                    'success' => false,
                    'message' => 'API authentication failed. Check X-API-Key header.',
                    'results' => [],
                    'summary' => ['total' => 0, 'matched' => 0, 'not_found' => 0]
                ];
            }

            return [
                'success' => false,
                'message' => 'API returned error: ' . $response->status(),
                'results' => [],
                'summary' => ['total' => 0, 'matched' => 0, 'not_found' => 0]
            ];
        } catch (\Exception $e) {
            Log::error('Bulk API call failed', ['error' => $e->getMessage()]);
            return [
                'success' => false,
                'message' => 'API call exception: ' . $e->getMessage(),
                'results' => [],
                'summary' => ['total' => 0, 'matched' => 0, 'not_found' => 0]
            ];
        }
    }

    private function getEmployeeAccounts($employeeIds)
    {
        if (empty($employeeIds)) {
            return [];
        }

        try {
            return DB::connection('hrims')
                ->table('hrm_employee_general')
                ->whereIn('EmployeeID', $employeeIds)
                ->whereNotNull('AccountNo')
                ->where('AccountNo', '!=', '')
                ->pluck('AccountNo', 'EmployeeID')
                ->toArray();
        } catch (\Exception $e) {
            Log::error('Failed to fetch employee accounts', ['error' => $e->getMessage()]);
            return [];
        }
    }

    /**
     * Mark payment as matched
     */
    private function markAsMatched($type, $activity, $transaction, $confirmedBy, $yearId)
    {
        // UTR-01: a bank match without a UTR is not a confirmed payment
        if (\App\Support\PaymentUtr::missingProof($transaction['utr_number'] ?? null, $transaction['bank_reference'] ?? null, $transaction['credit_reference'] ?? null)) { // UTR-04
            $this->updateUnsettledReason($type, $activity, 'Bank match has no UTR - enter the UTR to confirm', $yearId);

            return false;
        }
        $refNo = $transaction['utr_number'] ?? $transaction['bank_reference'] ?? 'N/A';
        $txnDate = $transaction['value_date'] ?? $transaction['date'] ?? now()->toDateString();

        // LV-C06: one bank transaction pays one month / advance line, for the same amount
        $expected = (float) ($type === 'expense' ? ($activity->payment_amount ?? 0) : ($activity->payment_amount ?? $activity->finance_approved_amount ?? 0));
        if (isset($transaction['amount']) && $expected > 0 && abs((float) $transaction['amount'] - $expected) > 1) {
            $this->updateUnsettledReason($type, $activity, 'Bank amount '.$transaction['amount'].' differs from the payment amount '.$expected, $yearId);

            return false;
        }
        $refs = array_values(array_filter([$transaction['utr_number'] ?? null, $transaction['bank_reference'] ?? null], fn ($v) => trim((string) $v) !== '' && $v !== 'N/A'));
        if ($refs) {
            $app = $type === 'expense' ? 'Xeasy_Expense' : 'Xeasy_Advance';
            $used = DigiNextPaymentFile::where('match_status', 'MATCHED')->whereIn('transaction_refno', $refs)
                ->where(function ($q) use ($app, $activity) {
                    $q->where('application', '!=', $app)->orWhere('matched_activity_id', '!=', (int) $activity->id);
                })
                ->where('matched_activity_id', '>', 0)
                ->first(['id', 'application', 'matched_activity_id', 'employee_id', 'month']);
            if ($used) {
                $this->updateUnsettledReason($type, $activity, "Bank transaction {$refs[0]} is already matched to {$used->application} record {$used->matched_activity_id} (employee {$used->employee_id}, month {$used->month})", $yearId);

                return false;
            }
        }
        $finremark = '';
        $companyId = session('company_id');
        $daAmount = 0;
        try {
            $claimsTable = "y{$yearId}_expenseclaims";
            $daRow = DB::connection(session('expense_connection'))->selectOne("
                SELECT 
                    COALESCE(SUM(CASE WHEN FinancedTAmt > 0 THEN FinancedTAmt ELSE 0 END), 0) as da_fin,
                    COALESCE(SUM(CASE WHEN ApprTAmt > 0 THEN ApprTAmt ELSE 0 END), 0) as da_appr,
                    COALESCE(SUM(CASE WHEN VerifyTAmt > 0 THEN VerifyTAmt ELSE 0 END), 0) as da_ver,
                    COALESCE(SUM(CASE WHEN FilledTAmt > 0 THEN FilledTAmt ELSE 0 END), 0) as da_fill
                FROM {$claimsTable}
                WHERE CrBy = ? AND ClaimMonth = ? AND ClaimStatus != 'Deactivate' AND FilledOkay = 1 AND (ClaimId = 19 OR ClaimId = 20)
            ", [$activity->EmployeeID, $activity->Month]);
            if ($daRow) {
                $daAmount = floatval($daRow->da_fin > 0 ? $daRow->da_fin : ($daRow->da_appr > 0 ? $daRow->da_appr : ($daRow->da_ver > 0 ? $daRow->da_ver : ($daRow->da_fill ?? 0))));
            }
        } catch (\Exception $e) {}

        if ($daAmount == 0) {
            $actAppr = floatval($activity->Approved_Amount ?? ($activity->Verified_Amount ?? 0));
            $actPaid = floatval($transaction['amount'] ?? ($activity->payment_amount ?? 0));
            $actAdj = floatval($activity->total_adjustment ?? (floatval($activity->total_expense_adjustment ?? 0) + floatval($activity->total_activity_adjustment ?? 0)));
            if ($actAppr > 0 && $actPaid > 0 && ($actAppr - $actAdj - $actPaid) > 0) {
                $daAmount = round($actAppr - $actAdj - $actPaid, 2);
            }
        }

        if ($type === 'expense' && $companyId == 11 && function_exists('formatVvnrPaymentRemark')) {
            $finremark = formatVvnrPaymentRemark($activity->EmployeeID, $activity->Month, $yearId, $transaction['amount'] ?? $activity->payment_amount, '', $activity);
        } elseif ((!empty($activity->total_expense_adjustment) && $activity->total_expense_adjustment > 0) && (!empty($activity->total_activity_adjustment) && $activity->total_activity_adjustment > 0)) {
            $finremark = "Paid Rs. {$transaction['amount']}, Adjusted with Expense Adv. Rs. {$activity->total_expense_adjustment} | Adjusted with Activity Adv. Rs. {$activity->total_activity_adjustment}";
        } elseif (!empty($activity->total_expense_adjustment) && $activity->total_expense_adjustment > 0) {
            $finremark = "Paid Rs. {$transaction['amount']}, Adjusted with Expense Adv. Rs. {$activity->total_expense_adjustment}";
        } elseif (!empty($activity->total_activity_adjustment) && $activity->total_activity_adjustment > 0) {
            $finremark = "Paid Rs. {$transaction['amount']}, Adjusted with Activity Adv. Rs. {$activity->total_activity_adjustment}";
        } else {
            $finremark = "Paid Rs. {$transaction['amount']}, No adjustments";
        }

        if ($type === 'expense' && $companyId != 11 && $daAmount > 0 && stripos($finremark, 'DA Rs') === false) {
            $daStr = (floor($daAmount) == $daAmount) ? number_format($daAmount, 0, '', '') : number_format($daAmount, 2);
            $finremark .= ", DA Rs. {$daStr}";
        }

        if ($type === 'expense') {
            DB::connection(session('expense_connection'))
                ->table("y{$yearId}_monthexpensefinal")
                ->where('id', $activity->id)
                ->update([
                    'payment_status' => self::PAYMENT_STATUS_CONFIRMED,
                    'Fin_PayDate' => $txnDate,
                    'payment_date' => $txnDate,
                    'payment_confirmed_date' => now()->toDateString(),
                    'utr_number'=>$transaction['utr_number'] ?? '' ,
                    'bank_reference'=>$transaction['bank_reference'] ?? '' ,
                    'Fin_PayRemark' => $finremark,
                    'Fin_PayAmt' => $transaction['amount'] ?? $activity->payment_amount,
                    'Fin_PayOption' =>'Bank',
                    'Fin_PayBy'=> $confirmedBy,
                ]);
        } else {
            $activity->update([
                'payment_status' => self::PAYMENT_STATUS_PAID,
                'payment_date' => $txnDate,
                'utr_number'=>$transaction['utr_number'] ?? '' ,
                'bank_reference'=>$transaction['bank_reference'] ?? '' ,
                'payment_remark' =>  $finremark,
                'confirmed_at' => now()
            ]);

            // If advance request is flagged as imprest, sync to employee_ledger_balances
            $advanceRequest = $activity->employee->advanceRequest ?? null;
            $isImprest = ($advanceRequest && $advanceRequest->is_imprest) || (!empty($activity->is_imprest));
            if ($isImprest && $activity->employee) {
                $apiFinance = app(\App\Http\Controllers\Api\Advance\AdvanceFinanceController::class);
                $apiFinance->syncImprestToEmployeeLedger(
                    $activity->employee->employee_id,
                    $transaction['amount'] ?? $activity->payment_amount ?? $activity->finance_approved_amount,
                    $txnDate,
                    $yearId,
                    $activity->employee->company_id ?? 1
                );
            }
        }

        // Save to payment file
        DigiNextPaymentFile::create([
            'transaction_refno' => $refNo,
            'amount' => $transaction['amount'] ?? $activity->payment_amount ?? $activity->finance_approved_amount,
            'beneficiary_account_number' => $transaction['beneficiary_account_number'] ?? null,
            'beneficiary_name' => $transaction['beneficiary_name'] ?? null,
            'value_date' => $txnDate,
            'match_status' => 'MATCHED',
            'is_processed' => true,
            'processed_at' => now(),
            'processed_by' => $confirmedBy,
            'application' => $type === 'expense' ? 'Xeasy_Expense' : 'Xeasy_Advance',
            'year_id' => $yearId,
            'employee_id' => $activity->EmployeeID ?? $activity->employee->employee_id ?? null,
            // LV-C06: keep the link to the month / advance line that this bank row paid
            'matched_activity_id' => (int) $activity->id,
            ...(\Illuminate\Support\Facades\Schema::hasColumn('digi_next_payment_file', 'company_id') ? ['company_id' => (int) session('company_id', 1)] : []), // T-03
            'month' => $type === 'expense' ? (int) ($activity->Month ?? 0) : (int) ($activity->employee->advanceRequest->month ?? 0),
            'match_remarks' => ($type === 'expense' ? "Month {$activity->Month} record {$activity->id}" : "Advance line {$activity->id}").' (bank verification)',
        ]);
        if ($type !== 'expense' && ! empty($activity->payment_batch_id)) {
            \App\Services\Payments\PaymentBatches::refresh($activity->payment_batch_id); // LV-C06: close the batch when all its lines are paid
        }

        Log::info('Payment matched successfully', [
            'type' => $type,
            'activity_id' => $activity->id,
            'ref_no' => $refNo
        ]);
        return true;
    }

    /**
     * Mark payment as unsettled
     */
    private function markAsUnsettled($type, $activity, $employeeId, $reason, $yearId)
    {
        if ($type === 'expense') {
            DB::connection(session('expense_connection'))
                ->table("y{$yearId}_monthexpensefinal")
                ->where('id', $activity->id)
                ->update([
                    'payment_status' => self::PAYMENT_STATUS_UNSETTLED,
                    'payment_unsettled_date' => now()->toDateString(),
                    'payment_unsettled_reason' => $reason
                ]);
        } else {
            $activity->update([
                'payment_status' => self::PAYMENT_STATUS_UNSETTLED,
                'unsettled_at' => now(),
                'payment_unsettled_reason' => $reason
            ]);
        }

        Log::info('Payment marked as unsettled', [
            'type' => $type,
            'activity_id' => $activity->id,
            'employee_id' => $employeeId,
            'reason' => $reason
        ]);
    }

    /**
     * Get unsettled payments
     */
    public function getUnsettled(Request $request)
    {
        $yearId = (int) ($request->get('year_id', session('year_id')));
        $type = $request->get('type', 'all');

        $results = [];

        if ($type === 'expense' || $type === 'all') {
            $table = "y{$yearId}_monthexpensefinal";
            $expenseUnsettled = DB::connection(session('expense_connection'))
                ->table($table)
                ->where('payment_status', self::PAYMENT_STATUS_UNSETTLED)
                ->select('id as activity_id', 'EmployeeID as employee_id', 'payment_amount', 'payment_unsettled_date as unsettled_date')
                ->get();

            $results['expense'] = $expenseUnsettled;
        }

        // UAT-03: VVNR has no advance tables; the advance part is skipped there instead of a 500
        if (($type === 'advance' || $type === 'all') && \Illuminate\Support\Facades\Schema::connection(session('expense_connection'))->hasTable('advance_request_activities') && \Illuminate\Support\Facades\Schema::connection(session('expense_connection'))->hasTable('advance_request_employees')) {
            // UAT-03: activities have no employee_id/unsettled_at; the employee sits on advance_request_employees.
            $advanceUnsettled = DB::connection(session('expense_connection'))
                ->table('advance_request_activities as a')
                ->leftJoin('advance_request_employees as e', 'e.id', '=', 'a.advance_request_employee_id')
                ->where('a.payment_status', self::PAYMENT_STATUS_UNSETTLED)
                ->whereNull('a.deleted_at')
                ->select('a.id as activity_id', 'e.employee_id', 'a.finance_approved_amount as payment_amount', 'a.updated_at as unsettled_date', 'a.payment_unsettled_reason')
                ->get();

            $results['advance'] = $advanceUnsettled;
        }

        return response()->json([
            'success' => true,
            'data' => $results
        ]);
    }

    /**
     * Retry unsettled payment
     */
    public function retryUnsettled(Request $request)
    {
        try {
            $type = $request->input('type');
            $activityId = $request->input('activity_id');
            $yearId = (int) ($request->input('year_id', session('year_id')));

            if ($type === 'expense') {
                $activity = DB::connection(session('expense_connection'))
                    ->table("y{$yearId}_monthexpensefinal")
                    ->where('id', $activityId)
                    ->first();

                if ($activity) {
                    DB::connection(session('expense_connection'))
                        ->table("y{$yearId}_monthexpensefinal")
                        ->where('id', $activityId)
                        ->update([
                            'payment_status' => self::PAYMENT_STATUS_INSTRUCTED,
                            'payment_unsettled_date' => null
                        ]);
                }
            } else {
                $activity = AdvanceRequestActivity::find($activityId);
                if ($activity) {
                    $activity->update([
                        'payment_status' => self::PAYMENT_STATUS_READY_FOR_PYMNT,
                        'unsettled_at' => null
                    ]);
                }
            }

            if (!$activity) {
                return response()->json([
                    'success' => false,
                    'message' => 'Activity not found'
                ], 404);
            }

            return response()->json([
                'success' => true,
                'message' => 'Retry initiated'
            ]);
        } catch (\Exception $e) {
            Log::error('Retry failed', ['error' => $e->getMessage()]);
            return response()->json([
                'success' => false,
                'message' => 'Retry failed: ' . $e->getMessage()
            ], 500);
        }
    }
}
