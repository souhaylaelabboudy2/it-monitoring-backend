<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Nvr extends Model
{
    protected $fillable = [
    'name',
    'type',
    'cameras_count',
    'status',
    'disk_usage',
    'last_sync'
];
}
