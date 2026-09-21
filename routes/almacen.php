<?php

use App\Http\Controllers\Almacen\DashboardController;
use App\Http\Controllers\Almacen\ReceptionController;
use App\Http\Controllers\Almacen\ReceptionStickerController;
use App\Http\Controllers\Almacen\StockAdjustmentController;
use App\Http\Controllers\Almacen\StockController;
use App\Http\Controllers\Almacen\StockLookupController;
use Illuminate\Support\Facades\Route;

// Rutas del rol Almacén. Se incluye desde routes/web.php dentro del grupo
// {current_team} (auth + verified + EnsureTeamMembership) y además exige el
// rol "Almacen" (spatie/laravel-permission).
Route::prefix('almacen')
    ->name('almacen.')
    ->middleware('role:Almacen')
    ->group(function () {
        Route::get('dashboard', DashboardController::class)->name('dashboard');
        Route::get('stock', [StockController::class, 'index'])->name('stock.index');

        // Recepciones de proveedor (§84.8)
        Route::get('recepciones', [ReceptionController::class, 'index'])->name('recepciones.index');
        Route::get('recepciones/nueva', [ReceptionController::class, 'create'])->name('recepciones.create');
        Route::post('recepciones', [ReceptionController::class, 'store'])->name('recepciones.store');
        Route::get('recepciones/{reception}', [ReceptionController::class, 'show'])->name('recepciones.show');
        Route::put('recepciones/{reception}', [ReceptionController::class, 'update'])->name('recepciones.update');

        // Stickers de código de barras (§84.9)
        Route::get('stickers', [ReceptionStickerController::class, 'index'])->name('stickers.index');
        Route::get('recepciones/{reception}/stickers', [ReceptionStickerController::class, 'show'])->name('recepciones.stickers');

        // Ajustes de stock autorizados (§84.10)
        Route::get('ajustes', [StockAdjustmentController::class, 'index'])->name('ajustes.index');
        Route::post('ajustes', [StockAdjustmentController::class, 'store'])->name('ajustes.store');

        // Consulta rápida por serie / código de barras (§84.12)
        Route::get('consulta', [StockLookupController::class, 'index'])->name('consulta.index');
        Route::get('consulta/buscar', [StockLookupController::class, 'search'])->name('consulta.buscar');
    });
