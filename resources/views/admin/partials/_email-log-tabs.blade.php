{{-- PAGE-CLEAN-04 (20 Nov 2026, Page cleanup row 90): one "Email Logs" menu item; the two logs are its tabs --}}
<ul class="nav nav-tabs mb-3" role="tablist" aria-label="Email logs">
    <li class="nav-item"><a class="nav-link {{ request()->routeIs('admin.mail.logs.*') ? 'active' : '' }}" @if (request()->routeIs('admin.mail.logs.*')) aria-current="page" @endif href="{{ route('admin.mail.logs.index') }}">All mails sent</a></li>
    <li class="nav-item"><a class="nav-link {{ request()->routeIs('email.send.log*') ? 'active' : '' }}" @if (request()->routeIs('email.send.log*')) aria-current="page" @endif href="{{ route('email.send.log') }}">Notification runs (VVNR / reminders)</a></li>
</ul>
