<?php

namespace App\Models;

use App\Traits\CompanyDatabaseConnection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

class OtherParticipantCategory extends Model
{
    use HasFactory, CompanyDatabaseConnection;

    protected $table = 'other_participant_categories';
    protected $connectionType = 'expense';

    protected $fillable = [
        'name',
        'code',
        'description',
        'sort_order',
        'status',
    ];

    protected $casts = [
        'status' => 'integer',
        'sort_order' => 'integer',
    ];

    /**
     * Auto-ensure table exists with pre-seeded values if missing
     */
    public static function ensureTableExists()
    {
        try {
            if (!Schema::hasTable('other_participant_categories')) {
                Schema::create('other_participant_categories', function ($table) {
                    $table->id();
                    $table->string('name');
                    $table->string('code')->nullable();
                    $table->text('description')->nullable();
                    $table->integer('sort_order')->default(0);
                    $table->boolean('status')->default(1);
                    $table->timestamps();
                });

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
        } catch (\Exception $e) {
            // Silence exception if table exists or schema check handled
        }
    }

    public static function boot()
    {
        parent::boot();
        static::ensureTableExists();
    }

    /**
     * Scope query to active categories ordered by sort_order
     */
    public function scopeActive($query)
    {
        return $query->where('status', 1)->orderBy('sort_order', 'asc')->orderBy('name', 'asc');
    }
}
