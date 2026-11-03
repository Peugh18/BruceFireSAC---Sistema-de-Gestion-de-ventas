# Inventario de vistas actuales (generado desde el código)

> Generado por `docs/audit/herramientas/inventario_vistas.py` a partir de `php artisan route:list` y de las llamadas `Inertia::render` de cada controlador. Los diálogos salen de `DialogTitle`/`SheetTitle` de cada página (los títulos dinámicos no aparecen). Volver a generar después de cambiar rutas.

**Total:** 76 rutas de pantalla · 72 páginas con ruta · 85 archivos de página · 9 diálogos con título fijo.

## Público / común

| ID    | Ruta                                  | Nombre de ruta           | Página                          | Líneas | Diálogos y paneles | Controlador                                    |
| ----- | ------------------------------------- | ------------------------ | ------------------------------- | ------ | ------------------ | ---------------------------------------------- |
| V-001 | `/verificar-certificado/{token}`      | `certificados.verificar` | `publico/verificar-certificado` | 362    | —                  | `PublicCertificateVerificationController@show` |
| V-002 | `/{current_team}/dashboard`           | `dashboard`              | `dashboard`                     | 37     | —                  | `DashboardController`                          |
| V-003 | `/{current_team}/equipos/{equipment}` | `equipos.show`           | `equipos/show`                  | 110    | —                  | `EquipmentSheetController@show`                |

## Mi cuenta (todos)

| ID    | Ruta                 | Nombre de ruta  | Página              | Líneas | Diálogos y paneles | Controlador                        |
| ----- | -------------------- | --------------- | ------------------- | ------ | ------------------ | ---------------------------------- |
| V-004 | `/settings/profile`  | `profile.edit`  | `settings/profile`  | 139    | —                  | `Settings\ProfileController@edit`  |
| V-005 | `/settings/security` | `security.edit` | `settings/security` | 163    | —                  | `Settings\SecurityController@edit` |

## Gerente

| ID    | Ruta                                                       | Nombre de ruta                             | Página                               | Líneas | Diálogos y paneles | Controlador                                        |
| ----- | ---------------------------------------------------------- | ------------------------------------------ | ------------------------------------ | ------ | ------------------ | -------------------------------------------------- |
| V-006 | `/{current_team}/gerente/auditoria`                        | `gerente.auditoria.index`                  | `gerente/auditoria/index`            | 410    | —                  | `Gerente\AuditController@index`                    |
| V-007 | `/{current_team}/gerente/cajas`                            | `gerente.cajas.index`                      | `gerente/cajas/index`                | 921    | —                  | `Gerente\CashRegisterConsolidatedController@index` |
| V-008 | `/{current_team}/gerente/chispa`                           | `gerente.chispa.index`                     | `gerente/chispa/index`               | 130    | —                  | `Gerente\ChispaController@index`                   |
| V-009 | `/{current_team}/gerente/cobranzas`                        | `gerente.cobranzas.index`                  | `gerente/cobranzas/index`            | 770    | —                  | `Gerente\CollectionConsolidatedController@index`   |
| V-010 | `/{current_team}/gerente/configuracion/certificados`       | `gerente.configuracion.certificados.index` | `gerente/configuracion/certificados` | 127    | —                  | `Gerente\CertificateTypeController@index`          |
| V-011 | `/{current_team}/gerente/configuracion/empresa`            | `gerente.configuracion.empresa.edit`       | `gerente/configuracion/empresa`      | 739    | —                  | `Gerente\CompanySettingController@edit`            |
| V-012 | `/{current_team}/gerente/configuracion/firmas`             | `gerente.configuracion.firmas.index`       | `gerente/configuracion/firmas`       | 478    | —                  | `Gerente\SignerController@index`                   |
| V-013 | `/{current_team}/gerente/consulta/clientes/{client}`       | `gerente.consulta.cliente`                 | `gerente/consulta/ficha`             | 65     | —                  | `Gerente\ConsultaController@cliente`               |
| V-014 | `/{current_team}/gerente/consulta/ordenes/{service_order}` | `gerente.consulta.orden`                   | `gerente/consulta/ficha`             | 65     | —                  | `Gerente\ConsultaController@orden`                 |
| V-015 | `/{current_team}/gerente/consulta/ventas/{sale}`           | `gerente.consulta.venta`                   | `gerente/consulta/ficha`             | 65     | —                  | `Gerente\ConsultaController@venta`                 |
| V-016 | `/{current_team}/gerente/dashboard`                        | `gerente.dashboard`                        | `gerente/dashboard`                  | 519    | —                  | `Gerente\DashboardController`                      |
| V-017 | `/{current_team}/gerente/dashboard/detalle`                | `gerente.dashboard.detalle`                | `gerente/dashboard-detalle`          | 80     | —                  | `Gerente\DashboardController@detalle`              |
| V-018 | `/{current_team}/gerente/inteligencia`                     | `gerente.inteligencia`                     | `gerente/inteligencia`               | 866    | —                  | `Gerente\InteligenciaController`                   |
| V-019 | `/{current_team}/gerente/productos`                        | `gerente.productos.index`                  | `gerente/productos/index`            | 1222   | —                  | `Gerente\ProductController@index`                  |
| V-020 | `/{current_team}/gerente/sedes`                            | `gerente.sedes.index`                      | `gerente/sedes/index`                | 599    | —                  | `Gerente\SedeController@index`                     |
| V-021 | `/{current_team}/gerente/servicios`                        | `gerente.servicios.index`                  | `gerente/servicios/index`            | 1098   | —                  | `Gerente\ServiceController@index`                  |
| V-022 | `/{current_team}/gerente/transporte`                       | `gerente.transporte.index`                 | `gerente/transporte/index`           | 277    | —                  | `Gerente\TransporteController@index`               |
| V-023 | `/{current_team}/gerente/usuarios`                         | `gerente.usuarios.index`                   | `gerente/usuarios/index`             | 716    | —                  | `Gerente\UserController@index`                     |

## Vendedor

| ID    | Ruta                                                                          | Nombre de ruta                                     | Página                                        | Líneas | Diálogos y paneles                                                                                           | Controlador                                    |
| ----- | ----------------------------------------------------------------------------- | -------------------------------------------------- | --------------------------------------------- | ------ | ------------------------------------------------------------------------------------------------------------ | ---------------------------------------------- |
| V-024 | `/{current_team}/vendedor/alertas`                                            | `vendedor.alertas.index`                           | `vendedor/alertas/index`                      | 542    | —                                                                                                            | `Vendedor\AlertController@index`               |
| V-025 | `/{current_team}/vendedor/caja`                                               | `vendedor.caja.index`                              | `vendedor/cobranzas/index`                    | 1613   | Abrir turno de caja; Cerrar turno de caja; Registrar salida de dinero; Registrar pago de cuota; Anular cobro | `Vendedor\CashRegisterController@show`         |
| V-026 | `/{current_team}/vendedor/certificados`                                       | `vendedor.certificados.index`                      | `vendedor/certificados/index`                 | 560    | —                                                                                                            | `Vendedor\CertificateController@index`         |
| V-027 | `/{current_team}/vendedor/certificados/{certificate}`                         | `vendedor.certificados.show`                       | `vendedor/certificados/show`                  | 618    | Corregir datos del certificado                                                                               | `Vendedor\CertificateController@show`          |
| V-028 | `/{current_team}/vendedor/clientes`                                           | `vendedor.clientes.index`                          | `vendedor/clientes/index`                     | 587    | —                                                                                                            | `Vendedor\ClientController@index`              |
| V-029 | `/{current_team}/vendedor/clientes/{client}`                                  | `vendedor.clientes.show`                           | `vendedor/clientes/show`                      | 2890   | Editar datos del cliente                                                                                     | `Vendedor\ClientController@show`               |
| V-030 | `/{current_team}/vendedor/cobranzas`                                          | `vendedor.cobranzas.index`                         | `vendedor/cobranzas/pendientes`               | 9      | —                                                                                                            | `Vendedor\CollectionController@index`          |
| V-031 | `/{current_team}/vendedor/comunicacion`                                       | `vendedor.comunicacion.index`                      | `vendedor/comunicacion/index`                 | 242    | —                                                                                                            | `Vendedor\CommunicationController@index`       |
| V-032 | `/{current_team}/vendedor/cotizaciones`                                       | `vendedor.cotizaciones.index`                      | `vendedor/cotizaciones/index`                 | 1076   | —                                                                                                            | `Vendedor\QuoteController@index`               |
| V-033 | `/{current_team}/vendedor/cotizaciones/nueva`                                 | `vendedor.cotizaciones.create`                     | `vendedor/cotizaciones/nueva`                 | 761    | —                                                                                                            | `Vendedor\QuoteController@create`              |
| V-034 | `/{current_team}/vendedor/cotizaciones/{quote}/editar`                        | `vendedor.cotizaciones.edit`                       | `vendedor/cotizaciones/nueva`                 | 761    | —                                                                                                            | `Vendedor\QuoteController@edit`                |
| V-035 | `/{current_team}/vendedor/dashboard`                                          | `vendedor.dashboard`                               | `vendedor/dashboard`                          | 761    | —                                                                                                            | `Vendedor\DashboardController`                 |
| V-036 | `/{current_team}/vendedor/deficiencias`                                       | `vendedor.deficiencias.index`                      | `vendedor/deficiencias/index`                 | 191    | —                                                                                                            | `Vendedor\DeficiencyController@index`          |
| V-037 | `/{current_team}/vendedor/facturacion`                                        | `vendedor.facturacion.index`                       | `vendedor/facturacion/index`                  | 771    | —                                                                                                            | `Vendedor\BillingController@index`             |
| V-038 | `/{current_team}/vendedor/ordenes-servicio`                                   | `vendedor.ordenes-servicio.index`                  | `vendedor/ordenes-servicio/index`             | 816    | Nueva orden de servicio                                                                                      | `Vendedor\ServiceOrderController@index`        |
| V-039 | `/{current_team}/vendedor/ordenes-servicio/{service_order}`                   | `vendedor.ordenes-servicio.show`                   | `vendedor/ordenes-servicio/show`              | 1044   | —                                                                                                            | `Vendedor\ServiceOrderController@show`         |
| V-040 | `/{current_team}/vendedor/ordenes-servicio/{service_order}/entrega-mostrador` | `vendedor.ordenes-servicio.entrega-mostrador.show` | `vendedor/ordenes-servicio/entrega-mostrador` | 158    | —                                                                                                            | `Vendedor\CounterDeliveryController@show`      |
| V-041 | `/{current_team}/vendedor/ventas`                                             | `vendedor.ventas.index`                            | `vendedor/ventas/index`                       | 585    | —                                                                                                            | `Vendedor\SaleController@index`                |
| V-042 | `/{current_team}/vendedor/ventas/nueva`                                       | `vendedor.ventas.create`                           | `vendedor/ventas/nueva`                       | 1828   | —                                                                                                            | `Vendedor\SaleController@create`               |
| V-043 | `/{current_team}/vendedor/ventas/{sale}`                                      | `vendedor.ventas.show`                             | `vendedor/ventas/show`                        | 1248   | Formalizar nota de venta                                                                                     | `Vendedor\SaleController@show`                 |
| V-044 | `/{current_team}/vendedor/ventas/{sale}/certificado-servicio/{tipo:codigo}`   | `vendedor.ventas.certificado-servicio.create`      | `vendedor/ventas/certificado-servicio`        | 698    | —                                                                                                            | `Vendedor\ServiceCertificateController@create` |
| V-045 | `/{current_team}/vendedor/ventas/{sale}/certificados`                         | `vendedor.ventas.certificados.create`              | `vendedor/ventas/certificados`                | 1286   | —                                                                                                            | `Vendedor\SaleCertificateController@create`    |
| V-046 | `/{current_team}/vendedor/ventas/{sale}/editar`                               | `vendedor.ventas.edit`                             | `vendedor/ventas/nueva`                       | 1828   | —                                                                                                            | `Vendedor\SaleController@edit`                 |
| V-047 | `/{current_team}/vendedor/ventas/{sale}/nota/{tipo}`                          | `vendedor.ventas.nota`                             | `vendedor/ventas/nota`                        | 516    | —                                                                                                            | `Vendedor\NotaController@create`               |

## Guías (Vendedor)

| ID    | Ruta                            | Nombre de ruta | Página        | Líneas | Diálogos y paneles | Controlador                      |
| ----- | ------------------------------- | -------------- | ------------- | ------ | ------------------ | -------------------------------- |
| V-048 | `/{current_team}/guias`         | `guias.index`  | `guias/index` | 218    | —                  | `DispatchGuideController@index`  |
| V-049 | `/{current_team}/guias/nueva`   | `guias.create` | `guias/form`  | 512    | —                  | `DispatchGuideController@create` |
| V-050 | `/{current_team}/guias/{guide}` | `guias.show`   | `guias/show`  | 565    | —                  | `DispatchGuideController@show`   |

## Almacen

| ID    | Ruta                                              | Nombre de ruta               | Página                       | Líneas | Diálogos y paneles | Controlador                                |
| ----- | ------------------------------------------------- | ---------------------------- | ---------------------------- | ------ | ------------------ | ------------------------------------------ |
| V-051 | `/{current_team}/almacen/ajustes`                 | `almacen.ajustes.index`      | `almacen/ajustes/index`      | 837    | —                  | `Almacen\StockAdjustmentController@index`  |
| V-052 | `/{current_team}/almacen/consulta`                | `almacen.consulta.index`     | `almacen/consulta/index`     | 508    | —                  | `Almacen\StockLookupController@index`      |
| V-053 | `/{current_team}/almacen/dashboard`               | `almacen.dashboard`          | `almacen/dashboard`          | 548    | —                  | `Almacen\DashboardController`              |
| V-054 | `/{current_team}/almacen/fichas`                  | `almacen.fichas.index`       | `almacen/fichas/index`       | 192    | —                  | `Almacen\FichaTecnicaController@index`     |
| V-055 | `/{current_team}/almacen/recepciones`             | `almacen.recepciones.index`  | `almacen/recepciones/index`  | 458    | —                  | `Almacen\ReceptionController@index`        |
| V-056 | `/{current_team}/almacen/recepciones/nueva`       | `almacen.recepciones.create` | `almacen/recepciones/create` | 1199   | —                  | `Almacen\ReceptionController@create`       |
| V-057 | `/{current_team}/almacen/recepciones/{reception}` | `almacen.recepciones.show`   | `almacen/recepciones/show`   | 900    | —                  | `Almacen\ReceptionController@show`         |
| V-058 | `/{current_team}/almacen/stickers`                | `almacen.stickers.index`     | `almacen/stickers/index`     | 298    | —                  | `Almacen\ReceptionStickerController@index` |
| V-059 | `/{current_team}/almacen/stock`                   | `almacen.stock.index`        | `almacen/stock/index`        | 1074   | —                  | `Almacen\StockController@index`            |
| V-060 | `/{current_team}/almacen/traslados`               | `almacen.traslados.index`    | `almacen/traslados/index`    | 395    | —                  | `Almacen\TransferController@index`         |

## Técnico de Planta

| ID    | Ruta                                                     | Nombre de ruta                      | Página                              | Líneas | Diálogos y paneles | Controlador                                |
| ----- | -------------------------------------------------------- | ----------------------------------- | ----------------------------------- | ------ | ------------------ | ------------------------------------------ |
| V-061 | `/{current_team}/tecnico-planta/dashboard`               | `tecnico-planta.dashboard`          | `tecnico-planta/dashboard`          | 510    | —                  | `TecnicoPlanta\DashboardController@index`  |
| V-062 | `/{current_team}/tecnico-planta/deficiencias`            | `tecnico-planta.deficiencias.index` | `tecnico-planta/deficiencias/index` | 278    | —                  | `TecnicoPlanta\DeficiencyController@index` |
| V-063 | `/{current_team}/tecnico-planta/ordenes/{service_order}` | `tecnico-planta.ordenes.show`       | `tecnico-planta/orden/show`         | 1508   | —                  | `TecnicoPlanta\OrdenTallerController@show` |
| V-064 | `/{current_team}/tecnico-planta/recepciones`             | `tecnico-planta.recepciones.index`  | `tecnico-planta/recepciones/index`  | 237    | —                  | `TecnicoPlanta\ReceptionController@index`  |

## Técnico de Campo

| ID    | Ruta                                                           | Nombre de ruta                       | Página                               | Líneas | Diálogos y paneles | Controlador                                   |
| ----- | -------------------------------------------------------------- | ------------------------------------ | ------------------------------------ | ------ | ------------------ | --------------------------------------------- |
| V-065 | `/{current_team}/tecnico-campo/dashboard`                      | `tecnico-campo.dashboard`            | `tecnico-campo/dashboard`            | 435    | —                  | `TecnicoCampo\DashboardController@index`      |
| V-066 | `/{current_team}/tecnico-campo/entregas`                       | `tecnico-campo.entregas.index`       | `tecnico-campo/entregas/index`       | 362    | —                  | `TecnicoCampo\DeliveryController@index`       |
| V-067 | `/{current_team}/tecnico-campo/entregas/{service_order}`       | `tecnico-campo.entregas.show`        | `tecnico-campo/entregas/show`        | 714    | —                  | `TecnicoCampo\DeliveryController@show`        |
| V-068 | `/{current_team}/tecnico-campo/inspecciones`                   | `tecnico-campo.inspecciones.index`   | `tecnico-campo/inspecciones/index`   | 378    | —                  | `TecnicoCampo\InspectionController@index`     |
| V-069 | `/{current_team}/tecnico-campo/inspecciones/{service_order}`   | `tecnico-campo.inspecciones.show`    | `tecnico-campo/inspecciones/show`    | 878    | —                  | `TecnicoCampo\InspectionController@show`      |
| V-070 | `/{current_team}/tecnico-campo/instalaciones`                  | `tecnico-campo.instalaciones.index`  | `tecnico-campo/instalaciones/index`  | 384    | —                  | `TecnicoCampo\InstallationController@index`   |
| V-071 | `/{current_team}/tecnico-campo/instalaciones/{service_order}`  | `tecnico-campo.instalaciones.show`   | `tecnico-campo/instalaciones/show`   | 896    | —                  | `TecnicoCampo\InstallationController@show`    |
| V-072 | `/{current_team}/tecnico-campo/mantenimientos`                 | `tecnico-campo.mantenimientos.index` | `tecnico-campo/mantenimientos/index` | 114    | —                  | `TecnicoCampo\MaintenanceController@index`    |
| V-073 | `/{current_team}/tecnico-campo/mantenimientos/{service_order}` | `tecnico-campo.mantenimientos.show`  | `tecnico-campo/mantenimientos/show`  | 535    | —                  | `TecnicoCampo\MaintenanceController@show`     |
| V-074 | `/{current_team}/tecnico-campo/recojos`                        | `tecnico-campo.recojos.index`        | `tecnico-campo/recojos/index`        | 167    | —                  | `TecnicoCampo\CollectionController@index`     |
| V-075 | `/{current_team}/tecnico-campo/recojos/nuevo`                  | `tecnico-campo.recojos.create`       | `tecnico-campo/recojos/create`       | 505    | —                  | `TecnicoCampo\IniciarRecojoController@create` |
| V-076 | `/{current_team}/tecnico-campo/recojos/{service_order}`        | `tecnico-campo.recojos.show`         | `tecnico-campo/recojos/show`         | 638    | —                  | `TecnicoCampo\CollectionController@show`      |

## Páginas sin ruta GET propia

`auth/*` las sirve Fortify (`FortifyServiceProvider`); `errors/*`, el manejador de excepciones; `settings/appearance` es una vista directa. Las demás se abren desde otra pantalla o son restos: revisarlas una por una (los diálogos de usuarios guardados en `pages/` deberían ir a `components/`).

- `auth/confirm-password`
- `auth/forgot-password`
- `auth/login`
- `auth/reset-password`
- `auth/two-factor-challenge`
- `auth/verify-email`
- `errors/error`
- `errors/sin-sede`
- `gerente/configuracion/certificado-editor`
- `gerente/reportes/index`
- `gerente/usuarios/editar-usuario-dialog`
- `gerente/usuarios/nuevo-usuario-dialog`
- `settings/appearance`

## Páginas más grandes (candidatas a dividir en componentes)

| Página                           | Líneas |
| -------------------------------- | ------ |
| `vendedor/clientes/show`         | 2890   |
| `vendedor/ventas/nueva`          | 1828   |
| `vendedor/cobranzas/index`       | 1613   |
| `tecnico-planta/orden/show`      | 1508   |
| `vendedor/ventas/certificados`   | 1286   |
| `vendedor/ventas/show`           | 1248   |
| `gerente/productos/index`        | 1222   |
| `almacen/recepciones/create`     | 1199   |
| `gerente/servicios/index`        | 1098   |
| `vendedor/cotizaciones/index`    | 1076   |
| `almacen/stock/index`            | 1074   |
| `vendedor/ordenes-servicio/show` | 1044   |
