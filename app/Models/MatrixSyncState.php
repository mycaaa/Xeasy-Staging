<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MatrixSyncState extends Model
{
    protected $table = 'matrix_sync_states';

    protected $guarded = ['id'];

    protected $casts = [
        'last_synced_at' => 'datetime',
    ];

    /**
     * Get a state value by key
     */
    public static function getValue(string $key, $default = null)
    {
        $state = static::where('key', $key)->first();
        return $state ? $state->value : $default;
    }

    /**
     * Set a state value by key
     */
    public static function setValue(string $key, $value, ?string $status = 'idle', ?string $lastError = null): self
    {
        return static::updateOrCreate(
            ['key' => $key],
            [
                'value' => $value,
                'status' => $status,
                'last_synced_at' => now(),
                'last_error' => $lastError,
            ]
        );
    }
}
