<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class LogActivity
{
    /**
     * Handle an incoming request and log the activity to the existing activity_log table.
     */
    public function handle(Request $request, Closure $next)
    {
        $response = $next($request);

        if (!Auth::check()) {
            return $response;
        }

        // Skip logging for activity-log pages and static assets
        $skipPaths = ['activity-logs', 'activity-log-data', 'activity-log-module', 'activity-log-user', 'debugbar'];
        foreach ($skipPaths as $path) {
            if (str_contains($request->path(), $path)) {
                return $response;
            }
        }

        // Only log meaningful requests — skip AJAX GET (data fetches)
        $method = $request->method();
        $isAjax = $request->ajax() || $request->wantsJson();

        // Skip only GET AJAX requests (data fetches) — log all POST/PUT/DELETE regardless
        if ($method === 'GET' && $isAjax) {
            return $response;
        }
        // D-07: list/table fetches sent as POST (DataTables 'draw', *.data / *.list routes) are reads,
        // not business events; they made up most of the 1.6 million rows
        $routeName = (string) ($request->route()?->getName() ?? '');
        if ($request->has('draw')
            || preg_match('/(^|[._-])(get|show|view|detail|details|data|list|load|search|fetch|datatable|ajax|count|summary)([._-]|$)/i', $routeName)
            || preg_match('#(/(get|load|fetch|search)[-_]|datatable|/list$|/data$|/details?$)#i', $request->path())) {
            return $response;
        }

        // Skip GET page views that are just loading pages (optional: remove this to log all page views)
        // We keep page view logging for audit trail

        try {
            $user = Auth::user();
            $statusCode = method_exists($response, 'getStatusCode') ? $response->getStatusCode() : 200;

            // Determine event from HTTP method
            $event = match ($method) {
                'POST' => 'created',
                'PUT', 'PATCH' => 'updated',
                'DELETE' => 'deleted',
                default => 'viewed',
            };

            $description = $this->getDescription($request, $event);
            $module = $this->getModule($request);

            // Sanitize request data (remove sensitive fields)
            $requestData = $request->except(['password', 'password_confirmation', '_token', 'body_html', 'tokenid', 'token', 'api_key', 'otp', 'emppass', 'EmpPass', 'current_password', 'new_password']); // D-07: no secrets in the log
            if (!empty($requestData)) {
                $requestData = array_slice($requestData, 0, 20);
            }

            $properties = [
                'ip_address' => $request->ip(),
                'user_agent' => substr($request->userAgent() ?? '', 0, 300),
                'method' => $method,
                'url' => $request->fullUrl(),
                'route_name' => $request->route() ? $request->route()->getName() : null,
                'status_code' => $statusCode,
                'module' => $module,
                'request_data' => $requestData,
            ];

            DB::table('activity_log')->insert([
                'log_name' => $module,
                'description' => $description,
                'subject_type' => null,
                'subject_id' => null,
                'event' => $event,
                'causer_type' => 'App\\Models\\User',
                'causer_id' => $user->id,
                'properties' => json_encode($properties),
                'batch_uuid' => null,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        } catch (\Exception $e) {
            \Log::error('Activity log middleware error: ' . $e->getMessage());
        }

        return $response;
    }

    private function getDescription(Request $request, string $event): string
    {
        $routeName = $request->route() ? $request->route()->getName() : '';
        $path = $request->path();

        if ($routeName) {
            $parts = explode('.', $routeName);
            $resource = ucfirst(str_replace(['-', '_'], ' ', $parts[0] ?? ''));
            $action = ucfirst($parts[1] ?? $event);
            return "{$action} {$resource}";
        }

        $segments = array_filter(explode('/', $path));
        $resource = ucfirst(str_replace(['-', '_'], ' ', end($segments) ?: $path));

        return match ($event) {
            'created' => "Created {$resource}",
            'updated' => "Updated {$resource}",
            'deleted' => "Deleted {$resource}",
            default => "Viewed {$resource}",
        };
    }

    private function getModule(Request $request): string
    {
        $path = $request->path();
        $segments = array_filter(explode('/', $path));
        $first = reset($segments) ?: 'general';

        $moduleMap = [
            'employee' => 'Employee Management',
            'users' => 'User Management',
            'roles' => 'Role Management',
            'permissions' => 'Permission Management',
            'settings' => 'Settings',
            'claim' => 'Claims',
            'claims' => 'Claims',
            'expense' => 'Expense',
            'verifier' => 'Verification',
            'approver' => 'Approval',
            'finance' => 'Finance',
            'payment' => 'Payments',
            'reports' => 'Reports',
            'email-templates' => 'Email Templates',
            'email-notification' => 'Email Notifications',
            'advance' => 'Advance Requests',
            'home' => 'Dashboard',
            'menu' => 'Menu Management',
            'rate' => 'Rate Management',
            'vendor' => 'Vendor Management',
            'mediator' => 'Mediator',
            'admin' => 'Admin',
        ];

        return $moduleMap[$first] ?? ucfirst(str_replace(['-', '_'], ' ', $first));
    }
}
