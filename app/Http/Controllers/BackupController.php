<?php

namespace App\Http\Controllers;

use App\Models\Backup;
use App\Models\Alert;
use Illuminate\Http\Request;

class BackupController extends Controller
{
    /**
     * Get all backups
     * GET /api/backups
     */
    public function index()
    {
        try {
            $backups = Backup::latest('date')->get();
            
            return response()->json([
                'success' => true,
                'data' => $backups,
                'count' => $backups->count()
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve backups',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Create a new backup
     * POST /api/backups
     */
    public function store(Request $request)
    {
        // Validate input
        $validated = $request->validate([
            'server_name' => 'required|string|max:255',
            'status' => 'required|in:success,failed',
            'date' => 'nullable|date_format:Y-m-d H:i:s'
        ], [
            'server_name.required' => 'Server name is required',
            'server_name.string' => 'Server name must be a string',
            'server_name.max' => 'Server name cannot exceed 255 characters',
            'status.required' => 'Status is required',
            'status.in' => 'Status must be either "success" or "failed"',
            'date.date_format' => 'Date must be in format: Y-m-d H:i:s'
        ]);

        try {
            // Create the backup record
            $backup = Backup::create([
                'server_name' => $validated['server_name'],
                'status' => $validated['status'],
                'date' => $validated['date'] ?? now()
            ]);

            // Create alert if backup failed
            if ($validated['status'] === 'failed') {
                Alert::create([
                    'message' => "Backup failed for " . $request->server_name,
                    'type' => 'backup'
                ]);
            }

            return response()->json([
                'success' => true,
                'message' => 'Backup created successfully',
                'data' => $backup
            ], 201);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to create backup',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Mettre à jour un backup (legacy endpoint)
     * POST /api/update-backup
     */
    public function update(Request $request)
    {
        return $this->store($request);
    }
}
