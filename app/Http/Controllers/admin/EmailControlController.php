<?php

namespace App\Http\Controllers\admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;

class EmailControlController extends Controller
{
    public function index()
    {
        $departments = DB::connection('hrims')
            ->table('core_departments')
            ->select('id', 'department_name')
            ->orderBy('department_name')
            ->get();

        $grades = DB::connection('hrims')
            ->table('core_grades')
            ->select('id', 'grade_name')
            ->orderBy('grade_name')
            ->get();

        return view('admin.email-control', compact('departments', 'grades'));
    }

    public function getData(Request $request)
    {
        $companyId = session('company_id', 1);
        $perPage = $request->input('per_page', 30);
        $page = $request->input('page', 1);

        $query = DB::connection('hrims')
            ->table('hrm_employee as e')
            ->join('hrm_employee_general as g', 'e.EmployeeID', '=', 'g.EmployeeID')
            ->leftJoin('core_departments as d', 'g.DepartmentId', '=', 'd.id')
            ->leftJoin('core_grades as gr', 'g.GradeId', '=', 'gr.id')
            ->leftJoin('core_designation as dg', 'g.DesigId', '=', 'dg.id')
            ->where('e.CompanyId', $companyId)
            ->where('e.EmpStatus', 'A');

        // Filters
        if ($request->filled('department_id')) {
            $query->where('g.DepartmentId', $request->department_id);
        }
        if ($request->filled('grade_id')) {
            $query->where('g.GradeId', $request->grade_id);
        }
        if ($request->filled('search')) {
            $s = $request->search;
            $query->where(function ($q) use ($s) {
                $q->where('e.EmpCode_New', 'like', "%$s%")
                  ->orWhereRaw("CONCAT(e.Fname,' ',COALESCE(e.Sname,''),' ',e.Lname) LIKE ?", ["%$s%"])
                  ->orWhere('g.EmailId_Vnr', 'like', "%$s%")
                  ->orWhere('gr.grade_name', 'like', "%$s%");
            });
        }

        $total = (clone $query)->count();

        $employees = $query->select(
            'e.EmployeeID',
            'e.EmpCode_New as emp_code',
            DB::raw("TRIM(CONCAT(e.Fname,' ',COALESCE(e.Sname,''),' ',e.Lname)) as employee_name"),
            'd.department_name',
            'gr.grade_name',
            'dg.designation_name',
            'g.EmailId_Vnr as email',
            'g.MobileNo_Vnr as mobile'
        )
        ->orderBy('e.EmpCode_New')
        ->offset(($page - 1) * $perPage)
        ->limit($perPage)
        ->get();

        // Get existing control records
        $controls = DB::table('eml_email_control')
            ->whereIn('employee_id', $employees->pluck('EmployeeID'))
            ->get()
            ->keyBy('employee_id');

        $result = $employees->map(function ($emp) use ($controls) {
            $ctrl = $controls->get($emp->EmployeeID);
            return [
                'employee_id' => $emp->EmployeeID,
                'emp_code' => $emp->emp_code,
                'employee_name' => $emp->employee_name,
                'department' => $emp->department_name,
                'grade' => $emp->grade_name,
                'designation' => $emp->designation_name,
                'email' => $emp->email,
                'mobile' => $emp->mobile,
                'is_active' => $ctrl ? (bool) $ctrl->is_active : true,
                'receive_submission_reminder' => $ctrl ? (bool) $ctrl->receive_submission_reminder : true,
                'receive_escalation' => $ctrl ? (bool) $ctrl->receive_escalation : true,
                'receive_receiving_pending' => $ctrl ? (bool) $ctrl->receive_receiving_pending : true,
                'receive_payment_notification' => $ctrl ? (bool) $ctrl->receive_payment_notification : true,
                'receive_approval_notification' => $ctrl ? (bool) $ctrl->receive_approval_notification : true,
                'reason' => $ctrl->reason ?? null,
            ];
        });

        return $this->jsonSuccess([
            'employees' => $result,
            'pagination' => [
                'total' => $total,
                'per_page' => (int) $perPage,
                'current_page' => (int) $page,
                'last_page' => (int) ceil($total / $perPage),
            ],
        ]);
    }

    public function update(Request $request)
    {
        $employeeId = $request->input('employee_id');
        $field = $request->input('field');
        $value = $request->input('value') ? 1 : 0;
        $reason = $request->input('reason', '');

        $allowedFields = ['is_active', 'receive_submission_reminder', 'receive_escalation',
            'receive_receiving_pending', 'receive_payment_notification', 'receive_approval_notification'];

        if (!in_array($field, $allowedFields)) {
            return $this->jsonError('Invalid field', 422);
        }

        DB::table('eml_email_control')->updateOrInsert(
            ['employee_id' => $employeeId],
            [
                $field => $value,
                'reason' => $reason ?: null,
                'updated_by' => Auth::id(),
                'updated_at' => now(),
                'created_at' => DB::raw('COALESCE(created_at, NOW())'),
            ]
        );

        return $this->jsonSuccess(null, 'Updated successfully');
    }

    public function bulkUpdate(Request $request)
    {
        $employeeIds = $request->input('employee_ids', []);
        $field = $request->input('field');
        $value = $request->input('value') ? 1 : 0;
        $reason = $request->input('reason', '');

        $allowedFields = ['is_active', 'receive_submission_reminder', 'receive_escalation',
            'receive_receiving_pending', 'receive_payment_notification', 'receive_approval_notification'];

        if (!in_array($field, $allowedFields) || empty($employeeIds)) {
            return $this->jsonError('Invalid request', 422);
        }

        foreach ($employeeIds as $empId) {
            DB::table('eml_email_control')->updateOrInsert(
                ['employee_id' => $empId],
                [
                    $field => $value,
                    'reason' => $reason ?: null,
                    'updated_by' => Auth::id(),
                    'updated_at' => now(),
                    'created_at' => DB::raw('COALESCE(created_at, NOW())'),
                ]
            );
        }

        return $this->jsonSuccess(null, count($employeeIds) . ' employees updated');
    }
}
