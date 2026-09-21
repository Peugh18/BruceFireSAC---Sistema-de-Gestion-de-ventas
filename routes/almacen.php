<?php

use App\Http\Controllers\Almacen\DashboardController;
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
    });
