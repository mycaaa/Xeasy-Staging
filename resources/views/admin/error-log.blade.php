@extends('layouts.app')

@section('content')
<div class="page-content">
    <div class="container-fluid">
        <div class="card border-0 shadow-sm">
            <div class="card-header bg-white d-flex flex-wrap align-items-center gap-2">
                <h5 class="mb-0 me-auto"><i class="ri-bug-line me-1"></i> Error log</h5>
                <form method="get" class="d-flex gap-2">
                    <input class="form-control form-control-sm" name="q" value="{{ $q }}" placeholder="Filter, e.g. btc or boarding" aria-label="Filter errors">
                    <button class="btn btn-sm btn-primary">Filter</button>
                </form>
            </div>
            <div class="card-body p-0">
                <p class="text-muted small px-3 pt-3 mb-2">Latest errors on this server, newest first (up to 200). Passwords, tokens and keys are masked.</p>
                <div class="table-responsive">
                    <table class="table table-sm table-hover align-middle mb-0">
                        <thead class="table-light"><tr><th style="width:150px">Time</th><th>Error</th><th style="width:280px">Where</th></tr></thead>
                        <tbody>
                        @forelse ($rows as $r)
                            <tr>
                                <td class="text-nowrap small">{{ $r['at'] }}</td>
                                <td class="small" style="word-break:break-word">{{ $r['message'] }}</td>
                                <td class="small text-muted" style="word-break:break-all">{{ $r['where'] }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="3" class="text-center text-muted py-4">No errors found.</td></tr>
                        @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
