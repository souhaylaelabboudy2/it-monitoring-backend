<?php

use App\Http\Controllers\ServerController;
use App\Http\Controllers\AlertController;
use App\Http\Controllers\IncidentController;
use App\Http\Controllers\NvrController;
use App\Http\Controllers\BackupController;
use App\Http\Controllers\AuthController;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\ZabbixController;

Route::post('/login', [AuthController::class, 'login']);

Route::middleware('auth:sanctum')->group(function () {
    Route::get('/servers', [ServerController::class, 'index']);
    Route::get('/nvr', [NvrController::class, 'index']);
    Route::get('/alerts', [AlertController::class, 'index']);
});

Route::post('/update-server', [ServerController::class, 'update']);
Route::get('/stats', [ServerController::class, 'stats']);

Route::get('/incidents', [IncidentController::class, 'index']);
Route::post('/incidents', [IncidentController::class, 'store']);
Route::put('/incidents/{id}', [IncidentController::class, 'update']);
Route::delete('/incidents/{id}', [IncidentController::class, 'destroy']);

Route::post('/update-nvr', [NvrController::class, 'update']);

Route::get('/backups', [BackupController::class, 'index']);
Route::post('/update-backup', [BackupController::class, 'update']);

Route::get('/zabbix/hosts', [ZabbixController::class, 'getHosts']);
