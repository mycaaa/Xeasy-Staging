{{-- resources/views/finance/partials/unsettled-details-modal.blade.php --}}
{{-- Unsettled Payment Details Modal --}}
<div class="modal fade" id="unsettledDetailsModal" tabindex="-1">
    <div class="modal-dialog modal-xl">
        <div class="modal-content">
            <div class="modal-header" style="background-color: #fde2e2; color: #9b1c1c;">
                <h5 class="modal-title">
                    <i class="fa fa-exclamation-triangle"></i> Payment Mismatch Details
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body" style="max-height: 80vh; overflow-y: auto;">
                <div id="unsettledModalMessage" class="alert" style="display: none; margin-bottom: 15px;"></div>
                
                <div class="row">
                    <!-- Left Column - Discrepancy Details -->
                    <div class="col-md-7">
                        <div class="row">
                            <div class="col-md-12">
                                <h6 class="text-primary mb-3" style="font-size: 0.85rem;"><i class="fa fa-file-text-o"></i> Transaction Information</h6>
                                <table class="table table-sm" style="font-size: 0.75rem;" id="unsettledTransactionTable">
                                    <tr><td><strong>Transaction Ref:</strong></td><td id="displayTransactionRef">-</td></tr>
                                    <tr><td><strong>Beneficiary:</strong></td><td id="displayBeneficiary">-</td></tr>
                                    <tr><td><strong>Account No:</strong></td><td id="displayAccountNo">-</td></tr>
                                    <tr><td><strong>Status:</strong></td><td><span class="status-badge" id="displayStatus">-</span></td></tr>
                                </table>
                            </div>
                        </div>
                        
                        <div class="row mt-2">
                            <div class="col-12">
                                <h6 class="text-primary mb-2" style="font-size: 0.85rem;"><i class="fa fa-comment"></i> Reason for Discrepancy</h6>
                                <div class="alert alert-info" style="font-size: 0.7rem; padding: 8px;" id="displayReason">
                                    -
                                </div>
                            </div>
                        </div>
                        
                        <!-- Manual Payment Section -->
                        <div class="row mt-2">
                            <div class="col-12">
                                <div class="card" style="border: 1px solid #ffc107;">
                                    <div class="card-header" style="background-color: #fff3cd; padding: 8px 12px;">
                                        <h6 class="mb-0" style="font-size: 0.8rem;"><i class="fa fa-pencil-square-o"></i> Manual Payment</h6>
                                    </div>
                                    <div class="card-body" style="padding: 10px;">
                                        <div class="row">
                                            <div class="col-md-6 mb-2">
                                                <label class="form-label" style="font-size: 0.75rem; margin-bottom: 2px;">
                                                    Transaction Reference <span class="text-danger">*</span>
                                                </label>
                                                <input type="text" class="form-control form-control-sm" id="manualTransactionRef" 
                                                       placeholder="e.g., BT26013049926940" aria-label="e.g., BT26013049926940" style="font-size: 0.75rem;" required>
                                            </div>
                                            <div class="col-md-6 mb-2">
                                                <label class="form-label" style="font-size: 0.75rem; margin-bottom: 2px;">
                                                    Payment Amount (₹) <span class="text-danger">*</span>
                                                </label>
                                                <input type="number" class="form-control form-control-sm" id="manualPaymentAmount" 
                                                       placeholder="e.g., 2000.00" aria-label="e.g., 2000.00" step="0.01" min="0" style="font-size: 0.75rem;" required>
                                            </div>
                                        </div>
                                        <div class="row">
                                            <div class="col-md-6 mb-2">
                                                <label class="form-label" style="font-size: 0.75rem; margin-bottom: 2px;">
                                                    Payment Date <span class="text-danger">*</span>
                                                </label>
                                                <input type="date" class="form-control form-control-sm" id="manualPaymentDate" 
                                                       style="font-size: 0.75rem;" required>
                                            </div>
                                            <div class="col-md-6 mb-2">
                                                <label class="form-label" style="font-size: 0.75rem; margin-bottom: 2px;">
                                                    UTR Reference <span class="text-danger">*</span>
                                                </label>
                                                <input type="text" class="form-control form-control-sm" id="manualUtrRef" 
                                                       placeholder="e.g., BARBD26028317633" aria-label="e.g., BARBD26028317633" style="font-size: 0.75rem;" required>
                                            </div>
                                        </div>
                                        <div class="row">
                                            <div class="col-12 mb-2">
                                                <label class="form-label" style="font-size: 0.75rem; margin-bottom: 2px;">
                                                    Payment Remark <span class="text-danger">*</span>
                                                </label>
                                                <input type="text" class="form-control form-control-sm" id="manualPaymentRemark" 
                                                       placeholder="e.g., Manual adjustment" aria-label="e.g., Manual adjustment" style="font-size: 0.75rem;" required>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Right Column - Payment Records List -->
                    <div class="col-md-5">
                        <div class="card" style="border: 1px solid #dee2e6; height: 100%;">
                            <div class="card-header" style="background-color: #f8f9fa; padding: 8px 12px;">
                                <h6 class="mb-0" style="font-size: 0.8rem;">
                                    <i class="fa fa-list-ul"></i> Available Payment Records 
                                    <span class="badge bg-primary" id="paymentRecordsCount" style="font-size: 0.65rem;">0</span>
                                </h6>
                            </div>
                            <div class="card-body" style="padding: 8px; max-height: 500px; overflow-y: auto;" id="paymentRecordsContainer">
                                <div class="text-muted text-center py-3" style="font-size: 0.7rem;">
                                    <i class="fa fa-info-circle"></i> No payment records found
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Close</button>
                <button type="button" class="btn btn-warning btn-sm" id="resolveDiscrepancyBtn">
                    <i class="fa fa-check"></i> Mark as Resolved
                </button>
            </div>
        </div>
    </div>
</div>

<input type="hidden" id="currentUnsettledId" value="">
<input type="hidden" id="currentActivityId" value="">