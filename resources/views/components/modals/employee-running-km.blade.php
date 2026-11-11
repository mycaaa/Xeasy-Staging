{{--
    Employee Running KM Modal Component
    ─────────────────────────────────────
    Include this once on any page that needs it:

        @include('components.modals.employee-running-km')

    Then trigger it from anywhere with JS:

        openEmpKmModal(employeeId, employeeName)

    Or use the data-attribute trigger on any element:

        <a href="#" data-emp-km-id="123" data-emp-km-name="John Doe">View KM</a>
--}}

<div class="modal fade" id="empKmModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header" style="background:#15648C;color:#fff;padding:10px 16px;">
                <h5 class="modal-title mb-0 text-white" id="empKmModalTitle">
    <i class="ri-car-line me-1"></i> Running KM Detail
</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body p-0" style="min-height:400px;">
                <iframe id="empKmModalFrame"
                        src="about:blank"
                        style="width:100%;height:580px;border:none;display:block;"
                        onload="this.style.height=Math.max(400,(this.contentWindow.document.body?this.contentWindow.document.body.scrollHeight+20:400))+'px'">
                </iframe>
            </div>
            <div class="modal-footer py-2">
                <a id="empKmModalOpenLink" href="#" target="_blank" class="btn btn-outline-secondary btn-sm">
                    <i class="ri-external-link-line me-1"></i> Open in New Tab
                </a>
                <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>

<script>
(function () {
    // Guard: only register once even if component is included multiple times
    if (window._empKmModalReady) return;
    window._empKmModalReady = true;

    var BASE_URL = '{{ url("employee-running-km-view") }}';

    /**
     * Open the Running KM modal for a specific employee.
     *
     * @param {number|string} employeeId
     * @param {string}        employeeName  (optional — used for the modal title)
     */
    window.openEmpKmModal = function (employeeId, employeeName) {
        if (!employeeId) return;

        var url = BASE_URL + '/' + employeeId;

        document.getElementById('empKmModalTitle').innerHTML =
            '<i class="ri-car-line me-1"></i> ' +
            (employeeName ? employeeName : 'Running KM Detail');

        document.getElementById('empKmModalFrame').src = url;
        document.getElementById('empKmModalOpenLink').href = url;

        var modal = bootstrap.Modal.getOrCreateInstance(document.getElementById('empKmModal'));
        modal.show();
    };

    // Reset iframe when modal closes
    document.getElementById('empKmModal').addEventListener('hidden.bs.modal', function () {
        document.getElementById('empKmModalFrame').src = 'about:blank';
    });

    // Auto-wire any element with data-emp-km-id attribute
    document.addEventListener('click', function (e) {
        var el = e.target.closest('[data-emp-km-id]');
        if (!el) return;
        e.preventDefault();
        window.openEmpKmModal(el.dataset.empKmId, el.dataset.empKmName || '');
    });
}());
</script>
