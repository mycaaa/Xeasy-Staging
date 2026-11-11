@extends('layouts.app')
@section('content')
<div class="page-content">
   <div class="container-fluid">
      <div class="row mb-3">
         <div class="col-12">
            <div class="d-flex justify-content-end">
            </div>
         </div>
      </div>
      <div class="row">
         <div class="col-12">
            <div class="card">
               <div class="card-header align-items-center d-flex">
                  <h4 class="card-title mb-0 flex-grow-1">All Payments</h4>
                  <div class="flex-shrink-0">
                     <a href="{{ route('payments.import') }}" >                                                        <button type="button" class="btn btn-primary btn-label waves-effect waves-light rounded-pill" >
                     <i class="ri-add-circle-fill label-icon align-middle rounded-pill fs-16 me-2"></i> Import Payments
                     </button>
                     </a>
                  </div>
               </div>
               <div class="card-body">
                  <div class="table-responsive">
                     <table id="paymentsTable" class="table table-striped table-bordered dt-responsive nowrap" style="width:100%">
                        <thead>
                           <tr>
                              <th>Record No</th>
                              <th>Transaction Ref</th>
                              <th>Beneficiary Name</th>
                              <th>Bank Name</th>
                              <th>Value Date</th>
                              <th>Amount</th>
                              <th>Payment Type</th>
                              <th>Transaction Status</th>
                              <th>Import Date</th>
                              <th>Action</th>
                           </tr>
                        </thead>
                        <tbody>
                        </tbody>
                     </table>
                  </div>
               </div>
            </div>
         </div>
      </div>
   </div>
</div>

<!-- Payment Details Modal -->
<div class="modal fade" id="paymentDetailsModal" tabindex="-1" aria-labelledby="paymentDetailsModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-xl">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="paymentDetailsModalLabel">
                    <i class="mdi mdi-file-document-outline me-2"></i>Payment Details
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body" id="paymentDetailsContent">
                <div class="text-center py-4">
                    <div class="spinner-border text-primary" role="status">
                        <span class="visually-hidden">Loading...</span>
                    </div>
                    <p class="mt-2 text-muted">Loading payment details...</p>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection

@push('styles')
<link href="{{ asset('assets/libs/datatables.net-bs5/css/dataTables.bootstrap5.min.css') }}" rel="stylesheet" type="text/css" />
<link href="{{ asset('assets/libs/datatables.net-responsive-bs5/css/responsive.bootstrap5.min.css') }}" rel="stylesheet" type="text/css" />
@endpush

@push('scripts')
<script src="{{ asset('assets/libs/datatables.net/js/jquery.dataTables.min.js') }}"></script>
<script src="{{ asset('assets/libs/datatables.net-bs5/js/dataTables.bootstrap5.min.js') }}"></script>
<script src="{{ asset('assets/libs/datatables.net-responsive/js/dataTables.responsive.min.js') }}"></script>
<script src="{{ asset('assets/libs/datatables.net-responsive-bs5/js/responsive.bootstrap5.min.js') }}"></script>

<script>
$(document).ready(function() {
    $('#paymentsTable').DataTable({
        processing: true,
        serverSide: true,
        ajax: {
            url: '{{ route("payments.data") }}',
            type: 'GET'
        },
        columns: [
            { data: 'record_no', name: 'record_no' },
            { data: 'transaction_refno', name: 'transaction_refno' },
            { data: 'beneficiary_name', name: 'beneficiary_name' },
            { data: 'bank_name', name: 'bank_name' },
            { data: 'value_date', name: 'value_date' },
            { data: 'amount', name: 'amount', orderable: true },
            { data: 'payment_type', name: 'payment_type' },
            { data: 'transaction_status', name: 'transaction_status', orderable: false },
            { data: 'import_date', name: 'import_date' },
            { data: 'action', name: 'action', orderable: false, searchable: false }
        ],
        order: [[8, 'desc']], 
        pageLength: 25,
        lengthMenu: [[10, 25, 50, 100, -1], [10, 25, 50, 100, "All"]],
        responsive: true,
        language: {
            processing: '<div class="spinner-border text-primary" role="status"><span class="visually-hidden">Loading...</span></div>',
            emptyTable: "No payment records found",
            zeroRecords: "No matching payment records found"
        },
        drawCallback: function(settings) {
            $('[data-bs-toggle="tooltip"]').tooltip();
        }
    });
    
    // Handle view details button click
    $(document).on('click', '.view-details', function() {
        var paymentId = $(this).data('id');
        
        // Show loading spinner
        $('#paymentDetailsContent').html(`
            <div class="text-center py-4">
                <div class="spinner-border text-primary" role="status">
                    <span class="visually-hidden">Loading...</span>
                </div>
                <p class="mt-2 text-muted">Loading payment details...</p>
            </div>
        `);
        
        // Fetch payment details
        $.ajax({
            url: '{{ route("payments.details") }}',
            type: 'GET',
            data: { id: paymentId },
            success: function(response) {
                if (response.success) {
                    var data = response.data;
                    var html = `
                        <div class="row g-4">
                            <div class="col-md-6">
                                <div class="card border-primary h-100">
                                    <div class="card-header bg-primary-subtle">
                                        <h6 class="card-title mb-0 text-primary">
                                            <i class="mdi mdi-account-details me-2"></i>Basic Information
                                        </h6>
                                    </div>
                                    <div class="card-body">
                                        <div class="row g-3">
                                            <div class="col-6">
                                                <label class="form-label text-muted mb-1">Record No</label>
                                                <div class="fw-semibold">${data.record_no}</div>
                                            </div>
                                            <div class="col-6">
                                                <label class="form-label text-muted mb-1">Transaction Ref</label>
                                                <div class="fw-semibold text-primary">${data.transaction_refno}</div>
                                            </div>
                                            <div class="col-6">
                                                <label class="form-label text-muted mb-1">Value Date</label>
                                                <div class="fw-semibold">${data.value_date}</div>
                                            </div>
                                            <div class="col-6">
                                                <label class="form-label text-muted mb-1">Amount</label>
                                                <div class="fw-bold text-success fs-5">${data.amount}</div>
                                            </div>
                                            <div class="col-6">
                                                <label class="form-label text-muted mb-1">Currency</label>
                                                <div class="fw-semibold">${data.currency}</div>
                                            </div>
                                            <div class="col-6">
                                                <label class="form-label text-muted mb-1">Payment Type</label>
                                                <div><span class="badge bg-info fs-6">${data.payment_type}</span></div>
                                            </div>
                                            <div class="col-12">
                                                <label class="form-label text-muted mb-1">Transaction Status</label>
                                                <div><span class="badge bg-success fs-6">${data.transaction_status}</span></div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="col-md-6">
                                <div class="card border-success h-100">
                                    <div class="card-header bg-success-subtle">
                                        <h6 class="card-title mb-0 text-success">
                                            <i class="mdi mdi-bank me-2"></i>Beneficiary Details
                                        </h6>
                                    </div>
                                    <div class="card-body">
                                        <div class="row g-3">
                                            <div class="col-12">
                                                <label class="form-label text-muted mb-1">Beneficiary Name</label>
                                                <div class="fw-semibold">${data.beneficiary_name}</div>
                                            </div>
                                            <div class="col-12">
                                                <label class="form-label text-muted mb-1">Account Number</label>
                                                <div class="fw-semibold font-monospace">${data.beneficiary_account_number}</div>
                                            </div>
                                            <div class="col-6">
                                                <label class="form-label text-muted mb-1">Bank Name</label>
                                                <div class="fw-semibold">${data.bank_name}</div>
                                            </div>
                                            <div class="col-6">
                                                <label class="form-label text-muted mb-1">IFSC Code</label>
                                                <div class="fw-semibold font-monospace">${data.ifsc_code}</div>
                                            </div>
                                            <div class="col-6">
                                                <label class="form-label text-muted mb-1">Destination Bank</label>
                                                <div class="fw-semibold">${data.destination_bank}</div>
                                            </div>
                                            <div class="col-6">
                                                <label class="form-label text-muted mb-1">Aadhaar Number</label>
                                                <div class="fw-semibold font-monospace">${data.beneficiary_aadhaar_number}</div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <div class="row g-4 mt-2">
                            <div class="col-md-6">
                                <div class="card border-warning h-100">
                                    <div class="card-header bg-warning-subtle">
                                        <h6 class="card-title mb-0 text-warning">
                                            <i class="mdi mdi-bank-transfer me-2"></i>Transaction Details
                                        </h6>
                                    </div>
                                    <div class="card-body">
                                        <div class="row g-3">
                                            <div class="col-12">
                                                <label class="form-label text-muted mb-1">RBI/UTR Reference</label>
                                                <div class="fw-semibold font-monospace">${data.rbi_utr_reference_number}</div>
                                            </div>
                                            <div class="col-12">
                                                <label class="form-label text-muted mb-1">Debit Account</label>
                                                <div class="fw-semibold font-monospace">${data.debit_account_number}</div>
                                            </div>
                                            <div class="col-6">
                                                <label class="form-label text-muted mb-1">Credit Reference</label>
                                                <div class="fw-semibold">${data.credit_reference}</div>
                                            </div>
                                            <div class="col-6">
                                                <label class="form-label text-muted mb-1">Error Description</label>
                                                <div class="fw-semibold ${data.error_description === '-' ? 'text-muted' : 'text-danger'}">${data.error_description}</div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="col-md-6">
                                <div class="card border-info h-100">
                                    <div class="card-header bg-info-subtle">
                                        <h6 class="card-title mb-0 text-info">
                                            <i class="mdi mdi-information me-2"></i>Import Information
                                        </h6>
                                    </div>
                                    <div class="card-body">
                                        <div class="row g-3">
                                            <div class="col-6">
                                                <label class="form-label text-muted mb-1">Import Date</label>
                                                <div class="fw-semibold">${data.import_date}</div>
                                            </div>
                                            <div class="col-6">
                                                <label class="form-label text-muted mb-1">Created At</label>
                                                <div class="fw-semibold">${data.created_at}</div>
                                            </div>
                                            <div class="col-12">
                                                <label class="form-label text-muted mb-1">Import Batch ID</label>
                                                <div class="font-monospace text-primary small bg-light p-2 rounded">${data.import_batch_id}</div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    `;
                    $('#paymentDetailsContent').html(html);
                } else {
                    $('#paymentDetailsContent').html(`
                        <div class="alert alert-danger">
                            <i class="mdi mdi-alert-circle me-2"></i>Error loading payment details.
                        </div>
                    `);
                }
            },
            error: function() {
                $('#paymentDetailsContent').html(`
                    <div class="alert alert-danger">
                        <i class="mdi mdi-alert-circle me-2"></i>Error loading payment details. Please try again.
                    </div>
                `);
            }
        });
    });
});
</script>
@endpush