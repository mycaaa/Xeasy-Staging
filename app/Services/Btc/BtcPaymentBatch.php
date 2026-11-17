<?php

namespace App\Services\Btc;

use App\Models\BtcCase;
use App\Models\BtcCaseLog;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * BTC-BATCH-01 (17 Nov 2026): bank bulk-payment file and the bank's response.
 *
 * The Treasury maker picks payment-ready cases whose vendor bank account is verified; the batch makes one
 * payment entry per case (status pending, no UTR yet) and the bank upload file (same columns as the bank file
 * Xeasy already imports for employee payments). After the bank runs it, the bank's response file is imported:
 * each row is matched by its transaction reference; SUCCESS rows get the UTR, FAILED rows are marked failed with
 * the bank's error. A Treasury checker (not the batch maker) then authorises the paid entries, which pays the case.
 */
class BtcPaymentBatch
{
    public const COLUMNS = ['Record No', 'Payment Type', 'Debit Account Number', 'Beneficiary Name', 'Beneficiary Account Number', 'IFSC Code',
        'Amount', 'Value Date', 'Transaction Ref No', 'Credit Reference', 'Currency', 'Beneficiary Email'];

    /** Payment-ready cases that can go into a batch, with their bank account (or why not). */
    public static function candidates(int $companyId): Collection
    {
        return BtcCase::where('company_id', $companyId)->where('status', 'dept_approved')
            ->where(fn ($w) => $w->whereNull('on_hold')->orWhere('on_hold', 0))
            ->whereNotExists(fn ($p) => $p->from('btc_payments')->whereColumn('btc_payments.btc_case_id', 'btc_cases.id')->where('btc_payments.status', 'pending'))
            ->orderBy('id')->get()->map(function ($c) {
                $bank = BtcBank::forCase($c);
                $due = round((float) $c->net_payable - (float) $c->paid_amount, 2);

                return (object) ['case' => $c, 'bank' => $bank, 'due' => $due, 'blocked' => ! $bank ? 'no verified bank account for this vendor' : ($due <= 0 ? 'nothing due' : null)];
            });
    }

    /** @return array{batch:object, rows:array} */
    public static function make(array $caseIds, string $valueDate, $user): array
    {
        $co = (int) session('company_id', 1);
        $debit = (string) config('btc.bank.debit_account', '');
        if ($debit === '') {
            throw new RuntimeException('Set the company debit account number on BTC Settings first.');
        }
        $pick = self::candidates($co)->filter(fn ($x) => in_array($x->case->id, $caseIds, true) && ! $x->blocked)->values();
        if ($pick->isEmpty()) {
            throw new RuntimeException('Choose at least one payment-ready case with a verified vendor bank account.');
        }

        return DB::transaction(function () use ($pick, $valueDate, $user, $co, $debit) {
            $no = 'BTCB'.now('Asia/Kolkata')->format('ymdHis');
            $bid = DB::table('btc_payment_batches')->insertGetId(['company_id' => $co, 'batch_no' => $no, 'cases' => $pick->count(),
                'total' => $pick->sum('due'), 'created_by' => $user->id, 'created_by_name' => $user->name, 'created_at' => now(), 'updated_at' => now()]);
            $rows = [];
            foreach ($pick as $i => $x) {
                $c = $x->case;
                $ref = $no.str_pad((string) ($i + 1), 3, '0', STR_PAD_LEFT);
                $mode = $x->due >= 200000 ? 'rtgs' : 'neft';
                DB::table('btc_payments')->insert(['btc_case_id' => $c->id, 'amount' => $x->due, 'paid_on' => $valueDate, 'mode' => $mode,
                    'utr_number' => null, 'bank_ref' => $ref, 'bank_txn_ref' => $ref, 'batch_id' => $bid, 'status' => 'pending',
                    'created_by' => $user->id, 'created_at' => now(), 'updated_at' => now()]);
                BtcCaseLog::create(['btc_case_id' => $c->id, 'action' => 'payment_batch', 'from_status' => $c->status, 'to_status' => $c->status,
                    'remark' => 'In bank payment batch '.$no.': Rs '.number_format($x->due, 2).' to '.BtcBank::masked($x->bank), 'user_id' => $user->id, 'user_name' => $user->name]);
                $rows[] = [$i + 1, strtoupper($mode), $debit, $x->bank->account_name, BtcBank::accountNo($x->bank), $x->bank->ifsc, number_format($x->due, 2, '.', ''),
                    \Carbon\Carbon::parse($valueDate)->format('d-m-Y'), $ref, mb_substr($c->case_no.' '.$c->bill_no, 0, 30), 'INR', ''];
            }

            return ['batch' => DB::table('btc_payment_batches')->find($bid), 'rows' => $rows];
        });
    }

    /** The upload file rows of a batch (built at download time; account numbers are not kept in a file). */
    public static function rows(int $batchId): array
    {
        $debit = (string) config('btc.bank.debit_account', '');
        $rows = [];
        foreach (DB::table('btc_payments')->where('batch_id', $batchId)->whereIn('status', ['pending', 'success'])->orderBy('id')->get() as $i => $p) {
            $c = BtcCase::find($p->btc_case_id);
            $bank = $c ? BtcBank::forCase($c) : null;
            if (! $c || ! $bank) {
                continue;
            }
            $rows[] = [$i + 1, strtoupper((string) $p->mode), $debit, $bank->account_name, BtcBank::accountNo($bank), $bank->ifsc, number_format((float) $p->amount, 2, '.', ''),
                \Carbon\Carbon::parse($p->paid_on)->format('d-m-Y'), $p->bank_txn_ref, mb_substr($c->case_no.' '.$c->bill_no, 0, 30), 'INR', ''];
        }

        return $rows;
    }

    /**
     * The bank's response (xlsx/csv with a header row). Columns are found by name: transaction ref, UTR, status,
     * amount, error. Returns counts and per-row notes.
     */
    public static function importResponse(string $path, $user): array
    {
        $sheet = \PhpOffice\PhpSpreadsheet\IOFactory::load($path)->getActiveSheet()->toArray(null, true, true, false);
        $head = array_map(fn ($h) => strtolower(preg_replace('/[^a-z0-9]/i', '', (string) $h)), array_shift($sheet) ?? []);
        $col = function (array $names) use ($head) {
            foreach ($names as $n) {
                if (($i = array_search($n, $head, true)) !== false) {
                    return $i;
                }
            }

            return null;
        };
        $iRef = $col(['transactionrefno', 'transactionreference', 'customerreference', 'transactionref', 'refno']);
        $iUtr = $col(['rbiutrreferencenumber', 'utr', 'utrno', 'utrnumber', 'bankreference']);
        $iSt = $col(['transactionstatus', 'status']);
        $iAmt = $col(['amount', 'transactionamount']);
        $iErr = $col(['errordescription', 'error', 'reason', 'remarks']);
        if ($iRef === null || $iSt === null) {
            throw new RuntimeException('The file needs the columns "Transaction Ref No" and "Transaction Status" (and the UTR for paid rows).');
        }
        $out = ['paid' => 0, 'failed' => 0, 'skipped' => 0, 'notes' => []];
        $batches = [];
        foreach ($sheet as $n => $r) {
            $ref = trim((string) ($r[$iRef] ?? ''));
            if ($ref === '') {
                continue;
            }
            $p = DB::table('btc_payments')->where('bank_txn_ref', $ref)->first();
            if (! $p || $p->status !== 'pending') {
                $out['skipped']++;
                $out['notes'][] = 'Row '.($n + 2).": {$ref} ".(! $p ? 'not a BTC batch payment' : 'already '.$p->status);
                continue;
            }
            $case = BtcCase::find($p->btc_case_id);
            $status = strtoupper(trim((string) $r[$iSt]));
            if ($iAmt !== null && abs((float) str_replace(',', '', (string) $r[$iAmt]) - (float) $p->amount) > 0.009) {
                $out['skipped']++;
                $out['notes'][] = 'Row '.($n + 2).": {$ref} amount differs from the batch (Rs ".number_format((float) $p->amount, 2).'), left for checking';
                continue;
            }
            if (in_array($status, ['SUCCESS', 'SUCCESSFUL', 'PAID', 'PROCESSED', 'COMPLETED'], true)) {
                $utr = strtoupper(preg_replace('/[^A-Za-z0-9]/', '', (string) ($iUtr !== null ? $r[$iUtr] : '')));
                if (strlen($utr) < 6) {
                    $out['skipped']++;
                    $out['notes'][] = 'Row '.($n + 2).": {$ref} success but no UTR";
                    continue;
                }
                DB::table('btc_payments')->where('id', $p->id)->update(['utr_number' => $utr, 'updated_at' => now()]);
                BtcCaseLog::create(['btc_case_id' => $p->btc_case_id, 'action' => 'bank_response', 'from_status' => $case->status, 'to_status' => $case->status,
                    'remark' => "Bank paid Rs ".number_format((float) $p->amount, 2)." UTR {$utr}; waiting for Treasury checker", 'user_id' => $user->id, 'user_name' => $user->name]);
                $out['paid']++;
            } else {
                $err = mb_substr(trim((string) ($iErr !== null ? $r[$iErr] : '')) ?: $status, 0, 300);
                DB::table('btc_payments')->where('id', $p->id)->update(['status' => 'failed', 'failed_reason' => 'Bank: '.$err, 'failed_by' => $user->id, 'failed_at' => now(), 'updated_at' => now()]);
                BtcCaseLog::create(['btc_case_id' => $p->btc_case_id, 'action' => 'bank_response', 'from_status' => $case->status, 'to_status' => $case->status,
                    'remark' => "Bank payment failed: {$err} (back to payment-ready)", 'user_id' => $user->id, 'user_name' => $user->name]);
                $out['failed']++;
            }
            $batches[$p->batch_id] = true;
        }
        foreach (array_keys($batches) as $b) {
            DB::table('btc_payment_batches')->where('id', $b)->update(['status' => 'response_imported', 'response_at' => now(),
                'response_summary' => "paid {$out['paid']}, failed {$out['failed']}, skipped {$out['skipped']}", 'updated_at' => now()]);
        }

        return $out;
    }
}
