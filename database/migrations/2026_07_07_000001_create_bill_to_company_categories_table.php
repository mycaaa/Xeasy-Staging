<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        Schema::connection('mysql')->create('bill_to_company_categories', function (Blueprint $table) {
            $table->id();
            $table->string('name', 100)->unique();
            $table->string('description', 255)->nullable();
            $table->boolean('is_active')->default(true);
            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->timestamps();
        });

        // Seed default categories
        $now = now();
        $defaults = [
            ['name' => 'Travel',     'description' => 'Travel related expenses',      'is_active' => true, 'created_at' => $now, 'updated_at' => $now],
            ['name' => 'Hotel',      'description' => 'Hotel / accommodation bills',   'is_active' => true, 'created_at' => $now, 'updated_at' => $now],
            ['name' => 'Medical',    'description' => 'Medical / health expenses',     'is_active' => true, 'created_at' => $now, 'updated_at' => $now],
            ['name' => 'Conference', 'description' => 'Conference / seminar expenses', 'is_active' => true, 'created_at' => $now, 'updated_at' => $now],
            ['name' => 'Equipment',  'description' => 'Equipment / hardware expenses', 'is_active' => true, 'created_at' => $now, 'updated_at' => $now],
            ['name' => 'Misc',       'description' => 'Miscellaneous expenses',        'is_active' => true, 'created_at' => $now, 'updated_at' => $now],
        ];

        DB::connection('mysql')->table('bill_to_company_categories')->insert($defaults);
    }

    public function down(): void
    {
        Schema::connection('mysql')->dropIfExists('bill_to_company_categories');
    }
};
