{{-- FNF-RULES-01 (21 Nov 2026): the rules of the F&F working, with effective dates --}}
@extends('layouts.app')
@section('content')
<div class="page-content"><div class="container-fluid">
    <div class="d-flex flex-wrap align-items-center gap-2 mb-2">
        <h5 class="mb-0"><i class="ri-scales-3-line"></i> F&amp;F Rules</h5>
        <span class="small text-muted">The F&amp;F working of an employee uses the values in force on his last working day. A change takes effect from the date you give; workings already cleared are kept as they were.</span>
        <a class="btn btn-sm btn-outline-primary ms-auto" href="{{ route('fnf.index') }}">F&amp;F Finance Clearance</a>
    </div>
    @if (session('success'))<div class="alert alert-success py-2 small">{{ session('success') }}</div>@endif
    @if (isset($errors) && $errors->any())<div class="alert alert-danger py-2 small">{{ $errors->first() }}</div>@endif
    <div class="card"><div class="table-responsive">
        <table class="table table-sm align-middle mb-0">
            <thead><tr><th>Rule</th><th>What it does</th><th class="text-center">In force today</th><th style="min-width:420px">Change (new value from a date)</th></tr></thead>
            <tbody>
            @foreach ($rules as $key => $r)
                <tr>
                    <td class="fw-semibold">{{ $r[0] }}</td>
                    <td class="small text-muted">{{ $r[1] }}</td>
                    <td class="text-center fw-bold">{{ $r[2] === 'bool' ? ($now[$key] ? 'Yes' : 'No') : $now[$key] }}</td>
                    <td>
                        <form method="post" action="{{ route('fnf.rules.save') }}" class="d-flex flex-wrap gap-1 align-items-end">@csrf
                            <input type="hidden" name="rule_key" value="{{ $key }}">
                            <div><label class="small mb-0" for="v{{ $key }}">Value</label>
                                @if ($r[2] === 'bool')
                                    <select id="v{{ $key }}" name="value" class="form-select form-select-sm"><option value="1" @selected($now[$key])>Yes</option><option value="0" @selected(! $now[$key])>No</option></select>
                                @else
                                    <input id="v{{ $key }}" name="value" type="number" min="{{ $r[4] }}" max="{{ $r[5] }}" value="{{ $now[$key] }}" class="form-control form-control-sm" style="width:80px">
                                @endif
                            </div>
                            <div><label class="small mb-0" for="f{{ $key }}">Effective from</label><input id="f{{ $key }}" name="effective_from" type="date" required value="{{ now()->toDateString() }}" class="form-control form-control-sm"></div>
                            <div><label class="small mb-0" for="t{{ $key }}">Effective to (optional)</label><input id="t{{ $key }}" name="effective_to" type="date" class="form-control form-control-sm"></div>
                            <div class="flex-grow-1"><label class="small mb-0" for="r{{ $key }}">Reason</label><input id="r{{ $key }}" name="remark" required minlength="5" maxlength="300" class="form-control form-control-sm"></div>
                            <button class="btn btn-sm btn-primary">Save</button>
                        </form>
                        @if (! empty($hist[$key]) && count($hist[$key]))
                            <details class="small mt-1"><summary>History ({{ count($hist[$key]) }})</summary>
                                <table class="table table-sm mb-0">
                                    <tr><th>Value</th><th>From</th><th>To</th><th>Reason</th><th>By / when</th></tr>
                                    @foreach ($hist[$key] as $h)
                                        <tr><td>{{ $r[2] === 'bool' ? ($h->value ? 'Yes' : 'No') : $h->value }}</td><td>{{ \Carbon\Carbon::parse($h->effective_from)->format('d-m-Y') }}</td>
                                            <td>{{ $h->effective_to ? \Carbon\Carbon::parse($h->effective_to)->format('d-m-Y') : '-' }}</td><td>{{ $h->remark }}</td>
                                            <td>{{ $h->updated_by_name }} {{ \Carbon\Carbon::parse($h->created_at)->format('d-m-Y H:i') }}</td></tr>
                                    @endforeach
                                </table>
                            </details>
                        @else
                            <div class="small text-muted mt-1">Default value (not changed yet).</div>
                        @endif
                    </td>
                </tr>
            @endforeach
            <tr>
                <td class="fw-semibold">Separation reminder mail</td>
                <td class="small text-muted">Mail to the separating employee about pending claims, daily from this many days before the last working day.</td>
                <td class="text-center fw-bold">{{ $reminder->days_before_separation ?? 7 }} days</td>
                <td class="small"><a href="{{ url('resigned-submission-reminder') }}">Change on F&amp;F Reminder Mails</a></td>
            </tr>
            </tbody>
        </table>
    </div></div>
</div></div>
@endsection
