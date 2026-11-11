<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('auto_verify_settings', function (Blueprint $table) {
            $table->id();
            $table->integer('company_id')->default(0)->comment('0=Global Default, 1=VNR Seeds, 3=VNR Nursery, 11=VVNR');
            $table->boolean('is_enabled')->default(true)->comment('Master switch for auto-verification');
            $table->boolean('auto_submit_month')->default(true)->comment('Auto-submit monthly voucher if all claims verified');
            $table->boolean('courier_check_required')->default(true)->comment('Require physical courier received before auto-submitting month');
            $table->integer('batch_size')->default(250)->comment('Default chunk size for processing');
            $table->decimal('gps_tolerance_pct', 5, 2)->default(15.00)->comment('Max allowable GPS variance percentage');
            $table->decimal('ocr_tolerance_pct', 5, 2)->default(5.00)->comment('OCR receipt match tolerance percentage');
            $table->decimal('ocr_tolerance_flat', 8, 2)->default(5.00)->comment('OCR receipt flat amount buffer in INR');
            $table->decimal('max_auto_verify_amount', 10, 2)->default(10000.00)->comment('Claims above this amount always flag for review');
            $table->json('rules')->nullable()->comment('Per-claim-type conditions and action-on-breach matrix');
            $table->string('updated_by')->nullable();
            $table->timestamps();

            $table->unique('company_id');
        });

        // Default rules JSON template
        $defaultRules = json_encode([
            '7' => [ // Travel / 2/4 Wheeler
                'enabled' => true,
                'action_over_limit' => 'cap', // cap, flag, reject
                'require_attachment' => false,
                'require_continuity' => true,
                'max_amount' => 15000,
            ],
            '19' => [ // DA@HQ
                'enabled' => true,
                'action_over_limit' => 'cap',
                'require_attachment' => false,
                'max_amount' => 2000,
            ],
            '20' => [ // DA Outside HQ
                'enabled' => true,
                'action_over_limit' => 'cap',
                'require_attachment' => false,
                'max_amount' => 5000,
            ],
            '1' => [ // Lodging
                'enabled' => true,
                'action_over_limit' => 'flag',
                'require_attachment' => true,
                'max_amount' => 10000,
            ],
            '17' => [ // Meals / Group Meals
                'enabled' => true,
                'action_over_limit' => 'cap',
                'require_attachment' => false,
                'max_amount' => 5000,
            ],
            'default' => [ // All other claims (fare, bus, stationery, courier, etc.)
                'enabled' => true,
                'action_over_limit' => 'flag',
                'require_attachment' => true,
                'max_amount' => 5000,
            ],
        ]);

        // Insert initial baseline records
        DB::table('auto_verify_settings')->insert([
            [
                'company_id' => 0, // Global Defaults
                'is_enabled' => true,
                'auto_submit_month' => true,
                'courier_check_required' => true,
                'batch_size' => 250,
                'gps_tolerance_pct' => 15.00,
                'ocr_tolerance_pct' => 5.00,
                'ocr_tolerance_flat' => 5.00,
                'max_auto_verify_amount' => 10000.00,
                'rules' => $defaultRules,
                'updated_by' => 'System',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'company_id' => 1, // VNR Seeds
                'is_enabled' => true,
                'auto_submit_month' => true,
                'courier_check_required' => true,
                'batch_size' => 250,
                'gps_tolerance_pct' => 15.00,
                'ocr_tolerance_pct' => 5.00,
                'ocr_tolerance_flat' => 5.00,
                'max_auto_verify_amount' => 10000.00,
                'rules' => $defaultRules,
                'updated_by' => 'System',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'company_id' => 3, // VNR Nursery
                'is_enabled' => true,
                'auto_submit_month' => true,
                'courier_check_required' => true,
                'batch_size' => 250,
                'gps_tolerance_pct' => 15.00,
                'ocr_tolerance_pct' => 5.00,
                'ocr_tolerance_flat' => 5.00,
                'max_auto_verify_amount' => 10000.00,
                'rules' => $defaultRules,
                'updated_by' => 'System',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'company_id' => 11, // VVNR Seeds
                'is_enabled' => true,
                'auto_submit_month' => true,
                'courier_check_required' => true,
                'batch_size' => 250,
                'gps_tolerance_pct' => 15.00,
                'ocr_tolerance_pct' => 5.00,
                'ocr_tolerance_flat' => 5.00,
                'max_auto_verify_amount' => 10000.00,
                'rules' => $defaultRules,
                'updated_by' => 'System',
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('auto_verify_settings');
    }
};
