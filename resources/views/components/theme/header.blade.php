<!doctype html>
<html lang="en" @foreach (session('theme_settings', [
    'data-layout' => 'vertical',
    'data-topbar' => 'light',
    'data-sidebar' => 'dark',
    'data-sidebar-size' => 'sm',
    'data-sidebar-image' => 'none',
    'data-preloader' => 'enable',
    'data-theme' => 'default',
    'data-theme-colors' => 'default',
    'data-bs-theme' => 'light',
    'data-layout-width' => 'fluid',
    'data-layout-position' => 'fixed',
    'data-layout-style' => 'default',
    'data-body-image' => 'none',
    'data-sidebar-visibility' => 'show',
]) as $key => $value) {{ $key }}="{{ $value }}" @endforeach>

<head>
    <meta charset="utf-8" />
    <title>@yield('title', 'Xeasy Web')</title>
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="current-year-id" content="{{ session('year_id', '') }}">
    <meta name="current-company-id" content="{{ session('company_id', '') }}">
    <meta name="company-id" content="{{ session('company_id', '') }}">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="shortcut icon" href="{{ URL::to('/') }}/custom/favicon.png">
    <script src="{{ URL::to('/') }}/assets/js/layout.js"></script>
    <link href="{{ URL::to('/') }}/assets/css/bootstrap.min.css" rel="stylesheet" type="text/css" />
    <link href="{{ URL::to('/') }}/assets/css/icons.min.css" rel="stylesheet" type="text/css" />
    <link href="{{ URL::to('/') }}/assets/css/app.min.css" rel="stylesheet" type="text/css" />
    <link href="{{ URL::to('/') }}/assets/css/custom.min.css" rel="stylesheet" type="text/css" />
    <link href="{{ URL::to('/') }}/assets/css/a11y.css" rel="stylesheet" type="text/css" /><!-- U-03 -->
    <meta name="xeasy-year-id" content="{{ (int) session('year_id') }}"><!-- PAX-YEAR-01 -->
    <link href="{{ asset('vendor/cdn/select2@4.1.0-rc.0/dist/css/select2.min.css') }}" rel="stylesheet" />
    <link rel="stylesheet" href="{{ asset('vendor/cdn/datatables.net-bs5@1.11.5/css/dataTables.bootstrap5.min.css') }}" />
    <link rel="stylesheet" href="{{ asset('vendor/cdn/datatables.net-responsive-bs@2.2.9/css/responsive.bootstrap.min.css') }}" />
    <link rel="stylesheet" href="{{ asset('vendor/cdn/datatables.net-buttons-dt@2.2.2/css/buttons.dataTables.min.css') }}">
    <link rel="stylesheet" href="{{ asset('vendor/cdn/jquery-toast-plugin@1.3.2/dist/jquery.toast.min.css') }}">
    <link rel="stylesheet" href="{{ asset('vendor/cdn/viewerjs@1.11.7/dist/viewer.min.css') }}" />
    <link rel="stylesheet" href="{{ asset('vendor/cdn/datatables.net-fixedcolumns-dt@4.3.0/css/fixedColumns.dataTables.min.css') }}">

    @stack('styles')
</head>

<body>
<a class="skip-link" href="#main-content">Skip to main content</a>
