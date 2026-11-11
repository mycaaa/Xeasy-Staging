<?php
use Illuminate\Support\Facades\DB;
$ids = array_map(fn($r)=>(int)$r->id, DB::select("select employee_id id from employee_ledger_balances union select are.employee_id from advance_request_employees are"));
$asOf = $argv[4] ?? null; if ($asOf==='-') $asOf=null;
$res = app(\App\Http\Controllers\ExpensePaymentController::class)->computeBatchEmployeeLedgerBalances($ids, $asOf);
$t=[]; $bad=0;
foreach ($res as $r) { foreach ($r as $k=>$v) $t[$k]=($t[$k]??0)+$v; if (abs($r['opening_advance']+$r['advances_paid']-$r['adjustments']-$r['advance_balance'])>0.01) $bad++; }
foreach ($t as $k=>$v) printf("%s=%.0f ", $k, $v); echo "employees=".count($res)." identity_violations=$bad\n";
