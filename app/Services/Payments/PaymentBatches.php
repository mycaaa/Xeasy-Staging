<?php

namespace App\Services\Payments;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * LV-C06: advance payment batches were inserted as Pending and never updated, and batch ids used
 * rand(1,999) per day against a unique key (a clash failed the whole release).
 *
 * nextId(): PAY_BATCH_YYYYMMDD_NNN, the next free number for the day.
 * refresh(): sets a batch from its advance lines: all paid (or rejected) -> Completed,
 *            some paid -> Processing, none -> Pending.
 */
class PaymentBatches
{
    public static function nextId(?string $conn = null): string
    {
        $prefix = 'PAY_BATCH_'.date('Ymd').'_';
        $db = DB::connection($conn);
        $last = $db->table('payment_batches')->where('batch_id', 'like', $prefix.'%')->lockForUpdate()->pluck('batch_id')
            ->map(fn ($b) => (int) substr($b, strlen($prefix)))->max() ?? 0;

        return $prefix.str_pad((string) ($last + 1), 3, '0', STR_PAD_LEFT);
    }

    public static function refresh(?string $batchId, ?string $conn = null): ?string
    {
        if (! $batchId) {
            return null;
        }
        $db = DB::connection($conn);
        if (! Schema::connection($conn)->hasTable('payment_batches')) {
            return null;
        }
        $rows = $db->table('advance_request_activities')->where('payment_batch_id', $batchId)
            ->selectRaw("SUM(payment_status='Paid') paid, SUM(payment_status='Rejected') rej, COUNT(*) n")->first();
        if (! $rows || ! $rows->n) {
            return null;
        }
        $status = ((int) $rows->paid + (int) $rows->rej) >= (int) $rows->n ? 'Completed' : ((int) $rows->paid > 0 ? 'Processing' : 'Pending');
        $upd = ['status' => $status, 'updated_at' => now()];
        if ($status === 'Completed') {
            $upd['processed_at'] = now();
            $upd['remarks'] = "{$rows->paid} paid, {$rows->rej} rejected (auto)";
        }
        $db->table('payment_batches')->where('batch_id', $batchId)->where('status', '!=', $status)->update($upd);

        return $status;
    }

    /** Refresh every batch that is not completed. */
    public static function refreshAll(?string $conn = null): array
    {
        $out = ['Pending' => 0, 'Processing' => 0, 'Completed' => 0];
        foreach (DB::connection($conn)->table('payment_batches')->where('status', '!=', 'Completed')->pluck('batch_id') as $b) {
            $s = self::refresh($b, $conn);
            if ($s) {
                $out[$s]++;
            }
        }

        return $out;
    }
}
