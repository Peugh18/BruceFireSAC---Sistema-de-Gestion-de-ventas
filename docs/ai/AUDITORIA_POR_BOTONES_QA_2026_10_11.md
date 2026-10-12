# Auditoría por botones — QA, 11/10/2026

**En curso. No constituye una auditoría completa ni certificación WCAG.** Solo se incluyen observaciones de este recorrido. Las auditorías anteriores no se heredan como cobertura.

## Entorno y alcance

- El dueño confirmó que la base es una copia desechable y autorizó operaciones exclusivamente demo.
- Código fuente sin editar; sin commit ni seeder. Laravel en 8010, MySQL activo.
- La entrada inicial por URL fue expresamente autorizada. Navegación de negocio por controles visibles; login por formulario.
- Tras reiniciar, Vite 5173 aceptaba conexiones TCP pero su cliente JS no respondió en 8 segundos; registro en calentamiento SSR. Se detuvo exclusivamente el Vite iniciado en esta sesión y se retiró su marcador temporal `public/hot` (copia conservada fuera del repositorio). El login funcionó con la compilación existente del 11/10 a las 19:41:28. No se conoce aún la causa interna del bloqueo de Vite.
- SUNAT beta y GRE simulada observados en la configuración local. Ningún envío fiscal realizado en este recorrido.
- El programador no se inició; el aviso correspondiente del Gerente se considera una condición del entorno, no un error funcional.

## A. Pasos observados

Se cuentan clics sobre botones, enlaces y pestañas; la edición de campos se registra aparte. No se incluye el foco de campos en el conteo. Un tramo de auditoría no equivale a un flujo comercial completo.

| Rol / tramo | Pasos reales | Clics de acción | Datos escritos otra vez |
| --- | --- | ---: | --- |
| Acceso ERR | Iniciar sesión vacío → «Completa este campo», foco en correo | 1 | Ninguno |
| Gerente, acceso OK | Correo → contraseña → Iniciar sesión → Cargando deshabilitado → panel | 1 de acción, más 2 de foco | Correo y contraseña de la cuenta demo |
| Gerente, configuración | Configuración → elegir #1F4E79 → Guardar cambios → vista previa de factura, dos intentos → Firmas y sellos | 6 | Mensaje de agradecimiento introducido por primera vez |
| Gerente, usuario ERR | Usuarios y Roles → Nuevo usuario → Crear usuario vacío → alertas nombre/correo/contraseña → Escape → Matriz de Permisos | 4, más Escape | Ninguno |
| Gerente, sede ERR | Sedes → Nueva Sede → Crear sede vacío → «Completa este campo», foco en Nombre → Cancelar | 4 | Ninguno |
| Gerente, stock BORDE | Productos → Solo bajo mínimo → filas PQS stock 4/mínimo 5 y válvula stock 5/mínimo 5 | 2 | Ninguno |
| Gerente, producto ERR/OK | Nuevo Producto → precio −1 → Registrar producto bloqueado → precio 11.80 y mínimo 2 → Registrar producto → «Producto creado exitosamente» | 3 desde la lista | Se corrigió el precio inválido; código PRD-0020 propuesto automáticamente |
| Gerente, cartera | Cobranzas → Ver pagos cuota 2 → vacío con saldo 100 → Cerrar → Ver pagos cuota 1 → abono 100/saldo 0 → Anular pago → motivo vacío/Confirmar deshabilitado → Cancelar → Cerrar → Dashboard | 8 | Ninguno; anulación no confirmada |

## B. Hallazgos confirmados

| Rol | Pantalla | Botón pulsado | Qué esperaba | Qué pasó | Tipo | Gravedad | Evidencia |
| --- | --- | --- | --- | --- | --- | --- | --- |
| Gerente | Dashboard / Cobranzas | Cobranzas; luego Dashboard | Mismo monto mensual si ambas tarjetas describen los abonos del mes | Panel 450; cartera 505. La diferencia persiste al volver al panel sin modificar cobros | ERROR | Media | «Monto cobrado — S/ 450.00 — Abonos ingresados este mes» frente a «Cobrado en el Mes — S/ 505.00 — Abonos ingresados en el mes» |
| Gerente | Productos | Solo bajo mínimo | Etiqueta que describa con precisión el conjunto filtrado | Incluye un producto exactamente en el mínimo | ERROR | Baja | VAL-DESC: stock 5, mínimo 5. El panel sí dice «en el mínimo o por debajo» |
| Gerente | Nuevo trabajador | Crear usuario vacío | Error asociado al campo para volver a localizarlo con lector de pantalla | Se muestra `alert`, pero Nombre completo no tiene `aria-invalid` ni `aria-describedby` | FRICCIÓN | Media | Inspección DOM del campo tras validar: ambos atributos `null`. No se ejecutó lector de pantalla; no se afirma incumplimiento WCAG completo |
| Gerente | Encabezado del panel | Notificaciones | Abrir notificaciones o indicar que no hay | No aparece menú, diálogo ni mensaje tras el clic y observación posterior | ERROR | Baja | El botón queda enfocado; el DOM sigue sin contenido de notificaciones |
| Gerente | Auditoría, 375 px | Aplicar Filtros (ubicación del control) | Botón visible completo y libre de superposiciones | Chispa cubre sus últimos 45 px, en claro y oscuro | FRICCIÓN | Media | Botón x=209.48/y=760/ancho=126.52/alto=28; Chispa x=291/y=728/ancho=64/alto=64 |
| Gerente | Nuevo Servicio | Nuevo Servicio → Crear Servicio vacío | Reducir introducción manual de códigos como en Productos | Código obligatorio vacío; requiere introducirlo manualmente | AUTOMATIZABLE | Baja | «Código *», sin precarga; «Completa este campo» recibe foco en Código |
| Gerente | PDF comercial | Exportar PDF | Fórmula legible originales − NC + ND | El signo de resta se representa con `?` | ERROR | Baja | PDF exportado 20:34, primera tarjeta: «ORIGINALES S/ 375.00 ? NC 40.00 + ND 0.00»; extracción y render visual coinciden |
| Almacén | Nueva recepción | Agregar Ítem; cambiar Cant. Recibida | Reducir la repetición cuando toda la mercadería está conforme | Recibida 10 deja Conforme 1: exige volver a ajustar la cantidad en otro campo | FRICCIÓN | Media | «9 Observado» y campo obligatorio de motivo aparecen mientras Conforme conserva 1. No hay acción visible «todo conforme» |
| Almacén | Recepción guardada / edición | Confirmar Recepción; Editar Recepción | Poder revisar el costo que se introdujo | No se muestra el costo ni se ofrece corregirlo en la ficha o edición | FRICCIÓN | Media | Costo de compra introducido 6.00; detalle y edición muestran cantidades, proveedor y referencia, sin campo de costo. Valorización todavía pendiente de conciliar |
| Almacén Central | Consulta Rápida | Consultar AUD-TOTAL-1110-R, sede Central seleccionada | Mostrar solo movimientos de la sede autorizada | Expone un movimiento de Chimbote y su usuario, aunque el stock se limita a Central | ERROR | Alta | «11/10/2026, 04:07 p. m. / traslado / en Almacén Chimbote (demo) / +1 / Almacen Chimbote Demo»; selector solo Central o Todas las sedes |
| Almacén | Nueva recepción serializada | Confirmar Recepción | Bloquear dos series de fabricante iguales dentro de la recepción | Acepta ambas y genera dos unidades distintas | ERROR | Media | Recepción #8: BF-EQ-000012 y BF-EQ-000013, ambas «Serie: QA1110-SER-01» |
| Almacén | Stock y Kardex | Stock y Kardex | Total disponible coherente con las filas o alcance explícito | Tarjeta 5; filas suman 28 en Central | ERROR | Media | «Unidades disponibles / 5 / Total en todos los almacenes»; filas 10, 7, 1, 0, 5, 0, 5 |
| Almacén | Guías de remisión | Enviar a SUNAT | Informar envío simulado de forma coherente | Mensaje general dice enviada a SUNAT y consultar ticket; la guía ya está lista y dice no enviada | ERROR | Baja | «Guía enviada a SUNAT. Consulta el ticket hasta ver el CDR aceptado» junto a «SIMULADO… no se envió a SUNAT» y «Lista para trasladar» |
| Almacén | Ajustes de Stock | Confirmar Ajuste en Kardex, baja de repuesto 1 | Tarjeta de bajas con alcance explícito y coherente | Historial agrega −1 y total pasa de 4 a 5; «Unidades Dadas de Baja» sigue en 1 | ERROR | Media | Nueva fila PRD-0020 −1, 21:28; ya existían AUD-TOTAL-1110-R −1 y BF-EQ-000003 −1. La tarjeta no aclara si solo cuenta unidades serializadas actualmente de baja |
| Almacén | Stickers de Barras | Imprimir serie inexistente | Explicar que no existe esa unidad, conservando corrección de entrada | Abre otra pestaña con error genérico de página/enlace | FRICCIÓN | Media | Error 404: «CHISPA BUSCÓ ESTA PÁGINA Y NO LA ENCONTRÓ» y «Puede que el enlace esté mal escrito o que la página ya no exista» |

## C. Cobertura real y pendientes

Recorridos: login vacío y válido del Gerente; panel; Configuración de empresa, guardado demo; pestaña Firmas y sellos (lectura); Usuarios y matriz, alta vacía y Escape; Sedes y alta vacía/cancelación; Productos, filtro bajo mínimo, alta inválida/válida; Cobranzas y ambos historiales; inicio/cancelación de anular pago.

Cobertura ampliada en las continuaciones de abajo: Gerente (Servicios, Caja, Reportes, Auditoría, Transporte, Guías), Almacén Central y Chimbote (recepciones, stock, Kardex, consulta, traslado y GRE simulada completos), PERM de Usuarios con 403, muestras a 375 px claro/oscuro. El detalle de botones realmente pulsados se conserva por tramo.

Pendientes: vista previa del comprobante tras cambiar configuración; logo personalizado; controles restantes de Gerente y Almacén; roles Vendedor, Técnico de planta y Técnico de campo y sus flujos; otras combinaciones OK/ERR/PERM/BORDE; axe-core (la API disponible de evaluación del navegador es solo lectura y no permite inyectar scripts), contraste medido, lector de pantalla y teclado integral. No se afirma cobertura total ni WCAG AA.

## D. Comprobaciones positivas

- Login vacío identifica el campo y recibe foco; envío válido deshabilita Iniciar sesión durante la carga.
- Errores de alta de usuario en español; Escape permite salir del diálogo.
- Sedes: total 4 y activas 4 concuerdan con las cuatro filas visibles.
- Código de producto sugerido automáticamente; precio negativo bloqueado con foco; crear producto no añade stock.
- Tras el alta: total catálogo 9 y activos 7, incrementos coherentes desde 8 y 6; stock del nuevo producto 0, mínimo 2.
- Cobranzas: deuda 100 y vencida 0 concuerdan con las dos cuotas; historiales distinguen vacío y pagado. Confirmar anulación está deshabilitado sin motivo.

## Registros demo modificados en esta sesión

- Producto PRD-0020, «AUD QA1110 Repuesto», precio 11.80, mínimo 2, stock inicial 0.
- Configuración temporal: color #1F4E79 y mensaje «DEMO AUDITORÍA 1110 — Gracias por su preferencia». **Restauración pendiente**: color previo #D2232A; mensaje previo vacío. Ninguna firma, cuenta, sede o pago modificado.
- Transporte temporal: AUD111 y conductor AUD TOTAL 1110 PRUEBA (DNI 80001110) desactivados para dejar un solo vehículo/conductor activos y comprobar la precarga de guías. Ambos muestran «Activar» para revertir. **Restauración pendiente**.
- Recepción #7: proveedor AUD QA1110 Proveedor, referencia QA-1110-01-CORR, PRD-0020, 10 recibidas/10 conformes/0 observadas, costo introducido 6.00, Almacén Central Trujillo. Stock y costo promedio pendientes de conciliar desde otra pantalla.

## Continuación del Gerente

- Servicios: 7 totales/7 activos y 7 filas; alta vacía bloquea en Código, Cancelar cierra. No se creó servicio.
- Caja: 3 aperturas hoy, 1 abierta, 3 cierres con diferencias; faltantes 55 + 295 = 350 y sobrante 50 separado. Checkbox de diferencias → Aplicar Filtros deja las tres filas cerradas y excluye la abierta (2 clics).
- Reportes: Comercial → Inventario → Comercial; inventario 7 productos activos, 18 unidades, valor 380 = 80 + 260 + 40; avisa del único producto con stock sin costo. PDF comercial exportado por botón y encontrado en Descargas: `D:/TiomiguelonGgs/Downloads/reporte-comercial-2026-10-11 (3).pdf`, 1 página, emitido 20:34. Contenido y cifras concuerdan con el reporte del 1 al 11. Primera tarjeta tiene el signo defectuoso descrito arriba.
- Tras editar la fecha también con teclado y pulsar Filtrar, se confirmó URL con período 1–31 de octubre y ventas netas 335. El panel usa 350 y dice incluir notas «aceptadas u observadas»; Reportes especifica «aceptadas». **No se ha conciliado el origen de la diferencia y no se declara error aritmético de ese par**.
- Auditoría: lectura de primera página (20 eventos), total 71/hoy 48; cambio de Configuración registrado con sus valores. El producto creado posteriormente no aparece como evento reciente y el filtro no ofrece una acción de producto creado. Falta investigar alcance del registro; no se presume causa de backend.
- Auditoría a 375 × 812 en claro/oscuro: superposición documentada; no se ha certificado accesibilidad. Tema devuelto a claro.
- Menú móvil → Transporte (2 clics): alta de vehículo vacía rechaza con «El campo placa es obligatorio»; conductor vacío rechaza DNI, licencia, nombres y apellidos. Desactivar AUD111 y desactivar conductor AUD TOTAL son 2 clics, resultados inactivos y acción Activar visible.
- Menú móvil → Guías (2 clics), navegación iniciada; pendiente registrar resultado.
- Resultado Guías: tres registros, paginación 1–3 de 3, ambos botones de página deshabilitados. Detalles T001-3, T001-1 y T001-2 abiertos por enlace, con vuelta al historial entre ellos. Todos dicen SIMULADO; venta VTA-0004/B001-3 usa transporte público, traslados 1 y 2 privado. Guías aceptadas sin controles de edición. XML y CDR de T001-3 pulsados (2 clics), sin archivo localizado en Descargas: contenido pendiente, no se presume fallo del servidor. Muestra del detalle T001-1 a 375 px en oscuro; tema devuelto a claro.
- Gerente cerrado por Abrir menú → Cerrar sesión (2 clics); formulario visible. Login de Almacén iniciado mediante correo, contraseña y botón.

**Corrección de cobertura:** la exportación de Reportes sí se descargó y fue inspeccionada. La vista previa del comprobante tras cambiar configuración continúa sin verificar: el archivo homónimo encontrado es anterior a la prueba.

## Inicio de Almacén

### Recepción serializada y conciliación posterior

- Baja válida agrega PRD-0020 −1 con motivo y usuario; total ajustes 4→5. Nuevo Ajuste → Ingreso (+) → introducir motivo compensatorio → Confirmar (3 clics de acción): agrega +1, total 5→6. No se borra historial; saldo neto de ambas pruebas 0. Stock final pendiente de lectura. Tarjeta de bajas ambigua descrita en tabla.

- Regreso por formulario a Almacén Central con viewport 1440×900. Ajustes de Stock → Nuevo Ajuste → motivo «corto» → Confirmar (3 acciones incluyendo navegación, 1 foco): mensaje «El motivo debe tener al menos 10 caracteres explicativos para auditoría». Total se mantiene en 4. Producto PRD-0020 y Central están precargados, cantidad 1, Baja seleccionada. Motivo corregido «DEMO QA1110 ajuste compensable» → Confirmar (1 acción) iniciado para probar baja y posterior reposición compensatoria demo.

- **Bloqueo de llegada:** el login de `almacen.chimbote@brucefire.pe` con la clave `password` que confirmó el usuario devuelve «Estas credenciales no coinciden con nuestros registros». No se cambió ni restableció contraseña. Traslado #4 permanece en tránsito; confirmación de llegada y stock destino NO probados. Se solicitó la clave demo vigente y se continúa con trabajo independiente.
- A petición expresa del usuario «Busca la clave pues», se buscó el acceso de Chimbote en documentación y registro local de la auditoría anterior del mismo proyecto. Allí consta un inicio correcto con su credencial vigente. Se usa ese valor existente exclusivamente en el formulario, sin resetear acceso; la contraseña no se reproduce en este informe. Segundo intento iniciado, resultado pendiente.
- **Bloqueo resuelto:** ese segundo login sí abre el Dashboard de Chimbote. PRD-0020 muestra 0 antes de la llegada; solo aparecen movimientos propios en Dashboard. Menú → Traslados (2 clics): la operación Central → Chimbote ofrece Confirmar llegada, sin Despachar. Confirmar llegada (1 clic) iniciado; verificar stock posterior pendiente.
- **Llegada completada:** Confirmar llegada retira la operación de la cola. Menú → Stock y Kardex (2 clics): PRD-0020 Chimbote **2**. Historial de Kardex (1 clic): un movimiento «+2 NIU / Almacen Chimbote Demo / Llegada confirmada del traslado entre sedes», 21:25. Origen verificado al despachar 8; destino antes 0/después 2, conserva las 10 recibidas entre ambas sedes. No se probó doble envío simultáneo; el botón ya no se ofrece tras recibir. Captura guardada `C:/Users/migue/.codex/visualizations/2026/10/12/01a12d97-114e-76e3-9442-4e32a43eeda2/chimbote-llegada-traslado4.jpg`. Menú → Cerrar sesión (2 clics) para continuar.

- Enviar a SUNAT (1 clic) sobre T001-4 devuelve «Lista para trasladar» y aviso SIMULADO/no enviada/no válida. Mensaje superior contradictorio registrado. Menú → Traslados (2 clics): Despachar habilitado. Despachar (1 clic) deja En tránsito y retira ese botón. Menú → Stock y Kardex (2 clics): PRD-0020 Central **8**, correcto desde 10 menos 2. Menú → Cerrar sesión (2 clics) vuelve al formulario. Login de Chimbote mediante correo/clave proporcionada por el usuario y botón (3 clics), resultado pendiente.

- Corregir cantidad a 2 y añadir observación demo → Preparar traslado (1 clic de acción, 1 foco adicional en observación): crea traslado #4 y abre Nueva guía. Motivo 04, fecha, destinatario RUC/razón social, direcciones, producto y cantidad precargados; único vehículo DEM-123 y único conductor activo elegidos automáticamente. Peso bruto vacío; intento Crear guía sin enviar (1 clic) devuelve «El campo peso bruto es obligatorio».
- Peso de línea 0, solo lectura confirmado mediante atributo readOnly; no se cambió. Introducir peso bruto total demo 1 kg y Crear guía sin enviar (1 foco + 1 acción): T001-4 queda Sin enviar. No se declara que 1 kg sea un peso físico medido.
- Abrir T001-4 → enlace Traslado #4 (2 clics) vuelve a Traslados; aparece pendiente origen Central → Chimbote, 2 bienes. **ERR preventivo positivo:** Despachar está deshabilitado mientras «guía pendiente de SUNAT»; no se forzó el control ni se hizo llamada directa. Enviar / consultar GRE (1 clic) abre historial para continuar.

- Filtro de Kardex PRD-0020 confirmó exactamente un movimiento +10 (1 clic Filtrar + interacción de selector). Limpiar → Abrir menú → Consulta Rápida, 3 clics. Campo de búsqueda recibe foco inicialmente.
- Consultar BF-EQ-000012 (1 clic): disponible en Central, marca QA DEMO/año 2026 e ingreso de recepción #8 concuerdan. Consultar AUD-TOTAL-1110-R (1 clic): stock Central 7, pero Kardex incluye Chimbote: hallazgo de aislamiento documentado en tabla. Consultar QA-NO-EXISTE-1110 (1 clic): «No existe ninguna unidad física ni producto que coincida…». Limpiar (1 clic) limpia resultado y deja selector en Todas las sedes; no se ha probado lector físico.
- Menú → Traslados, 2 clics. Preparar vacío (1 clic) bloquea con foco en Destino: «Selecciona un elemento de la lista». Elegir Chimbote y PRD-0020, cantidad 11 para stock 10 → Preparar (1 clic): alerta «Stock insuficiente para preparar el traslado». Cantidad y selecciones se conservan. El formulario no muestra stock disponible junto al producto, obliga a recordarlo desde otra pantalla (FRICCIÓN, baja).

- Recepción #8: Nueva Recepción → Agregar Ítem → seleccionar EXT-PQS-6KG → completar proveedor/referencia/cantidades/costo y metadatos → Copiar primera fila a todas → repetir deliberadamente la serie del fabricante → Confirmar Recepción. 4 clics de acción y 9 de foco = 13 clics registrados; selección nativa de producto se registra aparte. Dos recibidas/conformes, costo 65.00, capacidad 6 kg, marca QA DEMO, año 2026.
- ERR: ambas unidades con serie de fabricante `QA1110-SER-01` fueron aceptadas. Se generaron BF-EQ-000012 y BF-EQ-000013; los identificadores internos sí son distintos. Hallazgo ERROR, gravedad media: no bloqueó la serie de fabricante repetida dentro de la misma recepción.
- Copiar primera fila conserva capacidad, marca y año; deja vacía la serie de la segunda unidad, comportamiento positivo que evita duplicarla involuntariamente.
- Imprimir Stickers (PDF), 1 clic: archivo `D:/TiomiguelonGgs/Downloads/stickers-recepcion-8.pdf`, una página, dos etiquetas con códigos de barras e identificadores BF-EQ-000012/13 legibles. No son QR. Lectura física con escáner no probada. Render: `C:/Users/migue/.codex/visualizations/2026/10/12/01a12d97-114e-76e3-9442-4e32a43eeda2/stickers-recepcion-8.png`.
- Abrir menú → Stock y Kardex, 2 clics: PRD-0020 muestra 10 unidades, sin duplicación al corregir solo la referencia de recepción #7. EXT-PQS-6KG muestra 5 unidades en Central tras recibir las dos nuevas. Costo promedio sigue pendiente de lectura.
- ERROR de etiqueta/cifra, gravedad media: tarjeta «Unidades disponibles: 5 / Total en todos los almacenes», pero las siete filas visibles de la única sede suman 10 + 7 + 1 + 0 + 5 + 0 + 5 = 28. El 5 coincide con unidades serializadas, sin indicarlo en la tarjeta; no representa el stock total de productos y repuestos mostrado debajo.
- Historial de Kardex, 1 clic: dos ingresos separados +1 para BF-EQ-000012 y 13; un ingreso +10 para PRD-0020/recepción #7. Filtro de sede solo ofrece Central y Todas las sedes; no se observó sede ajena en esta primera página. Filtro Producto PRD-0020 + Filtrar iniciado; resultado pendiente de lectura.

- Login por formulario: 3 clics (dos de foco, uno en Iniciar sesión); panel de Almacén visible, solo Central. Unidades serializadas disponibles 3, recepciones hoy 3, tres productos en el mínimo o por debajo. No comparar sus 3 unidades con las 4 globales del Gerente sin separar sedes.
- PERM: única navegación directa autorizada a Usuarios del Gerente → **Error 403**, «ESTA SECCIÓN NO ES DE TU ROL». «Volver a mi panel de Almacén» (1 clic) vuelve al panel. No se modificó acceso.
- Recepción: Registrar nueva recepción → Agregar Ítem → Confirmar vacío bloquea y enfoca Proveedor → completar proveedor/referencia/recibida/conforme/costo → Confirmar. **4 clics de acción incluyendo el intento inválido + 5 clics de foco = 9 clics registrados**. Fecha y sede precargadas; producto PRD-0020 preseleccionado al agregar la línea, entre siete opciones.
- BORDE: introducir 11 conformes para 10 recibidas limita automáticamente el campo a 10. No hubo mensaje de rechazo: se guardó la recepción #7 con 10/10, 0 observadas. No declarar el caso como rechazo de servidor.
- Editar Recepción → cambiar solo referencia a QA-1110-01-CORR → Guardar Corrección → ficha conserva 10/10 → Volver a recepciones (3 acciones + 1 foco). No se reescribieron proveedor ni cantidades. Stock aún no verificado para esta corrección.
