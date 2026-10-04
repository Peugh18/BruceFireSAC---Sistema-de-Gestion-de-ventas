# Manual del Vendedor — Bruce Fire

## Registrar un cliente

1. Ve a **Clientes** y pulsa **Agregar cliente**, o hazlo desde el buscador de Venta, Cotización u Orden de Servicio.
2. Elige RUC o DNI y escribe el número: la razón social, dirección y estado SUNAT se completan solos desde RENIEC/SUNAT.
3. Teléfono, email y nombre comercial son opcionales (SUNAT no los pide).
4. Pulsa **Guardar cliente**.

- La consulta a RENIEC/SUNAT solo se hace al registrar un cliente nuevo; buscar clientes ya registrados no la usa.

## Buscar un cliente

- En cualquier buscador de cliente escribe nombre, razón social, RUC o DNI: filtra letra por letra y las palabras pueden ir en cualquier orden ("jose urcia").
- Elige con clic o con las flechas y Enter. Verás una tarjeta con RUC/DNI, dirección y estado SUNAT.
- **Quitar** deselecciona el cliente; **Ficha** abre sus datos en otra pestaña.
- Si no existe, aparece **Agregar cliente**.

## Hacer una cotización

1. **Cotizaciones → Nueva cotización**.
2. Busca y elige el cliente.
3. Elige por cuántos días se respeta el precio: 7, 15 o 30 días (la fecha de emisión es hoy).
4. Marca si es para **Local** o **Vehículo** y, si quieres, la **Referencia** (placa o sede u oficina del cliente).
5. Busca productos o servicios por nombre, código o código de barras y agrégalos. Ajusta cantidad, precio o descuento (el descuento nunca deja una línea en S/ 0.00).
6. Los precios **ya incluyen IGV**.
7. Elige la condición de pago propuesta y pulsa **Guardar cotización**.
8. En la lista puedes **Enviar**, marcar **Aceptada** y luego **Pasar a venta**.

## Registrar una venta

1. **Ventas → Nueva venta** (o **Pasar a venta** desde una cotización aceptada).
2. Busca el cliente. Con DNI el comprobante pasa solo a **Boleta**: la factura solo se emite a RUC.
3. Para una boleta sin identificar usa **Clientes varios** (solo hasta S/ 700).
4. Elige el comprobante: Factura, Boleta o Nota de venta (interna, no va a SUNAT).
5. Elige el destino: **Local** o **Vehículo**, y la **Referencia** (placa o sede). Sale impresa en el comprobante.
6. La sede es la tuya: se toma sola.
7. En "Productos y servicios":
    - Escanea la **serie BF-EQ** del extintor o su código de barras, y se agrega solo.
    - O escribe el nombre: si el producto tiene serie, se abre una ventana para marcar las unidades.
    - Productos sin serie (bases, soportes) y servicios se agregan por cantidad.
8. Los precios incluyen IGV. Pulsa **Registrar venta** y luego **Confirmar y emitir**.

## Venta a crédito

1. En Condición de pago elige **Crédito**: se abre la ventana del crédito.
2. Pon el plazo en días (7, 15, 30, 45, 60 u otro) y el número de cuotas, y pulsa **Calcular**.
3. Puedes cambiar la fecha y el monto de cada cuota, agregar o quitar cuotas. La suma debe ser igual al total.
4. Pulsa **Guardar crédito**. Las cuotas salen en la factura y en **Cobranzas**.

## Editar una venta: un solo botón

- En la lista de ventas (lápiz) o en el detalle de la venta, el botón **Editar** abre el mismo formulario de la venta, ya lleno. Ahí cambias todo: cliente, factura/boleta/nota de venta, productos, extintores, precios, condición y medio de pago.
- Sirve para un **borrador**, una **nota de venta** y una factura o boleta que SUNAT **aún no recibe** o que **rechazó**. La venta conserva su número.
    - Por enviar: se vuelve a generar con el **mismo número** (si pasas de factura a boleta o al revés, toma el número de la otra serie).
    - Rechazada: sale un comprobante nuevo con otro número y la fecha de hoy.
    - Nota de venta: conserva su número NV.
- Al confirmar, la factura o boleta queda **por enviar** 6 horas y luego se envía sola a SUNAT. Mientras tanto también puedes **Enviar ya** o **Anular venta**.
- Una vez **aceptada** por SUNAT ya no se edita: se corrige con **nota de crédito** (el lápiz de la lista te lleva directo a ella).

## Certificados de una venta

1. Abre la venta confirmada y pulsa **Armar certificados**.
2. Por regla: **Local** = Operatividad y Garantía + Capacitación; **Vehículo** = Operatividad y Garantía + Prueba Hidrostática.
3. Si los extintores van a varios locales o vehículos, pulsa **Otro local o vehículo** y asigna cada extintor a su grupo.
4. Ordena con las flechas y escribe el **N° del cliente** de cada extintor (o usa **Numerar 01, 02…**) para que coincida con sus etiquetas.
5. Pulsa **Emitir certificados**. Cada certificado tiene un QR que verifica su autenticidad.
6. Si te equivocaste, **Volver a armar** reemplaza los anteriores.

## Órdenes de servicio (recarga, mantenimiento)

1. **Órdenes de Servicio → Nueva orden**.
2. Busca el cliente y el servicio (del catálogo que crea el Gerente).
3. Elige el área: **Planta** (taller) o **Campo** (en el local del cliente). El responsable es opcional: toda el área ve la orden.
4. Pon la referencia, la fecha, la prioridad e instrucciones (por ejemplo, cuántos extintores y su numeración).
5. Sigue el avance en **Comunicación con Taller**.

## Facturación electrónica

- Al entrar ves los comprobantes **de hoy** (por su fecha de emisión). Cambia **Desde / Hasta** o usa un atajo (**Hoy, Ayer, Esta semana, Este mes**); se aplica solo. Filtra por tipo o busca por número (F001-65), cliente o RUC dentro de esas fechas. La descarga en ZIP o Excel toma lo filtrado.
- Marca comprobantes (o "seleccionar los N del filtro") y pulsa **Descargar ZIP** (XML, CDR, PDF) o **Excel** (registro de ventas).
- Reenvía los observados o con excepción con el botón de reenviar.

## Cobranzas y alertas

- **Cobranzas**: cuotas pendientes y vencidas de tus ventas emitidas (un borrador o una venta anulada no se cobra); registra los pagos. Cobrar en **efectivo** exige la caja abierta.
- **Caja**: el arqueo es ciego: cuentas el cajón y recién al cerrar ves el esperado. Si anulas o rebajas una venta, la devolución sale de la caja del turno en que devuelves el dinero; si editas una venta de un turno anterior, solo se registra hoy la diferencia.
- **Órdenes listas**: una orden con el certificado emitido ya se puede entregar en mostrador (una sola vez).
- **Alertas de Vencimiento**: clientes con recarga o prueba hidrostática por vencer, para ofrecerles el servicio.

## Errores comunes

- "La factura solo se emite a clientes con RUC": el cliente tiene DNI; emite boleta.
- "No puede superar S/ 700": una boleta a Clientes varios pasó el límite; registra el DNI del cliente.
- "Stock insuficiente": no hay suficientes unidades en el almacén de tu sede.
- "Su RUC no está Activo y Habido": SUNAT no permite facturarle; revisa el RUC.
- No aparece un servicio en el buscador: pide al Gerente que lo cree en Servicios.
- "El código ... no está registrado": escaneaste un EPP cuyo código de barras no está en ningún producto; pide al Gerente que lo agregue.
- "Solo hay N en el almacén": no se puede vender más de lo que hay.
- Nota de crédito por **anulación**, **error de RUC** o **devolución total** que cubre el total: la venta se anula (vuelve el stock y se cierran las cuotas).

## Dirección del cliente en factura y boleta

- **Factura:** el cliente debe tener dirección fiscal. Normalmente viene sola al consultar el RUC; si falta, al elegir Factura aparece un recuadro rojo para escribirla y **Guardar dirección** (queda en su ficha).
- **Boleta:** la dirección es opcional. Si el cliente con DNI te la da, regístrala y saldrá impresa.

## Lista de ventas por fechas

- En **Ventas** ves solo **tus** ventas: cada vendedor corrige o anula lo suyo; el Gerente ve las de todos.
- Una factura, boleta o nota de venta anulada sale en el PDF con la marca de agua roja **ANULADO**; un certificado vencido o anulado sale con **VENCIDO** o **ANULADO**.
- Al entrar a **Ventas** ves solo las ventas **de hoy**. Arriba eliges **Desde / Hasta** o un atajo (**Hoy, Ayer, Esta semana, Este mes**); el cambio se aplica solo.
- Puedes buscar por cliente, RUC/DNI, número de venta o de comprobante.
- Los recuadros de arriba suman **tus** ventas emitidas en esas fechas: total, al contado, a crédito. "Por enviar a SUNAT" cuenta todo lo que aún falta enviar, sea del día que sea.

## Por vencer: por empresa

- **Por empresa** (vista principal): una tarjeta por cliente con cuántos extintores tiene, cuántos están vencidos o vencen en 30 días y su próximo vencimiento. Ábrela para ver cada extintor con su serie, recarga y prueba hidrostática. Botón **WhatsApp** para ofrecer la recarga; si el cliente no tiene celular sale **Agregar número**. También cuenta lo vendido **sin serie** (recargas y extintores por cantidad) y lo comprado en el sistema anterior: se marca _estimado_ y vence al año de la compra.
- Filtra por **Todas, Con vencidos, Próximos 30 días o Próximos 3 meses**, y busca por empresa, RUC o serie.
- **Avisos por extintor** es la lista de antes (vencidas, esta semana, este mes).
