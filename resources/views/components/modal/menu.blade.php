<div class="modal fade" id="menuModal" tabindex="-1" aria-labelledby="menuLabel">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="progress-container" style="display: none">
                <div class="progres" style="height: 5px;">
                    <div class="indeterminate" style="background-color: var(--vz-primary);"></div>
                </div>
            </div>
            <div class="modal-header">
                <h5 class="modal-title" id="addMenuLabel">Add New Menu</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <form action="javascript:void(0);" id="menuForm" novalidate>
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label for="title" class="form-label">Menu Name</label>
                            <input type="text" class="form-control" id="title" name="title"
                                placeholder="Enter menu name" required />
                        </div>
                        <div class="col-md-6">
                            <label for="menuSelect" class="form-label">Parent Menu</label>
                            <select class="form-control" id="menuSelect" name="parent_id" required>

                            </select>
                        </div>
                        <div class="col-md-6">
                            <label for="icon" class="form-label">Icon</label>
                            <input type="text" class="form-control" id="icon" name="icon"
                                placeholder="Enter icon class" />
                        </div>
                        <div class="col-md-6">
                            <label for="order" class="form-label">Order</label>
                            <input type="number" class="form-control" id="order" name="order"
                                placeholder="Enter menu order" />
                        </div>
                        <div class="col-md-6">
                            <label for="url" class="form-label">Menu URL</label>
                            <input type="text" class="form-control" id="url" name="url" placeholder="Enter menu URL"
                                required />
                        </div>
                        <div class="col-md-6">
                            <label for="permissionSelect" class="form-label">Permission</label>
                            <select class="form-control" id="permissionSelect" name="permission" required>

                            </select>
                        </div>
                        <div class="col-md-6">
                            <div class="form-check form-switch">
                                <input class="form-check-input" name="is_active" type="checkbox" checked role="switch"
                                    id="is_active" onchange="toggleSwitchText()" />
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
                                    id="saveMenuBtn">
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