<x-theme.header />
<div id="layout-wrapper">
    <x-theme.topNavbar />
    <x-theme.sidebar />
    <div class="vertical-overlay"></div>
    <div class="main-content" id="main-content" role="main" tabindex="-1">
        @yield('content')
        <x-theme.copyrigth />
    </div>
</div>
<x-modal.menu_search />
<x-modal.ehome />
<x-theme.footer />