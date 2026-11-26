{{-- BTC-UI-01 (26 Nov 2026): the buttons of the BTC Request page (as before) --}}
@can('btc.finance')
    <a class="xe-btn xe-btn-plain" href="{{ route('btc.export.boarding') }}"><i class="ri-plane-line"></i> Boarding passes pending</a>
@endcan
<button class="btn btn-sm btn-primary" data-bs-toggle="modal" data-bs-target="#btcNewModal"><i class="ri-add-line"></i> New BTC case</button>
