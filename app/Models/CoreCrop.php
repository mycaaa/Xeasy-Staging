<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CoreCrop extends Model
{
    protected $connection = 'hrims';
    protected $table = 'core_crops';
    protected $primaryKey = 'id';
    public $timestamps = false;

    protected $fillable = [
        'id',
        'vertical_id',
        'crop_name',
        'crop_code',
        'numeric_code',
        'effective_date',
        'is_active',
    ];


    public function vertical()
    {
        return $this->belongsTo(CoreVertical::class, 'vertical_id', 'id');
    }

    public static function getByVertical($verticalId)
    {
        if (empty($verticalId)) {
            return collect();
        }

        return self::where('is_active', 1)->whereRaw("FIND_IN_SET(?, vertical_id)", [$verticalId])->orderBy('crop_name')->get(['id', 'crop_name as name']);
    }
}
