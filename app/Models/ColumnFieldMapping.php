<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ColumnFieldMapping extends Model
{
    protected $table = 'column_field_mapping';

    protected $fillable = [
        'claim_id',
        'temp_column',
        'input_type',
        'select_table',
        'search_column',
        'return_column',
        'punch_table',
        'punch_column',
        'condition',
        'created_by',
        'updated_by',
    ];

    public function createdBy()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function updatedBy()
    {
        return $this->belongsTo(User::class, 'updated_by');
    }
}