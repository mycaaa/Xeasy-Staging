<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * UAT-03 (6 Nov 2026): Settings -> General failed with "tbl_setting_general doesn't exist":
 * the GeneralSettings model and the settings screen were added without their table.
 * Creates it only where missing (the screen inserts the first row on save).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('tbl_setting_general')) {
            return;
        }
        Schema::create('tbl_setting_general', function (Blueprint $t) {
            $t->id();
            $t->string('project_name', 150)->nullable();
            $t->string('time_zone', 60)->nullable();
            $t->string('default_language', 20)->nullable();
            $t->boolean('maintenance_mode')->default(false);
            $t->string('site_url', 255)->nullable();
            $t->text('contact_info')->nullable();
            $t->text('analytics_tracking')->nullable();
            $t->text('site_description')->nullable();
            $t->string('logo_path', 255)->nullable();
            $t->unsignedBigInteger('created_by')->nullable();
            $t->unsignedBigInteger('updated_by')->nullable();
            $t->timestamps();
        });
    }

    public function down(): void
    {
        // keep data; drop manually if ever needed
    }
};
