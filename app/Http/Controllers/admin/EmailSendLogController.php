<?php

namespace App\Http\Controllers\admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class EmailSendLogController extends Controller
{
    /**
     * Show the Email Send Log page.
     */
    /**
     * VVNR-LOG-01 (Finance mail 19-31 Oct): VVNR mails are written to the VVNR database's own
     * eml_send_log (VvnrEmailNotificationController, reminders). The log screen now reads that
     * table when the user works in VVNR (or picks VVNR in the company filter), so VVNR gets the
     * same mail log as VNR.
     */
    private function isVvnrLog(): bool
    {
        return (int) request('company_id', 0) === 11 || ((int) session('company_id') === 11 && ! request()->filled('company_id'));
    }

    private function logTable()
    {
        return $this->isVvnrLog() ? DB::connection('expense_vvnr')->table('eml_send_log') : DB::table('eml_send_log');
    }

    public function index()
    {
        return view('admin.email-send-log');
    }

    /**
     * AJAX: paginated + filtered log data.
     */
    public function getData(Request $request)
    {
        $draw    = (int) $request->input('draw', 1);
        $start   = (int) $request->input('start', 0);
        $length  = (int) $request->input('length', 25);
        $search  = $request->input('search.value', '');

        $query = $this->logTable();

        // ── Filters ──────────────────────────────────────────────
        if ($request->filled('notification_type')) {
            $query->where('notification_type', $request->notification_type);
        }
        if ($request->filled('mail_category')) {
            $query->where('mail_category', $request->mail_category);
        }
        if ($request->filled('is_sent') && $request->is_sent !== '') {
            $query->where('is_sent', (int) $request->is_sent);
        }
        if ($request->filled('company_id') && ! $this->isVvnrLog()) {
            $query->where('company_id', (int) $request->company_id);
        }
        if ($request->filled('sent_for_month')) {
            $query->where('sent_for_month', $request->sent_for_month);
        }
        if ($request->filled('ref_type')) {
            $query->where('ref_type', $request->ref_type);
        }
        if ($request->filled('date_from')) {
            $query->whereDate('created_at', '>=', $request->date_from);
        }
        if ($request->filled('date_to')) {
            $query->whereDate('created_at', '<=', $request->date_to);
        }
        if ($request->filled('emp_code')) {
            $query->where('recipient_emp_code', 'like', '%' . $request->emp_code . '%');
        }
        if ($request->filled('recipient_email')) {
            $query->where('recipient_email', 'like', '%' . $request->recipient_email . '%');
        }

        // Global search
        if ($search !== '') {
            $query->where(function ($q) use ($search) {
                $q->where('recipient_name',   'like', "%{$search}%")
                  ->orWhere('recipient_email',  'like', "%{$search}%")
                  ->orWhere('recipient_emp_code','like', "%{$search}%")
                  ->orWhere('subject',           'like', "%{$search}%")
                  ->orWhere('notification_type', 'like', "%{$search}%")
                  ->orWhere('ref_id',            'like', "%{$search}%");
            });
        }

        $total    = (clone $query)->count();
        $filtered = $total; // same as total when no extra non-filter query

        $rows = (clone $query)
            ->orderByDesc('created_at')
            ->offset($start)
            ->limit($length)
            ->get();

        $data = $rows->map(function ($row) {
            $statusBadge = $row->is_sent
                ? '<span class="badge bg-success-subtle text-success border border-success-subtle">Sent</span>'
                : ($row->failed_at
                    ? '<span class="badge bg-danger-subtle text-danger border border-danger-subtle">Failed</span>'
                    : '<span class="badge bg-warning-subtle text-warning border border-warning-subtle">Not Sent</span>');

            $categoryBadge = match ($row->mail_category) {
                'expense'  => '<span class="badge bg-primary-subtle text-primary">Expense</span>',
                'advance'  => '<span class="badge bg-info-subtle text-info">Advance</span>',
                'payment'  => '<span class="badge bg-purple-subtle text-purple">Payment</span>',
                default    => '<span class="badge bg-secondary-subtle text-secondary">General</span>',
            };

            $refCell = $row->ref_type
                ? '<span class="text-muted small">' . e($row->ref_type) . '</span><br><strong>#' . e($row->ref_id) . '</strong>'
                : '-';

            $ccCell = $row->cc_emails
                ? '<small class="text-muted">' . e($row->cc_emails) . '</small>'
                : '-';

            return [
                'id'                => $row->id,
                'created_at'        => \Carbon\Carbon::parse($row->created_at)->format('d-m-Y H:i:s'),
                'notification_type' => '<span class="badge bg-dark-subtle text-dark">' . e($row->notification_type) . '</span>',
                'mail_category'     => $categoryBadge,
                'subject'           => '<span title="' . e($row->subject) . '">' . e(\Str::limit($row->subject, 50)) . '</span>',
                'recipient'         => '<strong>' . e($row->recipient_name ?? '—') . '</strong><br>'
                                       . '<small class="text-muted">' . e($row->recipient_emp_code ?? '') . '</small><br>'
                                       . '<small>' . e($row->recipient_email) . '</small>',
                'cc'                => $ccCell,
                'stage'             => $row->stage ? '<span class="badge bg-light text-dark border">' . e($row->stage) . '</span>' : '-',
                'sent_for_month'    => $row->sent_for_month ?? '-',
                'company'           => ($row->company_id == 11 || $this->isVvnrLog()) ? '<span class="badge bg-warning-subtle text-warning">VVNR</span>' : '<span class="badge bg-primary-subtle text-primary">VNR</span>',
                'ref'               => $refCell,
                'status'            => $statusBadge,
                'sender'            => $row->sender_name
                                       ? e($row->sender_name) . '<br><small class="text-muted">' . e($row->sender_emp_code ?? '') . '</small>'
                                       : '<span class="text-muted">System</span>',
                'actions'           => '<button class="btn btn-xs btn-outline-primary btn-view-detail" data-id="' . $row->id . '" title="View Detail"><i class="ri-eye-line"></i></button>',
                'error_message'     => $row->error_message,
                'body_html'         => $row->body_html,
                'template_name'     => $row->template_name ?? '-',
                'failed_at'         => $row->failed_at,
                'sent_at'           => $row->sent_at,
            ];
        });

        return response()->json([
            'draw'            => $draw,
            'recordsTotal'    => $total,
            'recordsFiltered' => $filtered,
            'data'            => $data,
        ]);
    }

    /**
     * AJAX: summary stats for the dashboard cards.
     */
    public function getStats(Request $request)
    {
        $dateFrom = $request->input('date_from');
        $dateTo   = $request->input('date_to');

        $base = $this->logTable();
        if ($dateFrom) $base->whereDate('created_at', '>=', $dateFrom);
        if ($dateTo)   $base->whereDate('created_at', '<=', $dateTo);

        $total   = (clone $base)->count();
        $sent    = (clone $base)->where('is_sent', 1)->count();
        $failed  = (clone $base)->whereNotNull('failed_at')->count();
        $notSent = (clone $base)->where('is_sent', 0)->whereNull('failed_at')->count();

        $byType = (clone $base)
            ->select('notification_type', DB::raw('COUNT(*) as total'), DB::raw('SUM(is_sent) as sent'))
            ->groupBy('notification_type')
            ->orderByDesc('total')
            ->get();

        $byCategory = (clone $base)
            ->select('mail_category', DB::raw('COUNT(*) as total'))
            ->groupBy('mail_category')
            ->orderByDesc('total')
            ->get();

        return response()->json([
            'total'       => $total,
            'sent'        => $sent,
            'failed'      => $failed,
            'not_sent'    => $notSent,
            'by_type'     => $byType,
            'by_category' => $byCategory,
        ]);
    }

    /**
     * AJAX: single row detail (body_html + full info).
     */
    public function getDetail(Request $request)
    {
        $id  = $request->input('id');
        $row = $this->logTable()->where('id', $id)->first();

        if (! $row) {
            return response()->json(['success' => false, 'message' => 'Record not found'], 404);
        }

        return response()->json(['success' => true, 'data' => $row]);
    }

    /**
     * Return distinct notification_types for filter dropdown.
     */
    public function getFilterOptions()
    {
        $types = $this->logTable()
            ->select('notification_type')
            ->distinct()
            ->orderBy('notification_type')
            ->pluck('notification_type');

        $months = $this->logTable()
            ->select('sent_for_month')
            ->whereNotNull('sent_for_month')
            ->distinct()
            ->orderByDesc('sent_for_month')
            ->pluck('sent_for_month');

        return response()->json(['types' => $types, 'months' => $months]);
    }
}
