<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class UserMovementLog extends Model
{
    use HasFactory;

    public $timestamps = false;

    protected $table = 'user_movements_log';

    protected $fillable = [
        'user_id',
        'action_performed',
        'affected_record_id',
    ];

    protected $casts = [
        'created_at' => 'datetime',
    ];

    /**
     * Get the user that performed the action.
     */
    public function user()
    {
        return $this->belongsTo(User::class);
    }
} 