<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

/**
 * REQ-005 & REQ-006: Transfer / Deputation Notification Service
 *
 * Detects employee transfers/deputation events from HRIMS and sends
 * automatic notifications to relevant stakeholders.
 *
 * REQ-005: VVNR → VNR company transfer
 * REQ-006: Location / Department / Deputation transfers
 */
class TransferNotificationService
{
    // Notification types
    const TYPE_COMPANY_TRANSFER   = 'company_transfer';   // REQ-005: VVNR→VNR
    const TYPE_LOCATION_TRANSFER  = 'location_transfer';  // REQ-006: Location
    const TYPE_DEPT_TRANSFER      = 'dept_transfer';       // REQ-006: Department
    const TYPE_DEPUTATION         = 'deputation';          // REQ-006: Deputation

    /** Tracker #34: --dry-run detects and reports but sends and logs nothing. */
    private bool $dryRun = false;

    /** UAT E-10: detection errors of the last processNewTransfers() run (missing HRMS table, SQL error). */
    private array $errors = [];

    /** @return string[] */
    public function errors(): array
    {
        return $this->errors;
    }

    /** Whether the de-duplication log table exists (migration 2026_11_05_000003). */
    public function logTableExists(): bool
    {
        try {
            return \Illuminate\Support\Facades\Schema::connection('mysql')->hasTable('transfer_notifications');
        } catch (\Throwable $e) {
            return false;
        }
    }

    /**
     * Check for and process new transfers since last run.
     * Called by the CloseExpiredMonths command or a dedicated cron.
     */
    public function processNewTransfers(bool $dryRun = false): array
    {
        $this->dryRun = $dryRun;
        $this->errors = [];
        $processed = [];

        // REQ-005: Detect VVNR → VNR company transfers
        $processed[] = $this->detectCompanyTransfers();

        // REQ-006: Detect department/location/deputation transfers
        $processed[] = $this->detectDepartmentTransfers();
        $processed[] = $this->detectDeputationTransfers();

        return $processed;
    }

    private function detectionError(string $message): string
    {
        $this->errors[] = $message;

        return $message;
    }

    /**
     * TRANSFER-02 (5 Nov 2026): HRMS records transfers, deputations and promotions as HR operation
     * requests (hrims.hroperation_request_employee, request_type_name), not in the
     * hrm_employee_transfer_history / hrm_employee_deputation tables the first version read (they do
     * not exist). Names come from the core_* masters (core_departments, core_city_village_by_state).
     *
     * A request counts once HR has acted on it (hr_action_status = 2) and its effective date
     * (valid_from_date) falls in the last `transfer.lookback_days` days (default 7) up to today.
     * transfer_notifications stops a second mail for the same employee, type and date.
     */
    private function operationRequests(array $typeNames): array
    {
        $hrimsDb = \App\Helpers\DatabaseSwitcher::getHrimsDatabaseName();
        $appDb = config('database.connections.mysql.database', 'expense_app');
        $lookback = max(1, (int) config('xeasy_notifications.transfer.lookback_days', 7));
        $in = implode(',', array_fill(0, count($typeNames), '?'));

        return DB::connection('hrims')->select("
            SELECT r.id AS request_id, r.request_type_name, r.transfer_subtype, r.CompID AS request_company,
                   e.EmployeeID, COALESCE(NULLIF(e.EmpCode_New, ''), e.EmpCode) AS EmpCode, e.CompanyId AS current_company,
                   TRIM(CONCAT(e.Fname,' ',COALESCE(e.Sname,''),' ',e.Lname)) AS FullName,
                   g.EmailId_Vnr, g.ReportingName, g.RepEmployeeID,
                   r.valid_from_date AS transfer_date, r.valid_to_date,
                   d1.department_name AS from_department, COALESCE(d2.department_name, d3.department_name) AS to_department,
                   h1.city_village_name AS from_location, COALESCE(h2.city_village_name, h3.city_village_name) AS to_location
              FROM {$hrimsDb}.hroperation_request_employee r
              JOIN {$hrimsDb}.hrm_employee e ON e.EmployeeID = r.employee_id
              LEFT JOIN {$hrimsDb}.hrm_employee_general g ON g.EmployeeID = r.employee_id
              LEFT JOIN {$hrimsDb}.core_departments d1 ON d1.id = r.ext_dept
              LEFT JOIN {$hrimsDb}.core_departments d2 ON d2.id = r.hr_new_department
              LEFT JOIN {$hrimsDb}.core_departments d3 ON d3.id = r.new_dept
              LEFT JOIN {$hrimsDb}.core_city_village_by_state h1 ON h1.id = r.ext_hq
              LEFT JOIN {$hrimsDb}.core_city_village_by_state h2 ON h2.id = r.hr_new_hq
              LEFT JOIN {$hrimsDb}.core_city_village_by_state h3 ON h3.id = r.new_hq
             WHERE r.request_type_name IN ({$in})
               AND r.hr_action_status = 2
               AND r.valid_from_date IS NOT NULL
               AND r.valid_from_date BETWEEN DATE_SUB(CURDATE(), INTERVAL {$lookback} DAY) AND CURDATE()
        ", $typeNames);
    }

    private function alreadyNotified(int $employeeId, string $type, $date): bool
    {
        if (! $this->logTableExists()) {
            return false;
        }

        return DB::connection('mysql')->table('transfer_notifications')
            ->where('employee_id', $employeeId)->where('notification_type', $type)->whereDate('transfer_date', $date)->exists();
    }

    /** REQ-005: employee moved from VVNR (11) to VNR (1): a request raised under VVNR for someone now on VNR. */
    private function detectCompanyTransfers(): string
    {
        try {
            $n = 0;
            foreach ($this->operationRequests(['Location Transfer', 'Department Transfer', 'Relocation']) as $emp) {
                if ((string) $emp->request_company !== '11' || (int) $emp->current_company !== 1 || $this->alreadyNotified((int) $emp->EmployeeID, self::TYPE_COMPANY_TRANSFER, $emp->transfer_date)) {
                    continue;
                }
                $this->sendTransferNotification($emp, self::TYPE_COMPANY_TRANSFER, [
                    'from_company' => 'VVNR',
                    'to_company'   => 'VNR',
                    'note'         => 'Employee transferred from VVNR to VNR. Finance team: please settle outstanding imprest/advances.',
                ]);
                $this->logNotification($emp->EmployeeID, self::TYPE_COMPANY_TRANSFER, $emp->transfer_date);
                $n++;
            }

            return $n . ' VVNR→VNR transfer notifications ' . ($this->dryRun ? 'found (dry run, not sent)' : 'sent');
        } catch (\Exception $e) {
            Log::error('TransferNotificationService@detectCompanyTransfers: ' . $e->getMessage());
            return $this->detectionError('Error detecting company transfers: ' . $e->getMessage());
        }
    }

    /** REQ-006: location (incl. relocation) and department transfers. */
    private function detectDepartmentTransfers(): string
    {
        try {
            $n = 0;
            foreach ($this->operationRequests(['Location Transfer', 'Relocation', 'Department Transfer']) as $emp) {
                $type = $emp->request_type_name === 'Department Transfer' ? self::TYPE_DEPT_TRANSFER : self::TYPE_LOCATION_TRANSFER;
                if ($this->alreadyNotified((int) $emp->EmployeeID, $type, $emp->transfer_date)) {
                    continue;
                }
                $this->sendTransferNotification($emp, $type, [
                    'from_dept'     => $emp->from_department ?? '—',
                    'to_dept'       => $emp->to_department ?? ($emp->from_department ?? '—'),
                    'from_location' => $emp->from_location ?? '—',
                    'to_location'   => $emp->to_location ?? ($emp->from_location ?? '—'),
                    'effective_from'=> $emp->transfer_date,
                ]);
                $this->logNotification($emp->EmployeeID, $type, $emp->transfer_date);
                $n++;
            }

            return $n . ' department/location transfer notifications ' . ($this->dryRun ? 'found (dry run, not sent)' : 'sent');
        } catch (\Exception $e) {
            Log::error('TransferNotificationService@detectDepartmentTransfers: ' . $e->getMessage());
            return $this->detectionError('Error detecting department/location transfers: ' . $e->getMessage());
        }
    }

    /** REQ-006: deputations. */
    private function detectDeputationTransfers(): string
    {
        try {
            $n = 0;
            foreach ($this->operationRequests(['Deputation']) as $emp) {
                if ($this->alreadyNotified((int) $emp->EmployeeID, self::TYPE_DEPUTATION, $emp->transfer_date)) {
                    continue;
                }
                $this->sendTransferNotification($emp, self::TYPE_DEPUTATION, [
                    'from_dept'      => $emp->from_department ?? '—',
                    'to_dept'        => $emp->to_department ?? ($emp->from_department ?? '—'),
                    'from_location'  => $emp->from_location ?? '—',
                    'to_location'    => $emp->to_location ?? '—',
                    'effective_from' => $emp->transfer_date ?? '—',
                    'effective_to'   => $emp->valid_to_date ? substr((string) $emp->valid_to_date, 0, 10) : '—',
                ]);
                $this->logNotification($emp->EmployeeID, self::TYPE_DEPUTATION, $emp->transfer_date);
                $n++;
            }

            return $n . ' deputation notifications ' . ($this->dryRun ? 'found (dry run, not sent)' : 'sent');
        } catch (\Exception $e) {
            Log::error('TransferNotificationService@detectDeputationTransfers: ' . $e->getMessage());
            return $this->detectionError('Error detecting deputations: ' . $e->getMessage());
        }
    }

    /**
     * Send notification email/in-app for a transfer event.
     */
    private function sendTransferNotification(object $emp, string $type, array $details): void
    {
        $typeLabels = [
            self::TYPE_COMPANY_TRANSFER  => 'Company Transfer (VVNR → VNR)',
            self::TYPE_LOCATION_TRANSFER => 'Location Transfer',
            self::TYPE_DEPT_TRANSFER     => 'Department Transfer',
            self::TYPE_DEPUTATION        => 'Deputation',
        ];

        $subject = "[Xeasy] {$typeLabels[$type]}: {$emp->FullName} ({$emp->EmpCode})";
        $body    = $this->buildNotificationBody($emp, $type, $typeLabels[$type], $details);

        if ($this->dryRun) {
            Log::info("TransferNotification [dry-run] would send [{$type}] for employee {$emp->EmployeeID}");

            return;
        }

        // Log to application log
        Log::info("TransferNotification [{$type}] Employee: {$emp->FullName} ({$emp->EmpCode})", $details);

        // Central notification (if service is configured)
        try {
            $centralService = app(CentralNotificationService::class);
            $centralService->raiseAction(
                referenceType: 'employee_transfer',
                referenceId:   (string) $emp->EmployeeID,
                module:        'hr',
                title:         $subject,
                description:   "Transfer notification for {$emp->FullName}",
                applicantEmployeeCode: $emp->EmpCode ?? '',
                approverEmployeeCode:  $emp->EmpCode ?? '',
                meta: array_merge($details, ['type' => $type, 'employee' => $emp->FullName]),
            );
        } catch (\Exception $e) {
            Log::warning('TransferNotification: Central notification failed — ' . $e->getMessage());
        }

        // Email notification (if email configured). MAIL_ACTIVE is the same switch the other
        // employee mails use, so a test server with real SMTP settings does not mail employees.
        $mailActive = filter_var(config('xeasy_notifications.mail_active', false), FILTER_VALIDATE_BOOLEAN);
        if (!empty($emp->EmailId_Vnr) && !$mailActive) {
            Log::info("TransferNotification: MAIL_ACTIVE is off, mail not sent for employee {$emp->EmployeeID}");
        }
        if (!empty($emp->EmailId_Vnr) && $mailActive) {
            try {
                Mail::raw($body, function ($msg) use ($emp, $subject) {
                    $msg->to($emp->EmailId_Vnr)->subject($subject);
                });
            } catch (\Exception $e) {
                Log::warning('TransferNotification: Email failed — ' . $e->getMessage());
            }
        }
    }

    private function buildNotificationBody(object $emp, string $type, string $typeLabel, array $details): string
    {
        $lines = [
            "Transfer Notification — {$typeLabel}",
            str_repeat('=', 50),
            "Employee : {$emp->FullName} ({$emp->EmpCode})",
            "Date     : " . now()->format('d-m-Y'),
        ];

        foreach ($details as $key => $value) {
            $lines[] = ucwords(str_replace('_', ' ', $key)) . ": {$value}";
        }

        $lines[] = '';
        $lines[] = 'Action Required:';
        if ($type === self::TYPE_COMPANY_TRANSFER) {
            $lines[] = '• Finance Team: Settle any outstanding imprest/advance for VVNR.';
            $lines[] = '• Previous RM (VVNR): Close pending approvals.';
            $lines[] = '• New RM (VNR): Employee will be reporting from the effective date.';
        } else {
            $lines[] = '• Previous Reporting Manager: Update pending claims.';
            $lines[] = '• New Reporting Manager: Employee assignment is effective immediately.';
            $lines[] = '• Finance Team: Verify advance/imprest eligibility for new location.';
        }

        return implode("\n", $lines);
    }

    /**
     * Log a sent notification to prevent duplicates.
     */
    private function logNotification(int $employeeId, string $type, $transferDate): void
    {
        if ($this->dryRun) {
            return;
        }
        try {
            DB::connection('mysql')->table('transfer_notifications')->insert([
                'employee_id'       => $employeeId,
                'notification_type' => $type,
                'transfer_date'     => $transferDate,
                'notified_at'       => now(),
                'created_at'        => now(),
                'updated_at'        => now(),
            ]);
        } catch (\Exception $e) {
            Log::warning('TransferNotification: Could not log to DB — ' . $e->getMessage());
        }
    }

    /**
     * Manually trigger notification for a specific employee transfer.
     * Can be called from an admin action or API endpoint.
     */
    public function triggerManual(int $employeeId, string $type, array $details): bool
    {
        try {
            $hrimsDb = \App\Helpers\DatabaseSwitcher::getHrimsDatabaseName();
            $emp = DB::connection('hrims')->selectOne("
                SELECT e.EmployeeID, e.EmpCode_New AS EmpCode,
                       CONCAT(TRIM(CONCAT(e.Fname,' ',COALESCE(e.Sname,''),' ',e.Lname))) AS FullName,
                       g.EmailId_Vnr, g.ReportingName
                FROM {$hrimsDb}.hrm_employee e
                LEFT JOIN {$hrimsDb}.hrm_employee_general g ON e.EmployeeID = g.EmployeeID
                WHERE e.EmployeeID = ?
            ", [$employeeId]);

            if (!$emp) return false;

            $this->sendTransferNotification($emp, $type, $details);
            $this->logNotification($employeeId, $type, now());
            return true;
        } catch (\Exception $e) {
            Log::error('TransferNotificationService@triggerManual: ' . $e->getMessage());
            return false;
        }
    }
}
