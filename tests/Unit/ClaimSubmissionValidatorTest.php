<?php

namespace Tests\Unit;

use App\Services\ClaimRules\ClaimSubmissionValidator;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Tests\TestCase;

class ClaimSubmissionValidatorTest extends TestCase
{
    private function validator(): ClaimSubmissionValidator
    {
        return new ClaimSubmissionValidator();
    }

    private function file(string $name = 'bill.jpg'): UploadedFile
    {
        return UploadedFile::fake()->create($name, 10, 'image/jpeg');
    }

    // ── #16 lodging receipt ────────────────────────────────────────────────
    public function test_lodging_at_or_below_threshold_needs_no_receipt(): void
    {
        $this->assertSame([], $this->validator()->lodgingReceipt(10000, ['total' => 1, 'receipt' => 0, 'receipt_supported' => true]));
    }

    public function test_lodging_above_threshold_requires_tagged_receipt_when_client_supports_it(): void
    {
        $errors = $this->validator()->lodgingReceipt(10001, ['total' => 3, 'receipt' => 0, 'receipt_supported' => true]);
        $this->assertCount(1, $errors);
        $this->assertStringContainsString('payment receipt', $errors[0]);
        $this->assertSame([], $this->validator()->lodgingReceipt(25000, ['total' => 2, 'receipt' => 1, 'receipt_supported' => true]));
    }

    public function test_lodging_legacy_client_falls_back_to_two_files(): void
    {
        $this->assertCount(1, $this->validator()->lodgingReceipt(12000, ['total' => 1, 'receipt' => 0, 'receipt_supported' => false]));
        $this->assertSame([], $this->validator()->lodgingReceipt(12000, ['total' => 2, 'receipt' => 0, 'receipt_supported' => false]));
        config(['claim_rules.lodging.legacy_min_files' => 0]);
        $this->assertCount(1, $this->validator()->lodgingReceipt(12000, ['total' => 5, 'receipt' => 0, 'receipt_supported' => false]));
    }

    public function test_threshold_is_configurable_and_rules_can_be_switched_off(): void
    {
        config(['claim_rules.lodging.receipt_threshold' => 5000]);
        $this->assertCount(1, $this->validator()->validateBill(['claim_id' => 1, 'amount' => 6000, 'files' => ['total' => 1, 'receipt' => 0, 'receipt_supported' => true]]));
        config(['claim_rules.enabled' => false]);
        $this->assertSame([], $this->validator()->validateBill(['claim_id' => 1, 'amount' => 60000, 'files' => []]));
    }

    public function test_file_counts_on_a_legacy_request(): void
    {
        $request = Request::create('/api/claim.php', 'POST', ['claim_id' => 1, 'amount' => 15000, 'Img2_offline' => 'x.jpg'], [], [
            'upload_img' => [$this->file()],
            'File_Payment_Receipt' => [$this->file('receipt.pdf')],
        ]);
        $counts = $this->validator()->fileCounts($request);
        $this->assertSame(['total' => 3, 'receipt' => 1, 'receipt_supported' => true], $counts);
        $this->assertSame([], $this->validator()->validateBillRequest($request));
    }

    // ── #18 rail ticket category ───────────────────────────────────────────
    public function test_rail_ticket_category_is_mandatory_and_normalised(): void
    {
        $v = $this->validator();
        $this->assertCount(1, $v->validateBill(['claim_id' => 14, 'amount' => 900]));
        $this->assertCount(1, $v->validateBill(['claim_id' => 14, 'ticket_category' => 'Business']));
        $this->assertSame([], $v->validateBill(['claim_id' => 14, 'ticket_category' => 'tatkal']));
        $this->assertSame('Premium Tatkal', $v->normalizeTicketCategory('PREMIUM TATKAL'));
        $this->assertSame('General', $v->normalizeTicketCategory(' GN '));
        $this->assertNull($v->normalizeTicketCategory(''));
        $this->assertCount(1, $v->validatePunch(1, 14, ['rail_quota' => '']));
        $this->assertSame([], $v->validatePunch(1, 14, ['rail_quota' => 'GENERAL']));
    }

    // ── #20 courier date ──────────────────────────────────────────────────
    public function test_courier_date_bounds(): void
    {
        $v = $this->validator();
        $this->assertSame('2026-11-05', ClaimSubmissionValidator::parseDate('05-11-2026'));
        $this->assertNull(ClaimSubmissionValidator::parseDate('2026-02-30'));
        $this->assertCount(1, $v->courierDate(date('Y-m-d', strtotime('+1 day')), (int) date('n'), (int) date('n') >= 4 ? (int) date('Y') : (int) date('Y') - 1));
        $this->assertCount(1, $v->courierDate('2026-03-31', 4, 2026));
        $this->assertSame([], $v->courierDate('2026-04-01', 4, 2026));
        $this->assertCount(1, $v->courierDate('2026-04-01', 4, 2026, '2026-05-03'));
        $this->assertCount(1, $v->receivingDate('2026-05-01', '2026-05-03'));
    }

    // ── UAT E-12: web DA form (no offline reference on the web) ─────────────
    public function test_web_dos_without_file_gets_the_night_halt_message_even_with_an_offline_reference(): void
    {
        $request = Request::create('/', 'POST', ['mclaimtype' => 20, 'Img1_offline' => 'IMG_offline_1.jpg']);
        $this->assertSame([], $this->validator()->validateDaRequest($request), 'mobile API: offline reference accepted (by design)');
        $errors = $this->validator()->validateDaRequest($request, null, false);
        $this->assertCount(1, $errors);
        $this->assertStringContainsString('night-halt proof', $errors[0]);
    }

    public function test_web_dos_with_file_passes(): void
    {
        $request = Request::create('/', 'POST', ['mclaimtype' => 20], [], ['uploadedFile' => $this->file('halt.jpg')]);
        $this->assertSame([], $this->validator()->validateDaRequest($request, null, false));
    }
}
