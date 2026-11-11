<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ClaimGroup extends Model
{

    protected $table = 'claimgroup';
    protected $primaryKey = 'cgId';
    public $timestamps = false;
    protected $fillable = ['cgName']; // Add other fillable fields as needed
}