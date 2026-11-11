<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CityCategory extends Model
{
    use HasFactory;

    protected $table = 'city_category';

    protected $primaryKey = 'ccid';

    public $timestamps = false;

    protected $fillable = [
        'city_name',
        'city_category',
        'crby',
        'crdate',
    ];

    protected $casts = [
        'ccid' => 'integer',
        'crby' => 'integer',
        'crdate' => 'date',
        'city_name' => 'string',
        'city_category' => 'string',
    ];

    /**
     * Get audit logs for this city category
     */
    public function logs()
    {
        return $this->hasMany(CityCategoryLog::class, 'ccid', 'ccid')->orderByDesc('created_at');
    }

    /**
     * Relationship to employee who created this record
     */
    public function creator()
    {
        return $this->belongsTo(HRMEmployees::class, 'crby', 'EmployeeID');
    }

    /**
     * Get cities by category
     */
    public static function getCitiesByCategory(string $category)
    {
        return self::where('city_category', $category)->orderBy('city_name')->get();
    }
}
