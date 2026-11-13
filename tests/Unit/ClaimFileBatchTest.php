<?php

namespace Tests\Unit;

use App\Services\ClaimFiles\ClaimFileBatch;
use App\Services\ClaimFiles\ClaimFileException;
use App\Services\ClaimRules\ClaimSubmissionValidator;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * UAT R-01. Files go to the company folder (S3-FOLDER-01: VNR = xeasy/). The s3 disk is pointed at a local temp folder (writable case) or at a
 * root below a regular file (unwritable for any user, root included). No real
 * bucket is touched.
 */
class ClaimFileBatchTest extends TestCase
{
    private string $tmp;

    protected function setUp(): void
    {
        parent::setUp();
        $this->tmp = rtrim(sys_get_temp_dir(), '/').'/r01_'.bin2hex(random_bytes(4));
        mkdir($this->tmp, 0700, true);
        $this->useDisk($this->tmp.'/disk');
    }

    protected function tearDown(): void
    {
        exec('rm -rf '.escapeshellarg($this->tmp));
        parent::tearDown();
    }

    private function useDisk(string $root): void
    {
        config(['filesystems.disks.s3' => ['driver' => 'local', 'root' => $root, 'throw' => false]]);
        Storage::forgetDisk('s3');
    }

    private function unwritableDisk(): void
    {
        $blocker = $this->tmp.'/not_a_dir';
        file_put_contents($blocker, 'x');
        $this->useDisk($blocker.'/disk');
    }

    private function file(string $name = 'bill.jpg'): UploadedFile
    {
        // LV-S10 checks the content, so the test file is a real (tiny) PDF whatever its name
        return UploadedFile::fake()->createWithContent($name, "%PDF-1.4\n1 0 obj<<>>endobj\ntrailer<<>>\n%%EOF\n");
    }

    private function filesOnDisk(): array
    {
        $root = $this->tmp.'/disk';
        if (! is_dir($root)) {
            return [];
        }
        $out = [];
        foreach (new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($root, \FilesystemIterator::SKIP_DOTS)) as $f) {
            $out[] = substr($f->getPathname(), strlen($root) + 1);
        }
        sort($out);

        return $out;
    }

    public function test_stores_at_the_mobile_path_and_counts_by_field(): void
    {
        $batch = new ClaimFileBatch();
        $batch->putMobile($this->file(), 8, 1001, 'File_Lodging_1.jpg', 'File_Lodging');
        $batch->putMobile($this->file(), 8, 1001, 'File_Payment_Receipt_1.jpg', 'File_Payment_Receipt');

        $this->assertSame(['xeasy/8/1001/File_Lodging_1.jpg', 'xeasy/8/1001/File_Payment_Receipt_1.jpg'], $this->filesOnDisk());
        $this->assertSame(['File_Lodging' => 1, 'File_Payment_Receipt' => 1], $batch->countsByField());
        $this->assertSame(1, $batch->count('File_Payment_Receipt'));
        $this->assertSame(2, $batch->count());
    }

    public function test_failed_store_throws_and_stores_nothing(): void
    {
        $this->unwritableDisk();
        $batch = new ClaimFileBatch();
        try {
            $batch->putMobile($this->file(), 8, 1001, 'a.jpg', 'upload_img');
            $this->fail('expected ClaimFileException');
        } catch (ClaimFileException $e) {
            $this->assertStringContainsString('claim was not saved', $e->getMessage());
        }
        $this->assertSame(0, $batch->count());
    }

    public function test_invalid_upload_is_a_failure_not_a_skip(): void
    {
        $broken = new UploadedFile(__FILE__, 'x.jpg', 'image/jpeg', UPLOAD_ERR_PARTIAL, true);
        $this->expectException(ClaimFileException::class);
        (new ClaimFileBatch())->putMobile($broken, 8, 1001, 'x.jpg', 'upload_img');
    }

    public function test_same_path_twice_in_one_save_is_refused(): void
    {
        $batch = new ClaimFileBatch();
        $batch->putMobile($this->file(), 8, 1001, 'participants.pdf', 'participant_list_file');
        $this->expectException(ClaimFileException::class);
        $batch->putMobile($this->file(), 8, 1001, 'participants.pdf', 'participant_list_file');
    }

    public function test_discard_deletes_files_already_stored(): void
    {
        $batch = new ClaimFileBatch();
        $batch->putMobile($this->file(), 8, 1001, 'one.jpg', 'upload_img');
        $batch->putMobile($this->file(), 8, 1001, 'two.jpg', 'upload_img');
        $this->assertCount(2, $this->filesOnDisk());

        $this->assertSame(2, $batch->discard());
        $this->assertSame([], $this->filesOnDisk());
        $this->assertSame(0, $batch->count());
    }

    private function needsSqlite(): void
    {
        if (! in_array('sqlite', \PDO::getAvailableDrivers(), true)) {
            $this->markTestSkipped('pdo_sqlite not installed; the rollback path is covered by the staging UAT (work/r01_e12_fixes.md).');
        }
    }

    public function test_discard_keeps_an_object_that_existed_before_the_save(): void
    {
        mkdir($this->tmp.'/disk/xeasy/8/1001', 0700, true);
        file_put_contents($this->tmp.'/disk/xeasy/8/1001/participants.pdf', 'earlier claim');
        $batch = new ClaimFileBatch();
        $batch->putMobile($this->file(), 8, 1001, 'participants.pdf', 'participant_list_file');
        $batch->putMobile($this->file(), 8, 1001, 'new.pdf', 'participant_list_file');

        $this->assertSame(1, $batch->discard());
        $this->assertSame(['xeasy/8/1001/participants.pdf'], $this->filesOnDisk());
    }

    public function test_commit_with_rolls_back_rows_and_deletes_files_on_failure(): void
    {
        $this->needsSqlite();
        Schema::create('r01_claims', function (Blueprint $t) {
            $t->increments('ExpId');
            $t->string('FileName');
        });
        $batch = new ClaimFileBatch();
        $batch->putMobile($this->file(), 8, 1001, 'keep_or_drop.jpg', 'upload_img');

        try {
            $batch->commitWith(null, function () {
                DB::table('r01_claims')->insert(['FileName' => 'keep_or_drop.jpg']);
                throw new \RuntimeException('simulated DB failure after the claim insert');
            });
            $this->fail('expected the exception to be rethrown');
        } catch (\RuntimeException $e) {
            $this->assertSame('simulated DB failure after the claim insert', $e->getMessage());
        }
        $this->assertSame(0, DB::table('r01_claims')->count(), 'claim row must be rolled back');
        $this->assertSame([], $this->filesOnDisk(), 'stored file must be deleted');
    }

    public function test_commit_with_keeps_rows_and_files_on_success(): void
    {
        $this->needsSqlite();
        Schema::create('r01_claims', function (Blueprint $t) {
            $t->increments('ExpId');
            $t->string('FileName');
        });
        $batch = new ClaimFileBatch();
        $batch->putMobile($this->file(), 8, 1001, 'kept.jpg', 'upload_img');
        $result = $batch->commitWith(null, fn () => DB::table('r01_claims')->insertGetId(['FileName' => 'kept.jpg']));

        $this->assertSame(1, (int) $result);
        $this->assertSame(1, DB::table('r01_claims')->count());
        $this->assertSame(['xeasy/8/1001/kept.jpg'], $this->filesOnDisk());
        $this->assertSame(0, $batch->discard(), 'after commit the batch no longer owns the files');
        $this->assertSame(['xeasy/8/1001/kept.jpg'], $this->filesOnDisk());
    }

    // ── rules count stored files, not request files ──────────────────────────
    private function lodgingRequest(): Request
    {
        return Request::create('/', 'POST', ['claim_id' => 1, 'amount' => 12000, 'receipt_upload_supported' => '1'], [], [
            'File_Lodging' => [$this->file('bill.jpg')],
            'File_Payment_Receipt' => [$this->file('receipt.jpg')],
        ]);
    }

    public function test_lodging_receipt_rule_uses_stored_counts_when_given(): void
    {
        $v = new ClaimSubmissionValidator();
        $request = $this->lodgingRequest();
        $this->assertSame([], $v->validateBillRequest($request), 'request carries a receipt');
        $this->assertNotSame([], $v->validateBillRequest($request, ['File_Lodging' => 1]), 'receipt not stored: rule fails');
        $this->assertSame([], $v->validateBillRequest($request, ['File_Lodging' => 1, 'File_Payment_Receipt' => 1]));
    }

    public function test_dos_rule_uses_stored_count_when_given(): void
    {
        $v = new ClaimSubmissionValidator();
        $request = Request::create('/', 'POST', ['DAtype' => 20], [], ['upload_img' => $this->file('halt.jpg')]);
        $this->assertSame([], $v->validateDaRequest($request));
        $this->assertNotSame([], $v->validateDaRequest($request, 0), 'proof not stored: rule fails');
        $this->assertSame([], $v->validateDaRequest($request, 1));
    }
}
