<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Incident extends Model
{
    protected $fillable = [
        'key',
        'title',
        'description',
        'severity',
        'status'
    ];

    protected $casts = [
        'created_at' => 'datetime',
        'updated_at' => 'datetime'
    ];

    /**
     * Relationship: An incident has many alerts
     */
    public function alerts()
    {
        return $this->hasMany(Alert::class);
    }

    /**
     * Check if incident is resolved
     */
    public function isResolved(): bool
    {
        return $this->status === 'resolved';
    }

    /**
     * Check if incident is open
     */
    public function isOpen(): bool
    {
        return $this->status === 'open';
    }

    /**
     * Resolve this incident
     */
    public function resolve(): void
    {
        // Resolve all related alerts too
        $this->alerts()->update(['status' => 'resolved']);
        $this->update(['status' => 'resolved']);
    }

    /**
     * Acknowledge this incident
     */
    public function acknowledge(): void
    {
        $this->update(['status' => 'acknowledged']);
    }

    /**
     * Find incident by unique key
     */
    public static function findByKey(string $key): ?self
    {
        return self::where('key', $key)->first();
    }

    /**
     * Find open incident by key
     */
    public static function findOpenByKey(string $key): ?self
    {
        return self::where('key', $key)
            ->where('status', 'open')
            ->first();
    }

    /**
     * Create or update incident by key (prevents duplicates)
     * 
     * @param string $key Unique identifier (e.g., 'server_offline_AD')
     * @param string $title Human-readable title
     * @param string $description Detailed description
     * @param string $severity 'info', 'warning', or 'critical'
     * @return array ['incident' => Incident, 'created' => boolean]
     */
    public static function createOrUpdateByKey(
        string $key,
        string $title,
        string $description,
        string $severity = 'warning'
    ): array {
        // Check if open incident exists with this key
        $existingIncident = self::findOpenByKey($key);

        if ($existingIncident) {
            // Update existing incident
            $existingIncident->update([
                'title' => $title,
                'description' => $description,
                'severity' => $severity,
                'status' => 'open'
            ]);

            return [
                'incident' => $existingIncident,
                'created' => false,
                'action' => 'updated'
            ];
        }

        // Create new incident
        $incident = self::create([
            'key' => $key,
            'title' => $title,
            'description' => $description,
            'severity' => $severity,
            'status' => 'open'
        ]);

        return [
            'incident' => $incident,
            'created' => true,
            'action' => 'created'
        ];
    }
}