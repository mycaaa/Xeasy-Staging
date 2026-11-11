<?php

namespace App\Services\Claims;

use App\Support\RecordAccess;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

/**
 * B-01 / L-01 (audit, 6 Nov 2026): one server-side gate for claim status changes.
 *
 * Call guard() BEFORE the update that changes y{year}_expenseclaims.ClaimStatus. It:
 *   1. reads the claims' current status and owner;
 *   2. checks config('claim_transitions') for the company's process: may this role move a claim
 *      from that status to the new one;
 *   3. checks the actor holds the role: own claim (claimant), in the claimant's reporting chain or
 *      approval history or an approver permission (approver), a staff permission (punching,
 *      verifier, mediator, finance), or a server job (system);
 *   4. writes one claim_amount_change_logs row per claim (field ClaimStatus, old -> new, who,
 *      role, mode, source, remark) - the central change history (B-02 / B-03 / LV-C07).
 * A refused change throws ClaimTransitionDenied (or is only logged when enforce = false).
 * Before, each screen and mobile route set ClaimStatus on its own, with no check of the current
 * status or of who was acting (e.g. mobile "approved" approved any claim id for any caller).
 */
class ClaimTransition
{
    private static array $logTable = [];

    /**
     * @param string|ConnectionInterface $conn    expense connection (name or instance)
     * @param int|int[]|callable          $claims  ExpId(s), or fn(Builder $q) that scopes the claims table
     * @param string                      $role    claimant | approver | punching | verifier | mediator | finance | system
     * @return int[] ExpIds that may be changed (all of them when enforcing; refused ones throw)
     */
    public static function guard($conn, int $yearId, $claims, string $to, string $role, string $source,
        ?string $remark = null, string $mode = 'manual', ?int $actorEmp = null, ?int $companyId = null): array
    {
        $db = $conn instanceof ConnectionInterface ? $conn : DB::connection($conn);
        $table = "y{$yearId}_expenseclaims";
        // RET-01: archived years are read-only
        \App\Services\Retention\RecordRetention::assertWritable($db->getName(), $yearId, 'A claim status change');
        $q = $db->table($table);
        if (is_callable($claims)) {
            $claims($q);
        } else {
            $q->whereIn('ExpId', array_map('intval', (array) $claims));
        }
        $rows = $q->get(['ExpId', 'ClaimStatus', 'CrBy', 'ClaimId', 'odomtr_opening', 'odomtr_closing', 'FilledBy']);
        if ($rows->isEmpty()) {
            return [];
        }

        $companyId = $companyId ?: (int) (session('company_id') ?: self::companyOf($db->getName()));
        $process = config("claim_transitions.process.{$companyId}", 'vnr');
        $allowed = config("claim_transitions.transitions.{$process}.{$to}", []);
        $user = auth()->user();
        $actorEmp = $actorEmp ?: (int) (request()?->attributes->get('api_auth_empid') ?: RecordAccess::selfId($user));
        $enforce = (bool) config('claim_transitions.enforce', true);

        $dupConflicts = ($to === 'Approved' && config('odo_duplicates.hold_before_approval', true))
            ? DuplicateOdometer::conflicts($db, $yearId, $rows) : [];

        // KM-CAP-01: Breeding running km above the monthly limit waits for the HOD's approval
        $kmHolds = ($to === 'Approved') ? \App\Services\ClaimRules\KmCapApproval::blocking($db, $yearId, $rows) : [];

        $refused = [];
        $ok = [];
        foreach ($rows as $r) {
            $from = self::norm($r->ClaimStatus);
            $why = null;
            if (! array_key_exists($role, $allowed)) {
                $why = "role {$role} may not set {$to}";
            } elseif (! in_array('*', $allowed[$role], true) && ! in_array($from, $allowed[$role], true)) {
                $why = "{$from} -> {$to} not allowed for {$role}";
            } elseif (! self::actorHolds($role, $user, $actorEmp, (int) $r->CrBy)) {
                $why = "actor {$actorEmp} is not {$role} for employee {$r->CrBy}";
            }
            if ($why !== null && $from === $to) {
                $why = null; // same status again (re-save): not a transition
            }
            // ODO-DUP-02: possible duplicate odometer readings wait for the verifier's review
            if ($why === null && $to === 'Approved' && $from !== $to && isset($dupConflicts[(int) $r->ExpId])) {
                $why = 'possible duplicate odometer readings with ExpId '.implode(', ', $dupConflicts[(int) $r->ExpId]).' - the verifier has to deactivate one or mark it "not a duplicate" first';
            }
            // SOD-01 (10 Nov 2026): whoever keyed (punched) a claim for someone else may not also verify it
            if ($why === null && $to === 'Verified' && $from !== $to && $actorEmp > 0 && (int) ($r->FilledBy ?? 0) === $actorEmp && (int) $r->CrBy !== $actorEmp) {
                $why = 'you keyed this claim (data punching); another verifier has to verify it';
            }
            // SOD-02 (11 Nov 2026): nobody verifies or approves their own claim (seen on staging: a claimant verified their own)
            if ($why === null && in_array($to, ['Verified', 'Approved'], true) && $from !== $to && $actorEmp > 0 && (int) $r->CrBy === $actorEmp
                && config('claim_rules.sod.block_own_claim', true)) {
                $why = $to === 'Verified' ? 'this is your own claim; another verifier has to verify it' : 'this is your own claim; your approver has to approve it';
            }
            if ($why === null && $to === 'Approved' && $from !== $to && isset($kmHolds[(int) $r->ExpId])) {
                $why = $kmHolds[(int) $r->ExpId] === 'rejected'
                    ? 'the HOD did not approve the running km above the monthly limit - the verifier has to correct or deactivate the claim'
                    : 'running km above the monthly limit is waiting for the HOD\'s approval (Km limit approvals)';
            }
            if ($why !== null) {
                $refused[] = ['exp_id' => (int) $r->ExpId, 'from' => $from, 'why' => $why];
            } else {
                $ok[] = $r;
            }
        }

        if ($refused) {
            Log::warning('Claim status change refused', ['to' => $to, 'role' => $role, 'source' => $source, 'actor' => $actorEmp, 'enforced' => $enforce, 'refused' => array_slice($refused, 0, 20)]);
            if ($enforce) {
                throw new ClaimTransitionDenied($to, $refused);
            }
            $ok = $rows->all();
        }

        self::log($db, $yearId, $companyId, $ok, $to, $role, $source, $remark, $mode, $actorEmp, $user);

        return array_map(fn ($r) => (int) $r->ExpId, $ok);
    }

    /** guard() for callers that hold the table name (y{year}_expenseclaims) instead of the year. */
    public static function guardTable($conn, string $table, $claims, string $to, string $role, string $source,
        ?string $remark = null, string $mode = 'manual', ?int $actorEmp = null, ?int $companyId = null): array
    {
        if (! preg_match('/^y(\d+)_expenseclaims$/', $table, $m)) {
            throw new \InvalidArgumentException("Not a claims table: {$table}");
        }

        return self::guard($conn, (int) $m[1], $claims, $to, $role, $source, $remark, $mode, $actorEmp, $companyId);
    }

    public static function norm($status): string
    {
        $s = trim((string) $status);
        if ($s === 'Deactivat') {
            return 'Deactivate';
        }

        return $s;
    }

    private static function actorHolds(string $role, $user, int $actorEmp, int $claimant): bool
    {
        if ($user && method_exists($user, 'hasRole')) {
            try {
                if ($user->hasRole('Super Admin')) {
                    return true;
                }
            } catch (\Throwable $e) {
            }
        }
        switch ($role) {
            case 'system':
                return true; // server-side jobs (auto-verification); always logged as system
            case 'claimant':
                return $actorEmp > 0 && $actorEmp === $claimant;
            case 'approver':
                if ($actorEmp > 0 && $actorEmp !== $claimant
                    && (in_array($actorEmp, RecordAccess::managersOf($claimant), true) || RecordAccess::approvedFor($actorEmp, $claimant))) {
                    return true;
                }

                return self::hasAnyPermission($user, 'approver');
            default:
                return self::hasAnyPermission($user, $role);
        }
    }

    private static function hasAnyPermission($user, string $role): bool
    {
        if (! $user) {
            return false;
        }
        foreach ((array) config("claim_transitions.role_permissions.{$role}", []) as $perm) {
            try {
                if ($user->can($perm)) {
                    return true;
                }
            } catch (\Throwable $e) {
            }
        }

        return false;
    }

    private static function companyOf(string $connName): int
    {
        return str_contains($connName, 'vvnr') ? 11 : 1;
    }

    /** One central history row per claim; falls back to the main database when the company's lacks the table. */
    private static function log(ConnectionInterface $db, int $yearId, int $companyId, array $rows, string $to, string $role,
        string $source, ?string $remark, string $mode, int $actorEmp, $user): void
    {
        if (! $rows) {
            return;
        }
        try {
            $target = self::logTarget($db);
            $hasRole = $target[1];
            $insert = [];
            foreach ($rows as $r) {
                $from = self::norm($r->ClaimStatus);
                $row = [
                    'company_id' => $companyId,
                    'year_id' => $yearId,
                    'exp_id' => (int) $r->ExpId,
                    'employee_id' => (int) $r->CrBy,
                    'source' => mb_substr($source, 0, 40),
                    'field' => 'ClaimStatus',
                    'old_value' => $from,
                    'new_value' => $to,
                    'remark' => $remark !== null ? mb_substr($remark, 0, 500) : null,
                    'changed_by' => $user->id ?? ($actorEmp ?: null),
                    'created_at' => now(),
                ];
                if ($hasRole) {
                    $row['actor_role'] = $role;
                    $row['mode'] = $mode;
                    $row['actor_employee_id'] = $actorEmp ?: null;
                }
                $insert[] = $row;
            }
            foreach (array_chunk($insert, 500) as $chunk) {
                $target[0]->table('claim_amount_change_logs')->insert($chunk);
            }
        } catch (\Throwable $e) {
            Log::error('Claim status history not written: '.$e->getMessage(), ['to' => $to, 'source' => $source]);
        }
    }

    /** @return array{0: ConnectionInterface, 1: bool} */
    private static function logTarget(ConnectionInterface $db): array
    {
        $name = $db->getName();
        if (! isset(self::$logTable[$name])) {
            $s = Schema::connection($name);
            if ($s->hasTable('claim_amount_change_logs')) {
                self::$logTable[$name] = [$name, $s->hasColumn('claim_amount_change_logs', 'actor_role')];
            } else {
                $main = config('database.default');
                self::$logTable[$name] = [$main, Schema::connection($main)->hasColumn('claim_amount_change_logs', 'actor_role')];
            }
        }

        return [DB::connection(self::$logTable[$name][0]), self::$logTable[$name][1]];
    }
}
