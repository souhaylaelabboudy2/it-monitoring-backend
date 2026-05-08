<?php

namespace App\Http\Controllers;

use App\Models\AlertSystem;
use App\Models\Incident;
use App\Services\IncidentService;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Validation\ValidationException;

class AlertSystemController extends Controller
{
    /**
     * Get latest alerts
     * GET /api/alerts
     */
    public function index()
    {
        try {
            $alerts = AlertSystem::orderBy('last_seen', 'desc')
                ->limit(10)
                ->get();

            return response()->json([
                'success' => true,
                'count' => $alerts->count(),
                'data' => $alerts
            ], Response::HTTP_OK);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve alerts',
                'error' => $e->getMessage()
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Store or update alert with automatic incident creation
     * POST /api/alerts
     * 
     * Request body:
     * {
     *   "key": "server_offline_Active_Directory",
     *   "title": "Server Active Directory is DOWN",
     *   "message": "Server not responding to ping",
     *   "type": "server",
     *   "severity": "critical"
     * }
     */
    public function store(Request $request)
    {
        try {
            $validated = $request->validate([
                'key' => 'required|string|max:255',
                'title' => 'required|string|max:255',
                'message' => 'required|string',
                'type' => 'required|in:server,backup,nvr',
                'severity' => 'required|in:critical,warning,info'
            ]);
        } catch (ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Validation error',
                'errors' => $e->errors()
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        try {
            // ✅ CHECK FOR EXISTING ALERT BY KEY
            $alert = AlertSystem::where('key', $validated['key'])->first();

            if ($alert) {
                // ✅ UPDATE EXISTING ALERT
                $oldSeverity = $alert->severity;
                $newSeverity = $validated['severity'];
                
                $alert->update([
                    'title' => $validated['title'],
                    'message' => $validated['message'],
                    'severity' => $newSeverity,
                    'status' => 'active',
                    'last_seen' => now()
                ]);

                $result = [
                    'success' => true,
                    'data' => $alert,
                    'is_new' => false,
                    'severity_changed' => $oldSeverity !== $newSeverity
                ];

                // ✅ AUTOMATIC INCIDENT CREATION FOR CRITICAL ALERTS (ONLY ONCE)
                // Only create incident if:
                // 1. Alert is now critical AND
                // 2. Alert didn't have an incident before
                if ($newSeverity === 'critical' && !$alert->incident_id) {
                    $incidentResult = $this->createIncidentForAlert($validated, $alert);
                    $result['incident'] = $incidentResult['incident'];
                    $result['incident_action'] = $incidentResult['action'];
                    $result['message'] = 'Alert escalated to CRITICAL - Incident created';
                } elseif ($newSeverity === 'critical' && $alert->incident_id) {
                    // Alert already has an incident, just update it
                    $result['message'] = 'Alert updated - Existing incident maintained';
                } else {
                    $result['message'] = 'Alert updated';
                }

                add_log('alert_updated', 'alert', "Alert: {$validated['key']}");
                return response()->json($result, Response::HTTP_OK);
            }

            // ✅ CREATE NEW ALERT
            $alert = AlertSystem::create([
                'key' => $validated['key'],
                'title' => $validated['title'],
                'message' => $validated['message'],
                'type' => $validated['type'],
                'severity' => $validated['severity'],
                'status' => 'active',
                'last_seen' => now()
            ]);

            add_log('alert_created', 'alert', $validated['title']);

            $result = [
                'success' => true,
                'data' => $alert,
                'is_new' => true,
                'message' => 'Alert created'
            ];

            // ✅ AUTOMATIC INCIDENT CREATION FOR NEW CRITICAL ALERTS
            if ($validated['severity'] === 'critical') {
                $incidentResult = $this->createIncidentForAlert($validated, $alert);
                $result['incident'] = $incidentResult['incident'];
                $result['incident_action'] = $incidentResult['action'];
                $result['message'] = 'Critical alert created - Incident generated';
            }

            return response()->json(
                $result, 
                Response::HTTP_CREATED
            );
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to store alert',
                'error' => $e->getMessage()
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Create or update incident based on alert type
     * Uses IncidentService for smart incident management
     */
    private function createIncidentForAlert(array $alert, AlertSystem $alertModel): array
    {
        $type = $alert['type'];
        $severity = $alert['severity'];
        
        // Extract failure count from message if available
        $failureCount = 1;
        if (preg_match('/×(\d+)/', $alert['message'], $matches)) {
            $failureCount = (int)$matches[1];
        }

        $incidentResult = match($type) {
            'server' => $this->createServerIncident($alert, $failureCount),
            'backup' => $this->createBackupIncident($alert, $failureCount),
            'nvr' => $this->createNvrIncident($alert, $failureCount),
            default => null
        };

        if ($incidentResult) {
            // Link alert to incident
            $alertModel->update(['incident_id' => $incidentResult['incident']->id]);
            return $incidentResult;
        }

        // Fallback: create generic incident
        $incidentResult = Incident::createOrUpdateByKey(
            key: $alert['key'],
            title: $alert['title'],
            description: $alert['message'],
            severity: $severity
        );

        $alertModel->update(['incident_id' => $incidentResult['incident']->id]);
        return $incidentResult;
    }

    /**
     * Create server-related incident
     */
    private function createServerIncident(array $alert, int $failureCount): array
    {
        // Extract server name from key or title
        $serverName = $this->extractResourceName($alert['key'], 'server');
        
        if (strpos($alert['message'], 'OFFLINE') !== false || strpos($alert['title'], 'OFFLINE') !== false) {
            return IncidentService::handleServerOffline($serverName);
        }

        // Generic server incident
        return Incident::createOrUpdateByKey(
            key: $alert['key'],
            title: $alert['title'],
            description: $alert['message'],
            severity: $alert['severity'] === 'critical' ? 'critical' : 'warning'
        );
    }

    /**
     * Create backup-related incident
     */
    private function createBackupIncident(array $alert, int $failureCount): array
    {
        $serverName = $this->extractResourceName($alert['key'], 'backup');
        
        return IncidentService::handleBackupFailure($serverName, $failureCount);
    }

    /**
     * Create NVR-related incident
     */
    private function createNvrIncident(array $alert, int $failureCount): array
    {
        $nvrName = $this->extractResourceName($alert['key'], 'nvr');
        
        if (strpos($alert['message'], 'OFFLINE') !== false) {
            return IncidentService::handleNvrOffline($nvrName);
        } elseif (strpos($alert['message'], 'SYNC') !== false) {
            return IncidentService::handleNvrSyncLoss($nvrName, $failureCount);
        }

        return Incident::createOrUpdateByKey(
            key: $alert['key'],
            title: $alert['title'],
            description: $alert['message'],
            severity: $alert['severity'] === 'critical' ? 'critical' : 'warning'
        );
    }

    /**
     * Extract resource name from alert key
     * Examples: server_offline_Active_Directory → Active Directory
     */
    private function extractResourceName(string $key, string $type): string
    {
        // Remove type prefix (server_offline_, backup_failed_, nvr_offline_, nvr_sync_lost_)
        $patterns = [
            'server' => '/^server_.*?_/',
            'backup' => '/^backup_.*?_/',
            'nvr' => '/^nvr_.*?_/'
        ];

        $pattern = $patterns[$type] ?? '/^[a-z]+_.*?_/';
        $name = preg_replace($pattern, '', $key);
        
        // Convert underscores back to spaces
        return str_replace('_', ' ', $name);
    }

    /**
     * Resolve alert
     * POST /api/alerts/resolve
     */
    public function resolve(Request $request)
    {
        try {
            $validated = $request->validate([
                'key' => 'required|string|max:255'
            ]);
        } catch (ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Validation error',
                'errors' => $e->errors()
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        try {
            $alert = AlertSystem::where('key', $validated['key'])->first();

            if (!$alert) {
                return response()->json([
                    'success' => false,
                    'message' => 'Alert not found'
                ], Response::HTTP_NOT_FOUND);
            }

            if ($alert->status === 'active') {
                $alert->update([
                    'status' => 'resolved',
                    'severity' => 'info',
                    'last_seen' => now()
                ]);

                // Also resolve linked incident if it exists
                if ($alert->incident_id) {
                    $incident = Incident::find($alert->incident_id);
                    if ($incident) {
                        $incident->resolve();
                    }
                }

                add_log('alert_resolved', 'alert', $validated['key']);

                return response()->json([
                    'success' => true,
                    'message' => 'Alert and linked incident resolved',
                    'data' => $alert
                ], Response::HTTP_OK);
            }

            return response()->json([
                'success' => true,
                'message' => 'Alert already resolved',
                'data' => $alert
            ], Response::HTTP_OK);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to resolve alert',
                'error' => $e->getMessage()
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Get critical alerts only
     * GET /api/alerts/critical
     */
    public function getCritical()
    {
        try {
            $alerts = AlertSystem::where('severity', 'critical')
                ->where('status', 'active')
                ->orderBy('last_seen', 'desc')
                ->get();

            return response()->json([
                'success' => true,
                'count' => $alerts->count(),
                'data' => $alerts
            ], Response::HTTP_OK);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve critical alerts',
                'error' => $e->getMessage()
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Get alerts by type
     * GET /api/alerts/type/{type}
     */
    public function getByType($type)
    {
        try {
            $alerts = AlertSystem::where('type', $type)
                ->orderBy('last_seen', 'desc')
                ->limit(10)
                ->get();

            return response()->json([
                'success' => true,
                'type' => $type,
                'count' => $alerts->count(),
                'data' => $alerts
            ], Response::HTTP_OK);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve alerts by type',
                'error' => $e->getMessage()
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Get all open incidents
     * GET /api/incidents/open
     */
    public function getOpenIncidents()
    {
        try {
            $incidents = IncidentService::getOpenIncidents();

            return response()->json([
                'success' => true,
                'count' => count($incidents),
                'data' => $incidents
            ], Response::HTTP_OK);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve open incidents',
                'error' => $e->getMessage()
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Get critical open incidents
     * GET /api/incidents/critical
     */
    public function getCriticalIncidents()
    {
        try {
            $incidents = IncidentService::getCriticalOpen();

            return response()->json([
                'success' => true,
                'count' => count($incidents),
                'data' => $incidents
            ], Response::HTTP_OK);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve critical incidents',
                'error' => $e->getMessage()
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }
}
