<?php

namespace App\Support;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * REM-SET-01 (decided 10 Nov 2026): one settings screen for the reminder mails; no fallback to
 * another recipient when the manager (or manager's manager) is missing - that mail is skipped.
 *
 * Values are kept in reminder_settings (key -> value) and laid over config('xeasy_notifications')
 * when the app boots, so the scheduler and the reminder services read the saved values.
 */
class ReminderSettings
{
    public const TABLE = 'reminder_settings';

    /** key => [label, type, group, help] */
    public const FIELDS = [
        'approval_reminder.enabled' => ['Send', 'bool', 'Manager approval reminder', 'Mail the reporting manager about submitted months waiting for approval'],
        'approval_reminder.time' => ['Time of day', 'time', 'Manager approval reminder', ''],
        'approval_reminder.start_after_days' => ['First reminder after (days from submission)', 'int', 'Manager approval reminder', ''],
        'approval_reminder.repeat_every_days' => ['Repeat every (days)', 'int', 'Manager approval reminder', ''],
        'approval_reminder.stop_after_days' => ['Stop after (days from submission, 0 = never)', 'int', 'Manager approval reminder', 'Months older than this are no longer reminded'],
        'courier_reminder.enabled' => ['Send', 'bool', 'Courier (hard copy) reminder', 'Daily mail to the employee until the courier details are entered'],
        'courier_reminder.time' => ['Time of day', 'time', 'Courier (hard copy) reminder', ''],
        'courier_reminder.start_after_days' => ['First reminder after (days from submission)', 'int', 'Courier (hard copy) reminder', ''],
        'courier_reminder.max_days' => ['Stop after (days from submission, 0 = never)', 'int', 'Courier (hard copy) reminder', ''],
        'vvnr_hierarchy_summary.enabled' => ['Send', 'bool', 'VVNR monthly hierarchy summary', 'Previous month summary to managers\' managers'],
        'vvnr_hierarchy_summary.day' => ['Day of month', 'int', 'VVNR monthly hierarchy summary', ''],
        'vvnr_hierarchy_summary.time' => ['Time of day', 'time', 'VVNR monthly hierarchy summary', ''],
        'employee.enabled' => ['Send', 'bool', 'Employee claim updates', 'Deductions, month approved / financed, payment confirmed'],
        'employee.lookback_days' => ['Only events from the last (days)', 'int', 'Employee claim updates', ''],
    ];

    public static function load(): void
    {
        try {
            if (! Schema::hasTable(self::TABLE)) {
                return;
            }
            foreach (DB::table(self::TABLE)->pluck('value', 'key') as $k => $v) {
                if (! isset(self::FIELDS[$k])) {
                    continue;
                }
                config(['xeasy_notifications.'.$k => self::cast($v, self::FIELDS[$k][1])]);
            }
        } catch (\Throwable $e) {
            // settings table not reachable: keep the file defaults
        }
    }

    public static function cast($v, string $type)
    {
        return match ($type) {
            'bool' => filter_var($v, FILTER_VALIDATE_BOOLEAN),
            'int' => max(0, (int) $v),
            'time' => preg_match('/^([01]\d|2[0-3]):[0-5]\d$/', (string) $v) ? (string) $v : '10:00',
            default => (string) $v,
        };
    }

    public static function current(): array
    {
        $out = [];
        foreach (self::FIELDS as $k => $f) {
            $out[$k] = config('xeasy_notifications.'.$k);
        }

        return $out;
    }

    public static function save(array $input, ?int $userId): array
    {
        self::ensureTable();
        $changed = [];
        foreach (self::FIELDS as $k => [$label, $type]) {
            $form = str_replace('.', '__', $k);
            $new = $type === 'bool' ? (isset($input[$form]) && $input[$form] ? '1' : '0') : (string) ($input[$form] ?? '');
            if ($type === 'int' && ! preg_match('/^\d{1,4}$/', $new)) {
                continue;
            }
            if ($type === 'time' && ! preg_match('/^([01]\d|2[0-3]):[0-5]\d$/', $new)) {
                continue;
            }
            $old = DB::table(self::TABLE)->where('key', $k)->value('value');
            $oldEff = $old ?? (is_bool(config('xeasy_notifications.'.$k)) ? (config('xeasy_notifications.'.$k) ? '1' : '0') : (string) config('xeasy_notifications.'.$k));
            if ((string) $oldEff === $new) {
                continue;
            }
            DB::table(self::TABLE)->updateOrInsert(['key' => $k], ['value' => $new, 'updated_by' => $userId, 'updated_at' => now()]);
            $changed[$k] = [$oldEff, $new];
        }
        if ($changed) {
            \Log::info('Reminder settings changed', ['by' => $userId, 'changes' => $changed]);
        }

        return $changed;
    }

    public static function ensureTable(): void
    {
        if (Schema::hasTable(self::TABLE)) {
            return;
        }
        Schema::create(self::TABLE, function ($t) {
            $t->string('key', 80)->primary();
            $t->string('value', 255)->nullable();
            $t->unsignedBigInteger('updated_by')->nullable();
            $t->timestamp('updated_at')->nullable();
        });
    }
}
