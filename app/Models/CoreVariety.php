<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class CoreVariety extends Model {
    protected $connection = 'hrims';
    protected $table = 'core_varieties';
    protected $primaryKey = 'id';
    public $incrementing = false;
    public $timestamps = false;
    protected $fillable = ['id', 'crop_id', 'variety_name', 'variety_code', 'numeric_code', 'category_id', 'is_active', 'effective_date', ];
    public function crop() {
        return $this->belongsTo(CoreCrop::class, 'crop_id', 'id');
    }
    public static function getByCrops($cropIds, $onlyActive = true) {
        if (is_string($cropIds)) {
            $cropIds = explode(',', $cropIds);
        }
        $query = self::whereIn('crop_id', $cropIds);
        if ($onlyActive) {
            $query->where('is_active', 1);
        }
        return $query->get();
    }
}
