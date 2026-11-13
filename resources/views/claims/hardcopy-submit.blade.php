{{-- HARDCOPY-01: Submit Hardcopy Bills (design of 5 Nov 2026) --}}
@extends('layouts.app')

@section('content')
<style>
    .hc-card { border: 1px solid #dfe5ee; border-radius: 10px; overflow: hidden; }
    .hc-head { background: #dde6ff; color: #1e3a8a; padding: 10px 16px; font-weight: 600; font-size: 1.05rem; }
    .hc-note { background: #fff6d6; border: 1px solid #f0d78c; border-radius: 8px; padding: 10px 12px; font-size: .85rem; }
    .hc-panel { display: none; }
    .hc-panel.active { display: block; }
</style>
<div class="col-md-12 h-100 p-3 mt-5">
    <div class="row card mt-2">
        <div class="col-lg-12 shadow p-3">
            <h5 class="mb-0">Submit Hardcopy Bills</h5>
            <p class="text-muted small mb-3">Select the month and how you sent the bills. Only the fields for that way appear. Receipt at HO is confirmed separately.</p>

            @if (session('success'))
                <div class="alert alert-success py-2">{{ session('success') }}</div>
            @endif
            @if (isset($errors) && $errors->any())
                <div class="alert alert-danger py-2">{{ $errors->first() }}</div>
            @endif

            @if ($months->isEmpty())
                <div class="alert alert-info">You have no submitted month yet. Submit the month's claims first, then record the hard copy here.</div>
            @else
            <form method="post" action="{{ route('hardcopy.store') }}" enctype="multipart/form-data" class="row g-3" id="hcForm">
                @csrf
                <div class="col-md-4">
                    <label class="form-label small fw-semibold">Claim month <span class="text-danger">*</span></label>
                    <select name="month" id="hcMonth" class="form-select" required>
                        @foreach ($months as $m)
                            @php $received = ! empty($m->RecevingDate) && \App\Support\ZeroDate::has($m->RecevingDate); @endphp
                            <option value="{{ $m->Month }}" @selected((string) old('month') === (string) $m->Month) @disabled($received)>
                                {{ date('F', mktime(0, 0, 0, (int) $m->Month, 1)) }}
                                @if ($received) (received at HO) @elseif (! empty($m->PostDate) && \App\Support\ZeroDate::has($m->PostDate)) (sent {{ \Carbon\Carbon::parse($m->PostDate)->format('d-m-Y') }}) @endif
                            </option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-4 d-flex align-items-end">
                    {{-- HC-SUMMARY-01 --}}
                    <a class="btn btn-outline-primary" id="hcSummaryBtn" target="_blank" href="{{ route('hardcopy.summary', ['month' => $months->first()->Month, 'print' => 1]) }}"><i class="ri-printer-line"></i> Print summary sheet</a>
                </div>
                <div class="col-md-4">
                    <label class="form-label small fw-semibold">Submission mode <span class="text-danger">*</span></label>
                    <select name="mode" id="hcMode" class="form-select" required>
                        @foreach ($modes as $k => $label)
                            <option value="{{ $k }}" @selected(old('mode', 'dms') === $k)>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>

                <div class="col-12">
                    {{-- In person to DMS --}}
                    <div class="hc-card hc-panel" data-mode="dms">
                        <div class="hc-head">Submitted in person to DMS</div>
                        <div class="p-3 row g-3">
                            <div class="col-md-6">
                                <label class="form-label small fw-semibold">Handed over to <span class="text-danger">*</span></label>
                                <input type="text" name="handed_to" value="{{ old('handed_to') }}" class="form-control" placeholder="Enter the person's name" aria-label="Enter the person's name" maxlength="120">
                                <div class="form-text">Enter the name of the person who received the bills.</div>
                            </div>
                            <div class="col-md-3">
                                <label class="form-label small fw-semibold">Handover date</label>
                                <input type="date" name="handover_date" value="{{ old('handover_date', now()->toDateString()) }}" max="{{ now()->toDateString() }}" class="form-control hc-dms-date">
                            </div>
                        </div>
                    </div>

                    {{-- Courier / Post --}}
                    <div class="hc-card hc-panel" data-mode="courier">
                        <div class="hc-head">Courier / Post</div>
                        <div class="p-3 row g-3">
                            <div class="col-12">
                                <div class="hc-note">
                                    <div class="fw-semibold mb-1"><i class="ri-information-line"></i> Before dispatch to HO</div>
                                    <div><b>To:</b> DMS Department, HO</div>
                                    <div><b>From:</b> your name, employee code and address</div>
                                    <div><b>Envelope title:</b> Expense Claim – Month / Year</div>
                                    <div>Arrange bills date-wise; attach the summary sheet at the front (Print summary sheet, above). For combined dispatches, list the employee codes on the back of the envelope (recommended).</div>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <label class="form-label small fw-semibold">Dispatch date <span class="text-danger">*</span></label>
                                <input type="date" name="dispatch_date" value="{{ old('dispatch_date', now()->toDateString()) }}" max="{{ now()->toDateString() }}" class="form-control">
                            </div>
                            <div class="col-md-3">
                                <label class="form-label small fw-semibold">Service provider <span class="text-danger">*</span></label>
                                <select name="provider" id="hcProvider" class="form-select">
                                    <option value="">Select courier / postal service</option>
                                    @foreach ($providers as $p)
                                        <option value="{{ $p }}" @selected(old('provider') === $p)>{{ $p }}</option>
                                    @endforeach
                                </select>
                                <div class="form-text">Choose Other to enter a name.</div>
                            </div>
                            <div class="col-md-3" id="hcProviderOther" style="display:none">
                                <label class="form-label small fw-semibold">Service name <span class="text-danger">*</span></label>
                                <input type="text" name="provider_other" value="{{ old('provider_other') }}" class="form-control" maxlength="80">
                            </div>
                            <div class="col-md-3">
                                <label class="form-label small fw-semibold">Tracking number <span class="text-danger">*</span></label>
                                <input type="text" name="tracking_no" value="{{ old('tracking_no') }}" class="form-control" placeholder="Tracking / consignment number" aria-label="Tracking / consignment number" maxlength="60">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label small fw-semibold">Dispatch receipt <span class="text-muted fw-normal">(optional)</span></label>
                                <input type="file" name="file" class="form-control hc-file" accept=".pdf,.jpg,.jpeg,.png">
                            </div>
                        </div>
                    </div>

                    {{-- Reporting manager --}}
                    <div class="hc-card hc-panel" data-mode="manager">
                        <div class="hc-head">Reporting Manager</div>
                        <div class="p-3 row g-3">
                            <div class="col-md-3">
                                <label class="form-label small fw-semibold">Handover date <span class="text-danger">*</span></label>
                                <input type="date" name="handover_date" value="{{ old('handover_date', now()->toDateString()) }}" max="{{ now()->toDateString() }}" class="form-control hc-rm-date">
                            </div>
                            <div class="col-md-5">
                                <label class="form-label small fw-semibold">Reporting Manager</label>
                                <input type="text" class="form-control" value="{{ $rm ? $rm['name'].' ('.$rm['code'].')' : 'Not set in the employee master' }}" readonly>
                                <div class="form-text">Filled from the employee master.</div>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label small fw-semibold">Acknowledgement <span class="text-muted fw-normal">(optional)</span></label>
                                <input type="file" name="file" class="form-control hc-file" accept=".pdf,.jpg,.jpeg,.png">
                            </div>
                        </div>
                    </div>
                </div>

                <div class="col-12">
                    <button class="btn btn-primary px-4" id="hcSave">Save Handover Details</button>
                </div>
            </form>
            <div class="alert alert-info small mt-3 mb-0"><i class="ri-information-line"></i> Handover or dispatch recorded here &bull; receipt at HO is confirmed separately by DMS.</div>
            @endif

            @if ($history->isNotEmpty())
                <h6 class="mt-4">Recorded so far</h6>
                <div class="table-responsive">
                    <table class="table table-sm table-bordered small">
                        <thead class="table-light"><tr><th>Saved on</th><th>Month</th><th>Mode</th><th>Date</th><th>Details</th><th>File</th></tr></thead>
                        <tbody>
                        @foreach ($history as $h)
                            <tr>
                                <td class="text-nowrap">{{ \Carbon\Carbon::parse($h->created_at)->format('d-m-Y H:i') }}</td>
                                <td>{{ date('F', mktime(0, 0, 0, (int) $h->month, 1)) }}</td>
                                <td>{{ $modes[$h->mode] ?? $h->mode }}</td>
                                <td>{{ \Carbon\Carbon::parse($h->submitted_on)->format('d-m-Y') }}</td>
                                <td>{{ $h->mode === 'dms' ? 'Handed to '.$h->handed_to : ($h->mode === 'courier' ? $h->provider.' · '.$h->tracking_no : 'RM '.$h->manager_name) }}</td>
                                <td>@if ($h->file_path)<a href="{{ route('hardcopy.file', $h->id) }}" target="_blank">View</a>@endif</td>
                            </tr>
                        @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </div>
    </div>
</div>
<script>
    (function () {
        const mode = document.getElementById('hcMode');
        if (!mode) return;
        const save = document.getElementById('hcSave');
        const provider = document.getElementById('hcProvider');
        function show() {
            document.querySelectorAll('.hc-panel').forEach(function (p) {
                const on = p.dataset.mode === mode.value;
                p.classList.toggle('active', on);
                // only the visible panel's fields are sent
                p.querySelectorAll('input, select').forEach(function (el) { el.disabled = !on; });
            });
            save.textContent = mode.value === 'courier' ? 'Save Dispatch Details' : 'Save Handover Details';
        }
        function other() {
            document.getElementById('hcProviderOther').style.display = provider.value === 'Other' ? '' : 'none';
        }
        mode.addEventListener('change', show);
        provider.addEventListener('change', other);
        show(); other();
    })();
</script>
<script>
document.addEventListener('DOMContentLoaded', function () {
    var sel = document.getElementById('hcMonth'), a = document.getElementById('hcSummaryBtn');
    if (!sel || !a) return;
    var set = function () { a.href = "{{ route('hardcopy.summary') }}?month=" + encodeURIComponent(sel.value) + "&print=1"; };
    sel.addEventListener('change', set); set();
});
</script>
@endsection
