{{-- ADV-POL-01 --}}
@extends('layouts.app')

@section('content')
    <div class="col-md-12 h-100 p-3 mt-5">
        <div class="row card mt-2">
            <div class="col-lg-12 shadow p-3">
                <h5 class="mb-1">Advance policy check</h5>
                <p class="text-muted small mb-2">The written VNR advance policy (7 Nov 2026) against the advance set-up and this year's advances. Newest first.</p>
                <div class="alert alert-light border small py-2">
                    <strong>Policy.</strong>
                    Fixed travel allowance: {{ implode(', ', $policy['fixed_travel']['departments']) }}, J1&ndash;M1
                    (@foreach ($policy['fixed_travel']['amounts'] as $g => $a){{ $g }} ₹{{ number_format($a) }}{{ $loop->last ? '' : ', ' }}@endforeach).
                    Supply-chain advance: {{ implode(', ', $policy['supply_chain_auto']['departments']) }}, up to {{ $policy['supply_chain_auto']['max_percent'] }}% of the month's claim without DA, only after that month is submitted (checked when it is raised).
                    Tour advance: Support Functions, {{ $policy['tour']['min_grade'] }} and above.
                    Activity advance: {{ implode(', ', $policy['activity']['departments']) }}.
                    Super Admin can still enable advances for any employee or department.
                </div>
                @foreach ($sections as $key => $s)
                    <h6 class="mt-3 mb-1">{{ $s['title'] }} <span class="badge {{ count($s['rows']) ? 'bg-warning text-dark' : 'bg-success' }}">{{ count($s['rows']) }}</span></h6>
                    @if (count($s['rows']))
                        <div class="table-responsive" style="max-height:40vh">
                            <table class="table table-sm table-bordered small">
                                <thead class="table-light"><tr>@foreach (array_keys($s['rows'][0]) as $h)<th>{{ $h }}</th>@endforeach</tr></thead>
                                <tbody>
                                @foreach ($s['rows'] as $r)
                                    <tr>@foreach ($r as $v)<td>{{ is_float($v) ? number_format($v, 0) : $v }}</td>@endforeach</tr>
                                @endforeach
                                </tbody>
                            </table>
                        </div>
                    @else
                        <p class="small text-muted mb-1">Nothing outside the policy.</p>
                    @endif
                @endforeach
            </div>
        </div>
    </div>
@endsection
