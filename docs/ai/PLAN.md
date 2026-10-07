# PLAN: Sistema web para la gestión de ventas en BRUCE FIRE S.A.C.

**Estado (2026-10-06):** auditoría completa terminada (`AUDITORIA.md` y `SUNAT.md`). **El dueño pidió no cambiar código hasta revisar el reporte.** Se trabaja en SUNAT **beta**; producción, al final (fase G).

Los IDs (S#, C#, V#, T#, A#) remiten a `AUDITORIA.md` y `SUNAT.md`. ⚠️ **DRS** = alcance nuevo: antes de construirlo hay que agregar su REQ al DRS v3.1, su historia en el Product Backlog y su fila en la Matriz de Consistencia.

## Ya hecho (resumen)

- **Roles:** los 5 roles construidos con su recorrido principal: venta → SUNAT beta → certificado → orden → taller → campo → acta.
- **Auditoría del 2026-10-05:**
    - PDF de las notas igual a su XML.
    - Numeración de series sin choques.
    - Traslados sin almacén muestran un aviso en vez de romperse.
    - Se quitó "Eliminar cuenta".
    - Prueba de que el historial no se puede borrar.
    - Páginas de error con Chispa.
    - Subido en el PR #23 (abierto, sin fusionar).
- **2026-10-06:**
    - 2 enlaces 404 del técnico de planta, corregidos.
    - Auditoría por roles hecha en equipo (Claude, GPT y Antigravity).
    - Guías SUNAT convertidas en `documentos/sunat/`.
    - Normativa vigente en `SUNAT.md`.
    - Documentación unificada.

## Pendiente: sistema (en este orden)

### A. Facturación lista para pasar a producción

- [x] S1: elegir el servidor beta o producción según `SUNAT_BETA` y verificar el certificado TLS (envío propio con `verify_peer`, sin `See`)
- [x] S2: bloquear las NC con motivo 04, 05 u 08 sobre boletas
- [x] S3: ND motivo 13 para penalidades, inafecto; el 03 pasa a "otros conceptos" (R.S. 000048-2026)
- [x] S4: tipo de afectación IGV (catálogo 07) en productos, servicios y líneas de venta, con su select en el catálogo del Gerente (Codex). _Falta correr la suite completa._
- [x] S5: guardar la hora de emisión y usar la misma en el XML y en el PDF
- [x] S6: guardar la fecha de la NC y la ND al crearla y reutilizarla en los reintentos
- [x] S9: catálogo 09 completo
- [x] S10: las notas usan la afectación del comprobante original (`DesgloseNota`); los casos mixtos o los motivos 11, 12 y 13 sin datos se bloquean con un mensaje claro (Codex). _Falta correr la suite completa._
- [x] S8: aviso en Facturación del vendedor y en el dashboard del Gerente (vence hoy o mañana, y vencidos), con lista filtrada
- [x] S7: comunicación de baja real (RA para serie F, RC estado 3 para serie B, ticket y consulta), solo no entregados y dentro de 7 días desde el CDR
- [x] S12: se guarda una copia de los datos de emisión (cliente y líneas) en `datos_emision`, y el PDF y la baja la leen (Codex). _Falta correr la suite completa._
- [x] S13: no se acepta nada sin un CDR válido
- [x] V2 y S17: permisos Spatie en rutas y FormRequest; la NC y la ND del vendedor esperan la aprobación del Gerente

### A2. Seguridad (antes de producción)

- [x] X1: scope `visiblePara(User)` en `Sale`, `ServiceOrder`, `Certificate` y `Equipment` (lo usan `AcotaPorSede` y los controladores de ventas, órdenes, certificados y la ficha del cliente). _Pruebas escritas; sin suite completa (MySQL apagado)._
- [x] X2: firmas, sellos y fotos de capacitación en el disco privado; la firma se ve por una ruta solo del Gerente; migración que mueve los archivos. _Pruebas escritas; sin suite completa (MySQL apagado)._
- [x] X3: `must_change_password` y middleware `ExigirSeguridadDeLaCuenta` (contraseña inicial y 2FA del Gerente, interruptor `SEGURIDAD_EXIGIR_2FA_GERENTE`). _Pruebas escritas; sin suite completa (MySQL apagado)._
- [x] X4 y X5: bloque de producción en `.env.example`, `TRUSTED_PROXIES`, HSTS por https y CSP con nonce (en modo solo reporte). _Pruebas escritas; sin suite completa (MySQL apagado)._

### B. Certificados y tipo de extintor

- [x] C1: agente (lista `EquipmentType`, con PQS BC) y capacidad en el producto, copiados a la unidad y al equipo; técnicos eligen de la lista; sin agente no se emite. _Pruebas escritas; sin suite completa (MySQL apagado)._
- [x] C2: al cobrar solo sale operatividad de extintores nuevos; P.H. y capacitación quedan pendientes y la P.H. exige fecha, presión, tiempo y resultado reales. _Pruebas escritas; sin suite completa (MySQL apagado)._
- [x] Una sola regla de certificados: `EmitirCertificadosDeVenta::tiposPorDestino`; se borró `CertificateRuleEngine` (sin uso) y su prueba unitaria.
- [x] A4: el servicio elige su tipo de certificado en el formulario del Gerente. _Pruebas escritas; sin suite completa (MySQL apagado)._

### C. Ventas, caja y cobranzas

- [x] V1: línea serializada siempre con cantidad 1 _Código y pruebas escritas; sin suite completa (MySQL apagado)._
- [x] V3: la cotización pasa completa a la venta (descuento, condición de pago, observaciones, vehículo) _Código y pruebas escritas; sin suite completa (MySQL apagado)._
- [x] V4 y S11: las notas aceptadas cambian el saldo por cobrar _Código y pruebas escritas; sin suite completa (MySQL apagado)._
- [x] V5: renovar el vencimiento del equipo solo al cerrar el trabajo técnico _Código y pruebas escritas; sin suite completa (MySQL apagado)._
- [x] V6: un adicional autorizado genera deuda real _Código y pruebas escritas; sin suite completa (MySQL apagado)._
- [x] V7: pago ligado al turno de caja; bloqueo contra doble apertura o confirmación _Código y pruebas escritas; sin suite completa (MySQL apagado)._
- [x] V8: la ficha del cliente muestra al vendedor solo sus ventas, cotizaciones y certificados (§90.1); hecho con X1. _Pruebas escritas; sin suite completa (MySQL apagado)._

- [x] X6: la tarea diaria no debe vencer cotizaciones "aceptadas" _Código y pruebas escritas; sin suite completa (MySQL apagado)._
- [x] X7: medir los KPI del proyecto (tiempo de venta, tiempo de cotización, clientes recuperados por alertas: la cotización guarda la alerta de origen) _Código y pruebas escritas; sin suite completa (MySQL apagado)._
- [x] X8 y X9: alertas filtradas por sede o vendedor, con registro de contacto; "ofrecer recarga" según la capacidad y el agente _Código y pruebas escritas; sin suite completa (MySQL apagado)._

### D. Técnicos ⚠️ DRS

- [x] Evidencias únicas (§33): foto, audio o archivo por orden, equipo y etapa (tabla `evidencias`, disco privado, ruta con sesión)
- [x] Conversación de la orden sobre `ServiceOrderEvent`: todos escriben y adjuntan (T3)
- [x] Firma táctil en recojo, entrega, instalación, inspección y mantenimiento (T1); impresa en el acta y en la constancia de recojo
- [x] Mantenimiento en sitio (T2) con checklist por extintor, fotos antes y después, firma y acta. ⚠️ El motor único queda parcial: recojo, entrega, instalación e inspección siguen con su pantalla; comparten componentes (firma, fotos, conversación, checklist). Falta: estados "en camino" y "en sitio" y checklist por plantilla.
- [x] Instalación escaneando cada unidad vendida (T4); deficiencia fuera del checklist con foto (T5)

### E. Almacén y Gerente

- [x] A2: tabla de categorías y botón "Gestionar" junto al select; categoría también en servicios ⚠️ DRS
- [x] A3: unidad de medida del servicio con el catálogo SUNAT 03; rechazar unidades desconocidas
- [x] A5: no permitir dar de baja dos veces la misma unidad
- [x] A6: arreglar los botones sin acción (nueva recepción y anular pago)
- [x] A1: costo de compra en la recepción y valorización correcta del inventario ⚠️ DRS
- [x] Stickers de 5 × 5 cm, 20 por hoja, con opción de empezar en la posición N (cambia el REQ-INV-07)
- [x] A7: paginar en la base de datos; quitar el N+1 de Consulta Rápida

### F. Interfaz y nombres — al final, con prototipos aprobados antes (decisión del dueño, 2026-10-06)

- [ ] Nombres: "Cajas", "Inventario" y grupo "Empresa"; cada tarjeta del dashboard enlaza a su reporte
- [ ] Menús con rutas Wayfinder (las URL armadas a mano causaron los 404)
- [ ] Contraste de las opciones del select en modo oscuro
- [ ] Sistema de diseño: `AppleSegmentedTabs`, `AppleKpiCard`, `AppleTableCard`, Chispa sin tapar botones en celular, `currentSede` compartido. **Sin tocar `.bf-nav-active`.**

- [ ] X13: un `AlertDialog` común en lugar de los 7 `confirm()` nativos
- [ ] X14 y X15: componente de tabla común que en el celular se vea como tarjetas; partir las páginas de más de 900 líneas (empezar por `clientes/show.tsx`) y usar _deferred props_
- [ ] X16 a X18: tokens de tipografía (texto mínimo legible), `StatusBadge` y `Kpi` comunes, `alt` y `htmlFor` que falten, Chispa animada más liviana

### G. Servidor y paso a producción (al final)

- [ ] Checklist de `SUNAT.md` §4: todos los casos probados en beta con su CDR aceptado
- [ ] Cron `schedule:run` cada minuto y worker de colas supervisado
- [ ] Respaldo fuera del servidor, incluidos los XML y los CDR
- [ ] `trustProxies` según el hosting y `SESSION_SECURE_COOKIE=true`
- [ ] Certificado digital de producción, usuario SOL, datos reales de la empresa y cuenta de detracción

### H. Guía de remisión electrónica (obligatoria) ⚠️ DRS

Diseño en `SUNAT.md` §5.

- [ ] **Ya, sin código:** emitir las GRE desde el portal SOL o la app Emprender mientras el sistema no las tenga
- [x] Actualizar Greenter de **v4.3.1 (2021) a v5.3** y agregar `greenter/gre-api` (autorizado por el dueño). PHPStan no encontró cambios que afecten a la facturación; falta correr la suite de facturación con MySQL
- [ ] Credenciales de la API (`client_id` y `client_secret`) desde Menú SOL (van en `.env`: `SUNAT_GRE_CLIENT_ID` y `SUNAT_GRE_CLIENT_SECRET`); el ambiente de pruebas por defecto es `gre-test.nubefact.com` (`SUNAT_GRE_AUTH_HOST` y `SUNAT_GRE_API_HOST`): confirmarlo con una guía real de prueba
- [x] Datos maestros: vehículos (placa y categoría M1, L o N), conductores, peso por producto y código de establecimiento por sede
- [x] (código hecho, sin probar contra SUNAT ni con MySQL) Módulo único "Guías de remisión": emitir desde la venta (motivo 01), desde la orden de recojo o entrega (motivo 13, por confirmar) y desde el traslado entre sedes (04, con "en tránsito", A8); CDR aceptado antes de salir

## Decisiones pendientes del dueño o del contador

1. ¿La P.H. y la capacitación se hacen de verdad en cada venta, o un técnico debe validarlas? (C2)
2. ¿Venden algo exonerado o inafecto? ¿Qué código de detracción les corresponde? (S4, S14)
3. ¿Cobran penalidades o mora? (S3)
4. ¿Con qué vehículo transportan (auto, moto o camioneta)? ¿Dónde emiten hoy la guía de remisión? ¿Qué motivo usan para el recojo y la devolución de los extintores en recarga? (H)
5. ¿Quieren registrar el costo de compra? (A1)
6. ¿Prestan extintores mientras recargan los del cliente? ¿Los técnicos trabajan seguido sin señal?

---

## Contenido y redes (Bruce Fire) [EN CURSO]

- Carpeta de contenido: `../Contenido/` (PERFIL, GANCHOS, CALENDARIO, PUBLICADO, piezas).
- [x] Perfil de marca, banco de ganchos y guion del video de presentación (`piezas/2026-10-05-video-presentacion/guion.md`).
- [ ] Aprobar el guion y resolver los pendientes del cliente (WhatsApp, recojo y entrega, ASNEEX).
- [x] Video armado en HyperFrames (`../Contenido/piezas/2026-10-05-video-presentacion/video/`, 38 s, 9:16), con imágenes provisionales; `hyperframes check` pasa.
- [x] Video v2 final 9:16 renderizado (Chispa ilustrada, voz Jair Solano, música Eleven Music, 3 clips de Veo).
- [ ] Versión 1:1 para LinkedIn y captions por red.
- [ ] Crear los perfiles en TikTok, Instagram, Facebook, YouTube, LinkedIn y Google.

## Entregables académicos Semana 8 Sprint 7

**Estado al 07/10/2026:** métricas versión 1.2 corregidas a cinco VI y cinco VD; Informe Final y matriz concordantes. Los instrumentos requieren actualización y son el siguiente paso; informe de estado, Scrum y demostración pendientes. Periodo 02/10–09/10, entrega el 09/10. El usuario confirmó este repositorio y pidió conservar toda la memoria.

Detalle y fuentes en Obsidian: `D:/TiomiguelonGgs/Documents/Recuerda/Cerebro/Documentación de Bruce Fire SAC/27 - Plan de entregables Semana 8 Sprint 7.md`.

- [x] Revisar plantillas, matriz, informe final, Scrum anterior, Obsidian y cronograma.
- [x] Guardar el plan y los hallazgos de la conversación.
- [x] Métricas: exactamente cinco VI (PRI, PCF, PEU, SUS y TRP) y cinco VD; ocho campos completos, unidades, fórmulas y criterios concordantes.
- [ ] Instrumentos: actualizar los anteriores formatos A1–A13 al diseño de ocho instrumentos principales I01–I08; añadir fichas técnicas, registros y relación con las diez métricas. El inventario de 122 REQ existente sirve de base.
- [ ] Scrum Sprint 7: periodo completo 02/10–09/10, códigos EDT y estados sustentados.
- [ ] Informe Sprint 7: concordar con Scrum, recalcular anexos, porcentajes y semáforos. No copiar AC ni CPI sin sustento.
- [ ] Verificar Word/Excel y coherencia cruzada antes de entregar.
- [ ] Después revisar el sistema y las pruebas pendientes; no dar por validado lo implementado.

**Siguiente paso documental:** preparar los instrumentos de medición conforme a las diez métricas corregidas; después continuar informe de estado, Scrum y demostración. Planes de pruebas Funcionales y Unitarias quedan como referencia pendiente de alcance; el pedido de guardar memoria no confirma incorporarlos. No confundir fechas planificadas con terminación real. Este bloque no cambia las prioridades ni el estado de las fases técnicas anteriores.

### Investigación oficial de Semana 8 completada 07/10/2026

- [x] Contrastar PMBOK/PMI, ISO y Scrum con las plantillas y los documentos actuales, sin editar entregables.
- [x] Conectar Obsidian en lectura y confirmar Semana 8 = Sprint 7, 02/10–09/10.
- [x] Revisar 3.7, 3.9 y 3.10: tres indicadores VI y cinco VD; propuesta de cinco métricas con dos complementarias y SUS conservado.
- [x] Verificar PV S7 = S/ 890.59 en Curva S actual, respetando la distribución especial de actas; no hay EV/AC de S7.
- [x] Resolver la trazabilidad de métricas complementarias y la terminología ISO 2023; unidad explícita en Tipo de medida.
- [x] Completar A1 con los 122 REQ del alcance vigente y enlazar todos los instrumentos declarados con sus anexos.
- [ ] Conciliar las cifras históricas de Obsidian con los Excel actuales y sustentar EV, AC y cierres reales.
- [ ] Preparar la demostración del avance del software de la foto, concordante con el informe y Scrum; pruebas funcionales siguen sin ejecución según la bitácora.

Siguiente paso: aplicar los instrumentos tras revisión del equipo y expertos; continuar informe, Scrum y demo con datos sustentados. No dar por confirmados resultados del 8 y 9 antes de disponer de evidencia. Investigación detallada: C:/Users/migue/.codex/visualizations/2026/10/07/01a11448-9708-7930-933f-5d7d8b01b516/investigacion-semana8/Investigacion previa Semana 8.md

### Métricas e instrumentos terminados 07/10/2026

- [x] Entregar los dos DOCX en Entregables/Semana 8: métricas 12 páginas e instrumentos 21 páginas.
- [x] Verificar las 33 páginas, fórmulas, 122 REQ y conservación de todas las partes de la plantilla salvo el cuerpo XML.
- [ ] Aplicar juicio de tres expertos y piloto; resultados, firmas y aprobación permanecen pendientes.
- [ ] Confirmar antes de aplicar los protocolos propuestos: 30 intentos medidos por escenario y ventana de seguimiento de 14 días.
- SUS se conserva como sexta ficha VI complementaria; el mínimo cinco se cumple con medidas del producto. No modificar la matriz 3.7 sin revisión académica.
- Informe de estado, Scrum y demostración quedan pendientes; no se ejecutaron pruebas del software en esta tarea.

### Aclaración instrumentos de tesis y cinco VI — 07/10/2026

- [x] Investigar presentación de instrumentos en tesis y guías institucionales; contrastar PMI e ISO sobre medición durante el desarrollo.
- [x] Ajustar a EXACTAMENTE cinco VI y cinco VD: completitud, corrección, prevención de errores, SUS y promedio de respuesta; PTR retirado como ficha independiente.
- [x] Actualizar Informe Final 3.7, 3.8, 3.9, 3.10 y 5.14, notas de resultados pendientes y matriz externa, conforme a las diez métricas y ocho instrumentos declarados.
- [ ] Añadir fichas técnicas uniformes y matriz de relación; distinguir instrumentos de medición de diagnóstico y hojas de validación.
- [x] Precisar el diseño de aplicación progresiva durante desarrollo, versión/alcance y C1/C2; la aplicación real, los resultados y la aprobación siguen pendientes.
- Las versiones entregadas anteriormente todavía tienen seis fichas VI contando SUS. Esta instrucción reemplaza la decisión anterior de mantener SUS como sexta, sin borrar su historia.
- La corrección autorizada posteriormente ya se ejecutó en tres DOCX; los instrumentos se prepararán en la siguiente fase, por pedido del usuario.
- Investigación: C:\Users\migue\.codex\visualizations\2026\10\07\01a11448-9708-7930-933f-5d7d8b01b516\semana8-metricas-instrumentos-v2\Investigacion instrumentos tesis y etapa de medicion.md

### Corrección concordante de cinco VI y cinco VD — 07/10/2026

- [x] Instalar métricas v1.2 (11 páginas), Informe Final (96 páginas) y matriz (2 páginas), con respaldo y SHA comprobados.
- [x] VI01 PRI; VI02 PCF; VI03 PEU; VI04 SUS; VI05 TRP. SUS forma parte de los cinco. PTR deja de ser ficha; el umbral RNF-05 se comprueba también por lectura individual.
- [x] VD01 tiempo de venta; VD02 tiempo de cotización; VD03 porcentaje de monto pendiente; VD04 porcentaje de productos con diferencias; VD05 porcentaje de clientes recuperados.
- [x] Tres dimensiones VI conservadas, 122 REQ y 11 RNF; muestra planificada 216/88/216/343/295, total distinto 942 sujeto a confirmar marcos de los periodos reales.
- [ ] Elaborar ocho instrumentos I01–I08: requisitos, pruebas funcionales válidas e inválidas, SUS, respuesta del software, cronometraje comercial (ventas/cotizaciones separados), cobranzas, inventario y clientes.
- [ ] Fijar antes de aplicar un plazo común de seguimiento de clientes viable para C1/C2. La propuesta previa de catorce días queda retirada hasta conciliación; 30 intentos/5 preparatorios sigue como protocolo propuesto.
- [ ] Aplicar revisión de expertos y piloto, después medir. No hay resultados ni aprobación acreditados.
- Las fechas y el cierre siguen el cronograma. Semana 13 fue una consulta del usuario, no una instrucción de cambiar fechas.
- Esta versión reemplaza las decisiones históricas de seis VI y los formatos anteriores aún sin actualizar. Siguiente paso: instrumentos; luego informe de estado, Scrum y demo.

### Plan de elaboración de instrumentos por bloques — 07/10/2026

- [x] Guardar plan detallado en `docs/ai/PLAN_INSTRUMENTOS_SEMANA8.md`, por pedido de planificar primero y conservar el contexto.
- [ ] Bloque 1: respaldos, matriz diez métricas→ocho instrumentos y ocho fichas técnicas.
- [ ] Bloque 2: I01–I04 (122 REQ, pruebas válidas/inválidas, SUS y tiempos del software).
- [ ] Bloque 3: I05–I08 (ventas/cotizaciones separadas, cobranzas, inventario y clientes).
- [ ] Bloque 4: apoyos de diagnóstico/validación, fuentes y concordancia de nombres/códigos.
- [ ] Bloque 5: cálculos, revisión visual de todas las páginas, instalación con respaldo y memoria.
- Cada instrumento tendrá ficha técnica, procedimiento y formato llenable; los registros reales se incorporarán solo con evidencia. No hay nueva aplicación ni resultados en esta planificación.
- El borrador A1–A13 sigue sin actualizar; corregir VI06, PTR y catorce días durante elaboración. Mantener I02 con secciones y I05 con dos registros.
- Nombre VD04 aprobado: «Porcentaje de productos con diferencias de stock en el inventario (PDS)». Pendiente aplicar esa redacción mínima en los documentos relacionados, sin cambiar fórmula/objetivo.
- Siguiente paso: ejecutar Bloque 1 y guardar avance. El usuario solicitó primero el plan; no se elaboró ni instaló otro Word en este turno.
