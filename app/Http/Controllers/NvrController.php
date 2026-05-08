<?php

namespace App\Http\Controllers;

use App\Models\Nvr;
use App\Services\NvrAlertService;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Validation\ValidationException;

class NvrController extends Controller
{
    /**
     * Get all NVRs
     * GET /api/nvrs
     */
    public function index()
    {
        try {
            $nvrs = Nvr::orderBy('name')->get();
            
            return response()->json([
                'success' => true,
                'count' => count($nvrs),
                'data' => $nvrs
            ], Response::HTTP_OK);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve NVRs',
                'error' => $e->getMessage()
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Update NVR with automatic alert handling
     * POST /api/update-nvr (legacy)
     * POST /api/nvrs (new standard)
     * 
     * Request body:
     * {
     *   "name": "NVR Main",
     *   "type": "standard|master",
     *   "status": "online|offline",
     *   "sync_status": "synced|lost",
     *   "cameras_count": 15,
     *   "disk_usage": 75.5
     * }
     */
    public function update(Request $request)
    {
        try {
            $validated = $request->validate([
                'name' => 'required|string|max:255',
                'type' => 'sometimes|in:standard,master',
                'status' => 'required|in:online,offline',
                'sync_status' => 'sometimes|in:synced,lost',
                'cameras_count' => 'sometimes|integer|min:0|max:256',
                'disk_usage' => 'sometimes|numeric|min:0|max:100'
            ], [
                'name.required' => 'NVR name is required',
                'type.in' => 'Type must be either "standard" or "master"',
                'status.required' => 'Status is required',
                'status.in' => 'Status must be either "online" or "offline"',
                'sync_status.in' => 'Sync status must be either "synced" or "lost"',
                'disk_usage.numeric' => 'Disk usage must be a number'
            ]);
        } catch (ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Validation error',
                'errors' => $e->errors()
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        try {
            // ✅ PROCESS NVR WITH AUTOMATIC ALERTS
            $result = NvrAlertService::processNvr(
                name: $validated['name'],
                type: $validated['type'] ?? 'standard',
                status: $validated['status'],
                syncStatus: $validated['sync_status'] ?? null,
                camerasCount: $validated['cameras_count'] ?? null,
                diskUsage: $validated['disk_usage'] ?? null
            );

            $nvr = $result['nvr'];
            $action = $result['action'];

            // Build response
            $response = [
                'success' => true,
                'data' => $nvr,
                'action' => $action
            ];

            // Add alert info
            if (isset($result['offline_alert'])) {
                $response['offline_alert'] = $result['offline_alert'];
            }
            if (isset($result['sync_alert'])) {
                $response['sync_alert'] = $result['sync_alert'];
            }
            if (isset($result['disk_alert'])) {
                $response['disk_alert'] = $result['disk_alert'];
            }

            // Add incident info
            if ($result['incident_created']) {
                $response['incident_created'] = true;
            }
            if ($result['alert_resolved']) {
                $response['alert_resolved'] = true;
            }

            // Build message
            $messages = [];
            if ($validated['status'] === 'offline') {
                $messages[] = '🔴 NVR OFFLINE - Critical alert created';
            } elseif ($action === 'resolved_offline') {
                $messages[] = '✅ NVR is back ONLINE - Alert resolved';
            }
            if (isset($result['sync_alert'])) {
                $severity = $result['sync_alert']['severity'] === 'critical' 
                    ? '🔴 CRITICAL' 
                    : '⚠️ WARNING';
                $count = $result['sync_alert']['count'] ?? 0;
                $messages[] = "{$severity} Sync Loss #{$count}";
            }
            if (isset($result['disk_alert'])) {
                $messages[] = '⚠️ Disk usage warning';
            }

            $response['message'] = implode(' | ', $messages) ?: 'NVR updated successfully';

            $statusCode = match ($action) {
                'offline' => Response::HTTP_BAD_REQUEST,
                'created' => Response::HTTP_CREATED,
                default => Response::HTTP_OK
            };

            return response()->json($response, $statusCode);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to process NVR',
                'error' => $e->getMessage()
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Alias for update (POST /api/nvrs)
     */
    public function store(Request $request)
    {
        return $this->update($request);
    }

    /**
     * Get NVR status for dashboard
     * GET /api/nvrs/{name}
     */
    public function getStatus($name)
    {
        try {
            $status = NvrAlertService::getNvrStatus($name);

            if (isset($status['error'])) {
                return response()->json([
                    'success' => false,
                    'message' => $status['error']
                ], Response::HTTP_NOT_FOUND);
            }

            return response()->json([
                'success' => true,
                'data' => $status
            ], Response::HTTP_OK);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve NVR status',
                'error' => $e->getMessage()
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Get all offline/failed NVRs
     * GET /api/nvrs/failed
     */
    public function getFailed()
    {
        try {
            $failedNvrs = NvrAlertService::getFailedNvrs();

            return response()->json([
                'success' => true,
                'count' => count($failedNvrs),
                'data' => $failedNvrs
            ], Response::HTTP_OK);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve failed NVRs',
                'error' => $e->getMessage()
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Get critical NVRs
     * GET /api/nvrs/critical
     */
    public function getCritical()
    {
        try {
            $criticalNvrs = NvrAlertService::getCriticalNvrs();

            return response()->json([
                'success' => true,
                'count' => count($criticalNvrs),
                'data' => $criticalNvrs
            ], Response::HTTP_OK);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve critical NVRs',
                'error' => $e->getMessage()
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Get NVR statistics
     * GET /api/nvrs/stats
     */
    public function getStats()
    {
        try {
            $stats = NvrAlertService::getStatistics();

            return response()->json([
                'success' => true,
                'data' => $stats
            ], Response::HTTP_OK);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve NVR statistics',
                'error' => $e->getMessage()
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }
}