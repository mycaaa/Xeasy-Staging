<?php

namespace App\Services\ClaimFiles;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;

/**
 * FILE-FP-01 (10 Nov 2026): a SHA-256 fingerprint of every claim attachment, so the same bill photo or PDF
 * uploaded again (by the same or another employee, for another claim) is caught even when the bill number
 * was typed differently. New uploads are fingerprinted when stored (ClaimFileBatch::put); older ones and the
 * mobile API's uploads by "uploads:fingerprint". A reused file is shown as a warning on the punch / verifier
 * save and listed by the command; it does not block the save.
 */
class UploadFingerprint
{
    public const TABLE = 'upload_fingerprints';

    public static function ensureTable(?string $conn = null): void
    {
        $conn = $conn ?: config('database.default');
        if (Schema::connection($conn)->hasTable(self::TABLE)) {
            return;
        }
        \App\Support\SafeTable::create($conn, self::TABLE, function ($t) {
            $t->id();
            $t->char('sha256', 64)->index();
            $t->string('path', 255)->unique();
            $t->string('file_name', 190)->index();
            $t->unsignedInteger('year_id')->nullable();
            $t->unsignedInteger('employee_id')->nullable();
            $t->unsignedInteger('size')->default(0);
            $t->string('source', 20)->default('upload');
            $t->timestamp('created_at')->nullable();
        });
    }

    public static function record(string $path, string $name, string $contents, string $source = 'upload'): void
    {
        try {
            self::ensureTable();
            $y = $e = null;
            if (preg_match('#^Expense/(\d+)/(\d+)/#', $path, $m)) {
                $y = (int) $m[1];
                $e = (int) $m[2];
            }
            DB::table(self::TABLE)->updateOrInsert(['path' => $path], [
                'sha256' => hash('sha256', $contents), 'file_name' => mb_substr($name, 0, 190), 'year_id' => $y, 'employee_id' => $e,
                'size' => strlen($contents), 'source' => $source, 'created_at' => now(),
            ]);
        } catch (\Throwable $ex) {
            Log::warning('UploadFingerprint: '.$ex->getMessage());
        }
    }

    /** File names of one claim (claimuploads rows and the Img_1..Img_8 columns). */
    public static function claimFiles(string $conn, int $yearId, int $expId): array
    {
        $db = DB::connection($conn);
        $names = [];
        if (Schema::connection($conn)->hasTable("y{$yearId}_claimuploads")) {
            $names = $db->table("y{$yearId}_claimuploads")->where('ExpId', $expId)->pluck('FileName')->all();
        }
        $c = (array) $db->table("y{$yearId}_expenseclaims")->where('ExpId', $expId)->first();
        foreach ($c as $k => $v) {
            if (preg_match('/^Img_\d$/', $k) && is_string($v) && $v !== '') {
                $names[] = basename($v);
            }
        }

        return array_values(array_unique(array_filter($names)));
    }

    /**
     * Other live claims that carry a file identical to one of this claim's files.
     * @return array<int, array{exp_id:int, employee_id:int, status:string, file:string}>
     */
    public static function reused(string $conn, int $yearId, int $expId): array
    {
        if (! Schema::hasTable(self::TABLE)) {
            return [];
        }
        $mine = self::claimFiles($conn, $yearId, $expId);
        if (! $mine) {
            return [];
        }
        $hashes = DB::table(self::TABLE)->whereIn('file_name', $mine)->pluck('sha256')->unique()->all();
        if (! $hashes) {
            return [];
        }
        $others = DB::table(self::TABLE)->whereIn('sha256', $hashes)->whereNotIn('file_name', $mine)->get(['file_name', 'year_id']);
        $out = [];
        foreach ($others as $o) {
            $y = (int) ($o->year_id ?: $yearId);
            if (! Schema::connection($conn)->hasTable("y{$y}_claimuploads")) {
                continue;
            }
            $hit = DB::connection($conn)->table("y{$y}_claimuploads as u")->join("y{$y}_expenseclaims as e", 'e.ExpId', '=', 'u.ExpId')
                ->where('u.FileName', $o->file_name)->where('e.ExpId', '!=', $expId)->whereNotIn('e.ClaimStatus', ['Deactivate'])
                ->first(['e.ExpId', 'e.CrBy', 'e.ClaimStatus']);
            if ($hit) {
                $out[(int) $hit->ExpId] = ['exp_id' => (int) $hit->ExpId, 'employee_id' => (int) $hit->CrBy, 'status' => (string) $hit->ClaimStatus, 'file' => $o->file_name, 'year_id' => $y];
            }
        }

        return array_values($out);
    }

    public static function warning(string $conn, int $yearId, int $expId): ?string
    {
        try {
            $r = self::reused($conn, $yearId, $expId);
        } catch (\Throwable $e) {
            return null;
        }
        if (! $r) {
            return null;
        }
        $list = implode(', ', array_map(fn ($x) => 'ExpId '.$x['exp_id'].' (employee '.$x['employee_id'].', '.$x['status'].')', array_slice($r, 0, 5)));

        return 'The same file (identical copy) is already attached to '.$list.'. Check it is not the same bill claimed twice.';
    }

    /** Read a stored attachment for the backfill (S3 path of the app, then the public URL disk). */
    public static function fetch(int $yearId, int $empId, string $name): ?array
    {
        $path = "Expense/{$yearId}/{$empId}/{$name}";
        try {
            if (Storage::disk('s3')->exists($path)) {
                return [$path, Storage::disk('s3')->get($path)];
            }
        } catch (\Throwable $e) {
        }

        return null;
    }
}
