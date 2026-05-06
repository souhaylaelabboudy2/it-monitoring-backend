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
     * Store or update alert
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
            // Check if alert already exists
            $alert = AlertSystem::where('key', $validated['key'])->first();

            if ($alert) {
                // Alert exists - just update last_seen and mark as active
                $alert->update([
                    'status' => 'active',
                    'last_seen' => now()
                ]);

                return response()->json([
                    'success' => true,
                    'message' => 'Alert updated',
                    'data' => $alert,
                    'is_new' => false
                ], Response::HTTP_OK);
            }

            // New alert - create it
            $alert = AlertSystem::create([
                'key' => $validated['key'],
                'title' => $validated['title'],
                'message' => $validated['message'],
                'type' => $validated['type'],
                'severity' => $validated['severity'],
                'status' => 'active',
                'last_seen' => now()
            ]);

            // If critical, create incident (only once, for new alerts)
            if ($validated['severity'] === 'critical') {
                Incident::create([
                    'title' => $validated['title'],
                    'description' => $validated['message'],
                    'severity' => 'high',
                    'status' => 'open'
                ]);
            }

            return response()->json([
                'success' => true,
                'message' => 'Alert created successfully',
                'data' => $alert,
                'is_new' => true,
                'incident_created' => $validated['severity'] === 'critical'
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

            // Only resolve if currently active
            if ($alert->status === 'active') {
                $alert->update([
                    'status' => 'resolved',
                    'last_seen' => now()
                ]);

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
}
