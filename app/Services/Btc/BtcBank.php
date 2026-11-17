<?php

namespace App\Services\Btc;

use App\Models\BtcCase;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use RuntimeException;

/**
 * BTC-BANK-01 (17 Nov 2026): vendor bank accounts for BTC payments. One person enters (Treasury maker / Finance),
 * another verifies (Treasury checker / BTC admin); only a verified account is paid. A change is a new entry that
 * replaces the old one only when verified. The account number is stored encrypted; screens show the last 4 digits.
 */
class BtcBank
{
    public static function ready(): bool
    {
        return Schema::hasTable('btc_vendor_banks');
    }

    /** The verified account for a case's vendor (vendor id, else GSTIN, else exact name). */
    public static function forCase(BtcCase $c): ?object
    {
        if (! self::ready()) {
            return null;
        }
        $q = fn () => DB::table('btc_vendor_banks')->where('company_id', $c->company_id)->where('status', 'verified')->orderByDesc('id');
        if ($c->vendor_id && ($r = $q()->where('vendor_id', $c->vendor_id)->first())) {
            return $r;
        }
        if ($c->vendor_gstin && ($r = $q()->where('vendor_gstin', strtoupper($c->vendor_gstin))->first())) {
            return $r;
        }

        return $c->vendor_name ? $q()->where('vendor_name', $c->vendor_name)->first() : null;
    }

    public static function masked(?object $b): ?string
    {
        return $b ? trim($b->account_name.' · XXXX'.$b->account_last4.' · '.$b->ifsc.($b->bank_name ? ' · '.$b->bank_name : '')) : null;
    }

    public static function accountNo(object $b): string
    {
        return Crypt::decryptString($b->account_no_enc);
    }

    public static function enter(array $v, $user): int
    {
        $acc = preg_replace('/\s+/', '', (string) $v['account_no']);
        $ifsc = strtoupper(trim((string) $v['ifsc']));
        if (! preg_match('/^[0-9]{6,18}$/', $acc)) {
            throw new RuntimeException('Account number: 6 to 18 digits.');
        }
        if ($acc !== preg_replace('/\s+/', '', (string) ($v['account_no_confirm'] ?? ''))) {
            throw new RuntimeException('The two account numbers do not match.');
        }
        if (! preg_match('/^[A-Z]{4}0[A-Z0-9]{6}$/', $ifsc)) {
            throw new RuntimeException('IFSC must be 11 characters like HDFC0001234.');
        }
        $gstin = strtoupper(trim((string) ($v['vendor_gstin'] ?? ''))) ?: null;

        return DB::table('btc_vendor_banks')->insertGetId([
            'company_id' => (int) session('company_id', 1), 'vendor_id' => (int) ($v['vendor_id'] ?? 0) ?: null, 'vendor_name' => trim((string) $v['vendor_name']),
            'vendor_gstin' => $gstin, 'account_name' => trim((string) $v['account_name']), 'account_no_enc' => Crypt::encryptString($acc),
            'account_last4' => substr($acc, -4), 'ifsc' => $ifsc, 'bank_name' => trim((string) ($v['bank_name'] ?? '')) ?: null,
            'status' => 'pending', 'remark' => trim((string) ($v['remark'] ?? '')) ?: null,
            'entered_by' => $user->id, 'entered_by_name' => $user->name, 'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    public static function decide(int $id, bool $approve, string $remark, $user): object
    {
        $b = DB::table('btc_vendor_banks')->where('id', $id)->where('status', 'pending')->first();
        if (! $b) {
            throw new RuntimeException('This entry is not waiting for verification.');
        }
        if ((int) $b->entered_by === (int) $user->id) {
            throw new RuntimeException('You entered these bank details; another person must verify them.');
        }
        if (! $approve && mb_strlen(trim($remark)) < 5) {
            throw new RuntimeException('Give the reason for rejecting.');
        }
        DB::transaction(function () use ($b, $approve, $remark, $user) {
            if ($approve) {
                // the new account replaces the vendor's earlier verified one
                DB::table('btc_vendor_banks')->where('company_id', $b->company_id)->where('status', 'verified')->where('id', '!=', $b->id)
                    ->where(fn ($q) => $q->when($b->vendor_id, fn ($w) => $w->orWhere('vendor_id', $b->vendor_id))
                        ->when($b->vendor_gstin, fn ($w) => $w->orWhere('vendor_gstin', $b->vendor_gstin))->orWhere('vendor_name', $b->vendor_name))
                    ->update(['status' => 'replaced', 'updated_at' => now()]);
            }
            DB::table('btc_vendor_banks')->where('id', $b->id)->update(['status' => $approve ? 'verified' : 'rejected',
                'remark' => trim($remark) ?: $b->remark, 'verified_by' => $user->id, 'verified_by_name' => $user->name, 'verified_at' => now(), 'updated_at' => now()]);
        });

        return DB::table('btc_vendor_banks')->find($b->id);
    }
}
