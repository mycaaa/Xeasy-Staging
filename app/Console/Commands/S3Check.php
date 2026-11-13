<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;

/**
 * S3-SEC-01 (13 Nov 2026): checks the claim-file bucket set in .env end to end, so a test host can be
 * proven before testers upload bills:
 *   1. it is not the live bucket (on a test host);
 *   2. a small file can be written, read back and deleted (the IAM policy allows the app's own work);
 *   3. the file is NOT readable without credentials (public access blocked);
 *   4. the link the app hands out works (signed link for a private bucket).
 * Nothing else in the bucket is touched; the test file is always removed.
 *
 *   php artisan files:s3-check
 */
class S3Check extends Command
{
    protected $signature = 'files:s3-check';

    protected $description = 'Check the claim-file bucket: write, read, delete, public access blocked, view link';

    public function handle(): int
    {
        $cfg = config('filesystems.disks.s3');
        if (($cfg['driver'] ?? '') !== 's3') {
            return $this->checkLocal((string) ($cfg['root'] ?? storage_path('app/s3_local')));
        }
        $bucket = (string) ($cfg['bucket'] ?? '');
        $this->line('Bucket: '.($bucket ?: '(not set)').'  region: '.($cfg['region'] ?? '?').'  private: '.(! empty($cfg['private_bucket']) ? 'yes' : 'no'));
        if ($bucket === '' || empty($cfg['key'])) {
            $this->error('AWS_BUCKET / AWS_ACCESS_KEY_ID are not set in .env.');
            return self::FAILURE;
        }
        $live = array_filter(array_map('trim', explode(',', (string) env('S3_LIVE_BUCKETS', 'developerinvnr.bkt'))));
        if (! app()->environment('production') && in_array($bucket, $live, true)) {
            $this->error("This is not the production app but AWS_BUCKET is the live bucket ({$bucket}). Use a test bucket.");
            return self::FAILURE;
        }

        $disk = Storage::disk('s3');
        $key = 'xeasy-healthcheck/'.now()->format('YmdHis').'_'.bin2hex(random_bytes(4)).'.txt';
        $body = 'Xeasy S3 check '.now()->toDateTimeString();
        $ok = true;
        try {
            $this->check('write', fn () => $disk->put($key, $body) !== false);
            $this->check('read back', fn () => $disk->get($key) === $body);

            $plain = rtrim((string) ($cfg['url'] ?? ''), '/') ?: "https://{$bucket}.s3.{$cfg['region']}.amazonaws.com";
            $status = Http::timeout(15)->get($plain.'/'.$key)->status();
            if ($status === 200) {
                $ok = false;
                $this->error("  public access: OPEN - the file can be read without credentials (HTTP 200). Turn on Block Public Access.");
            } else {
                $this->info("  public access: blocked (HTTP {$status})");
            }

            $view = app(\App\Services\S3Service::class)->getFileViewUrl($key);
            $vs = $view ? Http::timeout(15)->get($view)->status() : 0;
            if ($vs === 200) {
                $this->info('  view link: works'.(str_contains($view, 'X-Amz-Signature') ? ' (signed, expires)' : ''));
            } else {
                $ok = false;
                $this->error("  view link: HTTP {$vs}".(empty($cfg['private_bucket']) ? ' - set AWS_PRIVATE_BUCKET=true for a private bucket' : ''));
            }
        } catch (\Throwable $e) {
            $ok = false;
            $this->error('  failed: '.$e->getMessage());
        } finally {
            try {
                $disk->delete($key);
                $this->check('delete', fn () => ! $disk->exists($key));
            } catch (\Throwable $e) {
                $ok = false;
                $this->error('  delete failed: '.$e->getMessage().' (remove '.$key.' by hand)');
            }
        }

        $ok ? $this->info('Bucket ready for testing.') : $this->error('Bucket NOT ready - see above.');

        return $ok ? self::SUCCESS : self::FAILURE;
    }

    /** FILES-PERM-01: CLAIM_FILES_LOCAL=true - can this user (run it as the web user) store, read and delete a file? */
    private function checkLocal(string $root): int
    {
        $user = function_exists('posix_geteuid') ? (posix_getpwuid(posix_geteuid())['name'] ?? '?') : get_current_user();
        $this->line("Claim files are kept on this server in {$root} (CLAIM_FILES_LOCAL=true); checking as user {$user}.");
        $why = \App\Support\FileStoreDiagnosis::localProblem($root, 'Expense/'.date('Y').'/healthcheck');
        if ($why) {
            $this->error('  '.$why);
            $this->error('Uploads will fail. Fix: chgrp -R www-data storage && find storage -type d -exec chmod 2775 {} + && chmod -R g+rw storage');
            return self::FAILURE;
        }
        $disk = Storage::disk('s3');
        $key = 'xeasy-healthcheck/'.now()->format('YmdHis').'_'.bin2hex(random_bytes(4)).'.txt';
        try {
            $this->check('write', fn () => $disk->put($key, 'Xeasy file check') !== false);
            $this->check('read back', fn () => $disk->get($key) === 'Xeasy file check');
            $disk->delete($key);
            $this->check('delete', fn () => ! $disk->exists($key));
            $disk->deleteDirectory('xeasy-healthcheck');
        } catch (\Throwable $e) {
            $this->error('  '.$e->getMessage().' - '.(\App\Support\FileStoreDiagnosis::localProblem($root, 'xeasy-healthcheck') ?? 'see storage/logs'));
            return self::FAILURE;
        }
        $this->info('File storage ready.');

        return self::SUCCESS;
    }

    private function check(string $what, callable $fn): void
    {
        if (! $fn()) {
            throw new \RuntimeException("{$what} did not work");
        }
        $this->info("  {$what}: ok");
    }
}
