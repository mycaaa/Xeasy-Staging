<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class ReportAccessMiddleware
{
    /**
     * Roles allowed to access claim stage reports.
     * Maps role slugs to permission levels.
     */
    private const ALLOWED_ROLES = [
        'admin' => 'full',
        'super-admin' => 'full',
        'accounts' => 'full',
        'finance' => 'full',
        'manager' => 'limited',    // Can only see their team
        'hr' => 'full',
    ];

    public function handle(Request $request, Closure $next, string $level = 'full')
    {
        $user = Auth::user();

        if (!$user) {
            if ($request->expectsJson()) {
                return response()->json(['error' => 'Unauthorized'], 401);
            }
            return redirect()->route('login');
        }

        // Check if user has any of the allowed roles (using Spatie HasRoles)
        $userRoles = $user->getRoleNames()->toArray();
        $hasAccess = false;

        foreach ($userRoles as $role) {
            $roleLower = strtolower($role);
            if (isset(self::ALLOWED_ROLES[$roleLower])) {
                $accessLevel = self::ALLOWED_ROLES[$roleLower];
                if ($level === 'full' && $accessLevel === 'full') {
                    $hasAccess = true;
                    break;
                }
                if ($level === 'limited') {
                    $hasAccess = true;
                    break;
                }
            }
        }

        // Also check permission-based access
        if (!$hasAccess && $user->can('section_reports')) {
            $hasAccess = true;
        }

        if (!$hasAccess) {
            if ($request->expectsJson()) {
                return response()->json(['error' => 'Access denied. Insufficient permissions.'], 403);
            }
            abort(403, 'You do not have permission to access reports.');
        }

        return $next($request);
    }
}
