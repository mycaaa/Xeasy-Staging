<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * S-07: the old PHP back office logged in against hrm_user.EmpPass (unsalted MD5) in each expense
 * database. The new app never reads it. Run once at cut-over, after the old pages are switched off:
 * the MD5 values are blanked so they cannot be cracked from a copy of the database.
 */
class RetireLegacyLogins extends Command
{
    protected $signature = 'security:retire-legacy-logins {--apply : blank the passwords (default: count only)}';

    protected $description = 'Blank the old back-office MD5 passwords (hrm_user) once the old app is off';

    public function handle(): int
    {
        foreach (array_unique(['expense', \App\Helpers\DatabaseSwitcher::getExpenseConnection(11)]) as $conn) {
            try {
                if (! Schema::connection($conn)->hasTable('hrm_user')) {
                    continue;
                }
                $q = DB::connection($conn)->table('hrm_user')->where('EmpPass', '!=', '');
                $n = (clone $q)->count();
                if ($this->option('apply') && $n) {
                    $q->update(['EmpPass' => '']);
                }
                $this->line("{$conn}.hrm_user: {$n} passwords ".($this->option('apply') ? 'blanked' : 'to blank'));
            } catch (\Throwable $e) {
                $this->warn("{$conn}: ".$e->getMessage());
            }
        }

        return self::SUCCESS;
    }
}
