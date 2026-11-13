<?php

namespace App\Services\Claims;

use Illuminate\Database\ConnectionInterface;
use Illuminate\Support\Facades\Schema;
use RuntimeException;

/**
 * Audit fix VER-20: keeps what the employee claimed when a verifier changes a claim.
 *
 * - The first verifier change copies the claimed values into the Orig* columns
 *   (COALESCE keeps the first copy; later changes never overwrite it).
 * - Every change writes one claim_amount_change_logs row per field that changed.
 */
class ClaimChangeTracker
{
    /** claim column => original-copy column */
    private const ORIGINAL_COLUMNS = [
        'FilledTAmt' => 'OrigFilledTAmt',
        'odomtr_opening' => 'OrigOdoOpening',
        'odomtr_closing' => 'OrigOdoClosing',
        'TotKm' => 'OrigTotKm',
        'RatePerKM' => 'OrigRatePerKM',
    ];

    private static array $checked = [];

    public function __construct(private ConnectionInterface $db, private int $yearId)
    {
    }

    /** Columns read before a change, for record(). */
    public static function trackedColumns(): array
    {
        return array_merge(array_keys(self::ORIGINAL_COLUMNS), ['VerifyTAmt']);
    }

    /**
     * @param object $before  claim row read before the change (must hold trackedColumns())
     * @param array  $after   column => new value actually written
     */
    public function record(int $expId, object $before, array $after, string $source, ?string $remark, ?int $employeeId, ?int $changedBy): void
    {
        $table = "y{$this->yearId}_expenseclaims";
        $this->assertReady($table);

        $changed = [];
        foreach (self::trackedColumns() as $col) {
            if (! array_key_exists($col, $after)) {
                continue;
            }
            $old = $before->{$col} ?? null;
            $new = $after[$col];
            if ($this->same($old, $new)) {
                continue;
            }
            $changed[$col] = [$old, $new];
        }
        if ($changed === []) {
            return;
        }

        $sets = [];
        $bindings = [];
        foreach (self::ORIGINAL_COLUMNS as $col => $origCol) {
            $sets[] = "`{$origCol}` = COALESCE(`{$origCol}`, ?)";
            $bindings[] = $before->{$col} ?? null;
        }
        $sets[] = '`VerifierEditBy` = ?';
        $bindings[] = $changedBy;
        $sets[] = '`VerifierEditAt` = NOW()';
        $bindings[] = $expId;
        $this->db->update("UPDATE `{$table}` SET ".implode(', ', $sets).' WHERE ExpId = ?', $bindings);

        $rows = [];
        foreach ($changed as $col => [$old, $new]) {
            $rows[] = [
                'company_id' => session('company_id'),
                'year_id' => $this->yearId,
                'exp_id' => $expId,
                'employee_id' => $employeeId,
                'source' => $source,
                'field' => $col,
                'old_value' => $old === null ? null : (string) $old,
                'new_value' => $new === null ? null : (string) $new,
                'remark' => $remark !== null ? mb_substr($remark, 0, 500) : null,
                'changed_by' => $changedBy,
                'created_at' => now(),
            ];
        }
        $this->db->table('claim_amount_change_logs')->insert($rows);
    }

    private function same($a, $b): bool
    {
        if (is_numeric($a) && is_numeric($b)) {
            return abs((float) $a - (float) $b) < 0.005;
        }
        return (string) $a === (string) $b;
    }

    /** Fail loudly if the migration has not been run: the change must not go untracked. */
    private function assertReady(string $table): void
    {
        $key = $this->db->getName().'|'.$table;
        if (isset(self::$checked[$key])) {
            return;
        }
        $schema = Schema::connection($this->db->getName());
        if (! $schema->hasTable('claim_amount_change_logs') || ! $schema->hasColumn($table, 'OrigFilledTAmt')) {
            throw new RuntimeException('Claim change tracking is not set up on '.$this->db->getDatabaseName().'.'.$table
                .' (deploy runs migration 2026_11_13_000007; by hand: php artisan migrate --path=database/migrations/2026_11_13_000007_claim_change_tracking_all_company_dbs.php).');
        }
        self::$checked[$key] = true;
    }
}
