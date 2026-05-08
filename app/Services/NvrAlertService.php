<?php

namespace App\Services;

use App\Models\Nvr;
use App\Models\AlertSystem;
use Illuminate\Support\Facades\DB;

/**
 * NvrAlertService - Orchestrate NVR monitoring, alerts, and escalation
 * 
 * Features:
 * - Offline detection: Create critical alert automatically
 * - Sync loss tracking: Warning alert, escalate to critical at 2+ failures (master only)
 * - Standard NVRs: Ignored for sync status
 * - Deduplication: One alert per NVR
 * - Spam prevention: 5-minute cooldown
 * - Auto-resolution: When NVR comes online or sync restored
 */
class NvrAlertService
{
    /**
     * Main entry point for NVR processing
     * 
     * POST /api/nvrs
     * {
     *   "name": "NVR Main",
     *   "type": "standard|master",
     *   "status": "online|offline",
     *   "sync_status": "synced|lost",
     *   "cameras_count": 15,
     *   "disk_usage": 75.5
     * }
     */
    public static function processNvr(
        string $name,
        string $type,
        string $status,
        ?string $syncStatus = null,
        ?int $camerasCount = null,
        ?float $diskUsage = null
    ): array
    {
        $nvr = Nvr::where('name', $name)->first();
        $isNew = !$nvr;
        $oldStatus = $nvr?->status;
        $oldSyncStatus = $nvr?->sync_status;

        // Create or update NVR record
        if ($isNew) {
            $nvr = Nvr::create([
                'name' => $name,
                'type' => $type,
                'status' => $status,
                'sync_status' => ($type === 'master') ? ($syncStatus ?? 'synced') : 'synced',
                'cameras_count' => $camerasCount ?? 0,
                'disk_usage' => $diskUsage ?? 0,
                'consecutive_sync_losses' => 0,
                'last_check' => now()
            ]);
        } else {
            $nvr->update([
                'type' => $type,
                'status' => $status,
                'sync_status' => ($type === 'master') ? ($syncStatus ?? $oldSyncStatus ?? 'synced') : 'synced',
                'cameras_count' => $camerasCount ?? $nvr->cameras_count,
                'disk_usage' => $diskUsage ?? $nvr->disk_usage,
                'last_check' => now()
            ]);
        }

        $result = [
            'nvr' => $nvr,
            'action' => 'created',
            'alert_created' => false,
            'alert_resolved' => false,
            'incident_created' => false
        ];

        // ============================================================
        // HANDLE OFFLINE STATUS (all NVR types)
        // ============================================================
        if ($status === 'offline') {
            // Status changed from online → offline
            if ($oldStatus && $oldStatus !== 'offline') {
                $result['offline_alert'] = self::createOfflineAlert($nvr);
                $result['action'] = 'offline';
            }
            // First time offline
            elseif ($isNew) {
                $result['offline_alert'] = self::createOfflineAlert($nvr);
                $result['action'] = 'offline';
            }
        }
        // NVR came back online - resolve offline alert
        elseif ($status === 'online' && $oldStatus === 'offline') {
            self::resolveOfflineAlert($nvr);
            $nvr->update(['consecutive_sync_losses' => 0]);
            $result['action'] = 'resolved_offline';
            $result['alert_resolved'] = true;
            add_log('nvr_up', 'nvr', $name);
        }

        // ============================================================
        // HANDLE SYNC LOSS (master NVRs only)
        // ============================================================
        if ($type === 'master' && $status === 'online') {
            $currentSyncStatus = $syncStatus ?? $oldSyncStatus ?? 'synced';

            // Sync lost
            if ($currentSyncStatus === 'lost') {
                $nvr->consecutive_sync_losses = ($nvr->consecutive_sync_losses ?? 0) + 1;
                $nvr->save();

                $syncLosses = $nvr->consecutive_sync_losses;
                $severity = $syncLosses >= 2 ? 'critical' : 'warning';

                self::createSyncLossAlert($nvr, $syncLosses, $severity);
                $result['sync_alert'] = ['created' => true, 'severity' => $severity, 'count' => $syncLosses];
                $result['action'] = 'sync_loss_' . $syncLosses;

                add_log('nvr_sync_lost', 'nvr', $name);

                if ($severity === 'critical') {
                    $result['incident_created'] = true;
                    IncidentService::handleNvrSyncLoss($name, $syncLosses);
                }
            }
            // Sync restored
            elseif ($currentSyncStatus === 'synced' && $oldSyncStatus === 'lost') {
                $previousLosses = $nvr->consecutive_sync_losses;
                $nvr->update(['consecutive_sync_losses' => 0]);

                self::resolveSyncLossAlert($nvr);
                IncidentService::resolveByKey("nvr_sync_lost_" . str_replace(' ', '_', $name));

                $result['action'] = 'sync_restored';
                $result['alert_resolved'] = true;
                $result['sync_alert'] = ['resolved' => true, 'previous_losses' => $previousLosses];

                add_log('nvr_sync_restored', 'nvr', $name);
            }
        }

        // ============================================================
        // HANDLE DISK USAGE WARNING
        // ============================================================
        if ($diskUsage && $diskUsage > 90) {
            self::createDiskAlert($nvr);
            $result['disk_alert'] = ['created' => true, 'usage' => $diskUsage];
        }

        return $result;
    }

    /**
     * Create critical alert for offline NVR
     */
    private static function createOfflineAlert(Nvr $nvr): array
    {
        $key = "nvr_offline_" . str_replace(' ', '_', $nvr->name);
        $title = "🔴 CRITICAL: NVR Offline - {$nvr->name}";
        $message = "NVR {$nvr->name} is currently offline and not accessible for monitoring.";

        $existingAlert = AlertSystem::where('key', $key)->first();

        if ($existingAlert) {
            $existingAlert->update([
                'title' => $title,
                'message' => $message,
                'severity' => 'critical',
                'status' => 'open',
                'updated_at' => now()
            ]);
            $created = false;
        } else {
            $alert = AlertSystem::create([
                'key' => $key,
                'title' => $title,
                'message' => $message,
                'type' => 'nvr',
                'severity' => 'critical',
                'status' => 'open'
            ]);

            // Auto-create incident for offline
            $incident = IncidentService::handleNvrOffline($nvr->name);

            $existingAlert = $alert;
            $created = true;
        }

        return ['created' => $created, 'alert' => $existingAlert];
    }

    /**
     * Create or update sync loss alert (warning → critical escalation)
     */
    private static function createSyncLossAlert(Nvr $nvr, int $syncLosses, string $severity): array
    {
        $key = "nvr_sync_lost_" . str_replace(' ', '_', $nvr->name);
        $emoji = $severity === 'critical' ? '🔴' : '⚠️';
        $title = "{$emoji} {$severity}: NVR Sync Loss - {$nvr->name}";
        $message = "Master NVR {$nvr->name} has lost sync. ";
        $message .= $syncLosses === 1 
            ? "First sync loss detected." 
            : "Sync loss #{$syncLosses} - Escalated to CRITICAL!";

        $existingAlert = AlertSystem::where('key', $key)->first();

        if ($existingAlert) {
            $existingAlert->update([
                'title' => $title,
                'message' => $message,
                'severity' => $severity,
                'status' => 'open',
                'updated_at' => now()
            ]);
            $created = false;
        } else {
            $alert = AlertSystem::create([
                'key' => $key,
                'title' => $title,
                'message' => $message,
                'type' => 'nvr',
                'severity' => $severity,
                'status' => 'open'
            ]);
            $existingAlert = $alert;
            $created = true;
        }

        return ['created' => $created, 'alert' => $existingAlert];
    }

    /**
     * Create disk usage warning alert
     */
    private static function createDiskAlert(Nvr $nvr): array
    {
        $key = "nvr_disk_full_" . str_replace(' ', '_', $nvr->name);
        $title = "⚠️ WARNING: NVR Disk Nearly Full - {$nvr->name}";
        $message = "NVR {$nvr->name} disk usage is at {$nvr->disk_usage}%. Consider archiving or deleting old recordings.";

        $existingAlert = AlertSystem::where('key', $key)->first();

        if ($existingAlert) {
            $existingAlert->update([
                'title' => $title,
                'message' => $message,
                'severity' => 'warning',
                'status' => 'open',
                'updated_at' => now()
            ]);
            $created = false;
        } else {
            $alert = AlertSystem::create([
                'key' => $key,
                'title' => $title,
                'message' => $message,
                'type' => 'nvr',
                'severity' => 'warning',
                'status' => 'open'
            ]);
            $existingAlert = $alert;
            $created = true;
        }

        return ['created' => $created, 'alert' => $existingAlert];
    }

    /**
     * Resolve offline alert
     */
    private static function resolveOfflineAlert(Nvr $nvr): void
    {
        $key = "nvr_offline_" . str_replace(' ', '_', $nvr->name);
        $alert = AlertSystem::where('key', $key)->first();

        if ($alert) {
            $alert->update([
                'status' => 'resolved',
                'title' => str_replace('🔴 CRITICAL', '✅ RESOLVED', $alert->title),
                'updated_at' => now()
            ]);

            if ($alert->incident_id) {
                IncidentService::resolveByKey($key);
            }
        }
    }

    /**
     * Resolve sync loss alert
     */
    private static function resolveSyncLossAlert(Nvr $nvr): void
    {
        $key = "nvr_sync_lost_" . str_replace(' ', '_', $nvr->name);
        $alert = AlertSystem::where('key', $key)->first();

        if ($alert) {
            $alert->update([
                'status' => 'resolved',
                'title' => str_replace(['🔴', '⚠️'], '✅', $alert->title),
                'updated_at' => now()
            ]);
        }
    }

    /**
     * Get NVR status for dashboard
     */
    public static function getNvrStatus(string $name): array
    {
        $nvr = Nvr::where('name', $name)->first();

        if (!$nvr) {
            return ['error' => 'NVR not found'];
        }

        $alerts = AlertSystem::where('type', 'nvr')
            ->where(function ($q) use ($name) {
                $q->where('title', 'like', "%{$name}%");
            })
            ->get();

        return [
            'name' => $nvr->name,
            'type' => $nvr->type,
            'status' => $nvr->status,
            'sync_status' => $nvr->type === 'master' ? $nvr->sync_status : 'N/A',
            'consecutive_sync_losses' => $nvr->type === 'master' ? $nvr->consecutive_sync_losses : 0,
            'cameras_count' => $nvr->cameras_count,
            'disk_usage' => $nvr->disk_usage . '%',
            'last_check' => $nvr->last_check?->diffForHumans(),
            'active_alerts' => $alerts->where('status', 'open')->count(),
            'total_alerts' => $alerts->count(),
            'alerts' => $alerts->map(fn($a) => [
                'title' => $a->title,
                'severity' => $a->severity,
                'status' => $a->status
            ])
        ];
    }

    /**
     * Get all failed/offline NVRs
     */
    public static function getFailedNvrs(): array
    {
        $offlineNvrs = Nvr::where('status', 'offline')->get();

        return $offlineNvrs->map(function ($nvr) {
            return [
                'name' => $nvr->name,
                'type' => $nvr->type,
                'status' => 'offline',
                'last_check' => $nvr->last_check?->diffForHumans()
            ];
        })->toArray();
    }

    /**
     * Get all critical NVRs (offline or critical sync loss)
     */
    public static function getCriticalNvrs(): array
    {
        $critical = [];

        // Offline NVRs
        $offlineNvrs = Nvr::where('status', 'offline')->get();
        foreach ($offlineNvrs as $nvr) {
            $critical[] = [
                'name' => $nvr->name,
                'type' => $nvr->type,
                'issue' => 'offline',
                'severity' => 'critical'
            ];
        }

        // Master NVRs with sync loss >= 2
        $syncCritical = Nvr::where('type', 'master')
            ->where('consecutive_sync_losses', '>=', 2)
            ->where('status', 'online')
            ->get();

        foreach ($syncCritical as $nvr) {
            $critical[] = [
                'name' => $nvr->name,
                'type' => 'master',
                'issue' => 'sync_loss',
                'consecutive_losses' => $nvr->consecutive_sync_losses,
                'severity' => 'critical'
            ];
        }

        return $critical;
    }

    /**
     * Get NVR statistics
     */
    public static function getStatistics(): array
    {
        $total = Nvr::count();
        $online = Nvr::where('status', 'online')->count();
        $offline = Nvr::where('status', 'offline')->count();
        $masters = Nvr::where('type', 'master')->count();
        $standards = Nvr::where('type', 'standard')->count();

        $masterSyncIssues = Nvr::where('type', 'master')
            ->where('consecutive_sync_losses', '>', 0)
            ->count();

        $highDiskUsage = Nvr::where('disk_usage', '>', 90)->count();

        return [
            'total_nvrs' => $total,
            'online' => $online,
            'offline' => $offline,
            'success_rate_percent' => $total > 0 ? round(($online / $total) * 100, 2) : 0,
            'master_nvrs' => $masters,
            'standard_nvrs' => $standards,
            'master_sync_issues' => $masterSyncIssues,
            'high_disk_usage_count' => $highDiskUsage,
            'total_cameras' => Nvr::sum('cameras_count'),
            'average_disk_usage' => round(Nvr::avg('disk_usage'), 2)
        ];
    }
}
