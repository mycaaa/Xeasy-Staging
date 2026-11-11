{{-- resources/views/finance/partials/import-modal.blade.php --}}
{{-- Import Payment Confirmation Modal --}}
<div class="modal fade" id="importConfirmationModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header" style="background-color: #155997; color: white;">
                <h5 class="modal-title">
                    <i class="fa fa-upload"></i> Import Payment Confirmation
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div id="importMessage" class="alert" style="display: none;"></div>
                
                <div class="mb-3">
                    <label for="paymentFile" class="form-label">Select Payment Confirmation File</label>
                    <input type="file" class="form-control" id="paymentFile" accept=".xlsx,.xls,.csv" required>
                    <div class="form-text">
                        <i class="fa fa-info-circle"></i> 
                        Supported formats: Excel (.xlsx, .xls) or CSV (.csv)
                    </div>
                </div>
                
                <div class="mb-3">
                    <h6>Expected File Format:</h6>
                    <div class="table-responsive">
                        <table class="table table-sm table-bordered">
                            <thead class="table-light">
                                <tr style="font-size: 0.75rem;">
                                    <th>Transaction Ref</th>
                                    <th>Beneficiary Name</th>
                                    <th>Amount</th>
                                    <th>Bank Name</th>
                                    <th>Status</th>
                                    <th>UTR Reference</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr style="font-size: 0.7rem; color: #666;">
                                    <td>BT26012849794029</td>
                                    <td>John Doe</td>
                                    <td>2383.00</td>
                                    <td>SBIN0002637</td>
                                    <td>Accepted By Bank</td>
                                    <td>BARBD26028317592</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-primary btn-sm" onclick="importPaymentConfirmation()">
                    <i class="fa fa-upload"></i> Import
                </button>
            </div>
        </div>
    </div>
</div>