<?php

namespace App\Services\Reports;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;

/**
 * VEH-RATE-ALERT-01 (HR, 11 Nov 2026): when an active employee's 2W or 4W rate is zero, mail HR (Super Admin in
 * Cc) asking for the rate; repeat every claim_rules.vehicle_rate_alert.repeat_days (3) days until the rate is
 * entered; log every mail. The alert closes by itself once the rate is no longer zero (or the employee leaves).
 * Employees who are already at zero are left as they are (HR: zero counts as the rate for now) and get a
 * fresh first notification on the first run.
 */
class VehicleRateAlerts
{
    public function run(int $companyId = 1, bool $dryRun = false): array
    {
        $gaps = collect((new VehicleRateGaps())->rows($companyId))->filter(fn ($r) => $r['rate_missing']);
        $ids = $this->employeeIds($gaps->pluck('emp_code')->all(), $companyId);
        $now = now();
        $repeat = (int) config('claim_rules.vehicle_rate_alert.repeat_days', 3);
        $out = ['first' => 0, 'reminder' => 0, 'waiting' => 0, 'resolved' => 0, 'mails' => []];
        $seen = [];
        foreach ($gaps as $g) {
            $empId = $ids[$g['emp_code']] ?? null;
            if (! $empId) {
                continue;
            }
            $seen[] = $empId;
            $alert = DB::table('vehicle_rate_alerts')->where('company_id', $companyId)->where('employee_id', $empId)->where('status', 'open')->first();
            if ($alert && $alert->last_sent_at && $now->lt(\Carbon\Carbon::parse($alert->last_sent_at)->addDays($repeat))) {
                $out['waiting']++;
                if (! $dryRun && $alert->issue !== $g['issues']) {
                    DB::table('vehicle_rate_alerts')->where('id', $alert->id)->update(['issue' => $g['issues'], 'updated_at' => $now]);
                }
                continue;
            }
            $kind = $alert ? 'reminder' : 'first';
            $out[$kind]++;
            if ($dryRun) {
                $out['mails'][] = [$kind, $g['emp_code'], $g['name'], $g['issues']];
                continue;
            }
            $alertId = $alert->id ?? DB::table('vehicle_rate_alerts')->insertGetId([
                'company_id' => $companyId, 'employee_id' => $empId, 'emp_code' => $g['emp_code'], 'employee_name' => $g['name'],
                'department' => $g['department'], 'issue' => $g['issues'], 'status' => 'open', 'first_detected_at' => $now,
                'created_at' => $now, 'updated_at' => $now,
            ]);
            [$status, $err, $to, $cc] = $this->send($g, $kind, $alert ? (int) $alert->send_count + 1 : 1);
            DB::table('vehicle_rate_alert_mails')->insert([
                'alert_id' => $alertId, 'company_id' => $companyId, 'employee_id' => $empId, 'emp_code' => $g['emp_code'],
                'employee_name' => $g['name'], 'issue' => $g['issues'], 'kind' => $kind, 'to_list' => implode(', ', $to),
                'cc_list' => mb_substr(implode(', ', $cc), 0, 1000), 'status' => $status, 'error' => $err ? mb_substr($err, 0, 500) : null, 'sent_at' => $now,
            ]);
            if ($status === 'sent') {
                DB::table('vehicle_rate_alerts')->where('id', $alertId)->update([
                    'last_sent_at' => $now, 'send_count' => DB::raw('send_count + 1'), 'issue' => $g['issues'], 'updated_at' => $now,
                ]);
            }
            $out['mails'][] = [$kind, $g['emp_code'], $g['name'], $status];
        }
        // rate entered (or no longer active): close the alert
        $q = DB::table('vehicle_rate_alerts')->where('company_id', $companyId)->where('status', 'open')->whereNotIn('employee_id', $seen ?: [0]);
        $out['resolved'] = $dryRun ? $q->count() : $q->update(['status' => 'resolved', 'resolved_at' => $now, 'updated_at' => $now]);

        return $out;
    }

    private function employeeIds(array $codes, int $companyId): array
    {
        return DB::connection(\App\Helpers\DatabaseSwitcher::getHrimsConnection($companyId))->table('hrm_employee')->where('EmpStatus', 'A')
            ->whereIn('EmpCode', $codes ?: ['-'])->pluck('EmployeeID', 'EmpCode')->map(fn ($v) => (int) $v)->all();
    }

    /** HR in To, every Super Admin in Cc (HR decision 11 Nov). @return array{0:string,1:?string,2:array,3:array} */
    private function send(array $g, string $kind, int $nth): array
    {
        $to = array_values(array_filter(array_map('trim', explode(',', (string) config('claim_rules.vehicle_rate_report_to', '')))));
        $cc = DB::table('users as u')->join('model_has_roles as m', function ($j) {
            $j->on('m.model_id', '=', 'u.id')->where('m.model_type', 'App\\Models\\User');
        })->join('roles as r', 'r.id', '=', 'm.role_id')->where('r.name', 'Super Admin')->where('u.status', 1)
            ->whereNotNull('u.email')->where('u.email', 'like', '%@%')->pluck('u.email')->unique()->values()->all();
        $cc = array_values(array_diff($cc, $to));
        if (! $to) {
            return ['no_recipient', 'VEHICLE_RATE_REPORT_TO is not set', $to, $cc];
        }
        $subject = ($kind === 'first' ? '' : "Reminder {$nth}: ")."Vehicle rate missing - {$g['emp_code']} {$g['name']}";
        $body = "Dear HR,\n\nThe running-km rate of this active employee is zero in HRIMS, so their own-vehicle claims are paid at Rs 0. Please assign the applicable rate at the earliest.\n\n"
            ."Employee: {$g['emp_code']} - {$g['name']}\nDepartment: {$g['department']}\nGrade: {$g['grade']}\nVehicle policy: {$g['policy']}\n"
            .'2W rate: '.($g['rate_2w'] ?: 0).'   4W rate: '.($g['rate_4w'] ?: 0)."\nWhat is missing: {$g['issues']}\n"
            ."Running-km claims this year: 2W {$g['claims_2w']}, 4W {$g['claims_4w']} (at Rs 0: {$g['claims_at_zero']}); last bill {$g['last_bill']}\n\n"
            .'This mail repeats every '.config('claim_rules.vehicle_rate_alert.repeat_days', 3)." days until the rate is entered.\n\nXeasy";
        try {
            Mail::raw($body, function ($m) use ($to, $cc, $subject) {
                $m->to($to)->subject($subject);
                if ($cc) {
                    $m->cc($cc);
                }
            });

            return ['sent', null, $to, $cc];
        } catch (\Throwable $e) {
            return ['failed', $e->getMessage(), $to, $cc];
        }
    }
}
