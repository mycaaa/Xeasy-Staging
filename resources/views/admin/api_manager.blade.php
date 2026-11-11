@extends('layouts.app')

@section('content')
    <div class="page-content">
        <div class="container-fluid">
            <div class="row">
                <div class="col-lg-12">
                    <div class="card">
                        <div class="card-header align-items-center d-flex">
                            <h4 class="card-title mb-0 flex-grow-1">API Manager List</h4>
                            <div class="flex-shrink-0">
                                @can('Create API')
                                    <button type="button"
                                        class="btn btn-primary btn-label waves-effect waves-light rounded-pill"
                                        data-bs-toggle="modal" data-bs-target="#apiModal" id="addApiBtn">
                                        <i class="ri-add-circle-fill label-icon align-middle rounded-pill fs-16 me-2"></i> Add
                                        New
                                    </button>
                                @endcan
                            </div>
                        </div>
                        <div class="card-body">
                            <table id="apiManagerTable"
                                class="table nowrap dt-responsive align-middle table-hover table-bordered"
                                style="width: 100%;">
                                <thead class="table-light">
                                    <tr>
                                        <th>S No.</th>
                                        <th>Claim ID</th>
                                        <th>Name</th>
                                        <th>Endpoint</th>
                                        <th>Status</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @can('API List')
                                        @foreach ($apis as $key => $api)
                                            <tr>
                                                <td>{{ $key + 1 }}</td>
                                                <td>{{ $api->claim_id }}</td>
                                                <td>{{ $api->name }}</td>
                                                <td>{{ $api->endpoint }}</td>
                                                <td>
                                                    @if ($api->status == 1)
                                                        <span class="badge bg-success-subtle text-success badge-border">Active</span>
                                                    @else
                                                        <span class="badge bg-danger-subtle text-danger badge-border">Inactive</span>
                                                    @endif
                                                </td>
                                                <td>
                                                    @can('Edit API')
                                                        <button type="button" data-bs-toggle="modal" data-bs-target="#apiModal"
                                                            class="btn btn-primary btn-sm edit-api" data-id="{{ $api->id }}">
                                                            <i class="ri-edit-2-fill"></i>
                                                        </button>
                                                    @endcan
                                                    @can('Feilds Mapping')
                                                        <a href="{{ route('fields.mapping.page', ['claim_id' => $api->claim_id]) }}"
                                                            target="_blank" class="btn btn-info btn-sm feilds-mapping-api"
                                                            data-id="{{ $api->claim_id }}">
                                                            <i class="ri-list-settings-fill"></i>
                                                        </a>
                                                    @endcan
                                                </td>
                                            </tr>
                                        @endforeach
                                    @else
                                        <tr>
                                            <td colspan="6" class="text-center">
                                                <span class="text-danger">You do not have permission to view the API
                                                    list.</span>
                                            </td>
                                        </tr>
                                    @endcan
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <x-modal.api_manager />
    <x-modal.api_feild_mapping />
@endsection
@push('scripts')
    <script src="{{ asset('custom/js/pages/api_manager.js') }}"></script>
@endpush