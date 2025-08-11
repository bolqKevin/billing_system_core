<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class UserLoginLog extends Model
{
    use HasFactory;

    protected $table = 'user_login_log';

    protected $fillable = [
        'user_id',
        'username',
        'event_type',
        'created_at',
        'updated_at',
    ];

    protected $casts = [
        'created_at' => 'datetime',
    ];

    /**
     * Get the user that performed the login action.
     */
    public function user()
    {
        return $this->belongsTo(User::class);
    }
} 