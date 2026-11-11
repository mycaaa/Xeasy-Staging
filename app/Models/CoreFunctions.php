<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CoreFunctions extends Model
{
    protected $connection = 'hrims';
    protected $table = 'core_functions';
    protected $primaryKey = 'id';
    public $timestamps = false;
    protected $fillable = ['id', 'function_name', 'function_code', 'effective_date', 'is_active'];
}
