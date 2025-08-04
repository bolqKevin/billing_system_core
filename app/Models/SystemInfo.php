<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SystemInfo extends Model
{
    use HasFactory;

    protected $table = 'system_info';

    protected $fillable = [
        'system_name',
        'version',
        'release_date',
        'owner',
        'developer',
        'technologies',
    ];

    protected $casts = [
        'release_date' => 'date',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];
} 