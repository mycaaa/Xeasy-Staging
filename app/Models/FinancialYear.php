<?php

namespace App\Models;

use App\Traits\CompanyDatabaseConnection;
use Illuminate\Database\Eloquent\Model;

class FinancialYear extends Model
{
    use CompanyDatabaseConnection;

    protected $table = 'financialyear';
    protected $primaryKey = 'YearId';
    /**
     * The database connection name.
     */
    protected $connection = null; // Will be set dynamically by trait
    
    /**
     * The connection type for this model
     */
    protected $connectionType = 'expense'; // Specify this model uses expense connection

    public $timestamps = false;

    protected $fillable = [
        'Year',
        'y1',
        'y2',
        'FromDate',
        'Todate',
        'Status',
        'CrBy'
    ];

    public static function getYearById($yearId)
    {
        return self::where('YearId', $yearId)->value('Year');
    }
}
