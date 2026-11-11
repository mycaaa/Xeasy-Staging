<?php

namespace App\Traits;

use App\Helpers\DatabaseSwitcher;
use Illuminate\Support\Facades\Session;
use Illuminate\Support\Facades\DB;

trait CompanyDatabaseConnection
{
    /**
     * Get the database connection name based on company and connection type
     *
     * @param string $connectionType 'expense' or 'hrims'
     * @return string
     */
    public function getConnectionName($connectionType = 'expense')
    {
        $companyId = Session::get('company_id', 1);
        
        if ($connectionType === 'hrims') {
            return DatabaseSwitcher::getHrimsConnection($companyId);
        }
        
        return DatabaseSwitcher::getExpenseConnection($companyId);
    }

    /**
     * Set the connection dynamically based on company
     */
    public function setDynamicConnection()
    {
        if (Session::has('company_id')) {
            $connectionType = property_exists($this, 'connectionType') ? $this->connectionType : 'expense';
            $newConnection = $this->getConnectionName($connectionType);
            
            // Force purge the old connection to ensure fresh connection
            if ($this->connection && $this->connection !== $newConnection) {
                DB::purge($this->connection);
            }
            
            $this->connection = $newConnection;
        }
    }

    /**
     * Override the newQuery method to ensure proper connection
     */
    public function newQuery()
    {
        $this->setDynamicConnection();
        return parent::newQuery();
    }

    /**
     * Override newModelQuery to ensure proper connection for static calls
     */
    public function newModelQuery()
    {
        $this->setDynamicConnection();
        return parent::newModelQuery();
    }

    /**
     * Override resolveConnection to ensure proper connection (static method)
     */
    public static function resolveConnection($connection = null)
    {
        // If a specific connection is requested, use it
        if ($connection) {
            return parent::resolveConnection($connection);
        }
        
        // Try multiple ways to determine the company ID
        $companyId = null;
        
        // Method 1: Try session
        if (function_exists('session') && session()->isStarted()) {
            $companyId = session('company_id');
        }
        
        // Method 2: Try Session facade
        if (!$companyId && class_exists('\Illuminate\Support\Facades\Session')) {
            try {
                $companyId = Session::get('company_id');
            } catch (\Exception $e) {
                // Session not available yet
            }
        }
        
        // Method 3: Try to infer from current default connection
        if (!$companyId) {
            $currentDefault = config('database.default');
            if (strpos($currentDefault, 'vvnr') !== false) {
                $companyId = 2;
            } else {
                $companyId = 1;
            }
        }
        
        // Fallback to company 1 if still no company ID
        $companyId = $companyId ?: 1;
        
        // Check if the model has a connectionType property
        $instance = new static();
        $connectionType = property_exists($instance, 'connectionType') ? $instance->connectionType : 'expense';
        
        if ($connectionType === 'hrims') {
            $connectionName = DatabaseSwitcher::getHrimsConnection($companyId);
        } else {
            $connectionName = DatabaseSwitcher::getExpenseConnection($companyId);
        }
        
        return parent::resolveConnection($connectionName);
    }

    /**
     * Override getConnectionName to always use dynamic connection
     */
    public function getDynamicConnectionName()
    {
        $this->setDynamicConnection();
        return $this->connection;
    }

    /**
     * Override newInstance to ensure proper connection
     */
    public function newInstance($attributes = [], $exists = false)
    {
        $model = parent::newInstance($attributes, $exists);
        $model->setDynamicConnection();
        return $model;
    }

    /**
     * Boot the trait - called when the model is booted
     */
    public static function bootCompanyDatabaseConnection()
    {
        // Set connection on model events
        static::creating(function ($model) {
            $model->setDynamicConnection();
        });
        
        static::updating(function ($model) {
            $model->setDynamicConnection();
        });

        static::retrieved(function ($model) {
            $model->setDynamicConnection();
        });
    }
}