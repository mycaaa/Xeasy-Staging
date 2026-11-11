{{-- KM-CAP-01 --}}
@extends('layouts.app')

@section('content')
@php $nm = fn ($id) => isset($names[$id]) ? trim($names[$id]->Fname.' '.$names[$id]->Sname.' '.$names[$id]->Lname).' ('.$names[$id]->EmpCode.')' : ($id ?: '—'); @endphp
<div class="col-md-12 h-100 p-3 mt-5">
    <div class="row card mt-2"><div class="col-lg-12 shadow p-3">
        <h5 class="mb-1">Km limit approvals</h5>
        <p class="text-muted small mb-2">Breeding and Breeding Support: running km above {{ number_format((float) (collect(config('claim_rules.km_cap.monthly'))->firstWhere('approval', 'hod')['max_total'] ?? 1200)) }} km in a month needs the HOD's approval. Until it is approved the claim cannot be approved.</p>
        @if (session('success'))<div class="alert alert-success py-2 small">{{ session('success') }}</div>@endif
        @if (isset($errors) && $errors->any())<div class="alert alert-danger py-2 small">{{ $errors->first() }}</div>@endif
        <div class="mb-2 small">
            @foreach (['pending' => 'Waiting', 'approved' => 'Approved', 'rejected' => 'Not approved', 'all' => 'All'] as $k => $label)
                <a href="?status={{ $k }}" class="btn btn-sm {{ $status === $k ? 'btn-primary' : 'btn-outline-primary' }}">{{ $label }}</a>
            @endforeach
        </div>
        <table class="table table-sm table-bordered small">
            <thead class="table-light"><tr><th>Employee</th><th>Month</th><th>Claim</th><th class="text-end">Km this month</th><th class="text-end">Limit</th>@if ($staff)<th>HOD</th>@endif<th>Status</th><th>Decision</th></tr></thead>
            <tbody>
            @forelse ($rows as $r)
                <tr>
                    <td>{{ $nm($r->employee_id) }}</td>
                    <td>{{ date('M', mktime(0, 0, 0, $r->month, 1)) }}</td>
                    <td>ExpId {{ $r->exp_id }}</td>
                    <td class="text-end">{{ number_format($r->month_km) }}</td>
                    <td class="text-end">{{ number_format($r->limit_km) }}</td>
                    @if ($staff)<td>{{ $nm($r->hod_id) }}</td>@endif
                    <td>{{ ['pending' => 'Waiting', 'approved' => 'Approved', 'rejected' => 'Not approved', 'not_needed' => 'Within the limit now'][$r->status] ?? $r->status }}@if ($r->remark)<div class="text-muted">{{ $r->remark }}</div>@endif</td>
                    <td>
                        @if ($r->status === 'pending')
                            <form method="POST" action="{{ route('km.cap.approvals.decide', $r->id) }}" class="d-flex gap-1">
                                @csrf
                                <input name="remark" class="form-control form-control-sm" placeholder="Remark (needed to refuse)" aria-label="Remark (needed to refuse)">
                                <button name="decision" value="approved" class="btn btn-sm btn-success">Approve</button>
                                <button name="decision" value="rejected" class="btn btn-sm btn-outline-danger">Refuse</button>
                            </form>
                        @endif
                    </td>
                </tr>
            @empty
                <tr><td colspan="8" class="text-muted">Nothing here.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div></div>
</div>
@endsection
