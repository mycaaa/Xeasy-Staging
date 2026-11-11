@extends('layouts.app')
@section('content')
    <div class="page-content">
        <div class="container-fluid">

            {{-- Page Header --}}
            <div class="row mb-3 pb-1">
                <div class="col-12">
                    <div class="d-flex align-items-lg-center flex-lg-row flex-column">
                        <div class="flex-grow-1">
                            <h4 class="fs-16 mb-1">Team Members</h4>
                            <p class="text-muted mb-0">View and manage your team</p>
                        </div>
                        <div class="mt-3 mt-lg-0">
                            <a href="{{ url('/reporting-manager/dashboard') }}" class="btn btn-soft-primary waves-effect waves-light">
                                <i class="ri-arrow-left-line me-1"></i>Back to Dashboard
                            </a>
                        </div>
                    </div>
                </div>
            </div>

            {{-- Team Members Grid --}}
            <div class="row">
                @forelse($teamMembers ?? [] as $member)
                    <div class="col-lg-4 col-md-6">
                        <div class="card card-animate">
                            <div class="card-body">
                                <div class="d-flex align-items-center mb-3">
                                    <div class="flex-shrink-0">
                                        <div class="avatar-md">
                                            <span class="avatar-title bg-light rounded-circle text-primary fs-3">
                                                <i class="ri-user-line"></i>
                                            </span>
                                        </div>
                                    </div>
                                    <div class="flex-grow-1 ms-3">
                                        <h5 class="mb-0">
                                            {{ $member->FirstName ?? '' }} {{ $member->LastName ?? '' }}
                                        </h5>
                                        <p class="text-muted mb-0 fs-13">
                                            {{ $member->Designation ?? 'Employee' }}
                                        </p>
                                    </div>
                                </div>

                                <div class="row g-2 mb-3">
                                    <div class="col-6">
                                        <div class="text-center p-2 border rounded">
                                            <p class="text-muted mb-1 fs-12">Total Claims</p>
                                            <h6 class="mb-0">-</h6>
                                        </div>
                                    </div>
                                    <div class="col-6">
                                        <div class="text-center p-2 border rounded">
                                            <p class="text-muted mb-1 fs-12">Pending</p>
                                            <h6 class="mb-0 text-warning">-</h6>
                                        </div>
                                    </div>
                                </div>

                                <div class="row g-2 mb-3">
                                    <div class="col-6">
                                        <div class="text-center p-2 border rounded">
                                            <p class="text-muted mb-1 fs-12">Approved</p>
                                            <h6 class="mb-0 text-success">-</h6>
                                        </div>
                                    </div>
                                    <div class="col-6">
                                        <div class="text-center p-2 border rounded">
                                            <p class="text-muted mb-1 fs-12">Total Amount</p>
                                            <h6 class="mb-0">₹ -</h6>
                                        </div>
                                    </div>
                                </div>

                                <div class="d-flex gap-2">
                                    <a href="#" class="btn btn-sm btn-primary flex-grow-1 waves-effect waves-light">
                                        <i class="ri-eye-line me-1"></i>View Claims
                                    </a>
                                    <a href="#" class="btn btn-sm btn-soft-secondary waves-effect waves-light">
                                        <i class="ri-mail-line"></i>
                                    </a>
                                </div>
                            </div>
                        </div>
                    </div>
                @empty
                    <div class="col-12">
                        <div class="card">
                            <div class="card-body text-center py-5">
                                <i class="ri-user-line fs-1 text-muted d-block mb-3"></i>
                                <h5 class="text-muted">No team members found</h5>
                                <p class="text-muted mb-0">You don't have any team members assigned yet</p>
                            </div>
                        </div>
                    </div>
                @endforelse
            </div>

            {{-- Team Claims Summary Table --}}
            @if(count($teamMembers ?? []) > 0)
                <div class="row mt-4">
                    <div class="col-12">
                        <div class="card">
                            <div class="card-header align-items-center d-flex">
                                <h4 class="card-title mb-0 flex-grow-1">Team Claims Summary</h4>
                            </div>
                            <div class="card-body p-0">
                                <div class="table-responsive">
                                    <table class="table table-sm table-hover align-middle mb-0">
                                        <thead class="table-light">
                                            <tr>
                                                <th>Employee Name</th>
                                                <th class="text-center">Total Claims</th>
                                                <th class="text-center">Pending</th>
                                                <th class="text-center">Approved</th>
                                                <th class="text-center">Returned</th>
                                                <th class="text-center">Total Amount</th>
                                                <th class="text-center">Avg Amount</th>
                                            </tr>
                                        </thead>
                                        <tbody id="teamSummaryTable">
                                            <tr>
                                                <td colspan="7" class="text-center text-muted py-4">
                                                    <div class="spinner-border spinner-border-sm text-primary" role="status"></div>
                                                    <p class="mt-2 mb-0">Loading team summary...</p>
                                                </td>
                                            </tr>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            @endif
        </div>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function () {
            loadTeamSummary();
        });

        function loadTeamSummary() {
            // TODO: Implement AJAX call to fetch team summary data
            // Call endpoint: /reporting-manager/api/team-claims
        }
    </script>
@endsection
