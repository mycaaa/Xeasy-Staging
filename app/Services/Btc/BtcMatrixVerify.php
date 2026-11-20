<?php

namespace App\Services\Btc;

use App\Models\BtcCase;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

/**
 * BTC-MATRIX-01 (20 Nov 2026, tracker tab "Data Punching Form & Finance approval screens", item 9): after Treasury
 * records a payment, Xeasy sends it to Matrix (POST {url}/verify or {url}/verify-bulk) with the beneficiary account,
 * amount, date (Y-m-d), source "Xeasy_BTC" and the BTC ID as source_reference, so Matrix can match it with the bank.
 *
 * The beneficiary account is read only for the call and never stored (only its last 4 digits): the verified vendor
 * bank in BTC, else the core vendor master, else - when the bill is paid to the employee - the employee's bank in
 * HRIMS. Off until BTC_MATRIX_VERIFY_ENABLED=true and BTC_MATRIX_VERIFY_KEY are set in .env.
 */
class BtcMatrixVerify
{
    private ?array $coreAccounts = null;

    public static function ready(): bool
    {
        return Schema::hasTable('btc_payments') && Schema::hasColumn('btc_payments', 'matrix_status');
    }

    public static function enabled(): bool
    {
        return (bool) config('btc.matrix_verify.enabled') && (string) config('btc.matrix_verify.key') !== '' && (string) config('btc.matrix_verify.url') !== '';
    }

    /** Payments still to send (not sent, or failed with fewer than 5 tries). */
    public static function pending(?array $ids = null)
    {
        return DB::table('btc_payments')->where('status', 'success')
            ->where(fn ($q) => $q->whereNull('matrix_status')->orWhere(fn ($w) => $w->whereIn('matrix_status', ['failed', 'no_account'])->where('matrix_tries', '<', 5)))
            ->when($ids, fn ($q) => $q->whereIn('id', $ids))->orderBy('id');
    }

    /** @return array{ok:bool, message:string, sent?:int, verified?:int, mismatch?:int, failed?:int, no_account?:int} */
    public function run(?array $ids = null, bool $dryRun = false, int $limit = 500): array
    {
        if (! self::ready()) {
            return ['ok' => false, 'message' => 'Run the BTC database update first.'];
        }
        $payments = self::pending($ids)->limit($limit)->get();
        if ($payments->isEmpty()) {
            return ['ok' => true, 'message' => 'Nothing to send: every payment is already confirmed to Matrix.'];
        }
        if (! $dryRun && ! self::enabled()) {
            return ['ok' => false, 'message' => 'Sending to Matrix is off (BTC_MATRIX_VERIFY_ENABLED / BTC_MATRIX_VERIFY_KEY not set in .env). '.$payments->count().' payment(s) waiting.'];
        }
        $cases = BtcCase::whereIn('id', $payments->pluck('btc_case_id')->unique())->get()->keyBy('id');
        $items = [];
        $count = ['sent' => 0, 'verified' => 0, 'mismatch' => 0, 'failed' => 0, 'no_account' => 0];
        foreach ($payments as $p) {
            $c = $cases[$p->btc_case_id] ?? null;
            $acc = $c ? $this->account($c) : null;
            if (! $acc) {
                $count['no_account']++;
                if (! $dryRun) {
                    $this->mark($p->id, 'no_account', 'Beneficiary bank account not found (vendor bank / core vendor / employee bank).', null, true);
                }
                continue;
            }
            $items[] = ['id' => $p->id, 'last4' => substr($acc, -4), 'body' => [
                'beneficiary_account_number' => mb_substr($acc, 0, 50),
                'amount' => round((float) $p->amount, 2),
                'date' => \Carbon\Carbon::parse($p->paid_on ?: $p->created_at)->format('Y-m-d'),
                'source' => mb_substr((string) config('btc.matrix_verify.source', 'Xeasy_BTC'), 0, 100),
                'source_reference' => mb_substr(($c->case_no ?? 'BTC').($p->utr_number ? '/'.$p->utr_number : ''), 0, 100),
            ]];
        }
        if ($dryRun) {
            return ['ok' => true, 'message' => 'Check only: '.count($items).' payment(s) ready to send, '.$count['no_account'].' without a bank account.', 'sent' => 0] + $count;
        }
        foreach (array_chunk($items, max(1, (int) config('btc.matrix_verify.bulk_size', 100))) as $chunk) {
            $this->send($chunk, $count);
        }
        $msg = "Matrix: {$count['verified']} matched, {$count['mismatch']} not matched, {$count['sent']} sent (no result yet), {$count['failed']} failed, {$count['no_account']} without a bank account.";
        Log::info('btc matrix verify', $count);

        return ['ok' => $count['failed'] === 0, 'message' => $msg] + $count;
    }

    private function send(array $chunk, array &$count): void
    {
        $url = (string) config('btc.matrix_verify.url');
        $bulk = count($chunk) > 1;
        try {
            $http = Http::timeout(60)->acceptJson()->withHeaders([(string) config('btc.matrix_verify.key_header', 'X-API-Key') => (string) config('btc.matrix_verify.key')])
                ->withOptions(['verify' => \App\Support\TlsVerify::option()]);
            $res = $bulk ? $http->post($url.'/verify-bulk', ['payments' => array_column($chunk, 'body')]) : $http->post($url.'/verify', $chunk[0]['body']);
        } catch (\Throwable $e) {
            foreach ($chunk as $it) {
                $this->mark($it['id'], 'failed', 'Matrix not reachable: '.mb_substr($e->getMessage(), 0, 200), $it['last4'], true);
                $count['failed']++;
            }

            return;
        }
        $json = $res->json();
        if (! $res->successful()) {
            $why = 'HTTP '.$res->status().': '.mb_substr(self::message($json) ?: $res->body(), 0, 250);
            foreach ($chunk as $it) {
                $this->mark($it['id'], 'failed', $why, $it['last4'], true);
                $count['failed']++;
            }

            return;
        }
        // per-payment results when Matrix gives them (by position, or by source_reference)
        $results = is_array($json) ? ($json['results'] ?? $json['data'] ?? $json['payments'] ?? null) : null;
        foreach ($chunk as $i => $it) {
            $r = $bulk ? (is_array($results) ? (self::findResult($results, $it['body']['source_reference']) ?? ($results[$i] ?? null)) : null) : (is_array($results) && ! array_is_list($results) ? $results : $json);
            [$st, $msg] = self::outcome(is_array($r) ? $r : null, $json);
            $this->mark($it['id'], $st, $msg, $it['last4'], false);
            $count[$st]++;
        }
    }

    private static function findResult(array $results, string $ref): ?array
    {
        foreach ($results as $r) {
            if (is_array($r) && ($r['source_reference'] ?? null) === $ref) {
                return $r;
            }
        }

        return null;
    }

    /** verified | mismatch | sent, from what Matrix answered. */
    private static function outcome(?array $r, $whole): array
    {
        $msg = self::message($r) ?: self::message($whole) ?: 'Sent to Matrix';
        if ($r === null) {
            return ['sent', $msg];
        }
        foreach (['verified', 'matched', 'is_verified', 'match'] as $k) {
            if (array_key_exists($k, $r) && is_bool($r[$k])) {
                return [$r[$k] ? 'verified' : 'mismatch', $msg];
            }
        }
        $s = strtolower((string) ($r['status'] ?? $r['result'] ?? ''));
        if (in_array($s, ['verified', 'matched', 'match', 'success', 'found', 'ok'], true)) {
            return ['verified', $msg];
        }
        if (in_array($s, ['mismatch', 'not_matched', 'unmatched', 'not_found', 'failed', 'rejected'], true)) {
            return ['mismatch', $msg];
        }

        return ['sent', $msg];
    }

    private static function message($j): string
    {
        if (! is_array($j)) {
            return '';
        }
        $m = $j['message'] ?? $j['msg'] ?? $j['error'] ?? $j['remarks'] ?? '';

        // no account numbers in what is kept
        return mb_substr(preg_replace('/\d{6,}/', '***', is_string($m) ? $m : json_encode($m)), 0, 280);
    }

    private function mark(int $id, string $status, string $msg, ?string $last4, bool $try): void
    {
        DB::table('btc_payments')->where('id', $id)->update(array_filter([
            'matrix_status' => $status, 'matrix_sent_at' => now(), 'matrix_message' => mb_substr($msg, 0, 300),
            'matrix_account_last4' => $last4, 'updated_at' => now(),
        ], fn ($v) => $v !== null) + ($try ? ['matrix_tries' => DB::raw('matrix_tries + 1')] : []));
    }

    /** Beneficiary account for the case (read for the call only). */
    private function account(BtcCase $c): ?string
    {
        $clean = fn ($a) => ($a = preg_replace('/[^0-9A-Za-z]/', '', (string) $a)) !== '' && strlen($a) >= 6 ? strtoupper($a) : null;
        // 1. vendor bank verified in BTC
        if (Schema::hasTable('btc_vendor_banks') && ($c->vendor_gstin || $c->vendor_name)) {
            $b = DB::table('btc_vendor_banks')->where('status', 'verified')
                ->where(fn ($q) => $q->when($c->vendor_gstin, fn ($w) => $w->where('vendor_gstin', $c->vendor_gstin))->when(! $c->vendor_gstin, fn ($w) => $w->where('vendor_name', $c->vendor_name)))
                ->orderByDesc('verified_at')->first();
            if ($b) {
                try {
                    if ($a = $clean(Crypt::decryptString($b->account_no_enc))) {
                        return $a;
                    }
                } catch (\Throwable $e) {
                }
            }
        }
        // 2. core vendor master (bank account fields of the core list, read now and not kept)
        if ($c->vendor_code && ($a = $clean($this->coreAccount((string) $c->vendor_code)))) {
            return $a;
        }
        // 3. a bill paid to the employee (no vendor): the employee's salary account in HRIMS
        if (! $c->vendor_code && ! $c->vendor_name && $c->employee_id) {
            try {
                return $clean(DB::connection('hrims')->table('hrm_employee_general')->where('EmployeeID', $c->employee_id)->value('AccountNo'));
            } catch (\Throwable $e) {
            }
        }

        return null;
    }

    private function coreAccount(string $code): ?string
    {
        if ($this->coreAccounts === null) {
            $this->coreAccounts = [];
            $url = (string) config('btc.core_vendors.url');
            $key = (string) config('btc.core_vendors.key');
            if ($url !== '' && $key !== '') {
                try {
                    $res = Http::timeout(60)->withHeaders(['api-key' => $key, 'Accept' => 'application/json'])->withOptions(['verify' => \App\Support\TlsVerify::option()])->get($url);
                    foreach ((array) $res->json('list') as $v) {
                        if (! is_array($v) || empty($v['code'])) {
                            continue;
                        }
                        foreach (['bank_account_number', 'account_number', 'bank_account_no', 'account_no', 'acc_no', 'bank_account'] as $k) {
                            if (! empty($v[$k]) && is_scalar($v[$k])) {
                                $this->coreAccounts[(string) $v['code']] = (string) $v[$k];
                                break;
                            }
                        }
                    }
                } catch (\Throwable $e) {
                    report($e);
                }
            }
        }

        return $this->coreAccounts[$code] ?? null;
    }
}
