<div class="modal fade" id="apiModal" tabindex="-1" aria-labelledby="apiLabel">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="progress-container" style="display: none;">
                <div class="progres" style="height: 5px;">
                    <div class="indeterminate" style="background-color: var(--vz-primary);"></div>
                </div>
            </div>
            <div class="modal-header">
                <h5 class="modal-title" id="addApiLabel">Add New API</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <form action="javascript:void(0);" id="apiForm" novalidate>
                    <div class="row g-3">

                        <div class="col-xxl-6">
                            <label for="claim_id" class="form-label">Claim ID</label>
                            <input type="text" class="form-control" id="claim_id" name="claim_id" placeholder="Enter claim ID" required />
                        </div>

                        <div class="col-xxl-6">
                            <label for="api_name" class="form-label">API Name</label>
                            <input type="text" class="form-control" id="api_name" name="name" placeholder="Enter API name" required />
                        </div>

                        <div class="col-xxl-12">
                            <label for="endpoint" class="form-label">Endpoint URL</label>
                            <input type="text" class="form-control" id="endpoint" name="endpoint" placeholder="Enter endpoint URL" required />
                        </div>

                        <div class="col-xxl-6">
                            <div class="form-check form-switch">
                                <input class="form-check-input" name="status" type="checkbox" checked role="switch" id="api_status" onchange="toggleSwitchText()" />
                                <label class="form-check-label" for="api_status" id="api_status_label">Active</label>
                            </div>
                        </div>

                        <div class="col-lg-12">
                            <div class="hstack gap-2 justify-content-end">
                                <button type="button" class="btn btn-primary btn-label waves-effect waves-light rounded-pill" id="saveApiBtn">
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
