<?php

use App\Http\Controllers\TechnicalOrderAssignmentController;
use App\Http\Controllers\TecnicoCampo\CollectionController;
use App\Http\Controllers\TecnicoCampo\DashboardController;
use App\Http\Controllers\TecnicoCampo\DeliveryController;
use App\Http\Controllers\TecnicoCampo\InspectionController;
use App\Http\Controllers\TecnicoCampo\InstallationController;
use App\Http\Controllers\TecnicoCampo\MaintenanceController;
use App\Http\Middleware\EnsureTechnicalOrderAccess;
use App\Http\Middleware\EnsureTieneSede;
use Illuminate\Support\Facades\Route;

// Rutas del rol Técnico de Campo (§85). Se incluye desde routes/web.php dentro
// del grupo {current_team} (auth + verified + EnsureTeamMembership) y exige el
// rol "TecnicoCampo" (spatie/laravel-permission).
Route::prefix('tecnico-campo')
    ->name('tecnico-campo.')
    ->middleware(['role:TecnicoCampo', EnsureTieneSede::class, EnsureTechnicalOrderAccess::class])
    ->group(function () {
        Route::get('dashboard', [DashboardController::class, 'index'])->name('dashboard');

        // Recojo y Cadena de Custodia (§22.1, §22.4, Fase 7)
        Route::get('recojos', [CollectionController::class, 'index'])->name('recojos.index');
        Route::get('recojos/{service_order}', [CollectionController::class, 'show'])->name('recojos.show');
        Route::post('ordenes/{service_order}/tomar', [TechnicalOrderAssignmentController::class, 'take'])->name('ordenes.tomar');
        Route::post('recojos/{service_order}', [CollectionController::class, 'store'])->name('recojos.store');
        Route::post('recojos/{service_order}/equipos', [CollectionController::class, 'storeEquipment'])->name('recojos.equipos.store');
        Route::get('recojos/{service_order}/constancia-recepcion', [CollectionController::class, 'receipt'])->name('recojos.constancia-recepcion');

        // Inspecciones de Campo (§24, Fase 8)
        Route::get('inspecciones', [InspectionController::class, 'index'])->name('inspecciones.index');
        Route::get('inspecciones/{service_order}', [InspectionController::class, 'show'])->name('inspecciones.show');
        Route::post('inspecciones/{service_order}/equipos', [InspectionController::class, 'storeEquipment'])->name('inspecciones.equipos.store');
        Route::post('inspecciones/{service_order}/equipos/{equipment}/checklist', [InspectionController::class, 'storeChecklist'])->name('inspecciones.checklist.store');
        Route::post('inspecciones/{service_order}/finalizar', [InspectionController::class, 'complete'])->name('inspecciones.complete');

        // Instalaciones en Campo (§25, Fase 9)
        Route::get('instalaciones', [InstallationController::class, 'index'])->name('instalaciones.index');
        Route::get('instalaciones/{service_order}', [InstallationController::class, 'show'])->name('instalaciones.show');
        Route::post('instalaciones/{service_order}', [InstallationController::class, 'store'])->name('instalaciones.store');

        // Mantenimiento en sitio (T2, Fase D)
        Route::get('mantenimientos', [MaintenanceController::class, 'index'])->name('mantenimientos.index');
        Route::get('mantenimientos/{service_order}', [MaintenanceController::class, 'show'])->name('mantenimientos.show');
        Route::post('mantenimientos/{service_order}/equipos', [MaintenanceController::class, 'storeEquipment'])->name('mantenimientos.equipos.store');
        Route::post('mantenimientos/{service_order}/equipos/{equipment}/checklist', [MaintenanceController::class, 'storeChecklist'])->name('mantenimientos.checklist.store');
        Route::post('mantenimientos/{service_order}/finalizar', [MaintenanceController::class, 'complete'])->name('mantenimientos.complete');
        Route::get('mantenimientos/{service_order}/acta-pdf', [MaintenanceController::class, 'pdf'])->name('mantenimientos.pdf');

        // Entrega Final y Acta de Conformidad (§22.3, §23, Fase 10)
        Route::get('entregas', [DeliveryController::class, 'index'])->name('entregas.index');
        Route::get('entregas/{service_order}', [DeliveryController::class, 'show'])->name('entregas.show');
        Route::post('entregas/{service_order}/confirmar', [DeliveryController::class, 'confirm'])->name('entregas.confirm');
        Route::get('entregas/{service_order}/acta-pdf', [DeliveryController::class, 'pdf'])->name('entregas.pdf');
    });
