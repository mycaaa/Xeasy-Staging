@php
    $Rmk = $rmk ?? '';
    $exp = $exp ?? null;
    $expDlt = $expDlt ?? null;
@endphp

@if(session('role_id') == 'E' || (session('role_id') == 'A' && ($exp->CrBy ?? 0) == session('EmployeeID')))
    <tr>
        <td colspan="4" style="text-align:center;">
            <table style="width:100%; padding:0px;" border="1">
                <tr style="background-color:#BCBCBC;">
                    <th style="width:15%;text-align:center;">Amount</th>
                    <th style="width:85%;text-align:center;">Remark</th>
                </tr>
                <tr>
                    <td><input value="{{ $exp->FilledTAmt ?? 0 }}" style="width:100%;text-align:center;" readonly/></td>
                    <td><input value="{{ $expDlt->Remark ?? '' }}" style="width:100%;" readonly/></td>
                </tr>
            </table>
        </td>
    </tr>

@elseif(session('role_id') == 'M')
    @if(($exp->ClaimId ?? 0) == 18)
        <tr>
            <th scope="row">Remark</th>
            <td colspan="3">
                <input class="form-control" name="M_Remark" value="{{ $expDlt->Remark ?? $Rmk }}">
                <input type="hidden" name="TotalAmt" id="TotalAmt" value="0">
            </td>
        </tr>
    @else
        <tr>
            <th scope="row">Total Amount</th>
            <td><input type="text" class="form-control" name="TotalAmt" id="TotalAmt" value="{{ $exp->FilledTAmt ?? 0 }}" required></td>
            <th scope="row">Remark</th>
            <td><input class="form-control" name="M_Remark" value="{{ $expDlt->Remark ?? $Rmk }}"></td>
        </tr>
    @endif

@elseif(session('role_id') == 'V')
    @if(session('company_id') == 11)
        <tr>
            <td colspan="4" style="text-align:center;">
                <table style="width:100%; padding:0px;" border="1">
                    <tr style="background-color:#BCBCBC;">
                        <th colspan="2">Claimant</th>
                        <th colspan="2">Approver</th>
                        <th colspan="2">Verifier</th>
                    </tr>
                    <tr style="background-color:#BCBCBC;">
                        <th style="width:10%;">Amount</th>
                        <th style="width:23%;">Remark</th>
                        <th style="width:10%;">Amount</th>
                        <th style="width:23%;">Remark</th>
                        <th style="width:10%;">Amount</th>
                        <th style="width:24%;">Remark</th>
                    </tr>
                    <tr>
                        <td><input value="{{ $exp->FilledTAmt ?? 0 }}" style="width:100%;text-align:center;" readonly/></td>
                        <td><input value="{{ $expDlt->Remark ?? '' }}" style="width:100%;" readonly/></td>
                        <td><input value="{{ $exp->ApprTAmt ?? 0 }}" style="width:100%;text-align:center;" readonly/></td>
                        <td><input value="{{ $expDlt->ApprTRemark ?? $exp->ApprTRemark ?? '' }}" style="width:100%;" readonly/></td>
                        <td><input type="text" class="form-control" style="width:100%;text-align:center;" name="TotalAmt" id="TotalAmt" value="{{ $exp->VerifyTAmt ?? 0 }}" required></td>
                        <td><input class="form-control" style="width:100%;" name="M_Remark" value="{{ $exp->VerifyTRemark ?? '' }}"></td>
                    </tr>
                </table>
            </td>
        </tr>
    @else
        <tr>
            <td colspan="4" style="text-align:center;">
                <table style="width:100%; padding:0px;" border="1">
                    <tr style="background-color:#BCBCBC;">
                        <th colspan="2">Claimant</th>
                        <th colspan="2">Verifier</th>
                    </tr>
                    <tr style="background-color:#BCBCBC;">
                        <th style="width:10%;">Amount</th>
                        <th style="width:40%;">Remark</th>
                        <th style="width:10%;">Amount</th>
                        <th style="width:40%;">Remark</th>
                    </tr>
                    <tr>
                        <td><input value="{{ $exp->FilledTAmt ?? 0 }}" style="width:100%;text-align:center;" readonly/></td>
                        <td><input value="{{ $expDlt->Remark ?? '' }}" style="width:100%;" readonly/></td>
                        <td><input type="text" class="form-control" style="width:100%;text-align:center;" name="TotalAmt" id="TotalAmt" value="{{ $exp->VerifyTAmt ?? 0 }}" required></td>
                        <td><input class="form-control" style="width:100%;" name="M_Remark" value="{{ $exp->VerifyTRemark ?? '' }}"></td>
                    </tr>
                </table>
            </td>
        </tr>
    @endif

@elseif(in_array(session('role_id'), ['F', 'P']) || (session('role_id') == 'A' && ($exp->CrBy ?? 0) != session('EmployeeID')))
    <tr>
        <td colspan="4" style="text-align:center;">
            <table style="width:100%; padding:0px;" border="1">
                <tr style="background-color:#BCBCBC;">
                    <th colspan="2">Claimant</th>
                    <th colspan="2">Verifier</th>
                    <th colspan="2">Approver</th>
                    <th colspan="2">Finance</th>
                </tr>
                <tr style="background-color:#BCBCBC;">
                    <th>Amount</th><th>Remark</th>
                    <th>Amount</th><th>Remark</th>
                    <th>Amount</th><th>Remark</th>
                    <th>Amount</th><th>Remark</th>
                </tr>
                <tr>
                    <td><input value="{{ $exp->FilledTAmt ?? 0 }}" style="width:100%;text-align:center;" readonly/></td>
                    <td><input value="{{ $expDlt->Remark ?? '' }}" style="width:100%;" readonly/></td>
                    <td><input value="{{ $exp->VerifyTAmt ?? 0 }}" style="width:100%;text-align:center;" readonly/></td>
                    <td><input value="{{ $expDlt->VerifyTRemark ?? '' }}" style="width:100%;" readonly/></td>
                    <td><input value="{{ $exp->ApprTAmt ?? 0 }}" style="width:100%;text-align:center;" readonly/></td>
                    <td><input value="{{ $expDlt->ApprTRemark ?? '' }}" style="width:100%;" readonly/></td>
                    <td><input value="{{ $exp->FinancedTAmt ?? 0 }}" style="width:100%;text-align:center;" readonly/></td>
                    <td><input value="{{ $expDlt->FinancedTRemark ?? '' }}" style="width:100%;" readonly/></td>
                </tr>
            </table>
        </td>
    </tr>
@endif
