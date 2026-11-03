# Errores del sistema — registro compartido (Claude, GPT/Codex)

> Lo leen y lo actualizan todos los agentes. **Nunca se borra un error**: solo cambia su estado.
> Lo esperado está en [CONTEXTO_SISTEMA.md](CONTEXTO_SISTEMA.md); cómo auditar, en [PROMPT_AUDITORIA.md](PROMPT_AUDITORIA.md).

## Reglas

1. **Número correlativo** (E-001, E-002…). No se reutiliza.
2. **Estados:** `Abierto` → `En curso` → `Solucionado` (o `Descartado`, con el motivo). Al cambiar de estado se agrega una línea con la fecha y quién lo hizo, sin borrar lo anterior.
3. **Gravedad:**
    - **Crítico:** dinero, SUNAT, pérdida o fuga de datos, permisos;
    - **Alto:** bloquea un flujo de trabajo;
    - **Medio:** se puede hacer pero confunde o cuesta;
    - **Bajo:** detalle visual o de texto.
4. **Para pasar a Solucionado:**
    - causa raíz escrita;
    - prueba automática que fallaba y ahora pasa (archivo y nombre);
    - revisión en el navegador si es de pantalla.
5. Si el arreglo cambia una regla del negocio, también se actualiza `CONTEXTO_SISTEMA.md`.
6. Las recomendaciones («otros sistemas lo hacen así») van en la sección **Mejoras propuestas** y no se implementan sin aprobación del usuario.

## Plantilla

```
### E-000 · Título corto
- Rol / pantalla / botón:
- Gravedad:
- Pasos: 1) … 2) …
- Esperado:
- Pasó:
- Causa raíz:
- Prueba:
- Historial:
    - AAAA-MM-DD · Abierto · quién
```

## Resumen

| N.°   | Rol                       | Pantalla                                                            | Gravedad              | Estado      |
| ----- | ------------------------- | ------------------------------------------------------------------- | --------------------- | ----------- |
| E-001 | Gerente                   | Configuración → Certificados → Nuevo                                | Medio                 | Solucionado |
| E-002 | Vendedor                  | Clientes → Agregar cliente                                          | Medio                 | Abierto     |
| E-003 | Vendedor                  | Clientes → Agregar cliente                                          | Bajo                  | Abierto     |
| E-004 | Vendedor                  | Clientes (lista y formulario)                                       | Medio                 | Abierto     |
| E-005 | Vendedor                  | Clientes (lista y formulario)                                       | Bajo                  | Abierto     |
| E-006 | Vendedor                  | Ventas → Nueva venta                                                | Medio                 | Abierto     |
| E-007 | Gerente                   | Certificados → Nuevo (desde un producto)                            | Medio                 | Solucionado |
| E-008 | Todos                     | Certificado PDF y Word                                              | Medio                 | Solucionado |
| E-009 | Vendedor                  | Nueva venta → Emitir factura                                        | Bajo                  | Abierto     |
| E-010 | Vendedor                  | Nueva venta (avisos de stock)                                       | Medio                 | Abierto     |
| E-011 | Vendedor                  | Caja → Cerrar turno (arqueo ciego)                                  | Alto                  | Solucionado |
| E-012 | Vendedor                  | Caja → Cierres recientes                                            | Bajo                  | Abierto     |
| E-013 | Vendedor                  | Cotización → Pasar a venta                                          | Medio                 | Abierto     |
| E-014 | Vendedor                  | Cotización y venta a crédito (formularios)                          | Bajo                  | Abierto     |
| E-015 | Gerente                   | Servicios y Productos → Nuevo                                       | Medio                 | Abierto     |
| E-016 | Gerente / SUNAT           | Sedes → código de establecimiento; facturación                      | Medio (por confirmar) | Abierto     |
| E-017 | Gerente                   | Sedes → Nueva Sede                                                  | Bajo                  | Abierto     |
| E-018 | Gerente                   | Transporte (rutas antiguas)                                         | Bajo                  | Abierto     |
| E-019 | Gerente                   | Auditoría → detalle                                                 | Bajo                  | Abierto     |
| E-020 | Todos                     | Formularios (mensajes de error)                                     | Bajo                  | Abierto     |
| E-021 | Gerente                   | Dashboard → Ventas netas y Margen bruto                             | Alto                  | Abierto     |
| E-022 | Vendedor                  | Órdenes de servicio → Nueva orden                                   | Medio                 | Abierto     |
| E-023 | Vendedor → Planta         | Nueva orden (cantidad de extintores)                                | Alto                  | Abierto     |
| E-024 | Técnico de Planta         | Orden → Recibir → Registrar a mano                                  | Alto                  | Abierto     |
| E-025 | Técnico de Planta         | Cola de taller (nombres de servicio)                                | Bajo                  | Abierto     |
| E-026 | Vendedor                  | Inicio                                                              | Medio                 | Abierto     |
| E-027 | Vendedor ↔ Planta         | Órdenes de servicio (estado)                                        | Medio                 | Abierto     |
| E-028 | Vendedor                  | Deficiencias → Aprobar                                              | Medio                 | Abierto     |
| E-029 | Técnico de Planta         | Orden → Reparar (repuestos)                                         | Medio                 | Abierto     |
| E-030 | Campo                     | Entrega y Acta de Conformidad (datos)                               | Alto                  | Abierto     |
| E-031 | Campo                     | Acta de Conformidad (diseño) e Inicio                               | Bajo                  | Abierto     |
| E-032 | Campo / Vendedor          | Entrega (ejemplo de DNI) y Recojo (texto)                           | Bajo                  | Abierto     |
| E-033 | Almacén                   | Consulta rápida (BF-EQ)                                             | Medio                 | Abierto     |
| E-034 | Todos                     | Celular: botón de Chispa                                            | Medio                 | Abierto     |
| E-035 | Gerente                   | Editor de certificados (vista previa, QR)                           | Bajo                  | Abierto     |
| E-036 | Técnico de Planta         | Orden cerrada                                                       | Bajo                  | Abierto     |
| E-037 | Todos                     | Mi cuenta → Confirmar contraseña                                    | Bajo                  | Abierto     |
| E-038 | Vendedor / Gerente        | Clientes, Recompra e IA («Clientes varios»)                         | Medio                 | Abierto     |
| E-039 | Vendedor                  | Ficha del cliente → Nueva venta                                     | Alto                  | Abierto     |
| E-040 | Vendedor                  | Ventas → Editar (comprobante por enviar)                            | Medio                 | Abierto     |
| E-041 | Vendedor                  | Nota de crédito y Comunicar baja                                    | Bajo                  | Abierto     |
| E-042 | Vendedor / SUNAT          | Editar factura ↔ boleta (numeración)                                | Medio (por confirmar) | Abierto     |
| E-043 | Vendedor                  | Guía de remisión (mensajes y documento)                             | Medio                 | Abierto     |
| E-044 | Vendedor                  | Venta → Anular nota de venta                                        | Alto                  | Abierto     |
| E-045 | Vendedor                  | Cobranzas → Registrar pago                                          | Bajo                  | Abierto     |
| E-046 | Vendedor                  | Cotizaciones (enviar, aceptar, rechazar)                            | Medio                 | Abierto     |
| E-047 | Técnico de Planta         | Orden → Revisar → Extintor no apto                                  | Medio                 | Abierto     |
| E-048 | Vendedor                  | Deficiencias → Cotizar adicional, Aprobar y Rechazar                | Medio                 | Abierto     |
| E-049 | Técnico de Planta         | Orden → Cierre (reparación rechazada por el cliente)                | Alto                  | Abierto     |
| E-050 | Técnico de Planta         | Búsqueda de equipo por serie de fábrica                             | Medio                 | Abierto     |
| E-051 | Vendedor                  | Certificado de producto → Corregir datos                            | Alto                  | Abierto     |
| E-052 | Vendedor / SUNAT          | Nota de débito por pago tardío (IGV)                                | Medio (por confirmar) | Abierto     |
| E-053 | Todos                     | Formularios con errores solo en aviso pasajero                      | Medio                 | Abierto     |
| E-054 | Técnico de Campo ↔ Planta | Mantenimiento en sitio (equipo dado de baja) y checklists distintos | Medio                 | Abierto     |
| E-055 | Técnico de Campo          | Recojo → Confirmar recojo                                           | Alto                  | Abierto     |
| E-056 | Gerente                   | Usuarios → cambiar rol y sede                                       | Medio                 | Abierto     |
| E-057 | Técnico de Campo          | Inspección en sitio (certificado y estado)                          | Alto                  | Abierto     |
| E-058 | Técnico de Campo          | Pantallas de entrega, mantenimiento e inspección                    | Bajo                  | Abierto     |
| E-059 | Gerente                   | Usuarios → Matriz de permisos                                       | Bajo                  | Abierto     |
| E-060 | Vendedor                  | Orden → Entrega en mostrador                                        | Medio                 | Abierto     |
| E-061 | Gerente                   | Caja consolidada (fechas de apertura y cierre)                      | Bajo                  | Abierto     |
| E-062 | Técnico de Campo/Vendedor | Mantenimiento e inspección en sitio → estado final                  | Medio                 | Abierto     |
| E-063 | Vendedor, Gerente         | Botones verdes con texto blanco (6 pantallas)                       | Medio                 | Abierto     |
| E-064 | Técnico de Campo          | Entregas, Inspecciones, Instalaciones → pestañas de filtro          | Bajo                  | Abierto     |
| E-065 | Vendedor                  | Deficiencias (tema oscuro) → etiqueta roja                          | Bajo                  | Abierto     |
| E-066 | Vendedor                  | Detalle de la orden en celular (cabecera)                           | Medio                 | Abierto     |
| E-067 | Vendedor                  | Nueva venta → escanear serie (lector de barras)                     | Medio                 | Abierto     |
| E-068 | Vendedor                  | Venta → Armar certificados                                          | Medio                 | Abierto     |
| E-069 | Todos                     | Certificado de operatividad de extintores nuevos (PDF y Word)       | Medio                 | Abierto     |
| E-070 | Técnico de Campo          | Mediciones del certificado (columnas con mínimo)                    | Medio                 | Abierto     |
| E-071 | Técnico de Campo          | Instalaciones (lámina de seguridad)                                 | Bajo                  | Abierto     |
| E-072 | Técnico de Planta         | Orden de taller → Recibir con faltantes                             | Medio                 | Abierto     |
| E-073 | Vendedor                  | Nota de crédito → corregir comprobante                              | Crítico               | Solucionado |
| E-074 | Vendedor                  | Nota de crédito → stock                                             | Crítico               | Solucionado |
| E-075 | Vendedor                  | Nota de crédito → devolución parcial                                | Crítico               | Solucionado |
| E-076 | Vendedor                  | Nota de crédito → excedente                                         | Crítico               | Solucionado |
| E-077 | Vendedor                  | Venta → depósito de detracción                                      | Alto                  | Solucionado |
| E-078 | Vendedor                  | Nueva venta y cotización → resumen                                  | Medio                 | Solucionado |
| E-079 | Vendedor                  | Certificado de servicio                                             | Alto                  | Descartado |
| E-080 | Vendedor                  | Envío SUNAT                                                         | Bajo                  | Solucionado |
| E-081 | Gerente                   | Panel → filtro de mes                                               | Bajo                  | Solucionado |

## Errores

### E-001 · El formulario del editor por bloques se monta sobre la vista previa

- **Rol / pantalla / botón:** Gerente → Configuración → Certificados → Nuevo (editor por bloques).
- **Gravedad:** Medio.
- **Pasos:** abrir «Crear certificado por bloques» en escritorio (pantalla ancha).
- **Esperado:** la columna de la izquierda (390 px) y la vista previa, una al lado de la otra.
- **Pasó:** los selectores salían más anchos que su columna y tapaban el borde de la vista previa.
- **Causa raíz:** un `<select>` sin ancho toma el ancho de su opción más larga, y el `label` en grilla de `Campo` lo dejaba crecer.
- **Arreglo:** `Campo` en `resources/js/components/certificados/editor-dinamico-certificado.tsx` pone `w-full min-w-0` a su control.
- **Prueba:** solo de pantalla (es CSS, no hay prueba automática).
- **Historial:**
    - 2026-10-19 · Abierto · usuario (captura)
    - 2026-10-19 · Solucionado · Claude; confirmado en el navegador a 1920 px: columna 302–692, vista previa desde 712, ningún campo se sale

### E-002 · El error del RUC no se borra al corregirlo

- **Rol / pantalla / botón:** Vendedor → Clientes → Agregar cliente → Guardar cliente.
- **Gravedad:** Medio.
- **Pasos:** 1) escribir el RUC `20123456788` (dígito verificador malo) y una razón social; 2) Guardar: sale «El RUC no es válido…»; 3) cambiarlo por `20600000013` (válido).
- **Esperado:** el mensaje de RUC inválido desaparece al editar el campo.
- **Pasó:** siguen visibles «El RUC no es válido…» y, debajo, «No encontramos ese RUC». Se contradicen.
- **Causa raíz:** por revisar (el formulario no limpia `errors.numero_documento` al cambiar el valor).
- **Prueba:** —
- **Historial:**
    - 2026-10-19 · Abierto · Claude (auditoría en el navegador)

### E-003 · El mensaje de RUC inválido dice «debe empezar con 10 o 20»

- **Rol / pantalla / botón:** Vendedor → Clientes → Agregar o editar cliente.
- **Gravedad:** Bajo.
- **Esperado:** el texto coincide con la regla: `app/Support/Ruc.php` acepta 10, 15, 16, 17 y 20.
- **Pasó:** `StoreClientRequest` y `UpdateClientRequest` dicen «debe empezar con 10 o 20», y confunden a quien tenga un RUC 15 o 17.
- **Prueba:** —
- **Historial:**
    - 2026-10-19 · Abierto · Claude

### E-004 · Accesibilidad del formulario y la lista de clientes

- **Rol / pantalla / botón:** Vendedor → Clientes.
- **Gravedad:** Medio (WCAG 2.2 AA).
- **Pasó:**
    - ~~las pestañas «Mis clientes» y «Por registrar» no tienen nombre accesible~~ (descartado el 2026-10-19: sí tienen su texto; fue una lectura equivocada de la herramienta);
    - al fallar la validación, los campos no quedan con `aria-invalid` ni enlazados a su mensaje.
- **Esperado:** cada control con nombre; cada campo con error marcado y con `aria-describedby` a su mensaje.
- **Prueba:** —
- **Historial:**
    - 2026-10-19 · Abierto · Claude
    - 2026-10-19 · Corregido el alcance: axe-core no marca violaciones en Clientes; queda abierto solo lo de `aria-invalid` y `aria-describedby`

### E-005 · Textos sin tilde y lista vacía sin mensaje

- **Rol / pantalla / botón:** Vendedor → Clientes.
- **Gravedad:** Bajo.
- **Pasó:**
    - «Razon social», «Condicion», «DIRECCION» y «ULTIMA COMPRA» van sin tilde;
    - sin clientes, la tabla queda vacía y solo dice «Mostrando 0-0 de 0», sin explicar qué hacer.
- **Esperado:** textos con tilde y un estado vacío con la acción «Agregar cliente».
- **Prueba:** —
- **Historial:**
    - 2026-10-19 · Abierto · Claude

### E-006 · Nueva venta: el aviso de caja cerrada tapa lo que de verdad falta y se repite

- **Rol / pantalla / botón:** Vendedor → Ventas → Nueva venta → Emitir factura.
- **Gravedad:** Medio.
- **Pasos:** 1) abrir Nueva venta sin caja abierta; 2) sin elegir cliente ni productos, pulsar «Emitir factura».
- **Esperado:** primero «Elige un cliente», luego «Agrega un producto». La caja importa solo si el medio de pago es efectivo, y el aviso sale una vez.
- **Pasó:**
    - el único aviso es «No tienes una caja abierta…»;
    - al elegir cliente, ese mismo texto aparece 3 veces: en el medio de pago, en la tabla de ítems y junto al botón Emitir;
    - «Emitir factura» y «Guardar borrador» están activos aunque no haya cliente ni ítems.
- **Prueba:** —
- **Historial:**
    - 2026-10-19 · Abierto · Claude

### E-007 · Al crear el certificado desde un producto, recién al guardar avisa que no puede llevar resultado

- **Rol / pantalla / botón:** Gerente → Productos → Editar → «Crear certificado nuevo» → Guardar plantilla.
- **Gravedad:** Medio.
- **Pasos:** 1) abrir «Crear certificado nuevo» desde la manguera; 2) llenar nombre, prefijo y firmante; 3) guardar.
- **Esperado:** el editor ya sabe que es un certificado de producto y no ofrece pruebas ni resultado.
- **Pasó:** venía con el bloque «Resultado de la evaluación» y al guardar decía «quita las pruebas y el resultado».
- **Causa raíz:** la composición por defecto era la de un informe de servicio.
- **Arreglo:** en `editor-dinamico-certificado.tsx`:
    - desde un producto, la composición inicial no trae pruebas, resultado ni diploma;
    - con productos ligados, esas opciones quedan deshabilitadas en «Agregar bloque»;
    - si ya estaban, aparece un aviso.
- **Prueba:** de pantalla, revisada en el navegador. La regla del servidor ya la cubre `CertificadoDeProductoTest`.
- **Pendiente menor:** después de guardar vuelve a la lista de certificados, no al producto.
- **Historial:**
    - 2026-10-19 · Abierto · Claude (auditoría)
    - 2026-10-19 · Solucionado · Claude; verificado: plantilla «Garantía de mangueras» creada y la manguera ligada (ficha: marca, modelo, presion_de_trabajo)

### E-008 · El certificado no mostraba la unidad de las columnas y en uno de producto hablaba de «servicio»

- **Rol / pantalla / botón:** cualquier certificado en PDF o Word; se vio en el de la manguera (GM-2026-000001).
- **Gravedad:** Medio.
- **Pasó:**
    - la cabecera decía «Presión de trabajo» sin «(psi)»; igual «Potencia» y «Autonomía» en luces. La vista previa sí la mostraba;
    - un certificado de producto decía «Datos del servicio» y no mostraba con qué comprobante se vendió.
- **Causa raíz:** el Blade y el Word imprimían solo `titulo`; el rótulo de datos era fijo.
- **Arreglo:**
    - cabecera `Título (unidad)` en `certificado-operatividad.blade.php`, `CertificateWordService` y `hoja-certificado.tsx`;
    - `CertificateDocumentData` da `titulo_datos` («Datos del certificado» cuando no hay orden ni tipo de atención) y `comprobante` (factura, boleta o NV de la venta, cuando no hay orden);
    - en el editor, un certificado creado desde un producto trae la declaración de garantía y la tabla «Productos».
- **Prueba:** `CertificadoDeProductoTest` («el certificado de producto se une a su venta por el comprobante…»).
- **Historial:**
    - 2026-10-19 · Abierto · Claude
    - 2026-10-19 · Solucionado · Claude; PDF regenerado y revisado

### E-009 · Al rechazar una factura por RUC no verificado, el mensaje sale con datos vacíos

- **Rol / pantalla / botón:** Vendedor → Nueva venta → Factura → Emitir factura (cliente con RUC «Sin verificar»).
- **Gravedad:** Bajo.
- **Pasó:** «su RUC no está Activo y Habido ante SUNAT (estado: , domicilio: )».
- **Esperado:** «No se pudo verificar el RUC en SUNAT. Abre la ficha del cliente y pulsa Consultar SUNAT, o emite boleta o nota de venta.»
- **Además:** al emitir **boleta** al mismo cliente sale el mismo texto «No se puede **facturar**…», que no corresponde a una boleta, y repetido dos veces.
- **Prueba:** —
- **Historial:**
    - 2026-10-19 · Abierto · Claude

### E-010 · El aviso de stock insuficiente se queda aunque la cantidad ya es válida

- **Rol / pantalla / botón:** Vendedor → Nueva venta → cantidad de un producto.
- **Gravedad:** Medio.
- **Pasos:** 1) escribir una cantidad mayor al stock: el sistema la baja al máximo y avisa «Solo hay 20…»; 2) poner una cantidad válida.
- **Pasó:** el aviso sigue visible, incluso junto a otros errores al emitir. Es el mismo patrón de E-002: los avisos no se limpian al corregir.
- **Nota:** en un campo numérico, si no se borra lo anterior, escribir «10» deja «2010» y el sistema lo baja a 20. La venta salió con 20 (no es un error, pero hay que tenerlo en cuenta).
- **Prueba:** —
- **Historial:**
    - 2026-10-19 · Abierto · Claude

### E-011 · El arqueo ciego le dice al vendedor cuánto falta y lo deja volver a contar

- **Rol / pantalla / botón:** Vendedor → Caja y cobranzas → Cerrar turno (arqueo ciego) → Confirmar.
- **Gravedad:** Alto (control de dinero).
- **Pasos:** 1) abrir caja con S/ 100; 2) cerrar contando S/ 80 sin observación.
- **Pasó:** responde «Faltan S/ 20.00. Explica qué pasó», y el vendedor ya sabe lo esperado: puede cambiar el monto contado a 100 y cerrar sin diferencia. El arqueo deja de ser ciego.
- **Esperado (cómo lo hacen los sistemas de caja):**
    - el primer conteo se guarda tal cual;
    - si hay diferencia, se pide la explicación sin dejar cambiar el conteo, o el reconteo queda registrado junto al primero;
    - el Gerente ve las dos cifras.
- **Causa raíz:** `CloseCashRegister` calculaba la diferencia y la devolvía en el error de validación («Faltan S/ 20.00») sin guardar el conteo, así que el vendedor podía reenviar otro monto. Además `DatosDeCaja` mandaba al vendedor el esperado y la diferencia de sus cierres.
- **Arreglo:**
    - tablas `cash_register_counts` y `cash_register_count_lines`: cada conteo por billete y moneda queda guardado; el primero no se cambia;
    - si no cuadra, el conteo se guarda fuera de la transacción del rechazo y el aviso no dice monto ni signo; mientras espera la explicación no entran cobros en efectivo ni salidas (`CashRegister::abiertaDe`, `exigirAbiertaParaEfectivo`, `RegistrarSalidaDeCaja`);
    - el Gerente ve el turno «Espera explicación» con esperado, contado y diferencia, y puede autorizar un reconteo (`AutorizarReconteoDeCaja`): el primer conteo queda anulado con su nombre y se muestran los dos en el detalle;
    - diálogo de cierre con 11 denominaciones y total en vivo; el vendedor ya no recibe esperado ni diferencia.
- **Prueba:** `tests/Feature/CierreCiegoDeCajaTest.php` (9 pruebas, fallaban antes del arreglo); regresión de caja 82/82.
- **Historial:**
    - 2026-10-19 · Abierto · Claude
    - 2026-10-20 · Confirmado también con sobrante: contar S/ 85 cuando se esperaban 80 responde «Sobran S/ 5.00» antes de cerrar · Claude
    - 2026-10-23 · Solucionado · Claude; revisado en el navegador: conteo de S/ 20 con esperado S/ 0 pide explicación sin monto; el Gerente ve «Espera explicación · Sobrante S/ 20.00» y autoriza el reconteo; axe sin violaciones (Caja consolidada y diálogo en celular oscuro), sin desborde a 375 px
    - 2026-10-23 · Corregido después de la entrega: el usuario no pudo cerrar con monedas («Solo se cuentan billetes y monedas en soles»). Causa: Laravel lee el punto de la clave «0.10» como un nivel del arreglo al validar. Las denominaciones viajan en céntimos (`CashRegisterCount::DENOMINACIONES`); prueba «las monedas con céntimos también se cuentan». Revisado en el navegador: venta con Yape aparece en el arqueo (S/ 35) y el cierre con monedas suma bien · Claude

### E-012 · «1 cierres» y otros plurales

- **Rol / pantalla / botón:** Vendedor → Caja y cobranzas → Cierres recientes.
- **Gravedad:** Bajo.
- **Pasó:** «1 cierres». Igual patrón que «Faltan 1 por completar» (ya corregido en Fichas técnicas). También en el Inicio del vendedor: «1 cuotas vencidas necesitan tu atención» y «Vencida hace 1 días».
- **Esperado:** singular y plural según la cantidad.
- **Historial:**
    - 2026-10-19 · Abierto · Claude

### E-013 · Al pasar una cotización a venta no se ve el stock hasta emitir

- **Rol / pantalla / botón:** Vendedor → Cotizaciones → Pasar a venta → Emitir.
- **Gravedad:** Medio.
- **Pasos:** cotizar 5 mangueras sin stock y pulsar «Pasar a venta».
- **Pasó:**
    - la línea dice solo «Producto», sin «· stock N» como en una venta nueva;
    - el vendedor arma el crédito y recién al emitir sale «Stock insuficiente…: hay 0», repetido dos veces.
- **Esperado:** al traer la cotización se ve el stock de cada línea y se avisa antes de llenar cuotas.
- **Historial:**
    - 2026-10-19 · Abierto · Claude

### E-014 · Detalles de formularios en cotización y crédito

- **Rol / pantalla / botón:** Vendedor → Nueva cotización y Nueva venta → Crédito.
- **Gravedad:** Bajo.
- **Pasó:**
    - con un descuento mayor que la línea, el total se ve como «−S/ 100.00» antes de guardar (el servidor sí lo rechaza);
    - «Falta completar cliente» sale dos veces;
    - en «Venta a crédito», «Plazo» y «N.° de cuotas», y los campos de cada cuota, no tienen etiqueta accesible, y el botón de quitar cuota no tiene nombre;
    - si las cuotas no suman el total, «Guardar crédito» no hace nada ni lo explica (solo está el texto «faltan S/ 40»).
- **Historial:**
    - 2026-10-19 · Abierto · Claude

### E-015 · Se pueden crear dos servicios (o productos) con el mismo nombre

- **Rol / pantalla / botón:** Gerente → Servicios → Nuevo Servicio → Crear servicio. Mismo caso en Productos.
- **Gravedad:** Medio (dato duplicado; principio 3 de CONTEXTO_SISTEMA).
- **Pasos:** crear «Recarga de extintor PQS 6 kg» a S/ 35 y otra vez el mismo nombre a S/ 40.
- **Pasó:** quedan SRV-0001 y SRV-0002 activos con el mismo nombre y distinto precio. El vendedor ve dos opciones iguales.
- **Esperado:** el nombre no se repite entre los activos (Odoo y SAP avisan del duplicado), o al menos se advierte antes de crear.
- **Historial:**
    - 2026-10-19 · Abierto · Claude (SRV-0002 se desactivó para seguir la prueba)

### E-016 · La factura siempre sale con el establecimiento 0000, aunque la sede tenga otro

- **Rol / pantalla / botón:** Gerente → Sedes (código de establecimiento anexo) y Vendedor → emitir factura o boleta.
- **Gravedad:** Medio, por confirmar con la contadora.
- **Pasó:**
    - `GreenterService.php:188` y `:304` fijan `setCodLocal('0000')`;
    - el campo `sedes.cod_establecimiento_anexo` solo se usa en la guía de remisión;
    - en local, las 3 sedes tienen `0000`, y al crear una sede nueva el campo viene con `0000`.
- **Esperado:** si la tienda está registrada en SUNAT como anexo (0001, 0002…), sus comprobantes salen con ese código, que se toma de la sede de la venta. Hay que confirmar con la contadora los códigos reales de cada local.
- **Historial:**
    - 2026-10-19 · Abierto · Claude

### E-017 · Nueva Sede: campos sin identificar para el lector de pantalla

- **Rol / pantalla / botón:** Gerente → Sedes → Nueva Sede.
- **Gravedad:** Bajo (accesibilidad).
- **Pasó:** el nombre y otro campo no tienen `id` ni `name`, así que no quedan ligados a su etiqueta. Al guardar vacío solo sale el globo del navegador.
- **Historial:**
    - 2026-10-19 · Abierto · Claude

### E-018 · Rutas y controlador de Transporte que nadie puede usar

- **Rol / pantalla / botón:** `/gerente/transporte` (no está en el menú).
- **Gravedad:** Bajo (código muerto).
- **Pasó:** `routes/gerente.php:40-44` exige `role:Gerente` y `role:Vendedor` a la vez: siempre da 403. El comentario dice «rutas antiguas bloqueadas: el vendedor agrega transporte dentro de la guía».
- **Esperado:** borrar las rutas, `TransporteController` y su página si ya no se usan.
- **Historial:**
    - 2026-10-19 · Abierto · Claude

### E-019 · La auditoría muestra códigos y JSON en lugar de nombres

- **Rol / pantalla / botón:** Gerente → Auditoría → columna Detalle.
- **Gravedad:** Bajo.
- **Pasó:**
    - «Cliente: 1» en lugar del nombre;
    - «Tipo: garantia_de_mangueras» en lugar de «Garantía de mangueras»;
    - «Ficha tecnica: {"marca":…}» en JSON y sin tilde.
- **Además:** la recepción de mercadería no aparece como acción propia, solo como «Producto actualizado · Costo promedio».
- **Historial:**
    - 2026-10-19 · Abierto · Claude

### E-020 · El mismo mensaje de error sale dos veces

- **Rol / pantalla / botón:** varios formularios: Datos de la empresa (RUC), Nueva cotización (cliente), Cerrar turno (faltante), Nueva venta (stock).
- **Gravedad:** Bajo.
- **Pasó:** el error aparece en el resumen de arriba y otra vez junto al campo, con el mismo texto. Es parte del patrón de E-002 y E-010: los avisos no se limpian ni se agrupan.
- **Esperado:** un resumen breve arriba («Revisa 2 campos») y el detalle solo junto a cada campo.
- **Historial:**
    - 2026-10-19 · Abierto · Claude

### E-021 · «Ventas netas» y «Margen bruto» incluyen el IGV

- **Rol / pantalla / botón:** Gerente → Dashboard (y lo que use `ventasNetas()` y `margenBruto()`: tendencias y reporte PDF).
- **Gravedad:** Alto (números para decidir).
- **Pasos:** vender 20 mangueras a S/ 120 con IGV (S/ 2,400) con costo de S/ 45.50.
- **Pasó:**
    - Ventas netas S/ 2,400 y Margen S/ 1,490 (62 %);
    - `DashboardController::ventasNetas()` suma `sales.total` (con IGV);
    - `margenBruto()` le resta el costo de los productos, y además cuenta como margen todo el ingreso por servicios.
- **Esperado:**
    - ventas netas sin IGV: S/ 2,033.90;
    - margen = neto − costo = S/ 1,123.90 (55 %);
    - definir si el costo de compra se guarda sin IGV (lo normal, porque el IGV de compra es crédito fiscal) y decir de dónde sale el costo de los servicios.
- **Confirmado después:**
    - con dos recargas de S/ 35 el margen subió a S/ 1,560: los servicios entran completos como margen;
    - el reporte mensual en PDF repite las mismas cifras;
    - «Variación 100 %» cuando el mes anterior es 0, que es una división entre cero; debería decir «nuevo» o «—».
- **Historial:**
    - 2026-10-19 · Abierto · Claude

### E-022 · Nueva orden de servicio: «Falta completar service id» y fecha pasada aceptada

- **Rol / pantalla / botón:** Vendedor → Servicios → Nueva orden → Crear orden.
- **Gravedad:** Medio.
- **Pasó:**
    - el error dice «Falta completar service id», en inglés técnico;
    - después de elegir el servicio el error sigue visible (patrón de E-002);
    - la fecha programada acepta días pasados (10/10/2026) sin aviso, y la orden nace «atrasada».
- **Esperado:** «Elige el tipo de servicio»; el error se borra al elegir; una fecha pasada pide confirmación o no se permite.
- **Historial:**
    - 2026-10-19 · Abierto · Claude

### E-023 · La orden de servicio no tiene cantidad: el taller espera 1 extintor aunque sean 3

- **Rol / pantalla / botón:** Vendedor → Nueva orden de servicio; Técnico de Planta → Orden → Recibir.
- **Gravedad:** Alto (relación entre lo vendido y lo atendido).
- **Pasos:** crear una orden de recarga con la instrucción «3 extintores PQS 6 kg enumerados del 1 al 3».
- **Pasó:** el formulario no pide cantidad. El taller muestra «0 de 1 extintores» y «faltan 1 de 1», y la cantidad real queda solo en el texto libre.
- **Esperado:** la orden lleva la cantidad por servicio, o se crea desde la venta o cotización con sus cantidades, y el taller espera esa cantidad.
- **Historial:**
    - 2026-10-19 · Abierto · Claude

### E-024 · El taller acepta un año de fabricación futuro y calcula mal las fechas del extintor

- **Rol / pantalla / botón:** Técnico de Planta → Orden → Paso 1 Recibir → Registrar a mano → Guardar extintor.
- **Gravedad:** Alto (la prueba hidrostática y la vida útil dependen del año).
- **Pasos:** registrar un extintor con año de fabricación 2030.
- **Pasó:**
    - se guardó `anio_fabricacion = 2030`: `TecnicoPlanta/ReceptionController.php:206` solo exige texto de hasta 100 caracteres. Almacén sí lo valida en su recepción;
    - el extintor del cliente quedó con `fecha_venta` = hoy, aunque llegó a recarga y no se le vendió;
    - `proxima_prueba_hidrostatica` = hoy + 5 años, sin considerar el año de fabricación.
- **Esperado:** año entre 1980 y el actual (la misma regla que en Almacén); sin fecha de venta para equipos que no vendió la empresa; la próxima PH según el año o la última PH registrada.
- **Consecuencia vista:** el certificado OG-2026-0001 salió impreso con «Año fab. 2030», «Próx. P.H. OCT-2031» y «Marca: No legible / Pendiente de verificar». Un documento legal no debería emitirse con datos «pendientes de verificar».
- **Corrección implementada (2026-10-23, Codex/Astra):** alta de taller sin venta ni recarga supuestas; última PH real persistida, próxima calculada desde ella, intervalo único configurable en Gerencia (5 años por defecto), fechas próximas editables y año 1980–actual. Validación compartida para alta y edición. Los certificados nuevos guardan sus fechas y las mantienen al cambiar posteriormente el equipo/configuración o corregir su numeración. Las fechas desconocidas permanecen vacías.
- **Investigación:** [OSHA 1910.157](https://www.osha.gov/laws-regs/regulations/standardnumber/1910/1910.157) distingue intervalos por tipo y exige registrar fecha real/identificación del equipo; se aplica la política de 5 años de Bruce Fire aprobada por el usuario, sin presentarla como regla universal o norma peruana verificada. [Odoo: seguimiento por series](https://www.odoo.com/documentation/18.0/applications/inventory_and_mrp/inventory/product_management/product_tracking/serial_numbers.html) respalda mantener la identidad del equipo a través de sus servicios.
- **Verificación:** `composer ci:check` (1,433 pruebas / 8,151 aserciones) y 122 regresiones dirigidas (642 aserciones) pasan; también pasan tipos, Pint, PHPStan, formato/lint y build. E-024 sigue abierto hasta revisar la interfaz y cotejar manualmente las fechas históricas desconocidas; no se hizo actualización masiva ni se inventaron datos. El navegador local sigue deshabilitado en Work.
- **Historial:**
    - 2026-10-19 · Abierto · Claude

### E-025 · La cola del taller cambia las mayúsculas del nombre del servicio

- **Rol / pantalla / botón:** Técnico de Planta → Inicio (cola de taller).
- **Gravedad:** Bajo.
- **Pasó:** «Recarga De Extintor PQS 6 Kg» en lugar de «Recarga de extintor PQS 6 kg» («Kg» además es incorrecto: el símbolo es «kg»).
- **Historial:**
    - 2026-10-19 · Abierto · Claude

### E-026 · El Inicio del vendedor dice «Todo al día» con un permiso del cliente pendiente

- **Rol / pantalla / botón:** Vendedor → Inicio.
- **Gravedad:** Medio.
- **Pasó:** con OT-2026-0001 en curso y una deficiencia esperando la autorización del cliente (el taller está en pausa por eso), el Inicio dice «0 servicios en agenda» y «Todo al día: nada por enviar, cobrar ni pasar a venta». Solo la campana muestra 1.
- **Esperado:** el Inicio muestra «1 trabajo espera la autorización del cliente», porque es lo que frena al taller.
- **Historial:**
    - 2026-10-19 · Abierto · Claude

### E-027 · El vendedor ve la orden en «Asignada» mientras el taller ya va en el paso 3

- **Rol / pantalla / botón:** Vendedor → Órdenes de servicio (lista) frente a Técnico de Planta → Orden.
- **Gravedad:** Medio (cada rol ve un estado distinto del mismo trabajo).
- **Pasó:** el taller recibió y revisó el extintor y la orden está en «Paso 3 de 5 · En pausa». El vendedor ve «Asignada: etapa actual · En proceso: pendiente».
- **Esperado:** el mismo estado en los dos lados («En taller · paso 3 · espera tu autorización»).
- **Historial:**
    - 2026-10-19 · Abierto · Claude

### E-028 · Aprobar un adicional deja escribir el importe y después lo rechaza

- **Rol / pantalla / botón:** Vendedor → Deficiencias y adicionales → Aprobar → Confirmar aprobación.
- **Gravedad:** Medio.
- **Pasó:**
    - el formulario muestra «Importe aceptado (S/)» aunque no se elija cotización; al confirmar, solo sale un aviso pasajero: «Cotiza el adicional antes de autorizar su importe»;
    - el selector de cotización ofrece la COT-0001 de mangueras, que no tiene relación con la deficiencia;
    - se puede aprobar sin importe: la reparación queda sin costo y sin aviso de que no se cobrará.
- **Esperado:** el importe aparece solo con cotización elegida; las cotizaciones ofrecidas son las de esa orden; aprobar sin importe dice claramente «sin costo para el cliente».
- **Historial:**
    - 2026-10-19 · Abierto · Claude

### E-029 · El taller ofrece como repuesto cualquier producto, incluso los que no son repuestos

- **Rol / pantalla / botón:** Técnico de Planta → Orden → Paso 4 Reparar → Repuesto usado.
- **Gravedad:** Medio.
- **Pasó:** para la «Manguera de descarga» de un extintor, la única opción es «Manguera contra incendio 1½ x 15 m (sin stock)», que es el producto de gabinete que vendemos y no un repuesto.
- **Esperado:** la lista muestra solo la categoría «Repuesto» (y, mejor, los repuestos de esa parte: manguera, válvula, manómetro), con el stock del Taller.
- **Historial:**
    - 2026-10-19 · Abierto · Claude

### E-030 · La entrega y el acta usan la dirección de nuestra sede como si fuera la del cliente

- **Rol / pantalla / botón:** Técnico de Campo → Entregas → OT-2026-0001 y el Acta de Conformidad (PDF).
- **Gravedad:** Alto (documento firmado por el cliente con datos equivocados).
- **Pasó:**
    - «Lugar de Entrega: suarez 758» y en el acta «Dirección / Sede: suarez 758», que es la dirección de la sede. La del cliente es «Av. Prueba 123, Trujillo»;
    - «Fecha de Recepción: 10/10/2026» es la fecha programada; la recepción real fue el 19/10;
    - después de entregado, el extintor sigue con ubicación «Planta - Taller»;
    - «Total: 1» aunque el vendedor indicó 3 (ver E-023).
- **Esperado:** dirección del cliente (o la referencia de la orden), fecha real de recepción, y que la ubicación del equipo pase al cliente al entregarlo.
- **Relación que ya existe y no se usa:** el cliente tiene «Sedes y sucursales» (puntos de entrega con dirección y contacto), y la Referencia de la venta ya las sugiere. La entrega y el acta deberían tomar esa sede.
- **Historial:**
    - 2026-10-19 · Abierto · Claude

### E-031 · El acta no tiene el diseño de la marca y el Inicio de Campo se contradice

- **Rol / pantalla / botón:** Acta de Conformidad (PDF); Técnico de Campo → Inicio.
- **Gravedad:** Bajo.
- **Pasó:**
    - el acta usa azul y letra Times, distinto de los certificados (rojo y grafito, fuentes de la marca) y del comprobante;
    - el Inicio de Campo dice «Pendientes: 1» y, más abajo, «Tu semana: sin trabajo pendiente»;
    - el menú de Campo solo tiene Inicio y Entregas: recojos, inspecciones, instalaciones y mantenimientos existen, pero solo se llega por los filtros del Inicio.
- **Historial:**
    - 2026-10-19 · Abierto · Claude

### E-032 · Un ejemplo de DNI real en pantalla y un texto que contradice dónde llegan los equipos

- **Rol / pantalla / botón:** Técnico de Campo → Entrega (DNI del receptor) y → Iniciar recojo.
- **Gravedad:** Bajo (privacidad y claridad).
- **Pasó:**
    - el ejemplo «8 números, por ejemplo 45892147» (`tecnico-campo/entregas/show.tsx:594`) es un DNI real: la consulta a RENIEC devuelve el nombre de una persona;
    - el recojo dice «Planta lo recibirá en Almacen», pero según la decisión vigente el Taller es un lugar aparte de los almacenes.
- **Esperado:** un ejemplo que no sea un DNI real (por ejemplo «8 números») y el texto «lo recibirá el Taller».
- **Historial:**
    - 2026-10-19 · Abierto · Claude

### E-033 · La consulta rápida pide un código BF-EQ pero no encuentra los extintores de clientes

- **Rol / pantalla / botón:** Almacén → Consulta rápida → Consultar.
- **Gravedad:** Medio.
- **Pasos:** buscar «BF-EQ-000001», el extintor del cliente registrado por el taller en OT-2026-0001.
- **Pasó:** «No existe ninguna unidad física ni producto que coincida», aunque la ayuda de la misma pantalla dice «ingrese el correlativo BF-EQ-XXXXXX».
- **Causa probable:** la consulta solo busca en el stock (InventoryUnit) y los extintores de clientes están en `equipment`.
- **Esperado:** encontrar también los equipos de clientes (dónde están, de qué cliente, su orden) o cambiar la ayuda para no prometerlo.
- **Historial:**
    - 2026-10-19 · Abierto · Claude

### E-034 · En celular, el botón flotante de Chispa tapa montos y campos

- **Rol / pantalla / botón:** todos; se vio en Vendedor → Nueva venta (resumen) y Gerente → Editor de certificados (subtítulo), a 375 px.
- **Gravedad:** Medio (tapa el total a pagar).
- **Pasó:** la mascota flotante queda encima de «S/ 0.00» del resumen y del campo Subtítulo.
- **Esperado:** el contenido deja espacio abajo para el botón, o el botón se achica o se esconde en pantallas con formulario o resumen fijo.
- **Historial:**
    - 2026-10-19 · Abierto · Claude

### E-035 · El recuadro del QR en la vista previa usa ARIA sin rol

- **Rol / pantalla / botón:** Gerente → Certificados → editar plantilla (vista previa).
- **Gravedad:** Bajo (axe-core: `aria-prohibited-attr`, nivel «serious»).
- **Pasó:** `hoja-dinamica-certificado.tsx:237`: `<div className="qr" aria-label="QR de verificación" />` sin rol.
- **Esperado:** `role="img"` con su `aria-label`, o `aria-hidden` si es decorativo.
- **Historial:**
    - 2026-10-19 · Abierto · Claude

### E-036 · El técnico de planta no puede volver a ver una orden cerrada

- **Rol / pantalla / botón:** Técnico de Planta → `/tecnico-planta/ordenes/1` después de entregada y cerrada.
- **Gravedad:** Bajo (puede ser intencional).
- **Pasó:** responde 404. El técnico pierde el historial de su propio trabajo (fotos, hallazgos, repuestos).
- **Esperado:** decidir si el técnico ve sus órdenes cerradas en modo lectura (lo usual en sistemas de servicio técnico).
- **Historial:**
    - 2026-10-19 · Abierto · Claude

### E-037 · Un mensaje en inglés al confirmar la contraseña

- **Rol / pantalla / botón:** cualquier rol → Mi cuenta → Seguridad → «Confirmar contraseña» con una clave equivocada.
- **Gravedad:** Bajo.
- **Pasó:** «The provided password was incorrect.» El resto (inicio de sesión, cambio de contraseña) sí está en español.
- **Esperado:** «La contraseña no es correcta.» Falta la traducción de ese mensaje de Fortify en `lang/es`.
- **Historial:**
    - 2026-10-19 · Abierto · Claude
    - 2026-10-20 · También al activar dos pasos con un código malo: «The provided two factor authentication code was invalid.» No existe `lang/es.json`, así que ningún mensaje de Fortify por clave de texto sale traducido · Claude

### E-038 · «Clientes varios» se cuenta como un cliente real

- **Rol / pantalla / botón:** Vendedor → Clientes («Mis clientes 2»); Gerente → Recompra e IA («2 sin puntuación»).
- **Gravedad:** Medio (distorsiona conteos e IA).
- **Pasó:** el registro interno «CLIENTES VARIOS» (00000000), que el sistema crea para boletas sin identificar, suma en el total de clientes y entra a la IA de recompra.
- **Esperado:** excluirlo de conteos, listas de cartera, «Por vencer» e IA; solo se usa al emitir boletas anónimas.
- **Historial:**
    - 2026-10-19 · Abierto · Claude

### E-039 · «Nueva venta» desde la ficha del cliente da error 500 para un vendedor con sede

- **Rol / pantalla / botón:** Vendedor (con sede asignada) → Clientes → ficha → «Nueva venta».
- **Gravedad:** Alto (el botón falla siempre para ese vendedor).
- **Pasos:** entrar como `claude.vendedor` (sede Mayorista), abrir la ficha de CLI-0003 y pulsar «Nueva venta».
- **Pasó:** página 500. Log: `Unknown column 'sede_id' in 'where clause'` en `select … from clients where sede_id = 2 and clients.id = 3`.
- **Causa raíz:** `app/Http/Controllers/Vendedor/SaleController.php:157` filtra `clients` por `sede_id`, pero los clientes no pertenecen a una sede (la tabla no tiene esa columna).
- **Esperado:** precargar el cliente sin filtrar por sede. Falta además una prueba con un vendedor que tenga sede restringida.
- **Además (Bajo):** la ficha muestra «Consultado el 2026-10-19T15:37:38-05:00» en formato técnico.
- **Historial:**
    - 2026-10-19 · Abierto · Claude

### E-040 · Al editar una venta que sube de precio, el sistema registra solo el pago de la diferencia

- **Rol / pantalla / botón:** Vendedor → Ventas → VTA-0004 (F001-1 por enviar) → Editar → Guardar cambios.
- **Gravedad:** Medio (cobro registrado sin que exista).
- **Pasos:** factura de 1 recarga (S/ 35, pagada por transferencia) → Editar → cantidad 2 → Guardar.
- **Pasó:** aparece un segundo pago «Transferencia · Ajuste por edición de la venta · S/ 35 · Registrado», sin que nadie lo cobrara. El texto sale pegado: «TransferenciaAjuste Por Edición De La Venta».
- **Esperado:** la diferencia queda como saldo por cobrar (o se pregunta cómo pagó la diferencia).
- **Historial:**
    - 2026-10-19 · Abierto · Claude

### E-041 · Detalles de notas de crédito y baja

- **Rol / pantalla / botón:** Vendedor → Venta → Nota de crédito y Comunicar baja.
- **Gravedad:** Bajo.
- **Pasó:**
    - después de emitir la NC FC01-1, el formulario sigue con «Aceptar y enviar a SUNAT» activo y es fácil emitir otra por error;
    - para un servicio se ofrece «Ya recibí los productos: vuelven al stock»;
    - «Comunicar baja» se ofrece en una factura con NC y ND, y recién al enviar dice «da de baja primero esas notas».
- **Lo que funciona bien:** las cantidades devueltas no pasan de lo vendido; NC FC01-1 y ND FD01-1 aceptadas por SUNAT beta.
- **Historial:**
    - 2026-10-19 · Abierto · Claude

### E-042 · Cambiar una factura por enviar a boleta podría dejar un salto en la numeración

- **Rol / pantalla / botón:** Vendedor → Venta (por enviar) → Editar.
- **Gravedad:** Medio, por confirmar con la contadora.
- **Pasó:** la pantalla dice «si pasas de factura a boleta o al revés, toma el número de la otra serie». El número de la serie original (por ejemplo F001-1) queda sin usar y nunca se envía.
- **Esperado:** confirmar si ese número se reutiliza, se informa o se evita el cambio de tipo una vez numerado.
- **Historial:**
    - 2026-10-19 · Abierto · Claude

### E-043 · Guía de remisión: mensajes con nombres internos y sin PDF imprimible

- **Rol / pantalla / botón:** Vendedor → Venta (factura) → Emitir guía → Crear guía; detalle de la guía T001-1.
- **Gravedad:** Medio.
- **Pasó:**
    - mensajes «Falta completar transport vehicle id», «Falta completar driver id», «nuevo conductor dni debe tener 8 números» y «El campo nuevo conductor licencia es obligatorio…» (nombres internos de los campos, mismo patrón que E-022);
    - el detalle de la guía solo ofrece XML y CDR; no hay representación impresa (PDF con QR) para entregar al transportista o al cliente;
    - el peso viene en 0 porque el producto no tiene peso registrado, y hay que escribirlo a mano.
- **Lo que funciona bien:** se arma sola desde la factura (destinatario, partida, llegada con ubigeo, bienes); valida la placa; en local avisa con claridad «SIMULADO…».
- **Esperado:** nombres de campo en español en `lang/es/validation.php` (atributos) y un PDF de la guía.
- **Historial:**
    - 2026-10-19 · Abierto · Claude

### E-044 · Anular una venta deja su pago como cobrado y no pide motivo

- **Rol / pantalla / botón:** Vendedor → Ventas → VTA-0002 (NV-0002, cobro de la orden OT-2026-0001) → Anular nota de venta → Confirmar.
- **Gravedad:** Alto (dinero y trazabilidad).
- **Pasó:**
    - no pide motivo de anulación;
    - el pago de S/ 35 por transferencia sigue «Registrado» en la venta anulada: no hay devolución ni ajuste, y lo «cobrado» queda inflado;
    - el aviso dice «sus certificados se anulan», pero el certificado OG-2026-0001 sigue vigente (el servicio sí se hizo);
    - la orden OT-2026-0001 queda «cerrada» y unida a una venta anulada: el trabajo hecho queda sin cobrar y nada lo avisa.
- **Esperado (como en Odoo o SAP):** motivo obligatorio; al anular, el pago pasa a «por devolver» o «saldo a favor» del cliente; el aviso dice exactamente qué se anula; la orden vuelve a «falta cobrar».
- **Historial:**
    - 2026-10-19 · Abierto · Claude

### E-045 · Cobrar una cuota propone efectivo aunque la caja esté cerrada

- **Rol / pantalla / botón:** Vendedor → Caja y cobranzas → Registrar pago (cuota).
- **Gravedad:** Bajo.
- **Pasó:** con la caja cerrada, el formulario propone «Efectivo». Al registrar sale un aviso pasajero: «Para cobrar en efectivo abre primero tu turno en Caja…». El aviso desaparece en segundos y el formulario no cambia.
- **Lo que funciona bien:** no deja pagar más que el saldo; el abono parcial (S/ 30 de 60) deja la cuota con saldo S/ 30; la nota de débito aparece como cuota por cobrar.
- **Esperado:** con la caja cerrada, proponer otro medio o mostrar el aviso fijo dentro del formulario.
- **Nota:** durante la prueba se apagó MySQL (Laragon) y los formularios no dijeron nada. Conviene un aviso de «no hay conexión con la base de datos».
- **Historial:**
    - 2026-10-19 · Abierto · Claude

### E-046 · Una cotización solo pasa a «Enviada» por WhatsApp; si no, nunca se puede aceptar ni rechazar

- **Rol / pantalla / botón:** Vendedor → Cotizaciones → fila de COT-0001.
- **Gravedad:** Medio (embudo de ventas incompleto; la conversión del Dashboard queda en «Sin datos»).
- **Pasó:**
    - el cliente no tiene celular: la fila muestra «Agregar número», sin «Enviar», «Aceptar» ni «Rechazar», aunque al crearla el aviso decía «Pulsa Enviar cuando se la mandes al cliente»;
    - los botones Aceptar y Rechazar solo aparecen con estado «Enviada», y ese estado solo se marca al pulsar el enlace de WhatsApp (`cotizaciones/index.tsx`, `canAcceptOrReject`);
    - con un celular inválido («123»), «Guardar y enviar» se deshabilita sin decir por qué.
- **Lo que funciona bien:** PDF de la cotización (395 KB); «Pasar a venta» funciona desde Borrador.
- **Esperado:** un botón «Marcar como enviada» (en persona, correo o PDF) y aceptar o rechazar sin depender de WhatsApp.
- **Historial:**
    - 2026-10-19 · Abierto · Claude

### E-047 · «Extintor no apto» se pierde al pasar a otro extintor y no pide foto

- **Rol / pantalla / botón:** Técnico de Planta → OT-2026-0002 → Paso 2 Revisar → «Extintor no apto para uso».
- **Gravedad:** Medio.
- **Pasos:** marcar «no apto» en el extintor 1, pulsar «Siguiente», revisar el 2 y volver al 1.
- **Pasó:**
    - la casilla «no apto» del extintor 1 ya estaba desmarcada: el estado vive en el componente de cada extintor (`checklist-por-partes.tsx`) y se pierde al cambiar de extintor si no se guardó antes;
    - el motivo sí es obligatorio (bien), pero no pide foto, aunque dar de baja el equipo del cliente y anular sus certificados es la decisión más grave del taller. Las fallas de cada parte sí exigen foto;
    - se puede marcar «no apto» con todas las partes en «bien», sin ninguna parte con falla.
- **Esperado:** guardar o advertir antes de cambiar de extintor; foto obligatoria para «no apto».
- **Historial:**
    - 2026-10-19 · Abierto · Claude

### E-048 · Adicionales: no hay repuestos que cotizar y el selector muestra otro monto

- **Rol / pantalla / botón:** Vendedor → Deficiencias y adicionales → Cotizar adicional, Aprobar y Rechazar.
- **Gravedad:** Medio.
- **Pasó:**
    - para cotizar la «Válvula de descarga» hay que elegir un ítem del catálogo, pero solo existen el extintor, la manguera de gabinete y la recarga. No hay repuestos ni un servicio de reparación (relacionado con E-029);
    - la tarjeta dice «COT-0002 · S/ 35.00» (el adicional), pero el selector del formulario Aprobar dice «COT-0002 · S/ 85.00» (la cotización entera, con los 2 mantenimientos). El importe propuesto sí es el correcto (S/ 35);
    - el ejemplo del campo de observaciones al **rechazar** dice «Ej. Aprobó cotización adicional…».
- **Lo que funciona bien:** el rechazo registra quién, canal y fecha; un doble envío se frena («Esta deficiencia no está esperando autorización»); la cotización de la orden toma la cantidad real recibida (2 mantenimientos).
- **Historial:**
    - 2026-10-19 · Abierto · Claude

### E-049 · Si el cliente rechaza una reparación, la orden no se puede cerrar y queda trabada

- **Rol / pantalla / botón:** Técnico de Planta → OT-2026-0002 → Paso 5 Cierre → «Terminar y emitir certificados».
- **Gravedad:** Alto (la orden no avanza ni se entrega).
- **Pasos:**
    1. 2 extintores: el 1 «no apto»; el 2 con fallas en manómetro y válvula;
    2. el vendedor rechaza la del manómetro y aprueba la de la válvula;
    3. se repara la válvula y se pulsa «Terminar y emitir certificados».
- **Pasó:**
    - el resumen muestra «1 Apto» (BF-EQ-000003) y el botón; al pulsarlo sale «No hay extintores recibidos y aptos para certificar»;
    - después la pantalla queda sin botones: no hay forma de cerrar la orden sin certificado ni de entregarla.
- **Causa raíz:** `ServiceOrder::equiposAptos()` (`app/Models/ServiceOrder.php:217`) excluye a los equipos con alguna deficiencia rechazada; la pantalla de cierre usa otro criterio para mostrar «Apto». `ExecuteAndCloseServiceOrder.php:226` lanza el error cuando no queda ninguno.
- **Esperado:** el cierre muestra «No certificable: el cliente no autorizó la reparación de …» y permite terminar la orden sin certificado para ese equipo (devolverlo con constancia), como en los sistemas de servicio técnico.
- **Además:** el repuesto usado por el Taller se descontó del almacén de la tienda (sede 1), no del almacén propio del Taller (sede 3), y sin traslado (ver E-029).
- **Historial:**
    - 2026-10-19 · Abierto · Claude

### E-050 · Buscar un extintor por su serie de fábrica no lo encuentra

- **Rol / pantalla / botón:** Técnico de Planta → campo «Escanea el sticker BF-EQ o escribe la serie de fábrica» (`tecnico-planta.equipos.search`).
- **Gravedad:** Medio.
- **Pasó:** `?code=BF-EQ-000002` lo encuentra; `?code=SER-AUD-002` (su serie de fábrica) responde `found:false`.
- **Esperado:** encontrarlo también por la serie de fábrica, como promete el campo. Es lo normal cuando el extintor llega sin sticker o con el sticker dañado.
- **Historial:**
    - 2026-10-19 · Abierto · Claude

### E-051 · Corregir un certificado de producto deja certificar más unidades de las vendidas y le pone «tipo de atención»

- **Rol / pantalla / botón:** Vendedor → Certificados → GM-2026-000002 → Corregir datos → Guardar corrección.
- **Gravedad:** Alto (documento legal que garantiza una manguera no vendida).
- **Pasos:** en la corrección cambiar «Cantidad» de 2 a 3 y guardar.
- **Pasó:**
    - se guardó la revisión 1 con cantidad 3, aunque la venta F001-2 tiene 2;
    - el formulario muestra «Tipo de atención» y «Dirección del servicio», que no aplican a un producto, y al guardar puso `tipo_atencion = mantenimiento`: el PDF vuelve a decir «Datos del servicio» y «Tipo de atención: Mantenimiento» (deshace E-008);
    - el detalle del certificado muestra el botón «Corregir datos» dos veces, y no hay forma de anular un certificado desde ahí.
- **Esperado:**
    - en un certificado de producto, producto y cantidad salen de la venta y no se editan (o no superan lo vendido);
    - la corrección no ofrece tipo de atención;
    - la ficha se corrige en Almacén y se regenera.
- **Historial:**
    - 2026-10-19 · Abierto · Claude

### E-052 · La nota de débito por pago tardío se emite con IGV

- **Rol / pantalla / botón:** Vendedor → Venta → Nota de débito → «Cobro por pago tardío» (FD01-1, S/ 5).
- **Gravedad:** Medio, por confirmar con la contadora.
- **Pasó:** se emitió con base S/ 4.24 + IGV S/ 0.76. Los intereses moratorios suelen tratarse como indemnización no gravada con IGV.
- **Esperado:** confirmar con la contadora la afectación (gravado o inafecto) de los intereses por mora y del ajuste de precio.
- **Además (lo que funciona):**
    - el CSV de comprobantes sale como un registro de ventas, con las notas de crédito en negativo y el comprobante afectado;
    - la descarga masiva en ZIP (970 KB), el Excel de clientes y la constancia de recepción en PDF funcionan.
- **Historial:**
    - 2026-10-19 · Abierto · Claude

### E-053 · Varios formularios muestran el error solo en un aviso que desaparece

- **Rol / pantalla / botón:**
    - Vendedor → Clientes → Editar cliente (teléfono «123»);
    - Caja y cobranzas → Registrar pago (efectivo sin caja);
    - Deficiencias → Aprobar (importe sin cotización);
    - Venta → Nota de crédito.
- **Gravedad:** Medio (WCAG: el error no queda junto al campo ni persiste).
- **Pasó:** al fallar la validación del servidor, el formulario queda abierto sin marcar el campo. El motivo («Escribe un celular de 9 dígitos…», «Para cobrar en efectivo abre primero tu turno…») sale en un aviso (`toast.error`) que desaparece en segundos. Otras pantallas (Nueva venta, Fichas técnicas, Sedes) sí muestran el error junto al campo.
- **Esperado:** el mismo patrón en todo el sistema: error junto al campo, con `aria-invalid` y `aria-describedby`; el aviso, como mucho, de resumen.
- **Además (Bajo):** la ficha del cliente muestra fechas técnicas («2026-10-19T15:50:06-05:00») en Historial de actividad y en «Consultado el…»; los tipos de sede van en minúscula; los formularios de crear y editar cliente tienen campos distintos (crear muestra Estado SUNAT y Condición; editar, WhatsApp y Observaciones).
- **Historial:**
    - 2026-10-19 · Abierto · Claude

### E-054 · Campo acepta un extintor dado de baja y revisa con otro checklist que Planta

- **Rol / pantalla / botón:** Técnico de Campo → Mantenimientos → OT-2026-0003 → Agregar extintor → Revisar → Finalizar.
- **Gravedad:** Medio.
- **Pasó:**
    - Campo pudo agregar y revisar como «Conforme» el BF-EQ-000002, que Planta había dado de baja (no apto) en la OT-2. La lista «Equipos registrados del cliente» lo ofrece sin marcarlo como dado de baja;
    - al finalizar no emitió certificado (bien), pero el mensaje dice «Ningún extintor salió conforme», mientras la pantalla lo muestra «Conforme»;
    - el mismo extintor se revisa con **dos checklists distintos**: Planta, 5 partes que «empiezan en verde»; Campo, 14 puntos que hay que marcar uno por uno (Conforme, Observado, No aplica).
- **Esperado:**
    - no permitir (o advertir fuerte) agregar un equipo dado de baja, y mostrar «dado de baja el 19/10 en OT-2026-0002»;
    - un solo checklist del extintor (el de 14 puntos de Campo, que se acerca más a la NTP 350.043) usado por los dos roles.
- **Lo que funciona bien:** la lista de lo que falta antes de finalizar (fotos antes y después, checklist, receptor, firma, conformidad) es muy clara.
- **Historial:**
    - 2026-10-19 · Abierto · Claude

### E-055 · Un recojo iniciado sin servicio nunca se puede confirmar

- **Rol / pantalla / botón:** Técnico de Campo → Iniciar recojo (2 extintores) → registrar 2 extintores, foto, firma y conformidad → «Confirmar Recojo y Cadena de Custodia».
- **Gravedad:** Alto (el recojo queda trabado en «Pendiente de recojo»).
- **Pasó:** aviso fijo «Registra los 0 extintores recogidos antes de confirmar», aunque hay «Extintores recogidos (2)». Recargar no lo arregla.
- **Causa raíz:**
    - `app/Actions/TecnicoCampo/IniciarRecojo.php:43` crea la línea con la cantidad solo si se eligió un servicio, y el formulario no lo pide («el vendedor define los servicios…»); la cantidad queda solo en el evento (`cantidad_esperada`);
    - `CollectionController.php:127` toma `cantidadEsperada` de la suma de líneas, que da 0;
    - `recojos/show.tsx:130` exige que los registrados sean iguales a la cantidad esperada.
- **Esperado:** guardar la cantidad esperada aunque no haya servicio (o tomarla del evento) y permitir confirmar si los registrados coinciden.
- **Además:** el lugar del recojo es texto libre aunque el cliente tenga sedes registradas; y dice «Planta lo recibirá en Almacen» (E-032).
- **Historial:**
    - 2026-10-19 · Abierto · Claude

### E-056 · No se puede pasar a un vendedor a Almacén (bloqueo entre rol y sede)

- **Rol / pantalla / botón:** Gerente → Usuarios y roles → tarjeta del trabajador → selectores Rol y Sede.
- **Gravedad:** Medio (hay que crear otro usuario para cambiar de puesto).
- **Pasos:** usuario Vendedor con sede Mayorista (tienda) → cambiar rol a Almacén.
- **Pasó:**
    - rol: «El personal de almacén trabaja en un almacén o una sede mixta; Mayorista es una tienda»;
    - quitar la sede primero: «Un vendedor necesita una sede para trabajar»;
    - el selector de sede solo ofrece las sedes del rol actual, y «Editar datos» solo cambia nombre y correo;
    - los rechazos salen en avisos pasajeros (E-053).
- **Esperado:** cambiar rol y sede en un mismo paso («Cambiar puesto»), validando la combinación final.
- **Lo que funciona bien:** crear con contraseña generada; confirmación con valor anterior y nuevo; desactivar conserva el historial y corta las sesiones.
- **Historial:**
    - 2026-10-19 · Abierto · Claude

### E-057 · La inspección emite un certificado aunque su tipo dice «sin certificado», y certifica un extintor con falla rechazada

- **Rol / pantalla / botón:** Técnico de Campo → Inspecciones → OT-2026-0005 → Finalizar y guardar inspección.
- **Gravedad:** Alto (documento emitido contra la configuración y contra el historial del equipo).
- **Pasó:**
    - el tipo de servicio «Inspección» figura como «Sin certificado» en Gerente → Servicios → Gestionar, pero al finalizar sale «certificado de operatividad emitido para 1 extintor(es)»;
    - el BF-EQ-000003 tenía el manómetro con falla y el cliente rechazó repararlo (OT-2026-0002); en la inspección se marcó todo «Conforme» y se certificó sin ninguna referencia a esa falla;
    - la orden queda «Lista para entregar», aunque la inspección se hizo en el local del cliente y no hay nada que entregar;
    - la lista de equipos ofrece el BF-EQ-000002, dado de baja, y los BF-EQ-000004 y 000005 con ubicación «Planta - Taller», aunque su recojo no se confirmó (E-055).
- **Esperado:**
    - el certificado sale solo si el tipo de servicio lo indica;
    - una deficiencia abierta o rechazada se muestra al técnico y bloquea o advierte antes de certificar;
    - la inspección en sitio termina como «Cerrada» o «Entregada».
- **Historial:**
    - 2026-10-19 · Abierto · Claude

### E-058 · Las pantallas de Campo se ven y se llaman distinto entre sí

- **Rol / pantalla / botón:** Técnico de Campo → Entrega, Mantenimiento e Inspección.
- **Gravedad:** Bajo (consistencia).
- **Pasó:** cada una tiene otra estructura y otros nombres: «CLIENTE & SEDE DE INSPECCIÓN» frente a «CLIENTE RECEPTOR»; «Finalizar y Guardar Inspección de Campo», «Finalizar mantenimiento y generar acta» y «Confirmar Entrega y Cerrar Orden de Servicio»; el mantenimiento lista lo que falta y la inspección no.
- **Esperado:** el mismo esqueleto para las tres (equipos → fotos → conformidad y firma → lo que falta → finalizar), con los textos del sistema de diseño.
- **Historial:**
    - 2026-10-19 · Abierto · Claude

### E-059 · La matriz de permisos no refleja lo que cada rol puede hacer

- **Rol / pantalla / botón:** Gerente → Usuarios y roles → «Matriz de permisos (solo lectura)».
- **Gravedad:** Bajo.
- **Pasó:**
    - para el Gerente lista solo «Productos: crear o editar», pero también crea servicios, tipos de servicio, sedes, usuarios, certificados y firmas;
    - no aparece «Fichas técnicas» para Almacén.
    - El acceso real se controla por rol en las rutas (`role:…`), no por esos permisos, así que la matriz es decorativa y puede engañar.
- **Esperado:** generar la matriz desde lo que de verdad permite cada rol, o quitarla.
- **Historial:**
    - 2026-10-19 · Abierto · Claude

### E-060 · La entrega en mostrador deja entregar sin cobrar y sin firma

- **Rol / pantalla / botón:** Vendedor → OT-2026-0005 → Más acciones → Entrega en mostrador → Registrar entrega conforme.
- **Gravedad:** Medio.
- **Pasó:**
    - aviso «Esta orden todavía no tiene una venta vinculada. Puedes entregarla, pero registra la venta pendiente», y deja entregar: el servicio se entrega sin cobrar y nada obliga a cobrarlo después;
    - no pide firma ni foto, mientras la entrega de Campo sí exige firma con el dedo y conformidad;
    - el error de DNI dice «receptor dni debe tener 8 números» (nombre interno del campo);
    - la opción está escondida en el menú «Más acciones».
- **Esperado:** la misma regla de entrega para Campo y mostrador (firma y conformidad) y, si no hay venta, al menos pedir confirmación del Gerente o dejar la orden en «falta cobrar» visible en el Inicio.
- **Historial:**
    - 2026-10-19 · Abierto · Claude

### E-061 · Caja consolidada muestra las fechas en formato de sistema

- **Rol / pantalla / botón:** Gerente → Caja consolidada → tabla de turnos.
- **Gravedad:** Bajo.
- **Pasó:** apertura y cierre salen como «2026-10-20 01:11:38», mientras el resto del sistema usa «20/10/2026» y «01:11 a. m.».
- **Esperado:** el mismo formato de fecha y hora en todas las pantallas.
- **Historial:**
    - 2026-10-20 · Abierto · Claude
    - 2026-10-20 · También en Almacén → Recepción #2 («Fecha de ingreso: 2026-10-20») · Claude

### E-062 · Un servicio hecho en el local del cliente queda «Listo para entregar»

- **Rol / pantalla / botón:** Técnico de Campo → Mantenimiento o Inspección en sitio → Finalizar; luego Vendedor → Inicio → Avisos de servicios.
- **Gravedad:** Medio.
- **Pasó:**
    - OT-2026-0003 (mantenimiento en sitio, con firma del cliente) quedó en «listo_entrega»;
    - el vendedor recibe «OT-2026-0003… está listo: coordina la entrega con el cliente», aunque los extintores nunca salieron del local;
    - la orden queda abierta para siempre o se cierra con una «Entrega en mostrador» falsa (así se cerró OT-2026-0005; ver E-060).
- **Causa:** `MaintenanceController.php:206` e `InspectionController.php:255` pasan la orden a `listo_entrega`, el mismo estado del taller.
- **Esperado:** el servicio en sitio que el cliente firma queda «Entregado/Cerrado» (o pasa a «falta cobrar» si no hay venta), sin un paso de entrega.
- **Historial:**
    - 2026-10-20 · Abierto · Claude

### E-063 · Los botones verdes con texto blanco no tienen contraste suficiente

- **Rol / pantalla / botón:** Vendedor → Caja y cobranzas → «Abrir turno de caja» (y otros botones verdes).
- **Gravedad:** Medio (WCAG 2.2 AA 1.4.3; axe-core `color-contrast`, nivel «serious»).
- **Pasó:** blanco sobre `bg-emerald-600` (#009966) da 3.65:1; el mínimo para texto de 13 px es 4.5:1. El mismo par está en `deficiencias-vendedor.tsx`, `gerente/configuracion/empresa.tsx`, `vendedor/cobranzas/index.tsx`, `vendedor/cotizaciones/index.tsx`, `vendedor/ventas/certificado-servicio.tsx` y `vendedor/ventas/show.tsx`. Además es un color suelto, no un token de `DESIGN.md`.
- **Esperado:** un token de «éxito» con contraste AA (por ejemplo, emerald-700 o el verde del sistema de diseño) y usarlo en todos.
- **Historial:**
    - 2026-10-20 · Abierto · Claude

### E-064 · La etiqueta de las pestañas de filtro de Campo no se lee bien

- **Rol / pantalla / botón:** Técnico de Campo → Entregas / Inspecciones / Instalaciones → pestaña activa («Listas», «Todas»).
- **Gravedad:** Bajo (axe-core `color-contrast`, «serious»).
- **Pasó:** la etiqueta usa `opacity-80` sobre el fondo de la pestaña activa y no llega a 4.5:1. Está repetida en `tecnico-campo/entregas/index.tsx`, `inspecciones/index.tsx` e `instalaciones/index.tsx` (y Mantenimientos no la tiene: ver E-058).
- **Esperado:** color de texto con contraste AA, sin opacidad, en un componente común para las cuatro pantallas.
- **Historial:**
    - 2026-10-20 · Abierto · Claude

### E-065 · En tema oscuro la etiqueta roja de Deficiencias no se lee bien

- **Rol / pantalla / botón:** Vendedor → Deficiencias (tema oscuro).
- **Gravedad:** Bajo (axe-core `color-contrast`: 3.95:1, mínimo 4.5:1).
- **Pasó:** `deficiencias-vendedor.tsx:88` y `:205` usan `bg-destructive/10 text-destructive-strong` sin variante oscura (#d15551 sobre #2d1c20). Otros lugares sí agregan `dark:text-red-300`.
- **Esperado:** que el token `destructive-strong` tenga su valor para el tema oscuro en `app.css`, y no un parche por pantalla.
- **Historial:**
    - 2026-10-20 · Abierto · Claude

### E-066 · En celular la cabecera de la orden se sale de la pantalla

- **Rol / pantalla / botón:** Vendedor → Órdenes de servicio → OT-2026-0005, a 375 px (claro y oscuro).
- **Gravedad:** Medio (hay que mover la página de lado para ver las acciones).
- **Pasó:**
    - la cabecera (`HEADER.flex flex-wrap… lg:col-start-1`) mide 746 px y el bloque del título (`flex-[1_1_260px]`) 402 px, así que el contenido principal se desplaza a lo ancho;
    - la fecha sale «Lunes, 19 De Octubre», con mayúscula en «De»;
    - el chip gris de la cabecera tiene un contraste de 4.35:1 (mínimo 4.5:1).
- **Esperado:** cabecera en columna en celular, sin desplazamiento horizontal; fecha «lunes, 19 de octubre» como en el resto del sistema.
- **Historial:**
    - 2026-10-20 · Abierto · Claude
    - 2026-10-20 · Parecido en Gerente → Configuración → Empresa: el campo «Razón social» se pasa 3 px y la página se corre un poco de lado · Claude

### E-067 · El lector de barras usa el resultado de la búsqueda anterior

- **Rol / pantalla / botón:** Vendedor → Nueva venta → «Buscar en el catálogo» con el lector (serie + Enter).
- **Gravedad:** Medio (se pierde un equipo de la venta, o entra otro producto).
- **Pasos:** 1) escanear BF-EQ-000006 → entra bien; 2) escanear una serie que no existe (BF-EQ-999999); 3) escanear enseguida BF-EQ-000007.
- **Pasó:** el paso 3 no agrega nada y no avisa; la serie queda escrita en el buscador. Recién un segundo Enter la agrega.
- **Causa:** `catalog-picker.tsx:150` usa `respuesta` si existe y no está `cargando`, pero esa respuesta es de la búsqueda anterior: el lector da Enter antes de los 200 ms de la pausa del buscador. Si la búsqueda anterior tenía resultados (por ejemplo, se escribió «extintor»), el Enter agrega `items[activo]` de esa lista: un producto equivocado.
- **Esperado:** el Enter consulta siempre el término actual (o comprueba que la respuesta es de ese término) y, si no hay nada, deja el aviso «No hay… que coincidan».
- **Historial:**
    - 2026-10-20 · Abierto · Claude

### E-068 · Armar certificados acepta el mismo número de cliente para dos extintores

- **Rol / pantalla / botón:** Vendedor → VTA-0007 → Certificados → «N° del cliente» → Guardar ajustes.
- **Gravedad:** Medio.
- **Pasó:**
    - con «01» en los dos extintores del mismo grupo, guarda y el certificado OG-2026-0003 lista dos extintores «01» (`EmitirCertificadosRequest.php:30` solo pide texto de hasta 40);
    - con horas de capacitación en 0, el error dice «grupos.0.capacitacion.horas no puede ser menor que 1.» (nombre interno, como E-060);
    - textos «2 extintor(es)» y «2 certificado(s) listos.» (ver E-012).
- **Esperado:** número de cliente único dentro de cada certificado, con el error junto al campo y en español claro («Las horas deben ser al menos 1»).
- **Historial:**
    - 2026-10-20 · Abierto · Claude

### E-069 · El certificado de un extintor nuevo dice que se le hizo mantenimiento y recarga

- **Rol / pantalla / botón:** Vendedor → VTA-0007 (dos extintores PQS nuevos, «Tipo de atención: Nuevo») → OG-2026-0003 → PDF.
- **Gravedad:** Medio (documento que se entrega al cliente y a la inspección).
- **Pasó:** el texto fijo dice que los extintores «han sido sometidos a mantenimiento», que los PQS «son recargados» y que las mangueras de CO₂ pasaron la prueba de conductividad, aunque son unidades nuevas y no hay CO₂. Además lista las tres opciones del resultado (operativo, con observación, no operativo).
- **Esperado (como los certificados de los fabricantes y de los recargadores grandes):** para unidades nuevas, un texto de «extintor nuevo, operativo, con garantía de fábrica»; los párrafos de recarga, conductividad o prueba hidrostática solo cuando aplican al agente o al servicio hecho. Ver también E-057 (inspección).
- **Historial:**
    - 2026-10-20 · Abierto · Claude

### E-070 · El certificado acepta mediciones por debajo del mínimo de la plantilla

- **Rol / pantalla / botón:** Técnico de Campo → Instalación OT-2026-0006 → Mediciones → «Espesor (micras)» = -5 → Registrar.
- **Gravedad:** Medio (el certificado LS-2026-0001 salió con espesor -5).
- **Pasó:** la plantilla «Instalación de Lámina de Seguridad» define la columna Espesor con `minimo: 4`, pero `EmitirCertificadoDeServicio::filas()` (líneas 193-197) solo revisa que sea un número; `minimo` (y `maximo`) de las columnas no se valida en ningún lado, y la pantalla tampoco lo muestra. Los mínimos de las pruebas (checklist) sí se evalúan.
- **Esperado:** validar mínimo y máximo de cada columna con un mensaje claro («El espesor debe ser al menos 4 micras») y mostrar el rango junto al campo.
- **Historial:**
    - 2026-10-20 · Abierto · Claude

### E-071 · La pantalla de instalación habla de extintores aunque se instale otra cosa

- **Rol / pantalla / botón:** Técnico de Campo → Instalaciones → OT-2026-0006 (lámina de seguridad).
- **Gravedad:** Bajo.
- **Pasó:**
    - textos de extintor en un servicio de lámina: «registro de extintores», «0 equipos a instalar», «El cliente valida la instalación física de los extintores y los soportes», «Equipos vinculados al historial del cliente»;
    - las secciones saltan de «1. Ubicación» a «4. Conformidad»;
    - el estado sale «Por recibir» antes de empezar y «Lista para entregar» al terminar (ver E-062);
    - los avisos de error del primer intento siguen en pantalla después de guardar con éxito;
    - una orden cuenta como instalación solo si el **nombre** del servicio contiene «instalac» o «pozo» (`InstallationController.php:40`): si el Gerente cambia el nombre, la orden desaparece de la lista.
- **Esperado:** textos según el tipo de servicio; la instalación definida por el tipo de servicio (como `atencion` en Recarga y Mantenimiento), no por el nombre.
- **Historial:**
    - 2026-10-20 · Abierto · Claude

### E-072 · Una orden se recibe con 0 extintores y el faltante ya no se puede recibir después

- **Rol / pantalla / botón:** Técnico de Planta → OT-2026-0007 → Recibir → desmarcar el único extintor → «Si falta alguno, ¿qué pasó?» → Confirmar recepción.
- **Gravedad:** Medio.
- **Pasó:**
    - sin motivo lo rechaza bien («Falta un equipo: escribe qué pasó…»);
    - con motivo acepta «0 de 1 llegaron»: la orden pasa a «Recibido en planta» y al paso Revisar, que dice «Aún no hay extintores recibidos. Escanéalos en el paso Recibir»;
    - en Recibir ya no hay casillas y «Agregar» queda deshabilitado: si el extintor llega mañana, no hay forma de recibirlo;
    - el extintor que no llegó sigue en «Orden del certificado» y cuenta como «1 de 1 registrados»;
    - el motivo solo queda en el historial interno (`ServiceOrderEvent`): el vendedor no recibe aviso del faltante.
- **Esperado (como el «backorder» de una recepción en Odoo o SAP):** con 0 recibidos no se confirma; con faltantes, los equipos que no llegaron quedan «por recibir» y se pueden recibir después; el vendedor recibe un aviso con el motivo.
- **Historial:**
    - 2026-10-20 · Abierto · Claude

### E-073 · Nota de crédito → corregir comprobante
- Rol / pantalla / botón: Vendedor · Nota de crédito → corregir comprobante
- Gravedad: Crítico
- Pasó: Anular para corregir anulaba certificados y perdía orden y cotización.
- Causa raíz y arreglo: La venta queda en corrección; `CorregirVenta` traslada lo que sigue a la venta nueva.
- Prueba: `CorregirComprobanteTest`
- Historial:
    - 2026-11-01 · Solucionado · Claude

### E-074 · Nota de crédito → stock
- Rol / pantalla / botón: Vendedor · Nota de crédito → stock
- Gravedad: Crítico
- Pasó: Anular sin marcar la casilla descontaba dos veces al rehacer.
- Causa raíz y arreglo: Al anular, los productos siempre regresan; los servicios no mueven stock.
- Prueba: `NoteReasonAuditTest`
- Historial:
    - 2026-11-01 · Solucionado · Claude

### E-075 · Nota de crédito → devolución parcial
- Rol / pantalla / botón: Vendedor · Nota de crédito → devolución parcial
- Gravedad: Crítico
- Pasó: No regresaba stock y dejaba devolver dos veces la misma línea.
- Causa raíz y arreglo: `DevolucionParcial` limita por línea y serie y regresa lo devuelto.
- Prueba: `CorregirComprobanteTest`
- Historial:
    - 2026-11-01 · Solucionado · Claude

### E-076 · Nota de crédito → excedente
- Rol / pantalla / botón: Vendedor · Nota de crédito → excedente
- Gravedad: Crítico
- Pasó: El excedente registraba como devuelta la detracción, que no entró a caja.
- Causa raíz y arreglo: `registrarDevolucion` excluye los pagos de detracción.
- Prueba: `ListasEInicioVendedorTest`
- Historial:
    - 2026-11-01 · Solucionado · Claude

### E-077 · Venta → depósito de detracción
- Rol / pantalla / botón: Vendedor · Venta → depósito de detracción
- Gravedad: Alto
- Pasó: Calcula el saldo sin notas de crédito ni devoluciones.
- Historial:
    - 2026-11-01 · Abierto · Claude
    - 2026-11-01 · Solucionado · Claude: Depósito de detracción validado con `saldoDeVenta` (notas y devoluciones incluidas). Prueba: `CierreErroresNotasTest`.

### E-078 · Nueva venta y cotización → resumen
- Rol / pantalla / botón: Vendedor · Nueva venta y cotización → resumen
- Gravedad: Medio
- Pasó: El IGV en pantalla asume todo gravado; el servidor usa la afectación real.
- Historial:
    - 2026-11-01 · Abierto · Claude
    - 2026-11-01 · Solucionado · Claude: `noGravados` y `desglosarIgv` calculan igual que el comprobante en venta y cotización. Prueba: `CierreErroresNotasTest`.

### E-079 · Certificado de servicio
- Rol / pantalla / botón: Vendedor · Certificado de servicio
- Gravedad: Alto
- Pasó: Valida solo la sede: un vendedor podría entrar al de una venta ajena de su sede.
- Historial:
    - 2026-11-01 · Abierto · Claude
    - 2026-11-01 · Descartado · Claude: Falsa alarma: el controlador ya validaba al vendedor. Se unificó con `ValidaVentaVisible` y queda prueba en `CierreErroresNotasTest`.

### E-080 · Envío SUNAT
- Rol / pantalla / botón: Vendedor · Envío SUNAT
- Gravedad: Bajo
- Pasó: El contador de rechazados omite excepciones y suma todo el historial; una nota en excepción descuenta lo disponible para acreditar.
- Historial:
    - 2026-11-01 · Abierto · Claude
    - 2026-11-01 · Solucionado · Claude: El contador cuenta rechazos y excepciones del periodo. Descontar una nota en excepción se mantiene: se reintenta y SUNAT podría aceptarla. Prueba: `CierreErroresNotasTest`.

### E-081 · Panel → filtro de mes
- Rol / pantalla / botón: Gerente · Panel → filtro de mes
- Gravedad: Bajo
- Pasó: El límite usa UTC: el último día del mes por la noche marca el mes siguiente.
- Historial:
    - 2026-11-01 · Abierto · Claude
    - 2026-11-01 · Solucionado · Claude: El límite sale de `mesActual` del servidor, en hora de Perú. Prueba: `CierreErroresNotasTest`.

## Checklist de la auditoría (todas las pantallas)

> Sale de las rutas del sistema (`php artisan route:list`). Se marca `[x]` cuando la pantalla se probó en el navegador con datos buenos y malos. Lo que falta sigue aquí aunque se pierda el contexto.

**Orden:** flujo del negocio. Gerente (catálogo) → Almacén → Vendedor → Técnico de Planta → Técnico de Campo → Guías → Gerente (control y reportes) → Mi cuenta.

### Gerente (22)

- [x] Productos (nuevo, editar, certificado que lleva)
- [x] Servicios: nuevo (desglose IGV), duplicado, desactivar con confirmación
- [x] Tipos de servicio («Gestionar»): lista con certificado y detracción, nombre obligatorio; servicio SRV-0004 Inspección creado
- [x] Configuración → Certificados (lista, nuevo por bloques)
- [x] Formato fijo OG: título vacío rechazado («Falta completar titulo», sin tilde)
- [x] Configuración → Datos de la empresa: RUC con dígito malo (rechaza; no se guardó)
- [x] Cuentas bancarias: número con letras y CCI corto rechazados; logo que no es imagen no se carga
- [x] Vista previa de la factura (PDF)
- [x] Configuración → Firmas y sellos (carga; faltan las 5 firmas)
- [x] Sedes: lista y relaciones, nueva vacía
- [x] Usuarios y roles: lista, correo repetido, contraseña corta, no desactivarse a sí mismo
- [x] Usuarios: crear válido (clave generada), cambiar rol (bloqueo E-056), desactivar
- [x] Usuarios: matriz de permisos (E-059)
- [x] Dashboard: refleja la venta (ver E-021)
- [x] Dashboard: detalle por tipo (`?tipo=ventas`) y reporte PDF
- [x] Dashboard: filtro de mes (el de sede solo aparece con varias tiendas); aviso «las tareas automáticas no están corriendo»
- [x] Recompra e IA (sin puntuaciones todavía; ver E-038)
- [x] Caja consolidada: muestra el faltante del vendedor, aviso en rojo
- [x] Cobranzas (carga; sin cuotas)
- [x] Auditoría: registra producto, servicio, caja, venta, certificado y ficha (ver E-019)
- [x] Reportes: reporte del mes en PDF (2 páginas, coincide con el Dashboard; ver E-021)
- [x] Consulta de cliente, venta y orden (enlazadas desde Auditoría)
- [x] Códigos de producto SUNAT (es un buscador JSON, no una pantalla)
- [x] Transporte: bloqueado a propósito (ver E-018)
- [x] Chispa (abre; no se le enviaron preguntas para no usar el servicio de IA)

### Almacén (11)

- [x] Dashboard: cuadra 20 entradas y 20 salidas, Kardex
- [x] Stock y Kardex (carga)
- [x] Recepciones: nueva y detalle
- [x] Recepciones: editar (bajar debajo de lo vendido → rechazado; subir a 30 → stock 10), stickers rollo y A4
- [x] Fichas técnicas
- [x] Stickers de barras (carga)
- [x] Ajustes de stock: baja mayor al stock → «Solo hay 0…»
- [ ] Traslados (solo con varios almacenes)
- [x] Consulta rápida: BF-EQ de cliente no se encuentra (E-033)

### Vendedor (33)

- [x] Dashboard: ventas del mes cuadran (2,865 − NC 35 + ND 5 = 2,835), cartera S/ 65, accesos rápidos; plurales «1 cuotas vencidas», «hace 1 días» (E-012); aviso de entrega de un servicio en sitio (E-062)
- [x] Clientes: lista y agregar
- [x] Clientes: ficha, editar (teléfono y correo malos), agregar sede (aparece en la Referencia de la venta), exportar a Excel
- [x] Por vencer (vacío)
- [x] Cotizaciones: guardar vacía, descuento mayor a la línea, válida, pasar a venta con crédito
- [x] Cotizaciones: editar (carga), PDF, agregar número inválido; enviar, aceptar y rechazar no se pueden sin WhatsApp (E-046)
- [x] Ventas: nueva (factura rechazada, nota de venta), detalle
- [x] Ventas: boleta a Clientes varios enviada a SUNAT beta y **aceptada** (B001-1); boleta a RUC no verificado rechazada
- [x] Factura a RUC habido (CLI-0003, entidad pública) → editar por enviar → F001-1 aceptada; NC parcial FC01-1 y ND FD01-1 aceptadas; baja con notas rechazada por el sistema; detracción > S/ 700 bloqueada hasta clasificar
- [x] Factura a crédito F001-3 con 2 cuotas aceptada; cobro de cuota (más del saldo, efectivo sin caja, Yape parcial); escaneo por código PRD (suma la cantidad); anular nota de venta (E-044)
- [x] Escanear serie BF-EQ de un extintor nuevo en venta: recepción con 2 series (serie repetida rechazada; marca nueva desde el selector), venta VTA-0007 escaneando BF-EQ-000006 y 000007, serie repetida «no se repitieron», serie inexistente y lector rápido (E-067)
- [x] Ventas → certificados: operatividad OG-2026-0003 sale sola; armar con capacitación (horas 0 rechazada; E-068), número de cliente repetido aceptado (E-068), capacitación BF-UME-2026-0001; PDF y Word descargan; texto de unidades nuevas (E-069)
- [x] Caja: abrir (fondo negativo y válido), salida mayor al efectivo, cerrar con faltante sin y con explicación
- [x] Caja: cobro en efectivo dentro del turno (cuota 1 de VTA-0006), cierre con sobrante S/ 5 y observación; el Gerente lo ve (E-011, E-061)
- [x] Cobranzas (cuotas): ver Caja y cobranzas
- [x] Servicios: nueva orden (vacía, fecha pasada, válida)
- [x] Constancia de recepción PDF (OT-2)
- [x] Entrega en mostrador (OT-2026-0005; E-060)
- [x] Certificados: lista (une cliente, venta y NV)
- [x] Certificados: detalle (PDF, Word, QR), corregir (E-051); no hay «anular» para el vendedor
- [x] Envío SUNAT: lista vacía
- [x] Envío SUNAT: descarga masiva ZIP y CSV para Excel (ver E-052)
- [x] Deficiencias: notificación, aprobar (sin persona, con importe sin cotización, sin importe)
- [x] Deficiencias: rechazar (manómetro), cotizar adicional (válvula) y aprobar con cotización (S/ 35)
- [x] Chat de la orden: el mensaje del vendedor queda en la línea de tiempo de la orden

### Técnico de Planta (6)

- [x] Orden de taller completa: tomar, recibir a mano (año futuro), revisar con falla y foto obligatoria, hallazgo con permiso, reparar sin repuesto, cerrar con certificado, lista para entrega
- [x] Planta (OT-2026-0002): extintor no cotizado, extintor no apto (E-047), dos fallas con foto, reparar con repuesto, cierre trabado (E-049)
- [x] Planta: listas de recepciones y hallazgos (cargan), stickers rollo y A4, búsqueda de equipo (E-050)
- [x] Planta: faltantes en OT-2026-0007 (sin motivo rechazado, extintor no cotizado rechazado, 0 de 1 aceptado: E-072)

### Técnico de Campo (15)

- [x] Entrega: asignación desde ventas, DNI malo, sin conformidad, firma con el dedo, cierre y acta PDF
- [x] Recojo: validaciones (lugar obligatorio, cantidad mínima 1)
- [x] Mantenimiento en sitio OT-2026-0003: tomar, agregar equipo (dado de baja; E-054), checklist de 14 puntos, fotos, firma, finalizar
- [x] Recojo OT-2026-0004: cliente, 2 extintores (uno con datos y otro «Planta completará»), foto, firma; no se puede confirmar (E-055)
- [x] Inspección OT-2026-0005 completa (E-057); ficha y búsqueda de cliente de Campo; constancia del recojo PDF
- [x] Instalaciones: el Gerente crea SRV-0005 «Instalación de lámina de seguridad (por m²)»; el vendedor crea OT-2026-0006 para Campo; Campo registra con área en texto (rechazada), sin foto (rechazada), sin firma (botón deshabilitado) y espesor -5 (aceptado: E-070); certificado LS-2026-0001 (E-071)

### Guías de remisión (4)

- [x] Guías: lista vacía; «Emitir guía» solo aparece en ventas con factura o boleta que tengan productos (por diseño)
- [x] Guía T001-1 desde la factura F001-2 (datos malos y buenos, vehículo y conductor nuevos); envío simulado en local
- [ ] Guía: envío real a SUNAT (requiere credenciales GRE de producción); traslado entre sedes (no aplica: hay un solo almacén)

### Mi cuenta (3)

- [x] Perfil (vacío y correo malo), Seguridad (confirmar clave, contraseña actual mala y confirmación distinta) — ver E-037
- [x] Apariencia: claro, oscuro y sistema (se guarda; pestañas con aria-selected); dos pasos: pide confirmar la contraseña, QR, código malo (E-037), cerrar sin confirmar no deja la cuenta a medias
- [ ] Llave de acceso (passkey): necesita un dispositivo real con huella o llave; no se puede en el navegador integrado

### Transversal

- [x] Celular (375 px) y oscuro: Almacén (inicio, fichas), Vendedor (inicio, clientes, nueva venta, orden), Planta (inicio), Gerente (inicio, editor): sin desborde horizontal (ver E-034)
- [x] Celular (375 px), claro y oscuro, en las 50 pantallas de lista y formulario de los 5 roles y Guías: sin desborde salvo detalle de orden y Empresa (E-066); contraste E-063, E-064, E-065
- [x] axe-core WCAG 2.2 AA en 14 pantallas principales: 0 violaciones, salvo E-035
- [x] axe-core en el resto de pantallas (Almacén 6, Gerente 16, Planta 2, Campo 7, Vendedor 6) y en diálogos (agregar cliente, abrir turno, registrar pago, comunicar baja): solo E-063 y E-064
- [x] Permisos: Técnico de Campo contra 11 pantallas de otros roles → 403 en todas, con página de error clara
- [x] Permisos: Vendedor, Almacén y Planta contra pantallas y acciones (POST, PUT, PATCH) de otros roles → 403 en todas, incluido cambiarse el rol a Gerente
- [x] Verificación pública por QR: token real (válido y vigente, con PDF), token falso (404), sin iniciar sesión

## Flujos probados de punta a punta

1. **Producto con certificado:** Gerente crea la plantilla desde el producto → Almacén llena la ficha y recibe 20 → Vendedor vende (NV-0001) → certificado GM-2026-000001 automático con la referencia. Funciona.
2. **Servicio en taller y entrega:** Vendedor crea OT-2026-0001 → Planta recibe, revisa, reporta la falla con foto → Vendedor autoriza (persona, canal y fecha) → Planta repara y cierra → certificado OG-2026-0001 → Vendedor cobra desde la orden (VTA-0002/NV-0002) unida al certificado → Campo entrega con firma y la orden se cierra con acta. Funciona, con E-023, E-024 y E-026 a E-030.
3. **Boleta electrónica:** venta a Clientes varios → B001-1 «por enviar», editable hasta el envío automático → «Enviar ya» → **aceptada por SUNAT beta**.
4. **Factura y notas:** F001-1 a la SUNAT (RUC habido) → editada antes de enviar → aceptada → NC parcial FC01-1 y ND FD01-1 aceptadas.
5. **Escaneo y guía:** escanear PRD-0002 dos veces → cantidad 2 → factura F001-2 aceptada → certificado GM-2026-000002 automático → guía T001-1 (simulada en local).
6. **Caja:** apertura → salida rechazada → cierre con faltante → el Gerente lo ve en Caja consolidada. Funciona, con E-011.
7. **Caja con sobrante:** apertura S/ 50 → cobro en efectivo de una cuota (S/ 30) → cierre contando S/ 85 → pide la explicación → el Gerente ve «Sobrante S/ 5.00». Funciona, con E-011 y E-061.
8. **Extintor nuevo con serie:** Almacén recibe 2 extintores (marca nueva desde el selector) → BF-EQ-000006 y 000007 → venta VTA-0007 escaneando las series → operatividad OG-2026-0003 sale sola → armar certificados con capacitación BF-UME-2026-0001. Funciona, con E-067 a E-069.
9. **Instalación:** el Gerente crea el servicio → el vendedor crea OT-2026-0006 para Campo → Campo registra mediciones, fotos y firma → certificado LS-2026-0001. Funciona, con E-062, E-070 y E-071.
10. **Faltantes en taller:** OT-2026-0007 → Planta recibe 0 de 1 con motivo. Funciona a medias: E-072.

## Cobertura de la auditoría

| Rol      | Pantalla                                                                                    | Revisado             | Notas                                                        |
| -------- | ------------------------------------------------------------------------------------------- | -------------------- | ------------------------------------------------------------ |
| Vendedor | Clientes: lista, agregar (vacío, RUC malo, RUC válido inexistente, duplicado)               | 2026-10-19           | Validaciones correctas; ver E-002 a E-005                    |
| Vendedor | Nueva venta: emitir sin cliente, elegir cliente                                             | 2026-10-19 (parcial) | Falta catálogo y stock para seguir; ver E-006                |
| Gerente  | Productos: nuevo (vacío, precio negativo, válido), editar, «Crear certificado nuevo»        | 2026-10-19           | Validaciones correctas; ver E-007                            |
| Gerente  | Certificados: crear «Garantía de mangueras» por bloques ligada a producto                   | 2026-10-19           | Correcto tras E-007                                          |
| Almacén  | Fichas técnicas: presión con letras, ficha válida                                           | 2026-10-19           | Correcto                                                     |
| Almacén  | Recepciones: vacía, sin ítems, costo negativo, conforme > recibido, válida (20 mangueras)   | 2026-10-19           | Correcto                                                     |
| Vendedor | Nueva venta: más que el stock, factura a RUC no verificado, nota de venta por transferencia | 2026-10-19           | El certificado sale solo (GM-2026-000001); ver E-008 a E-010 |

## Datos del negocio pendientes (no son errores del sistema)

- Datos de la empresa: logo, teléfono, correo, cuenta de detracción del Banco de la Nación y cuentas bancarias, que hoy están vacíos y salen en comprobantes y certificados.
- Código de establecimiento SUNAT de cada sede (ver E-016).
- Credenciales GRE de SUNAT para enviar guías de verdad (en local se simulan).
- Peso (kg) de cada producto, para que la guía lo calcule sola.
- Detracción de cada tipo de servicio: todos dicen «por definir» (lo confirma la contadora). **Mientras tanto no se puede facturar ningún servicio mayor a S/ 700** (el sistema lo bloquea, como debe).
- Fotos de las firmas: faltan las 5 (Firmas y sellos).

## Decisiones aprobadas (2026-10-23)

> Las tomó el usuario después de comparar con sistemas grandes. Cada fase vuelve a investigar el tema antes de programar (ver PLAN.md).

**Dinero y SUNAT**

- **E-011 · Caja:** cierre ciego con planilla por billetes (S/ 200, 100, 50, 20, 10) y monedas (S/ 5, 2, 1, 0.50, 0.20, 0.10); solo se cuenta el efectivo. El vendedor no ve el esperado ni la diferencia: si la hay, explica sin cambiar el conteo. El Gerente ve esperado, contado, diferencia y detalle; un reconteo lo autoriza él y se guardan los dos. Fuente: [OCA POS Blind Session Closing](https://apps.odoo.com/apps/modules/18.0/pos_blind_session_closing); [conteo por denominación en Odoo 17](https://cybrosys.com/blog/how-to-manage-opening-and-closing-controls-in-odoo-17-pos).
- **E-021 · Dashboard:** una sola pantalla, sin pestañas: total arriba y dos bloques lado a lado, **Productos** (ventas sin IGV − costo de compra) y **Servicios** (ventas sin IGV − repuestos usados). «Nuevo» o «—» cuando el mes anterior es 0.
- **E-044 · Comprobante aceptado por SUNAT:** el vendedor ve dos botones; por dentro se emite la nota de crédito.
    - **Corregir** (error de datos): el cliente se queda con lo comprado, el stock no se mueve, el pago y las series pasan solos al comprobante nuevo. Fuente: [Odoo «Revertir y crear factura»](https://www.odoo.com/documentation/master/applications/finance/accounting/customer_invoices/credit_notes.html).
    - **Anular** (devolución total o parcial): se marcan o escanean las series que regresan (solo las de esa venta), vuelven al stock y el dinero queda por devolver o como saldo a favor.
    - La nota de venta interna se anula con motivo obligatorio; el pago queda por devolver y la orden vuelve a «falta cobrar».
- **E-040 · Editar antes de enviar:** al cambiar el total, preguntar «¿El cliente pagó el nuevo total?». Sí: se corrige el pago (queda quién y el monto anterior; si es efectivo, ajusta la caja del turno). No: la diferencia queda por cobrar. Nunca un pago inventado.
- **E-042 · Factura ↔ boleta:** una vez numerado no cambia de tipo; se anula y se crea otro.
- **E-052 · ND por mora:** la afectación depende del motivo; los intereses moratorios van inafectos (RTF 00214-5-2000, citada por [Noticiero Contable](https://noticierocontable.com/intereses-compensatorio-moratorio)). Confirma la contadora.
- **Detracción:** obligatoria al crear el tipo de servicio (código y porcentaje). Recarga = «Otros servicios empresariales», Informe 017-2006 ([SUNAT](https://orientacion.sunat.gob.pe/consultas-sunat-detracciones-empresas)).

- **Toda venta exige turno de caja abierto** (decidido el 2026-10-23, como el punto de venta de Odoo): sin turno no se emite, se pague en efectivo, Yape, transferencia o a crédito; el borrador sí se guarda. Hecho: `CashRegister::exigirAbiertaParaVender` en las tres entradas de emisión de `SaleController` y aviso en Nueva venta; prueba `VentaExigeCajaAbiertaTest`.

**Órdenes y entregas**

- **E-060 · Entrega en tienda:** al terminar el taller, la orden queda «Por recoger». Botón «Entregar al cliente»: DNI y nombre de quien recoge; si falta cobrar, primero se cobra (sin pago solo con autorización del Gerente); imprime el acta para firmar en papel; la orden queda «Entregada». Fuente: [Odoo Repairs](https://odoo.com/documentation/18.0/applications/inventory_and_mrp/repairs/repair_orders.html) (facturar → cobrar → entregar).
- **E-062:** el servicio en sitio con firma queda «Cerrado» si tiene venta y «Falta cobrar» si no.
- **E-049:** sin certificado para el equipo no reparado, con constancia de «no certificable»; botón «Ofrecer extintor nuevo» (cotización). Si el cliente deja el extintor, se desecha con constancia de baja.
- **E-072:** lo que falta queda «por recibir», se recibe después y el vendedor recibe aviso (backorder).
- **E-057 · Inspección:** genera el informe en tabla (como la hoja de la empresa) y el certificado. Se registra, certifica y cobra **lo encontrado** (si cotizaron 20 y hay 18, son 18) y el vendedor recibe aviso de la diferencia. Fuente: software del rubro ([BuildOps](https://buildops.com/resources/fire-extinguisher-inspection-app), [ServiceTrade](https://servicetrade.com/?p=1130)).
- **E-054 · Checklist único** para Planta y Campo: Manómetro (N.A. automático en CO₂), Pasador y precinto, Manguera y boquilla, Válvula, Cilindro, Tarjeta o etiqueta, Gabinete o soporte (solo si el equipo tiene gabinete). Estados Conforme / Necesita cambio / No aplica; todo empieza en Conforme y lo malo exige foto. Planta agrega agente y peso. Debajo: marca, año de fabricación, próxima recarga, vencimiento de PH y observación.

**Certificados**

- **E-024 · Fechas:** el técnico registra la fecha de la última PH grabada en el cilindro; próxima PH = +5 años para **todos** los extintores (política de la empresa, más estricta que NFPA 10; valor único configurable por el Gerente) y editable. Próxima recarga = fecha del servicio + 12 meses, editable. Sin fecha de venta para equipos que no vendió la empresa; año de fabricación no futuro.
- **E-069:** el texto sale según el origen: venta → nuevo; recarga → recarga; mantenimiento → mantenimiento. Párrafos de CO₂ y PH solo cuando aplican.
- **Vehículos:** cada vehículo se va con sus certificados (operatividad y PH) con su placa.
- **Nombre comercial:** opción por cliente «En el certificado usar el nombre comercial», que reemplaza a la razón social en el certificado; el RUC se mantiene. La factura siempre lleva la razón social.

**Clientes**

- Al crear el cliente: casillas «Tiene otras sedes» y «Tiene vehículos» para agregarlos ahí mismo (hoy solo desde la ficha). La consulta de RUC no devuelve anexos: se registran a mano.
- El buscador encuentra al cliente también por el nombre o la dirección de una sede y deja elegir la sede; de ella salen la dirección de la venta, el certificado y el acta (E-030).

**Reglas pequeñas:** E-015 no se repite el nombre entre activos · E-022 fecha pasada pide confirmación · E-028 aprobar sin importe con aviso «sin costo para el cliente» · E-036 el técnico ve sus órdenes cerradas en solo lectura · E-059 matriz generada desde los permisos reales · E-018 se borran las rutas de Transporte.

**Para el final:** E-016 (código de establecimiento de la ficha RUC), tasas de detracción e IGV de la ND con la contadora, credenciales GRE (el sistema sigue en demo).

## Mejoras propuestas (requieren aprobación)

_(vacío)_
