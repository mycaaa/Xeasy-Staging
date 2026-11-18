<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * BTC-REQ-22 (18 Nov 2026, BTC requirement sheet item 22): vendor boarding-pass links expired the moment they were
 * used. expires_at was a TIMESTAMP NOT NULL column, which MySQL (explicit_defaults_for_timestamp = OFF) turns into
 * "DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP": recording last use reset the expiry to now.
 * The column becomes DATETIME and links that were cut short this way get their 30 days back (from creation).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('vendor_portal_links')) {
            return;
        }
        DB::statement('ALTER TABLE vendor_portal_links MODIFY expires_at DATETIME NOT NULL');
        DB::table('vendor_portal_links')->whereNull('revoked_at')
            ->whereRaw('expires_at < DATE_ADD(created_at, INTERVAL 1 DAY)')
            ->update(['expires_at' => DB::raw('DATE_ADD(created_at, INTERVAL 30 DAY)')]);
    }

    public function down(): void
    {
    }
};
