<?php

use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

// Rutas del rol Almacén. Se incluye desde routes/web.php dentro del grupo
// {current_team} (auth + verified + EnsureTeamMembership) y además exige el
// rol "Almacen" (spatie/laravel-permission).
Route::prefix('almacen')
    ->name('almacen.')
    ->middleware('role:Almacen')
    ->group(function () {
        Route::get('dashboard', function () {
            return Inertia::render('almacen/placeholder');
        })->name('dashboard');
    });
