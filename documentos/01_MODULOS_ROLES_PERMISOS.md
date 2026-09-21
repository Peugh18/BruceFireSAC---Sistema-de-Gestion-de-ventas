# BRUCE FIRE — Mapa de Módulos, Conexiones y Permisos por Rol

Versión visual (diagrama + matriz interactiva): [Artifact — Mapa de Módulos BRUCE FIRE](https://claude.ai/artifact/4Z3eJX4keKoukPgbfv1qmm)

Este documento congela la decisión de **qué módulos existen, cómo se
conectan y qué hace cada rol**, ANTES de seguir maquetando pantallas.
El diseño visual en Stitch queda en pausa (2026-09-19) porque generó
34 pantallas desconectadas entre sí — cada sidebar/botón era solo un
dibujo, no un enlace real (verificado en modo Vista Previa: clic en
"Dashboard" no navegaba a ninguna parte). Antes de retomar diseño hay
que tener este mapa cerrado para no rehacer pantallas.

------------------------------------------------------------------------

## 0. Correcciones de esta revisión (2026-09-19, sesión de diseño)

1. **Técnico de Planta también trabaja solo desde el celular** (el
   usuario lo aclaró al ver el primer diseño de escritorio). Se
   corrige: de los 5 roles, solo **Gerente, Vendedor y Almacén** usan
   vista de escritorio; **Técnico de Planta y Técnico de Campo son
   100% móviles**. Se rehace el Dashboard de Planta en formato móvil.
2. **El sidebar de 27 módulos era demasiado plano** — se generaliza:
   varios de los "módulos" operativos (Checklist, Instalaciones,
   Deficiencias, Autorización de Adicionales, Cadena de Custodia, Acta
   de Conformidad, Comunicación por Orden, Evidencia Fotográfica) no
   son destinos de navegación propios, son **pestañas dentro del
   detalle de una Orden de Servicio** — un técnico entra a SU orden y
   ahí ve todo eso junto, no busca 8 ítems sueltos en el menú. El
   permiso fino de cada uno se mantiene igual (sección 4), solo cambia
   dónde vive en la interfaz. Sidebar generalizado a ~16-18 ítems por
   rol en vez de hasta 27.
3. **Nuevo módulo: Sedes y Almacenes** (§10) — el usuario preguntó
   cómo modelar que antes tenían 4 tiendas con un solo almacén
   compartido (misma ciudad) y ahora solo 1 tienda, previendo que al
   expandirse a otra ciudad necesitarán almacén propio ahí. Investigado:
   es el patrón estándar **hub-and-spoke** de retail multi-tienda — un
   almacén central ("hub") abastece a varias tiendas ("spokes") dentro
   de la misma ciudad/zona, y una ciudad nueva típicamente amerita su
   propio hub porque trasladar stock entre ciudades no es práctico
   ([Hub and spoke — Interlake Mecalux](https://www.interlakemecalux.com/blog/hub-and-spoke),
   [Retail ERP multi-location — Stok.ly](https://www.stok.ly/retail-erp-inventory-centric-control-for-multi-location-stores-warehouses-pos/)).

## 1. Roles del sistema

1. **Gerente / Administrador** — mismo rol por ahora; único con acceso
   al meta-módulo Roles y Permisos.
2. **Vendedor**
3. **Almacén**
4. **Técnico de Planta**
5. **Técnico de Campo**

## 2. Los 27 módulos, en 8 bloques

**Base**: Dashboard (vista según rol), Notificaciones.

**Maestros**: Clientes, Equipos del Cliente, Inventario (Productos +
Servicios + Kardex, sin "Catálogo" separado).

**Comercial**: Cotizaciones, Ventas, Cobranzas.

**Operación técnica**: Órdenes de Servicio, Checklist/Inspecciones,
Instalaciones, Alta Técnica Rápida, Deficiencias, Autorización de
Adicionales, Cadena de Custodia, Acta de Conformidad, Comunicación por
Orden, Evidencia Fotográfica, Alertas de Vencimiento.

**Certificados**: un solo "Motor de Certificados" que resuelve la
plantilla correcta por tipo (Operatividad y Garantía, Prueba
Hidrostática, Informe Técnico de Sistema de Detección, Instalación de
Lámina de Seguridad — extensible a más tipos).

**Fiscal SUNAT**: Facturación Electrónica, Notas de Crédito/Débito,
Guía de Remisión.

**Gestión**: Reportes, Roles y Permisos, Auditoría.

**Maestros** (nuevo): Sedes y Almacenes — ver sección 10.

**Inteligencia Artificial**: Asistente IA — predictivo comercial,
lectura asistida de placa/etiqueta, asistente gerencial conversacional
y resumen técnico asistido (Documento Maestro §39). Este bloque se
había omitido en la primera versión de este mapa; el usuario lo
detectó y se corrigió. La IA **nunca decide sola**: no determina si un
extintor es seguro, no inventa serie/año, no calcula tributos ni
decide reglas SUNAT, no emite comprobantes, no autoriza descuentos, no
toca inventario sin una acción transaccional real detrás (§39.5) —
siempre sugiere y una persona confirma.

## 3. Flujo de datos entre módulos (resumen)

```
Cliente → Equipo del Cliente
Cliente → Cotización → Venta
Inventario → Cotización / Venta (ítems)
Venta → Equipo del Cliente (alta automática al vender extintor)
Venta → Facturación Electrónica → Notas de Crédito/Débito
Venta (crédito) → Cobranzas
Venta (traslado) → Guía de Remisión
Venta (servicio) → Orden de Servicio
Orden de Servicio → Checklist/Inspección → Deficiencia → Autorización de Adicional
Orden de Servicio → Instalación / Alta Técnica Rápida / Cadena de Custodia
Cadena de Custodia → Acta de Conformidad
Checklist + Acta + Instalación → Motor de Certificados → Alertas de Vencimiento
Venta + Inventario + Certificados + Cobranzas → Reportes
Roles y Permisos → controla el acceso a TODOS los bloques anteriores
```

Ver el diagrama completo (Mermaid) en el artefacto publicado.

## 4. Matriz de permisos por rol (semilla por defecto)

Leyenda: **TOTAL** (crear/editar/eliminar) · **VER** (solo lectura) ·
**PROPIO** (solo sus propios registros) · **APRUEBA** (crea y/o
aprueba/rechaza) · **NO** (sin acceso).

| Módulo | Gerente | Vendedor | Almacén | Téc. Planta | Téc. Campo |
|---|---|---|---|---|---|
| Dashboard | TOTAL (mensual) | PROPIO (día) | PROPIO | PROPIO | PROPIO |
| Notificaciones | PROPIO | PROPIO | PROPIO | PROPIO | PROPIO |
| Clientes | TOTAL | TOTAL | VER | NO | VER |
| Equipos del Cliente | TOTAL | VER | VER | APRUEBA (certifica) | APRUEBA (campo) |
| Inventario | TOTAL | VER (para vender) | TOTAL | APRUEBA (consumo insumos) | NO |
| Cotizaciones | TOTAL | TOTAL | NO | NO | NO |
| Ventas | TOTAL | TOTAL | NO | NO | NO |
| Cobranzas | TOTAL | PROPIO (sus ventas) | NO | NO | NO |
| Órdenes de Servicio | TOTAL | APRUEBA (crea) | NO | PROPIO (taller) | PROPIO (campo) |
| Checklist / Inspecciones | VER | NO | NO | TOTAL | TOTAL |
| Instalaciones | VER | NO | NO | NO | TOTAL |
| Alta Técnica Rápida | VER | NO | APRUEBA | APRUEBA | APRUEBA |
| Deficiencias | APRUEBA | VER | NO | APRUEBA (crea) | APRUEBA (crea) |
| Autorización de Adicionales | APRUEBA | APRUEBA | NO | APRUEBA (crea) | APRUEBA (crea) |
| Cadena de Custodia | VER | NO | APRUEBA (recepción) | APRUEBA | APRUEBA |
| Acta de Conformidad | VER | NO | NO | NO | APRUEBA (firma) |
| Comunicación por Orden | VER | APRUEBA | NO | APRUEBA | NO |
| Evidencia Fotográfica | VER | NO | NO | APRUEBA | APRUEBA |
| Alertas de Vencimiento | TOTAL | PROPIO (sus clientes) | NO | NO | PROPIO (sus visitas) |
| Motor de Certificados | TOTAL | VER (imprime) | NO | APRUEBA (operatividad/P.H.) | APRUEBA (detección/lámina) |
| Facturación Electrónica | TOTAL | APRUEBA (emite) | NO | NO | NO |
| Notas de Crédito/Débito | TOTAL | APRUEBA (con aprobación) | NO | NO | NO |
| Guía de Remisión | TOTAL | APRUEBA (crea) | APRUEBA (despacho) | NO | NO |
| Reportes | TOTAL | PROPIO | PROPIO (inventario) | PROPIO | PROPIO |
| Roles y Permisos | TOTAL (único) | NO | NO | NO | NO |
| Auditoría | TOTAL | NO | NO | NO | NO |
| Asistente IA | TOTAL (predictivo + gerencial) | PROPIO (sus clientes) | PROPIO (riesgo de stock) | APRUEBA (lee, técnico confirma) | APRUEBA (lee, técnico confirma) |

## 5. Cómo Gerencia activa o retira módulos (sin tocar código)

Ninguna celda de la tabla anterior queda fija en el código. Cada una
es un permiso individual (`ventas.crear`, `inventario.ver`,
`certificados.generar`, etc.) agrupado por módulo y almacenado en base
de datos con `spatie/laravel-permission`. Solo Gerente/Administrador
tiene acceso a la pantalla **Roles y Permisos**, donde ve la misma
matriz pero editable con un interruptor por módulo y por rol — apagar
un módulo para un rol lo quita del sidebar de ese rol al instante, sin
desplegar código nuevo.

## 6. Plantillas reales de certificados (2026-09-19)

El usuario proporcionó los formatos oficiales ya en uso — **no son
diseño a mejorar, son la plantilla real** que el motor de certificados
debe reproducir con datos dinámicos. Guardadas en
`documentos/plantillas_certificados/`:

1. **Informe Técnico — Sistema de Detección y Alarma**: datos del
   servicio, objeto del informe, checklist de actividades, tabla de
   hallazgos/acción correctiva/estado final, recomendaciones,
   conclusión técnica y registro fotográfico. Lo genera Técnico de
   Campo.
2. **Certificado de Instalación de Lámina de Seguridad**: datos del
   cliente, detalle de instalación (mampara/medidas), características
   técnicas, beneficios, QR de verificación. Lo genera Técnico de
   Campo.
3. **Certificado de Prueba Hidrostática**: ficha técnica del extintor,
   presión de prueba/trabajo, vigencia a 5 años (confirma NTP 350.043,
   ya investigado en el Documento Maestro §78.2). Lo genera Técnico de
   Planta.
4. **Certificado de Operatividad y Garantía**: mismo extintor, vigencia
   anual, con check Venta/Recarga/Mantenimiento — es el de mayor
   rotación. Lo genera Técnico de Planta.
5. **Hoja de inspección (Excel de referencia)**: confirma las columnas
   exactas del módulo Checklist/Inspecciones (n° interno, serie,
   ubicación, agente, manómetro/pasador/manguera, fabricación,
   tarjeta, próx. recarga, vencimiento P.H.) — ya coincide con lo
   documentado en el Documento Maestro §24.

## 7. Correcciones aplicadas (revisión 2026-09-19, a pedido del usuario)

1. **Comunicación por Orden**: es exclusivamente Vendedor↔Técnico de
   Planta (coordinación de urgencia en taller, Documento Maestro §17).
   Técnico de Campo no participa — tiene su propio contacto directo
   con el cliente. Corregido de VER a NO.
2. **Faltaba el módulo de Asistente IA** (Documento Maestro §39): el
   usuario lo notó al revisar ("¿la IA dónde está?"). Se agregó como
   8° bloque — ver sección 2 y la fila nueva en la matriz.

## 8. Funcionalidad detallada por rol — pizarra

El detalle módulo por módulo de qué hace exactamente cada rol (no solo
el nivel de acceso, la acción concreta) vive en el artefacto como una
**pizarra tipo tablero**: una columna por rol, todas visibles al mismo
tiempo, sin necesidad de hacer clic para desplegar nada — más la
pizarra de estructura base de pantalla (wireframe, sección 07):
[Mapa de Módulos BRUCE FIRE](https://claude.ai/artifact/4Z3eJX4keKoukPgbfv1qmm) (secciones 06 y 07).

Resumen de módulos por rol (de 27 totales):

- **Gerente/Administrador**: 27 de 27 (todos; único con Roles y Permisos).
- **Vendedor**: 18 — Dashboard, Notificaciones, Clientes, Equipos del Cliente (ver), Inventario (ver), Cotizaciones, Ventas, Cobranzas, Órdenes de Servicio (crea), Deficiencias (ver), Autorización de Adicionales (aprueba), Comunicación por Orden, Alertas de Vencimiento, Motor de Certificados (ver/imprime), Facturación Electrónica, Notas de Crédito/Débito (solicita), Guía de Remisión, Reportes, Asistente IA (predictivo de sus clientes).
- **Almacén**: 9 — Dashboard, Notificaciones, Clientes (ver), Inventario, Cadena de Custodia, Alta Técnica Rápida, Guía de Remisión (despacho), Reportes (inventario), Asistente IA (riesgo de stock).
- **Técnico de Planta**: 13 — Dashboard, Notificaciones, Equipos del Cliente, Inventario (consumo de insumos), Órdenes de Servicio, Alta Técnica Rápida, Deficiencias, Autorización de Adicionales (solicita), Cadena de Custodia, Comunicación por Orden, Evidencia Fotográfica, Motor de Certificados (Operatividad/P.H.), Reportes, Asistente IA (lectura asistida + resumen técnico).
- **Técnico de Campo**: 16 — Dashboard, Notificaciones, Clientes (ver), Equipos del Cliente, Órdenes de Servicio, Checklist/Inspecciones, Instalaciones, Alta Técnica Rápida, Deficiencias, Autorización de Adicionales (solicita), Cadena de Custodia, Acta de Conformidad, Evidencia Fotográfica, Alertas de Vencimiento (sus visitas), Motor de Certificados (Detección/Lámina), Reportes, Asistente IA (lectura asistida + resumen técnico).

## 10. Sedes y Almacenes (nuevo módulo, 2026-09-19)

### El caso real de BRUCE FIRE

- **Antes**: 4 tiendas (puntos de venta) en la misma ciudad, **1 solo
  almacén** compartido entre las 4.
- **Ahora**: 1 sola tienda activa.
- **A futuro**: si abren en otra ciudad, la intuición del usuario era
  correcta — ahí sí van a necesitar almacén propio.

### Por qué (investigado)

Es exactamente el modelo **hub-and-spoke** que usa el retail
multi-tienda: un almacén central ("hub") abastece a varias tiendas
("spokes") dentro de la misma zona/ciudad porque el costo y tiempo de
reabastecer entre tiendas cercanas es bajo; al entrar a una ciudad
nueva, la distancia hace que sea más eficiente (y a veces la única
opción real) tener un hub propio ahí en vez de mandar stock desde la
ciudad original en cada venta.

### Modelo de datos

Una sola tabla `sedes`, no dos tablas separadas de "tienda" y
"almacén" — porque una sede puede ser ambas cosas a la vez (como su
única sede actual, que vende y almacena en el mismo sitio):

- `id`, `nombre`, `ciudad`, `dirección`, `teléfono`, `estado`
  (activa/inactiva — para dar de baja una tienda sin borrar su
  historial).
- `tipo`: `tienda` (solo vende, no tiene stock propio) · `almacén`
  (solo abastece, no atiende clientes) · `mixta` (vende y abastece,
  como la sede única de hoy).
- `almacen_id` (nullable, referencia a otra sede cuyo tipo sea
  `almacén` o `mixta`): de qué almacén saca stock esta sede. Una sede
  `tienda` SIEMPRE tiene este campo lleno; una sede `almacén` o
  `mixta` lo tiene vacío porque **es** su propio almacén.

### Reglas de negocio

1. El stock (`InventoryStock`, kardex, unidades serializadas) se
   guarda por **almacén**, nunca por tienda — una tienda sin almacén
   propio consulta y descuenta del almacén al que apunta
   `almacen_id`.
2. Al crear una sede nueva tipo `tienda`, el formulario **obliga** a
   elegir a qué almacén se conecta — no puede quedar suelta.
3. Al crear una sede nueva tipo `almacén` o `mixta` en una ciudad que
   no tiene ninguna sede todavía, el sistema lo sugiere por defecto
   (nueva ciudad → probablemente necesita su propio almacén), pero
   Gerencia puede igual conectarla a un almacén de otra ciudad si el
   negocio decide operar así.
4. El selector "Sede" que ya aparece en el header de todas las
   pantallas (hoy con datos de ejemplo) pasa a ser real: al elegir una
   tienda ahí, toda la pantalla (ventas, inventario visible, caja)
   opera sobre el almacén al que esa tienda está conectada.
5. Solo Gerente/Administrador crea y edita sedes (ver matriz de
   permisos, sección 4 — se agrega como fila nueva).

## 11. Próximo paso

Está pendiente de tu revisión y aprobación la pizarra de estructura
(sección 07 del artefacto) y el diseño ya corregido (sidebar
generalizado, Técnico de Planta en móvil, módulo de Sedes). Recién con
eso aprobado se sigue con las siguientes sub-fases de diseño o se pasa
a maquetar directo en código con navegación por rol real (Inertia +
permisos de Laravel), que es donde de todas formas se prueba el flujo
de verdad.
