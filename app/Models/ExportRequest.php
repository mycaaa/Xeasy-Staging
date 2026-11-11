<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ExportRequest extends Model
{
    protected $fillable = [
        'user_id',
        'report_type',
        'filters',
        'columns',
        'file_name',
        'download_url',
        'status',
        'error_message',
        'queued_at',
        'completed_at',
    ];

    protected $casts = [
        'filters' => 'array',
        'columns' => 'array',
        'queued_at' => 'datetime',
        'completed_at' => 'datetime',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
?>