<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;

class DuplicateExpenseController extends Controller
{
    /**
     * Check if user has permission to view duplicate expenses
     * Allowed for: Super Admin, Admin, Finance roles
     */
    private function authorizeUser()
    {
        $user = Auth::user();
        if (!$user) {
            abort(401, 'Unauthorized. Please log in.');
        }

        $allowedRoles = ['Super Admin', 'Admin', 'Finance'];
        $userRoles = $user->getRoleNames()->toArray();

        if (!array_intersect($allowedRoles, $userRoles)) {
            abort(403, 'Access Denied. Your roles: ' . implode(', ', $userRoles) . '. Allowed: ' . implode(', ', $allowedRoles));
        }
    }

    /**
     * Display duplicate expenses with enriched data
     */
    public function index()
    {
        $this->authorizeUser();

        $yearId = session('year_id');
        $connection = session('expense_connection');

        $fillTable = "y{$yearId}_g1_expensefilldata";
        $expTable = "y{$yearId}_expenseclaims";

        // Get duplicates grouped by ExpId
        $duplicates = DB::connection($connection)
            ->table($fillTable)
            ->selectRaw('COUNT(*) as count, ExpId')
            ->groupBy('ExpId')
            ->having(DB::raw('COUNT(*)'), '>', 1)
            ->orderBy('ExpId', 'desc')
            ->get();

        // Get detailed duplicate records with expense claim info
        $duplicateDetails = [];
        foreach ($duplicates as $dup) {
            $records = DB::connection($connection)
                ->table("{$fillTable} as fd")
                ->leftJoin("{$expTable} as ec", 'fd.ExpId', '=', 'ec.ExpId')
                ->where('fd.ExpId', $dup->ExpId)
                ->orderBy('fd.did', 'asc')
                ->select([
                    'fd.did',
                    'fd.ExpId',
                    'fd.DocName',
                    'fd.CityName',
                    'fd.HotelName',
                    'fd.BillNo',
                    'fd.BillDate',
                    'fd.JourneyFrom',
                    'fd.JourneyUpto',
                    'fd.CalAmount',
                    'ec.ClaimId',
                    'ec.Amount',
                    'ec.ClaimStatus',
                    'ec.CrDate',
                    'ec.BillDate as ClaimBillDate',
                    'ec.ClaimMonth',
                    'ec.CrBy',
                    'ec.FilledTAmt',
                    'ec.VerifyTAmt',
                    'ec.ApprTAmt',
                ])
                ->get();

            $duplicateDetails[] = [
                'expId' => $dup->ExpId,
                'count' => $dup->count,
                'records' => $records
            ];
        }

        $totalDuplicateRecords = $duplicates->sum('count');

        return view('admin.duplicate-expense', compact('duplicateDetails', 'yearId', 'totalDuplicateRecords'));
    }

    /**
     * Delete a single duplicate expense record
     */
    public function destroy(Request $request)
    {
        $this->authorizeUser();

        $request->validate([
            'did' => 'required|integer|min:1',
        ]);

        $yearId = session('year_id');
        $connection = session('expense_connection');
        $did = $request->input('did');

        try {
            $deleted = DB::connection($connection)
                ->table("y{$yearId}_g1_expensefilldata")
                ->where('did', $did)
                ->delete();

            if ($deleted) {
                return response()->json([
                    'status' => 'success',
                    'message' => 'Duplicate record (DID: ' . $did . ') deleted successfully.',
                ]);
            } else {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Record not found.',
                ], 404);
            }
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Error deleting record: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Bulk delete duplicate expense records
     */
    public function bulkDestroy(Request $request)
    {
        $this->authorizeUser();

        $request->validate([
            'dids' => 'required|array|min:1',
            'dids.*' => 'integer|min:1',
        ]);

        $yearId = session('year_id');
        $connection = session('expense_connection');
        $dids = $request->input('dids');

        try {
            $deletedCount = DB::connection($connection)
                ->table("y{$yearId}_g1_expensefilldata")
                ->whereIn('did', $dids)
                ->delete();

            return response()->json([
                'status' => 'success',
                'message' => "{$deletedCount} duplicate record(s) deleted successfully.",
                'data' => [
                    'deleted_count' => $deletedCount,
                    'requested_count' => count($dids),
                ]
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Error deleting records: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get duplicate records for a specific expense ID
     */
    public function getExpenseDuplicates($expId)
    {
        $this->authorizeUser();

        $yearId = session('year_id');
        $connection = session('expense_connection');

        try {
            $records = DB::connection($connection)
                ->table("y{$yearId}_g1_expensefilldata")
                ->where('ExpId', $expId)
                ->orderBy('did', 'asc')
                ->get();

            return response()->json([
                'status' => 'success',
                'data' => $records,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => $e->getMessage(),
            ], 500);
        }
    }
}
