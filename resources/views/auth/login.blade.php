<!doctype html>
<html lang="en" data-layout="vertical" data-topbar="light" data-sidebar="dark" data-sidebar-size="lg"
    data-sidebar-image="none" data-preloader="disable" data-theme="default" data-theme-colors="default">

<head>
    <meta charset="utf-8" />
    <title>Sign In</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="shortcut icon" href="assets/images/favicon.ico">
    <script src="assets/js/layout.js"></script>
    <link href="assets/css/bootstrap.min.css" rel="stylesheet" type="text/css" />
    <link href="assets/css/icons.min.css" rel="stylesheet" type="text/css" />
    <link href="assets/css/app.min.css" rel="stylesheet" type="text/css" />
    <link href="assets/css/custom.min.css" rel="stylesheet" type="text/css" />
</head>

<body>
    <div class="auth-page-wrapper auth-bg-cover py-5 d-flex justify-content-center align-items-center min-vh-100">
        <div class="bg-overlay"></div>
        <div class="auth-page-content">
            <div class="container">
                <div class="row">
                    <div class="col-lg-12">
                        <div class="text-center">
                            <div>
                                <a href="{{ URL::to('/login') }}" class="d-inline-block auth-logo">
                                    <img src="{{ URL::to('/') }}/custom/xeasy_logo_ligth.png" alt=""
                                        height="100">
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="row justify-content-center">
                    <div class="col-md-8 col-lg-6 col-xl-5">
                        <div class="card mt-4 card-bg-fill">
                            <div class="card-body p-4">
                                <div class="text-center mt-2">
                                    <h5 class="text-primary">Welcome Back !</h5>
                                    <p class="text-muted">Sign in to continue to Xeasy.</p>
                                </div>
                                <div class="p-2 mt-4">
                                    @if (session('success'))
                                        <div class="alert alert-success">
                                            {{ session('success') }}
                                        </div>
                                    @endif

                                    <form method="POST" action="{{ route('login') }}">
                                        @csrf
                                        <input type="hidden" name="company" value="1">

                                        <div class="mb-3">
                                            <label for="employeeid" class="form-label">Employee ID</label>
                                            <input type="text"
                                                class="form-control @error('employeeid') is-invalid @enderror"
                                                id="employeeid" name="employeeid" placeholder="Enter employee ID"
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

                                        

                                        <div class="mb-3">
                                            <label for="financial_year" class="form-label">Financial Year</label>
                                            <select name="financial_year" id="financial_year"
                                                class="form-select @error('financial_year') is-invalid @enderror"
                                                required>
                                                <option value="8"
                                                    {{ old('financial_year', '8') == '8' ? 'selected' : '' }}>
                                                    2026-2027
                                                </option>
                                                <option value="7"
                                                    {{ old('financial_year') == '7' ? 'selected' : '' }}>
                                                    2025-2026
                                                </option>
                                                <option value="6"
                                                    {{ old('financial_year') == '6' ? 'selected' : '' }}>
                                                    2024-2025
                                                </option>
                                            </select>
                                            @error('financial_year')
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
                                    <p class="text-center small mt-3 mb-0"><a href="{{ route('privacy.notice') }}" target="_blank" rel="noopener">Privacy notice</a> &middot; how Xeasy uses your data</p>
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
    <script src="assets/libs/bootstrap/js/bootstrap.bundle.min.js"></script>
    <script src="assets/libs/simplebar/simplebar.min.js"></script>
    <script src="assets/libs/node-waves/waves.min.js"></script>
    <script src="assets/libs/feather-icons/feather.min.js"></script>
    <script src="assets/js/pages/plugins/lord-icon-2.1.0.js"></script>
    <script src="assets/js/plugins.js"></script>
    <script src="assets/js/pages/password-addon.init.js"></script>
</body>

</html>
