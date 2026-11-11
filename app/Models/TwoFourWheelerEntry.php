<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TwoFourWheelerEntry extends Model
{
    protected $primaryKey = 'WheelId';
    public $timestamps = false;


    protected $fillable = [
        'ExpId',
        'did',
        'VehicleReg',
        'JourneyStartDt',
        'JourneyEndDt',
        'DistTraOpen',
        'DistTraClose',
        'Totalkm',
        'Rate_PerKm',
        'FilledTAmt',
    ];

    public static function tableName(int $yearId): string
    {
        return "y{$yearId}_24_wheeler_entry";
    }

    public function __construct(array $attributes = [])
    {
        parent::__construct($attributes);

        $yearId = session('year_id');
        $this->table = "y{$yearId}_24_wheeler_entry";
    }
}
