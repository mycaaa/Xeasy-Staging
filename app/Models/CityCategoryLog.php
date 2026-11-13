<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CityCategoryLog extends Model
{
    protected $table = 'city_category_logs';

    protected $fillable = [
        'ccid',
        'action', // 'Created', 'Updated', 'Deleted'
        'city_name',
        'city_category',
        'old_data',
        'new_data',
        'changes_summary',
        'action_by',
        'action_by_name',
        'ip_address',
    ];

    protected $casts = [
        'old_data' => 'array',
        'new_data' => 'array',
        'action_by' => 'integer',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    /**
     * Relationship to CityCategory (if record still exists)
     */
    public function cityCategory()
    {
        return $this->belongsTo(CityCategory::class, 'ccid', 'ccid');
    }

    /**
     * Ensure the city_category_logs table exists across connections without requiring manual terminal migration.
     */
    public static function ensureTableExists(string $conn = 'mysql'): void
    {
        try {
            if (!Schema::connection($conn)->hasTable('city_category_logs')) {
                \App\Support\SafeTable::create($conn, 'city_category_logs', function (Blueprint $table) {
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
        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::warning('Could not check or create city_category_logs table: ' . $e->getMessage());
        }
    }
}
