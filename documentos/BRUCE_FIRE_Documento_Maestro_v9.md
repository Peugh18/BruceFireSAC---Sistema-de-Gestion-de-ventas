# BRUCE FIRE S.A.C.

## Documento Maestro del Sistema Web de Gestión Comercial, Operativa y Técnica

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

# 76. ADENDA — AUDITORÍA TÉCNICA Y PLAN DE EJECUCIÓN (2026-09-19)

Este documento maestro (v9) ya existía en el repositorio y fue
eliminado sin commitear antes de esta sesión. Se restauró desde
`git show HEAD` porque sigue siendo la fuente funcional vigente: casi
todo lo descrito por voz el 2026-09-19 (roles, dashboards, clientes,
equipos, checklist, deficiencias, autorización de adicionales,
cotización→venta sin re-digitar, condición de pago a crédito con
cuotas, certificados con motor de reglas y QR propio, GRE, cobranzas)
ya estaba especificado aquí y, según auditoría de código, ya está
implementado en un alto porcentaje (roles Almacén/Gerente/Técnico de
Campo/Técnico de Planta/Vendedor con permisos granulares vía
spatie/laravel-permission, coinciden 1:1 con la sección 35/36).

Esta adenda no reemplaza el documento; registra qué se auditó, qué
está confirmado roto, qué es genuinamente nuevo respecto a v9, y en
qué orden se ejecuta, siguiendo la Regla de Control de Cambios
(sección 74).

## 76.1 Confirmado sólido (no se toca)

- Matriz de roles/permisos (`php artisan permission:show`) ya replica
  la sección 36 exactamente.
- `Equipment`/`EquipmentEvent`/`EquipmentTransfer`: modelo de equipo
  del cliente ya separado del catálogo, con historial y estados
  (sección 7-8).
- `InventoryUnit` (serie, marca, capacidad, año, barcode, estado) +
  `InventoryMovement` (Kardex con stock antes/después, motivo,
  referencia): la trazabilidad de fondo ya es correcta.
- `SaleItemProcessor`: al vender una unidad serializada, ya descuenta
  stock, marca la unidad `vendido` y crea el `Equipment` del cliente
  automáticamente (sección 13.2 y 50.1 ya conectadas).
- Cotización → conversión a venta sin re-digitar (`QuoteController`),
  condición de pago a crédito con `SaleInstallment`/`SalePayment`,
  notas de crédito/débito (`CreditDebitNote`), guía de remisión
  (`ShippingGuide*`) y checklist/deficiencias (`ChecklistItem`,
  `Deficiency`) ya existen como módulos separados — no se reinventan,
  solo se auditan puntualmente si el usuario reporta un bug concreto.
- El modal "agregar cliente" en Ventas/Cotizaciones ya reutiliza el
  mismo componente que la pantalla de Clientes (`InlineClientDialog`
  → `ClientForm`), tal como se pidió por voz — no se duplica.

## 76.2 Confirmado roto (con evidencia de código)

1. Gestión de Inventario fragmentada — el motivo original de esta
   conversación. Hoy conviven `CatalogItem` (tabla `catalog_items`,
   con flags `controla_stock`/`control_serializado`), una migración
   pendiente sin aplicar que separa `products`/`services`
   (`2026_09_19_065235_...`, `2026_09_19_065236_...`) con
   `legacy_catalog_item_id`, y una tabla `inventory_stocks` aparte
   1:1. Resultado: 4 pantallas para un mismo concepto (Catálogo →
   Inventario → Recepción de lote → Movimientos) y, tras crear un
   producto, no hay un número de stock editable visible en su ficha
   (solo checkboxes y páginas separadas para "ajuste" o "recepción").
   Decisión ya tomada con el usuario: terminar la separación
   Product/Service (no volver a un `CatalogItem` unificado),
   eliminando `CatalogItem`/`catalog_items`/`inventory_stocks`, y
   ejecutar por etapas (Inventario primero, luego reconexión a
   Ventas/Cotizaciones/Órdenes de servicio/Reportes/Facturación
   SUNAT).
2. `ClientController::documentLookup` no revisa la base local antes
   de llamar a la API externa (APIsPeru) —
   app/Http/Controllers/ClientController.php líneas 65-91. El usuario
   pidió explícitamente: "si ya tenemos un cliente agregado... no
   llamamos de nuevo, buscamos internamente". Hoy siempre golpea la
   API aunque el RUC/DNI ya exista en `clients.numero_documento`.
   Fix: antes de invocar `DocumentLookupService`, buscar
   `Client::where('numero_documento', $numero)->first()` y devolver
   esos datos si existe, sin llamar a la API.

## 76.3 Genuinamente nuevo respecto a v9 (se agrega al alcance)

- Impresión de stickers de código de barras en hoja de 4 al recibir
  un lote de extintores nuevos en Almacén: la sección 9 y 41.8 ya
  cubren que se genera barcode, pero no el layout de impresión. Se
  agrega: al confirmar una Recepción de unidades serializadas, botón
  "Imprimir stickers" que arma una hoja A4 con grilla de 4 etiquetas
  (código de barras + código interno) por página, para no desperdiciar
  una hoja completa por unidad.
- Ejemplos de certificado y checklist recibidos como archivos de
  referencia el 2026-09-19 (quedan como insumo de diseño, no se
  transcriben aquí por ser documentos de terceros):
    - Planilla de inspección con columnas Ítem, N° interno, N° serie,
      ubicación, agente, capacidad, manómetro, pasador, manguera,
      marca/procedencia, fabricación, tarjeta, fecha próx. recarga,
      vencimiento P.H., observación — confirma exactamente la sección
      24 ("por extintor") y valida que debe ser una tabla dinámica de N
      filas, no N plantillas.
    - Certificado de instalación de lámina de seguridad con datos del
      cliente, detalle de instalación (mampara/medida), características
      técnicas, QR de verificación y firma — encaja en la sección 26
      como un tipo más de "Otros configurables" del motor de reglas,
      mismo patrón que Operatividad/P.H./Capacitación.
- Confirmación explícita de que el checklist/certificado debe
  generalizarse para todos los servicios técnicos de campo
  (fumigación, desratización, pozos sépticos, sistema de detección,
  cámaras, pozo a tierra, lámina de seguridad), no solo extintores —
  la sección 15 ya decía "Otros configurables"; se confirma que el
  motor de reglas (26.1) debe resolver la plantilla por tipo de
  servicio, no por texto libre.

## 76.4 Plan de ejecución por etapas (aprobado)

Etapa 1 — Gestión de Inventario (en curso):
terminar migración products/services, retirar
CatalogItem/catalog_items/inventory_stocks; un solo formulario "Nuevo
registro" (Producto/Servicio) con stock inicial y mínimo numéricos;
ficha de producto con ajuste de stock numérico visible; recepción de
lote (series) accesible desde la propia ficha del producto; sticker
de código de barras en hoja de 4 al recibir lote serializado; 3
pestañas: Productos en Stock · Servicios de Taller · Kardex.

Etapa 2 — Reconexión: SaleItemProcessor, StoreSaleRequest,
StoreQuoteRequest, DeficiencyController,
ServiceOrderChecklistController, ReportService,
BillingService/SaleDocumentBuilder apuntando a Product/Service, sin
catalog_item_id; fix de documentLookup con consulta local antes de la
API externa.

Etapa 3 (a definir tras Etapa 1-2): certificados con plantilla
dinámica por tipo y generalización a servicios no-extintor;
investigación SUNAT de estados de comprobante/CDR y GRE electrónica
(secciones 30-31, aún pendientes de investigar en v9).

## 76.5 Bug crítico encontrado y resuelto antes de la Etapa 1

Antes de tocar Inventario se detectó que **la base de datos de
desarrollo estaba desincronizada del código**: las migraciones
`2026_09_18_130001_create_sales_table.php`,
`2026_09_18_145714_create_electronic_documents_table.php` y
`2026_09_18_164400_create_credit_debit_notes_table.php` ya se habían
ejecutado, pero después alguien les agregó columnas nuevas editando el
archivo en vez de crear una migración nueva — esos cambios nunca
llegaron a la base real. Resultado: **toda venta, factura/boleta o
nota de crédito/débito fallaba con error SQL** ("no such column:
mto_valor_unitario", etc.) en la app real, aunque los tests pasaran
(usan una base efímera que sí corre todas las migraciones desde cero).

Se corrigió con una migración de reparación
(`2026_09_19_070000_repair_sales_billing_columns_drift.php`) que
agrega solo las columnas faltantes de forma idempotente, más el
arreglo del bug propio de
`2026_09_19_065236_add_product_service_and_tax_snapshots_to_sales_and_quotes.php`
(a `sale_items` le faltaban sus columnas de impuestos antes de que la
migración intentara actualizarlas). Regla para evitar que se repita:
**nunca editar una migración que ya corrió** (`php artisan
migrate:status` para verificar); si falta una columna, se crea una
migración nueva. Verificado: 445 tests pasan y el esquema de
`sales`/`sale_items`/`electronic_documents`/`credit_debit_notes` en la
base de desarrollo ya coincide con lo que el código escribe.

---

# 77. INVESTIGACIÓN CON FUENTES — GRE, CAJA Y DASHBOARDS (2026-09-19)

Por pedido explícito del usuario, esta sección documenta hallazgos con
fuente verificable, no supuestos.

## 77.1 Guía de Remisión Electrónica (GRE): ya está construida y bien

Auditoría de código: `ShippingGuide` (modelo), `ShippingGuideController`,
`ShippingService`, `ShippingGuideBuilder` y `GreenterService::sendDespatch()`
**ya existen y ya implementan exactamente el flujo de la sección 30-31**:

- Motivo de traslado con los códigos del Catálogo 20 de SUNAT (venta
  `01`, compra `02`, traslado entre establecimientos `04`, importación
  `08`, exportación `09`, otros `13`) — coincide con la fuente oficial
  ([Guía de Remisión | SUNAT](https://cpe.sunat.gob.pe/tipos_de_comprobantes/guiaderemision),
  [Guía de Remisión Electrónica - Modelo general | SUNAT](https://orientacion.sunat.gob.pe/guia-de-remision-electronica-modelo-general)).
- Usa el modelo `Despatch` de Greenter (no `Invoice`/`Note`), en un
  servicio separado (`ShippingService`), exactamente como pide la
  sección 31 ("Para GRE se mantendrá un servicio separado porque su
  envío utiliza flujo/API diferente").
- Guarda destinatario (cliente o datos libres), transportista
  (razón social/RUC), vehículo, conductor, modalidad
  (transporte público/privado) — todo lo que la sección 30 pedía.
- Asociada opcionalmente a una `Sale` (`sale_id` nullable), tal como
  el usuario intuía ("creo que va asociada a una venta").

**No hace falta investigarla ni reconstruirla — ya sigue el patrón
correcto.** Pendiente real (no arquitectónico): confirmar que la
serie/formato y los anexos técnicos de la Resolución N.º 000108-2026/SUNAT
(cambio de conductor en tránsito, vigente desde junio 2026) estén
cubiertos si BRUCE FIRE los necesita — la GRE-remitente es obligatoria
hasta el 31/08/2026 y la GRE-transportista hasta el 28/02/2027 con
periodo de discrecionalidad
([Guías de Remisión Electrónicas SUNAT: Cambios para 2026](https://llbsolutions.com/es/guias-remision-electronicas-sunat-cambios-clave-2026/),
[Guía de Remisión Electrónica 2026: Obligatoria Desde Julio](https://perugestiona.pe/tramites-sunat/guia-remision-electronica/)).
Esto se revisa cuando BRUCE FIRE empiece a emitir GRE en producción,
no ahora.

## 77.2 Control de caja: no existe, se agrega (patrón "arqueo ciego")

Confirmado por auditoría (`grep -ri caja`): **no existe ningún módulo
de control de caja** en el sistema. Se diseña con el patrón estándar
de POS ("arqueo ciego"), que es la práctica más usada porque fuerza
honestidad en el conteo (el sistema no le muestra al vendedor cuánto
_debería_ tener antes de que él cuente)
([Arqueo de caja: Checklist de control de efectivo en tienda](https://safetyculture.com/library/retail/arqueo-q5ufvffkecwk4ymc),
[Arqueo de caja: cómo hacerlo paso a paso](https://yo-facturo.com/blog/arqueo-de-caja-guia/)):

1.  **Apertura de turno**: el Vendedor cuenta el efectivo físico que
    tiene y lo declara antes de vender (fondo fijo inicial).
2.  Durante el turno, cada venta con forma de pago `efectivo` se suma
    automáticamente al esperado de esa caja (ya existe
    `SalePayment.forma_pago` para esto, no hay que inventar nada
    nuevo ahí).
3.  **Cierre de turno (arqueo ciego)**: el Vendedor cuenta el efectivo
    físico y lo declara **sin ver el total esperado**. El sistema
    calcula la diferencia después y la registra (sobrante/faltante).
4.  El Gerente ve el historial de aperturas/cierres de todos los
    vendedores, con diferencias resaltadas.

Tabla nueva sugerida: `cash_registers` (turno: usuario, fecha/hora
apertura, monto apertura, fecha/hora cierre, monto contado al cierre,
monto esperado calculado, diferencia, observación). No se mezcla con
`sale_payments` (que ya registra cada pago individual); `cash_registers`
solo agrupa el turno y hace el arqueo.

## 77.3 Dashboards por rol: hoy solo existe el de Gerente

Auditoría: `DashboardController` renderiza siempre el mismo dashboard
(`DashboardService::build()`), sin importar el rol — no hay
diferenciación por Vendedor/Almacén/Técnico. La sección 5 de este
documento ya especifica qué debe ver cada rol; falta implementarlo.
Regla confirmada con el usuario: el Vendedor ve el efectivo/ventas
**del día** (para su arqueo de caja) pero no el acumulado mensual de
la empresa; eso es exclusivo de Gerente/Administrador.

## 77.4 RUC/DNI: doble consumo de API — verificado, no hay llamada duplicada en el código propio

Se revisó `client-form.tsx` línea por línea: el lookup se dispara al
alcanzar el largo exacto (8 DNI / 11 RUC) y de nuevo en `onBlur`, pero
ambos casos están deduplicados por `lastQueriedDoc` (mismo
tipo+número no vuelve a llamar). El único modal de "agregar cliente"
en Ventas/Cotizaciones reutiliza el mismo componente, no lo duplica.
Con el fix de la sección 76.2 (consulta local antes de la API), un
mismo RUC/DNI ya conocido nunca vuelve a tocar la API externa. Si el
usuario sigue viendo consumo de 2 créditos por una consulta genuinamente
nueva, es del lado del proveedor (APIsPeru) — se debe confirmar en su
panel de facturación, no es un bug de este código.

## 77.5 Secuencia de trabajo actualizada

```text
Etapa 1 (en curso) — Gestión de Inventario: Product/Service, stock numérico, retirar CatalogItem.
Etapa 2 — Reconexión de Ventas/Cotizaciones/Reportes/Facturación a Product/Service.
Etapa 3 — Certificados dinámicos por tipo + generalización a servicios no-extintor.
Etapa 4 — Control de caja (arqueo ciego) + dashboards diferenciados por rol.
```

GRE (sección 77.1) no entra en esta secuencia porque ya está resuelta;
solo se revisa si se detecta un caso real no cubierto al usarla.

Nota: las notas de voz del usuario se guardan tal cual, sin editar, en
`documentos/notas_de_voz_usuario.md`, para no perder contexto entre
sesiones. Este documento (v9) es la versión ya organizada y con
fuentes; ese otro archivo es el material crudo de origen.

---

# 78. INVESTIGACIÓN ADICIONAL CON FUENTES — VACÍOS DETECTADOS (2026-09-19, continuación)

El usuario repitió por voz el mismo contexto de la sección 77 (roles,
catálogo muerto, certificados, cobranzas, técnicos) porque el
observador de memoria entre sesiones estuvo caído (ver aviso de
sistema) y temía que se hubiera perdido el hilo. **No se perdió**: todo
eso ya está resuelto en las secciones 76-77 con la decisión ya tomada
(catálogo se elimina, sección 76.2 punto 1) y el plan por etapas ya
aprobado (76.4/77.5). Esta sección solo documenta dos reglas de
negocio que el usuario mencionó por voz y que, al auditar el documento
completo, **no estaban capturadas en ningún lado todavía** — no son
opinión del usuario, son requisitos normativos verificables:

## 78.1 RUC debe estar "Activo" y "Habido" antes de facturar — regla dura, no solo advertencia

El usuario pidió explícitamente "no vamos a facturar a un RUC que está
inactivo" y pidió investigarlo a fondo. Confirmado con fuente: SUNAT
exige que, para incorporarse y operar en el sistema de emisión
electrónica, el RUC esté en condición **Activo** y con domicilio fiscal
**Habido**; si el domicilio pasa a "No habido", SUNAT bloquea la
emisión de comprobantes
([Condiciones para incorporarse al Sistema de Emisión Electrónica — SUNAT](https://orientacion.sunat.gob.pe/13-condiciones-para-incorporarse-el-sistema-de-emision-electronica),
[Qué significa estado activo en consulta RUC SUNAT](https://consultaruc-sunat.com/que-significa-estado-activo-consulta-ruc-sunat/)).

Esto no estaba en ninguna sección del documento (6, 12, 13, 28). Se
agrega como regla de negoción dura:

- El lookup de RUC (sección 6.1 / `DocumentLookupService`) debe guardar
  también `estado_contribuyente` (ej. `ACTIVO`) y
  `condicion_domicilio` (ej. `HABIDO`) devueltos por la fuente
  (APIsPeru u otra), no solo razón social y dirección.
- Al emitir **Factura o Boleta** (no aplica a Cotización, que es
  interna), si el cliente tiene `estado_contribuyente != ACTIVO` o
  `condicion_domicilio != HABIDO`, el sistema debe **bloquear el envío
  a SUNAT** con mensaje explícito, no solo mostrar una advertencia
  visual. Si el dato guardado tiene más de N días (a definir, sugerido
  30), se debe re-consultar antes de bloquear/permitir, para no quedar
  con un estado desactualizado indefinidamente.
- No aplica a Boleta con DNI (personas naturales no tienen estado
  RUC).

## 78.2 Prueba hidrostática de extintores: vencimiento a 5 años, no "a definir"

El usuario mencionó "vencimiento de la prueba aerostática" (aerostática
es un error de transcripción de voz; el término correcto es
**hidrostática**) como un campo del checklist (sección 24) y como tipo
de certificado (sección 15, 26) pero el documento nunca fijó la
periodicidad, dejándolo como dato libre. Confirmado con la norma
técnica peruana NTP 350.043-1 (INDECOPI): el intervalo máximo entre
pruebas hidrostáticas es de **5 años** para extintores portátiles, y
también de 5 años para los cilindros/botellas impulsoras de gas en
extintores de rueda
([NTP 350.043-1, 3ª edición 2011](https://www.regionpiura.gob.pe/documentos/dependencias/phpmZ0ZJJ.pdf),
resumen técnico en
[NTP 350.043: La Norma Peruana de Extintores, Explicada](https://www.firetrack.pe/recursos/ntp-350-043-extintores)).
La inspección visual, en cambio, es **mensual** según NFPA 10
([Checklist de auditoría de extintores según NFPA 10](https://nfpatoolkit.co/blog/checklist-auditoria-extintores.html)).

Se agrega como regla derivada, no como dato manual:

- `InventoryUnit` (o la unidad serializada del extintor) debe guardar
  `fecha_ultima_prueba_hidrostatica`. El sistema **calcula**
  `fecha_proxima_prueba_hidrostatica = fecha_ultima_prueba_hidrostatica + 5 años`
  — el técnico no la escribe a mano, para que las alertas de la
  sección 27 puedan dispararse solas.
- Igual patrón para la recarga anual (sección "vencimiento recurrente",
  50.4): la fecha de próxima recarga/certificado de operatividad se
  calcula a partir de la fecha de recarga/instalación + 1 año, no se
  captura como texto libre.

## 78.3 Confirmación: la decisión "matar catálogo" no requiere más investigación

El usuario volvió a insistir por voz en eliminar "Catálogo" como
concepto separado ("no catálogo, que eso no existe"). Esto ya es
exactamente la decisión tomada en la sección 76.2 punto 1 y el plan de
76.4/77.5 (Etapa 1): se retira `CatalogItem`/`catalog_items`, el
inventario pasa a un único flujo de alta "Producto o Servicio" con
stock numérico visible en la propia ficha, sin pantallas separadas. No
hay nada nuevo que decidir aquí — se reafirma para que quede explícito
que no es una opinión pendiente de validar, es una decisión ya cerrada
que solo falta ejecutar en la Etapa 1.

## 78.4 Corrección menor a la sección 41.3 / docs técnicos de Greenter

`docs/FACTURACION_GREENTER_SUNAT.md` (guía técnica de referencia para
implementación, separada de este documento) ya cubre en detalle:
tipos de documento (Catálogo 01), detracciones SPOT al 12% sobre
S/700, formas de pago contado/crédito con cuotas (RS 193-2020), notas
de crédito/débito con motivos (Catálogo 09), GRE remitente vía API
REST 2022+, ciclo de envío/CDR con clasificación de códigos de
respuesta, y QR tributario oficial. No se duplica aquí; se referencia
como la fuente técnica de implementación para la Etapa 3+ (Facturación
SUNAT).

## 78.5 Casos especiales de Greenter revisados con fuente oficial (2026-09-19)

Se investigó toda la documentación de `greenter.dev` (ejemplos de
exonerada, gratuita, descuento por línea, percepción, anticipo,
detracción, exportación, ICBPER, boleta, contingencia, forma de pago,
más `/usage`, `/starter`, `/production`, `/faq`, `/packages/xml`) y se
volcó el análisis completo en `docs/FACTURACION_GREENTER_SUNAT.md`
sección 12, con la decisión de qué sí construir y qué no, para no
sobre-construir casos que el negocio no tiene. Dos hallazgos relevantes
para este documento:

- **Anticipo** (§12.1 del doc técnico) sí aplica al negocio: las
  instalaciones (sistema de detección, cámaras, pozo a tierra) suelen
  cobrar un adelanto antes de ejecutar la obra. Se agrega a la sección
  12.2 (conversión de cotización a venta): cuando una cotización de
  instalación se acepta con anticipo, se emite primero la factura del
  anticipo y luego la factura final referenciándola (Greenter lo
  soporta de forma nativa vía `Prepayment`).
- **Código de detracción dudoso**: el código `022` que ya estaba en
  `docs/FACTURACION_GREENTER_SUNAT.md` §4 (Otros servicios
  empresariales) puede no ser el correcto — el más literal para
  "recarga y mantenimiento de extintores" sería `020` (Mantenimiento y
  reparación de bienes muebles) del Catálogo 54, pero esto **no se
  puede resolver con documentación pública genérica**; queda marcado
  como pendiente de confirmar con el contador de BRUCE FIRE antes de
  la Etapa 3, no se debe codificar a ciegas.
- Exonerada, percepción, exportación e ICBPER no aplican al modelo de
  negocio actual (venta/servicio local gravado con IGV, no agente de
  percepción, no exporta, no vende bolsas plásticas) — se documentan
  como descartados a propósito, no como pendientes.

---

# 79. VERIFICACIÓN CRUZADA — NOTA DE VOZ 2026-09-19 vs DOCUMENTO (2026-09-20)

Se auditó `documentos/notas_de_voz_usuario.md` (entrada del 2026-09-19)
punto por punto contra este documento para confirmar que nada quedó
sin integrar. Resultado: **todo el contenido funcional/de producto ya
estaba capturado**, con correspondencia exacta:

- Roles, almacenero, stickers de barcode en hoja de 4 → §76.3.
- Certificados por destino (local: Operatividad+Capacitación;
  vehículo: Operatividad+P.H.) → ya especificado literalmente en §26.1,
  coincide palabra por palabra con lo dictado.
- Otros servicios con certificado propio (fumigación, desratización,
  detección, pozo a tierra, cámaras, lámina de seguridad, etc.) → §76.3.
- Trazabilidad de venta (unidades serializadas agrupadas por cantidad,
  serie interna) → §76.1 (confirmado sólido, no se toca).
- Clientes: autocompletado RUC/DNI sin re-consultar si ya existe, RUC
  no habido bloqueado → §76.2 punto 2 (fix) y §78.1 (regla dura).
- Inventario (queja central), Producto/Servicio, stock numérico → §76.2
  punto 1 y §76.4 (Etapa 1, decisión ya cerrada).
- Checklist técnico generalizable a todos los servicios → §76.3.
- Gerente/Admin mismo rol, dashboards diferenciados sin acumulado
  mensual para Vendedor → §77.3.
- Control de caja (arqueo ciego, investigar) → §77.2.
- Guía de Remisión (investigar) → §77.1 (ya resuelta, no requiere
  reconstrucción).
- Cotización como documento interno que se envuelve en boleta/factura
  → §76.1.

Dos elementos de la nota de voz **no tenían ningún lugar en el
documento** porque no son especificación de producto sino principios
de cómo construir/colaborar. Se agregan aquí para que no se pierdan:

## 79.1 Principio de arquitectura: evitar hardcodeo (regla general, no solo RUC/API)

La nota de voz pide explícitamente evitar "harcodeo" como queja
general de arquitectura. Hasta ahora solo estaba resuelto el caso
puntual (RUC/DNI: no volver a llamar la API si el dato ya existe
localmente, §76.2 punto 2 y §78.1). Se eleva a regla de arquitectura
general, aplicable a todo el sistema, no solo a ese caso:

- Catálogos de SUNAT (tipos de documento, motivos de traslado, códigos
  de detracción, unidades de medida), tipos de certificado, roles y
  permisos, y cualquier lista que pueda cambiar por normativa o por
  decisión de negocio, se modelan como datos configurables (tabla,
  seeder, config de Laravel) — nunca como `match`/`switch`/arrays
  literales repetidos en varios controladores.
- Antes de llamar a una API externa (APIsPeru/RENIEC, Greenter,
  cualquier otra), siempre se verifica primero si el dato ya existe en
  la base local; la API es el último recurso, no el primero. Ya
  aplicado a clientes (§76.2.2); debe aplicarse igual a cualquier
  integración externa futura.
- Al detectar un valor fijo repetido en código que debería ser
  configurable, se reporta y se corrige en la misma tarea si el
  alcance lo permite, en vez de replicarlo.

## 79.2 Estilo de colaboración del usuario con Claude (meta, no producto)

Instrucción repetida varias veces por voz, registrada aquí de forma
explícita porque gobierna cómo se debe trabajar en todas las sesiones
de este proyecto, no solo esta: el usuario **no** quiere entregar todo
el contexto de golpe para que Claude "arregle todo" en un solo tiro.
Prefiere dar una idea vaga y que Claude:

1.  la mejore y complete los huecos con investigación propia (con
    fuentes verificables, como en §77-78, no supuestos);
2.  decida cómo conectar esa idea con lo que ya existe en el sistema;
3.  documente en Markdown (este documento y
    `documentos/notas_de_voz_usuario.md`) todo lo investigado, para no
    perder el hilo entre sesiones aunque el usuario no repita el
    contexto completo la próxima vez.

Esta preferencia ya se está seguiendo de facto desde §76 en adelante;
se deja explícita para que una sesión futura no vuelva a pedir "dame
todo el contexto" innecesariamente.

---

# 80. AUDITORÍA DEL ROL VENDEDOR Y VERIFICACIÓN DE GREENTER (2026-09-20)

Por pedido explícito del usuario, se auditó con evidencia de código
(no supuestos) el rol Vendedor completo (43 rutas:
`app/Http/Controllers/Vendedor/*`) y su integración real con Greenter
y con la API de RUC/DNI. Progresó desde la última auditoría (§76-78):
ya existe módulo de Caja (`CashRegisterController`/`CashRegister`,
contradice el "no existe" de §77.2) y el Dashboard de Vendedor ya está
correctamente aislado por rol (contradice el "no diferenciado" de
§77.3, ver `DashboardController` con comentario explícito en código:
"NUNCA expone acumulados mensuales ni datos globales de la empresa").

## 80.1 Confirmado sólido

- `RucLookupService` (reemplazó a `DocumentLookupService`): ya busca
  primero en `clients.numero_documento` local y solo golpea APIsPeru
  si no existe — cumple §76.2 punto 2. Ya captura y persiste
  `estado_contribuyente`/`condicion_domicilio` en cada consulta.
- Dashboard Vendedor (`DashboardController`): solo ventas/caja del día
  del vendedor autenticado (`where('vendedor_id', $user->id)`), cero
  acumulado mensual/empresa — cumple §77.3 al pie de la letra.
- Cobertura funcional completa: ventas, cotizaciones, clientes (+sedes
  +vehículos), caja (apertura/cierre con arqueo), cobranzas,
  certificados, deficiencias (+autorización), facturación SUNAT
  (PDF/XML/CDR/reenvío), notas de crédito, órdenes de servicio,
  comunicación, alertas, escaneo de series, búsqueda de inventario por
  serie. No hay ninguna función del flujo comercial sin ruta/controller.

## 80.2 Bug confirmado — KPIs del Vendedor filtran mal (viola la regla que el propio Dashboard sí respeta)

`SaleController::index()` (líneas 41-45) calcula `ventas_del_mes` con
`Sale::whereBetween('fecha', [...])->sum('total')` y `comprobantes` con
`Sale::count()` — **sin filtrar por `vendedor_id`**: suman ventas de
TODA la empresa, no las del vendedor logueado. `BillingController::index()`
(líneas 51-56) tiene el mismo problema con `emitidos_hoy`/`aceptados_hoy`/
`observados`/`rechazados` sobre `ElectronicDocument` global. Contradice
directamente la regla que `DashboardController` sí implementa
correctamente en la misma capa de rol. Fix: agregar
`->where('vendedor_id', $user->id)` (o el join correspondiente vía
`sale.vendedor_id` en Billing) a ambos bloques de KPIs.

## 80.3 Bug confirmado — regla §78.1 (RUC Activo/Habido) capturada pero no aplicada

El dato se guarda (`RucLookupService`, `Client::estado_contribuyente`/
`condicion_domicilio`) pero **no hay ningún bloqueo real** antes de
emitir: ni `StoreSaleRequest::rules()`, ni `CreateSale::handle()`, ni
`EmitElectronicDocument::handle()` verifican esos campos. Un cliente
`INACTIVO`/`NO HABIDO` puede facturar (Factura o Boleta) sin obstáculo,
contra la regla dura ya decidida en §78.1. Fix: agregar la validación
en `EmitElectronicDocument::handle()` (antes de `reserveNextCorrelativo`)
o como regla custom en `StoreSaleRequest` cuando `comprobante_tipo`
sea factura/boleta y el cliente tenga `tipo_documento = RUC`.

## 80.4 CRÍTICO — Greenter está instalado pero NO integrado de verdad: nunca se construye ni se firma un comprobante real

Verificado con `composer show`: `greenter/core`, `greenter/lite`,
`greenter/ws`, `greenter/xml`, `greenter/xmldsig` (v4.3.x) SÍ están
instalados. `GreenterSunatClient::send()` SÍ instancia `Greenter\See`
correctamente (certificado, Clave SOL) y llama a `$see->sendXml(...)`.
Pero el flujo completo tiene un hueco central, admitido en el propio
código:

- `GreenterService::build()` arma un **array PHP plano** a partir del
  `Sale`, no un objeto `Greenter\Model\Sale\Invoice` (ni `Note` para
  notas de crédito/débito, ni `Despatch` para GRE). El propio docblock
  de la clase lo dice: _"Esta capa no firma ni envía XML: solo
  transforma el Sale a datos de facturación para que luego puedan
  convertirse a objetos Greenter cuando exista certificado."_
- `GreenterService::signNormalizedPayload()` no firma nada: hace
  `json_encode(['document_name' => ..., 'payload' => $payload])` y
  llama a ese resultado "xmlSigned".
- `GreenterSunatClient::send(string $xmlSigned, ...)` recibe ese JSON y
  lo pasa tal cual a `$see->sendXml('invoice', $documentName, $xmlSigned)`
  — SUNAT recibiría un JSON etiquetado como XML firmado: **fallaría en
  producción real**, esto solo "funciona" hoy porque nunca se ejecuta
  contra SUNAT real.
- Los 445 tests que pasan (§76.5) nunca ejercitan este camino: cada
  test que toca facturación enlaza un fake de `SunatClientInterface`
  (`tests/Feature/BillingModuleTest.php` línea 51) que devuelve
  `codigo: 0, mensaje: 'Aceptado'` sin tocar Greenter real. Por eso el
  hueco no se detecta en CI.

**Qué falta realmente** (no es investigación nueva — `docs/FACTURACION_GREENTER_SUNAT.md`
ya documenta el diseño correcto en detalle, secciones 4-12; falta
ejecutarlo):

1. Un builder (`SaleDocumentBuilder` o similar, mencionado como
   objetivo en §76.4 pero no creado aún) que convierta el payload de
   `GreenterService::build()` en un objeto real
   `Greenter\Model\Sale\Invoice` (o `Note`/`Despatch` según tipo), con
   `Company`, `Client`, `SaleDetail[]`, `Legend[]`, `FormaPagos`,
   `Charge`s/detracción — todo lo que `docs/FACTURACION_GREENTER_SUNAT.md`
   ya especifica.
2. Reemplazar `signNormalizedPayload()`/`sendXml()` por el flujo
   estándar de Greenter: `$see->send($invoice)` (que internamente
   construye XML, firma con `xmldsig` y envía), usando el objeto real,
   no una firma manual de string.
3. Mantener un test de integración real (no solo con fake) contra el
   ambiente BETA de SUNAT (`SUNAT_BETA=true`, credenciales de prueba)
   antes de dar por cerrada la Etapa 3 (Facturación SUNAT, §76.4).

Esto no bloquea la Etapa 1 (Inventario, en curso) pero sí es
información crítica para no asumir que "facturación ya funciona" — el
95% del trabajo de research/config ya está (series, detracción, notas
de crédito, GRE, RucLookup), pero el paso de construir+firmar el
comprobante real con objetos Greenter está sin hacer.

## 80.5 Nota — Etapa 1/2 de Product/Service sigue pendiente

`catalog_item_id`/`catalogItem` sigue presente en decenas de
referencias, incluida la propia `GreenterService` (usa
`$item->catalogItem->codigo`/`nombre`/`unidad_medida`). Confirma que la
Etapa 2 (§76.4: reconexión de `GreenterService` a `Product`/`Service`)
sigue sin empezar — cuando se ejecute, `GreenterService` debe
actualizarse para leer de `Product`/`Service` en vez de `CatalogItem`.

---

# 81. CORRECCIÓN DEL ROL VENDEDOR — GREENTER REAL, RUC ACTIVO/HABIDO Y KPIs (2026-09-20)

A pedido explícito del usuario ("arregla corrige y alinea e integra que
todo ese rol esté funcionando todo"), se corrigieron los 3 hallazgos de
§80 más un cuarto encontrado durante la implementación. **Verificado
extremo a extremo contra el ambiente BETA real de SUNAT** (no solo con
tests): una Factura de prueba fue construida, firmada y enviada, y
SUNAT respondió con código 0 y observaciones reales de formato de
dirección — exactamente lo esperado con datos de empresa de prueba.
Detalle de cada corrección:

## 81.1 Integración Greenter real (cierra §80.4)

- `GreenterService::buildInvoice()` (antes `build()`) ahora construye
  un objeto real `Greenter\Model\Sale\Invoice` (Company, Client,
  SaleDetail[], FormaPago, Cuotas si es crédito, Detracción si aplica,
  Legends con monto en letras vía `NumberFormatter` con `SPELLOUT`) en
  vez de un array plano.
- `GreenterService::sign()` (antes `signNormalizedPayload()`, que solo
  hacía `json_encode`) ahora firma de verdad con `Greenter\See::getXmlSigned()`
  usando el certificado configurado.
- `EmitElectronicDocument` guarda `xml_path` (antes nunca se llenaba)
  y usa `$invoice->getName()` (formato `RUC-tipoDoc-Serie-Correlativo`)
  como nombre de documento — el código anterior armaba el nombre a mano
  como `"{serie}-{correlativo}"`, sin RUC ni tipoDoc.
- **Bug adicional encontrado en producción (no en tests) durante la
  verificación manual**: `GreenterSunatClient` llamaba a
  `$see->sendXml('invoice', ...)` con el alias corto `'invoice'`, pero
  `XmlBuilderResolver::findBuilderType()` de la versión instalada de
  Greenter (4.3.x) espera el FQCN completo (usa
  `substr(strrchr($docClass, '\\'), 1)` para extraer el nombre de
  clase) — con `'invoice'` (sin `\`) esto lanzaba
  `TypeError: substr(): Argument #1 ($string) must be of type string, false given`.
  Este bug ya existía antes de esta sesión (la ruta nunca se
  ejecutaba realmente porque el paso anterior devolvía JSON) y solo
  salió a la luz al hacer funcionar la firma real. Fix: usar
  `Greenter\Model\Sale\Invoice::class` en vez del string `'invoice'`.
- `ResponseClassifier::classify()` ahora distingue código 0 con notas
  de observación (`observado`) de código 0 sin notas (`aceptado`),
  igual que la clasificación oficial SUNAT documentada en
  `docs/FACTURACION_GREENTER_SUNAT.md` §9.
- Notas de crédito/débito siguen sin poder emitirse (no se puede
  construir un `Note` correcto porque `electronic_documents` no
  guarda su desglose de montos) — `buildInvoice()` lanza una excepción
  clara en vez de fabricar datos. `IssueCreditNote` sigue sin llamar a
  `EmitElectronicDocument`; sigue pendiente como trabajo futuro que
  requiere una migración nueva (columnas de montos en
  `electronic_documents` o una tabla de líneas de la nota).
- Certificado de prueba (`tests/Fixtures/certificates/test-certificate.pem`,
  autofirmado, generado con OpenSSL solo para firmar en tests/local, no
  sirve para producción) — necesario porque el certificado real de
  SUNAT no existe en el repo ni en `.env`.

## 81.2 Regla RUC Activo/Habido aplicada de verdad (cierra §80.3, ejecuta §78.1)

Se creó `App\Actions\Sales\ConfirmSale` y la ruta
`POST vendedor/ventas/{sale}/confirmar` (antes no existía ningún punto
de entrada que pasara una venta de `borrador` a `confirmada`, ni que
disparara la emisión — `EmitElectronicDocument::handle(Sale)` nunca se
llamaba desde ningún controlador). Ahora: confirmar bloquea con
`ValidationException` si el cliente tiene RUC (no aplica a Boleta con
DNI) y su `estado_contribuyente`/`condicion_domicilio` no son
`ACTIVO`/`HABIDO`; si pasa, cambia el estado y emite el comprobante
electrónico en la misma transacción (rollback completo si SUNAT
falla). Botón "Confirmar y emitir" agregado en
`resources/js/pages/vendedor/ventas/show.tsx`.

## 81.3 KPIs del Vendedor acotados a sus propias ventas (cierra §80.2)

`SaleController::index()` y `BillingController::index()` ahora filtran
por `vendedor_id`/`sale.vendedor_id` en vez de sumar la empresa
completa, igual que ya hacía `DashboardController`.

## 81.4 Verificación

- 165 tests (578 assertions) en verde, incluidos 3 tests nuevos de
  `SaleConfirmationTest` y 3 nuevos/reescritos en `BillingModuleTest`
  que verifican XML real (no JSON), nombre de documento con formato
  SUNAT, y clasificación `observado` vs `aceptado`.
- Verificación manual en navegador contra el ambiente BETA real de
  SUNAT: venta creada → RUC verificado localmente (sin llamar a
  APIsPeru, ya existía) → confirmada → Factura F001-1 construida,
  firmada y **aceptada por SUNAT con observaciones reales de formato**
  (esperado: `BILLING_COMPANY_UBIGEO`/`DEPARTAMENTO`/etc siguen vacíos
  en `.env`, son datos reales de la empresa pendientes de configurar
  antes de producción, no un bug). `vendor/bin/pint` y
  `npm run types:check` limpios. Datos de prueba y cambios temporales
  de `.env` revertidos tras la verificación.
- Pendiente real para producción (no de código): completar
  `SUNAT_RUC`/`USUARIO_SOL`/`CLAVE_SOL`/`CERT_PATH` y
  `BILLING_COMPANY_*` con los datos reales de BRUCE FIRE.

---

# 82. VERIFICACIÓN LEGAL — QUÉ DEBE LLEVAR UNA FACTURA/BOLETA PARA NO ARRIESGAR MULTA (2026-09-21)

A pedido explícito del usuario ("investiga qué debe tener una boleta y
factura para que no nos multen"), se auditó el PDF generado contra el
**Reglamento de Comprobantes de Pago** (Art. 8, 9 y 10 — fuente oficial:
[sunat.gob.pe/legislacion/comprob/regla/capituloIII.pdf](https://www.sunat.gob.pe/legislacion/comprob/regla/capituloIII.pdf))
y [orientacion.sunat.gob.pe](https://orientacion.sunat.gob.pe/03-boleta-de-venta),
numeral por numeral.

## 82.1 Ya cumplido (verificado campo por campo)

Factura (Art. 8 num. 1) y Boleta (num. 3): razón social/nombre comercial
emisor, dirección fiscal, RUC, denominación del comprobante,
serie-correlativo, datos del adquirente (nombre + RUC/DNI), descripción
del bien/servicio con cantidad y unidad de medida, precios unitarios,
valor de venta sin tributos, monto discriminado de IGV con la tasa,
importe total numérico **y literal** (monto en letras), fecha de
emisión, signo de moneda (S/), y placa del vehículo cuando el servicio
es de mantenimiento/reparación para vehículos automotores (Art. 8 num.
1.18). Todo esto ya estaba o quedó cubierto por el trabajo de esta
sesión (§81).

## 82.2 Vacío real encontrado y corregido: leyenda y cuenta de detracción

El Art. 8 exige que, cuando una operación está sujeta al Sistema de
Pago de Obligaciones Tributarias (SPOT/detracción — servicios de
recarga/mantenimiento sobre S/700), el comprobante lo declare. Ya se
generaba correctamente en el XML enviado a SUNAT (legenda 2006,
`GreenterService`), pero **la representación impresa (PDF) no lo
mostraba** — un comprobante que se ve "limpio" en papel pero cuya
versión electrónica dice otra cosa es exactamente el tipo de
inconsistencia que genera observaciones/riesgo en una fiscalización.

Se corrigió:

- Nuevo campo `CompanySetting.cuenta_detraccion` (cuenta del Banco de
  la Nación), editable desde Gerente → Datos de la empresa.
- El PDF ahora muestra, cuando aplica: la leyenda "Operación sujeta al
  Sistema de Pago de Obligaciones Tributarias", el código de bien
  (Catálogo 54) y el monto de la detracción, más la cuenta de depósito
  si está configurada.
- Test añadido (`BillingModuleTest`) que verifica que la leyenda y la
  cuenta aparecen en el HTML renderizado cuando el servicio supera
  S/700.

## 82.3 Verificado y confirmado como decisión ya tomada, no un vacío

El Art. 8 (num. 1.9 y 3.7) pide indicar el número de serie del bien
vendido "si se trata de un bien identificable". El PDF actual **no**
desglosa el número de serie por línea cuando se venden varias unidades
iguales — pero esto ya es una decisión de negocio explícita y
documentada del usuario (§76.1, notas de voz 2026-09-19: "al vender 9
extintores iguales se agrupan como 'cantidad 9'... cada uno mantiene su
serie individual de forma interna, no se desglosa en la factura salvo
que el ID sea distinto"), y la propia norma lo permite ("si no fuera
posible indicar el número de serie... al momento de la emisión, dicha
información se consignará al momento de la entrega del bien"). No se
toca sin que el usuario lo pida explícitamente.

## 82.4 Nota de cumplimiento aparte de la factura misma

El Art. 8 num. 3.10 exige, además, que el RUC del cliente esté
verificado como Activo/Habido antes de facturar — esa regla es
independiente del contenido del PDF y **ya está implementada y
verificada** en `ConfirmSale` (§78.1, §80.3/81.2), bloqueando la
emisión antes de siquiera generar el comprobante.

## 82.5 Contadores del sidebar del Vendedor eran placeholders fijos (corregido)

El usuario detectó que el badge "Clientes 1,240" del menú lateral no se
movía. Confirmado: `resources/js/components/vendedor-sidebar.tsx` tenía
`count: '1,240'`, `'7'`, `'4'`, `'3'` como strings literales para
Clientes/Cotizaciones/Alertas/Deficiencias — nunca estuvieron
conectados a datos reales, eran diseño de referencia sin cablear.

Se corrigió agregando un prop compartido `sidebarCounts` en
`HandleInertiaRequests::share()`, calculado solo cuando el usuario
tiene el rol Vendedor (evita consultas innecesarias en páginas de otros
roles), igual de lazy que `currentTeam`/`teams` ya existentes:

- `clientes`: `Client::where('activo', true)->count()` (global, no hay
  noción de "cliente asignado a un vendedor" en el modelo).
- `cotizaciones`: cotizaciones en estado `enviada` del vendedor
  autenticado (`vendedor_id`), mismo patrón que ya usa
  `DashboardController`.
- `alertas`: equipos con `proxima_fecha_atencion` o
  `proxima_prueba_hidrostatica` dentro de los próximos 7 días —
  aproximación liviana (conteo directo en BD) del mismo criterio que ya
  usa `AlertController` para el segmento "vencidas"+"esta_semana", sin
  duplicar su agrupación completa en PHP.
- `deficiencias`: `Deficiency::where('estado', 'esperando_autorizacion')`
  — las que están pendientes de que el Vendedor las autorice.

Verificado en navegador: con 5 clientes activos sembrados, el sidebar
mostró "5", no "1,240". Test de regresión en
`tests/Feature/SidebarCountsTest.php` (incluye caso de que el prop sea
`null` para un rol que no es Vendedor).

## 82.6 Certificados: confirmado que falta generación de PDF y verificación pública

El usuario preguntó si el módulo de Certificados también está
incompleto. Confirmado con auditoría de código (no se tocó en esta
sesión, queda documentado para la próxima):

- `CertificateController` solo tiene `index()`/`show()` — no hay
  `store()`/`create()`, no se generan certificados desde el panel
  Vendedor.
- El modelo `Certificate` ya tiene `qr_token` (campo listo), y
  `PublicCertificateVerificationController::show()` ya expone un
  endpoint público por `qr_token` — pero **devuelve JSON, no una
  página HTML de verificación** que un cliente pueda abrir escaneando
  el QR.
- **No existe generación de PDF del certificado** (búsqueda de
  `pdf|dompdf|barryvdh` en `app/` solo encuentra resultados en
  `Billing`, nada en `Certificados`).
- No hay ninguna clase en `app/Actions/Certificados/*` — el motor de
  reglas de certificados (§26.1, plantilla dinámica por tipo) sigue
  sin implementarse.

Es un vacío real y del mismo tamaño que la Guía de Remisión (§77.1
corregido, ver §80): requiere su propio ciclo de trabajo (plantilla
Blade por tipo de certificado, reutilizando el `ComprobantePdfService`
como patrón, más la página pública de verificación). Queda como
siguiente fase pendiente de decidir con el usuario, no se construye
sin confirmación explícita de alcance.

---

# 83. PLAN — CIERRE DE VACÍOS DEL ROL VENDEDOR (planificado 2026-09-21, sin construir todavía)

Roadmap de los 4 vacíos confirmados en §82.5-82.6 para el rol Vendedor,
en el orden recomendado (de menor a mayor esfuerzo, cada uno cierra un
riesgo real):

## 83.1 Orden recomendado y por qué

1. **Notas de crédito/débito** (esfuerzo bajo-medio): ya existe todo el
   flujo (`IssueCreditNote`, `CreditNoteController`, pantalla) salvo
   guardar el desglose de montos y llamar a `EmitElectronicDocument`.
   Es completar algo que ya está casi ahí, no construir de cero.
2. **Cotización — PDF** (esfuerzo bajo): la Cotización no es un CPE
   SUNAT, así que no necesita Greenter ni QR tributario — solo
   reutilizar `ComprobantePdfService`/plantilla Blade con una variante
   simple ("COTIZACIÓN — Documento interno, no válido como comprobante
   de pago"). El más rápido de los cuatro.
3. **Certificados** (esfuerzo medio-alto): motor de reglas por tipo de
   servicio (§26.1) + plantilla PDF por tipo + página pública de
   verificación (HTML, no JSON) usando el `qr_token` que ya existe.
4. **Guía de Remisión** (esfuerzo alto): único que requiere construir
   un módulo completo desde cero — modelo, migración, integración
   Greenter (`Despatch`, API REST GRE con credenciales propias, ya
   documentado en `docs/FACTURACION_GREENTER_SUNAT.md` §8), controlador,
   pantallas, y solo al final el PDF.

## 83.2 Notas de crédito/débito — plan técnico

1. Migración: agregar a `electronic_documents` (o tabla nueva
   `electronic_document_items` para no ensuciar la tabla genérica) los
   campos de montos: `subtotal`, `igv`, `total`, y una tabla de líneas
   (`codigo`, `descripcion`, `cantidad`, `precio_unitario`, `subtotal`)
   — igual forma que `sale_items`, porque una nota no siempre repite
   exactamente los ítems de la venta (puede ser descuento parcial).
2. `IssueCreditNote::handle()` ya recibe `$importe` pero lo descarta —
   debe persistirlo, y agregar parámetro de líneas afectadas (con
   default = las mismas líneas de la venta original para el caso de
   anulación total, que es el caso más común según los PDFs de
   referencia que pasó el usuario).
3. `GreenterService`: nuevo método `buildNote()` (hoy `buildInvoice()`
   lanza excepción para nota_credito/nota_debito a propósito) usando
   `Greenter\Model\Sale\Note`, ya documentado en
   `docs/FACTURACION_GREENTER_SUNAT.md` §7 con motivos del Catálogo 09.
4. `IssueCreditNote` debe llamar a `EmitElectronicDocument::sendDocument()`
   al final, igual que ya hace `ConfirmSale` para Factura/Boleta.
5. Reusar `ComprobantePdfService`/plantilla (ya tiene el bloque
   condicional para `nota_credito`/`nota_debito` con "DOCUMENTO QUE
   MODIFICA", solo falta que le lleguen datos reales).

## 83.3 Cotización — PDF, plan técnico

1. Nueva vista Blade `resources/views/pdf/cotizacion.blade.php`
   (variante simplificada de `comprobante.blade.php`: sin QR
   tributario, sin cuentas de detracción, con leyenda "COTIZACIÓN —
   documento interno, no es comprobante de pago", con vigencia).
2. `ComprobantePdfService` o un servicio hermano
   `CotizacionPdfService` — reutilizar `NumeroEnLetrasService` y
   `CompanySetting`/`CompanyBankAccount` igual que comprobantes.
3. Ruta `GET vendedor/cotizaciones/{quote}/pdf` +
   `QuoteController::downloadPdf()`.

## 83.4 Certificados — plan técnico

1. Página pública real: nueva ruta `GET /verificar-certificado/{token}`
   con vista Blade (no Inertia) que muestre los datos del certificado
   y del equipo — hoy `PublicCertificateVerificationController::show()`
   solo devuelve JSON.
2. `CertificateController::store()`/`generate()`: falta el motor de
   reglas (§26.1) que decida qué tipo de certificado corresponde según
   servicio + destino (local/vehículo) + si hubo P.H./capacitación —
   ya especificado en el doc maestro, solo falta implementarlo.
3. Una plantilla Blade por `CertificateType` (Operatividad y Garantía,
   Prueba Hidrostática, Capacitación, Operatividad de Sistemas de
   Detección, y las "Otros configurables" — fumigación, desratización,
   pozo a tierra, cámaras, lámina de seguridad, etc. de §76.3), todas
   reutilizando el patrón de tabla dinámica de N filas (§26.2, no crear
   una plantilla por cantidad de equipos).
4. QR de verificación usando `ComprobanteQrGenerator` como patrón (el
   `qr_token` ya existe en el modelo `Certificate`).

## 83.5 Guía de Remisión — plan técnico (resumen; se detalla cuando se empiece)

1. Migración + modelo `ShippingGuide`/`ShippingGuideDetail` (motivo de
   traslado, transportista, vehículo, conductor, partida/llegada,
   `sale_id` nullable — todo ya especificado en el doc maestro §30-31 y
   verificado contra fuente oficial SUNAT en §77.1).
2. `GreenterService::buildDespatch()` usando `Greenter\Model\Despatch\Despatch`
   vía la API REST 2022+ de SUNAT (credenciales `SUNAT_GRE_CLIENT_ID`/
   `SUNAT_GRE_CLIENT_SECRET`, ya reservadas en `config/billing.php` sin
   usar todavía).
3. `ShippingGuideController` + rutas en `routes/vendedor.php`, pantalla
   de creación desde el detalle de una Venta.
4. PDF con el mismo patrón visual que ya mostró el usuario (referencia
   TCPDF de "Bruce Cars", ver nota de Obsidian del 2026-09-20).

---

# 84. PLAN — ROL ALMACÉN (planificado 2026-09-21, sin construir todavía)

**Estado actual: no existe absolutamente nada del rol Almacén en el
código** — ni rutas, ni controlador, ni pantalla. Solo existe el rol
vacío en `RolesAndPermissionsSeeder` (sin permisos asignados) y los
modelos de datos de base que el rol va a usar:
`CatalogItem` (código, nombre, tipo, unidad_medida, precio_venta,
aplica_igv, activo), `InventoryUnit` (unidad serializada: catalog_item,
sede_almacén, numero_serie, estado, fecha_ingreso) e
`InventoryMovement` (Kardex: tipo, cantidad, unidad, sede, usuario,
observación). Esto reduce el trabajo real: no hay que diseñar el
modelo de datos desde cero, solo construir la capa de rutas/
controladores/pantallas encima.

## 84.1 Alcance del rol (ya definido en el doc maestro §9-11, §35.3, §36)

Almacén tiene, según la especificación ya escrita:
catálogo (solo lectura), stock, recepciones, movimientos, unidades
serializadas, repuestos. **Regla dura repetida tres veces en el
documento** (§11.3, §35.3, §49.6): _"Almacén NO pistolea/escanea
equipos para asignarlos a una venta — eso lo hace el Vendedor."_ Es la
frontera de responsabilidad más importante a respetar al construir
este rol: Almacén controla existencias, nunca decide qué se vende.

## 84.2 Módulos propuestos (orden de construcción sugerido)

1. **Dashboard de Almacén** — KPIs propios (unidades en stock,
   productos bajo el mínimo, recepciones del día, movimientos
   recientes), siguiendo el mismo patrón ya usado en
   `Vendedor\DashboardController` (dashboard propio por rol, nunca
   compartido — regla ya aplicada al Vendedor en §77.3).

2. **Catálogo (solo lectura)** — listado de `CatalogItem` con stock
   actual por sede, sin poder crear/editar precios (eso es de Gerente/
   Comercial). Búsqueda por código/nombre/tipo.

3. **Stock y Kardex** — vista de `InventoryMovement` filtrable por
   producto/sede/fecha/tipo de movimiento (entrada/salida/ajuste/
   traslado), con el saldo corriente. Es el módulo central del rol.

4. **Recepción de proveedor** (§11.2) — formulario: proveedor,
   documento de referencia, fecha, producto, cantidad, cantidad
   conforme, cantidad observada, observación. Para extintores nuevos
   (unidades serializadas): captura serie, marca, capacidad, año por
   cada unidad del lote — crea `InventoryUnit` + `InventoryMovement`
   tipo "entrada" en una transacción.

5. **Impresión de stickers de código de barras** — ya especificado en
   §76.3 y §9: al confirmar una recepción de unidades serializadas,
   botón "Imprimir stickers" que arma una hoja A4 en grilla de 4
   etiquetas (código de barras BF-EQ-XXXXXX + código interno) por
   página. Reutiliza `barryvdh/laravel-dompdf` (ya instalado) — mismo
   patrón que se acaba de construir para comprobantes, pero con
   `endroid/qr-code`/barcode en vez de QR tributario.

6. **Ajustes de stock autorizados** — corrección manual de cantidad
   con motivo obligatorio (merma, error de conteo, etc.), siempre deja
   rastro en el Kardex (nunca se edita el stock directo, solo se
   inserta un `InventoryMovement` tipo "ajuste" con la diferencia,
   igual patrón que ya usa `SaleItemProcessor` para ventas).

7. **Repuestos/componentes** (§10.3) — manguera, válvula, manómetro,
   pasador, precinto, boquilla, difusor, manija, empaques, O-ring. Es
   el mismo modelo `CatalogItem`/`InventoryUnit` (o solo `CatalogItem`
   con stock no serializado si un repuesto no lleva número de serie
   propio — a decidir caso por caso, sin sobre-construir), no una
   tabla nueva.

8. **Disponibilidad/consulta rápida** — buscador por número de serie o
   código de barras que muestre estado actual de una unidad (mismo
   patrón que `InventoryLookupController::bySerial()` que el Vendedor
   ya usa para escanear en una venta — Almacén necesita el equivalente
   de solo-consulta, sin poder "vender" desde ahí).

## 84.3 DECISIÓN (2026-09-21): ejecutar Etapa 1 antes de construir Almacén — investigado con fuentes

El usuario pidió explícitamente no adivinar esto y "revisar bien que
tenga sentido" antes de decidir, porque Almacén necesita conectar de
verdad con Vendedor (Producto/Servicio), Certificados y los stickers
de escaneo — no ser una pantalla aislada. Se investigaron prácticas
estándar de gestión de inventario para negocios con productos
identificables/serializados + servicios + cumplimiento normativo
([NetSuite — Serialized Inventory Tracking](https://www.netsuite.com/portal/resource/articles/inventory-management/serialized-tracking.shtml),
[Unleashed — Barcoding and Inventory Management](https://www.unleashedsoftware.com/blog/barcoding-and-inventory-management-the-ultimate-guide/)).

**Hallazgo clave**: el patrón correcto para este tipo de negocio es un
único **maestro de ítems** (lo que aquí sería `Product`/`Service`) del
que cuelgan tres capas — stock/Kardex (`InventoryMovement`), unidades
serializadas con su barcode (`InventoryUnit`), y los documentos que
consumen ese maestro (venta, certificado, orden de servicio). **No es
correcto tener un maestro de ítems por rol** (un "Catálogo" propio de
Almacén separado de lo que usa Vendedor) — eso es exactamente lo que
ya se había decidido evitar en §76.2/§78.3 ("matar catálogo") y nunca
se ejecutó.

**Decisión**: se ejecuta la Etapa 1 (separar `CatalogItem` en
`Product`/`Service`, retirar `CatalogItem`) **como parte de construir
Almacén, no después**. Construir Almacén sobre `CatalogItem` ahora
significaría reconstruir sus pantallas de stock apenas se ejecute la
Etapa 1 (documentado como riesgo explícito en la versión anterior de
esta sección) — con la señal clara del usuario de que Almacén debe
conectar con Vendedor/Certificados desde el día uno, ya no tiene
sentido posponerlo. Efectos concretos en el plan:

- El **Módulo 2 — Catálogo (§84.6) se elimina como pantalla separada**
  de Almacén: no existe un "catálogo" que Almacén vea distinto al que
  ve Vendedor. La función de "ver qué productos/servicios existen y
  cuánto stock tienen" se fusiona dentro del Módulo 3 — Stock (§84.7),
  que pasa a ser la única pantalla de "qué hay y cuánto hay" para
  Almacén.
- `Product`/`Service` reemplazan a `CatalogItem` en todos los módulos
  de este plan (§84.7-§84.12) y en los permisos (§84.4: se elimina la
  clave `catalog`, no hace falta).
- `GreenterService`, `ComprobantePdfService` y el resto de lo
  construido para Vendedor (§80-82) deben actualizarse a `Product`/
  `Service` en la misma etapa — ya estaba anotado como pendiente
  (§80.5/§81), ahora tiene una fecha de ejecución real en vez de
  quedar indefinido.
- `InventoryUnit`/`InventoryMovement` (el Kardex) **no cambian de
  diseño** — solo su FK pasa de `catalog_item_id` a apuntar al ítem
  correcto (`product_id` para productos serializados/no serializados,
  los servicios no tienen stock por definición).
- Certificados (§82.6, todavía sin construir) y Alertas de Vencimiento
  (ya construido para Vendedor) también leen de `Equipment`/
  `CatalogItem` hoy — se actualizan en la misma pasada, no en una
  aparte, para no dejar puntos sueltos.

Esto convierte "construir Almacén" en dos entregables secuenciales
dentro del mismo esfuerzo: (1) ejecutar la migración Product/Service
que ya estaba decidida, (2) construir las pantallas de Almacén ya
directamente sobre el modelo correcto. Es más trabajo inicial que
construir sobre `CatalogItem`, pero evita reconstruir dos veces y es
lo que el usuario pidió explícitamente verificar antes de avanzar.

## 84.4 Permisos sugeridos (siguiendo la matriz ya definida en §36)

Requiere ampliar las acciones de `inventory` en
`RolesAndPermissionsSeeder::MODULES`. **Ya no hace falta una clave
`catalog`** (decisión §84.3: no hay pantalla de catálogo separada,
todo vive bajo `inventory.view`).

```text
MODULES:
  inventory  => ['view', 'manage', 'receive', 'adjust',
                 'print_stickers', 'lookup']      // ampliada

ALMACEN_PERMISSIONS:
  dashboard.view_own
  inventory.view
  inventory.receive
  inventory.adjust
  inventory.print_stickers
  inventory.lookup
  sedes.view
```

Ningún permiso de `sales.*` ni `sales.scan_units` — esa es la barrera
que hace cumplir la regla "Almacén no pistolea para vender". El
detalle de qué permiso cubre cada módulo está en §84.5-§84.12.

## 84.5 Módulo 1 — Dashboard de Almacén

- **Ruta:** `GET almacen/dashboard` → nombre `almacen.dashboard`.
- **Controlador:** `App\Http\Controllers\Almacen\DashboardController`
  (invokable, mismo patrón que `Vendedor\DashboardController` — KPIs
  propios del rol, nunca acumulados de otros roles).
- **KPIs:**
    - Unidades disponibles en stock (`InventoryUnit::where('estado',
'disponible')->count()`, agrupable por sede).
    - Recepciones de hoy (`InventoryMovement::where('tipo',
'ingreso')->whereDate('created_at', today())->count()`).
    - Movimientos recientes: últimos 10 `InventoryMovement` (con
      `catalogItem`, `sede`, `user`).
    - Productos bajo el mínimo: **aclaración importante (2026-09-21)** —
      este KPI es sobre **reabastecer el almacén** (ej. "quedan 2
      extintores PQS 6kg en stock, hay que comprar más al proveedor"),
      **no tiene nada que ver** con que un extintor instalado en casa de
      un cliente necesite cambio/recarga/prueba hidrostática — eso ya es
      otro módulo completamente distinto (Alertas de Vencimiento, ya
      construido para Vendedor en `Vendedor\AlertController`, basado en
      `Equipment.proxima_fecha_atencion`/`proxima_prueba_hidrostatica`,
      nada que ver con `InventoryUnit`/stock de almacén). Son dos
      conceptos con el mismo verbo ("vencer") pero completamente
      separados: uno es inventario propio de la empresa, el otro es
      mantenimiento de equipos de clientes.
- **Modelos:** `InventoryUnit`, `InventoryMovement` (solo lectura).
- **Permiso:** `dashboard.view_own` (reutiliza el permiso ya existente,
  no hace falta uno nuevo).

## 84.6 Módulo 2 — [ELIMINADO] Catálogo ya no es una pantalla separada

Decisión tomada en §84.3: no existe un "catálogo" propio de Almacén.
Ver contenido fusionado del listado de productos/servicios con stock
dentro del Módulo 3 — Stock (§84.7) a continuación.

## 84.7 Módulo 3 — Stock (incluye qué existe y cuánto hay) y Kardex (módulo central)

- **Rutas:**
    - `GET almacen/stock` → `almacen.stock.index` — listado de
      Producto/Servicio con stock actual por sede (reemplaza al antiguo
      "Catálogo" del §84.6: es la única pantalla de "qué hay y cuánto
      hay" para Almacén, no hay una segunda pantalla de solo-catálogo).
    - `GET almacen/kardex` → `almacen.kardex.index` — historial de
      movimientos.
- **Controladores:** `Almacen\StockController@index`,
  `Almacen\KardexController@index`.
- **Filtros de Stock:** `search` (código o nombre), `tipo`
  (`producto`|`servicio`).
- **Columnas de Stock:** código, nombre, tipo, unidad de medida,
  precio de venta (solo lectura — editar precio sigue siendo de
  Gerente/Comercial, no construido todavía), stock disponible por sede
  (para `tipo = servicio` se muestra "N/A", los servicios no tienen
  stock por definición). Sin botones de crear/editar/eliminar el ítem.
- **Filtros de Kardex:** `product_id`, `sede_id`, `fecha_desde`,
  `fecha_hasta`, `tipo` (`ingreso`|`salida_venta`|`ajuste`|`traslado`
  — estos son los valores reales del enum en la migración, no
  "entrada"/"salida" genéricos).
- **Columnas de Kardex:** fecha, tipo, producto, unidad serializada
  (si aplica), sede, cantidad (con signo), usuario, observación,
  saldo corriente (calculado, no columna).
- **Modelos:** `Product` (tras Etapa 1, ver §84.3), `InventoryUnit`,
  `InventoryMovement`, `Sede` — todo de solo lectura en este módulo.
- **Permiso:** `inventory.view` (reemplaza a `catalog.view`, que se
  elimina de §84.4).

## 84.8 Módulo 4 — Recepción de proveedor

> Nota de consistencia: esta subsección (§84.8) y las siguientes
> (§84.9) se escribieron antes de la decisión de §84.3 y todavía
> nombran `catalog_items`/`CatalogItem` en el detalle técnico fino.
> Leer como `products`/`Product` en cada mención — el diseño
> conceptual no cambia, solo el nombre de la tabla/modelo tras
> ejecutar la Etapa 1. No se reescribió campo por campo para no
> introducir errores de edición en un documento de planificación.

Este es el módulo con más superficie nueva porque el modelo actual
(`CatalogItem`/`InventoryUnit`/`InventoryMovement`) no tiene forma de
agrupar varias líneas bajo un mismo documento de proveedor. Se
necesita una tabla nueva, pequeña, que actúa como cabecera y aprovecha
la referencia polimórfica que `InventoryMovement.referencia_type/id`
ya tiene:

```text
receptions (tabla nueva)
  id
  proveedor            string, required
  documento_referencia string, nullable   (guía/factura del proveedor)
  fecha                date, required
  sede_almacen_id       FK sedes, required
  user_id              FK users            (quién recibió)
  observacion          string, nullable
  timestamps
```

Cada línea de la recepción genera **un `InventoryMovement` tipo
`ingreso`** con `referencia_type = Reception::class` y
`referencia_id = $reception->id`:

- Línea de producto **no serializado** (repuestos, insumos): un solo
  `InventoryMovement` con `cantidad = N`, `inventory_unit_id = null`.
- Línea de producto **serializado** (extintores nuevos): se crean `N`
  filas de `InventoryUnit` (una por unidad física) + `N`
  `InventoryMovement` (uno por unidad, `cantidad = 1`,
  `inventory_unit_id` apuntando a cada unidad). Todo en una única
  transacción de BD (`DB::transaction`), igual patrón que
  `SaleItemProcessor` usa para ventas.

**Campo nuevo necesario:** `catalog_items.serializado` (boolean,
default `false`) — hoy no existe forma de saber si un `CatalogItem`
requiere captura de unidades individuales al recibir stock. Sin este
campo, la pantalla de Recepción no puede decidir qué sub-formulario
mostrar por línea.

**Campos nuevos necesarios en `inventory_units`:** `marca` (string,
nullable) y `anio_fabricacion` (smallint, nullable). La capacidad y el
tipo de extintor (PQS, CO2, etc.) **no** necesitan columna nueva:
según la convención ya usada en el catálogo, cada combinación
capacidad+tipo es su propio `CatalogItem` (ej. "Extintor PQS 6kg" y
"Extintor CO2 5kg" son dos códigos distintos), así que capacidad/tipo
ya quedan implícitos en `catalog_item_id`. Solo marca y año de
fabricación varían por lote/unidad y necesitan vivir en
`InventoryUnit`.

- **Ruta índice:** `GET almacen/recepciones` → `almacen.recepciones.index`.
- **Ruta formulario:** `GET almacen/recepciones/nueva` →
  `almacen.recepciones.create`.
- **Ruta guardar:** `POST almacen/recepciones` →
  `almacen.recepciones.store`.
- **Ruta detalle:** `GET almacen/recepciones/{reception}` →
  `almacen.recepciones.show`.
- **Controlador:** `Almacen\ReceptionController`.
- **Campos del formulario:**
    - `proveedor`: string, required, max:150.
    - `documento_referencia`: string, nullable, max:50.
    - `fecha`: date, required, `before_or_equal:today`.
    - `sede_almacen_id`: select, required, `exists:sedes,id` (solo
      sedes con `tipo` en `almacen`/`mixta`).
    - `items`: array, required, min 1 elemento. Cada item:
        - `catalog_item_id`: required, `exists:catalog_items,id`.
        - `cantidad`: integer, required, min:1.
        - `cantidad_conforme`: integer, required, min:0,
          `lte:cantidad`.
        - `observacion_item`: string, nullable, **required si
          `cantidad_conforme < cantidad`** (obliga a explicar por qué,
          para poder reclamarle al proveedor después).
        - Si `catalog_item.serializado === true`, además un array
          `unidades` de tamaño `cantidad_conforme`, cada una con:
          `marca` (string, required) y `anio_fabricacion` (integer,
          required, entre 1990 y el año actual). El `numero_serie` **no**
          lo captura el usuario — lo genera el backend (ver abajo).
- **Generación del código interno — DECIDIDO 2026-09-21:** en el
  momento de crear cada `InventoryUnit`, el backend genera
  `numero_serie` con el formato `BF-EQ-{secuencial autoincremental de
6 dígitos con ceros a la izquierda}` (ej. `BF-EQ-000123`),
  garantizado único y correlativo por un `autoincrement`/secuencia de
  BD, nunca por conteo de filas (para no repetir número si se borra
  una unidad). Confirmado con el usuario, sin cambios de formato.
- **Mercadería no conforme/dañada — DECIDIDO 2026-09-21, con
  investigación**: se preguntó explícitamente qué hacen las empresas
  reales antes de decidir. Práctica estándar de recepción de almacén
  ([Racklify — Quarantine Workflow](https://racklify.com/encyclopedia/from-inspection-to-disposition/),
  [FastTQM — Incoming Inspection Best Practices](https://www.fasttqmsoftware.com/learn/incoming-inspection-best-practices)):
  lo no conforme **se separa y se registra siempre** (nunca se
  descarta sin dejar rastro) para poder reclamarle al proveedor dentro
  de la ventana de disputa, pero **nunca se mezcla con el stock
  disponible para la venta**. Aplicado a este sistema, sin construir
  una zona de cuarentena completa (fuera de alcance, nadie la pidió):
    - La cantidad no conforme de cada línea queda registrada en la
      propia tabla `receptions`/línea (`cantidad` vs `cantidad_conforme`
        - `observacion_item` obligatoria) — es el respaldo para el
          reclamo al proveedor.
    - **Solo `cantidad_conforme` genera `InventoryUnit`/`InventoryMovement`**
      (entra al stock real). La diferencia (`cantidad - cantidad_conforme`)
      NO crea unidades ni movimiento — no ensucia el Kardex con algo que
      nunca estuvo disponible para vender, pero el hecho no se pierde
      porque vive en el documento de recepción.
- **Recepción confirmada: editable — DECIDIDO 2026-09-21.** A
  diferencia de una Venta (que se vuelve inmutable al confirmarse), una
  Recepción sí se puede corregir después (ej. error de tipeo en
  cantidad conforme). Implica: `ReceptionController@update`, y que
  cualquier corrección que cambie `cantidad_conforme` debe ajustar
  también los `InventoryMovement`/`InventoryUnit` ya creados (crear un
  movimiento de ajuste compensatorio, nunca editar un movimiento
  histórico ya guardado — mismo principio ya aplicado en
  facturación: "nunca editar algo que ya corrió", §76.5).
- **Modelos:** `Reception` (nuevo), `Product` (tras Etapa 1, §84.3),
  `InventoryUnit`, `InventoryMovement`.
- **Permiso:** `inventory.receive`.

## 84.9 Módulo 5 — Impresión de stickers de código de barras

- **Ruta:** `GET almacen/recepciones/{reception}/stickers` →
  `almacen.recepciones.stickers`, devuelve el PDF inline (como
  `BillingController::downloadPdf`).
- **Controlador:** `Almacen\ReceptionStickerController@show`.
- **Servicio nuevo:** `App\Services\Inventory\StickerPdfService`,
  mismo patrón que `ComprobantePdfService`: recibe la `Reception`,
  carga sus `InventoryUnit` (vía los `InventoryMovement` asociados),
  genera el PDF con `Pdf::loadView('pdf.stickers', [...])
->setPaper('a4')` y lo guarda/streamea.
- **Vista nueva:** `resources/views/pdf/stickers.blade.php`.
- **Layout de la hoja A4:** grilla 2×2 (4 etiquetas por hoja),
  cada etiqueta ~9.5cm × 6cm con margen entre celdas para no
  desperdiciar hoja. Contenido de cada etiqueta, de arriba a abajo:
    1. Logo BF pequeño (esquina superior, opcional, mismo
       `logoBase64()` que ya usa `ComprobantePdfService`).
    2. Código de barras 1D (Code128) del `numero_serie`.
    3. `numero_serie` en texto legible debajo del barcode (por si el
       lector falla).
    4. Nombre del `CatalogItem` (truncado a 1-2 líneas).
    5. Marca + año de fabricación, en fuente pequeña.
    - Si la recepción tiene más de 4 unidades, se repite la grilla en
      páginas siguientes (dompdf pagina automático con `page-break`).
- **Librería de barcode — dependencia nueva requerida:**
  `endroid/qr-code` (ya instalado) **solo genera códigos QR**, no
  sirve para Code128 1D. `bacon/bacon-qr-code` tampoco genera 1D
  (también es QR). No hay ninguna librería de barcode 1D instalada
  hoy. Se necesita agregar `picqer/php-barcode-generator` (MIT,
  genera PNG/SVG/HTML de Code128 sin depender de extensiones GD
  raras) — **esto es un cambio de dependencias y requiere aprobación
  explícita del usuario antes de instalarlo**, según la regla del
  proyecto de no tocar dependencias sin aprobación.
- **Modelos:** `Reception`, `InventoryUnit`, `CatalogItem` (solo
  lectura).
- **Permiso:** `inventory.print_stickers`.

## 84.10 Módulo 6 — Ajustes de stock autorizados

- **Rutas:**
    - `GET almacen/ajustes` → `almacen.ajustes.index`.
    - `POST almacen/ajustes` → `almacen.ajustes.store`.
- **Controlador:** `Almacen\StockAdjustmentController`.
- **Campos del formulario:**
    - `product_id`: required, `exists:products,id` (tras Etapa 1, §84.3).
    - `inventory_unit_id`: nullable, `exists:inventory_units,id` — solo
      cuando el ajuste es sobre una unidad serializada puntual (ej. dar
      de baja una unidad dañada).
    - `sede_id`: required, `exists:sedes,id`.
    - `tipo_ajuste`: required, `in:incremento,decremento`.
    - `cantidad`: integer, required, min:1.
    - `motivo`: string, required, min:10 — **obligatorio siempre**, es
      la regla explícita del doc maestro (§84.2 punto 6).
    - `observacion`: string, nullable.
- **Backend:** crea un `InventoryMovement` tipo `ajuste` con
  `cantidad` firmada según `tipo_ajuste` (positiva si incremento,
  negativa si decremento) y `observacion = motivo`. **Nunca** se
  edita el stock directamente — el saldo siempre se deriva de la suma
  de movimientos en el Kardex. Si `inventory_unit_id` viene informado
  y el ajuste es un decremento total de esa unidad, además se
  actualiza `InventoryUnit.estado = 'baja'`.
- **Modelos:** `InventoryMovement`, `InventoryUnit`, `Product`.
- **Permiso:** `inventory.adjust`.
- **DECIDIDO 2026-09-21: aplicación directa, sin doble aprobación.**
  El Almacenero aplica el ajuste directo (queda igual de trazable
  porque `motivo` es obligatorio y el movimiento queda en el Kardex
  con su usuario — la auditoría es el propio historial, no una
  aprobación previa). No se construye ningún flujo tipo
  `DeficiencyAuthorizationController` para esto.

## 84.11 Módulo 7 — Repuestos/componentes

No requiere tabla nueva ni controlador nuevo: reutiliza exactamente
`Almacen\StockController` (§84.7) y `Almacen\ReceptionController`
(§84.8) que ya operan sobre `Product`/`InventoryUnit`. La mayoría de
repuestos (manguera, válvula, manómetro, pasador, precinto, boquilla,
difusor, manija, empaques, O-ring) **no** llevan serie propia, así que
en la Recepción usan la rama "no serializado"
(`products.serializado = false`) del mismo formulario.

- **`categoria` — DECIDIDO 2026-09-21 (sin respuesta explícita del
  usuario, se aplica la convención ya establecida del proyecto de no
  sobre-construir)**: no se agrega la columna `categoria` por ahora.
  El código/nombre ya alcanza para diferenciar un repuesto de un
  extintor en el listado (filtro de texto libre sobre `codigo`/
  `nombre` ya cubre el caso). Si en el uso real hace falta agrupar por
  categoría, se agrega después con evidencia real de la necesidad, no
  por anticipación.
- **Permiso:** ninguno nuevo — usa `inventory.view` e
  `inventory.receive` ya definidos.

## 84.12 Módulo 8 — Consulta rápida por serie/código de barras

- **Ruta:** `GET almacen/consulta` → `almacen.consulta.index` (pantalla
  de búsqueda).
- **Ruta de resolución:** `GET almacen/consulta/buscar` →
  `almacen.consulta.buscar` (JSON, tipo autocompletar al escanear).
- **Controlador:** `Almacen\StockLookupController` (nuevo, **no**
  reutiliza `Vendedor\InventoryLookupController` directamente porque
  ese está pensado para venta: filtra `estaDisponible()` y exige
  `sede_almacen_id`). El de Almacén:
    - Busca por `numero_serie` exacto, sin exigir `sede_almacen_id`
      (opcional como filtro).
    - **No filtra por `estaDisponible()`** — Almacén debe poder
      consultar también unidades `vendido`/`baja`/`reservado` para dar
      soporte o auditar.
    - Devuelve: `numero_serie`, `product` (nombre, código),
      `sede_almacen`, `estado`, `fecha_ingreso`, `marca`,
      `anio_fabricacion`, y los últimos 5 `InventoryMovement` de esa
      unidad (historial).
    - **Regla dura:** esta pantalla es 100% de solo lectura. No expone
      ninguna acción de "reservar", "vender" ni "agregar a venta" — esa
      es exactamente la frontera de §11.3/§35.3/§49.6 que Almacén no
      puede cruzar. No comparte controlador ni lógica de escritura con
      `SaleItemScanController`.
- **Modelos:** `InventoryUnit`, `InventoryMovement` (solo lectura).
- **Permiso:** `inventory.lookup`.

## 84.13 Resumen de cambios de esquema y dependencias nuevas (actualizado 2026-09-21)

| Cambio                                                 | Tipo                                                      | Estado                                                                 | Módulo                                                                    |
| ------------------------------------------------------ | --------------------------------------------------------- | ---------------------------------------------------------------------- | ------------------------------------------------------------------------- |
| Migración `Product`/`Service` (retirar `CatalogItem`)  | Etapa 1 ya decidida (§76.2), ahora con fecha de ejecución | **A ejecutar primero, ver §84.3**                                      | Todos                                                                     |
| Tabla `receptions`                                     | tabla nueva                                               | por construir                                                          | 84.8                                                                      |
| `products.serializado` (boolean)                       | columna nueva                                             | por construir                                                          | 84.8                                                                      |
| `inventory_units.marca` (string nullable)              | columna nueva                                             | por construir                                                          | 84.8, 84.9                                                                |
| `inventory_units.anio_fabricacion` (smallint nullable) | columna nueva                                             | por construir                                                          | 84.8, 84.9                                                                |
| `products.stock_minimo` (integer nullable)             | columna nueva                                             | **aclarado qué es (84.5), falta que el usuario confirme si se agrega** | 84.5 (KPI "bajo mínimo" — reabastecer almacén, no vencimiento de equipos) |
| `products.categoria`                                   | —                                                         | **descartado por ahora** (§84.11), no sobre-construir                  | —                                                                         |
| `picqer/php-barcode-generator`                         | dependencia Composer nueva                                | **APROBADO por el usuario 2026-09-21**                                 | 84.9 (stickers)                                                           |
| Layout/sidebar `AlmacenLayout`/`AlmacenSidebar`        | frontend nuevo                                            | por construir                                                          | todos                                                                     |
| `routes/almacen.php`                                   | archivo nuevo                                             | por construir                                                          | todos                                                                     |

Todo lo demás (permisos, controladores, vistas Inertia) es capa nueva
sobre modelos ya existentes — ningún otro cambio de esquema es
necesario.

## 84.14 Preguntas — RESUELTAS 2026-09-21 (una sigue pendiente)

Todas las preguntas de la primera pasada del diseño quedaron
resueltas por el usuario, con investigación de por medio donde se le
pidió explícitamente:

1. ✅ **Secuencia de trabajo**: se ejecuta la Etapa 1 (`Product`/
   `Service`) como parte de construir Almacén, no después. Decisión
   con investigación de por medio, ver §84.3.
2. ✅ **Código interno**: `BF-EQ-000123`, correlativo por secuencia de
   BD. Confirmado sin cambios.
3. ✅ **Mercadería no conforme**: se registra en la línea de la
   recepción (nunca se pierde el dato, sirve para reclamo al
   proveedor) pero no entra al stock disponible — no se crea
   `InventoryUnit`/movimiento para la cantidad no conforme. Decisión
   con investigación de práctica real de almacenes, ver §84.8.
4. ✅ **Recepción confirmada**: editable (a diferencia de una venta).
5. ✅ **`picqer/php-barcode-generator`**: aprobado.
6. ✅ **Ajustes de stock**: aplicación directa, sin doble aprobación.
7. ✅ **Columna `categoria`**: no se agrega por ahora (sin
   sobre-construir); se reconsidera si aparece necesidad real de uso.

**Pendiente, la única que sigue abierta:**

8. **KPI "productos bajo el mínimo"**: ya se aclaró qué es (reabastecer
   almacén, nada que ver con vencimiento de extintores instalados en
   clientes — eso es Alertas de Vencimiento, módulo distinto ya
   construido para Vendedor). Falta que el usuario confirme si se
   agrega `products.stock_minimo` ahora (el KPI se muestra desde el
   día uno) o se pospone ese KPI puntual hasta que haga falta (el
   resto del dashboard de Almacén funciona igual sin él).

---

# 85. PLAN — ROLES TÉCNICO DE PLANTA Y TÉCNICO DE CAMPO (planificado 2026-09-21, sin construir todavía)

## 85.1 Por qué van juntos

Roadmap §58 Fase 4 ("Servicios") agrupa Planta y Campo porque ambos
ejecutan una única entidad ya existente en el backend: `ServiceOrder`
(máquina de 13 estados, §16.2), `Deficiency` y
`DeficiencyAuthorization`. El lado de **Vendedor ya está construido**:
crea la orden (`Vendedor\ServiceOrderController`), ve deficiencias y
las autoriza (`Vendedor\DeficiencyController`,
`DeficiencyAuthorizationController`). Falta el lado que **recibe,
ejecuta y cierra** esa orden — eso son estos dos roles. No hay
"catálogo propio" que decidir aquí (ya se resolvió esa clase de
problema en §84.3): ambos roles operan sobre `ServiceOrder`,
`Deficiency`, `Equipment` y `Product` ya existentes, nunca crean tablas
paralelas de esas entidades.

## 85.2 Regla dura (repetida en el doc, no negociable)

Mobile-first **obligatorio** para ambos roles (§70): la interfaz se
diseña primero para celular, nunca se "encoge" una pantalla de
escritorio. Cards, pasos, cámara accesible, escaneo rápido, selector
Conforme/Observado/N/A, sin tablas horizontales. El Técnico no negocia
precios ni emite CPE (§35.4, §35.5, §21).

## 85.3 Lo que ya existe (no reconstruir)

- `App\Models\ServiceOrder` con `ESTADOS` (13 pasos finos) y
  `coarseLabel()`.
- `App\Models\Deficiency` y `DeficiencyAuthorization`.
- `App\Models\ServiceOrderEvent` (bitácora append-only de eventos de
  la orden — es el mecanismo de "comunicación sin chat" de §17).
- `Vendedor\ServiceOrderController`, `DeficiencyController`,
  `DeficiencyAuthorizationController`, `CommunicationController`.
- `App\Models\Equipment` (equipos del cliente, para Alta Técnica
  Rápida §18).
- `App\Models\Certificate`/`CertificateType` y el servicio de PDF de
  certificados ya construido para Vendedor — hoy se dispara a mano,
  este plan lo conecta a `listo_certificado`.
- `App\Services\Inventory\*` y `Product`/`InventoryUnit`/
  `InventoryMovement` de Almacén — el consumo de repuestos en una
  reparación reutiliza esto, nunca una tabla de stock paralela.
- Patrón de PDF con dompdf (`ComprobantePdfService`,
  `StickerPdfService`) — el Acta de Conformidad (§23) reutiliza el
  mismo enfoque.

## 85.4 Lo que falta construir

- `routes/tecnico-planta.php`, `routes/tecnico-campo.php` +
  middleware `role:TecnicoPlanta` / `role:TecnicoCampo`.
- Permisos: expandir `service_orders` (`view,create,manage` hoy) y
  `deficiencies` (`view,create,authorize` hoy) a los verbos exactos
  de §36 (`assign`, `receive`, `execute`, `close`, `resolve`), más
  módulos nuevos según §85.6.
- Layouts/sidebars mobile-first para ambos roles (nunca reutilizar
  `AppLayout` genérico — recordar excluir `tecnico-planta/` y
  `tecnico-campo/` en `app.tsx`, el mismo bug que ya se corrigió para
  `almacen/`).
- Checklist digital dinámico (§19), Alta Técnica Rápida (§18),
  Recojo/Entrega con cadena de custodia (§22), Inspecciones (§24),
  Instalaciones (§25), Acta de Conformidad (§23).

## 85.5 Fases de ejecución (orden sugerido)

Detalle completo en el runbook de Obsidian
`Bruce Fire/2026-09-21 - Runbook Tecnico Planta y Campo.md` — aquí
solo el resumen:

0. Scaffolding de ambos roles (rutas, permisos, layouts/sidebars).
1. Dashboard Técnico de Planta (colas por estado, §5.4).
2. Recepción en Planta + Alta Técnica Rápida (§18).
3. Checklist Técnico Digital (§19).
4. Deficiencias desde Planta (§20) + notificación a Vendedor
   (reutiliza `CommunicationController`/`ServiceOrderEvent`).
5. Ejecución y cierre técnico: consumo de repuestos de Almacén,
   transición hasta `listo_certificado`, disparo automático del
   certificado.
6. Dashboard Técnico de Campo (servicios de hoy, §5.5).
7. Recojo con cadena de custodia (§22.1, §22.4).
8. Inspecciones de Campo (§24) — reutiliza el motor de checklist de
   la fase 3.
9. Instalaciones (§25) — equipos instalados pueden pasar a Equipos
   del Cliente.
10. Entrega final + Acta de Conformidad (§22.3, §23) — cierra la
    orden.

## 85.6 Preguntas abiertas (a resolver por el agente ejecutor solo si

bloquean una fase; si no, seguir con el valor por defecto indicado)

1. ¿Un técnico puede estar asignado a Planta y Campo a la vez, o son
   roles excluyentes por usuario? Por defecto: excluyentes (un
   usuario tiene un solo rol técnico), como ya aplica el patrón
   `role:X` de Vendedor/Almacén.
2. ¿El Acta de Conformidad requiere firma digital capturada
   (canvas táctil) o basta un checkbox de conformidad + nombre del
   receptor? Por defecto: checkbox + nombre, como ya se hizo con la
   conformidad de recepción en Almacén — firma digital se puede
   añadir después sin romper el esquema.
3. ¿La cadena de custodia (§22.4) es un modelo nuevo
   (`ServiceOrderCustody` o similar) o se modela como eventos
   tipados dentro de `ServiceOrderEvent` (`payload` json ya existe
   para eso)? Por defecto: reutilizar `ServiceOrderEvent` con `tipo`
   nuevo por cada eslabón (`recojo`, `recepcion_planta`,
   `entrega_final`) — evita una tabla nueva para algo que ya es,
   estructuralmente, una bitácora de eventos.

---

# 86. PLAN — ROL GERENTE (planificado 2026-09-21, sin construir todavía)

## 86.1 Por qué es distinto de los demás roles

Vendedor, Almacén, Técnico de Planta y Técnico de Campo **ejecutan**
operación (crean, reciben, resuelven). Gerente **no ejecuta nada
operativo** — es consulta amplia + configuración global (§35.1: "no
necesita necesariamente administrar credenciales técnicas SUNAT").
No hay una historia de "Gerente hace una venta"; hay una historia de
"Gerente necesita saber, sin pedírselo a nadie, si el mes va bien".
Por eso este plan no tiene una máquina de estados propia como
`ServiceOrder` — es, sobre todo, lectura agregada de datos que los
otros 4 roles ya generan, más un puñado de pantallas de administración
que hoy no tiene nadie (CRUD de Producto/Servicio, control de caja
consolidado, reportes, auditoría).

**Decisión ya tomada implícitamente por el propio proyecto**: el rol
"Administrador" del §35.6 (usuarios, roles, permisos, plantillas,
configuración, auditoría) **nunca se creó como rol Spatie separado**
— solo existen 5 roles reales:
`Vendedor, Almacen, Gerente, TecnicoPlanta, TecnicoCampo`. Y
Configuración de Empresa (que es territorio de "Administrador" según
el doc) ya se construyó bajo `routes/gerente.php`. Este plan mantiene
esa decisión: Gerente absorbe el alcance de Administrador, no se crea
un sexto rol. Si en el futuro hace falta separar (ej. un gerente
comercial sin acceso a usuarios/roles), la matriz de permisos
granulares (§36) ya permite hacerlo sin tocar la arquitectura.

## 86.2 Lo que ya existe (no reconstruir, no duplicar)

- `App\Models\CompanySetting`, `CompanyBankAccount` +
  `Gerente\CompanySettingController`, `CompanyBankAccountController`
  — única pantalla que Gerente tiene hoy.
- `App\Models\CashRegister` (turno de caja, patrón "arqueo ciego" ya
  implementado, §77.2) + `Vendedor\CashRegisterController` — Gerente
  solo necesita una vista de **consulta** sobre esto (historial de
  todos los vendedores con diferencias resaltadas, tal como pide
  §77.2 punto 4), nunca reconstruir el arqueo.
- Todos los datos fuente que Gerente va a agregar/reportar ya existen
  y ya se generan solos en los otros roles: `Sale`/`SalePayment`,
  `Quote`, `ServiceOrder`/`Deficiency`, `Certificate`,
  `ElectronicDocument` (estado SUNAT), `InventoryMovement`,
  `Product`/`Service` (incluye `stock_minimo`, agregado en la Etapa 1
  de Almacén pero nunca editable desde ninguna pantalla).
- Permisos ya reservados en `RolesAndPermissionsSeeder::MODULES` sin
  usar todavía: `dashboard.view_total` (vs. `view_own` que ya usan
  los demás roles — la distinción ya está pensada) y
  `roles_permissions.manage`.
- Patrón de gráficos/tarjetas de dashboard, patrón de exportación a
  PDF con dompdf (`ComprobantePdfService`, `StickerPdfService`,
  `ActaConformidadPdfService`) — los reportes exportables reutilizan
  este enfoque, no una librería nueva.

## 86.3 Lo que falta

- Dashboard real de Gerente (hoy `DashboardController` genérico solo
  redirige Vendedor/Almacén a los suyos — Gerente cae a una pantalla
  vacía del starter kit).
- CRUD de `Product`/`Service` — **el vacío más urgente**: hoy
  `stock_minimo` existe en la tabla pero nadie puede editarlo desde
  ninguna pantalla, lo mismo que precios (`precio_venta`). Sin esto,
  el KPI "bajo el mínimo" del dashboard de Almacén nunca mostrará
  nada en producción real.
- Reportes (§34): comerciales, inventario, servicios, equipos,
  certificados, facturación, cobranzas — con exportación a
  Excel/PDF "cuando aporte valor" (no todos necesitan Excel desde el
  día uno).
- Cobranzas consolidadas (§32): hoy Vendedor ya registra pagos
  (`collections.register_payment`), Gerente necesita la vista
  agregada de cartera (total por cobrar, vencido, vence esta semana,
  cobrado este mes) cruzando todos los vendedores, no solo el propio.
- Auditoría (§37): no existe ningún registro de acciones sensibles
  todavía en ningún rol. Este plan agrega la tabla y el visor para
  Gerente; conectar cada acción sensible de los otros 4 roles a este
  log es trabajo transversal, se hace incrementalmente (ver §86.6
  Fase 5), no de una sola vez.
- Gestión de usuarios/roles (asignar rol a un usuario, ver quién
  tiene qué permiso) — hoy solo existe por `php artisan tinker` o
  seeder.

**Fuera de alcance de este plan** (decisión, no olvido): las tarjetas
y gráficos de IA del §5.1 (proyección de ventas, demanda estimada,
riesgo de quiebre de stock, resumen ejecutivo). El propio doc maestro
lo dice en el §59: _"IA entra cuando la base de datos ya es
confiable"_ — y el roadmap (§58) pone IA en la Fase 8, la última. El
dashboard de Gerente se construye con las tarjetas y gráficos de
datos reales primero; el bloque de IA queda como sección vacía o
directamente omitido hasta que corresponda su fase.

## 86.4 Módulos (orden sugerido)

1. **Dashboard de Gerente** (§5.1, sin el bloque de IA) — tarjetas:
   ventas del día/mes, facturación del mes, monto cobrado, cuentas
   por cobrar, vencido, cotizaciones pendientes, tasa de conversión,
   órdenes en proceso, equipos próximos a atención, stock crítico,
   documentos SUNAT con error. Gráficos: ventas mensuales, ventas por
   producto/servicio, servicios por tipo, cartera por estado, top
   clientes, productos/repuestos con mayor movimiento.
2. **CRUD de Producto/Servicio** — crear/editar/desactivar (nunca
   eliminar si tiene historial, regla ya aplicada en Equipos §60),
   incluye `precio_venta` y `stock_minimo`. Esto es lo que
   desbloquea de verdad el KPI de Almacén.
3. **Caja consolidada** — vista de solo lectura sobre `CashRegister`
   de todos los vendedores, diferencias resaltadas (§77.2 punto 4).
4. **Cobranzas consolidadas** (§32) — cartera agregada de todos los
   vendedores, no solo la propia.
5. **Reportes** (§34) — empezar por comercial e inventario (los que
   ya tienen todo el dato fuente limpio), exportación PDF reutilizando
   dompdf; Excel solo donde aporte valor real (ej. reporte de ventas
   por periodo, no el dashboard).
6. **Auditoría** (§37) — tabla + visor. Conectar las acciones más
   sensibles primero (venta, ajuste de stock, autorización de
   adicional, cierre de orden, emisión/anulación de certificado,
   cambios de configuración) — no las 13 de la lista completa de una
   sola vez.
7. **Usuarios y roles** — listar usuarios del team, asignar/quitar
   rol, ver permisos efectivos. Sin crear un builder visual de
   permisos granulares — se editan por rol completo (los 5 roles ya
   fijos), no permiso por permiso por usuario (§36: "evitar permisos
   directos a usuarios salvo excepción justificada").

## 86.5 Preguntas abiertas (valor por defecto si no bloquean)

1. ¿El CRUD de Producto/Servicio lo usa _solo_ Gerente, o también
   Vendedor/Almacén pueden editar precios como se mencionó y quedó
   pendiente en la auditoría de Almacén (§84, "eso ya vemos cómo
   arreglar luego")? Por defecto: el CRUD (crear/desactivar producto)
   es exclusivo de Gerente; la edición de precio en el momento de una
   venta por Vendedor es un permiso granular aparte
   (`sales.override_price` o similar) que se decide cuando se
   retome ese pendiente — no bloquea construir el CRUD ahora.
2. ¿Reportes exportan a Excel desde la Fase 5, o se pospone Excel
   para después de tener PDF funcionando en los reportes principales?
   Por defecto: PDF primero (reutiliza dompdf sin dependencia nueva);
   Excel se agrega cuando un reporte concreto lo necesite de verdad
   (ej. `maatwebsite/excel` recién ahí, no antes — evita instalar una
   librería que después no se usa).
3. ¿Auditoría registra automáticamente vía Eloquent observers/events,
   o cada acción llama explícitamente a un `AuditLogger`? Por
   defecto: llamada explícita en el punto de la acción (mismo patrón
   que `ServiceOrderEvent::create()` ya usado en todo Planta/Campo),
   más predecible y más fácil de auditar el propio código que
   observers automáticos que capturan de más.

# 87. Un solo botón «Editar» para la venta (2026-10-03)

**Pedido del usuario:** "debería ser solo un botón para poder editar la
boleta, factura o nota de venta en general; no vamos a tener un botón por
cada cosa que queremos editar".

**Antes** había tres caminos distintos para corregir una venta:

| Botón                                    | Qué permitía                                             | Dónde                  |
| ---------------------------------------- | -------------------------------------------------------- | ---------------------- |
| Editar (borrador)                        | todo, con el formulario de venta                         | solo borradores        |
| Editar comprobante / Corregir y reemitir | solo factura↔boleta y cliente (modal)                    | por enviar o rechazado |
| Corregir productos o precios             | anulaba la venta y abría una copia (otro número interno) | por enviar             |

**Ahora** hay un único botón **Editar** (en el detalle y como lápiz en la
lista) que abre el mismo formulario de venta, ya lleno, y deja cambiar
todo: cliente, tipo de comprobante, productos/extintores, precios,
condición y medio de pago. Regla única en `Sale::sePuedeEditar()`:

- **Borrador** → se edita como siempre (puede guardarse o emitirse).
- **Nota de venta emitida** → se edita; conserva su número NV. Si pasa a
  factura/boleta, el número NV se libera y se programa el comprobante.
- **Factura/boleta por enviar** (SUNAT aún no la recibe) → se regenera
  con el **mismo número**; si cambia de tipo, el número vuelve a su serie
  y toma uno de la otra; conserva fecha y hora de envío programada.
- **Factura/boleta rechazada** → se emite una nueva con otro número y
  fecha de hoy (SUNAT no permite reutilizar el número).
- **Aceptada por SUNAT** → no se edita (regla §76.5: nunca editar algo
  que ya corrió); el lápiz de la lista lleva a la **nota de crédito**.
- **Anulada** → no se edita; se usa «Rehacer venta».

La venta conserva siempre su número interno (ya no se crea otra venta).
Al guardar: las unidades sacadas vuelven al stock y las nuevas salen
(Kardex limpio), el cobro al contado se recalcula manteniendo su fecha de
caja, los equipos pasan al cliente nuevo y los certificados se corrigen
(conservan su número con una revisión) o se anulan si ya no hay
extintores. No se puede editar si ya hay cuotas cobradas.

Código: `app/Actions/Sales/EditarVentaEmitida.php` (reemplaza a
`CorregirComprobante` y a la ruta `corregir-productos`),
`SaleController::edit/update`, `resources/js/pages/vendedor/ventas/`
(`show.tsx`, `index.tsx`, `nueva.tsx`).

# 88. Comprobante personalizable y con diseño de marca (2026-10-04)

**Pedido del usuario:** la factura/boleta impresa se veía plana (gris,
sin marca), el logo salía diminuto o no salía, "NIU" en vez de "UND",
campos vacíos como "Dirección: -" u "Obs:". Quiere que sea estética y
dinámica: **lo general se llena desde Gerente y lo particular sale de la
venta o cotización**.

**Lo general (Gerente → Configuración → Empresa → Diseño del
comprobante):** color de la marca (con sugerencias; el texto encima
pasa a blanco u oscuro según el contraste), página web, mensaje de
agradecimiento, condiciones de venta/garantía, leyenda de pie, cuentas
bancarias y logo. Botón **Vista previa de la factura** con datos de
ejemplo.

**Logo con mínimo y máximo:** se valida al subirlo (PNG/JPG, de 150×60 a
3000 px por lado, 2 MB). En el PDF se dibuja sin deformarse dentro de una
caja de hasta 190×80 px; si es muy alargado se le permite llegar a 230 px
de ancho para que no quede como una tira
(`CompanySetting::logoParaPdf()`).

**Lo particular (de la venta):** cliente, documento, dirección (solo si
existe), referencia/local/placa, condición y medio de pago, vencimiento,
vendedor que atendió, ítems, observaciones (solo si hay), cuotas,
detracción.

**Diseño:** cabecera con logo, nombre comercial y razón social; recuadro
tributario con el color de la marca; panel suave con cliente y datos de
la operación; tabla con encabezado de marca y filas alternadas; las
columnas Código y Dscto. solo aparecen si alguna línea las usa;
unidades legibles (NIU→UND, ZZ→SERV); cantidades sin decimales cuando
son enteras; monto en letras destacado; total resaltado; QR y leyenda
SUNAT al pie.

**PDFs ya emitidos:** al descargarlos (o en el ZIP masivo) se vuelven a
dibujar si la empresa o sus cuentas cambiaron después, usando el mismo
XML firmado (`ComprobantePdfService::vigente()`); lo enviado a SUNAT no
cambia.

Código: `resources/views/pdf/comprobante.blade.php`,
`app/Services/Billing/ComprobantePdfService.php`,
`app/Models/CompanySetting.php`, migración
`2026_10_04_010000_add_diseno_comprobante_to_company_settings_table`,
`resources/js/pages/gerente/configuracion/empresa.tsx`.

Pendiente: aplicar el mismo diseño a la nota de venta y a la
cotización; campos por venta como N° de orden de compra o de guía de
remisión si el negocio los usa.
