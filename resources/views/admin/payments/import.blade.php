@extends('layouts.app')

@section('content')
<div class="page-content">
    <div class="container-fluid">

        <!-- Import Form -->
        <div class="row">
            <div class="col-lg-8 mx-auto">
                <div class="card">
                    <div class="card-header">
                        <h4 class="card-title mb-0">Upload Payment File</h4>
                    </div>
                    <div class="card-body">
                        <form id="importForm" enctype="multipart/form-data">
                            @csrf
                            <div class="mb-3">
                                <label for="payment_file" class="form-label">Select File <span class="text-danger">*</span></label>
                                <input type="file" class="form-control" id="payment_file" name="payment_file" 
                                       accept=".xlsx,.xls,.csv" required>
                                <div class="form-text">Supported formats: Excel (.xlsx, .xls) and CSV (.csv). Maximum file size: 10MB</div>
                            </div>
                            
                            <div class="mb-3">
                                <div class="alert alert-info">
                                    <h6 class="alert-heading">File Format Requirements:</h6>
                                    <ul class="mb-2">
                                        <li>Expected 17 columns: Record No, Beneficiary Account Number, Transaction Refno, Bank Name, IFSC Code, Account Number, Value Date, Amount, Beneficiary Name, Payment Type, RBI/UTR Reference Number, Currency, Error Description, Status of Cheque and Direct Debit Transaction, Beneficiary Aadhaar number, Destination Bank, Credit Reference</li>
                                        <li>Date format should be DD/MM/YYYY</li>
                                        <li>Amount should be numeric</li>
                                        <li>Empty cells or '- -' will be treated as NULL</li>
                                        <li>Duplicate transaction reference numbers will be skipped</li>
                                    </ul>
                                    <a href="{{ asset('templates/payment_import_template.csv') }}" class="btn btn-sm btn-outline-info" download>
                                        <i class="mdi mdi-download me-1"></i>Download Sample Template
                                    </a>
                                </div>
                            </div>

                            <div class="text-end">
                                <button type="submit" class="btn btn-primary" id="importBtn">
                                    <i class="mdi mdi-upload me-1"></i>Import File
                                </button>
                                <a href="{{ route('payments.index') }}" class="btn btn-secondary ms-2">
                                    <i class="mdi mdi-arrow-left me-1"></i>Back to List
                                </a>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>

        <!-- Import Results -->
        <div class="row" id="resultsSection" style="display: none;">
            <div class="col-lg-8 mx-auto">
                <div class="card">
                    <div class="card-header">
                        <h4 class="card-title mb-0">Import Results</h4>
                    </div>
                    <div class="card-body" id="resultsContent">
                        <!-- Results will be populated here -->
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Loading Modal -->
<div class="modal fade" id="loadingModal" tabindex="-1" data-bs-backdrop="static" data-bs-keyboard="false">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-body text-center p-4">
                <div class="spinner-border text-primary mb-3" role="status">
                    <span class="visually-hidden">Loading...</span>
                </div>
                <h5>Processing Import...</h5>
                <p class="text-muted mb-0">Please wait while we process your file.</p>
            </div>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
$(document).ready(function() {
    $('#importForm').on('submit', function(e) {
        e.preventDefault();
        
        const formData = new FormData(this);
        const loadingModal = new bootstrap.Modal(document.getElementById('loadingModal'));
        
        // Show loading modal
        loadingModal.show();
        
        // Disable submit button
        $('#importBtn').prop('disabled', true);
        
        $.ajax({
            url: '{{ route("payments.import.process") }}',
            type: 'POST',
            data: formData,
            processData: false,
            contentType: false,
            success: function(response) {
                loadingModal.hide();
                $('#importBtn').prop('disabled', false);
                
                if (response.success) {
                    showResults(response.data);
                    $('#importForm')[0].reset();
                    
                    // Show success toast
                    showToast('success', 'Import completed successfully!');
                } else {
                    showToast('error', response.message);
                }
            },
            error: function(xhr) {
                loadingModal.hide();
                $('#importBtn').prop('disabled', false);
                
                let message = 'Import failed. Please try again.';
                if (xhr.responseJSON && xhr.responseJSON.message) {
                    message = xhr.responseJSON.message;
                }
                
                showToast('error', message);
            }
        });
    });
    
    function showResults(data) {
        let html = `
            <div class="row">
                <div class="col-md-3">
                    <div class="card bg-primary text-white">
                        <div class="card-body">
                            <div class="d-flex align-items-center">
                                <div class="flex-grow-1">
                                    <h4 class="mb-0">${data.total_rows}</h4>
                                    <p class="mb-0">Total Rows</p>
                                </div>
                                <div class="flex-shrink-0">
                                    <i class="mdi mdi-file-document-outline fs-2"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card bg-success text-white">
                        <div class="card-body">
                            <div class="d-flex align-items-center">
                                <div class="flex-grow-1">
                                    <h4 class="mb-0">${data.inserted_rows}</h4>
                                    <p class="mb-0">Inserted</p>
                                </div>
                                <div class="flex-shrink-0">
                                    <i class="mdi mdi-check-circle-outline fs-2"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card bg-warning text-white">
                        <div class="card-body">
                            <div class="d-flex align-items-center">
                                <div class="flex-grow-1">
                                    <h4 class="mb-0">${data.skipped_rows}</h4>
                                    <p class="mb-0">Skipped</p>
                                </div>
                                <div class="flex-shrink-0">
                                    <i class="mdi mdi-skip-next-outline fs-2"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card bg-danger text-white">
                        <div class="card-body">
                            <div class="d-flex align-items-center">
                                <div class="flex-grow-1">
                                    <h4 class="mb-0">${data.failed_rows.length}</h4>
                                    <p class="mb-0">Failed</p>
                                </div>
                                <div class="flex-shrink-0">
                                    <i class="mdi mdi-alert-circle-outline fs-2"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        `;
        
        if (data.failed_rows.length > 0) {
            html += `
                <div class="mt-3">
                    <h6>Failed Rows:</h6>
                    <div class="table-responsive">
                        <table class="table table-sm">
                            <thead>
                                <tr>
                                    <th>Row Number</th>
                                    <th>Reason</th>
                                </tr>
                            </thead>
                            <tbody>
            `;
            
            data.failed_rows.forEach(function(row) {
                html += `
                    <tr>
                        <td>${row.row}</td>
                        <td>${row.reason}</td>
                    </tr>
                `;
            });
            
            html += `
                            </tbody>
                        </table>
                    </div>
                </div>
            `;
        }
        
        $('#resultsContent').html(html);
        $('#resultsSection').show();
    }
    
    function showToast(type, message) {
        // You can implement your toast notification here
        // For now, using simple alert
        if (type === 'success') {
            alert('Success: ' + message);
        } else {
            alert('Error: ' + message);
        }
    }
});
</script>
@endpush