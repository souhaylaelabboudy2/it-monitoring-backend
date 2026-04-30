<?php

namespace App\Http\Controllers;

use App\Models\Incident;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Validation\ValidationException;

class IncidentController extends Controller
{
    public function index()
    {
        $incidents = Incident::orderBy('created_at', 'desc')->get();
        
        return response()->json([
            'success' => true,
            'count' => count($incidents),
            'data' => $incidents
        ], Response::HTTP_OK);
    }

    public function store(Request $request)
    {
        try {
            $validated = $request->validate([
                'title' => 'required|string|max:255',
                'description' => 'required|string|max:1000',
                'severity' => 'sometimes|in:low,medium,high'
            ]);
        } catch (ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Validation error',
                'errors' => $e->errors()
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $incident = Incident::create([
            'title' => $validated['title'],
            'description' => $validated['description'],
            'severity' => $validated['severity'] ?? 'medium',
            'status' => 'open'
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Incident created successfully',
            'data' => $incident
        ], Response::HTTP_CREATED);
    }

    public function update(Request $request, $id)
    {
        $incident = Incident::find($id);
        
        if (!$incident) {
            return response()->json([
                'success' => false,
                'message' => 'Incident not found'
            ], Response::HTTP_NOT_FOUND);
        }

        try {
            $validated = $request->validate([
                'status' => 'sometimes|in:open,resolved',
                'description' => 'sometimes|string|max:1000',
                'severity' => 'sometimes|in:low,medium,high'
            ]);
        } catch (ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Validation error',
                'errors' => $e->errors()
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $incident->update($validated);

        return response()->json([
            'success' => true,
            'message' => 'Incident updated successfully',
            'data' => $incident
        ], Response::HTTP_OK);
    }

    public function destroy($id)
    {
        $incident = Incident::find($id);
        
        if (!$incident) {
            return response()->json([
                'success' => false,
                'message' => 'Incident not found'
            ], Response::HTTP_NOT_FOUND);
        }

        $incident->delete();

        return response()->json([
            'success' => true,
            'message' => 'Incident deleted successfully'
        ], Response::HTTP_OK);
    }
}