<div class="table-responsive">
    <table class="table table-bordered">
        <thead>
            <tr>
                <th>Exp ID</th>
                <th>Bill Date</th>
                <th>Claim Type</th>
                <th>Claimed Amount</th>
                @if($status == 'Verified')
                    <th>Verified Amount</th>
                @endif
                @if($status == 'Closed' || $type == 'Submit')
                    <th>Files</th>
                @endif
                <th>Remark</th>
                @if($status == 'Pending')
                    <th>Action</th>
                @endif
            </tr>
        </thead>
        <tbody>
            @forelse($details as $detail)
            <tr>
                <td>{{ $detail->ExpId }}</td>
                <td>{{ \Carbon\Carbon::parse($detail->CrDate)->format('d/m/Y') }}</td>
                <td>{{ $detail->ClaimName ?? '-' }}</td>
                <td class="text-right">₹ {{ number_format($detail->FilledTAmt ?? 0, 2) }}</td>
                @if($status == 'Verified')
                    <td class="text-right">₹ {{ number_format($detail->VerifyTAmt ?? 0, 2) }}</td>
                @endif
                @if($status == 'Closed' || $type == 'Submit')
                    <td>
                        @if($detail->Img_1)
                            <a href="{{ $detail->Img_1 }}" target="_blank">
                                <img alt="Bill or document image" src="{{ $detail->Img_1 }}" width="50" height="30">
                            </a>
                        @endif
                        @if($detail->pdf_1)
                            <a href="{{ $detail->pdf_1 }}" target="_blank">
                                <img alt="PDF file" src="/images/pdf-icon.png" width="30" height="30">
                            </a>
                        @endif
                    </td>
                @endif
                <td>{{ $detail->DateEntryRemark ?? '-' }}</td>
                @if($status == 'Pending')
                    <td>
                        <button class="btn btn-sm btn-success" 
                                onclick="approveClaim('{{ $detail->ExpId }}', '{{ $employeeId }}', '{{ $month }}', '{{ $yearId }}', '{{ $comId ?? 1 }}', '{{ $detail->FilledTAmt }}')">
                            Approve
                        </button>
                        <button class="btn btn-sm btn-danger" 
                                onclick="denyClaim('{{ $detail->ExpId }}', '{{ $employeeId }}', '{{ $month }}', '{{ $yearId }}', '{{ $comId ?? 1 }}')">
                            Deny
                        </button>
                    </td>
                @endif
            </tr>
            @empty
            <tr>
                <td colspan="10" class="text-center">No records found</td>
            </tr>
            @endforelse
        </tbody>
    </table>
</div>