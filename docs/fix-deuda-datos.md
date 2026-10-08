# Cierre de deuda de DATOS y MODELO — Bruce Fire S.A.C.

Fecha: 2026-10-08 · Ámbito: `database/migrations`, `app/Models`, `database/factories`, `database/seeders`, tests ajustados.
BD de pruebas usadas: `bruce_fire_mig_check` (migración desde cero) y `bruce_fire_test_deuda` (pest). `bruce_fire` (producción) NO se tocó.

## 1. `sales.medio_pago` / `sales.numero_operacion` vs `sale_payments` — DECISIÓN: `sale_payments` es la fuente de verdad

**Decisión**: el modelo lee SIEMPRE `sale_payments` y solo cae a la cabecera cuando la venta no tiene ningún cobro (ventas históricas). Las columnas `sales.medio_pago`/`numero_operacion` NO se borran: además de respaldo histórico son el campo de paso del formulario de la venta (`StoreSaleRequest` → `ConfirmSale::registrarCobros()` crea los `SalePayment` y luego las vacía con `update(['medio_pago' => null, ...])`).

Evidencia del riesgo real y de por qué ya está cubierto:

- `Sale::medioPagoTexto()` (`app/Models/Sale.php:100-108`) prioriza el primer pago por `id` y solo usa `sales.medio_pago` como fallback → la cabecera "yape" con pagos distintos NUNCA se pinta; el pago manda.
- Regeneración de PDF (`ComprobantePdfService::datos():214`): usa `medioPagoTexto()` → `RedibujarComprobantes` (`billing:redibujar-pdf`) muestra el medio real de `sale_payments`, incluso con datos antiguos (usa `datos_emision`/XML congelados para lo demás).
- XML a SUNAT: NO lleva medio de pago del vendedor; solo el `codMedioPago` de la detracción desde `config('billing.detraccion')` (`GreenterService:122`). La regeneración XML no depende de estas columnas.

Cambios: documentación de la decisión en `Sale::medioPagoTexto()` y en las propiedades `medio_pago`/`numero_operacion`. Tests: se conserva y se amplía la cobertura en `ReglasIntegridadBaseDatosTest` (con pagos → manda el pago; sin pagos → respaldo de la cabecera).

## 2. `sales.fecha` y `sale_payments.fecha` como `date` — DECISIÓN: se quedan en `date`; `created_at` es el respaldo de hora (ya existe y se documenta)

Análisis de impacto (evidencia):

- **Arqueos**: NO dependen de la hora de `fecha`. El cobro se liga a su turno por `sale_payments.cash_register_id` y los turnos tienen `cash_registers.fecha_apertura/fecha_cierre` como `datetime` (`CloseCashRegister` usa `now()`). El arqueo es por turno, no por día-hora.
- **Escrituras**: los flujos escriben fechas de negocio: `ConfirmSale` → `'fecha' => today()`; `CreateSale`/`StoreSaleRequest` validan `fecha` como date; `registrarCobros()` → `'fecha' => today()`. Migrar a `datetime` guardaría `00:00:00` igual: no aporta la hora sin tocar escritores (fuera de ámbito).
- **Cuotas**: `CreateSale:171` hace `fecha + 30 días` y `Sale::diasCredito()` usa `diffInDays` entre `fecha` y `fecha_vencimiento` (date) — con horas introducidas, `(int) diffInDays` empezaría a truncar según la hora del día (riesgo de cuotas con un día de diferencia).
- **Reportes/listados**: todos serializan con `->toDateString()` o comparan con `whereDate(...)`; cambiar el cast `date` → `datetime` cambiaría el JSON del frontend (fuera de ámbito, otro agente activo en `resources/js`).
- **Orden real de los cobros**: la hora de cada cobro está en `sale_payments.created_at` (y `updated_at` marca la anulación). Existe desde la creación de la tabla (`$table->timestamps()`), se rellena solo y no se puede perder.

Conclusión: lo que menos rompa es NO cambiar el esquema; `created_at` ya es el respaldo de hora. Queda documentado en `Sale::$fecha`, `SalePayment::$fecha` y con test (`el cobro conserva su hora de registro en created_at`). Si algún día se quiere el orden fino en Consolidados, es `orderBy('fecha')->orderBy('created_at')` en el controlador (fuera de este ámbito).

## 3. `products.categoria` / `services.categoria` → FK REAL sobre `product_categories.clave` (SIN pérdida de datos)

Migración `2026_10_08_040000_vincular_categoria_de_productos_y_servicios`:

- `''` se unifica a `NULL` (ambos significan "sin categoría"; la FK solo admite claves reales).
- Cualquier clave huérfana (dato anterior al catálogo) se conserva creando su fila en `product_categories` (mismo criterio que la migración `2026_10_07_100000`). Nada se borra ni se trunca.
- `products.categoria → product_categories.clave` y `services.categoria → product_categories.clave` con `ON DELETE RESTRICT` (MySQL 8.4 acepta la FK aunque el hijo sea `varchar(255)` y el padre `varchar(40)`, verificado en la BD de pruebas).

Por qué FK sobre la `clave` y no una columna `categoria_id`: la `clave` es inmutable por diseño (renombrar solo cambia el `nombre`), guardar `clave` es lo que hace todo el código actual, y así la integridad se cierra en la base sin romper controladores ni perder datos. Ahora la base impide borrar una categoría en uso ni renombrar su `clave` con uso (antes cualquier borrado o renombrado huérfanaba productos); el `nombre` visible sigue libre.

Modelos: `Product::categoriaDelCatalogo()` y `Service::categoriaDelCatalogo()` (belongsTo por `clave`), docblocks en `Product`, `Service` y `ProductCategory`. Tests en `ReglasIntegridadBaseDatosTest`.

## 4. `dispatch_guides.client_id` — añadido con FK y relleno desde el origen

Migración `2026_10_08_040100_add_client_id_a_guias_de_remision`:

- `client_id` nullable con FK a `clients` (`ON DELETE RESTRICT`, misma política que `sales.client_id`).
- Backfill: desde `sales.client_id` (guías con `sale_id`) y desde `service_orders.client_id` (guías con `service_order_id`). Guías de traslado entre sedes quedan `NULL` (son internas).
- El snapshot `destinatario_*` NO se toca: es lo que va impreso y a SUNAT.

Modelo `DispatchGuide`: `client_id` en fillable + docblock, relación `client()` y hook `creating` que completa `client_id` desde la venta/orden al crear guías nuevas (la FK se mantiene sola de aquí en adelante). Test nuevo en `GuiaRemisionTest`.

## 5. Tablas ML históricas: `cascadeOnDelete` → `restrict`

Migración `2026_10_08_040200_historial_ml_en_restrict` (patrón de la casa, como `2026_10_07_210000`):

- `ml_comprobantes_historicos.documento_cliente` → restrict (antes cascada).
- `ml_lineas_historicas.comprobante` → restrict (antes cascada).
- `down()` devuelve exactamente el estado anterior (cascada), verificado con rollback real.
  Test: `el historial de ML es inmutable y sus claves no se borran en cascada` en `ReglasIntegridadBaseDatosTest`.

## 6. `equipment.ubicacion_actual` y `deficiency_authorizations.autorizado_por` — DECISIÓN YA TOMADA: se quedan como texto libre

Son, respectivamente, la ubicación física que escribe el usuario y el nombre de la persona que autoriza: no son listas cerradas ni claves. NO se restringe nada. Ya estaba documentado en la migración `2026_10_07_210300_cerrar_listas_de_valores_de_esquema` y así continúa.

## Archivos cambiados por este trabajo

- `database/migrations/2026_10_08_040000_vincular_categoria_de_productos_y_servicios.php` (nueva)
- `database/migrations/2026_10_08_040100_add_client_id_a_guias_de_remision.php` (nueva)
- `database/migrations/2026_10_08_040200_historial_ml_en_restrict.php` (nueva)
- `app/Models/Sale.php` (docblocks: decisión #1 y respaldo de hora de `fecha`)
- `app/Models/SalePayment.php` (docblock: `fecha` vs `created_at`)
- `app/Models/Product.php`, `app/Models/Service.php` (relación `categoriaDelCatalogo()` + docblocks)
- `app/Models/ProductCategory.php` (docblock de la FK)
- `app/Models/DispatchGuide.php` (fill `client_id`, relación `client()`, hook `creating`)
- `tests/Feature/Database/ReglasIntegridadBaseDatosTest.php` (4 tests nuevos)
- `tests/Feature/GuiaRemisionTest.php` (1 test nuevo)

NO se tocaron: `resources/js` (otro agente), controladores/Actions/Requests/Services, `database/factories`, `database/seeders` (no hacía falta), las columnas `sales.medio_pago`/`numero_operacion`/`fecha`/`sale_payments.fecha` (decisiones #1 y #2), `equipment.ubicacion_actual` y `deficiency_authorizations.autorizado_por` (#6), migraciones antiguas, ni la BD `bruce_fire`. En el árbol git hay más archivos modificados de otros agentes en curso (controladores, `resources/js`, docs, otros tests): NO son de este trabajo.

## Verificación (todo real, sobre BD de pruebas)

1. **`php artisan migrate` completo desde cero** sobre `bruce_fire_mig_check` (creada vacía): 108 migraciones OK, incluidas las 3 nuevas. Verificado además con datos reales:
    - Producto con clave huérfana → se creó su categoría (`clave_huerfana`/`Clave_huerfana`) y el producto conservó su valor; producto con `categoria=''` → `NULL`.
    - Guía desde venta → `client_id` = cliente de la venta; guía desde orden → `client_id` = cliente de la orden.
    - `information_schema`: `products_categoria_foreign`, `services_categoria_foreign` (→ `product_categories.clave`), `dispatch_guides_client_id_foreign` (→ `clients.id`) y las 2 FK ML en `RESTRICT`.
2. **`migrate:rollback --step=3`**: los `down()` devuelven el estado exacto (FKs ML otra vez en `CASCADE`, columnas/FK nuevas desaparecen) y un `migrate` posterior las re-aplica.
3. **Pruebas** (BD de pruebas `bruce_fire_test_deuda`; corren una por archivo porque hay otro proceso usando `bruce_fire_testing` en paralelo):
    - `ReglasIntegridadBaseDatosTest`: 37/37 ✔ (incluye los 4 tests nuevos: respaldo del medio de pago, hora en `created_at`, FK de categorías, ML en restrict)
    - `GuiaRemisionTest`: 8/8 ✔ (incluye el test nuevo de `client_id`)
    - `BorradoProtegidoDeHistorialTest`: 2/2 ✔ · `NotaVentaTest`: 7/7 ✔ · `FaseCVentasCajaCobranzasTest`: 11/11 ✔ · `AuditoriaCajaYVentasTest`: 4/4 ✔ · `LotesEppTest`: 8/8 ✔
    - Extra (#1): `ComprobanteEnvioDiferidoTest --filter="redibujar|medio"` 1/1 ✔ (`billing:redibujar-pdf` regenera con el medio correcto) y `CobroAlContadoTest` 5/5 ✔ (la cabecera se vacía tras cobrar y `medioPagoTexto()` sale del pago).
4. **`./vendor/bin/pint --parallel --test`**: ✔ (mismo criterio que el hook).
5. **`./vendor/bin/phpstan analyse --no-progress`**: ✔ 0 errores.

Nota operativa: se dejaron las BD de pruebas `bruce_fire_mig_check` (con las filas de demostración del backfill) y `bruce_fire_test_deuda` por si hay que re-verificar; se pueden soltar sin problema.
