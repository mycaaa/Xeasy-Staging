{{-- resources/views/finance/partials/finance-approval-modal.blade.php --}}
{{-- Finance Approval Modal --}}
<div class="modal fade" id="financeApprovalModal" tabindex="-1">
    <div class="modal-dialog modal-xl">
        <div class="modal-content">
            <div class="modal-header" id="financeApprovalModalHeader" style="color: white;">
                <h5 class="modal-title">
                    <i class="fa" id="financeApprovalModalIcon"></i> <span id="financeApprovalModalTitle"></span>
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body" style="max-height: 85vh; overflow-y: auto;">
                <div id="financeModalMessage" class="alert" style="display: none; margin-bottom: 15px;"></div>
                
                <div class="row">
                    <div class="col-8" id="financeApprovalLeftColumn">
                        <!-- Request Information -->
                        <div class="mb-3">
                            <h6 style="color: #155997; margin-bottom: 10px;">
                                <i class="fa fa-file-text-o"></i> Request Information
                            </h6>
                            <div class="row mb-2" id="financeRequestInfo"></div>
                        </div>
                        
                        <!-- Activity Details -->
                        <div class="mb-3">
                            <h6 style="color: #155997; margin-bottom: 10px;">
                                <i class="fa fa-list-ul"></i> Activity Details
                            </h6>
                            <div style="background-color: #f8f9fa; padding: 8px; border-radius: 4px;" id="financeActivityDetails"></div>
                        </div>
                        
                        <!-- Account Summary -->
                        <div class="mb-3">
                            <h6 style="color: #155997; margin-bottom: 10px;">
                                <i class="fa fa-file-text-o"></i> Account Summary
                            </h6>
                            <iframe id="accountSummaryFrame"
                                width="100%"
                                height="420"
                                style="border:1px solid #dee2e6;border-radius:4px;"
                                frameborder="0"
                                scrolling="yes">
                            </iframe>
                        </div>
                    </div>
                    
                    <div class="col-4">
                        <!-- Approval History -->
                        <div class="card mb-3" style="border: 1px solid #dee2e6;" id="approvalHistoryCard">
                            <div class="card-header" style="background-color: #f8f9fa; font-weight: 600; padding: 6px 10px; font-size: 0.75rem;">
                                <i class="fa fa-list-check"></i> Approval History
                            </div>
                            <div class="card-body" style="padding: 6px;" id="approvalHistoryBody">
                                <div class="text-muted text-center py-2">Loading...</div>
                            </div>
                        </div>
                        
                        <!-- Finance Action -->
                        <div class="card" style="border: 1px solid #dee2e6;">
                            <div class="card-header" style="background-color: #f8f9fa; font-weight: 600;">
                                <i class="fa fa-credit-card"></i> Finance Action
                            </div>
                            <div class="card-body">
                                <div class="mb-3" id="financeAmountField"></div>
                                
                                <div class="mb-3">
                                    <label class="form-label">Finance Remarks <span id="financeRemarkRequired"></span></label>
                                    <textarea class="form-control form-control-sm" id="financeApprovalRemark" 
                                              rows="1" placeholder="Enter finance remarks..." aria-label="Enter finance remarks"></textarea>
                                </div>
                                
                                <div class="d-grid gap-2">
                                    <button type="button" class="btn" id="financeActionBtn">
                                        <i class="fa" id="financeActionIcon"></i> <span id="financeActionText"></span>
                                    </button>
                                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                                        <i class="fa fa-times"></i> Cancel
                                    </button>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Recent Advance History -->
                        <div class="card mt-3" style="border: 1px solid #dee2e6;">
                            <div class="card-header" style="background-color: #f8f9fa; font-weight: 600;">
                                <i class="fa fa-history"></i> Recent Advance History
                            </div>
                            <div class="card-body" style="padding: 8px; max-height: 200px; overflow-y: auto;" id="recentAdvanceHistory">
                                <div class="text-muted text-center py-2">Loading...</div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<input type="hidden" id="currentRequestId" value="">
<input type="hidden" id="currentAction" value="">