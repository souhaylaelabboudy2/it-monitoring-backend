<?php

namespace App\Http\Controllers;

use App\Models\Backup;
use App\Models\Alert;
use Illuminate\Http\Request;

class BackupController extends Controller
{
    public function index()
    {
        return Backup::latest('created_at')->limit(10)->get();
    }

    /**
     * Mettre à jour un backup
     * POST /api/update-backup
     */
    public function update(Request $request)
    {
        $backup = Backup::create([
            'name' => $request->name,
            'status' => $request->status,
            'size' => $request->size ?? 0,
            'created_at' => now()
        ]);

        // Créer une alerte si la sauvegarde a échoué
        if ($request->status === "failed") {
            Alert::create([
                'message' => "Backup failed: " . $request->name,
                'type' => "backup"
            ]);
        }

        // Créer une alerte si la sauvegarde a réussi (optionnel)
        if ($request->status === "success") {
            Alert::create([
                'message' => "Backup completed: " . $request->name,
                'type' => "backup"
            ]);
        }

        return response()->json([
            'success' => true,
            'message' => 'Backup logged successfully',
            'backup' => $backup
        ], 201);
    }
}
