@extends('layouts.app')

@section('content')
<div class="page-content">
    <div class="container-fluid">
        <div class="card border-0 shadow-sm">
            <div class="card-header bg-white d-flex flex-wrap align-items-center gap-2">
                <h5 class="mb-0 me-auto"><i class="ri-database-2-line me-1"></i> Database setup</h5>
                @if ($pending > 0)
                    <form method="post" action="{{ route('admin.db-setup.run') }}" onsubmit="this.querySelector('button').disabled = true; this.querySelector('button').innerText = 'Running…';">
                        @csrf
                        <button class="btn btn-sm btn-primary">Run {{ $pending }} missing or incomplete {{ $pending === 1 ? 'change' : 'changes' }}</button>
                    </form>
                @else
                    <span class="badge bg-success-subtle text-success">Up to date</span>
                @endif
            </div>
            <div class="card-body p-0">
                <p class="text-muted small px-3 pt-3 mb-2">
                    The database changes this version of Xeasy needs (deploy/migrations.txt). Missing ones make the pages that use them
                    answer "Server Error". Running them adds tables, columns and menu items; it does not change claim or payment data.
                    Changes run one by one, in this order, and stop at the first one that fails.
                </p>

                @if ($results)
                    <div class="px-3">
                        @foreach ($results as $r)
                            <div class="alert {{ $r['ok'] ? 'alert-success' : 'alert-danger' }} py-2 small mb-2">
                                <strong>{{ $r['name'] }}</strong> — {{ $r['ok'] ? 'done' : 'failed' }}
                                @if ($r['output'] !== '')
                                    <pre class="mb-0 mt-1 small" style="white-space:pre-wrap">{{ $r['output'] }}</pre>
                                @endif
                            </div>
                        @endforeach
                    </div>
                @endif

                <div class="table-responsive">
                    <table class="table table-sm table-hover align-middle mb-0">
                        <thead class="table-light"><tr><th style="width:60px">#</th><th>Database change</th><th style="width:260px">On this server</th></tr></thead>
                        <tbody>
                        @foreach (array_reverse($rows, true) as $i => $r)
                            <tr>
                                <td class="small text-muted">{{ $i + 1 }}</td>
                                <td class="small">{{ $r['name'] }}</td>
                                <td class="small">
                                    @if ($r['ran'] && ! empty($r['missing']))
                                        <span class="text-danger fw-semibold">Incomplete</span>
                                        <div class="text-muted" style="font-size:.7rem">recorded as done, but not here: {{ implode(', ', array_slice($r['missing'], 0, 6)) }}{{ count($r['missing']) > 6 ? ' …' : '' }}</div>
                                    @elseif ($r['ran'])
                                        <span class="text-success">Done</span>
                                    @elseif (! $r['file'])
                                        <span class="text-muted">File not in this version</span>
                                    @else
                                        <span class="text-danger fw-semibold">Missing</span>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
