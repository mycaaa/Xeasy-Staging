<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;
use App\Models\User;
use App\Models\AdvanceRequest;
use App\Models\AdvanceRequestEmployee;
use App\Models\AdvanceRequestActivity;
use App\Models\AdvanceActivityApproval;
use App\Models\Employee;
use App\Models\ApproverMapping;
use App\Models\ActivityName;
use App\Models\ActivityType;
use App\Models\AdvanceRequestType;
use App\Models\AdvanceRequestMaster;

class AdvanceRequestViewController extends Controller
{
    public function index(){
         return view('advancerequest.advancerequest');
    }
    public function create(){
         return view('advancerequest.advance_create');
    }
    public function advanceapproval(){
         return view('advancerequest.advance_approvals');
    }

    public function report(){
         // ADV-REPORT-01 (14 Nov 2026): the report link opened a 403 for managers (it needed the reports permission).
         // The report runs on the viewer's own reporting tree, so HODs and managers with a team may open it.
         abort_unless(self::reportAllowed(auth()->user()), 403, 'The advance report is for managers with a team, HODs and the reports team.');
         return view('advancerequest.advance_report');
    }

    /** ADV-REPORT-01: may this user open the advance report? Used for the page and to show its links. */
    public static function reportAllowed($user): bool
    {
        if (! $user) {
            return false;
        }
        try {
            if ($user->can('section_reports') || $user->can('payment_section')) {
                return true;
            }
        } catch (\Throwable $e) {
        }
        static $memo = [];
        $k = (int) $user->id;

        return $memo[$k] ??= \App\Support\HodAccess::allowedTeamReport($user);
    }
  
}