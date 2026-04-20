<?php

use App\Http\Controllers\ServerController;
use App\Http\Controllers\AlertController;
use App\Http\Controllers\IncidentController;
use App\Http\Controllers\NvrController;
use Illuminate\Support\Facades\Route;

Route::get('/servers', [ServerController::class, 'index']);
Route::post('/update-server', [ServerController::class, 'update']);

Route::get('/alerts', [AlertController::class, 'index']);

Route::get('/incidents', [IncidentController::class, 'index']);
Route::post('/incidents', [IncidentController::class, 'store']);

Route::get('/nvr', [NvrController::class, 'index']);