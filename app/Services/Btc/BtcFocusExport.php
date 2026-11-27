<?php

namespace App\Services\Btc;

use App\Models\BtcCase;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * BTC-FOCUS-01 (20 Nov 2026, tracker tab "Data Punching Form & Finance approval screens", items 7 and 8).
 *
 *  jv   - Focus JV import for the vendor bill (one row per employee / pax, amount split), DocNo JV/BTC/<FY>/0001
 *  ejv  - Focus Employee JV import (expense booking to the ledger), DocNo EJV/BTC/<FY>/0001, made with the vendor JV
 *         at Focus Export on the Punch Screen; none for a guest's share (BTC-JV-34, 27 Nov 2026)
 *  bank - Focus Bank Payment (non cheque) import of the payments Treasury recorded, DocNo BP/BTC/<FY>/0001
 *
 * Employee values come from HRIMS by employee code (never by name); ledger / sub-ledger codes from config
 * btc.focus_export (the codes written in the tracker). A case that fails a check is listed with the reason and gets
 * no document number; nothing is guessed.
 */
class BtcFocusExport
{
    // BTC-FOCUS-03 (20 Nov 2026): columns exactly as the samples Finance shared ("Jv import template Dt.09.10.26",
    // "employee jv import Dt.09.10.26", Treasury "Advance Sheet 22-10-2026" > Payment Export)
    // BTC-FOCUS-05 (25 Nov 2026, testing point 19): the Focus import sheet in this column order
    public const JV_COLS = ['DocNo', 'Date', 'Time', 'Business Entity', 'TDS JVNo', 'Reverse Charge [Yn]', 'Bill No', 'Bill Date', 'Department', 'Cost Center',
        'Business Unit', 'Activity', 'Location', 'State', 'Category', 'Crop', 'Region', 'Function', 'FC-Vertical', 'Sub Department', 'Zone',
        'DrAccount', 'CrAccount', 'Amount', 'Narration', 'Reference', 'TDS Bill Amount', 'TDS', 'TDS Per.'];

    public const EJV_COLS = ['DocNo', 'Date', 'Account (EC)', 'Business Entity', 'Region', 'Business Unit', 'sNarration', 'ActMonths', 'LB', 'Crop',
        'Department', 'Location', 'Activity', 'Category', 'Subledger', 'State', 'Function', 'FC-Vertical', 'Sub Department', 'Zone', 'LEDGER', 'Amount'];

    public const BANK_COLS = ['DocNo', 'Date', 'Time', 'CashBankAC', 'TDS JVNo', 'Narration', 'Cheque No', 'Transactiontype Code', 'Activity', 'Category',
        'Region', 'Crop', 'Farm', 'Business Entity', 'Cost Center', 'Department', 'PMT Category', 'Business Unit', 'Location', 'State', 'Function',
        'FC-Vertical', 'Sub Department', 'Zone', 'Account', 'Amount', 'Reference', 'Remarks', 'TDS Bill Amount', 'TDS', 'BRSUser'];

    /** Date formats per file, as in the samples. */
    public const DATE_FORMATS = ['jv' => ['Date' => 'dd/mm/yy', 'Bill Date' => 'dd/mm/yyyy'], 'ejv' => ['Date' => 'dd/mm/yyyy'], 'bank' => ['Date' => 'dd-mm-yyyy']];

    private array $empCache = [];

    public static function ready(): bool
    {
        return Schema::hasColumn('btc_cases', 'focus_jv_doc') && Schema::hasTable('btc_doc_series');
    }

    /** Financial year label "2026-27" for a date (April to March). */
    public static function fy($date): string
    {
        $d = Carbon::parse($date ?: now());
        $y = $d->month >= 4 ? $d->year : $d->year - 1;

        return $y.'-'.substr((string) ($y + 1), -2);
    }

    /** Next number of a series, e.g. JV/BTC/2026-27/0007 (locked, so two exports never share a number). */
    public static function nextDoc(string $pattern, $date, int $companyId): string
    {
        $series = str_replace('{fy}', self::fy($date), $pattern);

        return DB::transaction(function () use ($series, $companyId) {
            $row = DB::table('btc_doc_series')->where('company_id', $companyId)->where('series', $series)->lockForUpdate()->first();
            if (! $row) {
                DB::table('btc_doc_series')->insert(['company_id' => $companyId, 'series' => $series, 'last_no' => 0, 'created_at' => now(), 'updated_at' => now()]);
                $row = DB::table('btc_doc_series')->where('company_id', $companyId)->where('series', $series)->lockForUpdate()->first();
            }
            $n = (int) $row->last_no + 1;
            DB::table('btc_doc_series')->where('id', $row->id)->update(['last_no' => $n, 'updated_at' => now()]);

            return $series.str_pad((string) $n, 4, '0', STR_PAD_LEFT);
        });
    }

    /** "26-27" for a date. */
    public static function fyShort($date): string
    {
        [$a, $b] = explode('-', self::fy($date));

        return substr($a, -2).'-'.$b;
    }

    /**
     * BTC-FOCUS-05: JV/BTC/26-27/2511/0026 - financial year, day and month of the import, then a number that runs through
     * the whole financial year (the day part does not restart it).
     */
    public static function nextJvDoc($date, int $companyId): string
    {
        $pattern = (string) config('btc.focus_export.vendor_doc', 'JV/BTC/{fys}/{ddmm}/');
        $series = 'JVBTC/'.self::fy($date).'/';   // one counter per financial year
        $n = substr(self::nextDoc($series, $date, $companyId), -4);

        return strtr($pattern, ['{fys}' => self::fyShort($date), '{fy}' => self::fy($date), '{ddmm}' => Carbon::parse($date ?: now())->format('dm')]).$n;
    }

    public static function employeeAccount(string $empCode): string
    {
        $code = preg_replace('/\D/', '', $empCode);

        return strtoupper(strtr((string) config('btc.focus_export.employee_account', 'BTC-E{code4}'), ['{code4}' => str_pad($code, 4, '0', STR_PAD_LEFT), '{code}' => $code]));
    }

    /** Employee master values (HRIMS) by employee code; null when the code is not in the master. */
    public function employee(string $empCode): ?array
    {
        $empCode = trim($empCode);
        if ($empCode === '') {
            return null;
        }
        if (array_key_exists($empCode, $this->empCache)) {
            return $this->empCache[$empCode];
        }
        $r = null;
        try {
            $h = DB::connection('hrims');
            $r = $h->table('hrm_employee as e')->leftJoin('hrm_employee_general as g', 'g.EmployeeID', '=', 'e.EmployeeID')
                ->leftJoin('core_departments as d', 'd.id', '=', 'g.DepartmentId')
                ->leftJoin('core_sub_department_master as sd', 'sd.id', '=', 'g.SubDepartmentId')
                ->leftJoin('core_business_unit as bu', 'bu.id', '=', 'g.BUId')
                ->leftJoin('core_regions as r', 'r.id', '=', 'g.RegionId')
                ->leftJoin('core_zones as z', 'z.id', '=', 'g.ZoneId')
                ->leftJoin('core_functions as f', 'f.id', '=', 'g.EmpFunction')
                ->leftJoin('core_verticals as v', 'v.id', '=', 'g.EmpVertical')
                ->leftJoin('core_city_village_by_state as hq', 'hq.id', '=', 'g.HqId')
                ->leftJoin('core_states as st', 'st.id', '=', 'hq.state_id')
                ->where('e.EmpCode', $empCode)->orderByRaw("e.EmpStatus = 'A' DESC")->orderByDesc('e.EmployeeID')
                ->first(['e.EmployeeID', 'e.EmpCode', 'e.Fname', 'e.Sname', 'e.Lname', 'e.CompanyId', 'd.department_code', 'sd.focus_code as sub_dept', 'sd.sub_department_name',
                    'bu.business_unit_code', 'r.focus_code as region', 'r.region_name', 'z.focus_code as zone_focus', 'z.zone_code', 'f.function_code', 'v.vertical_code',
                    'hq.city_village_name as location', 'st.state_code', 'st.state_name', 'g.HQ', 'g.Category']);
        } catch (\Throwable $e) {
            report($e);
        }
        $v = fn ($x) => trim((string) $x) !== '' ? trim((string) $x) : 'N/A';

        return $this->empCache[$empCode] = $r ? [
            'employee_id' => (int) $r->EmployeeID,
            'code' => (string) $r->EmpCode,
            'name' => trim(preg_replace('/\s+/', ' ', $r->Fname.' '.$r->Sname.' '.$r->Lname)),
            'entity' => ((int) $r->CompanyId === 11) ? 'VVNR' : 'VNR',
            'department' => $v($r->department_code), 'sub_department' => $v($r->sub_dept), 'business_unit' => $v($r->business_unit_code),
            'region' => $v($r->region), 'zone' => $v($r->zone_focus ?: $r->zone_code), 'function' => $v($r->function_code), 'vertical' => $v($r->vertical_code),
            'location' => $v(strtoupper((string) ($r->location ?: $r->HQ))), 'state' => $v($r->state_code), 'state_name' => (string) $r->state_name,
            'category' => $v($r->Category ?? ''), 'crop' => 'All Crop',   // BTC-FOCUS-05: no crop on the employee master yet
        ] : null;
    }

    /** Employees on the bill: the requester plus any co-travellers (by code, no duplicates). */
    public static function pax(BtcCase $c): array
    {
        $list = [];
        if ($c->emp_code) {
            $list[(string) $c->emp_code] = (string) $c->emp_name;
        }
        $t = json_decode((string) ($c->travellers ?? ''), true);
        foreach (is_array($t) ? $t : [] as $p) {
            $code = trim((string) ($p['code'] ?? ''));
            if ($code !== '' && ! isset($list[$code])) {
                $list[$code] = (string) ($p['name'] ?? '');
            }
        }

        return $list;
    }

    public static function expenseType(BtcCase $c): string
    {
        return (string) ($c->focus_expense_type ?: config('btc.focus_export.expense_type_by_category.'.$c->category, 'Misc.'));
    }

    /** [ledger, sub-ledger, error] for the case and entity - from the Focus ledgers Finance keeps in BTC Settings. */
    public static function ledger(BtcCase $c, string $entity): array
    {
        return FocusLedgers::lookup((string) ($c->focus_activity_type ?? ''), self::expenseType($c), $entity);
    }

    /** Activity column: the Focus code of the Activity Head, else "All Activity". */
    public static function activity(BtcCase $c): string
    {
        return FocusLedgers::headCode($c->focus_activity_head) ?: (string) config('btc.focus_export.activity_default', 'All Activity');
    }

    private static function punch(BtcCase $c, string $k): ?string
    {
        $p = json_decode((string) ($c->punch_json ?? ''), true);
        $v = is_array($p) ? trim((string) ($p[$k] ?? '')) : '';

        return $v !== '' ? $v : null;
    }

    private static function dmy($d): ?string
    {
        if (! $d) {
            return null;
        }
        try {
            $c = $d instanceof \DateTimeInterface ? Carbon::instance($d) : Carbon::parse(str_replace('/', '-', (string) $d));

            return $c->year > 1990 ? $c->format('d-m-Y') : null;
        } catch (\Throwable $e) {
            return null;
        }
    }

    /**
     * The narration date. BTC-JV-34 (27 Nov 2026, testing point 34): date first, then month (dd-mm-YYYY) - it was
     * mm-dd-YYYY. Nothing else of the narration changes.
     */
    private static function mdy($d): ?string
    {
        return self::dmy($d);
    }

    /**
     * BTC-FOCUS-05 (25 Nov 2026, testing point 19): "Being {Mode} of {Employee Name} & {Person Name} stay in {Vendor Name}
     * in room no. {Room No} from Dt.- {Stay From} to dt.- {Stay To} vide bill no.- {Inv No} dt.-{Inv Date} For The Purpose
     * of {Purpose}", dates dd-mm-YYYY (BTC-JV-34), the Punch Screen's narration remark at the end. Null parts are left out.
     */
    public static function narration(BtcCase $c, array $pax, string $stateName): array
    {
        $p = json_decode((string) ($c->punch_json ?? ''), true) ?: [];
        $mode = trim((string) ($p['_mode'] ?? '')) ?: (string) config('btc.narration_mode.'.$c->category, mb_strtolower(self::expenseType($c)).' exp.');
        $people = array_map(fn ($code, $name) => trim(trim($name).' '.self::employeeCodeE((string) $code)), array_keys($pax), $pax);
        foreach (array_filter(array_map('trim', explode(',', (string) ($c->guests ?? '')))) as $g) {
            $people[] = $g;
        }
        $who = implode(' & ', $people);
        $legs = (array) ($p['_t']['legs'] ?? []);
        $from = self::mdy($c->service_from) ?: self::mdy(self::punch($c, 'Chk. In Date')) ?: self::mdy(self::punch($c, 'Journey Date')) ?: self::mdy(self::punch($c, 'Booking Date')) ?: self::mdy($c->travel_date)
            ?: self::mdy(self::punch($c, 'Meeting Date')) ?: (in_array($c->category, ['meals', 'meeting', 'other'], true) ? self::mdy($c->bill_date) : null);
        $to = self::mdy($c->service_to) ?: self::mdy(self::punch($c, 'Chk. Out Date')) ?: self::mdy($legs ? (end($legs)['Journey Date'] ?? null) : null) ?: self::mdy(self::punch($c, 'End Date')) ?: $from;
        $billDt = self::mdy($c->bill_date);
        $err = [];
        if (! $from) {
            $err[] = 'stay / travel date missing';
        }
        if (! $billDt) {
            $err[] = 'bill date missing';
        }
        $verb = $c->category === 'hotel' ? 'stay in' : (in_array($c->category, ['air', 'train', 'bus', 'vehicle'], true) ? 'travel by' : 'at');
        $room = self::punch($c, 'Room No') ?: self::punch($c, 'Room No.') ?: self::punch($c, 'Room Number');
        $route = '';
        if ($legs) {
            $route = ' ('.implode(', ', array_filter(array_map(fn ($l) => trim(($l['Journey From'] ?? '').'-'.($l['Journey Upto'] ?? ''), '-'), $legs))).')';
        } elseif (self::punch($c, 'Route From') || self::punch($c, 'Route To')) {
            $route = ' ('.trim(self::punch($c, 'Route From').'-'.self::punch($c, 'Route To'), '-').')';
        }
        $remark = trim((string) ($p['_narration_remark'] ?? ''));
        $n = 'Being '.$mode." of {$who} {$verb} ".trim((string) $c->vendor_name).($stateName !== '' ? ", {$stateName}" : '').($route !== ' ()' ? $route : '')
            .($room && $c->category === 'hotel' ? " in room no. {$room}" : '')
            .($from ? " from Dt.- {$from} to dt.- {$to}" : '')
            .' vide bill no.- '.trim((string) $c->bill_no).($billDt ? " dt.-{$billDt}" : '')
            .(trim((string) $c->purpose) !== '' ? ' For The Purpose of '.trim(preg_replace('/\s+/', ' ', (string) $c->purpose)) : '')
            .($remark !== '' ? ' '.$remark : '');

        return [mb_substr(preg_replace('/\s+/', ' ', $n), 0, 500), $err];
    }

    /** The vendor's state from the core vendor list (narration "stay in <vendor>, <state>"). */
    public static function vendorState(BtcCase $c): string
    {
        if (! $c->vendor_code || ! Schema::hasTable('btc_core_vendors')) {
            return '';
        }

        return trim((string) DB::table('btc_core_vendors')->where('code', $c->vendor_code)->value('state_name'));
    }

    /** "JV/BTC/2026-27/0001" or "JV/BTC/2026-27/0001-0002" for several rows. */
    public static function docRange(array $docs): string
    {
        $docs = array_values(array_filter($docs));
        if (count($docs) <= 1) {
            return (string) ($docs[0] ?? '');
        }

        return mb_substr($docs[0].'-'.substr((string) end($docs), -4), 0, 40);
    }

    public static function employeeCodeE(string $code): string
    {
        return 'E'.str_pad(preg_replace('/\D/', '', $code), 4, '0', STR_PAD_LEFT);
    }

    /** BTC-JV-34: "E1001", "1001", "e01001" -> "1001" (the employee code as the master keeps it). */
    private static function bareCode(string $x): string
    {
        $x = strtoupper(trim($x));

        return preg_replace('/^E0*/', '', $x) ?: $x;
    }

    /**
     * BTC-JV-34 (27 Nov 2026, testing point 34): who the bill is for - employees (code => name) and guests (names).
     * Employees: the requester and the co-travellers; for a Bus "Team + Guest" request the passengers as entered (the
     * requester only when he is one of them). Guests: the guest names of the request.
     *
     * @return array{employees: array<string,string>, guests: list<string>}
     */
    public static function persons(BtcCase $c): array
    {
        $emp = self::pax($c);
        $pj = json_decode((string) ($c->passengers_json ?? ''), true);
        if (is_array($pj) && $pj && $c->emp_code) {
            $codes = array_map(fn ($p) => self::bareCode((string) ($p['code'] ?? '')), array_filter($pj, fn ($p) => ($p['type'] ?? '') === 'employee'));
            if (! in_array(self::bareCode((string) $c->emp_code), $codes, true)) {
                unset($emp[(string) $c->emp_code]);
            }
        }
        $guests = array_values(array_filter(array_map('trim', explode(',', (string) ($c->guests ?? ''))), fn ($g) => $g !== ''));

        return ['employees' => $emp, 'guests' => $guests];
    }

    /** BTC-JV-34: the expense type of a journey leg from its Mode (Air / Train / Rail / Bus); else the bill's. */
    private static function legType(BtcCase $c, string $mode): string
    {
        $m = strtolower(trim($mode));
        $cat = str_contains($m, 'air') || str_contains($m, 'flight') ? 'air' : (str_contains($m, 'train') || str_contains($m, 'rail') ? 'train' : (str_contains($m, 'bus') ? 'bus' : null));
        if ($cat === null || $cat === $c->category) {
            return self::expenseType($c);
        }

        return (string) config('btc.focus_export.expense_type_by_category.'.$cat, self::expenseType($c));
    }

    /**
     * BTC-JV-34: the bill as accounting lines - one per line of the punch table that has a line total (journey legs,
     * hotel line items, invoice items), each with its own expense type and people; what the lines do not cover
     * (service charge, its GST, rounding) is one more line. A bill without such a table is one line.
     *
     * @return array{0: list<array{label:string, type:string, amount:float, employees:array<string,string>, guests:list<string>}>, 1: list<string>}
     */
    public function lines(BtcCase $c): array
    {
        $who = self::persons($c);
        $total = round((float) $c->bill_amount, 2);
        $p = json_decode((string) ($c->punch_json ?? ''), true) ?: [];
        $form = (string) config('btc.punch_form_of.'.$c->category, '');
        $err = [];
        $lines = [];
        foreach ((array) config("btc.punch_forms.{$form}.tables", []) as $key => $def) {
            $col = $def['total'] ?? null;
            if (! $col) {
                continue;
            }
            foreach ((array) ($p['_t'][$key] ?? []) as $i => $r) {
                $amt = round((float) ($r[$col] ?? 0), 2);
                if (abs($amt) < 0.005) {
                    continue;
                }
                $emps = $who['employees'];
                $guests = $who['guests'];
                $codes = array_values(array_filter(array_map(fn ($x) => self::bareCode($x), preg_split('/[\s,;]+/', (string) ($r['Employees'] ?? ''))), fn ($x) => $x !== ''));
                if ($codes) {   // the line names its employees: only they
                    $emps = [];
                    foreach ($codes as $code) {
                        $m = $this->employee($code);
                        if (! $m) {
                            $err[] = ($def['title'] ?? $key).' '.($i + 1).": employee code {$code} not in the employee master";

                            continue;
                        }
                        $emps[$m['code']] = $m['name'];
                    }
                    $guests = [];
                }
                $type = $key === 'legs' ? self::legType($c, (string) ($r['Mode'] ?? '')) : self::expenseType($c);
                $label = $key === 'legs' ? trim(($r['Mode'] ?? '').' '.trim(($r['Journey From'] ?? '').'-'.($r['Journey Upto'] ?? ''), '-').' '.($r['Ticket Number'] ?? $r['PNR Number'] ?? ''))
                    : trim((string) ($r['Particulars'] ?? $r['Particular'] ?? ''));
                $lines[] = ['label' => ($def['title'] ?? $key).' '.($i + 1).($label !== '' ? ': '.$label : ''), 'type' => $type, 'amount' => $amt, 'employees' => $emps, 'guests' => $guests];
            }
            if ($lines) {
                break;   // one table carries the amounts
            }
        }
        $sum = round(array_sum(array_column($lines, 'amount')), 2);
        if ($lines && $sum - $total > 0.009) {
            $err[] = 'the punched lines add up to Rs '.number_format($sum, 2).', more than the bill total Rs '.number_format($total, 2);
        }
        $rest = round($total - $sum, 2);
        if (! $lines || abs($rest) > 0.009) {
            $lines[] = ['label' => $lines ? 'Other charges on the bill (service charge, GST, rounding)' : 'Bill', 'type' => self::expenseType($c), 'amount' => $rest,
                'employees' => $who['employees'], 'guests' => $who['guests']];
        }

        return [$lines, $err];
    }

    /**
     * BTC-JV-34: each line split equally among its people (paisa difference on the last) - one entry per person per line.
     * An employee's share: vendor JV Dr Employee BTC / Cr vendor, and the employee JV Dr expense / Cr Employee BTC.
     * A guest's share: vendor JV Dr expense / Cr vendor only - no Employee BTC, no employee JV.
     *
     * @return array{0: list<array{kind:string, code:string, name:string, type:string, amount:float, line:string}>, 1: list<string>}
     */
    public function entries(BtcCase $c): array
    {
        [$lines, $err] = $this->lines($c);
        $out = [];
        foreach ($lines as $l) {
            $people = [];
            foreach ($l['employees'] as $code => $name) {
                $people[] = ['kind' => 'employee', 'code' => (string) $code, 'name' => (string) $name];
            }
            foreach ($l['guests'] as $g) {
                $people[] = ['kind' => 'guest', 'code' => '', 'name' => $g];
            }
            if (! $people) {
                $err[] = $l['label'].': no employee or guest';

                continue;
            }
            $n = count($people);
            $each = $l['amount'] >= 0 ? floor($l['amount'] / $n * 100) / 100 : ceil($l['amount'] / $n * 100) / 100;
            foreach ($people as $i => $who) {
                $amt = $i === $n - 1 ? round($l['amount'] - $each * ($n - 1), 2) : $each;
                $out[] = $who + ['type' => $l['type'], 'amount' => $amt, 'line' => $l['label']];
            }
        }

        return [$out, $err];
    }

    /** BTC-JV-34: the entity of the bill for a guest's share - the requester's company (else the bill's company). */
    private function billEntity(BtcCase $c, array $entries): array
    {
        $m = $c->emp_code ? $this->employee((string) $c->emp_code) : null;
        foreach ($entries as $e) {
            if (! $m && $e['kind'] === 'employee') {
                $m = $this->employee($e['code']);
            }
        }

        return [$m, $m['entity'] ?? ((int) $c->company_id === 11 ? 'VVNR' : 'VNR')];
    }

    /** Checks before any entry (tracker list); returns the problems. */
    public function validate(BtcCase $c, array $pax): array
    {
        $e = [];
        if (trim((string) $c->bill_no) === '') {
            $e[] = 'bill no. missing';
        } elseif (BtcCase::where('id', '!=', $c->id)->where('company_id', $c->company_id)->whereNotIn('status', ['cancelled', 'rejected'])
            ->where('bill_no', $c->bill_no)->where(fn ($q) => $q->where('vendor_name', $c->vendor_name)->when($c->vendor_gstin, fn ($w) => $w->orWhere('vendor_gstin', $c->vendor_gstin)))->exists()) {
            $e[] = 'bill no. '.$c->bill_no.' is on another case of this vendor';
        }
        if (trim((string) $c->vendor_code) === '') {
            $e[] = 'vendor not linked to the vendor master (no vendor code) - choose the vendor from the core list';
        }
        if ((float) $c->bill_amount <= 0) {
            $e[] = 'bill amount missing';
        }
        if (trim((string) $c->purpose) === '') {
            $e[] = 'purpose missing';
        }
        if ($c->employee_id && ! $c->manager_action_at && $c->route !== 'direct_bill') {
            $e[] = 'reporting manager (HOD) approval not recorded';
        }
        $who = self::persons($c);
        if (! $pax && ! $who['guests']) {
            $e[] = 'no employee or guest on the bill';   // BTC-JV-34: a guest-only bill is booked straight to expense
        }
        foreach (array_keys($pax) as $code) {
            if (! $this->employee((string) $code)) {
                $e[] = "employee code {$code} not in the employee master";
            }
        }

        return $e;
    }

    /** Excel serial date (the import wants real dates, not text). */
    private static function xlDate($d)
    {
        if (! $d) {
            return '';
        }

        return \PhpOffice\PhpSpreadsheet\Shared\Date::PHPToExcel(Carbon::parse($d)->startOfDay());
    }

    /**
     * Vendor JV rows for the cases. $assign = false: check only (no numbers given, nothing marked).
     * BTC-JV-34 (27 Nov 2026, testing point 34): one row per person per bill line (see entries()); an employee's row
     * Dr Employee BTC / Cr vendor, a guest's row Dr the expense ledger (Focus ledgers in BTC Settings, by the entity
     * and the line's expense type) / Cr vendor. All rows of the bill under one DocNo.
     * @return array{rows: array, check: array}
     */
    public function vendorJv($cases, bool $assign): array
    {
        $rows = [];
        $check = [];
        foreach ($cases as $c) {
            $pax = self::pax($c);
            $err = $this->validate($c, $pax);
            [$entries, $eerr] = $this->entries($c);
            $err = array_merge($err, $eerr);
            [$req, $billEntity] = $this->billEntity($c, $entries);
            $first = $req;
            $ledgers = [];
            $ledgerOf = function (string $type, string $entity) use ($c, &$ledgers, &$err) {
                $k = $type.'|'.$entity;
                if (! isset($ledgers[$k])) {
                    $ledgers[$k] = self::ledger($c->replicate()->forceFill(['focus_expense_type' => $type]), $entity);
                    if ($ledgers[$k][2]) {
                        $err[] = $ledgers[$k][2];
                    }
                }

                return $ledgers[$k];
            };
            $be = fn (string $entity) => (string) (config('btc.focus_export.business_entity')[$entity] ?? '');
            $plan = [];
            foreach ($entries as $e) {
                $m = $e['kind'] === 'employee' ? $this->employee($e['code']) : $req;
                $entity = $m['entity'] ?? $billEntity;
                [$ledger, $sub] = $ledgerOf($e['type'], $entity);
                if ($be($entity) === '') {
                    $err[] = "Business Entity for {$entity} not set (BTC_FOCUS_ENTITY_VVNR) - Finance to confirm";
                }
                if (! $m) {
                    $err[] = 'no employee master for the dimensions of '.($e['kind'] === 'guest' ? 'the guest share (requester)' : $e['code']);
                }
                $plan[] = [$e, $m, $entity, $ledger, $sub];
            }
            $err = array_values(array_unique($err));
            [$narr, $nerr] = self::narration($c, self::persons($c)['employees'], self::vendorState($c) ?: (string) ($first['state_name'] ?? ''));   // BTC-JV-34: a guest-only bill names the guests only
            $err = array_merge($err, $nerr);
            [$l0, $s0] = $plan ? [$plan[0][3], $plan[0][4]] : [null, null];
            $check[] = $this->checkRow($c, 'JV', $err, $first, $billEntity, $l0, $s0, $pax, $entries);
            if ($err) {
                continue;
            }
            $activity = self::activity($c);
            $doc = $c->focus_jv_doc ?: ($assign ? self::nextJvDoc(now(), (int) $c->company_id) : '(on export)');
            $at = $c->focus_exported_at ? Carbon::parse($c->focus_exported_at) : now();
            foreach ($plan as [$e, $m, $entity, $ledger, $sub]) {
                $dr = $e['kind'] === 'employee' ? self::employeeAccount($e['code']) : (string) $ledger;
                $rows[] = [$doc, self::xlDate($at), $at->format('H:i:s'), $be($entity), '', '', (string) $c->bill_no, self::xlDate($c->bill_date), $m['department'], $sub,
                    $m['business_unit'], $activity, $m['location'], $m['state'], $m['category'] ?? 'N/A', $m['crop'] ?? 'All Crop', $m['region'], $m['function'], $m['vertical'],
                    $m['sub_department'], $m['zone'], $dr, strtoupper((string) $c->vendor_code), $e['amount'], $narr, '', '', '', ''];
            }
            if ($assign && ! $c->focus_jv_doc) {
                $c->focus_jv_doc = $doc;
                $c->focus_exported_at = now();
                if ($c->exists) {
                    BtcCase::whereKey($c->id)->update(['focus_jv_doc' => $doc, 'focus_exported_at' => $c->focus_exported_at]);
                }
                $n = count($plan);
                $this->log($c, 'Focus JV '.$doc.' exported ('.$n.' row'.($n > 1 ? 's' : '').', Rs '.number_format((float) $c->bill_amount, 2).')');
            }
        }

        return ['rows' => $rows, 'check' => $check];
    }

    /**
     * Employee JV (expense booking): Dr the expense ledger / Cr Employee BTC, one row per employee per bill line.
     * BTC-JV-34 (27 Nov 2026, testing point 34): made with the vendor JV at Focus Export on the Punch Screen (it used to
     * wait for payment approval and Treasury's export); none for a guest's share; each line keeps its own expense
     * ledger (by the employee's entity VNR / VVNR and the line's expense type, from the Focus ledgers in BTC Settings).
     */
    public function employeeJv($cases, bool $assign): array
    {
        $rows = [];
        $check = [];
        foreach ($cases as $c) {
            $pax = self::pax($c);
            $err = $this->validate($c, $pax);
            [$entries, $eerr] = $this->entries($c);
            $err = array_merge($err, $eerr);
            $emp = array_values(array_filter($entries, fn ($e) => $e['kind'] === 'employee'));
            [$req, $billEntity] = $this->billEntity($c, $entries);
            $plan = [];
            foreach ($emp as $e) {
                $m = $this->employee($e['code']);
                $entity = $m['entity'] ?? $billEntity;
                [$ledger, $sub, $lerr] = self::ledger($c->replicate()->forceFill(['focus_expense_type' => $e['type']]), $entity);
                if ($lerr) {
                    $err[] = $lerr;
                }
                $be = (string) (config('btc.focus_export.business_entity')[$entity] ?? '');
                if ($be === '') {
                    $err[] = "Business Entity for {$entity} not set - Finance to confirm";
                }
                $plan[] = [$e, $entity, $be, $ledger, $sub];
            }
            $err = array_values(array_unique($err));
            [$narr, $nerr] = self::narration($c, self::persons($c)['employees'], self::vendorState($c) ?: (string) ($req['state_name'] ?? ''));
            $err = array_merge($err, $nerr);
            $row = $this->checkRow($c, 'EJV', $err, $req, $billEntity, $plan[0][3] ?? null, $plan[0][4] ?? null, $pax, $emp);
            if (! $err && ! $emp) {
                $row['problems'] = 'No employee JV: the bill is for guest(s) only - booked straight to expense in the vendor JV';
            }
            $check[] = $row;
            if ($err || ! $emp) {
                continue;
            }
            $date = $c->focus_exported_at ? Carbon::parse($c->focus_exported_at) : now();
            $activity = self::activity($c);
            $mon = strtoupper($date->format('M'));
            $doc = $c->focus_ejv_doc ?: ($assign ? self::nextDoc(config('btc.focus_export.employee_series'), $date, (int) $c->company_id) : '(on export)');   // BTC-FOCUS-05: one per bill
            foreach ($plan as [$e, $entity, $be, $ledger, $sub]) {
                // as in the sample: the dimensions stay blank in the employee JV (they are on the vendor JV)
                $rows[] = [$doc, self::xlDate($date), self::employeeAccount($e['code']), $be, '', '', $narr, $mon, '', 'All Crop',
                    '', '', $activity, 'N/A', $sub, '', '', '', '', '', $ledger, $e['amount']];
            }
            if ($assign && ! $c->focus_ejv_doc) {
                $c->focus_ejv_doc = $doc;
                if ($c->exists) {
                    BtcCase::whereKey($c->id)->update(['focus_ejv_doc' => $doc, 'focus_ejv_at' => now()]);
                }
                $this->log($c, 'Focus Employee JV '.$doc.' exported ('.count($plan).' row'.(count($plan) > 1 ? 's' : '').')');
            }
        }

        return ['rows' => $rows, 'check' => $check];
    }

    /** Bank payment (payment release) rows for the payments Treasury recorded and not yet exported. */
    public function bankPayments($payments, bool $assign): array
    {
        $rows = [];
        $check = [];
        $cases = BtcCase::whereIn('id', collect($payments)->pluck('btc_case_id')->unique())->get()->keyBy('id');
        foreach ($payments as $p) {
            $c = $cases[$p->btc_case_id] ?? null;
            if (! $c) {
                continue;
            }
            $err = [];
            if (trim((string) $c->vendor_code) === '') {
                $err[] = 'vendor code missing';
            }
            if (! $c->focus_jv_doc) {
                $err[] = 'vendor JV not exported yet';
            }
            $m = $c->emp_code ? $this->employee((string) $c->emp_code) : null;
            $entity = $m['entity'] ?? 'VNR';
            [, $sub, $lerr] = self::ledger($c, $entity);
            if ($lerr) {
                $err[] = $lerr;
            }
            $be = (string) (config('btc.focus_export.business_entity')[$entity] ?? '');
            if ($be === '') {
                $err[] = "Business Entity for {$entity} not set";
            }
            $check[] = ['type' => 'Bank payment', 'case' => $c->case_no, 'result' => $err ? 'Fail' : 'Pass', 'problems' => implode('; ', $err),
                'employee' => trim($c->emp_code.' '.$c->emp_name), 'entity' => $entity, 'vendor' => $c->vendor_name.' ('.$c->vendor_code.')',
                'ledger' => '', 'sub' => (string) $sub, 'dims' => $m ? implode(' / ', [$m['department'], $m['location'], $m['state'], $m['region']]) : '',
                'amount' => (float) $p->amount];
            if ($err) {
                continue;
            }
            $doc = $p->focus_doc ?: ($assign ? self::nextDoc(config('btc.focus_export.bank_series'), $p->paid_on, (int) $c->company_id) : '(on export)');
            $narr = 'Being payment to '.trim((string) $c->vendor_name).' against bill no.- '.$c->bill_no.' Dt.-'.self::dmy($c->bill_date)
                .' for '.trim($c->emp_name.' '.self::employeeCodeE((string) $c->emp_code)).' ('.$c->case_no.')';
            $bd = (array) config('btc.focus_export.bank_dims', []);
            // as in the Treasury sample: DocNo blank (Focus numbers it), the paying office's dimensions, TDS 0
            $rows[] = ['', self::xlDate($p->paid_on), Carbon::parse($p->created_at ?? now())->format('H:i:s'), config('btc.focus_export.cash_bank_ac'), '', mb_substr($narr, 0, 500),
                (string) $p->utr_number, strtoupper((string) $p->mode), self::activity($c), 'N/A', $bd['region'] ?? 'N/A', $bd['crop'] ?? 'All Crop', 'N/A', $be,
                $bd['cost_center'] ?? 'N/A', $bd['department'] ?? 'FIN', config('btc.focus_export.pmt_category'), $bd['business_unit'] ?? 'N/A', $bd['location'] ?? 'RAIPUR',
                $bd['state'] ?? '22', $bd['function'] ?? 'BSF', $bd['vertical'] ?? 'CM', $bd['sub_department'] ?? 'SUB_DEPT_FIN_124', $bd['zone'] ?? '',
                strtoupper((string) $c->vendor_code), (float) $p->amount, '', '', 0, 0, ''];
            if ($assign && ! $p->focus_doc) {
                DB::table('btc_payments')->where('id', $p->id)->update(['focus_doc' => $doc, 'focus_exported_at' => now()]);
                $this->log($c, 'Focus bank payment '.$doc.' exported (UTR '.$p->utr_number.')');
            }
        }

        return ['rows' => $rows, 'check' => $check];
    }

    private function checkRow(BtcCase $c, string $type, array $err, ?array $m, string $entity, $ledger, $sub, array $pax, array $entries = []): array
    {
        // BTC-JV-34: the entries (person, line, expense type, amount) when the bill has more than one
        $people = $entries ? implode('; ', array_map(fn ($e) => trim(($e['kind'] === 'guest' ? 'Guest '.$e['name'] : $e['code'].' '.$e['name']).' - '.$e['type'].' '.number_format($e['amount'], 2)), $entries))
            : implode(', ', array_map(fn ($k, $v) => $k.' '.$v, array_keys($pax), $pax));

        return ['type' => $type, 'case' => $c->case_no, 'result' => $err ? 'Fail' : 'Pass', 'problems' => implode('; ', $err),
            'employee' => $people, 'entity' => $entity,
            'vendor' => trim($c->vendor_name.' ('.($c->vendor_code ?: 'no code').')'), 'ledger' => (string) $ledger, 'sub' => (string) $sub,
            'dims' => $m ? implode(' / ', [$m['department'], $m['sub_department'], $m['business_unit'], $m['region'], $m['zone'], $m['function'], $m['vertical'], $m['location'], $m['state']]) : '',
            'amount' => (float) $c->bill_amount];
    }

    private function log(BtcCase $c, string $remark): void
    {
        $u = auth()->user();
        \App\Models\BtcCaseLog::create(['btc_case_id' => $c->id, 'action' => 'focus_export', 'from_status' => $c->status, 'to_status' => $c->status,
            'remark' => $remark, 'user_id' => $u->id ?? null, 'user_name' => $u->name ?? 'system']);
    }
}
