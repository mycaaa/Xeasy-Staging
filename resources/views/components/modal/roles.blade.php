<div class="modal fade" id="roleModal" tabindex="-1" aria-labelledby="roleLabel">
    <div class="modal-dialog modal-xl">
        <div class="modal-content">
            <div class="progress-container" style="display: none;">
                <div class="progres" style="height: 5px;">
                    <div class="indeterminate" style="background-color: var(--vz-primary);"></div>
                </div>
            </div>
            <div class="modal-header">
                <h5 class="modal-title" id="addRoleLabel">Add New Role</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <form action="javascript:void(0);" id="roleForm" novalidate>
                    <div class="row g-3">
                        <div class="col-md-4">
                            <label for="role_name" class="form-label">Name</label>
                            <input type="text" class="form-control" id="role_name" name="role_name"
                                placeholder="Enter role name" required />
                        </div>
                        <div class="col-md-4">
                            <div class="form-check form-switch" style="margin-top: 32px;">
                                <input class="form-check-input" name="is_active" type="checkbox" checked role="switch"
                                    id="is_active" onchange="toggleSwitchText()" />
                                <label class="form-check-label" for="is_active" id="is_active_label">Active</label>
                            </div>
                        </div>
                        <div class="col-md-12">
                            <label for="role_permissions" class="form-label">Permissions</label>
                            <div id="allPermissionsContainer"
                                class="accordion nesting-accordion custom-accordionwithicon accordion-border-box">
                            </div>
                        </div>
                        <div class="col-lg-12">
                            <div class="hstack gap-2 justify-content-end">
                                <button type="button"
                                    class="btn btn-primary btn-label waves-effect waves-light rounded-pill"
                                    id="saveRoleBtn">
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