<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Backup extends Model
{
    public $timestamps = true;
    
    protected $fillable = [
        'server_name',
        'status'
    ];

    protected $casts = [
        'backup_date' => 'datetime'
    ];
}