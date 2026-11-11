@extends('layouts.app') @section('content')
    <div class="page-content">
        <div class="container-fluid">
            <div class="row">
                <div class="col-xl-12">
                    <div class="card card-height-100">
                        <div class="card-header align-items-center d-flex gap-2 flex-wrap">
                            <h4 class="card-title mb-0 flex-grow-1">Claim Amount & Count Exception Report</h4>
                            <div class="dropdown card-header-dropdown">
                                @php $selectedMonth = request('month'); @endphp
                                <input type="hidden" id="month" value="{{ $selectedMonth ?? '' }}" />
                                <a class="text-reset dropdown-btn" href="#" data-bs-toggle="dropdown" aria-haspopup="true"
                                    aria-expanded="false">
                                    <span class="fw-semibold fs-12">Month: </span>
                                    <span class="text-muted">
                                        {{ $selectedMonth ? date('F', mktime(0, 0, 0, $selectedMonth, 1)) : 'All' }}
                                        <i class="mdi mdi-chevron-down"></i>
                                    </span>
                                </a>
                                <div class="dropdown-menu dropdown-menu-end">
                                    <a class="dropdown-item" href="{{ url()->current() }}">All Months</a>
                                    @for ($i = 1; $i <= 12; $i++)
                                        <a class="dropdown-item d-flex justify-content-between align-items-center"
                                            href="{{ url()->current() }}?month={{ $i }}">
                                            <span>{{ date('F', mktime(0, 0, 0, $i, 1)) }}</span>
                                        </a>
                                    @endfor
                                </div>
                            </div>
                            <div class="ms-auto">
                                <button type="button" class="btn btn-sm btn-primary" id="exportButton"><i
                                        class="ri-download-2-fill"></i> Export</button>
                            </div>
                        </div>
                        {{-- Extra Filters --}}
                        <div class="card-body border-bottom pb-2 pt-2">
                            <div class="row g-2 align-items-end">
                                <div class="col-auto">
                                    <label class="form-label mb-1 fs-12">Department</label>
                                    <select id="filterDept" class="form-select form-select-sm" style="min-width:140px;">
                                        <option value="">All Departments</option>
                                        @foreach($records->pluck('department_name')->unique()->filter()->sort()->values() as $dept)
                                            <option value="{{ $dept }}">{{ $dept }}</option>
                                        @endforeach
                                    </select>
                                </div>
                                <div class="col-auto">
                                    <label class="form-label mb-1 fs-12">Diff Amt (Min)</label>
                                    <input type="number" id="filterDiffAmtMin" class="form-control form-control-sm" style="width:100px;" placeholder="0">
                                </div>
                                <div class="col-auto">
                                    <label class="form-label mb-1 fs-12">Diff Amt (Max)</label>
                                    <input type="number" id="filterDiffAmtMax" class="form-control form-control-sm" style="width:100px;" placeholder="∞">
                                </div>
                                <div class="col-auto">
                                    <label class="form-label mb-1 fs-12">Diff Cnt (Min)</label>
                                    <input type="number" id="filterDiffCntMin" class="form-control form-control-sm" style="width:90px;" placeholder="0">
                                </div>
                                <div class="col-auto">
                                    <label class="form-label mb-1 fs-12">Diff Cnt (Max)</label>
                                    <input type="number" id="filterDiffCntMax" class="form-control form-control-sm" style="width:90px;" placeholder="∞">
                                </div>
                                <div class="col-auto">
                                    <button type="button" class="btn btn-sm btn-secondary" id="clearFilters">Clear</button>
                                </div>
                            </div>
                        </div>
                        <div class="card-body pb-3 pt-0">
                            <form method="GET" class="row g-1 mb-1 mt-1" action="{{ url()->current() }}"></form>
                            <div class="table-responsive">
                                <table id="claimReportTable"
                                    class="table nowrap dt-responsive align-middle table-hover table-bordered"
                                    style="width: 100%;">
                                    <thead class="table-light">
                                        <tr>
                                            <th>Sn</th>
                                            <th style="text-align: left;">Employee</th>
                                            <th>Department</th>
                                            <th>Month</th>
                                            <th>Status</th>
                                            <th>Submit Dt</th>
                                            <th>Final Amt</th>
                                            <th>Final Cnt</th>
                                            <th>Detail Amt</th>
                                            <th>Detail Cnt</th>
                                            <th>Diff Amt</th>
                                            <th>Diff Cnt</th>
                                            <th>Action</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @forelse ($records as $index => $item)
                                            <tr>
                                                <td>{{ $index + 1 }}</td>

                                                <td style="text-align: left;">
                                                    {{ $item->employee_code ?? '-' }} - {{ $item->employee_name ?? '-' }}
                                                </td>

                                                <td>{{ $item->department_name ?? '-' }}</td>

                                                <td>
                                                    @if(is_numeric($item->month))
                                                    {{ date('F', mktime(0, 0, 0, $item->month, 1)) }} @else
                                                    {{ $item->month ?? '-' }} @endif
                                                </td>

                                                <td>{{ $item->status ?? '-' }}</td>

                                                <td>
                                                    @if(!empty($item->date_of_submit))
                                                    {{ date('d-m-Y', strtotime($item->date_of_submit)) }} @else - @endif
                                                </td>

                                                <td>{{ number_format($item->final_claim_amount ?? 0, 0) }}</td>
                                                <td>{{ $item->final_claim_count ?? 0 }}</td>
                                                <td>{{ number_format($item->detail_claim_amount ?? 0, 0) }}</td>
                                                <td>
                                                    <a href="javascript:void(0);" class="text-primary show-detail fw-semibold"
                                                        data-employee-id="{{ $item->EmployeeID }}">
                                                        {{ $item->detail_claim_count ?? 0 }}
                                                    </a>
                                                </td>
                                                <td>{{ number_format($item->difference_amount ?? 0, 0) }}</td>
                                                <td>{{ $item->difference_count ?? 0 }}</td>
                                                <td>
                                                    <button type="button" class="btn btn-sm btn-info update-detail-amt-as-final"
                                                        data-employee-id="{{ $item->EmployeeID }}"
                                                        data-month="{{ $item->month }}"
                                                        data-detail-amt="{{ $item->detail_claim_amount ?? 0 }}"
                                                        data-detail-count="{{ $item->detail_claim_count ?? 0 }}"
                                                        data-final-amt="{{ $item->final_claim_amount ?? 0 }}"
                                                        data-final-count="{{ $item->final_claim_count ?? 0 }}">
                                                        Update Amount
                                                    </button>
                                                </td>
                                            </tr>
                                        @empty
                                            <tr>
                                                <td colspan="12" class="text-center">
                                                    No records found for selected month.
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
    <!-- Claim Detail Modal -->
    <div class="modal fade" id="claimDetailModal" tabindex="-1" aria-labelledby="claimDetailLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg modal-dialog-scrollable">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="claimDetailLabel">Claim Details</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>

                <div class="modal-body">
                    <div class="table-responsive">
                        <table class="table table-bordered table-striped table-sm" id="claimDetailTable">
                            <thead class="table-light">
                                <tr>
                                    <th>#</th>
                                    <th>Claim ID</th>
                                    <th>Filled Amount</th>
                                    <th>Status</th>
                                    <th>Odometer</th>
                                    <th>Backdate</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr>
                                    <td colspan="6" class="text-center text-muted">Loading...</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>


@endsection @push('styles') @endpush @push('scripts')
    <script>
        $(document).ready(function () {
            $("#month").on("change", function () {
                var month = $(this).val();
                window.location.href = "{{ url('claim-exception-report') }}?month=" + month;
            });
            let table;
            if ($.fn.DataTable.isDataTable("#claimReportTable")) {
                table = $("#claimReportTable").DataTable();
                table.destroy();
                $("#claimReportTable").empty();
            }
            table = $("#claimReportTable").DataTable({
                responsive: true,
                ordering: false,
                searching: true,
                paging: true,
            });

            // Column indices: 2=Dept, 10=Diff Amt, 11=Diff Cnt
            $.fn.dataTable.ext.search.push(function (settings, data) {
                if (settings.nTable.id !== 'claimReportTable') return true;

                var dept     = $("#filterDept").val().trim().toLowerCase();
                var diffAmtMin = parseFloat($("#filterDiffAmtMin").val());
                var diffAmtMax = parseFloat($("#filterDiffAmtMax").val());
                var diffCntMin = parseFloat($("#filterDiffCntMin").val());
                var diffCntMax = parseFloat($("#filterDiffCntMax").val());

                var rowDept    = (data[2] || '').trim().toLowerCase();
                var rowDiffAmt = parseFloat((data[10] || '0').replace(/,/g, ''));
                var rowDiffCnt = parseFloat(data[11] || '0');

                if (dept && rowDept !== dept) return false;
                if (!isNaN(diffAmtMin) && rowDiffAmt < diffAmtMin) return false;
                if (!isNaN(diffAmtMax) && rowDiffAmt > diffAmtMax) return false;
                if (!isNaN(diffCntMin) && rowDiffCnt < diffCntMin) return false;
                if (!isNaN(diffCntMax) && rowDiffCnt > diffCntMax) return false;

                return true;
            });

            $("#filterDept, #filterDiffAmtMin, #filterDiffAmtMax, #filterDiffCntMin, #filterDiffCntMax").on("change keyup", function () {
                table.draw();
            });

            $("#clearFilters").on("click", function () {
                $("#filterDept").val('');
                $("#filterDiffAmtMin, #filterDiffAmtMax, #filterDiffCntMin, #filterDiffCntMax").val('');
                table.draw();
            });

            $("#exportButton").on("click", function () {
                var month = $("#month").val();
                var url = "{{ url('export/claim-exception-report') }}";
                if (month) url += "?month=" + month;
                window.location.href = url;
            });

            $("body").on("click", ".update-detail-amt-as-final", function () {
                const employeeId = $(this).data("employee-id");
                const month = $(this).data("month");
                $.post("/update-final-amount", {
                    employee_id: employeeId,
                    month: month,
                    detail_amount: $(this).data("detail-amt"),
                    final_amount: $(this).data("final-amt"),
                    detail_count: $(this).data("detail-count"),
                    final_count: $(this).data("final-count"),
                    _token: "{{ csrf_token() }}"
                }, function (response) {
                    if (response.success) {
                        location.reload();
                    } else {
                        alert(response.message || "Failed to update final amount.");
                    }
                }).fail(function () {
                    alert("An error occurred while updating final amount.");
                });
            });

            $(document).on("click", ".show-detail", function () {
                const employeeId = $(this).data("employee-id");
                const month = $("#month").val();
                const modal = $("#claimDetailModal");
                const tbody = $("#claimDetailTable tbody");

                tbody.html(`<tr><td colspan="6" class="text-center text-muted">Loading...</td></tr>`);
                modal.modal("show");

                $.get("/claim-exception-detail", {
                    employee_id: employeeId,
                    month: month
                }, function (response) {
                    const data = response.data || [];
                    if (data.length === 0) {
                        tbody.html(`<tr><td colspan="6" class="text-center text-danger">No claim details found.</td></tr>`);
                        return;
                    }
                    let rows = "";
                    data.forEach((r, i) => {
                        rows += `
                                        <tr>
                                            <td>${i + 1}</td>
                                            <td>${r.ExpId}</td>
                                            <td>${Number(r.FilledTAmt).toFixed(2)}</td>
                                            <td>${r.ClaimStatus}</td>
                                            <td>${r.App_Odometer ?? '-'}</td>
                                            <td>${r.Backdate_Odometer_Status ?? '-'}</td>
                                        </tr>`;
                    });
                    tbody.html(rows);
                });
            });
        });
    </script>
@endpush