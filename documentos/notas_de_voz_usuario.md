# Notas de voz del usuario — transcripción cruda

Este archivo guarda tal cual las notas de voz que el usuario ha ido
dictando sobre cómo debe funcionar el sistema, para no perder contexto
entre sesiones ni tener que pedírselas de nuevo. Es material fuente sin
editar; la versión organizada, deduplicada y con fuentes está en
`BRUCE_FIRE_Documento_Maestro_v9.md` (secciones 76-77). Cuando el
usuario dicte algo nuevo, se agrega aquí como una entrada nueva y luego
se integra al documento maestro.

---

## Entrada 2026-09-19 (sesión de rediseño de inventario)

Roles: Vendedor (vende, factura, cotiza, ve estado de facturación y
cobranza), Almacenero (llena productos; los extintores no tienen
código de barras de fábrica así que el sistema genera uno propio al
recibir el lote, uno por unidad, guardando internamente capacidad,
serie, marca, tipo, año de fabricación; permite imprimir stickers
agrupados de a 4 por hoja para no desperdiciar una hoja por unidad).

Certificados: para extintores, según destino (local vs. vehículo/en
general) cambia el tipo — local: Operatividad y Garantía +
Capacitación; vehículo/general: Operatividad y Garantía + Prueba
Hidrostática. Además la empresa da otros servicios con su propio
certificado: fumigación, desratización, mantenimiento de sistema de
detección, instalación/mantenimiento de extinción, pozo a tierra,
desinfección, limpieza de pozos sépticos, instalación/mantenimiento de
cámaras, instalación de lámina de seguridad. Cada uno necesita su
propia plantilla de certificado (o ninguna si no aplica). Los
certificados deben autocompletarse con los datos que ya tiene el
sistema (cliente, extintor escaneado en la venta) para no volver a
digitar, y llevar QR de verificación contra suplantación.

Trazabilidad de venta: al vender 9 extintores iguales (mismo ID de
producto) se agrupan como "cantidad 9" en la venta/factura, pero cada
uno mantiene su serie individual de forma interna (no se desglosa en
la factura salvo que el ID sea distinto).

Gestión de clientes: tabla simple (razón social, RUC/DNI, dirección),
autocompletado por RUC/DNI vía API (sin volver a llamar si el cliente
ya existe localmente), botón agregar y exportar (Excel/PDF), búsqueda.
No permitir facturar a RUC no habido/inactivo (verificar con la
fuente).

Ventas: filtro por mes, selector de tipo de comprobante (factura,
boleta, nota de crédito/débito; la serie/correlativo solo se muestran,
no se editan), búsqueda dinámica de cliente con modal para agregar uno
nuevo (reutilizando el mismo formulario de Clientes), pregunta
obligatoria de si el destino es local o vehículo (habilita
sede/placa), condición de pago contado/crédito con cuotas, tabla de
productos con escaneo por código de barras. Envío de facturas: filtro
por mes/tipo/estado SUNAT, reenvío, descarga masiva (XML/CDR/PDF/
Excel), anulación o nota de crédito según si ya se envió a SUNAT.

Cobranzas: módulo aparte porque hay ventas a crédito; total por
cobrar, vencido, pagos registrados, aviso al cliente.

Inventario (la queja central): debe preguntar Producto o Servicio al
crear; producto lleva stock, servicio no. Unidad de medida debe
investigarse caso por caso (ej. cinta reflectiva se vende por metro,
no por unidad) y no complicarse. Precio ya incluye IGV, con
indicador de si aplica o no. Rechazo explícito a "llenar el stock con
un botón nomás" — tiene que ser con números.

Técnicos: de Planta (recargan extintores que llegan por el vendedor o
por el técnico de campo) y de Campo (instalan, inspeccionan, hacen
mantenimiento en sitio del cliente). Checklist por extintor: serie,
ubicación, agente, capacidad, manómetro, pasador, manguera, marca,
fabricación, tarjeta de inspección, próxima recarga, vencimiento de
prueba hidrostática, observación + foto. Debe generalizarse el mismo
patrón de checklist/informe a los demás servicios (sistema de
detección, pozos sépticos, cámaras, etc.) en vez de reinventar uno por
tipo. Debe tomarse foto de cómo se encontró y cómo se dejó el equipo.
Comunicación cliente-técnico de campo por eventos de la orden, no chat
interno. Todo el flujo técnico debe ser responsive/usable desde
celular.

Gerente/Admin: por ahora son el mismo rol (no existe un rol
Administrador separado todavía; se podría habilitar en el futuro).
Cada rol debe tener su propio dashboard con solo los datos que le
corresponden — el Vendedor ve su día (para su control de caja) pero
NO el acumulado mensual de la empresa, eso es solo de Gerencia.
Control de caja: el usuario no sabe cómo debe funcionar exactamente,
pidió investigar (ver documento maestro §77.2 — arqueo ciego).

Guía de Remisión: el usuario no sabe cómo se emite ni dónde debería
vivir en el sistema (intuye que va asociada a una venta, con
destinatario y transportista) — pidió investigar cómo lo hacen otras
facturadoras (ver documento maestro §77.1 — ya estaba bien construida
en el código).

Cotización: es un documento interno (no existe para SUNAT), se
"envuelve" en boleta o factura al convertirse, sin volver a
digitalizar los datos.

Arquitectura: pidió explícitamente evitar "harcodeo" y quejó que las
llamadas a las APIs de Greenter/RENIEC-DNI consumían el doble de lo
esperado por llamada — pidió no llamar a la API si el dato ya existe
localmente.

Instrucción general repetida varias veces: el usuario no quiere dar
todo el contexto de golpe para que Claude "arregle todo" — pide que
Claude tome la idea vaga, la mejore, decida cómo conectar todo, y lo
sorprenda, documentando en Markdown todo lo que investigue para no
perder el hilo entre sesiones.
