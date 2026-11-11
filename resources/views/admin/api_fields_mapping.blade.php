@extends('layouts.app')
@section('content')
    <div class="page-content">
        <div class="container-fluid">
            <div class="row">
                <div class="col-lg-12">
                    <div class="card">
                        <div class="card-header align-items-center d-flex">
                            <h4 class="card-title mb-0 flex-grow-1">Field Mapping
                                {{ $claim_id }}
                            </h4>
                            <div class="flex-shrink-0">
                                <a href="{{ route('api-manager.index') }}"
                                    class="btn btn-primary btn-label waves-effect waves-light rounded-pill">
                                    <i class="ri-add-circle-fill label-icon align-middle rounded-pill fs-16 me-2"></i>
                                    Back
                                </a>
                            </div>
                        </div>
                        <div class="card-body">
                            <form id="mapping-form">
                                @csrf
                                <div class="table-responsive">
                                    <table class="table table-bordered">
                                        <thead>
                                            <tr>
                                                <th scope="col" rowspan="2" class="text-center"
                                                    style="width: 150px; align-content: center;">Temp Column</th>
                                                <th scope="col" rowspan="2" class="text-center"
                                                    style="width: 120px; align-content: center;">Input Type</th>
                                                <th scope="col" colspan="3" class="text-center">Select Lookup (if Input Type
                                                    is 'Select')</th>
                                                <th scope="col" colspan="2" class="text-center">Target Punch Table</th>
                                                <th scope="col" rowspan="2" class="text-center"
                                                    style="width: 200px; align-content: center;">Condition</th>
                                            </tr>
                                            <tr>
                                                <th scope="col" class="text-center">Table</th>
                                                <th scope="col" class="text-center">Search By</th>
                                                <th scope="col" class="text-center">Return Value</th>
                                                <th scope="col" class="text-center">Table</th>
                                                <th scope="col" class="text-center">Column</th>
                                            </tr>
                                        </thead>
                                        <tbody id="mapping-rows">
                                            @foreach ($columns as $index => $column)
                                                <tr data-temp-column="{{ $column }}">
                                                    <td>
                                                        {{ $column }}
                                                        <input type="hidden" name="temp_column[]" value="{{ $column }}">
                                                    </td>
                                                    <td>
                                                        <select class="form-control input-type" name="input_type[]">
                                                            <option value="Input">Input</option>
                                                            <option value="Select">Select</option>
                                                        </select>
                                                    </td>
                                                    <td>
                                                        <input type="text" class="form-control select-table"
                                                            name="select_table[]" disabled>
                                                    </td>
                                                    <td>
                                                        <input type="text" class="form-control search-column"
                                                            name="search_column[]" disabled>
                                                    </td>
                                                    <td>
                                                        <input type="text" class="form-control return-column"
                                                            name="return_column[]" disabled>
                                                    </td>
                                                    <td>
                                                        <input type="text" class="form-control punch-table"
                                                            name="punch_table[]">
                                                    </td>
                                                    <td>
                                                        <input type="text" class="form-control punch-column"
                                                            name="punch_column[]">
                                                    </td>
                                                    <td>
                                                        <input type="text" class="form-control" name="condition[]" value="" />
                                                    </td>
                                                </tr>
                                            @endforeach
                                        </tbody>
                                    </table>
                                </div>
                                <button type="submit" class="btn btn-primary mt-3">Save</button>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection
@push('styles')
    <link rel="stylesheet" href="{{ asset('vendor/cdn/jquery-ui@1.13.3/dist/themes/base/jquery-ui.css') }}">
@endpush
@push('scripts')
    <script src="{{ asset('vendor/cdn/jquery-ui@1.13.3/dist/jquery-ui.min.js') }}"></script>
    <script>
        $(document).ready(function () {
            // Initialize Select2 for input-type (keep as select)
            $("#mapping-rows .input-type").select2();

            // Function to save form data via AJAX
            function saveMapping() {
                const formData = $("#mapping-form").serialize();
                $.ajax({
                    url: "{{ route('fields.mapping.store-fields-mapping', $claim_id) }}",
                    method: "POST",
                    data: formData,
                    headers: {
                        "X-CSRF-TOKEN": $('meta[name="csrf-token"]').attr("content"),
                    },
                    success: function (response) {
                        showAlert(
                            "success",
                            "ri-checkbox-circle-line",
                            response.message || "Field mappings saved successfully!"
                        );
                    },
                    error: function (xhr) {
                        showAlert(
                            "danger",
                            "ri-error-warning-line",
                            xhr.responseJSON.message || "An error occurred while saving mappings."
                        );
                    }
                });
            }

            // Function to initialize autocomplete for a field
            function initAutocomplete(element, sourceUrl, disabled = false, defaultValue = '') {
                element.autocomplete({
                    source: function (request, response) {
                        if (!sourceUrl) {
                            response([]);
                            return;
                        }
                        const url = sourceUrl.replace('__table__', element.closest('tr').find('.select-table').val() || '');
                        $.ajax({
                            url: url,
                            method: 'GET',
                            data: { term: request.term },
                            success: function (data) {
                                const items = sourceUrl.includes('get.tables') ? data.tables : data.columns;
                                response(items.map(item => ({ label: item, value: item })));
                            }
                        });
                    },
                    minLength: 0,
                    select: function (event, ui) {
                        $(this).val(ui.item.value);
                        saveMapping();
                        updateDependentFields($(this));
                        return false;
                    },
                    change: function (event, ui) {
                        if (!ui.item && $(this).val() !== '') {
                            $(this).val('');
                        }
                        saveMapping();
                        updateDependentFields($(this));
                    },
                    open: function () {
                        $(this).autocomplete('widget');
                    }
                }).prop('disabled', disabled).val(defaultValue);

                // Allow clicking to show all options
                element.on('focus', function () {
                    if (!$(this).prop('disabled')) {
                        $(this).autocomplete('search', '');
                    }
                });
            }

            // Function to update dependent fields (e.g., columns after table selection)
            function updateDependentFields(element) {
                const row = element.closest('tr');
                if (element.hasClass('select-table')) {
                    const table = element.val();
                    const searchColumn = row.find('.search-column');
                    const returnColumn = row.find('.return-column');
                    if (table) {
                        initAutocomplete(searchColumn, "{{ route('get.columns', ['table' => '__table__']) }}", false);
                        initAutocomplete(returnColumn, "{{ route('get.columns', ['table' => '__table__']) }}", false);
                    } else {
                        searchColumn.val('').prop('disabled', true);
                        returnColumn.val('').prop('disabled', true);
                    }
                } else if (element.hasClass('punch-table')) {
                    const table = element.val();
                    const punchColumn = row.find('.punch-column');
                    if (table) {
                        initAutocomplete(punchColumn, "{{ route('get.columns', ['table' => '__table__']) }}", false);
                    } else {
                        punchColumn.val('').prop('disabled', false);
                    }
                }
            }

            // Fetch tables and initialize autocompletes
            $.ajax({
                url: "{{ route('get.tables') }}",
                method: "GET",
                headers: {
                    "X-CSRF-TOKEN": $('meta[name="csrf-token"]').attr("content"),
                },
                success: function (response) {
                    const tables = response.tables;
                    const punchTables = tables.filter(table => table.match(/^y\d+/));

                    // Initialize autocompletes for select-table and punch-table
                    $("#mapping-rows tr").each(function () {
                        const row = $(this);
                        initAutocomplete(row.find('.select-table'), "{{ route('get.tables') }}", true);
                        initAutocomplete(row.find('.punch-table'), "{{ route('get.tables') }}", false);
                        initAutocomplete(row.find('.search-column'), "{{ route('get.columns', ['table' => '__table__']) }}", true);
                        initAutocomplete(row.find('.return-column'), "{{ route('get.columns', ['table' => '__table__']) }}", true);
                        initAutocomplete(row.find('.punch-column'), "{{ route('get.columns', ['table' => '__table__']) }}", false);
                    });

                    // Fetch existing mappings and prefill form
                    $.ajax({
                        url: "{{ route('fields.mapping.get', $claim_id) }}",
                        method: "GET",
                        success: function (response) {
                            const mappings = response.mappings;
                            $("#mapping-rows tr").each(function () {
                                const row = $(this);
                                const tempColumn = row.data('temp-column');
                                const mapping = mappings[tempColumn];
                                if (mapping) {
                                    const inputType = mapping.input_type || 'Input';
                                    row.find('.input-type').val(inputType).trigger('change');
                                    row.find('.condition').val(mapping.condition || '');

                                    if (inputType === 'Select' && mapping.select_table) {
                                        row.find('.select-table').val(mapping.select_table).prop('disabled', false);
                                        row.find('.search-column').val(mapping.search_column || '').prop('disabled', false);
                                        row.find('.return-column').val(mapping.return_column || '').prop('disabled', false);
                                    }

                                    if (mapping.punch_table) {
                                        row.find('.punch-table').val(mapping.punch_table);
                                        row.find('.punch-column').val(mapping.punch_column || '').prop('disabled', false);
                                    }
                                }
                            });
                        },
                    });
                },
            });

            // Handle input-type change
            $(document).on('change', '.input-type', function () {
                const row = $(this).closest('tr');
                const selectTable = row.find('.select-table');
                const searchColumn = row.find('.search-column');
                const returnColumn = row.find('.return-column');
                const inputType = $(this).val();

                if (inputType === 'Input') {
                    selectTable.val('').prop('disabled', true);
                    searchColumn.val('').prop('disabled', true);
                    returnColumn.val('').prop('disabled', true);
                } else if (inputType === 'Select') {
                    selectTable.prop('disabled', false);
                    searchColumn.prop('disabled', true);
                    returnColumn.prop('disabled', true);
                }
                saveMapping();
            });

            // Prevent form submission reload
            $('#mapping-form').on('submit', function (e) {
                e.preventDefault();
                saveMapping();
            });
        });
    </script>
@endpush