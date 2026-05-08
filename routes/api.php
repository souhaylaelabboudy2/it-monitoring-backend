<?php

use App\Http\Controllers\ServerController;
use App\Http\Controllers\AlertController;
use App\Http\Controllers\AlertSystemController;
use App\Http\Controllers\IncidentController;
use App\Http\Controllers\NvrController;
use App\Http\Controllers\BackupController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\LogController;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\ZabbixController;

Route::post('/login', [AuthController::class, 'login']);

Route::middleware('auth:sanctum')->group(function () {
    Route::get('/servers', [ServerController::class, 'index']);
    Route::get('/nvr', [NvrController::class, 'index']);
    Route::get('/alerts', [AlertController::class, 'index']);
    Route::get('/logs', [LogController::class, 'index']);
    Route::get('/logs/search', [LogController::class, 'show']);
});

Route::post('/update-server', [ServerController::class, 'update']);
Route::get('/stats', [ServerController::class, 'stats']);

Route::get('/incidents', [IncidentController::class, 'index']);
Route::post('/incidents', [IncidentController::class, 'store']);
Route::put('/incidents/{id}', [IncidentController::class, 'update']);
Route::delete('/incidents/{id}', [IncidentController::class, 'destroy']);
Route::post('/system/incident', [IncidentController::class, 'storeSystem']);

Route::post('/update-nvr', [NvrController::class, 'update']);
Route::get('/nvrs', [NvrController::class, 'index']);
Route::post('/nvrs', [NvrController::class, 'store']);
Route::get('/nvrs/{name}', [NvrController::class, 'getStatus']);
Route::get('/nvrs/failed', [NvrController::class, 'getFailed']);
Route::get('/nvrs/critical', [NvrController::class, 'getCritical']);
Route::get('/nvrs/stats', [NvrController::class, 'getStats']);

Route::get('/backups', [BackupController::class, 'index']);
Route::post('/backups', [BackupController::class, 'store']);
Route::post('/update-backup', [BackupController::class, 'update']);
Route::get('/backups/server/{serverName}', [BackupController::class, 'getByServer']);
Route::get('/backups/failed', [BackupController::class, 'getFailed']);
Route::get('/backups/critical', [BackupController::class, 'getCritical']);
Route::get('/backups/stats', [BackupController::class, 'getStats']);

Route::get('/zabbix/hosts', [ZabbixController::class, 'getHosts']);

// Alert System (no auth required for Python monitoring script)
Route::get('/alerts-system', [AlertSystemController::class, 'index']);
Route::post('/alerts', [AlertSystemController::class, 'store']);
Route::post('/alerts/resolve', [AlertSystemController::class, 'resolve']);
Route::get('/alerts/critical', [AlertSystemController::class, 'getCritical']);
Route::get('/alerts/type/{type}', [AlertSystemController::class, 'getByType']);

// Incident endpoints
Route::get('/incidents/open', [AlertSystemController::class, 'getOpenIncidents']);
Route::get('/incidents/critical', [AlertSystemController::class, 'getCriticalIncidents']);

