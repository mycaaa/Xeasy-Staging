<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Spatie\Activitylog\Traits\LogsActivity;
use Spatie\Permission\Traits\HasRoles;

class User extends Authenticatable
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasFactory, HasRoles, LogsActivity, Notifiable;

    /**
     * The database connection name for the model.
     * Users table should always use VNR (expense) connection regardless of company selection
     * since it's a shared authentication table
     *
     * @var string
     */


    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'status',
        'is_guest',
        'created_by',
        'updated_by',
        'employee_id',
        'company_access_id',
        'role_id',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'is_guest' => 'boolean',
        ];
    }

    /**
     * Self-healing helper for zero-terminal environments
     */
    public static function ensureGuestColumnExists(): void
    {
        if (\Illuminate\Support\Facades\Schema::hasTable('users') && !\Illuminate\Support\Facades\Schema::hasColumn('users', 'is_guest')) {
            \Illuminate\Support\Facades\Schema::table('users', function (\Illuminate\Database\Schema\Blueprint $table) {
                $table->tinyInteger('is_guest')->default(0)->after('status')->index();
            });
        }
    }

    public function scopeGuests($query)
    {
        return $query->where('is_guest', 1);
    }

    public function scopeEmployees($query)
    {
        return $query->where(function ($q) {
            $q->where('is_guest', 0)->orWhereNull('is_guest');
        });
    }



    public function permissions()
    {
        return $this->morphToMany(
            \App\Models\Permission::class,
            'model',
            'model_has_permissions',
            'model_id',
            'permission_id'
        );
    }

    public function getActivitylogOptions(): \Spatie\Activitylog\LogOptions
    {
        return \Spatie\Activitylog\LogOptions::defaults()
            ->logAll()
            ->logOnlyDirty();
    }

    /**
     * Get the employee details from HRIMS database
     */
    public function employee()
    {
        return $this->belongsTo(\App\Models\HRMEmployees::class, 'employee_id', 'EmployeeID');
    }
}
