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
        Schema::create('other_participant_categories', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('code')->nullable()->unique();
            $table->text('description')->nullable();
            $table->integer('sort_order')->default(0);
            $table->boolean('status')->default(1)->comment('1: Active, 0: Inactive');
            $table->timestamps();
        });

        // Seed initial standard 7 categories
        $defaultCategories = [
            ['name' => 'Retailer', 'code' => 'RETAILER', 'sort_order' => 1, 'status' => 1],
            ['name' => 'Dealer', 'code' => 'DEALER', 'sort_order' => 2, 'status' => 1],
            ['name' => 'Distributor', 'code' => 'DISTRIBUTOR', 'sort_order' => 3, 'status' => 1],
            ['name' => 'Consultant', 'code' => 'CONSULTANT', 'sort_order' => 4, 'status' => 1],
            ['name' => 'Management', 'code' => 'MANAGEMENT', 'sort_order' => 5, 'status' => 1],
            ['name' => 'Guest', 'code' => 'GUEST', 'sort_order' => 6, 'status' => 1],
            ['name' => 'Other Employee not available in the list', 'code' => 'OTHER_EMPLOYEE', 'sort_order' => 7, 'status' => 1],
        ];

        foreach ($defaultCategories as $cat) {
            DB::table('other_participant_categories')->insert(array_merge($cat, [
                'created_at' => now(),
                'updated_at' => now(),
            ]));
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('other_participant_categories');
    }
};
