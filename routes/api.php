<?php

use App\Http\Controllers\Api\MobileEventSyncController;
use App\Http\Controllers\Api\MobileHeartbeatController;
use App\Http\Controllers\Api\MobileStatusController;
use App\Http\Controllers\Api\MobileUnlinkController;
use App\Http\Controllers\Api\TerminalEmployeeSyncController;
use App\Http\Controllers\Api\TerminalEventSyncController;
use App\Http\Controllers\Api\TerminalHeartbeatController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes — Sincronización offline vía PWA (kiosko y celular personal)
|--------------------------------------------------------------------------
|
| Autenticadas con Laravel Sanctum. Sanctum resuelve el `tokenable` de forma
| polimórfica (por `personal_access_tokens.tokenable_type`) — un mismo guard
| `auth:sanctum` sirve tokens de `Terminal` (kiosko) y de `Employee`
| (celular personal) sin necesidad de guards/providers separados en
| config/auth.php. Cada grupo abajo exige su propia ability.
|
*/

// Kiosko de sucursal — bearer token emitido al provisionar el terminal (ver
// TerminalSetupController). Ability requerida: 'terminal:sync'.
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

// Celular personal del empleado — bearer token emitido al vincular el
// dispositivo (ver MobileLinkController). Ability requerida: 'mobile:sync'.
// A diferencia del kiosko (N empleados por terminal), acá el token identifica
// a un único empleado (el propio dueño del token vía $request->user()), por
// eso no existe un endpoint de "sync de empleados" — el heartbeat ya
// devuelve el descriptor facial actualizado del propio empleado.
Route::prefix('v1/mobile')->name('api.mobile.')->middleware(['auth:sanctum'])->group(function () {
    Route::post('/heartbeat', [MobileHeartbeatController::class, 'store'])
        ->middleware('ability:mobile:sync')
        ->name('heartbeat');

    Route::get('/status', [MobileStatusController::class, 'show'])
        ->middleware('ability:mobile:sync')
        ->name('status');

    Route::post('/events/sync', [MobileEventSyncController::class, 'store'])
        ->middleware('ability:mobile:sync')
        ->name('events.sync');

    Route::post('/unlink', [MobileUnlinkController::class, 'store'])
        ->middleware('ability:mobile:sync')
        ->name('unlink');
});
