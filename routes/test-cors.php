<?php

use Illuminate\Support\Facades\Route;

// Simple CORS test route
Route::get('/test-cors', function () {
    return response()->json([
        'success' => true,
        'message' => 'CORS is working!',
        'timestamp' => now(),
        'origin' => request()->header('Origin'),
        'user_agent' => request()->header('User-Agent')
    ]);
})->middleware(['advance.cors']);

Route::post('/test-cors-post', function () {
    return response()->json([
        'success' => true,
        'message' => 'CORS POST is working!',
        'data' => request()->all(),
        'timestamp' => now()
    ]);
})->middleware(['advance.cors']);