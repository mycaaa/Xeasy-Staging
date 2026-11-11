@php
    use Illuminate\Support\Facades\DB;

    // Get expense claim data (only if FilledOkay = 1)
    $exp = DB::connection(session('expense_connection'))
        ->selectOne("select * from y".session('year_id')."_expenseclaims where ExpId = ? and FilledOkay = 1", [$expid]);

    if (!$exp) {
        abort(404, 'Claim not found or not confirmed by claimant (FilledOkay is not 1).');
    }

    // Get associated uploads
    $uploads = DB::connection(session('expense_connection'))
        ->select("select cu.* from y".session('year_id')."_claimuploads cu, y".session('year_id')."_expenseclaims e
                  where cu.ExpId = e.ExpId and (e.ExpId = ? or e.AttachTo = ?)
                  order by cu.UploadSequence asc", [$expid, $expid]);

    $data = [];
    if (count($uploads) > 0) {
        foreach ($uploads as $upload) {
            $data[] = $upload->FileName;
        }
    } else {
        if (isset($exp->Img_1) && !empty($exp->Img_1)) {
            $data[] = $exp->Img_1;
        }
    }

    $requestText = urlencode(json_encode($data));

    // Get claim type name
    $claimType = DB::selectOne("select ClaimName from claimtype where ClaimId = ?", [$exp->ClaimId]);

    // Get employee name
    $employee = DB::connection('hrims')
        ->selectOne("select Fname, Sname, Lname from hrm_employee where EmployeeID = ?", [$exp->CrBy]);
    $employeeName = $employee ? trim($employee->Fname.' '.$employee->Sname.' '.$employee->Lname) : '';
@endphp
@extends('layouts.standalone')

@section('title', 'Claim details')

@push('head')
    <link rel="stylesheet" href="{{ asset('vendor/cdn/bootstrap@4.2.1/dist/css/bootstrap.min.css') }}">
    <link rel="stylesheet" href="{{ asset('vendor/cdn/font-awesome@4.7.0/css/font-awesome.min.css') }}">
    <link rel="stylesheet" type="text/css" href="{{ asset('css/jquery.datetimepicker.css') }}">
    <link href="{{ asset('vendor/cdn/gijgo@1.9.11/css/gijgo.min.css') }}" rel="stylesheet" type="text/css" />
    <style>
        .previewdiv { padding: 0px; }
        .claimtable th, .claimtable td { font-size: 12px; vertical-align: middle; }
        body { padding: 20px; background: #f5f5f5; }
        .loading-spinner {
            display: inline-block;
            width: 30px;
            height: 30px;
            border: 3px solid #f3f3f3;
            border-top: 3px solid #15648C;
            border-radius: 50%;
            animation: spin 1s linear infinite;
        }
        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }
    </style>
@endpush

@section('content')
<div class="container-fluid">
    <div class="row">
        <!-- Image Preview Section -->
        <div id="uplshdiv" class="col-md-6 h-100 previewdiv" style="padding:0px;">
            <div style="position: relative; width:100%; padding:0px;">
                <center>
                    <div id="preview" style="width:100%; height:400px; padding:0px;">
                        <input type="hidden" id="RequestText" name="RequestText" value="{{ $requestText }}">
                        <iframe src="{{ url('img-preview') }}?imglink={{ $requestText }}&inpage=showclaim&expid={{ $expid }}"
                                border="0" style="width:100%; height:450px;"></iframe>
                    </div>
                </center>
            </div>
        </div>

        <!-- Claim Details Section -->
        <div class="col-md-6 shadow">
            <center>
                <div class="table-responsive" style="padding-top:5px;">
                    <table class="table table-sm claimtable" border="0" style="width:100%;">
                        <thead class="thead-dark">
                            <tr>
                                <th scope="row" style="width:80px; vertical-align:middle;">Expense Type:</th>
                                <th style="vertical-align:middle; width:150px;">
                                    <div class="form-control font-weight-bold">
                                        {{ $claimType->ClaimName ?? 'N/A' }} - {{ $exp->ClaimId }}
                                    </div>
                                </th>
                                <th scope="row" style="width:50px; vertical-align:middle; text-align:right;">Year:</th>
                                <th style="width:80px; text-align:center;">
                                    <div class="form-control font-weight-bold">{{ session('year_id') }}</div>
                                </th>
                                <th style="width:50px;">&nbsp;</th>
                                <th style="vertical-align:middle; width:50px;">
                                    <button class="btn btn-sm btn-warning pull-right" onclick="closeModalAndEdit()">
                                        <i class="fa fa-pencil-square-o"></i> Edit
                                    </button>
                                </th>
                            </tr>
                        </thead>
                        <tbody id="claimformbody">
                            <tr>
                                <td colspan="6" style="text-align: center; padding: 50px;">
                                    <div class="loading-spinner"></div>
                                    <br>
                                    Loading form...
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </center>

            <!-- Multiple Remarks Section -->
            @include('claims.multipleremark')
        </div>
    </div>
</div>

<input id="claimstatus{{ $exp->ExpId }}" value="{{ $exp->ClaimStatus }}" type="hidden">
<input type="hidden" id="cid" value="{{ $exp->ClaimId }}">
<input type="hidden" id="expid" value="{{ $expid }}">

<script src="{{ asset('vendor/cdn/jquery@3.3.1/dist/jquery.js') }}"></script>
<script src="{{ asset('vendor/cdn/bootstrap@4.2.1/dist/js/bootstrap.min.js') }}"></script>
<script src="{{ asset('vendor/cdn/gijgo@1.9.11/js/gijgo.min.js') }}"></script>
<script src="{{ asset('js/jquery.datetimepicker.full.min.js') }}"></script>

<script>
    $(document).ready(function() {
        var expid = $('#expid').val();
        var cid = $('#cid').val();
        var empid = '{{ $exp->CrBy }}';

        console.log('Loading form for expid:', expid, 'cid:', cid);

        // Load claim form
        $.ajax({
            url: "{{ route('claims.show-form') }}",
            type: "POST",
            data: {
                act: "showclaimform",
                claimid: cid,
                expid: expid,
                _token: '{{ csrf_token() }}'
            },
            success: function(data) {
                console.log('Form loaded successfully');
                $('#claimformbody').html(data);

                // Re-initialize datepickers after form loads
                if (typeof $.fn.datetimepicker !== 'undefined') {
                    $('.dat').datetimepicker({format:'d-m-Y'});
                }

                // Re-initialize select2 if needed
                if (typeof $.fn.select2 !== 'undefined') {
                    $('.js-example-basic-multiple, .sel2').select2();
                }
            },
            error: function(xhr, status, error) {
                console.error('Error loading form:', error);
                console.log('Response:', xhr.responseText);
                $('#claimformbody').html('<tr><td colspan="6" class="text-center text-danger">Error loading form: ' + error + '</td></tr>');
            }
        });

        // Adjust height for mobile
        if (screen.width < 700) {
            $('#uplshdiv').removeClass('h-100').addClass('h-50');
        }
    });

    function closeModalAndEdit() {
        // Close modal and redirect to edit page if needed
        window.parent.$('#myModal').modal('hide');
        // Optional: redirect to edit page
        // window.location.href = "{{ url('claims/edit') }}/" + $('#expid').val();
    }
</script>
@endsection
