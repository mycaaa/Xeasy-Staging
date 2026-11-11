@extends('layouts.standalone')

@section('title', 'Expense Details')

@push('head')
    <style>
        @import url('https://fonts.googleapis.com/css2?family=HKGrotesk&display=swap');

        body {
            font-family: 'HK Grotesk', sans-serif;
            margin: 20px;
            background-color: #f9f9f9;
            color: #333;
        }



        .report-header h2 {
            margin: 0;
            font-size: 22px;
            text-align: center;
            letter-spacing: 0.5px;
        }

        .meta-info {
            font-size: 14px;
            margin-top: 8px;
        }

        .meta-info strong {
            color: #ffffff;
        }

        .section-title {
            font-size: 14px;
            margin-top: 5px;
            color: #e2e6ea;
        }

        .section-title span {
            font-weight: bold;
            color: #fff;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            background-color: #fff;
            box-shadow: 0 0 5px rgba(0, 0, 0, 0.08);
        }

        thead th {
            background-color: #007bff;
            color: #fff;
            padding: 8px;
            font-size: 13px;
        }

        tbody td {
            padding: 6px;
            text-align: center;
            border: 1px solid #dee2e6;
            font-size: 12px;
        }

        tbody tr:nth-child(even) {
            background-color: #f1f9ff;
        }

        tbody tr:hover {
            background-color: #e2f0fc;
        }

        .total-col {
            background-color: #ffffcc !important;
            font-weight: bold;
        }
    </style>
@endpush

@section('content')
    <div class="report-header">
        <h2>Expense Details ({{ $period }})</h2>
        <div class="section-title"><span>Claimer:</span> {{ $claimer }}</div>
        <div class="section-title"><span>Processing:</span> Common, M4, Manager Plant</div>
        <div class="section-title"><span>HQ:</span> {{ $hq }} &nbsp; <span>State:</span> {{ $state }}</div>
        <div class="section-title"><span>Total Amount:</span> ₹{{ number_format($total_amount) }}/- &nbsp;&nbsp;
            <span>Submitted Date:</span> {{ $submitted_date }}</div>
        <div class="section-title"><span>Expense Type:</span> {{ $expense_type }}</div>
    </div>

    <table>
        <thead>
            <tr>
                <th>Sn</th>
                <th>Date</th>
                <th>Remark</th>
                <th>FromKm</th>
                <th>ToKm</th>
                <th>TotalKm</th>
                <th>2W</th>
                <th>4W</th>
                <th>LDG</th>
                <th>MLS</th>
                <th>MSC</th>
                <th>Total</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($expenses as $index => $expense)
                <tr>
                    <td>{{ $index + 1 }}</td>
                    <td>{{ $expense['date'] }}</td>
                    <td style="text-align: left;">{{ $expense['description'] }}</td>
                    <td>{{ $expense['from_km'] }}</td>
                    <td>{{ $expense['to_km'] }}</td>
                    <td>{{ $expense['total_km'] }}</td>
                    <td>{{ $expense['2w'] }}</td>
                    <td>{{ $expense['4w'] }}</td>
                    <td>{{ $expense['ldg'] }}</td>
                    <td>{{ $expense['mls'] }}</td>
                    <td>{{ $expense['msc'] }}</td>
                    <td class="total-col">{{ $expense['total'] }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>

<x-report-stamp />
@endsection
