<?php

namespace App\Http\Controllers;

use App\Models\Server;
use App\Models\Alert;
use Illuminate\Http\Request;

class ServerController extends Controller
{
    public function index()
    {
        return Server::all();
    }

    public function update(Request $request)
    {
        $server = Server::where('name', $request->name)->first();

        if ($server) {
            $oldStatus = $server->status;
            
            $server->update([
                'status' => $request->status,
                'cpu_usage' => $request->cpu,
                'ram_usage' => $request->ram,
                'disk_usage' => $request->disk,
                'last_check' => now()
            ]);

            if ($oldStatus !== "offline" && $request->status === "offline") {
                Alert::create([
                    'message' => "Server down: " . $request->name,
                    'type' => "server"
                ]);
            }
            
            if ($oldStatus === "offline" && $request->status === "online") {
                Alert::create([
                    'message' => "Server back online: " . $request->name,
                    'type' => "server"
                ]);
            }
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