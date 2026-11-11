<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('submission_settings', function (Blueprint $table) {
            $table->id();
            $table->integer('company_id')->comment('1=VNR Seeds, 3=VNR Nursery, 11=VVNR');
            $table->integer('last_submission_day')->default(15)->comment('Last day of month to allow submission');
            $table->boolean('is_active')->default(true);
            $table->string('updated_by')->nullable();
            $table->timestamps();

            $table->unique('company_id');
        });

        // Insert default values for all companies
        DB::table('submission_settings')->insert([
            ['company_id' => 1, 'last_submission_day' => 15, 'is_active' => true, 'created_at' => now(), 'updated_at' => now()],
            ['company_id' => 3, 'last_submission_day' => 15, 'is_active' => true, 'created_at' => now(), 'updated_at' => now()],
            ['company_id' => 11, 'last_submission_day' => 15, 'is_active' => true, 'created_at' => now(), 'updated_at' => now()],
        ]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('submission_settings');
    }
};
