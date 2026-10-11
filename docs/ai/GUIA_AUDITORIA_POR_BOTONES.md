# Guía para auditar BRUCE FIRE por botones

Para quien audite (GPT, Claude u otra persona): se audita **navegando con clics**, como lo haría el usuario. Entrar por URL escondió errores reales (una ficha que nadie enlazaba, un marco duplicado). Escribir una URL solo vale para confirmar que un acceso ajeno da 403 o 404.

## Criterio

El sistema existe para **automatizar y acortar procesos y corregir errores**. En cada flujo mide: cuántos clics, qué se repite, qué dato se vuelve a escribir, qué paso se podría hacer solo. Un proceso más largo que el anterior es un hallazgo.

## Poner el entorno en marcha

1. MySQL de Laragon encendido (base `bruce_fire`).
2. `php artisan serve --port=8010`, `npm run dev` y `php artisan schedule:work` (o `composer run dev`, que arranca los tres). Sin el programador, el Gerente ve «las tareas automáticas no están corriendo».
3. Datos de demostración: `php artisan db:seed --class=DemoAuditoriaSeeder` (idempotente; nunca corre en producción). Deja cada flujo en varios estados.
4. SUNAT solo en modo beta: no aprobar notas ni emitir comprobantes si no se quiere salir a la red.

## Cuentas (contraseña `password`)

| Rol               | Correo                      | Entra en                             |
| ----------------- | --------------------------- | ------------------------------------ |
| Gerente           | gerente@brucefire.pe        | /bruce-fire/gerente/dashboard        |
| Vendedor          | vendedor@brucefire.pe       | /bruce-fire/vendedor/dashboard       |
| Almacén           | almacen@brucefire.pe        | /bruce-fire/almacen/dashboard        |
| Técnico de planta | tecnico.planta@brucefire.pe | /bruce-fire/tecnico-planta/dashboard |
| Técnico de campo  | tecnico.campo@brucefire.pe  | /bruce-fire/tecnico-campo/dashboard  |

## Qué hay para recorrer

| Flujo             | Dónde empezar                                                         | Estados de demostración                                                                              |
| ----------------- | --------------------------------------------------------------------- | ---------------------------------------------------------------------------------------------------- |
| Cotizaciones      | Vendedor → Cotizaciones                                               | borrador, enviada, aceptada, convertida                                                              |
| Órdenes de planta | Planta → Recepción                                                    | por recibir, recibida, esperando autorización, autorizada, en proceso, terminada, lista para entrega |
| Órdenes de campo  | Campo → Recojos, Inspecciones, Instalaciones, Mantenimiento, Entregas | una por cola; una lista para entrega con acta                                                        |
| Deficiencias      | Planta → Deficiencias; Vendedor → orden                               | una esperando autorización                                                                           |
| Ventas y cobro    | Vendedor → Ventas, Caja; Gerente → Cobranzas                          | venta a crédito con cuota vencida y otra por vencer; borrador                                        |
| Comprobantes      | Vendedor → Comprobantes SUNAT                                         | aceptados, pendiente, observado, rechazado, nota de crédito                                          |
| Alertas           | Vendedor → Por vencer                                                 | extintores que vencen en 5 y 20 días                                                                 |
| Traslados         | Almacén → Traslados                                                   | uno pendiente de guía                                                                                |
| Guías de remisión | Cualquier rol con acceso → Guías                                      | crearlas desde venta, orden o traslado                                                               |

## Cómo registrar un hallazgo

Rol, pantalla, botón pulsado, qué esperabas y qué pasó, y el tamaño en clics del proceso. Distingue **error** (algo se rompe o miente), **fricción** (funciona pero sobra trabajo) y **automatización posible**. No declares «todos los botones funcionan» por pasar las pruebas de código: anota qué recorriste y qué no.

## Flujos y casos para la auditoría total

Cada flujo se recorre con clics. Casos: **OK** (camino normal), **ERR** (entrada inválida o regla de negocio que debe bloquear con mensaje claro), **PERM** (otro rol o sede ajena no entra), **BORDE** (límite o estado raro). Anota también cuántos clics toma.

### 1. Venta y comprobante (Vendedor)

- Nueva venta: buscar cliente → producto → emitir. OK: DNI genera boleta, RUC factura, nota de venta sin SUNAT; contado y crédito; efectivo, Yape, Plin, transferencia. ERR: cliente sin documento válido, extintor sin serie, stock cero, boleta mayor a S/ 700 a «clientes varios», crédito sin cuotas. BORDE: venta con extintor y servicio con detracción (más de S/ 700), venta con adicional cotizado, doble clic en emitir.
- Cotización → venta: «Pasar a venta» precarga líneas y precio; falta elegir la serie. ERR: cotización vencida o anulada. BORDE: editar la cotización después de enviada.
- Edición de una venta antes de que SUNAT la acepte; anulación de un borrador.
- Comprobantes: aceptado, pendiente, observado, rechazado (reenvío). BORDE: comprobante sin CDR.

### 2. Notas de crédito y débito

- Pedir (Vendedor) y aprobar o rechazar (Gerente). Motivos: 01 anulación, 02 error de RUC y 06 devolución total (saldo completo); 03 corrección de descripción, 05 descuento por ítem (no en boleta) y 07 devolución por ítem (se eligen líneas); 09 y 10 por importe. ERR: importe mayor al saldo, motivo 13 (no disponible), nota sobre comprobante no aceptado. BORDE: acumulado de varias notas igual al total; anulación con o sin «recibí los equipos» (stock); cobro ya hecho (devolución a caja).
- Reporte: ventas netas = originales − NC + ND del período.

### 3. Cobranzas y caja

- Cuotas: registrar pago (efectivo, otros), pago parcial, anular cobro, detracción acreditada. BORDE: cuota vencida, pago que supera el saldo.
- Caja: abrir turno (propone lo contado en el último cierre), cerrar con arqueo ciego (faltante y sobrante), aviso de turno de más de 24 horas. PERM: el Gerente ve cajas de todos, el vendedor solo la suya.

### 4. Taller (Técnico de planta)

- Recepción: confirmar (toma la orden), faltantes y sobrantes, alta rápida de equipo. Checklist por extintor: «marcar todo conforme», observado (genera deficiencia), CO2 sin manómetro. Cierre: «terminar trabajo y emitir certificados» en un paso, prueba hidrostática por extintor.
- ERR: cerrar sin checklist de todos los recibidos, con deficiencias abiertas, orden anulada. BORDE: equipo de año ilegible (certificado con año vacío), un extintor rechazado dentro de la orden, dos técnicos tomando la misma orden.

### 5. Deficiencias y adicionales

- Detectar (técnico) → cotizar adicional (Vendedor) → aprobar o rechazar con persona y canal → la orden pasa a «autorizado» → consumir el repuesto una sola vez → resolver. ERR: aprobar sin cotización, importe distinto, repuesto sin stock. PERM: un técnico no autoriza.

### 6. Campo (Técnico de campo)

- Recojo con firma (pasa a Planta), inspección, instalación (certificado automático), mantenimiento en sitio (checklist, fotos antes y después, firma y acta), entrega con acta y cierre. ERR: sin conformidad o sin firma, sin foto, sin escanear las unidades vendidas. BORDE: orden entregada ya no se ofrece para tomar; el técnico que registra el trabajo se queda con la orden.

### 7. Almacén

- Recepción de compra con series y costo, stickers, ajuste de stock con motivo, consulta rápida. Traslado: preparar → emitir guía → enviar a SUNAT → despachar con CDR → confirmar llegada. ERR: despachar sin CDR, destino sin almacén, serie duplicada.

### 8. Guías de remisión

- Desde venta, orden o traslado: datos precargados, vehículo y conductor únicos preseleccionados, historial con paginación, detalle, XML y CDR, editar transporte solo en borrador. ERR: guía rechazada (reenvío), borrador sin transporte. PERM: cada rol ve solo las de su sede.

### 9. Gerente

- Usuarios (roles con tilde, contraseña generada), sedes y transporte, productos (código sugerido) y servicios, cobranzas y cajas consolidadas, auditoría, reportes comerciales e inventario, firmas y configuración de la empresa. PERM: ningún otro rol entra a estas pantallas.

### 10. Transversales

- Ficha del equipo BF-EQ (solo órdenes visibles para el rol), alertas «Por vencer» y «Ofrecer recarga» (necesita servicio de ese agente y capacidad), conversación y evidencias por orden, aislamiento por sede en todo listado, móvil, tema oscuro y accesibilidad (teclado, foco, contraste).

### Aún sin ejecutar

- Comprobado contra SUNAT beta: boleta, factura, nota de crédito (por importe y anulación total), baja y su efecto en stock y caja.
- Guías de remisión: no hay beta público de la API de guías. En la PC de desarrollo se usa una guía **simulada** (`SUNAT_GRE_SIMULADO=true` en `.env`): acepta sin salir a SUNAT y deja el mensaje «SIMULADO». Con ella se recorre traslado → enviar guía → despachar → confirmar llegada. Nunca se activa en producción. Validar de verdad requiere `SUNAT_GRE_CLIENT_ID` y `SUNAT_GRE_CLIENT_SECRET` (credenciales del dueño).
- Para recibir un traslado hace falta un usuario de Almacén de la sede destino (el demo trae «Almacén Chimbote (demo)»): se crea desde Gerente → Usuarios, y en su primer ingreso debe confirmar y cambiar la contraseña.
