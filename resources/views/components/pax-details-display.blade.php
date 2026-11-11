{{-- PAX Details Display Component --}}
{{-- Displays employee details, eligibility, and quota information for claims --}}

<div class="pax-details-container">
    @if(isset($paxData) && (!empty($paxData['participants']) || !empty($paxData['other_participants_parsed']) || !empty($paxData['other_participants']) || !empty($paxData['pax_count'])))
        <div class="card">
            <div class="card-header bg-info text-white">
                <h5 class="mb-0">
                    <i class="fas fa-users"></i> 
                    {{ $paxData['claim_type_name'] }} - Participant Details
                </h5>
            </div>
            <div class="card-body">
                {{-- Total Participants Count --}}
                <div class="alert alert-primary text-dark">
                    <strong>Total Participants:</strong> {{ $paxData['total_participants'] }}
                    @if($paxData['pax_count'])
                        | <strong>PAX Count:</strong> {{ $paxData['pax_count'] }}
                    @endif
                    @if($paxData['quota'])
                        | <strong>Quota (Rail Fare):</strong> <span class="badge badge-success">{{ $paxData['quota'] }}</span>
                    @endif
                </div>

                {{-- Participants Table --}}
                @if(!empty($paxData['participants']))
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
                                @if($paxData['participants'][0] && $paxData['participants'][0]['eligibility'])
                                    <th>Max Amount</th>
                                    <th>Max Days</th>
                                @endif
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($paxData['participants'] as $index => $participant)
                                @php
                                    $tooltipInfo = collect([
                                        $participant['email'] ?? null ? 'Email: ' . $participant['email'] : null,
                                        $participant['mobile'] ?? null ? 'Mobile: ' . $participant['mobile'] : null,
                                        $participant['branch']['name'] ?? null ? 'Branch: ' . $participant['branch']['name'] : null,
                                        $participant['division']['name'] ?? null ? 'Division: ' . $participant['division']['name'] : null,
                                    ])->filter()->implode(' | ');
                                @endphp
                                <tr class="{{ $participant['is_valid'] ? '' : 'table-warning' }}" 
                                    title="{{ $tooltipInfo }}" 
                                    data-bs-toggle="tooltip">
                                    <td>{{ $index + 1 }}</td>
                                    <td>
                                        <code class="bg-light p-2">{{ $participant['emp_code'] }}</code>
                                    </td>
                                    <td>
                                        <div>
                                            <strong>{{ $participant['emp_name'] }}</strong>
                                            @if($participant['email'] ?? null)
                                                <br><small class="text-muted">
                                                    <i class="fas fa-envelope"></i> {{ $participant['email'] }}
                                                </small>
                                            @endif
                                            @if($participant['mobile'] ?? null)
                                                <br><small class="text-muted">
                                                    <i class="fas fa-phone"></i> {{ $participant['mobile'] }}
                                                </small>
                                            @endif
                                        </div>
                                    </td>
                                    <td>
                                        @if(isset($participant['department']['name']))
                                            <span class="text-dark">
                                                <i class="fas fa-building"></i> {{ $participant['department']['name'] }}
                                            </span>
                                        @else
                                            <span class="text-muted">-</span>
                                        @endif
                                    </td>
                                    <td>
                                        @if(isset($participant['designation']['name']))
                                            <span class="text-dark">
                                                <i class="fas fa-briefcase"></i> {{ $participant['designation']['name'] }}
                                            </span>
                                        @else
                                            <span class="text-muted">-</span>
                                        @endif
                                    </td>
                                    <td>
                                        @if(isset($participant['grade']['name']))
                                            <span class="badge badge-info">{{ $participant['grade']['name'] }}</span>
                                        @else
                                            <span class="text-muted">-</span>
                                        @endif
                                    </td>
                                    <td>
                                        @if($participant['is_valid'])
                                            <span class="badge badge-success">
                                                <i class="fas fa-check-circle"></i> Valid
                                            </span>
                                        @else
                                            <span class="badge badge-danger">
                                                <i class="fas fa-exclamation-circle"></i> Invalid
                                            </span>
                                        @endif
                                    </td>
                                    @if($participant['eligibility'])
                                        <td>
                                            ₹{{ number_format($participant['eligibility']['max_amount'], 2) }}
                                        </td>
                                        <td>
                                            {{ $participant['eligibility']['max_days'] }} days
                                        </td>
                                    @endif
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="9" class="text-center text-muted">
                                        <i class="fas fa-info-circle"></i> No participant details available
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
                @endif

                {{-- Other Participants Table --}}
                @if(isset($paxData['other_participants_parsed']) && is_array($paxData['other_participants_parsed']) && count($paxData['other_participants_parsed']) > 0)
                    <div class="card mt-3 border-secondary">
                        <div class="card-header bg-secondary text-white py-2">
                            <h6 class="mb-0 fs-14"><i class="fas fa-user-tag me-1"></i> Other Participants (External / Non-Employees)</h6>
                        </div>
                        <div class="table-responsive">
                            <table class="table table-sm table-hover mb-0">
                                <thead class="table-light">
                                    <tr>
                                        <th style="width: 50px;">#</th>
                                        <th>Participant Name</th>
                                        <th>Role / Category</th>
                                        <th>Details / Remarks</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach($paxData['other_participants_parsed'] as $i => $other)
                                        @php
                                            $name = is_array($other) ? ($other['name'] ?? '-') : $other;
                                            $category = is_array($other) ? ($other['category'] ?? 'Other') : 'Other';
                                            $details = is_array($other) ? ($other['details'] ?? '-') : '-';
                                            $catLower = strtolower($category);
                                            $badgeClass = 'bg-secondary text-white';
                                            if (str_contains($catLower, 'management')) $badgeClass = 'bg-warning text-dark';
                                            elseif (str_contains($catLower, 'retailer')) $badgeClass = 'bg-info text-white';
                                            elseif (str_contains($catLower, 'dealer')) $badgeClass = 'bg-primary text-white';
                                            elseif (str_contains($catLower, 'distributor')) $badgeClass = 'bg-success text-white';
                                            elseif (str_contains($catLower, 'consultant')) $badgeClass = 'bg-purple text-white';
                                            elseif (str_contains($catLower, 'guest')) $badgeClass = 'bg-dark text-white';
                                        @endphp
                                        <tr>
                                            <td>{{ $i + 1 }}</td>
                                            <td><strong>{{ $name }}</strong></td>
                                            <td><span class="badge {{ $badgeClass }} p-1 px-2">{{ $category }}</span></td>
                                            <td>{{ $details }}</td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    </div>
                @elseif($paxData['other_participants'])
                    <div class="alert alert-secondary mt-3">
                        <strong><i class="fas fa-note-sticky"></i> Other Participants:</strong>
                        <p class="mb-0">{{ $paxData['other_participants'] }}</p>
                    </div>
                @endif

                {{-- Rail Fare Quota Editor (Data Punch) --}}
                @if($paxData['claim_id'] == 14 && isset($editable) && $editable)
                    <div class="card mt-3 border-warning">
                        <div class="card-header bg-warning text-dark">
                            <h6 class="mb-0"><i class="fas fa-edit"></i> Update Quota (Rail Fare)</h6>
                        </div>
                        <div class="card-body">
                            <form id="quotaUpdateForm" class="form-inline">
                                @csrf
                                <input type="hidden" name="exp_id" value="{{ $paxData['exp_id'] }}">
                                <input type="hidden" name="year_id" value="{{ $paxData['year_id'] ?? request('year_id') }}">
                                
                                <div class="form-group mr-3">
                                    <label for="newQuota" class="mr-2">New Quota:</label>
                                    <input type="text" id="newQuota" name="quota" class="form-control" 
                                           placeholder="e.g., AC, 2AC, 3AC, SL" value="{{ $paxData['quota'] ?? '' }}">
                                </div>
                                <button type="button" class="btn btn-primary" onclick="updateQuota()">
                                    <i class="fas fa-save"></i> Update Quota
                                </button>
                            </form>
                        </div>
                    </div>
                @endif

                {{-- PAX Participants Editor (Data Punch) --}}
                @if(in_array($paxData['claim_id'], [1, 17]) && isset($editable) && $editable)
                    <div class="card mt-3 border-info">
                        <div class="card-header bg-info text-white">
                            <h6 class="mb-0"><i class="fas fa-users-cog"></i> Update Participants</h6>
                        </div>
                        <div class="card-body">
                            <form id="paxUpdateForm">
                                @csrf
                                <input type="hidden" name="exp_id" value="{{ $paxData['exp_id'] }}">
                                <input type="hidden" name="year_id" value="{{ $paxData['year_id'] ?? request('year_id') }}">
                                
                                <div class="form-group">
                                    <label for="participantCodes">Employee Codes (comma-separated):</label>
                                    <input type="text" id="participantCodes" name="participants" class="form-control" 
                                           placeholder="e.g., E001, E002, E003"
                                           value="{{ implode(', ', array_map(fn($p) => $p['emp_code'], $paxData['participants'])) }}">
                                </div>
                                
                                <div class="form-group">
                                    <label for="otherParticipants">Other Participants (Notes):</label>
                                    <textarea id="otherParticipants" name="other_participants" class="form-control" rows="2"
                                              placeholder="Any additional participant details...">{{ $paxData['other_participants'] }}</textarea>
                                </div>
                                
                                <button type="button" class="btn btn-primary" onclick="updatePaxDetails()">
                                    <i class="fas fa-save"></i> Update Participants
                                </button>
                            </form>
                        </div>
                    </div>
                @endif
            </div>
        </div>
    @else
        <div class="alert alert-info">
            <i class="fas fa-info-circle"></i> No PAX details found for this claim.
        </div>
    @endif
</div>

<script>
function updateQuota() {
    const form = document.getElementById('quotaUpdateForm');
    const formData = new FormData(form);
    
    fetch('{{ route("api.pax.update-quota") }}', {
        method: 'POST',
        headers: {
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
            'Accept': 'application/json'
        },
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            alert('Quota updated successfully!');
            location.reload();
        } else {
            alert('Error: ' + (data.error || 'Failed to update quota'));
        }
    })
    .catch(error => {
        console.error('Error:', error);
        alert('An error occurred while updating quota');
    });
}

function updatePaxDetails() {
    const form = document.getElementById('paxUpdateForm');
    const participantCodes = document.getElementById('participantCodes').value
        .split(',')
        .map(code => code.trim())
        .filter(code => code.length > 0);
    
    if (participantCodes.length === 0) {
        alert('Please enter at least one employee code');
        return;
    }
    
    const formData = new FormData(form);
    formData.set('participants', JSON.stringify(participantCodes));
    
    fetch('{{ route("api.pax.update-participants") }}', {
        method: 'POST',
        headers: {
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
            'Accept': 'application/json'
        },
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            alert('Participants updated successfully!');
            location.reload();
        } else {
            alert('Error: ' + (data.error || 'Failed to update participants'));
        }
    })
    .catch(error => {
        console.error('Error:', error);
        alert('An error occurred while updating participants');
    });
}
</script>

<style>
.pax-details-container {
    margin: 20px 0;
}

.pax-details-container .table {
    margin-bottom: 0;
}

.pax-details-container .badge {
    font-size: 0.85rem;
    padding: 0.5rem 0.75rem;
}

.pax-details-container code {
    font-weight: bold;
    color: #d63384;
}

.pax-details-container tr[title]:hover {
    cursor: help;
}
</style>

<script>
// Initialize Bootstrap tooltips for PAX details
document.addEventListener('DOMContentLoaded', function() {
    const tooltipTriggerList = document.querySelectorAll('[data-bs-toggle="tooltip"]');
    if (typeof bootstrap !== 'undefined' && bootstrap.Tooltip) {
        [...tooltipTriggerList].map(tooltipTriggerEl => new bootstrap.Tooltip(tooltipTriggerEl));
    }
});
</script>
