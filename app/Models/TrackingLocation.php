<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TrackingLocation extends Model
{
    protected $connection = 'tracking';
    protected $table = 'tracking_locations';
    protected $primaryKey = 'id';

    protected $fillable = [
        'uuid',
        'tracking_session_id',
        'employee_id',
        'latitude',
        'longitude',
        'accuracy',
        'speed',
        'battery',
        'recorded_at',
        'received_at',
    ];

    protected $casts = [
        'latitude' => 'float',
        'longitude' => 'float',
        'accuracy' => 'float',
        'speed' => 'float',
        'battery' => 'integer',
        'recorded_at' => 'datetime',
        'received_at' => 'datetime',
    ];

    public function session()
    {
        return $this->belongsTo(TrackingSession::class, 'tracking_session_id', 'id');
    }
}
