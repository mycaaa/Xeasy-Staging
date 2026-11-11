<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AdvanceExportLog extends Model
{
    protected $table = 'advance_export_logs';

    protected $guarded = ['id'];

    protected $casts = [
        'export_amount' => 'decimal:2',
        'total_activities_in_export' => 'integer',
        'exported_at' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime'
    ];

    /**
     * Get the activity that was exported
     */
    public function activity()
    {
        return $this->belongsTo(AdvanceRequestActivity::class, 'activity_id');
    }

    /**
     * Get the user who performed the export
     */
    public function exporter()
    {
        return $this->belongsTo(User::class, 'exported_by');
    }

    /**
     * Scope to get logs for a specific activity
     */
    public function scopeForActivity($query, $activityId)
    {
        return $query->where('activity_id', $activityId);
    }

    /**
     * Scope to get logs for a specific batch
     */
    public function scopeForBatch($query, $batchId)
    {
        return $query->where('export_batch_id', $batchId);
    }

    /**
     * Scope to get logs by date range
     */
    public function scopeDateRange($query, $fromDate, $toDate)
    {
        return $query->whereBetween('exported_at', [$fromDate, $toDate]);
    }

    /**
     * Scope to get successful exports only
     */
    public function scopeSuccessful($query)
    {
        return $query->where('export_status', 'success');
    }
}
