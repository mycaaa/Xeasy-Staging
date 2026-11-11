@extends('layouts.app')

@section('title', 'Submission Deadline Settings')

@section('content')
<div class="container-fluid">
    <div class="row">
        <div class="col-md-8 mx-auto">
            <div class="card shadow-sm">
                <div class="card-header bg-primary text-white d-flex justify-content-between align-items-center">
                    <h5 class="mb-0">
                        <i class="fas fa-calendar-alt me-2"></i>
                        Expense Submission Deadline Settings
                    </h5>
                </div>
                <div class="card-body">
                    @if(session('success'))
                        <div class="alert alert-success alert-dismissible fade show" role="alert">
                            <i class="fas fa-check-circle me-2"></i>{{ session('success') }}
                            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                        </div>
                    @endif

                    <div class="alert alert-info">
                        <i class="fas fa-info-circle me-2"></i>
                        <strong>How it works:</strong> Employees can submit their previous month's expenses until the
                        specified day of the current month. After this day, submission is locked unless
                        <code>SubPermission</code> is set to 'Y' for that employee.
                    </div>

                    <form action="{{ route('admin.submission-settings.update') }}" method="POST">
                        @csrf
                        @method('PUT')

                        <div class="table-responsive">
                            <table class="table table-bordered table-hover">
                                <thead class="table-light">
                                    <tr>
                                        <th style="width:50px;">#</th>
                                        <th>Company</th>
                                        <th style="width:200px;">Last Submission Day</th>
                                        <th style="width:150px;">Last Updated</th>
                                        <th style="width:120px;">Updated By</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach($settings as $index => $setting)
                                    <tr>
                                        <td class="text-center">{{ $index + 1 }}</td>
                                        <td>
                                            <strong>{{ $setting->company_name }}</strong>
                                            <br>
                                            <small class="text-muted">Company ID: {{ $setting->company_id }}</small>
                                            <input type="hidden" name="settings[{{ $index }}][company_id]" value="{{ $setting->company_id }}">
                                        </td>
                                        <td>
                                            <div class="input-group">
                                                <input type="number"
                                                       class="form-control"
                                                       name="settings[{{ $index }}][last_submission_day]"
                                                       value="{{ $setting->last_submission_day }}"
                                                       min="1"
                                                       max="28"
                                                       required>
                                                <span class="input-group-text">of month</span>
                                            </div>
                                            <small class="text-muted">Employees can submit until this day</small>
                                        </td>
                                        <td>
                                            <small>{{ $setting->updated_at ? $setting->updated_at->format('d M Y, h:i A') : '-' }}</small>
                                        </td>
                                        <td>
                                            <small>{{ $setting->updated_by ?? '-' }}</small>
                                        </td>
                                    </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>

                        <div class="d-flex justify-content-end mt-3">
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-save me-2"></i>Save Settings
                            </button>
                        </div>
                    </form>
                </div>
            </div>

            {{-- Example explanation --}}
            <div class="card mt-3 shadow-sm">
                <div class="card-header bg-light">
                    <h6 class="mb-0"><i class="fas fa-question-circle me-2"></i>Example</h6>
                </div>
                <div class="card-body">
                    <p class="mb-1">If <strong>Last Submission Day = 15</strong> for VNR Seeds:</p>
                    <ul class="mb-0">
                        <li>May expenses can be submitted until <strong>15th June</strong></li>
                        <li>After 15th June, the Submit button is hidden for May</li>
                        <li>Employees with <code>SubPermission = 'Y'</code> can still submit anytime</li>
                    </ul>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
