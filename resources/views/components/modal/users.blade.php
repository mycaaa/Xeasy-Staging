<div class="modal fade" id="userModal" data-bs-backdrop="static" tabindex="-1" aria-labelledby="userLabel">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="progress-container" style="display: none;">
                <div class="progres" style="height: 5px;">
                    <div class="indeterminate" style="background-color: var(--vz-primary);"></div>
                </div>
            </div>
            <div class="modal-header">
                <h5 class="modal-title" id="addUserModalTitle">Add New User</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <form action="javascript:void(0);" id="userForm" novalidate autocomplete="off">
                    <input type="hidden" name="user_id" id="user_id" value="">
                    <input type="hidden" name="is_guest" id="is_guest" value="0">
                    <div class="row g-3">
                        <!-- Employee Specific Field -->
                        <div class="col-md-6" id="employeeFieldsWrap">
                            <label for="employee_id" class="form-label">Employee ID <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" id="employee_id" name="employee_id"
                                placeholder="Enter employee ID" autocomplete="off" required />
                            <small class="text-muted">Enter the Employee ID from HRIMS database</small>
                        </div>

                        <!-- Guest Specific Fields -->
                        <div class="col-12" id="guestFieldsWrap" style="display: none;">
                            <div class="p-3 bg-light rounded border mb-2">
                                <div class="d-flex align-items-center mb-2">
                                    <span class="badge bg-warning-subtle text-warning border border-warning-subtle me-2">
                                        <i class="ri-user-shared-line me-1"></i>Guest Account Details
                                    </span>
                                    <small class="text-muted">Non-HRIMS user with custom credentials and granular access control</small>
                                </div>
                                <div class="row g-3">
                                    <div class="col-md-6">
                                        <label for="guest_name" class="form-label">Full Name <span class="text-danger">*</span></label>
                                        <input type="text" class="form-control" id="guest_name" name="guest_name" placeholder="e.g. John Doe (Auditor)" autocomplete="off" />
                                    </div>
                                    <div class="col-md-6">
                                        <label for="guest_username" class="form-label">Guest Username / ID <span class="text-danger">*</span></label>
                                        <div class="input-group">
                                            <span class="input-group-text"><i class="ri-user-line"></i></span>
                                            <input type="text" class="form-control" id="guest_username" name="guest_username" placeholder="e.g. auditor_john" autocomplete="off" />
                                            <button type="button" class="btn btn-outline-secondary btn-sm" id="btnGenGuestUsername" title="Generate auto username">Auto</button>
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <label for="guest_email" class="form-label">Email Address <small class="text-muted">(Optional)</small></label>
                                        <div class="input-group">
                                            <span class="input-group-text"><i class="ri-mail-line"></i></span>
                                            <input type="email" class="form-control" id="guest_email" name="guest_email" placeholder="e.g. guest@example.com" autocomplete="off" />
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <label for="guest_password" class="form-label" id="guestPasswordLabel">Password <span class="text-danger">*</span></label>
                                        <div class="input-group">
                                            <input type="password" class="form-control" id="guest_password" name="guest_password" placeholder="Min 6 characters" autocomplete="new-password" />
                                            <button type="button" class="btn btn-outline-secondary btn-sm" id="btnToggleGuestPass" title="Show / Hide Password">
                                                <i class="ri-eye-line" id="togglePassIcon"></i>
                                            </button>
                                            <button type="button" class="btn btn-outline-primary btn-sm" id="btnGenGuestPass" title="Generate random password">Generate</button>
                                        </div>
                                        <small class="text-muted" id="guestPassHelp">Used to sign in on the main login screen</small>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <label for="role" class="form-label">Role</label>
                            <select class="form-select select2" name="role_id" id="role_id" multiple="multiple"
                                required>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <div class="form-check form-switch">
                                <input class="form-check-input" name="is_active" type="checkbox" checked
                                    permission="switch" id="is_active" onchange="toggleSwitchText()" />
                                <label class="form-check-label" for="is_active" id="is_active_label">Active</label>
                            </div>
                        </div>
                        <div class="col-md-12">
                            <label for="companyAccess">Company Access</label>

                            <!-- Select All -->
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" id="selectAllCompanies">
                                <label class="form-check-label" for="selectAllCompanies">Select All</label>
                            </div>

                            <!-- Company List -->
                            <div class="form-check">
                                <input class="form-check-input company-checkbox" type="checkbox" value="1">
                                <label class="form-check-label">VNR Seeds Pvt Ltd</label>
                            </div>

                            <div class="form-check">
                                <input class="form-check-input company-checkbox" type="checkbox" value="3">
                                <label class="form-check-label">VNR Nursery Pvt Ltd</label>
                            </div>

                            <div class="form-check">
                                <input class="form-check-input company-checkbox" type="checkbox" value="11">
                                <label class="form-check-label">VVNR</label>
                            </div>

                            <!-- Hidden input -->
                            <input type="hidden" name="company_ids" id="company_ids">
                        </div>
                        <div class="col-lg-12">
                            <div class="hstack gap-2 justify-content-end">
                                <button type="button"
                                    class="btn btn-primary btn-label waves-effect waves-light rounded-pill"
                                    id="saveUserBtn">
                                    <i class="ri-check-double-line label-icon align-middle rounded-pill fs-16 me-2">
                                        <span class="loader" style="display: none;"></span>
                                    </i>
                                    Submit
                                </button>
                            </div>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>
<div class="modal fade" id="permissionModal" data-bs-backdrop="static" tabindex="-1"
    aria-labelledby="permissionModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="permissionModalLabel">Assign Permission <span id="userNameTitle"></span>
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <!-- Tabs nav -->
                <ul class="nav nav-tabs mb-3" id="permissionTab" role="tablist">
                    <li class="nav-item" role="presentation">
                        <button class="nav-link active" id="user-permission-tab" data-bs-toggle="tab"
                            data-bs-target="#user-permission" type="button" role="tab" aria-controls="user-permission"
                            aria-selected="true">
                            User Permissions
                        </button>
                    </li>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link" id="claim-type-tab" data-bs-toggle="tab" data-bs-target="#claim-type"
                            type="button" role="tab" aria-controls="claim-type" aria-selected="false">
                            Claim Types
                        </button>
                    </li>
                </ul>

                <!-- Tabs content -->
                <div class="tab-content" id="permissionTabContent">
                    <!-- User Permissions Tab -->
                    <div class="tab-pane fade show active" id="user-permission" role="tabpanel"
                        aria-labelledby="user-permission-tab">
                        <div id="permissions-container"
                            class="accordion nesting-accordion custom-accordionwithicon accordion-border-box"></div>
                    </div>

                    <!-- Claim Type Tab -->
                    <div class="tab-pane fade" id="claim-type" role="tabpanel" aria-labelledby="claim-type-tab">
                        <div id="claim-type-container"
                            class="accordion nesting-accordion custom-accordionwithicon accordion-border-box">
                            <!-- Claim type accordions will be appended here -->
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="importEmployeeModal" data-bs-backdrop="static" tabindex="-1"
    aria-labelledby="importEmployeeModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="importEmployeeModalLabel">Confirm Employee Import</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <p>Are you sure you want to import employees from the HRIMS database? This will create new user accounts
                    for employees who do not already have one.</p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-primary btn-label waves-effect waves-light rounded-pill"
                    id="confirmImportBtn">
                    <i class="ri-check-double-line label-icon align-middle rounded-pill fs-16 me-2">
                        <span class="loader" style="display: none;"></span>
                    </i>
                    Confirm
                </button>
            </div>
        </div>
    </div>
</div>