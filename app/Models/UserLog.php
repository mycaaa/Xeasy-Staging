<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class UserLog extends Model
{
    protected $table = 'user_logs';

    protected $fillable = [
        'user_id',
        'ip_address',
        'is_success',
        'user_agent',
        'timestamp',
        'status',
        'login_method',
    ];

    protected $casts = [
        'status' => 'array',
    ];

    public $timestamps = true;

    public function user()
    {
        // user_logs.user_id → users.id
        return $this->belongsTo(User::class, 'user_id', 'id');
    }
}
