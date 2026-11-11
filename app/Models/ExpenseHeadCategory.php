<?php

namespace App\Models;

use App\Traits\CompanyDatabaseConnection;
use Illuminate\Database\Eloquent\Model;

class ExpenseHeadCategory extends Model
{
    use CompanyDatabaseConnection;

    protected $table = 'adv_expense_head_category';
    protected $primaryKey = 'id';
    protected $connection = null;
    protected $connectionType = 'expense';
    public $timestamps = true;
    protected $fillable = [
        'expense_head_name',
        'short_code',
        'field_type',
        'has_file',
        'file_required',
        'status',
    ];


    protected $casts = [
        'has_file' => 'boolean',
        'file_required' => 'boolean',
        'status' => 'boolean',
    ];
}