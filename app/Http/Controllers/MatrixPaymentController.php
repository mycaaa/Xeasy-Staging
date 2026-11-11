<?php

namespace App\Http\Controllers;

use App\Services\Matrix\MatrixPaymentManager;
use App\Models\MatrixPaymentInstruction;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class MatrixPaymentController extends Controller
{
    protected MatrixPaymentManager $paymentManager;

    public function __construct(MatrixPaymentManager $paymentManager)
    {
        $this->paymentManager = $paymentManager;
    }

    protected function authorizeUser(): void
    {
        $empCode = (string) (
            session('emp_code') ??
            session('employee_details.EmpCode') ??
            auth()->user()?->employee?->EmpCode ??
            auth()->user()?->employee?->EmpCode_New ??
            auth()->user()?->employee_id ??
            auth()->user()?->emp_code ??
            ''
        );

        if ($empCode !== '1740') {
            abort(403, 'Unauthorized. Matrix payment integration is currently restricted to employee 1740.');
        }
    }

    /**
     * Push selected reimbursement records to Matrix
     */
    public function pushReimbursements(Request $request)
    {
        $this->authorizeUser();

        try {
            $rows = $request->input('rows');
            if (is_string($rows)) {
                $rows = json_decode($rows, true);
            }

            if (empty($rows) && $request->has('ids')) {
                $ids = (array) $request->input('ids');
            } else {
                $ids = array_column($rows ?: [], 'id');
            }

            if (empty($ids)) {
                return response()->json([
                    'success' => false,
                    'message' => 'No reimbursement records selected to push to Matrix',
                ], 400);
            }

            $yearId = (int) session('year_id', date('Y'));
            $companyId = (int) session('company_id', 1);

            $result = $this->paymentManager->pushReimbursements($ids, $yearId, $companyId);

            return response()->json($result);
        } catch (Exception $e) {
            Log::error('Matrix push reimbursements error: ' . $e->getMessage(), [
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to push to Matrix: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Push selected advance activities to Matrix
     */
    public function pushAdvances(Request $request)
    {
        $this->authorizeUser();

        try {
            $rows = $request->input('rows');
            if (is_string($rows)) {
                $rows = json_decode($rows, true);
            }

            if (empty($rows) && $request->has('ids')) {
                $ids = (array) $request->input('ids');
            } else {
                $ids = array_column($rows ?: [], 'id');
            }

            if (empty($ids)) {
                return response()->json([
                    'success' => false,
                    'message' => 'No advance requests selected to push to Matrix',
                ], 400);
            }

            $companyId = (int) session('company_id', 1);

            $result = $this->paymentManager->pushAdvances($ids, $companyId);

            return response()->json($result);
        } catch (Exception $e) {
            Log::error('Matrix push advances error: ' . $e->getMessage(), [
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to push advances to Matrix: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Manually trigger sync of Matrix payment outcomes
     */
    public function syncUpdates(Request $request)
    {
        $this->authorizeUser();

        try {
            $result = $this->paymentManager->syncUpdates();

            return response()->json($result);
        } catch (Exception $e) {
            Log::error('Matrix sync updates error: ' . $e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Failed to sync with Matrix: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get Matrix instructions status for records
     */
    public function getInstructionStatuses(Request $request)
    {
        $type = $request->input('payment_type');
        $ids = (array) $request->input('record_ids', []);

        $query = MatrixPaymentInstruction::query();
        if ($type) {
            $query->where('payment_type', $type);
        }
        if (!empty($ids)) {
            $query->whereIn('record_id', $ids);
        }

        $instructions = $query->get(['source_ref', 'record_id', 'payment_type', 'matrix_status', 'instruction_no', 'utr_number', 'paid_on', 'status_reason']);

        return response()->json([
            'success' => true,
            'data' => $instructions,
        ]);
    }

    /**
     * Preview generated payload without pushing to Matrix
     */
    public function previewPayload(Request $request)
    {
        $this->authorizeUser();

        $type = $request->input('payment_type', 'reimbursement');
        $rows = $request->input('rows');
        if (is_string($rows)) {
            $rows = json_decode($rows, true);
        }
        $ids = !empty($rows) ? array_column($rows, 'id') : (array) $request->input('ids', []);

        if (empty($ids)) {
            return response()->json(['success' => false, 'message' => 'No records selected for preview'], 400);
        }

        $builder = app(\App\Services\Matrix\MatrixPaymentPayloadBuilder::class);
        $companyId = (int) session('company_id', 1);

        if ($type === 'reimbursement') {
            $yearId = (int) session('year_id', date('Y'));
            $conn = \App\Helpers\DatabaseSwitcher::getExpenseConnection($companyId);
            $records = \Illuminate\Support\Facades\DB::connection($conn)
                ->table("y{$yearId}_monthexpensefinal")
                ->whereIn('id', $ids)
                ->get();

            $instructions = $builder->buildReimbursementInstructions($records, $yearId, $companyId);
        } else {
            $activities = \App\Models\AdvanceRequestActivity::with(['employee.advanceRequest', 'activityType'])
                ->whereIn('id', $ids)
                ->get();

            $instructions = $builder->buildAdvanceInstructions($activities, $companyId);
        }

        $cleanPayload = array_map(function ($item) {
            unset($item['_local_record_id'], $item['_local_year_id'], $item['_local_company_id'], $item['_local_type']);
            return $item;
        }, $instructions);

        return response()->json([
            'success' => true,
            'count' => count($cleanPayload),
            'payload' => [
                'instructions' => $cleanPayload,
            ],
        ]);
    }
}
