{{-- BTC-CARD-01 (17 Nov 2026): Bill To Company flash card on the employee Dashboard --}}
@php($btcCard = \App\Http\Controllers\admin\BtcPagesController::card())
@if ($btcCard)
@php($inr = fn ($n) => '₹'.number_format((float) $n, 0))
<style>
    .btc-flash { border: 0; border-radius: 16px; overflow: hidden; background: linear-gradient(120deg, #0f4c5c 0%, #136f63 55%, #3e8e41 110%); color: #fff; }
    .btc-flash .tiles { display: grid; grid-template-columns: repeat(auto-fit, minmax(128px, 1fr)); gap: 8px; }
    .btc-flash .tile { display: block; background: rgba(255,255,255,.10); border: 1px solid rgba(255,255,255,.22); border-radius: 12px; padding: 8px 12px; color: #fff; text-decoration: none; }
    .btc-flash a.tile:hover { background: rgba(255,255,255,.18); color: #fff; }
    .btc-flash .tile .v { font-size: 1.3rem; font-weight: 800; font-variant-numeric: tabular-nums; line-height: 1.2; }
    .btc-flash .tile .l { font-size: .72rem; opacity: .85; }
    .btc-flash .tile.alert-on { background: #fde68a; color: #713f12; border-color: #fde68a; }
    .btc-flash .grp { font-size: .72rem; opacity: .8; margin: 2px 0 6px; }
</style>
<div class="card mb-3 btc-flash">
    <div class="card-body">
        <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-2">
            <div><div style="font-weight:700;font-size:1.02rem"><i class="ri-bill-line"></i> Bill To Company (BTC)</div>
                @if ($btcCard['latest'])<div style="font-size:.78rem;opacity:.85">Latest: {{ $btcCard['latest']['no'] }} · {{ $btcCard['latest']['stage'] }} · waiting for {{ $btcCard['latest']['with'] }}</div>@endif
            </div>
            <a href="{{ route('btc.index') }}" style="font-weight:600;font-size:.8rem;color:#fff;background:rgba(255,255,255,.15);border:1px solid rgba(255,255,255,.4);border-radius:999px;padding:5px 14px;text-decoration:none">Open BTC</a>
        </div>
        <div class="grp">My requests</div>
        <div class="tiles mb-2">
            <a class="tile" href="{{ route('btc.index') }}"><div class="v">{{ $btcCard['my_open'] }}</div><div class="l">open of {{ $btcCard['my_total'] }} · {{ $inr($btcCard['my_value']) }}</div></a>
            <a class="tile {{ $btcCard['returned'] ? 'alert-on' : '' }}" href="{{ route('btc.index') }}"><div class="v">{{ $btcCard['returned'] }}</div><div class="l">returned to me for correction</div></a>
            <a class="tile {{ $btcCard['approve'] ? 'alert-on' : '' }}" href="{{ route('btc.index') }}?tab=approvals"><div class="v">{{ $btcCard['approve'] }}</div><div class="l">waiting for my approval</div></a>
            <div class="tile"><div class="v">{{ $inr($btcCard['adv_balance']) }}</div><div class="l">my advance balance with vendors</div></div>
        </div>
        @if ($btcCard['team'] && $btcCard['team']['requests'])
            @php($t = $btcCard['team'])
            <div class="grp">My team (all levels below) · {{ $t['people'] }} people with requests</div>
            <div class="tiles">
                <a class="tile" href="{{ route('btc.team', ['scope' => 'all', 'show' => 'open']) }}"><div class="v">{{ $t['open'] }}</div><div class="l">open of {{ $t['requests'] }} · {{ $inr($t['value']) }}</div></a>
                <a class="tile {{ $t['returned'] ? 'alert-on' : '' }}" href="{{ route('btc.team', ['scope' => 'all', 'status' => 'returned']) }}"><div class="v">{{ $t['returned'] }}</div><div class="l">returned for correction</div></a>
                <a class="tile" href="{{ route('btc.team', ['scope' => 'all']) }}"><div class="v">{{ $inr($t['billed']) }}</div><div class="l">billed, not yet paid</div></a>
            </div>
        @endif
    </div>
</div>
@endif
