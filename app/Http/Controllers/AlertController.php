<?php

namespace App\Http\Controllers;

use App\Models\Alert;
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
}