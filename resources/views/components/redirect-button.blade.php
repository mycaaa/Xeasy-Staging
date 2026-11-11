@php
$tabs = [
[
'text' => 'Activity Categories',
'route' => 'activity-categories.index',
'icon' => 'mdi mdi-format-list-bulleted',
],
[
'text' => 'Activity Types',
'route' => 'activity-types.index',
'icon' => 'mdi mdi-clipboard-text',
],
[
'text' => 'Activity Names',
'route' => 'activity-names.index',
'icon' => 'mdi mdi-note-outline',
],
[
'text' => 'Expense Heads',
'route' => 'expense-heads.index',
'icon' => 'mdi mdi-file-tree',
],
[
'text' => 'Expense Head Mapping',
'route' => 'expense-head-mappings.index',
'icon' => 'mdi mdi-sitemap',
],
];
@endphp
<ul class="nav nav-tabs nav-border-top nav-border-top-primary" style="border-bottom: none" role="tablist">
    @foreach ($tabs as $tab)
    <li class="nav-item" role="presentation">
        <a href="{{ route($tab['route']) }}" class="nav-link {{ request()->routeIs($tab['route']) ? 'active' : '' }}">
            <span class="d-block d-sm-none"><i class="{{ $tab['icon'] }}"></i></span>
            <span class="d-none d-sm-block">{{ $tab['text'] }}</span>
        </a>
    </li>
    @endforeach
</ul>