<?php

namespace App\Http\Controllers\admin;

use App\Http\Controllers\Controller;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

class ExpenseController extends Controller
{
    
    public function getNonSubmittedEmployeesThreeDays(): JsonResponse
    {
        try {
            $referenceDate = Carbon::parse('2025-09-15');
            $threeDaysBefore = $referenceDate->subDays(3); 

            
            $currentDate = Carbon::now('Asia/Kolkata'); 
            if ($currentDate->greaterThan($threeDaysBefore)) {
                return response()->json([
                    'success' => false,
                    'message' => 'No employees to retrieve as current date exceeds 3-day cutoff date.',
                ], 200);
            }

            $employees = DB::table('y7_monthexpensefinal')
                ->select('EmployeeId')
                ->where('Status', 'Open')
                ->where(function ($q) use ($threeDaysBefore) {
                    $q->where('DateOfSubmit', '0000-00-00')
                        ->orWhereNull('DateOfSubmit')
                        ->orWhere('DateOfSubmit', '<', $threeDaysBefore);
                })
                ->distinct()
                ->get();

            $response = [
                'success' => true,
                'cutoff_date' => $threeDaysBefore->toDateString(),
                'employees' => $employees,
                'message' => $employees->isEmpty() ? 'No employees found.' : 'Employees retrieved successfully.',
            ];

            return response()->json($response, 200);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'An error occurred: '.$e->getMessage(),
            ], 500);
        }
    }

    public function getNonSubmittedEmployeesFiveDays(): JsonResponse
    {
        try {
            $referenceDate = Carbon::parse('2025-09-15');
            $fiveDaysBefore = $referenceDate->subDays(5); 

            
            $currentDate = Carbon::now('Asia/Kolkata'); 
            if ($currentDate->greaterThan($fiveDaysBefore)) {
                return response()->json([
                    'success' => false,
                    'message' => 'No employees to retrieve as current date exceeds 5-day cutoff date.',
                ], 200);
            }

            $employees = DB::table('y7_monthexpensefinal')
                ->select('EmployeeId')
                ->where('Status', 'Open')
                ->where(function ($q) use ($fiveDaysBefore) {
                    $q->where('DateOfSubmit', '0000-00-00')
                        ->orWhereNull('DateOfSubmit')
                        ->orWhere('DateOfSubmit', '<', $fiveDaysBefore);
                })
                ->distinct()
                ->get();

            $response = [
                'success' => true,
                'cutoff_date' => $fiveDaysBefore->toDateString(),
                'employees' => $employees,
                'message' => $employees->isEmpty() ? 'No employees found.' : 'Employees retrieved successfully.',
            ];

            return response()->json($response, 200);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'An error occurred: '.$e->getMessage(),
            ], 500);
        }
    }
}
