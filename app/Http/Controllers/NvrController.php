<?php

namespace App\Http\Controllers;

use App\Models\Nvr;
use App\Models\Alert;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Validation\ValidationException;

class NvrController extends Controller
{
    /**
     * Récupère tous les NVR
     * GET /api/nvr
     */
    public function index()
    {
        $nvrs = Nvr::orderBy('name')->get();
        
        return response()->json([
            'success' => true,
            'count' => count($nvrs),
            'data' => $nvrs
        ], Response::HTTP_OK);
    }

    /**
     * Mettre à jour un NVR avec validation
     * POST /api/update-nvr
     * 
     * Request body:
     * {
     *   "name": "NVR Master",
     *   "type": "master",
     *   "status": "online",
     *   "sync_status": "synced",
     *   "cameras_count": 12,
     *   "disk_usage": 65.5
     * }
     */
    public function update(Request $request)
    {
        // Validation des données d'entrée
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

        // Rechercher oder créer le NVR
        $nvr = Nvr::where('name', $validated['name'])->first();
        
        if (!$nvr) {
            $nvr = Nvr::create([
                'name' => $validated['name'],
                'type' => $validated['type'] ?? 'standard',
                'sync_status' => $validated['sync_status'] ?? 'synced',
                'status' => $validated['status'],
                'cameras_count' => $validated['cameras_count'] ?? 0,
                'disk_usage' => $validated['disk_usage'] ?? 0,
                'last_check' => now()
            ]);
            
            // Alerte pour nouveau NVR offline
            if ($validated['status'] === 'offline') {
                Alert::create([
                    'message' => "New NVR detected offline: " . $validated['name'],
                    'type' => "nvr"
                ]);
            }
            
            return response()->json([
                'success' => true,
                'message' => 'NVR created successfully',
                'data' => $nvr
            ], Response::HTTP_CREATED);
        }

        // Récupérer les anciennes valeurs pour la comparaison
        $oldStatus = $nvr->status;
        $oldCamerasCount = $nvr->cameras_count;
        $oldDiskUsage = $nvr->disk_usage;
        $oldSyncStatus = $nvr->sync_status;

        // Mettre à jour le NVR
        $nvr->update([
            'type' => $validated['type'] ?? $nvr->type,
            'sync_status' => $validated['sync_status'] ?? $oldSyncStatus,
            'status' => $validated['status'],
            'cameras_count' => $validated['cameras_count'] ?? $oldCamerasCount,
            'disk_usage' => $validated['disk_usage'] ?? $oldDiskUsage,
            'last_check' => now()
        ]);

        // ===== ALERTS =====
        if ($validated['status'] === 'offline') {
            Alert::create([
                'message' => "NVR offline: " . $validated['name'],
                'type' => 'nvr'
            ]);
        }

        if (($validated['disk_usage'] ?? 0) > 90) {
            Alert::create([
                'message' => "NVR disk full: " . $validated['name'],
                'type' => 'nvr'
            ]);
        }

        if ($validated['type'] === 'master' && $validated['sync_status'] === 'lost') {
            Alert::create([
                'message' => "NVR MASTER sync lost: " . $validated['name'],
                'type' => 'nvr'
            ]);
        }

        return response()->json([
            'success' => true,
            'message' => 'NVR updated successfully',
            'data' => $nvr
        ], Response::HTTP_OK);
    }
}
