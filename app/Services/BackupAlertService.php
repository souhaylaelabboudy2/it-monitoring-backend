<?php

namespace App\Services;

use App\Models\Backup;
use App\Models\AlertSystem;
use App\Models\Incident;

class BackupAlertService
{
    /**
     * Process backup status and create alerts if needed
     */
    public static function processBackup(
        string $serverName,
        string $status,
        ?int $durationMinutes = null,
        ?float $sizeGb = null,
        ?string $errorMessage = null
    ): array {
        // Get last backup for this server
        $lastBackup = Backup::lastForServer($serverName);
        
        // Calculate consecutive failures
        $consecutiveFailures = 0;
        if ($status === 'failed') {
            $consecutiveFailures = ($lastBackup ? $lastBackup->consecutive_failures : 0) + 1;
        } else {
            $consecutiveFailures = 0; // Reset on success
        }

        // Create backup record
        $backup = Backup::create([
            'server_name' => $serverName,
            'status' => $status,
            'consecutive_failures' => $consecutiveFailures,
            'duration_minutes' => $durationMinutes,
            'size_gb' => $sizeGb,
            'error_message' => $errorMessage
        ]);

        $result = [
            'backup' => $backup,
            'alert_created' => false,
            'incident_created' => false,
            'action' => null
        ];

        // Handle failure
        if ($status === 'failed') {
            $alertResult = self::createBackupFailureAlert($backup, $consecutiveFailures);
            $result['alert_created'] = $alertResult['created'];
            $result['alert'] = $alertResult['alert'];
            $result['action'] = 'failed';

            // Create incident if critical
            if ($consecutiveFailures >= 3) {
                $incidentResult = IncidentService::handleBackupFailure($serverName, $consecutiveFailures);
                $result['incident_created'] = $incidentResult['created'];
                $result['incident'] = $incidentResult['incident'];
            }
        }
        // Handle success
        else {
            $result['action'] = 'success';
            
            // Resolve previous alerts if failures are now reset
            if ($lastBackup && $lastBackup->consecutive_failures > 0) {
                $result['alert_resolved'] = self::resolveBackupAlert($serverName);
                
                // Resolve incident if exists
                if ($result['alert_resolved']) {
                    $incidentKey = "backup_failed_" . str_replace(' ', '_', $serverName);
                    IncidentService::resolveByKey($incidentKey);
                }
            }
        }

        return $result;
    }

    /**
     * Create backup failure alert with proper escalation
     */
    private static function createBackupFailureAlert(Backup $backup, int $failureCount): array
    {
        $serverName = $backup->server_name;
        $key = "backup_failed_" . str_replace(' ', '_', $serverName);
        $severity = $failureCount >= 3 ? 'critical' : 'warning';

        // Check for existing alert
        $existingAlert = AlertSystem::where('key', $key)->first();

        if ($existingAlert) {
            // Update existing alert
            $existingAlert->update([
                'title' => $backup->getAlertTitle(),
                'message' => $backup->getAlertMessage(),
                'severity' => $severity,
                'status' => 'active',
                'last_seen' => now()
            ]);

            return [
                'created' => false,
                'updated' => true,
                'alert' => $existingAlert
            ];
        }

        // Create new alert
        $alert = AlertSystem::create([
            'key' => $key,
            'title' => $backup->getAlertTitle(),
            'message' => $backup->getAlertMessage(),
            'type' => 'backup',
            'severity' => $severity,
            'status' => 'active',
            'last_seen' => now()
        ]);

        return [
            'created' => true,
            'updated' => false,
            'alert' => $alert
        ];
    }

    /**
     * Resolve backup alert when backup succeeds
     */
    private static function resolveBackupAlert(string $serverName): bool
    {
        $key = "backup_failed_" . str_replace(' ', '_', $serverName);
        $alert = AlertSystem::where('key', $key)->first();

        if ($alert && $alert->status === 'active') {
            $alert->update([
                'status' => 'resolved',
                'severity' => 'info',
                'last_seen' => now()
            ]);
            return true;
        }

        return false;
    }

    /**
     * Get backup status summary for a server
     */
    public static function getServerSummary(string $serverName): array
    {
        $lastBackup = Backup::lastForServer($serverName);
        
        if (!$lastBackup) {
            return [
                'server_name' => $serverName,
                'status' => 'unknown',
                'last_backup' => null,
                'consecutive_failures' => 0,
                'alert' => null
            ];
        }

        $alert = AlertSystem::where('key', "backup_failed_" . str_replace(' ', '_', $serverName))
            ->first();

        return [
            'server_name' => $serverName,
            'status' => $lastBackup->status,
            'last_backup' => $lastBackup->created_at,
            'consecutive_failures' => $lastBackup->consecutive_failures,
            'duration_minutes' => $lastBackup->duration_minutes,
            'size_gb' => $lastBackup->size_gb,
            'severity' => $lastBackup->getAlertSeverity(),
            'alert' => $alert
        ];
    }

    /**
     * Get all servers with failures
     */
    public static function getFailedServers()
    {
        $backups = Backup::where('status', 'failed')
            ->where('consecutive_failures', '>', 0)
            ->latest('created_at')
            ->get();

        $servers = [];
        foreach ($backups as $backup) {
            if (!isset($servers[$backup->server_name])) {
                $servers[$backup->server_name] = $backup;
            }
        }

        return $servers;
    }

    /**
     * Get critical backup failures
     */
    public static function getCriticalFailures()
    {
        return Backup::where('status', 'failed')
            ->where('consecutive_failures', '>=', 3)
            ->latest('created_at')
            ->get();
    }

    /**
     * Get backup statistics
     */
    public static function getStatistics(): array
    {
        $totalBackups = Backup::count();
        $successfulBackups = Backup::where('status', 'success')->count();
        $failedBackups = Backup::where('status', 'failed')->count();
        $criticalFailures = Backup::where('consecutive_failures', '>=', 3)->count();

        return [
            'total' => $totalBackups,
            'successful' => $successfulBackups,
            'failed' => $failedBackups,
            'critical' => $criticalFailures,
            'success_rate' => $totalBackups > 0 ? round(($successfulBackups / $totalBackups) * 100, 2) : 0
        ];
    }
}
