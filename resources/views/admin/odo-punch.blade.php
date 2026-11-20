{{-- ODO-PUNCH-01 (20 Nov 2026, tracker tab "Decision for You" #3): odometer readings changed by the employee --}}
@extends('layouts.app')
@section('content')
<div class="page-content"><div class="container-fluid">
    <div class="d-flex flex-wrap align-items-center gap-2 mb-2">
        <h5 class="mb-0"><i class="ri-dashboard-3-line"></i> Odometer readings changed by employee</h5>
        <span class="small text-muted">Readings the system read from the photos go straight to verification. These were typed or changed by the employee: check them against the photos, then confirm or correct. Until then the claim cannot be verified.</span>
    </div>
    @if (session('success'))<div class="alert alert-success py-2 small">{{ session('success') }}</div>@endif
    @if (session('error'))<div class="alert alert-danger py-2 small">{{ session('error') }}</div>@endif
    <ul class="nav nav-tabs mb-2">
        <li class="nav-item"><a class="nav-link {{ $tab === 'pending' ? 'active' : '' }}" href="{{ route('data.punch.odo', ['tab' => 'pending']) }}">To punch <span class="badge bg-warning text-dark">{{ $counts['pending'] }}</span></a></li>
        <li class="nav-item"><a class="nav-link {{ $tab === 'done' ? 'active' : '' }}" href="{{ route('data.punch.odo', ['tab' => 'done']) }}">Punched</a></li>
    </ul>
    <div class="card"><div class="table-responsive">
        <table class="table table-sm align-middle mb-0">
            <thead><tr><th>Employee</th><th>Date</th><th>Photos</th><th>System read</th><th>Employee entered</th><th>Why here</th><th style="min-width:300px">{{ $tab === 'pending' ? 'Punch' : 'Result' }}</th></tr></thead>
            <tbody>
            @forelse ($rows as $r)
                @php($c = $claims[$r->exp_id] ?? null)
                @php($e = $emps[$r->emp_id] ?? null)
                <tr>
                    <td>{{ $e->EmpCode ?? $r->emp_id }} {{ $e->name ?? '' }}<div class="small text-muted">Claim {{ $r->exp_id }}</div></td>
                    <td class="text-nowrap">{{ \Carbon\Carbon::parse($r->bill_date)->format('d-m-Y') }}</td>
                    <td class="text-nowrap">
                        @if ($c && ($u = $img($c->opening_filepath, $r->emp_id)))<a href="{{ $u }}" target="_blank" rel="noopener">Opening</a>@endif
                        @if ($c && ($u = $img($c->closing_filepath, $r->emp_id)))<a href="{{ $u }}" target="_blank" rel="noopener" class="ms-2">Closing</a>@endif
                    </td>
                    <td class="small">{{ $r->ocr_opening ?? '-' }} / {{ $r->ocr_closing ?? '-' }}</td>
                    <td class="small fw-semibold">{{ $r->entered_opening }} / {{ $r->entered_closing }} <span class="text-muted">({{ $r->entered_closing - $r->entered_opening }} km)</span></td>
                    <td class="small">{{ $r->reason }}</td>
                    <td>
                        @if ($tab === 'pending')
                            <form method="post" action="{{ route('data.punch.odo.save', $r->id) }}" class="d-flex flex-wrap gap-1 align-items-end">@csrf
                                <div><label class="small mb-0" for="o{{ $r->id }}">Opening</label><input id="o{{ $r->id }}" name="opening" type="number" min="0" required value="{{ $c->odomtr_opening ?? $r->entered_opening }}" class="form-control form-control-sm" style="width:100px"></div>
                                <div><label class="small mb-0" for="c{{ $r->id }}">Closing</label><input id="c{{ $r->id }}" name="closing" type="number" min="0" required value="{{ $c->odomtr_closing ?? $r->entered_closing }}" class="form-control form-control-sm" style="width:100px"></div>
                                <div><label class="small mb-0" for="r{{ $r->id }}">Remark</label><input id="r{{ $r->id }}" name="remark" maxlength="255" class="form-control form-control-sm" style="width:130px"></div>
                                <button class="btn btn-sm btn-primary">Save punch</button>
                            </form>
                        @else
                            <span class="badge {{ $r->status === 'punched' ? 'bg-success' : 'bg-secondary' }}">{{ $r->status === 'punched' ? 'Punched' : 'Not needed' }}</span>
                            <span class="small">{{ $r->punched_opening }} / {{ $r->punched_closing }} {{ $r->punched_at ? \Carbon\Carbon::parse($r->punched_at)->format('d-m-Y H:i') : '' }} {{ $r->remark }}</span>
                        @endif
                    </td>
                </tr>
            @empty
                <tr><td colspan="7" class="text-center text-muted py-4">{{ $tab === 'pending' ? 'No odometer readings waiting for punching.' : 'Nothing punched yet.' }}</td></tr>
            @endforelse
            </tbody>
        </table>
    </div></div>
    <div class="mt-2">{{ $rows->links() }}</div>
</div></div>
@endsection
