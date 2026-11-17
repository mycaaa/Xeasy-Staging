{{-- BTC-CARD-01: Bill To Company flash card on the employee Dashboard (DASH-CARD-02, 17 Nov 2026: same frame as the Claim Score card) --}}
@php($btcCard = \App\Http\Controllers\admin\BtcPagesController::card())
@if ($btcCard)
@php($inr = fn ($n) => '₹'.number_format((float) $n, 0))
@php($t = ($btcCard['team']['requests'] ?? 0) ? $btcCard['team'] : null)
<div class="col-12 col-lg">
<div class="dash-flash dash-flash-btc">
    <div class="dash-flash-head">
        <div><div class="dash-flash-title"><i class="ri-bill-line"></i> Bill To Company (BTC)</div>
            <div class="dash-flash-sub">@if ($btcCard['latest']){{ $btcCard['latest']['no'] }} · {{ $btcCard['latest']['stage'] }} · with {{ $btcCard['latest']['with'] }}@else My requests @if ($t)and my team @endif @endif</div></div>
        <a href="{{ route('btc.index') }}" class="dash-flash-btn">Open BTC</a>
    </div>
    <div class="dash-flash-body">
        <div class="dash-flash-tiles">
            <a class="dash-tile" href="{{ route('btc.index') }}"><b>{{ $btcCard['my_open'] }}</b><span>My open requests<br>{{ $inr($btcCard['my_value']) }}</span></a>
            <a class="dash-tile {{ $btcCard['returned'] ? 'hot' : '' }}" href="{{ route('btc.index') }}"><b>{{ $btcCard['returned'] }}</b><span>Returned to me<br>for correction</span></a>
            <a class="dash-tile {{ $btcCard['approve'] ? 'hot' : '' }}" href="{{ route('btc.index') }}?tab=approvals"><b>{{ $btcCard['approve'] }}</b><span>Waiting for<br>my approval</span></a>
            <div class="dash-tile"><b>{{ $inr($btcCard['adv_balance']) }}</b><span>My advance<br>with vendors</span></div>
        </div>
            @if ($t)
        <div class="dash-flash-group">My team</div>
        <div class="dash-flash-tiles team3">
                <a class="dash-tile team" href="{{ route('btc.team', ['scope' => 'all', 'show' => 'open']) }}"><b>{{ $t['open'] }}</b><span>Open requests<br>{{ $inr($t['value']) }}</span></a>
                <a class="dash-tile team {{ $t['returned'] ? 'hot' : '' }}" href="{{ route('btc.team', ['scope' => 'all', 'status' => 'returned']) }}"><b>{{ $t['returned'] }}</b><span>Returned<br>for correction</span></a>
                <a class="dash-tile team" href="{{ route('btc.team', ['scope' => 'all']) }}"><b>{{ $inr($t['billed']) }}</b><span>Billed,<br>not yet paid</span></a>
        </div>
            @endif
    </div>
</div>
</div>
@endif
