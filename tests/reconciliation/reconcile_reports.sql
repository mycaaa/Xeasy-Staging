-- =====================================================================================
-- reconcile_reports.sql  -  Laravel (xeasy_laravel) ledger / report / export reconciliation
-- Database: expense (3-Oct-2026 production copy).  Aggregates only - no personal data.
-- Run: mariadb expense < reconcile_reports.sql
-- Each block states the report it reconciles and the expected result after the fixes in
-- /home/claude/audit/laravel_fix (findings: /home/claude/audit/findings/LV_reports_fix.md).
-- Figures quoted in comments are the 3-Oct values.
-- Year tables: y7_ = FY 2025-26, y8_ = FY 2026-27.
-- =====================================================================================

-- ---------------------------------------------------------------------------------
-- R1 (LR-04) employee_ledger_balances integrity
--   before repair: 242 rows double-encoded (237 y7 + 5 y8), closing_balance 0 on all 276 rows
--   after `php artisan ledger:repair-balances`: 0 double-encoded, closing_balance set on the
--   latest row of each employee (256 non-zero, total 1,27,49,648)
-- ---------------------------------------------------------------------------------
SELECT year_id, company_id, COUNT(*) AS ledger_rows,
       SUM(JSON_VALID(remaining_balances_json) AND JSON_TYPE(remaining_balances_json) = 'STRING') AS json_double_encoded,
       SUM(JSON_VALID(remaining_balances_json) AND JSON_TYPE(remaining_balances_json) = 'OBJECT') AS json_ok,
       SUM(closing_balance <> 0) AS closing_written, ROUND(SUM(closing_balance)) AS closing_total,
       ROUND(SUM(balance)) AS imported_opening_balance
FROM employee_ledger_balances WHERE deleted_at IS NULL GROUP BY year_id, company_id;

-- ---------------------------------------------------------------------------------
-- R2 (LR-01/LR-02) Ledger Summary components, all employees with a ledger row or an advance
--   expected (engine, after fix): imprest 91,00,000 ; opening advances 24,19,610 ;
--   advances paid 2,86,07,747 ; adjustments applied 2,70,30,709 ; advance balance 39,96,648 ;
--   total 1,30,96,648 (opening + paid - adjustments = advance balance, per employee).
--   The engine itself is PHP (FIFO); work/lv_reports_harness/ledger_batch2.php prints it.
--   This SQL gives the source-side control totals the engine must tie to.
-- ---------------------------------------------------------------------------------
SELECT
  (SELECT ROUND(SUM(COALESCE(fixed_imprest_amt_1,0)+COALESCE(fixed_imprest_amt_2,0)))
     FROM employee_ledger_balances l WHERE deleted_at IS NULL
      AND id = (SELECT MAX(id) FROM employee_ledger_balances x WHERE x.employee_id = l.employee_id AND x.deleted_at IS NULL)) AS imprest_latest_row,
  (SELECT ROUND(SUM(COALESCE(advance_amt_1,0)+COALESCE(advance_amt_2,0)+COALESCE(advance_amt_3,0)+COALESCE(advance_amt_4,0)))
     FROM employee_ledger_balances l WHERE deleted_at IS NULL
      AND id = (SELECT MAX(id) FROM employee_ledger_balances x WHERE x.employee_id = l.employee_id AND x.deleted_at IS NULL)) AS opening_advance_slots,
  (SELECT ROUND(SUM(COALESCE(NULLIF(ara.payment_amount,0), ara.amount)))
     FROM advance_requests ar JOIN advance_request_employees are ON are.advance_request_id = ar.id
     JOIN advance_request_activities ara ON ara.advance_request_employee_id = are.id
    WHERE ara.payment_status IN ('Paid','Unsettled')
      AND (ar.is_imprest = 0 OR ar.is_imprest IS NULL) AND (ara.is_imprest = 0 OR ara.is_imprest IS NULL)
      AND (ara.purpose NOT LIKE '%imprest%' OR ara.purpose IS NULL)
      AND ar.deleted_at IS NULL AND are.deleted_at IS NULL AND ara.deleted_at IS NULL) AS advances_paid,
  (SELECT ROUND(SUM(total_adjustment)) FROM (SELECT total_adjustment, Fin_PayDate, Fin_AppDate FROM y7_monthexpensefinal
            UNION ALL SELECT total_adjustment, Fin_PayDate, Fin_AppDate FROM y8_monthexpensefinal) m
    WHERE total_adjustment > 0 AND (Fin_PayDate >= '2026-02-01' OR Fin_AppDate >= '2026-02-01')) AS adjustments_since_golive;

-- Opening advances that also exist as advance requests (to show they are not double counted)
-- result: 69 slots, 10 coincidental amount matches, 0 amount+date matches, 66 dated before go-live
WITH slots AS (
  SELECT employee_id, advance_amt_1 amt, advance_date_1 d FROM employee_ledger_balances WHERE advance_amt_1 > 0
  UNION ALL SELECT employee_id, advance_amt_2, advance_date_2 FROM employee_ledger_balances WHERE advance_amt_2 > 0
  UNION ALL SELECT employee_id, advance_amt_3, advance_date_3 FROM employee_ledger_balances WHERE advance_amt_3 > 0
  UNION ALL SELECT employee_id, advance_amt_4, advance_date_4 FROM employee_ledger_balances WHERE advance_amt_4 > 0),
ara AS (SELECT are.employee_id, COALESCE(ara.payment_amount, ara.amount) amt, ara.payment_date d
          FROM advance_request_activities ara JOIN advance_request_employees are ON are.id = ara.advance_request_employee_id
          JOIN advance_requests ar ON ar.id = are.advance_request_id
         WHERE ara.payment_status IN ('Paid','Unsettled') AND ara.deleted_at IS NULL AND are.deleted_at IS NULL AND ar.deleted_at IS NULL)
SELECT COUNT(*) slots, ROUND(SUM(amt)) amount,
       SUM(EXISTS(SELECT 1 FROM ara WHERE ara.employee_id = s.employee_id AND ABS(ara.amt - s.amt) < 1)) match_amount,
       SUM(EXISTS(SELECT 1 FROM ara WHERE ara.employee_id = s.employee_id AND ABS(ara.amt - s.amt) < 1 AND ara.d = s.d)) match_amount_date,
       SUM(s.d < '2026-02-01' OR s.d = '0000-00-00') before_golive
FROM slots s;

-- ---------------------------------------------------------------------------------
-- R3 (LR-02) the OLD as-of-date formula (baseline ledgerSummaryAsOfDate) as of 2026-10-03
--   result: 384 employees, total 1,03,67,000 vs the old current screen 1,28,72,148 (same day).
--   After the fix both use the engine: 1,30,96,648.
-- ---------------------------------------------------------------------------------
SET @asof = '2026-10-03';
WITH emp AS (SELECT employee_id id FROM employee_ledger_balances UNION SELECT employee_id FROM advance_request_employees),
lb AS (SELECT l.* FROM employee_ledger_balances l WHERE l.id = (SELECT MAX(id) FROM employee_ledger_balances x WHERE x.employee_id = l.employee_id)),
imp AS (SELECT employee_id,
   (CASE WHEN fixed_imprest_amt_1 > 0 AND (fixed_imprest_date_1 IS NULL OR fixed_imprest_date_1 = '0000-00-00' OR fixed_imprest_date_1 <= @asof) THEN fixed_imprest_amt_1 ELSE 0 END)
  +(CASE WHEN fixed_imprest_amt_2 > 0 AND (fixed_imprest_date_2 IS NULL OR fixed_imprest_date_2 = '0000-00-00' OR fixed_imprest_date_2 <= @asof) THEN fixed_imprest_amt_2 ELSE 0 END) imprest,
   (CASE WHEN advance_amt_1 > 0 AND (advance_date_1 IS NULL OR advance_date_1 = '0000-00-00' OR advance_date_1 <= @asof) THEN advance_amt_1 ELSE 0 END)
  +(CASE WHEN advance_amt_2 > 0 AND (advance_date_2 IS NULL OR advance_date_2 = '0000-00-00' OR advance_date_2 <= @asof) THEN advance_amt_2 ELSE 0 END)
  +(CASE WHEN advance_amt_3 > 0 AND (advance_date_3 IS NULL OR advance_date_3 = '0000-00-00' OR advance_date_3 <= @asof) THEN advance_amt_3 ELSE 0 END)
  +(CASE WHEN advance_amt_4 > 0 AND (advance_date_4 IS NULL OR advance_date_4 = '0000-00-00' OR advance_date_4 <= @asof) THEN advance_amt_4 ELSE 0 END) opening FROM lb),
paid AS (SELECT are.employee_id, SUM(COALESCE(ara.finance_approved_amount, ara.payment_amount, ara.final_amount, ara.amount)) paid
  FROM advance_requests ar JOIN advance_request_employees are ON are.advance_request_id = ar.id JOIN advance_request_activities ara ON ara.advance_request_employee_id = are.id
  WHERE ara.payment_status IN ('Paid','Unsettled') AND (ara.payment_date IS NULL OR ara.payment_date = '0000-00-00' OR ara.payment_date <= @asof)
    AND (ar.is_imprest = 0 OR ar.is_imprest IS NULL) AND (ara.is_imprest = 0 OR ara.is_imprest IS NULL) AND (ara.purpose NOT LIKE '%imprest%' OR ara.purpose IS NULL)
    AND ar.deleted_at IS NULL AND are.deleted_at IS NULL AND ara.deleted_at IS NULL GROUP BY are.employee_id),
mef AS (SELECT EmployeeID, Status, payment_status, total_adjustment, Fin_PayDate, Fin_AppDate FROM y7_monthexpensefinal
        UNION ALL SELECT EmployeeID, Status, payment_status, total_adjustment, Fin_PayDate, Fin_AppDate FROM y8_monthexpensefinal),
adj AS (SELECT EmployeeID, SUM(total_adjustment) adj FROM mef
  WHERE (payment_status IN ('ConfirmedPayment','InstructedPayment','Unsettled') OR Status = 'Closed' OR (Fin_PayDate IS NOT NULL AND Fin_PayDate <> '0000-00-00' AND total_adjustment > 0))
    AND total_adjustment > 0
    AND ((Fin_PayDate IS NOT NULL AND Fin_PayDate <> '0000-00-00' AND Fin_PayDate <= @asof)
      OR ((Fin_PayDate IS NULL OR Fin_PayDate = '0000-00-00') AND Fin_AppDate IS NOT NULL AND Fin_AppDate <> '0000-00-00' AND Fin_AppDate <= @asof))
  GROUP BY EmployeeID)
SELECT COUNT(*) employees, ROUND(SUM(COALESCE(imp.imprest,0))) imprest, ROUND(SUM(COALESCE(imp.opening,0))) opening_adv,
       ROUND(SUM(COALESCE(paid.paid,0))) paid, ROUND(SUM(COALESCE(adj.adj,0))) adjustments_all,
       ROUND(SUM(COALESCE(imp.imprest,0) + GREATEST(0, COALESCE(imp.opening,0) + COALESCE(paid.paid,0) - COALESCE(adj.adj,0)))) old_asof_total
FROM emp LEFT JOIN imp ON imp.employee_id = emp.id LEFT JOIN paid ON paid.employee_id = emp.id LEFT JOIN adj ON adj.EmployeeID = emp.id;

-- Adjustment timeline: legacy months (payment_status Pending, pay month <= 2026-01) vs new app
SELECT YearId, payment_status, DATE_FORMAT(NULLIF(Fin_PayDate,'0000-00-00'),'%Y-%m') pay_month, COUNT(*) months, ROUND(SUM(total_adjustment)) adjustment
FROM (SELECT YearId, payment_status, Fin_PayDate, total_adjustment FROM y7_monthexpensefinal
      UNION ALL SELECT YearId, payment_status, Fin_PayDate, total_adjustment FROM y8_monthexpensefinal) m
WHERE total_adjustment > 0 GROUP BY 1,2,3 ORDER BY 1,3;

-- ---------------------------------------------------------------------------------
-- R4 (LR-05) Ledger Summary balance filter (old filter used imported balance / closing_balance)
--   old 'zero' filter matched all 276 ledger rows (closing_balance always 0) and every employee
--   without a row; computed: zero 119, positive 265 of 384 (engine output).
-- ---------------------------------------------------------------------------------
SELECT COUNT(*) ledger_rows, SUM(balance > 0 OR closing_balance > 0) old_positive_filter,
       SUM(balance IS NULL OR balance = 0 OR closing_balance = 0) old_zero_filter
FROM audit_scratch.employee_ledger_balances_backup_20261104;   -- pre-repair snapshot

-- ---------------------------------------------------------------------------------
-- R5 (LR-06) Payment Stages Summary: Advance column (old = SUM(Fin_AdvancePay))
--   FY25-26: old 3,60,32,977 -> 4,08,58,250 ; FY26-27: old 0 -> 1,75,08,362
-- ---------------------------------------------------------------------------------
SELECT 'FY2025-26' fy, ROUND(SUM(Claim_Amount)) filled, ROUND(SUM(Finance_Amount)) financed,
       ROUND(COALESCE(SUM(Fin_AdvancePay),0)) advance_old,
       ROUND(SUM(COALESCE(NULLIF(Fin_AdvancePay+0,0), total_adjustment, 0))) advance_new,
       ROUND(SUM(Fin_PayAmt)) paid,
       SUM((COALESCE(Fin_AdvancePay,0)+0) = 0 AND total_adjustment > 0) months_missing_advance
FROM y7_monthexpensefinal
UNION ALL
SELECT 'FY2026-27', ROUND(SUM(Claim_Amount)), ROUND(SUM(Finance_Amount)), ROUND(COALESCE(SUM(Fin_AdvancePay),0)),
       ROUND(SUM(COALESCE(NULLIF(Fin_AdvancePay+0,0), total_adjustment, 0))), ROUND(SUM(Fin_PayAmt)),
       SUM((COALESCE(Fin_AdvancePay,0)+0) = 0 AND total_adjustment > 0)
FROM y8_monthexpensefinal;

-- ---------------------------------------------------------------------------------
-- R6 (LR-07/LR-08) Focus export: activity claims (claim group 7) in closed, finance-approved months.
--   The export's own SQL needs HRMS; the source-side control is: per claim, the detail
--   FinanceEditAmount should add up to FinancedTAmt. Claims where it does not are the ones whose
--   ERP lines cannot be made to tie and need finance review (FY26-27 47 claims after fix).
--   Missing g7 columns per year (cause of the FY25-26 export failure):
-- ---------------------------------------------------------------------------------
SELECT COUNT(*) g7_columns_missing_in_y7
FROM information_schema.columns c8
WHERE c8.table_schema = 'expense' AND c8.table_name = 'y8_g7_expensefilldata'
  AND c8.column_name NOT IN (SELECT column_name FROM information_schema.columns
                             WHERE table_schema = 'expense' AND table_name = 'y7_g7_expensefilldata');

SELECT 'FY2026-27' fy, COUNT(*) activity_claims, ROUND(SUM(e.FinancedTAmt)) financed,
       SUM(ABS(COALESCE(d.fe, 0) - e.FinancedTAmt) >= 1) detail_not_equal_claim,
       ROUND(SUM(COALESCE(d.fe, 0))) detail_total
FROM y8_expenseclaims e JOIN claimtype c ON c.ClaimId = e.ClaimId
JOIN y8_monthexpensefinal mef ON mef.EmployeeID = e.CrBy AND mef.Month = e.ClaimMonth AND mef.YearId = 8 AND mef.Status = 'Closed' AND mef.Fin_AppBy > 0
LEFT JOIN (SELECT ExpId, SUM(FinanceEditAmount) fe FROM y8_expenseclaimsdetails GROUP BY ExpId) d ON d.ExpId = e.ExpId
WHERE c.cgId = 7 AND e.FilledOkay = 1 AND e.ClaimStatus NOT IN ('Deactivate','Draft') AND e.ClaimAtStep >= 4 AND e.ClaimYearId = 8
UNION ALL
SELECT 'FY2025-26', COUNT(*), ROUND(SUM(e.FinancedTAmt)), SUM(ABS(COALESCE(d.fe, 0) - e.FinancedTAmt) >= 1), ROUND(SUM(COALESCE(d.fe, 0)))
FROM y7_expenseclaims e JOIN claimtype c ON c.ClaimId = e.ClaimId
JOIN y7_monthexpensefinal mef ON mef.EmployeeID = e.CrBy AND mef.Month = e.ClaimMonth AND mef.YearId = 7 AND mef.Status = 'Closed' AND mef.Fin_AppBy > 0
LEFT JOIN (SELECT ExpId, SUM(FinanceEditAmount) fe FROM y7_expenseclaimsdetails GROUP BY ExpId) d ON d.ExpId = e.ExpId
WHERE c.cgId = 7 AND e.FilledOkay = 1 AND e.ClaimStatus NOT IN ('Deactivate','Draft') AND e.ClaimAtStep >= 4 AND e.ClaimYearId = 7;

-- If the harness table exists (work/lv_reports_harness/focus_fix.php writes nothing; the
-- baseline export rows were materialised in audit_scratch.focus_rows_y8): head lines vs claim
SELECT COUNT(*) claims, ROUND(SUM(e.FinancedTAmt)) claim_financed, ROUND(SUM(per.exp_amt)) exported_baseline,
       SUM(per.exp_amt > e.FinancedTAmt + 0.5) over_n, ROUND(SUM(GREATEST(per.exp_amt - e.FinancedTAmt, 0))) over_amt,
       SUM(per.exp_amt < e.FinancedTAmt - 0.5) under_n, ROUND(SUM(GREATEST(e.FinancedTAmt - per.exp_amt, 0))) under_amt
FROM (SELECT ExpId, SUM(FinancedTAmt) exp_amt FROM audit_scratch.focus_rows_y8 WHERE cgId = 7 GROUP BY ExpId) per
JOIN y8_expenseclaims e ON e.ExpId = per.ExpId;

-- ---------------------------------------------------------------------------------
-- R7 (LR-09) Odometer Analysis: running from deactivated / draft claims (old query included it)
-- ---------------------------------------------------------------------------------
SELECT 'FY2025-26' fy, ec.ClaimStatus IN ('Deactivate','Deactivat','Draft') excluded_now, COUNT(*) entries, ROUND(SUM(we.Totalkm)) km, ROUND(SUM(we.FilledTAmt)) amount
FROM y7_24_wheeler_entry we JOIN y7_expenseclaims ec ON ec.ExpId = we.ExpId GROUP BY 2
UNION ALL
SELECT 'FY2026-27', ec.ClaimStatus IN ('Deactivate','Deactivat','Draft'), COUNT(*), ROUND(SUM(we.Totalkm)), ROUND(SUM(we.FilledTAmt))
FROM y8_24_wheeler_entry we JOIN y8_expenseclaims ec ON ec.ExpId = we.ExpId GROUP BY 2;

-- ---------------------------------------------------------------------------------
-- R8 (LR-10) runningkm company duplicates (Total Running KM fan-out)
-- ---------------------------------------------------------------------------------
SELECT yearid, comid, COUNT(*) rows_, COUNT(DISTINCT empid) employees FROM runningkm GROUP BY yearid, comid;
SELECT yearid, SUM(has0 AND has1) duplicated_employees, SUM(has0 AND NOT has1) only_comid0
FROM (SELECT empid, yearid, MAX(comid = 0) has0, MAX(comid = 1) has1 FROM runningkm GROUP BY empid, yearid) x GROUP BY yearid;
WITH c AS (SELECT empid, SUM(yearid=5) n5, SUM(yearid=6) n6, SUM(yearid=7) n7, SUM(yearid=8) n8 FROM runningkm WHERE yearid IN (5,6,7,8) GROUP BY empid)
SELECT COUNT(*) employees, SUM(GREATEST(n5,1)*GREATEST(n6,1)*GREATEST(n7,1)*GREATEST(n8,1) > 1) employees_multiplied_old,
       SUM(GREATEST(n5,1)*GREATEST(n6,1)*GREATEST(n7,1)*GREATEST(n8,1)) report_rows_old FROM c;

-- ---------------------------------------------------------------------------------
-- R9 (LR-11) Claim-wise TAT: claims per claim type (old report showed 1 per type)
-- ---------------------------------------------------------------------------------
SELECT COUNT(DISTINCT ec.ClaimId) claim_types, COUNT(DISTINCT ec.ExpId) claims,
       ROUND(SUM(CASE WHEN ec.FilledTAmt > 0 THEN ec.FilledTAmt ELSE ec.Amount END)) amount
FROM y8_expenseclaims ec JOIN y8_monthexpensefinal mef ON mef.EmployeeID = ec.CrBy AND mef.Month = ec.ClaimMonth AND mef.YearId = ec.ClaimYearId
WHERE mef.YearId = 8 AND mef.DateOfSubmit <> '0000-00-00' AND ec.ClaimStatus <> 'Deactivate' AND ec.FilledOkay IN (0,1);

-- ---------------------------------------------------------------------------------
-- R10 checks that found no defect (kept as regression controls)
-- ---------------------------------------------------------------------------------
-- child-table cardinality used by the claim / focus reports (g tables 1 row per claim; details and uploads 1:N)
SELECT 'y8 g1' t, COUNT(*) n, COUNT(DISTINCT ExpId) claims FROM y8_g1_expensefilldata
UNION ALL SELECT 'y8 g7', COUNT(*), COUNT(DISTINCT ExpId) FROM y8_g7_expensefilldata
UNION ALL SELECT 'y8 details', COUNT(*), COUNT(DISTINCT ExpId) FROM y8_expenseclaimsdetails
UNION ALL SELECT 'y8 uploads', COUNT(*), COUNT(DISTINCT ExpId) FROM y8_claimuploads
UNION ALL SELECT 'y8 mef (emp,month,year)', COUNT(*), COUNT(DISTINCT EmployeeID, Month, YearId) FROM y8_monthexpensefinal
UNION ALL SELECT 'y8 wheeler', COUNT(*), COUNT(DISTINCT ExpId) FROM y8_24_wheeler_entry;
-- advance_request_employees.employee_id is the HRMS EmployeeID (matches users.employee_id), not users.id
SELECT COUNT(*) rows_, SUM(employee_id IN (SELECT employee_id FROM users)) matches_users_employee_id,
       SUM(employee_id IN (SELECT id FROM users)) collides_with_users_id FROM advance_request_employees;
-- DA In/Out: months that the zero-date COALESCE would drop (0 in this copy)
SELECT COUNT(*) FROM y8_monthexpensefinal WHERE Finance_Date = '0000-00-00' AND (Fin_PayDate <> '0000-00-00' OR payment_date <> '0000-00-00') AND COALESCE(Finance_Amount,0) > 0;
