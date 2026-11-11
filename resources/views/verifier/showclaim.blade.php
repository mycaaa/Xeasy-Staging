@extends('layouts.standalone')

@section('title', 'Claim Details')

@push('head')
    <link rel="stylesheet" href="{{ asset('vendor/cdn/bootstrap@4.2.1/dist/css/bootstrap.min.css') }}">
    <link rel="stylesheet" href="{{ asset('vendor/cdn/font-awesome@4.7.0/css/font-awesome.min.css') }}">
    <style>
        body { padding: 20px; background-color: #f5f5f5; }
        .claim-container { max-width: 1200px; margin: 0 auto; }
        .remark-box { border: 1px solid #ddd; padding: 10px; margin-top: 10px; border-radius: 5px; }
        .status-badge { font-size: 14px; padding: 5px 10px; }
    </style>
@endpush

@section('content')
<div class="container claim-container">
    <div class="card">
        <div class="card-header bg-primary text-white">
            <h5>Expense Claim Details - ID: {{ $expId }}</h5>
        </div>
        <div class="card-body">
            <div id="claim-details">
                <!-- Claim details will be loaded via AJAX -->
                <div class="text-center">
                    <div class="spinner-border text-primary" role="status">
                        <span class="sr-only">Loading...</span>
                    </div>
                    <p>Loading claim details...</p>
                </div>
            </div>

            <div class="remark-box mt-3">
                <h6>Add Remark</h6>
                <div class="form-group">
                    <textarea id="remarkText" class="form-control" rows="2" placeholder="Enter your remark here..." aria-label="Enter your remark here"></textarea>
                </div>
                <button class="btn btn-primary" onclick="submitRemark()">Submit Remark</button>
            </div>

            <div id="remarks-list" class="mt-3">
                <h6>Previous Remarks</h6>
                <div id="remarks-container"></div>
            </div>
        </div>
    </div>
</div>

<script src="{{ asset('vendor/cdn/jquery@3.3.1/dist/jquery.min.js') }}"></script>
<script>
    const expId = '{{ $expId }}';
    const employeeId = '{{ $employeeId }}';
    const yearId = '{{ $yearId }}';
    const companyId = '{{ $companyId }}';

    // Load claim details
    function loadClaimDetails() {
        $.ajax({
            url: "{{ url('verifier/expense-details') }}",
            method: "GET",
            data: {
                expid: expId,
                ei: employeeId,
                YearId: yearId,
                CompanyId: companyId
            },
            success: function(response) {
                if (response.Code === '300' && response.ExpenseDetails) {
                    displayClaimDetails(response.ExpenseDetails);
                    displayRemarks(response.Remarks || []);
                } else {
                    $('#claim-details').html('<div class="alert alert-danger">Failed to load claim details</div>');
                }
            },
            error: function() {
                $('#claim-details').html('<div class="alert alert-danger">Error loading claim details</div>');
            }
        });
    }

    function displayClaimDetails(details) {
        let html = '<table class="table table-bordered">';
        html += '<tr><th>Expense ID</th><td>' + (details.ExpId || expId) + '</td></tr>';
        html += '<tr><th>Bill Date</th><td>' + (details.BillDate || '-') + '</td></tr>';
        html += '<tr><th>Amount</th><td>' + (details.Amount || '0') + '/-</td></tr>';
        html += '<tr><th>Status</th><td>' + getStatusHtml(details.ClaimStatus) + '</td></tr>';
        html += '<tr><th>Description</th><td>' + (details.Description || '-') + '</td></tr>';
        html += '</table>';

        // Add images if available
        if (details.Img_1 || details.Img_2 || details.Img_3) {
            html += '<h6>Attached Images</h6><div class="row">';
            for(let i = 1; i <= 3; i++) {
                if(details['Img_' + i]) {
                    html += '<div class="col-md-4"><img alt="Bill or document image" src="' + details['Img_' + i] + '" class="img-fluid img-thumbnail" onclick="window.open(this.src)"></div>';
                }
            }
            html += '</div>';
        }

        $('#claim-details').html(html);
        
        // Load PAX details if applicable
        const claimId = details.ClaimId;
        if (claimId === 1 || claimId === 14 || claimId === 17 || claimId === 42) {
            loadPaxDetails(details.ExpId, claimId);
        }
    }

    function getStatusHtml(status) {
        const badges = {
            'Draft': '<span class="badge badge-secondary">Draft</span>',
            'Submitted': '<span class="badge badge-info">Submitted</span>',
            'Filled': '<span class="badge badge-success">Filled</span>',
            'Deactivate': '<span class="badge badge-danger">Deactivated</span>'
        };
        return badges[status] || '<span class="badge badge-secondary">' + status + '</span>';
    }

    function displayRemarks(remarks) {
        if (!remarks || remarks.length === 0) {
            $('#remarks-container').html('<p class="text-muted">No remarks yet</p>');
            return;
        }

        let html = '<div class="list-group">';
        remarks.forEach(remark => {
            html += '<div class="list-group-item">';
            html += '<strong>' + $('<div>').text(String(remark.UserName || 'User')).html() + '</strong> <small class="text-muted">(' + $('<div>').text(String(remark.Date || '-')).html() + ')</small>'; // S-09
            html += '<p class="mb-0 mt-1">' + $('<div>').text(String(remark.Remark || '')).html() + '</p>';
            html += '</div>';
        });
        html += '</div>';
        $('#remarks-container').html(html);
    }

    // 🏨 Load and display PAX details
    function loadPaxDetails(expId, claimId) {
        const url = new URL('{{ route("api.pax.get") }}', window.location.origin);
        url.searchParams.append('exp_id', expId);
        url.searchParams.append('claim_id', claimId);
        url.searchParams.append('year_id', yearId);
        url.searchParams.append('com_id', companyId);
        
        console.log('🏨 Loading PAX details from:', url.toString());
        
        fetch(url, {
            headers: {
                'X-CSRF-TOKEN': '{{ csrf_token() }}',
                'Accept': 'application/json'
            }
        })
        .then(response => response.json())
        .then(data => {
            console.log('🏨 PAX details response:', data);
            // Display if: has participants OR has other_participants OR has quota
            if (data.success && (
                (data.participants && data.participants.length > 0) || 
                data.other_participants || 
                data.quota
            )) {
                displayPaxDetailsTable(data);
            } else if (data.data && data.data.length === 0) {
                console.log('No PAX data available');
            }
        })
        .catch(error => {
            console.error('Error loading PAX details:', error);
        });
    }

    function displayPaxDetailsTable(data) {
        let html = '<div class="card mt-4 border-info">';
        html += '<div class="card-header bg-info text-white">';
        html += '<h6 class="mb-0"><i class="fa fa-users"></i> ' + data.claim_type_name + ' - Participant Details</h6>';
        html += '</div>';
        html += '<div class="card-body">';
        
        // Summary
        html += '<div class="alert alert-info mb-3 text-dark">';
        html += '<strong>Total Participants:</strong> ' + (data.total_participants || 0);
        if (data.pax_count) html += ' | <strong>PAX Count:</strong> ' + data.pax_count;
        if (data.quota) html += ' | <strong>Quota:</strong> <span class="badge badge-success">' + data.quota + '</span>';
        html += '</div>';
        
        // Participants table (only if there are participants)
        if (data.participants && data.participants.length > 0) {
            html += '<div class="table-responsive">';
            html += '<table class="table table-striped table-sm table-hover">';
            html += '<thead class="table-dark">';
            html += '<tr>';
            html += '<th>#</th>';
            html += '<th>Code</th>';
            html += '<th>Employee Details</th>';
            html += '<th>Department</th>';
            html += '<th>Designation</th>';
            html += '<th>Grade</th>';
            html += '<th>Status</th>';
            if (data.participants[0] && data.participants[0].eligibility) {
                html += '<th>Max Amount</th>';
                html += '<th>Max Days</th>';
            }
            html += '</tr>';
            html += '</thead>';
            html += '<tbody>';
            
            data.participants.forEach((participant, index) => {
                const validClass = participant.is_valid ? '' : ' table-warning';
                const statusBadge = participant.is_valid 
                    ? '<span class="badge badge-success"><i class="fa fa-check-circle"></i> Valid</span>'
                    : '<span class="badge badge-danger"><i class="fa fa-times-circle"></i> Invalid</span>';
                
                const tooltipInfo = [
                    participant.email ? 'Email: ' + participant.email : '',
                    participant.mobile ? 'Mobile: ' + participant.mobile : '',
                    participant.branch && participant.branch.name ? 'Branch: ' + participant.branch.name : '',
                    participant.division && participant.division.name ? 'Division: ' + participant.division.name : ''
                ].filter(Boolean).join(' | ');
                
                html += '<tr class="' + validClass + '" title="' + tooltipInfo + '">';
                html += '<td>' + (index + 1) + '</td>';
                html += '<td><code class="bg-light p-1">' + participant.emp_code + '</code></td>';
                html += '<td>';
                html += '<div>';
                html += '<strong>' + participant.emp_name + '</strong>';
                if (participant.email) {
                    html += '<br><small class="text-muted"><i class="fa fa-envelope"></i> ' + participant.email + '</small>';
                }
                if (participant.mobile) {
                    html += '<br><small class="text-muted"><i class="fa fa-phone"></i> ' + participant.mobile + '</small>';
                }
                html += '</div>';
                html += '</td>';
                html += '<td>';
                if (participant.department && participant.department.name) {
                    html += '<span class="text-dark"><i class="fa fa-building"></i> ' + participant.department.name + '</span>';
                } else {
                    html += '<span class="text-muted">-</span>';
                }
                html += '</td>';
                html += '<td>';
                if (participant.designation && participant.designation.name) {
                    html += '<span class="text-dark"><i class="fa fa-briefcase"></i> ' + participant.designation.name + '</span>';
                } else {
                    html += '<span class="text-muted">-</span>';
                }
                html += '</td>';
                html += '<td>';
                if (participant.grade && participant.grade.name) {
                    html += '<span class="badge badge-info">' + participant.grade.name + '</span>';
                } else {
                    html += '<span class="text-muted">-</span>';
                }
                html += '</td>';
                html += '<td>' + statusBadge + '</td>';
                
                if (participant.eligibility) {
                    html += '<td>₹' + parseFloat(participant.eligibility.max_amount).toLocaleString('en-IN', {minimumFractionDigits: 2}) + '</td>';
                    html += '<td>' + participant.eligibility.max_days + ' days</td>';
                }
                
                html += '</tr>';
            });
            
            html += '</tbody>';
            html += '</table>';
            html += '</div>';
        }
        
        // Other participants - display even if no regular participants
        if (data.other_participants && data.other_participants.trim() !== '') {
            html += '<div class="alert alert-secondary mt-3 mb-0">';
            html += '<strong><i class="fa fa-sticky-note"></i> Other Participants / Notes:</strong>';
            html += '<p class="mb-0 mt-2">' + (data.other_participants || 'N/A') + '</p>';
            html += '</div>';
        }
        
        html += '</div>';
        html += '</div>';
        
        // Insert PAX details after claim details
        $('#claim-details').after(html);
    }

    function submitRemark() {
        const remark = $('#remarkText').val().trim();
        if (!remark) {
            alert('Please enter a remark');
            return;
        }

        $.ajax({
            url: "{{ url('verifier/add-remark') }}",
            method: "POST",
            data: {
                ei: employeeId,
                YearId: yearId,
                CompanyId: companyId,
                exp_id: expId,
                remark: remark,
                _token: '{{ csrf_token() }}'
            },
            success: function(response) {
                if (response.Code === '300') {
                    alert('Remark added successfully');
                    $('#remarkText').val('');
                    loadClaimDetails(); // Reload to show new remark
                } else {
                    alert(response.msg || 'Failed to add remark');
                }
            },
            error: function() {
                alert('Error adding remark');
            }
        });
    }

    // Load on page ready
    $(document).ready(function() {
        loadClaimDetails();
    });
</script>
@endsection
