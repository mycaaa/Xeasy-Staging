<?php

namespace App\Http\Controllers\admin;

use App\Http\Controllers\Controller;
use App\Helpers\DatabaseSwitcher;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class MailLogController extends Controller
{
    private function getTable(): string
    {
        return 'sent_emails';
    }

    private function getSubjectCategories(): array
    {
        return [
            'advance_request' => ['keyword' => 'Advance Request', 'label' => 'Advance Request'],
            'advance_approval' => ['keyword' => 'Advance approval', 'label' => 'Advance Approval'],
            'submitted' => ['keyword' => 'Submitted', 'label' => 'Submission'],
            'pending_approval' => ['keyword' => 'Pending for approval', 'label' => 'Pending for Approval'],
            'pending_submission' => ['keyword' => 'Pending Expense Submission', 'label' => 'Pending Expense Submission'],
            'pending' => ['keyword' => 'Pending Your Approval', 'label' => 'Pending Your Approval'],
            'approved' => ['keyword' => 'Approved', 'label' => 'Approved'],
            'rejected' => ['keyword' => 'Rejected', 'label' => 'Rejected'],
            'finance_approved' => ['keyword' => 'Finance Approved', 'label' => 'Finance Approved'],
            'finance_rejected' => ['keyword' => 'Finance Rejected', 'label' => 'Finance Rejected'],
            'reverted' => ['keyword' => 'Reverted', 'label' => 'Reverted'],
            'export_ready' => ['keyword' => 'Export is Ready', 'label' => 'Export Ready'],
        ];
    }

    private function mapSubjectToCategory($subject): ?string
    {
        $categories = $this->getSubjectCategories();
        foreach ($categories as $catKey => $catData) {
            if (stripos($subject, $catData['keyword']) !== false) {
                return $catKey;
            }
        }
        return null;
    }

    public function index()
    {
        $table = $this->getTable();

        if (!Schema::hasTable($table)) {
            return view('admin.mail-logs', ['subjects' => [], 'recipients' => [], 'senders' => [], 'recipientMap' => [], 'employees' => [], 'error' => 'sent_emails table not found']);
        }

        // Get distinct subjects - normalize by trimming whitespace
        $subjects = DB::table($table)
            ->selectRaw('TRIM(subject) as subject')
            ->whereNotNull('subject')
            ->where('subject', '!=', '')
            ->groupBy(DB::raw('TRIM(subject)'))
            ->orderBy('subject')
            ->pluck('subject')
            ->toArray();

        // Get distinct recipient emails
        $recipients = DB::table($table)
            ->select('to')
            ->distinct()
            ->whereNotNull('to')
            ->where('to', '!=', '')
            ->orderBy('to')
            ->pluck('to')
            ->toArray();

        // Build recipient map: email -> "EmpCode - Name (email)"
        $recipientMap = $this->buildRecipientMap($recipients);

        $senders = DB::table($table)
            ->select('from')
            ->distinct()
            ->whereNotNull('from')
            ->where('from', '!=', '')
            ->orderBy('from')
            ->pluck('from')
            ->toArray();

        // Get employee list for the Employee filter dropdown
        $employees = $this->getEmployeeList();

        // Get subject categories for the filter dropdown
        $subjectCategories = $this->getSubjectCategories();

        return view('admin.mail-logs', compact('subjects', 'recipients', 'senders', 'recipientMap', 'employees', 'subjectCategories'));
    }

    /**
     * Get list of employees with code, name, department, designation for dropdown
     */
    private function getEmployeeList(): array
    {
        try {
            $employees = DB::connection('hrims')
                ->table('hrm_employee')
                ->leftJoin('hrm_employee_general', 'hrm_employee.EmployeeID', '=', 'hrm_employee_general.EmployeeID')
                ->leftJoin('core_departments', 'hrm_employee_general.DepartmentId', '=', 'core_departments.id')
                ->leftJoin('core_designation', 'hrm_employee_general.DesigId', '=', 'core_designation.id')
                ->where('hrm_employee.EmpStatus', 'A')
                ->whereNotNull('hrm_employee.EmpCode')
                ->where('hrm_employee.EmpCode', '!=', '0')
                ->select(
                    'hrm_employee.EmployeeID',
                    'hrm_employee.EmpCode',
                    'hrm_employee.EmpCode_New',
                    'hrm_employee.Fname',
                    'hrm_employee.Sname',
                    'hrm_employee.Lname',
                    'hrm_employee_general.EmailId_Vnr as Email',
                    'core_departments.department_name as DepartmentName',
                    'core_designation.designation_name as Designation'
                )
                ->orderBy('hrm_employee.EmpCode')
                ->get();

            $result = [];
            $seen = [];
            foreach ($employees as $emp) {
                if (isset($seen[$emp->EmployeeID])) continue;
                $seen[$emp->EmployeeID] = true;

                $name = trim(($emp->Fname ?? '') . ' ' . ($emp->Sname ?? '') . ' ' . ($emp->Lname ?? ''));
                $dept = $emp->DepartmentName ?? '';
                $code = !empty($emp->EmpCode_New) ? $emp->EmpCode_New : $emp->EmpCode;
                $email = !empty($emp->Email) ? $emp->Email : '';

                $result[] = [
                    'code' => $code,
                    'name' => $name,
                    'email' => $email,
                    'department' => $dept,
                    'designation' => $emp->Designation ?? '',
                    'display' => $code . ' - ' . $name . ($email ? ' (' . $email . ')' : '') . ($dept ? ' [' . $dept . ']' : ''),
                ];
            }
            return $result;
        } catch (\Exception $e) {
            \Log::error('MailLogController: Could not get employee list: ' . $e->getMessage() . ' | ' . $e->getTraceAsString());
            return [];
        }
    }

    /**
     * Build a map of email -> display string with employee code and department
     */
    private function buildRecipientMap(array $recipients): array
    {
        if (empty($recipients)) return [];

        try {
            // Extract clean email addresses
            $cleanEmails = array_map(function($email) {
                if (preg_match('/<(.+?)>/', $email, $matches)) {
                    return strtolower(trim($matches[1]));
                }
                return strtolower(trim($email));
            }, $recipients);

            // Query employees by EmailId_Vnr only
            $employees = DB::connection('hrims')
                ->table('hrm_employee')
                ->leftJoin('hrm_employee_general', 'hrm_employee.EmployeeID', '=', 'hrm_employee_general.EmployeeID')
                ->leftJoin('core_departments', 'hrm_employee_general.DepartmentId', '=', 'core_departments.id')
                ->whereIn(DB::raw('LOWER(TRIM(hrm_employee_general.EmailId_Vnr))'), $cleanEmails)
                ->select(
                    'hrm_employee.EmployeeID',
                    'hrm_employee.EmpCode',
                    'hrm_employee.EmpCode_New',
                    'hrm_employee.Fname',
                    'hrm_employee.Sname',
                    'hrm_employee.Lname',
                    'hrm_employee_general.EmailId_Vnr as Email',
                    'core_departments.department_name as DepartmentName'
                )
                ->get();

            $emailLookup = [];
            foreach ($employees as $emp) {
                if (!empty($emp->Email)) {
                    $key = strtolower(trim($emp->Email));
                    if (!isset($emailLookup[$key]) || (empty($emailLookup[$key]->DepartmentName) && !empty($emp->DepartmentName))) {
                        $emailLookup[$key] = $emp;
                    }
                }
            }

            $map = [];
            foreach ($recipients as $rawEmail) {
                if (preg_match('/<(.+?)>/', $rawEmail, $matches)) {
                    $cleanEmail = strtolower(trim($matches[1]));
                } else {
                    $cleanEmail = strtolower(trim($rawEmail));
                }

                if (isset($emailLookup[$cleanEmail])) {
                    $emp = $emailLookup[$cleanEmail];
                    $code = $emp->EmpCode_New ?? $emp->EmpCode;
                    $name = trim(($emp->Fname ?? '') . ' ' . ($emp->Lname ?? ''));
                    $dept = $emp->DepartmentName ? ' [' . $emp->DepartmentName . ']' : '';
                    $map[$rawEmail] = $code . ' - ' . $name . $dept . ' (' . $rawEmail . ')';
                } else {
                    $map[$rawEmail] = $rawEmail;
                }
            }

            return $map;
        } catch (\Exception $e) {
            \Log::warning('MailLogController: Could not build recipient map: ' . $e->getMessage());
            $map = [];
            foreach ($recipients as $r) {
                $map[$r] = $r;
            }
            return $map;
        }
    }

    public function getData(Request $request)
    {
        $table = $this->getTable();

        if (!Schema::hasTable($table)) {
            return response()->json(['success' => false, 'message' => 'sent_emails table not found', 'data' => [], 'total' => 0]);
        }

        $query = DB::table($table)->orderBy('created_at', 'desc');

        if ($request->filled('from')) {
            $query->where('from', 'like', '%' . $request->input('from') . '%');
        }

        // Employee code filter takes priority over 'to' filter
        if ($request->filled('emp_code')) {
            $empCode = trim($request->input('emp_code'));
            try {
                $searchTerms = [];
                $isEmail = filter_var($empCode, FILTER_VALIDATE_EMAIL) !== false;

                if ($isEmail) {
                    $searchTerms[] = strtolower($empCode);
                } else {
                    $employees = DB::connection('hrims')
                        ->table('hrm_employee')
                        ->leftJoin('hrm_employee_general', 'hrm_employee.EmployeeID', '=', 'hrm_employee_general.EmployeeID')
                        ->where(function ($q) use ($empCode) {
                            $q->where('hrm_employee.EmpCode', $empCode)
                              ->orWhere('hrm_employee.EmpCode_New', $empCode);
                        })
                        ->select('hrm_employee_general.EmailId_Vnr')
                        ->get();

                    foreach ($employees as $emp) {
                        if (!empty($emp->EmailId_Vnr)) {
                            $searchTerms[] = strtolower(trim($emp->EmailId_Vnr));
                        }
                    }
                }

                $searchTerms = array_unique(array_filter($searchTerms));

                if (!empty($searchTerms)) {
                    $query->where(function($q) use ($searchTerms) {
                        foreach ($searchTerms as $email) {
                            $q->orWhere('to', 'like', '%' . $email . '%');
                        }
                    });
                } else {
                    // Fall back to matching the raw emp_code value inside the recipient field.
                    $query->where('to', 'like', '%' . $empCode . '%');
                }
            } catch (\Exception $e) {
                \Log::warning('MailLog emp_code filter error: ' . $e->getMessage());
                $query->where('to', 'like', '%' . $empCode . '%');
            }
        } elseif ($request->filled('to')) {
            // Support multiple 'to' values (comma-separated or array)
            $toValues = $request->input('to');
            if (is_string($toValues)) {
                $toValues = array_filter(array_map('trim', explode(',', $toValues)));
            }
            if (!empty($toValues)) {
                $query->where(function($q) use ($toValues) {
                    foreach ($toValues as $toVal) {
                        $q->orWhere('to', 'like', '%' . $toVal . '%');
                    }
                });
            }
        }

        if ($request->filled('subject_category')) {
            $categoryValues = $request->input('subject_category');
            if (is_string($categoryValues)) {
                $categoryValues = [trim($categoryValues)];
            }
            $categories = $this->getSubjectCategories();
            if (!empty($categoryValues)) {
                $query->where(function($q) use ($categoryValues, $categories) {
                    foreach ($categoryValues as $catKey) {
                        $catKey = trim($catKey);
                        if ($catKey === '' || !isset($categories[$catKey])) {
                            continue;
                        }
                        $keyword = $categories[$catKey]['keyword'];
                        $q->orWhere('subject', 'like', '%' . $keyword . '%');
                    }
                });
            }
        } elseif ($request->filled('subject')) {
            $subjectValues = $request->input('subject');
            if (is_string($subjectValues)) {
                $subjectValues = [trim($subjectValues)];
            }
            if (!empty($subjectValues)) {
                $query->where(function($q) use ($subjectValues) {
                    foreach ($subjectValues as $subj) {
                        $subj = trim($subj);
                        if ($subj === '') {
                            continue;
                        }
                        $q->orWhere('subject', 'like', '%' . $subj . '%');
                    }
                });
            }
        }
        if ($request->filled('date_from')) {
            $query->whereDate('created_at', '>=', $request->input('date_from'));
        }
        if ($request->filled('date_to')) {
            $query->whereDate('created_at', '<=', $request->input('date_to'));
        }

        $perPage = (int) $request->input('per_page', 25);
        $page = (int) $request->input('page', 1);
        $total = $query->count();
        $data = $query->offset(($page - 1) * $perPage)->limit($perPage)->get();

        // Enrich data with employee codes
        $data = $this->enrichWithEmployeeCodes($data);

        return response()->json([
            'success' => true,
            'data' => $data,
            'total' => $total,
            'current_page' => $page,
            'last_page' => (int) ceil($total / $perPage),
            'per_page' => $perPage,
        ]);
    }

    /**
     * Add employee code, name, department, designation info to the email data
     */
    private function enrichWithEmployeeCodes($data)
    {
        if ($data->isEmpty()) return $data;

        try {
            // Collect all unique emails from 'to' field
            $allEmails = [];
            foreach ($data as $row) {
                if (!empty($row->to)) {
                    // Handle comma-separated multiple emails
                    $emails = preg_split('/[,;]/', $row->to);
                    foreach ($emails as $email) {
                        if (preg_match('/<(.+?)>/', $email, $matches)) {
                            $allEmails[] = strtolower(trim($matches[1]));
                        } else {
                            $cleaned = strtolower(trim($email));
                            if (filter_var($cleaned, FILTER_VALIDATE_EMAIL)) {
                                $allEmails[] = $cleaned;
                            }
                        }
                    }
                }
            }
            $allEmails = array_unique(array_filter($allEmails));

            if (empty($allEmails)) return $data;

            $employees = DB::connection('hrims')
                ->table('hrm_employee')
                ->leftJoin('hrm_employee_general', 'hrm_employee.EmployeeID', '=', 'hrm_employee_general.EmployeeID')
                ->leftJoin('core_departments', 'hrm_employee_general.DepartmentId', '=', 'core_departments.id')
                ->leftJoin('core_designation', 'hrm_employee_general.DesigId', '=', 'core_designation.id')
                ->whereIn(DB::raw('LOWER(TRIM(hrm_employee_general.EmailId_Vnr))'), $allEmails)
                ->select(
                    'hrm_employee.EmployeeID',
                    'hrm_employee.EmpCode',
                    'hrm_employee.EmpCode_New',
                    'hrm_employee.Fname',
                    'hrm_employee.Sname',
                    'hrm_employee.Lname',
                    'hrm_employee_general.EmailId_Vnr as Email',
                    'core_departments.department_name as DepartmentName',
                    'core_designation.designation_name as Designation'
                )
                ->get()
                ->unique('EmployeeID');

            $emailLookup = [];
            foreach ($employees as $emp) {
                if (!empty($emp->Email)) {
                    $key = strtolower(trim($emp->Email));
                    if (!isset($emailLookup[$key]) || (empty($emailLookup[$key]->DepartmentName) && !empty($emp->DepartmentName))) {
                        $emailLookup[$key] = $emp;
                    }
                }
            }

            foreach ($data as $row) {
                $row->emp_code = null;
                $row->emp_name = null;
                $row->emp_email = null;
                $row->department = null;
                $row->designation = null;

                if (!empty($row->to)) {
                    $emails = preg_split('/[,;]/', $row->to);
                    foreach ($emails as $email) {
                        if (preg_match('/<(.+?)>/', $email, $matches)) {
                            $cleanEmail = strtolower(trim($matches[1]));
                        } else {
                            $cleanEmail = strtolower(trim($email));
                        }

                        if (isset($emailLookup[$cleanEmail])) {
                            $emp = $emailLookup[$cleanEmail];
                            $row->emp_code = $emp->EmpCode_New ?? $emp->EmpCode;
                            $row->emp_name = trim(($emp->Fname ?? '') . ' ' . ($emp->Sname ?? '') . ' ' . ($emp->Lname ?? ''));
                            $row->emp_email = $cleanEmail;
                            $row->department = $emp->DepartmentName ?? null;
                            $row->designation = $emp->Designation ?? null;
                            break;
                        }
                    }
                }
            }
        } catch (\Exception $e) {
            \Log::warning('MailLog enrichWithEmployeeCodes error: ' . $e->getMessage());
        }

        return $data;
    }

    public function getSubjectsByCategory(Request $request)
    {
        $table = $this->getTable();
        $categoryKey = $request->input('category');

        if (!$categoryKey) {
            return response()->json(['success' => false, 'data' => []]);
        }

        $categories = $this->getSubjectCategories();
        if (!isset($categories[$categoryKey])) {
            return response()->json(['success' => false, 'data' => []]);
        }

        $keyword = $categories[$categoryKey]['keyword'];

        try {
            $subjects = DB::table($table)
                ->selectRaw('TRIM(subject) as subject')
                ->where('subject', 'like', '%' . $keyword . '%')
                ->whereNotNull('subject')
                ->where('subject', '!=', '')
                ->groupBy(DB::raw('TRIM(subject)'))
                ->orderBy('subject')
                ->pluck('subject')
                ->toArray();

            return response()->json(['success' => true, 'data' => $subjects]);
        } catch (\Exception $e) {
            \Log::error('MailLogController: Error fetching subjects by category: ' . $e->getMessage());
            return response()->json(['success' => false, 'data' => []]);
        }
    }

    public function getBody($id)
    {
        $email = DB::table($this->getTable())->where('id', $id)->first();

        if (!$email) {
            return response('<p style="padding:20px;text-align:center;color:#999;">Email not found</p>')->header('Content-Type', 'text/html');
        }

        $body = $email->body ?? '';

        if (empty($body)) {
            return response($this->noBodyHtml($email))->header('Content-Type', 'text/html');
        }

        // Method 1: Body is raw HTML (not compressed)
        if (str_starts_with(trim($body), '<') || str_starts_with(trim($body), '<!')) {
            return response($body)->header('Content-Type', 'text/html');
        }

        // Method 2: Body is gzcompressed binary (package default when compressBody=true)
        $uncompressed = @gzuncompress($body);
        if ($uncompressed !== false) {
            return response($uncompressed)->header('Content-Type', 'text/html');
        }

        // Method 3: Body is base64 encoded then gzcompressed
        $decoded = @base64_decode($body, true);
        if ($decoded !== false) {
            $uncompressed = @gzuncompress($decoded);
            if ($uncompressed !== false) {
                return response($uncompressed)->header('Content-Type', 'text/html');
            }
            // Just base64 without gzip
            if (str_contains($decoded, '<')) {
                return response($decoded)->header('Content-Type', 'text/html');
            }
        }

        // Method 4: Return as-is (might be plain text)
        return response('<pre style="padding:20px;font-family:monospace;white-space:pre-wrap;">' . htmlspecialchars($body) . '</pre>')->header('Content-Type', 'text/html');
    }

    private function noBodyHtml($email): string
    {
        return '<div style="padding:30px;font-family:Arial;text-align:center;color:#999;">' .
            '<h3>Unable to decode email body</h3>' .
            '<p>Subject: ' . htmlspecialchars($email->subject ?? '') . '</p>' .
            '<p>To: ' . htmlspecialchars($email->to ?? '') . '</p>' .
            '<p>Date: ' . ($email->created_at ?? '') . '</p>' .
            '<p style="font-size:11px;color:#ccc;">Body length: ' . strlen($email->body ?? '') . ' chars</p>' .
            '</div>';
    }
}
