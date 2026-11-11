<?php

namespace App\Support;

use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

/**
 * LV-C05b (6 Nov): one maker-checker rule for the payment chain. The person who did the previous
 * step (finance approval of an advance, release of a payment) may not do the next one (release,
 * bank/UTR confirmation). Same switches as the expense-payment release check:
 * payment_controls.maker_checker (on/off) and payment_controls.maker_checker_mode (enforce|log).
 * Super Admin is exempt. Returns true when the action must be refused.
 */
class MakerChecker
{
    public static function blocks($makerId, $checkerId, string $what, array $context = []): bool
    {
        $maker = (int) $makerId;
        $checker = (int) ($checkerId ?: Auth::id());
        if (! config('payment_controls.maker_checker', true) || $maker <= 0 || $checker <= 0 || $maker !== $checker) {
            return false;
        }
        $user = Auth::user();
        if (function_exists('isSuperAdmin') ? isSuperAdmin($user) : ($user && method_exists($user, 'hasRole') && ($user->hasRole('Super Admin') || $user->hasRole('super_admin')))) {
            return false;
        }
        $ctx = ['what' => $what, 'user' => $checker] + $context;
        if (config('payment_controls.maker_checker_mode', 'enforce') === 'log') {
            Log::warning('Maker-checker (log only): same user did both steps', $ctx);

            return false;
        }
        Log::warning('Maker-checker refused: same user did both steps', $ctx);

        return true;
    }
}
