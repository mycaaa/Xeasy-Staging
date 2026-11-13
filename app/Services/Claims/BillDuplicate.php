<?php

namespace App\Services\Claims;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * DUP-BILL-01 (Finance decision 7 Nov 2026): the same bill claimed by another employee.
 *
 *  1. Punching: when the bill number punched for a claim is already on another employee's live
 *     claim (same bill / invoice / PNR number and bill date, and the same vendor - GSTIN, hotel or
 *     billing name - or the same amount), the punch is saved but the punch staff get an alert, and
 *     the match is recorded (bill_duplicate_flags).
 *  2. Verifier: the claim view shows a red panel naming the other employee and claim, so the
 *     verifier can check it before verifying. Shared bills can be genuine (a bill split between
 *     employees), so nothing is blocked. Past cases stay with Finance's manual review.
 */
class BillDuplicate
{
    public const TABLE = 'bill_duplicate_flags';

    public static function norm(?string $v): string
    {
        return strtoupper(preg_replace('/[^A-Za-z0-9]/', '', (string) $v));
    }

    /**
     * Other employees' live claims with the same bill.
     *
     * @return array<int, object> rows: ExpId, CrBy, ClaimId, ClaimStatus, FilledTAmt, BillNo, BillDate, match
     */
    public static function find(string $conn, int $yearId, int $expId): array
    {
        $g1 = "y{$yearId}_g1_expensefilldata";
        $ec = "y{$yearId}_expenseclaims";
        $db = DB::connection($conn);
        if (! Schema::connection($conn)->hasTable($g1)) {
            return [];
        }
        $me = $db->table("{$g1} as f")->join("{$ec} as e", 'e.ExpId', '=', 'f.ExpId')->where('f.ExpId', $expId)
            ->first(['e.ExpId', 'e.CrBy', 'e.FilledTAmt', 'f.BillNo', 'f.Invoice', 'f.PNRNo', 'f.BillDate', 'f.GSTIN', 'f.HotelName', 'f.BillingName']);
        if (! $me || empty($me->BillDate) || str_starts_with((string) $me->BillDate, '0000')) {
            return [];
        }
        $nums = array_values(array_unique(array_filter([self::norm($me->BillNo), self::norm($me->Invoice), self::norm($me->PNRNo)], fn ($n) => strlen($n) >= 3 && ! preg_match('/^0+$/', $n))));
        if (! $nums) {
            return [];
        }
        $expr = fn ($c) => "UPPER(REGEXP_REPLACE(COALESCE(f.{$c},''), '[^A-Za-z0-9]', ''))";
        $rows = $db->table("{$g1} as f")->join("{$ec} as e", 'e.ExpId', '=', 'f.ExpId')
            ->where('f.BillDate', $me->BillDate)->where('e.CrBy', '!=', $me->CrBy)->where('e.ExpId', '!=', $expId)
            ->whereNotIn('e.ClaimStatus', ['Deactivate', 'Deactivat', 'Draft'])
            ->where(function ($q) use ($expr, $nums) {
                foreach (['BillNo', 'Invoice', 'PNRNo'] as $c) {
                    $q->orWhereIn(DB::raw($expr($c)), $nums);
                }
            })
            ->limit(20)
            ->get(['e.ExpId', 'e.CrBy', 'e.ClaimId', 'e.ClaimStatus', 'e.FilledTAmt', 'f.BillNo', 'f.Invoice', 'f.PNRNo', 'f.BillDate', 'f.GSTIN', 'f.HotelName', 'f.BillingName']);

        $out = [];
        foreach ($rows as $r) {
            $why = [];
            if ($me->GSTIN && $r->GSTIN && self::norm($me->GSTIN) === self::norm($r->GSTIN)) {
                $why[] = 'same GSTIN';
            }
            foreach (['HotelName' => 'same hotel', 'BillingName' => 'same billing name'] as $col => $label) {
                if (strlen(self::norm($me->{$col})) >= 3 && self::norm($me->{$col}) === self::norm($r->{$col})) {
                    $why[] = $label;
                }
            }
            if ((float) $me->FilledTAmt > 0 && abs((float) $me->FilledTAmt - (float) $r->FilledTAmt) < 1) {
                $why[] = 'same amount';
            }
            if (! $why) {
                continue; // same number on the same day at another vendor: not the same bill
            }
            $r->match = 'same bill no. and date, '.implode(', ', $why);
            $out[] = $r;
        }

        return $out;
    }

    /** Record the matches found at punching; returns the alert text or null. */
    public static function flag(string $conn, int $yearId, int $expId, string $stage = 'punching'): ?string
    {
        try {
            $hits = self::find($conn, $yearId, $expId);
        } catch (\Throwable $e) {
            return null;
        }
        if (! $hits) {
            return null;
        }
        self::ensureTable($conn);
        foreach ($hits as $h) {
            DB::connection($conn)->table(self::TABLE)->updateOrInsert(
                ['year_id' => $yearId, 'exp_id' => $expId, 'other_exp_id' => (int) $h->ExpId],
                ['other_employee_id' => (int) $h->CrBy, 'bill_no' => mb_substr((string) ($h->BillNo ?: $h->Invoice ?: $h->PNRNo), 0, 40), 'bill_date' => $h->BillDate, 'match_reason' => mb_substr($h->match, 0, 120), 'stage' => $stage, 'flagged_at' => now()]
            );
        }
        $names = self::names(array_map(fn ($h) => (int) $h->CrBy, $hits));
        $list = implode('; ', array_map(fn ($h) => 'ExpId '.$h->ExpId.' of '.($names[(int) $h->CrBy] ?? 'employee '.$h->CrBy).' ('.$h->match.')', $hits));

        return 'This bill has already been claimed by another employee: '.$list.'. The claim is saved and highlighted for the verifier.';
    }

    public static function names(array $empIds): array
    {
        if (! $empIds) {
            return [];
        }
        try {
            return DB::connection('hrims')->table('hrm_employee')->whereIn('EmployeeID', array_unique($empIds))
                ->get(['EmployeeID', 'EmpCode', DB::raw("TRIM(CONCAT_WS(' ', Fname, NULLIF(Sname,''), Lname)) AS n")])
                ->mapWithKeys(fn ($e) => [(int) $e->EmployeeID => $e->EmpCode.' '.$e->n])->all();
        } catch (\Throwable $e) {
            return [];
        }
    }

    public static function ensureTable(string $conn): void
    {
        if (Schema::connection($conn)->hasTable(self::TABLE)) {
            return;
        }
        \App\Support\SafeTable::create($conn, self::TABLE, function ($t) {
            $t->id();
            $t->unsignedSmallInteger('year_id');
            $t->unsignedBigInteger('exp_id');
            $t->unsignedBigInteger('other_exp_id');
            $t->unsignedBigInteger('other_employee_id');
            $t->string('bill_no', 40)->nullable();
            $t->date('bill_date')->nullable();
            $t->string('match_reason', 120)->nullable();
            $t->string('stage', 20)->default('punching');
            $t->timestamp('flagged_at')->useCurrent();
            $t->unique(['year_id', 'exp_id', 'other_exp_id']);
        });
    }

    /** Red panel for the claim view (verifier / approver / punch). Empty string when no match. */
    public static function panelHtml(string $conn, int $yearId, int $expId): string
    {
        try {
            $hits = self::find($conn, $yearId, $expId);
        } catch (\Throwable $e) {
            return '';
        }
        if (! $hits) {
            return '';
        }
        $names = self::names(array_map(fn ($h) => (int) $h->CrBy, $hits));
        $li = '';
        foreach ($hits as $h) {
            $li .= '<li>ExpId <strong>'.(int) $h->ExpId.'</strong> - '.e($names[(int) $h->CrBy] ?? 'employee '.$h->CrBy)
                .' - ₹'.number_format((float) $h->FilledTAmt, 2).' - '.e($h->ClaimStatus).' <span class="text-muted">('.e($h->match).')</span></li>';
        }

        return '<div class="alert alert-danger mt-3 mb-0" id="dup-bill-alert"><strong>Same bill already claimed by another employee.</strong> '
            .'Check before verifying (a bill shared between employees can be genuine; then each should claim only their share).<ul class="mb-0 mt-1">'.$li.'</ul></div>';
    }
}
