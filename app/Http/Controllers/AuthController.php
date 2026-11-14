<?php

namespace App\Http\Controllers;

use App\Helpers\DatabaseSwitcher;
use App\Models\FinancialYear;
use App\Models\HRMEmployees;
use App\Models\User;
use App\Models\UserLog;
use Firebase\JWT\JWT;
use Firebase\JWT\Key;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Session;
use Illuminate\Validation\ValidationException;
use Exception;

class AuthController extends Controller
{
    protected $strcode;

    public function __construct()
    {
        $this->strcode = ['', '0', '1', '2', '3', '4', '5', '6', '7', '8', '9', 'a', 'A', 'b', 'B', 'c', 'C', 'd', 'D', 'e', 'E', 'f', 'F', 'g', 'G', 'h', 'H', 'i', 'I', 'j', 'J', 'k', 'K', 'l', 'L', 'm', 'M', 'n', 'N', 'o', 'O', 'p', 'P', 'q', 'Q', 'r', 'R', 's', 'S', 't', 'T', 'u', 'U', 'v', 'V', 'w', 'W', 'x', 'X', 'y', 'Y', 'z', 'Z', '#', '@', '$', '%', '^', '&', '*', '_', '!', '?', ' '];
    }

    public function showLoginForm()
    {
        if (Auth::check()) {
            return redirect()->route('dashboard');
        }

        // LOGIN-UI-01: one sign-in page (with company and financial year) at "/"
        return redirect()->route('login');
    }

    public function login(Request $request)
    {
        // If the user is already authenticated and just pressed back,
        // send them to the dashboard instead of showing the login form.
        if (auth()->check() && $request->isMethod('get') && !$request->input('token')) {
            return redirect()->route('dashboard');
        }

        $token = $request->input('token');
        // LV-S02: the token key comes from the environment (was hard-coded); rotate it.
        $secretKey = (string) config('app.sso_jwt_key');
        $login_method = $token ? 'token' : 'normal';
        $status_messages = ['Login attempt initiated'];
        $log_data = [
            'user_id' => 0,
            'ip_address' => $request->ip(),
            'is_success' => 0,
            'user_agent' => $request->userAgent(),
            'timestamp' => now()->toDateTimeString(),
            'status' => [],  // Will be updated with JSON-encoded status_messages
            'login_method' => $login_method,
        ];

        // If GET request and no token, show login view
        if ($request->isMethod('get') && !$token) {
            // Get financial years for both companies to populate the dropdown
            // Default to VNR (company 1) financial years for initial load
            // LOGIN-UI-03: a company database that cannot be reached leaves that company's year list empty
            // instead of failing the whole sign-in page
            $years = function (int $company) {
                try {
                    DatabaseSwitcher::setCompanyConnections($company);

                    return FinancialYear::orderBy('YearId', 'desc')->get(['YearId', 'Year', 'FromDate', 'Todate']);
                } catch (\Throwable $e) {
                    \Illuminate\Support\Facades\Log::warning("Sign-in page: financial years of company {$company} not available: ".$e->getMessage());

                    return collect();
                }
            };
            $vnr_financial_years = $years(1);
            // LOGIN-COMPANY-01: only VNR's year list is shown; the other companies' lists are no longer needed here
            $vnr_nursery_financial_years = collect();
            $vvnr_financial_years = collect();

            // Reset to default connection
            DatabaseSwitcher::setCompanyConnections(1);

            $status_messages[] = 'Login page accessed';
            try {
                UserLog::create(array_merge($log_data, ['status' => json_encode($status_messages)]));
            } catch (\Throwable $e) {
                // LOGIN-UI-03: a page-view log row must not stop the sign-in page from opening
                \Illuminate\Support\Facades\Log::warning('Sign-in page view not logged: '.$e->getMessage());
            }

            return view('login', compact('vnr_financial_years', 'vvnr_financial_years', 'vnr_nursery_financial_years'));
        }

        // Validate input (company and financial_year not required for token-based login)
        try {
            $credentials = $request->validate([
                'employeeid' => $token ? 'nullable|string' : 'required|string',
                'password' => $token ? 'nullable|string' : 'required|string',
                // LOGIN-COMPANY-01 (14 Nov 2026): no company on the sign-in page; it comes from the employee's HR record
                'company' => 'nullable|in:1,3,11',
                'financial_year' => $token ? 'nullable' : 'required',
            ]);
            $status_messages[] = 'Input validation completed';
        } catch (ValidationException $e) {
            // Format validation errors for logging
            $errorMessages = collect($e->errors())->flatten()->toArray();
            $errorCount = count($errorMessages);
            $reason = 'Input validation failed: ' . $errorMessages[0] . ($errorCount > 1 ? ' (and ' . ($errorCount - 1) . ' more errors)' : '');
            $status_messages[] = $reason;
            UserLog::create(array_merge($log_data, ['status' => json_encode($status_messages)]));

            return back()->withErrors($e->errors())->withInput($request->except('password'));
        }

        // Initialize variables
        $user = null;
        $token_valid = false;
        $decoded = null;

        // Determine company ID early (will be 1 for token auth, request company for normal auth)
        $companyId = (int) ($token ? 1 : ($request->company ?? 1));

        // Set company-specific database connections
        DatabaseSwitcher::setCompanyConnections($companyId);
        $status_messages[] = 'Database connections set for company: ' . DatabaseSwitcher::getCompanyName($companyId);

        // Check if financial year is valid
        $fy_check = FinancialYear::orderBy('YearId', 'desc')->first();
        if (!$fy_check) {
            $status_messages[] = 'Invalid or inactive financial year';
            UserLog::create(array_merge($log_data, ['status' => json_encode($status_messages)]));

            return back()
                ->with('error', 'Invalid or inactive financial year.')
                ->withInput($request->except('password'));
        }
        $status_messages[] = 'Financial year validated';

        // Handle token-based authentication
        if ($token) {
            try {
                $status_messages[] = 'Attempting token-based authentication';
                if ($secretKey === '') {
                    throw new Exception('Token login is not configured.');
                }
                $decoded = JWT::decode($token, new Key($secretKey, 'HS256'));
                // LV-S02: optionally require an expiry so a leaked token cannot be reused forever
                if (config('app.sso_jwt_require_exp') && (! isset($decoded->exp) || (int) $decoded->exp < time())) {
                    throw new Exception('Login link has expired. Please open Xeasy again from the portal.');
                }
                if (!isset($decoded->sub) || empty($decoded->sub)) {
                    $status_messages[] = 'Token username does not match provided identity';
                    UserLog::create(array_merge($log_data, ['status' => json_encode($status_messages)]));
                    throw new Exception('Token username does not match provided identity.');
                }
                if ($request->employeeid && $decoded->sub !== $request->employeeid) {
                    $status_messages[] = 'Token username mismatch with provided employee ID';
                    UserLog::create(array_merge($log_data, ['status' => json_encode($status_messages)]));
                    throw new Exception('Token username does not match provided identity.');
                }

                $user = User::where('employee_id', $decoded->sub)
                    ->where('status', 1)
                    ->first();

                if (!$user) {
                    $status_messages[] = 'User not found or inactive';
                    $log_data['user_id'] = $decoded->sub ?? $log_data['user_id'];
                    UserLog::create(array_merge($log_data, ['status' => json_encode($status_messages)]));
                }

                // Check for existing session with different user
                if (Session::has('authenticated') && Session::get('employee_id') != $user->employee_id) {
                    $status_messages[] = 'Session terminated for new user login';
                    $log_data['user_id'] = Session::get('employee_id') ?: 0;
                    UserLog::create(array_merge($log_data, ['status' => json_encode($status_messages)]));
                    Session::flush();

                    return back()
                        ->with('error', 'Previous user session terminated. Logging in as new user.')
                        ->withInput($request->except('password'));
                }

                $token_valid = true;
                $status_messages[] = 'Token-based authentication successful, using static company=1, financial_year=7';
            } catch (Exception $e) {
                $status_messages[] = 'Token authentication failed: ' . $e->getMessage();
                UserLog::create(array_merge($log_data, ['status' => json_encode($status_messages)]));

                return back()
                    ->with('error', 'Invalid or expired token: ' . $e->getMessage())
                    ->withInput($request->except('password'));
            }
        }

        // Initialize employee data variable
        $employeeData = null;
        $role = 'E';  // Default role (Employee)
        $reporting_vess = 'N';
        $reporting_nursery = 'N';

        // Handle normal authentication or token with password
        if (!$token_valid || ($token_valid && $request->password)) {
            if (!$request->employeeid) {
                $status_messages[] = 'Employee ID required for normal authentication';
                UserLog::create(array_merge($log_data, ['status' => json_encode($status_messages)]));

                return back()->withErrors([
                    'employeeid' => 'The employeeid field is required for normal authentication.',
                ])->withInput($request->except('password'));
            }

            $status_messages[] = 'Attempting normal authentication';

            // Use appropriate HRIMS connection based on company
            $hrimsConnection = DatabaseSwitcher::getHrimsConnection($companyId);

            // Remove leading zeros from employee code (original uses EmpCode_New)
            // VVNR codes are stored with 'v' prefix (e.g., 'v2256'), keep as-is
            // Handle case-insensitive input (user may type 'V2256' but DB has 'v2256')
            $empcode = ltrim($request->employeeid, '0') ?: $request->employeeid;
            // C-06: lockout after repeated wrong passwords
            if ($lockMsg = \App\Support\LoginLockout::blocked($request->employeeid)) {
                UserLog::create(array_merge($log_data, ['status' => json_encode(['Locked: too many wrong passwords'])]));

                return back()->withErrors(['employeeid' => $lockMsg])->withInput($request->except('password'));
            }
            // Lowercase the prefix for VVNR codes (v/V prefix)
            if (preg_match('/^[a-zA-Z]/', $empcode)) {
                $empcode = strtolower($empcode);
            }

            // ── Step 1: Resolve the real CompanyId from HRIMS (same as original PHP) ──
            // The original always looks up CompanyId from the DB rather than trusting
            // the form value, because EmpCode_New is unique across all companies.
            $empRow = \Illuminate\Support\Facades\DB::connection($hrimsConnection)
                ->table('hrm_employee')
                ->select('CompanyId')
                ->where('EmpCode_New', $empcode)
                ->where('EmpStatus', '!=', 'De')
                ->first();

            if ($empRow) {
                $companyId = (int) $empRow->CompanyId;
                // LOGIN-COMPANY-01: the year is matched to the company by its label after sign-in (see yearForCompany);
                // the old fixed "VVNR = VNR - 2" offset is gone
                // Re-set DB connections now that we know the real company
                DatabaseSwitcher::setCompanyConnections($companyId);
            }

            // HRIMS always stores employees under CompanyId from the DB lookup above
            $hrimsCompanyId = $companyId;

            // ── Step 2: Check employee status ──────────────────────────────────────
            $employeeCheck = \Illuminate\Support\Facades\DB::connection($hrimsConnection)
                ->table('hrm_employee')
                ->select('EmpStatus', 'DateOfSepration')
                ->where('EmpCode_New', $empcode)
                ->where('CompanyId', $hrimsCompanyId)
                ->where('EmpStatus', '!=', 'De')
                ->first();

            $run_qry = null;
            $num = 0;

            if ($employeeCheck) {
                $DatAcc = date('Y-m-d', strtotime($employeeCheck->DateOfSepration . '+15 day'));

                if ($employeeCheck->EmpStatus == 'A' || $employeeCheck->EmpStatus == 'R') {
                    $run_qry = \Illuminate\Support\Facades\DB::connection($hrimsConnection)
                        ->table('hrm_employee')
                        ->where('EmpCode_New', $empcode)
                        ->where('CompanyId', $hrimsCompanyId)
                        ->whereIn('EmpStatus', ['A', 'R']);
                } elseif ($employeeCheck->EmpStatus == 'D' && \App\Support\ZeroDate::has($DatAcc) && $DatAcc != '1970-01-01') {
                    $run_qry = \Illuminate\Support\Facades\DB::connection($hrimsConnection)
                        ->table('hrm_employee')
                        ->where('EmpCode_New', $empcode)
                        ->where('CompanyId', $hrimsCompanyId)
                        ->where('EmpStatus', 'D')
                        ->whereDate('DateOfSepration', '<=', $DatAcc);
                }

                if ($run_qry) {
                    $num = $run_qry->count();
                    $employee = $run_qry->first();
                }
            }

            if ($num > 0) {
                // Check password (with special cases for specific employee IDs)
                $decryptedPass = $this->decrypt($employee->EmpPass);
                // LV-S02: password must match; the fixed-password code and the any-password
                // list of 5 EmployeeIDs were removed.
                $isValidPassword = is_string($decryptedPass) && $decryptedPass !== '' && hash_equals($decryptedPass, (string) $request->password);

                if (!$isValidPassword) {
                    \App\Support\LoginLockout::failed($request->employeeid);
                    $status_messages[] = 'Invalid credentials';
                    $log_data['user_id'] = (int) preg_replace('/[^0-9]/', '', $request->employeeid);
                    UserLog::create(array_merge($log_data, ['status' => json_encode($status_messages)]));

                    return back()->withErrors([
                        'employeeid' => 'The provided credentials do not match our records.',
                    ])->withInput($request->except('password'));
                }

                \App\Support\LoginLockout::succeeded($request->employeeid);

                // Update token for Android/Web (non-iOS)
                $special_characters = [':', '-', '`', '~', '^', '!', '@', '#', '$', '%', '^', '&', '*', '(', ')', '{', '}', '_', '+'];
                $token_clean = str_replace($special_characters, '', $request->input('tokenid', ''));

                if ($token_clean != '' && $request->input('platform', '') != 'ios') {
                    \Illuminate\Support\Facades\DB::connection($hrimsConnection)
                        ->table('hrm_employee')
                        ->where('EmployeeID', $employee->EmployeeID)
                        ->update(['xeasy_tokenid' => $token_clean]);
                }

                // Update token for iOS
                if ($request->input('platform', '') == 'ios' && $token_clean != '') {
                    \Illuminate\Support\Facades\DB::connection($hrimsConnection)
                        ->table('hrm_employee')
                        ->where('EmployeeID', $employee->EmployeeID)
                        ->update(['xeasy_tokenid_ios' => $token_clean]);
                }

                // Get Grade Value
                $grade = \Illuminate\Support\Facades\DB::connection($hrimsConnection)
                    ->table('hrm_employee_general as g')
                    ->join('core_grades as gr', 'g.GradeId', '=', 'gr.id')
                    ->where('g.EmployeeID', $employee->EmployeeID)
                    ->select('gr.grade_name as GradeValue')
                    ->first();

                // Get MoveRep status
                $moveRep = \Illuminate\Support\Facades\DB::connection($hrimsConnection)
                    ->table('hrm_employee')
                    ->where('EmployeeID', $employee->EmployeeID)
                    ->select('MoveRep')
                    ->first();

                // Determine Role (A = Appraiser, E = Employee)
                $appraiserCount = \Illuminate\Support\Facades\DB::connection($hrimsConnection)
                    ->table('hrm_employee_general')
                    ->where('RepEmployeeID', $employee->EmployeeID)
                    ->count();

                if ($appraiserCount > 0 || ($moveRep && $moveRep->MoveRep == 'Y')) {
                    $role = 'A';
                } else {
                    $role = 'E';
                }

                // Get detailed employee information
                $employeeData = \Illuminate\Support\Facades\DB::connection($hrimsConnection)
                    ->table('hrm_employee as e')
                    ->leftJoin('hrm_employee_general as eg', 'e.EmployeeID', '=', 'eg.EmployeeID')
                    ->leftJoin('hrm_employee_personal as ep', 'e.EmployeeID', '=', 'ep.EmployeeID')
                    ->leftJoin('core_city_village_by_state as ccvs', 'eg.HqId', '=', 'ccvs.id')
                    ->leftJoin('core_territory as ct', 'eg.TerrId', '=', 'ct.id')
                    ->leftJoin('core_departments as cd', 'eg.DepartmentId', '=', 'cd.id')
                    ->leftJoin('core_designation as cdes', 'eg.DesigId', '=', 'cdes.id')
                    ->leftJoin('core_grades as cg', 'eg.GradeId', '=', 'cg.id')
                    ->where('e.EmployeeId', $employee->EmployeeID)
                    ->where('e.CompanyId', $hrimsCompanyId)
                    ->select(
                        'e.EmployeeID',
                        'eg.GradeId',
                        'eg.MobileNo_Vnr as OfficialMobile',
                        'cg.grade_name as GradeValue',
                        'eg.HqId as HeadquarterId',
                        \Illuminate\Support\Facades\DB::raw('CASE WHEN eg.TerrId > 0 THEN ct.territory_name ELSE ccvs.city_village_name END as HqName'),
                        'eg.DepartmentId',
                        'cd.department_name as DepartmentName',
                        'eg.DesigId',
                        'cdes.designation_name as DesigName',
                        'eg.RepEmployeeID as ReportingEmployeeID',
                        'ep.MobileNo as PersonalMobile',
                        'eg.ReportingName as ReportingName'
                    )
                    ->first();

                // Check Reporting_NURSURY (employees in Nursery company reporting to this person)
                $nurseryCount = \Illuminate\Support\Facades\DB::connection($hrimsConnection)
                    ->table('hrm_employee_general')
                    ->where('RepEmployeeID', $employee->EmployeeID)
                    ->whereIn('EmployeeID', function ($query) {
                        $query->select('EmployeeID')
                              ->from('hrm_employee')
                              ->where('CompanyId', 3)
                              ->whereIn('EmpStatus', ['A', 'R']);
                    })
                    ->count();

                // Check Reporting_VESS (VVNR employees reporting to this person, or MoveRep=Y)
                $vvnrCount = \Illuminate\Support\Facades\DB::connection($hrimsConnection)
                    ->table('hrm_employee_general as g')
                    ->join('hrm_employee as e', 'g.EmployeeID', '=', 'e.EmployeeID')
                    ->where('g.RepEmployeeID', $employee->EmployeeID)
                    ->whereIn('e.EmpStatus', ['A', 'R'])
                    ->where('e.CompanyId', 11)
                    ->count();

                $reporting_nursery = ($nurseryCount > 0) ? 'Y' : 'N';
                $reporting_vess    = ($vvnrCount > 0 || ($moveRep && $moveRep->MoveRep == 'Y')) ? 'Y' : 'N';

                // Get user from local users table
                $user = User::where('employee_id', $employee->EmployeeID)->first();

                // Fallback: Check if user was created using EmpCode or EmpCode_New
                if (!$user) {
                    $empCodes = array_filter([$employee->EmpCode_New ?? null, $employee->EmpCode ?? null, $request->employeeid ?? null]);
                    $user = User::whereIn('employee_id', $empCodes)->first();
                    if ($user) {
                        $user->update(['employee_id' => (string) $employee->EmployeeID]);
                    }
                }

                // Ensure user status is active
                if ($user && $user->status != 1) {
                    $user->update(['status' => 1]);
                }

                // If user doesn't exist in local users table, auto-create so active HRIMS employee can login
                if (!$user) {
                    $empFullName = trim(($employee->Fname ?? '') . ' ' . ($employee->Sname ? $employee->Sname . ' ' : '') . ($employee->Lname ?? ''));
                    $user = User::create([
                        'name' => $empFullName ?: ($employee->EmpCode_New ?? $employee->EmpCode ?? 'Employee'),
                        'email' => $employeeData->OfficialMobile ?? ($empcode . '@vnrseeds.com'),
                        'password' => \Illuminate\Support\Facades\Hash::make(\Illuminate\Support\Str::random(40)), // S-07: employees log in against HRIMS; no HRIMS cipher or fixed password copied here
                        'employee_id' => (string) $employee->EmployeeID,
                        'role_id' => 7,
                        'status' => 1,
                        'company_access_id' => (string) ($employee->CompanyId ?? 1),
                        'created_by' => 1,
                        'updated_by' => 1,
                    ]);
                    $user->assignRole(7);
                }

                // Always keep user name in sync with HRIMS employee full name
                if ($user) {
                    $empFullName = trim(($employee->Fname ?? '') . ' ' . ($employee->Sname ? $employee->Sname . ' ' : '') . ($employee->Lname ?? ''));
                    if (!empty($empFullName) && $user->name !== $empFullName) {
                        $user->update(['name' => $empFullName]);
                    }
                }

                $status_messages[] = $user ? 'Normal authentication successful' : 'User not found after employee check';
            } else {
                // Check if user exists in local users table as a guest user
                $guestUser = User::where(function ($q) use ($request) {
                    $q->where('employee_id', $request->employeeid)
                      ->orWhere('email', $request->employeeid);
                })->where('status', 1)->first();

                // AUTH-HRIMS-01: only HRIMS passwords log in, unless auth.local_password_login is switched on
                if ($guestUser && ! config('auth.local_password_login', false)) {
                    $status_messages[] = 'Local (guest) password login is switched off: HRIMS login only';
                    $guestUser = null;
                }
                if ($guestUser && \Illuminate\Support\Facades\Hash::check($request->password, $guestUser->password)) {
                    \App\Support\LoginLockout::succeeded($request->employeeid);
                    $user = $guestUser;
                    $role = 'Guest';
                    $status_messages[] = 'Guest user authentication successful';
                } else {
                    \App\Support\LoginLockout::failed($request->employeeid);
                    $status_messages[] = 'Employee/User not found or inactive';
                    $log_data['user_id'] = (int) preg_replace('/[^0-9]/', '', $request->employeeid) ?: 0;
                    UserLog::create(array_merge($log_data, ['status' => json_encode($status_messages)]));

                    return back()->withErrors([
                        'employeeid' => 'Invalid credentials or account is inactive.',
                    ])->withInput($request->except('password'));
                }
            }
        }

        // If no user found, authentication failed
        if (!$user) {
            $status_messages[] = 'Authentication failed';
            UserLog::create(array_merge($log_data, ['status' => json_encode($status_messages)]));

            return back()
                ->with('error', 'Authentication failed.')
                ->withInput($request->except('password'));
        }

        // Set session data
        $log_data['user_id'] = $user->employee_id;

        // Cast to int — $request->company comes in as a string; keep it consistent everywhere
        $companyAccessId = $user->company_access_id;
        $companyAccess = array_filter(explode(',', (string) $companyAccessId));
        // LOGIN-COMPANY-01: the company is the employee's own (HR record); other companies are reached with the
        // company switch in the header. A company still posted (older forms) is honoured if the user has access.
        $hrCompany = (int) (isset($employee) ? ($employee->CompanyId ?? 1) : 1);
        $reqCompany = (int) ($token_valid ? 1 : ($request->company ?: (in_array($hrCompany, [1, 3, 11], true) ? $hrCompany : 1)));
        
        if (!empty($companyAccess) && !in_array($reqCompany, $companyAccess)) {
            $companyId = (int) ($companyAccess[0] ?? 1);
        } else {
            $companyId = $reqCompany;
        }

        DatabaseSwitcher::setCompanyConnections($companyId);
        $companyName  = DatabaseSwitcher::getCompanyName($companyId);

        // Detect user's assigned Spatie roles and determine effective role code
        $userRoleNames = $user->roles ? $user->roles->pluck('name')->toArray() : [];
        $isMediator = false;
        $isVerifier = false;
        $isAdmin = false;
        foreach ($userRoleNames as $rName) {
            $lower = strtolower($rName);
            if (str_contains($lower, 'mediator') || str_contains($lower, 'punch')) {
                $isMediator = true;
            }
            if (str_contains($lower, 'verifier') || str_contains($lower, 'verify')) {
                $isVerifier = true;
            }
            if (str_contains($lower, 'admin')) {
                $isAdmin = true;
            }
        }

        $effectiveRoleCode = $role ?? 'E';
        if ($isMediator) {
            $effectiveRoleCode = 'M';
            $role = 'M';
            Session::put('mediator', 'Y');
        } elseif ($isVerifier) {
            $effectiveRoleCode = 'V';
            $role = 'V';
            Session::put('verifier', 'Y');
        } elseif ($isAdmin) {
            $effectiveRoleCode = 'S';
            $role = 'S';
        }

        // Self-heal: ensure role_id on users table is populated with primary role if null
        if (empty($user->role_id) && $user->roles->isNotEmpty()) {
            $user->update(['role_id' => $user->roles->first()->id]);
        }

        Session::put('back_office', false);
        Session::put('back_office_active', false);
        Session::put('role_id', $effectiveRoleCode);
        Session::put('emp_role', $effectiveRoleCode);
        Session::put('employee_id', $user->employee_id);
        Session::put('EmployeeID', $user->employee_id);
        Session::put('company_id', $companyId);
        Session::put('company', $companyName);
        Session::put('company_name', $companyName);
        Session::put('company_access_id', $companyAccessId);

        // Store the employee's API token so controllers can pass it to external APIs
        $tokenIdValue = isset($employee) ? ($employee->xeasy_tokenid ?? '') : '';
        Session::put('token_id', $tokenIdValue);

        $empFullName = isset($employee)
            ? trim(($employee->Fname ?? '') . ' ' . ($employee->Sname ? $employee->Sname . ' ' : '') . ($employee->Lname ?? ''))
            : ($user->name ?? '');
        $empCodeVal = isset($employee)
            ? ($employee->EmpCode_New ?? $employee->EmpCode ?? $request->employeeid)
            : ($request->employeeid ?: ($decoded ? $decoded->sub : ''));

        // Add additional employee data to session (from your PHP code)
        if ($employeeData) {
            Session::put('emp_code', $empCodeVal);
            Session::put('emp_name', $empFullName);
            Session::put('emp_role', $effectiveRoleCode);
            Session::put('emp_grade', $grade->GradeValue ?? $employeeData->GradeValue ?? '');
            Session::put('emp_grade_value', $employeeData->GradeValue ?? '');
            Session::put('reporting_vess', $reporting_vess);
            Session::put('reporting_nursery', $reporting_nursery);
            Session::put('emp_status', $employee->EmpStatus ?? '');
            Session::put('headquarter_name', $employeeData->HqName ?? '');
            Session::put('department_name', $employeeData->DepartmentName ?? '');
            Session::put('designation_name', $employeeData->DesigName ?? '');
            Session::put('mobile_no', $employeeData->OfficialMobile ?? '');
            Session::put('reporting_name', $employeeData->ReportingName ?? '');
            Session::forget('profile_img_v2'); // PHOTO-01
            Session::put('profile_img', \App\Support\EmployeePhoto::url($empCodeVal, (int) (($employee->CompanyId ?? null) ?: $companyId)));

            // Store the complete employee data array for easy access
            Session::put('employee_details', [
                'EmployeeID' => $employee->EmployeeID,
                'EmpCode' => $empCodeVal,
                'EmpName' => $empFullName,
                'ComId' => $companyId,
                'Role' => $effectiveRoleCode,
                'Grade' => $grade->GradeValue ?? $employeeData->GradeValue ?? '',
                'Reporting_VESS' => $reporting_vess,
                'Reporting_NURSURY' => $reporting_nursery,
                'Status' => $employee->EmpStatus ?? '',
                'GradeValue' => $employeeData->GradeValue ?? '',
                'HeadQuarterName' => $employeeData->HqName ?? '',
                'DepartmentName' => $employeeData->DepartmentName ?? '',
                'DesigName' => $employeeData->DesigName ?? '',
                'MobileNo' => $employeeData->OfficialMobile ?? '',
                'ReportingName' => $employeeData->ReportingName ?? '',
                'ProfileImg' => \App\Support\EmployeePhoto::url($empCodeVal, (int) (($employee->CompanyId ?? null) ?: $companyId))
            ]);
        } else {
            Session::put('emp_code', $empCodeVal);
            Session::put('emp_name', $empFullName);
            Session::put('emp_role', $effectiveRoleCode);
            $profileImg = ($user && $user->is_guest)
                ? asset('assets/images/users/avatar-1.jpg')
                : (\App\Support\EmployeePhoto::url($empCodeVal, (int) (($employee->CompanyId ?? null) ?: $companyId)));
            Session::put('profile_img', $profileImg);
            Session::put('is_guest', (bool) ($user->is_guest ?? false));

            $firstRole = $user->roles ? $user->roles->first() : null;
            $deptOrRole = $firstRole ? $firstRole->name : 'Guest';
            Session::put('employee_details', [
                'EmployeeID' => $user->id,
                'EmpCode' => $empCodeVal,
                'EmpName' => $empFullName,
                'ComId' => $companyId,
                'Role' => $effectiveRoleCode,
                'Grade' => '',
                'Reporting_VESS' => 'N',
                'Reporting_NURSURY' => 'N',
                'Status' => 'A',
                'GradeValue' => '',
                'HeadQuarterName' => '',
                'DepartmentName' => $deptOrRole,
                'DesigName' => $deptOrRole,
                'MobileNo' => '',
                'ReportingName' => '',
                'ProfileImg' => $profileImg,
            ]);
        }

        // Login user
        Auth::login($user, $request->has('remember'));

        // Fetch financial year details for session
        if ($token_valid) {
            $today = date('Y-m-d');
            $activeFy = FinancialYear::where('FromDate', '<=', $today)
                ->where('Todate', '>=', $today)
                ->first();
            $yearId = $activeFy ? (int) $activeFy->YearId : 8;
        } else {
            $yearId = $this->yearForCompany((int) $request->financial_year, (int) $companyId, $request->filled('company'));
        }
        $yearValue = FinancialYear::getYearById($yearId);

        Session::put('authenticated', true);
        Session::put('year_id', $yearId);
        Session::put('year_value', $yearValue);

        // Fetch advance access flags from the legacy API (same as Flutter show_request_tab)
        // URL format: {API_BASE_URL}/advance_request.php?value=show_request_tab&YearId=...
        try {
            $empId   = $user->employee_id;
            $tokenId = $tokenIdValue;
            $advUrl  = \App\Support\LegacyApi::baseUrl()
                     . '/advance_request.php'
                     . '?value=show_request_tab'
                     . '&YearId='   . urlencode($yearId)
                     . '&tokenid='  . urlencode($tokenId)
                     . '&ComId='    . urlencode($companyId)
                     . '&empid='    . urlencode($empId);

            $advResp = \App\Support\LegacyApi::http(10)
                ->get($advUrl)
                ->json();

            // Response wraps flags inside 'data' key: {"code":200,"status":"success","data":{...}}
            $flags = $advResp['data'] ?? $advResp;

            Session::put('adv_activity',          ($flags['Activity']                    ?? '') === 'Y' ? 'Y' : 'N');
            Session::put('adv_expense',           ($flags['Expense']                     ?? '') === 'Y' ? 'Y' : 'N');
            Session::put('adv_other',             ($flags['Other']                       ?? '') === 'Y' ? 'Y' : 'N');
            Session::put('adv_approver',          ($flags['IsApprover']                  ?? '') === 'Y' ? 'Y' : 'N');
            Session::put('adv_hod',               ($flags['IsHOD']                       ?? '') === 'Y' ? 'Y' : 'N');
            Session::put('adv_submitted_for_me',  ($flags['AdvanceRequestsSubmittedForMe'] ?? '') === 'Y' ? 'Y' : 'N');
            Session::put('adv_act_advance',       ($flags['ActivityAdvance']             ?? '') === 'Y' ? 'Y' : 'N');
            Session::put('adv_exp_advance',       ($flags['ExpenseAdvance']              ?? '') === 'Y' ? 'Y' : 'N');
            Session::put('adv_oth_advance',       ($flags['OtherAdvance']               ?? '') === 'Y' ? 'Y' : 'N');
            Session::put('adv_tour_travel',       ($flags['IsTourTravel']               ?? '') === 'Y' ? 'Y' : 'N');
            Session::put('adv_allow_expense',     ($flags['isAllowExpense']             ?? '') === 'Y' ? 'Y' : 'N');

            // Master gate — show Advance tab if user can apply OR approve OR is HOD
            $hasAny = in_array('Y', [
                $flags['Activity']     ?? '',
                $flags['Expense']      ?? '',
                $flags['Other']        ?? '',
                $flags['IsApprover']   ?? '',
                $flags['IsHOD']        ?? '',
                $flags['IsTourTravel'] ?? '',
            ]);
            Session::put('advance_module', $hasAny ? 'Y' : 'N');
        } catch (\Exception $e) {
            // Non-fatal — advance module flags remain unset (hidden)
            \Log::warning('Advance access API failed during login: ' . $e->getMessage());
            Session::put('advance_module', 'N');
        }

        $status_messages[] = 'Session data and Auth completed';

        // Log successful login
        $log_data['is_success'] = 1;
        $status_messages[] = 'Login successful';
        UserLog::create(array_merge($log_data, ['status' => json_encode($status_messages)]));

        return redirect()->route('dashboard')->with('success', 'Logged in successfully');
    }

    public function logout(Request $request)
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect('/login')->with('success', 'Logged out successfully');
    }

    public function checkEmp($empCode)
    {
        // LV-S01: this returned an employee's HRMS password in clear text; disabled.
        abort(404);

        $rawCode = trim($empCode);
        $noZero = ltrim($rawCode, '0') ?: $rawCode;
        $upper = strtoupper($rawCode);
        $lower = strtolower($rawCode);
        $isNumeric = is_numeric($noZero);

        $hasPrefix = preg_match('/^([a-zA-Z]+)(\d+)$/', $rawCode, $matches);
        $prefix = $hasPrefix ? strtoupper($matches[1]) : null;
        $numPart = $hasPrefix ? (int) $matches[2] : null;

        $hrimsDb = DatabaseSwitcher::getHrimsDatabaseName();

        $employees = \Illuminate\Support\Facades\DB::table($hrimsDb . '.hrm_employee as e')
            ->leftJoin($hrimsDb . '.hrm_employee_general as g', 'e.EmployeeID', '=', 'g.EmployeeID')
            ->leftJoin($hrimsDb . '.core_departments as d', 'g.DepartmentId', '=', 'd.id')
            ->leftJoin($hrimsDb . '.core_designation as des', 'g.DesigId', '=', 'des.id')
            ->leftJoin($hrimsDb . '.core_grades as gr', 'g.GradeId', '=', 'gr.id')
            ->where(function ($q) use ($rawCode, $noZero, $upper, $lower, $isNumeric, $hasPrefix, $prefix, $numPart) {
                $q->where('e.EmpCode', $rawCode)
                    ->orWhere('e.EmpCode', $upper)
                    ->orWhere('e.EmpCode', $lower)
                    ->orWhere('e.EmpCode_New', $rawCode)
                    ->orWhere('e.EmpCode_New', $upper)
                    ->orWhere('e.EmpCode_New', $lower);

                if ($isNumeric) {
                    $q->orWhere('e.EmpCode', $noZero)
                        ->orWhere('e.EmpCode_New', $noZero)
                        ->orWhere('e.ECode', (int) $noZero)
                        ->orWhere('e.EmployeeID', (int) $noZero);
                }

                if ($hasPrefix && $numPart !== null) {
                    $q->orWhere(function ($sub) use ($prefix, $numPart) {
                        $sub->where('e.VCode', $prefix)
                            ->where('e.ECode', $numPart);
                    });
                }
            })
            ->select([
                'e.EmployeeID',
                'e.EmpCode',
                'e.EmpCode_New',
                'e.ECode',
                'e.VCode',
                'e.CompanyId',
                'e.EmpStatus',
                'e.DateOfResignation',
                'e.DateOfSepration',
                'e.EmpPass',
                'e.Fname',
                'e.Sname',
                'e.Lname',
                'g.EmailId_Vnr',
                'g.MobileNo_Vnr',
                'd.department_name',
                'des.designation_name',
                'gr.grade_name',
            ])
            ->get();

        if ($employees->isEmpty()) {
            return response()->json([
                'success' => false,
                'message' => 'Employee not found',
                'emp_code' => $empCode,
            ], 404);
        }

        // Map employee data with decrypted password and company info
        $mapped = $employees->map(function ($emp) {
            $decryptedPassword = !empty($emp->EmpPass) ? $this->decrypt($emp->EmpPass) : '';
            $companyName = DatabaseSwitcher::getCompanyName($emp->CompanyId);
            $fullName = trim(($emp->Fname ?? '') . ' ' . ($emp->Sname ?? '') . ' ' . ($emp->Lname ?? ''));

            return [
                'employee_id' => $emp->EmployeeID,
                'emp_code' => $emp->EmpCode,
                'emp_code_new' => $emp->EmpCode_New,
                'emp_name' => $fullName,
                'company_id' => $emp->CompanyId,
                'company_name' => $companyName,
                'status' => in_array($emp->EmpStatus, ['A', 'R']) ? 'Active' : 'Deactive',
                'emp_status' => $emp->EmpStatus,
                'date_of_resignation' => ($emp->DateOfResignation && \App\Support\ZeroDate::has($emp->DateOfResignation)) ? $emp->DateOfResignation : null,
                'date_of_separation' => ($emp->DateOfSepration && \App\Support\ZeroDate::has($emp->DateOfSepration)) ? $emp->DateOfSepration : null,
                'department' => $emp->department_name,
                'designation' => $emp->designation_name,
                'grade' => $emp->grade_name,
                'email' => $emp->EmailId_Vnr,
                'mobile' => $emp->MobileNo_Vnr,
                'password' => $decryptedPassword,
            ];
        });

        $primary = $mapped->first();

        return response()->json([
            'success' => true,
            'emp_code' => $empCode,
            'total_matches' => $mapped->count(),
            'employee_id' => $primary['employee_id'],
            'password' => $primary['password'],
            'emp_name' => $primary['emp_name'],
            'company_id' => $primary['company_id'],
            'company_name' => $primary['company_name'],
            'status' => $primary['status'],
            'department' => $primary['department'],
            'designation' => $primary['designation'],
            'data' => $mapped->count() === 1 ? $primary : $mapped,
        ]);
    }

    public function decrypt($encryptedText)
    {
        $chunks = str_split($encryptedText, 3);
        $output = '';
        foreach ($chunks as $chunk) {
            $output .= $this->derandomized($chunk, $this->strcode);
        }

        return $output;
    }

    public function strsplt($text, $size = 1)
    {
        $chunks = [];
        $length = strlen($text);
        for ($i = 0; $i < $length; $i += $size) {
            $chunks[] = substr($text, $i, $size);
        }

        return $chunks;
    }

    public function derandomized($chunk, $strcode)
    {
        $arr = $this->strsplt($chunk, strlen($chunk) - 1);
        $output = '';
        for ($x = 0; $x < strlen($chunk) - 1; $x++) {
            $s = $this->key_locator(substr($arr[0], $x, 1), $strcode);
            $t = $this->key_locator($arr[1], $strcode);
            $newcode = $s - $t;
            if ($newcode < 0) {
                $newcode += count($strcode) - 1;
            }
            if ($newcode == 0 && $s != 0) {
                $newcode = count($strcode) - 1;
            }
            $output .= $strcode[$newcode];
        }

        return $output;
    }

    public function convert_keyto_value($thetext)
    {
        $output = '';
        $a = $this->add_random_key($thetext);
        while (in_array(count($this->strcode) - 1, $a)) {
            $a = $this->add_random_key($thetext);
        }
        for ($i = 0; $i < strlen($thetext) + 1; $i++) {
            $output .= $this->strcode[$a[$i]];
        }

        return $output;
    }

    public function add_random_key($thetext)
    {
        $newcode = [];
        $rnd = rand(1, count($this->strcode) - 2);
        for ($i = 0; $i < strlen($thetext); $i++) {
            $x = $this->key_locator(substr($thetext, $i, 1), $this->strcode);
            $temp = $x + $rnd;
            if ($temp > count($this->strcode) - 1) {
                $temp -= count($this->strcode) - 1;
            }
            $newcode[] = $temp;
        }
        $newcode[] = $rnd;

        return $newcode;
    }

    public function encrypt($thetext)
    {
        $output = '';
        $nstr = $this->strsplt($thetext, 2);
        for ($i = 0; $i < count($nstr); $i++) {
            $output .= $this->convert_keyto_value($nstr[$i]);
        }

        return $output;
    }

    public function key_locator($code, $strcode)
    {
        return array_search($code, $strcode) ?: 0;
    }

    /**
     * LOGIN-COMPANY-01 (14 Nov 2026): the sign-in page lists VNR's financial years. Each company numbers its years
     * differently (VNR 8 = VVNR 6 = 2026-27), so the year is matched by its label in the signed-in company; a year the
     * company does not have falls back to its current year (else its latest).
     */
    private function yearForCompany(int $postedYearId, int $companyId, bool $postedForCompany): int
    {
        if ($postedForCompany) {
            return $postedYearId;   // an older form that sent the company also sent that company's own year
        }
        try {
            $label = (string) \Illuminate\Support\Facades\DB::connection(DatabaseSwitcher::getExpenseConnection(1))
                ->table('financialyear')->where('YearId', $postedYearId)->value('Year');
            if ($companyId === 1 || in_array($companyId, [2, 4], true)) {
                return $postedYearId;
            }
            $db = \Illuminate\Support\Facades\DB::connection(DatabaseSwitcher::getExpenseConnection($companyId))->table('financialyear');
            if ($label !== '' && ($id = (clone $db)->where('Year', $label)->value('YearId'))) {
                return (int) $id;
            }
            $today = date('Y-m-d');

            return (int) ((clone $db)->where('FromDate', '<=', $today)->where('Todate', '>=', $today)->value('YearId')
                ?: (clone $db)->max('YearId') ?: $postedYearId);
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::warning('Sign-in year match failed: '.$e->getMessage());

            return $postedYearId;
        }
    }
}
