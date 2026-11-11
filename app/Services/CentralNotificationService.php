<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class CentralNotificationService
{
    private string $baseUrl;
    private string $appName;
    private string $appToken;

    public function __construct()
    {
        $this->baseUrl = rtrim(config('services.central_notification.base_url', ''), '/');
        $this->appName = config('services.central_notification.app_name', 'xeasy');
        $this->appToken = config('services.central_notification.app_token', '');
    }

    /**
     * Common headers for all requests
     */
    private function headers(): array
    {
        return [
            'Content-Type' => 'application/json',
            'Accept' => 'application/json',
            'X-App-Name' => $this->appName,
            'X-App-Token' => $this->appToken,
        ];
    }

    /**
     * Raise a new action (when a claim/advance/leave is submitted for approval)
     */
    public function raiseAction(array $data): array
    {
        return $this->post('/api/v1/actions/raise', $data);
    }

    /**
     * Approve an action
     */
    public function approveAction(string $referenceType, string $referenceId, ?string $remarks = '', array $meta = []): array
    {
        $remarks = (string) $remarks; // APPR-NOTIFY-01: a missing remark failed the approval request (TypeError after the save)
        return $this->post('/api/v1/actions/approve', [
            'reference_type' => $referenceType,
            'reference_id' => $referenceId,
            'remarks' => $remarks,
            'meta' => $meta,
        ]);
    }

    /**
     * Reject an action
     */
    public function rejectAction(string $referenceType, string $referenceId, ?string $remarks = '', array $meta = []): array
    {
        $remarks = (string) $remarks; // APPR-NOTIFY-01: a missing remark failed the approval request (TypeError after the save)
        return $this->post('/api/v1/actions/reject', [
            'reference_type' => $referenceType,
            'reference_id' => $referenceId,
            'remarks' => $remarks,
            'meta' => $meta,
        ]);
    }

    /**
     * Cancel an action
     */
    public function cancelAction(string $referenceType, string $referenceId): array
    {
        return $this->post('/api/v1/actions/cancel', [
            'reference_type' => $referenceType,
            'reference_id' => $referenceId,
        ]);
    }

    /**
     * Forward an action to the next approver
     */
    public function forwardAction(array $data): array
    {
        return $this->post('/api/v1/actions/forward', $data);
    }

    /**
     * Get pending actions for an employee
     */
    public function getPendingActions(string $employeeCode, ?string $sourceApp = null): array
    {
        $url = "/api/v1/actions/pending/{$employeeCode}";
        $query = $sourceApp ? ['source_app' => $sourceApp] : [];

        return $this->get($url, $query);
    }

    /**
     * Get pending action counts for an employee
     */
    public function getActionCounts(string $employeeCode): array
    {
        return $this->get("/api/v1/actions/counts/{$employeeCode}");
    }

    /**
     * Get action history for an employee
     */
    public function getActionHistory(string $employeeCode, ?string $sourceApp = null): array
    {
        $url = "/api/v1/actions/history/{$employeeCode}";
        $query = $sourceApp ? ['source_app' => $sourceApp] : [];

        return $this->get($url, $query);
    }

    // ── Helper: Raise action for expense claim ─────────────────────────────

    /**
     * Raise notification for expense claim submission
     */
    public function raiseExpenseClaim(
        string $referenceId,
        string $applicantCode,
        string $applicantName,
        string $approverCode,
        string $approverName,
        int $approvalLevel = 1,
        string $title = '',
        string $description = '',
        array $meta = []
    ): array {
        return $this->raiseAction([
            'reference_type' => 'expense_claim',
            'reference_id' => $referenceId,
            'module' => 'Expense Management',
            'title' => $title ?: "Expense Claim - {$applicantName}",
            'description' => $description ?: "Expense claim submitted for approval",
            'applicant_employee_code' => $applicantCode,
            'applicant_name' => $applicantName,
            'approver_employee_code' => $approverCode,
            'approver_name' => $approverName,
            'approval_level' => $approvalLevel,
            'action_url' => config('app.url') . "/approver?status=pending",
            'meta' => $meta,
        ]);
    }

    /**
     * Raise notification for advance request submission
     */
    public function raiseAdvanceRequest(
        string $referenceId,
        string $applicantCode,
        string $applicantName,
        string $approverCode,
        string $approverName,
        int $approvalLevel = 1,
        string $title = '',
        string $description = '',
        array $meta = []
    ): array {
        return $this->raiseAction([
            'reference_type' => 'advance_request',
            'reference_id' => $referenceId,
            'module' => 'Advance Management',
            'title' => $title ?: "Advance Request - {$applicantName}",
            'description' => $description ?: "Advance request submitted for approval",
            'applicant_employee_code' => $applicantCode,
            'applicant_name' => $applicantName,
            'approver_employee_code' => $approverCode,
            'approver_name' => $approverName,
            'approval_level' => $approvalLevel,
            'action_url' => config('app.url') . "/advance/approvals/pending",
            'meta' => $meta,
        ]);
    }

    // ── Private HTTP helpers ────────────────────────────────────────────────

    private function post(string $endpoint, array $data): array
    {
        if (empty($this->baseUrl)) {
            Log::warning('CentralNotificationService: base_url not configured, skipping notification.');
            return ['success' => false, 'message' => 'Service not configured'];
        }

        try {
            $response = Http::withHeaders($this->headers())
                ->withOptions(['verify' => \App\Support\TlsVerify::option(), 'timeout' => 10])
                ->post($this->baseUrl . $endpoint, $data);

            $result = $response->json() ?? [];

            if (!$response->successful()) {
                Log::error('CentralNotification API error', [
                    'endpoint' => $endpoint,
                    'status' => $response->status(),
                    'response' => $result,
                    'data' => $data,
                ]);
            }

            return $result;
        } catch (\Exception $e) {
            Log::error('CentralNotification API exception', [
                'endpoint' => $endpoint,
                'error' => $e->getMessage(),
                'data' => $data,
            ]);

            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    private function get(string $endpoint, array $query = []): array
    {
        if (empty($this->baseUrl)) {
            Log::warning('CentralNotificationService: base_url not configured, skipping.');
            return ['success' => false, 'message' => 'Service not configured'];
        }

        try {
            $response = Http::withHeaders($this->headers())
                ->withOptions(['verify' => \App\Support\TlsVerify::option(), 'timeout' => 10])
                ->get($this->baseUrl . $endpoint, $query);

            $result = $response->json() ?? [];

            if (!$response->successful()) {
                Log::error('CentralNotification API error', [
                    'endpoint' => $endpoint,
                    'status' => $response->status(),
                    'response' => $result,
                ]);
            }

            return $result;
        } catch (\Exception $e) {
            Log::error('CentralNotification API exception', [
                'endpoint' => $endpoint,
                'error' => $e->getMessage(),
            ]);

            return ['success' => false, 'message' => $e->getMessage()];
        }
    }
}
