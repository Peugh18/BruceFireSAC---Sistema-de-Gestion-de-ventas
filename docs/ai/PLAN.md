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

- [x] S1: elegir el servidor beta o producción según `SUNAT_BETA` y verificar el certificado TLS
- [x] S2: bloquear las NC con motivo 04, 05 u 08 sobre boletas
- [x] S3: ND motivo 13 para penalidades, inafecto; el 03 pasa a "otros conceptos" (R.S. 000048-2026)
- [x] S4: agregar campo de tipo de afectación IGV (Catálogo 07: 10/20/30) en el producto e ítem de venta
- [x] S5: guardar la hora de emisión y usar la misma en el XML y en el PDF
- [x] S6: guardar la fecha de la NC y la ND al crearla y reutilizarla en los reintentos
- [x] S9 y S10: catálogo 09 completo; notas que cuadran con un original que no sea todo gravado
- [x] S8: avisar cuando un comprobante pendiente se acerca a su plazo (factura 3 días, boleta 5)
- [x] S7: comunicación de baja (solo comprobantes no entregados, dentro de 7 días)
- [x] S12 y S13: congelar el comprobante emitido; reintentos y CDR confiables
- [x] V2 y S17: aplicar los permisos de verdad; la NC necesita aprobación del Gerente

### A2. Seguridad (antes de producción)

- [ ] X1: filtro de sede en la capa de datos (scope o trait en `Sale`, `ServiceOrder`, `Certificate`, `Equipment`) y pruebas de acceso cruzado por ruta; corregir la ficha del cliente
- [ ] X2: firmas y sellos al disco privado, servidos con una ruta autenticada
- [ ] X3: obligar a cambiar la contraseña en el primer ingreso; 2FA obligatorio para el Gerente
- [ ] X4 y X5: `.env` de producción (`APP_DEBUG=false`, `LOG_LEVEL=warning`, cookie segura), `trustProxies` del hosting, CSP y HSTS

### B. Certificados y tipo de extintor

- [ ] C1: agente y capacidad en el producto, copiados a la unidad y al equipo; si falta el agente, no se emite el certificado
- [ ] C2: los certificados técnicos (P.H., capacitación) se emiten solo después de que un técnico los valide, no al cobrar
- [ ] Una sola regla de certificados: `CertificateRuleEngine` o la regla por destino, no las dos
- [ ] A4: el servicio elige su tipo de certificado en el formulario del Gerente

### C. Ventas, caja y cobranzas

- [ ] V1: línea serializada siempre con cantidad 1
- [ ] V3: la cotización pasa completa a la venta (descuento, condición de pago, observaciones, vehículo)
- [ ] V4 y S11: las notas aceptadas cambian el saldo por cobrar
- [ ] V5: renovar el vencimiento del equipo solo al cerrar el trabajo técnico
- [ ] V6: un adicional autorizado genera deuda real
- [ ] V7: pago ligado al turno de caja; bloqueo contra doble apertura o confirmación
- [ ] V8: decidir si el vendedor ve en la ficha del cliente solo sus propias ventas

- [ ] X6: la tarea diaria no debe vencer cotizaciones "aceptadas"
- [ ] X7: medir los KPI del proyecto (tiempo de venta, tiempo de cotización, clientes recuperados por alertas: la cotización guarda la alerta de origen)
- [ ] X8 y X9: alertas filtradas por sede o vendedor, con registro de contacto; "ofrecer recarga" según la capacidad y el agente

### D. Técnicos ⚠️ DRS

- [ ] Evidencias únicas (§33): foto, audio o archivo por orden, equipo y etapa
- [ ] Conversación de la orden sobre `ServiceOrderEvent`: todos escriben y adjuntan (T3)
- [ ] Firma táctil en recojo, entrega, instalación, inspección y mantenimiento (T1)
- [ ] Motor único de visitas de campo con plantillas; mantenimiento en sitio incluido (T2)
- [ ] Instalación escaneando cada unidad vendida (T4); deficiencia fuera del checklist (T5)

### E. Almacén y Gerente

- [ ] A2: tabla de categorías y botón "Gestionar" junto al select; categoría también en servicios ⚠️ DRS
- [ ] A3: unidad de medida del servicio con el catálogo SUNAT 03; rechazar unidades desconocidas
- [ ] A5: no permitir dar de baja dos veces la misma unidad
- [ ] A6: arreglar los botones sin acción (nueva recepción y anular pago)
- [ ] A1: costo de compra en la recepción y valorización correcta del inventario ⚠️ DRS
- [ ] Stickers de 5 × 5 cm, 20 por hoja, con opción de empezar en la posición N (cambia el REQ-INV-07)
- [ ] A7: paginar en la base de datos; quitar el N+1 de Consulta Rápida

### F. Interfaz y nombres

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
