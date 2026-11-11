<?php

namespace App\Services\Claims;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * GPS-ODO-01 (7 Nov 2026): store where the phone was when the odometer photo was taken.
 *
 * The app already sends the location with every opening and closing upload
 * (opening_latitude / opening_lat / latitude ... and the same for closing), but nothing saved
 * the closing point and the Laravel API saved neither. Verifiers can now see both points on a
 * map and the straight-line distance between them next to the km claimed.
 *
 * A point is saved only when it is a real coordinate (numbers, inside -90..90 / -180..180,
 * not 0,0). No location sent = nothing changes, so old app versions keep working.
 */
class OdometerGps
{
    /** @return array{0: float, 1: float}|null */
    public static function read(Request $request, string $kind): ?array
    {
        $lat = self::first($request, [$kind.'_latitude', $kind.'_lat', 'latitude', 'lat']);
        $lng = self::first($request, [$kind.'_longitude', $kind.'_lng', 'longitude', 'lng', 'long']);

        return self::valid($lat, $lng) ? [$lat, $lng] : null;
    }

    public static function valid(?float $lat, ?float $lng): bool
    {
        return $lat !== null && $lng !== null && abs($lat) <= 90 && abs($lng) <= 180 && ! ($lat == 0.0 && $lng == 0.0);
    }

    /** Save the point(s) on the claim; skipped quietly when the table has no GPS columns. */
    public static function save(string $conn, string $table, int $expId, Request $request, array $kinds): int
    {
        if ($expId <= 0 || ! self::hasColumns($conn, $table)) {
            return 0;
        }
        $set = [];
        foreach ($kinds as $kind) {
            if ($p = self::read($request, $kind)) {
                $set[$kind.'_lat'] = round($p[0], 8);
                $set[$kind.'_lng'] = round($p[1], 8);
            }
        }

        return $set ? DB::connection($conn)->table($table)->where('ExpId', $expId)->update($set) : 0;
    }

    /** Straight-line distance in km between the opening and closing points (haversine). */
    public static function distanceKm($lat1, $lng1, $lat2, $lng2): ?float
    {
        foreach ([$lat1, $lng1, $lat2, $lng2] as $v) {
            if ($v === null || $v === '' || ! is_numeric($v)) {
                return null;
            }
        }
        if (! self::valid((float) $lat1, (float) $lng1) || ! self::valid((float) $lat2, (float) $lng2)) {
            return null;
        }
        $r = 6371.0;
        $dLat = deg2rad($lat2 - $lat1);
        $dLng = deg2rad($lng2 - $lng1);
        $a = sin($dLat / 2) ** 2 + cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * sin($dLng / 2) ** 2;

        return round($r * 2 * atan2(sqrt($a), sqrt(1 - $a)), 1);
    }

    public static function mapUrl($lat, $lng): string
    {
        return 'https://www.google.com/maps?q='.rawurlencode($lat.','.$lng);
    }

    private static function hasColumns(string $conn, string $table): bool
    {
        static $cache = [];
        $key = $conn.'|'.$table;

        return $cache[$key] ??= Schema::connection($conn)->hasColumns($table, ['opening_lat', 'opening_lng', 'closing_lat', 'closing_lng']);
    }

    private static function first(Request $request, array $keys): ?float
    {
        foreach ($keys as $k) {
            $v = $request->input($k);
            if ($v !== null && $v !== '' && is_numeric($v)) {
                return (float) $v;
            }
        }

        return null;
    }
}
