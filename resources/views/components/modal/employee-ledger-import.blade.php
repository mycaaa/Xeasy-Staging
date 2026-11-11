<!-- Employee Ledger Import Modal -->
<div class="modal fade" id="employeeLedgerImportModal" tabindex="-1" aria-labelledby="employeeLedgerImportModalLabel"
    aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="employeeLedgerImportModalLabel">
                    <i class="fas fa-file-import me-2"></i>Import Employee Ledger Balance
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <!-- Import Form -->
                <form id="employeeLedgerImportForm" enctype="multipart/form-data">
                    @csrf
                    <div class="row mb-4">
                        <div class="col-md-12">
                            <div class="d-flex justify-content-between align-items-center">
                                <label for="excel_file" class="form-label">Select Excel File</label>
                                <a href="#" onclick="downloadTemplate()" class="text-primary">
                                    <i class="fas fa-download me-2"></i>Download Import Format
                                </a>
                            </div>
                            <input type="file" class="form-control" id="excel_file" name="excel_file"
                                accept=".xlsx,.xls,.csv" required>
                            <div class="form-text">
                                Supported formats: Excel (.xlsx, .xls) or CSV (.csv)
                            </div>
                            
                            <!-- Duplicate Handling Options -->
                            <div class="mt-3">
                                <label class="form-label">Duplicate Record Handling:</label>
                                <div class="form-check">
                                    <input class="form-check-input" type="radio" name="duplicate_action" id="skip_duplicates" value="skip" checked>
                                    <label class="form-check-label" for="skip_duplicates">
                                        <i class="fas fa-forward me-1"></i>Skip duplicate records
                                    </label>
                                </div>
                                <div class="form-check">
                                    <input class="form-check-input" type="radio" name="duplicate_action" id="overwrite_duplicates" value="overwrite">
                                    <label class="form-check-label" for="overwrite_duplicates">
                                        <i class="fas fa-sync-alt me-1"></i>Overwrite existing records
                                    </label>
                                </div>
                            </div>
                        </div>
                    </div>
                </form>

                <!-- Import Progress -->
                <div id="importProgress" class="d-none">
                    <div class="progress mb-3">
                        <div class="progress-bar progress-bar-striped progress-bar-animated" role="progressbar"
                            style="width: 0%"></div>
                    </div>
                    <div id="importStatus" class="text-center">
                        <i class="fas fa-spinner fa-spin me-2"></i>Processing import...
                    </div>
                </div>

                <!-- Import Results -->
                <div id="importResults" class="d-none">
                    <div class="alert alert-success">
                        <h6><i class="fas fa-check-circle me-2"></i>Import Completed</h6>
                        <div id="importSummary"></div>
                    </div>

                    <!-- Validation Errors -->
                    <div id="validationErrors" class="d-none">
                        <div class="alert alert-warning">
                            <h6><i class="fas fa-exclamation-triangle me-2"></i>Validation Issues</h6>
                            <div class="table-responsive">
                                <table class="table table-sm">
                                    <thead>
                                        <tr>
                                            <th>Row</th>
                                            <th>Employee Code</th>
                                            <th>Issue</th>
                                        </tr>
                                    </thead>
                                    <tbody id="validationErrorsList">
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                <button type="button" class="btn btn-primary" id="startImportBtn">
                    <i class="fas fa-upload me-2"></i>Start Import
                </button>
                <button type="button" class="btn btn-success d-none" id="downloadTemplateBtn">
                    <i class="fas fa-download me-2"></i>Download Template
                </button>
            </div>
        </div>
    </div>
</div>

<script>
    document.addEventListener('DOMContentLoaded', function() {
        const modal = document.getElementById('employeeLedgerImportModal');
        const form = document.getElementById('employeeLedgerImportForm');
        const startImportBtn = document.getElementById('startImportBtn');
        const importProgress = document.getElementById('importProgress');
        const importResults = document.getElementById('importResults');



        // Start import process
        startImportBtn.addEventListener('click', function() {
            const fileInput = document.getElementById('excel_file');
            if (!fileInput.files[0]) {
                alert('Please select a file to import');
                return;
            }

            startImport();
        });



        function startImport() {
            const formData = new FormData(form);

            // Show progress
            importProgress.classList.remove('d-none');
            importResults.classList.add('d-none');
            startImportBtn.disabled = true;

            // Get CSRF token
            const csrfToken = document.querySelector('meta[name="csrf-token"]');
            const headers = {};

            if (csrfToken) {
                headers['X-CSRF-TOKEN'] = csrfToken.getAttribute('content');
            }

            fetch('/api/employee-ledger/import', {
                    method: 'POST',
                    body: formData,
                    headers: headers
                })
                .then(response => {
                    if (!response.ok) {
                        throw new Error(`HTTP error! status: ${response.status}`);
                    }
                    return response.json();
                })
                .then(data => {
                    importProgress.classList.add('d-none');
                    importResults.classList.remove('d-none');
                    startImportBtn.disabled = false;

                    if (data.success) {
                        showImportResults(data);
                    } else {
                        showImportError(data.message || 'Import failed');
                    }
                })
                .catch(error => {
                    console.error('Import error:', error);
                    importProgress.classList.add('d-none');
                    startImportBtn.disabled = false;
                    showImportError('An error occurred during import: ' + error.message);
                });
        }

        function showImportResults(data) {
            const summary = document.getElementById('importSummary');
            summary.innerHTML = `
            <ul class="mb-0">
                <li>Total records processed: ${data.total_processed || 0}</li>
                <li>Successfully imported: ${data.successful_imports || 0}</li>
                <li>Skipped duplicates: ${data.skipped_duplicates || 0}</li>
                <li>Overwritten records: ${data.overwritten_records || 0}</li>
                <li>Validation errors: ${data.validation_errors || 0}</li>
            </ul>
        `;

            if (data.errors && data.errors.length > 0) {
                showValidationErrors(data.errors);
            }
        }

        function showValidationErrors(errors) {
            const errorsContainer = document.getElementById('validationErrors');
            const errorsList = document.getElementById('validationErrorsList');

            errorsList.innerHTML = '';
            errors.forEach(error => {
                const row = document.createElement('tr');
                row.innerHTML = `
                <td>${error.row}</td>
                <td>${error.employee_code}</td>
                <td>${error.message}</td>
            `;
                errorsList.appendChild(row);
            });

            errorsContainer.classList.remove('d-none');
        }

        function showImportError(message) {
            const results = document.getElementById('importResults');
            results.innerHTML = `
            <div class="alert alert-danger">
                <h6><i class="fas fa-exclamation-circle me-2"></i>Import Failed</h6>
                <p class="mb-0">${message}</p>
            </div>
        `;
            results.classList.remove('d-none');
        }


    });

    function downloadTemplate() {
        window.location.href = '/api/employee-ledger/template';
    }
</script>
