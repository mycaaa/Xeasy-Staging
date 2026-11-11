{{-- BOARDING-01: vendor boarding-pass portal links (Finance) --}}
@extends('layouts.app')

@section('content')
<div class="col-md-12 h-100 p-3 mt-5">
    <div class="row card mt-2">
        <div class="col-lg-12 shadow p-3">
            <div class="d-flex justify-content-between align-items-center mb-1">
                <h5 class="mb-0">Vendor boarding-pass links</h5>
                <a href="{{ route('btc.index') }}" class="btn btn-sm btn-outline-secondary">Back to BTC</a>
            </div>
            <p class="text-muted small mb-3">Send a travel vendor its link. Without an Xeasy login, the vendor sees only its own air bookings still waiting for a boarding pass and uploads one file per booking. The upload marks the boarding pass received on the BTC case.</p>
            @if (session('success'))<div class="alert alert-success py-2">{{ session('success') }}</div>@endif
            @if ($newUrl)
                <div class="alert alert-warning">
                    <div class="small fw-semibold mb-1">New link (shown once):</div>
                    <div class="input-group input-group-sm">
                        <input type="text" class="form-control" id="newLink" value="{{ $newUrl }}" readonly>
                        <button class="btn btn-dark" type="button" onclick="navigator.clipboard.writeText(document.getElementById('newLink').value); this.textContent='Copied';">Copy</button>
                    </div>
                </div>
            @endif
            @if (isset($errors) && $errors->any())<div class="alert alert-danger py-2">{{ $errors->first() }}</div>@endif

            <h6>Vendors with boarding passes pending</h6>
            <table class="table table-sm table-bordered small">
                <thead class="table-light"><tr><th>Vendor</th><th class="text-end">Cases pending</th><th>Oldest travel date</th><th style="width:280px">Create link</th></tr></thead>
                <tbody>
                @forelse ($pending as $p)
                    <tr>
                        <td>{{ $p->vendor_name }}</td>
                        <td class="text-end">{{ $p->cases }}</td>
                        <td>{{ $p->oldest ? \Carbon\Carbon::parse($p->oldest)->format('d-m-Y') : '' }}</td>
                        <td>
                            @if ($p->vendor_name !== '(no vendor)')
                            <form method="post" action="{{ route('btc.vendor.create') }}" class="d-flex gap-1">
                                @csrf
                                <input type="hidden" name="vendor_id" value="{{ $p->vendor_id ?: '' }}">
                                <input type="hidden" name="vendor_name" value="{{ $p->vendor_name }}">
                                <select name="days" class="form-select form-select-sm" style="width:110px">
                                    <option value="7">7 days</option><option value="30" selected>30 days</option><option value="90">90 days</option>
                                </select>
                                <button class="btn btn-sm btn-primary">Create link</button>
                            </form>
                            @else
                                <span class="text-muted">Set the vendor on these cases first</span>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="4" class="text-center text-muted">No boarding passes pending.</td></tr>
                @endforelse
                </tbody>
            </table>

            <h6 class="mt-3">Links</h6>
            <table class="table table-sm table-bordered small">
                <thead class="table-light"><tr><th>Created</th><th>Vendor</th><th>Expires</th><th>Last used</th><th>By</th><th>Status</th><th></th></tr></thead>
                <tbody>
                @forelse ($links as $l)
                    @php $active = ! $l->revoked_at && now()->lt($l->expires_at); @endphp
                    <tr>
                        <td>{{ \Carbon\Carbon::parse($l->created_at)->format('d-m-Y H:i') }}</td>
                        <td>{{ $l->vendor_name }}</td>
                        <td>{{ \Carbon\Carbon::parse($l->expires_at)->format('d-m-Y') }}</td>
                        <td>{{ $l->last_used_at ? \Carbon\Carbon::parse($l->last_used_at)->format('d-m-Y H:i') : '' }}</td>
                        <td>{{ $l->created_by_name }}</td>
                        <td>{{ $l->revoked_at ? 'Revoked' : ($active ? 'Active' : 'Expired') }}</td>
                        <td>@if ($active)<form method="post" action="{{ route('btc.vendor.revoke', $l->id) }}">@csrf<button class="btn btn-sm btn-outline-danger py-0">Revoke</button></form>@endif</td>
                    </tr>
                @empty
                    <tr><td colspan="7" class="text-center text-muted">No links yet.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
@endsection
