<?php

use App\Http\Controllers\DispatchGuideController;
use App\Http\Middleware\EnsureTieneSede;
use Illuminate\Support\Facades\Route;

// Guías de remisión electrónicas (Fase H). Se incluye desde routes/web.php
// dentro del grupo {current_team}. Las ven y crean los roles que mueven bienes:
// Vendedor (ventas), Técnico de Campo (recojo y entrega), Almacén (traslados)
// y Gerente; cada uno solo las de su sede.
Route::prefix('guias')
    ->name('guias.')
    ->middleware(['role:Vendedor|Gerente|Almacen|TecnicoCampo', EnsureTieneSede::class])
    ->group(function () {
        Route::get('/', [DispatchGuideController::class, 'index'])->middleware('can:guias_remision.view')->name('index');
        Route::get('nueva', [DispatchGuideController::class, 'create'])->middleware('can:guias_remision.create')->name('create');
        Route::post('/', [DispatchGuideController::class, 'store'])->middleware('can:guias_remision.create')->name('store');
        Route::post('{guide}/enviar', [DispatchGuideController::class, 'enviar'])->middleware('can:guias_remision.create')->name('enviar');
        Route::post('{guide}/consultar', [DispatchGuideController::class, 'consultar'])->middleware('can:guias_remision.view')->name('consultar');
    });
