<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $connections = array_filter(array_unique([
            config('database.default', 'mysql'),
            'mysql',
            'expense',
            'expense_app',
        ]), fn ($conn) => config("database.connections.{$conn}") !== null);

        foreach ($connections as $conn) {
            try {
                if (Schema::connection($conn)->hasTable('advance_requests')) {
                    if (! Schema::connection($conn)->hasColumn('advance_requests', 'is_imprest')) {
                        Schema::connection($conn)->table('advance_requests', function (Blueprint $table) {
                            $table->boolean('is_imprest')->default(false)->index();
                        });
                    }
                }
                if (Schema::connection($conn)->hasTable('advance_request_activities')) {
                    if (! Schema::connection($conn)->hasColumn('advance_request_activities', 'is_imprest')) {
                        Schema::connection($conn)->table('advance_request_activities', function (Blueprint $table) {
                            $table->boolean('is_imprest')->default(false)->index();
                        });
                    }
                }
            } catch (\Exception $e) {
                \Illuminate\Support\Facades\Log::warning("Migration add_is_imprest failed on connection {$conn}: ".$e->getMessage());
            }
        }
    }

    public function down(): void
    {
        $connections = array_filter(array_unique([
            config('database.default', 'mysql'),
            'mysql',
            'expense',
            'expense_app',
        ]), fn ($conn) => config("database.connections.{$conn}") !== null);

        foreach ($connections as $conn) {
            try {
                if (Schema::connection($conn)->hasTable('advance_requests')) {
                    if (Schema::connection($conn)->hasColumn('advance_requests', 'is_imprest')) {
                        Schema::connection($conn)->table('advance_requests', function (Blueprint $table) {
                            $table->dropColumn('is_imprest');
                        });
                    }
                }
                if (Schema::connection($conn)->hasTable('advance_request_activities')) {
                    if (Schema::connection($conn)->hasColumn('advance_request_activities', 'is_imprest')) {
                        Schema::connection($conn)->table('advance_request_activities', function (Blueprint $table) {
                            $table->dropColumn('is_imprest');
                        });
                    }
                }
            } catch (\Exception $e) {
            }
        }
    }
};
