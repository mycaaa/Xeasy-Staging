<?php

namespace App\Http\Controllers\admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\SaveConfigRequest;
use App\Models\CompanyDbConfig;
use App\Models\CoreCompany;
use App\Models\CoreDepartments;
use App\Models\CoreVertical;
use App\Models\CoreVerticalDepartmentMapping;
use App\Models\CoreVerticalFunctionMapping;
use App\Models\GeneralSettings;
use App\Models\OdoApprovalRule;
use App\Models\OdoBackdateSetting;
use App\Models\ThemeCustomizer;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;

class SettingController extends Controller
{
    public function index()
    {
        return view('admin.settings');
    }

    public function company()
    {
        $company = CoreCompany::all();
        return $this->jsonSuccess($company, 'Company fetched successfully.');
    }

    public function saveCompanyConfig(SaveConfigRequest $request)
    {
        $validated = $request->validated();
        $status = !empty($validated['is_active']) ? 1 : 0;
        $data = [
            'db_connection' => $validated['db_connection'],
            'db_host' => $validated['db_host'],
            'db_port' => $validated['db_port'],
            'db_database' => $validated['db_database'],
            'db_username' => $validated['db_username'],
            'db_password' => $validated['db_password'],
            'status' => $status,
            'created_by' => auth()->id(),
            'updated_by' => auth()->id(),
        ];
        $existingConfig = CompanyDbConfig::where('company_id', $validated['company_id'])->where('db_name', $validated['db_name'])->first();
        if ($existingConfig) {
            $existingConfig->update($data);
            return $this->jsonSuccess($existingConfig, 'Configuration updated successfully.');
        } else {
            $data['company_id'] = $validated['company_id'];
            $data['db_name'] = $validated['db_name'];
            $newConfig = CompanyDbConfig::create($data);
            return $this->jsonSuccess($newConfig, 'Configuration created successfully.');
        }
    }

    public function getCompanyConfig(Request $request, string $id)
    {
        $configs = CompanyDbConfig::where('company_id', $id)->get();
        $data = ['hrims' => null, 'expense' => null];
        foreach ($configs as $config) {
            if ($config->db_name === 'hrims') {
                $data['hrims'] = $config;
            } elseif ($config->db_name === 'expense') {
                $data['expense'] = $config;
            }
        }
        return $this->jsonSuccess($data, 'Company details fetched successfully.');
    }

    public function saveThemeSettings(Request $request)
    {
        $user = Auth::user();
        $data = $request->all();
        $validated = $request->validate([
            'layout' => 'in:vertical,horizontal,twocolumn,semibox',
            'sidebar_user_profile' => 'boolean',
            'theme' => 'in:default,saas,corporate,galaxy,material,creative,minimal,modern,interactive,classic,vintage',
            'color_scheme' => 'in:light,dark',
            'sidebar_visibility' => 'in:show,hidden',
            'layout_width' => 'in:fluid,boxed',
            'layout_position' => 'in:fixed,scrollable',
            'topbar_color' => 'in:light,dark',
            'sidebar_size' => 'in:lg,md,sm,sm-hover',
            'sidebar_view' => 'in:default,detached',
            'sidebar_color' => 'in:light,dark,gradient,gradient-2,gradient-3,gradient-4',
            'sidebar_image' => 'in:none,img-1,img-2,img-3,img-4',
            'primary_color' => 'in:default,green,purple,blue',
            'preloader' => 'in:enable,disable',
            'body_image' => 'in:none,img-1,img-2,img-3',
        ]);
        ThemeCustomizer::updateOrCreate(['user_id' => $user ? $user->id : null], $validated);
        return response()->json(['message' => 'Settings saved successfully']);
    }

    public function saveGeneralSettings(Request $request)
    {
        $request->validate([
            'projectName' => 'required|string|max:255',
            'timeZone' => 'required|string|max:255',
            'language' => 'required|string|max:255',
            'siteUrl' => 'nullable|url|max:255',
            'contactInfo' => 'nullable|string|max:500',
            'siteDescription' => 'nullable|string|max:1000',
            'logo' => 'nullable|image|max:2048',
            'maintenanceMode' => 'nullable|boolean',
        ]);
        $data = [
            'project_name' => $request->projectName,
            'time_zone' => $request->timeZone,
            'default_language' => $request->language,
            'maintenance_mode' => $request->maintenanceMode ? 1 : 0,
            'site_url' => $request->siteUrl,
            'contact_info' => $request->contactInfo,
            'site_description' => $request->siteDescription,
            'updated_by' => auth()->id(),
            'updated_at' => now(),
        ];
        if ($request->hasFile('logo')) {
            $timestamp = now()->format('YmdHis');
            $folder = "uploads/logos/{$timestamp}";
            $fileName = Str::random(10) . '.' . $request->file('logo')->getClientOriginalExtension();
            $path = $request->file('logo')->storeAs($folder, $fileName, 'public');
            $data['logo_path'] = $path;
        }
        $existingSettings = GeneralSettings::first();
        if ($existingSettings) {
            $existingSettings->update($data);
        } else {
            $data['created_by'] = auth()->id();
            $data['created_at'] = now();
            GeneralSettings::create($data);
        }
        return $this->jsonSuccess($data, 'Settings saved successfully.');
    }

    public function getGeneralSettings()
    {
        $settings = GeneralSettings::first();
        if (!$settings) {
            // UAT-03: an empty table is not an error; the settings form loads blank.
            return $this->jsonSuccess(null, 'No settings saved yet.');
        }
        return $this->jsonSuccess($settings, 'Settings fetched successfully.');
    }

    public function odoBackdateApproval()
    {
        $departments = CoreDepartments::all();
        $settings = OdoBackdateSetting::all()->keyBy('department_id');
        $deptVerticalMappings = CoreVerticalDepartmentMapping::whereIn('department_id', $departments->pluck('id'))->get();
        $funcVerticalMap = CoreVerticalFunctionMapping::all()->keyBy('id');
        $verticals = CoreVertical::all()->keyBy('id');
        $mappedVerticalDetails = $deptVerticalMappings->groupBy('department_id')->map(function ($deptMaps) use ($funcVerticalMap, $verticals) {
            return $deptMaps->map(function ($deptMap) use ($funcVerticalMap, $verticals) {
                $funcMapping = $funcVerticalMap[$deptMap->function_vertical_id] ?? null;
                if (!$funcMapping)
                    return null;
                return $verticals[$funcMapping->vertical_id] ?? null;
            })->filter();
        });
        return view('admin.odo_backdate_approval', compact('departments', 'settings', 'mappedVerticalDetails'));
    }

    public function updateOdoBackdateSetting(Request $request, $department_id)
    {
        $data = $request->only(['is_active', 'approval_type', 'effective_date', 'delayed_day']);
        $verticals = $request->input('verticals', []);
        $data['verticals'] = implode(',', $verticals);
        OdoBackdateSetting::updateOrCreate(['department_id' => $department_id], $data);
        return response()->json(['success' => true, 'message' => 'Setting updated successfully']);
    }

    public function accessRunningKmSetting()
    {
        $departments = CoreDepartments::all();
        $rules = OdoApprovalRule::all()->keyBy('department_id');
        $deptVerticalMappings = CoreVerticalDepartmentMapping::whereIn('department_id', $departments->pluck('id'))->get();
        $funcVerticalMap = CoreVerticalFunctionMapping::all()->keyBy('id');
        $verticals = CoreVertical::all()->keyBy('id');
        $mappedVerticalDetails = $deptVerticalMappings->groupBy('department_id')->map(function ($deptMaps) use ($funcVerticalMap, $verticals) {
            return $deptMaps->map(function ($deptMap) use ($funcVerticalMap, $verticals) {
                $funcMapping = $funcVerticalMap[$deptMap->function_vertical_id] ?? null;
                if (!$funcMapping)
                    return null;
                return $verticals[$funcMapping->vertical_id] ?? null;
            })->filter();
        });
        return view('admin.access_running_km_setting', compact('departments', 'rules', 'mappedVerticalDetails'));
    }

    public function updateAccessRunningKmSetting(Request $request, $department_id)
    {
        $isActive = $request->boolean('is_active');

        // Dynamic validation rules based on active status
        $rules = [
            'is_active' => 'required|boolean',
            'auto_approve_if_below_limit' => 'required|in:Y,N',
            'dept_active' => 'required|in:Y,N',
            'effective_to' => 'nullable|date|after:effective_from',
        ];

        // If department is active, make all fields required except effective_to
        if ($isActive) {
            $rules['monthly_km_limit'] = 'required|integer|min:1|max:999999';
            $rules['effective_from'] = 'required|date|before_or_equal:today';
            $rules['verticals'] = 'required|array|min:1';
            $rules['verticals.*'] = 'integer';
        } else {
            // If not active, fields can be nullable but still validated if provided
            $rules['monthly_km_limit'] = 'nullable|integer|min:1|max:999999';
            $rules['effective_from'] = 'nullable|date|before_or_equal:today';
            $rules['verticals'] = 'nullable|array';
            $rules['verticals.*'] = 'integer';
        }

        $messages = [
            'effective_from.required' => 'Effective From date is required when department is active.',
            'effective_from.before_or_equal' => 'Effective From date cannot be in the future.',
            'effective_to.after' => 'Effective To date must be after Effective From date.',
            'monthly_km_limit.required' => 'Monthly KM limit is required when department is active.',
            'monthly_km_limit.min' => 'Monthly KM limit must be at least 1.',
            'monthly_km_limit.max' => 'Monthly KM limit cannot exceed 999,999.',
            'auto_approve_if_below_limit.in' => 'Auto approve option must be Yes or No.',
            'verticals.required' => 'At least one vertical must be selected when department is active.',
            'verticals.min' => 'At least one vertical must be selected when department is active.',
            'verticals.*.exists' => 'Selected vertical is invalid.'
        ];

        $validated = $request->validate($rules, $messages);

        // Business logic validation
        $isActive = $validated['is_active'];
        $effectiveFrom = $validated['effective_from'];
        $effectiveTo = $validated['effective_to'] ?? null;
        $monthlyKmLimit = $validated['monthly_km_limit'] ?? null;
        $verticals = $validated['verticals'] ?? [];

        // Check if department exists
        $department = CoreDepartments::find($department_id);
        if (!$department) {
            return response()->json([
                'success' => false,
                'message' => 'Department not found.'
            ], 404);
        }

        // If rule is active, perform additional validations
        if ($isActive) {
            // Check for overlapping date ranges for the same department
            $existingRule = OdoApprovalRule::where('department_id', $department_id)
                ->where('is_active', true)
                ->first();

            if ($existingRule) {
                // If updating existing rule, check for date conflicts with other active rules
                $conflictingRules = OdoApprovalRule::where('department_id', $department_id)
                    ->where('id', '!=', $existingRule->id)
                    ->where('is_active', true)
                    ->where(function ($query) use ($effectiveFrom, $effectiveTo) {
                        $query->where(function ($q) use ($effectiveFrom, $effectiveTo) {
                            // New rule starts within existing rule period
                            $q
                                ->where('effective_from', '<=', $effectiveFrom)
                                ->where(function ($subQ) use ($effectiveFrom) {
                                    $subQ
                                        ->whereNull('effective_to')
                                        ->orWhere('effective_to', '>=', $effectiveFrom);
                                });
                        })->orWhere(function ($q) use ($effectiveFrom, $effectiveTo) {
                            // New rule ends within existing rule period
                            if ($effectiveTo) {
                                $q
                                    ->where('effective_from', '<=', $effectiveTo)
                                    ->where(function ($subQ) use ($effectiveTo) {
                                        $subQ
                                            ->whereNull('effective_to')
                                            ->orWhere('effective_to', '>=', $effectiveTo);
                                    });
                            }
                        });
                    })
                    ->exists();

                if ($conflictingRules) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Date range conflicts with existing active rule for this department.'
                    ], 422);
                }
            }

            // Validate effective date logic
            if ($effectiveTo && $effectiveTo <= $effectiveFrom) {
                return response()->json([
                    'success' => false,
                    'message' => 'Effective To date must be after Effective From date.'
                ], 422);
            }

            // If monthly KM limit is set but auto-approve is No, warn user
            if ($monthlyKmLimit && $validated['auto_approve_if_below_limit'] === 'N') {
                // This is allowed but we could log it or provide a warning
                \Log::info("Department {$department->department_name} has KM limit but auto-approve is disabled");
            }
        }

        // Prepare data for saving
        $data = [
            'department_id' => $department_id,
            'is_active' => $isActive,
            'auto_approve_if_below_limit' => $validated['auto_approve_if_below_limit'],
            'dept_active' => $validated['dept_active'],
            'monthly_km_limit' => $monthlyKmLimit,
            'effective_from' => $effectiveFrom,
            'effective_to' => $effectiveTo,
            'vertical_ids' => !empty($verticals) ? implode(',', $verticals) : null,
        ];

        try {
            $rule = OdoApprovalRule::updateOrCreate(
                ['department_id' => $department_id],
                $data
            );

            $action = $rule->wasRecentlyCreated ? 'created' : 'updated';

            return response()->json([
                'success' => true,
                'message' => "Access running KM setting {$action} successfully for {$department->department_name}.",
                'data' => $rule
            ]);
        } catch (\Exception $e) {
            \Log::error('Error saving access running KM setting: ' . $e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Failed to save access running KM setting. Please try again.'
            ], 500);
        }
    }
}
