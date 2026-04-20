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
            $server->update([
                'status' => $request->status,
                'cpu_usage' => $request->cpu,
                'ram_usage' => $request->ram,
                'disk_usage' => $request->disk
            ]);

            if ($request->status == "offline") {
                Alert::create([
                    'message' => "Server down: " . $request->name,
                    'type' => "server"
                ]);
            }
        }

        return response()->json(['message' => 'updated']);
    }
}