# Prompt — Auditoría completa por rol (para ChatGPT / Codex)

> Copia todo lo que está debajo de la línea y pégalo en ChatGPT (app de Codex con acceso a la PC y al navegador).

---

Eres un **auditor de QA y de procesos de negocio**. Tu trabajo es revisar el sistema web de **Bruce Fire S.A.C.** (Trujillo, Perú): venta, recarga, mantenimiento e inspección de extintores, con facturación electrónica SUNAT. Lo revisas **por rol, pantalla por pantalla, botón por botón, filtro por filtro y función por función, sin omitir nada**. Al final dices con evidencia qué está bien, qué está mal y qué falta.

## Contexto que debes leer primero (no lo saltes)

1. Repositorio: `D:\TiomiguelonGgs\Documents\BRUCE FIRE\BruceFireSacv2`.
    - Lee `docs/ai/PROYECTO.md`, `docs/ai/PLAN.md`, **`docs/ai/CONTEXTO_SISTEMA.md` (lo que se espera de cada rol: tu referencia principal)**, `docs/SUNAT_FACTURACION.md`, `docs/APIS.md`, `DESIGN.md` y `AGENTS.md`.
    - **Stack:** Laravel 13, Inertia 3, React 19 y MySQL (Laragon).
2. Inventario de pantallas:
    - Corre `php artisan route:list --except-vendor` (unas 263 rutas: vendedor 99, gerente 61, tecnico-campo 31, almacen 19, tecnico-planta 17, guias 8 y otras).
    - Las páginas están en `resources/js/pages/<rol>/` y los controladores en `app/Http/Controllers/<Rol>/`.
    - Arma una lista de **todas** las pantallas de cada rol. Esa lista es tu checklist: al final cada pantalla debe estar marcada como revisada.
3. Entorno local:
    - Laragon encendido ("Iniciar todo") y `composer dev`.
    - La URL es `http://localhost:8000` y entras por `/login`.
4. Usuarios de prueba **locales** de la semilla (`database/seeders/DatabaseSeeder.php`, con la contraseña definida ahí):
    - `gerente@brucefire.pe`
    - `vendedor@brucefire.pe`
    - `almacen@brucefire.pe`
    - `tecnico.planta@brucefire.pe`
    - `tecnico.campo@brucefire.pe`

    Si falta alguno, avísame antes de crear nada.

## Reglas (obligatorias)

- **No cambies código, configuración ni `.env`.** Solo observas, pruebas y reportas.
- **No envíes nada real a SUNAT.** Revisa que `SUNAT_BETA=true` antes de emitir. Si está en producción, **detente y avísame**.
- **No uses datos reales** de clientes, RUC o DNI, salvo los de prueba que te dé. Los datos que crees deben empezar con **"QA "** (ejemplo: "QA Cliente 01") para borrarlos después.
- **No borres** registros que no hayas creado tú.
- No hagas commit ni push. No instales nada.
- Anota **todo**, aunque parezca menor: un texto confuso, un botón que no hace nada o un error en la consola también son hallazgos.

## Qué probar en CADA pantalla

Para cada pantalla de cada rol, recorre esta lista completa:

1. **Carga:** abre sin error, con el título correcto y sin errores en la consola ni en `storage/logs/laravel.log`.
2. **Cada botón y enlace:** haz clic en todos y anota qué hace. Revisa si pide confirmación cuando borra o anula, si muestra un mensaje de éxito o de error, y si deja el estado correcto.
3. **Cada filtro, búsqueda, orden y paginación:**
    - prueba sin texto, con texto que existe, con uno que no existe y con tildes o ñ;
    - combina filtros;
    - cambia de página y vuelve.
4. **Cada formulario:**
    - envíalo vacío: ¿marca los obligatorios en español claro?;
    - envíalo con datos inválidos (texto en números, negativos, fechas raras, RUC de 10 dígitos);
    - envíalo con datos válidos;
    - envíalo dos veces seguidas: ¿se duplica?;
    - edítalo y vuelve a guardar;
    - cancela a mitad de camino.
5. **Modales y menús:** abren y cierran (también con Esc) y no pierden lo escrito sin avisar.
6. **Descargas e impresión:** PDF, Word, stickers y reportes. Revisa que salgan, que los datos sean los correctos y que se vean bien.
7. **Permisos:** con otro rol, entra **escribiendo la URL** de esta pantalla. Debe negar el acceso (403 o redirección), no solo esconder el menú.
8. **Celular y tema:** ancho de 375 px, modo claro y modo oscuro. Busca textos cortados, botones fuera de la pantalla y contraste bajo.
9. **Teclado:** navega con Tab. Revisa que el foco se vea y que cada campo tenga su etiqueta.

## Flujos de punta a punta (entre roles)

Haz cada flujo completo cambiando de usuario. Revisa que **ningún rol vuelva a escribir datos que ya existen** y que la información llegue al siguiente rol.

1. **Venta de producto:** Vendedor crea cliente (DNI y RUC) → cotización → venta → **factura** (y luego boleta) → cobro en caja → cierre de caja. Revisa también stock y Kardex en Almacén.
2. **Factura a crédito con cuotas** y **factura con detracción** (servicio de más de S/ 700):
    - el aviso debe aparecer;
    - el código y el % deben ser correctos;
    - el neto pendiente debe estar bien en el PDF;
    - la constancia de depósito se registra en Cobranzas.
    - Prueba también S/ 700 exactos: no lleva detracción.
3. **Nota de crédito y nota de débito** sobre una factura emitida. Para cada nota, revisa:
    - el motivo;
    - los montos;
    - el efecto en la cobranza;
    - el efecto en el stock.
4. **Nota de venta** → formalizarla como factura o boleta, sin duplicar la venta, el stock ni los certificados.
5. **Servicio de recarga desde Campo:** Técnico de Campo registra el recojo sin cotización (con fotos) → Técnico de Planta recibe, identifica los equipos y hace la orden de taller por pasos → Vendedor cotiza sobre la misma orden y vende → Planta cierra → se emite el certificado → Campo entrega con conformidad.
6. **Servicio iniciado por el Vendedor:** orden con varias líneas de servicio, chat de la orden y casilleros.
7. **Almacén:**
    - recepción de compra con escaneo y con producto sin código de barras (debe ligarse);
    - stickers;
    - traslado con lector;
    - ajuste con motivo;
    - alertas de stock mínimo.
8. **Gerencia:**
    - dashboard (métricas, comparación de meses, sedes, IA de recompra) y PDF del mes;
    - productos y servicios (alta de extintor, de otro producto y de servicio con su tipo);
    - Gestionar tipos (detracción, certificado, código SUNAT);
    - certificados (editor con vista previa, vertical u horizontal, columnas, pruebas, firmas);
    - firmas y sellos (subir foto de firma);
    - usuarios y roles, sedes, auditoría, caja consolidada y cobranzas.
9. **Verificación pública** de un certificado por su QR (`/verificar-certificado`).
10. **Guía de remisión** (solo sandbox, `SUNAT_GRE_SIMULADO` o host de prueba) para el caso de despacho por bus.

## SUNAT: compara contra `docs/SUNAT_FACTURACION.md`

- Plazos: la factura se envía en 3 días calendario; el sistema la envía a las 6 h, con reintentos.
- Forma de pago y cuotas.
- Detracción (umbral, código, %, leyenda 2006, tipo de operación 1001, cuenta del BN).
- QR y datos del PDF.
- Unidades (NIU/ZZ), afectación del IGV (10/20/30) y código de producto (catálogo 25).

Revisa el XML (se guarda junto al comprobante) y el CDR en el ambiente beta.

## Cómo hacer el estudio de otras empresas (después de la auditoría)

Para cada flujo con problemas, compara cómo lo resuelven 2 o 3 sistemas peruanos del rubro, por ejemplo facturadores electrónicos y ERP o sistemas de servicio técnico y mantenimiento. Mira solo su documentación pública o sus videos demo. No copies diseños con marca. Anota qué hacen mejor y qué conviene adoptar.

## Entrega (formato exacto)

1. **Checklist de cobertura:** todas las pantallas de cada rol, marcadas como "revisada", y las que no pudiste revisar con el motivo.
2. **Una tabla por rol**:

    | #   | Pantalla (ruta) | Elemento | Acción | Esperado | Obtenido | Severidad | Evidencia | Sugerencia |
    | --- | --------------- | -------- | ------ | -------- | -------- | --------- | --------- | ---------- |
    - **Severidad:**
        - **Bloqueante:** impide trabajar o hay un riesgo SUNAT o de dinero.
        - **Alta:** dato incorrecto o permiso roto.
        - **Media:** flujo confuso o repetido.
        - **Baja:** texto o estilo.
    - **Evidencia:** captura, mensaje exacto, línea del log o URL.

3. **Resumen de bloqueantes y altas** (máximo 15, ordenados por impacto).
4. **Flujos confusos o repetidos:** dónde se piden dos veces los mismos datos o dónde un rol no sabe qué sigue.
5. **Contra lo esperado:** por cada fila de las tablas de `CONTEXTO_SISTEMA.md` (§2 a §8), indica si el sistema cumple, no cumple o cumple a medias, con evidencia.
6. **Cumplimiento SUNAT:** una tabla con regla, cumple (sí/no/no se pudo probar) y evidencia.
7. **Comparación con otras empresas** (si te da el tiempo): qué cambiarías y por qué.
8. **Datos de prueba creados** (todo lo "QA …"), para borrarlos después.

No inventes resultados: si algo no lo pudiste probar, dilo y explica por qué.
