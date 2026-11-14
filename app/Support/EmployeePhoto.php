<?php

namespace App\Support;

use Illuminate\Support\Facades\DB;

/**
 * PHOTO-01 (14 Nov 2026): employee photos are kept per company (Employee_Image/<company>/<code>.jpg) and employee
 * codes repeat across companies (VNR 1204 and VVNR 1204 are different people). The photo folder must be the
 * company the employee belongs to in HR, not the company someone signed in to - signing in to VVNR showed the
 * VVNR employee with the same code.
 */
class EmployeePhoto
{
    public static function url(?string $empCode, ?int $hrCompanyId): string
    {
        $code = trim((string) $empCode);
        if ($code === '') {
            return asset('custom/no_user.png');
        }

        return config('app.employee_photo_base').'/'.((int) $hrCompanyId ?: 1).'/'.rawurlencode($code).'.jpg';
    }

    /** HR company of an employee (hrm_employee.CompanyId), null when unknown. */
    public static function hrCompany(int $employeeId): ?int
    {
        if ($employeeId <= 0) {
            return null;
        }
        try {
            $c = DB::connection('hrims')->table('hrm_employee')->where('EmployeeID', $employeeId)->value('CompanyId');

            return $c ? (int) $c : null;
        } catch (\Throwable $e) {
            return null;
        }
    }

    /** Photo of the signed-in person (looked up once per session). */
    public static function forSession(): string
    {
        if (session('is_guest')) {
            return session('profile_img') ?: asset('assets/images/users/avatar-1.jpg');
        }
        $url = session('profile_img_v2');
        if (! $url) {
            $company = self::hrCompany((int) session('employee_id')) ?? (int) session('company_id', 1);
            $url = self::url((string) session('emp_code'), $company);
            session(['profile_img_v2' => $url, 'profile_img' => $url]);
        }

        return $url;
    }
}
