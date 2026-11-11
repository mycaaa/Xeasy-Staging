<!doctype html>
<html lang="en" data-layout="vertical" data-topbar="light" data-sidebar="dark" data-sidebar-size="lg"
    data-sidebar-image="none" data-preloader="disable" data-theme="default" data-theme-colors="default">

<head>
    <meta charset="utf-8" />
    <title>Sign In</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="shortcut icon" href="{{ URL::to('/') }}/assets/images/favicon.ico">
    <script src="{{ URL::to('/') }}/assets/js/layout.js"></script>
    <link href="{{ URL::to('/') }}/assets/css/bootstrap.min.css" rel="stylesheet" type="text/css" />
    <link href="{{ URL::to('/') }}/assets/css/icons.min.css" rel="stylesheet" type="text/css" />
    <link href="{{ URL::to('/') }}/assets/css/app.min.css" rel="stylesheet" type="text/css" />
    <link href="{{ URL::to('/') }}/assets/css/custom.min.css" rel="stylesheet" type="text/css" />
    <link href="{{ asset('vendor/cdn/select2@4.1.0-rc.0/dist/css/select2.min.css') }}" rel="stylesheet" />
</head>

<body>
    <div class="auth-page-wrapper auth-bg-cover py-5 d-flex justify-content-center align-items-center min-vh-100">
        <div class="bg-overlay"></div>
        <div class="auth-page-content">
            <div class="container">
                <div class="row">
                    <div class="col-lg-12">
                        <div class="text-center">
                            <a href="{{ URL::to('/') }}" class="d-inline-block auth-logo">
                                <img src="{{ URL::to('/') }}/custom/xeasy_logo_ligth.png" alt="Xeasy Logo"
                                    height="100">
                            </a>
                        </div>
                    </div>
                </div>
                <div class="row justify-content-center">
                    <div class="col-md-8 col-lg-6 col-xl-5">
                        <div class="card mt-4 card-bg-fill">
                            <div class="card-body p-4">
                                <div class="text-center mt-2">
                                    <h5 class="text-primary">Welcome Back!</h5>
                                    <p class="text-muted">Sign in to continue to Xeasy.</p>
                                </div>
                                <div class="p-2 mt-4">
                                    <form method="POST" action="{{ route('login') }}">
                                        @csrf
                                        <div class="mb-3">
                                            <label for="company" class="form-label">Company</label>
                                            <select name="company" id="company"
                                                class="form-control @error('company') is-invalid @enderror" required>
                                                <option value="1" {{ old('company') == '1' ? 'selected' : '' }}>
                                                    VNR Seeds Pvt Ltd</option>
                                                <option value="3" {{ old('company') == '3' ? 'selected' : '' }}>
                                                    VNR Nursery Pvt Ltd</option>
                                                <option value="11" {{ old('company') == '11' ? 'selected' : '' }}>
                                                    VVNR</option>
                                            </select>
                                            @error('company')
                                                <span class="invalid-feedback d-block" role="alert">
                                                    <strong>{{ $message }}</strong>
                                                </span>
                                            @enderror
                                        </div>
                                        <div class="mb-3">
                                            <label for="financial_year" class="form-label">Financial Year</label>
                                            <select name="financial_year" id="financial_year"
                                                class="form-control @error('financial_year') is-invalid @enderror"
                                                required>
                                                @if (isset($vnr_financial_years) && $vnr_financial_years->count() > 0)
                                                    @foreach ($vnr_financial_years as $year)
                                                        <option value="{{ $year->YearId }}"
                                                            {{ old('financial_year') == $year->YearId ? 'selected' : '' }}>
                                                            {{ $year->Year }}
                                                        </option>
                                                    @endforeach
                                                @endif
                                            </select>
                                            @error('financial_year')
                                                <span class="invalid-feedback d-block" role="alert">
                                                    <strong>{{ $message }}</strong>
                                                </span>
                                            @enderror
                                        </div>
                                        <div class="mb-3">
                                            <label for="employeeid" class="form-label">Employee ID / Guest Username</label>
                                            <input type="text"
                                                class="form-control @error('employeeid') is-invalid @enderror"
                                                id="employeeid" name="employeeid" placeholder="Enter employee ID or guest username"
                                                value="{{ old('employeeid') }}" required autocomplete="username"
                                                autofocus>
                                            @error('employeeid')
                                                <span class="invalid-feedback d-block" role="alert">
                                                    <strong>{{ $message }}</strong>
                                                </span>
                                            @enderror
                                        </div>
                                        <div class="mb-3">
                                            <label class="form-label" for="password">Password</label>
                                            <div class="position-relative auth-pass-inputgroup mb-3">
                                                <input type="password"
                                                    class="form-control pe-5 password-input @error('password') is-invalid @enderror"
                                                    id="password" name="password" placeholder="Enter password" required
                                                    autocomplete="current-password">
                                                <button
                                                    class="btn btn-link position-absolute end-0 top-0 text-decoration-none text-muted password-addon material-shadow-none"
                                                    type="button" id="password-addon">
                                                    <i class="ri-eye-fill align-middle"></i>
                                                </button>
                                            </div>
                                            @error('password')
                                                <span class="invalid-feedback d-block" role="alert">
                                                    <strong>{{ $message }}</strong>
                                                </span>
                                            @enderror
                                        </div>
                                        <div class="form-check mb-3">
                                            <input class="form-check-input" type="checkbox" id="remember"
                                                name="remember" {{ old('remember') ? 'checked' : '' }}>
                                            <label class="form-check-label" for="remember">
                                                Remember me
                                            </label>
                                        </div>
                                        <div class="mt-4">
                                            <button class="btn btn-primary w-100" type="submit">Sign In</button>
                                        </div>
                                    </form>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <footer class="footer galaxy-border-none">
            <div class="container">
                <div class="row">
                    <div class="col-lg-12">
                        <div class="text-center">
                            <p class="mb-0">
                                &copy;
                                <script>
                                    document.write(new Date().getFullYear())
                                </script> Xeasy – Employee Expense Management Software by VNR Seeds Pvt.
                                Ltd.
                            </p>
                        </div>
                    </div>
                </div>
            </div>
        </footer>
    </div>
    <script src="{{ asset('vendor/cdn/jquery@3.7.1/dist/jquery.min.js') }}"></script>
    <script src="{{ URL::to('/') }}/assets/libs/bootstrap/js/bootstrap.bundle.min.js"></script>
    <script src="{{ URL::to('/') }}/assets/libs/simplebar/simplebar.min.js"></script>
    <script src="{{ URL::to('/') }}/assets/libs/node-waves/waves.min.js"></script>
    <script src="{{ URL::to('/') }}/assets/libs/feather-icons/feather.min.js"></script>
    <script src="{{ URL::to('/') }}/assets/js/pages/plugins/lord-icon-2.1.0.js"></script>
    <script src="{{ URL::to('/') }}/assets/js/plugins.js"></script>
    <script src="{{ URL::to('/') }}/assets/js/pages/password-addon.init.js"></script>
    <script src="{{ asset('vendor/cdn/select2@4.1.0-rc.0/dist/js/select2.min.js') }}"></script>
    <script src="{{ URL::to('/') }}/custom/js/main.js"></script>
    <script>
        $(document).ready(function() {
            // Store financial years data for all three companies
            const companyYears = {
                1: @json($vnr_financial_years ?? []), // VNR Seeds Pvt Ltd
                3: @json($vnr_nursery_financial_years ?? []), // VNR Nursery Pvt Ltd
                11: @json($vvnr_financial_years ?? []) // VVNR
            };

            // Function to populate financial year dropdown based on company
            function loadFinancialYears(companyId) {
                const financialYearSelect = $('#financial_year');
                const years = companyYears[companyId] || [];

                // Clear existing options
                financialYearSelect.empty();

                // Add new options
                years.forEach(function(year) {
                    const option = $('<option></option>')
                        .attr('value', year.YearId)
                        .text(year.Year);
                    financialYearSelect.append(option);
                });

                // Set default year based on company
                setDefaultYear(companyId);
            }

            // Function to set default financial year based on company
            function setDefaultYear(companyId) {
                const financialYearSelect = $('#financial_year');
                const years = companyYears[companyId] || [];

                if (years.length === 0) return;

                let defaultYearId;

                // Use most recent year (first item since ordered desc) for all companies
                defaultYearId = years[0].YearId;

                // Optional: Add specific logic for VVNR if needed
                if (companyId == 11) {
                    // VVNR: Prefer 2023-2024 if available, otherwise use most recent
                    const preferred = years.find(year => year.Year.includes('2023-2024'));
                    if (preferred) {
                        defaultYearId = preferred.YearId;
                    }
                }

                if (defaultYearId) {
                    financialYearSelect.val(defaultYearId);
                }
            }

            // Function to handle company change
            function onCompanyChange() {
                const companyValue = $('#company').val();
                if (companyValue) {
                    loadFinancialYears(parseInt(companyValue));
                }
            }

            // Set initial financial year on page load if no old value exists
            @if (!old('financial_year'))
                const initialCompany = $('#company').val() || '1';
                setDefaultYear(parseInt(initialCompany));
            @endif

            // Handle company selection changes
            $('#company').on('change', onCompanyChange);
        });
    </script>
</body>

</html>
