<?php

namespace App\Http\Controllers\admin;

use App\Http\Controllers\Controller;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Schema;
use Symfony\Component\Mime\Part\TextPart;

class VvnrEmailNotificationController extends Controller
{
    private const VVNR_COMPANY_ID = 11;
    private const VVNR_EXPENSE_CONN = 'expense_vvnr';

    public function __construct()
    {
        $this->middleware(function ($request, $next) {
            // Exclude public cron routes
            if ($request->is('cron/*')) {
                return $next($request);
            }

            $companyId = (int) session('company_id');
            if ($companyId !== self::VVNR_COMPANY_ID) {
                if ($request->expectsJson() || $request->ajax()) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Access Denied: VVNR company session is not active. This module cannot be accessed through VNR.'
                    ], 403);
                }

                abort(403, 'Access Denied: The VVNR Email Management module is strictly accessible only when the active company session is VVNR (Company ID: 11). It cannot be accessed while in VNR session.');
            }

            return $next($request);
        });
    }

    /**
     * Helper to get table query on the VVNR database connection.
     */
    private function vvnrTable(string $table)
    {
        return DB::connection(self::VVNR_EXPENSE_CONN)->table($table);
    }

    /**
     * Show VVNR Email Management Dashboard.
     */
    public function index()
    {
        $departments = DB::connection('hrims')
            ->table('hrm_employee as e')
            ->join('hrm_employee_general as g', 'e.EmployeeID', '=', 'g.EmployeeID')
            ->join('core_departments as d', 'g.DepartmentId', '=', 'd.id')
            ->where('e.CompanyId', self::VVNR_COMPANY_ID)
            ->where('e.EmpStatus', 'A')
            ->select('d.id', 'd.department_name')
            ->distinct()
            ->orderBy('d.department_name')
            ->get();

        if ($departments->isEmpty()) {
            $departments = DB::connection('hrims')
                ->table('core_departments')
                ->select('id', 'department_name')
                ->orderBy('department_name')
                ->get();
        }

        $grades = DB::connection('hrims')
            ->table('hrm_employee as e')
            ->join('hrm_employee_general as g', 'e.EmployeeID', '=', 'g.EmployeeID')
            ->join('core_grades as gr', 'g.GradeId', '=', 'gr.id')
            ->where('e.CompanyId', self::VVNR_COMPANY_ID)
            ->where('e.EmpStatus', 'A')
            ->whereNotNull('gr.grade_name')
            ->where('gr.grade_name', '!=', '')
            ->select('gr.id', 'gr.grade_name')
            ->distinct()
            ->orderBy('gr.grade_name')
            ->get();

        if ($grades->isEmpty()) {
            $grades = DB::connection('hrims')
                ->table('core_grades')
                ->select('id', 'grade_name')
                ->orderBy('grade_name')
                ->get();
        }

        // Deduplicate any grade records that share the same grade_name
        $grades = $grades->unique('grade_name')->values();

        // High-level summary stats directly from VVNR database
        $activeRulesCount = $this->vvnrTable('eml_notification')
            ->where('is_active', 1)
            ->count();

        $templatesCount = $this->vvnrTable('eml_email_templates')
            ->where('is_active', 1)
            ->count();

        $totalVvnrEmployees = DB::connection('hrims')
            ->table('hrm_employee')
            ->where('CompanyId', self::VVNR_COMPANY_ID)
            ->where('EmpStatus', 'A')
            ->count();

        $disabledRecipientsCount = $this->vvnrTable('eml_email_control')
            ->where('is_active', 0)
            ->count();

        $todaySentCount = $this->vvnrTable('eml_send_log')
            ->whereDate('created_at', today())
            ->where('is_sent', 1)
            ->count();

        return view('admin.vvnr-email-management', compact(
            'departments',
            'grades',
            'activeRulesCount',
            'templatesCount',
            'totalVvnrEmployees',
            'disabledRecipientsCount',
            'todaySentCount'
        ));
    }

    // =========================================================================
    // 1. TRIGGER RULES / AUTOMATION SCHEDULES (VVNR DB)
    // =========================================================================

    private function ensureTriggerTargetColumns(): void
    {
        try {
            $conn = DB::connection(self::VVNR_EXPENSE_CONN);
            $tables = $conn->select("SHOW TABLES LIKE 'eml_notification'");
            if (!empty($tables)) {
                $cols = array_column($conn->select("SHOW COLUMNS FROM eml_notification"), 'Field');
                if (!in_array('department_ids', $cols)) {
                    $conn->statement("ALTER TABLE eml_notification ADD COLUMN department_ids TEXT NULL AFTER email_template_id");
                }
                if (!in_array('grade_ids', $cols)) {
                    $conn->statement("ALTER TABLE eml_notification ADD COLUMN grade_ids TEXT NULL AFTER department_ids");
                }
                if (!in_array('recipient_level', $cols)) {
                    $conn->statement("ALTER TABLE eml_notification ADD COLUMN recipient_level VARCHAR(50) NOT NULL DEFAULT 'employee' AFTER stage");
                }
                if (!in_array('custom_recipient_emails', $cols)) {
                    $conn->statement("ALTER TABLE eml_notification ADD COLUMN custom_recipient_emails TEXT NULL AFTER recipient_level");
                }
            }
        } catch (\Throwable $e) {
            Log::error('ensureTriggerTargetColumns error: ' . $e->getMessage());
        }
    }

    private function ensureDefaultTemplates(): void
    {
        try {
            $existing = $this->vvnrTable('eml_email_templates')->pluck('name')->toArray();

            // Default Template 1: Approval Reminder with dynamic pending days
            if (!in_array('vvnr_approval_reminder', $existing)) {
                $this->vvnrTable('eml_email_templates')->insert([
                    'name'       => 'vvnr_approval_reminder',
                    'subject'    => '[VVNR Reminder] Pending Expense Claim Approval for {{employee_name}} ({{emp_code}}) - {{month}}',
                    'body_html'  => '<div style="font-family:Arial,sans-serif;color:#333;line-height:1.5;">
<p>Dear {{manager_name}},</p>
<p>This is to inform you that the monthly expense reimbursement claim for <strong>{{employee_name}} ({{emp_code}})</strong> for the month of <strong>{{month}}</strong> was submitted on <strong>{{submission_date}}</strong> and is awaiting your verification and approval.</p>
<div style="background:#fff8e6;border-left:4px solid #f59e0b;padding:12px;margin:15px 0;border-radius:4px;">
    <strong style="color:#b45309;font-size:14px;">Approval has been pending for {{pending_days}} days.</strong><br>
    <span style="font-size:12px;color:#666;">Pending Since: <strong>{{pending_since}}</strong> | Expected Due Date: <strong>{{due_date}}</strong></span>
</div>
<p>Please log in to the portal at your earliest convenience to review and approve the claim:</p>
<p><a href="{{login_url}}" style="display:inline-block;padding:9px 18px;background:#2b5c8f;color:#fff;text-decoration:none;border-radius:4px;font-weight:bold;">Review & Approve Claim</a></p>
<p style="margin-top:20px;font-size:12px;color:#777;">Regards,<br><strong>VVNR Expense Automation System</strong></p>
</div>',
                    'body_text'  => "Dear {{manager_name}},\n\nExpense claim for {{employee_name}} ({{emp_code}}) for {{month}} submitted on {{submission_date}} is pending approval.\nApproval has been pending for {{pending_days}} days.\nPending Since: {{pending_since}} | Due Date: {{due_date}}\n\nPlease review at: {{login_url}}",
                    'category'   => 'expense',
                    'is_active'  => 1,
                    'created_by' => Auth::id() ?: 1,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }

            // Default Template 2: Monthly Hierarchy-wise Summary for Manager\'s Manager
            if (!in_array('vvnr_hierarchy_monthly_summary', $existing)) {
                $this->vvnrTable('eml_email_templates')->insert([
                    'name'       => 'vvnr_hierarchy_monthly_summary',
                    'subject'    => '[VVNR Monthly Summary] Expense Claims Pending Status for {{month}} - Hierarchy of {{manager_name}}',
                    'body_html'  => '<div style="font-family:Arial,sans-serif;color:#333;line-height:1.5;">
<p>Dear {{manager_name}},</p>
<p>Please find below the consolidated monthly expense claim status and pending summary for all employees under your reporting hierarchy for the month of <strong>{{month}}</strong>:</p>

{{hierarchy_summary_table}}

<p style="margin-top:20px;">
    Kindly follow up with the respective reporting managers to ensure all pending submissions and approvals are cleared before the monthly cut-off date.
</p>
<p>You can monitor team claims online at: <a href="{{login_url}}" target="_blank">{{login_url}}</a></p>
<p style="margin-top:20px;font-size:12px;color:#777;">Regards,<br><strong>VVNR Expense Automation System</strong></p>
</div>',
                    'body_text'  => "Dear {{manager_name}},\n\nPlease review the consolidated monthly expense claim status and pending summary for all employees under your hierarchy for {{month}}.\n\nPlease log in to {{login_url}} for complete details.",
                    'category'   => 'expense',
                    'is_active'  => 1,
                    'created_by' => Auth::id() ?: 1,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        } catch (\Throwable $e) {
            Log::error('ensureDefaultTemplates error: ' . $e->getMessage());
        }
    }

    public function getRules()
    {
        $this->ensureTriggerTargetColumns();
        $this->ensureDefaultTemplates();

        try {
            $cols = [];
            try {
                $cols = array_column(DB::connection(self::VVNR_EXPENSE_CONN)->select("SHOW COLUMNS FROM eml_notification"), 'Field');
            } catch (\Throwable $e) {
                // Ignore if table query fails
            }

            $hasDeptCol        = in_array('department_ids', $cols);
            $hasGradeCol       = in_array('grade_ids', $cols);
            $hasRecipLevelCol  = in_array('recipient_level', $cols);
            $hasCustomEmailCol = in_array('custom_recipient_emails', $cols);

            $selectFields = [
                'n.id',
                'n.notification_type',
                'n.stage',
                'n.send_day',
                'n.email_template_id',
                'n.is_active',
                'n.description',
                'n.updated_at',
                't.name as template_name',
                't.subject as template_subject',
            ];

            $selectFields[] = $hasDeptCol ? 'n.department_ids' : DB::raw('NULL as department_ids');
            $selectFields[] = $hasGradeCol ? 'n.grade_ids' : DB::raw('NULL as grade_ids');
            $selectFields[] = $hasRecipLevelCol ? 'n.recipient_level' : DB::raw("'employee' as recipient_level");
            $selectFields[] = $hasCustomEmailCol ? 'n.custom_recipient_emails' : DB::raw('NULL as custom_recipient_emails');

            $rules = $this->vvnrTable('eml_notification as n')
                ->leftJoin('eml_email_templates as t', 'n.email_template_id', '=', 't.id')
                ->select($selectFields)
                ->orderBy('n.send_day')
                ->get();

            $deptMap = DB::connection('hrims')->table('core_departments')->pluck('department_name', 'id')->toArray();
            $gradeMap = DB::connection('hrims')->table('core_grades')->pluck('grade_name', 'id')->toArray();

            $rules = $rules->map(function ($r) use ($deptMap, $gradeMap) {
                $targetDeptIds = !empty($r->department_ids) ? (json_decode($r->department_ids, true) ?: array_filter(explode(',', $r->department_ids))) : [];
                $targetGradeIds = !empty($r->grade_ids) ? (json_decode($r->grade_ids, true) ?: array_filter(explode(',', $r->grade_ids))) : [];

                $deptNames = [];
                foreach ($targetDeptIds as $id) {
                    if (isset($deptMap[$id])) $deptNames[] = $deptMap[$id];
                }
                $gradeNames = [];
                foreach ($targetGradeIds as $id) {
                    if (isset($gradeMap[$id])) $gradeNames[] = $gradeMap[$id];
                }

                $r->target_dept_ids  = array_map('intval', $targetDeptIds);
                $r->target_grade_ids = array_map('intval', $targetGradeIds);
                $r->department_names = $deptNames;
                $r->grade_names      = $gradeNames;
                $r->recipient_level  = $r->recipient_level ?: 'employee';
                $r->custom_recipient_emails = $r->custom_recipient_emails ?: '';
                return $r;
            });

            return $this->jsonSuccess($rules);
        } catch (\Throwable $e) {
            Log::error('getRules error: ' . $e->getMessage());
            return $this->jsonError('Failed to load trigger rules: ' . $e->getMessage(), 500);
        }
    }

    public function saveRule(Request $request)
    {
        $this->ensureTriggerTargetColumns();

        $validated = $request->validate([
            'id'                      => 'nullable|integer',
            'notification_type'       => 'required|string|max:50',
            'recipient_level'         => 'nullable|string|in:employee,reporting_manager,manager_manager,custom',
            'custom_recipient_emails' => 'nullable|string',
            'stage'                   => 'nullable|string|max:50',
            'send_day'                => 'required|integer|min:1|max:31',
            'email_template_id'       => 'required|integer',
            'department_ids'          => 'nullable|array',
            'grade_ids'               => 'nullable|array',
            'is_active'               => 'boolean',
            'description'             => 'nullable|string|max:255',
        ]);

        $deptIds = !empty($validated['department_ids']) ? array_values(array_filter(array_map('intval', $validated['department_ids']))) : null;
        $gradeIds = !empty($validated['grade_ids']) ? array_values(array_filter(array_map('intval', $validated['grade_ids']))) : null;

        $data = [
            'notification_type'       => $validated['notification_type'],
            'stage'                   => $validated['stage'] ?? null,
            'recipient_level'         => $validated['recipient_level'] ?? 'employee',
            'custom_recipient_emails' => $validated['custom_recipient_emails'] ?? null,
            'send_day'                => (int) $validated['send_day'],
            'email_template_id'       => (int) $validated['email_template_id'],
            'department_ids'          => !empty($deptIds) ? json_encode($deptIds) : null,
            'grade_ids'               => !empty($gradeIds) ? json_encode($gradeIds) : null,
            'is_active'               => $request->boolean('is_active', true) ? 1 : 0,
            'description'             => $validated['description'] ?? null,
            'updated_at'              => now(),
        ];

        try {
            $cols = array_column(DB::connection(self::VVNR_EXPENSE_CONN)->select("SHOW COLUMNS FROM eml_notification"), 'Field');
            if (!in_array('department_ids', $cols)) {
                unset($data['department_ids']);
            }
            if (!in_array('grade_ids', $cols)) {
                unset($data['grade_ids']);
            }
            if (!in_array('recipient_level', $cols)) {
                unset($data['recipient_level']);
            }
            if (!in_array('custom_recipient_emails', $cols)) {
                unset($data['custom_recipient_emails']);
            }
        } catch (\Throwable $e) {
            // Ignore error
        }

        if (!empty($validated['id'])) {
            $this->vvnrTable('eml_notification')
                ->where('id', $validated['id'])
                ->update($data);
            $ruleId = $validated['id'];
            $msg = 'Trigger rule updated successfully on VVNR DB.';
        } else {
            $data['created_at'] = now();
            $ruleId = $this->vvnrTable('eml_notification')->insertGetId($data);
            $msg = 'Trigger rule created successfully on VVNR DB.';
        }

        return $this->jsonSuccess(['id' => $ruleId], $msg);
    }

    public function toggleRule($id)
    {
        $rule = $this->vvnrTable('eml_notification')
            ->where('id', $id)
            ->first();

        if (!$rule) {
            return $this->jsonError('Rule not found on VVNR DB', 404);
        }

        $newStatus = $rule->is_active ? 0 : 1;
        $this->vvnrTable('eml_notification')->where('id', $id)->update([
            'is_active'  => $newStatus,
            'updated_at' => now(),
        ]);

        return $this->jsonSuccess(['is_active' => $newStatus], 'Status updated successfully on VVNR DB');
    }

    public function deleteRule($id)
    {
        $this->vvnrTable('eml_notification')
            ->where('id', $id)
            ->delete();

        return $this->jsonSuccess(null, 'Rule deleted successfully from VVNR DB');
    }

    // =========================================================================
    // 2. EMAIL TEMPLATES MANAGEMENT (VVNR DB)
    // =========================================================================

    public function getTemplates()
    {
        $templates = $this->vvnrTable('eml_email_templates')
            ->select('id', 'name', 'subject', 'category', 'is_active', 'created_at', 'updated_at')
            ->orderBy('id', 'desc')
            ->get();

        return $this->jsonSuccess($templates);
    }

    public function getTemplate($id)
    {
        $template = $this->vvnrTable('eml_email_templates')->where('id', $id)->first();
        if (!$template) {
            return $this->jsonError('Template not found on VVNR DB', 404);
        }
        return $this->jsonSuccess($template);
    }

    public function saveTemplate(Request $request)
    {
        $validated = $request->validate([
            'id'        => 'nullable|integer',
            'name'      => 'required|string|max:100',
            'subject'   => 'required|string|max:255',
            'body_html' => 'required|string',
            'category'  => 'nullable|string|max:50',
            'is_active' => 'boolean',
        ]);

        $existing = $this->vvnrTable('eml_email_templates')
            ->where('name', $validated['name'])
            ->when(!empty($validated['id']), function ($q) use ($validated) {
                $q->where('id', '!=', $validated['id']);
            })
            ->first();
        if ($existing) {
            return $this->jsonError('A template with this name already exists in VVNR database. Please choose a different name.', 422);
        }

        $data = [
            'name'       => $validated['name'],
            'subject'    => $validated['subject'],
            'body_html'  => $validated['body_html'],
            'body_text'  => strip_tags($validated['body_html']),
            'category'   => $validated['category'] ?? 'expense',
            'is_active'  => $request->boolean('is_active', true) ? 1 : 0,
            'updated_by' => Auth::id(),
            'updated_at' => now(),
        ];

        if (!empty($validated['id'])) {
            $this->vvnrTable('eml_email_templates')->where('id', $validated['id'])->update($data);
            $templateId = $validated['id'];
            $msg = 'Template updated successfully on VVNR DB.';
        } else {
            $data['created_by'] = Auth::id();
            $data['created_at'] = now();
            $templateId = $this->vvnrTable('eml_email_templates')->insertGetId($data);
            $msg = 'Template created successfully on VVNR DB.';
        }

        return $this->jsonSuccess(['id' => $templateId], $msg);
    }

    public function previewTemplate($id)
    {
        $template = $this->vvnrTable('eml_email_templates')->where('id', $id)->first();
        if (!$template) {
            return $this->jsonError('Template not found on VVNR DB', 404);
        }

        $sampleTable = "<table style='border-collapse:collapse;width:100%;font-size:13px;'>
            <thead><tr style='background:#f2f2f2;'>
                <th style='padding:6px 10px;border:1px solid #ddd;'>S.No</th>
                <th style='padding:6px 10px;border:1px solid #ddd;'>Employee</th>
                <th style='padding:6px 10px;border:1px solid #ddd;'>Code</th>
                <th style='padding:6px 10px;border:1px solid #ddd;'>Month</th>
                <th style='padding:6px 10px;border:1px solid #ddd;'>Submission Date</th>
                <th style='padding:6px 10px;border:1px solid #ddd;'>Pending Days</th>
            </tr></thead>
            <tbody>
                <tr>
                    <td style='padding:6px 10px;border:1px solid #ddd;'>1</td>
                    <td style='padding:6px 10px;border:1px solid #ddd;'>Ankit Verma</td>
                    <td style='padding:6px 10px;border:1px solid #ddd;'>VVNR012</td>
                    <td style='padding:6px 10px;border:1px solid #ddd;'>September</td>
                    <td style='padding:6px 10px;border:1px solid #ddd;'>29-Sep-2026</td>
                    <td style='padding:6px 10px;border:1px solid #ddd;color:#b45309;font-weight:bold;'>7 days</td>
                </tr>
            </tbody>
        </table>";

        $sampleHierarchyTable = "<div style='font-family:Arial,sans-serif;font-size:13px;'>
            <table style='width:100%;border-collapse:collapse;margin-bottom:15px;background:#f8fafc;border:1px solid #e2e8f0;border-radius:6px;'>
                <tr>
                    <td style='padding:10px;text-align:center;border-right:1px solid #e2e8f0;'><span style='font-size:11px;color:#64748b;font-weight:bold;text-transform:uppercase;'>Total Staff</span><br><strong style='font-size:18px;color:#1e3a8a;'>18</strong></td>
                    <td style='padding:10px;text-align:center;border-right:1px solid #e2e8f0;'><span style='font-size:11px;color:#856404;font-weight:bold;text-transform:uppercase;'>Not Submitted</span><br><strong style='font-size:18px;color:#d97706;'>3</strong></td>
                    <td style='padding:10px;text-align:center;border-right:1px solid #e2e8f0;'><span style='font-size:11px;color:#0369a1;font-weight:bold;text-transform:uppercase;'>Approval Pending</span><br><strong style='font-size:18px;color:#0284c7;'>4</strong></td>
                    <td style='padding:10px;text-align:center;border-right:1px solid #e2e8f0;'><span style='font-size:11px;color:#b91c1c;font-weight:bold;text-transform:uppercase;'>Hard Copy Pending</span><br><strong style='font-size:18px;color:#dc2626;'>2</strong></td>
                    <td style='padding:10px;text-align:center;border-right:1px solid #e2e8f0;'><span style='font-size:11px;color:#475569;font-weight:bold;text-transform:uppercase;'>Other Pending</span><br><strong style='font-size:18px;color:#64748b;'>1</strong></td>
                    <td style='padding:10px;text-align:center;background:#fee2e2;'><span style='font-size:11px;color:#991b1b;font-weight:bold;text-transform:uppercase;'>Total Pending</span><br><strong style='font-size:18px;color:#b91c1c;'>10</strong></td>
                </tr>
            </table>
            <table style='width:100%;border-collapse:collapse;font-size:12px;border:1px solid #cbd5e1;'>
                <thead>
                    <tr style='background:#2b5c8f;color:#ffffff;'>
                        <th style='padding:7px 9px;border:1px solid #cbd5e1;text-align:left;'>S.No</th>
                        <th style='padding:7px 9px;border:1px solid #cbd5e1;text-align:left;'>Manager Name</th>
                        <th style='padding:7px 9px;border:1px solid #cbd5e1;text-align:center;'>Total Staff</th>
                        <th style='padding:7px 9px;border:1px solid #cbd5e1;text-align:center;'>Submission Pending</th>
                        <th style='padding:7px 9px;border:1px solid #cbd5e1;text-align:center;'>Approval Pending</th>
                        <th style='padding:7px 9px;border:1px solid #cbd5e1;text-align:center;'>Hard Copy Pending</th>
                        <th style='padding:7px 9px;border:1px solid #cbd5e1;text-align:center;'>Other Pending</th>
                        <th style='padding:7px 9px;border:1px solid #cbd5e1;text-align:center;'>Total Pending</th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td style='padding:6px 9px;border:1px solid #cbd5e1;'>1</td>
                        <td style='padding:6px 9px;border:1px solid #cbd5e1;'><strong>Rajesh Kumar</strong> (VVNR004)</td>
                        <td style='padding:6px 9px;border:1px solid #cbd5e1;text-align:center;'>10</td>
                        <td style='padding:6px 9px;border:1px solid #cbd5e1;text-align:center;color:#d97706;font-weight:bold;'>2</td>
                        <td style='padding:6px 9px;border:1px solid #cbd5e1;text-align:center;color:#0284c7;font-weight:bold;'>2</td>
                        <td style='padding:6px 9px;border:1px solid #cbd5e1;text-align:center;color:#dc2626;font-weight:bold;'>1</td>
                        <td style='padding:6px 9px;border:1px solid #cbd5e1;text-align:center;'>1</td>
                        <td style='padding:6px 9px;border:1px solid #cbd5e1;text-align:center;font-weight:bold;color:#b91c1c;'>6</td>
                    </tr>
                    <tr style='background:#f8fafc;'>
                        <td style='padding:6px 9px;border:1px solid #cbd5e1;'>2</td>
                        <td style='padding:6px 9px;border:1px solid #cbd5e1;'><strong>Amit Sharma</strong> (VVNR007)</td>
                        <td style='padding:6px 9px;border:1px solid #cbd5e1;text-align:center;'>8</td>
                        <td style='padding:6px 9px;border:1px solid #cbd5e1;text-align:center;color:#d97706;font-weight:bold;'>1</td>
                        <td style='padding:6px 9px;border:1px solid #cbd5e1;text-align:center;color:#0284c7;font-weight:bold;'>2</td>
                        <td style='padding:6px 9px;border:1px solid #cbd5e1;text-align:center;color:#dc2626;font-weight:bold;'>1</td>
                        <td style='padding:6px 9px;border:1px solid #cbd5e1;text-align:center;'>0</td>
                        <td style='padding:6px 9px;border:1px solid #cbd5e1;text-align:center;font-weight:bold;color:#b91c1c;'>4</td>
                    </tr>
                </tbody>
                <tfoot>
                    <tr style='background:#f1f5f9;font-weight:bold;'>
                        <td colspan='2' style='padding:7px 9px;border:1px solid #cbd5e1;text-align:right;'>Consolidated Total:</td>
                        <td style='padding:7px 9px;border:1px solid #cbd5e1;text-align:center;'>18</td>
                        <td style='padding:7px 9px;border:1px solid #cbd5e1;text-align:center;color:#d97706;'>3</td>
                        <td style='padding:7px 9px;border:1px solid #cbd5e1;text-align:center;color:#0284c7;'>4</td>
                        <td style='padding:7px 9px;border:1px solid #cbd5e1;text-align:center;color:#dc2626;'>2</td>
                        <td style='padding:7px 9px;border:1px solid #cbd5e1;text-align:center;'>1</td>
                        <td style='padding:7px 9px;border:1px solid #cbd5e1;text-align:center;color:#b91c1c;'>10</td>
                    </tr>
                </tfoot>
            </table>
        </div>";

        $placeholders = [
            'employee_name'          => 'Ankit Verma',
            'emp_code'               => 'VVNR012',
            'month'                  => Carbon::now()->subMonth()->format('F Y'),
            'submission_date'        => '29-Sep-2026',
            'pending_since'          => '29-Sep-2026',
            'pending_days'           => '7',
            'due_date'               => '06-Oct-2026',
            'manager_name'           => 'Rajesh Kumar (Reporting Manager)',
            'manager_manager_name'   => 'Sanjay Sharma (Reviewer / HOD)',
            'employee_table'         => $sampleTable,
            'hierarchy_summary_table'=> $sampleHierarchyTable,
            'login_url'              => url('/login'),
            'company_name'           => 'VVNR',
        ];

        $renderedSubject = $this->renderTemplatePlaceholders($template->subject, $placeholders);
        $renderedBody    = $this->renderTemplatePlaceholders($template->body_html, $placeholders);

        return $this->jsonSuccess([
            'subject'   => $renderedSubject,
            'body_html' => $renderedBody,
        ]);
    }

    public function deleteTemplate($id)
    {
        $isLinked = $this->vvnrTable('eml_notification')->where('email_template_id', $id)->exists();
        if ($isLinked) {
            return $this->jsonError('Cannot delete this template because it is linked to one or more active trigger rules. Please reassign or delete those rules first.', 422);
        }

        $this->vvnrTable('eml_email_templates')->where('id', $id)->delete();
        return $this->jsonSuccess(null, 'Template deleted successfully from VVNR DB.');
    }

    // =========================================================================
    // 3. RECIPIENT CONTROL (VVNR DB: eml_email_control)
    // =========================================================================

    public function getRecipients(Request $request)
    {
        $perPage = (int) $request->input('per_page', 25);
        $page    = (int) $request->input('page', 1);

        $query = DB::connection('hrims')
            ->table('hrm_employee as e')
            ->join('hrm_employee_general as g', 'e.EmployeeID', '=', 'g.EmployeeID')
            ->leftJoin('core_departments as d', 'g.DepartmentId', '=', 'd.id')
            ->leftJoin('core_grades as gr', 'g.GradeId', '=', 'gr.id')
            ->leftJoin('core_designation as dg', 'g.DesigId', '=', 'dg.id')
            ->where('e.CompanyId', self::VVNR_COMPANY_ID)
            ->where('e.EmpStatus', 'A');

        // Filters
        if ($request->filled('department_id')) {
            $query->where('g.DepartmentId', $request->department_id);
        }
        if ($request->filled('grade_id')) {
            $query->where('g.GradeId', $request->grade_id);
        }
        if ($request->filled('search')) {
            $s = trim($request->search);
            $query->where(function ($q) use ($s) {
                $q->where('e.EmpCode_New', 'like', "%$s%")
                  ->orWhereRaw("CONCAT(COALESCE(e.Fname,''),' ',COALESCE(e.Sname,''),' ',COALESCE(e.Lname,'')) LIKE ?", ["%$s%"])
                  ->orWhere('g.EmailId_Vnr', 'like', "%$s%")
                  ->orWhere('gr.grade_name', 'like', "%$s%");
            });
        }

        $total = (clone $query)->count();

        $employees = $query->select(
            'e.EmployeeID',
            'e.EmpCode_New as emp_code',
            DB::raw("TRIM(CONCAT(COALESCE(e.Fname,''),' ',COALESCE(e.Sname,''),' ',COALESCE(e.Lname,''))) as employee_name"),
            'd.department_name',
            'gr.grade_name',
            'dg.designation_name',
            'g.EmailId_Vnr as email',
            'g.MobileNo_Vnr as mobile'
        )
        ->orderBy('e.EmpCode_New')
        ->offset(($page - 1) * $perPage)
        ->limit($perPage)
        ->get();

        // Query controls from VVNR database connection
        $controls = $this->vvnrTable('eml_email_control')
            ->whereIn('employee_id', $employees->pluck('EmployeeID'))
            ->get()
            ->keyBy('employee_id');

        $result = $employees->map(function ($emp) use ($controls) {
            $ctrl = $controls->get($emp->EmployeeID);
            return [
                'employee_id'                 => $emp->EmployeeID,
                'emp_code'                    => $emp->emp_code,
                'employee_name'               => $emp->employee_name,
                'department'                  => $emp->department_name,
                'grade'                       => $emp->grade_name,
                'designation'                 => $emp->designation_name,
                'email'                       => $emp->email,
                'mobile'                      => $emp->mobile,
                'is_active'                   => $ctrl ? (bool) $ctrl->is_active : true,
                'receive_submission_reminder' => $ctrl ? (bool) $ctrl->receive_submission_reminder : true,
                'receive_receiving_pending'   => $ctrl ? (bool) $ctrl->receive_receiving_pending : true,
                'receive_escalation'          => $ctrl ? (bool) $ctrl->receive_escalation : true,
                'receive_approval_notification'=> $ctrl ? (bool) $ctrl->receive_approval_notification : true,
                'receive_payment_notification'=> $ctrl ? (bool) $ctrl->receive_payment_notification : true,
                'reason'                      => $ctrl->reason ?? null,
            ];
        });

        if ($request->input('show') === 'disabled') {
            $result = $result->filter(fn ($item) => !$item['is_active'])->values();
        } elseif ($request->input('show') === 'enabled') {
            $result = $result->filter(fn ($item) => $item['is_active'])->values();
        }

        return $this->jsonSuccess([
            'employees'  => $result,
            'pagination' => [
                'total'        => $total,
                'per_page'     => $perPage,
                'current_page' => $page,
                'last_page'    => (int) ceil($total / max(1, $perPage)),
            ],
        ]);
    }

    public function updateRecipient(Request $request)
    {
        $employeeId = $request->input('employee_id');
        $field      = $request->input('field');
        $value      = $request->boolean('value') ? 1 : 0;
        $reason     = $request->input('reason', '');

        $allowedFields = [
            'is_active',
            'receive_submission_reminder',
            'receive_receiving_pending',
            'receive_escalation',
            'receive_approval_notification',
            'receive_payment_notification'
        ];

        if (!in_array($field, $allowedFields)) {
            return $this->jsonError('Invalid control field', 422);
        }

        $this->vvnrTable('eml_email_control')->updateOrInsert(
            ['employee_id' => $employeeId],
            [
                $field       => $value,
                'reason'     => $reason ?: null,
                'updated_by' => Auth::id(),
                'updated_at' => now(),
                'created_at' => DB::raw('COALESCE(created_at, NOW())'),
            ]
        );

        return $this->jsonSuccess(null, 'Recipient setting updated in VVNR DB');
    }

    /**
     * Get department-wise recipient summary for VVNR, with optional grade filter and grade breakdown.
     */
    public function getDepartmentRecipientsSummary(Request $request)
    {
        $selectedGradeId = $request->input('grade_id');

        $query = DB::connection('hrims')
            ->table('hrm_employee as e')
            ->join('hrm_employee_general as g', 'e.EmployeeID', '=', 'g.EmployeeID')
            ->leftJoin('core_departments as d', 'g.DepartmentId', '=', 'd.id')
            ->leftJoin('core_grades as gr', 'g.GradeId', '=', 'gr.id')
            ->where('e.CompanyId', self::VVNR_COMPANY_ID)
            ->where('e.EmpStatus', 'A');

        if (!empty($selectedGradeId)) {
            $query->where('g.GradeId', $selectedGradeId);
        }

        $deptEmployees = $query->select(
            'e.EmployeeID',
            'g.DepartmentId',
            'g.GradeId',
            DB::raw("COALESCE(d.department_name, 'Unassigned') as department_name"),
            DB::raw("COALESCE(gr.grade_name, 'Unassigned') as grade_name")
        )->get();

        $controls = $this->vvnrTable('eml_email_control')
            ->whereIn('employee_id', $deptEmployees->pluck('EmployeeID'))
            ->get()
            ->keyBy('employee_id');

        $grouped = $deptEmployees->groupBy('DepartmentId')->map(function ($emps, $deptId) use ($controls) {
            $total = $emps->count();
            $deptName = $emps->first()->department_name;
            $activeCount = 0;
            $subCount = 0;
            $recvCount = 0;
            $escCount = 0;

            foreach ($emps as $emp) {
                $ctrl = $controls->get($emp->EmployeeID);
                $isActive = $ctrl ? (bool) $ctrl->is_active : true;
                if ($isActive) {
                    $activeCount++;
                    if ($ctrl ? (bool) $ctrl->receive_submission_reminder : true) $subCount++;
                    if ($ctrl ? (bool) $ctrl->receive_receiving_pending : true) $recvCount++;
                    if ($ctrl ? (bool) $ctrl->receive_escalation : true) $escCount++;
                }
            }

            // Grades breakdown for this specific department
            $gradesBreakdown = $emps->groupBy('GradeId')->map(function ($gradeEmps, $gId) use ($controls) {
                $gTotal = $gradeEmps->count();
                $gName = $gradeEmps->first()->grade_name;
                $gActive = 0;
                foreach ($gradeEmps as $ge) {
                    $ctrl = $controls->get($ge->EmployeeID);
                    if ($ctrl ? (bool) $ctrl->is_active : true) {
                        $gActive++;
                    }
                }
                return [
                    'grade_id'        => (int) ($gId ?: 0),
                    'grade_name'      => $gName,
                    'total_employees' => $gTotal,
                    'active_count'    => $gActive,
                    'disabled_count'  => $gTotal - $gActive,
                ];
            })->values()->sortBy('grade_name')->values();

            return [
                'department_id'   => $deptId ?: 0,
                'department_name' => $deptName,
                'total_employees' => $total,
                'active_count'    => $activeCount,
                'disabled_count'  => $total - $activeCount,
                'sub_count'       => $subCount,
                'recv_count'      => $recvCount,
                'esc_count'       => $escCount,
                'grades'          => $gradesBreakdown,
            ];
        })->values()->sortBy('department_name')->values();

        return $this->jsonSuccess($grouped);
    }

    /**
     * Get grade-wise recipient summary for VVNR.
     */
    public function getGradeRecipientsSummary()
    {
        $gradeEmployees = DB::connection('hrims')
            ->table('hrm_employee as e')
            ->join('hrm_employee_general as g', 'e.EmployeeID', '=', 'g.EmployeeID')
            ->leftJoin('core_grades as gr', 'g.GradeId', '=', 'gr.id')
            ->where('e.CompanyId', self::VVNR_COMPANY_ID)
            ->where('e.EmpStatus', 'A')
            ->select('e.EmployeeID', 'g.GradeId', DB::raw("COALESCE(gr.grade_name, 'Unassigned') as grade_name"))
            ->get();

        $controls = $this->vvnrTable('eml_email_control')
            ->whereIn('employee_id', $gradeEmployees->pluck('EmployeeID'))
            ->get()
            ->keyBy('employee_id');

        $grouped = $gradeEmployees->groupBy('GradeId')->map(function ($emps, $gradeId) use ($controls) {
            $total = $emps->count();
            $gradeName = $emps->first()->grade_name;
            $activeCount = 0;
            $subCount = 0;
            $recvCount = 0;
            $escCount = 0;

            foreach ($emps as $emp) {
                $ctrl = $controls->get($emp->EmployeeID);
                $isActive = $ctrl ? (bool) $ctrl->is_active : true;
                if ($isActive) {
                    $activeCount++;
                    if ($ctrl ? (bool) $ctrl->receive_submission_reminder : true) $subCount++;
                    if ($ctrl ? (bool) $ctrl->receive_receiving_pending : true) $recvCount++;
                    if ($ctrl ? (bool) $ctrl->receive_escalation : true) $escCount++;
                }
            }

            return [
                'grade_id'        => $gradeId ?: 0,
                'grade_name'      => $gradeName,
                'total_employees' => $total,
                'active_count'    => $activeCount,
                'disabled_count'  => $total - $activeCount,
                'sub_count'       => $subCount,
                'recv_count'      => $recvCount,
                'esc_count'       => $escCount,
            ];
        })->values()->sortBy('grade_name')->values();

        return $this->jsonSuccess($grouped);
    }

    public function bulkUpdateRecipients(Request $request)
    {
        $scope        = $request->input('scope', 'selected'); // 'selected', 'department', 'grade', 'all'
        $departmentId = $request->input('department_id');
        $gradeId      = $request->input('grade_id');
        $employeeIds  = $request->input('employee_ids', []);
        $field        = $request->input('field', 'is_active');
        $value        = $request->boolean('value') ? 1 : 0;
        $reason       = $request->input('reason', '');

        $allowedFields = [
            'is_active',
            'receive_submission_reminder',
            'receive_receiving_pending',
            'receive_escalation',
            'receive_approval_notification',
            'receive_payment_notification'
        ];

        if (!in_array($field, $allowedFields)) {
            return $this->jsonError('Invalid control field', 422);
        }

        // Resolve target employees based on scope
        if ($scope === 'department') {
            if (!$departmentId) {
                return $this->jsonError('Please select a department to update', 422);
            }
            $employeeIds = DB::connection('hrims')
                ->table('hrm_employee as e')
                ->join('hrm_employee_general as g', 'e.EmployeeID', '=', 'g.EmployeeID')
                ->where('e.CompanyId', self::VVNR_COMPANY_ID)
                ->where('e.EmpStatus', 'A')
                ->where('g.DepartmentId', $departmentId)
                ->pluck('e.EmployeeID')
                ->toArray();
        } elseif ($scope === 'grade') {
            if (!$gradeId) {
                return $this->jsonError('Please select a grade to update', 422);
            }
            $employeeIds = DB::connection('hrims')
                ->table('hrm_employee as e')
                ->join('hrm_employee_general as g', 'e.EmployeeID', '=', 'g.EmployeeID')
                ->where('e.CompanyId', self::VVNR_COMPANY_ID)
                ->where('e.EmpStatus', 'A')
                ->where('g.GradeId', $gradeId)
                ->pluck('e.EmployeeID')
                ->toArray();
        } elseif ($scope === 'department_and_grade') {
            if (!$departmentId || !$gradeId) {
                return $this->jsonError('Please select both a department and a grade to update', 422);
            }
            $employeeIds = DB::connection('hrims')
                ->table('hrm_employee as e')
                ->join('hrm_employee_general as g', 'e.EmployeeID', '=', 'g.EmployeeID')
                ->where('e.CompanyId', self::VVNR_COMPANY_ID)
                ->where('e.EmpStatus', 'A')
                ->where('g.DepartmentId', $departmentId)
                ->where('g.GradeId', $gradeId)
                ->pluck('e.EmployeeID')
                ->toArray();
        } elseif ($scope === 'all') {
            $employeeIds = DB::connection('hrims')
                ->table('hrm_employee as e')
                ->where('e.CompanyId', self::VVNR_COMPANY_ID)
                ->where('e.EmpStatus', 'A')
                ->pluck('e.EmployeeID')
                ->toArray();
        }

        if (empty($employeeIds)) {
            return $this->jsonError('No active VVNR employees found for the chosen criteria', 422);
        }

        $empDetails = DB::connection('hrims')
            ->table('hrm_employee as e')
            ->join('hrm_employee_general as g', 'e.EmployeeID', '=', 'g.EmployeeID')
            ->leftJoin('core_departments as d', 'g.DepartmentId', '=', 'd.id')
            ->whereIn('e.EmployeeID', $employeeIds)
            ->select(
                'e.EmployeeID',
                'e.EmpCode_New as emp_code',
                DB::raw("TRIM(CONCAT(e.Fname,' ',COALESCE(e.Sname,''),' ',e.Lname)) as employee_name"),
                'd.department_name',
                'g.EmailId_Vnr as email'
            )
            ->get()
            ->keyBy('EmployeeID');

        foreach ($employeeIds as $empId) {
            $detail = $empDetails->get($empId);
            $this->vvnrTable('eml_email_control')->updateOrInsert(
                ['employee_id' => $empId],
                [
                    'emp_code'      => $detail->emp_code ?? null,
                    'employee_name' => $detail->employee_name ?? null,
                    'department'    => $detail->department_name ?? null,
                    'email'         => $detail->email ?? null,
                    $field          => $value,
                    'reason'        => $reason ?: null,
                    'updated_by'    => Auth::id(),
                    'updated_at'    => now(),
                    'created_at'    => DB::raw('COALESCE(created_at, NOW())'),
                ]
            );
        }

        return $this->jsonSuccess(null, count($employeeIds) . ' recipients successfully updated in VVNR DB');
    }

    /**
     * Check if an employee is eligible to receive a specific email type from VVNR DB.
     */
    private function canReceiveEmail(int $employeeId, string $emailType): bool
    {
        $control = $this->vvnrTable('eml_email_control')
            ->where('employee_id', $employeeId)
            ->first();

        if (!$control) {
            return true; // Default allowed
        }

        if (!$control->is_active) {
            return false; // Master switch disabled
        }

        return match ($emailType) {
            'submission_reminder' => (bool) $control->receive_submission_reminder,
            'pending_hardcopy'    => (bool) $control->receive_receiving_pending,
            'pending_hardcopy_escalation' => (bool) $control->receive_escalation,
            'approval_notification' => (bool) $control->receive_approval_notification,
            'payment_notification'  => (bool) $control->receive_payment_notification,
            default => true,
        };
    }

    // =========================================================================
    // 4. PREVIEW & DISPATCH ENGINE (Automated & Manual)
    // =========================================================================

    private function getVvnrActiveYearId(): ?int
    {
        try {
            // VVNR-YEAR-01: the year that contains today. The Active flag on VVNR still points
            // to 2025-26, so summaries and reminders ran on last year's tables.
            $today = now('Asia/Kolkata')->toDateString();
            $yearId = $this->vvnrTable('financialyear')
                ->where('FromDate', '<=', $today)->where('Todate', '>=', $today)
                ->orderByDesc('YearId')
                ->value('YearId');
            if (!$yearId) {
                $yearId = $this->vvnrTable('financialyear')->where('Status', 'Active')->value('YearId');
            }

            if (!$yearId) {
                $yearId = $this->vvnrTable('financialyear')
                    ->orderBy('YearId', 'desc')
                    ->value('YearId');
            }

            return $yearId ? (int) $yearId : null;
        } catch (\Exception $e) {
            Log::error('Error fetching VVNR active year: ' . $e->getMessage());
            return null;
        }
    }

    /**
     * VVNR-MAIL-02: first day of a claim month in the VVNR financial year (Apr-Dec = first
     * year, Jan-Mar = second). Carbon::create(null, $month, 1) used the calendar year, so in
     * Jan-Mar the summary and due dates pointed at the wrong year.
     */
    private function vvnrMonthStart(int $month): Carbon
    {
        $year = null;
        try {
            $yearId = $this->getVvnrActiveYearId();
            $fy = $yearId ? $this->vvnrTable('financialyear')->where('YearId', $yearId)->first() : null;
            if ($fy && !empty($fy->FromDate)) {
                $from = Carbon::parse($fy->FromDate);
                $year = $month >= $from->month ? $from->year : $from->year + 1;
            }
        } catch (\Throwable $e) {
            // fall back below
        }
        if (!$year) {
            $now = Carbon::now('Asia/Kolkata');
            $fyStart = $now->month >= 4 ? $now->year : $now->year - 1;
            $year = $month >= 4 ? $fyStart : $fyStart + 1;
        }

        return Carbon::create($year, $month, 1, 0, 0, 0, 'Asia/Kolkata');
    }

    /**
     * Calculate system dynamic pending days based on start date and current date.
     */
    public static function calculatePendingDays(?string $pendingSinceDate, ?string $currentDate = null): int
    {
        if (empty($pendingSinceDate) || \App\Support\ZeroDate::none($pendingSinceDate) || \App\Support\ZeroDate::none($pendingSinceDate) || $pendingSinceDate === '-') {
            return 0;
        }

        try {
            $since = Carbon::parse($pendingSinceDate)->startOfDay();
            $current = $currentDate ? Carbon::parse($currentDate)->startOfDay() : Carbon::now('Asia/Kolkata')->startOfDay();
            return max(0, (int) $since->diffInDays($current));
        } catch (\Throwable $e) {
            return 0;
        }
    }

    /**
     * Replace both {{variable}} and {variable} placeholders safely.
     */
    private function renderTemplatePlaceholders(string $content, array $data): string
    {
        $map = [];
        foreach ($data as $key => $val) {
            $map['{{' . $key . '}}'] = (string) $val;
            $map['{' . $key . '}']   = (string) $val;
        }
        return str_replace(array_keys($map), array_values($map), $content);
    }

    /**
     * Map all active VVNR employees to their reporting hierarchy:
     * - Employee details
     * - Level 1: Reporting Manager (RepEmployeeID / AppraiserId)
     * - Level 2: Reporting Manager's Manager (ReviewerId / HOD / Manager's RepEmployeeID)
     */
    public function getVvnrEmployeeHierarchyMap(array $employeeIds = []): array
    {
        $query = DB::connection('hrims')
            ->table('hrm_employee as e')
            ->join('hrm_employee_general as g', 'e.EmployeeID', '=', 'g.EmployeeID')
            ->leftJoin('core_departments as d', 'g.DepartmentId', '=', 'd.id')
            ->leftJoin('core_grades as gr', 'g.GradeId', '=', 'gr.id')
            ->where('e.CompanyId', self::VVNR_COMPANY_ID)
            ->where('e.EmpStatus', 'A');

        if (!empty($employeeIds)) {
            $query->whereIn('e.EmployeeID', $employeeIds);
        }

        $employees = $query->select(
            'e.EmployeeID as employee_id',
            'e.EmpCode_New as emp_code',
            DB::raw("TRIM(CONCAT(COALESCE(e.Fname,''),' ',COALESCE(e.Sname,''),' ',COALESCE(e.Lname,''))) as employee_name"),
            'g.EmailId_Vnr as email',
            'g.MobileNo_Vnr as mobile',
            'g.DepartmentId as department_id',
            'd.department_name',
            'g.GradeId as grade_id',
            'gr.grade_name',
            'g.RepEmployeeID as general_rep_id'
        )->get();

        // Collect all manager IDs to resolve their full details
        $allManagerIds = [];
        foreach ($employees as $emp) {
            $l1 = $emp->general_rep_id;
            if ($l1 && $l1 != $emp->employee_id) $allManagerIds[] = (int) $l1;
        }
        $allManagerIds = array_values(array_unique(array_filter($allManagerIds)));

        $managerMasters = collect();
        if (!empty($allManagerIds)) {
            $managerMasters = DB::connection('hrims')
                ->table('hrm_employee as e')
                ->join('hrm_employee_general as g', 'e.EmployeeID', '=', 'g.EmployeeID')
                ->whereIn('e.EmployeeID', $allManagerIds)
                ->select(
                    'e.EmployeeID as manager_id',
                    'e.EmpCode_New as manager_code',
                    DB::raw("TRIM(CONCAT(COALESCE(e.Fname,''),' ',COALESCE(e.Sname,''),' ',COALESCE(e.Lname,''))) as manager_name"),
                    'g.EmailId_Vnr as manager_email',
                    'g.RepEmployeeID as manager_rep_id'
                )->get()->keyBy('manager_id');
        }

        $hierarchyMap = [];
        foreach ($employees as $emp) {
            $empId = (int) $emp->employee_id;
            $l1Id  = (int) $emp->general_rep_id;
            $l1Mgr = $managerMasters->get($l1Id);

            // Determine L2 (Reporting Manager's Manager)
            $l2Id = null;
            if ($l1Mgr && !empty($l1Mgr->manager_rep_id)) {
                $candidateL2 = (int) $l1Mgr->manager_rep_id;
                if ($candidateL2 && $candidateL2 !== $empId && $candidateL2 !== $l1Id) {
                    $l2Id = $candidateL2;
                }
            }

            $l2Mgr = $l2Id ? $managerMasters->get($l2Id) : null;
            if (!$l2Mgr && $l2Id) {
                $l2Direct = DB::connection('hrims')
                    ->table('hrm_employee as e')
                    ->join('hrm_employee_general as g', 'e.EmployeeID', '=', 'g.EmployeeID')
                    ->where('e.EmployeeID', $l2Id)
                    ->select(
                        'e.EmployeeID as manager_id',
                        'e.EmpCode_New as manager_code',
                        DB::raw("TRIM(CONCAT(COALESCE(e.Fname,''),' ',COALESCE(e.Sname,''),' ',COALESCE(e.Lname,''))) as manager_name"),
                        'g.EmailId_Vnr as manager_email'
                    )->first();
                if ($l2Direct) {
                    $l2Mgr = $l2Direct;
                }
            }

            $hierarchyMap[$empId] = [
                'employee_id'            => $empId,
                'emp_code'               => $emp->emp_code,
                'employee_name'          => $emp->employee_name,
                'email'                  => $emp->email,
                'mobile'                 => $emp->mobile,
                'department_id'          => $emp->department_id,
                'department_name'        => $emp->department_name ?: 'General',
                'grade_id'               => $emp->grade_id,
                'grade_name'             => $emp->grade_name ?: 'Unassigned',
                // Level 1: Reporting Manager
                'manager_id'             => $l1Id ?: null,
                'manager_code'           => $l1Mgr->manager_code ?? null,
                'manager_name'           => $l1Mgr->manager_name ?? ($l1Id ? 'Manager #' . $l1Id : 'N/A'),
                'manager_email'          => $l1Mgr->manager_email ?? null,
                // Level 2: Reporting Manager's Manager (Reviewer / HOD)
                'manager_manager_id'     => $l2Id ?: ($l1Id ?: $empId),
                'manager_manager_code'   => $l2Mgr->manager_code ?? ($l1Mgr->manager_code ?? $emp->emp_code),
                'manager_manager_name'   => $l2Mgr->manager_name ?? ($l1Mgr->manager_name ?? $emp->employee_name),
                'manager_manager_email'  => $l2Mgr->manager_email ?? ($l1Mgr->manager_email ?? $emp->email),
            ];
        }

        return $hierarchyMap;
    }

    /**
     * Build monthly consolidated hierarchy summary for Reporting Manager's Manager.
     */
    public function buildHierarchyMonthlySummary(int $month, ?int $targetL2ManagerId = null): array
    {
        $yearId = $this->getVvnrActiveYearId();
        $monthName = $this->vvnrMonthStart($month)->format('F');

        if (!$yearId) {
            return [
                'month'           => $month,
                'month_name'      => $monthName,
                'overall_summary' => [
                    'total_employees'    => 0,
                    'submission_pending' => 0,
                    'approval_pending'   => 0,
                    'hardcopy_pending'   => 0,
                    'other_pending'      => 0,
                    'total_pending'      => 0,
                ],
                'hierarchies'     => [],
                'l2_managers'     => [],
            ];
        }

        $hierarchyMap = $this->getVvnrEmployeeHierarchyMap();
        $monthexpenseTable = "y{$yearId}_monthexpensefinal";

        $mefRows = $this->vvnrTable($monthexpenseTable)
            ->where('Month', $month)
            ->select('EmployeeID', 'DateOfSubmit', 'Approved_Date', 'RecevingDate', 'Verified_Date', 'Fin_PayDate', 'Status')
            ->get()
            ->keyBy('EmployeeID');

        $classifiedEmployees = [];
        foreach ($hierarchyMap as $empId => $emp) {
            $mef = $mefRows->get($empId);

            $hasSubmitted = $mef && !empty($mef->DateOfSubmit) && \App\Support\ZeroDate::has($mef->DateOfSubmit);
            $isApproved   = $mef && !empty($mef->Approved_Date) && \App\Support\ZeroDate::has($mef->Approved_Date);
            $isReceived   = $mef && !empty($mef->RecevingDate) && \App\Support\ZeroDate::has($mef->RecevingDate);
            $isPaid       = $mef && !empty($mef->Fin_PayDate) && \App\Support\ZeroDate::has($mef->Fin_PayDate);
            $isVerified   = $mef && !empty($mef->Verified_Date) && \App\Support\ZeroDate::has($mef->Verified_Date);

            $submissionPending = !$hasSubmitted;
            $approvalPending   = $hasSubmitted && !$isApproved;
            $hardcopyPending   = $hasSubmitted && !$isReceived;
            $otherPending      = $hasSubmitted && $isApproved && (!$isPaid || !$isVerified || ($mef->Status ?? '') === 'Hold');

            $pendingSince = null;
            $submissionDateStr = '-';
            if ($submissionPending) {
                $pendingSince = $this->vvnrMonthStart($month)->addMonth()->startOfMonth()->format('Y-m-d');
            } elseif ($approvalPending) {
                $pendingSince = $mef->DateOfSubmit;
                $submissionDateStr = Carbon::parse($mef->DateOfSubmit)->format('d-M-Y');
            } elseif ($hardcopyPending) {
                $pendingSince = $mef->DateOfSubmit;
                $submissionDateStr = Carbon::parse($mef->DateOfSubmit)->format('d-M-Y');
            } else {
                $pendingSince = $mef->DateOfSubmit;
                $submissionDateStr = $hasSubmitted ? Carbon::parse($mef->DateOfSubmit)->format('d-M-Y') : '-';
            }

            $pendingDays = $this->calculatePendingDays($pendingSince);

            $classifiedEmployees[$empId] = array_merge($emp, [
                'has_submitted'         => $hasSubmitted,
                'submission_date'       => $submissionDateStr,
                'pending_since'         => $pendingSince ? Carbon::parse($pendingSince)->format('d-M-Y') : '-',
                'pending_days'          => $pendingDays,
                'due_date'              => $this->vvnrMonthStart($month)->addMonth()->day(7)->format('d-M-Y'),
                'is_submission_pending' => $submissionPending,
                'is_approval_pending'   => $approvalPending,
                'is_hardcopy_pending'   => $hardcopyPending,
                'is_other_pending'      => $otherPending,
                'is_any_pending'        => ($submissionPending || $approvalPending || $hardcopyPending || $otherPending),
            ]);
        }

        // Group by L2 Manager (Manager's Manager)
        $groupedByL2 = [];
        foreach ($classifiedEmployees as $emp) {
            $l2Id = $emp['manager_manager_id'];
            $groupedByL2[$l2Id][] = $emp;
        }

        $allL2Options = [];
        $hierarchies = [];

        $overallTotalEmployees    = 0;
        $overallSubmissionPending = 0;
        $overallApprovalPending   = 0;
        $overallHardcopyPending   = 0;
        $overallOtherPending      = 0;
        $overallTotalPending      = 0;

        foreach ($groupedByL2 as $l2Id => $reportees) {
            $l2First = $reportees[0];
            $l2Name  = $l2First['manager_manager_name'];
            $l2Code  = $l2First['manager_manager_code'];
            $l2Email = $l2First['manager_manager_email'];

            $allL2Options[] = [
                'manager_id'   => $l2Id,
                'manager_name' => $l2Name,
                'manager_code' => $l2Code,
                'count'        => count($reportees),
            ];

            // Subgroup by L1 (Reporting Manager)
            $groupedByL1 = [];
            foreach ($reportees as $rep) {
                $l1Id = $rep['manager_id'] ?: 0;
                $groupedByL1[$l1Id][] = $rep;
            }

            $managerBreakdown = [];
            $l2TotalEmp = count($reportees);
            $l2SubPend  = 0;
            $l2AppPend  = 0;
            $l2HardPend = 0;
            $l2OthPend  = 0;
            $l2TotPend  = 0;

            foreach ($groupedByL1 as $l1Id => $team) {
                $l1First = $team[0];
                $l1Name  = $l1First['manager_name'];
                $l1Code  = $l1First['manager_code'];
                $l1Email = $l1First['manager_email'];

                $tEmp  = count($team);
                $tSub  = count(array_filter($team, fn($e) => $e['is_submission_pending']));
                $tApp  = count(array_filter($team, fn($e) => $e['is_approval_pending']));
                $tHard = count(array_filter($team, fn($e) => $e['is_hardcopy_pending']));
                $tOth  = count(array_filter($team, fn($e) => $e['is_other_pending']));
                $tTot  = count(array_filter($team, fn($e) => $e['is_any_pending']));

                $l2SubPend  += $tSub;
                $l2AppPend  += $tApp;
                $l2HardPend += $tHard;
                $l2OthPend  += $tOth;
                $l2TotPend  += $tTot;

                $managerBreakdown[] = [
                    'manager_id'         => $l1Id,
                    'manager_name'       => $l1Name,
                    'manager_code'       => $l1Code,
                    'manager_email'      => $l1Email,
                    'total_employees'    => $tEmp,
                    'submission_pending' => $tSub,
                    'approval_pending'   => $tApp,
                    'hardcopy_pending'   => $tHard,
                    'other_pending'      => $tOth,
                    'total_pending'      => $tTot,
                    'team'               => $team,
                ];
            }

            usort($managerBreakdown, fn($a, $b) => strcmp($a['manager_name'], $b['manager_name']));

            $overallTotalEmployees    += $l2TotalEmp;
            $overallSubmissionPending += $l2SubPend;
            $overallApprovalPending   += $l2AppPend;
            $overallHardcopyPending   += $l2HardPend;
            $overallOtherPending      += $l2OthPend;
            $overallTotalPending      += $l2TotPend;

            $htmlTable = $this->generateHierarchyHtmlTable($monthName, $l2Name, [
                'total_employees'    => $l2TotalEmp,
                'submission_pending' => $l2SubPend,
                'approval_pending'   => $l2AppPend,
                'hardcopy_pending'   => $l2HardPend,
                'other_pending'      => $l2OthPend,
                'total_pending'      => $l2TotPend,
            ], $managerBreakdown);

            $hierarchies[$l2Id] = [
                'manager_manager_id'    => $l2Id,
                'manager_manager_name'  => $l2Name,
                'manager_manager_code'  => $l2Code,
                'manager_manager_email' => $l2Email,
                'summary' => [
                    'total_employees'    => $l2TotalEmp,
                    'submission_pending' => $l2SubPend,
                    'approval_pending'   => $l2AppPend,
                    'hardcopy_pending'   => $l2HardPend,
                    'other_pending'      => $l2OthPend,
                    'total_pending'      => $l2TotPend,
                ],
                'manager_breakdown'     => $managerBreakdown,
                'html_table'            => $htmlTable,
            ];
        }

        usort($allL2Options, fn($a, $b) => strcmp($a['manager_name'], $b['manager_name']));

        $overallSummary = [
            'total_employees'    => $overallTotalEmployees,
            'submission_pending' => $overallSubmissionPending,
            'approval_pending'   => $overallApprovalPending,
            'hardcopy_pending'   => $overallHardcopyPending,
            'other_pending'      => $overallOtherPending,
            'total_pending'      => $overallTotalPending,
        ];

        if ($targetL2ManagerId) {
            $filtered = isset($hierarchies[$targetL2ManagerId]) ? [$hierarchies[$targetL2ManagerId]] : [];
            return [
                'month'           => $month,
                'month_name'      => $monthName,
                'overall_summary' => $hierarchies[$targetL2ManagerId]['summary'] ?? $overallSummary,
                'hierarchies'     => $filtered,
                'l2_managers'     => $allL2Options,
            ];
        }

        return [
            'month'           => $month,
            'month_name'      => $monthName,
            'overall_summary' => $overallSummary,
            'hierarchies'     => array_values($hierarchies),
            'l2_managers'     => $allL2Options,
        ];
    }

    /**
     * Generate HTML layout for monthly hierarchy summary email and preview.
     */
    private function generateHierarchyHtmlTable(string $monthName, string $managerManagerName, array $summary, array $breakdown): string
    {
        $rowsHtml = '';
        foreach ($breakdown as $idx => $b) {
            $bg = ($idx % 2 === 0) ? '#ffffff' : '#f8fafc';
            $rowsHtml .= "<tr style='background:{$bg};'>
                <td style='padding:7px 10px;border:1px solid #cbd5e1;text-align:center;'>" . ($idx + 1) . "</td>
                <td style='padding:7px 10px;border:1px solid #cbd5e1;'><strong>{$b['manager_name']}</strong> <span style='color:#64748b;'>({$b['manager_code']})</span></td>
                <td style='padding:7px 10px;border:1px solid #cbd5e1;text-align:center;'>{$b['total_employees']}</td>
                <td style='padding:7px 10px;border:1px solid #cbd5e1;text-align:center;color:#d97706;font-weight:bold;'>{$b['submission_pending']}</td>
                <td style='padding:7px 10px;border:1px solid #cbd5e1;text-align:center;color:#0284c7;font-weight:bold;'>{$b['approval_pending']}</td>
                <td style='padding:7px 10px;border:1px solid #cbd5e1;text-align:center;color:#dc2626;font-weight:bold;'>{$b['hardcopy_pending']}</td>
                <td style='padding:7px 10px;border:1px solid #cbd5e1;text-align:center;color:#64748b;'>{$b['other_pending']}</td>
                <td style='padding:7px 10px;border:1px solid #cbd5e1;text-align:center;color:#b91c1c;font-weight:bold;background:#fef2f2;'>{$b['total_pending']}</td>
            </tr>";
        }

        return "<div style='font-family:Arial,sans-serif;color:#333;line-height:1.4;'>
            <!-- Summary Metric Cards -->
            <table style='width:100%;border-collapse:collapse;margin-bottom:16px;background:#f8fafc;border:1px solid #e2e8f0;border-radius:6px;'>
                <tr>
                    <td style='padding:10px;text-align:center;border-right:1px solid #e2e8f0;'><span style='font-size:10px;color:#64748b;font-weight:bold;text-transform:uppercase;'>Total Employees</span><br><strong style='font-size:18px;color:#1e3a8a;'>{$summary['total_employees']}</strong></td>
                    <td style='padding:10px;text-align:center;border-right:1px solid #e2e8f0;'><span style='font-size:10px;color:#856404;font-weight:bold;text-transform:uppercase;'>Not Submitted</span><br><strong style='font-size:18px;color:#d97706;'>{$summary['submission_pending']}</strong></td>
                    <td style='padding:10px;text-align:center;border-right:1px solid #e2e8f0;'><span style='font-size:10px;color:#0369a1;font-weight:bold;text-transform:uppercase;'>Approval Pending</span><br><strong style='font-size:18px;color:#0284c7;'>{$summary['approval_pending']}</strong></td>
                    <td style='padding:10px;text-align:center;border-right:1px solid #e2e8f0;'><span style='font-size:10px;color:#b91c1c;font-weight:bold;text-transform:uppercase;'>Hard Copy Pending</span><br><strong style='font-size:18px;color:#dc2626;'>{$summary['hardcopy_pending']}</strong></td>
                    <td style='padding:10px;text-align:center;border-right:1px solid #e2e8f0;'><span style='font-size:10px;color:#475569;font-weight:bold;text-transform:uppercase;'>Other Pending</span><br><strong style='font-size:18px;color:#64748b;'>{$summary['other_pending']}</strong></td>
                    <td style='padding:10px;text-align:center;background:#fee2e2;'><span style='font-size:10px;color:#991b1b;font-weight:bold;text-transform:uppercase;'>Total Pending</span><br><strong style='font-size:18px;color:#b91c1c;'>{$summary['total_pending']}</strong></td>
                </tr>
            </table>

            <!-- Manager Breakdown Table -->
            <table style='width:100%;border-collapse:collapse;font-size:12px;border:1px solid #cbd5e1;'>
                <thead>
                    <tr style='background:#2b5c8f;color:#ffffff;'>
                        <th style='padding:8px 10px;border:1px solid #cbd5e1;text-align:center;width:40px;'>#</th>
                        <th style='padding:8px 10px;border:1px solid #cbd5e1;text-align:left;'>Manager Name</th>
                        <th style='padding:8px 10px;border:1px solid #cbd5e1;text-align:center;'>Total Staff</th>
                        <th style='padding:8px 10px;border:1px solid #cbd5e1;text-align:center;'>Submission Pending</th>
                        <th style='padding:8px 10px;border:1px solid #cbd5e1;text-align:center;'>Approval Pending</th>
                        <th style='padding:8px 10px;border:1px solid #cbd5e1;text-align:center;'>Hard Copy Pending</th>
                        <th style='padding:8px 10px;border:1px solid #cbd5e1;text-align:center;'>Other Pending</th>
                        <th style='padding:8px 10px;border:1px solid #cbd5e1;text-align:center;background:#1e4268;'>Total Pending</th>
                    </tr>
                </thead>
                <tbody>
                    {$rowsHtml}
                </tbody>
                <tfoot>
                    <tr style='background:#f1f5f9;font-weight:bold;'>
                        <td colspan='2' style='padding:8px 10px;border:1px solid #cbd5e1;text-align:right;'>Hierarchy Consolidated Total:</td>
                        <td style='padding:8px 10px;border:1px solid #cbd5e1;text-align:center;'>{$summary['total_employees']}</td>
                        <td style='padding:8px 10px;border:1px solid #cbd5e1;text-align:center;color:#d97706;'>{$summary['submission_pending']}</td>
                        <td style='padding:8px 10px;border:1px solid #cbd5e1;text-align:center;color:#0284c7;'>{$summary['approval_pending']}</td>
                        <td style='padding:8px 10px;border:1px solid #cbd5e1;text-align:center;color:#dc2626;'>{$summary['hardcopy_pending']}</td>
                        <td style='padding:8px 10px;border:1px solid #cbd5e1;text-align:center;color:#64748b;'>{$summary['other_pending']}</td>
                        <td style='padding:8px 10px;border:1px solid #cbd5e1;text-align:center;color:#b91c1c;background:#fee2e2;'>{$summary['total_pending']}</td>
                    </tr>
                </tfoot>
            </table>
        </div>";
    }

    /**
     * API Endpoint to retrieve monthly hierarchy summary data.
     */
    public function getHierarchyMonthlySummary(Request $request)
    {
        $month = (int) $request->input('month', Carbon::now()->subMonth()->month);
        $managerManagerId = $request->filled('manager_manager_id') ? (int) $request->manager_manager_id : null;

        $summaryData = $this->buildHierarchyMonthlySummary($month, $managerManagerId);

        // Include recent months for the filter dropdown
        $monthsList = [];
        for ($i = 0; $i < 6; $i++) {
            $dt = Carbon::now()->subMonths($i);
            $monthsList[] = [
                'month' => $dt->month,
                'name'  => $dt->format('F Y'),
                'is_current' => ($dt->month === $month),
            ];
        }
        $summaryData['months_list'] = $monthsList;

        return $this->jsonSuccess($summaryData);
    }

    /**
     * Dispatch monthly hierarchy summary email to selected or all L2 managers.
     */
    public function dispatchHierarchyMonthlySummary(Request $request)
    {
        $month = (int) $request->input('month', Carbon::now()->subMonth()->month);
        $targetL2Id = $request->filled('manager_manager_id') ? (int) $request->manager_manager_id : null;
        $dryRun = $request->boolean('dry_run', false);

        $template = $this->vvnrTable('eml_email_templates')
            ->where('name', 'vvnr_hierarchy_monthly_summary')
            ->where('is_active', 1)
            ->first();

        if (!$template) {
            $template = $this->vvnrTable('eml_email_templates')
                ->where('category', 'expense')
                ->where('is_active', 1)
                ->first();
        }

        if (!$template) {
            return $this->jsonError('No active template found for hierarchy monthly summary on VVNR DB.', 404);
        }

        $summaryData = $this->buildHierarchyMonthlySummary($month, $targetL2Id);
        $hierarchies = $summaryData['hierarchies'] ?? [];

        if (empty($hierarchies)) {
            return $this->jsonError('No active employees or hierarchies found for the specified month.', 404);
        }

        $isMailActive = filter_var(config('xeasy_notifications.mail_active', false), FILTER_VALIDATE_BOOLEAN);
        $sentCount    = 0;
        $failedCount  = 0;
        $skippedCount = 0;

        foreach ($hierarchies as $h) {
            $recipientEmail = $h['manager_manager_email'] ?? null;
            if (empty($recipientEmail) || !filter_var($recipientEmail, FILTER_VALIDATE_EMAIL)) {
                $skippedCount++;
                continue;
            }

            $placeholders = [
                'employee_name'           => $h['manager_manager_name'],
                'emp_code'                => $h['manager_manager_code'],
                'month'                   => $summaryData['month_name'],
                'submission_date'         => '-',
                'pending_since'           => '-',
                'pending_days'            => '0',
                'due_date'                => $this->vvnrMonthStart($month)->addMonth()->day(7)->format('d-M-Y'),
                'manager_name'            => $h['manager_manager_name'],
                'manager_manager_name'    => $h['manager_manager_name'],
                'employee_table'          => '',
                'hierarchy_summary_table' => $h['html_table'],
                'login_url'               => url('/login'),
                'company_name'            => 'VVNR',
            ];

            $subject = $this->renderTemplatePlaceholders($template->subject, $placeholders);
            $body    = $this->renderTemplatePlaceholders($template->body_html, $placeholders);

            if ($dryRun) {
                $sentCount++;
                continue;
            }

            $success = false;
            $errorMsg = null;

            if ($isMailActive) {
                try {
                    Mail::html($body, function ($msg) use ($recipientEmail, $subject) {
                        $msg->to($recipientEmail)->subject($subject);
                    });
                    $success = true;
                    $sentCount++;
                } catch (\Throwable $e) {
                    $errorMsg = $e->getMessage();
                    $failedCount++;
                }
            } else {
                $success = true;
                $sentCount++;
            }

            $this->vvnrTable('eml_send_log')->insert([
                'template_id'           => $template->id,
                'template_name'         => $template->name,
                'notification_type'     => 'hierarchy_monthly_summary',
                'mail_category'         => 'expense',
                'stage'                 => 'monthly_summary',
                'subject'               => $subject,
                'body_html'             => $body,
                'recipient_employee_id' => $h['manager_manager_id'] ?? null,
                'recipient_emp_code'    => $h['manager_manager_code'] ?? null,
                'recipient_name'        => $h['manager_manager_name'] ?? null,
                'recipient_email'       => $recipientEmail,
                'company_id'            => self::VVNR_COMPANY_ID,
                'sent_for_month'        => $this->vvnrMonthStart($month)->format('Y-m'),
                'is_sent'               => $success,
                'sent_at'               => $success ? now() : null,
                'failed_at'             => $success ? null : now(),
                'error_message'         => $errorMsg,
                'created_at'            => now(),
                'updated_at'            => now(),
            ]);
        }

        return $this->jsonSuccess([
            'sent'    => $sentCount,
            'failed'  => $failedCount,
            'skipped' => $skippedCount,
            'total'   => count($hierarchies),
            'dry_run' => $dryRun,
        ], "Hierarchy summary dispatched for {$sentCount} reporting managers.");
    }

    public function previewTrigger(Request $request, string $type)
    {
        $ruleId = $request->input('rule_id');
        $query  = $this->vvnrTable('eml_notification')->where('is_active', 1);

        if ($ruleId) {
            $query->where('id', $ruleId);
        } else {
            $query->where('notification_type', $type);
        }

        $rule = $query->first();

        $recipients = $this->collectEligibleRecipients($type, $rule);
        return $this->jsonSuccess([
            'type'       => $type,
            'count'      => count($recipients),
            'recipients' => $recipients,
        ]);
    }

    public function dispatchTrigger(Request $request, string $type, ?int $ruleId = null)
    {
        $dryRun    = $request->boolean('dry_run', false);
        $ignoreDay = $request->boolean('ignore_day', true);
        $ruleId    = $ruleId ?: $request->input('rule_id');

        $query = $this->vvnrTable('eml_notification')
            ->where('is_active', 1);

        if ($ruleId) {
            $query->where('id', $ruleId);
        } else {
            $query->where('notification_type', $type);
        }

        $rule = $query->first();

        if (!$rule) {
            return $this->jsonError("No active trigger rule found for '{$type}' in VVNR DB.", 404);
        }

        if (!$ignoreDay && (int) $rule->send_day !== (int) now()->day) {
            return $this->jsonSuccess(null, "Skipped: Rule '{$type}' is scheduled for Day {$rule->send_day}, but today is Day " . now()->day);
        }

        $template = $this->vvnrTable('eml_email_templates')
            ->where('id', $rule->email_template_id)
            ->where('is_active', 1)
            ->first();

        if (!$template) {
            return $this->jsonError("Linked email template #{$rule->email_template_id} is inactive or missing on VVNR DB.", 404);
        }

        $isMailActive = filter_var(config('xeasy_notifications.mail_active', false), FILTER_VALIDATE_BOOLEAN);
        $recipients   = $this->collectEligibleRecipients($type, $rule);

        $sentCount    = 0;
        $failedCount  = 0;
        $skippedCount = 0;

        foreach ($recipients as $recipient) {
            // VVNR-MAIL-02: a custom recipient may be several addresses (comma-separated)
            $toList = array_values(array_filter(array_map('trim', explode(',', (string) ($recipient['email'] ?? ''))), fn ($e) => filter_var($e, FILTER_VALIDATE_EMAIL)));
            if (empty($toList)) {
                $skippedCount++;
                continue;
            }
            $recipientEmail = implode(', ', $toList);

            $placeholders = [
                'employee_name'           => $recipient['employee_name'] ?? '',
                'emp_code'                => $recipient['emp_code'] ?? '',
                'month'                   => $recipient['month_name'] ?? Carbon::now()->subMonth()->format('F'),
                'submission_date'         => $recipient['submission_date'] ?? '-',
                'pending_since'           => $recipient['pending_since'] ?? '-',
                'pending_days'            => (string) ($recipient['pending_days'] ?? '0'),
                'due_date'                => $recipient['due_date'] ?? now()->day(7)->format('d-M-Y'),
                'manager_name'            => $recipient['manager_name'] ?? '',
                'manager_manager_name'    => $recipient['manager_manager_name'] ?? '',
                'employee_table'          => $recipient['employee_table'] ?? '',
                'hierarchy_summary_table' => $recipient['hierarchy_summary_table'] ?? '',
                'login_url'               => url('/login'),
                'company_name'            => 'VVNR',
            ];

            $subject = $this->renderTemplatePlaceholders($template->subject, $placeholders);
            $body    = $this->renderTemplatePlaceholders($template->body_html, $placeholders);

            if ($dryRun) {
                $sentCount++;
                continue;
            }

            $success  = false;
            $errorMsg = null;

            if ($isMailActive) {
                try {
                    Mail::html($body, function ($msg) use ($toList, $subject) {
                        $msg->to($toList)
                            ->subject($subject);
                    });
                    $success = true;
                    $sentCount++;
                } catch (\Throwable $e) {
                    $errorMsg = $e->getMessage();
                    $failedCount++;
                }
            } else {
                $success = true;
                $sentCount++;
            }

            $this->vvnrTable('eml_send_log')->insert([
                'template_id'           => $template->id,
                'template_name'         => $template->name,
                'notification_type'     => $type,
                'mail_category'         => 'expense',
                'stage'                 => $rule->stage,
                'subject'               => $subject,
                'body_html'             => $body,
                'recipient_employee_id' => $recipient['employee_id'] ?? null,
                'recipient_emp_code'    => $recipient['emp_code'] ?? null,
                'recipient_name'        => $recipient['employee_name'] ?? null,
                'recipient_email'       => $recipientEmail,
                'company_id'            => self::VVNR_COMPANY_ID,
                'sent_for_month'        => now()->subMonth()->format('Y-m'),
                'is_sent'               => $success,
                'sent_at'               => $success ? now() : null,
                'failed_at'             => $success ? null : now(),
                'error_message'         => $errorMsg,
                'created_at'            => now(),
                'updated_at'            => now(),
            ]);
        }

        return $this->jsonSuccess([
            'sent'    => $sentCount,
            'failed'  => $failedCount,
            'skipped' => $skippedCount,
            'total'   => count($recipients),
            'dry_run' => $dryRun,
        ], "Execution completed on VVNR DB: {$sentCount} processed, {$failedCount} failed, {$skippedCount} skipped.");
    }

    public function sendTestEmail(Request $request)
    {
        $validated = $request->validate([
            'test_email'      => 'required|email',
            'template_id'     => 'required|integer',
            'recipient_level' => 'nullable|string',
        ]);

        $template = $this->vvnrTable('eml_email_templates')->where('id', $validated['template_id'])->first();
        if (!$template) {
            return $this->jsonError('Template not found on VVNR DB', 404);
        }

        $hierarchyData = $this->buildHierarchyMonthlySummary((int) Carbon::now()->subMonth()->month);
        $sampleHtmlTable = $hierarchyData['hierarchies'][0]['html_table'] ?? '<p><em>Sample hierarchy summary content</em></p>';

        $placeholders = [
            'employee_name'           => 'Test Employee (Claimant)',
            'emp_code'                => 'VVNR001',
            'month'                   => Carbon::now()->subMonth()->format('F Y'),
            'submission_date'         => Carbon::now()->subDays(7)->format('d-M-Y'),
            'pending_since'           => Carbon::now()->subDays(7)->format('d-M-Y'),
            'pending_days'            => '7',
            'due_date'                => Carbon::now()->addDays(3)->format('d-M-Y'),
            'manager_name'            => 'Test Reporting Manager',
            'manager_manager_name'    => 'Test Reviewer / HOD (Manager\'s Manager)',
            'employee_table'          => '<p><em>Sample team table placeholder content</em></p>',
            'hierarchy_summary_table' => $sampleHtmlTable,
            'login_url'               => url('/login'),
            'company_name'            => 'VVNR',
        ];

        $subject = '[TEST] ' . $this->renderTemplatePlaceholders($template->subject, $placeholders);
        $body    = $this->renderTemplatePlaceholders($template->body_html, $placeholders);

        try {
            Mail::html($body, function ($msg) use ($validated, $subject) {
                $msg->to($validated['test_email'])->subject($subject);
            });

            $this->vvnrTable('eml_send_log')->insert([
                'template_id'       => $template->id,
                'template_name'     => $template->name,
                'notification_type' => 'test_email',
                'mail_category'     => 'general',
                'subject'           => $subject,
                'body_html'         => $body,
                'recipient_name'    => 'Test Recipient',
                'recipient_email'   => $validated['test_email'],
                'company_id'        => self::VVNR_COMPANY_ID,
                'is_sent'           => true,
                'sent_at'           => now(),
                'created_at'        => now(),
                'updated_at'        => now(),
            ]);

            return $this->jsonSuccess(null, "Test email dispatched successfully to {$validated['test_email']}");
        } catch (\Throwable $e) {
            return $this->jsonError('Failed to send test email: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Collect eligible recipients honoring recipient_level (employee, reporting_manager, manager_manager, custom)
     * and computing system dynamic pending days, submission date, pending since date, and due date.
     */
    private function collectEligibleRecipients(string $type, ?object $rule = null): array
    {
        $yearId = $this->getVvnrActiveYearId();
        if (!$yearId) {
            return [];
        }

        $targetDeptIds = [];
        $targetGradeIds = [];
        $recipientLevel = $rule->recipient_level ?? 'employee';
        $customEmails = !empty($rule->custom_recipient_emails) ? array_filter(array_map('trim', explode(',', $rule->custom_recipient_emails))) : [];

        if ($rule) {
            if (!empty($rule->department_ids)) {
                $decodedDept = json_decode($rule->department_ids, true) ?: array_filter(explode(',', $rule->department_ids));
                $targetDeptIds = array_values(array_filter(array_map('intval', (array) $decodedDept)));
            }
            if (!empty($rule->grade_ids)) {
                $decodedGrade = json_decode($rule->grade_ids, true) ?: array_filter(explode(',', $rule->grade_ids));
                $targetGradeIds = array_values(array_filter(array_map('intval', (array) $decodedGrade)));
            }
        }

        if (!empty($targetGradeIds)) {
            $gradeNames = DB::connection('hrims')->table('core_grades')->whereIn('id', $targetGradeIds)->pluck('grade_name')->filter()->toArray();
            if (!empty($gradeNames)) {
                $expandedIds = DB::connection('hrims')->table('core_grades')->whereIn('grade_name', $gradeNames)->pluck('id')->toArray();
                $targetGradeIds = array_values(array_unique(array_merge($targetGradeIds, $expandedIds)));
            }
        }

        $today = Carbon::now('Asia/Kolkata');
        $previousMonth = $today->copy()->subMonth()->month;
        $previousMonthName = $today->copy()->subMonth()->format('F');
        // VVNR-MAIL-02: approval / hard-copy reminders cover every still-pending month of the year up to
        // last month (before, only last month was checked, so September cases were never reminded in Nov).
        $openMonths = [];
        for ($d = $this->vvnrMonthStart(4); $d->lte($today->copy()->subMonthNoOverflow()->startOfMonth()); $d->addMonth()) {
            $openMonths[] = (int) $d->month;
        }
        if (empty($openMonths)) {
            $openMonths = [$previousMonth];
        }

        $monthexpenseTable = "y{$yearId}_monthexpensefinal";
        $hierarchyMap = $this->getVvnrEmployeeHierarchyMap();

        $recipients = [];

        // Helper to resolve recipient details based on selected recipient_level
        $resolveRecipientTarget = function (array $empInfo) use ($recipientLevel, $customEmails) {
            if ($recipientLevel === 'custom' && !empty($customEmails)) {
                return [
                    'email' => implode(',', $customEmails),
                    'name'  => 'Custom Recipient',
                ];
            } elseif ($recipientLevel === 'reporting_manager') {
                return [
                    'email' => $empInfo['manager_email'] ?? $empInfo['email'],
                    'name'  => $empInfo['manager_name'] ?? $empInfo['employee_name'],
                ];
            } elseif ($recipientLevel === 'manager_manager') {
                return [
                    'email' => $empInfo['manager_manager_email'] ?? ($empInfo['manager_email'] ?? $empInfo['email']),
                    'name'  => $empInfo['manager_manager_name'] ?? ($empInfo['manager_name'] ?? $empInfo['employee_name']),
                ];
            }

            // Default: 'employee'
            return [
                'email' => $empInfo['email'],
                'name'  => $empInfo['employee_name'],
            ];
        };

        // ─────────────────────────────────────────────────────────────────────
        // CASE 1: SUBMISSION REMINDER (Employees who have not submitted)
        // ─────────────────────────────────────────────────────────────────────
        if ($type === 'submission_reminder') {
            $submittedEmpIds = $this->vvnrTable($monthexpenseTable)
                ->where('Month', $previousMonth)
                ->whereNotNull('DateOfSubmit')
                ->where('DateOfSubmit', '!=', '0000-00-00')
                ->pluck('EmployeeId')
                ->toArray();

            $pendingSince = $today->copy()->startOfMonth()->format('d-M-Y');
            $pendingDays  = $this->calculatePendingDays($today->copy()->startOfMonth()->format('Y-m-d'));
            $dueDate      = $today->copy()->day($rule->send_day ?? 7)->format('d-M-Y');

            foreach ($hierarchyMap as $empId => $emp) {
                if (in_array($empId, $submittedEmpIds)) {
                    continue;
                }

                if (!empty($targetDeptIds) && !in_array($emp['department_id'], $targetDeptIds)) {
                    continue;
                }
                if (!empty($targetGradeIds) && !in_array($emp['grade_id'], $targetGradeIds)) {
                    continue;
                }

                if (!$this->canReceiveEmail($empId, 'submission_reminder')) {
                    continue;
                }

                $target = $resolveRecipientTarget($emp);

                $recipients[] = [
                    'employee_id'          => $empId,
                    'emp_code'             => $emp['emp_code'],
                    'employee_name'        => $emp['employee_name'],
                    'manager_name'         => $emp['manager_name'],
                    'manager_manager_name' => $emp['manager_manager_name'],
                    'email'                => $target['email'],
                    'recipient_name'       => $target['name'],
                    'month_name'           => $previousMonthName,
                    'submission_date'      => 'Not Submitted',
                    'pending_since'        => $pendingSince,
                    'pending_days'         => $pendingDays,
                    'due_date'             => $dueDate,
                ];
            }
        }

        // ─────────────────────────────────────────────────────────────────────
        // CASE 2: APPROVAL NOTIFICATION (Submitted, but Approval Pending)
        // ─────────────────────────────────────────────────────────────────────
        elseif ($type === 'approval_notification') {
            $pendingApprovalRows = $this->vvnrTable("{$monthexpenseTable} as m")
                ->whereIn('m.Month', $openMonths)
                ->whereNotNull('m.DateOfSubmit')
                ->where('m.DateOfSubmit', '!=', '0000-00-00')
                ->where(function ($q) {
                    $q->whereNull('m.Approved_Date')->orWhere('m.Approved_Date', '0000-00-00');
                })
                ->select('m.EmployeeId', 'm.DateOfSubmit', 'm.Month')
                ->get();

            // Default recipient for approval reminder is reporting_manager unless custom or manager_manager selected
            $approvalRecipientLevel = ($recipientLevel === 'employee') ? 'reporting_manager' : $recipientLevel;

            foreach ($pendingApprovalRows as $row) {
                $empId = (int) $row->EmployeeId;
                if (!isset($hierarchyMap[$empId])) {
                    continue;
                }

                $emp = $hierarchyMap[$empId];

                if (!empty($targetDeptIds) && !in_array($emp['department_id'], $targetDeptIds)) {
                    continue;
                }
                if (!empty($targetGradeIds) && !in_array($emp['grade_id'], $targetGradeIds)) {
                    continue;
                }

                if (!$this->canReceiveEmail($empId, 'approval_notification')) {
                    continue;
                }

                $subDate      = Carbon::parse($row->DateOfSubmit)->format('d-M-Y');
                $pendingSince = $subDate;
                $pendingDays  = $this->calculatePendingDays($row->DateOfSubmit);
                $dueDate      = Carbon::parse($row->DateOfSubmit)->addDays(5)->format('d-M-Y');

                if ($approvalRecipientLevel === 'manager_manager') {
                    $targetEmail = $emp['manager_manager_email'] ?: ($emp['manager_email'] ?: $emp['email']);
                    $targetName  = $emp['manager_manager_name'] ?: $emp['manager_name'];
                } elseif ($approvalRecipientLevel === 'custom' && !empty($customEmails)) {
                    $targetEmail = implode(",", $customEmails);
                    $targetName  = 'Custom Recipient';
                } elseif ($approvalRecipientLevel === 'employee') {
                    $targetEmail = $emp['email'];
                    $targetName  = $emp['employee_name'];
                } else {
                    // reporting_manager
                    $targetEmail = $emp['manager_email'] ?: $emp['email'];
                    $targetName  = $emp['manager_name'] ?: $emp['employee_name'];
                }

                $recipients[] = [
                    'employee_id'          => $empId,
                    'emp_code'             => $emp['emp_code'],
                    'employee_name'        => $emp['employee_name'],
                    'manager_name'         => $emp['manager_name'],
                    'manager_manager_name' => $emp['manager_manager_name'],
                    'email'                => $targetEmail,
                    'recipient_name'       => $targetName,
                    'month_name'           => $this->vvnrMonthStart((int) $row->Month)->format('F'),
                    'submission_date'      => $subDate,
                    'pending_since'        => $pendingSince,
                    'pending_days'         => $pendingDays,
                    'due_date'             => $dueDate,
                ];
            }
        }

        // ─────────────────────────────────────────────────────────────────────
        // CASE 3: PENDING HARDCOPY / COURIER (Submitted, but RecevingDate pending)
        // ─────────────────────────────────────────────────────────────────────
        elseif ($type === 'pending_hardcopy') {
            $submittedRows = $this->vvnrTable("{$monthexpenseTable} as m")
                ->whereIn('m.Month', $openMonths)
                ->whereNotNull('m.DateOfSubmit')
                ->where('m.DateOfSubmit', '!=', '0000-00-00')
                // COURIER-03: remind only once 3 days have passed since submission
                ->where('m.DateOfSubmit', '<=', now('Asia/Kolkata')->subDays((int) config('xeasy_notifications.courier_reminder.start_after_days', 3))->toDateString())
                ->where(function ($q) {
                    $q->whereNull('m.RecevingDate')->orWhere('m.RecevingDate', '0000-00-00');
                })
                ->select('m.EmployeeId', 'm.DateOfSubmit', 'm.Month')
                ->get();

            foreach ($submittedRows as $row) {
                $empId = (int) $row->EmployeeId;
                if (!isset($hierarchyMap[$empId])) {
                    continue;
                }

                $emp = $hierarchyMap[$empId];

                if (!empty($targetDeptIds) && !in_array($emp['department_id'], $targetDeptIds)) {
                    continue;
                }
                if (!empty($targetGradeIds) && !in_array($emp['grade_id'], $targetGradeIds)) {
                    continue;
                }

                if (!$this->canReceiveEmail($empId, 'pending_hardcopy')) {
                    continue;
                }

                $subDate      = Carbon::parse($row->DateOfSubmit)->format('d-M-Y');
                $pendingSince = $subDate;
                $pendingDays  = $this->calculatePendingDays($row->DateOfSubmit);
                $dueDate      = Carbon::parse($row->DateOfSubmit)->addDays(10)->format('d-M-Y');

                $target = $resolveRecipientTarget($emp);

                $recipients[] = [
                    'employee_id'          => $empId,
                    'emp_code'             => $emp['emp_code'],
                    'employee_name'        => $emp['employee_name'],
                    'manager_name'         => $emp['manager_name'],
                    'manager_manager_name' => $emp['manager_manager_name'],
                    'email'                => $target['email'],
                    'recipient_name'       => $target['name'],
                    'month_name'           => $this->vvnrMonthStart((int) $row->Month)->format('F'),
                    'submission_date'      => $subDate,
                    'pending_since'        => $pendingSince,
                    'pending_days'         => $pendingDays,
                    'due_date'             => $dueDate,
                ];
            }
        }

        // ─────────────────────────────────────────────────────────────────────
        // CASE 4: HARDCOPY OVERDUE ESCALATION (Grouped by manager / manager's manager)
        // ─────────────────────────────────────────────────────────────────────
        elseif ($type === 'pending_hardcopy_escalation') {
            $pendingRows = $this->vvnrTable("{$monthexpenseTable} as m")
                ->whereIn('m.Month', $openMonths)
                ->whereNotNull('m.DateOfSubmit')
                ->where('m.DateOfSubmit', '!=', '0000-00-00')
                ->where(function ($q) {
                    $q->whereNull('m.RecevingDate')->orWhere('m.RecevingDate', '0000-00-00');
                })
                ->select('m.EmployeeId', 'm.DateOfSubmit', 'm.Month')
                ->get();

            // Group by recipient manager (either reporting_manager or manager_manager)
            $groupedByTargetMgr = [];
            foreach ($pendingRows as $row) {
                $empId = (int) $row->EmployeeId;
                if (!isset($hierarchyMap[$empId])) {
                    continue;
                }

                $emp = $hierarchyMap[$empId];

                if (!empty($targetDeptIds) && !in_array($emp['department_id'], $targetDeptIds)) {
                    continue;
                }
                if (!empty($targetGradeIds) && !in_array($emp['grade_id'], $targetGradeIds)) {
                    continue;
                }

                $targetMgrId = ($recipientLevel === 'manager_manager')
                    ? $emp['manager_manager_id']
                    : ($emp['manager_id'] ?: $empId);

                $emp['DateOfSubmit'] = $row->DateOfSubmit;
                $emp['claim_month'] = $this->vvnrMonthStart((int) $row->Month)->format('F');
                $emp['pending_days'] = $this->calculatePendingDays($row->DateOfSubmit);
                $groupedByTargetMgr[$targetMgrId][] = $emp;
            }

            foreach ($groupedByTargetMgr as $mgrId => $members) {
                $first = $members[0];
                $targetEmail = ($recipientLevel === 'manager_manager') ? $first['manager_manager_email'] : $first['manager_email'];
                $targetName  = ($recipientLevel === 'manager_manager') ? $first['manager_manager_name'] : $first['manager_name'];

                if ($recipientLevel === 'custom' && !empty($customEmails)) {
                    $targetEmail = implode(',', $customEmails);
                    $targetName  = 'Custom Recipient';
                }

                if (!$this->canReceiveEmail($mgrId, 'pending_hardcopy_escalation')) {
                    continue;
                }

                $rows = '';
                foreach ($members as $idx => $m) {
                    $subDate = ($m['DateOfSubmit'] && \App\Support\ZeroDate::has($m['DateOfSubmit'])) ? Carbon::parse($m['DateOfSubmit'])->format('d-M-Y') : '-';
                    $rows .= "<tr>
                        <td style='padding:6px 10px;border:1px solid #ddd;text-align:center;'>" . ($idx + 1) . "</td>
                        <td style='padding:6px 10px;border:1px solid #ddd;'>{$m['employee_name']}</td>
                        <td style='padding:6px 10px;border:1px solid #ddd;'>{$m['emp_code']}</td>
                        <td style='padding:6px 10px;border:1px solid #ddd;'>{$m['claim_month']}</td>
                        <td style='padding:6px 10px;border:1px solid #ddd;'>{$subDate}</td>
                        <td style='padding:6px 10px;border:1px solid #ddd;color:#b45309;font-weight:bold;'>{$m['pending_days']} days</td>
                    </tr>";
                }

                $empTable = "<table style='border-collapse:collapse;width:100%;font-size:13px;'>
                    <thead><tr style='background:#f2f2f2;'>
                        <th style='padding:6px 10px;border:1px solid #ddd;'>S.No</th>
                        <th style='padding:6px 10px;border:1px solid #ddd;'>Name</th>
                        <th style='padding:6px 10px;border:1px solid #ddd;'>Code</th>
                        <th style='padding:6px 10px;border:1px solid #ddd;'>Month</th>
                        <th style='padding:6px 10px;border:1px solid #ddd;'>Submission Date</th>
                        <th style='padding:6px 10px;border:1px solid #ddd;'>Pending Days</th>
                    </tr></thead>
                    <tbody>{$rows}</tbody>
                </table>";

                $recipients[] = [
                    'employee_id'          => $mgrId,
                    'emp_code'             => $first['manager_code'] ?? '',
                    'employee_name'        => $targetName,
                    'manager_name'         => $first['manager_name'] ?? '',
                    'manager_manager_name' => $first['manager_manager_name'] ?? '',
                    'email'                => $targetEmail,
                    'recipient_name'       => $targetName,
                    'month_name'           => $this->vvnrMonthStart((int) $row->Month)->format('F'),
                    'employee_table'       => $empTable,
                    'submission_date'      => Carbon::parse($members[0]['DateOfSubmit'])->format('d-M-Y'),
                    'pending_since'        => Carbon::parse($members[0]['DateOfSubmit'])->format('d-M-Y'),
                    'pending_days'         => (string) $members[0]['pending_days'],
                    'due_date'             => Carbon::parse($members[0]['DateOfSubmit'])->addDays(10)->format('d-M-Y'),
                ];
            }
        }

        // ─────────────────────────────────────────────────────────────────────
        // CASE 5: HIERARCHY MONTHLY SUMMARY (Reporting Manager's Manager Report)
        // ─────────────────────────────────────────────────────────────────────
        elseif ($type === 'hierarchy_monthly_summary') {
            $summaryData = $this->buildHierarchyMonthlySummary($previousMonth);
            $hierarchies = $summaryData['hierarchies'] ?? [];

            foreach ($hierarchies as $h) {
                $targetEmail = $h['manager_manager_email'];
                $targetName  = $h['manager_manager_name'];

                if ($recipientLevel === 'custom' && !empty($customEmails)) {
                    $targetEmail = implode(',', $customEmails);
                    $targetName  = 'Custom Recipient';
                }

                $recipients[] = [
                    'employee_id'             => $h['manager_manager_id'],
                    'emp_code'                => $h['manager_manager_code'],
                    'employee_name'           => $targetName,
                    'manager_name'            => $targetName,
                    'manager_manager_name'    => $targetName,
                    'email'                   => $targetEmail,
                    'recipient_name'          => $targetName,
                    'month_name'              => $previousMonthName,
                    'submission_date'         => '-',
                    'pending_since'           => '-',
                    'pending_days'            => '0',
                    'due_date'                => $this->vvnrMonthStart($previousMonth)->addMonth()->day(7)->format('d-M-Y'),
                    'hierarchy_summary_table' => $h['html_table'],
                ];
            }
        }

        return $recipients;
    }

    // =========================================================================
    // 5. EMAIL AUDIT LOGS (VVNR DB: eml_send_log)
    // =========================================================================

    public function getLogs(Request $request)
    {
        $perPage = (int) $request->input('per_page', 25);
        $page    = (int) $request->input('page', 1);

        $query = $this->vvnrTable('eml_send_log');

        if ($request->filled('notification_type')) {
            $query->where('notification_type', $request->notification_type);
        }
        if ($request->filled('is_sent') && $request->is_sent !== '') {
            $query->where('is_sent', (int) $request->is_sent);
        }
        if ($request->filled('date_from')) {
            $query->whereDate('created_at', '>=', $request->date_from);
        }
        if ($request->filled('date_to')) {
            $query->whereDate('created_at', '<=', $request->date_to);
        }
        if ($request->filled('search')) {
            $s = trim($request->search);
            $query->where(function ($q) use ($s) {
                $q->where('recipient_emp_code', 'like', "%$s%")
                  ->orWhere('recipient_name', 'like', "%$s%")
                  ->orWhere('recipient_email', 'like', "%$s%")
                  ->orWhere('subject', 'like', "%$s%");
            });
        }

        $total = (clone $query)->count();

        $logs = $query->orderBy('id', 'desc')
            ->offset(($page - 1) * $perPage)
            ->limit($perPage)
            ->get();

        return $this->jsonSuccess([
            'logs'       => $logs,
            'pagination' => [
                'total'        => $total,
                'per_page'     => $perPage,
                'current_page' => $page,
                'last_page'    => (int) ceil($total / max(1, $perPage)),
            ],
        ]);
    }

    public function getLogDetail($id)
    {
        $log = $this->vvnrTable('eml_send_log')
            ->where('id', $id)
            ->first();

        if (!$log) {
            return $this->jsonError('Log entry not found on VVNR DB', 404);
        }

        return $this->jsonSuccess($log);
    }

    // =========================================================================
    // 6. CRON JOB ENTRY POINTS
    // =========================================================================

    public function cronSubmissionReminder(Request $request)
    {
        return $this->dispatchTrigger($request, 'submission_reminder');
    }

    public function cronCourierPending(Request $request)
    {
        return $this->dispatchTrigger($request, 'pending_hardcopy');
    }

    public function cronHardcopyEscalation(Request $request)
    {
        return $this->dispatchTrigger($request, 'pending_hardcopy_escalation');
    }

    public function cronApprovalReminder(Request $request)
    {
        return $this->dispatchTrigger($request, 'approval_notification');
    }

    public function cronHierarchyMonthlySummary(Request $request)
    {
        return $this->dispatchHierarchyMonthlySummary($request);
    }

    /**
     * Single Master Daily Cron Trigger:
     * Checks all active rules in eml_notification for VVNR where send_day == today->day
     * and executes them. If ?force_all=1 is passed, executes all active rules regardless of day.
     */
    public function cronDailyAutomation(Request $request)
    {
        $todayDay = (int) now()->day;
        $forceAll = $request->boolean('force_all', false);
        $dryRun   = $request->boolean('dry_run', false);

        $query = $this->vvnrTable('eml_notification')->where('is_active', 1);
        if (!$forceAll) {
            $query->where('send_day', $todayDay);
        }
        $rules = $query->get();

        if ($rules->isEmpty()) {
            return $this->jsonSuccess([
                'today_day'       => $todayDay,
                'rules_processed' => 0,
            ], "No active VVNR email trigger rules scheduled for Day {$todayDay} of this month.");
        }

        $results = [];
        foreach ($rules as $rule) {
            $req = new Request(['dry_run' => $dryRun, 'ignore_day' => true]);
            $res = $this->dispatchTrigger($req, $rule->notification_type, $rule->id);
            $results[] = [
                'rule_id'           => $rule->id,
                'notification_type' => $rule->notification_type,
                'scheduled_day'     => $rule->send_day,
                'details'           => $res->getData()
            ];
        }

        return $this->jsonSuccess([
            'today_day'       => $todayDay,
            'rules_processed' => count($rules),
            'dry_run'         => $dryRun,
            'results'         => $results,
        ], "Daily VVNR email automation completed for Day {$todayDay}. Processed " . count($rules) . " active rules.");
    }
}
