<?php

use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Session;
use App\Helpers\DatabaseSwitcher;

// Temporary debug route to check session and database connections
Route::get('/debug/session', function () {
    $data = [
        'session_data' => [
            'company_id' => Session::get('company_id'),
            'company_name' => Session::get('company_name'),
            'company' => Session::get('company'),
            'year_id' => Session::get('year_id'),
            'year_value' => Session::get('year_value'),
            'authenticated' => Session::get('authenticated'),
            'employee_id' => Session::get('employee_id'),
        ],
        'database_config' => [
            'default_connection' => config('database.default'),
            'expense_connection' => DatabaseSwitcher::getExpenseConnection(),
            'hrims_connection' => DatabaseSwitcher::getHrimsConnection(),
        ],
        'company_names' => [
            'company_1' => DatabaseSwitcher::getCompanyName(1),
            'company_2' => DatabaseSwitcher::getCompanyName(2),
        ]
    ];
    
    return response()->json($data, 200, [], JSON_PRETTY_PRINT);
})->middleware('web');