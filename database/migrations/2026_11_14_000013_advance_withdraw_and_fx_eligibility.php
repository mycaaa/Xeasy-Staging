<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * ADV-WITHDRAW-01 / ADV-FX-ELIG-01 (14 Nov 2026):
 *  - the status lists of advance requests, their activities, approval rows and the action log get "Withdrawn" /
 *    "Withdraw", so a requester can withdraw a request while it waits for approval;
 *  - advance_fx_eligibility holds who may request an advance in a foreign currency (Advance Configuration Setting).
 * Safe to run again; existing values are kept.
 */
return new class extends Migration
{
    /** Add a value to an ENUM column, keeping its other values, null-ability and default. */
    private function addEnumValue(string $table, string $column, string $value): void
    {
        if (! Schema::hasTable($table) || ! Schema::hasColumn($table, $column)) {
            return;
        }
        $col = DB::selectOne('SHOW COLUMNS FROM `'.$table.'` LIKE '.DB::getPdo()->quote($column));   // SHOW does not take bound values
        if (! $col || ! preg_match("/^enum\((.*)\)$/i", (string) $col->Type, $m)) {
            return;   // not an enum (e.g. varchar): nothing to do
        }
        $values = str_getcsv($m[1], ',', "'", '');
        if (in_array($value, $values, true)) {
            return;
        }
        $values[] = $value;
        $list = implode(',', array_map(fn ($v) => DB::getPdo()->quote($v), $values));
        $null = strtoupper((string) $col->Null) === 'YES' ? 'NULL' : 'NOT NULL';
        $default = $col->Default !== null ? ' DEFAULT '.DB::getPdo()->quote($col->Default) : ($null === 'NULL' ? ' DEFAULT NULL' : '');
        DB::statement("ALTER TABLE `{$table}` MODIFY `{$column}` ENUM({$list}) {$null}{$default}");
    }

    public function up(): void
    {
        $this->addEnumValue('advance_requests', 'current_status', 'Withdrawn');
        $this->addEnumValue('advance_request_activities', 'approval_status', 'Withdrawn');
        $this->addEnumValue('advance_activity_approvals', 'approver_status', 'Withdrawn');
        $this->addEnumValue('advance_request_actions', 'action_type', 'Withdraw');

        if (! Schema::hasTable('advance_fx_eligibility')) {
            Schema::create('advance_fx_eligibility', function (Blueprint $t) {
                $t->id();
                $t->unsignedTinyInteger('company_id')->default(1);
                $t->boolean('all_employees')->default(false);
                $t->json('department_ids')->nullable();
                $t->json('grade_ids')->nullable();
                $t->json('employee_ids')->nullable();
                $t->unsignedBigInteger('updated_by')->nullable();
                $t->timestamps();
                $t->unique('company_id');
            });
        }
    }

    public function down(): void
    {
        // enum values and the settings table are kept
    }
};
