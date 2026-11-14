@php
    use Illuminate\Support\Facades\Route;
    $prefix = $prefix ?? 'vnr';
    $status = $status ?? 'pending';
    $companyId = $companyId ?? session('company_id', 1);
    $routeName = fn (string $name) => "{$prefix}.{$name}";
    $routeUrl = fn (string $name) => Route::has($routeName($name)) ? route($routeName($name)) : null;
    $approverRoutes = [
        'pendingList' => $routeUrl('approver.pending.list'),
        'pendingDay' => $routeUrl('approver.pending.day'),
        'approvedList' => $routeUrl('approver.approved.list'),
        'approvedDay' => $routeUrl('approver.approved.day'),
        'approveClaim' => $routeUrl('approver.claim.approve'),
        'approveHeadwise' => $routeUrl('approver.claim.approve.headwise'),
        'submitClaim' => $routeUrl('approver.claim.submit'),
        'expenseDetail' => $routeUrl('approver.expense.detail'),
        'teamMembers' => $routeUrl('team.members.list'),
        'teamExpenses' => $routeUrl('team.all.expenses'),
        'teamRunningKm' => Route::has('approver.team.runningkm') ? route('approver.team.runningkm') : url('approver/team-runningkm'),
    ];
@endphp

<div class="page-content">
<div class="container-fluid">
<div class="row">
<div class="col-xl-12">
<div class="card card-height-100 border-0 shadow-sm">

    {{-- ═══ App-Style Tab Bar ═══ --}}
    <div class="card-header bg-white border-bottom-0 pb-0 pt-3 px-3">
        <div class="d-flex align-items-center justify-content-between flex-wrap gap-2">
            <div class="app-tab-bar d-flex" role="tablist">
                <a href="{{ url('vnr/approver?status=' . $status) }}" class="app-tab {{ $prefix==='vnr'?'active':'' }}">VNR</a>
                @if(session('reporting_vess') == 'Y')
                <a href="{{ url('vvnr/approver?status=' . $status) }}" class="app-tab {{ $prefix==='vvnr'?'active':'' }}">VVNR</a>
                @endif
                @if(session('reporting_nursery') == 'Y')
                <a href="{{ url('nursery/approver?status=' . $status) }}" class="app-tab {{ $prefix==='nursery'?'active':'' }}">NURSERY</a>
                @endif
            </div>
            <div class="status-pills d-flex gap-1">
                <a href="{{ url($prefix.'/approver?status=pending') }}" class="status-pill {{ $status==='pending'?'active-pending':'' }}">
                    <i class="ri-time-line"></i> Pending
                </a>
                <a href="{{ url($prefix.'/approver?status=approved') }}" class="status-pill {{ $status==='approved'?'active-approved':'' }}">
                    <i class="ri-checkbox-circle-line"></i> Approved
                </a>
            </div>
        </div>
    </div>

    <div class="card-body pt-3">
        {{-- ═══ Action Chips ═══ --}}
        <div class="d-flex flex-wrap gap-2 mb-3">
            @if($prefix !== 'vvnr' && session('show_backdate_approver') == 'Y')
            <a href="{{ Route::has('admin.odo.backdate.approval') ? route('admin.odo.backdate.approval') : url('odo-backdate-approval') }}" class="action-chip">
                <i class="ri-time-line"></i><span>Delayed Odo</span>
            </a>
            @endif
            @if($prefix !== 'vvnr' && session('show_approver_advance') == 'Y')
            <a href="{{ Route::has('advance.approver.index') ? route('advance.approver.index') : url('advance-requests/advanceApprovals') }}" class="action-chip">
                <i class="ri-hand-coin-line"></i><span>Adv. Req.</span>
            </a>
            <a href="{{ url('/advance-requests/report') }}" class="action-chip" title="Advance Report">
                <i class="ri-file-chart-line"></i><span>Adv. Report</span>
            </a>
            @endif
            @if($prefix !== 'vvnr' && session('show_odo_approver') == 'Y')
            <a href="{{ Route::has('admin.odo.approval') ? route('admin.odo.approval') : url('odo-backdate-approval') }}" class="action-chip">
                <i class="ri-speed-line"></i><span>Excess Running</span>
            </a>
            @endif
            <button class="action-chip" id="teamExpBtn"><i class="ri-team-line"></i><span>Team Exp.</span></button>
            <button class="action-chip" id="runningKmBtn"><i class="ri-roadster-line"></i><span>Running KM</span></button>
        </div>

        @if($status === 'pending')
        <div class="section-bar pending-bar mb-3">
            <span><i class="ri-time-line"></i> Pending Claims</span>
            <span class="badge bg-white text-dark" id="pendingCount">...</span>
        </div>
        <div class="alert py-2 px-3 mb-3 border-0" style="font-size:13px;background:linear-gradient(135deg,#fff3cd,#ffeeba);color:#856404;">
            <i class="ri-error-warning-line me-1"></i>
            If there are no changes in the approved amount, kindly click on submit button directly.
        </div>
        <div id="pendingCards">
            <div class="text-center py-5"><div class="spinner-border spinner-border-sm text-primary"></div> <span class="text-muted ms-2">Loading...</span></div>
        </div>
        @endif

        @if($status === 'approved')
        <div class="section-bar approved-bar mb-3">
            <span><i class="ri-checkbox-circle-line"></i> Approved Claims</span>
            <span class="badge bg-white text-dark" id="approvedCount">...</span>
        </div>
        <div class="row g-2 mb-3 align-items-center">
            <div class="col-lg-3 col-md-4 col-sm-6"><select class="form-select form-select-sm rounded-pill" id="filterMember"><option value="">All Members</option></select></div>
            <div class="col-lg-2 col-md-3 col-sm-6"><select class="form-select form-select-sm rounded-pill" id="filterMonth">
                <option value="">All Months</option>
                <option value="4">April</option><option value="5">May</option><option value="6">June</option>
                <option value="7">July</option><option value="8">August</option><option value="9">September</option>
                <option value="10">October</option><option value="11">November</option><option value="12">December</option>
                <option value="1">January</option><option value="2">February</option><option value="3">March</option>
            </select></div>
            <div class="col-auto d-flex gap-1">
                <button class="btn btn-sm btn-primary rounded-pill px-3" id="applyFilter"><i class="ri-search-line"></i> Filter</button>
                <button class="btn btn-sm btn-light rounded-pill px-3" id="resetFilter" title="Reset all filters"><i class="ri-refresh-line"></i></button>
            </div>
            <div class="col-lg-4 col-md-4 col-sm-12 ms-auto">
                <div class="position-relative">
                    <input type="text" id="searchApproved" class="form-control form-control-sm rounded-pill ps-4" placeholder="Search by name, code, month, amount..." aria-label="Search by name, code, month, amount">
                    <i class="ri-search-line position-absolute top-50 start-0 translate-middle-y ms-2 text-muted" style="pointer-events:none;"></i>
                </div>
            </div>
        </div>
        <div id="approvedCards">
            <div class="text-center py-5"><div class="spinner-border spinner-border-sm text-primary"></div> <span class="text-muted ms-2">Loading approved claims...</span></div>
        </div>
        <div id="approvedPagination" class="d-flex justify-content-between align-items-center flex-wrap gap-2 mt-3 pt-2 border-top" style="display:none;"></div>
        @endif
    </div>
</div></div></div></div></div>

{{-- Modals --}}
<div class="modal fade" id="dayModal" data-bs-backdrop="static" tabindex="-1"><div class="modal-dialog modal-fullscreen"><div class="modal-content border-0 shadow"><div class="modal-header py-2 bg-light"><h6 class="modal-title" id="dayModalTitle">Claims</h6><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div><div class="modal-body" id="dayModalBody"></div></div></div></div>
<div class="modal fade" id="teamExpModal" data-bs-backdrop="static" tabindex="-1"><div class="modal-dialog modal-xl"><div class="modal-content border-0 shadow"><div class="modal-header py-2 bg-light"><h6 class="modal-title"><i class="ri-team-line me-1"></i> Team All Expenses</h6><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div><div class="modal-body" id="teamExpBody"></div></div></div></div>
<div class="modal fade" id="runKmModal" data-bs-backdrop="static" tabindex="-1"><div class="modal-dialog modal-xl"><div class="modal-content border-0 shadow"><div class="modal-header py-2 bg-light"><h6 class="modal-title"><i class="ri-roadster-line me-1"></i> Team Running KM</h6><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div><div class="modal-body" id="runKmBody"></div></div></div></div>

{{-- Claim Detail Modal (same as eHome/Verifier) --}}
<x-modal.view_claim_detail />

@push('styles')
<style>
.app-tab-bar{background:var(--vz-light,#f3f6f9);border-radius:25px;padding:3px;}
.app-tab{padding:6px 20px;border-radius:22px;font-size:13px;font-weight:600;color:#555;text-decoration:none;transition:all .2s;}
.app-tab.active{background:var(--vz-primary,#405189);color:#fff;box-shadow:0 2px 8px rgba(var(--vz-primary-rgb,64,81,137),.35);}
.app-tab:hover:not(.active){background:rgba(var(--vz-primary-rgb,64,81,137),.08);color:var(--vz-primary,#405189);}
.status-pill{padding:5px 14px;border-radius:20px;font-size:12px;font-weight:600;text-decoration:none;color:#666;background:#f5f5f5;transition:all .2s;}
.status-pill.active-pending{background:linear-gradient(135deg,#f7971e,#ffd200);color:#fff;box-shadow:0 2px 8px rgba(247,151,30,.3);}
.status-pill.active-approved{background:#d7f3e6;color:#065f46;box-shadow:0 2px 8px rgba(10,179,156,.3);}
.status-pill:hover:not(.active-pending):not(.active-approved){background:#e9e9e9;}
.action-chip{display:inline-flex;align-items:center;gap:6px;padding:8px 16px;border-radius:25px;border:2px solid var(--vz-primary,#405189);background:#fff;color:var(--vz-primary,#405189);font-size:13px;font-weight:600;cursor:pointer;transition:all .2s;text-decoration:none;}
.action-chip:hover{background:var(--vz-primary,#405189);color:#fff;transform:translateY(-1px);box-shadow:0 3px 10px rgba(var(--vz-primary-rgb,64,81,137),.25);}
.action-chip i{font-size:16px;}
.section-bar{padding:10px 16px;border-radius:8px;font-size:14px;font-weight:700;display:flex;align-items:center;justify-content:space-between;}
.section-bar.pending-bar{background:linear-gradient(135deg,rgba(var(--vz-primary-rgb,64,81,137),.1),rgba(var(--vz-primary-rgb,64,81,137),.05));color:var(--vz-primary,#405189);}
.section-bar.approved-bar{background:linear-gradient(135deg,rgba(var(--vz-success-rgb,10,179,156),.12),rgba(var(--vz-success-rgb,10,179,156),.05));color:var(--vz-success,#0ab39c);}

.claim-card{border:none;border-radius:14px;overflow:hidden;box-shadow:0 2px 12px rgba(0,0,0,.06);margin-bottom:12px;position:relative;transition:all .2s;}
.claim-card:hover{box-shadow:0 4px 20px rgba(0,0,0,.1);transform:translateY(-1px);}
.claim-card .stripe{position:absolute;left:0;top:0;bottom:0;width:5px;}
.claim-card .stripe.primary{background:#dde6ff; color: #1e3a8a;}
.claim-card .stripe.success{background:#d7f3e6; color: #065f46;}
.claim-card .card-body{padding:14px 14px 14px 20px;}
.claim-card .emp-name{font-weight:700;font-size:15px;color:var(--vz-primary,#405189);}
.claim-card .meta{font-size:12px;color:#666;margin-top:2px;}
.claim-card .amount{font-weight:700;color:var(--vz-primary,#405189);font-size:15px;}
.claim-card .amount.success{color:var(--vz-success,#0ab39c);}
.pill-btn{display:inline-flex;align-items:center;gap:4px;padding:5px 14px;border-radius:20px;font-size:11px;font-weight:600;text-decoration:none;cursor:pointer;transition:all .15s;border:none;}
.pill-btn.att{background:rgba(var(--vz-primary-rgb,64,81,137),.1);color:var(--vz-primary,#405189);}
.pill-btn.att:hover{background:var(--vz-primary,#405189);color:#fff;}
.pill-btn.view{background:rgba(var(--vz-info-rgb,41,156,219),.1);color:var(--vz-info,#299cdb);}
.pill-btn.view:hover{background:var(--vz-info,#299cdb);color:#fff;}
.pill-btn.submit{background:rgba(var(--vz-success-rgb,10,179,156),.1);color:var(--vz-success,#0ab39c);}
.pill-btn.submit:hover{background:var(--vz-success,#0ab39c);color:#fff;}
.mini-stepper{display:flex;align-items:flex-start;padding:10px 0 4px;border-top:1px solid #f0f0f0;margin-top:10px;}
.mini-step{flex:1;text-align:center;position:relative;}
.mini-step:not(:last-child)::after{content:'';position:absolute;top:14px;left:55%;width:90%;height:2px;background:#e0e0e0;z-index:0;}
.mini-step.done:not(:last-child)::after{background:var(--vz-primary,#405189);}
.step-dot{width:28px;height:28px;border-radius:50%;display:inline-flex;align-items:center;justify-content:center;font-size:12px;position:relative;z-index:1;}
.step-dot.done{background:var(--vz-primary,#405189);color:#fff;}
.step-dot.wait{background:#f5f5f5;color:#aaa;border:2px solid var(--vz-primary,#405189);}
.mini-step .lbl{font-size:10px;font-weight:600;color:#555;margin-top:3px;}
.mini-step .val{font-size:10px;color:var(--vz-primary,#405189);font-weight:700;}
.mini-step .val-date{font-size:9px;color:#6b7280;font-weight:400;margin-top:1px;}
.empty-state{text-align:center;padding:40px 20px;color:#aaa;}
.empty-state i{font-size:48px;display:block;margin-bottom:10px;}
.empty-state p{font-size:14px;margin:0;}
.day-tbl th,.day-tbl td{font-size:12px;vertical-align:middle;white-space:nowrap;}
.day-tbl td.remark-col{white-space:normal !important;word-break:break-word;line-height:1.4;}
.day-tbl .amt-input{width:85px;}
.btn-pax-pill{display:inline-flex;align-items:center;gap:3px;padding:2px 8px;border-radius:12px;font-size:11px;font-weight:600;color:var(--vz-primary,#405189);background:rgba(var(--vz-primary-rgb,64,81,137),.08);border:1px solid rgba(var(--vz-primary-rgb,64,81,137),.2);cursor:pointer;transition:all .15s;line-height:1.3;}
.btn-pax-pill:hover{background:var(--vz-primary,#405189);color:#fff;border-color:var(--vz-primary,#405189);}
.pax-detail-row td{white-space:normal !important;}
.pax-sub-table th, .pax-sub-table td{padding:5px 8px;font-size:11.5px;vertical-align:middle;}
.rotating{animation:spin 1s linear infinite;display:inline-block;}
@keyframes spin{from{transform:rotate(0)}to{transform:rotate(360deg)}}
</style>
@endpush

@push('scripts')
<script src="{{ asset('custom/js/pages/claim.js') }}?v={{ time() }}"></script>
<script src="{{ asset('custom/js/pages/approver-day-filters.js') }}?v={{ filemtime(public_path('custom/js/pages/approver-day-filters.js')) }}"></script>
<script src="{{ asset('vendor/cdn/sweetalert2@11.26.25/dist/sweetalert2.all.min.js') }}"></script>
<script>
$(function(){
    const R=@json($approverRoutes);const csrf=@json(csrf_token());const status=@json($status);const comId=@json((string)$companyId);const prefix=@json($prefix);
    const M=['','January','February','March','April','May','June','July','August','September','October','November','December'];
    if(status==='pending')loadPending();
    if(status==='approved'){
        loadMembers();
        loadApproved('','');
    }
    $('#teamExpBtn').on('click',()=>{$('#teamExpModal').modal('show');loadTeamExp();});
    $('#runningKmBtn').on('click',()=>{$('#runKmModal').modal('show');loadRunKm();});
    $('#applyFilter').on('click',()=>loadApproved($('#filterMember').val(),$('#filterMonth').val()));
    $('#resetFilter').on('click',()=>{
        $('#filterMember,#filterMonth,#searchApproved').val('');
        loadApproved('','');
    });
    let searchApprovedTimer = null;
    $('#searchApproved').on('input', function(){
        clearTimeout(searchApprovedTimer);
        searchApprovedTimer = setTimeout(()=>{
            approvedCurrentPage = 1;
            renderApprovedCards();
        }, 150);
    });

    function loadPending(){
        if(!R.pendingList)return;
        $.getJSON(R.pendingList).done(res=>{
            const list=res.Pending_list||[];$('#pendingCount').text(list.length);
            // APPR-WAIT-01: say how many submitted months are still with the verification team
            const wait=parseInt(res.Awaiting_verification||0,10);
            const waitNote=wait>0?`<div class="alert alert-info py-2 px-3 mb-2 small" role="status"><i class="ri-time-line me-1" aria-hidden="true"></i>${wait} submitted month(s) of your team are with the verification team. They appear here for approval once verified.</div>`:'';
            if(!list.length){$('#pendingCards').html(waitNote+'<div class="empty-state"><i class="ri-inbox-line"></i><p>Nothing waiting for your approval</p></div>');return;}
            let h='';list.forEach(item=>{
                if(list.length && h===''){console.log('[Approver API] sample item keys:', Object.keys(item), 'DateOfSubmit:', item.DateOfSubmit, 'Verified_Date:', item.Verified_Date);}
                const id=item.EmployeeID||'',nm=eName(item),mo=item.Month||'',yr=item.YearId||'';
                const amt=n(item.Claim_Amount||0),vAmt=n(item.Verified_Amount||0),aAmt=n(item.Approved_Amount||item.TotalApprovedAmt||0);
                const tc=item.Total_Claim||'0';
                const diff=amt-vAmt;
                const cour=item.RecevingDate||item.ReceivingDate||'';const cv=vd(cour);
                const hasVerifier = (vAmt > 0) || (item.Verified_Date && item.Verified_Date !== '0000-00-00') || (item.verification_status === 'Verified');

                // Determine stage: Post Pending -> Verification Pending -> Verified
                let vStatusBadge = '';
                let statusRightHtml = '';
                let submitBtnHtml = '';

                if (!cv) {
                    // Stage 1: Post / Courier is pending
                    vStatusBadge = '<span class="badge bg-warning text-dark ms-1" style="font-size:10px;"><i class="ri-truck-line me-1"></i>Post Pending</span>';
                    statusRightHtml = '<div style="font-size:11px;color:#d97706;font-weight:600"><i class="ri-truck-line"></i> Post Pending</div>';
                    submitBtnHtml = `<button type="button" class="pill-btn disabled-submit-btn ms-auto" data-reason="Post / Courier is pending" title="Post Pending - Cannot submit"><i class="ri-lock-line me-1"></i> Submit</button>`;
                } else if (!hasVerifier) {
                    // Stage 2: Courier received, verification pending
                    vStatusBadge = '<span class="badge bg-warning text-dark ms-1" style="font-size:10px;"><i class="ri-time-line me-1"></i>Verification Pending</span>';
                    statusRightHtml = '<div style="font-size:11px;color:#d97706;font-weight:600"><i class="ri-time-line"></i> Verification Pending</div>';
                    submitBtnHtml = `<button type="button" class="pill-btn disabled-submit-btn ms-auto" data-reason="Verification is pending" title="Verification Pending - Cannot submit"><i class="ri-lock-line me-1"></i> Submit</button>`;
                } else {
                    // Stage 3: Verified
                    vStatusBadge = '<span class="badge bg-success ms-1" style="font-size:10px;"><i class="ri-checkbox-circle-line me-1"></i>Verified</span>';
                    statusRightHtml = `<div style="font-size:11px;color:#198754;font-weight:600"><i class="ri-checkbox-circle-line"></i> Verified: ₹${fa(vAmt)}</div>`;
                    if (diff > 0) {
                        statusRightHtml += `<div style="font-size:11px;color:#dc3545">Diff: ₹${fa(diff)}</div>`;
                    }
                    submitBtnHtml = `<a class="pill-btn submit submit-btn ms-auto" data-empid="${e(id)}" data-month="${e(mo)}" data-yearid="${e(yr)}" data-verified="1"><i class="ri-check-double-line"></i> Submit</a>`;
                }

                h+=`<div class="card claim-card"><div class="stripe primary"></div><div class="card-body">
                <div class="d-flex justify-content-between align-items-start">
                    <div><div class="emp-name">${e(nm)} ${vStatusBadge}</div><div class="meta"><i class="ri-calendar-line"></i> ${e(M[+mo]||mo)} &nbsp;|&nbsp; Claims: <b>${e(tc)}</b> &nbsp;|&nbsp; Courier: ${cv?'<span class="text-success fw-semibold">'+fd(cour)+'</span>':'<span class="text-warning">Pending</span>'}</div></div>
                    <div class="text-end">
                        <div class="amount">₹${fa(amt)}</div>
                        ${statusRightHtml}
                    </div>
                </div>
                <div class="d-flex flex-wrap gap-2 mt-2 align-items-center">
                    <a class="pill-btn att att-btn" data-empid="${e(id)}" data-month="${e(mo)}"><i class="ri-calendar-check-line"></i> Att.</a>
                    <a class="pill-btn view view-btn" data-empid="${e(id)}" data-month="${e(mo)}" data-yearid="${e(yr)}" data-name="${e(nm)}" data-mode="pending"><i class="ri-eye-line"></i> View</a>
                    ${submitBtnHtml}
                </div>
                <div class="mini-stepper">
                    <div class="mini-step done"><div class="step-dot done"><i class="ri-user-3-line"></i></div><div class="lbl">Claimant</div><div class="val">₹${fa(amt)}</div><div class="val-date">${fd(fld(item,'DateOfSubmit','dateofsubmit','SubmitDate','Crdate'))}</div></div>
                    <div class="mini-step ${cv?'done':''}"><div class="step-dot ${cv?'done':'wait'}"><i class="ri-truck-line"></i></div><div class="lbl">Post</div><div class="val">${cv?fd(cour):'-'}</div></div>
                    <div class="mini-step ${hasVerifier?'done':''}"><div class="step-dot ${hasVerifier?'done':'wait'}"><i class="ri-file-search-line"></i></div><div class="lbl">Verifier</div><div class="val">${hasVerifier?'₹'+fa(vAmt):'Pending'}</div><div class="val-date">${hasVerifier?fd(fld(item,'Verified_Date','verified_date','VerifiedDate','VerifDate','Verify_Date')):'-'}</div></div>
                    <div class="mini-step"><div class="step-dot wait"><i class="ri-shield-check-line"></i></div><div class="lbl">Approver</div><div class="val">${aAmt>0?'₹'+fa(aAmt):'Pending'}</div><div class="val-date">${aAmt>0?fd(fld(item,'Approved_Date','approved_date','ApprovedDate','ApprDate','Appr_Date')):'-'}</div></div>
                </div></div></div>`;
            });$('#pendingCards').html(waitNote+h);
        }).fail(()=>$('#pendingCards').html('<div class="empty-state text-muted py-5 text-center"><i class="ri-error-warning-line fs-24 d-block mb-2"></i><p>Failed to load</p></div>'));
    }

    // ═══ VIEW DAY ═══
    $(document).on('click','.view-btn',function(ev){ev.preventDefault();
        const id=$(this).data('empid'),mo=$(this).data('month'),yr=$(this).data('yearid'),nm=$(this).data('name')||'',mode=$(this).data('mode')||'pending';
        $('#dayModalTitle').text((mode==='approved'?'Approved':'Pending')+' - '+nm+' - '+(M[+mo]||mo));
        $('#dayModalBody').html('<div class="text-center py-4"><div class="spinner-border text-primary spinner-border-sm"></div></div>');$('#dayModal').modal('show');
        $.getJSON(mode==='approved'?R.approvedDay:R.pendingDay,{empid:id,month:mo,yearid:yr,comid:comId}).done(res=>{
            renderDay(res.DayWiseDetails||[],mode,id,mo,nm);
        }).fail(()=>$('#dayModalBody').html('<p class="text-center text-danger">Failed to load.</p>'));
    });
    function renderDay(list,mode,cE,cM,empName){
        if(!list.length){$('#dayModalBody').html('<div class="empty-state"><i class="ri-inbox-line"></i><p>No details</p></div>');return;}
        const ed=mode==='pending';let tC=0,tV=0,tA=0,rows='';
        const unapproved=[];
        list.forEach((d,i)=>{const eid=d.ExpId||d.expId||'',empId=d.EmployeeID||d.employeeId||d.EId||cE,cn=d.ClaimName||d.claimName||'Expense';
            const ca=n(d.FilledTAmt||d.Claim_Amount||0),va=n(d.VerifyTAmt||d.Verified_Amount||0);
            const rawAppr=n(d.ApprTAmt||d.Approved_Amount||0);
            const aa=ed?(rawAppr>0?rawAppr:va):rawAppr;
            
            // Amount difference logic: If verifier deducted amount, show -amount in red
            let diffText = '-';
            let diffClass = 'text-muted';
            if (va < ca) {
                const deducted = ca - va;
                diffText = '-' + fa(deducted);
                diffClass = 'text-danger fw-bold';
            } else if (va > ca) {
                const added = va - ca;
                diffText = '+' + fa(added);
                diffClass = 'text-success fw-bold';
            }

            const bd=d.BillDate||d.billDate||'',apd=d.ApprDate||'',isA=vd(apd);
            tC+=ca;tV+=va;tA+=aa;
            if(ed && !isA && aa!==va){unapproved.push('#'+eid);}

            // Build image thumbnails
            const imgs=[];
            ['Img_1','Img_2','Img_3'].forEach(k=>{if(d[k])imgs.push(d[k]);});
            ['opening_filepath','closing_filepath'].forEach(k=>{if(d[k])imgs.push(d[k]);});
            const pdfFiles=[];
            ['pdf_1','pdf_2'].forEach(k=>{if(d[k])pdfFiles.push(d[k]);});
            let mediaHtml='';
            if(imgs.length){mediaHtml+=imgs.map(u=>`<a href="${u}" target="_blank"><img alt="Bill or document image" src="${u}" class="rounded border" style="height:35px;width:35px;object-fit:cover;margin-right:3px;" onerror="this.style.display='none'"></a>`).join('');}
            if(pdfFiles.length){mediaHtml+=pdfFiles.map((u,pi)=>`<a href="${u}" target="_blank" class="badge bg-danger-subtle text-danger" style="font-size:9px;margin-right:3px;"><i class="ri-file-pdf-line"></i></a>`).join('');}
            if(!mediaHtml)mediaHtml='<span class="text-muted" style="font-size:9px;">—</span>';

            // Employee & Verifier remarks
            const empRemark = String(d.DateEntryRemark || d.EmpRemark || d.Remark || '').trim();
            const empRmkContent = empRemark ? `<div style="font-size:11px;color:#475569;word-break:break-word;" title="${e(empRemark)}">${e(empRemark)}</div>` : '<span class="text-muted" style="font-size:11px;">—</span>';

            const vRmk = String(d.VerifyTRemark || d.VerifyRemark || d.verifier_remark || d.VerifierRemark || d.VerTRemark || '').trim();
            let vRmkContent = '';
            if (vRmk) {
                if (va < ca) {
                    vRmkContent = `<div class="p-1 px-2 rounded" style="background:#fef2f2;border-left:3px solid #ef4444;font-size:11px;color:#991b1b;word-break:break-word;" title="${e(vRmk)}">
                        <i class="ri-error-warning-line me-1 text-danger"></i>${e(vRmk)}
                    </div>`;
                } else {
                    vRmkContent = `<div style="font-size:11px;color:#334155;word-break:break-word;" title="${e(vRmk)}">${e(vRmk)}</div>`;
                }
            } else {
                vRmkContent = '<span class="text-muted" style="font-size:11px;">—</span>';
            }

            // ── PAX Details Building ──
            const hasPax = (n(d.NoOfPAX) > 0) || (d.PaxEmployeeDetails && d.PaxEmployeeDetails.length > 0) || (d.PaxDetails && String(d.PaxDetails).trim() !== '');
            let paxBadgeHtml = '';
            let paxRowHtml = '';

            if (hasPax) {
                const paxCount = n(d.NoOfPAX) || (d.PaxEmployeeDetails ? d.PaxEmployeeDetails.length : 0);
                paxBadgeHtml = `<div class="mt-1">
                    <button type="button" class="btn-pax-pill toggle-pax-btn" data-target="pax_row_${e(eid)}" title="Click to view participant details">
                        <i class="ri-group-line"></i><span>PAX: <b>${paxCount}</b></span><i class="ri-arrow-down-s-line pax-chevron"></i>
                    </button>
                </div>`;

                // Build employee participants list
                let empListHtml = '';
                if (d.PaxEmployeeDetails && d.PaxEmployeeDetails.length > 0) {
                    let empRows = '';
                    d.PaxEmployeeDetails.forEach((pEmp, pIdx) => {
                        const empCode = pEmp.EmpCode || pEmp.EmpCode_New || pEmp.EmployeeID || '-';
                        const empName = pEmp.EmployeeName || [pEmp.Fname, pEmp.Sname, pEmp.Lname].filter(Boolean).join(' ') || ('Employee #' + empCode);
                        const dept = pEmp.department_name || '-';
                        const grade = pEmp.grade_name || '-';
                        empRows += `<tr>
                            <td class="text-center text-muted" style="width:35px;">${pIdx + 1}</td>
                            <td style="width:90px;"><span class="badge bg-light text-dark border font-monospace">${e(empCode)}</span></td>
                            <td class="fw-semibold text-dark">${e(empName)}</td>
                            <td><span class="text-secondary">${e(dept)}</span></td>
                            <td class="text-center" style="width:65px;"><span class="badge bg-secondary-subtle text-secondary">${e(grade)}</span></td>
                        </tr>`;
                    });
                    empListHtml = `<div class="table-responsive mb-1">
                        <table class="table table-sm table-bordered bg-white mb-0 pax-sub-table">
                            <thead class="table-light">
                                <tr class="text-muted" style="font-size:11px;">
                                    <th class="text-center" style="width:35px;">#</th>
                                    <th>Emp Code</th>
                                    <th>Employee Name</th>
                                    <th>Department</th>
                                    <th class="text-center">Grade</th>
                                </tr>
                            </thead>
                            <tbody>${empRows}</tbody>
                        </table>
                    </div>`;
                } else if (d.PaxDetails && String(d.PaxDetails).trim() !== '') {
                    empListHtml = `<div class="text-muted small py-1"><i class="ri-information-line me-1"></i> Participant IDs: <span class="fw-semibold font-monospace">${e(d.PaxDetails)}</span></div>`;
                }

                // Build other / external participants list
                const otherList = parseOtherPax(d.PaxDetails_Other);
                let otherListHtml = '';
                if (otherList.length > 0) {
                    let otherRows = '';
                    otherList.forEach((oth, oIdx) => {
                        otherRows += `<tr>
                            <td class="text-center text-muted" style="width:35px;">${oIdx + 1}</td>
                            <td class="fw-semibold text-dark">${e(oth.name || '-')}</td>
                            <td style="width:130px;"><span class="badge bg-info-subtle text-info">${e(oth.category || 'External')}</span></td>
                            <td class="text-muted" style="font-size:11px;">${e(oth.details || '-')}</td>
                        </tr>`;
                    });
                    otherListHtml = `<div class="mt-2">
                        <div class="fw-semibold text-dark mb-1" style="font-size:11.5px;"><i class="ri-user-follow-line me-1 text-info"></i> Other / External Participants (${otherList.length})</div>
                        <div class="table-responsive">
                            <table class="table table-sm table-bordered bg-white mb-0 pax-sub-table">
                                <thead class="table-light">
                                    <tr class="text-muted" style="font-size:11px;">
                                        <th class="text-center" style="width:35px;">#</th>
                                        <th>Guest / Participant Name</th>
                                        <th>Category</th>
                                        <th>Details</th>
                                    </tr>
                                </thead>
                                <tbody>${otherRows}</tbody>
                            </table>
                        </div>
                    </div>`;
                }

                paxRowHtml = `<tr id="pax_row_${e(eid)}" class="pax-detail-row" style="display:none;background:#f8fafc;">
                    <td colspan="14" class="p-3 border-top-0">
                        <div class="pax-card p-3 rounded-3 border bg-white shadow-sm">
                            <div class="d-flex justify-content-between align-items-center mb-2 pb-2 border-bottom flex-wrap gap-2">
                                <div>
                                    <span class="fw-bold text-primary" style="font-size:13px;">
                                        <i class="ri-restaurant-line me-1 text-warning"></i> PAX & Participants Details (Exp #${e(eid)} - ${e(cn)})
                                    </span>
                                </div>
                                <div class="d-flex align-items-center gap-2">
                                    <span class="badge bg-primary-subtle text-primary border border-primary-subtle">Total PAX: <b>${paxCount}</b></span>
                                    ${d.PaxEmployeeDetails && d.PaxEmployeeDetails.length ? `<span class="badge bg-success-subtle text-success border border-success-subtle">Employees: <b>${d.PaxEmployeeDetails.length}</b></span>` : ''}
                                    ${otherList.length ? `<span class="badge bg-info-subtle text-info border border-info-subtle">Other: <b>${otherList.length}</b></span>` : ''}
                                    <button type="button" class="btn btn-sm btn-light py-0 px-2 toggle-pax-btn text-muted" data-target="pax_row_${e(eid)}" title="Close">
                                        <i class="ri-close-line"></i>
                                    </button>
                                </div>
                            </div>
                            ${empListHtml}
                            ${otherListHtml}
                        </div>
                    </td>
                </tr>`;
            }

            rows+=`<tr><td class="text-center">${ed?`<input type="checkbox" class="form-check-input apr-check" aria-label="Select claim ${e(eid)}">`:''}</td><td class="text-center">${i+1}</td>
            <td>
                <a href="#" class="fw-semibold view-claim" data-expid="${e(eid)}" data-empid="${e(empId)}" data-claim-id="${e(d.ClaimId||d.claimId||d.cgId||'')}" data-cgid="${e(d.ClaimId||d.claimId||d.cgId||'')}">${e(cn)}</a>
                <div class="text-muted" style="font-size:9px;">Exp #${e(eid)}</div>
                ${paxBadgeHtml}
            </td>
            <td class="text-center">${fd(bd)}</td>
            <td>${mediaHtml}</td>
            <td class="text-end claim-claimed-cell">₹${fa(ca)}</td>
            <td class="text-end claim-verified-cell">₹${fa(va)}</td>
            <td class="text-end ${diffClass}">${diffText}</td>
            <td class="remark-col" style="min-width:140px;max-width:240px;">${vRmkContent}</td>
            <td class="text-end">${ed?`<input type="number" class="form-control form-control-sm amt-input aa" value="${aa}" min="0">`:'₹'+fa(aa)}</td>
            <td>${ed?`<input type="text" class="form-control form-control-sm amt-input ar" value="${e(d.ApprTRemark||d.ApprRemark||'')}" placeholder="Remark" aria-label="Remark">`:e(d.ApprTRemark||d.ApprRemark||'-')}</td>
            <td class="remark-col" style="min-width:130px;max-width:220px;">${empRmkContent}</td>
            <td class="text-center claim-status-cell">${isA?'<span class="badge bg-success">Done</span>':'<span class="badge bg-warning text-dark">Pending</span>'}</td>
            <td class="text-center">${ed?`<button class="btn btn-sm ${isA?'btn-outline-success':'btn-primary'} rounded-pill px-3 approve-one" data-expid="${e(eid)}" data-empid="${e(empId)}" data-month="${e(cM)}" title="${isA?'Update the approved amount (until final submit)':'Approve'}"><i class="${isA?'ri-refresh-line':'ri-check-line'}"></i></button>`:''}</td></tr>` + paxRowHtml;
        });

        // Total diff in footer
        let diffTotalText = '-';
        let diffTotalClass = '';
        if (tV < tC) {
            diffTotalText = '-' + fa(tC - tV);
            diffTotalClass = 'text-danger fw-bold';
        } else if (tV > tC) {
            diffTotalText = '+' + fa(tV - tC);
            diffTotalClass = 'text-success fw-bold';
        }

        // LEGEND-01 (tracker #31): meaning of the colours used in this table
        const legendHtml = `<div class="d-flex flex-wrap gap-3 align-items-center mb-2 px-1" style="font-size:11px;">
            <strong>Colour legend:</strong>
            <span><span class="text-danger fw-bold">-₹</span> verifier deducted from the claim</span>
            <span><span class="text-success fw-bold">+₹</span> verifier added to the claim</span>
            <span><span class="d-inline-block align-middle me-1" style="width:12px;height:12px;background:#fef2f2;border-left:3px solid #ef4444;"></span>verifier remark on a deduction</span>
            <span><span class="badge bg-warning text-dark">Pending</span> not yet approved</span>
            <span><span class="badge bg-success">Done</span> approved</span>
        </div>`;
        // APPR-BULK-01: tick claims (or all) and approve them in one go at the amounts shown
        const bulkBar = ed ? `<div class="d-flex align-items-center gap-2 mb-2 px-1 apr-bulk-bar">
            <button type="button" class="btn btn-sm btn-success rounded-pill px-3 approve-selected" disabled><i class="ri-check-double-line me-1"></i>Approve selected (<span class="apr-sel-count">0</span>)</button>
            <small class="text-muted">Tick the claims, or the box in the header for all. Until the final Submit you can change an approved amount and approve it again.</small>
        </div>` : '';
        $('#dayModalBody').html(legendHtml + bulkBar + `<div class="table-responsive"><table class="table table-bordered table-hover day-tbl mb-0">
            <thead class="table-light"><tr class="text-center align-middle" style="font-size:11.5px;">
                <th style="width:32px;">${ed?'<input type="checkbox" class="form-check-input apr-check-all" title="Select all" aria-label="Select all claims">':''}</th>
                <th style="width:35px;">#</th>
                <th>Claim</th>
                <th style="width:85px;">Date</th>
                <th>Attachments</th>
                <th style="width:75px;">Claimed</th>
                <th style="width:75px;">Verified</th>
                <th style="width:70px;">Diff</th>
                <th style="min-width:140px;max-width:240px;">Verifier Remark</th>
                <th style="width:90px;">Appr. Amt</th>
                <th style="width:120px;">Appr. Remark</th>
                <th style="min-width:130px;max-width:220px;">Emp. Remark</th>
                <th style="width:65px;">Status</th>
                <th style="width:50px;">Action</th>
            </tr></thead>
            <tbody>${rows}</tbody>
            <tfoot class="table-light fw-bold"><tr>
                <td colspan="5" class="text-end">Total</td>
                <td class="text-end">₹${fa(tC)}</td>
                <td class="text-end">₹${fa(tV)}</td>
                <td class="text-end ${diffTotalClass}">${diffTotalText}</td>
                <td></td>
                <td class="text-end">₹${fa(tA)}</td>
                <td colspan="4"></td>
            </tr></tfoot>
        </table></div>`);

        // APPR-FILTER-01: column filters (full-screen view)
        if(window.xeasyApproverFilters){window.xeasyApproverFilters.attach('#dayModalBody');}
        // Mark this employee+month as reviewed for submit validation
        if(ed){
            const cardKey=cE+'_'+cM;
            reviewedEmployees[cardKey]={name:empName||'',claimTotal:tC,verifyTotal:tV,approveTotal:tA,unapproved:unapproved};
        }
    }
    // ═══ APPROVE ONE ═══
    // returns a promise: true when approved, false when refused (message shown unless quiet)
    function approveRow($r, quiet){
        const $b=$r.find('.approve-one'),eid=$b.data('expid'),empId=$b.data('empid'),mo=$b.data('month'),amt=n($r.find('.aa').val()),rmk=$r.find('.ar').val()||'';
        const fail=(t,m,focus)=>{if(!quiet){Swal.fire(t,m,'warning');}if(focus){$r.find(focus).focus();}$r.addClass('table-danger');return $.Deferred().resolve(false,'#'+eid+': '+m).promise();};
        if(amt<=0){return fail('Error','Enter valid amount.','.aa');}
        const vAmt=n($r.find('.claim-verified-cell').text());const cAmt=n($r.find('.claim-claimed-cell').text());const basis=vAmt>0?vAmt:cAmt;
        // APPR-CAP-01: the approver can keep or reduce, never increase (ceiling: verified, else claimed)
        if(basis>0&&amt>basis+0.009){return fail('Not allowed','The approved amount ('+amt.toFixed(2)+') cannot be more than the '+(vAmt>0?'verified':'claimed')+' amount ('+basis.toFixed(2)+').','.aa');}
        // VVNR-APPR-REM-01: a cut against the verified amount (or the claimed amount when not yet verified) needs a remark
        if(((vAmt>0&&amt!==vAmt)||(basis>0&&amt<basis-0.009)) && (!rmk.trim()||rmk.trim().toLowerCase()==='approved')){
            return fail('Remark Required','The approved amount ('+amt.toFixed(2)+') is '+(amt<basis?'less than':'different from')+' the '+(vAmt>0?'verified':'claimed')+' amount ('+basis.toFixed(2)+'). Please enter a remark.','.ar');
        }
        $r.removeClass('table-danger');
        $b.prop('disabled',true).html('<i class="ri-loader-4-line rotating"></i>');
        const d=$.Deferred();
        $.post(R.approveClaim,{_token:csrf,exp_id:eid,employee_id:empId,amount:amt,remark:rmk,month:mo}).done(res=>{
            if(res.Code=='300'||res.success){
                $r.find('.claim-status-cell').html('<span class="badge bg-success">Done</span>');
                $b.prop('disabled',false).removeClass('btn-primary').addClass('btn-outline-success').attr('title','Update the approved amount (until final submit)').html('<i class="ri-refresh-line"></i>');
                $r.find('.apr-check').prop('checked',false);
                const cardKey=empId+'_'+mo;
                if(reviewedEmployees[cardKey]){
                    reviewedEmployees[cardKey].unapproved=reviewedEmployees[cardKey].unapproved.filter(x=>x!=='#'+eid);
                    reviewedEmployees[cardKey].approveTotal=n(res.TotalApprovedAmt)||reviewedEmployees[cardKey].approveTotal;
                }
                d.resolve(true);
            }else{if(!quiet){Swal.fire('Failed',res.msg||'Error','error');}$b.prop('disabled',false).html('<i class="ri-check-line"></i>');$r.addClass('table-danger');d.resolve(false,'#'+eid+': '+(res.msg||'Error'));}
        }).fail(x=>{const j=x.responseJSON||{};if(j.remark_required){$r.find('.ar').focus();}if(!quiet){Swal.fire(j.remark_required?'Remark Required':'Error',j.msg||j.message||'Network error',j.remark_required?'warning':'error');}$b.prop('disabled',false).html('<i class="ri-check-line"></i>');$r.addClass('table-danger');d.resolve(false,'#'+eid+': '+(j.msg||j.message||'Network error'));});
        return d.promise();
    }
    $(document).on('click','.approve-one',function(){
        approveRow($(this).closest('tr'),false).done(ok=>{if(ok){Swal.fire({icon:'success',title:'Approved!',timer:1000,showConfirmButton:false});}});
    });
    // ═══ APPROVE SELECTED (APPR-BULK-01) ═══
    function aprSelCount(){const c=$('#dayModalBody .apr-check:checked').length;$('#dayModalBody .apr-sel-count').text(c);$('#dayModalBody .approve-selected').prop('disabled',c===0);
        const all=$('#dayModalBody tr:visible .apr-check').length;$('#dayModalBody .apr-check-all').prop('checked',all>0&&c===all).prop('indeterminate',c>0&&c<all);}
    $(document).on('change','#dayModalBody .apr-check-all',function(){$('#dayModalBody .apr-check').prop('checked',false);$('#dayModalBody tr:visible .apr-check').prop('checked',this.checked);aprSelCount();});
    $(document).on('change','#dayModalBody .apr-check',aprSelCount);
    $(document).on('click','#dayModalBody .approve-selected',function(){
        const rows=$('#dayModalBody .apr-check:checked').closest('tr').toArray();
        if(!rows.length){return;}
        const $btn=$(this).prop('disabled',true);let ok=0;const bad=[];
        (async()=>{for(const tr of rows){const [res,msg]=await new Promise(r=>approveRow($(tr),true).done((a,b)=>r([a,b])));if(res){ok++;}else{bad.push(msg);}}
            aprSelCount();
            Swal.fire({icon:bad.length?'warning':'success',title:ok+' of '+rows.length+' approved',html:bad.length?'<div class="text-start" style="font-size:12px;"><p>Not approved (marked red):</p><ul>'+bad.map(m=>'<li>'+e(m)+'</li>').join('')+'</ul></div>':''});
        })();
    });
    // ═══ SUBMIT MONTH ═══
    // Track which employees have been reviewed (viewed day-wise details)
    var reviewedEmployees = {};

    $(document).on('click', '.disabled-submit-btn', function(ev){
        ev.preventDefault();
        const reason = $(this).data('reason') || 'Claims are not verified';
        Swal.fire({
            icon: 'warning',
            title: 'Cannot Submit',
            html: `<p>This month cannot be submitted because <strong>${e(reason)}</strong>.</p><p class="text-muted" style="font-size:12px;">Only claims verified by the verifier can be approved and submitted.</p>`,
            confirmButtonText: 'OK',
            confirmButtonColor: '#405189'
        });
    });

    $(document).on('click','.submit-btn',function(ev){ev.preventDefault();
        const id=$(this).data('empid'),mo=$(this).data('month'),yr=$(this).data('yearid'),$b=$(this);
        const cardKey = id + '_' + mo;

        // Condition 0: Must be verified before submitting
        const isVerified = $(this).data('verified') == 1 || $(this).data('verified') === true;
        if (!isVerified) {
            Swal.fire({
                icon: 'warning',
                title: 'Verification Required',
                html: '<p>This claim month cannot be submitted because it is <strong>not yet verified</strong> by the verifier.</p><p class="text-muted" style="font-size:12px;">Only verified claims can be approved and submitted.</p>',
                confirmButtonText: 'OK',
                confirmButtonColor: '#405189'
            });
            return;
        }

        // Condition 1: Must review (view day-wise) before submitting
        if (!reviewedEmployees[cardKey]) {
            Swal.fire({
                icon: 'warning',
                title: 'Review Required',
                html: '<p>Please <strong>View</strong> and review the day-wise claims before submitting.</p><p class="text-muted" style="font-size:12px;">Click the "View" button first to review all claims.</p>',
                confirmButtonText: 'OK',
                confirmButtonColor: '#405189'
            });
            return;
        }

        // Condition 2 (VNR only): Check if all claims are approved
        const unapproved = reviewedEmployees[cardKey].unapproved || [];
        if (prefix === 'vnr' && unapproved.length > 0) {
            Swal.fire({
                icon: 'error',
                title: 'Unapproved Claims',
                html: `<p>Please approve the following claims before final submit:</p><p class="text-danger fw-bold">${unapproved.join(', ')}</p>`,
                confirmButtonText: 'OK',
                confirmButtonColor: '#405189'
            });
            return;
        }

        // Show submit confirmation with summary
        const info = reviewedEmployees[cardKey];
        Swal.fire({
            title: 'Confirm Submit',
            html: `<div class="text-start" style="font-size:14px;">
                <table class="table table-sm table-borderless mb-0">
                    <tr><td class="fw-bold">Employee:</td><td>${e(info.name||'')}</td></tr>
                    <tr><td class="fw-bold">Month:</td><td>${M[+mo]||mo}</td></tr>
                    <tr><td class="fw-bold">Claimant Amt:</td><td>₹${fa(info.claimTotal||0)}</td></tr>
                    <tr><td class="fw-bold">Verified Amt:</td><td>₹${fa(info.verifyTotal||0)}</td></tr>
                    <tr><td class="fw-bold">Approved Amt:</td><td class="text-success fw-bold">₹${fa(info.approveTotal||0)}</td></tr>
                </table>
            </div>`,
            icon: 'question',
            showCancelButton: true,
            confirmButtonText: '<i class="ri-check-double-line"></i> Submit',
            confirmButtonColor: '#0ab39c',
            cancelButtonText: 'Cancel'
        }).then(r=>{if(!r.isConfirmed)return;$b.html('<i class="ri-loader-4-line rotating"></i>');
        $.post(R.submitClaim,{_token:csrf,emp_id:id,month:mo,year_id:yr}).done(res=>{if(res.Code=='300'||res.success){Swal.fire({icon:'success',title:'Submitted!',timer:1200,showConfirmButton:false});loadPending();}else{Swal.fire('Failed',res.msg||'Error','error');$b.html('<i class="ri-check-double-line"></i> Submit');}}).fail(()=>{Swal.fire('Error','Network error','error');$b.html('<i class="ri-check-double-line"></i> Submit');});});
    });
    // ═══ ATTENDANCE ═══
    $(document).on('click','.att-btn',function(ev){ev.preventDefault();window.open('https://vnrseeds.co.in/public/api/Att_records?empid='+$(this).data('empid')+'&Month='+$(this).data('month')+'&Year='+new Date().getFullYear(),'_blank','width=900,height=500');});

    // ═══ APPROVED ═══
    let allApprovedList = [];
    let approvedCurrentPage = 1;
    let approvedPageSize = 10;

    function loadApproved(mem, mo){
        $('#approvedCards').html('<div class="text-center py-5"><div class="spinner-border spinner-border-sm text-primary"></div> <span class="text-muted ms-2">Loading approved claims...</span></div>');
        $('#approvedPagination').hide();
        $.getJSON(R.approvedList, {member: mem || '', month: mo || ''}).done(res => {
            allApprovedList = res.Approved_list || [];
            approvedCurrentPage = 1;
            renderApprovedCards();
        }).fail(() => {
            $('#approvedCount').text('0');
            $('#approvedCards').html('<div class="empty-state text-danger"><i class="ri-error-warning-line"></i><p>Failed to load approved claims</p></div>');
            $('#approvedPagination').hide();
        });
    }

    function renderApprovedCards(){
        const query = ($('#searchApproved').val() || '').trim().toLowerCase();
        let filtered = allApprovedList;
        if(query){
            filtered = allApprovedList.filter(item => {
                const nm = (eName(item) || '').toLowerCase();
                const id = String(item.EmployeeID || '').toLowerCase();
                const code = String(item.EmpCode || item.EmployeeCode || '').toLowerCase();
                const moNum = String(item.Month || '');
                const moName = String(M[+moNum] || '').toLowerCase();
                const ca = String(item.Claim_Amount || '');
                const va = String(item.Verified_Amount || '');
                const aa = String(item.Approved_Amount || '');
                const fi = String(item.Finance_Amount || '');
                return nm.includes(query) || id.includes(query) || code.includes(query) ||
                       moNum.includes(query) || moName.includes(query) ||
                       ca.includes(query) || va.includes(query) || aa.includes(query) || fi.includes(query);
            });
        }

        if(query){
            $('#approvedCount').text(filtered.length + ' of ' + allApprovedList.length);
        } else {
            $('#approvedCount').text(allApprovedList.length);
        }

        if(!filtered.length){
            const msg = allApprovedList.length ? 'No claims matching "' + e(query) + '"' : 'No approved claims found';
            $('#approvedCards').html(`<div class="empty-state"><i class="ri-inbox-line"></i><p>${msg}</p></div>`);
            $('#approvedPagination').hide();
            return;
        }

        const total = filtered.length;
        const pageSizeNum = approvedPageSize === 'all' ? total : parseInt(approvedPageSize, 10);
        const totalPages = approvedPageSize === 'all' ? 1 : Math.max(1, Math.ceil(total / pageSizeNum));

        if(approvedCurrentPage > totalPages) approvedCurrentPage = totalPages;
        if(approvedCurrentPage < 1) approvedCurrentPage = 1;

        const startIdx = approvedPageSize === 'all' ? 0 : (approvedCurrentPage - 1) * pageSizeNum;
        const endIdx = approvedPageSize === 'all' ? total : Math.min(startIdx + pageSizeNum, total);
        const pagedItems = filtered.slice(startIdx, endIdx);

        let h = '';
        pagedItems.forEach(item => {
            const id = item.EmployeeID || '', nm = eName(item), mo2 = item.Month || '', yr = item.YearId || '';
            const ca = n(item.Claim_Amount || 0), va = n(item.Verified_Amount || 0), aa = n(item.Approved_Amount || 0), fi = n(item.Finance_Amount || 0);
            const diff = ca - va;
            const cour = item.RecevingDate || item.ReceivingDate || '';
            h += `<div class="card claim-card"><div class="stripe success"></div><div class="card-body">
            <div class="d-flex justify-content-between align-items-start">
                <div><div class="emp-name" style="color:var(--vz-success)">${e(nm)}</div><div class="meta"><i class="ri-calendar-line"></i> ${e(M[+mo2] || mo2)} &nbsp;|&nbsp; Courier: ${vd(cour) ? fd(cour) : '<span class="text-warning">Pending</span>'}</div></div>
                <div class="text-end"><div class="amount success">₹${fa(aa)}</div><div style="font-size:11px;color:#888">Finance: ₹${fa(fi)}</div></div>
            </div>
            <div class="d-flex flex-wrap gap-2 mt-2 align-items-center">
                <span class="badge bg-light text-dark border">Claimed: ₹${fa(ca)}</span>
                <span class="badge bg-info-subtle text-info">Verified: ₹${fa(va)}</span>
                ${diff > 0 ? `<span class="badge bg-danger-subtle text-danger">Diff: ₹${fa(diff)}</span>` : ''}
                <span class="badge bg-success-subtle text-success">Approved: ₹${fa(aa)}</span>
                <a class="pill-btn view view-btn ms-auto" data-empid="${e(id)}" data-month="${e(mo2)}" data-yearid="${e(yr)}" data-name="${e(nm)}" data-mode="approved"><i class="ri-eye-line"></i> View</a>
            </div></div></div>`;
        });
        $('#approvedCards').html(h);

        // Build pagination controls
        let pagesHtml = '';
        if(totalPages <= 7){
            for(let p = 1; p <= totalPages; p++){
                pagesHtml += `<li class="page-item ${p === approvedCurrentPage ? 'active' : ''}"><a class="page-link approved-page-btn" href="#" data-page="${p}">${p}</a></li>`;
            }
        } else {
            let pageArr = [];
            if(approvedCurrentPage <= 4){
                pageArr = [1, 2, 3, 4, 5, '...', totalPages];
            } else if(approvedCurrentPage >= totalPages - 3){
                pageArr = [1, '...', totalPages - 4, totalPages - 3, totalPages - 2, totalPages - 1, totalPages];
            } else {
                pageArr = [1, '...', approvedCurrentPage - 1, approvedCurrentPage, approvedCurrentPage + 1, '...', totalPages];
            }
            pageArr.forEach(p => {
                if(p === '...'){
                    pagesHtml += `<li class="page-item disabled"><span class="page-link">...</span></li>`;
                } else {
                    pagesHtml += `<li class="page-item ${p === approvedCurrentPage ? 'active' : ''}"><a class="page-link approved-page-btn" href="#" data-page="${p}">${p}</a></li>`;
                }
            });
        }

        const paginationHtml = `
            <div class="d-flex align-items-center gap-2 text-muted" style="font-size:13px;">
                <span>Showing <b>${startIdx + 1}</b> to <b>${endIdx}</b> of <b>${total}</b> claims</span>
                <div class="d-flex align-items-center ms-2">
                    <span class="me-1">Per page:</span>
                    <select id="approvedPageSizeSelect" class="form-select form-select-sm" style="width: auto; height: 30px; font-size: 12px; padding: 2px 24px 2px 8px;">
                        <option value="10" ${approvedPageSize == 10 ? 'selected' : ''}>10</option>
                        <option value="25" ${approvedPageSize == 25 ? 'selected' : ''}>25</option>
                        <option value="50" ${approvedPageSize == 50 ? 'selected' : ''}>50</option>
                        <option value="100" ${approvedPageSize == 100 ? 'selected' : ''}>100</option>
                        <option value="all" ${approvedPageSize === 'all' ? 'selected' : ''}>All</option>
                    </select>
                </div>
            </div>
            <div>
                <ul class="pagination pagination-sm mb-0">
                    <li class="page-item ${approvedCurrentPage === 1 ? 'disabled' : ''}">
                        <a class="page-link approved-page-btn" href="#" data-page="${approvedCurrentPage - 1}"><i class="ri-arrow-left-s-line"></i></a>
                    </li>
                    ${pagesHtml}
                    <li class="page-item ${approvedCurrentPage === totalPages ? 'disabled' : ''}">
                        <a class="page-link approved-page-btn" href="#" data-page="${approvedCurrentPage + 1}"><i class="ri-arrow-right-s-line"></i></a>
                    </li>
                </ul>
            </div>
        `;
        $('#approvedPagination').html(paginationHtml).show();
    }

    $(document).on('change', '#approvedPageSizeSelect', function(){
        const val = $(this).val();
        approvedPageSize = val === 'all' ? 'all' : parseInt(val, 10);
        approvedCurrentPage = 1;
        renderApprovedCards();
    });

    $(document).on('click', '.approved-page-btn', function(e){
        e.preventDefault();
        const p = $(this).data('page');
        if(p && p !== approvedCurrentPage && p >= 1){
            approvedCurrentPage = p;
            renderApprovedCards();
            const el = document.getElementById('approvedCards');
            if(el) el.scrollIntoView({ behavior: 'smooth', block: 'start' });
        }
    });
    function loadMembers(){if(!R.teamMembers)return;$.getJSON(R.teamMembers).done(res=>{(res.Team_list||[]).forEach(m=>{$('#filterMember').append(`<option value="${e(m.EmployeeID||'')}">${e((m.EmpCode||'')+' - '+(m.FullName||m.Ename||''))}</option>`);});});}

    // ═══ TEAM EXP ═══
    function loadTeamExp(){if(!R.teamExpenses)return;$('#teamExpBody').html('<div class="text-center py-5"><div class="spinner-border text-primary"></div></div>');
        $.getJSON(R.teamExpenses).done(res=>{const et=res.data?.ExpenseType||res.ExpenseType||{};const claims=et.ClaimTypes||[];if(!claims.length){$('#teamExpBody').html('<div class="empty-state"><i class="ri-inbox-line"></i><p>No data</p></div>');return;}
        const fm=[4,5,6,7,8,9,10,11,12,1,2,3],mh=['Apr','May','Jun','Jul','Aug','Sep','Oct','Nov','Dec','Jan','Feb','Mar'];let tots={},ov=0,rows='';
        // Show Only Expense Heads Actually Claimed by the Team (Point C)
        const claimedTypes = claims.filter(c => {
            const m = c.MonthlyTotals || c.months || {};
            const total = n(c.MonthTotal || c.Total || 0);
            if (total > 0) return true;
            return fm.some(mon => n(m[mon] || m[String(mon)] || 0) > 0);
        });
        if (!claimedTypes.length) {
            $('#teamExpBody').html('<div class="empty-state text-muted py-5 text-center"><i class="ri-inbox-line fs-24 d-block mb-2"></i><p class="mb-0">No expense claims submitted by team members</p></div>');
            return;
        }
        claimedTypes.forEach(c=>{const m=c.MonthlyTotals||c.months||{};let rt=0,cells='';fm.forEach((mon,i)=>{const v=n(m[mon]||m[String(mon)]||0);tots[i]=(tots[i]||0)+v;rt+=v;cells+=`<td class="text-end">${v>0?'₹'+fa(v):'-'}</td>`;});ov+=rt;rows+=`<tr><td class="fw-semibold">${e(c.ClaimName||'Claim')}</td>${cells}<td class="text-end fw-bold">₹${fa(rt||c.MonthTotal||c.Total||0)}</td></tr>`;});
        let foot='<td class="fw-bold">Total</td>';fm.forEach((_,i)=>{foot+=`<td class="text-end fw-bold">₹${fa(tots[i]||0)}</td>`;});foot+=`<td class="text-end fw-bold">₹${fa(ov)}</td>`;
        $('#teamExpBody').html(`<div class="table-responsive"><table class="table table-bordered table-sm"><thead class="table-light"><tr><th>Claim Type</th>${mh.map(h=>'<th class="text-center">'+h+'</th>').join('')}<th class="text-center">Total</th></tr></thead><tbody>${rows}</tbody><tfoot class="table-light"><tr>${foot}</tr></tfoot></table></div>`);
        }).fail(()=>$('#teamExpBody').html('<p class="text-danger text-center">Failed to load.</p>'));
    }
    // ═══ RUNNING KM ═══
    function loadRunKm(){$('#runKmBody').html('<div class="text-center py-5"><div class="spinner-border text-primary"></div></div>');
        $.getJSON(R.teamRunningKm).done(res=>{const data=res.data||[];if(!data.length){$('#runKmBody').html('<div class="empty-state"><i class="ri-roadster-line"></i><p>No running KM data</p></div>');return;}
        const fm=[4,5,6,7,8,9,10,11,12,1,2,3],mh=['Apr','May','Jun','Jul','Aug','Sep','Oct','Nov','Dec','Jan','Feb','Mar'];let tots={},ov=0,rows='';
        data.forEach(emp=>{const m=emp.months||{};let rt=0,cells='';fm.forEach((mon,i)=>{const v=n(m[mon]||m[String(mon)]||0);tots[i]=(tots[i]||0)+v;rt+=v;cells+=`<td class="text-end">${v>0?v.toLocaleString('en-IN'):'-'}</td>`;});ov+=rt;rows+=`<tr><td class="fw-semibold">${e(emp.employee_name||'')}</td>${cells}<td class="text-end fw-bold">${rt.toLocaleString('en-IN')} km</td></tr>`;});
        let foot='<td class="fw-bold">Total</td>';fm.forEach((_,i)=>{foot+=`<td class="text-end fw-bold">${(tots[i]||0).toLocaleString('en-IN')}</td>`;});foot+=`<td class="text-end fw-bold">${ov.toLocaleString('en-IN')} km</td>`;
        $('#runKmBody').html(`<div class="table-responsive"><table class="table table-bordered table-sm"><thead class="table-light"><tr><th>Employee</th>${mh.map(h=>'<th class="text-center">'+h+'</th>').join('')}<th class="text-center">Total KM</th></tr></thead><tbody>${rows}</tbody><tfoot class="table-light"><tr>${foot}</tr></tfoot></table></div>`);
        }).fail(()=>$('#runKmBody').html('<p class="text-danger text-center">Failed to load.</p>'));
    }
    // ═══ PAX TOGGLE ═══
    $(document).on('click', '.toggle-pax-btn', function(ev){
        ev.preventDefault();
        ev.stopPropagation();
        const targetId = $(this).data('target');
        const $row = $('#' + targetId);
        const $chevron = $(this).find('.pax-chevron');
        $row.toggle();
        if($row.is(':visible')){
            $chevron.removeClass('ri-arrow-down-s-line').addClass('ri-arrow-up-s-line');
        }else{
            $chevron.removeClass('ri-arrow-up-s-line').addClass('ri-arrow-down-s-line');
        }
    });

    function parseOtherPax(raw){
        if(!raw) return [];
        if(typeof raw === 'object' && Array.isArray(raw)) return raw;
        if(typeof raw !== 'string') return [];
        const trimmed = raw.trim();
        if(!trimmed) return [];
        try{
            const parsed = JSON.parse(trimmed);
            if(Array.isArray(parsed)) return parsed;
            if(typeof parsed === 'object') return [parsed];
        }catch(e){
            try{
                let rep = trimmed;
                if(!rep.endsWith(']')){
                    if(rep.lastIndexOf('}') !== -1){
                        rep = rep.substring(0, rep.lastIndexOf('}') + 1) + ']';
                        const p = JSON.parse(rep);
                        if(Array.isArray(p)) return p;
                    }
                }
            }catch(e2){}
            return [{name: trimmed, category: 'External', details: ''}];
        }
        return [];
    }

    // ═══ HELPERS ═══
    function eName(i){const nm=i.EmpName;return(nm&&typeof nm==='object')?(nm.Ename||nm.ename||'N/A'):(nm||i.Ename||i.FullName||'N/A');}
    function n(v){return parseFloat(String(v||0).replace(/[^0-9.-]/g,''))||0;}
    function fa(v){return n(v).toLocaleString('en-IN',{maximumFractionDigits:0});}
    function vd(v){return v&&v!=='0000-00-00'&&v!=='null'&&v!==''&&v!=='1970-01-01'&&v!=='1970-01-01 00:00:00';}
    function fd(v){if(!vd(v))return'-';try{const d=new Date(String(v).replace(' ','T'));return isNaN(d.getTime())?'-':d.toLocaleDateString('en-GB');}catch(e){return'-';}}
    // Try multiple possible field names for a date value from API
    function fld(item,...keys){for(const k of keys){if(item[k]&&vd(item[k]))return item[k];}return '';}
    function e(v){return String(v??'').replace(/[&<>"']/g,m=>({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#039;'})[m]);}
});
</script>
@endpush
