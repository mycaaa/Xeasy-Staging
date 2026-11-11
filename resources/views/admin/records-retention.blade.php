{{-- RET-01 --}}
@extends('layouts.app')

@section('content')
    <div class="col-md-12 h-100 p-3 mt-5">
        <div class="row card mt-2">
            <div class="col-lg-12 shadow p-3">
                <h5 class="mb-1">Records retention</h5>
                <p class="text-muted small mb-2">Claim records and bills are kept financial-year-wise for at least {{ $keep }} years after the year ends. Years that ended more than {{ $after }} years ago are archived: read-only, still viewable, with an archive copy (records:archive). Xeasy never deletes a year.</p>
                <table class="table table-sm table-bordered small">
                    <thead class="table-light"><tr><th>Financial year</th><th>Status</th><th class="text-end">Claims</th><th class="text-end">Bill files</th><th class="text-end">Employee-months</th><th>Archived from</th><th>Keep until</th><th>Archive copy</th></tr></thead>
                    <tbody>
                    @foreach ($years as $y)
                        <tr>
                            <td>{{ $y['year'] }}</td>
                            <td><span class="badge {{ $y['status'] === 'archived' ? 'bg-secondary' : ($y['status'] === 'active' ? 'bg-success' : 'bg-info text-dark') }}">{{ $y['status'] === 'archived' ? 'Archived (read-only)' : ucfirst($y['status']) }}</span></td>
                            <td class="text-end">{{ $y['claims'] !== null ? number_format($y['claims']) : '-' }}</td>
                            <td class="text-end">{{ $y['files'] !== null ? number_format($y['files']) : '-' }}</td>
                            <td class="text-end">{{ $y['months'] !== null ? number_format($y['months']) : '-' }}</td>
                            <td>{{ \Carbon\Carbon::parse($y['archive_from'])->format('d-m-Y') }}</td>
                            <td>{{ \Carbon\Carbon::parse($y['keep_until'])->format('d-m-Y') }}</td>
                            <td>{{ $y['archive'] ? \Carbon\Carbon::parse($y['archive']->archived_at)->format('d-m-Y').' · '.number_format($y['archive']->rows).' rows' : ($y['status'] === 'archived' ? 'not made yet' : '') }}</td>
                        </tr>
                    @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    </div>
@endsection
