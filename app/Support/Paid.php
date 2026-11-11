<?php

namespace App\Support;

use Illuminate\Support\Facades\Schema;

/**
 * PAID-01 (6 Nov): one definition of "paid" for a month record (y{N}_monthexpensefinal row).
 * A month counts as paid when any of these holds:
 *  1. it has a pay date: Fin_PayDate, else payment_date (the DA-02 rule; legacy months were
 *     paid with only a date);
 *  2. payment_status is ConfirmedPayment and it has a valid bank UTR (utr_number, bank_reference
 *     or the linked F&F UTR), see PaymentUtr::clean; when PAYMENTS_REQUIRE_UTR is off the
 *     status alone is enough;
 *  3. no bank payment was due (utr_exempt_reason da_payroll / advance_adjusted), or it was paid
 *     with the F&F settlement (ff_utr linked). A plain ff_hold (still waiting for F&F) is not paid.
 * Columns that a company's table does not have (VVNR has no ff_utr / utr_exempt_reason, older
 * years only Fin_PayDate) are simply left out.
 */
class Paid
{
    public const COLUMNS = ['Fin_PayDate', 'payment_date', 'payment_status', 'utr_number', 'bank_reference', 'ff_utr', 'utr_exempt_reason'];

    private static array $colCache = [];

    /** DA-02 pay date (Y-m-d) of a month row, or null. */
    public static function date($row): ?string
    {
        $row = (array) $row;
        foreach (['Fin_PayDate', 'payment_date'] as $k) {
            $v = substr(trim((string) ($row[$k] ?? '')), 0, 10);
            if ($v !== '' && \App\Support\ZeroDate::has($v) && substr($v, 0, 4) > '2000') {
                return $v;
            }
        }

        return null;
    }

    public static function is($row): bool
    {
        $row = (array) $row;
        if (self::date($row) !== null) {
            return true;
        }
        $reason = $row['utr_exempt_reason'] ?? null;
        if (in_array($reason, PaymentUtr::NO_UTR_DUE, true) || PaymentUtr::clean($row['ff_utr'] ?? null) !== null) {
            return true;
        }
        if (($row['payment_status'] ?? null) === 'ConfirmedPayment') {
            return ! PaymentUtr::required()
                || PaymentUtr::clean($row['utr_number'] ?? null) !== null
                || PaymentUtr::clean($row['bank_reference'] ?? null) !== null;
        }

        return false;
    }

    /** The paid-rule columns this table has. */
    public static function existing(string $conn, string $table): array
    {
        $key = "$conn.$table";
        if (! isset(self::$colCache[$key])) {
            try {
                self::$colCache[$key] = array_values(array_intersect(self::COLUMNS, Schema::connection($conn)->getColumnListing($table)));
            } catch (\Throwable $e) {
                self::$colCache[$key] = ['Fin_PayDate'];
            }
        }

        return self::$colCache[$key];
    }

    /** Qualified select list ("mef.Fin_PayDate", ...) for the columns the table has. */
    public static function selects(string $conn, string $table, string $alias = 'mef'): array
    {
        return array_map(fn ($c) => "{$alias}.{$c}", self::existing($conn, $table));
    }

    /** SQL date expression (DA-02). */
    public static function dateSql(string $conn, string $table, string $alias = 'mef'): string
    {
        $cols = self::existing($conn, $table);
        $parts = [];
        foreach (['Fin_PayDate', 'payment_date'] as $c) {
            if (in_array($c, $cols, true)) {
                $parts[] = "NULLIF(NULLIF(DATE({$alias}.{$c}), '0000-00-00'), '1970-01-01')";
            }
        }

        return $parts ? 'COALESCE('.implode(', ', $parts).')' : 'NULL';
    }

    /** SQL boolean expression: the month row is paid. */
    public static function sql(string $conn, string $table, string $alias = 'mef'): string
    {
        $cols = self::existing($conn, $table);
        $has = fn ($c) => in_array($c, $cols, true);
        $or = ['('.self::dateSql($conn, $table, $alias).' IS NOT NULL)'];
        $noDue = "'".implode("','", PaymentUtr::NO_UTR_DUE)."'";
        if ($has('utr_exempt_reason')) {
            $or[] = "{$alias}.utr_exempt_reason IN ({$noDue})";
        }
        if ($has('ff_utr')) {
            $or[] = self::utrSql("{$alias}.ff_utr");
        }
        if ($has('payment_status')) {
            $utr = [];
            foreach (['utr_number', 'bank_reference'] as $c) {
                if ($has($c)) {
                    $utr[] = self::utrSql("{$alias}.{$c}");
                }
            }
            $or[] = "({$alias}.payment_status = 'ConfirmedPayment'"
                .(PaymentUtr::required() ? ' AND ('.($utr ? implode(' OR ', $utr) : '0').')' : '').')';
        }

        // COALESCE: NULL columns (no reason, no UTR) must give 0, not NULL, so NOT (...) works.
        return 'COALESCE(('.implode(' OR ', $or).'), 0)';
    }

    /** SQL twin of PaymentUtr::clean() !== null. */
    private static function utrSql(string $col): string
    {
        $core = "REGEXP_REPLACE(UPPER(IFNULL({$col}, '')), '[^A-Z0-9]', '')";

        return "(CHAR_LENGTH({$core}) >= 6 AND {$core} NOT REGEXP '^0+$' AND {$core} NOT IN ('NULL','NA','NIL','NONE','PENDING','TBD','NOTAVAILABLE'))";
    }
}
