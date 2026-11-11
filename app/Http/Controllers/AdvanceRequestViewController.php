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
         return view('advancerequest.advance_report');
    }
  
}