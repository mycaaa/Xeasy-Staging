<?php

namespace App\Models;

use App\Traits\CompanyDatabaseConnection;
use Illuminate\Database\Eloquent\Model;

class ActivityCategory extends Model
{
    use CompanyDatabaseConnection;

    protected $table = 'adv_activity_categories';
    protected $primaryKey = 'id';
    protected $connection = null;
    protected $connectionType = 'expense';
    public $timestamps = true;
    protected $fillable = [
        'category_name',
        'mapped_activity',
        'description',
        'status',
    ];


    protected $casts = [
        'status' => 'boolean',
        'mapped_activity' => 'string',
    ];
}
