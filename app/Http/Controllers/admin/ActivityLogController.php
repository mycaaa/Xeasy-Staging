<?php

namespace App\Http\Controllers\admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ActivityLogController extends Controller
{
    // ── Index ─────────────────────────────────────────────────────────────────

    public function index()
    {
        $users = DB::table('users')
            ->select('id', 'name', 'employee_id')
            ->orderBy('name')
            ->get();

        // PERF-02: the filter lists change rarely; read them once every 10 minutes, not on every page load
        $modules = \Illuminate\Support\Facades\Cache::remember('activity_log_modules', 600, fn () => DB::table('activity_log')
            ->select('log_name')->distinct()->orderBy('log_name')
            ->pluck('log_name')->filter()->values());

        $events = \Illuminate\Support\Facades\Cache::remember('activity_log_events', 600, fn () => DB::table('activity_log')
            ->select('event')->distinct()->orderBy('event')
            ->pluck('event')->filter()->values());

        return view('admin.activity-logs', compact('users', 'modules', 'events'));
    }

    // ── Main data (AJAX) ──────────────────────────────────────────────────────

    public function getData(Request $request)
    {
        $baseQuery = $this->scopedQuery($request);

        // Stats — aggregate over the entire filtered set
        $statsRow = (clone $baseQuery)->selectRaw("
            COUNT(*) AS total_actions,
            SUM(CASE WHEN al.event = 'viewed'  THEN 1 ELSE 0 END) AS total_viewed,
            SUM(CASE WHEN al.event = 'created' THEN 1 ELSE 0 END) AS total_created,
            SUM(CASE WHEN al.event = 'updated' THEN 1 ELSE 0 END) AS total_updated,
            SUM(CASE WHEN al.event = 'deleted' THEN 1 ELSE 0 END) AS total_deleted,
            COUNT(DISTINCT al.causer_id) AS unique_users
        ")->first();

        $stats = [
            'total_actions' => (int) ($statsRow->total_actions ?? 0),
            'total_viewed'  => (int) ($statsRow->total_viewed  ?? 0),
            'total_created' => (int) ($statsRow->total_created ?? 0),
            'total_updated' => (int) ($statsRow->total_updated ?? 0),
            'total_deleted' => (int) ($statsRow->total_deleted ?? 0),
            'unique_users'  => (int) ($statsRow->unique_users  ?? 0),
        ];
        $total = $stats['total_actions']; // same filtered set; saves a second full scan

        $perPage = max(1, (int) $request->input('per_page', 100));
        $page    = max(1, (int) $request->input('page', 1));
        $offset  = ($page - 1) * $perPage;

        $rows = (clone $baseQuery)
            ->orderByDesc('al.id') // primary key; same order as created_at for this append-only log
            ->offset($offset)
            ->limit($perPage)
            ->get();

        $logs = $this->enrichRows($rows);

        return response()->json([
            'success' => true,
            'data'    => [
                'logs'  => $logs,
                'stats' => $stats,
                'pagination' => [
                    'total'        => $total,
                    'per_page'     => $perPage,
                    'current_page' => $page,
                    'last_page'    => (int) ceil($total / $perPage),
                ],
            ],
        ]);
    }

    // ── Export (streamed CSV) ─────────────────────────────────────────────────

    public function export(Request $request): StreamedResponse
    {
        // UAT-03: the export loaded the whole activity_log (56% of the database) into memory and
        // ran out of 1 GB. Without a date range it now covers the last 31 days, it streams in
        // chunks of 2,000 rows, and it stops at 200,000 rows (noted in the file).
        $baseQuery = $this->scopedQuery($request);
        $self      = $this;

        $filename  = 'activity_log_' . now()->format('Ymd_His') . '.csv';

        return response()->streamDownload(function () use ($baseQuery, $self) {
            $logsIter = (function () use ($baseQuery, $self) {
                $lastId = null; $sent = 0;
                while ($sent < 200000) {
                    $q = (clone $baseQuery)->orderByDesc('al.id')->limit(2000);
                    if ($lastId !== null) {
                        $q->where('al.id', '<', $lastId);
                    }
                    $rows = $q->get();
                    if ($rows->isEmpty()) {
                        return;
                    }
                    $lastId = $rows->last()->id;
                    foreach ($self->enrichRowsPublic($rows) as $log) {
                        $sent++;
                        yield $log;
                    }
                    if ($rows->count() < 2000) {
                        return;
                    }
                }
                yield ['__truncated' => true];
            })();
            $logs = $logsIter;

            $out = fopen('php://output', 'w');

            // BOM for Excel UTF-8
            fwrite($out, "\xEF\xBB\xBF");

            fputcsv($out, [
                '#', 'Date & Time', 'Performed By', 'Performer Emp ID',
                'Event', 'Module', 'Description',
                'Subject Model', 'Subject ID',
                'Affected Employee', 'Affected Emp Code', 'Affected Emp ID',
                'Changed Fields (count)', 'Field', 'Old Value', 'New Value',
                'IP Address', 'Method', 'URL',
            ]);

            $i = 1;
            foreach ($logs as $log) {
                if (! empty($log['__truncated'])) {
                    fputcsv($out, ['Stopped at 200,000 rows: narrow the date range to export the rest.']);
                    break;
                }
                $diffRows = $log['diff'] ?? [];

                if (empty($diffRows)) {
                    // One CSV row even if no diff
                    fputcsv($out, [
                        $i++,
                        $log['created_at'],
                        $log['user_name'],
                        $log['employee_id'],
                        $log['event'],
                        $log['log_name'],
                        $log['description'],
                        $log['subject_type'] ? class_basename($log['subject_type']) : '',
                        $log['subject_id'],
                        $log['subject_employee_name'],
                        $log['subject_emp_code'],
                        $log['subject_employee_id'],
                        0, '', '', '',
                        $log['properties']['ip_address'] ?? '',
                        $log['properties']['method']     ?? '',
                        $log['properties']['url']        ?? '',
                    ]);
                } else {
                    // One row per changed field so the diff is fully readable
                    foreach ($diffRows as $k => $diff) {
                        fputcsv($out, [
                            $k === 0 ? $i++ : '',          // # only on first diff row
                            $k === 0 ? $log['created_at'] : '',
                            $k === 0 ? $log['user_name']  : '',
                            $k === 0 ? $log['employee_id']: '',
                            $k === 0 ? $log['event']      : '',
                            $k === 0 ? $log['log_name']   : '',
                            $k === 0 ? $log['description']: '',
                            $k === 0 ? ($log['subject_type'] ? class_basename($log['subject_type']) : '') : '',
                            $k === 0 ? $log['subject_id'] : '',
                            $k === 0 ? $log['subject_employee_name'] : '',
                            $k === 0 ? $log['subject_emp_code']      : '',
                            $k === 0 ? $log['subject_employee_id']   : '',
                            $k === 0 ? count($diffRows)   : '',
                            $diff['field'],
                            $diff['old'] ?? '',
                            $diff['new'] ?? '',
                            $k === 0 ? ($log['properties']['ip_address'] ?? '') : '',
                            $k === 0 ? ($log['properties']['method']     ?? '') : '',
                            $k === 0 ? ($log['properties']['url']        ?? '') : '',
                        ]);
                    }
                }
            }

            fputcsv($out, []); fputcsv($out, \App\Support\ReportStamp::csvRow()); // STAMP-01
            fclose($out);
        }, $filename, [
            'Content-Type'        => 'text/csv; charset=UTF-8',
            'Content-Disposition' => "attachment; filename=\"{$filename}\"",
        ]);
    }

    // ── Module summary ────────────────────────────────────────────────────────

    public function moduleSummary(Request $request)
    {
        $query = $this->scopedQuery($request)->selectRaw("
            al.log_name,
            COUNT(*) AS total,
            SUM(CASE WHEN al.event='viewed'  THEN 1 ELSE 0 END) AS viewed,
            SUM(CASE WHEN al.event='created' THEN 1 ELSE 0 END) AS created,
            SUM(CASE WHEN al.event='updated' THEN 1 ELSE 0 END) AS updated,
            SUM(CASE WHEN al.event='deleted' THEN 1 ELSE 0 END) AS deleted
        ")->groupBy('al.log_name')->orderByDesc('total');

        return response()->json(['success' => true, 'data' => $query->get()]);
    }

    // ── User summary ──────────────────────────────────────────────────────────

    public function userSummary(Request $request)
    {
        $query = $this->scopedQuery($request)->selectRaw("
            al.causer_id,
            users.name        AS user_name,
            users.employee_id AS employee_id,
            COUNT(*) AS total,
            SUM(CASE WHEN al.event='created' THEN 1 ELSE 0 END) AS created,
            SUM(CASE WHEN al.event='updated' THEN 1 ELSE 0 END) AS updated,
            SUM(CASE WHEN al.event='deleted' THEN 1 ELSE 0 END) AS deleted,
            MAX(al.created_at) AS last_activity
        ")->whereNotNull('al.causer_id')
          ->groupBy('al.causer_id', 'users.name', 'users.employee_id')
          ->orderByDesc('total');

        return response()->json(['success' => true, 'data' => $query->get()]);
    }

    // ── Private helpers ───────────────────────────────────────────────────────

    /**
     * Base query with joins and filters applied.
     */
    private function buildQuery(Request $request)
    {
        $query = DB::table('activity_log as al')
            ->leftJoin('users', function ($j) {
                $j->on('al.causer_id', '=', 'users.id')
                  ->where('al.causer_type', '=', 'App\\Models\\User');
            })
            ->select([
                'al.id', 'al.log_name', 'al.description',
                'al.subject_type', 'al.subject_id',
                'al.event', 'al.causer_id', 'al.properties', 'al.created_at',
                'users.name as user_name', 'users.employee_id',
            ]);

        $this->applyFilters($query, $request);

        return $query;
    }

    /**
     * Derive a human-readable event label from HTTP method when event is null.
     */
    private function deriveEvent(?string $event, array $props): string
    {
        if ($event !== null) {
            return $event;
        }
        $method = strtoupper($props['method'] ?? 'GET');
        if ($method === 'GET') {
            return 'viewed';
        }
        if (in_array($method, ['DELETE'])) {
            return 'deleted';
        }
        // POST, PUT, PATCH
        return 'updated';
    }

    /**
     * Enrich a collection of raw DB rows:
     *   - batch-load employee names from HRIMS
     *   - build diff arrays
     */
    /** @internal used by the chunked export */
    public function enrichRowsPublic($rows): array
    {
        return $this->enrichRows($rows);
    }

    /** UAT-03: the activity-log page asks for its filter lists here; the route existed but the method did not. */
    public function getFilters()
    {
        $logNames = DB::table('activity_log')->select('log_name')->distinct()->orderBy('log_name')->limit(500)->pluck('log_name')->filter()->values();
        $users = DB::table('users')->select('id', 'name')->orderBy('name')->get();

        return response()->json(['log_names' => $logNames, 'users' => $users]);
    }

    /**
     * UAT-03: data, summaries and export share one scope. With no date filter they cover the last
     * 31 days (the page, summaries and export used to aggregate the whole table: 30-120 s).
     * The date filter is DATE(created_at) and cannot use an index; activity_log is append-only, so
     * the first id on the start date is found by a binary search on the primary key and used as a floor.
     */
    private function scopedQuery(Request $request)
    {
        if (! $request->filled('date_from') && ! $request->filled('date_to')) {
            $request->merge(['date_from' => now()->subDays(31)->toDateString()]);
        }
        $query = $this->buildQuery($request);
        $minId = $this->firstIdOnOrAfter($request->input('date_from'));
        if ($minId !== null) {
            $query->where('al.id', '>=', $minId);
        }

        return $query;
    }

    /** First activity_log id whose created_at is on or after $date (binary search on the PK). */
    private function firstIdOnOrAfter($date): ?int
    {
        if (! $date) {
            return null;
        }
        $conn = DB::connection(); // same connection buildQuery() uses
        $lo = (int) $conn->table('activity_log')->min('id');
        $hi = (int) $conn->table('activity_log')->max('id');
        if ($hi === 0) {
            return null;
        }
        $start = \Carbon\Carbon::parse($date)->startOfDay()->toDateTimeString();
        while ($lo < $hi) {
            $mid = intdiv($lo + $hi, 2);
            $row = $conn->table('activity_log')->where('id', '>=', $mid)->orderBy('id')->first(['id', 'created_at']);
            if (! $row) {
                $hi = $mid;
            } elseif ($row->created_at >= $start) {
                $hi = min($mid, (int) $row->id);
            } else {
                $lo = (int) $row->id + 1;
            }
        }

        return $lo;
    }

    private function enrichRows($rows): array
    {
        // ── 1. Decode properties and collect employee IDs needing lookup ──────
        $decoded = $rows->map(function ($row) {
            $props   = json_decode($row->properties ?? '{}', true) ?? [];
            $attrs   = $props['attributes'] ?? [];
            $empId   = $attrs['EmployeeID'] ?? $attrs['employee_id'] ?? $attrs['CrBy']
                       ?? $props['emp_id']   // custom expense-update format
                       ?? ($props['main_claim']['CrBy'] ?? null)
                       ?? null;
            return [$row, $props, $attrs, $props['old'] ?? [], $empId];
        });

        $subjectEmpIds = $decoded
            ->pluck(4)           // index 4 = $empId
            ->filter()
            ->unique()
            ->values()
            ->toArray();

        // ── 2. Batch-load all needed employees in ONE query ───────────────────
        $empMap = [];
        if (!empty($subjectEmpIds)) {
            try {
                DB::connection('hrims')
                    ->table('hrm_employee')
                    ->whereIn('EmployeeID', $subjectEmpIds)
                    ->select('EmployeeID', 'EmpCode_New',
                        DB::raw("CONCAT_WS(' ', Fname, Sname, Lname) AS full_name"))
                    ->get()
                    ->each(function ($e) use (&$empMap) {
                        $empMap[$e->EmployeeID] = $e;
                    });
            } catch (\Exception $e) {
                // HRIMS unavailable — skip enrichment silently
            }
        }

        // ── 3. Build final array ──────────────────────────────────────────────
        return $decoded->map(function ($tuple) use ($empMap) {
            [$row, $props, $attrs, $oldVals, $empId] = $tuple;

            $emp          = $empId ? ($empMap[$empId] ?? null) : null;
            $diff         = $this->buildDiff($props, $attrs, $oldVals, $row->event, $row->log_name);
            $derivedEvent = $this->deriveEvent($row->event, $props);

            return [
                'id'                    => $row->id,
                'log_name'              => $row->log_name,
                'description'           => $row->description,
                'subject_type'          => $row->subject_type,
                'subject_id'            => $row->subject_id,
                'event'                 => $row->event,
                'derived_event'         => $derivedEvent,
                'causer_id'             => $row->causer_id,
                'user_name'             => $row->user_name   ?? 'System',
                'employee_id'           => $row->employee_id ?? '',
                'properties'            => $props,
                'diff'                  => $diff,
                'before_data'           => $props['before'] ?? null,
                'after_data'            => $props['after'] ?? null,
                'changed_fields'        => $props['changed_fields'] ?? [],
                'deleted_record'        => $props['deleted_record'] ?? null,
                'subject_employee_id'   => $empId,
                'subject_employee_name' => $emp?->full_name ?? null,
                'subject_emp_code'      => $emp?->EmpCode_New ?? null,
                'created_at'            => $row->created_at,
            ];
        })->toArray();
    }

    /**
     * Build a diff array from Spatie attributes/old,
     * OR from full before/after snapshots (GlobalModelObserver enhanced format),
     * OR a human-readable summary from custom log formats (expense update, etc.)
     */
    private function buildDiff(array $props, array $attrs, array $oldVals, ?string $event, ?string $logName): array
    {
        // ── Custom format: expense update (main_claim + g1_expense_data snapshot) ──
        if (isset($props['main_claim']) || isset($props['g1_expense_data'])) {
            return $this->buildExpenseDiff($props);
        }

        // ── Enhanced format: full before/after snapshots ────────────────────
        // For updates: compare before vs after to show all changed fields
        if ($event === 'updated' && isset($props['before']) && isset($props['after'])) {
            $diff = [];
            $before = $props['before'];
            $after  = $props['after'];
            // Show only fields that actually changed
            foreach ($after as $field => $newVal) {
                if ($field === '_truncated') continue;
                $oldVal = $before[$field] ?? null;
                if ((string) $oldVal !== (string) $newVal) {
                    $diff[] = ['field' => $field, 'old' => $oldVal, 'new' => $newVal];
                }
            }
            return $diff;
        }

        // For deletes with full before snapshot: show all deleted fields
        if ($event === 'deleted' && isset($props['before'])) {
            $diff = [];
            foreach ($props['before'] as $field => $val) {
                if ($field === '_truncated' || $field === 'deleted_record') continue;
                if ($val !== null && $val !== '' && \App\Support\ZeroDate::has($val)) {
                    $diff[] = ['field' => $field, 'old' => $val, 'new' => null];
                }
            }
            return $diff;
        }

        // ── Standard Spatie diff (attributes vs old) ────────────────────────
        $diff = [];

        if (!empty($oldVals) && !empty($attrs)) {
            foreach ($attrs as $field => $newVal) {
                $oldVal = $oldVals[$field] ?? null;
                if ((string) $oldVal !== (string) $newVal) {
                    $diff[] = ['field' => $field, 'old' => $oldVal, 'new' => $newVal];
                }
            }
        } elseif ($event === 'created' && !empty($attrs)) {
            foreach ($attrs as $field => $newVal) {
                $diff[] = ['field' => $field, 'old' => null, 'new' => $newVal];
            }
        } elseif ($event === 'deleted' && !empty($oldVals)) {
            foreach ($oldVals as $field => $oldVal) {
                $diff[] = ['field' => $field, 'old' => $oldVal, 'new' => null];
            }
        }

        return $diff;
    }

    /**
     * Build human-readable rows from the custom expense-update log format.
     *
     * The properties contain:
     *   cg_id, emp_id,
     *   main_claim  → full snapshot of y{n}_expenseclaims row
     *   g1_expense_data → full snapshot of y{n}_g1_expensefilldata row
     *
     * We present the most meaningful fields only.
     * IDs (Vendor_Id, Journey_From_Id, etc.) are resolved to human-readable names
     * via batched DB lookups.
     */
    private function buildExpenseDiff(array $props): array
    {
        // Field labels — map DB column names to human-readable labels
        static $mainLabels = [
            'ExpId'        => 'Expense ID',
            'ClaimId'      => 'Claim Type',
            'BillDate'     => 'Bill Date',
            'ClaimMonth'   => 'Claim Month',
            'ClaimStatus'  => 'Claim Status',
            'ClaimAtStep'  => 'Claim Step',
            'Amount'       => 'Amount (₹)',
            'FilledTAmt'   => 'Filled Amount (₹)',
            'VerifyTAmt'   => 'Verified Amount (₹)',
            'ApprTAmt'     => 'Approved Amount (₹)',
            'FinancedTAmt' => 'Finance Amount (₹)',
            'FilledBy'     => 'Filled By',
            'VerifyBy'     => 'Verified By',
            'ApprBy'       => 'Approved By',
            'FinancedBy'   => 'Financed By',
            'RtnBy'        => 'Returned By',
            'CrBy'         => 'Created By',
            'Remark'       => 'Remark',
            'Img_1'        => 'Image 1',
            'Img_2'        => 'Image 2',
            'Img_3'        => 'Image 3',
        ];

        static $g1Labels = [
            'BillDate'       => 'Bill Date',
            'BillNo'         => 'Bill No.',
            'Agent'          => 'Agent / Vendor',
            'Vendor_Id'      => 'Vendor',
            'PNRNo'          => 'PNR No.',
            'TrainBusName'   => 'Train / Bus Name',
            'Class'          => 'Travel Class',
            'BookingDate'    => 'Booking Date',
            'JourneyStartDt' => 'Journey Start',
            'JourneyEndDt'   => 'Journey End',
            'JourneyFrom'    => 'From',
            'Journey_From_Id'=> 'From City',
            'JourneyUpto'    => 'To',
            'Journey_Upto_Id'=> 'To City',
            'BookingStatus'  => 'Booking Status',
            'TravelInsurance'=> 'Travel Insurance (₹)',
            'IsTravelInsurance' => 'Insurance Included?',
            'StayDuration'   => 'Stay Duration (days)',
            'HotelName'      => 'Hotel Name',
            'BillingName'    => 'Billing Name',
            'Mode'           => 'Travel Mode',
            'WType'          => 'Vehicle Type',
            'Plan'           => 'Plan',
            'NoOfPAX'        => 'No. of PAX',
            'GST'            => 'GST',
            'CityName'       => 'City',
            'CityCategory'   => 'City Category',
            'RechargeAmt'    => 'Recharge Amount',
            'PaymenDetails'  => 'Payment Details',
            'DocketNumber'   => 'Docket Number',
            'DocketBookedDt' => 'Docket Booking Date',
            'WeightCharged'  => 'Weight Charged',
            'DAAmount'       => 'DA Amount',
            'CalAmount'      => 'Calculated Amount',
            'FixedAmount'    => 'Fixed Amount',
        ];

        // ── ClaimStatus readable map ─────────────────────────────────────────
        static $claimStatusMap = [
            'Draft'      => 'Draft',
            'Submitted'  => 'Submitted',
            'Verified'   => 'Verified',
            'Approved'   => 'Approved',
            'Finance'    => 'Finance Cleared',
            'Payment'    => 'Paid',
            'Deactivate' => 'Deactivated',
        ];

        // ── WType readable map ───────────────────────────────────────────────
        static $wtypeMap = [
            '0' => 'N/A',
            '2' => 'Two Wheeler',
            '4' => 'Four Wheeler',
            0   => 'N/A',
            2   => 'Two Wheeler',
            4   => 'Four Wheeler',
        ];

        $main = $props['main_claim']      ?? [];
        $g1   = $props['g1_expense_data'] ?? [];

        // ── Collect IDs for batch lookups ─────────────────────────────────────
        $userIds    = [];
        $cityIds    = [];
        $vendorIds  = [];
        $claimTypes = [];

        // User IDs from main claim
        foreach (['FilledBy', 'VerifyBy', 'ApprBy', 'FinancedBy', 'RtnBy', 'CrBy'] as $f) {
            $v = $main[$f] ?? null;
            if ($v && is_numeric($v)) {
                $userIds[] = (int) $v;
            }
        }

        // City IDs from g1
        foreach (['Journey_From_Id', 'Journey_Upto_Id'] as $f) {
            $v = $g1[$f] ?? null;
            if ($v && is_numeric($v)) {
                $cityIds[] = (int) $v;
            }
        }

        // Vendor IDs from g1
        $vendorId = $g1['Vendor_Id'] ?? null;
        if ($vendorId && is_numeric($vendorId)) {
            $vendorIds[] = (int) $vendorId;
        }

        // Claim type ID from main
        $claimId = $main['ClaimId'] ?? null;
        if ($claimId && is_numeric($claimId)) {
            $claimTypes[] = (int) $claimId;
        }

        // ── Batch fetch lookups ───────────────────────────────────────────────
        $userMap   = [];
        $cityMap   = [];
        $vendorMap = [];
        $claimMap  = [];

        if (!empty($userIds)) {
            try {
                DB::table('users')
                    ->whereIn('id', array_unique($userIds))
                    ->select('id', 'name', 'employee_id')
                    ->get()
                    ->each(function ($u) use (&$userMap) {
                        $label = $u->name;
                        if ($u->employee_id) {
                            $label = $u->employee_id . ' — ' . $u->name;
                        }
                        $userMap[$u->id] = $label;
                    });
            } catch (\Exception $e) { /* silently skip */ }
        }

        if (!empty($cityIds)) {
            try {
                DB::table('city_category')
                    ->whereIn('ccid', array_unique($cityIds))
                    ->select('ccid', 'city_name')
                    ->get()
                    ->each(function ($c) use (&$cityMap) {
                        $cityMap[$c->ccid] = $c->city_name;
                    });
            } catch (\Exception $e) { /* silently skip */ }
        }

        if (!empty($vendorIds)) {
            try {
                // Detect the actual column name for vendor name
                $vendorCols = DB::getSchemaBuilder()->getColumnListing('vendor_list');
                $nameCol    = in_array('VendorName', $vendorCols)
                    ? 'VendorName'
                    : (in_array('vendor_name', $vendorCols) ? 'vendor_name' : null);

                if ($nameCol) {
                    DB::table('vendor_list')
                        ->whereIn('id', array_unique($vendorIds))
                        ->select('id', $nameCol . ' as vendor_name')
                        ->get()
                        ->each(function ($v) use (&$vendorMap) {
                            $vendorMap[$v->id] = $v->vendor_name;
                        });
                }
            } catch (\Exception $e) { /* silently skip */ }
        }

        if (!empty($claimTypes)) {
            try {
                DB::table('claimtype')
                    ->whereIn('ClaimId', array_unique($claimTypes))
                    ->select('ClaimId', 'ClaimType')
                    ->get()
                    ->each(function ($ct) use (&$claimMap) {
                        $claimMap[$ct->ClaimId] = $ct->ClaimType;
                    });
            } catch (\Exception $e) { /* silently skip */ }
        }

        // ── Helper: resolve value for a given field ───────────────────────────
        $resolve = function (string $col, $rawVal) use (
            $userMap, $cityMap, $vendorMap, $claimMap,
            $claimStatusMap, $wtypeMap
        ) {
            if (is_null($rawVal) || $rawVal === '' || \App\Support\ZeroDate::none($rawVal)) {
                return null;
            }

            switch ($col) {
                case 'ClaimStatus':
                    return $claimStatusMap[$rawVal] ?? $rawVal;

                case 'ClaimMonth':
                    $n = (int) $rawVal;
                    if ($n >= 1 && $n <= 12) {
                        return \DateTime::createFromFormat('!m', (string) $n)->format('F');
                    }
                    return $rawVal;

                case 'WType':
                    return $wtypeMap[$rawVal] ?? $rawVal;

                case 'ClaimId':
                    return $claimMap[$rawVal] ?? ('Claim #' . $rawVal);

                case 'Vendor_Id':
                    return $vendorMap[$rawVal] ?? ('Vendor #' . $rawVal);

                case 'Journey_From_Id':
                case 'Journey_Upto_Id':
                    return $cityMap[$rawVal] ?? ('City #' . $rawVal);

                case 'FilledBy':
                case 'VerifyBy':
                case 'ApprBy':
                case 'FinancedBy':
                case 'RtnBy':
                case 'CrBy':
                    if (is_numeric($rawVal)) {
                        return $userMap[(int)$rawVal] ?? ('User #' . $rawVal);
                    }
                    return $rawVal;

                default:
                    return $rawVal;
            }
        };

        $diff = [];

        // Context fields shown first
        $empIdVal = $props['emp_id'] ?? null;
        if (!is_null($empIdVal) && $empIdVal !== '') {
            $diff[] = ['field' => 'Employee ID (Affected)', 'old' => null, 'new' => $empIdVal, 'type' => 'info'];
        }

        // Main claim snapshot
        foreach ($mainLabels as $col => $label) {
            $val = $main[$col] ?? null;
            if (is_null($val) || $val === '' || \App\Support\ZeroDate::none($val) || $val === 0 || $val === '0') {
                continue;
            }
            $resolved = $resolve($col, $val);
            if (!is_null($resolved) && $resolved !== '') {
                $diff[] = ['field' => $label, 'old' => null, 'new' => $resolved, 'type' => 'snapshot'];
            }
        }

        // G1 / fill data snapshot
        if (!empty($g1)) {
            foreach ($g1Labels as $col => $label) {
                $val = $g1[$col] ?? null;
                if (is_null($val) || $val === '' || \App\Support\ZeroDate::none($val) || $val === '0') {
                    continue;
                }
                $resolved = $resolve($col, $val);
                if (!is_null($resolved) && $resolved !== '') {
                    $diff[] = ['field' => $label, 'old' => null, 'new' => $resolved, 'type' => 'snapshot'];
                }
            }
        }

        return $diff;
    }

    /**
     * Apply request filters to a query builder instance.
     */
    private function applyFilters(&$query, Request $request): void
    {
        if ($request->filled('date_from')) {
            $query->whereDate('al.created_at', '>=', $request->input('date_from'));
        }
        if ($request->filled('date_to')) {
            $query->whereDate('al.created_at', '<=', $request->input('date_to'));
        }
        $userId = $request->input('user_id') ?: $request->input('causer_id');
        if ($userId) {
            $query->where('al.causer_id', $userId);
        }
        $module = $request->input('module') ?: $request->input('log_name');
        if ($module) {
            $query->where('al.log_name', $module);
        }
        if ($request->filled('event')) {
            $query->where('al.event', $request->input('event'));
        }
        if ($request->filled('subject_type')) {
            $query->where('al.subject_type', 'LIKE', '%' . $request->input('subject_type') . '%');
        }
        if ($request->filled('search')) {
            $s = $request->input('search');
            $query->where(function ($q) use ($s) {
                $q->where('al.description', 'LIKE', "%{$s}%")
                  ->orWhere('al.properties',   'LIKE', "%{$s}%")
                  ->orWhere('users.name',       'LIKE', "%{$s}%");
            });
        }
    }
}
