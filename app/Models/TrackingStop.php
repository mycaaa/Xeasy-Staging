<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TrackingStop extends Model
{
    protected $connection = 'tracking';
    protected $table = 'tracking_stops';
    protected $primaryKey = 'id';

    protected $fillable = [
        'tracking_session_id',
        'employee_id',
        'latitude',
        'longitude',
        'arrival_at',
        'departure_at',
        'duration_seconds',
        'samples_count',
        'radius_meters',
        'address',
        'geocoded_at',
        'geocode_status',
        'sequence',
    ];

    protected $casts = [
        'latitude' => 'float',
        'longitude' => 'float',
        'arrival_at' => 'datetime',
        'departure_at' => 'datetime',
        'geocoded_at' => 'datetime',
        'duration_seconds' => 'integer',
        'radius_meters' => 'integer',
        'sequence' => 'integer',
    ];

    public function session()
    {
        return $this->belongsTo(TrackingSession::class, 'tracking_session_id', 'id');
    }
}
