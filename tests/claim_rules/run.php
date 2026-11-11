<?php
// Usage (no vendor/ needed): php tests/claim_rules/run.php . tests/claim_rules/cases.php
// Minimal stand-ins so the validator's pure rules can run without vendor/.
namespace Illuminate\Http { class Request {} class UploadedFile {} }
namespace Illuminate\Support\Facades { class DB { public static function connection($c=null){ throw new \RuntimeException('no db'); } } }
namespace {
$GLOBALS['cfg'] = require $argv[1].'/config/claim_rules.php';
function env($k,$d=null){ return $d; }
function config($k,$d=null){ if (is_array($k)) { foreach($k as $kk=>$v){ $p=explode('.',$kk); array_shift($p); $r=&$GLOBALS['cfg']; foreach($p as $x){ $r=&$r[$x]; } $r=$v; } return; }
  $p=explode('.',$k); array_shift($p); $r=$GLOBALS['cfg']; foreach($p as $x){ if(!is_array($r)||!array_key_exists($x,$r)) return $d; $r=$r[$x]; } return $r; }
require $argv[1].'/app/Helpers/CommonHelper.php';
require $argv[1].'/app/Services/ClaimRules/ClaimSubmissionValidator.php';
$v = new App\Services\ClaimRules\ClaimSubmissionValidator();
$fail=0; function ok($c,$m){ global $fail; echo ($c?'PASS ':'FAIL ').$m."\n"; if(!$c)$fail++; }
require $argv[2];
echo $fail ? "$fail FAILED\n" : "ALL PASS\n"; exit($fail?1:0);
}
