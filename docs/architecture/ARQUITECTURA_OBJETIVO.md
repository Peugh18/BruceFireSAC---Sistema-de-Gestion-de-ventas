# Arquitectura objetivo, modelo de datos y permisos

> Estado: **propuesta** (2026-10-28), sujeta a [ADR-0001](../../adr/0001-evolucionar-el-beta.md). Describe hacia dónde evolucionar el beta, no un sistema nuevo.

## 1. Capas (las que ya usa el proyecto, con reglas más estrictas)

```text
Página Inertia (resources/js/pages/<rol>/…)
   └─ componentes compartidos (resources/js/components/…)      ← presentación, sin reglas de negocio
Controlador delgado por rol (app/Http/Controllers/<Rol>/…)
   ├─ Form Request (validación de entrada)
   ├─ Policy / permiso (autorización)
   └─ Action de dominio (app/Actions/<Dominio>/…)              ← una operación de negocio, en transacción
         └─ Servicios (app/Services/…)                         ← cálculos y documentos compartidos
               └─ Modelos Eloquent + base MySQL               ← restricciones e integridad
Integraciones: Greenter (SUNAT), GRE, RENIEC/SUNAT, colas
```

**Reglas:**

1. **Una regla de negocio vive en un solo lugar del backend.** Ejemplos ya existentes: `CashRegister::exigirAbierta…`, `EmitirCertificadosDeVenta::tiposPorDestino`, `PrecioConIgv::TASA`.
2. **El frontend no calcula importes fiscales por su cuenta.** Muestra lo que calcula el backend o usa un helper compartido alimentado por él.
3. **Las métricas salen de un servicio único** ([METRICAS_FINANCIERAS.md](../finance-bi/METRICAS_FINANCIERAS.md)).
4. **Toda operación con dinero o SUNAT** es una Action en transacción, con bloqueo (`lockForUpdate`), idempotente y con su prueba.
5. **El estado de la venta, el del comprobante y el del pago** siguen en tablas separadas.

## 2. Modelo de datos: fuente oficial de cada dato

| Dato                            | Fuente oficial                                                                     | No debe vivir en                                                            |
| ------------------------------- | ---------------------------------------------------------------------------------- | --------------------------------------------------------------------------- |
| Cliente y sus sedes y vehículos | `clients`, `client_sites`, `vehicles`                                              | Texto libre en ventas u órdenes (hoy la «referencia» es texto; E-030)       |
| Producto, servicio y precio     | `products`, `services`, `tipos_de_servicio`                                        | Constantes en pantallas                                                     |
| Stock                           | `inventory_movements` (Kardex) e `inventory_units`                                 | Contadores en otras tablas                                                  |
| Extintor del cliente            | `equipment` (BF-EQ), con fechas reales de PH y recarga                             | Copias en certificados; el certificado guarda su foto histórica a propósito |
| Venta                           | `sales`, `sale_items`                                                              | —                                                                           |
| Comprobante fiscal              | `electronic_documents` (XML, CDR, estado SUNAT)                                    | Campos de estado SUNAT en `sales`                                           |
| Pagos y devoluciones            | `sale_payments`, `sale_refunds`, `installments`                                    | Campos «pagado» calculados a mano                                           |
| Caja                            | `cash_registers`, `cash_register_counts` y sus líneas, `cash_register_withdrawals` | —                                                                           |
| Orden de servicio               | `service_orders`, `service_order_lines`, `service_order_events`                    | —                                                                           |
| Certificado                     | `certificates`, `certificate_units`, versiones de tipo                             | —                                                                           |

**Pendiente de diseño (no existe hoy):** compras y proveedores, para respaldar el costo de compra (decisión P-05). Tampoco hay un diagrama ER generado. Se propone generarlo desde las migraciones (Laravel Boost `database-schema`) cuando se apruebe la fase.

## 3. Roles y permisos

| Rol               | Alcance                                                                                | Observación de la auditoría                   |
| ----------------- | -------------------------------------------------------------------------------------- | --------------------------------------------- |
| Gerente           | Toda la empresa: catálogo, usuarios, sedes, reportes, caja consolidada, reconteo       | Supervisa; no aprueba cada operación diaria   |
| Vendedor          | Su sede: clientes, cotizaciones, ventas, caja, cobranzas, órdenes, certificados, guías | Los clientes no pertenecen a una sede (E-039) |
| Almacén           | Su almacén: recepciones, stock, ajustes, traslados, fichas, stickers                   | —                                             |
| Técnico de Planta | Taller: recepción de equipos, checklist, hallazgos, reparación, cierre                 | —                                             |
| Técnico de Campo  | Entregas, recojos, inspecciones, instalaciones, mantenimientos                         | —                                             |

- **Hoy:** acceso por rol en las rutas (`role:…`) más algunos permisos de spatie y una sola Policy.
- **Propuesta:** mantener el control por rol en las rutas (ya probado con pruebas 403). Sumar Policies solo donde el acceso depende del registro (sede, vendedor, orden asignada). Generar la matriz de permisos desde las rutas reales (E-059).

## 4. Inventario de vistas y trazabilidad

- **Vistas actuales:** [INVENTARIO_VISTAS_ACTUAL.md](../views/INVENTARIO_VISTAS_ACTUAL.md), generado desde el código: 76 rutas de pantalla, 85 páginas, diálogos por pantalla y las páginas más grandes. Se regenera con `docs/audit/herramientas/inventario_vistas.py`.
- **Matriz objetivo** (requisito → proceso → módulo → vista → componente → servicio → entidad → permiso → prueba): se completa módulo por módulo al aprobar cada fase. Así no se duplica una matriz que envejece antes de usarse. Plantilla por fila:

| Requisito                            | Proceso         | Módulo | Vista (V-xxx)                 | Componentes                                    | Action / Servicio                                      | Entidades                 | Permiso                         | Prueba                      |
| ------------------------------------ | --------------- | ------ | ----------------------------- | ---------------------------------------------- | ------------------------------------------------------ | ------------------------- | ------------------------------- | --------------------------- |
| Ej.: «toda venta exige caja abierta» | Venta en tienda | Ventas | V-0xx `vendedor/ventas/nueva` | `ClientPicker`, `CatalogPicker`, aviso de caja | `CashRegister::exigirAbiertaParaVender`, `ConfirmSale` | `sales`, `cash_registers` | `role:Vendedor`, `sales.create` | `VentaExigeCajaAbiertaTest` |

## 5. Respaldo, migraciones y recuperación

- **Migraciones:** solo aditivas y con `down()`. Ninguna corrige datos históricos por suposición (regla aplicada por Codex en E-024).
- **Respaldo:** no hay un procedimiento documentado. Antes de producción hay que definir un respaldo diario de MySQL fuera de la PC, una prueba de restauración mensual y el resguardo del certificado `.pem` (decisión P-06).
- **Desarrollo:** MySQL de Laragon tiene binlog activo (permite recuperar un borrado). Nunca correr `migrate:fresh` fuera de Pest sin revisar `php artisan db:show`.
