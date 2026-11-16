{{-- DB-SETUP-01: a table or column this page needs is missing on this server --}}
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Database update needed</title>
    <style>
        body { margin: 0; min-height: 100vh; display: flex; align-items: center; justify-content: center; background: #f6f9fc;
               font-family: system-ui, -apple-system, 'Segoe UI', sans-serif; color: #334155; }
        .box { max-width: 520px; margin: 16px; padding: 28px 30px; background: #fff; border: 1px solid #e2e8f0; border-radius: 14px; }
        h1 { font-size: 1.05rem; margin: 0 0 10px; color: #1e293b; }
        p { font-size: .9rem; line-height: 1.55; margin: 0 0 14px; }
        a { color: #15648C; font-weight: 600; }
    </style>
</head>
<body>
<div class="box">
    <h1>Database update needed</h1>
    <p>{{ $message }}</p>
    @if (auth()->check() && method_exists(auth()->user(), 'hasRole') && auth()->user()->hasRole('Super Admin'))
        <p><a href="{{ url('/admin/db-setup') }}">Open Database setup</a></p>
    @endif
    <p><a href="{{ url('/') }}">Back to Xeasy</a></p>
</div>
</body>
</html>
