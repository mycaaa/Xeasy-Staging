@extends('layouts.app')
@section('content')
    <div class="page-content">
        <div class="container-fluid">

            <div class="row">
                @php
                    // Define default card data to prevent missing key errors
                    $defaultCardData = [
                        'Total Expense' => 0,
                        'Draft' => 0,
                        'Deactivated' => 0,
                        'Submitted' => 0,
                        'Filled' => 0,
                        'Verified' => 0,
                        'Approved' => 0,
                        'Financed' => 0,
                    ];
                    $cardData = array_merge($defaultCardData, $cardData ?? []);

                    $cards = [
                        ['label' => 'Total Expense', 'icon' => 'ri-wallet-3-fill', 'color' => 'text-primary', 'value' => $cardData['Total Expense']],
                        ['label' => 'Draft', 'icon' => 'ri-time-line', 'color' => 'text-warning', 'value' => $cardData['Draft']],
                        ['label' => 'Deactivated', 'icon' => 'ri-archive-line', 'color' => 'text-secondary', 'value' => $cardData['Deactivated']],
                        ['label' => 'Submitted', 'icon' => 'ri-send-plane-fill', 'color' => 'text-dark', 'value' => $cardData['Submitted']],
                        ['label' => 'Filled', 'icon' => 'ri-file-list-3-line', 'color' => 'text-info', 'value' => $cardData['Filled']],
                        ['label' => 'Verified', 'icon' => 'ri-eye-fill', 'color' => 'text-success', 'value' => $cardData['Verified']],
                        ['label' => 'Approved', 'icon' => 'ri-check-double-fill', 'color' => 'text-success', 'value' => $cardData['Approved']],
                        ['label' => 'Financed', 'icon' => 'ri-hand-coin-fill', 'color' => 'text-primary', 'value' => $cardData['Financed']],
                    ];
                @endphp
                @foreach ($cards as $card)
                    <div class="col-lg-3 col-md-6">
                        <div class="card">
                            <div class="card-body">
                                <div class="d-flex align-items-center">
                                    <div class="avatar-sm flex-shrink-0">
                                        <span
                                            class="avatar-title bg-light {{ $card['color'] }} rounded-circle fs-3 material-shadow">
                                            <i class="{{ $card['icon'] }} align-middle"></i>
                                        </span>
                                    </div>
                                    <div class="flex-grow-1 ms-3">
                                        <p class="text-uppercase fw-semibold fs-12 text-muted mb-1">{{ $card['label'] }}</p>
                                        <h4 class="mb-0"><span class="counter-value"
                                                data-target="{{ $card['value'] }}">{{ $card['value'] }}</span></h4>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                @endforeach
            </div>
            <div class="row">
                <div class="col-xl-12">
                    <div class="card">
                        <div class="card-header border-0 align-items-center d-flex">
                            <h4 class="card-title mb-0 flex-grow-1">Expense Tracking Dashboard</h4>
                            <div>
                                <button type="button" class="btn btn-soft-secondary btn-sm material-shadow-none">
                                    Export Excel
                                </button>
                            </div>
                        </div>
                        <div class="card-header p-0 border-0 bg-light-subtle">
                            <div class="row g-0 text-center">
                                @php
                                    $months = [
                                        'jan' => 'January',
                                        'feb' => 'February',
                                        'mar' => 'March',
                                        'apr' => 'April',
                                        'may' => 'May',
                                        'jun' => 'June',
                                        'jul' => 'July',
                                        'aug' => 'August',
                                        'sep' => 'September',
                                        'oct' => 'October',
                                        'nov' => 'November',
                                        'dec' => 'December'
                                    ];
                                @endphp
                                <div class="col-6 col-sm-3">
                                    <div class="p-3 border border-dashed border-start-0">
                                        <label class="form-label mb-1 text-muted">Month</label>
                                        <select class="form-select select2" name="month" id="select-month">
                                            <option value="">Select Month</option>
                                            @foreach ($months as $key => $label)
                                                <option value="{{ $key }}">{{ $label }}</option>
                                            @endforeach
                                        </select>
                                    </div>
                                </div>
                                <div class="col-6 col-sm-3">
                                    <div class="p-3 border border-dashed border-start-0">
                                        <label class="form-label mb-1 text-muted">Claim Type</label>
                                        <select class="form-select select2" name="claim_type" id="select-claim-type">
                                            <option value="">Select Type</option>
                                            @foreach ($claimTypeTotals as $claimType)
                                                <option value="{{ strtolower($claimType->ClaimName) }}">
                                                    {{ $claimType->ClaimName }}</option>
                                            @endforeach
                                        </select>
                                    </div>
                                </div>
                                <div class="col-6 col-sm-3">
                                    <div class="p-3 border border-dashed border-start-0">
                                        <label class="form-label mb-1 text-muted">Department</label>
                                        <select class="form-select select2" name="department" id="select-department">
                                            <option value="">Select Department</option>
                                            @foreach ($departmentTotals as $dept)
                                                <option value="{{ strtolower($dept->department_name) }}">
                                                    {{ $dept->department_name }}</option>
                                            @endforeach
                                        </select>
                                    </div>
                                </div>
                                <div class="col-6 col-sm-3">
                                    <div class="p-3 border border-dashed border-start-0 border-end-0">
                                        <label class="form-label mb-1 text-muted">Claim Status</label>
                                        <select class="form-select select2" name="claim_status" id="select-claim-status">
                                            <option value="">Select Status</option>
                                            @foreach ($cardData as $label => $value)
                                                @if ($label != 'Total Expense')
                                                    <option value="{{ strtolower($label) }}">{{ $label }}</option>
                                                @endif
                                            @endforeach
                                        </select>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="card-body pb-2">
                            <div class="row">
                                <div class="col-xl-6 mb-3">
                                    <h6 class="text-muted text-center">CY vs PY Expenses by Department</h6>
                                    <canvas id="departmentExpensesChart" height="300"></canvas>
                                </div>
                                <div class="col-xl-6 mb-3">
                                    <h6 class="text-muted text-center">CY Expenses by Category</h6>
                                    <canvas id="categoryExpensesChart" height="300"></canvas>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection
@push('styles')
    <link href="{{ asset('vendor/cdn/select2@4.1.0-rc.0/dist/css/select2.min.css') }}" rel="stylesheet" />
@endpush
@push('scripts')
    <script src="{{ asset('vendor/cdn/jquery@3.6.0/dist/jquery.min.js') }}"></script>
    <script src="{{ asset('vendor/cdn/select2@4.1.0-rc.0/dist/js/select2.min.js') }}"></script>
    <script src="{{ asset('vendor/cdn/chart.js@4.6.1/dist/chart.umd.js') }}"></script>
    <script>
        $(document).ready(function () {
            // Initialize Select2
            $(".select2").select2({ width: 'resolve' });

            // Set CSRF token for AJAX
            $.ajaxSetup({
                headers: {
                    'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
                }
            });

            // Chart data with fallbacks
            const departmentData = {
                labels: [@foreach ($departmentTotals as $dept)"{{ $dept->department_name }}", @endforeach] || ['No Data'],
                datasets: [
                    {
                        label: "CY",
                        data: [@foreach ($departmentTotals as $dept){{ $dept->TotalFinancedTAmt_Y7 }}, @endforeach] || [0],
                        backgroundColor: "rgba(54, 162, 235, 0.7)",
                    },
                    {
                        label: "PY",
                        data: [@foreach ($departmentTotals as $dept){{ $dept->TotalFinancedTAmt_Y6 }}, @endforeach] || [0],
                        backgroundColor: "rgba(255, 206, 86, 0.7)",
                    },
                ],
            };
            const categoryData = {
                labels: [@foreach ($claimTypeTotals as $claimType)"{{ $claimType->ClaimName }}", @endforeach] || ['No Data'],
                datasets: [
                    {
                        label: "CY Expenses",
                        data: [@foreach ($claimTypeTotals as $claimType){{ $claimType->TotalFinancedAmount }}, @endforeach] || [0],
                        backgroundColor: [
                            "rgba(255, 99, 132, 0.7)",
                            "rgba(54, 162, 235, 0.7)",
                            "rgba(255, 206, 86, 0.7)",
                            "rgba(75, 192, 192, 0.7)",
                        ],
                    },
                ],
            };

            // Initialize charts
            const departmentCtx = document.getElementById("departmentExpensesChart").getContext("2d");
            const departmentChart = new Chart(departmentCtx, {
                type: "bar",
                data: departmentData,
                options: {
                    indexAxis: "y",
                    responsive: true,
                    scales: {
                        x: {
                            beginAtZero: true,
                        },
                    },
                    plugins: {
                        legend: {
                            position: "top",
                        },
                    },
                },
            });

            const categoryCtx = document.getElementById("categoryExpensesChart").getContext("2d");
            const categoryChart = new Chart(categoryCtx, {
                type: "bar",
                data: categoryData,
                options: {
                    indexAxis: "y",
                    responsive: true,
                    scales: {
                        x: {
                            beginAtZero: true,
                        },
                    },
                    plugins: {
                        legend: {
                            display: false,
                        },
                    },
                },
            });

            // AJAX for filter updates
            function updateCharts() {
                const $selects = $("#select-month, #select-claim-type, #select-department, #select-claim-status");
                $selects.prop('disabled', true);

                $.ajax({
                    url: "{{ route('admin.overview.filter') }}",
                    method: "POST",
                    data: {
                        month: $("#select-month").val(),
                        claim_type: $("#select-claim-type").val(),
                        department: $("#select-department").val(),
                        claim_status: $("#select-claim-status").val(),
                    },
                    success: function (response) {
                        // Update department chart
                        departmentChart.data.labels = response.departmentTotals.map(d => d.department_name) || ['No Data'];
                        departmentChart.data.datasets[0].data = response.departmentTotals.map(d => d.TotalFinancedTAmt_Y7) || [0];
                        departmentChart.data.datasets[1].data = response.departmentTotals.map(d => d.TotalFinancedTAmt_Y6) || [0];
                        departmentChart.update();

                        // Update category chart
                        categoryChart.data.labels = response.claimTypeTotals.map(c => c.ClaimName) || ['No Data'];
                        categoryChart.data.datasets[0].data = response.claimTypeTotals.map(c => c.TotalFinancedAmount) || [0];
                        categoryChart.data.datasets[0].backgroundColor = response.claimTypeTotals.map((_, i) => [
                            "rgba(255, 99, 132, 0.7)",
                            "rgba(54, 162, 235, 0.7)",
                            "rgba(255, 206, 86, 0.7)",
                            "rgba(75, 192, 192, 0.7)",
                        ][i % 4]);
                        categoryChart.update();

                        // Update card values
                        response.cardData.forEach(item => {
                            $(`.counter-value[data-target='${item.originalValue}']`).attr('data-target', item.value).text(item.value);
                        });
                    },
                    error: function (xhr) {
                        console.error("Error fetching filtered data:", xhr);
                    },
                    complete: function () {
                        $selects.prop('disabled', false);
                    }
                });
            }

            // Bind filter change events
            $("#select-month, #select-claim-type, #select-department, #select-claim-status").on("change", updateCharts);
        });
    </script>
@endpush