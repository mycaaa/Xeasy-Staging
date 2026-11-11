<?php

namespace App\Observers;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Global observer that logs all Eloquent model events (created, updated, deleted)
 * to the activity_log table for complete audit trail across the application.
 *
 * Stores FULL before-state for updates and deletes so every change is fully traceable:
 *   - created:  properties.attributes = all new values
 *   - updated:  properties.before     = full record BEFORE update
 *              properties.after      = full record AFTER update
 *              properties.old        = only changed fields (old values)
 *              properties.attributes = only changed fields (new values)
 *   - deleted:  properties.before     = full record BEFORE deletion (complete snapshot)
 *              properties.old        = same as before (backward compat)
 */
class GlobalModelObserver
{
    /**
     * Models to skip logging for (to avoid infinite loops or noise).
     */
    protected array $skipModels = [
        'Spatie\\Activitylog\\Models\\Activity', // Don't log the log itself
        'App\\Models\\UserLog',                  // Already tracked separately
    ];

    /**
     * Attributes to never log (sensitive data).
     */
    protected array $hiddenAttributes = [
        'password', 'remember_token', 'two_factor_secret', 'two_factor_recovery_codes',
    ];

    public function created(Model $model): void
    {
        $this->logActivity($model, 'created');
    }

    public function updated(Model $model): void
    {
        $this->logActivity($model, 'updated');
    }

    public function deleted(Model $model): void
    {
        $this->logActivity($model, 'deleted');
    }

    /**
     * Log the activity to the activity_log table.
     */
    protected function logActivity(Model $model, string $event): void
    {
        // Skip if model is in the skip list
        if (in_array(get_class($model), $this->skipModels)) {
            return;
        }

        // Skip if model already uses Spatie's LogsActivity trait (it logs itself)
        if ($this->modelHasSpatieLogging($model)) {
            return;
        }

        try {
            $userId = Auth::id();
            $modelClass = get_class($model);
            $modelShortName = class_basename($model);
            $modelId = $model->getKey();

            // Build properties based on event
            $properties = $this->buildProperties($model, $event);

            // Build description
            $description = "{$modelShortName} was {$event}";
            if ($modelId) {
                $description .= " (ID: {$modelId})";
            }

            // Determine log_name from model
            $logName = $this->getLogName($model);

            DB::table('activity_log')->insert([
                'log_name' => $logName,
                'description' => $description,
                'subject_type' => $modelClass,
                'subject_id' => $modelId,
                'event' => $event,
                'causer_type' => $userId ? 'App\\Models\\User' : null,
                'causer_id' => $userId,
                'properties' => json_encode($properties),
                'batch_uuid' => null,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        } catch (\Exception $e) {
            // Silently fail — never break the app for logging
            Log::error('GlobalModelObserver error: ' . $e->getMessage());
        }
    }

    /**
     * Check if model already uses Spatie's LogsActivity trait.
     */
    protected function modelHasSpatieLogging(Model $model): bool
    {
        return in_array(
            'Spatie\\Activitylog\\Traits\\LogsActivity',
            class_uses_recursive($model)
        );
    }

    /**
     * Build the properties array based on the event type.
     *
     * For UPDATES: stores full before/after snapshots + the specific changed fields.
     * For DELETES: stores the complete record that was deleted.
     * For CREATES: stores all attributes of the new record.
     */
    protected function buildProperties(Model $model, string $event): array
    {
        $properties = [];

        switch ($event) {
            case 'created':
                $attributes = $this->filterAttributes($model->getAttributes());
                $properties['attributes'] = $attributes;
                break;

            case 'updated':
                // ── Full snapshot BEFORE update (original state of entire record) ──
                $beforeAll = $this->filterAttributes($model->getRawOriginal());
                $properties['before'] = $beforeAll;

                // ── Full snapshot AFTER update (current state of entire record) ──
                $afterAll = $this->filterAttributes($model->getAttributes());
                $properties['after'] = $afterAll;

                // ── Only changed fields (for quick diff display — backward compat) ──
                $dirty = $this->filterAttributes($model->getDirty());
                $original = [];
                foreach (array_keys($dirty) as $key) {
                    $original[$key] = $model->getOriginal($key);
                }
                $properties['old'] = $original;
                $properties['attributes'] = $dirty;

                // ── Summary of what changed ──
                $properties['changed_fields'] = array_keys($dirty);
                break;

            case 'deleted':
                // ── Full snapshot of the record BEFORE deletion ──
                $allAttributes = $this->filterAttributes($model->getAttributes());
                $properties['before'] = $allAttributes;
                $properties['old'] = $allAttributes; // backward compat

                // ── Mark what was deleted for clarity ──
                $properties['deleted_record'] = [
                    'table' => $model->getTable(),
                    'primary_key' => $model->getKeyName(),
                    'primary_value' => $model->getKey(),
                ];
                break;
        }

        return $properties;
    }

    /**
     * Filter out sensitive attributes.
     */
    protected function filterAttributes(array $attributes): array
    {
        foreach ($this->hiddenAttributes as $hidden) {
            unset($attributes[$hidden]);
        }

        // Limit to 50 fields max to avoid huge JSON (increased from 30 for full snapshots)
        if (count($attributes) > 50) {
            $attributes = array_slice($attributes, 0, 50, true);
            $attributes['_truncated'] = true;
        }

        return $attributes;
    }

    /**
     * Determine a meaningful log_name from the model.
     */
    protected function getLogName(Model $model): string
    {
        $class = class_basename($model);

        $moduleMap = [
            'User' => 'User Management',
            'Menu' => 'Menu Management',
            'EmailTemplate' => 'Email Templates',
            'ExpenseClaim' => 'Claims',
            'MonthExpenseFinal' => 'Claims',
            'GeneralSettings' => 'Settings',
            'ClaimType' => 'Claim Types',
            'ClaimRatingRule' => 'Claim Rating',
            'ClaimRatingResult' => 'Claim Rating',
            'ClaimRatingSetting' => 'Claim Rating',
            'AdvanceRequest' => 'Advance Requests',
            'AdvanceRequestMaster' => 'Advance Requests',
            'AdvanceRequestAction' => 'Advance Requests',
            'EmployeeVehicle' => 'Employee Vehicles',
            'VendorList' => 'Vendor Management',
            'VendorType' => 'Vendor Management',
            'FinancialYear' => 'Financial Year',
            'Permission' => 'Permission Management',
            'PermissionGroup' => 'Permission Management',
            'Roles' => 'Role Management',
            'CityCategory' => 'City Categories',
            'ExpenseHeadMapping' => 'Expense Heads',
            'ExpenseHeadCategory' => 'Expense Heads',
            'Airport' => 'Airport Management',
            'EligibilityPolicy' => 'Eligibility',
        ];

        return $moduleMap[$class] ?? str_replace(['_', '-'], ' ', \Illuminate\Support\Str::snake($class, ' '));
    }
}
