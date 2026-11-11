<?php
namespace App\Http\Controllers\admin;

use App\Helpers\PaymentImportHelper;
use App\Http\Controllers\Controller;
use App\Http\Requests\PaymentImportRequest;
use App\Models\DigiNextPaymentFile;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use PhpOffice\PhpSpreadsheet\Shared\Date;
use PhpOffice\PhpSpreadsheet\IOFactory;
use Yajra\DataTables\Facades\DataTables;

class PaymentController extends Controller
{
    public function import()
    {
        return view('admin.payments.import');
    }

    public function processImport(PaymentImportRequest $request)
    {
        try {
            $result = $this->importPaymentFile($request->file('payment_file'));
            return response()->json(['success' => true, 'message' => 'Import completed successfully', 'data' => $result]);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => 'Import failed: ' . $e->getMessage()], 500);
        }
    }

    private function importPaymentFile(UploadedFile $file): array
    {
        $importBatchId = Str::uuid()->toString();
        $importDate = now()->format('Y-m-d');
        $totalRows = 0;
        $insertedRows = 0;
        $skippedRows = 0;
        $failedRows = [];
        DB::beginTransaction();
        try {
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
            return ['total_rows' => $totalRows, 'inserted_rows' => $insertedRows, 'skipped_rows' => $skippedRows, 'failed_rows' => $failedRows, 'import_batch_id' => $importBatchId];
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

            $excelHeaders[] = ($bestScore >= 60)
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

    public function index()
    {
        return view('admin.payments.index');
    }

    public function getData(Request $request)
    {
        $query = DigiNextPaymentFile::select(['id', 'record_no', 'transaction_refno', 'beneficiary_name', 'bank_name', 'value_date', 'amount', 'payment_type', 'transaction_status', 'import_date']);
        return DataTables::of($query)->addColumn('action', function ($row) {
            return '<button type="button" class="btn btn-sm btn-primary view-details" data-id="' . $row->id . '" data-bs-toggle="modal" data-bs-target="#paymentDetailsModal">
                            <i class="mdi mdi-eye me-1"></i>View Details
                        </button>';
        })->editColumn('amount', function ($row) {
            return '₹' . number_format($row->amount, 2);
        })->editColumn('value_date', function ($row) {
            return $row->value_date ? $row->value_date->format('d/m/Y') : '';
        })->editColumn('import_date', function ($row) {
            return $row->import_date ? $row->import_date->format('d/m/Y') : '';
        })->editColumn('transaction_status', function ($row) {
            return $row->transaction_status;
        })->editColumn('bank_name', function ($row) {
            return $row->bank_name ?: '-';  // Show dash if bank name is not available
        })->rawColumns(['transaction_status', 'action'])->make(true);
    }

    public function getDetails(Request $request)
    {
        $payment = DigiNextPaymentFile::find($request->id);
        if (!$payment) {
            return response()->json(['error' => 'Payment not found'], 404);
        }
        return response()->json(['success' => true, 'data' => ['id' => $payment->id, 'record_no' => $payment->record_no, 'beneficiary_account_number' => $payment->beneficiary_account_number, 'transaction_refno' => $payment->transaction_refno, 'bank_name' => $payment->bank_name ?: '-', 'ifsc_code' => $payment->ifsc_code ?: '-', 'debit_account_number' => $payment->debit_account_number ?: '-', 'value_date' => $payment->value_date ? $payment->value_date->format('d/m/Y') : '-', 'amount' => '₹' . number_format($payment->amount, 2), 'beneficiary_name' => $payment->beneficiary_name, 'payment_type' => $payment->payment_type ?: '-', 'rbi_utr_reference_number' => $payment->rbi_utr_reference_number ?: '-', 'currency' => $payment->currency ?: 'INR', 'error_description' => $payment->error_description ?: '-', 'transaction_status' => $payment->transaction_status ?: 'SUCCESS', 'beneficiary_aadhaar_number' => $payment->beneficiary_aadhaar_number ?: '-', 'destination_bank' => $payment->destination_bank ?: '-', 'credit_reference' => $payment->credit_reference ?: '-', 'import_date' => $payment->import_date ? $payment->import_date->format('d/m/Y') : '-', 'import_batch_id' => $payment->import_batch_id, 'created_at' => $payment->created_at ? $payment->created_at->format('d/m/Y H:i:s') : '-']]);
    }
}
