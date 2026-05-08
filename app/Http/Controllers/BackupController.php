<?php

namespace App\Http\Controllers;

use App\Models\Backup;
use App\Models\Alert;
use App\Services\BackupAlertService;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class BackupController extends Controller
{
    /**
     * Get all backups
     * GET /api/backups
     */
    public function index()
    {
        try {
            $backups = Backup::orderBy('created_at', 'desc')->limit(50)->get();
            
            return response()->json([
                'success' => true,
                'data' => $backups,
                'count' => $backups->count()
            ], Response::HTTP_OK);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve backups',
                'error' => $e->getMessage()
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Create or update backup with automatic alert handling
     * POST /api/backups
     * 
     * Request body:
     * {
     *   "server_name": "SQL Server",
     *   "status": "success|failed",
     *   "duration_minutes": 45,
     *   "size_gb": 150.5,
     *   "error_message": "Connection timeout"
     * }
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'server_name' => 'required|string|max:255',
            'status' => 'required|in:success,failed',
            'duration_minutes' => 'sometimes|integer|min:0',
            'size_gb' => 'sometimes|numeric|min:0',
            'error_message' => 'sometimes|string|max:500'
        ]);

        try {
            // Check for duplicate within 5 minutes (spam prevention)
            $last = Backup::lastForServer($validated['server_name']);
            
            if ($last) {
                $statusChanged = $last->status !== $validated['status'];
                $minutesPassed = now()->diffInMinutes($last->created_at);
                
                // Skip if same status and within 5 minutes
                if (!$statusChanged && $minutesPassed < 5) {
                    return response()->json([
                        'success' => true,
                        'message' => 'Duplicate backup within 5 minutes - skipped',
                        'data' => $last,
                        'action' => 'skipped'
                    ], Response::HTTP_OK);
                }
            }

            // ✅ PROCESS BACKUP WITH AUTOMATIC ALERTS
            $result = BackupAlertService::processBackup(
                serverName: $validated['server_name'],
                status: $validated['status'],
                durationMinutes: $validated['duration_minutes'] ?? null,
                sizeGb: $validated['size_gb'] ?? null,
                errorMessage: $validated['error_message'] ?? null
            );

            $backup = $result['backup'];
            
            // Log the action
            $logType = $validated['status'] === 'failed' 
                ? 'backup_failed' 
                : 'backup_success';
            add_log($logType, 'backup', $validated['server_name']);

            // Build response
            $response = [
                'success' => true,
                'data' => $backup,
                'action' => $result['action'],
                'consecutive_failures' => $backup->consecutive_failures
            ];

            // Add alert info if created
            if ($result['alert_created'] || isset($result['alert'])) {
                $response['alert'] = [
                    'created' => $result['alert_created'],
                    'severity' => $result['alert']->severity ?? null,
                    'title' => $result['alert']->title ?? null
                ];
            }

            // Add incident info if created
            if (isset($result['incident_created']) && $result['incident_created']) {
                $response['incident'] = [
                    'created' => true,
                    'title' => $result['incident']['title'] ?? null
                ];
            }

            // Add resolution info if applicable
            if (isset($result['alert_resolved']) && $result['alert_resolved']) {
                $response['message'] = 'Backup succeeded - Alert and incident resolved';
            } else {
                $response['message'] = $validated['status'] === 'failed'
                    ? "Backup failed (Failure #{$backup->consecutive_failures})"
                    : 'Backup succeeded';
            }

            $statusCode = $validated['status'] === 'failed' 
                ? Response::HTTP_BAD_REQUEST 
                : Response::HTTP_CREATED;

            return response()->json($response, $statusCode);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to process backup',
                'error' => $e->getMessage()
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Update backup (alias to store)
     */
    public function update(Request $request)
    {
        return $this->store($request);
    }

    /**
     * Get backup status for a server
     * GET /api/backups/server/{serverName}
     */
    public function getByServer($serverName)
    {
        try {
            $summary = BackupAlertService::getServerSummary($serverName);

            return response()->json([
                'success' => true,
                'data' => $summary
            ], Response::HTTP_OK);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve backup status',
                'error' => $e->getMessage()
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Get all failed backups
     * GET /api/backups/failed
     */
    public function getFailed()
    {
        try {
            $failedServers = BackupAlertService::getFailedServers();

            return response()->json([
                'success' => true,
                'count' => count($failedServers),
                'data' => $failedServers
            ], Response::HTTP_OK);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve failed backups',
                'error' => $e->getMessage()
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Get critical backup failures
     * GET /api/backups/critical
     */
    public function getCritical()
    {
        try {
            $criticalBackups = BackupAlertService::getCriticalFailures();

            return response()->json([
                'success' => true,
                'count' => count($criticalBackups),
                'data' => $criticalBackups
            ], Response::HTTP_OK);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve critical backups',
                'error' => $e->getMessage()
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Get backup statistics
     * GET /api/backups/stats
     */
    public function getStats()
    {
        try {
            $stats = BackupAlertService::getStatistics();

            return response()->json([
                'success' => true,
                'data' => $stats
            ], Response::HTTP_OK);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve backup statistics',
                'error' => $e->getMessage()
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }
}