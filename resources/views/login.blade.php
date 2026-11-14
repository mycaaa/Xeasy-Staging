<!doctype html>
{{-- LOGIN-UI-01 (13 Nov 2026): new sign-in page. Same form fields and names as before (company, financial_year,
     employeeid, password, remember); self-contained styles so the theme CSS no longer loads here. --}}
<html lang="en">

<head>
    <meta charset="utf-8" />
    <title>Sign in to Xeasy</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="color-scheme" content="light">
    <link rel="shortcut icon" href="{{ URL::to('/') }}/assets/images/favicon.ico">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Hanken+Grotesk:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link href="{{ URL::to('/') }}/assets/css/icons.min.css" rel="stylesheet" type="text/css" />
    <style>
        :root {
            /* PASTEL-01 palette (assets/css/pastel.css) */
            --navy: #1e3a8a;          /* pastel primary text */
            --navy-2: #172f73;
            --sky: #dde6ff;           /* pastel primary background */
            --sky-bd: #c2d1fb;
            --sky-hv: #cbd8fd;
            --navy-line: #c2d1fb;
            --ink: #1f2937;
            --muted: #5b6478;
            --paper: #f7f9fe;
            --line: #d7dbe3;
            --field-bg: #d7f3e6; --field-tx: #065f46; --field-bd: #b5e6cf;
            --sun-bg: #fdefd2; --sun-tx: #7a4500; --sun-bd: #f8dca3;
            --danger: #b42318;
            --radius: 10px;
        }
        * { box-sizing: border-box; }
        html, body { margin: 0; min-height: 100%; }
        body {
            font-family: "Hanken Grotesk", system-ui, -apple-system, "Segoe UI", Roboto, sans-serif;
            color: var(--ink);
            background: var(--paper);
            font-size: 15px;
            line-height: 1.5;
            -webkit-font-smoothing: antialiased;
        }
        .shell { display: grid; grid-template-columns: minmax(360px, 44%) 1fr; min-height: 100vh; }

        /* ── left: what Xeasy is, and the path every claim takes ── */
        .story {
            background: var(--sky);
            color: var(--navy);
            padding: 44px 56px;
            display: flex;
            flex-direction: column;
            position: relative;
            overflow: hidden;
        }
        .story::after { /* faint rows, like a sown field seen from above */
            content: "";
            position: absolute; inset: 0;
            background: repeating-linear-gradient(-28deg, transparent 0 46px, rgba(30,58,138,.045) 46px 48px);
            pointer-events: none;
        }
        .story > * { position: relative; z-index: 1; }
        .brand { display: flex; align-items: center; gap: 14px; text-decoration: none; color: var(--navy); }
        /* BRAND-01: the VNR mark on a white tile, then the Xeasy product name */
        .brand .vnr { background: #fff; border-radius: 12px; padding: 8px 10px; box-shadow: 0 1px 0 #c2d1fb; display: grid; place-items: center; }
        .brand .vnr img { width: 50px; height: 66px; object-fit: contain; display: block; }
        .brand .sep { width: 1px; align-self: stretch; background: #b4c5f6; margin: 6px 0; }
        .brand .product { display: flex; align-items: center; gap: 8px; }
        .brand .product img { width: 56px; height: 56px; object-fit: contain; margin: -6px; }
        .brand span { font-weight: 700; font-size: 15px; opacity: .85; line-height: 1.25; }
        .story h2 {
            font-size: clamp(30px, 3.1vw, 44px);
            line-height: 1.08;
            letter-spacing: -.02em;
            font-weight: 800;
            margin: auto 0 14px;
            max-width: 15ch;
        }
        .story .lede { margin: 0 0 36px; max-width: 42ch; color: #3b4f8f; font-size: 16px; }

        .path { list-style: none; margin: 0 0 auto; padding: 0; max-width: 360px; }
        .path li {
            display: grid; grid-template-columns: 22px 1fr; gap: 14px;
            padding-bottom: 14px; position: relative; font-size: 14.5px;
        }
        .path li::before { /* the line joining the stages */
            content: ""; position: absolute; left: 10px; top: 18px; bottom: -2px; width: 2px; background: #b4c5f6;
        }
        .path li:last-child::before { display: none; }
        .path .dot {
            width: 22px; height: 22px; border-radius: 50%;
            border: 2px solid #9fb3ef; background: #fff;
            display: grid; place-items: center; font-size: 11px; font-weight: 700; color: var(--navy);
        }
        .path li:first-child .dot { background: var(--sun-bg); border-color: var(--sun-bd); color: var(--sun-tx); }
        .path li:last-child .dot { background: var(--field-bg); border-color: var(--field-bd); color: var(--field-tx); }
        .path b { display: block; font-weight: 600; }
        .path small { color: #4d5f9a; font-size: 13px; }
        .story .foot { margin-top: 32px; font-size: 12.5px; color: #5a6aa0; }

        /* ── right: the form ── */
        .panel { display: flex; align-items: center; justify-content: center; padding: 48px 24px; }
        .card { width: 100%; max-width: 400px; }
        .card h1 { font-size: 28px; letter-spacing: -.015em; line-height: 1.15; margin: 0 0 6px; font-weight: 800; }
        .card .sub { margin: 0 0 28px; color: var(--muted); }
        .field { margin-bottom: 18px; }
        .field > label, .field > .label { display: block; font-weight: 600; font-size: 14px; margin-bottom: 6px; }
        .input, select.input {
            width: 100%; height: 46px; padding: 0 14px;
            font: inherit; color: var(--ink);
            background: #fff; border: 1.5px solid var(--line); border-radius: var(--radius);
            transition: border-color .15s, box-shadow .15s;
        }
        select.input {
            appearance: none; padding-right: 40px;
            background: #fff url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='12' height='8' viewBox='0 0 12 8'%3E%3Cpath d='M1 1l5 5 5-5' stroke='%235b6478' stroke-width='1.8' fill='none' stroke-linecap='round'/%3E%3C/svg%3E") no-repeat right 14px center;
        }
        .input:hover { border-color: #b9c2d4; }
        .input:focus { outline: none; border-color: var(--navy-2); box-shadow: 0 0 0 4px rgba(19, 41, 122, .14); }
        .input.is-invalid { border-color: var(--danger); }
        .error { color: var(--danger); font-size: 13px; margin-top: 6px; }

        /* company as a three-way switch: one tap, always visible */
        .companies { display: grid; grid-template-columns: repeat(3, 1fr); gap: 0; border: 1.5px solid var(--line); border-radius: var(--radius); background: #fff; padding: 3px; }
        .companies input { position: absolute; opacity: 0; pointer-events: none; }
        .companies label {
            display: flex; align-items: center; justify-content: center; text-align: center;
            min-height: 40px; padding: 4px 8px; border-radius: 7px; cursor: pointer;
            font-size: 13.5px; font-weight: 600; color: var(--muted); line-height: 1.2;
            transition: background .15s, color .15s;
        }
        .companies label:hover { color: var(--ink); }
        .companies input:checked + label { background: var(--sky); color: var(--navy); box-shadow: inset 0 0 0 1.5px var(--sky-bd); }
        .companies input:focus-visible + label { outline: 3px solid rgba(19, 41, 122, .35); outline-offset: 1px; }

        .pass { position: relative; }
        .pass .input { padding-right: 48px; }
        .pass button {
            position: absolute; right: 4px; top: 4px; width: 38px; height: 38px;
            border: 0; background: transparent; border-radius: 8px; color: var(--muted); cursor: pointer; font-size: 18px;
        }
        .pass button:hover { color: var(--ink); background: var(--paper); }
        .pass button:focus-visible { outline: 3px solid rgba(19, 41, 122, .35); }

        .row { display: flex; align-items: center; justify-content: space-between; gap: 12px; margin: 4px 0 22px; }
        .check { display: inline-flex; align-items: center; gap: 8px; font-size: 14px; cursor: pointer; }
        .check input { width: 17px; height: 17px; accent-color: var(--navy); }

        .btn {
            width: 100%; height: 48px; border: 1.5px solid var(--sky-bd); border-radius: var(--radius);
            background: var(--sky); color: var(--navy); font: inherit; font-weight: 700; font-size: 15.5px; cursor: pointer;
            transition: background .15s, transform .05s;
        }
        .btn:hover { background: var(--sky-hv); }
        .btn:active { transform: translateY(1px); }
        .btn:focus-visible { outline: 3px solid rgba(19, 41, 122, .4); outline-offset: 2px; }
        .btn[disabled] { opacity: .7; cursor: progress; }

        .notice { margin: 0 0 20px; padding: 12px 14px; border-radius: var(--radius); font-size: 14px; }
        .notice.err { background: #fde2e2; color: #9b1c1c; border: 1px solid #f8c4c4; }
        .notice.ok { background: var(--field-bg); color: var(--field-tx); border: 1px solid var(--field-bd); }
        .small { margin-top: 22px; font-size: 13px; color: var(--muted); text-align: center; }
        .small a { color: var(--navy-2); font-weight: 600; }

        @media (max-width: 900px) {
            .shell { grid-template-columns: 1fr; }
            .story { padding: 22px 20px 26px; }
            .story h2 { font-size: 24px; margin: 18px 0 6px; max-width: none; }
            .brand .vnr img { width: 40px; height: 53px; }
            .story .lede { margin: 0; font-size: 14.5px; }
            .path, .story .foot { display: none; }
            .panel { padding: 28px 16px 40px; align-items: flex-start; }
        }
        @media (prefers-reduced-motion: reduce) { * { transition: none !important; } }
    </style>
</head>

<body>
    @php
        $oldCompany = (string) old('company', '1');
        $processSteps = [
            ['Bill upload', 'You add the bill or odometer photo'],
            ['Punching check', 'The punching team reads and checks it'],
            ['Your confirmation', 'You mark each claim OK, then submit the month'],
            ['Verification', 'Checked against policy'],
            ['Manager approval', 'Your reporting manager approves'],
            ['Finance', 'Approved for payment'],
            ['Paid', 'Paid to your account'],
        ];
    @endphp
    <div class="shell">
        <aside class="story" aria-label="About Xeasy">
            <a href="{{ URL::to('/') }}" class="brand">
                <span class="vnr"><img src="{{ URL::to('/') }}/custom/vnr_logo_sm.png" srcset="{{ URL::to('/') }}/custom/vnr_logo.png 2x" alt="VNR"></span>
                <span class="sep" aria-hidden="true"></span>
                <span class="product">
                    <img src="{{ URL::to('/') }}/custom/xeasy_logo_dark.png" alt="Xeasy">
                    <span>Expense claims<br>for VNR teams</span>
                </span>
            </a>
            <h2>Every claim, from bill to payment.</h2>
            <p class="lede">Upload bills, confirm what was punched, and follow each month through verification, approval and payment.</p>
            <ol class="path">
                @foreach ($processSteps as $i => [$title, $hint])
                    <li><span class="dot" aria-hidden="true">{{ $i + 1 }}</span><span><b>{{ $title }}</b><small>{{ $hint }}</small></span></li>
                @endforeach
            </ol>
            <p class="foot">&copy; {{ date('Y') }} VNR Seeds Pvt. Ltd.</p>
        </aside>

        <main class="panel">
            <div class="card">
                <h1>Sign in to Xeasy</h1>
                <p class="sub">Use your employee ID and Xeasy password.</p>

                @if (session('error'))
                    <div class="notice err" role="alert">{{ session('error') }}</div>
                @endif
                @if (session('success'))
                    <div class="notice ok" role="status">{{ session('success') }}</div>
                @endif

                <form method="POST" action="{{ route('login') }}" id="loginForm" novalidate>
                    @csrf
                    <div class="field">
                        <span class="label" id="companyLabel">Company</span>
                        <div class="companies" role="radiogroup" aria-labelledby="companyLabel">
                            @foreach (['1' => 'VNR Seeds', '3' => 'VNR Nursery', '11' => 'VVNR'] as $id => $name)
                                <input type="radio" name="company" id="company{{ $id }}" value="{{ $id }}" {{ $oldCompany === (string) $id ? 'checked' : '' }} required>
                                <label for="company{{ $id }}">{{ $name }}</label>
                            @endforeach
                        </div>
                        @error('company') <div class="error" role="alert">{{ $message }}</div> @enderror
                    </div>

                    <div class="field">
                        <label for="financial_year">Financial year</label>
                        <select name="financial_year" id="financial_year" class="input @error('financial_year') is-invalid @enderror" required>
                            @foreach ($vnr_financial_years ?? [] as $year)
                                <option value="{{ $year->YearId }}" {{ old('financial_year') == $year->YearId ? 'selected' : '' }}>{{ $year->Year }}</option>
                            @endforeach
                        </select>
                        @error('financial_year') <div class="error" role="alert">{{ $message }}</div> @enderror
                    </div>

                    <div class="field">
                        <label for="employeeid">Employee ID or guest username</label>
                        <input type="text" class="input @error('employeeid') is-invalid @enderror" id="employeeid" name="employeeid"
                            value="{{ old('employeeid') }}" required autocomplete="username" autofocus inputmode="text" spellcheck="false">
                        @error('employeeid') <div class="error" role="alert">{{ $message }}</div> @enderror
                    </div>

                    <div class="field">
                        <label for="password">Password</label>
                        <div class="pass">
                            <input type="password" class="input @error('password') is-invalid @enderror" id="password" name="password"
                                required autocomplete="current-password">
                            <button type="button" id="togglePass" aria-label="Show password" aria-pressed="false"><i class="ri-eye-line" aria-hidden="true"></i></button>
                        </div>
                        @error('password') <div class="error" role="alert">{{ $message }}</div> @enderror
                    </div>

                    <div class="row">
                        <label class="check"><input type="checkbox" id="remember" name="remember" {{ old('remember') ? 'checked' : '' }}> Keep me signed in</label>
                    </div>

                    <button class="btn" type="submit" id="signInBtn">Sign in</button>
                </form>

                <p class="small">See how Xeasy uses your data in the <a href="{{ route('privacy.notice') }}" target="_blank" rel="noopener">privacy notice</a>.</p>
            </div>
        </main>
    </div>

    <script>
        (function () {
            // Financial years differ by company: each company's list, newest first
            var years = {
                1: @json($vnr_financial_years ?? []),
                3: @json($vnr_nursery_financial_years ?? []),
                11: @json($vvnr_financial_years ?? [])
            };
            var sel = document.getElementById('financial_year');
            var keepOld = @json((bool) old('financial_year'));

            function fill(company) {
                var list = years[company] || [];
                var current = sel.value;
                sel.innerHTML = '';
                list.forEach(function (y) {
                    var o = document.createElement('option');
                    o.value = y.YearId; o.textContent = y.Year; sel.appendChild(o);
                });
                if (keepOld && list.some(function (y) { return String(y.YearId) === current; })) {
                    sel.value = current;
                } else if (list.length) {
                    var pick = list[0];
                    if (String(company) === '11') { // VVNR works in 2023-2024 by default when it exists
                        pick = list.find(function (y) { return String(y.Year).indexOf('2023-2024') !== -1; }) || pick;
                    }
                    sel.value = pick.YearId;
                }
            }
            document.querySelectorAll('input[name="company"]').forEach(function (r) {
                r.addEventListener('change', function () { keepOld = false; fill(r.value); });
            });
            var checked = document.querySelector('input[name="company"]:checked');
            fill(checked ? checked.value : '1');

            var pass = document.getElementById('password');
            var tog = document.getElementById('togglePass');
            tog.addEventListener('click', function () {
                var show = pass.type === 'password';
                pass.type = show ? 'text' : 'password';
                tog.setAttribute('aria-pressed', show ? 'true' : 'false');
                tog.setAttribute('aria-label', show ? 'Hide password' : 'Show password');
                tog.firstElementChild.className = show ? 'ri-eye-off-line' : 'ri-eye-line';
            });

            var form = document.getElementById('loginForm');
            form.addEventListener('submit', function (e) {
                var id = document.getElementById('employeeid');
                if (!id.value.trim()) { e.preventDefault(); id.focus(); id.classList.add('is-invalid'); return; }
                if (!pass.value) { e.preventDefault(); pass.focus(); pass.classList.add('is-invalid'); return; }
                var b = document.getElementById('signInBtn');
                b.disabled = true; b.textContent = 'Signing in…';
            });
        })();
    </script>
</body>

</html>
