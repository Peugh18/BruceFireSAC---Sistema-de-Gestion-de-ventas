# BRUCE FIRE S.A.C. — Plan por Fases (BruceFireSacv2)

> **Archivado el 2026-10-06.** Lo reemplaza `docs/ai/PLAN.md`. Se guarda solo como historial.

Este documento es el **mapa de ejecución**. El contenido funcional
completo (roles, reglas de negocio, certificados, checklist técnico,
facturación, etc.) ya está maduro y con fuentes en
`documentos/BRUCE_FIRE_Documento_Maestro_v9.md` (78 secciones) — no se
repite aquí. Este archivo solo dice **en qué orden se construye**, para
no "mover según la idea nomás" y evitar reabrir decisiones ya tomadas.

Proyecto base confirmado por el usuario: **BruceFireSacv2**, arranque
limpio sobre `laravel/react-starter-kit` (Laravel 13, PHP 8.3,
Inertia + React 19, Fortify, Teams, Passkeys, Tailwind v4, shadcn/ui).
El proyecto anterior (`BruceFireSAC`) queda descartado — no se vuelve a
tocar ni se migra código de ahí; el criterio ya no es "¿qué había
hecho?" sino "¿qué dice el documento maestro?".

---

## Fase 0 — Base del proyecto (antes de diseño)

Objetivo: dejar el starter kit listo para recibir la identidad y los
módulos de BRUCE FIRE sin arrastrar nada genérico a medias.

- Traducir a español todo lo que el starter trae en inglés: páginas de
  auth (`login`, `register`, `forgot-password`, `reset-password`,
  `verify-email`, `two-factor-challenge`, `confirm-password`),
  `settings/appearance`, `settings/security`, `teams/*`, componentes
  compartidos (`nav-user`, `user-menu-content`, modales de equipo,
  passkeys, 2FA) y sus mensajes de validación (`lang/`). Se cambia
  **todo**, no una parte — la mezcla español/inglés es justamente lo
  que se quiere evitar.
- Decidir qué se conserva del starter y qué se retira (Teams/passkeys
  no estaban pedidos por el usuario — se evalúa si BRUCE FIRE necesita
  "equipos" tipo SaaS multi-tenant o si eso se elimina para no cargar
  UI/rutas que no se van a usar).
- Instalar y configurar las dependencias base decididas en la sección
  "Librerías confirmadas" más abajo (spatie/laravel-permission primero,
  porque los roles condicionan rutas y menús desde el día 1).
- Sembrar los roles iniciales (Gerente, Vendedor, Almacén, Técnico de
  Planta, Técnico de Campo) y usuarios de prueba (Documento Maestro
  §69), para poder probar el diseño con navegación real por rol.

## Fase 1 — Diseño (identidad visual + mockups) — **fase actual**

Objetivo: cerrar la identidad visual completa ANTES de escribir una
sola pantalla de negocio, para que ningún módulo salga con "colores
que no coinciden".

1. Reemplazar la paleta neutra por defecto de shadcn en
   `resources/css/app.css` por los tokens reales de marca (rojo BRUCE
   FIRE como primario, grafito/negro secundario, más `--success`,
   `--warning`, `--info` que hoy no existen en el starter) — light y
   dark, según Documento Maestro §3 y §52.
2. Mockups de las pantallas y modales clave (dashboard por rol, tabla
   de inventario con alta Producto/Servicio, ficha de cliente,
   venta/cotización, checklist técnico móvil, certificado con QR,
   cobranzas) en tema claro y oscuro, con la paleta ya aplicada — para
   validar contigo antes de programar cada módulo.
3. Iconografía y tipografía: una sola familia de íconos (Documento
   Maestro §53), mantener `Instrument Sans` o decidir reemplazo.

### 2.1 Mockups generados (2026-09-19) — proyecto "Bruce Fire Sales Dashboard" en Stitch

Generados con Google Stitch (stitch.withgoogle.com, cuenta del
usuario) usando el sistema de diseño real de BRUCE FIRE — sidebar,
header y tokens compartidos entre las 12 pantallas, rojo de marca
corregido a `#E31E24` (el de logo real, no una aproximación) tras
comparar contra los archivos `logo solo.png` y
`EXTINTORES BRUCE 2027 BLANCO.png` que el usuario proporcionó (ver
`resources/images/brand/`). El isotipo exacto (círculo con las letras
E/F en 3D plateado y llama roja/naranja) no se pudo subir a Stitch por
una limitación de automatización del navegador; las pantallas usan una
aproximación textual del isotipo — **pendiente reemplazar por el PNG
real al maquetar en código** (Fase 3).

Pantallas completas, una por rol/módulo:

- **Login** — con selector de sede e ingreso alternativo por llave de
  seguridad FIDO/DNI-e (aprovecha las passkeys que ya trae el starter).
- **Dashboard Vendedor** (claro y oscuro) — KPIs del día, arqueo de
  caja, cotizaciones por cerrar, cobros en mora, gráfico semanal,
  últimas ventas con estados SUNAT.
- **Dashboard Gerente/Administrador** — KPIs mensuales de toda la
  empresa, ranking de ventas por vendedor, cobranza, órdenes de
  servicio activas.
- **Dashboard Almacén** — recepción de lote con generación automática
  de series, vista previa de hoja de stickers de código de barras
  (grilla 2x2), listado de unidades serializadas por estado.
- **Inventario** (rol técnico/gerencial) — tabla única Producto/
  Servicio con Kardex, sin "Catálogo" separado (confirma la decisión
  de la sección 76.2 del Documento Maestro).
- **Clientes** — directorio con alta rápida por autocompletado
  SUNAT/RENIEC, estado de RUC verificado.
- **Modal Nueva Venta/Cotización** — búsqueda de cliente, ítems
  escaneables, condición de pago con cuotas y liquidación fiscal.
- **Cobranzas** — arqueo de caja ciego (conteo físico sin ver el saldo
  esperado) y flujo de cobros por canal.
- **Certificado de Operatividad y Garantía** — formato A4 imprimible
  con QR de verificación, firma de ingeniero colegiado y sello del
  taller autorizado.
- **Checklist técnico móvil (375px)** — puntos de control del
  extintor (manómetro, pasador, manguera) con estado OK/Observado.

- **Dashboard Gerente/Administrador** — KPIs mensuales de toda la
  empresa, ranking de ventas por vendedor, cobranza, órdenes de
  servicio activas.
- **Panel Técnico de Planta** (escritorio) — cola de trabajo de
  extintores en taller (en cola/en proceso/listos), parámetros de
  sello de calidad (manómetro calibrado, agente, hermeticidad) según
  NTP 350.043, que alimentan el certificado.
- **Panel Técnico de Campo** (móvil 375px) — agenda del día con
  órdenes de servicio asignadas (inspección/instalación/
  mantenimiento), botón "Continuar Checklist Técnico" y contacto
  directo al cliente (llamada/WhatsApp).

Con esto quedan cubiertos los 5 roles del Documento Maestro §5/§35
(Gerente, Vendedor, Almacén, Técnico de Planta, Técnico de Campo).

Corrección aplicada (2026-09-19): la primera versión de la hoja de
stickers en Almacén generaba solo 4 etiquetas grandes por A4; se pidió
corregir a etiquetas de tamaño real (~5×3 cm) en grilla 3×8 (24 por
hoja), con guías de margen de 3mm y nota de calibración de impresora a
600 DPI — ya reflejado en la pantalla.

### 2.2 Segunda ronda (2026-09-19) — módulos secundarios, 100% de cobertura

A pedido explícito del usuario ("¿ya están todas las vistas? todos los
módulos?") se auditó el documento maestro completo contra lo generado
y se completaron las 20 pantallas que faltaban, hasta cubrir el 100%
de los módulos descritos en las 78 secciones:

- **Ficha de Equipo del Cliente** (§7) — historial de vida en línea de
  tiempo, estado operativo/por vencer/vencido.
- **Transferencia de Equipo** (§8) — Acta de Transferencia con ruta de
  custodia origen→destino y trazabilidad QR.
- **Alta Técnica Rápida** (§18) — registro de equipo externo sin
  código BRUCE FIRE, genera código interno y QR al guardar.
- **Servicios** (§15) — tarifario de servicios técnicos.
- **Orden de Servicio** (§16) — detalle con línea de tiempo de
  estados.
- **Comunicación Vendedor↔Técnico de Planta** (§17) — hilo de eventos
  por orden, no chat general.
- **Matriz de Deficiencias Técnicas** (§20) — con severidad, foto y
  estado.
- **Autorización de Adicionales** (§21) — trabajos fuera de
  cotización pendientes de aprobación del cliente/vendedor.
- **Cadena de Custodia: Recojo y Entrega** (§22) — firma digital y
  fotos.
- **Acta de Conformidad y Recepción Digital** (§23) — doble firma
  digital (proveedor/cliente) con token OTP, hash SHA-256 y QR.
- **Inspección de Extintores** (§24) — cabecera + tabla dinámica de N
  filas por extintor, con todas las columnas normativas (manómetro,
  pasador, manguera, vencimiento P.H., etc.).
- **Instalación de Sistema de Detección** (§25) — formulario por zona
  con foto de inicio/fin.
- **Galería de Evidencia Fotográfica** (§33) — comparativa "cómo se
  encontró / cómo se dejó", hash de trazabilidad.
- **Alertas y Próximas Atenciones** (§27) — vencimientos ordenados por
  urgencia con campaña de notificación masiva.
- **Facturación y Envío SUNAT** (§28-31) — listado con filtros de
  estado, reenvío, descarga XML/CDR/PDF.
- **Notas de Crédito y Débito** (§29) — referencian un comprobante ya
  emitido, motivo del Catálogo 09 SUNAT.
- **Guía de Remisión Electrónica** (§30) — remitente, destinatario,
  transportista, motivo de traslado.
- **Reportes y Analítica Consolidada** (§34) — ventas, servicios de
  taller, kardex, top clientes, exportación Excel/PDF.
- **Administración de Roles y Permisos** (§35-36) — matriz por rol
  igual a la sección 36 del documento maestro.
- **Auditoría / Log de Trazabilidad Forense** (§37) — quién, qué,
  cuándo.
- **Centro de Notificaciones Internas** (§38) — órdenes asignadas,
  deficiencias, vencimientos, aprobaciones pendientes.

Con esta ronda, **las 78 secciones del Documento Maestro ya tienen su
pantalla de referencia visual** en el proyecto "Bruce Fire Sales
Dashboard" de Stitch, con el mismo sistema de diseño, sidebar, header
y rojo de marca (#E31E24) en las 34 pantallas.

Único pendiente para cerrar la Fase 1 al 100% (no bloquea empezar la
Fase 2): reemplazar el isotipo aproximado por el PNG real
(`resources/images/brand/bruce-fire-isotipo.png`) en cada pantalla al
momento de maquetar en código.

## Fase 2 — Base de datos

Objetivo: un esquema cerrado y correcto desde el inicio, para no estar
"moviendo" tablas cuando ya haya datos de prueba cargados.

- Modelado completo de todas las tablas del sistema (clientes, sedes,
  equipos del cliente, productos/servicios — sin `CatalogItem`,
  inventario/kardex, unidades serializadas, cotizaciones, ventas,
  condición de pago/cuotas, órdenes de servicio, checklist dinámico,
  deficiencias, certificados, comprobantes electrónicos, notas de
  crédito/débito, guía de remisión, cobranzas/caja) con relaciones,
  llaves foráneas, índices y enums, siguiendo el modelo de estados
  general (Documento Maestro §48) y las reglas de negocio (§49).
- Migraciones + factories + seeders de datos realistas (no ficticios
  masivos, sección 10.3) para poder probar cada módulo con datos
  parecidos a la operación real.
- Ninguna tabla se crea "por si acaso" — cada una debe poder señalarse
  a una sección concreta del documento maestro.

## Fase 3 — Vistas y funcionalidades por módulo

Objetivo: construir módulo por módulo, en el orden en que unos
dependen de otros (no se puede facturar sin clientes ni inventario).

Orden de construcción (retoma la sección 72 del documento maestro,
adaptada a que ahora se parte de cero):

1. Clientes (CRUD + lookup RUC/DNI con caché local — ver Fase 4).
2. Inventario (Producto/Servicio unificado, stock numérico, unidades
   serializadas, recepción de lote, stickers de código de barras).
3. Comercial: Cotización → Venta, condición de pago (contado/crédito
   con cuotas y anticipos), escaneo de extintores vendidos.
4. Servicios y Órdenes de Servicio: checklist dinámico generalizado
   (extintores + demás servicios técnicos), deficiencias, fotos de
   evidencia, comunicación cliente↔técnico de campo.
5. Certificados: motor de reglas por tipo de destino/servicio, QR de
   autenticidad.
6. Facturación electrónica y GRE (ver Fase 4 — Greenter).
7. Cobranzas (arqueo ciego de caja, cuotas vencidas, pagos).
8. Dashboards diferenciados por rol y reportes (Documento Maestro §5,
   §34, §77.3).

## Fase 4 — Integraciones externas

- **API RUC/DNI**: un solo servicio (`DocumentLookupService`) que
  primero busca en `clients` local por número de documento y solo
  golpea la API externa si no existe o el dato está desactualizado;
  guarda además `estado_contribuyente`/`condicion_domicilio` (Activo +
  Habido) para bloquear facturación a RUC no habido (Documento Maestro
  §78.1). No se llama a la API dos veces por el mismo dato.
- **Greenter/SUNAT**: factura, boleta, notas de crédito/débito, GRE,
  descuento por línea y anticipos (sí aplican), sin exonerada/
  percepción/exportación/ICBPER (no aplican al negocio) — todo el
  detalle técnico y las decisiones de qué sí/no construir ya están en
  `docs/FACTURACION_GREENTER_SUNAT.md` (secciones 1-12). Pendiente real
  antes de esta fase: confirmar con el contador el código de
  detracción correcto del Catálogo 54 (§4 del doc técnico).

## Fase 5 — Calidad y cierre

- Suite de pruebas por módulo (Pest), revisión visual light/dark en
  cada pantalla nueva, checklist de "Definición de Terminado" por
  funcionalidad (Documento Maestro §73).
- Antes de producción con SUNAT: checklist de `docs/FACTURACION_GREENTER_SUNAT.md`
  §12.4 (certificado `.pem`, usuario SOL secundario con permiso de
  Facturación Electrónica activado con anticipación, credenciales
  separadas para GRE).

---

## Librerías confirmadas para BruceFireSacv2 (investigado 2026-09-19)

El Documento Maestro (§41) ya decidía la categoría de cada necesidad
pero dejaba la librería exacta "a definir cuando se cierre la versión
de Laravel". Ya está cerrada (Laravel 13 / PHP 8.3), así que se fija la
elección concreta, con la razón:

| Necesidad                                                                    | Paquete elegido                             | Por qué                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                    |
| ---------------------------------------------------------------------------- | ------------------------------------------- | ---------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| Roles y permisos                                                             | `spatie/laravel-permission`                 | Ya confirmado en uso real (Documento Maestro §76.1) — integra con Gate/Policy nativos de Laravel.                                                                                                                                                                                                                                                                                                                                                                                                          |
| Auditoría                                                                    | `spatie/laravel-activitylog`                | Estándar de facto para "quién cambió qué", ya decidido en §41.2.                                                                                                                                                                                                                                                                                                                                                                                                                                           |
| Multimedia (fotos de evidencia técnica, logo, adjuntos de certificado)       | `spatie/laravel-medialibrary`               | Ya aprobado en §67.1; evita reinventar manejo de archivos/conversions.                                                                                                                                                                                                                                                                                                                                                                                                                                     |
| Backups                                                                      | `spatie/laravel-backup`                     | Ya aprobado en §67.1.                                                                                                                                                                                                                                                                                                                                                                                                                                                                                      |
| **PDF** (certificados, comprobantes, reportes)                               | `spatie/laravel-pdf` (Browsershot/Chromium) | El starter usa Tailwind v4 con clases modernas (grid/flex); `barryvdh/laravel-dompdf` solo soporta CSS 2.1 y rompería el mismo diseño que se define en la Fase 1. `spatie/laravel-pdf` renderiza con Chromium real, mismo resultado visual que en el navegador ([comparación 2026](https://medium.com/@developerawam/spaties-laravel-pdf-vs-laravel-dompdf-which-one-should-you-actually-use-8c1d2ca104f7)). Costo: requiere Node/Chromium en el servidor — se define en Fase 5 según dónde se despliegue. |
| **QR** (certificados, código de barras interno)                              | `f9webltd/simple-qrcode`                    | El paquete original `simplesoftwareio/simple-qrcode` está sin mantenimiento; este fork activo soporta PHP ^8.2 y Laravel ^11/^12/^13, misma API fluida y fácil de abstraer detrás de un servicio propio (§41.8).                                                                                                                                                                                                                                                                                           |
| **Código de barras** (stickers internos de extintores/unidades serializadas) | `picqer/php-barcode-generator`              | QR y código de barras no resuelven el mismo problema: el QR valida certificados públicamente; el barcode interno agiliza escaneo físico en almacén/venta/taller. `picqer/php-barcode-generator` evita dibujar barras a mano y puede envolverse en un servicio `BarcodeService`.                                                                                                                                                                                                                            |
| **Excel** (exportar clientes/ventas/reportes)                                | `maatwebsite/excel` v4.x                    | La v4.0.3 (14/09/2026) ya soporta Laravel ^12/^13 y PHP ^8.3 de forma nativa — no hace falta alternativa.                                                                                                                                                                                                                                                                                                                                                                                                  |
| **Facturación electrónica SUNAT**                                            | `greenter/lite`                             | Ya decidido y documentado a fondo en `docs/FACTURACION_GREENTER_SUNAT.md`.                                                                                                                                                                                                                                                                                                                                                                                                                                 |
| Colas/Scheduler/Notifications                                                | Nativos de Laravel                          | Ya decidido en §41.4-41.6, sin paquete externo.                                                                                                                                                                                                                                                                                                                                                                                                                                                            |
| Tablas complejas en React                                                    | `@tanstack/react-table`                     | Clientes, inventario, ventas, comprobantes, cobranzas y reportes necesitan filtros, ordenamiento, columnas y paginación sin reinventar una tabla propia. Encaja con Inertia + React y no impone UI visual.                                                                                                                                                                                                                                                                                                 |
| Gráficos de dashboards                                                       | `recharts`                                  | Suficiente para KPIs por rol, ventas por mes, cartera, stock crítico y servicios; más simple que librerías pesadas para el MVP.                                                                                                                                                                                                                                                                                                                                                                            |
| Fechas/calendarios                                                           | `date-fns` + `react-day-picker`             | Filtros por mes, vencimientos, cuotas, próximas atenciones, programación de servicios y calendarios ligeros.                                                                                                                                                                                                                                                                                                                                                                                               |
| Buscador global / comandos rápidos                                           | `cmdk`                                      | Opcional cuando exista navegación real por módulos: buscar cliente, venta, certificado, orden o comando rápido sin construir un command palette desde cero.                                                                                                                                                                                                                                                                                                                                                |

No se agrega ninguna dependencia más "por si acaso" — cada fila de
esta tabla resuelve una necesidad ya escrita en el documento maestro,
no una hipotética.

### Sobre catálogos de Skills externos (Laravel Cloud Skills, otros repos)

Investigado de nuevo el 2026-09-19 por pedido explícito del usuario,
usando `https://skills.laravel.cloud/`, documentación oficial de
Laravel Boost y publicaciones de Spatie sobre Skills.

Primero, una distinción importante:

- **Librerías/paquetes Composer/NPM**: se instalan en la aplicación y
  dan funcionalidad real en runtime (`spatie/laravel-permission`,
  `greenter/lite`, `maatwebsite/excel`, etc.).
- **Skills de agente**: son instrucciones reutilizables para Codex,
  Claude Code, Cursor, Copilot, Windsurf, etc. No agregan funcionalidad
  al sistema; ayudan a que el agente escriba Laravel/PHP/Inertia con
  mejores patrones y menos alucinaciones. Laravel Boost permite
  instalarlas con `php artisan boost:add-skill <repo> --skill <nombre>`.

Laravel documenta Boost como un MCP para agentes con inspección de app,
BD, rutas, comandos Artisan, logs, Tinker y búsqueda de documentación
versionada; también indica que las Skills se cargan bajo demanda para
evitar llenar el contexto con reglas que no aplican. El sitio
`skills.laravel.cloud` lista **200 Skills** al momento de la revisión,
con comando de instalación, repositorio fuente, contador de instalación
y auditorías (`Gen Agent Trust Hub`, `Socket`, `Snyk`) para skills
comunitarias. Spatie confirma que sus guías ahora existen como Skills
reutilizables para varios asistentes, no solo Boost.

#### Skills recomendadas para BruceFireSacv2

Estas son las que sí encajan con el stack real del proyecto
(Laravel 13, PHP 8.3, Inertia React, Pest, PHPStan/Larastan, Tailwind 4) y con el tipo de sistema: comercial, técnico, fiscal y con auditoría.

| Prioridad | Skill                                                    | Comando                                                                                | Uso en Bruce Fire                                                                                                         | Decisión                                                                                                    |
| --------- | -------------------------------------------------------- | -------------------------------------------------------------------------------------- | ------------------------------------------------------------------------------------------------------------------------- | ----------------------------------------------------------------------------------------------------------- |
| Alta      | `spatie-laravel-php`                                     | `php artisan boost:add-skill spatie/guidelines-skills --skill spatie-laravel-php`      | Estándar general de PHP/Laravel: tipado, PSR-12, controladores, validación, Blade, convenciones de rutas y estilo Spatie. | **Instalar**. Es la skill más alineada con el estilo que el usuario ya mencionó ("sabía yo de Spatie").     |
| Alta      | `laravel-inertia-react`                                  | `php artisan boost:add-skill asyrafhussin/agent-skills --skill laravel-inertia-react`  | Formularios Inertia, page props, layouts persistentes, páginas React conectadas a controladores Laravel.                  | **Instalar** cuando empiece Fase 3 (pantallas reales).                                                      |
| Alta      | `laravel-best-practices`                                 | `php artisan boost:add-skill asyrafhussin/agent-skills --skill laravel-best-practices` | Convenciones Laravel 13 para controladores, modelos, migraciones, servicios y validación.                                 | **Instalar** si no se instala una skill equivalente de arquitectura; no duplicar con muchas similares.      |
| Alta      | `laravel-testing`                                        | `php artisan boost:add-skill asyrafhussin/agent-skills --skill laravel-testing`        | Pest 4 / PHPUnit 12, factories, HTTP tests, autenticación y assertions de BD.                                             | **Instalar** antes de Fase 2-3 para que las migraciones y módulos nazcan con pruebas.                       |
| Alta      | `laravel-security` o `laravel-security-affaan-m`         | `php artisan boost:add-skill affaan-m/ecc --skill laravel-security`                    | Autenticación, autorización, policies, CSRF, XSS, uploads, `.env`, producción y auditoría de seguridad.                   | **Instalar una sola variante**; preferir `affaan-m/ecc` para evitar duplicado con `everything-claude-code`. |
| Media     | `laravel-verification` o `laravel-verification-affaan-m` | `php artisan boost:add-skill affaan-m/ecc --skill laravel-verification`                | Checklist de verificación: lint, PHPStan/Larastan, tests, seguridad y readiness antes de merge/deploy.                    | **Instalar una sola variante**; útil para Fase 5 y cierres por módulo.                                      |
| Media     | `laravel-queues`                                         | `php artisan boost:add-skill asyrafhussin/agent-skills --skill laravel-queues`         | Facturación SUNAT, PDFs, emails, backups, reportes grandes, reintentos y fallos.                                          | **Instalar cuando se implementen jobs/colas**; no es urgente en diseño.                                     |
| Media     | `laravel-pdf`                                            | `php artisan boost:add-skill spatie/laravel-pdf --skill laravel-pdf`                   | Generación de PDF con `spatie/laravel-pdf`: certificados, comprobantes, reportes.                                         | **Instalar cuando empiece Certificados/PDF**; tiene pocas instalaciones, pero viene del repo del paquete.   |

#### Skills útiles pero no prioritarias

| Skill                                            | Motivo                                                                                                                                                                                                                        |
| ------------------------------------------------ | ----------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| `laravel-specialist`                             | Muy instalada y amplia, pero mezcla Sanctum, Horizon, Livewire y patrones generales. Puede servir si se quiere una skill "todo terreno", aunque se solapa con `laravel-best-practices`, `laravel-testing` y `laravel-queues`. |
| `php-pro` / `php-best-practices`                 | Buenas para PHP puro, pero el proyecto ya está fuertemente en Laravel; usar solo si se trabaja en servicios PHP complejos fuera de controladores/modelos.                                                                     |
| `laravel-patterns` / `laravel-patterns-affaan-m` | Similar a `laravel-best-practices`. Elegir una familia, no ambas.                                                                                                                                                             |
| `laravel-project-patterns`                       | Interesante porque intenta derivar convenciones desde el propio repositorio; se evalúa luego, cuando el proyecto tenga más módulos propios ya implementados.                                                                  |
| `laravel-mcp` / `php-mcp-server-generator`       | No aplica ahora; Bruce Fire no está construyendo un servidor MCP propio.                                                                                                                                                      |

#### Skills descartadas para este proyecto

| Skill                                                                               | Por qué no                                                                                                                  |
| ----------------------------------------------------------------------------------- | --------------------------------------------------------------------------------------------------------------------------- |
| `wp-phpstan`                                                                        | Es para WordPress, no Laravel.                                                                                              |
| `laravel-inertia-vue`, `shadcn-vue`                                                 | El proyecto usa React, no Vue.                                                                                              |
| `laravel-livewire` / skills centradas en Livewire                                   | El starter usa Inertia + React. No mezclar frameworks de UI.                                                                |
| Skills genéricas de documentos (`word-documents`, `analyze-document`, `markitdown`) | No resuelven generación de certificados del sistema; para eso ya se decidió `spatie/laravel-pdf` + plantillas HTML propias. |
| Skills de `laravel-11-12-app-guidelines`                                            | La app es Laravel 13; solo serviría si aparece una versión explícita para Laravel 13.                                       |

#### Regla de adopción

No instalar Skills en masa. Se instalan por fase:

1. **Fase 0-2**: `spatie-laravel-php`, `laravel-best-practices`,
   `laravel-testing`, `laravel-security`.
2. **Fase 3 (vistas Inertia/React)**: `laravel-inertia-react`.
3. **Facturación/PDF/Certificados**: `laravel-pdf`, y `laravel-queues`
   si los PDF/envíos SUNAT pasan a jobs.
4. **Cierre/calidad**: `laravel-verification`.

Si dos Skills cubren lo mismo, se elige una sola. El criterio será:
compatibilidad con Laravel 13/PHP 8.3, auditorías en verde, repositorio
fuente confiable, y que no empuje tecnologías que el proyecto no usa
(Livewire, Vue, WordPress, APIs genéricas sin necesidad).

---

## Estado de esta fase

**Fase actual: Fase 1 (Diseño)** — en curso.

### Historial de la Fase 1

1. Mockups generados con Stitch (34 pantallas) — **descartados como
   proceso**: se generaron independientes, sin navegación real entre
   ellas (verificado en Vista Previa: los enlaces del sidebar no
   navegaban a ninguna parte). Quedan solo como referencia de
   contenido, no como base de diseño.
2. Antes de rediseñar, se congeló el **mapa de módulos, conexiones y
   permisos por rol** completo (27 módulos, incluyendo el módulo de
   IA que se había omitido) en
   [01_MODULOS_ROLES_PERMISOS.md](01_MODULOS_ROLES_PERMISOS.md) y su
   [artefacto visual](https://claude.ai/artifact/4Z3eJX4keKoukPgbfv1qmm) —
   aprobado como base antes de volver a diseñar.
3. **Diseño real hecho directamente por Claude** (no IA de terceros),
   con enlaces reales entre pantallas, en:
   [BRUCE FIRE — Diseño de Producto](https://claude.ai/artifact/VGmQFb4stTRJKPNdd3WQZk).
   Paleta: rojo de marca real `#E31E24` (del isotipo oficial), grafito
   `#1A1A1D`, tipografía Oswald (encabezados) + IBM Plex Sans (texto) +
   IBM Plex Mono (datos/códigos) — mismo lenguaje visual que los
   documentos de planificación, para que todo el proyecto se vea como
   un solo sistema.

    **Fase 1.1 — entregada**: Login + Dashboard de los 5 roles
    (Gerente, Vendedor, Almacén, Técnico de Planta, Técnico de Campo en
    versión móvil), con el sidebar de cada rol reflejando exactamente
    los módulos que le corresponden según la matriz de permisos.
    Pendiente de aprobación del usuario antes de continuar.

    **Próximas sub-fases** (una vez aprobada 1.1): 1.2 Comercial
    (Clientes, Cotizaciones, Ventas, Cobranzas) · 1.3 Operación técnica
    (Inventario, Órdenes de Servicio, Checklist, Instalaciones,
    Deficiencias, Adicionales, Custodia, Acta de Conformidad) · 1.4
    Certificados y Fiscal SUNAT · 1.5 Gestión (Reportes, Roles y
    Permisos, Auditoría, Asistente IA).
