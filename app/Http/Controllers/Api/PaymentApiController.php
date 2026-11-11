<?php
namespace App\Http\Controllers\Api;

use App\Helpers\PaymentImportHelper;
use App\Http\Controllers\Api\Advance\AdvanceFinanceController;
use App\Http\Controllers\Controller;
use App\Models\DigiNextPaymentFile;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use PhpOffice\PhpSpreadsheet\Shared\Date;
use PhpOffice\PhpSpreadsheet\IOFactory;

class PaymentApiController extends Controller
{
    public function import(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'payment_file' => 'required|file|mimes:xlsx,xls,csv|max:10240',
                'yearId' => 'required|integer',
                'source_app' => 'nullable|string|max:100',
                'confirmed_by' => 'nullable|integer',
            ]);
            if ($validator->fails()) {
                return response()->json(['success' => false, 'message' => 'Validation failed', 'errors' => $validator->errors()], 422);
            }
            $yearId = (int) $request->input('yearId');
            $file = $request->file('payment_file');
            $result = $this->importPaymentFile($file, $request->input('source_app', 'API'), $yearId);
            try {
                $confirmResult = $this->autoConfirmPayments($yearId, $request->input('confirmed_by'));
                $result['auto_confirm_result'] = $confirmResult;
            } catch (\Exception $e) {
                $result['auto_confirm_error'] = $e->getMessage();
            }
            return response()->json(['success' => true, 'message' => 'Import completed successfully', 'data' => $result], 200);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => 'Import failed', 'error' => $e->getMessage()], 500);
        }
    }

    public function list(Request $request)
    {
        try {
            $perPage = $request->input('per_page', 25);
            $page = $request->input('page', 1);
            $search = $request->input('search');
            $sortBy = $request->input('sort_by', 'created_at');
            $sortOrder = $request->input('sort_order', 'desc');
            $dateFrom = $request->input('date_from');
            $dateTo = $request->input('date_to');
            $importDateFrom = $request->input('import_date_from');
            $importDateTo = $request->input('import_date_to');
            $matchStatus = $request->input('match_status');
            $batchId = $request->input('batch_id');
            $paymentType = $request->input('payment_type');
            $bankName = $request->input('bank_name');
            $minAmount = $request->input('min_amount');
            $maxAmount = $request->input('max_amount');
            $application = $request->input('application');
            $query = DigiNextPaymentFile::query();
            if ($search) {
                $query->where(function ($q) use ($search) {
                    $q->where('transaction_refno', 'like', "%{$search}%")->orWhere('beneficiary_name', 'like', "%{$search}%")->orWhere('beneficiary_account_number', 'like', "%{$search}%")->orWhere('bank_name', 'like', "%{$search}%")->orWhere('rbi_utr_reference_number', 'like', "%{$search}%");
                });
            }
            if ($matchStatus) {
                $query->where('match_status', $matchStatus);
            }
            if ($dateFrom && $dateTo) {
                if ($dateFrom > $dateTo) {
                    $temp = $dateFrom;
                    $dateFrom = $dateTo;
                    $dateTo = $temp;
                }
                $query->whereBetween('value_date', [$dateFrom, $dateTo]);
            } elseif ($dateFrom) {
                $query->whereDate('value_date', '>=', $dateFrom);
            } elseif ($dateTo) {
                $query->whereDate('value_date', '<=', $dateTo);
            }
            if ($importDateFrom && $importDateTo) {
                if ($importDateFrom > $importDateTo) {
                    $temp = $importDateFrom;
                    $importDateFrom = $importDateTo;
                    $importDateTo = $temp;
                }
                $query->whereBetween('import_date', [$importDateFrom, $importDateTo]);
            } elseif ($importDateFrom) {
                $query->whereDate('import_date', '>=', $importDateFrom);
            } elseif ($importDateTo) {
                $query->whereDate('import_date', '<=', $importDateTo);
            }
            if ($batchId) {
                $query->where('import_batch_id', $batchId);
            }
            if ($paymentType) {
                $query->where('payment_type', $paymentType);
            }
            if ($bankName) {
                $query->where('bank_name', 'like', "%{$bankName}%");
            }
            if ($minAmount) {
                $query->where('amount', '>=', $minAmount);
            }
            if ($maxAmount) {
                $query->where('amount', '<=', $maxAmount);
            }
            if ($application) {
                $query->where('application', $application);
            }
            $query->orderBy($sortBy, $sortOrder);
            $payments = $query->paginate($perPage, ['*'], 'page', $page);
            $summaryQuery = clone $query;
            $totalAmount = $summaryQuery->sum('amount');
            $transactionStatusCounts = $summaryQuery->clone()->groupBy('transaction_status')->selectRaw('transaction_status, count(*) as count')->pluck('count', 'transaction_status');
            $matchStatusCounts = $summaryQuery->clone()->groupBy('match_status')->selectRaw('match_status, count(*) as count')->pluck('count', 'match_status');
            return response()->json(['success' => true, 'data' => ['payments' => $payments->items(), 'pagination' => ['current_page' => $payments->currentPage(), 'last_page' => $payments->lastPage(), 'per_page' => $payments->perPage(), 'total' => $payments->total(), 'from' => $payments->firstItem(), 'to' => $payments->lastItem()], 'summary' => ['total_amount' => number_format($totalAmount, 2), 'transaction_status_counts' => $transactionStatusCounts, 'match_status_counts' => $matchStatusCounts, 'filters_applied' => ['search' => $search, 'date_from' => $dateFrom, 'date_to' => $dateTo, 'import_date_from' => $importDateFrom, 'import_date_to' => $importDateTo, 'batch_id' => $batchId, 'match_status' => $matchStatus, 'payment_type' => $paymentType, 'bank_name' => $bankName, 'min_amount' => $minAmount, 'max_amount' => $maxAmount, 'application' => $application]]]], 200);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => 'Failed to fetch payments: ' . $e->getMessage(), 'error_code' => 'FETCH_ERROR'], 500);
        }
    }

    public function details($id)
    {
        try {
            $payment = DigiNextPaymentFile::find($id);
            if (!$payment) {
                return response()->json(['success' => false, 'message' => 'Payment not found', 'error_code' => 'NOT_FOUND'], 404);
            }
            return response()->json(['success' => true, 'data' => $payment], 200);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => 'Failed to fetch payment details: ' . $e->getMessage(), 'error_code' => 'FETCH_ERROR'], 500);
        }
    }

    public function importStatus($batchId)
    {
        try {
            $payments = DigiNextPaymentFile::where('import_batch_id', $batchId)->get();
            if ($payments->isEmpty()) {
                return response()->json(['success' => false, 'message' => 'Import batch not found', 'error_code' => 'BATCH_NOT_FOUND'], 404);
            }
            $stats = ['batch_id' => $batchId, 'total_records' => $payments->count(), 'import_date' => $payments->first()->import_date, 'created_at' => $payments->first()->created_at, 'total_amount' => $payments->sum('amount'), 'transaction_status_breakdown' => $payments->groupBy('transaction_status')->map->count(), 'match_status_breakdown' => $payments->groupBy('match_status')->map->count(), 'processing_breakdown' => [
                'processed' => $payments->where('is_processed', true)->count(),
                'unprocessed' => $payments->where('is_processed', false)->count(),
            ], 'success_rates' => [
                'transaction_success_rate' => $payments->count() > 0 ? round(($payments->where('transaction_status', 'SUCCESS')->count() / $payments->count()) * 100, 2) : 0,
                'match_success_rate' => $payments->count() > 0 ? round(($payments->where('match_status', 'MATCHED')->count() / $payments->count()) * 100, 2) : 0,
                'processing_rate' => $payments->count() > 0 ? round(($payments->where('is_processed', true)->count() / $payments->count()) * 100, 2) : 0,
            ], 'matched_activities' => $payments->whereNotNull('matched_activity_id')->map(function ($payment) {
                return ['payment_id' => $payment->id, 'transaction_refno' => $payment->transaction_refno, 'matched_activity_id' => $payment->matched_activity_id, 'processed_at' => $payment->processed_at, 'match_remarks' => $payment->match_remarks];
            })->values()];
            return response()->json(['success' => true, 'data' => $stats], 200);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => 'Failed to fetch import status: ' . $e->getMessage(), 'error_code' => 'FETCH_ERROR'], 500);
        }
    }

    public function getBatches(Request $request)
    {
        try {
            $perPage = $request->input('per_page', 25);
            $page = $request->input('page', 1);
            $sortBy = $request->input('sort_by', 'created_at');
            $sortOrder = $request->input('sort_order', 'desc');
            $batches = DigiNextPaymentFile::select(['import_batch_id', 'import_date', DB::raw('MIN(created_at) as created_at'), DB::raw('COUNT(*) as total_records'), DB::raw('SUM(amount) as total_amount'), DB::raw('COUNT(CASE WHEN transaction_status = "SUCCESS" THEN 1 END) as success_count'), DB::raw('COUNT(CASE WHEN transaction_status = "FAILED" THEN 1 END) as failed_count'), DB::raw('COUNT(CASE WHEN transaction_status = "PENDING" THEN 1 END) as pending_count'), DB::raw('COUNT(CASE WHEN match_status = "MATCHED" THEN 1 END) as matched_count'), DB::raw('COUNT(CASE WHEN match_status = "UNMATCHED" THEN 1 END) as unmatched_count'), DB::raw('COUNT(CASE WHEN match_status = "PENDING" THEN 1 END) as match_pending_count'), DB::raw('COUNT(CASE WHEN is_processed = 1 THEN 1 END) as processed_count'), DB::raw('COUNT(CASE WHEN is_processed = 0 THEN 1 END) as unprocessed_count')])->groupBy('import_batch_id', 'import_date')->orderBy($sortBy === 'created_at' ? DB::raw('MIN(created_at)') : $sortBy, $sortOrder)->paginate($perPage, ['*'], 'page', $page);
            $formattedBatches = $batches->getCollection()->map(function ($batch) {
                return ['batch_id' => $batch->import_batch_id, 'import_date' => $batch->import_date, 'created_at' => $batch->created_at, 'total_records' => $batch->total_records, 'total_amount' => number_format($batch->total_amount, 2), 'transaction_status_breakdown' => ['SUCCESS' => $batch->success_count, 'FAILED' => $batch->failed_count, 'PENDING' => $batch->pending_count], 'match_status_breakdown' => ['MATCHED' => $batch->matched_count, 'UNMATCHED' => $batch->unmatched_count, 'PENDING' => $batch->match_pending_count], 'processing_breakdown' => ['processed' => $batch->processed_count, 'unprocessed' => $batch->unprocessed_count], 'success_rates' => ['transaction_success_rate' => $batch->total_records > 0 ? round(($batch->success_count / $batch->total_records) * 100, 2) : 0, 'match_success_rate' => $batch->total_records > 0 ? round(($batch->matched_count / $batch->total_records) * 100, 2) : 0, 'processing_rate' => $batch->total_records > 0 ? round(($batch->processed_count / $batch->total_records) * 100, 2) : 0]];
            });
            return response()->json(['success' => true, 'data' => ['batches' => $formattedBatches, 'pagination' => ['current_page' => $batches->currentPage(), 'last_page' => $batches->lastPage(), 'per_page' => $batches->perPage(), 'total' => $batches->total(), 'from' => $batches->firstItem(), 'to' => $batches->lastItem()]]], 200);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => 'Failed to fetch import batches: ' . $e->getMessage(), 'error_code' => 'FETCH_ERROR'], 500);
        }
    }

    public function delete($id)
    {
        try {
            $payment = DigiNextPaymentFile::find($id);
            if (!$payment) {
                return response()->json(['success' => false, 'message' => 'Payment not found', 'error_code' => 'NOT_FOUND'], 404);
            }
            $payment->delete();
            return response()->json(['success' => true, 'message' => 'Payment deleted successfully'], 200);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => 'Failed to delete payment: ' . $e->getMessage(), 'error_code' => 'DELETE_ERROR'], 500);
        }
    }

    public function export(Request $request)
    {
        try {
            $query = DigiNextPaymentFile::query();
            if ($request->has('batch_id')) {
                $query->where('import_batch_id', $request->input('batch_id'));
            }
            if ($request->has('date_from')) {
                $query->whereDate('value_date', '>=', $request->input('date_from'));
            }
            if ($request->has('date_to')) {
                $query->whereDate('value_date', '<=', $request->input('date_to'));
            }
            if ($request->has('import_date_from')) {
                $query->whereDate('import_date', '>=', $request->input('import_date_from'));
            }
            if ($request->has('import_date_to')) {
                $query->whereDate('import_date', '<=', $request->input('import_date_to'));
            }
            if ($request->has('match_status')) {
                $query->where('match_status', $request->input('match_status'));
            }
            $payments = $query->get();
            if ($payments->isEmpty()) {
                return response()->json(['success' => false, 'message' => 'No payments found for export', 'error_code' => 'NO_DATA'], 404);
            }
            $filename = 'payments_export_' . date('Y-m-d_H-i-s') . '.csv';
            return response()->streamDownload(function () use ($payments) {
                $handle = fopen('php://output', 'w');
                fputcsv($handle, ['Record No', 'Beneficiary Account Number', 'Transaction Refno', 'Bank Name', 'IFSC Code', 'Debit Account Number', 'Value Date', 'Amount', 'Beneficiary Name', 'Payment Type', 'RBI/UTR Reference Number', 'Currency', 'Error Description', 'Transaction Status', 'Beneficiary Aadhaar Number', 'Destination Bank', 'Credit Reference', 'Import Date', 'Import Batch ID', 'Is Processed', 'Matched Activity ID', 'Processed At', 'Processed By', 'Match Status', 'Match Remarks']);
                foreach ($payments as $payment) {
                    fputcsv($handle, [$payment->record_no, $payment->beneficiary_account_number, $payment->transaction_refno, $payment->bank_name, $payment->ifsc_code, $payment->debit_account_number, $payment->value_date ? $payment->value_date->format('d/m/Y') : '', $payment->amount, $payment->beneficiary_name, $payment->payment_type, $payment->rbi_utr_reference_number, $payment->currency, $payment->error_description, $payment->transaction_status, $payment->beneficiary_aadhaar_number, $payment->destination_bank, $payment->credit_reference, $payment->import_date ? $payment->import_date->format('d/m/Y') : '', $payment->import_batch_id, $payment->is_processed ? 'Yes' : 'No', $payment->matched_activity_id, $payment->processed_at ? $payment->processed_at->format('d/m/Y H:i:s') : '', $payment->processed_by, $payment->match_status, $payment->match_remarks]);
                }
                fclose($handle);
            }, $filename, ['Content-Type' => 'text/csv', 'Content-Disposition' => 'attachment; filename="' . $filename . '"']);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => 'Export failed: ' . $e->getMessage(), 'error_code' => 'EXPORT_ERROR'], 500);
        }
    }

    public function updateStatus(Request $request, $id)
    {
        try {
            $validator = Validator::make($request->all(), ['match_status' => 'required|in:MATCHED,UNMATCHED,PENDING', 'is_processed' => 'boolean', 'matched_activity_id' => 'nullable|integer', 'match_remarks' => 'nullable|string|max:1000', 'processed_by' => 'nullable|integer']);
            if ($validator->fails()) {
                return response()->json(['success' => false, 'message' => 'Validation failed', 'errors' => $validator->errors()], 422);
            }
            $payment = DigiNextPaymentFile::find($id);
            if (!$payment) {
                return response()->json(['success' => false, 'message' => 'Payment not found', 'error_code' => 'NOT_FOUND'], 404);
            }
            $updateData = [
                'match_status' => $request->input('match_status'),
                'match_remarks' => $request->input('match_remarks'),
            ];
            if ($request->has('is_processed')) {
                $updateData['is_processed'] = $request->input('is_processed');
                if ($request->input('is_processed')) {
                    $updateData['processed_at'] = now();
                    $updateData['processed_by'] = $request->input('processed_by');
                }
            }
            if ($request->has('matched_activity_id')) {
                $updateData['matched_activity_id'] = $request->input('matched_activity_id');
            }
            $payment->update($updateData);
            return response()->json(['success' => true, 'message' => 'Payment status updated successfully', 'data' => $payment->fresh()], 200);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => 'Failed to update payment status: ' . $e->getMessage(), 'error_code' => 'UPDATE_ERROR'], 500);
        }
    }

    public function bulkUpdateStatus(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), ['payment_ids' => 'required|array|min:1', 'payment_ids.*' => 'integer|exists:digi_next_payment_file,id', 'match_status' => 'required|in:MATCHED,UNMATCHED,PENDING', 'is_processed' => 'boolean', 'match_remarks' => 'nullable|string|max:1000', 'processed_by' => 'nullable|integer']);
            if ($validator->fails()) {
                return response()->json(['success' => false, 'message' => 'Validation failed', 'errors' => $validator->errors()], 422);
            }
            $paymentIds = $request->input('payment_ids');
            $updateData = [
                'match_status' => $request->input('match_status'),
                'match_remarks' => $request->input('match_remarks'),
            ];
            if ($request->has('is_processed')) {
                $updateData['is_processed'] = $request->input('is_processed');
                if ($request->input('is_processed')) {
                    $updateData['processed_at'] = now();
                    $updateData['processed_by'] = $request->input('processed_by');
                }
            }
            DB::beginTransaction();
            $updatedCount = DigiNextPaymentFile::whereIn('id', $paymentIds)->update($updateData);
            DB::commit();
            return response()->json(['success' => true, 'message' => "Successfully updated {$updatedCount} payment records", 'data' => ['updated_count' => $updatedCount, 'payment_ids' => $paymentIds]], 200);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['success' => false, 'message' => 'Failed to bulk update payment status: ' . $e->getMessage(), 'error_code' => 'BULK_UPDATE_ERROR'], 500);
        }
    }

    /**
     * LV-S10: bank / payment import files hold payee names and account numbers. They are kept on
     * the private disk (storage/app/private/payment_imports, not under public/), with a server name
     * and an extension taken from the file's content.
     */
    private function storeUploadedFile(UploadedFile $file): array
    {
        $originalName = $file->getClientOriginalName();
        $extension = \App\Support\SafeUpload::check($file, ['xlsx', 'xls', 'csv'], 20480);
        $fileName = \App\Support\SafeUpload::name('payment_import', $extension);
        $relativeDir = 'private/payment_imports/' . now()->format('Y') . '/' . now()->format('m');
        $dir = storage_path('app/' . $relativeDir);
        if (!file_exists($dir)) {
            mkdir($dir, 0750, true);
        }
        $destinationPath = $dir . DIRECTORY_SEPARATOR . $fileName;
        copy($file->getRealPath(), $destinationPath);
        $relativePath = $relativeDir . '/' . $fileName;
        return ['file_name' => $originalName, 'stored_name' => $fileName, 'relative_path' => $relativePath, 'full_path' => $destinationPath];
    }

    private function importPaymentFile(UploadedFile $file, string $sourceApp = 'API', int $yearId = null): array
    {
        $importBatchId = Str::uuid()->toString();
        $importDate = now()->format('Y-m-d');
        $totalRows = 0;
        $insertedRows = 0;
        $skippedRows = 0;
        $failedRows = [];
        DB::beginTransaction();
        try {
            $storedFilePath = $this->storeUploadedFile($file);

            // read excel data
            $result = $this->readExcelFile($file);
            $rows = $result['rows'];
            $totalRows = count($rows);

            foreach ($rows as $index => $row) {
                try {
                    $validationErrors = PaymentImportHelper::validateRequiredFields($row);
                    if (!empty($validationErrors)) {
                        $failedRows[] = ['row' => $index + 2, 'reason' => implode(', ', $validationErrors)];
                        continue;
                    }

                    // format date
                    if (!empty($row['value_date'])) {
                        $value = trim($row['value_date']);
                        // Excel numeric date
                        if (is_numeric($value)) {
                            $row['value_date'] = Date::excelToDateTimeObject($value)->format('Y-m-d');
                        }
                        // dd-mm-yyyy
                        elseif (preg_match('/^\d{2}-\d{2}-\d{4}$/', $value)) {
                            $row['value_date'] = Carbon::createFromFormat('d-m-Y', $value)->format('Y-m-d');
                        }
                        // dd/mm/yyyy
                        elseif (preg_match('/^\d{2}\/\d{2}\/\d{4}$/', $value)) {
                            $row['value_date'] = Carbon::createFromFormat('d/m/Y', $value)->format('Y-m-d');
                        }
                        // fallback parser
                        else {
                            $row['value_date'] = Carbon::parse($value)->format('Y-m-d');
                        }
                    }

                    // clean amount
                    $row['amount'] = isset($row['amount']) ? (float) str_replace(',', '', $row['amount']) : 0;

                    // defaults
                    $row['currency'] = $row['currency'] ?? 'INR';
                    $row['transaction_status'] = $row['transaction_status'] ?? 'SUCCESS';

                    // add import metadata
                    $row['import_date'] = $importDate;
                    $row['import_batch_id'] = $importBatchId;
                    $row['import_file_path'] = $storedFilePath['relative_path'] ?? null;
                    $row['import_file_name'] = $storedFilePath['file_name'] ?? null;

                    if (!empty($row['transaction_refno']) && DigiNextPaymentFile::where('transaction_refno', $row['transaction_refno'])->exists()) {
                        $skippedRows++;
                        continue;
                    }

                    // insert row
                    DigiNextPaymentFile::create($row);
                    $insertedRows++;
                } catch (\Exception $e) {
                    $failedRows[] = ['row' => $index + 2, 'reason' => $e->getMessage()];
                }
            }

            DB::commit();
            return ['total_rows' => $totalRows, 'inserted_rows' => $insertedRows, 'skipped_rows' => $skippedRows, 'failed_rows' => $failedRows, 'import_batch_id' => $importBatchId, 'source_app' => $sourceApp, 'file_path' => $storedFilePath['relative_path'] ?? null, 'file_name' => $storedFilePath['file_name'] ?? null];
        } catch (\Exception $e) {
            DB::rollBack();
            throw $e;
        }
    }

    private function readExcelFile(UploadedFile $file): array
    {
        $spreadsheet = IOFactory::load($file->getPathname());

        // pick Sheet 1
        $worksheet = $spreadsheet->getSheet(0);

        $data = [];
        $headers = [];

        // get last row & column
        $highestRow = $worksheet->getHighestRow();
        $highestColumn = $worksheet->getHighestColumn();
        $highestColumnIndex =
            \PhpOffice\PhpSpreadsheet\Cell\Coordinate::columnIndexFromString($highestColumn);

        /*
         * |--------------------------------------------------------------------------
         * | Skip rows 1–5
         * | Row 6 = header row
         * |--------------------------------------------------------------------------
         */
        $headerRow = 6;

        /*
         * |--------------------------------------------------------------------------
         * | Read header row (skip Column A)
         * |--------------------------------------------------------------------------
         */
        for ($col = 2; $col <= $highestColumnIndex; $col++) {
            $headers[] = trim((string) $worksheet
                ->getCell([$col, $headerRow])
                ->getValue());
        }

        // get table columns
        $tableColumns = Schema::getColumnListing((new DigiNextPaymentFile)->getTable());

        /*
         * |--------------------------------------------------------------------------
         * | Smart header matching
         * |--------------------------------------------------------------------------
         */
        $excelHeaders = [];

        foreach ($headers as $header) {
            $cleanHeader = strtolower(trim($header));
            $cleanHeader = preg_replace('/[^a-z0-9 ]/', ' ', $cleanHeader);
            $cleanHeader = preg_replace('/\s+/', ' ', $cleanHeader);

            $bestMatch = null;
            $bestScore = 0;

            foreach ($tableColumns as $column) {
                similar_text($cleanHeader, str_replace('_', ' ', $column), $percent);

                if ($percent > $bestScore) {
                    $bestScore = $percent;
                    $bestMatch = $column;
                }
            }

            $excelHeaders[] = ($bestScore >= 95)
                ? $bestMatch
                : Str::snake($cleanHeader);
        }

        /*
         * |--------------------------------------------------------------------------
         * | Read data rows (start after header)
         * |--------------------------------------------------------------------------
         */
        for ($row = $headerRow + 1; $row <= $highestRow; $row++) {
            $rowData = [];

            for ($col = 2; $col <= $highestColumnIndex; $col++) {
                $cell = $worksheet->getCell([$col, $row]);
                $value = $cell->getValue();

                // convert Excel date
                if (Date::isDateTime($cell) && !empty($value)) {
                    $value = Date::excelToDateTimeObject($value)->format('Y-m-d');
                }

                $rowData[$excelHeaders[$col - 2]] = $value;
            }

            // skip empty rows
            if (!empty(array_filter($rowData))) {
                $data[] = $rowData;
            }
        }

        return [
            'headers' => $excelHeaders,
            'rows' => $data
        ];
    }

    public function listUploadedFiles(Request $request)
    {
        try {
            $perPage = $request->input('per_page', 25);
            $page = $request->input('page', 1);
            $sortBy = $request->input('sort_by', 'created_at');
            $sortOrder = $request->input('sort_order', 'desc');
            $dateFrom = $request->input('date_from');
            $dateTo = $request->input('date_to');
            $search = $request->input('search');
            $query = DigiNextPaymentFile::select(['import_batch_id', 'import_file_name', 'import_file_path', 'import_date', DB::raw('MIN(created_at) as uploaded_at'), DB::raw('COUNT(*) as total_records'), DB::raw('SUM(amount) as total_amount'), DB::raw('COUNT(CASE WHEN transaction_status = "SUCCESS" THEN 1 END) as success_count'), DB::raw('COUNT(CASE WHEN transaction_status = "FAILED" THEN 1 END) as failed_count'), DB::raw('COUNT(CASE WHEN match_status = "MATCHED" THEN 1 END) as matched_count'), DB::raw('COUNT(CASE WHEN is_processed = 1 THEN 1 END) as processed_count')])->whereNotNull('import_file_name')->groupBy('import_batch_id', 'import_file_name', 'import_file_path', 'import_date');
            if ($search) {
                $query->having('import_file_name', 'like', "%{$search}%");
            }
            if ($dateFrom && $dateTo) {
                if ($dateFrom > $dateTo) {
                    $temp = $dateFrom;
                    $dateFrom = $dateTo;
                    $dateTo = $temp;
                }
                $query->whereBetween('import_date', [$dateFrom, $dateTo]);
            } elseif ($dateFrom) {
                $query->whereDate('import_date', '>=', $dateFrom);
            } elseif ($dateTo) {
                $query->whereDate('import_date', '<=', $dateTo);
            }
            $query->orderBy($sortBy === 'created_at' ? DB::raw('MIN(created_at)') : $sortBy, $sortOrder);
            $files = $query->paginate($perPage, ['*'], 'page', $page);
            $formattedFiles = $files->getCollection()->map(function ($file) {
                $fileExists = file_exists(public_path($file->import_file_path));
                $fileSize = $fileExists ? filesize(public_path($file->import_file_path)) : null;
                return ['batch_id' => $file->import_batch_id, 'file_name' => $file->import_file_name, 'file_path' => $file->import_file_path, 'file_url' => $file->import_file_path ? url($file->import_file_path) : null, 'file_exists' => $fileExists, 'file_size' => $fileSize ? round($fileSize / 1024, 2) . ' KB' : null, 'import_date' => $file->import_date, 'uploaded_at' => $file->uploaded_at, 'total_records' => $file->total_records, 'total_amount' => number_format($file->total_amount, 2), 'statistics' => ['success_count' => $file->success_count, 'failed_count' => $file->failed_count, 'matched_count' => $file->matched_count, 'processed_count' => $file->processed_count, 'success_rate' => $file->total_records > 0 ? round(($file->success_count / $file->total_records) * 100, 2) : 0, 'match_rate' => $file->total_records > 0 ? round(($file->matched_count / $file->total_records) * 100, 2) : 0, 'processing_rate' => $file->total_records > 0 ? round(($file->processed_count / $file->total_records) * 100, 2) : 0]];
            });
            return response()->json(['success' => true, 'data' => ['files' => $formattedFiles, 'pagination' => ['current_page' => $files->currentPage(), 'last_page' => $files->lastPage(), 'per_page' => $files->perPage(), 'total' => $files->total(), 'from' => $files->firstItem(), 'to' => $files->lastItem()]]], 200);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => 'Failed to fetch uploaded files: ' . $e->getMessage(), 'error_code' => 'FETCH_ERROR'], 500);
        }
    }

    private function autoConfirmPayments($yearId, $confirmedBy = null): array
    {
        try {
            $advanceFinanceController = new AdvanceFinanceController();
            $mockRequest = new Request();

            $mockRequest->merge([
                'yearId' => $yearId,
                'confirmed_by' => $confirmedBy,
            ]);

            // Run confirmations
            $advanceResponse = $advanceFinanceController->confirmAdvancePayments($mockRequest);
            $expenseResponse = $advanceFinanceController->confirmExpensePayments($mockRequest);

            $advanceData = $advanceResponse->getData(true);
            $expenseData = $expenseResponse->getData(true);

            $advanceSuccess = $advanceData['success'] ?? false;
            $expenseSuccess = $expenseData['success'] ?? false;

            // =========================
            // EXTRACT COUNTS SAFELY
            // =========================
            $advanceCounts = [
                'total' => $advanceData['data']['total'] ?? 0,
                'matched' => $advanceData['data']['matched'] ?? 0,
                'unsettled' => $advanceData['data']['unsettled'] ?? 0,
            ];

            $expenseCounts = [
                'total' => $expenseData['data']['total'] ?? 0,
                'matched' => $expenseData['data']['matched'] ?? 0,
                'unsettled' => $expenseData['data']['unsettled'] ?? 0,
            ];

            // =========================
            // COMBINED COUNTERS
            // =========================
            $totalProcessed = $advanceCounts['total'] + $expenseCounts['total'];
            $totalMatched = $advanceCounts['matched'] + $expenseCounts['matched'];
            $totalUnsettled = $advanceCounts['unsettled'] + $expenseCounts['unsettled'];
            $totalFailed = $totalProcessed - $totalMatched;

            $successRate = $totalProcessed > 0
                ? round(($totalMatched / $totalProcessed) * 100, 2)
                : 0;

            $combinedData = [
                'summary' => [
                    'total_processed' => $totalProcessed,
                    'total_matched' => $totalMatched,
                    'total_unsettled' => $totalUnsettled,
                    'total_failed' => $totalFailed,
                    'success_rate' => $successRate . '%',
                ],
                'advance' => [
                    'success' => $advanceSuccess,
                    'counts' => $advanceCounts,
                    'message' => $advanceData['message'] ?? null,
                ],
                'expense' => [
                    'success' => $expenseSuccess,
                    'counts' => $expenseCounts,
                    'message' => $expenseData['message'] ?? null,
                ],
            ];

            // =========================
            // FINAL STATUS MESSAGE
            // =========================
            if ($advanceSuccess && $expenseSuccess) {
                $message = 'All payments confirmed successfully.';
            } elseif ($advanceSuccess || $expenseSuccess) {
                $message = 'Partial confirmation completed.';
            } else {
                $message = 'Both confirmations failed.';
            }

            return [
                'success' => ($advanceSuccess || $expenseSuccess),
                'message' => $message,
                'data' => $combinedData
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'message' => 'Auto-confirm failed: ' . $e->getMessage(),
                'data' => null
            ];
        }
    }
}
