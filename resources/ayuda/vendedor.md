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
5. Busca productos o servicios por nombre, código o código de barras y agrégalos. Ajusta cantidad, precio o descuento.
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

## Comprobante "por enviar" y corregir sin nota de crédito
- Al confirmar, la factura o boleta queda **por enviar** 6 horas y luego se envía sola a SUNAT.
- Mientras está por enviar, en el detalle de la venta puedes:
  - **Editar comprobante**: cambiar factura ↔ boleta o el cliente.
  - **Enviar ya**: mandarla a SUNAT en el momento.
  - **Anular venta**: si hubo un error de ítems; las unidades vuelven al stock.
- Si SUNAT la **rechaza**, usa **Corregir y reemitir**: sale un comprobante nuevo con otro número.
- Una vez **aceptada** por SUNAT solo se corrige con **nota de crédito** (en el mismo detalle de la venta).

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
- Filtra por tipo, mes o busca por número (F001-65), cliente o RUC.
- Marca comprobantes (o "seleccionar los N del filtro") y pulsa **Descargar ZIP** (XML, CDR, PDF) o **Excel** (registro de ventas).
- Reenvía los observados o con excepción con el botón de reenviar.

## Cobranzas y alertas
- **Cobranzas**: cuotas pendientes y vencidas de las ventas a crédito; registra los pagos.
- **Alertas de Vencimiento**: clientes con recarga o prueba hidrostática por vencer, para ofrecerles el servicio.

## Errores comunes
- "La factura solo se emite a clientes con RUC": el cliente tiene DNI; emite boleta.
- "No puede superar S/ 700": una boleta a Clientes varios pasó el límite; registra el DNI del cliente.
- "Stock insuficiente": no hay suficientes unidades en el almacén de tu sede.
- "Su RUC no está Activo y Habido": SUNAT no permite facturarle; revisa el RUC.
- No aparece un servicio en el buscador: pide al Gerente que lo cree en Servicios.
