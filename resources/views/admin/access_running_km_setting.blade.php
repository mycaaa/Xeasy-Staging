@extends('layouts.app')
@section('content')
    <div class="page-content">
        <div class="container-fluid">
            <div class="row">
                <div class="col-lg-12">
                    <div class="card">
                        <div class="card-header align-items-center d-flex">
                            <h4 class="card-title mb-0 flex-grow-1">Excess Running KM Setting</h4>
                        </div>
                        <div class="card-body">
                            <div class="table-responsive">
                                <table style="margin-top: 15px" id="accessRunningKmTable"
                                    class="table nowrap dt-responsive align-middle table-hover table-bordered"
                                    style="width:100%">
                                    <thead class="table-light">
                                        <tr>
                                            <th style="width:5%;align-content: center;">Sr No.</th>
                                            <th style="width:15%;align-content: center; text-align:left;">Department</th>
                                            <th style="width:10%;align-content: center;">Active</th>
                                            <th style="width:15%;align-content: center;">Monthly KM Limit</th>
                                            <th style="width:15%;align-content: center;">Auto Approve Below Limit</th>
                                            <th style="width:20%;align-content: center;">Vertical</th>
                                            <th style="width:10%;align-content: center;">Effective From</th>
                                            <th style="width:10%;align-content: center;">Effective To</th>
                                            <th style="width:5%;align-content: center;">Action</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @foreach ($departments as $department)
                                            @php
                                                $rule = $rules[$department->id] ?? null;
                                            @endphp
                                            <tr data-department-id="{{ $department->id }}">
                                                <td>{{ $loop->iteration }}</td>
                                                <td style="text-align: left;">{{ $department->department_name }}</td>
                                                <td>
                                                    <div class="form-check form-switch text-center">
                                                        <input class="form-check-input is-active-switch" type="checkbox"
                                                            data-department-id="{{ $department->id }}"
                                                            {{ $rule && $rule->is_active ? 'checked' : '' }}>
                                                    </div>
                                                </td>
                                                <td>
                                                    <input type="number" class="form-control monthly-km-input"
                                                        data-department-id="{{ $department->id }}"
                                                        placeholder="Enter KM limit" min="1" max="999999"
                                                        value="{{ $rule && $rule->monthly_km_limit ? $rule->monthly_km_limit : '' }}">
                                                </td>
                                                <td>
                                                    <select class="form-select auto-approve-select"
                                                        data-department-id="{{ $department->id }}">
                                                        <option value="Y"
                                                            {{ $rule && $rule->auto_approve_if_below_limit === 'Y' ? 'selected' : '' }}>
                                                            Yes</option>
                                                        <option value="N"
                                                            {{ $rule && $rule->auto_approve_if_below_limit === 'N' ? 'selected' : '' }}>
                                                            No</option>
                                                    </select>
                                                </td>
                                                <td style="white-space: normal;width: 20%;">
                                                    @if (isset($mappedVerticalDetails[$department->id]) && $mappedVerticalDetails[$department->id]->isNotEmpty())
                                                        @php
                                                            $savedVerticals =
                                                                $rule && $rule->vertical_ids
                                                                    ? explode(',', $rule->vertical_ids)
                                                                    : [];
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
                                                    <input type="date" class="form-control effective-from-input"
                                                        data-department-id="{{ $department->id }}" placeholder="DD-MM-YYYY"
                                                        value="{{ $rule && $rule->effective_from ? $rule->effective_from->format('Y-m-d') : '' }}">
                                                </td>
                                                <td>
                                                    <input type="date" class="form-control effective-to-input"
                                                        data-department-id="{{ $department->id }}" placeholder="DD-MM-YYYY"
                                                        value="{{ $rule && $rule->effective_to ? $rule->effective_to->format('Y-m-d') : '' }}">
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
                            <div class="mt-1">
                                <small class="text-muted">
                                    <i class="ri-information-line"></i> Required fields when department is active.
                                    "Effective To" is optional and can be left empty for open-ended rules.
                                </small>
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
    <script src="{{ asset('custom/js/pages/access_running_km_setting.js') }}"></script>
@endpush
