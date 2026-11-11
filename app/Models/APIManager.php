<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class APIManager extends Model
{

    protected $table = 'api_list';

    protected $fillable = [
        'claim_id',
        'name',
        'endpoint',
        'method',
        'description',
        'status',
        'created_at',
        'updated_at',
        'created_by',
        'updated_by',
    ];


    public $timestamps = false;
}
