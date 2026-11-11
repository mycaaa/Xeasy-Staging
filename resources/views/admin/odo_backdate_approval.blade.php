@extends('layouts.app')
@section('content')
    <div class="page-content">
        <div class="container-fluid">
            <div class="row">
                <div class="col-lg-12">
                    <div class="card">
                        <div class="card-header align-items-center d-flex">
                            <h4 class="card-title mb-0 flex-grow-1">Odometer Backdate Approval Settings</h4>
                        </div>
                        <div class="card-body">
                            <div class="table-responsive">
                                <table style="margin-top: 15px" id="odoSettingsTable"
                                    class="table nowrap dt-responsive align-middle table-hover table-bordered"
                                    style="width:100%">
                                    <thead class="table-light">
                                        <tr>
                                            <th style="width:5%;">Sr No.</th>
                                            <th style="width:15%; text-align:left;">Department</th>
                                            <th style="width:10%;">Enable Check</th>
                                            <th style="width:15%;">Approval Required</th>
                                            <th style="width:10%;">Delayed Day</th>
                                            <th style="width:25%;">Vertical</th>
                                            <th style="width:15%;">Effective Date</th>
                                            <th style="width:5%;">Action</th>
                                        </tr>
                                    </thead>

                                    <tbody>
                                        @foreach ($departments as $department)
                                            @php
                                                $setting = $settings[$department->id] ?? null;
                                            @endphp
                                            <tr data-department-id="{{ $department->id }}">
                                                <td>{{ $loop->iteration }}</td>
                                                <td style="text-align: left;">{{ $department->department_name }}</td>
                                                <td>
                                                    <div class="form-check form-switch text-center">
                                                        <input class="form-check-input is-active-switch" type="checkbox"
                                                            data-department-id="{{ $department->id }}"
                                                            {{ $setting && $setting->is_active ? 'checked' : '' }}>
                                                    </div>
                                                </td>
                                                <td>
                                                    <select class="form-select approval-select"
                                                        data-department-id="{{ $department->id }}">
                                                        <option value="">Select Approval Type</option>
                                                        @if (strtolower($department->department_name) === 'sales')
                                                            <option value="bu_level"
                                                                {{ $setting && $setting->approval_type === 'bu_level' ? 'selected' : '' }}>
                                                                BU Level</option>
                                                        @endif
                                                        <option value="hod_level"
                                                            {{ $setting && $setting->approval_type === 'hod_level' ? 'selected' : '' }}>
                                                            HOD Level</option>
                                                    </select>
                                                </td>
                                                <td>
                                                    <select class="form-select delayed-day-select"
                                                        data-department-id="{{ $department->id }}">
                                                        <option value="">Select Days</option>
                                                        @for ($i = 1; $i <= 10; $i++)
                                                            <option value="{{ $i }}"
                                                                {{ $setting && $setting->delayed_day == $i ? 'selected' : '' }}>
                                                                {{ $i }} Day{{ $i > 1 ? 's' : '' }}
                                                            </option>
                                                        @endfor
                                                    </select>
                                                </td>
                                                <td style="white-space: normal;width: 25%;">
                                                    @if (isset($mappedVerticalDetails[$department->id]) && $mappedVerticalDetails[$department->id]->isNotEmpty())
                                                        @php
                                                            $savedVerticals = $setting && $setting->verticals ? explode(',', $setting->verticals) : [];
                                                            $uniqueVerticals = $mappedVerticalDetails[
                                                                $department->id
                                                            ]->unique('id');
                                                        @endphp
                                                        @foreach ($uniqueVerticals as $v)
                                                            <div class="form-check form-check-inline"
                                                                style="margin-right: 5px;">
                                                                <input class="form-check-input" type="checkbox"
                                                                    id="vertical_{{ $department->id }}_{{ $v->id }}"
                                                                    name="verticals[{{ $department->id }}][]"
                                                                    value="{{ $v->id }}"
                                                                    {{ in_array($v->id, $savedVerticals) ? 'checked' : '' }}>
                                                                <label class="form-check-label"
                                                                    for="vertical_{{ $department->id }}_{{ $v->id }}">
                                                                    {{ $v->vertical_code }}
                                                                </label>
                                                            </div>
                                                        @endforeach
                                                    @else
                                                        <span>-</span>
                                                    @endif
                                                </td>
                                                <td>
                                                    <input type="date" class="form-control effective-date-input"
                                                        data-department-id="{{ $department->id }}" placeholder="DD-MM-YYYY"
                                                        value="{{ $setting && $setting->effective_date ? $setting->effective_date->format('Y-m-d') : '' }}">
                                                </td>
                                                <td>
                                                    <button class="btn btn-primary btn-sm save-btn"
                                                        data-department-id="{{ $department->id }}"><i
                                                            class="ri-save-3-line"></i></button>
                                                </td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <div class="modal fade" id="confirmSaveModal" tabindex="-1" aria-labelledby="confirmSaveModalLabel" aria-hidden="true">
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
                    Are you sure you want to save the changes for this department?
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
@endsection
@push('styles')
    <link rel="stylesheet" href="assets/libs/@simonwep/pickr/themes/classic.min.css" />
    <link rel="stylesheet" href="assets/libs/@simonwep/pickr/themes/monolith.min.css" />
    <link rel="stylesheet" href="assets/libs/@simonwep/pickr/themes/nano.min.css" />
@endpush
@push('scripts')
    <script src="{{ asset('custom/js/pages/odo_backdate_approval.js') }}"></script>
@endpush
