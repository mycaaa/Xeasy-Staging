<?php

namespace App\Support;

use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * PAY-LOG-01 (audit LV-C07): one append-only history for payment and ledger writes
 * (status, amount, UTR, dates, adjustments) across VNR/VVNR, kept in the main expense DB.
 * Call snapshot() before an update and diff() after it, or record() for a single change.
 */
class PaymentChangeLog
{
    public const FIELDS = ['payment_status', 'payment_amount', 'utr_number', 'bank_reference', 'payment_date', 'Fin_PayDate', 'Fin_PayAmt',
        'Approved_Amount', 'Finance_Amount', 'payment_approved_by', 'payment_instructed_by', 'utr_exempt_reason', 'ff_utr',
        'adjusted_amount', 'remaining_amount', 'adjustment_date', 'opening_balance', 'Amount', 'amount', 'Status',
        'total_adjustment', 'other_adjustment', 'advance_payment_amount', 'ledger_balance_at_approval', 'is_focus_exported', 'is_exported',
        'payment_released_by', 'finance_approved_amount', 'finance_status', 'payment_hold_remark', 'hold_remark',
        'debit', 'credit', 'balance', 'transaction_type', 'remarks', 'dr', 'cr', 'fixed_imprest_amt_1', 'fixed_imprest_amt_2',
        'advance_amt_1', 'advance_amt_2', 'advance_amt_3', 'advance_amt_4', 'ff_voucher_no'];

    /** Rows (keyed by id) of the given table and ids, for the tracked fields that exist. */
    public static function snapshot(string $conn, string $table, array $ids): array
    {
        try {
            $ids = array_values(array_filter(array_map('intval', $ids)));
            if (! $ids) {
                return [];
            }
            $cols = array_values(array_intersect(self::FIELDS, \Illuminate\Support\Facades\Schema::connection($conn)->getColumnListing($table)));
            $extra = array_values(array_intersect(['EmployeeID', 'employee_id'], \Illuminate\Support\Facades\Schema::connection($conn)->getColumnListing($table)));

            return DB::connection($conn)->table($table)->whereIn('id', $ids)->get(array_merge(['id'], $cols, $extra))
                ->keyBy('id')->map(fn ($r) => (array) $r)->all();
        } catch (\Throwable $e) {
            Log::warning('PAY-LOG-01 snapshot failed: '.$e->getMessage());

            return [];
        }
    }

    /** Compare a snapshot with the rows now and log every changed tracked field. */
    public static function diff(string $conn, string $table, array $before, string $entity, string $source, ?string $remark = null): int
    {
        if (! $before) {
            return 0;
        }
        $after = self::snapshot($conn, $table, array_keys($before));
        $n = 0;
        foreach ($before as $id => $old) {
            $new = $after[$id] ?? [];
            foreach ($old as $field => $value) {
                if (in_array($field, ['id', 'EmployeeID', 'employee_id'], true)) {
                    continue;
                }
                $nv = $new[$field] ?? null;
                if ((string) $value !== (string) $nv) {
                    self::record($entity, $table, (int) $id, $field, $value, $nv, $source, $remark, (int) ($old['EmployeeID'] ?? $old['employee_id'] ?? 0) ?: null);
                    $n++;
                }
            }
        }

        return $n;
    }

    public static function record(string $entity, ?string $table, ?int $recordId, string $field, $old, $new, string $source, ?string $remark = null, ?int $employeeId = null): void
    {
        try {
            $yearId = null;
            if ($table && preg_match('/^y(\d+)_/', $table, $m)) {
                $yearId = (int) $m[1];
            }
            DB::table('payment_change_logs')->insert([
                'company_id' => (int) session('company_id') ?: null,
                'year_id' => $yearId ?: ((int) session('year_id') ?: null),
                'entity' => substr($entity, 0, 40),
                'table_name' => $table ? substr($table, 0, 64) : null,
                'record_id' => $recordId,
                'employee_id' => $employeeId,
                'field' => substr($field, 0, 40),
                'old_value' => $old === null ? null : substr((string) $old, 0, 120),
                'new_value' => $new === null ? null : substr((string) $new, 0, 120),
                'source' => substr($source, 0, 60),
                'remark' => $remark ? substr($remark, 0, 500) : null,
                'changed_by' => Auth::id(),
                'ip' => request()?->ip(),
                'created_at' => now(),
            ]);
        } catch (\Throwable $e) {
            Log::warning('PAY-LOG-01 not written: '.$e->getMessage());
        }
    }
}
