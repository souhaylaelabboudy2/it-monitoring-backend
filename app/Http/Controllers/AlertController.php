<?php

namespace App\Http\Controllers;

use App\Models\Alert;
use App\Models\Incident;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class AlertController extends Controller
{
    /**
     * Récupère les 10 dernières alertes triées par date décroissante
     * GET /api/alerts
     */
    public function index()
    {
        $alerts = Alert::latest('created_at')
            ->limit(10)
            ->get();
        
        return response()->json([
            'success' => true,
            'count' => count($alerts),
            'data' => $alerts
        ], Response::HTTP_OK);
    }

    /**
     * Create or update an alert with escalation and incident management
     * POST /api/alerts
     */
    public function store(Request $request)
    {
        $request->validate([
            'key' => 'required|string',
            'title' => 'required|string',
            'message' => 'required|string',
            'type' => 'required|string|in:server,backup,nvr',
            'severity' => 'required|string|in:info,warning,critical'
        ]);

        // ✅ CHECK FOR EXISTING ALERT BY KEY
        $existingAlert = Alert::findByKey($request->key);

        if ($existingAlert) {
            // ✅ UPDATE EXISTING ALERT (escalation scenario)
            $oldSeverity = $existingAlert->severity;
            $newSeverity = $request->severity;
            
            $existingAlert->update([
                'title' => $request->title,
                'message' => $request->message,
                'severity' => $newSeverity,
                'status' => 'open'
            ]);

            // ✅ INCIDENT CREATION ONLY WHEN ESCALATING TO CRITICAL
            if ($newSeverity === 'critical' && $oldSeverity !== 'critical' && !$existingAlert->incident_id) {
                // Create incident ONLY ONCE when severity becomes critical
                $incident = Incident::create([
                    'title' => $existingAlert->title,
                    'description' => $existingAlert->message,
                    'severity' => 'critical',
                    'status' => 'open'
                ]);

                // Link alert to the new incident
                $existingAlert->update(['incident_id' => $incident->id]);

                return response()->json([
                    'success' => true,
                    'action' => 'escalated',
                    'message' => 'Alert escalated to CRITICAL - Incident created',
                    'data' => [
                        'alert' => $existingAlert,
                        'incident' => $incident
                    ]
                ], Response::HTTP_OK);
            }

            return response()->json([
                'success' => true,
                'action' => 'updated',
                'message' => 'Alert updated',
                'data' => $existingAlert
            ], Response::HTTP_OK);
        }

        // ✅ CREATE NEW ALERT
        $alert = Alert::create([
            'key' => $request->key,
            'title' => $request->title,
            'message' => $request->message,
            'type' => $request->type,
            'severity' => $request->severity,
            'status' => 'open'
        ]);

        // ✅ CREATE INCIDENT ONLY IF NEW ALERT IS CRITICAL
        if ($request->severity === 'critical') {
            $incident = Incident::create([
                'title' => $request->title,
                'description' => $request->message,
                'severity' => 'critical',
                'status' => 'open'
            ]);

            $alert->update(['incident_id' => $incident->id]);

            return response()->json([
                'success' => true,
                'action' => 'created_critical',
                'message' => 'Critical alert created - Incident generated',
                'data' => [
                    'alert' => $alert,
                    'incident' => $incident
                ]
            ], Response::HTTP_CREATED);
        }

        return response()->json([
            'success' => true,
            'action' => 'created',
            'message' => 'Alert created',
            'data' => $alert
        ], Response::HTTP_CREATED);
    }

    /**
     * Resolve an alert by its key
     * POST /api/alerts/resolve
     */
    public function resolve(Request $request)
    {
        $request->validate([
            'key' => 'required|string'
        ]);

        $alert = Alert::findByKey($request->key);

        if (!$alert) {
            return response()->json([
                'success' => false,
                'message' => 'Alert not found'
            ], Response::HTTP_NOT_FOUND);
        }

        // ✅ RESOLVE ALERT
        $alert->resolve();

        return response()->json([
            'success' => true,
            'message' => 'Alert resolved',
            'data' => $alert
        ], Response::HTTP_OK);
    }

    /**
     * Get alert by key
     * GET /api/alerts/{key}
     */
    public function show(string $key)
    {
        $alert = Alert::findByKey($key);

        if (!$alert) {
            return response()->json([
                'success' => false,
                'message' => 'Alert not found'
            ], Response::HTTP_NOT_FOUND);
        }

        return response()->json([
            'success' => true,
            'data' => $alert
        ], Response::HTTP_OK);
    }

    /**
     * Get alerts by type
     * GET /api/alerts/type/{type}
     */
    public function getByType(string $type)
    {
        $alerts = Alert::where('type', $type)
            ->latest('created_at')
            ->limit(10)
            ->get();

        return response()->json([
            'success' => true,
            'type' => $type,
            'count' => count($alerts),
            'data' => $alerts
        ], Response::HTTP_OK);
    }

    /**
     * Get critical alerts only
     * GET /api/alerts/critical
     */
    public function getCritical()
    {
        $alerts = Alert::where('severity', 'critical')
            ->where('status', 'open')
            ->latest('created_at')
            ->get();

        return response()->json([
            'success' => true,
            'severity' => 'critical',
            'count' => count($alerts),
            'data' => $alerts
        ], Response::HTTP_OK);
    }
}