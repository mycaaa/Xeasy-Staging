<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('claim_rating_rules', function (Blueprint $table) {
            $table->unsignedSmallInteger('min_claims')->default(0)->after('star_1_min_days')
                ->comment('Minimum number of claims required for this rating to apply. 0 = no minimum.');
        });
    }

    public function down(): void
    {
        Schema::table('claim_rating_rules', function (Blueprint $table) {
            $table->dropColumn('min_claims');
        });
    }
};
