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
 *  ejv  - Focus Employee JV import (expense booking to the ledger), DocNo EJV/BTC/<FY>/0001, only when approved and,
 *         for air / rail / bus, the proof of travel is received
 *  bank - Focus Bank Payment (non cheque) import of the payments Treasury recorded, DocNo BP/BTC/<FY>/0001
 *
 * Employee values come from HRIMS by employee code (never by name); ledger / sub-ledger codes from config
 * btc.focus_export (the codes written in the tracker). A case that fails a check is listed with the reason and gets
 * no document number; nothing is guessed.
 */
class BtcFocusExport
{
    public const JV_COLS = ['DocNo', 'Date', 'Business Entity', 'Narration', 'TDS JVNo', 'Reverse Charge [Yn]', 'Bill No', 'Bill Date', 'Department',
        'Cost Center', 'Business Unit', 'Activity', 'Location', 'State', 'Category', 'Crop', 'Region', 'Function', 'FC-Vertical', 'Sub Department', 'Zone',
        'DrAccount', 'CrAccount', 'Amount', 'TDS', 'TDSPer'];

    // Focus "13. EMPLOYEE JV IMPORT FORMAT": Account (EC), ..., Account (ledger)
    public const EJV_COLS = ['DocNo', 'Date', 'Time', 'Account', 'Business Entity', 'sNarration', 'ActMonths', 'LB', 'Crop', 'Department', 'Location',
        'Activity', 'Category', 'State', 'Region', 'Business Unit', 'Cost Center', 'Account', 'Amount', 'sRemarks'];

    // Focus "2. BANK PAYMENT - NON CHEQUE IMPORT FORMAT"
    public const BANK_COLS = ['DocNo', 'Date', 'Time', 'CashBankAC', 'TDSJVNo', 'sNarration', 'sChequeNo', 'TransactiontypeCode', 'Activity', 'Category',
        'Region', 'Crop', 'Farm', 'Business Entity', 'Cost Center', 'Department', 'PMT Category', 'Business Unit', 'Location', 'State', 'Account', 'Amount',
        'Reference', 'sRemarks', 'TDSBillAmount', 'TDS', 'sBRSUser'];

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
                    'hq.city_village_name as location', 'st.state_code', 'st.state_name', 'g.HQ']);
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

    /** Narration as in the tracker; null parts are left out, invalid dates never written. */
    public static function narration(BtcCase $c, array $pax, string $stateName): array
    {
        $type = self::expenseType($c);
        $who = implode(' & ', array_map(fn ($code, $name) => trim($name).' '.self::employeeCodeE($code), array_keys($pax), $pax));
        $from = self::dmy($c->service_from) ?: self::dmy(self::punch($c, 'Chk. In Date')) ?: self::dmy(self::punch($c, 'Journey Date')) ?: self::dmy($c->travel_date);
        $to = self::dmy($c->service_to) ?: self::dmy(self::punch($c, 'Chk. Out Date')) ?: $from;
        $billDt = self::dmy($c->bill_date);
        $err = [];
        if (! $from) {
            $err[] = 'stay / travel date missing';
        }
        if (! $billDt) {
            $err[] = 'bill date missing';
        }
        $verb = in_array($c->category, ['hotel'], true) ? 'stay in' : (in_array($c->category, ['air', 'train', 'bus', 'vehicle'], true) ? 'travel by' : 'at');
        $rooms = self::punch($c, 'Number of Rooms');
        $n = "Being {$type} exp. of {$who} {$verb} ".trim((string) $c->vendor_name).($stateName !== '' ? ", {$stateName}" : '')
            .($rooms && $c->category === 'hotel' ? " ({$rooms} room(s))" : '')
            .($from ? " from Dt.- {$from} to Dt.- {$to}" : '')
            .' vide bill no.- '.trim((string) $c->bill_no).($billDt ? " Dt.- {$billDt}" : '')
            .(trim((string) $c->purpose) !== '' ? ' for the purpose of '.trim(preg_replace('/\s+/', ' ', (string) $c->purpose)) : '');

        return [mb_substr($n, 0, 500), $err];
    }

    /** The vendor's state from the core vendor list (narration "stay in <vendor>, <state>"). */
    public static function vendorState(BtcCase $c): string
    {
        if (! $c->vendor_code || ! Schema::hasTable('btc_core_vendors')) {
            return '';
        }

        return trim((string) DB::table('btc_core_vendors')->where('code', $c->vendor_code)->value('state_name'));
    }

    public static function employeeCodeE(string $code): string
    {
        return 'E'.str_pad(preg_replace('/\D/', '', $code), 4, '0', STR_PAD_LEFT);
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
        if (! $pax) {
            $e[] = 'no employee on the bill';
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
     * @return array{rows: array, check: array}
     */
    public function vendorJv($cases, bool $assign): array
    {
        $rows = [];
        $check = [];
        foreach ($cases as $c) {
            $pax = self::pax($c);
            $err = $this->validate($c, $pax);
            $first = $pax ? $this->employee((string) array_key_first($pax)) : null;
            $entity = $first['entity'] ?? 'VNR';
            [$ledger, $sub, $lerr] = self::ledger($c, $entity);
            if ($lerr) {
                $err[] = $lerr;
            }
            $be = (string) (config('btc.focus_export.business_entity')[$entity] ?? '');
            if ($be === '') {
                $err[] = "Business Entity for {$entity} not set (BTC_FOCUS_ENTITY_VVNR) - Finance to confirm";
            }
            [$narr, $nerr] = self::narration($c, $pax, self::vendorState($c) ?: (string) ($first['state_name'] ?? ''));
            $err = array_merge($err, $nerr);
            $date = $c->finance_at ?: now();
            $check[] = $this->checkRow($c, 'JV', $err, $first, $entity, $ledger, $sub, $pax);
            if ($err) {
                continue;
            }
            $doc = $c->focus_jv_doc ?: ($assign ? self::nextDoc(config('btc.focus_export.vendor_series'), $date, (int) $c->company_id) : '(on export)');
            $activity = self::activity($c);
            // one row per employee; the amount split, any paisa difference on the last row
            $n = count($pax);
            $total = round((float) $c->bill_amount, 2);
            $each = floor($total / $n * 100) / 100;
            $i = 0;
            foreach ($pax as $code => $name) {
                $i++;
                $m = $this->employee((string) $code);
                $amt = $i === $n ? round($total - $each * ($n - 1), 2) : $each;
                $rows[] = [$doc, self::xlDate($date), $be, $narr, '', 'N', (string) $c->bill_no, self::xlDate($c->bill_date), $m['department'], $sub,
                    $m['business_unit'], $activity, $m['location'], $m['state'], 'N/A', 'N/A', $m['region'], $m['function'], $m['vertical'], $m['sub_department'],
                    $m['zone'], self::employeeAccount((string) $code), strtoupper((string) $c->vendor_code), $amt, '', ''];
            }
            if ($assign && ! $c->focus_jv_doc) {
                BtcCase::whereKey($c->id)->update(['focus_jv_doc' => $doc, 'focus_exported_at' => $c->focus_exported_at ?: now()]);
                $this->log($c, 'Focus JV '.$doc.' exported');
            }
        }

        return ['rows' => $rows, 'check' => $check];
    }

    /** Employee JV: only after payment approval and, for air / rail / bus, with the proof of travel received. */
    public function employeeJv($cases, bool $assign): array
    {
        $rows = [];
        $check = [];
        foreach ($cases as $c) {
            $pax = self::pax($c);
            $err = $this->validate($c, $pax);
            if (! in_array($c->status, ['fin_approved', 'paid', 'closed'], true)) {
                $err[] = 'Expense booking pending: payment approval not given yet';
            }
            if (in_array($c->category, ['air', 'train', 'bus'], true) && in_array('boarding pass', $c->pendingDocuments(), true)) {
                $err[] = 'Expense booking pending: proof of travel (boarding pass / ticket) not received';
            }
            if (! $c->focus_jv_doc) {
                $err[] = 'Expense booking pending: export the vendor JV first';
            }
            $first = $pax ? $this->employee((string) array_key_first($pax)) : null;
            $entity = $first['entity'] ?? 'VNR';
            [$ledger, $sub, $lerr] = self::ledger($c, $entity);
            if ($lerr) {
                $err[] = $lerr;
            }
            $be = (string) (config('btc.focus_export.business_entity')[$entity] ?? '');
            if ($be === '') {
                $err[] = "Business Entity for {$entity} not set - Finance to confirm";
            }
            [$narr, $nerr] = self::narration($c, $pax, self::vendorState($c) ?: (string) ($first['state_name'] ?? ''));
            $err = array_merge($err, $nerr);
            $check[] = $this->checkRow($c, 'EJV', $err, $first, $entity, $ledger, $sub, $pax);
            if ($err) {
                continue;
            }
            $date = $c->fin_approved_at ?: now();
            $doc = $c->focus_ejv_doc ?: ($assign ? self::nextDoc(config('btc.focus_export.employee_series'), $date, (int) $c->company_id) : '(on export)');
            $activity = self::activity($c);
            $n = count($pax);
            $total = round((float) $c->bill_amount, 2);
            $each = floor($total / $n * 100) / 100;
            $i = 0;
            foreach ($pax as $code => $name) {
                $i++;
                $m = $this->employee((string) $code);
                $amt = $i === $n ? round($total - $each * ($n - 1), 2) : $each;   // equals the JV debit of this employee
                $rows[] = [$doc, self::xlDate($date), Carbon::parse($date)->format('H:i:s'), self::employeeAccount((string) $code), $be, $narr, '', '', 'N/A',
                    $m['department'], $m['location'], $activity, 'N/A', $m['state'], $m['region'], $m['business_unit'], $sub, $ledger, $amt, $c->case_no.' / '.$c->focus_jv_doc];
            }
            if ($assign && ! $c->focus_ejv_doc) {
                BtcCase::whereKey($c->id)->update(['focus_ejv_doc' => $doc, 'focus_ejv_at' => now()]);
                $this->log($c, 'Focus Employee JV '.$doc.' exported');
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
            $narr = 'Being payment to '.trim((string) $c->vendor_name).' against bill no.- '.$c->bill_no.' Dt.- '.self::dmy($c->bill_date).' ('.$c->case_no.', '.$c->focus_jv_doc.')'
                .' for '.trim($c->emp_name.' '.self::employeeCodeE((string) $c->emp_code));
            $rows[] = [$doc, self::xlDate($p->paid_on), Carbon::parse($p->created_at ?? now())->format('H:i:s'), config('btc.focus_export.cash_bank_ac'), '', mb_substr($narr, 0, 500),
                (string) $p->utr_number, strtoupper((string) $p->mode), self::activity($c), 'N/A',
                $m['region'] ?? 'N/A', 'N/A', 'N/A', $be, $sub, $m['department'] ?? 'N/A', config('btc.focus_export.pmt_category'), $m['business_unit'] ?? 'N/A',
                $m['location'] ?? 'N/A', $m['state'] ?? 'N/A', strtoupper((string) $c->vendor_code), (float) $p->amount, $c->case_no, (string) $p->bank_ref, '', '', ''];
            if ($assign && ! $p->focus_doc) {
                DB::table('btc_payments')->where('id', $p->id)->update(['focus_doc' => $doc, 'focus_exported_at' => now()]);
                $this->log($c, 'Focus bank payment '.$doc.' exported (UTR '.$p->utr_number.')');
            }
        }

        return ['rows' => $rows, 'check' => $check];
    }

    private function checkRow(BtcCase $c, string $type, array $err, ?array $m, string $entity, $ledger, $sub, array $pax): array
    {
        return ['type' => $type, 'case' => $c->case_no, 'result' => $err ? 'Fail' : 'Pass', 'problems' => implode('; ', $err),
            'employee' => implode(', ', array_map(fn ($k, $v) => $k.' '.$v, array_keys($pax), $pax)), 'entity' => $entity,
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
