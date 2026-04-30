<?php

namespace App\Http\Controllers;

use Illuminate\Support\Facades\Http;

class ZabbixController extends Controller
{
    private $zabbixUrl = "http://localhost:8090/api_jsonrpc.php";

    private function login()
    {
        $response = Http::post($this->zabbixUrl, [
            "jsonrpc" => "2.0",
            "method" => "user.login",
            "params" => [
                "user" => "Admin",
                "password" => "zabbix"
            ],
            "id" => 1
        ]);
        return $response->json()["result"];
    }

    public function getHosts()
    {
        $token = $this->login();

        $response = Http::post($this->zabbixUrl, [
            "jsonrpc" => "2.0",
            "method" => "host.get",
            "params" => [
                "output" => ["hostid", "host", "status"],
                "selectInterfaces" => ["ip"]
            ],
            "auth" => $token,
            "id" => 2
        ]);

        return response()->json($response->json()["result"]);
    }
}