{{-- BTC-GRID-01 (28 Nov 2026): rows per page and page navigation under a BTC list --}}
@php $p = $p ?? $rows; @endphp
@if ($p instanceof \Illuminate\Contracts\Pagination\LengthAwarePaginator)
<div class="btc-pager-bar">
    <span>{{ $p->total() ? 'Showing '.$p->firstItem().'–'.$p->lastItem().' of '.$p->total() : 'No rows' }}</span>
    <form method="get" class="d-flex align-items-center gap-2 mb-0">
        @foreach (request()->except(['per_page', 'page']) as $k => $v)
            @if (is_array($v))@foreach ($v as $vv)<input type="hidden" name="{{ $k }}[]" value="{{ $vv }}">@endforeach @else<input type="hidden" name="{{ $k }}" value="{{ $v }}">@endif
        @endforeach
        <label class="small text-muted mb-0" for="btcPerPage{{ $loop->index ?? '' }}">Rows per page</label>
        <select name="per_page" id="btcPerPage{{ $loop->index ?? '' }}" class="form-select form-select-sm" style="width:auto" onchange="this.form.submit()">
            @foreach (\App\Support\BtcPerPage::CHOICES as $n)<option value="{{ $n }}" @selected($p->perPage() === $n)>{{ $n }}</option>@endforeach
        </select>
    </form>
    <div class="ms-md-auto">{{ $p->onEachSide(1)->links() }}</div>
</div>
@endif
