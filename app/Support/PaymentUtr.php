<?php

namespace App\Support;

/**
 * UTR-01 (tracker: "Expense and advance UTR visible to employees"): a payment is not marked paid
 * without the bank's UTR. 120 FY2026-27 payments (52 expense months, 68 advances) were marked paid
 * with no UTR because the bank-file matching paths did not copy it. Switch: PAYMENTS_REQUIRE_UTR.
 */
class PaymentUtr
{
    public static function required(): bool
    {
        return (bool) config('app.payments_require_utr', true);
    }

    /**
     * Normalised UTR, or null when it is missing. UTR-03: placeholders such as "- -", "NIL",
     * "PENDING" or anything with fewer than 6 letters/digits are not a UTR (Finance's sheet used
     * "- -" for "no UTR"; before, that counted as a confirmed payment).
     */
    public static function clean($utr): ?string
    {
        $u = strtoupper(trim((string) $utr));
        $core = preg_replace('/[^A-Z0-9]/', '', $u);
        if ($core === '' || strlen($core) < 6 || preg_match('/^0+$/', $core)
            || in_array($core, ['NULL', 'NA', 'NIL', 'NONE', 'PENDING', 'TBD', 'NOTAVAILABLE'], true)) {
            return null;
        }

        return $u;
    }

    /**
     * UTR-04: the payment proof of a bank-file line - the UTR, or for a same-bank transfer (no UTR)
     * the bank's reference number. Returns [utr|null, reference|null]; both null = no proof.
     */
    public static function proof($utr, ...$references): array
    {
        $u = self::clean($utr);
        if ($u !== null) {
            return [$u, null];
        }
        foreach ($references as $r) {
            if (($c = self::clean($r)) !== null) {
                return [null, mb_substr($c, 0, 30)];
            }
        }

        return [null, null];
    }

    public static function missingProof($utr, ...$references): bool
    {
        [$u, $r] = self::proof($utr, ...$references);

        return self::required() && $u === null && $r === null;
    }

    public static function missing($utr): bool
    {
        return self::required() && self::clean($utr) === null;
    }

    /**
     * UTR-02: reasons Finance gives for a month marked paid with ₹0 / no UTR.
     *  ff_hold          paid later with the F&F settlement (the UTR comes from the F&F link)
     *  da_payroll       DA-only month, paid through payroll (audit B-04)
     *  advance_adjusted fully adjusted against an advance, nothing to pay
     */
    public const EXEMPT_REASONS = [
        'ff_hold' => 'Payment hold - payable with F&F',
        'da_payroll' => 'Not paid here - DA claims only (payroll)',
        'advance_adjusted' => 'Not paid here - adjusted with advance',
        // UTR-04 (Finance 7 Nov 2026): a transfer inside the same bank gets no UTR, only a bank
        // reference number; the reference is kept in bank_reference and counts as the payment proof.
        'same_bank_ref' => 'Same-bank transfer - bank reference no. instead of UTR',
    ];

    /** Reasons that mean no bank payment was due, so no UTR is expected. */
    public const NO_UTR_DUE = ['da_payroll', 'advance_adjusted'];

    /**
     * A month record (object/array with utr_number, bank_reference, ff_utr, utr_exempt_reason)
     * lacks a UTR that is still owed. da_payroll / advance_adjusted never owe one; ff_hold owes
     * the F&F UTR until it is linked.
     */
    public static function missingForMonth($row): bool
    {
        $row = (array) $row;
        if (in_array($row['utr_exempt_reason'] ?? null, self::NO_UTR_DUE, true)) {
            return false;
        }
        foreach (['utr_number', 'bank_reference', 'ff_utr'] as $k) {
            if (self::clean($row[$k] ?? null) !== null) {
                return false;
            }
        }

        return self::required();
    }

    /** Throws when the UTR is required and missing. */
    public static function assert($utr, string $what): string
    {
        $u = self::clean($utr);
        if ($u === null && self::required()) {
            throw new \RuntimeException("{$what}: no UTR, so it cannot be marked paid. Enter the bank UTR.");
        }

        return (string) $u;
    }
}
