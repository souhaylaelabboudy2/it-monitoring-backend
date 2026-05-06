<?php

namespace App\Http\Controllers;

use App\Models\Log;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class LogController extends Controller
{
    /**
     * Get latest 20 logs
     * GET /api/logs
     */
    public function index()
    {
        try {
            $logs = Log::orderBy('created_at', 'desc')
                ->limit(20)
                ->get();

            return response()->json([
                'success' => true,
                'count' => $logs->count(),
                'data' => $logs
            ], Response::HTTP_OK);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve logs',
                'error' => $e->getMessage()
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Get logs filtered by type
     * GET /api/logs/search?type=server&limit=50
     */
    public function show(Request $request)
    {
        try {
            $type = $request->query('type');
            $limit = $request->query('limit', 20);

            $query = Log::orderBy('created_at', 'desc');

            if ($type) {
                $query->where('type', $type);
            }

            $logs = $query->limit($limit)->get();

            return response()->json([
                'success' => true,
                'count' => $logs->count(),
                'data' => $logs,
                'filter' => ['type' => $type, 'limit' => $limit]
            ], Response::HTTP_OK);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve logs',
                'error' => $e->getMessage()
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }
}
