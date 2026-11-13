@extends('layouts.app')

@section('content')
<div class="page-content">
    <div class="container-fluid">

        {{-- Header --}}
        <div class="row mb-3">
            <div class="col-12">
                <div class="d-flex justify-content-between align-items-center">
                    <h4 class="mb-0 fw-bold" style="color:#15648C;">
                        <i class="ri-star-smile-line me-2"></i>Claim Processing Ratings — {{ $companyName }}
                    </h4>
                    <a href="{{ route('admin.claim.rating.index') }}" class="btn btn-sm btn-outline-primary">
                        <i class="ri-settings-3-line me-1"></i>Manage Rules
                    </a>
                </div>
            </div>
        </div>

        {{-- Filters --}}
        <div class="card mb-3">
            <div class="card-body py-2">
                <div class="row align-items-end g-2">
                    <div class="col-md-3">
                        <label class="form-label fw-bold mb-1" style="font-size:12px;">Function</label>
                        <select class="form-select form-select-sm" id="filterFunction">
                            <option value="">All Functions</option>
                            @foreach($functions as $fn)
                                <option value="{{ $fn->id }}">{{ $fn->function_name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label fw-bold mb-1" style="font-size:12px;">Department</label>
                        <select class="form-select form-select-sm" id="filterDepartment">
                            <option value="">All Departments</option>
                            @foreach($departments as $dept)
                                <option value="{{ $dept->id }}">{{ $dept->department_name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label fw-bold mb-1" style="font-size:12px;">Months</label>
                        <div class="d-flex gap-1">
                            <select class="form-select form-select-sm" id="filterMonthFrom">
                                <option value="4">Apr</option><option value="5">May</option><option value="6">Jun</option>
                                <option value="7">Jul</option><option value="8">Aug</option><option value="9">Sep</option>
                                <option value="10">Oct</option><option value="11">Nov</option><option value="12">Dec</option>
                                <option value="1">Jan</option><option value="2">Feb</option><option value="3">Mar</option>
                            </select>
                            <select class="form-select form-select-sm" id="filterMonthTo">
                                <option value="4">Apr</option><option value="5">May</option><option value="6">Jun</option>
                                <option value="7">Jul</option><option value="8">Aug</option><option value="9">Sep</option>
                                <option value="10">Oct</option><option value="11">Nov</option><option value="12">Dec</option>
                                <option value="1">Jan</option><option value="2">Feb</option><option value="3" selected>Mar</option>
                            </select>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <button class="btn btn-primary btn-sm w-100" id="btnLoadRatings">
                            <i class="ri-refresh-line me-1"></i>Load Ratings
                        </button>
                    </div>
                </div>
            </div>
        </div>

        {{-- Role Summary Cards --}}
        <div class="row mb-3" id="roleSummaryCards">
            {{-- Populated dynamically --}}
        </div>

        {{-- Tabs --}}
        <ul class="nav nav-tabs">
            <li class="nav-item">
                <a class="nav-link active" data-bs-toggle="tab" href="#tabLeaderboard">
                    <i class="ri-trophy-line me-1"></i>Leaderboard
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link" data-bs-toggle="tab" href="#tabGrouped">
                    <i class="ri-folder-chart-line me-1"></i>Grouped View
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link" data-bs-toggle="tab" href="#tabRoleWise">
                    <i class="ri-team-line me-1"></i>Role-Wise
                </a>
            </li>
        </ul>

        <div class="tab-content mt-0">
            {{-- Leaderboard --}}
            <div class="tab-pane fade show active" id="tabLeaderboard">
                <div class="card border-top-0 rounded-0 rounded-bottom">
                    <div class="card-body p-2">
                        <div class="table-responsive">
                            <table class="table table-bordered table-sm table-hover align-middle mb-0" id="leaderboardTable" style="font-size:11px;">
                                <thead style="background:#15648C;color:white;">
                                    <tr>
                                        <th class="text-center">#</th>
                                        <th>Employee</th>
                                        <th>Dept</th>
                                        <th class="text-center">Role(s)</th>
                                        <th class="text-center">Claims</th>
                                        <th class="text-center">Avg</th>
                                        <th class="text-center">Stars</th>
                                    </tr>
                                </thead>
                                <tbody id="leaderboardBody">
                                    <tr><td colspan="7" class="text-center py-4 text-muted">Click "Load Ratings" to generate</td></tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>

            {{-- Grouped View --}}
            <div class="tab-pane fade" id="tabGrouped">
                <div class="card border-top-0 rounded-0 rounded-bottom">
                    <div class="card-body p-2">
                        <div class="row mb-2">
                            <div class="col-md-3">
                                <select class="form-select form-select-sm" id="groupBy">
                                    <option value="department">Group by Department</option>
                                    <option value="role">Group by Role</option>
                                    <option value="month">Group by Month</option>
                                </select>
                            </div>
                        </div>
                        <div id="groupedContent">
                            <div class="text-center py-4 text-muted">Click "Load Ratings" to generate</div>
                        </div>
                    </div>
                </div>
            </div>

            {{-- Role-Wise --}}
            <div class="tab-pane fade" id="tabRoleWise">
                <div class="card border-top-0 rounded-0 rounded-bottom">
                    <div class="card-body p-2">
                        <div class="row mb-2">
                            <div class="col-md-3">
                                <select class="form-select form-select-sm" id="filterRole">
                                    <option value="">All Roles</option>
                                    <option value="employee">Employee</option>
                                    <option value="mediator">Mediator</option>
                                    <option value="courier">Courier</option>
                                    <option value="verifier">Verifier</option>
                                    <option value="approver">Approver</option>
                                    <option value="finance">Finance</option>
                                </select>
                            </div>
                        </div>
                        <div id="roleWiseContent">
                            <div class="text-center py-4 text-muted">Click "Load Ratings" to generate</div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

    </div>
</div>

{{-- Detail Modal --}}
<div class="modal fade" id="lbDetailModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header" style="background:#15648C;color:white;">
                <h5 class="modal-title text-white"><i class="ri-star-smile-line me-1"></i>Rating Details</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body" id="lbDetailModalBody"></div>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<link rel="stylesheet" href="{{ asset('vendor/cdn/datatables.net-bs5@1.13.7/css/dataTables.bootstrap5.min.css') }}">
<script src="{{ asset('vendor/cdn/datatables.net@1.13.7/js/jquery.dataTables.min.js') }}"></script>
<script src="{{ asset('vendor/cdn/datatables.net-bs5@1.13.7/js/dataTables.bootstrap5.min.js') }}"></script>
<style>
    .group-header { cursor:pointer; transition:background 0.2s; }
    .group-header:hover { background:#e8f4fd !important; }
    .group-detail { display:none; }
</style>
<script>
const monthNames = ['Jan','Feb','Mar','Apr','May','Jun','Jul','Aug','Sep','Oct','Nov','Dec'];
const starColors = { 5:'#4CAF50', 4:'#8BC34A', 3:'#FFC107', 2:'#FF9800', 1:'#F44336' };
const roleColors = { employee:'#0d6efd', mediator:'#6f42c1', courier:'#fd7e14', verifier:'#20c997', approver:'#0dcaf0', finance:'#198754' };
const roleIcons = { employee:'ri-user-line', mediator:'ri-keyboard-line', courier:'ri-mail-send-line', verifier:'ri-shield-check-line', approver:'ri-checkbox-circle-line', finance:'ri-bank-line' };
const roleLabels = { employee:'Employee', mediator:'Mediator', courier:'Courier', verifier:'Verifier', approver:'Approver', finance:'Finance' };
let leaderboardDT = null, allData = null;

function getFilters() {
    const from = parseInt($('#filterMonthFrom').val());
    const to = parseInt($('#filterMonthTo').val());
    const fyOrder = [4,5,6,7,8,9,10,11,12,1,2,3];
    const fromIdx = fyOrder.indexOf(from);
    const toIdx = fyOrder.indexOf(to);
    const months = [];
    if (fromIdx <= toIdx) { for (let i = fromIdx; i <= toIdx; i++) months.push(fyOrder[i]); }
    else { for (let i = fromIdx; i < fyOrder.length; i++) months.push(fyOrder[i]); for (let i = 0; i <= toIdx; i++) months.push(fyOrder[i]); }
    return { months, function_id: $('#filterFunction').val() || '', department_id: $('#filterDepartment').val() || '' };
}

function renderStars(r, s) {
    s=s||12; let h=''; const f=Math.floor(r),hl=(r-f)>=0.5;
    for(let i=0;i<f;i++) h+='<i class="ri-star-fill" style="color:#FFC107;font-size:'+s+'px;"></i>';
    if(hl) h+='<i class="ri-star-half-fill" style="color:#FFC107;font-size:'+s+'px;"></i>';
    for(let i=0;i<5-f-(hl?1:0);i++) h+='<i class="ri-star-line" style="color:#ddd;font-size:'+s+'px;"></i>';
    return h;
}

function rBadge(stars, days) { return '<span class="badge" style="background:'+starColors[stars]+';font-size:9px;">'+stars+'★ '+days+'d</span>'; }

// PERF-04: the server sends one employee list and [employee, month, [[rule, stars, days], ...]] per claim
function expandCompact(d) {
    const rules = d.rules || [];
    d.claims = (d.claims_c || []).map(function(row) {
        const e = d.emps[row[0]] || [];
        const ratings = {};
        row[2].forEach(function(t) {
            const r = rules[t[0]] || {};
            ratings[r.stage_key] = {stars: t[1], days: t[2], role: r.responsible_role, stage_label: r.stage_label};
        });
        return {employee_id: e[0], emp_code: e[1], employee_name: e[2], department: e[3], month: row[1], ratings: ratings};
    });
    delete d.claims_c;
    return d;
}
const DETAIL_ROWS = 300; // rows drawn per group; the averages always use every claim
function moreRow(total, cols) {
    return total > DETAIL_ROWS ? '<tr><td colspan="'+cols+'" class="text-center text-muted">Showing '+DETAIL_ROWS+' of '+total+' rows. Narrow the filters to see the rest.</td></tr>' : '';
}

function loadRatings() {
    $('#leaderboardBody').html('<tr><td colspan="7" class="text-center py-3"><div class="spinner-border spinner-border-sm text-primary"></div></td></tr>');
    $.get('{{ route("admin.claim.rating.ratings") }}', Object.assign(getFilters(), {compact: 1}), function(res) {
        if (res.success && res.data && res.data.compact) res.data = expandCompact(res.data);
        if (!res.success || !res.data.claims || !res.data.claims.length) {
            $('#leaderboardBody').html('<tr><td colspan="7" class="text-center py-3 text-muted">No data found.</td></tr>');
            $('#groupedContent, #roleWiseContent').html('<div class="text-center py-3 text-muted">No data</div>');
            $('#roleSummaryCards').html(''); return;
        }
        allData = res.data;
        renderLeaderboard();
        renderGrouped($('#groupBy').val());
        renderRoleWise('');
        renderCards();
    });
}

function renderLeaderboard() {
    const empMap = {};
    allData.claims.forEach(function(c) {
        if (!empMap[c.employee_id]) empMap[c.employee_id] = {ec:c.emp_code, n:c.employee_name, d:c.department, cl:0, s:0, cnt:0, roles:{}};
        empMap[c.employee_id].cl++;
        Object.values(c.ratings).forEach(function(r) { empMap[c.employee_id].s+=r.stars; empMap[c.employee_id].cnt++; empMap[c.employee_id].roles[r.role]=1; });
    });
    const list = Object.values(empMap).map(function(e){e.avg=e.cnt?(e.s/e.cnt).toFixed(1):0;return e;}).sort(function(a,b){return b.avg-a.avg;});
    if (leaderboardDT) { leaderboardDT.destroy(); leaderboardDT=null; }
    let h='';
    list.forEach(function(e,i){
        const m=i<3?['🥇','🥈','🥉'][i]:(i+1);
        const rb=Object.keys(e.roles).map(function(r){return '<span class="badge" style="background:'+roleColors[r]+';font-size:8px;">'+r+'</span>';}).join(' ');
        h+='<tr><td class="text-center fw-bold">'+m+'</td><td><strong>'+e.ec+'</strong> '+e.n+'</td><td>'+(e.d||'-')+'</td><td class="text-center">'+rb+'</td><td class="text-center">'+e.cl+'</td><td class="text-center fw-bold" style="color:'+(starColors[Math.round(+e.avg)]||'#666')+';">'+e.avg+'</td><td class="text-center">'+renderStars(+e.avg)+'</td></tr>';
    });
    $('#leaderboardBody').html(h);
    leaderboardDT=$('#leaderboardTable').DataTable({pageLength:25,order:[[5,'desc']],dom:'<"d-flex justify-content-between mb-2"lf>rt<"d-flex justify-content-between mt-2"ip>'});
}

function renderGrouped(by) {
    const claims=allData.claims, rules=allData.rules, groups={};
    claims.forEach(function(c){
        if(by==='role'){ Object.values(c.ratings).forEach(function(r){ if(!groups[r.role])groups[r.role]={items:[],s:0,cnt:0}; groups[r.role].items.push(c); groups[r.role].s+=r.stars; groups[r.role].cnt++; }); return; }
        let key = by==='department'?(c.department||'Unknown'):monthNames[(c.month||1)-1];
        if(!groups[key])groups[key]={items:[],s:0,cnt:0};
        groups[key].items.push(c);
        Object.values(c.ratings).forEach(function(r){groups[key].s+=r.stars;groups[key].cnt++;});
    });
    const sorted=Object.entries(groups).sort(function(a,b){return(b[1].cnt?b[1].s/b[1].cnt:0)-(a[1].cnt?a[1].s/a[1].cnt:0);});
    let h='';
    sorted.forEach(function(e,gi){
        const gn=e[0],g=e[1],avg=g.cnt?(g.s/g.cnt).toFixed(1):0,col=starColors[Math.round(+avg)]||'#666';
        h+='<div class="border rounded mb-2"><div class="group-header d-flex align-items-center justify-content-between px-3 py-2" data-group="'+gi+'" style="background:#f0f4ff;">';
        h+='<div><strong style="color:#15648C;">'+gn+'</strong> <small class="text-muted">('+g.items.length+' claims)</small></div>';
        h+='<div class="d-flex align-items-center gap-2"><span class="fw-bold" style="color:'+col+';font-size:15px;">'+avg+'</span>'+renderStars(+avg,13)+'<i class="ri-arrow-down-s-line text-muted"></i></div></div>';
        h+='<div class="group-detail" id="group-'+gi+'"><table class="table table-sm table-bordered mb-0" style="font-size:10px;"><thead style="background:#e8f4fd;"><tr><th>Employee</th><th>Mon</th>';
        rules.forEach(function(r){h+='<th class="text-center" title="'+r.stage_label+'">'+r.stage_label.substring(0,15)+'</th>';});
        h+='</tr></thead><tbody>';
        g.items.slice(0, DETAIL_ROWS).forEach(function(c){
            h+='<tr><td><strong>'+c.emp_code+'</strong> '+c.employee_name+'</td><td>'+monthNames[(c.month||1)-1]+'</td>';
            rules.forEach(function(r){const rt=c.ratings[r.stage_key]; h+='<td class="text-center">'+(rt?rBadge(rt.stars,rt.days):'—')+'</td>';});
            h+='</tr>';
        });
        h+=moreRow(g.items.length, rules.length+2);
        h+='</tbody></table></div></div>';
    });
    $('#groupedContent').html(h||'<div class="text-center py-3 text-muted">No data</div>');
}

function renderRoleWise(filter) {
    const rg={};
    allData.claims.forEach(function(c){
        Object.keys(c.ratings).forEach(function(sk){
            const r=c.ratings[sk]; if(filter&&r.role!==filter)return;
            if(!rg[r.role])rg[r.role]={items:[],s:0,cnt:0};
            rg[r.role].items.push({ec:c.emp_code,n:c.employee_name,d:c.department,mo:c.month,st:r.stage_label,days:r.days,stars:r.stars});
            rg[r.role].s+=r.stars; rg[r.role].cnt++;
        });
    });
    let h='';
    Object.keys(rg).forEach(function(role){
        const g=rg[role],avg=(g.s/g.cnt).toFixed(1),col=starColors[Math.round(+avg)]||'#666';
        h+='<div class="border rounded mb-2"><div class="d-flex align-items-center justify-content-between px-3 py-2" style="border-left:4px solid '+roleColors[role]+';background:'+roleColors[role]+'10;">';
        h+='<div><i class="'+(roleIcons[role]||'ri-star-line')+'" style="color:'+roleColors[role]+';"></i> <strong>'+(roleLabels[role]||role)+'</strong> <small class="text-muted">('+g.cnt+')</small></div>';
        h+='<div><span class="fw-bold" style="color:'+col+';font-size:15px;">'+avg+'</span> '+renderStars(+avg,12)+'</div></div>';
        h+='<table class="table table-sm table-bordered mb-0" style="font-size:10px;"><thead><tr style="background:#fafafa;"><th>Employee</th><th>Dept</th><th>Mon</th><th>Stage</th><th class="text-center">Days</th><th class="text-center">Rating</th></tr></thead><tbody>';
        g.items.sort(function(a,b){return b.stars-a.stars;}).slice(0, DETAIL_ROWS).forEach(function(it){
            h+='<tr><td><strong>'+it.ec+'</strong> '+it.n+'</td><td>'+(it.d||'-')+'</td><td>'+monthNames[(it.mo||1)-1]+'</td><td>'+it.st+'</td><td class="text-center">'+it.days+'d</td><td class="text-center">'+rBadge(it.stars,it.days)+'</td></tr>';
        });
        h+=moreRow(g.items.length, 6);
        h+='</tbody></table></div>';
    });
    $('#roleWiseContent').html(h||'<div class="text-center py-3 text-muted">No data</div>');
}

function renderCards() {
    const rs=allData.role_summary; let h='';
    Object.keys(rs).forEach(function(role){
        const s=rs[role],col=starColors[Math.round(s.avg_stars)]||'#666';
        h+='<div class="col-md-2 col-sm-4 mb-2"><div class="card border-0 shadow-sm h-100"><div class="card-body text-center py-2">';
        h+='<i class="'+(roleIcons[role]||'ri-star-line')+'" style="font-size:20px;color:'+roleColors[role]+';"></i>';
        h+='<div style="font-size:10px;font-weight:600;">'+(roleLabels[role]||role)+'</div>';
        h+='<div class="fw-bold" style="font-size:18px;color:'+col+';">'+s.avg_stars+'</div>';
        h+=renderStars(s.avg_stars,11)+'<div class="text-muted" style="font-size:9px;">'+s.count+'</div>';
        h+='</div></div></div>';
    });
    $('#roleSummaryCards').html(h);
}

$(document).ready(function(){
    $('#btnLoadRatings').on('click', loadRatings);
    $(document).on('click','.group-header',function(){$('#group-'+$(this).data('group')).slideToggle(200);});
    $('#groupBy').on('change',function(){if(allData)renderGrouped($(this).val());});
    $('#filterRole').on('change',function(){if(allData)renderRoleWise($(this).val());});
});
</script>
@endpush
