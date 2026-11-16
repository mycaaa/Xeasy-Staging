<?php

namespace App\Services\ClaimScore;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * CLAIM-SCORE-02: Finance & Forensics board figures - cuts at each stage, red flags, round amounts, stage speed and
 * payments, money-at-risk / approval-control / policy / evidence checks and repeated late submitters. Read only;
 * cached for 30 minutes per company and year.
 */
class FinanceForensics
{
    public function __construct(public int $companyId = 1, public ?int $yearId = null)
    {
        $this->yearId = $yearId ?: \App\Support\CurrentYear::id();
    }

    public function data(bool $fresh = false): array
    {
        $key = "claimscore:fin:{$this->companyId}:{$this->yearId}";
        if ($fresh) {
            Cache::forget($key);
        }

        return Cache::remember($key, 1800, fn () => $this->build());
    }

    private function safe(callable $f, $default = null)
    {
        try {
            return $f();
        } catch (\Throwable $e) {
            report($e);

            return $default;
        }
    }

    private function build(): array
    {
        $d = DB::connection('expense');
        $hdb = DB::connection('hrims')->getDatabaseName();
        $y = $this->yearId;
        $co = $this->companyId;
        $ec = "y{$y}_expenseclaims";
        $mf = "y{$y}_monthexpensefinal";
        $svc = new ClaimScoreService($co, $y);
        $months = $svc->months();
        $mIn = implode(',', $months);
        $W = "c.ClaimYearId=$y AND c.ClaimStatus NOT IN ('Deactivate','Deactivat','Draft') AND c.ClaimMonth IN ($mIn) AND e.CompanyId=$co";
        $J = "FROM $ec c JOIN $hdb.hrm_employee e ON e.EmployeeID=c.CrBy";
        $one = fn ($sql) => (array) ($d->selectOne($sql) ?? []);
        $all = fn ($sql) => array_map(fn ($r) => array_map(fn ($v) => is_numeric($v) ? $v + 0 : $v, (array) $r), $d->select($sql));
        $cut = "COUNT(*) n, SUM(c.FilledTAmt) fil, SUM(c.VerifyTAmt>0) ver, SUM(c.VerifyTAmt>0 AND c.VerifyTAmt<c.FilledTAmt) vcut,
          SUM(CASE WHEN c.VerifyTAmt>0 THEN c.FilledTAmt ELSE 0 END) vfil,
          SUM(CASE WHEN c.VerifyTAmt>0 AND c.VerifyTAmt<c.FilledTAmt THEN c.FilledTAmt-c.VerifyTAmt ELSE 0 END) vcutamt,
          SUM(c.ApprTAmt>0 AND c.ApprTAmt<c.VerifyTAmt) acut, SUM(CASE WHEN c.ApprTAmt>0 AND c.ApprTAmt<c.VerifyTAmt THEN c.VerifyTAmt-c.ApprTAmt ELSE 0 END) acutamt,
          SUM(c.FinancedTAmt>0 AND c.FinancedTAmt<c.ApprTAmt) fcut, SUM(CASE WHEN c.FinancedTAmt>0 AND c.FinancedTAmt<c.ApprTAmt THEN c.ApprTAmt-c.FinancedTAmt ELSE 0 END) fcutamt,
          SUM(c.FilledTAmt>=500 AND MOD(c.FilledTAmt,500)=0) r500, SUM(c.FilledTAmt>=100 AND MOD(c.FilledTAmt,100)=0) r100, SUM(c.FilledTAmt>=100) ge100";
        $out = ['generated' => now()->format('d-m-Y H:i'), 'months' => $months, 'monthNames' => ClaimScoreService::MONTH_NAMES];

        $out['monthly'] = $this->safe(fn () => $all("SELECT c.ClaimMonth m, $cut $J WHERE $W GROUP BY c.ClaimMonth ORDER BY FIELD(c.ClaimMonth, $mIn)"), []);
        $out['bytype'] = $this->safe(fn () => $all("SELECT c.ClaimId id, COALESCE(t.ClaimName, CONCAT('Type ', c.ClaimId)) name, $cut $J LEFT JOIN claimtype t ON t.ClaimId=c.ClaimId WHERE $W GROUP BY c.ClaimId, t.ClaimName ORDER BY fil DESC"), []);
        $nm = "TRIM(CONCAT_WS(' ', e.Fname, NULLIF(e.Sname,''), e.Lname))";
        $out['emp'] = $this->safe(fn () => $all("SELECT c.CrBy e, e.EmpCode c, $nm nm, IFNULL(dp.department_name,'') d, $cut $J LEFT JOIN $hdb.hrm_employee_general g ON g.EmployeeID=c.CrBy LEFT JOIN $hdb.core_departments dp ON dp.id=g.DepartmentId WHERE $W GROUP BY c.CrBy, e.EmpCode, e.Fname, e.Sname, e.Lname, dp.department_name"), []);
        $out['ver'] = $this->safe(fn () => $all("SELECT c.VerifyBy e, TRIM(CONCAT_WS(' ', v.Fname, NULLIF(v.Sname,''), v.Lname)) nm, $cut $J LEFT JOIN $hdb.hrm_employee v ON v.EmployeeID=c.VerifyBy WHERE $W AND c.VerifyBy>0 GROUP BY c.VerifyBy, v.Fname, v.Sname, v.Lname HAVING n>=20 ORDER BY n DESC"), []);

        // red flags
        $out['dups'] = $this->safe(function () use ($all, $J, $W, $nm) {
            $g = $all("SELECT c.CrBy e, $nm nm, c.ClaimId t, DATE(c.BillDate) bd, c.FilledTAmt a, COUNT(*) k, GROUP_CONCAT(c.ExpId ORDER BY c.ExpId) ids $J
                WHERE $W AND c.ClaimId<>7 AND c.FilledTAmt>=200 AND c.BillDate>'2000-01-01' GROUP BY c.CrBy, e.Fname, e.Sname, e.Lname, c.ClaimId, DATE(c.BillDate), c.FilledTAmt HAVING COUNT(*)>1");
            usort($g, fn ($a, $b) => ($b['k'] - 1) * $b['a'] <=> ($a['k'] - 1) * $a['a']);

            return ['groups' => count($g), 'extra' => array_sum(array_map(fn ($x) => $x['k'] - 1, $g)), 'extra_amount' => array_sum(array_map(fn ($x) => ($x['k'] - 1) * $x['a'], $g)), 'top' => array_slice($g, 0, 25)];
        }, ['groups' => 0, 'extra' => 0, 'extra_amount' => 0, 'top' => []]);
        $out['timing'] = $this->safe(fn () => $one("SELECT SUM(c.BillDate>'2000-01-01' AND DATE(c.BillDate)>DATE(c.CrDate)) future_bill, SUM(c.BillDate>'2000-01-01' AND DATEDIFF(c.CrDate,c.BillDate)>60) old_bill,
            SUM(c.BillDate>'2000-01-01' AND DAYOFWEEK(c.BillDate)=1) sunday_bill, COUNT(*) n $J WHERE $W"), []);
        $out['outliers'] = $this->safe(function () use ($d, $J, $W, $nm) {
            $med = [];
            foreach ($d->select("SELECT c.ExpId, c.CrBy, $nm nm, c.ClaimId, c.FilledTAmt, DATE(c.BillDate) bd $J WHERE $W AND c.FilledTAmt>0") as $r) {
                $med[$r->CrBy.'_'.$r->ClaimId][] = [(float) $r->FilledTAmt, (int) $r->ExpId, $r->bd, $r->nm];
            }
            $o = [];
            foreach ($med as $k => $list) {
                if (count($list) < 5) {
                    continue;
                }
                $a = array_column($list, 0);
                sort($a);
                $mid = $a[intdiv(count($a), 2)];
                foreach ($list as $x) {
                    if ($mid > 0 && $x[0] >= 2000 && $x[0] > 3 * $mid) {
                        $o[] = ['e' => (int) explode('_', $k)[0], 'nm' => $x[3], 't' => (int) explode('_', $k)[1], 'a' => $x[0], 'med' => $mid, 'x' => round($x[0] / $mid, 1), 'id' => $x[1], 'bd' => $x[2]];
                    }
                }
            }
            usort($o, fn ($p, $q) => $q['a'] <=> $p['a']);

            return ['count' => count($o), 'amount' => array_sum(array_column($o, 'a')), 'top' => array_slice($o, 0, 25)];
        }, ['count' => 0, 'amount' => 0, 'top' => []]);

        // stage speed and payments
        $out['finstage'] = $this->safe(fn () => $all("SELECT month m, stage_key k, ROUND(AVG(days_taken),2) days, ROUND(AVG(stars),2) stars, COUNT(*) n FROM claim_rating_results
            WHERE company_id=$co AND year_id=$y AND month IN ($mIn) AND stage_key IN ('receive_to_verify','verify_to_approve','approve_to_finance','finance_to_payment') GROUP BY month, stage_key"), []);
        $out['finpeople'] = $this->safe(fn () => $all("SELECT rated_person_id p, rated_person_name nm, stage_key k, ROUND(AVG(days_taken),2) days, ROUND(AVG(stars),2) stars, COUNT(*) n FROM claim_rating_results
            WHERE company_id=$co AND year_id=$y AND month IN ($mIn) AND stage_key IN ('approve_to_finance','finance_to_payment','receive_to_verify') AND rated_person_id>0
            GROUP BY rated_person_id, rated_person_name, stage_key HAVING COUNT(*)>=50 ORDER BY n DESC"), []);
        $out['payments'] = $this->safe(fn () => $all("SELECT f.Month m, COUNT(*) months, SUM(f.Finance_Date>'2000-01-01') financed,
            SUM(f.Finance_Date>'2000-01-01' AND (f.payment_date IS NULL OR f.payment_date<'2000-01-01')) unpaid,
            SUM(CASE WHEN f.Finance_Date>'2000-01-01' AND (f.payment_date IS NULL OR f.payment_date<'2000-01-01') THEN f.Finance_Amount ELSE 0 END) unpaid_amt,
            SUM(f.payment_date>'2000-01-01' AND (f.utr_number IS NULL OR f.utr_number='')) paid_no_utr,
            ROUND(AVG(CASE WHEN f.Approved_Date>'2000-01-01' AND f.Finance_Date>=f.Approved_Date THEN DATEDIFF(f.Finance_Date,f.Approved_Date) END),1) appr_to_fin,
            ROUND(AVG(CASE WHEN f.Finance_Date>'2000-01-01' AND f.payment_date>=f.Finance_Date THEN DATEDIFF(f.payment_date,f.Finance_Date) END),1) fin_to_pay,
            SUM(f.Finance_Amount) fin_amt FROM $mf f JOIN $hdb.hrm_employee e ON e.EmployeeID=f.EmployeeID WHERE f.YearId=$y AND f.Month IN ($mIn) AND e.CompanyId=$co
            GROUP BY f.Month ORDER BY FIELD(f.Month, $mIn)"), []);

        // controls
        $c = [];
        // unsettled payments: claim months and advances marked Unsettled by the bank reconciliation (the app's own status),
        // plus open rows of the older bank-file mismatch table
        $c['unsettled'] = [
            'claims' => $this->safe(fn () => $one("SELECT COUNT(*) n, SUM(f.Finance_Amount) amt FROM $mf f JOIN $hdb.hrm_employee e ON e.EmployeeID=f.EmployeeID WHERE f.YearId=$y AND e.CompanyId=$co AND f.payment_status='Unsettled'"), []),
            'advances' => $this->safe(fn () => $one("SELECT COUNT(*) n, SUM(COALESCE(NULLIF(finance_approved_amount,0), amount)) amt FROM advance_request_activities WHERE deleted_at IS NULL AND payment_status='Unsettled'"), []),
            'bankfile' => $this->safe(fn () => $one("SELECT SUM(status='Unsettled') n, SUM(status='Unsettled' AND ABS(excel_amount-db_amount)>1) mism FROM unsettled_payments WHERE IFNULL(company_id,$co)=$co"), []),
        ];
        $c['advances'] = $this->safe(fn () => $one("SELECT COUNT(*) n, SUM(remaining_balance) bal, SUM(payment_date < DATE_SUB(CURDATE(), INTERVAL 60 DAY)) old60,
            SUM(CASE WHEN payment_date < DATE_SUB(CURDATE(), INTERVAL 60 DAY) THEN remaining_balance END) old60_bal FROM advance_request_activities WHERE deleted_at IS NULL AND payment_date IS NOT NULL AND remaining_balance>0"), []);
        $c['utr'] = $this->safe(fn () => $all("SELECT f.utr_number u, COUNT(*) k, SUM(f.payment_amount) amt FROM $mf f WHERE f.YearId=$y AND f.utr_number<>'' GROUP BY f.utr_number HAVING COUNT(DISTINCT f.EmployeeID)>1"), []);
        $c['pay_order'] = $this->safe(fn () => $one("SELECT SUM(payment_date>'2000-01-01' AND (RecevingDate IS NULL OR RecevingDate<'2000-01-01')) no_hardcopy,
            SUM(payment_date>'2000-01-01' AND Approved_Date>'2000-01-01' AND payment_date<Approved_Date) before_appr FROM $mf WHERE YearId=$y AND Month IN ($mIn)"), []);
        $c['focus'] = $this->safe(fn () => $one('SELECT COUNT(*) runs, SUM(ABS(difference)>1) diff, SUM(ABS(difference)) amt FROM focus_ledger_recon_logs WHERE IFNULL(company_id,'.$co.')='.$co), []);
        $c['raised'] = $this->safe(fn () => $one("SELECT SUM(c.VerifyTAmt>c.FilledTAmt AND c.VerifyTAmt>0) ver_up, SUM(CASE WHEN c.VerifyTAmt>c.FilledTAmt AND c.VerifyTAmt>0 THEN c.VerifyTAmt-c.FilledTAmt END) ver_amt,
            SUM(c.ApprTAmt>c.VerifyTAmt AND c.VerifyTAmt>0) appr_up, SUM(CASE WHEN c.ApprTAmt>c.VerifyTAmt AND c.VerifyTAmt>0 THEN c.ApprTAmt-c.VerifyTAmt END) appr_amt,
            SUM(c.FinancedTAmt>c.ApprTAmt AND c.ApprTAmt>0) fin_up, SUM(CASE WHEN c.FinancedTAmt>c.ApprTAmt AND c.ApprTAmt>0 THEN c.FinancedTAmt-c.ApprTAmt END) fin_amt $J WHERE $W"), []);
        $c['approvers'] = $this->safe(fn () => $all("SELECT c.ApprBy p, TRIM(CONCAT_WS(' ', a.Fname, NULLIF(a.Sname,''), a.Lname)) nm, COUNT(*) n, SUM(c.ApprTAmt<c.VerifyTAmt) cut, SUM(DATE(c.ApprDate)=DATE(c.VerifyDate)) sameday, MAX(x.k) maxday
            $J LEFT JOIN $hdb.hrm_employee a ON a.EmployeeID=c.ApprBy
            JOIN (SELECT ApprBy, MAX(k) k FROM (SELECT ApprBy, DATE(ApprDate) dd, COUNT(*) k FROM $ec WHERE ClaimYearId=$y AND ApprBy>0 GROUP BY ApprBy, DATE(ApprDate)) z GROUP BY ApprBy) x ON x.ApprBy=c.ApprBy
            WHERE $W AND c.ApprBy>0 GROUP BY c.ApprBy, a.Fname, a.Sname, a.Lname HAVING n>=1000 AND cut<=n*0.005 ORDER BY n DESC LIMIT 15"), []);
        $c['chain'] = $this->safe(fn () => $one("SELECT COUNT(*) n, SUM(c.ApprTAmt) amt, SUM(c.ApprBy=g2.RepEmployeeID) skip $J JOIN $hdb.hrm_employee_general g ON g.EmployeeID=c.CrBy LEFT JOIN $hdb.hrm_employee_general g2 ON g2.EmployeeID=g.RepEmployeeID WHERE $W AND c.ApprBy>0 AND c.ApprBy<>g.RepEmployeeID"), []);
        $EL = "(SELECT x.* FROM $hdb.hrm_employee_eligibility x JOIN (SELECT EmployeeID, MAX(EligibilityId) mid FROM $hdb.hrm_employee_eligibility GROUP BY EmployeeID) mm ON mm.mid=x.EligibilityId)";
        $c['lodging'] = $this->safe(fn () => $one("SELECT COUNT(*) n, SUM(c.ApprTAmt-el.Lodging_CategoryA) over_amt, SUM(c.ApprTAmt>2*el.Lodging_CategoryA) over2x, (SELECT COUNT(*) $J WHERE $W AND c.ClaimId=1) total
            $J JOIN $EL el ON el.EmployeeID=c.CrBy WHERE $W AND c.ClaimId=1 AND el.Lodging_CategoryA>0 AND c.ApprTAmt>el.Lodging_CategoryA"), []);
        $c['leave'] = $this->safe(fn () => $one("SELECT COUNT(*) n, SUM(c.FilledTAmt) amt, COUNT(DISTINCT c.CrBy) emps, SUM(c.ClaimId=7) veh, SUM(CASE WHEN c.ClaimId=7 THEN c.TotKm END) km
            $J JOIN $hdb.hrm_employee_attendance a ON a.EmployeeID=c.CrBy AND a.AttDate=DATE(c.BillDate) WHERE $W AND a.AttValue IN ('A','CL','EL','SL','PL','FL','TL','ML')"), []);
        $c['sunday_da'] = $this->safe(fn () => $one("SELECT COUNT(*) n, SUM(c.FilledTAmt) amt $J WHERE $W AND c.ClaimId=19 AND DAYOFWEEK(c.BillDate)=1"), []);
        $c['after_exit'] = $this->safe(fn () => $one("SELECT COUNT(*) n, SUM(c.FilledTAmt) amt, COUNT(DISTINCT c.CrBy) emps $J WHERE $W AND e.DateOfSepration>'2000-01-01' AND c.BillDate>'2000-01-01' AND DATE(c.BillDate)>e.DateOfSepration"), []);
        $noimg = "IFNULL(c.Img_1,'')='' AND IFNULL(c.Img_2,'')='' AND IFNULL(c.Img_3,'')='' AND IFNULL(c.Img_4,'')='' AND IFNULL(c.Img_5,'')='' AND IFNULL(c.Img1_offline,'')='' AND IFNULL(c.Img2_offline,'')='' AND IFNULL(c.Img3_offline,'')='' AND IFNULL(c.Pdf1_offline,'')='' AND IFNULL(c.Pdf2_offline,'')=''";
        $c['noimg'] = $this->safe(fn () => $one("SELECT COUNT(*) n, SUM(c.FilledTAmt) amt, COUNT(DISTINCT c.CrBy) emps $J WHERE $W AND c.ClaimId<>7 AND c.FilledTAmt>=500 AND $noimg"), []);
        $c['split'] = $this->safe(fn () => $one("SELECT COUNT(*) grp, SUM(k) bills, SUM(s) amt FROM (SELECT c.CrBy, c.ClaimId, DATE(c.BillDate) dd, COUNT(*) k, SUM(c.FilledTAmt) s $J WHERE $W AND c.ClaimId<>7 AND c.FilledTAmt BETWEEN 1 AND 499 GROUP BY c.CrBy, c.ClaimId, DATE(c.BillDate) HAVING COUNT(*)>=4) z"), []);
        $c['edits'] = $this->safe(fn () => Schema::connection('expense')->hasTable('claim_amount_change_logs') ? $d->table('claim_amount_change_logs')->count() : 0, 0);
        $out['controls'] = $c;

        // repeated late submitters (month not submitted by the deadline)
        $out['late'] = $this->safe(function () use ($svc) {
            $closed = array_values(array_filter($svc->months(), fn ($m) => ! $svc->isOpen($m)));
            $rows = [];
            foreach ($svc->raw() as $e) {
                $late = [];
                $sub = 0;
                foreach ($closed as $m) {
                    if (! isset($e['m'][$m]) || ! array_key_exists('onTime', $e['m'][$m])) {
                        continue;
                    }
                    $sub++;
                    if ($e['m'][$m]['onTime'] === 0) {
                        $late[] = $m;
                    }
                }
                if (count($late) < 2) {
                    continue;
                }
                $run = 0;
                foreach (array_reverse($closed) as $m) {
                    if (in_array($m, $late, true)) {
                        $run++;
                    } elseif (isset($e['m'][$m]['onTime'])) {
                        break;
                    }
                }
                $rows[] = ['id' => $e['p']['id'], 'c' => $e['p']['c'], 'nm' => $e['p']['nm'], 'd' => $e['p']['d'], 'g' => $e['p']['g'],
                    'mgr' => $e['p']['mg'], 'late' => count($late), 'of' => $sub, 'months' => $late, 'run' => $run];
            }
            usort($rows, fn ($a, $b) => [$b['late'], $b['run']] <=> [$a['late'], $a['run']]);
            $mg = array_unique(array_filter(array_column($rows, 'mgr')));
            $names = $mg ? DB::connection('hrims')->table('hrm_employee')->whereIn('EmployeeID', $mg)->get(['EmployeeID', 'Fname', 'Sname', 'Lname'])
                ->mapWithKeys(fn ($p) => [$p->EmployeeID => trim(preg_replace('/\s+/', ' ', "$p->Fname $p->Sname $p->Lname"))])->all() : [];
            foreach ($rows as &$r) {
                $r['mgrName'] = $names[$r['mgr']] ?? '';
            }

            return ['closed' => $closed, 'rows' => $rows];
        }, ['closed' => [], 'rows' => []]);
        $out['types'] = $this->safe(fn () => $d->table('claimtype')->pluck('ClaimName', 'ClaimId')->all(), []);

        return $out;
    }
}
