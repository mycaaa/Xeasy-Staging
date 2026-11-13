<?php

namespace App\Http\Controllers\admin;

use App\Helpers\CommonHelper;
use App\Helpers\DatabaseSwitcher;
use App\Http\Controllers\admin\RateController;
use App\Http\Controllers\Controller;
use App\Services\ClaimRules\ClaimSubmissionValidator;
use App\Services\EligibilityValidationService;
use App\Models\ActivityType;
use App\Models\Airport;
use App\Models\CityCategory;
use App\Models\ClaimType;
use App\Models\CoreVariety;
use App\Models\CoreVertical;
use App\Models\EmployeeVehicle;
use App\Models\ExpenseClaim;
use App\Models\ExpenseRemark;
use App\Models\G1ExpenseFillData;
use App\Models\HRMEmployeeGeneral;
use App\Models\HRMEmployees;
use App\Models\MonthExpenseFinal;
use App\Models\TwoFourWheelerEntry;
use App\Models\VendorList;
use Illuminate\Filesystem\FilesystemAdapter;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Schema;

class ClaimViewController extends Controller
{
    /** y{year}_claimuploads.UploadSequence of the lodging online payment receipt (tracker #16). */
    private const PAYMENT_RECEIPT_SEQUENCE = 110;

    const VEHICLE_TYPE_TWO_WHEELER = 2;
    const VEHICLE_TYPE_FOUR_WHEELER = 4;

    /**
     * Claim statuses in which the claim head (ClaimId) may still be changed.
     * Once a claim has moved past this into verification/approval/finance,
     * changing its head would corrupt the workflow, so it's blocked.
     */
    const HEAD_CHANGE_ALLOWED_STATUSES = ['Draft', 'Submitted', 'Filled', 'Verified'];

    private function getRateController()
    {
        return new RateController();
    }

    private function getRatePerKM($employeeId, $billDate, $wheelType, $vehiclePolicy = 0)
    {
        $rateController = $this->getRateController();
        return $rateController->getRatePerKM($employeeId, $billDate, $wheelType, $vehiclePolicy);
    }

    public function getClaimDetailView(Request $request)
    {
        $request->validate(['claim_id' => 'required', 'expid' => 'required']);
        $claimId = $request->input('claim_id');
        $expid = $request->input('expid');
        $yearId = (int) ($request->input('year_id') ?: session('year_id'));
        $referer = $request->headers->get('referer');
        $viewContext = 'view';
        if (str_contains($referer, 'verify-pending') || str_contains($referer, 'verify-approved')) {
            $viewContext = 'verify';
        } elseif (str_contains($referer, 'approve-pending') || str_contains($referer, 'approve-approved')) {
            $viewContext = 'approve';
        } elseif (str_contains($referer, 'fin-pending') || str_contains($referer, 'fin-approved')) {
            $viewContext = 'finance';
        }

        // If company_id is passed, temporarily switch the expense connection
        // This allows approver page (which can't switch company) to query the correct DB
        $companyId = $request->input('company_id');
        $originalConnection = session('expense_connection');
        $originalYearId = $yearId;
        $originalCompanyId = session('company_id');

        try {
            if ($companyId) {
                $expenseConnection = DatabaseSwitcher::getExpenseConnection((int) $companyId);
                session(['expense_connection' => $expenseConnection]);
                session(['company_id' => (int) $companyId]);
                if ((int)$companyId == 11 && (int)$originalCompanyId != 11) {
                    $yearId = max(1, (int)$originalYearId - 2);
                    session(['year_id' => $yearId]);
                }
            }

        $expense_detail = DB::connection(session('expense_connection'))->table(ExpenseClaim::tableName($yearId))->where('ExpId', $expid)->first();
        if (!$expense_detail) {
            return response()->json(['error' => "No expense claim found for ExpId: {$expid}"], 404);
        }

        // Never show details if FilledOkay is not 1 for claims/finance screen
        if ((int)($expense_detail->FilledOkay ?? 0) !== 1) {
            if (str_contains((string)$referer, 'claims') || $viewContext === 'finance' || $request->input('source') === 'claims') {
                return response()->json(['error' => "Claim details cannot be shown because FilledOkay is not 1."], 403);
            }
        }
        $cgId = ClaimType::getCgIdByClaimId($claimId);

        // Handle potential missing hrm_employee table with proper database connection
        try {
            $createdByUser = DB::connection(session('hrims_connection'))
                ->table('hrm_employee')
                ->where('EmployeeID', $expense_detail->CrBy)
                ->select('EmployeeID', 'EmpCode', 'Fname', 'Sname', 'Lname')
                ->first();

            if ($createdByUser) {
                $createdByUser->full_name = trim(
                    ($createdByUser->Fname ?? '') . ' '
                    . ($createdByUser->Sname ?? '') . ' '
                    . ($createdByUser->Lname ?? '')
                );
            }
        } catch (\Illuminate\Database\QueryException $e) {
            if (str_contains($e->getMessage(), "doesn't exist") || str_contains($e->getMessage(), 'Table')) {
                Log::warning('HRIMS table not found for company ' . session('company_id', 'unknown') . ': ' . $e->getMessage());
                return response()->json(['error' => 'Employee data not available for this company'], 500);
            }
            throw $e;
        }

        $expenseConnName = session('expense_connection') ?: 'expense';
        $tableName = ExpenseRemark::tableName($yearId);
        $remarkTableToUse = null;

        if (Schema::connection($expenseConnName)->hasTable($tableName)) {
            $remarkTableToUse = $tableName;
        } elseif (Schema::connection($expenseConnName)->hasTable('expenseremark')) {
            $remarkTableToUse = 'expenseremark';
        }

        $remarks = collect();
        if ($remarkTableToUse) {
            try {
                $remarks = DB::connection($expenseConnName)
                    ->table($remarkTableToUse)
                    ->leftJoin('users', function ($join) use ($remarkTableToUse) {
                        $join->on('users.employee_id', '=', DB::raw("CASE WHEN {$remarkTableToUse}.ByRole = 'E' THEN {$remarkTableToUse}.By END"));
                        $join->orOn('users.id', '=', DB::raw("CASE WHEN {$remarkTableToUse}.ByRole <> 'E' THEN {$remarkTableToUse}.By END"));
                    })
                    ->select("{$remarkTableToUse}.*", 'users.name')
                    ->where("{$remarkTableToUse}.ExpId", $expid)
                    ->orderBy("{$remarkTableToUse}.CrDateTime", 'ASC')
                    ->get();
            } catch (\Illuminate\Database\QueryException $e) {
                Log::warning("Expense remark table query error for {$remarkTableToUse}: " . $e->getMessage());
                $remarks = collect();
            }
        }
        if (!empty($expense_detail->DateEntryRemark)) {
            $firstRemark = (object) [
                'RemarkId' => 0,
                'ExpId' => $expid,
                'By' => $expense_detail->CrBy,
                'ByRole' => 'E',
                'Remark' => $expense_detail->DateEntryRemark,
                'CrDateTime' => $expense_detail->CrDate ?? now(),
                'name' => $createdByUser->full_name ?? 'Employee',
            ];
            $remarks[] = $firstRemark;
        }
        $html = '';
        $employee_detail = [];
        if ($cgId == 1) {
            $componentPath = resource_path("views/components/modal/claim_view/claim_detail_{$claimId}.blade.php");
            if ($claimId == 7) {
                $employee_vehicle = EmployeeVehicle::where('EmployeeID', $expense_detail->CrBy)->where('status', 'A')->orderByDesc('id')->first(); // RATE-02: latest Vehicle tab row
                if ($employee_vehicle) {
                    $employee_detail = [
                        'registration_no' => $employee_vehicle->registration_no,
                        'brand' => $employee_vehicle->brand,
                        'vehicle_type' => $employee_vehicle->vehicle_type,
                    ];
                }
                $travel_data = DB::connection(session('expense_connection'))->table("y{$yearId}_g{$cgId}_expensefilldata")->where('ExpId', $expid)->first(['VehicleType', 'VehicleReg']);
                $vehicle_reg = DB::connection(session('expense_connection'))->table("y{$yearId}_g{$cgId}_expensefilldata")->where('ExpId', $expid)->value('VehicleReg');
                $expense_detail->punching_data = $vehicle_reg ?? ($employee_vehicle->registration_no ?? null);
                $wheelType = $expense_detail->WType ?? $employee_detail['vehicle_type'] ?? self::VEHICLE_TYPE_FOUR_WHEELER;
                $rate = $this->getRatePerKM($expense_detail->CrBy, $expense_detail->BillDate ?? date('Y-m-d'), $wheelType);
                $expense_detail->calculated_rate = $rate;
                $rate_2w = $this->getRatePerKM($expense_detail->CrBy, $expense_detail->BillDate ?? date('Y-m-d'), self::VEHICLE_TYPE_TWO_WHEELER);
                $rate_4w = $this->getRatePerKM($expense_detail->CrBy, $expense_detail->BillDate ?? date('Y-m-d'), self::VEHICLE_TYPE_FOUR_WHEELER);
                $expense_detail->rate_2w = $rate_2w;
                $expense_detail->rate_4w = $rate_4w;
                $wheelerRows = DB::connection(session('expense_connection'))->table(TwoFourWheelerEntry::tableName($yearId))->where('ExpId', $expid)->get();
                $expense_detail->wheeler_entries = $wheelerRows;
                $expense_detail->punching_data = $travel_data;
            } elseif ($claimId == 1) {
                try {
                    $employee = DB::connection(session('hrims_connection'))->table('hrm_employee')->where('EmployeeID', $expense_detail->CrBy)->select('Fname', 'Sname', 'Lname')->first();
                    $fullName = trim("{$employee->Fname} {$employee->Sname} {$employee->Lname}");
                } catch (\Illuminate\Database\QueryException $e) {
                    if (str_contains($e->getMessage(), "doesn't exist") || str_contains($e->getMessage(), 'Table') && str_contains($e->getMessage(), 'hrm_employee')) {
                        Log::warning('HRIMS table not found for company ' . session('company_id', 'unknown') . ': ' . $e->getMessage());
                        return response()->json(['error' => 'Employee data not available for this company'], 500);
                    } else {
                        throw $e;
                    }
                }
                $employee_detail['employee_detail'] = ['full_name' => $fullName];
                $travel_data = DB::connection(session('expense_connection'))->table("y{$yearId}_g{$cgId}_expensefilldata as ef")->leftJoin('city_category as cc', 'ef.CityName', '=', 'cc.city_name')->where('ef.ExpId', $expid)->first(['ef.did', 'ef.CityCategory', 'ef.CityName', 'cc.ccid', 'ef.HotelName', 'ef.HotelAddress', 'ef.HotelContact', 'ef.HotelEmail', 'ef.BillingName', 'ef.BillingAddress', 'ef.BillNo', 'ef.BillDate', 'ef.Arrival', 'ef.Departure', 'ef.StayDuration', 'ef.Mode', 'ef.Plan', 'ef.NoOfPAX', 'ef.PaxDetails', 'ef.PaxDetails_Other', 'ef.GST', 'ef.BillingInstruction']);
                $expense_detail->punching_data = $travel_data;
                $expense_detail->remark_chat = $remarks;
                $componentPath = resource_path("views/components/modal/claim_view/claim_detail_{$claimId}.blade.php");
                if (file_exists($componentPath)) {
                    $html = view("components.modal.claim_view.claim_detail_{$claimId}", [
                        'claimId' => $claimId,
                        'cgId' => $cgId,
                        'expense_detail' => $expense_detail,
                        'employee_detail' => $employee_detail,
                    ])->render();
                } else {
                    $html = '<div class="alert alert-warning">Component for Claim ID ' . $claimId . ' not found.</div>';
                }
            } elseif ($claimId == 2) {
                try {
                    $eligibility = DB::connection(session('hrims_connection'))->table('hrm_employee_eligibility')->select('Lodging_CategoryA', 'Lodging_CategoryB', 'Lodging_CategoryC')->where('EmployeeID', $expense_detail->CrBy)->where('STATUS', 'A')->first();
                } catch (\Illuminate\Database\QueryException $e) {
                    if (str_contains($e->getMessage(), "doesn't exist") || str_contains($e->getMessage(), 'Table')) {
                        Log::warning('HRIMS eligibility table not found for company ' . session('company_id', 'unknown') . ': ' . $e->getMessage());
                        $eligibility = null;  // Don't create static fallback data
                    } else {
                        throw $e;
                    }
                }
                $employee_detail['employee_detail'] = $eligibility;
                $travel_data = DB::connection(session('expense_connection'))->table("y{$yearId}_g{$cgId}_expensefilldata")->where('ExpId', $expid)->first([
                    'Agent',
                    'Vendor_Id',
                    'PNRNo',
                    'TrainBusName',
                    'Class',
                    'BookingDate',
                    'JourneyStartDt',
                    'JourneyEndDt',
                    'StayDuration',
                    'JourneyFrom',
                    'Journey_From_Id',
                    'JourneyUpto',
                    'Journey_Upto_Id',
                    'TravelInsurance',
                    'IsTravelInsurance',
                ]);
                $expense_detail->punching_data = $travel_data;
            } elseif ($claimId == 3) {
                $travel_data = DB::connection(session('expense_connection'))->table("y{$yearId}_g{$cgId}_expensefilldata")->where('ExpId', $expid)->first(['BillDate', 'JourneyFrom', 'Mode', 'Journey_From_Id', 'JourneyUpto', 'Journey_Upto_Id']);
                $expense_detail->punching_data = $travel_data;
            } elseif ($claimId == 4) {
                try {
                    $employee = DB::connection(session('hrims_connection'))->table('hrm_employee')->where('EmployeeID', $expense_detail->CrBy)->select('Fname', 'Sname', 'Lname')->first();
                    $fullName = trim("{$employee->Fname} {$employee->Sname} {$employee->Lname}");
                } catch (\Illuminate\Database\QueryException $e) {
                    if (str_contains($e->getMessage(), "doesn't exist") || str_contains($e->getMessage(), 'Table') && str_contains($e->getMessage(), 'hrm_employee')) {
                        Log::warning('HRIMS table not found for company ' . session('company_id', 'unknown') . ': ' . $e->getMessage());
                        return response()->json(['error' => 'Employee data not available for this company'], 500);
                    } else {
                        throw $e;
                    }
                }
                $employee_detail['employee_detail'] = ['full_name' => $fullName];
                $travel_data = DB::connection(session('expense_connection'))->table("y{$yearId}_g{$cgId}_expensefilldata")->where('ExpId', $expid)->first([
                    'AgencyName',
                    'BillingName',
                    'BillingAddress',
                    'Vendor_Id',
                    'AgencyAddress',
                    'Invoice',
                    'BillDate',
                    'VehicleReg',
                    'DailyBasisCharges',
                    'JourneyStartDt',
                    'JourneyEndDt',
                    'JourneyFrom',
                    'Journey_From_Id',
                    'JourneyUpto',
                    'Journey_Upto_Id',
                ]);
                if ($travel_data) {
                    if (empty($travel_data->BillDate) || \App\Support\ZeroDate::none($travel_data->BillDate) || str_starts_with((string)$travel_data->BillDate, '0000-00-00') || str_starts_with((string)$travel_data->BillDate, '1970-01-01')) {
                        $travel_data->BillDate = $expense_detail->BillDate ?? null;
                    }
                } else {
                    $travel_data = (object) [
                        'BillDate' => $expense_detail->BillDate ?? null,
                    ];
                }
                $expense_detail->punching_data = $travel_data;
            } elseif ($claimId == 6) {
                $travel_data = DB::connection(session('expense_connection'))->table("y{$yearId}_g{$cgId}_expensefilldata")->where('ExpId', $expid)->first([
                    'SenderName',
                    'SenderAddress',
                    'ServiceProvider',
                    'WeightCharged',
                    'DocketNumber',
                    'DocketBookedDt',
                    'ReceiverName',
                    'ReceiverAddress',
                    'JourneyFrom',
                    'JourneyUpto',
                ]);
                $expense_detail->punching_data = $travel_data;
            } elseif ($claimId == 8) {
                try {
                    $employee = DB::connection(session('hrims_connection'))->table('hrm_employee')->where('EmployeeID', $expense_detail->CrBy)->select('Fname', 'Sname', 'Lname')->first();
                    $fullName = trim("{$employee->Fname} {$employee->Sname} {$employee->Lname}");
                } catch (\Illuminate\Database\QueryException $e) {
                    if (str_contains($e->getMessage(), "doesn't exist") || str_contains($e->getMessage(), 'Table') && str_contains($e->getMessage(), 'hrm_employee')) {
                        Log::warning('HRIMS table not found for company ' . session('company_id', 'unknown') . ': ' . $e->getMessage());
                        return response()->json(['error' => 'Employee data not available for this company'], 500);
                    } else {
                        throw $e;
                    }
                }
                $employee_detail['employee_detail'] = ['full_name' => $fullName];
                $travel_data = DB::connection(session('expense_connection'))->table("y{$yearId}_g{$cgId}_expensefilldata")->where('ExpId', $expid)->first(['BillingName', 'BillingAddress']);
                $expense_detail->punching_data = $travel_data;
            } elseif ($claimId == 9) {
                try {
                    $employee = DB::connection(session('hrims_connection'))->table('hrm_employee')->where('EmployeeID', $expense_detail->CrBy)->select('Fname', 'Sname', 'Lname')->first();
                    $fullName = trim("{$employee->Fname} {$employee->Sname} {$employee->Lname}");
                } catch (\Illuminate\Database\QueryException $e) {
                    if (str_contains($e->getMessage(), "doesn't exist") || str_contains($e->getMessage(), 'Table') && str_contains($e->getMessage(), 'hrm_employee')) {
                        return response()->json(['error' => 'Employee data not available for this company'], 500);
                    } else {
                        throw $e;
                    }
                }
                $employee_detail['employee_detail'] = ['full_name' => $fullName];
                $travel_data = DB::connection(session('expense_connection'))->table("y{$yearId}_g{$cgId}_expensefilldata")->where('ExpId', $expid)->first(['BillingName', 'BillingAddress']);
                $expense_detail->punching_data = $travel_data;
            } elseif ($claimId == 14) {
                $travel_data = DB::connection(session('expense_connection'))->table("y{$yearId}_g{$cgId}_expensefilldata")->where('ExpId', $expid)->first(['Agent', 'Vendor_Id', 'PNRNo', 'TrainBusName', 'IsTravelInsurance', 'TravelInsurance', 'BookingDate', 'JourneyStartDt', 'JourneyEndDt', 'StayDuration', 'JourneyFrom', 'Journey_From_Id', 'JourneyUpto', 'Journey_Upto_Id', 'BookingStatus', 'Class']);
                $expense_detail->punching_data = $travel_data;
            } elseif ($claimId == 15) {
                $travel_data = DB::connection(session('expense_connection'))->table("y{$yearId}_g{$cgId}_expensefilldata")->where('ExpId', $expid)->first([
                    'Agent',
                    'Vendor_Id',
                    'PNRNo',
                    'TrainBusName',
                    'Class',
                    'BookingDate',
                    'JourneyStartDt',
                    'JourneyEndDt',
                    'StayDuration',
                    'JourneyFrom',
                    'Journey_From_Id',
                    'JourneyUpto',
                    'Journey_Upto_Id',
                    'TravelInsurance',
                    'IsTravelInsurance',
                ]);
                $expense_detail->punching_data = $travel_data;
            } elseif ($claimId == 16) {
                $travel_data = DB::connection(session('expense_connection'))->table("y{$yearId}_g{$cgId}_expensefilldata")->where('ExpId', $expid)->first(['BillDate']);
                $particulars = DB::connection(session('expense_connection'))->table("y{$yearId}_expenseclaimsdetails")->where('ExpId', $expid)->select('ecdId', 'ExpId', 'Title', 'Amount', 'Remark')->get();
                if (!$travel_data) {
                    $travel_data = (object) [];
                }
                $travel_data->particulars = $particulars;
                $expense_detail->punching_data = $travel_data;
            } elseif ($claimId == 17) {
                $travel_data = DB::connection(session('expense_connection'))->table("y{$yearId}_g{$cgId}_expensefilldata")->where('ExpId', $expid)->first(['BillDate', 'NoOfPAX', 'PaxDetails', 'PaxDetails_Other']);
                $particulars = DB::connection(session('expense_connection'))->table("y{$yearId}_expenseclaimsdetails")->where('ExpId', $expid)->select('ecdId', 'ExpId', 'Title', 'Amount', 'Remark')->get();
                if (!$travel_data) {
                    $travel_data = (object) [];
                }
                $travel_data->particulars = $particulars;
                $expense_detail->punching_data = $travel_data;
            } elseif ($claimId == 18) {
                $travel_data = DB::connection(session('expense_connection'))->table("y{$yearId}_g{$cgId}_expensefilldata")->where('ExpId', $expid)->first([
                    'BookingDate',
                    'JourneyStartDt',
                    'JourneyEndDt',
                    'StayDuration',
                    'JourneyFrom',
                    'Journey_From_Id',
                    'JourneyUpto',
                    'Journey_Upto_Id',
                ]);
                $expense_detail->punching_data = $travel_data;
            } elseif ($claimId == 42) {
                // PaxDetails / NoOfPAX / PaxDetails_Other: Team Meeting participants (Tracker #35)
                $travel_data = DB::connection(session('expense_connection'))->table("y{$yearId}_g{$cgId}_expensefilldata")->where('ExpId', $expid)->first(['BillDate', 'PaxDetails', 'NoOfPAX', 'PaxDetails_Other']);
                $particulars = DB::connection(session('expense_connection'))->table("y{$yearId}_expenseclaimsdetails")->where('ExpId', $expid)->select('ecdId', 'ExpId', 'Title', 'Amount', 'Remark')->get();
                if (!$travel_data) {
                    $travel_data = (object) [];
                }
                $travel_data->particulars = $particulars;
                $expense_detail->punching_data = $travel_data;
            }
            if (isset($expense_detail->punching_data) && is_object($expense_detail->punching_data)) {
                if (property_exists($expense_detail->punching_data, 'BillDate')) {
                    $bDate = $expense_detail->punching_data->BillDate;
                    if (empty($bDate) || \App\Support\ZeroDate::none($bDate) || str_starts_with((string)$bDate, '0000-00-00') || str_starts_with((string)$bDate, '1970-01-01')) {
                        $expense_detail->punching_data->BillDate = $expense_detail->BillDate ?? null;
                    }
                }
                if (property_exists($expense_detail->punching_data, 'BookingDate')) {
                    $bDate = $expense_detail->punching_data->BookingDate;
                    if (empty($bDate) || \App\Support\ZeroDate::none($bDate) || str_starts_with((string)$bDate, '0000-00-00') || str_starts_with((string)$bDate, '1970-01-01')) {
                        $expense_detail->punching_data->BookingDate = $expense_detail->BillDate ?? null;
                    }
                }
                if (property_exists($expense_detail->punching_data, 'DocketBookedDt')) {
                    $bDate = $expense_detail->punching_data->DocketBookedDt;
                    if (empty($bDate) || \App\Support\ZeroDate::none($bDate) || str_starts_with((string)$bDate, '0000-00-00') || str_starts_with((string)$bDate, '1970-01-01')) {
                        $expense_detail->punching_data->DocketBookedDt = $expense_detail->BillDate ?? null;
                    }
                }
            } elseif (empty($expense_detail->punching_data)) {
                $expense_detail->punching_data = (object) [
                    'BillDate' => $expense_detail->BillDate ?? null,
                ];
            }
            $expense_detail->remark_chat = $remarks;
            if (file_exists($componentPath)) {
                $html = view("components.modal.claim_view.claim_detail_{$claimId}", [
                    'claimId' => $claimId,
                    'cgId' => $cgId,
                    'expense_detail' => $expense_detail,
                    'employee_detail' => $employee_detail,
                ])->render();
            } else {
                $html = '<div class="alert alert-warning">Component for Claim ID ' . $claimId . ' not found.</div>';
            }
        } elseif ($cgId == 7) {
            $activityRow = DB::connection(session('expense_connection'))->table('adv_activity_names')->where('activity_name', $claimId)->select('id as activity_name_id')->first();
            $activityId = $activityRow->activity_name_id ?? 0;
            $expenseHeads = DB::connection(session('expense_connection'))->table('adv_expense_head_name_mapping as m')->join('adv_expense_head_category as c', 'm.expense_head_id', '=', 'c.id')->where('m.activity_id', $activityId)->where('m.checked', 1)->orderBy('c.expense_head_name', 'ASC')->select('c.expense_head_name', 'c.short_code', 'c.field_type', 'c.has_file', 'c.file_required')->get();
            $expenses = [];
            foreach ($expenseHeads as $row) {
                $label = $row->expense_head_name;
                $value = preg_replace('/[^A-Za-z0-9 ]/', '', $label);
                $value = preg_replace('/\s+/', '_', trim($value));
                $value = preg_replace('/__+/', '_', $value);
                $value = strtolower($value);
                $expenses[] = [
                    'label' => $label,
                    'value' => $value,
                    'file_input' => 'file_' . $value . '[]',
                    'field_type' => $row->field_type,
                    'has_file' => (bool) $row->has_file,
                    'file_required' => (bool) $row->file_required,
                ];
            }
            $componentPath = resource_path('views/components/modal/claim_view/claim_detail_g7.blade.php');
            if (file_exists($componentPath)) {
                $filledData = DB::connection(session('expense_connection'))->table("y{$yearId}_g7_expensefilldata")->where('ExpId', $expid)->first();

                // Handle potential missing hrm_employee_general table with proper database connection
                try {
                    $employee = DB::connection(session('hrims_connection'))->table('hrm_employee_general')->where('EmployeeID', $expense_detail->CrBy)->first();
                } catch (\Illuminate\Database\QueryException $e) {
                    if (str_contains($e->getMessage(), "doesn't exist") || str_contains($e->getMessage(), 'Table')) {
                        Log::warning('HRIMS employee_general table not found for company ' . session('company_id', 'unknown') . ': ' . $e->getMessage());
                        return response()->json(['error' => 'Employee data not available for this company'], 500);
                    } else {
                        throw $e;
                    }
                }

                $crops = CoreVertical::getCrops($employee->EmpVertical ?? 1);
                if (!empty($filledData) && !empty($filledData->crop_list)) {
                    $varieties = CoreVariety::getByCrops($filledData->crop_list);
                } else {
                    $varieties = collect();
                }
                $activityTypesList = ActivityType::getByDepartment($employee->DepartmentId ?? 1);
                $html = view('components.modal.claim_view.claim_detail_g7', ['claimId' => $claimId, 'cgId' => $cgId, 'expense_detail' => $expense_detail, 'expenses' => $expenses, 'filledData' => $filledData, 'expId' => $expid, 'activity_types' => $activityTypesList, 'crops' => $crops, 'varieties' => $varieties, 'employee' => $employee, 'viewContext' => $viewContext])->render();
            } else {
                $html = '<div class="alert alert-warning">Component for Claim ID ' . $claimId . ' not found.</div>';
            }
        } else {
            $html = '<div class="alert alert-warning">No component available for cgId = ' . $cgId . '.</div>';
        }
        $uploadedFiles = $this->buildUploadedFilesList($expid, $claimId);

        // Get eligibility validation data and render it as HTML appended to the form
        try {
            $eligibilityService = new EligibilityValidationService();
            $eligibilityData = $eligibilityService->getEligibilityData($expense_detail, $yearId);
            if (!empty($eligibilityData) && ($eligibilityData['status'] ?? '') !== 'unavailable') {
                $html .= $this->renderEligibilityPanel($eligibilityData);
            }
        } catch (\Exception $e) {
            Log::warning('Eligibility validation failed for ExpId ' . $expid . ': ' . $e->getMessage());
        }

        // GST-02: GST amount and invoice number fields on every bill claim (not running km / DA)
        if ((int) $cgId === 1 && ! in_array((int) $claimId, [7, 19, 20], true)) {
            try {
                $g1t = "y{$yearId}_g1_expensefilldata";
                $gcols = array_flip(\Illuminate\Support\Facades\Schema::connection(session('expense_connection'))->getColumnListing($g1t));
                if (isset($gcols['GST_Amount'])) {
                    $gr = DB::connection(session('expense_connection'))->table($g1t)->where('ExpId', $expid)->first(['GST_Amount', 'Invoice']);
                    $html .= view('claims.partials.tax-invoice-fields', ['gstAmount' => $gr->GST_Amount ?? null, 'invoiceNo' => $gr->Invoice ?? null, 'showInvoice' => ! in_array((int) $claimId, [1, 4], true)])->render();
                }
            } catch (\Throwable $e) {
                Log::warning('GST fields failed for ExpId '.$expid.': '.$e->getMessage());
            }
        }

        // DUP-BILL-01: same bill already claimed by another employee (verifier sees it before verifying)
        if ((int) $cgId === 1) {
            $html .= \App\Services\Claims\BillDuplicate::panelHtml((string) session('expense_connection'), (int) $yearId, (int) $expid);
        }
        // POL-08: rail / air class against the eligibility
        if (in_array((int) $claimId, [\App\Services\Claims\TravelClassCheck::RAIL, \App\Services\Claims\TravelClassCheck::AIR], true)) {
            $html .= \App\Services\Claims\TravelClassCheck::panelHtml((string) session('expense_connection'), (int) $yearId, (int) $expid);
        }

        // UPI-PROOF-01: UPI payment proof panel (every bill claim; not the running-km claim)
        if ((int) $claimId !== 7) {
            try {
                $html .= \App\Http\Controllers\UpiProofController::panelHtml((string) session('expense_connection'), (int) session('company_id', 1), (int) $yearId, $expense_detail);
            } catch (\Throwable $e) {
                Log::warning('UPI proof panel failed for ExpId ' . $expid . ': ' . $e->getMessage());
            }
        }

        return $this->jsonResponse([
            'html' => $html,
            'uploaded_files' => $uploadedFiles
        ]);

        } finally {
            // Always restore original connection, year_id, and company_id unconditionally
            if (!empty($originalConnection)) {
                session(['expense_connection' => $originalConnection]);
            }
            if (!empty($originalYearId)) {
                session(['year_id' => $originalYearId]);
            }
            if (!empty($originalCompanyId)) {
                session(['company_id' => $originalCompanyId]);
            }
        }
    }

    /**
     * Sanitize string to ensure valid UTF-8 encoding for JSON serialization
     * Replaces or removes invalid UTF-8 sequences
     */
    private function sanitizeUtf8($value)
    {
        if (is_string($value)) {
            // Convert to valid UTF-8, replacing invalid sequences with replacement character
            return mb_convert_encoding($value, 'UTF-8', 'UTF-8');
        }

        if (is_array($value)) {
            $sanitized = [];
            foreach ($value as $key => $item) {
                $sanitized[$key] = $this->sanitizeUtf8($item);
            }
            return $sanitized;
        }

        if (is_object($value)) {
            $data = (array) $value;
            $sanitized = [];
            foreach ($data as $key => $item) {
                $sanitized[$key] = $this->sanitizeUtf8($item);
            }
            return (object) $sanitized;
        }

        return $value;
    }

    /**
     * Return a JSON response with UTF-8 sanitization applied
     */
    protected function jsonResponse($data = [], $status = 200, $headers = [])
    {
        return response()->json(
            $this->sanitizeUtf8($data),
            $status,
            $headers
        );
    }

    /**
     * Render eligibility panel as HTML (modern card UI)
     */
    private function renderEligibilityPanel(array $data): string
    {
        $status = $data['status'] ?? 'no_limit';
        $claimId = $data['claim_id'] ?? 0;
        $details = $data['details'] ?? [];
        $baseInfo = $data['base_info'] ?? [];

        // Status styling
        $statusConfig = [
            'exceeded' => ['bg' => '#FEF2F2', 'border' => '#FECACA', 'text' => '#DC2626', 'icon' => 'ri-error-warning-fill', 'label' => 'Exceeds Limit'],
            'within_limit' => ['bg' => '#F0FDF4', 'border' => '#BBF7D0', 'text' => '#16A34A', 'icon' => 'ri-checkbox-circle-fill', 'label' => 'Within Limit'],
            'no_limit' => ['bg' => '#F8FAFC', 'border' => '#E2E8F0', 'text' => '#64748B', 'icon' => 'ri-information-line', 'label' => 'No Limit Defined'],
        ];
        $sc = $statusConfig[$status] ?? $statusConfig['no_limit'];

        $html = '<div style="margin-top:16px;border-radius:10px;border:1px solid ' . $sc['border'] . ';background:' . $sc['bg'] . ';padding:14px 16px;font-size:12px;">';

        // Header with status
        $html .= '<div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:12px;">';
        $html .= '<div style="display:flex;align-items:center;gap:6px;">';
        $html .= '<i class="ri-shield-check-line" style="font-size:16px;color:#15648C;"></i>';
        $html .= '<span style="font-weight:600;font-size:13px;color:#1E293B;">Policy Check</span>';
        $html .= '</div>';
        $html .= '<span style="display:inline-flex;align-items:center;gap:4px;padding:3px 10px;border-radius:20px;font-size:11px;font-weight:600;color:' . $sc['text'] . ';background:white;border:1px solid ' . $sc['border'] . ';">';
        $html .= '<i class="' . $sc['icon'] . '"></i>' . $sc['label'];
        $html .= '</span>';
        $html .= '</div>';

        // Amount comparison cards row
        $html .= '<div style="display:flex;gap:10px;margin-bottom:12px;flex-wrap:wrap;">';

        // Claimed Amount card
        $html .= '<div style="flex:1;min-width:100px;background:white;border-radius:8px;padding:10px 12px;border:1px solid #E2E8F0;text-align:center;">';
        $html .= '<div style="font-size:10px;color:#64748B;text-transform:uppercase;letter-spacing:0.5px;">Claimed</div>';
        $html .= '<div style="font-size:16px;font-weight:700;color:#1E293B;margin-top:2px;">₹' . number_format($data['claimed_amount'] ?? 0) . '</div>';
        $html .= '</div>';

        // Eligible Limit card
        if (($data['eligible_limit'] ?? 0) > 0) {
            $limitColor = ($data['exceeds_limit'] ?? false) ? '#DC2626' : '#16A34A';
            $html .= '<div style="flex:1;min-width:100px;background:white;border-radius:8px;padding:10px 12px;border:1px solid #E2E8F0;text-align:center;">';
            $html .= '<div style="font-size:10px;color:#64748B;text-transform:uppercase;letter-spacing:0.5px;">Eligible Limit</div>';
            $html .= '<div style="font-size:16px;font-weight:700;color:' . $limitColor . ';margin-top:2px;">₹' . number_format($data['eligible_limit']) . '</div>';
            $html .= '</div>';
        }

        // Excess card (only if exceeded)
        if (($data['exceeds_limit'] ?? false) && ($data['excess_amount'] ?? 0) > 0) {
            $html .= '<div style="flex:1;min-width:100px;background:#FEF2F2;border-radius:8px;padding:10px 12px;border:1px solid #FECACA;text-align:center;">';
            $html .= '<div style="font-size:10px;color:#DC2626;text-transform:uppercase;letter-spacing:0.5px;">Excess</div>';
            $html .= '<div style="font-size:16px;font-weight:700;color:#DC2626;margin-top:2px;">₹' . number_format($data['excess_amount']) . '</div>';
            $html .= '</div>';
        }

        $html .= '</div>';

        // Details grid
        $html .= '<div style="display:grid;grid-template-columns:repeat(2,1fr);gap:6px 16px;">';

        // Basis
        $html .= $this->renderDetailRow('Basis', $data['validation_basis'] ?? '');

        // Grade
        if (!empty($baseInfo['grade']) && $baseInfo['grade'] !== 'N/A') {
            $html .= $this->renderDetailRow('Grade', $baseInfo['grade']);
        }

        // Policy
        if (!empty($baseInfo['policy_name']) && ($baseInfo['policy_id'] ?? 0) > 0) {
            $html .= $this->renderDetailRow('Policy', $baseInfo['policy_name']);
        }

        // Travel/KM specific details
        if ($claimId == 7) {
            $html .= $this->renderDetailRow('Total KM', ($details['total_km'] ?? 0) . ' km');
            $html .= $this->renderDetailRow('Vehicle', $details['wheel_type'] ?? '');
            $html .= $this->renderDetailRow('Rate/KM', '₹' . number_format($details['calculated_rate'] ?? 0, 2));
            $html .= $this->renderDetailRow('Month Running', ($details['month_running_km'] ?? 0) . ' km');
            if (($details['base_rate_2w'] ?? 0) > 0) {
                $html .= $this->renderDetailRow('2W Rate', '₹' . number_format($details['base_rate_2w'], 2));
            }
            if (($details['base_rate_4w'] ?? 0) > 0) {
                $html .= $this->renderDetailRow('4W Rate', '₹' . number_format($details['base_rate_4w'], 2));
            }
        }

        // Lodging specific
        if ($claimId == 1 && !empty($details['all_limits'])) {
            // Show duration and per-day limit
            if (($details['duration_days'] ?? 0) > 0) {
                $html .= $this->renderDetailRow('Duration', ($details['duration_days']) . ' day(s)');
                $html .= $this->renderDetailRow('Per Day Limit', '₹' . number_format($details['per_day_limit'] ?? 0));
            }
            $cityCategory = $details['city_category'] ?? '';
            foreach ($details['all_limits'] as $cat => $limit) {
                $catLetter = str_replace('Category ', '', $cat);
                $isActive = $catLetter === $cityCategory;
                $label = $cat . ($isActive ? ' ✓' : '');
                $value = '₹' . number_format($limit) . '/day';
                $html .= $this->renderDetailRow($label, $value, $isActive);
            }
        }

        // DA specific
        if (in_array($claimId, [19, 20]) && !empty($details['raw_limit_value'])) {
            $html .= $this->renderDetailRow('Eligibility', $details['raw_limit_value']);
        }

        $html .= '</div>'; // close grid
        $html .= '</div>'; // close card

        return $html;
    }

    /**
     * Render a single detail row for the eligibility panel
     */
    private function renderDetailRow(string $label, string $value, bool $highlight = false): string
    {
        $bgStyle = $highlight ? 'background:#EFF6FF;border-radius:4px;padding:2px 6px;' : '';
        $fontWeight = $highlight ? 'font-weight:600;' : '';
        return '<div style="display:flex;justify-content:space-between;padding:4px 0;border-bottom:1px solid #F1F5F9;' . $bgStyle . '">'
            . '<span style="color:#64748B;">' . e($label) . '</span>'
            . '<span style="color:#1E293B;' . $fontWeight . '">' . e($value) . '</span>'
            . '</div>';
    }

    private function buildUploadedFilesList($expid, $claimid): array
    {
        $cgId = ClaimType::getCgIdByClaimId($claimid);
        $yearId = session('year_id');
        $basePath = $this->getExpenseBasePath();
        $backupFolder = $this->getBackupFolder();

        $replaceChars = ['~' => '_', '(' => '_', ')' => '_', ' ' => '_'];
        $uploadedFiles = [];

        if ($cgId == 1) {
            // UAT E-05: shared map incl. 110 (online payment receipt, tracker #16).
            $uploadSequenceMap = $this->billUploadCategoryMap();
            $categoryLabels = $this->billUploadLabels();
            $sequenceToNameMap = array_flip($uploadSequenceMap);
            $uploadsTable = "y{$yearId}_claimuploads";
            $claimsTable = "y{$yearId}_expenseclaims";

            $files = DB::connection(session('expense_connection'))
                ->table($uploadsTable . ' as u')
                ->join($claimsTable . ' as c', 'u.ExpId', '=', 'c.ExpId')
                ->where('u.ExpId', $expid)
                ->orderBy('u.UploadSequence')
                ->select('u.cuId', 'u.ExpId', 'u.FileName', 'u.UploadSequence', 'u.created_at', 'c.CrBy', 'c.ClaimYearId')
                ->get();

            $s3 = Storage::disk('s3');
            foreach ($files as $item) {
                $fileLabel = $sequenceToNameMap[$item->UploadSequence] ?? 'File_Others';
                $fileName = strtr($item->FileName, $replaceChars);
                $candidates = $this->candidateFilePaths(1, $basePath, $item->ClaimYearId, $claimid, $item->CrBy, $fileName);
                $realPath = $this->findExistingS3Path($s3, $candidates);
                $url = ($realPath ? $this->getS3Url($realPath) : null)
                    ?: "https://vnragro.com/File_Bkp/{$backupFolder}/{$item->ClaimYearId}/{$item->CrBy}/" . rawurlencode($fileName);

                $uploadedFiles[] = [
                    'sequence' => $item->UploadSequence ?? 999,
                    'cuId' => $item->cuId,
                    'file_column' => $fileLabel,
                    'file_label' => $categoryLabels[$fileLabel] ?? null,
                    'is_payment_receipt' => in_array((int) $item->UploadSequence, [self::PAYMENT_RECEIPT_SEQUENCE, \App\Services\Claims\UpiProof::SEQUENCE], true),
                    'file_name' => $fileName,
                    'file_path' => $fileName,
                    'file_url' => $url,
                    'created_at' => $item->created_at,
                    's3_path' => $realPath,
                ];
            }
        } elseif ($cgId == 7) {
            $g7Table = "y{$yearId}_g7_expensefilldata";
            $claimsTable = "y{$yearId}_expenseclaims";

            $claim = DB::connection(session('expense_connection'))
                ->table($claimsTable)
                ->where('ExpId', $expid)
                ->select('CrBy', 'ClaimYearId')
                ->first();

            if (!$claim)
                return $uploadedFiles;

            $data = DB::connection(session('expense_connection'))
                ->table($g7Table)
                ->where('ExpId', $expid)
                ->first();

            $categories = DB::connection(session('expense_connection'))
                ->table('adv_expense_head_category')
                ->where('status', 1)
                ->where('has_file', 1)
                ->get()
                ->keyBy(fn($cat) => 'file_' . $this->sanitizeColumnName($cat->expense_head_name ?? 'unknown'));

            if ($data) {
                $s3 = Storage::disk('s3');
                foreach ((array) $data as $column => $value) {
                    if (str_starts_with($column, 'file_') && !empty($value)) {
                        $filesArray = json_decode($value, true);
                        if (is_array($filesArray)) {
                            $category = $categories[$column] ?? null;
                            foreach ($filesArray as $fileNameRaw) {
                                $fileName = strtr($fileNameRaw, $replaceChars);
                                $candidates = $this->candidateFilePaths(7, $basePath, $claim->ClaimYearId, $claimid, $claim->CrBy, $fileName);
                                $realPath = $this->findExistingS3Path($s3, $candidates);
                                $url = $realPath
                                    ? $this->getS3Url($realPath)
                                    : null;
                                $url = $url
                                    ?: "https://vnragro.com/File_Bkp/{$backupFolder}/activity/{$claim->ClaimYearId}/{$claimid}/{$claim->CrBy}/" . rawurlencode($fileName);

                                $uploadedFiles[$column]['category_name'] = $category->expense_head_name ?? 'Unknown';
                                $uploadedFiles[$column]['short_code'] = $category->short_code ?? 'NA';
                                $uploadedFiles[$column]['files'][] = [
                                    'sequence' => 999,
                                    'file_column' => $column,
                                    'file_name' => $fileName,
                                    'file_path' => $fileName,
                                    'file_url' => $url,
                                    's3_path' => $realPath,
                                ];
                            }
                        }
                    }
                }
            }
        }

        return $uploadedFiles;
    }

    /**
     * Fixed set of document categories bill-type (cgId != 7) claims file their
     * uploads under. Keyed by label, valued by the UploadSequence stored in
     * y{year}_claimuploads. Shared by file listing and the change-head flow
     * so a "target category" always resolves to a real UploadSequence.
     */
    private function billUploadCategoryMap(): array
    {
        return [
            'File_Meeting_Hall' => 101,
            'File_Lodging' => 102,
            'File_Meals' => 103,
            'File_Business_Entertainment' => 104,
            'File_Others' => 105,
            'File_Toll_Pass' => 106,
            'File_Toll_Tax' => 107,
            'File_International_Recharge' => 108,
            'File_Printing_Stationery' => 109,
            'File_Payment_Receipt' => 110,
            'File_UPI_Proof' => \App\Services\Claims\UpiProof::SEQUENCE, // UPI-PROOF-01
        ];
    }

    /**
     * List the fixed bill-claim upload categories for the "change claim head"
     * mapping UI when the target head is a Bill (cgId != 7).
     */
    /** Display labels of billUploadCategoryMap() (punch / verify / approve file lists and change-head mapping). */
    private function billUploadLabels(): array
    {
        return [
            'File_Meeting_Hall' => 'Meeting Hall',
            'File_Lodging' => 'Lodging',
            'File_Meals' => 'Meals',
            'File_Business_Entertainment' => 'Business Entertainment',
            'File_Others' => 'Others',
            'File_Toll_Pass' => 'Toll Pass',
            'File_Toll_Tax' => 'Toll Tax',
            'File_International_Recharge' => 'International Recharge',
            'File_Printing_Stationery' => 'Printing & Stationery',
            'File_Payment_Receipt' => 'Online Payment Receipt',
            'File_UPI_Proof' => 'UPI Payment Proof',
        ];
    }

    public function getBillUploadCategories()
    {
        $labels = $this->billUploadLabels();

        $categories = [];
        foreach ($this->billUploadCategoryMap() as $key => $sequence) {
            $categories[] = [
                'value' => $sequence,
                'label' => $labels[$key] ?? str_replace('_', ' ', preg_replace('/^File_/', '', $key)),
                'column' => $key,
            ];
        }

        return $this->jsonSuccess($categories);
    }

    protected function getExpenseBasePath(): string
    {
        $companyId = session('company_id');
        return ($companyId == 11) ? 'VVNR_Expense' : 'Expense';
    }

    protected function getBackupFolder(): string
    {
        $companyId = session('company_id');
        return ($companyId == 11) ? 'vxeasyfile' : 'xeasyfile';
    }

    protected function getS3Url(string $path): ?string
    {
        if ($path === '') {
            return null;
        }
        $disk = Storage::disk('s3');
        if (!$disk->exists($path)) {
            return null;
        }
        // Generate a presigned temporary URL (valid for 1 hour)
        try {
            return $disk->temporaryUrl($path, now()->addHour());
        } catch (\Exception $e) {
            // Fallback to plain URL if temporaryUrl is not supported
            if ($disk instanceof FilesystemAdapter && method_exists($disk, 'url')) {
                return $disk->url($path);
            }
            return null;
        }
    }

    /**
     * Ordered list of S3 paths a file might actually live at.
     *
     * The web upload flow (uploadFiles()) writes Activity files to the
     * "canonical" nested path: {basePath}/activity/{yearId}/{claimId}/{crBy}/{file}.
     * But the mobile app's S3Service::uploadFile()/generateS3Path() (used by
     * ClaimActionsController, ClaimController, ExtractionController,
     * OdometerController) — and BaseMobileController::resolveFileUrl()'s own
     * read fallback — ALWAYS write/read a flat path regardless of claim type:
     * "Expense/{yearId}/{userId}/{file}", with a legacy "Expense/6/{userId}/{file}"
     * fallback for a hardcoded historical yearId. Most real Activity-claim
     * files are uploaded from the mobile app, so they live at the flat path,
     * not the nested one — that mismatch is why file lookups/relocation can
     * silently miss real files. Probe every known convention (dynamic
     * basePath first, then the literal "Expense" prefix some pre-fix records
     * still use) so we reliably find the file wherever it actually sits.
     */
    private function candidateFilePaths(int $cgId, string $basePath, $claimYearId, $claimId, $crBy, string $fileName): array
    {
        $prefixes = array_unique(array_filter([$basePath, 'Expense']));
        $paths = [];
        foreach ($prefixes as $prefix) {
            if ($cgId == 7) {
                $paths[] = "{$prefix}/activity/{$claimYearId}/{$claimId}/{$crBy}/{$fileName}";
            }
            $paths[] = "{$prefix}/{$claimYearId}/{$crBy}/{$fileName}";
            $paths[] = "{$prefix}/6/{$crBy}/{$fileName}";
        }
        return array_values(array_unique($paths));
    }

    /**
     * Return the first candidate path that actually exists on S3, or null.
     */
    private function findExistingS3Path($disk, array $candidates): ?string
    {
        foreach ($candidates as $path) {
            try {
                if ($disk->exists($path)) {
                    return $path;
                }
            } catch (\Exception $e) {
                continue;
            }
        }
        return null;
    }

    public function viewUploadedFiles(Request $request, $expid, $claimid)
    {
        $cgId = ClaimType::getCgIdByClaimId($claimid);
        $yearId = (int) ($request->input('year_id') ?: session('year_id'));
        $companyId = $request->input('company_id');
        $originalConnection = session('expense_connection');
        $originalYearId = session('year_id');
        $originalCompanyId = session('company_id');

        try {
            if ($request->filled('year_id')) {
                session(['year_id' => (int) $request->input('year_id')]);
            }
            if ($companyId) {
                $expenseConnection = DatabaseSwitcher::getExpenseConnection((int) $companyId);
                session(['expense_connection' => $expenseConnection]);
                session(['company_id' => (int) $companyId]);
            }

            $basePath = $this->getExpenseBasePath();
            $backupFolder = $this->getBackupFolder();

        $replaceChars = [
            '~' => '_',
            '(' => '_',
            ')' => '_',
            ' ' => '_',
        ];

        $uploadedFiles = [];

        if ($cgId == 1) {
            // UAT E-05: shared map incl. 110 (online payment receipt, tracker #16).
            $uploadSequenceMap = $this->billUploadCategoryMap();
            $categoryLabels = $this->billUploadLabels();

            $sequenceToNameMap = array_flip($uploadSequenceMap);

            $uploadsTable = "y{$yearId}_claimuploads";
            $claimsTable = "y{$yearId}_expenseclaims";

            $files = DB::connection(session('expense_connection'))
                ->table($uploadsTable . ' as u')
                ->join($claimsTable . ' as c', 'u.ExpId', '=', 'c.ExpId')
                ->where('u.ExpId', $expid)
                ->orderBy('u.UploadSequence')
                ->select(
                    'u.cuId',
                    'u.ExpId',
                    'u.FileName',
                    'u.UploadSequence',
                    'u.created_at',
                    'c.CrBy',
                    'c.ClaimYearId'
                )
                ->get();

            $s3 = Storage::disk('s3');
            foreach ($files as $item) {
                $fileLabel = $sequenceToNameMap[$item->UploadSequence] ?? 'File_Others';

                // 🔹 sanitize filename
                $fileName = strtr($item->FileName, $replaceChars);

                $candidates = $this->candidateFilePaths(1, $basePath, $item->ClaimYearId, $claimid, $item->CrBy, $fileName);
                $realPath = $this->findExistingS3Path($s3, $candidates);
                $url = $realPath ? $this->getS3Url($realPath) : null;

                // fallback
                if (!$url) {
                    $url = "https://vnragro.com/File_Bkp/{$backupFolder}/{$item->ClaimYearId}/{$item->CrBy}/" . rawurlencode($fileName);
                }

                $uploadedFiles[] = [
                    'sequence'    => $item->UploadSequence ?? 999,
                    'cuId'        => $item->cuId,
                    'file_column' => $fileLabel,
                    'file_label'  => $categoryLabels[$fileLabel] ?? null,
                    'is_payment_receipt' => in_array((int) $item->UploadSequence, [self::PAYMENT_RECEIPT_SEQUENCE, \App\Services\Claims\UpiProof::SEQUENCE], true),
                    'file_path'   => $fileName,
                    'file_url'    => $url,
                    'created_at'  => $item->created_at,
                    's3_path'     => $realPath,
                    'claim_amt'   => $claim->FilledTAmt ?? 0,
                    'ver_amt'     => $claim->VerifyTAmt ?? 0,
                    'app_amt'     => $claim->ApprTAmt ?? 0,
                    'fin_amt'     => $claim->FinancedTAmt ?? 0,
                ];
            }
        } elseif ($cgId == 7) {
            $g7Table = "y{$yearId}_g7_expensefilldata";
            $claimsTable = "y{$yearId}_expenseclaims";

            $claim = DB::connection(session('expense_connection'))
                ->table($claimsTable)
                ->where('ExpId', $expid)
                ->select('CrBy', 'ClaimYearId')
                ->first();

            if (!$claim) {
                return $this->jsonError('Claim not found', 404);
            }

            $data = DB::connection(session('expense_connection'))
                ->table($g7Table)
                ->where('ExpId', $expid)
                ->first();

            $categories = DB::connection(session('expense_connection'))
                ->table('adv_expense_head_category')
                ->where('status', 1)
                ->where('has_file', 1)
                ->get()
                ->keyBy(function ($cat) {
                    return 'file_' . $this->sanitizeColumnName($cat->expense_head_name ?? 'unknown');
                });

            if ($data) {
                $s3 = Storage::disk('s3');
                foreach ((array) $data as $column => $value) {
                    if (str_starts_with($column, 'file_') && !empty($value)) {
                        $filesArray = json_decode($value, true);

                        if (is_array($filesArray)) {
                            $category = $categories[$column] ?? null;
                            $amountCol = preg_replace('/^file_/', '', $column);
                            $headAmount = $data->{$amountCol} ?? null;
                            $verAmt = $data->{"ver_{$amountCol}"} ?? null;
                            $appAmt = $data->{"app_{$amountCol}"} ?? null;
                            $finAmt = $data->{"pay_{$amountCol}"} ?? null;
                            $verRemark = $data->{"ver_remark_{$amountCol}"} ?? null;
                            $appRemark = $data->{"app_remark_{$amountCol}"} ?? null;
                            $finRemark = $data->{"pay_remark_{$amountCol}"} ?? null;

                            $uploadedFiles[$column]['category_name'] = $category->expense_head_name ?? 'Unknown';
                            $uploadedFiles[$column]['short_code']    = $category->short_code ?? 'NA';
                            $uploadedFiles[$column]['amount']        = $headAmount;

                            foreach ($filesArray as $fileNameRaw) {
                                // 🔹 sanitize filename
                                $fileName = strtr($fileNameRaw, $replaceChars);

                                $candidates = $this->candidateFilePaths(7, $basePath, $claim->ClaimYearId, $claimid, $claim->CrBy, $fileName);
                                $realPath = $this->findExistingS3Path($s3, $candidates);
                                $url = $realPath ? $this->getS3Url($realPath) : null;

                                if (!$url) {
                                    $url = "https://vnragro.com/File_Bkp/{$backupFolder}/activity/{$claim->ClaimYearId}/{$claimid}/{$claim->CrBy}/" . rawurlencode($fileName);
                                }

                                $uploadedFiles[$column]['files'][] = [
                                    'sequence'    => 999,
                                    'file_column' => $column,
                                    'file_path'   => $fileName,
                                    'file_url'    => $url,
                                    's3_path'     => $realPath,
                                    'head_slug'   => $amountCol,
                                    'claim_amt'   => $headAmount,
                                    'ver_amt'     => $verAmt,
                                    'app_amt'     => $appAmt,
                                    'fin_amt'     => $finAmt,
                                    'ver_remark'  => $verRemark,
                                    'app_remark'  => $appRemark,
                                    'fin_remark'  => $finRemark,
                                ];
                            }
                        }
                    }
                }
            }
        }

        return $this->jsonSuccess([
            'expid' => $expid,
            'cgid' => $cgId,
            'uploaded_files' => $uploadedFiles,
        ]);
        } finally {
            if (!empty($originalConnection)) {
                session(['expense_connection' => $originalConnection]);
            }
            if (!empty($originalYearId)) {
                session(['year_id' => $originalYearId]);
            }
            if (!empty($originalCompanyId)) {
                session(['company_id' => $originalCompanyId]);
            }
        }
    }

    public function uploadClaimImage(Request $request)
    {
        $request->validate([
            'expid'    => 'required|integer',
            'image'    => 'required|file|mimes:jpg,jpeg,png,gif,bmp,webp,pdf|max:10240',
            'sequence' => 'nullable|integer',
        ]);
        if ($refused = $this->refuseClaimEdit($request->input('expid'))) { // APPR-RO-01
            return $refused;
        }

        $yearId    = session('year_id');
        $expId     = $request->input('expid');
        $basePath  = $this->getExpenseBasePath();
        $sequence  = $request->input('sequence', 1);

        $claimsTable  = ExpenseClaim::tableName($yearId);
        $claim = DB::connection(session('expense_connection'))
            ->table($claimsTable)
            ->where('ExpId', $expId)
            ->select('CrBy', 'ClaimYearId', 'ClaimId')
            ->first();

        if (!$claim) {
            return $this->jsonError('Claim not found.');
        }

        $cgId = ClaimType::getCgIdByClaimId($claim->ClaimId);
        $file      = $request->file('image');
        $ext       = $file->getClientOriginalExtension();
        $timestamp = now()->format('dmy His');
        $timestamp = str_replace(' ', '', $timestamp);
        $fileName  = "Img_{$expId}_{$timestamp}_1.{$ext}";

        if ($cgId == 7) {
            $s3Path = "{$basePath}/activity/{$claim->ClaimYearId}/{$claim->ClaimId}/{$claim->CrBy}/{$fileName}";
        } else {
            $s3Path = "{$basePath}/{$claim->ClaimYearId}/{$claim->CrBy}/{$fileName}";
        }

        // UAT R-01: a put() that returns false is a failure too (it used to be ignored), and the
        // file is deleted again if the DB write below fails.
        $stored = new \App\Services\ClaimFiles\ClaimFileBatch();
        try {
            $stored->put($s3Path, $file, 'image');
        } catch (\App\Services\ClaimFiles\ClaimFileException $e) {
            Log::error('Claim image upload failed', ['expid' => $expId, 'error' => $e->getMessage()]);
            return $this->jsonError($e->getMessage());
        }

        try {
            $stored->commitWith(session('expense_connection'), function () use ($cgId, $claim, $expId, $yearId, $fileName, $sequence) {

                if ($cgId == 7) {
                    $activityRow = DB::connection(session('expense_connection'))
                        ->table('adv_activity_names')
                        ->where('activity_name', $claim->ClaimId)
                        ->select('id as activity_name_id')
                        ->first();
                    $activityId = $activityRow->activity_name_id ?? 0;

                    $firstHead = DB::connection(session('expense_connection'))
                        ->table('adv_expense_head_name_mapping as m')
                        ->join('adv_expense_head_category as c', 'm.expense_head_id', '=', 'c.id')
                        ->where('m.activity_id', $activityId)
                        ->where('m.checked', 1)
                        ->where('c.has_file', 1)
                        ->orderBy('c.expense_head_name', 'ASC')
                        ->select('c.expense_head_name')
                        ->first();

                    $headCol = $firstHead ? ('file_' . $this->sanitizeColumnName($firstHead->expense_head_name)) : 'file_others';

                    $g7Table = "y{$yearId}_g7_expensefilldata";
                    $g7Row = DB::connection(session('expense_connection'))->table($g7Table)->where('ExpId', $expId)->first();
                    $existingFiles = [];
                    if ($g7Row && !empty($g7Row->{$headCol})) {
                        $existingFiles = json_decode($g7Row->{$headCol}, true) ?: [];
                    }
                    $existingFiles[] = $fileName;

                    DB::connection(session('expense_connection'))->table($g7Table)->updateOrInsert(
                        ['ExpId' => $expId],
                        [
                            $headCol => json_encode(array_values(array_unique($existingFiles))),
                            'updated_at' => now(),
                            'created_at' => now(),
                        ]
                    );
                } else {
                    $uploadsTable = "y{$yearId}_claimuploads";
                    DB::connection(session('expense_connection'))
                        ->table($uploadsTable)
                        ->insert([
                            'ExpId'          => $expId,
                            'FileName'       => $fileName,
                            'UploadSequence' => $sequence,
                            'created_at'     => now(),
                        ]);
                }
            });
        } catch (\Throwable $e) {
            Log::error('Claim image DB write failed', ['expid' => $expId, 'error' => $e->getMessage()]);
            return $this->jsonError('The file could not be attached to the claim: ' . $e->getMessage());
        }

        $url = $this->getS3Url($s3Path)
            ?: app(\App\Services\S3Service::class)->getFileViewUrl($s3Path);

        return $this->jsonSuccess([
            'file_url'  => $url,
            'file_path' => $fileName,
            'sequence'  => $sequence,
        ], 'Image uploaded successfully.');
    }

    /**
     * Get expense heads/bill types for a given activity claimId (cgId == 7).
     */
    public function getActivityExpenseHeads(Request $request, $claimId)
    {
        $cgId = ClaimType::getCgIdByClaimId($claimId);
        if ($cgId != 7) {
            return $this->jsonSuccess([
                'is_activity'   => false,
                'cg_id'         => $cgId,
                'expense_heads' => []
            ]);
        }

        $activityRow = DB::connection(session('expense_connection'))
            ->table('adv_activity_names')
            ->where('activity_name', $claimId)
            ->select('id as activity_name_id')
            ->first();
        $activityId = $activityRow->activity_name_id ?? 0;

        $heads = DB::connection(session('expense_connection'))
            ->table('adv_expense_head_name_mapping as m')
            ->join('adv_expense_head_category as c', 'm.expense_head_id', '=', 'c.id')
            ->where('m.activity_id', $activityId)
            ->where('m.checked', 1)
            ->where('c.has_file', 1)
            ->orderBy('c.expense_head_name', 'ASC')
            ->select('c.id', 'c.expense_head_name', 'c.short_code')
            ->get();

        if ($heads->isEmpty()) {
            $heads = DB::connection(session('expense_connection'))
                ->table('adv_expense_head_category')
                ->where('status', 1)
                ->where('has_file', 1)
                ->orderBy('expense_head_name', 'ASC')
                ->select('id', 'expense_head_name', 'short_code')
                ->get();
        }

        $formattedHeads = $heads->map(function ($row) {
            $label = $row->expense_head_name;
            $val = preg_replace('/[^A-Za-z0-9 ]/', '', $label);
            $val = preg_replace('/\s+/', '_', trim($val));
            $val = preg_replace('/__+/', '_', $val);
            $val = strtolower($val);
            return [
                'id'         => $row->id,
                'label'      => $label,
                'value'      => $val,
                'column'     => 'file_' . $val,
                'short_code' => $row->short_code ?? $label,
            ];
        });

        return $this->jsonSuccess([
            'is_activity'   => true,
            'cg_id'         => $cgId,
            'expense_heads' => $formattedHeads
        ]);
    }

    /**
     * Change a claim's head (ClaimId) — covers Activity->Activity (different
     * activity), Bill->Activity, and Activity->Bill.
     * Preserves amounts and activity details (Crops, Varieties, Activity Type, Bill Date)
     * during Activity-to-Activity transitions.
     */
    public function changeClaimHead(Request $request)
    {
        $request->validate([
            'expid'         => 'required|integer',
            'new_claim_id'  => 'required|integer',
            'file_mappings' => 'nullable|array',
        ]);

        $expId        = $request->input('expid');
        $newClaimId   = (int) $request->input('new_claim_id');
        $fileMappings = $request->input('file_mappings', []);
        $yearId       = session('year_id');
        $connName     = session('expense_connection');
        $basePath     = $this->getExpenseBasePath();
        $replaceChars = ['~' => '_', '(' => '_', ')' => '_', ' ' => '_'];

        $claimsTable = ExpenseClaim::tableName($yearId);
        $claim = DB::connection($connName)
            ->table($claimsTable)
            ->where('ExpId', $expId)
            ->first();

        if (!$claim) {
            return $this->jsonError('Claim not found.', 404);
        }

        if (!in_array($claim->ClaimStatus, self::HEAD_CHANGE_ALLOWED_STATUSES, true)) {
            return $this->jsonError(
                "Claim head cannot be changed once the claim has moved past Draft/Filled stage (current status: {$claim->ClaimStatus}).",
                422
            );
        }

        $oldClaimId = $claim->ClaimId;
        if ($newClaimId === (int) $oldClaimId) {
            return $this->jsonError('Claim is already of this type.', 422);
        }

        $oldCgId = ClaimType::getCgIdByClaimId($oldClaimId);
        $newCgId = ClaimType::getCgIdByClaimId($newClaimId);

        if ($oldCgId != 7 && $newCgId != 7) {
            return $this->jsonError('Use the claim type dropdown directly for Bill-to-Bill changes; no file relocation is needed.', 422);
        }

        $claimYearId = $claim->ClaimYearId;
        $crBy        = $claim->CrBy;

        // Fetch existing G7 data if switching from an Activity claim
        $oldG7 = null;
        if ($oldCgId == 7) {
            $oldG7 = DB::connection($connName)->table("y{$yearId}_g7_expensefilldata")->where('ExpId', $expId)->first();
        }

        $existingFiles     = $this->buildUploadedFilesList($expId, $oldClaimId);
        $existingFilePaths = $this->flattenUploadedFilesWithPaths($existingFiles);
        $existingFileNames = array_keys($existingFilePaths);

        $normalizedMappings = [];
        $headToAmountsMap   = [];

        foreach ($fileMappings as $fm) {
            if (!is_array($fm) || empty($fm['file_name'])) {
                continue;
            }
            $target = $fm['target'] ?? null;
            $normalizedMappings[$fm['file_name']] = $target;

            if ($target) {
                $headSlug = preg_replace('/^file_/', '', (string) $target);
                if (isset($fm['amount']) && $fm['amount'] !== '' && $fm['amount'] !== null) {
                    $amt = floatval($fm['amount']);
                    $headToAmountsMap[$headSlug] = ($headToAmountsMap[$headSlug] ?? 0) + $amt;
                }
            }
        }

        $missing = array_values(array_diff($existingFileNames, array_keys($normalizedMappings)));
        if (!empty($missing) && !empty($existingFileNames)) {
            return $this->jsonError(
                'Please assign a target category for every file before changing the claim head: ' . implode(', ', $missing),
                422
            );
        }

        $s3        = Storage::disk('s3');
        $s3Service = app(\App\Services\S3Service::class);
        $relocated = [];
        $errors    = [];

        $headToFilesMap  = [];
        $billUploadRows  = [];
        $validSequences  = array_values($this->billUploadCategoryMap());

        foreach ($normalizedMappings as $rawName => $target) {
            $fileName = strtr($rawName, $replaceChars);

            $oldPath = $existingFilePaths[$rawName]
                ?? $existingFilePaths[$fileName]
                ?? $this->findExistingS3Path($s3, $this->candidateFilePaths($oldCgId, $basePath, $claimYearId, $oldClaimId, $crBy, $fileName));

            $newPath = ($newCgId == 7)
                ? "{$basePath}/activity/{$claimYearId}/{$newClaimId}/{$crBy}/{$fileName}"
                : "{$basePath}/{$claimYearId}/{$crBy}/{$fileName}";

            if (!$oldPath && !$s3->exists($newPath)) {
                $errors[] = "File not found on S3 under any known path: {$fileName}";
                continue;
            }

            if ($oldPath && $oldPath !== $newPath) {
                $moveResult = $s3Service->transferPath($oldPath, $newPath, true);
                if (!($moveResult['success'] ?? false)) {
                    Log::error('changeClaimHead: S3 move error', [
                        'expid' => $expId, 'old_path' => $oldPath, 'new_path' => $newPath,
                        'message' => $moveResult['message'] ?? 'unknown',
                    ]);
                    $errors[] = "Failed to move {$fileName} from {$oldPath}: " . ($moveResult['message'] ?? 'unknown error');
                    continue;
                }
            }

            $url = $this->getS3Url($newPath) ?: $s3->url($newPath);

            if ($newCgId == 7) {
                $head = is_string($target) ? $target : '';
                if ($head !== '' && !str_starts_with($head, 'file_')) {
                    $head = 'file_' . $head;
                }
                if ($head === '') {
                    $errors[] = "No expense head selected for {$fileName}; file moved but not attached.";
                } else {
                    $headToFilesMap[$head][] = $fileName;
                }
                $relocated[] = ['file_name' => $fileName, 'file_url' => $url, 'target' => $head];
            } else {
                $sequence = (int) $target;
                if (!in_array($sequence, $validSequences, true)) {
                    $errors[] = "Invalid upload category for {$fileName}; file moved but not attached.";
                } else {
                    $billUploadRows[] = ['FileName' => $fileName, 'UploadSequence' => $sequence];
                }
                $relocated[] = ['file_name' => $fileName, 'file_url' => $url, 'target' => $target];
            }
        }

        if (!empty($errors) && empty($relocated) && !empty($existingFileNames)) {
            return $this->jsonError(implode('; ', $errors), 500);
        }

        DB::connection($connName)->transaction(function () use (
            $connName, $claimsTable, $claim, $expId, $newClaimId, $oldCgId, $newCgId, $yearId,
            $headToFilesMap, $headToAmountsMap, $billUploadRows, $existingFileNames, $oldG7
        ) {
            $claimUpdate = ['ClaimId' => $newClaimId];

            if ($oldCgId == 7 && $newCgId == 7) {
                // Preserve amounts and details for Activity -> Activity switch
                $claimUpdate['FilledTAmt']   = $claim->FilledTAmt ?? 0;
                $claimUpdate['VerifyTAmt']   = $claim->VerifyTAmt ?? 0;
                $claimUpdate['ApprTAmt']     = $claim->ApprTAmt ?? 0;
                $claimUpdate['FinancedTAmt'] = $claim->FinancedTAmt ?? 0;
                $claimUpdate['activity_type'] = $claim->activity_type ?? null;
                $claimUpdate['traill_no']    = $claim->traill_no ?? null;
                $claimUpdate['BillDate']     = $claim->BillDate ?? null;
            } elseif ($oldCgId == 1 && $newCgId == 7) {
                // Preserve BillDate and FilledTAmt when moving from Bill to Activity
                $claimUpdate['FilledTAmt']   = $claim->FilledTAmt ?? 0;
                $claimUpdate['VerifyTAmt']   = $claim->VerifyTAmt ?? 0;
                $claimUpdate['ApprTAmt']     = $claim->ApprTAmt ?? 0;
                $claimUpdate['FinancedTAmt'] = $claim->FinancedTAmt ?? 0;
                $claimUpdate['BillDate']     = $claim->BillDate ?? null;
            } elseif ($oldCgId == 7 && $newCgId == 1) {
                // Preserve BillDate and FilledTAmt when moving from Activity to Bill
                $claimUpdate['FilledTAmt']   = $claim->FilledTAmt ?? 0;
                $claimUpdate['VerifyTAmt']   = $claim->VerifyTAmt ?? 0;
                $claimUpdate['ApprTAmt']     = $claim->ApprTAmt ?? 0;
                $claimUpdate['FinancedTAmt'] = $claim->FinancedTAmt ?? 0;
                $claimUpdate['BillDate']     = $claim->BillDate ?? null;
            } else {
                $claimUpdate['FilledTAmt']   = 0;
                $claimUpdate['VerifyTAmt']   = 0;
                $claimUpdate['ApprTAmt']     = 0;
                $claimUpdate['FinancedTAmt'] = 0;
            }

            // Clean up old sub-table if leaving Bill type
            if ($oldCgId == 1 && $newCgId != 1) {
                DB::connection($connName)->table(G1ExpenseFillData::tableName($yearId))->where('ExpId', $expId)->delete();
                if (!empty($existingFileNames)) {
                    DB::connection($connName)->table("y{$yearId}_claimuploads")
                        ->where('ExpId', $expId)
                        ->whereIn('FileName', $existingFileNames)
                        ->delete();
                }
            }

            if ($newCgId == 7) {
                $g7Update = ['ExpId' => $expId, 'updated_at' => now(), 'created_at' => now()];

                // Copy over activity details and existing amounts if old claim was also Activity
                if ($oldG7) {
                    if (!empty($oldG7->crop_list)) {
                        $g7Update['crop_list'] = $oldG7->crop_list;
                    }
                    if (!empty($oldG7->variety_list)) {
                        $g7Update['variety_list'] = $oldG7->variety_list;
                    }

                    // Copy over head amounts and remarks from old G7
                    foreach ((array) $oldG7 as $col => $val) {
                        if (!in_array($col, ['id', 'ExpId', 'crop_list', 'variety_list', 'created_at', 'updated_at']) && !str_starts_with($col, 'file_') && $val !== null) {
                            $g7Update[$col] = $val;
                        }
                    }
                }

                // If amounts were mapped for heads from the modal, apply them directly!
                if (!empty($headToAmountsMap)) {
                    foreach ($headToAmountsMap as $hSlug => $amt) {
                        $g7Update[$hSlug] = $amt;
                    }
                }

                // Attach relocated files to assigned heads
                foreach ($headToFilesMap as $col => $fNames) {
                    $g7Update[$col] = json_encode(array_values(array_unique($fNames)));
                }

                // If moving from Bill to Activity and no head amounts yet, place FilledTAmt in the first head
                if ($oldCgId == 1 && ($claim->FilledTAmt ?? 0) > 0 && !empty($headToFilesMap) && empty($headToAmountsMap)) {
                    $firstFileHead = array_key_first($headToFilesMap);
                    $firstHeadSlug = preg_replace('/^file_/', '', $firstFileHead);
                    if ($firstHeadSlug && !isset($g7Update[$firstHeadSlug])) {
                        $g7Update[$firstHeadSlug] = $claim->FilledTAmt;
                    }
                }

                // Recalculate total amount from G7 if head amounts are set
                $totalHeadAmt = 0;
                foreach ($g7Update as $k => $v) {
                    if (!in_array($k, ['id', 'ExpId', 'crop_list', 'variety_list', 'created_at', 'updated_at']) && !str_starts_with($k, 'file_') && !str_starts_with($k, 'ver_') && !str_starts_with($k, 'app_') && !str_starts_with($k, 'pay_') && is_numeric($v)) {
                        $totalHeadAmt += floatval($v);
                    }
                }
                if ($totalHeadAmt > 0) {
                    $claimUpdate['FilledTAmt'] = $totalHeadAmt;
                }

                // Delete old G7 row before re-inserting updated values
                if ($oldCgId == 7) {
                    DB::connection($connName)->table("y{$yearId}_g7_expensefilldata")->where('ExpId', $expId)->delete();
                }

                DB::connection($connName)->table("y{$yearId}_g7_expensefilldata")
                    ->updateOrInsert(['ExpId' => $expId], $g7Update);
            } elseif ($newCgId != 7 && !empty($billUploadRows)) {
                if ($oldCgId == 7) {
                    DB::connection($connName)->table("y{$yearId}_g7_expensefilldata")->where('ExpId', $expId)->delete();
                }
                $uploadsTable = "y{$yearId}_claimuploads";
                foreach ($billUploadRows as $row) {
                    DB::connection($connName)->table($uploadsTable)->insert([
                        'ExpId'          => $expId,
                        'FileName'       => $row['FileName'],
                        'UploadSequence' => $row['UploadSequence'],
                        'created_at'     => now(),
                    ]);
                }
            }

            DB::connection($connName)->table($claimsTable)->where('ExpId', $expId)->update($claimUpdate);

            // Tracker #28: same audit trail as the bulk change, when the log table exists.
            if (Schema::connection($connName)->hasTable('claim_head_change_logs')) {
                $this->logHeadChange($connName, (int) $yearId, $claim, $newClaimId, 'single', null, auth()->user());
            }
        });

        return $this->jsonSuccess([
            'new_claim_id' => $newClaimId,
            'relocated'    => $relocated,
            'errors'       => $errors,
        ], empty($errors)
            ? 'Claim head changed and all details transferred successfully.'
            : 'Claim head changed, but some files had warnings.'
        );
    }

    /**
     * Tracker #28: change the head (ClaimId) of many bill claims at once.
     *
     * Bill-to-bill only (claim group 1, excluding odometer and DA heads):
     * those keep their files in the flat S3 path and claimuploads, so nothing
     * has to move. Activity changes still go through changeClaimHead() one at
     * a time, because each file must be mapped to an expense head. All claims
     * are checked first and changed in one transaction (all or nothing), with
     * one claim_head_change_logs row per claim (old head, new head, actor).
     */
    public function bulkChangeClaimHead(Request $request)
    {
        $request->validate([
            'expids'       => 'required|array|min:1|max:500',
            'expids.*'     => 'integer',
            'new_claim_id' => 'required|integer',
            'remark'       => 'nullable|string|max:500',
        ]);

        $yearId      = (int) session('year_id');
        $connName    = session('expense_connection');
        $claimsTable = ExpenseClaim::tableName($yearId);
        $newClaimId  = (int) $request->input('new_claim_id');
        $expIds      = array_values(array_unique(array_map('intval', $request->input('expids'))));
        $excluded    = [7, 19, 20]; // odometer and DA heads have their own data model

        if (! Schema::connection($connName)->hasTable('claim_head_change_logs')) {
            return $this->jsonError('Claim head change log is not set up on this server (migration 2026_11_05_000005 pending). No change was made.', 503);
        }

        $target = DB::connection($connName)->table('claimtype')->where('ClaimId', $newClaimId)->first(['ClaimId', 'ClaimName', 'cgId', 'ClaimStatus']);
        if (! $target || (int) $target->cgId !== 1 || $target->ClaimStatus !== 'A' || in_array($newClaimId, $excluded, true)) {
            return $this->jsonError('Choose an active bill claim head (not odometer or DA) as the new head.', 422);
        }

        $claims = DB::connection($connName)->table($claimsTable)->whereIn('ExpId', $expIds)->get(['ExpId', 'ClaimId', 'ClaimStatus', 'CrBy']);
        $problems = [];
        if ($claims->count() !== count($expIds)) {
            $problems[] = 'not found: '.implode(', ', array_diff($expIds, $claims->pluck('ExpId')->map(fn ($id) => (int) $id)->all()));
        }
        foreach ($claims as $claim) {
            if (! in_array($claim->ClaimStatus, self::HEAD_CHANGE_ALLOWED_STATUSES, true)) {
                $problems[] = "#{$claim->ExpId} is {$claim->ClaimStatus}";
            } elseif ((int) $claim->ClaimId === $newClaimId) {
                $problems[] = "#{$claim->ExpId} already has this head";
            } elseif (in_array((int) $claim->ClaimId, $excluded, true) || (int) ClaimType::getCgIdByClaimId($claim->ClaimId) !== 1) {
                $problems[] = "#{$claim->ExpId} is not a bill claim (change it from the claim screen)";
            }
        }
        if (! empty($problems)) {
            return $this->jsonError('No claim was changed. '.implode('; ', $problems).'.', 422);
        }

        $batchId = (string) \Illuminate\Support\Str::uuid();
        $actor   = auth()->user();
        DB::connection($connName)->transaction(function () use ($connName, $claimsTable, $claims, $newClaimId, $yearId, $batchId, $actor, $request) {
            foreach ($claims as $claim) {
                DB::connection($connName)->table($claimsTable)->where('ExpId', $claim->ExpId)->update(['ClaimId' => $newClaimId]);
                $this->logHeadChange($connName, $yearId, $claim, $newClaimId, 'bulk', $batchId, $actor, $request->input('remark'));
            }
        });

        return $this->jsonSuccess(['changed' => $claims->count(), 'batch_id' => $batchId],
            "Head changed to {$target->ClaimName} for {$claims->count()} claim(s).");
    }

    /** One audit row in claim_head_change_logs (Tracker #28; also written by changeClaimHead()). */
    private function logHeadChange(string $connName, int $yearId, object $claim, int $newClaimId, string $mode, ?string $batchId, $actor, ?string $remark = null): void
    {
        DB::connection($connName)->table('claim_head_change_logs')->insert([
            'batch_id'        => $batchId,
            'company_id'      => session('company_id'),
            'year_id'         => $yearId,
            'exp_id'          => $claim->ExpId,
            'employee_id'     => $claim->CrBy ?? null,
            'old_claim_id'    => (int) $claim->ClaimId,
            'new_claim_id'    => $newClaimId,
            'claim_status'    => $claim->ClaimStatus ?? null,
            'mode'            => $mode,
            'changed_by'      => $actor?->id,
            'changed_by_name' => $actor?->name ?? session('emp_name'),
            'remark'          => $remark,
            'created_at'      => now(),
        ]);
    }

    /**
     * Flatten buildUploadedFilesList()'s two possible shapes (flat array for
     * Bill claims, column-keyed groups for Activity claims) into a plain list
     * of filenames.
     */
    private function flattenUploadedFileNames(array $uploadedFiles): array
    {
        return array_keys($this->flattenUploadedFilesWithPaths($uploadedFiles));
    }

    /**
     * Same flattening as flattenUploadedFileNames(), but keyed by filename
     * with the REAL S3 path buildUploadedFilesList() actually found it at
     * (via candidateFilePaths()/findExistingS3Path()) as the value — or null
     * if it couldn't be located under any known convention. This is the
     * authoritative source changeClaimHead() uses to relocate a file, rather
     * than re-guessing a single path that may not match reality (mobile
     * uploads, legacy paths, etc.).
     */
    private function flattenUploadedFilesWithPaths(array $uploadedFiles): array
    {
        $map = [];
        foreach ($uploadedFiles as $entry) {
            if (isset($entry['files']) && is_array($entry['files'])) {
                foreach ($entry['files'] as $f) {
                    if (!empty($f['file_path'])) {
                        $map[$f['file_path']] = $f['s3_path'] ?? null;
                    }
                }
            } elseif (!empty($entry['file_path'])) {
                $map[$entry['file_path']] = $entry['s3_path'] ?? null;
            }
        }
        return $map;
    }

    public function calculateRateAjax(Request $request)    {
        $request->validate(['employee_id' => 'required|integer', 'bill_date' => 'required|date', 'vehicle_type' => 'required|in:2,4']);
        $employeeId = $request->input('employee_id');
        $billDate = $request->input('bill_date');
        $vehicleType = (int) $request->input('vehicle_type');
        $rate = $this->getRatePerKM($employeeId, $billDate, $vehicleType);
        $response = ['rate' => $rate, 'formatted_rate' => number_format($rate, 2), 'vehicle_type' => $vehicleType, 'vehicle_type_label' => $vehicleType == self::VEHICLE_TYPE_TWO_WHEELER ? '2W' : '4W'];
        return $this->jsonSuccess($response, 'Rate calculated successfully');
    }

    public function getActiveClaimTypes()
    {
        $user = auth()->user();
        $expenseConnection = DatabaseSwitcher::getExpenseConnection();
        if ($user->hasRole('Super Admin')) {
            return $this->getAllClaimTypes();
        }
        $allowedClaimIds = DB::connection('expense')->table('tbl_user_claim_types')->where('user_id', $user->id)->pluck('claim_type_id')->toArray();
        if (empty($allowedClaimIds)) {
            return $this->jsonSuccess([], 'No claim access assigned.');
        }
        return $this->getClaimTypesFiltered($allowedClaimIds);
    }

    private function getAllClaimTypes()
    {
        $expenseConnection = DatabaseSwitcher::getExpenseConnection();
        $claimTypes = DB::connection(session('expense_connection'))->table('claimtype as ct')->leftJoin('claimgroup as cg', 'cg.cgId', '=', 'ct.cgId')->whereIn('ct.cgId', [1, 2, 4, 7])->orderBy('cg.cgName')->orderBy('ct.ClaimName')->get(['ct.ClaimId', 'ct.ClaimName', 'ct.cgId', 'cg.cgName']);
        return $this->formatClaimTypes($claimTypes);
    }
    // private function getAllClaimTypes()
    // {
    //     $expenseConnection = DatabaseSwitcher::getExpenseConnection();
    //     $claimTypes = DB::connection(session('expense_connection'))->table('claimtype as ct')->leftJoin('claimgroup as cg', 'cg.cgId', '=', 'ct.cgId')->whereIn('ct.ClaimStatus', ['A', 'B'])->whereIn('ct.cgId', [1, 2, 4, 7])->orderBy('cg.cgName')->orderBy('ct.ClaimName')->get(['ct.ClaimId', 'ct.ClaimName', 'cg.cgName']);
    //     return $this->formatClaimTypes($claimTypes);
    // }

    private function getClaimTypesFiltered(array $allowedClaimIds)
    {
        $expenseConnection = DatabaseSwitcher::getExpenseConnection();
        $claimTypes = DB::connection(session('expense_connection'))->table('claimtype as ct')->leftJoin('claimgroup as cg', 'cg.cgId', '=', 'ct.cgId')->whereIn('ct.ClaimId', $allowedClaimIds)->whereIn('ct.ClaimStatus', ['A', 'B'])->whereIn('ct.cgId', [1, 2, 4, 7])->orderBy('cg.cgName')->orderBy('ct.ClaimName')->get(['ct.ClaimId', 'ct.ClaimName', 'ct.cgId', 'cg.cgName']);
        return $this->formatClaimTypes($claimTypes);
    }

    private function formatClaimTypes($claimTypes)
    {
        $grouped = [];
        foreach ($claimTypes as $claim) {
            $grouped[$claim->cgName][] = [
                'id' => $claim->ClaimId,
                'text' => $claim->ClaimName,
                'cgid' => $claim->cgId,
            ];
        }
        $result = [];
        foreach ($grouped as $group => $claims) {
            $result[] = ['text' => $group, 'children' => $claims];
        }
        return $this->jsonSuccess($result, 'Claim type fetched successfully.');
    }

    public function getVarieties(Request $request)
    {
        $cropIds = $request->input('crop_ids', []);
        if (empty($cropIds)) {
            return response()->json([]);
        }
        $varieties = CoreVariety::where('is_active', 1)->whereIn('crop_id', $cropIds)->orderBy('variety_name')->get(['id', 'variety_name']);
        return response()->json($varieties);
    }

    /**
     * APPR-RO-01 (13 Nov 2026): punched data and claim documents are changed only by the data entry, verification
     * or payment teams (or Super Admin), or by the claimant on their own claim. A reporting manager (or anyone
     * else) only views them. Returns an error response, or null when allowed.
     */
    private function refuseClaimEdit($expId)
    {
        $user = auth()->user();
        if (! $user) {
            return $this->jsonError('Please log in again.', 401);
        }
        if ((method_exists($user, 'hasAnyRole') && $user->hasAnyRole(['Super Admin', 'Admin']))
            || collect(['section_data_punching', 'section_verifier', 'verifier_verifier_screen', 'payment_section', 'finance'])->contains(fn ($p) => $user->can($p))) {
            return null;
        }
        $owner = \App\Support\RecordAccess::claimOwner((int) $expId, (int) session('year_id'), (string) session('expense_connection'));
        if ($owner !== null && (int) $owner === (int) \App\Support\RecordAccess::selfId($user)) {
            return null;
        }
        \Illuminate\Support\Facades\Log::warning('APPR-RO-01: claim edit refused', ['user' => $user->id, 'exp_id' => $expId, 'path' => request()->path()]);

        return $this->jsonError('You can view this claim but not change it. Changes are made by the data entry or verification team.', 403);
    }

    public function uploadClaimFiles(Request $request)
    {
        if ($refused = $this->refuseClaimEdit($request->input('expid'))) {
            return $refused;
        }
        $yearId = session('year_id');
        // RET-01: no punching in an archived (read-only) year
        if (\App\Services\Retention\RecordRetention::isArchived((string) session('expense_connection'), (int) $yearId)) {
            return $this->jsonError('This financial year is archived (read-only). Archived records are kept unchanged for the 8-year retention period.', 423);
        }
        $expenseConnection = DatabaseSwitcher::getExpenseConnection();
        $claimId = $request->input('claimid');
        $expId = $request->input('expid');
        $cgId = $request->input('cgid');
        $empId = $request->input('empid');
        $totalAmount = $request->input('totalAmount');
        $isValidateDuplicate = filter_var($request->input('is_validate_duplicate', false), FILTER_VALIDATE_BOOLEAN);
        $totalVerified = $request->input('totalVerified');
        $totalApproved = $request->input('totalApproved') ?? $totalVerified;
        $totalFinance = $request->input('totalFinance') ?? $totalVerified;
        $formDataArray = $this->prepareFormData($request->input('formData'));

        // Duplicate punch check: same employee, same ClaimId, same BillDate, different ExpId
        if ($cgId == 1) {
            $billDateRaw = $formDataArray['bill_date'] ?? $formDataArray['booking_date'] ?? $formDataArray['journey_date'] ?? $formDataArray['date_of_travel'] ?? $formDataArray['travel_date'] ?? null;
            $billDateForCheck = CommonHelper::toDatabaseFormat($billDateRaw);

            if ($billDateForCheck && $empId && $claimId && $isValidateDuplicate) {
                $expenseClaimTable = ExpenseClaim::tableName($yearId);
                $duplicate = DB::connection(session('expense_connection'))
                    ->table($expenseClaimTable)
                    ->where('CrBy', $empId)
                    ->where('ClaimId', $claimId)
                    ->whereDate('BillDate', $billDateForCheck)
                    ->where('FilledTAmt', $totalAmount)
                    ->where('ExpId', '!=', $expId)
                    ->where('ClaimStatus', '!=', 'Deactivate')
                    ->first();

                if ($duplicate) {
                    return $this->jsonError(
                        "Duplicate punch: A claim of the same type already exists for this employee on {$billDateForCheck} (ExpId: {$duplicate->ExpId})."
                    );
                }
            }
        }

        // Tracker rules on the punch / verifier save (ticket category, participants vs PAX).
        $ruleError = ClaimSubmissionValidator::first(app(ClaimSubmissionValidator::class)->validatePunch((int) $cgId, (int) $claimId, $formDataArray));
        if ($ruleError !== null) {
            return $this->jsonError($ruleError, 422); // validation, not a server fault (claim.js shows message either way)
        }

        // Q-02: on the punch / verifier save, verified may not exceed claimed and approved may not
        // exceed verified without a reason in the remark (the claimed amount stays frozen, FREEZE-01).
        if ($expId) {
            $capExisting = DB::connection(session('expense_connection'))->table(ExpenseClaim::tableName($yearId))->where('ExpId', $expId)->first(['ExpId', 'CrBy', 'FilledTAmt', 'VerifyTAmt', 'ApprTAmt']);
            $capClaimed = $this->filledAmountFrozen($capExisting) ? (float) $capExisting->FilledTAmt : (float) $totalAmount;
            $capRemark = (string) $request->input('verify_remark', $request->input('VerifyTRemark', $request->input('remark', '')));
            $capRow = (object) ['ExpId' => (int) $expId, 'CrBy' => (int) $empId, 'FilledTAmt' => $capClaimed, 'VerifyTAmt' => (float) $totalVerified];
            $capMsg = (float) $totalVerified > 0 ? \App\Services\Claims\StageCap::check(session('expense_connection'), (int) $yearId, $capRow, $totalVerified, 'verify', $capRemark, 'punch_save') : null;
            if (! $capMsg && (float) $totalApproved > 0 && $request->filled('totalApproved')) {
                $capMsg = \App\Services\Claims\StageCap::check(session('expense_connection'), (int) $yearId, $capRow, $totalApproved, 'approve', $capRemark, 'punch_save');
            }
            // PAY-REMARK-01: an approved amount below the verified one needs the reason (VNR)
            if (! $capMsg && $request->filled('totalApproved') && (float) $totalApproved > 0 && abs((float) $totalApproved - (float) ($capExisting->ApprTAmt ?? 0)) > 1) {
                $apprRemark = (string) $request->input('approve_remark', $request->input('ApprTRemark', $capRemark));
                $capMsg = \App\Services\Claims\StageCap::checkCut(session('expense_connection'), (int) $yearId, $capRow, $totalApproved, $apprRemark, 'web_approve', (int) session('company_id', 1));
            }
            if ($capMsg) {
                return $this->jsonError($capMsg, 422);
            }
        }

        // Lodging cap by city tier and annual km cap by grade (HR policy), override with a reason.
        $eligMsg = app(\App\Services\ClaimRules\EligibilityCaps::class)->check(
            (string) session('expense_connection'), (int) $yearId, (int) $cgId, (int) $claimId, (int) $expId, (int) $empId,
            $formDataArray, $totalAmount,
            (string) $request->input('override_reason', $request->input('verify_remark', $request->input('VerifyTRemark', $request->input('remark', ''))))
        );
        if ($eligMsg) {
            return $this->jsonError($eligMsg, 422);
        }

        // PUNCH-TRACK-01: what this punch changes is logged after the save
        $punchBefore = \App\Services\Claims\PunchChangeTrack::snapshot((string) session('expense_connection'), (int) $yearId, (int) $cgId, (int) $expId);

        // UAT R-01: activity files stored by uploadFiles() during this save are deleted again
        // if the save fails (uploadFiles() throws when a file cannot be stored).
        $this->punchFiles = new \App\Services\ClaimFiles\ClaimFileBatch();
        try {
            DB::connection(session('expense_connection'))->transaction(function () use ($cgId, $claimId, $expId, $empId, $yearId, $formDataArray, $request, $totalAmount, $totalVerified, $totalApproved, $totalFinance, $expenseConnection) {
                $expenseClaimTable = ExpenseClaim::tableName($yearId);
                if ($cgId == 7) {
                    $billDate = CommonHelper::toDatabaseFormat($formDataArray['bill_date'] ?? null);
                    $expensesData = $this->parseJsonInput($request->input('expensesData'));
                    $expenseHeads = $this->getExpenseHeads($claimId);
                    $g7Data = $this->processExpensesData($expensesData, $expenseHeads, $request, $claimId, $yearId, $empId);

                    $existingClaim = DB::connection(session('expense_connection'))->table($expenseClaimTable)->where('ExpId', $expId)->first();

                    $companyId = (int) ($request->input('company_id') ?? session('company_id', 1));
                    $expenseConn = (string) (session('expense_connection') ?? config('database.default'));
                    $isVVNR = ($companyId === 11 || str_contains($expenseConn, 'vvnr'));

                    $resolvedVerifyTAmt = $totalVerified;
                    if (!$isVVNR) {
                        $isVerifyEmptyOrZero = !$existingClaim
                            || empty($existingClaim->VerifyDate)
                            || \App\Support\ZeroDate::none($existingClaim->VerifyDate)
                            || str_starts_with((string)$existingClaim->VerifyDate, '0000-00-00')
                            || empty($existingClaim->VerifyBy)
                            || empty($existingClaim->VerifyTAmt)
                            || (float)$existingClaim->VerifyTAmt <= 0;

                        if ($isVerifyEmptyOrZero || empty($totalVerified) || (float)$totalVerified <= 0) {
                            $resolvedVerifyTAmt = (!empty($totalVerified) && (float)$totalVerified > 0) ? $totalVerified : $totalAmount;
                        }
                    }

                    $mainData = [
                        'BillDate' => $billDate,
                        'traill_no' => $formDataArray['trial_no'] ?? null,
                        'ClaimId' => $claimId,
                        'activity_type' => $formDataArray['activity_type'] ?? null,
                        'FilledTAmt' => $this->filledAmountFrozen($existingClaim) ? $existingClaim->FilledTAmt : $totalAmount, // FREEZE-01
                        'VerifyTAmt' => $this->filledAmountFrozen($existingClaim) && (empty($totalVerified) || (float) $totalVerified <= 0) ? $totalAmount : $resolvedVerifyTAmt,
                        'ApprTAmt' => $totalApproved,
                        'FinancedTAmt' => $totalFinance,
                    ];
                    DB::connection(session('expense_connection'))->table($expenseClaimTable)->updateOrInsert(['ExpId' => $expId], $mainData);
                    $g7Data = array_merge($g7Data, [
                        'ExpId' => $expId,
                        'crop_list' => $this->implodeArray($formDataArray['crops'] ?? ''),
                        'variety_list' => $this->implodeArray($formDataArray['variety'] ?? ''),
                        'updated_at' => now(),
                        'created_at' => now(),
                    ]);
                    $g7ExpenseTable = "y{$yearId}_g7_expensefilldata";
                    DB::connection(session('expense_connection'))->table($g7ExpenseTable)->updateOrInsert(['ExpId' => $expId], $g7Data);
                    $this->logExpenseUpdate($expId, $cgId, $empId, $expenseClaimTable, $g7ExpenseTable, 'g7_data');
                } elseif ($cgId == 1) {
                    $g1ExpenseTable = G1ExpenseFillData::tableName($yearId);
                    try {
                        $emp = DB::connection(session('hrims_connection'))
                            ->table('hrm_employee')
                            ->where('EmployeeID', $empId)
                            ->select('EmployeeID', 'EmpCode', 'Fname', 'Sname', 'Lname')
                            ->first();

                        if ($emp) {
                            $emp->full_name = trim(
                                ($emp->Fname ?? '') . ' '
                                . ($emp->Sname ?? '') . ' '
                                . ($emp->Lname ?? '')
                            );
                        }
                    } catch (\Illuminate\Database\QueryException $e) {
                        if (str_contains($e->getMessage(), "doesn't exist") || str_contains($e->getMessage(), 'Table')) {
                            Log::warning('HRIMS table not found for company ' . session('company_id', 'unknown') . ': ' . $e->getMessage());
                            return $this->jsonError('Employee data not available for this company');
                        }
                        throw $e;
                    }
                    $travelData = [];
                    $mainClaimData = [];
                    $bookingDate = null;
                    switch ($claimId) {
                        case 1:
                            // CITY-01: the tier always comes from the company's city master
                            // (policy city list), never from the tier typed or left in the form.
                            $cityId = $formDataArray['city_category_select'] ?? null;
                            $cityName = null;
                            $cityCategory = null;
                            $cityMaster = DB::connection(session('expense_connection'))->table('city_category');

                            if ($cityId === '0' || $cityId === 0) {
                                $cityName = trim((string) ($formDataArray['other_city_name'] ?? '')) ?: null;
                                if ($cityName) {
                                    $existingCity = (clone $cityMaster)
                                        ->whereRaw('LOWER(TRIM(city_name)) = ?', [mb_strtolower($cityName)])
                                        ->orderBy('ccid')
                                        ->first(['ccid', 'city_name', 'city_category']);
                                    if ($existingCity) {
                                        $cityName = trim($existingCity->city_name);
                                        $cityCategory = $existingCity->city_category;
                                    } else {
                                        // A city not in the policy list is a C city (Travel Policy, city table).
                                        (clone $cityMaster)->insert([
                                            'city_name' => mb_substr($cityName, 0, 30),
                                            'city_category' => 'C',
                                            'crby' => (int) (session('employee_id') ?? 0),
                                        ]);
                                        $cityCategory = 'C';
                                    }
                                }
                            } elseif (!empty($cityId)) {
                                $cityObj = (clone $cityMaster)->where('ccid', (int) $cityId)->first(['city_name', 'city_category']);
                                if ($cityObj) {
                                    $cityName = trim($cityObj->city_name);
                                    $cityCategory = $cityObj->city_category;
                                }
                            }
                            if ($cityName === null && !empty($cityId) && $cityId !== '0') {
                                throw new \RuntimeException('Selected city was not found in the city list. Please select the city again.');
                            }

                            $hotelId = $formDataArray['hotel_name_select'] ?? null;
                            $hotelName = null;

                            if ($hotelId === '0' || $hotelId === 0) {
                                $hotelName = $formDataArray['other_hotel_name'] ?? null;
                                // Auto-create hotel if name provided and not already exists
                                if ($hotelName) {
                                    $existingHotel = DB::connection(session('expense_connection'))
                                        ->table('hotel_list')
                                        ->whereRaw('LOWER(hotel_name) = ?', [strtolower($hotelName)])
                                        ->first();
                                    if (!$existingHotel) {
                                        DB::connection(session('expense_connection'))
                                            ->table('hotel_list')
                                            ->insert([
                                                'hotel_name' => $hotelName,
                                                'hotel_address' => $formDataArray['hotel_address'] ?? null,
                                                'HotelContact' => $formDataArray['hotel_contact'] ?? null,
                                                'HotelEmail' => $formDataArray['hotel_email'] ?? null,
                                                'crby' => Auth::id(),
                                                'crdate' => now()->format('Y-m-d'),
                                            ]);
                                    }
                                }
                            } else {
                                $hotelName = $formDataArray['hotel_name'] ?? null;
                            }

                            $paxDetailsRaw = $formDataArray['lodging_participants'] ?? $formDataArray['pax_details'] ?? [];
                            if (is_string($paxDetailsRaw) && str_starts_with(trim($paxDetailsRaw), '[')) {
                                $decoded = json_decode($paxDetailsRaw, true);
                                if (is_array($decoded)) {
                                    $paxDetailsRaw = $decoded;
                                }
                            }
                            $paxDetailsList = is_array($paxDetailsRaw) ? $paxDetailsRaw : explode(',', (string)$paxDetailsRaw);
                            $paxDetailsList = array_values(array_unique(array_filter(array_map('trim', $paxDetailsList))));
                            $paxDetailsString = implode(',', $paxDetailsList);

                            $paxDetailsOtherRaw = $formDataArray['lodging_other_participants'] ?? $formDataArray['pax_details_other'] ?? null;
                            $paxDetailsOther = CommonHelper::normalizeOtherParticipantsJson($paxDetailsOtherRaw);
                            $parsedOther = CommonHelper::parseOtherParticipants($paxDetailsOther);
                            $paxCount = $formDataArray['PaxDetails'] ?? $formDataArray['pax_count'] ?? (count($paxDetailsList) + count($parsedOther));

                            // Safeguard: Fetch existing fill data so missing inputs (e.g. Verifier view) don't wipe existing participants
                            $existingFill = DB::connection(session('expense_connection'))
                                ->table($g1ExpenseTable)
                                ->where('ExpId', $expId)
                                ->first(['PaxDetails', 'PaxDetails_Other', 'NoOfPAX']);

                            $hasExplicitPaxInInput = isset($formDataArray['lodging_participants']) || (isset($formDataArray['pax_details']) && !empty($formDataArray['pax_details']));
                            if (!$hasExplicitPaxInInput && empty($paxDetailsList) && $existingFill && !empty($existingFill->PaxDetails)) {
                                $paxDetailsString = $existingFill->PaxDetails;
                                $paxDetailsList = array_values(array_unique(array_filter(array_map('trim', explode(',', $paxDetailsString)))));
                            }

                            $hasExplicitOtherInInput = isset($formDataArray['lodging_other_participants']) || isset($formDataArray['pax_details_other']);
                            if (!$hasExplicitOtherInInput && empty($paxDetailsOther) && $existingFill && !empty($existingFill->PaxDetails_Other)) {
                                $paxDetailsOther = $existingFill->PaxDetails_Other;
                                $parsedOther = CommonHelper::parseOtherParticipants($paxDetailsOther);
                            }

                            $hasExplicitCountInInput = isset($formDataArray['PaxDetails']) || isset($formDataArray['pax_count']);
                            if (!$hasExplicitCountInInput && empty($paxCount) && $existingFill && !empty($existingFill->NoOfPAX)) {
                                $paxCount = $existingFill->NoOfPAX;
                            } elseif (empty($paxCount)) {
                                $paxCount = count($paxDetailsList) + count($parsedOther);
                            }

                            $arrival = !empty($formDataArray['arrival_datetime'])
                                ? CommonHelper::toDatabaseFormat($formDataArray['arrival_datetime'])
                                : null;
                            $departure = !empty($formDataArray['departure_datetime'])
                                ? CommonHelper::toDatabaseFormat($formDataArray['departure_datetime'])
                                : null;

                            $travelData = [
                                'BillingName' => $formDataArray['billing_person'] ?? null,
                                'BillingAddress' => $formDataArray['billing_address'] ?? null,
                                'CityName' => $cityName,
                                'CityCategory' => $cityCategory,
                                'HotelName' => $hotelName,
                                'HotelContact' => $formDataArray['hotel_contact'] ?? null,
                                'HotelEmail' => $formDataArray['hotel_email'] ?? null,
                                'HotelAddress' => $formDataArray['hotel_address'] ?? null,
                                'Plan' => $formDataArray['meal_plan'] ?? null,
                                'BillDate' => CommonHelper::toDatabaseFormat($formDataArray['bill_date'] ?? null),
                                'BillNo' => $formDataArray['bill_no'] ?? null,
                                'Arrival' => $arrival,
                                'Departure' => $departure,
                                'StayDuration' => $formDataArray['stay_duration'] ?? null,
                                'GST' => $formDataArray['gst_rate'] ?? null,
                                // GST-01: vendor GSTIN and its offline check result
                                ...(array_key_exists('gstin', $formDataArray) && \Illuminate\Support\Facades\Schema::connection(session('expense_connection'))->hasColumn($g1ExpenseTable, 'GSTIN')
                                    ? (function ($v) { $c = \App\Support\Gstin::check($v); return ['GSTIN' => $c['gstin'] ?: null, 'GSTIN_Status' => $c['status']]; })($formDataArray['gstin'])
                                    : []),
                                'BillingInstruction' => $formDataArray['billing_instruction'] ?? null,
                                'NoOfPAX' => $paxCount,
                                'PaxDetails' => $paxDetailsString,
                                'PaxDetails_Other' => $paxDetailsOther,
                                'Mode' => $formDataArray['payment_mode'] ?? null,
                            ];

                            $bookingDate = CommonHelper::toDatabaseFormat($formDataArray['bill_date'] ?? null);
                            break;
                        case 2:
                            $journeyFrom = Airport::find($formDataArray['journey_from'] ?? null);
                            $journeyUpto = Airport::find($formDataArray['journey_upto'] ?? null);
                            $agent = VendorList::find($formDataArray['agent_name'] ?? null);
                            if (!$agent && !empty($formDataArray['other_agent_name'])) {
                                $agent = VendorList::create([
                                    'vendor_type' => 'agent',
                                    'vendor_name' => $formDataArray['other_agent_name'],
                                    'vendor_address' => null,
                                    'status' => '1',
                                    'request_status' => 'A',
                                    'created_by' => Auth::id(),
                                    'created_date' => now(),
                                ]);
                            }
                            $agentName = $agent->vendor_name ?? $formDataArray['other_agent_name'] ?? null;
                            $travelData = ['Agent' => $agentName, 'Vendor_Id' => $agent->id ?? $formDataArray['agent_name'] ?? null, 'PNRNo' => $formDataArray['pnr_number'] ?? null, 'TrainBusName' => $formDataArray['flight_number'] ?? null, 'Class' => $formDataArray['travel_class'] ?? null, 'BillDate' => CommonHelper::toDatabaseFormat($formDataArray['journey_date'] ?? null), 'BookingDate' => CommonHelper::toDatabaseFormat($formDataArray['booking_date'] ?? null), 'JourneyStartDt' => CommonHelper::toDatabaseFormat($formDataArray['journey_date'] ?? null), 'JourneyEndDt' => CommonHelper::toDatabaseFormat($formDataArray['journey_date'] ?? null), 'StayDuration' => $formDataArray['period_days'] ?? 0, 'JourneyFrom' => $journeyFrom?->city, 'Journey_From_Id' => $formDataArray['journey_from'] ?? null, 'JourneyUpto' => $journeyUpto?->city, 'Journey_Upto_Id' => $formDataArray['journey_upto'] ?? null, 'TravelInsurance' => $formDataArray['insurance_amount'] ?? 0, 'IsTravelInsurance' => $formDataArray['travel_insurance'] ?? '0', 'Mode' => 'Air', 'PassengerDetail' => $emp->full_name];
                            $bookingDate = CommonHelper::toDatabaseFormat($formDataArray['journey_date'] ?? null);
                            break;
                        case 3:
                            $travelData = [
                                'Mode' => $formDataArray['Mode'] ?? null,
                                'BillDate' => CommonHelper::toDatabaseFormat($formDataArray['booking_date'] ?? null),
                                'JourneyFrom' => $formDataArray['journey_from'] ?? null,
                                'Journey_From_Id' => null,
                                'JourneyUpto' => $formDataArray['journey_upto'] ?? null,
                                'Journey_Upto_Id' => null,
                            ];
                            $bookingDate = CommonHelper::toDatabaseFormat($formDataArray['booking_date'] ?? null);
                            break;
                        case 4:
                            $agency = null;
                            $agencyId = $formDataArray['agency_name'] ?? null;
                            if (!empty($agencyId)) {
                                $agency = VendorList::find($agencyId);
                            }
                            if (!$agency && !empty($formDataArray['other_agency_name'])) {
                                $agency = VendorList::create([
                                    'vendor_type' => 'Agency',
                                    'vendor_name' => $formDataArray['other_agency_name'],
                                    'vendor_address' => $formDataArray['agency_address'] ?? null,
                                    'contact_person' => $formDataArray['billing_person'] ?? null,
                                    'company_id' => Auth::user()->company_id ?? 0,
                                    'created_by' => Auth::id() ?? 0,
                                    'created_at' => now(),
                                    'updated_by' => Auth::id() ?? 0,
                                    'updated_at' => now(),
                                    'status' => '1',
                                    'request_status' => 'A',
                                ]);
                                $agencyId = $agency->id;
                            }
                            $agencyName = $agency->vendor_name ?? null;
                            $travelData = [
                                'AgencyName' => $agencyName,
                                'BillingName' => $formDataArray['billing_person'] ?? null,
                                'BillingAddress' => $formDataArray['billing_address'] ?? null,
                                'Vendor_Id' => $agencyId,
                                'AgencyAddress' => $formDataArray['agency_address'] ?? null,
                                'Invoice' => $formDataArray['invoice_no'] ?? null,
                                'BillDate' => !empty($formDataArray['date_of_travel']) ? CommonHelper::toDatabaseFormat($formDataArray['date_of_travel']) : null,
                                'VehicleReg' => $formDataArray['vehicle_class'] ?? null,
                                'DailyBasisCharges' => $formDataArray['charges_type'] ?? null,
                                'JourneyStartDt' => !empty($formDataArray['journey_start']) ? CommonHelper::toDatabaseFormat($formDataArray['journey_start']) : null,
                                'JourneyEndDt' => !empty($formDataArray['journey_end']) ? CommonHelper::toDatabaseFormat($formDataArray['journey_end']) : null,
                            ];
                            $bookingDate = $travelData['BillDate'] ?? null;
                            break;
                        case 7:
                            $isMultiDay = $formDataArray['multi_day_trip'] ?? 'no';
                            $vehicleType = $formDataArray['vehicle'] ?? null;
                            $vehicleReg = $formDataArray['vehicle_reg'] ?? null;
                            $odomOpen = $formDataArray['opening_reading'] ?? 0;
                            $odomClose = $formDataArray['closing_reading'] ?? 0;
                            $totalKm = $formDataArray['km_running'] ?? 0;
                            $rateApplied = $formDataArray['rate_per_km'] ?? 0;
                            // RKM-RATE-01 (decided 10 Nov 2026): readings may be corrected; km and the rate
                            // are worked out here (rate from the employee's eligibility on the trip date)
                            if (! config('claim_rules.running_km.manual_rate', false) && (float) $odomClose > (float) $odomOpen) {
                                $totalKm = (float) $odomClose - (float) $odomOpen;
                                $rateDate = CommonHelper::toDatabaseFormat($formDataArray['travel_date'] ?? null) ?: date('Y-m-d');
                                $rateEmp = (int) (DB::connection(session('expense_connection'))->table("y{$yearId}_expenseclaims")->where('ExpId', $expId)->value('CrBy') ?: $empId);
                                $sysRate = (float) $this->getRatePerKM($rateEmp, $rateDate, (int) $vehicleType);
                                if ($sysRate > 0) {
                                    $rateApplied = $sysRate;
                                    $totalAmount = round($totalKm * $sysRate, 2);
                                }
                            }
                            $tripStart = !empty($formDataArray['trip_started_on']) ? CommonHelper::toDatabaseFormat($formDataArray['trip_started_on']) : null;
                            $tripEnd = !empty($formDataArray['trip_ended_on']) ? CommonHelper::toDatabaseFormat($formDataArray['trip_ended_on']) : null;
                            $singleDate = CommonHelper::toDatabaseFormat($formDataArray['travel_date'] ?? null);
                            $finalStartDate = ($isMultiDay === 'yes') ? $tripStart : $singleDate;
                            $finalEndDate = ($isMultiDay === 'yes') ? $tripEnd : $singleDate;
                            $entryData = [
                                'did' => 0,
                                'ExpId' => $expId,
                                'VehicleReg' => $vehicleReg,
                                'JourneyStartDt' => $finalStartDate,
                                'JourneyEndDt' => $finalEndDate,
                                'DistTraOpen' => $odomOpen,
                                'DistTraClose' => $odomClose,
                                'Totalkm' => $totalKm,
                                'Rate_PerKm' => $rateApplied,
                                'FilledTAmt' => $totalAmount,
                            ];
                            DB::connection(session('expense_connection'))
                                ->table("y{$yearId}_24_wheeler_entry")
                                ->updateOrInsert(
                                    ['ExpId' => $expId],
                                    $entryData
                                );
                            $travelData = ['IsMultiDay' => $isMultiDay, 'VehicleType' => $vehicleType, 'BillDate' => $singleDate, 'VehicleReg' => $vehicleReg];
                            $mainClaimData = ['odomtr_opening' => $odomOpen, 'odomtr_closing' => $odomClose, 'TotKm' => $totalKm, 'WType' => $vehicleType, 'RatePerKM' => $rateApplied, 'FilledTAmt' => $totalAmount, 'BillDate' => $singleDate, 'ClaimId' => $claimId];
                            $bookingDate = $singleDate;
                            break;
                        case 6:
                            $travelData = [
                                'SenderName' => $formDataArray['sender_name'] ?? null,
                                'SenderAddress' => $formDataArray['sender_address'] ?? null,
                                'ServiceProvider' => $formDataArray['service_provider'] !== '0'
                                    ? ($formDataArray['service_provider'] ?? null)
                                    : ($formDataArray['other_cp_name'] ?? null),
                                'WeightCharged' => $formDataArray['weight_charged'] ?? null,
                                'DocketNumber' => $formDataArray['docket_number'] ?? null,
                                'DocketBookedDt' => CommonHelper::toDatabaseFormat($formDataArray['bill_date'] ?? null),
                                'ReceiverName' => $formDataArray['receiver_name'] ?? null,
                                'ReceiverAddress' => $formDataArray['receiver_address'] ?? null,
                                'JourneyFrom' => $formDataArray['source_city'] !== '0' ? $formDataArray['source_city'] : ($formDataArray['other_city_name'] ?? null),
                                'JourneyUpto' => $formDataArray['dest_city'] !== '0' ? $formDataArray['dest_city'] : ($formDataArray['other2_city_name'] ?? null),
                            ];
                            $bookingDate = CommonHelper::toDatabaseFormat($formDataArray['bill_date'] ?? null);
                            break;
                        case 8:
                            $travelData = ['BillingName' => $formDataArray['person'] ?? null, 'BillingAddress' => $formDataArray['person_address'] ?? null, 'BillDate' => CommonHelper::toDatabaseFormat($formDataArray['bill_date'] ?? null)];
                            $bookingDate = $travelData['BillDate'] ?? null;
                            break;
                        case 9:
                            $travelData = ['BillingName' => $formDataArray['person'] ?? null, 'BillingAddress' => $formDataArray['person_address'] ?? null, 'BillDate' => CommonHelper::toDatabaseFormat($formDataArray['bill_date'] ?? null)];
                            $bookingDate = $travelData['BillDate'] ?? null;
                            break;
                        case 14:
                            $journeyFrom = CityCategory::find($formDataArray['journey_from'] ?? null);
                            $journeyUpto = CityCategory::find($formDataArray['journey_upto'] ?? null);
                            $agent = VendorList::find($formDataArray['agent_name'] ?? null);
                            $travelData = [
                                'Agent' => $agent->vendor_name ?? null,
                                'Vendor_Id' => $formDataArray['agent_name'] ?? null,
                                'PNRNo' => $formDataArray['pnr_number'] ?? null,
                                'TrainBusName' => $formDataArray['flight_number'] ?? null,
                                'Class' => $formDataArray['travel_class'] ?? null,
                                'Quota' => app(ClaimSubmissionValidator::class)->normalizeTicketCategory($formDataArray['rail_quota'] ?? $formDataArray['quota'] ?? $formDataArray['ticket_category'] ?? null),
                                'BillDate' => CommonHelper::toDatabaseFormat($formDataArray['journey_date'] ?? null),
                                'BookingDate' => CommonHelper::toDatabaseFormat($formDataArray['booking_date'] ?? null),
                                'JourneyStartDt' => CommonHelper::toDatabaseFormat($formDataArray['journey_date'] ?? null),
                                'JourneyEndDt' => CommonHelper::toDatabaseFormat($formDataArray['journey_date'] ?? null),
                                'StayDuration' => $formDataArray['period_days'] ?? 0,
                                'JourneyFrom' => $journeyFrom?->city,
                                'Journey_From_Id' => $formDataArray['journey_from'] ?? null,
                                'JourneyUpto' => $journeyUpto?->city,
                                'Journey_Upto_Id' => $formDataArray['journey_upto'] ?? null,
                                'TravelInsurance' => $formDataArray['insurance_amount'] ?? 0,
                                'IsTravelInsurance' => $formDataArray['travel_insurance'] ?? '0',
                                'BookingStatus' => $formDataArray['BookingStatus'] ?? '',
                            ];
                            $bookingDate = CommonHelper::toDatabaseFormat($formDataArray['journey_date'] ?? null);
                            break;
                        case 15:
                            $journeyFrom = CityCategory::find($formDataArray['journey_from'] ?? null);
                            $journeyUpto = CityCategory::find($formDataArray['journey_upto'] ?? null);
                            $agent = VendorList::find($formDataArray['agent_name'] ?? null);
                            if (!$agent && !empty($formDataArray['other_agent_name'])) {
                                $agent = VendorList::create([
                                    'vendor_type' => 'agent',
                                    'vendor_name' => $formDataArray['other_agent_name'],
                                    'vendor_address' => null,
                                    'status' => '1',
                                    'request_status' => 'A',
                                    'created_by' => Auth::id(),
                                    'created_date' => now(),
                                ]);
                            }
                            $travelData = [
                                'Agent' => $agent->vendor_name ?? $formDataArray['other_agent_name'] ?? null,
                                'Vendor_Id' => $agent->id ?? $formDataArray['agent_name'] ?? null,
                                'PNRNo' => $formDataArray['pnr_number'] ?? null,
                                'TrainBusName' => $formDataArray['flight_number'] ?? null,
                                'Class' => $formDataArray['travel_class'] ?? null,
                                'BillDate' => CommonHelper::toDatabaseFormat($formDataArray['journey_date'] ?? null),
                                'BookingDate' => CommonHelper::toDatabaseFormat($formDataArray['booking_date'] ?? null),
                                'JourneyStartDt' => CommonHelper::toDatabaseFormat($formDataArray['journey_date'] ?? null),
                                'JourneyEndDt' => CommonHelper::toDatabaseFormat($formDataArray['journey_date'] ?? null),
                                'StayDuration' => $formDataArray['period_days'] ?? 0,
                                'JourneyFrom' => $journeyFrom?->city,
                                'Journey_From_Id' => $formDataArray['journey_from'] ?? null,
                                'JourneyUpto' => $journeyUpto?->city,
                                'Journey_Upto_Id' => $formDataArray['journey_upto'] ?? null,
                                'TravelInsurance' => $formDataArray['insurance_amount'] ?? 0,
                                'IsTravelInsurance' => $formDataArray['travel_insurance'] ?? '0',
                            ];
                            $bookingDate = CommonHelper::toDatabaseFormat($formDataArray['booking_date'] ?? null);
                            break;
                        case 16:
                            $billDate = CommonHelper::toDatabaseFormat($formDataArray['bill_date'] ?? null);
                            $travelData = [
                                'BillDate' => $billDate,
                            ];
                            $particulars = $this->parseJsonInput($request->input('particularsData', []));
                            $expenseClaimsDetailsTable = "y{$yearId}_expenseclaimsdetails";
                            DB::connection(session('expense_connection'))->table($expenseClaimsDetailsTable)->where('ExpId', $expId)->delete();
                            if (!empty($particulars)) {
                                foreach ($particulars as $row) {
                                    DB::connection(session('expense_connection'))->table($expenseClaimsDetailsTable)->insert([
                                        'ExpId' => $expId,
                                        'Title' => $row['particulars'] ?? null,
                                        'Remark' => $row['remark'] ?? null,
                                        'Amount' => $row['amount'] ?? 0,
                                    ]);
                                }
                            }
                            $bookingDate = $billDate;
                            break;
                        case 17:
                            $billDate = CommonHelper::toDatabaseFormat($formDataArray['bill_date'] ?? null);
                            $mealPaxRaw = $formDataArray['meal_participants'] ?? $formDataArray['pax_details'] ?? [];
                            if (is_string($mealPaxRaw) && str_starts_with(trim($mealPaxRaw), '[')) {
                                $decoded = json_decode($mealPaxRaw, true);
                                if (is_array($decoded)) {
                                    $mealPaxRaw = $decoded;
                                }
                            }
                            $mealPaxList = is_array($mealPaxRaw) ? $mealPaxRaw : explode(',', (string)$mealPaxRaw);
                            $mealPaxList = array_values(array_unique(array_filter(array_map('trim', $mealPaxList))));
                            $mealPaxString = implode(',', $mealPaxList);

                            $mealPaxOtherRaw = $formDataArray['meal_other_participants'] ?? $formDataArray['pax_details_other'] ?? null;
                            $mealPaxOther = CommonHelper::normalizeOtherParticipantsJson($mealPaxOtherRaw);
                            $parsedOther = CommonHelper::parseOtherParticipants($mealPaxOther);
                            $mealPaxCount = $formDataArray['PaxDetails'] ?? $formDataArray['pax_count'] ?? (count($mealPaxList) + count($parsedOther));

                            // Safeguard: Fetch existing fill data so missing inputs (e.g. Verifier view) don't wipe existing participants
                            $existingFill = DB::connection(session('expense_connection'))
                                ->table($g1ExpenseTable)
                                ->where('ExpId', $expId)
                                ->first(['PaxDetails', 'PaxDetails_Other', 'NoOfPAX']);

                            $hasExplicitPaxInInput = isset($formDataArray['meal_participants']) || (isset($formDataArray['pax_details']) && !empty($formDataArray['pax_details']));
                            if (!$hasExplicitPaxInInput && empty($mealPaxList) && $existingFill && !empty($existingFill->PaxDetails)) {
                                $mealPaxString = $existingFill->PaxDetails;
                                $mealPaxList = array_values(array_unique(array_filter(array_map('trim', explode(',', $mealPaxString)))));
                            }

                            $hasExplicitOtherInInput = isset($formDataArray['meal_other_participants']) || isset($formDataArray['pax_details_other']);
                            if (!$hasExplicitOtherInInput && empty($mealPaxOther) && $existingFill && !empty($existingFill->PaxDetails_Other)) {
                                $mealPaxOther = $existingFill->PaxDetails_Other;
                                $parsedOther = CommonHelper::parseOtherParticipants($mealPaxOther);
                            }

                            $hasExplicitCountInInput = isset($formDataArray['PaxDetails']) || isset($formDataArray['pax_count']);
                            if (!$hasExplicitCountInInput && empty($mealPaxCount) && $existingFill && !empty($existingFill->NoOfPAX)) {
                                $mealPaxCount = $existingFill->NoOfPAX;
                            } elseif (empty($mealPaxCount)) {
                                $mealPaxCount = count($mealPaxList) + count($parsedOther);
                            }

                            $travelData = [
                                'BillDate' => $billDate,
                                'NoOfPAX' => $mealPaxCount,
                                'PaxDetails' => $mealPaxString,
                                'PaxDetails_Other' => $mealPaxOther,
                            ];
                            $particulars = $this->parseJsonInput($request->input('particularsData', []));
                            $expenseClaimsDetailsTable = "y{$yearId}_expenseclaimsdetails";
                            DB::connection(session('expense_connection'))->table($expenseClaimsDetailsTable)->where('ExpId', $expId)->delete();
                            if (!empty($particulars)) {
                                foreach ($particulars as $row) {
                                    DB::connection(session('expense_connection'))->table($expenseClaimsDetailsTable)->insert([
                                        'ExpId' => $expId,
                                        'Title' => $row['particulars'] ?? null,
                                        'Remark' => $row['remark'] ?? null,
                                        'Amount' => $row['amount'] ?? 0,
                                    ]);
                                }
                            }
                            $bookingDate = $billDate;
                            break;
                        case 18:
                            $journeyFrom = CityCategory::find($formDataArray['journey_from'] ?? null);
                            $journeyUpto = CityCategory::find($formDataArray['journey_upto'] ?? null);
                            $travelData = [
                                'BookingDate' => CommonHelper::toDatabaseFormat($formDataArray['booking_date'] ?? null),
                                'JourneyStartDt' => CommonHelper::toDatabaseFormat($formDataArray['journey_start'] ?? null),
                                'JourneyEndDt' => CommonHelper::toDatabaseFormat($formDataArray['journey_end'] ?? null),
                                'JourneyFrom' => $journeyFrom?->city_name,
                                'Journey_From_Id' => $formDataArray['journey_from'] ?? null,
                                'JourneyUpto' => $journeyUpto?->city_name,
                                'Journey_Upto_Id' => $formDataArray['journey_upto'] ?? null,
                            ];
                            $bookingDate = CommonHelper::toDatabaseFormat($formDataArray['booking_date'] ?? null);
                            break;
                        case 19:
                            $travelData = [
                                'BillDate' => CommonHelper::toDatabaseFormat($formDataArray['bill_date'] ?? null),
                            ];
                            $bookingDate = CommonHelper::toDatabaseFormat($formDataArray['bill_date'] ?? null);
                            break;
                        case 20:
                            $travelData = [
                                'BillDate' => CommonHelper::toDatabaseFormat($formDataArray['bill_date'] ?? null),
                            ];
                            $bookingDate = CommonHelper::toDatabaseFormat($formDataArray['bill_date'] ?? null);
                            break;
                        case 42:
                            $billDate = CommonHelper::toDatabaseFormat($formDataArray['bill_date'] ?? null);
                            $travelData = [
                                'BillDate' => $billDate,
                            ];
                            $particulars = $this->parseJsonInput($request->input('particularsData', []));
                            $expenseClaimsDetailsTable = "y{$yearId}_expenseclaimsdetails";
                            DB::connection(session('expense_connection'))->table($expenseClaimsDetailsTable)->where('ExpId', $expId)->delete();
                            if (!empty($particulars)) {
                                foreach ($particulars as $row) {
                                    DB::connection(session('expense_connection'))->table($expenseClaimsDetailsTable)->insert([
                                        'ExpId' => $expId,
                                        'Title' => $row['particulars'] ?? null,
                                        'Remark' => $row['remark'] ?? null,
                                        'Amount' => $row['amount'] ?? 0,
                                    ]);
                                }
                            }
                            $bookingDate = $billDate;
                            break;
                        default:
                            $travelData = [];
                            $bookingDate = null;
                            break;
                    }
                    // GST-02: GST amount and invoice number of the bill (all bill claims)
                    if (!empty($travelData)) {
                        $g1Cols = array_flip(\Illuminate\Support\Facades\Schema::connection(session('expense_connection'))->getColumnListing($g1ExpenseTable));
                        if (isset($g1Cols['GST_Amount']) && array_key_exists('gst_amount', $formDataArray)) {
                            $gstAmt = str_replace(',', '', trim((string) $formDataArray['gst_amount']));
                            if ($gstAmt !== '' && (! is_numeric($gstAmt) || (float) $gstAmt < 0 || (float) $gstAmt > (float) ($formDataArray['totalAmount'] ?? $totalAmount ?: PHP_INT_MAX))) {
                                throw new \App\Services\ClaimFiles\ClaimFileException('GST amount must be a number between 0 and the bill amount.');
                            }
                            $travelData['GST_Amount'] = $gstAmt === '' ? null : round((float) $gstAmt, 2);
                        }
                        if (isset($g1Cols['Invoice']) && array_key_exists('invoice_no', $formDataArray) && ! array_key_exists('Invoice', $travelData)) {
                            $travelData['Invoice'] = mb_substr(trim((string) $formDataArray['invoice_no']), 0, 30) ?: null;
                        }
                    }
                    if (!empty($travelData)) {
                        DB::connection(session('expense_connection'))->table($g1ExpenseTable)->updateOrInsert(['ExpId' => $expId], $travelData);
                        $rawTotalAmount = $formDataArray['totalAmount'] ?? $totalAmount;
                        if (is_string($rawTotalAmount)) {
                            $rawTotalAmount = str_replace(',', '', trim($rawTotalAmount));
                        }
                        $filledTotalAmount = (is_numeric($rawTotalAmount) && (float)$rawTotalAmount > 0)
                            ? (float)$rawTotalAmount
                            : ((is_numeric($totalAmount) && (float)$totalAmount > 0) ? (float)$totalAmount : 0);

                        $is_filled = $request->input('is_filled');
                        $existingClaim = DB::connection(session('expense_connection'))->table($expenseClaimTable)->where('ExpId', $expId)->first();

                        if (empty($bookingDate) && !empty($existingClaim->BillDate) && \App\Support\ZeroDate::has($existingClaim->BillDate) && !str_starts_with((string)$existingClaim->BillDate, '0000-00-00')) {
                            $bookingDate = $existingClaim->BillDate;
                        }

                        $globalClaimData = [
                            'BillDate' => $bookingDate,
                            'ClaimId' => $claimId,
                            'ClaimMonth' => CommonHelper::getMonth($bookingDate),
                            'Remark' => '',
                        ];

                        // Separate Claimed Amount and Verified Amount (Point B.5)
                        $referer = (string) $request->headers->get('referer', '');
                        $isDataPunch = $request->input('is_data_punch') === '1'
                            || str_contains($referer, 'data-punch')
                            || (isset($is_filled) && $is_filled == 1);
                        $isVerifier = !$isDataPunch && (
                            $request->input('is_verifier') === '1'
                            || str_contains($referer, 'verify-pending')
                            || str_contains($referer, 'verify-approved')
                            || str_contains($referer, 'verify-running-km')
                        );

                        $companyId = (int) ($request->input('company_id') ?? session('company_id', 1));
                        $expenseConn = (string) (session('expense_connection') ?? config('database.default'));
                        $isVVNR = ($companyId === 11 || str_contains($expenseConn, 'vvnr'));

                        if ($this->filledAmountFrozen($existingClaim)) {
                            // FREEZE-01: claimed amount stays; the correction is the verified amount
                            $globalClaimData['VerifyTAmt'] = $filledTotalAmount;
                        } elseif ($isDataPunch || !$isVerifier) {
                            // Data Punch or Employee claim entry: update the Claimed Amount (FilledTAmt)
                            $globalClaimData['FilledTAmt'] = $filledTotalAmount;

                            if (!$isVVNR) {
                                // For VNR and Nursery: keep VerifyTAmt in sync if claim is not yet verified by a verifier
                                $isVerifyEmptyOrZero = !$existingClaim
                                    || empty($existingClaim->VerifyDate)
                                    || \App\Support\ZeroDate::none($existingClaim->VerifyDate)
                                    || str_starts_with((string)$existingClaim->VerifyDate, '0000-00-00')
                                    || empty($existingClaim->VerifyBy)
                                    || empty($existingClaim->VerifyTAmt)
                                    || (float)$existingClaim->VerifyTAmt <= 0;

                                if ($isVerifyEmptyOrZero) {
                                    $globalClaimData['VerifyTAmt'] = $filledTotalAmount;
                                }
                            }
                        } else {
                            // Verifier update: preserve claimant's original FilledTAmt, only update VerifyTAmt
                            if (!$existingClaim || empty($existingClaim->FilledTAmt) || (float)$existingClaim->FilledTAmt <= 0) {
                                $globalClaimData['FilledTAmt'] = $filledTotalAmount;
                                if (!$isVVNR) {
                                    $globalClaimData['VerifyTAmt'] = $filledTotalAmount;
                                }
                            } else {
                                $globalClaimData['VerifyTAmt'] = $filledTotalAmount;
                            }
                        }

                        if ($is_filled == 1) {
                            $mainClaimData = [];
                        }
                        if ($this->filledAmountFrozen($existingClaim)) {
                            unset($mainClaimData['FilledTAmt']); // FREEZE-01
                        }
                        $finalClaimData = array_merge($mainClaimData, $globalClaimData);
                        DB::connection(session('expense_connection'))->table($expenseClaimTable)->updateOrInsert(['ExpId' => $expId], $finalClaimData);
                        // $table = MonthExpenseFinal::tableName($yearId);
                        // $model = new MonthExpenseFinal();
                        // $model->setTable($table);
                        // $model->setConnection(session('expense_connection'));
                        // $exists = $model->where('EmployeeID', $empId)->where('Month', CommonHelper::getMonth($bookingDate))->exists();
                        // if (!$exists) {
                        //     $model->create([
                        //         'EmployeeID' => $empId,
                        //         'Month' => CommonHelper::getMonth($bookingDate),
                        //         'YearId' => $yearId,
                        //         'Status' => 'Open',
                        //         'Crdate' => now()->format('Y-m-d'),
                        //     ]);
                        // }
                        $this->logExpenseUpdate($expId, $cgId, $empId, $expenseClaimTable, $g1ExpenseTable, 'g1_expense_data');
                    }
                }
            });
            $this->punchFiles->keep();
            // MONTH-REC-01: the punched claim needs its employee-month row (the old code for it was commented out)
            if ($expId) {
                \App\Services\Claims\MonthRecord::ensure((string) session('expense_connection'), (int) $yearId, (int) $expId);
            }
            \App\Services\Claims\PunchChangeTrack::record((string) session('expense_connection'), (int) $yearId, (int) $cgId, (int) $expId, $punchBefore, (int) session('company_id', 1));
            // DUP-BILL-01: alert when another employee already claimed this bill (saved, not blocked)
            $dupBill = ((int) $cgId === 1 && $expId) ? \App\Services\Claims\BillDuplicate::flag((string) session('expense_connection'), (int) $yearId, (int) $expId) : null;

            // FILE-FP-01: an identical file is already attached to another claim (saved, not blocked)
            $fpWarn = $expId ? \App\Services\ClaimFiles\UploadFingerprint::warning((string) session('expense_connection'), (int) $yearId, (int) $expId) : null;
            if ($fpWarn) {
                $dupBill = trim(($dupBill ? $dupBill.' ' : '').$fpWarn);
            }

            // POL-08: travel class above the eligibility (saved, not blocked)
            $classWarn = $expId ? \App\Services\Claims\TravelClassCheck::warning((string) session('expense_connection'), (int) $yearId, (int) $expId) : null;
            if ($classWarn) {
                $dupBill = trim(($dupBill ? $dupBill.' ' : '').$classWarn);
            }

            return $this->jsonSuccess($dupBill ? ['duplicate_bill_warning' => $dupBill] : [], 'Claim data and files processed successfully.');
        } catch (\Throwable $e) {
            $this->punchFiles->discard();
            if ($e instanceof \App\Services\ClaimFiles\ClaimFileException) {
                return $this->jsonError($e->getMessage());
            }
            return $this->jsonError('Error processing claim: ' . $e->getMessage());
        } finally {
            $this->punchFiles = null;
        }
    }

    /** UAT R-01: files stored during the current uploadClaimFiles() save. */
    private ?\App\Services\ClaimFiles\ClaimFileBatch $punchFiles = null;

    private function implodeArray($value)
    {
        return is_array($value) ? implode(',', $value) : ($value ?: '');
    }

    private function logExpenseUpdate($expId, $cgId, $empId, $mainTable, $subTable, $subKey)
    {
        $claimRecord = DB::connection(session('expense_connection'))->table($mainTable)->where('ExpId', $expId)->first();
        $subRecord = DB::connection(session('expense_connection'))->table($subTable)->where('ExpId', $expId)->first();
        activity()->causedBy(Auth::user())->performedOn(new ExpenseClaim(['ExpId' => $expId]))->useLog('expense update')->withProperties([
            'cg_id' => $cgId,
            'emp_id' => $empId,
            'main_claim' => (array) $claimRecord,
            $subKey => (array) $subRecord,
        ])->log("Expense data updated for ExpId: {$expId}");
    }

    private function sanitizeColumnName($name)
    {
        $name = strtolower($name);
        $name = preg_replace('/[^a-z0-9]/', '_', $name);
        $name = preg_replace('/_+/', '_', $name);
        return trim($name, '_');
    }

    private function parseJsonInput($input)
    {
        $data = is_string($input) ? json_decode($input, true) : $input;
        return is_array($data) ? $data : [];
    }

    private function prepareFormData($formData)
    {
        $formData = $this->parseJsonInput($formData);
        $result = [];
        foreach ($formData as $item) {
            $key = str_replace('[]', '', $item['name']);
            if (isset($result[$key])) {
                if (!is_array($result[$key])) {
                    $result[$key] = [$result[$key]];
                }
                $result[$key][] = $item['value'];
            } else {
                $result[$key] = $item['value'];
            }
        }
        return $result;
    }

    /**
     * FREEZE-01 (Finance mail 26 Oct, decided 5 Nov): the filled amount is the employee's own
     * claim and stays as claimed. Once it is set, only the claimant may change it; corrections by
     * data punching or verification go into the verified amount. A claim uploaded without an
     * amount gets its filled amount at the first punch.
     */
    private function filledAmountFrozen(?object $existing): bool
    {
        if (! $existing || (float) ($existing->FilledTAmt ?? 0) <= 0) {
            return false;
        }
        $editor = (int) (session('employee_id') ?? session('EmployeeID') ?? 0);

        return $editor !== (int) ($existing->CrBy ?? 0);
    }

    private function getExpenseHeads($claimId)
    {
        $sanitize = fn($name) => $this->sanitizeColumnName($name);
        return DB::connection(session('expense_connection'))->table('adv_expense_head_name_mapping as m')->join('adv_expense_head_category as c', 'm.expense_head_id', '=', 'c.id')->where('m.activity_id', $claimId)->select('c.expense_head_name', 'c.short_code', 'c.has_file', 'c.file_required')->get()->keyBy(fn($row) => $sanitize($row->expense_head_name));
    }

    private function processExpensesData($expensesData, $expenseHeads, Request $request, $claimId, $yearId, $empId)
    {
        $g7Data = [];
        foreach ($expensesData as $index => $expense) {
            $valueKey = strtolower($expense['value'] ?? '');
            $key = $expenseHeads[$valueKey]->short_code ?? $this->sanitizeColumnName($expense['label'] ?? 'unknown');
            $claimAmount = $expense['claim_amount'] ?? null;
            $verifiedAmt = $expense['verified_amount'] ?? null;
            $approvedAmt = $expense['approved_amount'] ?? null;
            $financeAmt = $expense['finance_amount'] ?? null;
            $verRemark = $expense['verified_remark'] ?? null;
            $appRemark = $expense['approved_remark'] ?? null;
            $finRemark = $expense['finance_remark'] ?? null;
            $preloadedFiles = $expense['preloaded_files'] ?? [];
            if (is_string($preloadedFiles)) {
                $preloadedFiles = json_decode($preloadedFiles, true) ?? [];
            }
            $uploadedFileNames = $this->uploadFiles($request, $index, $claimId, $yearId, $empId, $key, $this->getExpenseBasePath());
            $allFiles = array_merge($preloadedFiles, $uploadedFileNames);
            $g7Data["file_{$key}"] = json_encode($allFiles);
            $g7Data[$key] = $claimAmount;
            $g7Data["ver_{$key}"] = $verifiedAmt;
            $g7Data["ver_remark_{$key}"] = $verRemark;
            $g7Data["app_{$key}"] = $approvedAmt;
            $g7Data["app_remark_{$key}"] = $appRemark;
            $g7Data["pay_{$key}"] = $financeAmt;
        }
        return $g7Data;
    }

    private function uploadFiles(Request $request, $index, $claimId, $yearId, $empId, $key, $basePath = null)
    {
        $uploadedFileNames = [];
        // Must stay identical to the read/relocate path formula in
        // buildUploadedFilesList()/viewUploadedFiles()/changeClaimHead():
        // {basePath}/activity/{yearId}/{claimId}/{empId}/{fileName}
        // Hardcoding "Expense" here (as before) broke lookups for companies
        // whose getExpenseBasePath() resolves to "VVNR_Expense".
        $basePath = $basePath ?: $this->getExpenseBasePath();
        if ($request->hasFile("expensesData.{$index}.files")) {
            $files = $request->file("expensesData.{$index}.files");
            if (!is_array($files)) {
                $files = [$files];
            }
            foreach ($files as $file) {
                if ($file instanceof \Illuminate\Http\UploadedFile && $file->isValid()) {
                    $timestamp = time();
                    $uniqueId = uniqid();
                    $extension = $file->getClientOriginalExtension();
                    $fileName = "{$claimId}_{$empId}_{$uniqueId}_{$timestamp}.{$extension}";
                    $path = "{$basePath}/activity/{$yearId}/{$claimId}/{$empId}/{$fileName}";
                    // UAT R-01: a failed store throws ClaimFileException, which rolls back the
                    // punch save (it used to be logged and skipped, and the save reported success).
                    ($this->punchFiles ?? new \App\Services\ClaimFiles\ClaimFileBatch())->put($path, $file, "expensesData.{$index}.files", $fileName);
                    $uploadedFileNames[] = $fileName;
                    Log::info('File uploaded successfully', [
                        'index' => $index,
                        'claim_id' => $claimId,
                        'file_name' => $fileName,
                        'path' => $path,
                    ]);
                } else {
                    Log::warning('Invalid or missing file in upload', [
                        'index' => $index,
                        'claim_id' => $claimId,
                    ]);
                }
            }
        } else {
            Log::info('No files found for expensesData index', [
                'index' => $index,
                'claim_id' => $claimId,
            ]);
        }
        return $uploadedFileNames;
    }

    public function getEmployeeAllClaims(Request $request)
    {
        $month = $request->month;
        $crby = $request->crby;
        $yearId = session('year_id');
        $expenseConnection = DatabaseSwitcher::getExpenseConnection();
        $table = ExpenseClaim::tableName($yearId);
        $conn = session('expense_connection') ?: $expenseConnection;
        $query = DB::connection($conn)->table($table . ' as ec')
            ->leftJoin('claimtype', 'claimtype.ClaimId', '=', 'ec.ClaimId')
            ->select('ec.ExpId', 'claimtype.ClaimName', 'ec.BillDate', 'ec.CrDate', 'ec.c_verify', 'ec.v_verify', 'ec.FilledOkay', 'ec.FilledTAmt', 'ec.DateEntryRemark', 'ec.ApprTAmt', 'ec.ApprDate', 'ec.ApprTRemark', 'ec.VerifyTAmt', 'ec.VerifyTRemark', 'ec.VerifyDate', 'ec.FinancedTAmt', 'ec.FinancedDate', 'ec.FinancedTRemark', 'ec.App_Odometer', 'ec.Backdate_Odometer_Status', 'ec.ClaimStatus')
            ->where('ec.CrBy', $crby)
            ->where('ec.ClaimStatus', '!=', 'Deactivate');
        if (!empty($month)) {
            $query->whereRaw('MONTH(ec.BillDate) = ?', [$month]);
        }
        return $query->orderBy('ec.ExpId', 'DESC')->get();
    }

    public function sendMessage(Request $request)
    {
        $request->validate(['expid' => 'required', 'message' => 'required|string']);
        $yearId = session('year_id');
        $expenseConnection = DatabaseSwitcher::getExpenseConnection();
        $expenseConnName = session('expense_connection') ?: 'expense';
        $table = ExpenseRemark::tableName($yearId);
        if (!Schema::connection($expenseConnName)->hasTable($table)) {
            if (Schema::connection($expenseConnName)->hasTable('expenseremark')) {
                $table = 'expenseremark';
            } else {
                return response()->json(['error' => 'Expense remarks table not found for this financial year.'], 400);
            }
        }
        $userRole = 'A';
        $now = now();
        DB::connection($expenseConnName)->table($table)->insert([
            'ExpId' => $request->expid,
            'Remark' => $request->message,
            'By' => auth()->user()->id,
            'ByRole' => $userRole,
            'CrDateTime' => $now,
        ]);
        $html = '
            <div class="d-flex mb-2 justify-content-end">
                <div class="p-2 remark-chat-bubble remark-chat-bubble--mine">
                    <small class="text-muted d-block">Role: ' . $userRole . '</small>
                    <div class="mt-1">' . e($request->message) . '</div>
                    <small class="text-muted d-block mt-1" style="font-size:11px;">' . $now->format('d M Y h:i A') . '</small>
                </div>
            </div>
        ';
        return response()->json(['html' => $html]);
    }

    public function actionHold(Request $request)
    {
        $request->validate([
            'expid' => 'required|integer',
            'claimid' => 'required|integer',
        ]);
        $fYearId = session('year_id');
        $table = "y{$fYearId}_expenseclaims";
        // B-01: data-punching status change, checked against the transition table and logged
        \App\Services\Claims\ClaimTransition::guard(session('expense_connection'), (int) $fYearId, fn ($q) => $q->where('ExpId', (int) $request->expid)->where('AttachTo', 0)->where('ClaimStatus', '!=', 'Deactivate'), 'Submitted', 'punching', 'punch_hold');
        try {
            $updated = DB::connection(session('expense_connection'))->table($table)->where('ExpId', $request->expid)->where('ClaimId', $request->claimid)->where('ClaimStatus', '!=', 'Deactivate')->where('AttachTo', 0)->update([
                'ClaimStatus' => 'Submitted',
                'Rmk' => 1,
            ]);
            if ($updated) {
                $holdCount = DB::connection(session('expense_connection'))->table($table)->where('ClaimStatus', 'Submitted')->where('Rmk', 1)->where('AttachTo', 0)->where('ClaimStatus', '!=', 'Deactivate')->count();
                $draftCount = DB::connection(session('expense_connection'))->table($table)->where('ClaimStatus', 'Draft')->where('Filledokay', '!=', 2)->where('AttachTo', 0)->where('ClaimStatus', '!=', 'Deactivate')->count();
                return $this->jsonSuccess([
                    'expid' => $request->expid,
                    'holdCount' => $holdCount,
                    'draftCount' => $draftCount,
                ], 'Claim has been successfully put on Hold.');
            }
            return $this->jsonError('Claim not found or already on Hold.', 404);
        } catch (\Exception $e) {
            return $this->jsonError('Failed to put claim on Hold.', 500);
        }
    }

    public function actionFilled(Request $request)
    {
        $request->validate([
            'expid' => 'required|integer',
            'claimid' => 'required|integer',
        ]);
        $fYearId = session('year_id');
        $table = "y{$fYearId}_expenseclaims";
        // B-01: data-punching status change, checked against the transition table and logged
        \App\Services\Claims\ClaimTransition::guard(session('expense_connection'), (int) $fYearId, fn ($q) => $q->where('ExpId', (int) $request->expid)->where('AttachTo', 0)->where('ClaimStatus', '!=', 'Deactivate'), 'Filled', 'punching', 'punch_filled');
        try {
            // First, get the current claim details to check for duplicates
            $currentClaim = DB::connection(session('expense_connection'))
                ->table($table)
                ->where('ExpId', $request->expid)
                ->where('ClaimId', $request->claimid)
                ->where('ClaimStatus', '!=', 'Deactivate')
                ->where('AttachTo', 0)
                ->first();
            
            if (!$currentClaim) {
                return $this->jsonError('Claim not found.', 404);
            }
            
            // Check for duplicate expenses: same employee, claim type, bill date, and amount
            // Only check if we have all required data
            if ($currentClaim->CrBy && $currentClaim->ClaimId && $currentClaim->BillDate && $currentClaim->FilledTAmt) {
                // Format amount to handle potential floating point precision issues
                $amount = (float) $currentClaim->FilledTAmt;
                $duplicateCheck = DB::connection(session('expense_connection'))
                    ->table($table)
                    ->where('ExpId', '!=', $request->expid) // Exclude current claim
                    ->where('CrBy', $currentClaim->CrBy) // Same employee
                    ->where('ClaimId', $currentClaim->ClaimId) // Same claim type
                    ->whereDate('BillDate', $currentClaim->BillDate) // Same bill date (date comparison)
                    ->where('AttachTo', 0)
                    ->where('ClaimStatus', '!=', 'Deactivate')
                    // Use absolute value comparison with small tolerance for floating point
                    ->whereRaw('ABS(FilledTAmt - ?) < 0.01', [$amount])
                    ->first();
                
                if ($duplicateCheck) {
                    return $this->jsonError(
                        "Duplicate punch: A claim of the same type already exists for this employee on {$currentClaim->BillDate} with same amount (ExpId: {$duplicateCheck->ExpId})."
                    );
                }
            }
            
            $fillUpdate = [
                'ClaimStatus' => 'Filled',
                'Filledokay' => 1,
                'ClaimAtStep' => 3,
                'FilledBy' => auth()->id(),
                'FilledDate' => now(),
            ];

            $companyId = (int) ($request->input('company_id') ?? session('company_id', 1));
            $expenseConn = (string) (session('expense_connection') ?? config('database.default'));
            $isVVNR = ($companyId === 11 || str_contains($expenseConn, 'vvnr'));

            if (!$isVVNR && $currentClaim) {
                $isVerifyEmptyOrZero = empty($currentClaim->VerifyDate)
                    || \App\Support\ZeroDate::none($currentClaim->VerifyDate)
                    || str_starts_with((string)$currentClaim->VerifyDate, '0000-00-00')
                    || empty($currentClaim->VerifyBy)
                    || empty($currentClaim->VerifyTAmt)
                    || (float)$currentClaim->VerifyTAmt <= 0;

                if ($isVerifyEmptyOrZero && !empty($currentClaim->FilledTAmt) && (float)$currentClaim->FilledTAmt > 0) {
                    $fillUpdate['VerifyTAmt'] = $currentClaim->FilledTAmt;
                }
            }

            $updated = DB::connection(session('expense_connection'))->table($table)->where('ExpId', $request->expid)->where('ClaimId', $request->claimid)->where('ClaimStatus', '!=', 'Deactivate')->where('AttachTo', 0)->update($fillUpdate);
            if ($updated) {
                $filledCount = DB::connection(session('expense_connection'))->table($table)->where('ClaimStatus', 'Filled')->where('Filledokay', '!=', 2)->where('AttachTo', 0)->where('ClaimStatus', '!=', 'Deactivate')->count();
                $uploadedCount = DB::connection(session('expense_connection'))->table($table)->where('ClaimStatus', 'Submitted')->where('Rmk', 0)->where('AttachTo', 0)->where('ClaimStatus', '!=', 'Deactivate')->count();
                $holdCount = DB::connection(session('expense_connection'))->table($table)->where('ClaimStatus', 'Submitted')->where('Rmk', 1)->where('AttachTo', 0)->where('ClaimStatus', '!=', 'Deactivate')->count();
                return $this->jsonSuccess([
                    'expid' => $request->expid,
                    'filledCount' => $filledCount,
                    'uploadedCount' => $uploadedCount,
                    'holdCount' => $holdCount,
                ], 'Claim has been marked as Filled.');
            }
            return $this->jsonError('Claim not found or already Filled.', 404);
        } catch (\Exception $e) {
            return $this->jsonError('Failed to mark claim as Filled.', 500);
        }
    }

    public function actionDeny(Request $request)
    {
        $request->validate([
            'expid' => 'required|integer',
            'claimid' => 'required|integer',
        ]);
        $fYearId = session('year_id');
        $table = "y{$fYearId}_expenseclaims";
        // B-01: data-punching status change, checked against the transition table and logged
        \App\Services\Claims\ClaimTransition::guard(session('expense_connection'), (int) $fYearId, fn ($q) => $q->where('ExpId', (int) $request->expid)->where('AttachTo', 0)->where('ClaimStatus', '!=', 'Deactivate'), 'Filled', 'punching', 'punch_deny');
        try {
            $updated = DB::connection(session('expense_connection'))->table($table)
                ->where('ExpId', $request->expid)
                ->where('ClaimId', $request->claimid)
                ->where('ClaimStatus', '!=', 'Deactivate')
                ->where('AttachTo', 0)
                ->update([
                    'ClaimStatus' => 'Filled',
                    'Filledokay' => 2,
                    'FilledBy' => auth()->id(),
                    'FilledDate' => now(),
                    'Rmk' => 0,
                ]);

            if ($updated) {
                $holdCount = DB::connection(session('expense_connection'))->table($table)->where('ClaimStatus', 'Submitted')->where('Rmk', 1)->where('AttachTo', 0)->where('ClaimStatus', '!=', 'Deactivate')->count();
                $draftCount = DB::connection(session('expense_connection'))->table($table)->where('ClaimStatus', 'Draft')->where('Filledokay', '!=', 2)->where('AttachTo', 0)->where('ClaimStatus', '!=', 'Deactivate')->count();
                $filledCount = DB::connection(session('expense_connection'))->table($table)->where('ClaimStatus', 'Filled')->where('Filledokay', '!=', 2)->where('AttachTo', 0)->where('ClaimStatus', '!=', 'Deactivate')->count();
                $uploadedCount = DB::connection(session('expense_connection'))->table($table)->where('ClaimStatus', 'Submitted')->where('Rmk', 0)->where('AttachTo', 0)->where('ClaimStatus', '!=', 'Deactivate')->count();
                $deniedCount = DB::connection(session('expense_connection'))->table($table)->where('Filledokay', 2)->where('AttachTo', 0)->where('ClaimStatus', '!=', 'Deactivate')->count();

                return $this->jsonSuccess([
                    'expid' => $request->expid,
                    'holdCount' => $holdCount,
                    'draftCount' => $draftCount,
                    'filledCount' => $filledCount,
                    'uploadedCount' => $uploadedCount,
                    'deniedCount' => $deniedCount,
                ], 'Claim has been Denied successfully.');
            }
            return $this->jsonError('Claim not found or already Denied.', 404);
        } catch (\Exception $e) {
            return $this->jsonError('Failed to Deny claim.', 500);
        }
    }

    public function actionRevertUploaded(Request $request)
    {
        $request->validate([
            'expid' => 'required|integer',
            'claimid' => 'required|integer',
        ]);
        $fYearId = session('year_id');
        $table = "y{$fYearId}_expenseclaims";
        // B-01: data-punching status change, checked against the transition table and logged
        \App\Services\Claims\ClaimTransition::guard(session('expense_connection'), (int) $fYearId, fn ($q) => $q->where('ExpId', (int) $request->expid)->where('AttachTo', 0)->where('ClaimStatus', '!=', 'Deactivate'), 'Submitted', 'punching', 'punch_revert_uploaded');
        try {
            $updated = DB::connection(session('expense_connection'))->table($table)
                ->where('ExpId', $request->expid)
                ->where('ClaimId', $request->claimid)
                ->where('ClaimStatus', '!=', 'Deactivate')
                ->where('AttachTo', 0)
                ->update([
                    'ClaimStatus' => 'Submitted',
                    'Filledokay' => 0,
                    'FilledBy' => 0,
                    'FilledDate' => null,
                    'Rmk' => 0,
                ]);

            if ($updated) {
                $holdCount = DB::connection(session('expense_connection'))->table($table)->where('ClaimStatus', 'Submitted')->where('Rmk', 1)->where('AttachTo', 0)->where('ClaimStatus', '!=', 'Deactivate')->count();
                $draftCount = DB::connection(session('expense_connection'))->table($table)->where('ClaimStatus', 'Draft')->where('Filledokay', '!=', 2)->where('AttachTo', 0)->where('ClaimStatus', '!=', 'Deactivate')->count();
                $filledCount = DB::connection(session('expense_connection'))->table($table)->where('ClaimStatus', 'Filled')->where('Filledokay', '!=', 2)->where('AttachTo', 0)->where('ClaimStatus', '!=', 'Deactivate')->count();
                $uploadedCount = DB::connection(session('expense_connection'))->table($table)->where('ClaimStatus', 'Submitted')->where('Rmk', 0)->where('AttachTo', 0)->where('ClaimStatus', '!=', 'Deactivate')->count();
                $deniedCount = DB::connection(session('expense_connection'))->table($table)->where('Filledokay', 2)->where('AttachTo', 0)->where('ClaimStatus', '!=', 'Deactivate')->count();

                return $this->jsonSuccess([
                    'expid' => $request->expid,
                    'holdCount' => $holdCount,
                    'draftCount' => $draftCount,
                    'filledCount' => $filledCount,
                    'uploadedCount' => $uploadedCount,
                    'deniedCount' => $deniedCount,
                ], 'Claim has been reverted to Uploaded status successfully.');
            }
            return $this->jsonError('Claim not found or could not be reverted.', 404);
        } catch (\Exception $e) {
            return $this->jsonError('Failed to revert claim to Uploaded status.', 500);
        }
    }

    public function actionDraft(Request $request)
    {
        $request->validate([
            'expid' => 'required|integer',
            'claimid' => 'required|integer',
        ]);
        $fYearId = session('year_id');
        $table = "y{$fYearId}_expenseclaims";
        // B-01: data-punching status change, checked against the transition table and logged
        \App\Services\Claims\ClaimTransition::guard(session('expense_connection'), (int) $fYearId, fn ($q) => $q->where('ExpId', (int) $request->expid)->where('AttachTo', 0)->where('ClaimStatus', '!=', 'Deactivate'), 'Draft', 'punching', 'punch_draft');
        try {
            $updated = DB::connection(session('expense_connection'))->table($table)->where('ExpId', $request->expid)->where('ClaimId', $request->claimid)->where('ClaimStatus', '!=', 'Deactivate')->where('AttachTo', 0)->update([
                'ClaimStatus' => 'Draft',
                'Filledokay' => 0,
                'Rmk' => 0,
            ]);
            if ($updated) {
                $holdCount = DB::connection(session('expense_connection'))->table($table)->where('ClaimStatus', 'Submitted')->where('Rmk', 1)->where('AttachTo', 0)->where('ClaimStatus', '!=', 'Deactivate')->count();
                $draftCount = DB::connection(session('expense_connection'))->table($table)->where('ClaimStatus', 'Draft')->where('Filledokay', '!=', 2)->where('AttachTo', 0)->where('ClaimStatus', '!=', 'Deactivate')->count();
                return $this->jsonSuccess([
                    'expid' => $request->expid,
                    'holdCount' => $holdCount,
                    'draftCount' => $draftCount,
                ], 'Claim has been saved as Draft successfully.');
            }
            return $this->jsonError('Claim not found or already in Draft.', 404);
        } catch (\Exception $e) {
            return $this->jsonError('Failed to save as Draft.', 500);
        }
    }

    public function getCities(Request $request)
    {
        try {
            $search = $request->input('search', '');
            $query = DB::connection(session('expense_connection'))->table('city_category')->select('ccid', 'city_name', 'city_category')->orderBy('city_name');
            if (!empty($search)) {
                $query->where(function ($q) use ($search) {
                    $q->where('city_name', 'LIKE', "%{$search}%")->orWhere('city_category', 'LIKE', "%{$search}%");
                });
            }
            $cities = $query->limit(100)->get();
            return response()->json(['success' => true, 'cities' => $cities]);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => 'Error fetching cities: ' . $e->getMessage()], 500);
        }
    }

    public function getHotels(Request $request)
    {
        try {
            $search = $request->input('search', '');
            $cityId = $request->input('city_id');
            $query = DB::connection(session('expense_connection'))->table('hotel_list')->select('hid', 'hotel_name', 'hotel_address', 'HotelContact', 'HotelEmail')->orderBy('hotel_name');
            if (!empty($cityId)) {
                $query->where('ccid', $cityId);
            }
            if (!empty($search)) {
                $query->where('hotel_name', 'LIKE', "%{$search}%");
            }
            $hotels = $query->limit(100)->get();
            return response()->json(['success' => true, 'hotels' => $hotels]);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => 'Error fetching hotels: ' . $e->getMessage()], 500);
        }
    }

    public function getHotelsByCity(Request $request)
    {
        return $this->getHotels($request);
    }

    public function getHotelDetails(Request $request)
    {
        try {
            $hotelId = $request->input('hotel_id');
            $hotel = DB::connection(session('expense_connection'))->table('hotel_list')->where('hid', $hotelId)->first(['hotel_name', 'hotel_address', 'HotelContact', 'HotelEmail']);
            return response()->json(['success' => true, 'hotel' => $hotel]);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => 'Error fetching hotel details: ' . $e->getMessage()], 500);
        }
    }

    public function getEmployees(Request $request)
    {
        try {
            $search = $request->input('search', '');

            $query = DB::connection(session('hrims_connection'))->table('hrm_employee')->select('EmployeeID', 'EmpCode', DB::raw("CONCAT(Fname, ' ', COALESCE(Sname, ''), ' ', COALESCE(Lname, '')) as full_name"))->where('EmpStatus', 'A')->orderBy('Fname');
            if (!empty($search)) {
                $query->where(function ($q) use ($search) {
                    $q->where('Fname', 'LIKE', "%{$search}%")->orWhere('Sname', 'LIKE', "%{$search}%")->orWhere('Lname', 'LIKE', "%{$search}%")->orWhere('EmpCode', 'LIKE', "%{$search}%");
                });
            }
            $employees = $query->limit(100)->get();
            return response()->json(['success' => true, 'employees' => $employees]);
        } catch (\Illuminate\Database\QueryException $e) {
            // Handle missing hrm_employee table
            if (str_contains($e->getMessage(), "doesn't exist") || str_contains($e->getMessage(), 'Table') && str_contains($e->getMessage(), 'hrm_employee')) {
                Log::warning('HRIMS employee table not found for company ' . session('company_id', 'unknown') . ': ' . $e->getMessage());
                return response()->json(['success' => true, 'employees' => [], 'message' => 'Employee data not available for this company']);
            }
            return response()->json(['success' => false, 'message' => 'Database error: ' . $e->getMessage()], 500);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => 'Error fetching employees: ' . $e->getMessage()], 500);
        }
    }

    public function getEmployeesByIds(Request $request)
    {
        try {
            $employeeIds = $request->input('employee_ids', []);
            if (empty($employeeIds)) {
                return response()->json(['success' => true, 'employees' => []]);
            }

            $employees = DB::connection(session('hrims_connection'))->table('hrm_employee')->select('EmployeeID', 'EmpCode', DB::raw("CONCAT(Fname, ' ', COALESCE(Sname, ''), ' ', COALESCE(Lname, '')) as full_name"))->whereIn('EmployeeID', $employeeIds)->where('EmpStatus', 'A')->orderBy('Fname')->get();
            return response()->json(['success' => true, 'employees' => $employees]);
        } catch (\Illuminate\Database\QueryException $e) {
            // Handle missing hrm_employee table
            if (str_contains($e->getMessage(), "doesn't exist") || str_contains($e->getMessage(), 'Table') && str_contains($e->getMessage(), 'hrm_employee')) {
                Log::warning('HRIMS employee table not found for company ' . session('company_id', 'unknown') . ': ' . $e->getMessage());
                return response()->json(['success' => true, 'employees' => [], 'message' => 'Employee data not available for this company']);
            }
            return response()->json(['success' => false, 'message' => 'Database error: ' . $e->getMessage()], 500);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => 'Error fetching employees: ' . $e->getMessage()], 500);
        }
    }

    public function getCourierProviders()
    {
        $providers = DB::connection(session('expense_connection'))
            ->table('courier_provider')
            ->orderBy('name')
            ->get(['id', 'name']);
        return response()->json(['success' => true, 'data' => $providers]);
    }

    public function getCityCategory(Request $request)
    {
        try {
            $ccid = $request->input('ccid');
            $city = DB::connection(session('expense_connection'))->table('city_category')->where('ccid', $ccid)->first(['city_category']);
            return response()->json(['success' => true, 'category' => $city->city_category ?? '']);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => 'Error fetching city category: ' . $e->getMessage()], 500);
        }
    }
}