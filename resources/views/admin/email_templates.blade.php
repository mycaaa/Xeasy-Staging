@extends('layouts.app')
@section('content')
    <div class="page-content pb-0">
        <div class="container-fluid">
            <div class="row">
                <div class="col-lg-12">
                    <div class="card">
                        <div class="card-header align-items-center d-flex">
                            <h4 class="card-title mb-0 flex-grow-1">Email Template List</h4>
                             <div class="dropdown card-header-dropdown">
                                <a class="text-reset dropdown-btn" href="#" data-bs-toggle="dropdown" aria-haspopup="true"
                                    aria-expanded="false">
                                    <span class="fw-semibold fs-12">Status: </span>
                                    <span class="text-muted">
                                        @if($status === 'active')
                                            Active
                                        @elseif($status === 'inactive')
                                            Inactive
                                        @else
                                            All
                                        @endif
                                        <i class="mdi mdi-chevron-down"></i>
                                    </span>
                                </a>
                                <div class="dropdown-menu dropdown-menu-end">
                                    <a class="dropdown-item" href="{{ url()->current() }}?status=all">All</a>
                                    <a class="dropdown-item" href="{{ url()->current() }}?status=active">Active</a>
                                    <a class="dropdown-item" href="{{ url()->current() }}?status=inactive">Inactive</a>
                                </div>
                            </div>
                            <div class="flex-shrink-0">
                                @can('new_email_template')
                                    <button type="button"
                                        class="btn btn-primary btn-label waves-effect waves-light rounded-pill"
                                        data-bs-toggle="modal" data-bs-target="#templateModal" id="templateBtn">
                                        <i class="ri-add-circle-fill label-icon align-middle rounded-pill fs-16 me-2"></i> Add New
                                    </button>
                                @endcan
                            </div>
                        </div>
                        <div class="card-body">
                            <table id="templateMasterTable"
                                class="table nowrap dt-responsive align-middle table-hover table-bordered"
                                style="width: 100%;">
                                <thead class="table-light">
                                    <tr>
                                        <th>S No.</th>
                                        <th style="text-align: left">Template Name</th>
                                        <th style="text-align: left">Subject</th>
                                        <th>Category</th>
                                        <th>Status</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @can('email_template_list')
                                        @foreach ($templates as $key => $template)
                                            <tr>
                                                <td>{{ $key + 1 }}</td>
                                                <td style="text-align: left">{{ $template->name }}</td>
                                                <td style="text-align: left">{{ $template->subject }}</td>
                                                <td>{{ $template->category ?? '-' }}</td>
                                                <td>
                                                    @if ($template->is_active == 1)
                                                        <span class="badge bg-success-subtle text-success badge-border">Active</span>
                                                    @else
                                                        <span class="badge bg-danger-subtle text-danger badge-border">Inactive</span>
                                                    @endif
                                                </td>
                                                <td>
                                                    @can('modify_email_template')
                                                        <button type="button" data-bs-toggle="modal" data-bs-target="#templateModal"
                                                            class="btn btn-primary btn-sm edit-template"
                                                            data-id="{{ $template->id }}"><i class="ri-edit-2-fill"></i></button>
                                                    @endcan
                                                    @can('email_template_activity_logs')
                                                        <button type="button" class="btn btn-info btn-sm view-logs"
                                                            data-id="{{ $template->id }}" data-bs-toggle="modal"
                                                            data-bs-target="#logModal"><i class="ri-file-list-3-fill"></i></button>
                                                    @endcan
                                                </td>
                                            </tr>
                                        @endforeach
                                    @else
                                        <tr>
                                            <td colspan="5" class="text-center">
                                                <span class="text-danger">You do not have permission to view the Email Template List.</span>
                                            </td>
                                        </tr>
                                    @endcan
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Template Modal -->
    <div class="modal fade" id="templateModal" tabindex="-1" aria-labelledby="templateModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="progress-container" style="display: none">
                    <div class="progres" style="height: 5px;">
                        <div class="indeterminate" style="background-color: var(--vz-primary);"></div>
                    </div>
                </div>
                <div class="modal-header">
                    <h5 class="modal-title" id="templateModalLabel">Add New Email Template</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <form id="templateForm">
                        <div class="row">
                            <div class="col-md-8">
                                <div class="mb-2">
                            <label for="name" class="form-label">Template Name</label>
                            <input type="text" class="form-control" id="name" name="name" required>
                        </div>
                            </div>
                            <div class="col-md-4">
                                 <div class="mb-2">
                            <label for="category" class="form-label">Category</label>
                            <select class="form-control" id="category" name="category">
                                <option value="">Select a category</option>
                                <option value="Support">Support / Queries</option>
                                <option value="Notifications">Notifications / Alerts</option>
                                <option value="Policy">Policy / Updates</option>
                            </select>
                        </div>
                            </div>
                        </div>
                       
                        <div class="mb-2">
                            <label for="subject" class="form-label">Subject</label>
                            <input type="text" class="form-control" id="subject" name="subject" required>
                        </div>
                         <div class="mb-2">
                            <label class="form-label">Available Variables</label>
                            <div id="variableList"></div>
                        </div>
                        <div class="mb-2">
                            <label for="body_html" class="form-label">Body</label>
                            <textarea class="form-control" id="body_html" name="body_html" rows="6" required></textarea>
                        </div>
                        
                        <div class="mb-2">
                            <div class="form-check form-switch">
                                <input class="form-check-input" name="is_active" type="checkbox" checked
                                    permission="switch" id="is_active" onchange="toggleSwitchText()" />
                                <label class="form-check-label" for="is_active" id="is_active_label">Active</label>
                            </div>
                        </div>
                         <div class="col-lg-12">
                           <div class="hstack gap-2 justify-content-end">
                               <button type="button" class="btn btn-primary btn-label waves-effect waves-light rounded-pill" id="saveTemplateBtn">
                                   <i class="ri-check-double-line label-icon align-middle rounded-pill fs-16 me-2">
                                       <span class="loader" style="display: none;"></span>
                                   </i>
                                   Submit
                               </button>
                           </div>
                       </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <!-- Notification Schedule Section -->
    <div class="row mt-3 p-4">
        <div class="col-lg-12">
            <div class="card">
                <div class="card-header align-items-center d-flex">
                    <h4 class="card-title mb-0 flex-grow-1" style="color:#15648C;">
                        <i class="ri-calendar-schedule-line me-1"></i>Email Notification Schedule
                    </h4>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-bordered table-sm align-middle" id="notificationTable">
                            <thead style="background:#dde6ff;color:#1e3a8a;">
                                <tr>
                                    <th>#</th>
                                    <th>Notification Type</th>
                                    <th>Stage</th>
                                    <th>Email Template</th>
                                    <th>Send Day (of month)</th>
                                    <th>Status</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody id="notificationBody">
                                <tr><td colspan="7" class="text-center py-3"><div class="spinner-border spinner-border-sm text-primary"></div> Loading...</td></tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Edit Notification Modal -->
    <div class="modal fade" id="editNotificationModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header" style="background:#dde6ff;color:#1e3a8a;">
                    <h5 class="modal-title text-white"><i class="ri-edit-line me-1"></i>Edit Notification Schedule</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <input type="hidden" id="editNotifId">
                    <div class="mb-3">
                        <label class="form-label fw-bold">Notification Type</label>
                        <input type="text" class="form-control" id="editNotifType" readonly style="background:#f8f9fa;">
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-bold">Stage</label>
                        <select class="form-select" id="editNotifStage">
                            <option value="1">Stage 1</option>
                            <option value="2">Stage 2</option>
                            <option value="3">Stage 3</option>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-bold">Email Template</label>
                        <select class="form-select" id="editNotifTemplate">
                            @foreach($templates as $t)
                                <option value="{{ $t->id }}">{{ $t->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-bold">Send Day (of month)</label>
                        <select class="form-select" id="editNotifDay">
                            @for($d = 1; $d <= 28; $d++)
                                <option value="{{ $d }}">{{ $d }}{{ $d == 1 ? 'st' : ($d == 2 ? 'nd' : ($d == 3 ? 'rd' : 'th')) }}</option>
                            @endfor
                        </select>
                    </div>
                    <div class="mb-3">
                        <div class="form-check form-switch">
                            <input class="form-check-input" type="checkbox" id="editNotifActive" checked>
                            <label class="form-check-label fw-bold" for="editNotifActive">Active</label>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="button" class="btn btn-primary" id="btnSaveNotification">
                        <i class="ri-save-line me-1"></i>Save
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- View Template Modal -->
    <div class="modal fade" id="viewTemplateModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header" style="background:#dde6ff;color:#1e3a8a;">
                    <h5 class="modal-title text-white"><i class="ri-eye-line me-1"></i>View Email Template</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-2">
                        <label class="form-label fw-bold">Template Name</label>
                        <p id="viewTemplateName" class="text-muted"></p>
                    </div>
                    <div class="mb-2">
                        <label class="form-label fw-bold">Subject</label>
                        <p id="viewTemplateSubject" class="text-muted"></p>
                    </div>
                    <div class="mb-2">
                        <label class="form-label fw-bold">Body</label>
                        <div id="viewTemplateBody" class="border rounded p-3" style="min-height:150px;background:#f8f9fa;"></div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                </div>
            </div>
        </div>
    </div>

    <!-- Log Modal -->
    <div class="modal fade" id="logModal" tabindex="-1" aria-labelledby="logModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-xl">
            <div class="modal-content">
                <div class="progress-container" style="display: none">
                    <div class="progres" style="height: 5px;">
                        <div class="indeterminate" style="background-color: var(--vz-primary);"></div>
                    </div>
                </div>
                <div class="modal-header">
                    <h5 class="modal-title" id="logModalLabel">Activity Logs for Email Template - <span id="logTemplateTitle"></span></h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <table class="table table-bordered table-striped" id="logTable">
                        <thead>
                            <tr>
                                <th>Event</th>
                                <th>Description</th>
                                <th>Changes</th>
                                <th>User</th>
                                <th>Date</th>
                            </tr>
                        </thead>
                        <tbody></tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
@endsection
@push('scripts')
<script src="{{ asset('custom/js/tinymce/tinymce.min.js') }}"></script>
    <script src="{{ asset('custom/js/pages/email_templates.js') }}"></script>
    <script>
    // Notification Schedule Management
    $(document).ready(function() {
        loadNotifications();
    });

    function loadNotifications() {
        $.get('{{ url("email-notification-schedule") }}', function(res) {
            if (!res.success || !res.data.length) {
                $('#notificationBody').html('<tr><td colspan="7" class="text-center text-muted py-3">No notification schedules found</td></tr>');
                return;
            }
            // Sort by Send Day ascending
            res.data.sort(function(a, b) {
                return a.send_day - b.send_day;
            });
            var html = '';
            res.data.forEach(function(n, i) {
                var statusBadge = n.is_active == 1
                    ? '<span class="badge bg-success">Active</span>'
                    : '<span class="badge bg-secondary">Inactive</span>';
                var typeName = (n.notification_type || '').replace(/_/g, ' ').replace(/\b\w/g, l => l.toUpperCase());
                html += '<tr>' +
                    '<td>' + (i+1) + '</td>' +
                    '<td>' + typeName + '</td>' +
                    '<td><span class="badge bg-info">Stage ' + n.stage + '</span></td>' +
                    '<td>' + (n.template_name || 'Template #' + n.email_template_id) + '</td>' +
                    '<td><strong>' + n.send_day + '</strong><sup>' + getDaySuffix(n.send_day) + '</sup> of month</td>' +
                    '<td>' + statusBadge + '</td>' +
                    '<td><button class="btn btn-sm btn-outline-info btn-view-template" data-template-id="' + n.email_template_id + '" title="View Template"><i class="ri-eye-line"></i></button> ' +
                    '<button class="btn btn-sm btn-outline-primary btn-edit-notif" data-id="' + n.id + '" data-type="' + n.notification_type + '" data-stage="' + n.stage + '" data-template="' + n.email_template_id + '" data-day="' + n.send_day + '" data-active="' + n.is_active + '"><i class="ri-edit-line"></i></button></td>' +
                    '</tr>';
            });
            $('#notificationBody').html(html);
        });
    }

    function getDaySuffix(d) {
        if (d == 1 || d == 21 || d == 31) return 'st';
        if (d == 2 || d == 22) return 'nd';
        if (d == 3 || d == 23) return 'rd';
        return 'th';
    }

    // View template from notification schedule
    $(document).on('click', '.btn-view-template', function() {
        var templateId = $(this).data('template-id');
        $.get('{{ url("email-template") }}/' + templateId + '/edit', function(res) {
            if (res.success && res.data) {
                $('#viewTemplateName').text(res.data.name || '-');
                $('#viewTemplateSubject').text(res.data.subject || '-');
                $('#viewTemplateBody').html(res.data.body_html || '<em>No content</em>');
                $('#viewTemplateModal').modal('show');
            } else {
                alert('Template not found');
            }
        }).fail(function() {
            alert('Failed to load template details');
        });
    });

    // Edit notification
    $(document).on('click', '.btn-edit-notif', function() {
        var $btn = $(this);
        $('#editNotifId').val($btn.data('id'));
        $('#editNotifType').val($btn.data('type').replace(/_/g, ' ').replace(/\b\w/g, l => l.toUpperCase()));
        $('#editNotifStage').val($btn.data('stage'));
        $('#editNotifTemplate').val($btn.data('template'));
        $('#editNotifDay').val($btn.data('day'));
        $('#editNotifActive').prop('checked', $btn.data('active') == 1);
        $('#editNotificationModal').modal('show');
    });

    // Save notification
    $('#btnSaveNotification').on('click', function() {
        var id = $('#editNotifId').val();
        $.ajax({
            url: '{{ url("email-notification-schedule") }}/' + id,
            method: 'PUT',
            data: {
                _token: '{{ csrf_token() }}',
                stage: $('#editNotifStage').val(),
                email_template_id: $('#editNotifTemplate').val(),
                send_day: $('#editNotifDay').val(),
                is_active: $('#editNotifActive').is(':checked') ? 1 : 0,
            },
            success: function(res) {
                $('#editNotificationModal').modal('hide');
                loadNotifications();
                if (typeof Swal !== 'undefined') {
                    Swal.fire({ toast: true, position: 'top-end', icon: 'success', title: 'Saved', showConfirmButton: false, timer: 1500 });
                }
            },
            error: function(xhr) {
                alert('Error: ' + (xhr.responseJSON?.message || 'Failed to save'));
            }
        });
    });
    </script>
@endpush