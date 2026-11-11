<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CompanyDbConfig extends Model
{
    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'db_config_by_company';

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'company_id',
        'db_name',
        'db_connection',
        'db_host',
        'db_port',
        'db_database',
        'db_username',
        'db_password',
        'status',
        'created_by',
        'updated_by'
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array
     */
    protected $casts = [
        'status' => 'integer',
        'db_port' => 'integer',
    ];

    /**
     * Define the relationship with the CoreCompany model.
     *
     * @return BelongsTo
     */
    public function company()
    {
        return $this->belongsTo(CoreCompany::class, 'company_id');
    }
}