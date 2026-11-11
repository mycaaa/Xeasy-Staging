<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Permission extends Model
{
    // Always use the main VNR expense connection for permissions


    protected $fillable = ['name', 'permission_key', 'permission_group_id', 'status', 'created_by', 'updated_by', 'guard_name', 'created_at', 'updated_at'];

    public function group()
    {
        return $this->belongsTo(PermissionGroup::class, 'permission_group_id');
    }
}
