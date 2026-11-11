{{-- resources/views/reports/partials/ledger-modals.blade.php --}}

<!-- Employee Details Modal -->
<div class="modal fade" id="employeeDetailsModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-scrollable">
        <div class="modal-content shadow-lg border-0">
            <div class="modal-header py-2" style="background-color: #15648C; color: white;">
                <h6 class="modal-title text-white fw-bold d-flex align-items-center">
                    <i class="fa fa-user-circle me-2"></i> Employee Financial Details &mdash; <span id="empModalHeaderName" class="ms-1 fw-normal"></span>
                </h6>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body p-3">
                <!-- Content loaded via AJAX -->
            </div>
            <div class="modal-footer py-2 bg-light d-flex justify-content-between align-items-center">
                <div class="d-flex gap-2">
                    <button type="button" class="btn btn-success btn-sm shadow-sm" onclick="exportModalActiveTabExcel()">
                        <i class="fa fa-file-excel-o me-1"></i> <span id="empModalFooterExportText">Export Advances Log (Excel)</span>
                    </button>
                    <button type="button" class="btn btn-outline-success btn-sm shadow-sm" onclick="exportModalBothTabsExcel()">
                        <i class="fa fa-download me-1"></i> Export Both Tabs (Excel)
                    </button>
                </div>
                <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>

<!-- Balance Adjustment Modal -->
<div class="modal fade" id="balanceAdjustmentModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header" style="background-color: #15648C; color: white;">
                <h5 class="modal-title text-white">Adjust Ledger Balance</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form id="balanceAdjustmentForm" method="POST">
                @csrf
                <div class="modal-body">
                    <input type="hidden" name="employee_id" id="adjustment_employee_id">

                    <div class="mb-3">
                        <label class="form-label">Current Balance</label>
                        <input type="text" class="form-control" id="current_balance" readonly>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Balance Type</label>
                        <select name="balance_type" class="form-control" required>
                            <option value="debit">Debit (Add to balance)</option>
                            <option value="credit">Credit (Subtract from balance)</option>
                        </select>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Amount</label>
                        <input type="number" name="amount" class="form-control" step="0.01" min="0.01" required>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Remarks</label>
                        <textarea name="remarks" class="form-control" rows="3" required></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary btn-sm">Update Balance</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- As-of-Date Snapshot Modal -->
<div class="modal fade" id="snapshotModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-xl">
        <div class="modal-content">
            <div class="modal-header" style="background-color: #15648C; color: white; padding: 10px 15px;">
                <h5 class="modal-title text-white">
                    <i class="fa fa-calendar"></i>
                    Employee Balance &mdash; <span id="snapshotModalDateLabel"></span>
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <!-- Summary bar -->
                <div class="snapshot-summary-bar" id="snapshotSummaryBar" style="display:none;">
                    <div class="snap-stat">Total Employees: <span id="snapTotalEmp">0</span></div>
                    <div class="snap-stat">Total Imprest: <span id="snapTotalImprest">₹0</span></div>
                    <div class="snap-stat">Total Advance: <span id="snapTotalAdv">₹0</span></div>
                    <div class="snap-stat">Total Balance: <span id="snapTotalBal">₹0</span></div>
                </div>

                <!-- Pagination info -->
                <div id="snapshotPaginationInfo" class="mb-2" style="font-size:12px; color:#666;"></div>

                <!-- Table -->
                <div style="overflow-x:auto;">
                    <table class="table table-bordered snapshot-table" id="snapshotTable">
                        <thead>
                            <tr>
                                <th style="width:40px;">S.No</th>
                                <th>Employee Name</th>
                                <th>Emp Code</th>
                                <th>Status</th>
                                <th>Imprest</th>
                                <th>Advance Balance</th>
                                <th>Total Balance</th>
                                <th>Advance Details</th>
                            </tr>
                        </thead>
                        <tbody id="snapshotTableBody">
                            <tr><td colspan="8" class="text-center text-muted">Select a date and click "View Balance" to load.</td></tr>
                        </tbody>
                    </table>
                </div>

                <!-- Pagination controls -->
                <div id="snapshotPagination" class="d-flex justify-content-center gap-2 mt-2"></div>
            </div>
            <div class="modal-footer">
                <small class="text-muted me-auto" id="snapshotFooterNote"></small>
                <button type="button" class="btn btn-success btn-sm" onclick="exportSnapshot()">
                    <i class="fa fa-download"></i> Export Excel
                </button>
                <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>
