<?php

namespace App\Services;

use App\Models\Incident;
use App\Models\AlertSystem;

class IncidentService
{
    /**
     * Create or update incident for server offline
     */
    public static function handleServerOffline(string $serverName): array
    {
        $key = "server_offline_" . str_replace(' ', '_', $serverName);
        
        return Incident::createOrUpdateByKey(
            key: $key,
            title: "🔴 Server OFFLINE: $serverName",
            description: "Server '$serverName' is not responding to health checks and is considered offline.",
            severity: 'critical'
        );
    }

    /**
     * Create or update incident for backup failure
     */
    public static function handleBackupFailure(string $serverName, int $failureCount = 1): array
    {
        $key = "backup_failed_" . str_replace(' ', '_', $serverName);
        
        $description = match(true) {
            $failureCount >= 3 => "Backup for '$serverName' has failed $failureCount times consecutively. Critical action required.",
            $failureCount >= 2 => "Backup for '$serverName' has failed $failureCount times consecutively. Escalation needed.",
            default => "Backup for '$serverName' failed. First failure detected."
        };

        return Incident::createOrUpdateByKey(
            key: $key,
            title: "💾 Backup FAILED: $serverName (×$failureCount)",
            description: $description,
            severity: $failureCount >= 3 ? 'critical' : 'warning'
        );
    }

    /**
     * Create or update incident for NVR offline
     */
    public static function handleNvrOffline(string $nvrName): array
    {
        $key = "nvr_offline_" . str_replace(' ', '_', $nvrName);
        
        return Incident::createOrUpdateByKey(
            key: $key,
            title: "📹 NVR OFFLINE: $nvrName",
            description: "NVR system '$nvrName' is offline and not responding to monitoring requests.",
            severity: 'critical'
        );
    }

    /**
     * Create or update incident for NVR sync loss
     */
    public static function handleNvrSyncLoss(string $nvrName, int $syncFailureCount = 1): array
    {
        $key = "nvr_sync_lost_" . str_replace(' ', '_', $nvrName);
        
        $description = match(true) {
            $syncFailureCount >= 2 => "NVR Master '$nvrName' has lost synchronization for $syncFailureCount consecutive cycles. Critical issue.",
            default => "NVR Master '$nvrName' lost synchronization. First occurrence."
        };

        return Incident::createOrUpdateByKey(
            key: $key,
            title: "🔗 NVR SYNC LOST: $nvrName (Cycle ×$syncFailureCount)",
            description: $description,
            severity: $syncFailureCount >= 2 ? 'critical' : 'warning'
        );
    }

    /**
     * Resolve incident by key and log resolution
     */
    public static function resolveByKey(string $key): bool
    {
        $incident = Incident::findByKey($key);
        
        if ($incident && !$incident->isResolved()) {
            $incident->resolve();
            return true;
        }
        
        return false;
    }

    /**
     * Resolve all incidents for a specific resource
     */
    public static function resolveForResource(string $resourceName): int
    {
        $pattern = str_replace(' ', '_', $resourceName);
        
        $resolved = Incident::where('key', 'like', "%$pattern%")
            ->where('status', 'open')
            ->get();

        $count = 0;
        foreach ($resolved as $incident) {
            $incident->resolve();
            $count++;
        }

        return $count;
    }

    /**
     * Get critical open incidents
     */
    public static function getCriticalOpen()
    {
        return Incident::where('severity', 'critical')
            ->where('status', 'open')
            ->latest('created_at')
            ->get();
    }

    /**
     * Get all open incidents
     */
    public static function getOpenIncidents()
    {
        return Incident::where('status', 'open')
            ->latest('created_at')
            ->get();
    }

    /**
     * Check if resource has open incident
     */
    public static function hasOpenIncident(string $resourceName): bool
    {
        $pattern = str_replace(' ', '_', $resourceName);
        
        return Incident::where('key', 'like', "%$pattern%")
            ->where('status', 'open')
            ->exists();
    }
}
