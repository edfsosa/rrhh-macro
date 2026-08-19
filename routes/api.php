<?php

use App\Http\Controllers\Api\TerminalEmployeeSyncController;
use App\Http\Controllers\Api\TerminalEventSyncController;
use App\Http\Controllers\Api\TerminalHeartbeatController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes — Sincronización de Terminales (marcación offline vía PWA)
|--------------------------------------------------------------------------
|
| Autenticadas con Laravel Sanctum (bearer token emitido al provisionar el
| terminal, ver TerminalSetupController). Ability requerida: 'terminal:sync'.
| Solo consumidas por el kiosko/terminal — el flujo de celular personal
| (mark.js) sigue usando las rutas de sesión en routes/web.php sin cambios.
|
*/

Route::prefix('v1/terminal')->name('api.terminal.')->middleware(['auth:sanctum'])->group(function () {
    Route::get('/employees/sync', [TerminalEmployeeSyncController::class, 'index'])
        ->middleware('ability:terminal:sync')
        ->name('employees.sync');

    Route::get('/employees/{employee}/status', [TerminalEmployeeSyncController::class, 'status'])
        ->middleware('ability:terminal:sync')
        ->name('employees.status');

    Route::post('/events/sync', [TerminalEventSyncController::class, 'store'])
        ->middleware('ability:terminal:sync')
        ->name('events.sync');

    Route::post('/heartbeat', [TerminalHeartbeatController::class, 'store'])
        ->middleware('ability:terminal:sync')
        ->name('heartbeat');
});
