<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CoreVerticalFunctionMapping extends Model
{
    protected $connection = 'hrims';          
    protected $table = 'core_vertical_function_mapping';
    protected $primaryKey = 'id';
    public $incrementing = false;             
    protected $keyType = 'string';
    public $timestamps = false;               

    protected $fillable = [
        'id',
        'org_function_id',
        'vertical_id',
        'name',
        'effective_from',
        'effective_to',
    ];
}
