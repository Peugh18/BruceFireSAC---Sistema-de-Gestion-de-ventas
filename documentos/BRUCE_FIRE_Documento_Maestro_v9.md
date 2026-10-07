# BRUCE FIRE S.A.C.

## Documento Maestro del Sistema Web de Gestión Comercial, Operativa y Técnica

> ## Estado del documento y fe de erratas (2026-10-06)
>
> - **Nombre oficial del proyecto:** Sistema web para la gestión de ventas en BRUCE FIRE S.A.C. El alcance académico está fijado en el DRS v3.1 (Obsidian).
> - **Este documento es la especificación funcional (§1–§75).** Las secciones **§76–§90** (adendas, auditorías, planes y bitácoras) se movieron a `documentos/archivo/Documento_Maestro_v9_historial_76-90.md` con sus mismos números.
> - **Qué falta hacer:** `docs/ai/PLAN.md`. **Qué está mal:** `docs/ai/AUDITORIA.md`. **Normativa SUNAT vigente:** `docs/ai/SUNAT.md`. Ante cualquier choque con este documento, mandan esos tres.
> - **Erratas conocidas:**
>     1. **Rol Administrador** (§5.6, §35.6, §69): nunca se creó. El **Gerente** absorbe su alcance; hay 5 roles.
>     2. **Guía de remisión** (§30): **no está construida**, aunque el antiguo §77.1 decía que sí. Es obligatoria: diseño en `docs/ai/SUNAT.md` §5.
>     3. **Roadmap y MVP** (§58–§59) y **orden de arranque** (§72): históricos. El plan vigente es `docs/ai/PLAN.md`.
>     4. **Catálogo** (§10): no hay una pantalla "Catálogo" aparte. Productos y Servicios los administra el Gerente. Tampoco existen el tipo de agente del extintor ni las categorías administrables (pendientes C1 y A2).
>     5. **Firma** (§22–§25): hoy es una casilla con el nombre; la firma táctil y las fotos (§33) están pendientes (T1).
>     6. **Normativa SUNAT** (§28–§31): las guías XML son de 2017. Rigen la R.S. 193-2020 (forma de pago), la R.S. 000003-2023 (plazos) y la R.S. 000048-2026 (notas). Ver `docs/ai/SUNAT.md`.

**Versión funcional consolidada --- Diseño empresarial Light/Dark ---
Laravel + React + Inertia + MySQL**

> **Objetivo del documento:** definir, antes del desarrollo, qué tendrá
> y qué no tendrá el sistema de BRUCE FIRE, cómo se relacionarán los
> módulos, qué hará cada rol, cómo funcionarán los procesos
> comerciales/técnicos, cómo se integrará SUNAT mediante Greenter, cómo
> se controlarán los extintores y certificados, y dónde aportará valor
> la IA sin sobrecargar el sistema.

---

# 1. VISIÓN DEL SISTEMA

BRUCE FIRE tendrá una plataforma web empresarial única para administrar:

- clientes, contactos, sedes y vehículos;
- equipos/extintores pertenecientes a los clientes;
- productos, servicios, componentes y repuestos;
- inventario físico;
- cotizaciones internas;
- ventas;
- facturas, boletas y notas electrónicas;
- guías de remisión electrónicas cuando correspondan;
- órdenes de servicio;
- recojo y entrega de equipos;
- recarga y mantenimiento en Planta;
- inspecciones, instalaciones y mantenimiento en Campo;
- checklists técnicos;
- deficiencias y componentes observados;
- autorizaciones de adicionales;
- fotografías y evidencias;
- actas de conformidad;
- certificados;
- cuentas por cobrar;
- alertas de próximas atenciones;
- reportes;
- usuarios, roles, permisos y auditoría;
- IA predictiva y asistiva.

El principio fundamental será:

> **CAPTURAR UNA VEZ → REUTILIZAR EN TODO EL SISTEMA.**

Ejemplo: la serie, marca, capacidad y tipo de un extintor no se volverán
a escribir en la orden, certificado, acta e historial. Se registran en
la ficha del equipo y los demás procesos los reutilizan.

---

# 2. ALCANCE GENERAL

## 2.1 El sistema SÍ tendrá

1.  Gestión interna de BRUCE FIRE.
2.  Acceso por usuario y contraseña.
3.  Dashboards diferentes según rol.
4.  Tema claro y oscuro.
5.  Diseño responsive para PC, tablet y móvil.
6.  Clientes naturales y jurídicos.
7.  Varias sedes por cliente.
8.  Vehículos asociados cuando corresponda.
9.  Equipos/extintores del cliente.
10. Historial técnico por equipo.
11. Código de barras interno BRUCE FIRE.
12. Catálogo de productos, servicios, componentes y repuestos.
13. Inventario y movimientos.
14. Cotizaciones.
15. Conversión de cotización a venta.
16. Órdenes de servicio.
17. Flujo diferenciado de Técnico de Planta y Técnico de Campo.
18. Recojo y entrega.
19. Checklist técnico digital.
20. Deficiencias y adicionales.
21. Evidencia fotográfica.
22. Autorización del cliente por WhatsApp o presencial.
23. Actas de conformidad.
24. Certificados dinámicos.
25. QR público de verificación de certificados.
26. Factura electrónica.
27. Boleta electrónica.
28. Nota de crédito y nota de débito cuando corresponda.
29. GRE cuando corresponda.
30. Ventas al contado y crédito.
31. Cuotas y cobranzas.
32. Alertas de próximas atenciones.
33. Acceso directo a WhatsApp.
34. Correo cuando el cliente tenga email.
35. Exportaciones PDF/Excel según módulo.
36. Reportes gerenciales.
37. Auditoría de acciones sensibles.
38. IA predictiva basada en datos históricos.
39. IA asistiva para tareas concretas.
40. Integración SUNAT mediante Greenter.

## 2.2 El sistema NO tendrá inicialmente

- tienda e-commerce pública;
- marketplace;
- aplicación móvil nativa;
- microservicios;
- chat interno completo;
- telefonía desde la computadora;
- WhatsApp Business API automatizada en el MVP;
- CRM complejo de llamadas;
- módulo contable completo;
- planillas;
- recursos humanos;
- compras/ERP completo;
- múltiples empresas emisoras/multitenancy;
- portal de cliente completo;
- reconocimiento automático de datos técnicos sin confirmación humana;
- decisiones técnicas realizadas por IA;
- reglas tributarias decididas por IA;
- eliminación física de equipos con historial;
- módulos duplicados de Cotización y Proforma.

---

# 3. IDENTIDAD VISUAL Y DISEÑO EMPRESARIAL

La interfaz tomará como referencia estructural el dashboard moderno
proporcionado: sidebar limpia, tarjetas KPI, gráficos, tablas compactas,
bordes suaves, jerarquía clara y espacios amplios. No se copiará
literalmente el diseño.

La identidad visual se adaptará a BRUCE FIRE usando sus elementos de
marca:

- rojo como color primario;
- negro/grafito como color corporativo secundario;
- blanco como fondo principal del tema claro;
- grises neutros para superficies;
- rojo oscuro para estados críticos;
- verde únicamente para éxito/conformidad;
- ámbar para advertencias;
- azul solo cuando sea necesario para información neutral.

## 3.1 Tema claro

**Fondo general:** blanco/gris muy claro.\
**Sidebar:** blanco o gris claro.\
**Tarjetas:** blanco.\
**Texto principal:** negro/grafito.\
**Primario:** rojo BRUCE FIRE.\
**Bordes:** gris suave.\
**Hover:** rojo muy tenue.\
**Botón primario:** rojo con texto blanco.

## 3.2 Tema oscuro

Inspirado en la versión negra del material corporativo:

**Fondo general:** negro carbón/grafito.\
**Sidebar:** negro profundo.\
**Tarjetas:** gris carbón.\
**Texto:** blanco/gris claro.\
**Primario:** rojo BRUCE FIRE.\
**Bordes:** gris oscuro.\
**Hover:** rojo oscuro/transparente.\
**Estados:** colores accesibles sin saturación excesiva.

## 3.3 Reglas de UI

- Sidebar colapsable.
- Header superior limpio.
- Breadcrumbs en páginas profundas.
- Buscador global opcional.
- Campana de notificaciones.
- Selector Light / Dark / Sistema.
- Avatar y menú de usuario.
- Tablas con búsqueda, filtros, paginación y acciones contextuales.
- Formularios por secciones; evitar formularios gigantes.
- Acciones destructivas siempre con confirmación.
- Badges de estado.
- Skeleton/loading states.
- Empty states claros.
- Diseño responsive.
- En móvil técnico: tarjetas, no tablas horizontales enormes.
- Acciones principales siempre visibles.
- El rojo corporativo no debe usarse para todo; se reserva para
  identidad y acciones prioritarias.

---

# 4. NAVEGACIÓN PRINCIPAL

La navegación propuesta será:

1.  **Dashboard**
2.  **Clientes**
3.  **Catálogo**
4.  **Inventario**
5.  **Comercial**
6.  **Servicios**
7.  **Certificados**
8.  **Facturación**
9.  **Guías**
10. **Cobranzas**
11. **Reportes**
12. **Administración**

Los módulos visibles dependerán del rol y permisos.

---

# 5. DASHBOARD

No existirá un único dashboard idéntico para todos.

## 5.1 Gerente

Tarjetas:

- ventas del día;
- ventas del mes;
- facturación del mes;
- monto cobrado;
- cuentas por cobrar;
- vencido por cobrar;
- cotizaciones pendientes;
- tasa de conversión;
- órdenes en proceso;
- equipos próximos a atención;
- stock crítico;
- documentos SUNAT con error.

Gráficos:

- ventas mensuales;
- ventas por producto/servicio;
- servicios por tipo;
- cartera por estado;
- tendencia de recargas;
- top clientes;
- productos/repuestos con mayor movimiento.

IA:

- proyección de ventas;
- demanda estimada;
- clientes con mayor probabilidad de volver a requerir servicio;
- riesgo de quiebre de stock;
- resumen ejecutivo.

## 5.2 Vendedor

- cotizaciones pendientes;
- cotizaciones aceptadas;
- ventas recientes;
- equipos próximos a atención;
- equipos vencidos;
- clientes a contactar;
- órdenes esperando autorización;
- órdenes listas;
- certificados listos;
- cuentas por cobrar relacionadas.

Acciones rápidas:

- Nueva cotización.
- Nueva venta.
- Nuevo cliente.
- Nueva orden.
- WhatsApp a cliente.
- Convertir cotización a venta.

## 5.3 Almacén

- stock bajo;
- productos sin stock;
- recepciones recientes;
- movimientos del día;
- unidades físicas disponibles;
- repuestos críticos.

## 5.4 Técnico de Planta

- órdenes pendientes de recepción;
- recibidas;
- en revisión;
- esperando autorización;
- autorizadas;
- en proceso;
- pendientes de datos;
- listas para certificado;
- listas para entrega.

## 5.5 Técnico de Campo

- servicios de hoy;
- recojos;
- entregas;
- inspecciones;
- instalaciones;
- mantenimientos;
- pendientes;
- en proceso;
- finalizados.

## 5.6 Administrador

No tendrá un dashboard técnico ficticio de "salud del sistema".

Tendrá acceso principalmente a configuración, usuarios, roles, permisos,
plantillas, series, parámetros y auditoría.

---

# 6. CLIENTES

## 6.1 Datos del cliente

- código interno automático;
- tipo de documento;
- DNI/RUC;
- razón social/nombres;
- nombre comercial;
- condición/estado tributario si se integra una fuente válida;
- teléfono;
- WhatsApp;
- email;
- dirección fiscal;
- departamento;
- provincia;
- distrito;
- ubigeo;
- estado activo/inactivo;
- observaciones.

## 6.2 Sedes

Un cliente puede tener:

- oficina;
- tienda;
- planta;
- almacén;
- local;
- sucursal;
- otra sede.

Cada sede:

- nombre;
- dirección;
- ubigeo;
- referencia;
- contacto;
- teléfono;
- email;
- estado.

## 6.3 Vehículos

Cuando la operación sea para vehículos:

- placa;
- marca;
- modelo;
- descripción;
- cliente;
- estado.

La placa puede reutilizarse en cotización, factura impresa, acta y
certificado cuando corresponda.

## 6.4 Perfil 360° del cliente

Pestañas:

- Resumen.
- Sedes.
- Vehículos.
- Equipos.
- Cotizaciones.
- Ventas.
- Servicios.
- Certificados.
- Comprobantes.
- Cobranzas.
- Historial.

---

# 7. EQUIPOS DEL CLIENTE

Este concepto es crítico.

Un **producto del catálogo** no es lo mismo que un **equipo físico del
cliente**.

Ejemplo:

`Extintor PQS ABC 6 kg` = producto.

`Serie 0398, marca ABC, PQS, 6 kg, propiedad de FONPELL` = equipo
físico.

## 7.1 Datos

- ID interno;
- código BRUCE FIRE;
- barcode;
- cliente;
- sede;
- vehículo opcional;
- origen: vendido por BRUCE FIRE / externo / desconocido;
- tipo de equipo;
- agente;
- capacidad/peso;
- marca;
- serie fabricante;
- año fabricación;
- ubicación;
- estado;
- última atención;
- próxima atención;
- última P.H.;
- próxima P.H.;
- observaciones.

## 7.2 Historial de vida

Cada equipo tendrá timeline:

- alta;
- venta si fue vendido por BRUCE FIRE;
- transferencia;
- recojo;
- recarga;
- mantenimiento;
- inspección;
- P.H.;
- deficiencias;
- repuestos reemplazados;
- fotos;
- certificados;
- entregas;
- cambios de sede;
- baja/reemplazo.

## 7.3 Estados

- Activo.
- Fuera de servicio.
- Reemplazado.
- Retirado.
- Baja definitiva.
- No localizado.

Nunca se elimina un equipo que tenga historial.

---

# 8. TRANSFERENCIA DE EQUIPOS

No se editará silenciosamente el cliente o sede.

Acción: **Transferir equipo**.

Registrar:

- equipo;
- cliente/sede origen;
- cliente/sede destino;
- fecha;
- motivo;
- responsable;
- evidencia/documento opcional;
- observación.

El historial anterior permanece intacto.

---

# 9. CÓDIGO DE BARRAS BRUCE FIRE

## 9.1 Objetivo

Identificar rápidamente el equipo dentro del sistema.

Ejemplo:

`BF-EQ-000245`

## 9.2 Primera atención de equipo externo

1.  Técnico recibe equipo.
2.  Si no existe, selecciona **Nuevo equipo**.
3.  Realiza Alta Técnica Rápida.
4.  Sistema genera código.
5.  Se imprime barcode.
6.  Se coloca durante la recepción o, si no es posible, antes de la
    entrega.
7.  En servicios posteriores se escanea.

El barcode **no sustituye** la serie del fabricante.

No necesita imprimir vencimiento o P.H. si BRUCE FIRE ya usa etiquetas
técnicas separadas para esos datos.

---

# 10. CATÁLOGO

## 10.1 Productos

- código;
- categoría;
- nombre;
- descripción;
- unidad;
- precio;
- impuesto;
- controla stock;
- control serializado;
- genera barcode;
- estado.

Ejemplos:

- Extintor PQS ABC 6 kg.
- Cámara.
- Manguera.
- Válvula.
- Manómetro.

## 10.2 Servicios

- código;
- categoría;
- nombre;
- descripción;
- precio;
- unidad;
- impuesto;
- tipo técnico;
- requiere orden;
- requiere certificado;
- checklist aplicable;
- estado.

Ejemplos:

- Recarga y mantenimiento PQS 6 kg.
- Recarga y mantenimiento PQS 9 kg.
- Inspección.
- Instalación.
- Mantenimiento.
- Prueba hidrostática.
- Capacitación.

## 10.3 Repuestos/componentes

Podrán controlarse:

- manguera;
- válvula;
- manómetro;
- pasador/seguro;
- precinto/sello;
- boquilla;
- difusor/corneta;
- manija/palanca;
- empaques;
- O-ring;
- otros repuestos reales.

No se cargará un catálogo enorme ficticio. Se registrarán los
componentes que BRUCE FIRE realmente compra, usa o vende.

---

# 11. INVENTARIO

Inventario no registra nuevamente productos.

**Catálogo define qué existe. Inventario controla cuánto existe
físicamente.**

## 11.1 Funciones

- stock;
- stock mínimo;
- entradas;
- salidas;
- movimientos;
- ajustes autorizados;
- recepciones;
- unidades serializadas;
- repuestos;
- disponibilidad;
- historial.

## 11.2 Recepción de proveedor

- proveedor;
- documento referencia;
- fecha;
- producto;
- cantidad;
- cantidad conforme;
- cantidad observada;
- observación;
- usuario.

Para extintores nuevos:

- serie;
- marca;
- capacidad;
- año;
- barcode si corresponde.

## 11.3 Regla de venta

**Almacén NO pistolea para vender.**

El Vendedor es quien escanea los extintores físicos exactos que se
entregarán al cliente.

---

# 12. COMERCIAL

## 12.1 Cotización

**Cotización es el documento comercial interno oficial.**

No se envía a SUNAT.

No existirá Proforma como flujo separado.

### Datos

- número;
- fecha;
- vendedor;
- cliente;
- sede;
- vehículo/placa cuando corresponda;
- vigencia;
- productos;
- servicios;
- cantidades;
- precios;
- descuentos;
- subtotal;
- IGV;
- total;
- condición propuesta;
- observaciones;
- estado.

### Estados

- Borrador.
- Emitida.
- Enviada.
- Aceptada.
- Rechazada.
- Vencida.
- Convertida.
- Anulada internamente.

### Acciones

- editar mientras corresponda;
- duplicar;
- descargar PDF;
- imprimir;
- enviar;
- marcar aceptación;
- convertir a venta.

## 12.2 Conversión a venta

Flujo:

`Cotización → Aceptada → Convertir a venta → Venta`

Al convertir:

- cliente se conserva;
- sede se conserva;
- placa se conserva;
- ítems se conservan;
- precios/descuentos se conservan;
- observaciones se trasladan;
- no se vuelve a digitar.

Si contiene servicios:

`Cotización → Venta + Orden de Servicio vinculada`

Si contiene productos y servicios:

`Cotización → Venta + Orden de Servicio`

Ambos conservan referencia a la cotización original.

---

# 13. VENTA

## 13.1 Datos

- número interno;
- cotización origen opcional;
- cliente;
- sede;
- vehículo/placa;
- vendedor;
- fecha;
- productos/servicios;
- unidades físicas seleccionadas;
- condición de pago;
- forma(s) de pago;
- total;
- estado;
- observaciones.

## 13.2 Escaneo de extintores vendidos

Para producto serializado:

1.  vendedor agrega SKU y cantidad;
2.  físicamente recibe/prepara los equipos;
3.  escanea cada barcode;
4.  sistema recupera unidad exacta;
5.  valida disponibilidad;
6.  vincula serie/unidad a la venta;
7.  al completar venta pasa a equipo del cliente.

Nunca seleccionar unidades aleatorias.

---

# 14. CONDICIÓN Y FORMAS DE PAGO

## 14.1 Condición

- Contado.
- Crédito.

## 14.2 Formas internas

- efectivo;
- transferencia;
- Yape;
- Plin;
- POS;
- depósito;
- otro.

## 14.3 Crédito

- monto pendiente;
- número de cuotas;
- fecha de vencimiento;
- monto por cuota;
- estado;
- pagos parciales.

La estructura debe soportar los comprobantes reales de BRUCE FIRE con
cuota y fecha de vencimiento.

---

# 15. SERVICIOS

El módulo central operativo.

Tipos:

- Recarga.
- Mantenimiento.
- Prueba hidrostática.
- Inspección.
- Instalación.
- Mantenimiento en campo.
- Otros configurables.

---

# 16. ORDEN DE SERVICIO

## 16.1 Datos generales

- código;
- cliente;
- sede;
- vehículo;
- cotización/venta origen;
- tipo de servicio;
- fecha;
- técnico asignado;
- equipos;
- observaciones;
- prioridad;
- estado.

## 16.2 Estados de Planta

- Pendiente de recepción.
- Recibido en Planta.
- En revisión.
- Esperando autorización.
- Autorizado.
- En proceso.
- Trabajo terminado.
- Pendiente de datos.
- Datos completos.
- Listo para certificado.
- Listo para entrega.
- Entregado.
- Cerrado.

Los estados deben controlarse mediante transiciones válidas, no como
texto libre.

---

# 17. COMUNICACIÓN VENDEDOR ↔ TÉCNICO DE PLANTA

No se implementará chat interno.

La comunicación será mediante eventos estructurados de la Orden.

Ejemplo:

1.  Vendedor crea orden.
2.  Planta recibe.
3.  Técnico detecta manguera dañada.
4.  Registra deficiencia + foto.
5.  Sistema notifica al vendedor.
6.  Vendedor contacta cliente.
7.  Cliente acepta por WhatsApp o presencial.
8.  Vendedor registra autorización.
9.  Planta recibe aviso.
10. Técnico realiza reemplazo.
11. Marca deficiencia resuelta.
12. Continúa servicio.
13. Completa datos.
14. Sistema habilita certificado.

Así la información queda trazable.

---

# 18. ALTA TÉCNICA RÁPIDA

Para extintores de otras empresas.

## Caso A: ya tiene código BRUCE FIRE

`Escanear → abrir ficha → revisar → servicio`

## Caso B: no existe

`Nuevo equipo → datos mínimos → foto → checklist → generar barcode`

Datos mínimos:

- agente/tipo;
- capacidad;
- marca;
- serie si legible;
- año si legible;
- foto general;
- foto de placa si aporta información.

Si un dato no puede leerse:

**No legible / Pendiente de verificar.**

Nunca obligar al técnico a inventar datos.

---

# 19. CHECKLIST TÉCNICO DIGITAL

Diseñado para móvil/tablet.

Opciones:

- Conforme.
- Observado.
- No aplica.

## 19.1 Elementos

- identificación;
- cilindro;
- corrosión;
- golpes/deformación;
- válvula;
- manómetro cuando aplique;
- pasador;
- precinto;
- manguera;
- boquilla/difusor;
- manija/palanca;
- rotulado;
- agente/carga;
- servicio;
- observaciones;
- bloque P.H. cuando aplique;
- bloque específico por tipo de extintor.

## 19.2 Checklist dinámico

Si no aplica un componente, no se fuerza.

Si el equipo ya está registrado, no se muestran nuevamente todos los
datos maestros.

Si marca **Observado**, se despliega:

- componente;
- condición;
- foto;
- nota;
- acción recomendada.

---

# 20. DEFICIENCIAS

Cada deficiencia pertenece a:

- una orden;
- un equipo;
- un componente.

Campos:

- componente;
- condición;
- foto;
- nota;
- acción recomendada;
- repuesto sugerido;
- requiere autorización;
- estado;
- resolución.

Estados:

- Detectada.
- Esperando autorización.
- Autorizada.
- Rechazada.
- En corrección.
- Resuelta.

---

# 21. AUTORIZACIÓN DE ADICIONALES

El Técnico no negocia precios.

Flujo:

`Técnico detecta → Vendedor recibe → cotiza adicional → cliente aprueba → Vendedor registra → Técnico ejecuta`

Canales aceptados:

- WhatsApp.
- Presencial.

El sistema guardará:

- quién autorizó;
- fecha;
- canal;
- observación/evidencia si se requiere.

---

# 22. RECOJO Y ENTREGA

Responsable operativo: **Técnico de Campo**.

## 22.1 Recojo

- orden;
- cliente;
- dirección/sede;
- contacto;
- fecha/hora;
- cantidad;
- equipos;
- fotos;
- observaciones;
- responsable;
- conformidad/firma.

## 22.2 Recepción en Planta

- quién entrega;
- quién recibe;
- cantidad;
- diferencias;
- observaciones;
- fecha/hora.

## 22.3 Entrega final

- cantidad;
- fecha/hora;
- fotos;
- observaciones;
- receptor;
- firma/conformidad.

## 22.4 Cadena de custodia

El sistema conserva:

`Recogido por → recibido por Planta → procesado por → entregado por → recibido por cliente`

---

# 23. ACTA DE CONFORMIDAD

Documento diferente al certificado y al comprobante.

Se generará desde la Orden.

Datos:

- BRUCE FIRE;
- RUC;
- cliente;
- RUC/DNI;
- fecha recepción;
- fecha entrega;
- objeto del servicio;
- tabla de equipos;
- placa;
- peso/capacidad;
- serie;
- tipo;
- observaciones;
- fotografías;
- nombre del receptor;
- firma/conformidad.

La tabla será dinámica.

Si son 20 extintores, genera 20 filas automáticamente.

---

# 24. INSPECCIONES

Técnico de Campo.

## Flujo

`Orden → visita → equipos → checklist → fotos → deficiencias → firma → informe/certificado → cierre`

## Datos de cabecera

- cliente;
- RUC;
- domicilio;
- actividad;
- sede;
- fecha;
- responsable;
- cargo;
- firma;
- número de trabajadores si el formato lo requiere.

## Por extintor

- número interno;
- serie;
- ubicación;
- agente;
- capacidad;
- manómetro;
- pasador;
- manguera;
- marca/procedencia;
- fabricación;
- tarjeta;
- próxima recarga;
- P.H.;
- observación.

En móvil cada extintor será una tarjeta, no una tabla horizontal.

---

# 25. INSTALACIONES

Técnico de Campo.

- orden;
- cliente;
- sede;
- áreas;
- productos;
- unidades;
- técnicos;
- ubicación instalada;
- foto antes;
- foto después;
- pruebas;
- observaciones;
- firma;
- certificado aplicable.

Los equipos instalados que requieran seguimiento pueden pasar a Equipos
del Cliente.

---

# 26. CERTIFICADOS

Tipos iniciales:

- Operatividad y Garantía.
- Prueba Hidrostática.
- Capacitación/Participación.
- Operatividad de Sistemas de Detección/Alarma.
- Otros configurables.

## 26.1 Motor de reglas

El sistema sugerirá certificados según:

- servicio realizado;
- tipo de equipo;
- destino;
- vehículo/local;
- P.H. realizada;
- capacitación realizada;
- reglas configuradas.

Ejemplos operativos BRUCE FIRE:

**Vehículo:** Operatividad + P.H. cuando técnicamente corresponda.\
**Local:** Operatividad + Capacitación cuando la capacitación forme
parte de la operación.

No se generará P.H. únicamente por ser vehículo si la prueba no
corresponde/no fue realizada.

## 26.2 Certificado dinámico por grupo

No crear plantilla de 1, 2, 10 y 20.

Una plantilla por tipo:

`Plantilla Operatividad → tabla repetible de equipos`

Si la orden tiene 20:

- 20 filas;
- salto automático de página;
- datos comunes una sola vez.

## 26.3 Firmas

Las firmas autorizadas se cargan en configuración/plantilla.

No obligar a insertar manualmente la firma en cada certificado.

## 26.4 QR de autenticidad

Cada certificado:

- número único;
- QR;
- token público no predecible;
- página pública de verificación.

La página mostrará:

- BRUCE FIRE;
- número;
- tipo;
- cliente;
- emisión;
- vigencia;
- equipos esenciales;
- estado.

Estados:

- Vigente.
- Vencido.
- Reemplazado.
- Anulado.

El QR del certificado **no es QR SUNAT**.

---

# 27. ALERTAS Y PRÓXIMAS ATENCIONES

No será un CRM complicado.

Widget/listado:

- cliente;
- equipo;
- cantidad;
- próxima fecha;
- estado;
- teléfono;
- email.

Acciones:

- **WhatsApp**
- **Correo**
- **Crear cotización**

No habrá botón de llamada.

WhatsApp abre conversación mediante click-to-chat/WhatsApp Web.

Colores:

- rojo = vencido;
- ámbar = próximo;
- normal = futuro.

---

# 28. FACTURACIÓN ELECTRÓNICA

Solo entra SUNAT después de existir una Venta.

Flujo:

`Cotización interna → Convertir a venta → Factura/Boleta → Greenter → SUNAT`

## 28.1 Factura

Soportar:

- cliente/RUC;
- dirección;
- condición pago;
- emisión;
- vencimiento;
- placa cuando corresponda;
- detalle;
- unidad;
- cantidad;
- precio;
- descuento;
- gravado;
- IGV;
- total;
- cuotas;
- observación;
- cuentas bancarias;
- representación PDF;
- XML;
- CDR.

## 28.2 Boleta

Mismo principio tributario, con reglas propias del documento.

## 28.3 Crédito

Guardar estructuradamente:

- monto pendiente;
- cuotas;
- vencimientos;
- importes.

No solo imprimirlo en PDF.

---

# 29. NOTAS DE CRÉDITO/DÉBITO

Nunca crear NC como venta independiente.

Flujo:

`Comprobante existente → acción Nota de Crédito → motivo → documento relacionado`

Guardar:

- CPE afectado;
- motivo;
- detalle;
- importe;
- fecha;
- respuesta SUNAT;
- XML;
- CDR;
- PDF.

El comprobante original permanece en historial.

---

# 30. GRE

Módulo separado porque su flujo técnico no es igual al de
factura/boleta.

Campos:

- documento relacionado;
- motivo traslado;
- fecha inicio;
- origen;
- destino;
- destinatario;
- bienes;
- peso;
- modalidad;
- transportista;
- vehículo;
- placa;
- conductor;
- licencia;
- observaciones;
- estado SUNAT.

Debe soportar los escenarios reales de transporte privado/público que
BRUCE FIRE utiliza.

---

# 31. GREENTER / SUNAT

Greenter será una **capa de integración**, no el centro del sistema.

Arquitectura propuesta:

```text
Venta
  ↓
BillingService
  ↓
InvoiceBuilder / ReceiptBuilder / CreditNoteBuilder
  ↓
GreenterService
  ↓
SUNAT
  ↓
CDR / estado / error
```

Para GRE se mantendrá un servicio separado porque su envío utiliza
flujo/API diferente.

Guardar:

- tipo;
- serie;
- correlativo;
- XML;
- hash;
- CDR;
- estado;
- respuesta;
- error;
- intentos;
- fecha envío.

No guardar certificados digitales/credenciales como texto plano en
tablas visibles.

---

# 32. COBRANZAS

- documentos pendientes;
- cliente;
- total;
- saldo;
- vencimiento;
- cuotas;
- estado;
- pagos parciales;
- método;
- número de operación;
- fecha;
- observación.

Dashboard:

- total por cobrar;
- vencido;
- vence esta semana;
- cobrado este mes.

---

# 33. FOTOGRAFÍAS Y EVIDENCIA

Tipos:

- recepción;
- placa/identificación;
- deficiencia;
- proceso;
- antes;
- después;
- entrega;
- evidencia adicional.

Cada foto debe saber:

- orden;
- equipo opcional;
- etapa;
- usuario;
- fecha.

## Política

No borrar automáticamente evidencia importante solo por ahorrar espacio.

Estrategia:

1.  almacenamiento activo;
2.  compresión/miniaturas;
3.  archivo;
4.  exportación;
5.  purga únicamente de material auxiliar según política autorizada.

Documentos fiscales y evidencias críticas tienen políticas separadas.

---

# 34. REPORTES

## Comerciales

- ventas por periodo;
- vendedor;
- cliente;
- producto;
- servicio;
- conversión de cotizaciones.

## Inventario

- stock;
- movimientos;
- repuestos;
- stock mínimo;
- rotación.

## Servicios

- órdenes;
- tiempos;
- recargas;
- inspecciones;
- instalaciones;
- deficiencias;
- técnicos.

## Equipos

- próximos a atención;
- P.H.;
- historial;
- estado;
- cliente/sede.

## Certificados

- emitidos;
- vigentes;
- vencidos;
- anulados.

## Facturación

- CPE;
- estado SUNAT;
- errores;
- ventas contado/crédito.

## Cobranzas

- saldos;
- vencidos;
- pagos.

Exportar a Excel/PDF cuando aporte valor.

---

# 35. ROLES Y PERMISOS

Roles:

1.  Gerente.
2.  Vendedor.
3.  Almacén.
4.  Técnico de Planta.
5.  Técnico de Campo.
6.  Administrador.

## 35.1 Gerente

Acceso amplio de consulta a:

- dashboards;
- ventas;
- servicios;
- inventario;
- certificados;
- facturación;
- cobranzas;
- reportes;
- IA.

No necesita necesariamente administrar credenciales técnicas SUNAT.

## 35.2 Vendedor

- clientes;
- sedes;
- vehículos;
- cotizaciones;
- ventas;
- escaneo de unidades vendidas;
- alertas;
- órdenes;
- deficiencias comerciales;
- autorizaciones;
- certificados;
- CPE según permisos;
- cobranzas según permisos.

## 35.3 Almacén

- catálogo lectura;
- stock;
- recepciones;
- movimientos;
- unidades;
- repuestos.

No escanea equipos para asignarlos a una venta.

## 35.4 Técnico de Planta

- órdenes asignadas;
- recepción;
- alta rápida;
- barcode;
- checklist;
- recarga;
- mantenimiento;
- P.H. aplicable;
- deficiencias;
- fotos;
- datos técnicos;
- cierre técnico.

No cambia precios ni emite CPE.

## 35.5 Técnico de Campo

- órdenes asignadas;
- recojo;
- entrega;
- inspección;
- instalación;
- mantenimiento de campo;
- checklist;
- fotos;
- firmas;
- deficiencias;
- cierre.

## 35.6 Administrador

- usuarios;
- roles;
- permisos;
- parámetros;
- series;
- plantillas;
- configuración;
- auditoría;
- credenciales/integraciones protegidas.

---

# 36. MATRIZ DE PERMISOS

No programar la seguridad preguntando únicamente "¿es vendedor?".

Usar permisos granulares:

```text
clients.view
clients.create
clients.update

quotes.view
quotes.create
quotes.update
quotes.convert

sales.view
sales.create
sales.scan_units

inventory.view
inventory.receive
inventory.adjust

service_orders.view
service_orders.create
service_orders.assign
service_orders.receive
service_orders.execute
service_orders.close

deficiencies.create
deficiencies.authorize
deficiencies.resolve

certificates.view
certificates.generate
certificates.void

billing.view
billing.issue
billing.retry
billing.credit_note

collections.view
collections.register_payment

reports.view

users.manage
roles.manage
settings.manage
audit.view
```

Los permisos se asignan a roles; evitar permisos directos a usuarios
salvo excepción justificada.

---

# 37. AUDITORÍA

Registrar acciones sensibles:

- creación/edición de cliente;
- cambio de precio importante;
- conversión de cotización;
- venta;
- ajuste de stock;
- transferencia de equipo;
- autorización de adicional;
- cierre de orden;
- emisión/anulación de certificado;
- emisión de CPE;
- nota de crédito;
- registro/modificación de pago;
- cambios de roles/permisos;
- cambios de configuración.

Guardar:

- usuario;
- acción;
- entidad;
- ID;
- antes/después cuando aplique;
- fecha;
- contexto.

---

# 38. NOTIFICACIONES INTERNAS

Notificaciones útiles, no ruido.

Ejemplos:

- nueva orden para Planta;
- recojo asignado a Campo;
- deficiencia para Ventas;
- adicional autorizado para Técnico;
- trabajo terminado;
- certificado listo;
- equipo próximo a atención;
- stock crítico;
- cuota próxima/vencida;
- error SUNAT.

Campana con contador y bandeja.

---

# 39. IA DEL SISTEMA

La IA no debe existir solo "para decir que tiene IA".

## 39.1 IA predictiva comercial

Con históricos:

- probabilidad de próximo servicio;
- clientes con mayor oportunidad;
- estimación de demanda;
- proyección de ventas;
- estacionalidad;
- riesgo de stock.

La salida debe mostrarse como apoyo, no como verdad absoluta.

> **Estado de Implementación (§39.1 — 2026-09-23):** Implementado con Machine Learning supervisado real y local (Regresión Logística, `scikit-learn` en script `scripts/ml/train_retention_model.py`, exportado a `storage/app/ml/retention_model.json`). Inferencia en PHP puro (`RetentionModel.php`) sin dependencias de Python ni APIs externas en producción. Evalúa 7 features libres de fuga (corte temporal `2026-03-12`) con métricas auditadas en conjunto de prueba: **ROC-AUC: 73.7%**, **Accuracy: 75.7%**, **Precision: 67.2%**. Integrado en el Dashboard del Gerente con factores explicativos y persistido mediante comando diario `ml:score-clients` en `client_retention_scores`.

## 39.2 IA para lectura asistida

Foto de placa/etiqueta:

- sugerir marca;
- serie;
- capacidad;
- año;
- texto visible.

**El técnico confirma.**

## 39.3 Asistente gerencial

Ejemplos:

- "¿Cuánto vendimos este mes?"
- "¿Qué clientes tienen más equipos por vencer?"
- "¿Qué servicio creció más?"
- "¿Qué repuestos están cerca de agotarse?"

Debe respetar permisos.

## 39.4 Resumen técnico asistido

A partir de checklist/notas:

- generar borrador de observación;
- resumir deficiencias;
- preparar texto comercial.

Nunca modificar automáticamente datos técnicos.

## 39.5 IA NO hará

- decidir si un extintor es técnicamente seguro sin técnico;
- inventar serie/año;
- calcular tributos libremente;
- decidir reglas SUNAT;
- emitir CPE por sí sola;
- autorizar descuentos;
- modificar inventario sin acción transaccional.

---

# 40. STACK TECNOLÓGICO

## Backend

- Laravel.
- PHP.
- Eloquent ORM.
- MySQL.
- Jobs/Queues.
- Scheduler.
- Notifications.
- Storage.

## Frontend

- React 19.
- TypeScript.
- Inertia.
- Tailwind CSS.
- shadcn/ui.
- Vite.

## Arquitectura

Una sola aplicación Laravel full-stack.

No separar React en otro repositorio.

No microservicios.

No API REST interna innecesaria para cada pantalla.

Inertia permite mantener Laravel + React dentro del mismo proyecto.

---

# 41. LIBRERÍAS / PAQUETES RECOMENDADOS

## 41.1 spatie/laravel-permission

Para:

- roles;
- permisos;
- integración con Laravel Gate;
- autorización granular.

## 41.2 spatie/laravel-activitylog

Para:

- auditoría;
- eventos de modelos;
- usuario causante;
- cambios relevantes.

No registrar cada lectura de pantalla; solo eventos que aporten
trazabilidad.

## 41.3 Greenter

Para:

- construir CPE;
- XML UBL;
- firma;
- envío;
- procesamiento de respuesta/CDR.

Debe quedar encapsulado en servicios propios.

## 41.4 Laravel Queues

Para:

- envío SUNAT;
- reintentos;
- PDFs pesados;
- exportaciones;
- notificaciones;
- tareas de archivo;
- procesos IA no inmediatos.

Para empezar puede utilizarse el driver de base de datos; Redis no es
obligatorio.

## 41.5 Laravel Scheduler

Para:

- detectar próximos vencimientos;
- actualizar alertas;
- ejecutar archivo de evidencias;
- tareas recurrentes;
- reintentos controlados.

## 41.6 Laravel Notifications

Para:

- bandeja interna;
- correo cuando corresponda;
- avisos de órdenes/deficiencias.

## 41.7 Laravel Precognition

Opcional para validación anticipada de formularios complejos con
React/Inertia.

## 41.8 Generación de barcode/QR

Usar una librería mantenida y compatible con el stack final para:

- barcode interno de equipo;
- QR de certificado.

Debe abstraerse detrás de un servicio para no acoplar el dominio a un
paquete concreto.

## 41.9 Excel

Usar una librería Laravel mantenida para exportaciones/importaciones
cuando se cierre la versión exacta de Laravel.

No instalar paquetes "por si acaso"; cada dependencia debe justificar su
uso.

---

# 42. ESTRUCTURA BACKEND SUGERIDA

```text
app/
├── Actions/
├── Enums/
├── Events/
├── Http/
│   ├── Controllers/
│   ├── Requests/
│   └── Middleware/
├── Jobs/
├── Models/
├── Notifications/
├── Policies/
├── Services/
│   ├── Clients/
│   ├── Catalog/
│   ├── Inventory/
│   ├── Commercial/
│   ├── Services/
│   ├── Certificates/
│   ├── Billing/
│   │   ├── BillingService.php
│   │   ├── GreenterService.php
│   │   ├── InvoiceBuilder.php
│   │   ├── ReceiptBuilder.php
│   │   ├── CreditNoteBuilder.php
│   │   └── SunatResponseService.php
│   ├── Shipping/
│   └── AI/
└── Support/
```

No crear una "Clean Architecture" exagerada para un capstone.

Sí separar lógica importante de los Controllers.

---

# 43. ESTRUCTURA FRONTEND SUGERIDA

```text
resources/js/
├── components/
│   ├── ui/
│   ├── shared/
│   ├── charts/
│   ├── forms/
│   └── tables/
├── layouts/
├── pages/
│   ├── dashboard/
│   ├── clients/
│   ├── catalog/
│   ├── inventory/
│   ├── commercial/
│   ├── services/
│   ├── certificates/
│   ├── billing/
│   ├── guides/
│   ├── collections/
│   ├── reports/
│   └── administration/
├── hooks/
├── lib/
└── types/
```

---

# 44. COMPONENTES VISUALES REUTILIZABLES

Crear componentes comunes:

- `PageHeader`
- `StatCard`
- `StatusBadge`
- `DataTable`
- `FilterBar`
- `SearchInput`
- `ConfirmDialog`
- `EmptyState`
- `FormSection`
- `EntityCard`
- `EquipmentCard`
- `ChecklistItem`
- `PhotoUploader`
- `BarcodeScannerInput`
- `Timeline`
- `NotificationBell`
- `ThemeToggle`
- `MoneyDisplay`
- `SunatStatusBadge`
- `CertificateStatusBadge`

Esto mantiene consistencia visual.

---

# 45. RESPONSIVE

## Escritorio

Dashboard completo, tablas, panel lateral.

## Tablet

Especialmente útil para Planta.

Tarjetas de equipos y checklist.

## Móvil

Prioridad Técnico de Campo/Planta:

- órdenes;
- equipo;
- escaneo;
- checklist;
- fotos;
- deficiencias;
- firma;
- cerrar trabajo.

No intentar mostrar el ERP completo como escritorio reducido.

---

# 46. SEGURIDAD

- contraseñas hasheadas;
- HTTPS;
- CSRF;
- validación backend;
- autorización mediante Policies/Gates;
- rate limiting donde corresponda;
- sesiones seguras;
- credenciales SUNAT protegidas;
- certificado digital fuera de almacenamiento público;
- logs sin secretos;
- backups;
- control de acceso a fotografías/documentos;
- URLs públicas de certificados con token no predecible;
- auditoría de cambios sensibles.

Registro público de usuarios: **deshabilitado** para el sistema interno.

Los usuarios los crea el Administrador.

---

# 47. DOCUMENTOS Y ARCHIVOS

Separar:

## Comerciales

- Cotización.

## Operativos

- Orden.
- Acta.
- Informe.
- Evidencias.

## Técnicos

- Certificados.

## Fiscales

- Factura.
- Boleta.
- NC/ND.
- GRE.
- XML.
- CDR.

No mezclar estados ni reglas.

---

# 48. MODELO DE ESTADOS: REGLA GENERAL

Cada entidad tendrá su propio estado.

Nunca usar un enum global como:

`pendiente / aprobado / anulado`

para todo.

Ejemplo:

**Cotización:** borrador, emitida, aceptada, rechazada, vencida,
convertida.

**Orden:** pendiente recepción, recibida, revisión, autorización,
proceso, terminada, datos completos, entrega, cerrada.

**CPE:** pendiente, procesando, aceptado, observado, rechazado/error,
anulado según proceso aplicable.

**Certificado:** borrador, emitido, vigente, vencido, reemplazado,
anulado.

**Cobranza:** pendiente, parcial, pagada, vencida.

---

# 49. REGLAS IMPORTANTES DE NEGOCIO

1.  Una cotización no afecta stock.
2.  Convertir cotización no debe duplicar datos.
3.  Una venta puede provenir de una cotización.
4.  Una cotización con servicios genera orden al convertirse.
5.  El Vendedor escanea unidades serializadas vendidas.
6.  El Almacenero no asigna extintores a ventas.
7.  Un extintor externo puede convertirse en equipo del cliente sin
    pasar por inventario.
8.  Un equipo no se elimina si tiene historial.
9.  Un cambio de sede/cliente es transferencia.
10. Una deficiencia pertenece al equipo y orden.
11. El técnico no modifica precios.
12. El vendedor no inventa datos técnicos.
13. Un certificado se genera desde datos existentes.
14. P.H. y próxima atención son fechas diferentes.
15. Barcode interno y serie fabricante son diferentes.
16. QR certificado y barcode equipo son diferentes.
17. Cotización no va a SUNAT.
18. Factura/Boleta nacen de la Venta.
19. NC/ND nacen vinculadas a un CPE.
20. La IA nunca reemplaza validaciones tributarias/técnicas.

---

# 50. FLUJOS MAESTROS

## 50.1 Venta directa de extintores

```text
Cliente
→ Venta/Cotización
→ Producto
→ Vendedor escanea unidades exactas
→ Venta
→ Equipos pasan al cliente
→ Certificado si corresponde
→ Factura/Boleta
→ Cobro
→ Próxima atención
```

## 50.2 Cotización aceptada

```text
Cotización
→ Aceptada
→ Convertir a venta
→ Venta
→ si hay servicio: Orden
→ ejecución
→ certificado
→ Factura/Boleta
```

## 50.3 Recarga de equipo externo

```text
Cliente
→ Orden
→ Recepción
→ Nuevo equipo
→ Alta Técnica Rápida
→ Barcode BRUCE FIRE
→ Checklist
→ Deficiencias
→ Autorización
→ Recarga
→ Datos técnicos
→ Certificado
→ Facturación
→ Entrega
→ Próxima atención
```

## 50.4 Vencimiento recurrente

```text
Equipo
→ Próxima atención
→ Alerta
→ WhatsApp/Correo
→ Cotización
→ Aceptación
→ Recojo
→ Orden
→ Servicio
→ Certificado
→ Facturación
→ Entrega
→ Nueva próxima atención
```

## 50.5 Inspección

```text
Orden
→ Campo
→ Equipo
→ Checklist
→ Fotos
→ Deficiencias
→ Firma
→ Informe/Certificado
→ Facturación
→ Historial
```

## 50.6 Instalación

```text
Cotización
→ Venta + Orden
→ Campo
→ Instalación por área
→ Evidencia antes/después
→ Pruebas
→ Certificado
→ Facturación
→ Activos del cliente
```

---

# 51. DISEÑO DEL DASHBOARD VISUAL

Inspirado en la referencia compartida:

## Header

- saludo según usuario;
- fecha;
- búsqueda;
- notificaciones;
- tema;
- perfil.

## Primera fila

4--5 KPI.

Ejemplo Gerente:

- Ventas mes.
- Facturación.
- Por cobrar.
- Servicios pendientes.
- Equipos por vencer.

## Segunda fila

- gráfico principal de ventas;
- gráfico distribución de servicios.

## Tercera fila

- próximos vencimientos;
- órdenes recientes;
- stock crítico.

## Sidebar

Logo BRUCE FIRE compacto arriba.

Menú con íconos.

Abajo:

- tema;
- configuración si tiene permiso;
- perfil;
- cerrar sesión.

En Dark Mode el logo puede usar la versión diseñada para fondo negro; en
Light Mode la versión para fondo blanco.

---

# 52. TOKENS DE DISEÑO

En lugar de poner colores directamente en cada componente, definir
tokens:

```text
--background
--foreground
--card
--card-foreground
--primary
--primary-foreground
--secondary
--muted
--border
--destructive
--success
--warning
--info
```

Luego Light/Dark redefine los valores.

Esto facilita mantener el estilo BRUCE FIRE sin duplicar CSS.

---

# 53. TIPOGRAFÍA E ICONOGRAFÍA

- Tipografía sans-serif moderna y limpia.
- Pesos limitados.
- Iconos consistentes (una sola familia).
- Evitar iconos 3D/mezclas de estilos dentro del software.
- El logo sí conserva la identidad gráfica corporativa.

---

# 54. ACCESIBILIDAD

- contraste adecuado;
- no depender solo del color;
- foco visible;
- labels;
- botones con área táctil suficiente;
- tablas accesibles;
- dark mode legible;
- estados con texto + color;
- confirmaciones claras.

---

# 55. RENDIMIENTO

- paginación server-side;
- búsqueda indexada;
- índices MySQL;
- eager loading controlado;
- no cargar todas las fotos;
- thumbnails;
- jobs para tareas pesadas;
- cache solo donde aporte;
- no agregar Redis inicialmente sin necesidad;
- consultas agregadas para dashboards;
- archivos fuera de DB.

---

# 56. RESPALDO Y RECUPERACIÓN

Definir:

- backup de MySQL;
- backup de archivos críticos;
- retención;
- restauración probada;
- separación de evidencias archivadas;
- certificado/credenciales protegidas;
- exportación de información importante.

---

# 57. DATOS HISTÓRICOS E IA

Antes de IA predictiva:

1.  inventariar históricos;
2.  importar clientes;
3.  deduplicar RUC/DNI;
4.  importar ventas;
5.  importar servicios;
6.  importar equipos identificables;
7.  normalizar fechas;
8.  normalizar estados;
9.  marcar fuente `Migrado`;
10. medir calidad.

Solo después se entrena/evalúa un modelo.

---

# 58. ROADMAP DE DESARROLLO

## Fase 1 --- Base

- proyecto Laravel;
- React/Inertia;
- MySQL;
- autenticación;
- tema Light/Dark;
- layout;
- roles/permisos;
- auditoría;
- configuración base.

## Fase 2 --- Maestros

- clientes;
- sedes;
- vehículos;
- catálogo;
- inventario;
- equipos del cliente.

## Fase 3 --- Comercial

- cotizaciones;
- conversión;
- ventas;
- escaneo;
- contado/crédito.

## Fase 4 --- Servicios

- órdenes;
- Planta;
- Campo;
- recojo/entrega;
- checklists;
- deficiencias;
- evidencias;
- actas.

## Fase 5 --- Certificados

- plantillas;
- reglas;
- PDF;
- QR;
- verificación.

## Fase 6 --- SUNAT

- Greenter;
- factura;
- boleta;
- NC/ND;
- XML/CDR;
- estados;
- GRE.

## Fase 7 --- Cobranzas y reportes

- cuotas;
- pagos;
- dashboards;
- exportaciones.

## Fase 8 --- IA

- migración histórica;
- predicción;
- lectura asistida;
- asistente gerencial.

---

# 59. CRITERIO PARA MVP

El MVP debe ser completamente usable sin IA.

Prioridad:

1.  usuarios/permisos;
2.  clientes;
3.  catálogo;
4.  inventario;
5.  cotización;
6.  venta;
7.  equipos;
8.  órdenes;
9.  Planta/Campo;
10. certificados;
11. facturación;
12. cobranza.

IA entra cuando la base de datos ya es confiable.

---

# 60. DECISIONES YA CONGELADAS

- Documento comercial previo: **COTIZACIÓN**.
- Cotización no va a SUNAT.
- Botón **Convertir a venta**.
- Venta origina Factura/Boleta.
- Dos técnicos: Planta y Campo.
- Campo realiza recojo/entrega.
- Planta realiza recarga.
- Campo realiza inspección/instalación/mantenimiento en campo.
- Checklist rápido.
- Deficiencias con foto.
- Adicionales autorizados por WhatsApp o presencial.
- Componentes/repuestos controlables.
- Barcode BRUCE FIRE al recibir/capturar equipo externo.
- Serie fabricante no se reemplaza.
- P.H. controlada separadamente.
- Certificados dinámicos por grupo.
- Firmas cargadas en plantilla/configuración.
- QR de verificación en certificados.
- Equipos no se eliminan si tienen historial.
- Transferencias auditadas.
- Evidencias con política de archivo.
- Light/Dark.
- Laravel + React + Inertia + MySQL.
- Greenter como integración SUNAT.
- Spatie Permission para roles/permisos.
- Spatie Activitylog para auditoría.
- IA como asistencia/predicción, no como autoridad.

---

# 61. PENDIENTES QUE YA NO CAMBIAN LA ARQUITECTURA

Solo quedan datos de configuración/carga:

- lista final real de repuestos;
- plantillas finales de certificados;
- firmas definitivas;
- imágenes/formatos de etiquetas físicas actuales;
- reglas exactas de campos obligatorios por tipo de certificado;
- series reales que utilizará BRUCE FIRE;
- credenciales/certificado SUNAT en despliegue;
- política empresarial definitiva de retención de fotografías;
- históricos concretos a migrar.

Estos puntos no requieren crear módulos nuevos.

---

# 62. RESULTADO ESPERADO

El sistema final debe permitir que una operación fluya sin duplicación:

```text
CLIENTE
  ↓
COTIZACIÓN
  ↓
CONVERTIR A VENTA
  ├── PRODUCTOS → unidades exactas → cliente
  └── SERVICIOS → Orden de Servicio
                       ↓
              Planta / Campo
                       ↓
          checklist + fotos + deficiencias
                       ↓
               datos técnicos completos
                       ↓
             acta + certificado + QR
                       ↓
VENTA → FACTURA / BOLETA → GRE si corresponde
                       ↓
                 COBRANZA
                       ↓
             PRÓXIMA ATENCIÓN
                       ↓
               ALERTA COMERCIAL
                       ↓
                 NUEVO CICLO
```

La meta no es tener "muchos módulos", sino que **cada módulo tenga una
responsabilidad clara y todos compartan una sola fuente de verdad**.

---

# 63. REFERENCIAS TÉCNICAS PARA IMPLEMENTACIÓN

- Laravel Starter Kit oficial: React + TypeScript + Inertia +
  Tailwind/shadcn.
- Spatie Laravel Permission: roles/permisos sobre Laravel Gate.
- Spatie Laravel Activitylog: auditoría de actividad y cambios.
- Greenter: integración de facturación electrónica peruana y SUNAT.
- Laravel Queues / Scheduler / Notifications: procesos asíncronos,
  tareas recurrentes y avisos.
- Laravel Precognition: opción para validación anticipada de
  formularios React/Inertia.

> Antes de instalar cualquier dependencia adicional se verificará
> compatibilidad con la versión final de Laravel/PHP. No se instalarán
> paquetes innecesarios.

---

# 64. CONCLUSIÓN

BRUCE FIRE tendrá un sistema empresarial integrado, pero no
sobrecargado. La arquitectura separa correctamente catálogo, inventario,
equipos del cliente, comercial, operación técnica, certificados y
tributación.

El elemento central del negocio técnico será el **Equipo del Cliente** y
su historial. El elemento central de coordinación será la **Orden de
Servicio**. El elemento comercial previo será la **Cotización**. La
**Venta** será el punto de entrada a la facturación. Greenter/SUNAT
permanecerá como una capa fiscal independiente.

El diseño visual tendrá identidad BRUCE FIRE, con **Light Mode y Dark
Mode**, dashboard moderno, sidebar, tarjetas, gráficos, tablas
profesionales y una interfaz móvil especialmente simplificada para
técnicos.

La IA se incorporará donde genere ahorro real: predicción de
demanda/servicios, priorización comercial, lectura asistida de placas y
resúmenes. El sistema seguirá funcionando completamente sin IA y las
decisiones técnicas, tributarias y comerciales sensibles permanecerán
bajo control humano.

**Este documento debe considerarse la fuente funcional maestra antes de
construir el ERD, diccionario de datos, mapa de pantallas, matriz final
de permisos y backlog técnico.**

---

# 65. ESTÁNDARES OBLIGATORIOS DE DESARROLLO

El desarrollo de BRUCE FIRE deberá seguir buenas prácticas desde el
primer módulo. Estas reglas no son recomendaciones opcionales: forman
parte de la definición técnica del proyecto.

## 65.1 Principios

- Código legible antes que código "ingenioso".
- Responsabilidad única.
- No duplicar lógica.
- No duplicar fuentes de verdad.
- Controllers delgados.
- Validaciones centralizadas.
- Autorización mediante Policies/Gates y permisos.
- Estados mediante Enums cuando corresponda.
- Operaciones críticas dentro de transacciones.
- Servicios externos encapsulados.
- Componentes React reutilizables.
- TypeScript estricto.
- Consultas eficientes y revisión de N+1.
- Tests para procesos críticos.
- Refactorización continua.
- Mobile-first obligatorio para los técnicos.
- No sobrearquitecturar.

## 65.2 Regla de modularidad

Cada dominio es dueño de su información:

```text
Clientes       → clientes, sedes, vehículos y equipos del cliente
Catálogo       → definición de productos, servicios y repuestos
Inventario     → existencias y movimientos físicos
Comercial      → cotizaciones y ventas
Servicios      → órdenes, trabajo técnico, deficiencias y evidencias
Certificados   → emisión y verificación técnica
Facturación    → CPE y comunicación SUNAT
Guías          → GRE
Cobranzas      → cuotas, saldos y pagos
Administración → usuarios, permisos, configuración y auditoría
```

Un módulo no debe crear una segunda copia de información que pertenece a
otro.

## 65.3 Flujo interno recomendado

```text
Request
   ↓
Controller
   ↓
Form Request / validación
   ↓
Action o Service
   ↓
Modelos de dominio
   ↓
Events / Jobs / Notifications cuando corresponda
```

Ejemplos de acciones con responsabilidad concreta:

```text
ConvertQuoteToSaleAction
AssignSerializedUnitsAction
CreateServiceOrderAction
ReceiveCustomerEquipmentAction
RegisterDeficiencyAction
AuthorizeAdditionalAction
CompleteTechnicalWorkAction
GenerateCertificateAction
IssueElectronicDocumentAction
RegisterPaymentAction
TransferCustomerEquipmentAction
```

No convertir todo en `Services` genéricos gigantes.

## 65.4 Controllers delgados

Un Controller:

- recibe la solicitud;
- delega validación;
- verifica autorización;
- llama a una acción/servicio;
- devuelve respuesta.

No debe contener cientos de líneas de reglas de negocio.

## 65.5 Transacciones

Usar transacciones en operaciones que deben completarse de manera
atómica.

Ejemplos:

- Cotización → Venta.
- Venta de equipo serializado → asignación de unidad → movimiento de
  inventario.
- Venta con servicios → creación de Orden.
- Registro de pago → actualización de saldo/cuota.
- Transferencia de equipo.
- Emisión de documentos cuando existan cambios internos relacionados.

Si una parte crítica falla, no debe quedar una operación a medias.

## 65.6 Refactorización continua

Al terminar cada funcionalidad:

1.  revisar duplicación;
2.  revisar nombres;
3.  extraer lógica repetida;
4.  reducir métodos demasiado grandes;
5.  revisar responsabilidades;
6.  revisar consultas;
7.  revisar permisos;
8.  revisar validaciones;
9.  ejecutar pruebas;
10. verificar responsive;
11. ejecutar formateadores/análisis definidos por el proyecto.

No esperar al final del proyecto para refactorizar.

## 65.7 No crear abstracciones prematuras

No crear Repository, Interface, Factory o patrón adicional solo porque
"se ve profesional".

Se utilizarán cuando exista una necesidad concreta.

La prioridad será una arquitectura sencilla, modular, mantenible y fácil
de defender académicamente.

---

# 66. SKILL INTERNA DE DESARROLLO BRUCE FIRE

Se mantendrá una guía interna, por ejemplo:

```text
/skills/bruce-fire-development/SKILL.md
```

Su propósito será evitar que diferentes etapas del desarrollo produzcan
código contradictorio.

## Antes de programar

```text
1. Leer el Documento Maestro vigente.
2. Identificar el módulo propietario del dato.
3. Revisar modelos/tablas/componentes existentes.
4. Revisar estados y permisos relacionados.
5. Buscar si Laravel ya resuelve la necesidad.
6. Revisar si una dependencia ya aprobada la resuelve.
7. Solo después diseñar código nuevo.
```

## Durante el desarrollo

```text
✓ nombres claros
✓ métodos pequeños
✓ Controller delgado
✓ Form Requests
✓ Actions/Services para negocio
✓ Policies y permisos
✓ transacciones
✓ Enums de estados
✓ componentes React reutilizables
✓ TypeScript
✓ validación backend obligatoria
✓ manejo explícito de errores
✓ mobile-first en pantallas técnicas
✓ accesibilidad básica
```

## Antes de cerrar una tarea

```text
□ pruebas pasan
□ permisos revisados
□ no hay lógica duplicada
□ no hay N+1 evidente
□ estados válidos
□ errores controlados
□ Light Mode revisado
□ Dark Mode revisado
□ escritorio revisado
□ móvil revisado
□ refactorización realizada
```

---

# 67. POLÍTICA DE LIBRERÍAS Y DEPENDENCIAS

La regla será:

> **No reinventar una solución que Laravel o una librería madura ya
> resuelve correctamente, pero tampoco instalar dependencias sin una
> necesidad real.**

Antes de instalar un paquete se comprobará:

1.  problema concreto que resuelve;
2.  si Laravel lo resuelve nativamente;
3.  compatibilidad con Laravel/PHP del proyecto;
4.  mantenimiento del paquete;
5.  licencia;
6.  documentación;
7.  impacto en seguridad;
8.  facilidad para reemplazarlo;
9.  si reduce realmente código/riesgo.

## 67.1 Dependencias base aprobadas

### Greenter

Integración SUNAT:

- construcción de documentos electrónicos;
- XML;
- firma;
- envío;
- respuesta/CDR;
- integración fiscal encapsulada.

Greenter no contiene reglas comerciales de BRUCE FIRE.

### spatie/laravel-permission

Para:

- roles;
- permisos;
- Gates;
- autorización granular.

### spatie/laravel-activitylog

Para auditoría de acciones relevantes.

### spatie/laravel-medialibrary

Candidato recomendado para:

- fotografías;
- evidencias;
- asociación de archivos a modelos;
- conversiones/miniaturas;
- organización de medios.

Antes de congelarlo se verificará compatibilidad con la versión exacta
del stack.

### spatie/laravel-backup

Candidato recomendado para estrategia de respaldo de base de
datos/archivos.

### Laravel Pint

Para formato consistente del código PHP.

## 67.2 Capacidades nativas que se prefieren antes de instalar otro paquete

Laravel ya proporciona capacidades para:

- Queues;
- Scheduler;
- Notifications;
- Events;
- Policies/Gates;
- Validation;
- Filesystem;
- Mail;
- Cache;
- Logging.

No instalar un paquete externo para reemplazarlas sin una razón
concreta.

## 67.3 Dependencias a evaluar solo cuando aparezca la necesidad

- `spatie/laravel-data`: DTOs/datos tipados si reduce duplicación
  real.
- herramientas de Excel/importación/exportación;
- librería de PDF elegida después de probar las plantillas reales;
- librería de QR/barcode mantenida y compatible;
- herramientas de filtros avanzados si Eloquent normal deja de ser
  suficiente.

No forman parte obligatoria del núcleo hasta validar su necesidad.

---

# 68. LIMPIEZA DEL STARTER KIT

El Starter Kit se utilizará como base técnica, no como producto final.

Después de crear el proyecto se realizará una limpieza controlada.

## 68.1 Conservar

```text
Autenticación
Login
Logout
Sesiones
Recuperación de contraseña, si finalmente se habilita
React
TypeScript
Inertia
Tailwind
shadcn/ui
Vite
Layout técnico útil
Soporte de tema/apariencia aprovechable
Infraestructura de validación/autenticación necesaria
```

## 68.2 Eliminar o reemplazar

```text
Registro público
Dashboard demo
Widgets demo
Contenido placeholder
Páginas de ejemplo
Enlaces promocionales/documentación
Componentes sin uso
Navegación demo
Datos ficticios
Pantallas que no pertenecen a BRUCE FIRE
```

No borrar archivos internos a ciegas. Antes se revisarán sus
dependencias.

## 68.3 Registro público

**Deshabilitado.**

BRUCE FIRE es un sistema interno.

Los usuarios son creados por un usuario autorizado.

---

# 69. USUARIOS Y ROLES INICIALES

El negocio tiene **cinco roles operativos**:

1.  Gerente.
2.  Vendedor.
3.  Almacén.
4.  Técnico de Planta.
5.  Técnico de Campo.

Adicionalmente existe:

6.  Administrador del sistema.

Por tanto:

> **5 roles operativos + 1 rol administrativo.**

No significa que existirán únicamente cinco cuentas. Puede haber varios
usuarios con el mismo rol.

Ejemplo:

```text
Usuario A → Técnico de Campo
Usuario B → Técnico de Campo
Usuario C → Vendedor
```

Los permisos se asignarán principalmente al rol mediante Spatie
Permission.

---

# 70. MOBILE-FIRST OBLIGATORIO PARA TÉCNICOS

Este requisito es **obligatorio**, no opcional.

Las interfaces de:

- Técnico de Planta;
- Técnico de Campo;

deben diseñarse primero para celular y después adaptarse a
tablet/escritorio.

## 70.1 Objetivo

El técnico debe poder trabajar desde el celular mientras:

- recoge equipos;
- recibe equipos;
- escanea barcode;
- registra equipo externo;
- realiza checklist;
- toma fotos;
- registra deficiencias;
- revisa autorizaciones;
- ejecuta mantenimiento;
- inspecciona;
- instala;
- registra entrega;
- obtiene conformidad.

## 70.2 Pantalla móvil de trabajo

Ejemplo conceptual:

```text
┌───────────────────────────┐
│ OS-00152                  │
│ FONPELL S.A.C.            │
│ Recarga · 15 equipos      │
├───────────────────────────┤
│ Equipo 03 / 15            │
│ BF-EQ-000245              │
│ PQS ABC · 9 kg            │
│                           │
│ [ Escanear código ]       │
│                           │
│ Cilindro       CONFORME   │
│ Válvula        CONFORME   │
│ Manómetro      OBSERVADO  │
│ Manguera       CONFORME   │
│ Pasador        CONFORME   │
│                           │
│ [ Tomar foto ]            │
│ [ + Deficiencia ]         │
│                           │
│ [Anterior]   [Siguiente]  │
└───────────────────────────┘
```

## 70.3 Reglas UX móvil

- botones táctiles grandes;
- información esencial primero;
- navegación por pasos;
- cámara accesible directamente;
- escaneo rápido;
- selector Conforme / Observado / N/A;
- guardar avance;
- minimizar escritura;
- precargar datos conocidos;
- feedback inmediato;
- evitar modales innecesarios;
- no utilizar tablas horizontales;
- acciones principales en zona fácil de alcanzar;
- errores comprensibles;
- funcionar correctamente con teclado móvil.

## 70.4 Diferencia de interfaces

**Gerente/Ventas/Almacén en escritorio:** tablas, filtros, gráficos y
operaciones administrativas.

**Técnico en móvil:** cards, pasos, cámara, barcode, checklist y botones
grandes.

No se diseñará una única pantalla de escritorio para luego "encogerla".

---

# 71. ESTRATEGIA DE CALIDAD

## 71.1 Pruebas prioritarias

Se priorizarán pruebas sobre reglas que podrían causar pérdidas o
inconsistencias:

- permisos;
- conversión Cotización → Venta;
- cálculo de totales;
- asignación de equipos serializados;
- movimientos de inventario;
- creación de Orden;
- estados de Orden;
- deficiencias/autorizaciones;
- transferencia de equipo;
- generación de certificado;
- facturación;
- registro de pagos.

## 71.2 Datos de prueba

Factories/seeders permitirán generar escenarios coherentes de desarrollo
sin utilizar información real innecesariamente.

## 71.3 Revisión visual

Toda funcionalidad de interfaz se comprobará en:

- Light;
- Dark;
- escritorio;
- tablet cuando aplique;
- móvil, obligatorio para técnicos.

---

# 72. ORDEN CORRECTO PARA EMPEZAR A DESARROLLAR

No empezar directamente por Facturación ni IA.

## Paso 1 --- Crear base

```text
Laravel Starter Kit
React + TypeScript + Inertia
MySQL
Git
.env
configuración local
```

## Paso 2 --- Limpiar Starter Kit

Eliminar demos y registro público conservando autenticación e
infraestructura necesaria.

## Paso 3 --- Sistema visual

Construir:

- AppLayout;
- sidebar;
- header;
- ThemeToggle;
- tokens Light/Dark;
- navegación;
- estados comunes;
- componentes UI base.

## Paso 4 --- Usuarios, roles y permisos

- instalar/configurar Permission;
- crear roles;
- seed inicial;
- Policies;
- navegación por permisos;
- pruebas de autorización.

## Paso 5 --- Maestros

En orden:

```text
Clientes
→ Sedes
→ Vehículos
→ Catálogo
→ Inventario
→ Equipos del Cliente
```

## Paso 6 --- Comercial

```text
Cotización
→ aceptación
→ Convertir a Venta
→ Venta
```

## Paso 7 --- Servicios

```text
Orden
→ Planta/Campo
→ recojo
→ recepción
→ barcode
→ checklist
→ deficiencias
→ autorización
→ ejecución
→ entrega
```

Aquí se valida exhaustivamente la experiencia móvil.

## Paso 8 --- Certificados

Solo cuando los datos técnicos ya estén consolidados.

## Paso 9 --- Facturación/SUNAT

Integrar Greenter sobre una Venta ya estable.

## Paso 10 --- GRE/Cobranzas/Reportes

Completar procesos complementarios.

## Paso 11 --- IA

Solo con datos suficientes y procesos estables.

---

# 73. DEFINICIÓN DE TERMINADO POR FUNCIONALIDAD

Una historia no está terminada únicamente porque "funciona en mi PC".

Debe cumplir:

```text
□ regla de negocio implementada
□ validación backend
□ autorización
□ errores controlados
□ auditoría si aplica
□ prueba crítica
□ UI consistente
□ Light Mode
□ Dark Mode
□ responsive
□ móvil técnico si aplica
□ sin duplicación evidente
□ consultas revisadas
□ código formateado
□ refactorizado
□ documentación actualizada si cambió una regla
```

---

# 74. REGLA DE CONTROL DE CAMBIOS

El Documento Maestro es la fuente funcional.

Si durante desarrollo aparece una nueva idea:

```text
Idea
→ comprobar necesidad real
→ comprobar módulo propietario
→ comprobar impacto en flujo/datos/permisos
→ comprobar si ya existe solución
→ aprobar cambio
→ actualizar Documento Maestro
→ recién implementar
```

Esto evita que el sistema vuelva a llenarse de funciones
contradictorias.

---

# 75. DECISIÓN TÉCNICA FINAL PARA EL ARRANQUE

El proyecto comenzará con una base pequeña y controlada:

```text
Laravel
React
TypeScript
Inertia
Tailwind
shadcn/ui
MySQL

+
Spatie Permission
Spatie Activitylog

Después, cuando el módulo lo necesite:
Greenter
Media Library
Backup
QR/Barcode
PDF/Excel
```

No instalar todo el ecosistema en el primer commit.

El orden será:

> **Base limpia → seguridad → diseño empresarial → maestros → comercial
> → operación técnica móvil → certificados → SUNAT → cobranzas/reportes
> → IA.**

Esta secuencia reduce retrabajo porque SUNAT, certificados e IA se
apoyarán en datos y procesos ya estabilizados.

---

---

> Las secciones §76–§90 (historial) están en `documentos/archivo/Documento_Maestro_v9_historial_76-90.md`.
