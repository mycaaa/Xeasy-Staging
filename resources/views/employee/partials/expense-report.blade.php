<div class="table-responsive">
    <table class="table table-bordered">
        <thead>
            <tr>
                <th>Exp ID</th>
                <th>Bill Date</th>
                <th>Claim Type</th>
                <th>Claimed Amount</th>
                <th>Status</th>
            </tr>
        </thead>
        <tbody>
            @forelse($claims as $claim)
            <tr>
                <td>{{ $claim->ExpId }}</td>
                <td>{{ \Carbon\Carbon::parse($claim->CrDate)->format('d/m/Y') }}</td>
                <td>{{ $claim->ClaimName ?? '-' }}</td>
                <td class="text-right">₹ {{ number_format($claim->FilledTAmt ?? 0, 2) }}</td>
                <td>
                    @if($claim->ClaimStatus == 'Approved')
                        <span class="badge bg-success">Approved</span>
                    @elseif($claim->ClaimStatus == 'Verified')
                        <span class="badge bg-info">Verified</span>
                    @else
                        <span class="badge bg-warning">Pending</span>
                    @endif
                </td>
            </tr>
            @empty
            <tr>
                <td colspan="5" class="text-center">No expense records found</td>
            </tr>
            @endforelse
        </tbody>
    </table>
</div>