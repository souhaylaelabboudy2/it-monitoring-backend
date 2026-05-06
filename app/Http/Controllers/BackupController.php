<?php

namespace App\Http\Controllers;

use App\Models\Backup;
use App\Models\Alert;
use Illuminate\Http\Request;

class BackupController extends Controller
{
    public function index()
    {
        try {
            $backups = Backup::orderBy('created_at', 'desc')->get();
            
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

    public function store(Request $request)
    {
        $validated = $request->validate([
            'server_name' => 'required|string|max:255',
            'status' => 'required|in:success,failed',
        ]);

        try {
            $last = Backup::where('server_name', $validated['server_name'])
                          ->latest()
                          ->first();

            if ($last) {
                $statusChanged = $last->status !== $validated['status'];
                $minutesPassed = now()->diffInMinutes($last->created_at);
                
                if (!$statusChanged && $minutesPassed < 5) {
                    return response()->json([
                        'success' => true,
                        'message' => 'No change & within 5min window - backup not inserted',
                        'data' => $last
                    ], 200);
                }
            }

            $backup = Backup::create([
                'server_name' => $validated['server_name'],
                'status' => $validated['status']
            ]);

            if ($validated['status'] === 'failed') {
                Alert::create([
                    'message' => "Backup failed for " . $validated['server_name'],
                    'type' => 'backup'
                ]);
                add_log('backup_failed', 'backup', $validated['server_name']);
            } elseif ($validated['status'] === 'success') {
                add_log('backup_success', 'backup', $validated['server_name']);
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

    public function update(Request $request)
    {
        return $this->store($request);
    }
}