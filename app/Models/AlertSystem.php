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
        'incident_id',
        'last_seen'
    ];

    protected $casts = [
        'last_seen' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime'
    ];

    /**
     * Relationship: An alert belongs to an incident
     */
    public function incident()
    {
        return $this->belongsTo(Incident::class);
    }

    /**
     * Find alert by unique key
     */
    public static function findByKey(string $key): ?self
    {
        return self::where('key', $key)->first();
    }

    /**
     * Check if alert is resolved
     */
    public function isResolved(): bool
    {
        return $this->status === 'resolved';
    }
}
