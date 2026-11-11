<div class="card-header align-items-center d-flex" id="dataPunchSection">

    {{-- Main Title with Remix Icon --}}
    <h4 class="card-title mb-0 me-4 d-flex align-items-center">
        <i class="ri-file-list-2-line me-1" style="font-size: 20px;margin-top: 2px;"></i> Approval View
    </h4>

    {{-- Prefix Switch --}}
    <div class="me-3 d-flex align-items-center">

        <div class="form-check form-check-inline me-2">
            <input type="radio"
                id="switchVnr"
                class="form-check-input"
                name="prefixSwitch"
                value="vnr"
                {{ $prefix === 'vnr' ? 'checked' : '' }}>
            <label for="switchVnr" class="form-check-label" style="cursor:pointer;">VNR</label>
        </div>

        @if(session('reporting_vess') == 'Y')
        <div class="form-check form-check-inline me-2">
            <input type="radio"
                id="switchVvnr"
                class="form-check-input"
                name="prefixSwitch"
                value="vvnr"
                {{ $prefix === 'vvnr' ? 'checked' : '' }}>
            <label for="switchVvnr" class="form-check-label" style="cursor:pointer;">VVNR</label>
        </div>
        @endif

        @if(session('reporting_nursery') == 'Y')
        <div class="form-check form-check-inline me-2">
            <input type="radio"
                id="switchNursery"
                class="form-check-input"
                name="prefixSwitch"
                value="nursery"
                {{ $prefix === 'nursery' ? 'checked' : '' }}>
            <label for="switchNursery" class="form-check-label" style="cursor:pointer;">NURSERY</label>
        </div>
        @endif

    </div>

    {{-- Status Buttons --}}
    <div class="status-buttons ms-auto">
        <a href="{{ url($prefix . '/approver?status=pending') }}"
            class="status-btn draft {{ $status === 'pending' ? 'active' : '' }}">
            <i class="ri-edit-circle-line"></i>
            <span>Pending</span>
        </a>

        <a href="{{ url($prefix . '/approver?status=approved') }}"
            class="status-btn filled {{ $status === 'approved' ? 'active' : '' }}">
            <i class="ri-checkbox-circle-line"></i>
            <span>Approved</span>
        </a>
    </div>

</div>