<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class UserClaimType extends Model
{
    protected $table = 'tbl_user_claim_types';

    protected $fillable = [
        'user_id',
        'claim_type_id',
    ]; //
}
