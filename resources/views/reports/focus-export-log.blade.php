{{-- FOCUS-LOG-01: read-only Focus export log --}}
@extends('layouts.app')

@section('content')
    <div class="col-md-12 h-100 p-3 mt-5">
        <div class="row card mt-2">
            <div class="col-lg-12 shadow p-3">
                <h5 class="mb-1">Focus export log</h5>
                <p class="text-muted small mb-2">Every Focus export (claim and payment), newest first. Entries cannot be edited or deleted. The hash identifies the exact file (or the exported rows for downloads).</p>
                <form method="get" class="d-flex gap-2 mb-2">
                    <select name="type" class="form-select form-select-sm" style="max-width:240px">
                        <option value="">All export types</option>
                        @foreach ($types as $t)
                            <option value="{{ $t }}" @selected(request('type') === $t)>{{ $t }}</option>
                        @endforeach
                    </select>
                    <button class="btn btn-sm btn-primary">Filter</button>
                </form>
                <div class="table-responsive">
                    <table class="table table-sm table-bordered small">
                        <thead class="table-light">
                            <tr><th>When</th><th>Type</th><th>Year</th><th>File</th><th class="text-end">Rows</th><th class="text-end">Total ₹</th><th>By</th><th>Filters</th><th>Hash</th></tr>
                        </thead>
                        <tbody>
                            @forelse ($logs as $l)
                                <tr>
                                    <td class="text-nowrap">{{ \Carbon\Carbon::parse($l->created_at)->format('d-m-Y H:i') }}</td>
                                    <td>{{ $l->export_type }}</td>
                                    <td>{{ $l->year_id }}</td>
                                    <td>{{ $l->file_name }}</td>
                                    <td class="text-end">{{ number_format($l->row_count) }}</td>
                                    <td class="text-end">{{ $l->total_amount === null ? '-' : number_format((float) $l->total_amount, 2) }}</td>
                                    <td>{{ $l->exported_by_name ?: $l->exported_by }}</td>
                                    <td><code class="small">{{ \Illuminate\Support\Str::limit((string) $l->filters, 80) }}</code></td>
                                    <td><code class="small" title="{{ $l->hash_of }}">{{ substr((string) $l->content_hash, 0, 12) }}</code></td>
                                </tr>
                            @empty
                                <tr><td colspan="9" class="text-center text-muted">No Focus exports logged yet.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
                <x-report-stamp />
            </div>
        </div>
    </div>
@endsection
