<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DigiNextPaymentFile extends Model
{
    protected $table = 'digi_next_payment_file';

    protected $fillable = [
        'record_no',
        'beneficiary_account_number',
        'transaction_refno',
        'bank_name',
        'ifsc_code',
        'debit_account_number',
        'value_date',
        'amount',
        'beneficiary_name',
        'payment_type',
        'rbi_utr_reference_number',
        'currency',
        'error_description',
        'transaction_status',
        'beneficiary_aadhaar_number',
        'destination_bank',
        'credit_reference',
        'import_date',
        'import_batch_id',
        'import_file_path',
        'import_file_name',
        'is_processed',
        'match_status',
        'matched_activity_id',
        'processed_at',
        'processed_by',
        'match_remarks',
        'application',
        'year_id',
        'month',
        'company_id', // T-03
        'employee_id'
    ];

    protected $casts = [
        'value_date' => 'date',
        'import_date' => 'date',
        'amount' => 'decimal:2',
        'processed_at' => 'datetime',
        'is_processed' => 'boolean'
    ];

    public $timestamps = true;

    const CREATED_AT = 'created_at';
    const UPDATED_AT = null;
}
