<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use PDO;

/**
 * Middleware that enables PDO::ATTR_STRINGIFY_FETCHES for mobile API routes only.
 * This makes all database values return as strings (matching legacy mysqli_fetch_assoc behavior)
 * without affecting the web dashboard which needs proper types for Carbon/Eloquent.
 */
class StringifyDatabaseFetches
{
    public function handle(Request $request, Closure $next)
    {
        // Set STRINGIFY_FETCHES on all active connections
        $connections = ['expense', 'hrims', 'expense_nursery', 'expense_vvnr'];

        foreach ($connections as $connName) {
            try {
                $pdo = DB::connection($connName)->getPdo();
                $pdo->setAttribute(PDO::ATTR_STRINGIFY_FETCHES, true);
            } catch (\Exception $e) {
                // Connection might not be established yet, skip
            }
        }

        return $next($request);
    }
}
