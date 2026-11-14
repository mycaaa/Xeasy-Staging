    <div class="modal fade" id="claimDetailModal" data-bs-backdrop="static" data-modal-expid="" data-modal-empid=""
        data-viewer-empid="{{ (int) session('employee_id') }}"
        data-readonly-page="{{ (request()->is('approver', 'approver/*', 'vnr/approver*', 'vvnr/approver*', 'nursery/approver*', 'approver-dashboard*', 'dashboard', 'employee-report') || request()->routeIs('employee.dashboard')) ? 1 : 0 }}"
        data-modal-claimid="" data-modal-cgid="" tabindex="-1" aria-labelledby="claimDetailModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-fullscreen">
            <div class="modal-content">
                <div class="modal-header d-flex align-items-center justify-content-between">
                    <h5 class="modal-title" id="claimDetailModalLabel">
                        <i class="ri-file-text-line me-2"></i>Document Viewer & Data Entry
                    </h5>
                    <div class="d-flex align-items-center" style="gap: 10px;">
                        <span id="empDisplay" class="fw-semibold text-primary d-flex align-items-center"
                            style="gap:4px;">
                            <span id="empDisplayValue"></span>
                        </span>
                        <span id="expIdDisplay" class="fw-semibold d-flex align-items-center" style="gap: 5px;">
                            Exp ID: <span id="expIdValue"></span>
                            <span class="copy-wrapper"
                                style="position: relative; display: flex; align-items: center; gap: 5px;">
                                <i class="ri-file-copy-line copy-icon" style="cursor: pointer;"></i>
                                <span class="copied-text"
                                    style="display:none; color: green; font-size: 0.85rem;">Copied!</span>
                            </span>
                            <span class="reload-wrapper"
                                style="position: relative; display: flex; align-items: center; gap: 5px;">
                                <i class="ri-refresh-line reload-icon" style="cursor: pointer;"></i>
                                <span class="reloaded-text"
                                    style="display:none; color: green; font-size: 0.85rem;">Reloaded!</span>
                            </span>
                        </span>
                        <select name="" style="width: 260px;" class="form-control"
                            id="sltClaimTypeList"></select>
                        {{-- DOCVIEW-CLOSE-01 (14 Nov 2026): a way back at the top; before, the only Close was at the bottom
                             of the full-screen viewer, so people used the browser's back button and left the page --}}
                        <button type="button" class="btn btn-sm btn-soft-secondary d-flex align-items-center" data-bs-dismiss="modal"
                            title="Close and go back (Esc)" aria-label="Close the document viewer">
                            <i class="ri-arrow-left-line me-1" aria-hidden="true"></i>Back
                        </button>
                    </div>
                </div>
                <div class="modal-body p-0">
                    <div class="row g-0">
                        <div class="col-md-5 viewer-section p-2">
                            <div class="file-info small d-flex justify-content-between flex-wrap">
                                <span><strong>File:</strong> <span id="fileName">Loading...</span></span>
                                <span><strong>Type:</strong> <span id="fileType">-</span></span>
                                <span><strong>#</strong> <span id="fileIndex">1/2</span></span>
                            </div>

                            <div class="mb-1 mt-1">
                                <small class="d-flex align-items-center justify-content-between">
                                    <label class="form-label small fw-bold mb-0">Documents:</label>
                                    @can('upload_image_option')
                                        <span id="claimImageUploadWrapper" style="display:none;">
                                            <label for="claimImageInput" class="btn btn-sm btn-outline-primary py-0 px-2"
                                                style="cursor:pointer;" title="Upload Image">
                                                <i class="ri-upload-2-line"></i> Upload
                                            </label>
                                            <input type="file" id="claimImageInput" accept="image/*,application/pdf"
                                                style="display:none;">
                                        </span>
                                    @endcan
                                </small>
                                <div class="thumbnail-list" id="thumbnailList">
                                    <div class="loading-spinner">
                                        <div class="spinner-border spinner-border-sm" role="status">
                                            <span class="visually-hidden">Loading...</span>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div class="main-viewer" id="mainViewer">
                                <div class="loading-spinner">
                                    <div class="spinner-border" role="status">
                                        <span class="visually-hidden">Loading...</span>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-7 form-section p-3">
                            <form id="dataEntryForm">
                            </form>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-sm btn-primary" id="prevBtn">
                        <i class="ri-arrow-left-line me-1"></i>Previous
                    </button>
                    <button type="button" class="btn btn-sm btn-primary" id="nextBtn">
                        Next<i class="ri-arrow-right-line ms-1"></i>
                    </button>
                    @php
                        $isClaimReport = request()->is('claim-report');
                        $isverifyApproved = request()->is('verify-approved');
                        $isUploadedPage = request()->is('data-punch/uploaded*');
                        $isFilledPage = request()->is('data-punch/filled*');
                        $isHoldPage = request()->is('data-punch/hold*');
                        $isDraftpage = request()->is('data-punch/draft*');
                        $isDeniedPage = request()->is('data-punch/denied*');
                        $isDeactivatedPage = request()->is('data-punch/deactivated*');
                        $isDataPunchPage = $isUploadedPage || $isFilledPage || $isHoldPage || $isDraftpage || $isDeniedPage || $isDeactivatedPage;
                        $currentUser = auth()->user();
                        $isAdminUser = $currentUser && (
                            $currentUser->id === 1 ||
                            session('user_type') === 'Admin' ||
                            (method_exists($currentUser, 'hasAnyRole') && $currentUser->hasAnyRole(['Super Admin', 'super_admin', 'Admin', 'admin'])) ||
                            (method_exists($currentUser, 'can') && ($currentUser->can('section_admin') || $currentUser->can('super_admin') || $currentUser->can('admin')))
                        );
                        // Dashboard = view-only mode: no action buttons shown
                        $isDashboard = request()->is('employee-report') || request()->is('dashboard') || request()->routeIs('employee.dashboard') || request()->routeIs('dashboard');
                        // APPR-RO-01 (13 Nov 2026): the approver screens show the documents and the punched data view-only
                        $isApproverPage = request()->is('approver', 'approver/*', 'vnr/approver*', 'vvnr/approver*', 'nursery/approver*', 'approver-dashboard*');
                    @endphp
                    @if (!$isDashboard && !$isApproverPage)
                        @if ($isDeactivatedPage && $isAdminUser)
                            <button type="button" class="btn btn-sm btn-success" id="modalRevertDeactivatedBtn"
                                title="Revert this deactivated claim back to Uploaded">
                                <i class="ri-arrow-go-back-line me-1"></i>Revert to Uploaded
                            </button>
                        @endif
                        @if ($isDeniedPage)
                            <button type="button" class="btn btn-sm btn-info" id="revertUploadedBtn">
                                <i class="ri-arrow-go-back-line me-1"></i>Revert to Uploaded
                            </button>
                        @endif
                        @if ($isFilledPage)
                            <button type="button" class="btn btn-sm btn-danger" id="denyBtn">
                                <i class="ri-close-circle-line me-1"></i>Deny
                            </button>
                            <button type="button" class="btn btn-sm btn-warning" id="holdBtn">
                                <i class="ri-pause-line me-1"></i>Hold
                            </button>
                            <button type="button" class="btn btn-sm btn-info" id="draftBtn">
                                <i class="ri-draft-line me-1"></i>Draft
                            </button>
                        @endif
                        @if ($isUploadedPage || $isHoldPage || $isDraftpage)
                            <button type="button" class="btn btn-sm btn-danger" id="denyBtn">
                                <i class="ri-close-circle-line me-1"></i>Deny
                            </button>
                            @if ($isUploadedPage)
                                <button type="button" class="btn btn-sm btn-info" id="draftBtn">
                                    <i class="ri-draft-line me-1"></i>Draft
                                </button>
                            @endif
                            <button type="button" class="btn btn-sm btn-warning" id="holdBtn">
                                <i class="ri-pause-line me-1"></i>Hold
                            </button>
                            <button type="button" class="btn btn-sm btn-secondary" id="filledBtn">
                                <i class="ri-checkbox-circle-line me-1"></i>Filled
                            </button>
                        @endif
                        @if (!$isverifyApproved)
                            <button type="button" class="btn btn-sm btn-success" id="saveBtn"
                                data-is-filled="{{ $isFilledPage ? 1 : 0 }}">
                                <i class="ri-save-line me-1"></i>Save
                            </button>
                        @endif
                        @if ($isDataPunchPage && $isAdminUser)
                            <button type="button" class="btn btn-sm btn-danger" id="modalDeactivateClaimBtn">
                                <i class="ri-forbid-line me-1"></i>Deactivate
                            </button>
                        @endif
                    @endif
                    @if ($isDashboard && \Illuminate\Support\Facades\Route::has('claimant.claim.verify'))
                        {{-- DASH-CONFIRM-01 (14 Nov 2026): on the Dashboard the employee confirms or denies the punched figures of
                             their own bill here (shown only for their own bills waiting for confirmation) --}}
                        <button type="button" class="btn btn-sm btn-success d-none" id="dashConfirmBtn" data-url="{{ route('claimant.claim.verify') }}">
                            <i class="ri-check-line me-1"></i>OK &amp; Verify
                        </button>
                        <button type="button" class="btn btn-sm btn-danger d-none" id="dashDenyBtn" data-url="{{ route('claimant.claim.deny') }}">
                            <i class="ri-close-circle-line me-1"></i>Deny
                        </button>
                    @endif
                    <button type="button" class="btn btn-sm btn-secondary" data-bs-dismiss="modal" id="cancelBtn">
                        <i class="ri-close-line me-1"></i>Close
                    </button>
                </div>
            </div>
        </div>
    </div>

    <div class="modal fade" id="changeHeadModal" data-bs-backdrop="static" tabindex="-1"
        aria-labelledby="changeHeadModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg modal-dialog-scrollable">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="changeHeadModalLabel">
                        <i class="ri-exchange-line me-2"></i>Change Claim Head
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="alert alert-warning small mb-3" id="changeHeadWarning"></div>
                    <div id="changeHeadFileMapping"></div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-sm btn-secondary" id="changeHeadCancelBtn"
                        data-bs-dismiss="modal">
                        <i class="ri-close-line me-1"></i>Cancel
                    </button>
                    <button type="button" class="btn btn-sm btn-primary" id="changeHeadConfirmBtn">
                        <i class="ri-check-line me-1"></i>Confirm & Move Files
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Modal for switching image / amount between expense heads within the same activity -->
    <div class="modal fade" id="switchActivityHeadModal" data-bs-backdrop="static" tabindex="-1"
        aria-labelledby="switchActivityHeadModalLabel" aria-hidden="true" style="z-index: 1060;">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header py-2 bg-light">
                    <h6 class="modal-title fw-bold" id="switchActivityHeadModalLabel">
                        <i class="ri-exchange-funds-line me-1 text-primary"></i>Switch Image & Details to Another Head
                    </h6>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label small text-muted mb-1">File / Document:</label>
                        <div class="d-flex align-items-center p-2 rounded bg-light border">
                            <i class="ri-file-image-line me-2 fs-18 text-primary"></i>
                            <span id="switchHeadFileName" class="fw-semibold text-truncate small" style="max-width: 380px;"></span>
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label small text-muted mb-1">Current Expense Head:</label>
                        <input type="text" id="switchHeadCurrentName" class="form-control form-control-sm bg-light" readonly>
                    </div>
                    <div class="mb-3">
                        <label class="form-label small fw-bold text-dark mb-1">Move To Expense Head <span class="text-danger">*</span>:</label>
                        <select id="switchHeadTargetSelect" class="form-select form-select-sm">
                            <option value="">-- Select Target Expense Head --</option>
                        </select>
                    </div>
                    <div class="p-2 border rounded bg-light-subtle">
                        <div class="form-check form-switch mb-1">
                            <input class="form-check-input" type="checkbox" id="switchHeadMoveAmountCheck" checked>
                            <label class="form-check-label small fw-bold" for="switchHeadMoveAmountCheck">
                                Also move Amount & Remarks to target head
                            </label>
                        </div>
                        <div id="switchHeadAmountPreview" class="small text-muted ps-4">
                            <span>Claim Amount: <strong id="switchHeadClaimAmtPreview">₹0</strong></span>
                        </div>
                    </div>
                </div>
                <div class="modal-footer py-2">
                    <button type="button" class="btn btn-sm btn-secondary" data-bs-dismiss="modal">
                        <i class="ri-close-line me-1"></i>Cancel
                    </button>
                    <button type="button" class="btn btn-sm btn-primary" id="switchHeadConfirmBtn">
                        <i class="ri-check-line me-1"></i>Confirm & Move
                    </button>
                </div>
            </div>
        </div>
    </div>

{{-- DOCVIEW-CLOSE-01: Esc closes the viewer on view-only screens (the browser's back button: ux.js MODAL-BACK-01) --}}
@once
@php
    $docViewOnly = request()->is('employee-report', 'dashboard', 'approver', 'approver/*', 'vnr/approver*', 'vvnr/approver*', 'nursery/approver*', 'approver-dashboard*')
        || request()->routeIs('dashboard', 'employee.dashboard');
@endphp
<script>
(function () {
    function init() {
        var el = document.getElementById('claimDetailModal');
        if (!el || el.dataset.backWired) { return; }
        el.dataset.backWired = '1';
        // the browser's back button: see MODAL-BACK-01 in custom/js/ux.js (all pop-ups)
        // DASH-CONFIRM-01: own bill waiting for the employee's confirmation (status Filled, not yet confirmed or denied)
        var pick = null;
        $(document).on('click', '#myEhomeBody .view-claim, #myClaimsMonthContainer .view-claim', function () {
            var $a = $(this);
            // odometer bills are agreed month-wise from Running KM, not here
            if (String($a.data('claim-id') || '') === '7') { pick = null; return; }
            pick = { exp: String($a.data('expid') || ''), emp: String($a.data('empid') || ''),
                status: String($a.data('status') || ''), fo: String($a.data('filled-okay') ?? '') };
        });
        el.addEventListener('shown.bs.modal', function () {
            var me = String(el.getAttribute('data-viewer-empid') || ''), $c = $('#dashConfirmBtn'), $d = $('#dashDenyBtn');
            var own = pick && pick.emp === me && pick.status === 'Filled' && pick.fo !== '1' && pick.fo !== '2';
            $c.toggleClass('d-none', !own).data('exp', own ? pick.exp : '');
            $d.toggleClass('d-none', !own).data('exp', own ? pick.exp : '');
        });
        el.addEventListener('hidden.bs.modal', function () { pick = null; $('#dashConfirmBtn, #dashDenyBtn').addClass('d-none'); });
        function dashPost(url, data, done) {
            data._token = $('meta[name="csrf-token"]').attr('content');
            return $.post(url, data).done(function (r) {
                if (String(r && r.Code) === '300' || (r && r.success)) { done(); }
                else { Swal.fire('Not done', (r && (r.msg || r.message)) || 'Please try again.', 'warning'); }
            }).fail(function (x) { Swal.fire('Not done', (x.responseJSON && (x.responseJSON.msg || x.responseJSON.message)) || 'Please try again.', 'error'); });
        }
        function dashRefresh() {
            bootstrap.Modal.getOrCreateInstance(el).hide();
            if (window.loadMyEhome) { window.loadMyEhome(); }
            $('#refreshMyClaims').trigger('click');
        }
        $(document).on('click', '#dashConfirmBtn', function () {
            var exp = $(this).data('exp'), url = $(this).data('url');
            if (!exp) { return; }
            Swal.fire({ title: 'Confirm this bill?', text: 'The punched figures are correct.', icon: 'question', showCancelButton: true, confirmButtonText: 'OK & Verify' })
                .then(function (r) { if (r.isConfirmed) { dashPost(url, { exp_id: exp }, function () { Swal.fire({ icon: 'success', title: 'Confirmed', timer: 1200, showConfirmButton: false }); dashRefresh(); }); } });
        });
        $(document).on('click', '#dashDenyBtn', function () {
            var exp = $(this).data('exp'), url = $(this).data('url');
            if (!exp) { return; }
            Swal.fire({ title: 'Deny this bill?', text: 'It goes back to the punching team with your remark.', input: 'text', inputPlaceholder: 'What is wrong?',
                inputAttributes: { maxlength: 50 }, showCancelButton: true, confirmButtonText: 'Deny', confirmButtonColor: '#dc2626',
                inputValidator: function (v) { return !v || !v.trim() ? 'A remark is needed' : null; } })
                .then(function (r) { if (r.isConfirmed) { dashPost(url, { exp_id: exp, remark: r.value.trim() }, function () { Swal.fire({ icon: 'success', title: 'Sent back to the punching team', timer: 1400, showConfirmButton: false }); dashRefresh(); }); } });
        });

        // Esc only where the viewer is view-only (Dashboard, approver screens), so typed punching data is not lost
        var viewOnly = {{ $docViewOnly ? 'true' : 'false' }};
        document.addEventListener('keydown', function (ev) {
            if (!viewOnly || ev.key !== 'Escape' || !el.classList.contains('show')) { return; }
            // a dialog opened over the viewer (SweetAlert, another modal) handles its own Esc
            if (document.querySelector('.swal2-container') || document.querySelectorAll('.modal.show').length > 1) { return; }
            if (window.bootstrap) { bootstrap.Modal.getOrCreateInstance(el).hide(); }
        });
    }
    if (document.readyState === 'loading') { document.addEventListener('DOMContentLoaded', init); } else { init(); }
})();
</script>
@endonce
