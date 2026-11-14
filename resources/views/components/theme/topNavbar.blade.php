<div id="removeNotificationModal" class="modal fade zoomIn" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"
                    id="NotificationModalbtn-close"></button>
            </div>
            <div class="modal-body">
                <div class="mt-2 text-center">
                    {{-- PERF-09: plain icon; the animated one loaded a 310 KB script and an outside file on every page --}}
                    <i class="ri-delete-bin-line text-danger" style="font-size:64px" aria-hidden="true"></i>
                    <div class="mt-4 pt-2 fs-15 mx-4 mx-sm-5">
                        <h4>Are you sure ?</h4>
                        <p class="text-muted mx-4 mb-0">Are you sure you want to remove this Notification ?</p>
                    </div>
                </div>
                <div class="d-flex gap-2 justify-content-center mt-4 mb-2">
                    <button type="button" class="btn w-sm btn-light" data-bs-dismiss="modal">Close</button>
                    <button type="button" class="btn w-sm btn-danger" id="delete-notification">Yes, Delete It!</button>
                </div>
            </div>
        </div>
    </div>
</div>
<header id="page-topbar">
    <div class="layout-width">
        <div class="navbar-header">
            <div class="d-flex">
                <div id="horizontal"></div>
                <div class="navbar-brand-box horizontal-logo">
                    <a href="#" class="logo logo-dark">
                        <span class="logo-sm">
                            <img src="{{ URL::to('/') }}/assets/images/logo-sm.png" alt="" height="22">
                        </span>
                        <span class="logo-lg">
                            <img src="{{ URL::to('/') }}/custom/xeasy_logo_dark.png" alt="" height="60">
                        </span>
                    </a>
                    <a href="#" class="logo logo-light">
                        <span class="logo-sm">
                            <img src="{{ URL::to('/') }}/assets/images/logo-sm.png" alt="" height="22">
                        </span>
                        <span class="logo-lg">
                            <img src="{{ URL::to('/') }}/assets/images/logo-light.png" alt="" height="17">
                        </span>
                    </a>
                </div>
                <button type="button"
                    class="btn btn-sm px-3 fs-16 header-item vertical-menu-btn topnav-hamburger material-shadow-none"
                    id="topnav-hamburger-icon" style="margin-top: 12px" aria-label="Show or hide the menu" title="Menu">
                    <span class="hamburger-icon">
                        <span></span>
                        <span></span>
                        <span></span>
                    </span>
                </button>
                @can('employeee_status')
                    <form class="app-search d-none d-md-block">
                        <meta name="csrf-token" content="{{ csrf_token() }}">
                        <div class="position-relative">
                            <input type="text" class="form-control" placeholder="Search employee.. (Ctrl+E)" aria-label="Search employee.. (Ctrl+E)"
                                autocomplete="off" id="empSearchOptions" value="">
                            <span class="mdi mdi-magnify search-widget-icon"></span>
                            <span class="mdi mdi-close-circle search-widget-icon search-widget-icon-close d-none"
                                id="search-close-options"></span>
                        </div>
                        <div class="dropdown-menu dropdown-menu-lg" id="search-dropdown">
                            <div data-simplebar style="max-height: 320px;">
                                <div class="dropdown-header" style="display: flex; justify-content: space-between; align-items: center;">
                                    <h6 class="text-overflow text-muted mb-0">Employees</h6>
                                    <div>
                                        <select id="statusFilter" class="form-select form-select-sm py-0" style="font-size: 11px; height: 24px; padding-right: 20px;">
                                            <option value="A" selected>Active & Resigned</option>
                                            <option value="pure_active">Active Only</option>
                                            <option value="R">Resigned Only</option>
                                            <option value="D">Deactive</option>
                                            <option value="all">All</option>
                                        </select>
                                    </div>
                                </div>
                                <div class="notification-list">
                                </div>
                            </div>
                            <div class="text-center pt-3 pb-1">
                                <a href="/admin/employees" class="btn btn-primary btn-sm">View All Employees <i
                                        class="ri-arrow-right-line ms-1"></i></a>
                            </div>
                        </div>
                    </form>
                @endcan
            </div>
            <div class="d-flex align-items-center">
                <button type="button"
                    class="btn btn-icon btn-topbar material-shadow-none btn-ghost-secondary rounded-circle"
                    data-bs-toggle="tooltip" data-bs-placement="bottom" title="Fullscreen" aria-label="Fullscreen" data-toggle="fullscreen">
                    <i class='bx bx-fullscreen fs-22'></i>
                </button>
                <button type="button"
                    class="btn btn-icon btn-topbar material-shadow-none btn-ghost-secondary rounded-circle light-dark-mode"
                    data-bs-toggle="tooltip" data-bs-placement="bottom" title="Toggle Dark Mode" aria-label="Toggle dark mode">
                    <i class='bx bx-moon fs-22'></i>
                </button>
                <div class="ms-1 header-item d-none d-sm-flex">


                    <button type="button" id="clearCacheBtn"
                        class="btn btn-icon btn-topbar material-shadow-none btn-ghost-secondary rounded-circle"
                        title="Refresh (clear cache)" aria-label="Refresh (clear cache)" data-bs-toggle="tooltip" data-bs-placement="bottom">
                        <i class='bx bx-revision fs-22'></i>
                    </button>
                    @can('git_command_panel')
                        <!-- <button type="button" id="gitCommandBtn"
                                                                        class="btn btn-icon btn-topbar material-shadow-none btn-ghost-secondary rounded-circle"
                                                                        title="Git Commands" data-bs-toggle="tooltip" data-bs-placement="bottom">
                                                                        <i class='bx bx-git-branch fs-22'></i>
                                                                    </button> -->
                    @endcan
                </div>
                {{-- Notifications Dropdown (Restricted to Verifier, Admin, and Super Admin) --}}
                @if(\App\Services\SystemAlertsService::isAllowedUser(auth()->user()))
                @inject('alertsService', 'App\Services\SystemAlertsService')
                @php
                    $sysAlertsSummary = $alertsService->cachedNotificationSummary(); // PERF-08
                    $totCount     = $sysAlertsSummary['total_count'] ?? 0;
                    $transCount   = $sysAlertsSummary['vvnr_transfers_count'] ?? 0;
                    $joinCount    = $sysAlertsSummary['new_joinings_count'] ?? 0;
                    $promCount    = $sysAlertsSummary['promotions_count'] ?? 0;
                    $imprestCount = $sysAlertsSummary['missing_imprest_count'] ?? 0;
                    $previewTransfers  = array_slice($sysAlertsSummary['transfers'] ?? [], 0, 4);
                    $previewJoinings   = array_slice($sysAlertsSummary['joinings'] ?? [], 0, 4);
                    $previewPromotions = array_slice($sysAlertsSummary['promotions'] ?? [], 0, 4);
                    $previewImprest    = array_slice($sysAlertsSummary['missing_imprest'] ?? [], 0, 4);
                @endphp
                <div class="dropdown topbar-head-dropdown ms-1 header-item" id="notificationDropdown">
                    <button type="button"
                        class="btn btn-icon btn-topbar material-shadow-none btn-ghost-secondary rounded-circle"
                        id="page-header-notifications-dropdown" data-bs-toggle="dropdown" aria-label="Notifications" title="Notifications"
                        data-bs-auto-close="outside" aria-haspopup="true" aria-expanded="false">
                        <i class='bx bx-bell fs-22'></i>
                        @if($totCount > 0)
                            <span class="position-absolute topbar-badge fs-10 translate-middle badge rounded-pill bg-danger">
                                {{ $totCount }}
                                <span class="visually-hidden">alerts</span>
                            </span>
                        @endif
                    </button>
                    <div class="dropdown-menu dropdown-menu-lg dropdown-menu-end p-0"
                        aria-labelledby="page-header-notifications-dropdown">
                        <div class="dropdown-head bg-primary bg-pattern rounded-top">
                            <div class="p-3">
                                <div class="row align-items-center">
                                    <div class="col">
                                        <h6 class="m-0 fs-15 fw-semibold text-white"> System Alerts & Notifications </h6>
                                    </div>
                                    <div class="col-auto">
                                        <span class="badge bg-light text-primary fs-12 fw-bold"> {{ $totCount }} New</span>
                                    </div>
                                </div>
                            </div>
                            <div class="px-2 pt-2">
                                <ul class="nav nav-tabs dropdown-tabs nav-tabs-custom" role="tablist">
                                    <li class="nav-item waves-effect waves-light">
                                        <a class="nav-link active fs-12" data-bs-toggle="tab" href="#transfers-noti-tab" role="tab">
                                            Transfers ({{ $transCount }})
                                        </a>
                                    </li>
                                    <li class="nav-item waves-effect waves-light">
                                        <a class="nav-link fs-12" data-bs-toggle="tab" href="#joinings-noti-tab" role="tab">
                                            Joinings ({{ $joinCount }})
                                        </a>
                                    </li>
                                    <li class="nav-item waves-effect waves-light">
                                        <a class="nav-link fs-12" data-bs-toggle="tab" href="#promotions-noti-tab" role="tab">
                                            Promotions ({{ $promCount }})
                                        </a>
                                    </li>
                                    <li class="nav-item waves-effect waves-light">
                                        <a class="nav-link fs-12" data-bs-toggle="tab" href="#imprest-noti-tab" role="tab">
                                            Imprest ({{ $imprestCount }})
                                        </a>
                                    </li>
                                </ul>
                            </div>
                        </div>

                        <div class="tab-content position-relative">

                            {{-- TAB 1: Transfers --}}
                            <div class="tab-pane fade show active py-2 ps-2" id="transfers-noti-tab" role="tabpanel">
                                <div data-simplebar style="max-height: 280px;" class="pe-2">
                                    @forelse($previewTransfers as $t)
                                    <a href="{{ route('admin.system-notifications.index') }}?tab=transfers" class="text-reset notification-item d-block dropdown-item position-relative">
                                        <div class="d-flex align-items-center">
                                            <div class="avatar-xs me-3 flex-shrink-0">
                                                <span class="avatar-title bg-primary-subtle text-primary rounded-circle fs-16">
                                                    <i class="ri-swap-box-line"></i>
                                                </span>
                                            </div>
                                            <div class="flex-grow-1">
                                                <h6 class="mt-0 mb-1 fs-13 fw-semibold">{{ $t['emp_name'] ?? '' }} <small class="text-muted">({{ $t['emp_code'] ?? '' }})</small></h6>
                                                <div class="fs-12 text-muted">
                                                    <span class="badge bg-warning-subtle text-warning">VVNR</span> → <span class="badge bg-success-subtle text-success">VNR</span>
                                                    <span class="ms-1">{{ $t['department_name'] ?? '' }}</span>
                                                </div>
                                            </div>
                                        </div>
                                    </a>
                                    @empty
                                    <div class="text-center text-muted py-3 fs-13">
                                        <i class="ri-inbox-line me-1"></i> No recent VVNR → VNR transfers.
                                    </div>
                                    @endforelse
                                </div>
                            </div>

                            {{-- TAB 2: Joinings --}}
                            <div class="tab-pane fade py-2 ps-2" id="joinings-noti-tab" role="tabpanel">
                                <div data-simplebar style="max-height: 280px;" class="pe-2">
                                    @forelse($previewJoinings as $j)
                                    <a href="{{ route('admin.system-notifications.index') }}?tab=joinings" class="text-reset notification-item d-block dropdown-item position-relative">
                                        <div class="d-flex align-items-center">
                                            <div class="avatar-xs me-3 flex-shrink-0">
                                                <span class="avatar-title bg-success-subtle text-success rounded-circle fs-16">
                                                    <i class="ri-user-add-line"></i>
                                                </span>
                                            </div>
                                            <div class="flex-grow-1">
                                                <h6 class="mt-0 mb-1 fs-13 fw-semibold">{{ $j['emp_name'] ?? '' }} <small class="text-muted">({{ $j['emp_code'] ?? '' }})</small></h6>
                                                <div class="fs-12 text-muted">
                                                    <span>{{ $j['department_name'] ?? '' }}</span> • Joined: <strong class="text-dark">{{ !empty($j['DateJoining']) && \App\Support\ZeroDate::has($j['DateJoining']) ? date('d M Y', strtotime($j['DateJoining'])) : '-' }}</strong>
                                                </div>
                                            </div>
                                        </div>
                                    </a>
                                    @empty
                                    <div class="text-center text-muted py-3 fs-13">
                                        <i class="ri-user-follow-line me-1"></i> No new joinings in last 90 days.
                                    </div>
                                    @endforelse
                                </div>
                            {{-- TAB 3: Promotions --}}
                            <div class="tab-pane fade py-2 ps-2" id="promotions-noti-tab" role="tabpanel">
                                <div data-simplebar style="max-height: 280px;" class="pe-2">
                                    @forelse($previewPromotions as $p)
                                    <a href="{{ route('admin.system-notifications.index') }}?tab=promotions" class="text-reset notification-item d-block dropdown-item position-relative">
                                        <div class="d-flex align-items-center">
                                            <div class="avatar-xs me-3 flex-shrink-0">
                                                <span class="avatar-title bg-info-subtle text-info rounded-circle fs-16">
                                                    <i class="ri-award-line"></i>
                                                </span>
                                            </div>
                                            <div class="flex-grow-1">
                                                <h6 class="mt-0 mb-1 fs-13 fw-semibold">{{ $p['emp_name'] ?? '' }} <small class="text-muted">({{ $p['emp_code'] ?? '' }})</small></h6>
                                                <div class="fs-12 text-muted">
                                                    Grade: <span class="badge bg-warning-subtle text-warning">{{ $p['previous_grade'] ?? '-' }}</span> → <span class="badge bg-success-subtle text-success">{{ $p['new_grade'] ?? '-' }}</span>
                                                </div>
                                            </div>
                                        </div>
                                    </a>
                                    @empty
                                    <div class="text-center text-muted py-3 fs-13">
                                        <i class="ri-award-line me-1"></i> No promotions in last 90 days.
                                    </div>
                                    @endforelse
                                </div>
                            </div>

                            {{-- TAB 4: Missing Imprest --}}
                            <div class="tab-pane fade py-2 ps-2" id="imprest-noti-tab" role="tabpanel">
                                <div data-simplebar style="max-height: 280px;" class="pe-2">
                                    @forelse($previewImprest as $m)
                                    <a href="{{ route('admin.system-notifications.index') }}?tab=imprest" class="text-reset notification-item d-block dropdown-item position-relative">
                                        <div class="d-flex align-items-center">
                                            <div class="avatar-xs me-3 flex-shrink-0">
                                                <span class="avatar-title bg-warning-subtle text-warning rounded-circle fs-16">
                                                    <i class="ri-alert-line"></i>
                                                </span>
                                            </div>
                                            <div class="flex-grow-1">
                                                <h6 class="mt-0 mb-1 fs-13 fw-semibold">{{ $m['emp_name'] ?? '' }} <small class="text-muted">({{ $m['emp_code'] ?? '' }})</small></h6>
                                                <div class="fs-12 text-muted">
                                                    Grade: <span class="badge bg-warning-subtle text-warning">{{ $m['grade_name'] ?? '' }}</span>
                                                    • Eligible: <strong class="text-success">₹{{ number_format($m['eligible_amount'] ?? 0) }}</strong>
                                                </div>
                                            </div>
                                        </div>
                                    </a>
                                    @empty
                                    <div class="text-center text-muted py-3 fs-13">
                                        <i class="ri-checkbox-circle-line me-1 text-success"></i> All eligible employees have imprest!
                                    </div>
                                    @endforelse
                                </div>
                            </div>

                        </div>

                        <div class="p-2 text-center border-top bg-light">
                            <a href="{{ route('admin.system-notifications.index') }}" class="btn btn-soft-primary btn-sm w-100 fw-bold">
                                View All Alert Details <i class="ri-arrow-right-line align-middle ms-1"></i>
                            </a>
                        </div>
                    </div>
                </div>
                @endif
                {{-- Company/Year Switcher Button --}}
                <button type="button"
                    class="btn btn-topbar material-shadow-none btn-ghost-secondary rounded-circle d-flex align-items-center gap-1"
                    data-bs-toggle="modal" data-bs-target="#companyYearModal" title="Switch Company/Year" aria-label="Switch company or year">
                    <i class='ri-building-line fs-22'></i>
                    <span class="d-none d-lg-inline-block fs-12 fw-medium text-truncate" style="max-width: 120px;">
                        {{ session('company_name', 'Company') }} | {{ session('year_value', 'FY') }}
                    </span>
                </button>

                <div class="dropdown ms-sm-3 header-item topbar-user">
                    <button type="button" class="btn material-shadow-none" style="padding: 0 5px"
                        id="page-header-user-dropdown" data-bs-toggle="dropdown" aria-haspopup="true" aria-label="Profile menu"
                        aria-expanded="false">
                        <span class="d-flex align-items-center">
                            <img class="rounded-circle header-profile-user"
                                src="{{ \App\Support\EmployeePhoto::forSession() }}"
                                onerror="this.onerror=null;this.src='{{ asset('custom/no_user.png') }}'" alt="Profile photo" decoding="async">
                            <span class="text-start ms-xl-2">
                                <span class="d-none d-xl-inline-block ms-1 fw-medium user-name-text">
                                    {{ session('emp_name') ?: (Auth::user()?->name ?? 'User') }}
                                </span>
                                <span class="d-none d-xl-block ms-1 fs-12 user-name-sub-text text-danger">
                                    <span>{{ session('company') }}</span> - <span
                                        id="financialYear">{{ session('year_value') }}</span>
                                </span>
                            </span>
                        </span>
                    </button>
                    <div class="dropdown-menu dropdown-menu-end">
                        <h6 class="dropdown-header">Welcome {{ session('emp_name') ?: (Auth::user()?->name ?? 'User') }}!</h6>
                        @if (session('back_office') === true)
                            <a class="dropdown-item" href="">
                                <i class="mdi mdi-briefcase text-muted fs-16 align-middle me-1"></i>
                                <span class="align-middle">Back Office</span>
                            </a>
                        @endif
                        <a class="dropdown-item" href=""><i
                                class="mdi mdi-account-circle text-muted fs-16 align-middle me-1"></i> <span
                                class="align-middle">Profile</span></a>
                        <a class="dropdown-item" href="#"><i
                                class="mdi mdi-lifebuoy text-muted fs-16 align-middle me-1"></i> <span
                                class="align-middle">Help</span></a>
                        <div class="dropdown-divider"></div>
                        @can('settings_overview')
                            <a class="dropdown-item d-flex justify-content-between align-items-center" href="/settings">
                                <div>
                                    <i class="mdi mdi-cog-outline text-muted fs-16 align-middle me-1"></i>
                                    <span class="align-middle">Settings</span>
                                </div>
                                <span class="badge bg-success-subtle text-success mt-1">New</span>
                            </a>
                        @endcan
                        <a class="dropdown-item" href="#"><i
                                class="mdi mdi-lock text-muted fs-16 align-middle me-1"></i> <span
                                class="align-middle">Lock screen</span></a>
                        <form method="POST" action="{{ route('logout') }}">
                            @csrf
                            <button type="submit" class="dropdown-item">
                                <i class="mdi mdi-logout text-muted fs-16 align-middle me-1"></i>
                                <span class="align-middle" data-key="t-logout">Logout</span>
                            </button>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>
</header>

<!-- Company/Year Switcher Modal -->
<div class="modal fade" id="companyYearModal" data-bs-backdrop="static" tabindex="-1"
    aria-labelledby="companyYearModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content border-0">
            <div class="modal-header d-flex justify-content-between align-items-center px-4 py-3">
                <h5 class="modal-title d-flex align-items-center mb-0" id="companyYearModalLabel">
                    <i class="ri-building-line me-2 fs-5"></i>
                    Company & Financial Year
                </h5>
                  <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <form id="companyYearForm">
                    @csrf
                    <div class="mb-3">
                        @php
                            $companies = [
                                1 => 'VNR Seeds Pvt Ltd',
                                3 => 'VNR Nursery Pvt Ltd',
                                11 => 'VVNR',
                            ];
                            $companyAccess = array_filter(explode(',', session('company_access_id', '')));
                            // If no company_access_id set, use current company_id as default
                            if (empty($companyAccess)) {
                                $companyAccess = [session('company_id', 1)];
                            }
                            $hasMultipleCompanies = count($companyAccess) > 1;
                            $selectedCompany = old('company') ?? (session('company_id') ?? ($companyAccess[0] ?? 1));
                        @endphp
                        <label for="modalCompany" class="form-label">Company</label>
                        <select name="company" id="modalCompany" class="form-select" data-current-company-id="{{ session('company_id', 1) }}" required
                            {{ !$hasMultipleCompanies ? 'disabled' : '' }}>
                            @foreach ($companies as $id => $name)
                                @if (in_array($id, $companyAccess))
                                    <option value="{{ $id }}"
                                        {{ $id == $selectedCompany ? 'selected' : '' }}>
                                        {{ $name }}
                                    </option>
                                @endif
                            @endforeach
                        </select>
                        @if(!$hasMultipleCompanies)
                            <input type="hidden" name="company" value="{{ $selectedCompany }}">
                            <small class="text-muted">Your assigned company</small>
                        @endif
                    </div>
                    <div class="mb-3">
                        <label for="modalFinancialYear" class="form-label">Financial Year</label>
                        <select name="financial_year" id="modalFinancialYear" class="form-select" required>
                            <!-- Options will be loaded dynamically -->
                        </select>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-primary btn-label waves-effect waves-light rounded-pill ms-auto"
                    id="switchCompanyYear"> <i
                        class="ri-check-double-line label-icon align-middle rounded-pill fs-16 me-2">
                        <span class="loader" style="display: none;"></span>
                    </i>
                    Switch & Refresh
                </button>
            </div>
        </div>
    </div>
</div>
<div id="companyYearModalTrigger"
    data-show="{{ session('show_company_year_modal') ? 'true' : 'false' }}">
</div>
@if (session('show_company_year_modal'))
    @php session()->forget('show_company_year_modal'); @endphp
@endif
<div class="modal fade" id="gitCommandModal" data-bs-backdrop="static" tabindex="-1"
    aria-labelledby="gitCommandModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0">
            <div class="modal-header">
                <h5 class="modal-title" id="gitCommandModalLabel">
                    Git Command Panel
                </h5>
                <div>
                    <i id="showGitInfo" class="ri-information-line text-primary ms-2" style="cursor:pointer;"
                        title="View Command Details"></i>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
            </div>
            <div class="modal-body">
                <div id="gitFormSection">
                    <div class="mb-3">
                        <label class="form-label">Select Git Command</label>
                        <select id="gitCommand" class="form-select">
                            <option value="">-- Select Command --</option>
                            <option value="status">1. Git Status</option>
                            <option value="pull">2. Git Pull</option>
                            <option value="add">3. Git Add .</option>
                            <option value="commit">4. Git Commit (default msg)</option>
                            <option value="push">5. Git Push</option>
                            <option value="fetch">6. Git Fetch</option>
                            <option value="branch">7. Git Branch</option>
                            <option value="log">8. Git Log (last 5)</option>
                            <option value="refresh-ignore">9. Refresh .gitignore Changes</option>
                            <option value="force-pull">10. Force Pull (Discard Local & Sync with Remote)</option>
                            <option value="force-push">11. Force Push (Overwrite Remote)</option>
                            <option value="reset-local">12. Keep Git Version (Discard Local Changes)</option>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Password</label>
                        <input type="password" id="gitPassword" class="form-control"
                            placeholder="Enter password to proceed" aria-label="Enter password to proceed">
                    </div>
                    <div id="commandInfoBox" class="alert alert-secondary d-none small"></div>
                    <div class="mb-3 text-center">
                        <button id="runGitCommand" class="btn btn-primary w-100">Run Command</button>
                    </div>
                    <div id="gitOutputContainer" class="d-none">
                        <h6 class="text-muted mt-3">Output:</h6>
                        <pre id="gitOutput" class="bg-light p-2 border rounded text-break small"
                            style="white-space: pre-wrap; word-wrap: break-word;"></pre>
                    </div>
                </div>
                <div id="gitInfoSection" class="d-none">
                    <div id="gitCommandDetails"></div>
                    <div class="text-center mt-3">
                        <button id="backToForm" class="btn btn-primary btn-sm">← Back to Command Panel</button>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
