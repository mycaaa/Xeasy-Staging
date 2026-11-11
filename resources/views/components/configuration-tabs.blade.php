@php
$tabs = [
    [
        'text' => 'State Codes',
        'route' => 'state-code-mappings.index',
        'icon' => 'mdi mdi-map-marker',
        'permission' => 'state_code_mapping_list',
    ],
    [
        'text' => 'Department Codes',
        'route' => 'department-code-mappings.index',
        'icon' => 'mdi mdi-office-building',
        'permission' => 'department_code_mapping_list',
    ],
    [
        'text' => 'Claim Focus',
        'route' => 'claim-focus-mappings.index',
        'icon' => 'mdi mdi-file-document-multiple',
        'permission' => 'claim_focus_mapping_list',
    ],

    // [
    //     'text' => 'Field Promotion',
    //     'route' => 'state-field-promotion-mappings.index',
    //     'icon' => 'mdi mdi-bullhorn',
    //     'permission' => 'state_field_promotion_mapping_list',
    // ],

    // [
    //     'text' => 'Some Other Tab',
    //     'route' => 'another-route',
    //     'icon' => 'mdi mdi-example',
    //     'permission' => 'another_permission',
    // ],
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