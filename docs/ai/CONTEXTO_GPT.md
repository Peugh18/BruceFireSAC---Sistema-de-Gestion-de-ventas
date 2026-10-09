# Contexto para continuar (de Claude a GPT)

## Proyecto

"Sistema web para la gestión de ventas en BRUCE FIRE S.A.C." (proyecto UPN, alcance fijado por el DRS v3.1). Stack: Laravel 13, PHP 8.3, Inertia v3 + React 19, MySQL (Laragon), Pest, Greenter 5.3 (SUNAT **solo beta**). Repo local: `D:\TiomiguelonGgs\Documents\BRUCE FIRE\BruceFireSacv2`, rama `fix/correcciones-auditoria-completa`. Antes de tocar nada lee `docs/ai/PROYECTO.md`, `docs/ai/PLAN.md`, la última entrada de `docs/ai/BITACORA.md` y `.ai/rules/`.

Usuarios de prueba (contraseña `password`): vendedor@, gerente@, almacen@, tecnico.planta@ y tecnico.campo@brucefire.pe. Rutas con prefijo `/bruce-fire/<rol>/...`.

## Qué hizo Claude en esta sesión

1. **Reparó el entorno.**
    - Ruta del certificado SUNAT en `.env`: apuntaba a la carpeta vieja del proyecto y daba un 500 al emitir.
    - 9 migraciones pendientes, con respaldo previo en `storage/app/backups/`.
    - Levantó un servidor de prueba en el puerto 8010, porque el de 8000 tenía cargado el `.env` viejo.
2. **Auditoría E2E real en el navegador, rol por rol**, con datos de prueba:
    - Venta, boleta y nota de crédito aceptadas en SUNAT beta, con aprobación del Gerente.
    - Orden de planta completa.
    - Recojo de campo con firma.
    - Ajuste de stock.
    - Aislamiento entre roles.
3. **Corrigió errores de lógica**, con pruebas (commit `b79f7de`):
    - Ya no se cobra una orden anulada.
    - Los adicionales ya resueltos se cobran, y se avisan los aprobados sin cotización.
    - Un extintor no entra en dos órdenes abiertas.
    - Los paneles de técnicos ya no muestran anuladas y sus KPIs se limitan a su sede o técnico.
    - El vendedor ve la falla, el extintor y la foto de la deficiencia.
    - Textos y fechas de pantalla.
4. 65 pruebas de las zonas tocadas pasan. Se registró en `docs/ai/BITACORA.md` (commit `c40ab49`). **No se hizo push**: lo hace el usuario.

## Pendientes (para ti)

- KPIs del Gerente (`Gerente/DashboardController`): las ventas y la facturación del mes no restan las notas de crédito.
- Accesibilidad: hay botones, tabs y links solo con ícono, sin `aria-label`. Pasa en técnicos, órdenes, ajustes y caja.
- Traslados: falta un mensaje cuando no hay otro almacén de destino.
- Un certificado SUNAT mal configurado da 500: debe mostrar un mensaje claro.
- Títulos de eventos sin tildes ("Alta tecnica rapida", "Recepcion planta") en la conversación de la orden.
- El scheduler no corre: no hay respaldos desde el 27/09 ni envío diferido.
- Correr la suite completa con `composer ci:check`.

## Reglas

- Respuestas en español.
- Nada de push ni de dependencias nuevas sin permiso.
- SUNAT solo en beta.
- Pruebas breves y focalizadas; la suite completa va al final.
- Formatear con `vendor/bin/pint --dirty` en PHP.
- En TS/TSX **no** correr `npx prettier` sin opciones, porque el proyecto no tiene config y reescribe todo el archivo. Usar `--single-quote --tab-width 4`, o mejor, solo editar a mano.

## Por qué Claude se quedó sin contexto ("tokens")

No fue un error del sistema. La sesión fue muy larga y casi todo el gasto vino de la **auditoría en navegador**:

- **Capturas de pantalla y lectura de páginas completas**: cada pantalla devuelve miles de tokens, y se revisaron decenas.
- **Muchos pasos de clic → esperar → leer** por cada rol (5 roles, unos 15 flujos), cada uno con su respuesta larga.
- **Lectura de archivos grandes** (`SaleController`, vistas `.tsx` de 800+ líneas) y salidas largas de pruebas.
- Mismo hilo con trabajo previo: auditorías, prototipos y documentos SUNAT.

Cuando el contexto se llena, la herramienta **resume la conversación y sigue** (se "compacta"). Por eso parece que "se acabó". Para gastar menos la próxima vez:

- Probar los flujos con **pruebas Pest o peticiones HTTP** en vez de clics y capturas.
- Usar el navegador solo para lo visual.
- Leer solo fragmentos de archivo, no archivos enteros.
- Abrir una sesión nueva por tema (por ejemplo "solo accesibilidad" o "solo KPIs").
