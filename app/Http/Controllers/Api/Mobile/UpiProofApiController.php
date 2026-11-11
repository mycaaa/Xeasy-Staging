<?php

namespace App\Http\Controllers\Api\Mobile;

use App\Services\Claims\UpiProof;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

/**
 * UPI-PROOF-01: the app's side of the UPI payment proof.
 *   claim.php?value=upi_proof_upload  empid, tokenid, YearId, ComId, ExpId, utr, amount, paid_on (Y-m-d), payee, upi_img (file)
 *   claim.php?value=upi_proof_list    empid, tokenid, YearId, ComId, ExpId
 * Only the claimant, and only before the claim is verified (web staff can add later).
 */
class UpiProofApiController extends BaseMobileController
{
    private function claimFor(Request $request): array
    {
        $empId = (int) $request->input('empid');
        $yearId = $this->getYearId($request);
        $comId = $this->getComId($request);
        $expId = (int) $request->input('ExpId', $request->input('expid', 0));
        if ($empId <= 0 || $yearId <= 0 || $expId <= 0) {
            return [null, 'Parameter Missing!'];
        }
        if (! $this->validateToken($request)) {
            return [null, 'Invalid Employee!'];
        }
        $conn = $this->getExpenseConnection($comId);
        $claim = UpiProof::claim($conn, $yearId, $expId);
        if (! $claim || (int) $claim->CrBy !== $empId) {
            return [null, 'Claim not found.'];
        }

        return [[$conn, $comId, $yearId, $expId, $empId, $claim], null];
    }

    public function upload(Request $request): JsonResponse
    {
        [$c, $err] = $this->claimFor($request);
        if ($err) {
            return $this->error($err);
        }
        [$conn, $comId, $yearId, $expId, $empId, $claim] = $c;
        if (! UpiProof::ownerMayChange($claim)) {
            return $this->error('This claim is already verified. Ask the verifier to attach the UPI proof.');
        }
        if (! $request->hasFile('upi_img')) {
            return $this->error('UPI screenshot missing!');
        }
        try {
            $row = UpiProof::add($conn, $comId, $yearId, $expId, $request->file('upi_img'), [
                'utr' => (string) $request->input('utr', ''), 'amount' => $request->input('amount'),
                'paid_on' => (string) $request->input('paid_on', ''), 'payee' => $request->input('payee'),
            ], 'app', $empId, null);
        } catch (\InvalidArgumentException $e) {
            return $this->error($e->getMessage());
        } catch (\Throwable $e) {
            Log::error('UPI proof upload failed: '.$e->getMessage());

            return $this->error('Upload failed. Please try again.');
        }

        return $this->success(['id' => $row->id, 'utr' => $row->utr], 'UPI proof attached');
    }

    public function list(Request $request): JsonResponse
    {
        [$c, $err] = $this->claimFor($request);
        if ($err) {
            return $this->error($err);
        }
        [$conn, , $yearId, , , $claim] = $c;
        $s = UpiProof::summary($conn, $yearId, $claim);

        return $this->success([
            'proofs' => $s['proofs']->map(fn ($p) => ['id' => $p->id, 'utr' => $p->utr, 'amount' => (float) $p->amount, 'paid_on' => $p->paid_on, 'payee' => $p->payee])->values(),
            'upi_total' => $s['upi_total'], 'claim_amount' => $s['claim_amount'], 'payment_mode' => $s['payment_mode'],
            'can_add' => UpiProof::ownerMayChange($claim),
        ]);
    }
}
