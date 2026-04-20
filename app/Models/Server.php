<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Server extends Model
{
    public $timestamps = false;
    
    protected $fillable = [
        'name',
        'ip_address',
        'status',
        'cpu_usage',
        'ram_usage',
        'disk_usage',
        'last_check'
    ];
}
