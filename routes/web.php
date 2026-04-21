<?php

use Illuminate\Support\Facades\Route;
use App\Models\Server;

Route::get('/', function () {
    return view('welcome');
});

Route::get('/servers', function () {
    $servers = Server::all();
    return view('servers', ['servers' => $servers]);
});
