@extends('layouts.app')

@section('content')
<div class="page-content">
    <div class="container-fluid">
        <div class="row">
            <div class="col-xl-12">
                <div class="card card-height-100">

                    <!-- Header -->
                    <div class="card-header align-items-center d-flex gap-2">
                        <h4 class="card-title mb-0 flex-grow-1">
                            Employee Claim Behavior
                        </h4>

                        <input type="text"
                               class="form-control"
                               data-provider="flatpickr"
                               data-date-format="Y-m-d"
                               data-range-date="true"
                               id="dateRange"
                               style="width: 250px;"
                               placeholder="Select date range" aria-label="Select date range"
                               value="{{ old('fromDate', $fromDate ?? date('Y-m-d')) }} to {{ old('toDate', $toDate ?? date('Y-m-d')) }}">

                        <button type="button" class="btn btn-outline-primary btn-sm position-relative" data-bs-toggle="offcanvas" data-bs-target="#additionalFiltersCanvas">
                            <i class="ri-filter-3-line"></i> More Filters
                            <span class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-danger d-none" id="organogramFilterCountBadge">0</span>
                        </button>
                    </div>

                    <div class="card-body pb-3 pt-0">

                        <!-- Hidden Form -->
                        <form method="GET" id="filterForm" class="row g-1 mb-1 mt-1" action="{{ url()->current() }}">
                            <input type="hidden" name="type" value="{{ $type ?? 'top' }}">
                            <input type="hidden" name="fromDate" value="{{ $fromDate }}">
                            <input type="hidden" name="toDate" value="{{ $toDate }}">
                            @foreach(['function_ids', 'vertical_ids', 'department_ids', 'sub_department_ids', 'bu_ids', 'zone_ids', 'region_ids', 'territory_ids', 'hq_ids', 'policy_ids'] as $filterKey)
                                @if(request()->filled($filterKey))
                                    @foreach((array)request($filterKey) as $fVal)
                                        <input type="hidden" name="{{ $filterKey }}[]" value="{{ $fVal }}">
                                    @endforeach
                                @endif
                            @endforeach
                        </form>

                        <!-- Tabs -->
                        <ul class="nav nav-tabs mt-3">

                            <li class="nav-item">
                                <a class="nav-link {{ ($type ?? 'top') == 'top' ? 'active fw-bold' : '' }}"
                                   href="{{ url()->current() }}?{{ http_build_query(array_merge(request()->except('type'), ['type' => 'top', 'fromDate' => $fromDate, 'toDate' => $toDate])) }}">
                                    ✅ Top Rating (Same Day)
                                </a>
                            </li>

                            <li class="nav-item">
                                <a class="nav-link {{ ($type ?? 'top') == 'late' ? 'active fw-bold' : '' }}"
                                   href="{{ url()->current() }}?{{ http_build_query(array_merge(request()->except('type'), ['type' => 'late', 'fromDate' => $fromDate, 'toDate' => $toDate])) }}">
                                    ❌ Late / Bulk Upload
                                </a>
                            </li>

                        </ul>

                        <!-- Table -->
                        <div class="mt-3">
                            <table id="claimTable"
                                   class="table nowrap dt-responsive align-middle table-hover table-bordered"
                                   style="width:100%">

                                <thead class="table-light">
                                    <tr>
                                        <th>Sn</th>
                                        <th style="text-align: left">Employee Name</th>
                                        <th>Grade</th>
                                        <th>Vertical</th>
                                        <th>Department</th>
                                        <th>Total Claims Uploaded</th>
                                    </tr>
                                </thead>

                                <tbody>
                                    @forelse ($data as $index => $item)
                                        <tr>
                                            <td>{{ $index + 1 }}</td>
                                            <td style="text-align: left">
                                                {{ $item->EmployeeName }} - {{ $item->EmpCode }}
                                            </td>
                                            <td>{{ $item->grade_name }}</td>
                                            <td>{{ $item->vertical_name }}</td>
                                            <td>{{ $item->department_name }}</td>
                                            <td>{{ $item->TotalClaimsUploaded }}</td>
                                        </tr>
                                    @empty
                                        <tr>
                                            <td colspan="6" class="text-center">
                                                No records found for selected date range.
                                            </td>
                                        </tr>
                                    @endforelse
                                </tbody>

                            </table>
                        </div>

                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

@include('partials.organogram-filters-drawer')
@endsection


@push('styles')
<link rel="stylesheet" href="assets/libs/@simonwep/pickr/themes/classic.min.css" />
<link rel="stylesheet" href="assets/libs/@simonwep/pickr/themes/monolith.min.css" />
<link rel="stylesheet" href="assets/libs/@simonwep/pickr/themes/nano.min.css" />
<link href="{{ asset('vendor/cdn/select2@4.1.0-rc.0/dist/css/select2.min.css') }}" rel="stylesheet" />
@endpush


@push('scripts')
<script src="{{ asset('vendor/cdn/select2@4.1.0-rc.0/dist/js/select2.min.js') }}"></script>
@include('partials.organogram-filters-js')

<script>
$(document).ready(function () {

    // ✅ Date Picker
    flatpickr("#dateRange", {
        dateFormat: "Y-m-d",
        mode: "range",
        onChange: function (selectedDates, dateStr, instance) {
            if (selectedDates.length === 2) {

                var dates = selectedDates.map(date =>
                    instance.formatDate(date, "Y-m-d")
                );

                $('#dateRange').val(dates.join(' to '));

                var form = $('#filterForm');
                form.find('input[name="fromDate"], input[name="toDate"]').remove();

                form.append($('<input>', {
                    type: 'hidden',
                    name: 'fromDate',
                    value: dates[0]
                }));

                form.append($('<input>', {
                    type: 'hidden',
                    name: 'toDate',
                    value: dates[1]
                }));

                form.submit();
            }
        }
    });

    // Handle Organogram Filters
    $(document).on('organogramFiltersApplied', function (e, filters) {
        var form = $('#filterForm');
        form.find('input[name^="function_ids"], input[name^="vertical_ids"], input[name^="department_ids"], input[name^="sub_department_ids"], input[name^="bu_ids"], input[name^="zone_ids"], input[name^="region_ids"], input[name^="territory_ids"], input[name^="hq_ids"], input[name^="policy_ids"]').remove();

        $.each(filters, function (key, values) {
            if (Array.isArray(values) && values.length > 0) {
                $.each(values, function (i, val) {
                    form.append($('<input>', {
                        type: 'hidden',
                        name: key + '[]',
                        value: val
                    }));
                });
            }
        });

        form.submit();
    });

    $(document).on('organogramFiltersReset', function () {
        var form = $('#filterForm');
        form.find('input[name^="function_ids"], input[name^="vertical_ids"], input[name^="department_ids"], input[name^="sub_department_ids"], input[name^="bu_ids"], input[name^="zone_ids"], input[name^="region_ids"], input[name^="territory_ids"], input[name^="hq_ids"], input[name^="policy_ids"]').remove();
        form.submit();
    });

    // ✅ DataTable (single table only)
    $('#claimTable').DataTable({
        responsive: true,
        ordering: true,
        searching: true,
        paging: true,
        pageLength: 10,
        deferRender: true
    });

});
</script>
@endpush
