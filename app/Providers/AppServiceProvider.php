<?php

namespace App\Providers;

use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;
use Illuminate\Database\Eloquent\Model;
use App\Observers\GlobalModelObserver;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        // Register DatabaseSwitcher as a singleton
        $this->app->singleton('database.switcher', function ($app) {
            return new \App\Helpers\DatabaseSwitcher();
        });
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // REM-SET-01: saved reminder settings over the config defaults
        \App\Support\ReminderSettings::load();

        // G-08: company addresses only, when MAIL_ALLOWED_DOMAINS is set
        \Illuminate\Support\Facades\Event::listen(\Illuminate\Mail\Events\MessageSending::class, function ($event) {
            return \App\Support\MailDomainGuard::filter($event->message);
        });

        // MAIL-01b: the same "already done" line on every reminder mail (VNR and VVNR, all senders)
        \Illuminate\Support\Facades\Event::listen(\Illuminate\Mail\Events\MessageSending::class, function ($event) {
            try {
                $msg = $event->message;
                if (! $msg instanceof \Symfony\Component\Mime\Email || ! \App\Support\ReminderFooter::applies($msg->getSubject())) {
                    return;
                }
                if (is_string($msg->getHtmlBody()) && $msg->getHtmlBody() !== '') {
                    $msg->html(\App\Support\ReminderFooter::html($msg->getHtmlBody()));
                }
                if (is_string($msg->getTextBody()) && $msg->getTextBody() !== '') {
                    $msg->text(\App\Support\ReminderFooter::text($msg->getTextBody()));
                }
            } catch (\Throwable $e) {
                \Illuminate\Support\Facades\Log::warning('MAIL-01b footer: '.$e->getMessage());
            }
        });

        // STAMP-01: date/time and user on every Excel/CSV report (not on ERP / bank upload files)
        if (class_exists(\Maatwebsite\Excel\Writer::class)) {
            \Maatwebsite\Excel\Writer::listen(\Maatwebsite\Excel\Events\BeforeWriting::class, function ($event) {
                try {
                    if ($event->getConcernable() instanceof \App\Exports\Concerns\NoReportStamp) {
                        return;
                    }
                    \App\Support\ReportStamp::workbook($event->getWriter()->getDelegate());
                } catch (\Throwable $e) {
                    \Illuminate\Support\Facades\Log::warning('Report stamp skipped: '.$e->getMessage());
                }
            });
        }

        Gate::before(function ($user, $ability) {
            if ($user->hasRole('Super Admin')) {
                return true;
            }

            // Ensure Mediator role has all required permissions for Data Punch
            $userRoleNames = $user->roles ? $user->roles->pluck('name')->map('strtolower')->toArray() : [];
            $isMediator = false;
            $isVerifier = false;
            foreach ($userRoleNames as $rName) {
                if (str_contains($rName, 'mediator') || str_contains($rName, 'punch')) {
                    $isMediator = true;
                }
                if (str_contains($rName, 'verifier') || str_contains($rName, 'verify')) {
                    $isVerifier = true;
                }
            }

            if ($isMediator) {
                $mediatorAbilities = [
                    'section_data_punching',
                    'view_claim_detail',
                    'data_punch',
                ];
                if (in_array($ability, $mediatorAbilities, true)) {
                    return true;
                }
            }

            if ($isVerifier) {
                $verifierAbilities = [
                    'section_verifier',
                    'verify_pending_list',
                    'verify_approved_list',
                    'view_claim_detail',
                    'pending_claims_submit_to_app',
                    'pending_claims_return',
                    'pending_claims_ehome',
                    'pending_claims_view_attendance',
                    'pending_claims_view_eligibility',
                    'pending_claims_courier_detail',
                    'verifier_revert',
                    'verifier_return_claim',
                    'verify_running_km',
                ];
                if (in_array($ability, $verifierAbilities, true)) {
                    return true;
                }
            }

            return null;
        });

        // Register global model observer for complete activity tracking
        // This will log all created/updated/deleted events on every Eloquent model
        $this->registerGlobalModelObserver();
    }

    /**
     * Register the global model observer on all models in the App\Models namespace.
     */
    protected function registerGlobalModelObserver(): void
    {
        $modelsPath = app_path('Models');
        if (!is_dir($modelsPath)) {
            return;
        }

        $modelFiles = glob($modelsPath . '/*.php');
        foreach ($modelFiles as $file) {
            $className = 'App\\Models\\' . pathinfo($file, PATHINFO_FILENAME);
            if (class_exists($className) && is_subclass_of($className, Model::class)) {
                try {
                    $className::observe(GlobalModelObserver::class);
                } catch (\Exception $e) {
                    // Skip models that can't be observed
                }
            }
        }
    }
}
