{{-- BTC-REQ-01 / 02: BTC Roles & Access (Super Admin) --}}
@extends('layouts.app')
@section('content')
<div class="page-content"><div class="container-fluid xe-screen btc-ui">
    @include('admin.btc.pages._nav', ['title' => 'BTC Roles & Access', 'icon' => 'ri-shield-user-line', 'sub' => 'Roles, screen-wise and action-wise permissions, entity and department access, approval limits, user assignment and history'])
    <div class="xe-seg mb-3">
        @foreach (['roles' => 'Roles', 'users' => 'Users', 'history' => 'Access history', 'activity' => 'User activity'] as $k => $l)
            <a href="{{ route('btc.access', ['tab' => $k]) }}" class="{{ $tab === $k ? 'active' : '' }}">{{ $l }}</a>
        @endforeach
    </div>

    @php $off = fn ($r) => $r && $r->is_active !== null && (int) $r->is_active === 0; @endphp
    @if ($tab === 'roles')
        @php
            $edit = request('edit') ? $roles->firstWhere('id', (int) request('edit')) : null;
            $editPerms = $edit ? ($off($edit) ? array_filter(explode(',', (string) $edit->saved_permissions)) : ($permByRole[$edit->id] ?? [])) : [];
            $editCos = $edit ? array_filter(explode(',', (string) $edit->company_ids)) : [];
            $editDeps = $edit ? array_filter(explode(',', (string) $edit->department_ids)) : [];
        @endphp
        <section class="xe-section">
            <div class="table-responsive"><table class="table table-sm mb-0 align-middle" data-pager>
                <thead><tr><th>Role</th><th>Access responsibility</th><th>Permissions</th><th>Entities</th><th>Departments</th><th class="text-end">Approval limit</th><th class="text-end">Users</th><th>Status</th><th></th></tr></thead>
                <tbody>
                @foreach ($roles as $r)
                    @php $perms = $permByRole[$r->id] ?? []; $deps = array_filter(explode(',', (string) $r->department_ids)); @endphp
                    <tr class="{{ $off($r) ? 'text-muted' : '' }}">
                        <td class="fw-semibold">{{ $r->name }}</td>
                        <td class="small">{{ $r->description }}</td>
                        <td class="small" style="max-width:280px">{{ $off($r) ? 'kept while deactivated: '.str_replace(',', ', ', (string) $r->saved_permissions) : (implode(', ', $perms) ?: 'acts through the request (no desk)') }}</td>
                        <td class="small">{{ $r->company_ids ? collect(explode(',', $r->company_ids))->map(fn ($c) => $companies[(int) $c] ?? $c)->implode(', ') : 'All' }}</td>
                        <td class="small">{{ $deps ? collect($deps)->map(fn ($d) => $departments[(int) $d] ?? $d)->take(4)->implode(', ').(count($deps) > 4 ? ' +'.(count($deps) - 4) : '') : 'All' }}</td>
                        <td class="text-end">{{ $r->approval_limit !== null ? '₹'.number_format((float) $r->approval_limit) : 'No limit' }}</td>
                        <td class="text-end">{{ $userCount[$r->id] ?? 0 }}</td>
                        <td>{{ $off($r) ? 'Inactive' : 'Active' }}</td>
                        <td class="text-nowrap">
                            <a class="xe-btn xe-btn-blue" href="{{ route('btc.access', ['tab' => 'roles', 'edit' => $r->id]) }}#roleForm">Edit</a>
                            <form method="post" action="{{ route('btc.access.toggle', $r->id) }}" class="d-inline">@csrf
                                <button class="xe-btn {{ $off($r) ? 'xe-btn-green' : 'xe-btn-amber' }}">{{ $off($r) ? 'Activate' : 'Deactivate' }}</button></form>
                        </td>
                    </tr>
                @endforeach
                </tbody>
            </table></div>
        </section>

        <section class="xe-section" id="roleForm">
            <div class="xe-section-hdr"><span class="xe-section-title"><i class="ri-edit-line"></i>{{ $edit ? 'Edit '.$edit->name : 'Create a BTC role' }}</span>
                @if ($edit)<a href="{{ route('btc.access') }}" class="small ms-auto">New role instead</a>@endif</div>
            <form method="post" action="{{ route('btc.access.role') }}" class="xe-section-body">
                @csrf
                <input type="hidden" name="role_id" value="{{ $edit->id ?? '' }}">
                <div class="row g-2">
                    <div class="col-md-4"><label class="form-label small mb-1" for="rName">Role name</label><input class="form-control form-control-sm" id="rName" name="name" required maxlength="100" value="{{ old('name', $edit->name ?? '') }}" placeholder="BTC ..."></div>
                    <div class="col-md-5"><label class="form-label small mb-1" for="rDesc">Access responsibility</label><input class="form-control form-control-sm" id="rDesc" name="description" maxlength="300" value="{{ old('description', $edit->description ?? '') }}"></div>
                    <div class="col-md-3"><label class="form-label small mb-1" for="rLimit">Approval limit (₹, empty = no limit)</label><input type="number" min="0" step="0.01" class="form-control form-control-sm" id="rLimit" name="approval_limit" value="{{ old('approval_limit', $edit->approval_limit ?? '') }}"></div>
                    @foreach ($catalog as $group => $perms)
                        <div class="col-md-6">
                            <div class="small fw-semibold mb-1">{{ $group === 'Screens' ? 'Screen-wise permissions' : 'Action-wise permissions' }}</div>
                            @foreach ($perms as $p => $label)
                                <label class="d-block small"><input type="checkbox" class="form-check-input me-1" name="permissions[]" value="{{ $p }}" @checked(in_array($p, old('permissions', $editPerms), true))> {{ $label }} <span class="text-muted">({{ $p }})</span></label>
                            @endforeach
                        </div>
                    @endforeach
                    <div class="col-md-4">
                        <div class="small fw-semibold mb-1">Entity-wise access (none ticked = all)</div>
                        @foreach ($companies as $id => $n)<label class="d-block small"><input type="checkbox" class="form-check-input me-1" name="companies[]" value="{{ $id }}" @checked(in_array((string) $id, old('companies', $editCos), false))> {{ $n }}</label>@endforeach
                    </div>
                    <div class="col-md-8">
                        <label class="small fw-semibold mb-1" for="rDeps">Department-wise access (none selected = all; Ctrl/Cmd-click for several)</label>
                        <select multiple class="form-select form-select-sm" id="rDeps" name="departments[]" size="7">
                            @foreach ($departments as $id => $n)<option value="{{ $id }}" @selected(in_array((string) $id, old('departments', $editDeps), false))>{{ $n }}</option>@endforeach
                        </select>
                    </div>
                    <div class="col-12"><button class="xe-btn xe-btn-main">{{ $edit ? 'Save role' : 'Create role' }}</button>
                        <span class="small text-muted ms-2">Super Admin assigns DMS Admin, BTC Admin and the Finance roles separately on the Users tab.</span></div>
                </div>
            </form>
        </section>
    @elseif ($tab === 'users')
        <form method="get" class="d-flex gap-2 mb-2"><input type="hidden" name="tab" value="users">
            <input type="search" name="user" value="{{ request('user') }}" class="form-control form-control-sm" style="max-width:320px" placeholder="Employee code or name" aria-label="Find user" required>
            <button class="xe-btn xe-btn-blue">Find</button></form>
        @if (request('user') && $users->isEmpty())<div class="small text-muted">No user found.</div>@endif
        @foreach ($users as $u)
            @php $has = $userRoles[$u->id] ?? []; @endphp
            <section class="xe-section">
                <div class="xe-section-hdr"><span class="xe-section-title"><i class="ri-user-line"></i>{{ $u->name }}</span><span class="small text-muted">{{ $u->email }} · Emp. code {{ $codes[$u->employee_id] ?? '-' }}</span>
                    <a class="small ms-auto" href="{{ route('btc.access', ['tab' => 'activity', 'activity_user' => $u->id]) }}">activity</a></div>
                <div class="xe-section-body d-flex flex-wrap gap-2">
                    @foreach ($roles as $r)
                        <form method="post" action="{{ route('btc.access.user') }}">@csrf
                            <input type="hidden" name="user_id" value="{{ $u->id }}"><input type="hidden" name="role_id" value="{{ $r->id }}">
                            <input type="hidden" name="op" value="{{ in_array($r->id, $has) ? 'revoke' : 'assign' }}">
                            <button class="xe-btn {{ in_array($r->id, $has) ? 'xe-btn-green' : 'xe-btn-plain' }}" title="{{ in_array($r->id, $has) ? 'Click to revoke' : 'Click to assign' }}">
                                <i class="{{ in_array($r->id, $has) ? 'ri-checkbox-circle-fill' : 'ri-add-line' }}"></i>{{ $r->name }}</button>
                        </form>
                    @endforeach
                </div>
            </section>
        @endforeach
    @elseif ($tab === 'history')
        <section class="xe-section"><div class="table-responsive"><table class="table table-sm mb-0" data-pager>
            <thead><tr><th>Time</th><th>Change</th><th>Role</th><th>User</th><th>Details</th><th>By</th></tr></thead>
            <tbody>
            @forelse ($history as $h)
                <tr><td class="text-nowrap">{{ \Carbon\Carbon::parse($h->created_at)->format('d-m-Y H:i') }}</td><td>{{ str_replace('_', ' ', $h->action) }}</td><td>{{ $h->role_name }}</td><td>{{ $h->target_user }}</td>
                    <td class="small text-muted text-break" style="max-width:420px">{{ $h->details }}</td><td>{{ $h->actor_name }}</td></tr>
            @empty
                <tr><td colspan="6" class="text-center text-muted py-3">No access change yet.</td></tr>
            @endforelse
            </tbody></table></div></section>
    @else
        <form method="get" class="d-flex gap-2 mb-2"><input type="hidden" name="tab" value="activity">
            <input type="number" name="activity_user" value="{{ $activityUser }}" class="form-control form-control-sm" style="max-width:200px" placeholder="User ID" aria-label="User ID" required>
            <button class="xe-btn xe-btn-blue">Show</button><span class="small text-muted align-self-center">or open "activity" from the Users tab</span></form>
        <section class="xe-section"><div class="table-responsive"><table class="table table-sm mb-0" data-pager>
            <thead><tr><th>Time</th><th>Request</th><th>Step</th><th>Stage after</th><th>Remark</th></tr></thead>
            <tbody>
            @forelse ($activity as $a)
                <tr><td class="text-nowrap">{{ \Carbon\Carbon::parse($a->created_at)->format('d-m-Y H:i') }}</td><td>{{ $a->case_no }}</td><td>{{ str_replace('_', ' ', $a->action) }}</td>
                    <td>{{ config("btc.statuses.{$a->to_status}", $a->to_status) }}</td><td class="small text-muted">{{ $a->remark }}</td></tr>
            @empty
                <tr><td colspan="5" class="text-center text-muted py-3">{{ $activityUser ? 'No BTC activity for this user.' : 'Choose a user.' }}</td></tr>
            @endforelse
            </tbody></table></div></section>
    @endif
</div></div>
@endsection
