{{-- resources/views/finance/partials/summary-cards.blade.php --}}
{{-- Summary Cards for Unsettled Payments --}}
<div class="row mb-3" id="summaryCards" style="display: none;">
    <div class="col-md-3">
        <div class="card" style="border: 1px solid #dee2e6;">
            <div class="card-body" style="padding: 10px;">
                <div style="font-size: 12px; color: #6c757d;">Total Unsettled</div>
                <div style="font-size: 24px; font-weight: 700;" id="totalUnsettledCount">0</div>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card" style="border: 1px solid #dee2e6;">
            <div class="card-body" style="padding: 10px;">
                <div style="font-size: 12px; color: #6c757d;">Excel Amount</div>
                <div style="font-size: 24px; font-weight: 700; color: #17a2b8;" id="totalExcelAmount">₹ 0</div>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card" style="border: 1px solid #dee2e6;">
            <div class="card-body" style="padding: 10px;">
                <div style="font-size: 12px; color: #6c757d;">DB Amount</div>
                <div style="font-size: 24px; font-weight: 700; color: #28a745;" id="totalDbAmount">₹ 0</div>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card" style="border: 1px solid #dee2e6;">
            <div class="card-body" style="padding: 10px;">
                <div style="font-size: 12px; color: #6c757d;">Difference</div>
                <div style="font-size: 24px; font-weight: 700;" id="totalDifference">₹ 0</div>
            </div>
        </div>
    </div>
</div>