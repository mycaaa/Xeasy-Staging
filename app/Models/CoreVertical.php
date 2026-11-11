<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CoreVertical extends Model
{
    protected $connection = 'hrims';
    protected $table = 'core_verticals';
    protected $primaryKey = 'id';
    public $timestamps = false;
    protected $fillable = ['id', 'vertical_name', 'vertical_code', 'effective_date', 'is_active',];
    public function crops()
    {
        return $this->hasMany(CoreCrop::class, 'vertical_id', 'id')->where('is_active', 1)->orderBy('crop_name');
    }
    public static function active()
    {
        return self::where('is_active', 1)->orderBy('vertical_name')->get(['id', 'vertical_name as name']);
    }
    public static function getCrops($verticalId)
    {
        return CoreCrop::getByVertical($verticalId);
    }
}
