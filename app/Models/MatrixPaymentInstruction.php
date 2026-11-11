<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MatrixPaymentInstruction extends Model
{
    protected $table = 'matrix_payment_instructions';

    protected $guarded = ['id'];

    protected $casts = [
        'amount' => 'decimal:2',
        'instructed_at' => 'datetime',
        'paid_on' => 'date',
        'last_synced_at' => 'datetime',
        'focus_payload' => 'array',
        'request_payload' => 'array',
        'response_payload' => 'array',
    ];

    // Status Constants
    const STATUS_CREATED = 'created';
    const STATUS_PENDING_APPROVAL = 'pending_approval';
    const STATUS_APPROVED = 'approved';
    const STATUS_BATCHED = 'batched';
    const STATUS_RELEASED = 'released';
    const STATUS_ON_HOLD = 'on_hold';
    const STATUS_PAID = 'paid';
    const STATUS_FAILED = 'failed';
    const STATUS_REJECTED = 'rejected';
    const STATUS_CANCELLED = 'cancelled';

    /**
     * Scope query to active in-progress payments
     */
    public function scopeInProgress($query)
    {
        return $query->whereIn('matrix_status', [
            self::STATUS_CREATED,
            self::STATUS_PENDING_APPROVAL,
            self::STATUS_APPROVED,
            self::STATUS_BATCHED,
            self::STATUS_RELEASED,
            self::STATUS_ON_HOLD,
        ]);
    }
}
