<?php

use App\Http\Controllers\Vendedor\AlertController;
use App\Http\Controllers\Vendedor\BillingController;
use App\Http\Controllers\Vendedor\CashRegisterController;
use App\Http\Controllers\Vendedor\CatalogoController;
use App\Http\Controllers\Vendedor\CertificateController;
use App\Http\Controllers\Vendedor\ClientController;
use App\Http\Controllers\Vendedor\ClientSiteController;
use App\Http\Controllers\Vendedor\CollectionController;
use App\Http\Controllers\Vendedor\CommunicationController;
use App\Http\Controllers\Vendedor\CounterDeliveryController;
use App\Http\Controllers\Vendedor\CreditNoteController;
use App\Http\Controllers\Vendedor\DashboardController;
use App\Http\Controllers\Vendedor\DebitNoteController;
use App\Http\Controllers\Vendedor\DeficiencyAuthorizationController;
use App\Http\Controllers\Vendedor\DeficiencyController;
use App\Http\Controllers\Vendedor\InventoryLookupController;
use App\Http\Controllers\Vendedor\QuoteController;
use App\Http\Controllers\Vendedor\RucLookupController;
use App\Http\Controllers\Vendedor\SaleCertificateController;
use App\Http\Controllers\Vendedor\SaleController;
use App\Http\Controllers\Vendedor\SaleItemScanController;
use App\Http\Controllers\Vendedor\ServiceCertificateController;
use App\Http\Controllers\Vendedor\ServiceOrderController;
use App\Http\Controllers\Vendedor\ServiceOrderEquipmentController;
use App\Http\Controllers\Vendedor\ServiceOrderReceiptController;
use App\Http\Controllers\Vendedor\VehicleController;
use App\Http\Middleware\EnsureTieneSede;
use Illuminate\Support\Facades\Route;

// Rutas del rol Vendedor. Se incluye desde routes/web.php dentro del grupo
// {current_team} (auth + verified + EnsureTeamMembership) y además exige el
// rol "Vendedor" (spatie/laravel-permission). Cada fase del plan añade sus
// propias rutas aquí dentro del mismo prefijo.
Route::prefix('vendedor')
    ->name('vendedor.')
    ->middleware(['role:Vendedor', EnsureTieneSede::class])
    ->group(function () {
        Route::get('dashboard', DashboardController::class)->name('dashboard');

        Route::get('inventario/buscar-serie', [InventoryLookupController::class, 'bySerial'])->name('inventario.buscar-serie');

        Route::get('clientes', [ClientController::class, 'index'])->name('clientes.index');
        Route::post('clientes', [ClientController::class, 'store'])->name('clientes.store');
        Route::get('clientes/buscar', [ClientController::class, 'search'])->name('clientes.search');
        Route::get('clientes/exportar', [ClientController::class, 'export'])->name('clientes.export');
        Route::get('clientes/{client}/ficha', [CatalogoController::class, 'ficha'])->name('clientes.ficha');
        Route::get('catalogo/buscar', [CatalogoController::class, 'buscar'])->name('catalogo.buscar');
        Route::get('catalogo/unidades', [CatalogoController::class, 'unidades'])->name('catalogo.unidades');
        Route::get('clientes/{client}', [ClientController::class, 'show'])->name('clientes.show');
        Route::post('clientes/{client}/verificar-sunat', [ClientController::class, 'verifySunat'])->name('clientes.verificar-sunat');
        Route::put('clientes/{client}', [ClientController::class, 'update'])->name('clientes.update');
        Route::patch('clientes/{client}/direccion', [ClientController::class, 'actualizarDireccion'])->name('clientes.direccion');
        Route::patch('clientes/{client}/whatsapp', [ClientController::class, 'actualizarWhatsapp'])->name('clientes.whatsapp');
        Route::post('clientes/{client}/extintores/{equipment}/reportar-uso', [ClientController::class, 'reportEquipmentUsed'])->name('clientes.extintores.reportar-uso');

        Route::post('clientes/{client}/sites', [ClientSiteController::class, 'store'])->name('clientes.sites.store');
        Route::put('clientes/{client}/sites/{site}', [ClientSiteController::class, 'update'])->name('clientes.sites.update');
        Route::delete('clientes/{client}/sites/{site}', [ClientSiteController::class, 'destroy'])->name('clientes.sites.destroy');

        Route::post('clientes/{client}/vehiculos', [VehicleController::class, 'store'])->name('clientes.vehiculos.store');
        Route::put('clientes/{client}/vehiculos/{vehicle}', [VehicleController::class, 'update'])->name('clientes.vehiculos.update');
        Route::delete('clientes/{client}/vehiculos/{vehicle}', [VehicleController::class, 'destroy'])->name('clientes.vehiculos.destroy');

        Route::get('ruc-lookup', [RucLookupController::class, 'show'])->name('ruc-lookup');

        Route::get('cotizaciones', [QuoteController::class, 'index'])->name('cotizaciones.index');
        Route::get('cotizaciones/nueva', [QuoteController::class, 'create'])->middleware('can:quotes.create')->name('cotizaciones.create');
        Route::get('cotizaciones/buscar-catalogo', [QuoteController::class, 'searchCatalogo'])->name('cotizaciones.buscar-catalogo');
        Route::post('cotizaciones', [QuoteController::class, 'store'])->middleware('can:quotes.create')->name('cotizaciones.store');
        Route::post('cotizaciones/{quote}/enviar', [QuoteController::class, 'send'])->middleware('can:quotes.create')->name('cotizaciones.send');
        Route::get('cotizaciones/{quote}/pdf', [QuoteController::class, 'pdf'])->name('cotizaciones.pdf');
        Route::post('cotizaciones/{quote}/aceptar', [QuoteController::class, 'accept'])->middleware('can:quotes.convert')->name('cotizaciones.accept');
        Route::post('cotizaciones/{quote}/rechazar', [QuoteController::class, 'reject'])->middleware('can:quotes.update')->name('cotizaciones.reject');

        Route::get('ventas', [SaleController::class, 'index'])->name('ventas.index');
        Route::get('ventas/nueva', [SaleController::class, 'create'])->middleware('can:sales.create')->name('ventas.create');
        Route::post('ventas', [SaleController::class, 'store'])->middleware('can:sales.create')->name('ventas.store');
        Route::get('ventas/escanear-serie', [SaleItemScanController::class, 'resolve'])->name('ventas.escanear-serie');
        Route::get('ventas/{sale}', [SaleController::class, 'show'])->name('ventas.show');
        Route::get('ventas/{sale}/editar', [SaleController::class, 'edit'])->middleware('can:sales.create')->name('ventas.edit');
        Route::put('ventas/{sale}', [SaleController::class, 'update'])->middleware('can:sales.create')->name('ventas.update');
        Route::get('ventas/{sale}/nota-venta-pdf', [SaleController::class, 'notaVentaPdf'])->name('ventas.nota-venta-pdf');
        Route::post('ventas/{sale}/confirmar', [SaleController::class, 'confirm'])->middleware('can:sales.create')->name('ventas.confirmar');
        Route::post('ventas/{sale}/enviar-sunat', [SaleController::class, 'enviarSunat'])->middleware('can:sales.create')->name('ventas.enviar-sunat');
        Route::post('ventas/{sale}/anular', [SaleController::class, 'anular'])->middleware('can:sales.create')->name('ventas.anular');
        Route::post('ventas/{sale}/descartar', [SaleController::class, 'descartar'])->middleware('can:sales.create')->name('ventas.descartar');
        Route::get('ventas/{sale}/certificado-servicio/{tipo:codigo}', [ServiceCertificateController::class, 'create'])->middleware('can:certificates.print')->name('ventas.certificado-servicio.create')->withoutScopedBindings();
        Route::post('ventas/{sale}/certificado-servicio/{tipo:codigo}', [ServiceCertificateController::class, 'store'])->middleware('can:certificates.print')->name('ventas.certificado-servicio.store')->withoutScopedBindings();
        Route::post('ventas/{sale}/items/{item}/cambiar-unidad', [SaleController::class, 'cambiarUnidad'])->middleware('can:sales.create')->name('ventas.cambiar-unidad');
        Route::get('ventas/{sale}/certificados', [SaleCertificateController::class, 'create'])->middleware('can:certificates.print')->name('ventas.certificados.create');
        Route::post('ventas/{sale}/certificados', [SaleCertificateController::class, 'store'])->middleware('can:certificates.print')->name('ventas.certificados.store');

        Route::get('ordenes-servicio', [ServiceOrderController::class, 'index'])->name('ordenes-servicio.index');
        Route::post('ordenes-servicio', [ServiceOrderController::class, 'store'])->name('ordenes-servicio.store');
        Route::get('ordenes-servicio/{service_order}', [ServiceOrderController::class, 'show'])->name('ordenes-servicio.show');
        Route::put('ordenes-servicio/{service_order}', [ServiceOrderController::class, 'update'])->name('ordenes-servicio.update');
        Route::post('ordenes-servicio/{service_order}/anular', [ServiceOrderController::class, 'anular'])->name('ordenes-servicio.anular');
        Route::post('ordenes-servicio/{service_order}/asignar-tecnico', [ServiceOrderController::class, 'assign'])->name('ordenes-servicio.asignar-tecnico');
        Route::post('ordenes-servicio/{service_order}/equipos', [ServiceOrderEquipmentController::class, 'store'])->name('ordenes-servicio.equipos.store');
        Route::get('ordenes-servicio/{service_order}/constancia-recepcion', ServiceOrderReceiptController::class)->name('ordenes-servicio.constancia-recepcion');
        Route::get('ordenes-servicio/{service_order}/entrega-mostrador', [CounterDeliveryController::class, 'show'])->name('ordenes-servicio.entrega-mostrador.show');
        Route::post('ordenes-servicio/{service_order}/entrega-mostrador', [CounterDeliveryController::class, 'store'])->name('ordenes-servicio.entrega-mostrador.store');
        Route::get('ordenes-servicio/{service_order}/entrega-mostrador/acta-pdf', [CounterDeliveryController::class, 'pdf'])->name('ordenes-servicio.entrega-mostrador.pdf');

        Route::get('comunicacion', [CommunicationController::class, 'index'])->name('comunicacion.index');
        Route::post('comunicacion/{service_order}/nota', [CommunicationController::class, 'nota'])->name('comunicacion.nota');

        Route::get('deficiencias', [DeficiencyController::class, 'index'])->name('deficiencias.index');
        Route::post('deficiencias/{deficiency}/autorizar', [DeficiencyAuthorizationController::class, 'store'])->name('deficiencias.autorizar');

        Route::get('alertas', [AlertController::class, 'index'])->name('alertas.index');
        Route::post('alertas/ofrecer-recarga', [AlertController::class, 'offerRecharge'])->name('alertas.ofrecer-recarga');

        Route::get('certificados', [CertificateController::class, 'index'])->name('certificados.index');
        Route::get('certificados/{certificate}', [CertificateController::class, 'show'])->name('certificados.show');
        Route::get('certificados/{certificate}/pdf', [CertificateController::class, 'pdf'])->middleware('can:certificates.print')->name('certificados.pdf');
        Route::get('certificados/{certificate}/word', [CertificateController::class, 'word'])->middleware('can:certificates.print')->name('certificados.word');

        Route::get('facturacion', [BillingController::class, 'index'])->name('facturacion.index');
        Route::get('facturacion/descarga-masiva', [BillingController::class, 'descargaMasiva'])->name('facturacion.descarga-masiva');
        Route::get('facturacion/excel', [BillingController::class, 'exportarExcel'])->name('facturacion.excel');
        Route::post('facturacion/{electronic_document}/reenviar', [BillingController::class, 'resend'])->middleware('can:billing.resend')->name('facturacion.resend');
        Route::post('facturacion/{electronic_document}/baja', [BillingController::class, 'baja'])->middleware('can:billing.void')->name('facturacion.baja');
        Route::get('facturacion/{electronic_document}/xml', [BillingController::class, 'downloadXml'])->name('facturacion.xml');
        Route::get('facturacion/{electronic_document}/cdr', [BillingController::class, 'downloadCdr'])->name('facturacion.cdr');
        Route::get('facturacion/{electronic_document}/pdf', [BillingController::class, 'downloadPdf'])->name('facturacion.pdf');
        Route::post('notas-credito', [CreditNoteController::class, 'store'])->name('notas-credito.store');
        Route::post('notas-debito', [DebitNoteController::class, 'store'])->name('notas-debito.store');

        Route::get('caja', [CashRegisterController::class, 'show'])->name('caja.index');
        Route::post('caja/abrir', [CashRegisterController::class, 'open'])->middleware('can:cashregister.open')->name('caja.abrir');
        Route::post('caja/{cash_register}/cerrar', [CashRegisterController::class, 'close'])->middleware('can:cashregister.close')->name('caja.cerrar');

        Route::get('cobranzas', [CollectionController::class, 'index'])->name('cobranzas.index');
        Route::post('cobranzas/cuotas/{installment}/pagar', [CollectionController::class, 'registerPayment'])->middleware('can:collections.register_payment')->name('cobranzas.pagar');
        Route::delete('cobranzas/pagos/{payment}', [CollectionController::class, 'cancelPayment'])->middleware('can:collections.register_payment')->name('cobranzas.pagos.anular');
    });
