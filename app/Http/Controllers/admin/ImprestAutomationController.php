<?php

namespace App\Http\Controllers\admin;

use App\Http\Controllers\Controller;
use App\Services\Imprest\ImprestReleaseGuard;
use App\Services\ImprestAutomationService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Log;

/**
 * REQ-001: Imprest Automation — Admin Controller
 */
class ImprestAutomationController extends Controller
{
    public static function ensureTableExists(string $conn = 'mysql'): void
    {
        try {
            if (!Schema::connection($conn)->hasTable('imprest_requests')) {
                Schema::connection($conn)->create('imprest_requests', function (Blueprint $table) {
                    $table->id();
                    $table->unsignedBigInteger('employee_id');
                    $table->string('emp_code', 30)->nullable();
                    $table->string('emp_name', 150)->nullable();
                    $table->string('department', 100)->nullable();
                    $table->string('grade', 30)->nullable();
                    $table->string('trigger_type', 30);
                    $table->string('previous_grade', 30)->nullable();
                    $table->decimal('eligible_amount', 12, 2)->default(0);
                    $table->decimal('previous_amount', 12, 2)->default(0);
                    $table->decimal('request_amount', 12, 2)->default(0);
                    $table->string('status', 20)->default('pending');
                    $table->text('remarks')->nullable();
                    $table->string('approved_by', 100)->nullable();
                    $table->timestamp('approved_at')->nullable();
                    $table->timestamps();

                    $table->index('employee_id');
                    $table->index('status');
                    $table->index('trigger_type');
                    // UAT E-01: one request per employee and grade (see migration 2026_11_05_000006).
                    $table->unique(['employee_id', 'grade'], ImprestReleaseGuard::REQUEST_UNIQUE_INDEX);
                });
            }
            // Ensure is_imprest column exists on advance tables
            if (Schema::connection($conn)->hasTable('advance_requests') && !Schema::connection($conn)->hasColumn('advance_requests', 'is_imprest')) {
                Schema::connection($conn)->table('advance_requests', function (Blueprint $table) {
                    $table->boolean('is_imprest')->default(false)->index();
                });
            }
            if (Schema::connection($conn)->hasTable('advance_request_activities') && !Schema::connection($conn)->hasColumn('advance_request_activities', 'is_imprest')) {
                Schema::connection($conn)->table('advance_request_activities', function (Blueprint $table) {
                    $table->boolean('is_imprest')->default(false)->index();
                });
            }
        } catch (\Exception $e) {
            Log::warning('ImprestAutomationController::ensureTableExists failed on connection [' . $conn . ']: ' . $e->getMessage());
        }
    }

    /**
     * Tracker #36: every approve / reject / release is appended to
     * imprest_request_actions (migration 2026_11_05_000002). Actions are
     * refused while that table is missing, so no action goes unrecorded.
     */
    private function historyUnavailable(string $conn): ?\Illuminate\Http\JsonResponse
    {
        if (Schema::connection($conn)->hasTable('imprest_request_actions')) {
            return null;
        }
        Log::error("imprest_request_actions missing on [{$conn}]: run migration 2026_11_05_000002.");

        return response()->json(['success' => false, 'message' => 'Imprest action history is not set up on this server (migration 2026_11_05_000002 pending). No change was made.'], 503);
    }

    /** UAT E-01: releases and approvals are refused until the auto-advance guard (migration 2026_11_05_000006) is in place. */
    private function guardUnavailable(string $conn): ?\Illuminate\Http\JsonResponse
    {
        if (app(ImprestReleaseGuard::class)->linkTableExists($conn)) {
            return null;
        }
        Log::error('imprest_auto_advances missing on ['.$conn.']: run migration 2026_11_05_000006.');

        return response()->json(['success' => false, 'message' => 'Imprest auto-advance guard is not set up on this server (migration 2026_11_05_000006 pending). No change was made.'], 503);
    }

    private function concurrentActionResponse(): \Illuminate\Http\JsonResponse
    {
        return response()->json(['success' => false, 'message' => 'This imprest is being released or approved by another user at the same moment. Refresh the list: no second request or advance was created.'], 409);
    }

    /**
     * Create the system auto advance for an approved / released imprest
     * request, at most once per request. Must run inside the transaction
     * opened on $txConn (ImprestReleaseGuard::transactionConnection()).
     *
     * @return string created | exists_request | exists_month
     */
    private function createImprestAutoAdvance(string $txConn, int $imprestRequestId, int $employeeId, float $amount, string $purpose, string $approverRemark): string
    {
        $guard = app(ImprestReleaseGuard::class);
        if (! $guard->reserve($txConn, $imprestRequestId, $employeeId)) {
            return 'exists_request';
        }

        $currentMonth = (int) date('n');
        $yearId = session('year_id', date('Y'));

        // An imprest auto advance already raised for this employee this month (e.g. before this guard existed).
        $existingAdvance = \App\Models\AdvanceRequest::where('is_imprest', 1)
            ->where(function ($q) use ($employeeId) {
                $q->where('req_app_submit_id', $employeeId)
                  ->orWhereHas('employees', function ($eq) use ($employeeId) {
                      $eq->where('employee_id', $employeeId);
                  });
            })
            ->where('month', $currentMonth)
            ->first();
        if ($existingAdvance) {
            $guard->attach($txConn, $imprestRequestId, (int) $existingAdvance->id);

            return 'exists_month';
        }

        // Same connection as $txConn when both point at one database: this is a savepoint
        // inside the caller's transaction, so everything commits or rolls back together.
        $advanceId = DB::transaction(function () use ($yearId, $currentMonth, $employeeId, $amount, $purpose, $approverRemark) {
            $advanceRequest = \App\Models\AdvanceRequest::create([
                'year_id'           => $yearId,
                'advance_type_id'   => 2, // Auto Advance
                'is_imprest'        => 1, // Flagged as Imprest
                'activity_name_id'  => 0,
                'month'             => $currentMonth,
                'advance_fill_date' => now(),
                'current_status'    => \App\Models\AdvanceRequest::STATUS_FINANCE,
                'submission_no'     => 1,
                'last_submitted_at' => now(),
                'req_app_submit_id' => 0, // System Generated (not raised by employee or approver)
                'created_by'        => 0, // System Generated
                'updated_by'        => 0,
            ]);

            $advanceRequestEmployee = \App\Models\AdvanceRequestEmployee::create([
                'advance_request_id' => $advanceRequest->id,
                'employee_id'        => $employeeId,
            ]);

            $activity = \App\Models\AdvanceRequestActivity::create([
                'advance_request_employee_id' => $advanceRequestEmployee->id,
                'activity_type_id'            => null,
                'activity_crops_id'           => null,
                'activity_variety_id'         => null,
                'amount'                      => $amount,
                'is_imprest'                  => 1, // Flagged as Imprest
                'purpose'                     => $purpose,
                'current_approval_level'      => 0,
                'approval_status'             => \App\Models\AdvanceRequestActivity::APPROVAL_STATUS_APPROVED,
                'finance_status'              => \App\Models\AdvanceRequestActivity::FINANCE_STATUS_PENDING,
                'payment_status'              => \App\Models\AdvanceRequestActivity::PAYMENT_STATUS_PENDING,
                'final_status'                => \App\Models\AdvanceRequestActivity::FINAL_STATUS_PENDING,
                'created_by'                  => 0,
                'updated_by'                  => 0,
            ]);

            \App\Models\AdvanceActivityApproval::create([
                'advance_request_activity_id' => $activity->id,
                'submission_no'               => 1,
                'approval_level'              => 0,
                'approver_id'                 => 0, // System Auto-Approved
                'approver_status'             => 'Approved',
                'approver_amount'             => $amount,
                'approver_remark'             => $approverRemark,
                'approver_date'               => now(),
                'created_by'                  => 0,
                'updated_by'                  => 0,
            ]);

            \App\Models\AdvanceRequestAction::create([
                'advance_request_id' => $advanceRequest->id,
                'submission_no'      => 1,
                'action_type'        => 'Submit',
                'action_by'          => 0, // System
                'created_by'         => 0,
                'updated_by'         => 0,
            ]);

            return (int) $advanceRequest->id;
        });
        $guard->attach($txConn, $imprestRequestId, $advanceId);

        return 'created';
    }

    private function recordAction(string $conn, ?int $requestId, int $employeeId, string $action, ?string $fromStatus, string $toStatus, float $amount, ?string $remarks, ?string $actorName): void
    {
        DB::connection($conn)->table('imprest_request_actions')->insert([
            'imprest_request_id' => $requestId,
            'employee_id'        => $employeeId,
            'action'             => $action,
            'from_status'        => $fromStatus,
            'to_status'          => $toStatus,
            'amount'             => $amount,
            'remarks'            => $remarks,
            'actor_user_id'      => auth()->id(),
            'actor_employee_id'  => session('employee_id') ?: (auth()->user()->employee_id ?? null),
            'actor_name'         => $actorName,
            'ip_address'         => request()->ip(),
            'created_at'         => now(),
            'updated_at'         => now(),
        ]);
    }

    /** rejected_by / rejected_at when the column exists (migration 2026_11_05_000002). */
    private function rejectorFields(string $conn, ?string $rejectedBy): array
    {
        return Schema::connection($conn)->hasColumn('imprest_requests', 'rejected_by')
            ? ['rejected_by' => $rejectedBy, 'rejected_at' => now()]
            : [];
    }

    private function getImprestConnection(): string
    {
        $conn = session('expense_connection', 'expense');
        self::ensureTableExists($conn);
        self::ensureTableExists('mysql');
        if (Schema::connection($conn)->hasTable('imprest_requests')) {
            return $conn;
        }
        return 'mysql';
    }

    public function index(Request $request)
    {
        $conn = $this->getImprestConnection();
        $status = $request->get('status', 'pending');
        $trigger = $request->get('trigger', '');

        $query = DB::connection($conn)
            ->table('imprest_requests')
            ->orderByDesc('created_at');

        if ($status) $query->where('status', $status);
        if ($trigger) $query->where('trigger_type', $trigger);

        $requests = $query->paginate(20)->withQueryString();
        $counts = DB::connection($conn)->table('imprest_requests')
            ->selectRaw("status, COUNT(*) as cnt")
            ->groupBy('status')
            ->pluck('cnt', 'status');

        return view('admin.imprest-requests', compact('requests', 'counts', 'status', 'trigger'));
    }

    public function approve(Request $request, int $id)
    {
        $request->validate(['remarks' => 'nullable|string|max:500']);
        $conn = $this->getImprestConnection();
        $approvedBy = session('emp_name', auth()->user()?->name ?? 'Admin');

        $imprestReq = DB::connection($conn)->table('imprest_requests')->where('id', $id)->first();
        if (!$imprestReq) {
            return response()->json(['success' => false, 'message' => 'Imprest request not found.'], 404);
        }
        if (($imprestReq->status ?? 'pending') !== 'pending') {
            return response()->json(['success' => false, 'message' => "Imprest request is already {$imprestReq->status}."], 409);
        }
        if ($historyError = $this->historyUnavailable($conn)) {
            return $historyError;
        }
        if ($guardError = $this->guardUnavailable($conn)) {
            return $guardError;
        }

        $amount = (float) ($imprestReq->request_amount ?? $imprestReq->eligible_amount ?? 0);
        $txConn = ImprestReleaseGuard::transactionConnection($conn);

        // Conditional update: a second click / concurrent request finds no pending row and gets 409.
        // UAT E-01: the auto advance is created in the same transaction, at most once per request.
        try {
            $outcome = DB::connection($txConn)->transaction(function () use ($txConn, $id, $imprestReq, $approvedBy, $request, $amount) {
                $affected = DB::connection($txConn)->table('imprest_requests')->where('id', $id)->where('status', 'pending')->update([
                    'status'      => 'approved',
                    'approved_by' => $approvedBy,
                    'approved_at' => now(),
                    'remarks'     => $request->remarks,
                    'updated_at'  => now(),
                ]);
                if ($affected === 0) {
                    return null;
                }
                $this->recordAction($txConn, $id, (int) $imprestReq->employee_id, 'approved', $imprestReq->status ?? 'pending', 'approved',
                    (float) ($imprestReq->request_amount ?? 0), $request->remarks, $approvedBy);

                if ($amount <= 0) {
                    return 'no_amount';
                }

                return $this->createImprestAutoAdvance($txConn, (int) $id, (int) $imprestReq->employee_id, $amount,
                    'System Generated Auto Advance for Imprest Approval', 'System Generated Auto-Approved for Imprest Approval');
            });
        } catch (\Throwable $e) {
            if (ImprestReleaseGuard::isConcurrencyConflict($e)) {
                return $this->concurrentActionResponse();
            }
            throw $e;
        }
        if ($outcome === null) {
            return response()->json(['success' => false, 'message' => 'Imprest request is no longer pending.'], 409);
        }

        Log::info("Imprest request [#{$id}] approved", [
            'approved_by' => $approvedBy,
            'remarks'     => $request->remarks,
            'auto_advance' => $outcome,
        ]);

        $advanceMsg = $outcome === 'created' ? ' Auto Advance request created for Finance (System Generated).' : '';

        return response()->json(['success' => true, 'message' => 'Imprest request approved.' . $advanceMsg]);
    }

    public function reject(Request $request, int $id)
    {
        $request->validate(['remarks' => 'required|string|max:500']);
        $conn = $this->getImprestConnection();
        $rejectedBy = session('emp_name', auth()->user()?->name ?? 'Admin');

        $imprestReq = DB::connection($conn)->table('imprest_requests')->where('id', $id)->first();
        if (!$imprestReq) {
            return response()->json(['success' => false, 'message' => 'Imprest request not found.'], 404);
        }
        if (($imprestReq->status ?? 'pending') !== 'pending') {
            return response()->json(['success' => false, 'message' => "Imprest request is already {$imprestReq->status}."], 409);
        }
        if ($historyError = $this->historyUnavailable($conn)) {
            return $historyError;
        }

        $changed = DB::connection($conn)->transaction(function () use ($conn, $id, $imprestReq, $rejectedBy, $request) {
            $affected = DB::connection($conn)->table('imprest_requests')->where('id', $id)->where('status', 'pending')->update(array_merge([
                'status'     => 'rejected',
                'remarks'    => $request->remarks,
                'updated_at' => now(),
            ], $this->rejectorFields($conn, $rejectedBy)));
            if ($affected === 0) {
                return false;
            }
            $this->recordAction($conn, $id, (int) $imprestReq->employee_id, 'rejected', $imprestReq->status ?? 'pending', 'rejected',
                (float) ($imprestReq->request_amount ?? 0), $request->remarks, $rejectedBy);

            return true;
        });
        if (! $changed) {
            return response()->json(['success' => false, 'message' => 'Imprest request is no longer pending.'], 409);
        }

        Log::info("Imprest request [#{$id}] rejected", [
            'rejected_by' => $rejectedBy,
            'remarks'     => $request->remarks,
        ]);

        return response()->json(['success' => true, 'message' => 'Imprest request rejected.']);
    }

    public function runManual()
    {
        $service = new ImprestAutomationService();
        $results = $service->processAll();
        return response()->json(['success' => true, 'results' => $results]);
    }

    /**
     * Report: Active employees in eligible grades (J1–M1) and departments
     * (Sales / PD / Plant Disease) who have NO fixed imprest in the ledger.
     */
    public function missingImprest(Request $request)
    {
        $hrimsConn  = \App\Helpers\DatabaseSwitcher::getHrimsConnection();
        $expConn    = session('expense_connection', 'expense');
        $companyId  = (int) session('company_id', 1);
        $hrimsDb    = \App\Helpers\DatabaseSwitcher::getHrimsDatabaseName();
        $expDb      = config("database.connections.{$expConn}.database", 'expense_app');

        // Eligible grade names and amounts (mirrors ImprestAutomationService)
        $gradeAmounts = [
            'J1' => 20000,
            'J2' => 20000,
            'J3' => 40000,
            'J4' => 40000,
            'M1' => 40000,
        ];
        $eligibleGradeKeys = [
            'J1', 'J2', 'J3', 'J4', 'M1',
            'J-1', 'J-2', 'J-3', 'J-4', 'M-1',
        ];

        // Fetch eligible grade IDs from core_grades using normalized name matching
        $eligibleGradeIds = [];
        try {
            $eligibleGradeIds = DB::connection($hrimsConn)
                ->table('core_grades')
                ->get()
                ->filter(function ($g) use ($gradeAmounts) {
                    $clean = strtoupper(str_replace(['-', ' '], '', $g->grade_name ?? ''));
                    return isset($gradeAmounts[$clean]);
                })
                ->pluck('id')
                ->toArray();
        } catch (\Exception $e) {
            Log::warning('Fetch eligible grade IDs failed: ' . $e->getMessage());
        }

        // Get all active employees in eligible grades+departments
        $empQuery = DB::connection($hrimsConn)
            ->table('hrm_employee as e')
            ->join('hrm_employee_general as eg', 'e.EmployeeID', '=', 'eg.EmployeeID')
            ->join('core_grades as g',           'eg.GradeId',   '=', 'g.id')
            ->join('core_departments as d',      'eg.DepartmentId', '=', 'd.id')
            ->where('e.EmpStatus', 'A')
            ->where(function ($q) use ($eligibleGradeIds, $eligibleGradeKeys) {
                if (!empty($eligibleGradeIds)) {
                    $q->whereIn('eg.GradeId', $eligibleGradeIds);
                } else {
                    $q->whereIn('g.grade_name', $eligibleGradeKeys);
                }
            })
            ->where(function ($q) {
                $q->where(DB::raw('LOWER(d.department_name)'), 'like', '%sales%')
                  ->orWhere(DB::raw('LOWER(d.department_name)'), 'like', '%pd%')
                  ->orWhere(DB::raw('LOWER(d.department_name)'), 'like', '%plant disease%');
            })
            ->select(
                'e.EmployeeID',
                DB::raw("TRIM(CONCAT(e.Fname,' ',COALESCE(e.Sname,''),' ',COALESCE(e.Lname,''))) AS emp_name"),
                DB::raw("COALESCE(e.EmpCode_New, e.EmpCode) AS emp_code"),
                'eg.DateJoining',
                'g.grade_name',
                'd.department_name'
            )
            ->orderBy('d.department_name')
            ->orderBy('g.grade_name')
            ->orderBy('e.Fname');

        $employees = (clone $empQuery)->where('e.CompanyId', $companyId)->get();

        if ($employees->isEmpty()) {
            // Fallback: Query all active employees regardless of CompanyId
            $employees = $empQuery->get();
        }

        // Fetch employee ledger balances (both fixed_imprest_amt_1 and fixed_imprest_amt_2)
        $ledgerBalances = collect();
        try {
            $ledgerBalances = DB::connection($expConn)
                ->table('employee_ledger_balances')
                ->whereIn('employee_id', $employees->pluck('EmployeeID')->toArray())
                ->groupBy('employee_id')
                ->select(
                    'employee_id',
                    DB::raw('COALESCE(MAX(fixed_imprest_amt_1), 0) as amt_1'),
                    DB::raw('COALESCE(MAX(fixed_imprest_amt_2), 0) as amt_2')
                )
                ->get()
                ->keyBy('employee_id');
        } catch (\Exception $e) {
            Log::warning('Fetch employee_ledger_balances failed: ' . $e->getMessage());
        }

        // Get existing imprest_requests records safely
        $conn = $this->getImprestConnection();
        $imprestReqs = collect();
        try {
            $imprestReqs = DB::connection($conn)
                ->table('imprest_requests')
                ->whereIn('employee_id', $employees->pluck('EmployeeID')->toArray())
                ->orderBy('id', 'desc')
                ->get()
                ->groupBy('employee_id');
        } catch (\Exception $e) {
            Log::warning('Fetch imprest_requests failed: ' . $e->getMessage());
        }

        // Fetch latest promotion / appraisal date from hrims.hrm_pms_appraisal_history
        // Exclude initial joining salary records (must be > 30 days after DateJoining)
        $promotions = [];
        try {
            $promotions = DB::connection($hrimsConn)
                ->table('hrm_pms_appraisal_history as ah')
                ->leftJoin('hrm_employee_general as eg', 'ah.EmployeeID', '=', 'eg.EmployeeID')
                ->whereIn('ah.EmployeeID', $employees->pluck('EmployeeID')->toArray())
                ->whereNotNull('ah.SalaryChange_Date')
                ->where('ah.SalaryChange_Date', '!=', '0000-00-00')
                ->where(function ($q) {
                    $q->whereNull('eg.DateJoining')
                      ->orWhere('eg.DateJoining', '=', '0000-00-00')
                      ->orWhereRaw('DATEDIFF(ah.SalaryChange_Date, eg.DateJoining) > 30');
                })
                ->groupBy('ah.EmployeeID')
                ->select('ah.EmployeeID', DB::raw('MAX(ah.SalaryChange_Date) as latest_salary_change_date'))
                ->pluck('latest_salary_change_date', 'ah.EmployeeID')
                ->toArray();
        } catch (\Exception $e) {
            Log::warning('Fetch appraisal history failed: ' . $e->getMessage());
        }

        $sixMonthsAgo = \Carbon\Carbon::now()->subMonths(6)->startOfDay();

        // Compute eligible amount, current ledger imprest, and remaining shortfall
        $missing = $employees->map(function ($e) use ($gradeAmounts, $ledgerBalances, $imprestReqs, $promotions, $sixMonthsAgo) {
            $cleanGrade = strtoupper(str_replace(['-', ' '], '', $e->grade_name ?? ''));
            $e->eligible_amount = $gradeAmounts[$cleanGrade] ?? 0;

            $lb = $ledgerBalances->get($e->EmployeeID);
            $e->current_imprest = $lb ? ((float)$lb->amt_1 + (float)$lb->amt_2) : 0.0;
            $e->request_amount  = max(0.0, (float)$e->eligible_amount - (float)$e->current_imprest);
            $e->is_upgrade      = ($e->current_imprest > 0);

            // Match imprest request for the current grade
            $userReqs = $imprestReqs->get($e->EmployeeID, collect());
            $currentReq = $userReqs->first(function ($r) use ($cleanGrade) {
                $reqGrade = strtoupper(str_replace(['-', ' '], '', $r->grade ?? ''));
                return $reqGrade === $cleanGrade;
            }) ?? $userReqs->first();

            if ($currentReq) {
                $reqGrade = strtoupper(str_replace(['-', ' '], '', $currentReq->grade ?? ''));
                if ($reqGrade !== $cleanGrade || (float)$currentReq->eligible_amount < (float)$e->eligible_amount) {
                    $e->imprest_status  = null;
                    $e->imprest_remarks = null;
                } else {
                    $e->imprest_status  = in_array(strtolower($currentReq->status ?? ''), ['rejected', 'missing_reject']) ? 'rejected' : $currentReq->status;
                    $e->imprest_remarks = $currentReq->remarks;
                }
            } else {
                $e->imprest_status  = null;
                $e->imprest_remarks = null;
            }

            // Date of Promotion (SalaryChange_Date from hrm_pms_appraisal_history)
            $rawChangeDate = $promotions[$e->EmployeeID] ?? null;
            $displayPromotion = '-';
            if (!empty($rawChangeDate) && \App\Support\ZeroDate::has($rawChangeDate)) {
                try {
                    $changeDate = \Carbon\Carbon::parse($rawChangeDate);
                    $joiningDate = (!empty($e->DateJoining) && \App\Support\ZeroDate::has($e->DateJoining))
                        ? \Carbon\Carbon::parse($e->DateJoining)
                        : null;

                    $isNotJoining = true;
                    if ($joiningDate) {
                        $diffDays = $joiningDate->diffInDays($changeDate, false);
                        if ($diffDays <= 30) {
                            $isNotJoining = false;
                        }
                    }

                    if ($isNotJoining && $changeDate->greaterThanOrEqualTo($sixMonthsAgo)) {
                        $displayPromotion = $changeDate->format('d M Y');
                    } else {
                        $displayPromotion = '-';
                    }
                } catch (\Throwable $ex) {
                    $displayPromotion = '-';
                }
            }
            $e->promotion_date = $displayPromotion;

            return $e;
        })
        // Filter — keep only employees who have a shortfall (request_amount > 0)
        ->filter(fn($e) => $e->request_amount > 0)
        ->values();

        // Apply optional filters from request
        if ($request->filled('department')) {
            $dept = strtolower($request->department);
            $missing = $missing->filter(fn($e) => str_contains(strtolower($e->department_name), $dept))->values();
        }
        if ($request->filled('grade')) {
            $filterGrade = strtoupper(str_replace(['-', ' '], '', $request->grade));
            $missing = $missing->filter(fn($e) => strtoupper(str_replace(['-', ' '], '', $e->grade_name)) === $filterGrade)->values();
        }
        if ($request->filled('type')) {
            $type = strtolower($request->type);
            if ($type === 'upgrade') {
                $missing = $missing->filter(fn($e) => $e->is_upgrade)->values();
            } elseif ($type === 'new') {
                $missing = $missing->filter(fn($e) => !$e->is_upgrade)->values();
            }
        }
        if ($request->filled('status')) {
            $st = strtolower($request->status);
            if ($st === 'rejected') {
                $missing = $missing->filter(fn($e) => $e->imprest_status === 'rejected')->values();
            } elseif ($st === 'approved' || $st === 'released') {
                $missing = $missing->filter(fn($e) => $e->imprest_status === 'approved')->values();
            } elseif ($st === 'pending' || $st === 'missing') {
                $missing = $missing->filter(fn($e) => empty($e->imprest_status) || $e->imprest_status === 'pending')->values();
            }
        }

        // Summary counts
        $totalEligibleCount = $employees->count();
        $withFullImprestCount = $employees->filter(function ($e) use ($gradeAmounts, $ledgerBalances) {
            $clean = strtoupper(str_replace(['-', ' '], '', $e->grade_name ?? ''));
            $elig = $gradeAmounts[$clean] ?? 0;
            $lb = $ledgerBalances->get($e->EmployeeID);
            $curr = $lb ? ((float)$lb->amt_1 + (float)$lb->amt_2) : 0.0;
            return ($curr >= $elig && $elig > 0);
        })->count();

        $summary = [
            'total_eligible' => $totalEligibleCount,
            'with_imprest'   => $withFullImprestCount,
            'missing'        => $missing->count(),
            'upgrade_count'  => $missing->filter(fn($e) => $e->is_upgrade)->count(),
            'total_pending'  => $missing->filter(fn($e) => empty($e->imprest_status) || $e->imprest_status === 'pending')->sum('request_amount'),
            'rejected_count' => $missing->filter(fn($e) => $e->imprest_status === 'rejected')->count(),
        ];

        $departments = $employees->pluck('department_name')->unique()->sort()->values();
        $grades      = array_keys($gradeAmounts);

        return view('admin.imprest-missing', compact('missing', 'summary', 'departments', 'grades'));
    }

    /**
     * Release missing imprest and automatically apply for Auto Advance.
     */
    public function releaseMissing(Request $request, int $employeeId)
    {
        $request->validate(['remarks' => 'nullable|string|max:500']);

        $conn = $this->getImprestConnection();
        $hrimsConn = \App\Helpers\DatabaseSwitcher::getHrimsConnection();

        $gradeAmounts = ['J1'=>20000,'J2'=>20000,'J3'=>40000,'J4'=>40000,'M1'=>40000];

        $emp = DB::connection($hrimsConn)
            ->table('hrm_employee as e')
            ->join('hrm_employee_general as eg', 'e.EmployeeID', '=', 'eg.EmployeeID')
            ->join('core_grades as g',           'eg.GradeId',   '=', 'g.id')
            ->join('core_departments as d',      'eg.DepartmentId', '=', 'd.id')
            ->where('e.EmployeeID', $employeeId)
            ->select(
                'e.EmployeeID',
                DB::raw("TRIM(CONCAT(e.Fname,' ',COALESCE(e.Sname,''),' ',e.Lname)) AS emp_name"),
                DB::raw("COALESCE(e.EmpCode_New, e.EmpCode) AS emp_code"),
                'g.grade_name',
                'd.department_name'
            )
            ->first();

        if (!$emp) {
            return response()->json(['success' => false, 'message' => 'Employee not found.'], 404);
        }

        $cleanGrade = strtoupper(str_replace(['-', ' '], '', $emp->grade_name ?? ''));
        $eligibleAmount = $gradeAmounts[$cleanGrade] ?? 0;
        if ($eligibleAmount <= 0) {
            return response()->json(['success' => false, 'message' => "Grade {$emp->grade_name} is not eligible for imprest."], 400);
        }

        // Fetch employee's current fixed imprest from employee_ledger_balances
        $expConn = session('expense_connection', 'expense');
        $ledger = DB::connection($expConn)
            ->table('employee_ledger_balances')
            ->where('employee_id', $employeeId)
            ->select(
                DB::raw('COALESCE(MAX(fixed_imprest_amt_1), 0) as amt_1'),
                DB::raw('COALESCE(MAX(fixed_imprest_amt_2), 0) as amt_2')
            )
            ->first();

        $currentImprest = $ledger ? ((float)$ledger->amt_1 + (float)$ledger->amt_2) : 0.0;
        $requestAmount  = max(0.0, (float)$eligibleAmount - (float)$currentImprest);

        if ($requestAmount <= 0) {
            return response()->json([
                'success' => false,
                'message' => "Employee {$emp->emp_name} already has full fixed imprest of ₹" . number_format($currentImprest) . "."
            ], 400);
        }

        $isUpgrade   = ($currentImprest > 0);
        $triggerType = $isUpgrade ? 'grade_upgrade' : 'missing_release';

        $approvedBy = session('emp_name', auth()->user()?->name ?? 'Admin');
        $remarks = $request->input('remarks') ?: ($isUpgrade
            ? "Grade upgrade to {$emp->grade_name} (Current: Rs. " . number_format($currentImprest) . ", Delta: Rs. " . number_format($requestAmount) . ") & Auto Advance applied"
            : 'Released from Missing Imprest report & Auto Advance applied');

        if ($historyError = $this->historyUnavailable($conn)) {
            return $historyError;
        }
        if ($guardError = $this->guardUnavailable($conn)) {
            return $guardError;
        }

        $txConn = ImprestReleaseGuard::transactionConnection($conn);
        $guard = app(ImprestReleaseGuard::class);
        $activityPurpose = $isUpgrade
            ? "System Generated Auto Advance for Grade Upgrade Imprest Release (Grade: {$emp->grade_name}, Current: ₹" . number_format($currentImprest) . ", Delta: ₹" . number_format($requestAmount) . ")"
            : 'System Generated Auto Advance for Imprest Release';
        $approverRemark = $isUpgrade ? 'System Generated Auto-approved for Grade Upgrade Imprest Release' : 'System Generated Auto-approved for Imprest Release';

        // UAT E-01: status check, request write, action row and auto advance in ONE transaction,
        // under a row lock on the employee's imprest_requests rows. A concurrent second release
        // waits for the lock and then finds the request approved (409); if no row existed yet,
        // the unique key (employee_id, grade) makes the second insert fail (409).
        try {
            $outcome = DB::connection($txConn)->transaction(function () use ($txConn, $guard, $employeeId, $emp, $triggerType, $eligibleAmount, $currentImprest, $requestAmount, $remarks, $approvedBy, $activityPurpose, $approverRemark) {
                // 1. Create or update imprest_requests record (re-read under the lock)
                $existingReq = $guard->lockRequestForGrade($txConn, $employeeId, $emp->grade_name);

                // Tracker #36: a request already approved for this grade is closed; releasing it again
                // would raise a second auto advance before the first one reaches the ledger.
                if ($existingReq && ($existingReq->status ?? '') === 'approved') {
                    return ['conflict' => "Imprest for {$emp->emp_name} ({$emp->grade_name}) is already approved (request #{$existingReq->id})."];
                }

                if ($existingReq) {
                    $imprestRequestId = (int) $existingReq->id;
                    DB::connection($txConn)->table('imprest_requests')
                        ->where('id', $existingReq->id)
                        ->update([
                            'status'          => 'approved',
                            'approved_by'     => $approvedBy,
                            'approved_at'     => now(),
                            'remarks'         => $remarks,
                            'eligible_amount' => $eligibleAmount,
                            'previous_amount' => $currentImprest,
                            'request_amount'  => $requestAmount,
                            'trigger_type'    => $triggerType,
                            'updated_at'      => now(),
                        ]);
                } else {
                    $imprestRequestId = (int) DB::connection($txConn)->table('imprest_requests')->insertGetId([
                        'employee_id'     => $employeeId,
                        'emp_code'        => $emp->emp_code ?? '',
                        'emp_name'        => $emp->emp_name ?? '',
                        'department'      => $emp->department_name ?? '',
                        'grade'           => $emp->grade_name ?? '',
                        'trigger_type'    => $triggerType,
                        'eligible_amount' => $eligibleAmount,
                        'previous_amount' => $currentImprest,
                        'request_amount'  => $requestAmount,
                        'status'          => 'approved',
                        'remarks'         => $remarks,
                        'approved_by'     => $approvedBy,
                        'approved_at'     => now(),
                        'created_at'      => now(),
                        'updated_at'      => now(),
                    ]);
                }
                $this->recordAction($txConn, $imprestRequestId, $employeeId, 'released', $existingReq->status ?? null, 'approved', $requestAmount, $remarks, $approvedBy);

                // 2. Automatically apply for Auto Advance (AdvanceRequest) with delta request_amount, once per request
                return ['advance' => $this->createImprestAutoAdvance($txConn, $imprestRequestId, $employeeId, $requestAmount, $activityPurpose, $approverRemark)];
            });
        } catch (\Throwable $e) {
            if (ImprestReleaseGuard::isConcurrencyConflict($e)) {
                return $this->concurrentActionResponse();
            }
            throw $e;
        }
        if (isset($outcome['conflict'])) {
            return response()->json(['success' => false, 'message' => $outcome['conflict']], 409);
        }

        $advanceMsg = match ($outcome['advance']) {
            'created' => ' Auto Advance request applied successfully (System Generated)!',
            default   => ' (Auto Advance request was already created for this month).',
        };

        Log::info("Missing Imprest Released & Auto Advance applied", [
            'employee_id'     => $employeeId,
            'emp_code'        => $emp->emp_code,
            'emp_name'        => $emp->emp_name,
            'eligible_amount' => $eligibleAmount,
            'current_imprest' => $currentImprest,
            'request_amount'  => $requestAmount,
            'is_upgrade'      => $isUpgrade,
            'approved_by'     => $approvedBy,
            'remarks'         => $remarks,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Imprest released successfully (₹' . number_format($requestAmount) . ') for ' . $emp->emp_name . '.' . $advanceMsg,
        ]);
    }

    /**
     * Reject missing imprest request.
     */
    public function rejectMissing(Request $request, int $employeeId)
    {
        $request->validate(['remarks' => 'required|string|max:500']);

        $conn = $this->getImprestConnection();
        $hrimsConn = \App\Helpers\DatabaseSwitcher::getHrimsConnection();

        $emp = DB::connection($hrimsConn)
            ->table('hrm_employee as e')
            ->join('hrm_employee_general as eg', 'e.EmployeeID', '=', 'eg.EmployeeID')
            ->join('core_grades as g',           'eg.GradeId',   '=', 'g.id')
            ->join('core_departments as d',      'eg.DepartmentId', '=', 'd.id')
            ->where('e.EmployeeID', $employeeId)
            ->select(
                'e.EmployeeID',
                DB::raw("TRIM(CONCAT(e.Fname,' ',COALESCE(e.Sname,''),' ',e.Lname)) AS emp_name"),
                DB::raw("COALESCE(e.EmpCode_New, e.EmpCode) AS emp_code"),
                'g.grade_name',
                'd.department_name'
            )
            ->first();

        if (!$emp) {
            return response()->json(['success' => false, 'message' => 'Employee not found.'], 404);
        }

        $gradeAmounts = ['J1'=>20000,'J2'=>20000,'J3'=>40000,'J4'=>40000,'M1'=>40000];
        $cleanGrade = strtoupper(str_replace(['-', ' '], '', $emp->grade_name ?? ''));
        $eligibleAmount = $gradeAmounts[$cleanGrade] ?? 0;

        $expConn = session('expense_connection', 'expense');
        $ledger = DB::connection($expConn)
            ->table('employee_ledger_balances')
            ->where('employee_id', $employeeId)
            ->select(
                DB::raw('COALESCE(MAX(fixed_imprest_amt_1), 0) as amt_1'),
                DB::raw('COALESCE(MAX(fixed_imprest_amt_2), 0) as amt_2')
            )
            ->first();

        $currentImprest = $ledger ? ((float)$ledger->amt_1 + (float)$ledger->amt_2) : 0.0;
        $requestAmount  = max(0.0, (float)$eligibleAmount - (float)$currentImprest);
        $triggerType    = ($currentImprest > 0) ? 'grade_upgrade_reject' : 'missing_reject';

        $existingReq = DB::connection($conn)->table('imprest_requests')
            ->where('employee_id', $employeeId)
            ->where(function ($q) use ($emp, $cleanGrade) {
                $q->where('grade', $emp->grade_name)
                  ->orWhere(DB::raw("REPLACE(REPLACE(UPPER(grade), '-', ''), ' ', '')"), $cleanGrade);
            })
            ->first();

        $rejectedBy = session('emp_name', auth()->user()?->name ?? 'Admin');
        if ($historyError = $this->historyUnavailable($conn)) {
            return $historyError;
        }
        // Tracker #36: an approved (released) request cannot be turned into a rejection here.
        if ($existingReq && ($existingReq->status ?? '') === 'approved') {
            return response()->json(['success' => false, 'message' => "Imprest for {$emp->emp_name} is already approved (request #{$existingReq->id}); it cannot be rejected from this screen."], 409);
        }

        DB::connection($conn)->beginTransaction();
        try {
        if ($existingReq) {
            $imprestRequestId = (int) $existingReq->id;
            DB::connection($conn)->table('imprest_requests')
                ->where('id', $existingReq->id)
                ->update(array_merge([
                    'status'          => 'rejected',
                    'remarks'         => $request->remarks,
                    'eligible_amount' => $eligibleAmount,
                    'previous_amount' => $currentImprest,
                    'request_amount'  => $requestAmount,
                    'trigger_type'    => $triggerType,
                    'updated_at'      => now(),
                ], $this->rejectorFields($conn, $rejectedBy)));
        } else {
            $imprestRequestId = (int) DB::connection($conn)->table('imprest_requests')->insertGetId(array_merge([
                'employee_id'     => $employeeId,
                'emp_code'        => $emp->emp_code ?? '',
                'emp_name'        => $emp->emp_name ?? '',
                'department'      => $emp->department_name ?? '',
                'grade'           => $emp->grade_name ?? '',
                'trigger_type'    => $triggerType,
                'eligible_amount' => $eligibleAmount,
                'previous_amount' => $currentImprest,
                'request_amount'  => $requestAmount,
                'status'          => 'rejected',
                'remarks'         => $request->remarks,
                'created_at'      => now(),
                'updated_at'      => now(),
            ], $this->rejectorFields($conn, $rejectedBy)));
        }
        $this->recordAction($conn, $imprestRequestId, $employeeId, 'release_rejected', $existingReq->status ?? null, 'rejected', $requestAmount, $request->remarks, $rejectedBy);
        DB::connection($conn)->commit();
        } catch (\Throwable $e) {
            DB::connection($conn)->rollBack();
            throw $e;
        }

        Log::info("Missing Imprest Rejected", [
            'employee_id'     => $employeeId,
            'emp_code'        => $emp->emp_code,
            'emp_name'        => $emp->emp_name,
            'eligible_amount' => $eligibleAmount,
            'current_imprest' => $currentImprest,
            'request_amount'  => $requestAmount,
            'rejected_by'     => $rejectedBy,
            'remarks'         => $request->remarks,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Imprest request rejected for ' . $emp->emp_name . '.',
        ]);
    }

    /**
     * One-click cleanup to reset created_by, req_app_submit_id, approver_id to 0 for all Imprest advances
     */
    public function cleanRaisedBy(Request $request)
    {
        $connections = array_filter(array_unique([
            session('expense_connection', 'expense'),
            config('database.default', 'mysql'),
            'mysql',
            'expense',
            'expense_app',
        ]), fn ($c) => config("database.connections.{$c}") !== null);

        $totalUpdatedRequests = 0;
        $details = [];

        foreach ($connections as $conn) {
            try {
                if (!Schema::connection($conn)->hasTable('advance_requests')) {
                    continue;
                }

                $imprestRequestIds = DB::connection($conn)->table('advance_requests as ar')
                    ->leftJoin('advance_request_employees as are', 'are.advance_request_id', '=', 'ar.id')
                    ->leftJoin('advance_request_activities as ara', 'ara.advance_request_employee_id', '=', 'are.id')
                    ->where(function ($q) {
                        $q->where('ar.is_imprest', 1)
                          ->orWhere('ara.is_imprest', 1)
                          ->orWhere('ara.purpose', 'like', '%imprest%');
                    })
                    ->distinct()
                    ->pluck('ar.id')
                    ->toArray();

                if (empty($imprestRequestIds)) {
                    $details[$conn] = '0 records found';
                    continue;
                }

                // Update advance_requests
                $reqUpdateData = [];
                if (Schema::connection($conn)->hasColumn('advance_requests', 'is_imprest')) {
                    $reqUpdateData['is_imprest'] = 1;
                }
                if (Schema::connection($conn)->hasColumn('advance_requests', 'created_by')) {
                    $reqUpdateData['created_by'] = 0;
                }
                if (Schema::connection($conn)->hasColumn('advance_requests', 'updated_by')) {
                    $reqUpdateData['updated_by'] = 0;
                }
                if (Schema::connection($conn)->hasColumn('advance_requests', 'req_app_submit_id')) {
                    $reqUpdateData['req_app_submit_id'] = 0;
                }
                $reqUpdated = 0;
                if (!empty($reqUpdateData)) {
                    $reqUpdated = DB::connection($conn)->table('advance_requests')
                        ->whereIn('id', $imprestRequestIds)
                        ->update($reqUpdateData);
                }

                // Update advance_request_employees if columns exist
                $empUpdateData = [];
                if (Schema::connection($conn)->hasColumn('advance_request_employees', 'created_by')) {
                    $empUpdateData['created_by'] = 0;
                }
                if (Schema::connection($conn)->hasColumn('advance_request_employees', 'updated_by')) {
                    $empUpdateData['updated_by'] = 0;
                }
                if (!empty($empUpdateData)) {
                    DB::connection($conn)->table('advance_request_employees')
                        ->whereIn('advance_request_id', $imprestRequestIds)
                        ->update($empUpdateData);
                }

                $empIds = DB::connection($conn)->table('advance_request_employees')
                    ->whereIn('advance_request_id', $imprestRequestIds)
                    ->pluck('id')
                    ->toArray();

                if (!empty($empIds) && Schema::connection($conn)->hasTable('advance_request_activities')) {
                    $actUpdateData = [];
                    if (Schema::connection($conn)->hasColumn('advance_request_activities', 'is_imprest')) {
                        $actUpdateData['is_imprest'] = 1;
                    }
                    if (Schema::connection($conn)->hasColumn('advance_request_activities', 'created_by')) {
                        $actUpdateData['created_by'] = 0;
                    }
                    if (Schema::connection($conn)->hasColumn('advance_request_activities', 'updated_by')) {
                        $actUpdateData['updated_by'] = 0;
                    }
                    if (!empty($actUpdateData)) {
                        DB::connection($conn)->table('advance_request_activities')
                            ->whereIn('advance_request_employee_id', $empIds)
                            ->update($actUpdateData);
                    }

                    $actIds = DB::connection($conn)->table('advance_request_activities')
                        ->whereIn('advance_request_employee_id', $empIds)
                        ->pluck('id')
                        ->toArray();

                    if (!empty($actIds) && Schema::connection($conn)->hasTable('advance_activity_approvals')) {
                        $apprUpdateData = [];
                        if (Schema::connection($conn)->hasColumn('advance_activity_approvals', 'approver_id')) {
                            $apprUpdateData['approver_id'] = 0;
                        }
                        if (Schema::connection($conn)->hasColumn('advance_activity_approvals', 'approver_remark')) {
                            $apprUpdateData['approver_remark'] = DB::raw("CASE WHEN approver_remark IS NULL OR approver_remark = '' THEN 'System Generated Auto-Approved for Imprest' ELSE approver_remark END");
                        }
                        if (Schema::connection($conn)->hasColumn('advance_activity_approvals', 'created_by')) {
                            $apprUpdateData['created_by'] = 0;
                        }
                        if (Schema::connection($conn)->hasColumn('advance_activity_approvals', 'updated_by')) {
                            $apprUpdateData['updated_by'] = 0;
                        }
                        if (!empty($apprUpdateData)) {
                            DB::connection($conn)->table('advance_activity_approvals')
                                ->whereIn('advance_request_activity_id', $actIds)
                                ->update($apprUpdateData);
                        }
                    }
                }

                if (Schema::connection($conn)->hasTable('advance_request_actions')) {
                    $actionUpdateData = [];
                    if (Schema::connection($conn)->hasColumn('advance_request_actions', 'action_by')) {
                        $actionUpdateData['action_by'] = 0;
                    }
                    if (Schema::connection($conn)->hasColumn('advance_request_actions', 'created_by')) {
                        $actionUpdateData['created_by'] = 0;
                    }
                    if (Schema::connection($conn)->hasColumn('advance_request_actions', 'updated_by')) {
                        $actionUpdateData['updated_by'] = 0;
                    }
                    if (!empty($actionUpdateData)) {
                        DB::connection($conn)->table('advance_request_actions')
                            ->whereIn('advance_request_id', $imprestRequestIds)
                            ->update($actionUpdateData);
                    }
                }

                $verified = DB::connection($conn)->table('advance_requests')
                    ->whereIn('id', $imprestRequestIds)
                    ->select('id', 'created_by', 'req_app_submit_id', 'is_imprest')
                    ->get();

                $totalUpdatedRequests += count($imprestRequestIds);
                $details[$conn] = [
                    'message' => "Successfully processed and reset " . count($imprestRequestIds) . " imprest advance request(s) to System Generated (0)!",
                    'request_ids' => $imprestRequestIds,
                    'verified_records' => $verified
                ];
            } catch (\Exception $e) {
                $details[$conn] = 'Error: ' . $e->getMessage();
            }
        }

        return response()->json([
            'success' => true,
            'message' => "All {$totalUpdatedRequests} Imprest Auto Advance record(s) have been reset and verified as System Generated (0)!",
            'details' => $details
        ]);
    }
}
