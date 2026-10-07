# PROYECTO: Sistema web para la gestión de ventas en BRUCE FIRE S.A.C.

**Lee esto primero.** Aquí está qué es el sistema, las decisiones que ya se tomaron y dónde está cada documento. Para saber qué falta hacer, ve a `PLAN.md`.

## 1. Qué es

- **Nombre oficial:** Sistema web para la gestión de ventas en BRUCE FIRE S.A.C. No es un ERP.
- **Cliente real:** BRUCE FIRE S.A.C. (Trujillo). Vende, recarga, mantiene, inspecciona e instala extintores y presta otros servicios de seguridad: señalización, detección, pozo a tierra y fumigación. Hoy factura con un sistema de terceros (CODEPLEX) y lleva el control de extintores en papel. El sponsor es el Gerente General.
- **Contexto académico:** proyecto del curso en la UPN (Grupo 01, 5 integrantes), con Scrum de 16 sprints del 21/08 al 11/12/2026. El **alcance está fijado en el DRS v3.1** (21 módulos, 122 requisitos). Todo lo nuevo necesita su REQ en el DRS, su historia en el Product Backlog y su fila en la Matriz de Consistencia.
- **Fuera de alcance:** contabilidad y planillas. El sistema no envía correos ni mensajes fuera de sí mismo.
- **SUNAT:** hoy **solo en el ambiente beta (pruebas)**. Se pasa a producción cuando todo esté validado (`SUNAT.md` §4).

## 2. Stack

- **Backend:** Laravel 13 con PHP 8.3 y MySQL 8.4 (Laragon en desarrollo).
- **Acceso y roles:** Fortify (2FA y passkeys) y Spatie Permission.
- **Facturación:** Greenter (UBL 2.1).
- **Documentos y códigos:** DomPDF, PHPWord, QR y código de barras Code 128.
- **Frontend:** Inertia v3, React 19, TypeScript, Tailwind 4 y Radix. Las rutas tipadas salen de Wayfinder (`--with-form`).
- **Calidad:** Pest, PHPStan (Larastan), Pint, `vp check` y `tsc`. Todo junto en `composer ci:check`. El CI de GitHub lo corre en cada PR; el hook `pre-push` hace solo una revisión rápida.

## 3. Roles y lo que hace cada uno

| Rol                                         | Hace                                                                                                                                                       |
| ------------------------------------------- | ---------------------------------------------------------------------------------------------------------------------------------------------------------- |
| **Vendedor**                                | Clientes, cotizaciones, ventas, caja (arqueo ciego), comprobantes SUNAT, notas, cobranzas, órdenes de servicio, autorización de adicionales y certificados |
| **Gerente** (también hace de administrador) | Dashboard, catálogo de productos y servicios, cajas y cobranzas consolidadas, reportes, auditoría, sedes, usuarios y roles, configuración, firmantes       |
| **Almacén**                                 | Stock y Kardex, recepciones, stickers BF-EQ, ajustes, traslados y consulta rápida                                                                          |
| **Técnico de planta**                       | Recepción en taller, alta técnica rápida, checklist por extintor, deficiencias y ejecución                                                                 |
| **Técnico de campo**                        | Recojo, inspección, instalación y entrega con acta                                                                                                         |
| **Público**                                 | Verificación de un certificado por QR                                                                                                                      |

**Cadena de un servicio:** vendedor crea la orden → campo recoge o la orden se recibe en tienda → planta recibe, revisa y recarga → deficiencia → el vendedor autoriza → planta resuelve con repuesto del almacén → certificado → campo entrega con acta → orden cerrada.

## 4. Decisiones ya tomadas (no reabrir sin motivo)

| Tema                     | Decisión                                                                                                                                         |
| ------------------------ | ------------------------------------------------------------------------------------------------------------------------------------------------ |
| Roles                    | 5 roles fijos. El Gerente absorbe al administrador. Un usuario tiene **una sola sede**.                                                          |
| Sedes                    | La tienda vende con el stock de su almacén asignado. Las sedes se desactivan, no se borran. Los clientes y el catálogo se comparten entre sedes. |
| Catálogo                 | Un solo maestro de productos y servicios para todos los roles                                                                                    |
| Ajustes de stock         | Directos, sin doble aprobación, con motivo de 10 caracteres o más, y siempre con su movimiento en el Kardex                                      |
| Caja                     | Arqueo ciego y un solo turno abierto por trabajador                                                                                              |
| Comunicación entre roles | Por eventos de la orden (`ServiceOrderEvent`), sin chat general                                                                                  |
| Cadena de custodia       | Eventos tipados en `ServiceOrderEvent`, sin tabla nueva                                                                                          |
| Firma                    | El §85.6.2 dejó primero casilla + nombre, y la firma táctil para después. **El dueño ya la pidió.**                                              |
| Certificados             | `certificate_units` es una foto legal y no cambia después. Hay QR público y versión en Word.                                                     |
| Historial                | Usuarios, clientes y ventas con historial no se borran (FK `restrict`, migración `2026_09_28_014309`). Se quitó "Eliminar cuenta".               |
| Boletas                  | Se envían de una en una, sin resumen diario                                                                                                      |
| Auditoría                | `AuditLogger::log()` se llama donde ocurre cada acción                                                                                           |
| Hora                     | `America/Lima`                                                                                                                                   |
| Primer Gerente           | Se crea por consola (`sistema:crear-gerente`); no hay contraseñas por defecto                                                                    |

## 5. Mapa de documentos

| Necesito…                                                            | Dónde                                                        |
| -------------------------------------------------------------------- | ------------------------------------------------------------ |
| Qué falta hacer, en orden                                            | `docs/ai/PLAN.md`                                            |
| Qué está mal y por qué                                               | `docs/ai/AUDITORIA.md`                                       |
| Normativa SUNAT vigente, estado de la facturación y guía de remisión | `docs/ai/SUNAT.md`                                           |
| Historial de sesiones                                                | `docs/ai/BITACORA.md`                                        |
| Detalle por rol e investigación de empresas grandes                  | `docs/ai/anexos/`                                            |
| Especificación funcional (§1–§75, con fe de erratas al inicio)       | `documentos/BRUCE_FIRE_Documento_Maestro_v9.md`              |
| Historial del Maestro (§76–§90) y plan por fases viejo               | `documentos/archivo/`                                        |
| Matriz de permisos (parcialmente obsoleta: manda el seeder)          | `documentos/01_MODULOS_ROLES_PERMISOS.md`                    |
| Lo que pidió el dueño por voz                                        | `documentos/notas_de_voz_usuario.md`                         |
| Guías oficiales de SUNAT                                             | `documentos/sunat/`                                          |
| Certificados e informes reales de referencia                         | `documentos/plantillas_certificados/`                        |
| Cómo se usa Greenter                                                 | `docs/FACTURACION_GREENTER_SUNAT.md`                         |
| Decisiones y documentos del curso (DRS, EDT, backlog)                | Obsidian: `Bruce Fire/` y `Documentación de Bruce Fire SAC/` |

## 6. Deuda de interfaz conocida

- Hay 4 estilos de pestañas. Se unificarán en un _segmented control_.
- Falta un `KpiCard` y una tabla que se vea como tarjetas en el celular.
- Chispa tapa botones en celulares de 375 px.
- Hay muchos `text-[..px]` y `rounded-[..px]` sueltos; deben pasar a tokens.
- **El diseño de la barra lateral (`.bf-nav-active`) no se toca.**
