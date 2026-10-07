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
- [x] S4: tipo de afectación IGV (catálogo 07) en productos, servicios y líneas de venta, con su select en el catálogo del Gerente (Codex). *Falta correr la suite completa.*
- [x] S5: guardar la hora de emisión y usar la misma en el XML y en el PDF
- [x] S6: guardar la fecha de la NC y la ND al crearla y reutilizarla en los reintentos
- [x] S9: catálogo 09 completo
- [x] S10: las notas usan la afectación del comprobante original (`DesgloseNota`); los casos mixtos o los motivos 11, 12 y 13 sin datos se bloquean con un mensaje claro (Codex). *Falta correr la suite completa.*
- [x] S8: aviso en Facturación del vendedor y en el dashboard del Gerente (vence hoy o mañana, y vencidos), con lista filtrada
- [x] S7: comunicación de baja real (RA para serie F, RC estado 3 para serie B, ticket y consulta), solo no entregados y dentro de 7 días desde el CDR
- [x] S12: se guarda una copia de los datos de emisión (cliente y líneas) en `datos_emision`, y el PDF y la baja la leen (Codex). *Falta correr la suite completa.*
- [x] S13: no se acepta nada sin un CDR válido
- [x] V2 y S17: permisos Spatie en rutas y FormRequest; la NC y la ND del vendedor esperan la aprobación del Gerente

### A2. Seguridad (antes de producción)

- [x] X1: scope `visiblePara(User)` en `Sale`, `ServiceOrder`, `Certificate` y `Equipment` (lo usan `AcotaPorSede` y los controladores de ventas, órdenes, certificados y la ficha del cliente). *Pruebas escritas; sin suite completa (MySQL apagado).*
- [x] X2: firmas, sellos y fotos de capacitación en el disco privado; la firma se ve por una ruta solo del Gerente; migración que mueve los archivos. *Pruebas escritas; sin suite completa (MySQL apagado).*
- [x] X3: `must_change_password` y middleware `ExigirSeguridadDeLaCuenta` (contraseña inicial y 2FA del Gerente, interruptor `SEGURIDAD_EXIGIR_2FA_GERENTE`). *Pruebas escritas; sin suite completa (MySQL apagado).*
- [x] X4 y X5: bloque de producción en `.env.example`, `TRUSTED_PROXIES`, HSTS por https y CSP con nonce (en modo solo reporte). *Pruebas escritas; sin suite completa (MySQL apagado).*

### B. Certificados y tipo de extintor

- [x] C1: agente (lista `EquipmentType`, con PQS BC) y capacidad en el producto, copiados a la unidad y al equipo; técnicos eligen de la lista; sin agente no se emite. *Pruebas escritas; sin suite completa (MySQL apagado).*
- [x] C2: al cobrar solo sale operatividad de extintores nuevos; P.H. y capacitación quedan pendientes y la P.H. exige fecha, presión, tiempo y resultado reales. *Pruebas escritas; sin suite completa (MySQL apagado).*
- [x] Una sola regla de certificados: `EmitirCertificadosDeVenta::tiposPorDestino`; se borró `CertificateRuleEngine` (sin uso) y su prueba unitaria.
- [x] A4: el servicio elige su tipo de certificado en el formulario del Gerente. *Pruebas escritas; sin suite completa (MySQL apagado).*

### C. Ventas, caja y cobranzas

- [x] V1: línea serializada siempre con cantidad 1 *Código y pruebas escritas; sin suite completa (MySQL apagado).*
- [x] V3: la cotización pasa completa a la venta (descuento, condición de pago, observaciones, vehículo) *Código y pruebas escritas; sin suite completa (MySQL apagado).*
- [x] V4 y S11: las notas aceptadas cambian el saldo por cobrar *Código y pruebas escritas; sin suite completa (MySQL apagado).*
- [x] V5: renovar el vencimiento del equipo solo al cerrar el trabajo técnico *Código y pruebas escritas; sin suite completa (MySQL apagado).*
- [x] V6: un adicional autorizado genera deuda real *Código y pruebas escritas; sin suite completa (MySQL apagado).*
- [x] V7: pago ligado al turno de caja; bloqueo contra doble apertura o confirmación *Código y pruebas escritas; sin suite completa (MySQL apagado).*
- [x] V8: la ficha del cliente muestra al vendedor solo sus ventas, cotizaciones y certificados (§90.1); hecho con X1. *Pruebas escritas; sin suite completa (MySQL apagado).*

- [x] X6: la tarea diaria no debe vencer cotizaciones "aceptadas" *Código y pruebas escritas; sin suite completa (MySQL apagado).*
- [x] X7: medir los KPI del proyecto (tiempo de venta, tiempo de cotización, clientes recuperados por alertas: la cotización guarda la alerta de origen) *Código y pruebas escritas; sin suite completa (MySQL apagado).*
- [x] X8 y X9: alertas filtradas por sede o vendedor, con registro de contacto; "ofrecer recarga" según la capacidad y el agente *Código y pruebas escritas; sin suite completa (MySQL apagado).*

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
- [ ] Actualizar Greenter de **v4.3.1 (2021) a v5.3** y agregar `greenter/gre-api` (requiere aprobación: cambia dependencias); volver a pasar toda la suite de facturación
- [ ] Credenciales de la API (`client_id` y `client_secret`) desde Menú SOL; confirmar cómo probar sin afectar producción
- [ ] Datos maestros: vehículos (placa y categoría M1, L o N), conductores, peso por producto y código de establecimiento por sede
- [ ] Módulo único "Guías de remisión": emitir desde la venta (motivo 01), desde la orden de recojo o entrega (motivo 13, por confirmar) y desde el traslado entre sedes (04, con "en tránsito", A8); CDR aceptado antes de salir

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

**Estado al 07/10/2026:** revisión inicial y plan propuesto guardados; Word y Excel sin modificar. Periodo 02/10–09/10, entrega el 09/10. El usuario confirmó este repositorio y pidió conservar toda la memoria.

Detalle y fuentes en Obsidian: `D:/TiomiguelonGgs/Documents/Recuerda/Cerebro/Documentación de Bruce Fire SAC/27 - Plan de entregables Semana 8 Sprint 7.md`.

- [x] Revisar plantillas, matriz, informe final, Scrum anterior, Obsidian y cronograma.
- [x] Guardar el plan y los hallazgos de la conversación.
- [ ] Métricas: mínimo cinco VI alineadas a ISO y cinco VD de proceso; ocho filas completas, unidad, rangos e instrumento existente. Mantener SUS y corregir denominadores.
- [ ] Instrumentos: formularios aplicables para ambas variables, instrucciones, muestra, C1/C2 y evidencia.
- [ ] Scrum Sprint 7: periodo completo 02/10–09/10, códigos EDT y estados sustentados.
- [ ] Informe Sprint 7: concordar con Scrum, recalcular anexos, porcentajes y semáforos. No copiar AC ni CPI sin sustento.
- [ ] Verificar Word/Excel y coherencia cruzada antes de entregar.
- [ ] Después revisar el sistema y las pruebas pendientes; no dar por validado lo implementado.

**Siguiente paso documental:** retomar el plan con el usuario y preparar los cuatro archivos de Semana 8 conservando las plantillas. Planes de pruebas Funcionales y Unitarias quedan como referencia pendiente de alcance; el pedido de guardar memoria no confirma incorporarlos. No confundir fechas planificadas con terminación real. Este bloque no cambia las prioridades ni el estado de las fases técnicas anteriores.
