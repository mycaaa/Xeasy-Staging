<?php

namespace App\Http\Controllers;

use App\Services\Payments\UtrExemption;
use App\Support\PaymentUtr;
use Illuminate\Http\Request;

/** UTR-02: Expense Payments > No-UTR Reasons (months marked paid with ₹0 / no UTR). */
class UtrExemptionController extends Controller
{
    public function index()
    {
        $svc = UtrExemption::forSession();
        if (! $svc->ready()) {
            return view('expense-payments.utr-exemptions', ['ready' => false, 'groups' => [], 'reasons' => PaymentUtr::EXEMPT_REASONS]);
        }

        return view('expense-payments.utr-exemptions', [
            'ready' => true,
            'groups' => $svc->grouped(),
            'reasons' => PaymentUtr::EXEMPT_REASONS,
        ]);
    }

    public function setReason(Request $request)
    {
        $v = $request->validate([
            'employee_id' => 'required|integer',
            'month' => 'required|integer|between:1,12',
            'reason' => 'nullable|in:'.implode(',', array_keys(PaymentUtr::EXEMPT_REASONS)),
            'reference' => 'nullable|string|max:30',
        ]);
        try {
            UtrExemption::forSession()->setReason((int) $v['employee_id'], (int) $v['month'], $v['reason'] ?: null, auth()->id(), $v['reference'] ?? null);
        } catch (\Throwable $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 422);
        }

        return response()->json(['success' => true, 'message' => 'Reason saved.']);
    }

    public function linkFf(Request $request)
    {
        $v = $request->validate([
            'employee_id' => 'required|integer',
            'month' => 'required|integer|between:1,12',
            'ff_settlement_date' => 'required|date',
            'ff_settlement_amount' => 'required|numeric|min:0.01',
            'ff_utr' => 'required|string|max:60',
            'ff_voucher_no' => 'nullable|string|max:60',
        ]);
        try {
            $n = UtrExemption::forSession()->linkFfSettlement((int) $v['employee_id'], (int) $v['month'], $v, auth()->id());
        } catch (\Throwable $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 422);
        }

        return response()->json(['success' => true, 'message' => 'F&F settlement linked to '.$n.' held month(s); they now show as paid.']);
    }
}
