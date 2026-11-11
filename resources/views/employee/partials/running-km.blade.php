<div class="table-responsive">
    <table class="table table-bordered">
        <thead>
            <tr>
                <th>Bill Date</th>
                <th>Opening Reading</th>
                <th>Closing Reading</th>
                <th>Running KM</th>
            </tr>
        </thead>
        <tbody>
            @forelse($runningData as $data)
            <tr>
                <td>{{ \Carbon\Carbon::parse($data->BillDate)->format('d/m/Y') }}</td>
                <td>{{ $data->odomtr_opening ?? '-' }}</td>
                <td>{{ $data->odomtr_closing ?? '-' }}</td>
                <td>{{ $data->TotKm ?? 0 }}</td>
            </tr>
            @empty
            <tr>
                <td colspan="4" class="text-center">No running KM data found</td>
            </tr>
            @endforelse
        </tbody>
        <tfoot>
            <tr class="bg-light">
                <th colspan="3" class="text-right">Total KM</th>
                <th>{{ $runningData->sum('TotKm') }}</th>
            </tr>
        </tfoot>
    </table>
</div>