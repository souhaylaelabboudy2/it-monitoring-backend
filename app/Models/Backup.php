<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Backup extends Model
{
   protected $fillable = [
    'server_id',
    'status',
    'backup_date'
];
}
