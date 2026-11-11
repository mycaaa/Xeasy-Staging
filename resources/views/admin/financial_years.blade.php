@extends('layouts.app')
@section('content')
    <div class="page-content">
        <div class="container-fluid">
            <div class="row">
                <div class="col-lg-12">
                    <div class="card">
                        <div class="card-header align-items-center d-flex">
                            <h4 class="card-title mb-0 flex-grow-1">Financial Years List
                            </h4>
                            <div class="flex-shrink-0">
                                <button type="button"
                                    class="btn btn-primary btn-label waves-effect waves-light rounded-pill"
                                    data-bs-toggle="modal" data-bs-target="#FinancialYearModal" id="addFinancialYearBtn">
                                    <i class="ri-add-circle-fill label-icon align-middle rounded-pill fs-16 me-2"></i> Add
                                    New
                                </button>
                            </div>
                        </div>
                        <div class="card-body">
                            <table id="FinancialYearsMasterTable"
                                class="table nowrap dt-responsive align-middle table-hover table-bordered"
                                style="width: 100%;">
                                <thead class="table-light">
                                    <tr>
                                        <th>S No.</th>
                                        <th>Financial Year</th>
                                        <th>Status</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach ($financialYears as $key => $financialYear)
                                        <tr>
                                            <td>{{ $key + 1 }}</td>
                                            <td>{{ $financialYear->Year }}</td>
                                            <td>
                                                @if ($financialYear->Status == 'Active')
                                                    <span class="badge bg-success-subtle text-success badge-border">Active</span>
                                                @else
                                                    <span class="badge bg-danger-subtle text-danger badge-border">Inactive</span>
                                                @endif
                                            </td>
                                            <td>
                                                <button type="button" data-bs-toggle="modal"
                                                    data-bs-target="#Financial YearModal" id="addFinancial YearBtn"
                                                    class="btn btn-primary btn-sm edit-Financial Year"
                                                    data-id="{{ $financialYear->YearId }}"><i
                                                        class="ri-edit-2-fill"></i></button>

                                            </td>
                                        </tr>
                                    @endforeach

                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <x-modal.financial />
@endsection
@push('scripts')
    <script src="{{ asset('custom/js/pages/financial.js') }}"></script>
@endpush