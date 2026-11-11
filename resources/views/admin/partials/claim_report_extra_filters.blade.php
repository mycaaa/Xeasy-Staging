{{-- Claim Report Specific Extra Filters --}}
@can('user_filter')
    <div class="col-md-6 mb-2">
        <label class="form-label small text-muted fw-bold mb-1">Select Employee</label>
        <select class="form-select filter-select" id="userSelect" multiple data-placeholder="Select Employee">
            @if(isset($guestUsers) && count($guestUsers) > 0)
                <optgroup label="Guest Users">
                    @foreach ($guestUsers as $guest)
                        <option value="{{ $guest->id }}" data-status="Guest">
                            {{ $guest->employee_id ? $guest->employee_id . ' - ' : '' }}{{ $guest->name }} (Guest)
                        </option>
                    @endforeach
                </optgroup>
            @endif
            <optgroup label="Employees">
                @foreach ($employees ?? [] as $employee)
                    <option value="{{ $employee->EmployeeID }}"
                        data-status="{{ $employee->EmpStatus }}"
                        class="{{ $employee->EmpStatus === 'D' ? 'deactivated' : '' }}">
                        {{ $employee->EmpCode }} - {{ $employee->Fname }}
                        {{ $employee->Sname ?? '' }} {{ $employee->Lname }}
                    </option>
                @endforeach
            </optgroup>
        </select>
    </div>
@endcan

<div class="col-md-6 mb-2">
    <label class="form-label small text-muted fw-bold mb-1">Select Filed By</label>
    <select class="form-select filter-select" id="filledBySelect" multiple data-placeholder="Select Filed By">
        @if(isset($guestUsers) && count($guestUsers) > 0)
            <optgroup label="Guest Users">
                @foreach ($guestUsers as $guest)
                    <option value="{{ $guest->id }}" data-status="Guest">
                        {{ $guest->employee_id ? $guest->employee_id . ' - ' : '' }}{{ $guest->name }} (Guest)
                    </option>
                @endforeach
            </optgroup>
        @endif
        <optgroup label="Employees">
            @foreach ($employees ?? [] as $employee)
                <option value="{{ $employee->EmployeeID }}"
                    data-status="{{ $employee->EmpStatus }}"
                    class="{{ $employee->EmpStatus === 'D' ? 'deactivated' : '' }}">
                    {{ $employee->EmpCode }} - {{ $employee->Fname }}
                    {{ $employee->Sname ?? '' }} {{ $employee->Lname }}
                </option>
            @endforeach
        </optgroup>
    </select>
</div>

@can('month_filter')
    <div class="col-md-6 mb-2">
        <label class="form-label small text-muted fw-bold mb-1">Select Month</label>
        <select class="form-select filter-select" id="monthSelect" multiple data-placeholder="Select Month">
            <option value="1">January</option>
            <option value="2">February</option>
            <option value="3">March</option>
            <option value="4">April</option>
            <option value="5">May</option>
            <option value="6">June</option>
            <option value="7">July</option>
            <option value="8">August</option>
            <option value="9">September</option>
            <option value="10">October</option>
            <option value="11">November</option>
            <option value="12">December</option>
        </select>
    </div>
@endcan

@can('claim_type_filter')
    @php $groupedClaimTypes = isset($claimTypes) ? $claimTypes->groupBy('cgName') : collect([]); @endphp
    <div class="col-md-6 mb-2">
        <label class="form-label small text-muted fw-bold mb-1">Select Claim Type</label>
        <select class="form-select filter-select" id="claimTypeSelect" multiple data-placeholder="Select Claim Type">
            @foreach ($groupedClaimTypes as $groupName => $claims)
                <optgroup label="{{ $groupName ?? 'Ungrouped' }}">
                    @foreach ($claims as $claim)
                        <option value="{{ $claim->ClaimId }}"
                            data-group="{{ $groupName ?? 'Ungrouped' }}">
                            {{ $claim->ClaimName }}
                        </option>
                    @endforeach
                </optgroup>
            @endforeach
        </select>
    </div>
@endcan

@can('claim_status_filter')
    <div class="col-md-6 mb-2">
        <label class="form-label small text-muted fw-bold mb-1">Select Claim Status</label>
        <select class="form-select filter-select" id="claimStatusSelect" multiple data-placeholder="Select Claim Status">
            <option value="Submitted">Submitted</option>
            <option value="Filled">Filled</option>
            <option value="Verified">Verified</option>
            <option value="Approved">Approved</option>
            <option value="Financed">Financed</option>
        </select>
    </div>
@endcan

@can('wheeler_type_filter')
    <div class="col-md-6 mb-2">
        <label class="form-label small text-muted fw-bold mb-1">Select Wheeler Type</label>
        <select class="form-select filter-select" id="wheelerTypeSelect" multiple data-placeholder="Select Wheeler Type">
            <option value="2">2 Wheeler</option>
            <option value="4">4 Wheeler</option>
        </select>
    </div>
@endcan

@can('vehicle_type_filter')
    <div class="col-md-6 mb-2">
        <label class="form-label small text-muted fw-bold mb-1">Select Vehicle Type</label>
        <select class="form-select filter-select" id="vehicleTypeSelect" multiple data-placeholder="Select Vehicle Type">
            <option value="new">New</option>
            <option value="old">Old</option>
        </select>
    </div>
@endcan

<div class="col-md-12 mt-2 mb-1">
    @can('deactivate_claims_filter')
        <div class="form-check mb-2">
            <input class="form-check-input" type="radio" name="claim_filter_type"
                id="deactivatedClaims" value="deactivated_claims" />
            <label class="form-check-label" for="deactivatedClaims">
                Deactivate Claims
            </label>
        </div>
    @endcan
    @can('deactivate_after_filling_filter')
        <div class="form-check mb-2">
            <input class="form-check-input" type="radio" name="claim_filter_type"
                id="deactivateAfterFilling" value="deactivate_after_filling" />
            <label class="form-check-label" for="deactivateAfterFilling">
                Deactivate After Filling
            </label>
        </div>
    @endcan
    @can('backdate_odometer_submission_filter')
        <div class="form-check mb-2">
            <input class="form-check-input" type="radio" name="claim_filter_type"
                id="backdate_odometer_submission"
                value="backdate_odometer_submission" />
            <label class="form-check-label" for="backdate_odometer_submission">
                Backdate Odometer Submission
            </label>
        </div>
    @endcan
    @can('expense_sunday_holiday_filter')
        <div class="form-check mb-2">
            <input class="form-check-input" type="radio" name="claim_filter_type"
                id="expense_sunday_holiday" value="expense_sunday_holiday" />
            <label class="form-check-label" for="expense_sunday_holiday">
                Expense (Sunday & Holiday)
            </label>
        </div>
    @endcan
    @can('bill_date_exception_filter')
        <div class="form-check mb-2">
            <input class="form-check-input" type="radio" name="claim_filter_type"
                id="bill_date_exception" value="bill_date_exception" />
            <label class="form-check-label" for="bill_date_exception">
                Bill Date Exception
            </label>
        </div>
    @endcan
    @can('pending_for_punching_filter')
        <div class="form-check mb-2">
            <input class="form-check-input" type="radio" name="claim_filter_type"
                id="pending_for_punching" value="pending_for_punching" />
            <label class="form-check-label" for="pending_for_punching">
                Pending for Punching
            </label>
        </div>
    @endcan
    @can('access_running_filter')
        <div class="form-check mb-2">
            <input class="form-check-input" type="radio" name="claim_filter_type"
                id="access_running" value="access_running" />
            <label class="form-check-label" for="access_running">
                Excess Running KM
            </label>
        </div>
    @endcan
    <div class="form-check mb-2">
        <input class="form-check-input" type="radio" name="claim_filter_type"
            id="opened_list_only_employee" value="opened_list_only_employee" />
        <label class="form-check-label" for="opened_list_only_employee">
            Opened List Only Employee
        </label>
    </div>
</div>
