{{-- HC-SUMMARY-01: summary sheet to attach in front of the hard-copy bills --}}
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8">
<title>Expense Claim Summary - {{ $emp['code'] }} - {{ $monthName }}</title>
<style>
    @page { size: A4; margin: 12mm; }
    body { font-family: Arial, Helvetica, sans-serif; font-size: 12px; color: #111; margin: 0; }
    h1 { font-size: 17px; margin: 0 0 2px; }
    .sub { color: #444; margin-bottom: 10px; }
    .box { border: 1px solid #999; padding: 8px 10px; margin-bottom: 10px; }
    .grid { display: grid; grid-template-columns: 1fr 1fr; gap: 4px 18px; }
    table { width: 100%; border-collapse: collapse; margin-bottom: 10px; }
    th, td { border: 1px solid #999; padding: 4px 6px; text-align: left; vertical-align: top; }
    th { background: #eef2f7; }
    td.n, th.n { text-align: right; white-space: nowrap; font-variant-numeric: tabular-nums; }
    tfoot td { font-weight: 700; background: #f7f7f7; }
    .sign { display: flex; justify-content: space-between; margin-top: 34px; }
    .sign div { width: 30%; border-top: 1px solid #333; padding-top: 4px; text-align: center; }
    .noprint { margin: 10px 0; }
    @media print { .noprint { display: none; } }
</style>
</head>
<body>
<div class="noprint"><button onclick="window.print()">Print</button> <a href="{{ route('hardcopy.index') }}">Back</a></div>
<h1>Expense Claim – {{ $monthName }} {{ $fy }}</h1>
<div class="sub">Summary sheet: attach in front of the bills, arranged by date as listed below. To: DMS Department, HO.</div>

<div class="box grid">
    <div><b>Employee:</b> {{ $emp['code'] }} – {{ $emp['name'] }}</div>
    <div><b>Department:</b> {{ $emp['dept'] }}</div>
    <div><b>Designation:</b> {{ $emp['desig'] }}</div>
    <div><b>HQ:</b> {{ $emp['hq'] }}</div>
    <div><b>Reporting manager:</b> {{ $rm ? $rm['name'].' ('.$rm['code'].')' : '-' }}</div>
    <div><b>Mobile:</b> {{ $emp['mobile'] }}</div>
    <div><b>Submitted on:</b> {{ ! empty($m->DateOfSubmit) && $m->DateOfSubmit > '2000-01-01' ? \Carbon\Carbon::parse($m->DateOfSubmit)->format('d-m-Y') : '-' }}</div>
    <div><b>Sent:</b> @if (! empty($m->PostDate) && $m->PostDate > '2000-01-01'){{ \Carbon\Carbon::parse($m->PostDate)->format('d-m-Y') }} {{ $m->Agency }} {{ $m->DocateNo }}@else ____________________ @endif</div>
</div>

<table aria-label="Bills in date order">
    <thead><tr><th scope="col">#</th><th scope="col">Bill date</th><th scope="col">Expense head</th><th scope="col">Claim ID</th><th scope="col">Remark</th><th scope="col" class="n">Km</th><th scope="col" class="n">Amount (₹)</th><th scope="col">Bill attached ✓</th></tr></thead>
    <tbody>
    @forelse ($lines as $i => $l)
        <tr>
            <td>{{ $i + 1 }}</td>
            <td>{{ $l->BillDate ? \Carbon\Carbon::parse($l->BillDate)->format('d-m-Y') : '' }}</td>
            <td>{{ $l->ClaimName }}</td>
            <td>{{ $l->ExpId }}</td>
            <td>{{ \Illuminate\Support\Str::limit(trim(($l->Remark ?? '') ?: ($l->DateEntryRemark ?? '')), 60) }}</td>
            <td class="n">{{ (float) $l->TotKm > 0 ? number_format((float) $l->TotKm) : '' }}</td>
            <td class="n">{{ number_format((float) $l->FilledTAmt, 2) }}</td>
            <td></td>
        </tr>
    @empty
        <tr><td colspan="8">No claim lines in this month.</td></tr>
    @endforelse
    </tbody>
    <tfoot><tr><td colspan="6">Total ({{ $lines->count() }} bills)</td><td class="n">{{ number_format((float) $lines->sum('FilledTAmt'), 2) }}</td><td></td></tr></tfoot>
</table>

<table aria-label="Totals by expense head" style="width:60%">
    <thead><tr><th scope="col">Expense head</th><th scope="col" class="n">Bills</th><th scope="col" class="n">Amount (₹)</th></tr></thead>
    <tbody>@foreach ($byHead as $head => $h)<tr><td>{{ $head }}</td><td class="n">{{ $h['n'] }}</td><td class="n">{{ number_format((float) $h['amt'], 2) }}</td></tr>@endforeach</tbody>
</table>

<p>I confirm that the bills attached are the originals of the claims listed above.</p>
<div class="sign"><div>Employee signature</div><div>Received by (DMS)</div><div>Date of receipt</div></div>
<script>window.addEventListener('load', function () { if (new URLSearchParams(location.search).get('print') === '1') window.print(); });</script>
</body>
</html>
