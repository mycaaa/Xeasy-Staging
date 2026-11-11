<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class VendorType extends Model
{
    protected $table = 'vendor_type';
    protected $fillable = ['vendor_type', 'status', 'created_by', 'updated_by'];

    public function vendors()
    {
        return $this->hasMany(VendorList::class, 'vendor_type_id');
    }
}
