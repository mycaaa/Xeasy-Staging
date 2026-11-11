<?php

namespace App\Support;

use Illuminate\Support\Facades\RateLimiter;

/**
 * C-06 / S-07: lock an employee code after repeated wrong passwords (web and mobile app).
 * 5 wrong passwords within 15 minutes lock the code for the rest of the 15 minutes; a correct
 * login clears the count. The route throttle (20 a minute per IP) stays in front of this.
 */
class LoginLockout
{
    public static function key(?string $code): string
    {
        return 'xeasy-login:'.strtolower(ltrim(trim((string) $code), '0'));
    }

    /** Null when the code may try, otherwise the message to show. */
    public static function blocked(?string $code): ?string
    {
        $max = (int) config('auth.lockout_attempts', 5);
        if (! RateLimiter::tooManyAttempts(self::key($code), $max)) {
            return null;
        }
        $min = max(1, (int) ceil(RateLimiter::availableIn(self::key($code)) / 60));

        return "Too many wrong passwords for this login. Try again in {$min} minute(s), or ask HR to reset the password.";
    }

    public static function failed(?string $code): void
    {
        RateLimiter::hit(self::key($code), (int) config('auth.lockout_minutes', 15) * 60);
        \Log::warning('Login failed', ['login' => self::key($code), 'ip' => request()->ip()]);
    }

    public static function succeeded(?string $code): void
    {
        RateLimiter::clear(self::key($code));
    }
}
