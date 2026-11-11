<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\G1ExpenseFillData;
use App\Models\HRMEmployees;
use App\Models\EligibilityPolicy;
use App\Helpers\DatabaseSwitcher;
use App\Models\ClaimType;
use App\Models\OtherParticipantCategory;
use App\Helpers\CommonHelper;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class PaxDetailsController extends Controller
{
    private function resolveNumericYearId($yearId, $comId = null)
    {
        if (empty($yearId)) {
            $yearId = session('year_id', 8);
        }

        $targetCompanyId = $comId ? (int)$comId : (int)session('company_id', 1);
        $expenseConn = DatabaseSwitcher::getExpenseConnection($targetCompanyId);

        // Handle numeric yearId
        if (is_numeric($yearId)) {
            $numYearId = (int)$yearId;
            try {
                // First check if numYearId exists directly in target connection's financialyear table
                $existsInTarget = DB::connection($expenseConn)->table('financialyear')
                    ->where('YearId', $numYearId)
                    ->exists();

                if ($existsInTarget) {
                    return $numYearId;
                }

                // If Company 11 (VVNR) and yearId was passed from master VNR session (e.g. 8 for 2026-2027)
                if ($targetCompanyId === 11) {
                    $vnrYearStr = DB::connection('expense')->table('financialyear')
                        ->where('YearId', $numYearId)
                        ->value('Year');

                    if ($vnrYearStr) {
                        $matchedVvnrId = DB::connection('expense_vvnr')->table('financialyear')
                            ->where('Year', $vnrYearStr)
                            ->value('YearId');
                        if ($matchedVvnrId) {
                            return (int)$matchedVvnrId;
                        }
                    }
                    // Fallback offset of -2 for company 11
                    return max(1, $numYearId - 2);
                }
            } catch (\Throwable $e) {
                // Fall through to string search
            }
            return $numYearId;
        }

        try {
            $yearStr = trim((string)$yearId);
            $firstYearPart = trim(explode('-', $yearStr)[0]);

            // 1. Try exact match on Year (e.g., '2026-2027' or '2026-27')
            $fy = DB::connection($expenseConn)->table('financialyear')
                ->where('Year', $yearStr)
                ->value('YearId');

            // 2. Try alternate year string formats if hyphenated (e.g. 2026-2027 <-> 2026-27)
            if (!$fy && str_contains($yearStr, '-')) {
                $parts = explode('-', $yearStr);
                $startYear = trim($parts[0]);
                $endYear = trim($parts[1]);

                if (strlen($endYear) === 4) {
                    $shortFormat = $startYear . '-' . substr($endYear, 2); // e.g. "2026-27"
                    $fy = DB::connection($expenseConn)->table('financialyear')
                        ->where('Year', $shortFormat)
                        ->value('YearId');
                } elseif (strlen($endYear) === 2) {
                    $longFormat = $startYear . '-20' . $endYear; // e.g. "2026-2027"
                    $fy = DB::connection($expenseConn)->table('financialyear')
                        ->where('Year', $longFormat)
                        ->value('YearId');
                }
            }

            // 3. Try matching Year starting with firstYearPart (e.g. "2026-%")
            if (!$fy && !empty($firstYearPart)) {
                $fy = DB::connection($expenseConn)->table('financialyear')
                    ->where('Year', 'LIKE', "{$firstYearPart}-%")
                    ->value('YearId');
            }

            // 4. Try matching by y1 integer column if present
            if (!$fy && is_numeric($firstYearPart)) {
                $fy = DB::connection($expenseConn)->table('financialyear')
                    ->where('y1', (int)$firstYearPart)
                    ->value('YearId');
            }

            if ($fy) {
                return (int) $fy;
            }
        } catch (\Throwable $e) {
            Log::error("PaxDetailsController resolveNumericYearId error: " . $e->getMessage());
        }

        return (int) session('year_id', 8);
    }

    /**
     * Get PAX details for a specific expense claim
     * Returns: Employee details, eligibility, quota (for rail fare)
     */
    public function getPaxDetails(Request $request)
    {
        $expId = $request->exp_id;
        $rawYearId = (int) ($request->year_id ?? session('year_id')); // Use request parameter first, fallback to session
        $claimId = $request->claim_id;
        $targetCompanyId = (int) ($request->com_id ?? session('company_id', 1));

        if (!$expId || !$rawYearId || !$claimId) {
            return response()->json(['error' => 'Missing parameters'], 400);
        }

        $yearId = $this->resolveNumericYearId($rawYearId, $targetCompanyId);

        try {
            $expenseConn = DatabaseSwitcher::getExpenseConnection($targetCompanyId);
            $cgId = ClaimType::getCgIdByClaimId($claimId) ?: 1;
            $tableName = "y{$yearId}_g{$cgId}_expensefilldata";

            $fillData = DB::connection($expenseConn)->table($tableName)
                ->where('ExpId', $expId)
                ->first();

            if (!$fillData) {
                $participantCodes = '';
                $otherParticipants = '';
                $paxCount = 0;
                $quota = null;
            } else {
                $participantCodes = $fillData->PaxDetails ?? '';
                $otherParticipants = $fillData->PaxDetails_Other ?? '';
                $paxCount = $fillData->NoOfPAX ?? 0;
                $quota = $fillData->Quota ?? null; // For rail fare
            }

            $employeeDetails = [];
            $participantArray = [];

            // If there are participant codes, fetch employee details
            if (!empty($participantCodes)) {
                $decoded = json_decode($participantCodes, true);
                $keys = is_array($decoded) ? $decoded : array_map('trim', explode(',', $participantCodes));

                try {
                    $hrimsConn = session('hrims_connection') ?: 'hrims';
                    $employees = DB::connection($hrimsConn)->table('hrm_employee')
                        ->leftJoin('hrm_department', 'hrm_employee.DeptID', '=', 'hrm_department.DepartmentID')
                        ->leftJoin('hrm_designation', 'hrm_employee.DesigID', '=', 'hrm_designation.DesignationID')
                        ->leftJoin('hrm_grade', 'hrm_employee.GradeID', '=', 'hrm_grade.GradeID')
                        ->leftJoin('hrm_branch', 'hrm_employee.BranchID', '=', 'hrm_branch.BranchID')
                        ->leftJoin('hrm_division', 'hrm_employee.DivisionID', '=', 'hrm_division.DivisionID')
                        ->whereIn('hrm_employee.EmployeeID', $keys)
                        ->where('hrm_employee.EmpStatus', 'A')
                        ->select(
                            'hrm_employee.*',
                            'hrm_department.DeptName',
                            'hrm_designation.DesigName',
                            'hrm_grade.GradeName',
                            'hrm_branch.BranchName',
                            'hrm_division.DivisionName'
                        )
                        ->get();

                    foreach ($employees as $emp) {
                        $empCode = !empty($emp->EmpCode_New) ? $emp->EmpCode_New : $emp->EmpCode;
                        $fullName = trim(($emp->Fname ?? '') . ' ' . ($emp->Sname ?? '') . ' ' . ($emp->Lname ?? ''));
                        
                        $empDetails = [
                            'emp_code' => $empCode,
                            'emp_id' => $emp->EmployeeID,
                            'emp_name' => $fullName,
                            'first_name' => $emp->Fname ?? '',
                            'middle_name' => $emp->Sname ?? '',
                            'last_name' => $emp->Lname ?? '',
                            'email' => $emp->EmailID ?? null,
                            'mobile' => $emp->MobileNo ?? null,
                            'department' => [
                                'id' => $emp->DeptID ?? null,
                                'name' => $emp->DeptName ?? null
                            ],
                            'designation' => [
                                'id' => $emp->DesigID ?? null,
                                'name' => $emp->DesigName ?? null
                            ],
                            'grade' => [
                                'id' => $emp->GradeID ?? null,
                                'name' => $emp->GradeName ?? null
                            ],
                            'branch' => [
                                'id' => $emp->BranchID ?? null,
                                'name' => $emp->BranchName ?? null
                            ],
                            'division' => [
                                'id' => $emp->DivisionID ?? null,
                                'name' => $emp->DivisionName ?? null
                            ],
                            'joining_date' => $emp->JoinDate ?? null,
                            'date_of_birth' => $emp->DOB ?? null,
                            'gender' => $emp->Gender ?? null,
                            'reporting_manager_id' => $emp->ReportingManagerID ?? null,
                            'emp_status' => $emp->EmpStatus ?? null,
                            'eligibility' => $this->getEmployeeEligibility($emp->EmployeeID, $claimId, $comId),
                            'is_valid' => true
                        ];
                        $participantArray[] = $empDetails;
                        $employeeDetails[] = $empDetails;
                    }

                    // Handle any keys that weren't found in employee list
                    foreach ($keys as $key) {
                        $found = $employees->first(function($e) use ($key) {
                            return (string)$e->EmployeeID === (string)$key;
                        });
                        if (!$found) {
                            $participantArray[] = [
                                'emp_code' => $key,
                                'emp_id' => is_numeric($key) ? $key : null,
                                'emp_name' => 'Employee not found',
                                'first_name' => '',
                                'middle_name' => '',
                                'last_name' => '',
                                'email' => null,
                                'mobile' => null,
                                'department' => null,
                                'designation' => null,
                                'grade' => null,
                                'branch' => null,
                                'division' => null,
                                'joining_date' => null,
                                'date_of_birth' => null,
                                'gender' => null,
                                'reporting_manager_id' => null,
                                'emp_status' => null,
                                'eligibility' => null,
                                'is_valid' => false
                            ];
                        }
                    }
                } catch (\Throwable $empEx) {
                    Log::error("getPaxDetails: Employee lookup failed: " . $empEx->getMessage());
                }
            }

            $parsedOtherParticipants = CommonHelper::parseOtherParticipants($otherParticipants);
            $otherCount = count($parsedOtherParticipants);
            $normalizedOtherJson = CommonHelper::normalizeOtherParticipantsJson($otherParticipants);

            // Fetch active master categories for category resolution
            $masterCategories = [];
            try {
                OtherParticipantCategory::ensureTableExists();
                $masterCategories = OtherParticipantCategory::active()->get(['id', 'name', 'code']);
            } catch (\Throwable $catEx) {
                Log::warning("getPaxDetails: Master categories lookup issue: " . $catEx->getMessage());
            }

            $response = [
                'success' => true,
                'exp_id' => $expId,
                'claim_id' => $claimId,
                'pax_count' => $paxCount,
                'participants' => $participantArray,
                'other_participants' => $otherParticipants,
                'other_participants_parsed' => $parsedOtherParticipants,
                'master_categories' => $masterCategories,
                'quota' => $quota, // For rail fare
                'claim_type_name' => $this->getClaimTypeName($claimId),
                'total_participants' => count($participantArray) + $otherCount
            ];

            return response()->json($response);

        } catch (\Throwable $e) {
            Log::error("getPaxDetails Exception: " . $e->getMessage());
            return response()->json(['error' => 'Server error: ' . $e->getMessage()], 500);
        }
    }

    /**
     * Get employee eligibility details for a specific claim type
     */
    private function getEmployeeEligibility($employeeId, $claimId, $comId)
    {
        try {
            $hrimsConn = session('hrims_connection') ?: 'hrims';
            $eligibility = DB::connection($hrimsConn)
                ->table('hrm_employee_eligibility')
                ->where('EmployeeID', $employeeId)
                ->where('CompanyID', $comId)
                ->select('MaxAmount', 'MaxDays', 'PolicyId', 'EligibilityStatus', 'DA_Inside_Hq', 'DA_Outside_Hq')
                ->orderByRaw("CASE WHEN Status = 'A' THEN 0 ELSE 1 END")
                ->first();

            if ($eligibility) {
                $cleanDhq = preg_replace('/[^0-9.]/', '', (string)($eligibility->DA_Inside_Hq ?? ''));
                $cleanDAtHq = preg_replace('/[^0-9.]/', '', (string)($eligibility->DA_Outside_Hq ?? ''));
                return [
                    'max_amount' => $eligibility->MaxAmount,
                    'max_days' => $eligibility->MaxDays,
                    'policy_id' => $eligibility->PolicyId,
                    'status' => $eligibility->EligibilityStatus,
                    'da_inside_hq' => is_numeric($cleanDhq) ? (float)$cleanDhq : 0,
                    'da_outside_hq' => is_numeric($cleanDAtHq) ? (float)$cleanDAtHq : 0,
                ];
            }

            return null;

        } catch (\Throwable $e) {
            return null;
        }
    }

    /**
     * Get claim type name from claim ID
     */
    private function getClaimTypeName($claimId)
    {
        $claimTypes = [
            1 => 'Lodging',
            14 => 'Rail Fare',
            17 => 'Group Meal',
            16 => 'Mobile Recharge',
            42 => 'General Expense'
        ];

        return $claimTypes[$claimId] ?? 'Unknown Claim Type';
    }

    /**
     * Update quota for rail fare claim (Data Punch)
     */
    public function updateQuota(Request $request)
    {
        $expId = $request->exp_id;
        $rawYearId = ($request->filled('year_id') ? (int) $request->year_id : null);
        $newQuota = $request->quota;
        $targetCompanyId = (int) ($request->com_id ?? session('company_id', 1));

        if (!$expId || !$rawYearId || !$newQuota) {
            return response()->json(['error' => 'Missing parameters'], 400);
        }

        $yearId = $this->resolveNumericYearId($rawYearId, $targetCompanyId);

        // Tracker #18: only known ticket categories, stored in canonical spelling.
        $validator = app(\App\Services\ClaimRules\ClaimSubmissionValidator::class);
        $quotaError = \App\Services\ClaimRules\ClaimSubmissionValidator::first($validator->railTicketCategory($newQuota));
        if ($quotaError !== null) {
            return response()->json(['error' => $quotaError], 422);
        }
        $newQuota = $validator->normalizeTicketCategory($newQuota);

        try {
            $expenseConn = DatabaseSwitcher::getExpenseConnection($targetCompanyId);
            $claimId = $request->claim_id ?? 14;
            $cgId = ClaimType::getCgIdByClaimId($claimId) ?: 1;
            $tableName = "y{$yearId}_g{$cgId}_expensefilldata";

            $updated = DB::connection($expenseConn)->table($tableName)
                ->where('ExpId', $expId)
                ->update(['Quota' => $newQuota]);

            if ($updated) {
                return response()->json([
                    'success' => true,
                    'message' => 'Quota updated successfully',
                    'new_quota' => $newQuota
                ]);
            } else {
                return response()->json(['error' => 'Failed to update quota'], 400);
            }

        } catch (\Throwable $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    /**
     * Update PAX details for group claims
     */
    public function updatePaxDetails(Request $request)
    {
        $expId = $request->exp_id;
        $rawYearId = ($request->filled('year_id') ? (int) $request->year_id : null);
        $claimId = $request->claim_id ?? 17;
        $targetCompanyId = (int) ($request->com_id ?? session('company_id', 1));
        $newParticipants = $request->participants; // JSON array
        $otherParticipants = $request->other_participants;

        if (!$expId || !$rawYearId || !$newParticipants) {
            return response()->json(['error' => 'Missing parameters'], 400);
        }

        $yearId = $this->resolveNumericYearId($rawYearId, $targetCompanyId);

        try {
            $participantCodes = is_array($newParticipants) ? implode(',', $newParticipants) : $newParticipants;
            $expenseConn = DatabaseSwitcher::getExpenseConnection($targetCompanyId);
            $cgId = ClaimType::getCgIdByClaimId($claimId) ?: 1;
            $tableName = "y{$yearId}_g{$cgId}_expensefilldata";

            $updated = DB::connection($expenseConn)->table($tableName)
                ->where('ExpId', $expId)
                ->update([
                    'PaxDetails' => $participantCodes,
                    'PaxDetails_Other' => CommonHelper::normalizeOtherParticipantsJson($otherParticipants)
                ]);

            if ($updated) {
                return response()->json([
                    'success' => true,
                    'message' => 'PAX details updated successfully'
                ]);
            } else {
                return response()->json(['error' => 'Failed to update PAX details'], 400);
            }

        } catch (\Throwable $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    /**
     * Upload supporting document for PAX details
     */
    public function uploadDocument(Request $request)
    {
        $expId = $request->exp_id;
        $rawYearId = ($request->filled('year_id') ? (int) $request->year_id : null);
        $claimId = $request->claim_id;
        $targetCompanyId = (int) ($request->com_id ?? session('company_id', 1));

        if (!$expId || !$rawYearId || !$claimId) {
            return response()->json(['error' => 'Missing parameters'], 400);
        }

        if (!$request->hasFile('document')) {
            return response()->json(['error' => 'No file uploaded'], 400);
        }

        $file = $request->file('document');

        // Validate file (LV-S10: type read from the content, extension from the detected type, 10 MB)
        try {
            $extension = \App\Support\SafeUpload::check($file, ['jpg', 'png', 'gif', 'pdf', 'webp', 'heic'], 10240);
        } catch (\RuntimeException $e) {
            return response()->json(['error' => 'Invalid file: '.$e->getMessage().' Allowed: JPG, PNG, GIF, PDF'], 400);
        }

        $yearId = $this->resolveNumericYearId($rawYearId, $targetCompanyId);

        try {
            // FILES-01 (UAT-03): stored privately (storage/app/private/pax_documents/yearId/claimId/), not
            // in public/. The saved path and URL keep the old "uploads/pax_documents/..." form; that URL
            // is now an authorised route (PrivateFileController@pax, RecordAccess on the claim).
            $yearId = (int) $yearId;
            $claimId = (int) $claimId;
            $directory = "uploads/pax_documents/{$yearId}/{$claimId}";
            $publicPath = storage_path("app/private/pax_documents/{$yearId}/{$claimId}");

            if (!file_exists($publicPath)) {
                mkdir($publicPath, 0750, true);
            }

            // Generate unique filename: exp_{expId}_{timestamp}.{extension}
            $timestamp = time();
            $filename = "exp_".(int) $expId."_{$timestamp}_".bin2hex(random_bytes(3)).".{$extension}";
            $relativePath = "{$directory}/{$filename}";

            // Move file to destination
            $file->move($publicPath, $filename);

            // Store file path in database (optional - you can add a column to store this)
            $expenseConn = DatabaseSwitcher::getExpenseConnection($targetCompanyId);
            $cgId = ClaimType::getCgIdByClaimId($claimId) ?: 1;
            $tableName = "y{$yearId}_g{$cgId}_expensefilldata";

            // Update the PaxDocumentPath field if it exists, otherwise just return success
            try {
                DB::connection($expenseConn)->table($tableName)
                    ->where('ExpId', $expId)
                    ->update(['PaxDocumentPath' => $relativePath]);
            } catch (\Throwable $e) {
                // Column might not exist yet, that's okay
                Log::info("PaxDocumentPath column may not exist: " . $e->getMessage());
            }

            return response()->json([
                'success' => true,
                'message' => 'Document uploaded successfully',
                'file_path' => $relativePath,
                'file_url' => url($relativePath),
                'file_name' => $filename
            ]);

        } catch (\Throwable $e) {
            Log::error("uploadDocument Exception: " . $e->getMessage());
            return response()->json(['error' => 'Upload failed: ' . $e->getMessage()], 500);
        }
    }
}
