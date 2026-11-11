@php
    use Illuminate\Support\Facades\DB;

    $yearId = session('year_id');

    // Set role-based permissions
    $title = 'readonly';
    $astate = 'readonly';
    $vastate = 'readonly';
    $aastate = 'readonly';
    $fastate = 'readonly';
    $Amount = '';
    $VerifierEditAmount = '';
    $ApproverEditAmount = '';
    $FinanceEditAmount = '';

    switch(session('role_id')) {
        case 'E':
            $title = 'readonly';
            break;
        case 'M':
            $title = 'readonly required';
            $Amount = 'transparent';
            break;
        case 'V':
            $title = 'readonly';
            $vastate = '';
            $Amount = 'readonly';
            $VerifierEditAmount = 'transparent';
            break;
        case 'A':
            $title = 'readonly';
            $aastate = '';
            $ApproverEditAmount = 'transparent';
            break;
        case 'F':
            $title = 'readonly';
            $fastate = '';
            $FinanceEditAmount = 'transparent';
            break;
    }

    // Override based on claim status
    if(isset($exp->ClaimStatus)) {
        if($exp->ClaimStatus == 'Filled') {
            $astate = 'readonly';
            $Amount = '';
            $title = 'readonly';
        } else if($exp->ClaimStatus == 'Verified') {
            $vastate = 'readonly';
            $VerifierEditAmount = '';
        } else if($exp->ClaimStatus == 'Approved') {
            $aastate = 'readonly';
            $ApproverEditAmount = '';
        } else if($exp->ClaimStatus == 'Financed') {
            $fastate = 'readonly';
            $FinanceEditAmount = '';
        }
    }

    // Determine if user can edit
    $canEdit = false;
    if(($upbtndis ?? '') == '' && (session('role_id') == 'M' || session('role_id') == 'V' || session('role_id') == 'S')) {
        $canEdit = true;
    }

    // Map claim ID to form view name
    $claimFormMap = [
        1 => 'lodging',
        2 => 'airfare',
        3 => 'localconveyance',
        4 => 'hiredvehicle',
        5 => 'phonefax',
        6 => 'postagecourier',
        7 => '24wheeler',
        8 => 'vehiclemaint',
        9 => 'vehiclefuel',
        10 => 'rstofd',
        11 => 'fdfv',
        12 => 'jeepcampain',
        13 => 'dealermeeting',
        14 => 'railfare',
        15 => 'busfare',
        16 => 'miscellaneous',
        17 => 'meal',
        18 => 'boardingpass',
        22 => 'retailer_crop_tour',
        42 => 'team_meeting_expense',
    ];

    $formName = $claimFormMap[$claimid] ?? 'airfare';
@endphp

<form id="claimform" action="{{ route('claims.update') }}" method="post" enctype="multipart/form-data">
    @csrf
    <input type="hidden" name="ClaimId" value="{{ $claimid }}">
    <input type="hidden" name="expid" value="{{ $expid }}">
    <input type="hidden" id="savetype" name="savetype" value="">

    @if(isset($expf->did))
        <input type="hidden" name="expfid" value="{{ $expf->did }}">
    @endif

    <!-- Pass all data to the form -->
    @include("claims.forms.form_{$formName}", [
        'exp' => $exp,
        'expDlt' => $expDlt,
        'expf' => $expf,
        'yearId' => $yearId,
        'canEdit' => $canEdit,
        'title' => $title,
        'astate' => $astate,
        'vastate' => $vastate,
        'aastate' => $aastate,
        'fastate' => $fastate,
        'Amount' => $Amount,
        'VerifierEditAmount' => $VerifierEditAmount,
        'ApproverEditAmount' => $ApproverEditAmount,
        'FinanceEditAmount' => $FinanceEditAmount
    ])
</form>
