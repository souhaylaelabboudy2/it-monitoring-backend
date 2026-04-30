<?php

namespace App\Http\Controllers;

use App\Models\Server;
use App\Models\Alert;
use App\Events\ServerUpdated;
use Illuminate\Http\Request;

class ServerController extends Controller
{
    public function index()
    {
        return Server::all();
    }

    public function update(Request $request)
{
    $server = Server::updateOrCreate(
        ['name' => $request->name],
        [
            'status' => $request->status,
            'ip' => $request->ip,
            'cpu_usage' => $request->cpu,
            'ram_usage' => $request->ram,
            'disk_usage' => $request->disk,
            'last_check' => now()
        ]
    );

    try {
        event(new ServerUpdated($server));
    } catch (\Exception $e) {
        \Log::error('Broadcasting error: ' . $e->getMessage());
    }

    if ($request->status === "offline") {
        Alert::create([
            'message' => "Server down: " . $request->name,
            'type' => "server"
        ]);
    }

    return response()->json([
        'success' => true,
        'message' => 'Server updated successfully',
        'server' => $server
    ], 200);
}
    public function stats()
    {
        $total = Server::count();
        $online = Server::where('status', 'online')->count();
        $offline = Server::where('status', 'offline')->count();

        return response()->json([
            'total' => $total,
            'online' => $online,
            'offline' => $offline
        ]);
    }
}