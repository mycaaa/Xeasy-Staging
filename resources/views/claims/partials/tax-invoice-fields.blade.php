{{-- GST-02 (Finance 7 Nov 2026): GST amount and invoice number of the bill --}}
<div class="row g-2 mt-2 border-top pt-2" id="tax-invoice-fields">
    @if ($showInvoice)
        <div class="col-md-6">
            <label class="form-label mb-0 small" for="invoice_no_tax">Invoice / bill number</label>
            <input type="text" class="form-control form-control-sm" id="invoice_no_tax" name="invoice_no" maxlength="30" value="{{ $invoiceNo }}">
        </div>
    @endif
    <div class="col-md-6">
        <label class="form-label mb-0 small" for="gst_amount">GST amount on the bill (₹)</label>
        <input type="number" step="0.01" min="0" class="form-control form-control-sm" id="gst_amount" name="gst_amount" value="{{ $gstAmount !== null ? (float) $gstAmount : '' }}" placeholder="0 if the bill has no GST">
    </div>
</div>
