<?php

namespace Tests\Unit;

use App\Support\LedgerJson;
use PHPUnit\Framework\TestCase;

class LedgerJsonTest extends TestCase
{
    private const OBJ = '{"ledger":[{"advance_id":"0","remaining_amount":1500}],"paid":[{"advance_id":"12","remaining_amount":2500.5}],"expense":[],"carried":[]}';

    public function test_decodes_single_and_double_encoded_values_identically(): void
    {
        $single = LedgerJson::decode(self::OBJ);
        $double = LedgerJson::decode(json_encode(self::OBJ));        // what approveClaimMonth used to store
        $triple = LedgerJson::decode(json_encode(json_encode(self::OBJ)));

        $this->assertSame($single, $double);
        $this->assertSame($single, $triple);
        $this->assertSame(['ledger', 'paid', 'expense', 'carried'], array_keys($single));
    }

    public function test_encode_writes_object_once_and_is_idempotent(): void
    {
        $once = LedgerJson::encode(json_encode(self::OBJ));
        $this->assertFalse(LedgerJson::isDoubleEncoded($once));
        $this->assertTrue(LedgerJson::isDoubleEncoded(json_encode(self::OBJ)));
        $this->assertSame($once, LedgerJson::encode($once));
        $this->assertSame(json_decode(self::OBJ, true), json_decode($once, true));
    }

    public function test_empty_and_invalid_values(): void
    {
        $this->assertSame([], LedgerJson::decode(null));
        $this->assertSame([], LedgerJson::decode(''));
        $this->assertSame([], LedgerJson::decode('not json'));
        $this->assertNull(LedgerJson::encode(null));
        $this->assertSame('{}', LedgerJson::encode('{}'));
    }

    public function test_total_remaining_and_closing_balance(): void
    {
        $this->assertSame(4000.5, LedgerJson::totalRemaining(json_encode(self::OBJ)));
        $row = (object) ['fixed_imprest_amt_1' => 10000, 'fixed_imprest_amt_2' => 5000];
        // closing = imprest + outstanding advances (opening + paid - adjusted)
        $this->assertSame(19000.5, LedgerJson::closingBalance($row, self::OBJ));
    }
}
