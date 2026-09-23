<?php

use App\Http\Controllers\Vendedor\AlertController;
use App\Http\Controllers\Vendedor\BillingController;
use App\Http\Controllers\Vendedor\CashRegisterController;
use App\Http\Controllers\Vendedor\CertificateController;
use App\Http\Controllers\Vendedor\ClientController;
use App\Http\Controllers\Vendedor\ClientSiteController;
use App\Http\Controllers\Vendedor\CollectionController;
use App\Http\Controllers\Vendedor\CommunicationController;
use App\Http\Controllers\Vendedor\CreditNoteController;
use App\Http\Controllers\Vendedor\DashboardController;
use App\Http\Controllers\Vendedor\DeficiencyAuthorizationController;
use App\Http\Controllers\Vendedor\DeficiencyController;
use App\Http\Controllers\Vendedor\InventoryLookupController;
use App\Http\Controllers\Vendedor\QuoteController;
use App\Http\Controllers\Vendedor\RucLookupController;
use App\Http\Controllers\Vendedor\SaleController;
use App\Http\Controllers\Vendedor\SaleItemScanController;
use App\Http\Controllers\Vendedor\ServiceOrderController;
use App\Http\Controllers\Vendedor\VehicleController;
use Illuminate\Support\Facades\Route;

// Rutas del rol Vendedor. Se incluye desde routes/web.php dentro del grupo
// {current_team} (auth + verified + EnsureTeamMembership) y además exige el
// rol "Vendedor" (spatie/laravel-permission). Cada fase del plan añade sus
// propias rutas aquí dentro del mismo prefijo.
Route::prefix('vendedor')
    ->name('vendedor.')
    ->middleware('role:Vendedor')
    ->group(function () {
        Route::get('dashboard', DashboardController::class)->name('dashboard');

        Route::get('inventario/buscar-serie', [InventoryLookupController::class, 'bySerial'])->name('inventario.buscar-serie');

        Route::get('clientes', [ClientController::class, 'index'])->name('clientes.index');
        Route::post('clientes', [ClientController::class, 'store'])->name('clientes.store');
        Route::get('clientes/{client}', [ClientController::class, 'show'])->name('clientes.show');
        Route::put('clientes/{client}', [ClientController::class, 'update'])->name('clientes.update');

        Route::post('clientes/{client}/sites', [ClientSiteController::class, 'store'])->name('clientes.sites.store');
        Route::put('clientes/{client}/sites/{site}', [ClientSiteController::class, 'update'])->name('clientes.sites.update');
        Route::delete('clientes/{client}/sites/{site}', [ClientSiteController::class, 'destroy'])->name('clientes.sites.destroy');

        Route::post('clientes/{client}/vehiculos', [VehicleController::class, 'store'])->name('clientes.vehiculos.store');
        Route::put('clientes/{client}/vehiculos/{vehicle}', [VehicleController::class, 'update'])->name('clientes.vehiculos.update');
        Route::delete('clientes/{client}/vehiculos/{vehicle}', [VehicleController::class, 'destroy'])->name('clientes.vehiculos.destroy');

        Route::get('ruc-lookup', [RucLookupController::class, 'show'])->name('ruc-lookup');

        Route::get('cotizaciones', [QuoteController::class, 'index'])->name('cotizaciones.index');
        Route::get('cotizaciones/nueva', [QuoteController::class, 'create'])->name('cotizaciones.create');
        Route::post('cotizaciones', [QuoteController::class, 'store'])->name('cotizaciones.store');
        Route::post('cotizaciones/{quote}/enviar', [QuoteController::class, 'send'])->name('cotizaciones.send');
        Route::post('cotizaciones/{quote}/aceptar', [QuoteController::class, 'accept'])->name('cotizaciones.accept');
        Route::post('cotizaciones/{quote}/rechazar', [QuoteController::class, 'reject'])->name('cotizaciones.reject');

        Route::get('ventas', [SaleController::class, 'index'])->name('ventas.index');
        Route::get('ventas/nueva', [SaleController::class, 'create'])->name('ventas.create');
        Route::post('ventas', [SaleController::class, 'store'])->name('ventas.store');
        Route::get('ventas/escanear-serie', [SaleItemScanController::class, 'resolve'])->name('ventas.escanear-serie');
        Route::get('ventas/{sale}', [SaleController::class, 'show'])->name('ventas.show');
        Route::post('ventas/{sale}/confirmar', [SaleController::class, 'confirm'])->name('ventas.confirmar');

        Route::get('ordenes-servicio', [ServiceOrderController::class, 'index'])->name('ordenes-servicio.index');
        Route::post('ordenes-servicio', [ServiceOrderController::class, 'store'])->name('ordenes-servicio.store');
        Route::get('ordenes-servicio/{service_order}', [ServiceOrderController::class, 'show'])->name('ordenes-servicio.show');

        Route::get('comunicacion', [CommunicationController::class, 'index'])->name('comunicacion.index');

        Route::get('deficiencias', [DeficiencyController::class, 'index'])->name('deficiencias.index');
        Route::post('deficiencias/{deficiency}/autorizar', [DeficiencyAuthorizationController::class, 'store'])->name('deficiencias.autorizar');

        Route::get('alertas', [AlertController::class, 'index'])->name('alertas.index');

        Route::get('certificados', [CertificateController::class, 'index'])->name('certificados.index');
        Route::get('certificados/{certificate}', [CertificateController::class, 'show'])->name('certificados.show');
        Route::get('certificados/{certificate}/pdf', [CertificateController::class, 'pdf'])->name('certificados.pdf');

        Route::get('facturacion', [BillingController::class, 'index'])->name('facturacion.index');
        Route::post('facturacion/{electronic_document}/reenviar', [BillingController::class, 'resend'])->name('facturacion.resend');
        Route::get('facturacion/{electronic_document}/xml', [BillingController::class, 'downloadXml'])->name('facturacion.xml');
        Route::get('facturacion/{electronic_document}/cdr', [BillingController::class, 'downloadCdr'])->name('facturacion.cdr');
        Route::get('facturacion/{electronic_document}/pdf', [BillingController::class, 'downloadPdf'])->name('facturacion.pdf');
        Route::post('notas-credito', [CreditNoteController::class, 'store'])->name('notas-credito.store');

        Route::get('caja', [CashRegisterController::class, 'show'])->name('caja.index');
        Route::post('caja/abrir', [CashRegisterController::class, 'open'])->name('caja.abrir');
        Route::post('caja/{cash_register}/cerrar', [CashRegisterController::class, 'close'])->name('caja.cerrar');

        Route::get('cobranzas', [CollectionController::class, 'index'])->name('cobranzas.index');
        Route::post('cobranzas/cuotas/{installment}/pagar', [CollectionController::class, 'registerPayment'])->name('cobranzas.pagar');
    });
