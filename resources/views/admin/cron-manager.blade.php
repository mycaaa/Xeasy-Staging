@extends('layouts.app')

@section('content')
<div class="page-content">
    <div class="container-fluid">

        <div class="row mb-3">
            <div class="col-12">
                <h4 class="fw-bold" style="color:#15648C;">
                    <i class="ri-timer-line me-2"></i>Scheduled Tasks / Cron Manager
                </h4>
                <p class="text-muted mb-0">Run scheduled commands manually or view their status.</p>
            </div>
        </div>

        {{-- Close Expired Months --}}
        <div class="card mb-3">
            <div class="card-header d-flex justify-content-between align-items-center">
                <div>
                    <h6 class="mb-0 fw-bold"><i class="ri-calendar-close-line me-1"></i>Close Expired Months</h6>
                    <small class="text-muted">Closes months older than 3 months in monthexpensefinal. Sets Status=Closed, SubPermission=N, and auto-fills DateOfSubmit if empty.</small>
                </div>
                <span class="badge bg-info">Scheduled: 15th of every month</span>
            </div>
            <div class="card-body">
                <div class="row g-3">
                    {{-- Dry Run --}}
                    <div class="col-md-4">
                        <div class="card border h-100">
                            <div class="card-body text-center">
                                <i class="ri-eye-line" style="font-size:32px;color:#0dcaf0;"></i>
                                <h6 class="fw-bold mt-2">Preview (Dry Run)</h6>
                                <p class="text-muted small">See what would be closed without making changes</p>
                                <button class="btn btn-outline-info btn-sm btn-run-cron" data-command="claims:close-expired-months --dry-run">
                                    <i class="ri-play-line me-1"></i>Run Dry
                                </button>
                            </div>
                        </div>
                    </div>

                    {{-- Run All Companies --}}
                    <div class="col-md-4">
                        <div class="card border h-100">
                            <div class="card-body text-center">
                                <i class="ri-building-2-line" style="font-size:32px;color:#198754;"></i>
                                <h6 class="fw-bold mt-2">Close All Companies</h6>
                                <p class="text-muted small">VNR + VVNR + Nursery — all years</p>
                                <button class="btn btn-success btn-sm btn-run-cron" data-command="claims:close-expired-months">
                                    <i class="ri-check-double-line me-1"></i>Run Now
                                </button>
                            </div>
                        </div>
                    </div>

                    {{-- Run Per Company --}}
                    <div class="col-md-4">
                        <div class="card border h-100">
                            <div class="card-body text-center">
                                <i class="ri-filter-line" style="font-size:32px;color:#6f42c1;"></i>
                                <h6 class="fw-bold mt-2">Close by Company</h6>
                                <p class="text-muted small">Run for a specific company only</p>
                                <div class="btn-group btn-group-sm">
                                    <button class="btn btn-outline-primary btn-run-cron" data-command="claims:close-expired-months --company=1">VNR</button>
                                    <button class="btn btn-outline-primary btn-run-cron" data-command="claims:close-expired-months --company=11">VVNR</button>
                                    <button class="btn btn-outline-primary btn-run-cron" data-command="claims:close-expired-months --company=3">Nursery</button>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                {{-- Output Console --}}
                <div class="mt-3">
                    <label class="form-label fw-bold"><i class="ri-terminal-line me-1"></i>Output</label>
                    <pre id="cronOutput" class="bg-dark text-light p-3 rounded" style="min-height:150px;max-height:400px;overflow-y:auto;font-size:12px;white-space:pre-wrap;">Ready. Click a button above to run a command.</pre>
                </div>
            </div>
        </div>

        {{-- Schedule Info --}}
        <div class="card">
            <div class="card-header">
                <h6 class="mb-0 fw-bold"><i class="ri-information-line me-1"></i>Cron Setup Info</h6>
            </div>
            <div class="card-body">
                <p>To enable automatic scheduling on your server, add this cron entry in Plesk Scheduled Tasks:</p>
                <div class="bg-light p-3 rounded border">
                    <p class="mb-1 text-muted small fw-bold">Current Project Path on Server (base_path):</p>
                    <code style="font-size:13px;" class="text-primary">{{ base_path() }}</code>
                    <hr class="my-2">
                    <p class="mb-1 text-muted small fw-bold">Recommended Plesk Cron Command:</p>
                    <code style="font-size:13px;">cd {{ base_path() }} && /opt/plesk/php/8.3/bin/php artisan schedule:run >> /dev/null 2>&1</code>
                </div>
                <p class="mt-2 text-muted small mb-0">
                    Runs Laravel's scheduler every minute. The scheduler automatically executes configured commands at their designated times (e.g., month closing on the 15th, weekly imprest automation, transfer notifications, etc.).
                </p>
            </div>
        </div>

    </div>
</div>
@endsection

@push('scripts')
<script>
$(document).ready(function() {
    $(document).on('click', '.btn-run-cron', function() {
        const $btn = $(this);
        const command = $btn.data('command');
        const $output = $('#cronOutput');

        $btn.prop('disabled', true);
        $output.text('Running: ' + command + '\nPlease wait...\n');

        $.ajax({
            url: '{{ route("admin.cron.run") }}',
            method: 'POST',
            data: { _token: '{{ csrf_token() }}', command: command },
            success: function(res) {
                $btn.prop('disabled', false);
                if (res.success) {
                    $output.text('$ ' + command + '\n\n' + res.output + '\n\n✓ Completed at ' + new Date().toLocaleString());
                } else {
                    $output.text('$ ' + command + '\n\n✗ Error: ' + res.message);
                }
            },
            error: function(xhr) {
                $btn.prop('disabled', false);
                $output.text('$ ' + command + '\n\n✗ Request failed: ' + (xhr.responseJSON?.message || xhr.statusText));
            }
        });
    });
});
</script>
@endpush
