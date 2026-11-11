<?php

namespace App\Http\Controllers\admin;

use App\Http\Controllers\Controller;
use App\Models\ClaimRatingRule;
use App\Services\Reports\ClaimRatingService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class ClaimRatingController extends Controller
{
    /**
     * Show the rating rules management page
     */
    public function index()
    {
        $companyId = (int) session('company_id', 1);
        $rules = ClaimRatingRule::where('company_id', $companyId)
            ->orderBy('sort_order')
            ->get();
        $stages = ClaimRatingRule::getStagesForCompany($companyId);
        $companyName = $companyId == 11 ? 'VVNR' : ($companyId == 3 ? 'Nursery' : 'VNR');

        return view('admin.claim-rating-rules', compact('rules', 'stages', 'companyId', 'companyName'));
    }

    /**
     * Show the employee ratings dashboard page
     */
    public function ratingsView()
    {
        $companyId = (int) session('company_id', 1);
        $companyName = $companyId == 11 ? 'VVNR' : ($companyId == 3 ? 'Nursery' : 'VNR');
        $functions = \App\Models\CoreFunctions::where('is_active', 1)->get(['id', 'function_name']);
        $departments = \App\Models\CoreDepartments::where('is_active', 1)->get(['id', 'department_name']);

        return view('admin.claim-rating-dashboard', compact('companyId', 'companyName', 'functions', 'departments'));
    }

    /**
     * Store or update a rating rule
     */
    public function store(Request $request)
    {
        $request->validate([
            'stage_key' => 'required|string|max:50',
            'star_5_max_days' => 'required|integer|min:0',
            'star_4_max_days' => 'required|integer|min:0',
            'star_3_max_days' => 'required|integer|min:0',
            'star_2_max_days' => 'required|integer|min:0',
            'star_1_min_days' => 'required|integer|min:0',
            'min_claims' => 'nullable|integer|min:0',
            'effective_date' => 'nullable|date',
        ]);

        $companyId = (int) session('company_id', 1);
        $stages = ClaimRatingRule::getStagesForCompany($companyId);
        $stageKey = $request->input('stage_key');

        if (!isset($stages[$stageKey])) {
            return response()->json(['success' => false, 'message' => 'Invalid stage'], 400);
        }

        $rule = ClaimRatingRule::updateOrCreate(
            ['company_id' => $companyId, 'stage_key' => $stageKey],
            [
                'stage_label' => $stages[$stageKey]['label'],
                'responsible_role' => $stages[$stageKey]['role'],
                'star_5_max_days' => $request->input('star_5_max_days'),
                'star_4_max_days' => $request->input('star_4_max_days'),
                'star_3_max_days' => $request->input('star_3_max_days'),
                'star_2_max_days' => $request->input('star_2_max_days'),
                'star_1_min_days' => $request->input('star_1_min_days'),
                'min_claims' => $request->input('min_claims', 0),
                'sort_order' => $stages[$stageKey]['sort'] ?? 0,
                'effective_date' => $request->input('effective_date') ?: now()->toDateString(),
                'is_active' => true,
                'updated_by' => Auth::id(),
            ]
        );

        if ($rule->wasRecentlyCreated) {
            $rule->created_by = Auth::id();
            $rule->save();
        }

        return response()->json(['success' => true, 'message' => 'Rule saved successfully', 'rule' => $rule]);
    }

    /**
     * Get a single rule for editing
     */
    public function edit($id)
    {
        $rule = ClaimRatingRule::findOrFail($id);
        return response()->json(['success' => true, 'rule' => $rule]);
    }

    /**
     * Save calculation settings
     */
    public function saveSettings(Request $request)
    {
        $request->validate([
            'calculation_day' => 'required|integer|min:1|max:28',
            'auto_calculate' => 'boolean',
        ]);

        $companyId = (int) session('company_id', 1);
        $setting = \App\Models\ClaimRatingSetting::updateOrCreate(
            ['company_id' => $companyId],
            [
                'calculation_day' => $request->input('calculation_day'),
                'auto_calculate' => $request->boolean('auto_calculate'),
            ]
        );

        return response()->json(['success' => true, 'message' => 'Settings saved', 'setting' => $setting]);
    }

    /**
     * Manually trigger rating calculation and store results
     */
    public function calculateAndStore(Request $request)
    {
        // M-06: a full year is about a million ratings; stored month by month, but it needs time and memory
        @ini_set('memory_limit', '2048M');
        @set_time_limit(900);
        $service = new ClaimRatingService();
        $companyId = (int) session('company_id', 1);
        $yearId = \App\Support\CurrentYear::id();
        
        // Handle months - could come as array or comma-separated
        $months = $request->input('months');
        if (is_string($months)) {
            $months = explode(',', $months);
        }
        if (empty($months) || !is_array($months)) {
            $months = [4, 5, 6, 7, 8, 9, 10, 11, 12, 1, 2, 3];
        }
        $months = array_map('intval', $months);

        $filters = [
            'months' => $months,
            'function_id' => $request->input('function_id'),
            'department_id' => $request->input('department_id'),
        ];

        // M-06: stored with the rated person (verifier, approver, finance, data entry), wrong dates left out
        try {
            [$claims, $stored] = $service->storeRatings($months, Auth::id());
        } catch (\RuntimeException $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()]);
        }

        return response()->json([
            'success' => true,
            'message' => "Ratings calculated and stored. {$stored} ratings for {$claims} claims.",
            'total_claims' => $claims,
            'total_ratings' => $stored,
        ]);
    }

    /**
     * Delete a rating rule
     */
    public function destroy($id)
    {
        $rule = ClaimRatingRule::findOrFail($id);
        $rule->delete();

        return response()->json(['success' => true, 'message' => 'Rule deleted']);
    }

    /**
     * Toggle rule active/inactive
     */
    public function toggle($id)
    {
        $rule = ClaimRatingRule::findOrFail($id);
        $rule->is_active = !$rule->is_active;
        $rule->updated_by = Auth::id();
        $rule->save();

        return response()->json(['success' => true, 'is_active' => $rule->is_active]);
    }

    /**
     * API: Get rating results
     */
    public function getRatings(Request $request)
    {
        $service = new ClaimRatingService();
        $filters = [
            'months' => $request->input('months', [4, 5, 6, 7, 8, 9, 10, 11, 12, 1, 2, 3]),
            'function_id' => $request->input('function_id'),
            'department_id' => $request->input('department_id'),
        ];

        $data = $service->calculateRatings($filters);

        if (isset($data['error'])) {
            return response()->json(['success' => false, 'message' => $data['error'], 'data' => ['claims' => [], 'role_summary' => [], 'rules' => [], 'total_claims' => 0]]);
        }

        return response()->json(['success' => true, 'data' => $data]);
    }

    /**
     * API: Get employee rating summary (leaderboard)
     */
    public function getEmployeeRatings(Request $request)
    {
        $service = new ClaimRatingService();
        $filters = [
            'months' => $request->input('months', [4, 5, 6, 7, 8, 9, 10, 11, 12, 1, 2, 3]),
            'function_id' => $request->input('function_id'),
            'department_id' => $request->input('department_id'),
        ];

        $data = $service->getEmployeeRatingSummary($filters);

        // If error returned (no rules defined)
        if (isset($data['error'])) {
            return response()->json(['success' => false, 'message' => $data['error'], 'data' => []]);
        }

        return response()->json(['success' => true, 'data' => $data]);
    }
}
