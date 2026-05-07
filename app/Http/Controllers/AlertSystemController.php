<?php

namespace App\Http\Controllers;

use App\Models\AlertSystem;
use App\Models\Incident;
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
     * Store or update alert with escalation logic
     * POST /api/alerts
     * 
     * Request body:
     * {
     *   "key": "server_down_Active_Directory",
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
                // ✅ ESCALATION TRACKING: Update existing alert
                $oldSeverity = $alert->severity;
                $newSeverity = $validated['severity'];
                
                // Update alert with new data
                $alert->update([
                    'title' => $validated['title'],
                    'message' => $validated['message'],
                    'severity' => $newSeverity,
                    'status' => 'active',
                    'last_seen' => now()
                ]);

                // ✅ INCIDENT CREATION ONLY WHEN ESCALATING TO CRITICAL
                if ($newSeverity === 'critical' && $oldSeverity !== 'critical' && !$alert->incident_id) {
                    // Create incident ONLY ONCE when severity becomes critical
                    $incident = Incident::create([
                        'title' => $validated['title'],
                        'description' => $validated['message'],
                        'severity' => 'high',
                        'status' => 'open'
                    ]);

                    // Link alert to the incident
                    $alert->update(['incident_id' => $incident->id]);

                    add_log('incident_escalation', 'alert', "Alert escalated to critical: {$validated['key']}");

                    return response()->json([
                        'success' => true,
                        'message' => 'Alert escalated to CRITICAL - Incident created',
                        'data' => $alert,
                        'is_new' => false,
                        'escalated' => true,
                        'incident_created' => true,
                        'incident' => $incident
                    ], Response::HTTP_OK);
                }

                add_log('alert_updated', 'alert', "Alert updated: {$validated['key']}");

                return response()->json([
                    'success' => true,
                    'message' => 'Alert updated (severity: ' . $newSeverity . ')',
                    'data' => $alert,
                    'is_new' => false,
                    'escalated' => false,
                    'severity_changed' => $oldSeverity !== $newSeverity
                ], Response::HTTP_OK);
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

            // ✅ CREATE INCIDENT ONLY IF NEW ALERT IS CRITICAL
            if ($validated['severity'] === 'critical') {
                $incident = Incident::create([
                    'title' => $validated['title'],
                    'description' => $validated['message'],
                    'severity' => 'high',
                    'status' => 'open'
                ]);

                // Link alert to incident
                $alert->update(['incident_id' => $incident->id]);

                return response()->json([
                    'success' => true,
                    'message' => 'Critical alert created - Incident generated',
                    'data' => $alert,
                    'is_new' => true,
                    'incident_created' => true,
                    'incident' => $incident
                ], Response::HTTP_CREATED);
            }

            return response()->json([
                'success' => true,
                'message' => 'Alert created successfully (severity: ' . $validated['severity'] . ')',
                'data' => $alert,
                'is_new' => true,
                'incident_created' => false
            ], Response::HTTP_CREATED);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to store alert',
                'error' => $e->getMessage()
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Resolve alert
     * POST /api/alerts/resolve
     * 
     * Request body:
     * {
     *   "key": "server_down_Active_Directory"
     * }
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

            // ✅ Only resolve if currently active
            if ($alert->status === 'active') {
                $alert->update([
                    'status' => 'resolved',
                    'last_seen' => now()
                ]);

                // Reset severity to warning when resolving
                // (allows for future escalation if issue returns)
                $alert->update(['severity' => 'warning']);

                add_log('alert_resolved', 'alert', $validated['key']);

                return response()->json([
                    'success' => true,
                    'message' => 'Alert resolved successfully',
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
}
