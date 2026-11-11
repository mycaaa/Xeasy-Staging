{{-- VEH-RATE-REPORT-01 / RATE-EFF-01 --}}
@extends('layouts.app')

@section('content')
    <div class="col-md-12 h-100 p-3 mt-5">
        <div class="row card mt-2">
            <div class="col-lg-12 shadow p-3">
                <div class="d-flex justify-content-between align-items-center">
                    <h5 class="mb-1">Vehicle rates to update</h5>
                    <a href="{{ route('vehicle.rate.gaps.export') }}" class="btn btn-sm btn-success">Excel</a>
                </div>
                <p class="text-muted small mb-2">Active employees only. Rate gaps first (their running-km claims are paid at ₹0 until HR enters the rate), then Own Your Car employees without the vehicle value. Sent to HR every Monday.</p>
                @if (session('success'))<div class="alert alert-success py-1">{{ session('success') }}</div>@endif
                <form method="get" class="mb-2">
                    <select name="only" class="form-select form-select-sm d-inline-block w-auto" onchange="this.form.submit()">
                        <option value="">All ({{ count($rows) }})</option>
                        <option value="rate" @selected(request('only') === 'rate')>Rate missing</option>
                        <option value="docs" @selected(request('only') === 'docs')>Vehicle value</option>
                    </select>
                </form>
                <div class="table-responsive" style="max-height:55vh">
                    <table class="table table-sm table-bordered table-hover small">
                        <thead class="table-light"><tr>@foreach (\App\Http\Controllers\admin\VehicleRateGapsController::HEAD as $h)<th>{{ $h }}</th>@endforeach</tr></thead>
                        <tbody>
                        @forelse ($rows as $r)
                            <tr class="{{ $r['rate_missing'] ? 'table-warning' : '' }}">
                                <td>{{ $r['emp_code'] }}</td><td>{{ $r['name'] }}</td><td>{{ $r['department'] }}</td><td>{{ $r['grade'] }}</td><td>{{ $r['policy'] }}</td>
                                <td class="text-end">{{ $r['rate_2w'] ?: '' }}</td><td class="text-end">{{ $r['rate_4w'] ?: '' }}</td>
                                <td class="text-end">{{ $r['claims_2w'] }}</td><td class="text-end">{{ $r['claims_4w'] }}</td><td class="text-end">{{ $r['claims_at_zero'] }}</td>
                                <td>{{ $r['last_bill'] ? \Carbon\Carbon::parse($r['last_bill'])->format('d-m-Y') : '' }}</td><td>{{ $r['issues'] }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="12" class="text-center text-muted">Nothing to update.</td></tr>
                        @endforelse
                        </tbody>
                    </table>
                </div>

                <h6 class="mt-4 mb-1">Mails to HR about zero rates</h6>
                <p class="text-muted small mb-2">Each active employee whose 2W or 4W rate is zero is mailed to HR (Super Admin in Cc), again every {{ config('claim_rules.vehicle_rate_alert.repeat_days', 3) }} days until the rate is entered; the alert closes by itself then. Newest first.</p>
                <div class="table-responsive" style="max-height:40vh">
                    <table class="table table-sm table-bordered small" aria-label="Mails to HR about zero rates">
                        <thead class="table-light"><tr><th>Sent</th><th>Emp code</th><th>Employee</th><th>What is missing</th><th>Mail</th><th>Status</th><th>To</th><th>Cc</th><th>Alert</th></tr></thead>
                        <tbody>
                        @forelse ($alertMails ?? [] as $m)
                            <tr>
                                <td class="text-nowrap">{{ \Carbon\Carbon::parse($m->sent_at)->format('d-m-Y H:i') }}</td><td>{{ $m->emp_code }}</td><td>{{ $m->employee_name }}</td><td>{{ $m->issue }}</td>
                                <td>{{ $m->kind === 'first' ? 'First' : 'Reminder' }}</td>
                                <td>{{ $m->status === 'sent' ? 'Sent' : ($m->status === 'no_recipient' ? 'Not sent: no HR address set' : 'Failed') }}@if ($m->error && $m->status === 'failed') <span class="text-muted">({{ \Illuminate\Support\Str::limit($m->error, 60) }})</span>@endif</td>
                                <td>{{ $m->to_list }}</td><td class="small">{{ \Illuminate\Support\Str::limit($m->cc_list, 80) }}</td>
                                <td>{{ $m->alert_status === 'resolved' ? 'Rate entered' : 'Open' }}{{ $m->send_count ? ' ('.$m->send_count.' sent)' : '' }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="9">No mail yet.</td></tr>
                        @endforelse
                        </tbody>
                    </table>
                </div>

                <h6 class="mt-4 mb-1">Eligibility changes - effective date</h6>
                <p class="text-muted small mb-2">A vehicle or policy change applies to bills from its effective date (by default the day HR saved it). Change the date when the change took effect on another day. Newest first, last 120 days.</p>
                <div class="table-responsive" style="max-height:40vh">
                    <table class="table table-sm table-bordered small">
                        <thead class="table-light"><tr><th>Emp code</th><th>Employee</th><th>Policy</th><th>2W rate</th><th>4W rate</th><th>Saved on</th><th>Effective from</th><th></th></tr></thead>
                        <tbody>
                        @foreach ($changes as $c)
                            <tr>
                                <td>{{ $c->EmpCode }}</td><td>{{ $c->name }}</td><td>{{ $c->VehiclePolicy }}</td><td>{{ $c->Travel_TwoWeeKM }}</td><td>{{ $c->Travel_FourWeeKM }}</td>
                                <td>{{ \Carbon\Carbon::parse($c->EligCreatedDate)->format('d-m-Y') }}</td>
                                <td>
                                    <form method="post" action="{{ route('vehicle.rate.effective') }}" class="d-flex gap-1">
                                        @csrf
                                        <input type="hidden" name="eligibility_id" value="{{ $c->EligibilityId }}">
                                        <input type="date" name="effective_from" value="{{ $c->effective_from }}" class="form-control form-control-sm" required>
                                        <input type="text" name="remark" class="form-control form-control-sm" placeholder="Why (optional)" aria-label="Why (optional)">
                                        <button class="btn btn-sm btn-primary">Save</button>
                                    </form>
                                </td>
                                <td>{{ $c->overridden ? 'set by HR' : '' }}</td>
                            </tr>
                        @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
@endsection
