<?php

namespace App\Http\Controllers\admin;

use App\Http\Controllers\Controller;
use App\Models\ClaimType;
use App\Models\ExpenseRemark;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Yajra\DataTables\Facades\DataTables;

class MediatorController extends Controller
{
    public function dataPunch(Request $request, $status)
    {
        $companyId = session('company_id', 1);
        $expenseConn = \App\Helpers\DatabaseSwitcher::getExpenseConnection($companyId);
        DB::connection($expenseConn)->enableQueryLog();
        $fYearId = session('year_id');
        $today = now()->format('Y-m-d');
        $monthStart = now()->startOfMonth()->format('Y-m-d');
        $table = "y{$fYearId}_expenseclaims";
        $baseCondition = function ($query) use ($today, $companyId) {
            $query->where(function ($q) use ($today, $companyId) {
                $q->where('CrDate', '<=', $today)->orWhere('ClaimId', '!=', 7)->orWhereRaw('? = 3', [$companyId]);
            });
        };
        $holdCount = DB::connection($expenseConn)->table($table)->where('ClaimStatus', 'Submitted')->where('Rmk', 1)->where('AttachTo', 0)->where('ClaimId', '!=', 7)->where($baseCondition)->count();
        $draftCount = DB::connection($expenseConn)->table($table)->where('ClaimStatus', 'Draft')->where('Filledokay', '!=', 2)->where('AttachTo', 0)->where('ClaimId', '!=', 7)->where($baseCondition)->count();
        $filledCount = DB::connection($expenseConn)->table($table)->where('ClaimStatus', 'Filled')->where('Filledokay', '!=', 2)->where('AttachTo', 0)->where('ClaimId', '!=', 7)->where($baseCondition)->count();
        $hrimsDb = \App\Helpers\DatabaseSwitcher::getHrimsDatabaseName($companyId);
        $uploadedCount = DB::connection($expenseConn)
            ->table("$table as ec")
            ->join($hrimsDb . '.hrm_employee as emp', 'ec.CrBy', '=', 'emp.EmployeeID')
            ->join('claimtype as ct', 'ec.ClaimId', '=', 'ct.ClaimId')
            ->where('ec.ClaimStatus', 'Submitted')
            ->where('ec.Rmk', 0)
            ->where('ec.AttachTo', 0)
            ->where('ec.ClaimId', '!=', 7)
            ->where(function ($q) use ($today, $companyId) {
                $q
                    ->where('ec.CrDate', '<=', $today)
                    ->orWhereRaw('? = 3', [$companyId]);
            })
            ->count();
        $deniedCount = DB::connection($expenseConn)->table($table)->where(function ($q) {
            $q->where('Filledokay', 2)->orWhere('FilledOkay', 2);
        })->where('AttachTo', 0)->where('ClaimId', '!=', 7)->where($baseCondition)->count();
        $deactivatedCount = $this->isAdminOrSuperAdmin()
            ? DB::connection($expenseConn)->table($table)->where('ClaimStatus', 'Deactivate')->where('AttachTo', 0)->where('ClaimId', '!=', 7)->where($baseCondition)->count()
            : 0;
        // Build status-specific query for filters
        if ($status === 'deactivated') {
            $statusQuery = DB::connection($expenseConn)->table($table)->where('ClaimStatus', 'Deactivate')->where('AttachTo', 0)->where($baseCondition);
        } else {
            $statusQuery = DB::connection($expenseConn)->table($table)->where('ClaimStatus', '!=', 'Deactivate')->where('AttachTo', 0)->where($baseCondition);
            $this->applyPunchStatus($statusQuery, $status, '', $monthStart, $request->boolean('show_punched'));
        }
        $availableDates = (clone $statusQuery)->select('CrDate')->groupBy('CrDate')->orderByDesc('CrDate')->pluck('CrDate');
        $availableEmployees = (clone $statusQuery)->select('CrBy')->groupBy('CrBy')->orderBy('CrBy')->pluck('CrBy');
        $employees = collect();
        if ($availableEmployees->isNotEmpty()) {
            $employees = DB::connection(session('hrims_connection', 'hrims'))->table('hrm_employee')->whereIn('EmployeeID', $availableEmployees)->select('EmployeeID', DB::connection($expenseConn)->raw("CONCAT(Fname, ' ', COALESCE(Sname, ''), ' ', Lname) as EmpName"), 'EmpCode')->orderBy('EmpCode')->get();
        }
        $claimTypes = DB::connection($expenseConn)->table('claimtype')->where('ClaimStatus', 'A')->whereIn('cgId', [1, 7])->orderBy('ClaimName')->get(['ClaimId', 'ClaimName']);
        $isAdminView = $this->isAdminOrSuperAdmin();
        // Tracker #28: target heads for the bulk change (bill heads only; not odometer / DA)
        $bulkHeadTypes = DB::connection($expenseConn)->table('claimtype')->where('ClaimStatus', 'A')->where('cgId', 1)->whereNotIn('ClaimId', [7, 19, 20])->orderBy('ClaimName')->get(['ClaimId', 'ClaimName']);
        $canBulkChangeHead = $status !== 'deactivated' && (bool) auth()->user()?->can('claim.bulk_change_head');
        return view('admin.data_punch', compact('status', 'holdCount', 'draftCount', 'filledCount', 'uploadedCount', 'deniedCount', 'deactivatedCount', 'availableDates', 'employees', 'claimTypes', 'isAdminView', 'bulkHeadTypes', 'canBulkChangeHead'));
    }

    /**
     * Status condition of a Data Punch tab. ODO-27 (tracker, 27 Oct, employee 1850): with
     * "Show punched" ticked the tab also lists claims that are already punched (Filled), of any
     * month, so a date whose claims were all punched still appears in the date filter. The Filled
     * tab's filter lists stop at the current month unless the box is ticked.
     */
    private function applyPunchStatus($query, string $status, string $p, ?string $monthStart, bool $showPunched): void
    {
        $own = function ($q) use ($status, $p, $monthStart, $showPunched) {
            if ($status === 'hold') {
                $q->where($p.'ClaimStatus', 'Submitted')->where($p.'Rmk', 1);
            } elseif ($status === 'draft') {
                $q->where($p.'ClaimStatus', 'Draft')->where($p.'Filledokay', '!=', 2);
            } elseif ($status === 'filled') {
                $q->where($p.'ClaimStatus', 'Filled')->where($p.'Filledokay', '!=', 2);
                if ($monthStart && ! $showPunched) {
                    $q->where($p.'BillDate', '>=', $monthStart);
                }
            } elseif ($status === 'uploaded') {
                $q->where($p.'ClaimStatus', 'Submitted')->where($p.'Rmk', 0);
            } elseif ($status === 'denied') {
                $q->where(function ($qq) use ($p) {
                    $qq->where($p.'Filledokay', 2)->orWhere($p.'FilledOkay', 2);
                });
            }
        };
        if (! $showPunched || $status === 'filled' || ! in_array($status, ['hold', 'draft', 'uploaded', 'denied'], true)) {
            $own($query);

            return;
        }
        $query->where(function ($q) use ($own, $p) {
            $q->where($own)->orWhere(function ($f) use ($p) {
                $f->where($p.'ClaimStatus', 'Filled')->where($p.'Filledokay', '!=', 2);
            });
        });
    }

    public function dataPunchData(Request $request, $status)
    {
        $fYearId = session('year_id');
        $companyId = session('company_id', 1);
        $expenseConn = \App\Helpers\DatabaseSwitcher::getExpenseConnection($companyId);
        $today = now()->format('Y-m-d');
        $monthStart = now()->startOfMonth()->format('Y-m-d');
        $table = "y{$fYearId}_expenseclaims";
        $baseCondition = function ($query) use ($today, $companyId) {
            $query->where(function ($q) use ($today, $companyId) {
                $q->where('ec.CrDate', '<=', $today)->orWhere('ec.ClaimId', '!=', 7)->orWhereRaw('? = 3', [$companyId]);
            });
        };
        $hrimsDb = \App\Helpers\DatabaseSwitcher::getHrimsDatabaseName($companyId);
        $query = DB::connection($expenseConn)->table($table . ' as ec')->join($hrimsDb . '.hrm_employee as emp', 'ec.CrBy', '=', 'emp.EmployeeID')->join('claimtype as ct', 'ec.ClaimId', '=', 'ct.ClaimId')->select([
            'ec.ExpId',
            'ec.ClaimId',
            'ec.CrBy',
            'ec.BillDate',
            'ec.CrDate',
            'ec.DateEntryRemark',
            'ec.ClaimStatus',
            'ec.FilOkDenyRemark',
            'ec.FilledTAmt', // HOLD-01: shown on the Hold tab
            'ec.Amount',
            'emp.EmpCode',
            DB::connection($expenseConn)->raw("CONCAT(emp.EmpCode, ' - ', emp.Fname, ' ', COALESCE(emp.Sname, ''), ' ', emp.Lname) AS EmpName"),
            'ct.ClaimName',
        ])->where('ec.ClaimId', '!=', 7)->where('ec.AttachTo', 0)->where($baseCondition);
        if ($status === 'deactivated') {
            if (!$this->isAdminOrSuperAdmin()) {
                return response()->json(['data' => []], 403);
            }
            $query->where('ec.ClaimStatus', 'Deactivate');
        } else {
            $query->where('ec.ClaimStatus', '!=', 'Deactivate');
            // the Filled tab table was never limited to this month; only its filter lists were
            $this->applyPunchStatus($query, $status, 'ec.', null, $request->boolean('show_punched'));
        }
        if ($request->filled('date') && $request->date !== '1010') {
            $query->whereDate('ec.CrDate', $request->date);
        }
        if ($request->filled('emp') && $request->emp !== '1010') {
            $query->where('ec.CrBy', $request->emp);
        }
        if ($request->filled('claim_type') && $request->claim_type !== '1010') {
            $claimTypes = (array) $request->claim_type;
            $query->whereIn('ec.ClaimId', $claimTypes);
        }
        return DataTables::of($query)->addIndexColumn()->editColumn('CrDate', function ($row) {
            return Carbon::parse($row->CrDate)->format('d-m-Y');
        })->editColumn('BillDate', function ($row) {
            return Carbon::parse($row->BillDate)->format('d-m-Y');
        })->addColumn('claim_name', function ($row) {
            return $row->ClaimName;
        })->addColumn('employee', function ($row) {
            return $row->EmpName;
        })->addColumn('action', function ($row) use ($status) {
            $cgId = ClaimType::getCgIdByClaimId($row->ClaimId);
            // Deactivated tab: show only Revert button for admins
            if ($status === 'deactivated') {
                if ($this->isAdminOrSuperAdmin()) {
                    return '
                        <button type="button"
                                class="btn btn-success btn-sm revert-deactivated me-1"
                                data-claim-id="' . $row->ClaimId . '"
                                data-expid="' . $row->ExpId . '"
                                title="Revert Deactivated Claim">
                            <i class="ri-arrow-go-back-line"></i> Revert
                        </button>
                    ';
                }
                return '';
            }
            $revertBtn = '';
            if ($status === 'denied') {
                $revertBtn = '
                    <button type="button"
                            class="btn btn-warning btn-sm revert-claim me-1"
                            data-claim-id="' . $row->ClaimId . '"
                            data-expid="' . $row->ExpId . '"
                            title="Revert to Uploaded">
                        <i class="ri-arrow-go-back-line"></i>
                    </button>
                ';
            }
            $deactivateBtn = '';
            if ($this->isAdminOrSuperAdmin()) {
                $deactivateBtn = '
                    <button type="button"
                            class="btn btn-danger btn-sm deactivate-claim ms-1"
                            data-expid="' . $row->ExpId . '"
                            data-claim-id="' . $row->ClaimId . '"
                            title="Deactivate Claim">
                        <i class="ri-forbid-line"></i>
                    </button>
                ';
            }
            return $revertBtn . '
                    <button type="button"
                            class="btn btn-primary btn-sm view-claim"
                            data-claim-id="' . $row->ClaimId . '"
                            data-empid="' . $row->CrBy . '"
                            data-cgid="' . $cgId . '"
                            data-expid="' . $row->ExpId . '"
                            data-empname="' . e($row->EmpName) . '"
                            data-empcode="' . e($row->EmpCode) . '"
                            title="View / Edit Claim">
                        <i class="ri-edit-2-fill"></i>
                    </button>
                ' . $deactivateBtn;
        })->filterColumn('employee', function ($query, $keyword) {
            $query->whereRaw("CONCAT(emp.EmpCode, ' - ', emp.Fname, ' ', COALESCE(emp.Sname, ''), ' ', emp.Lname) LIKE ?", ["%{$keyword}%"]);
        })->rawColumns(['action'])->make(true);
    }

    public function getFilteredOptions(Request $request, $status)
    {
        $fYearId = session('year_id');
        $companyId = session('company_id');
        $expenseConn = \App\Helpers\DatabaseSwitcher::getExpenseConnection($companyId);
        $today = now()->format('Y-m-d');
        $monthStart = now()->startOfMonth()->format('Y-m-d');
        $table = "y{$fYearId}_expenseclaims";
        $baseCondition = function ($query) use ($today, $companyId) {
            $query->where(function ($q) use ($today, $companyId) {
                $q->where('CrDate', '<=', $today)->orWhere('ClaimId', '!=', 7)->orWhereRaw('? = 3', [$companyId]);
            });
        };
        if ($status === 'deactivated') {
            $statusQuery = DB::connection($expenseConn)->table($table)->where('ClaimStatus', 'Deactivate')->where('AttachTo', 0)->where($baseCondition);
        } else {
            $statusQuery = DB::connection($expenseConn)->table($table)->where('ClaimStatus', '!=', 'Deactivate')->where('AttachTo', 0)->where($baseCondition);
            $this->applyPunchStatus($statusQuery, $status, '', $monthStart, $request->boolean('show_punched'));
        }
        if ($request->has('date') && $request->date != '' && $request->date != '1010') {
            $statusQuery->whereDate('CrDate', $request->date);
        }
        if ($request->has('emp') && $request->emp != '' && $request->emp != '1010') {
            $statusQuery->where('CrBy', $request->emp);
        }
        $availableDates = (clone $statusQuery)->select('CrDate')->groupBy('CrDate')->orderByDesc('CrDate')->pluck('CrDate')->map(function ($date) {
            return ['value' => $date, 'text' => Carbon::parse($date)->format('d-m-Y')];
        });
        $availableEmployees = (clone $statusQuery)->select('CrBy')->groupBy('CrBy')->orderBy('CrBy')->pluck('CrBy');
        $employees = collect();
        if ($availableEmployees->isNotEmpty()) {
            $employees = DB::connection(session('hrims_connection', 'hrims'))->table('hrm_employee')->whereIn('EmployeeID', $availableEmployees)->select('EmployeeID', DB::raw("CONCAT(Fname, ' ', COALESCE(Sname, ''), ' ', Lname) as EmpName"), 'EmpCode')->orderBy('EmpCode')->get()->map(function ($emp) {
                return ['value' => $emp->EmployeeID, 'text' => $emp->EmpCode . ' - ' . $emp->EmpName];
            });
        }
        return response()->json(['dates' => $availableDates, 'employees' => $employees]);
    }

    /**
     * Revert a deactivated claim back to Submitted status. Admin/Super Admin only.
     */
    public function revertDeactivatedClaim(Request $request)
    {
        if (!$this->isAdminOrSuperAdmin()) {
            return response()->json(['success' => false, 'message' => 'Unauthorized. Only Admin or Super Admin can revert deactivated claims.'], 403);
        }

        $request->validate([
            'expid'   => 'required|integer',
            'claimid' => 'required|integer',
        ]);

        $expId     = $request->expid;
        $claimId   = $request->claimid;
        $fYearId   = session('year_id');
        $companyId = session('company_id', 1);
        $expenseConn = \App\Helpers\DatabaseSwitcher::getExpenseConnection($companyId);
        $table = "y{$fYearId}_expenseclaims";

        try {
            DB::beginTransaction();

            $claim = DB::connection($expenseConn)->table($table)
                ->where('ExpId', $expId)
                ->where('ClaimId', $claimId)
                ->where('ClaimStatus', 'Deactivate')
                ->first();

            if (!$claim) {
                DB::rollBack();
                return response()->json(['success' => false, 'message' => 'Claim not found or not in deactivated state.'], 404);
            }

            // Revert to Submitted status (B-01: checked and logged)
            \App\Services\Claims\ClaimTransition::guardTable($expenseConn, $table, $expId, 'Submitted', 'mediator', 'mediator_revert_deactivate');
            DB::connection($expenseConn)->table($table)
                ->where('ExpId', $expId)
                ->update([
                    'ClaimStatus'    => 'Submitted',
                    'Filledokay'     => 0,
                    'FilledBy'       => 0,
                    'FilledDate'     => null,
                    'Rmk'            => 0,
                    'FilOkDenyRemark' => null,
                ]);

            // Log to activity_log
            try {
                DB::table('activity_log')->insert([
                    'log_name'     => 'expense_claim',
                    'description'  => "Deactivated claim {$expId} was reverted to Uploaded by " . auth()->user()->name,
                    'subject_type' => 'App\\Models\\ExpenseClaim',
                    'subject_id'   => $expId,
                    'event'        => 'reactivated',
                    'causer_type'  => 'App\\Models\\User',
                    'causer_id'    => auth()->id(),
                    'properties'   => json_encode([
                        'exp_id'            => $expId,
                        'claim_id'          => $claimId,
                        'reverted_by_id'    => auth()->id(),
                        'reverted_by_name'  => auth()->user()->name,
                        'reverted_at'       => now()->toDateTimeString(),
                        'company_id'        => $companyId,
                        'year_id'           => $fYearId,
                    ]),
                    'batch_uuid'   => null,
                    'created_at'   => now(),
                    'updated_at'   => now(),
                ]);
            } catch (\Exception $logEx) {
                Log::error('revertDeactivatedClaim: activity_log insert failed: ' . $logEx->getMessage());
            }

            // Log to expenseremark
            try {
                $remarkTable = ExpenseRemark::tableName($fYearId);
                if (!Schema::connection($expenseConn)->hasTable($remarkTable)) {
                    $remarkTable = 'expenseremark';
                }
                if (Schema::connection($expenseConn)->hasTable($remarkTable)) {
                    DB::connection($expenseConn)->table($remarkTable)->insert([
                        'ExpId'      => $expId,
                        'Remark'     => 'Deactivated claim reverted to Uploaded by Admin.',
                        'By'         => auth()->id(),
                        'ByRole'     => 'A',
                        'CrDateTime' => now(),
                    ]);
                }
            } catch (\Exception $remarkEx) {
                Log::error('revertDeactivatedClaim: expenseremark insert failed: ' . $remarkEx->getMessage());
            }

            DB::commit();

            // Recalculate badge counts
            $holdCount       = DB::connection($expenseConn)->table($table)->where('ClaimStatus', 'Submitted')->where('Rmk', 1)->where('AttachTo', 0)->where('ClaimId', '!=', 7)->count();
            $draftCount      = DB::connection($expenseConn)->table($table)->where('ClaimStatus', 'Draft')->where('Filledokay', '!=', 2)->where('AttachTo', 0)->where('ClaimId', '!=', 7)->count();
            $filledCount     = DB::connection($expenseConn)->table($table)->where('ClaimStatus', 'Filled')->where('Filledokay', '!=', 2)->where('AttachTo', 0)->where('ClaimId', '!=', 7)->count();
            $uploadedCount   = DB::connection($expenseConn)->table($table)->where('ClaimStatus', 'Submitted')->where('Rmk', 0)->where('AttachTo', 0)->where('ClaimId', '!=', 7)->count();
            $deniedCount     = DB::connection($expenseConn)->table($table)->where(function ($q) { $q->where('Filledokay', 2)->orWhere('FilledOkay', 2); })->where('AttachTo', 0)->where('ClaimId', '!=', 7)->count();
            $deactivatedCount = DB::connection($expenseConn)->table($table)->where('ClaimStatus', 'Deactivate')->where('AttachTo', 0)->where('ClaimId', '!=', 7)->count();

            return response()->json([
                'success' => true,
                'message' => 'Claim has been reverted to Uploaded status successfully.',
                'data'    => [
                    'expid'            => $expId,
                    'holdCount'        => $holdCount,
                    'draftCount'       => $draftCount,
                    'filledCount'      => $filledCount,
                    'uploadedCount'    => $uploadedCount,
                    'deniedCount'      => $deniedCount,
                    'deactivatedCount' => $deactivatedCount,
                ],
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('revertDeactivatedClaim error: ' . $e->getMessage());
            return response()->json(['success' => false, 'message' => 'Failed to revert claim. Please try again.'], 500);
        }
    }

    /**
     * Check if the current authenticated user is Admin or Super Admin.
     */
    private function isAdminOrSuperAdmin(): bool
    {
        $user = auth()->user();
        if (!$user) return false;
        if ($user->id === 1 || session('user_type') === 'Admin') return true;
        if (method_exists($user, 'hasAnyRole') && $user->hasAnyRole(['Super Admin', 'super_admin', 'Admin', 'admin'])) return true;
        if (method_exists($user, 'can') && ($user->can('section_admin') || $user->can('super_admin') || $user->can('admin'))) return true;
        return false;
    }

    /**
     * Deactivate a claim. Only accessible by Admin or Super Admin.
     */
    public function deactivateClaim(Request $request)
    {
        if (!$this->isAdminOrSuperAdmin()) {
            return response()->json(['success' => false, 'message' => 'Unauthorized. Only Admin or Super Admin can deactivate claims.'], 403);
        }

        $request->validate([
            'expid'   => 'required|integer',
            'claimid' => 'required|integer',
            'reason'  => 'required|string|max:500',
        ]);

        $expId    = $request->expid;
        $claimId  = $request->claimid;
        $reason   = trim($request->reason);
        $fYearId  = session('year_id');
        $companyId = session('company_id', 1);
        $expenseConn = \App\Helpers\DatabaseSwitcher::getExpenseConnection($companyId);
        $table = "y{$fYearId}_expenseclaims";

        try {
            DB::beginTransaction();

            $currentClaim = DB::connection($expenseConn)->table($table)
                ->where('ExpId', $expId)
                ->where('ClaimId', $claimId)
                ->where('ClaimStatus', '!=', 'Deactivate')
                ->first();

            if (!$currentClaim) {
                DB::rollBack();
                return response()->json(['success' => false, 'message' => 'Claim not found or already deactivated.'], 404);
            }

            $previousStatus = $currentClaim->ClaimStatus;

            // Update claim status (B-01: checked and logged)
            \App\Services\Claims\ClaimTransition::guardTable($expenseConn, $table, $expId, 'Deactivate', 'mediator', 'mediator_deactivate', $reason);
            DB::connection($expenseConn)->table($table)
                ->where('ExpId', $expId)
                ->update([
                    'ClaimStatus'    => 'Deactivate',
                    'FilOkDenyRemark' => 'Deactivated: ' . $reason,
                ]);

            // Log to activity_log
            try {
                DB::table('activity_log')->insert([
                    'log_name'     => 'expense_claim',
                    'description'  => "Claim {$expId} was deactivated by " . auth()->user()->name . ". Reason: " . $reason,
                    'subject_type' => 'App\\Models\\ExpenseClaim',
                    'subject_id'   => $expId,
                    'event'        => 'deactivated',
                    'causer_type'  => 'App\\Models\\User',
                    'causer_id'    => auth()->id(),
                    'properties'   => json_encode([
                        'exp_id'              => $expId,
                        'claim_id'            => $claimId,
                        'reason'              => $reason,
                        'previous_status'     => $previousStatus,
                        'deactivated_by_id'   => auth()->id(),
                        'deactivated_by_name' => auth()->user()->name,
                        'deactivated_at'      => now()->toDateTimeString(),
                        'company_id'          => $companyId,
                        'year_id'             => $fYearId,
                    ]),
                    'batch_uuid'   => null,
                    'created_at'   => now(),
                    'updated_at'   => now(),
                ]);
            } catch (\Exception $logEx) {
                Log::error('deactivateClaim: activity_log insert failed: ' . $logEx->getMessage());
            }

            // Log to expenseremark
            try {
                $remarkTable = ExpenseRemark::tableName($fYearId);
                if (!Schema::connection($expenseConn)->hasTable($remarkTable)) {
                    $remarkTable = 'expenseremark';
                }
                if (Schema::connection($expenseConn)->hasTable($remarkTable)) {
                    DB::connection($expenseConn)->table($remarkTable)->insert([
                        'ExpId'      => $expId,
                        'Remark'     => 'Claim Deactivated: ' . $reason,
                        'By'         => auth()->id(),
                        'ByRole'     => 'A',
                        'CrDateTime' => now(),
                    ]);
                }
            } catch (\Exception $remarkEx) {
                Log::error('deactivateClaim: expenseremark insert failed: ' . $remarkEx->getMessage());
            }

            DB::commit();

            // Recalculate badge counts
            $holdCount     = DB::connection($expenseConn)->table($table)->where('ClaimStatus', 'Submitted')->where('Rmk', 1)->where('AttachTo', 0)->where('ClaimId', '!=', 7)->where('ClaimStatus', '!=', 'Deactivate')->count();
            $draftCount    = DB::connection($expenseConn)->table($table)->where('ClaimStatus', 'Draft')->where('Filledokay', '!=', 2)->where('AttachTo', 0)->where('ClaimId', '!=', 7)->where('ClaimStatus', '!=', 'Deactivate')->count();
            $filledCount   = DB::connection($expenseConn)->table($table)->where('ClaimStatus', 'Filled')->where('Filledokay', '!=', 2)->where('AttachTo', 0)->where('ClaimId', '!=', 7)->where('ClaimStatus', '!=', 'Deactivate')->count();
            $uploadedCount = DB::connection($expenseConn)->table($table)->where('ClaimStatus', 'Submitted')->where('Rmk', 0)->where('AttachTo', 0)->where('ClaimId', '!=', 7)->where('ClaimStatus', '!=', 'Deactivate')->count();
            $deniedCount   = DB::connection($expenseConn)->table($table)->where(function ($q) { $q->where('Filledokay', 2)->orWhere('FilledOkay', 2); })->where('AttachTo', 0)->where('ClaimId', '!=', 7)->where('ClaimStatus', '!=', 'Deactivate')->count();

            return response()->json([
                'success' => true,
                'message' => 'Claim has been deactivated successfully.',
                'data'    => [
                    'expid'         => $expId,
                    'holdCount'     => $holdCount,
                    'draftCount'    => $draftCount,
                    'filledCount'   => $filledCount,
                    'uploadedCount' => $uploadedCount,
                    'deniedCount'   => $deniedCount,
                ],
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('deactivateClaim error: ' . $e->getMessage());
            return response()->json(['success' => false, 'message' => 'Failed to deactivate claim. Please try again.'], 500);
        }
    }
}
