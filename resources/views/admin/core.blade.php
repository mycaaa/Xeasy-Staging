@extends('layouts.app')
@section('content')
    <div class="page-content">
        <div class="container-fluid">
            <div class="row">
                <div class="col-lg-12">
                    <div class="card">
                        <div class="card-header align-items-center d-flex">
                            <h4 class="card-title mb-0 flex-grow-1">Core API's</h4>
                            <div class="flex-shrink-0">
                                <button type="button" class="btn btn-info btn-label waves-effect waves-light rounded-pill"
                                    id="import-actions">
                                    <i class="ri-download-2-fill label-icon align-middle rounded-pill fs-16 me-2"></i>
                                    Import
                                </button>
                                <button type="button"
                                    class="btn btn-primary btn-label waves-effect waves-light rounded-pill" id="syncAPI">
                                    <i class="ri-loop-left-fill label-icon align-middle rounded-pill fs-16 me-2"></i>
                                    Sync APIs
                                </button>
                            </div>
                        </div>
                        <div class="card-body">
                            <table class="table nowrap dt-responsive align-middle table-hover table-bordered"
                                id="coreAPITable">
                                <thead class="table-light text-muted">
                                    <tr>
                                        <th>#</th>
                                        <th>S.No</th>
                                        <th style="text-align:left"">API Name</th>
                                               <th style=" text-align:left"">API End Point</th>
                                        <th style="text-align:left"">Parameter</th>
                                               <th style=" text-align:left"">Description</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach ($api_list as $api)
                                        <tr>
                                            <td class="text-center">
                                                <input type="checkbox" class="form-check-input" name="apis"
                                                    id="apis_{{ $loop->iteration }}" value="{{ $api->api_end_point }}">
                                            </td>
                                            <td class="text-center">{{ $loop->iteration }}</td>
                                            <td style="text-align:left"">{{ $api->api_name }}</td>
                                                                           <td style=" text-align:left"">
                                                {{ $api->api_end_point }}
                                            </td>
                                            <td style="text-align:left"">{{ $api->parameters }}</td>
                                                                           <td style=" text-align:left"">
                                                {{ $api->description }}</td>
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
@endsection
@push('scripts')
    <script>
        $(document).ready(function () {
            $("#coreAPITable").DataTable({
                ordering: false,
                searching: true,
                paging: true,
                info: true,
                lengthChange: true,
                pageLength: 10,
                lengthMenu: [10, 25, 50, 100],
            });
        })
        $(document).on('click', '#syncAPI', function () {
            $.ajax({
                url: "{{ route('core_api_sync') }}",
                method: 'GET',
                processData: false,
                dataType: 'json',
                contentType: false,
                success: function (data) {
                    if (data.status == 200) {
                        $("#elmLoader").addClass('d-none');

                        window.location.reload();
                    } else {
                        $("#elmLoader").addClass('d-none');
                        showAlert(
                            "danger",
                            "ri-error-warning-line", "An error occurred while saving."
                        );

                    }
                }
            });

        });
        $('#coreAPITable').on('change', 'input[name="apis"]', function () {
            var anyChecked = $('#coreAPITable input[name="apis"]:checked').length > 0;
            $('#import-actions').toggle(anyChecked);
        });
        $('#import-actions').hide();
        $(document).on('click', '#import-actions', function () {
            var api_end_points = [];

            $("input[name='apis']").each(function () {
                if ($(this).prop("checked") === true) {
                    var value = $(this).val();
                    api_end_points.push(value);
                }
            });
            if (api_end_points.length > 0) {
                if (confirm('Are you sure to import selected api data?')) {
                    $.ajax({
                        url: "{{ route('importAPISData') }}",
                        type: 'POST',
                        data: {
                            api_end_points: api_end_points,
                            _token: "{{ csrf_token() }}"
                        },

                        success: function (data) {
                            if (data.status === 400) {
                                window.location.reload();
                            } else {
                                showAlert(
                                    "danger",
                                    "ri-error-warning-line", "An error occurred while saving."
                                );

                            }

                        }
                    });
                }

            } else {
                showAlert(
                    "danger",
                    "ri-error-warning-line", "An error occurred while saving."
                );
            }
        });
    </script>
@endpush