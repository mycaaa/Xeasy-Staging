<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('claim_rating_rules', function (Blueprint $table) {
            $table->id();
            $table->unsignedTinyInteger('company_id')->default(1)->comment('1=VNR, 11=VVNR, 3=Nursery');
            $table->string('stage_key', 50)->comment('Stage identifier: submit_to_courier, courier_to_receive, receive_to_verify, verify_to_approve, approve_to_pay');
            $table->string('stage_label', 100)->comment('Display label for the stage');
            $table->string('responsible_role', 50)->comment('Who is rated: employee, courier, verifier, approver, finance');
            $table->unsignedSmallInteger('star_5_max_days')->comment('Max days for 5 stars');
            $table->unsignedSmallInteger('star_4_max_days')->comment('Max days for 4 stars');
            $table->unsignedSmallInteger('star_3_max_days')->comment('Max days for 3 stars');
            $table->unsignedSmallInteger('star_2_max_days')->comment('Max days for 2 stars');
            $table->unsignedSmallInteger('star_1_min_days')->comment('Min days for 1 star (anything above star_2_max)');
            $table->unsignedTinyInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->timestamps();

            $table->index(['company_id', 'stage_key']);
            $table->index(['company_id', 'is_active']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('claim_rating_rules');
    }
};
