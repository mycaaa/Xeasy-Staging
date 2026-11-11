@extends('layouts.app')

@section('content')
<div class="page-content">
    <div class="container-fluid">

        <div class="card">
            <div class="card-header d-flex align-items-center gap-2">
                <h4 class="card-title mb-0 flex-grow-1">
                    High Amount Claim Report
                </h4>

                <!-- Date Range -->
                <input type="text"
                       id="dateRange"
                       class="form-control"
                       style="width:250px;"
                       placeholder="Select date range" aria-label="Select date range"
                       value="{{ $fromDate }} to {{ $toDate }}">

                <button type="button" class="btn btn-outline-primary position-relative" data-bs-toggle="offcanvas" data-bs-target="#additionalFiltersCanvas">
                    <i class="ri-filter-3-line me-1"></i> More Filters
                    <span class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-danger d-none" id="organogramFilterCountBadge">
                        0
                    </span>
                </button>
            </div>

            @include('partials.organogram-filters-drawer')

            <div class="card-body">

                <form method="GET" action="{{ route('high.amount.claim.report') }}" id="reportFilterForm">
                    <input type="hidden" name="fromDate" id="formFromDate" value="{{ $fromDate }}">
                    <input type="hidden" name="toDate" id="formToDate" value="{{ $toDate }}">
                </form>

                <table id="claimTable"
                       class="table table-bordered table-hover dt-responsive nowrap"
                       style="width:100%">

                    <thead class="table-light">
                        <tr>
                            <th>Sn</th>
                            <th>Employee</th>
                            <th>Total Claims</th>
                            <th>Total Amount (₹)</th>
                            <th>Top Claim Type</th>
                        </tr>
                    </thead>

                    <tbody>
                        @forelse($data as $index => $item)
                            <tr>
                                <td>{{ $index + 1 }}</td>
                                <td>{{ $item->EmployeeName }} - {{ $item->EmpCode }}</td>
                                <td>{{ $item->TotalClaims }}</td>
                                <td>₹ {{ number_format($item->TotalAmount, 2) }}</td>
                                <td>{{ $item->TopClaimType ?? '-' }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="5" class="text-center">
                                    No data found
                                </td>
                            </tr>
                        @endforelse
                    </tbody>

                </table>

            </div>
        </div>

    </div>
</div>
@endsection


@push('scripts')
<script src="{{ asset('vendor/cdn/flatpickr@4.6.13/dist/flatpickr.min.js') }}"></script>

<script>
$(document).ready(function () {

    function submitWithOrganogram() {
        let form = $('#reportFilterForm');
        form.find('input[name*="_ids"]').remove();
        if (typeof window.getOrganogramFilterValues === 'function') {
            const organogramVals = window.getOrganogramFilterValues();
            Object.keys(organogramVals).forEach(function (k) {
                const val = organogramVals[k];
                if (Array.isArray(val)) {
                    val.forEach(item => form.append($('<input>', { type: 'hidden', name: k + '[]', value: item })));
                } else if (val !== null && val !== undefined && val !== '') {
                    form.append($('<input>', { type: 'hidden', name: k, value: val }));
                }
            });
        }
        form.submit();
    }

    // Date Range Picker
    flatpickr("#dateRange", {
        mode: "range",
        dateFormat: "Y-m-d",
        defaultDate: ["{{ $fromDate }}", "{{ $toDate }}"],
        onChange: function (selectedDates, dateStr, instance) {

            if (selectedDates.length === 2) {
                let dates = selectedDates.map(d =>
                    instance.formatDate(d, "Y-m-d")
                );

                $('#formFromDate').val(dates[0]);
                $('#formToDate').val(dates[1]);
                submitWithOrganogram();
            }
        }
    });

    $(document).on('organogramFiltersApplied', function() {
        submitWithOrganogram();
    });

    $(document).on('organogramFiltersReset', function() {
        let form = $('#reportFilterForm');
        form.find('input[name*="_ids"]').remove();
        form.submit();
    });

    // DataTable
    $('#claimTable').DataTable({
        responsive: true,
        pageLength: 10,
        deferRender: true
    });

});
</script>
@include('partials.organogram-filters-js')
@endpush
