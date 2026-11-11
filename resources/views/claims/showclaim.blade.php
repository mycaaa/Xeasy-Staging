@php
    use Illuminate\Support\Facades\DB;

    // Get claim type group for form selection
    $cg = DB::selectOne("select cgId from claimtype where ClaimId = ?", [$exp->ClaimId]);
    $cgId = $cg ? $cg->cgId : 0;

    // Get expense claim details
    $expDlt = DB::connection(session('expense_connection'))
        ->selectOne("select * from y{$yearId}_expenseclaimsdetails where ExpId = ?", [$expid]);

    // Get group-specific fill data
    $expf = null;
    if($cgId > 0) {
        $expf = DB::connection(session('expense_connection'))
            ->selectOne("select * from y{$yearId}_g{$cgId}_expensefilldata where ExpId = ?", [$expid]);
    }

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

    $formName = $claimFormMap[$exp->ClaimId] ?? 'airfare';
@endphp

<link rel="stylesheet" href="{{ asset('css/jquery.datetimepicker.css') }}">
<link rel="stylesheet" type="text/css" href="{{ asset('vendor/cdn/bootstrap@4.2.1/dist/css/bootstrap.min.css') }}">
<link rel="stylesheet" type="text/css" href="{{ asset('vendor/cdn/font-awesome@4.7.0/css/font-awesome.min.css') }}">
<link href="{{ asset('vendor/cdn/gijgo@1.9.11/css/gijgo.min.css') }}" rel="stylesheet" type="text/css" />
<link rel="stylesheet" href="{{ asset('css/select2.min.css') }}">

<div class="container-fluid">
    <div class="row">
        <div id="uplshdiv" class="col-md-6 h-100 previewdiv" style="padding:0px;">
            <div style="position: relative; width:100%; padding:0px;">
                <center>
                    <div id="preview" style="width:100%; height:400px; padding:0px;">
                        <input type="hidden" id="RequestText" name="RequestText" value="{{ $requestText }}" form="claimform" />
                        <iframe src="{{ route('img-preview') }}?imglink={{ $requestText }}&inpage=showclaim&expid={{ $expid }}"
                                border="0" style="width:100%; height:450px;"></iframe>
                    </div>
                </center>
            </div>
        </div>

        <div class="col-md-6 shadow">
            <center>
                <div class="table-responsive" style="padding-top:5px;">
                    <table class="table table-sm claimtable" border="0" style="width:100%;">
                        <thead class="thead-dark">
                            <tr>
                                <th scope="row" style="width:80px; vertical-align:middle;">-Expense_Type:</th>
                                <th style="vertical-align:middle; width:150px;">
                                    @if(session('role_id') == 'M' OR session('role_id') == 'V')
                                        <select id="claimtype" name="claimtype" class="claimheadsel form-control pull-left"
                                            onchange="showclaimforma(this.value)" style="width:100%; font-size:11px;"
                                            form="claimform" disabled>
                                            <option value="">-----Select-----</option>
                                            @php
                                                $arr_exist = [19, 20, 21];
                                                $excludeClaims = "";
                                                if (session('role_id') == 'M') {
                                                    $excludeClaims = " AND ct.ClaimId NOT IN (7,42)";
                                                }

                                                if (in_array($exp->ClaimId, $arr_exist)) {
                                                    $c2 = DB::select("
                                                        SELECT ct.ClaimId, ct.ClaimName, cg.cgCode, cg.cgName
                                                        FROM claimtype ct, y{$yearId}_empclaimassign ca, claimgroup cg
                                                        WHERE ct.ClaimStatus='B'
                                                          AND ct.cgId=cg.cgId
                                                          {$excludeClaims}
                                                        GROUP BY ct.ClaimId
                                                    ");
                                                    foreach ($c2 as $cl) {
                                                        $selected = ($exp->ClaimId == $cl->ClaimId) ? 'selected' : '';
                                                        echo "<option value='{$cl->ClaimId}' {$selected}>{$cl->ClaimName}</option>";
                                                    }
                                                } else {
                                                    $claimIdCondition = "";
                                                    if (session('year_id') == '7') {
                                                        $claimIdCondition = "AND ct.ClaimId != 5 and ct.cgid=1";
                                                    }
                                                    $c = DB::select("
                                                        SELECT ca.ClaimId, ct.ClaimName, cg.cgCode, cg.cgName
                                                        FROM claimtype ct, y{$yearId}_empclaimassign ca, claimgroup cg
                                                        WHERE ca.ClaimId=ct.ClaimId
                                                          AND ct.ClaimStatus='A'
                                                          {$claimIdCondition}
                                                          AND ct.cgId=cg.cgId
                                                          {$excludeClaims}
                                                        GROUP BY ct.ClaimId
                                                    ");
                                                    foreach ($c as $cl) {
                                                        $selected = ($exp->ClaimId == $cl->ClaimId) ? 'selected' : '';
                                                        echo "<option value='{$cl->ClaimId}' {$selected}>{$cl->ClaimName}</option>";
                                                    }
                                                }
                                            @endphp
                                        </select>
                                    @else
                                        <div class="form-control font-weight-bold">
                                            {{ $claimType->ClaimName ?? 'N/A' }}-{{ $exp->ClaimId }}
                                        </div>
                                        <script type="text/javascript">getlimit({{ $exp->CrBy }}, {{ $exp->ClaimId }});</script>
                                    @endif
                                </th>
                                <th scope="row" style="width:50px; vertical-align:middle; text-align:right;">Year:</th>
                                <th style="width:80px; text-align:center;">
                                    <div class="form-control font-weight-bold">{{ session('year_id') }}</div>
                                </th>
                                <th style="width:50px;">&nbsp;</th>
                                <th style="vertical-align:middle; width:50px;">
                                    @if($canEdit)
                                        <button id="editbtn" class="btn btn-sm btn-warning pull-right" onclick="enableEdit()">
                                            <i class="fa fa-pencil-square-o"></i> Edit
                                        </button>
                                    @endif
                                </th>
                            </tr>
                        </thead>
                        <tbody id="claimformbody">
                            <!-- Form will be loaded here via AJAX -->
                            <tr>
                                <td colspan="6" class="text-center">
                                    <div class="spinner-border text-primary" role="status">
                                        <span class="sr-only">Loading...</span>
                                    </div>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </center>
            @include('claims.multipleremark')
        </div>
    </div>
</div>

<input id="claimstatus{{ $exp->ExpId }}" value="{{ $exp->ClaimStatus }}" type="hidden">
<input type="hidden" id="cid" value="{{ $exp->ClaimId }}">
<input type="hidden" id="expid" value="{{ $expid }}">

<script src="{{ asset('vendor/cdn/jquery@3.3.1/dist/jquery.js') }}"></script>
<script type="text/javascript" src="{{ asset('vendor/cdn/bootstrap@4.2.1/dist/js/bootstrap.min.js') }}"></script>
<script src="{{ asset('vendor/cdn/gijgo@1.9.11/js/gijgo.min.js') }}" type="text/javascript"></script>
<script src="{{ asset('js/jquery.datetimepicker.full.min.js') }}"></script>
<script src="{{ asset('js/select2.min.js') }}"></script>

<script type="text/javascript">
var upbtndis = '{{ $canEdit ? "" : "disabled" }}';
var currentCid = {{ $exp->ClaimId }};
var currentExpId = {{ $expid }};
var currentEmpId = {{ $exp->CrBy }};

function showclaimforma(cid) {
    currentCid = cid;
    $('#cid').val(cid);

    $('#claimformbody').html('<tr><td colspan="6" class="text-center"><div class="spinner-border text-primary" role="status"></div></td></tr>');

    $.post("{{ route('claims.show-form') }}", {
        act: "showclaimform",
        claimid: cid,
        expid: currentExpId,
        upbtndis: upbtndis,
        sessionid: "{{ session('EmployeeID') ?? '' }}",
        emprole: "{{ session('role_id') ?? '' }}",
        _token: "{{ csrf_token() }}"
    }, function(data){
        $('#claimformbody').html(data);
        if (typeof edit === 'function') edit();
        if (typeof addfaredet === 'function') addfaredet();
        getlimit(currentEmpId, cid);
        enableEdit();
    }).fail(function() {
        $('#claimformbody').html('<tr><td colspan="6" class="text-center text-danger">Error loading form</td></tr>');
    });
}

function getlimit(empid, cid) {
    $.post("{{ route('claims.get-limit') }}", {
        empid: empid,
        cid: cid,
        _token: "{{ csrf_token() }}"
    }, function(data){
        setTimeout(function(){
            if($("#limitspan").length) $("#limitspan").html(data);
        }, 500);
    });
}

function enableEdit() {
    $('#claimtype').attr('disabled', false);
    if($('#Update').length) $('#Update').attr('disabled', false);
    if($('#draft2').length) $('#draft2').attr('disabled', false);
    // Enable all form inputs
    $('#claimformbody input, #claimformbody select, #claimformbody textarea').prop('readonly', false).prop('disabled', false);
}

$(document).ready(function(){
    // Load the initial form
    showclaimforma(currentCid);

    var sw = parseInt(screen.width);
    if(sw < 700) {
        $('#uplshdiv').removeClass('h-100');
        $('#uplshdiv').addClass('h-50');
    }
});
</script>
