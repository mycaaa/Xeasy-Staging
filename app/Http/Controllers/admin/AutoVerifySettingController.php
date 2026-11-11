<?php

namespace App\Http\Controllers\admin;

use App\Helpers\DatabaseSwitcher;
use App\Http\Controllers\Controller;
use App\Models\AutoVerifySetting;
use App\Models\ClaimType;
use App\Models\ExpenseClaim;
use App\Services\AutoVerificationService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class AutoVerifySettingController extends Controller
{
    protected AutoVerificationService $autoVerifyService;

    public function __construct(AutoVerificationService $autoVerifyService)
    {
        $this->autoVerifyService = $autoVerifyService;
    }

    protected function isAuthorizedUser(): bool
    {
        $empCode = (string) (
            session('emp_code') 
            ?? session('employee_details.EmpCode') 
            ?? auth()->user()?->employee?->EmpCode 
            ?? auth()->user()?->employee?->EmpCode_New 
            ?? auth()->user()?->employee_id 
            ?? ''
        );
        return $empCode === '1740' || auth()->user()?->employee_id == 2081 || auth()->user()?->employee_id == 1740;
    }

    /**
     * Display the auto-verification configuration dashboard.
     */
    public function index(Request $request)
    {
        if (!$this->isAuthorizedUser()) {
            abort(403, 'Unauthorized. Access restricted to employee code 1740.');
        }

        $companyId = (int) $request->input('company_id', 0); // 0 = Global Defaults
        $currentCompany = in_array($companyId, [0, 1, 3, 11]) ? $companyId : 0;

        $setting = AutoVerifySetting::where('company_id', $currentCompany)->first();

        if (!$setting) {
            // Seed from global default if not present
            $globalSetting = AutoVerifySetting::where('company_id', 0)->first();
            $setting = AutoVerifySetting::create([
                'company_id' => $currentCompany,
                'is_enabled' => $globalSetting->is_enabled ?? true,
                'auto_submit_month' => $globalSetting->auto_submit_month ?? true,
                'courier_check_required' => $globalSetting->courier_check_required ?? true,
                'batch_size' => $globalSetting->batch_size ?? 250,
                'gps_tolerance_pct' => $globalSetting->gps_tolerance_pct ?? 15.0,
                'ocr_tolerance_pct' => $globalSetting->ocr_tolerance_pct ?? 5.0,
                'ocr_tolerance_flat' => $globalSetting->ocr_tolerance_flat ?? 5.0,
                'max_auto_verify_amount' => $globalSetting->max_auto_verify_amount ?? 10000.0,
                'rules' => $globalSetting->rules ?? [],
                'updated_by' => 'System',
            ]);
        }

        // Fetch all claim types
        $claimTypes = ClaimType::orderBy('ClaimId')->get();

        // Categorize claim types for organized UI presentation
        $categorizedClaims = [
            'core' => [
                'title' => 'Core Daily & Travel Claims',
                'description' => 'High-volume repetitive claims (Vehicle Running, DA, Lodging, Meals)',
                'ids' => [7, 19, 20, 1, 17],
            ],
            'transit' => [
                'title' => 'Transit & Conveyance',
                'description' => 'Tickets and fares (Air, Rail, Bus, Local Conveyance, Hired Vehicles)',
                'ids' => [2, 14, 15, 3, 4, 18, 43],
            ],
            'office' => [
                'title' => 'Office, Postage & Admin',
                'description' => 'Phone, Courier, Maintenance, and Miscellaneous',
                'ids' => [5, 6, 8, 9, 16],
            ],
            'campaigns' => [
                'title' => 'Field Campaigns & Dealer Meetings',
                'description' => 'Field days, demonstrations, retailer/dealer meetings',
                'ids' => [10, 11, 12, 13, 22, 23, 24, 25, 26, 27, 28, 29, 30, 31, 32, 33, 34, 35, 36, 37, 38, 39, 40, 41, 42],
            ],
        ];

        // Financial years dynamically queried from database
        $financialYears = collect();
        $simCompanyId = ($currentCompany === 0) ? 1 : $currentCompany;
        try {
            $conn = DatabaseSwitcher::getExpenseConnection($simCompanyId);
            $financialYears = DB::connection($conn)->table('financialyear')
                ->orderBy('YearId', 'desc')
                ->get(['YearId', 'Year']);
        } catch (\Exception $e) {
            $financialYears = collect();
        }

        // Selected financial year (from session, DB active year, or fallback to 8)
        $selectedYear = $financialYears->first();
        $yearId = (int) (session('year_id') ?? ($selectedYear->YearId ?? 8));

        $pendingCount = 0;
        try {
            $tbl = ExpenseClaim::tableName($yearId);
            $pendingCount = DB::connection($conn)->table($tbl)
                ->whereIn('ClaimStatus', ['Submitted', 'Filled'])
                ->whereNotIn('ClaimStatus', ['Deactivate', 'Draft'])
                ->where(function ($q) {
                    $q->whereNull('v_verify')->orWhere('v_verify', '!=', 'Y');
                })
                ->where('AttachTo', 0)
                ->count();
        } catch (\Exception $e) {
            $pendingCount = 0;
        }

        $companies = [
            0 => 'Global Defaults',
            1 => 'VNR Seeds',
            3 => 'VNR Nursery',
            11 => 'VVNR Seeds',
        ];

        return view('admin.settings.auto_verify_settings', compact(
            'setting',
            'currentCompany',
            'companies',
            'claimTypes',
            'categorizedClaims',
            'pendingCount',
            'yearId',
            'financialYears'
        ));
    }

    /**
     * Save/Update auto-verification settings and rules.
     */
    public function update(Request $request)
    {
        if (!$this->isAuthorizedUser()) {
            abort(403, 'Unauthorized. Access restricted to employee code 1740.');
        }

        $validated = $request->validate([
            'company_id' => 'required|integer|in:0,1,3,11',
            'is_enabled' => 'nullable|boolean',
            'auto_submit_month' => 'nullable|boolean',
            'courier_check_required' => 'nullable|boolean',
            'batch_size' => 'required|integer|min:10|max:2000',
            'gps_tolerance_pct' => 'required|numeric|min:0|max:100',
            'ocr_tolerance_pct' => 'required|numeric|min:0|max:100',
            'ocr_tolerance_flat' => 'required|numeric|min:0|max:1000',
            'max_auto_verify_amount' => 'required|numeric|min:0|max:1000000',
            'rules' => 'nullable|array',
        ]);

        $companyId = (int) $validated['company_id'];

        // Format rules array cleanly (handling both categories and claim IDs)
        $rules = [];
        if (!empty($validated['rules'])) {
            foreach ($validated['rules'] as $key => $rule) {
                if ($key === 'odometer') {
                    $rules['odometer'] = [
                        'enabled' => !empty($rule['enabled']),
                        'check_with_gps' => !empty($rule['check_with_gps']),
                        'gps_tolerance_pct' => isset($rule['gps_tolerance_pct']) ? (float) $rule['gps_tolerance_pct'] : 15.0,
                        'missing_gps_action' => in_array($rule['missing_gps_action'] ?? '', ['pass', 'flag']) ? $rule['missing_gps_action'] : 'pass',
                        'excess_gps_action' => in_array($rule['excess_gps_action'] ?? '', ['cap', 'flag', 'reject']) ? $rule['excess_gps_action'] : 'flag',
                        'require_opening_photo' => !empty($rule['require_opening_photo']),
                        'require_closing_photo' => !empty($rule['require_closing_photo']),
                        'require_attachment' => !empty($rule['require_attachment']),
                        'monthly_km_limit_check' => !empty($rule['monthly_km_limit_check']),
                        'max_daily_km' => isset($rule['max_daily_km']) ? (float) $rule['max_daily_km'] : 400.0,
                        'action_over_limit' => in_array($rule['action_over_limit'] ?? '', ['cap', 'flag', 'reject']) ? $rule['action_over_limit'] : 'cap',
                        'max_amount' => isset($rule['max_amount']) ? (float) $rule['max_amount'] : 15000.0,
                    ];
                } elseif ($key === 'da') {
                    $rules['da'] = [
                        'enabled' => !empty($rule['enabled']),
                        'check_grade_limit' => !empty($rule['check_grade_limit']),
                        'check_attendance' => !empty($rule['check_attendance']),
                        'action_over_limit' => in_array($rule['action_over_limit'] ?? '', ['cap', 'flag', 'reject']) ? $rule['action_over_limit'] : 'cap',
                        'require_attachment' => !empty($rule['require_attachment']),
                        'max_amount' => isset($rule['max_amount']) ? (float) $rule['max_amount'] : 3000.0,
                    ];
                } elseif ($key === 'lodging') {
                    $rules['lodging'] = [
                        'enabled' => !empty($rule['enabled']),
                        'check_city_category' => !empty($rule['check_city_category']),
                        'action_over_limit' => in_array($rule['action_over_limit'] ?? '', ['cap', 'flag', 'reject']) ? $rule['action_over_limit'] : 'flag',
                        'require_attachment' => !empty($rule['require_attachment']),
                        'max_amount' => isset($rule['max_amount']) ? (float) $rule['max_amount'] : 10000.0,
                    ];
                } elseif ($key === 'meals') {
                    $rules['meals'] = [
                        'enabled' => !empty($rule['enabled']),
                        'check_headcount_limit' => !empty($rule['check_headcount_limit']),
                        'action_over_limit' => in_array($rule['action_over_limit'] ?? '', ['cap', 'flag', 'reject']) ? $rule['action_over_limit'] : 'cap',
                        'require_attachment' => !empty($rule['require_attachment']),
                        'max_amount' => isset($rule['max_amount']) ? (float) $rule['max_amount'] : 5000.0,
                    ];
                } elseif ($key === 'transit') {
                    $rules['transit'] = [
                        'enabled' => !empty($rule['enabled']),
                        'check_grade_entitlement' => !empty($rule['check_grade_entitlement']),
                        'action_unentitled' => in_array($rule['action_unentitled'] ?? '', ['flag', 'reject']) ? $rule['action_unentitled'] : 'flag',
                        'action_over_limit' => in_array($rule['action_over_limit'] ?? '', ['cap', 'flag', 'reject']) ? $rule['action_over_limit'] : 'flag',
                        'require_attachment' => !empty($rule['require_attachment']),
                        'max_amount' => isset($rule['max_amount']) ? (float) $rule['max_amount'] : 15000.0,
                    ];
                } elseif ($key === 'general_bills') {
                    $rules['general_bills'] = [
                        'enabled' => !empty($rule['enabled']),
                        'check_with_ocr' => !empty($rule['check_with_ocr']),
                        'ocr_tolerance_pct' => isset($rule['ocr_tolerance_pct']) ? (float) $rule['ocr_tolerance_pct'] : 5.0,
                        'ocr_tolerance_flat' => isset($rule['ocr_tolerance_flat']) ? (float) $rule['ocr_tolerance_flat'] : 5.0,
                        'action_ocr_mismatch' => in_array($rule['action_ocr_mismatch'] ?? '', ['cap', 'flag', 'reject']) ? $rule['action_ocr_mismatch'] : 'flag',
                        'action_over_limit' => in_array($rule['action_over_limit'] ?? '', ['cap', 'flag', 'reject']) ? $rule['action_over_limit'] : 'flag',
                        'require_attachment' => !empty($rule['require_attachment']),
                        'max_amount' => isset($rule['max_amount']) ? (float) $rule['max_amount'] : 5000.0,
                    ];
                } elseif ($key === 'campaigns') {
                    $rules['campaigns'] = [
                        'enabled' => !empty($rule['enabled']),
                        'require_attachment' => !empty($rule['require_attachment']),
                        'action_over_limit' => in_array($rule['action_over_limit'] ?? '', ['cap', 'flag', 'reject']) ? $rule['action_over_limit'] : 'flag',
                        'max_amount' => isset($rule['max_amount']) ? (float) $rule['max_amount'] : 25000.0,
                    ];
                } else {
                    $rules[$key] = [
                        'enabled' => !empty($rule['enabled']),
                        'action_over_limit' => in_array($rule['action_over_limit'] ?? '', ['cap', 'flag', 'reject'])
                            ? $rule['action_over_limit']
                            : 'flag',
                        'require_attachment' => !empty($rule['require_attachment']),
                        'require_continuity' => !empty($rule['require_continuity']),
                        'max_amount' => isset($rule['max_amount']) && is_numeric($rule['max_amount'])
                            ? (float) $rule['max_amount']
                            : (float) $validated['max_auto_verify_amount'],
                    ];
                }
            }
        }

        $data = [
            'is_enabled' => $request->boolean('is_enabled'),
            'auto_submit_month' => $request->boolean('auto_submit_month'),
            'courier_check_required' => $request->boolean('courier_check_required'),
            'batch_size' => (int) $validated['batch_size'],
            'gps_tolerance_pct' => (float) $validated['gps_tolerance_pct'],
            'ocr_tolerance_pct' => (float) $validated['ocr_tolerance_pct'],
            'ocr_tolerance_flat' => (float) $validated['ocr_tolerance_flat'],
            'max_auto_verify_amount' => (float) $validated['max_auto_verify_amount'],
            'rules' => $rules,
            'updated_by' => Auth::user()->name ?? 'Administrator',
            'updated_at' => now(),
        ];

        AutoVerifySetting::updateOrCreate(
            ['company_id' => $companyId],
            $data
        );

        AutoVerifySetting::clearCache($companyId);
        AutoVerifySetting::clearCache(0); // clear global fallback cache as well

        if ($request->ajax() || $request->wantsJson()) {
            return response()->json([
                'success' => true,
                'message' => 'Auto-verification settings & rules saved successfully!',
            ]);
        }

        return redirect()->route('admin.settings.auto-verify', ['company_id' => $companyId])
            ->with('success', 'Auto-verification settings & rules saved successfully!');
    }

    /**
     * Run a dry-run test simulation against live pending claims.
     */
    public function simulate(Request $request)
    {
        if (!$this->isAuthorizedUser()) {
            return response()->json(['success' => false, 'message' => 'Unauthorized. Access restricted to employee code 1740.'], 403);
        }

        $companyId = (int) $request->input('company_id', 1);
        if ($companyId === 0) $companyId = 1;

        $month = (int) $request->input('month', 4);
        $yearId = (int) $request->input('year_id', 8);
        $limit = (int) $request->input('limit', 100);

        try {
            $summary = $this->autoVerifyService->batchVerifyPending(
                $companyId,
                $yearId,
                [
                    'month' => $month,
                    'limit' => $limit,
                ],
                true // dry run
            );

            return response()->json([
                'success' => true,
                'summary' => $summary,
                'message' => "Simulation completed on Month {$month}. Total: " . ($summary['total_evaluated'] ?? 0) . " claims evaluated.",
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Simulation failed: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get comprehensive claim details and its policy evaluation for inspection modal.
     */
    public function getClaimDetails(Request $request)
    {
        if (!$this->isAuthorizedUser()) {
            return response()->json(['success' => false, 'message' => 'Unauthorized. Access restricted to employee code 1740.'], 403);
        }

        $expId = (int) $request->input('exp_id', $request->input('claim_id'));
        $companyId = (int) $request->input('company_id', 1);
        if ($companyId === 0) $companyId = 1;
        $yearId = (int) $request->input('year_id', 8);

        if ($expId <= 0) {
            return response()->json(['success' => false, 'message' => 'Valid Claim/Exp ID required'], 400);
        }

        try {
            $conn = DatabaseSwitcher::getExpenseConnection($companyId);
            $claimTable = ExpenseClaim::tableName($yearId);

            if (!DB::connection($conn)->getSchemaBuilder()->hasTable($claimTable)) {
                return response()->json(['success' => false, 'message' => "Table {$claimTable} not found on database"], 404);
            }

            $claim = DB::connection($conn)->table("{$claimTable} as e")
                ->leftJoin('claimtype as c', 'c.ClaimId', '=', 'e.ClaimId')
                ->select('e.*', 'c.ClaimName', 'c.ClaimCode')
                ->where('e.ExpId', $expId)
                ->first();

            if (!$claim) {
                return response()->json(['success' => false, 'message' => "Claim record #{$expId} not found in FY Year ID {$yearId}"]);
            }

            // Employee Name from hrims
            $employeeName = "Employee #{$claim->CrBy}";
            try {
                $emp = DB::connection('hrims')->table('hrm_employee')
                    ->where('EmployeeID', $claim->CrBy)
                    ->first(['Fname', 'Sname', 'Lname', 'EmpCode']);
                if ($emp) {
                    $employeeName = trim("{$emp->Fname} {$emp->Sname} {$emp->Lname}") . " ({$emp->EmpCode})";
                }
            } catch (\Exception $e) {
                // ignore
            }

            // Evaluate against current active policy rules (dry-run simulation)
            $evaluation = $this->autoVerifyService->verifyClaim($claim, $yearId, $companyId, true);

            // Fetch uploads / attachments
            $attachments = [];
            $uploadTable = "y{$yearId}_claimuploads";
            if (DB::connection($conn)->getSchemaBuilder()->hasTable($uploadTable)) {
                $uploads = DB::connection($conn)->table($uploadTable)
                    ->where('ExpId', $expId)
                    ->get(['FileName']);
                foreach ($uploads as $u) {
                    if (!empty($u->FileName)) {
                        $attachments[] = $u->FileName;
                    }
                }
            }

            // Check direct image columns
            foreach (['opening_filepath', 'closing_filepath', 'Img_1', 'Img_2', 'Img1_offline'] as $col) {
                if (!empty($claim->{$col})) {
                    $attachments[] = $claim->{$col};
                }
            }

            return response()->json([
                'success' => true,
                'claim' => [
                    'exp_id' => $claim->ExpId ?? $expId,
                    'claim_id' => $claim->ClaimId ?? null,
                    'claim_name' => $claim->ClaimName ?? ($claim->ClaimId ? "Claim #{$claim->ClaimId}" : 'Claim'),
                    'claim_code' => $claim->ClaimCode ?? '',
                    'employee_id' => $claim->CrBy ?? null,
                    'employee_name' => $employeeName,
                    'bill_date' => $claim->BillDate ?? null,
                    'claim_month' => $claim->ClaimMonth ?? null,
                    'claim_year' => $claim->ClaimYearId ?? $claim->ClaimYear ?? $yearId,
                    'claimed_amount' => (float) ($claim->FilledTAmt ?? $claim->Amount ?? 0),
                    'verified_amount' => (float) ($claim->VerifyTAmt ?? 0),
                    'status' => $claim->ClaimStatus ?? null,
                    'step' => $claim->ClaimAtStep ?? 0,
                    'remarks' => $claim->Remark ?? $claim->Remarks ?? 'N/A',
                    'tot_km' => $claim->TotKm ?? null,
                    'rate_per_km' => $claim->RatePerKM ?? null,
                    'opening_km' => $claim->odomtr_opening ?? null,
                    'closing_km' => $claim->odomtr_closing ?? null,
                    'wheel_type' => $claim->WType ?? null,
                    'ocr_amount' => $claim->ocr_amount ?? null,
                    'attachments' => array_values(array_unique($attachments)),
                ],
                'evaluation' => $evaluation,
            ]);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }
}
