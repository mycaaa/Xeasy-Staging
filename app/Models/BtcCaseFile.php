<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class BtcCaseFile extends Model
{
    protected $table = 'btc_case_files';

    public $timestamps = false;

    protected $guarded = ['id'];
}
