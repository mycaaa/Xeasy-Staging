@extends('layouts.app')

@section('content')
<div class="page-content">
    <div class="container-fluid">

        <div class="row mb-3">
            <div class="col-12">
                <h4 class="fw-bold" style="color:#15648C;">
                    <i class="ri-mail-send-line me-2"></i>Sent Mail Logs
                </h4>
            </div>
        </div>

        {{-- Filters --}}
        <div class="card mb-3" style="border:1px solid #e0e7ef;">
            <div class="card-body py-3" style="background:#f8fafc;">
                {{-- Row 1: Employee, Recipient, Subject Category --}}
                <div class="row g-2 align-items-end mb-2">
                    <div class="col-md-3">
                        <label class="form-label mb-1 fw-bold" style="font-size:11px;color:#15648C;">Employee (code / email)</label>
                        <select class="form-select form-select-sm" id="filterEmployee">
                            <option value="">All Employees</option>
                            @foreach($employees as $emp)
                                <option value="{{ $emp['code'] }}">{{ $emp['display'] }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label mb-1 fw-bold" style="font-size:11px;color:#15648C;">Recipient (To)</label>
                        <select class="form-select form-select-sm" id="filterTo" multiple>
                            @foreach($recipientMap as $email => $displayText)
                                <option value="{{ $email }}">{{ $displayText }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label mb-1 fw-bold" style="font-size:11px;color:#15648C;">Subject Category</label>
                        <select class="form-select form-select-sm" id="filterSubjectCategory" multiple>
                            @foreach($subjectCategories as $key => $category)
                                <option value="{{ $key }}">{{ $category['label'] }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label mb-1 fw-bold" style="font-size:11px;color:#15648C;">Subject</label>
                        <select class="form-select form-select-sm" id="filterSubject" multiple>
                            @foreach($subjects as $s)
                                <option value="{{ $s }}">{{ $s }}</option>
                            @endforeach
                        </select>
                    </div>
                </div>
                {{-- Row 2: From, Date Range, Buttons --}}
                <div class="row g-2 align-items-end">
                    <div class="col-md-3">
                        <label class="form-label mb-1 fw-bold" style="font-size:11px;color:#15648C;">From</label>
                        <select class="form-select form-select-sm" id="filterFrom">
                            <option value="">All Senders</option>
                            @foreach($senders as $s)
                                <option value="{{ $s }}">{{ $s }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label mb-1 fw-bold" style="font-size:11px;color:#15648C;">Date Range</label>
                        <div class="d-flex gap-1">
                            <input type="date" class="form-control form-control-sm" id="filterDateFrom" style="font-size:11px;">
                            <input type="date" class="form-control form-control-sm" id="filterDateTo" style="font-size:11px;">
                        </div>
                    </div>
                    <div class="col-md-2 d-flex gap-1">
                        <button class="btn btn-sm flex-fill" id="btnSearch" style="background:#15648C;color:white;">
                            <i class="ri-search-line me-1"></i>Search
                        </button>
                        <button class="btn btn-outline-secondary btn-sm" id="btnClear">
                            Clear
                        </button>
                    </div>
                </div>
            </div>
        </div>

        {{-- Stats --}}
        <div class="d-flex justify-content-between align-items-center mb-2">
            <span class="text-muted" style="font-size:12px;">Total: <strong id="totalCount">0</strong> emails</span>
            <select class="form-select form-select-sm" id="perPage" style="font-size:11px;width:auto;">
                <option value="25">25 per page</option>
                <option value="50">50 per page</option>
                <option value="100">100 per page</option>
            </select>
        </div>

        {{-- Table --}}
        <div class="card">
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-bordered table-sm table-hover align-middle mb-0" style="font-size:11px;">
                        <thead style="background:#15648C;color:white;">
                            <tr>
                                <th style="width:30px;">#</th>
                                <th>From</th>
                                <th>To</th>
                                <th style="width:140px;">Emp Email</th>
                                <th style="width:80px;">EC</th>
                                <th>Emp Name</th>
                                <th>Department</th>
                                <th>Designation</th>
                                <th>Subject</th>
                                <th style="width:140px;">Date</th>
                                <th style="width:50px;">View</th>
                            </tr>
                        </thead>
                        <tbody id="mailLogBody">
                            <tr><td colspan="10" class="text-center py-4"><div class="spinner-border spinner-border-sm text-primary"></div> Loading...</td></tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        {{-- Pagination --}}
        <div class="d-flex justify-content-between align-items-center mt-2">
            <span class="text-muted" style="font-size:11px;" id="pageInfo"></span>
            <div>
                <button class="btn btn-sm" id="btnPrev" disabled style="border:1px solid #15648C;color:#15648C;">&laquo; Prev</button>
                <button class="btn btn-sm" id="btnNext" disabled style="border:1px solid #15648C;color:#15648C;">Next &raquo;</button>
            </div>
        </div>

    </div>
</div>

{{-- Email Body Modal --}}
<div class="modal fade" id="emailBodyModal" tabindex="-1" style="z-index:1060;">
    <div class="modal-dialog modal-xl">
        <div class="modal-content">
            <div class="modal-header py-2" style="background:#15648C;color:white;">
                <h6 class="modal-title mb-0"><i class="ri-mail-open-line me-1"></i><span id="modalSubject">Email Content</span></h6>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body p-0">
                <div class="p-2 bg-light border-bottom" id="modalMeta" style="font-size:11px;"></div>
                <iframe id="emailBodyFrame" style="width:100%;height:500px;border:none;"></iframe>
            </div>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<link href="{{ asset('vendor/cdn/select2@4.1.0-rc.0/dist/css/select2.min.css') }}" rel="stylesheet" />
<link href="{{ asset('vendor/cdn/select2-bootstrap-5-theme@1.3.0/dist/select2-bootstrap-5-theme.min.css') }}" rel="stylesheet" />
<script src="{{ asset('vendor/cdn/select2@4.1.0-rc.0/dist/js/select2.min.js') }}"></script>
<style>
    .select2-container--bootstrap-5 .select2-selection--multiple .select2-selection__choice {
        background-color: #15648C !important;
        color: #ffffff !important;
        border: none !important;
        font-size: 10px !important;
        padding: 1px 6px !important;
    }
    .select2-container--bootstrap-5 .select2-selection--multiple .select2-selection__choice__remove {
        color: #ffffff !important;
    }
    .select2-container--bootstrap-5 .select2-selection--single .select2-selection__rendered {
        color: #333;
        font-size: 11px !important;
    }
    .select2-container--bootstrap-5 .select2-selection--multiple .select2-selection__rendered {
        font-size: 11px !important;
    }
    .select2-container--bootstrap-5 .select2-selection {
        min-height: 30px !important;
        font-size: 11px !important;
    }
    .select2-container--bootstrap-5 .select2-search__field {
        font-size: 11px !important;
    }
    .select2-results__option {
        font-size: 11px !important;
        padding: 4px 8px !important;
    }
    #emailBodyModal .modal-header {
        background: #15648C !important;
        color: #ffffff !important;
    }
    #emailBodyModal .modal-header .modal-title,
    #emailBodyModal .modal-header .modal-title span {
        color: #ffffff !important;
    }
</style>
<script>
let currentPage = 1;
let lastPage = 1;

$(document).ready(function() {
    // Init Select2 on dropdowns
    $('#filterTo').select2({
        placeholder: 'Search recipient...',
        allowClear: true,
        theme: 'bootstrap-5',
        width: '100%',
    });
    $('#filterSubjectCategory').select2({
        placeholder: 'Filter by category...',
        allowClear: true,
        theme: 'bootstrap-5',
        width: '100%',
    });
    $('#filterSubject').select2({
        placeholder: 'Search subject...',
        allowClear: true,
        theme: 'bootstrap-5',
        width: '100%',
    });
    $('#filterFrom').select2({
        placeholder: 'All Senders',
        allowClear: true,
        theme: 'bootstrap-5',
        width: '100%',
    });
    $('#filterEmployee').select2({
        placeholder: 'Search employee by name or code...',
        allowClear: true,
        theme: 'bootstrap-5',
        width: '100%',
    });

    loadMails(1);

    // Update subjects when category changes
    $('#filterSubjectCategory').on('change', function() {
        var selectedCategories = $(this).val();

        if (!selectedCategories || selectedCategories.length === 0) {
            // If no category selected, clear subjects
            $('#filterSubject').val(null).trigger('change');
            $('#filterSubject').empty();
            // Reload all subjects from initial state
            $.get('{{ route("admin.mail.logs.index") }}', function(res) {
                location.reload();
            });
            return;
        }

        // Use first selected category
        var firstCategory = selectedCategories[0];

        $.get('{{ route("admin.mail.logs.subjects-by-category") }}', {category: firstCategory}, function(res) {
            if (res.success && res.data.length > 0) {
                $('#filterSubject').empty();
                res.data.forEach(function(subject) {
                    $('#filterSubject').append('<option value="' + subject + '">' + subject + '</option>');
                });
                $('#filterSubject').val(null).trigger('change');
            } else {
                $('#filterSubject').empty().trigger('change');
            }
        }).fail(function() {
            console.error('Failed to load subjects for category');
            $('#filterSubject').empty().trigger('change');
        });
    });

    $('#btnSearch').on('click', function() { loadMails(1); });
    $('#btnClear').on('click', function() {
        $('#filterTo').val(null).trigger('change');
        $('#filterSubjectCategory').val(null).trigger('change');
        $('#filterSubject').val(null).trigger('change');
        $('#filterFrom').val(null).trigger('change');
        $('#filterEmployee').val(null).trigger('change');
        $('#filterDateFrom, #filterDateTo').val('');
        loadMails(1);
    });
    $('#perPage').on('change', function() { loadMails(1); });
    $('#btnPrev').on('click', function() { if(currentPage > 1) loadMails(currentPage - 1); });
    $('#btnNext').on('click', function() { if(currentPage < lastPage) loadMails(currentPage + 1); });

    // View email body
    $(document).on('click', '.btn-view-email', function(e) {
        e.preventDefault();
        e.stopPropagation();
        var id = $(this).data('id');
        var subject = $(this).data('subject');
        var to = $(this).data('to');
        var from = $(this).data('from');
        var date = $(this).data('date');
        $('#modalSubject').text(subject || 'Email Content');
        $('#modalMeta').html('<strong>From:</strong> ' + (from||'-') + ' &nbsp;|&nbsp; <strong>To:</strong> ' + (to||'-') + ' &nbsp;|&nbsp; <strong>Date:</strong> ' + (date||'-'));
        $('#emailBodyFrame').attr('src', '{{ url("admin/mail-logs/body") }}/' + id);
        var modal = new bootstrap.Modal(document.getElementById('emailBodyModal'));
        modal.show();
    });
});

function loadMails(page) {
    currentPage = page || 1;
    var toVal = $('#filterTo').val(); // array (multiselect)
    var subjectCategoryVal = $('#filterSubjectCategory').val(); // array (multiselect)
    var subjectVal = $('#filterSubject').val(); // array (multiselect)
    var empVal = $('#filterEmployee').val();

    var params = {
        page: currentPage,
        per_page: $('#perPage').val(),
        from: $('#filterFrom').val() || '',
        date_from: $('#filterDateFrom').val(),
        date_to: $('#filterDateTo').val(),
    };

    // Only send emp_code if actually selected
    if (empVal && empVal.length > 0) {
        params.emp_code = empVal;
    }

    // Send multiple 'to' values as array
    if (toVal && toVal.length) {
        params.to = toVal;
    }

    // Send multiple 'subject_category' values as array (takes priority over subject)
    if (subjectCategoryVal && subjectCategoryVal.length) {
        params.subject_category = subjectCategoryVal;
    }
    // Send multiple 'subject' values as array (only if no category selected)
    else if (subjectVal && subjectVal.length) {
        params.subject = subjectVal;
    }

    console.log('Mail filter params:', params);

    $('#mailLogBody').html('<tr><td colspan="10" class="text-center py-3"><div class="spinner-border spinner-border-sm text-primary"></div> Loading...</td></tr>');

    $.get('{{ route("admin.mail.logs.data") }}', params, function(res) {
        if (!res.success || !res.data.length) {
            $('#mailLogBody').html('<tr><td colspan="10" class="text-center py-3 text-muted">No emails found</td></tr>');
            $('#totalCount').text(0);
            $('#pageInfo').text('');
            $('#btnPrev, #btnNext').prop('disabled', true);
            return;
        }

        var html = '';
        var offset = (res.current_page - 1) * res.per_page;
        res.data.forEach(function(email, i) {
            var date = email.created_at ? new Date(email.created_at).toLocaleString('en-IN', {day:'2-digit',month:'short',year:'numeric',hour:'2-digit',minute:'2-digit'}) : '-';
            var empDisplay = email.emp_code ? '<span class="badge bg-primary-subtle text-primary" style="font-size:9px;">' + email.emp_code + '</span>' : '-';
            var empName = email.emp_name || '-';
            var empEmail = email.emp_email || '-';
            var department = email.department || '-';
            var designation = email.designation || '-';
            html += '<tr>' +
                '<td>' + (offset + i + 1) + '</td>' +
                '<td style="max-width:150px;" class="text-truncate" title="' + (email.from||'') + '">' + (email.from || '-') + '</td>' +
                '<td style="max-width:180px;" class="text-truncate" title="' + (email.to||'') + '">' + (email.to || '-') + '</td>' +
                '<td style="max-width:140px;" class="text-truncate" title="' + empEmail + '">' + empEmail + '</td>' +
                '<td class="text-center">' + empDisplay + '</td>' +
                '<td style="max-width:140px;" class="text-truncate" title="' + empName + '">' + empName + '</td>' +
                '<td style="max-width:130px;" class="text-truncate" title="' + department + '"><span class="badge bg-info-subtle text-info" style="font-size:9px;">' + department + '</span></td>' +
                '<td style="max-width:130px;" class="text-truncate" title="' + designation + '">' + designation + '</td>' +
                '<td class="fw-semibold" style="max-width:200px;" title="' + (email.subject||'') + '">' + (email.subject || '-') + '</td>' +
                '<td style="white-space:nowrap;">' + date + '</td>' +
                '<td class="text-center"><button class="btn btn-xs btn-view-email" data-id="' + email.id + '" data-subject="' + (email.subject||'').replace(/"/g,'&quot;') + '" data-to="' + (email.to||'') + '" data-from="' + (email.from||'') + '" data-date="' + date + '" style="font-size:10px;padding:1px 6px;background:#15648C;color:white;border:none;border-radius:3px;"><i class="ri-eye-line"></i></button></td>' +
                '</tr>';
        });
        $('#mailLogBody').html(html);
        $('#totalCount').text(res.total);
        lastPage = res.last_page;
        $('#pageInfo').text('Page ' + res.current_page + ' of ' + res.last_page);
        $('#btnPrev').prop('disabled', res.current_page <= 1);
        $('#btnNext').prop('disabled', res.current_page >= res.last_page);
    });
}
</script>
@endpush
