{{-- REM-SET-01 --}}
@extends('layouts.app')

@section('content')
    <div class="col-md-12 h-100 p-3 mt-5">
        <div class="row card mt-2">
            <div class="col-lg-12 shadow p-3">
                <h5 class="mb-1">Reminder settings</h5>
                <p class="text-muted small mb-2">When the person a reminder is meant for has no mail address or no manager, that reminder is skipped (no fallback to anyone else). Mail sending overall: <strong>{{ $mailActive ? 'on' : 'off (MAIL_ACTIVE)' }}</strong>.</p>
                @if (session('success'))
                    <div class="alert alert-success py-2 small">{{ session('success') }}</div>
                @endif
                <form method="POST" action="{{ route('reminder.settings.update') }}">
                    @csrf
                    @foreach (collect($fields)->groupBy(fn ($f) => $f[2], true) as $group => $items)
                        <h6 class="mt-3 mb-1">{{ $group }}</h6>
                        @php $help = collect($items)->first()[3] ?? ''; @endphp
                        @if ($help)<div class="text-muted small mb-1">{{ $help }}</div>@endif
                        <div class="row g-2">
                            @foreach ($items as $key => $f)
                                @php $name = str_replace('.', '__', $key); $v = $values[$key] ?? null; @endphp
                                <div class="col-md-3">
                                    <label class="form-label small mb-0" for="{{ $name }}">{{ $f[0] }}</label>
                                    @if ($f[1] === 'bool')
                                        <div class="form-check form-switch"><input class="form-check-input" type="checkbox" id="{{ $name }}" name="{{ $name }}" value="1" @checked(filter_var($v, FILTER_VALIDATE_BOOLEAN))></div>
                                    @elseif ($f[1] === 'time')
                                        <input type="time" class="form-control form-control-sm" id="{{ $name }}" name="{{ $name }}" value="{{ $v }}">
                                    @else
                                        <input type="number" min="0" max="9999" class="form-control form-control-sm" id="{{ $name }}" name="{{ $name }}" value="{{ (int) $v }}">
                                    @endif
                                </div>
                            @endforeach
                        </div>
                    @endforeach
                    <button type="submit" class="btn btn-primary btn-sm mt-3">Save</button>
                </form>
            </div>
        </div>
    </div>
@endsection
