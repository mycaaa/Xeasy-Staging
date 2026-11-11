<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TrackingSession extends Model
{
    protected $connection = 'tracking';
    protected $table = 'tracking_sessions';
    protected $primaryKey = 'id';

    protected $fillable = [
        'employee_id',
        'tracking_date',
        'started_at',
        'ended_at',
        'start_latitude',
        'start_longitude',
        'end_latitude',
        'end_longitude',
        'total_distance_km',
        'moving_seconds',
        'stopped_seconds',
        'unclassified_seconds',
        'locations_count',
        'valid_locations_count',
        'stops_count',
        'status',
        'ended_by',
        'processed_at',
        'processing_error',
        'start_battery',
        'end_battery',
        'start_accuracy',
        'end_accuracy',
        'device_info',
    ];

    protected $casts = [
        'tracking_date' => 'date',
        'started_at' => 'datetime',
        'ended_at' => 'datetime',
        'processed_at' => 'datetime',
        'total_distance_km' => 'float',
        'start_latitude' => 'float',
        'start_longitude' => 'float',
        'end_latitude' => 'float',
        'end_longitude' => 'float',
    ];

    public function locations()
    {
        return $this->hasMany(TrackingLocation::class, 'tracking_session_id', 'id');
    }

    public function stops()
    {
        return $this->hasMany(TrackingStop::class, 'tracking_session_id', 'id');
    }
}
