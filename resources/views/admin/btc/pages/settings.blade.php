{{-- BTC-CONF-01 (17 Nov 2026): BTC Settings - controls, Focus ledgers, TDS defaults, Nova matching --}}
@extends('layouts.app')
@section('content')
<div class="page-content"><div class="container-fluid xe-screen">
    @include('admin.btc.pages._nav', ['title' => 'BTC Settings', 'icon' => 'ri-settings-3-line',
        'sub' => 'Values Finance may change without a program change. Every change is logged below.'])
    @if (session('success'))<div class="alert alert-success py-2">{{ session('success') }}</div>@endif
    @if (session('error'))<div class="alert alert-danger py-2">{{ session('error') }}</div>@endif

    <form method="post" action="{{ route('btc.settings.save') }}">@csrf
        @foreach (collect($fields)->groupBy(fn ($f) => $f[2], true) as $group => $items)
            <section class="xe-section mb-3">
                <div class="xe-section-hdr"><span class="xe-section-title">{{ $group }}</span></div>
                <div class="xe-section-body"><div class="row g-2">
                    @foreach ($items as $k => [$label, $type, $g, $help])
                        @php($name = str_replace('.', '__', $k))
                        <div class="col-md-6">
                            @if ($type === 'bool')
                                <label class="form-check mt-2"><input type="checkbox" class="form-check-input" name="{{ $name }}" value="1" @checked($values[$k])> {{ $label }}</label>
                            @else
                                <label class="form-label small mb-1" for="s_{{ $name }}">{{ $label }}</label>
                                @if ($type === 'nature')
                                    <select class="form-select form-select-sm" id="s_{{ $name }}" name="{{ $name }}">@foreach ($natures as $nk => $nl)<option value="{{ $nk }}" @selected($values[$k] === $nk)>{{ $nl }}</option>@endforeach</select>
                                @else
                                    <input class="form-control form-control-sm" id="s_{{ $name }}" name="{{ $name }}" type="{{ $type === 'int' ? 'number' : 'text' }}" @if ($type === 'int') min="0" @endif value="{{ $values[$k] }}">
                                @endif
                            @endif
                            @if ($help)<div class="form-text">{{ $help }}</div>@endif
                        </div>
                    @endforeach
                </div></div>
            </section>
        @endforeach
        <button class="xe-btn xe-btn-blue mb-3"><i class="ri-save-line"></i>Save settings</button>
    </form>

    <section class="xe-section">
        <div class="xe-section-hdr"><span class="xe-section-title">Change history</span></div>
        <div class="table-responsive"><table class="table table-sm mb-0">
            <thead><tr><th>When</th><th>By</th><th>Setting</th><th>From</th><th>To</th></tr></thead>
            <tbody>
            @forelse ($logs as $l)
                <tr><td class="text-nowrap">{{ substr((string) $l->created_at, 0, 16) }}</td><td>{{ $l->user_name }}</td><td>{{ $fields[$l->key][0] ?? $l->key }}</td><td>{{ $l->old_value }}</td><td>{{ $l->new_value }}</td></tr>
            @empty
                <tr><td colspan="5" class="text-center text-muted py-3">No changes yet.</td></tr>
            @endforelse
            </tbody>
        </table></div>
    </section>
</div></div>
@endsection
