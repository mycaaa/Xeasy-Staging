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
        'alias_of',
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
        return self::listed()->where('city_category', $category)->orderBy('city_name')->get();
    }

    // ── CITY-03 (13 Nov 2026): misspelled names grouped under the correct city ───────────────────────────

    public static function hasAliases(): bool
    {
        static $has = null;
        return $has ??= \Illuminate\Support\Facades\Schema::connection((new self())->getConnectionName())->hasColumn('city_category', 'alias_of');
    }

    /** Rows shown in drop-downs: the correct cities only (grouped misspellings hidden). */
    public function scopeListed($query)
    {
        return self::hasAliases() ? $query->whereNull('alias_of') : $query;
    }

    /** The city a (possibly misspelled) row stands for. */
    public static function canonicalId($ccid)
    {
        if (! $ccid || ! self::hasAliases()) {
            return $ccid;
        }
        $alias = self::where('ccid', (int) $ccid)->value('alias_of');

        return $alias ?: $ccid;
    }

    /** Drop-down rows with the names grouped under each (searchable, and used to pre-select old claims). */
    public static function listWithAliases($category = null)
    {
        $q = self::listed()->orderBy('city_name');
        if ($category) {
            $q->where('city_category', $category);
        }
        $cities = $q->get();
        if (self::hasAliases()) {
            $aliases = self::whereNotNull('alias_of')->get(['ccid', 'city_name', 'alias_of'])->groupBy('alias_of');
            $cities->each(function ($c) use ($aliases) {
                $g = $aliases->get($c->ccid);
                $c->setAttribute('aliases', $g ? $g->pluck('city_name')->map(fn ($n) => trim($n))->values()->all() : []);
                $c->setAttribute('alias_ids', $g ? $g->pluck('ccid')->values()->all() : []);
            });
        }

        return $cities;
    }
}
