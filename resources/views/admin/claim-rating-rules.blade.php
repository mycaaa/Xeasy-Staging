@extends('layouts.app')

@section('content')
<div class="page-content">
    <div class="container-fluid">

        <div class="row mb-3">
            <div class="col-12">
                <div class="d-flex justify-content-between align-items-center">
                    <h4 class="mb-0 fw-bold" style="color:#15648C;">
                        <i class="ri-star-line me-2"></i>Claim Rating Rules — {{ $companyName }}
                    </h4>
                    <span class="badge bg-info">{{ $companyName }} Workflow</span>
                </div>
                <p class="text-muted mt-1 mb-0" style="font-size:13px;">
                    Define SLA rules for each stage. Ratings are calculated based on days taken between stages.
                </p>
            </div>
        </div>

        {{-- Workflow Diagram --}}
        <div class="card mb-3">
            <div class="card-body py-2">
                <div class="d-flex align-items-center justify-content-center gap-2 flex-wrap" style="font-size:12px;">
                    @foreach($stages as $key => $stage)
                        <span class="badge bg-primary px-3 py-2">{{ $stage['label'] }}</span>
                        @if(!$loop->last)
                            <i class="ri-arrow-right-line text-muted"></i>
                        @endif
                    @endforeach
                </div>
            </div>
        </div>
         {{-- Add/Edit Rule Form --}}
        <div class="card">
            <div class="card-header">
                <h6 class="mb-0 fw-bold"><i class="ri-add-circle-line me-1"></i>Add / Update Rule</h6>
            </div>
            <div class="card-body">
                <form id="ruleForm">
                    @csrf
                    <div class="row g-3">
                        <div class="col-md-3">
                            <label class="form-label fw-bold">Stage</label>
                            <select class="form-select" id="stageKey" name="stage_key" required>
                                <option value="">Select Stage</option>
                                @foreach($stages as $key => $stage)
                                    <option value="{{ $key }}" data-role="{{ $stage['role'] }}">
                                        {{ $stage['label'] }} ({{ ucfirst($stage['role']) }})
                                    </option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-md-2">
                            <label class="form-label fw-bold">Effective Date</label>
                            <input type="date" class="form-control" name="effective_date" id="effectiveDate" value="{{ date('Y-m-d') }}">
                        </div>
                        <div class="col-md-1">
                            <label class="form-label fw-bold">Min Claims</label>
                            <input type="number" class="form-control" name="min_claims" id="minClaims" value="0" min="0" placeholder="0">
                            <small class="text-muted">0 = no min</small>
                        </div>
                        <div class="col-md-7">
                            <label class="form-label fw-bold">Rating Thresholds (in days)</label>
                            <div class="row g-2">
                                <div class="col">
                                    <div class="input-group input-group-sm">
                                        <span class="input-group-text" style="background:#4CAF50;color:white;">⭐5</span>
                                        <input type="number" class="form-control" name="star_5_max_days" id="star5" placeholder="≤" min="0" required>
                                    </div>
                                    <small class="text-muted">≤ days</small>
                                </div>
                                <div class="col">
                                    <div class="input-group input-group-sm">
                                        <span class="input-group-text" style="background:#8BC34A;color:white;">⭐4</span>
                                        <input type="number" class="form-control" name="star_4_max_days" id="star4" placeholder="≤" min="0" required>
                                    </div>
                                    <small class="text-muted">≤ days</small>
                                </div>
                                <div class="col">
                                    <div class="input-group input-group-sm">
                                        <span class="input-group-text" style="background:#FFC107;color:white;">⭐3</span>
                                        <input type="number" class="form-control" name="star_3_max_days" id="star3" placeholder="≤" min="0" required>
                                    </div>
                                    <small class="text-muted">≤ days</small>
                                </div>
                                <div class="col">
                                    <div class="input-group input-group-sm">
                                        <span class="input-group-text" style="background:#FF9800;color:white;">⭐2</span>
                                        <input type="number" class="form-control" name="star_2_max_days" id="star2" placeholder="≤" min="0" required>
                                    </div>
                                    <small class="text-muted">≤ days</small>
                                </div>
                                <div class="col">
                                    <div class="input-group input-group-sm">
                                        <span class="input-group-text" style="background:#F44336;color:white;">⭐1</span>
                                        <input type="number" class="form-control" name="star_1_min_days" id="star1" placeholder=">" min="0" required>
                                    </div>
                                    <small class="text-muted">> days</small>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="mt-3">
                        <button type="submit" class="btn btn-primary" id="btnSaveRule">
                            <i class="ri-save-line me-1"></i>Save Rule
                        </button>
                        <button type="button" class="btn btn-secondary d-none" id="btnCancelEdit">
                            <i class="ri-close-line me-1"></i>Cancel
                        </button>
                    </div>
                </form>
            </div>
        </div>

        {{-- Existing Rules --}}
        <div class="card mb-3">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h6 class="mb-0 fw-bold">Defined Rules</h6>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-bordered table-sm align-middle mb-0">
                        <thead style="background:#15648C;color:white;">
                            <tr>
                                <th>Stage</th>
                                <th>Responsible</th>
                                <th class="text-center">⭐5 (≤ days)</th>
                                <th class="text-center">⭐4 (≤ days)</th>
                                <th class="text-center">⭐3 (≤ days)</th>
                                <th class="text-center">⭐2 (≤ days)</th>
                                <th class="text-center">⭐1 (> days)</th>
                                <th class="text-center">Min Claims</th>
                                <th class="text-center">Effective</th>
                                <th class="text-center">Status</th>
                                <th class="text-center">Actions</th>
                            </tr>
                        </thead>
                        <tbody id="rulesTableBody">
                            @forelse($rules as $rule)
                                <tr id="rule-{{ $rule->id }}">
                                    <td class="fw-semibold">{{ $rule->stage_label }}</td>
                                    <td><span class="badge bg-secondary">{{ ucfirst($rule->responsible_role) }}</span></td>
                                    <td class="text-center" style="background:#E8F5E9;">{{ $rule->star_5_max_days }}</td>
                                    <td class="text-center" style="background:#F1F8E9;">{{ $rule->star_4_max_days }}</td>
                                    <td class="text-center" style="background:#FFF9C4;">{{ $rule->star_3_max_days }}</td>
                                    <td class="text-center" style="background:#FFE0B2;">{{ $rule->star_2_max_days }}</td>
                                    <td class="text-center" style="background:#FFCDD2;">> {{ $rule->star_2_max_days }}</td>
                                    <td class="text-center"><span class="badge bg-info">{{ $rule->min_claims ?: 'Any' }}</span></td>
                                    <td class="text-center" style="font-size:11px;">{{ $rule->effective_date ? date('d-M-Y', strtotime($rule->effective_date)) : '-' }}</td>
                                    <td class="text-center">
                                        <span class="badge {{ $rule->is_active ? 'bg-success' : 'bg-secondary' }} btn-toggle-rule" data-id="{{ $rule->id }}" style="cursor:pointer;">
                                            {{ $rule->is_active ? 'Active' : 'Inactive' }}
                                        </span>
                                    </td>
                                    <td class="text-center">
                                        <button class="btn btn-sm btn-outline-primary btn-edit-rule me-1" data-id="{{ $rule->id }}" title="Edit">
                                            <i class="ri-edit-line"></i>
                                        </button>
                                        <button class="btn btn-sm btn-outline-danger btn-delete-rule" data-id="{{ $rule->id }}" title="Delete">
                                            <i class="ri-delete-bin-line"></i>
                                        </button>
                                    </td>
                                </tr>
                            @empty
                                <tr><td colspan="10" class="text-center text-muted py-3">No rules defined yet. Add rules below.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

       

        {{-- Calculation Settings --}}
        <div class="card mt-3">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h6 class="mb-0 fw-bold"><i class="ri-calendar-schedule-line me-1"></i>Calculation Schedule</h6>
                @php $setting = \App\Models\ClaimRatingSetting::getForCompany(); @endphp
                @if($setting->last_calculated_date)
                    <small class="text-muted">Last calculated: {{ $setting->last_calculated_date->format('d-M-Y') }}</small>
                @endif
            </div>
            <div class="card-body">
                <div class="row align-items-end g-3">
                    <div class="col-md-3">
                        <label class="form-label fw-bold">Calculate on Day of Month</label>
                        <select class="form-select" id="calculationDay">
                            @for($d = 1; $d <= 28; $d++)
                                <option value="{{ $d }}" {{ $setting->calculation_day == $d ? 'selected' : '' }}>{{ $d }}{{ $d == 1 ? 'st' : ($d == 2 ? 'nd' : ($d == 3 ? 'rd' : 'th')) }}</option>
                            @endfor
                        </select>
                    </div>
                    <div class="col-md-2">
                        <div class="form-check form-switch mt-4">
                            <input class="form-check-input" type="checkbox" id="autoCalculate" {{ $setting->auto_calculate ? 'checked' : '' }}>
                            <label class="form-check-label fw-bold" for="autoCalculate">Auto-calculate</label>
                        </div>
                    </div>
                    <div class="col-md-2">
                        <button class="btn btn-outline-primary btn-sm" id="btnSaveSettings">
                            <i class="ri-save-line me-1"></i>Save Settings
                        </button>
                    </div>
                    <div class="col-md-5 text-end">
                        <button class="btn btn-success" id="btnCalculateNow">
                            <i class="ri-calculator-line me-1"></i>Calculate & Store Ratings Now
                        </button>
                    </div>
                </div>
            </div>
        </div>

    </div>
</div>
@endsection

@push('scripts')
<script>
$(document).ready(function() {
    // Save rule
    $('#ruleForm').on('submit', function(e) {
        e.preventDefault();
        const formData = $(this).serialize();

        $.ajax({
            url: '{{ route("admin.claim.rating.store") }}',
            method: 'POST',
            data: formData,
            success: function(res) {
                if (res.success) {
                    Swal.fire({ toast: true, position: 'top-end', icon: 'success', title: res.message, showConfirmButton: false, timer: 2000 });
                    location.reload();
                } else {
                    Swal.fire('Error', res.message, 'error');
                }
            },
            error: function(xhr) {
                const msg = xhr.responseJSON?.message || 'Failed to save rule';
                Swal.fire('Error', msg, 'error');
            }
        });
    });

    // Edit rule — load into form
    $(document).on('click', '.btn-edit-rule', function() {
        const id = $(this).data('id');
        $.get('/admin/claim-rating/edit/' + id, function(res) {
            if (res.success) {
                const r = res.rule;
                $('#stageKey').val(r.stage_key);
                $('#star5').val(r.star_5_max_days);
                $('#star4').val(r.star_4_max_days);
                $('#star3').val(r.star_3_max_days);
                $('#star2').val(r.star_2_max_days);
                $('#star1').val(r.star_1_min_days);
                $('#effectiveDate').val(r.effective_date || '');
                $('#minClaims').val(r.min_claims || 0);
                $('#btnSaveRule').html('<i class="ri-save-line me-1"></i>Update Rule');
                $('#btnCancelEdit').removeClass('d-none');
                $('html, body').animate({ scrollTop: $('#ruleForm').offset().top - 100 }, 300);
            }
        });
    });

    // Cancel edit
    $('#btnCancelEdit').on('click', function() {
        $('#ruleForm')[0].reset();
        $('#btnSaveRule').html('<i class="ri-save-line me-1"></i>Save Rule');
        $(this).addClass('d-none');
    });

    // Toggle rule
    $(document).on('click', '.btn-toggle-rule', function() {
        const id = $(this).data('id');
        $.ajax({
            url: '/admin/claim-rating/toggle/' + id,
            method: 'POST',
            data: { _token: '{{ csrf_token() }}' },
            success: function(res) {
                location.reload();
            }
        });
    });

    // Delete rule
    $(document).on('click', '.btn-delete-rule', function() {
        const id = $(this).data('id');
        Swal.fire({
            title: 'Delete this rule?',
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#dc3545',
            confirmButtonText: 'Delete'
        }).then(function(result) {
            if (result.isConfirmed) {
                $.ajax({
                    url: '/admin/claim-rating/' + id,
                    method: 'DELETE',
                    data: { _token: '{{ csrf_token() }}' },
                    success: function(res) {
                        $('#rule-' + id).fadeOut();
                        Swal.fire({ toast: true, position: 'top-end', icon: 'success', title: 'Deleted', showConfirmButton: false, timer: 1500 });
                    }
                });
            }
        });
    });

    // Save settings
    $('#btnSaveSettings').on('click', function() {
        $.ajax({
            url: '{{ route("admin.claim.rating.settings") }}',
            method: 'POST',
            data: {
                _token: '{{ csrf_token() }}',
                calculation_day: $('#calculationDay').val(),
                auto_calculate: $('#autoCalculate').is(':checked') ? 1 : 0,
            },
            success: function(res) {
                Swal.fire({ toast: true, position: 'top-end', icon: 'success', title: res.message, showConfirmButton: false, timer: 2000 });
            }
        });
    });

    // Calculate & Store Now
    $('#btnCalculateNow').on('click', function() {
        const $btn = $(this);
        Swal.fire({
            title: 'Calculate Ratings?',
            text: 'This will calculate ratings for all claims in the current financial year and store them in the database.',
            icon: 'question',
            showCancelButton: true,
            confirmButtonText: 'Yes, Calculate',
            confirmButtonColor: '#198754',
        }).then(function(result) {
            if (!result.isConfirmed) return;
            $btn.prop('disabled', true).html('<span class="spinner-border spinner-border-sm me-1"></span>Calculating...');
            $.ajax({
                url: '{{ route("admin.claim.rating.calculate") }}',
                method: 'POST',
                data: { _token: '{{ csrf_token() }}', months: [4,5,6,7,8,9,10,11,12,1,2,3] },
                success: function(res) {
                    $btn.prop('disabled', false).html('<i class="ri-calculator-line me-1"></i>Calculate & Store Ratings Now');
                    if (res.success) {
                        Swal.fire('Done!', res.message, 'success');
                    } else {
                        Swal.fire('Error', res.message, 'error');
                    }
                },
                error: function() {
                    $btn.prop('disabled', false).html('<i class="ri-calculator-line me-1"></i>Calculate & Store Ratings Now');
                    Swal.fire('Error', 'Calculation failed', 'error');
                }
            });
        });
    });
});
</script>
@endpush
