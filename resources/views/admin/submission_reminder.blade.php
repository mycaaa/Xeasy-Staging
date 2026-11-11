@extends('layouts.app')
@section('content')
    <div class="page-content">
        <div class="container-fluid">
            <div class="row">
                <div class="col-lg-12">
                    <div class="card">
                        <div class="card-header align-items-center d-flex gap-2">
                            <h4 class="card-title mb-0 flex-grow-1">Submission Reminder Emails</h4>
                            <div class="flex-shrink-0 d-flex gap-2">
                                <button type="button" class="btn btn-primary btn-label waves-effect waves-light rounded-pill"
                                    id="sendEmails">
                                    <i class="ri-mail-send-line label-icon align-middle rounded-pill fs-16 me-2"></i>
                                    Send Emails
                                </button>
                                <button type="button"
                                    class="btn btn-secondary btn-label waves-effect waves-light rounded-pill"
                                    onclick="window.open('https://expensenew.vnrseeds.co.in/sentemails','_blank')">
                                    <i class="ri-file-list-3-line label-icon align-middle rounded-pill fs-16 me-2"></i>
                                    View Logs
                                </button>
                            </div>
                        </div>
                        <div class="card-body">
                            <ul class="nav nav-tabs mb-3" id="reminderTabs" role="tablist">
                                <li class="nav-item" role="presentation">
                                    <button class="nav-link active" id="submission-tab" data-bs-toggle="tab"
                                        data-bs-target="#submission-pane" type="button" role="tab">
                                        Expense Submission
                                    </button>
                                </li>
                                <li class="nav-item" role="presentation">
                                    <button class="nav-link" id="hardcopy-tab" data-bs-toggle="tab"
                                        data-bs-target="#hardcopy-pane" type="button" role="tab">
                                        Pending Hard-Copy
                                    </button>
                                </li>
                                <li class="nav-item" role="presentation">
                                    <button class="nav-link" id="escalation-tab" data-bs-toggle="tab"
                                        data-bs-target="#escalation-pane" type="button" role="tab">
                                        Escalation (Managers)
                                    </button>
                                </li>
                            </ul>
                            <div class="tab-content" id="reminderTabContent">
                                <div class="tab-pane fade show active" id="submission-pane" role="tabpanel">
                                    <table id="submissionReminderTable"
                                        class="table nowrap dt-responsive align-middle table-hover table-bordered"
                                        style="width: 100%;">
                                        <thead class="table-light">
                                            <tr>
                                                <th>S No.</th>
                                                <th>Employee Name</th>
                                                <th>Email</th>
                                                <th>Month</th>
                                                <th>Status</th>
                                                <th>Stage</th>
                                                <th>Email Subject</th>
                                                <th>Email Sent</th>
                                            </tr>
                                        </thead>
                                        <tbody></tbody>
                                    </table>
                                </div>
                                <div class="tab-pane fade" id="hardcopy-pane" role="tabpanel">
                                    <table id="hardcopyReminderTable"
                                        class="table nowrap dt-responsive align-middle table-hover table-bordered"
                                        style="width: 100%;">
                                        <thead class="table-light">
                                            <tr>
                                                <th>S No.</th>
                                                <th>Employee Name</th>
                                                <th>Email</th>
                                                <th>Month</th>
                                                <th>Status</th>
                                                <th>Stage</th>
                                                <th>Email Subject</th>
                                                <th>Email Sent</th>
                                            </tr>
                                        </thead>
                                        <tbody></tbody>
                                    </table>
                                </div>
                                <div class="tab-pane fade" id="escalation-pane" role="tabpanel">
                                    <table id="escalationTable"
                                        class="table nowrap dt-responsive align-middle table-hover table-bordered"
                                        style="width: 100%;">
                                        <thead class="table-light">
                                            <tr>
                                                <th>S No.</th>
                                                <th>Manager Email</th>
                                                <th>Employee Name</th>
                                                <th>Employee Code</th>
                                                <th>Month</th>
                                                <th>Submission Date</th>
                                                <th>Stage</th>
                                                <th>Email Subject</th>
                                                <th>Email Sent</th>
                                            </tr>
                                        </thead>
                                        <tbody></tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <div class="modal fade" id="sendEmailsModal" tabindex="-1" aria-labelledby="sendEmailsModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="progress-container" style="display: none;">
                    <div class="progres" style="height: 5px;">
                        <div class="indeterminate" style="background-color: var(--vz-primary);"></div>
                    </div>
                </div>
                <div class="modal-header">
                    <h5 class="modal-title" id="sendEmailsModalLabel">Confirm Send Reminder Emails</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <p>Are you sure you want to send reminder emails to all employees who haven't submitted their expense?</p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-primary btn-label waves-effect waves-light rounded-pill"
                        id="confirmSendEmailsBtn">
                        <i class="ri-check-double-line label-icon align-middle rounded-pill fs-16 me-2"><span class="loader"
                                style="display: none;"></span></i>
                        Confirm
                    </button>
                </div>
            </div>
        </div>
    </div>
@endsection
@push('scripts')
    <script src="{{ asset('custom/js/pages/submission_reminder.js') }}"></script>
@endpush
