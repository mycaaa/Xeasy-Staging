Ledger / report reconciliation (run against a COPY of the expense database, never production writes)

1. SQL control totals:   mariadb expense < tests/reconciliation/reconcile_reports.sql
2. Ledger engine totals: php tests/reconciliation/boot.php . tests/reconciliation/ledger_batch2.php x -
                         php tests/reconciliation/boot.php . tests/reconciliation/ledger_batch2.php x 2026-10-03
   Prints total_imprest / advance_balance / total_balance / opening_advance / advances_paid /
   adjustments over every employee with a ledger row or an advance, and the number of employees
   where opening_advance + advances_paid - adjustments != advance_balance (must be 0).
   The "-" run (current) and the dated run (as-of the dump date) must give the same totals.
3. Unit test:            vendor/bin/phpunit tests/Unit/LedgerJsonTest.php
4. Stored-row repair:    php artisan ledger:repair-balances --dry-run   (then without --dry-run)
