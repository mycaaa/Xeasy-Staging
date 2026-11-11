{{--
    U-05 (11 Nov 2026): one shell for the screens that open without the menu: pages shown in a pop-up window or
    a frame inside another screen (account summary, claim details, bill image, verifier claim and month report,
    running-km view, approver expense details) and the vendor boarding-pass portal (no login).
    Each screen keeps its own styles and scripts (@push('head') / @push('scripts')); the shell gives them the same
    language, character set, viewport, CSRF meta, accessibility styles and a main landmark.
--}}
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'Xeasy')</title>
    <link href="{{ asset('assets/css/a11y.css') }}" rel="stylesheet" type="text/css">
@stack('head')
</head>
<body @yield('body_attrs')>
<main id="main-content" tabindex="-1">
@yield('content')
</main>
@stack('scripts')
</body>
</html>
