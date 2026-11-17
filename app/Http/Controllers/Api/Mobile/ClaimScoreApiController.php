<?php

namespace App\Http\Controllers\Api\Mobile;

use App\Http\Controllers\ClaimScoreController;
use App\Services\ClaimScore\ClaimScoreService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * CLAIM-SCORE-06 (17 Nov 2026): Claim Score for the mobile app, same figures as the website's My Claim Score and
 * My Team pages. The caller sees only their own score (with the department board as the visibility setting allows)
 * and, when people report to them, their own team. No amounts, no other manager's team.
 *
 *   {base}/api/{ComId}/claim_score.php?value=me|team&empid=..&tokenid=..&ComId=..[&month=4][&scope=direct|all]
 */
class ClaimScoreApiController extends BaseMobileController
{
    public function handle(Request $request): JsonResponse
    {
        $emp = $this->validateToken($request);
        if (! $emp) {
            return $this->error('Invalid Employee!');
        }
        $empId = (int) $emp->EmployeeID;
        $comId = (int) ($request->input('ComId') ?: $request->route('comId') ?: 1);

        try {
            $s = new ClaimScoreService($comId);
            $months = $s->months();
            if (! $months) {
                return $this->error('Claim Score is not available yet.');
            }
            $m = (int) $request->input('month', 0);
            $month = in_array($m, $months, true) ? $m : $s->defaultMonth();
            $web = new ClaimScoreController();
            $isManager = count($s->teamIds($empId, false)) > 0;

            if ($request->input('value') === 'team') {
                if (! $isManager) {
                    return $this->error('Nobody reports to you.');
                }
                $scope = $request->input('scope') === 'all' ? 'all' : 'direct';
                $p = $web->teamPayload($s, $month, $empId, $scope);
                unset($p['race']);   // other managers' teams stay on the website board only

                return response()->json(['Code' => '300', 'msg' => 'Data Available!', 'isManager' => true, 'data' => $p]);
            }

            $p = $web->mePayload($s, $month, $empId);

            return response()->json(['Code' => '300', 'msg' => $p['row'] ? 'Data Available!' : 'No score for this month',
                'isManager' => $isManager, 'data' => $p]);
        } catch (\Throwable $e) {
            report($e);

            return $this->error('Claim Score could not be loaded. Please try again later.');
        }
    }
}
