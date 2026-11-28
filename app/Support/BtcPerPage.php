<?php

namespace App\Support;

use Illuminate\Http\Request;

/** BTC-GRID-01 (28 Nov 2026): rows per page chosen on the BTC lists (?per_page=), within a fixed set. */
class BtcPerPage
{
    public const CHOICES = [10, 25, 50, 100, 200];

    public static function of(?Request $request = null, int $default = 50): int
    {
        $n = (int) ($request ?? request())->input('per_page', $default);

        return in_array($n, self::CHOICES, true) ? $n : $default;
    }
}
