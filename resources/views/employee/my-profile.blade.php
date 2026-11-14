@extends('layouts.app')

{{-- PROFILE-01 (14 Nov 2026): the signed-in employee's own details, opened from Profile in the top-right menu --}}
@section('content')
@php
    $v = fn ($x) => ($x !== null && trim((string) $x) !== '') ? $x : '—';
    $statusLabel = ['A' => 'Active', 'R' => 'Resigned', 'D' => 'Deactivated', 'De' => 'Deactivated'][$emp->EmpStatus ?? ''] ?? ($emp->EmpStatus ?? '');
    $join = !empty($emp->JoinDate) && \App\Support\ZeroDate::has($emp->JoinDate) ? \Carbon\Carbon::parse($emp->JoinDate)->format('d/m/Y') : '—';
@endphp
<style>
    .mp-head { display: flex; gap: 18px; align-items: center; flex-wrap: wrap; }
    .mp-photo { width: 84px; height: 84px; border-radius: 50%; object-fit: cover; border: 3px solid #dde6ff; background: #f4f6fb; }
    .mp-name { font-size: 1.35rem; font-weight: 700; margin: 0; color: #1f2937; }
    .mp-sub { color: #5b6478; margin: 2px 0 0; }
    .mp-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(240px, 1fr)); gap: 0 28px; }
    .mp-row { display: flex; justify-content: space-between; gap: 12px; padding: 10px 0; border-bottom: 1px solid #eef1f6; font-size: .9rem; }
    .mp-row dt { color: #5b6478; font-weight: 500; }
    .mp-row dd { margin: 0; text-align: right; font-weight: 600; color: #1f2937; word-break: break-word; }
    .mp-links a { margin: 0 8px 8px 0; }
</style>

<div class="row justify-content-center">
    <div class="col-xl-9">
        <div class="card">
            <div class="card-body">
                @if (! $emp)
                    <p class="mb-0 text-muted">Your HR record could not be read just now. Refresh the page; if it keeps happening, tell the HR team.</p>
                @else
                    <div class="mp-head mb-3">
                        <img class="mp-photo" src="{{ $photo }}" alt="Your photo" onerror="this.onerror=null;this.src='{{ asset('custom/no_user.png') }}'">
                        <div>
                            <h1 class="mp-name">{{ $emp->FullName }}</h1>
                            <p class="mp-sub">{{ $v($emp->designation_name) }} · {{ $v($emp->department_name) }}</p>
                            <p class="mp-sub small">
                                Employee {{ $emp->EmpCode }} · {{ $companyName }}
                                @if ($statusLabel !== '') <span class="badge {{ ($emp->EmpStatus ?? '') === 'A' ? 'bg-success-subtle text-success' : 'bg-warning-subtle text-warning' }} ms-1">{{ $statusLabel }}</span> @endif
                                @foreach ($roles as $r) <span class="badge bg-primary-subtle text-primary ms-1">{{ $r }}</span> @endforeach
                            </p>
                        </div>
                    </div>

                    <div class="mp-grid">
                        <dl class="mb-0">
                            <div class="mp-row"><dt>Grade</dt><dd>{{ $v($emp->grade_name) }}</dd></div>
                            <div class="mp-row"><dt>Vertical</dt><dd>{{ $v($emp->vertical_name) }}</dd></div>
                            <div class="mp-row"><dt>Headquarter</dt><dd>{{ $v($emp->city_village_name) }}</dd></div>
                            <div class="mp-row"><dt>Territory</dt><dd>{{ $v($emp->territory_name) }}</dd></div>
                            <div class="mp-row"><dt>State</dt><dd>{{ $v($emp->state_name) }}</dd></div>
                        </dl>
                        <dl class="mb-0">
                            <div class="mp-row"><dt>Reports to</dt><dd>{{ $v($emp->ReportingName) }}</dd></div>
                            <div class="mp-row"><dt>Manager's phone</dt><dd>{{ $v($emp->ReportingContactNo) }}</dd></div>
                            <div class="mp-row"><dt>Mobile</dt><dd>{{ $v($emp->MobileNo_Vnr) }}</dd></div>
                            <div class="mp-row"><dt>Email</dt><dd>{{ $v($emp->EmailId_Vnr) }}</dd></div>
                            <div class="mp-row"><dt>Joined</dt><dd>{{ $join }}</dd></div>
                        </dl>
                    </div>

                    <p class="small text-muted mt-3 mb-3">These details come from the HR system. To correct anything, contact the HR team.</p>
                    <div class="mp-links">
                        <a href="{{ url('/claimant') }}" class="btn btn-sm btn-soft-primary"><i class="ri-file-list-3-line me-1" aria-hidden="true"></i>My claims</a>
                        <a href="{{ url('/dashboard') }}" class="btn btn-sm btn-soft-secondary"><i class="ri-home-4-line me-1" aria-hidden="true"></i>Dashboard</a>
                    </div>
                @endif
            </div>
        </div>
    </div>
</div>
@endsection
