<?php

use App\Http\Controllers\TechnicalOrderAssignmentController;
use App\Http\Controllers\TecnicoPlanta\ChecklistController;
use App\Http\Controllers\TecnicoPlanta\DashboardController;
use App\Http\Controllers\TecnicoPlanta\DeficiencyController;
use App\Http\Controllers\TecnicoPlanta\ExecutionController;
use App\Http\Controllers\TecnicoPlanta\ReceptionController;
use App\Http\Middleware\EnsureTechnicalOrderAccess;
use App\Http\Middleware\EnsureTieneSede;
use Illuminate\Support\Facades\Route;

// Rutas del rol Técnico de Planta (§85). Se incluye desde routes/web.php dentro
// del grupo {current_team} (auth + verified + EnsureTeamMembership) y exige el
// rol "TecnicoPlanta" (spatie/laravel-permission).
Route::prefix('tecnico-planta')
    ->name('tecnico-planta.')
    ->middleware(['role:TecnicoPlanta', EnsureTieneSede::class, EnsureTechnicalOrderAccess::class])
    ->group(function () {
        Route::get('dashboard', [DashboardController::class, 'index'])->name('dashboard');

        // Recepciones y Alta Técnica Rápida (§18)
        Route::get('recepciones', [ReceptionController::class, 'index'])->name('recepciones.index');
        Route::get('recepciones/{service_order}', [ReceptionController::class, 'show'])->name('recepciones.show');
        Route::post('ordenes/{service_order}/tomar', [TechnicalOrderAssignmentController::class, 'take'])->name('ordenes.tomar');
        Route::post('recepciones/{service_order}/confirmar', [ReceptionController::class, 'confirmReception'])->name('recepciones.confirm');
        Route::get('equipos/buscar', [ReceptionController::class, 'searchEquipment'])->name('equipos.search');
        Route::post('recepciones/{service_order}/equipos', [ReceptionController::class, 'storeEquipment'])->name('recepciones.equipos.store');
        Route::get('recepciones/{service_order}/stickers', [ReceptionController::class, 'printStickers'])->name('recepciones.stickers');

        // Checklist Técnico Digital (§19)
        Route::get('ordenes/{service_order}/equipos/{equipment}/checklist', [ChecklistController::class, 'create'])->name('checklist.create');
        Route::post('ordenes/{service_order}/equipos/{equipment}/checklist', [ChecklistController::class, 'store'])->name('checklist.store');

        // Deficiencias desde Planta (§20)
        Route::get('deficiencias', [DeficiencyController::class, 'index'])->name('deficiencias.index');
        Route::post('ordenes/{service_order}/deficiencias', [DeficiencyController::class, 'store'])->name('deficiencias.store');
        Route::post('deficiencias/{deficiency}/resolver', [DeficiencyController::class, 'resolve'])->name('deficiencias.resolve');

        // Ejecución técnica de taller y cierre (§85, Fase 5)
        Route::get('ordenes/{service_order}/ejecucion', [ExecutionController::class, 'show'])->name('ejecucion.show');
        Route::post('ordenes/{service_order}/deficiencias/{deficiency}/consumir-repuesto', [ExecutionController::class, 'consumeSpare'])->name('ejecucion.consume-spare');
        Route::post('ordenes/{service_order}/avanzar-estado', [ExecutionController::class, 'advance'])->name('ejecucion.advance');
    });
