@extends('layouts.app')

@section('content')
   <div class="page-content">
      <div class="container-fluid">
         <div class="card mb-3 shadow-sm sticky-card">
            <div class="card-body">
               <div class="d-flex flex-wrap justify-content-between align-items-center mb-1">
                  <div>
                     <h5 class="fw-bold">User Login Activity Analytics</h5>
                     <small class="text-muted mb-0">Monitor user authentication patterns and security insights for better
                        access management</small>
                  </div>
                  <div class="d-flex gap-1 align-items-center">
                     <input type="text" class="form-control form-control-sm" data-provider="flatpickr"
                        data-date-format="Y-m-d" data-range-date="true" id="dateRange" style="width: 200px;"
                        placeholder="Select date range" aria-label="Select date range" value="">
                     <select id="filterType" class="form-select form-select-sm w-auto">
                        <option value="all">All Logins</option>
                        <option value="token">Token Authentication</option>
                        <option value="normal">Normal Authentication</option>
                        <option value="multiple_ip">Multiple IP Users</option>
                     </select>
                     <select id="sortBy" class="form-select form-select-sm w-auto">
                        <option value="timestamp">Sort by Time</option>
                        <option value="user_id">Sort by User</option>
                        <option value="login_method">Sort by Method</option>
                     </select>
                  </div>
               </div>
               <div class="row text-center">
                  <div class="col border-end">
                     <i class="bi bi-person-check text-primary fs-2 mb-2"></i>
                     <p class="text-muted mb-0">Total Logins</p>
                     <h5 id="totalLogins" class="text-primary fw-bold mb-0">0</h5>
                  </div>
                  <div class="col border-end">
                     <i class="bi bi-people text-secondary fs-2 mb-2"></i>
                     <p class="text-muted mb-0">Unique Users</p>
                     <h5 id="uniqueUsers" class="text-secondary fw-bold mb-0">0</h5>
                  </div>
                  <div class="col border-end">
                     <i class="bi bi-shield-check text-info fs-2 mb-2"></i>
                     <p class="text-muted mb-0">Token Logins</p>
                     <h5 id="tokenLogins" class="text-info fw-bold mb-0">0</h5>
                  </div>
                  <div class="col border-end">
                     <i class="bi bi-geo-alt text-success fs-2 mb-2"></i>
                     <p class="text-muted mb-0">Unique IPs</p>
                     <h5 id="uniqueIPs" class="text-success fw-bold mb-0">0</h5>
                  </div>
                  <div class="col">
                     <i class="bi bi-exclamation-triangle text-danger fs-2 mb-2"></i>
                     <p class="text-muted mb-0">Multi-IP Users</p>
                     <h5 id="multiIPUsers" class="text-danger fw-bold mb-0">0</h5>
                  </div>
               </div>
            </div>
         </div>
         <div class="card">
            <div class="card-header d-flex align-items-center justify-content-between">
               <h4 class="card-title mb-0 flex-grow-1">User Activity Analytics</h4>
               <ul class="nav nav-pills card-header-pills" role="tablist">
                  <li class="nav-item" style="width:fit-content !important;" role="presentation">
                     <button class="nav-link active" id="hourly-tab" data-bs-toggle="tab" data-bs-target="#hourly-chart"
                        type="button" role="tab" aria-controls="hourly-chart" aria-selected="true">
                        <i class="bi bi-clock-history me-1"></i> Hourly Activity
                     </button>
                  </li>
                  <li class="nav-item" style="width:fit-content !important;" role="presentation">
                     <button class="nav-link" id="method-tab" data-bs-toggle="tab" data-bs-target="#method-chart"
                        type="button" role="tab" aria-controls="method-chart" aria-selected="false">
                        <i class="bi bi-shield-check me-1"></i> Auth Methods
                     </button>
                  </li>
                  <li class="nav-item" style="width:fit-content !important;" role="presentation">
                     <button class="nav-link" id="user-tab" data-bs-toggle="tab" data-bs-target="#user-chart" type="button"
                        role="tab" aria-controls="user-chart" aria-selected="false">
                        <i class="bi bi-people me-1"></i> User Comparison
                     </button>
                  </li>
                  <li class="nav-item" style="width:fit-content !important;" role="presentation">
                     <button class="nav-link" id="daily-tab" data-bs-toggle="tab" data-bs-target="#daily-chart"
                        type="button" role="tab" aria-controls="daily-chart" aria-selected="false">
                        <i class="bi bi-calendar3 me-1"></i> Daily Trends
                     </button>
                  </li>
               </ul>
            </div>

            <div class="card-body">
               <div class="tab-content text-muted">
                  <!-- Hourly Activity -->
                  <div class="tab-pane fade show active" id="hourly-chart" role="tabpanel" aria-labelledby="hourly-tab">
                     <div class="d-flex justify-content-between align-items-center mb-3">
                        <h6 class="mb-0">Login Activity by Hour</h6>
                        <small class="text-muted">Peak activity hours and patterns</small>
                     </div>
                     <div style="height: 250px;">
                        <canvas id="hourlyChart"></canvas>
                     </div>
                     <div class="row mt-3 text-center">
                        <div class="col-md-3">
                           <div class="border rounded p-2">
                              <small class="text-muted d-block">Peak Hour</small>
                              <strong id="peakHourDisplay">--</strong>
                           </div>
                        </div>
                        <div class="col-md-3">
                           <div class="border rounded p-2">
                              <small class="text-muted d-block">Morning (6-12)</small>
                              <strong id="morningLogins">0</strong>
                           </div>
                        </div>
                        <div class="col-md-3">
                           <div class="border rounded p-2">
                              <small class="text-muted d-block">Afternoon (12-18)</small>
                              <strong id="afternoonLogins">0</strong>
                           </div>
                        </div>
                        <div class="col-md-3">
                           <div class="border rounded p-2">
                              <small class="text-muted d-block">Evening (18-24)</small>
                              <strong id="eveningLogins">0</strong>
                           </div>
                        </div>
                     </div>
                  </div>

                  <!-- Auth Methods -->
                  <div class="tab-pane fade" id="method-chart" role="tabpanel" aria-labelledby="method-tab">
                     <div class="d-flex justify-content-between align-items-center mb-3">
                        <h6 class="mb-0">Authentication Method Distribution</h6>
                        <small class="text-muted">Token vs Normal authentication breakdown</small>
                     </div>
                     <div class="row">
                        <div class="col-md-8">
                           <div style="height: 250px;">
                              <canvas id="methodChart"></canvas>
                           </div>
                        </div>
                        <div class="col-md-4">
                           <div class="h-100 d-flex flex-column justify-content-center">
                              <div class="mb-3">
                                 <div class="d-flex align-items-center mb-2">
                                    <div class="bg-info rounded me-2" style="width: 20px; height: 20px;"></div>
                                    <span class="fw-bold">Token Authentication</span>
                                 </div>
                                 <div class="ps-4">
                                    <div class="d-flex justify-content-between"><small>Count:</small> <strong
                                          id="tokenCount">0</strong></div>
                                    <div class="d-flex justify-content-between"><small>Percentage:</small> <strong
                                          id="tokenPercentage">0%</strong></div>
                                 </div>
                              </div>
                              <div class="mb-3">
                                 <div class="d-flex align-items-center mb-2">
                                    <div class="bg-success rounded me-2" style="width: 20px; height: 20px;"></div>
                                    <span class="fw-bold">Normal Authentication</span>
                                 </div>
                                 <div class="ps-4">
                                    <div class="d-flex justify-content-between"><small>Count:</small> <strong
                                          id="normalCount">0</strong></div>
                                    <div class="d-flex justify-content-between"><small>Percentage:</small> <strong
                                          id="normalPercentage">0%</strong></div>
                                 </div>
                              </div>

                           </div>
                        </div>
                     </div>
                  </div>

                  <!-- User Comparison -->
                  <div class="tab-pane fade" id="user-chart" role="tabpanel" aria-labelledby="user-tab">
                     <div class="d-flex justify-content-between align-items-center mb-3">
                        <h6 class="mb-0">User Activity Comparison</h6>
                        <small class="text-muted">Top users by login frequency</small>
                     </div>
                     <div style="height: 250px;">
                        <canvas id="userChart"></canvas>
                     </div>
                     <div class="row mt-3">
                        <div class="col-md-12">
                           <div class="table-responsive">
                              <table class="table table-sm">
                                 <thead class="table-light">
                                    <tr>
                                       <th>Rank</th>
                                       <th>User</th>
                                       <th>Login Count</th>
                                       <th>Last Login</th>
                                       <th>Unique IPs</th>
                                    </tr>
                                 </thead>
                                 <tbody id="userRankingTable">
                                    <!-- Dynamic content -->
                                 </tbody>
                              </table>
                           </div>
                        </div>
                     </div>
                  </div>

                  <!-- Daily Trends -->
                  <div class="tab-pane fade" id="daily-chart" role="tabpanel" aria-labelledby="daily-tab">
                     <div class="d-flex justify-content-between align-items-center mb-3">
                        <h6 class="mb-0">Daily Login Trends</h6>
                        <small class="text-muted">Login activity over time</small>
                     </div>
                     <div style="height: 250px;">
                        <canvas id="dailyChart"></canvas>
                     </div>
                     <div class="row mt-3 text-center">
                        <div class="col-md-3">
                           <div class="border rounded p-2">
                              <small class="text-muted d-block">Highest Day</small>
                              <strong id="highestDay">--</strong>
                           </div>
                        </div>
                        <div class="col-md-3">
                           <div class="border rounded p-2">
                              <small class="text-muted d-block">Average per Day</small>
                              <strong id="averagePerDay">0</strong>
                           </div>
                        </div>
                        <div class="col-md-3">
                           <div class="border rounded p-2">
                              <small class="text-muted d-block">Total Days</small>
                              <strong id="totalDays">0</strong>
                           </div>
                        </div>
                        <div class="col-md-3">
                           <div class="border rounded p-2">
                              <small class="text-muted d-block">Growth Trend</small>
                              <strong id="growthTrend">--</strong>
                           </div>
                        </div>
                     </div>
                  </div>
               </div>
            </div>
         </div>

         <div class="card shadow-sm mb-3">
            <div class="card-body">
               <h5 class="mb-3">Detailed Login Analysis</h5>
               <div class="table-responsive">
                  <table class="table table-bordered" id="loginTable">
                     <thead class="table-light">
                        <tr>
                           <th>#</th>
                           <th>User</th>
                           <th>Employee ID</th>
                           <th>IP Address</th>
                           <th>Login Method</th>
                           <th>Timestamp</th>
                           <th>User Agent</th>
                           <th class="text-center">Status</th>
                        </tr>
                     </thead>
                     <tbody></tbody>
                  </table>
               </div>
            </div>
         </div>
      </div>
   </div>

   @push('scripts')
      <script src="{{ asset('vendor/cdn/chart.js@4.6.1/dist/chart.umd.js') }}"></script>
      <script src="{{ asset('vendor/cdn/chartjs-plugin-datalabels@2.2.0/dist/chartjs-plugin-datalabels.min.js') }}"></script>
      <script src="{{ asset('custom/js/pages/user_activity.js') }}"></script>
   @endpush
@endsection