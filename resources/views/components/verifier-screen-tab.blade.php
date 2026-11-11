@php
$tabs = [
[
'text' => 'Pending',
'route' => 'verify-pending',
'icon' => 'mdi mdi-map-marker',
'permission' => 'verify_pending_list',
],
[
'text' => 'Approved',
'route' => 'verify-approved',
'icon' => 'mdi mdi-office-building',
'permission' => 'verify_approved_list',
],

];
@endphp

<ul class="nav nav-tabs nav-border-top nav-border-top-primary" style="border-bottom: none" role="tablist">
    @foreach ($tabs as $tab)
    @can($tab['permission'])
    <li class="nav-item" role="presentation">
        <a href="{{ route($tab['route']) }}"
            class="nav-link {{ request()->routeIs($tab['route']) ? 'active' : '' }}">
            <span class="d-block d-sm-none"><i class="{{ $tab['icon'] }}"></i></span>
            <span class="d-none d-sm-block">{{ $tab['text'] }}</span>
        </a>
    </li>
    @endcan
    @endforeach
</ul>