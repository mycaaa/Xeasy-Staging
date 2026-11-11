<?php

namespace App\Services\Claims;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

/**
 * Punch-to-claim mover (port of the old extract-data.php "autopunch_data", M-08).
 *
 * Each claim the app submits with OCR / offline punch data gets a tbl_extraction_queue row
 * (expid, claim_id, temp_punch_id). The mover copies the punched fields from
 * y{year}_temp_punch_{claim_id} into y{year}_g{cgId}_expensefilldata using column_field_mapping
 * (select fields are matched to their master by closest text, as before), and the expense lines
 * from y{year}_temp_expenses_detail into y{year}_expenseclaimsdetails.
 *
 * Before, the Laravel route answered "processed" without doing anything, so after the switch to
 * the new app nothing would have been moved. M-08 also asked for retries: a failed row is tried
 * again up to config('claim_rules.punch_queue.max_retries', 3) times, then left FAILED with the
 * error; rows stuck for more than a day are reported by run().
 */
class PunchQueueMover
{
    public function __construct(private string $conn, private int $yearId)
    {
    }

    /** Process PENDING rows (and FAILED rows with retries left). */
    public function run(?int $queueId = null, int $limit = 500): array
    {
        $db = DB::connection($this->conn);
        $max = (int) config('claim_rules.punch_queue.max_retries', 3);
        $q = $db->table('tbl_extraction_queue')
            ->when($queueId, fn ($x) => $x->where('id', $queueId))
            ->when(! $queueId, fn ($x) => $x->where(fn ($w) => $w->where('status', 'PENDING')->orWhere(fn ($f) => $f->where('status', 'FAILED')->where('retry_count', '<', $max))))
            ->orderBy('id')->limit($limit);
        $done = 0;
        $failed = 0;
        $messages = [];
        foreach ($q->get() as $row) {
            [$ok, $msg] = $this->moveOne($row);
            if ($ok) {
                $done++;
            } else {
                $failed++;
            }
            $messages[] = "queue {$row->id} (ExpId {$row->expid}): {$msg}";
        }
        $stuck = (int) $db->table('tbl_extraction_queue')->where(function ($w) use ($max) {
            $w->where(fn ($p) => $p->where('status', 'PENDING')->where('created_at', '<', now()->subDay()))
                ->orWhere(fn ($f) => $f->where('status', 'FAILED')->where('retry_count', '>=', $max));
        })->count();
        if ($stuck) {
            Log::warning("Punch queue: {$stuck} rows need attention (pending over a day or failed {$max} times)");
        }

        return ['moved' => $done, 'failed' => $failed, 'stuck' => $stuck, 'messages' => $messages];
    }

    /** @return array{0: bool, 1: string} */
    public function moveOne(object $row): array
    {
        $db = DB::connection($this->conn);
        $y = $this->yearId;
        $claimId = (int) $row->claim_id;
        $expId = (int) $row->expid;
        $punchId = (int) $row->temp_punch_id;
        try {
            if ($claimId <= 0 || $expId <= 0) {
                throw new \RuntimeException("invalid claim_id {$claimId} / ExpId {$row->expid}");
            }
            $cgId = (int) $db->table('claimtype')->where('ClaimId', $claimId)->value('cgId');
            if (! $cgId) {
                throw new \RuntimeException("no claim group for claim_id {$claimId}");
            }
            $temp = "y{$y}_temp_punch_{$claimId}";
            $target = "y{$y}_g{$cgId}_expensefilldata";
            if (! Schema::connection($this->conn)->hasTable($temp) || ! Schema::connection($this->conn)->hasTable($target)) {
                throw new \RuntimeException("table {$temp} or {$target} missing");
            }
            $maps = $db->table('column_field_mapping')->where('claim_id', $claimId)->get();
            if ($maps->isEmpty()) {
                throw new \RuntimeException("no field mapping for claim_id {$claimId}");
            }
            $tempCols = array_flip(Schema::connection($this->conn)->getColumnListing($temp));
            $targetCols = array_flip(Schema::connection($this->conn)->getColumnListing($target));
            $src = $db->table($temp)->where('id', $punchId)->first();
            if (! $src) {
                throw new \RuntimeException("no row {$punchId} in {$temp}");
            }
            $data = [];
            foreach ($maps as $m) {
                if (! isset($tempCols[$m->field_name]) || ! isset($targetCols[$m->map_with])) {
                    continue;
                }
                $v = $src->{$m->field_name} ?? '';
                if (strtolower((string) $m->input_type) === 'select' && $m->select_table && $m->search_column && $m->return_column) {
                    $match = $this->closest($m->select_table, $m->search_column, $v, $m->return_column, $m->add_condition);
                    if ($match !== null) {
                        $data[$m->map_with] = $match;
                    }
                } else {
                    $data[$m->map_with] = $v ?? '';
                }
            }

            $db->transaction(function () use ($db, $target, $expId, $data, $y, $claimId, $punchId, $row) {
                if ($data) {
                    $db->table($target)->updateOrInsert(['ExpId' => $expId], $data);
                }
                $lines = Schema::connection($this->conn)->hasTable("y{$y}_temp_expenses_detail")
                    ? $db->table("y{$y}_temp_expenses_detail")->where('claim_id', $claimId)->where('punch_id', $punchId)->get(['title', 'remark', 'amount'])
                    : collect();
                if ($lines->isNotEmpty() && ! $db->table("y{$y}_expenseclaimsdetails")->where('ExpId', $expId)->exists()) {
                    $db->table("y{$y}_expenseclaimsdetails")->insert($lines->map(fn ($l) => ['ExpId' => $expId, 'Title' => (string) $l->title, 'Amount' => (float) $l->amount, 'Remark' => (string) $l->remark])->all());
                }
                $db->table('tbl_extraction_queue')->where('id', $row->id)->update(['status' => 'COMPLETED', 'updated_at' => now(), 'last_error_message' => null]);
            });

            return [true, 'moved ('.count($data).' fields)'];
        } catch (\Throwable $e) {
            $db->table('tbl_extraction_queue')->where('id', $row->id)->update([
                'status' => 'FAILED', 'updated_at' => now(),
                'retry_count' => DB::raw('COALESCE(retry_count,0) + 1'),
                'last_error_message' => mb_substr($e->getMessage(), 0, 500),
            ]);

            return [false, 'failed: '.$e->getMessage()];
        }
    }

    /** Closest master value by text similarity (>= 50%), as the old mover did. */
    public function closest(string $table, string $searchCol, $value, string $returnCol, ?string $cond): ?string
    {
        $value = trim((string) $value);
        if ($value === '' || ! preg_match('/^[A-Za-z0-9_]+$/', $table.$searchCol.$returnCol)) {
            return null;
        }
        $parts = array_filter(array_map('trim', preg_split('/and|&/i', preg_replace('/\s*\([^)]+\)/', '', $value))));
        if (! $parts) {
            return null;
        }
        $q = DB::connection($this->conn)->table($table)->select([$searchCol, $returnCol]);
        if ($cond) {
            $q->whereRaw($cond); // from the mapping table (admin configuration), not from user input
        }
        $best = null;
        $bestPct = 0;
        foreach ($q->get() as $r) {
            $db = trim((string) ($r->{$searchCol} ?? ''));
            if ($db === '') {
                continue;
            }
            foreach ($parts as $p) {
                similar_text(strtoupper($p), strtoupper($db), $pct);
                if ($pct >= 50 && $pct > $bestPct) {
                    $bestPct = $pct;
                    $best = (string) $r->{$returnCol};
                }
            }
        }

        return $best;
    }
}
