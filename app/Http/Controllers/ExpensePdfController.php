<?php

namespace App\Http\Controllers;

use App\Http\Controllers\admin\PrintDetailsEmpController;
use Illuminate\Http\Request;

class ExpensePdfController extends Controller
{
    /**
     * Generate comprehensive expense statement PDF.
     * Delegates to PrintDetailsEmpController to include all claim types (Travel, Lodging, Meals, DA, etc.)
     */
    public function generate(Request $request)
    {
        $request->merge([
            'e'  => $request->input('empid', $request->input('e')),
            'y'  => $request->input('YearId', $request->input('y')),
            'm'  => $request->input('month', $request->input('m')),
            'nn' => $request->input('nn', 1),
            'c'  => $request->input('ComId', $request->input('c', session('company_id', 1))),
        ]);

        return app(PrintDetailsEmpController::class)->generate($request);
    }
}