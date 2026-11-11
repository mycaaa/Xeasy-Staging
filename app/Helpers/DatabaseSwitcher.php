<?php

namespace App\Helpers;

use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Session;

class DatabaseSwitcher
{
    /**
     * Set database connections based on company selection
     *
     * @param int $companyId
     * @return void
     */
    public static function setCompanyConnections($companyId)
    {
        // Clear any existing database connections to force reconnection
        DB::purge('expense');
        DB::purge('hrims');
        DB::purge('expense_vvnr');
        DB::purge('expense_nursery');
        DB::purge('hrims_vvnr');

        // IMPORTANT: Keep default connection as 'expense' (VNR) for shared tables like users
        // Only use specific connections for company-specific data
        Config::set('database.default', 'expense');

        // Store company ID and connection names in session for models and queries to use
        if (function_exists('session')) {
            $expenseConnection = self::getExpenseConnection($companyId);
            $hrimsConnection = self::getHrimsConnection($companyId);
            
            session([
                'company_id' => $companyId,
                'expense_connection' => $expenseConnection,
                'hrims_connection' => $hrimsConnection,
            ]);
        }

        // Force Laravel to refresh the default connection
        DB::setDefaultConnection('expense');
    }

    /**
     * Get the appropriate expense database connection name
     *
     * @param int|null $companyId
     * @return string
     */
    public static function getExpenseConnection($companyId = null)
    {
        $companyId = $companyId ?? Session::get('company_id', 1);

        if ($companyId == 11) {
            return 'expense_vvnr';
        }
        if ($companyId == 3) {
            return 'expense_nursery';
        }
        return 'expense';
    }

    /**
     * Get the appropriate HRIMS database connection name
     *
     * @param int|null $companyId
     * @return string
     */
    public static function getHrimsConnection($companyId = null)
    {
        // Fixed: Always use the main 'hrims' connection for the second database
        return 'hrims';
    }

    /**
     * Get company name by ID
     *
     * @param int $companyId
     * @return string
     */
    public static function getCompanyName($companyId)
    {
        switch ((int) $companyId) {
            case 1:
                return 'VNR Seeds Pvt Ltd';
            case 2:
                return 'VNR Group Concern';
            case 3:
                return 'VNR Nursery Pvt Ltd';
            case 4:
                return 'Teamlease Services Ltd';
            case 11:
                return 'VVNR';
            default:
                return 'VNR Seeds Pvt Ltd';
        }
    }

    /**
     * Get the HRIMS database name for cross-database queries
     *
     * @param int|null $companyId
     * @return string
     */
    /**
     * Physical schema name behind an expense connection name (e.g. connection "expense"
     * may point at database "expense_staging"). Use this, never the connection name,
     * when qualifying a table across databases.
     */
    public static function getExpenseDatabaseName($connection = null)
    {
        $connection = $connection ?: (session('expense_connection') ?: 'expense');
        return \Illuminate\Support\Facades\DB::connection($connection)->getDatabaseName();
    }

    public static function getHrimsDatabaseName($companyId = null)
    {
        return env('DB_HRIMS_DATABASE', 'hrims');
    }

    /**
     * Replace hardcoded 'hrims.' references with the correct database name
     *
     * @param string $query
     * @param int|null $companyId
     * @return string
     */
    public static function replaceHrimsReferences($query, $companyId = null)
    {
        $hrimsDbName = self::getHrimsDatabaseName($companyId);
        return str_replace('hrims.', $hrimsDbName . '.', $query);
    }

    /**
     * Initialize database connections for the current session
     *
     * @return void
     */
    public static function initializeSessionConnections()
    {
        $companyId = Session::get('company_id', 1);
        self::setCompanyConnections($companyId);
    }
}