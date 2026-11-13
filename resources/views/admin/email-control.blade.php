@extends('layouts.app')
@section('content')
<div class="page-content">
    <div class="container-fluid">
        <div class="row mb-3">
            <div class="col-12">
                <h4 class="mb-0 fw-bold" style="color:#15648C;">
                    <i class="ri-mail-settings-line me-2"></i>Email Control — Manage Recipients
                </h4>
            </div>
        </div>

        {{-- Filters --}}
        <div class="card mb-3">
            <div class="card-body py-2">
                <div class="row align-items-end g-2">
                    <div class="col-md-2">
                        <label class="form-label fw-bold mb-1" style="font-size:11px;">Department</label>
                        <select class="form-select form-select-sm" id="filterDept">
                            <option value="">All Departments</option>
                            @foreach($departments as $d)
                                <option value="{{ $d->id }}">{{ $d->department_name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-2">
                        <label class="form-label fw-bold mb-1" style="font-size:11px;">Grade</label>
                        <select class="form-select form-select-sm" id="filterGrade">
                            <option value="">All Grades</option>
                            @foreach($grades as $g)
                                <option value="{{ $g->id }}">{{ $g->grade_name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label fw-bold mb-1" style="font-size:11px;">Search (Name / Code / Email)</label>
                        <input type="text" class="form-control form-control-sm" id="filterSearch" placeholder="Type to search..." aria-label="Type to search">
                    </div>
                    <div class="col-md-2">
                        <label class="form-label fw-bold mb-1" style="font-size:11px;">Show</label>
                        <select class="form-select form-select-sm" id="filterShow">
                            <option value="">All</option>
                            <option value="disabled">Disabled Only</option>
                            <option value="enabled">Enabled Only</option>
                        </select>
                    </div>
                    <div class="col-md-1">
                        <button class="btn btn-primary btn-sm w-100" id="btnLoad"><i class="ri-search-line"></i></button>
                    </div>
                    <div class="col-md-2">
                        <button class="btn btn-outline-warning btn-sm w-100" id="btnBulk" disabled>
                            <i class="ri-settings-3-line me-1"></i>Bulk
                        </button>
                    </div>
                </div>
            </div>
        </div>

        {{-- Table --}}
        <div class="card">
            <div class="card-body p-2">
                <div class="d-flex justify-content-between align-items-center mb-2">
                    <small class="text-muted" id="statusText"></small>
                    <nav id="pagination"></nav>
                </div>
                <div class="table-responsive">
                    <table class="table table-bordered table-sm table-hover align-middle mb-0" style="font-size:10px;">
                        <thead style="background:#dde6ff;color:#1e3a8a;">
                            <tr>
                                <th><input type="checkbox" id="checkAll"></th>
                                <th>Code</th>
                                <th>Employee Name</th>
                                <th>Department</th>
                                <th>Grade</th>
                                <th>Designation</th>
                                <th>Email</th>
                                <th class="text-center" title="Master switch">All</th>
                                <th class="text-center" title="Submission Reminder">Sub</th>
                                <th class="text-center" title="Escalation">Esc</th>
                                <th class="text-center" title="Receiving Pending">Recv</th>
                                <th class="text-center" title="Payment Notification">Pay</th>
                                <th class="text-center" title="Approval Notification">Appr</th>
                            </tr>
                        </thead>
                        <tbody id="tableBody">
                            <tr><td colspan="13" class="text-center py-4 text-muted">Click search to load</td></tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

{{-- Bulk Modal --}}
<div class="modal fade" id="bulkModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header" style="background:#dde6ff;color:#1e3a8a;">
                <h5 class="modal-title text-white"><i class="ri-settings-3-line me-1"></i>Bulk Update</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <p class="text-muted mb-2" style="font-size:11px;"><span id="bulkCount">0</span> employees selected</p>
                <div class="mb-3">
                    <label class="form-label fw-bold">Email Type</label>
                    <select class="form-select form-select-sm" id="bulkField">
                        <option value="is_active">All Emails (Master)</option>
                        <option value="receive_submission_reminder">Submission Reminder</option>
                        <option value="receive_escalation">Escalation</option>
                        <option value="receive_receiving_pending">Receiving Pending</option>
                        <option value="receive_payment_notification">Payment</option>
                        <option value="receive_approval_notification">Approval</option>
                    </select>
                </div>
                <div class="mb-3">
                    <label class="form-label fw-bold">Action</label>
                    <div class="d-flex gap-3">
                        <div class="form-check"><input class="form-check-input" type="radio" name="bulkValue" id="bulkEnable" value="1" checked><label class="form-check-label" for="bulkEnable">Enable</label></div>
                        <div class="form-check"><input class="form-check-input" type="radio" name="bulkValue" id="bulkDisable" value="0"><label class="form-check-label" for="bulkDisable">Disable</label></div>
                    </div>
                </div>
                <div class="mb-3">
                    <label class="form-label fw-bold">Reason (optional)</label>
                    <input type="text" class="form-control form-control-sm" id="bulkReason" placeholder="e.g. Left company, On leave..." aria-label="e.g. Left company, On leave">
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-primary btn-sm" id="btnBulkSave"><i class="ri-check-line me-1"></i>Apply</button>
            </div>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
let currentPage = 1;
let currentData = [];

function loadData(page) {
    page = page || 1; currentPage = page;
    const params = { department_id:$('#filterDept').val(), grade_id:$('#filterGrade').val(), search:$('#filterSearch').val(), page:page, per_page:30 };
    $('#tableBody').html('<tr><td colspan="13" class="text-center py-3"><div class="spinner-border spinner-border-sm text-primary"></div></td></tr>');
    $.get('{{ url("email-control/data") }}', params, function(res) {
        if (!res.success || !res.data.employees.length) {
            $('#tableBody').html('<tr><td colspan="13" class="text-center py-3 text-muted">No employees found</td></tr>');
            $('#statusText').text('0 results'); $('#pagination').html(''); return;
        }
        currentData = res.data.employees;
        renderTable();
        const p = res.data.pagination;
        $('#statusText').text('Page '+p.current_page+' of '+p.last_page+' ('+p.total+' total)');
        renderPagination(p);
    });
}

function renderTable() {
    const filter = $('#filterShow').val();
    let data = currentData;
    if (filter==='disabled') data = data.filter(e=>!e.is_active);
    if (filter==='enabled') data = data.filter(e=>e.is_active);
    let h='';
    data.forEach(function(e){
        const noEmail = !e.email ? 'style="background:#fff3cd;"' : '';
        h+='<tr '+noEmail+'>';
        h+='<td><input type="checkbox" class="emp-check" value="'+e.employee_id+'"></td>';
        h+='<td><strong>'+e.emp_code+'</strong></td>';
        h+='<td>'+e.employee_name+'</td>';
        h+='<td>'+(e.department||'-')+'</td>';
        h+='<td><span class="badge bg-light text-dark" style="font-size:9px;">'+(e.grade||'-')+'</span></td>';
        h+='<td style="font-size:9px;">'+(e.designation||'-')+'</td>';
        h+='<td><small>'+(e.email||'<span class="text-danger">No email</span>')+'</small></td>';
        h+='<td class="text-center">'+sw(e,'is_active')+'</td>';
        h+='<td class="text-center">'+sw(e,'receive_submission_reminder')+'</td>';
        h+='<td class="text-center">'+sw(e,'receive_escalation')+'</td>';
        h+='<td class="text-center">'+sw(e,'receive_receiving_pending')+'</td>';
        h+='<td class="text-center">'+sw(e,'receive_payment_notification')+'</td>';
        h+='<td class="text-center">'+sw(e,'receive_approval_notification')+'</td>';
        h+='</tr>';
    });
    $('#tableBody').html(h);
}

function sw(emp, field) {
    const ck = emp[field]?'checked':'';
    const dis = (field!=='is_active'&&!emp.is_active)?'disabled':'';
    return '<div class="form-check form-switch d-flex justify-content-center mb-0"><input class="form-check-input email-toggle" type="checkbox" '+ck+' '+dis+' data-id="'+emp.employee_id+'" data-field="'+field+'" style="cursor:pointer;"></div>';
}

function renderPagination(p) {
    if (p.last_page<=1){$('#pagination').html('');return;}
    let h='<ul class="pagination pagination-sm mb-0">';
    h+='<li class="page-item '+(p.current_page===1?'disabled':'')+'"><a class="page-link pg" href="#" data-p="'+(p.current_page-1)+'">&lsaquo;</a></li>';
    const s=Math.max(1,p.current_page-2), en=Math.min(p.last_page,p.current_page+2);
    for(let i=s;i<=en;i++) h+='<li class="page-item '+(i===p.current_page?'active':'')+'"><a class="page-link pg" href="#" data-p="'+i+'">'+i+'</a></li>';
    h+='<li class="page-item '+(p.current_page===p.last_page?'disabled':'')+'"><a class="page-link pg" href="#" data-p="'+(p.current_page+1)+'">&rsaquo;</a></li></ul>';
    $('#pagination').html(h);
}

$(document).on('click','.pg',function(e){e.preventDefault();loadData($(this).data('p'));});
$(document).on('change','.email-toggle',function(){
    const $el=$(this), empId=$el.data('id'), field=$el.data('field'), val=$el.is(':checked')?1:0;
    $.post('{{ url("email-control/update") }}',{_token:'{{ csrf_token() }}',employee_id:empId,field:field,value:val},function(res){
        if(res.success){const emp=currentData.find(e=>e.employee_id==empId);if(emp){emp[field]=!!val;}if(field==='is_active')renderTable();}
    });
});
$('#checkAll').on('change',function(){$('.emp-check').prop('checked',$(this).is(':checked'));updateBulk();});
$(document).on('change','.emp-check',updateBulk);
function updateBulk(){const c=$('.emp-check:checked').length;$('#btnBulk').prop('disabled',c===0).html('<i class="ri-settings-3-line me-1"></i>Bulk'+(c?' ('+c+')':''));}
$('#btnBulk').on('click',function(){$('#bulkCount').text($('.emp-check:checked').length);$('#bulkModal').modal('show');});
$('#btnBulkSave').on('click',function(){
    const ids=[];$('.emp-check:checked').each(function(){ids.push($(this).val());});
    $.post('{{ url("email-control/bulk-update") }}',{_token:'{{ csrf_token() }}',employee_ids:ids,field:$('#bulkField').val(),value:$('input[name=bulkValue]:checked').val(),reason:$('#bulkReason').val()},function(res){
        $('#bulkModal').modal('hide');if(res.success)loadData(currentPage);else alert(res.message||'Error');
    });
});
$('#filterShow').on('change',renderTable);
$(document).ready(function(){$('#btnLoad').on('click',function(){loadData(1);});loadData(1);});
</script>
@endpush
