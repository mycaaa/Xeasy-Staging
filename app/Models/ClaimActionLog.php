<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ClaimActionLog extends Model
{
    public $timestamps = false;

    protected $table = 'claim_action_logs';

    protected $fillable = [
        'company_id',
        'year_id',
        'exp_id',
        'employee_id',
        'claim_month',
        'action',
        'action_by_role',
        'action_by',
        'action_by_name',
        'reason',
        'from_status',
        'to_status',
        'return_count',
        'action_at',
    ];

    protected $casts = [
        'action_at' => 'datetime',
    ];

    // ── Actions ────────────────────────────────────────────────────────────
    const ACTION_RETURN        = 'return';
    const ACTION_SUBMIT        = 'submit';
    const ACTION_RESUBMIT      = 'resubmit';
    const ACTION_ALLOW_SUBMIT  = 'allow_submit';
    const ACTION_REVERT        = 'revert';

    // ── Roles ──────────────────────────────────────────────────────────────
    const ROLE_VERIFIER  = 'V';
    const ROLE_APPROVER  = 'A';
    const ROLE_ADMIN     = 'AD';
    const ROLE_EMPLOYEE  = 'E';

    /**
     * Get the cumulative return count for a given employee + month + year.
     */
    public static function returnCount(int $employeeId, int $month, int $yearId, ?int $expId = null): int
    {
        $query = static::where('employee_id', $employeeId)
            ->where('claim_month', $month)
            ->where('year_id', $yearId)
            ->where('action', self::ACTION_RETURN);

        if ($expId !== null) {
            $query->where('exp_id', $expId);
        }

        return $query->count();
    }
}
