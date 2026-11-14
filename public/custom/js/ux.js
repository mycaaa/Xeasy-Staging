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

    // MENU-TOGGLE-01: the < > button on the menu edge does what the three-line button did
    function menuToggle() {
        var btn = document.getElementById('xeMenuToggle');
        var ham = document.getElementById('topnav-hamburger-icon');
        if (!btn || !ham) return;
        var root = document.documentElement;
        var paint = function () {
            var size = root.getAttribute('data-sidebar-size') || 'lg';
            var open = size === 'lg' || size === 'md';
            btn.firstElementChild.className = open ? 'ri-arrow-left-s-line' : 'ri-arrow-right-s-line';
            var label = open ? 'Collapse the menu' : 'Expand the menu';
            btn.setAttribute('aria-label', label);
            btn.title = label;
            btn.setAttribute('aria-expanded', open ? 'true' : 'false');
        };
        btn.addEventListener('click', function () { ham.click(); setTimeout(paint, 30); });
        new MutationObserver(paint).observe(root, { attributes: true, attributeFilter: ['data-sidebar-size'] });
        paint();
    }

    function start() {
        setTitle(markActiveMenu());
        sessionExpiry();
        menuToggle();
    }
    tables(); // now, before the page scripts create their tables
    if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', start); else start();
})();

/*
 * MODAL-BACK-01 (14 Nov 2026): the browser's back button closes an open pop-up instead of leaving the page.
 * People pressed Back to close full-screen views (document viewer, month detail) and landed on the previous page
 * (from Dashboard back to the approver home). Each pop-up adds a history entry while it is open; Back closes the
 * top-most pop-up, and closing a pop-up any other way removes its entry again. A pop-up can opt out with
 * data-xe-no-history.
 */
(function () {
    if (!window.jQuery || !window.history || !history.pushState) return;
    var $ = window.jQuery, depth = 0, skipPop = false, skipHidden = false;
    $(document).on('shown.bs.modal', '.modal', function () {
        if (this.hasAttribute('data-xe-no-history')) return;
        try { history.pushState({ xeModal: depth + 1 }, ''); depth++; } catch (e) { }
    });
    $(document).on('hidden.bs.modal', '.modal', function () {
        if (this.hasAttribute('data-xe-no-history')) return;
        if (skipHidden) { skipHidden = false; return; }
        if (depth > 0 && history.state && history.state.xeModal) { depth--; skipPop = true; history.back(); }
    });
    window.addEventListener('popstate', function () {
        if (skipPop) { skipPop = false; return; }
        var open = $('.modal.show').not('[data-xe-no-history]').last()[0];
        if (open && depth > 0 && window.bootstrap) {
            depth--; skipHidden = true;
            window.bootstrap.Modal.getOrCreateInstance(open).hide();
        }
    });
})();

/* QA-20.5 (14 Nov 2026): every "×" close button has the accessible name "Close" (screen readers read nothing). */
(function () {
    function name(root) {
        var list = (root || document).querySelectorAll('.btn-close:not([aria-label]), [data-bs-dismiss]:not([aria-label]):empty');
        for (var i = 0; i < list.length; i++) { list[i].setAttribute('aria-label', 'Close'); if (!list[i].title) { list[i].title = 'Close'; } }
    }
    if (document.readyState === 'loading') { document.addEventListener('DOMContentLoaded', function () { name(); }); } else { name(); }
    try {
        new MutationObserver(function (muts) {
            for (var i = 0; i < muts.length; i++) { if (muts[i].addedNodes.length) { name(); return; } }
        }).observe(document.body || document.documentElement, { childList: true, subtree: true });
    } catch (e) { }
})();


/* DENY-REMARK-01 (14 Nov 2026, tracker #23): a SweetAlert box (e.g. the Deny remark) opened over a Bootstrap pop-up could not
 * keep the typing focus: the pop-up's focus trap pulled it back, so the remark could not be typed. Focus moving into a
 * SweetAlert box is no longer passed on to the trap. */
(function () {
    if (window.__xeSwalFocusFix) { return; }
    window.__xeSwalFocusFix = true;
    window.addEventListener('focusin', function (e) {
        var t = e.target;
        if (t && t.closest && t.closest('.swal2-container')) { e.stopImmediatePropagation(); }
    }, true);
})();
