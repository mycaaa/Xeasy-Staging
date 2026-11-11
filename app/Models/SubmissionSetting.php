<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Spatie\Activitylog\Traits\LogsActivity;
use Spatie\Activitylog\LogOptions;

class SubmissionSetting extends Model
{
    use LogsActivity;

    protected $table = 'submission_settings';

    protected $fillable = [
        'company_id',
        'last_submission_day',
        'is_active',
        'updated_by',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'last_submission_day' => 'integer',
        'company_id' => 'integer',
    ];

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['company_id', 'last_submission_day', 'is_active', 'updated_by'])
            ->logOnlyDirty()
            ->setDescriptionForEvent(fn(string $eventName) => "Submission setting {$eventName}");
    }

    /**
     * Get the last submission day for a company.
     * Falls back to 15 if no setting found.
     */
    public static function getLastDay(int $companyId): int
    {
        $setting = self::where('company_id', $companyId)
            ->where('is_active', true)
            ->first();

        return $setting ? $setting->last_submission_day : 15;
    }

    /**
     * Get company name label.
     */
    public function getCompanyNameAttribute(): string
    {
        return match ($this->company_id) {
            1 => 'VNR Seeds',
            3 => 'VNR Nursery',
            11 => 'VVNR Seeds',
            default => "Company {$this->company_id}",
        };
    }
}
