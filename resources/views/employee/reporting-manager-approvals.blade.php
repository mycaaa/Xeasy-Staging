@extends('layouts.app')
@section('content')
    <div class="page-content">
        <div class="container-fluid">

            {{-- Page Header --}}
            <div class="row mb-3 pb-1">
                <div class="col-12">
                    <div class="d-flex align-items-lg-center flex-lg-row flex-column">
                        <div class="flex-grow-1">
                            <h4 class="fs-16 mb-1">Approvals</h4>
                            <p class="text-muted mb-0">Manage and review team member claims</p>
                        </div>
                        <div class="mt-3 mt-lg-0">
                            <a href="{{ url('/reporting-manager/dashboard') }}" class="btn btn-soft-primary waves-effect waves-light">
                                <i class="ri-arrow-left-line me-1"></i>Back to Dashboard
                            </a>
                        </div>
                    </div>
                </div>
            </div>

            {{-- Status Tabs --}}
            <ul class="nav nav-tabs nav-tabs-custom nav-primary mb-3" role="tablist">
                <li class="nav-item">
                    <a class="nav-link {{ $status === 'pending' ? 'active' : '' }}" data-bs-toggle="tab" href="#pending-tab" role="tab">
                        <i class="ri-time-line me-1 align-bottom"></i> Pending
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link {{ $status === 'approved' ? 'active' : '' }}" data-bs-toggle="tab" href="#approved-tab" role="tab">
                        <i class="ri-check-double-line me-1 align-bottom"></i> Approved
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link {{ $status === 'returned' ? 'active' : '' }}" data-bs-toggle="tab" href="#returned-tab" role="tab">
                        <i class="ri-close-circle-line me-1 align-bottom"></i> Returned
                    </a>
                </li>
            </ul>

            {{-- Tab Content --}}
            <div class="tab-content">

                {{-- Pending Approvals --}}
                <div class="tab-pane {{ $status === 'pending' ? 'active' : '' }}" id="pending-tab" role="tabpanel">
                    <div class="card">
                        <div class="card-header align-items-center d-flex">
                            <h4 class="card-title mb-0 flex-grow-1">Pending Approvals</h4>
                            <button class="btn btn-sm btn-soft-primary" id="refreshPendingTable">
                                <i class="ri-refresh-line"></i>
                            </button>
                        </div>
                        <div class="card-body p-0">
                            <div class="table-responsive">
                                <table class="table table-hover align-middle mb-0">
                                    <thead class="table-light">
                                        <tr>
                                            <th>Employee</th>
                                            <th>Claim ID</th>
                                            <th>Claim Type</th>
                                            <th>Amount</th>
                                            <th>Submitted Date</th>
                                            <th class="text-center">Days Pending</th>
                                            <th class="text-center">Action</th>
                                        </tr>
                                    </thead>
                                    <tbody id="pendingApprovalsList">
                                        <tr>
                                            <td colspan="7" class="text-center text-muted py-4">
                                                <div class="spinner-border spinner-border-sm text-primary" role="status"></div>
                                                <p class="mt-2 mb-0">Loading pending approvals...</p>
                                            </td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>

                {{-- Approved Claims --}}
                <div class="tab-pane {{ $status === 'approved' ? 'active' : '' }}" id="approved-tab" role="tabpanel">
                    <div class="card">
                        <div class="card-header align-items-center d-flex">
                            <h4 class="card-title mb-0 flex-grow-1">Approved Claims</h4>
                        </div>
                        <div class="card-body p-0">
                            <div class="table-responsive">
                                <table class="table table-hover align-middle mb-0">
                                    <thead class="table-light">
                                        <tr>
                                            <th>Employee</th>
                                            <th>Claim ID</th>
                                            <th>Claim Type</th>
                                            <th>Amount</th>
                                            <th>Approved Date</th>
                                            <th class="text-center">Status</th>
                                            <th class="text-center">Action</th>
                                        </tr>
                                    </thead>
                                    <tbody id="approvedClaimsList">
                                        <tr>
                                            <td colspan="7" class="text-center text-muted py-4">
                                                <div class="spinner-border spinner-border-sm text-primary" role="status"></div>
                                                <p class="mt-2 mb-0">Loading approved claims...</p>
                                            </td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>

                {{-- Returned Claims --}}
                <div class="tab-pane {{ $status === 'returned' ? 'active' : '' }}" id="returned-tab" role="tabpanel">
                    <div class="card">
                        <div class="card-header align-items-center d-flex">
                            <h4 class="card-title mb-0 flex-grow-1">Returned Claims</h4>
                        </div>
                        <div class="card-body p-0">
                            <div class="table-responsive">
                                <table class="table table-hover align-middle mb-0">
                                    <thead class="table-light">
                                        <tr>
                                            <th>Employee</th>
                                            <th>Claim ID</th>
                                            <th>Claim Type</th>
                                            <th>Amount</th>
                                            <th>Returned Date</th>
                                            <th class="text-center">Reason</th>
                                            <th class="text-center">Action</th>
                                        </tr>
                                    </thead>
                                    <tbody id="returnedClaimsList">
                                        <tr>
                                            <td colspan="7" class="text-center text-muted py-4">
                                                <div class="spinner-border spinner-border-sm text-primary" role="status"></div>
                                                <p class="mt-2 mb-0">Loading returned claims...</p>
                                            </td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>

            </div>
        </div>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function () {
            loadPendingApprovals();

            document.getElementById('refreshPendingTable')?.addEventListener('click', function () {
                loadPendingApprovals();
            });

            // Load data on tab change
            document.querySelectorAll('[data-bs-toggle="tab"]').forEach(tab => {
                tab.addEventListener('shown.bs.tab', function (e) {
                    const target = e.target.getAttribute('href');
                    if (target === '#pending-tab') {
                        loadPendingApprovals();
                    } else if (target === '#approved-tab') {
                        loadApprovedClaims();
                    } else if (target === '#returned-tab') {
                        loadReturnedClaims();
                    }
                });
            });
        });

        function loadPendingApprovals() {
            const tbody = document.getElementById('pendingApprovalsList');
            // TODO: Implement AJAX call to fetch pending approvals
            // For now, show placeholder
        }

        function loadApprovedClaims() {
            const tbody = document.getElementById('approvedClaimsList');
            // TODO: Implement AJAX call to fetch approved claims
        }

        function loadReturnedClaims() {
            const tbody = document.getElementById('returnedClaimsList');
            // TODO: Implement AJAX call to fetch returned claims
        }
    </script>
@endsection
