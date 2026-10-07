# Auditoría: Técnico de planta, técnico de campo y comunicación

Autor: Claude. Fecha: 2026-10-06. Detalle de la comparación con empresas grandes: `docs/ai/anexos/investigacion-campo-y-comunicacion.md`.

## Resumen

- El esqueleto de los dos roles existe: órdenes, recepción, checklist por extintor, deficiencias, recojo, entrega, inspección e instalación.
- **No se puede subir ninguna foto ni recoger una firma en ninguna pantalla.** Esto es la base de §22–§25 y §33.
- **El mantenimiento en sitio no existe.**
- La comunicación es solo de ida: el vendedor escribe y el técnico lee las últimas 10 notas, sin adjuntos.
- El tipo de agente del extintor es texto libre o no existe. El certificado de un extintor vendido pone "PQS-ABC" por defecto.

## Funcionalidades

### Técnico de planta

| Funcionalidad                             | ¿Funciona?                         | ¿Cumple el doc?    | Problema                                                                                      | Archivo:línea                                                                    |
| ----------------------------------------- | ---------------------------------- | ------------------ | --------------------------------------------------------------------------------------------- | -------------------------------------------------------------------------------- |
| Cola de taller (dashboard)                | ✅ Sí, tras arreglar 2 enlaces 404 | ✅ §5.4            | El botón "Recepción" y el clic en una orden daban 404. **Corregido el 2026-10-06.**           | `resources/js/pages/tecnico-planta/dashboard.tsx`                                |
| Recepción y alta técnica rápida           | ✅                                 | ✅ §18, §22.2      | —                                                                                             | `app/Http/Controllers/TecnicoPlanta/ReceptionController.php`                     |
| Checklist por extintor                    | ✅                                 | ⚠️ §19             | 14 ítems fijos en código; no se reutiliza para otros servicios (lo pidieron las notas de voz) | `app/Actions/Tecnico/ProcessChecklist.php:21-35`                                 |
| Foto en ítem observado o deficiencia      | ❌                                 | ❌ §19.2, §20, §33 | Existe la columna `foto_path`, pero ninguna pantalla sube fotos                               | `DeficiencyController.php:117`, migración `..._create_deficiencies_table.php:20` |
| Registrar deficiencia fuera del checklist | ❌                                 | ⚠️ §20             | La ruta `deficiencias.store` existe sin formulario                                            | `routes/tecnico-planta.php:37`                                                   |
| Ejecución, repuestos y avance de estado   | ✅                                 | ✅                 | No hay acceso al checklist desde la ejecución                                                 | `ExecutionController.php:139`, `ejecucion/show.tsx:393`                          |
| Comunicación con el vendedor              | ⚠️ Solo lectura                    | ⚠️ §17             | El técnico ve las últimas 10 notas; no puede responder ni adjuntar                            | `ExecutionController.php:31`, `ejecucion/show.tsx:229-258`                       |

### Técnico de campo

| Funcionalidad                              | ¿Funciona?   | ¿Cumple el doc? | Problema                                                                                                                   | Archivo:línea                                                                          |
| ------------------------------------------ | ------------ | --------------- | -------------------------------------------------------------------------------------------------------------------------- | -------------------------------------------------------------------------------------- |
| Recojo                                     | ⚠️           | ❌ §22.1        | Sin foto por equipo ni firma; solo la casilla "conformidad". `foto_path` es texto suelto sin carga de archivo.             | `TecnicoCampo/CollectionController.php:155-156`                                        |
| Entrega y acta                             | ⚠️           | ❌ §22.3, §23   | Nombre y DNI del receptor, sin foto ni firma. El acta PDF sale sin firma ni fotos.                                         | `DeliveryController.php:123-148`, `resources/views/pdf/acta-conformidad.blade.php:259` |
| Inspección                                 | ⚠️           | ⚠️ §24          | Checklist por extintor sí; fotos y firma no                                                                                | `InspectionController.php`                                                             |
| Instalación                                | ⚠️           | ❌ §25          | Por orden, con una lista suelta; no escanea la unidad vendida. Las fotos de antes y después son campos de texto sin carga. | `InstallationController.php:119-141`, `instalaciones/show.tsx:126-127`                 |
| Mantenimiento en sitio                     | ❌ No existe | ❌ §35.5        | No hay ruta, controlador ni pantalla                                                                                       | `routes/tecnico-campo.php`                                                             |
| Estados de la visita (en camino, en sitio) | ❌           | —               | Los estados de la orden piensan en planta                                                                                  | `app/Models/ServiceOrder.php:60-64`                                                    |
| Uso en celular                             | ⚠️           | ⚠️ notas de voz | Diseño responsive, pero sin cámara (`capture`) ni firma táctil                                                             | `resources/js/pages/tecnico-campo/**`                                                  |

## Datos duplicados y relaciones con otros módulos

1. 🔴 **Tipo de agente del extintor.** Hoy vive en varios lugares a la vez, con calidades distintas:
    - **Producto:** no tiene agente. Se adivina del nombre con `EquipmentType::fromDescription()` (`app/Enums/EquipmentType.php`).
    - **Unidad del almacén** (`InventoryUnit`): no tiene agente.
    - **Equipo creado al vender:** queda sin agente (`app/Actions/Sales/ProcessSaleItem.php:149-160`).
    - **Equipo recibido en planta o campo:** texto libre de hasta 50 o 100 caracteres (`QuickRegisterEquipment.php:33`, `CollectionController.php:179`, `InstallationController.php:133`).
    - **Certificado:** si falta, usa `'PQS-ABC'` (`app/Actions/Certificates/IssueCertificate.php:231`).
    - `CambiarUnidadVendida.php:139` lee `$unit->tipo_agente`, un atributo que `InventoryUnit` no tiene, así que siempre es null.

    **Propuesta:** un solo origen, el agente y la capacidad del **producto** (lista del enum `EquipmentType`). La unidad y el equipo lo heredan. Para equipos de clientes, el técnico elige de la misma lista, no escribe texto libre.

2. **Fotos:** columnas `foto_path`, `foto_antes_path` y `foto_despues_path` repartidas en varias tablas, ninguna en uso. Propuesta: un solo registro de evidencia (§33) y borrar esas columnas después de migrar.
3. **Comunicación y bitácora:** `ServiceOrderEvent` ya es la línea de tiempo de la orden. La conversación nueva debe **reutilizar** esa tabla (con autor, `equipment_id` opcional y adjuntos), no crear un chat aparte.
4. **Checklist:** planta (`ProcessChecklist`) e inspección de campo guardan checklists por separado. Conviene una sola plantilla por tipo de servicio.

## Comparación con empresas grandes

| Tema                   | Cómo lo hacen                                                                                                | Bruce Fire                         | Veredicto                          |
| ---------------------- | ------------------------------------------------------------------------------------------------------------ | ---------------------------------- | ---------------------------------- |
| Comunicación           | Conversación por orden con fotos y archivos (Chatter de Salesforce y Odoo, chat del trabajo de ServiceTitan) | Notas de solo lectura              | ⚠️ Ampliar sobre la misma bitácora |
| Activo                 | Escanear el código → checklist → historial (Uptick, Inspect Point, BuildingReports)                          | Código BF-EQ y alta rápida         | ✅                                 |
| Tipos de trabajo       | Un motor con plantillas (worksheets de Odoo)                                                                 | Cuatro pantallas con lógica propia | ⚠️ Unificar                        |
| Firma                  | Táctil, impresa en el reporte                                                                                | Casilla de conformidad             | ❌                                 |
| Evidencia              | Foto por deficiencia, antes y después                                                                        | Ninguna                            | ❌                                 |
| Visita                 | Asignada → en camino → en sitio → terminada                                                                  | No existe                          | ⚠️                                 |
| Préstamo de extintores | Común en recargas                                                                                            | No existe                          | ❓                                 |
| Sin señal              | Offline y sincroniza después                                                                                 | Necesita conexión                  | ⚠️ Más adelante (PWA)              |

## Propuestas priorizadas

1. 🔴 **Crítico**
    - Tipo de agente con un solo origen (producto → unidad → equipo → certificado). Bloquear el certificado si falta.
    - Evidencia fotográfica única (§33) y firma táctil: sin ellas, el acta y la cadena de custodia no tienen respaldo.
2. 🟠 **Importante**
    - Conversación de la orden: todos escriben, con foto, audio y archivo, y la etiqueta del equipo.
    - Motor único de visitas de campo, con mantenimiento incluido.
    - Instalación por unidad escaneada.
3. 🟢 **Mejora**
    - Estados de visita: en camino y en sitio.
    - Checklist por plantilla según el servicio.
    - Acceso al checklist desde la ejecución.
    - Modo sin señal.

## Preguntas para el dueño

1. ¿Bruce Fire deja extintores de préstamo cuando recoge para recarga?
2. ¿Los técnicos de campo trabajan seguido en zonas sin señal?
3. ¿Quién puede escribir en la conversación de la orden: también el gerente? ¿Y el cliente, más adelante?
