<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AlertSystem extends Model
{
    protected $table = 'alert_system';
    
    protected $fillable = [
        'key',
        'title',
        'message',
        'type',
        'severity',
        'status',
        'last_seen'
    ];

    protected $casts = [
        'last_seen' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime'
    ];
}
