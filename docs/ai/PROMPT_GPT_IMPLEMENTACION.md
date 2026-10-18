# Prompt — GPT/Codex audita y luego implementa los cambios de CONTEXTO_SISTEMA §10

> Copia todo lo que está debajo de la línea en Codex/ChatGPT con acceso al repositorio.

---

Eres el **jefe de respaldo** del proyecto Bruce Fire S.A.C., un sistema de extintores en Trujillo (Perú). El repositorio está en `D:\TiomiguelonGgs\Documents\BRUCE FIRE\BruceFireSacv2`, rama `feat/ordenes-varios-servicios`.

Trabajas en **dos etapas**: primero **auditas**, después **corriges por fases**. No saltes la auditoría.

### Lee antes de todo

- `docs/ai/PROYECTO.md`, `docs/ai/PLAN.md`, la última entrada de `docs/ai/BITACORA.md` y **`docs/ai/CONTEXTO_SISTEMA.md`**. Este último explica lo que se espera de cada rol; las decisiones del dueño están en §9 y los cambios en §10.
- `AGENTS.md`, `.ai/rules/index.md` y sus reglas, y `DESIGN.md`. Las normas SUNAT están en `docs/SUNAT_FACTURACION.md` y las APIs en `docs/APIS.md`.
- **Stack:** Laravel 13, PHP 8.3, Inertia 3, React 19 + TS, Tailwind 4, Wayfinder y Pest. La base es MySQL en Laragon.

### Reglas obligatorias

- **Commits y dependencias:**
    - no hagas commit ni push;
    - no agregues dependencias, excepto **`recharts`**, que está aprobada.
- **Archivos protegidos:**
    - no leas ni cambies `.env` ni los certificados reales de SUNAT; las pruebas usan `tests/Fixtures/certificates/test-certificate.pem`;
    - no agregues errores a `phpstan-baseline.neon`.
- **Forma de trabajar:**
    - antes de un cambio de lógica escribe la prueba Pest que falla (TDD);
    - corre solo las pruebas afectadas, una corrida a la vez y nunca en paralelo: `php artisan test --compact <archivo>`.
- **Al terminar cada fase:**
    - `vendor/bin/pint --dirty --format agent`;
    - `vendor/bin/phpstan analyse`;
    - `npm run types:check`;
    - `npm run check`;
    - si cambiaste rutas: `php artisan wayfinder:generate --with-form`.
- **Textos:** la interfaz va en español claro, sin jerga, y usa los tokens de `DESIGN.md`.
- **Nombres:** usa los que ya tiene el sistema. **No inventes nombres de lugares ni de roles.**
- **Ante una duda de negocio:** pregúntame. No supongas.

### Lugares del negocio (decisión del dueño)

- **Tienda**: venta.
- **Almacén**: stock. Hay un usuario de almacén por cada punto de venta.
- **Taller**: el lugar donde se hacen las recargas. Tiene dirección aparte y atiende a **todas** las tiendas. Ahí adentro trabajan los **Técnicos de Planta**, y de ahí salen los Técnicos de Campo. El sistema ya usa "orden de taller".
- **Hoy:**
    - `sedes.tipo` solo admite `tienda`, `almacen` o `mixta` (tienda con almacén);
    - `User::TIPOS_DE_SEDE_POR_ROL` amarra a los técnicos a un almacén;
    - `Sede::idsAtendidosPor()` / `idsQueAtienden()` / `idsComercialesAtendidasPor()` limitan qué órdenes ve el técnico.
    - Por eso no se puede crear bien un técnico. **Verifica esto en la auditoría.**

### Etapa 1: Auditoría (sin cambiar código)

Recorre el código y, con el sistema levantado en local (`composer dev`, `http://localhost:8000`), también la interfaz. Usa los usuarios locales de `database/seeders/DatabaseSeeder.php`.

Para cada uno de los 8 cambios de CONTEXTO_SISTEMA §10, reporta:

1. **Cómo funciona hoy**, con archivo:línea.
2. **Qué está mal o qué falta** frente a lo que pide el dueño.
3. **Qué archivos tocarías**, todos los llamadores incluidos. Busca con grep cada uso antes de proponer un cambio.
4. **Riesgos:**
    - pruebas existentes que se romperían;
    - datos ya guardados;
    - SUNAT;
    - versiones de certificados ya emitidos.
5. **La solución más simple que funciona.** Prefiere un cambio en el método compartido antes que parches en cada llamador.

Los 8 cambios:

1. **Tipo de sede Taller.**
    - Técnicos de Planta y de Campo pertenecen al Taller y ven y atienden las órdenes de todas las tiendas.
    - Un recojo de Campo con varias tiendas pide elegir la tienda.
    - El stock de repuestos sigue saliendo del almacén de la tienda de la orden.
    - Se arregla la creación de técnicos en Usuarios y Sedes.
2. **Traslados:** solo se ven en el menú y en la ruta si hay 2 o más almacenes activos.
3. **Stickers:** al imprimir se elige el rollo de 80×50 mm (como hoy) o una hoja A4 con varias etiquetas, por ejemplo 2×5. Revisa `StickerPdfService` y todas las pantallas que imprimen stickers.
4. **BI con gráficos (`recharts`) para los 5 roles**, según CONTEXTO_SISTEMA §3 a §6. Sin ticket promedio.
5. **Módulo de IA propio del gerente**, que reutiliza `ClientRetentionScore` y los datos históricos.
6. **Editor de certificados profesional:**
    - compara **pdfme** (drag/drop) con mejorar el editor actual (`resources/js/pages/gerente/configuracion/certificado-editor.tsx`, `hoja-certificado.tsx` y `CertificatePdfService` con dompdf);
    - respeta las versiones ya emitidas (`certificate_type_versions`).
7. **Técnico de Campo:** instalación, pozo a tierra y mantenimiento llenan las columnas del certificado sin volver a escribir datos.
8. **Producto sin código de barras:** código interno y sticker por producto. Almacén liga los códigos al recibir.

**Entrega de la etapa 1:**

- una tabla por cambio (hoy, problema, archivos, riesgos, solución);
- un orden recomendado;
- tus preguntas para el dueño.

**Espera mi aprobación antes de la etapa 2.**

### Etapa 2: Corregir por fases (después de mi aprobación)

- Hazlo en este orden, una fase a la vez:
    - Fase 1 = cambios 1 a 3;
    - Fase 2 = cambio 4;
    - Fase 3 = cambio 5;
    - Fase 4 = cambio 6;
    - Fase 5 = cambio 7;
    - Fase 6 = cambio 8.
- En cada fase:
    1. pruebas que fallan;
    2. código;
    3. pruebas afectadas en verde;
    4. los chequeos de las reglas;
    5. revisión en el navegador en escritorio y en celular, con tema claro y oscuro, y axe sin violaciones en las pantallas tocadas.
- Al cerrar cada fase:
    - deja una entrada en `docs/ai/BITACORA.md` con lo hecho, la evidencia (salida de las pruebas) y el siguiente paso;
    - actualiza `docs/ai/PLAN.md`;
    - **detente y muéstrame el resultado** antes de seguir.
- No des nada por terminado sin evidencia. Si algo no pudiste probar, dilo.
