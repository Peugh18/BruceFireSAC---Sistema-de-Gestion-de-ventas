<?php

use App\Http\Controllers\Gerente\AuditController;
use App\Http\Controllers\Gerente\CashRegisterConsolidatedController;
use App\Http\Controllers\Gerente\CertificateTypeController;
use App\Http\Controllers\Gerente\ChispaController;
use App\Http\Controllers\Gerente\CollectionConsolidatedController;
use App\Http\Controllers\Gerente\CompanyBankAccountController;
use App\Http\Controllers\Gerente\CompanySettingController;
use App\Http\Controllers\Gerente\ConsultaController;
use App\Http\Controllers\Gerente\DashboardController;
use App\Http\Controllers\Gerente\InteligenciaController;
use App\Http\Controllers\Gerente\ProductCategoryController;
use App\Http\Controllers\Gerente\ProductController;
use App\Http\Controllers\Gerente\ReportController;
use App\Http\Controllers\Gerente\SedeController;
use App\Http\Controllers\Gerente\ServiceController;
use App\Http\Controllers\Gerente\SignerController;
use App\Http\Controllers\Gerente\SunatCodigoProductoController;
use App\Http\Controllers\Gerente\TipoDeServicioController;
use App\Http\Controllers\Gerente\TransporteController;
use App\Http\Controllers\Gerente\UserController;
use App\Http\Controllers\Vendedor\CollectionController as VendedorCollectionController;
use Illuminate\Support\Facades\Route;

// Rutas del rol Gerente. Se incluye desde routes/web.php dentro del grupo
// {current_team} (auth + verified + EnsureTeamMembership), igual que
// routes/vendedor.php, y además exige el rol "Gerente" (spatie/laravel-permission).
Route::prefix('gerente')
    ->name('gerente.')
    ->middleware('role:Gerente')
    ->group(function () {
        Route::get('dashboard', DashboardController::class)->name('dashboard');
        Route::get('inteligencia', InteligenciaController::class)->name('inteligencia');
        Route::get('dashboard/detalle', [DashboardController::class, 'detalle'])->name('dashboard.detalle');
        Route::get('consulta/ventas/{sale}', [ConsultaController::class, 'venta'])->middleware('can:sales.view')->name('consulta.venta');
        Route::get('consulta/ordenes/{service_order}', [ConsultaController::class, 'orden'])->middleware('can:service_orders.view')->name('consulta.orden');
        Route::get('consulta/clientes/{client}', [ConsultaController::class, 'cliente'])->middleware('can:clients.view')->name('consulta.cliente');

        // Rutas antiguas bloqueadas: el vendedor agrega transporte dentro de la guía.
        Route::get('transporte', [TransporteController::class, 'index'])->middleware('role:Vendedor')->name('transporte.index');
        Route::post('transporte/vehiculos', [TransporteController::class, 'storeVehiculo'])->middleware('role:Vendedor')->name('transporte.vehiculos.store');
        Route::patch('transporte/vehiculos/{vehiculo}/toggle', [TransporteController::class, 'toggleVehiculo'])->middleware('role:Vendedor')->name('transporte.vehiculos.toggle');
        Route::post('transporte/conductores', [TransporteController::class, 'storeConductor'])->middleware('role:Vendedor')->name('transporte.conductores.store');
        Route::patch('transporte/conductores/{conductor}/toggle', [TransporteController::class, 'toggleConductor'])->middleware('role:Vendedor')->name('transporte.conductores.toggle');

        // Catálogo de Productos y Servicios (§86.4.2)
        Route::resource('productos', ProductController::class)->except(['create', 'edit', 'show']);
        Route::patch('productos/{producto}/toggle-status', [ProductController::class, 'toggleStatus'])->name('productos.toggle-status');

        // Categorías del catálogo: las usan productos y servicios (A2)
        Route::post('categorias', [ProductCategoryController::class, 'store'])->name('categorias.store');
        Route::put('categorias/{categoria}', [ProductCategoryController::class, 'update'])->name('categorias.update');
        Route::delete('categorias/{categoria}', [ProductCategoryController::class, 'destroy'])->name('categorias.destroy');

        Route::resource('servicios', ServiceController::class)->except(['create', 'edit', 'show']);
        Route::patch('servicios/{servicio}/toggle-status', [ServiceController::class, 'toggleStatus'])->name('servicios.toggle-status');
        Route::resource('tipos-de-servicio', TipoDeServicioController::class)->only(['store', 'update', 'destroy'])->parameters(['tipos-de-servicio' => 'tipo_de_servicio']);

        // Caja consolidada (§77.2 punto 4)
        Route::get('cajas', [CashRegisterConsolidatedController::class, 'index'])->name('cajas.index');

        // Cobranzas consolidadas (§32)
        Route::get('cobranzas', [CollectionConsolidatedController::class, 'index'])->name('cobranzas.index');
        Route::delete('cobranzas/pagos/{payment}', [VendedorCollectionController::class, 'cancelPayment'])->name('cobranzas.pagos.anular');

        // Reportes Gerenciales (§34)
        Route::get('reportes', [ReportController::class, 'index'])->name('reportes.index');
        Route::get('reportes/comercial/pdf', [ReportController::class, 'exportComercialPdf'])->name('reportes.comercial.pdf');
        Route::get('reportes/inventario/pdf', [ReportController::class, 'exportInventarioPdf'])->name('reportes.inventario.pdf');

        // Preguntas que Chispa no supo responder (para mejorar los manuales)
        Route::get('chispa', [ChispaController::class, 'index'])->name('chispa.index');

        // Auditoría (§37)
        Route::get('auditoria', [AuditController::class, 'index'])->name('auditoria.index');

        // Sedes: tiendas, almacenes y sedes mixtas
        Route::resource('sedes', SedeController::class)->only(['index', 'store', 'update']);
        Route::patch('sedes/{sede}/toggle-status', [SedeController::class, 'toggleStatus'])->name('sedes.toggle-status');

        // Usuarios y roles (§36, §86.4.8)
        Route::get('usuarios', [UserController::class, 'index'])->name('usuarios.index');
        Route::post('usuarios', [UserController::class, 'store'])->name('usuarios.store');
        Route::patch('usuarios/{user}', [UserController::class, 'update'])->name('usuarios.update');
        Route::patch('usuarios/{user}/estado', [UserController::class, 'updateStatus'])->name('usuarios.update-status');
        Route::patch('usuarios/{user}/rol', [UserController::class, 'updateRole'])->name('usuarios.update-role');
        Route::patch('usuarios/{user}/sede', [UserController::class, 'updateSede'])->name('usuarios.update-sede');

        Route::get('configuracion/empresa', [CompanySettingController::class, 'edit'])->name('configuracion.empresa.edit');
        Route::post('configuracion/empresa', [CompanySettingController::class, 'update'])->name('configuracion.empresa.update');
        Route::get('configuracion/empresa/vista-previa-comprobante', [CompanySettingController::class, 'vistaPrevia'])->name('configuracion.empresa.vista-previa');

        Route::post('configuracion/empresa/cuentas-bancarias', [CompanyBankAccountController::class, 'store'])->name('configuracion.cuentas-bancarias.store');
        Route::put('configuracion/empresa/cuentas-bancarias/{cuenta_bancaria}', [CompanyBankAccountController::class, 'update'])->name('configuracion.cuentas-bancarias.update');
        Route::delete('configuracion/empresa/cuentas-bancarias/{cuenta_bancaria}', [CompanyBankAccountController::class, 'destroy'])->name('configuracion.cuentas-bancarias.destroy');

        // Catálogo 25 de SUNAT: se busca por nombre o código al crear productos y tipos de servicio.
        Route::get('sunat/codigos-producto', SunatCodigoProductoController::class)->name('sunat.codigos-producto');

        // Firmas y sellos de quienes firman los certificados
        // Certificados: textos de la plantilla general y certificados nuevos.
        Route::get('configuracion/certificados', [CertificateTypeController::class, 'index'])->name('configuracion.certificados.index');
        Route::get('configuracion/certificados/nuevo', [CertificateTypeController::class, 'create'])->name('configuracion.certificados.create');
        Route::post('configuracion/certificados', [CertificateTypeController::class, 'store'])->name('configuracion.certificados.store');
        Route::get('configuracion/certificados/{certificado}/editar', [CertificateTypeController::class, 'edit'])->name('configuracion.certificados.edit');
        Route::put('configuracion/certificados/{certificado}', [CertificateTypeController::class, 'update'])->name('configuracion.certificados.update');
        Route::get('configuracion/firmas', [SignerController::class, 'index'])->name('configuracion.firmas.index');
        Route::post('configuracion/firmas', [SignerController::class, 'store'])->name('configuracion.firmas.store');
        Route::post('configuracion/firmas/{firmante}', [SignerController::class, 'update'])->name('configuracion.firmas.update');
        Route::get('configuracion/firmas/{firmante}/{tipo}', [SignerController::class, 'imagen'])
            ->whereIn('tipo', ['firma', 'sello'])
            ->name('configuracion.firmas.imagen');
        Route::get('configuracion/firmas/vista-previa/{tipo:codigo}', [SignerController::class, 'vistaPrevia'])
            ->withoutScopedBindings()
            ->name('configuracion.firmas.vista-previa');
    });
