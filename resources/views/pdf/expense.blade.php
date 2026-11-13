<!DOCTYPE html>
<html lang="en">
<head>
    <style>
        body { font-family: 'DejaVu Sans', freeserif, sans-serif; font-size: 11px; }
        table { border-collapse: collapse; width: 100%; }
        th, td { border:1px solid #000; padding:4px; }
        th { background:#dde6ff; color:#1e3a8a; }
        .title { background:#dde6ff; color:#1e3a8a; padding:5px; font-weight:bold; }
        .total { background:#FFFFB3; font-weight:bold; }
        .center { text-align:center; }
        .right { text-align:right; }
    </style>
</head>

<body>

@foreach($groups as $group)

    <div class="title">
        EXPENSE TYPE : {{ strtoupper($group['name']) }}
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
                <th>Total</th>
            </tr>
        </thead>

        <tbody>
            @foreach($group['rows'] as $i => $row)
                <tr>
                    <td class="center">{{ $i+1 }}</td>
                    <td class="center">{{ date('d-m-Y', strtotime($row['date'])) }}</td>
                    <td>{{ $row['remark'] }}</td>
                    <td class="center">{{ $row['from_km'] }}</td>
                    <td class="center">{{ $row['to_km'] }}</td>
                    <td class="center">{{ $row['total_km'] }}</td>
                    <td class="right">{{ $row['twoW'] }}</td>
                    <td class="right">{{ $row['fourW'] }}</td>
                    <td class="right total">{{ $row['total'] }}</td>
                </tr>
            @endforeach

            <tr class="total">
                <td colspan="5" class="right">Total:</td>
                <td class="center">{{ $group['total_km'] }} KM</td>
                <td class="right">{{ $group['total_2w'] }}</td>
                <td class="right">{{ $group['total_4w'] }}</td>
                <td class="right">{{ $group['grand_total'] }}</td>
            </tr>
        </tbody>
    </table>

    <br>

@endforeach

</body>
</html>