<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TemplateVariable extends Model
{
   protected $table = 'eml_template_variables';
    protected $primaryKey = 'column_id';
    public $timestamps = true;

    protected $fillable = [
        'column_name',
        'description',
        'source_type',
        'source_table',
        'source_column',
        'join_with_table',
        'join_with_column',
        'return_column',
        'join_type',
        'join_condition',
        'display_order',
        'created_by',
        'updated_by',
        'created_at',
        'updated_at',
        'is_active'
    ];
}
