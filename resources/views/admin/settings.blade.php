@extends('layouts.app')

@section('title', ucwords(str_replace('-', ' ', Request::path())))

@section('content')
    <div class="page-content">
        <div class="container-fluid">
            <div class="row">
                <div class="col-xxl-12">
                    <div class="card">
                        <div class="card-body">
                            <div class="row">
                                <!-- Sidebar Navigation -->
                                <div class="col-lg-3">
                                    <div class="nav nav-pills flex-column nav-pills-tab custom-verti-nav-pills text-center"
                                        role="tablist" aria-orientation="vertical">
                                        <a class="nav-link active" id="general-tab" data-bs-toggle="pill" href="#general"
                                            role="tab" aria-controls="general" aria-selected="true">
                                            <i class="ri-settings-3-line d-block fs-20 mb-1"></i> General
                                        </a>
                                        <a class="nav-link" id="security-tab" data-bs-toggle="pill" href="#security"
                                            role="tab" aria-controls="security" aria-selected="false">
                                            <i class="ri-lock-line d-block fs-20 mb-1"></i> Security
                                        </a>
                                        <a class="nav-link" id="integrations-tab" data-bs-toggle="pill" href="#integrations"
                                            role="tab" aria-controls="integrations" aria-selected="false">
                                            <i class="ri-plug-line d-block fs-20 mb-1"></i> Integrations
                                        </a>
                                        <a class="nav-link" id="performance-tab" data-bs-toggle="pill" href="#performance"
                                            role="tab" aria-controls="performance" aria-selected="false">
                                            <i class="ri-speed-up-line d-block fs-20 mb-1"></i> Performance
                                        </a>
                                        <a class="nav-link" id="notifications-tab" data-bs-toggle="pill"
                                            href="#notifications" role="tab" aria-controls="notifications"
                                            aria-selected="false">
                                            <i class="ri-notification-3-line d-block fs-20 mb-1"></i> Notifications
                                        </a>
                                        <a class="nav-link" id="appearance-tab" data-bs-toggle="pill" href="#appearance"
                                            role="tab" aria-controls="appearance" aria-selected="false">
                                            <i class="ri-palette-line d-block fs-20 mb-1"></i> Appearance
                                        </a>
                                        <a class="nav-link" id="database-tab" data-bs-toggle="pill" href="#database"
                                            role="tab" aria-controls="database" aria-selected="false">
                                            <i class="ri-database-2-line d-block fs-20 mb-1"></i> Database
                                        </a>
                                        <a class="nav-link" id="analytics-tab" data-bs-toggle="pill" href="#analytics"
                                            role="tab" aria-controls="analytics" aria-selected="false">
                                            <i class="ri-line-chart-line d-block fs-20 mb-1"></i> Analytics
                                        </a>
                                        <a class="nav-link" id="userActivity-tab" data-bs-toggle="pill" href="#userActivity"
                                            role="tab" aria-controls="userActivity" aria-selected="false">
                                            <i class="ri-line-chart-line d-block fs-20 mb-1"></i> User Activity
                                        </a>
                                        <a class="nav-link" id="backup-tab" data-bs-toggle="pill" href="#backup" role="tab"
                                            aria-controls="backup" aria-selected="false">
                                            <i class="ri-recycle-line d-block fs-20 mb-1"></i> Backup
                                        </a>
                                    </div>
                                </div>
                                <!-- Tab Content -->
                                <div class="col-lg-9">
                                    <div class="tab-content text-muted mt-3 mt-lg-0">
                                        <!-- General Tab -->
                                        <div class="tab-pane fade show active" id="general" role="tabpanel"
                                            aria-labelledby="general-tab">
                                            <h6>general_settings</h6>
                                            <form id="generalSettingsForm" enctype="multipart/form-data" method="POST"
                                                action="">
                                                @csrf
                                                <div class="row">
                                                    <div class="mb-3 col-md-4">
                                                        <label for="projectName" class="form-label">Project Name</label>
                                                        <input type="text" class="form-control" name="project_name"
                                                            id="projectName" placeholder="Enter project name" required>
                                                    </div>
                                                    <div class="mb-3 col-md-4">
                                                        <label for="timeZone" class="form-label">Time Zone</label>
                                                        <select class="form-select" id="timeZone" name="time_zone" required>
                                                            <option value="Asia/Kolkata">India Standard Time (UTC +5:30)
                                                            </option>
                                                            <option value="Asia/Tokyo">Japan Standard Time (UTC +9)
                                                            </option>
                                                            <option value="Asia/Bishkek">Kyrgyzstan Time (UTC +6)</option>
                                                            <option value="Asia/Krasnoyarsk">Krasnoyarsk Summer Time (UTC
                                                                +8)</option>
                                                        </select>
                                                    </div>
                                                    <div class="mb-3 col-md-4">
                                                        <label for="language" class="form-label">Default Language</label>
                                                        <select class="form-select" id="language" name="default_language"
                                                            required>
                                                            <option value="en">English</option>
                                                            <option value="es">Spanish</option>
                                                            <option value="fr">French</option>
                                                        </select>
                                                    </div>
                                                    <div class="mb-3 col-md-4">
                                                        <label for="siteUrl" class="form-label">Site URL</label>
                                                        <input type="url" class="form-control" id="siteUrl" name="site_url"
                                                            placeholder="Enter site URL" required>
                                                    </div>
                                                    <div class="mb-3 col-md-4">
                                                        <label for="contactInfo" class="form-label">Contact
                                                            Information</label>
                                                        <input type="text" class="form-control" id="contactInfo"
                                                            name="contact_info" placeholder="Enter contact info">
                                                    </div>
                                                    <div class="mb-3 col-md-4">
                                                        <label for="logo" class="form-label">Logo</label>
                                                        <input type="file" class="form-control" id="logo" name="logo"
                                                            accept="image/*">
                                                        <div id="logoPreviewContainer" class="mt-2">
                                                            <a href="#" id="viewLogoLink" class="d-none me-2">View/Zoom</a>
                                                            <a href="#" id="downloadLogoLink" class="d-none me-2"
                                                                download>Download</a>
                                                            <button type="button" id="deleteLogoBtn"
                                                                class="btn btn-danger btn-sm d-none">Delete</button>
                                                            <div id="imageViewer" class="mt-2 d-none">
                                                                <img id="logoLivePreview" src="" alt="Live Preview"
                                                                    style="max-width: 150px;">
                                                            </div>
                                                        </div>
                                                    </div>
                                                    <div class="mb-3 col-md-12">
                                                        <label for="siteDescription" class="form-label">Site
                                                            Description</label>
                                                        <textarea class="form-control" id="siteDescription"
                                                            name="site_description" placeholder="Enter site description"
                                                            rows="2"></textarea>
                                                    </div>
                                                    <div class="mb-3 col-md-12">
                                                        <button type="submit" class="btn btn-primary">Save
                                                            Changes</button>
                                                    </div>
                                                </div>
                                            </form>
                                        </div>
                                        <!-- Security Tab -->
                                        <div class="tab-pane fade" id="security" role="tabpanel"
                                            aria-labelledby="security-tab">
                                            <h6>Security Settings</h6>
                                            <form method="POST" action="">
                                                @csrf
                                                <div class="mb-3">
                                                    <label for="apiKey" class="form-label">API Key</label>
                                                    <input type="password" class="form-control" id="apiKey" name="api_key"
                                                        placeholder="Enter API key">
                                                </div>
                                                <div class="mb-3">
                                                    <label for="sessionTimeout" class="form-label">Session Timeout
                                                        (minutes)</label>
                                                    <input type="number" class="form-control" id="sessionTimeout"
                                                        name="session_timeout" value="15" min="1" required>
                                                </div>
                                                <div class="mb-3 form-check">
                                                    <input type="checkbox" class="form-check-input" id="enableEncryption"
                                                        name="enable_encryption">
                                                    <label class="form-check-label" for="enableEncryption">Enable Data
                                                        Encryption</label>
                                                </div>
                                                <button type="submit" class="btn btn-primary">Save Changes</button>
                                            </form>
                                        </div>
                                        <!-- Integrations Tab -->
                                        <div class="tab-pane fade" id="integrations" role="tabpanel"
                                            aria-labelledby="integrations-tab">
                                            <h6>Integrations Settings</h6>
                                            <form method="POST" action="">
                                                @csrf
                                                <div class="mb-3">
                                                    <label for="smtpHost" class="form-label">SMTP Host</label>
                                                    <input type="text" class="form-control" id="smtpHost" name="smtp_host"
                                                        placeholder="smtp.example.com">
                                                </div>
                                                <div class="mb-3">
                                                    <label for="smtpUsername" class="form-label">SMTP Username</label>
                                                    <input type="text" class="form-control" id="smtpUsername"
                                                        name="smtp_username" placeholder="Enter SMTP username">
                                                </div>
                                                <div class="mb-3">
                                                    <label for="webhookUrl" class="form-label">Webhook URL</label>
                                                    <input type="url" class="form-control" id="webhookUrl"
                                                        name="webhook_url" placeholder="https://example.com/webhook">
                                                </div>
                                                <button type="submit" class="btn btn-primary">Save Changes</button>
                                            </form>
                                        </div>
                                        <!-- Performance Tab -->
                                        <div class="tab-pane fade" id="performance" role="tabpanel"
                                            aria-labelledby="performance-tab">
                                            <h6>Performance Settings</h6>
                                            <form method="POST" action="">
                                                @csrf
                                                <div class="mb-3">
                                                    <label for="cacheTTL" class="form-label">Cache TTL (seconds)</label>
                                                    <input type="number" class="form-control" id="cacheTTL" name="cache_ttl"
                                                        value="3600" min="0" required>
                                                </div>
                                                <div class="mb-3">
                                                    <label for="rateLimit" class="form-label">Rate Limit
                                                        (requests/min)</label>
                                                    <input type="number" class="form-control" id="rateLimit"
                                                        name="rate_limit" value="100" min="1" required>
                                                </div>
                                                <button type="submit" class="btn btn-primary">Save Changes</button>
                                            </form>
                                        </div>
                                        <!-- Notifications Tab -->
                                        <div class="tab-pane fade" id="notifications" role="tabpanel"
                                            aria-labelledby="notifications-tab">
                                            <h6>Notifications Settings</h6>
                                            <form method="POST" action="">
                                                @csrf
                                                <div class="mb-3 form-check">
                                                    <input type="checkbox" class="form-check-input" id="adminAlerts"
                                                        name="admin_alerts">
                                                    <label class="form-check-label" for="adminAlerts">Enable Admin
                                                        Alerts</label>
                                                </div>
                                                <div class="mb-3">
                                                    <label for="notificationChannel" class="form-label">Notification
                                                        Channel</label>
                                                    <select class="form-select" id="notificationChannel"
                                                        name="notification_channel" required>
                                                        <option value="email">Email</option>
                                                        <option value="slack">Slack</option>
                                                        <option value="sms">SMS</option>
                                                    </select>
                                                </div>
                                                <button type="submit" class="btn btn-primary">Save Changes</button>
                                            </form>
                                        </div>
                                        <!-- Appearance Tab -->
                                        <div class="tab-pane fade" id="appearance" role="tabpanel"
                                            aria-labelledby="appearance-tab">
                                            <h6>Appearance Settings</h6>
                                            <form method="POST" action="">
                                                @csrf
                                                <div data-simplebar class="h-100">
                                                    <div class="p-1">
                                                        <h6 class="mb-0 fw-semibold text-uppercase">Layout</h6>
                                                        <p class="text-muted">Choose your layout</p>
                                                        <div class="row gy-3">
                                                            <div class="col-3">
                                                                <div class="form-check card-radio">
                                                                    <input id="customizer-layout01" name="data-layout"
                                                                        type="radio" value="vertical"
                                                                        class="form-check-input">
                                                                    <label
                                                                        class="form-check-label p-0 avatar-md w-100 material-shadow"
                                                                        for="customizer-layout01">
                                                                        <span class="d-flex gap-1 h-100">
                                                                            <span class="flex-shrink-0">
                                                                                <span
                                                                                    class="bg-light d-flex h-100 flex-column gap-1 p-1">
                                                                                    <span
                                                                                        class="d-block p-1 px-2 bg-primary-subtle rounded mb-2"></span>
                                                                                    <span
                                                                                        class="d-block p-1 px-2 pb-0 bg-primary-subtle"></span>
                                                                                    <span
                                                                                        class="d-block p-1 px-2 pb-0 bg-primary-subtle"></span>
                                                                                    <span
                                                                                        class="d-block p-1 px-2 pb-0 bg-primary-subtle"></span>
                                                                                </span>
                                                                            </span>
                                                                            <span class="flex-grow-1">
                                                                                <span class="d-flex h-100 flex-column">
                                                                                    <span
                                                                                        class="bg-light d-block p-1"></span>
                                                                                    <span
                                                                                        class="bg-light d-block p-1 mt-auto"></span>
                                                                                </span>
                                                                            </span>
                                                                        </span>
                                                                    </label>
                                                                </div>
                                                                <h5 class="fs-13 text-center mt-2">Vertical</h5>
                                                            </div>
                                                            <div class="col-3">
                                                                <div class="form-check card-radio">
                                                                    <input id="customizer-layout02" name="data-layout"
                                                                        type="radio" value="horizontal"
                                                                        class="form-check-input">
                                                                    <label
                                                                        class="form-check-label p-0 avatar-md w-100 material-shadow"
                                                                        for="customizer-layout02">
                                                                        <span class="d-flex h-100 flex-column gap-1">
                                                                            <span
                                                                                class="bg-light d-flex p-1 gap-1 align-items-center">
                                                                                <span
                                                                                    class="d-block p-1 bg-primary-subtle rounded me-1"></span>
                                                                                <span
                                                                                    class="d-block p-1 pb-0 px-2 bg-primary-subtle ms-auto"></span>
                                                                                <span
                                                                                    class="d-block p-1 pb-0 px-2 bg-primary-subtle"></span>
                                                                            </span>
                                                                            <span class="bg-light d-block p-1"></span>
                                                                            <span
                                                                                class="bg-light d-block p-1 mt-auto"></span>
                                                                        </span>
                                                                    </label>
                                                                </div>
                                                                <h5 class="fs-13 text-center mt-2">Horizontal</h5>
                                                            </div>
                                                            <div class="col-3">
                                                                <div class="form-check card-radio">
                                                                    <input id="customizer-layout03" name="data-layout"
                                                                        type="radio" value="twocolumn"
                                                                        class="form-check-input">
                                                                    <label
                                                                        class="form-check-label p-0 avatar-md w-100 material-shadow"
                                                                        for="customizer-layout03">
                                                                        <span class="d-flex gap-1 h-100">
                                                                            <span class="flex-shrink-0">
                                                                                <span
                                                                                    class="bg-light d-flex h-100 flex-column gap-1">
                                                                                    <span
                                                                                        class="d-block p-1 bg-primary-subtle mb-2"></span>
                                                                                    <span
                                                                                        class="d-block p-1 pb-0 bg-primary-subtle"></span>
                                                                                    <span
                                                                                        class="d-block p-1 pb-0 bg-primary-subtle"></span>
                                                                                    <span
                                                                                        class="d-block p-1 pb-0 bg-primary-subtle"></span>
                                                                                </span>
                                                                            </span>
                                                                            <span class="flex-shrink-0">
                                                                                <span
                                                                                    class="bg-light d-flex h-100 flex-column gap-1 p-1">
                                                                                    <span
                                                                                        class="d-block p-1 px-2 pb-0 bg-primary-subtle"></span>
                                                                                    <span
                                                                                        class="d-block p-1 px-2 pb-0 bg-primary-subtle"></span>
                                                                                    <span
                                                                                        class="d-block p-1 px-2 pb-0 bg-primary-subtle"></span>
                                                                                    <span
                                                                                        class="d-block p-1 px-2 pb-0 bg-primary-subtle"></span>
                                                                                </span>
                                                                            </span>
                                                                            <span class="flex-grow-1">
                                                                                <span class="d-flex h-100 flex-column">
                                                                                    <span
                                                                                        class="bg-light d-block p-1"></span>
                                                                                    <span
                                                                                        class="bg-light d-block p-1 mt-auto"></span>
                                                                                </span>
                                                                            </span>
                                                                        </span>
                                                                    </label>
                                                                </div>
                                                                <h5 class="fs-13 text-center mt-2">Two Column</h5>
                                                            </div>
                                                            <div class="col-3">
                                                                <div class="form-check card-radio">
                                                                    <input id="customizer-layout04" name="data-layout"
                                                                        type="radio" value="semibox"
                                                                        class="form-check-input">
                                                                    <label
                                                                        class="form-check-label p-0 avatar-md w-100 material-shadow"
                                                                        for="customizer-layout04">
                                                                        <span class="d-flex gap-1 h-100">
                                                                            <span class="flex-shrink-0 p-1">
                                                                                <span
                                                                                    class="bg-light d-flex h-100 flex-column gap-1 p-1">
                                                                                    <span
                                                                                        class="d-block p-1 px-2 bg-primary-subtle rounded mb-2"></span>
                                                                                    <span
                                                                                        class="d-block p-1 px-2 pb-0 bg-primary-subtle"></span>
                                                                                    <span
                                                                                        class="d-block p-1 px-2 pb-0 bg-primary-subtle"></span>
                                                                                    <span
                                                                                        class="d-block p-1 px-2 pb-0 bg-primary-subtle"></span>
                                                                                </span>
                                                                            </span>
                                                                            <span class="flex-grow-1">
                                                                                <span
                                                                                    class="d-flex h-100 flex-column pt-1 pe-2">
                                                                                    <span
                                                                                        class="bg-light d-block p-1"></span>
                                                                                    <span
                                                                                        class="bg-light d-block p-1 mt-auto"></span>
                                                                                </span>
                                                                            </span>
                                                                        </span>
                                                                    </label>
                                                                </div>
                                                                <h5 class="fs-13 text-center mt-2">Semi Box</h5>
                                                            </div>
                                                        </div>
                                                        <div class="form-check form-switch form-switch-md mb-3 mt-4">
                                                            <input type="checkbox" class="form-check-input"
                                                                id="sidebarUserProfile" name="sidebar_user_profile">
                                                            <label class="form-check-label" for="sidebarUserProfile">Sidebar
                                                                User Profile
                                                                Avatar</label>
                                                        </div>
                                                        <h6 class="mt-4 mb-0 fw-semibold text-uppercase">Theme</h6>
                                                        <p class="text-muted">Choose your suitable Theme.</p>
                                                        <div class="row">
                                                            @foreach ([['id' => 'customizer-theme01', 'value' => 'default', 'label' => 'Default'], ['id' => 'customizer-theme02', 'value' => 'saas', 'label' => 'Sass'], ['id' => 'customizer-theme03', 'value' => 'corporate', 'label' => 'Corporate'], ['id' => 'customizer-theme04', 'value' => 'galaxy', 'label' => 'Galaxy'], ['id' => 'customizer-theme05', 'value' => 'material', 'label' => 'Material'], ['id' => 'customizer-theme06', 'value' => 'creative', 'label' => 'Creative'], ['id' => 'customizer-theme07', 'value' => 'minimal', 'label' => 'Minimal'], ['id' => 'customizer-theme08', 'value' => 'modern', 'label' => 'Modern'], ['id' => 'customizer-theme09', 'value' => 'interactive', 'label' => 'Interactive'], ['id' => 'customizer-theme10', 'value' => 'classic', 'label' => 'Classic'], ['id' => 'customizer-theme11', 'value' => 'vintage', 'label' => 'Vintage']] as $theme)
                                                                <div class="col-3">
                                                                    <div class="form-check card-radio">
                                                                        <input id="{{ $theme['id'] }}" name="data-theme"
                                                                            type="radio" value="{{ $theme['value'] }}"
                                                                            class="form-check-input">
                                                                        <label class="form-check-label p-0"
                                                                            for="{{ $theme['id'] }}">
                                                                            <img src="{{ asset('assets/images/demos/' . $theme['value'] . '.png') }}"
                                                                                alt="" class="img-fluid">
                                                                        </label>
                                                                    </div>
                                                                    <h5 class="fs-13 text-center fw-medium mt-2">
                                                                        {{ $theme['label'] }}
                                                                    </h5>
                                                                </div>
                                                            @endforeach
                                                        </div>
                                                        <h6 class="mt-4 mb-0 fw-semibold text-uppercase">Color Scheme</h6>
                                                        <p class="text-muted">Choose Light or Dark Scheme.</p>
                                                        <div class="colorscheme-cardradio">
                                                            <div class="row">
                                                                <div class="col-3">
                                                                    <div class="form-check card-radio">
                                                                        <input class="form-check-input" type="radio"
                                                                            name="data-bs-theme" id="layout-mode-light"
                                                                            value="light">
                                                                        <label
                                                                            class="form-check-label p-0 avatar-md w-100 material-shadow"
                                                                            for="layout-mode-light">
                                                                            <span class="d-flex gap-1 h-100">
                                                                                <span class="flex-shrink-0">
                                                                                    <span
                                                                                        class="bg-light d-flex h-100 flex-column gap-1 p-1">
                                                                                        <span
                                                                                            class="d-block p-1 px-2 bg-primary-subtle rounded mb-2"></span>
                                                                                        <span
                                                                                            class="d-block p-1 px-2 pb-0 bg-primary-subtle"></span>
                                                                                        <span
                                                                                            class="d-block p-1 px-2 pb-0 bg-primary-subtle"></span>
                                                                                        <span
                                                                                            class="d-block p-1 px-2 pb-0 bg-primary-subtle"></span>
                                                                                    </span>
                                                                                </span>
                                                                                <span class="flex-grow-1">
                                                                                    <span class="d-flex h-100 flex-column">
                                                                                        <span
                                                                                            class="bg-light d-block p-1"></span>
                                                                                        <span
                                                                                            class="bg-light d-block p-1 mt-auto"></span>
                                                                                    </span>
                                                                                </span>
                                                                            </span>
                                                                        </label>
                                                                    </div>
                                                                    <h5 class="fs-13 text-center mt-2">Light</h5>
                                                                </div>
                                                                <div class="col-3">
                                                                    <div class="form-check card-radio dark">
                                                                        <input class="form-check-input" type="radio"
                                                                            name="data-bs-theme" id="layout-mode-dark"
                                                                            value="dark">
                                                                        <label
                                                                            class="form-check-label p-0 avatar-md w-100 bg-dark material-shadow"
                                                                            for="layout-mode-dark">
                                                                            <span class="d-flex gap-1 h-100">
                                                                                <span class="flex-shrink-0">
                                                                                    <span
                                                                                        class="bg-white bg-opacity-10 d-flex h-100 flex-column gap-1 p-1">
                                                                                        <span
                                                                                            class="d-block p-1 px-2 bg-white bg-opacity-10 rounded mb-2"></span>
                                                                                        <span
                                                                                            class="d-block p-1 px-2 pb-0 bg-white bg-opacity-10"></span>
                                                                                        <span
                                                                                            class="d-block p-1 px-2 pb-0 bg-white bg-opacity-10"></span>
                                                                                        <span
                                                                                            class="d-block p-1 px-2 pb-0 bg-white bg-opacity-10"></span>
                                                                                    </span>
                                                                                </span>
                                                                                <span class="flex-grow-1">
                                                                                    <span class="d-flex h-100 flex-column">
                                                                                        <span
                                                                                            class="bg-white bg-opacity-10 d-block p-1"></span>
                                                                                        <span
                                                                                            class="bg-white bg-opacity-10 d-block p-1 mt-auto"></span>
                                                                                    </span>
                                                                                </span>
                                                                            </span>
                                                                        </label>
                                                                    </div>
                                                                    <h5 class="fs-13 text-center mt-2">Dark</h5>
                                                                </div>
                                                            </div>
                                                        </div>
                                                        <div id="sidebar-visibility">
                                                            <h6 class="mt-4 mb-0 fw-semibold text-uppercase">Sidebar
                                                                Visibility</h6>
                                                            <p class="text-muted">Choose Show or Hidden sidebar.</p>
                                                            <div class="row">
                                                                <div class="col-3">
                                                                    <div class="form-check card-radio">
                                                                        <input class="form-check-input" type="radio"
                                                                            name="data-sidebar-visibility"
                                                                            id="sidebar-visibility-show" value="show">
                                                                        <label
                                                                            class="form-check-label p-0 avatar-md w-100 material-shadow"
                                                                            for="sidebar-visibility-show">
                                                                            <span class="d-flex gap-1 h-100">
                                                                                <span class="flex-shrink-0 p-1">
                                                                                    <span
                                                                                        class="bg-light d-flex h-100 flex-column gap-1 p-1">
                                                                                        <span
                                                                                            class="d-block p-1 px-2 bg-primary-subtle rounded mb-2"></span>
                                                                                        <span
                                                                                            class="d-block p-1 px-2 pb-0 bg-primary-subtle"></span>
                                                                                        <span
                                                                                            class="d-block p-1 px-2 pb-0 bg-primary-subtle"></span>
                                                                                        <span
                                                                                            class="d-block p-1 px-2 pb-0 bg-primary-subtle"></span>
                                                                                    </span>
                                                                                </span>
                                                                                <span class="flex-grow-1">
                                                                                    <span
                                                                                        class="d-flex h-100 flex-column pt-1 pe-2">
                                                                                        <span
                                                                                            class="bg-light d-block p-1"></span>
                                                                                        <span
                                                                                            class="bg-light d-block p-1 mt-auto"></span>
                                                                                    </span>
                                                                                </span>
                                                                            </span>
                                                                        </label>
                                                                    </div>
                                                                    <h5 class="fs-13 text-center mt-2">Show</h5>
                                                                </div>
                                                                <div class="col-3">
                                                                    <div class="form-check card-radio">
                                                                        <input class="form-check-input" type="radio"
                                                                            name="data-sidebar-visibility"
                                                                            id="sidebar-visibility-hidden" value="hidden">
                                                                        <label
                                                                            class="form-check-label p-0 avatar-md w-100 px-2 material-shadow"
                                                                            for="sidebar-visibility-hidden">
                                                                            <span class="d-flex gap-1 h-100">
                                                                                <span class="flex-grow-1">
                                                                                    <span
                                                                                        class="d-flex h-100 flex-column pt-1 px-2">
                                                                                        <span
                                                                                            class="bg-light d-block p-1"></span>
                                                                                        <span
                                                                                            class="bg-light d-block p-1 mt-auto"></span>
                                                                                    </span>
                                                                                </span>
                                                                            </span>
                                                                        </label>
                                                                    </div>
                                                                    <h5 class="fs-13 text-center mt-2">Hidden</h5>
                                                                </div>
                                                            </div>
                                                        </div>
                                                        <div id="layout-width">
                                                            <h6 class="mt-4 mb-0 fw-semibold text-uppercase">Layout Width
                                                            </h6>
                                                            <p class="text-muted">Choose Fluid or Boxed layout.</p>
                                                            <div class="row">
                                                                <div class="col-3">
                                                                    <div class="form-check card-radio">
                                                                        <input class="form-check-input" type="radio"
                                                                            name="data-layout-width" id="layout-width-fluid"
                                                                            value="fluid">
                                                                        <label
                                                                            class="form-check-label p-0 avatar-md w-100 material-shadow"
                                                                            for="layout-width-fluid">
                                                                            <span class="d-flex gap-1 h-100">
                                                                                <span class="flex-shrink-0">
                                                                                    <span
                                                                                        class="bg-light d-flex h-100 flex-column gap-1 p-1">
                                                                                        <span
                                                                                            class="d-block p-1 px-2 bg-primary-subtle rounded mb-2"></span>
                                                                                        <span
                                                                                            class="d-block p-1 px-2 pb-0 bg-primary-subtle"></span>
                                                                                        <span
                                                                                            class="d-block p-1 px-2 pb-0 bg-primary-subtle"></span>
                                                                                        <span
                                                                                            class="d-block p-1 px-2 pb-0 bg-primary-subtle"></span>
                                                                                    </span>
                                                                                </span>
                                                                                <span class="flex-grow-1">
                                                                                    <span class="d-flex h-100 flex-column">
                                                                                        <span
                                                                                            class="bg-light d-block p-1"></span>
                                                                                        <span
                                                                                            class="bg-light d-block p-1 mt-auto"></span>
                                                                                    </span>
                                                                                </span>
                                                                            </span>
                                                                        </label>
                                                                    </div>
                                                                    <h5 class="fs-13 text-center mt-2">Fluid</h5>
                                                                </div>
                                                                <div class="col-3">
                                                                    <div class="form-check card-radio">
                                                                        <input class="form-check-input" type="radio"
                                                                            name="data-layout-width" id="layout-width-boxed"
                                                                            value="boxed">
                                                                        <label
                                                                            class="form-check-label p-0 avatar-md w-100 px-2 material-shadow"
                                                                            for="layout-width-boxed">
                                                                            <span
                                                                                class="d-flex gap-1 h-100 border-start border-end">
                                                                                <span class="flex-shrink-0">
                                                                                    <span
                                                                                        class="bg-light d-flex h-100 flex-column gap-1 p-1">
                                                                                        <span
                                                                                            class="d-block p-1 px-2 bg-primary-subtle rounded mb-2"></span>
                                                                                        <span
                                                                                            class="d-block p-1 px-2 pb-0 bg-primary-subtle"></span>
                                                                                        <span
                                                                                            class="d-block p-1 px-2 pb-0 bg-primary-subtle"></span>
                                                                                        <span
                                                                                            class="d-block p-1 px-2 pb-0 bg-primary-subtle"></span>
                                                                                    </span>
                                                                                </span>
                                                                                <span class="flex-grow-1">
                                                                                    <span class="d-flex h-100 flex-column">
                                                                                        <span
                                                                                            class="bg-light d-block p-1"></span>
                                                                                        <span
                                                                                            class="bg-light d-block p-1 mt-auto"></span>
                                                                                    </span>
                                                                                </span>
                                                                            </span>
                                                                        </label>
                                                                    </div>
                                                                    <h5 class="fs-13 text-center mt-2">Boxed</h5>
                                                                </div>
                                                            </div>
                                                        </div>
                                                        <div id="layout-position">
                                                            <h6 class="mt-4 mb-0 fw-semibold text-uppercase">Layout
                                                                Position</h6>
                                                            <p class="text-muted">Choose Fixed or Scrollable Layout
                                                                Position.</p>
                                                            <div class="btn-group radio" role="group">
                                                                <input type="radio" class="btn-check"
                                                                    name="data-layout-position" id="layout-position-fixed"
                                                                    value="fixed">
                                                                <label class="btn btn-light w-sm"
                                                                    for="layout-position-fixed">Fixed</label>
                                                                <input type="radio" class="btn-check"
                                                                    name="data-layout-position"
                                                                    id="layout-position-scrollable" value="scrollable">
                                                                <label class="btn btn-light w-sm ms-0"
                                                                    for="layout-position-scrollable">Scrollable</label>
                                                            </div>
                                                        </div>
                                                        <h6 class="mt-4 mb-0 fw-semibold text-uppercase">Topbar Color</h6>
                                                        <p class="text-muted">Choose Light or Dark Topbar Color.</p>
                                                        <div class="row">
                                                            <div class="col-3">
                                                                <div class="form-check card-radio">
                                                                    <input class="form-check-input" type="radio"
                                                                        name="data-topbar" id="topbar-color-light"
                                                                        value="light">
                                                                    <label
                                                                        class="form-check-label p-0 avatar-md w-100 material-shadow"
                                                                        for="topbar-color-light">
                                                                        <span class="d-flex gap-1 h-100">
                                                                            <span class="flex-shrink-0">
                                                                                <span
                                                                                    class="bg-light d-flex h-100 flex-column gap-1 p-1">
                                                                                    <span
                                                                                        class="d-block p-1 px-2 bg-primary-subtle rounded mb-2"></span>
                                                                                    <span
                                                                                        class="d-block p-1 px-2 pb-0 bg-primary-subtle"></span>
                                                                                    <span
                                                                                        class="d-block p-1 px-2 pb-0 bg-primary-subtle"></span>
                                                                                    <span
                                                                                        class="d-block p-1 px-2 pb-0 bg-primary-subtle"></span>
                                                                                </span>
                                                                            </span>
                                                                            <span class="flex-grow-1">
                                                                                <span class="d-flex h-100 flex-column">
                                                                                    <span
                                                                                        class="bg-light d-block p-1"></span>
                                                                                    <span
                                                                                        class="bg-light d-block p-1 mt-auto"></span>
                                                                                </span>
                                                                            </span>
                                                                        </span>
                                                                    </label>
                                                                </div>
                                                                <h5 class="fs-13 text-center mt-2">Light</h5>
                                                            </div>
                                                            <div class="col-3">
                                                                <div class="form-check card-radio">
                                                                    <input class="form-check-input" type="radio"
                                                                        name="data-topbar" id="topbar-color-dark"
                                                                        value="dark">
                                                                    <label
                                                                        class="form-check-label p-0 avatar-md w-100 material-shadow"
                                                                        for="topbar-color-dark">
                                                                        <span class="d-flex gap-1 h-100">
                                                                            <span class="flex-shrink-0">
                                                                                <span
                                                                                    class="bg-light d-flex h-100 flex-column gap-1 p-1">
                                                                                    <span
                                                                                        class="d-block p-1 px-2 bg-primary-subtle rounded mb-2"></span>
                                                                                    <span
                                                                                        class="d-block p-1 px-2 pb-0 bg-primary-subtle"></span>
                                                                                    <span
                                                                                        class="d-block p-1 px-2 pb-0 bg-primary-subtle"></span>
                                                                                    <span
                                                                                        class="d-block p-1 px-2 pb-0 bg-primary-subtle"></span>
                                                                                </span>
                                                                            </span>
                                                                            <span class="flex-grow-1">
                                                                                <span class="d-flex h-100 flex-column">
                                                                                    <span
                                                                                        class="bg-primary d-block p-1"></span>
                                                                                    <span
                                                                                        class="bg-light d-block p-1 mt-auto"></span>
                                                                                </span>
                                                                            </span>
                                                                        </span>
                                                                    </label>
                                                                </div>
                                                                <h5 class="fs-13 text-center mt-2">Dark</h5>
                                                            </div>
                                                        </div>
                                                        <div id="sidebar-size">
                                                            <h6 class="mt-4 mb-0 fw-semibold text-uppercase">Sidebar Size
                                                            </h6>
                                                            <p class="text-muted">Choose a size of Sidebar.</p>
                                                            <div class="row">
                                                                <div class="col-3">
                                                                    <div class="form-check sidebar-setting card-radio">
                                                                        <input class="form-check-input" type="radio"
                                                                            name="data-sidebar-size"
                                                                            id="sidebar-size-default" value="lg">
                                                                        <label
                                                                            class="form-check-label p-0 avatar-md w-100 material-shadow"
                                                                            for="sidebar-size-default">
                                                                            <span class="d-flex gap-1 h-100">
                                                                                <span class="flex-shrink-0">
                                                                                    <span
                                                                                        class="bg-light d-flex h-100 flex-column gap-1 p-1">
                                                                                        <span
                                                                                            class="d-block p-1 px-2 bg-primary-subtle rounded mb-2"></span>
                                                                                        <span
                                                                                            class="d-block p-1 px-2 pb-0 bg-primary-subtle"></span>
                                                                                        <span
                                                                                            class="d-block p-1 px-2 pb-0 bg-primary-subtle"></span>
                                                                                        <span
                                                                                            class="d-block p-1 px-2 pb-0 bg-primary-subtle"></span>
                                                                                    </span>
                                                                                </span>
                                                                                <span class="flex-grow-1">
                                                                                    <span class="d-flex h-100 flex-column">
                                                                                        <span
                                                                                            class="bg-light d-block p-1"></span>
                                                                                        <span
                                                                                            class="bg-light d-block p-1 mt-auto"></span>
                                                                                    </span>
                                                                                </span>
                                                                            </span>
                                                                        </label>
                                                                    </div>
                                                                    <h5 class="fs-13 text-center mt-2">Default</h5>
                                                                </div>
                                                                <div class="col-3">
                                                                    <div class="form-check sidebar-setting card-radio">
                                                                        <input class="form-check-input" type="radio"
                                                                            name="data-sidebar-size"
                                                                            id="sidebar-size-compact" value="md">
                                                                        <label
                                                                            class="form-check-label p-0 avatar-md w-100 material-shadow"
                                                                            for="sidebar-size-compact">
                                                                            <span class="d-flex gap-1 h-100">
                                                                                <span class="flex-shrink-0">
                                                                                    <span
                                                                                        class="bg-light d-flex h-100 flex-column gap-1 p-1">
                                                                                        <span
                                                                                            class="d-block p-1 bg-primary-subtle rounded mb-2"></span>
                                                                                        <span
                                                                                            class="d-block p-1 pb-0 bg-primary-subtle"></span>
                                                                                        <span
                                                                                            class="d-block p-1 pb-0 bg-primary-subtle"></span>
                                                                                        <span
                                                                                            class="d-block p-1 pb-0 bg-primary-subtle"></span>
                                                                                    </span>
                                                                                </span>
                                                                                <span class="flex-grow-1">
                                                                                    <span class="d-flex h-100 flex-column">
                                                                                        <span
                                                                                            class="bg-light d-block p-1"></span>
                                                                                        <span
                                                                                            class="bg-light d-block p-1 mt-auto"></span>
                                                                                    </span>
                                                                                </span>
                                                                            </span>
                                                                        </label>
                                                                    </div>
                                                                    <h5 class="fs-13 text-center mt-2">Compact</h5>
                                                                </div>
                                                                <div class="col-3">
                                                                    <div class="form-check sidebar-setting card-radio">
                                                                        <input class="form-check-input" type="radio"
                                                                            name="data-sidebar-size" id="sidebar-size-small"
                                                                            value="sm">
                                                                        <label
                                                                            class="form-check-label p-0 avatar-md w-100 material-shadow"
                                                                            for="sidebar-size-small">
                                                                            <span class="d-flex gap-1 h-100">
                                                                                <span class="flex-shrink-0">
                                                                                    <span
                                                                                        class="bg-light d-flex h-100 flex-column gap-1">
                                                                                        <span
                                                                                            class="d-block p-1 bg-primary-subtle mb-2"></span>
                                                                                        <span
                                                                                            class="d-block p-1 pb-0 bg-primary-subtle"></span>
                                                                                        <span
                                                                                            class="d-block p-1 pb-0 bg-primary-subtle"></span>
                                                                                        <span
                                                                                            class="d-block p-1 pb-0 bg-primary-subtle"></span>
                                                                                    </span>
                                                                                </span>
                                                                                <span class="flex-grow-1">
                                                                                    <span class="d-flex h-100 flex-column">
                                                                                        <span
                                                                                            class="bg-light d-block p-1"></span>
                                                                                        <span
                                                                                            class="bg-light d-block p-1 mt-auto"></span>
                                                                                    </span>
                                                                                </span>
                                                                            </span>
                                                                        </label>
                                                                    </div>
                                                                    <h5 class="fs-13 text-center mt-2">Small (Icon View)
                                                                    </h5>
                                                                </div>
                                                                <div class="col-3">
                                                                    <div class="form-check sidebar-setting card-radio">
                                                                        <input class="form-check-input" type="radio"
                                                                            name="data-sidebar-size"
                                                                            id="sidebar-size-small-hover" value="sm-hover">
                                                                        <label
                                                                            class="form-check-label p-0 avatar-md w-100 material-shadow"
                                                                            for="sidebar-size-small-hover">
                                                                            <span class="d-flex gap-1 h-100">
                                                                                <span class="flex-shrink-0">
                                                                                    <span
                                                                                        class="bg-light d-flex h-100 flex-column gap-1">
                                                                                        <span
                                                                                            class="d-block p-1 bg-primary-subtle mb-2"></span>
                                                                                        <span
                                                                                            class="d-block p-1 pb-0 bg-primary-subtle"></span>
                                                                                        <span
                                                                                            class="d-block p-1 pb-0 bg-primary-subtle"></span>
                                                                                        <span
                                                                                            class="d-block p-1 pb-0 bg-primary-subtle"></span>
                                                                                    </span>
                                                                                </span>
                                                                                <span class="flex-grow-1">
                                                                                    <span class="d-flex h-100 flex-column">
                                                                                        <span
                                                                                            class="bg-light d-block p-1"></span>
                                                                                        <span
                                                                                            class="bg-light d-block p-1 mt-auto"></span>
                                                                                    </span>
                                                                                </span>
                                                                            </span>
                                                                        </label>
                                                                    </div>
                                                                    <h5 class="fs-13 text-center mt-2">Small Hover View
                                                                    </h5>
                                                                </div>
                                                            </div>
                                                        </div>
                                                        <div id="sidebar-view">
                                                            <h6 class="mt-4 mb-0 fw-semibold text-uppercase">Sidebar View
                                                            </h6>
                                                            <p class="text-muted">Choose Default or Detached Sidebar view.
                                                            </p>
                                                            <div class="row">
                                                                <div class="col-3">
                                                                    <div class="form-check sidebar-setting card-radio">
                                                                        <input class="form-check-input" type="radio"
                                                                            name="data-layout-style"
                                                                            id="sidebar-view-default" value="default">
                                                                        <label
                                                                            class="form-check-label p-0 avatar-md w-100 material-shadow"
                                                                            for="sidebar-view-default">
                                                                            <span class="d-flex gap-1 h-100">
                                                                                <span class="flex-shrink-0">
                                                                                    <span
                                                                                        class="bg-light d-flex h-100 flex-column gap-1 p-1">
                                                                                        <span
                                                                                            class="d-block p-1 px-2 bg-primary-subtle rounded mb-2"></span>
                                                                                        <span
                                                                                            class="d-block p-1 px-2 pb-0 bg-primary-subtle"></span>
                                                                                        <span
                                                                                            class="d-block p-1 px-2 pb-0 bg-primary-subtle"></span>
                                                                                        <span
                                                                                            class="d-block p-1 px-2 pb-0 bg-primary-subtle"></span>
                                                                                    </span>
                                                                                </span>
                                                                                <span class="flex-grow-1">
                                                                                    <span class="d-flex h-100 flex-column">
                                                                                        <span
                                                                                            class="bg-light d-block p-1"></span>
                                                                                        <span
                                                                                            class="bg-light d-block p-1 mt-auto"></span>
                                                                                    </span>
                                                                                </span>
                                                                            </span>
                                                                        </label>
                                                                    </div>
                                                                    <h5 class="fs-13 text-center mt-2">Default</h5>
                                                                </div>
                                                                <div class="col-3">
                                                                    <div class="form-check sidebar-setting card-radio">
                                                                        <input class="form-check-input" type="radio"
                                                                            name="data-layout-style"
                                                                            id="sidebar-view-detached" value="detached">
                                                                        <label
                                                                            class="form-check-label p-0 avatar-md w-100 material-shadow"
                                                                            for="sidebar-view-detached">
                                                                            <span class="d-flex h-100 flex-column">
                                                                                <span
                                                                                    class="bg-light d-flex p-1 gap-1 align-items-center px-2">
                                                                                    <span
                                                                                        class="d-block p-1 bg-primary-subtle rounded me-1"></span>
                                                                                    <span
                                                                                        class="d-block p-1 pb-0 px-2 bg-primary-subtle ms-auto"></span>
                                                                                    <span
                                                                                        class="d-block p-1 pb-0 px-2 bg-primary-subtle"></span>
                                                                                </span>
                                                                                <span class="d-flex gap-1 h-100 p-1 px-2">
                                                                                    <span class="flex-shrink-0">
                                                                                        <span
                                                                                            class="bg-light d-flex h-100 flex-column gap-1 p-1">
                                                                                            <span
                                                                                                class="d-block p-1 px-2 pb-0 bg-primary-subtle"></span>
                                                                                            <span
                                                                                                class="d-block p-1 px-2 pb-0 bg-primary-subtle"></span>
                                                                                            <span
                                                                                                class="d-block p-1 px-2 pb-0 bg-primary-subtle"></span>
                                                                                        </span>
                                                                                    </span>
                                                                                </span>
                                                                                <span
                                                                                    class="bg-light d-block p-1 mt-auto px-2"></span>
                                                                            </span>
                                                                        </label>
                                                                    </div>
                                                                    <h5 class="fs-13 text-center mt-2">Detached</h5>
                                                                </div>
                                                            </div>
                                                        </div>
                                                        <div id="sidebar-color-setting">
                                                            <h6 class="mt-4 mb-0 fw-semibold text-uppercase">Sidebar Color
                                                            </h6>
                                                            <p class="text-muted">Choose a color of Sidebar.</p>
                                                            <div class="row">
                                                                <div class="col-3">
                                                                    <div class="form-check sidebar-setting card-radio">
                                                                        <input class="form-check-input" type="radio"
                                                                            name="data-sidebar" id="sidebar-color-light"
                                                                            value="light">
                                                                        <label
                                                                            class="form-check-label p-0 avatar-md w-100 material-shadow"
                                                                            for="sidebar-color-light">
                                                                            <span class="d-flex gap-1 h-100">
                                                                                <span class="flex-shrink-0">
                                                                                    <span
                                                                                        class="bg-white border-end d-flex h-100 flex-column gap-1 p-1">
                                                                                        <span
                                                                                            class="d-block p-1 px-2 bg-primary-subtle rounded mb-2"></span>
                                                                                        <span
                                                                                            class="d-block p-1 px-2 pb-0 bg-primary-subtle"></span>
                                                                                        <span
                                                                                            class="d-block p-1 px-2 pb-0 bg-primary-subtle"></span>
                                                                                        <span
                                                                                            class="d-block p-1 px-2 pb-0 bg-primary-subtle"></span>
                                                                                    </span>
                                                                                </span>
                                                                                <span class="flex-grow-1">
                                                                                    <span class="d-flex h-100 flex-column">
                                                                                        <span
                                                                                            class="bg-light d-block p-1"></span>
                                                                                        <span
                                                                                            class="bg-light d-block p-1 mt-auto"></span>
                                                                                    </span>
                                                                                </span>
                                                                            </span>
                                                                        </label>
                                                                    </div>
                                                                    <h5 class="fs-13 text-center mt-2">Light</h5>
                                                                </div>
                                                                <div class="col-3">
                                                                    <div class="form-check sidebar-setting card-radio">
                                                                        <input class="form-check-input" type="radio"
                                                                            name="data-sidebar" id="sidebar-color-dark"
                                                                            value="dark">
                                                                        <label
                                                                            class="form-check-label p-0 avatar-md w-100 material-shadow"
                                                                            for="sidebar-color-dark">
                                                                            <span class="d-flex gap-1 h-100">
                                                                                <span class="flex-shrink-0">
                                                                                    <span
                                                                                        class="bg-primary d-flex h-100 flex-column gap-1 p-1">
                                                                                        <span
                                                                                            class="d-block p-1 px-2 bg-white bg-opacity-10 rounded mb-2"></span>
                                                                                        <span
                                                                                            class="d-block p-1 px-2 pb-0 bg-white bg-opacity-10"></span>
                                                                                        <span
                                                                                            class="d-block p-1 px-2 pb-0 bg-white bg-opacity-10"></span>
                                                                                        <span
                                                                                            class="d-block p-1 px-2 pb-0 bg-white bg-opacity-10"></span>
                                                                                    </span>
                                                                                </span>
                                                                                <span class="flex-grow-1">
                                                                                    <span class="d-flex h-100 flex-column">
                                                                                        <span
                                                                                            class="bg-light d-block p-1"></span>
                                                                                        <span
                                                                                            class="bg-light d-block p-1 mt-auto"></span>
                                                                                    </span>
                                                                                </span>
                                                                            </span>
                                                                        </label>
                                                                    </div>
                                                                    <h5 class="fs-13 text-center mt-2">Dark</h5>
                                                                </div>
                                                                <div class="col-3">
                                                                    <button
                                                                        class="btn btn-link avatar-md w-100 p-0 overflow-hidden border"
                                                                        type="button" data-bs-toggle="collapse"
                                                                        data-bs-target="#collapseBgGradient"
                                                                        aria-expanded="false"
                                                                        aria-controls="collapseBgGradient">
                                                                        <span class="d-flex gap-1 h-100">
                                                                            <span class="flex-shrink-0">
                                                                                <span
                                                                                    class="bg-vertical-gradient d-flex h-100 flex-column gap-1 p-1">
                                                                                    <span
                                                                                        class="d-block p-1 px-2 bg-white bg-opacity-10 rounded mb-2"></span>
                                                                                    <span
                                                                                        class="d-block p-1 px-2 pb-0 bg-white bg-opacity-10"></span>
                                                                                    <span
                                                                                        class="d-block p-1 px-2 pb-0 bg-white bg-opacity-10"></span>
                                                                                    <span
                                                                                        class="d-block p-1 px-2 pb-0 bg-white bg-opacity-10"></span>
                                                                                </span>
                                                                            </span>
                                                                            <span class="flex-grow-1">
                                                                                <span class="d-flex h-100 flex-column">
                                                                                    <span
                                                                                        class="bg-light d-block p-1"></span>
                                                                                    <span
                                                                                        class="bg-light d-block p-1 mt-auto"></span>
                                                                                </span>
                                                                            </span>
                                                                        </span>
                                                                    </button>
                                                                    <h5 class="fs-13 text-center mt-2">Gradient</h5>
                                                                </div>
                                                            </div>
                                                            <div class="collapse" id="collapseBgGradient">
                                                                <div
                                                                    class="d-flex gap-2 flex-wrap img-switch p-2 px-3 bg-light rounded">
                                                                    @foreach ([['id' => 'sidebar-color-gradient', 'value' => 'gradient'], ['id' => 'sidebar-color-gradient-2', 'value' => 'gradient-2'], ['id' => 'sidebar-color-gradient-3', 'value' => 'gradient-3'], ['id' => 'sidebar-color-gradient-4', 'value' => 'gradient-4']] as $gradient)
                                                                        <div class="form-check sidebar-setting card-radio">
                                                                            <input class="form-check-input" type="radio"
                                                                                name="data-sidebar" id="{{ $gradient['id'] }}"
                                                                                value="{{ $gradient['value'] }}">
                                                                            <label
                                                                                class="form-check-label p-0 avatar-xs rounded-circle"
                                                                                for="{{ $gradient['id'] }}">
                                                                                <span
                                                                                    class="avatar-title rounded-circle bg-vertical-{{ $gradient['value'] }}"></span>
                                                                            </label>
                                                                        </div>
                                                                    @endforeach
                                                                </div>
                                                            </div>
                                                        </div>
                                                        <div id="sidebar-img">
                                                            <h6 class="mt-4 mb-0 fw-semibold text-uppercase">Sidebar Images
                                                            </h6>
                                                            <p class="text-muted">Choose a image of Sidebar.</p>
                                                            <div class="d-flex gap-2 flex-wrap img-switch">
                                                                <div class="form-check sidebar-setting card-radio">
                                                                    <input class="form-check-input" type="radio"
                                                                        name="data-sidebar-image" id="sidebarimg-none"
                                                                        value="none">
                                                                    <label class="form-check-label p-0 avatar-sm h-auto"
                                                                        for="sidebarimg-none">
                                                                        <span
                                                                            class="avatar-md w-auto bg-light d-flex align-items-center justify-content-center">
                                                                            <i class="ri-close-fill fs-20"></i>
                                                                        </span>
                                                                    </label>
                                                                </div>
                                                                @foreach (['img-1', 'img-2', 'img-3', 'img-4'] as $img)
                                                                    <div class="form-check sidebar-setting card-radio">
                                                                        <input class="form-check-input" type="radio"
                                                                            name="data-sidebar-image"
                                                                            id="sidebarimg-{{ str_replace('-', '', $img) }}"
                                                                            value="{{ $img }}">
                                                                        <label class="form-check-label p-0 avatar-sm h-auto"
                                                                            for="sidebarimg-{{ str_replace('-', '', $img) }}">
                                                                            <img src="{{ asset('assets/images/sidebar/' . $img . '.jpg') }}"
                                                                                alt=""
                                                                                class="avatar-md w-auto object-fit-cover">
                                                                        </label>
                                                                    </div>
                                                                @endforeach
                                                            </div>
                                                        </div>
                                                        <div id="primary-color">
                                                            <h6 class="mt-4 mb-0 fw-semibold text-uppercase">Primary Color
                                                            </h6>
                                                            <p class="text-muted">Choose a color of Primary.</p>
                                                            <div class="d-flex flex-wrap gap-2">
                                                                @foreach ([['id' => 'themeColor-01', 'value' => 'default'], ['id' => 'themeColor-02', 'value' => 'green'], ['id' => 'themeColor-03', 'value' => 'purple'], ['id' => 'themeColor-04', 'value' => 'blue']] as $color)
                                                                    <div class="form-check sidebar-setting card-radio">
                                                                        <input class="form-check-input" type="radio"
                                                                            name="data-theme-colors" id="{{ $color['id'] }}"
                                                                            value="{{ $color['value'] }}">
                                                                        <label class="form-check-label avatar-xs p-0"
                                                                            for="{{ $color['id'] }}">
                                                                            <span
                                                                                class="avatar-title rounded-circle bg-{{ $color['value'] }}"></span>
                                                                        </label>
                                                                    </div>
                                                                @endforeach
                                                            </div>
                                                        </div>
                                                        <div id="preloader-menu">
                                                            <h6 class="mt-4 mb-0 fw-semibold text-uppercase">Preloader</h6>
                                                            <p class="text-muted">Choose a preloader.</p>
                                                            <div class="row">
                                                                <div class="col-3">
                                                                    <div class="form-check sidebar-setting card-radio">
                                                                        <input class="form-check-input" type="radio"
                                                                            name="data-preloader" id="preloader-view-custom"
                                                                            value="enable">
                                                                        <label
                                                                            class="form-check-label p-0 avatar-md w-100 material-shadow"
                                                                            for="preloader-view-custom">
                                                                            <span class="d-flex gap-1 h-100">
                                                                                <span class="flex-shrink-0">
                                                                                    <span
                                                                                        class="bg-light d-flex h-100 flex-column gap-1 p-1">
                                                                                        <span
                                                                                            class="d-block p-1 px-2 bg-primary-subtle rounded mb-2"></span>
                                                                                        <span
                                                                                            class="d-block p-1 px-2 pb-0 bg-primary-subtle"></span>
                                                                                        <span
                                                                                            class="d-block p-1 px-2 pb-0 bg-primary-subtle"></span>
                                                                                        <span
                                                                                            class="d-block p-1 px-2 pb-0 bg-primary-subtle"></span>
                                                                                    </span>
                                                                                </span>
                                                                                <span class="flex-grow-1">
                                                                                    <span class="d-flex h-100 flex-column">
                                                                                        <span
                                                                                            class="bg-light d-block p-1"></span>
                                                                                        <span
                                                                                            class="bg-light d-block p-1 mt-auto"></span>
                                                                                    </span>
                                                                                </span>
                                                                            </span>
                                                                            <div id="status"
                                                                                class="d-flex align-items-center justify-content-center">
                                                                                <div class="spinner-border text-primary avatar-xxs m-auto"
                                                                                    role="status">
                                                                                    <span
                                                                                        class="visually-hidden">Loading...</span>
                                                                                </div>
                                                                            </div>
                                                                        </label>
                                                                    </div>
                                                                    <h5 class="fs-13 text-center mt-2">Enable</h5>
                                                                </div>
                                                                <div class="col-3">
                                                                    <div class="form-check sidebar-setting card-radio">
                                                                        <input class="form-check-input" type="radio"
                                                                            name="data-preloader" id="preloader-view-none"
                                                                            value="disable">
                                                                        <label
                                                                            class="form-check-label p-0 avatar-md w-100 material-shadow"
                                                                            for="preloader-view-none">
                                                                            <span class="d-flex gap-1 h-100">
                                                                                <span class="flex-shrink-0">
                                                                                    <span
                                                                                        class="bg-light d-flex h-100 flex-column gap-1 p-1">
                                                                                        <span
                                                                                            class="d-block p-1 px-2 bg-primary-subtle rounded mb-2"></span>
                                                                                        <span
                                                                                            class="d-block p-1 px-2 pb-0 bg-primary-subtle"></span>
                                                                                        <span
                                                                                            class="d-block p-1 px-2 pb-0 bg-primary-subtle"></span>
                                                                                        <span
                                                                                            class="d-block p-1 px-2 pb-0 bg-primary-subtle"></span>
                                                                                    </span>
                                                                                </span>
                                                                                <span class="flex-grow-1">
                                                                                    <span class="d-flex h-100 flex-column">
                                                                                        <span
                                                                                            class="bg-light d-block p-1"></span>
                                                                                        <span
                                                                                            class="bg-light d-block p-1 mt-auto"></span>
                                                                                    </span>
                                                                                </span>
                                                                            </span>
                                                                        </label>
                                                                    </div>
                                                                    <h5 class="fs-13 text-center mt-2">Disable</h5>
                                                                </div>
                                                            </div>
                                                        </div>
                                                        <div id="body-img">
                                                            <h6 class="mt-4 mb-0 fw-semibold text-uppercase">Background
                                                                Image</h6>
                                                            <p class="text-muted">Choose a body background image.</p>
                                                            <div class="row">
                                                                @foreach (['none', 'img-1', 'img-2', 'img-3'] as $img)
                                                                    <div class="col-3">
                                                                        <div class="form-check sidebar-setting card-radio">
                                                                            <input class="form-check-input" type="radio"
                                                                                name="data-body-image"
                                                                                id="body-img-{{ str_replace('-', '', $img) }}"
                                                                                value="{{ $img }}">
                                                                            <label class="form-check-label p-0 avatar-md w-100"
                                                                                data-body-image="{{ $img }}"
                                                                                for="body-img-{{ str_replace('-', '', $img) }}">
                                                                                @if ($img == 'none')
                                                                                    <span class="d-flex gap-1 h-100">
                                                                                        <span class="flex-shrink-0">
                                                                                            <span
                                                                                                class="bg-light d-flex h-100 flex-column gap-1 p-1">
                                                                                                <span
                                                                                                    class="d-block p-1 px-2 bg-primary-subtle rounded mb-2"></span>
                                                                                                <span
                                                                                                    class="d-block p-1 px-2 pb-0 bg-primary-subtle"></span>
                                                                                                <span
                                                                                                    class="d-block p-1 px-2 pb-0 bg-primary-subtle"></span>
                                                                                                <span
                                                                                                    class="d-block p-1 px-2 pb-0 bg-primary-subtle"></span>
                                                                                            </span>
                                                                                        </span>
                                                                                        <span class="flex-grow-1">
                                                                                            <span class="d-flex h-100 flex-column">
                                                                                                <span
                                                                                                    class="bg-light d-block p-1"></span>
                                                                                                <span
                                                                                                    class="bg-light d-block p-1 mt-auto"></span>
                                                                                            </span>
                                                                                        </span>
                                                                                    </span>
                                                                                @else
                                                                                    <img src="{{ asset('assets/images/body/' . $img . '.jpg') }}"
                                                                                        alt=""
                                                                                        class="avatar-md w-100 object-fit-cover">
                                                                                @endif
                                                                            </label>
                                                                        </div>
                                                                        <h5 class="fs-13 text-center mt-2">
                                                                            {{ ucfirst(str_replace('img-', '', $img)) }}
                                                                        </h5>
                                                                    </div>
                                                                @endforeach
                                                            </div>
                                                        </div>
                                                    </div>
                                                </div>
                                                <button type="button" id="save-theme-settings"
                                                    class="btn btn-primary mt-3">Save Settings</button>
                                            </form>
                                        </div>
                                        <!-- Database Tab -->
                                        <div class="tab-pane fade" id="database" role="tabpanel"
                                            aria-labelledby="database-tab">
                                            <h6>Database Settings</h6>
                                            <table class="table table-bordered">
                                                <thead>
                                                    <tr>
                                                        <th>ID</th>
                                                        <th>Company Name</th>
                                                        <th>Company Code</th>
                                                        <th>Action</th>
                                                    </tr>
                                                </thead>
                                                <tbody>
                                                    <!-- Populate dynamically via JavaScript or Blade -->
                                                </tbody>
                                            </table>
                                        </div>
                                        <!-- Backup Tab -->
                                        <div class="tab-pane fade" id="backup" role="tabpanel" aria-labelledby="backup-tab">
                                            <h6>Backup Settings</h6>
                                            <form method="POST" action="">
                                                @csrf
                                                <div class="mb-3">
                                                    <label for="dbHost" class="form-label">Database Host</label>
                                                    <input type="text" class="form-control" id="dbHost" name="db_host"
                                                        placeholder="localhost" required>
                                                </div>
                                                <div class="mb-3">
                                                    <label for="backupSchedule" class="form-label">Backup Schedule</label>
                                                    <select class="form-select" id="backupSchedule" name="backup_schedule"
                                                        required>
                                                        <option value="daily">Daily</option>
                                                        <option value="weekly">Weekly</option>
                                                        <option value="monthly">Monthly</option>
                                                    </select>
                                                </div>
                                                <button type="submit" class="btn btn-primary">Save Changes</button>
                                            </form>
                                        </div>
                                        <!-- Analytics Tab -->
                                        <div class="tab-pane fade" id="analytics" role="tabpanel"
                                            aria-labelledby="analytics-tab">
                                            <h6>Analytics Settings</h6>
                                            <form method="POST" action="">
                                                @csrf
                                                <div class="mb-3 form-check">
                                                    <input type="checkbox" class="form-check-input" id="enableTracking"
                                                        name="enable_tracking">
                                                    <label class="form-check-label" for="enableTracking">Enable Usage
                                                        Tracking</label>
                                                </div>
                                                <div class="mb-3">
                                                    <label for="analyticsKey" class="form-label">Analytics Key</label>
                                                    <input type="password" class="form-control" id="analyticsKey"
                                                        name="analytics_key" placeholder="Enter analytics key">
                                                </div>
                                                <button type="submit" class="btn btn-primary">Save Changes</button>
                                            </form>
                                        </div>

                                        <!-- User Activity Tab -->
                                        <div class="tab-pane fade" id="userActivity" role="tabpanel"
                                            aria-labelledby="userActivity-tab">
                                            <h6>User Activity Settings</h6>
                                            <form method="POST" action="">
                                                @csrf
                                                <div class="mb-3 form-check">
                                                    <input type="checkbox" class="form-check-input" id="enableUserActivity"
                                                        name="enable_user_activity">
                                                    <label class="form-check-label" for="enableUserActivity">Enable User Activity
                                                        Logging</label>
                                                </div>
                                            </form>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Database Configuration Modal -->
    <div class="modal fade" id="configModal" tabindex="-1" aria-labelledby="configModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <!-- Progress Bar -->
                <div class="progress-container d-none">
                    <div class="progress" style="height: 5px;">
                        <div class="progress-bar indeterminate" style="background-color: var(--vz-primary);"></div>
                    </div>
                </div>
                <!-- Modal Header -->
                <div class="modal-header">
                    <h5 class="modal-title" id="configModalLabel">Database Configuration <span id="modalCompanyName"></span>
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <!-- Modal Body -->
                <div class="modal-body">
                    <!-- Tabs Navigation -->
                    <ul class="nav nav-tabs mb-3" id="dbTabs" role="tablist">
                        <li class="nav-item" role="presentation">
                            <button class="nav-link active" id="hrims-tab" data-bs-toggle="tab" data-bs-target="#hrims"
                                type="button" role="tab" aria-controls="hrims" aria-selected="true">HRIMS</button>
                        </li>
                        <li class="nav-item" role="presentation">
                            <button class="nav-link" id="expense-tab" data-bs-toggle="tab" data-bs-target="#expense"
                                type="button" role="tab" aria-controls="expense" aria-selected="false">Expense</button>
                        </li>
                    </ul>
                    <!-- Tab Content -->
                    <div class="tab-content" id="dbTabContent">
                        <!-- HRIMS Tab -->
                        <div class="tab-pane fade show active" id="hrims" role="tabpanel" aria-labelledby="hrims-tab">
                            <form method="POST" class="mt-3" id="hrims-form" action="s') }}">
                                @csrf
                                <input type="hidden" name="company_id" id="hrims_company_id">
                                <input type="hidden" name="db_name" value="hrims">
                                <div class="row">
                                    <div class="col-md-6 mb-3">
                                        <label for="hrims_db_connection" class="form-label">Database Connection</label>
                                        <input type="text" name="db_connection" id="hrims_db_connection"
                                            class="form-control" value="mysql" required>
                                    </div>
                                    <div class="col-md-6 mb-3">
                                        <label for="hrims_db_host" class="form-label">Database Host</label>
                                        <input type="text" name="db_host" id="hrims_db_host" class="form-control"
                                            value="{{ env('DB_HOST', '127.0.0.1') }}" required>
                                    </div>
                                    <div class="col-md-6 mb-3">
                                        <label for="hrims_db_port" class="form-label">Database Port</label>
                                        <input type="number" name="db_port" id="hrims_db_port" class="form-control"
                                            value="3306" required>
                                    </div>
                                    <div class="col-md-6 mb-3">
                                        <label for="hrims_db_database" class="form-label">Database</label>
                                        <input type="text" name="db_database" id="hrims_db_database" class="form-control"
                                            value="hrims" required>
                                    </div>
                                    <div class="col-md-6 mb-3">
                                        <label for="hrims_db_username" class="form-label">Database Username</label>
                                        <input type="text" name="db_username" id="hrims_db_username" class="form-control"
                                            value="root" required>
                                    </div>
                                    <div class="col-md-6 mb-3">
                                        <label for="hrims_db_password" class="form-label">Database Password</label>
                                        <input type="password" name="db_password" id="hrims_db_password"
                                            class="form-control">
                                    </div>
                                    <div class="col-md-6 mb-3">
                                        <div class="form-check form-switch">
                                            <input class="form-check-input" name="is_active" type="checkbox" role="switch"
                                                id="hrims_is_active" checked>
                                            <label class="form-check-label" for="hrims_is_active"
                                                id="hrims_is_active_label">Active</label>
                                        </div>
                                    </div>
                                    <div class="col-lg-12">
                                        <div class="hstack gap-2 justify-content-end">
                                            <button type="submit"
                                                class="btn btn-primary btn-label waves-effect waves-light rounded-pill"
                                                id="hrims_save_config">
                                                <i
                                                    class="ri-check-double-line label-icon align-middle rounded-pill fs-16 me-2"></i>
                                                <span class="loader d-none"></span>
                                                Save Config
                                            </button>
                                        </div>
                                    </div>
                                </div>
                            </form>
                        </div>
                        <!-- Expense Tab -->
                        <div class="tab-pane fade" id="expense" role="tabpanel" aria-labelledby="expense-tab">
                            <form method="POST" class="mt-3" id="expense-form" action="nse') }}">
                                @csrf
                                <input type="hidden" name="company_id" id="expense_company_id">
                                <input type="hidden" name="db_name" value="expense">
                                <div class="row">
                                    <div class="col-md-6 mb-3">
                                        <label for="expense_db_connection" class="form-label">Database
                                            Connection</label>
                                        <input type="text" name="db_connection" id="expense_db_connection"
                                            class="form-control" value="mysql" required>
                                    </div>
                                    <div class="col-md-6 mb-3">
                                        <label for="expense_db_host" class="form-label">Database Host</label>
                                        <input type="text" name="db_host" id="expense_db_host" class="form-control"
                                            value="{{ env('DB_HOST', '127.0.0.1') }}" required>
                                    </div>
                                    <div class="col-md-6 mb-3">
                                        <label for="expense_db_port" class="form-label">Database Port</label>
                                        <input type="number" name="db_port" id="expense_db_port" class="form-control"
                                            value="3306" required>
                                    </div>
                                    <div class="col-md-6 mb-3">
                                        <label for="expense_db_database" class="form-label">Database</label>
                                        <input type="text" name="db_database" id="expense_db_database" class="form-control"
                                            value="expense" required>
                                    </div>
                                    <div class="col-md-6 mb-3">
                                        <label for="expense_db_username" class="form-label">Database Username</label>
                                        <input type="text" name="db_username" id="expense_db_username" class="form-control"
                                            value="root" required>
                                    </div>
                                    <div class="col-md-6 mb-3">
                                        <label for="expense_db_password" class="form-label">Database Password</label>
                                        <input type="password" name="db_password" id="expense_db_password"
                                            class="form-control">
                                    </div>
                                    <div class="col-md-6 mb-3">
                                        <div class="form-check form-switch">
                                            <input class="form-check-input" name="is_active" type="checkbox" role="switch"
                                                id="expense_is_active" checked>
                                            <label class="form-check-label" for="expense_is_active"
                                                id="expense_is_active_label">Active</label>
                                        </div>
                                    </div>
                                    <div class="col-lg-12">
                                        <div class="hstack gap-2 justify-content-end">
                                            <button type="submit"
                                                class="btn btn-primary btn-label waves-effect waves-light rounded-pill"
                                                id="expense_save_config">
                                                <i
                                                    class="ri-check-double-line label-icon align-middle rounded-pill fs-16 me-2"></i>
                                                <span class="loader d-none"></span>
                                                Save Config
                                            </button>
                                        </div>
                                    </div>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Logo Preview Modal -->
    <div class="modal fade" id="logoModal" tabindex="-1" aria-labelledby="logoModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="logoModalLabel">Logo Preview</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body" id="viewerContainer" style="position: relative; overflow: auto;">
                    <img id="logoModalImage" src="" alt="Logo Preview"
                        style="max-width: 100%; max-height: 80vh; display: none;">
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                </div>
            </div>
        </div>
    </div>
@endsection

@push('scripts')
    <link href="{{ asset('vendor/cdn/viewerjs@1.11.3/dist/viewer.min.css') }}" rel="stylesheet">
    <script src="{{ asset('vendor/cdn/viewerjs@1.11.3/dist/viewer.min.js') }}"></script>
    <script src="{{ asset('custom/js/pages/setting.js') }}"></script>
@endpush