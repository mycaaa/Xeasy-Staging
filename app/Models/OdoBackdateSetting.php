<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class OdoBackdateSetting extends Model
{
    use HasFactory;

    protected $table = 'odo_backdate_settings';

    protected $casts = [
        'effective_date' => 'date',
    ];

    protected $fillable = ['department_id', 'is_active', 'approval_type', 'effective_date', 'delayed_day', 'verticals'];

    /** ODO-BD-01: the settings live in the logged-in company's expense database (VVNR has its own). */
    public function getConnectionName()
    {
        return session('expense_connection') ?: parent::getConnectionName();
    }
}
