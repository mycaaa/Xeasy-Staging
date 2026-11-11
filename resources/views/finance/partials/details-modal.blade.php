{{-- resources/views/finance/partials/details-modal.blade.php --}}
{{-- Generic Details Modal --}}
<div class="modal fade" id="detailModal" tabindex="-1">
    <div class="modal-dialog modal-xl">
        <div class="modal-content">
            <div class="modal-header" style="background-color: #155997; color: white;">
                <h5 class="modal-title">
                    <i class="fa fa-file-text-o"></i> Request Details
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body" style="max-height: 85vh; overflow-y: auto;" id="detailsModalBody">
                <div class="text-center py-5">
                    <i class="fa fa-spinner fa-spin fa-2x"></i>
                    <p class="mt-2">Loading details...</p>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>