<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** BTC-PAY-01 (17 Nov 2026): one payment (or failed payment) of a BTC case. */
class BtcPayment extends Model
{
    protected $table = 'btc_payments';

    protected $guarded = ['id'];

    protected $casts = ['paid_on' => 'date', 'failed_at' => 'datetime', 'amount' => 'decimal:2'];
}
