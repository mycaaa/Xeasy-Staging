<?php

namespace App\Services\Matrix;

use App\Helpers\DatabaseSwitcher;
use App\Models\AdvanceRequestActivity;
use App\Models\MatrixPaymentInstruction;
use App\Models\MatrixSyncState;
use Exception;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class MatrixPaymentManager
{
    protected MatrixApiService $apiService;
    protected MatrixPaymentPayloadBuilder $payloadBuilder;

    public function __construct(MatrixApiService $apiService, MatrixPaymentPayloadBuilder $payloadBuilder)
    {
        $this->apiService = $apiService;
        $this->payloadBuilder = $payloadBuilder;
    }

    /**
     * Push selected expense reimbursement records to Matrix
     *
     * @param array $recordIds IDs in y{yearId}_monthexpensefinal
     * @param int $yearId
     * @param int $companyId
     * @return array
     */
    public function pushReimbursements(array $recordIds, int $yearId, int $companyId = 1): array
    {
        if (empty($recordIds)) {
            return ['success' => false, 'message' => 'No records selected'];
        }

        $connectionName = DatabaseSwitcher::getExpenseConnection($companyId);
        $tableName = "y{$yearId}_monthexpensefinal";

        $records = DB::connection($connectionName)
            ->table($tableName)
            ->whereIn('id', $recordIds)
            ->get();

        if ($records->isEmpty()) {
            return ['success' => false, 'message' => 'Selected payment records not found'];
        }

        // 1. Separate zero/negative amount records from positive payable records
        $zeroRecords = $records->filter(fn($r) => (float)($r->payment_amount ?? 0) <= 0);
        $positiveRecords = $records->filter(fn($r) => (float)($r->payment_amount ?? 0) > 0);

        // Auto-finalize zero amounts locally (matching Xeasy core logic)
        if ($zeroRecords->isNotEmpty()) {
            $zeroIds = $zeroRecords->pluck('id')->toArray();
            DB::connection($connectionName)
                ->table($tableName)
                ->whereIn('id', $zeroIds)
                ->update([
                    'payment_status' => 'ConfirmedPayment',
                    'payment_confirmed_date' => now()->toDateString(),
                    'Fin_PayDate' => now()->toDateString(),
                    'payment_date' => now()->toDateString(),
                    'Fin_PayRemark' => \App\Support\SystemRemark::mark('Zero payment amount - auto confirmed locally'), // REMARK-AUTO-01
                ]);
        }

        if ($positiveRecords->isEmpty()) {
            return [
                'success' => true,
                'message' => 'All selected records had zero payable amount and were marked confirmed locally.',
                'pushed_count' => 0,
                'zero_count' => $zeroRecords->count(),
            ];
        }

        // 2. Build instructions payload
        $batchRef = 'PAY_BATCH_REIMB_' . now()->format('Ymd_His');
        $rawInstructions = $this->payloadBuilder->buildReimbursementInstructions(
            $positiveRecords,
            $yearId,
            $companyId,
            $batchRef
        );

        $results = [];
        $totalPushed = 0;
        $failedCount = 0;

        // 3. Chunk in groups of 500 per Matrix API spec
        $chunkSize = (int) config('matrix.batch_size', 500);
        $chunks = array_chunk($rawInstructions, $chunkSize);

        foreach ($chunks as $chunk) {
            // Strip internal keys before sending to Matrix
            $apiPayload = array_map(function ($item) {
                unset($item['_local_record_id'], $item['_local_year_id'], $item['_local_company_id'], $item['_local_type']);
                return $item;
            }, $chunk);

            $apiResponse = $this->apiService->pushPaymentInstructions($apiPayload);

            if (!$apiResponse['success']) {
                Log::error('Matrix reimbursement push chunk failed', ['response' => $apiResponse]);
                $failedCount += count($chunk);
                continue;
            }

            // Map API results back to local records
            $itemResults = $apiResponse['results'] ?? [];
            $resultByRef = [];
            foreach ($itemResults as $res) {
                if (isset($res['source_ref'])) {
                    $resultByRef[$res['source_ref']] = $res;
                }
            }

            foreach ($chunk as $instruction) {
                $ref = $instruction['source_ref'];
                $itemRes = $resultByRef[$ref] ?? [];
                $resultType = $itemRes['result'] ?? 'created';
                $matrixId = $itemRes['id'] ?? null;

                // Save or update local Matrix instruction tracker
                MatrixPaymentInstruction::updateOrCreate(
                    ['source_ref' => $ref],
                    [
                        'payment_type' => 'reimbursement',
                        'company_id' => $companyId,
                        'year_id' => $yearId,
                        'record_id' => $instruction['_local_record_id'],
                        'batch_ref' => $batchRef,
                        'payee_focus_code' => $instruction['payee_focus_code'],
                        'payee_name' => $instruction['payee_name'],
                        'payee_type' => $instruction['payee_type'],
                        'beneficiary_account_number' => $instruction['beneficiary_account_number'],
                        'beneficiary_ifsc' => $instruction['beneficiary_ifsc'],
                        'beneficiary_bank_name' => $instruction['beneficiary_bank_name'],
                        'amount' => $instruction['amount'],
                        'payment_mode' => $instruction['payment_mode'],
                        'narration' => $instruction['narration'],
                        'instructed_at' => $instruction['instructed_at'],
                        'matrix_id' => $matrixId,
                        'matrix_status' => $resultType === 'already_accepted' ? MatrixPaymentInstruction::STATUS_APPROVED : MatrixPaymentInstruction::STATUS_CREATED,
                        'focus_payload' => $instruction['focus'],
                        'request_payload' => $instruction,
                        'response_payload' => $itemRes,
                        'last_synced_at' => now(),
                    ]
                );

                // If Matrix held it, mark row in monthexpensefinal as InstructedPayment
                if (in_array($resultType, ['created', 'updated', 'reopened', 'unchanged', 'already_accepted'])) {
                    DB::connection($connectionName)
                        ->table($tableName)
                        ->where('id', $instruction['_local_record_id'])
                        ->update([
                            'payment_status' => 'InstructedPayment',
                            'payment_instructed_date' => now(),
                        ]);
                    $totalPushed++;
                } else {
                    $failedCount++;
                }
            }

            $results[] = $apiResponse;
        }

        return [
            'success' => $failedCount === 0,
            'batch_ref' => $batchRef,
            'pushed_count' => $totalPushed,
            'failed_count' => $failedCount,
            'zero_count' => $zeroRecords->count(),
            'message' => "Successfully pushed {$totalPushed} payment(s) to Matrix" . ($zeroRecords->count() > 0 ? " ({$zeroRecords->count()} zero-amount auto-confirmed)" : "") . ($failedCount > 0 ? " ({$failedCount} failed)" : ""),
            'details' => $results,
        ];
    }

    /**
     * Push selected advance request activities to Matrix
     *
     * @param array $activityIds
     * @param int $companyId
     * @return array
     */
    public function pushAdvances(array $activityIds, int $companyId = 1): array
    {
        if (empty($activityIds)) {
            return ['success' => false, 'message' => 'No advance activities selected'];
        }

        $activities = AdvanceRequestActivity::with(['employee.advanceRequest', 'activityType'])
            ->whereIn('id', $activityIds)
            ->get();

        if ($activities->isEmpty()) {
            return ['success' => false, 'message' => 'Selected advance activities not found'];
        }

        $batchRef = 'PAY_BATCH_ADV_' . now()->format('Ymd_His');
        $rawInstructions = $this->payloadBuilder->buildAdvanceInstructions($activities, $companyId, $batchRef);

        if (empty($rawInstructions)) {
            return ['success' => false, 'message' => 'No valid advance activities with amount > 0 to push'];
        }

        $results = [];
        $totalPushed = 0;
        $failedCount = 0;

        $chunkSize = (int) config('matrix.batch_size', 500);
        $chunks = array_chunk($rawInstructions, $chunkSize);

        foreach ($chunks as $chunk) {
            $apiPayload = array_map(function ($item) {
                unset($item['_local_record_id'], $item['_local_year_id'], $item['_local_company_id'], $item['_local_type']);
                return $item;
            }, $chunk);

            $apiResponse = $this->apiService->pushPaymentInstructions($apiPayload);

            if (!$apiResponse['success']) {
                Log::error('Matrix advance push chunk failed', ['response' => $apiResponse]);
                $failedCount += count($chunk);
                continue;
            }

            $itemResults = $apiResponse['results'] ?? [];
            $resultByRef = [];
            foreach ($itemResults as $res) {
                if (isset($res['source_ref'])) {
                    $resultByRef[$res['source_ref']] = $res;
                }
            }

            foreach ($chunk as $instruction) {
                $ref = $instruction['source_ref'];
                $itemRes = $resultByRef[$ref] ?? [];
                $resultType = $itemRes['result'] ?? 'created';
                $matrixId = $itemRes['id'] ?? null;

                MatrixPaymentInstruction::updateOrCreate(
                    ['source_ref' => $ref],
                    [
                        'payment_type' => 'advance',
                        'company_id' => $companyId,
                        'year_id' => $instruction['_local_year_id'],
                        'record_id' => $instruction['_local_record_id'],
                        'batch_ref' => $batchRef,
                        'payee_focus_code' => $instruction['payee_focus_code'],
                        'payee_name' => $instruction['payee_name'],
                        'payee_type' => $instruction['payee_type'],
                        'beneficiary_account_number' => $instruction['beneficiary_account_number'],
                        'beneficiary_ifsc' => $instruction['beneficiary_ifsc'],
                        'beneficiary_bank_name' => $instruction['beneficiary_bank_name'],
                        'amount' => $instruction['amount'],
                        'payment_mode' => $instruction['payment_mode'],
                        'narration' => $instruction['narration'],
                        'instructed_at' => $instruction['instructed_at'],
                        'matrix_id' => $matrixId,
                        'matrix_status' => $resultType === 'already_accepted' ? MatrixPaymentInstruction::STATUS_APPROVED : MatrixPaymentInstruction::STATUS_CREATED,
                        'focus_payload' => $instruction['focus'],
                        'request_payload' => $instruction,
                        'response_payload' => $itemRes,
                        'last_synced_at' => now(),
                    ]
                );

                if (in_array($resultType, ['created', 'updated', 'reopened', 'unchanged', 'already_accepted'])) {
                    AdvanceRequestActivity::where('id', $instruction['_local_record_id'])
                        ->update([
                            'payment_status' => AdvanceRequestActivity::PAYMENT_STATUS_READY,
                            'payment_released_date' => now(),
                        ]);
                    $totalPushed++;
                } else {
                    $failedCount++;
                }
            }

            $results[] = $apiResponse;
        }

        return [
            'success' => $failedCount === 0,
            'batch_ref' => $batchRef,
            'pushed_count' => $totalPushed,
            'failed_count' => $failedCount,
            'message' => "Successfully pushed {$totalPushed} advance payment(s) to Matrix" . ($failedCount > 0 ? " ({$failedCount} failed)" : ""),
            'details' => $results,
        ];
    }

    /**
     * Poll Matrix updates incrementally and apply outcomes to Xeasy tables
     *
     * @return array
     */
    public function syncUpdates(): array
    {
        $syncCursorKey = 'matrix_payment_updates_next_since';
        $since = MatrixSyncState::getValue($syncCursorKey);

        $totalProcessed = 0;
        $updatedInstructions = 0;
        $hasMore = true;
        $maxLoops = 20; // safety ceiling to prevent infinite loop in single command
        $currentLoop = 0;

        Log::info('Starting Matrix payment updates sync', ['since' => $since]);

        while ($hasMore && $currentLoop < $maxLoops) {
            $currentLoop++;
            $response = $this->apiService->fetchUpdates($since, 200);

            if (!($response['success'] ?? false)) {
                MatrixSyncState::setValue($syncCursorKey, $since, 'error', 'API returned unsuccessful response');
                return ['success' => false, 'message' => 'Sync failed'];
            }

            $data = $response['data'] ?? [];
            $nextSince = $response['next_since'] ?? null;
            $hasMore = (bool) ($response['has_more'] ?? false);

            foreach ($data as $item) {
                $totalProcessed++;
                $sourceRef = $item['source_ref'] ?? null;
                if (!$sourceRef) {
                    continue;
                }

                $instruction = MatrixPaymentInstruction::where('source_ref', $sourceRef)->first();
                if (!$instruction) {
                    Log::warning('Received Matrix update for unknown source_ref', ['source_ref' => $sourceRef]);
                    continue;
                }

                $status = $item['status'] ?? null;
                $statusReason = $item['status_reason'] ?? null;
                $instructionNo = $item['instruction_no'] ?? null;
                $utrNumber = $item['utr_number'] ?? null;
                $paidOn = $item['paid_on'] ?? null;

                // Update instruction tracker
                $instruction->update([
                    'matrix_status' => $status,
                    'status_reason' => $statusReason,
                    'instruction_no' => $instructionNo ?: $instruction->instruction_no,
                    'utr_number' => $utrNumber ?: $instruction->utr_number,
                    'paid_on' => $paidOn ?: $instruction->paid_on,
                    'last_synced_at' => now(),
                ]);

                // Apply outcome to underlying record
                $this->applyOutcomeToLocalRecord($instruction, $status, $statusReason, $instructionNo, $utrNumber, $paidOn);
                $updatedInstructions++;
            }

            // Save progressive cursor
            if ($nextSince) {
                $since = $nextSince;
                MatrixSyncState::setValue($syncCursorKey, $nextSince, 'idle');
            }
        }

        Log::info('Completed Matrix payment updates sync', [
            'total_processed' => $totalProcessed,
            'updated_instructions' => $updatedInstructions,
            'next_since' => $since,
        ]);

        return [
            'success' => true,
            'total_processed' => $totalProcessed,
            'updated_instructions' => $updatedInstructions,
            'next_since' => $since,
        ];
    }

    /**
     * Apply Matrix payment outcome to the local business tables
     */
    protected function applyOutcomeToLocalRecord(
        MatrixPaymentInstruction $instruction,
        string $status,
        ?string $statusReason,
        ?string $instructionNo,
        ?string $utrNumber,
        ?string $paidOn
    ): void {
        $paymentType = $instruction->payment_type;
        $recordId = $instruction->record_id;
        $yearId = $instruction->year_id;
        $companyId = $instruction->company_id;

        if ($paymentType === 'reimbursement') {
            if (!$yearId) {
                return;
            }

            $connectionName = DatabaseSwitcher::getExpenseConnection($companyId);
            $tableName = "y{$yearId}_monthexpensefinal";

            if ($status === MatrixPaymentInstruction::STATUS_PAID) {
                $txnDate = $paidOn ?: now()->toDateString();
                $remark = "Paid via Matrix Bank (UTR: {$utrNumber}, PI: {$instructionNo})";

                DB::connection($connectionName)
                    ->table($tableName)
                    ->where('id', $recordId)
                    ->update([
                        'payment_status' => 'ConfirmedPayment',
                        'Fin_PayDate' => $txnDate,
                        'payment_date' => $txnDate,
                        'payment_confirmed_date' => now()->toDateString(),
                        'utr_number' => $utrNumber ?? '',
                        'bank_reference' => $instructionNo ?? '',
                        'Fin_PayRemark' => \App\Support\SystemRemark::mark($remark),
                        'Fin_PayAmt' => $instruction->amount,
                        'Fin_PayOption' => 'Bank',
                    ]);
            } elseif (in_array($status, [MatrixPaymentInstruction::STATUS_FAILED, MatrixPaymentInstruction::STATUS_REJECTED, MatrixPaymentInstruction::STATUS_CANCELLED])) {
                DB::connection($connectionName)
                    ->table($tableName)
                    ->where('id', $recordId)
                    ->update([
                        'payment_status' => 'Unsettled',
                        'Fin_PayRemark' => \App\Support\SystemRemark::mark("Matrix {$status}: " . ($statusReason ?: 'Returned to finance')),
                    ]);
            }
        } elseif ($paymentType === 'advance') {
            $activity = AdvanceRequestActivity::with('employee')->find($recordId);
            if (!$activity) {
                return;
            }

            if ($status === MatrixPaymentInstruction::STATUS_PAID) {
                $txnDate = $paidOn ?: now()->toDateString();
                $remark = "Paid via Matrix Bank (UTR: {$utrNumber}, PI: {$instructionNo})";

                $activity->update([
                    'payment_status' => AdvanceRequestActivity::PAYMENT_STATUS_PAID,
                    'payment_date' => $txnDate,
                    'utr_number' => $utrNumber ?? '',
                    'bank_reference' => $instructionNo ?? '',
                    'payment_remark' => $remark,
                    'confirmed_at' => now(),
                ]);

                // Sync imprest to ledger if applicable
                $advanceRequest = $activity->employee->advanceRequest ?? null;
                $isImprest = ($advanceRequest && $advanceRequest->is_imprest) || (!empty($activity->is_imprest));
                if ($isImprest && $activity->employee) {
                    try {
                        $apiFinance = app(\App\Http\Controllers\Api\Advance\AdvanceFinanceController::class);
                        $apiFinance->syncImprestToEmployeeLedger(
                            $activity->employee->employee_id,
                            $instruction->amount,
                            $txnDate,
                            $yearId ?: session('year_id', 1),
                            $companyId
                        );
                    } catch (Exception $e) {
                        Log::error('Error syncing imprest ledger after Matrix payment', ['error' => $e->getMessage()]);
                    }
                }
            } elseif (in_array($status, [MatrixPaymentInstruction::STATUS_FAILED, MatrixPaymentInstruction::STATUS_REJECTED, MatrixPaymentInstruction::STATUS_CANCELLED])) {
                $activity->update([
                    'payment_status' => 'Unsettled',
                    'payment_remark' => "Matrix {$status}: " . ($statusReason ?: 'Returned to finance'),
                ]);
            }
        }
    }
}
