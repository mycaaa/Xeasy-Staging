<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureVvnrSession
{
    /**
     * Handle an incoming request.
     * Ensure the active session belongs to VVNR (company_id = 11).
     */
    public function handle(Request $request, Closure $next): Response
    {
        $companyId = (int) session('company_id');

        if ($companyId !== 11) {
            if ($request->expectsJson() || $request->ajax()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Access Denied: VVNR session is not active. This module can only be accessed when active company is VVNR (Company ID: 11).'
                ], 403);
            }

            abort(403, 'Access Denied: The VVNR Email Management module is strictly accessible only when the active company session is VVNR (Company ID: 11). Please switch company to VVNR from the top navigation.');
        }

        return $next($request);
    }
}
