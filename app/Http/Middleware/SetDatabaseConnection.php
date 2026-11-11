<?php

namespace App\Http\Middleware;

use App\Helpers\DatabaseSwitcher;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Session;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\Response;

class SetDatabaseConnection
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        // Only set database connections if user is authenticated and has a company session
        if (Session::has('company_id') && Session::has('authenticated')) {
            $companyId = Session::get('company_id');
            
            // Force refresh database connections
            DatabaseSwitcher::setCompanyConnections($companyId);
            
            // Clear any cached model instances that might have old connections
            if (class_exists('\Illuminate\Database\Eloquent\Model')) {
                // Clear the connection resolver cache
                DB::purge();
            }
        }

        return $next($request);
    }
}