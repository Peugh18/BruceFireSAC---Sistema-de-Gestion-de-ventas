# Auditoría independiente de Codex — 09/10/2026

## Dictamen

La entrega contiene mejoras reales y supera la suite existente, pero no corresponde afirmar «0 fallos» ni «todo en verde». La regla de detracción agregada en 53cda2b tiene un defecto reproducido con dos regresiones nuevas y una discrepancia entre la validación del navegador y el servidor. Hay además contraste insuficiente, un error de hidratación en Cajas y avisos duplicados.

Se auditó el checkout real `D:/TiomiguelonGgs/Documents/BRUCE FIRE/BruceFireSacv2`, rama `fix/flujo-cotizacion-auditoria`, HEAD `53cda2bb030d9319564720c99a32b84a75b5f7ab`, contra `http://127.0.0.1:8000`. La ruta Desktop configurada en el entorno no existe. SUNAT está en beta. No se modificó código de aplicación, dependencias, credenciales ni reglas fiscales; no se hizo commit ni push.

## Verificación ejecutada

| Comprobación            | Resultado y alcance                                                                                                                                                                                                               |
| ----------------------- | --------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| Suite completa          | `php artisan test --compact --parallel --processes=2`: 944/944, 5.060 aserciones, 435 segundos. Bases de prueba, sin reiniciar la base de desarrollo.                                                                             |
| TypeScript              | `npx tsc --noEmit`: salida 0.                                                                                                                                                                                                     |
| PHPStan                 | `php vendor/bin/phpstan analyse --no-progress --error-format=table`: 0 errores.                                                                                                                                                   |
| Pint                    | `php vendor/bin/pint --test`: passed.                                                                                                                                                                                             |
| CI original             | `composer ci:check` se detuvo en `npm run check`: formato de `docs/ai/BITACORA.md`. Se limitó temporalmente el paralelismo a dos procesos y se restauró composer.json byte por byte. Los demás checks se ejecutaron por separado. |
| Navegación autenticada  | 37 rutas de listado/formulario: vendedor 12, gerente 8, almacén 8, planta 3, campo 6. Todas respondieron 200 tras reintentar timeouts. No son 37 flujos completos ni una certificación de todos los botones.                      |
| Aislamiento entre roles | Vendedor hacia almacén y almacén/planta/campo hacia vendedor: 403. No demuestra por sí solo aislamiento de todas las sedes y todos los endpoints.                                                                                 |
| Axe                     | axe-core 4.10.3 desde cdnjs, tags WCAG 2 A/AA, 2.1 AA y 2.2 AA: una pantalla por rol. Deficiencias del vendedor presentó infracciones de contraste. Las otras cuatro no presentaron infracciones automáticas en ese estado.       |

Después de guardar y formatear únicamente la documentación del informe y memoria, `npm run check` pasó: 213 archivos con formato correcto y 175 sin errores de lint. No se volvió a ejecutar la suite completa por un cambio exclusivo de documentación; los demás checks anteriores corresponden al mismo código de aplicación.

Los timeouts iniciales y errores de selectores de los scripts se corrigieron o se anotaron como limitaciones; no se clasificaron como fallos de aplicación. La suite existente no cubría las dos regresiones nuevas de cantidades.

## Hallazgos reproducibles

### H-1 · Alta: un consumo de una unidad acredita cualquier cantidad del mismo producto

`app/Services/Billing/DetraccionCalculator.php:87-111` usa `exists()` para encontrar una salida del mismo producto en una orden de esa venta. No asigna el consumo a una línea ni compara cantidades.

Reproducción en base de pruebas: factura en borrador, total mayor de S/700, servicio clasificado 022 y salida de taller de una unidad del producto. Una línea de diez unidades sin `deficiency_id` es aceptada como parte del servicio. En una segunda prueba, líneas de una y tres unidades del mismo producto también son aceptadas con un único consumo de una unidad. Ambas pruebas esperaban `ValidationException` y fallaron porque no se lanzó.

Impacto: productos vendidos sueltos pueden entrar en la misma factura y base de detracción como si se hubieran instalado. Corregir la vinculación y asignación de cantidades; no basta que coincida el SKU. Esta reproducción es del calculador, no una emisión real a SUNAT ni una explotación demostrada desde la pantalla.

Evidencia reproducible conservada en `storage/app/auditoria-independiente/BruceFireDetraccionReviewTest.php`. Ejecutarla con Pest desde la raíz usa el entorno de pruebas y actualmente debe fallar en los dos casos.

### H-2 · Media: el navegador no contempla el consumo que acepta el servidor

`resources/js/pages/vendedor/ventas/nueva.tsx:375-384` considera suelto todo producto sin `deficiency_id`, mientras H-1 contempla consumos físicos de órdenes de esa venta. El submit bloquea antes del POST en esa condición.

Una línea respaldada por un consumo válido, pero sin `deficiency_id`, resulta aceptable para el servidor e inemitible desde ese formulario. Evidencia por lectura de ambas condiciones y pruebas del calculador; no se completó una reproducción en navegador de un borrador con ese estado histórico. Unificar el criterio después de resolver H-1, sin convertir toda coincidencia de producto en un adicional instalado.

### H-3 · Media: contraste WCAG insuficiente en Deficiencias del vendedor

Axe detectó cinco nodos de `color-contrast`: badge Resuelta 3,24:1; badge secundario 4,32:1; texto Orden de Servicio 3,48:1; autorización y nombre del autorizante 3,49:1. Por tamaño necesitan 4,5:1. Evidencia con selectores, HTML y colores en `storage/app/auditoria-independiente/axe.json`.

No se detectó `button-name` ni `link-name` en estas cinco pantallas. Un enlace de foto parecía vacío al contar solo textContent, pero axe no lo marcó: no se reporta como enlace sin nombre. No se certifica WCAG completo: falta recorrer teclado, estados de todos los modales, pantallas con otros datos y revisión manual.

### H-4 · Baja: Cajas del gerente genera error de hidratación

En `/bruce-fire/gerente/cajas`, React detecta HTML del servidor `-S/ 0.00` frente a cliente `S/ 0.00`, y regenera el árbol. La página sigue funcionando, pero hay un error real en consola. Normalizar cero negativo y el formato entre SSR y navegador. Evidencia completa en `pantallas.json`; revisar `resources/js/pages/gerente/cajas/index.tsx` y el cálculo de faltantes.

### H-5 · Baja: el mismo aviso fiscal aparece tres veces

Reproducción real: factura, cliente con RUC, servicio S/800 y válvula suelta S/15; pulsar Emitir factura. Permanece en venta nueva y el texto «Este producto no es parte del servicio» aparece tres veces dentro de main. No se creó esa factura. Evidencia `aviso-detraccion.json`. Conservar una explicación y la indicación del campo relevante.

### H-6 · Baja: aviso de error anterior todavía visible inmediatamente después del éxito

En Productos se intentó crear con código ya existente VAL-DESC; se corrigió a AUD-CODEX-20261009 y se creó correctamente. Al abrir nuevamente el modal, los errores inline ya no estaban y U.M. era NIU, pero el toast anterior de código duplicado seguía visible inmediatamente. No reproduce un error de U.M. persistente dentro del formulario ni demuestra que el toast no expire. Evitar mensajes contradictorios simultáneos; no confundirlo con un fallo de guardado. Evidencia `producto-validacion.json`.

## Flujo real: del hallazgo al cobro

Se creó por interfaz COT-0001 para el cliente de prueba existente; desde Orden de taller se creó OT-2026-0004 sin venta ni cobro. El técnico tomó la orden y registró BF-EQ-000006, fabricante AUD-CODEX-20261009-01, PQS ABC, 6 kg, marca AUD CODEX, año 2026.

Se guardó checklist con válvula observada y foto sintética identificada como prueba. El vendedor vio la falla, el equipo y su foto, cotizó una válvula a S/15 en la misma cotización y registró autorizante, canal y fecha. El técnico consumió una válvula y la deficiencia quedó resuelta. Se convirtió la cotización a NV-0001 / VTA-0003, venta ID 5: servicio S/45 + adicional S/15 = S/60 y pago efectivo de S/60.

Contraste en base: `service_orders.id=4` enlaza quote_id 1 y sale_id 5; la línea de producto tiene deficiency_id 2; solo hay una salida_servicio de -1 para ese hallazgo; saldo de válvulas 9. No hubo una segunda salida de inventario por cobrar el adicional. Esto descarta que el flujo nuevo, correctamente cotizado y autorizado, no tenga dónde cobrar el adicional.

**Límite de este flujo:** se confirmó la recepción sin marcar la llegada del nuevo equipo; el pivot quedó recibido=0. El cierre técnico se bloqueó por ese dato. La venta se confirmó antes de terminar el taller y no generó certificados de ese trabajo en esta prueba. No se presenta como recorrido completo hasta certificado/entrega ni como defecto confirmado del cierre. El test conserva esa situación para inspección; no se alteró directamente la base para forzar un resultado.

No se emitió un nuevo comprobante electrónico a SUNAT en este flujo. Los comprobantes beta preexistentes no equivalen a una nueva prueba de red realizada por Codex.

## Estado actual y afirmaciones antiguas

| Afirmación del otro informe                                            | Resultado de esta revisión                                                                                                                                                                                         |
| ---------------------------------------------------------------------- | ------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------ |
| No corre el scheduler / sin respaldos desde 27/09                      | Desactualizada: schedule:work y workers de cola presentes; latido 09/10 17:29, programadorActivo=true; respaldo 09/10 14:09, respaldo al día. No se verificó restauración ni ejecución nocturna con la PC apagada. |
| No hay dónde cotizar/cobrar adicional                                  | El flujo nuevo sí lo permite; demostrado arriba. El adicional histórico S/35 de OT-0001 sigue sin venta enlazada y requiere tratamiento de datos anteriores, sin cobro automático retroactivo.                     |
| Planta no permite fotos                                                | Desactualizada: se adjuntó foto al checklist observado y se guardó.                                                                                                                                                |
| Condición siempre guarda otro                                          | El caso nuevo Fuga se normalizó a con_fuga y conservó el texto observado. No se auditaron todos los valores del catálogo.                                                                                          |
| No hay mensaje sin segundo almacén                                     | Desactualizada: el mensaje está y Preparar traslado se bloquea.                                                                                                                                                    |
| sales.medio_pago NULL implica pérdida del pago                         | En la venta nueva es NULL pero sale_payments contiene efectivo S/60. La forma de pago se conserva en su registro; NULL aislado no prueba un fallo.                                                                 |
| Siempre deben salir dos certificados al vender                         | Operatividad automática para unidades nuevas; capacitación y P.H. requieren datos reales. Las regresiones actuales validan esa separación. No afirmar una P.H. que no se hizo.                                     |
| Serie física y código interno                                          | Pruebas actuales verifican dos identificadores y su snapshot; el equipo nuevo también conserva ambos. Nueve escaneos y su PDF no se repitieron en navegador en esta sesión.                                        |
| Orden anulada ejecutable / servicio incompatible / certificados ajenos | Hay regresiones actuales que pasan. No se repitieron todas las manipulaciones HTTP con IDs ajenos en desarrollo; no se mantienen como fallos confirmados sin otra reproducción.                                    |

La cuenta del Banco de la Nación existe según el dueño, pero **no está configurada en la aplicación**. Las credenciales GRE OAuth no están presentes. El único servicio del catálogo actual, Recarga y mantenimiento PQS 6 kg, no tiene código de detracción por su clasificación combinada pendiente. Son limitaciones reales para emitir esos casos; no inventar cuenta, credenciales ni asignar 020/022/037 automáticamente. No se validó una GRE contra la red ni un despacho real: las pruebas de CDR usan fixtures.

## Evidencia y datos de prueba

La carpeta local ignorada `storage/app/auditoria-independiente/` contiene scripts, capturas y resultados JSON/texto. Contiene datos de prueba y no debe publicarse como un paquete de producción. Los archivos fuente no cambiaron.

Datos nuevos de esta auditoría: COT-0001 ID 1; OT-2026-0004 ID 4; equipo ID 7 BF-EQ-000006; checklist ID 2; deficiencia ID 2; autorización AUD-CODEX Cliente Prueba; venta ID 5 VTA-0003/NV-0001; producto AUD-CODEX-20261009 sin stock. La foto es sintética. El producto VAL-DESC, sus diez unidades iniciales y el cliente con RUC ya existían: no atribuir su creación a esta sesión.

## Siguiente corrección y prompt para volver a auditar

Prioridad: H-1, coherencia H-2, configuración fiscal real, contraste H-3, hidratación H-4 y duplicaciones H-5/H-6. No editar la regla de P.H. de cinco años, inventar datos fiscales, agregar dependencias ni alterar comprobantes históricos para ocultar fallos.

> Lee docs/ai/PROYECTO.md, PLAN.md, la última BITACORA.md y .ai/rules/. Revisa AUDITORIA_CODEX_2026_10_09.md contra el HEAD actual. Reproduce H-1 con consumo de una unidad frente a diez vendidas y con dos líneas del mismo producto; exige que solo cantidades vinculadas al servicio integren su factura. Prueba H-2 desde un borrador con consumo real y sin deficiency_id. Recorre por interfaz cotización → orden → alta/escaneo → marcar recibido → checklist/foto → cotizar adicional → autorización → consumo → terminar taller → cobrar → certificado/PDF/QR y contraste SQL. Verifica una sola salida de stock, cobro íntegro sin duplicarlo, identificadores independientes y certificado del trabajo adecuado. Revisa cinco roles, accesibilidad con axe y teclado, GRE solo beta y únicamente con credenciales legítimas, CDR antes de despachar, caja ciega y detracción fuera de caja. Diferencia pruebas de red, mocks y lectura de código. No agregues dependencias, no hagas commit/push y no marques fallos antiguos como vigentes sin reproducirlos. Ejecuta checks focalizados y composer ci:check al final; entrega evidencia y límites explícitos.
