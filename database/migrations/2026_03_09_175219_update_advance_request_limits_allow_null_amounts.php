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
        Schema::table('advance_request_limits', function (Blueprint $table) {
            $table->decimal('min_amount', 10, 2)->nullable()->change();
            $table->decimal('max_amount', 10, 2)->nullable()->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('advance_request_limits', function (Blueprint $table) {
            $table->decimal('min_amount', 10, 2)->nullable(false)->change();
            $table->decimal('max_amount', 10, 2)->nullable(false)->change();
        });
    }
};
