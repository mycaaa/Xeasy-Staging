{{-- IMP-STATUS-01: imprest status badge (App\Services\Imprest\ImprestStatus) --}}
@if ($st)
    @php $tone = ['light' => 'bg-light text-muted border'][$st['badge']] ?? 'bg-'.$st['badge'].'-subtle text-'.$st['badge']; @endphp
    <span class="badge {{ $tone }} px-2 py-1" data-imprest="{{ $st['key'] }}">{{ $st['label'] }}</span>
    @if ($st['key'] === 'exit_settle')
        <a href="#separations-tab" class="d-block fs-11 nav-tab-trigger" data-target="#separations-tab">{{ $st['detail'] }} - settle</a>
    @elseif (in_array($st['key'], ['due', 'topup_due', 'pending', 'rejected'], true))
        <a href="{{ url('/imprest-automation/missing-imprest') }}" class="d-block fs-11 text-muted text-wrap" style="max-width:220px">{{ $st['detail'] }}</a>
    @else
        <small class="d-block text-muted fs-11 text-wrap" style="max-width:220px">{{ $st['detail'] }}</small>
    @endif
@else
    <span class="text-muted">-</span>
@endif
