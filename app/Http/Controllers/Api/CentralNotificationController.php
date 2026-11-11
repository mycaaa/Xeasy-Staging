<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\CentralNotificationService;
use Illuminate\Http\Request;

class CentralNotificationController extends Controller
{
    private CentralNotificationService $service;

    public function __construct()
    {
        $this->service = new CentralNotificationService();
    }

    /**
     * Get pending actions for the current employee
     */
    public function pending(Request $request, $employeeCode)
    {
        $sourceApp = $request->query('source_app');
        $result = $this->service->getPendingActions($employeeCode, $sourceApp);

        return response()->json($result);
    }

    /**
     * Get action counts for the current employee
     */
    public function counts(Request $request, $employeeCode)
    {
        $result = $this->service->getActionCounts($employeeCode);

        return response()->json($result);
    }

    /**
     * Get action history for the current employee
     */
    public function history(Request $request, $employeeCode)
    {
        $sourceApp = $request->query('source_app');
        $result = $this->service->getActionHistory($employeeCode, $sourceApp);

        return response()->json($result);
    }

    /**
     * Raise a new action manually (generic endpoint)
     */
    public function raise(Request $request)
    {
        $request->validate([
            'reference_type' => 'required|string',
            'reference_id' => 'required|string',
            'module' => 'required|string',
            'title' => 'required|string',
            'applicant_employee_code' => 'required|string',
            'approver_employee_code' => 'required|string',
        ]);

        $result = $this->service->raiseAction($request->all());

        return response()->json($result);
    }

    /**
     * Approve an action manually
     */
    public function approve(Request $request)
    {
        $request->validate([
            'reference_type' => 'required|string',
            'reference_id' => 'required|string',
        ]);

        $result = $this->service->approveAction(
            $request->input('reference_type'),
            $request->input('reference_id'),
            $request->input('remarks', ''),
            $request->input('meta', [])
        );

        return response()->json($result);
    }

    /**
     * Reject an action manually
     */
    public function reject(Request $request)
    {
        $request->validate([
            'reference_type' => 'required|string',
            'reference_id' => 'required|string',
        ]);

        $result = $this->service->rejectAction(
            $request->input('reference_type'),
            $request->input('reference_id'),
            $request->input('remarks', ''),
            $request->input('meta', [])
        );

        return response()->json($result);
    }

    /**
     * Cancel an action
     */
    public function cancel(Request $request)
    {
        $request->validate([
            'reference_type' => 'required|string',
            'reference_id' => 'required|string',
        ]);

        $result = $this->service->cancelAction(
            $request->input('reference_type'),
            $request->input('reference_id')
        );

        return response()->json($result);
    }

    /**
     * Forward an action to the next approver
     */
    public function forward(Request $request)
    {
        $request->validate([
            'reference_type' => 'required|string',
            'reference_id' => 'required|string',
            'next_approver_employee_code' => 'required|string',
            'next_approver_name' => 'required|string',
            'approval_level' => 'required|integer',
        ]);

        $result = $this->service->forwardAction($request->all());

        return response()->json($result);
    }
}
