{{-- CLAIM-SCORE-01: Claim Score card on the employee Dashboard --}}
@php($csCard = \App\Http\Controllers\ClaimScoreController::card())
@if ($csCard)
<a href="{{ route('claim-score.me') }}" class="card mb-3 text-decoration-none" style="border:0;border-radius:16px;overflow:hidden;background:radial-gradient(120% 140% at 0% 0%,#3a2a9a 0%,#23185f 55%,#15648c 120%);color:#fff">
    <div class="card-body d-flex flex-wrap align-items-center gap-3">
        <div style="width:74px;height:74px;border-radius:50%;display:grid;place-content:center;border:6px solid rgba(255,255,255,.18);border-top-color:#f0c44a;font:700 1.35rem 'Space Grotesk',system-ui,sans-serif">{{ $csCard['score'] }}</div>
        <div class="flex-grow-1" style="min-width:180px">
            <div style="font-size:.75rem;opacity:.75">My Claim Score · {{ $csCard['month'] }}</div>
            <div style="font-weight:700;font-size:1.05rem">{{ $csCard['level']['n'] }}@if ($csCard['drank']) · #{{ $csCard['drank'] }} of {{ $csCard['dof'] }} in {{ $csCard['dept'] }}@endif</div>
            @if ($csCard['mission'])<div style="font-size:.8rem;opacity:.85">Quickest gain: {{ strtolower($csCard['mission']['title']) }} (up to +{{ $csCard['mission']['gain'] }} points)</div>@endif
        </div>
        <span style="font-weight:600;font-size:.82rem;background:rgba(255,255,255,.15);border:1px solid rgba(255,255,255,.4);border-radius:999px;padding:6px 14px">Open my score</span>
    </div>
</a>
@endif
