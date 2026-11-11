{{-- Advance Report Specific Extra Filters --}}
@can('user_filter')
    <div class="col-md-6 mb-2">
        <label class="form-label small text-muted fw-bold mb-1">Select Employee</label>
        <select class="form-select filter-select" id="userSelect" multiple data-placeholder="Select Employee">
            <option value="" disabled>Select Employee</option>
            @foreach ($employees ?? [] as $employee)
                <option value="{{ $employee->EmployeeID }}"
                        data-status="{{ $employee->EmpStatus }}"
                        class="{{ $employee->EmpStatus === 'D' ? 'deactivated' : '' }}">
                    {{ $employee->EmpCode }} - {{ $employee->Fname }} {{ $employee->Sname ?? '' }} {{ $employee->Lname }}
                </option>
            @endforeach
        </select>
    </div>
@endcan

<div class="col-md-6 mb-2">
    <label class="form-label small text-muted fw-bold mb-1">Select Activity Type</label>
    <select class="form-select filter-select" id="activityTypeSelect" multiple data-placeholder="Select Activity Type">
        <option value="" disabled>Select Activity Type</option>
        @foreach ($activityTypes ?? [] as $type)
            <option value="{{ $type->id }}">{{ $type->type_name }}</option>
        @endforeach
    </select>
</div>

<div class="col-md-6 mb-2">
    <label class="form-label small text-muted fw-bold mb-1">Select Activity Name</label>
    <select class="form-select filter-select" id="activityNameSelect" multiple data-placeholder="Select Activity Name">
        <option value="" disabled>Select Activity Name</option>
        @foreach ($activityNames ?? [] as $name)
            <option value="{{ $name->id }}">{{ $name->ClaimName }}</option>
        @endforeach
    </select>
</div>

<div class="col-md-12 mb-2">
    <label class="form-label small text-muted fw-bold mb-1">Auto Advance</label>
    <div class="d-flex gap-3">
        <div class="form-check">
            <input class="form-check-input" type="radio" name="autoAdvanceFilter" id="autoAll" value="all" checked>
            <label class="form-check-label" for="autoAll">All</label>
        </div>
        <div class="form-check">
            <input class="form-check-input" type="radio" name="autoAdvanceFilter" id="autoOnly" value="auto">
            <label class="form-check-label" for="autoOnly">Auto Only</label>
        </div>
        <div class="form-check">
            <input class="form-check-input" type="radio" name="autoAdvanceFilter" id="manualOnly" value="manual">
            <label class="form-check-label" for="manualOnly">Manual Only</label>
        </div>
    </div>
</div>

{{-- Submit Date Range --}}
<div class="col-md-12 mb-1">
    <label class="form-label small text-muted fw-bold mb-1">Submit Date Range</label>
</div>
<div class="col-md-6 mb-2">
    <label class="form-label text-muted small mb-1">From</label>
    <input type="date" class="form-control" id="submitDateFrom" placeholder="From" aria-label="From">
</div>
<div class="col-md-6 mb-2">
    <label class="form-label text-muted small mb-1">To</label>
    <input type="date" class="form-control" id="submitDateTo" placeholder="To" aria-label="To">
</div>

{{-- Approve Date Range --}}
<div class="col-md-12 mb-1">
    <label class="form-label small text-muted fw-bold mb-1">Approve Date Range</label>
</div>
<div class="col-md-6 mb-2">
    <label class="form-label text-muted small mb-1">From</label>
    <input type="date" class="form-control" id="approveDateFrom" placeholder="From" aria-label="From">
</div>
<div class="col-md-6 mb-2">
    <label class="form-label text-muted small mb-1">To</label>
    <input type="date" class="form-control" id="approveDateTo" placeholder="To" aria-label="To">
</div>
