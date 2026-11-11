{{-- PAX Details Section for Verifier Screen --}}

@section('pax-details')
    <div id="paxDetailsContainer" class="mt-4">
        <!-- PAX details will be loaded here via AJAX -->
    </div>
@endsection

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Get claim details from page context
    const expId = @json($expId ?? null);
    const claimId = @json($claimId ?? null);
    const yearId = @json($yearId ?? null);
    const comId = @json($comId ?? null);
    
    if (expId && claimId && yearId && comId) {
        fetchPaxDetails(expId, claimId, yearId, comId);
    }
});

function fetchPaxDetails(expId, claimId, yearId, comId) {
    const url = new URL('{{ route("api.pax.get") }}', window.location.origin);
    url.searchParams.append('exp_id', expId);
    url.searchParams.append('claim_id', claimId);
    url.searchParams.append('year_id', yearId);
    url.searchParams.append('com_id', comId);
    
    fetch(url, {
        headers: {
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
            'Accept': 'application/json'
        }
    })
    .then(data => {
        const hasEmployeePax = data.participants && data.participants.length > 0;
        const hasOtherParsed = data.other_participants_parsed && data.other_participants_parsed.length > 0;
        let hasOtherRaw = false;
        if (data.other_participants) {
            if (typeof data.other_participants === 'string' && data.other_participants.trim() !== '') hasOtherRaw = true;
            else if (Array.isArray(data.other_participants) && data.other_participants.length > 0) hasOtherRaw = true;
        }
        const hasPax = hasEmployeePax || hasOtherParsed || hasOtherRaw || (data.pax_count && parseInt(data.pax_count) > 0);

        if (data.success && hasPax) {
            displayPaxDetails(data);
        } else {
            document.getElementById('paxDetailsContainer').innerHTML = 
                '<div class="alert alert-info">No participant details available for this claim.</div>';
        }
    })
    .catch(error => {
        console.error('Error fetching PAX details:', error);
        document.getElementById('paxDetailsContainer').innerHTML = 
            '<div class="alert alert-warning">Could not load participant details.</div>';
    });
}

function displayPaxDetails(data) {
    let html = `
        <div class="card">
            <div class="card-header bg-primary text-white">
                <h5 class="mb-0">
                    <i class="fas fa-users"></i> ${data.claim_type_name} - Participant Details
                </h5>
            </div>
            <div class="card-body">
                <div class="alert alert-primary mb-3 text-dark">
                    <strong>Total Participants:</strong> ${data.total_participants}
                    ${data.pax_count ? `| <strong>PAX Count:</strong> ${data.pax_count}` : ''}
                    ${data.quota ? `| <strong>Quota (Rail Fare):</strong> <span class="badge badge-success">${data.quota}</span>` : ''}
                </div>
    `;

    if (data.participants && data.participants.length > 0) {
        html += `
                <div class="table-responsive">
                    <table class="table table-striped table-hover table-sm">
                        <thead class="table-dark">
                            <tr>
                                <th>#</th>
                                <th>Employee Code</th>
                                <th>Employee Details</th>
                                <th>Department</th>
                                <th>Designation</th>
                                <th>Grade</th>
                                <th>Status</th>
                                ${data.participants[0] && data.participants[0].eligibility ? `
                                    <th>Max Amount</th>
                                    <th>Max Days</th>
                                ` : ''}
                            </tr>
                        </thead>
                        <tbody>
        `;
        
        data.participants.forEach((participant, index) => {
            const statusBadge = participant.is_valid 
                ? '<span class="badge badge-success"><i class="fas fa-check-circle"></i> Valid</span>'
                : '<span class="badge badge-danger"><i class="fas fa-exclamation-circle"></i> Invalid</span>';
            
            const tooltipInfo = [
                participant.email ? `Email: ${participant.email}` : '',
                participant.mobile ? `Mobile: ${participant.mobile}` : '',
                participant.branch && participant.branch.name ? `Branch: ${participant.branch.name}` : '',
                participant.division && participant.division.name ? `Division: ${participant.division.name}` : ''
            ].filter(Boolean).join(' | ');
            
            html += `
                <tr class="${participant.is_valid ? '' : 'table-warning'}" title="${tooltipInfo}">
                    <td>${index + 1}</td>
                    <td><code class="bg-light p-1">${participant.emp_code}</code></td>
                    <td>
                        <div>
                            <strong>${participant.emp_name}</strong>
                            ${participant.email ? `<br><small class="text-muted"><i class="fas fa-envelope"></i> ${participant.email}</small>` : ''}
                            ${participant.mobile ? `<br><small class="text-muted"><i class="fas fa-phone"></i> ${participant.mobile}</small>` : ''}
                        </div>
                    </td>
                    <td>
                        ${participant.department && participant.department.name 
                            ? `<span class="text-dark"><i class="fas fa-building"></i> ${participant.department.name}</span>` 
                            : '<span class="text-muted">-</span>'}
                    </td>
                    <td>
                        ${participant.designation && participant.designation.name 
                            ? `<span class="text-dark"><i class="fas fa-briefcase"></i> ${participant.designation.name}</span>` 
                            : '<span class="text-muted">-</span>'}
                    </td>
                    <td>
                        ${participant.grade && participant.grade.name 
                            ? `<span class="badge badge-info">${participant.grade.name}</span>` 
                            : '<span class="text-muted">-</span>'}
                    </td>
                    <td>${statusBadge}</td>
                    ${participant.eligibility ? `
                        <td>₹${Number(participant.eligibility.max_amount).toLocaleString('en-IN', {minimumFractionDigits: 2})}</td>
                        <td>${participant.eligibility.max_days} days</td>
                    ` : ''}
                </tr>
            `;
        });
        
        html += `
                        </tbody>
                    </table>
                </div>
        `;
    }

    let parsedOtherList = [];
    if (data.other_participants_parsed && Array.isArray(data.other_participants_parsed) && data.other_participants_parsed.length > 0) {
        parsedOtherList = data.other_participants_parsed;
    } else if (data.other_participants) {
        if (typeof data.other_participants === 'string') {
            try {
                const parsed = JSON.parse(data.other_participants);
                if (Array.isArray(parsed)) parsedOtherList = parsed;
            } catch (e) {}
        }
    }

    if (parsedOtherList.length > 0) {
        html += `
            <div class="card mt-3 border-secondary">
                <div class="card-header bg-secondary text-white py-2">
                    <h6 class="mb-0 fs-14"><i class="fas fa-user-tag me-1"></i> Other Participants (External / Non-Employees)</h6>
                </div>
                <div class="table-responsive">
                    <table class="table table-sm table-hover mb-0">
                        <thead class="table-light">
                            <tr>
                                <th style="width:50px;">#</th>
                                <th>Participant Name</th>
                                <th>Role / Category</th>
                                <th>Details / Remarks</th>
                            </tr>
                        </thead>
                        <tbody>
        `;
        parsedOtherList.forEach((other, i) => {
            const name = typeof other === 'object' ? (other.name || '-') : other;
            const category = typeof other === 'object' ? (other.category || 'Other') : 'Other';
            const details = typeof other === 'object' ? (other.details || '-') : '-';

            let badgeClass = 'bg-secondary text-white';
            const catLower = String(category).toLowerCase();
            if (catLower.includes('management')) badgeClass = 'bg-warning text-dark';
            else if (catLower.includes('retailer')) badgeClass = 'bg-info text-white';
            else if (catLower.includes('dealer')) badgeClass = 'bg-primary text-white';
            else if (catLower.includes('distributor')) badgeClass = 'bg-success text-white';
            else if (catLower.includes('consultant')) badgeClass = 'bg-purple text-white';
            else if (catLower.includes('guest')) badgeClass = 'bg-dark text-white';

            html += `
                <tr>
                    <td>${i + 1}</td>
                    <td><strong>${name}</strong></td>
                    <td><span class="badge ${badgeClass} p-1 px-2">${category}</span></td>
                    <td>${details}</td>
                </tr>
            `;
        });
        html += `
                        </tbody>
                    </table>
                </div>
            </div>
        `;
    } else if (data.other_participants && typeof data.other_participants === 'string' && data.other_participants.trim() !== '') {
        html += `
            <div class="alert alert-secondary mt-3 mb-0">
                <strong><i class="fas fa-note-sticky"></i> Other Participants:</strong>
                <p class="mb-0">${data.other_participants}</p>
            </div>
        `;
    }
    
    html += `
            </div>
        </div>
    `;
    
    document.getElementById('paxDetailsContainer').innerHTML = html;
}
</script>
