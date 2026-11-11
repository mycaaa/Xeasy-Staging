<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\EmployeeLedgerController;

/*
|--------------------------------------------------------------------------
| Employee Ledger Routes
|--------------------------------------------------------------------------
|
| Routes for employee ledger balance management and import functionality
|
*/

Route::middleware(['auth'])->group(function () {
    
    // Employee Ledger Import Routes
    Route::prefix('employee-ledger')->name('employee-ledger.')->group(function () {
        
        // Main page
        Route::get('/', function () {
            return view('admin.employee-ledger.import');
        })->name('index');
        
        // Import functionality
        Route::post('/import', [EmployeeLedgerController::class, 'import'])->name('import');
        Route::get('/template', [EmployeeLedgerController::class, 'downloadTemplate'])->name('template');
        
        // API routes for AJAX calls
        Route::get('/financial-years', [EmployeeLedgerController::class, 'getFinancialYears'])->name('financial-years');
        
    });
    
});

// API Routes (for AJAX calls) - No CSRF protection needed
Route::prefix('api')->group(function () {
    
    // Financial years endpoint - no auth required for this
    Route::get('/financial-years', [EmployeeLedgerController::class, 'getFinancialYears']);
    
    Route::middleware(['auth'])->group(function () {
        Route::prefix('employee-ledger')->group(function () {
            Route::post('/import', [EmployeeLedgerController::class, 'import']);
            Route::get('/template', [EmployeeLedgerController::class, 'downloadTemplate']);
            Route::get('/data', [EmployeeLedgerController::class, 'getData']);
            Route::get('/employees', [EmployeeLedgerController::class, 'getEmployees']);
            Route::get('/{id}', [EmployeeLedgerController::class, 'show']);
            Route::post('/', [EmployeeLedgerController::class, 'store']);
            Route::put('/{id}', [EmployeeLedgerController::class, 'update']);
            Route::delete('/{id}', [EmployeeLedgerController::class, 'destroy']);
        });
    });
    
});