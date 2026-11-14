<?php

namespace Tests\Unit;

use App\Services\Claims\StageCap;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/** APPR-CAP-02 (14 Nov 2026): the claimed amount is the ceiling for the verifier and the approver, on screen and at submit. */
class StageCapTest extends TestCase
{
    private function claim(float $claimed, float $verified): object
    {
        return (object) ['ExpId' => 1, 'CrBy' => 10, 'FilledTAmt' => $claimed, 'VerifyTAmt' => $verified];
    }

    public function test_approver_ceiling_is_the_claimed_amount_even_when_verified_is_higher(): void
    {
        $this->assertSame(1500.0, StageCap::basis($this->claim(1500, 15000), 'approve'));
        $this->assertNotNull(StageCap::check('sqlite', 8, $this->claim(1500, 15000), 15000, 'approve', 'approve', 't'));
        $this->assertNull(StageCap::check('sqlite', 8, $this->claim(1500, 15000), 1500, 'approve', 'ok', 't'));
    }

    public function test_approver_cannot_approve_above_claimed_when_not_verified(): void
    {
        $this->assertNotNull(StageCap::check('sqlite', 8, $this->claim(740, 0), 150000, 'approve', '0000', 't'));
    }

    public function test_approver_may_raise_a_cut_up_to_the_claimed_amount(): void
    {
        $this->assertNull(StageCap::check('sqlite', 8, $this->claim(1000, 800), 1000, 'approve', 'restored', 't'));
        $this->assertNotNull(StageCap::check('sqlite', 8, $this->claim(1000, 800), 1001.5, 'approve', 'restored', 't'));
    }

    public function test_verifier_cannot_go_above_claimed_even_with_a_remark(): void
    {
        $this->assertNotNull(StageCap::check('sqlite', 8, $this->claim(1500, 0), 15000, 'verify', 'approve', 't'));
        $this->assertNull(StageCap::check('sqlite', 8, $this->claim(1500, 0), 1500, 'verify', '', 't'));
    }

    public function test_month_submit_lists_claims_above_claimed(): void
    {
        Schema::create('y8_expenseclaims', function (Blueprint $t) {
            $t->integer('ExpId');
            $t->integer('CrBy');
            $t->integer('ClaimMonth');
            $t->integer('ClaimYearId');
            $t->string('ClaimStatus');
            $t->decimal('FilledTAmt', 12, 2);
            $t->decimal('VerifyTAmt', 12, 2)->default(0);
            $t->decimal('ApprTAmt', 12, 2)->nullable();
        });
        DB::table('y8_expenseclaims')->insert([
            ['ExpId' => 1, 'CrBy' => 10, 'ClaimMonth' => 10, 'ClaimYearId' => 8, 'ClaimStatus' => 'Approved', 'FilledTAmt' => 1500, 'VerifyTAmt' => 15000, 'ApprTAmt' => 100],
            ['ExpId' => 2, 'CrBy' => 10, 'ClaimMonth' => 10, 'ClaimYearId' => 8, 'ClaimStatus' => 'Approved', 'FilledTAmt' => 740, 'VerifyTAmt' => 0, 'ApprTAmt' => 150000],
            ['ExpId' => 3, 'CrBy' => 10, 'ClaimMonth' => 10, 'ClaimYearId' => 8, 'ClaimStatus' => 'Verified', 'FilledTAmt' => 1000, 'VerifyTAmt' => 1200, 'ApprTAmt' => 0],
            ['ExpId' => 4, 'CrBy' => 10, 'ClaimMonth' => 10, 'ClaimYearId' => 8, 'ClaimStatus' => 'Verified', 'FilledTAmt' => 1000, 'VerifyTAmt' => 1000, 'ApprTAmt' => 0],
        ]);

        $over = StageCap::monthOverClaimed(config('database.default'), 8, 10, 10);
        $this->assertSame([2, 3], array_keys($over));
    }
}
