<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (!Schema::hasTable('city_category_logs')) {
            Schema::create('city_category_logs', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('ccid')->nullable()->index();
                $table->string('action', 50)->comment('Created, Updated, Deleted');
                $table->string('city_name', 150)->nullable()->index();
                $table->string('city_category', 50)->nullable()->index();
                $table->json('old_data')->nullable();
                $table->json('new_data')->nullable();
                $table->text('changes_summary')->nullable();
                $table->unsignedBigInteger('action_by')->nullable()->index();
                $table->string('action_by_name', 150)->nullable();
                $table->string('ip_address', 50)->nullable();
                $table->timestamps();
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('city_category_logs');
    }
};
