<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** BTC-01: one Bill To Company case (requirement → bill → verification → payment → closure). */
class BtcCase extends Model
{
    protected $table = 'btc_cases';

    protected $guarded = ['id'];

    protected $casts = [
        'service_from' => 'date', 'service_to' => 'date', 'bill_date' => 'date', 'travel_date' => 'date',
        'advance_paid_on' => 'date', 'original_received_on' => 'date', 'boarding_pass_received_on' => 'date', 'paid_on' => 'date',
        'manager_action_at' => 'datetime', 'bill_submitted_at' => 'datetime', 'dms_at' => 'datetime', 'finance_at' => 'datetime',
        'checker_at' => 'datetime', 'treasury_at' => 'datetime', 'dept_approved_at' => 'datetime', 'closed_at' => 'datetime',
        'focus_exported_at' => 'datetime',
        'new_vendor' => 'boolean', 'original_required' => 'boolean', 'boarding_pass_required' => 'boolean', 'duplicate_checked' => 'boolean',
    ];

    public function files()
    {
        return $this->hasMany(BtcCaseFile::class, 'btc_case_id');
    }

    public function logs()
    {
        return $this->hasMany(BtcCaseLog::class, 'btc_case_id')->orderByDesc('id');
    }

    public function statusLabel(): string
    {
        return config("btc.statuses.{$this->status}", $this->status);
    }

    public function categoryLabel(): string
    {
        return config("btc.categories.{$this->category}.label", $this->category);
    }

    /** Things still open after payment (closure is blocked until they are in). */
    public function pendingDocuments(): array
    {
        $p = [];
        if ($this->original_required && ! $this->original_received_on) {
            $p[] = 'original bill';
        }
        if ($this->boarding_pass_required && ! $this->boarding_pass_received_on) {
            $p[] = 'boarding pass';
        }

        return $p;
    }
}
