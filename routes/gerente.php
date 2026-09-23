<?php

use App\Http\Controllers\Gerente\AuditController;
use App\Http\Controllers\Gerente\CashRegisterConsolidatedController;
use App\Http\Controllers\Gerente\CollectionConsolidatedController;
use App\Http\Controllers\Gerente\CompanyBankAccountController;
use App\Http\Controllers\Gerente\CompanySettingController;
use App\Http\Controllers\Gerente\DashboardController;
use App\Http\Controllers\Gerente\ProductController;
use App\Http\Controllers\Gerente\ReportController;
use App\Http\Controllers\Gerente\SedeController;
use App\Http\Controllers\Gerente\ServiceController;
use App\Http\Controllers\Gerente\UserController;
use Illuminate\Support\Facades\Route;

// Rutas del rol Gerente. Se incluye desde routes/web.php dentro del grupo
// {current_team} (auth + verified + EnsureTeamMembership), igual que
// routes/vendedor.php, y además exige el rol "Gerente" (spatie/laravel-permission).
Route::prefix('gerente')
    ->name('gerente.')
    ->middleware('role:Gerente')
    ->group(function () {
        Route::get('dashboard', DashboardController::class)->name('dashboard');

        // Catálogo de Productos y Servicios (§86.4.2)
        Route::resource('productos', ProductController::class)->except(['create', 'edit', 'show']);
        Route::patch('productos/{producto}/toggle-status', [ProductController::class, 'toggleStatus'])->name('productos.toggle-status');

        Route::resource('servicios', ServiceController::class)->except(['create', 'edit', 'show']);
        Route::patch('servicios/{servicio}/toggle-status', [ServiceController::class, 'toggleStatus'])->name('servicios.toggle-status');

        // Caja consolidada (§77.2 punto 4)
        Route::get('cajas', [CashRegisterConsolidatedController::class, 'index'])->name('cajas.index');

        // Cobranzas consolidadas (§32)
        Route::get('cobranzas', [CollectionConsolidatedController::class, 'index'])->name('cobranzas.index');

        // Reportes Gerenciales (§34)
        Route::get('reportes', [ReportController::class, 'index'])->name('reportes.index');
        Route::get('reportes/comercial/pdf', [ReportController::class, 'exportComercialPdf'])->name('reportes.comercial.pdf');
        Route::get('reportes/inventario/pdf', [ReportController::class, 'exportInventarioPdf'])->name('reportes.inventario.pdf');

        // Auditoría (§37)
        Route::get('auditoria', [AuditController::class, 'index'])->name('auditoria.index');

        // Sedes: tiendas, almacenes y sedes mixtas
        Route::resource('sedes', SedeController::class)->only(['index', 'store', 'update']);
        Route::patch('sedes/{sede}/toggle-status', [SedeController::class, 'toggleStatus'])->name('sedes.toggle-status');

        // Usuarios y roles (§36, §86.4.8)
        Route::get('usuarios', [UserController::class, 'index'])->name('usuarios.index');
        Route::patch('usuarios/{user}/rol', [UserController::class, 'updateRole'])->name('usuarios.update-role');
        Route::patch('usuarios/{user}/sede', [UserController::class, 'updateSede'])->name('usuarios.update-sede');

        Route::get('configuracion/empresa', [CompanySettingController::class, 'edit'])->name('configuracion.empresa.edit');
        Route::post('configuracion/empresa', [CompanySettingController::class, 'update'])->name('configuracion.empresa.update');

        Route::post('configuracion/empresa/cuentas-bancarias', [CompanyBankAccountController::class, 'store'])->name('configuracion.cuentas-bancarias.store');
        Route::put('configuracion/empresa/cuentas-bancarias/{cuenta_bancaria}', [CompanyBankAccountController::class, 'update'])->name('configuracion.cuentas-bancarias.update');
        Route::delete('configuracion/empresa/cuentas-bancarias/{cuenta_bancaria}', [CompanyBankAccountController::class, 'destroy'])->name('configuracion.cuentas-bancarias.destroy');
    });
