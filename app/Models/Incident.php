<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Incident extends Model
{
    protected $fillable = [
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
}