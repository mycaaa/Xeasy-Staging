<?php

namespace App\Services\Reports;

use Illuminate\Support\Facades\Cache;

class ReportCacheService
{
    private const TTL_MINUTES = 15; // Cache for 15 minutes

    /**
     * Get cached report data or generate fresh
     */
    public static function remember(string $reportKey, array $filters, callable $callback, int $ttl = null): mixed
    {
        $cacheKey = self::buildCacheKey($reportKey, $filters);
        $ttl = $ttl ?? self::TTL_MINUTES;

        return Cache::remember($cacheKey, now()->addMinutes($ttl), $callback);
    }

    /**
     * Invalidate cache for a specific report
     */
    public static function forget(string $reportKey, array $filters = []): void
    {
        if (empty($filters)) {
            // Clear all cache keys matching the pattern
            Cache::forget($reportKey);
        } else {
            $cacheKey = self::buildCacheKey($reportKey, $filters);
            Cache::forget($cacheKey);
        }
    }

    /**
     * Invalidate all report caches for current company/year
     */
    public static function flushAll(): void
    {
        $prefix = 'report_' . session('company_id', 1) . '_' . \App\Support\CurrentYear::id();
        // Since Laravel's file/database cache doesn't support tag-based clearing easily,
        // we'll use a version key approach
        $versionKey = $prefix . '_version';
        Cache::increment($versionKey);
    }

    /**
     * Build a unique cache key from report name and filters
     */
    private static function buildCacheKey(string $reportKey, array $filters): string
    {
        $companyId = session('company_id', 1);
        $yearId = \App\Support\CurrentYear::id();
        $version = Cache::get("report_{$companyId}_{$yearId}_version", 1);

        $filterHash = md5(json_encode($filters));
        return "report_{$companyId}_{$yearId}_v{$version}_{$reportKey}_{$filterHash}";
    }
}
