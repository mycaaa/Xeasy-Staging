/*
 * UX-01 (13 Nov 2026): small shared improvements for every page that uses the main layout.
 *  1. The side menu marks the page you are on and opens its group (the theme looked for relative links,
 *     ours are absolute, so nothing was ever marked).
 *  2. Pages without their own title get "<menu name> - Xeasy" in the browser tab instead of "Xeasy Web".
 *  3. A table that fails to load says so in a short message instead of the DataTables alert box.
 *  4. When the login session has expired, a data request says so and offers to sign in again
 *     (it used to fail silently or show "Server error").
 */
(function () {
    'use strict';

    function norm(p) {
        try { p = new URL(p, location.origin).pathname; } catch (e) { return ''; }
        return p.replace(/\/+$/, '') || '/';
    }

    function markActiveMenu() {
        var nav = document.getElementById('navbar-nav');
        if (!nav) return null;
        var here = norm(location.href), best = null, bestLen = -1;
        nav.querySelectorAll('a[href]').forEach(function (a) {
            var h = a.getAttribute('href');
            if (!h || h.charAt(0) === '#' || h.indexOf('javascript:') === 0) return;
            var p = norm(h);
            if (p === '/' && here !== '/') return;
            if ((here === p || here.indexOf(p + '/') === 0) && p.length > bestLen) { best = a; bestLen = p.length; }
        });
        if (!best) return null;
        nav.querySelectorAll('a.active').forEach(function (a) { a.classList.remove('active'); });
        best.classList.add('active');
        best.setAttribute('aria-current', 'page');
        var box = best.closest('.collapse.menu-dropdown');
        while (box) {
            box.classList.add('show');
            var toggle = box.parentElement && box.parentElement.querySelector(':scope > a.menu-link, :scope > a.nav-link');
            if (toggle) { toggle.classList.add('active'); toggle.classList.remove('collapsed'); toggle.setAttribute('aria-expanded', 'true'); }
            box = box.parentElement ? box.parentElement.closest('.collapse.menu-dropdown') : null;
        }
        // keep the marked item in view inside the scrolling side menu
        setTimeout(function () {
            var r = best.getBoundingClientRect();
            if (r.top < 80 || r.bottom > window.innerHeight - 40) best.scrollIntoView({ block: 'center' });
        }, 300);
        return best;
    }

    function setTitle(link) {
        var t = (document.title || '').trim();
        if (link && (t === '' || t === 'Xeasy Web' || t === 'Xeasy')) {
            var name = (link.textContent || '').replace(/\s+/g, ' ').trim();
            if (name) document.title = name + ' - Xeasy';
        }
    }

    function notify(msg, type) {
        if (window.Swal && Swal.fire) {
            Swal.fire({ toast: true, position: 'top-end', icon: type || 'error', title: msg, showConfirmButton: false, timer: 6000, timerProgressBar: true });
        } else if (window.jQuery && jQuery.toast) {
            jQuery.toast({ text: msg, icon: type || 'error', position: 'top-right', hideAfter: 6000 });
        } else {
            console.warn(msg);
        }
    }

    function tables() {
        var $ = window.jQuery;
        if (!$ || !$.fn || !$.fn.dataTable) return;
        $.fn.dataTable.ext.errMode = function (settings, techNote, message) {
            console.warn('DataTables:', message);
            var xhr = settings && settings.jqXHR;
            if (xhr && xhr.status === 419) return; // handled by the session message below
            if (xhr && xhr.status === 403) { notify('You do not have access to this list.'); return; }
            notify('This table could not be loaded. Refresh the page; if it keeps happening, tell the Xeasy team which page it was.');
        };
        $.extend(true, $.fn.dataTable.defaults, {
            // UX-02: 25 rows a page (10 meant 30+ pages on the verifier queue); pages that set their own keep it
            pageLength: 25,
            lengthMenu: [[10, 25, 50, 100], [10, 25, 50, 100]],
            language: {
                emptyTable: 'Nothing to show yet.',
                zeroRecords: 'No matching records. Try a different search or filter.',
                processing: 'Loading…',
                searchPlaceholder: 'Search this list'
            }
        });
    }

    var sessionWarned = false;
    function sessionExpiry() {
        var $ = window.jQuery;
        if (!$) return;
        $(document).ajaxError(function (e, xhr) {
            if (sessionWarned || !xhr || (xhr.status !== 419 && xhr.status !== 401)) return;
            if (xhr.status === 401 && !/json/i.test(xhr.getResponseHeader('Content-Type') || '')) return;
            sessionWarned = true;
            var go = function () { location.href = (window.XEASY_LOGIN_URL || '/') ; };
            if (window.Swal && Swal.fire) {
                Swal.fire({ icon: 'info', title: 'Your session has expired', text: 'Sign in again to continue. Anything you typed on this page was not saved.', confirmButtonText: 'Sign in again', allowOutsideClick: false }).then(go);
            } else if (window.confirm('Your session has expired. Sign in again?')) { go(); }
        });
    }

    function start() {
        setTitle(markActiveMenu());
        sessionExpiry();
    }
    tables(); // now, before the page scripts create their tables
    if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', start); else start();
})();
