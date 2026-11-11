<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ClaimRatingSetting extends Model
{
    protected $table = 'claim_rating_settings';

    protected $fillable = [
        'company_id', 'calculation_day', 'auto_calculate',
        'last_calculated_date', 'last_calculated_by',
    ];

    protected $casts = [
        'auto_calculate' => 'boolean',
        'calculation_day' => 'integer',
        'last_calculated_date' => 'date',
    ];

    public static function getForCompany(?int $companyId = null): self
    {
        $companyId = $companyId ?? (int) session('company_id', 1);
        return self::firstOrCreate(
            ['company_id' => $companyId],
            ['calculation_day' => 10, 'auto_calculate' => false]
        );
    }
}
