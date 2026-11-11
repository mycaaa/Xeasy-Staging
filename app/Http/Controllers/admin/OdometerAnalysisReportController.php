<?php

namespace App\Http\Controllers\admin;

use App\Exports\OdometerAnalysisExport;
use App\Helpers\OrganogramFilterHelper;
use App\Http\Controllers\Controller;
use App\Models\CoreDepartments;
use App\Models\CoreFunctions;
use App\Models\CoreVertical;
use App\Services\Reports\OdometerAnalysisService;
use Illuminate\Http\Request;
use Maatwebsite\Excel\Facades\Excel;

class OdometerAnalysisReportController extends Controller
{
    /**
     * Show the Odometer Analysis Report page
     */
    public function index()
    {
        $company_id = session('company_id');
        $organogramOptions = OrganogramFilterHelper::getOrganogramFilterOptions($company_id);
        $departments = CoreDepartments::get(['id', 'department_name'])->sortBy('department_name');
        $verticals = CoreVertical::get(['id', 'vertical_name'])->sortBy('vertical_name');
        $functions = CoreFunctions::get(['id', 'function_name'])->sortBy('function_name');

        return view('reports.odometer-analysis', array_merge($organogramOptions, compact('departments', 'verticals', 'functions')));
    }

    /**
     * API: Get odometer summary
     */
    public function getSummary(Request $request)
    {
        try {
            $filters = $this->getFilters($request);
            $service = new OdometerAnalysisService();
            $data = $service->getOdometerSummary($filters);

            return response()->json(['success' => true, 'data' => $data]);
        } catch (\Exception $e) {
            \Log::error('Odometer Summary Controller Error: ' . $e->getMessage() . ' | Trace: ' . $e->getTraceAsString());
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    /**
     * API: Get 2-wheeler wise analysis
     */
    public function getTwoWheelerWise(Request $request)
    {
        try {
            $filters = $this->getFilters($request);
            $service = new OdometerAnalysisService();
            $data = $service->getTwoWheelerWiseAnalysis($filters);

            return response()->json(['success' => true, 'data' => $data]);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    /**
     * API: Get 4-wheeler wise analysis
     */
    public function getFourWheelerWise(Request $request)
    {
        try {
            $filters = $this->getFilters($request);
            $service = new OdometerAnalysisService();
            $data = $service->getFourWheelerWiseAnalysis($filters);

            return response()->json(['success' => true, 'data' => $data]);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    /**
     * API: Get employee-wise analysis
     */
    public function getEmployeeWise(Request $request)
    {
        try {
            $filters = $this->getFilters($request);
            $service = new OdometerAnalysisService();
            $data = $service->getEmployeeWiseAnalysis($filters);

            return response()->json(['success' => true, 'data' => $data]);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    /**
     * API: Get department-wise analysis
     */
    public function getDepartmentWise(Request $request)
    {
        try {
            $filters = $this->getFilters($request);
            $service = new OdometerAnalysisService();
            $data = $service->getDepartmentWiseAnalysis($filters);

            return response()->json(['success' => true, 'data' => $data]);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    /**
     * API: Get vertical-wise analysis
     */
    public function getVerticalWise(Request $request)
    {
        try {
            $filters = $this->getFilters($request);
            $service = new OdometerAnalysisService();
            $data = $service->getVerticalWiseAnalysis($filters);

            return response()->json(['success' => true, 'data' => $data]);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    /**
     * Export all data to Excel
     */
    public function export(Request $request)
    {
        try {
            $filters = $this->getFilters($request);
            $service = new OdometerAnalysisService();

            $data = [
                'summary' => $service->getOdometerSummary($filters),
                'employee_wise' => $service->getEmployeeWiseAnalysis($filters),
                'two_wheeler' => $service->getTwoWheelerWiseAnalysis($filters),
                'four_wheeler' => $service->getFourWheelerWiseAnalysis($filters),
                'department_wise' => $service->getDepartmentWiseAnalysis($filters),
                'vertical_wise' => $service->getVerticalWiseAnalysis($filters),
            ];

            return Excel::download(
                new OdometerAnalysisExport($data),
                'odometer_analysis_' . date('Y-m-d_H-i-s') . '.xlsx'
            );
        } catch (\Exception $e) {
            return back()->with('error', 'Export failed: ' . $e->getMessage());
        }
    }

    /**
     * Export month-wise grouped data to Excel
     */
    public function exportMonthWise(Request $request)
    {
        try {
            $filters = $this->getFilters($request);
            $service = new OdometerAnalysisService();
            $data = $service->getMonthWiseAnalysis($filters);

            return Excel::download(
                new \App\Exports\OdometerMonthWiseExport($data),
                'odometer_month_wise_' . date('Y-m-d_H-i-s') . '.xlsx'
            );
        } catch (\Exception $e) {
            return back()->with('error', 'Export failed: ' . $e->getMessage());
        }
    }

    /**
     * Helper method to extract and validate filters
     */
    private function getFilters(Request $request): array
    {
        $organogramFilters = OrganogramFilterHelper::extractFilters($request);

        return array_merge([
            'department_id' => $request->input('department_id'),
            'vertical_id' => $request->input('vertical_id'),
            'function_id' => $request->input('function_id'),
            'grade_id' => $request->input('grade_id'),
            'vehicle_type' => $request->input('vehicle_type'),
            'employee_id' => $request->input('employee_id'),
            'months' => $request->input('months'),
        ], $organogramFilters);
    }
}
