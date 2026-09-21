<?php

use App\Http\Controllers\Almacen\DashboardController;
use App\Http\Controllers\Almacen\ReceptionController;
use App\Http\Controllers\Almacen\StockController;
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
    });
