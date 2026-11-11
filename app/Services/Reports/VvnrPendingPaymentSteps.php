<?php

namespace App\Services\Reports;

use App\Helpers\DatabaseSwitcher;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * REP-32 (Finance, 5 Nov): VVNR stepwise pending payment report.
 *
 * VVNR pays before it verifies: claim -> manager (RM) approval -> payment -> verification ->
 * any difference found at verification is adjusted in a later month's payment. Every submitted
 * employee-month (y{Y}_monthexpensefinal, Status = Closed) is placed at the one step it is waiting on:
 *
 *   hold        Payable with F&F / salary (hold_remark, Fin_PayRemark "Payable with ...", is_exported = 2,
 *               or utr_exempt_reason = ff_hold) and not yet settled
 *   approval    1. submitted, waiting for manager approval              (claimed amount, age from DateOfSubmit)
 *   payment     2. approved, waiting for payment                        (approved amount, age from Approved_Date)
 *   verification 3. paid, waiting for verification                      (paid amount, age from pay date)
 *   adjustment  4. verified with a difference not yet adjusted later    (verified - approved, age from Verified_Date)
 *   closed      5. nothing pending (no difference, or the difference was carried into a later month)
 *
 * "Paid" = a pay date (Fin_PayDate, else payment_date) or payment_status ConfirmedPayment, or a linked
 * F&F settlement. The payment was made on the approved amount, so the verification difference is
 * Verified_Amount - Approved_Amount (negative = paid in excess, to recover; positive = short paid).
 *
 * A later adjustment is recognised from the payment screen's own records:
 *   - expense_adjustments_json of a later month holding key "prev_month_{m}" (amount), or
 *   - the following month's payment remark "Adjusted with previous month expenses Rs. N" (older entries).
 */
class VvnrPendingPaymentSteps
{
    public const STEPS = [
        'approval' => '1. Pending manager approval',
        'payment' => '2. Approved, pending payment',
        'verification' => '3. Paid, pending verification',
        'adjustment' => '4. Verified, difference pending adjustment',
        'hold' => 'On hold (Payable with F&F / salary)',
        'closed' => '5. Closed',
    ];

    public const BUCKETS = ['0-7', '8-15', '16-30', '>30'];

    private const FY_ORDER = [4, 5, 6, 7, 8, 9, 10, 11, 12, 1, 2, 3];

    private const MONTHS = [1 => 'Jan', 2 => 'Feb', 3 => 'Mar', 4 => 'Apr', 5 => 'May', 6 => 'Jun', 7 => 'Jul', 8 => 'Aug', 9 => 'Sep', 10 => 'Oct', 11 => 'Nov', 12 => 'Dec'];

    public function __construct(private ?string $conn = null)
    {
        $this->conn = $conn ?: DatabaseSwitcher::getExpenseConnection(11);
    }

    public function connection(): string
    {
        return $this->conn;
    }

    /** The year that contains today (financialyear.Status is out of date on VVNR). */
    public function currentYearId(): ?int
    {
        $today = now('Asia/Kolkata')->toDateString();
        $id = DB::connection($this->conn)->table('financialyear')
            ->where('FromDate', '<=', $today)->where('Todate', '>=', $today)->orderByDesc('YearId')->value('YearId');

        return $id ? (int) $id : (int) DB::connection($this->conn)->table('financialyear')->max('YearId');
    }

    /**
     * @param  array{month?:int|string|null, department_id?:int|string|null, employee_id?:int|string|null, approver_id?:int|string|null, step?:string|null, include_closed?:bool}  $f
     * @return array{rows: array<int, array>, summary: array}
     */
    public function build(int $yearId, array $f = []): array
    {
        $table = "y{$yearId}_monthexpensefinal";
        $schema = Schema::connection($this->conn);
        if (! $schema->hasTable($table)) {
            return ['rows' => [], 'summary' => $this->emptySummary()];
        }
        $hasUtrReason = $schema->hasColumn($table, 'utr_exempt_reason');
        $hrims = DatabaseSwitcher::getHrimsDatabaseName();
        $today = Carbon::now('Asia/Kolkata')->startOfDay();

        $cols = ['mef.id', 'mef.EmployeeID', 'mef.Month', 'mef.Status', 'mef.payment_status', 'mef.is_exported', 'mef.Total_Claim',
            'mef.DateOfSubmit', 'mef.Claim_Amount', 'mef.Approved_Amount', 'mef.Approved_Date', 'mef.Verified_Amount', 'mef.Verified_Date',
            'mef.Fin_PayAmt', 'mef.Fin_PayDate', 'mef.payment_date', 'mef.payment_amount', 'mef.Fin_PayRemark', 'mef.hold_remark', 'mef.utr_number',
            'e.EmpCode', 'e.EmpStatus', DB::raw("TRIM(CONCAT(e.Fname, ' ', COALESCE(e.Sname, ''), ' ', e.Lname)) AS emp_name"),
            'g.DepartmentId', 'dep.department_name', 'g.RepEmployeeID',
            DB::raw("TRIM(CONCAT(rm.Fname, ' ', COALESCE(rm.Sname, ''), ' ', rm.Lname)) AS rm_name"), 'rm.EmpCode as rm_code'];
        if ($hasUtrReason) {
            array_push($cols, 'mef.utr_exempt_reason', 'mef.ff_settlement_date', 'mef.ff_settlement_amount', 'mef.ff_utr');
        }

        $q = DB::connection($this->conn)->table("{$table} as mef")
            ->leftJoin("{$hrims}.hrm_employee as e", 'e.EmployeeID', '=', 'mef.EmployeeID')
            ->leftJoin("{$hrims}.hrm_employee_general as g", 'g.EmployeeID', '=', 'mef.EmployeeID')
            ->leftJoin("{$hrims}.core_departments as dep", 'dep.id', '=', 'g.DepartmentId')
            ->leftJoin("{$hrims}.hrm_employee as rm", 'rm.EmployeeID', '=', 'g.RepEmployeeID')
            ->where('mef.YearId', $yearId)
            ->where('mef.Status', 'Closed')
            ->select($cols);
        foreach (['month' => 'mef.Month', 'department_id' => 'g.DepartmentId', 'employee_id' => 'mef.EmployeeID', 'approver_id' => 'g.RepEmployeeID'] as $k => $col) {
            if (($f[$k] ?? '') !== '' && $f[$k] !== null) {
                $q->where($col, (int) $f[$k]);
            }
        }
        $records = $q->get();

        $later = $this->laterAdjustments($yearId, $records->pluck('EmployeeID')->unique()->all());

        $rows = [];
        $summary = $this->emptySummary();
        foreach ($records as $r) {
            $row = $this->classify($r, $hasUtrReason, $later, $today);
            $summary['steps'][$row['step']]['count']++;
            $summary['steps'][$row['step']]['amount'] += $row['step_amount'];
            if ($row['step'] === 'adjustment') {
                $summary['steps']['adjustment'][$row['step_amount'] < 0 ? 'excess' : 'short'] += abs($row['step_amount']);
            }
            if ($row['step'] !== 'closed') {
                $summary['ageing'][$row['age_bucket']]['count']++;
                $summary['ageing'][$row['age_bucket']]['amount'] += abs($row['step_amount']);
            }
            if (($f['step'] ?? '') !== '' && ($f['step'] ?? null) !== $row['step']) {
                continue;
            }
            if ($row['step'] === 'closed' && empty($f['include_closed']) && ($f['step'] ?? '') !== 'closed') {
                continue;
            }
            $rows[] = $row;
        }

        // Newest month first (FY order), then the oldest waiting first inside a month
        usort($rows, fn ($a, $b) => [$b['fy_pos'], $b['age_days']] <=> [$a['fy_pos'], $a['age_days']]);

        return ['rows' => $rows, 'summary' => $summary];
    }

    private function classify(object $r, bool $hasUtrReason, array $later, Carbon $today): array
    {
        $date = fn ($v) => ($v && \App\Support\ZeroDate::has($v) && \App\Support\ZeroDate::has($v) && substr((string) $v, 0, 4) > '2000') ? Carbon::parse(substr((string) $v, 0, 10)) : null;
        $submitted = $date($r->DateOfSubmit);
        $approved = $date($r->Approved_Date);
        $verified = $date($r->Verified_Date);
        $payDate = $date($r->Fin_PayDate) ?: $date($r->payment_date);
        $payAmt = (float) ($r->Fin_PayAmt ?: $r->payment_amount);

        $reason = $hasUtrReason ? ($r->utr_exempt_reason ?? null) : null;
        $ffLinked = $hasUtrReason && ! empty($r->ff_utr);
        if ($ffLinked) {
            $payDate = $date($r->ff_settlement_date) ?: $payDate;
            $payAmt = (float) ($r->ff_settlement_amount ?? $payAmt);
        }
        $holdText = trim((string) ($r->hold_remark ?: (preg_match('/^\s*payable with/i', (string) $r->Fin_PayRemark) ? $r->Fin_PayRemark : '')));
        $onHold = ! $ffLinked && ($reason === 'ff_hold' || $holdText !== '' || (int) $r->is_exported === 2) && $payAmt <= 0;
        $paid = $ffLinked || \App\Support\Paid::is($r); // PAID-01 (ConfirmedPayment now also needs a valid UTR)

        $diff = round((float) $r->Verified_Amount - (float) $r->Approved_Amount, 2);
        $adj = $later[$r->EmployeeID][(int) $r->Month] ?? null;

        if (! $approved && (float) $r->Approved_Amount <= 0) {
            [$step, $since, $amt] = ['approval', $submitted, (float) $r->Claim_Amount];
        } elseif ($onHold) {
            [$step, $since, $amt] = ['hold', $payDate ?: $approved, (float) $r->Approved_Amount];
        } elseif (! $paid) {
            [$step, $since, $amt] = ['payment', $approved ?: $submitted, (float) $r->Approved_Amount];
        } elseif (! $verified) {
            [$step, $since, $amt] = ['verification', $payDate ?: $approved, $payAmt];
        } elseif (abs($diff) >= 1 && $adj === null) {
            [$step, $since, $amt] = ['adjustment', $verified, $diff];
        } else {
            [$step, $since, $amt] = ['closed', $verified, 0.0];
        }
        $age = $since ? max(0, (int) $since->diffInDays($today)) : null;

        $fmt = fn (?Carbon $d) => $d ? $d->format('d-m-Y') : '';
        $month = (int) $r->Month;

        return [
            'id' => (int) $r->id,
            'employee_id' => (int) $r->EmployeeID,
            'emp_code' => (string) $r->EmpCode,
            'emp_name' => (string) $r->emp_name,
            'emp_status' => $r->EmpStatus === 'A' ? 'Active' : ($r->EmpStatus ? 'Left' : ''),
            'department' => (string) ($r->department_name ?? ''),
            'rm' => trim(($r->rm_code ? $r->rm_code.' - ' : '').($r->rm_name ?? '')),
            'month' => $month,
            'month_name' => self::MONTHS[$month] ?? (string) $month,
            'fy_pos' => (int) array_search($month, self::FY_ORDER, true),
            'claims' => (int) $r->Total_Claim,
            'claimed' => (float) $r->Claim_Amount,
            'approved' => (float) $r->Approved_Amount,
            'paid' => $paid ? $payAmt : 0.0,
            'verified' => (float) $r->Verified_Amount,
            'difference' => $verified ? $diff : 0.0,
            'submitted_on' => $fmt($submitted),
            'approved_on' => $fmt($approved),
            'paid_on' => $fmt($payDate),
            'verified_on' => $fmt($verified),
            'payment_status' => (string) $r->payment_status,
            'utr' => $ffLinked ? (string) $r->ff_utr : (string) $r->utr_number,
            'step' => $step,
            'step_label' => self::STEPS[$step],
            'step_amount' => round($amt, 2),
            'age_days' => $age ?? 0,
            'age_bucket' => $this->bucket($age ?? 0),
            'note' => $this->note($step, $holdText, $reason, $ffLinked, $adj, $diff, $r),
        ];
    }

    private function note(string $step, string $holdText, ?string $reason, bool $ffLinked, ?array $adj, float $diff, object $r): string
    {
        $n = [];
        if ($step === 'hold') {
            $n[] = $holdText !== '' ? $holdText : 'Payment on hold';
        }
        if ($ffLinked) {
            $n[] = 'Settled with F&F';
        }
        if ($reason === 'da_payroll') {
            $n[] = 'DA paid through payroll';
        }
        if ($reason === 'advance_adjusted') {
            $n[] = 'Adjusted with advance';
        }
        if ($step === 'payment' && in_array($r->payment_status, ['Approved', 'InstructedPayment'], true)) {
            $n[] = $r->payment_status === 'InstructedPayment' ? 'Payment instructed to bank, not confirmed' : 'Payment approved, not instructed';
        }
        if ($step === 'adjustment') {
            $n[] = $diff < 0 ? 'Paid in excess by ₹'.number_format(-$diff, 0).': recover in next payment' : 'Short paid by ₹'.number_format($diff, 0).': pay in next payment';
        }
        if ($adj !== null) {
            $n[] = 'Adjusted in '.(self::MONTHS[$adj['month']] ?? $adj['month']).($adj['amount'] !== null ? ' ₹'.number_format($adj['amount'], 0) : '');
        }

        return implode('; ', $n);
    }

    /**
     * Months whose verification difference was taken into a later payment:
     * [EmployeeID][month] => ['month' => later month, 'amount' => ?float]
     */
    private function laterAdjustments(int $yearId, array $employeeIds): array
    {
        $out = [];
        if (! $employeeIds) {
            return $out;
        }
        $rows = collect();
        foreach ([$yearId, $yearId + 1] as $y) {
            $t = "y{$y}_monthexpensefinal";
            if (! Schema::connection($this->conn)->hasTable($t)) {
                continue;
            }
            foreach (array_chunk($employeeIds, 1000) as $chunk) {
                $rows = $rows->merge(DB::connection($this->conn)->table($t)->whereIn('EmployeeID', $chunk)
                    ->select('EmployeeID', 'Month', 'expense_adjustments_json', 'Fin_PayRemark', DB::raw("{$y} AS y"))->get());
            }
        }
        foreach ($rows as $r) {
            $json = json_decode((string) $r->expense_adjustments_json, true);
            foreach (is_array($json) ? $json : [] as $key => $v) {
                if (preg_match('/^prev_month_(\d{1,2})$/', (string) $key, $m)) {
                    $src = (int) $m[1];
                    // the adjusted month belongs to $yearId when the later row is in $yearId, or is March taken in April of the next year
                    if ((int) $r->y === $yearId || ($src === 3 && (int) $r->Month === 4)) {
                        $out[$r->EmployeeID][$src] = ['month' => (int) $r->Month, 'amount' => is_array($v) ? (float) ($v['amount'] ?? 0) : (float) $v];
                    }
                }
            }
            if (preg_match('/previous month expenses\s*Rs\.?\s*([\d,]+(?:\.\d+)?)/i', (string) $r->Fin_PayRemark, $m)) {
                $src = (int) $r->Month === 4 ? 3 : ((int) $r->Month === 1 ? 12 : (int) $r->Month - 1);
                $belongs = (int) $r->y === $yearId ? (int) $r->Month !== 4 : (int) $r->Month === 4;
                if ($belongs && ! isset($out[$r->EmployeeID][$src])) {
                    $out[$r->EmployeeID][$src] = ['month' => (int) $r->Month, 'amount' => (float) str_replace(',', '', $m[1])];
                }
            }
        }

        return $out;
    }

    private function bucket(int $days): string
    {
        return $days <= 7 ? '0-7' : ($days <= 15 ? '8-15' : ($days <= 30 ? '16-30' : '>30'));
    }

    private function emptySummary(): array
    {
        $s = ['steps' => [], 'ageing' => []];
        foreach (self::STEPS as $k => $label) {
            $s['steps'][$k] = ['label' => $label, 'count' => 0, 'amount' => 0.0] + ($k === 'adjustment' ? ['excess' => 0.0, 'short' => 0.0] : []);
        }
        foreach (self::BUCKETS as $b) {
            $s['ageing'][$b] = ['count' => 0, 'amount' => 0.0];
        }

        return $s;
    }
}
