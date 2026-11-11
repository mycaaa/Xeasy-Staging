{{-- fixed-imprest-balance.blade.php --}}

@php
    $data = !empty($fixedImprest) ? $fixedImprest : (!empty($ledgerData) ? $ledgerData : []);
@endphp

@if(!empty($data))
    @php $hasFixedImprest = false; @endphp

    @for($i = 1; $i <= 2; $i++)
        @php
            $fixedAmt  = $data['fixed_imprest_amt_' . $i] ?? ($data['amount_' . $i] ?? 0);
            $fixedDate = $data['fixed_imprest_date_' . $i] ?? ($data['date_' . $i] ?? '');
        @endphp

        @if($fixedAmt > 0)
            @php $hasFixedImprest = true; @endphp
            <div style="margin-bottom:6px;">
                <div style="font-size:11px;font-weight:600;">
                    {{ number_format((float) $fixedAmt) }}
                </div>

                @if(!empty($fixedDate) && \App\Support\ZeroDate::has($fixedDate))
                    <div style="font-size:9px;color:#666;">
                        {{ \Carbon\Carbon::parse($fixedDate)->format('d-m-Y') }}
                    </div>
                @endif
            </div>
        @endif
    @endfor

    @if(!$hasFixedImprest)
        <div style="color:#999;font-size:11px;text-align:center;">-</div>
    @endif
@else
    <div style="color:#999;font-size:11px;text-align:center;">-</div>
@endif