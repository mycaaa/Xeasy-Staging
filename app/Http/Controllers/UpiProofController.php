<?php

namespace App\Http\Controllers;

use App\Helpers\DatabaseSwitcher;
use App\Services\Claims\UpiProof;
use App\Support\RecordAccess;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * UPI-PROOF-01: web side of the UPI payment proof (panel inside the claim view used by the
 * claimant, punch, verifier, approver and finance screens). See App\Services\Claims\UpiProof.
 */
class UpiProofController extends Controller
{
    /** @return array{0: string, 1: int, 2: int} connection, company, year */
    private function ctx(Request $request): array
    {
        $co = (int) ($request->input('company_id') ?: session('company_id', 1));
        $conn = $request->filled('company_id') ? DatabaseSwitcher::getExpenseConnection($co) : (string) (session('expense_connection') ?: DatabaseSwitcher::getExpenseConnection($co));
        $year = (int) ($request->input('year_id') ?: session('year_id'));
        abort_if($year <= 0, 422, 'Year missing.');

        return [$conn, $co, $year];
    }

    private function canChange(object $claim): bool
    {
        $user = auth()->user();
        if (RecordAccess::isStaff($user)) {
            return true;
        }

        return RecordAccess::selfId($user) === (int) $claim->CrBy && UpiProof::ownerMayChange($claim);
    }

    public static function panelHtml(string $conn, int $companyId, int $yearId, object $claim): string
    {
        $user = auth()->user();
        $staff = $user && RecordAccess::isStaff($user);
        $canAdd = $staff || ($user && RecordAccess::selfId($user) === (int) $claim->CrBy && UpiProof::ownerMayChange($claim));

        return view('claims.partials.upi-proof-panel', [
            'claim' => $claim, 'companyId' => $companyId, 'yearId' => $yearId,
            'canAdd' => $canAdd && ! in_array($claim->ClaimStatus, ['Deactivate', 'Deactivat'], true),
        ] + UpiProof::summary($conn, $yearId, $claim))->render();
    }

    public function panel(Request $request, int $expId)
    {
        [$conn, $co, $year] = $this->ctx($request);
        $claim = UpiProof::claim($conn, $year, $expId);
        abort_unless($claim, 404);
        abort_unless(RecordAccess::canViewClaim(auth()->user(), $expId, $year, $co), 403);

        return response()->json(['html' => self::panelHtml($conn, $co, $year, $claim)]);
    }

    public function store(Request $request, int $expId)
    {
        [$conn, $co, $year] = $this->ctx($request);
        $claim = UpiProof::claim($conn, $year, $expId);
        abort_unless($claim, 404);
        if (! $this->canChange($claim)) {
            return response()->json(['success' => false, 'message' => 'You cannot add a UPI proof to this claim now (it is already verified, or it is not your claim).'], 403);
        }
        $request->validate(['file' => 'required|file', 'utr' => 'required|string|max:60', 'amount' => 'required|numeric', 'paid_on' => 'required|date', 'payee' => 'nullable|string|max:120']);
        try {
            $row = UpiProof::add($conn, $co, $year, $expId, $request->file('file'), $request->only(['utr', 'amount', 'paid_on', 'payee']), 'web', RecordAccess::selfId(auth()->user()) ?: null, auth()->id());
        } catch (\InvalidArgumentException $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 422);
        }
        Log::info('UPI proof added', ['exp' => $expId, 'year' => $year, 'utr' => $row->utr, 'by' => auth()->id()]);

        return response()->json(['success' => true, 'message' => 'UPI proof attached.', 'html' => self::panelHtml($conn, $co, $year, UpiProof::claim($conn, $year, $expId))]);
    }

    public function remove(Request $request, int $id)
    {
        [$conn, $co] = $this->ctx($request);
        $row = DB::connection($conn)->table(UpiProof::TABLE)->where('id', $id)->whereNull('removed_at')->first();
        abort_unless($row, 404);
        $claim = UpiProof::claim($conn, (int) $row->year_id, (int) $row->exp_id);
        abort_unless($claim, 404);
        if (! $this->canChange($claim)) {
            return response()->json(['success' => false, 'message' => 'You cannot remove this UPI proof.'], 403);
        }
        UpiProof::remove($conn, $id, 'user:'.auth()->id());
        Log::info('UPI proof removed', ['id' => $id, 'exp' => $row->exp_id, 'by' => auth()->id()]);

        return response()->json(['success' => true, 'message' => 'UPI proof removed.', 'html' => self::panelHtml($conn, $co, (int) $row->year_id, $claim)]);
    }

    public function file(Request $request, int $id)
    {
        [$conn, $co] = $this->ctx($request);
        $row = DB::connection($conn)->table(UpiProof::TABLE)->where('id', $id)->first();
        abort_unless($row, 404);
        abort_unless(RecordAccess::canViewClaim(auth()->user(), (int) $row->exp_id, (int) $row->year_id, $co), 403);

        return UpiProof::fileResponse($row);
    }
}
