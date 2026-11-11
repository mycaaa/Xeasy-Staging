{{-- Reusable Organogram & Policy Filter Off-Canvas Drawer (Server-Side Rendered) --}}
@php
    if (!isset($functions) || !isset($verticals) || !isset($departments)) {
        $organogramOptions = \App\Helpers\OrganogramFilterHelper::getOrganogramFilterOptions(session('company_id'));
        $functions          = $functions ?? ($organogramOptions['functions'] ?? collect([]));
        $verticals          = $verticals ?? ($organogramOptions['verticals'] ?? collect([]));
        $departments        = $departments ?? ($organogramOptions['departments'] ?? collect([]));
        $sub_departments    = $sub_departments ?? ($organogramOptions['sub_departments'] ?? collect([]));
        $business_units     = $business_units ?? ($organogramOptions['business_units'] ?? collect([]));
        $zones              = $zones ?? ($organogramOptions['zones'] ?? collect([]));
        $regions            = $regions ?? ($organogramOptions['regions'] ?? collect([]));
        $territories        = $territories ?? ($organogramOptions['territories'] ?? collect([]));
        $hqs                = $hqs ?? ($organogramOptions['hqs'] ?? collect([]));
        $eligibility_policy = $eligibility_policy ?? ($organogramOptions['eligibility_policy'] ?? collect([]));
    }

    $drawerId        = $drawerId ?? 'additionalFiltersCanvas';
    $drawerTitle     = $drawerTitle ?? 'Additional Filters';
    $resetBtnId      = $resetBtnId ?? 'resetFiltersBtn';
    $resetBtnLabel   = $resetBtnLabel ?? 'Reset Filters';
    $applyBtnId      = $applyBtnId ?? 'applyFiltersBtn';
    $applyBtnLabel   = $applyBtnLabel ?? 'Apply Filters';
    $applyBtnDismiss = $applyBtnDismiss ?? false;
    $buttonLayout    = $buttonLayout ?? 'stacked';
    $enforcePerms    = $enforcePermissions ?? false;
@endphp

<style>
    #{{ $drawerId }}, .organogram-filters-canvas {
        width: 520px;
        max-width: 95vw;
    }
    #{{ $drawerId }} .offcanvas-header, .organogram-filters-canvas .offcanvas-header {
        background: #00175f;
        color: #ffffff;
        padding: 1rem 1.25rem;
    }
    #{{ $drawerId }} .offcanvas-header .offcanvas-title, .organogram-filters-canvas .offcanvas-header .offcanvas-title {
        color: #ffffff;
        font-weight: 700;
        font-size: 1.1rem;
    }
    #{{ $drawerId }} .offcanvas-header .btn-close, .organogram-filters-canvas .offcanvas-header .btn-close {
        filter: invert(1);
    }
    #{{ $drawerId }} .select2-container, .organogram-filters-canvas .select2-container {
        width: 100% !important;
    }
    #{{ $drawerId }} .select2-container--default .select2-selection--multiple, .organogram-filters-canvas .select2-container--default .select2-selection--multiple {
        border-color: #cbd5e1;
        border-radius: 6px;
        min-height: 38px;
    }
    #{{ $drawerId }} .select2-container--default.select2-container--focus .select2-selection--multiple, .organogram-filters-canvas .select2-container--default.select2-container--focus .select2-selection--multiple {
        border-color: #00175f;
    }
    .btn-apply-organogram-filters {
        background-color: #00175f;
        border-color: #00175f;
        color: #ffffff;
        font-weight: 600;
        padding: 0.6rem 1rem;
        border-radius: 6px;
        transition: all 0.2s;
    }
    .btn-apply-organogram-filters:hover {
        background-color: #001047;
        border-color: #001047;
        color: #ffffff;
    }
</style>

<div class="offcanvas offcanvas-end organogram-filters-canvas" tabindex="-1" id="{{ $drawerId }}" aria-labelledby="{{ $drawerId }}Label">
    <div class="offcanvas-header">
        <h5 class="offcanvas-title text-white" id="{{ $drawerId }}Label">
            <i class="ri-equalizer-line me-1"></i>{{ $drawerTitle }}
        </h5>
        <button type="button" class="btn-close" data-bs-dismiss="offcanvas" aria-label="Close"></button>
    </div>
    <div class="offcanvas-body">
        <div class="row g-2 p-1">
            {{-- 1. Select Function --}}
            @if(!$enforcePerms || (auth()->check() && auth()->user()->can('function_filter')))
            <div class="col-md-6 mb-2">
                <label class="form-label small text-muted fw-bold mb-1">Select Function</label>
                <select class="form-select filter-select" id="functionSelect" multiple data-placeholder="Select Function">
                    <option value="" disabled>Select Function</option>
                    @foreach ($functions ?? [] as $key => $function)
                        @php
                            $fId = is_object($function) ? $function->id : (is_array($function) ? ($function['id'] ?? $key) : $key);
                            $fName = is_object($function) ? $function->function_name : (is_array($function) ? ($function['function_name'] ?? $function) : $function);
                        @endphp
                        <option value="{{ $fId }}">{{ $fName }}</option>
                    @endforeach
                </select>
            </div>
            @endif

            {{-- 2. Select Vertical --}}
            @if(!$enforcePerms || (auth()->check() && auth()->user()->can('vertical_filter')))
            <div class="col-md-6 mb-2">
                <label class="form-label small text-muted fw-bold mb-1">Select Vertical</label>
                <select class="form-select filter-select" id="verticalSelect" multiple data-placeholder="Select Vertical">
                    <option value="" disabled>Select Vertical</option>
                    @foreach ($verticals ?? [] as $key => $vertical)
                        @php
                            $vId = is_object($vertical) ? $vertical->id : (is_array($vertical) ? ($vertical['id'] ?? $key) : $key);
                            $vName = is_object($vertical) ? $vertical->vertical_name : (is_array($vertical) ? ($vertical['vertical_name'] ?? $vertical) : $vertical);
                        @endphp
                        <option value="{{ $vId }}">{{ $vName }}</option>
                    @endforeach
                </select>
            </div>
            @endif

            {{-- 3. Select Department --}}
            @if(!$enforcePerms || (auth()->check() && auth()->user()->can('department_filter')))
            <div class="col-md-6 mb-2">
                <label class="form-label small text-muted fw-bold mb-1">Select Department</label>
                <select class="form-select filter-select" id="departmentSelect" multiple data-placeholder="Select Department">
                    <option value="" disabled>Select Department</option>
                    @foreach ($departments ?? [] as $key => $department)
                        @php
                            $dId = is_object($department) ? $department->id : (is_array($department) ? ($department['id'] ?? $key) : $key);
                            $dName = is_object($department) ? $department->department_name : (is_array($department) ? ($department['department_name'] ?? $department) : $department);
                        @endphp
                        <option value="{{ $dId }}">{{ $dName }}</option>
                    @endforeach
                </select>
            </div>
            @endif

            {{-- 4. Select Sub-Department --}}
            @if(!$enforcePerms || (auth()->check() && auth()->user()->can('sub_department_filter')))
            <div class="col-md-6 mb-2">
                <label class="form-label small text-muted fw-bold mb-1">Select Sub-Department</label>
                <select class="form-select filter-select" id="subDepartmentSelect" multiple data-placeholder="Select Sub-Department">
                    <option value="" disabled>Select Sub-Department</option>
                    @foreach ($sub_departments ?? [] as $key => $sub_department)
                        @php
                            $sdId = is_object($sub_department) ? $sub_department->id : (is_array($sub_department) ? ($sub_department['id'] ?? $key) : $key);
                            $sdName = is_object($sub_department) ? $sub_department->sub_department_name : (is_array($sub_department) ? ($sub_department['sub_department_name'] ?? $sub_department) : $sub_department);
                        @endphp
                        <option value="{{ $sdId }}">{{ $sdName }}</option>
                    @endforeach
                </select>
            </div>
            @endif

            {{-- 5. Select Business Unit (BU) --}}
            <div class="col-md-6 mb-2">
                <label class="form-label small text-muted fw-bold mb-1">Select Business Unit (BU)</label>
                <select class="form-select filter-select" id="buSelect" multiple data-placeholder="Select BU">
                    <option value="" disabled>Select BU</option>
                    @foreach ($business_units ?? [] as $key => $bu)
                        @php
                            $buId = is_object($bu) ? $bu->id : (is_array($bu) ? ($bu['id'] ?? $key) : $key);
                            $buName = is_object($bu) ? $bu->business_unit_name : (is_array($bu) ? ($bu['business_unit_name'] ?? $bu) : $bu);
                        @endphp
                        <option value="{{ $buId }}">{{ $buName }}</option>
                    @endforeach
                </select>
            </div>

            {{-- 6. Select Zone --}}
            <div class="col-md-6 mb-2">
                <label class="form-label small text-muted fw-bold mb-1">Select Zone</label>
                <select class="form-select filter-select" id="zoneSelect" multiple data-placeholder="Select Zone">
                    <option value="" disabled>Select Zone</option>
                    @foreach ($zones ?? [] as $key => $zone)
                        @php
                            $zId = is_object($zone) ? $zone->id : (is_array($zone) ? ($zone['id'] ?? $key) : $key);
                            $zName = is_object($zone) ? $zone->zone_name : (is_array($zone) ? ($zone['zone_name'] ?? $zone) : $zone);
                        @endphp
                        <option value="{{ $zId }}">{{ $zName }}</option>
                    @endforeach
                </select>
            </div>

            {{-- 7. Select Region --}}
            <div class="col-md-6 mb-2">
                <label class="form-label small text-muted fw-bold mb-1">Select Region</label>
                <select class="form-select filter-select" id="regionSelect" multiple data-placeholder="Select Region">
                    <option value="" disabled>Select Region</option>
                    @foreach ($regions ?? [] as $key => $region)
                        @php
                            $rId = is_object($region) ? $region->id : (is_array($region) ? ($region['id'] ?? $key) : $key);
                            $rName = is_object($region) ? $region->region_name : (is_array($region) ? ($region['region_name'] ?? $region) : $region);
                        @endphp
                        <option value="{{ $rId }}">{{ $rName }}</option>
                    @endforeach
                </select>
            </div>

            {{-- 8. Select Territory --}}
            <div class="col-md-6 mb-2">
                <label class="form-label small text-muted fw-bold mb-1">Select Territory</label>
                <select class="form-select filter-select" id="territorySelect" multiple data-placeholder="Select Territory">
                    <option value="" disabled>Select Territory</option>
                    @foreach ($territories ?? [] as $key => $territory)
                        @php
                            $tId = is_object($territory) ? $territory->id : (is_array($territory) ? ($territory['id'] ?? $key) : $key);
                            $tName = is_object($territory) ? $territory->territory_name : (is_array($territory) ? ($territory['territory_name'] ?? $territory) : $territory);
                        @endphp
                        <option value="{{ $tId }}">{{ $tName }}</option>
                    @endforeach
                </select>
            </div>

            {{-- 9. Select Loc / HQ --}}
            <div class="col-md-6 mb-2">
                <label class="form-label small text-muted fw-bold mb-1">Select Loc / HQ</label>
                <select class="form-select filter-select" id="hqSelect" multiple data-placeholder="Select HQ">
                    <option value="" disabled>Select HQ</option>
                    @foreach ($hqs ?? [] as $key => $hq)
                        @php
                            $hqId = is_object($hq) ? $hq->id : (is_array($hq) ? ($hq['id'] ?? $key) : $key);
                            $hqName = is_object($hq) ? $hq->city_village_name : (is_array($hq) ? ($hq['city_village_name'] ?? $hq) : $hq);
                        @endphp
                        <option value="{{ $hqId }}">{{ $hqName }}</option>
                    @endforeach
                </select>
            </div>

            {{-- 10. Select Policy --}}
            @if(!$enforcePerms || (auth()->check() && auth()->user()->can('policy_filter')))
            <div class="col-md-6 mb-2">
                <label class="form-label small text-muted fw-bold mb-1">Select Policy</label>
                <select class="form-select filter-select" id="policySelect" multiple data-placeholder="Select Policy">
                    <option value="" disabled>Select Policy</option>
                    @foreach ($eligibility_policy ?? [] as $key => $policy)
                        @php
                            $pId = is_object($policy) ? ($policy->PolicyId ?? $policy->id ?? $key) : (is_array($policy) ? ($policy['PolicyId'] ?? $policy['id'] ?? $key) : $key);
                            $pName = is_object($policy) ? ($policy->PolicyName ?? $policy->name ?? $policy) : (is_array($policy) ? ($policy['PolicyName'] ?? $policy['name'] ?? $policy) : $policy);
                        @endphp
                        <option value="{{ $pId }}">{{ $pName }}</option>
                    @endforeach
                </select>
            </div>
            @endif

            {{-- Report-specific additional filters partial / string / slot --}}
            @if(isset($extraFieldsView))
                @include($extraFieldsView, $extraFieldsData ?? [])
            @endif
            @if(isset($extraFields))
                {!! $extraFields !!}
            @endif
            {{ $slot ?? '' }}
        </div>

        @if($buttonLayout === 'row')
        <div class="row p-1 mt-3">
            <div class="col-6">
                <button type="button" id="{{ $resetBtnId }}" class="btn btn-outline-secondary w-100">
                    <i class="ri-refresh-line me-1"></i>{{ $resetBtnLabel }}
                </button>
            </div>
            <div class="col-6">
                <button type="button" id="{{ $applyBtnId }}" class="btn btn-primary w-100" {{ !empty($applyBtnDismiss) ? 'data-bs-dismiss=offcanvas' : '' }}>
                    <i class="ri-check-line me-1"></i>{{ $applyBtnLabel }}
                </button>
            </div>
        </div>
        @else
        <div class="row p-1 mt-3">
            <div class="col-12">
                <button type="button" id="{{ $applyBtnId }}" class="btn btn-apply-organogram-filters w-100 shadow-sm" {{ !empty($applyBtnDismiss) ? 'data-bs-dismiss=offcanvas' : '' }}>
                    <i class="ri-check-line me-1"></i>{{ $applyBtnLabel }}
                </button>
                <button type="button" id="{{ $resetBtnId }}" class="btn btn-outline-secondary w-100 mt-2">
                    <i class="ri-refresh-line me-1"></i>{{ $resetBtnLabel }}
                </button>
            </div>
        </div>
        @endif
    </div>
</div>
