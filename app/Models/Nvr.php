<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Nvr extends Model
{
    protected $table = 'nvr';
    public $timestamps = false;
    
    protected $fillable = [
        'name',
        'type',
        'sync_status',
        'cameras_count',
        'status',
        'disk_usage',
        'last_sync',
        'last_check'
    ];

    protected $casts = [
        'type' => 'string',
        'sync_status' => 'string',
        'cameras_count' => 'integer',
        'disk_usage' => 'float'
    ];
}