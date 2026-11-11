@extends('layouts.app')
@section('content')
    <div class="page-content">
        <div class="container-fluid">
            <div class="card">
                <x-redirect-button />
                <div class="card-body">
                    <div class="table-responsive">
                        <table id="expenseHeadTable" class="table table-striped table-bordered text-center">
                            <thead class="table-light">
                                <tr>
                                    <th class="sticky-col first-col">Sr No.</th>
                                    <th class="sticky-col second-col">Category</th>
                                    <th class="sticky-col third-col">Activity Name</th>
                                    @foreach ($expenseHeads as $head)
                                        <th class="text-center">
                                            <span data-bs-toggle="tooltip" data-bs-placement="top"
                                                title="{{ $head->expense_head_name }}">
                                                {{ $head->short_code ?? $head->expense_head_name }}
                                            </span>
                                        </th>
                                    @endforeach
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($activities as $key => $activity)
                                    <tr>
                                        <td class="sticky-col first-col">{{ $key + 1 }}</td>
                                        <td class="sticky-col second-col">{{ $activity->category_name }}</td>
                                        <td class="sticky-col third-col">
                                            {{ $activity->activity_name }}
                                            <input type="hidden" name="activity_names_id"
                                                value="{{ $activity->activity_id }}">
                                        </td>
                                        @foreach ($expenseHeads as $head)
                                            @php
                                                $mapKey = $activity->activity_id . '-' . $head->id;
                                                $checked = $mappings[$mapKey] ?? false;
                                            @endphp
                                            <td style="text-align: center;">
                                                <input type="checkbox" class="form-check-input expense-checkbox"
                                                    data-activity-id="{{ $activity->activity_id }}"
                                                    data-expense-head-id="{{ $head->id }}"
                                                    {{ $checked ? 'checked' : '' }}>
                                            </td>
                                        @endforeach
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>

                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection
@push('scripts')
    <script>
        $(document).ready(function() {
            var table = $('#expenseHeadTable').DataTable({
                scrollX: true,
                scrollCollapse: true,
                ordering: false,
                paging: true,
                fixedColumns: {
                    leftColumns: 3
                }
            });


            $('[data-bs-toggle="tooltip"]').tooltip();


            $('#expenseHeadTable').on('draw.dt', function() {
                $('[data-bs-toggle="tooltip"]').tooltip();
            });


            $('#expenseHeadTable tbody').on('mouseenter', 'td', function() {
                var colIdx = table.cell(this).index().column;

                $(table.cells().nodes()).removeClass('highlight-column hovered-cell');
                $(table.column(colIdx).nodes()).addClass('highlight-column');
                $(this).addClass('hovered-cell');


                $('.DTFC_LeftBodyWrapper table tbody tr').each(function(rowIdx, row) {
                    $(row).find('td').eq(colIdx).addClass('highlight-column');
                });
            });

            $('#expenseHeadTable tbody').on('mouseleave', 'td', function() {
                $(table.cells().nodes()).removeClass('highlight-column hovered-cell');
                $('.DTFC_LeftBodyWrapper table tbody tr td').removeClass('highlight-column');
            });

            $('#expenseHeadTable').on('change', '.expense-checkbox', function() {
                var $checkbox = $(this);
                var activityId = $checkbox.data('activity-id');
                var expenseHeadId = $checkbox.data('expense-head-id');
                var checked = $checkbox.is(':checked') ? 1 : 0;

                $.ajax({
                    url: '{{ route('expense-head-mappings.toggle') }}',
                    type: 'POST',
                    data: {
                        activity_id: activityId,
                        expense_head_id: expenseHeadId,
                        checked: checked,
                        _token: '{{ csrf_token() }}'
                    },
                    dataType: 'json',
                    beforeSend: function() {
                        $checkbox.prop('disabled', true);
                    },
                    success: function(response) {
                        if (response.success) {
                            showAlert(
                                "success",
                                "ri-checkbox-circle-line",
                                response.message || "Mapping updated successfully!"
                            );

                        } else {
                            showAlert(
                                "danger",
                                "ri-error-warning-line",
                                response.message || "Failed to update mapping."
                            );
                            alert(response.message || 'Failed to update mapping.');
                            $checkbox.prop('checked', !checked);
                        }
                    },
                    error: function(xhr) {
                        var errorMsg = xhr.responseJSON?.message || 'Failed to update mapping.';
                        showAlert(
                            "danger",
                            "ri-error-warning-line",
                            errorMsg || "Failed to update mapping."
                        );
                        $checkbox.prop('checked', !checked);
                    },
                    complete: function() {
                        $checkbox.prop('disabled', false);
                    }
                });
            });
        });
    </script>
@endpush
