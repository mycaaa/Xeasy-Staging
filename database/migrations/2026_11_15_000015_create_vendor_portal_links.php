<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * BOARDING-03 (15 Nov 2026): the vendor boarding-pass links table was only created by the page itself on first
 * use (VendorBoardingPassController::ensureTable), which fails where the web database user may not create
 * tables. Created here like any other table; same columns. Safe to run again.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('vendor_portal_links')) {
            return;
        }
        Schema::create('vendor_portal_links', function (Blueprint $t) {
            $t->id();
            $t->unsignedTinyInteger('company_id')->default(1);
            $t->unsignedBigInteger('vendor_id')->nullable();
            $t->string('vendor_name', 200);
            $t->char('token_hash', 64)->unique();
            $t->timestamp('expires_at');
            $t->timestamp('revoked_at')->nullable();
            $t->timestamp('last_used_at')->nullable();
            $t->unsignedBigInteger('created_by')->nullable();
            $t->string('created_by_name', 150)->nullable();
            $t->timestamp('created_at')->useCurrent();
        });
    }

    public function down(): void
    {
        // kept: holds issued vendor links
    }
};
