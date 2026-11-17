{{-- CLAIM-SCORE-01: Claim Score card on the employee Dashboard (DASH-CARD-02, 17 Nov 2026: same frame as the BTC card) --}}
@php($csCard = \App\Http\Controllers\ClaimScoreController::card())
@if ($csCard)
<div class="col-12 col-lg-5 col-xl-4 col-xxl-3">
<a href="{{ route('claim-score.me') }}" class="dash-flash dash-flash-score {{ !empty($csCompact) ? 'compact' : '' }} text-decoration-none">
    <div class="dash-flash-head">
        <div><div class="dash-flash-title"><i class="ri-trophy-line"></i> My Claim Score</div><div class="dash-flash-sub">{{ $csCard['month'] }}</div></div>
        <span class="dash-flash-btn">Open my score</span>
    </div>
    <div class="dash-flash-body dash-flash-row">
        <div class="dash-flash-ring">{{ $csCard['score'] }}</div>
        <div style="min-width:0">
            <div class="dash-flash-big">{{ $csCard['level']['n'] }}</div>
            @if ($csCard['drank'])<div class="dash-flash-line">#{{ $csCard['drank'] }} of {{ $csCard['dof'] }} in {{ $csCard['dept'] }}</div>@endif
            @if ($csCard['mission'])<div class="dash-flash-line">Quickest gain: {{ strtolower($csCard['mission']['title']) }} (up to +{{ $csCard['mission']['gain'] }})</div>@endif
        </div>
    </div>
</a>
</div>
@endif
