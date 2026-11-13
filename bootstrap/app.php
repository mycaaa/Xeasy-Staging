<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\Facades\Route;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
        then: function () {
            Route::middleware('web')->group(base_path('routes/backend.php'));
            // Audit fix SEC-01: api.php and advance.php need a web session, a mobile app
            // token or the internal API key (App\Http\Middleware\ApiAuthenticate).
            $apiLogin = [
                \Illuminate\Cookie\Middleware\EncryptCookies::class,
                \Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse::class,
                \Illuminate\Session\Middleware\StartSession::class,
                \App\Http\Middleware\UnifiedApiResponse::class, // LV-U03: one envelope on request (X-Xeasy-Response: v2); before the login check so 401s are wrapped too
                \App\Http\Middleware\ApiAuthenticate::class,
            ];
            Route::middleware(array_merge(['api'], $apiLogin))
                ->prefix('api')
                ->group(base_path('routes/api.php'));

            // Advance routes (were fully public before SEC-01)
            Route::middleware(array_merge([\Illuminate\Http\Middleware\HandleCors::class], $apiLogin))
                ->prefix('api')->group(base_path('routes/advance.php'));
            
            // Mobile API routes (legacy API migration)
            // These serve under /api/ to match Flutter app's Config.MainUrl
            // LV-S04: login check (app token / session / internal key); acting employee = token's
            Route::middleware(['api', \App\Http\Middleware\UnifiedApiResponse::class, \App\Http\Middleware\LegacyZeroDates::class, \Illuminate\Cookie\Middleware\EncryptCookies::class, \App\Http\Middleware\StartSessionIfCookie::class, \App\Http\Middleware\MobileApiAuthenticate::class])
                ->prefix('api')
                ->group(base_path('routes/mobile-api.php'));
            
            // Debug routes (remove in production)
            if (app()->environment(['local', 'development'])) {
                include base_path('routes/debug.php');
            }
        }
    )
    ->withMiddleware(function (Middleware $middleware) {
        // LV-S08 / S-03: year, employee and claim ids must be numbers on every route
        $middleware->append(\App\Http\Middleware\ValidateIdInput::class);
        // PERF-10: Server-Timing header and the slow-request log (php artisan perf:slow-pages)
        $middleware->prepend(\App\Http\Middleware\MeasureRequestTime::class);
        // Enable CORS for API routes
        $middleware->api(prepend: [
            \Illuminate\Http\Middleware\HandleCors::class,
        ]);
        
        $middleware->alias([
            'role' => \Spatie\Permission\Middleware\RoleMiddleware::class,
            'permission' => \Spatie\Permission\Middleware\PermissionMiddleware::class,
            'role_or_permission' => \Spatie\Permission\Middleware\RoleOrPermissionMiddleware::class,
            'database.connection' => \App\Http\Middleware\SetDatabaseConnection::class,
            'cors' => \App\Http\Middleware\CorsMiddleware::class,
            'report.access' => \App\Http\Middleware\ReportAccessMiddleware::class,
        ]);
        
        // Add the database connection middleware to web group
        $middleware->web(append: [
            \App\Http\Middleware\PreventBackHistory::class,
            \App\Http\Middleware\SetDatabaseConnection::class,
            \App\Http\Middleware\LogActivity::class,
            // Audit fix SEC-01: every web route needs login except the public list in the class
            \App\Http\Middleware\RequireWebLogin::class,
            // SEC-02: permission by path for admin screens that only had the login check
            \App\Http\Middleware\EnforceRouteAccess::class,
            // SEC-03: record-level check (own / reporting chain / staff) on employee and claim ids
            \App\Http\Middleware\EnforceRecordAccess::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions) {})
    ->withSchedule(function (Schedule $schedule) {
        // Close expired months on 15th of every month at midnight
        $schedule->command('claims:close-expired-months --months=3')
            ->monthlyOn(15, '00:00')
            ->withoutOverlapping()
            ->appendOutputTo(storage_path('logs/close-expired-months.log'));

        // REQ-001: Imprest Automation — runs every Monday morning
        $schedule->command('imprest:automate')
            ->weeklyOn(1, '09:00')
            ->withoutOverlapping()
            ->appendOutputTo(storage_path('logs/imprest-automation.log'));

        // REQ-005 & REQ-006: Transfer Notifications — runs daily at 8am
        $schedule->command('notifications:transfer')
            ->dailyAt('08:00')
            ->withoutOverlapping()
            ->appendOutputTo(storage_path('logs/transfer-notifications.log'));

        // Automated Claim Verification — runs periodically during business hours
        $schedule->command('claims:auto-verify')
            ->hourly()
            ->withoutOverlapping()
            ->appendOutputTo(storage_path('logs/auto-verify-claims.log'));

        // ODO-DUP-01: drop odometer claims saved twice for the same trip (unpaid months only)
        // RET-01: archive copy of years that became archived (1st of each month)
        foreach ([1, 11] as $co) {
            $schedule->command("records:archive --company={$co} --apply")->monthlyOn(1, '02:30')->withoutOverlapping()
                ->appendOutputTo(storage_path('logs/records-archive.log'));
        }
        // VEH-RATE-REPORT-01: Monday mail to HR
        $schedule->command('reports:vehicle-rate-gaps --company=1')->weeklyOn(1, '08:40')->withoutOverlapping()
            ->appendOutputTo(storage_path('logs/vehicle-rate-gaps.log'));
        // M-08: punch-to-claim mover (replaces the old extract-data.php cron)
        foreach ([1, 11] as $co) {
            $schedule->command("punch:run-queue --company={$co}")->everyTenMinutes()->withoutOverlapping()
                ->appendOutputTo(storage_path('logs/punch-queue.log'));
        }
        // VEH-RATE-ALERT-01: zero vehicle rate mail to HR, every 3 days until the rate is entered
        if (config('claim_rules.vehicle_rate_alert.enabled', true)) {
            foreach ([1, 11] as $co) {
                $schedule->command("vehicle-rates:alert --company={$co}")->dailyAt('09:20')->timezone('Asia/Kolkata')->withoutOverlapping()
                    ->appendOutputTo(storage_path('logs/vehicle-rate-alerts.log'));
            }
        }
        // M-06: claim stage ratings with the rated person, nightly for the year so far
        foreach ([1, 11] as $co) {
            $schedule->command("claims:rate --company={$co}")->dailyAt('04:10')->withoutOverlapping()
                ->appendOutputTo(storage_path('logs/claim-rating.log'));
        }
        // D-07: activity_log rows older than 180 days go to monthly compressed files (kept 8 years)
        $schedule->command('logs:archive-activity --apply')->monthlyOn(2, '03:00')->withoutOverlapping()
            ->appendOutputTo(storage_path('logs/archive-activity.log'));
        // D-03: nightly orphan-row check (report only)
        $schedule->command('data:orphan-check --company=1')->dailyAt('03:30')->withoutOverlapping()
            ->appendOutputTo(storage_path('logs/orphan-check.log'));
        // D-01: keep only the last 4 digits of bank account numbers once matched
        $schedule->command('payments:mask-bank-data --apply')->dailyAt('02:10')->withoutOverlapping()
            ->appendOutputTo(storage_path('logs/mask-bank-data.log'));
        // LV-C06: advance payment batches follow their lines (Completed when all are paid)
        $schedule->call(fn () => \App\Services\Payments\PaymentBatches::refreshAll())->dailyAt('02:15')->name('payment-batches-refresh')->withoutOverlapping();
        // FF-01: separated employees' unpaid months go on F&F hold
        foreach ([1, 11] as $co) {
            $schedule->command("payments:separation-hold --company={$co}")
                ->dailyAt('01:55')
                ->withoutOverlapping()
                ->appendOutputTo(storage_path('logs/separation-hold.log'));
        }
        if (config('odo_duplicates.daily_sweep', true)) {
            foreach ([1, 11] as $co) {
                $schedule->command("odo:duplicates --company={$co} --apply")
                    ->dailyAt('01:40')
                    ->withoutOverlapping()
                    ->appendOutputTo(storage_path('logs/odo-duplicates.log'));
            }
        }

        // Matrix Payment Updates Sync — runs every 5 minutes (SCHEDULE_MATRIX_SYNC=false turns it off,
        // e.g. on a test server that cannot reach Matrix)
        if (config('app.schedule_matrix_sync', true)) {
            $schedule->command('matrix:sync-updates')
                ->everyFiveMinutes()
                ->withoutOverlapping()
                ->appendOutputTo(storage_path('logs/matrix-sync.log'));
        }

        // Tracker #3: daily courier-details reminder from the day after submission
        if (config('xeasy_notifications.courier_reminder.enabled', true)) {
            $schedule->command('notifications:courier-reminder')
                ->dailyAt((string) config('xeasy_notifications.courier_reminder.time', '10:15'))
                ->timezone('Asia/Kolkata')
                ->withoutOverlapping()
                ->appendOutputTo(storage_path('logs/courier-reminders.log'));
        }

        // APPR-REM-01: reporting-manager approval reminder, 3 days after submission, every 3 days
        if (config('xeasy_notifications.approval_reminder.enabled', true)) {
            $schedule->command('notifications:approval-reminder')
                ->dailyAt((string) config('xeasy_notifications.approval_reminder.time', '10:35'))
                ->timezone('Asia/Kolkata')
                ->withoutOverlapping()
                ->appendOutputTo(storage_path('logs/approval-reminders.log'));
        }

        // VVNR-HIER-01: monthly hierarchy-wise summary for the previous month (VVNR)
        if (config('xeasy_notifications.vvnr_hierarchy_summary.enabled', true)) {
            $schedule->command('notifications:vvnr-hierarchy-summary')
                ->monthlyOn((int) config('xeasy_notifications.vvnr_hierarchy_summary.day', 10), (string) config('xeasy_notifications.vvnr_hierarchy_summary.time', '10:50'))
                ->timezone('Asia/Kolkata')
                ->withoutOverlapping()
                ->appendOutputTo(storage_path('logs/vvnr-hierarchy-summary.log'));
        }

        // ODO-BD-01: late-entered running-km claims (entry date - odometer date > allowed days) wait for back-date approval
        $schedule->command('odometer:flag-backdated')
            ->hourlyAt(20)
            ->withoutOverlapping()
            ->appendOutputTo(storage_path('logs/odometer-backdate.log'));

        // Tracker #29: employee mails for deductions, month status and payment
        if (config('xeasy_notifications.employee.enabled', true)) {
            $schedule->command('notifications:employee-claim-updates')
                ->cron((string) config('xeasy_notifications.employee.cron', '7 * * * *'))
                ->timezone('Asia/Kolkata')
                ->withoutOverlapping()
                ->appendOutputTo(storage_path('logs/employee-claim-notifications.log'));
        }

        // SCHED-01: queued mails/jobs (QUEUE_CONNECTION=database) are processed by the scheduler
        // itself, so one cron line runs everything. A long-running worker (supervisor/systemd)
        // can replace this: set SCHEDULE_QUEUE_WORKER=false.
        if (config('app.schedule_queue_worker', true)) {
            $schedule->command('queue:work --stop-when-empty --max-time=50 --tries=3 --sleep=1')
                ->everyMinute()
                ->withoutOverlapping(10)
                ->appendOutputTo(storage_path('logs/queue-worker.log'));
        }

        // SCHED-01: heartbeat, so a stopped scheduler can be noticed (cache key scheduler:last_run)
        $schedule->call(function () {
            \Illuminate\Support\Facades\Cache::put('scheduler:last_run', now()->toDateTimeString(), now()->addDays(7));
        })->everyMinute()->name('scheduler-heartbeat')->withoutOverlapping();

        // Resigned Employees Final Submission Reminder — runs daily at 9am
        $schedule->command('notifications:resigned-submission')
            ->dailyAt('09:00')
            ->withoutOverlapping()
            ->appendOutputTo(storage_path('logs/resigned-submission-reminders.log'));
    })
    ->create();
