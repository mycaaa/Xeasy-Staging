<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

// PERF-02: the activity log page reads the distinct events and filters by event; without an index
// that is a full scan of about 1.6 million rows (12 s). The index build is online (no table copy).
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('activity_log') || ! Schema::hasColumn('activity_log', 'event')) {
            return;
        }
        $has = collect(DB::select('SHOW INDEX FROM activity_log'))->contains(fn ($i) => $i->Key_name === 'activity_log_event_index');
        if (! $has) {
            Schema::table('activity_log', function (Blueprint $t) {
                $t->index('event', 'activity_log_event_index');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('activity_log')) {
            $has = collect(DB::select('SHOW INDEX FROM activity_log'))->contains(fn ($i) => $i->Key_name === 'activity_log_event_index');
            if ($has) {
                Schema::table('activity_log', fn (Blueprint $t) => $t->dropIndex('activity_log_event_index'));
            }
        }
    }
};
