<?php

namespace App\Services\Claims;

use App\Services\ClaimFiles\ClaimFileBatch;
use App\Services\ClaimFiles\ClaimFileException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;

/**
 * UPI-PROOF-01 (7 Nov 2026): proof of a bill paid by UPI.
 *
 * Small vendors often give no proper bill, or the bill does not show how it was paid. The claimant
 * (or punch / verifier staff) attaches the UPI payment screenshot with the UPI transaction number
 * (UTR / UPI Ref No.), amount, date and payee. The verifier sees it on the claim with:
 *   - the same transaction number already used on another claim -> refused when it is added;
 *   - UPI total vs claim amount;
 *   - "bill says paid by UPI but no UPI proof" when the punched payment mode names UPI / GPay / PhonePe ...
 *
 * The screenshot is also a normal claim file (claimuploads UploadSequence 111), so the existing file
 * viewers show it, labelled "UPI Payment Proof".
 */
class UpiProof
{
    public const TABLE = 'claim_upi_proofs';

    public const SEQUENCE = 111;

    /** Statuses after which the claimant can no longer add or remove a proof (staff still can). */
    public const LOCKED_STATUSES = ['Verified', 'Approved', 'Financed', 'Paid'];

    public const UPI_MODE_PATTERN = '/\b(upi|g\s*\.?\s*pay|google\s*pay|phone\s*pe|phonepe|phone\s*pay|paytm|bhim|bharat\s*pe|ybl|okaxis|okicici|okhdfc|oksbi|amazon\s*pay|qr)\b/i';

    public static function normaliseUtr(?string $utr): string
    {
        return strtoupper(preg_replace('/[\s\-]/', '', (string) $utr));
    }

    /** 12-digit UPI reference (RRN) or an app transaction id (letters and digits, 10-35 long). */
    public static function utrValid(string $utr): bool
    {
        return (bool) preg_match('/^(\d{12}|[A-Z0-9]{10,35})$/', $utr);
    }

    public static function ensureTable(string $conn): void
    {
        if (Schema::connection($conn)->hasTable(self::TABLE)) {
            return;
        }
        Schema::connection($conn)->create(self::TABLE, function ($t) {
            $t->id();
            $t->unsignedSmallInteger('company_id')->default(1);
            $t->unsignedSmallInteger('year_id');
            $t->unsignedBigInteger('exp_id');
            $t->unsignedBigInteger('employee_id');
            $t->string('utr', 40);
            $t->decimal('amount', 12, 2);
            $t->date('paid_on');
            $t->string('payee', 120)->nullable();
            $t->string('file_disk', 10);
            $t->string('file_path', 255);
            $t->string('file_name', 150);
            $t->unsignedBigInteger('cu_id')->nullable();
            $t->unsignedBigInteger('added_by_emp')->nullable();
            $t->unsignedBigInteger('added_by_user')->nullable();
            $t->string('added_via', 10)->default('web');
            $t->timestamp('removed_at')->nullable();
            $t->string('removed_by', 40)->nullable();
            $t->timestamp('created_at')->useCurrent();
            $t->index('utr');
            $t->index(['year_id', 'exp_id']);
        });
    }

    public static function claim(string $conn, int $yearId, int $expId): ?object
    {
        return DB::connection($conn)->table("y{$yearId}_expenseclaims")->where('ExpId', $expId)
            ->first(['ExpId', 'ClaimId', 'CrBy', 'ClaimYearId', 'ClaimStatus', 'ClaimAtStep', 'BillDate', 'FilledTAmt', 'VerifyTAmt']);
    }

    public static function ownerMayChange(object $claim): bool
    {
        return ! in_array($claim->ClaimStatus, self::LOCKED_STATUSES, true) && ! in_array($claim->ClaimStatus, ['Deactivate', 'Deactivat'], true);
    }

    /** @return \Illuminate\Support\Collection<int, object> active proofs of a claim, newest first */
    public static function forClaim(string $conn, int $yearId, int $expId)
    {
        if (! Schema::connection($conn)->hasTable(self::TABLE)) {
            return collect();
        }

        return DB::connection($conn)->table(self::TABLE)->where('year_id', $yearId)->where('exp_id', $expId)
            ->whereNull('removed_at')->orderByDesc('id')->get();
    }

    /**
     * Add a proof. Throws \InvalidArgumentException with a message for the user.
     *
     * @param  array{utr: string, amount: mixed, paid_on: string, payee?: ?string}  $data
     */
    public static function add(string $conn, int $companyId, int $yearId, int $expId, UploadedFile $file, array $data, string $via, ?int $byEmp, ?int $byUser): object
    {
        $claim = self::claim($conn, $yearId, $expId);
        if (! $claim) {
            throw new \InvalidArgumentException('Claim not found.');
        }
        if (in_array($claim->ClaimStatus, ['Deactivate', 'Deactivat'], true)) {
            throw new \InvalidArgumentException('This claim is deactivated.');
        }
        $utr = self::normaliseUtr($data['utr'] ?? '');
        if (! self::utrValid($utr)) {
            throw new \InvalidArgumentException('Enter the UPI transaction number (12-digit UPI Ref No. / UTR, or the app transaction id).');
        }
        $amount = round((float) ($data['amount'] ?? 0), 2);
        if ($amount <= 0 || $amount > 1000000) {
            throw new \InvalidArgumentException('Enter the amount paid.');
        }
        $paidOn = date('Y-m-d', strtotime((string) ($data['paid_on'] ?? '')) ?: 0);
        if ($paidOn <= '2000-01-01' || $paidOn > date('Y-m-d')) {
            throw new \InvalidArgumentException('Enter the payment date (not in the future).');
        }
        $ext = strtolower($file->getClientOriginalExtension() ?: 'jpg');
        if (! in_array($ext, ['jpg', 'jpeg', 'png', 'webp', 'pdf'], true) || $file->getSize() > 10 * 1024 * 1024) {
            throw new \InvalidArgumentException('Attach the payment screenshot (jpg, png, webp or pdf, up to 10 MB).');
        }

        self::ensureTable($conn);
        $db = DB::connection($conn);

        $lock = substr('xeasy_upi_'.$db->getDatabaseName().'_'.$utr, 0, 64);
        if ((int) ($db->selectOne('SELECT GET_LOCK(?, 10) AS l', [$lock])->l ?? 0) !== 1) {
            throw new \InvalidArgumentException('Please try again in a moment.');
        }
        try {
            $used = $db->table(self::TABLE)->where('utr', $utr)->whereNull('removed_at')->orderBy('id')->first();
            if ($used) {
                throw new \InvalidArgumentException((int) $used->exp_id === $expId && (int) $used->year_id === $yearId
                    ? "UPI transaction {$utr} is already attached to this claim."
                    : "UPI transaction {$utr} is already used on another claim (ExpId {$used->exp_id}, employee {$used->employee_id}). One payment can support only one claim.");
            }

            $s3 = (string) config('filesystems.disks.s3.bucket') !== '';
            $disk = $s3 ? 's3' : 'local';
            $name = 'UPI_'.$expId.'_'.date('dmyHis').ClaimFileBatch::uniqueSuffix().'.'.$ext;
            $base = $companyId === 11 ? 'VVNR_Expense' : 'Expense';
            $path = $s3 ? "{$base}/{$claim->ClaimYearId}/{$claim->CrBy}/{$name}" : "upi_proof/{$companyId}/{$yearId}/{$claim->CrBy}/{$name}";

            $files = new ClaimFileBatch($disk);
            try {
                $files->put($path, $file, 'UPI payment screenshot', $name);
            } catch (ClaimFileException $e) {
                throw new \InvalidArgumentException($e->getMessage());
            }

            return $files->commitWith($conn, function () use ($db, $s3, $yearId, $expId, $claim, $companyId, $utr, $amount, $paidOn, $data, $disk, $path, $name, $byEmp, $byUser, $via) {
                $cuId = null;
                if ($s3) {
                    $cuId = $db->table("y{$yearId}_claimuploads")->insertGetId(['ExpId' => $expId, 'FileName' => $name, 'UploadSequence' => self::SEQUENCE]);
                }
                $id = $db->table(self::TABLE)->insertGetId([
                    'company_id' => $companyId, 'year_id' => $yearId, 'exp_id' => $expId, 'employee_id' => (int) $claim->CrBy,
                    'utr' => $utr, 'amount' => $amount, 'paid_on' => $paidOn, 'payee' => mb_substr(trim((string) ($data['payee'] ?? '')), 0, 120) ?: null,
                    'file_disk' => $disk, 'file_path' => $path, 'file_name' => $name, 'cu_id' => $cuId,
                    'added_by_emp' => $byEmp, 'added_by_user' => $byUser, 'added_via' => $via, 'created_at' => now(),
                ]);

                return $db->table(self::TABLE)->where('id', $id)->first();
            });
        } finally {
            $db->select('SELECT RELEASE_LOCK(?)', [$lock]);
        }
    }

    public static function remove(string $conn, int $id, string $by): ?object
    {
        $db = DB::connection($conn);
        $row = $db->table(self::TABLE)->where('id', $id)->whereNull('removed_at')->first();
        if (! $row) {
            return null;
        }
        $db->transaction(function () use ($db, $row, $by) {
            $db->table(self::TABLE)->where('id', $row->id)->update(['removed_at' => now(), 'removed_by' => mb_substr($by, 0, 40)]);
            if ($row->cu_id) {
                $db->table("y{$row->year_id}_claimuploads")->where('cuId', $row->cu_id)->where('UploadSequence', self::SEQUENCE)->delete();
            }
        });

        return $row;
    }

    /** Payment mode punched on the bill (lodging fill data) - used for the "no proof" warning. */
    public static function billPaymentMode(string $conn, int $yearId, int $expId): ?string
    {
        foreach (["y{$yearId}_g1_expensefilldata" => 'PaymentMode'] as $table => $col) {
            try {
                if (Schema::connection($conn)->hasColumn($table, $col)) {
                    $v = DB::connection($conn)->table($table)->where('ExpId', $expId)->value($col);
                    if ($v !== null && trim($v) !== '') {
                        return trim($v);
                    }
                }
            } catch (\Throwable $e) {
                // table missing for this year
            }
        }

        return null;
    }

    /** Everything the panel shows. */
    public static function summary(string $conn, int $yearId, object $claim): array
    {
        $proofs = self::forClaim($conn, $yearId, (int) $claim->ExpId);
        $mode = self::billPaymentMode($conn, $yearId, (int) $claim->ExpId);
        $claimAmt = (float) ($claim->VerifyTAmt ?: $claim->FilledTAmt ?: 0);
        $total = round((float) $proofs->sum('amount'), 2);

        return [
            'proofs' => $proofs,
            'payment_mode' => $mode,
            'mode_is_upi' => $mode !== null && (bool) preg_match(self::UPI_MODE_PATTERN, $mode),
            'claim_amount' => $claimAmt,
            'upi_total' => $total,
            'short' => $proofs->isNotEmpty() && $claimAmt > 0 && $total + 0.5 < $claimAmt,
        ];
    }

    public static function fileResponse(object $row)
    {
        $disk = Storage::disk($row->file_disk);
        abort_unless($disk->exists($row->file_path), 404);
        if ($row->file_disk === 's3') {
            try {
                return redirect()->away($disk->temporaryUrl($row->file_path, now()->addMinutes(10)));
            } catch (\Throwable $e) {
                // driver without temporary URLs: stream it
            }
        }

        return $disk->response($row->file_path, $row->file_name);
    }
}
