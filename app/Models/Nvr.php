<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Nvr extends Model
{
    protected $table = 'nvrs';
    public $timestamps = false;
    
    protected $fillable = [
        'name',
        'type',
        'sync_status',
        'cameras_count',
        'status',
        'disk_usage',
        'last_sync',
        'last_check',
        'consecutive_sync_losses',
        'last_offline_duration'
    ];

    protected $casts = [
        'type' => 'string',
        'sync_status' => 'string',
        'cameras_count' => 'integer',
        'disk_usage' => 'float',
        'consecutive_sync_losses' => 'integer'
    ];

    /**
     * Get most recent NVR by name
     */
    public static function lastForServer(string $name)
    {
        return self::where('name', $name)->latest('last_check')->first();
    }

    /**
     * Get consecutive sync losses for master NVR
     */
    public function getConsecutiveSyncLosses(): int
    {
        return $this->consecutive_sync_losses ?? 0;
    }

    /**
     * Get alert severity for this NVR
     */
    public function getAlertSeverity(): string
    {
        // Offline = critical
        if ($this->status === 'offline') {
            return 'critical';
        }

        // Master NVR sync issues
        if ($this->type === 'master') {
            $syncLosses = $this->consecutive_sync_losses ?? 0;
            if ($syncLosses >= 2) {
                return 'critical';
            } elseif ($syncLosses === 1) {
                return 'warning';
            }
        }

        // Disk usage
        if ($this->disk_usage > 90) {
            return 'warning';
        }

        return 'info';
    }

    /**
     * Get professional alert title
     */
    public function getAlertTitle(): string
    {
        $severity = $this->getAlertSeverity();

        if ($this->status === 'offline') {
            return '🔴 CRITICAL: NVR Offline - ' . $this->name;
        }

        if ($this->type === 'master' && ($this->consecutive_sync_losses ?? 0) >= 2) {
            return '🔴 CRITICAL: NVR Sync Lost (×' . $this->consecutive_sync_losses . ') - ' . $this->name;
        }

        if ($this->type === 'master' && ($this->consecutive_sync_losses ?? 0) === 1) {
            return '⚠️ WARNING: NVR Sync Lost - ' . $this->name;
        }

        if ($this->disk_usage > 90) {
            return '⚠️ WARNING: NVR Disk Full - ' . $this->name;
        }

        return '✅ OK: NVR ' . $this->name;
    }

    /**
     * Get professional alert message
     */
    public function getAlertMessage(): string
    {
        if ($this->status === 'offline') {
            return "NVR {$this->name} is OFFLINE. Last check: {$this->last_check?->diffForHumans()}";
        }

        if ($this->type === 'master' && ($this->consecutive_sync_losses ?? 0) >= 1) {
            $losses = $this->consecutive_sync_losses;
            $status = $losses >= 2 ? 'ESCALATED to CRITICAL' : 'detected';
            return "Master NVR {$this->name} sync loss #{$losses} - {$status}. Cameras: {$this->cameras_count}, Disk: {$this->disk_usage}%";
        }

        if ($this->disk_usage > 90) {
            return "NVR {$this->name} disk usage is {$this->disk_usage}%. Available cameras: {$this->cameras_count}";
        }

        return "NVR {$this->name} status is OK. Type: {$this->type}, Cameras: {$this->cameras_count}, Disk: {$this->disk_usage}%";
    }
}