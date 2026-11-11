@extends('layouts.app')
@section('content')
<div class="page-content">
    <div class="container-fluid">

        {{-- Page Header --}}
        <div class="row mt-3">
            <div class="col-12">
                <div class="page-title-box d-sm-flex align-items-center justify-content-between">
                    <h4 class="mb-sm-0">Duplicate Expense Records</h4>
                    <div class="page-title-right">
                        <ol class="breadcrumb m-0">
                            <li class="breadcrumb-item"><a href="{{ url('/') }}">Home</a></li>
                            <li class="breadcrumb-item active">Duplicate Expenses</li>
                        </ol>
                    </div>
                </div>
            </div>
        </div>

        {{-- Summary Cards --}}
        {{-- <div class="row">
            <div class="col-xl-3 col-md-6">
                <div class="card card-animate">
                    <div class="card-body">
                        <div class="d-flex align-items-center">
                            <div class="flex-grow-1">
                                <p class="text-uppercase fw-medium text-muted mb-0">Duplicate Groups</p>
                            </div>
                        </div>
                        <div class="d-flex align-items-end justify-content-between mt-3">
                            <div>
                                <h4 class="fs-22 fw-semibold mb-0" id="totalGroups">{{ count($duplicateDetails) }}</h4>
                            </div>
                            <div class="avatar-sm flex-shrink-0">
                                <span class="avatar-title bg-warning-subtle rounded fs-3">
                                    <i class="ri-file-copy-2-line text-warning"></i>
                                </span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-xl-3 col-md-6">
                <div class="card card-animate">
                    <div class="card-body">
                        <div class="d-flex align-items-center">
                            <div class="flex-grow-1">
                                <p class="text-uppercase fw-medium text-muted mb-0">Total Duplicate Records</p>
                            </div>
                        </div>
                        <div class="d-flex align-items-end justify-content-between mt-3">
                            <div>
                                <h4 class="fs-22 fw-semibold mb-0" id="totalRecords">{{ $totalDuplicateRecords }}</h4>
                            </div>
                            <div class="avatar-sm flex-shrink-0">
                                <span class="avatar-title bg-danger-subtle rounded fs-3">
                                    <i class="ri-spam-2-line text-danger"></i>
                                </span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-xl-3 col-md-6">
                <div class="card card-animate">
                    <div class="card-body">
                        <div class="d-flex align-items-center">
                            <div class="flex-grow-1">
                                <p class="text-uppercase fw-medium text-muted mb-0">Year ID</p>
                            </div>
                        </div>
                        <div class="d-flex align-items-end justify-content-between mt-3">
                            <div>
                                <h4 class="fs-22 fw-semibold mb-0">{{ $yearId }}</h4>
                            </div>
                            <div class="avatar-sm flex-shrink-0">
                                <span class="avatar-title bg-info-subtle rounded fs-3">
                                    <i class="ri-calendar-line text-info"></i>
                                </span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-xl-3 col-md-6">
                <div class="card card-animate">
                    <div class="card-body">
                        <div class="d-flex align-items-center">
                            <div class="flex-grow-1">
                                <p class="text-uppercase fw-medium text-muted mb-0">Selected</p>
                            </div>
                        </div>
                        <div class="d-flex align-items-end justify-content-between mt-3">
                            <div>
                                <h4 class="fs-22 fw-semibold mb-0" id="selectedCount">0</h4>
                            </div>
                            <div class="avatar-sm flex-shrink-0">
                                <span class="avatar-title bg-success-subtle rounded fs-3">
                                    <i class="ri-checkbox-multiple-line text-success"></i>
                                </span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div> --}}

        {{-- Bulk Action Bar --}}
        <div class="row" id="bulkActionBar" style="display:none;">
            <div class="col-12">
                <div class="card bg-light border-warning mb-3">
                    <div class="card-body py-2">
                        <div class="d-flex align-items-center justify-content-between flex-wrap gap-2">
                            <div class="d-flex align-items-center gap-2">
                                <i class="ri-checkbox-multiple-line text-warning fs-20"></i>
                                <span class="fw-medium"><span id="bulkSelectedText">0</span> record(s) selected</span>
                            </div>
                            <div class="d-flex gap-2">
                                <button type="button" class="btn btn-sm btn-outline-secondary" id="btnClearSelection">
                                    <i class="ri-close-line"></i> Clear Selection
                                </button>
                                <button type="button" class="btn btn-sm btn-danger" id="btnBulkDelete">
                                    <i class="ri-delete-bin-5-line"></i> Delete Selected
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        {{-- Main Content --}}
        <div class="row">
            <div class="col-12">
                @if(count($duplicateDetails) > 0)
                    @foreach($duplicateDetails as $key => $group)
                    <div class="card mb-3" id="group-{{ $group['expId'] }}">
                        <div class="card-header d-flex align-items-center justify-content-between"
                             style="cursor:pointer; background:#f8f9fa;"
                             data-bs-toggle="collapse" data-bs-target="#collapse-{{ $key }}">
                            <div class="d-flex align-items-center gap-2">
                                <input type="checkbox" class="form-check-input select-group-checkbox"
                                       data-group="{{ $key }}"
                                       title="Select all in this group"
                                       onclick="event.stopPropagation();">
                                <span class="badge bg-warning text-dark">{{ $group['count'] }} Duplicates</span>
                                <h6 class="mb-0">Expense ID: <code>{{ $group['expId'] }}</code></h6>
                                @php
                                    $firstRec = $group['records']->first();
                                @endphp
                                @if($firstRec && $firstRec->ClaimStatus)
                                    <span class="badge bg-info-subtle text-info">Status: {{ $firstRec->ClaimStatus }}</span>
                                @endif
                                @if($firstRec && $firstRec->ClaimBillDate)
                                    <span class="text-muted small">Bill Date: {{ $firstRec->ClaimBillDate }}</span>
                                @endif
                            </div>
                            <i class="ri-arrow-down-s-line fs-18 text-muted"></i>
                        </div>
                        <div id="collapse-{{ $key }}" class="collapse">
                            <div class="card-body p-0">
                                <div class="table-responsive">
                                    <table class="table table-hover table-striped table-bordered align-middle mb-0" style="font-size:12px;">
                                        <thead class="table-light">
                                            <tr>
                                                <th style="width:40px;" class="text-center">
                                                    <input type="checkbox" class="form-check-input select-group-all"
                                                           data-group="{{ $key }}">
                                                </th>
                                                <th style="width:80px;" class="text-center">DID</th>
                                                <th style="width:80px;" class="text-center">Exp ID</th>
                                                <th>Document</th>
                                                <th>City</th>
                                                <th>Hotel / Vendor</th>
                                                <th>Bill No</th>
                                                <th>Bill Date</th>
                                                <th>Journey</th>
                                                <th class="text-end">Cal Amt</th>
                                                <th class="text-end">Claimed Amt</th>
                                                <th class="text-end">Filled Amt</th>
                                                <th class="text-end">Verified Amt</th>
                                                <th class="text-end">Approved Amt</th>
                                                <th>Created</th>
                                                <th style="width:80px;" class="text-center">Action</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            @foreach($group['records'] as $record)
                                            <tr id="row-{{ $record->did }}">
                                                <td class="text-center">
                                                    <input type="checkbox"
                                                           class="form-check-input row-checkbox"
                                                           data-did="{{ $record->did }}"
                                                           data-group="{{ $key }}">
                                                </td>
                                                <td class="text-center"><code>{{ $record->did }}</code></td>
                                                <td class="text-center"><code>{{ $record->ExpId }}</code></td>
                                                <td>{{ $record->DocName ?? '-' }}</td>
                                                <td>{{ $record->CityName ?? '-' }}</td>
                                                <td>{{ $record->HotelName ?? '-' }}</td>
                                                <td>{{ $record->BillNo ?? '-' }}</td>
                                                <td>{{ $record->BillDate ?? '-' }}</td>
                                                <td>
                                                    @if($record->JourneyFrom || $record->JourneyUpto)
                                                        {{ $record->JourneyFrom ?? '' }} → {{ $record->JourneyUpto ?? '' }}
                                                    @else
                                                        -
                                                    @endif
                                                </td>
                                                <td class="text-end">{{ $record->CalAmount ? number_format($record->CalAmount, 2) : '-' }}</td>
                                                <td class="text-end">{{ $record->Amount ? number_format($record->Amount, 2) : '-' }}</td>
                                                <td class="text-end">{{ $record->FilledTAmt ? number_format($record->FilledTAmt, 2) : '-' }}</td>
                                                <td class="text-end">{{ $record->VerifyTAmt ? number_format($record->VerifyTAmt, 2) : '-' }}</td>
                                                <td class="text-end">{{ $record->ApprTAmt ? number_format($record->ApprTAmt, 2) : '-' }}</td>
                                                <td>{{ $record->CrDate ?? '-' }}</td>
                                                <td class="text-center">
                                                    <button type="button"
                                                            class="btn btn-sm btn-soft-danger delete-single-btn"
                                                            data-did="{{ $record->did }}"
                                                            title="Delete DID: {{ $record->did }}">
                                                        <i class="ri-delete-bin-line"></i>
                                                    </button>
                                                </td>
                                            </tr>
                                            @endforeach
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                    </div>
                    @endforeach
                @else
                    <div class="card">
                        <div class="card-body text-center py-5">
                            <lord-icon src="https://cdn.lordicon.com/msoeawqm.json" trigger="loop"
                                colors="primary:#405189,secondary:#0ab39c" style="width:75px;height:75px"></lord-icon>
                            <h5 class="mt-3">No Duplicates Found</h5>
                            <p class="text-muted mb-0">All expense fill data records are unique. No action needed.</p>
                        </div>
                    </div>
                @endif
            </div>
        </div>

    </div>
</div>
@endsection

@push('scripts')
<script>
$(document).ready(function() {
    const csrfToken = $('meta[name="csrf-token"]').attr('content');

    // --- Selection Management ---
    function getSelectedDids() {
        let dids = [];
        $('.row-checkbox:checked').each(function() {
            dids.push(parseInt($(this).data('did')));
        });
        return dids;
    }

    function updateSelectionUI() {
        const count = getSelectedDids().length;
        $('#selectedCount').text(count);
        $('#bulkSelectedText').text(count);
        if (count > 0) {
            $('#bulkActionBar').slideDown(200);
        } else {
            $('#bulkActionBar').slideUp(200);
        }
    }

    // Individual row checkbox
    $(document).on('change', '.row-checkbox', function() {
        const group = $(this).data('group');
        const allInGroup = $(`.row-checkbox[data-group="${group}"]`);
        const checkedInGroup = allInGroup.filter(':checked');
        $(`.select-group-all[data-group="${group}"]`).prop('checked', allInGroup.length === checkedInGroup.length);
        $(`.select-group-checkbox[data-group="${group}"]`).prop('checked', allInGroup.length === checkedInGroup.length);
        updateSelectionUI();
    });

    // Select all in group (inside table header)
    $(document).on('change', '.select-group-all', function() {
        const group = $(this).data('group');
        const isChecked = $(this).is(':checked');
        $(`.row-checkbox[data-group="${group}"]`).prop('checked', isChecked);
        $(`.select-group-checkbox[data-group="${group}"]`).prop('checked', isChecked);
        updateSelectionUI();
    });

    // Select group checkbox (in card header)
    $(document).on('change', '.select-group-checkbox', function() {
        const group = $(this).data('group');
        const isChecked = $(this).is(':checked');
        $(`.row-checkbox[data-group="${group}"]`).prop('checked', isChecked);
        $(`.select-group-all[data-group="${group}"]`).prop('checked', isChecked);
        updateSelectionUI();
    });

    // Clear selection
    $('#btnClearSelection').on('click', function() {
        $('.row-checkbox, .select-group-all, .select-group-checkbox').prop('checked', false);
        updateSelectionUI();
    });

    // --- Delete Single Record ---
    $(document).on('click', '.delete-single-btn', function(e) {
        e.preventDefault();
        const btn = $(this);
        const did = btn.data('did');

        if (!confirm('Are you sure you want to delete record DID: ' + did + '?')) {
            return;
        }

        btn.prop('disabled', true).html('<i class="ri-loader-4-line ri-spin"></i>');

        $.ajax({
            url: '{{ route("duplicate-expense.destroy") }}',
            method: 'DELETE',
            headers: {
                'X-CSRF-TOKEN': csrfToken,
                'Content-Type': 'application/json',
                'Accept': 'application/json'
            },
            data: JSON.stringify({ did: did }),
            dataType: 'json',
            success: function(response) {
                if (response.status === 'success') {
                    $('#row-' + did).fadeOut(300, function() {
                        $(this).remove();
                        checkEmptyGroups();
                    });
                    showNotification('success', response.message);
                } else {
                    showNotification('error', response.message || 'Error deleting record.');
                    btn.prop('disabled', false).html('<i class="ri-delete-bin-line"></i>');
                }
            },
            error: function(xhr) {
                let msg = 'Error deleting record.';
                if (xhr.responseJSON && xhr.responseJSON.message) {
                    msg = xhr.responseJSON.message;
                }
                showNotification('error', msg);
                btn.prop('disabled', false).html('<i class="ri-delete-bin-line"></i>');
            }
        });
    });

    // --- Bulk Delete ---
    $('#btnBulkDelete').on('click', function() {
        const dids = getSelectedDids();
        if (dids.length === 0) return;

        if (!confirm('Are you sure you want to delete ' + dids.length + ' selected record(s)? This action cannot be undone.')) {
            return;
        }

        const btn = $(this);
        btn.prop('disabled', true).html('<i class="ri-loader-4-line ri-spin"></i> Deleting...');

        $.ajax({
            url: '{{ route("duplicate-expense.bulk-destroy") }}',
            method: 'DELETE',
            headers: {
                'X-CSRF-TOKEN': csrfToken,
                'Content-Type': 'application/json',
                'Accept': 'application/json'
            },
            data: JSON.stringify({ dids: dids }),
            dataType: 'json',
            success: function(response) {
                if (response.status === 'success') {
                    dids.forEach(function(did) {
                        $('#row-' + did).fadeOut(200, function() {
                            $(this).remove();
                        });
                    });
                    setTimeout(function() {
                        checkEmptyGroups();
                        updateSelectionUI();
                    }, 400);
                    showNotification('success', response.message);
                } else {
                    showNotification('error', response.message || 'Error during bulk delete.');
                }
                btn.prop('disabled', false).html('<i class="ri-delete-bin-5-line"></i> Delete Selected');
            },
            error: function(xhr) {
                let msg = 'Error during bulk delete.';
                if (xhr.responseJSON && xhr.responseJSON.message) {
                    msg = xhr.responseJSON.message;
                }
                showNotification('error', msg);
                btn.prop('disabled', false).html('<i class="ri-delete-bin-5-line"></i> Delete Selected');
            }
        });
    });

    // --- Helpers ---
    function checkEmptyGroups() {
        $('.card[id^="group-"]').each(function() {
            const rowCount = $(this).find('tbody tr').length;
            // If 0 or 1 row remains, it's no longer a duplicate group — remove it
            if (rowCount <= 1) {
                $(this).fadeOut(300, function() { $(this).remove(); });
            }
        });
        // Update counters
        let remainingGroups = $('.card[id^="group-"]:visible').length;
        let remainingRecords = $('tbody tr[id^="row-"]:visible').length;
        $('#totalGroups').text(remainingGroups);
        $('#totalRecords').text(remainingRecords);
    }

    function showNotification(type, message) {
        const bgClass = type === 'success' ? 'bg-success' : 'bg-danger';
        const icon = type === 'success' ? 'ri-check-line' : 'ri-error-warning-line';

        const toast = $(`
            <div class="position-fixed top-0 end-0 p-3" style="z-index:9999;">
                <div class="toast show align-items-center text-white ${bgClass} border-0" role="alert">
                    <div class="d-flex">
                        <div class="toast-body">
                            <i class="${icon} me-1"></i> ${message}
                        </div>
                        <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast"></button>
                    </div>
                </div>
            </div>
        `);

        $('body').append(toast);
        setTimeout(function() { toast.fadeOut(300, function() { $(this).remove(); }); }, 4000);
    }
});
</script>
@endpush
