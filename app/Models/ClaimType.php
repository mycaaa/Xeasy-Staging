<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ClaimType extends Model
{

    protected $table = 'claimtype';
    protected $primaryKey = 'ClaimId';
    public $timestamps = false;
    protected $fillable = ['ClaimId', 'cgId', 'ClaimName', 'ClaimCode', 'focus_code', 'color_code', 'ClaimStatus', 'ClaimCrBy'];

    public function group()
    {
        return $this->belongsTo(ClaimGroup::class, 'cgId', 'cgId');
    }

    public static function getCgIdByClaimId($claimId)
    {
        return self::where('ClaimId', $claimId)->value('cgId');
    }
}
