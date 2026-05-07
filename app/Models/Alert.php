<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Alert extends Model
{
    protected $fillable = [
        'title',
        'message',
        'type',
        'severity',
        'key',
        'status',
        'incident_id'
    ];

    protected $casts = [
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

    /**
     * Resolve this alert
     */
    public function resolve(): void
    {
        $this->update(['status' => 'resolved']);
    }

    /**
     * Mark as acknowledged
     */
    public function acknowledge(): void
    {
        $this->update(['status' => 'acknowledged']);
    }
}