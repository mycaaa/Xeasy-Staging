<?php
use App\Services\ClaimRules\ClaimSubmissionValidator as V;
// #16
ok($v->lodgingReceipt(10000, ['total'=>1,'receipt'=>0,'receipt_supported'=>true])===[], '16 at threshold ok');
ok(count($v->lodgingReceipt(10001, ['total'=>3,'receipt'=>0,'receipt_supported'=>true]))===1, '16 tagged client needs receipt');
ok($v->lodgingReceipt(10001, ['total'=>1,'receipt'=>1,'receipt_supported'=>true])===[], '16 receipt ok');
ok(count($v->lodgingReceipt(12000, ['total'=>1,'receipt'=>0,'receipt_supported'=>false]))===1, '16 legacy 1 file rejected');
ok($v->lodgingReceipt(12000, ['total'=>2,'receipt'=>0,'receipt_supported'=>false])===[], '16 legacy 2 files ok');
// #18
ok(count($v->validateBill(['claim_id'=>14]))===1, '18 blank rejected');
ok(count($v->validateBill(['claim_id'=>14,'ticket_category'=>'Business']))===1, '18 unknown rejected');
ok($v->validateBill(['claim_id'=>14,'ticket_category'=>'tatkal'])===[], '18 tatkal ok');
ok($v->normalizeTicketCategory('PREMIUM TATKAL')==='Premium Tatkal', '18 normalise PT');
ok(count($v->validatePunch(1,14,['rail_quota'=>'']))===1, '18 punch blank rejected');
// #20
ok(V::parseDate('05-11-2026')==='2026-11-05', '20 parse dmy');
ok(V::parseDate('2026-02-30')===null, '20 invalid date');
ok(V::parseDate('0000-00-00')===null, '20 zero date');
$today=date('Y-m-d'); $tom=date('Y-m-d', strtotime('+1 day'));
$m=(int)date('n'); $fy=$m>=4?(int)date('Y'):(int)date('Y')-1;
ok(count($v->courierDate($tom,$m,$fy))===1, '20 future rejected');
ok($v->courierDate($today,$m,$fy)===[], '20 today ok');
ok(count($v->courierDate('2026-03-31',4,2026))===1, '20 before month start rejected');
ok($v->courierDate('2026-04-01',4,2026)===[], '20 month start ok');
ok(count($v->courierDate('2026-04-01',4,2026,'2026-05-03'))===1, '20 before submission rejected when given');
ok(count($v->courierDate('abc',4,2026))===1, '20 garbage rejected');
ok($v->courierDate('2027-01-10',1,2026)===[] || date('Y-m-d')<'2027-01-10', '20 Jan belongs to FY end year');
ok(count($v->receivingDate('2026-05-01','2026-05-03'))===1, '20 receiving before post rejected');
ok($v->fyStartYear(8)===2026, '20 fy fallback mapping');
// #21 / #35
ok(count($v->validateBill(['claim_id'=>42,'pax_details'=>' ']))===1, '21 team meeting needs pax details');
ok($v->validateBill(['claim_id'=>42,'pax_details'=>'Sales team, 6 TMs'])===[], '21 team meeting with details ok');
config(['claim_rules.participants.activity_extra_claim_ids' => [23]]);
ok($v->activityRequiresParticipants(23)===true, '21 configured activity needs participants');
ok($v->activityRequiresParticipants(36)===false, '21 RST (no head, db unavailable) not required');
ok(count($v->validateActivity(['claim_id'=>23]))===1, '21 activity without names/files rejected');
ok($v->validateActivity(['claim_id'=>23,'participant_names'=>'A, B'])===[], '21 names ok');
ok($v->validateActivity(['claim_id'=>23,'participant_files'=>1])===[], '21 file ok');
ok($v->validateActivity(['claim_id'=>23,'existing_participant_files'=>1])===[], '21 existing file on edit ok');
config(['claim_rules.participants.activity_extra_claim_ids' => []]);
// #38
ok(count($v->participantsWithinPax(3, 'E1,E2', '[{"name":"A","category":"Dealer"},{"name":"B","category":"Guest"}]'))===1, '38 over pax rejected');
ok($v->participantsWithinPax(4, '["E1","E2"]', '[{"name":"A","category":"Dealer"},{"name":"B","category":"Guest"}]')===[], '38 equal pax ok');
ok($v->participantsWithinPax('', 'E1,E2,E3', '')===[], '38 no pax entered: no check');
ok($v->participantsWithinPax(2, 'E1,E1', 'Ramesh')===[], '38 duplicate employee counted once, text other counted');
ok(count($v->validateBill(['claim_id'=>17,'pax_details'=>'1','employee_participants'=>'E1','other_participants'=>'Ramesh']))===1, '38 via validateBill');
ok(count($v->validatePunch(1,17,['PaxDetails'=>'1','meal_participants'=>'E1,E2']))===1, '38 punch over pax rejected');
ok($v->validatePunch(1,17,['PaxDetails'=>'2'])===[], '38 punch without participant fields skipped');
// #17
ok(count($v->dosProof(20, 0, ''))===1, '17 DOS without proof rejected');
ok($v->dosProof(20, 1)===[], '17 DOS with file ok');
ok($v->dosProof(20, 0, 'DA_1_offline.jpg')===[], '17 DOS offline reference ok');
ok($v->dosProof(19, 0)===[], '17 DHQ needs no proof');
