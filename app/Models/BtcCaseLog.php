<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class BtcCaseLog extends Model
{
    protected $table = 'btc_case_logs';

    public $timestamps = false;

    protected $guarded = ['id'];
}
