<div class="extraction-details">
    <div class="row mb-3">
        <div class="col-12">
            <div class="alert alert-info">
                <h6 class="alert-heading mb-2">
                    <i class="ri-information-line me-1"></i>
                    Extraction ID: {{ $extraction->ExpId }}
                </h6>
                <div class="row">
                    <div class="col-md-6">
                        <small><strong>Employee:</strong> {{ $extraction->employee_name }}</small>
                    </div>
                    <div class="col-md-6">
                        <small><strong>Claim Type:</strong> {{ $extraction->ClaimName }}</small>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <div class="row mb-3">
        <div class="col-md-6">
            <table class="table table-sm table-bordered">
                <tr>
                    <th width="40%">Bill Date</th>
                    <td>{{ date('d-M-Y', strtotime($extraction->BillDate)) }}</td>
                </tr>
                <tr>
                    <th>Filled Amount</th>
                    <td><strong>₹{{ number_format($extraction->FilledTAmt, 2) }}</strong></td>
                </tr>
                <tr>
                    <th>Status</th>
                    <td>
                        @php
                        $statusColors = [
                        'Pending' => 'warning',
                        'Approved' => 'success',
                        'Rejected' => 'danger'
                        ];
                        $color = $statusColors[$extraction->ClaimStatus] ?? 'secondary';
                        @endphp
                        <span class="badge bg-{{ $color }}">{{ $extraction->ClaimStatus }}</span>
                    </td>
                </tr>
            </table>
        </div>
        <div class="col-md-6">
            <table class="table table-sm table-bordered">
                <tr>
                    <th width="40%">Claim Month</th>
                    <td>{{ date('F Y', mktime(0, 0, 0, $extraction->ClaimMonth, 1)) }}</td>
                </tr>
                <tr>
                    <th>Filled Date</th>
                    <td>{{ \App\Support\ZeroDate::has($extraction->FilledDate) ? date('d-M-Y', strtotime($extraction->FilledDate)) : 'N/A' }}</td>
                </tr>
                <tr>
                    <th>Temp Punch ID</th>
                    <td>{{ $extraction->temp_punch_id ?? 'N/A' }}</td>
                </tr>
            </table>
        </div>
    </div>
    @if($details && count($details) > 0)
    <div class="row">
        <div class="col-12">
            <h6 class="mb-2">
                <i class="ri-list-check me-1"></i>
                Expense Details
            </h6>
            <div class="table-responsive">
                <table class="table table-sm table-bordered table-striped">
                    <thead class="table-light">
                        <tr>
                            <th width="5%">#</th>
                            <th width="30%">Title</th>
                            <th width="45%">Remark</th>
                            <th width="20%" class="text-end">Amount</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($details as $index => $detail)
                        <tr>
                            <td>{{ $index + 1 }}</td>
                            <td>{{ $detail->Title }}</td>
                            <td>{{ $detail->Remark }}</td>
                            <td class="text-end">₹{{ number_format($detail->Amount, 2) }}</td>
                        </tr>
                        @endforeach
                    </tbody>
                    <tfoot class="table-light">
                        <tr>
                            <th colspan="3" class="text-end">Total:</th>
                            <th class="text-end">₹{{ number_format($details->sum('Amount'), 2) }}</th>
                        </tr>
                    </tfoot>
                </table>
            </div>
        </div>
    </div>
    @else
    <div class="alert alert-warning">
        <i class="ri-alert-line me-1"></i>
        No expense details found for this extraction.
    </div>
    @endif
    @if(!empty($extraction->Remark))
    <div class="row mt-3">
        <div class="col-12">
            <div class="card bg-light">
                <div class="card-body">
                    <h6 class="card-title">Remarks</h6>
                    <p class="card-text mb-0">{{ $extraction->Remark }}</p>
                </div>
            </div>
        </div>
    </div>
    @endif
</div>