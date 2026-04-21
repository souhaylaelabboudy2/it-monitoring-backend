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
     *   "status": "online",
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
                'status' => 'required|in:online,offline',
                'cameras_count' => 'sometimes|integer|min:0|max:256',
                'disk_usage' => 'sometimes|numeric|min:0|max:100'
            ]);
        } catch (ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erreur de validation',
                'errors' => $e->errors()
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        // Rechercher ou créer le NVR
        $nvr = Nvr::where('name', $validated['name'])->first();
        
        if (!$nvr) {
            $nvr = Nvr::create([
                'name' => $validated['name'],
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
                'nvr' => $nvr
            ], Response::HTTP_CREATED);
        }

        // Récupérer les anciennes valeurs pour la comparaison
        $oldStatus = $nvr->status;
        $oldCamerasCount = $nvr->cameras_count;

        // Mettre à jour le NVR
        $nvr->update([
            'status' => $validated['status'],
            'cameras_count' => $validated['cameras_count'] ?? $oldCamerasCount,
            'disk_usage' => $validated['disk_usage'] ?? $nvr->disk_usage,
            'last_check' => now()
        ]);

        // Gestion des alertes de statut
        if ($oldStatus !== "offline" && $validated['status'] === "offline") {
            Alert::create([
                'message' => "NVR down: " . $validated['name'],
                'type' => "nvr"
            ]);
        } elseif ($oldStatus === "offline" && $validated['status'] === "online") {
            Alert::create([
                'message' => "NVR back online: " . $validated['name'],
                'type' => "nvr"
            ]);
        }

        // Gestion des alertes de caméras
        if ($oldCamerasCount > 0 && $validated['cameras_count'] < $oldCamerasCount) {
            $cameraDifference = $oldCamerasCount - $validated['cameras_count'];
            Alert::create([
                'message' => "NVR camera drop: " . $validated['name'] . " (" . $cameraDifference . " cameras lost)",
                'type' => "nvr"
            ]);
        }

        return response()->json([
            'success' => true,
            'message' => 'NVR updated successfully',
            'nvr' => $nvr
        ], Response::HTTP_OK);
    }
}
