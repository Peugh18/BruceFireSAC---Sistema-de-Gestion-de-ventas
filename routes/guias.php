<?php

use App\Http\Controllers\DispatchGuideController;
use App\Http\Middleware\EnsureTieneSede;
use Illuminate\Support\Facades\Route;

// Las guías de clientes las gestiona exclusivamente el Vendedor.
Route::prefix('guias')
    ->name('guias.')
    ->middleware(['role:Vendedor', EnsureTieneSede::class])
    ->group(function () {
        Route::get('/', [DispatchGuideController::class, 'index'])->middleware('can:guias_remision.view')->name('index');
        Route::get('nueva', [DispatchGuideController::class, 'create'])->middleware('can:guias_remision.create')->name('create');
        Route::post('/', [DispatchGuideController::class, 'store'])->middleware('can:guias_remision.create')->name('store');
        Route::get('{guide}', [DispatchGuideController::class, 'show'])->middleware('can:guias_remision.view')->name('show');
        Route::get('{guide}/documentos/{tipo}', [DispatchGuideController::class, 'documento'])->middleware('can:guias_remision.view')->name('documento');
        Route::patch('{guide}', [DispatchGuideController::class, 'update'])->middleware('can:guias_remision.create')->name('update');
        Route::post('{guide}/enviar', [DispatchGuideController::class, 'enviar'])->middleware('can:guias_remision.create')->name('enviar');
        Route::post('{guide}/consultar', [DispatchGuideController::class, 'consultar'])->middleware('can:guias_remision.view')->name('consultar');
    });
