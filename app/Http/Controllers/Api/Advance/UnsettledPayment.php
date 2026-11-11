<?php

namespace App\Http\Controllers\Api\Advance;

use Illuminate\Database\Eloquent\Model;

class UnsettledPayment extends Model
{
    protected $table = 'unsettled_payments';
    
    protected $fillable = [
        'transaction_refno',
        'beneficiary_name',
        'account_no',
        'excel_amount',
        'db_amount',
        'reason',
        'status',
        'activity_id',
        'application'
    ];
}