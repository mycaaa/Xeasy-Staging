<button onclick="topFunction()" class="btn btn-danger btn-icon" id="back-to-top">
    <i class="ri-arrow-up-line"></i>
</button>
<div id="preloader">
    <div id="status">
        <div class="spinner-border text-white avatar-sm" role="status">
            <span class="visually-hidden"></span>
        </div>
        <span class="text-white mt-2">Loading...</span>
    </div>
</div>
<div class="modal fade" id="companyModal" tabindex="-1" aria-labelledby="companyModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header justify-content-between">
                <h5 class="modal-title" id="companyModalLabel">
                    Current - <span>{{ session('company_name') }}</span>
                    <span>({{ session('year_value') }})</span>
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <form id="companyForm">
                    <div class="mb-3">
                        <label for="companySelect" class="form-label">Company</label>
                        <select class="form-select" id="companySelect" required>
                            <option value="" disabled selected>Select a company</option>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label for="fySelect" class="form-label">Financial Year</label>
                        <select class="form-select" id="fySelect" disabled required>
                            <option value="" disabled selected>Select a financial year</option>
                        </select>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-primary btn-label waves-effect waves-light rounded-pill"
                    id="submitSelection">
                    <i class="ri-check-double-line label-icon align-middle rounded-pill fs-16 me-2">
                        <span class="loader" style="display: none;"></span>
                    </i>
                    Switch
                </button>
            </div>
        </div>
    </div>
</div>
<script src="{{ asset('vendor/cdn/jquery@3.7.1/dist/jquery.min.js') }}"></script>
<script src="{{ URL::to('/') }}/assets/libs/bootstrap/js/bootstrap.bundle.min.js"></script>
<script src="{{ URL::to('/') }}/assets/libs/simplebar/simplebar.min.js"></script>
<script src="{{ URL::to('/') }}/assets/libs/node-waves/waves.min.js"></script>
<script src="{{ URL::to('/') }}/assets/libs/feather-icons/feather.min.js"></script>
{{-- PERF-09: the animated-icon library (310 KB) only on pages that show one --}}
<script>
if (document.querySelector('lord-icon')) { var li = document.createElement('script'); li.src = "{{ URL::to('/') }}/assets/js/pages/plugins/lord-icon-2.1.0.js"; document.body.appendChild(li); }
</script>
<script src="{{ URL::to('/') }}/custom/js/main.js?v={{ filemtime(public_path('custom/js/main.js')) }}"></script>
<script src="{{ URL::to('/') }}/js/ehome-dashboard.js?v={{ filemtime(public_path('js/ehome-dashboard.js')) }}"></script>
<script src="{{ asset('vendor/cdn/select2@4.1.0-rc.0/dist/js/select2.min.js') }}"></script>
<script src="{{ asset('vendor/cdn/datatables.net@1.11.5/js/jquery.dataTables.min.js') }}"></script>
<script src="{{ asset('vendor/cdn/datatables.net-bs5@1.11.5/js/dataTables.bootstrap5.min.js') }}"></script>
<script src="{{ asset('vendor/cdn/datatables.net-responsive@2.2.9/js/dataTables.responsive.min.js') }}"></script>
<script src="{{ asset('vendor/cdn/datatables.net-buttons@2.2.2/js/dataTables.buttons.min.js') }}"></script>
<script src="{{ asset('vendor/cdn/datatables.net-fixedcolumns@4.3.0/js/dataTables.fixedColumns.min.js') }}"></script>
<script src="{{ asset('vendor/cdn/datatables.net-buttons@2.2.2/js/buttons.print.min.js') }}"></script>
<script src="{{ asset('vendor/cdn/datatables.net-buttons@2.2.2/js/buttons.html5.min.js') }}"></script>
{{-- PERF-06: pdfmake (about 2 MB) is only needed for the PDF export button; load it after the page is up --}}
<script>
(function () {
    function add(src, next) { var s = document.createElement('script'); s.src = src; s.onload = next || null; document.body.appendChild(s); }
    function load() { if (window.pdfMake && window.pdfMake.createPdf) return;
        add("{{ asset('vendor/cdn/pdfmake@0.1.53/build/pdfmake.min.js') }}", function () { add("{{ asset('vendor/cdn/pdfmake@0.1.53/build/vfs_fonts.js') }}"); }); }
    if (document.readyState === 'complete') { setTimeout(load, 300); } else { window.addEventListener('load', function () { setTimeout(load, 300); }); }
})();
</script>
<script src="{{ asset('vendor/cdn/jquery-toast-plugin@1.3.2/dist/jquery.toast.min.js') }}"></script>
<script src="{{ asset('vendor/cdn/sweetalert2@11.26.25/dist/sweetalert2.all.min.js') }}"></script>
{{-- Flatpickr - Global Load --}}
<link rel="stylesheet" href="{{ asset('vendor/cdn/flatpickr@4.6.13/dist/flatpickr.min.css') }}">
<script src="{{ asset('vendor/cdn/flatpickr@4.6.13/dist/flatpickr.min.js') }}"></script>
<style>
    /* ── Flatpickr Calendar Header ── (UI-02: navy text on the pastel header; it was white on pale blue) */
    .flatpickr-calendar {
        overflow: visible !important;
    }
    .flatpickr-months {
        background: #dde6ff !important;
        border-radius: 5px 5px 0 0;
        padding: 2px 0;
        overflow: visible !important; color: #1e3a8a;}
    .flatpickr-month {
        overflow: visible !important;
    }
    /* Prev / Next arrows */
    .flatpickr-months .flatpickr-prev-month,
    .flatpickr-months .flatpickr-next-month {
        color: #1e3a8a !important;
        fill:  #1e3a8a !important;
    }
    .flatpickr-months .flatpickr-prev-month svg path,
    .flatpickr-months .flatpickr-next-month svg path {
        fill: #1e3a8a !important;
        transition: fill .15s;
    }
    .flatpickr-months .flatpickr-prev-month:hover svg path,
    .flatpickr-months .flatpickr-next-month:hover svg path {
        fill: #001868 !important;
    }
    /* Month + Year text area */
    .flatpickr-current-month {
        color: #1e3a8a !important;
        overflow: visible !important;
    }
    /* Month native <select> */
    .flatpickr-current-month .flatpickr-monthDropdown-months {
        color: #1e3a8a !important;
        font-weight: 600;
        background: transparent !important;
        -webkit-appearance: none;
        appearance: none;
    }
    .flatpickr-current-month .flatpickr-monthDropdown-months option {
        background: #dde6ff;
        color: #1e3a8a;
    }
    /* Hide the native numInputWrapper (up/down arrows + input) */
    .flatpickr-current-month .numInputWrapper {
        display: none !important;
    }
    /* ── Custom Year Picker ── */
    .fp-year-btn {
        display: inline-flex;
        align-items: center;
        gap: 3px;
        color: #1e3a8a;
        font-size: 14px;
        font-weight: 600;
        font-family: inherit;
        cursor: pointer;
        padding: 2px 6px;
        border-radius: 4px;
        vertical-align: middle;
        user-select: none;
        transition: background .15s;
    }
    .fp-year-btn:hover {
        background: #cbd8fd;
    }
    .fp-year-btn .fp-caret {
        font-size: 10px;
        opacity: .8;
    }
    /* Dropdown panel — appended to <body> so no overflow clipping */
    .fp-year-panel {
        position: fixed;
        background: #fff;
        border: 1px solid #ddd;
        border-radius: 6px;
        box-shadow: 0 6px 20px rgba(0,0,0,0.18);
        z-index: 999999;
        max-height: 220px;
        overflow-y: auto;
        min-width: 90px;
        display: none;
    }
    .fp-year-panel.open {
        display: block;
    }
    .fp-year-panel ul {
        list-style: none;
        margin: 0;
        padding: 4px 0;
    }
    .fp-year-panel ul li {
        padding: 7px 18px;
        cursor: pointer;
        font-size: 13px;
        color: #333;
        text-align: center;
        transition: background .1s;
    }
    .fp-year-panel ul li:hover {
        background: #e8f4fb;
        color: #001868;
    }
    .fp-year-panel ul li.active {
        background: #dde6ff;
        color: #1e3a8a;
        font-weight: 700;
    }
    /* Weekday row */
    .flatpickr-weekdays {
        background: #eef2ff !important;
    }
    span.flatpickr-weekday {
        color: #1e3a8a !important;
        font-weight: 600;
        background: transparent !important;
    }
    /* Today highlight */
    .flatpickr-day.today {
        border-color: #001868;
    }
    .flatpickr-day.today:hover,
    .flatpickr-day.today:focus {
        background: #dde6ff;
        border-color: #001868;
        color: #1e3a8a;
    }
    /* Selected day */
    .flatpickr-day.selected,
    .flatpickr-day.selected:hover {
        background: #dde6ff !important;
        border-color: #001868 !important; color: #1e3a8a;}
</style>
<script>
(function () {
    var originalFlatpickr = window.flatpickr;

    /* ── one shared year-panel appended to body ── */
    var panel = document.createElement('div');
    panel.className = 'fp-year-panel';
    panel.innerHTML = '<ul></ul>';
    document.body.appendChild(panel);

    var activeBtn      = null;   // the .fp-year-btn that opened the panel
    var activeInstance = null;   // the flatpickr instance

    function positionPanel(btn) {
        var r = btn.getBoundingClientRect();
        panel.style.left = r.left + 'px';
        panel.style.top  = (r.bottom + 4) + 'px';
    }

    function openPanel(btn, instance, yearStart, yearEnd) {
        var ul = panel.querySelector('ul');
        ul.innerHTML = '';
        for (var y = yearEnd; y >= yearStart; y--) {
            var li = document.createElement('li');
            li.textContent = y;
            li.dataset.year = y;
            if (y === instance.currentYear) li.className = 'active';
            ul.appendChild(li);
        }
        activeBtn      = btn;
        activeInstance = instance;
        positionPanel(btn);
        panel.classList.add('open');
        /* scroll active year into view */
        var activeLi = ul.querySelector('.active');
        if (activeLi) activeLi.scrollIntoView({ block: 'center' });
    }

    function closePanel() {
        panel.classList.remove('open');
        activeBtn      = null;
        activeInstance = null;
    }

    /* click on a year option */
    panel.querySelector('ul').addEventListener('click', function (e) {
        var li = e.target.closest('li');
        if (!li || !activeInstance) return;
        var y = parseInt(li.dataset.year);
        activeInstance.changeYear(y);
        /* update active class */
        panel.querySelectorAll('li').forEach(function(el){ el.classList.remove('active'); });
        li.classList.add('active');
        /* update button label */
        if (activeBtn) activeBtn.querySelector('.fp-year-label').textContent = y;
        closePanel();
    });

    /* close when clicking outside */
    document.addEventListener('click', function (e) {
        if (!panel.contains(e.target) && (!activeBtn || !activeBtn.contains(e.target))) {
            closePanel();
        }
    });

    /* reposition on scroll/resize */
    window.addEventListener('scroll', function(){ if (activeBtn) positionPanel(activeBtn); }, true);
    window.addEventListener('resize', function(){ if (activeBtn) positionPanel(activeBtn); });

    /* ── inject into a flatpickr instance ── */
    function injectYearPicker(instance, config) {
        var container = instance.calendarContainer;
        if (container.querySelector('.fp-year-btn')) return;

        var numWrapper = container.querySelector('.flatpickr-current-month .numInputWrapper');
        var yearInput  = container.querySelector('.flatpickr-current-month .cur-year');
        if (!numWrapper || !yearInput) return;

        var currentYear = parseInt(yearInput.value) || new Date().getFullYear();
        var now = new Date();

        var yearStart = currentYear - 100;
        var yearEnd   = currentYear; // default cap at current year

        if (config.minDate) {
            var dMin = config.minDate === 'today' ? now : new Date(config.minDate);
            if (!isNaN(dMin)) yearStart = dMin.getFullYear();
        }
        if (config.maxDate) {
            var dMax = config.maxDate === 'today' ? now : new Date(config.maxDate);
            if (!isNaN(dMax)) yearEnd = dMax.getFullYear();
        }

        /* build the trigger button */
        var btn = document.createElement('span');
        btn.className = 'fp-year-btn';
        btn.innerHTML = '<span class="fp-year-label">' + currentYear + '</span>'
                      + '<span class="fp-caret">▼</span>';

        numWrapper.parentNode.insertBefore(btn, numWrapper.nextSibling);

        btn.addEventListener('click', function (e) {
            e.stopPropagation();
            if (panel.classList.contains('open') && activeBtn === btn) {
                closePanel();
            } else {
                openPanel(btn, instance, yearStart, yearEnd);
            }
        });

        /* sync label when prev/next arrows change the year */
        function syncYear() {
            btn.querySelector('.fp-year-label').textContent = instance.currentYear;
        }
        instance.config.onMonthChange = [].concat(instance.config.onMonthChange || []);
        instance.config.onMonthChange.push(syncYear);
        instance.config.onYearChange  = [].concat(instance.config.onYearChange  || []);
        instance.config.onYearChange.push(syncYear);
    }

    /* ── wrap flatpickr ── */
    window.flatpickr = function (selector, config) {
        config = config || {};

        // Global default: block future dates unless caller explicitly sets maxDate
        if (config.maxDate === undefined) {
            config.maxDate = 'today';
        }

        var origReady = config.onReady;
        config.onReady = function (dates, str, instance) {
            if (origReady) origReady.call(this, dates, str, instance);
            injectYearPicker(instance, config);
        };
        return originalFlatpickr(selector, config);
    };

    Object.keys(originalFlatpickr).forEach(function (k) {
        window.flatpickr[k] = originalFlatpickr[k];
    });
})();
</script>
@stack('scripts')
<script>
    // STAMP-01: Excel files built in the browser (SheetJS) also carry date, time and user
    (function () {
        var stampBy = @json(trim((string) (session('emp_name') ?: (auth()->user()->name ?? ''))) . (session('emp_code') ? ' (' . session('emp_code') . ')' : ''));
        function stampText() {
            var d = new Date(), p = function (n) { return (n < 10 ? '0' : '') + n; };
            var h = d.getHours() % 12 || 12, ap = d.getHours() < 12 ? 'AM' : 'PM';
            return 'Generated on ' + p(d.getDate()) + '/' + p(d.getMonth() + 1) + '/' + d.getFullYear() + ' ' + p(h) + ':' + p(d.getMinutes()) + ':' + p(d.getSeconds()) + ' ' + ap + ' | By: ' + (stampBy || 'User');
        }
        function patch() {
            if (!window.XLSX || !XLSX.writeFile || XLSX.writeFile.__stamped) { return; }
            var orig = XLSX.writeFile;
            XLSX.writeFile = function (wb, name, opts) {
                try {
                    (wb.SheetNames || []).forEach(function (n) {
                        var ws = wb.Sheets[n];
                        if (ws && XLSX.utils && XLSX.utils.sheet_add_aoa) { XLSX.utils.sheet_add_aoa(ws, [[''], [stampText()]], { origin: -1 }); }
                    });
                } catch (e) { console.warn('report stamp skipped', e); }
                return orig.call(this, wb, name, opts);
            };
            XLSX.writeFile.__stamped = true;
        }
        patch();
        document.addEventListener('DOMContentLoaded', patch);
        window.addEventListener('load', patch);
    })();
</script>
<script src="{{ URL::to('/') }}/assets/js/app.js"></script>
<script>window.XEASY_LOGIN_URL = "{{ route('login') }}";</script>
<script src="{{ URL::to('/') }}/custom/js/ux.js?v={{ filemtime(public_path('custom/js/ux.js')) }}"></script>
</body>

</html>