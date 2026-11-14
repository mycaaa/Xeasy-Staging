@extends('layouts.app')
@section('content')
    <div class="page-content">
        <div class="container-fluid">
            <div class="row">
                <div class="col-lg-12">
                    <div class="card">
                        <div class="card-header align-items-center d-flex">
                            <h4 class="card-title mb-0 flex-grow-1">Advance Request Master Settings</h4>
                        </div>
                        <div class="card-body">
                            <!-- Tabs for different advance types -->
                            <ul class="nav nav-tabs nav-justified" role="tablist">
                                @foreach ($advanceTypes as $index => $type)
                                    <li class="nav-item">
                                        <a class="nav-link {{ $index === 0 ? 'active' : '' }}" data-bs-toggle="tab"
                                            href="#tab-{{ $type->id }}" role="tab">
                                            <span class="d-block d-sm-none"><i class="fas fa-cog"></i></span>
                                            <span class="d-none d-sm-block">{{ $type->type_name }}</span>
                                        </a>
                                    </li>
                                @endforeach
                            </ul>

                            <!-- Tab content -->
                            <div class="tab-content p-3 text-muted">
                                @foreach ($advanceTypes as $index => $type)
                                    <div class="tab-pane {{ $index === 0 ? 'active' : '' }}" id="tab-{{ $type->id }}"
                                        role="tabpanel">

                                        @if ($type->id == 4)
                                            <!-- Auto Advance Settings (Special Layout) -->
                                            <div class="table-responsive">
                                                <table
                                                    class="table nowrap dt-responsive align-middle table-hover table-bordered advance-request-master-table"
                                                    style="width:100%">
                                                    <thead class="table-light">
                                                        <tr>
                                                            <th style="width:3%;">Sr No.</th>
                                                            <th style="width:18%; text-align:left;">Department</th>
                                                            <th style="width:8%;">Enable Check</th>
                                                            <th style="width:18%;">Management</th>
                                                            <th style="width:12%;">Effective From</th>
                                                            <th style="width:12%;">Effective To</th>
                                                            <th style="width:10%;">Expense %</th>
                                                            <th style="width:8%;">Action</th>
                                                        </tr>
                                                    </thead>
                                                    <tbody>
                                                        @foreach ($departments as $department)
                                                            @php
                                                                $setting =
                                                                    isset($settings[$type->id]) &&
                                                                    isset($settings[$type->id][$department->id])
                                                                        ? $settings[$type->id][$department->id]
                                                                        : null;
                                                            @endphp
                                                            <tr data-advance-type-id="{{ $type->id }}"
                                                                data-department-id="{{ $department->id }}">
                                                                <td>{{ $loop->iteration }}</td>
                                                                <td style="text-align: left;">
                                                                    {{ $department->department_name }}</td>
                                                                <td>
                                                                    <div class="form-check form-switch text-center">
                                                                        <input class="form-check-input is-active-switch"
                                                                            type="checkbox"
                                                                            data-advance-type-id="{{ $type->id }}"
                                                                            data-department-id="{{ $department->id }}"
                                                                            {{ $setting && ($setting->is_active == 1 || $setting->is_active === true) ? 'checked' : '' }}>
                                                                    </div>
                                                                </td>
                                                                <td style="white-space: normal;width: 18%;">
                                                                    <button type="button" 
                                                                        class="btn btn-outline-primary btn-sm management-select-btn"
                                                                        data-advance-type-id="{{ $type->id }}"
                                                                        data-department-id="{{ $department->id }}"
                                                                        data-department-name="{{ $department->department_name }}">
                                                                        <i class="ri-team-line"></i> Select Management
                                                                    </button>
                                                                    <input type="hidden" class="selected-managers-data"
                                                                        data-advance-type-id="{{ $type->id }}"
                                                                        data-department-id="{{ $department->id }}"
                                                                        value="{{ $setting && $setting->management_data ? json_encode($setting->management_data) : '[]' }}">
                                                                </td>
                                                                <td>
                                                                    <input type="date"
                                                                        class="form-control effective-from-input"
                                                                        data-advance-type-id="{{ $type->id }}"
                                                                        data-department-id="{{ $department->id }}"
                                                                        placeholder="DD-MM-YYYY"
                                                                        value="{{ $setting ? $setting->formatted_effective_from : '' }}">
                                                                </td>
                                                                <td>
                                                                    <input type="date"
                                                                        class="form-control effective-to-input"
                                                                        data-advance-type-id="{{ $type->id }}"
                                                                        data-department-id="{{ $department->id }}"
                                                                        placeholder="DD-MM-YYYY"
                                                                        value="{{ $setting ? $setting->formatted_effective_to : '' }}">
                                                                </td>
                                                                <td>
                                                                    <input type="number"
                                                                        class="form-control expense-percentage-input"
                                                                        data-advance-type-id="{{ $type->id }}"
                                                                        data-department-id="{{ $department->id }}"
                                                                        placeholder="0" step="1" min="0" max="100"
                                                                        value="{{ $setting && $setting->expense_percentage ? $setting->expense_percentage : '' }}">
                                                                </td>
                                                                <td>
                                                                    <button class="btn btn-primary btn-sm save-btn"
                                                                        data-advance-type-id="{{ $type->id }}"
                                                                        data-department-id="{{ $department->id }}">
                                                                        <i class="ri-save-3-line"></i>
                                                                    </button>
                                                                </td>
                                                            </tr>
                                                        @endforeach
                                                    </tbody>
                                                </table>
                                            </div>
                                        @elseif ($type->access_mode === 'DEPARTMENT')
                                            <!-- Department-based table for Act and Exp -->
                                            <div class="table-responsive">
                                                <table
                                                    class="table nowrap dt-responsive align-middle table-hover table-bordered advance-request-master-table"
                                                    style="width:100%">
                                                    <thead class="table-light">
                                                        <tr>
                                                            <th style="vertical-align: middle;width:3%;">Sr No.</th>
                                                            <th style="vertical-align: middle;width:15%; text-align:left;">Department</th>
                                                            <th style="vertical-align: middle;width:8%;">Enable Check</th>
                                                            @if ($type->type_code === 'Act')
                                                                <th style="vertical-align: middle;width:15%;">Designation</th>
                                                                {{-- <th style="vertical-align: middle;width:12%;">Verticals</th> --}}
                                                            @endif
                                                            @if ($type->type_code === 'Exp')
                                                                <th style="vertical-align: middle;width:12%;">Not Allow Grades</th>
                                                                <th style="vertical-align: middle;width:8%;">Tour & Travel</th>
                                                                <th style="vertical-align: middle;width:8%;">Allow Expense</th>
                                                                <th style="vertical-align: middle;width:12%;">Verticals</th>
                                                            @endif
                                                            <th style="vertical-align: middle;width:8%;">Approver Level</th>
                                                            <th style="vertical-align: middle;width:10%;">Effective From</th>
                                                            <th style="vertical-align: middle;width:10%;">Effective To</th>
                                                            <th style="vertical-align: middle;width:8%;">Min Amount</th>
                                                            <th style="vertical-align: middle;width:8%;">Max Amount</th>
                                                            <th style="vertical-align: middle;width:8%;">Action</th>
                                                        </tr>
                                                    </thead>
                                                    <tbody>
                                                        @foreach ($departments as $department)
                                                            @php
                                                                $setting =
                                                                    isset($settings[$type->id]) &&
                                                                    isset($settings[$type->id][$department->id])
                                                                        ? $settings[$type->id][$department->id]
                                                                        : null;
                                                                $limit =
                                                                    isset($limits[$type->id]) &&
                                                                    isset($limits[$type->id][$department->id])
                                                                        ? $limits[$type->id][$department->id]
                                                                        : null;
                                                            @endphp
                                                            <tr data-advance-type-id="{{ $type->id }}"
                                                                data-department-id="{{ $department->id }}">
                                                                <td>{{ $loop->iteration }}</td>
                                                                <td style="text-align: left;">
                                                                    {{ $department->department_name }}</td>
                                                                <td>
                                                                    <div class="form-check form-switch text-center">
                                                                        <input class="form-check-input is-active-switch"
                                                                            type="checkbox"
                                                                            data-advance-type-id="{{ $type->id }}"
                                                                            data-department-id="{{ $department->id }}"
                                                                            {{ $setting && ($setting->is_active == 1 || $setting->is_active === true) ? 'checked' : '' }}>
                                                                    </div>
                                                                </td>
                                                                @if ($type->type_code === 'Act')
                                                                    <td style="white-space: normal;width: 15%;">
                                                                        @if (isset($mappedDesignationDetails[$department->id]) && $mappedDesignationDetails[$department->id]->isNotEmpty())
                                                                            @php
                                                                                // Use vertical_designation (new format) or fall back to designation_ids (old format)
                                                                                $savedVerticalDesignation =
                                                                                    $setting &&
                                                                                    $setting->vertical_designation
                                                                                        ? $setting->vertical_designation
                                                                                        : [];
                                                                                
                                                                                // For backward compatibility, also check old designation_ids format
                                                                                $savedDesignations =
                                                                                    $setting &&
                                                                                    $setting->designation_ids
                                                                                        ? $setting->designation_ids
                                                                                        : [];
                                                                                
                                                                                $selectedNames = [];
                                                                                foreach (
                                                                                    $mappedDesignationDetails[
                                                                                        $department->id
                                                                                    ]
                                                                                    as $designation
                                                                                ) {
                                                                                    if (
                                                                                        in_array(
                                                                                            $designation->id,
                                                                                            $savedDesignations,
                                                                                        )
                                                                                    ) {
                                                                                        $selectedNames[] =
                                                                                            $designation->designation_name;
                                                                                    }
                                                                                }
                                                                            @endphp
                                                                            <input type="text"
                                                                                class="form-control designation-input"
                                                                                data-advance-type-id="{{ $type->id }}"
                                                                                data-department-id="{{ $department->id }}"
                                                                                placeholder="Click to select designations"
                                                                                value="{{ implode(', ', $selectedNames) }}"
                                                                                readonly style="cursor: pointer;">
                                                                            <input type="hidden" class="designation-ids"
                                                                                data-advance-type-id="{{ $type->id }}"
                                                                                data-department-id="{{ $department->id }}"
                                                                                value="{{ json_encode($savedVerticalDesignation ?: []) }}">
                                                                        @else
                                                                            <span>No designations mapped</span>
                                                                        @endif
                                                                    </td>
                                                                    {{-- <td style="white-space: normal;width: 12%;">
                                                                        @if (isset($mappedVerticalDetails[$department->id]) && $mappedVerticalDetails[$department->id]->isNotEmpty())
                                                                            @php
                                                                                $savedVerticals =
                                                                                    $setting && $setting->vertical_ids
                                                                                        ? $setting->vertical_ids
                                                                                        : [];
                                                                                // Filter out null verticals (departments with no mappings)
                                                                                $departmentVerticals = $mappedVerticalDetails[$department->id]->filter(function($vertical) {
                                                                                    return !is_null($vertical->id);
                                                                                });
                                                                            @endphp
                                                                            @if  ($departmentVerticals->isNotEmpty())
                                                                                    @foreach ($departmentVerticals as $vertical)
                                                                                        <div class="form-check form-check-inline">
                                                                                            <input class="form-check-input vertical-checkbox" 
                                                                                                type="checkbox" 
                                                                                                value="{{ $vertical->id }}"
                                                                                                id="vertical_{{ $type->id }}_{{ $department->id }}_{{ $vertical->id }}"
                                                                                                data-advance-type-id="{{ $type->id }}"
                                                                                                data-department-id="{{ $department->id }}"
                                                                                                {{ in_array($vertical->id, $savedVerticals) ? 'checked' : '' }}>
                                                                                            <label class="form-check-label" for="vertical_{{ $type->id }}_{{ $department->id }}_{{ $vertical->id }}">
                                                                                                {{ $vertical->vertical_code }}
                                                                                            </label>
                                                                                        </div>
                                                                                    @endforeach
                                                                            @else
                                                                                <span class="text-muted">No verticals</span>
                                                                            @endif
                                                                        @else
                                                                            <span class="text-muted">No verticals</span>
                                                                        @endif
                                                                    </td> --}}
                                                                @endif
                                                                @if ($type->type_code === 'Exp')
                                                                    <td style="white-space: normal;width: 15%;">
                                                                        @php
                                                                            $savedGrades =
                                                                                $setting && $setting->grade_ids
                                                                                    ? $setting->grade_ids
                                                                                    : [];
                                                                            $selectedGradeNames = [];
                                                                            foreach ($grades as $grade) {
                                                                                if (in_array($grade->id, $savedGrades)) {
                                                                                    $selectedGradeNames[] = $grade->grade_name;
                                                                                }
                                                                            }
                                                                        @endphp
                                                                        <input type="text"
                                                                            class="form-control grade-input"
                                                                            data-advance-type-id="{{ $type->id }}"
                                                                            data-department-id="{{ $department->id }}"
                                                                            placeholder="Click to select grades"
                                                                            value="{{ implode(', ', $selectedGradeNames) }}"
                                                                            readonly style="cursor: pointer;">
                                                                        <input type="hidden" class="grade-ids"
                                                                            data-advance-type-id="{{ $type->id }}"
                                                                            data-department-id="{{ $department->id }}"
                                                                            value="{{ json_encode($savedGrades) }}">
                                                                    </td>
                                                                    <td style="text-align: center;">
                                                                        <div class="form-check form-switch d-inline-block">
                                                                            <input class="form-check-input tour-travel-checkbox" 
                                                                                type="checkbox"
                                                                                data-advance-type-id="{{ $type->id }}"
                                                                                data-department-id="{{ $department->id }}"
                                                                                {{ $setting && $setting->is_tour_travel ? 'checked' : '' }}>
                                                                        </div>
                                                                    </td>
                                                                    <td style="text-align: center;">
                                                                        <div class="form-check form-switch d-inline-block">
                                                                            <input class="form-check-input allow-expense-checkbox" 
                                                                                type="checkbox"
                                                                                data-advance-type-id="{{ $type->id }}"
                                                                                data-department-id="{{ $department->id }}"
                                                                                {{ $setting && $setting->allow_expense ? 'checked' : '' }}>
                                                                        </div>
                                                                    </td>
                                                                    <td style="white-space: normal;width: 25%;">
                                                                        @if (isset($mappedVerticalDetails[$department->id]) && $mappedVerticalDetails[$department->id]->isNotEmpty())
                                                                            @php
                                                                                $savedVerticals =
                                                                                    $setting && $setting->vertical_ids
                                                                                        ? $setting->vertical_ids
                                                                                        : [];
                                                                                // Filter out null verticals (departments with no mappings)
                                                                                $departmentVerticals = $mappedVerticalDetails[$department->id]->filter(function($vertical) {
                                                                                    return !is_null($vertical->id);
                                                                                });
                                                                            @endphp
                                                                            @if  ($departmentVerticals->isNotEmpty())
                                                                                    @foreach ($departmentVerticals as $vertical)
                                                                                        <div class="form-check form-check-inline">
                                                                                            <input class="form-check-input vertical-checkbox" 
                                                                                                type="checkbox" 
                                                                                                value="{{ $vertical->id }}"
                                                                                                id="vertical_{{ $type->id }}_{{ $department->id }}_{{ $vertical->id }}"
                                                                                                data-advance-type-id="{{ $type->id }}"
                                                                                                data-department-id="{{ $department->id }}"
                                                                                                {{ in_array($vertical->id, $savedVerticals) ? 'checked' : '' }}>
                                                                                            <label class="form-check-label" for="vertical_{{ $type->id }}_{{ $department->id }}_{{ $vertical->id }}">
                                                                                                {{ $vertical->vertical_code }}
                                                                                            </label>
                                                                                        </div>
                                                                                    @endforeach
                                                                            @else
                                                                                <span class="text-muted">No verticals</span>
                                                                            @endif
                                                                        @else
                                                                            <span class="text-muted">No verticals</span>
                                                                        @endif
                                                                    </td>
                                                                @endif
                                                                <td>
                                                                    <select class="form-select approver-level-select"
                                                                        data-advance-type-id="{{ $type->id }}"
                                                                        data-department-id="{{ $department->id }}">
                                                                        <option value="">Select Level</option>
                                                                        <option value="1"
                                                                            {{ $setting && $setting->approver_level == 1 ? 'selected' : '' }}>
                                                                            Level 1</option>
                                                                        <option value="2"
                                                                            {{ $setting && $setting->approver_level == 2 ? 'selected' : '' }}>
                                                                            Level 2</option>
                                                                        <option value="3"
                                                                            {{ $setting && $setting->approver_level == 3 ? 'selected' : '' }}>
                                                                            Level 3</option>
                                                                    </select>
                                                                </td>
                                                                <td>
                                                                    <input type="date"
                                                                        class="form-control effective-from-input"
                                                                        data-advance-type-id="{{ $type->id }}"
                                                                        data-department-id="{{ $department->id }}"
                                                                        placeholder="DD-MM-YYYY"
                                                                        value="{{ $setting ? $setting->formatted_effective_from : '' }}">
                                                                </td>
                                                                <td>
                                                                    <input type="date"
                                                                        class="form-control effective-to-input"
                                                                        data-advance-type-id="{{ $type->id }}"
                                                                        data-department-id="{{ $department->id }}"
                                                                        placeholder="DD-MM-YYYY"
                                                                        value="{{ $setting ? $setting->formatted_effective_to : '' }}">
                                                                </td>
                                                                @if ($type->type_code === 'Act')
                                                                    <td colspan="2" class="text-center">
                                                                        <button type="button"
                                                                            class="btn btn-outline-primary btn-sm activity-limits-btn"
                                                                            data-advance-type-id="{{ $type->id }}"
                                                                            data-department-id="{{ $department->id }}"
                                                                            data-department-name="{{ $department->department_name }}">
                                                                            <i class="ri-settings-3-line"></i> Activity
                                                                            Limits
                                                                        </button>
                                                                    </td>
                                                                @else
                                                                    <td>
                                                                        <input type="number"
                                                                            class="form-control min-amount-input"
                                                                            data-advance-type-id="{{ $type->id }}"
                                                                            data-department-id="{{ $department->id }}"
                                                                            placeholder="0.00" step="0.01" min="0"
                                                                            max="999999"
                                                                            value="{{ $limit ? $limit->min_amount : '' }}">
                                                                    </td>
                                                                    <td>
                                                                        <input type="number"
                                                                            class="form-control max-amount-input"
                                                                            data-advance-type-id="{{ $type->id }}"
                                                                            data-department-id="{{ $department->id }}"
                                                                            placeholder="0.00" step="0.01" min="0"
                                                                            max="999999"
                                                                            value="{{ $limit ? $limit->max_amount : '' }}">
                                                                    </td>
                                                                @endif
                                                                <td>
                                                                    <button class="btn btn-primary btn-sm save-btn"
                                                                        data-advance-type-id="{{ $type->id }}"
                                                                        data-department-id="{{ $department->id }}">
                                                                        <i class="ri-save-3-line"></i>
                                                                    </button>
                                                                </td>
                                                            </tr>
                                                        @endforeach
                                                    </tbody>
                                                </table>
                                            </div>
                                        @else
                                            <!-- Employee-based section for Exp and OTHER -->
                                            <div class="row">
                                                <div class="col-12">
                                                    <div class="card">
                                                        <div
                                                            class="card-header align-items-center d-flex justify-content-between">
                                                            <h5 class="card-title mb-0">{{ $type->type_name }} Settings
                                                            </h5>
                                                            <button type="button"
                                                                class="btn btn-success btn-sm float-end"
                                                                id="addEmployeeRow"
                                                                data-advance-type-id="{{ $type->id }}">
                                                                <i class="ri-add-line"></i> Add Employee
                                                            </button>
                                                        </div>
                                                        <div class="card-body">
                                                            <div class="table-responsive">
                                                                <table class="table table-bordered employee-settings-table"
                                                                    data-advance-type-id="{{ $type->id }}">
                                                                    <thead class="table-light">
                                                                        <tr>
                                                                            <th style="width:5%;">Sr No.</th>
                                                                            <th style="width:10%;">Enable</th>
                                                                            <th style="width:25%;">Employee</th>
                                                                            <th style="width:10%;">Request For</th>
                                                                            <th style="width:15%;">Approver Level</th>
                                                                            <th style="width:12%;">Effective From</th>
                                                                            <th style="width:12%;">Effective To</th>
                                                                            <th style="width:11%;">Action</th>
                                                                        </tr>
                                                                    </thead>
                                                                    <tbody>
                                                                        @php
                                                                            $typeSettings =
                                                                                isset($settings[$type->id]) &&
                                                                                $settings[$type->id]->isNotEmpty()
                                                                                    ? $settings[$type->id]
                                                                                    : collect();
                                                                        @endphp

                                                                        @if ($typeSettings->isNotEmpty())
                                                                            @foreach ($typeSettings as $setting)
                                                                                @if ($setting->employee_ids)
                                                                                    @foreach ($setting->employee_ids as $employeeId)
                                                                                        @php
                                                                                            $employee = $employees
                                                                                                ->where(
                                                                                                    'EmployeeID',
                                                                                                    $employeeId,
                                                                                                )
                                                                                                ->first();
                                                                                        @endphp
                                                                                        @if ($employee)
                                                                                            <tr data-advance-type-id="{{ $type->id }}"
                                                                                                data-employee-id="{{ $employeeId }}"
                                                                                                data-setting-id="{{ $setting->id }}">
                                                                                                <td>{{ $loop->iteration }}
                                                                                                </td>
                                                                                                <td>
                                                                                                    <div
                                                                                                        class="form-check form-switch text-center">
                                                                                                        <input
                                                                                                            class="form-check-input is-active-switch"
                                                                                                            type="checkbox"
                                                                                                            data-advance-type-id="{{ $type->id }}"
                                                                                                            data-employee-id="{{ $employeeId }}"
                                                                                                            {{ $setting->is_active ? 'checked' : '' }}>
                                                                                                    </div>
                                                                                                </td>
                                                                                                <td>{{ trim($employee->Fname . ' ' . $employee->Sname . ' ' . $employee->Lname) }}
                                                                                                    ({{ $employee->EmpCode }})
                                                                                                </td>
                                                                                                <td>
                                                                                                    <div class="d-flex gap-2">
                                                                                                        <div class="form-check">
                                                                                                            <input class="form-check-input request-for-checkbox" 
                                                                                                                type="checkbox" 
                                                                                                                value="Act"
                                                                                                                data-advance-type-id="{{ $type->id }}"
                                                                                                                data-employee-id="{{ $employeeId }}"
                                                                                                                {{ $setting->request_for && in_array('Act', $setting->request_for) ? 'checked' : '' }}>
                                                                                                            <label class="form-check-label">Act</label>
                                                                                                        </div>
                                                                                                        <div class="form-check">
                                                                                                            <input class="form-check-input request-for-checkbox" 
                                                                                                                type="checkbox" 
                                                                                                                value="Exp"
                                                                                                                data-advance-type-id="{{ $type->id }}"
                                                                                                                data-employee-id="{{ $employeeId }}"
                                                                                                                {{ $setting->request_for && in_array('Exp', $setting->request_for) ? 'checked' : '' }}>
                                                                                                            <label class="form-check-label">Exp</label>
                                                                                                        </div>
                                                                                                    </div>
                                                                                                </td>
                                                                                                <td>
                                                                                                    <select
                                                                                                        class="form-select approver-level-select"
                                                                                                        data-advance-type-id="{{ $type->id }}"
                                                                                                        data-employee-id="{{ $employeeId }}">
                                                                                                        <option
                                                                                                            value="">
                                                                                                            Select Level
                                                                                                        </option>
                                                                                                        <option
                                                                                                            value="1"
                                                                                                            {{ $setting->approver_level == 1 ? 'selected' : '' }}>
                                                                                                            Level 1</option>
                                                                                                        <option
                                                                                                            value="2"
                                                                                                            {{ $setting->approver_level == 2 ? 'selected' : '' }}>
                                                                                                            Level 2</option>
                                                                                                        <option
                                                                                                            value="3"
                                                                                                            {{ $setting->approver_level == 3 ? 'selected' : '' }}>
                                                                                                            Level 3</option>
                                                                                                    </select>
                                                                                                </td>
                                                                                                <td>
                                                                                                    <input type="date"
                                                                                                        class="form-control effective-from-input"
                                                                                                        data-advance-type-id="{{ $type->id }}"
                                                                                                        data-employee-id="{{ $employeeId }}"
                                                                                                        value="{{ $setting->formatted_effective_from }}">
                                                                                                </td>
                                                                                                <td>
                                                                                                    <input type="date"
                                                                                                        class="form-control effective-to-input"
                                                                                                        data-advance-type-id="{{ $type->id }}"
                                                                                                        data-employee-id="{{ $employeeId }}"
                                                                                                        value="{{ $setting->formatted_effective_to }}">
                                                                                                </td>
                                                                                                <td>
                                                                                                    <button
                                                                                                        class="btn btn-primary btn-sm save-btn me-1"
                                                                                                        data-advance-type-id="{{ $type->id }}"
                                                                                                        data-employee-id="{{ $employeeId }}">
                                                                                                        <i
                                                                                                            class="ri-save-3-line"></i>
                                                                                                    </button>
                                                                                                    <button
                                                                                                        class="btn btn-danger btn-sm delete-btn"
                                                                                                        data-advance-type-id="{{ $type->id }}"
                                                                                                        data-employee-id="{{ $employeeId }}">
                                                                                                        <i
                                                                                                            class="ri-delete-bin-line"></i>
                                                                                                    </button>
                                                                                                </td>
                                                                                            </tr>
                                                                                        @endif
                                                                                    @endforeach
                                                                                @endif
                                                                            @endforeach
                                                                        @else
                                                                            <tr>
                                                                                <td colspan="8"
                                                                                    class="text-center text-muted">No
                                                                                    employees configured. Click "Add
                                                                                    Employee" to start.</td>
                                                                            </tr>
                                                                        @endif
                                                                    </tbody>
                                                                </table>
                                                            </div>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                        @endif
                                    </div>
                                @endforeach
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            {{-- ADV-FX-ELIG-01 (14 Nov 2026): who may request an advance in a foreign currency --}}
            @php
                $fxRules = \App\Services\Advance\FxEligibility::rules((int) session('company_id', 1));
                $fxEmpCodes = $fxRules['employees']
                    ? \Illuminate\Support\Facades\DB::connection('hrims')->table('hrm_employee')->whereIn('EmployeeID', $fxRules['employees'])->pluck('EmpCode')->implode(', ')
                    : '';
            @endphp
            <div class="row">
                <div class="col-lg-12">
                    <div class="card" id="fxEligibilityCard">
                        <div class="card-header align-items-center d-flex">
                            <h4 class="card-title mb-0 flex-grow-1"><i class="ri-exchange-dollar-line me-1"></i>Foreign currency eligibility</h4>
                        </div>
                        <div class="card-body">
                            <p class="text-muted small mb-3">Only these employees see currencies other than INR on New Advance Request, and a request in another currency is refused for anyone else on it (self or team members). Nobody is eligible until this is set.</p>
                            <form id="fxEligibilityForm" class="row g-3">
                                @csrf
                                <div class="col-12">
                                    <div class="form-check form-switch">
                                        <input class="form-check-input" type="checkbox" id="fxAll" name="all_employees" value="1" {{ $fxRules['all'] ? 'checked' : '' }}>
                                        <label class="form-check-label" for="fxAll">All employees of this company</label>
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label" for="fxDepartments">Departments</label>
                                    <select class="form-select" id="fxDepartments" name="department_ids[]" multiple size="6">
                                        @foreach ($departments as $d)
                                            <option value="{{ $d->id }}" {{ in_array((int) $d->id, $fxRules['departments'], true) ? 'selected' : '' }}>{{ $d->department_name }}</option>
                                        @endforeach
                                    </select>
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label" for="fxGrades">Grades</label>
                                    <select class="form-select" id="fxGrades" name="grade_ids[]" multiple size="6">
                                        @foreach ($grades as $g)
                                            <option value="{{ $g->id }}" {{ in_array((int) $g->id, $fxRules['grades'], true) ? 'selected' : '' }}>{{ $g->grade_name }}</option>
                                        @endforeach
                                    </select>
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label" for="fxEmployees">Employees (codes, comma separated)</label>
                                    <textarea class="form-control" id="fxEmployees" name="employee_codes" rows="5" placeholder="e.g. 97, 1813">{{ $fxEmpCodes }}</textarea>
                                </div>
                                <div class="col-12 d-flex align-items-center gap-2">
                                    <button type="submit" class="btn btn-primary btn-sm"><i class="ri-save-line me-1"></i>Save eligibility</button>
                                    <span class="small" id="fxEligibilityMsg" role="status"></span>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Designation Selection Modal -->
    <div class="modal fade" id="designationModal" tabindex="-1" aria-labelledby="designationModalLabel"
        aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="designationModalLabel">Select Designations</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <input type="text" class="form-control" id="designationSearch"
                                placeholder="Search designations..." aria-label="Search designations">
                        </div>
                        <div class="col-md-6">
                            <button type="button" class="btn btn-outline-secondary btn-sm" id="selectAllDesignations">
                                <i class="ri-check-double-line"></i> Select All
                            </button>
                            <button type="button" class="btn btn-outline-secondary btn-sm ms-2"
                                id="clearAllDesignations">
                                <i class="ri-close-line"></i> Clear All
                            </button>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-12">
                            <div class="designation-list"
                                style="max-height: 400px; overflow-y: auto; border: 1px solid #e9ecef; border-radius: 0.375rem; padding: 10px;">
                                <!-- Designations will be populated here -->
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="button" class="btn btn-primary" id="confirmDesignationSelection">
                        <i class="ri-check-line"></i> Confirm Selection
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Grade Selection Modal -->
    <div class="modal fade" id="gradeModal" tabindex="-1" aria-labelledby="gradeModalLabel"
        aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="gradeModalLabel">Select Grades (Not Allowed for Expense Advance)</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <input type="text" class="form-control" id="gradeSearch"
                                placeholder="Search grades..." aria-label="Search grades">
                        </div>
                        <div class="col-md-6">
                            <button type="button" class="btn btn-outline-secondary btn-sm" id="selectAllGrades">
                                <i class="ri-check-double-line"></i> Select All
                            </button>
                            <button type="button" class="btn btn-outline-secondary btn-sm ms-2"
                                id="clearAllGrades">
                                <i class="ri-close-line"></i> Clear All
                            </button>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-12">
                            <div class="grade-list"
                                style="max-height: 400px; overflow-y: auto; border: 1px solid #e9ecef; border-radius: 0.375rem; padding: 10px;">
                                <!-- Grades will be populated here -->
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="button" class="btn btn-primary" id="confirmGradeSelection">
                        <i class="ri-check-line"></i> Confirm Selection
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Employee Selection Modal for Adding New Rows -->
    <div class="modal fade" id="addEmployeeModal" tabindex="-1" aria-labelledby="addEmployeeModalLabel"
        aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="addEmployeeModalLabel">Add Employee</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="row mb-3">
                        <div class="col-md-12">
                            <label class="form-label">Request For <span class="text-danger">*</span></label>
                            <div class="d-flex gap-3">
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" name="add_request_for" id="addRequestForAct" value="Act" checked>
                                    <label class="form-check-label" for="addRequestForAct">Activity (Act)</label>
                                </div>
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" name="add_request_for" id="addRequestForExp" value="Exp">
                                    <label class="form-check-label" for="addRequestForExp">Expense (Exp)</label>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="row mb-3">
                        <div class="col-md-12">
                            <input type="text" class="form-control" id="addEmployeeSearch"
                                placeholder="Search employees..." aria-label="Search employees">
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-12">
                            <div class="add-employee-list"
                                style="max-height: 400px; overflow-y: auto; border: 1px solid #e9ecef; border-radius: 0.375rem; padding: 10px;">
                                <!-- Employees will be populated here -->
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="button" class="btn btn-primary" id="confirmAddEmployee">
                        <i class="ri-check-line"></i> Add Selected Employee
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Confirmation Modal -->
    <div class="modal fade" id="confirmSaveModal" tabindex="-1" aria-labelledby="confirmSaveModalLabel"
        aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="progress-container" style="display: none;">
                    <div class="progres" style="height: 5px;">
                        <div class="indeterminate" style="background-color: var(--vz-primary);"></div>
                    </div>
                </div>
                <div class="modal-header">
                    <h5 class="modal-title" id="confirmSaveModalLabel">Confirm Save</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    Are you sure you want to save the advance request master settings for this department?
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-primary btn-label waves-effect waves-light rounded-pill"
                        id="confirmSaveBtn">
                        <i class="ri-check-double-line label-icon align-middle rounded-pill fs-16 me-2">
                            <span class="loader" style="display: none;"></span>
                        </i>
                        Confirm
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Activity Limits Modal -->
    <div class="modal fade" id="activityLimitsModal" tabindex="-1" aria-labelledby="activityLimitsModalLabel"
        aria-hidden="true">
        <div class="modal-dialog modal-xl">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="activityLimitsModalLabel">Activity Limits - <span
                            id="activityLimitsDepartmentName"></span></h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="table-responsive">
                        <table class="table table-bordered activity-limits-table">
                            <thead class="table-light">
                                <tr>
                                    <th style="width:5%;">Sr No.</th>
                                    <th style="width:40%;">Activity Name</th>
                                    <th style="width:20%;">Min Amount</th>
                                    <th style="width:20%;">Max Amount</th>
                                    <th style="width:15%;">Action</th>
                                </tr>
                            </thead>
                            <tbody id="activityLimitsTableBody">
                                <!-- Activities will be populated here -->
                            </tbody>
                        </table>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                    <button type="button" class="btn btn-success" id="saveAllActivityLimits">
                        <i class="ri-save-3-line"></i> Save All Limits
                    </button>
                </div>
            </div>
        </div>
    </div>
    <!-- Management Selection Modal for Auto Advance -->
    <div class="modal fade" id="managementSelectionModal" tabindex="-1" aria-labelledby="managementSelectionModalLabel"
        aria-hidden="true">
        <div class="modal-dialog modal-xl">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="managementSelectionModalLabel">Select Management Team - <span id="managementDepartmentName"></span></h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="table-responsive">
                        <table class="table table-bordered table-hover">
                            <thead class="table-light">
                                <tr>
                                    <th style="width: 5%;">
                                        <input type="checkbox" class="form-check-input" id="selectAllManagers">
                                    </th>
                                    <th style="width: 10%;">Emp ID</th>
                                    <th style="width: 25%;">Name</th>
                                    <th style="width: 60%;">Verticals</th>
                                </tr>
                            </thead>
                            <tbody id="managementTableBody">
                                <!-- Management list will be populated here -->
                            </tbody>
                        </table>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="button" class="btn btn-primary" id="confirmManagementSelection">
                        <i class="ri-check-line"></i> Confirm Selection
                    </button>
                </div>
            </div>
        </div>
    </div>
@endsection

@push('styles')
    <link rel="stylesheet" href="assets/libs/@simonwep/pickr/themes/classic.min.css" />
    <link rel="stylesheet" href="assets/libs/@simonwep/pickr/themes/monolith.min.css" />
    <link rel="stylesheet" href="assets/libs/@simonwep/pickr/themes/nano.min.css" />
@endpush

@push('scripts')
    <script src="{{ asset('custom/js/pages/advance_request_master_employee_rows.js') }}"></script>
    <script>
    // ADV-FX-ELIG-01
    $(document).on('submit', '#fxEligibilityForm', function (e) {
        e.preventDefault();
        const $m = $('#fxEligibilityMsg').removeClass('text-success text-danger').text('Saving...');
        $.post('{{ route('admin.advance-request-master.fx.save') }}', $(this).serialize())
            .done(function (r) { $m.addClass(r.success ? 'text-success' : 'text-danger').text(r.message || (r.success ? 'Saved.' : 'Not saved.')); })
            .fail(function (x) { $m.addClass('text-danger').text((x.responseJSON && x.responseJSON.message) || 'Not saved.'); });
    });
    </script>
    <script src="{{ asset('custom/js/pages/advance_request_master_grade_handler.js') }}"></script>
    <script src="{{ asset('custom/js/pages/advance_request_master_management_handler.js') }}"></script>
@endpush
