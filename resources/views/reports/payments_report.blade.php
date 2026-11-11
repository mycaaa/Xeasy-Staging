@extends('layouts.app')

@section('content')
    <div class="page-content">
        <div class="container-fluid">
            <div class="row">
                <div class="col-xl-12">
                    <div class="card">
                        <div class="card-header align-items-center d-flex gap-2 flex-wrap">
                            <h4 class="card-title mb-0 flex-grow-1">
                                <i class="ri-bar-chart-grouped-line"></i> Payment Stages Summary
                            </h4>
                            <div class="ms-auto d-flex gap-2">
                                <button type="button" class="btn btn-sm btn-outline-primary position-relative" data-bs-toggle="offcanvas" data-bs-target="#additionalFiltersCanvas">
                                    <i class="ri-filter-3-line me-1"></i> More Filters
                                    <span class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-danger d-none" id="organogramFilterCountBadge">
                                        0
                                    </span>
                                </button>
                                <button type="button" class="btn btn-sm btn-primary" id="exportButton">
                                    <i class="ri-download-2-fill"></i> Export
                                </button>
                                <button type="button" class="btn btn-sm btn-secondary" id="refreshButton">
                                    <i class="ri-refresh-line"></i> Refresh
                                </button>
                            </div>
                        </div>

                        @include('partials.organogram-filters-drawer')

                        {{-- Filters --}}
                        <div class="card-body border-bottom">
                            <div class="row g-2">
                                <div class="col-md-3 d-none">
                                    <label class="form-label fs-12">Year</label>
                                    <select id="filterYear" class="form-select">
                                        <option value="">Select Year</option>
                                        @for($i = date('Y'); $i >= date('Y') - 5; $i--)
                                            <option value="{{ $i }}" {{ $i == date('Y') ? 'selected' : '' }}>{{ $i }}</option>
                                        @endfor
                                    </select>
                                </div>
                                <div class="col-md-3">
                                    <label class="form-label fs-12">Month</label>
                                    <div class="input-group">
                                        <select id="filterMonth" class="form-select select2-multiple" multiple>
                                            <option value="1">January</option>
                                            <option value="2">February</option>
                                            <option value="3">March</option>
                                            <option value="4">April</option>
                                            <option value="5">May</option>
                                            <option value="6">June</option>
                                            <option value="7">July</option>
                                            <option value="8">August</option>
                                            <option value="9">September</option>
                                            <option value="10">October</option>
                                            <option value="11">November</option>
                                            <option value="12">December</option>
                                        </select>
                                        <button class="btn btn-outline-secondary select-all-btn" type="button"
                                            data-target="#filterMonth" title="Select All">
                                            <i class="ri-checkbox-line"></i>
                                        </button>
                                    </div>
                                </div>
                                <div class="col-md-3">
                                    <label class="form-label fs-12">Function</label>
                                    <div class="input-group">
                                        <select id="filterFunction" class="form-select select2-multiple" multiple>
                                            @foreach($functions as $func)
                                                <option value="{{ $func->id }}">{{ $func->function_name }}</option>
                                            @endforeach
                                        </select>
                                        <button class="btn btn-outline-secondary select-all-btn" type="button"
                                            data-target="#filterFunction" title="Select All">
                                            <i class="ri-checkbox-line"></i>
                                        </button>
                                    </div>
                                </div>
                                <div class="col-md-3">
                                    <label class="form-label fs-12">Vertical</label>
                                    <div class="input-group">
                                        <select id="filterVertical" class="form-select select2-multiple" multiple>
                                            @foreach($verticals as $vert)
                                                <option value="{{ $vert->id }}">{{ $vert->vertical_name }}</option>
                                            @endforeach
                                        </select>
                                        <button class="btn btn-outline-secondary select-all-btn" type="button"
                                            data-target="#filterVertical" title="Select All">
                                            <i class="ri-checkbox-line"></i>
                                        </button>
                                    </div>
                                </div>
                                <div class="col-md-3">
                                    <label class="form-label fs-12">Department</label>
                                    <div class="input-group">
                                        <select id="filterDepartment" class="form-select select2-multiple" multiple>
                                            @foreach($departments as $dept)
                                                <option value="{{ $dept->id }}">{{ $dept->department_name }}</option>
                                            @endforeach
                                        </select>
                                        <button class="btn btn-outline-secondary select-all-btn" type="button"
                                            data-target="#filterDepartment" title="Select All">
                                            <i class="ri-checkbox-line"></i>
                                        </button>
                                    </div>
                                </div>
                                <div class="col-md-3">
                                    <label class="form-label fs-12">Sub Department</label>
                                    <div class="input-group">
                                        <select id="filterSubDepartment" class="form-select select2-multiple" multiple>
                                            @foreach($sub_departments as $sub_dept)
                                                <option value="{{ $sub_dept->id }}">{{ $sub_dept->sub_department_name }}
                                                </option>
                                            @endforeach
                                        </select>
                                        <button class="btn btn-outline-secondary select-all-btn" type="button"
                                            data-target="#filterSubDepartment" title="Select All">
                                            <i class="ri-checkbox-line"></i>
                                        </button>
                                    </div>
                                </div>
                                {{-- <div class="col-md-3">
                                    <label class="form-label fs-12">Employee</label>
                                    <div class="input-group">
                                        <select id="filterEmployee" class="form-select select2-multiple" multiple>
                                            @foreach($employees as $emp)
                                            <option value="{{ $emp->EmployeeID }}">
                                                {{ $emp->EmpCode }} - {{ $emp->Fname }} {{ $emp->Sname }} {{ $emp->Lname }}
                                            </option>
                                            @endforeach
                                        </select>
                                        <button class="btn btn-outline-secondary select-all-btn" type="button"
                                            data-target="#filterEmployee" title="Select All">
                                            <i class="ri-checkbox-line"></i>
                                        </button>
                                    </div>
                                </div> --}}
                            </div>
                            <div class="row g-2 mt-3">
                                <div class="col-md-3">
                                    <div class="form-check mt-2">
                                        <input type="checkbox" class="form-check-input" id="withEmployeeCheckbox">
                                        <label class="form-check-label" for="withEmployeeCheckbox">
                                            <i class="ri-user-settings-line"></i> Show Employee-wise Details
                                        </label>
                                    </div>
                                </div>
                                <div class="col-md-3">
                                    <button type="button" class="btn btn-sm btn-secondary w-100" id="clearFilters">
                                        <i class="ri-close-line"></i> Clear All Filters
                                    </button>
                                </div>
                                <div class="col-md-3">
                                    <button type="button" class="btn btn-sm btn-info w-100" id="applyFilters">
                                        <i class="ri-filter-line"></i> Apply Filters
                                    </button>
                                </div>
                                <div class="col-md-3">
                                    <button type="button" class="btn btn-sm btn-success w-100" id="selectAllFilters">
                                        <i class="ri-checkbox-line"></i> Select All Filters
                                    </button>
                                </div>
                            </div>
                        </div>

                        {{-- Loading Indicator --}}
                        <div id="loadingIndicator" class="text-center py-3" style="display: none;">
                            <div class="spinner-border text-primary" role="status">
                                <span class="visually-hidden">Loading...</span>
                            </div>
                            <p class="mt-2">Loading data...</p>
                        </div>

                        {{-- Error Message --}}
                        <div id="errorMessage" class="alert alert-danger m-3" style="display: none;">
                            <i class="ri-error-warning-line"></i> <span id="errorText"></span>
                        </div>

                        {{-- HORIZONTALLY SCROLLABLE TABLE --}}
                        <div class="card-body pt-3">
                            <div class="horizontal-scroll-wrapper"
                                style="overflow-x: auto; overflow-y: visible; border-radius: 0.75rem; border: 1px solid #e2e8f0; background: #fff;">
                                <div style="min-width: 100%; width: max-content; min-width: 100%;">
                                    <table id="stagesTable" class="table table-bordered table-hover mb-0"
                                        style="width: auto; white-space: nowrap; min-width: 100%;">
                                        <thead id="tableHeader" style="background-color: #f8fafc;">
                                            {{-- Header will be injected dynamically with proper columns --}}
                                        </thead>
                                        <tbody id="tableBody">
                                            <tr>
                                                <td colspan="10" class="text-center">Select year and click Apply Filters
                                                </td>
                                            </tr>
                                        </tbody>
                                        <tfoot id="tableFooter" style="background-color: #f1f5f9; font-weight: 600;">
                                            {{-- Footer will be injected --}}
                                        </tfoot>
                                    </table>
                                </div>
                            </div>
                            <div class="mt-2 text-muted small">
                                <i class="ri-arrow-left-right-line"></i> <span class="fw-semibold">Hint:</span> Scroll
                                horizontally to view all payment stages columns.
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection

@push('styles')
    <style>
        .input-group {
            display: flex;
            align-items: stretch;
        }

        .input-group .select2-container {
            flex: 1 1 auto;
            width: auto !important;
        }

        .select-all-btn {
            white-space: nowrap;
            border-top-right-radius: 0.375rem !important;
            border-bottom-right-radius: 0.375rem !important;
            border-left: none;
        }

        /* Make selected items inline and wrap */
        .select2-container--bootstrap-5 .select2-selection--multiple .select2-selection__rendered {
            display: flex;
            flex-wrap: wrap;
            gap: 5px;
        }

        /* Prevent full-width stretching */
        .select2-container--bootstrap-5 .select2-selection__choice {
            width: auto !important;
            max-width: 100%;
            white-space: normal;
            /* allow wrapping text */
        }

        /* Optional: better look */
        .select2-selection__choice {
            padding: 2px 8px;
            font-size: 14px;
        }

        .select2-dropdown {
            z-index: 1050 !important;
        }

        .select2-container--open {
            z-index: 1050 !important;
        }

        .form-check {
            padding-top: 8px;
        }

        .btn-sm i {
            margin-right: 4px;
        }

        table {
            font-size: 13px;
            border-collapse: separate;
            border-spacing: 0;
        }

        table td,
        table th {
            vertical-align: middle;
            padding: 12px 16px !important;
        }

        #errorMessage {
            position: fixed;
            top: 20px;
            right: 20px;
            z-index: 9999;
            min-width: 300px;
        }

        .adv-paid-column {
            background-color: #fff3cd !important;
            font-weight: bold;
        }

        .total-row {
            background-color: #f8f9fa;
            font-weight: bold;
        }

        .text-end {
            text-align: right;
        }

        .text-center {
            text-align: center;
        }

        .text-start {
            text-align: left;
        }

        .horizontal-scroll-wrapper {
            scrollbar-width: thin;
            scrollbar-color: #cbd5e1 #f1f5f9;
        }

        .horizontal-scroll-wrapper::-webkit-scrollbar {
            height: 8px;
        }

        .horizontal-scroll-wrapper::-webkit-scrollbar-track {
            background: #f1f5f9;
            border-radius: 10px;
        }

        .horizontal-scroll-wrapper::-webkit-scrollbar-thumb {
            background: #cbd5e1;
            border-radius: 10px;
        }

        .horizontal-scroll-wrapper::-webkit-scrollbar-thumb:hover {
            background: #94a3b8;
        }

        /* Fixed header background on horizontal scroll */
        .table thead th {
            position: sticky;
            top: 0;
            background-color: #f8fafc;
            z-index: 10;
            box-shadow: inset 0 -1px 0 #e2e8f0;
        }
    </style>
@endpush

@push('scripts')
    <script>
        $(document).ready(function () {
            let companyId = {{ session('company_id') ?? 0 }};
            let hideFinancedVerified = (companyId == 11);
            console.log('Payment Stages Report Ready - Horizontal Scroll Mode');

            let withEmployee = false;

            // Initialize Select2
            function initSelect2() {
                $('.select2-multiple').each(function () {
                    if ($(this).hasClass('select2-hidden-accessible')) {
                        $(this).select2('destroy');
                    }
                    $(this).select2({
                        theme: 'bootstrap-5',
                        width: '100%',
                        placeholder: 'Select options',
                        closeOnSelect: false,
                        allowClear: true
                    });
                });
            }

            function setupSelectAllButtons() {
                $('.select-all-btn').off('click').on('click', function () {
                    let targetSelect = $(this).data('target');
                    let $select = $(targetSelect);
                    let allValues = $select.find('option').map(function () { return this.value; }).get();
                    $select.val(allValues).trigger('change');
                    let btn = $(this);
                    let originalHtml = btn.html();
                    btn.html('<i class="ri-checkbox-circle-line"></i>');
                    setTimeout(() => btn.html(originalHtml), 800);
                });
            }

            function clearAllSelections() {
                $('.select2-multiple').each(function () {
                    $(this).val(null).trigger('change');
                });
            }

            function showLoading(show) {
                if (show) $('#loadingIndicator').show();
                else $('#loadingIndicator').hide();
            }

            function showError(message) {
                $('#errorText').text(message);
                $('#errorMessage').fadeIn();
                setTimeout(() => $('#errorMessage').fadeOut(), 5000);
            }

            function formatNumber(num) {
                if (isNaN(num) || num === null || num === undefined) return '0.00';
                return parseFloat(num).toLocaleString('en-IN', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
            }

            function toNumber(val) {
                if (val === null || val === undefined || val === '') return 0;
                return parseFloat(val.toString().replace(/,/g, '')) || 0;
            }

            // DYNAMIC HEADERS + HORIZONTAL SCROLL FRIENDLY (no fixed width issues)
            function updateHeaders() {
                let headers = ['#'];
                headers.push('Month');
                if (withEmployee) {
                    headers.push('Employee Code');
                    headers.push('Employee Name');
                }
                // All financial columns - ensures horizontal scroll works naturally
                headers.push('Filled Amount');

                if (!hideFinancedVerified) {
                    headers.push('Verified Amount');
                }

                headers.push('Approved Amount');

                if (!hideFinancedVerified) {
                    headers.push('Financed Amount');
                }

                headers.push('Advance');
                headers.push('Paid');
                headers.push('Adv + Paid');

                let headerRow = '';
                $.each(headers, function (index, header) {
                    let alignClass = 'text-end';
                    if (header === '#') alignClass = 'text-center';
                    if (header === 'Month') alignClass = 'text-start';
                    if (withEmployee && (header === 'Employee Code' || header === 'Employee Name')) alignClass = 'text-start';
                    if (header === 'Adv + Paid') alignClass = 'text-end adv-paid-column';
                    headerRow += `<th class="${alignClass}" style="white-space: nowrap;">${header}</th>`;
                });
                $('#tableHeader').html(headerRow);

                // Footer: dynamic colspan based on employee mode
                let colspan = withEmployee ? 4 : 2;
                let footerHtml = `
        <tr class="total-row">
            <td colspan="${colspan}" class="text-end fw-bold">Grand Total:</td>
            <td class="text-end fw-bold" id="totalFilled">0.00</td>
        `;

                if (!hideFinancedVerified) {
                    footerHtml += `<td class="text-end fw-bold" id="totalVerified">0.00</td>`;
                }

                footerHtml += `<td class="text-end fw-bold" id="totalApproved">0.00</td>`;

                if (!hideFinancedVerified) {
                    footerHtml += `<td class="text-end fw-bold" id="totalFinanced">0.00</td>`;
                }

                footerHtml += `
            <td class="text-end fw-bold" id="totalAdvance">0.00</td>
            <td class="text-end fw-bold" id="totalPaid">0.00</td>
            <td class="text-end fw-bold" id="totalAdvPlusPaid">0.00</td>
        </tr>
        `;

                $('#tableFooter').html(footerHtml);
                // $('#tableFooter').html(`
                //                 <tr class="total-row">
                //                     <td colspan="${colspan}" class="text-end fw-bold">Grand Total:</td>
                //                     <td class="text-end fw-bold" id="totalFilled">0.00</td>
                //                     <td class="text-end fw-bold" id="totalVerified">0.00</td>
                //                     <td class="text-end fw-bold" id="totalApproved">0.00</td>
                //                     <td class="text-end fw-bold" id="totalFinanced">0.00</td>
                //                     <td class="text-end fw-bold" id="totalAdvance">0.00</td>
                //                     <td class="text-end fw-bold" id="totalPaid">0.00</td>
                //                     <td class="text-end fw-bold" id="totalAdvPlusPaid">0.00</td>
                //                 </tr>
                //             `);
            }

            function renderTable(data) {
                if (!data || data.length === 0) {
                    $('#tableBody').html('<tr><td colspan="10" class="text-center">No data available</td></tr>');
                    $('#totalFilled').text('0.00');
                 if (!hideFinancedVerified) {
    $('#totalVerified').text(formatNumber(totals.verified));

}
                    $('#totalApproved').text('0.00');
                  if (!hideFinancedVerified) {

    $('#totalFinanced').text(formatNumber(totals.financed));
}
                    $('#totalAdvance').text('0.00');
                    $('#totalPaid').text('0.00');
                    $('#totalAdvPlusPaid').text('0.00');
                    return;
                }

                let tbody = '';
                let totals = { filled: 0, verified: 0, approved: 0, financed: 0, advance: 0, paid: 0, advPlusPaid: 0 };

                $.each(data, function (index, row) {
                    let filled = toNumber(row.filled_amount);
                    let verified = toNumber(row.verified_amount);
                    let approved = toNumber(row.approved_amount);
                    let financed = toNumber(row.financed_amount);
                    let advance = toNumber(row.advance_amount);
                    let paid = toNumber(row.paid_amount);
                    let advPlusPaid = advance + paid;

                    totals.filled += filled;
                    totals.verified += verified;
                    totals.approved += approved;
                    totals.financed += financed;
                    totals.advance += advance;
                    totals.paid += paid;
                    totals.advPlusPaid += advPlusPaid;

                    let rowHtml = '<tr>';
                    rowHtml += `<td class="text-center">${index + 1}</td>`;
                    rowHtml += `<td class="text-start">${row.Month || row.month || 'N/A'}</td>`;
                    if (withEmployee) {
                        rowHtml += `<td class="text-start">${row.EmpCode || row.emp_code || 'N/A'}</td>`;
                        rowHtml += `<td class="text-start">${row.EmployeeName || row.employee_name || 'N/A'}</td>`;
                    }
                    rowHtml += `<td class="text-end">${formatNumber(filled)}</td>`;

                    if (!hideFinancedVerified) {
                        rowHtml += `<td class="text-end">${formatNumber(verified)}</td>`;
                    }

                    rowHtml += `<td class="text-end">${formatNumber(approved)}</td>`;

                    if (!hideFinancedVerified) {
                        rowHtml += `<td class="text-end">${formatNumber(financed)}</td>`;
                    }

                    rowHtml += `<td class="text-end">${formatNumber(advance)}</td>`;
                    rowHtml += `<td class="text-end">${formatNumber(paid)}</td>`;
                    rowHtml += `<td class="text-end adv-paid-column">${formatNumber(advPlusPaid)}</td>`;
                    rowHtml += '</tr>';
                    tbody += rowHtml;
                });

                $('#tableBody').html(tbody);
                $('#totalFilled').text(formatNumber(totals.filled));
                $('#totalVerified').text(formatNumber(totals.verified));
                $('#totalApproved').text(formatNumber(totals.approved));
                $('#totalFinanced').text(formatNumber(totals.financed));
                $('#totalAdvance').text(formatNumber(totals.advance));
                $('#totalPaid').text(formatNumber(totals.paid));
                $('#totalAdvPlusPaid').text(formatNumber(totals.advPlusPaid));
            }

            // Load data from backend route
            function loadData() {
                let year = $('#filterYear').val();
                if (!year) {
                    $('#tableBody').html('<tr><td colspan="10" class="text-center">⚠️ Please select a year first</td></tr>');
                    return;
                }

                showLoading(true);
                let requestData = {
                    year: year,
                    employee_ids: $('#filterEmployee').val(),
                    months: $('#filterMonth').val(),
                    function_ids: $('#filterFunction').val(),
                    vertical_ids: $('#filterVertical').val(),
                    department_ids: $('#filterDepartment').val(),
                    sub_department_ids: $('#filterSubDepartment').val(),
                    with_employee: withEmployee
                };

                if (typeof window.getOrganogramFilterValues === 'function') {
                    $.extend(requestData, window.getOrganogramFilterValues());
                }

                $.ajax({
                    url: "{{ route('admin.reports.payment.stages.data') }}",
                    type: "POST",
                    headers: { 'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content') },
                    data: requestData,
                    success: function (response) {
                        showLoading(false);
                        let data = [];
                        if (response.data && Array.isArray(response.data)) data = response.data;
                        else if (Array.isArray(response)) data = response;
                        else if (response.data && typeof response.data === 'object') data = Object.values(response.data);

                        if (data.length === 0) {
                            $('#tableBody').html('<tr><td colspan="10" class="text-center">📭 No data found for selected filters</td></tr>');
                        } else {
                            renderTable(data);
                        }
                    },
                    error: function (xhr) {
                        showLoading(false);
                        let errorMsg = 'Failed to load data.';
                        try {
                            let resp = JSON.parse(xhr.responseText);
                            errorMsg = resp.error || resp.message || errorMsg;
                        } catch (e) { }
                        showError(errorMsg);
                        $('#tableBody').html(`<tr><td colspan="10" class="text-center text-danger">${errorMsg}</td></tr>`);
                    }
                });
            }

            // ---- Event Bindings ----
            $('#withEmployeeCheckbox').on('change', function () {
                withEmployee = $(this).is(':checked');
                updateHeaders();     // Rebuild columns dynamically (horizontal scroll adapts)
                if ($('#filterYear').val()) loadData();
            });

            $('#applyFilters').on('click', function () {
                if ($('#filterYear').val()) {
                    loadData();
                    Swal.fire({ icon: 'success', title: 'Filters Applied', text: 'Data updated', timer: 1500, showConfirmButton: false });
                } else {
                    Swal.fire({ icon: 'warning', title: 'Year Required', text: 'Please select a year first', confirmButtonText: 'OK' });
                }
            });

            $('#filterYear').on('change', function () {
                if ($(this).val()) loadData();
            });

            $('#refreshButton').on('click', function () {
                if ($('#filterYear').val()) {
                    loadData();
                    Swal.fire({ icon: 'success', title: 'Refreshed', text: 'Latest data loaded', timer: 1500, showConfirmButton: false });
                } else {
                    Swal.fire({ icon: 'info', title: 'Select Year', text: 'Please choose a year to refresh', confirmButtonText: 'OK' });
                }
            });

            $('#clearFilters').on('click', function () {
                clearAllSelections();
                if (typeof window.resetOrganogramFilters === 'function') {
                    window.resetOrganogramFilters();
                }
                $('#filterYear').val(new Date().getFullYear());
                $('#withEmployeeCheckbox').prop('checked', false);
                withEmployee = false;
                updateHeaders();
                loadData();
                Swal.fire({ icon: 'info', title: 'Cleared', text: 'All filters reset', timer: 1500, showConfirmButton: false });
            });

            $('#selectAllFilters').on('click', function () {
                $('.select2-multiple').each(function () {
                    let allValues = $(this).find('option').map(function () { return this.value; }).get();
                    $(this).val(allValues).trigger('change');
                });
                Swal.fire({ icon: 'success', title: 'Selected All', text: 'All filter options selected', timer: 1500, showConfirmButton: false });
            });

            $('#exportButton').on('click', function () {
                let year = $('#filterYear').val();
                if (!year) {
                    Swal.fire({ icon: 'warning', title: 'Export Error', text: 'Please select a year to export', confirmButtonText: 'OK' });
                    return;
                }
                let params = new URLSearchParams();
                params.append('year', year);
                let empIds = $('#filterEmployee').val();
                if (empIds && empIds.length) params.append('employee_ids', empIds.join(','));
                let months = $('#filterMonth').val();
                if (months && months.length) params.append('months', months.join(','));
                let funcIds = $('#filterFunction').val();
                if (funcIds && funcIds.length) params.append('function_ids', funcIds.join(','));
                let vertIds = $('#filterVertical').val();
                if (vertIds && vertIds.length) params.append('vertical_ids', vertIds.join(','));
                let deptIds = $('#filterDepartment').val();
                if (deptIds && deptIds.length) params.append('department_ids', deptIds.join(','));
                let subDeptIds = $('#filterSubDepartment').val();
                if (subDeptIds && subDeptIds.length) params.append('sub_department_ids', subDeptIds.join(','));
                params.append('with_employee', $('#withEmployeeCheckbox').is(':checked'));

                if (typeof window.getOrganogramFilterValues === 'function') {
                    const organogramVals = window.getOrganogramFilterValues();
                    Object.keys(organogramVals).forEach(function (k) {
                        const val = organogramVals[k];
                        if (Array.isArray(val)) {
                            val.forEach(item => params.append(k + '[]', item));
                        } else if (val !== null && val !== undefined && val !== '') {
                            params.append(k, val);
                        }
                    });
                }

                let exportUrl = "{{ route('admin.reports.payment.stages.export') }}?" + params.toString();
                window.open(exportUrl, '_blank');
                Swal.fire({ icon: 'info', title: 'Export Started', text: 'Preparing file...', timer: 2000, showConfirmButton: false });
            });

            $(document).on('organogramFiltersApplied organogramFiltersReset', function() {
                loadData();
            });

            // initializers
            initSelect2();
            setupSelectAllButtons();
            updateHeaders();

            // Set default year if present and load initial data
            if ($('#filterYear').val()) {
                loadData();
            }
        });
    </script>
    @include('partials.organogram-filters-js')
@endpush
