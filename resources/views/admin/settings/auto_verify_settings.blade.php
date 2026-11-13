@extends('layouts.app')

@section('title', 'Auto-Verification Rules & Settings')

@section('content')
<div class="page-content">
    <div class="container-fluid">

        {{-- Page Header --}}
        <div class="row mb-3">
            <div class="col-12">
                <div class="d-flex justify-content-between align-items-center flex-wrap gap-2">
                    <div>
                        <h4 class="mb-1 fw-bold" style="color:#15648C;">
                            <i class="ri-shield-check-line me-2"></i>Automated Verification Rules & Policies
                        </h4>
                        <p class="text-muted mb-0" style="font-size:13px;">
                            Configure comprehensive auto-approval conditions, GPS odometer checks, OCR bill validations, deduction behaviors, and tolerances.
                        </p>
                    </div>
                    <div class="d-flex align-items-center gap-2">
                        <span class="badge bg-light text-dark border px-3 py-2">
                            <i class="ri-calendar-event-line text-primary me-1"></i> Active FY:
                            <strong class="text-primary ms-1">
                                {{ (isset($financialYears) && $financialYears->firstWhere('YearId', $yearId)) ? $financialYears->firstWhere('YearId', $yearId)->Year : "FY (ID {$yearId})" }}
                            </strong>
                        </span>
                        <span class="badge bg-light text-dark border px-3 py-2">
                            <i class="ri-time-line text-warning me-1"></i> Pending Claims:
                            <strong class="text-primary ms-1">{{ number_format($pendingCount) }}</strong>
                        </span>
                        <a href="{{ route('verify-pending') }}" class="btn btn-outline-secondary btn-sm">
                            <i class="ri-dashboard-line me-1"></i> Verifier Dashboard
                        </a>
                    </div>
                </div>
            </div>
        </div>

        {{-- Flash Messages --}}
        @if(session('success'))
            <div class="alert alert-success alert-dismissible fade show" role="alert">
                <i class="ri-checkbox-circle-line me-2"></i> {{ session('success') }}
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        @endif
        @if(isset($errors) && $errors->any())
            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                <i class="ri-error-warning-line me-2"></i> Please check errors below.
                <ul class="mb-0 mt-1">
                    @foreach($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        @endif

        {{-- Company Selection Tabs --}}
        <div class="card mb-3 shadow-sm border-0">
            <div class="card-body p-2 bg-light rounded">
                <ul class="nav nav-pills nav-justified" id="companyTabs" role="tablist">
                    @foreach($companies as $cid => $cname)
                        <li class="nav-item" role="presentation">
                            <a class="nav-link fw-semibold {{ $currentCompany === $cid ? 'active bg-primary text-white shadow-sm' : 'text-dark' }}"
                               href="{{ route('admin.settings.auto-verify', ['company_id' => $cid]) }}">
                                @if($cid === 0)
                                    <i class="ri-global-line me-1"></i>
                                @elseif($cid === 1)
                                    <i class="ri-seedling-line me-1"></i>
                                @elseif($cid === 3)
                                    <i class="ri-plant-line me-1"></i>
                                @else
                                    <i class="ri-building-line me-1"></i>
                                @endif
                                {{ $cname }}
                                @if($currentCompany === $cid)
                                    <span class="badge bg-light text-primary ms-1" style="font-size:10px;">Active</span>
                                @endif
                            </a>
                        </li>
                    @endforeach
                </ul>
            </div>
        </div>

        @php
            $odo = $setting->getOdometerSettings();
            $da = $setting->getDaSettings();
            $lodging = $setting->getLodgingSettings();
            $meals = $setting->getMealSettings();
            $transit = $setting->getTransitSettings();
            $bills = $setting->getGeneralBillSettings();
            $campaigns = $setting->getCampaignSettings();
        @endphp

        {{-- Main Settings Form --}}
        <form action="{{ route('admin.settings.auto-verify.update') }}" method="POST" id="autoVerifySettingsForm">
            @csrf
            <input type="hidden" name="company_id" value="{{ $currentCompany }}">

            {{-- Row: Master Engine Controls & System Ceiling --}}
            <div class="card shadow-sm border-0 mb-4">
                <div class="card-header bg-white border-bottom py-3">
                    <div class="d-flex justify-content-between align-items-center">
                        <h6 class="card-title mb-0 fw-bold text-dark">
                            <i class="ri-settings-5-line text-primary me-2"></i>Global Automation Engine Controls
                        </h6>
                        <span class="badge {{ !empty($setting->is_enabled) ? 'bg-success' : 'bg-secondary' }}">
                            {{ !empty($setting->is_enabled) ? 'Automation Pipeline Active' : 'Automation Suspended' }}
                        </span>
                    </div>
                </div>
                <div class="card-body">
                    <div class="row g-3">
                        <div class="col-md-4">
                            <div class="p-3 rounded bg-light border h-100 d-flex flex-column justify-content-between">
                                <div>
                                    <div class="d-flex justify-content-between align-items-center mb-2">
                                        <h6 class="mb-0 fw-bold text-dark">Auto-Verification Master Switch</h6>
                                        <div class="form-check form-switch form-switch-md">
                                            <input class="form-check-input" type="checkbox" name="is_enabled" value="1" id="masterSwitch"
                                                   {{ old('is_enabled', $setting->is_enabled) ? 'checked' : '' }}>
                                        </div>
                                    </div>
                                    <small class="text-muted">Master on/off toggle for the automated verification pipeline for this company.</small>
                                </div>
                            </div>
                        </div>

                        <div class="col-md-4">
                            <div class="p-3 rounded bg-light border h-100 d-flex flex-column justify-content-between">
                                <div>
                                    <div class="d-flex justify-content-between align-items-center mb-2">
                                        <h6 class="mb-0 fw-bold text-dark">Auto-Submit Monthly Settlement</h6>
                                        <div class="form-check form-switch form-switch-md">
                                            <input class="form-check-input" type="checkbox" name="auto_submit_month" value="1" id="autoSubmitMonthSwitch"
                                                   {{ old('auto_submit_month', $setting->auto_submit_month) ? 'checked' : '' }}>
                                        </div>
                                    </div>
                                    <small class="text-muted">Automatically close voucher and forward to Approver when 100% of claims are verified.</small>
                                </div>
                            </div>
                        </div>

                        <div class="col-md-4">
                            <div class="p-3 rounded bg-light border h-100 d-flex flex-column justify-content-between">
                                <div>
                                    <div class="d-flex justify-content-between align-items-center mb-2">
                                        <h6 class="mb-0 fw-bold text-dark">Courier Receipt Enforcement</h6>
                                        <div class="form-check form-switch form-switch-md">
                                            <input class="form-check-input" type="checkbox" name="courier_check_required" value="1" id="courierCheckSwitch"
                                                   {{ old('courier_check_required', $setting->courier_check_required) ? 'checked' : '' }}>
                                        </div>
                                    </div>
                                    <small class="text-muted">Require physical courier receipt acknowledgement before auto-submitting month voucher.</small>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="row g-3 mt-1">
                        <div class="col-md-6">
                            <label class="form-label fw-semibold" style="font-size:12px;">Batch Processing Size (Claims per Execution)</label>
                            <div class="input-group input-group-sm">
                                <span class="input-group-text"><i class="ri-stack-line"></i></span>
                                <input type="number" class="form-control" name="batch_size" min="10" max="2000"
                                       value="{{ old('batch_size', $setting->batch_size ?? 250) }}" required>
                            </div>
                            <small class="text-muted" style="font-size:11px;">Recommended: 100 - 500 for optimal server performance.</small>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold" style="font-size:12px;">Global Single-Claim Maximum Ceiling (₹)</label>
                            <div class="input-group input-group-sm">
                                <span class="input-group-text">₹</span>
                                <input type="number" step="0.01" class="form-control" name="max_auto_verify_amount" min="0"
                                       value="{{ old('max_auto_verify_amount', $setting->max_auto_verify_amount ?? 10000) }}" required>
                            </div>
                            <small class="text-muted" style="font-size:11px;">Any claim exceeding this absolute ceiling is routed to a human verifier.</small>
                        </div>
                    </div>
                </div>
            </div>

            {{-- Section Title --}}
            <div class="d-flex justify-content-between align-items-center mb-3">
                <h5 class="fw-bold mb-0 text-dark">
                    <i class="ri-equalizer-line text-primary me-2"></i>Claim Category Rules, Validations & Switches
                </h5>
                <span class="badge bg-primary-subtle text-primary">All Possibilities & Options</span>
            </div>

            {{-- ========================================================================= --}}
            {{-- CARD 1: ODOMETER & TRAVEL (CLAIM 7)                                       --}}
            {{-- ========================================================================= --}}
            <div class="card shadow-sm border-0 mb-4" id="card-odometer">
                <div class="card-header bg-white border-bottom py-3 d-flex justify-content-between align-items-center flex-wrap gap-2">
                    <div class="d-flex align-items-center gap-2">
                        <div class="avatar-sm bg-primary-subtle text-primary rounded d-flex align-items-center justify-content-center p-2">
                            <i class="ri-roadster-line fs-20"></i>
                        </div>
                        <div>
                            <h6 class="card-title mb-0 fw-bold text-dark">Odometer & Vehicle Running Telemetry Policy</h6>
                            <small class="text-muted">Covers Two-Wheeler and Four-Wheeler travel, GPS telemetry, meter photos, and distance thresholds.</small>
                        </div>
                    </div>
                    <div class="d-flex align-items-center gap-3">
                        <span class="badge bg-light text-dark border">Claim #7</span>
                        <div class="form-check form-switch form-switch-md">
                            <input class="form-check-input" type="checkbox" name="rules[odometer][enabled]" value="1" id="odoEnabledSwitch"
                                   {{ !empty($odo['enabled']) ? 'checked' : '' }}>
                            <label class="form-check-label fw-semibold ms-1" for="odoEnabledSwitch">Enable Odometer Auto-Verification</label>
                        </div>
                    </div>
                </div>
                <div class="card-body">
                    {{-- Prominent GPS Odometer Switch --}}
                    <div class="p-3 mb-3 rounded border {{ !empty($odo['check_with_gps']) ? 'bg-success-subtle border-success' : 'bg-light border-warning' }}" id="gpsToggleBox">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <div class="d-flex align-items-center gap-2 mb-1">
                                    <h6 class="mb-0 fw-bold text-dark">
                                        <i class="ri-map-pin-range-line {{ !empty($odo['check_with_gps']) ? 'text-success' : 'text-secondary' }} me-1"></i>
                                        Check with GPS Odometer Telemetry
                                    </h6>
                                    <span class="badge {{ !empty($odo['check_with_gps']) ? 'bg-success' : 'bg-secondary' }}" id="gpsStatusBadge">
                                        {{ !empty($odo['check_with_gps']) ? 'GPS Check Active' : 'GPS Check Bypassed (Off)' }}
                                    </span>
                                </div>
                                <p class="text-muted mb-0" style="font-size:12px;">
                                    When <strong>ON</strong>: Claimed travel distance is strictly validated against real-time GPS background tracking recorded by the mobile app.
                                    <br>
                                    When <strong>OFF</strong>: GPS telemetry is bypassed. Travel claims are evaluated solely based on start/end meter readings, rate per KM, and daily thresholds (suitable if employees travel in low-connectivity areas).
                                </p>
                            </div>
                            <div class="form-check form-switch form-switch-lg ms-3">
                                <input class="form-check-input" type="checkbox" name="rules[odometer][check_with_gps]" value="1" id="checkGpsSwitch"
                                       {{ !empty($odo['check_with_gps']) ? 'checked' : '' }}
                                       onchange="toggleGpsStatus(this.checked)">
                            </div>
                        </div>
                    </div>

                    {{-- GPS Sub-Options --}}
                    <div class="row g-3 mb-3">
                        <div class="col-md-4">
                            <label class="form-label fw-bold text-dark" style="font-size:12px;">
                                GPS Distance Variance Tolerance (%)
                            </label>
                            <div class="d-flex align-items-center gap-2 mb-1">
                                <input type="range" class="form-range flex-grow-1" min="0" max="50" step="0.5" id="gpsTolRange"
                                       name="gps_tolerance_pct" value="{{ old('gps_tolerance_pct', $setting->gps_tolerance_pct ?? 15) }}"
                                       oninput="document.getElementById('gpsTolBadge').innerText = this.value + '%'; document.getElementById('gpsTolInput').value = this.value;">
                                <span class="badge bg-success-subtle text-success fs-13" id="gpsTolBadge">
                                    {{ old('gps_tolerance_pct', $setting->gps_tolerance_pct ?? 15) }}%
                                </span>
                            </div>
                            <input type="hidden" name="rules[odometer][gps_tolerance_pct]" id="gpsTolInput" value="{{ old('gps_tolerance_pct', $setting->gps_tolerance_pct ?? 15) }}">
                            <small class="text-muted" style="font-size:11px;">Allowable variance buffer between claimed KM and GPS KM.</small>
                        </div>

                        <div class="col-md-4">
                            <label class="form-label fw-bold text-dark" style="font-size:12px;">
                                When GPS Telemetry is Missing (No GPS Record)
                            </label>
                            <select class="form-select form-select-sm" name="rules[odometer][missing_gps_action]">
                                <option value="pass" {{ ($odo['missing_gps_action'] ?? 'pass') === 'pass' ? 'selected' : '' }}>
                                    🟢 Pass-through (Allow if meter readings are valid)
                                </option>
                                <option value="flag" {{ ($odo['missing_gps_action'] ?? 'pass') === 'flag' ? 'selected' : '' }}>
                                    🟡 Flag for Verifier Review (Require manual verification)
                                </option>
                            </select>
                            <small class="text-muted" style="font-size:11px;">Action when employee submits odometer claim without GPS tracking.</small>
                        </div>

                        <div class="col-md-4">
                            <label class="form-label fw-bold text-dark" style="font-size:12px;">
                                When Claimed Distance Exceeds GPS Track
                            </label>
                            <select class="form-select form-select-sm" name="rules[odometer][excess_gps_action]">
                                <option value="flag" {{ ($odo['excess_gps_action'] ?? 'flag') === 'flag' ? 'selected' : '' }}>
                                    🟡 Flag for Review (Do not auto-deduct, route to verifier)
                                </option>
                                <option value="cap" {{ ($odo['excess_gps_action'] ?? 'flag') === 'cap' ? 'selected' : '' }}>
                                    🟢 Auto-Cap Distance to GPS + Tolerance (Auto-deduct excess KM)
                                </option>
                                <option value="reject" {{ ($odo['excess_gps_action'] ?? 'flag') === 'reject' ? 'selected' : '' }}>
                                    🔴 Auto-Reject Claim (Decline inflated travel claim)
                                </option>
                            </select>
                            <small class="text-muted" style="font-size:11px;">Action when claimed KM is greater than GPS distance + tolerance.</small>
                        </div>
                    </div>

                    {{-- Odometer Photos & Safeguards --}}
                    <div class="row g-3 p-3 rounded bg-light border mb-3">
                        <div class="col-md-3">
                            <div class="form-check form-switch">
                                <input class="form-check-input" type="checkbox" name="rules[odometer][require_opening_photo]" value="1" id="reqOpenPhoto"
                                       {{ !empty($odo['require_opening_photo']) ? 'checked' : '' }}>
                                <label class="form-check-label fw-bold text-dark" for="reqOpenPhoto" style="font-size:12px;">
                                    Require Opening Meter Photo
                                </label>
                            </div>
                            <small class="text-muted d-block" style="font-size:11px;">Flag claim if opening photo is missing.</small>
                        </div>

                        <div class="col-md-3">
                            <div class="form-check form-switch">
                                <input class="form-check-input" type="checkbox" name="rules[odometer][require_closing_photo]" value="1" id="reqClosePhoto"
                                       {{ !empty($odo['require_closing_photo']) ? 'checked' : '' }}>
                                <label class="form-check-label fw-bold text-dark" for="reqClosePhoto" style="font-size:12px;">
                                    Require Closing Meter Photo
                                </label>
                            </div>
                            <small class="text-muted d-block" style="font-size:11px;">Flag claim if closing photo is missing.</small>
                        </div>

                        <div class="col-md-3">
                            <div class="form-check form-switch">
                                <input class="form-check-input" type="checkbox" name="rules[odometer][monthly_km_limit_check]" value="1" id="monthlyKmLimitCheck"
                                       {{ !empty($odo['monthly_km_limit_check']) ? 'checked' : '' }}>
                                <label class="form-check-label fw-bold text-dark" for="monthlyKmLimitCheck" style="font-size:12px;">
                                    Enforce Monthly 1,200 KM Policy
                                </label>
                            </div>
                            <small class="text-muted d-block" style="font-size:11px;">Flag trips crossing 1,200 cumulative KM without special approval.</small>
                        </div>

                        <div class="col-md-3">
                            <label class="form-label fw-bold text-dark mb-1" style="font-size:12px;">Max Single-Day Travel (KM)</label>
                            <div class="input-group input-group-sm">
                                <input type="number" step="1" min="50" max="2000" class="form-control" name="rules[odometer][max_daily_km]"
                                       value="{{ $odo['max_daily_km'] ?? 400 }}">
                                <span class="input-group-text">KM</span>
                            </div>
                            <small class="text-muted d-block" style="font-size:11px;">Safety cap for single-day journeys.</small>
                        </div>
                    </div>

                    {{-- Financial Ceiling and Deduction Action --}}
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label fw-bold text-dark" style="font-size:12px;">
                                Action When Claimed Amount > Eligible (Rate × KM)
                            </label>
                            <select class="form-select form-select-sm" name="rules[odometer][action_over_limit]">
                                <option value="cap" {{ ($odo['action_over_limit'] ?? 'cap') === 'cap' ? 'selected' : '' }}>
                                    🟢 Auto-Deduct / Cap to Rate × KM (Approve eligible amount, tag Adjusted)
                                </option>
                                <option value="flag" {{ ($odo['action_over_limit'] ?? 'cap') === 'flag' ? 'selected' : '' }}>
                                    🟡 Flag for Verifier Review (Do not auto-deduct)
                                </option>
                                <option value="reject" {{ ($odo['action_over_limit'] ?? 'cap') === 'reject' ? 'selected' : '' }}>
                                    🔴 Auto-Reject Claim
                                </option>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-bold text-dark" style="font-size:12px;">Max Single Travel Claim Ceiling (₹)</label>
                            <div class="input-group input-group-sm">
                                <span class="input-group-text">₹</span>
                                <input type="number" step="0.01" class="form-control" name="rules[odometer][max_amount]"
                                       value="{{ $odo['max_amount'] ?? 15000 }}">
                            </div>
                            <small class="text-muted" style="font-size:11px;">Claims exceeding this amount will require manual verifier sign-off.</small>
                        </div>
                    </div>
                </div>
            </div>

            {{-- ========================================================================= --}}
            {{-- CARD 2: GENERAL BILLS & OCR INTELLIGENCE                                  --}}
            {{-- ========================================================================= --}}
            <div class="card shadow-sm border-0 mb-4" id="card-general-bills">
                <div class="card-header bg-white border-bottom py-3 d-flex justify-content-between align-items-center flex-wrap gap-2">
                    <div class="d-flex align-items-center gap-2">
                        <div class="avatar-sm bg-info-subtle text-info rounded d-flex align-items-center justify-content-center p-2">
                            <i class="ri-file-text-line fs-20"></i>
                        </div>
                        <div>
                            <h6 class="card-title mb-0 fw-bold text-dark">General Bills, Invoices & OCR Intelligence Policy</h6>
                            <small class="text-muted">Stationery, Courier, Repairs, Printing, Admin and general invoice auto-verification options.</small>
                        </div>
                    </div>
                    <div class="d-flex align-items-center gap-3">
                        <span class="badge bg-light text-dark border">General Bills</span>
                        <div class="form-check form-switch form-switch-md">
                            <input class="form-check-input" type="checkbox" name="rules[general_bills][enabled]" value="1" id="billsEnabledSwitch"
                                   {{ !empty($bills['enabled']) ? 'checked' : '' }}>
                            <label class="form-check-label fw-semibold ms-1" for="billsEnabledSwitch">Enable Bills Auto-Verification</label>
                        </div>
                    </div>
                </div>
                <div class="card-body">
                    {{-- Prominent OCR Switch --}}
                    <div class="p-3 mb-3 rounded border {{ !empty($bills['check_with_ocr']) ? 'bg-primary-subtle border-primary' : 'bg-light border-secondary' }}" id="ocrToggleBox">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <div class="d-flex align-items-center gap-2 mb-1">
                                    <h6 class="mb-0 fw-bold text-dark">
                                        <i class="ri-scan-2-line {{ !empty($bills['check_with_ocr']) ? 'text-primary' : 'text-secondary' }} me-1"></i>
                                        Check with OCR Scanned Bill Total
                                    </h6>
                                    <span class="badge {{ !empty($bills['check_with_ocr']) ? 'bg-primary' : 'bg-secondary' }}" id="ocrStatusBadge">
                                        {{ !empty($bills['check_with_ocr']) ? 'OCR Comparison Active' : 'OCR Comparison Bypassed (Off)' }}
                                    </span>
                                </div>
                                <p class="text-muted mb-0" style="font-size:12px;">
                                    When <strong>ON</strong>: Compares the claimant filled total against the OCR extracted total from the uploaded tax invoice / bill.
                                    <br>
                                    When <strong>OFF</strong>: OCR scanning is bypassed. Claims are checked against mandatory attachment existence and single bill limit only.
                                </p>
                            </div>
                            <div class="form-check form-switch form-switch-lg ms-3">
                                <input class="form-check-input" type="checkbox" name="rules[general_bills][check_with_ocr]" value="1" id="checkOcrSwitch"
                                       {{ !empty($bills['check_with_ocr']) ? 'checked' : '' }}
                                       onchange="toggleOcrStatus(this.checked)">
                            </div>
                        </div>
                    </div>

                    {{-- OCR Tolerances & Actions --}}
                    <div class="row g-3 mb-3">
                        <div class="col-md-3">
                            <label class="form-label fw-bold text-dark" style="font-size:12px;">OCR Percentage Tolerance (%)</label>
                            <div class="input-group input-group-sm">
                                <input type="number" step="0.1" min="0" max="50" class="form-control"
                                       name="ocr_tolerance_pct" value="{{ old('ocr_tolerance_pct', $setting->ocr_tolerance_pct ?? 5) }}">
                                <span class="input-group-text">%</span>
                            </div>
                            <input type="hidden" name="rules[general_bills][ocr_tolerance_pct]" value="{{ old('ocr_tolerance_pct', $setting->ocr_tolerance_pct ?? 5) }}">
                            <small class="text-muted" style="font-size:11px;">Allowable percentage difference.</small>
                        </div>

                        <div class="col-md-3">
                            <label class="form-label fw-bold text-dark" style="font-size:12px;">OCR Flat Rupee Buffer (₹)</label>
                            <div class="input-group input-group-sm">
                                <span class="input-group-text">₹</span>
                                <input type="number" step="0.5" min="0" max="500" class="form-control"
                                       name="ocr_tolerance_flat" value="{{ old('ocr_tolerance_flat', $setting->ocr_tolerance_flat ?? 5) }}">
                            </div>
                            <input type="hidden" name="rules[general_bills][ocr_tolerance_flat]" value="{{ old('ocr_tolerance_flat', $setting->ocr_tolerance_flat ?? 5) }}">
                            <small class="text-muted" style="font-size:11px;">Covers minor rounding discrepancies.</small>
                        </div>

                        <div class="col-md-3">
                            <label class="form-label fw-bold text-dark" style="font-size:12px;">Action on OCR Discrepancy</label>
                            <select class="form-select form-select-sm" name="rules[general_bills][action_ocr_mismatch]">
                                <option value="flag" {{ ($bills['action_ocr_mismatch'] ?? 'flag') === 'flag' ? 'selected' : '' }}>
                                    🟡 Flag for Verifier Review (Default)
                                </option>
                                <option value="cap" {{ ($bills['action_ocr_mismatch'] ?? 'flag') === 'cap' ? 'selected' : '' }}>
                                    🟢 Auto-Adjust to Scanned OCR Total
                                </option>
                                <option value="reject" {{ ($bills['action_ocr_mismatch'] ?? 'flag') === 'reject' ? 'selected' : '' }}>
                                    🔴 Auto-Reject Claim
                                </option>
                            </select>
                            <small class="text-muted" style="font-size:11px;">Triggered if difference exceeds tolerance.</small>
                        </div>

                        <div class="col-md-3">
                            <label class="form-label fw-bold text-dark" style="font-size:12px;">Require Bill / Invoice Upload</label>
                            <div class="form-check form-switch pt-1">
                                <input class="form-check-input" type="checkbox" name="rules[general_bills][require_attachment]" value="1" id="reqBillAttachment"
                                       {{ !empty($bills['require_attachment']) ? 'checked' : '' }}>
                                <label class="form-check-label fw-semibold" for="reqBillAttachment" style="font-size:12px;">
                                    Mandatory Attachment
                                </label>
                            </div>
                            <small class="text-muted" style="font-size:11px;">Flag claims without uploaded bill.</small>
                        </div>
                    </div>

                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label fw-bold text-dark" style="font-size:12px;">When Claim Exceeds General Ceiling</label>
                            <select class="form-select form-select-sm" name="rules[general_bills][action_over_limit]">
                                <option value="flag" {{ ($bills['action_over_limit'] ?? 'flag') === 'flag' ? 'selected' : '' }}>
                                    🟡 Flag for Verifier Review
                                </option>
                                <option value="cap" {{ ($bills['action_over_limit'] ?? 'flag') === 'cap' ? 'selected' : '' }}>
                                    🟢 Auto-Cap to Maximum Limit
                                </option>
                                <option value="reject" {{ ($bills['action_over_limit'] ?? 'flag') === 'reject' ? 'selected' : '' }}>
                                    🔴 Auto-Reject Claim
                                </option>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-bold text-dark" style="font-size:12px;">Max General Bill Ceiling (₹)</label>
                            <div class="input-group input-group-sm">
                                <span class="input-group-text">₹</span>
                                <input type="number" step="0.01" class="form-control" name="rules[general_bills][max_amount]"
                                       value="{{ $bills['max_amount'] ?? 5000 }}">
                            </div>
                            <small class="text-muted" style="font-size:11px;">Any single general invoice above this requires verifier approval.</small>
                        </div>
                    </div>
                </div>
            </div>

            {{-- ========================================================================= --}}
            {{-- 2-COLUMN ROW: DA & MEALS + LODGING & TRANSIT                              --}}
            {{-- ========================================================================= --}}
            <div class="row g-3 mb-4">
                {{-- CARD 3: DAILY ALLOWANCE & GROUP MEALS --}}
                <div class="col-lg-6">
                    <div class="card shadow-sm border-0 h-100" id="card-da-meals">
                        <div class="card-header bg-white border-bottom py-3 d-flex justify-content-between align-items-center">
                            <div class="d-flex align-items-center gap-2">
                                <div class="avatar-sm bg-warning-subtle text-warning rounded d-flex align-items-center justify-content-center p-2">
                                    <i class="ri-restaurant-line fs-20"></i>
                                </div>
                                <div>
                                    <h6 class="card-title mb-0 fw-bold text-dark">Daily Allowance (DA) & Group Meals</h6>
                                    <small class="text-muted">Claims #19 (Inside HQ), #20 (Outside HQ), #17 (Group Meals)</small>
                                </div>
                            </div>
                            <div class="form-check form-switch">
                                <input class="form-check-input" type="checkbox" name="rules[da][enabled]" value="1" id="daEnabledSwitch"
                                       {{ !empty($da['enabled']) ? 'checked' : '' }}>
                            </div>
                        </div>
                        <div class="card-body">
                            <div class="row g-3 mb-3">
                                <div class="col-md-6">
                                    <div class="form-check form-switch mb-1">
                                        <input class="form-check-input" type="checkbox" name="rules[da][check_grade_limit]" value="1" id="daGradeLimit"
                                               {{ !empty($da['check_grade_limit']) ? 'checked' : '' }}>
                                        <label class="form-check-label fw-bold text-dark" for="daGradeLimit" style="font-size:12px;">
                                            Enforce Grade DA Limit
                                        </label>
                                    </div>
                                    <small class="text-muted" style="font-size:11px;">Check against grade daily entitlement.</small>
                                </div>
                                <div class="col-md-6">
                                    <div class="form-check form-switch mb-1">
                                        <input class="form-check-input" type="checkbox" name="rules[da][check_attendance]" value="1" id="daAttendance"
                                               {{ !empty($da['check_attendance']) ? 'checked' : '' }}>
                                        <label class="form-check-label fw-bold text-dark" for="daAttendance" style="font-size:12px;">
                                            Cross-Check ESS Attendance
                                        </label>
                                    </div>
                                    <small class="text-muted" style="font-size:11px;">Flag if employee was absent/leave on date.</small>
                                </div>
                            </div>

                            <div class="mb-3">
                                <label class="form-label fw-bold text-dark" style="font-size:12px;">When DA Claim Exceeds Grade Limit</label>
                                <select class="form-select form-select-sm" name="rules[da][action_over_limit]">
                                    <option value="cap" {{ ($da['action_over_limit'] ?? 'cap') === 'cap' ? 'selected' : '' }}>
                                        🟢 Auto-Cap to Grade DA Limit (Approve eligible, tag Adjusted)
                                    </option>
                                    <option value="flag" {{ ($da['action_over_limit'] ?? 'cap') === 'flag' ? 'selected' : '' }}>
                                        🟡 Flag for Verifier Review
                                    </option>
                                    <option value="reject" {{ ($da['action_over_limit'] ?? 'cap') === 'reject' ? 'selected' : '' }}>
                                        🔴 Auto-Reject Claim
                                    </option>
                                </select>
                            </div>

                            <hr class="my-3">

                            {{-- Group Meals Sub-section --}}
                            <div class="d-flex justify-content-between align-items-center mb-2">
                                <h6 class="mb-0 fw-bold text-dark" style="font-size:13px;">Group Meals Policy (Claim #17)</h6>
                                <div class="form-check form-switch">
                                    <input class="form-check-input" type="checkbox" name="rules[meals][enabled]" value="1" id="mealsEnabledSwitch"
                                           {{ !empty($meals['enabled']) ? 'checked' : '' }}>
                                </div>
                            </div>
                            <div class="row g-2 mb-2">
                                <div class="col-md-6">
                                    <div class="form-check form-switch mb-1">
                                        <input class="form-check-input" type="checkbox" name="rules[meals][check_headcount_limit]" value="1" id="mealHeadcount"
                                               {{ !empty($meals['check_headcount_limit']) ? 'checked' : '' }}>
                                        <label class="form-check-label fw-semibold" for="mealHeadcount" style="font-size:12px;">
                                            Check Headcount Quota
                                        </label>
                                    </div>
                                    <small class="text-muted" style="font-size:11px;">Headcount × Grade rate limit.</small>
                                </div>
                                <div class="col-md-6">
                                    <div class="form-check form-switch mb-1">
                                        <input class="form-check-input" type="checkbox" name="rules[meals][require_attachment]" value="1" id="mealAttachment"
                                               {{ !empty($meals['require_attachment']) ? 'checked' : '' }}>
                                        <label class="form-check-label fw-semibold" for="mealAttachment" style="font-size:12px;">
                                            Require Food Bill
                                        </label>
                                    </div>
                                    <small class="text-muted" style="font-size:11px;">Mandatory receipt upload.</small>
                                </div>
                            </div>
                            <div>
                                <label class="form-label fw-bold text-dark" style="font-size:12px;">When Group Meal Exceeds Headcount Limit</label>
                                <select class="form-select form-select-sm" name="rules[meals][action_over_limit]">
                                    <option value="cap" {{ ($meals['action_over_limit'] ?? 'cap') === 'cap' ? 'selected' : '' }}>
                                        🟢 Auto-Cap to Authorized Headcount Quota
                                    </option>
                                    <option value="flag" {{ ($meals['action_over_limit'] ?? 'cap') === 'flag' ? 'selected' : '' }}>
                                        🟡 Flag for Verifier Review
                                    </option>
                                    <option value="reject" {{ ($meals['action_over_limit'] ?? 'cap') === 'reject' ? 'selected' : '' }}>
                                        🔴 Auto-Reject Claim
                                    </option>
                                </select>
                            </div>
                        </div>
                    </div>
                </div>

                {{-- CARD 4: HOTEL LODGING & TRANSIT --}}
                <div class="col-lg-6">
                    <div class="card shadow-sm border-0 h-100" id="card-lodging-transit">
                        <div class="card-header bg-white border-bottom py-3 d-flex justify-content-between align-items-center">
                            <div class="d-flex align-items-center gap-2">
                                <div class="avatar-sm bg-success-subtle text-success rounded d-flex align-items-center justify-content-center p-2">
                                    <i class="ri-hotel-line fs-20"></i>
                                </div>
                                <div>
                                    <h6 class="card-title mb-0 fw-bold text-dark">Lodging & Transit Policies</h6>
                                    <small class="text-muted">Claims #1 (Hotel), #2, 14, 15, 3, 4 (Flight, Rail, Bus, Fares)</small>
                                </div>
                            </div>
                            <div class="form-check form-switch">
                                <input class="form-check-input" type="checkbox" name="rules[lodging][enabled]" value="1" id="lodgingEnabledSwitch"
                                       {{ !empty($lodging['enabled']) ? 'checked' : '' }}>
                            </div>
                        </div>
                        <div class="card-body">
                            {{-- Lodging Options --}}
                            <div class="d-flex justify-content-between align-items-center mb-2">
                                <h6 class="mb-0 fw-bold text-dark" style="font-size:13px;">Hotel & Lodging (Claim #1)</h6>
                                <span class="badge bg-light text-dark border">City Tariff</span>
                            </div>
                            <div class="row g-2 mb-2">
                                <div class="col-md-6">
                                    <div class="form-check form-switch mb-1">
                                        <input class="form-check-input" type="checkbox" name="rules[lodging][check_city_category]" value="1" id="lodgingCity"
                                               {{ !empty($lodging['check_city_category']) ? 'checked' : '' }}>
                                        <label class="form-check-label fw-semibold" for="lodgingCity" style="font-size:12px;">
                                            Check City Category Tariff
                                        </label>
                                    </div>
                                    <small class="text-muted" style="font-size:11px;">Tier A, B, C, Metro grade limits.</small>
                                </div>
                                <div class="col-md-6">
                                    <div class="form-check form-switch mb-1">
                                        <input class="form-check-input" type="checkbox" name="rules[lodging][require_attachment]" value="1" id="lodgingBill"
                                               {{ !empty($lodging['require_attachment']) ? 'checked' : '' }}>
                                        <label class="form-check-label fw-semibold" for="lodgingBill" style="font-size:12px;">
                                            Require Tax Invoice
                                        </label>
                                    </div>
                                    <small class="text-muted" style="font-size:11px;">Mandatory hotel bill attachment.</small>
                                </div>
                            </div>
                            <div class="mb-3">
                                <label class="form-label fw-bold text-dark" style="font-size:12px;">When Hotel Bill Exceeds City Tariff</label>
                                <select class="form-select form-select-sm" name="rules[lodging][action_over_limit]">
                                    <option value="flag" {{ ($lodging['action_over_limit'] ?? 'flag') === 'flag' ? 'selected' : '' }}>
                                        🟡 Flag for Verifier Review (Default for hotels)
                                    </option>
                                    <option value="cap" {{ ($lodging['action_over_limit'] ?? 'flag') === 'cap' ? 'selected' : '' }}>
                                        🟢 Auto-Cap to City Category Tariff Limit
                                    </option>
                                    <option value="reject" {{ ($lodging['action_over_limit'] ?? 'flag') === 'reject' ? 'selected' : '' }}>
                                        🔴 Auto-Reject Claim
                                    </option>
                                </select>
                            </div>

                            <hr class="my-3">

                            {{-- Transit Options --}}
                            <div class="d-flex justify-content-between align-items-center mb-2">
                                <h6 class="mb-0 fw-bold text-dark" style="font-size:13px;">Transit & Conveyance (Flight, Rail, Bus)</h6>
                                <div class="form-check form-switch">
                                    <input class="form-check-input" type="checkbox" name="rules[transit][enabled]" value="1" id="transitEnabledSwitch"
                                           {{ !empty($transit['enabled']) ? 'checked' : '' }}>
                                </div>
                            </div>
                            <div class="row g-2 mb-2">
                                <div class="col-md-6">
                                    <div class="form-check form-switch mb-1">
                                        <input class="form-check-input" type="checkbox" name="rules[transit][check_grade_entitlement]" value="1" id="transitGrade"
                                               {{ !empty($transit['check_grade_entitlement']) ? 'checked' : '' }}>
                                        <label class="form-check-label fw-semibold" for="transitGrade" style="font-size:12px;">
                                            Grade Mode Entitlement
                                        </label>
                                    </div>
                                    <small class="text-muted" style="font-size:11px;">Flight / 2AC / 3AC entitlement check.</small>
                                </div>
                                <div class="col-md-6">
                                    <div class="form-check form-switch mb-1">
                                        <input class="form-check-input" type="checkbox" name="rules[transit][require_attachment]" value="1" id="transitTicket"
                                               {{ !empty($transit['require_attachment']) ? 'checked' : '' }}>
                                        <label class="form-check-label fw-semibold" for="transitTicket" style="font-size:12px;">
                                            Require Ticket / Boarding Pass
                                        </label>
                                    </div>
                                    <small class="text-muted" style="font-size:11px;">Mandatory ticket attachment.</small>
                                </div>
                            </div>
                            <div>
                                <label class="form-label fw-bold text-dark" style="font-size:12px;">Action on Unentitled Mode / Over Limit</label>
                                <select class="form-select form-select-sm" name="rules[transit][action_over_limit]">
                                    <option value="flag" {{ ($transit['action_over_limit'] ?? 'flag') === 'flag' ? 'selected' : '' }}>
                                        🟡 Flag for Verifier Review
                                    </option>
                                    <option value="cap" {{ ($transit['action_over_limit'] ?? 'flag') === 'cap' ? 'selected' : '' }}>
                                        🟢 Auto-Cap to Entitled Class Limit
                                    </option>
                                    <option value="reject" {{ ($transit['action_over_limit'] ?? 'flag') === 'reject' ? 'selected' : '' }}>
                                        🔴 Auto-Reject Claim
                                    </option>
                                </select>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            {{-- ========================================================================= --}}
            {{-- COLLAPSIBLE CLAIM-BY-CLAIM OVERRIDE MATRIX (EVERY CLAIM TYPE 1-43)        --}}
            {{-- ========================================================================= --}}
            <div class="card shadow-sm border-0 mb-4">
                <div class="card-header bg-white border-bottom py-3 d-flex justify-content-between align-items-center flex-wrap gap-2">
                    <div>
                        <h6 class="card-title mb-0 fw-bold text-dark">
                            <i class="ri-table-line text-secondary me-2"></i>Granular Claim-by-Claim Override Matrix
                        </h6>
                        <small class="text-muted">Optionally customize specific behavior or ceiling for individual claim codes (1 to 43).</small>
                    </div>
                    <button class="btn btn-outline-secondary btn-sm" type="button" data-bs-toggle="collapse" data-bs-target="#claimMatrixCollapse">
                        <i class="ri-arrow-down-s-line me-1"></i> Toggle Individual Claim Types
                    </button>
                </div>

                <div class="collapse" id="claimMatrixCollapse">
                    <div class="card-body p-0">
                        {{-- Category Tabs --}}
                        <ul class="nav nav-tabs nav-tabs-custom nav-success p-2 pb-0" role="tablist">
                            @foreach($categorizedClaims as $catKey => $catInfo)
                                <li class="nav-item" role="presentation">
                                    <a class="nav-link {{ $loop->first ? 'active' : '' }}" data-bs-toggle="tab" href="#cat_{{ $catKey }}" role="tab">
                                        {{ $catInfo['title'] }}
                                    </a>
                                </li>
                            @endforeach
                        </ul>

                        <div class="tab-content p-3">
                            @foreach($categorizedClaims as $catKey => $catInfo)
                                <div class="tab-pane fade {{ $loop->first ? 'show active' : '' }}" id="cat_{{ $catKey }}" role="tabpanel">
                                    <div class="alert alert-light border py-2 px-3 mb-3 d-flex align-items-center gap-2" style="font-size:12px;">
                                        <i class="ri-information-line text-primary fs-16"></i>
                                        <span>{{ $catInfo['description'] }}</span>
                                    </div>

                                    <div class="table-responsive">
                                        <table class="table table-bordered table-hover align-middle mb-0" style="font-size:13px;">
                                            <thead class="table-light">
                                                <tr>
                                                    <th style="width: 50px;" class="text-center">ID</th>
                                                    <th style="width: 220px;">Claim Type</th>
                                                    <th style="width: 120px;" class="text-center">Auto-Verify</th>
                                                    <th>When Claim Exceeds Limit / Policy</th>
                                                    <th style="width: 140px;" class="text-center">Mandatory Bill</th>
                                                    <th style="width: 160px;">Max Ceiling (₹)</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                @foreach($claimTypes->whereIn('ClaimId', $catInfo['ids']) as $ctype)
                                                    @php
                                                        $cId = (string) $ctype->ClaimId;
                                                        $rule = $setting->getRuleForClaimType($ctype->ClaimId);
                                                    @endphp
                                                    <tr>
                                                        <td class="text-center fw-semibold text-muted">#{{ $ctype->ClaimId }}</td>
                                                        <td>
                                                            <strong>{{ $ctype->ClaimName }}</strong>
                                                            <br>
                                                            <span class="badge bg-secondary-subtle text-secondary" style="font-size:10px;">
                                                                {{ $ctype->ClaimCode }}
                                                            </span>
                                                        </td>
                                                        <td class="text-center">
                                                            <div class="form-check form-switch d-inline-block">
                                                                <input class="form-check-input" type="checkbox"
                                                                       name="rules[{{ $cId }}][enabled]" value="1"
                                                                       {{ !empty($rule['enabled']) ? 'checked' : '' }}>
                                                            </div>
                                                        </td>
                                                        <td>
                                                            <select class="form-select form-select-sm" name="rules[{{ $cId }}][action_over_limit]">
                                                                <option value="cap" {{ ($rule['action_over_limit'] ?? 'cap') === 'cap' ? 'selected' : '' }}>
                                                                    🟢 Auto-Deduct / Cap to Limit
                                                                </option>
                                                                <option value="flag" {{ ($rule['action_over_limit'] ?? 'cap') === 'flag' ? 'selected' : '' }}>
                                                                    🟡 Flag for Verifier Review
                                                                </option>
                                                                <option value="reject" {{ ($rule['action_over_limit'] ?? 'cap') === 'reject' ? 'selected' : '' }}>
                                                                    🔴 Auto-Reject Claim
                                                                </option>
                                                            </select>
                                                        </td>
                                                        <td class="text-center">
                                                            <div class="form-check form-switch d-inline-block">
                                                                <input class="form-check-input" type="checkbox"
                                                                       name="rules[{{ $cId }}][require_attachment]" value="1"
                                                                       {{ !empty($rule['require_attachment']) ? 'checked' : '' }}>
                                                            </div>
                                                        </td>
                                                        <td>
                                                            <div class="input-group input-group-sm">
                                                                <span class="input-group-text">₹</span>
                                                                <input type="number" class="form-control" name="rules[{{ $cId }}][max_amount]"
                                                                       value="{{ $rule['max_amount'] ?? 10000 }}" min="0">
                                                            </div>
                                                        </td>
                                                    </tr>
                                                @endforeach
                                            </tbody>
                                        </table>
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    </div>
                </div>

                {{-- Action Bar --}}
                <div class="card-footer bg-light border-top py-3 d-flex justify-content-between align-items-center flex-wrap gap-2">
                    <div class="text-muted" style="font-size:12px;">
                        <i class="ri-user-follow-line me-1"></i> Last updated by:
                        <strong>{{ $setting->updated_by ?? 'System' }}</strong>
                        ({{ $setting->updated_at ? $setting->updated_at->format('d M Y, h:i A') : 'Default' }})
                    </div>
                    <div class="d-flex gap-2">
                        <button type="button" class="btn btn-outline-primary" id="btnOpenSimulator" data-bs-toggle="collapse" data-bs-target="#simulationWidget">
                            <i class="ri-flask-line me-1"></i> Test Rules (Live Simulator)
                        </button>
                        <button type="submit" class="btn btn-primary px-4 fw-bold shadow-sm">
                            <i class="ri-save-3-line me-1"></i> Save All Settings & Rules
                        </button>
                    </div>
                </div>
            </div>
        </form>

        {{-- Interactive Live Simulation Box --}}
        <div class="collapse mb-4" id="simulationWidget">
            <div class="card shadow border border-primary border-opacity-25">
                <div class="card-header d-flex justify-content-between align-items-center py-3" style="background-color: #dde6ff !important; color: #1e3a8a;">
                    <h6 class="card-title mb-0 fw-bold" style="color: #ffffff !important;">
                        <i class="ri-play-circle-line me-2" style="color: #ffffff !important;"></i>Live Rule Simulation Preview (Dry-Run Engine)
                    </h6>
                    <button type="button" class="btn-close btn-close-white" data-bs-toggle="collapse" data-bs-target="#simulationWidget"></button>
                </div>
                <div class="card-body">
                    <p class="text-muted" style="font-size:13px;">
                        Test how these rule settings will behave against actual submitted claims without changing any database records.
                    </p>

                    <div class="row g-2 align-items-center mb-3">
                        <div class="col-md-3">
                            <label class="form-label fw-semibold" style="font-size:12px;">
                                <i class="ri-calendar-line text-primary me-1"></i> Financial Year
                            </label>
                            <select class="form-select form-select-sm fw-semibold" id="simYear">
                                @forelse($financialYears ?? [] as $fy)
                                    <option value="{{ $fy->YearId }}" {{ (int) $yearId === (int) $fy->YearId ? 'selected' : '' }}>
                                        FY {{ $fy->Year }} (Year ID {{ $fy->YearId }})
                                    </option>
                                @empty
                                    <option value="{{ $yearId }}">FY (Year ID {{ $yearId }})</option>
                                @endforelse
                            </select>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label fw-semibold" style="font-size:12px;">
                                <i class="ri-calendar-check-line text-success me-1"></i> Expense Month
                            </label>
                            <select class="form-select form-select-sm fw-semibold" id="simMonth">
                                @php
                                    $financialMonths = [
                                        4 => 'April (Month 4)',
                                        5 => 'May (Month 5)',
                                        6 => 'June (Month 6)',
                                        7 => 'July (Month 7)',
                                        8 => 'August (Month 8)',
                                        9 => 'September (Month 9)',
                                        10 => 'October (Month 10)',
                                        11 => 'November (Month 11)',
                                        12 => 'December (Month 12)',
                                        1 => 'January (Month 1)',
                                        2 => 'February (Month 2)',
                                        3 => 'March (Month 3)',
                                    ];
                                @endphp
                                @foreach($financialMonths as $mNum => $mLabel)
                                    <option value="{{ $mNum }}" {{ $mNum === 4 ? 'selected' : '' }}>
                                        {{ $mLabel }}
                                    </option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label fw-semibold" style="font-size:12px;">
                                <i class="ri-equalizer-line text-info me-1"></i> Evaluation Sample
                            </label>
                            <select class="form-select form-select-sm" id="simLimit">
                                <option value="100">First 100 Claims</option>
                                <option value="250" selected>First 250 Claims</option>
                                <option value="500">First 500 Claims</option>
                                <option value="1000">First 1,000 Claims</option>
                            </select>
                        </div>
                        <div class="col-md-3 pt-md-3">
                            <button type="button" class="btn btn-success btn-sm w-100 fw-bold py-2 shadow-sm" id="btnRunSim" onclick="executeSimulation()">
                                <i class="ri-rocket-line me-1"></i> Run Test Simulation
                            </button>
                        </div>
                    </div>

                    {{-- Quick Search by Claim / Exp ID --}}
                    <div class="p-2 mb-3 bg-light rounded border d-flex align-items-center justify-content-between flex-wrap gap-2">
                        <div class="d-flex align-items-center gap-2">
                            <span class="text-muted fw-semibold" style="font-size:12px;">
                                <i class="ri-search-eye-line text-primary me-1"></i> Quick Lookup Claim:
                            </span>
                            <div class="input-group input-group-sm" style="width: 270px;">
                                <span class="input-group-text">Exp ID #</span>
                                <input type="number" class="form-control" id="simDirectExpId" placeholder="e.g. 38695" aria-label="e.g. 38695" min="1">
                                <button class="btn btn-primary" type="button" id="btnDirectInspect" onclick="executeDirectInspect()">
                                    <i class="ri-eye-line me-1"></i> View Details
                                </button>
                            </div>
                        </div>
                        <small class="text-muted" style="font-size:11px;">
                            <i class="ri-information-line text-info me-1"></i>Click any claim row below or enter an Exp ID to inspect its full policy breakdown, receipt images, and verifier reasoning.
                        </small>
                    </div>

                    {{-- Simulation Results Display --}}
                    <div id="simSpinner" class="text-center py-4 d-none">
                        <div class="spinner-border text-primary" role="status"></div>
                        <p class="text-muted mt-2 mb-0" style="font-size:12px;">Evaluating claims against active rules and rates...</p>
                    </div>

                    <div id="simResultsContainer" class="d-none">
                        <div class="row g-2 mb-3">
                            <div class="col-md-3">
                                <div class="p-3 bg-light rounded text-center border">
                                    <small class="text-muted d-block">Evaluated Claims</small>
                                    <h4 class="mb-0 fw-bold text-dark" id="simTotal">0</h4>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="p-3 bg-success-subtle rounded text-center border border-success border-opacity-25">
                                    <small class="text-success fw-semibold d-block">🟢 Auto-Approved (Clean)</small>
                                    <h4 class="mb-0 fw-bold text-success" id="simApproved">0</h4>
                                    <small class="text-muted" id="simApprovedAmt">₹0.00</small>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="p-3 bg-warning-subtle rounded text-center border border-warning border-opacity-25">
                                    <small class="text-warning fw-semibold d-block">🟡 Auto-Deducted / Capped</small>
                                    <h4 class="mb-0 fw-bold text-warning" id="simAdjusted">0</h4>
                                    <small class="text-muted" id="simAdjustedAmt">₹0.00</small>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="p-3 bg-danger-subtle rounded text-center border border-danger border-opacity-25">
                                    <small class="text-danger fw-semibold d-block">🔴 Flagged for Review</small>
                                    <h4 class="mb-0 fw-bold text-danger" id="simFlagged">0</h4>
                                </div>
                            </div>
                        </div>

                        {{-- Sample Decisions Table --}}
                        <div class="table-responsive" style="max-height: 320px; overflow-y: auto;">
                            <table class="table table-sm table-bordered table-hover align-middle mb-0" style="font-size:12px;">
                                <thead class="table-light sticky-top">
                                    <tr>
                                        <th style="width:75px;" class="text-center">Exp ID</th>
                                        <th style="width:110px;">Claim Type</th>
                                        <th style="width:90px;" class="text-end">Claimed</th>
                                        <th style="width:90px;" class="text-end">Verified</th>
                                        <th style="width:95px;" class="text-center">Decision</th>
                                        <th>Audit Reason / Policy Applied</th>
                                        <th style="width:85px;" class="text-center">Details</th>
                                    </tr>
                                </thead>
                                <tbody id="simDecisionsBody">
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        {{-- Detailed Claim Inspection Modal --}}
        {{-- Detailed Claim Inspection Modal (e-Home View with Auto-Verify Audit) --}}
        <div class="modal fade" id="claimDetailModal" tabindex="-1" aria-labelledby="claimDetailModalLabel" aria-hidden="true">
            <div class="modal-dialog modal-xl modal-dialog-scrollable" style="max-width: 94vw;">
                <div class="modal-content border-0 shadow">
                    {{-- Modal Header (e-Home theme) --}}
                    <div class="modal-header d-flex justify-content-between align-items-center py-2 px-3" style="background: #dde6ff !important; color: #1e3a8a !important;">
                        <div class="d-flex align-items-center gap-2 flex-wrap">
                            <i class="ri-file-text-line fs-5 text-white" style="color: #ffffff !important;"></i>
                            <h5 class="modal-title fw-bold text-white mb-0" id="claimDetailModalLabel" style="color: #ffffff !important;">
                                Claim Details (e-Home View)
                            </h5>
                            <span class="badge bg-light text-dark fw-bold ms-2" id="mExpIdBadge">Exp ID: -</span>
                            <span class="badge bg-white text-primary fw-semibold" id="mClaimNameBadge">Claim Type</span>
                            <span class="badge bg-info-subtle text-info-emphasis fw-normal" id="mEmpBadge">Employee</span>
                        </div>
                        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>

                    <div class="modal-body p-0">
                        {{-- Loading Spinner --}}
                        <div id="modalLoadingSpinner" class="text-center py-5">
                            <div class="spinner-border text-primary" role="status"></div>
                            <p class="text-muted mt-2 mb-0" style="font-size:13px;">Loading e-Home claim details & running policy simulation...</p>
                        </div>

                        <div id="modalContentBody" class="d-none">
                            {{-- ══ AUTO-VERIFY SIMULATION VERDICT BANNER ══ --}}
                            <div class="p-3 border-bottom" id="mVerdictBanner" style="background: #f8fafc;">
                                <div class="d-flex align-items-center justify-content-between flex-wrap gap-2 mb-2">
                                    <div class="d-flex align-items-center gap-2">
                                        <span class="badge" id="mDecisionBadge" style="font-size: 12px; padding: 5px 10px;">Decision</span>
                                        <span class="fw-bold text-dark" id="mVerdictTitle" style="font-size: 13px;">Policy Simulation Outcome</span>
                                    </div>
                                    <div class="d-flex align-items-center gap-3 flex-wrap" style="font-size: 13px;">
                                        <div><span class="text-muted">Claimed:</span> <strong class="text-dark" id="mClaimedAmt">₹0.00</strong></div>
                                        <div><span class="text-muted">Verified:</span> <strong class="text-success" id="mVerifiedAmt">₹0.00</strong></div>
                                        <div><span class="text-muted">Deduction / Cap:</span> <strong class="text-danger" id="mDeductionAmt">₹0.00</strong></div>
                                    </div>
                                </div>
                                <div class="p-2 bg-white rounded border d-flex align-items-start gap-2">
                                    <i class="ri-information-line text-primary mt-1"></i>
                                    <div style="font-size: 12px; line-height: 1.5;">
                                        <strong class="text-dark">Auto-Verify Audit Reason: </strong>
                                        <span class="text-muted" id="mAuditReason">-</span>
                                    </div>
                                </div>
                            </div>

                            {{-- ══ SPLIT E-HOME BODY: VIEWER ON LEFT (5 cols), CLAIM FORM ON RIGHT (7 cols) ══ --}}
                            <div class="row g-0 eh-inline-body" style="min-height: 520px;">
                                {{-- Left Column: Document & Proof Viewer --}}
                                <div class="col-lg-5 col-md-6 border-end bg-light d-flex flex-column" style="min-height: 520px;">
                                    <div class="d-flex align-items-center justify-content-between px-3 py-2 bg-white border-bottom" style="font-size: 12px;">
                                        <span class="fw-semibold text-primary">
                                            <i class="ri-attachment-line me-1"></i> Document / Bill Viewer
                                        </span>
                                        <span class="badge bg-secondary-subtle text-secondary" id="mFilesCountBadge">0 Files</span>
                                    </div>
                                    <div class="flex-grow-1 p-2 d-flex flex-column justify-content-center" id="ehViewerContainer" style="min-height: 440px;">
                                        <div class="text-muted text-center py-5">
                                            <div class="spinner-border spinner-border-sm me-2 text-primary"></div> Loading documents...
                                        </div>
                                    </div>
                                </div>

                                {{-- Right Column: Official e-Home Claim Detail Form --}}
                                <div class="col-lg-7 col-md-6 p-3 overflow-auto" id="ehClaimFormContainer" style="max-height: 650px;">
                                    <div class="text-muted text-center py-5">
                                        <div class="spinner-border spinner-border-sm me-2 text-primary"></div> Loading claim form...
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="modal-footer bg-light py-2 px-3 d-flex justify-content-between align-items-center">
                        <small class="text-muted" style="font-size: 11px;">
                            <i class="ri-shield-check-line text-success me-1"></i> Simulated against current active auto-verify rules. No database changes made.
                        </small>
                        <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Close</button>
                    </div>
                </div>
            </div>
        </div>

    </div>
</div>

<script>
function toggleGpsStatus(checked) {
    const badge = document.getElementById('gpsStatusBadge');
    const box = document.getElementById('gpsToggleBox');
    if (checked) {
        badge.className = 'badge bg-success';
        badge.innerText = 'GPS Check Active';
        box.className = 'p-3 mb-3 rounded border bg-success-subtle border-success';
    } else {
        badge.className = 'badge bg-secondary';
        badge.innerText = 'GPS Check Bypassed (Off)';
        box.className = 'p-3 mb-3 rounded border bg-light border-warning';
    }
}

function toggleOcrStatus(checked) {
    const badge = document.getElementById('ocrStatusBadge');
    const box = document.getElementById('ocrToggleBox');
    if (checked) {
        badge.className = 'badge bg-primary';
        badge.innerText = 'OCR Comparison Active';
        box.className = 'p-3 mb-3 rounded border bg-primary-subtle border-primary';
    } else {
        badge.className = 'badge bg-secondary';
        badge.innerText = 'OCR Comparison Bypassed (Off)';
        box.className = 'p-3 mb-3 rounded border bg-light border-secondary';
    }
}

function ehBuildFile(filePath, fileUrl) {
    if (!filePath) filePath = 'document';
    const ext = filePath.split('.').pop().toLowerCase();
    const type = ['jpg', 'jpeg', 'png', 'gif', 'bmp', 'webp', 'heic'].includes(ext)
        ? 'image/' + ext
        : (ext === 'pdf' ? 'application/pdf' : 'application/octet-stream');
    return { url: fileUrl, name: filePath, type: type };
}

function ehRenderViewer(containerEl, files, uid) {
    if (!containerEl) return;
    let idx = 0;

    function buildThumb(f, i) {
        if (f.type.startsWith('image/')) {
            return `<img alt="Bill or document image" src="${f.url}" class="eh-thumb" data-index="${i}"
                         style="width:52px;height:52px;object-fit:cover;cursor:pointer;border-radius:4px;border:2px solid ${i === 0 ? '#15648C' : '#dee2e6'};margin:2px;"
                         onerror="this.src='/custom/no_user.png'">`;
        }
        return `<div class="eh-thumb" data-index="${i}"
                     style="width:52px;height:52px;display:flex;align-items:center;justify-content:center;cursor:pointer;border-radius:4px;border:2px solid ${i === 0 ? '#15648C' : '#dee2e6'};background:#f8f9fa;margin:2px;font-size:22px;color:#c00;">
                    <i class="ri-file-pdf-line"></i></div>`;
    }

    function renderMain(f) {
        if (f.type.startsWith('image/')) {
            return `<div style="width:100%;height:100%;display:flex;align-items:center;justify-content:center;overflow:hidden;padding:8px;">
                        <img alt="Bill or document image" src="${f.url}" style="max-width:100%;max-height:460px;object-fit:contain;border-radius:4px;"
                             onerror="this.src='/custom/no_user.png'">
                    </div>`;
        }
        return `<iframe src="${f.url}" style="width:100%;height:460px;border:none;border-radius:4px;"></iframe>`;
    }

    const thumbsHtml = files.map((f, i) => buildThumb(f, i)).join('');

    containerEl.innerHTML = `
        <div style="display:flex;flex-direction:column;height:100%;padding:4px;">
            <div class="eh-main-view" id="eh-main-${uid}" style="flex:1;min-height:360px;display:flex;align-items:center;justify-content:center;background:#fff;border-radius:4px;border:1px solid #e2e8f0;">
                ${renderMain(files[0])}
            </div>
            <div style="display:flex;flex-wrap:wrap;align-items:center;gap:2px;padding-top:8px;border-top:1px solid #dee2e6;margin-top:8px;">
                ${thumbsHtml}
                <a href="${files[0].url}" target="_blank" download id="eh-download-link-${uid}"
                   style="width:52px;height:52px;display:flex;align-items:center;justify-content:center;border-radius:4px;border:1px solid #dee2e6;background:#f8f9fa;color:#15648C;font-size:18px;margin:2px;"
                   title="Download Active Document"><i class="ri-download-line"></i></a>
            </div>
        </div>
    `;

    const thumbs = containerEl.querySelectorAll('.eh-thumb');
    thumbs.forEach(thumb => {
        thumb.addEventListener('click', function() {
            const i = parseInt(this.getAttribute('data-index'), 10);
            thumbs.forEach(t => t.style.borderColor = '#dee2e6');
            this.style.borderColor = '#15648C';
            const mainView = document.getElementById(`eh-main-${uid}`);
            if (mainView && files[i]) {
                mainView.innerHTML = renderMain(files[i]);
            }
            const dl = document.getElementById(`eh-download-link-${uid}`);
            if (dl && files[i]) {
                dl.setAttribute('href', files[i].url);
            }
        });
    });
}

function inspectClaimDetails(expId) {
    if (!expId) return;
    const modalEl = document.getElementById('claimDetailModal');
    if (!modalEl) return;
    const modal = (window.bootstrap && window.bootstrap.Modal)
        ? window.bootstrap.Modal.getOrCreateInstance(modalEl)
        : null;
    if (modal) modal.show();

    const spinner = document.getElementById('modalLoadingSpinner');
    const content = document.getElementById('modalContentBody');
    if (spinner) spinner.classList.remove('d-none');
    if (content) content.classList.add('d-none');

    const simYear = document.getElementById('simYear');
    const yearId = simYear ? simYear.value : {{ $yearId }};
    const companyId = {{ $currentCompany === 0 ? 1 : $currentCompany }};

    // 1. Fetch Claim Details & Real-Time Policy Audit
    fetch(`{{ route('admin.settings.auto-verify.claim-details') }}?exp_id=${expId}&company_id=${companyId}&year_id=${yearId}`)
        .then(res => res.json())
        .then(data => {
            if (!data.success || !data.claim) {
                if (spinner) spinner.classList.add('d-none');
                alert(data.message || 'Could not load claim details.');
                return;
            }

            const c = data.claim;
            const ev = data.evaluation || {};

            // Header tags
            const expIdBadge = document.getElementById('mExpIdBadge');
            const claimNameBadge = document.getElementById('mClaimNameBadge');
            const empBadge = document.getElementById('mEmpBadge');
            if (expIdBadge) expIdBadge.innerText = `Exp ID: #${c.exp_id}`;
            if (claimNameBadge) claimNameBadge.innerText = `${c.claim_name} (${c.claim_code || '#' + c.claim_id})`;
            if (empBadge) empBadge.innerText = `${c.employee_name}`;

            // Amounts
            const claimed = Number(c.claimed_amount || 0);
            const verified = Number(ev.verified_amount !== undefined ? ev.verified_amount : c.verified_amount);
            const diff = Math.max(0, claimed - verified);

            const claimedEl = document.getElementById('mClaimedAmt');
            const verifiedEl = document.getElementById('mVerifiedAmt');
            const deductionEl = document.getElementById('mDeductionAmt');
            const reasonEl = document.getElementById('mAuditReason');
            if (claimedEl) claimedEl.innerText = '₹' + claimed.toFixed(2);
            if (verifiedEl) verifiedEl.innerText = '₹' + verified.toFixed(2);
            if (deductionEl) deductionEl.innerText = '₹' + diff.toFixed(2);
            if (reasonEl) reasonEl.innerText = ev.reason || 'Standard compliant claim';

            const badge = document.getElementById('mDecisionBadge');
            const vBanner = document.getElementById('mVerdictBanner');
            if (badge && vBanner) {
                if (ev.status === 'approved') {
                    badge.className = 'badge bg-success';
                    badge.innerText = '🟢 Auto-Approved';
                    vBanner.style.background = '#f0fdf4';
                } else if (ev.status === 'adjusted') {
                    badge.className = 'badge bg-warning text-dark';
                    badge.innerText = '🟡 Auto-Deducted / Capped';
                    vBanner.style.background = '#fffbeb';
                } else {
                    badge.className = 'badge bg-danger';
                    badge.innerText = '🔴 Review Flag';
                    vBanner.style.background = '#fef2f2';
                }
            }

            if (spinner) spinner.classList.add('d-none');
            if (content) content.classList.remove('d-none');

            // 2. Fetch e-Home Claim Detail Form
            const formContainer = document.getElementById('ehClaimFormContainer');
            if (formContainer) {
                formContainer.innerHTML = '<div class="text-muted text-center py-5"><div class="spinner-border spinner-border-sm me-2 text-primary"></div>Loading e-Home claim form...</div>';
                fetch(`/claim-detail?claim_id=${c.claim_id}&expid=${c.exp_id}&year_id=${yearId}&company_id=${companyId}`)
                    .then(res => res.json())
                    .then(res => {
                        const html = res.html || '<div class="alert alert-warning mb-0">No claim form detail available.</div>';
                        formContainer.innerHTML = html;
                    })
                    .catch(() => {
                        formContainer.innerHTML = '<div class="alert alert-danger m-3"><i class="ri-error-warning-line me-1"></i> Failed to load e-Home claim form view.</div>';
                    });
            }

            // 3. Fetch e-Home Document Files & Receipts
            const viewerContainer = document.getElementById('ehViewerContainer');
            if (viewerContainer) {
                viewerContainer.innerHTML = '<div class="text-muted text-center py-5"><div class="spinner-border spinner-border-sm me-2 text-primary"></div>Loading documents...</div>';
                fetch(`/get-uploaded-files/${c.exp_id}/${c.claim_id}?year_id=${yearId}&company_id=${companyId}`)
                    .then(res => res.json())
                    .then(res => {
                        let files = [];
                        if (res.data && res.data.uploaded_files) {
                            const raw = res.data.uploaded_files;
                            if (Array.isArray(raw)) {
                                files = raw.map(f => ehBuildFile(f.file_path, f.file_url));
                            } else {
                                Object.values(raw).forEach(cat => {
                                    (cat.files || []).forEach(f => files.push(ehBuildFile(f.file_path, f.file_url)));
                                });
                            }
                        }
                        if (!files.length && c.attachments && c.attachments.length) {
                            files = c.attachments.map(att => ehBuildFile(att, att.startsWith('http') ? att : `/storage/${att}`));
                        }

                        const countBadge = document.getElementById('mFilesCountBadge');
                        if (countBadge) countBadge.innerText = `${files.length} Files`;

                        if (!files.length) {
                            viewerContainer.innerHTML = '<div class="text-muted text-center py-5"><i class="ri-image-off-line fs-1 d-block mb-2 text-secondary"></i>No uploaded receipts or documents found for this claim.</div>';
                            return;
                        }
                        ehRenderViewer(viewerContainer, files, c.exp_id);
                    })
                    .catch(() => {
                        viewerContainer.innerHTML = '<div class="text-danger text-center py-4"><i class="ri-error-warning-line me-1"></i> Failed to load document attachments.</div>';
                    });
            }
        })
        .catch(err => {
            if (spinner) spinner.classList.add('d-none');
            alert('Error loading claim details: ' + err.message);
        });
}

// Form vs Chat toggle support inside e-Home claim view (pure vanilla delegation)
document.addEventListener('change', function(e) {
    if (e.target && e.target.classList.contains('view-toggle')) {
        const formSec = document.getElementById('punch-form-section');
        const chatSec = document.getElementById('chat-section');
        if (e.target.value === 'chat') {
            if (formSec) formSec.style.display = 'none';
            if (chatSec) chatSec.style.display = 'block';
        } else {
            if (chatSec) chatSec.style.display = 'none';
            if (formSec) formSec.style.display = 'block';
        }
    }
});

function executeDirectInspect() {
    const simDirectExpId = document.getElementById('simDirectExpId');
    const val = simDirectExpId ? simDirectExpId.value.trim() : '';
    if (val) {
        inspectClaimDetails(val);
    } else {
        alert('Please enter an Exp ID');
    }
}

function executeSimulation() {
    const btnRunSim = document.getElementById('btnRunSim');
    const simSpinner = document.getElementById('simSpinner');
    const simResultsContainer = document.getElementById('simResultsContainer');
    const simMonth = document.getElementById('simMonth');
    const simYear = document.getElementById('simYear');
    const simLimit = document.getElementById('simLimit');

    if (simSpinner) simSpinner.classList.remove('d-none');
    if (simResultsContainer) simResultsContainer.classList.add('d-none');
    if (btnRunSim) btnRunSim.disabled = true;

    const postData = {
        company_id: {{ $currentCompany === 0 ? 1 : $currentCompany }},
        month: simMonth ? simMonth.value : 4,
        year_id: simYear ? simYear.value : {{ $yearId }},
        limit: simLimit ? simLimit.value : 250
    };

    fetch("{{ route('admin.settings.auto-verify.simulate') }}", {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': '{{ csrf_token() }}'
        },
        body: JSON.stringify(postData)
    })
    .then(res => res.json())
    .then(data => {
        if (simSpinner) simSpinner.classList.add('d-none');
        if (btnRunSim) btnRunSim.disabled = false;

        if (data.success && data.summary) {
            const s = data.summary;
            document.getElementById('simTotal').innerText = s.total_evaluated || 0;
            document.getElementById('simApproved').innerText = s.approved || 0;
            document.getElementById('simAdjusted').innerText = s.adjusted || 0;
            document.getElementById('simFlagged').innerText = s.flagged || 0;
            document.getElementById('simApprovedAmt').innerText = '₹' + Number(s.approved_amount || 0).toLocaleString('en-IN', {minimumFractionDigits: 2});
            document.getElementById('simAdjustedAmt').innerText = '₹' + Number(s.adjusted_amount || 0).toLocaleString('en-IN', {minimumFractionDigits: 2});

            const tbody = document.getElementById('simDecisionsBody');
            if (tbody) {
                tbody.innerHTML = '';

                if (s.claims && s.claims.length > 0) {
                    s.claims.slice(0, 100).forEach(c => {
                        let badgeClass = 'bg-secondary';
                        let badgeText = c.status;
                        if (c.status === 'approved') {
                            badgeClass = 'bg-success';
                            badgeText = 'Approved';
                        } else if (c.status === 'adjusted') {
                            badgeClass = 'bg-warning text-dark';
                            badgeText = 'Auto-Deducted';
                        } else if (c.status === 'flagged') {
                            badgeClass = 'bg-danger';
                            badgeText = 'Review Flag';
                        }

                        const tr = document.createElement('tr');
                        tr.innerHTML = `
                            <td class="text-center">
                                <a href="javascript:void(0);" class="fw-bold text-primary text-decoration-underline" onclick="inspectClaimDetails(${c.exp_id})">#${c.exp_id}</a>
                            </td>
                            <td>Claim #${c.claim_id}</td>
                            <td class="text-end">₹${Number(c.claimed_amount).toFixed(2)}</td>
                            <td class="text-end fw-bold">₹${Number(c.verified_amount).toFixed(2)}</td>
                            <td class="text-center"><span class="badge ${badgeClass}">${badgeText}</span></td>
                            <td><small>${c.reason}</small></td>
                            <td class="text-center">
                                <button type="button" class="btn btn-xs btn-outline-primary py-0 px-2" style="font-size:11px;" onclick="inspectClaimDetails(${c.exp_id})">
                                    <i class="ri-eye-line me-1"></i>Details
                                </button>
                            </td>
                        `;
                        tbody.appendChild(tr);
                    });
                } else {
                    tbody.innerHTML = '<tr><td colspan="7" class="text-center text-muted py-3">No claims processed for this selection.</td></tr>';
                }
            }

            if (simResultsContainer) simResultsContainer.classList.remove('d-none');
        } else {
            alert(data.message || 'Simulation encountered an error');
        }
    })
    .catch(err => {
        if (simSpinner) simSpinner.classList.add('d-none');
        if (btnRunSim) btnRunSim.disabled = false;
        alert('Simulation failed: ' + err.message);
    });
}

document.addEventListener('DOMContentLoaded', function() {
    const simDirectExpId = document.getElementById('simDirectExpId');
    if (simDirectExpId) {
        simDirectExpId.addEventListener('keypress', function(e) {
            if (e.key === 'Enter') {
                executeDirectInspect();
            }
        });
    }

    const btnRunSim = document.getElementById('btnRunSim');
    if (btnRunSim) {
        btnRunSim.addEventListener('click', executeSimulation);
    }
});
</script>
@endsection
