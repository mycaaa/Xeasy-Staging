@extends('layouts.app')
@section('content')
   <div class="page-content">
      <div class="container-fluid">
         <div class="card mb-3 shadow-sm sticky-card">
            <div class="card-body">
               <div class="d-flex flex-wrap justify-content-between align-items-center mb-1">
                  <div>
                     <h5 class="fw-bold">
                        Department Expense Analytics</h4>
                        <small class="text-muted mb-0">Data-driven expense monitoring and insights for financial
                           decisions</small>
                  </div>
                  <div class="d-flex gap-1 align-items-center">
                     <input type="text" class="form-control form-control-sm" data-provider="flatpickr"
                        data-date-format="Y-m-d" data-range-date="true" id="dateRange" style="width: 150px;"
                        placeholder="Select date range" aria-label="Select date range" value="">
                     <div class="dropdown form-select-sm w-auto d-inline-block position-relative"
                        style="min-width: 180px;">
                        <button class="dropdown-toggle form-control" type="button" style="padding: 4px;"
                           id="departmentDropdown" data-bs-toggle="dropdown" aria-expanded="false">
                           <span id="selectedDepartments">All Departments</span>
                        </button>
                        <ul class="dropdown-menu p-2" style="max-height: 200px; overflow-y: auto;"
                           aria-labelledby="departmentDropdown">
                           <li>
                              <div class="form-check">
                                 <input type="checkbox" class="form-check-input" id="selectAllDepartments" value="">
                                 <label class="form-check-label" for="selectAllDepartments">
                                    All Departments
                                 </label>
                              </div>
                           </li>
                           @foreach($departments as $department)
                              <li>
                                 <div class="form-check">
                                    <input type="checkbox" name="departments[]" class="form-check-input department-checkbox"
                                       id="department_{{ $department->id }}" value="{{ $department->id }}">
                                    <label class="form-check-label" for="department_{{ $department->id }}">
                                       {{ $department->department_name }}
                                    </label>
                                 </div>
                              </li>
                           @endforeach
                        </ul>
                     </div>
                     <select id="filterType" class="form-select form-select-sm w-auto">
                        <option value="all">All Types</option>
                        <option value="increased">Increased Expenses</option>
                        <option value="decreased">Decreased Expenses</option>
                        <option value="critical">Critical Changes (±50%)</option>
                     </select>
                     <select id="sortBy" class="form-select form-select-sm w-auto">
                        <option value="variation">Sort by Variation</option>
                        <option value="current">Sort by Current Year</option>
                        <option value="previous">Sort by Previous Year</option>
                     </select>
                     <button class="btn btn-sm btn-primary" data-bs-toggle="modal" data-bs-target="#exportModal">
                        <i class="ri-download-2-fill"></i> Export Reports
                     </button>
                  </div>
               </div>
               <div class="row text-center">
                  <div class="col border-end">
                     <i class="bi bi-currency-rupee text-primary fs-2 mb-2"></i>
                     <p class="text-muted mb-0">Total Previous Year</p>
                     <h5 id="totalPrevious" class="text-primary fw-bold mb-0">₹0</h5>
                  </div>
                  <div class="col border-end">
                     <i class="bi bi-currency-rupee text-secondary fs-2 mb-2"></i>
                     <p class="text-muted mb-0">Total Current Year</p>
                     <h5 id="totalCurrent" class="text-secondary fw-bold mb-0">₹0</h5>
                  </div>
                  <div class="col border-end">
                     <i id="variationIcon" class="bi bi-graph-up text-info fs-2 mb-2"></i>
                     <p class="text-muted mb-0">Overall Variation</p>
                     <h5 id="overallVariation" class="text-info fw-bold mb-0">0%</h5>
                  </div>
                  <div class="col">
                     <i class="bi bi-arrow-up-circle text-success fs-2 mb-2"></i>
                     <p class="text-muted mb-0">Increased Expenses</p>
                     <h5 id="increasedCount" class="text-success fw-bold mb-0">0</h5>
                  </div>
                  <div class="col border-end">
                     <i class="bi bi-arrow-down-circle text-danger fs-2 mb-2"></i>
                     <p class="text-muted mb-0">Decreased Expenses</p>
                     <h5 id="decreasedCount" class="text-danger fw-bold mb-0">0</h5>
                  </div>
                  <div class="col">
                     <i class="bi bi-arrow-up-circle text-warning fs-2 mb-2"></i>
                     <p class="text-muted mb-0">Critical Changes (±50%)</p>
                     <h5 id="criticalCount" class="text-warning fw-bold mb-0">0</h5>
                  </div>
               </div>
            </div>
         </div>
         <div class="row">
            <div class="col-lg-6">
               <div class="card mb-3 shadow-sm">
                  <div class="card-body" style="height: 365px;">
                     <h5 class="mb-3">Department Expense Comparison</h5>
                     <canvas id="barChart"></canvas>
                  </div>
               </div>
            </div>
            <div class="col-lg-6">
               <div class="card mb-3 shadow-sm">
                  <div class="card-body" style="height: 365px;">
                     <h5 class="mb-3">Current Year Expense Distribution</h5>
                     <div style="max-width:400px; margin:auto;">
                        <canvas id="pieChart"></canvas>
                     </div>
                  </div>
               </div>
            </div>
         </div>
         <div class="card shadow-sm mb-3">
            <div class="card-body">
               <h5 class="mb-3">Detailed Department Analysis</h5>
               <div class="table-responsive">
                  <table class="table table-bordered" id="deptTable">
                     <thead class="table-light">
                        <tr>
                           <th colspan='2'>#</th>
                           <th class="text-start">Department</th>
                           <th class="text-end">Previous Year</th>
                           <th class="text-end">Current Year</th>
                           <th class="text-center">Variation</th>
                        </tr>
                     </thead>
                     <tbody></tbody>
                  </table>
               </div>
            </div>
         </div>
         <div class="card shadow-sm mb-3">
            <div class="card-body">
               <h5 class="mb-3">Monthly Trend Analysis</h5>
               <div style="height:300px;">
                  <canvas id="lineChart"></canvas>
               </div>
            </div>
         </div>
         <div class="card shadow-sm">
            <div class="card-body">
               <div class="align-items-center d-flex mb-2">
                  <h5 class="flex-grow-1">Top Expense Submitters Analysis</h5>
               </div>
               <div class="d-flex gap-3">
                  <div class="flex-fill">
                     <h6 class="text-center">Top Expense Submitters</h6>
                     <div class="table-responsive">
                        <table class="table table-sm table-bordered table-striped table-hover" id="tblTopEmployees">
                           <thead class="table-light">
                              <tr>
                                 <th>#</th>
                                 <th class="text-start">Employee</th>
                                 <th>Department</th>
                                 <th class="text-end">Count</th>
                              </tr>
                           </thead>
                           <tbody></tbody>
                        </table>
                     </div>
                  </div>
                  <div class="flex-fill">
                     <h6 class="text-center">Same-Day Submissions</h6>
                     <div class="table-responsive">
                        <table class="table table-sm table-bordered table-striped table-hover" id="tblTopEmployeesSameDay">
                           <thead class="table-light">
                              <tr>
                                 <th>#</th>
                                 <th class="text-start">Employee</th>
                                 <th>Department</th>
                                 <th class="text-end">Count</th>
                              </tr>
                           </thead>
                           <tbody></tbody>
                        </table>
                     </div>
                  </div>
                  <div class="flex-fill">
                     <h6 class="text-center">Delayed Submissions</h6>
                     <div class="table-responsive">
                        <table class="table table-sm table-bordered table-striped table-hover" id="tblTopEmployeesRevert">
                           <thead class="table-light">
                              <tr>
                                 <th>#</th>
                                 <th class="text-start">Employee</th>
                                 <th>Department</th>
                                 <th class="text-end">Count</th>
                              </tr>
                           </thead>
                           <tbody></tbody>
                        </table>
                     </div>
                  </div>
               </div>
            </div>
         </div>
         <div class="card shadow-sm">
            <div class="card-body">
               <div class="align-items-center d-flex mb-2">
                  <h5 class="flex-grow-1">Department wise Claim Type Analysis</h5>
               </div>
               <div id="claimTypeWiseContainer">
               </div>
            </div>
         </div>
      </div>
   </div>
   <div class="modal fade" id="exportModal" tabindex="-1" aria-labelledby="exportModalLabel" aria-hidden="true">
      <div class="modal-dialog modal-md">
         <div class="modal-content">
            <div class="progress-container" style="display: none;">
               <div class="progres" style="height: 5px;">
                  <div class="indeterminate" style="background-color: var(--vz-primary);"></div>
               </div>
            </div>
            <div class="modal-header">
               <h5 class="modal-title" id="exportModalLabel">Reports to Export</h5>
               <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
               <div class="mb-3">
                  <strong>Selected Date Range:</strong>
                  <span id="selectedDateRange">None</span>
               </div>
               <div class="d-flex justify-content-between">
                  <p class="mb-2">Choose the reports you want to export:</p>
                  <div class="form-check">
                     <input class="form-check-input" type="checkbox" id="checkAll">
                     <label class="form-check-label fw-bold" for="checkAll">All Reports</label>
                  </div>
               </div>
               <div class="form-check">
                  <input class="form-check-input report-option" type="checkbox" value="department_expense_comparison"
                     id="report1">
                  <label class="form-check-label" for="report1">Department Expense Comparison</label>
               </div>
               <div class="form-check">
                  <input class="form-check-input report-option" type="checkbox" value="current_year_expense_distribution"
                     id="report2">
                  <label class="form-check-label" for="report2">Current Year Expense Distribution</label>
               </div>

               <div class="form-check">
                  <input class="form-check-input report-option" type="checkbox" value="monthly_trend_analysis"
                     id="report3">
                  <label class="form-check-label" for="report3">Monthly Trend Analysis</label>
               </div>
               <div class="form-check">
                  <input class="form-check-input report-option" type="checkbox" value="department_claim_type_totals"
                     id="report4">
                  <label class="form-check-label" for="report4">Department wise Claim Type Analysis</label>
               </div>
               <small class="text-info"><strong>Note:</strong> If more than one
                  report is selected, the files will be
                  downloaded as a ZIP
                  archive.</small>
            </div>
            <div class="modal-footer">
               <div class="hstack gap-2 justify-content-end">
                  <button type="button" class="btn btn-primary btn-label waves-effect waves-light rounded-pill"
                     id="exportReports">
                     <i class="ri-check-double-line label-icon align-middle rounded-pill fs-16 me-2">
                        <span class="loader" style="display: none;"></span>
                     </i>
                     Export Selected
                  </button>
               </div>
            </div>
         </div>
      </div>
   </div>
@endsection
@push('styles')
   <link href="{{ asset('vendor/cdn/select2@4.1.0-rc.0/dist/css/select2.min.css') }}" rel="stylesheet" />
@endpush
@push('scripts')
   <script src="{{ asset('vendor/cdn/chart.js@4.6.1/dist/chart.umd.js') }}"></script>
   <script src="{{ asset('vendor/cdn/chartjs-plugin-datalabels@2.2.0/dist/chartjs-plugin-datalabels.min.js') }}"></script>
   <script src="{{ asset('custom/js/pages/department.js') }}"></script>
@endpush