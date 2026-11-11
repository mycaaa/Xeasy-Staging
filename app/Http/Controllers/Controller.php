<?php

namespace App\Http\Controllers;

use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Foundation\Bus\DispatchesJobs;
use Illuminate\Foundation\Validation\ValidatesRequests;
use Illuminate\Routing\Controller as BaseController;
use Illuminate\Support\Facades\Artisan;

class Controller extends BaseController
{
    use AuthorizesRequests, DispatchesJobs, ValidatesRequests;
protected function jsonSuccess($data, $message = '')
{
    return response()->json([
        'success' => true,
        'data' => $data,
        'message' => $message
    ])
    ->header('Access-Control-Allow-Origin', \App\Support\AllowedOrigin::for())
    ->header('Access-Control-Allow-Methods', 'GET, POST, PUT, DELETE, OPTIONS')
    ->header('Access-Control-Allow-Headers', 'Content-Type, Authorization, X-Requested-With')
    ->header('Access-Control-Allow-Credentials', 'true');
}

protected function jsonError($message, $code = 500)
{
    return response()->json([
        'success' => false,
        'message' => $message
    ], $code)
    ->header('Access-Control-Allow-Origin', \App\Support\AllowedOrigin::for())
    ->header('Access-Control-Allow-Methods', 'GET, POST, PUT, DELETE, OPTIONS')
    ->header('Access-Control-Allow-Headers', 'Content-Type, Authorization, X-Requested-With')
    ->header('Access-Control-Allow-Credentials', 'true');
}

    public function clearAllCaches()
    {
        Artisan::call('cache:clear');
        Artisan::call('route:clear');
        Artisan::call('config:clear');
        Artisan::call('view:clear');
        Artisan::call('optimize:clear');

        return $this->jsonSuccess([], 'All caches cleared successfully.');
    }
}
