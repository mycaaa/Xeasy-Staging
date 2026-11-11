<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Spatie\Activitylog\Traits\LogsActivity;

class Menu extends Model
{
    use HasFactory, LogsActivity;

    // Always use the main VNR expense connection for menus

    protected $table = 'menus';

    protected $fillable = [
        'title',
        'url',
        'icon',
        'parent_id',
        'permission_name',
        'permission_id',
        'order',
        'data_key',
        'company_access_id',
        'status',
        'created_by',
        'updated_by',
    ];

    public function parent()
    {
        return $this->belongsTo(Menu::class, 'parent_id');
    }

    public function children()
    {
        return $this->hasMany(Menu::class, 'parent_id');
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function updater()
    {
        return $this->belongsTo(User::class, 'updated_by');
    }
    public function getActivitylogOptions(): \Spatie\Activitylog\LogOptions
    {
        return \Spatie\Activitylog\LogOptions::defaults()
            ->logFillable()
            ->logOnlyDirty()
            ->setDescriptionForEvent(fn(string $eventName) => "Menu {$eventName}")
            ->useLogName('menu')
            ->logAll();
    }
}
